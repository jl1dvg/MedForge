<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\SolicitudController;

// CORS (similar a tu otro API)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $hcNumber = $_GET['hcNumber'] ?? null;

    if (!$hcNumber) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parámetro hcNumber requerido',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new SolicitudController($pdo);
    $response = $controller->obtenerEstadosPorHc($hcNumber);

    if (!is_array($response)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Respuesta inválida del controlador',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}