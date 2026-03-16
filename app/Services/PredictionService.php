<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\User;
use Carbon\Carbon;

class PredictionService
{
    // Population prior — anchors early predictions before enough data exists
    private const PRIOR_CYCLE_LENGTH = 28.0;
    private const PRIOR_VARIANCE = 9.0; // std dev ~3 days

    public function predict(User $user): ?array
    {
        $cycles = Cycle::where('user_id', $user->id)
            ->whereNotNull('cycle_length')
            ->orderBy('start_date', 'asc')
            ->get();

        if ($cycles->isEmpty()) {
            return null;
        }

        $lastCycle = Cycle::where('user_id', $user->id)
            ->orderBy('start_date', 'desc')
            ->first();

        // Chronological order for time-series methods
        $lengths = $cycles->pluck('cycle_length')->toArray();
        $n = count($lengths);

        // --- Run all prediction methods ---
        $kalman = $this->kalmanFilter($lengths);
        $pattern = $this->detectPattern($lengths);
        $changepoint = $this->detectChangepoint($lengths);
        $trend = $this->detectTrend($lengths, $changepoint['start_index']);

        // --- Ensemble: weight methods by backtest accuracy ---
        $ensemble = $this->ensemble($lengths, $kalman, $pattern, $trend, $changepoint);

        // --- Period length estimate ---
        $periodLengths = $cycles->whereNotNull('period_length')
            ->pluck('period_length')->toArray();
        $avgPeriodLength = !empty($periodLengths)
            ? $this->kalmanFilter($periodLengths)['estimate']
            : 5.0;

        // --- Confidence from ensemble uncertainty ---
        $confidence = $this->computeConfidence($lengths, $ensemble, $changepoint);

        $predictedCycleLength = max(18, min(50, $ensemble['estimate']));
        $predictedStart = $lastCycle->start_date->copy()->addDays(round($predictedCycleLength));
        $predictedEnd = $predictedStart->copy()->addDays(round($avgPeriodLength) - 1);

        // --- Ovulation & fertile window ---
        $ovulation = $this->predictOvulation($predictedStart, $predictedCycleLength, $lengths);

        return [
            'predicted_start' => $predictedStart,
            'predicted_end' => $predictedEnd,
            'avg_cycle_length' => round($predictedCycleLength, 1),
            'avg_period_length' => round($avgPeriodLength, 1),
            'confidence_window' => $confidence['window'],
            'confidence_pct' => $confidence['pct'],
            'window_start' => $predictedStart->copy()->subDays($confidence['window']),
            'window_end' => $predictedStart->copy()->addDays($confidence['window']),
            'cycles_analyzed' => $n,
            'days_until' => (int) now()->diffInDays($predictedStart, false),
            'trend' => $trend['direction'],
            'regularity' => $confidence['regularity'],
            'method' => $ensemble['primary_method'],
            'pattern' => $pattern['type'],
            'ovulation' => $ovulation,
        ];
    }

    // ===================================================================
    //  METHOD 1: KALMAN FILTER
    //  Optimal sequential estimator. Same math that makes GPS work.
    //  Treats each cycle as a noisy measurement of your "true" cycle length.
    //  Automatically balances between trusting new data vs. stability.
    // ===================================================================

    private function kalmanFilter(array $lengths): array
    {
        $n = count($lengths);

        // Initialize with population prior
        $estimate = self::PRIOR_CYCLE_LENGTH;
        $errorVariance = self::PRIOR_VARIANCE;

        // Process noise: how much we expect the true cycle to drift per cycle
        // Lower = more stable predictions, higher = more responsive to changes
        $processNoise = 0.5;

        // Measurement noise: estimated from data or default
        $measurementNoise = $n >= 3
            ? max(1.0, $this->variance(array_slice($lengths, -min($n, 6))))
            : 4.0;

        $estimates = [];
        $gains = [];

        foreach ($lengths as $measurement) {
            // Predict step: state stays the same, uncertainty grows
            $predictedVariance = $errorVariance + $processNoise;

            // Update step: incorporate new measurement
            $kalmanGain = $predictedVariance / ($predictedVariance + $measurementNoise);
            $estimate = $estimate + $kalmanGain * ($measurement - $estimate);
            $errorVariance = (1 - $kalmanGain) * $predictedVariance;

            $estimates[] = $estimate;
            $gains[] = $kalmanGain;
        }

        return [
            'estimate' => $estimate,
            'variance' => $errorVariance,
            'estimates' => $estimates,
            'last_gain' => end($gains),
        ];
    }

