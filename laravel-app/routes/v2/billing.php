<?php

use App\Modules\Billing\Http\Controllers\BillingReadController;
use App\Modules\Billing\Http\Controllers\BillingWriteController;
use Illuminate\Support\Facades\Route;

Route::get('/api/billing/no-facturados', [BillingReadController::class, 'noFacturados']);
Route::get('/api/billing/afiliaciones', [BillingReadController::class, 'afiliaciones']);

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
