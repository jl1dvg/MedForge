<?php

use Core\Router;
use Modules\Insumos\Controllers\InsumosController;

return function (Router $router) {
    $router->get('/insumos', function (\PDO $pdo) {
        (new InsumosController($pdo))->index();
    });

    $router->get('/insumos/list', function (\PDO $pdo) {
        (new InsumosController($pdo))->listar();
    });

    $router->post('/insumos/guardar', function (\PDO $pdo) {
        (new InsumosController($pdo))->guardar();
    });

    $router->get('/insumos/medicamentos', function (\PDO $pdo) {
        (new InsumosController($pdo))->medicamentos();
    });

    $router->get('/insumos/medicamentos/list', function (\PDO $pdo) {
        (new InsumosController($pdo))->listarMedicamentos();
    });

    $router->post('/insumos/medicamentos/guardar', function (\PDO $pdo) {
        (new InsumosController($pdo))->guardarMedicamento();
    });

    $router->post('/insumos/medicamentos/eliminar', function (\PDO $pdo) {
        (new InsumosController($pdo))->eliminarMedicamento();
    });
};
