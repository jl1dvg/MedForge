<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

class WhatsappRealtimeService
{
    public const EVENT_INBOUND_MESSAGE = 'whatsapp.inbound-message';
    public const EVENT_CONVERSATION_UPDATED = 'whatsapp.conversation-updated';
    private const CHANNEL = 'whatsapp-ops';

    /**
     * @var array<string,mixed>|null
     */
    private ?array $configCache = null;

    public function broadcastInboundMessage(WhatsappConversation $conversation, WhatsappMessage $message): void
    {
        $preview = trim((string) ($conversation->last_message_preview ?? $message->body ?? ''));
        if ($preview === '') {
            $preview = '[' . trim((string) ($message->message_type ?? 'mensaje')) . ']';
        }

        $assignedUserName = $this->resolveAssignedUserName((int) ($conversation->assigned_user_id ?? 0));
        $isQueue = (bool) ($conversation->needs_human ?? false) && (int) ($conversation->assigned_user_id ?? 0) <= 0;

        $payload = [
            'type' => 'inbound_message',
            'conversation' => $this->serializeConversation($conversation, $assignedUserName),
            'message' => $this->serializeMessage($message),
            'notification' => [
                'dedupeKey' => 'whatsapp-inbound-' . (int) $message->id,
                'title' => $this->resolveConversationTitle($conversation),
                'message' => $preview,
                'meta' => array_values(array_filter([
                    $conversation->patient_full_name ? 'Paciente: ' . $conversation->patient_full_name : null,
                    $assignedUserName !== null ? 'Asignado a ' . $assignedUserName : 'Sin tomar',
                ])),
                'badges' => array_values(array_filter([
                    ['label' => 'WhatsApp', 'variant' => 'bg-success text-white'],
                    $isQueue ? ['label' => 'En cola', 'variant' => 'bg-warning text-dark'] : null,
                    !$isQueue && (int) ($conversation->assigned_user_id ?? 0) > 0 ? ['label' => 'Asignado', 'variant' => 'bg-primary text-white'] : null,
                ])),
                'icon' => 'mdi mdi-whatsapp',
                'tone' => $isQueue ? 'warning' : 'info',
                'timestamp' => optional($message->message_timestamp ?? $message->created_at)?->toISOString(),
                'channels' => $this->mapChannels(),
            ],
            'pending_notification' => $isQueue ? [
                'dedupeKey' => 'whatsapp-queue-' . (int) $conversation->id,
                'title' => $this->resolveConversationTitle($conversation),
                'message' => 'Conversación sin tomar: ' . $preview,
                'meta' => array_values(array_filter([
                    $conversation->wa_number ? 'Número: +' . ltrim((string) $conversation->wa_number, '+') : null,
                    $conversation->patient_full_name ? 'Paciente: ' . $conversation->patient_full_name : null,
                ])),
                'badges' => [
                    ['label' => 'Pendiente', 'variant' => 'bg-danger text-white'],
                    ['label' => 'WhatsApp', 'variant' => 'bg-success text-white'],
                ],
                'icon' => 'mdi mdi-account-alert-outline',
                'tone' => 'danger',
                'timestamp' => optional($message->message_timestamp ?? $message->created_at)?->toISOString(),
                'channels' => $this->mapChannels(),
            ] : null,
        ];

        $this->trigger($payload, self::EVENT_INBOUND_MESSAGE);
    }

