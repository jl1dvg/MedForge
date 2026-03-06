<?php

use Core\Router;
use Modules\Pacientes\Controllers\PacientesController;

return function (Router $router) {
    $router->get('/pacientes', function (\PDO $pdo) {
        (new PacientesController($pdo))->index();
    });

    $router->post('/pacientes/datatable', function (\PDO $pdo) {
        (new PacientesController($pdo))->datatable();
    });

    $router->match(['GET', 'POST'], '/pacientes/detalles', function (\PDO $pdo) {
        (new PacientesController($pdo))->detalles();
    });

    $router->get('/pacientes/detalles/solicitud', function (\PDO $pdo) {
        (new PacientesController($pdo))->detalleSolicitudApi();
    });

    $router->get('/pacientes/detalles/section', function (\PDO $pdo) {
        (new PacientesController($pdo))->detallesSection();
    });

    $router->get('/pacientes/flujo', function (\PDO $pdo) {
        (new PacientesController($pdo))->flujo();
    });
};
