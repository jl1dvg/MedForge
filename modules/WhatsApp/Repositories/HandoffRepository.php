<?php

namespace Modules\WhatsApp\Repositories;

use PDO;

class HandoffRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $handoffId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_handoffs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $handoffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findActiveByConversation(int $conversationId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM whatsapp_handoffs WHERE conversation_id = :id AND status IN (\'queued\', \'assigned\') ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':id' => $conversationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO whatsapp_handoffs (conversation_id, wa_number, status, priority, topic, handoff_role_id, assigned_agent_id, assigned_at, assigned_until, queued_at, last_activity_at, notes, created_at, updated_at) ' .
            'VALUES (:conversation_id, :wa_number, :status, :priority, :topic, :handoff_role_id, :assigned_agent_id, :assigned_at, :assigned_until, :queued_at, :last_activity_at, :notes, NOW(), NOW())'
        );

        $stmt->execute([
            ':conversation_id' => $data['conversation_id'],
            ':wa_number' => $data['wa_number'],
            ':status' => $data['status'] ?? 'queued',
            ':priority' => $data['priority'] ?? 'normal',
            ':topic' => $data['topic'] ?? null,
            ':handoff_role_id' => $data['handoff_role_id'] ?? null,
            ':assigned_agent_id' => $data['assigned_agent_id'] ?? null,
            ':assigned_at' => $data['assigned_at'] ?? null,
            ':assigned_until' => $data['assigned_until'] ?? null,
            ':queued_at' => $data['queued_at'] ?? date('Y-m-d H:i:s'),
            ':last_activity_at' => $data['last_activity_at'] ?? ($data['queued_at'] ?? date('Y-m-d H:i:s')),
            ':notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $handoffId, array $fields): bool
    {
        if (empty($fields)) {
            return false;
        }

        $set = [];
        $params = [':id' => $handoffId];

        foreach ($fields as $key => $value) {
            $set[] = $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }

        $set[] = 'updated_at = NOW()';
        $sql = 'UPDATE whatsapp_handoffs SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function assign(int $handoffId, int $agentId, string $assignedUntil): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_handoffs SET status = \'assigned\', assigned_agent_id = :agent_id, assigned_at = NOW(), assigned_until = :assigned_until, last_activity_at = NOW(), updated_at = NOW() ' .
            'WHERE id = :id AND status = \'queued\' AND assigned_agent_id IS NULL'
        );
        $stmt->execute([
            ':agent_id' => $agentId,
            ':assigned_until' => $assignedUntil,
            ':id' => $handoffId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function transfer(int $handoffId, int $agentId, string $assignedUntil, ?string $note = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_handoffs SET status = \'assigned\', assigned_agent_id = :agent_id, assigned_at = NOW(), assigned_until = :assigned_until, notes = :notes, last_activity_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':agent_id' => $agentId,
            ':assigned_until' => $assignedUntil,
            ':notes' => $note,
            ':id' => $handoffId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markResolved(int $handoffId, ?string $note = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_handoffs SET status = \'resolved\', assigned_until = NULL, notes = :notes, last_activity_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':notes' => $note,
            ':id' => $handoffId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findExpired(string $cutoff): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM whatsapp_handoffs WHERE status = \'assigned\' AND assigned_until IS NOT NULL AND assigned_until <= :cutoff'
        );
        $stmt->execute([':cutoff' => $cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function requeue(int $handoffId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_handoffs SET status = \'queued\', assigned_agent_id = NULL, assigned_at = NULL, assigned_until = NULL, queued_at = NOW(), last_activity_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $handoffId]);

        return $stmt->rowCount() > 0;
    }

    public function insertEvent(int $handoffId, string $eventType, ?int $actorUserId = null, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO whatsapp_handoff_events (handoff_id, event_type, actor_user_id, notes, created_at) VALUES (:handoff_id, :event_type, :actor_user_id, :notes, NOW())'
        );
        $stmt->execute([
            ':handoff_id' => $handoffId,
            ':event_type' => $eventType,
            ':actor_user_id' => $actorUserId,
            ':notes' => $notes,
        ]);
    }
}
