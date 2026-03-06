<?php
require_once __DIR__ . '/../../bootstrap.php';

$formId = $_GET['form_id'] ?? null;
$hcNumber = $_GET['hc_number'] ?? null;

if (!$formId || !$hcNumber) {
    http_response_code(400);
    echo 'Faltan parámetros obligatorios.';
    return;
}

$query = http_build_query([
    'form_id' => $formId,
    'hc_number' => $hcNumber,
]);

header('Location: /v2/reports/cobertura/pdf?' . $query);
exit;
