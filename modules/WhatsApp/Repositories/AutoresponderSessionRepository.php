<?php

namespace Modules\WhatsApp\Repositories;

use PDO;

class AutoresponderSessionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByConversationId(int $conversationId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_autoresponder_sessions WHERE conversation_id = :id LIMIT 1');
        $stmt->execute([':id' => $conversationId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row['context'] = $this->decodeJson($row['context'] ?? null);
        $row['last_payload'] = $this->decodeJson($row['last_payload'] ?? null);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $waNumber): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_autoresponder_sessions WHERE wa_number = :number LIMIT 1');
        $stmt->execute([':number' => $waNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row['context'] = $this->decodeJson($row['context'] ?? null);
        $row['last_payload'] = $this->decodeJson($row['last_payload'] ?? null);

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(int $conversationId, string $waNumber, array $data): void
    {
        $sql = 'INSERT INTO whatsapp_autoresponder_sessions '
            . '(conversation_id, wa_number, scenario_id, node_id, awaiting, context, last_payload, last_interaction_at) '
            . 'VALUES (:conversation_id, :wa_number, :scenario_id, :node_id, :awaiting, :context, :last_payload, :last_interaction_at) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'wa_number = VALUES(wa_number), '
            . 'scenario_id = VALUES(scenario_id), '
            . 'node_id = VALUES(node_id), '
            . 'awaiting = VALUES(awaiting), '
            . 'context = VALUES(context), '
            . 'last_payload = VALUES(last_payload), '
            . 'last_interaction_at = VALUES(last_interaction_at)';

        $payload = [
            ':conversation_id' => $conversationId,
            ':wa_number' => $waNumber,
            ':scenario_id' => $data['scenario_id'] ?? null,
            ':node_id' => $data['node_id'] ?? null,
            ':awaiting' => $data['awaiting'] ?? null,
            ':context' => $this->encodeJson($data['context'] ?? []),
            ':last_payload' => $this->encodeJson($data['last_payload'] ?? null),
            ':last_interaction_at' => $data['last_interaction_at'] ?? date('Y-m-d H:i:s'),
        ];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($payload);
    }

    public function deleteByConversationId(int $conversationId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM whatsapp_autoresponder_sessions WHERE conversation_id = :id');
        $stmt->execute([':id' => $conversationId]);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decodeJson($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param mixed $value
     */
    private function encodeJson($value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }
}

