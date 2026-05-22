<?php

use App\Modules\AI\Http\Controllers\AIController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::post('/ai/enfermedad', [AIController::class, 'generarEnfermedad']);
    Route::post('/ai/plan', [AIController::class, 'generarPlan']);
});
