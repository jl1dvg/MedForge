<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardParityService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardReadController
{
    private DashboardParityService $service;

    public function __construct()
    {
        $this->service = new DashboardParityService();
    }

    public function summary(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->service->buildSummary(
                (string) $request->query('start_date', ''),
                (string) $request->query('end_date', '')
            );
        } catch (\Throwable $e) {
            Log::error('dashboard.read.summary.error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar el dashboard.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('dashboard.read.summary', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
        ]);

        return response()->json($payload)->header('X-Request-Id', $requestId);
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
