<?php

use App\Modules\Codes\Http\Controllers\CodesPackagesController;
use App\Modules\Codes\Http\Controllers\CodesReadController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,codes.view,codes.manage',
])->group(function (): void {
    Route::get('/codes/datatable', [CodesReadController::class, 'datatable']);
});

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,codes.manage',
])->group(function (): void {
    Route::get('/codes/api/packages', [CodesPackagesController::class, 'list']);
    Route::get('/codes/api/packages/{id}', [CodesPackagesController::class, 'show'])->whereNumber('id');
    Route::post('/codes/api/packages', [CodesPackagesController::class, 'store']);
    Route::post('/codes/api/packages/{id}', [CodesPackagesController::class, 'update'])->whereNumber('id');
    Route::post('/codes/api/packages/{id}/delete', [CodesPackagesController::class, 'delete'])->whereNumber('id');

    Route::get('/api/codes/packages', [CodesPackagesController::class, 'list']);
    Route::get('/api/codes/packages/{id}', [CodesPackagesController::class, 'show'])->whereNumber('id');
    Route::post('/api/codes/packages', [CodesPackagesController::class, 'store']);
    Route::post('/api/codes/packages/{id}', [CodesPackagesController::class, 'update'])->whereNumber('id');
    Route::post('/api/codes/packages/{id}/delete', [CodesPackagesController::class, 'delete'])->whereNumber('id');
});

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,codes.view,codes.manage,crm.view,crm.manage',
])->group(function (): void {
    Route::get('/codes/api/search', [CodesReadController::class, 'searchCodes']);
    Route::get('/api/codes/search', [CodesReadController::class, 'searchCodes']);
});

