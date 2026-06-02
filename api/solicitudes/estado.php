<?php
require_once __DIR__ . '/../../bootstrap.php';

use Models\SolicitudModel;
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

$hcNumber = trim((string)($_GET['hcNumber'] ?? ''));

if ($hcNumber === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Falta hcNumber']);
    exit;
}

try {
    $model = new SolicitudModel($pdo);
    $solicitudes = $model->obtenerEstadosPorHc($hcNumber);
    echo json_encode(['success' => true, 'data' => $solicitudes]);
} catch (Throwable $e) {
    error_log('Error en solicitudes/estado.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
