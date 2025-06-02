<?php
require_once __DIR__ . '/../../bootstrap.php';

ini_set('display_errors', 0);
error_reporting(0);

use Controllers\GuardarProyeccionController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Leer JSON recibido
$data = json_decode(file_get_contents('php://input'), true);

error_log("🧪 Contenido bruto recibido: " . file_get_contents('php://input'));
error_log("🧪 Contenido decodificado: " . print_r($data, true));

// Verificar campos faltantes por cada ítem
foreach ($data as $index => $item) {
    if (
        empty($item['hcNumber']) ||
        empty($item['form_id']) ||
        empty($item['procedimiento_proyectado'])
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Datos faltantes o incompletos en el índice $index: " .
                (empty($item['hcNumber']) ? "hcNumber " : "") .
                (empty($item['form_id']) ? "form_id " : "") .
                (empty($item['procedimiento_proyectado']) ? "procedimiento_proyectado" : "")
        ]);
        exit;
    }
}

if ($data === null) {
    error_log("❌ JSON mal formado o vacío: " . file_get_contents('php://input'));
    echo json_encode(["success" => false, "message" => "JSON mal formado o vacío"]);
    exit;
}

error_log("✅ JSON recibido correctamente: " . print_r($data, true));

// Log opcional para depuración
error_log("Datos recibidos: " . print_r($data, true));

// Ejecutar guardado
$controller = new GuardarProyeccionController($pdo);
$respuestas = [];

foreach ($data as $index => $item) {
    $respuesta = $controller->guardar($item);
    $respuestas[] = [
        'index' => $index,
        'success' => $respuesta['success'],
        'message' => $respuesta['message']
    ];
}

echo json_encode(["success" => true, "detalles" => $respuestas]);
exit;