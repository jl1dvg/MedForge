<?php

namespace Core;

use PDO;

class BaseController
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireAuth(string $redirect = '/auth/login'): void
    {
        if ($this->isAuthenticated()) {
            return;
        }

        if (strpos($redirect, 'auth_required=1') === false) {
            $redirect .= (strpos($redirect, '?') !== false ? '&' : '?') . 'auth_required=1';
        }

        header('Location: ' . $redirect);
        exit;
    }

    protected function render(string $viewPath, array $data = [], string|false|null $layout = null): void
    {
        $shared = array_merge(
            [
                'username' => $_SESSION['username'] ?? 'Invitado',
            ],
            $data
        );

        View::render($viewPath, $shared, $layout);
    }

    protected function json(array $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function currentPermissions(): array
    {
        return Permissions::normalize($_SESSION['permisos'] ?? []);
    }

    protected function hasPermission(string|array $permissions): bool
    {
        $current = $this->currentPermissions();

        if (is_array($permissions)) {
            return Permissions::containsAny($current, $permissions);
        }

        return Permissions::contains($current, $permissions);
    }

    protected function requirePermission(string|array $permissions): void
    {
        if ($this->hasPermission($permissions)) {
            return;
        }

        if (!headers_sent()) {
            http_response_code(403);
        }

        $this->render(BASE_PATH . '/views/errors/forbidden.php', [
            'pageTitle' => 'Acceso denegado',
            'requiredPermissions' => (array) $permissions,
        ]);

        exit;
    }
}
