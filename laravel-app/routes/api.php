<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function (): void {
    require __DIR__ . '/v2/health.php';
    require __DIR__ . '/v2/dashboard.php';
    require __DIR__ . '/v2/pacientes.php';
    require __DIR__ . '/v2/billing.php';
    require __DIR__ . '/v2/solicitudes.php';
    require __DIR__ . '/v2/auth.php';
});
