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

class ConsultasWriteController
{
    private ConsultasParityService $service;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $this->service = new ConsultasParityService($pdo);
    }

    public function guardar(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $payload = $this->payload($request);

        try {
            $result = $this->service->guardar($payload);
        } catch (Throwable $e) {
            Log::error('consultas.write.guardar.error', [
                'request_id' => $requestId,
                'payload_keys' => array_keys($payload),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la consulta',
                'error' => $e->getMessage(),
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function plan(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        $payload = $this->payload($request);

        try {
            $result = $this->service->actualizarPlan($payload);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400)->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('consultas.write.plan.error', [
                'request_id' => $requestId,
                'payload_keys' => array_keys($payload),
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

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $json = $request->json()->all();
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $all = $request->all();
        return is_array($all) ? $all : [];
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
