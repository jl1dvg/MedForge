<?php

namespace Modules\CiveExtension\Models;

use PDO;

class HealthCheckModel
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{endpoint:string, method:string, status_code:int|null, success:bool, latency_ms:int|null, error_message:?string, response_excerpt:?string} $payload
     */
    public function store(array $payload): void
    {
        $sql = 'INSERT INTO cive_extension_health_checks (endpoint, method, status_code, success, latency_ms, error_message, response_excerpt) VALUES (:endpoint, :method, :status_code, :success, :latency_ms, :error_message, :response_excerpt)';
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':endpoint' => $payload['endpoint'],
            ':method' => $payload['method'],
            ':status_code' => $payload['status_code'],
            ':success' => $payload['success'] ? 1 : 0,
            ':latency_ms' => $payload['latency_ms'],
            ':error_message' => $payload['error_message'],
            ':response_excerpt' => $payload['response_excerpt'],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 20): array
    {
        $sql = 'SELECT id, endpoint, method, status_code, success, latency_ms, error_message, response_excerpt, created_at FROM cive_extension_health_checks ORDER BY id DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
