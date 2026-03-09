<?php

use App\Modules\Pacientes\Http\Controllers\PacientesReadController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,pacientes.view,pacientes.manage',
])->group(function (): void {
    Route::get('/pacientes', [PacientesReadController::class, 'index']);
    Route::post('/pacientes/datatable', [PacientesReadController::class, 'datatable']);
    Route::match(['GET', 'POST'], '/pacientes/detalles', [PacientesReadController::class, 'detalles']);
    Route::get('/pacientes/detalles/solicitud', [PacientesReadController::class, 'detalleSolicitudApi']);
    Route::get('/pacientes/detalles/section', [PacientesReadController::class, 'detallesSection']);
});

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,pacientes.flujo.view,pacientes.view,pacientes.manage',
])->group(function (): void {
    Route::get('/pacientes/flujo', [PacientesReadController::class, 'flujo']);
    Route::get('/pacientes/flujo/tablero', [PacientesReadController::class, 'flujoTablero']);
    Route::get('/pacientes/flujo/recientes', [PacientesReadController::class, 'flujoRecientes']);
});
