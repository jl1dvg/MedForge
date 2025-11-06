<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap.php';
}

use Controllers\BillingController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var PDO $pdo */
global $pdo;

$formIds = isset($_GET['form_id']) ? explode(',', $_GET['form_id']) : [];
if (empty($formIds)) {
    die("Falta el parámetro form_id.");
}

$billingController = new BillingController($pdo);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('IESS');
$row = 1;

// Encabezados
$headers = [
    'form_id', 'hc_number', 'nombre', 'proc_codigo', 'proc_detalle', 'proc_precio'
];
foreach ($headers as $i => $title) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . $row, strtoupper($title));
}
$row++;

// Procesar cada form_id
foreach ($formIds as $fid) {
    $data = $billingController->obtenerDatos($fid);
    if (!$data) continue;

    $paciente = $data['paciente'] ?? [];
    $procedimientos = $data['procedimientos'] ?? [];

    foreach ($procedimientos as $p) {
        $sheet->setCellValue("A{$row}", $fid);
        $sheet->setCellValue("B{$row}", $paciente['hc_number'] ?? '');
        $sheet->setCellValue("C{$row}", trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? '') . ' ' . ($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? '')));
        $sheet->setCellValue("D{$row}", $p['proc_codigo'] ?? '');
        $sheet->setCellValue("E{$row}", $p['proc_detalle'] ?? '');
        $sheet->setCellValue("F{$row}", $p['proc_precio'] ?? '');
        $row++;
    }
}

// Descargar
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="reporte_iess_lote.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;