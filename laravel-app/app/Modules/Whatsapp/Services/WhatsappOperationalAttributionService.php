<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappOperationalAttributionService
{
    private const EVENT_TYPE_MAP = [
        'requested' => 'handoff_created',
        'handoff_created' => 'handoff_created',
        'requeued' => 'handoff_requeued',
        'handoff_requeued' => 'handoff_requeued',
        'auto_assigned' => 'auto_assigned',
        'assigned' => 'agent_taken',
        'agent_taken' => 'agent_taken',
        'first_response_after_assignment' => 'first_response_after_assignment',
        'abandonment_escalated' => 'abandonment_escalated',
        'template_rescue' => 'template_rescue_sent',
        'template_rescue_sent' => 'template_rescue_sent',
        'reminder_rescue' => 'reminder_rescue_sent',
        'reminder_rescue_sent' => 'reminder_rescue_sent',
        'supervisor_alerted' => 'supervisor_alerted',
    ];

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int}
     */
    public function refresh(CarbonInterface $from, CarbonInterface $to): array
    {
        if (!$this->hasRequiredTables()) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $fromAt = CarbonImmutable::parse($from->format('Y-m-d H:i:s'));
        $toAt = CarbonImmutable::parse($to->format('Y-m-d H:i:s'));
        $summary = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($this->bookingRows($fromAt, $toAt) as $booking) {
            $summary['processed']++;
            $match = $this->bestAttributionForBooking($booking);
            if ($match === null) {
                $summary['skipped']++;
                continue;
            }

            $exists = DB::table('whatsapp_operational_booking_attributions')
                ->where('booking_id', (int) $booking->booking_id)
                ->exists();

            DB::table('whatsapp_operational_booking_attributions')->updateOrInsert(
                ['booking_id' => (int) $booking->booking_id],
                [
                    'booking_conversation_id' => $booking->booking_conversation_id !== null
                        ? (int) $booking->booking_conversation_id
                        : null,
                    'attributed_conversation_id' => $match->conversation_id !== null
                        ? (int) $match->conversation_id
                        : null,
                    'handoff_id' => $match->handoff_id !== null ? (int) $match->handoff_id : null,
                    'event_id' => $match->event_id !== null ? (int) $match->event_id : null,
                    'event_type' => $this->canonicalEventType((string) $match->event_type),
                    'attribution_method' => (string) $match->attribution_method,
                    'confidence' => (string) $match->confidence,
                    'event_at' => (string) $match->event_at,
                    'booking_at' => (string) $booking->booking_at,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $exists ? $summary['updated']++ : $summary['created']++;
        }

        return $summary;
    }

    /**
     * @return array<int,object>
     */
    private function bookingRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('whatsapp_sigcenter_bookings as b')
            ->leftJoin('whatsapp_conversations as c', 'c.id', '=', 'b.conversation_id')
            ->select([
                'b.id as booking_id',
                'b.conversation_id as booking_conversation_id',
                DB::raw('COALESCE(b.wa_number, c.wa_number) AS booking_wa_number'),
                DB::raw('COALESCE(b.patient_hc_number, c.patient_hc_number) AS booking_patient_hc_number'),
                DB::raw('COALESCE(b.booked_at, b.created_at) AS booking_at'),
            ])
            ->whereIn('b.status', ['created', 'confirmed'])
            ->whereRaw('COALESCE(b.booked_at, b.created_at) >= ?', [$from->format('Y-m-d H:i:s')])
            ->whereRaw('COALESCE(b.booked_at, b.created_at) < ?', [$to->format('Y-m-d H:i:s')])
            ->orderBy('b.id')
            ->get()
            ->all();
    }

    private function bestAttributionForBooking(object $booking): ?object
    {
        $bookingAt = CarbonImmutable::parse((string) $booking->booking_at);
        $conversationId = $booking->booking_conversation_id !== null ? (int) $booking->booking_conversation_id : null;
        $waNumber = trim((string) ($booking->booking_wa_number ?? ''));
        $patientHcNumber = trim((string) ($booking->booking_patient_hc_number ?? ''));

        if ($conversationId !== null) {
            $match = $this->eventQuery()
                ->where('h.conversation_id', $conversationId)
                ->where('e.created_at', '>=', $bookingAt->subDays(7)->format('Y-m-d H:i:s'))
                ->where('e.created_at', '<', $bookingAt->format('Y-m-d H:i:s'))
                ->orderByDesc('e.created_at')
                ->first();

            if ($match !== null) {
                $match->attribution_method = 'same_conversation_7d';
                $match->confidence = 'high';

                return $match;
            }
        }

        if ($waNumber !== '') {
            $match = $this->eventQuery()
                ->where('c.wa_number', $waNumber)
                ->where('e.created_at', '>=', $bookingAt->subHours(72)->format('Y-m-d H:i:s'))
                ->where('e.created_at', '<', $bookingAt->format('Y-m-d H:i:s'))
                ->orderByDesc('e.created_at')
                ->first();

            if ($match !== null) {
                $match->attribution_method = 'same_wa_number_72h';
                $match->confidence = 'medium';

                return $match;
            }
        }

        if ($patientHcNumber !== '') {
            $match = $this->eventQuery()
                ->where('c.patient_hc_number', $patientHcNumber)
                ->where('e.created_at', '>=', $bookingAt->subHours(72)->format('Y-m-d H:i:s'))
                ->where('e.created_at', '<', $bookingAt->format('Y-m-d H:i:s'))
                ->orderByDesc('e.created_at')
                ->first();

            if ($match !== null) {
                $match->attribution_method = 'same_patient_hc_number_72h';
                $match->confidence = 'medium';

                return $match;
            }
        }

        return null;
    }

    private function eventQuery(): Builder
    {
        return DB::table('whatsapp_handoff_events as e')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
            ->leftJoin('whatsapp_conversations as c', 'c.id', '=', 'h.conversation_id')
            ->select([
                'e.id as event_id',
                'e.handoff_id',
                'e.event_type',
                'e.created_at as event_at',
                'h.conversation_id',
            ])
            ->whereIn('e.event_type', array_keys(self::EVENT_TYPE_MAP));
    }

    private function canonicalEventType(string $rawEventType): string
    {
        return self::EVENT_TYPE_MAP[$rawEventType] ?? $rawEventType;
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('whatsapp_operational_booking_attributions')
            && Schema::hasTable('whatsapp_sigcenter_bookings')
            && Schema::hasTable('whatsapp_handoff_events')
            && Schema::hasTable('whatsapp_handoffs')
            && Schema::hasTable('whatsapp_conversations');
    }
}
