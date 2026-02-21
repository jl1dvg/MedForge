<?php

use Core\Router;
use Modules\Dashboard\Controllers\DashboardController;

return function (Router $router) {
    $router->get('/dashboard', function (\PDO $pdo) {
        $rawFlag = $_ENV['DASHBOARD_V2_UI_ENABLED'] ?? getenv('DASHBOARD_V2_UI_ENABLED') ?? null;

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $envPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

            if (is_readable($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                    if (trim($key) !== 'DASHBOARD_V2_UI_ENABLED') {
                        continue;
                    }

                    $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                    break;
                }
            }
        }

        $dashboardV2UiEnabled = filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);

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
