<?php

namespace App\Modules\Derivaciones\Http\Controllers;

use App\Modules\Derivaciones\Services\DerivacionesScraperService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DerivacionesWriteController
{
    private DerivacionesScraperService $scraperService;

    public function __construct()
    {
        $this->scraperService = new DerivacionesScraperService(dirname(base_path()));
    }

    public function scrap(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión expirada',
            ], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $hcNumber = trim((string) $request->input('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'Faltan form_id o hc_number',
            ], 400);
        }

        try {
            $result = $this->scraperService->ejecutar($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('derivaciones.scrap.error', [
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Scrapping ejecutado',
            'data' => $result['payload'],
            'raw_output' => $result['raw_output'],
            'exit_code' => $result['exit_code'],
        ]);
    }
}
