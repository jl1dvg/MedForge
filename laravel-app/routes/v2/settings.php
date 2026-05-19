<?php

use App\Modules\Settings\Http\Controllers\SettingsReadController;
use App\Modules\Settings\Http\Controllers\SettingsWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,settings.manage'])->group(function (): void {
    Route::get('/settings', [SettingsReadController::class, 'all']);
    Route::get('/settings/key/{name}', [SettingsReadController::class, 'byKey'])->where('name', '.+');
    Route::get('/settings/{category}', [SettingsReadController::class, 'byCategory']);
    Route::post('/settings/{category}', [SettingsWriteController::class, 'saveCategory']);
    Route::post('/settings/upload', [SettingsWriteController::class, 'uploadFile']);
});
