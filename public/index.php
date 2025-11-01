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
use Controllers\CodesController;
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

        if ($path === '/codes/datatable' && $method === 'GET') {
            $controller = new CodesController($pdo);
            $controller->datatable($_GET);
            exit;
        }

        if ($path === '/reportes/estadistica_flujo' && $method === 'GET') {
            $controller = new EstadisticaFlujoController($pdo);
            $controller->index();
            exit;
        }

        if ($path === '/codes' && $method === 'GET') {
            $controller = new CodesController($pdo);
            $controller->index();
            exit;
        }

        if ($path === '/codes/create' && $method === 'GET') {
            $controller = new CodesController($pdo);
            $controller->create();
            exit;
        }

        if ($path === '/codes' && $method === 'POST') {
            $controller = new CodesController($pdo);
            $controller->store($_POST);
            exit;
        }

        if (preg_match('#^/codes/(\d+)/edit$#', $path, $m) && $method === 'GET') {
            $controller = new CodesController($pdo);
            $controller->edit((int) $m[1]);
            exit;
        }

        if (preg_match('#^/codes/(\d+)$#', $path, $m) && $method === 'POST') {
            $controller = new CodesController($pdo);
            $controller->update((int) $m[1], $_POST);
            exit;
        }

        if (preg_match('#^/codes/(\d+)/delete$#', $path, $m) && $method === 'POST') {
            $controller = new CodesController($pdo);
            $controller->destroy((int) $m[1]);
            exit;
        }

        if (preg_match('#^/codes/(\d+)/toggle$#', $path, $m) && $method === 'POST') {
            $controller = new CodesController($pdo);
            $controller->toggleActive((int) $m[1]);
            exit;
        }

        if (preg_match('#^/codes/(\d+)/relate$#', $path, $m) && $method === 'POST') {
            $controller = new CodesController($pdo);
            $controller->addRelation((int) $m[1], $_POST);
            exit;
        }

        if (preg_match('#^/codes/(\d+)/relate/del$#', $path, $m) && $method === 'POST') {
            $controller = new CodesController($pdo);
            $controller->removeRelation((int) $m[1], $_POST);
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
