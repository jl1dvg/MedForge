<?php
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Insumos\Models\LenteModel;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$model = new LenteModel($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        echo json_encode(['success' => true, 'lentes' => $model->listar()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if ($method === 'POST') {
        $resultado = $model->guardar($payload);
        if (($resultado['success'] ?? false) === false) {
            http_response_code(422);
        }
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'DELETE') {
        $id = isset($payload['id']) ? (int)$payload['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID requerido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ok = $model->eliminar($id);
        echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en API de lentes',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
