<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class WhatsappLeadWriteController
{
    public function __construct(
        private readonly WhatsappLeadService $service = new WhatsappLeadService()
    ) {
    }

    public function store(int $conversationId, Request $request): JsonResponse
    {
        try {
            $motivoBaja = trim((string) ($request->input('motivo_baja', '')));
            $actorUserId = (int) ($request->attributes->get('auth_user_id') ?? Auth::id() ?? 0);

            $lead = $this->service->createFromConversation($conversationId, $motivoBaja, $actorUserId);

            return response()->json(['ok' => true, 'data' => $lead], 201);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updateStatus(int $leadId, Request $request): JsonResponse
    {
        try {
            $status = trim((string) ($request->input('status', '')));
            $lead   = $this->service->updateStatus($leadId, $status);

            return response()->json(['ok' => true, 'data' => $lead]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
