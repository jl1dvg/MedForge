<?php

use App\Modules\Dashboard\Http\Controllers\DashboardUiController;
use App\Modules\Auth\Http\Controllers\UnifiedLogoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('legacy.auth')->get('/v2/dashboard', [DashboardUiController::class, 'index']);
Route::get('/v2/auth/logout', [UnifiedLogoutController::class, 'logout']);
