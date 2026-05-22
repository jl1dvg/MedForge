<?php

use App\Modules\CiveExtension\Http\Controllers\ConfigController;
use App\Modules\CiveExtension\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// /api/cive-extension routes — consumed by Chrome extension (asistentecive.consulmed.me)
Route::middleware('app.auth')->prefix('api/cive-extension')->group(function (): void {
    Route::get('/config', [ConfigController::class, 'show']);

    Route::middleware('app.permission:settings.manage,administrativo')->group(function (): void {
        Route::post('/health-check', [HealthController::class, 'run']);
        Route::get('/health-checks', [HealthController::class, 'index']);
    });
});
