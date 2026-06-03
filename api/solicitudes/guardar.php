<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarSolicitudController;
use Helpers\CorsHelper;

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=UTF-8');

if (!CorsHelper::prepare('EXTENSION_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origen no permitido para este recurso.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON mal formado o vacío']);
    exit;
}

try {
    $controller = new GuardarSolicitudController($pdo);
    $response = $controller->guardar($data);
    http_response_code($response['success'] ? 200 : 422);
    echo json_encode($response);
} catch (Throwable $e) {
    error_log('Error en solicitudes/guardar.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
