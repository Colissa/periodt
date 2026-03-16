<?php

use App\Http\Controllers\CycleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/cycles', [CycleController::class, 'store'])->name('cycles.store');
    Route::put('/cycles/{cycle}', [CycleController::class, 'update'])->name('cycles.update');
    Route::delete('/cycles/{cycle}', [CycleController::class, 'destroy'])->name('cycles.destroy');

    Route::get('/import', [ImportController::class, 'show'])->name('import.show');
    Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('/import/quick-entry', [ImportController::class, 'quickEntry'])->name('import.quick-entry');
    Route::post('/import/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
