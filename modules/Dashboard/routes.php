<?php

use Core\Router;
use Modules\Dashboard\Controllers\DashboardController;

return function (Router $router) {
    $router->get('/dashboard', function (\PDO $pdo) {
        $controller = new DashboardController($pdo);
        $controller->index();
    });
};
