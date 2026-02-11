<?php

namespace Modules\WhatsApp\Services;

use DateTimeImmutable;
use Core\Permissions;
use Modules\Notifications\Services\PusherConfigService;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\ConversationRepository;
use Modules\WhatsApp\Repositories\InboxRepository;
use Modules\WhatsApp\Support\PhoneNumberFormatter;
use PDO;
use Throwable;

class ConversationService
{
    private ConversationRepository $repository;
    private InboxRepository $inbox;
    private WhatsAppSettings $settings;
    private ?PusherConfigService $pusher = null;
    private ?HandoffService $handoffs = null;

    public function __construct(PDO $pdo)
    {
        $this->repository = new ConversationRepository($pdo);
        $this->inbox = new InboxRepository($pdo);
        $this->settings = new WhatsAppSettings($pdo);
        $this->pusher = new PusherConfigService($pdo);
    }

    public function ensureConversation(string $waNumber, array $attributes = []): int
    {
        $normalized = $this->normalizeNumber($waNumber);
        if ($normalized === null) {
            throw new \InvalidArgumentException('No se pudo formatear el número de WhatsApp.');
        }

        return $this->repository->upsertConversation($normalized, $attributes);
    }

    /**
     * @param array<string, mixed> $message
     * @return bool True when the inbound message was persisted, false if it was skipped.
     */
    public function recordIncoming(array $message): bool
    {
        $number = $this->normalizeNumber($message['from'] ?? null);
        if ($number === null) {
            return false;
        }

        $profileName = null;
        if (isset($message['profile']['name'])) {
            $profileName = trim((string) $message['profile']['name']);
        }

        $messageId = isset($message['id']) ? trim((string) $message['id']) : '';
        if ($messageId !== '' && $this->repository->messageExists($messageId)) {
            return false;
        }

        $conversationId = $this->repository->upsertConversation($number, [
            'display_name' => $profileName,
        ]);

        $type = isset($message['type']) ? (string) $message['type'] : 'text';
        $body = $this->extractBody($message);
        $timestamp = $this->resolveTimestamp($message['timestamp'] ?? null);

        $this->repository->insertMessage($conversationId, [
            'wa_message_id' => $message['id'] ?? null,
            'direction' => 'inbound',
            'message_type' => $type,
            'body' => $body,
            'raw_payload' => $message,
            'message_timestamp' => $timestamp,
        ]);

        $this->repository->touchConversation($conversationId, [
            'last_message_at' => $timestamp ?? date('Y-m-d H:i:s'),
            'last_message_direction' => 'inbound',
            'last_message_type' => $type,
            'last_message_preview' => $this->truncatePreview($body),
            'increment_unread' => 1,
        ]);

        $bodyForInbox = $body;
        if ($bodyForInbox === null || trim((string) $bodyForInbox) === '') {
            $bodyForInbox = '[' . $type . ']';
        }

        $this->inbox->recordIncoming($number, $type, (string) $bodyForInbox, $messageId !== '' ? $messageId : null, $message);

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordOutgoing(
        string $waNumber,
        string $messageType,
        ?string $body,
        array $payload = [],
        ?string $waMessageId = null
    ): int
    {
        $number = $this->normalizeNumber($waNumber);
        if ($number === null) {
            throw new \InvalidArgumentException('No se pudo formatear el número de WhatsApp.');
        }

        $conversationId = $this->repository->upsertConversation($number);
        $timestamp = date('Y-m-d H:i:s');
        $source = isset($payload['source']) ? strtolower((string) $payload['source']) : '';
        $clearHandoff = (bool) ($payload['clear_handoff'] ?? false) || $source === 'human';

        $resolvedMessageId = $waMessageId ?? (isset($payload['wa_message_id']) ? (string) $payload['wa_message_id'] : null);
        if ($resolvedMessageId !== null && trim($resolvedMessageId) === '') {
            $resolvedMessageId = null;
        }

        $this->repository->insertMessage($conversationId, [
            'wa_message_id' => $resolvedMessageId,
            'direction' => 'outbound',
            'message_type' => $messageType,
            'body' => $body,
            'raw_payload' => $payload,
            'message_timestamp' => $timestamp,
            'sent_at' => $timestamp,
            'status' => 'sent',
        ]);

        $this->repository->touchConversation($conversationId, [
            'last_message_at' => $timestamp,
            'last_message_direction' => 'outbound',
            'last_message_type' => $messageType,
            'last_message_preview' => $this->truncatePreview($body),
            'set_unread' => 0,
        ]);

        if ($clearHandoff) {
            $this->clearHandoff($conversationId);
        }

        $bodyForInbox = $body;
        if ($bodyForInbox === null || trim((string) $bodyForInbox) === '') {
            $bodyForInbox = '[' . $messageType . ']';
        }

        $payloadForInbox = $payload;
        if ($resolvedMessageId !== null) {
            $payloadForInbox['wa_message_id'] = $resolvedMessageId;
        }
        $payloadForInbox['status'] = 'sent';

        $this->inbox->recordOutgoing($number, $messageType, (string) $bodyForInbox, $payloadForInbox);

        return $conversationId;
    }

    public function updateMessageStatus(string $waMessageId, ?string $status, $timestamp = null): void
    {
        $waMessageId = trim($waMessageId);
        if ($waMessageId === '') {
            return;
        }

        $normalizedStatus = $status !== null ? strtolower(trim((string) $status)) : '';
        if ($normalizedStatus === '') {
            return;
        }

        $resolvedTimestamp = $this->resolveTimestamp($timestamp);

        $this->repository->updateMessageStatus($waMessageId, $normalizedStatus, $resolvedTimestamp);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(string $search = '', int $limit = 25): array
    {
        $rows = $this->repository->listConversations($search, $limit);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'wa_number' => $row['wa_number'],
                'display_name' => $row['display_name'] ?? null,
                'patient_hc_number' => $row['patient_hc_number'] ?? null,
                'patient_full_name' => $row['patient_full_name'] ?? null,
                'needs_human' => (bool) ($row['needs_human'] ?? false),
                'handoff_notes' => $row['handoff_notes'] ?? null,
                'handoff_role_id' => isset($row['handoff_role_id']) ? (int) $row['handoff_role_id'] : null,
                'handoff_role_name' => $row['handoff_role_name'] ?? null,
                'assigned_user_id' => isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : null,
                'assigned_user_name' => $row['assigned_user_name'] ?? null,
                'assigned_at' => $this->formatIsoDate($row['assigned_at'] ?? null),
                'handoff_requested_at' => $this->formatIsoDate($row['handoff_requested_at'] ?? null),
                'unread_count' => (int) ($row['unread_count'] ?? 0),
                'last_message' => [
                    'at' => $this->formatIsoDate($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? null),
                    'direction' => $row['last_message_direction'] ?? null,
                    'type' => $row['last_message_type'] ?? null,
                    'preview' => $row['last_message_preview'] ?? null,
                ],
            ];
        }

        return $result;
    }

