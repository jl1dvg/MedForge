<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../controllers/BillingController.php';
require_once __DIR__ . '/../controllers/EstadisticaFlujoController.php';
require_once __DIR__ . '/../controllers/CodesController.php';

use Controllers\BillingController;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Normalizar la ruta si viene con /public/index.php
$basePath = '/public/index.php';
if (substr($path, 0, strlen($basePath)) === $basePath) {
    $path = substr($path, strlen($basePath));
}
$method = $_SERVER['REQUEST_METHOD'];

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    die("No hay conexión a la base de datos");
}

// Ruta para generar Excel por afiliación
if ($path === '/billing/excel' && $method === 'GET') {
    $formId = $_GET['form_id'] ?? null;
    $grupo = $_GET['grupo'] ?? '';
    if ($formId) {
        $controller = new BillingController($pdo);
        $controller->generarExcel($formId, $grupo);
    } else {
        http_response_code(400);
        echo "Falta parámetro form_id";
    }
}
// Ruta para generar ZIP con todas las planillas del mes
if ($path === '/billing/exportar_mes' && $method === 'GET') {
    $mes = $_GET['mes'] ?? null;
    $grupo = $_GET['grupo'] ?? '';
    if ($mes) {
        $controller = new BillingController($pdo);
        $controller->exportarPlanillasPorMes($mes, $grupo);
    } else {
        http_response_code(400);
        echo "Falta parámetro mes";
    }
} // Puedes agregar más rutas aquí
if ($path === '/reportes/estadistica_flujo' && $method === 'GET') {
    $controller = new \Controllers\EstadisticaFlujoController($pdo);
    $controller->index();
    exit;
}
// === Rutas del módulo de Códigos ===
if ($path === '/codes' && $method === 'GET') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->index();
    exit;
}
if ($path === '/codes/create' && $method === 'GET') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->create();
    exit;
}
if ($path === '/codes' && $method === 'POST') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->store($_POST);
    exit;
}
if (preg_match('#^/codes/(\d+)/edit$#', $path, $m) && $method === 'GET') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->edit((int)$m[1]);
    exit;
}
if (preg_match('#^/codes/(\d+)$#', $path, $m) && $method === 'POST') {
    // Update (usamos POST como en tu especificación)
    $controller = new \Controllers\CodesController($pdo);
    $controller->update((int)$m[1], $_POST);
    exit;
}
if (preg_match('#^/codes/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->destroy((int)$m[1]);
    exit;
}
if (preg_match('#^/codes/(\d+)/toggle$#', $path, $m) && $method === 'POST') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->toggleActive((int)$m[1]);
    exit;
}
if (preg_match('#^/codes/(\d+)/relate$#', $path, $m) && $method === 'POST') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->addRelation((int)$m[1], $_POST);
    exit;
}
if (preg_match('#^/codes/(\d+)/relate/del$#', $path, $m) && $method === 'POST') {
    $controller = new \Controllers\CodesController($pdo);
    $controller->removeRelation((int)$m[1], $_POST);
    exit;
} // === Fin rutas de Códigos ===
else {
    http_response_code(404);
    echo "Ruta no encontrada: " . htmlspecialchars($path);
}