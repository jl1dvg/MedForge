<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarSolicitudController;

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Responder preflight sin cuerpo
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $raw = file_get_contents('php://input');

    // Validación de JSON
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'JSON mal formado',
            'error' => json_last_error_msg()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new GuardarSolicitudController($pdo);
    $response = $controller->guardar($data);

    // Garantizar array y forma de salida
    if (!is_array($response)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Respuesta inválida del controlador'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Si el controlador quiere indicar error, respeta su estado
    if (isset($response['success']) && $response['success'] === false) {
        // 422 es útil para validaciones; 400 genérico si prefieres
        http_response_code(422);
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // No echo de warnings/trace, solo JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}