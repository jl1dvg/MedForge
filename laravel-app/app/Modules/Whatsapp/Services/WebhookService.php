<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Shared\Support\SettingsOptionResolver;
use App\Models\WhatsappConversation;
use App\Models\WhatsappHandoff;
use App\Models\WhatsappMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebhookService
{
    private ?SettingsOptionResolver $settingsResolver = null;

    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly FlowRuntimeShadowObserverService $shadowObserver = new FlowRuntimeShadowObserverService(),
        private readonly WhatsappRealtimeService $realtime = new WhatsappRealtimeService(),
        private readonly FlowRuntimeExecutionService $runtime = new FlowRuntimeExecutionService(),
        private readonly ConversationAttributionService $attributionService = new ConversationAttributionService(),
    ) {
    }

    public function verifyToken(): string
    {
        $config = $this->configService->get();
        $token = trim((string) ($config['webhook_verify_token'] ?? ''));
        if ($token !== '') {
            return $token;
        }

        return (string) (
            env('WHATSAPP_WEBHOOK_VERIFY_TOKEN')
            ?: env('WHATSAPP_VERIFY_TOKEN')
            ?: 'medforge-whatsapp'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{statuses_applied:int,messages_persisted:int,automation_runs:int,automation_messages_sent:int}
     */
    public function process(array $payload): array
    {
        $statusesApplied = 0;
        foreach ($this->extractStatuses($payload) as $status) {
            $statusesApplied += $this->applyStatusUpdate($status) ? 1 : 0;
        }

        $messagesPersisted = 0;
        $automationRuns = 0;
        $automationMessagesSent = 0;
        foreach ($this->extractMessages($payload) as $message) {
            $persisted = $this->recordIncomingMessage($message);
            $messagesPersisted += $persisted ? 1 : 0;
            $this->observeAutomationShadow($message, $persisted);

            if ($persisted) {
                $automation = $this->executeAutomation($message);
                $automationRuns += !empty($automation['executed']) ? 1 : 0;
                $automationMessagesSent += (int) ($automation['messages_sent'] ?? 0);
                $this->syncConversationAttribution($message);
            }
        }

        return [
            'statuses_applied' => $statusesApplied,
            'messages_persisted' => $messagesPersisted,
            'automation_runs' => $automationRuns,
            'automation_messages_sent' => $automationMessagesSent,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractMessages(array $payload): array
    {
        $messages = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach (($entry['changes'] ?? []) as $change) {
                if (!is_array($change) || !isset($change['value']) || !is_array($change['value'])) {
                    continue;
                }

                $value = $change['value'];
                $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
                $contacts = $this->mapContacts(is_array($value['contacts'] ?? null) ? $value['contacts'] : []);

                foreach (($value['messages'] ?? []) as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $message['metadata'] = $metadata;
                    $from = isset($message['from']) ? (string) $message['from'] : '';
                    if ($from !== '' && isset($contacts[$from])) {
                        $message['profile'] = ['name' => $contacts[$from]];
                    }

                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractStatuses(array $payload): array
    {
        $statuses = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach (($entry['changes'] ?? []) as $change) {
                if (!is_array($change) || !isset($change['value']) || !is_array($change['value'])) {
                    continue;
                }

                foreach (($change['value']['statuses'] ?? []) as $status) {
                    if (is_array($status)) {
                        $statuses[] = $status;
                    }
                }
            }
        }

        return $statuses;
    }

    /**
     * @param array<int, array<string, mixed>> $contacts
     * @return array<string, string>
     */
    private function mapContacts(array $contacts): array
    {
        $map = [];

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $waId = trim((string) ($contact['wa_id'] ?? ''));
            $name = trim((string) ($contact['profile']['name'] ?? ''));

            if ($waId === '' || $name === '') {
                continue;
            }

            $map[$waId] = $name;
            $map['+' . ltrim($waId, '+')] = $name;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function applyStatusUpdate(array $status): bool
    {
        $messageId = trim((string) ($status['id'] ?? ''));
        $state = strtolower(trim((string) ($status['status'] ?? '')));

        if ($messageId === '' || $state === '') {
            return false;
        }

        $timestamp = $this->resolveTimestamp($status['timestamp'] ?? null);
        $updates = [
            'status' => $state,
            'updated_at' => now(),
        ];

        if ($timestamp !== null) {
            if ($state === 'sent') {
                $updates['sent_at'] = DB::raw('COALESCE(sent_at, \'' . $timestamp->toDateTimeString() . '\')');
            } elseif ($state === 'delivered') {
                $updates['delivered_at'] = DB::raw('COALESCE(delivered_at, \'' . $timestamp->toDateTimeString() . '\')');
            } elseif ($state === 'read') {
                $updates['read_at'] = DB::raw('COALESCE(read_at, \'' . $timestamp->toDateTimeString() . '\')');
            }
        }

        return WhatsappMessage::query()
            ->where('wa_message_id', $messageId)
            ->update($updates) > 0;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function recordIncomingMessage(array $message): bool
    {
        $number = $this->normalizePhoneNumber($message['from'] ?? null);
        if ($number === null) {
            return false;
        }

        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId !== '' && WhatsappMessage::query()->where('wa_message_id', $messageId)->exists()) {
            return false;
        }

        $type = trim((string) ($message['type'] ?? 'text'));
        if ($type === '') {
            $type = 'text';
        }

        $body = $this->extractText($message);
        $timestamp = $this->resolveTimestamp($message['timestamp'] ?? null) ?? now()->toImmutable();
        $displayName = trim((string) ($message['profile']['name'] ?? ''));
        $mediaPreview = $this->extractMediaPreview($message, $type);
        $previewText = $body !== null && trim($body) !== '' ? $body : $mediaPreview;
        $inboxBody = $previewText !== null && trim($previewText) !== '' ? $previewText : '[' . $type . ']';

        $persistedConversation = null;
        $persistedMessage = null;

        DB::transaction(function () use ($number, $displayName, $type, $body, $timestamp, $messageId, $message, $inboxBody, $previewText, &$persistedConversation, &$persistedMessage): void {
            $conversation = WhatsappConversation::query()->firstOrNew([
                'wa_number' => $number,
            ]);

            if ($displayName !== '' && ($conversation->display_name === null || trim((string) $conversation->display_name) === '')) {
                $conversation->display_name = $displayName;
            } elseif ($displayName !== '') {
                $conversation->display_name = $displayName;
            }

            $conversation->last_message_at = $timestamp;
            $conversation->last_message_direction = 'inbound';
            $conversation->last_message_type = $type;
            $conversation->last_message_preview = $previewText !== null ? mb_substr($previewText, 0, 512) : null;
            $conversation->unread_count = (int) $conversation->unread_count + 1;

            // Auto-reopen only real human conversations; bot quick replies must keep flowing through automation.
            if ($this->shouldAutoReopenHumanQueue($conversation, $type, $body)) {
                $conversation->needs_human = true;
            }

            $conversation->save();

            $persistedMessage = WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $messageId !== '' ? $messageId : null,
                'direction' => 'inbound',
                'message_type' => $type,
                'body' => $body,
                'raw_payload' => $message,
                'status' => 'received',
                'message_timestamp' => $timestamp,
            ]);

            DB::table('whatsapp_inbox_messages')->insert([
                'wa_number' => $number,
                'direction' => 'incoming',
                'message_type' => $type,
                'message_body' => $inboxBody,
                'message_id' => $messageId !== '' ? $messageId : null,
                'payload' => json_encode($message, JSON_UNESCAPED_UNICODE),
                'created_at' => $timestamp->toDateTimeString(),
            ]);

            $persistedConversation = $conversation;
        });

        if ($persistedConversation instanceof WhatsappConversation && $persistedMessage instanceof WhatsappMessage) {
            $this->attributionService->syncConversation($persistedConversation, $persistedMessage);
            $this->realtime->broadcastInboundMessage($persistedConversation, $persistedMessage);
        }

        return true;
    }

    private function shouldAutoReopenHumanQueue(WhatsappConversation $conversation, string $type, ?string $body): bool
    {
        if ($conversation->wasRecentlyCreated || (bool) $conversation->needs_human) {
            return false;
        }

        if ($type === 'interactive') {
            return false;
        }

        if ($this->isBotResumeText($body)) {
            return false;
        }

        if (!$this->humanQueueIsOpen()) {
            return false;
        }

        if (!Schema::hasTable('whatsapp_handoffs')) {
            return false;
        }

        return WhatsappHandoff::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'resolved')
            ->whereNotNull('assigned_agent_id')
            ->exists();
    }

    private function isBotResumeText(?string $body): bool
    {
        $normalized = $this->normalizeText((string) $body);

        return in_array($normalized, [
            'menu',
            'hola',
            'inicio',
            'iniciar',
            'empezar',
            'comenzar',
            'agendar',
            'agendar cita',
            'consultar cita',
            'servicios y sedes',
            'sedes',
            'promociones',
        ], true);
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    private function humanQueueIsOpen(): bool
    {
        $options = $this->settingsOptions([
            'whatsapp_handoff_business_timezone',
            'whatsapp_handoff_business_schedule',
            'whatsapp_handoff_business_holidays',
            'whatsapp_handoff_business_start',
            'whatsapp_handoff_business_end',
        ]);

        $timezone = trim((string) ($options['whatsapp_handoff_business_timezone'] ?? 'America/Guayaquil'));
        if ($timezone === '') {
            $timezone = 'America/Guayaquil';
        }

        $now = CarbonImmutable::now($timezone);
        if ($this->isConfiguredHoliday($now->toDateString(), (string) ($options['whatsapp_handoff_business_holidays'] ?? ''))) {
            return false;
        }

        $daySchedule = $this->resolveDaySchedule($now->isoWeekday(), $options);
        if ($daySchedule === null || !($daySchedule['enabled'] ?? false)) {
            return false;
        }

        $start = $this->minutesFromHour((string) ($daySchedule['start'] ?? '08:00'), 8 * 60);
        $end = $this->minutesFromHour((string) ($daySchedule['end'] ?? '18:00'), 18 * 60);
        $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    /**
     * @param array<string,string> $options
     * @return array{enabled:bool,start:string,end:string}|null
     */
    private function resolveDaySchedule(int $isoWeekday, array $options): ?array
    {
        $schedule = json_decode((string) ($options['whatsapp_handoff_business_schedule'] ?? ''), true);
        if (!is_array($schedule)) {
            $start = (string) ($options['whatsapp_handoff_business_start'] ?? '08:00');
            $end = (string) ($options['whatsapp_handoff_business_end'] ?? '18:00');

            return $isoWeekday >= 1 && $isoWeekday <= 6
                ? ['enabled' => true, 'start' => $start, 'end' => $end]
                : ['enabled' => false, 'start' => $start, 'end' => $end];
        }

        $dayKey = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ][$isoWeekday] ?? 'monday';

        $day = $schedule[$dayKey] ?? null;
        if (is_string($day) && preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $day, $matches)) {
            return ['enabled' => true, 'start' => $matches[1], 'end' => $matches[2]];
        }

        if (!is_array($day)) {
            return null;
        }

        return [
            'enabled' => filter_var($day['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'start' => (string) ($day['start'] ?? '08:00'),
            'end' => (string) ($day['end'] ?? '18:00'),
        ];
    }

    private function isConfiguredHoliday(string $date, string $configured): bool
    {
        $dates = preg_split('/[\r\n,]+/', $configured) ?: [];

        return in_array($date, array_map(static fn(string $value): string => trim($value), $dates), true);
    }

    private function minutesFromHour(string $value, int $default): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $matches)) {
            return $default;
        }

        $hour = max(0, min(23, (int) $matches[1]));
        $minute = max(0, min(59, (int) $matches[2]));

        return ($hour * 60) + $minute;
    }

    /**
     * @param array<int,string> $keys
     * @return array<string,string>
     */
    private function settingsOptions(array $keys): array
    {
        if (!$this->settingsResolver instanceof SettingsOptionResolver) {
            $this->settingsResolver = new SettingsOptionResolver();
        }

        return $this->settingsResolver->getOptions($keys);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function syncConversationAttribution(array $message): void
    {
        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId === '') {
            return;
        }

        $inboundMessage = WhatsappMessage::query()
            ->where('wa_message_id', $messageId)
            ->where('direction', 'inbound')
            ->latest('id')
            ->first();

        if (!$inboundMessage instanceof WhatsappMessage) {
            return;
        }

        $conversation = WhatsappConversation::query()->find($inboundMessage->conversation_id);
        if (!$conversation instanceof WhatsappConversation) {
            return;
        }

        $this->attributionService->syncConversation($conversation, $inboundMessage);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function observeAutomationShadow(array $message, bool $persisted): void
    {
        if (!$persisted) {
            return;
        }

        $number = $this->normalizePhoneNumber($message['from'] ?? null);
        $text = $this->extractText($message);
        if ($number === null || $text === null || trim($text) === '') {
            return;
        }

        $context = [];
        $conversation = WhatsappConversation::query()->where('wa_number', $number)->first();
        if ($conversation?->whatsapp_autoresponder_session !== null) {
            $sessionContext = $conversation->whatsapp_autoresponder_session->context;
            $context = is_array($sessionContext) ? $sessionContext : [];
        }

        $this->shadowObserver->observeWebhookInput([
            'wa_number' => $number,
            'text' => $text,
            'context' => $context,
        ], $message);
    }

    /**
     * @param array<string, mixed> $message
     * @return array{executed:bool,matched:bool,scenario_id:?string,messages_sent:int,handoff_requested:bool,reason:?string}
     */
    private function executeAutomation(array $message): array
    {
        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId === '') {
            return [
                'executed' => false,
                'matched' => false,
                'scenario_id' => null,
                'messages_sent' => 0,
                'handoff_requested' => false,
                'reason' => 'missing_message_id',
            ];
        }

        $inboundMessage = WhatsappMessage::query()
            ->where('wa_message_id', $messageId)
            ->where('direction', 'inbound')
            ->latest('id')
            ->first();

        if (!$inboundMessage instanceof WhatsappMessage) {
            return [
                'executed' => false,
                'matched' => false,
                'scenario_id' => null,
                'messages_sent' => 0,
                'handoff_requested' => false,
                'reason' => 'message_not_found',
            ];
        }

        $conversation = WhatsappConversation::query()->find($inboundMessage->conversation_id);
        if (!$conversation instanceof WhatsappConversation) {
            return [
                'executed' => false,
                'matched' => false,
                'scenario_id' => null,
                'messages_sent' => 0,
                'handoff_requested' => false,
                'reason' => 'conversation_not_found',
            ];
        }

        try {
            return $this->runtime->executeInbound($conversation, $inboundMessage, $message);
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'executed' => false,
                'matched' => false,
                'scenario_id' => null,
                'messages_sent' => 0,
                'handoff_requested' => false,
                'reason' => 'automation_error: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * @param mixed $value
     */
    private function normalizePhoneNumber($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * @param mixed $value
     */
    private function resolveTimestamp($value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampUTC((int) $value);
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractText(array $message): ?string
    {
        $type = (string) ($message['type'] ?? '');

        if ($type === 'text' && isset($message['text']['body'])) {
            return (string) $message['text']['body'];
        }

        if ($type === 'interactive' && isset($message['interactive']) && is_array($message['interactive'])) {
            $interactive = $message['interactive'];
            $interactiveType = $interactive['type'] ?? '';

            if ($interactiveType === 'button_reply') {
                return (string) ($interactive['button_reply']['id'] ?? $interactive['button_reply']['title'] ?? '');
            }

            if ($interactiveType === 'list_reply') {
                return (string) ($interactive['list_reply']['id'] ?? $interactive['list_reply']['title'] ?? '');
            }
        }

        if ($type === 'button' && isset($message['button']['payload'])) {
            return (string) $message['button']['payload'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractMediaPreview(array $message, string $type): ?string
    {
        if (!in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            return null;
        }

        $media = is_array($message[$type] ?? null) ? $message[$type] : [];
        $caption = trim((string) ($media['caption'] ?? ''));
        if ($caption !== '') {
            return $caption;
        }

        $filename = trim((string) ($media['filename'] ?? ''));
        if ($filename !== '') {
            return $filename;
        }

        if ($type === 'audio' && !empty($media['voice'])) {
            return '[voice]';
        }

        return '[' . $type . ']';
    }
}
