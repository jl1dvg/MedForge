<?php
require_once __DIR__ . '/../../bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Controllers\BillingController;

function aplicarEstiloCelda($sheet, $celda, $opciones = [])
{
    $estilo = $sheet->getStyle($celda);
    $estilo->getFont()
        ->setName('Calibri')
        ->setSize($opciones['fontSize'] ?? 14)
        ->setBold($opciones['bold'] ?? true)
        ->getColor()->setARGB($opciones['fontColor'] ?? 'FF000000');

    if (!empty($opciones['fillColor'])) {
        $estilo->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB($opciones['fillColor']);
    }

    $estilo->getAlignment()
        ->setVertical('center')
        ->setHorizontal($opciones['align'] ?? 'center');

    $estilo->getBorders()->getAllBorders()->setBorderStyle('thin');
}

$formId = $_GET['form_id'] ?? null;

if (!$formId) {
    die("Falta el parámetro form_id.");
}

// Obtener datos desde variables globales
$data = $GLOBALS['datos_facturacion'];
$formId = $GLOBALS['form_id_facturacion'];
if (!$data) {
    die("No se encontró la prefactura para form_id: $formId");
}

// Acceso directo a los datos del paciente y formulario desde $data
$pacienteInfo = $data['paciente'] ?? [];
$formDetails = $data['formulario'] ?? [];
$edadCalculada = $formDetails['edad'] ?? '';
$formDetails['fecha_inicio'] = $data['protocoloExtendido']['fecha_inicio'] ?? '';
$fechaISO = $formDetails['fecha_inicio'] ?? '';
$fecha = $fechaISO ? date('d-m-Y', strtotime($fechaISO)) : '';
$cedula = $pacienteInfo['cedula'] ?? '';
$periodo = date('Y-m', strtotime($fechaISO));

// Agregar valores de protocoloExtendido para uso en el Excel
$formDetails['fecha_inicio'] = $data['protocoloExtendido']['fecha_inicio'] ?? '';
$formDetails['diagnosticos'] = json_decode($data['protocoloExtendido']['diagnosticos'], true) ?? [];
$formDetails['diagnostico1'] = $formDetails['diagnosticos'][0]['idDiagnostico'] ?? '';
$formDetails['diagnostico2'] = $formDetails['diagnosticos'][1]['idDiagnostico'] ?? '';

$diagnosticoPrincipal = $formDetails['diagnostico1'] ?? '';
$diagnosticoSecundario = $formDetails['diagnostico2'] ?? '';

// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Prefactura');
$sheet->getColumnDimension('A')->setWidth(3.6);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(19);
$sheet->getColumnDimension('D')->setWidth(55);
$sheet->getColumnDimension('E')->setWidth(21);
$sheet->getColumnDimension('F')->setWidth(20);
$sheet->getColumnDimension('G')->setWidth(12.6);
$sheet->getColumnDimension('H')->setWidth(18);
$sheet->getColumnDimension('I')->setWidth(12);
$sheet->getColumnDimension('J')->setWidth(13);

$row = 1;

