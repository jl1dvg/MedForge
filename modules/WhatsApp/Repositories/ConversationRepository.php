<?php

namespace Modules\WhatsApp\Repositories;

use PDO;
use PDOException;

class ConversationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function upsertConversation(string $waNumber, array $attributes = []): int
    {
        $waNumber = trim($waNumber);
        if ($waNumber === '') {
            throw new \InvalidArgumentException('El número de WhatsApp no puede estar vacío.');
        }

        $stmt = $this->pdo->prepare('SELECT id, display_name, patient_hc_number, patient_full_name FROM whatsapp_conversations WHERE wa_number = :number LIMIT 1');
        $stmt->execute([':number' => $waNumber]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            $updates = [];
            $params = [':id' => (int) $existing['id']];

            foreach (['display_name', 'patient_hc_number', 'patient_full_name'] as $field) {
                if (!isset($attributes[$field])) {
                    continue;
                }

                $value = $attributes[$field];
                if ($value === null || $value === '') {
                    continue;
                }

                if ((string) $existing[$field] === (string) $value) {
                    continue;
                }

                $updates[] = $field . ' = :' . $field;
                $params[':' . $field] = $value;
            }

            if (!empty($updates)) {
                $updates[] = 'updated_at = NOW()';
                $sql = 'UPDATE whatsapp_conversations SET ' . implode(', ', $updates) . ' WHERE id = :id';
                $updateStmt = $this->pdo->prepare($sql);
                $updateStmt->execute($params);
            }

            return (int) $existing['id'];
        }

        $insert = $this->pdo->prepare('INSERT INTO whatsapp_conversations (wa_number, display_name, patient_hc_number, patient_full_name, created_at, updated_at) VALUES (:number, :display_name, :patient_hc_number, :patient_full_name, NOW(), NOW())');

        try {
            $insert->execute([
                ':number' => $waNumber,
                ':display_name' => $attributes['display_name'] ?? null,
                ':patient_hc_number' => $attributes['patient_hc_number'] ?? null,
                ':patient_full_name' => $attributes['patient_full_name'] ?? null,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            if ((int) $exception->getCode() !== 23000) {
                throw $exception;
            }

            $stmt = $this->pdo->prepare('SELECT id FROM whatsapp_conversations WHERE wa_number = :number LIMIT 1');
            $stmt->execute([':number' => $waNumber]);
            $id = $stmt->fetchColumn();

            if ($id === false) {
                throw $exception;
            }

            return (int) $id;
        }
    }

    public function messageExists(string $waMessageId): bool
    {
        $waMessageId = trim($waMessageId);
        if ($waMessageId === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM whatsapp_messages WHERE wa_message_id = :id LIMIT 1');
        $stmt->execute([':id' => $waMessageId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMessage(int $conversationId, array $data): void
    {
        $sql = 'INSERT INTO whatsapp_messages (conversation_id, wa_message_id, direction, message_type, body, raw_payload, status, message_timestamp, sent_at, delivered_at, read_at, created_at, updated_at) VALUES (:conversation_id, :wa_message_id, :direction, :message_type, :body, :raw_payload, :status, :message_timestamp, :sent_at, :delivered_at, :read_at, NOW(), NOW())';
        $stmt = $this->pdo->prepare($sql);

        $rawPayload = null;
        if (isset($data['raw_payload'])) {
            $encoded = json_encode($data['raw_payload'], JSON_UNESCAPED_UNICODE);
            $rawPayload = $encoded === false ? null : $encoded;
        }

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':wa_message_id' => $data['wa_message_id'] ?? null,
            ':direction' => $data['direction'] ?? 'inbound',
            ':message_type' => $data['message_type'] ?? 'text',
            ':body' => $data['body'] ?? null,
            ':raw_payload' => $rawPayload,
            ':status' => $data['status'] ?? null,
            ':message_timestamp' => $data['message_timestamp'] ?? null,
            ':sent_at' => $data['sent_at'] ?? null,
            ':delivered_at' => $data['delivered_at'] ?? null,
            ':read_at' => $data['read_at'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function touchConversation(int $conversationId, array $data): void
    {
        $fields = [];
        $params = [':id' => $conversationId];

        if (isset($data['last_message_at'])) {
            $fields[] = 'last_message_at = :last_message_at';
            $params[':last_message_at'] = $data['last_message_at'];
        }

        if (isset($data['last_message_direction'])) {
            $fields[] = 'last_message_direction = :last_message_direction';
            $params[':last_message_direction'] = $data['last_message_direction'];
        }

        if (array_key_exists('last_message_preview', $data)) {
            $fields[] = 'last_message_preview = :last_message_preview';
            $params[':last_message_preview'] = $data['last_message_preview'];
        }

        if (isset($data['last_message_type'])) {
            $fields[] = 'last_message_type = :last_message_type';
            $params[':last_message_type'] = $data['last_message_type'];
        }

        if (!empty($data['increment_unread'])) {
            $fields[] = 'unread_count = unread_count + :increment_unread';
            $params[':increment_unread'] = (int) $data['increment_unread'];
        } elseif (isset($data['set_unread'])) {
            $fields[] = 'unread_count = :set_unread';
            $params[':set_unread'] = (int) $data['set_unread'];
        }

        if (empty($fields)) {
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE whatsapp_conversations SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function markConversationAsRead(int $conversationId): void
    {
        $this->pdo->prepare('UPDATE whatsapp_conversations SET unread_count = 0, updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $conversationId]);

        $this->pdo->prepare('UPDATE whatsapp_messages SET read_at = COALESCE(read_at, NOW()) WHERE conversation_id = :id AND direction = "inbound" AND read_at IS NULL')
            ->execute([':id' => $conversationId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(string $search = '', int $limit = 25): array
    {
        $search = trim($search);
        $sql = 'SELECT id, wa_number, display_name, patient_hc_number, patient_full_name, last_message_at, last_message_direction, last_message_type, last_message_preview, unread_count, created_at, updated_at FROM whatsapp_conversations';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE wa_number LIKE :search OR display_name LIKE :search OR patient_full_name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC, id DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function findConversationById(int $conversationId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, wa_number, display_name, patient_hc_number, patient_full_name, last_message_at, last_message_direction, last_message_type, last_message_preview, unread_count, created_at, updated_at FROM whatsapp_conversations WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $conversationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchMessages(int $conversationId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT id, wa_message_id, direction, message_type, body, status, message_timestamp, sent_at, delivered_at, read_at, created_at, updated_at FROM whatsapp_messages WHERE conversation_id = :id ORDER BY COALESCE(message_timestamp, created_at) DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        return array_reverse($rows);
    }

    public function findConversationIdByNumber(string $waNumber): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM whatsapp_conversations WHERE wa_number = :number LIMIT 1');
        $stmt->execute([':number' => $waNumber]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
