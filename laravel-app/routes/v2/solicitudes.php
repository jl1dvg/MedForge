<?php

use App\Modules\Solicitudes\Http\Controllers\SolicitudesReadController;
use App\Modules\Solicitudes\Http\Controllers\SolicitudesWriteController;
use Illuminate\Support\Facades\Route;

// Legacy mirror paths (reads)
Route::match(['GET', 'POST'], '/solicitudes/kanban-data', [SolicitudesReadController::class, 'kanbanData']);
Route::post('/solicitudes/dashboard-data', [SolicitudesReadController::class, 'dashboardData']);
Route::get('/solicitudes/turnero-data', [SolicitudesReadController::class, 'turneroData']);
Route::get('/solicitudes/{id}/crm', [SolicitudesReadController::class, 'crmResumen'])->whereNumber('id');

// Legacy mirror paths (writes)
Route::post('/solicitudes/actualizar-estado', [SolicitudesWriteController::class, 'actualizarEstado']);
Route::post('/solicitudes/turnero-llamar', [SolicitudesWriteController::class, 'turneroLlamar']);
Route::get('/solicitudes/api/estado', [SolicitudesWriteController::class, 'apiEstadoGet']);
Route::post('/solicitudes/api/estado', [SolicitudesWriteController::class, 'apiEstadoPost']);
Route::post('/solicitudes/derivacion-preseleccion/guardar', [SolicitudesWriteController::class, 'guardarDerivacionPreseleccion']);
Route::post('/solicitudes/{id}/cirugia', [SolicitudesWriteController::class, 'guardarDetallesCirugia'])->whereNumber('id');
Route::post('/solicitudes/{id}/crm', [SolicitudesWriteController::class, 'crmGuardarDetalles'])->whereNumber('id');
Route::post('/solicitudes/{id}/crm/bootstrap', [SolicitudesWriteController::class, 'crmBootstrap'])->whereNumber('id');
Route::get('/solicitudes/{id}/crm/checklist-state', [SolicitudesWriteController::class, 'crmChecklistState'])->whereNumber('id');
Route::post('/solicitudes/{id}/crm/checklist', [SolicitudesWriteController::class, 'crmActualizarChecklist'])->whereNumber('id');
Route::post('/solicitudes/{id}/crm/notas', [SolicitudesWriteController::class, 'crmAgregarNota'])->whereNumber('id');
Route::post('/solicitudes/{id}/crm/tareas', [SolicitudesWriteController::class, 'crmGuardarTarea'])->whereNumber('id');
Route::post('/solicitudes/{id}/crm/tareas/estado', [SolicitudesWriteController::class, 'crmActualizarTarea'])->whereNumber('id');

// Legacy-style API aliases
Route::match(['GET', 'POST'], '/api/solicitudes/kanban_data.php', [SolicitudesReadController::class, 'kanbanData']);
Route::post('/api/solicitudes/dashboard_data.php', [SolicitudesReadController::class, 'dashboardData']);
Route::post('/api/solicitudes/actualizar_estado.php', [SolicitudesWriteController::class, 'actualizarEstado']);
Route::post('/api/solicitudes/turnero_llamar.php', [SolicitudesWriteController::class, 'turneroLlamar']);
Route::get('/api/solicitudes/estado.php', [SolicitudesWriteController::class, 'apiEstadoGet']);
Route::post('/api/solicitudes/estado.php', [SolicitudesWriteController::class, 'apiEstadoPost']);

// Clean aliases
Route::match(['GET', 'POST'], '/api/solicitudes/kanban', [SolicitudesReadController::class, 'kanbanData']);
Route::post('/api/solicitudes/dashboard', [SolicitudesReadController::class, 'dashboardData']);
Route::get('/api/solicitudes/turnero', [SolicitudesReadController::class, 'turneroData']);
Route::get('/api/solicitudes/{id}/crm', [SolicitudesReadController::class, 'crmResumen'])->whereNumber('id');
Route::get('/api/solicitudes/estado', [SolicitudesWriteController::class, 'apiEstadoGet']);
Route::post('/api/solicitudes/estado', [SolicitudesWriteController::class, 'apiEstadoPost']);
Route::post('/api/solicitudes/estado/actualizar', [SolicitudesWriteController::class, 'actualizarEstado']);
Route::post('/api/solicitudes/turnero/llamar', [SolicitudesWriteController::class, 'turneroLlamar']);
Route::post('/api/solicitudes/derivacion/guardar', [SolicitudesWriteController::class, 'guardarDerivacionPreseleccion']);
Route::post('/api/solicitudes/{id}/cirugia', [SolicitudesWriteController::class, 'guardarDetallesCirugia'])->whereNumber('id');
Route::post('/api/solicitudes/{id}/crm', [SolicitudesWriteController::class, 'crmGuardarDetalles'])->whereNumber('id');
Route::post('/api/solicitudes/{id}/crm/bootstrap', [SolicitudesWriteController::class, 'crmBootstrap'])->whereNumber('id');
Route::get('/api/solicitudes/{id}/crm/checklist-state', [SolicitudesWriteController::class, 'crmChecklistState'])->whereNumber('id');
Route::post('/api/solicitudes/{id}/crm/checklist', [SolicitudesWriteController::class, 'crmActualizarChecklist'])->whereNumber('id');
Route::post('/api/solicitudes/{id}/crm/notas', [SolicitudesWriteController::class, 'crmAgregarNota'])->whereNumber('id');
Route::post('/api/solicitudes/{id}/crm/tareas', [SolicitudesWriteController::class, 'crmGuardarTarea'])->whereNumber('id');
Route::post('/api/solicitudes/{id}/crm/tareas/estado', [SolicitudesWriteController::class, 'crmActualizarTarea'])->whereNumber('id');
