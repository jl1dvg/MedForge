<?php

use Core\Router;
use Modules\Settings\Controllers\SettingsController;

return static function (Router $router, \PDO $pdo): void {
    $router->match(['GET', 'POST'], '/settings', function (\PDO $pdo) {
        $controller = new SettingsController($pdo);
        $controller->index();
    });
};
