<?php

use Core\Router;
use Modules\EditorProtocolos\Controllers\EditorController;

return static function (Router $router) {
    $router->get('/protocolos', function (\PDO $pdo) {
        (new EditorController($pdo))->index();
    });

    $router->get('/protocolos/crear', function (\PDO $pdo) {
        (new EditorController($pdo))->create();
    });

    $router->get('/protocolos/editar', function (\PDO $pdo) {
        (new EditorController($pdo))->edit();
    });

    $router->post('/protocolos/guardar', function (\PDO $pdo) {
        (new EditorController($pdo))->store();
    });

    $router->post('/protocolos/eliminar', function (\PDO $pdo) {
        (new EditorController($pdo))->delete();
    });
};
