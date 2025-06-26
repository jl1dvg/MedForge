<?php
ini_set('display_errors', 1);
ini_set('html_errors', 0);
error_reporting(E_ALL);
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "$errstr in $errfile on line $errline"]);
    exit;
});
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DerivacionController;


header('Content-Type: application/json');
try {
    $datos = json_decode(file_get_contents('php://input'), true);
    $controller = new DerivacionController($pdo);
    $resultado = $controller->registrarProcedimientoCompleto($datos['procedimientos'] ?? []);
    echo json_encode($resultado);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
}