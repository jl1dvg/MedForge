<?php

declare(strict_types=1);

use Core\Router;
use Modules\CronManager\Controllers\CronManagerController;

return static function (Router $router, \PDO $pdo): void {
    $router->get('/cron-manager', function (\PDO $pdo): void {
        (new CronManagerController($pdo))->index();
    });

    $router->post('/cron-manager/run', function (\PDO $pdo): void {
        (new CronManagerController($pdo))->runAll();
    });

    $router->post('/cron-manager/run/{slug}', function (\PDO $pdo, string $slug): void {
        (new CronManagerController($pdo))->runTask($slug);
    });
};
