<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ListarProcedimientosController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

$controller = new ListarProcedimientosController($pdo);
$afiliacion = $_GET['afiliacion'] ?? '';
$response = $controller->listar($afiliacion);
$response['afiliacion_recibida'] = $afiliacion;

echo json_encode($response);