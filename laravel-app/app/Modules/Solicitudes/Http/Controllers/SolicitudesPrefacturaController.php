<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Solicitudes\Services\SolicitudesPrefacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SolicitudesPrefacturaController
{
    private SolicitudesPrefacturaService $service;

    public function __construct()
    {
        $this->service = new SolicitudesPrefacturaService();
    }

    public function prefactura(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response('<p class="text-danger mb-0">Sesión expirada.</p>', 401);
        }

        $hcNumber = trim((string) $request->query('hc_number', ''));
        $formId = trim((string) $request->query('form_id', ''));

        if ($hcNumber === '' || $formId === '') {
            return response('<p class="text-danger mb-0">Faltan parámetros para mostrar la prefactura.</p>', 400);
        }

        try {
            $viewData = $this->service->buildPrefacturaViewData($hcNumber, $formId);
        } catch (Throwable $e) {
            Log::error('solicitudes.prefactura.load.error', [
                'hc_number' => $hcNumber,
                'form_id' => $formId,
                'error' => $e->getMessage(),
            ]);

            return response('<p class="text-danger mb-0">No se pudo cargar la información de la solicitud.</p>', 500);
        }

        if (empty($viewData['solicitud'])) {
            return response('<p class="text-danger mb-0">No se encontraron datos para la solicitud seleccionada.</p>', 404);
        }

        return response()->view('solicitudes.prefactura_detalle', [
            'viewData' => $viewData,
            'slaLabels' => $this->defaultSlaLabels(),
        ]);
    }

    public function derivacion(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $hcNumber = trim((string) $request->query('hc_number', ''));
        $formId = trim((string) $request->query('form_id', ''));
        $solicitudId = (int) $request->query('solicitud_id', 0);

        if ($hcNumber === '' || $formId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros para consultar la derivación.',
            ], 400)->header('X-Request-Id', $requestId);
        }

        try {
            $derivacion = $this->service->resolveDerivacion($formId, $hcNumber, $solicitudId > 0 ? $solicitudId : null);
        } catch (Throwable $e) {
            Log::warning('solicitudes.prefactura.derivacion.error', [
                'request_id' => $requestId,
                'hc_number' => $hcNumber,
                'form_id' => $formId,
                'solicitud_id' => $solicitudId > 0 ? $solicitudId : null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'has_derivacion' => false,
                'derivacion_status' => 'error',
                'derivacion' => null,
            ])->header('X-Request-Id', $requestId);
        }

        if (!$derivacion) {
            return response()->json([
                'success' => true,
                'has_derivacion' => false,
                'derivacion_status' => 'missing',
                'message' => 'No hay derivación registrada para esta solicitud.',
                'derivacion' => null,
            ])->header('X-Request-Id', $requestId);
        }

        return response()->json([
            'success' => true,
            'has_derivacion' => true,
            'derivacion_status' => 'ok',
            'message' => null,
            'derivacion' => $derivacion,
        ])->header('X-Request-Id', $requestId);
    }

    public function derivacionPreseleccion(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $solicitudId = isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : null;

        if ($hcNumber === '' || $formId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros para consultar derivaciones disponibles.',
            ], 400)->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->resolveDerivacionPreseleccion($hcNumber, $formId, $solicitudId);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422)->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.prefactura.derivacion_preseleccion.error', [
                'request_id' => $requestId,
                'hc_number' => $hcNumber,
                'form_id' => $formId,
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener las derivaciones disponibles.',
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function rescrapeDerivacion(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $solicitudId = isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : null;

        if ($formId === '' || $hcNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'Faltan datos (form_id / hc_number).',
            ], 400)->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->rescrapeDerivacion($formId, $hcNumber, $solicitudId);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422)->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.prefactura.rescrape.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo re-scrapear la derivación.',
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function coberturaMail(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);

        /** @var UploadedFile|null $attachment */
        $attachment = $request->file('attachment');

        try {
            $result = $this->service->sendCoberturaMail(
                $payload,
                $attachment,
                LegacySessionAuth::userId($request)
            );
        } catch (RuntimeException $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 422;
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status)->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.prefactura.cobertura_mail.error', [
                'request_id' => $requestId,
                'payload_keys' => array_keys($payload),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo enviar el correo de cobertura',
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(Request $request): array
    {
        $all = $request->all();
        $json = $request->json()->all();

        if (!is_array($all)) {
            $all = [];
        }
        if (!is_array($json)) {
            $json = [];
        }

        return array_merge($all, $json);
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function defaultSlaLabels(): array
    {
        return [
            'en_rango' => ['color' => 'success', 'label' => 'SLA en rango', 'icon' => 'mdi-check-circle-outline'],
            'advertencia' => ['color' => 'warning', 'label' => 'SLA 72h', 'icon' => 'mdi-timer-sand'],
            'critico' => ['color' => 'danger', 'label' => 'SLA crítico', 'icon' => 'mdi-alert-octagon'],
            'vencido' => ['color' => 'dark', 'label' => 'SLA vencido', 'icon' => 'mdi-alert'],
            'sin_fecha' => ['color' => 'secondary', 'label' => 'SLA sin fecha', 'icon' => 'mdi-calendar-remove'],
            'cerrado' => ['color' => 'secondary', 'label' => 'SLA cerrado', 'icon' => 'mdi-lock-outline'],
        ];
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
