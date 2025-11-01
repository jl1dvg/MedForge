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
}