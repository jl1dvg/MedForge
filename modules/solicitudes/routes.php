<?php

use Controllers\SolicitudController;

// Ruta principal del módulo (Kanban)
$router->get('/solicitudes', function ($pdo) {
    $controller = new SolicitudController($pdo);
    $data = $controller->index();

    $viewPath = __DIR__ . '/views/solicitudes.php';
    $title = 'Solicitudes Quirúrgicas';
    include __DIR__ . '/../../views/layout.php';
});

// Ruta AJAX para obtener datos del Kanban
$router->post('/solicitudes/kanban_data', function ($pdo) {
    $controller = new SolicitudController($pdo);
    $controller->kanbanData($_POST); // retorna JSON
});