<?php

declare(strict_types=1);

namespace App\Modules\CiveExtension\Http\Controllers;

use App\Modules\CiveExtension\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController
{
    private HealthCheckService $service;

    public function __construct()
    {
        $this->service = new HealthCheckService();
    }

    public function run(): JsonResponse
    {
        try {
            $result = $this->service->runScheduledChecks(true);
        } catch (Throwable $exception) {
            Log::error('HealthController run error: ' . $exception->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No fue posible ejecutar los health checks.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function index(): JsonResponse
    {
        try {
            $history = $this->service->latestResults(25);
        } catch (Throwable $exception) {
            Log::error('HealthController index error: ' . $exception->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No fue posible recuperar el historial de health checks.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }
}
