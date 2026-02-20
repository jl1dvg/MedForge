<?php

use App\Modules\Billing\Http\Controllers\BillingReadController;
use Illuminate\Support\Facades\Route;

Route::get('/api/billing/no-facturados', [BillingReadController::class, 'noFacturados']);
Route::get('/api/billing/afiliaciones', [BillingReadController::class, 'afiliaciones']);
