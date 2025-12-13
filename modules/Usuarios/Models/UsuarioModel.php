<?php

namespace Modules\Usuarios\Models;

use PDO;

class UsuarioModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $sql = 'SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.username';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = 'SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function create(array $data): int
    {
        $payload = $this->preparePayload($data, true);
        $columns = array_keys($payload);
        $placeholders = array_map(static fn($column) => ':' . $column, $columns);

        $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $payload = $this->preparePayload($data, false);
        if (empty($payload)) {
            return false;
        }

        $set = [];
        foreach ($payload as $column => $value) {
            $set[] = $column . ' = :' . $column;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
        $payload['id'] = $id;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($payload);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countByRole(int $roleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = :role_id');
        $stmt->execute(['role_id' => $roleId]);
        return (int) $stmt->fetchColumn();
    }

    public function findPotentialDuplicates(string $firstName, string $lastName, ?string $birthDate, ?int $excludeId = null): array
    {
        if ($birthDate === null || $birthDate === '') {
            return [];
        }

        $sql = 'SELECT id, username, first_name, last_name, birth_date FROM users WHERE first_name = :first_name AND last_name = :last_name AND birth_date = :birth_date';

        $params = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $birthDate,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function preparePayload(array $data, bool $isCreate): array
    {
        $defaults = [
            'username' => '',
            'password' => null,
            'email' => '',
            'birth_date' => null,
            'is_subscribed' => 0,
            'is_approved' => 0,
            'nombre' => '',
            'cedula' => '',
            'national_id_encrypted' => null,
            'passport_number_encrypted' => null,
            'registro' => '',
            'sede' => '',
            'firma' => null,
            'firma_mime' => null,
            'firma_size' => null,
            'firma_hash' => null,
            'firma_created_at' => null,
            'firma_created_by' => null,
            'firma_updated_at' => null,
            'firma_updated_by' => null,
            'firma_verified_at' => null,
            'firma_verified_by' => null,
            'firma_deleted_at' => null,
            'firma_deleted_by' => null,
            'seal_status' => 'pending',
            'seal_status_updated_at' => null,
            'seal_status_updated_by' => null,
            'profile_photo' => null,
            'signature_path' => null,
            'signature_mime' => null,
            'signature_size' => null,
            'signature_hash' => null,
            'signature_created_at' => null,
            'signature_created_by' => null,
            'signature_updated_at' => null,
            'signature_updated_by' => null,
            'signature_verified_at' => null,
            'signature_verified_by' => null,
            'signature_deleted_at' => null,
            'signature_deleted_by' => null,
            'signature_status' => 'pending',
            'signature_status_updated_at' => null,
            'signature_status_updated_by' => null,
            'especialidad' => '',
            'subespecialidad' => '',
            'permisos' => '[]',
            'role_id' => null,
        ];

        $payload = [];
        foreach ($defaults as $column => $default) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            } elseif ($isCreate && $default !== null) {
                $payload[$column] = $default;
            }
        }

        // Remover password vacío en actualizaciones
        if (!$isCreate && array_key_exists('password', $payload) && !$payload['password']) {
            unset($payload['password']);
        }

        return $payload;
    }
}
