<?php

namespace App\Modules\Whatsapp\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Transforms a daily report array into exportable rows (CSV / XLSX).
 *
 * read_only=true, db_writes=0. No messages sent. No DB writes.
 * Separation: DailyReportService = calculates data; this = formats output.
 */
class WhatsappOperationalDailyReportExportService
{
    private const HEADERS = [
        'section', 'metric', 'key', 'label', 'value',
        'critical', 'high', 'medium', 'low', 'notes',
    ];

    /**
     * Convert the report array into a flat list of rows ready to export.
     *
     * @param  array<string,mixed> $report
     * @return array<int,array<string,string>>
     */
    public function toExportRows(array $report): array
    {
        $rows = [];

        // ── Summary ───────────────────────────────────────────────────────────
        $s = $report['summary'] ?? [];
        $rows[] = $this->row('summary', 'evaluated',    'evaluated',    'Evaluadas',      (string) ($s['evaluated']    ?? 0));
        $rows[] = $this->row('summary', 'alerts_total', 'alerts_total', 'Alertas totales',(string) ($s['alerts_total'] ?? 0));
        $rows[] = $this->row('summary', 'critical',     'critical',     'Críticas',       (string) ($s['critical']     ?? 0));
        $rows[] = $this->row('summary', 'high',         'high',         'Altas',          (string) ($s['high']         ?? 0));
        $rows[] = $this->row('summary', 'medium',       'medium',       'Medias',         (string) ($s['medium']       ?? 0));
        $rows[] = $this->row('summary', 'low',          'low',          'Bajas',          (string) ($s['low']          ?? 0));

        // ── By type ───────────────────────────────────────────────────────────
        foreach ($report['by_type'] ?? [] as $key => $count) {
            $rows[] = $this->row('by_type', 'count', (string) $key, (string) $key, (string) $count);
        }

        // ── By category ───────────────────────────────────────────────────────
        foreach ($report['by_category'] ?? [] as $key => $count) {
            $rows[] = $this->row('by_category', 'count', (string) $key, (string) $key, (string) $count);
        }

        // ── By agent ──────────────────────────────────────────────────────────
        foreach ($report['by_agent'] ?? [] as $ag) {
            $row            = $this->row('by_agent', 'count', '', (string) ($ag['assigned_user_name'] ?? 'Sin asignar'), (string) ($ag['alerts_total'] ?? 0));
            $row['critical'] = (string) ($ag['critical'] ?? 0);
            $row['high']     = (string) ($ag['high']     ?? 0);
            $row['medium']   = (string) ($ag['medium']   ?? 0);
            $row['low']      = (string) ($ag['low']      ?? 0);
            $rows[]          = $row;
        }

        // ── Top topics ────────────────────────────────────────────────────────
        foreach ($report['top_topics'] ?? [] as $t) {
            $rows[] = $this->row('top_topics', 'count', (string) ($t['topic'] ?? ''), (string) ($t['topic_label'] ?? $t['topic'] ?? ''), (string) ($t['count'] ?? 0));
        }

        // ── Notification preview ──────────────────────────────────────────────
        $np = $report['notification_preview'] ?? [];
        $rows[] = $this->row(
            'notification_preview',
            'would_notify',
            'hot_unassigned',
            'HOT críticas sin asignar',
            (string) ($np['would_notify'] ?? 0),
            notes: sprintf('mode=%s channel=%s', $np['mode'] ?? 'dry_run', $np['channel'] ?? 'none')
        );

        // ── Recommendations ───────────────────────────────────────────────────
        foreach ($report['recommendations'] ?? [] as $rec) {
            $row          = $this->row('recommendation', 'text', '', '', '');
            $row['notes'] = (string) $rec;
            $rows[]       = $row;
        }

        return $rows;
    }

    /**
     * Render export rows as a UTF-8 CSV string (without BOM — caller adds BOM).
     *
     * @param  array<string,mixed> $report
     */
    public function toCsv(array $report): string
    {
        $rows = $this->toExportRows($report);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::HEADERS);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Render export rows as an XLSX binary string using PhpSpreadsheet.
     *
     * @param  array<string,mixed> $report
     */
    public function toXlsx(array $report, string $date): string
    {
        $rows = $this->toExportRows($report);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte Diario');

        // Header row
        foreach (self::HEADERS as $col => $header) {
            $cell = $this->columnLetter($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Data rows
        foreach ($rows as $rowIdx => $row) {
            $excelRow = $rowIdx + 2;
            foreach (array_values($row) as $colIdx => $value) {
                $sheet->setCellValue($this->columnLetter($colIdx + 1) . $excelRow, $value);
            }
        }

        // Auto-size columns
        foreach (range(1, count(self::HEADERS)) as $col) {
            $sheet->getColumnDimension($this->columnLetter($col))->setAutoSize(true);
        }

        $sheet->getProperties()->setTitle("Reporte Diario {$date}");

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $content !== false ? $content : '';
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @return array<string,string>
     */
    private function row(
        string $section,
        string $metric,
        string $key,
        string $label,
        string $value,
        string $critical = '',
        string $high     = '',
        string $medium   = '',
        string $low      = '',
        string $notes    = '',
    ): array {
        return [
            'section'  => $section,
            'metric'   => $metric,
            'key'      => $key,
            'label'    => $label,
            'value'    => $value,
            'critical' => $critical,
            'high'     => $high,
            'medium'   => $medium,
            'low'      => $low,
            'notes'    => $notes,
        ];
    }

    private function columnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $letter    = chr(65 + $remainder) . $letter;
            $columnNumber = (int) (($columnNumber - 1) / 26);
        }
        return $letter;
    }
}
