<?php

declare(strict_types=1);

namespace App\Modules\CiveExtension\Http\Controllers;

use App\Modules\CiveExtension\Services\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConfigController
{
    public function __construct(private readonly ConfigService $service) {}

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