    // ===================================================================
    //  METHOD 2: PATTERN RECOGNITION
    //  Detects repeating structures that simple averages destroy:
    //  - Alternating (long/short/long/short)
    //  - Seasonal (quarterly rhythm)
    //  - Bimodal (two distinct cycle lengths)
    // ===================================================================

    private function detectPattern(array $lengths): array
    {
        $n = count($lengths);

        if ($n < 4) {
            return ['type' => 'none', 'prediction' => null, 'strength' => 0.0];
        }

        $patterns = [];

        // --- Check for alternating pattern ---
        $patterns['alternating'] = $this->checkAlternating($lengths);

        // --- Check for bimodal distribution ---
        $patterns['bimodal'] = $this->checkBimodal($lengths);

        // Return strongest pattern
        $best = ['type' => 'none', 'prediction' => null, 'strength' => 0.0];
        foreach ($patterns as $type => $result) {
            if ($result['strength'] > $best['strength'] && $result['strength'] > 0.6) {
                $best = $result;
                $best['type'] = $type;
            }
        }

        return $best;
    }

    private function checkAlternating(array $lengths): array
    {
        $n = count($lengths);
        if ($n < 4) return ['strength' => 0, 'prediction' => null];

        // Split into odd/even indexed cycles
        $odd = $even = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i % 2 === 0) $even[] = $lengths[$i];
            else $odd[] = $lengths[$i];
        }

        $evenMean = array_sum($even) / count($even);
        $oddMean = array_sum($odd) / count($odd);

        // Are even/odd significantly different from each other?
        $overallMean = array_sum($lengths) / $n;
        $separation = abs($evenMean - $oddMean);
        $overallStd = sqrt($this->variance($lengths));

        if ($overallStd < 0.5) return ['strength' => 0, 'prediction' => null];

        // Strength: ratio of between-group difference to within-group variation
        $evenStd = count($even) > 1 ? sqrt($this->variance($even)) : $overallStd;
        $oddStd = count($odd) > 1 ? sqrt($this->variance($odd)) : $overallStd;
        $withinGroupStd = ($evenStd + $oddStd) / 2;

        $strength = $withinGroupStd > 0.1
            ? min(1.0, $separation / ($withinGroupStd * 3))
            : 0;

        // Next prediction depends on whether next index is odd or even
        $nextIsEven = ($n % 2 === 0);
        $prediction = $nextIsEven ? $evenMean : $oddMean;

        return ['strength' => $strength, 'prediction' => $prediction];
    }

    private function checkBimodal(array $lengths): array
    {
        $n = count($lengths);
        if ($n < 5) return ['strength' => 0, 'prediction' => null];

        // Simple k-means with k=2
        $sorted = $lengths;
        sort($sorted);
        $split = intdiv($n, 2);

        $clusterA = array_slice($sorted, 0, $split);
        $clusterB = array_slice($sorted, $split);

        $meanA = array_sum($clusterA) / count($clusterA);
        $meanB = array_sum($clusterB) / count($clusterB);

        // Reassign based on distance to means (one iteration of k-means)
        $clusterA = $clusterB = [];
        foreach ($lengths as $l) {
            if (abs($l - $meanA) < abs($l - $meanB)) $clusterA[] = $l;
            else $clusterB[] = $l;
        }

        if (empty($clusterA) || empty($clusterB)) {
            return ['strength' => 0, 'prediction' => null];
        }

        $meanA = array_sum($clusterA) / count($clusterA);
        $meanB = array_sum($clusterB) / count($clusterB);
        $separation = abs($meanA - $meanB);

        $stdA = count($clusterA) > 1 ? sqrt($this->variance($clusterA)) : 1;
        $stdB = count($clusterB) > 1 ? sqrt($this->variance($clusterB)) : 1;
        $avgStd = ($stdA + $stdB) / 2;

        // Fisher criterion: separation relative to within-cluster spread
        $strength = $avgStd > 0.1 ? min(1.0, $separation / ($avgStd * 4)) : 0;

        // Predict: which cluster does the most recent cycle belong to?
        $lastVal = end($lengths);
        $inA = abs($lastVal - $meanA) < abs($lastVal - $meanB);

        // Next cycle is likely from the *other* cluster if bimodal
        $prediction = $inA ? $meanB : $meanA;

        return ['strength' => $strength, 'prediction' => $prediction];
    }

    // ===================================================================
    //  METHOD 3: CHANGEPOINT DETECTION (CUSUM)
    //  Detects when your cycle regime shifts — e.g., went on birth control,
    //  major stress event, lifestyle change. Only uses post-change data
    //  instead of polluting predictions with an old regime.
    // ===================================================================

    private function detectChangepoint(array $lengths): array
    {
        $n = count($lengths);

        if ($n < 4) {
            return ['detected' => false, 'start_index' => 0, 'message' => null];
        }

        $mean = array_sum($lengths) / $n;
        $std = sqrt($this->variance($lengths));

        if ($std < 1.0) {
            return ['detected' => false, 'start_index' => 0, 'message' => null];
        }

        // CUSUM: cumulative sum of deviations from mean
        // A changepoint shows as a peak in |CUSUM|
        $cusum = [];
        $cumulative = 0;
        foreach ($lengths as $l) {
            $cumulative += ($l - $mean);
            $cusum[] = $cumulative;
        }

        // Find max absolute CUSUM — that's the likely changepoint
        $maxAbs = 0;
        $changepointIndex = 0;
        foreach ($cusum as $i => $val) {
            if (abs($val) > $maxAbs) {
                $maxAbs = abs($val);
                $changepointIndex = $i;
            }
        }

        // Significance test: is the change meaningful?
        // Compare means before/after the changepoint
        $before = array_slice($lengths, 0, $changepointIndex + 1);
        $after = array_slice($lengths, $changepointIndex + 1);

        if (count($before) < 2 || count($after) < 2) {
            return ['detected' => false, 'start_index' => 0, 'message' => null];
        }

        $meanBefore = array_sum($before) / count($before);
        $meanAfter = array_sum($after) / count($after);
        $diff = abs($meanBefore - $meanAfter);

        // Welch's t-test approximation for significance
        $varBefore = $this->variance($before);
        $varAfter = $this->variance($after);
        $se = sqrt($varBefore / count($before) + $varAfter / count($after));

        $significant = $se > 0 && ($diff / $se) > 2.0 && $diff > 2.0;

        if ($significant) {
            $startIndex = $changepointIndex + 1;
            $direction = $meanAfter > $meanBefore ? 'longer' : 'shorter';
            return [
                'detected' => true,
                'start_index' => $startIndex,
                'message' => "Cycle shift detected: cycles got {$direction} (avg " . round($meanBefore, 1) . " → " . round($meanAfter, 1) . " days)",
            ];
        }

        return ['detected' => false, 'start_index' => 0, 'message' => null];
    }

    // ===================================================================
    //  METHOD 4: TREND DETECTION (post-changepoint)
    //  Weighted linear regression, but only on the current regime.
    // ===================================================================

    private function detectTrend(array $lengths, int $startIndex): array
    {
        $regime = array_slice($lengths, $startIndex);
        $n = count($regime);

        if ($n < 4) {
            return ['slope' => 0.0, 'direction' => 'stable', 'prediction' => null];
        }

        // Weighted least squares with exponential recency
        $decay = 0.25;
        $sumW = $sumWX = $sumWY = $sumWXX = $sumWXY = 0;

        for ($i = 0; $i < $n; $i++) {
            $w = exp(-$decay * ($n - 1 - $i));
            $sumW += $w;
            $sumWX += $w * $i;
            $sumWY += $w * $regime[$i];
            $sumWXX += $w * $i * $i;
            $sumWXY += $w * $i * $regime[$i];
        }

        $denom = $sumW * $sumWXX - $sumWX * $sumWX;
        if (abs($denom) < 1e-10) {
            return ['slope' => 0.0, 'direction' => 'stable', 'prediction' => null];
        }

        $slope = ($sumW * $sumWXY - $sumWX * $sumWY) / $denom;
        $intercept = ($sumWY - $slope * $sumWX) / $sumW;

        // Project one step ahead
        $prediction = $intercept + $slope * $n;

        // R² to assess fit quality
        $meanY = $sumWY / $sumW;
        $ssTot = $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $w = exp(-$decay * ($n - 1 - $i));
            $predicted = $intercept + $slope * $i;
            $ssRes += $w * ($regime[$i] - $predicted) ** 2;
            $ssTot += $w * ($regime[$i] - $meanY) ** 2;
        }
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        $direction = 'stable';
        if (abs($slope) > 0.3 && $r2 > 0.3) {
            $direction = $slope > 0 ? 'lengthening' : 'shortening';
        }

        return [
            'slope' => $slope,
            'direction' => $direction,
            'prediction' => $prediction,
            'r2' => $r2,
        ];
    }

    // ===================================================================
    //  ENSEMBLE: Self-tuning combination
    //  Backtests each method on historical data to see which has been
    //  most accurate for THIS user, then weights accordingly.
    // ===================================================================

    private function ensemble(array $lengths, array $kalman, array $pattern, array $trend, array $changepoint): array
    {
        $n = count($lengths);

        // Collect candidate predictions
        $candidates = [];

        $candidates['kalman'] = $kalman['estimate'];

        if ($pattern['prediction'] !== null && $pattern['strength'] > 0.6) {
            $candidates['pattern'] = $pattern['prediction'];
        }

        if ($trend['prediction'] !== null && $trend['direction'] !== 'stable') {
            $candidates['trend'] = $trend['prediction'];
        }

        // Post-changepoint simple average (if changepoint detected, use only recent regime)
        if ($changepoint['detected']) {
            $regime = array_slice($lengths, $changepoint['start_index']);
            if (!empty($regime)) {
                $candidates['regime'] = array_sum($regime) / count($regime);
            }
        }

        // With < 4 cycles, just trust the Kalman filter
        if ($n < 4) {
            return [
                'estimate' => $kalman['estimate'],
                'primary_method' => 'kalman',
                'weights' => ['kalman' => 1.0],
            ];
        }

        // --- Backtest each method ---
        $errors = [];
        foreach (array_keys($candidates) as $method) {
            $errors[$method] = $this->backtestMethod($method, $lengths, $changepoint);
        }

        // --- Convert errors to weights (inverse error, softmax-style) ---
        $weights = [];
        $minError = max(0.1, min($errors)); // avoid division by zero

        foreach ($errors as $method => $error) {
            // Inverse squared error — strong preference for accurate methods
            $weights[$method] = 1.0 / max($error, $minError) ** 2;
        }

        // Normalize
        $totalWeight = array_sum($weights);
        foreach ($weights as $method => $w) {
            $weights[$method] = $w / $totalWeight;
        }

        // Weighted ensemble prediction
        $estimate = 0;
        foreach ($candidates as $method => $prediction) {
            $estimate += $prediction * $weights[$method];
        }

        // Primary method = highest weight
        arsort($weights);
        $primaryMethod = array_key_first($weights);

        return [
            'estimate' => $estimate,
            'primary_method' => $primaryMethod,
            'weights' => $weights,
        ];
    }

    /**
     * Backtest a method: simulate predicting each cycle from past data only.
     * Returns mean absolute error.
     */
    private function backtestMethod(string $method, array $lengths, array $changepoint): float
    {
        $n = count($lengths);
        $errors = [];

        // Need at least 3 past cycles to backtest from
        $startFrom = max(3, $changepoint['detected'] ? $changepoint['start_index'] + 2 : 3);

        for ($i = $startFrom; $i < $n; $i++) {
            $past = array_slice($lengths, 0, $i);
            $actual = $lengths[$i];
            $predicted = null;

            switch ($method) {
                case 'kalman':
                    $result = $this->kalmanFilter($past);
                    $predicted = $result['estimate'];
                    break;

                case 'pattern':
                    $result = $this->detectPattern($past);
                    $predicted = $result['prediction'];
                    break;

                case 'trend':
                    $cpStart = $changepoint['detected'] ? $changepoint['start_index'] : 0;
                    $result = $this->detectTrend($past, min($cpStart, count($past) - 3));
                    $predicted = $result['prediction'];
                    break;

                case 'regime':
                    if ($changepoint['detected'] && $changepoint['start_index'] < $i) {
                        $regime = array_slice($past, $changepoint['start_index']);
                        $predicted = !empty($regime) ? array_sum($regime) / count($regime) : null;
                    }
                    break;
            }

            if ($predicted !== null) {
                $errors[] = abs($actual - $predicted);
            }
        }

        return !empty($errors) ? array_sum($errors) / count($errors) : 99.0;
    }

    // ===================================================================
    //  CONFIDENCE ESTIMATION
    // ===================================================================

    private function computeConfidence(array $lengths, array $ensemble, array $changepoint): array
    {
        // Use post-changepoint data for confidence if applicable
        $regime = $changepoint['detected']
            ? array_slice($lengths, $changepoint['start_index'])
            : $lengths;

        $n = count($regime);
        $estimate = $ensemble['estimate'];

        if ($n < 2) {
            return ['window' => 3, 'pct' => 60, 'regularity' => 'new'];
        }

        $std = sqrt($this->variance($regime));

        // Coefficient of variation
        $cv = $estimate > 0 ? $std / $estimate : 1;

        if ($cv < 0.04) $regularity = 'very regular';
        elseif ($cv < 0.08) $regularity = 'regular';
        elseif ($cv < 0.15) $regularity = 'somewhat irregular';
        else $regularity = 'irregular';

        // Window: use prediction interval (accounts for both variance and sample size)
        // t-distribution factor for small samples, approximated
        $tFactor = $n < 6 ? 1.8 : ($n < 10 ? 1.6 : 1.5);
        $predictionStd = $std * sqrt(1 + 1 / $n); // prediction interval, not confidence interval
        $window = max(1, round($predictionStd * $tFactor));

        // Confidence: based on how tight the window is relative to cycle length
        $windowRatio = $estimate > 0 ? $window / $estimate : 1;

        if ($windowRatio < 0.05) $pct = 95;
        elseif ($windowRatio < 0.08) $pct = 90;
        elseif ($windowRatio < 0.12) $pct = 85;
        elseif ($windowRatio < 0.18) $pct = 78;
        elseif ($windowRatio < 0.25) $pct = 70;
        else $pct = 60;

        // Bonus for more data
        $pct = min(96, $pct + max(0, $n - 4));

        return ['window' => $window, 'pct' => $pct, 'regularity' => $regularity];
    }

    // ===================================================================
    //  OVULATION & FERTILE WINDOW PREDICTION
    //  The luteal phase (ovulation → period) is biologically the most
    //  stable part of the cycle at ~12-16 days (mean 14). The follicular
    //  phase (period → ovulation) is what actually varies between people.
    //
    //  Strategy: estimate each user's personal luteal phase length from
    //  their cycle history, then count backwards from the predicted
    //  next period start. This is far more accurate than the naive
    //  "day 14" rule most apps use.
    // ===================================================================

    private function predictOvulation(Carbon $predictedPeriodStart, float $cycleLength, array $cycleLengths): array
    {
        $n = count($cycleLengths);
        $lutealEstimate = $this->estimateLutealPhase($cycleLengths);
        $lutealDays = (int) round($lutealEstimate);
        $cycleDays = (int) round($cycleLength);
        $today = now()->startOfDay();

        // Current cycle's ovulation: count backwards from predicted next period
        $currentOvulation = $predictedPeriodStart->copy()->subDays($lutealDays);

        // If this cycle's fertile window has fully passed, project the NEXT cycle
        $currentFertileEnd = $currentOvulation->copy()->addDays(1);
        if ($today->gt($currentFertileEnd)) {
            // Next cycle: predicted period start + one more cycle length - luteal phase
            $nextPeriodStart = $predictedPeriodStart->copy()->addDays($cycleDays);
            $ovulationDate = $nextPeriodStart->copy()->subDays($lutealDays);
            $isFutureCycle = true;
        } else {
            $ovulationDate = $currentOvulation;
            $isFutureCycle = false;
        }

        // Fertile window: sperm survives ~5 days, egg survives ~1 day
        $fertileStart = $ovulationDate->copy()->subDays(5);
        $fertileEnd = $ovulationDate->copy()->addDays(1);

        // Peak fertility: 2 days before ovulation + ovulation day
        $peakStart = $ovulationDate->copy()->subDays(2);
        $peakEnd = $ovulationDate->copy();

        // Confidence
        $lutealConfidence = 'moderate';
        if ($n >= 6) {
            $cv = sqrt($this->variance($cycleLengths)) / (array_sum($cycleLengths) / $n);
            if ($cv < 0.08) $lutealConfidence = 'high';
            elseif ($cv > 0.18) $lutealConfidence = 'low';
        }
        // Future cycle predictions are inherently less certain
        if ($isFutureCycle && $lutealConfidence === 'high') {
            $lutealConfidence = 'moderate';
        }

        $daysUntilOvulation = (int) now()->diffInDays($ovulationDate, false);

        // Current fertility status
        if ($today->lt($fertileStart)) {
            $status = 'not fertile';
        } elseif ($today->gte($fertileStart) && $today->lt($peakStart)) {
            $status = 'fertile';
        } elseif ($today->gte($peakStart) && $today->lte($peakEnd)) {
            $status = 'peak fertility';
        } elseif ($today->gt($peakEnd) && $today->lte($fertileEnd)) {
            $status = 'fertile';
        } else {
            $status = 'not fertile';
        }

        return [
            'date' => $ovulationDate,
            'luteal_length' => round($lutealEstimate, 1),
            'fertile_start' => $fertileStart,
            'fertile_end' => $fertileEnd,
            'peak_start' => $peakStart,
            'peak_end' => $peakEnd,
            'days_until_ovulation' => $daysUntilOvulation,
            'confidence' => $lutealConfidence,
            'status' => $status,
            'is_next_cycle' => $isFutureCycle,
        ];
    }

    /**
     * Estimate personal luteal phase length.
     *
     * The luteal phase is the most consistent part of the cycle (~14 days ± 1-2).
     * Since we can't directly measure it without BBT/LH data, we infer it:
     *
     * - With few cycles: use population mean of 14 days
     * - With more data: use the relationship between cycle variability and
     *   luteal stability. If cycles are very regular, the luteal phase is
     *   likely very consistent (use 14). If cycles vary, the variation is
     *   almost entirely in the follicular phase, so we still estimate ~14
     *   but with wider uncertainty.
     * - Cycle length adjusts the estimate: shorter cycles tend to have
     *   slightly shorter luteal phases, longer cycles slightly longer.
     */
    private function estimateLutealPhase(array $cycleLengths): float
    {
        $n = count($cycleLengths);
        $meanCycle = array_sum($cycleLengths) / $n;

        // Base: population mean luteal phase
        $baseLuteal = 14.0;

        // Adjust slightly based on cycle length
        // Research shows luteal phase correlates weakly with cycle length
        // ~0.1 day adjustment per day deviation from 28
        $cycleDelta = $meanCycle - 28.0;
        $lutealAdjustment = $cycleDelta * 0.1;

        // Clamp adjustment: luteal phase realistically ranges 10-17 days
        $estimate = $baseLuteal + $lutealAdjustment;
        $estimate = max(10, min(17, $estimate));

        // With sparse data, pull toward population mean
        if ($n < 4) {
            $dataWeight = $n / 6;
            $estimate = $baseLuteal * (1 - $dataWeight) + $estimate * $dataWeight;
        }

        return $estimate;
    }

    // ===================================================================
    //  MATH HELPERS
    // ===================================================================

    private function variance(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 4.0;

        $mean = array_sum($values) / $n;
        $sum = 0;
        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return $sum / ($n - 1);
    }
}
