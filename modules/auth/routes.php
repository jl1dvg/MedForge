<?php

use Core\Router;
use Modules\Auth\Controllers\AuthController;

return function (Router $router) {
    $router->get('/auth/login', function (\PDO $pdo) {
        $controller = new AuthController($pdo);
        $controller->loginForm();
    });

    $router->post('/auth/login', function (\PDO $pdo) {
        $controller = new AuthController($pdo);
        $controller->login();
    });

    $router->get('/auth/logout', function (\PDO $pdo) {
        $controller = new AuthController($pdo);
        $controller->logout();
    });
};
