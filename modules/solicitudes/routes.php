<?php

use Controllers\SolicitudController;
use Core\Router;

return function (Router $router) {
    $router->get('/solicitudes', function (\PDO $pdo) {
        (new SolicitudController($pdo))->index();
    });

    $router->post('/solicitudes/kanban-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->kanbanData();
    });

    $router->post('/solicitudes/actualizar-estado', function (\PDO $pdo) {
        (new SolicitudController($pdo))->actualizarEstado();
    });

    $router->get('/solicitudes/prefactura', function (\PDO $pdo) {
        (new SolicitudController($pdo))->prefactura();
    });
};
