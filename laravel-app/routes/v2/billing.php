<?php

use App\Modules\Billing\Http\Controllers\BillingReadController;
use App\Modules\Billing\Http\Controllers\BillingUiController;
use App\Modules\Billing\Http\Controllers\BillingWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::middleware(['app.permission:administrativo,billing.view,billing.manage'])->group(function (): void {
        Route::get('/billing', [BillingUiController::class, 'index']);
        Route::get('/billing/detalle', [BillingUiController::class, 'detalle']);
    });

    Route::middleware(['app.permission:administrativo,billing.no_facturados.view,billing.no_facturados.create,billing.manage'])->group(function (): void {
        Route::get('/billing/no-facturados', [BillingUiController::class, 'noFacturados']);
        Route::get('/api/billing/no-facturados', [BillingReadController::class, 'noFacturados']);
        Route::get('/api/billing/afiliaciones', [BillingReadController::class, 'afiliaciones']);
        Route::get('/api/billing/sedes', [BillingReadController::class, 'sedes']);
        Route::get('/api/billing/billing_preview.php', [BillingReadController::class, 'billingPreview']);
    });

    Route::middleware(['app.permission:administrativo,billing.dashboard.view,billing.manage'])->group(function (): void {
        Route::get('/billing/dashboard', [BillingUiController::class, 'dashboard']);
        Route::post('/billing/dashboard-data', [BillingUiController::class, 'dashboardData']);
        Route::get('/api/billing/kpis_procedimientos.php', [BillingUiController::class, 'kpisProcedimientos']);
    });

    Route::middleware(['app.permission:administrativo,billing.honorarios.view,billing.manage'])->group(function (): void {
        Route::get('/billing/honorarios', [BillingUiController::class, 'honorarios']);
        Route::post('/billing/honorarios-data', [BillingUiController::class, 'honorariosData']);
    });

    Route::middleware(['app.permission:administrativo,settings.manage,billing.manage'])->group(function (): void {
        Route::get('/billing/honorarios/settings', [BillingUiController::class, 'honorariosSettings']);
        Route::post('/billing/honorarios/settings', [BillingUiController::class, 'saveHonorariosSettings']);
    });

    Route::middleware(['app.permission:administrativo,billing.particulares.view,billing.manage'])->group(function (): void {
        Route::get('/informes/particulares', [BillingUiController::class, 'informeParticulares']);
        Route::get('/informes/particulares/referidos', [BillingUiController::class, 'informeParticularesReferidos']);
    });

    Route::middleware(['app.permission:administrativo,billing.iess.view,billing.manage'])->group(function (): void {
        Route::match(['GET', 'POST'], '/informes/iess', [BillingUiController::class, 'informeIess']);
    });

    Route::middleware(['app.permission:administrativo,billing.isspol.view,billing.manage'])->group(function (): void {
        Route::match(['GET', 'POST'], '/informes/isspol', [BillingUiController::class, 'informeIsspol']);
    });

    Route::middleware(['app.permission:administrativo,billing.issfa.view,billing.manage'])->group(function (): void {
        Route::match(['GET', 'POST'], '/informes/issfa', [BillingUiController::class, 'informeIssfa']);
    });

    Route::middleware(['app.permission:administrativo,billing.msp.view,billing.manage'])->group(function (): void {
        Route::match(['GET', 'POST'], '/informes/msp', [BillingUiController::class, 'informeMsp']);
    });

    Route::middleware(['app.permission:administrativo,billing.export,billing.manage'])->group(function (): void {
        Route::get('/informes/isspol/excel', [BillingUiController::class, 'informeIsspolExcel']);
        Route::get('/informes/issfa/excel', [BillingUiController::class, 'informeIssfaExcel']);
        Route::get('/informes/msp/excel', [BillingUiController::class, 'informeMspExcel']);
        Route::get('/informes/iess/excel', [BillingUiController::class, 'informeIessExcel']);
        Route::get('/informes/iess/consolidado', [BillingUiController::class, 'informeIessConsolidado']);
        Route::get('/informes/isspol/consolidado', [BillingUiController::class, 'informeIsspolConsolidado']);
        Route::get('/informes/issfa/consolidado', [BillingUiController::class, 'informeIssfaConsolidado']);
        Route::get('/informes/msp/consolidado', [BillingUiController::class, 'informeMspConsolidado']);
    });

    Route::middleware(['app.permission:administrativo,billing.no_facturados.create,billing.manage'])->group(function (): void {
        Route::post('/billing/no-facturados/crear', [BillingWriteController::class, 'crearDesdeNoFacturado']);
        Route::post('/api/billing/no-facturados/crear', [BillingWriteController::class, 'crearDesdeNoFacturado']);
    });

    Route::middleware(['app.permission:administrativo,billing.delete,billing.manage'])->group(function (): void {
        Route::post('/informes/api/eliminar-factura', [BillingWriteController::class, 'eliminarFactura']);
        Route::post('/api/billing/facturas/eliminar', [BillingWriteController::class, 'eliminarFactura']);
    });

    Route::middleware(['app.permission:administrativo,billing.scrape,billing.manage'])->group(function (): void {
        Route::post('/api/billing/verificacion_derivacion.php', [BillingWriteController::class, 'verificacionDerivacion']);
        Route::post('/api/billing/insertar_billing_main.php', [BillingWriteController::class, 'insertarBillingMain']);
        Route::post('/api/billing/derivaciones/verificar', [BillingWriteController::class, 'verificacionDerivacion']);
        Route::post('/api/billing/procedimientos/registrar', [BillingWriteController::class, 'insertarBillingMain']);
    });
});
