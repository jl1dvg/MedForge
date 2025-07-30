<?php
require '../../bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Obtener los datos enviados desde el script JavaScript
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['data'])) {
    die('No se proporcionaron datos para exportar.');
}

$ws_data = $data['data'];

// Crear una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Agregar datos a la hoja
$row = 1;
foreach ($ws_data as $rowData) {
    $col = 'A';
    foreach ($rowData as $cellData) {
        $sheet->setCellValue($col . $row, $cellData);
        $col++;
    }
    $row++;
}

// Combinar celdas y aplicar estilos personalizados
$sheet->mergeCells('D2:F2');
$sheet->mergeCells('G2:I2');
$sheet->mergeCells('J2:M2');

$combinedHeaderStyleArray = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
        'size' => 12,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'argb' => 'FF0000FF', // Fondo azul
        ],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];

$sheet->getStyle('D2:F2')->applyFromArray($combinedHeaderStyleArray);
$sheet->getStyle('G2:I2')->applyFromArray($combinedHeaderStyleArray);
$sheet->getStyle('J2:M2')->applyFromArray($combinedHeaderStyleArray);

// Aplicar estilo a las celdas de encabezado (la tercera fila)
$headerStyleArray = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
        'size' => 12,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'argb' => 'FF0000FF', // Fondo azul
        ],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];

$sheet->getStyle('A3:M3')->applyFromArray($headerStyleArray);

// Aplicar estilo a las celdas de datos
$dataStyleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];

$sheet->getStyle('A4:M' . ($row - 1))->applyFromArray($dataStyleArray);

// Guardar el archivo Excel y permitir la descarga
$writer = new Xlsx($spreadsheet);
$filename = 'SurgeryDataFiltered.xlsx';

// Enviar las cabeceras para descargar el archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>