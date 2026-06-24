<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappRescueMetricsService
{
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
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $to->format('Y-m-d H:i:s');

        return [
            'period' => [
                'from' => $fromSql,
                'to' => $toSql,
            ],
            'handoffs' => $this->handoffMetrics($fromSql, $toSql),
            'reminders' => $this->reminderMetrics($fromSql, $toSql),
            'hot_opportunities' => $this->hotOpportunityMetrics($fromSql, $toSql),
        ];
    }

    /**
     * @return array<string,int>
     */
    private function handoffMetrics(string $fromSql, string $toSql): array
    {
        $empty = [
            'requeued_to_auto_assigned' => 0,
            'auto_assigned_to_first_response' => 0,
            'auto_assigned_to_booking' => 0,
            'abandonment_escalated_to_assigned' => 0,
            'abandonment_escalated_to_booking' => 0,
        ];

        if (!$this->hasHandoffTables()) {
            return $empty;
        }

        $requeued = $this->eventsByType(['requeued', 'expired'], $fromSql, $toSql);
        $autoAssigned = $this->eventsByType(['auto_assigned'], $fromSql, $toSql);
        $abandonment = $this->eventsByType(['abandonment_escalated'], $fromSql, $toSql);

        return [
            'requeued_to_auto_assigned' => $this->countFollowupEvents($requeued, ['auto_assigned'], $toSql),
            'auto_assigned_to_first_response' => $this->countEventsWithFirstResponse($autoAssigned, $toSql),
            'auto_assigned_to_booking' => $this->countEventsWithBooking($autoAssigned, $toSql),
            'abandonment_escalated_to_assigned' => $this->countFollowupEvents($abandonment, ['auto_assigned', 'assigned'], $toSql),
            'abandonment_escalated_to_booking' => $this->countEventsWithBooking($abandonment, $toSql),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function reminderMetrics(string $fromSql, string $toSql): array
    {
        $empty = [
            'sent_to_confirmation' => 0,
            'failed' => 0,
            'failure_reasons' => [],
        ];

        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return $empty;
        }

        $sentToConfirmation = DB::table('whatsapp_appointment_reminders')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $fromSql)
            ->where('sent_at', '<', $toSql)
            ->where('status', 'responded')
            ->where('response_value', 'confirmar')
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
            'sent_to_confirmation' => (int) $sentToConfirmation,
            'failed' => $failedRows->count(),
            'failure_reasons' => $reasons,
        ];
    }

    /**
     * @return array<string,int>
     */
    private function hotOpportunityMetrics(string $fromSql, string $toSql): array
    {
        $empty = [
            'total' => 0,
            'booked' => 0,
        ];

        if (!Schema::hasTable('whatsapp_handoffs') || !Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return $empty;
        }

        $total = DB::table('whatsapp_handoffs')
            ->whereIn('topic', self::HOT_TOPICS)
            ->whereRaw('COALESCE(queued_at, created_at) >= ?', [$fromSql])
            ->whereRaw('COALESCE(queued_at, created_at) < ?', [$toSql])
            ->count();

        $booked = DB::table('whatsapp_handoffs as h')
            ->join('whatsapp_sigcenter_bookings as b', 'b.conversation_id', '=', 'h.conversation_id')
            ->whereIn('h.topic', self::HOT_TOPICS)
            ->whereRaw('COALESCE(h.queued_at, h.created_at) >= ?', [$fromSql])
            ->whereRaw('COALESCE(h.queued_at, h.created_at) < ?', [$toSql])
            ->where('b.status', 'created')
            ->whereRaw('COALESCE(b.booked_at, b.created_at) > COALESCE(h.queued_at, h.created_at)')
            ->whereRaw('COALESCE(b.booked_at, b.created_at) < ?', [$toSql])
            ->distinct('h.id')
            ->count('h.id');

        return [
            'total' => (int) $total,
            'booked' => (int) $booked,
        ];
    }

    private function hasHandoffTables(): bool
    {
        return Schema::hasTable('whatsapp_handoffs') && Schema::hasTable('whatsapp_handoff_events');
    }

    /**
     * @param array<int,string> $types
     * @return array<int,object>
     */
    private function eventsByType(array $types, string $fromSql, string $toSql): array
    {
        return DB::table('whatsapp_handoff_events as e')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
            ->select([
                'e.id as event_id',
                'e.handoff_id',
                'e.event_type',
                'e.created_at',
                'h.conversation_id',
            ])
            ->whereIn('e.event_type', $types)
            ->where('e.created_at', '>=', $fromSql)
            ->where('e.created_at', '<', $toSql)
            ->get()
            ->all();
    }

    /**
     * @param array<int,object> $events
     * @param array<int,string> $followupTypes
     */
    private function countFollowupEvents(array $events, array $followupTypes, string $toSql): int
    {
        $ids = $this->eventIds($events);
        if ($ids === []) {
            return 0;
        }

        return (int) DB::table('whatsapp_handoff_events as e')
            ->join('whatsapp_handoff_events as f', 'f.handoff_id', '=', 'e.handoff_id')
            ->whereIn('e.id', $ids)
            ->whereIn('f.event_type', $followupTypes)
            ->whereColumn('f.created_at', '>', 'e.created_at')
            ->where('f.created_at', '<', $toSql)
            ->distinct('e.id')
            ->count('e.id');
    }

    /**
     * @param array<int,object> $events
     */
    private function countEventsWithFirstResponse(array $events, string $toSql): int
    {
        if (!Schema::hasTable('whatsapp_messages') && !Schema::hasTable('whatsapp_inbox_messages')) {
            return 0;
        }

        $ids = $this->eventIds($events);
        if ($ids === []) {
            return 0;
        }

        if (Schema::hasTable('whatsapp_messages')) {
            return (int) DB::table('whatsapp_handoff_events as e')
                ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
                ->join('whatsapp_messages as m', 'm.conversation_id', '=', 'h.conversation_id')
                ->whereIn('e.id', $ids)
                ->where('m.direction', 'outbound')
                ->whereRaw('COALESCE(m.message_timestamp, m.created_at) > e.created_at')
                ->whereRaw('COALESCE(m.message_timestamp, m.created_at) < ?', [$toSql])
                ->distinct('e.id')
                ->count('e.id');
        }

        return (int) DB::table('whatsapp_handoff_events as e')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
            ->join('whatsapp_conversations as c', 'c.id', '=', 'h.conversation_id')
            ->join('whatsapp_inbox_messages as m', 'm.wa_number', '=', 'c.wa_number')
            ->whereIn('e.id', $ids)
            ->where('m.direction', 'outbound')
            ->whereColumn('m.created_at', '>', 'e.created_at')
            ->where('m.created_at', '<', $toSql)
            ->distinct('e.id')
            ->count('e.id');
    }

    /**
     * @param array<int,object> $events
     */
    private function countEventsWithBooking(array $events, string $toSql): int
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return 0;
        }

        $ids = $this->eventIds($events);
        if ($ids === []) {
            return 0;
        }

        return (int) DB::table('whatsapp_handoff_events as e')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
            ->join('whatsapp_sigcenter_bookings as b', 'b.conversation_id', '=', 'h.conversation_id')
            ->whereIn('e.id', $ids)
            ->where('b.status', 'created')
            ->whereRaw('COALESCE(b.booked_at, b.created_at) > e.created_at')
            ->whereRaw('COALESCE(b.booked_at, b.created_at) < ?', [$toSql])
            ->distinct('e.id')
            ->count('e.id');
    }

    /**
     * @param array<int,object> $events
     * @return array<int,int>
     */
    private function eventIds(array $events): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (object $event): int => (int) ($event->event_id ?? 0),
            $events
        ))));
    }
}
