<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarPrefacturaController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

$data = json_decode(file_get_contents('php://input'), true);

if ($data === null) {
    echo json_encode(["success" => false, "message" => "JSON mal formado"]);
    exit;
}

try {
    $controller = new GuardarPrefacturaController($pdo);
    $response = $controller->guardar($data);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Error al guardar los datos"]);
}