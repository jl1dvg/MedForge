<?php

use Core\Router;
use Modules\Cirugias\Controllers\CirugiasController;
use Modules\Cirugias\Controllers\CirugiasDashboardController;

return function (Router $router) {
    $resolveFlag = static function (string $flag, bool $default = false): bool {
        $rawFlag = null;
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $envPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

        // Prefer explicit value from .env to avoid stale process-level env vars.
        if (is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                if (trim($key) !== $flag) {
                    continue;
                }

                $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                break;
            }
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $rawFlag = $_ENV[$flag] ?? getenv($flag) ?? null;
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            return $default;
        }

        return filter_var((string) $rawFlag, FILTER_VALIDATE_BOOLEAN);
    };

    $redirectWithQuery = static function (string $target, int $status): void {
        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        header('Location: ' . $target, true, $status);
        exit;
    };

    $redirectUiIfEnabled = static function (string $target) use ($resolveFlag, $redirectWithQuery): void {
        if (!$resolveFlag('CIRUGIAS_V2_UI_ENABLED', false)) {
            return;
        }

        $redirectWithQuery($target, 302);
    };

    $redirectReadsIfEnabled = static function (string $target) use ($resolveFlag, $redirectWithQuery): void {
        if (!$resolveFlag('CIRUGIAS_V2_READS_ENABLED', false)) {
            return;
        }

        $redirectWithQuery($target, 307);
    };

    $redirectWritesIfEnabled = static function (string $target) use ($resolveFlag, $redirectWithQuery): void {
        if (!$resolveFlag('CIRUGIAS_V2_WRITES_ENABLED', false)) {
            return;
        }

        $redirectWithQuery($target, 307);
    };

    $router->get('/cirugias', function (\PDO $pdo) use ($redirectUiIfEnabled) {
        $redirectUiIfEnabled('/v2/cirugias');
        (new CirugiasController($pdo))->index();
    });

    $router->post('/cirugias/datatable', function (\PDO $pdo) use ($redirectReadsIfEnabled) {
        $redirectReadsIfEnabled('/v2/cirugias/datatable');
        (new CirugiasController($pdo))->datatable();
    });

    $router->get('/cirugias/dashboard', function (\PDO $pdo) use ($redirectUiIfEnabled) {
        $redirectUiIfEnabled('/v2/cirugias/dashboard');
        (new CirugiasDashboardController($pdo))->index();
    });

    $router->get('/cirugias/dashboard/export/pdf', function (\PDO $pdo) use ($redirectUiIfEnabled) {
        $redirectUiIfEnabled('/v2/cirugias/dashboard/export/pdf');
        (new CirugiasDashboardController($pdo))->exportPdf();
    });

    $router->get('/cirugias/dashboard/export/excel', function (\PDO $pdo) use ($redirectUiIfEnabled) {
        $redirectUiIfEnabled('/v2/cirugias/dashboard/export/excel');
        (new CirugiasDashboardController($pdo))->exportExcel();
    });

    $router->match(['GET', 'POST'], '/cirugias/wizard', function (\PDO $pdo) use ($redirectUiIfEnabled) {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $redirectUiIfEnabled('/v2/cirugias/wizard');
        }
        (new CirugiasController($pdo))->wizard();
    });

    $router->post('/cirugias/wizard/guardar', function (\PDO $pdo) use ($redirectWritesIfEnabled) {
        $redirectWritesIfEnabled('/v2/cirugias/wizard/guardar');
        (new CirugiasController($pdo))->guardar();
    });

    $router->post('/cirugias/wizard/autosave', function (\PDO $pdo) use ($redirectWritesIfEnabled) {
        $redirectWritesIfEnabled('/v2/cirugias/wizard/autosave');
        (new CirugiasController($pdo))->autosave();
    });

    $router->get('/cirugias/protocolo', function (\PDO $pdo) use ($redirectReadsIfEnabled) {
        $redirectReadsIfEnabled('/v2/cirugias/protocolo');
        (new CirugiasController($pdo))->protocolo();
    });

    $router->post('/cirugias/protocolo/printed', function (\PDO $pdo) use ($redirectWritesIfEnabled) {
        $redirectWritesIfEnabled('/v2/cirugias/protocolo/printed');
        (new CirugiasController($pdo))->togglePrinted();
    });

    $router->post('/cirugias/protocolo/status', function (\PDO $pdo) use ($redirectWritesIfEnabled) {
        $redirectWritesIfEnabled('/v2/cirugias/protocolo/status');
        (new CirugiasController($pdo))->updateStatus();
    });
};
