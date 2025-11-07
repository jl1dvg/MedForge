<?php

use Controllers\AIController;
use Core\Router;

return static function (Router $router, \PDO $pdo): void {
    $router->post('/ai/enfermedad', function (\PDO $pdo) {
        $controller = new AIController($pdo);
        $controller->generarEnfermedad();
    });

    $router->post('/ai/plan', function (\PDO $pdo) {
        $controller = new AIController($pdo);
        $controller->generarPlan();
    });
};

