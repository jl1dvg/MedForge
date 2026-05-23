<?php

use App\Modules\CiveExtension\Http\Controllers\ConfigController;
use App\Modules\CiveExtension\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// /api/cive-extension routes — consumed by Chrome extension (asistentecive.consulmed.me)

// Config es público — la extensión lo llama al arrancar sin sesión activa en MedForge.
// El doctor nunca necesita loguearse a MedForge; la extensión se autentica por su
// propio ID de extensión Chrome en las rutas de escritura.
// ConsultasCors agrega headers CORS para que el background service worker pueda acceder.
Route::middleware('consultas.cors')->prefix('api/cive-extension')->group(function (): void {
    Route::get('/config', [ConfigController::class, 'show']);
});

// Health checks requieren sesión de admin MedForge (solo lo usa el administrador desde la UI).
Route::middleware('app.auth')->prefix('api/cive-extension')->group(function (): void {
    Route::middleware('app.permission:settings.manage,administrativo')->group(function (): void {
        Route::post('/health-check', [HealthController::class, 'run']);
        Route::get('/health-checks', [HealthController::class, 'index']);
    });
});
