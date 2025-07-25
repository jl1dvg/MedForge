<?php
require_once '../../bootstrap.php'; // conexión y utilidades
use Controllers\DerivacionController;

$data = json_decode(file_get_contents("php://input"), true);
$formId = $data['form_id'] ?? null;
$codigo = $data['codigo_derivacion'] ?? null;
$hcNumber = $data['hc_number'] ?? null;
$fechaRegistro = $data['fecha_registro'] ?? null;
$fechaVigencia = $data['fecha_vigencia'] ?? null;
$referido = $data['referido'] ?? null;
$diagnostico = $data['diagnostico'] ?? null;

if ($formId && $codigo) {
    $controller = new DerivacionController($pdo);
    $resultado = $controller->guardarDerivacion($codigo, $formId, $hcNumber, $fechaRegistro, $fechaVigencia, $referido, $diagnostico);
    if ($resultado) {
        echo json_encode(["success" => true]);
    } else {
        error_log("❌ Falló el guardado de derivación: $codigo - $formId");
        echo json_encode(["success" => false]);
    }
}