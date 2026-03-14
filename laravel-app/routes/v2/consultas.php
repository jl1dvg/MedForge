<?php

use App\Modules\Consultas\Http\Controllers\ConsultasReadController;
use App\Modules\Consultas\Http\Controllers\ConsultasWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'consultas.cors',
    'legacy.auth',
    'legacy.permission:administrativo,ai.manage,ai.consultas.enfermedad,ai.consultas.plan',
])->group(function (): void {
    // Explicit preflight endpoints for cross-origin extension traffic.
    Route::options('/api/consultas/guardar.php', static fn () => response('', 204));
    Route::options('/api/consultas/anterior.php', static fn () => response('', 204));
    Route::options('/api/consultas/plan.php', static fn () => response('', 204));
    Route::options('/api/consultas/guardar', static fn () => response('', 204));
    Route::options('/api/consultas/anterior', static fn () => response('', 204));
    Route::options('/api/consultas/plan', static fn () => response('', 204));

    // Legacy-style API aliases
    Route::post('/api/consultas/guardar.php', [ConsultasWriteController::class, 'guardar']);
    Route::get('/api/consultas/anterior.php', [ConsultasReadController::class, 'anterior']);
    Route::get('/api/consultas/plan.php', [ConsultasReadController::class, 'plan']);
    Route::post('/api/consultas/plan.php', [ConsultasWriteController::class, 'plan']);

    // Clean aliases
    Route::post('/api/consultas/guardar', [ConsultasWriteController::class, 'guardar']);
    Route::get('/api/consultas/anterior', [ConsultasReadController::class, 'anterior']);
    Route::get('/api/consultas/plan', [ConsultasReadController::class, 'plan']);
    Route::post('/api/consultas/plan', [ConsultasWriteController::class, 'plan']);
});
