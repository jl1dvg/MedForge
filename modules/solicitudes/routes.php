<?php

use Controllers\SolicitudController;
use Core\Router;

return function (Router $router) {
    $router->get('/solicitudes', function (\PDO $pdo) {
        $controller = new SolicitudController($pdo);
        $data = $controller->index();

        $viewPath = __DIR__ . '/views/solicitudes.php';
        $title = 'Solicitudes Quirúrgicas';
        include __DIR__ . '/../../views/layout.php';
    });

    $router->post('/solicitudes/kanban_data', function (\PDO $pdo) {
        $controller = new SolicitudController($pdo);
        $controller->kanbanData($_POST);
    });
};