    public function getConversationWithMessages(int $conversationId, int $limit = 100): ?array
    {
        $conversation = $this->repository->findConversationById($conversationId);
        if ($conversation === null) {
            return null;
        }

        $messages = $this->repository->fetchMessages($conversationId, $limit);
        $this->repository->markConversationAsRead($conversationId);

        $mappedMessages = [];
        foreach ($messages as $message) {
            $mappedMessages[] = [
                'id' => (int) $message['id'],
                'direction' => $message['direction'],
                'type' => $message['message_type'],
                'body' => $message['body'],
                'status' => $message['status'] ?? null,
                'timestamp' => $this->formatIsoDate($message['message_timestamp'] ?? $message['created_at'] ?? null),
                'sent_at' => $this->formatIsoDate($message['sent_at'] ?? null),
                'delivered_at' => $this->formatIsoDate($message['delivered_at'] ?? null),
                'read_at' => $this->formatIsoDate($message['read_at'] ?? null),
            ];
        }

        return [
            'id' => (int) $conversation['id'],
            'wa_number' => $conversation['wa_number'],
            'display_name' => $conversation['display_name'] ?? null,
            'patient_hc_number' => $conversation['patient_hc_number'] ?? null,
            'patient_full_name' => $conversation['patient_full_name'] ?? null,
            'needs_human' => (bool) ($conversation['needs_human'] ?? false),
            'handoff_notes' => $conversation['handoff_notes'] ?? null,
            'handoff_role_id' => isset($conversation['handoff_role_id']) ? (int) $conversation['handoff_role_id'] : null,
            'handoff_role_name' => $conversation['handoff_role_name'] ?? null,
            'assigned_user_id' => isset($conversation['assigned_user_id']) ? (int) $conversation['assigned_user_id'] : null,
            'assigned_user_name' => $conversation['assigned_user_name'] ?? null,
            'assigned_at' => $this->formatIsoDate($conversation['assigned_at'] ?? null),
            'handoff_requested_at' => $this->formatIsoDate($conversation['handoff_requested_at'] ?? null),
            'last_message_at' => $this->formatIsoDate($conversation['last_message_at'] ?? null),
            'messages' => $mappedMessages,
        ];
    }

