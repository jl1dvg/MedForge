<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Whatsapp\Services\ConversationWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ConversationWriteController
{
    public function __construct(
        private readonly ConversationWriteService $service = new ConversationWriteService()
    ) {
    }

    public function sendMessage(int $conversationId, Request $request): JsonResponse
    {
        $message = trim((string) $request->input('message', ''));
        $previewUrl = $request->boolean('preview_url');
        $actorUserId = LegacySessionAuth::userId($request);

        try {
            $result = $this->service->sendTextToConversation($conversationId, $message, $previewUrl, $actorUserId);

            return response()->json([
                'ok' => true,
                'data' => $result,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible enviar el mensaje desde Laravel.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
