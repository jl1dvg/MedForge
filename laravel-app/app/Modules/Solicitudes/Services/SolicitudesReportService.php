<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Modules\Reporting\Services\ReportService;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SolicitudesReportService
{
    private SolicitudesReadParityService $readService;

    public function __construct(?SolicitudesReadParityService $readService = null)
    {
        $this->readService = $readService ?? new SolicitudesReadParityService();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{content:string,filename:string,content_type:string,disposition:string}
     */
    public function generatePdf(array $payload): array
    {
        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        if ($format !== 'pdf') {
            throw new \InvalidArgumentException('Formato no soportado.');
        }

        $reportData = $this->buildReportData($payload);
        $filename = 'solicitudes_' . date('Ymd_His') . '.pdf';
        $pdf = (new ReportService())->renderPdf('solicitudes_kanban', [
            'titulo' => 'Reporte de solicitudes',
            'generatedAt' => (new DateTimeImmutable('now'))->format('d-m-Y H:i'),
            'filters' => $reportData['filtersSummary'],
            'total' => count($reportData['rows']),
            'rows' => $reportData['rows'],
            'metricLabel' => $reportData['metricLabel'],
        ], [
            'destination' => 'S',
            'filename' => $filename,
            'mpdf' => [
                'orientation' => 'L',
                'margin_left' => 6,
                'margin_right' => 6,
                'margin_top' => 8,
                'margin_bottom' => 8,
            ],
        ]);

        if (strncmp($pdf, '%PDF-', 5) !== 0) {
            throw new \RuntimeException('No se pudo generar el PDF.');
        }

        return [
            'content' => $pdf,
            'filename' => $filename,
            'content_type' => 'application/pdf',
            'disposition' => 'inline',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{content:string,filename:string,content_type:string,disposition:string}
     */
    public function generateExcel(array $payload): array
    {
        $format = strtolower(trim((string) ($payload['format'] ?? 'excel')));
        if ($format !== 'excel') {
            throw new \InvalidArgumentException('Formato no soportado.');
        }

        $reportData = $this->buildReportData($payload);
        $filename = 'solicitudes_' . date('Ymd_His') . '.xlsx';
        $content = $this->renderExcel(
            $reportData['rows'],
            $reportData['filtersSummary'],
            [
                'title' => 'Reporte de solicitudes',
                'generated_at' => (new DateTimeImmutable('now'))->format('d-m-Y H:i'),
                'metric_label' => $reportData['metricLabel'],
                'total' => count($reportData['rows']),
            ]
        );

        if ($content === '' || strncmp($content, 'PK', 2) !== 0) {
            throw new \RuntimeException('No se pudo generar el Excel.');
        }

        return [
            'content' => $content,
            'filename' => $filename,
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'disposition' => 'attachment',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{rows:array<int,array<string,mixed>>,filtersSummary:array<int,array<string,string>>,metricLabel:?string}
     */
    private function buildReportData(array $payload): array
    {
        $filters = isset($payload['filters']) && is_array($payload['filters'])
            ? $payload['filters']
            : [];
        $quickMetric = trim((string) ($payload['quickMetric'] ?? ''));

        $result = $this->readService->kanbanData($filters);
        $rows = isset($result['data']) && is_array($result['data']) ? array_values($result['data']) : [];
        $metricLabel = $quickMetric !== '' ? $this->quickMetricLabel($quickMetric) : null;
        if ($quickMetric !== '') {
            $rows = $this->applyQuickMetric($rows, $quickMetric);
        }

        return [
            'rows' => $rows,
            'filtersSummary' => $this->filtersSummary($filters, $metricLabel),
            'metricLabel' => $metricLabel,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function applyQuickMetric(array $rows, string $quickMetric): array
    {
        return match ($quickMetric) {
            'sla_vencido' => array_values(array_filter($rows, static fn(array $row): bool => ($row['sla_status'] ?? '') === 'vencido')),
            'sla_critico' => array_values(array_filter($rows, static fn(array $row): bool => ($row['sla_status'] ?? '') === 'critico')),
            'sin_responsable' => array_values(array_filter($rows, static fn(array $row): bool => trim((string) ($row['crm_responsable_nombre'] ?? '')) === '')),
            default => $rows,
        };
    }

    private function quickMetricLabel(string $quickMetric): ?string
    {
        return match ($quickMetric) {
            'sla_vencido' => 'SLA vencido',
            'sla_critico' => 'SLA crítico',
            'sin_responsable' => 'Sin responsable',
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,string>>
     */
    private function filtersSummary(array $filters, ?string $metricLabel): array
    {
        $summary = [];
        $labels = [
            'search' => 'Buscar',
            'doctor' => 'Doctor',
            'afiliacion' => 'Afiliación',
            'afiliacion_categoria' => 'Categoría afiliación',
            'empresa_seguro' => 'Empresa afiliación',
            'sede' => 'Sede',
            'responsable_id' => 'Responsable',
            'prioridad' => 'Prioridad',
            'estado' => 'Estado/Columna',
        ];

        foreach ($labels as $key => $label) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $summary[] = ['label' => $label, 'value' => $value];
            }
        }

        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($from !== '' || $to !== '') {
            $summary[] = ['label' => 'Fecha', 'value' => sprintf('%s a %s', $from !== '' ? $from : '—', $to !== '' ? $to : '—')];
        }

        if ($metricLabel !== null && $metricLabel !== '') {
            $summary[] = ['label' => 'Quick report', 'value' => $metricLabel];
        }

        return $summary;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array<string,string>> $filtersSummary
     * @param array<string,mixed> $options
     */
    private function renderExcel(array $rows, array $filtersSummary, array $options): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        $row = 1;
        $sheet->setCellValue("A{$row}", (string) ($options['title'] ?? 'Reporte de solicitudes'));
        $sheet->mergeCells("A{$row}:K{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);

        $row++;
        $sheet->setCellValue("A{$row}", 'Generado:');
        $sheet->setCellValue("B{$row}", (string) ($options['generated_at'] ?? ''));
        $sheet->setCellValue("D{$row}", 'Total:');
        $sheet->setCellValueExplicit("E{$row}", (string) ($options['total'] ?? count($rows)), DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);

        if (!empty($options['metric_label'])) {
            $row++;
            $sheet->setCellValue("A{$row}", 'Quick report:');
            $sheet->setCellValue("B{$row}", (string) $options['metric_label']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }

        $row++;
        if ($filtersSummary !== []) {
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
        $headers = ['#', 'Solicitud ID', 'Paciente', 'HC', 'Afiliación', 'Doctor', 'Procedimiento', 'Estado/Columna', 'Prioridad', 'Fecha creación', 'Turno'];
        foreach ($headers as $index => $label) {
            $sheet->setCellValue(chr(ord('A') + $index) . $headerRow, $label);
        }
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->getFont()->setBold(true);
        $sheet->setAutoFilter("A{$headerRow}:K{$headerRow}");

        $dataStart = $headerRow + 1;
        $currentRow = $dataStart;
        if ($rows === []) {
            $sheet->setCellValue("A{$currentRow}", 'Sin registros para los filtros seleccionados.');
            $sheet->mergeCells("A{$currentRow}:K{$currentRow}");
            $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true);
        } else {
            foreach ($rows as $index => $rowData) {
                $values = [
                    (string) ($index + 1),
                    (string) ($rowData['id'] ?? '—'),
                    (string) ($rowData['full_name'] ?? '—'),
                    (string) ($rowData['hc_number'] ?? '—'),
                    (string) ($rowData['afiliacion'] ?? '—'),
                    (string) ($rowData['doctor'] ?? '—'),
                    (string) ($rowData['procedimiento'] ?? '—'),
                    (string) ($rowData['kanban_estado_label'] ?? $rowData['estado'] ?? '—'),
                    (string) ($rowData['prioridad'] ?? '—'),
                    $this->formatDate($rowData['created_at'] ?? null),
                    (string) ($rowData['turno'] ?? '—'),
                ];
                foreach ($values as $colIndex => $value) {
                    $sheet->setCellValueExplicit(chr(ord('A') + $colIndex) . $currentRow, $value, DataType::TYPE_STRING);
                }
                $currentRow++;
            }
        }

        $sheet->freezePane('A' . $dataStart);
        foreach (['A' => 5, 'B' => 12, 'C' => 24, 'D' => 12, 'E' => 18, 'F' => 22, 'G' => 32, 'H' => 18, 'I' => 12, 'J' => 18, 'K' => 12] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        if ($currentRow > $dataStart) {
            $sheet->getStyle("G{$dataStart}:G" . ($currentRow - 1))->getAlignment()->setWrapText(true);
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
            return (new DateTimeImmutable((string) $value))->format('d-m-Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
