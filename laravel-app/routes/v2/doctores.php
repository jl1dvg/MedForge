<?php

use App\Modules\Doctores\Http\Controllers\DoctoresController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/doctores', [DoctoresController::class, 'index']);
    Route::get('/doctores/{doctor}', [DoctoresController::class, 'show'])->whereNumber('doctor');
});
