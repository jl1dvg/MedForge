<?php

use Core\Router;
use Modules\Usuarios\Controllers\RolesController;
use Modules\Usuarios\Controllers\UsuariosController;

return static function (Router $router): void {
    $resolveUiFlag = static function (string $flagName): bool {
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
                if (trim($key) !== $flagName) {
                    continue;
                }

                $rawFlag = trim($value, " \t\n\r\0\x0B\"'");
                break;
            }
        }

        if ($rawFlag === null || trim((string) $rawFlag) === '') {
            $rawFlag = $_ENV[$flagName] ?? getenv($flagName) ?? null;
        }

        return filter_var((string) ($rawFlag ?? '0'), FILTER_VALIDATE_BOOLEAN);
    };

    $redirectToV2IfEnabled = static function (
        string $flagName,
        string $target,
        array $dropQueryKeys = []
    ) use ($resolveUiFlag): void {
        if (!$resolveUiFlag($flagName)) {
            return;
        }

        $query = $_GET ?? [];
        foreach ($dropQueryKeys as $key) {
            unset($query[$key]);
        }

        $queryString = http_build_query($query);
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        header('Location: ' . $target, true, 302);
        exit;
    };

    $router->get('/usuarios', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $redirectToV2IfEnabled('USUARIOS_V2_UI_ENABLED', '/v2/usuarios');
        (new UsuariosController($pdo))->index();
    });

    $router->get('/usuarios/media', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $redirectToV2IfEnabled('USUARIOS_V2_UI_ENABLED', '/v2/usuarios/media');
        (new UsuariosController($pdo))->media();
    });

    $router->match(['GET', 'POST'], '/usuarios/create', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $controller = new UsuariosController($pdo);
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $redirectToV2IfEnabled('USUARIOS_V2_UI_ENABLED', '/v2/usuarios/create');
            $controller->create();
            return;
        }

        // Legacy POSTs stay in place because the old forms do not send Laravel CSRF tokens.
        $controller->store();
    });

    $router->match(['GET', 'POST'], '/usuarios/edit', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $controller = new UsuariosController($pdo);
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $target = $id > 0 ? '/v2/usuarios/' . $id . '/edit' : '/v2/usuarios';
            $redirectToV2IfEnabled('USUARIOS_V2_UI_ENABLED', $target, ['id']);
            $controller->edit();
            return;
        }

        // Legacy POSTs stay in place because the old forms do not send Laravel CSRF tokens.
        $controller->update();
    });

    $router->post('/usuarios/delete', static function (\PDO $pdo) {
        (new UsuariosController($pdo))->destroy();
    });

    $router->get('/roles', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $redirectToV2IfEnabled('ROLES_V2_UI_ENABLED', '/v2/roles');
        (new RolesController($pdo))->index();
    });

    $router->match(['GET', 'POST'], '/roles/create', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $controller = new RolesController($pdo);
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $redirectToV2IfEnabled('ROLES_V2_UI_ENABLED', '/v2/roles/create');
            $controller->create();
            return;
        }

        // Legacy POSTs stay in place because the old forms do not send Laravel CSRF tokens.
        $controller->store();
    });

    $router->match(['GET', 'POST'], '/roles/edit', static function (\PDO $pdo) use ($redirectToV2IfEnabled): void {
        $controller = new RolesController($pdo);
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $target = $id > 0 ? '/v2/roles/' . $id . '/edit' : '/v2/roles';
            $redirectToV2IfEnabled('ROLES_V2_UI_ENABLED', $target, ['id']);
            $controller->edit();
            return;
        }

        // Legacy POSTs stay in place because the old forms do not send Laravel CSRF tokens.
        $controller->update();
    });

    $router->post('/roles/delete', static function (\PDO $pdo) {
        (new RolesController($pdo))->destroy();
    });
};
