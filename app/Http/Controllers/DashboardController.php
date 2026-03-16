<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Services\PredictionService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(PredictionService $predictionService)
    {
        $user = Auth::user();

        $cycles = Cycle::where('user_id', $user->id)
            ->orderBy('start_date', 'desc')
            ->get();

        $prediction = $predictionService->predict($user);

        return view('dashboard', compact('cycles', 'prediction'));
    }
}
