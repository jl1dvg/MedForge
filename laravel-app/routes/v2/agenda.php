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

    // Agenda V3 — React SPA + API
    Route::get('/agenda/v3',                          [AgendaV3Controller::class, 'shell']);
    Route::get('/api/agenda/v3/config',               [AgendaV3Controller::class, 'config']);
    Route::get('/api/agenda/v3/citas',                [AgendaV3Controller::class, 'listCitas']);
    Route::post('/api/agenda/v3/citas',               [AgendaV3Controller::class, 'createCita']);
    Route::put('/api/agenda/v3/citas/{id}',           [AgendaV3Controller::class, 'updateCita'])->whereNumber('id');
    Route::post('/api/agenda/v3/citas/{id}/avanzar',  [AgendaV3Controller::class, 'avanzarCita'])->whereNumber('id');
    Route::post('/api/agenda/v3/citas/{id}/consulta', [AgendaV3Controller::class, 'finalizarConsulta'])->whereNumber('id');
    Route::delete('/api/agenda/v3/citas/{id}',        [AgendaV3Controller::class, 'cancelarCita'])->whereNumber('id');
    Route::get('/api/agenda/v3/bloqueos',             [AgendaV3Controller::class, 'listBloqueos']);
    Route::post('/api/agenda/v3/bloqueos',            [AgendaV3Controller::class, 'createBloqueo']);
    Route::delete('/api/agenda/v3/bloqueos/{id}',     [AgendaV3Controller::class, 'deleteBloqueo'])->whereNumber('id');
});
