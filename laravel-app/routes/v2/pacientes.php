<?php

use App\Modules\Pacientes\Http\Controllers\PacientesReadController;
use Illuminate\Support\Facades\Route;

Route::get('/pacientes', [PacientesReadController::class, 'index']);
Route::post('/pacientes/datatable', [PacientesReadController::class, 'datatable']);
Route::match(['GET', 'POST'], '/pacientes/detalles', [PacientesReadController::class, 'detalles']);
Route::get('/pacientes/flujo', [PacientesReadController::class, 'flujo']);
