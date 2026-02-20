<?php

namespace App\Modules\Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HealthController
{
    public function __invoke(Request $request): JsonResponse
    {
        $requestId = (string) ($request->headers->get('X-Request-Id') ?: Str::uuid());
        $dbStatus = 'up';
        $statusCode = 200;

        try {
            DB::select('SELECT 1 AS ok');
        } catch (\Throwable $exception) {
            $dbStatus = 'down';
            $statusCode = 503;
        }

        return response()
            ->json([
                'service' => 'medforge-laravel-v2',
                'status' => $dbStatus === 'up' ? 'ok' : 'degraded',
                'db' => $dbStatus,
                'timestamp' => now()->toIso8601String(),
            ], $statusCode)
            ->header('X-Request-Id', $requestId);
    }
}
