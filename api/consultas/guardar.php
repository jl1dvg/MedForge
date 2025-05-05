<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarConsultaController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

$data = json_decode(file_get_contents('php://input'), true);

if ($data === null) {
    echo json_encode(["success" => false, "message" => "JSON mal formado"]);
    exit;
}

$controller = new GuardarConsultaController($pdo);
$response = $controller->guardar($data);

echo json_encode($response);