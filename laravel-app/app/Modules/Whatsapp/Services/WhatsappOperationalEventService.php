<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappOperationalEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class WhatsappOperationalEventService
{
    /** @var array<string,string> */
    public const EVENT_GROUPS = [
        'conversation_started' => 'conversation',
        'conversation_updated' => 'conversation',
        'conversation_closed' => 'conversation',
        'conversation_lost' => 'conversation',
        'intent_classified' => 'classification',
        'topic_classified' => 'classification',
        'bucket_classified' => 'classification',
        'priority_scored' => 'classification',
        'patient_identified' => 'patient',
        'patient_not_identified' => 'patient',
        'consent_granted' => 'patient',
        'consent_missing' => 'patient',
        'handoff_created' => 'handoff',
        'handoff_requeued' => 'handoff',
        'handoff_resolved' => 'handoff',
        'handoff_expired' => 'handoff',
        'autoassign_attempted' => 'assignment',
        'auto_assigned' => 'assignment',
        'autoassign_skipped' => 'assignment',
        'autoassign_failed' => 'assignment',
        'agent_taken' => 'assignment',
        'manual_assigned' => 'assignment',
        'legacy_assigned' => 'assignment',
        'transferred' => 'assignment',
        'assignment_rollback' => 'assignment',
        'first_response_after_assignment' => 'human_response',
        'agent_message_sent' => 'human_response',
        'agent_no_response' => 'human_response',
        'meta_window_expiring' => 'meta_template',
        'template_required' => 'meta_template',
        'template_rescue_sent' => 'meta_template',
        'template_rescue_failed' => 'meta_template',
        'template_rescue_replied' => 'meta_template',
        'availability_requested' => 'agenda',
        'availability_empty' => 'agenda',
        'booking_attempted' => 'agenda',
        'booking_created' => 'agenda',
        'booking_failed' => 'agenda',
        'reminder_sent' => 'reminder',
        'reminder_confirmed' => 'reminder',
        'reminder_failed' => 'reminder',
        'reminder_agent_requested' => 'reminder',
        'supervisor_alerted' => 'alert',
        'supervisor_acknowledged' => 'alert',
        'supervisor_resolved' => 'alert',
        'operational_attribution_created' => 'attribution',
        'operational_attribution_failed' => 'attribution',
        'job_started' => 'system',
        'job_completed' => 'system',
        'job_failed' => 'system',
        'integration_error' => 'system',
    ];

    /** @var array<string,string> */
    private const LEGACY_HANDOFF_EVENT_MAP = [
        'requested' => 'handoff_created',
        'queued' => 'handoff_created',
        'requeued' => 'handoff_requeued',
        'expired' => 'handoff_expired',
        'auto_assigned' => 'auto_assigned',
        'assigned' => 'legacy_assigned',
        'resolved' => 'handoff_resolved',
        'autoassign_rollback_level_a' => 'assignment_rollback',
        'transferred' => 'transferred',
    ];

    /**
     * @param array<string,mixed> $attributes
     */
    public function record(array $attributes): ?WhatsappOperationalEvent
    {
        if (!Schema::hasTable('whatsapp_operational_events')) {
            return null;
        }

        $eventType = trim((string) ($attributes['event_type'] ?? ''));
        if (!isset(self::EVENT_GROUPS[$eventType])) {
            throw new InvalidArgumentException("Evento operacional no soportado: {$eventType}");
        }

        $conversationId = (int) ($attributes['conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            return null;
        }

        $eventAt = $this->normalizeDateTime($attributes['event_at'] ?? null);
        $idempotencyKey = trim((string) ($attributes['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = $this->makeIdempotencyKey($attributes + [
                'conversation_id' => $conversationId,
                'event_type' => $eventType,
                'event_at' => $eventAt,
            ]);
        }

        $existing = WhatsappOperationalEvent::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof WhatsappOperationalEvent) {
            return $existing;
        }

        return WhatsappOperationalEvent::query()->create([
            'conversation_id' => $conversationId,
            'handoff_id' => $this->nullableInt($attributes['handoff_id'] ?? null),
            'booking_id' => $this->nullableInt($attributes['booking_id'] ?? null),
            'reminder_id' => $this->nullableInt($attributes['reminder_id'] ?? null),
            'message_id' => $this->nullableInt($attributes['message_id'] ?? null),
            'event_type' => $eventType,
            'event_group' => self::EVENT_GROUPS[$eventType],
            'event_at' => $eventAt,
            'actor_type' => $this->normalizeActorType((string) ($attributes['actor_type'] ?? 'system')),
            'actor_user_id' => $this->nullableInt($attributes['actor_user_id'] ?? null),
            'producer' => mb_substr(trim((string) ($attributes['producer'] ?? 'unknown')), 0, 96),
            'bucket' => $this->nullableString($attributes['bucket'] ?? null, 48),
            'topic' => $this->nullableString($attributes['topic'] ?? null, 96),
            'priority_score' => isset($attributes['priority_score']) ? (float) $attributes['priority_score'] : null,
            'wa_number' => $this->nullableString($attributes['wa_number'] ?? null, 32),
            'patient_hc_number' => $this->nullableString($attributes['patient_hc_number'] ?? null, 64),
            'reason' => $this->nullableString($attributes['reason'] ?? null, 191),
            'payload' => is_array($attributes['payload'] ?? null) ? $attributes['payload'] : null,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * @param array<string,mixed> $extra
     */
    public function recordForConversation(
        WhatsappConversation $conversation,
        string $eventType,
        string $producer,
        array $extra = [],
    ): ?WhatsappOperationalEvent {
        return $this->record(array_merge($extra, [
            'conversation_id' => (int) $conversation->id,
            'event_type' => $eventType,
            'producer' => $producer,
            'wa_number' => (string) $conversation->wa_number,
            'patient_hc_number' => $conversation->patient_hc_number,
        ]));
    }

    public function recordBookingCreated(int $bookingId): ?WhatsappOperationalEvent
    {
        if ($bookingId <= 0 || !Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return null;
        }

        $booking = DB::table('whatsapp_sigcenter_bookings')->where('id', $bookingId)->first();
        if ($booking === null || (int) ($booking->conversation_id ?? 0) <= 0) {
            return null;
        }

        return $this->record([
            'conversation_id' => (int) $booking->conversation_id,
            'booking_id' => $bookingId,
            'event_type' => 'booking_created',
            'event_at' => $booking->booked_at ?? $booking->created_at ?? now(),
            'actor_type' => 'integration',
            'producer' => 'flow_sigcenter_agenda',
            'wa_number' => $booking->wa_number ?? null,
            'patient_hc_number' => $booking->patient_hc_number ?? null,
            'payload' => [
                'sigcenter_agenda_id' => $booking->sigcenter_agenda_id ?? null,
                'status' => $booking->status ?? null,
            ],
            'idempotency_key' => "booking_created:{$bookingId}",
        ]);
    }

    /**
     * @return array{processed:int,created:int,skipped:int,missing_conversation:int,mappings:array<string,int>}
     */
    public function backfillLegacyHandoffEvents(?string $from = null, ?string $to = null, bool $dryRun = false): array
    {
        $summary = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'missing_conversation' => 0,
            'mappings' => [],
        ];

        if (!Schema::hasTable('whatsapp_handoff_events') || !Schema::hasTable('whatsapp_handoffs')) {
            return $summary;
        }

        $query = DB::table('whatsapp_handoff_events as e')
            ->leftJoin('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
            ->leftJoin('whatsapp_conversations as c', 'c.id', '=', 'h.conversation_id')
            ->select([
                'e.id',
                'e.handoff_id',
                'e.event_type',
                'e.actor_user_id',
                'e.notes',
                'e.created_at',
                'h.conversation_id',
                'h.wa_number as handoff_wa_number',
                'h.topic',
                'h.priority',
                'c.wa_number as conversation_wa_number',
                'c.patient_hc_number',
            ])
            ->orderBy('e.id');

        if ($from !== null && trim($from) !== '') {
            $query->where('e.created_at', '>=', trim($from) . ' 00:00:00');
        }
        if ($to !== null && trim($to) !== '') {
            $query->where('e.created_at', '<=', trim($to) . ' 23:59:59');
        }

        $query->chunkById(500, function ($rows) use (&$summary, $dryRun): void {
            foreach ($rows as $row) {
                $summary['processed']++;
                $legacyType = (string) ($row->event_type ?? '');
                $canonicalType = self::LEGACY_HANDOFF_EVENT_MAP[$legacyType] ?? null;
                if ($canonicalType === null) {
                    $summary['skipped']++;
                    continue;
                }

                $summary['mappings'][$legacyType . ' -> ' . $canonicalType] =
                    ($summary['mappings'][$legacyType . ' -> ' . $canonicalType] ?? 0) + 1;

                $conversationId = (int) ($row->conversation_id ?? 0);
                if ($conversationId <= 0) {
                    $summary['missing_conversation']++;
                    continue;
                }

                $idempotencyKey = 'legacy_handoff_event:' . (int) $row->id;
                if (Schema::hasTable('whatsapp_operational_events')
                    && WhatsappOperationalEvent::query()->where('idempotency_key', $idempotencyKey)->exists()
                ) {
                    $summary['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $summary['created']++;
                    continue;
                }

                $event = $this->record([
                    'conversation_id' => $conversationId,
                    'handoff_id' => (int) $row->handoff_id,
                    'event_type' => $canonicalType,
                    'event_at' => $row->created_at ?? now(),
                    'actor_type' => ((int) ($row->actor_user_id ?? 0)) > 0 ? 'agent' : 'system',
                    'actor_user_id' => $row->actor_user_id,
                    'producer' => 'legacy_handoff_backfill',
                    'topic' => $row->topic ?? null,
                    'priority_score' => $this->priorityToScore((string) ($row->priority ?? '')),
                    'wa_number' => $row->conversation_wa_number ?? $row->handoff_wa_number ?? null,
                    'patient_hc_number' => $row->patient_hc_number ?? null,
                    'reason' => $legacyType === 'expired' ? 'legacy_expired' : 'legacy_' . $legacyType,
                    'payload' => [
                        'legacy_event_id' => (int) $row->id,
                        'legacy_event_type' => $legacyType,
                        'notes' => $row->notes ?? null,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);

                if ($event instanceof WhatsappOperationalEvent) {
                    $summary['created']++;
                }
            }
        }, 'e.id', 'id');

        return $summary;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function makeIdempotencyKey(array $attributes): string
    {
        return sha1(implode('|', [
            (string) ($attributes['conversation_id'] ?? ''),
            (string) ($attributes['handoff_id'] ?? ''),
            (string) ($attributes['booking_id'] ?? ''),
            (string) ($attributes['reminder_id'] ?? ''),
            (string) ($attributes['message_id'] ?? ''),
            (string) ($attributes['event_type'] ?? ''),
            (string) ($attributes['event_at'] ?? ''),
            (string) ($attributes['producer'] ?? ''),
        ]));
    }

    private function normalizeDateTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : now()->toDateTimeString();
    }

    private function normalizeActorType(string $actorType): string
    {
        $actorType = strtolower(trim($actorType));

        return in_array($actorType, ['patient', 'agent', 'supervisor', 'bot', 'system', 'integration'], true)
            ? $actorType
            : 'system';
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $string = trim((string) $value);
        return $string !== '' ? mb_substr($string, 0, $limit) : null;
    }

    private function priorityToScore(string $priority): ?float
    {
        return match (strtolower(trim($priority))) {
            'critical', 'urgent' => 100.0,
            'high' => 75.0,
            'normal' => 50.0,
            'low' => 25.0,
            default => null,
        };
    }
}
