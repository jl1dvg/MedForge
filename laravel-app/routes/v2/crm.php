<?php

use App\Modules\CRM\Http\Controllers\CrmReadController;
use App\Modules\CRM\Http\Controllers\CrmProposalController;
use App\Modules\CRM\Http\Controllers\CrmWriteController;
use App\Modules\CRM\Http\Controllers\CrmOpportunityController;
use App\Modules\CRM\Http\Controllers\CrmContactController;
use App\Modules\CRM\Http\Controllers\CrmActivityController;
use App\Modules\CRM\Http\Controllers\CrmStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'app.auth',
    'app.permission:administrativo,crm.view,crm.manage,solicitudes.view,solicitudes.update,solicitudes.manage',
])->group(function (): void {
    Route::get('/crm/proposals/{id}/pdf', [CrmProposalController::class, 'pdf'])->whereNumber('id');
    Route::post('/crm/proposals/{id}/send-email', [CrmProposalController::class, 'sendEmail'])->whereNumber('id');
    Route::post('/crm/proposals/{id}/send-whatsapp', [CrmProposalController::class, 'sendWhatsapp'])->whereNumber('id');
    Route::get('/api/crm/proposals/{id}/pdf', [CrmProposalController::class, 'pdf'])->whereNumber('id');
    Route::post('/api/crm/proposals/{id}/send-email', [CrmProposalController::class, 'sendEmail'])->whereNumber('id');
    Route::post('/api/crm/proposals/{id}/send-whatsapp', [CrmProposalController::class, 'sendWhatsapp'])->whereNumber('id');
});

Route::middleware([
    'app.auth',
    'app.permission:administrativo,crm.view,crm.manage',
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

// ── CRM Pipeline (nuevo panel centralizado) ───────────────────────────────────
Route::middleware([
    'app.auth',
    'app.permission:administrativo,crm.view,crm.manage',
])->group(function (): void {
    Route::get('/crm/opportunities',                   [CrmOpportunityController::class, 'index']);
    Route::post('/crm/opportunities',                  [CrmOpportunityController::class, 'store']);
    Route::get('/crm/opportunities/{id}',              [CrmOpportunityController::class, 'show'])->whereNumber('id');
    Route::patch('/crm/opportunities/{id}',            [CrmOpportunityController::class, 'update'])->whereNumber('id');
    Route::post('/crm/opportunities/{id}/activities',  [CrmActivityController::class, 'store'])->whereNumber('id');

    Route::get('/crm/contacts/{id}',                   [CrmContactController::class, 'show'])->whereNumber('id');
    Route::patch('/crm/contacts/{id}',                 [CrmContactController::class, 'update'])->whereNumber('id');
    Route::post('/crm/contacts/{id}/merge',            [CrmContactController::class, 'merge'])->whereNumber('id');

    Route::get('/crm/stats',                           [CrmStatsController::class, 'index']);
});
