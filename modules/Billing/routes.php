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

    $router->get('/billing/no-facturados', function (\PDO $pdo) {
        (new BillingController($pdo))->noFacturados();
    });

    $router->post('/billing/no-facturados/crear', function (\PDO $pdo) {
        (new BillingController($pdo))->crearDesdeNoFacturado();
    });

    $router->get('/views/billing/no_facturados.php', function () {
        header('Location: /billing/no-facturados', true, 302);
        exit;
    });

    $router->post('/views/billing/components/crear_desde_no_facturado.php', function (\PDO $pdo) {
        (new BillingController($pdo))->crearDesdeNoFacturado();
    });

    $router->match(['GET', 'POST'], '/informes/iess', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIess();
    });

    $router->match(['GET', 'POST'], '/informes/isspol', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIsspol();
    });

    $router->match(['GET', 'POST'], '/informes/issfa', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIssfa();
    });

    $router->match(['GET', 'POST'], '/informes/particulares', function (\PDO $pdo) {
        (new InformesController($pdo))->informeParticulares();
    });

    $router->match(['GET', 'POST'], '/informes/iess/prueba', function (\PDO $pdo) {
        (new InformesController($pdo))->informeIessPrueba();
    });

    $router->get('/informes/iess/consolidado', function (\PDO $pdo) {
        (new InformesController($pdo))->generarConsolidadoIess();
    });

    $router->get('/informes/isspol/consolidado', function (\PDO $pdo) {
        (new InformesController($pdo))->generarConsolidadoIsspol();
    });

    $router->get('/informes/issfa/consolidado', function (\PDO $pdo) {
        (new InformesController($pdo))->generarConsolidadoIssfa();
    });

    $router->get('/informes/iess/excel-lote', function (\PDO $pdo) {
        (new InformesController($pdo))->generarExcelIessLote();
    });

    $router->match(['GET', 'POST'], '/informes/api/detalle-factura', function (\PDO $pdo) {
        (new InformesController($pdo))->ajaxDetalleFactura();
    });

    $router->post('/informes/api/eliminar-factura', function (\PDO $pdo) {
        (new InformesController($pdo))->ajaxEliminarFactura();
    });

    $router->post('/informes/api/scrapear-codigo', function (\PDO $pdo) {
        (new InformesController($pdo))->ajaxScrapearCodigoDerivacion();
    });
};
