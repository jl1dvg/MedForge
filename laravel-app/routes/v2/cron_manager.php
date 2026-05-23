<?php

use App\Modules\CronManager\Http\Controllers\CronManagerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,settings.manage'])->group(function (): void {
    Route::get('/cron-manager', [CronManagerController::class, 'index']);
    Route::post('/cron-manager/run', [CronManagerController::class, 'runAll']);
    Route::post('/cron-manager/run/{slug}', [CronManagerController::class, 'runTask']);
    Route::post('/cron-manager/settings/{slug}', [CronManagerController::class, 'updateSettings']);
});
