<?php
require_once __DIR__ . '/../bootstrap.php';

use Core\Auth;
use Core\Permissions;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.password, u.permisos, u.role_id, r.permissions AS role_permissions
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username
             LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $permissions = Permissions::merge($user['permisos'] ?? [], $user['role_permissions'] ?? []);
            $roleId = isset($user['role_id']) ? (int) $user['role_id'] : null;

            Auth::login($user['id'], $permissions, $roleId);
            $_SESSION['username'] = $user['username'] ?? $username;

            header('Location: /dashboard');
            exit;
        } else {
            echo 'Credenciales incorrectas';
        }
    } catch (PDOException $e) {
        echo 'Error de conexión: ' . $e->getMessage();
    }
}