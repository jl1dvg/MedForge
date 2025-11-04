<?php

use Core\Router;
use Modules\Reporting\Controllers\ReportController;
use Modules\Reporting\Services\ReportService;

return static function (Router $router): void {
    $router->get('/reports', static function (\PDO $pdo): void {
        $controller = new ReportController($pdo, new ReportService());
        $controller->index();
    });

    $router->get('/reports/{slug}', static function (\PDO $pdo, string $slug): void {
        $controller = new ReportController($pdo, new ReportService());
        $controller->show($slug);
    });
};
