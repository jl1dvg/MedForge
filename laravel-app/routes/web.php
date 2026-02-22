<?php

use App\Modules\Dashboard\Http\Controllers\DashboardUiController;
use App\Modules\Auth\Http\Controllers\UnifiedLogoutController;
use App\Modules\Solicitudes\Http\Controllers\SolicitudesUiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('legacy.auth')->get('/v2/dashboard', [DashboardUiController::class, 'index']);
Route::middleware('legacy.auth')->get('/v2/solicitudes', [SolicitudesUiController::class, 'index']);
Route::middleware('legacy.auth')->get('/v2/solicitudes/dashboard', [SolicitudesUiController::class, 'dashboard']);
Route::middleware('legacy.auth')->get('/v2/solicitudes/turnero', [SolicitudesUiController::class, 'turnero']);
Route::get('/v2/auth/logout', [UnifiedLogoutController::class, 'logout']);
