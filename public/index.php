<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error_log_php.txt');
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';

use Core\ModuleLoader;
use Core\Router;
use Controllers\BillingController;
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
        $basePath = '/public/index.php';
        if (strncmp($path, $basePath, strlen($basePath)) === 0) {
            $path = substr($path, strlen($basePath)) ?: '/';
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
        if ($path === '/billing/excel' && $method === 'GET') {
            $formId = $_GET['form_id'] ?? null;
            $grupo = $_GET['grupo'] ?? '';
            if ($formId) {
                $controller = new BillingController($pdo);
                $controller->generarExcel($formId, $grupo);
            } else {
                http_response_code(400);
                echo 'Falta parámetro form_id';
            }
            exit;
        }

        if ($path === '/billing/exportar_mes' && $method === 'GET') {
            $mes = $_GET['mes'] ?? null;
            $grupo = $_GET['grupo'] ?? '';
            if ($mes) {
                $controller = new BillingController($pdo);
                $controller->exportarPlanillasPorMes($mes, $grupo);
            } else {
                http_response_code(400);
                echo 'Falta parámetro mes';
            }
            exit;
        }

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
