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

        $hasInbound = $this->repository->hasInboundMessages($conversationId);
        $messages = $this->repository->fetchMessages($conversationId, $limit);
        $this->repository->markConversationAsRead($conversationId);

        $mappedMessages = [];
        foreach ($messages as $message) {
            $media = $this->extractMediaDetails($message);
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
                'media_url' => $media['media_url'],
                'media_filename' => $media['media_filename'],
                'media_caption' => $media['media_caption'],
                'media_mime' => $media['media_mime'],
                'media_id' => $media['media_id'],
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
            'has_inbound' => $hasInbound,
            'messages' => $mappedMessages,
        ];
    }

    public function hasInboundMessages(int $conversationId): bool
    {
        return $this->repository->hasInboundMessages($conversationId);
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

        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = (int) ($row['id'] ?? 0);
        }
        $presenceMap = $this->loadAgentPresenceMap($userIds);

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

            $agentId = (int) ($row['id'] ?? 0);
            $agents[] = [
                'id' => $agentId,
                'name' => $name,
                'email' => $row['email'] ?? null,
                'role_id' => isset($row['role_id']) ? (int) $row['role_id'] : null,
                'role_name' => $row['role_name'] ?? null,
                'presence_status' => $presenceMap[$agentId] ?? 'available',
            ];
        }

        return $agents;
    }

    public function getAgentPresence(int $userId): string
    {
        if ($userId <= 0) {
            return 'available';
        }

        $map = $this->loadAgentPresenceMap([$userId]);

        return $map[$userId] ?? 'available';
    }

    public function setAgentPresence(int $userId, string $status): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $status = $this->normalizePresenceStatus($status);

        try {
            $stmt = $this->repository->getPdo()->prepare(
                'INSERT INTO whatsapp_agent_presence (user_id, status, updated_at)
                 VALUES (:user_id, :status, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':status' => $status,
            ]);

            return true;
        } catch (Throwable $exception) {
            return false;
        }
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

    /**
     * @param array<int, int> $userIds
     * @return array<int, string>
     */
    private function loadAgentPresenceMap(array $userIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        try {
            $stmt = $this->repository->getPdo()->prepare(
                'SELECT user_id, status
                 FROM whatsapp_agent_presence
                 WHERE user_id IN (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $map[$id] = $this->normalizePresenceStatus((string) ($row['status'] ?? 'available'));
        }

        return $map;
    }

    private function normalizePresenceStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['available', 'away', 'offline'], true)) {
            return $status;
        }

        return 'available';
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

    public function hasInboundInRecentHours(string $waNumber, int $hours = 24): bool
    {
        $normalized = $this->normalizeNumber($waNumber);
        if ($normalized === null) {
            return false;
        }

        $hours = max(1, min(168, $hours));
        $since = (new DateTimeImmutable('-' . $hours . ' hours'))->format('Y-m-d H:i:s');

        return $this->repository->hasInboundSince($normalized, $since);
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

    /**
     * @param array<string, mixed> $message
     * @return array{media_url:?string,media_filename:?string,media_caption:?string,media_mime:?string,media_id:?string}
     */
    private function extractMediaDetails(array $message): array
    {
        $type = isset($message['message_type']) ? (string) $message['message_type'] : '';
        $payload = $this->decodePayload($message['raw_payload'] ?? null);

        $details = [
            'media_url' => null,
            'media_filename' => null,
            'media_caption' => null,
            'media_mime' => null,
            'media_id' => null,
        ];

        if ($type === 'image' && isset($payload['image']) && is_array($payload['image'])) {
            $image = $payload['image'];
            $details['media_url'] = isset($image['link']) ? (string) $image['link'] : (isset($image['url']) ? (string) $image['url'] : null);
            $details['media_caption'] = isset($image['caption']) ? (string) $image['caption'] : null;
            $details['media_mime'] = isset($image['mime_type']) ? (string) $image['mime_type'] : null;
            $details['media_id'] = isset($image['id']) ? (string) $image['id'] : null;
        } elseif ($type === 'document' && isset($payload['document']) && is_array($payload['document'])) {
            $document = $payload['document'];
            $details['media_url'] = isset($document['link']) ? (string) $document['link'] : (isset($document['url']) ? (string) $document['url'] : null);
            $details['media_caption'] = isset($document['caption']) ? (string) $document['caption'] : null;
            $details['media_filename'] = isset($document['filename']) ? (string) $document['filename'] : null;
            $details['media_mime'] = isset($document['mime_type']) ? (string) $document['mime_type'] : null;
            $details['media_id'] = isset($document['id']) ? (string) $document['id'] : null;
        } elseif ($type === 'audio' && isset($payload['audio']) && is_array($payload['audio'])) {
            $audio = $payload['audio'];
            $details['media_url'] = isset($audio['link']) ? (string) $audio['link'] : (isset($audio['url']) ? (string) $audio['url'] : null);
            $details['media_mime'] = isset($audio['mime_type']) ? (string) $audio['mime_type'] : null;
            $details['media_id'] = isset($audio['id']) ? (string) $audio['id'] : null;
        }

        return $details;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
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
