<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SolicitudesReadController
{
    private SolicitudesReadParityService $service;

    public function __construct()
    {
        $this->service = new SolicitudesReadParityService();
    }

    public function kanbanData(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'data' => [],
                'options' => [
                    'afiliaciones' => [],
                    'doctores' => [],
                ],
                'error' => 'Sesion expirada',
            ], 401)->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->payload($request);
            $result = $this->service->kanbanData($payload);
        } catch (\Throwable $e) {
            Log::error('solicitudes.read.kanban_data.error', [
                'request_id' => $requestId,
                'user_id' => LegacySessionAuth::userId($request),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'data' => [],
                'options' => [
                    'afiliaciones' => [],
                    'doctores' => [],
                ],
                'error' => 'No se pudo cargar la informacion de solicitudes',
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesion expirada'], 401)->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->payload($request);
            $result = $this->service->dashboardData($payload);
        } catch (\Throwable $e) {
            Log::error('solicitudes.read.dashboard_data.error', [
                'request_id' => $requestId,
                'user_id' => LegacySessionAuth::userId($request),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo cargar el dashboard de solicitudes.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function turneroData(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['data' => [], 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $estadoRaw = trim((string) $request->query('estado', ''));
        $requestedStates = $estadoRaw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $estadoRaw)), static fn(string $state): bool => $state !== ''));

        try {
            $result = $this->service->turneroData($requestedStates);
        } catch (\Throwable $e) {
            Log::error('solicitudes.read.turnero_data.error', [
                'request_id' => $requestId,
                'user_id' => LegacySessionAuth::userId($request),
                'error' => $e->getMessage(),
                'estado_filter' => $requestedStates,
            ]);

            return response()->json(['data' => [], 'error' => 'No se pudo cargar el turnero'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function crmResumen(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->crmResumen($id);
        } catch (RuntimeException $e) {
            $status = strcasecmp(trim($e->getMessage()), 'Solicitud no encontrada') === 0 ? 404 : 422;

            return response()->json(['success' => false, 'error' => $e->getMessage()], $status)
                ->header('X-Request-Id', $requestId);
        } catch (\Throwable $e) {
            Log::error('solicitudes.read.crm_resumen.error', [
                'request_id' => $requestId,
                'user_id' => LegacySessionAuth::userId($request),
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo cargar el detalle CRM'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true, 'data' => $result])->header('X-Request-Id', $requestId);
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

    private function requestId(Request $request): string
    {
        $header = trim((string) $request->header('X-Request-Id', ''));
        if ($header !== '') {
            return $header;
        }

        return 'v2-' . bin2hex(random_bytes(8));
    }
}
