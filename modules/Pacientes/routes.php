<?php

use Core\Router;
use Modules\Pacientes\Controllers\PacientesController;

return function (Router $router) {
    $pacientesV2UiEnabled = static function (): bool {
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
                if (trim($key) !== 'PACIENTES_V2_UI_ENABLED') {
                    continue;
                }

                $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                break;
            }
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $rawFlag = $_ENV['PACIENTES_V2_UI_ENABLED'] ?? getenv('PACIENTES_V2_UI_ENABLED') ?? null;
        }

        return filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);
    };

    $redirectToV2IfEnabled = static function (string $target) use ($pacientesV2UiEnabled): void {
        if (!$pacientesV2UiEnabled()) {
            return;
        }

        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        header('Location: ' . $target, true, 302);
        exit;
    };

    $router->get('/pacientes', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/pacientes');
        (new PacientesController($pdo))->index();
    });

    $router->post('/pacientes/datatable', function (\PDO $pdo) {
        (new PacientesController($pdo))->datatable();
    });

    $router->match(['GET', 'POST'], '/pacientes/detalles', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $redirectToV2IfEnabled('/v2/pacientes/detalles');
        }

        (new PacientesController($pdo))->detalles();
    });

    $router->get('/pacientes/detalles/solicitud', function (\PDO $pdo) {
        (new PacientesController($pdo))->detalleSolicitudApi();
    });

    $router->get('/pacientes/detalles/section', function (\PDO $pdo) {
        (new PacientesController($pdo))->detallesSection();
    });

    $router->get('/pacientes/flujo', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/pacientes/flujo');
        (new PacientesController($pdo))->flujo();
    });
};
