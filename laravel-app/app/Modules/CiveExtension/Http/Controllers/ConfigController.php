<?php

declare(strict_types=1);

namespace App\Modules\CiveExtension\Http\Controllers;

use App\Modules\CiveExtension\Services\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConfigController
{
    private ConfigService $service;

    public function __construct()
    {
        $this->service = new ConfigService();
    }

    public function show(): JsonResponse
    {
        try {
            $config = $this->service->getExtensionConfig();
        } catch (Throwable $exception) {
            Log::error('ConfigController error: ' . $exception->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No fue posible recuperar la configuración.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'config' => $config,
        ]);
    }
}
