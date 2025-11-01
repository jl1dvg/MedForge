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

    protected function render(string $viewPath, array $data = []): void
    {
        $shared = array_merge(
            [
                'username' => $_SESSION['username'] ?? 'Invitado',
            ],
            $data
        );

        View::render($viewPath, $shared);
    }
}