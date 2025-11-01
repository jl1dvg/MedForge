<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../core/Router.php';

use Core\Router;

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) die("No hay conexión a la base de datos");

$router = new Router($pdo);

// === 1️⃣ Cargar módulos completos (index.php de cada módulo) ===
foreach (glob(__DIR__ . '/../modules/*/index.php') as $moduleFile) {
    require_once $moduleFile;
}

// === 2️⃣ Cargar rutas sueltas si algún módulo no tiene index.php ===
foreach (glob(__DIR__ . '/../modules/*/routes.php') as $routesFile) {
    require_once $routesFile;
}

// *** Eliminar esta parte que causa el error: ***
// if (!$router->found()) {
//     http_response_code(404);
//     include __DIR__ . '/../views/404.php';
// }

// === 3️⃣ Despachar la ruta actual ===
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
ob_end_flush();