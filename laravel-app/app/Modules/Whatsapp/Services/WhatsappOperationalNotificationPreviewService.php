<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Support\Carbon;

/**
 * Dry-run preview of future operational notifications.
 *
 * read_only=true, db_writes=0 — no messages sent, no events inserted,
 * no scheduler, no external channels. Exists only to show what Fase 4C
 * would notify once coordinación approves activation.
 */
class WhatsappOperationalNotificationPreviewService
{
    private const CANDIDATE_TYPE     = WhatsappOperationalAlertService::ALERT_HOT_UNASSIGNED;
    private const CANDIDATE_SEVERITY = 'critical';

    public function __construct(
        private readonly WhatsappOperationalAlertService $alertService = new WhatsappOperationalAlertService()
    ) {
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function preview(array $options = []): array
    {
        $date    = (string) ($options['date'] ?? date('Y-m-d'));
        $chatUrl = (string) ($options['chat_url'] ?? url('/v2/whatsapp/chat'));

        $result = $this->alertService->alerts([
            'date'          => $date,
            'severity'      => self::CANDIDATE_SEVERITY,
            'include_items' => true,
        ]);

        $candidates = array_values(array_filter(
            $result['alerts'] ?? [],
            static fn (array $a): bool =>
                ($a['alert_type'] ?? '')   === self::CANDIDATE_TYPE
                && ($a['severity'] ?? '')  === self::CANDIDATE_SEVERITY
                && ($a['assigned_user_id'] ?? null) === null
        ));

        $notifications = array_map(
            fn (array $a): array => $this->buildPreviewItem($a, $chatUrl),
            $candidates
        );

        return [
            'ok'           => true,
            'mode'         => 'dry_run',
            'read_only'    => true,
            'db_writes'    => 0,
            'channel'      => 'none',
            'would_notify' => count($notifications),
            'evaluated'    => $result['evaluated'] ?? 0,
            'date'         => $date,
            'notifications' => $notifications,
        ];
    }

    /**
     * @param array<string,mixed> $alert
     * @return array<string,mixed>
     */
    private function buildPreviewItem(array $alert, string $chatUrl): array
    {
        $convId      = (int)    ($alert['conversation_id'] ?? 0);
        $waNumber    = (string) ($alert['wa_number']       ?? '');
        $displayName = (string) ($alert['display_name']    ?? $waNumber ?: 'Sin nombre');
        $hcNumber    = (string) ($alert['hc_number']       ?? '');
        $topicLabel  = (string) ($alert['topic_label']     ?? $alert['topic'] ?? '');
        $waitingMin  = (int)    ($alert['waiting_minutes'] ?? 0);

        $hcPart  = $hcNumber !== '' ? "\nHC: {$hcNumber}" : '';
        $chatLink = $waNumber !== ''
            ? "{$chatUrl}?search={$waNumber}&filter=all"
            : "{$chatUrl}?conversation={$convId}";

        $messagePreview = implode("\n", array_filter([
            '🚨 Alerta WhatsApp crítica',
            '',
            "Paciente/contacto: {$displayName}",
            "WhatsApp: {$waNumber}" . $hcPart,
            "Motivo: {$topicLabel}",
            "Tiempo esperando: {$waitingMin} min",
            'Estado: Sin asignar',
            '',
            "Abrir en chat:\n{$chatLink}",
        ]));

        return [
            'conversation_id' => $convId,
            'wa_number'       => $waNumber,
            'display_name'    => $displayName,
            'hc_number'       => $hcNumber,
            'alert_type'      => (string) ($alert['alert_type'] ?? ''),
            'severity'        => (string) ($alert['severity'] ?? ''),
            'topic_label'     => $topicLabel,
            'waiting_minutes' => $waitingMin,
            'chat_url'        => $chatLink,
            'message_preview' => $messagePreview,
        ];
    }
}