// === B1: Título principal
$sheet->setCellValue("B{$row}", "INFORME DE EVALUACION MEDICA Y FINANCIERA");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getStyle("B{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
$sheet->getRowDimension($row)->setRowHeight(33);
$sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');
$sheet->getStyle("B{$row}:J{$row}")->getBorders()->getBottom()->setBorderStyle('thin');
$row++;

// === B2: Subtítulo
$sheet->setCellValue("B{$row}", "USO DEL PRESTADOR EXTERNO");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getStyle("B{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
$sheet->getStyle("B{$row}:J{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0070C0');
$sheet->getStyle("B{$row}:J{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getRowDimension($row)->setRowHeight(21);
$sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');
$sheet->getStyle("B{$row}:J{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
$row++;

// === B3-D3: Nombre del prestador
$sheet->setCellValue("B{$row}", "NOMBRE DEL PRESTADOR ");
$sheet->mergeCells("B{$row}:C{$row}");
//$sheet->getStyle("B{$row}:C{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
$sheet->getStyle("B{$row}:C{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0070C0');
$sheet->getStyle("B{$row}:C{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getRowDimension($row)->setRowHeight(35);
$sheet->setCellValue("D{$row}", "CLINICA INTERNACIONAL DE LA VISION DE ECUADOR ");
$sheet->mergeCells("D{$row}:J{$row}");
foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $sheet->getStyle("{$col}{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
    $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');
    $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
}
$row++;

// === B4-I4: Nombre del paciente y fechas
$sheet->setCellValue("B{$row}", "NOMBRE DEL PACIENTE ");
$sheet->mergeCells("B{$row}:C{$row}");
$nombreCompleto = strtoupper($pacienteInfo['lname'] . ' ' . $pacienteInfo['lname2'] . ' ' . $pacienteInfo['fname']);
$sheet->setCellValue("D{$row}", $nombreCompleto);
$sheet->setCellValue("E{$row}", "FECHA DE INGRESO:");
$sheet->setCellValue("F{$row}", $formDetails['fecha_inicio'] ?? '');
$sheet->setCellValue("G{$row}", "FECHA DE EGRESO");
$sheet->mergeCells("G{$row}:H{$row}");
$sheet->setCellValue("I{$row}", $formDetails['fecha_inicio'] ?? '');
$sheet->mergeCells("I{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(20);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'E', 'G', 'H']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'E', 'G', 'H']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === B5-I5: Cédula, HC, edad
$sheet->setCellValue("B{$row}", "CEDULA DE IDENTIDAD");
$sheet->mergeCells("B{$row}:C{$row}");
$sheet->setCellValue("D{$row}", $pacienteInfo['hc_number']);
$sheet->setCellValue("E{$row}", "HISTORIA CLINICA");
$sheet->setCellValue("F{$row}", $pacienteInfo['hc_number']);
$sheet->mergeCells("F{$row}:G{$row}");
$sheet->setCellValue("H{$row}", "EDAD:");
$sheet->setCellValue("I{$row}", $edadCalculada);
$sheet->mergeCells("I{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(20);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'E', 'H']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'E', 'H']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === B6-G6: Diagnóstico y secundario
$sheet->setCellValue("B{$row}", "DIAGNOSTICO: ");
$sheet->mergeCells("B{$row}:C{$row}");
$sheet->setCellValue("D{$row}", $diagnosticoPrincipal);
$sheet->setCellValue("E{$row}", "DIAGNOSTICO SECUNDARIO ");
$sheet->mergeCells("E{$row}:F{$row}");
$sheet->setCellValue("G{$row}", $diagnosticoSecundario);
$sheet->mergeCells("G{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(20);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'E', 'F']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'E', 'F']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === D7: Hospital derivador
$sheet->setCellValue("A{$row}", "");
$sheet->setCellValue("D{$row}", "HOSPITAL DERIVADOR :");
$sheet->setCellValue("E{$row}", "");
$sheet->mergeCells("E{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(20);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['A', 'D']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['A', 'D']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === B8: Planilla de cargos
$sheet->setCellValue("B{$row}", "PLANILLA DE CARGOS DEL PROVEEDOR ");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(15);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === B9: Honorarios médicos
$sheet->setCellValue("B{$row}", "HONORARIOS MEDICOS ");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(15);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === Procedimientos Header
$sheet->setCellValue("B{$row}", "FECHA");
$sheet->setCellValue("C{$row}", "CODIGO (CPT/TARIFARIO)");
$sheet->setCellValue("D{$row}", "DETALLE/DESCRIPCION");
$sheet->setCellValue("E{$row}", "COSTO UNITARIO");
$sheet->mergeCells("E{$row}:H{$row}");
$sheet->setCellValue("I{$row}", "CANTIDAD");
$sheet->setCellValue("J{$row}", "COSTO TOTAL");
$sheet->getRowDimension($row)->setRowHeight(35);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === Procedimientos
foreach ($data['procedimientos'] as $index => $p) {
    $porcentaje = ($index === 0) ? 1 : 0.5;
    $precio = (float)$p['proc_precio'];
    $valorPorcentaje = $precio * $porcentaje;

    $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? ''); // Fecha
    $sheet->setCellValue("C{$row}", $p['proc_codigo']); // Código
    $sheet->setCellValue("D{$row}", $p['proc_detalle']); // Detalle
    $sheet->setCellValue("F{$row}", $precio); // Precio
    $sheet->setCellValue("G{$row}", $porcentaje * 100 . '%'); // Porcentaje
    $sheet->setCellValue("J{$row}", $valorPorcentaje); // Valor aplicado
    $sheet->setCellValue("K{$row}", 'CIRUJANO PRINCIPAL'); // Valor aplicado

    foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    $row++;
}
if (!empty($data['protocoloExtendido']['cirujano_2']) || !empty($data['protocoloExtendido']['primer_ayudante'])) {
    foreach ($data['procedimientos'] as $index => $p) {
        $porcentaje = ($index === 0) ? 0.2 : 0.1;
        $precio = (float)$p['proc_precio'];
        $valorPorcentaje = $precio * $porcentaje;

        $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? ''); // Fecha
        $sheet->setCellValue("C{$row}", $p['proc_codigo']); // Código
        $sheet->setCellValue("D{$row}", $p['proc_detalle']); // Detalle
        $sheet->setCellValue("F{$row}", $precio); // Precio
        $sheet->setCellValue("G{$row}", $porcentaje * 100 . '%'); // Porcentaje
        $sheet->setCellValue("J{$row}", $valorPorcentaje); // Valor aplicado
        $sheet->setCellValue("K{$row}", 'AYUDANTE'); // Valor aplicado


        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }

        $row++;
    }
}

// === Procedimiento con 16%
if (!empty($data['procedimientos'][0])) {
    $p = $data['procedimientos'][0];
    $precio = (float)$p['proc_precio'];
    $porcentaje = 0.16;
    $valorPorcentaje = $precio * $porcentaje;

    $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
    $sheet->setCellValue("C{$row}", $p['proc_codigo']);
    $sheet->setCellValue("D{$row}", $p['proc_detalle']);
    $sheet->setCellValue("F{$row}", $precio);
    $sheet->setCellValue("G{$row}", '16%');
    $sheet->setCellValue("J{$row}", $valorPorcentaje);
    $sheet->setCellValue("K{$row}", 'ANESTESIOLOGO'); // Valor aplicado


    foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    $row++;
}

// === Total de valores porcentuales (columna J)
$totalRow = $row;
$sheet->setCellValue("I{$totalRow}", "TOTAL:");
$sheet->setCellValue("J{$totalRow}", "=SUM(J10:J" . ($totalRow - 1) . ")");

$sheet->getStyle("B{$totalRow}:J{$totalRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle("B{$totalRow}:J{$totalRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
$sheet->getStyle("B{$totalRow}:J{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
$sheet->getRowDimension($totalRow)->setRowHeight(16);

$row++;


// === Planilla de cargos
$sheet->setCellValue("B{$row}", "MEDICINAS VALOR AL ORIGEN");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(15);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === Medicinas Header
$sheet->setCellValue("B{$row}", "FECHA");
$sheet->setCellValue("C{$row}", "CODIGO (CPT/TARIFARIO)");
$sheet->setCellValue("D{$row}", "DETALLE COSTO UNITARIO");
$sheet->setCellValue("E{$row}", "COSTO UNITARIO");
$sheet->setCellValue("F{$row}", "CANTIDAD");
$sheet->setCellValue("G{$row}", "SUB TOTAL");
$sheet->setCellValue("H{$row}", "SUB TOTAL + 10% GASTOS DE GESTION");
$sheet->setCellValue("I{$row}", "IVA 0% ");
$sheet->setCellValue("J{$row}", "TOTAL");
$sheet->getRowDimension($row)->setRowHeight(26);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === Agrupar insumos según IVA
$insumosConIVA = $data['insumos'];
$medicamentosSinIVA = $data['medicamentos'];

// Guardar inicio de bloque sin IVA (oxígeno + medicamentos sin IVA)
$inicioBloqueSinIVA = $row;

// Filas de oxígeno
foreach ($data['oxigeno'] as $o) {
    $codigo = $o['codigo'];
    $nombre = $o['nombre'];
    $valor2 = $o['valor2'];
    $tiempoLitros = (float)$o['tiempo'] * (float)$o['litros'] * (float)$o['valor1'];
    $precio = $o['precio'];

    $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
    $sheet->setCellValue("C{$row}", $codigo);
    $sheet->setCellValue("D{$row}", $nombre);
    $sheet->setCellValue("E{$row}", $valor2);
    $sheet->setCellValue("F{$row}", $tiempoLitros);
    $sheet->setCellValue("G{$row}", $precio);
    $sheet->setCellValue("J{$row}", $precio);

    foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    $row++;
}

// Filas de medicamentos sin IVA
foreach ($medicamentosSinIVA as $o) {
    $codigo = $o['codigo'];
    $nombre = $o['nombre'];
    $cantidad = $o['cantidad'];
    $precio = $o['precio'];

    $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
    $sheet->setCellValue("C{$row}", $codigo);
    $sheet->setCellValue("D{$row}", $nombre);
    $sheet->setCellValue("E{$row}", $precio / 0.10);
    $sheet->setCellValue("F{$row}", $cantidad);
    $sheet->setCellValue("G{$row}", $precio * $cantidad);
    $sheet->setCellValue("H{$row}", '');
    $sheet->setCellValue("I{$row}", 0);
    $sheet->setCellValue("J{$row}", $precio * $cantidad);

    foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    $row++;
}

// === Total oxígeno columna J (realmente total de bloque sin IVA)
$totalOxigenoRow = $row;
$finBloqueSinIVA = $row - 1;
$sheet->setCellValue("I{$totalOxigenoRow}", "TOTAL:");
$sheet->setCellValue("J{$totalOxigenoRow}", "=SUM(J{$inicioBloqueSinIVA}:J{$finBloqueSinIVA})");
$sheet->getStyle("B{$totalOxigenoRow}:J{$totalOxigenoRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle("B{$totalOxigenoRow}:J{$totalOxigenoRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
$sheet->getStyle("B{$totalOxigenoRow}:J{$totalOxigenoRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
$sheet->getRowDimension($totalOxigenoRow)->setRowHeight(16);

$row++;

// === Insumos
$sheet->setCellValue("B{$row}", "INSUMOS - VALOR AL ORIGEN ");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(15);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === Insumos Header
$sheet->setCellValue("B{$row}", "FECHA");
$sheet->setCellValue("C{$row}", "CODIGO (CPT/TARIFARIO)");
$sheet->setCellValue("D{$row}", "DETALLE COSTO UNITARIO");
$sheet->setCellValue("E{$row}", "COSTO UNITARIO");
$sheet->setCellValue("F{$row}", "CANTIDAD");
$sheet->setCellValue("G{$row}", "SUB TOTAL");
$sheet->setCellValue("H{$row}", "SUB TOTAL + 10% GASTOS DE GESTION");
$sheet->setCellValue("I{$row}", "IVA");
$sheet->setCellValue("J{$row}", "TOTAL");
$sheet->getRowDimension($row)->setRowHeight(26);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// Filas de insumos
foreach ($insumosConIVA as $o) {
    $codigo = $o['codigo'];
    $nombre = $o['nombre'];
    $cantidad = $o['cantidad'];
    $precio = $o['precio'];

    $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
    $sheet->setCellValue("C{$row}", $codigo);
    $sheet->setCellValue("D{$row}", $nombre);
    $sheet->setCellValue("E{$row}", $precio);
    $sheet->setCellValue("F{$row}", $cantidad);
    $sheet->setCellValue("G{$row}", $precio * $cantidad);
    $sheet->setCellValue("H{$row}", ($precio * $cantidad) * 0.10);
    $sheet->setCellValue("I{$row}", ($precio * $cantidad) * 0.15);
    $sheet->setCellValue("J{$row}", (($precio * $cantidad) * 1.25));

    foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    $row++;
}

// === Total insumos columna J
$totalInsumosRow = $row;
$sheet->setCellValue("I{$totalInsumosRow}", "TOTAL:");
$sheet->setCellValue("J{$totalInsumosRow}", "=SUM(J" . ($totalInsumosRow - count($insumosConIVA)) . ":J" . ($totalInsumosRow - 1) . ")");
$sheet->getStyle("B{$totalInsumosRow}:J{$totalInsumosRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle("B{$totalInsumosRow}:J{$totalInsumosRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
$sheet->getStyle("B{$totalInsumosRow}:J{$totalInsumosRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
$sheet->getRowDimension($totalInsumosRow)->setRowHeight(16);

$row++;

// === Servicios institucionales y equipos especializados
$sheet->setCellValue("B{$row}", "SERVICIOS INSTITUCIONALES Y EQUIPOS ESPECIALIZADOS");
$sheet->mergeCells("B{$row}:J{$row}");
$sheet->getRowDimension($row)->setRowHeight(15);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// === Servicios Header
$sheet->setCellValue("B{$row}", "FECHA");
$sheet->setCellValue("C{$row}", "CODIGO");
$sheet->setCellValue("D{$row}", "DETALLE COSTO UNITARIO");
$sheet->setCellValue("E{$row}", "COSTO * DIA");
$sheet->setCellValue("F{$row}", "N DE DIAS");
$sheet->setCellValue("J{$row}", "COSTO TOTAL");
$sheet->getRowDimension($row)->setRowHeight(26);

foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $fillColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FF0070C0' : null;
    $fontColor = in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ? 'FFFFFFFF' : 'FF000000';
    aplicarEstiloCelda($sheet, "{$col}{$row}", [
        'fillColor' => $fillColor,
        'fontColor' => $fontColor,
        'align' => 'center',
    ]);
}
$row++;

// Filas de servicios
foreach ($data['derechos'] as $o) {
    $codigo = $o['codigo'];
    $nombre = $o['detalle'];
    $cantidad = $o['cantidad'];
    $precio = $o['precio_afiliacion'];

    $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
    $sheet->setCellValue("C{$row}", $codigo);
    $sheet->setCellValue("D{$row}", $nombre);
    $sheet->setCellValue("E{$row}", $precio);
    $sheet->setCellValue("E{$row}", $cantidad);
    $sheet->setCellValue("J{$row}", ($precio * $cantidad));

    foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
        $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
    }

    $row++;
}

// === Total servicios columna J
// === Total servicios columna J
$totalServiciosRow = $row;
$sheet->setCellValue("I{$totalServiciosRow}", "TOTAL:");
$sheet->setCellValue("J{$totalServiciosRow}", "=SUM(J" . ($totalServiciosRow - count($data['derechos'])) . ":J" . ($totalServiciosRow - 1) . ")");
$sheet->getStyle("B{$totalServiciosRow}:J{$totalServiciosRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle("B{$totalServiciosRow}:J{$totalServiciosRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
$sheet->getStyle("B{$totalServiciosRow}:J{$totalServiciosRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
$sheet->getRowDimension($totalServiciosRow)->setRowHeight(16);
$row++;

// === Totales finales
$row += 2; // Espacio antes del resumen

$startResumenRow = $row; // para aplicar estilos en bloque

$sheet->setCellValue("G{$row}", "SUB TOTAL");
$sheet->mergeCells("G{$row}:I{$row}");
$sheet->setCellValue("J{$row}", "=J{$totalRow}+J{$totalOxigenoRow}+J{$totalInsumosRow}+J{$totalServiciosRow}");
$row++;

$sheet->setCellValue("G{$row}", "IVA 15%");
$sheet->mergeCells("G{$row}:I{$row}");
$sheet->setCellValue("J{$row}", "=J" . ($row - 1) . " * 0.15");
$row++;

$sheet->setCellValue("G{$row}", "TOTAL PLANILLA");
$sheet->mergeCells("G{$row}:I{$row}");
$sheet->setCellValue("J{$row}", "=J" . ($row - 2) . " + J" . ($row - 1));

$sheet->getStyle("G{$startResumenRow}:J{$row}")->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FF000000'], // negro
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFFFFF00'], // amarillo
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
]);


// === Anestesia
//$sheet->setCellValue("A{$row}", "Tiempo de Anestesia");
//$sheet->getStyle("A{$row}")->getFont()->setBold(true);
//$row++;
//$sheet->fromArray(['Código', 'Nombre', 'Tiempo', 'Valor2', 'Precio'], NULL, "A{$row}");
//$row++;
//foreach ($data['anestesia'] as $a) {
//    $sheet->fromArray([$a['codigo'], $a['nombre'], $a['tiempo'], $a['valor2'], $a['precio']], NULL, "A{$row}");
//    $row++;
//}

// Descargar archivo
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="prefactura_' . $formId . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;