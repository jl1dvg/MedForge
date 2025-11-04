<?php
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Reporting\Controllers\ReportController;

// Capturar parámetros
$form_id = $_GET['form_id'] ?? null;
$hc_number = $_GET['hc_number'] ?? null;
if ($form_id && $hc_number) {
    (new ReportController($pdo))->solicitudQuirurgica([
        'form_id' => $form_id,
        'hc_number' => $hc_number,
    ]);
} else {
    http_response_code(400);
    echo 'Faltan parámetros obligatorios.';
}
