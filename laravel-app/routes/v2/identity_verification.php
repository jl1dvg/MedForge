<?php

use App\Modules\IdentityVerification\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'app.auth',
    'app.permission:administrativo,pacientes.verification.view,pacientes.verification.manage',
])->group(function (): void {
    Route::get('/pacientes/certificaciones', [VerificationController::class, 'index']);
    Route::post('/pacientes/certificaciones', [VerificationController::class, 'store']);
    Route::get('/pacientes/certificaciones/detalle', [VerificationController::class, 'show']);
    Route::get('/pacientes/certificaciones/comprobante', [VerificationController::class, 'consentDocument']);
    Route::post('/pacientes/certificaciones/verificar', [VerificationController::class, 'verify']);
    Route::post('/pacientes/certificaciones/eliminar', [VerificationController::class, 'destroy']);
});
