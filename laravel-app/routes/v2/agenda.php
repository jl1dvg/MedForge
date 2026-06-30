<?php

use App\Modules\Agenda\Http\Controllers\AgendaReadController;
use App\Modules\Agenda\Http\Controllers\AgendaWriteController;
use App\Modules\Agenda\Http\Controllers\AgendaV3Controller;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'app.auth',
    'app.permission:administrativo,agenda.view,pacientes.view,solicitudes.view,examenes.view',
])->group(function (): void {
    Route::get('/agenda', [AgendaReadController::class, 'index']);
    Route::get('/agenda/visitas/{id}', [AgendaReadController::class, 'visita'])->whereNumber('id');
    Route::post('/agenda/citas', [AgendaWriteController::class, 'crearCita']);
    Route::post('/agenda/estado', [AgendaWriteController::class, 'actualizarEstado']);

    Route::get('/api/agenda', [AgendaReadController::class, 'index']);
    Route::get('/api/agenda/visitas/{id}', [AgendaReadController::class, 'visita'])->whereNumber('id');
    Route::post('/api/agenda/citas', [AgendaWriteController::class, 'crearCita']);
    Route::post('/api/agenda/estado', [AgendaWriteController::class, 'actualizarEstado']);

    // Agenda V3 shell — accesible como /v2/agenda/v3
    Route::get('/agenda/v3', [AgendaV3Controller::class, 'shell']);
});
