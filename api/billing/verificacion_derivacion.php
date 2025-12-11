<?php
ini_set('display_errors', 0);
ini_set('html_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../storage/logs/billing_api.log');

header('Content-Type: application/json');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'],
        ]);
        exit;
    }
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "$errstr in $errfile on line $errline",
    ]);
    exit;
});

require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DerivacionController;

try {
    $controller = new DerivacionController($pdo);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    $form_ids = $_POST['form_ids'] ?? [];
    if (!is_array($form_ids)) {
        $form_ids = [$form_ids];
    }

    error_log("📥 form_ids recibidos: " . json_encode($form_ids));

    $resultado = $controller->verificarFormIds($form_ids);
    echo json_encode($resultado);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