    public function getConversationSummary(int $conversationId): ?array
    {
        $conversation = $this->repository->findConversationById($conversationId);
        if ($conversation === null) {
            return null;
        }

        return [
            'id' => (int) $conversation['id'],
            'wa_number' => $conversation['wa_number'],
            'display_name' => $conversation['display_name'] ?? null,
            'patient_hc_number' => $conversation['patient_hc_number'] ?? null,
            'patient_full_name' => $conversation['patient_full_name'] ?? null,
            'last_message_at' => $this->formatIsoDate($conversation['last_message_at'] ?? null),
            'unread_count' => (int) ($conversation['unread_count'] ?? 0),
            'needs_human' => (bool) ($conversation['needs_human'] ?? false),
            'handoff_notes' => $conversation['handoff_notes'] ?? null,
            'handoff_role_id' => isset($conversation['handoff_role_id']) ? (int) $conversation['handoff_role_id'] : null,
            'handoff_role_name' => $conversation['handoff_role_name'] ?? null,
            'assigned_user_id' => isset($conversation['assigned_user_id']) ? (int) $conversation['assigned_user_id'] : null,
            'assigned_user_name' => $conversation['assigned_user_name'] ?? null,
            'assigned_at' => $this->formatIsoDate($conversation['assigned_at'] ?? null),
            'handoff_requested_at' => $this->formatIsoDate($conversation['handoff_requested_at'] ?? null),
        ];
    }

    public function flagForHandoff(string $waNumber, ?string $notes = null, ?int $roleId = null): bool
    {
        $handoffId = $this->getHandoffService()->requestHandoff($waNumber, $notes, $roleId);

        return $handoffId !== null;
    }

    public function clearHandoff(int $conversationId): void
    {
        $this->getHandoffService()->resolveConversation($conversationId);
    }

    public function assignConversation(int $conversationId, int $userId): bool
    {
        return $this->getHandoffService()->assignConversation($conversationId, $userId);
    }

    public function transferConversation(int $conversationId, int $userId, ?string $notes = null): bool
    {
        return $this->getHandoffService()->transferConversation($conversationId, $userId, $notes);
    }

    public function closeConversation(int $conversationId): bool
    {
        if ($conversationId <= 0) {
            return false;
        }

        $this->getHandoffService()->resolveConversation($conversationId);
        $this->markConversationAsRead($conversationId);

        return true;
    }

