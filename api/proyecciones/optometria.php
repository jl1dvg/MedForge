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

$data = json_decode(file_get_contents("php://input"), true);

$formId = $data['form_id'] ?? null;
$estado = $data['estado'] ?? null;

if ($formId && $estado) {
    switch ($estado) {
        case 'terminado_dilatar':
            $resultado = $controller->actualizarEstado($formId, 'DILATAR');
            break;

        case 'terminado_sin_dilatar':
            $resultado = $controller->actualizarEstado($formId, 'OPTOMETRIA_TERMINADO');
            break;

        case 'iniciar_atencion':
            $resultado = $controller->actualizarEstado($formId, 'OPTOMETRIA');
            break;

        default:
            $resultado = ["success" => false, "message" => "Estado inválido proporcionado."];
            break;
    }

    echo json_encode($resultado);
} else {
    echo json_encode(["success" => false, "message" => "Parámetros insuficientes para actualizar el estado."]);
}