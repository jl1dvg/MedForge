<?php

use App\Modules\Pharmacy\Http\Controllers\PharmacyApiController;
use App\Modules\Pharmacy\Http\Controllers\PharmacyUiController;
use Illuminate\Support\Facades\Route;

// Web UI routes — all return the React shell (pharmacy.app)
Route::get('/v2/pharmacy', [PharmacyUiController::class, 'index']);
Route::get('/v2/pharmacy/dashboard', [PharmacyUiController::class, 'dashboard']);
Route::get('/v2/pharmacy/prescriptions/{id}', [PharmacyUiController::class, 'show'])->whereNumber('id');
Route::get('/v2/pharmacy/inventory', [PharmacyUiController::class, 'inventory']);

// Legacy Blade form action routes (kept for backward compat, now redirect to React shell)
Route::patch('/v2/pharmacy/prescriptions/{id}/estado', [PharmacyUiController::class, 'updateEstado'])->whereNumber('id');
Route::post('/v2/pharmacy/prescriptions/{id}/estado', [PharmacyUiController::class, 'updateEstado'])->whereNumber('id');
Route::post('/v2/pharmacy/inventory', [PharmacyUiController::class, 'storeInventory']);
Route::patch('/v2/pharmacy/inventory/{id}', [PharmacyUiController::class, 'updateInventory'])->whereNumber('id');
Route::post('/v2/pharmacy/inventory/{id}', [PharmacyUiController::class, 'updateInventory'])->whereNumber('id');

// Internal JSON API routes (used by React frontend)
Route::get('/v2/pharmacy/api/prescriptions', [PharmacyUiController::class, 'apiPrescriptions']);
Route::get('/v2/pharmacy/api/prescriptions/{id}', [PharmacyUiController::class, 'apiPrescription'])->whereNumber('id');
Route::patch('/v2/pharmacy/api/prescriptions/{id}/estado', [PharmacyUiController::class, 'apiUpdateEstado'])->whereNumber('id');
Route::get('/v2/pharmacy/api/inventory', [PharmacyUiController::class, 'apiInventory']);
Route::post('/v2/pharmacy/api/inventory', [PharmacyUiController::class, 'apiStoreInventory']);
Route::patch('/v2/pharmacy/api/inventory/{id}', [PharmacyUiController::class, 'apiUpdateInventory'])->whereNumber('id');
Route::get('/v2/pharmacy/api/dashboard', [PharmacyUiController::class, 'apiDashboard']);
