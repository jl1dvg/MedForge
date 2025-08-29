<?php
require_once __DIR__ . '/../../../bootstrap.php';

$form_id = $_POST['form_id'] ?? null;
$hc_number = $_POST['hc_number'] ?? null;
$insumos = $_POST['insumos'] ?? null;
$medicamentos = $_POST['medicamentos'] ?? null;

if (!$form_id || !$hc_number) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

$sql = "UPDATE protocolo_data SET ";
$params = [];
$setClauses = [];

if ($insumos !== null) {
    $setClauses[] = "insumos = :insumos";
    $params[':insumos'] = $insumos;
}
if ($medicamentos !== null) {
    $setClauses[] = "medicamentos = :medicamentos";
    $params[':medicamentos'] = $medicamentos;
}

$sql .= implode(', ', $setClauses) . " WHERE form_id = :form_id AND hc_number = :hc_number";
$params[':form_id'] = $form_id;
$params[':hc_number'] = $hc_number;

$stmt = $pdo->prepare($sql);
$success = $stmt->execute($params);

echo json_encode(['success' => $success]);