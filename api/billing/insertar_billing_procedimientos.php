<?php
require_once __DIR__ . '/../../bootstrap.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Controllers\DerivacionController;

header('Content-Type: application/json');

$controller = new DerivacionController($pdo);

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data)) {
        throw new Exception("No se recibieron datos.");
    }

    $resultado = $controller->insertarBillingProcedimientos($data);

    echo json_encode([
        'success' => true,
        'resultado' => $resultado
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
