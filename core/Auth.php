<?php

namespace Core;

class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function login($userId, $permisos = null, ?int $roleId = null): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['permisos'] = Permissions::normalize($permisos);
        $_SESSION['role_id'] = $roleId;
        $_SESSION['session_active'] = true;
        $_SESSION['session_start_time'] = time();
        $_SESSION['last_activity_time'] = time();
    }

    public static function logout(): void
    {
        session_unset();
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /auth/login');
            exit;
        }
    }

    public static function user(): ?array
    {
        if (self::check()) {
            return [
                'id' => $_SESSION['user_id'] ?? null,
                'role_id' => $_SESSION['role_id'] ?? null,
                'permisos' => Permissions::normalize($_SESSION['permisos'] ?? null),
                'session_active' => $_SESSION['session_active'] ?? false,
                'session_start_time' => $_SESSION['session_start_time'] ?? null,
            ];
        }
        return null;
    }
}