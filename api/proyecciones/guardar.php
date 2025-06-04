<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../bootstrap.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
error_reporting(0);

use Controllers\GuardarProyeccionController;

try {
    $rawInput = file_get_contents('php://input');
    error_log("🧪 Contenido bruto recibido: " . $rawInput);
    $data = json_decode($rawInput, true);

    // Forzar el campo "estado" a "AGENDADO" en cada entrada
    foreach ($data as &$item) {
        $item['estado'] = 'AGENDADO';
    }
    unset($item); // rompe la referencia

    if ($data === null) {
        error_log("❌ JSON mal formado o vacío: " . $rawInput);
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "JSON mal formado o vacío"]);
        exit;
    }

    foreach ($data as $index => $item) {
        if (
            !is_array($item) ||
            !array_key_exists('hcNumber', $item) ||
            !array_key_exists('form_id', $item) ||
            !array_key_exists('procedimiento_proyectado', $item) ||
            empty($item['hcNumber']) ||
            empty($item['form_id']) ||
            empty($item['procedimiento_proyectado'])
        ) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Datos faltantes o incompletos en el índice $index: " .
                    (!array_key_exists('hcNumber', $item) || empty($item['hcNumber']) ? "hcNumber " : "") .
                    (!array_key_exists('form_id', $item) || empty($item['form_id']) ? "form_id " : "") .
                    (!array_key_exists('procedimiento_proyectado', $item) || empty($item['procedimiento_proyectado']) ? "procedimiento_proyectado" : "")
            ]);
            exit;
        }
    }

    $controller = new GuardarProyeccionController($pdo);
    $respuestas = [];

    foreach ($data as $index => $item) {
        if (count(array_filter($item, function ($v) {
                return $v === null;
            })) > 0) {
            throw new Exception("Parámetros con valor nulo detectados en el índice $index: " . json_encode($item));
        }
        $respuesta = $controller->guardar($item);
        if (!isset($respuesta['success']) || $respuesta['success'] === false) {
            error_log("❌ Error en el índice $index al guardar: " . print_r($respuesta, true));
        }
        $respuestas[] = [
            'index' => $index,
            'success' => $respuesta['success'],
            'message' => $respuesta['message']
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "detalles" => $respuestas
    ]);
    exit;

} catch (Throwable $e) {
    error_log("🛑 Excepción atrapada en guardar.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error interno: " . $e->getMessage()
    ]);
    exit;
}