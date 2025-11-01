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
};
