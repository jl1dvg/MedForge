<?php

declare(strict_types=1);

use App\Modules\CRM\Http\Controllers\CrmCaseController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'app.auth',
    'app.permission:administrativo,crm.view,crm.manage,solicitudes.view,solicitudes.update,solicitudes.manage',
])->group(function (): void {
    Route::get('/crm/cases/{sourceType}/{sourceId}', [CrmCaseController::class, 'show'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.show');
    Route::patch('/crm/cases/{sourceType}/{sourceId}', [CrmCaseController::class, 'update'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.update');
    Route::post('/crm/cases/{sourceType}/{sourceId}/contacts', [CrmCaseController::class, 'storeContact'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.contacts.store');
    Route::post('/crm/cases/{sourceType}/{sourceId}/notes', [CrmCaseController::class, 'storeNote'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.notes.store');
    Route::delete('/crm/cases/{sourceType}/{sourceId}/notes/{noteId}', [CrmCaseController::class, 'deleteNote'])
        ->whereNumber('sourceId')
        ->whereNumber('noteId')
        ->name('v3.crm.cases.notes.delete');
    Route::post('/crm/cases/{sourceType}/{sourceId}/tasks', [CrmCaseController::class, 'storeTask'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.tasks.store');
    Route::patch('/crm/cases/{sourceType}/{sourceId}/tasks/{taskId}', [CrmCaseController::class, 'updateTask'])
        ->whereNumber('sourceId')
        ->whereNumber('taskId')
        ->name('v3.crm.cases.tasks.update');
    Route::post('/crm/cases/{sourceType}/{sourceId}/whatsapp', [CrmCaseController::class, 'sendWhatsapp'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.whatsapp.store');
    Route::post('/crm/cases/{sourceType}/{sourceId}/email', [CrmCaseController::class, 'sendEmail'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.email.store');
    Route::get('/crm/catalog/codes', [CrmCaseController::class, 'catalogCodes'])
        ->name('v3.crm.catalog.codes');
    Route::get('/crm/catalog/packages', [CrmCaseController::class, 'catalogPackages'])
        ->name('v3.crm.catalog.packages');
    Route::post('/crm/cases/{sourceType}/{sourceId}/proposals', [CrmCaseController::class, 'storeProposal'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.proposals.store');
    Route::get('/crm/proposals/{proposalId}/pdf', [CrmCaseController::class, 'proposalPdf'])
        ->whereNumber('proposalId')
        ->name('v3.crm.proposals.pdf');
    Route::post('/crm/proposals/{proposalId}/send-email', [CrmCaseController::class, 'sendProposalEmail'])
        ->whereNumber('proposalId')
        ->name('v3.crm.proposals.email');
    Route::post('/crm/proposals/{proposalId}/send-whatsapp', [CrmCaseController::class, 'sendProposalWhatsapp'])
        ->whereNumber('proposalId')
        ->name('v3.crm.proposals.whatsapp');
});
