<?php

declare(strict_types=1);

use App\Modules\Solicitudes\Http\Controllers\SolicitudesReadController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'app.auth',
    'app.permission:administrativo,solicitudes.view,solicitudes.update,solicitudes.turnero,solicitudes.dashboard.view,solicitudes.manage',
])->group(function (): void {
    Route::get('/solicitudes/{id}/detalle', [SolicitudesReadController::class, 'detalleCompleto'])
        ->whereNumber('id')
        ->name('v3.solicitudes.detalle');
});
