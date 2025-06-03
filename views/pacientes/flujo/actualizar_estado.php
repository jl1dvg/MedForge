<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\GuardarProyeccionController;

header('Content-Type: application/json; charset=UTF-8');
ob_start(); // ← Captura cualquier salida inesperada

// Manejo de errores visibles solo en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Leer el JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['form_id'], $input['estado'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$form_id = (int)$input['form_id'];
$estado = trim($input['estado']);

if ($form_id <= 0 || $estado === '') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'ID o estado inválido']);
    exit;
}

try {
    $controller = new GuardarProyeccionController($pdo);
    $result = $controller->actualizarEstado($form_id, $estado);

    ob_clean(); // limpia cualquier salida previa
    echo json_encode($result);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Excepción: ' . $e->getMessage()
    ]);
}