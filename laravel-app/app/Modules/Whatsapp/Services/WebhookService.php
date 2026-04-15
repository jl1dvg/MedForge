<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WebhookService
{
    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly FlowRuntimeShadowObserverService $shadowObserver = new FlowRuntimeShadowObserverService(),
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
     * @return array{statuses_applied:int,messages_persisted:int}
     */
    public function process(array $payload): array
    {
        $statusesApplied = 0;
        foreach ($this->extractStatuses($payload) as $status) {
            $statusesApplied += $this->applyStatusUpdate($status) ? 1 : 0;
        }

        $messagesPersisted = 0;
        foreach ($this->extractMessages($payload) as $message) {
            $persisted = $this->recordIncomingMessage($message);
            $messagesPersisted += $persisted ? 1 : 0;
            $this->observeAutomationShadow($message, $persisted);
        }

        return [
            'statuses_applied' => $statusesApplied,
            'messages_persisted' => $messagesPersisted,
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
        $inboxBody = $body !== null && trim($body) !== '' ? $body : '[' . $type . ']';

        DB::transaction(function () use ($number, $displayName, $type, $body, $timestamp, $messageId, $message, $inboxBody): void {
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
            $conversation->last_message_preview = $body !== null ? mb_substr($body, 0, 512) : null;
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();

            WhatsappMessage::query()->create([
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
        });

        return true;
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
}
