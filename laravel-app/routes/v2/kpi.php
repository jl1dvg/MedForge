<?php

use App\Modules\KPI\Http\Controllers\KpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,kpis.view'])->group(function (): void {
    Route::get('/kpis', [KpiController::class, 'index']);
    Route::get('/kpis/{kpiKey}', [KpiController::class, 'show'])->where('kpiKey', '.+');
});
