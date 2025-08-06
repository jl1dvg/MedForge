<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\IplPlanificadorController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_id = $_POST['form_id'] ?? null;
    $hc_number = $_POST['hc_number'] ?? null;
    $cod_derivacion = $_POST['codigo'] ?? null;
    $fecha_registro = $_POST['fecha_registro'] ?? null;
    $fecha_vigencia = $_POST['fecha_vigencia'] ?? null;
    $diagnostico = $_POST['diagnostico'] ?? null;
    $guardar_derivacion = $_POST['guardar_derivacion'] ?? null;
    $guardar_planificacion = $_POST['guardar_planificacion'] ?? null;
    $nroSesion = $_POST['nro_sesion'] ?? null;
    $fechaFicticia = $_POST['fecha_ficticia'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $doctor = $_POST['doctor'] ?? null;
    $procedimiento = $_POST['procedimiento'] ?? null;


    if (!$form_id || !$hc_number) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $controller = new IplPlanificadorController($pdo);

    $derivacionGuardada = false;
    $planificacionGuardada = false;

    // Verificar si ya existen los registros
    $existencias = $controller->existePlanificacionYDerivacion($form_id, $hc_number);

    if (!$existencias['derivacion']) {
        $resDerivacion = $controller->guardarDerivacionManual($form_id, $hc_number, $cod_derivacion, $fecha_registro, $fecha_vigencia, $diagnostico);
        $derivacionGuardada = $resDerivacion['success'];
        $derivacionId = $pdo->lastInsertId();
    } else {
        $derivacionGuardada = true;
        // Obtener ID derivación existente
        $stmt = $pdo->prepare("SELECT id FROM derivaciones_form_id WHERE form_id = ? AND hc_number = ?");
        $stmt->execute([$form_id, $hc_number]);
        $derivacionId = $stmt->fetchColumn();
    }

    if (!$existencias['planificacion']) {
        $resPlanificacion = $controller->guardarPlanificacionManual(
            $hc_number, $form_id, $nroSesion, $fechaFicticia, $form_id, $estado,
            $derivacionId, $doctor, $procedimiento, $diagnostico
        );
        $planificacionGuardada = $resPlanificacion['success'];
    } else {
        $planificacionGuardada = true;
    }

    // --- Nueva sección de respuesta unificada ---
    $respuesta = ['success' => true];
    echo json_encode($respuesta);
    exit;
}