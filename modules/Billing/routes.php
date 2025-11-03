<?php

use Core\Router;
use Modules\Billing\Controllers\BillingController;
use Modules\Billing\Controllers\InformesController;

return function (Router $router) {
    $router->get('/billing', function (\PDO $pdo) {
        (new BillingController($pdo))->index();
    });

    $router->get('/billing/detalle', function (\PDO $pdo) {
        (new BillingController($pdo))->detalle();
    });

    $router->match(['GET', 'POST'], '/informes/iess', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIess();
    });
};
