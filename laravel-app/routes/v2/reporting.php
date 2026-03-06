<?php

use App\Modules\Reporting\Http\Controllers\ReportingReadController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->get('/reports/protocolo/data', [ReportingReadController::class, 'protocolData']);
