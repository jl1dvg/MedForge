<?php

use App\Modules\Agenda\Http\Controllers\AgendaReadController;
use App\Modules\Agenda\Http\Controllers\AgendaWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->group(function (): void {
    Route::get('/agenda', [AgendaReadController::class, 'index']);
    Route::get('/agenda/visitas/{id}', [AgendaReadController::class, 'visita'])->whereNumber('id');
    Route::post('/agenda/estado', [AgendaWriteController::class, 'actualizarEstado']);

    Route::get('/api/agenda', [AgendaReadController::class, 'index']);
    Route::get('/api/agenda/visitas/{id}', [AgendaReadController::class, 'visita'])->whereNumber('id');
    Route::post('/api/agenda/estado', [AgendaWriteController::class, 'actualizarEstado']);
});
