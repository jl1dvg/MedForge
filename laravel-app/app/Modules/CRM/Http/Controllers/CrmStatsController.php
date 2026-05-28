<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\CRM\Services\CrmStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CrmStatsController
{
    public function __construct(private readonly CrmStatsService $statsService) {}

    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        return response()->json([
            'data' => [
                'panel'    => $this->statsService->panelStats(),
                'by_stage' => $this->statsService->byStage(),
            ],
        ]);
    }
}
