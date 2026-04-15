<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignReadController
{
    public function __construct(
        private readonly CampaignService $service = new CampaignService()
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->overview(),
        ]);
    }

    public function audienceSuggestions(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->audienceSuggestions(
                (string) $request->query('segment', 'recent_open'),
                (int) $request->query('limit', 25),
            ),
        ]);
    }
}
