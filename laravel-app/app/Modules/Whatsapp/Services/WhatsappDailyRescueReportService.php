<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappDailyRescueReportService
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

    /**
     * @return array<string,mixed>
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?CarbonInterface $asOf = null): array
    {
        $fromAt = CarbonImmutable::parse($from->format('Y-m-d H:i:s'));
        $toAt = CarbonImmutable::parse($to->format('Y-m-d H:i:s'));
        $asOfAt = $asOf !== null
            ? CarbonImmutable::parse($asOf->format('Y-m-d H:i:s'))
            : $toAt;

        $buckets = $this->bucketMetrics($fromAt, $toAt, $asOfAt);
        $operations = $this->operationMetrics($fromAt, $toAt);
        $reminders = $this->reminderMetrics($fromAt, $toAt);
        $rates = $this->rates($buckets, $operations);

        return [
            'period' => [
                'from' => $fromAt->format('Y-m-d H:i:s'),
                'to' => $toAt->format('Y-m-d H:i:s'),
                'as_of' => $asOfAt->format('Y-m-d H:i:s'),
            ],
            'buckets' => $buckets,
            'operations' => $operations,
            'reminders' => $reminders,
            'rates' => $rates,
            'dashboard_ready' => [
                'bucket_order' => self::BUCKETS,
                'executive_buckets' => ['hot_open', 'hot_needs_template', 'rescue'],
                'debt_buckets' => ['backlog', 'lost'],
            ],
        ];
    }

    /**
     * @return array<string,array<string,int|float>>
     */
    private function bucketMetrics(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $asOf): array
    {
        $metrics = $this->emptyBuckets();
        foreach ($this->opportunityRows() as $row) {
            $bucket = $this->classifyBucket($row, $asOf);
            if ($bucket === null) {
                continue;
            }

            $hasBooking = (int) ($row->has_booking ?? 0) === 1;
            $bookedAt = $this->parseNullableAt($row->booking_at ?? null);
            $bookedInPeriod = $hasBooking
                && $bookedAt !== null
                && $bookedAt->greaterThanOrEqualTo($from)
                && $bookedAt->lessThan($to);

            if (!$hasBooking) {
                $metrics[$bucket]['open']++;
            }
            if ($bookedInPeriod) {
                $metrics[$bucket]['booked']++;
            }

            $metrics[$bucket]['total'] = $metrics[$bucket]['open'] + $metrics[$bucket]['booked'];
        }

        foreach (self::BUCKETS as $bucket) {
            $total = (int) $metrics[$bucket]['total'];
            $booked = (int) $metrics[$bucket]['booked'];
            $metrics[$bucket]['conversion_rate'] = $total > 0 ? round(($booked / $total) * 100, 1) : 0.0;
        }

        return $metrics;
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
            ->whereIn('status', ['queued', 'assigned'])
            ->groupBy('conversation_id');

        $query = DB::table('whatsapp_conversations as c')
            ->joinSub($latestHandoffSubquery, 'latest_h', 'latest_h.conversation_id', '=', 'c.id')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'latest_h.id')
            ->select([
                'c.id as conversation_id',
                'c.needs_human',
                'c.assigned_user_id',
                'c.created_at as conversation_created_at',
                'c.last_message_at',
                'c.handoff_requested_at',
                'h.id as handoff_id',
                'h.status as handoff_status',
                'h.topic',
                'h.queued_at',
                'h.created_at as handoff_created_at',
            ])
            ->where('c.needs_human', true)
            ->whereIn('h.topic', self::HOT_TOPICS);

        if (Schema::hasColumn('whatsapp_conversations', 'closed_at')) {
            $query->whereNull('c.closed_at');
        }

        if (Schema::hasTable('whatsapp_messages')) {
            $latestInbound = DB::table('whatsapp_messages')
                ->selectRaw('conversation_id, MAX(COALESCE(message_timestamp, created_at)) AS latest_inbound_at')
                ->where('direction', 'inbound')
                ->groupBy('conversation_id');
            $query->leftJoinSub($latestInbound, 'latest_inbound', 'latest_inbound.conversation_id', '=', 'c.id')
                ->addSelect('latest_inbound.latest_inbound_at');
        } else {
            $query->addSelect(DB::raw('NULL AS latest_inbound_at'));
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

        return $query->get()->all();
    }

    private function classifyBucket(object $row, CarbonImmutable $asOf): ?string
    {
        $ageAt = $this->parseNullableAt($row->queued_at ?? null)
            ?? $this->parseNullableAt($row->handoff_requested_at ?? null)
            ?? $this->parseNullableAt($row->last_message_at ?? null)
            ?? $this->parseNullableAt($row->conversation_created_at ?? null);

        if ($ageAt === null) {
            return null;
        }

        $ageMinutes = $ageAt->diffInMinutes($asOf, false);
        if ($ageMinutes < 0) {
            $ageMinutes = 0;
        }

        if ($ageMinutes <= 24 * 60) {
            $latestInbound = $this->parseNullableAt($row->latest_inbound_at ?? null);
            $windowOpen = $latestInbound !== null
                && $latestInbound->greaterThanOrEqualTo($asOf->subHours(24));

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
     * @return array<string,int>
     */
    private function operationMetrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $metrics = [
            'auto_assigned' => 0,
            'agent_taken' => 0,
            'requeued' => 0,
            'abandonment_escalated' => 0,
            'bookings_created' => 0,
        ];

        if (Schema::hasTable('whatsapp_handoff_events')) {
            $events = DB::table('whatsapp_handoff_events')
                ->selectRaw('event_type, COUNT(*) AS total')
                ->where('created_at', '>=', $from->format('Y-m-d H:i:s'))
                ->where('created_at', '<', $to->format('Y-m-d H:i:s'))
                ->whereIn('event_type', ['auto_assigned', 'assigned', 'requeued', 'expired', 'abandonment_escalated'])
                ->groupBy('event_type')
                ->pluck('total', 'event_type');

            $metrics['auto_assigned'] = (int) ($events['auto_assigned'] ?? 0);
            $metrics['agent_taken'] = (int) ($events['assigned'] ?? 0);
            $metrics['requeued'] = (int) ($events['requeued'] ?? 0) + (int) ($events['expired'] ?? 0);
            $metrics['abandonment_escalated'] = (int) ($events['abandonment_escalated'] ?? 0);
        }

        if (Schema::hasTable('whatsapp_sigcenter_bookings')) {
            $metrics['bookings_created'] = (int) DB::table('whatsapp_sigcenter_bookings')
                ->whereIn('status', ['created', 'confirmed'])
                ->whereRaw('COALESCE(booked_at, created_at) >= ?', [$from->format('Y-m-d H:i:s')])
                ->whereRaw('COALESCE(booked_at, created_at) < ?', [$to->format('Y-m-d H:i:s')])
                ->count();
        }

        return $metrics;
    }

    /**
     * @return array<string,mixed>
     */
    private function reminderMetrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $empty = [
            'sent' => 0,
            'confirmed' => 0,
            'failed' => 0,
            'failure_reasons' => [],
        ];

        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return $empty;
        }

        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $to->format('Y-m-d H:i:s');

        $sent = DB::table('whatsapp_appointment_reminders')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $fromSql)
            ->where('sent_at', '<', $toSql)
            ->count();

        $confirmed = DB::table('whatsapp_appointment_reminders')
            ->where('status', 'responded')
            ->where('response_value', 'confirmar')
            ->whereNotNull('responded_at')
            ->where('responded_at', '>=', $fromSql)
            ->where('responded_at', '<', $toSql)
            ->count();

        $failedRows = DB::table('whatsapp_appointment_reminders')
            ->select(['notes'])
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
            ->get();

        $reasons = [];
        foreach ($failedRows as $row) {
            $reason = trim((string) ($row->notes ?? 'Sin detalle'));
            $reason = $reason !== '' ? mb_substr($reason, 0, 160) : 'Sin detalle';
            $reasons[$reason] = (int) ($reasons[$reason] ?? 0) + 1;
        }
        arsort($reasons);

        return [
            'sent' => (int) $sent,
            'confirmed' => (int) $confirmed,
            'failed' => $failedRows->count(),
            'failure_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,array<string,int|float>> $buckets
     * @param array<string,int> $operations
     * @return array<string,float>
     */
    private function rates(array $buckets, array $operations): array
    {
        $executiveTotal = (int) ($buckets['hot_open']['total'] ?? 0)
            + (int) ($buckets['hot_needs_template']['total'] ?? 0)
            + (int) ($buckets['rescue']['total'] ?? 0);
        $debtTotal = (int) ($buckets['backlog']['open'] ?? 0)
            + (int) ($buckets['lost']['open'] ?? 0);
        $interventions = (int) ($operations['auto_assigned'] ?? 0) + (int) ($operations['agent_taken'] ?? 0);
        $bookings = (int) ($operations['bookings_created'] ?? 0);

        return [
            'assignment_rate' => $executiveTotal > 0 ? round(($interventions / $executiveTotal) * 100, 1) : 0.0,
            'rescue_rate' => $interventions > 0 ? round(($bookings / $interventions) * 100, 1) : 0.0,
            'abandonment_rate' => ($executiveTotal + $debtTotal) > 0 ? round((((int) ($buckets['lost']['open'] ?? 0)) / ($executiveTotal + $debtTotal)) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array<string,array{open:int,booked:int,total:int,conversion_rate:float}>
     */
    private function emptyBuckets(): array
    {
        $buckets = [];
        foreach (self::BUCKETS as $bucket) {
            $buckets[$bucket] = [
                'open' => 0,
                'booked' => 0,
                'total' => 0,
                'conversion_rate' => 0.0,
            ];
        }

        return $buckets;
    }

    private function parseNullableAt(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
