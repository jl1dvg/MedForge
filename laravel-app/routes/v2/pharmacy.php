<?php

use App\Modules\Pharmacy\Http\Controllers\PharmacyApiController;
use App\Modules\Pharmacy\Http\Controllers\PharmacyUiController;
use Illuminate\Support\Facades\Route;

// Web UI routes (registered in web.php under app.auth middleware)
Route::get('/v2/pharmacy', [PharmacyUiController::class, 'index']);
Route::get('/v2/pharmacy/dashboard', [PharmacyUiController::class, 'dashboard']);
Route::get('/v2/pharmacy/prescriptions/{id}', [PharmacyUiController::class, 'show'])->whereNumber('id');
Route::patch('/v2/pharmacy/prescriptions/{id}/estado', [PharmacyUiController::class, 'updateEstado'])->whereNumber('id');
Route::post('/v2/pharmacy/prescriptions/{id}/estado', [PharmacyUiController::class, 'updateEstado'])->whereNumber('id');
Route::get('/v2/pharmacy/inventory', [PharmacyUiController::class, 'inventory']);
Route::post('/v2/pharmacy/inventory', [PharmacyUiController::class, 'storeInventory']);
Route::patch('/v2/pharmacy/inventory/{id}', [PharmacyUiController::class, 'updateInventory'])->whereNumber('id');
Route::post('/v2/pharmacy/inventory/{id}', [PharmacyUiController::class, 'updateInventory'])->whereNumber('id');
