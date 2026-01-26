<?php

use Core\Router;
use Modules\Dashboard\Controllers\DashboardController;

return function (Router $router) {
    $router->get('/dashboard', function (\PDO $pdo) {
        $controller = new DashboardController($pdo);
        $controller->entry();
    });

    $router->get('/dashboard/general', function (\PDO $pdo) {
        $controller = new DashboardController($pdo);
        $controller->general();
    });

    $router->get('/dashboard/solicitudes', function (\PDO $pdo) {
        $controller = new DashboardController($pdo);
        $controller->solicitudes();
    });

    $router->get('/dashboard/cirugias', function (\PDO $pdo) {
        $controller = new DashboardController($pdo);
        $controller->cirugias();
    });

    $router->get('/dashboard/billing', function (\PDO $pdo) {
        $controller = new DashboardController($pdo);
        $controller->billing();
    });
};
