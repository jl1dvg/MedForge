<?php

declare(strict_types=1);

namespace App\Modules\Consultas\Http\Controllers;

use App\Modules\Consultas\Services\ConsultasParityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ConsultasReadController
{
    private ConsultasParityService $service;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $this->service = new ConsultasParityService($pdo);
    }

    public function anterior(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);

        $hcNumber = $request->query('hcNumber');
        $formId = $request->query('form_id');
        $procedimiento = $request->query('procedimiento');

        try {
            $result = $this->service->consultaAnterior(
                is_string($hcNumber) ? $hcNumber : null,
                is_string($formId) ? $formId : null,
                is_string($procedimiento) ? $procedimiento : null,
            );
        } catch (Throwable $e) {
            Log::error('consultas.read.anterior.error', [
                'request_id' => $requestId,
                'hc_number' => $hcNumber,
                'form_id' => $formId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar consulta anterior',
                'error' => $e->getMessage(),
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function plan(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);

        $formId = $request->query('form_id', $request->query('formId'));
        $hcNumber = $request->query('hcNumber', $request->query('hc_number'));

        try {
            $result = $this->service->obtenerPlan(
                is_string($formId) ? $formId : null,
                is_string($hcNumber) ? $hcNumber : null,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400)->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('consultas.read.plan.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo procesar la solicitud de plan',
                'error' => $e->getMessage(),
            ], 500)->header('X-Request-Id', $requestId);
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
