<?php

use App\Modules\Consultas\Http\Controllers\ConsultasReadController;
use App\Modules\Consultas\Http\Controllers\ConsultasWriteController;
use Illuminate\Support\Facades\Route;

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
