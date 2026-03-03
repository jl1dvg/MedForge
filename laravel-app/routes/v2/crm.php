<?php

use App\Modules\CRM\Http\Controllers\CrmReadController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->group(function (): void {
    Route::get('/crm/leads', [CrmReadController::class, 'leads']);
    Route::get('/crm/leads/meta', [CrmReadController::class, 'meta']);
    Route::get('/crm/leads/metrics', [CrmReadController::class, 'metrics']);

    Route::get('/api/crm/leads', [CrmReadController::class, 'leads']);
    Route::get('/api/crm/leads/meta', [CrmReadController::class, 'meta']);
    Route::get('/api/crm/leads/metrics', [CrmReadController::class, 'metrics']);
});
