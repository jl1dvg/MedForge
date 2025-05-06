<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ObtenerInsumosProtocoloController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Leer datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

if ($data === null) {
    echo json_encode(["success" => false, "message" => "JSON mal formado"]);
    exit;
}

$controller = new ObtenerInsumosProtocoloController($pdo);
$response = $controller->obtener($data);

echo json_encode($response);