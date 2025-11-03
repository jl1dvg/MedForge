<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\BillingController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formId = $_POST['form_id'] ?? null;
    $hcNumber = $_POST['hc_number'] ?? null;

    if (!$formId || !$hcNumber) {
        die("Faltan parámetros.");
    }

    $billingModel = new \Models\BillingMainModel($pdo);
    $existe = $billingModel->findByFormId($formId);

    if ($existe) {
        header("Location: /views/billing/no_facturado.php?form_id=" . urlencode($formId));
        exit;
    }

    // Usar BillingController para preparar la preview y luego insertar cada detalle
    $billingId = $billingModel->insert($hcNumber, $formId);

    // Corregir fecha de creación con fecha del protocolo (mantener esta lógica)
    $stmtFecha = $pdo->prepare("SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?");
    $stmtFecha->execute([$formId]);
    $fechaInicio = $stmtFecha->fetchColumn();
    if ($fechaInicio) {
        $billingModel->updateFechaCreacion($billingId, $fechaInicio);
    }

    // Preparar los datos a insertar usando BillingController
    $billingController = new BillingController($pdo);
    $preview = $billingController->prepararPreviewFacturacion($formId, $hcNumber);

    // Insertar procedimientos
    $procedimientosModel = new \Models\BillingProcedimientosModel($pdo);
    foreach ($preview['procedimientos'] as $p) {
        $procedimientosModel->insertar($billingId, [
            'id' => null,
            'procCodigo' => $p['procCodigo'],
            'procDetalle' => $p['procDetalle'],
            'procPrecio' => $p['procPrecio']
        ]);
    }

    // Insertar insumos
    // Insertar insumos
    $insumosModel = new \Models\BillingInsumosModel($pdo);
    foreach ($preview['insumos'] as $i) {
        $insumosModel->insertar($billingId, [
            'id' => $i['id'] ?? null,
            'codigo' => $i['codigo'],
            'nombre' => $i['nombre'],
            'cantidad' => $i['cantidad'],
            'precio' => $i['precio'] ?? 0, // 👈 usar directamente el precio calculado en el preview
            'iva' => $i['iva'] ?? 1
        ]);
    }

    // Insertar derechos
    $derechosModel = new \Models\BillingDerechosModel($pdo);
    foreach ($preview['derechos'] as $d) {
        $derechosModel->insertar($billingId, [
            'id' => $d['id'] ?? null,
            'codigo' => $d['codigo'],
            'detalle' => $d['detalle'],
            'cantidad' => $d['cantidad'],
            'iva' => $d['iva'] ?? 0,
            'precioAfiliacion' => $d['precioAfiliacion'] ?? 0
        ]);
    }

    // Insertar oxígeno
    $oxigenoModel = new \Models\BillingOxigenoModel($pdo);
    foreach ($preview['oxigeno'] as $o) {
        $oxigenoModel->insertar($billingId, [
            'codigo' => $o['codigo'],
            'nombre' => $o['nombre'],
            'tiempo' => $o['tiempo'],
            'litros' => $o['litros'],
            'valor1' => $o['valor1'],
            'valor2' => $o['valor2'],
            'precio' => $o['precio']
        ]);
    }

    // Insertar anestesia
    $anestesiaModel = new \Models\BillingAnestesiaModel($pdo);
    foreach ($preview['anestesia'] as $a) {
        $anestesiaModel->insertar($billingId, [
            'codigo' => $a['codigo'],
            'nombre' => $a['nombre'],
            'tiempo' => $a['tiempo'],
            'valor2' => $a['valor2'],
            'precio' => $a['precio']
        ]);
    }
    header("Location: /views/billing/no_facturado.php?form_id=" . urlencode($formId));
    exit;
}