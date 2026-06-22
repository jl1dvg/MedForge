<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Reporting\Services\PdfRenderer;
use App\Modules\Whatsapp\Services\KpiDashboardService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KpiReadController
{
    public function __construct(
        private readonly KpiDashboardService $service = new KpiDashboardService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            return response()->json([
                'ok' => true,
                'data' => $this->service->buildDashboard(
                    $start,
                    $end,
                    $this->nullableInt($request->query('role_id')),
                    $this->nullableInt($request->query('agent_id')),
                    $this->nullableInt($request->query('sla_target_minutes'))
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function drilldown(Request $request): JsonResponse
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);
            $metric = trim((string) $request->query('metric', ''));
            if ($metric === '') {
                throw new InvalidArgumentException('Debes indicar la métrica en ?metric=...');
            }

            return response()->json([
                'ok' => true,
                'data' => $this->service->buildDrilldown(
                    $metric,
                    $start,
                    $end,
                    $this->nullableInt($request->query('role_id')),
                    $this->nullableInt($request->query('agent_id')),
                    max(1, (int) $request->query('page', 1)),
                    max(1, min(200, (int) $request->query('limit', 50)))
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $roleId = $this->nullableInt($request->query('role_id'));
        $agentId = $this->nullableInt($request->query('agent_id'));
        $slaTargetMinutes = $this->nullableInt($request->query('sla_target_minutes'));
        $export = $this->service->exportOperationalWorkbookData($start, $end, $roleId, $agentId, $slaTargetMinutes);
        $spreadsheet = $this->buildOperationalWorkbook($export);
        $filename = sprintf(
            'whatsapp-detalle-operativo-%s-a-%s.xlsx',
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        return response()->streamDownload(static function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    public function exportPdf(Request $request)
    {
        [$start, $end] = $this->resolveDateRange($request);
        $roleId = $this->nullableInt($request->query('role_id'));
        $agentId = $this->nullableInt($request->query('agent_id'));
        $slaTargetMinutes = $this->nullableInt($request->query('sla_target_minutes'));
        $dashboard = $this->service->buildDashboard($start, $end, $roleId, $agentId, $slaTargetMinutes);
        $filename = sprintf(
            'whatsapp-resumen-ejecutivo-%s-a-%s.pdf',
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        try {
            if (!class_exists(\Mpdf\Mpdf::class)) {
                throw new RuntimeException('La librería mPDF no está disponible en el entorno.');
            }

            $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
            $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
            $analyticsSummary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
            $lifecycle = array_slice(is_array($analytics['lifecycle'] ?? null) ? $analytics['lifecycle'] : [], 0, 4);
            $sources = array_slice(is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [], 0, 6);
            $funnel = is_array($analytics['funnel'] ?? null) ? $analytics['funnel'] : [];
            $frictions = array_slice(is_array($analytics['frictions'] ?? null) ? $analytics['frictions'] : [], 0, 6);
            $insights = is_array($analytics['insights'] ?? null) ? $analytics['insights'] : [];

            $recommendations = $this->buildExecutiveRecommendations($dashboard);

            $html = view('whatsapp.pdf.dashboard-executive', [
                'generatedAt' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                'period' => is_array($dashboard['period'] ?? null) ? $dashboard['period'] : [],
                'filters' => is_array($dashboard['filters'] ?? null) ? $dashboard['filters'] : [],
                'summary' => $summary,
                'analyticsSummary' => $analyticsSummary,
                'lifecycle' => $lifecycle,
                'sources' => $sources,
                'funnel' => $funnel,
                'frictions' => $frictions,
                'insights' => $insights,
                'recommendations' => $recommendations,
            ])->render();

            $tempDir = storage_path('app/mpdf');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }

            $renderer = new PdfRenderer();
            $pdf = $renderer->renderHtml($html, [
                'filename' => $filename,
                'destination' => 'S',
                'mpdf' => [
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 10,
                    'tempDir' => $tempDir,
                ],
            ]);

            if (!is_string($pdf) || strncmp($pdf, '%PDF-', 5) !== 0) {
                throw new RuntimeException('No se pudo generar el PDF del dashboard.');
            }

            return response($pdf, 200, [
                'Content-Length' => (string) strlen($pdf),
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param array<string,mixed> $export
     */
    private function buildOperationalWorkbook(array $export): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('MedForge')
            ->setTitle('Detalle operativo WhatsApp');

        $period = is_array($export['period'] ?? null) ? $export['period'] : [];
        $dashboard = is_array($export['dashboard'] ?? null) ? $export['dashboard'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $sheets = is_array($export['sheets'] ?? null) ? $export['sheets'] : [];

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Resumen');
        $this->writeSummarySheet($summarySheet, $period, $summary, $sheets);

        $this->addTableSheet($spreadsheet, 'Para cerrar cita', [
            'Teléfono', 'HC / cédula', 'Origen', 'Campaña / anuncio', 'Intención', 'Etapa', 'Fricción', 'Agente', 'Fecha conversación', 'Motivo',
        ], array_map(static fn (array $row): array => [
            $row['wa_number'] ?? '',
            $row['patient_hc_number'] ?? '',
            $row['source_label'] ?? '',
            $row['campaign_headline'] ?: ($row['source_id'] ?? ''),
            $row['initial_intent_label'] ?? '',
            $row['stage_label'] ?? '',
            $row['friction_label'] ?? '',
            $row['assigned_agent_name'] ?? '',
            $row['conversation_created_at'] ?? '',
            $row['opportunity_reason'] ?? '',
        ], is_array($sheets['opportunities'] ?? null) ? $sheets['opportunities'] : []));

        $this->addTableSheet($spreadsheet, 'Citas logradas', [
            'Tipo', 'Teléfono', 'HC / cédula', 'Paciente', 'Origen', 'Campaña / anuncio', 'Fecha cita', 'Hora', 'Sede', 'Médico', 'Procedimiento', 'Agente', 'Creada en',
        ], array_map(static fn (array $row): array => [
            $row['booking_type'] ?? '',
            $row['wa_number'] ?? '',
            $row['patient_hc_number'] ?? '',
            $row['patient_name'] ?? '',
            $row['source_label'] ?? '',
            $row['campaign_headline'] ?: ($row['source_id'] ?? ''),
            $row['appointment_date'] ?? '',
            $row['appointment_time'] ?? '',
            $row['sede_nombre'] ?? '',
            $row['medico_nombre'] ?? '',
            $row['procedimiento_nombre'] ?? '',
            $row['agent_name'] ?? '',
            $row['booking_created_at'] ?? '',
        ], is_array($sheets['appointments'] ?? null) ? $sheets['appointments'] : []));

        $this->addTableSheet($spreadsheet, 'Ads cerrados', [
            'Teléfono', 'HC / cédula', 'Campaña / anuncio', 'Intención', 'Etapa', 'Resultado', 'Agente', 'Fecha conversación',
        ], array_map(static fn (array $row): array => [
            $row['wa_number'] ?? '',
            $row['patient_hc_number'] ?? '',
            $row['campaign_headline'] ?: ($row['source_id'] ?? ''),
            $row['initial_intent_label'] ?? '',
            $row['stage_label'] ?? '',
            $row['outcome_label'] ?? '',
            $row['assigned_agent_name'] ?? '',
            $row['conversation_created_at'] ?? '',
        ], is_array($sheets['ads_closed'] ?? null) ? $sheets['ads_closed'] : []));

        $this->addTableSheet($spreadsheet, 'Ads perdidos', [
            'Teléfono', 'HC / cédula', 'Campaña / anuncio', 'Intención', 'Etapa', 'Fricción', 'Agente', 'Fecha conversación', 'Motivo',
        ], array_map(static fn (array $row): array => [
            $row['wa_number'] ?? '',
            $row['patient_hc_number'] ?? '',
            $row['campaign_headline'] ?: ($row['source_id'] ?? ''),
            $row['initial_intent_label'] ?? '',
            $row['stage_label'] ?? '',
            $row['friction_label'] ?? '',
            $row['assigned_agent_name'] ?? '',
            $row['conversation_created_at'] ?? '',
            $row['opportunity_reason'] ?? '',
        ], is_array($sheets['ads_lost'] ?? null) ? $sheets['ads_lost'] : []));

        $this->addTableSheet($spreadsheet, 'Agentes', [
            'Agente', 'Teléfono', 'HC / cédula', 'Origen', 'Intención', 'Etapa', 'Cita creada', 'Fricción', 'Fecha conversación',
        ], array_map(static fn (array $row): array => [
            $row['assigned_agent_name'] ?? '',
            $row['wa_number'] ?? '',
            $row['patient_hc_number'] ?? '',
            $row['source_label'] ?? '',
            $row['initial_intent_label'] ?? '',
            $row['stage_label'] ?? '',
            ((int) ($row['has_booking'] ?? 0) === 1) ? 'Sí' : 'No',
            $row['friction_label'] ?? '',
            $row['conversation_created_at'] ?? '',
        ], is_array($sheets['agents'] ?? null) ? $sheets['agents'] : []));

        $this->addTableSheet($spreadsheet, 'Fricciones', [
            'Teléfono', 'HC / cédula', 'Origen', 'Intención', 'Etapa', 'Fricción', 'Agente', 'Fecha conversación',
        ], array_map(static fn (array $row): array => [
            $row['wa_number'] ?? '',
            $row['patient_hc_number'] ?? '',
            $row['source_label'] ?? '',
            $row['initial_intent_label'] ?? '',
            $row['stage_label'] ?? '',
            $row['friction_label'] ?? '',
            $row['assigned_agent_name'] ?? '',
            $row['conversation_created_at'] ?? '',
        ], is_array($sheets['frictions'] ?? null) ? $sheets['frictions'] : []));

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param array<string,mixed> $period
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $sheets
     */
    private function writeSummarySheet(Worksheet $sheet, array $period, array $summary, array $sheets): void
    {
        $sheet->setCellValue('A1', 'Detalle operativo WhatsApp');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Desde');
        $sheet->setCellValue('B2', (string) ($period['date_from'] ?? ''));
        $sheet->setCellValue('C2', 'Hasta');
        $sheet->setCellValue('D2', (string) ($period['date_to'] ?? ''));
        $sheet->setCellValue('A3', 'Generado');
        $sheet->setCellValue('B3', (string) ($period['generated_at'] ?? ''));

        $rows = [
            ['Conversaciones nuevas', $summary['conversations_new'] ?? 0],
            ['Citas por humano', $summary['human_attributed_appointments_strong'] ?? 0],
            ['Citas por bot / integración', $summary['sigcenter_bookings_created'] ?? 0],
            ['Oportunidades para cerrar cita', count(is_array($sheets['opportunities'] ?? null) ? $sheets['opportunities'] : [])],
            ['Ads cerrados o resueltos', count(is_array($sheets['ads_closed'] ?? null) ? $sheets['ads_closed'] : [])],
            ['Ads perdidos', count(is_array($sheets['ads_lost'] ?? null) ? $sheets['ads_lost'] : [])],
            ['Conversaciones con fricción', count(is_array($sheets['frictions'] ?? null) ? $sheets['frictions'] : [])],
        ];

        $sheet->setCellValue('A5', 'Indicador');
        $sheet->setCellValue('B5', 'Valor');
        $this->styleHeader($sheet, 'A5:B5');
        $rowNumber = 6;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowNumber}", (string) $row[0]);
            $sheet->setCellValue("B{$rowNumber}", (int) $row[1]);
            $rowNumber++;
        }
        $sheet->getStyle('A5:B' . max(5, $rowNumber - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');
        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->freezePane('A6');
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,mixed>> $rows
     */
    private function addTableSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = new Worksheet($spreadsheet, mb_substr($title, 0, 31));
        $spreadsheet->addSheet($sheet);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->cell($index + 1, 1), $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $column = $index + 1;
                if (in_array($headers[$index] ?? '', ['Teléfono', 'HC / cédula'], true)) {
                    $sheet->setCellValueExplicit($this->cell($column, $rowNumber), (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($this->cell($column, $rowNumber), $value);
                }
            }
            $rowNumber++;
        }

        $lastColumn = count($headers);
        $lastRow = max(1, $rowNumber - 1);
        $sheet->setAutoFilter($this->cell(1, 1) . ':' . $this->cell($lastColumn, $lastRow));
        $sheet->freezePane('A2');
        $this->styleHeader($sheet, $this->cell(1, 1) . ':' . $this->cell($lastColumn, 1));
        $sheet->getStyle($this->cell(1, 1) . ':' . $this->cell($lastColumn, $lastRow))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

        for ($column = 1; $column <= $lastColumn; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2A44');
    }

    private function cell(int $column, int $row): string
    {
        return Coordinate::stringFromColumnIndex($column) . $row;
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function resolveDateRange(Request $request): array
    {
        $today = new DateTimeImmutable('today');
        $from = $this->parseDate(trim((string) $request->query('date_from', ''))) ?? $today->modify('-29 days');
        $to = $this->parseDate(trim((string) $request->query('date_to', ''))) ?? $today;

        if ($from > $to) {
            throw new InvalidArgumentException('date_from no puede ser mayor que date_to.');
        }

        $days = (int) $from->diff($to)->days + 1;
        if ($days > 366) {
            throw new InvalidArgumentException('El rango máximo permitido es de 366 días.');
        }

        return [$from, $to];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw new InvalidArgumentException('Formato de fecha inválido. Usa YYYY-MM-DD.');
        }

        return $date->setTime(0, 0, 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }

    /**
     * @param array<string,mixed> $dashboard
     * @return array<int, string>
     */
    private function buildExecutiveRecommendations(array $dashboard): array
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
        $analyticsSummary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
        $sources = is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [];
        $frictions = is_array($analytics['frictions'] ?? null) ? $analytics['frictions'] : [];

        $recommendations = [];
        $humanAppointments = (int) ($summary['human_attributed_appointments_strong'] ?? 0);
        $botAppointments = (int) ($summary['sigcenter_bookings_created'] ?? 0);
        $totalAppointments = $humanAppointments + $botAppointments;
        $peopleInbound = (int) ($summary['people_inbound'] ?? 0);
        $attributedBookingRate = $peopleInbound > 0 ? ($totalAppointments / $peopleInbound) * 100 : 0.0;

        if ($humanAppointments > 0) {
            $recommendations[] = sprintf(
                'Separar el análisis de cierre humano: hay %d citas Sigcenter atribuibles a conversaciones atendidas por agentes, frente a %d creadas por bot/integración.',
                $humanAppointments,
                $botAppointments
            );
        }

        if ($attributedBookingRate < 1.0) {
            $recommendations[] = 'Priorizar una revisión del flujo de captación: el volumen ya existe, pero la conversión atribuida total a cita sigue por debajo de 1%.';
        }

        if (((int) ($summary['conversations_abandoned_with_handoff'] ?? 0)) > 0) {
            $recommendations[] = 'Separar una cola operativa para handoffs vencidos: los casos con handoff sin respuesta después de 24 horas son la deuda humana más accionable.';
        }

        if (!empty($frictions) && ($frictions[0]['friction_state'] ?? null) === 'handoff_required') {
            $recommendations[] = 'Reducir dependencia de humano en FAQ y captación temprana para liberar capacidad del equipo y reservar handoff a cierres complejos.';
        }

        foreach ($sources as $source) {
            if (($source['source_category'] ?? '') === 'ad' && (int) ($source['bookings'] ?? 0) === 0 && (int) ($source['total'] ?? 0) > 50) {
                $recommendations[] = 'Auditar Ads por calidad, no por volumen: hoy generan muchas conversaciones, pero no están cerrando en cita dentro del periodo.';
                break;
            }
        }

        if (((float) ($summary['attention_rate'] ?? 0)) < 20.0) {
            $recommendations[] = 'Revisar cobertura humana y tiempos de respuesta: la capacidad actual del equipo no está absorbiendo la demanda que sí cae a circuito manual.';
        }

        return array_slice(array_values(array_unique($recommendations)), 0, 5);
    }
}
