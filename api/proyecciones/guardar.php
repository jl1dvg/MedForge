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

    // Asignar "estado" a "AGENDADO" solo si el form_id no existe en la base de datos
    foreach ($data as &$item) {
        if (!isset($item['form_id']) || empty($item['form_id'])) {
            continue;
        }

        // Verificar si el form_id ya existe en la base de datos
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM procedimiento_proyectado WHERE form_id = ?");
        $stmtCheck->execute([$item['form_id']]);
        $formExiste = $stmtCheck->fetchColumn() > 0;

        if (!$formExiste) {
            $item['estado'] = 'AGENDADO';
        }
    }
    unset($item);

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
            'message' => $respuesta['message'],
            'id' => $respuesta['id'] ?? $item['form_id'],
            'form_id' => $respuesta['form_id'] ?? $item['form_id'],
            'afiliacion' => $respuesta['afiliacion'] ?? ($item['afiliacion'] ?? null),
            'estado' => $respuesta['estado'] ?? null,
            'hc_number' => $respuesta['hc_number'] ?? ($item['hcNumber'] ?? null),
            'visita_id' => $respuesta['visita_id'] ?? null,
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