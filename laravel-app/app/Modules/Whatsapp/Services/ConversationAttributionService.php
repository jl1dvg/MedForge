<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationAttribution;
use App\Models\WhatsappMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConversationAttributionService
{
    public function syncConversation(WhatsappConversation $conversation, ?WhatsappMessage $triggerMessage = null): void
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return;
        }

        $firstMessage = $this->firstMessage($conversation);
        $firstInbound = $this->firstInboundMessage($conversation, $triggerMessage);
        $referralPayload = $this->referralPayload($firstInbound);
        $firstSeenAt = $this->messageTime($firstMessage) ?? $this->messageTime($firstInbound) ?? $conversation->created_at ?? now();
        $initialIntent = $this->classifyInitialIntent($firstInbound?->body, $firstMessage?->direction);
        [$patientSegment, $lastClinicalTouchAt] = $this->resolvePatientSegment(
            $this->normalizeIdentifier((string) ($conversation->patient_hc_number ?? '')),
            $conversation->created_at
        );
        $sourceCategory = $this->resolveSourceCategory(
            $firstMessage,
            $firstInbound,
            $referralPayload,
            $initialIntent,
            $patientSegment,
            $lastClinicalTouchAt,
            $conversation->created_at
        );
        $conversationType = $this->resolveConversationType(
            $initialIntent,
            $sourceCategory,
            $patientSegment,
            $lastClinicalTouchAt,
            $conversation->created_at
        );

        WhatsappConversationAttribution::query()->updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'first_message_id' => $firstMessage?->id,
                'first_inbound_message_id' => $firstInbound?->id,
                'source_category' => $sourceCategory,
                'platform' => $this->derivePlatformFromUrl($this->scalar($referralPayload['source_url'] ?? null)),
                'source_type' => $this->truncate($this->scalar($referralPayload['source_type'] ?? null), 64),
                'source_id' => $this->truncate($this->scalar($referralPayload['source_id'] ?? null), 191),
                'source_url' => $this->scalar($referralPayload['source_url'] ?? null),
                'media_type' => $this->truncate($this->scalar($referralPayload['media_type'] ?? null), 64),
                'headline' => $this->truncate($this->scalar($referralPayload['headline'] ?? null), 191),
                'body' => $this->scalar($referralPayload['body'] ?? null),
                'video_url' => $this->scalar($referralPayload['video_url'] ?? null),
                'thumbnail_url' => $this->scalar($referralPayload['thumbnail_url'] ?? null),
                'ctwa_clid' => $this->truncate($this->scalar($referralPayload['ctwa_clid'] ?? null), 255),
                'welcome_message_text' => $this->scalar(data_get($referralPayload, 'welcome_message.text')),
                'profile_name' => $this->truncate((string) ($conversation->display_name ?? ''), 191),
                'initial_intent' => $initialIntent,
                'conversation_type' => $conversationType,
                'patient_segment' => $patientSegment,
                'patient_hc_number' => $this->normalizeIdentifier((string) ($conversation->patient_hc_number ?? '')) ?: null,
                'last_clinical_touch_at' => $lastClinicalTouchAt,
                'first_seen_at' => $firstSeenAt,
                'last_synced_at' => now(),
                'meta' => [
                    'first_message_type' => $firstMessage?->message_type,
                    'first_inbound_type' => $firstInbound?->message_type,
                    'conversation_needs_human' => (bool) ($conversation->needs_human ?? false),
                    'assigned_user_id' => $conversation->assigned_user_id,
                ],
            ]
        );
    }

    private function firstMessage(WhatsappConversation $conversation): ?WhatsappMessage
    {
        return WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->first();
    }

    private function firstInboundMessage(WhatsappConversation $conversation, ?WhatsappMessage $triggerMessage = null): ?WhatsappMessage
    {
        if ($triggerMessage instanceof WhatsappMessage
            && (int) $triggerMessage->conversation_id === (int) $conversation->id
            && $triggerMessage->direction === 'inbound'
        ) {
            $existing = WhatsappMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', 'inbound')
                ->where('id', '<=', $triggerMessage->id)
                ->orderBy('id')
                ->first();

            if ($existing instanceof WhatsappMessage) {
                return $existing;
            }
        }

        return WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->orderBy('id')
            ->first();
    }

    private function derivePlatformFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (str_contains($host, 'instagram.com')) {
            return 'instagram';
        }

        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
            return 'facebook';
        }

        if (str_contains($host, 'wa.me') || str_contains($host, 'whatsapp.com')) {
            return 'whatsapp';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function referralPayload(?WhatsappMessage $message): array
    {
        $payload = is_array($message?->raw_payload) ? $message->raw_payload : [];
        $referral = $payload['referral'] ?? null;

        return is_array($referral) ? $referral : [];
    }

    /**
     * @return array{0:string,1:?Carbon}
     */
    private function resolvePatientSegment(string $identifier, ?CarbonInterface $conversationCreatedAt): array
    {
        if ($identifier === '' || $conversationCreatedAt === null) {
            return ['unknown', null];
        }

        $lastConsultaAt = $this->latestConsultaAt($identifier, $conversationCreatedAt);
        $lastSurgeryAt = $this->latestSurgeryAt($identifier, $conversationCreatedAt);
        $lastTouch = $this->latestDate($lastConsultaAt, $lastSurgeryAt);

        if ($lastTouch === null) {
            return ['new_patient', null];
        }

        if ($lastTouch->lessThan($conversationCreatedAt->copy()->subDays(180))) {
            return ['reactivated_patient', $lastTouch];
        }

        return ['returning_patient', $lastTouch];
    }

    private function latestConsultaAt(string $identifier, CarbonInterface $conversationCreatedAt): ?Carbon
    {
        if (!Schema::hasTable('consulta_data')) {
            return null;
        }

        $value = DB::table('consulta_data')
            ->where('hc_number', $identifier)
            ->whereNotNull('fecha')
            ->where('fecha', '<', $conversationCreatedAt->toDateTimeString())
            ->max('fecha');

        return $this->parseDate($value);
    }

    private function latestSurgeryAt(string $identifier, CarbonInterface $conversationCreatedAt): ?Carbon
    {
        if (!Schema::hasTable('protocolo_data')) {
            return null;
        }

        $row = DB::table('protocolo_data')
            ->selectRaw('COALESCE(fecha_inicio, fecha) AS clinical_at')
            ->where('hc_number', $identifier)
            ->where(function ($query): void {
                $query->whereNotNull('fecha_inicio')
                    ->orWhereNotNull('fecha');
            })
            ->whereRaw('COALESCE(fecha_inicio, fecha) < ?', [$conversationCreatedAt->toDateTimeString()])
            ->orderByRaw('COALESCE(fecha_inicio, fecha) DESC')
            ->first();

        return $this->parseDate($row->clinical_at ?? null);
    }

    private function latestDate(?Carbon $left, ?Carbon $right): ?Carbon
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }

        return $left->greaterThan($right) ? $left : $right;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveSourceCategory(
        ?WhatsappMessage $firstMessage,
        ?WhatsappMessage $firstInbound,
        array $referralPayload,
        ?string $initialIntent,
        string $patientSegment,
        ?Carbon $lastClinicalTouchAt,
        ?CarbonInterface $conversationCreatedAt
    ): string
    {
        $referralType = strtolower(trim((string) ($referralPayload['source_type'] ?? '')));
        if ($referralType === 'ad') {
            return 'ad';
        }

        if (($firstMessage?->direction ?? null) === 'outbound') {
            return 'campaign_outbound';
        }

        if ($this->isRecentClinicalTouch($lastClinicalTouchAt, $conversationCreatedAt, 30)) {
            return $this->looksLikeSurgeryFollowup($initialIntent)
                ? 'post_surgery'
                : 'post_consultation';
        }

        if (in_array($initialIntent, ['results', 'reschedule', 'cancel', 'human_help'], true)) {
            return 'support_operational';
        }

        if (in_array($patientSegment, ['returning_patient', 'reactivated_patient'], true) && $firstInbound instanceof WhatsappMessage) {
            return 'patient_return';
        }

        if ($firstInbound instanceof WhatsappMessage) {
            return 'organic_direct';
        }

        return 'unknown';
    }

    private function resolveConversationType(
        ?string $initialIntent,
        string $sourceCategory,
        string $patientSegment,
        ?Carbon $lastClinicalTouchAt,
        ?CarbonInterface $conversationCreatedAt
    ): string
    {
        if ($sourceCategory === 'campaign_outbound') {
            return 'campaign_response';
        }

        if ($sourceCategory === 'post_surgery' || $this->isRecentClinicalTouch($lastClinicalTouchAt, $conversationCreatedAt, 30)) {
            return 'post_op_followup';
        }

        return match ($initialIntent) {
            'booking' => 'booking',
            'reschedule' => 'reschedule',
            'cancel' => 'cancel',
            'results' => 'results',
            'human_help' => 'human_help',
            'pricing', 'hours_location', 'general_info' => 'faq',
            default => in_array($patientSegment, ['returning_patient', 'reactivated_patient'], true) ? 'patient_return' : 'other',
        };
    }

    private function classifyInitialIntent(?string $text, ?string $firstMessageDirection = null): ?string
    {
        $normalized = $this->normalizeText((string) $text);
        if ($normalized === '') {
            return $firstMessageDirection === 'outbound' ? 'outbound_followup' : null;
        }

        if ($this->containsAny($normalized, ['agendar', 'agenda', 'cita', 'turno'])) {
            return 'booking';
        }
        if ($this->containsAny($normalized, ['reagendar', 'cambiar cita', 'mover cita'])) {
            return 'reschedule';
        }
        if ($this->containsAny($normalized, ['cancelar', 'anular cita'])) {
            return 'cancel';
        }
        if ($this->containsAny($normalized, ['precio', 'costa', 'cuanto cuesta', 'valor'])) {
            return 'pricing';
        }
        if ($this->containsAny($normalized, ['horario', 'direccion', 'ubicacion', 'sede', 'atienden'])) {
            return 'hours_location';
        }
        if ($this->containsAny($normalized, ['resultado', 'examen', 'resonancia', 'laboratorio'])) {
            return 'results';
        }
        if ($this->containsAny($normalized, ['ayuda', 'asesor', 'humano', 'agente', 'persona'])) {
            return 'human_help';
        }
        if ($this->containsAny($normalized, ['hola', 'buenas', 'informacion', 'información'])) {
            return 'general_info';
        }

        return 'other';
    }

    private function isRecentClinicalTouch(?Carbon $lastClinicalTouchAt, ?CarbonInterface $conversationCreatedAt, int $days): bool
    {
        if ($lastClinicalTouchAt === null || $conversationCreatedAt === null) {
            return false;
        }

        return $lastClinicalTouchAt->greaterThanOrEqualTo($conversationCreatedAt->copy()->subDays($days));
    }

    private function looksLikeSurgeryFollowup(?string $initialIntent): bool
    {
        return in_array($initialIntent, ['results', 'human_help'], true);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $this->normalizeText((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii !== false ? $ascii : $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeIdentifier(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    private function messageTime(?WhatsappMessage $message): ?CarbonInterface
    {
        return $message?->message_timestamp ?? $message?->created_at;
    }

    private function scalar(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function truncate(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
