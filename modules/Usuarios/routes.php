<?php

use Core\Router;
use Modules\Usuarios\Controllers\RolesController;
use Modules\Usuarios\Controllers\UsuariosController;

return static function (Router $router) {
    $router->get('/usuarios', static function (\PDO $pdo) {
        (new UsuariosController($pdo))->index();
    });

    $router->match(['GET', 'POST'], '/usuarios/create', static function (\PDO $pdo) {
        $controller = new UsuariosController($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
            return;
        }
        $controller->create();
    });

    $router->match(['GET', 'POST'], '/usuarios/edit', static function (\PDO $pdo) {
        $controller = new UsuariosController($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update();
            return;
        }
        $controller->edit();
    });

    $router->post('/usuarios/delete', static function (\PDO $pdo) {
        (new UsuariosController($pdo))->destroy();
    });

    $router->get('/roles', static function (\PDO $pdo) {
        (new RolesController($pdo))->index();
    });

    $router->match(['GET', 'POST'], '/roles/create', static function (\PDO $pdo) {
        $controller = new RolesController($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store();
            return;
        }
        $controller->create();
    });

    $router->match(['GET', 'POST'], '/roles/edit', static function (\PDO $pdo) {
        $controller = new RolesController($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update();
            return;
        }
        $controller->edit();
    });

    $router->post('/roles/delete', static function (\PDO $pdo) {
        (new RolesController($pdo))->destroy();
    });
};
