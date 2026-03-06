<?php

use App\Modules\Pacientes\Http\Controllers\PacientesReadController;
use Illuminate\Support\Facades\Route;

Route::get('/pacientes', [PacientesReadController::class, 'index']);
Route::post('/pacientes/datatable', [PacientesReadController::class, 'datatable']);
Route::match(['GET', 'POST'], '/pacientes/detalles', [PacientesReadController::class, 'detalles']);
Route::get('/pacientes/detalles/solicitud', [PacientesReadController::class, 'detalleSolicitudApi']);
Route::get('/pacientes/detalles/section', [PacientesReadController::class, 'detallesSection']);
Route::get('/pacientes/flujo', [PacientesReadController::class, 'flujo']);
Route::get('/pacientes/flujo/tablero', [PacientesReadController::class, 'flujoTablero']);
Route::get('/pacientes/flujo/recientes', [PacientesReadController::class, 'flujoRecientes']);
