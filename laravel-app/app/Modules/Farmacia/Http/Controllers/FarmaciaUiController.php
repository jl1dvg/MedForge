<?php

declare(strict_types=1);

namespace App\Modules\Farmacia\Http\Controllers;

use App\Modules\Farmacia\Services\FarmaciaDashboardService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FarmaciaUiController
{
    private FarmaciaDashboardService $service;

    public function __construct()
    {
        $this->service = new FarmaciaDashboardService();
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $payload = $this->service->dashboard($request->query());

        return view('farmacia.v2-dashboard', [
            'pageTitle' => 'Dashboard de Farmacia',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'filters' => $payload['filters'],
            'dashboard' => $payload['dashboard'],
            'rows' => $payload['rows'],
            'conciliationRows' => $payload['conciliationRows'],
            'doctorOptions' => $payload['doctorOptions'],
            'afiliacionOptions' => $payload['afiliacionOptions'],
            'afiliacionCategoriaOptions' => $payload['afiliacionCategoriaOptions'],
            'empresaAfiliacionOptions' => $payload['empresaAfiliacionOptions'],
            'sedeOptions' => $payload['sedeOptions'],
            'departamentoOptions' => $payload['departamentoOptions'],
            'topMedicosOptions' => $payload['topMedicosOptions'],
        ]);
    }

    public function exportPdf(Request $request): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $payload = $this->service->dashboard($request->query());
        $filename = 'dashboard_farmacia_' . date('Ymd_His') . '.pdf';
        $filtersSummary = $this->buildFiltersSummary($payload);

        try {
            $pdf = (new ReportService())->renderPdf('farmacia_dashboard', [
                'titulo' => 'Dashboard de KPIs de recetas',
                'generatedAt' => (new DateTimeImmutable('now'))->format('d-m-Y H:i'),
                'filters' => $filtersSummary,
                'total' => count(is_array($payload['rows'] ?? null) ? $payload['rows'] : []),
                ...$this->buildPdfExecutivePayload($payload),
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
        } catch (Throwable $e) {
            abort(500, 'No se pudo generar el PDF del dashboard de farmacia.');
        }

        if (strncmp($pdf, '%PDF-', 5) !== 0) {
            abort(500, 'No se pudo generar el PDF del dashboard de farmacia.');
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string)strlen($pdf),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $payload = $this->service->dashboard($request->query());
        $filtersSummary = $this->buildFiltersSummary($payload);
        $filename = 'dashboard_farmacia_' . date('Ymd_His') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        $row = 1;
        $sheet->setCellValue("A{$row}", 'Dashboard de KPIs de recetas');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(15);

        $row++;
        $sheet->setCellValue("A{$row}", 'Generado:');
        $sheet->setCellValue("B{$row}", (new DateTimeImmutable('now'))->format('d-m-Y H:i'));
        $sheet->setCellValue("D{$row}", 'Registros:');
        $sheet->setCellValueExplicit("E{$row}", (string)count($payload['rows'] ?? []), DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Filtros aplicados');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        if ($filtersSummary === []) {
            $row++;
            $sheet->setCellValue("A{$row}", 'Sin filtros específicos.');
            $sheet->mergeCells("A{$row}:E{$row}");
        } else {
            foreach ($filtersSummary as $filter) {
                $row++;
                $sheet->setCellValue("A{$row}", (string)($filter['label'] ?? ''));
                $sheet->setCellValue("B{$row}", (string)($filter['value'] ?? ''));
                $sheet->mergeCells("B{$row}:E{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            }
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'KPIs');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", 'Indicador');
        $sheet->setCellValue("B{$row}", 'Valor');
        $sheet->setCellValue("C{$row}", 'Detalle');
        $sheet->mergeCells("C{$row}:E{$row}");
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);

        foreach ((array)($payload['dashboard']['cards'] ?? []) as $card) {
            $row++;
            $sheet->setCellValue("A{$row}", (string)($card['label'] ?? ''));
            $sheet->setCellValueExplicit("B{$row}", (string)($card['value'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("C{$row}", (string)($card['hint'] ?? ''));
            $sheet->mergeCells("C{$row}:E{$row}");
        }

        $meta = is_array($payload['dashboard']['meta'] ?? null) ? $payload['dashboard']['meta'] : [];
        $tatPromedio = ($meta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$meta['tat_promedio_horas'], 2) . ' h' : '—';
        $tatMediana = ($meta['tat_mediana_horas'] ?? null) !== null ? number_format((float)$meta['tat_mediana_horas'], 2) . ' h' : '—';
        $tatP90 = ($meta['tat_p90_horas'] ?? null) !== null ? number_format((float)$meta['tat_p90_horas'], 2) . ' h' : '—';
        $row++;
        $sheet->setCellValue("A{$row}", 'TAT (promedio / mediana / P90)');
        $sheet->setCellValue("B{$row}", $tatPromedio);
        $sheet->setCellValue("C{$row}", 'Mediana: ' . $tatMediana . ' | P90: ' . $tatP90);
        $sheet->mergeCells("C{$row}:E{$row}");

        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(36);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(16);

        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Detalle');
        $detailHeaders = [
            '#',
            'Fecha receta',
            'Sede',
            'Localidad',
            'Departamento',
            'Médico',
            'Tipo afiliación',
            'Empresa afiliación',
            'Seguro / plan',
            'Producto',
            'Cantidad prescrita',
            'Unidades farmacia',
            'Diagnóstico',
            'Paciente',
            'Cédula paciente',
            'Edad',
            'Form ID',
            'Estado receta',
            'Cobertura %',
            'Dosis/Pauta',
            'Procedimiento',
        ];

        $detailRow = 1;
        foreach ($detailHeaders as $idx => $label) {
            $column = $this->excelColumnByIndex($idx);
            $detailSheet->setCellValue("{$column}{$detailRow}", $label);
        }
        $lastHeaderColumn = $this->excelColumnByIndex(count($detailHeaders) - 1);
        $detailSheet->getStyle("A1:{$lastHeaderColumn}1")->getFont()->setBold(true);
        $detailSheet->setAutoFilter("A1:{$lastHeaderColumn}1");

        foreach ((array)($payload['rows'] ?? []) as $index => $item) {
            $detailRow++;
            $values = [
                (string)($index + 1),
                (string)($item['fecha_receta'] ?? '—'),
                (string)($item['sede'] ?? ''),
                (string)($item['localidad'] ?? ''),
                (string)($item['departamento'] ?? ''),
                (string)($item['doctor'] ?? ''),
                (string)($item['tipo_afiliacion'] ?? ''),
                (string)($item['empresa_afiliacion'] ?? ''),
                (string)($item['afiliacion'] ?? ''),
                (string)($item['producto'] ?? ''),
                (string)($item['cantidad'] ?? '0'),
                (string)($item['total_farmacia'] ?? '0'),
                (string)($item['diagnostico'] ?? ''),
                (string)($item['paciente_nombre'] ?? ''),
                (string)($item['cedula_paciente'] ?? ''),
                (string)($item['edad_paciente'] ?? '—'),
                (string)($item['form_id'] ?? ''),
                (string)($item['estado_receta'] ?? ''),
                (string)($item['cobertura'] ?? '—'),
                (string)($item['dosis'] ?? ''),
                (string)($item['procedimiento_proyectado'] ?? ''),
            ];

            foreach ($values as $idx => $value) {
                $column = $this->excelColumnByIndex($idx);
                $detailSheet->setCellValueExplicit("{$column}{$detailRow}", $value, DataType::TYPE_STRING);
            }
        }

        $detailSheet->freezePane('A2');
        foreach ([
            'A' => 6, 'B' => 18, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 28, 'G' => 18,
            'H' => 22, 'I' => 24, 'J' => 28, 'K' => 16, 'L' => 16, 'M' => 48, 'N' => 34,
            'O' => 18, 'P' => 11, 'Q' => 14, 'R' => 16, 'S' => 13, 'T' => 32, 'U' => 44,
        ] as $column => $width) {
            $detailSheet->getColumnDimension($column)->setWidth($width);
        }

        if ($detailRow > 1) {
            foreach (['M', 'T', 'U'] as $wrapColumn) {
                $detailSheet->getStyle("{$wrapColumn}2:{$wrapColumn}{$detailRow}")
                    ->getAlignment()
                    ->setWrapText(true);
            }
        }

        $incidenciasSheet = $spreadsheet->createSheet();
        $incidenciasSheet->setTitle('Incidencias');
        $incidenciasHeaders = ['#', 'Fecha receta', 'Fecha factura', 'Tipo match', 'Sede', 'Empresa', 'Seguro', 'Médico', 'Paciente', 'Producto receta', 'Producto factura', 'Neto', 'Descuentos', 'Depto. factura'];
        foreach ($incidenciasHeaders as $idx => $label) {
            $column = $this->excelColumnByIndex($idx);
            $incidenciasSheet->setCellValue("{$column}1", $label);
        }
        $incidenciasSheet->getStyle('A1:N1')->getFont()->setBold(true);
        $incidenciasSheet->setAutoFilter('A1:N1');

        $incidenciaRow = 1;
        foreach ((array)($payload['conciliationRows'] ?? []) as $index => $item) {
            $incidenciaRow++;
            $values = [
                (string)($index + 1),
                (string)($item['fecha_receta'] ?? '—'),
                (string)($item['fecha_facturacion'] ?? '—'),
                (string)($item['tipo_match'] ?? ''),
                (string)($item['sede'] ?? ''),
                (string)($item['empresa_afiliacion'] ?? ''),
                (string)($item['afiliacion'] ?? ''),
                (string)($item['doctor'] ?? ''),
                (string)($item['paciente'] ?? ''),
                (string)($item['producto_receta'] ?? ''),
                (string)($item['producto_factura'] ?? ''),
                (string)($item['monto_linea_neto'] ?? ''),
                (string)($item['descuentos'] ?? ''),
                (string)($item['departamento_factura'] ?? ''),
            ];
            foreach ($values as $idx => $value) {
                $column = $this->excelColumnByIndex($idx);
                $incidenciasSheet->setCellValueExplicit("{$column}{$incidenciaRow}", $value, DataType::TYPE_STRING);
            }
        }
        foreach (range('A', 'N') as $column) {
            $incidenciasSheet->getColumnDimension($column)->setWidth(in_array($column, ['I', 'J', 'K'], true) ? 28 : 18);
        }
        $incidenciasSheet->freezePane('A2');

        $rankSheet = $spreadsheet->createSheet();
        $rankSheet->setTitle('Rankings');
        $rankSheet->setCellValue('A1', 'Top médicos por neto');
        $rankSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $rankSheet->setCellValue('A2', 'Médico');
        $rankSheet->setCellValue('B2', 'Neto');
        $rankSheet->getStyle('A2:B2')->getFont()->setBold(true);

        $doctorLabels = (array)($payload['dashboard']['charts']['neto_doctores']['labels'] ?? []);
        $doctorValues = (array)($payload['dashboard']['charts']['neto_doctores']['values'] ?? []);
        $rankRow = 2;
        foreach ($doctorLabels as $idx => $label) {
            $rankRow++;
            $rankSheet->setCellValue("A{$rankRow}", (string)$label);
            $rankSheet->setCellValue("B{$rankRow}", (float)($doctorValues[$idx] ?? 0));
        }

        $rankSheet->setCellValue('D1', 'Top seguros por neto');
        $rankSheet->getStyle('D1')->getFont()->setBold(true)->setSize(13);
        $rankSheet->setCellValue('D2', 'Seguro');
        $rankSheet->setCellValue('E2', 'Neto');
        $rankSheet->getStyle('D2:E2')->getFont()->setBold(true);
        $seguroLabels = (array)($payload['dashboard']['charts']['neto_afiliacion']['labels'] ?? []);
        $seguroValues = (array)($payload['dashboard']['charts']['neto_afiliacion']['values'] ?? []);
        $rankRow2 = 2;
        foreach ($seguroLabels as $idx => $label) {
            $rankRow2++;
            $rankSheet->setCellValue("D{$rankRow2}", (string)$label);
            $rankSheet->setCellValue("E{$rankRow2}", (float)($seguroValues[$idx] ?? 0));
        }

        foreach (['A' => 28, 'B' => 14, 'D' => 28, 'E' => 14] as $column => $width) {
            $rankSheet->getColumnDimension($column)->setWidth($width);
        }
        $rankSheet->freezePane('A2');

        $content = '';
        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'r+');
        $writer->save($stream);
        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        if ($content === '' || strncmp($content, 'PK', 2) !== 0) {
            abort(500, 'No se pudo generar el Excel del dashboard de farmacia.');
        }

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string)strlen($content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{label:string,value:string}>
     */
    private function buildFiltersSummary(array $payload): array
    {
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $summary = [
            ['label' => 'Desde', 'value' => trim((string)($filters['fecha_inicio'] ?? ''))],
            ['label' => 'Hasta', 'value' => trim((string)($filters['fecha_fin'] ?? ''))],
            ['label' => 'Médico', 'value' => trim((string)($filters['doctor'] ?? ''))],
            ['label' => 'Tipo afiliación', 'value' => $this->resolveLabelFromOptions((string)($filters['tipo_afiliacion'] ?? ''), (array)($payload['afiliacionCategoriaOptions'] ?? []))],
            ['label' => 'Empresa afiliación', 'value' => $this->resolveLabelFromOptions((string)($filters['empresa_afiliacion'] ?? ''), (array)($payload['empresaAfiliacionOptions'] ?? []))],
            ['label' => 'Seguro / plan', 'value' => $this->resolveLabelFromOptions((string)($filters['afiliacion'] ?? ''), (array)($payload['afiliacionOptions'] ?? []))],
            ['label' => 'Sede', 'value' => trim((string)($filters['sede'] ?? ''))],
            ['label' => 'Departamento', 'value' => trim((string)($filters['departamento'] ?? ''))],
            ['label' => 'Producto', 'value' => trim((string)($filters['producto'] ?? ''))],
            ['label' => 'Top médicos', 'value' => trim((string)($filters['top_n_medicos'] ?? ''))],
        ];

        return array_values(array_filter($summary, static fn(array $item): bool => trim((string)($item['value'] ?? '')) !== ''));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildPdfExecutivePayload(array $payload): array
    {
        $dashboard = is_array($payload['dashboard'] ?? null) ? $payload['dashboard'] : [];
        $cards = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
        $meta = is_array($dashboard['meta'] ?? null) ? $dashboard['meta'] : [];
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $conciliationRows = is_array($payload['conciliationRows'] ?? null) ? $payload['conciliationRows'] : [];

        $cardMap = [];
        foreach ($cards as $card) {
            $label = trim((string)($card['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $cardMap[$label] = [
                'value' => trim((string)($card['value'] ?? '')),
                'hint' => trim((string)($card['hint'] ?? '')),
            ];
        }

        $hallazgos = [];
        $items = count($rows);
        $sinMatch = 0;
        foreach ($conciliationRows as $item) {
            if ((string)($item['tipo_match'] ?? '') === 'sin_match') {
                $sinMatch++;
            }
        }

        if ($items > 0) {
            $hallazgos[] = 'Se analizaron ' . number_format($items) . ' líneas de receta en el rango seleccionado.';
        }
        if (isset($cardMap['Ingreso neto conciliado']['value'])) {
            $hallazgos[] = 'El ingreso neto conciliado del periodo fue ' . $cardMap['Ingreso neto conciliado']['value'] . '.';
        }
        if (($meta['conciliacion_exacta_pct'] ?? null) !== null) {
            $hallazgos[] = 'La tasa de conciliación exacta cerró en ' . number_format((float)$meta['conciliacion_exacta_pct'], 1) . '%.';
        }
        if ($sinMatch > 0) {
            $hallazgos[] = 'Se detectaron ' . number_format($sinMatch) . ' incidencias sin match que requieren revisión comercial u operativa.';
        }
        if (($meta['economia_ticket_promedio'] ?? null) !== null) {
            $hallazgos[] = 'El ticket neto promedio por línea conciliada fue $' . number_format((float)$meta['economia_ticket_promedio'], 2) . '.';
        }

        return [
            'rangeLabel' => trim((string)($filters['fecha_inicio'] ?? '')) . ' a ' . trim((string)($filters['fecha_fin'] ?? '')),
            'scopeNotice' => 'Este documento resume producción clínica, conciliación farmacéutica e impacto económico del rango seleccionado. La lectura está orientada a gestión médica, sedes y seguros.',
            'methodology' => [
                'La producción clínica se calcula desde recetas emitidas y despacho registrado en farmacia.',
                'La conciliación económica usa la tabla sincronizada por cron con match entre receta y facturación.',
                'Seguro, empresa y tipo de afiliación se normalizan con el mapa corporativo de afiliaciones cuando está disponible.',
                'Los gráficos del dashboard se resumen aquí como KPIs ejecutivos y hallazgos clave, no como detalle transaccional.',
            ],
            'hallazgosClave' => $hallazgos,
            'executiveKpis' => [
                ['label' => 'Ítems de recetas', 'value' => $cardMap['Ítems de recetas']['value'] ?? '0', 'note' => $cardMap['Ítems de recetas']['hint'] ?? ''],
                ['label' => 'Pacientes únicos', 'value' => $cardMap['Pacientes únicos']['value'] ?? '0', 'note' => $cardMap['Pacientes únicos']['hint'] ?? ''],
                ['label' => 'Médicos activos', 'value' => $cardMap['Médicos activos']['value'] ?? '0', 'note' => $cardMap['Médicos activos']['hint'] ?? ''],
                ['label' => 'Productos distintos', 'value' => $cardMap['Productos distintos']['value'] ?? '0', 'note' => $cardMap['Productos distintos']['hint'] ?? ''],
            ],
            'operationalKpis' => [
                ['label' => 'Unidades prescritas', 'value' => $cardMap['Unidades prescritas']['value'] ?? '0', 'note' => $cardMap['Unidades prescritas']['hint'] ?? ''],
                ['label' => 'Ítems por episodio', 'value' => $cardMap['Ítems por episodio']['value'] ?? '—', 'note' => $cardMap['Ítems por episodio']['hint'] ?? ''],
                ['label' => 'SLA <= 24h', 'value' => ($meta['sla_24h_pct'] ?? null) !== null ? number_format((float)$meta['sla_24h_pct'], 1) . '%' : '—', 'note' => 'Tiempo de respuesta del despacho'],
                ['label' => 'Dif. promedio facturación', 'value' => ($meta['conciliacion_diff_promedio_dias'] ?? null) !== null ? number_format((float)$meta['conciliacion_diff_promedio_dias'], 2) . ' d' : '—', 'note' => 'Días entre receta y facturación'],
            ],
            'economicKpis' => [
                ['label' => 'Ingreso neto conciliado', 'value' => $cardMap['Ingreso neto conciliado']['value'] ?? '—', 'note' => $cardMap['Ingreso neto conciliado']['hint'] ?? ''],
                ['label' => 'Ingreso neto exacto', 'value' => $cardMap['Ingreso neto exacto']['value'] ?? '—', 'note' => $cardMap['Ingreso neto exacto']['hint'] ?? ''],
                ['label' => 'Descuentos aplicados', 'value' => $cardMap['Descuentos aplicados']['value'] ?? '—', 'note' => $cardMap['Descuentos aplicados']['hint'] ?? ''],
                ['label' => 'Ticket neto promedio', 'value' => $cardMap['Ticket neto promedio']['value'] ?? '—', 'note' => $cardMap['Ticket neto promedio']['hint'] ?? ''],
            ],
            'qualityKpis' => [
                ['label' => 'Tasa exacta', 'value' => $cardMap['Tasa exacta']['value'] ?? '—', 'note' => $cardMap['Tasa exacta']['hint'] ?? ''],
                ['label' => 'Recetas sin match', 'value' => $cardMap['Recetas sin match']['value'] ?? '0', 'note' => $cardMap['Recetas sin match']['hint'] ?? ''],
                ['label' => 'TAT promedio', 'value' => ($meta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$meta['tat_promedio_horas'], 2) . ' h' : '—', 'note' => 'Promedio desde receta hasta actualización'],
                ['label' => 'TAT P90', 'value' => ($meta['tat_p90_horas'] ?? null) !== null ? number_format((float)$meta['tat_p90_horas'], 2) . ' h' : '—', 'note' => 'Percentil 90 del tiempo de atención'],
            ],
        ];
    }

    /**
     * @param array<int, mixed> $options
     */
    private function resolveLabelFromOptions(string $value, array $options): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        foreach ($options as $option) {
            if (is_array($option)) {
                if ((string)($option['value'] ?? '') === $value) {
                    return trim((string)($option['label'] ?? $value));
                }
                continue;
            }

            if ((string)$option === $value) {
                return trim((string)$option);
            }
        }

        return $value;
    }

    private function excelColumnByIndex(int $index): string
    {
        $index = max(0, $index);
        $column = '';

        do {
            $column = chr(($index % 26) + 65) . $column;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $column;
    }
}
