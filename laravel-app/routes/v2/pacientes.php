<?php

use App\Modules\Pacientes\Http\Controllers\PacientesReadController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'app.auth',
    'app.permission:administrativo,pacientes.manage',
])->group(function (): void {
    Route::put('/pacientes/editar', [PacientesReadController::class, 'editar']);
    Route::post('/pacientes/crear', [PacientesReadController::class, 'crear']);
});

Route::middleware([
    'app.auth',
    'app.permission:administrativo,pacientes.view,pacientes.manage',
])->group(function (): void {
    Route::get('/pacientes', [PacientesReadController::class, 'index']);
    Route::get('/pacientes/catalogos', [PacientesReadController::class, 'catalogos']);
    Route::get('/pacientes/kpis', [PacientesReadController::class, 'kpis']);
    Route::post('/pacientes/datatable', [PacientesReadController::class, 'datatable']);
    Route::match(['GET', 'POST'], '/pacientes/detalles', [PacientesReadController::class, 'detalles']);
    Route::get('/pacientes/detalles/solicitud', [PacientesReadController::class, 'detalleSolicitudApi']);
    Route::get('/pacientes/detalles/section', [PacientesReadController::class, 'detallesSection']);
});

Route::middleware([
    'app.auth',
    'app.permission:administrativo,pacientes.flujo.view,pacientes.view,pacientes.manage',
])->group(function (): void {
    Route::get('/pacientes/flujo', [PacientesReadController::class, 'flujo']);
    Route::get('/pacientes/flujo/tablero', [PacientesReadController::class, 'flujoTablero']);
    Route::get('/pacientes/flujo/recientes', [PacientesReadController::class, 'flujoRecientes']);
    Route::post('/pacientes/flujo/trayecto-estado', [PacientesReadController::class, 'actualizarEstadoTrayecto']);
});
