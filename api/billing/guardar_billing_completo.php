<?php
require_once __DIR__ . '/../../bootstrap.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Controllers\DerivacionController;

header('Content-Type: application/json');

$controller = new DerivacionController($pdo);

$logs = [];

try {
    $rawInput = file_get_contents('php://input');
    $logs[] = "📥 Datos brutos recibidos:";
    $logs[] = $rawInput;

    $data = json_decode($rawInput, true);

    if (empty($data)) {
        throw new Exception("No se recibieron datos o JSON mal formado.");
    }

    $logs[] = "✅ JSON decodificado:";
    $logs[] = print_r($data, true);

    $logs[] = "🔄 Llamando a guardarBillingCompleto...";

// 🔍 DEBUG opcional para ver los datos que llegan al backend
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo json_encode([
            'success' => true,
            'mensaje' => '✅ Datos recibidos correctamente por el API',
            'datos_recibidos' => $data,
            'logs' => $logs
        ]);
        exit;
    }

    $procedimientos = $data['procedimientos'] ?? [];
    $datos_limpios = [];

    foreach ($procedimientos as $p) {
        if (!empty($p['form_id']) && !empty($p['hc_number'])) {
            $datos_limpios[] = [
                'form_id' => (string)$p['form_id'],
                'hc_number' => (string)$p['hc_number']
            ];
        }
    }
    $logs[] = "📦 Datos enviados a guardarBillingCompleto:";
    $logs[] = print_r($datos_limpios, true);

    $resultado = $controller->guardarBillingCompleto($datos_limpios);

    $logs[] = "✅ Resultado de guardarBillingCompleto:";
    $logs[] = print_r($resultado, true);

    echo json_encode([
        'success' => true,
        'resultado' => $resultado,
        'logs' => $logs
    ]);
} catch (Exception $e) {
    $logs[] = "❌ Error en guardar_billing_completo.php: " . $e->getMessage();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'logs' => $logs
    ]);
}
