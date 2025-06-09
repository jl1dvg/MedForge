<?php
require_once __DIR__ . '/../../bootstrap.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

ini_set('display_errors', 0);
error_reporting(0);

use Controllers\GuardarProyeccionController;

header('Content-Type: application/json; charset=UTF-8');

$controller = new GuardarProyeccionController($pdo);

$formId = $_POST['form_id'] ?? null;

if ($formId) {
    $resultado = $controller->actualizarEstado($formId, 'OPTOMETRIA');
    echo json_encode($resultado);
} else {
    echo json_encode(["success" => false, "message" => "No se pudo actualizar el estado."]);
}