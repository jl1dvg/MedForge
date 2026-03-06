<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Services\BillingUiService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingUiController
{
    private BillingUiService $service;

    public function __construct()
    {
        $this->service = new BillingUiService();
    }

    public function index(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $mes = trim((string) $request->query('mes', ''));
        $facturas = $this->service->listarFacturas($mes !== '' ? $mes : null);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $facturas,
                'meta' => [
                    'mes' => $mes,
                    'total' => count($facturas),
                ],
            ]);
        }

        return view('billing.v2-index', [
            'pageTitle' => 'Billing',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'mesSeleccionado' => $mes,
            'facturas' => $facturas,
        ]);
    }

    public function noFacturados(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'datatable' => '/v2/api/billing/no-facturados',
                    'afiliaciones' => '/v2/api/billing/afiliaciones',
                    'crear' => '/v2/billing/no-facturados/crear',
                ],
            ]);
        }

        return view('billing.v2-no-facturados', [
            'pageTitle' => 'No Facturados',
            'currentUser' => LegacyCurrentUser::resolve($request),
        ]);
    }

    public function detalle(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $formId = trim((string) $request->query('form_id', ''));
        if ($formId === '') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'form_id es obligatorio'], 422);
            }

            return redirect('/v2/billing');
        }

        $detalle = $this->service->obtenerDetalleFactura($formId);
        if ($detalle === null) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Factura no encontrada'], 404);
            }

            return view('billing.v2-detalle-missing', [
                'pageTitle' => 'Factura no encontrada',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'formId' => $formId,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $detalle]);
        }

        return view('billing.v2-detalle', [
            'pageTitle' => 'Detalle de factura',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'detalle' => $detalle,
        ]);
    }

    private function isLegacyAuthenticated(Request $request): bool
    {
        return LegacySessionAuth::isAuthenticated($request);
    }
}
