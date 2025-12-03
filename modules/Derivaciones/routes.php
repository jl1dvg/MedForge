<?php

use Core\Router;
use Modules\Derivaciones\Controllers\DerivacionesController;

return function (Router $router) {
    // Vista principal de derivaciones.
    $router->get('/derivaciones', function (\PDO $pdo) {
        (new DerivacionesController($pdo))->index();
    });

    // Datos para DataTable.
    $router->post('/derivaciones/datatable', function (\PDO $pdo) {
        (new DerivacionesController($pdo))->datatable();
    });

    // Descarga/visualización de PDF asociado a la derivación.
    $router->get('/derivaciones/archivo/{id}', function (\PDO $pdo, $id) {
        (new DerivacionesController($pdo))->descargarArchivo((int) $id);
    });

    // Ejecutar scrapping para una derivación concreta.
    $router->post('/derivaciones/scrap', function (\PDO $pdo) {
        (new DerivacionesController($pdo))->ejecutarScraper();
    });
};
