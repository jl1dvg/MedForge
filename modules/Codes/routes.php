<?php

use Core\Router;
use Modules\Codes\Controllers\CodesController;
use Modules\Codes\Controllers\PackageController;

return static function (Router $router): void {
    $codesV2UiEnabled = static function (): bool {
        $rawFlag = null;
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
                if (trim($key) !== 'CODES_V2_UI_ENABLED') {
                    continue;
                }

                $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                break;
            }
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $rawFlag = $_ENV['CODES_V2_UI_ENABLED'] ?? getenv('CODES_V2_UI_ENABLED') ?? null;
        }

        return filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);
    };

    $redirectToV2IfEnabled = static function (string $target, int $status = 302) use ($codesV2UiEnabled): void {
        if (!$codesV2UiEnabled()) {
            return;
        }

        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        header('Location: ' . $target, true, $status);
        exit;
    };

    $prefixes = ['', '/public/index.php'];

    foreach ($prefixes as $prefix) {
        $router->get($prefix . '/codes', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes');
            (new CodesController($pdo))->index();
        });

        $router->get($prefix . '/codes/create', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/create');
            (new CodesController($pdo))->create();
        });

        $router->post($prefix . '/codes', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes', 307);
            (new CodesController($pdo))->store();
        });

        $router->get($prefix . '/codes/{id}/edit', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/' . (int) $id . '/edit');
            (new CodesController($pdo))->edit((int) $id);
        });

        $router->post($prefix . '/codes/{id}', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/' . (int) $id, 307);
            (new CodesController($pdo))->update((int) $id);
        });

        $router->post($prefix . '/codes/{id}/delete', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/' . (int) $id . '/delete', 307);
            (new CodesController($pdo))->destroy((int) $id);
        });

        $router->post($prefix . '/codes/{id}/toggle', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/' . (int) $id . '/toggle', 307);
            (new CodesController($pdo))->toggleActive((int) $id);
        });

        $router->post($prefix . '/codes/{id}/relate', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/' . (int) $id . '/relate', 307);
            (new CodesController($pdo))->addRelation((int) $id);
        });

        $router->post($prefix . '/codes/{id}/relate/del', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/' . (int) $id . '/relate/del', 307);
            (new CodesController($pdo))->removeRelation((int) $id);
        });

        $router->get($prefix . '/codes/datatable', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/datatable');
            (new CodesController($pdo))->datatable();
        });

        $router->get($prefix . '/codes/packages', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/packages');
            (new PackageController($pdo))->index();
        });

        $router->get($prefix . '/codes/api/packages', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/api/packages');
            (new PackageController($pdo))->list();
        });

        $router->get($prefix . '/codes/api/packages/{id}', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/api/packages/' . (int) $id);
            (new PackageController($pdo))->show((int) $id);
        });

        $router->post($prefix . '/codes/api/packages', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/api/packages', 307);
            (new PackageController($pdo))->store();
        });

        $router->post($prefix . '/codes/api/packages/{id}', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/api/packages/' . (int) $id, 307);
            (new PackageController($pdo))->update((int) $id);
        });

        $router->post($prefix . '/codes/api/packages/{id}/delete', static function (\PDO $pdo, string $id) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/api/packages/' . (int) $id . '/delete', 307);
            (new PackageController($pdo))->delete((int) $id);
        });

        $router->get($prefix . '/codes/api/search', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
            $redirectToV2IfEnabled('/v2/codes/api/search');
            (new PackageController($pdo))->searchCodes();
        });
    }
};
