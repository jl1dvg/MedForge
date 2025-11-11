<?php

use Core\Router;
use Modules\KPI\Controllers\KpiController;

return static function (Router $router): void {
    $router->get('/kpis', static function (\PDO $pdo): void {
        (new KpiController($pdo))->index();
    });

    $router->get('/kpis/{kpiKey}', static function (\PDO $pdo, string $kpiKey): void {
        (new KpiController($pdo))->show($kpiKey);
    });
};
