<?php

use Core\Router;
use Modules\CiveExtension\Controllers\ConfigController;
use Modules\CiveExtension\Controllers\HealthController;

return static function (Router $router, \PDO $pdo): void {
    $router->get('/api/cive-extension/config', function (\PDO $pdo) {
        $controller = new ConfigController($pdo);
        $controller->show();
    });

    $router->post('/api/cive-extension/health-check', function (\PDO $pdo) {
        $controller = new HealthController($pdo);
        $controller->run();
    });

    $router->get('/api/cive-extension/health-checks', function (\PDO $pdo) {
        $controller = new HealthController($pdo);
        $controller->index();
    });
};
