<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CampaignWriteController
{
    public function __construct(
        private readonly CampaignService $service = new CampaignService()
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->service->createDraft($request->all(), $this->actorUserId()),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible guardar la campaña.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function dryRun(int $campaignId, Request $request): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->service->executeDryRun($campaignId, $this->actorUserId()),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible ejecutar el dry run de la campaña.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    private function actorUserId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }
}
