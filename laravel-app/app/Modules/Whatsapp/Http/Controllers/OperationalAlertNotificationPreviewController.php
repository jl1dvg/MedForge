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
        // Guardrail: reject any attempt to activate sending
        if ($request->query('send') !== null || $request->query('channel') !== null) {
            return response()->json([
                'ok'      => false,
                'message' => 'This endpoint is read-only. Parameters send and channel are not accepted.',
                'mode'    => 'dry_run',
                'channel' => 'none',
            ], 422);
        }

        $date = trim((string) $request->query('date', date('Y-m-d')));

        $result = $this->previewService->preview([
            'date'     => $date,
            'chat_url' => url('/v2/whatsapp/chat'),
        ]);

        // Diagnostics block (only in non-production environments, behind auth)
        if ($request->query('debug') === '1' && app()->environment('local', 'staging', 'testing')) {
            $result['diagnostics'] = [
                'source'                       => 'WhatsappOperationalAlertService',
                'notification_preview_source'  => 'WhatsappOperationalNotificationPreviewService',
                'rules_version'                => 'v1',
                'notification_policy'          => 'dry_run_only',
                'allowed_notification_types'   => ['hot_unassigned'],
                'excluded_notification_types'  => ['rescue_aging', 'supervisor_sla_breach', 'no_availability_repeated', 'ambiguous_urgent_faq'],
                'read_only_guard'              => true,
            ];
        }

        return response()->json($result);
    }
}
