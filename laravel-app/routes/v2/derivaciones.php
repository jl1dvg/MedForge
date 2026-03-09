<?php

use App\Modules\Derivaciones\Http\Controllers\DerivacionesReadController;
use App\Modules\Derivaciones\Http\Controllers\DerivacionesWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,derivaciones.view,pacientes.view,solicitudes.view',
])->group(function (): void {
    Route::post('/derivaciones/datatable', [DerivacionesReadController::class, 'datatable']);
    Route::get('/derivaciones/archivo/{id}', [DerivacionesReadController::class, 'archivo'])->whereNumber('id');
    Route::post('/derivaciones/scrap', [DerivacionesWriteController::class, 'scrap']);
});
