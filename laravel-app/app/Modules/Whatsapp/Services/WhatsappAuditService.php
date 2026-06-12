<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAuditLog;

class WhatsappAuditService
{
    public function log(
        string $eventType,
        string $severity = 'info',
        ?int $conversationId = null,
        ?int $messageId = null,
        ?string $waNumber = null,
        ?string $patientHcNumber = null,
        ?int $userId = null,
        ?string $summary = null,
        array $payload = [],
        ?string $scenarioId = null,
        ?string $nodeId = null,
        ?string $actionType = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $metaRequestId = null,
    ): void {
        if (!config('whatsapp.audit.enabled', false)) {
            return;
        }

        try {
            WhatsappAuditLog::create([
                'conversation_id'  => $conversationId,
                'message_id'       => $messageId,
                'wa_number'        => $waNumber,
                'patient_hc_number' => $patientHcNumber,
                'user_id'          => $userId,
                'event_type'       => $eventType,
                'severity'         => $severity,
                'summary'          => $summary,
                'payload'          => $payload !== [] ? $payload : null,
                'scenario_id'      => $scenarioId,
                'node_id'          => $nodeId,
                'action_type'      => $actionType,
                'before_state'     => $beforeState,
                'after_state'      => $afterState,
                'error_code'       => $errorCode,
                'error_message'    => $errorMessage,
                'meta_request_id'  => $metaRequestId,
                'occurred_at'      => now(),
            ]);
        } catch (\Throwable) {
            // La auditoría nunca debe interrumpir el flujo principal
        }
    }
}
