<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\GuardarProtocoloController;

header('Content-Type: application/json');

try {
    $controller = new GuardarProtocoloController($pdo);
    $exito = $controller->guardar($_POST);

    if ($exito) {
        echo json_encode([
            'success' => true,
            'message' => 'Los datos se han actualizado correctamente.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo guardar la información del protocolo.'
        ]);
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}