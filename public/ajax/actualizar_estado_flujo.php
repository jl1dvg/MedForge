<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarProyeccionController;
use Throwable;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) && !isset($input['form_id'])) {
    echo json_encode(['success' => false, 'error' => 'Falta el identificador del registro (id o form_id).']);
    exit;
}

if (!isset($input['estado'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$formId = $input['form_id'] ?? $input['id'];

$pacienteController = new GuardarProyeccionController($pdo);
try {
    $resultado = $pacienteController->actualizarEstado($formId, $input['estado']);
} catch (Throwable $exception) {
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo actualizar el estado: ' . $exception->getMessage(),
    ]);
    exit;
}

echo json_encode($resultado);
