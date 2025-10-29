<?php

use Modules\Dashboard\Controllers\DashboardController;

$router->get('/Dashboard', function ($pdo) {
    $controller = new DashboardController($pdo);
    $controller->index();
});