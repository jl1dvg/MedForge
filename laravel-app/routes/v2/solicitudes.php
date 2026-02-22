<?php

use App\Modules\Solicitudes\Http\Controllers\SolicitudesReadController;
use Illuminate\Support\Facades\Route;

// Legacy mirror paths
Route::match(['GET', 'POST'], '/solicitudes/kanban-data', [SolicitudesReadController::class, 'kanbanData']);
Route::post('/solicitudes/dashboard-data', [SolicitudesReadController::class, 'dashboardData']);
Route::get('/solicitudes/turnero-data', [SolicitudesReadController::class, 'turneroData']);
Route::get('/solicitudes/{id}/crm', [SolicitudesReadController::class, 'crmResumen'])->whereNumber('id');

// Legacy-style API aliases
Route::match(['GET', 'POST'], '/api/solicitudes/kanban_data.php', [SolicitudesReadController::class, 'kanbanData']);
Route::post('/api/solicitudes/dashboard_data.php', [SolicitudesReadController::class, 'dashboardData']);

// Clean aliases
Route::match(['GET', 'POST'], '/api/solicitudes/kanban', [SolicitudesReadController::class, 'kanbanData']);
Route::post('/api/solicitudes/dashboard', [SolicitudesReadController::class, 'dashboardData']);
Route::get('/api/solicitudes/turnero', [SolicitudesReadController::class, 'turneroData']);
Route::get('/api/solicitudes/{id}/crm', [SolicitudesReadController::class, 'crmResumen'])->whereNumber('id');
