<?php

namespace Core;

class BaseController
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    protected function render($viewPath, $data = [], $title = 'Panel')
    {
        // Variables compartidas para el layout/partials
        $username = $_SESSION['username'] ?? 'Invitado';

        // Variables específicas de la vista
        extract($data);

        // 🔍 depuración temporal
        file_put_contents(__DIR__ . '/../debug_render.log', $viewPath . PHP_EOL, FILE_APPEND);

        $layout = __DIR__ . '/../views/layout.php';
        include $layout;
    }
}