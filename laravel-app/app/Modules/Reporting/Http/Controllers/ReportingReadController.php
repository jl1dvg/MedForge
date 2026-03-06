<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Services\ProtocolReportDataService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportingReadController
{
    private ProtocolReportDataService $service;

    public function __construct()
    {
        $this->service = new ProtocolReportDataService();
    }

    public function protocolData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->service->buildProtocolData($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.protocol_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data del reporte.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if ($payload === []) {
            return response()
                ->json(['error' => 'No se encontró el protocolo solicitado.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.protocol_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-protocol-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    private function requestId(Request $request): string
    {
        $header = trim((string) $request->header('X-Request-Id', ''));
        if ($header !== '') {
            return $header;
        }

        return 'v2-reporting-' . bin2hex(random_bytes(8));
    }
}
