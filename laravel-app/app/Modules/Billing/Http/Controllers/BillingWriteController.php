<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Services\BillingWriteParityService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class BillingWriteController
{
    private BillingWriteParityService $service;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->service = new BillingWriteParityService($pdo);
    }

    public function crearDesdeNoFacturado(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }
            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $hcNumber = trim((string) $request->input('hc_number', ''));
        if ($formId === '' || $hcNumber === '') {
            return response('Faltan parámetros.', 400)->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->crearDesdeNoFacturado($formId, $hcNumber, LegacySessionAuth::userId($request));
            Log::info('billing.write.crear_desde_no_facturado', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'created' => $result['created'] ?? false,
                'billing_id' => $result['billing_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('billing.write.crear_desde_no_facturado.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);
            return response('Ocurrió un error al crear la facturación.', 500)->header('X-Request-Id', $requestId);
        }

        return redirect('/v2/billing/detalle?form_id=' . urlencode($formId))->header('X-Request-Id', $requestId);
    }

    public function eliminarFactura(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()
                ->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', ''));
        if ($formId === '') {
            return response()
                ->json(['success' => false, 'message' => 'Solicitud inválida.'], 400)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $deleted = $this->service->eliminarFactura($formId);
            Log::info('billing.write.eliminar_factura', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'deleted' => $deleted,
            ]);
        } catch (\Throwable $e) {
            Log::error('billing.write.eliminar_factura.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'error' => $e->getMessage(),
            ]);
            return response()
                ->json(['success' => false, 'message' => 'Error al eliminar la factura.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()
            ->json([
                'success' => $deleted,
                'message' => $deleted ? 'Factura eliminada correctamente.' : 'No se encontró factura para el form_id indicado.',
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function verificacionDerivacion(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()
                ->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $raw = $request->input('form_ids', []);
        $formIds = is_array($raw) ? $raw : [$raw];

        $result = $this->service->verificarFormIds($formIds);
        Log::info('billing.write.verificacion_derivacion', [
            'request_id' => $requestId,
            'form_ids_count' => count($formIds),
            'success' => $result['success'] ?? null,
        ]);

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function insertarBillingMain(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()
                ->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $raw = (string) $request->getContent();
        $payload = json_decode($raw ?: 'null', true);
        if (!is_array($payload)) {
            return response()
                ->json(['success' => false, 'error' => 'JSON inválido o vacío', 'raw' => $raw], 400)
                ->header('X-Request-Id', $requestId);
        }

        $procedimientos = $payload['procedimientos'] ?? null;
        if (!is_array($procedimientos)) {
            return response()
                ->json(['success' => false, 'error' => 'Formato inválido: se esperaba "procedimientos" como arreglo'], 400)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->registrarProcedimientoCompleto($procedimientos, LegacySessionAuth::userId($request));
            Log::info('billing.write.insertar_billing_main', [
                'request_id' => $requestId,
                'procedimientos_count' => count($procedimientos),
                'errores_count' => count($result['errores'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('billing.write.insertar_billing_main.error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return response()
                ->json(['success' => false, 'error' => $e->getMessage()], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    private function requestId(Request $request): string
    {
        $header = trim((string) $request->header('X-Request-Id', ''));
        if ($header !== '') {
            return $header;
        }

        return 'v2-' . bin2hex(random_bytes(8));
    }
}
