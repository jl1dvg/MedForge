<?php

use App\Modules\Billing\Http\Controllers\BillingReadController;
use App\Modules\Billing\Http\Controllers\BillingUiController;
use App\Modules\Billing\Http\Controllers\BillingWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,reportes.view,reportes.export',
])->group(function (): void {
    // Billing UI (Laravel v2 shell)
    Route::get('/billing', [BillingUiController::class, 'index']);
    Route::get('/billing/no-facturados', [BillingUiController::class, 'noFacturados']);
    Route::get('/billing/detalle', [BillingUiController::class, 'detalle']);
    Route::get('/billing/dashboard', [BillingUiController::class, 'dashboard']);
    Route::post('/billing/dashboard-data', [BillingUiController::class, 'dashboardData']);
    Route::get('/billing/honorarios', [BillingUiController::class, 'honorarios']);
    Route::post('/billing/honorarios-data', [BillingUiController::class, 'honorariosData']);
    Route::get('/informes/particulares', [BillingUiController::class, 'informeParticulares']);
    Route::match(['GET', 'POST'], '/informes/iess', [BillingUiController::class, 'informeIess']);
    Route::match(['GET', 'POST'], '/informes/isspol', [BillingUiController::class, 'informeIsspol']);
    Route::match(['GET', 'POST'], '/informes/issfa', [BillingUiController::class, 'informeIssfa']);
    Route::match(['GET', 'POST'], '/informes/msp', [BillingUiController::class, 'informeMsp']);
    Route::get('/informes/iess/consolidado', [BillingUiController::class, 'informeIessConsolidado']);
    Route::get('/informes/isspol/consolidado', [BillingUiController::class, 'informeIsspolConsolidado']);
    Route::get('/informes/issfa/consolidado', [BillingUiController::class, 'informeIssfaConsolidado']);
    Route::get('/api/billing/kpis_procedimientos.php', [BillingUiController::class, 'kpisProcedimientos']);

    Route::get('/api/billing/no-facturados', [BillingReadController::class, 'noFacturados']);
    Route::get('/api/billing/afiliaciones', [BillingReadController::class, 'afiliaciones']);
    Route::get('/api/billing/sedes', [BillingReadController::class, 'sedes']);
    Route::get('/api/billing/billing_preview.php', [BillingReadController::class, 'billingPreview']);

    // Write endpoints (legacy path mirror)
    Route::post('/billing/no-facturados/crear', [BillingWriteController::class, 'crearDesdeNoFacturado']);
    Route::post('/informes/api/eliminar-factura', [BillingWriteController::class, 'eliminarFactura']);
    Route::post('/api/billing/verificacion_derivacion.php', [BillingWriteController::class, 'verificacionDerivacion']);
    Route::post('/api/billing/insertar_billing_main.php', [BillingWriteController::class, 'insertarBillingMain']);

    // Clean aliases
    Route::post('/api/billing/no-facturados/crear', [BillingWriteController::class, 'crearDesdeNoFacturado']);
    Route::post('/api/billing/facturas/eliminar', [BillingWriteController::class, 'eliminarFactura']);
    Route::post('/api/billing/derivaciones/verificar', [BillingWriteController::class, 'verificacionDerivacion']);
    Route::post('/api/billing/procedimientos/registrar', [BillingWriteController::class, 'insertarBillingMain']);
});
