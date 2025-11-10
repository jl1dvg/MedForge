<?php

use Core\Router;
use Modules\IdentityVerification\Controllers\VerificationController;
return function (Router $router) {
    $router->get('/pacientes/certificaciones', function (\PDO $pdo) {
        (new VerificationController($pdo))->index();
    });

    $router->post('/pacientes/certificaciones', function (\PDO $pdo) {
        (new VerificationController($pdo))->store();
    });

    $router->get('/pacientes/certificaciones/detalle', function (\PDO $pdo) {
        (new VerificationController($pdo))->show();
    });

    $router->get('/pacientes/certificaciones/comprobante', function (\PDO $pdo) {
        (new VerificationController($pdo))->consentDocument();
    });

    $router->post('/pacientes/certificaciones/verificar', function (\PDO $pdo) {
        (new VerificationController($pdo))->verify();
    });

    $router->post('/pacientes/certificaciones/eliminar', function (PDO $pdo) {
        (new VerificationController($pdo))->destroy();
    });
};
