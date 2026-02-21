<?php

use App\Modules\Dashboard\Http\Controllers\DashboardUiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/v2/dashboard', [DashboardUiController::class, 'index']);
