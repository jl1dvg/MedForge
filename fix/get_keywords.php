<?php
require_once __DIR__ . '/../bootstrap.php';

use Controllers\GuardarProyeccionController;

$controller = new GuardarProyeccionController($pdo);
$palabrasClave = $controller->obtenerPalabrasClaveProcedimientos();

header('Content-Type: application/json');
echo json_encode($palabrasClave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);