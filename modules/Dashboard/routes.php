<?php

use Core\Router;
use Modules\Dashboard\Controllers\DashboardController;

return function (Router $router) {
    $router->get('/dashboard', function (\PDO $pdo) {
        $dashboardV2UiEnabled = filter_var(
            $_ENV['DASHBOARD_V2_UI_ENABLED'] ?? getenv('DASHBOARD_V2_UI_ENABLED') ?? '0',
            FILTER_VALIDATE_BOOLEAN
        );

        if ($dashboardV2UiEnabled) {
            $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
            $target = '/v2/dashboard';
            if ($queryString !== '') {
                $target .= '?' . $queryString;
            }

            header('Location: ' . $target, true, 302);
            exit;
        }

        $controller = new DashboardController($pdo);
        $controller->index();
    });
};
