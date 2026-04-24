<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\ConversationStartService;
use App\Modules\Whatsapp\Services\ConversationWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ConversationWriteController
{
    public function __construct(
        private readonly ConversationWriteService $service = new ConversationWriteService(),
        private readonly ConversationStartService $startService = new ConversationStartService(),
    ) {
    }

    public function searchContacts(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->startService->searchContacts(
                    (string) $request->query('q', ''),
                    (int) $request->query('limit', 15)
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible buscar contactos.',
                'detail' => $e->getMessage(),
            ], 500);
        }
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
        $actorUserId = $this->actorUserId();

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

    public function startWithTemplate(Request $request): JsonResponse
    {
        $actorUserId = $this->actorUserId();

        try {
            $result = $this->startService->startConversationWithTemplate(
                (string) $request->input('wa_number', ''),
                (int) $request->input('template_id', 0),
                $actorUserId,
                $request->filled('contact_name') ? (string) $request->input('contact_name') : null,
                $request->filled('patient_hc_number') ? (string) $request->input('patient_hc_number') : null,
                $request->filled('patient_full_name') ? (string) $request->input('patient_full_name') : null,
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
                'error' => 'No fue posible iniciar la conversación con plantilla.',
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
