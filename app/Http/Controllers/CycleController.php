<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CycleController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today',
        ]);

        $user = Auth::user();

        $cycle = Cycle::create([
            'user_id' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
        ]);

        // Calculate period length if end date provided
        if ($cycle->end_date) {
            $cycle->period_length = $cycle->start_date->diffInDays($cycle->end_date) + 1;
            $cycle->save();
        }

        // Full recalculation so entry order doesn't matter
        $this->recalculateCycleLengths($user->id);

        return redirect()->route('dashboard')->with('success', 'Period logged!');
    }

    public function update(Request $request, Cycle $cycle)
    {
        if ($cycle->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ]);

        $cycle->end_date = $validated['end_date'];
        $cycle->period_length = $cycle->start_date->diffInDays($cycle->end_date) + 1;
        $cycle->save();

        return redirect()->route('dashboard')->with('success', 'Period updated!');
    }

    public function destroy(Cycle $cycle)
    {
        if ($cycle->user_id !== Auth::id()) {
            abort(403);
        }

        $cycle->delete();

        // Recalculate cycle lengths for remaining cycles
        $this->recalculateCycleLengths(Auth::id());

        return redirect()->route('dashboard')->with('success', 'Period deleted.');
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

        // Last cycle has no next, so clear its cycle_length
        if ($cycles->isNotEmpty()) {
            $last = $cycles->last();
            $last->cycle_length = null;
            $last->save();
        }
    }
}
