<?php

use App\Modules\MailTemplates\Http\Controllers\CoberturaMailTemplateController;
use Illuminate\Support\Facades\Route;

// /mail-templates/cobertura/resolve must be declared before the {key} wildcard to avoid shadowing
Route::middleware(['app.auth'])->group(function (): void {
    Route::post('/mail-templates/cobertura/resolve', [CoberturaMailTemplateController::class, 'resolve']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,settings.manage'])->group(function (): void {
    Route::get('/mail-templates/cobertura', [CoberturaMailTemplateController::class, 'index']);
    Route::get('/mail-templates/cobertura/{key}', [CoberturaMailTemplateController::class, 'index']);
    Route::post('/mail-templates/cobertura/{key}', [CoberturaMailTemplateController::class, 'save']);
});
