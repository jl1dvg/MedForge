<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappOperationalBaselineService
{
    private const BUCKETS = [
        'hot_open',
        'hot_needs_template',
        'rescue',
        'backlog',
        'lost',
    ];

    private const HOT_TOPICS = [
        'captacion_agendar',
        'agenda_sin_disponibilidad',
        'faq_escalada',
        'operacion_cita_vigente',
        'operacion_reagenda',
    ];

    private const OPERATIONAL_BOOKING_EVENTS = [
        'handoff_created',
        'handoff_requeued',
        'auto_assigned',
        'agent_taken',
        'first_response_after_assignment',
        'abandonment_escalated',
        'template_rescue_sent',
        'reminder_rescue_sent',
        'supervisor_alerted',
    ];

    /**
     * @return array<string,mixed>
     */
    public function baseline(CarbonInterface $snapshotDate, ?CarbonInterface $asOf = null, bool $persist = false): array
    {
        $date = CarbonImmutable::parse($snapshotDate->format('Y-m-d'))->startOfDay();
        $asOfAt = $asOf !== null
            ? CarbonImmutable::parse($asOf->format('Y-m-d H:i:s'))
            : CarbonImmutable::now();
        $periodTo = $date->addDay();

        $rows = $this->opportunityRows();
        $buckets = $this->emptyBuckets();
        foreach ($rows as $row) {
            $bucket = $this->classifyBucket($row, $asOfAt);
            if ($bucket === null) {
                continue;
            }

            $this->accumulateBucket($buckets[$bucket], $row, $asOfAt);
        }

        foreach (self::BUCKETS as $bucket) {
            $this->finalizeBucket($buckets[$bucket]);
        }

        $bookingsAfterIntervention = $this->bookingsAfterOperationalIntervention($date, $periodTo);
        $reminders = $this->reminderMetrics($date, $periodTo);

        $payload = [
            'snapshot_date' => $date->toDateString(),
            'generated_at' => $asOfAt->format('Y-m-d H:i:s'),
            'buckets' => $buckets,
            'bookings_after_operational_intervention' => $bookingsAfterIntervention,
            'reminders' => $reminders,
            'dashboard_ready' => [
                'bucket_order' => self::BUCKETS,
                'executive_buckets' => ['hot_open', 'hot_needs_template', 'rescue'],
                'debt_buckets' => ['backlog', 'lost'],
            ],
        ];

        if ($persist) {
            $this->persistSnapshot($payload, $asOfAt);
        }

        return $payload;
    }

    /**
     * @return array<int,object>
     */
    private function opportunityRows(): array
    {
        if (!Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

        $latestHandoffSubquery = DB::table('whatsapp_handoffs')
            ->selectRaw('conversation_id, MAX(id) AS id')
            ->whereIn('status', ['queued', 'assigned', 'expired'])
            ->groupBy('conversation_id');

        $query = DB::table('whatsapp_conversations as c')
            ->joinSub($latestHandoffSubquery, 'latest_h', 'latest_h.conversation_id', '=', 'c.id')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'latest_h.id')
            ->select([
                'c.id as conversation_id',
                'c.needs_human',
                'c.assigned_user_id',
                'c.assigned_at as conversation_assigned_at',
                'c.created_at as conversation_created_at',
                'c.last_message_at',
                'c.handoff_requested_at',
                'h.id as handoff_id',
                'h.status as handoff_status',
                'h.topic',
                'h.assigned_agent_id',
                'h.assigned_at as handoff_assigned_at',
                'h.queued_at',
                'h.created_at as handoff_created_at',
            ])
            ->where('c.needs_human', true)
            ->whereIn('h.topic', self::HOT_TOPICS);

        if (Schema::hasColumn('whatsapp_conversations', 'closed_at')) {
            $query->whereNull('c.closed_at');
        }

        if (Schema::hasTable('whatsapp_conversation_attributions')) {
            $query->leftJoin('whatsapp_conversation_attributions as a', 'a.conversation_id', '=', 'c.id')
                ->addSelect([
                    'a.source_category',
                    'a.initial_intent',
                    'a.patient_segment',
                ]);
        } else {
            $query->addSelect([
                DB::raw('NULL AS source_category'),
                DB::raw('NULL AS initial_intent'),
                DB::raw('NULL AS patient_segment'),
            ]);
        }

        if (Schema::hasTable('whatsapp_messages')) {
            $latestInbound = DB::table('whatsapp_messages')
                ->selectRaw('conversation_id, MAX(COALESCE(message_timestamp, created_at)) AS latest_inbound_at')
                ->where('direction', 'inbound')
                ->groupBy('conversation_id');
            $firstOutbound = DB::table('whatsapp_messages')
                ->selectRaw('conversation_id, MIN(COALESCE(message_timestamp, created_at)) AS first_outbound_at')
                ->where('direction', 'outbound')
                ->groupBy('conversation_id');

            $query->leftJoinSub($latestInbound, 'latest_inbound', 'latest_inbound.conversation_id', '=', 'c.id')
                ->leftJoinSub($firstOutbound, 'first_outbound', 'first_outbound.conversation_id', '=', 'c.id')
                ->addSelect([
                    'latest_inbound.latest_inbound_at',
                    'first_outbound.first_outbound_at',
                ]);
        } else {
            $query->addSelect([
                DB::raw('NULL AS latest_inbound_at'),
                DB::raw('NULL AS first_outbound_at'),
            ]);
        }

        if (Schema::hasTable('whatsapp_sigcenter_bookings')) {
            $bookings = DB::table('whatsapp_sigcenter_bookings')
                ->selectRaw('conversation_id, 1 AS has_booking, MAX(COALESCE(booked_at, created_at)) AS booking_at')
                ->whereIn('status', ['created', 'confirmed'])
                ->groupBy('conversation_id');
            $query->leftJoinSub($bookings, 'bookings', 'bookings.conversation_id', '=', 'c.id')
                ->addSelect([
                    DB::raw('COALESCE(bookings.has_booking, 0) AS has_booking'),
                    'bookings.booking_at',
                ]);
        } else {
            $query->addSelect([
                DB::raw('0 AS has_booking'),
                DB::raw('NULL AS booking_at'),
            ]);
        }

        if (Schema::hasTable('whatsapp_handoff_events')) {
            $eventSummary = DB::table('whatsapp_handoff_events')
                ->selectRaw(
                    'handoff_id,
                    MAX(CASE WHEN event_type = "auto_assigned" THEN 1 ELSE 0 END) AS has_auto_assigned,
                    MAX(CASE WHEN event_type IN ("requeued", "handoff_requeued") THEN 1 ELSE 0 END) AS has_requeue,
                    MAX(CASE WHEN event_type IN ("auto_assigned", "handoff_requeued", "requeued", "abandonment_escalated", "supervisor_alerted", "reminder_rescue", "reminder_rescue_sent", "template_rescue", "template_rescue_sent") THEN 1 ELSE 0 END) AS has_operational_intervention,
                    MIN(CASE WHEN event_type = "auto_assigned" THEN created_at ELSE NULL END) AS first_auto_assigned_at,
                    MIN(CASE WHEN event_type IN ("requeued", "handoff_requeued") THEN created_at ELSE NULL END) AS first_requeue_at,
                    MIN(CASE WHEN event_type IN ("auto_assigned", "handoff_requeued", "requeued", "abandonment_escalated", "supervisor_alerted", "reminder_rescue", "reminder_rescue_sent", "template_rescue", "template_rescue_sent") THEN created_at ELSE NULL END) AS first_operational_event_at'
                )
                ->groupBy('handoff_id');

            $query->leftJoinSub($eventSummary, 'event_summary', 'event_summary.handoff_id', '=', 'h.id')
                ->addSelect([
                    DB::raw('COALESCE(event_summary.has_auto_assigned, 0) AS has_auto_assigned'),
                    DB::raw('COALESCE(event_summary.has_requeue, 0) AS has_requeue'),
                    DB::raw('COALESCE(event_summary.has_operational_intervention, 0) AS has_operational_intervention'),
                    'event_summary.first_auto_assigned_at',
                    'event_summary.first_requeue_at',
                    'event_summary.first_operational_event_at',
                ]);
        } else {
            $query->addSelect([
                DB::raw('0 AS has_auto_assigned'),
                DB::raw('0 AS has_requeue'),
                DB::raw('0 AS has_operational_intervention'),
                DB::raw('NULL AS first_auto_assigned_at'),
                DB::raw('NULL AS first_requeue_at'),
                DB::raw('NULL AS first_operational_event_at'),
            ]);
        }

        return $query->get()->all();
    }

    private function classifyBucket(object $row, CarbonImmutable $asOf): ?string
    {
        $ageAt = $this->rowAgeAt($row);
        if ($ageAt === null) {
            return null;
        }

        $ageMinutes = max(0, $ageAt->diffInMinutes($asOf, false));
        if ($ageMinutes <= 24 * 60) {
            $latestInbound = $this->parseNullableAt($row->latest_inbound_at ?? null);
            $windowOpen = $latestInbound !== null && $latestInbound->greaterThanOrEqualTo($asOf->subHours(24));

            return $windowOpen ? 'hot_open' : 'hot_needs_template';
        }

        if ($ageMinutes <= 7 * 24 * 60) {
            return 'rescue';
        }

        if ($ageMinutes <= 30 * 24 * 60) {
            return 'backlog';
        }

        return 'lost';
    }

    /**
     * @param array<string,mixed> $bucket
     */
    private function accumulateBucket(array &$bucket, object $row, CarbonImmutable $asOf): void
    {
        $ageAt = $this->rowAgeAt($row);
        $queueAt = $this->parseNullableAt($row->queued_at ?? null)
            ?? $this->parseNullableAt($row->handoff_created_at ?? null)
            ?? $ageAt;
        $assignedAt = $this->parseNullableAt($row->handoff_assigned_at ?? null)
            ?? $this->parseNullableAt($row->conversation_assigned_at ?? null);
        $bookingAt = $this->parseNullableAt($row->booking_at ?? null);
        $firstOutboundAt = $this->parseNullableAt($row->first_outbound_at ?? null);
        $firstOperationalAt = $this->parseNullableAt($row->first_operational_event_at ?? null);
        $firstAutoAssignedAt = $this->parseNullableAt($row->first_auto_assigned_at ?? null);
        $firstRequeueAt = $this->parseNullableAt($row->first_requeue_at ?? null);
        $hasBooking = (int) ($row->has_booking ?? 0) === 1;
        $hasOperational = (int) ($row->has_operational_intervention ?? 0) === 1;
        $hasHumanTouch = ($firstOutboundAt !== null && ($queueAt === null || $firstOutboundAt->greaterThanOrEqualTo($queueAt)))
            || $hasOperational;

        $bucket['total_conversations']++;
        if ((int) ($row->assigned_user_id ?? 0) > 0 || (int) ($row->assigned_agent_id ?? 0) > 0) {
            $bucket['assigned']++;
        } else {
            $bucket['unassigned']++;
        }
        if ((int) ($row->has_auto_assigned ?? 0) === 1) {
            $bucket['autoassigned']++;
        }
        if ($firstOutboundAt !== null && ($queueAt === null || $firstOutboundAt->greaterThanOrEqualTo($queueAt))) {
            $bucket['with_first_response']++;
        }
        if ($hasBooking) {
            $bucket['with_booking']++;
            if ($bookingAt !== null && $firstOperationalAt !== null && $bookingAt->greaterThan($firstOperationalAt)) {
                $bucket['conversion_after_rescue']++;
            }
            if ($bookingAt !== null && $firstAutoAssignedAt !== null && $bookingAt->greaterThan($firstAutoAssignedAt)) {
                $bucket['conversion_after_autoassign']++;
            }
            if ($bookingAt !== null && $firstRequeueAt !== null && $bookingAt->greaterThan($firstRequeueAt)) {
                $bucket['conversion_after_requeue']++;
            }

            if ($hasHumanTouch && $firstOutboundAt !== null) {
                $bucket['hybrid_bookings']++;
            } elseif ($hasHumanTouch) {
                $bucket['human_bookings']++;
            } else {
                $bucket['bot_bookings']++;
            }
        }

        $topic = trim((string) ($row->topic ?? 'sin_topic')) ?: 'sin_topic';
        $origin = trim((string) ($row->source_category ?? 'unknown')) ?: 'unknown';
        $agent = (int) ($row->assigned_user_id ?? $row->assigned_agent_id ?? 0);
        $intervention = $hasOperational ? 'operational_intervention' : 'none';

        $bucket['by_topic'][$topic] = (int) ($bucket['by_topic'][$topic] ?? 0) + 1;
        $bucket['by_origin'][$origin] = (int) ($bucket['by_origin'][$origin] ?? 0) + 1;
        $bucket['by_agent'][$agent > 0 ? (string) $agent : 'unassigned'] = (int) ($bucket['by_agent'][$agent > 0 ? (string) $agent : 'unassigned'] ?? 0) + 1;
        $bucket['by_intervention_type'][$intervention] = (int) ($bucket['by_intervention_type'][$intervention] ?? 0) + 1;

        if ($ageAt !== null) {
            $bucket['_ages'][] = max(0, $ageAt->diffInMinutes($asOf, false));
        }
        if ($queueAt !== null) {
            $queueEnd = $assignedAt ?? $asOf;
            $bucket['_queue_waits'][] = max(0, $queueAt->diffInMinutes($queueEnd, false));
        }
    }

    /**
     * @param array<string,mixed> $bucket
     */
    private function finalizeBucket(array &$bucket): void
    {
        $total = (int) $bucket['total_conversations'];
        $booked = (int) $bucket['with_booking'];
        $autoassigned = (int) $bucket['autoassigned'];
        $requeued = (int) ($bucket['by_intervention_type']['operational_intervention'] ?? 0);

        $ages = $bucket['_ages'];
        $queueWaits = $bucket['_queue_waits'];

        $bucket['conversion_rate'] = $total > 0 ? round(($booked / $total) * 100, 1) : 0.0;
        $bucket['conversion_after_autoassign_rate'] = $autoassigned > 0 ? round(((int) $bucket['conversion_after_autoassign'] / $autoassigned) * 100, 1) : 0.0;
        $bucket['conversion_after_requeue_rate'] = $requeued > 0 ? round(((int) $bucket['conversion_after_requeue'] / $requeued) * 100, 1) : 0.0;
        $bucket['conversion_after_rescue_rate'] = $requeued > 0 ? round(((int) $bucket['conversion_after_rescue'] / $requeued) * 100, 1) : 0.0;
        $bucket['age_average_minutes'] = $this->average($ages);
        $bucket['age_median_minutes'] = $this->median($ages);
        $bucket['age_max_minutes'] = $ages !== [] ? (int) max($ages) : 0;
        $bucket['queue_wait_average_minutes'] = $this->average($queueWaits);
        $bucket['queue_wait_max_minutes'] = $queueWaits !== [] ? (int) max($queueWaits) : 0;

        unset($bucket['_ages'], $bucket['_queue_waits']);
        arsort($bucket['by_topic']);
        arsort($bucket['by_origin']);
        arsort($bucket['by_agent']);
        arsort($bucket['by_intervention_type']);
    }

    /**
     * @return array{total:int,by_event:array<string,int>}
     */
    private function bookingsAfterOperationalIntervention(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = [
            'total' => 0,
            'by_event' => array_fill_keys(self::OPERATIONAL_BOOKING_EVENTS, 0),
        ];

        if (!Schema::hasTable('whatsapp_operational_booking_attributions')) {
            return $result;
        }

        $rows = DB::table('whatsapp_operational_booking_attributions')
            ->select(['booking_id', 'event_type'])
            ->whereIn('event_type', self::OPERATIONAL_BOOKING_EVENTS)
            ->where('booking_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('booking_at', '<', $to->format('Y-m-d H:i:s'))
            ->orderBy('booking_id')
            ->get();

        $bookingIds = [];
        foreach ($rows as $row) {
            $bookingIds[(int) $row->booking_id] = true;
            $eventType = (string) ($row->event_type ?? '');
            if ($eventType !== '' && array_key_exists($eventType, $result['by_event'])) {
                $result['by_event'][$eventType]++;
            }
        }

        $result['total'] = count($bookingIds);

        return $result;
    }

    /**
     * @return array{confirmed:int,failed:int}
     */
    private function reminderMetrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $metrics = ['confirmed' => 0, 'failed' => 0];
        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return $metrics;
        }

        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $to->format('Y-m-d H:i:s');
        $metrics['confirmed'] = (int) DB::table('whatsapp_appointment_reminders')
            ->where('status', 'responded')
            ->where('response_value', 'confirmar')
            ->whereNotNull('responded_at')
            ->where('responded_at', '>=', $fromSql)
            ->where('responded_at', '<', $toSql)
            ->count();
        $metrics['failed'] = (int) DB::table('whatsapp_appointment_reminders')
            ->where('status', 'failed')
            ->where(function ($query) use ($fromSql, $toSql): void {
                $query->where(function ($inner) use ($fromSql, $toSql): void {
                    $inner->whereNotNull('failed_at')
                        ->where('failed_at', '>=', $fromSql)
                        ->where('failed_at', '<', $toSql);
                })->orWhere(function ($inner) use ($fromSql, $toSql): void {
                    $inner->whereNull('failed_at')
                        ->where('created_at', '>=', $fromSql)
                        ->where('created_at', '<', $toSql);
                });
            })
            ->count();

        return $metrics;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function persistSnapshot(array $payload, CarbonImmutable $generatedAt): void
    {
        if (!Schema::hasTable('whatsapp_operational_snapshots')) {
            return;
        }

        $buckets = $payload['buckets'];
        $bookingsAfterIntervention = $payload['bookings_after_operational_intervention'];
        $reminders = $payload['reminders'];

        DB::table('whatsapp_operational_snapshots')->updateOrInsert(
            ['snapshot_date' => (string) $payload['snapshot_date']],
            [
                'payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
                'hot_open_total' => (int) $buckets['hot_open']['total_conversations'],
                'hot_open_unassigned' => (int) $buckets['hot_open']['unassigned'],
                'hot_open_assigned' => (int) $buckets['hot_open']['assigned'],
                'hot_open_booked' => (int) $buckets['hot_open']['with_booking'],
                'hot_needs_template_total' => (int) $buckets['hot_needs_template']['total_conversations'],
                'hot_needs_template_booked' => (int) $buckets['hot_needs_template']['with_booking'],
                'rescue_total' => (int) $buckets['rescue']['total_conversations'],
                'rescue_booked' => (int) $buckets['rescue']['with_booking'],
                'backlog_total' => (int) $buckets['backlog']['total_conversations'],
                'lost_total' => (int) $buckets['lost']['total_conversations'],
                'rescued_bookings' => (int) $bookingsAfterIntervention['total'],
                'autoassigned_bookings' => (int) ($bookingsAfterIntervention['by_event']['auto_assigned'] ?? 0),
                'reminder_confirmations' => (int) $reminders['confirmed'],
                'reminder_failures' => (int) $reminders['failed'],
                'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
                'updated_at' => $generatedAt->format('Y-m-d H:i:s'),
                'created_at' => $generatedAt->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function emptyBuckets(): array
    {
        $buckets = [];
        foreach (self::BUCKETS as $bucket) {
            $buckets[$bucket] = [
                'total_conversations' => 0,
                'unassigned' => 0,
                'assigned' => 0,
                'autoassigned' => 0,
                'with_first_response' => 0,
                'with_booking' => 0,
                'conversion_rate' => 0.0,
                'conversion_after_autoassign' => 0,
                'conversion_after_autoassign_rate' => 0.0,
                'conversion_after_requeue' => 0,
                'conversion_after_requeue_rate' => 0.0,
                'conversion_after_rescue' => 0,
                'conversion_after_rescue_rate' => 0.0,
                'age_average_minutes' => 0.0,
                'age_median_minutes' => 0.0,
                'age_max_minutes' => 0,
                'queue_wait_average_minutes' => 0.0,
                'queue_wait_max_minutes' => 0,
                'human_bookings' => 0,
                'bot_bookings' => 0,
                'hybrid_bookings' => 0,
                'by_topic' => [],
                'by_origin' => [],
                'by_agent' => [],
                'by_intervention_type' => [],
                '_ages' => [],
                '_queue_waits' => [],
            ];
        }

        return $buckets;
    }

    private function rowAgeAt(object $row): ?CarbonImmutable
    {
        return $this->parseNullableAt($row->queued_at ?? null)
            ?? $this->parseNullableAt($row->handoff_requested_at ?? null)
            ?? $this->parseNullableAt($row->last_message_at ?? null)
            ?? $this->parseNullableAt($row->conversation_created_at ?? null);
    }

    /**
     * @param array<int,int|float> $values
     */
    private function average(array $values): float
    {
        return $values !== [] ? round(array_sum($values) / count($values), 1) : 0.0;
    }

    /**
     * @param array<int,int|float> $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return round((float) $values[$middle], 1);
        }

        return round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 1);
    }

    private function parseNullableAt(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
