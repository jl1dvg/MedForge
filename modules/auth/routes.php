<?php

use Core\Router;
use Modules\Auth\Controllers\AuthController;

/** @var Router $router */

// Asegurar carga del controlador (por si el autoload no llega)
require_once __DIR__ . '/Controllers/AuthController.php';

// GET: formulario de login
$router->get('/auth/login', function ($pdo) {
    $controller = new AuthController($pdo);
    $controller->loginForm();
});

// POST: validar credenciales
$router->post('/auth/login', function ($pdo) {
    $controller = new AuthController($pdo);
    $controller->login();
});

// GET: logout
$router->get('/auth/logout', function ($pdo) {
    $controller = new AuthController($pdo);
    $controller->logout();
});