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

    public function getPdo(): PDO
    {
        return $this->pdo;
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

    public function hasInboundMessages(int $conversationId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM whatsapp_messages WHERE conversation_id = :id AND direction = "inbound" LIMIT 1'
        );
        $stmt->execute([':id' => $conversationId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(string $search = '', int $limit = 25): array
    {
        $search = trim($search);
        $sql = 'SELECT c.id, c.wa_number, c.display_name, c.patient_hc_number, c.patient_full_name, c.last_message_at, c.last_message_direction, c.last_message_type, c.last_message_preview, c.needs_human, c.handoff_notes, c.handoff_role_id, c.assigned_user_id, c.assigned_at, c.handoff_requested_at, c.unread_count, c.created_at, c.updated_at, ' .
            'COALESCE(NULLIF(TRIM(CONCAT(u.first_name, " ", u.last_name)), ""), NULLIF(u.nombre, ""), u.username) AS assigned_user_name, r.name AS handoff_role_name ' .
            'FROM whatsapp_conversations c ' .
            'LEFT JOIN users u ON u.id = c.assigned_user_id ' .
            'LEFT JOIN roles r ON r.id = c.handoff_role_id';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE wa_number LIKE :search OR display_name LIKE :search OR patient_full_name LIKE :search OR patient_hc_number LIKE :search OR last_message_preview LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY COALESCE(c.last_message_at, c.updated_at, c.created_at) DESC, c.id DESC LIMIT :limit';
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
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.wa_number, c.display_name, c.patient_hc_number, c.patient_full_name, c.last_message_at, c.last_message_direction, c.last_message_type, c.last_message_preview, c.needs_human, c.handoff_notes, c.handoff_role_id, c.assigned_user_id, c.assigned_at, c.handoff_requested_at, c.unread_count, c.created_at, c.updated_at, ' .
            'COALESCE(NULLIF(TRIM(CONCAT(u.first_name, " ", u.last_name)), ""), NULLIF(u.nombre, ""), u.username) AS assigned_user_name, r.name AS handoff_role_name ' .
            'FROM whatsapp_conversations c ' .
            'LEFT JOIN users u ON u.id = c.assigned_user_id ' .
            'LEFT JOIN roles r ON r.id = c.handoff_role_id ' .
            'WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $conversationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchMessages(int $conversationId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT id, wa_message_id, direction, message_type, body, raw_payload, status, message_timestamp, sent_at, delivered_at, read_at, created_at, updated_at FROM whatsapp_messages WHERE conversation_id = :id ORDER BY COALESCE(message_timestamp, created_at) DESC, id DESC LIMIT :limit');
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

    public function setHandoffFlag(int $conversationId, bool $needsHuman, ?string $notes = null, ?int $roleId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_conversations SET needs_human = :needs_human, handoff_notes = :notes, handoff_role_id = :role_id, ' .
            'assigned_user_id = CASE WHEN :needs_human = 1 THEN NULL ELSE assigned_user_id END, ' .
            'assigned_at = CASE WHEN :needs_human = 1 THEN NULL ELSE assigned_at END, ' .
            'handoff_requested_at = CASE WHEN :needs_human = 1 THEN NOW() ELSE handoff_requested_at END, ' .
            'updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':needs_human' => $needsHuman ? 1 : 0,
            ':notes' => $notes !== null && $notes !== '' ? mb_substr($notes, 0, 255) : null,
            ':role_id' => $roleId,
            ':id' => $conversationId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateHandoffDetails(int $conversationId, ?string $notes = null, ?int $roleId = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_conversations SET needs_human = 1, handoff_notes = :notes, handoff_role_id = :role_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':notes' => $notes !== null && $notes !== '' ? mb_substr($notes, 0, 255) : null,
            ':role_id' => $roleId,
            ':id' => $conversationId,
        ]);
    }

    public function assignConversation(int $conversationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_conversations SET assigned_user_id = :user_id, assigned_at = COALESCE(assigned_at, NOW()), needs_human = 1, updated_at = NOW() ' .
            'WHERE id = :id AND (assigned_user_id IS NULL OR assigned_user_id = :user_id)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':id' => $conversationId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function transferConversation(int $conversationId, int $userId, ?string $notes = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_conversations SET assigned_user_id = :user_id, assigned_at = NOW(), needs_human = 1, handoff_notes = :notes, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':notes' => $notes !== null && $notes !== '' ? mb_substr($notes, 0, 255) : null,
            ':id' => $conversationId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function clearHandoff(int $conversationId): void
    {
        $this->pdo->prepare(
            'UPDATE whatsapp_conversations SET needs_human = 0, handoff_notes = NULL, handoff_role_id = NULL, assigned_user_id = NULL, assigned_at = NULL, updated_at = NOW() WHERE id = :id'
        )->execute([':id' => $conversationId]);
    }

    public function closeConversation(int $conversationId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_conversations SET needs_human = 0, handoff_notes = NULL, handoff_role_id = NULL, assigned_user_id = NULL, assigned_at = NULL, unread_count = 0, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $conversationId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteConversation(int $conversationId): bool
    {
        if ($conversationId <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('SELECT wa_number FROM whatsapp_conversations WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $conversationId]);
            $waNumber = $stmt->fetchColumn();

            if ($waNumber === false) {
                $this->pdo->rollBack();

                return false;
            }

            $this->pdo->prepare(
                'DELETE FROM whatsapp_handoff_events WHERE handoff_id IN (SELECT id FROM whatsapp_handoffs WHERE conversation_id = :id)'
            )->execute([':id' => $conversationId]);

            $this->pdo->prepare('DELETE FROM whatsapp_handoffs WHERE conversation_id = :id')
                ->execute([':id' => $conversationId]);

            $this->pdo->prepare('DELETE FROM whatsapp_inbox_messages WHERE wa_number = :wa_number')
                ->execute([':wa_number' => $waNumber]);

            $stmt = $this->pdo->prepare('DELETE FROM whatsapp_conversations WHERE id = :id');
            $stmt->execute([':id' => $conversationId]);
            $deleted = $stmt->rowCount() > 0;

            $this->pdo->commit();

            return $deleted;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    public function updateMessageStatus(string $waMessageId, string $status, ?string $timestamp = null): void
    {
        $waMessageId = trim($waMessageId);
        if ($waMessageId === '') {
            return;
        }

        $fields = ['status = :status'];
        $params = [
            ':status' => $status,
            ':wa_message_id' => $waMessageId,
        ];

        if ($timestamp !== null && $timestamp !== '') {
            if ($status === 'sent') {
                $fields[] = 'sent_at = COALESCE(sent_at, :timestamp)';
            } elseif ($status === 'delivered') {
                $fields[] = 'delivered_at = COALESCE(delivered_at, :timestamp)';
            } elseif ($status === 'read') {
                $fields[] = 'read_at = COALESCE(read_at, :timestamp)';
            }
            $params[':timestamp'] = $timestamp;
        }

        $fields[] = 'updated_at = NOW()';

        $sql = 'UPDATE whatsapp_messages SET ' . implode(', ', $fields) . ' WHERE wa_message_id = :wa_message_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
