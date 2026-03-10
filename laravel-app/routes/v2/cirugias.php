<?php

use App\Modules\Cirugias\Http\Controllers\CirugiasReadController;
use App\Modules\Cirugias\Http\Controllers\CirugiasUiController;
use App\Modules\Cirugias\Http\Controllers\CirugiasWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,cirugias.view,cirugias.manage,cirugias.dashboard.view',
])->group(function (): void {
    Route::get('/cirugias', [CirugiasUiController::class, 'index']);
    Route::post('/cirugias/datatable', [CirugiasReadController::class, 'datatable']);
    Route::match(['GET', 'POST'], '/cirugias/wizard', [CirugiasUiController::class, 'wizard']);
    Route::post('/cirugias/wizard/guardar', [CirugiasWriteController::class, 'guardar']);
    Route::post('/cirugias/wizard/autosave', [CirugiasWriteController::class, 'autosave']);
    Route::post('/cirugias/wizard/scrape-derivacion', [CirugiasWriteController::class, 'scrapeDerivacion']);
    Route::get('/cirugias/protocolo', [CirugiasReadController::class, 'protocolo']);
    Route::post('/cirugias/protocolo/printed', [CirugiasWriteController::class, 'togglePrinted']);
    Route::post('/cirugias/protocolo/status', [CirugiasWriteController::class, 'updateStatus']);

    Route::get('/cirugias/dashboard', [CirugiasUiController::class, 'dashboard']);
    Route::get('/cirugias/dashboard/export/pdf', [CirugiasUiController::class, 'exportPdf']);
    Route::get('/cirugias/dashboard/export/excel', [CirugiasUiController::class, 'exportExcel']);
});
