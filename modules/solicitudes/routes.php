<?php

use Controllers\SolicitudController;
use Core\Router;

return function (Router $router) {
    $router->get('/solicitudes', function (\PDO $pdo) {
        (new SolicitudController($pdo))->index();
    });

    $router->get('/solicitudes/turnero', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turnero();
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

    $router->get('/solicitudes/turnero-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroData();
    });

    $router->post('/solicitudes/turnero-llamar', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroLlamar();
    });

    $router->post('/solicitudes/turnero-siguiente', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroLlamarSiguiente();
    });
};
