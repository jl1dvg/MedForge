<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\GuardarProyeccionController;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'], $input['estado'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$pacienteController = new GuardarProyeccionController($pdo);
$resultado = $pacienteController->actualizarEstadoFlujo($input['id'], $input['estado']);

echo json_encode($resultado);