    public function broadcastConversationUpdate(WhatsappConversation $conversation, string $action, ?int $actorUserId = null, ?string $note = null): void
    {
        $action = trim($action);
        if ($action === '') {
            return;
        }

        $assignedUserName = $this->resolveAssignedUserName((int) ($conversation->assigned_user_id ?? 0));
        $actorName = $this->resolveAssignedUserName($actorUserId ?? 0);
        $actionLabel = match ($action) {
            'assigned' => 'Conversación tomada',
            'transferred' => 'Conversación transferida',
            'queued' => 'Conversación enviada a cola',
            'closed' => 'Conversación cerrada',
            default => 'Conversación actualizada',
        };

        $payload = [
            'type' => 'conversation_updated',
            'action' => $action,
            'conversation' => $this->serializeConversation($conversation, $assignedUserName),
            'notification' => [
                'dedupeKey' => 'whatsapp-conversation-' . $action . '-' . (int) $conversation->id,
                'title' => $this->resolveConversationTitle($conversation),
                'message' => $actionLabel,
                'meta' => array_values(array_filter([
                    $actorName !== null ? 'Actor: ' . $actorName : null,
                    $assignedUserName !== null ? 'Responsable: ' . $assignedUserName : 'Sin asignar',
                    $note !== null && trim($note) !== '' ? 'Nota: ' . trim($note) : null,
                ])),
                'badges' => array_values(array_filter([
                    ['label' => 'WhatsApp', 'variant' => 'bg-success text-white'],
                    $action === 'queued' ? ['label' => 'En cola', 'variant' => 'bg-warning text-dark'] : null,
                    $action === 'closed' ? ['label' => 'Resuelto', 'variant' => 'bg-secondary text-white'] : null,
                ])),
                'icon' => 'mdi mdi-whatsapp',
                'tone' => $action === 'queued' ? 'warning' : 'info',
                'timestamp' => now()->toISOString(),
                'channels' => $this->mapChannels(),
            ],
        ];

        $this->trigger($payload, self::EVENT_CONVERSATION_UPDATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $config = [
            'enabled' => false,
            'app_id' => '',
            'key' => '',
            'secret' => '',
            'cluster' => '',
            'channels' => [
                'email' => false,
                'sms' => false,
                'daily_summary' => false,
            ],
        ];

        try {
            $rows = DB::select(
                'SELECT name, value FROM settings WHERE name IN (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'pusher_app_id',
                    'pusher_app_key',
                    'pusher_app_secret',
                    'pusher_cluster',
                    'pusher_realtime_notifications',
                    'notifications_email_enabled',
                    'notifications_sms_enabled',
                    'notifications_daily_summary',
                ]
            );

            $options = [];
            foreach ($rows as $row) {
                $name = (string) ($row->name ?? '');
                if ($name === '') {
                    continue;
                }
                $options[$name] = (string) ($row->value ?? '');
            }

            $config['app_id'] = trim((string) ($options['pusher_app_id'] ?? ''));
            $config['key'] = trim((string) ($options['pusher_app_key'] ?? ''));
            $config['secret'] = trim((string) ($options['pusher_app_secret'] ?? ''));
            $config['cluster'] = trim((string) ($options['pusher_cluster'] ?? ''));
            $config['enabled'] = ((string) ($options['pusher_realtime_notifications'] ?? '0')) === '1'
                && $config['app_id'] !== ''
                && $config['key'] !== ''
                && $config['secret'] !== '';
            $config['channels'] = [
                'email' => ((string) ($options['notifications_email_enabled'] ?? '0')) === '1',
                'sms' => ((string) ($options['notifications_sms_enabled'] ?? '0')) === '1',
                'daily_summary' => ((string) ($options['notifications_daily_summary'] ?? '0')) === '1',
            ];
        } catch (Throwable) {
            $config['enabled'] = false;
        }

        $this->configCache = $config;

        return $this->configCache;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function trigger(array $payload, string $event): void
    {
        $config = $this->getConfig();

        if (!$config['enabled'] || !class_exists(\Pusher\Pusher::class)) {
            return;
        }

        $options = ['useTLS' => true];
        if ($config['cluster'] !== '') {
            $options['cluster'] = $config['cluster'];
        }

        try {
            $pusher = new \Pusher\Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                $options
            );

            $pusher->trigger(self::CHANNEL, $event, $payload);
        } catch (Throwable) {
            // Realtime must not block chat/webhook flow.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(WhatsappConversation $conversation, ?string $assignedUserName): array
    {
        return [
            'id' => (int) $conversation->id,
            'wa_number' => (string) $conversation->wa_number,
            'display_name' => $conversation->display_name,
            'patient_full_name' => $conversation->patient_full_name,
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_type' => $conversation->last_message_type,
            'last_message_direction' => $conversation->last_message_direction,
            'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
            'assigned_user_id' => $conversation->assigned_user_id !== null ? (int) $conversation->assigned_user_id : null,
            'assigned_user_name' => $assignedUserName,
            'needs_human' => (bool) ($conversation->needs_human ?? false),
            'unread_count' => (int) ($conversation->unread_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(WhatsappMessage $message): array
    {
        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $messageType = (string) ($message->message_type ?? 'text');
        $media = is_array($rawPayload[$messageType] ?? null) ? $rawPayload[$messageType] : [];
        $isMedia = in_array($messageType, ['image', 'video', 'document', 'audio'], true);
        $mediaId = $isMedia ? trim((string) ($media['id'] ?? '')) : '';
        $directLink = $isMedia ? trim((string) ($media['link'] ?? '')) : '';
        $caption = trim((string) ($media['caption'] ?? ''));
        $filename = trim((string) ($media['filename'] ?? ''));
        $mimeType = trim((string) ($media['mime_type'] ?? ''));

        return [
            'id' => (int) $message->id,
            'wa_message_id' => $message->wa_message_id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'status' => $message->status,
            'message_timestamp' => optional($message->message_timestamp)?->toISOString(),
            'sent_at' => optional($message->sent_at)?->toISOString(),
            'delivered_at' => optional($message->delivered_at)?->toISOString(),
            'read_at' => optional($message->read_at)?->toISOString(),
            'media' => $isMedia ? [
                'id' => $mediaId !== '' ? $mediaId : null,
                'mime_type' => $mimeType !== '' ? $mimeType : null,
                'filename' => $filename !== '' ? $filename : null,
                'caption' => $caption !== '' ? $caption : null,
                'voice' => (bool) ($media['voice'] ?? false),
                'download_url' => $mediaId !== '' || $directLink !== '' ? '/v2/whatsapp/api/messages/' . $message->id . '/media' : null,
            ] : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function mapChannels(): array
    {
        $channels = $this->getConfig()['channels'] ?? [];
        $labels = [];
        if (!empty($channels['email'])) {
            $labels[] = 'Correo';
        }
        if (!empty($channels['sms'])) {
            $labels[] = 'SMS';
        }
        if (!empty($channels['daily_summary'])) {
            $labels[] = 'Resumen diario';
        }

        return $labels;
    }

    private function resolveConversationTitle(WhatsappConversation $conversation): string
    {
        $title = trim((string) ($conversation->display_name ?? ''));
        if ($title !== '') {
            return $title;
        }

        return '+' . ltrim((string) $conversation->wa_number, '+');
    }

    private function resolveAssignedUserName(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $user = User::query()->find($userId);
        if (!$user instanceof User) {
            return null;
        }

        $displayName = trim((string) $user->nombre);
        if ($displayName === '') {
            $displayName = trim((string) $user->first_name . ' ' . (string) $user->last_name);
        }
        if ($displayName === '') {
            $displayName = trim((string) $user->username);
        }

        return $displayName !== '' ? $displayName : null;
    }
}
