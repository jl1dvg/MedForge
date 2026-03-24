<?php

use App\Modules\Examenes\Http\Controllers\ExamenesParityController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,examenes.view,examenes.manage',
])->group(function (): void {
// Legacy mirror paths (reads)
Route::match(['GET', 'POST'], '/examenes/kanban-data', [ExamenesParityController::class, 'kanbanData']);
Route::get('/examenes/turnero-data', [ExamenesParityController::class, 'turneroData']);
Route::get('/examenes/api/estado', [ExamenesParityController::class, 'apiEstadoGet']);
Route::get('/examenes/{id}/crm', [ExamenesParityController::class, 'crmResumen'])->whereNumber('id');
Route::get('/examenes/derivacion', [ExamenesParityController::class, 'derivacionDetalle']);
Route::get('/examenes/prefactura', [ExamenesParityController::class, 'prefactura']);
Route::get('/imagenes/examenes-realizados/nas/list', [ExamenesParityController::class, 'imagenesNasList']);
Route::get('/imagenes/examenes-realizados/nas/file', [ExamenesParityController::class, 'imagenesNasFile']);
Route::get('/imagenes/informes/datos', [ExamenesParityController::class, 'informeDatos']);
Route::get('/imagenes/informes/plantilla', [ExamenesParityController::class, 'informePlantilla']);
Route::get('/imagenes/dashboard/export/pdf', [ExamenesParityController::class, 'imagenesDashboardExportPdf']);
Route::get('/imagenes/dashboard/export/excel', [ExamenesParityController::class, 'imagenesDashboardExportExcel']);

// Legacy mirror paths (writes)
Route::post('/examenes/actualizar-estado', [ExamenesParityController::class, 'actualizarEstado']);
Route::post('/examenes/turnero-llamar', [ExamenesParityController::class, 'turneroLlamar']);
Route::post('/examenes/api/estado', [ExamenesParityController::class, 'apiEstadoPost']);
Route::post('/examenes/cobertura-mail', [ExamenesParityController::class, 'enviarCoberturaMail']);
Route::post('/examenes/derivacion-preseleccion', [ExamenesParityController::class, 'derivacionPreseleccion']);
Route::post('/examenes/derivacion-preseleccion/guardar', [ExamenesParityController::class, 'guardarDerivacionPreseleccion']);
Route::post('/examenes/reportes/pdf', [ExamenesParityController::class, 'reportePdf']);
Route::post('/examenes/reportes/excel', [ExamenesParityController::class, 'reporteExcel']);
Route::post('/examenes/notificaciones/recordatorios', [ExamenesParityController::class, 'enviarRecordatorios']);
Route::post('/examenes/{id}/crm', [ExamenesParityController::class, 'crmGuardarDetalles'])->whereNumber('id');
Route::post('/examenes/{id}/crm/bootstrap', [ExamenesParityController::class, 'crmBootstrap'])->whereNumber('id');
Route::get('/examenes/{id}/crm/checklist-state', [ExamenesParityController::class, 'crmChecklistState'])->whereNumber('id');
Route::post('/examenes/{id}/crm/checklist', [ExamenesParityController::class, 'crmActualizarChecklist'])->whereNumber('id');
Route::post('/examenes/{id}/crm/notas', [ExamenesParityController::class, 'crmAgregarNota'])->whereNumber('id');
Route::post('/examenes/{id}/crm/tareas', [ExamenesParityController::class, 'crmGuardarTarea'])->whereNumber('id');
Route::post('/examenes/{id}/crm/tareas/estado', [ExamenesParityController::class, 'crmActualizarTarea'])->whereNumber('id');
Route::post('/examenes/{id}/crm/bloqueo', [ExamenesParityController::class, 'crmRegistrarBloqueo'])->whereNumber('id');
Route::post('/examenes/{id}/crm/adjuntos', [ExamenesParityController::class, 'crmSubirAdjunto'])->whereNumber('id');
Route::post('/imagenes/examenes-realizados/nas/warm', [ExamenesParityController::class, 'imagenesNasWarm']);
Route::post('/imagenes/examenes-realizados/actualizar', [ExamenesParityController::class, 'actualizarImagenRealizada']);
Route::post('/imagenes/examenes-realizados/eliminar', [ExamenesParityController::class, 'eliminarImagenRealizada']);
Route::post('/imagenes/informes/guardar', [ExamenesParityController::class, 'informeGuardar']);
Route::post('/imagenes/informes/autofill', [ExamenesParityController::class, 'informeAutofill']);

// Clean aliases
Route::match(['GET', 'POST'], '/api/examenes/kanban', [ExamenesParityController::class, 'kanbanData']);
Route::get('/api/examenes/turnero', [ExamenesParityController::class, 'turneroData']);
Route::get('/api/examenes/estado', [ExamenesParityController::class, 'apiEstadoGet']);
Route::post('/api/examenes/estado', [ExamenesParityController::class, 'apiEstadoPost']);
Route::post('/api/examenes/estado/actualizar', [ExamenesParityController::class, 'actualizarEstado']);
Route::post('/api/examenes/turnero/llamar', [ExamenesParityController::class, 'turneroLlamar']);
Route::post('/api/examenes/cobertura-mail', [ExamenesParityController::class, 'enviarCoberturaMail']);
Route::post('/api/examenes/derivacion/preseleccion', [ExamenesParityController::class, 'derivacionPreseleccion']);
Route::post('/api/examenes/derivacion/guardar', [ExamenesParityController::class, 'guardarDerivacionPreseleccion']);
Route::post('/api/examenes/reportes/pdf', [ExamenesParityController::class, 'reportePdf']);
Route::post('/api/examenes/reportes/excel', [ExamenesParityController::class, 'reporteExcel']);
Route::get('/api/examenes/{id}/crm', [ExamenesParityController::class, 'crmResumen'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm', [ExamenesParityController::class, 'crmGuardarDetalles'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/bootstrap', [ExamenesParityController::class, 'crmBootstrap'])->whereNumber('id');
Route::get('/api/examenes/{id}/crm/checklist-state', [ExamenesParityController::class, 'crmChecklistState'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/checklist', [ExamenesParityController::class, 'crmActualizarChecklist'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/notas', [ExamenesParityController::class, 'crmAgregarNota'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/tareas', [ExamenesParityController::class, 'crmGuardarTarea'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/tareas/estado', [ExamenesParityController::class, 'crmActualizarTarea'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/bloqueo', [ExamenesParityController::class, 'crmRegistrarBloqueo'])->whereNumber('id');
Route::post('/api/examenes/{id}/crm/adjuntos', [ExamenesParityController::class, 'crmSubirAdjunto'])->whereNumber('id');
});
