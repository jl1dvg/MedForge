<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../controllers/BillingController.php';

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
else {
    http_response_code(404);
    echo "Ruta no encontrada: " . htmlspecialchars($path);
}