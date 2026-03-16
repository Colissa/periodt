<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ImportService
{
    /**
     * Parse an uploaded file and return normalized period entries.
     * Auto-detects format from popular apps.
     *
     * Returns: ['periods' => [...], 'format' => string, 'warnings' => [...]]
     */
    /**
     * Parse a list of manually entered dates into period entries.
     */
    public function parseManualDates(string $rawInput): array
    {
        $lines = preg_split('/[\n,;]+/', $rawInput);
        $periods = [];
        $warnings = [];

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Support "start - end" or "start to end" format
            $parts = preg_split('/\s*[-–—]\s*|\s+to\s+/i', $line, 2);
            $startDate = $this->parseDate(trim($parts[0]));

            if (!$startDate) {
                $warnings[] = "Could not parse date: '{$line}'";
                continue;
            }

            if (Carbon::parse($startDate)->isFuture()) continue;

            $endDate = null;
            if (isset($parts[1]) && !empty(trim($parts[1]))) {
                $endDate = $this->parseDate(trim($parts[1]));
            }

            $periods[] = [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        return ['periods' => $periods, 'format' => 'manual', 'warnings' => $warnings];
    }

    public function parse(string $contents, string $extension): array
    {
        if ($extension === 'xml') {
            return $this->parseAppleHealth($contents);
        }

        if ($extension === 'json') {
            return $this->parseSamsungHealth($contents);
        }

        // CSV-based formats
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $contents)));
        if (count($lines) < 2) {
            return ['periods' => [], 'format' => 'unknown', 'warnings' => ['File appears empty or has no data rows.']];
        }

        $header = str_getcsv(array_shift($lines));
        $headerLower = array_map(fn($h) => strtolower(trim($h)), $header);

        // Auto-detect format from header
        $format = $this->detectCsvFormat($headerLower);

        return match ($format) {
            'clue' => $this->parseClue($lines, $header, $headerLower),
            'flo' => $this->parseFlo($lines, $header, $headerLower),
            'generic' => $this->parseGenericCsv($lines, $header, $headerLower),
            default => ['periods' => [], 'format' => 'unknown', 'warnings' => ['Could not detect CSV format. See supported formats below.']],
        };
    }

    /**
     * Import parsed periods into the database for a user.
     * Skips duplicates (same start_date), calculates cycle lengths.
     */
    public function import(User $user, array $periods): array
    {
        $imported = 0;
        $skipped = 0;

        // Sort by start date ascending
        usort($periods, fn($a, $b) => $a['start_date'] <=> $b['start_date']);

        $existingDates = Cycle::where('user_id', $user->id)
            ->pluck('start_date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        foreach ($periods as $period) {
            $startDate = $period['start_date'];

            if (in_array($startDate, $existingDates)) {
                $skipped++;
                continue;
            }

            $endDate = $period['end_date'] ?? null;
            $periodLength = null;
            if ($endDate) {
                $periodLength = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            }

            Cycle::create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_length' => $periodLength,
            ]);

            $existingDates[] = $startDate;
            $imported++;
        }

        // Recalculate all cycle lengths in order
        $this->recalculateCycleLengths($user->id);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function detectCsvFormat(array $headerLower): string
    {
        // Clue: "Date", "Day in cycle", "Period - type", ...
        if (in_array('day in cycle', $headerLower) && $this->headerContains($headerLower, 'period')) {
            return 'clue';
        }

        // Flo: "Start Date", "End Date", "Cycle Length", "Period Length"
        // or "Dates", "Cycle day", "Period flow"
        if ($this->headerContains($headerLower, 'period flow') || $this->headerContains($headerLower, 'period length')) {
            if ($this->headerContains($headerLower, 'start') || $this->headerContains($headerLower, 'dates')) {
                return 'flo';
            }
        }

        // Generic: any CSV with columns that look like date fields
        if ($this->headerContains($headerLower, 'start') || $this->headerContains($headerLower, 'date')) {
            return 'generic';
        }

        return 'unknown';
    }

    /**
     * Parse Clue CSV export.
     * Clue exports one row per day, with "Period - type" containing flow level.
     * We need to group consecutive period days into cycles.
     */
    private function parseClue(array $lines, array $header, array $headerLower): array
    {
        $dateCol = array_search('date', $headerLower);
        $periodCol = $this->findColumnContaining($headerLower, 'period');

        if ($dateCol === false || $periodCol === false) {
            return ['periods' => [], 'format' => 'clue', 'warnings' => ['Could not find required columns (Date, Period) in Clue export.']];
        }

        // Collect all dates that have period data
        $periodDates = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (!isset($row[$dateCol], $row[$periodCol])) continue;

            $periodVal = strtolower(trim($row[$periodCol]));
            if (!empty($periodVal) && $periodVal !== 'none' && $periodVal !== 'excluded') {
                $date = $this->parseDate(trim($row[$dateCol]));
                if ($date) {
                    $periodDates[] = $date;
                }
            }
        }

        sort($periodDates);

        return [
            'periods' => $this->groupConsecutiveDates($periodDates),
            'format' => 'clue',
            'warnings' => [],
        ];
    }

    /**
     * Parse Flo CSV export.
     * Flo can export as cycle summaries (Start Date, End Date) or daily logs.
     */
    private function parseFlo(array $lines, array $header, array $headerLower): array
    {
        // Check if it's a cycle summary format
        $startCol = $this->findColumnContaining($headerLower, 'start');
        $endCol = $this->findColumnContaining($headerLower, 'end');

        if ($startCol !== false) {
            return $this->parseDatePairCsv($lines, $startCol, $endCol, 'flo');
        }

        // Daily log format — look for date + period flow columns
        $dateCol = $this->findColumnContaining($headerLower, 'date');
        $flowCol = $this->findColumnContaining($headerLower, 'period flow');
        if ($flowCol === false) {
            $flowCol = $this->findColumnContaining($headerLower, 'flow');
        }

        if ($dateCol === false || $flowCol === false) {
            return ['periods' => [], 'format' => 'flo', 'warnings' => ['Could not find required columns in Flo export.']];
        }

        $periodDates = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (!isset($row[$dateCol], $row[$flowCol])) continue;

            $flowVal = strtolower(trim($row[$flowCol]));
            if (!empty($flowVal) && $flowVal !== 'none' && $flowVal !== '0' && $flowVal !== 'no') {
                $date = $this->parseDate(trim($row[$dateCol]));
                if ($date) {
                    $periodDates[] = $date;
                }
            }
        }

        sort($periodDates);

        return [
            'periods' => $this->groupConsecutiveDates($periodDates),
            'format' => 'flo',
            'warnings' => [],
        ];
    }

    /**
     * Parse generic CSV with start_date and optional end_date columns.
     * Flexible column name matching.
     */
    private function parseGenericCsv(array $lines, array $header, array $headerLower): array
    {
        $startCol = $this->findColumnContaining($headerLower, 'start');
        if ($startCol === false) {
            $startCol = $this->findColumnContaining($headerLower, 'date');
        }
        $endCol = $this->findColumnContaining($headerLower, 'end');

        if ($startCol === false) {
            return ['periods' => [], 'format' => 'generic', 'warnings' => ['Could not find a date/start column.']];
        }

        return $this->parseDatePairCsv($lines, $startCol, $endCol, 'generic');
    }

    /**
     * Parse CSV with start/end date columns (cycle summary format).
     */
    private function parseDatePairCsv(array $lines, int $startCol, ?int $endCol, string $format): array
    {
        $periods = [];
        $warnings = [];

        foreach ($lines as $i => $line) {
            $row = str_getcsv($line);
            if (!isset($row[$startCol]) || empty(trim($row[$startCol]))) continue;

            $startDate = $this->parseDate(trim($row[$startCol]));
            if (!$startDate) {
                $warnings[] = "Row " . ($i + 2) . ": could not parse start date '{$row[$startCol]}'.";
                continue;
            }

            $endDate = null;
            if ($endCol !== false && $endCol !== null && isset($row[$endCol]) && !empty(trim($row[$endCol]))) {
                $endDate = $this->parseDate(trim($row[$endCol]));
            }

            // Sanity: skip future dates
            if (Carbon::parse($startDate)->isFuture()) continue;

            $periods[] = [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        return ['periods' => $periods, 'format' => $format, 'warnings' => $warnings];
    }

    /**
     * Parse Apple Health XML export.
     * Looks for menstrual flow records in HKCategoryTypeIdentifierMenstrualFlow.
     */
    private function parseAppleHealth(string $contents): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);

        if ($xml === false) {
            return ['periods' => [], 'format' => 'apple_health', 'warnings' => ['Could not parse XML file.']];
        }

        $periodDates = [];

        // Apple Health exports records as <Record> elements
        foreach ($xml->xpath('//Record[@type="HKCategoryTypeIdentifierMenstrualFlow"]') as $record) {
            $startDate = (string) $record['startDate'];
            $date = $this->parseDate(substr($startDate, 0, 10));
            if ($date) {
                $periodDates[] = $date;
            }
        }

        if (empty($periodDates)) {
            return ['periods' => [], 'format' => 'apple_health', 'warnings' => ['No menstrual flow data found in Apple Health export.']];
        }

        $periodDates = array_unique($periodDates);
        sort($periodDates);

        return [
            'periods' => $this->groupConsecutiveDates($periodDates),
            'format' => 'apple_health',
            'warnings' => [],
        ];
    }

    /**
     * Parse Samsung Health JSON export.
     * Samsung Health stores menstrual data in com.samsung.health.menstrual_period
     * or in the tracker data. Also handles the CSV-inside-zip format from dev mode.
     */
    private function parseSamsungHealth(string $contents): array
    {
        $data = json_decode($contents, true);

        if ($data === null) {
            return ['periods' => [], 'format' => 'samsung_health', 'warnings' => ['Could not parse JSON file.']];
        }

        $periodDates = [];

        // Samsung Health JSON can have different structures depending on export method
        // Structure 1: Array of records at top level
        $records = $data;

        // Structure 2: Nested under a key
        if (isset($data['data'])) {
            $records = $data['data'];
        } elseif (isset($data['records'])) {
            $records = $data['records'];
        }

        if (!is_array($records)) {
            return ['periods' => [], 'format' => 'samsung_health', 'warnings' => ['Unexpected JSON structure. Try exporting as CSV instead.']];
        }

        foreach ($records as $record) {
            if (!is_array($record)) continue;

            // Look for menstrual period records
            // Samsung uses start_time/end_time or start_date/end_date
            $startDate = null;
            $endDate = null;

            // Try various Samsung Health field names
            foreach (['start_time', 'start_date', 'startTime', 'startDate', 'date'] as $field) {
                if (isset($record[$field]) && !empty($record[$field])) {
                    $startDate = $this->parseDate(substr($record[$field], 0, 10));
                    if ($startDate) break;
                }
            }

            foreach (['end_time', 'end_date', 'endTime', 'endDate'] as $field) {
                if (isset($record[$field]) && !empty($record[$field])) {
                    $endDate = $this->parseDate(substr($record[$field], 0, 10));
                    if ($endDate) break;
                }
            }

            if ($startDate && !Carbon::parse($startDate)->isFuture()) {
                $periodDates[] = ['start_date' => $startDate, 'end_date' => $endDate];
            }
        }

        if (empty($periodDates)) {
            return ['periods' => [], 'format' => 'samsung_health', 'warnings' => [
                'No period data found in this JSON file. Samsung Health may not include menstrual data in standard exports. Try the Quick Entry option instead.',
            ]];
        }

        // If we got start/end pairs, use them directly
        // If we only got individual dates, group them
        $hasEndDates = collect($periodDates)->whereNotNull('end_date')->isNotEmpty();
        if ($hasEndDates) {
            return ['periods' => $periodDates, 'format' => 'samsung_health', 'warnings' => []];
        }

        // Group individual dates
        $dates = array_map(fn($p) => $p['start_date'], $periodDates);
        $dates = array_unique($dates);
        sort($dates);

        return [
            'periods' => $this->groupConsecutiveDates($dates),
            'format' => 'samsung_health',
            'warnings' => [],
        ];
    }

    /**
     * Group consecutive dates into period start/end pairs.
     * Gap of >2 days means a new period.
     */
    private function groupConsecutiveDates(array $dates): array
    {
        if (empty($dates)) return [];

        $periods = [];
        $currentStart = $dates[0];
        $currentEnd = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $daysDiff = Carbon::parse($currentEnd)->diffInDays(Carbon::parse($dates[$i]));

            if ($daysDiff <= 2) {
                // Continuation of same period (allow 1-day gap for missed tracking)
                $currentEnd = $dates[$i];
            } else {
                // New period
                $periods[] = [
                    'start_date' => $currentStart,
                    'end_date' => $currentEnd,
                ];
                $currentStart = $dates[$i];
                $currentEnd = $dates[$i];
            }
        }

        // Don't forget the last period
        $periods[] = [
            'start_date' => $currentStart,
            'end_date' => $currentEnd,
        ];

        return $periods;
    }

    private function recalculateCycleLengths(int $userId): void
    {
        $cycles = Cycle::where('user_id', $userId)
            ->orderBy('start_date')
            ->get();

        for ($i = 0; $i < $cycles->count() - 1; $i++) {
            $cycles[$i]->cycle_length = $cycles[$i]->start_date->diffInDays($cycles[$i + 1]->start_date);
            $cycles[$i]->save();
        }

        if ($cycles->isNotEmpty()) {
            $last = $cycles->last();
            $last->cycle_length = null;
            $last->save();
        }
    }

    /**
     * Try multiple date formats. People and apps are inconsistent.
     */
    private function parseDate(string $value): ?string
    {
        $formats = [
            'Y-m-d',
            'm/d/Y',
            'd/m/Y',
            'M d, Y',
            'M j, Y',
            'd M Y',
            'Y/m/d',
            'm-d-Y',
            'd-m-Y',
            'n/j/Y',
            'n/j/y',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        // Fallback: let Carbon try
        try {
            $date = Carbon::parse($value);
            if ($date->year > 2000 && $date->year <= now()->year) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception) {
            // nope
        }

        return null;
    }

    private function headerContains(array $headerLower, string $needle): bool
    {
        foreach ($headerLower as $col) {
            if (str_contains($col, $needle)) return true;
        }
        return false;
    }

    private function findColumnContaining(array $headerLower, string $needle): int|false
    {
        foreach ($headerLower as $i => $col) {
            if (str_contains($col, $needle)) return $i;
        }
        return false;
    }
}
