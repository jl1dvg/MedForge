<?php

namespace Modules\Cirugias\Controllers;

use Core\BaseController;
use DateTimeImmutable;
use Helpers\JsonLogger;
use Modules\Cirugias\Services\CirugiasDashboardService;
use Modules\Reporting\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;
use Throwable;

class CirugiasDashboardController extends BaseController
{
    private CirugiasDashboardService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new CirugiasDashboardService($pdo);
    }

    public function index(): void
    {
        $this->authorizeDashboardAccess();
        $dateRange = $this->resolveDateRange();
        $afiliacionFilter = $this->resolveAfiliacionFilter();
        $afiliacionCategoriaFilter = $this->resolveAfiliacionCategoriaFilter();
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter);
        $data['pageTitle'] = 'Dashboard quirúrgico';

        $this->render('modules/Cirugias/views/dashboard.php', $data);
    }

    public function exportPdf(): void
    {
        $this->authorizeDashboardAccess();

        $dateRange = $this->resolveDateRange();
        $afiliacionFilter = $this->resolveAfiliacionFilter();
        $afiliacionCategoriaFilter = $this->resolveAfiliacionCategoriaFilter();
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter);
        $filters = $this->buildDashboardFiltersSummary(
            $data['date_range'],
            $afiliacionFilter,
            $data['afiliacion_options'],
            $afiliacionCategoriaFilter,
            $data['afiliacion_categoria_options']
        );
        $filename = 'dashboard_cirugias_' . date('Ymd_His') . '.pdf';

        try {
            $reportService = new ReportService();
            $pdf = $reportService->renderPdf('cirugias_dashboard', [
                'titulo' => 'Dashboard de KPIs quirúrgicos',
                'generatedAt' => (new DateTimeImmutable('now'))->format('d-m-Y H:i'),
                'filters' => $filters,
                'cards' => $data['kpi_cards'],
                'periodoLabel' => (string)($data['date_range']['label'] ?? ''),
                'total' => count($data['kpi_cards']),
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
                $this->json(['error' => 'No se pudo generar el PDF (contenido inválido).'], 500);
                return;
            }

            if (!headers_sent()) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Length: ' . strlen($pdf));
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('X-Content-Type-Options: nosniff');
            }

            echo $pdf;
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'cirugias_dashboard_export',
                'Error exportando PDF del dashboard quirúrgico',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );

            $this->json(['error' => 'No se pudo generar el PDF (ref: ' . $errorId . ')'], 500);
        }
    }

    public function exportExcel(): void
    {
        $this->authorizeDashboardAccess();

        $dateRange = $this->resolveDateRange();
        $afiliacionFilter = $this->resolveAfiliacionFilter();
        $afiliacionCategoriaFilter = $this->resolveAfiliacionCategoriaFilter();
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter);
        $filters = $this->buildDashboardFiltersSummary(
            $data['date_range'],
            $afiliacionFilter,
            $data['afiliacion_options'],
            $afiliacionCategoriaFilter,
            $data['afiliacion_categoria_options']
        );
        $filename = 'dashboard_cirugias_' . date('Ymd_His') . '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('KPIs');

            $row = 1;
            $sheet->setCellValue("A{$row}", 'Dashboard de KPIs quirúrgicos');
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(15);

            $row++;
            $sheet->setCellValue("A{$row}", 'Generado:');
            $sheet->setCellValue("B{$row}", (new DateTimeImmutable('now'))->format('d-m-Y H:i'));
            $sheet->setCellValue("C{$row}", 'Periodo:');
            $sheet->setCellValue("D{$row}", (string)($data['date_range']['label'] ?? ''));
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);

            $row += 2;
            $sheet->setCellValue("A{$row}", 'Filtros aplicados');
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            if ($filters === []) {
                $row++;
                $sheet->setCellValue("A{$row}", 'Sin filtros específicos.');
                $sheet->mergeCells("A{$row}:D{$row}");
            } else {
                foreach ($filters as $filter) {
                    $row++;
                    $sheet->setCellValue("A{$row}", (string)($filter['label'] ?? ''));
                    $sheet->setCellValue("B{$row}", (string)($filter['value'] ?? ''));
                    $sheet->mergeCells("B{$row}:D{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                }
            }

            $row += 2;
            $sheet->setCellValue("A{$row}", 'KPIs');
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            $row++;
            $sheet->setCellValue("A{$row}", 'Indicador');
            $sheet->setCellValue("B{$row}", 'Valor');
            $sheet->setCellValue("C{$row}", 'Detalle');
            $sheet->mergeCells("C{$row}:D{$row}");
            $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);

            foreach ($data['kpi_cards'] as $card) {
                $row++;
                $sheet->setCellValue("A{$row}", (string)($card['label'] ?? ''));
                $sheet->setCellValueExplicit("B{$row}", (string)($card['value'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", (string)($card['hint'] ?? ''));
                $sheet->mergeCells("C{$row}:D{$row}");
            }

            foreach (['A' => 34, 'B' => 18, 'C' => 55, 'D' => 12] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            if (!headers_sent()) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                header('Pragma: public');
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'cirugias_dashboard_export',
                'Error exportando Excel del dashboard quirúrgico',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->getCurrentUserId(),
                ]
            );

            $this->json(['error' => 'No se pudo generar el Excel (ref: ' . $errorId . ')'], 500);
        }
    }

    private function authorizeDashboardAccess(): void
    {
        $this->requireAuth();
        $this->requirePermission([
            'cirugias.dashboard.view',
            'administrativo',
            'admin.usuarios.manage',
            'admin.roles.manage',
            'admin.usuarios',
            'admin.roles',
        ]);
    }

    private function buildDashboardPayload(
        array $dateRange,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        $startSql = $dateRange['start']->format('Y-m-d 00:00:00');
        $endSql = $dateRange['end']->format('Y-m-d 23:59:59');
        $dateRangeView = $this->formatDateRangeForView($dateRange);
        $afiliacionFilter = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $afiliacionOptions = $this->service->getAfiliacionOptions($startSql, $endSql);
        $afiliacionCategoriaOptions = $this->service->getAfiliacionCategoriaOptions($startSql, $endSql);

        $totalCirugias = $this->service->getTotalCirugias($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $sinFacturar = $this->service->getCirugiasSinFacturar($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $duracionPromedioRaw = $this->service->getDuracionPromedioMinutos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $duracionPromedio = $this->formatMinutes($duracionPromedioRaw);
        $estadoProtocolos = $this->service->getEstadoProtocolos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $cirugiasPorMes = $this->service->getCirugiasPorMes($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $topProcedimientos = $this->service->getTopProcedimientos($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter);
        $topCirujanos = $this->service->getTopCirujanos($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter);
        $topDoctoresSolicitudesRealizadas = $this->service->getTopDoctoresSolicitudesRealizadas($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter);
        $cirugiasPorConvenio = $this->service->getCirugiasPorConvenio($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $programacionKpis = $this->service->getProgramacionKpis($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);
        $reingresoMismoDiagnostico = $this->service->getReingresoMismoDiagnostico($startSql, $endSql);
        $cirugiasSinSolicitudPrevia = $this->service->getCirugiasSinSolicitudPrevia($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter);

        return [
            'date_range' => $dateRangeView,
            'total_cirugias' => $totalCirugias,
            'cirugias_sin_facturar' => $sinFacturar,
            'duracion_promedio' => $duracionPromedio,
            'estado_protocolos' => $estadoProtocolos,
            'cirugias_por_mes' => $cirugiasPorMes,
            'top_procedimientos' => $topProcedimientos,
            'top_cirujanos' => $topCirujanos,
            'top_doctores_solicitudes_realizadas' => $topDoctoresSolicitudesRealizadas,
            'cirugias_por_convenio' => $cirugiasPorConvenio,
            'programacion_kpis' => $programacionKpis,
            'reingreso_mismo_diagnostico' => $reingresoMismoDiagnostico,
            'cirugias_sin_solicitud_previa' => $cirugiasSinSolicitudPrevia,
            'afiliacion_filter' => $afiliacionFilter,
            'afiliacion_options' => $afiliacionOptions,
            'afiliacion_categoria_filter' => $afiliacionCategoriaFilter,
            'afiliacion_categoria_options' => $afiliacionCategoriaOptions,
            'kpi_cards' => $this->buildKpiCards(
                $totalCirugias,
                $sinFacturar,
                $duracionPromedio,
                $estadoProtocolos,
                $programacionKpis,
                $reingresoMismoDiagnostico,
                $cirugiasSinSolicitudPrevia
            ),
        ];
    }

    private function buildDashboardFiltersSummary(
        array $dateRange,
        string $afiliacionFilter,
        array $afiliacionOptions,
        string $afiliacionCategoriaFilter,
        array $afiliacionCategoriaOptions
    ): array
    {
        $filters = [
            ['label' => 'Desde', 'value' => (string)($dateRange['start'] ?? '')],
            ['label' => 'Hasta', 'value' => (string)($dateRange['end'] ?? '')],
        ];

        $afiliacionFilter = $this->normalizeAfiliacionFilter($afiliacionFilter);
        if ($afiliacionFilter !== '') {
            $afiliacionLabel = $afiliacionFilter;
            foreach ($afiliacionOptions as $option) {
                if ((string)($option['value'] ?? '') === $afiliacionFilter) {
                    $afiliacionLabel = (string)($option['label'] ?? $afiliacionFilter);
                    break;
                }
            }
            $filters[] = ['label' => 'Afiliación', 'value' => $afiliacionLabel];
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        if ($afiliacionCategoriaFilter !== '') {
            $afiliacionCategoriaLabel = $afiliacionCategoriaFilter;
            foreach ($afiliacionCategoriaOptions as $option) {
                if ((string)($option['value'] ?? '') === $afiliacionCategoriaFilter) {
                    $afiliacionCategoriaLabel = (string)($option['label'] ?? $afiliacionCategoriaFilter);
                    break;
                }
            }
            $filters[] = ['label' => 'Categoría de afiliación', 'value' => $afiliacionCategoriaLabel];
        }

        return $filters;
    }

    private function buildKpiCards(
        int $totalCirugias,
        int $sinFacturar,
        string $duracionPromedio,
        array $estadoProtocolos,
        array $programacionKpis,
        array $reingresoMismoDiagnostico,
        array $cirugiasSinSolicitudPrevia
    ): array {
        $programadas = (int)($programacionKpis['programadas'] ?? 0);
        $realizadas = (int)($programacionKpis['realizadas'] ?? 0);
        $suspendidas = (int)($programacionKpis['suspendidas'] ?? 0);
        $reprogramadas = (int)($programacionKpis['reprogramadas'] ?? 0);
        $cumplimiento = (float)($programacionKpis['cumplimiento'] ?? 0);
        $tasaSuspendidas = (float)($programacionKpis['tasa_suspendidas'] ?? 0);
        $tasaReprogramacion = (float)($programacionKpis['tasa_reprogramacion'] ?? 0);
        $leadTimePromedioDias = (float)($programacionKpis['tiempo_promedio_solicitud_cirugia_dias'] ?? 0);
        $backlogSinResolucion = (int)($programacionKpis['backlog_sin_resolucion'] ?? 0);
        $backlogEdadPromedioDias = (float)($programacionKpis['backlog_edad_promedio_dias'] ?? 0);
        $completadasTotal = (int)($programacionKpis['completadas_total'] ?? 0);
        $completadasConEvidencia = (int)($programacionKpis['completadas_con_evidencia'] ?? 0);
        $completadasConEvidenciaPct = (float)($programacionKpis['completadas_con_evidencia_pct'] ?? 0);
        $lateralidadEvaluable = (int)($programacionKpis['lateralidad_evaluable'] ?? 0);
        $lateralidadConcordante = (int)($programacionKpis['lateralidad_concordante'] ?? 0);
        $lateralidadConcordanciaPct = (float)($programacionKpis['lateralidad_concordancia_pct'] ?? 0);
        $reingresosTotal = (int)($reingresoMismoDiagnostico['total'] ?? 0);
        $reingresosTasa = (float)($reingresoMismoDiagnostico['tasa'] ?? 0);
        $reingresosEpisodios = (int)($reingresoMismoDiagnostico['episodios'] ?? 0);
        $cirugiasSinSolicitudPreviaTotal = (int)($cirugiasSinSolicitudPrevia['total'] ?? 0);
        $cirugiasSinSolicitudPreviaPct = (float)($cirugiasSinSolicitudPrevia['porcentaje'] ?? 0);

        return [
            ['label' => 'Cirugías en el periodo', 'value' => (string)$totalCirugias, 'hint' => 'Total de protocolos quirúrgicos en el rango'],
            ['label' => 'Protocolos revisados', 'value' => (string)((int)($estadoProtocolos['revisado'] ?? 0)), 'hint' => 'Protocolos completos y validados'],
            ['label' => 'Cirugías sin facturar', 'value' => (string)$sinFacturar, 'hint' => 'Protocolos sin registro en facturación'],
            ['label' => 'Duración promedio', 'value' => $duracionPromedio, 'hint' => 'Tiempo quirúrgico promedio por protocolo'],
            ['label' => 'Cumplimiento programación', 'value' => $this->formatPercent($cumplimiento), 'hint' => $realizadas . ' realizadas / ' . $programadas . ' programadas'],
            ['label' => 'Tasa de suspendidas', 'value' => $this->formatPercent($tasaSuspendidas), 'hint' => $suspendidas . ' suspendidas'],
            ['label' => 'Tasa de reprogramación', 'value' => $this->formatPercent($tasaReprogramacion), 'hint' => $reprogramadas . ' reprogramadas'],
            ['label' => 'Reingreso mismo CIE-10', 'value' => $this->formatPercent($reingresosTasa), 'hint' => $reingresosTotal . ' reingresos / ' . $reingresosEpisodios . ' episodios'],
            ['label' => 'Tiempo solicitud → cirugía', 'value' => $this->formatDays($leadTimePromedioDias), 'hint' => 'Promedio en solicitudes confirmadas'],
            ['label' => 'Backlog sin resolución', 'value' => (string)$backlogSinResolucion, 'hint' => 'Programadas sin cirugía confirmada'],
            ['label' => 'Edad promedio backlog', 'value' => $this->formatDays($backlogEdadPromedioDias), 'hint' => 'Antigüedad de solicitudes abiertas'],
            ['label' => 'Completadas con evidencia', 'value' => $this->formatPercent($completadasConEvidenciaPct), 'hint' => $completadasConEvidencia . ' con match / ' . $completadasTotal . ' completadas'],
            ['label' => 'Concordancia de lateralidad', 'value' => $this->formatPercent($lateralidadConcordanciaPct), 'hint' => $lateralidadConcordante . ' concordantes / ' . $lateralidadEvaluable . ' evaluables'],
            ['label' => 'Cirugías sin solicitud previa', 'value' => $this->formatPercent($cirugiasSinSolicitudPreviaPct), 'hint' => $cirugiasSinSolicitudPreviaTotal . ' de ' . $totalCirugias . ' cirugías'],
        ];
    }

    private function resolveDateRange(): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('-30 days');
        $end = $today;

        if (!empty($_GET['start_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $_GET['start_date']);
            if ($parsed instanceof DateTimeImmutable) {
                $start = $parsed;
            }
        }

        if (!empty($_GET['end_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $_GET['end_date']);
            if ($parsed instanceof DateTimeImmutable) {
                $end = $parsed;
            }
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function resolveAfiliacionFilter(): string
    {
        $value = trim((string)($_GET['afiliacion'] ?? ''));
        return $this->normalizeAfiliacionFilter($value);
    }

    private function resolveAfiliacionCategoriaFilter(): string
    {
        $value = trim((string)($_GET['afiliacion_categoria'] ?? ''));
        return $this->normalizeAfiliacionCategoriaFilter($value);
    }

    private function normalizeAfiliacionFilter(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'sin convenio') {
            return 'sin_convenio';
        }

        return $value;
    }

    private function normalizeAfiliacionCategoriaFilter(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'publica') {
            return 'publico';
        }
        if ($value === 'privada') {
            return 'privado';
        }

        return $value;
    }

    private function formatDateRangeForView(array $range): array
    {
        $start = $range['start'];
        $end = $range['end'];

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'label' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        ];
    }

    private function formatMinutes(float $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        $totalMinutes = (int) round($minutes);
        $hours = intdiv($totalMinutes, 60);
        $mins = $totalMinutes % 60;

        return sprintf('%dh %02dm', $hours, $mins);
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2) . '%';
    }

    private function formatDays(float $value): string
    {
        if ($value <= 0) {
            return '0.0 días';
        }

        return number_format($value, 1) . ' días';
    }
}
