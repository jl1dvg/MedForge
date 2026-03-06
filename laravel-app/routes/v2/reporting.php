<?php

use App\Modules\Reporting\Http\Controllers\ReportingReadController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->group(function (): void {
    Route::get('/reports/protocolo/data', [ReportingReadController::class, 'protocolData']);
    Route::get('/reports/protocolo/pdf', [ReportingReadController::class, 'protocolPdf']);
    Route::get('/reports/imagenes/012b/data', [ReportingReadController::class, 'informe012BData']);
    Route::match(['GET', 'POST'], '/reports/imagenes/012a/data', [ReportingReadController::class, 'cobertura012AData']);
    Route::get('/reports/imagenes/012b/pdf', [ReportingReadController::class, 'informe012BPdf']);
    Route::match(['GET', 'POST'], '/reports/imagenes/012a/pdf', [ReportingReadController::class, 'cobertura012APdf']);
    Route::get('/reports/cobertura/data', [ReportingReadController::class, 'coberturaData']);
    Route::get('/reports/consulta/data', [ReportingReadController::class, 'consultaData']);
    Route::match(['GET', 'POST'], '/reports/cirugias/descanso/data', [ReportingReadController::class, 'postSurgeryRestData']);
    Route::get('/reports/cobertura/pdf', [ReportingReadController::class, 'coberturaPdf']);
    Route::get('/reports/consulta/pdf', [ReportingReadController::class, 'consultaPdf']);
    Route::match(['GET', 'POST'], '/reports/cirugias/descanso/pdf', [ReportingReadController::class, 'postSurgeryRestPdf']);
});
