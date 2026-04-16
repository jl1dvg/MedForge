<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConversationWriteService
{
    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly CloudApiTransportService $transport = new CloudApiTransportService(),
    ) {
    }

    /**
     * @return array{conversation: array<string,mixed>, message: array<string,mixed>}
     */
    public function sendTextToConversation(int $conversationId, string $message, bool $previewUrl = false, ?int $actorUserId = null): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('El mensaje no puede estar vacío.');
        }

        $conversation = WhatsappConversation::query()->find($conversationId);
        if (!$conversation instanceof WhatsappConversation) {
            throw new RuntimeException('Conversación no encontrada.');
        }

        $requireAssignmentToReply = (bool) ($this->configService->get()['chat_require_assignment_to_reply'] ?? true);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        $needsHuman = (bool) ($conversation->needs_human ?? false);

        if (($needsHuman || $requireAssignmentToReply) && $assignedUserId <= 0) {
            throw new RuntimeException('Debes tomar esta conversación antes de responder.');
        }

        if ($assignedUserId > 0 && $actorUserId !== null && $assignedUserId !== $actorUserId) {
            throw new RuntimeException('Esta conversación está asignada a otro agente. Solo el agente asignado puede responder.');
        }

        if (!$this->hasInboundWindowOpen($conversationId)) {
            throw new RuntimeException('Este contacto no ha iniciado conversación. Debes enviar una plantilla aprobada para abrir la ventana de 24h.');
        }

        $config = $this->configService->get();
        if (!$config['enabled'] || $config['phone_number_id'] === '' || $config['access_token'] === '') {
            throw new RuntimeException('La integración de WhatsApp Cloud API no está lista en Laravel.');
        }

        $recipient = $this->normalizePhoneNumber($conversation->wa_number, $config['default_country_code']);
        if ($recipient === '') {
            throw new RuntimeException('El número de la conversación no es válido.');
        }

        $sentAt = now();
        $transportResult = $this->transport->sendText(
            $config['phone_number_id'],
            $config['access_token'],
            $config['api_version'],
            $recipient,
            $message,
            $previewUrl
        );

        $createdMessage = DB::transaction(function () use ($conversation, $message, $transportResult, $sentAt): WhatsappMessage {
            $created = WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $transportResult['wa_message_id'],
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $message,
                'raw_payload' => $transportResult['raw'],
                'status' => $transportResult['status'],
                'message_timestamp' => $sentAt,
                'sent_at' => $sentAt,
            ]);

            $conversation->fill([
                'last_message_at' => $sentAt,
                'last_message_direction' => 'outbound',
                'last_message_type' => 'text',
                'last_message_preview' => mb_substr($message, 0, 512),
            ]);
            $conversation->save();

            return $created;
        });

        $conversation->refresh();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'wa_number' => $conversation->wa_number,
                'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
                'last_message_direction' => $conversation->last_message_direction,
                'last_message_type' => $conversation->last_message_type,
                'last_message_preview' => $conversation->last_message_preview,
            ],
            'message' => [
                'id' => $createdMessage->id,
                'wa_message_id' => $createdMessage->wa_message_id,
                'direction' => $createdMessage->direction,
                'message_type' => $createdMessage->message_type,
                'body' => $createdMessage->body,
                'status' => $createdMessage->status,
                'sent_at' => optional($createdMessage->sent_at)?->toISOString(),
                'source' => 'laravel-v2',
                'actor_user_id' => $actorUserId,
            ],
        ];
    }

    /**
     * @return array{conversation: array<string,mixed>, message: array<string,mixed>}
     */
    public function sendMediaToConversation(
        int $conversationId,
        string $type,
        string $mediaUrl,
        ?string $caption = null,
        ?string $filename = null,
        ?string $mimeType = null,
        ?string $mediaDisk = null,
        ?string $mediaPath = null,
        ?int $actorUserId = null
    ): array {
        $type = trim($type);
        $mediaUrl = trim($mediaUrl);
        $caption = $caption !== null ? trim($caption) : null;
        $filename = $filename !== null ? trim($filename) : null;
        $mimeType = $mimeType !== null ? trim($mimeType) : null;
        $mediaDisk = $mediaDisk !== null ? trim($mediaDisk) : null;
        $mediaPath = $mediaPath !== null ? trim($mediaPath) : null;

        if (!in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            throw new RuntimeException('Tipo de media no soportado.');
        }

        if ($mediaUrl === '') {
            throw new RuntimeException('La URL del archivo es obligatoria.');
        }

        $conversation = WhatsappConversation::query()->find($conversationId);
        if (!$conversation instanceof WhatsappConversation) {
            throw new RuntimeException('Conversación no encontrada.');
        }

        $requireAssignmentToReply = (bool) ($this->configService->get()['chat_require_assignment_to_reply'] ?? true);
        $assignedUserId = (int) ($conversation->assigned_user_id ?? 0);
        $needsHuman = (bool) ($conversation->needs_human ?? false);

        if (($needsHuman || $requireAssignmentToReply) && $assignedUserId <= 0) {
            throw new RuntimeException('Debes tomar esta conversación antes de responder.');
        }

        if ($assignedUserId > 0 && $actorUserId !== null && $assignedUserId !== $actorUserId) {
            throw new RuntimeException('Esta conversación está asignada a otro agente. Solo el agente asignado puede responder.');
        }

        if (!$this->hasInboundWindowOpen($conversationId)) {
            throw new RuntimeException('Este contacto no ha iniciado conversación. Debes enviar una plantilla aprobada para abrir la ventana de 24h.');
        }

        $config = $this->configService->get();
        if (!$config['enabled'] || $config['phone_number_id'] === '' || $config['access_token'] === '') {
            throw new RuntimeException('La integración de WhatsApp Cloud API no está lista en Laravel.');
        }

        $recipient = $this->normalizePhoneNumber($conversation->wa_number, $config['default_country_code']);
        if ($recipient === '') {
            throw new RuntimeException('El número de la conversación no es válido.');
        }

        $sentAt = now();
        $transportResult = $this->transport->sendMedia(
            $config['phone_number_id'],
            $config['access_token'],
            $config['api_version'],
            $recipient,
            $type,
            $mediaUrl,
            $caption,
            $filename,
            $mimeType,
            $mediaDisk,
            $mediaPath
        );

        $createdMessage = DB::transaction(function () use ($conversation, $type, $caption, $filename, $mimeType, $mediaUrl, $mediaDisk, $mediaPath, $transportResult, $sentAt): WhatsappMessage {
            $rawPayload = is_array($transportResult['raw']) ? $transportResult['raw'] : [];
            $rawPayload[$type] = array_filter([
                'link' => $mediaUrl,
                'caption' => $caption,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'disk' => $mediaDisk,
                'path' => $mediaPath,
            ], static fn ($value) => $value !== null && $value !== '');

            $created = WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $transportResult['wa_message_id'],
                'direction' => 'outbound',
                'message_type' => $type,
                'body' => $caption,
                'raw_payload' => $rawPayload,
                'status' => $transportResult['status'],
                'message_timestamp' => $sentAt,
                'sent_at' => $sentAt,
            ]);

            $conversation->fill([
                'last_message_at' => $sentAt,
                'last_message_direction' => 'outbound',
                'last_message_type' => $type,
                'last_message_preview' => $caption !== null && $caption !== '' ? mb_substr($caption, 0, 512) : '[' . $type . ']',
            ]);
            $conversation->save();

            return $created;
        });

        $conversation->refresh();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'wa_number' => $conversation->wa_number,
                'last_message_at' => optional($conversation->last_message_at)?->toISOString(),
                'last_message_direction' => $conversation->last_message_direction,
                'last_message_type' => $conversation->last_message_type,
                'last_message_preview' => $conversation->last_message_preview,
            ],
            'message' => [
                'id' => $createdMessage->id,
                'wa_message_id' => $createdMessage->wa_message_id,
                'direction' => $createdMessage->direction,
                'message_type' => $createdMessage->message_type,
                'body' => $createdMessage->body,
                'status' => $createdMessage->status,
                'sent_at' => optional($createdMessage->sent_at)?->toISOString(),
                'source' => 'laravel-v2',
                'actor_user_id' => $actorUserId,
            ],
        ];
    }

    private function hasInboundWindowOpen(int $conversationId): bool
    {
        $threshold = now()->subHours(24)->format('Y-m-d H:i:s');

        return WhatsappMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('direction', 'inbound')
            ->whereRaw('COALESCE(message_timestamp, created_at) >= ?', [$threshold])
            ->exists();
    }

    private function normalizePhoneNumber(string $phoneNumber, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?: '';
        if ($digits === '') {
            return '';
        }

        if ($defaultCountryCode !== '' && !str_starts_with($digits, $defaultCountryCode)) {
            return $defaultCountryCode . ltrim($digits, '0');
        }

        return $digits;
    }
}
