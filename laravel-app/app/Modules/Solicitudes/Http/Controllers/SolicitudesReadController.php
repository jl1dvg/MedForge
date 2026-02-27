<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use DateTimeImmutable;
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

    public function conciliacionCirugias(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesion expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $dateFromRaw = trim((string) $request->query('date_from', ''));
        $dateToRaw = trim((string) $request->query('date_to', ''));
        $mes = trim((string) $request->query('mes', ''));
        $debugEnabled = filter_var((string) $request->query('debug', '0'), FILTER_VALIDATE_BOOLEAN);
        $debugHc = trim((string) $request->query('debug_hc', (string) $request->query('hc_number', '')));
        $debugFormId = trim((string) $request->query('debug_form_id', (string) $request->query('form_id', '')));
        $debugSolicitudId = (int) $request->query('debug_id', (int) $request->query('id', 0));
        $debugLimit = (int) $request->query('debug_limit', 25);

        $inicioBase = null;
        $finBase = null;

        if ($dateFromRaw !== '' || $dateToRaw !== '') {
            $dateFrom = $dateFromRaw !== '' ? $this->parseDateInput($dateFromRaw) : null;
            $dateTo = $dateToRaw !== '' ? $this->parseDateInput($dateToRaw) : null;

            if ($dateFromRaw !== '' && !$dateFrom) {
                return response()->json(
                    ['success' => false, 'error' => 'El parámetro "date_from" no tiene un formato válido.'],
                    422
                )->header('X-Request-Id', $requestId);
            }

            if ($dateToRaw !== '' && !$dateTo) {
                return response()->json(
                    ['success' => false, 'error' => 'El parámetro "date_to" no tiene un formato válido.'],
                    422
                )->header('X-Request-Id', $requestId);
            }

            $inicioBase = $dateFrom ?: $dateTo;
            $finBase = $dateTo ?: $dateFrom;
        }

        if (!$inicioBase || !$finBase) {
            $base = null;
            if ($mes !== '') {
                $base = DateTimeImmutable::createFromFormat('!Y-m', $mes) ?: null;
                if (!$base) {
                    return response()->json(
                        ['success' => false, 'error' => 'El parámetro "mes" debe tener formato YYYY-MM.'],
                        422
                    )->header('X-Request-Id', $requestId);
                }
            }

            if (!$base) {
                $base = new DateTimeImmutable('first day of this month');
            }

            $inicioBase = $base;
            $finBase = $base->modify('last day of this month');
        }

        $inicio = $inicioBase->setTime(0, 0, 0);
        $fin = $finBase->setTime(23, 59, 59);
        if ($inicio > $fin) {
            [$inicio, $fin] = [$fin, $inicio];
            $inicio = $inicio->setTime(0, 0, 0);
            $fin = $fin->setTime(23, 59, 59);
        }

        try {
            $data = $this->service->conciliacionCirugiasMes($inicio, $fin);
        } catch (\Throwable $e) {
            Log::error('solicitudes.read.conciliacion_cirugias.error', [
                'request_id' => $requestId,
                'user_id' => LegacySessionAuth::userId($request),
                'date_from' => $inicio->format('Y-m-d'),
                'date_to' => $fin->format('Y-m-d'),
                'mes' => $mes !== '' ? $mes : $inicio->format('Y-m'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo cargar la conciliación.',
            ], 500)->header('X-Request-Id', $requestId);
        }

        $debugPayload = null;
        if ($debugEnabled) {
            try {
                $debugPayload = $this->service->diagnosticarConciliacion($data, [
                    'hc_number' => $debugHc,
                    'solicitud_id' => $debugSolicitudId,
                    'form_id' => $debugFormId,
                    'limit' => $debugLimit,
                ]);
            } catch (\Throwable $e) {
                $debugPayload = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        $total = count($data);
        $confirmadas = 0;
        $conMatch = 0;
        foreach ($data as $row) {
            if (!empty($row['protocolo_confirmado'])) {
                $confirmadas++;
            }
            if (!empty($row['protocolo_posterior_compatible'])) {
                $conMatch++;
            }
        }

        return response()->json([
            'success' => true,
            'periodo' => [
                'mes' => $inicio->format('Y-m'),
                'from' => $inicio->format('Y-m-d'),
                'to' => $fin->format('Y-m-d'),
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ],
            'totales' => [
                'total' => $total,
                'con_match' => $conMatch,
                'confirmadas' => $confirmadas,
                'sin_match' => max(0, $total - $conMatch),
            ],
            'debug' => $debugPayload,
            'data' => $data,
        ])->header('X-Request-Id', $requestId);
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

    private function parseDateInput(string $value): ?DateTimeImmutable
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y'];
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $raw);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
