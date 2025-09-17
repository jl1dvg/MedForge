<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\BillingController;

header('Content-Type: application/json; charset=utf-8');

$formId = $_GET['form_id'] ?? null;
$hcNumber = $_GET['hc_number'] ?? null;

if (!$formId || !$hcNumber) {
    echo json_encode([
        "success" => false,
        "message" => "Parámetros faltantes"
    ]);
    exit;
}


try {
    $billingController = new BillingController($pdo);
    $preview = $billingController->prepararPreviewFacturacion($formId, $hcNumber);

    echo json_encode([
        "success" => true,
        "procedimientos" => $preview['procedimientos'],
        "insumos" => $preview['insumos'],
        "derechos" => $preview['derechos'],
        "oxigeno" => $preview['oxigeno'],
        "anestesia" => $preview['anestesia']
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
