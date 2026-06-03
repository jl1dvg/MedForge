<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ObtenerInsumosProtocoloController;
use Helpers\CorsHelper;

header('Content-Type: application/json; charset=UTF-8');

CorsHelper::prepare('EXTENSION_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
]);

// Leer datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

if ($data === null) {
    echo json_encode(["success" => false, "message" => "JSON mal formado"]);
    exit;
}

$controller = new ObtenerInsumosProtocoloController($pdo);
$response = $controller->obtener($data);

echo json_encode($response);