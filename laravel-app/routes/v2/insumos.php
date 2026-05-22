<?php

use App\Modules\Insumos\Http\Controllers\InsumosController;
use App\Modules\Insumos\Http\Controllers\LentesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/insumos', [InsumosController::class, 'index']);
    Route::get('/insumos/list', [InsumosController::class, 'listar']);
    Route::post('/insumos/guardar', [InsumosController::class, 'guardar']);
    Route::get('/insumos/medicamentos', [InsumosController::class, 'medicamentos']);
    Route::get('/insumos/medicamentos/list', [InsumosController::class, 'listarMedicamentos']);
    Route::post('/insumos/medicamentos/guardar', [InsumosController::class, 'guardarMedicamento']);
    Route::post('/insumos/medicamentos/eliminar', [InsumosController::class, 'eliminarMedicamento']);
    Route::get('/insumos/lentes', [LentesController::class, 'index']);
    Route::get('/insumos/lentes/list', [LentesController::class, 'listar']);
    Route::post('/insumos/lentes/guardar', [LentesController::class, 'guardar']);
    Route::post('/insumos/lentes/eliminar', [LentesController::class, 'eliminar']);
});
