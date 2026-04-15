<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\ProductivityToolkitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductivityReadController
{
    public function __construct(
        private readonly ProductivityToolkitService $service = new ProductivityToolkitService()
    ) {
    }

    public function quickReplies(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listQuickReplies(
                trim((string) $request->query('search', '')),
                (int) $request->query('limit', 25),
            ),
        ]);
    }

    public function conversationNotes(int $conversationId, Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->service->listConversationNotes(
                $conversationId,
                (int) $request->query('limit', 20),
            ),
        ]);
    }
}
