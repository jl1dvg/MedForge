<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\ProductivityToolkitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ProductivityWriteController
{
    public function __construct(
        private readonly ProductivityToolkitService $service = new ProductivityToolkitService()
    ) {
    }

    public function storeQuickReply(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->service->createQuickReply(
                    trim((string) $request->input('title', '')),
                    trim((string) $request->input('body', '')),
                    $request->input('shortcut'),
                    $this->actorUserId()
                ),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible guardar la respuesta rápida.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeConversationNote(int $conversationId, Request $request): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->service->addConversationNote(
                    $conversationId,
                    trim((string) $request->input('body', '')),
                    $this->actorUserId()
                ),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible guardar la nota interna.',
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
