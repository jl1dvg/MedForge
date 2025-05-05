<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ListarProcedimientosController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

$controller = new ListarProcedimientosController($pdo);
$response = $controller->listar();

echo json_encode($response);