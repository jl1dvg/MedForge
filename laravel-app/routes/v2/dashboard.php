<?php

use App\Modules\Dashboard\Http\Controllers\DashboardReadController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,dashboard.view',
])->get('/dashboard/summary', [DashboardReadController::class, 'summary']);
