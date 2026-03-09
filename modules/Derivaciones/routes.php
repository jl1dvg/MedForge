<?php

use Core\Router;
use Modules\Derivaciones\Controllers\DerivacionesController;

return function (Router $router) {
    $derivacionesV2UiEnabled = static function (): bool {
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
                if (trim($key) !== 'DERIVACIONES_V2_UI_ENABLED') {
                    continue;
                }

                $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                break;
            }
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $rawFlag = $_ENV['DERIVACIONES_V2_UI_ENABLED'] ?? getenv('DERIVACIONES_V2_UI_ENABLED') ?? null;
        }

        return filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);
    };

    $redirectToV2IfEnabled = static function (string $target, int $status = 302) use ($derivacionesV2UiEnabled): void {
        if (!$derivacionesV2UiEnabled()) {
            return;
        }

        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        header('Location: ' . $target, true, $status);
        exit;
    };

    // Vista principal de derivaciones.
    $router->get('/derivaciones', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/derivaciones');
        (new DerivacionesController($pdo))->index();
    });

    // Datos para DataTable.
    $router->post('/derivaciones/datatable', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/derivaciones/datatable', 307);
        (new DerivacionesController($pdo))->datatable();
    });

    // Descarga/visualización de PDF asociado a la derivación.
    $router->get('/derivaciones/archivo/{id}', function (\PDO $pdo, $id) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/derivaciones/archivo/' . (int) $id);
        (new DerivacionesController($pdo))->descargarArchivo((int) $id);
    });

    // Ejecutar scrapping para una derivación concreta.
    $router->post('/derivaciones/scrap', function (\PDO $pdo) use ($redirectToV2IfEnabled) {
        $redirectToV2IfEnabled('/v2/derivaciones/scrap', 307);
        (new DerivacionesController($pdo))->ejecutarScraper();
    });
};
