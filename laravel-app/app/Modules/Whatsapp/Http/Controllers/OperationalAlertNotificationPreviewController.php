<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappOperationalNotificationPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only dry-run preview of future Fase 4C notifications.
 *
 * No messages are sent. No DB writes. channel=none.
 */
class OperationalAlertNotificationPreviewController
{
    public function __construct(
        private readonly WhatsappOperationalNotificationPreviewService $previewService
            = new WhatsappOperationalNotificationPreviewService()
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $date = trim((string) $request->query('date', date('Y-m-d')));

        $result = $this->previewService->preview([
            'date'     => $date,
            'chat_url' => url('/v2/whatsapp/chat'),
        ]);

        return response()->json($result);
    }
}
