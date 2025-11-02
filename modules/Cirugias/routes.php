<?php

use Core\Router;
use Modules\Cirugias\Controllers\CirugiasController;

return function (Router $router) {
    $router->get('/cirugias', function (\PDO $pdo) {
        (new CirugiasController($pdo))->index();
    });

    $router->match(['GET', 'POST'], '/cirugias/wizard', function (\PDO $pdo) {
        (new CirugiasController($pdo))->wizard();
    });

    $router->post('/cirugias/wizard/guardar', function (\PDO $pdo) {
        (new CirugiasController($pdo))->guardar();
    });

    $router->post('/cirugias/wizard/autosave', function (\PDO $pdo) {
        (new CirugiasController($pdo))->autosave();
    });

    $router->get('/cirugias/protocolo', function (\PDO $pdo) {
        (new CirugiasController($pdo))->protocolo();
    });

    $router->post('/cirugias/protocolo/printed', function (\PDO $pdo) {
        (new CirugiasController($pdo))->togglePrinted();
    });

    $router->post('/cirugias/protocolo/status', function (\PDO $pdo) {
        (new CirugiasController($pdo))->updateStatus();
    });
};
