<?php

declare(strict_types=1);

namespace App\Modules\Billing\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MspIndividualExcelBuilder
{
    /** @param array<string, mixed> $datos */
    public function build(array $datos): Spreadsheet
    {
        $pacienteInfo = $datos['paciente'] ?? [];
        $formDetails  = $datos['formulario'] ?? [];
        $formDetails['fecha_inicio'] = $datos['protocoloExtendido']['fecha_inicio'] ?? '';
        $edadCalculada = $formDetails['edad'] ?? '';

        $diagnosticos = json_decode((string) ($datos['protocoloExtendido']['diagnosticos'] ?? '[]'), true) ?? [];
        $diagnosticoPrincipal  = $diagnosticos[0]['idDiagnostico'] ?? '';
        $diagnosticoSecundario = $diagnosticos[1]['idDiagnostico'] ?? '';

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Prefactura');

        foreach (['A' => 3.6, 'B' => 14, 'C' => 19, 'D' => 55, 'E' => 21, 'F' => 20, 'G' => 12.6, 'H' => 18, 'I' => 12, 'J' => 13] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $row = 1;

        $sheet->setCellValue("B{$row}", 'INFORME DE EVALUACION MEDICA Y FINANCIERA');
        $sheet->mergeCells("B{$row}:J{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(33);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getStyle("B{$row}:J{$row}")->getBorders()->getBottom()->setBorderStyle('thin');
        $row++;

        $sheet->setCellValue("B{$row}", 'USO DEL PRESTADOR EXTERNO');
        $sheet->mergeCells("B{$row}:J{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
        $sheet->getStyle("B{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0070C0');
        $sheet->getStyle("B{$row}:J{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(21);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getStyle("B{$row}:J{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $row++;

        $sheet->setCellValue("B{$row}", 'NOMBRE DEL PRESTADOR ');
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->getStyle("B{$row}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0070C0');
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(35);
        $sheet->setCellValue("D{$row}", 'CLINICA INTERNACIONAL DE LA VISION DE ECUADOR ');
        $sheet->mergeCells("D{$row}:J{$row}");
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');
            $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        }
        $row++;

        $nombreCompleto = strtoupper(($pacienteInfo['lname'] ?? '') . ' ' . ($pacienteInfo['lname2'] ?? '') . ' ' . ($pacienteInfo['fname'] ?? ''));
        $sheet->setCellValue("B{$row}", 'NOMBRE DEL PACIENTE ');
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $nombreCompleto);
        $sheet->setCellValue("E{$row}", 'FECHA DE INGRESO:');
        $sheet->setCellValue("F{$row}", $formDetails['fecha_inicio'] ?? '');
        $sheet->setCellValue("G{$row}", 'FECHA DE EGRESO');
        $sheet->mergeCells("G{$row}:H{$row}");
        $sheet->setCellValue("I{$row}", $formDetails['fecha_inicio'] ?? '');
        $sheet->mergeCells("I{$row}:J{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $fillColor = in_array($col, ['B', 'C', 'E', 'G', 'H'], true) ? 'FF0070C0' : null;
            $fontColor = in_array($col, ['B', 'C', 'E', 'G', 'H'], true) ? 'FFFFFFFF' : 'FF000000';
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => $fillColor, 'fontColor' => $fontColor, 'align' => 'center']);
        }
        $row++;

        $sheet->setCellValue("B{$row}", 'CEDULA DE IDENTIDAD');
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $pacienteInfo['hc_number'] ?? '');
        $sheet->setCellValue("E{$row}", 'HISTORIA CLINICA');
        $sheet->setCellValue("F{$row}", $pacienteInfo['hc_number'] ?? '');
        $sheet->mergeCells("F{$row}:G{$row}");
        $sheet->setCellValue("H{$row}", 'EDAD:');
        $sheet->setCellValue("I{$row}", $edadCalculada);
        $sheet->mergeCells("I{$row}:J{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $fillColor = in_array($col, ['B', 'C', 'E', 'H'], true) ? 'FF0070C0' : null;
            $fontColor = in_array($col, ['B', 'C', 'E', 'H'], true) ? 'FFFFFFFF' : 'FF000000';
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => $fillColor, 'fontColor' => $fontColor, 'align' => 'center']);
        }
        $row++;

        $sheet->setCellValue("B{$row}", 'DIAGNOSTICO: ');
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $diagnosticoPrincipal);
        $sheet->setCellValue("E{$row}", 'DIAGNOSTICO SECUNDARIO ');
        $sheet->mergeCells("E{$row}:F{$row}");
        $sheet->setCellValue("G{$row}", $diagnosticoSecundario);
        $sheet->mergeCells("G{$row}:J{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $fillColor = in_array($col, ['B', 'C', 'E', 'F'], true) ? 'FF0070C0' : null;
            $fontColor = in_array($col, ['B', 'C', 'E', 'F'], true) ? 'FFFFFFFF' : 'FF000000';
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => $fillColor, 'fontColor' => $fontColor, 'align' => 'center']);
        }
        $row++;

        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("D{$row}", 'HOSPITAL DERIVADOR :');
        $sheet->setCellValue("E{$row}", '');
        $sheet->mergeCells("E{$row}:J{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $fillColor = in_array($col, ['A', 'D'], true) ? 'FF0070C0' : null;
            $fontColor = in_array($col, ['A', 'D'], true) ? 'FFFFFFFF' : 'FF000000';
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => $fillColor, 'fontColor' => $fontColor, 'align' => 'center']);
        }
        $row++;

        $this->sectionHeader($sheet, $row, 'PLANILLA DE CARGOS DEL PROVEEDOR ');
        $row++;
        $this->sectionHeader($sheet, $row, 'HONORARIOS MEDICOS ');
        $row++;

        $sheet->setCellValue("B{$row}", 'FECHA');
        $sheet->setCellValue("C{$row}", 'CODIGO (CPT/TARIFARIO)');
        $sheet->setCellValue("D{$row}", 'DETALLE/DESCRIPCION');
        $sheet->setCellValue("E{$row}", 'COSTO UNITARIO');
        $sheet->mergeCells("E{$row}:H{$row}");
        $sheet->setCellValue("I{$row}", 'CANTIDAD');
        $sheet->setCellValue("J{$row}", 'COSTO TOTAL');
        $sheet->getRowDimension($row)->setRowHeight(35);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => 'FF0070C0', 'fontColor' => 'FFFFFFFF', 'align' => 'center']);
        }
        $row++;

        foreach ($datos['procedimientos'] as $index => $p) {
            $porcentaje      = ($index === 0) ? 1 : 0.5;
            $precio          = (float) $p['proc_precio'];
            $valorPorcentaje = $precio * $porcentaje;

            $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
            $sheet->setCellValue("C{$row}", $p['proc_codigo']);
            $sheet->setCellValue("D{$row}", $p['proc_detalle']);
            $sheet->setCellValue("F{$row}", $precio);
            $sheet->setCellValue("G{$row}", $porcentaje * 100 . '%');
            $sheet->setCellValue("J{$row}", $valorPorcentaje);
            $sheet->setCellValue("K{$row}", 'CIRUJANO PRINCIPAL');
            foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        if (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante'])) {
            foreach ($datos['procedimientos'] as $index => $p) {
                $porcentaje      = ($index === 0) ? 0.2 : 0.1;
                $precio          = (float) $p['proc_precio'];
                $valorPorcentaje = $precio * $porcentaje;

                $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
                $sheet->setCellValue("C{$row}", $p['proc_codigo']);
                $sheet->setCellValue("D{$row}", $p['proc_detalle']);
                $sheet->setCellValue("F{$row}", $precio);
                $sheet->setCellValue("G{$row}", $porcentaje * 100 . '%');
                $sheet->setCellValue("J{$row}", $valorPorcentaje);
                $sheet->setCellValue("K{$row}", 'AYUDANTE');
                foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                    $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
                }
                $row++;
            }
        }

        if (!empty($datos['procedimientos'][0])) {
            $p           = $datos['procedimientos'][0];
            $precio      = (float) $p['proc_precio'];
            $porcentaje  = 0.16;
            $valorPorcentaje = $precio * $porcentaje;

            $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
            $sheet->setCellValue("C{$row}", $p['proc_codigo']);
            $sheet->setCellValue("D{$row}", $p['proc_detalle']);
            $sheet->setCellValue("F{$row}", $precio);
            $sheet->setCellValue("G{$row}", '16%');
            $sheet->setCellValue("J{$row}", $valorPorcentaje);
            $sheet->setCellValue("K{$row}", 'ANESTESIOLOGO');
            foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue("I{$totalRow}", 'TOTAL:');
        $sheet->setCellValue("J{$totalRow}", '=SUM(J10:J' . ($totalRow - 1) . ')');
        $sheet->getStyle("B{$totalRow}:J{$totalRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("B{$totalRow}:J{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        $sheet->getStyle("B{$totalRow}:J{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getRowDimension($totalRow)->setRowHeight(16);
        $row++;

        [$row, $totalOxigenoRow] = $this->buildMedicinaSection($sheet, $row, $datos, $formDetails);
        $row++;
        [$row, $totalInsumosRow] = $this->buildInsumosSection($sheet, $row, $datos, $formDetails);
        $row++;
        [$row, $totalServiciosRow] = $this->buildServiciosSection($sheet, $row, $datos, $formDetails);
        $row++;

        $row += 2;
        $startResumenRow = $row;
        $sheet->setCellValue("G{$row}", 'SUB TOTAL');
        $sheet->mergeCells("G{$row}:I{$row}");
        $sheet->setCellValue("J{$row}", "=J{$totalRow}+J{$totalOxigenoRow}+J{$totalInsumosRow}+J{$totalServiciosRow}");
        $row++;
        $sheet->setCellValue("G{$row}", 'IVA 15%');
        $sheet->mergeCells("G{$row}:I{$row}");
        $sheet->setCellValue("J{$row}", '=J' . ($row - 1) . ' * 0.15');
        $row++;
        $sheet->setCellValue("G{$row}", 'TOTAL PLANILLA');
        $sheet->mergeCells("G{$row}:I{$row}");
        $sheet->setCellValue("J{$row}", '=J' . ($row - 2) . ' + J' . ($row - 1));
        $sheet->getStyle("G{$startResumenRow}:J{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FF000000']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFF00']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        return $spreadsheet;
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $formDetails
     * @return array{0:int,1:int}
     */
    private function buildMedicinaSection(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $datos, array $formDetails): array
    {
        $this->sectionHeader($sheet, $row, 'MEDICINAS VALOR AL ORIGEN');
        $row++;
        foreach (['B' => 'FECHA', 'C' => 'CODIGO (CPT/TARIFARIO)', 'D' => 'DETALLE COSTO UNITARIO', 'E' => 'COSTO UNITARIO', 'F' => 'CANTIDAD', 'G' => 'SUB TOTAL', 'H' => 'SUB TOTAL + 10% GASTOS DE GESTION', 'I' => 'IVA 0% ', 'J' => 'TOTAL'] as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getRowDimension($row)->setRowHeight(26);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => 'FF0070C0', 'fontColor' => 'FFFFFFFF', 'align' => 'center']);
        }
        $row++;
        $inicioBloqueSinIVA = $row;

        foreach ($datos['oxigeno'] as $o) {
            $tiempoLitros = (float) $o['tiempo'] * (float) $o['litros'] * (float) ($o['valor1'] ?? 1);
            $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
            $sheet->setCellValue("C{$row}", $o['codigo']);
            $sheet->setCellValue("D{$row}", $o['nombre']);
            $sheet->setCellValue("E{$row}", $o['valor2']);
            $sheet->setCellValue("F{$row}", $tiempoLitros);
            $sheet->setCellValue("G{$row}", $o['precio']);
            $sheet->setCellValue("J{$row}", $o['precio']);
            foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        foreach ($datos['medicamentos'] as $o) {
            $precio = $o['precio'];
            $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
            $sheet->setCellValue("C{$row}", $o['codigo']);
            $sheet->setCellValue("D{$row}", $o['nombre']);
            $sheet->setCellValue("E{$row}", $precio / 0.10);
            $sheet->setCellValue("F{$row}", $o['cantidad']);
            $sheet->setCellValue("G{$row}", $precio * $o['cantidad']);
            $sheet->setCellValue("H{$row}", '');
            $sheet->setCellValue("I{$row}", 0);
            $sheet->setCellValue("J{$row}", $precio * $o['cantidad']);
            foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        $totalOxigenoRow = $row;
        $finBloqueSinIVA = $row - 1;
        $sheet->setCellValue("I{$totalOxigenoRow}", 'TOTAL:');
        $sheet->setCellValue("J{$totalOxigenoRow}", "=SUM(J{$inicioBloqueSinIVA}:J{$finBloqueSinIVA})");
        $sheet->getStyle("B{$totalOxigenoRow}:J{$totalOxigenoRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("B{$totalOxigenoRow}:J{$totalOxigenoRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        $sheet->getStyle("B{$totalOxigenoRow}:J{$totalOxigenoRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getRowDimension($totalOxigenoRow)->setRowHeight(16);

        return [$row, $totalOxigenoRow];
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $formDetails
     * @return array{0:int,1:int}
     */
    private function buildInsumosSection(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $datos, array $formDetails): array
    {
        $this->sectionHeader($sheet, $row, 'INSUMOS - VALOR AL ORIGEN ');
        $row++;
        foreach (['B' => 'FECHA', 'C' => 'CODIGO (CPT/TARIFARIO)', 'D' => 'DETALLE COSTO UNITARIO', 'E' => 'COSTO UNITARIO', 'F' => 'CANTIDAD', 'G' => 'SUB TOTAL', 'H' => 'SUB TOTAL + 10% GASTOS DE GESTION', 'I' => 'IVA', 'J' => 'TOTAL'] as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getRowDimension($row)->setRowHeight(26);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => 'FF0070C0', 'fontColor' => 'FFFFFFFF', 'align' => 'center']);
        }
        $row++;
        $inicioInsumos = $row;

        foreach ($datos['insumos'] as $o) {
            $precio = $o['precio'];
            $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
            $sheet->setCellValue("C{$row}", $o['codigo']);
            $sheet->setCellValue("D{$row}", $o['nombre']);
            $sheet->setCellValue("E{$row}", $precio);
            $sheet->setCellValue("F{$row}", $o['cantidad']);
            $sheet->setCellValue("G{$row}", $precio * $o['cantidad']);
            $sheet->setCellValue("H{$row}", ($precio * $o['cantidad']) * 0.10);
            $sheet->setCellValue("I{$row}", ($precio * $o['cantidad']) * 0.15);
            $sheet->setCellValue("J{$row}", ($precio * $o['cantidad']) * 1.25);
            foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        $totalInsumosRow = $row;
        $sheet->setCellValue("I{$totalInsumosRow}", 'TOTAL:');
        $sheet->setCellValue("J{$totalInsumosRow}", '=SUM(J' . $inicioInsumos . ':J' . ($totalInsumosRow - 1) . ')');
        $sheet->getStyle("B{$totalInsumosRow}:J{$totalInsumosRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("B{$totalInsumosRow}:J{$totalInsumosRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        $sheet->getStyle("B{$totalInsumosRow}:J{$totalInsumosRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getRowDimension($totalInsumosRow)->setRowHeight(16);

        return [$row, $totalInsumosRow];
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $formDetails
     * @return array{0:int,1:int}
     */
    private function buildServiciosSection(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $datos, array $formDetails): array
    {
        $this->sectionHeader($sheet, $row, 'SERVICIOS INSTITUCIONALES Y EQUIPOS ESPECIALIZADOS');
        $row++;
        foreach (['B' => 'FECHA', 'C' => 'CODIGO', 'D' => 'DETALLE COSTO UNITARIO', 'E' => 'COSTO * DIA', 'F' => 'N DE DIAS', 'J' => 'COSTO TOTAL'] as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getRowDimension($row)->setRowHeight(26);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", ['fillColor' => 'FF0070C0', 'fontColor' => 'FFFFFFFF', 'align' => 'center']);
        }
        $row++;
        $inicioServicios = $row;

        foreach ($datos['derechos'] as $o) {
            $precio   = $o['precio_afiliacion'];
            $cantidad = $o['cantidad'];
            $sheet->setCellValue("B{$row}", $formDetails['fecha_inicio'] ?? '');
            $sheet->setCellValue("C{$row}", $o['codigo']);
            $sheet->setCellValue("D{$row}", $o['detalle']);
            $sheet->setCellValue("E{$row}", $precio);
            $sheet->setCellValue("F{$row}", $cantidad);
            $sheet->setCellValue("J{$row}", $precio * $cantidad);
            foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getBorders()->getAllBorders()->setBorderStyle('thin');
            }
            $row++;
        }

        $totalServiciosRow = $row;
        $sheet->setCellValue("I{$totalServiciosRow}", 'TOTAL:');
        $sheet->setCellValue("J{$totalServiciosRow}", '=SUM(J' . $inicioServicios . ':J' . ($totalServiciosRow - 1) . ')');
        $sheet->getStyle("B{$totalServiciosRow}:J{$totalServiciosRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("B{$totalServiciosRow}:J{$totalServiciosRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        $sheet->getStyle("B{$totalServiciosRow}:J{$totalServiciosRow}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getRowDimension($totalServiciosRow)->setRowHeight(16);

        return [$row, $totalServiciosRow];
    }

    private function sectionHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, string $label): void
    {
        $sheet->setCellValue("B{$row}", $label);
        $sheet->mergeCells("B{$row}:J{$row}");
        $sheet->getRowDimension($row)->setRowHeight(15);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $this->aplicarEstiloCelda($sheet, "{$col}{$row}", [
                'fillColor' => ($col === 'B') ? 'FF0070C0' : null,
                'fontColor' => ($col === 'B') ? 'FFFFFFFF' : 'FF000000',
                'align'     => 'center',
            ]);
        }
    }

    /** @param array<string, mixed> $opciones */
    private function aplicarEstiloCelda(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $celda, array $opciones = []): void
    {
        $estilo = $sheet->getStyle($celda);
        $estilo->getFont()
            ->setName('Calibri')
            ->setSize((int) ($opciones['fontSize'] ?? 14))
            ->setBold($opciones['bold'] ?? true)
            ->getColor()->setARGB((string) ($opciones['fontColor'] ?? 'FF000000'));

        if (!empty($opciones['fillColor'])) {
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB((string) $opciones['fillColor']);
        }

        $estilo->getAlignment()
            ->setVertical('center')
            ->setHorizontal((string) ($opciones['align'] ?? 'center'));

        $estilo->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
}
