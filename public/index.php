<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = '/public/index.php';
if (strncmp($requestPath, $basePath, strlen($basePath)) === 0) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$laravelBridgeExact = [
    '/auth/login',
    '/auth/logout',
    '/whatsapp/webhook',
    '/solicitudes/guardar.php',
    '/solicitudes/guardar',
    '/api/solicitudes/guardar.php',
    '/api/solicitudes/guardar',
    '/api/solicitudes/estado',
    '/api/solicitudes/estado.php',
];
$laravelBridgePrefixes = [
    '/v2',
    '/v3',
    '/usuarios',
    '/roles',
    '/feedback',
    '/protocolos',
    '/consultas',
    '/examenes',
    '/imagenes',
    '/agenda',
    '/derivaciones',
    '/reports',
    '/mailbox',
    '/mail',
    '/mail-templates',
    '/ai',
    '/search',
    '/api/cive-extension',
    '/api/consultas',
    '/api/proyecciones',
    '/insumos',
    '/kpis',
    '/doctores',
    '/cron-manager',
    '/cirugias',
    '/pacientes',
    '/procedimientos',
    '/api/procedimientos',
    '/billing',    // Onda 5-A
    '/informes',   // Onda 5-A
    '/crm',        // Onda 5 — reinvención CRM
    '/leads',      // Onda 5 — legacy /leads alias
    '/control-center',
];

if (in_array($requestPath, $laravelBridgeExact, true)) {
    require __DIR__ . '/v2_kernel.php';
    exit;
}

foreach ($laravelBridgePrefixes as $prefix) {
    if ($requestPath === $prefix || strncmp($requestPath, $prefix . '/', strlen($prefix) + 1) === 0) {
        require __DIR__ . '/v2_kernel.php';
        exit;
    }
}

require_once __DIR__ . '/../bootstrap.php';

use Core\ModuleLoader;
use Core\Router;
use Controllers\EstadisticaFlujoController;

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'No hay conexión a la base de datos';
    exit;
}

try {
    $router = new Router($pdo);

    // Redirige la raíz dependiendo del estado de autenticación.
    $router->get('/', function () {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }

        header('Location: /dashboard');
        exit;
    });

    ModuleLoader::register($router, $pdo, BASE_PATH . '/modules');

    $dispatched = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], true);

    if (!$dispatched) {
        file_put_contents(
            __DIR__ . '/../debug_router.log',
            'Ruta no despachada: ' . $_SERVER['REQUEST_URI'] . PHP_EOL,
            FILE_APPEND
        );

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

        // Normalizar la ruta si viene con /public/index.php
        $requestBasePath = '/public/index.php';
        if (strncmp($path, $requestBasePath, strlen($requestBasePath)) === 0) {
            $path = substr($path, strlen($requestBasePath)) ?: '/';
        }

        $method = $_SERVER['REQUEST_METHOD'];

        // Intentar despachar archivos legacy ubicados directamente en /public
        $relativePath = ltrim($path, '/');
        if (strpos($relativePath, 'public/') === 0) {
            $relativePath = substr($relativePath, strlen('public/'));
        }

        if ($relativePath !== '') {
            $candidate = PUBLIC_PATH . '/' . $relativePath;
            $publicRealPath = realpath(PUBLIC_PATH);
            $candidateRealPath = $candidate && file_exists($candidate) ? realpath($candidate) : false;

            if ($candidateRealPath && $publicRealPath && strpos($candidateRealPath, $publicRealPath) === 0 && is_file($candidateRealPath)) {
                require $candidateRealPath;
                exit;
            }
        }

        if ($path === '/views/login.php') {
            header('Location: /auth/login');
            exit;
        }

        if ($path === '/views/logout.php') {
            header('Location: /auth/logout');
            exit;
        }

        // === Rutas legacy ===
                if ($path === '/reportes/estadistica_flujo' && $method === 'GET') {
            $controller = new EstadisticaFlujoController($pdo);
            $controller->index();
            exit;
        }

        http_response_code(404);
        echo 'Ruta no encontrada: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
    }
} catch (Throwable $e) {
    file_put_contents(
        __DIR__ . '/../debug_router.log',
        date('Y-m-d H:i:s') . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo 'Error interno detectado: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
