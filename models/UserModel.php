<?php

namespace Models;

use Core\Permissions;
use PDO;

class UserModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllUsers()
    {
        $stmt = $this->db->query("SELECT * FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($data)
    {
        $permissions = $this->encodePermissions($data['permisos'] ?? []);
        $roleId = $this->normalizeRoleId($data['role_id'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, email, is_subscribed, is_approved, nombre, cedula, registro, sede, firma, especialidad, subespecialidad, permisos, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['email'],
            $data['is_subscribed'],
            $data['is_approved'],
            $data['nombre'],
            $data['cedula'],
            $data['registro'],
            $data['sede'],
            $data['firma'],
            $data['especialidad'],
            $data['subespecialidad'],
            $permissions,
            $roleId,
        ]);
    }

    public function updateUser($id, $data)
    {
        // Normalizar flags por si vienen como string/checkbox
        $is_subscribed = isset($data['is_subscribed']) ? (int)!!$data['is_subscribed'] : 0;
        $is_approved = isset($data['is_approved']) ? (int)!!$data['is_approved'] : 0;
        $encodedPermissions = $this->encodePermissions($data['permisos'] ?? []);
        $roleId = $this->normalizeRoleId($data['role_id'] ?? null);

        $fields = [
            'username = ?',
            'email = ?',
            'is_subscribed = ?',
            'is_approved = ?',
            'nombre = ?',
            'cedula = ?',
            'registro = ?',
            'sede = ?',
            'especialidad = ?',
            'subespecialidad = ?',
            'permisos = ?',
            'role_id = ?',
        ];

        $params = [
            $data['username'],
            $data['email'],
            $is_subscribed,
            $is_approved,
            $data['nombre'],
            $data['cedula'],
            $data['registro'],
            $data['sede'],
            $data['especialidad'],
            $data['subespecialidad'],
            $encodedPermissions,
            $roleId,
        ];

        // Incluir firma solo si viene en $data (permite setear vacía para borrar)
        if (array_key_exists('firma', $data)) {
            $fields[] = 'firma = ?';
            $params[] = $data['firma'];
        }

        // Incluir password solo si viene en $data (ya hasheado en el controller)
        if (isset($data['password']) && $data['password'] !== '') {
            array_splice($fields, 1, 0, 'password = ?'); // lo ponemos después de username para mantener orden lógico
            array_splice($params, 1, 0, $data['password']);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $params[] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    private function encodePermissions(mixed $permisos): string
    {
        $normalized = Permissions::normalize($permisos);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    private function normalizeRoleId(mixed $roleId): ?int
    {
        if ($roleId === null || $roleId === '') {
            return null;
        }

        $value = (int) $roleId;

        return $value > 0 ? $value : null;
    }
}