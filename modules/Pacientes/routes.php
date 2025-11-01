<?php

use Core\Router;
use Modules\Pacientes\Controllers\PacientesController;

return function (Router $router) {
    $router->get('/pacientes', function (\PDO $pdo) {
        (new PacientesController())->index($pdo);
    });

    $router->post('/pacientes/datatable', function (\PDO $pdo) {
        (new PacientesController())->datatable($pdo);
    });

    $router->get('/pacientes/detalles', function (\PDO $pdo) {
        (new PacientesController())->detalles($pdo);
    });
};
