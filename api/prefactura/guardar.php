<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarPrefacturaController;
use Helpers\CorsHelper;

header('Content-Type: application/json; charset=UTF-8');

CorsHelper::prepare('EXTENSION_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
]);

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