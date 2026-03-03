<?php

use Core\Router;
use Modules\Farmacia\Controllers\FarmaciaController;

return static function (Router $router): void {
    $router->get('/farmacia', static function (\PDO $pdo): void {
        (new FarmaciaController($pdo))->index();
    });

    $router->get('/farmacia/dashboard/export/pdf', static function (\PDO $pdo): void {
        (new FarmaciaController($pdo))->exportPdf();
    });

    $router->get('/farmacia/dashboard/export/excel', static function (\PDO $pdo): void {
        (new FarmaciaController($pdo))->exportExcel();
    });
};
