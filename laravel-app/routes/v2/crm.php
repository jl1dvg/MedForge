<?php

use App\Modules\CRM\Http\Controllers\CrmReadController;
use App\Modules\CRM\Http\Controllers\CrmWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,crm.view,crm.manage',
])->group(function (): void {
    Route::get('/crm/leads', [CrmReadController::class, 'leads']);
    Route::get('/crm/leads/meta', [CrmReadController::class, 'meta']);
    Route::get('/crm/leads/metrics', [CrmReadController::class, 'metrics']);
    Route::post('/crm/leads', [CrmWriteController::class, 'createLead']);
    Route::post('/crm/leads/update', [CrmWriteController::class, 'updateLead']);
    Route::patch('/crm/leads/{id}/status', [CrmWriteController::class, 'updateLeadStatus'])->whereNumber('id');

    Route::get('/api/crm/leads', [CrmReadController::class, 'leads']);
    Route::get('/api/crm/leads/meta', [CrmReadController::class, 'meta']);
    Route::get('/api/crm/leads/metrics', [CrmReadController::class, 'metrics']);
    Route::post('/api/crm/leads', [CrmWriteController::class, 'createLead']);
    Route::post('/api/crm/leads/update', [CrmWriteController::class, 'updateLead']);
    Route::patch('/api/crm/leads/{id}/status', [CrmWriteController::class, 'updateLeadStatus'])->whereNumber('id');
});
