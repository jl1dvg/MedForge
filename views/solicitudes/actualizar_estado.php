<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\SolicitudController;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $estado = $input['estado'] ?? null;

    if (!$id || !$estado) {
        throw new Exception("ID o estado inválido");
    }

    $controller = new SolicitudController($pdo);
    $controller->actualizarEstado($id, $estado);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}