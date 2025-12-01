<?php

use Core\Router;
use Modules\Insumos\Controllers\InsumosController;
use Modules\Insumos\Controllers\LentesController;

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

    // Lentes
    $router->get('/insumos/lentes', function (\PDO $pdo) {
        (new LentesController($pdo))->index();
    });

    $router->get('/insumos/lentes/list', function (\PDO $pdo) {
        (new LentesController($pdo))->listar();
    });

    $router->post('/insumos/lentes/guardar', function (\PDO $pdo) {
        (new LentesController($pdo))->guardar();
    });

    $router->post('/insumos/lentes/eliminar', function (\PDO $pdo) {
        (new LentesController($pdo))->eliminar();
    });
};
