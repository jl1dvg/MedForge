<?php

namespace Modules\Flowmaker\Repositories;

use PDO;
use PDOException;

class FlowRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, description, created_at, updated_at FROM flowmaker_flows ORDER BY id DESC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM flowmaker_flows WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC);

        return $flow === false ? null : $flow;
    }

    public function findByKey(string $flowKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM flowmaker_flows WHERE flow_key = :flow_key LIMIT 1');
        $stmt->execute([':flow_key' => $flowKey]);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC);

        return $flow === false ? null : $flow;
    }

    /**
     * @param array{name:string,description?:?string,flow_key?:?string,created_by?:?int,updated_by?:?int} $payload
     */
    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO flowmaker_flows (flow_key, name, description, created_by, updated_by)
             VALUES (:flow_key, :name, :description, :created_by, :updated_by)'
        );
        $stmt->execute([
            ':flow_key' => $payload['flow_key'] ?? null,
            ':name' => $payload['name'],
            ':description' => $payload['description'],
            ':created_by' => $payload['created_by'],
            ':updated_by' => $payload['updated_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{name?:string,description?:?string,updated_by?:?int} $payload
     */
    public function update(int $id, array $payload): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('flow_key', $payload)) {
            $fields[] = 'flow_key = :flow_key';
            $params[':flow_key'] = $payload['flow_key'];
        }

        if (array_key_exists('name', $payload)) {
            $fields[] = 'name = :name';
            $params[':name'] = $payload['name'];
        }

        if (array_key_exists('description', $payload)) {
            $fields[] = 'description = :description';
            $params[':description'] = $payload['description'];
        }

        if (array_key_exists('updated_by', $payload)) {
            $fields[] = 'updated_by = :updated_by';
            $params[':updated_by'] = $payload['updated_by'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE flowmaker_flows SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM flowmaker_flows WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Persists builder payload as JSON.
     *
     * @param array<string,mixed> $data
     */
    public function updateFlowData(int $id, array $data, ?int $userId = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE flowmaker_flows SET flow_data = :flow_data, updated_by = :updated_by, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':flow_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':updated_by' => $userId,
            ':id' => $id,
        ]);
    }

    /**
     * Ensures there is at least one default flow to work with.
     */
    public function ensureDefault(string $name = 'Flujo principal'): array
    {
        $stmt = $this->pdo->query('SELECT * FROM flowmaker_flows ORDER BY id ASC LIMIT 1');
        $existing = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($existing !== false) {
            return $existing;
        }

        $id = $this->create([
            'flow_key' => 'default',
            'name' => $name,
            'description' => 'Flujo creado automáticamente',
            'created_by' => $_SESSION['user_id'] ?? null,
            'updated_by' => $_SESSION['user_id'] ?? null,
        ]);

        return $this->find($id) ?? [
            'id' => $id,
            'name' => $name,
            'description' => 'Flujo creado automáticamente',
            'flow_data' => null,
        ];
    }

    public function getOrCreateAutoresponderFlow(): array
    {
        $existing = $this->findByKey('whatsapp_autoresponder');
        if ($existing) {
            return $existing;
        }

        $id = $this->create([
            'flow_key' => 'whatsapp_autoresponder',
            'name' => 'Autorespondedor WhatsApp',
            'description' => 'Flujo utilizado para el autorespondedor de WhatsApp',
            'created_by' => $_SESSION['user_id'] ?? null,
            'updated_by' => $_SESSION['user_id'] ?? null,
        ]);

        return $this->find($id) ?? $this->ensureDefault();
    }
}
