<?php
require_once __DIR__ . '/../../bootstrap.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', 0);
error_reporting(0);

use Controllers\GuardarProyeccionController;

$controller = new GuardarProyeccionController($pdo);

// ✅ 1) Responder preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ✅ 2) GET: consultar estado actual
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'estado') {
    $formId = $_GET['form_id'] ?? null;
    if (!$formId) {
        echo json_encode(["success" => false, "message" => "form_id requerido"]);
        exit;
    }

    if (method_exists($controller, 'obtenerEstado')) {
        try {
            // Posibles valores en BD: CONSULTA, CONSULTA_TERMINADO, DILATAR, etc.
            $estadoBD = $controller->obtenerEstado($formId);

            // Mapeo a lo que consume el frontend
            $map = [
                'CONSULTA'            => 'en_proceso',
                'CONSULTA_TERMINADO'  => 'terminado_sin_dilatar',
                'DILATAR'             => 'terminado_dilatar',
            ];
            $estadoFront = $map[$estadoBD] ?? 'pendiente';

            echo json_encode(["success" => true, "estado" => $estadoFront, "estado_bd" => $estadoBD]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(["success" => false, "message" => "No se pudo consultar estado", "error" => $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(["success" => true, "estado" => "pendiente"]);
    exit;
}

// ✅ 3) POST: aceptar JSON y form-encoded
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) { $data = []; }
$data = array_merge($_POST, $data);

$formId = $data['form_id'] ?? null;
$estado = $data['estado'] ?? null;

if ($formId && $estado) {
    switch ($estado) {
        case 'terminado_dilatar':
            $resultado = $controller->actualizarEstado($formId, 'DILATAR');
            break;

        case 'terminado_sin_dilatar':
            $resultado = $controller->actualizarEstado($formId, 'CONSULTA_TERMINADO');
            break;

        case 'iniciar_atencion':
            $resultado = $controller->actualizarEstado($formId, 'CONSULTA');
            break;

        default:
            $resultado = ["success" => false, "message" => "Estado inválido proporcionado."];
            break;
    }

    echo json_encode($resultado);
} else {
    echo json_encode(["success" => false, "message" => "Parámetros insuficientes para actualizar el estado."]);
}