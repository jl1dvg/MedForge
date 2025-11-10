<?php

namespace Modules\WhatsApp\Repositories;

use PDO;
use PDOException;

class InboxRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function recordIncoming(string $number, string $type, string $body, ?string $messageId, array $payload = []): void
    {
        $this->store('incoming', $number, $type, $body, $messageId, $payload);
    }

    public function recordOutgoing(string $number, string $type, string $body, array $payload = []): void
    {
        $this->store('outgoing', $number, $type, $body, null, $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_inbox_messages ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchSince(int $sinceId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_inbox_messages WHERE id > :since ORDER BY id ASC LIMIT :limit');
        $stmt->bindValue(':since', $sinceId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->normalizeRows($rows);
    }

    private function store(string $direction, string $number, string $type, string $body, ?string $messageId, array $payload): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO whatsapp_inbox_messages (wa_number, direction, message_type, message_body, message_id, payload)
                 VALUES (:number, :direction, :type, :body, :message_id, :payload)'
            );

            $encoded = empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                ':number' => $number,
                ':direction' => $direction,
                ':type' => $type,
                ':body' => $body,
                ':message_id' => $messageId,
                ':payload' => $encoded,
            ]);
        } catch (PDOException $exception) {
            error_log('No fue posible registrar el mensaje de WhatsApp: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            if (isset($row['payload']) && is_string($row['payload']) && $row['payload'] !== '') {
                $decoded = json_decode($row['payload'], true);
                if (is_array($decoded)) {
                    $row['payload'] = $decoded;
                }
            }

            return $row;
        }, $rows);
    }
}
