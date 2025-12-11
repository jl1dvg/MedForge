<?php
ini_set('display_errors', 0);
ini_set('html_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../storage/logs/billing_api.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "$errstr in $errfile on line $errline"]);
    exit;
});

require_once __DIR__ . '/../../bootstrap.php';
use Controllers\DerivacionController;

try {
    $raw = file_get_contents('php://input');
    $datos = json_decode($raw ?: 'null', true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'JSON inválido o vacío',
            'raw' => $raw,
        ]);
        exit;
    }

    $procedimientos = $datos['procedimientos'] ?? [];
    if (!is_array($procedimientos)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Formato inválido: se esperaba "procedimientos" como arreglo',
        ]);
        exit;
    }

    $controller = new DerivacionController($pdo);
    $resultado = $controller->registrarProcedimientoCompleto($procedimientos);
    echo json_encode($resultado);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
}
