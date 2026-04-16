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
        $messageType = trim((string) $request->input('message_type', 'text'));
        $mediaUrl = trim((string) $request->input('media_url', ''));
        $filename = trim((string) $request->input('filename', ''));
        $mimeType = trim((string) $request->input('mime_type', ''));
        $mediaDisk = trim((string) $request->input('media_disk', ''));
        $mediaPath = trim((string) $request->input('media_path', ''));
        $actorUserId = LegacySessionAuth::userId($request);

        try {
            $result = $messageType === 'text'
                ? $this->service->sendTextToConversation($conversationId, $message, $previewUrl, $actorUserId)
                : $this->service->sendMediaToConversation(
                    $conversationId,
                    $messageType,
                    $mediaUrl,
                    $message !== '' ? $message : null,
                    $filename !== '' ? $filename : null,
                    $mimeType !== '' ? $mimeType : null,
                    $mediaDisk !== '' ? $mediaDisk : null,
                    $mediaPath !== '' ? $mediaPath : null,
                    $actorUserId
                );

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
