<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\IplPlanificadorController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_id = $_POST['form_id'] ?? null;
    $hc_number = $_POST['hc_number'] ?? null;
    $cod_derivacion = $_POST['codigo'] ?? null;
    $fecha_registro = $_POST['fecha_registro'] ?? null;
    $fecha_vigencia = $_POST['fecha_vigencia'] ?? null;
    $diagnostico = $_POST['diagnostico'] ?? null;

    if (!$form_id || !$hc_number) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $controller = new IplPlanificadorController($pdo);
    $resultado = $controller->guardarDerivacionManual($form_id, $hc_number, $cod_derivacion, $fecha_registro, $fecha_vigencia, $diagnostico);

    echo json_encode($resultado);
}