    public function deleteConversation(int $conversationId): bool
    {
        return $this->repository->deleteConversation($conversationId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAgents(?int $roleId = null): array
    {
        $sql = 'SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.nombre, u.permisos, u.role_id, r.name AS role_name, r.permissions AS role_permissions ' .
            'FROM users u LEFT JOIN roles r ON r.id = u.role_id';
        $params = [];
        if ($roleId !== null && $roleId > 0) {
            $sql .= ' WHERE u.role_id = :role_id';
            $params[':role_id'] = $roleId;
        }
        $sql .= ' ORDER BY r.name, u.first_name, u.last_name, u.username';

        $stmt = $this->repository->getPdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        $agents = [];
        foreach ($rows as $row) {
            $permissions = Permissions::merge($row['permisos'] ?? [], $row['role_permissions'] ?? []);
            if (!Permissions::containsAny($permissions, ['whatsapp.chat.send', 'whatsapp.manage'])) {
                continue;
            }

            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['nombre'] ?? ''));
            }
            if ($name === '') {
                $name = (string) ($row['username'] ?? 'Agente');
            }

            $agents[] = [
                'id' => (int) $row['id'],
                'name' => $name,
                'email' => $row['email'] ?? null,
                'role_id' => isset($row['role_id']) ? (int) $row['role_id'] : null,
                'role_name' => $row['role_name'] ?? null,
            ];
        }

        return $agents;
    }

    private function notifyHandoff(int $conversationId): void
    {
        if (!$this->pusher instanceof PusherConfigService) {
            return;
        }

        $conversation = $this->repository->findConversationById($conversationId);
        if ($conversation === null) {
            return;
        }

        $payload = [
            'type' => 'whatsapp_handoff',
            'conversation_id' => (int) $conversation['id'],
            'wa_number' => $conversation['wa_number'],
            'display_name' => $conversation['display_name'] ?? null,
            'patient_full_name' => $conversation['patient_full_name'] ?? null,
            'handoff_notes' => $conversation['handoff_notes'] ?? null,
            'handoff_role_id' => isset($conversation['handoff_role_id']) ? (int) $conversation['handoff_role_id'] : null,
            'handoff_role_name' => $conversation['handoff_role_name'] ?? null,
            'last_message_at' => $this->formatIsoDate($conversation['last_message_at'] ?? null),
        ];

        $this->pusher->trigger($payload, null, PusherConfigService::EVENT_WHATSAPP_HANDOFF);
    }

    private function getHandoffService(): HandoffService
    {
        if (!$this->handoffs instanceof HandoffService) {
            $this->handoffs = new HandoffService($this->repository->getPdo());
        }

        return $this->handoffs;
    }

    public function markConversationAsRead(int $conversationId): void
    {
        $this->repository->markConversationAsRead($conversationId);
    }

    public function findConversationIdByNumber(string $waNumber): ?int
    {
        $normalized = $this->normalizeNumber($waNumber);
        if ($normalized === null) {
            return null;
        }

        return $this->repository->findConversationIdByNumber($normalized);
    }

    private function normalizeNumber(mixed $waNumber): ?string
    {
        if ($waNumber === null) {
            return null;
        }

        $number = (string) $waNumber;
        if ($number === '') {
            return null;
        }

        $config = $this->settings->get();
        $defaultCountry = $config['default_country_code'] ?? '';

        $formatted = PhoneNumberFormatter::formatPhoneNumber($number, [
            'default_country_code' => $defaultCountry,
        ]);

        if ($formatted !== null) {
            return $formatted;
        }

        $digits = preg_replace('/\D+/', '', $number);
        if ($digits === '') {
            return null;
        }

        return '+' . ltrim($digits, '+');
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractBody(array $message): ?string
    {
        $type = $message['type'] ?? '';

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

        if (isset($message['text']['body'])) {
            return (string) $message['text']['body'];
        }

        return null;
    }

    private function resolveTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp <= 0) {
                return null;
            }

            return date('Y-m-d H:i:s', $timestamp);
        }

        try {
            $date = new DateTimeImmutable((string) $value);

            return $date->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function truncatePreview(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $body = trim($body);
        if ($body === '') {
            return null;
        }

        if (mb_strlen($body) <= 160) {
            return $body;
        }

        return mb_substr($body, 0, 157) . '…';
    }

    private function formatIsoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value);

            return $date->format(DATE_ATOM);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
