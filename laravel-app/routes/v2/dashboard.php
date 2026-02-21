<?php

use App\Modules\Dashboard\Http\Controllers\DashboardReadController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->get('/dashboard/summary', [DashboardReadController::class, 'summary']);
