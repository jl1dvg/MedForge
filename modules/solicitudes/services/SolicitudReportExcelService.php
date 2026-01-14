<?php

namespace Modules\Solicitudes\Services;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SolicitudReportExcelService
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, string>> $filtersSummary
     */
    public function render(array $rows, array $filtersSummary, array $options = []): string
    {
        $title = $options['title'] ?? 'Reporte de solicitudes';
        $generatedAt = $options['generated_at'] ?? (new DateTimeImmutable('now'))->format('d-m-Y H:i');
        $metricLabel = $options['metric_label'] ?? null;
        $total = $options['total'] ?? count($rows);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        $row = 1;
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:K{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);

        $row++;
        $sheet->setCellValue("A{$row}", 'Generado:');
        $sheet->setCellValue("B{$row}", $generatedAt);
        $sheet->setCellValue("D{$row}", 'Total:');
        $sheet->setCellValueExplicit("E{$row}", (string) $total, DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);

        if (!empty($metricLabel)) {
            $row++;
            $sheet->setCellValue("A{$row}", 'Quick report:');
            $sheet->setCellValue("B{$row}", $metricLabel);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }

        $row++;
        if (!empty($filtersSummary)) {
            $sheet->setCellValue("A{$row}", 'Filtros aplicados');
            $sheet->mergeCells("A{$row}:K{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            foreach ($filtersSummary as $filter) {
                $row++;
                $sheet->setCellValue("A{$row}", $filter['label'] ?? '');
                $sheet->setCellValue("B{$row}", $filter['value'] ?? '');
                $sheet->mergeCells("B{$row}:K{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
        } else {
            $sheet->setCellValue("A{$row}", 'Sin filtros específicos.');
            $sheet->mergeCells("A{$row}:K{$row}");
        }

        $row++;
        $headerRow = $row;
        $headers = [
            '#',
            'Solicitud ID',
            'Paciente',
            'HC',
            'Afiliación',
            'Doctor',
            'Procedimiento',
            'Estado/Columna',
            'Prioridad',
            'Fecha creación',
            'Turno',
        ];

        foreach ($headers as $index => $label) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue("{$column}{$headerRow}", $label);
        }

        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->getFont()->setBold(true);
        $sheet->setAutoFilter("A{$headerRow}:K{$headerRow}");

        $dataStart = $headerRow + 1;
        $currentRow = $dataStart;

        if (empty($rows)) {
            $sheet->setCellValue("A{$currentRow}", 'Sin registros para los filtros seleccionados.');
            $sheet->mergeCells("A{$currentRow}:K{$currentRow}");
            $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true);
        } else {
            foreach ($rows as $index => $rowData) {
                $estadoLabel = $rowData['kanban_estado_label'] ?? $rowData['estado'] ?? '—';
                $values = [
                    (string) ($index + 1),
                    (string) ($rowData['id'] ?? '—'),
                    (string) ($rowData['full_name'] ?? '—'),
                    (string) ($rowData['hc_number'] ?? '—'),
                    (string) ($rowData['afiliacion'] ?? '—'),
                    (string) ($rowData['doctor'] ?? '—'),
                    (string) ($rowData['procedimiento'] ?? '—'),
                    (string) $estadoLabel,
                    (string) ($rowData['prioridad'] ?? '—'),
                    $this->formatDate($rowData['created_at'] ?? null),
                    (string) ($rowData['turno'] ?? '—'),
                ];

                foreach ($values as $colIndex => $value) {
                    $column = chr(ord('A') + $colIndex);
                    $sheet->setCellValueExplicit(
                        "{$column}{$currentRow}",
                        $value,
                        DataType::TYPE_STRING
                    );
                }
                $currentRow++;
            }
        }

        $sheet->freezePane('A' . $dataStart);

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(22);
        $sheet->getColumnDimension('G')->setWidth(32);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(18);
        $sheet->getColumnDimension('K')->setWidth(12);

        if ($currentRow > $dataStart) {
            $sheet->getStyle("G{$dataStart}:G" . ($currentRow - 1))
                ->getAlignment()
                ->setWrapText(true);
        }

        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'r+');
        $writer->save($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        return $contents ?: '';
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return '—';
        }

        try {
            $date = new DateTimeImmutable((string) $value);
            return $date->format('d-m-Y H:i');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
