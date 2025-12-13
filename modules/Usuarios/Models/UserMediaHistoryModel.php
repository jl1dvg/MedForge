<?php

namespace Modules\Usuarios\Models;

use PDO;

class UserMediaHistoryModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(int $userId, string $mediaType, string $action, array $payload = []): void
    {
        $version = $this->nextVersion($userId, $mediaType);
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_media_history (user_id, media_type, version, action, path, mime, size, hash, previous_path, status, acted_by, acted_at)
             VALUES (:user_id, :media_type, :version, :action, :path, :mime, :size, :hash, :previous_path, :status, :acted_by, :acted_at)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'media_type' => $mediaType,
            'version' => $version,
            'action' => $action,
            'path' => $payload['path'] ?? null,
            'mime' => $payload['mime'] ?? null,
            'size' => $payload['size'] ?? null,
            'hash' => $payload['hash'] ?? null,
            'previous_path' => $payload['previous_path'] ?? null,
            'status' => $payload['status'] ?? null,
            'acted_by' => $payload['acted_by'] ?? null,
            'acted_at' => $payload['acted_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_media_history WHERE user_id = :user_id ORDER BY acted_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function restorableVersions(int $userId, string $mediaType, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT version, path, mime, size, hash, action, acted_at, acted_by
             FROM user_media_history
             WHERE user_id = :user_id AND media_type = :media_type AND path IS NOT NULL
             ORDER BY version DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':media_type', $mediaType, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findVersion(int $userId, string $mediaType, int $version): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_media_history WHERE user_id = :user_id AND media_type = :media_type AND version = :version LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'media_type' => $mediaType,
            'version' => $version,
        ]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }

    private function nextVersion(int $userId, string $mediaType): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version), 0) FROM user_media_history WHERE user_id = :user_id AND media_type = :media_type');
        $stmt->execute([
            'user_id' => $userId,
            'media_type' => $mediaType,
        ]);

        return (int) $stmt->fetchColumn() + 1;
    }
}
