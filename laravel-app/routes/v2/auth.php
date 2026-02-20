<?php

use App\Modules\Auth\Http\Controllers\AuthMigrationController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/migration-status', [AuthMigrationController::class, 'status']);
