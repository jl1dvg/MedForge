<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap.php';
}
require_once BASE_PATH . '/helpers/InformesHelper.php';

use Controllers\BillingController;
use Modules\Pacientes\Services\PacienteService;
use Helpers\InformesHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$billingController = new BillingController($pdo);
$pacienteService = new PacienteService($pdo);

$mes = $_GET['mes'] ?? null;
$facturas = $billingController->obtenerFacturasDisponibles();

$pacientesCache = [];
$datosCache = [];
$filtros = ['mes' => $mes];

$consolidado = InformesHelper::obtenerConsolidadoFiltrado($facturas, $filtros, $billingController, $pacienteService, $pacientesCache, $datosCache);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Consolidado ISSPOL");

// Encabezados
$headers = ['# Expediente', 'Cédula', 'Apellidos', 'Nombre', 'Fecha Ingreso', 'Fecha Egreso', 'CIE10', 'Descripción', '# Hist. C.', 'Edad', 'Ge', 'Items', 'Monto Sol.'];
$sheet->fromArray($headers, null, 'A1');

$row = 2;
$n = 1;
foreach ($consolidado as $mes => $pacientes) {
    foreach ($pacientes as $p) {
        // Fetch patient details from DB
        $paciente = $pacienteService->getPatientDetails($p['hc_number']);
        $apellido = trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? ''));
        $nombre = trim(($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? ''));
        $cedula = $paciente['cedula'] ?? '';
        $edad = $pacienteService->calcularEdad($paciente['fecha_nacimiento'] ?? null);
        $genero = isset($paciente['sexo']) && $paciente['sexo'] ? strtoupper(substr($paciente['sexo'], 0, 1)) : '--';

        $sheet->setCellValue("A$row", "ISSPOL-{$n}");
        $sheet->setCellValue("B$row", $p['hc_number'] ?? '');
        $sheet->setCellValue("C$row", $apellido);
        $sheet->setCellValue("D$row", $nombre);
        $sheet->setCellValue("E$row", isset($p['fecha']) ? date('d/m/Y', strtotime($p['fecha'])) : '');
        $sheet->setCellValue("F$row", isset($p['fecha']) ? date('d/m/Y', strtotime($p['fecha'])) : '');
        $sheet->setCellValue("G$row", $p['cie10'] ?? '--');
        $sheet->setCellValue("H$row", $p['descripcion'] ?? '--');
        $sheet->setCellValue("I$row", $p['hc_number'] ?? '');
        $sheet->setCellValue("J$row", $edad ?? '');
        $sheet->setCellValue("K$row", $genero ?? '');
        $sheet->setCellValue("L$row", $p['items'] ?? 75);
        $sheet->setCellValue("M$row", isset($p['total']) ? number_format($p['total'], 2, '.', '') : '');
        $row++;
        $n++;
    }
}

// Headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="consolidado_isspol.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;