<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Services\CirugiaService;
use App\Modules\Cirugias\Services\CirugiasDashboardService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;
use Throwable;

class CirugiasUiController
{
    private const DASHBOARD_ALLOWED_PERMISSIONS = [
        'cirugias.dashboard.view',
        'administrativo',
        'admin.usuarios.manage',
        'admin.roles.manage',
        'admin.usuarios',
        'admin.roles',
    ];

    private CirugiaService $service;
    private CirugiasDashboardService $dashboardService;
    private PDO $pdo;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->pdo = $pdo;
        $this->service = new CirugiaService($pdo);
        $this->dashboardService = new CirugiasDashboardService($pdo);
    }

    public function index(Request $request): JsonResponse|RedirectResponse|View
    {
        $unauthorized = $this->requireLegacyAuth($request);
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        $fechaFinDefault = (new DateTimeImmutable('today'))->format('Y-m-d');
        $fechaInicioDefault = (new DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d');

        return view('cirugias.v2-index', [
            'pageTitle' => 'Reporte de Cirugías',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'afiliacionOptions' => $this->service->obtenerAfiliacionOptions(),
            'afiliacionCategoriaOptions' => $this->service->obtenerAfiliacionCategoriaOptions(),
            'sedeOptions' => $this->service->obtenerSedeOptions(),
            'fechaInicioDefault' => $fechaInicioDefault,
            'fechaFinDefault' => $fechaFinDefault,
        ]);
    }

    public function wizard(Request $request): JsonResponse|RedirectResponse|View|Response
    {
        $unauthorized = $this->requireLegacyAuth($request);
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        $formId = trim((string) ($request->query('form_id', $request->input('form_id', ''))));
        $hcNumber = trim((string) ($request->query('hc_number', $request->input('hc_number', ''))));

        if ($formId === '' || $hcNumber === '') {
            return response()->view('cirugias.v2-wizard-missing', [
                'pageTitle' => 'Protocolo no encontrado',
                'currentUser' => LegacyCurrentUser::resolve($request),
            ], 400);
        }

        $cirugia = $this->service->obtenerCirugiaPorId($formId, $hcNumber);
        if (!$cirugia) {
            return response()->view('cirugias.v2-wizard-missing', [
                'pageTitle' => 'Protocolo no encontrado',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'formId' => $formId,
                'hcNumber' => $hcNumber,
            ], 404);
        }

        $insumosDisponibles = $this->service->obtenerInsumosDisponibles((string) ($cirugia->afiliacion ?? ''));
        foreach ($insumosDisponibles as &$grupo) {
            uasort($grupo, static fn(array $a, array $b): int => strcmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? '')));
        }
        unset($grupo);

        $insumosSeleccionados = $this->service->obtenerInsumosPorProtocolo($cirugia->procedimiento_id ?? null, $cirugia->insumos ?? null);
        $categorias = array_keys($insumosDisponibles);

        $medicamentosSeleccionados = $this->service->obtenerMedicamentosConfigurados($cirugia->medicamentos ?? null, $cirugia->procedimiento_id ?? null);
        $opcionesMedicamentos = $this->service->obtenerOpcionesMedicamentos();

        return view('cirugias.v2-wizard', [
            'pageTitle' => 'Editar protocolo quirúrgico',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'cirugia' => $cirugia,
            'insumosDisponibles' => $insumosDisponibles,
            'insumosSeleccionados' => $insumosSeleccionados,
            'categoriasInsumos' => $categorias,
            'medicamentosSeleccionados' => $medicamentosSeleccionados,
            'opcionesMedicamentos' => $opcionesMedicamentos,
            'viasDisponibles' => ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'],
            'responsablesMedicamentos' => ['Asistente', 'Anestesiólogo', 'Cirujano Principal'],
            'cirujanos' => $this->obtenerStaffPorEspecialidad(),
        ]);
    }

    public function dashboard(Request $request): JsonResponse|RedirectResponse|View|Response
    {
        $unauthorized = $this->requireLegacyAuth($request);
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        if (!$this->hasAnyPermission($request, self::DASHBOARD_ALLOWED_PERMISSIONS)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Acceso denegado'], 403);
            }

            return response('Acceso denegado', 403);
        }

        $dateRange = $this->resolveDateRange($request);
        $afiliacionFilter = $this->resolveAfiliacionFilter($request);
        $afiliacionCategoriaFilter = $this->resolveAfiliacionCategoriaFilter($request);
        $sedeFilter = $this->resolveSedeFilter($request);
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);

        $data['pageTitle'] = 'Dashboard quirúrgico';
        $data['currentUser'] = LegacyCurrentUser::resolve($request);

        return view('cirugias.v2-dashboard', $data);
    }

    public function exportPdf(Request $request): JsonResponse|RedirectResponse|Response
    {
        $unauthorized = $this->requireLegacyAuth($request);
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        if (!$this->hasAnyPermission($request, self::DASHBOARD_ALLOWED_PERMISSIONS)) {
            return response()->json(['error' => 'Acceso denegado'], 403);
        }

        $dateRange = $this->resolveDateRange($request);
        $afiliacionFilter = $this->resolveAfiliacionFilter($request);
        $afiliacionCategoriaFilter = $this->resolveAfiliacionCategoriaFilter($request);
        $sedeFilter = $this->resolveSedeFilter($request);
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $filters = $this->buildDashboardFiltersSummary(
            $data['date_range'],
            $afiliacionFilter,
            $data['afiliacion_options'],
            $afiliacionCategoriaFilter,
            $data['afiliacion_categoria_options'],
            $sedeFilter,
            $data['sede_options']
        );
        $filename = 'dashboard_cirugias_' . date('Ymd_His') . '.pdf';

        try {
            $reportService = new ReportService();
            $pdf = $reportService->renderPdf('cirugias_dashboard', [
                'titulo' => 'Dashboard de KPIs quirúrgicos',
                'generatedAt' => (new DateTimeImmutable('now'))->format('d-m-Y H:i'),
                'filters' => $filters,
                'cards' => $data['kpi_cards'],
                'periodoLabel' => (string) ($data['date_range']['label'] ?? ''),
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
                return response()->json(['error' => 'No se pudo generar el PDF (contenido inválido).'], 500);
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => (string) strlen($pdf),
            ]);
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            Log::error('cirugias.dashboard.export_pdf.error', [
                'error_id' => $errorId,
                'user_id' => LegacySessionAuth::userId($request),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo generar el PDF (ref: ' . $errorId . ')'], 500);
        }
    }

    public function exportExcel(Request $request): JsonResponse|RedirectResponse|Response
    {
        $unauthorized = $this->requireLegacyAuth($request);
        if ($unauthorized !== null) {
            return $unauthorized;
        }

        if (!$this->hasAnyPermission($request, self::DASHBOARD_ALLOWED_PERMISSIONS)) {
            return response()->json(['error' => 'Acceso denegado'], 403);
        }

        $dateRange = $this->resolveDateRange($request);
        $afiliacionFilter = $this->resolveAfiliacionFilter($request);
        $afiliacionCategoriaFilter = $this->resolveAfiliacionCategoriaFilter($request);
        $sedeFilter = $this->resolveSedeFilter($request);
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $filters = $this->buildDashboardFiltersSummary(
            $data['date_range'],
            $afiliacionFilter,
            $data['afiliacion_options'],
            $afiliacionCategoriaFilter,
            $data['afiliacion_categoria_options'],
            $sedeFilter,
            $data['sede_options']
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
            $sheet->setCellValue("D{$row}", (string) ($data['date_range']['label'] ?? ''));
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
                    $sheet->setCellValue("A{$row}", (string) ($filter['label'] ?? ''));
                    $sheet->setCellValue("B{$row}", (string) ($filter['value'] ?? ''));
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
                $sheet->setCellValue("A{$row}", (string) ($card['label'] ?? ''));
                $sheet->setCellValueExplicit("B{$row}", (string) ($card['value'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", (string) ($card['hint'] ?? ''));
                $sheet->mergeCells("C{$row}:D{$row}");
            }

            foreach (['A' => 34, 'B' => 18, 'C' => 55, 'D' => 12] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $writer = new Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            return response((string) $content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
                'Pragma' => 'public',
            ]);
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            Log::error('cirugias.dashboard.export_excel.error', [
                'error_id' => $errorId,
                'user_id' => LegacySessionAuth::userId($request),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'No se pudo generar el Excel (ref: ' . $errorId . ')'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardPayload(
        array $dateRange,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array {
        $startSql = $dateRange['start']->format('Y-m-d 00:00:00');
        $endSql = $dateRange['end']->format('Y-m-d 23:59:59');
        $dateRangeView = $this->formatDateRangeForView($dateRange);
        $afiliacionFilter = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilter = $this->normalizeSedeFilter($sedeFilter);
        $afiliacionOptions = $this->dashboardService->getAfiliacionOptions($startSql, $endSql);
        $afiliacionCategoriaOptions = $this->dashboardService->getAfiliacionCategoriaOptions($startSql, $endSql);
        $sedeOptions = $this->dashboardService->getSedeOptions($startSql, $endSql);

        $totalCirugias = $this->dashboardService->getTotalCirugias($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $sinFacturar = $this->dashboardService->getCirugiasSinFacturar($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $duracionPromedioRaw = $this->dashboardService->getDuracionPromedioMinutos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $duracionPromedio = $this->formatMinutes($duracionPromedioRaw);
        $estadoProtocolos = $this->dashboardService->getEstadoProtocolos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $cirugiasPorMes = $this->dashboardService->getCirugiasPorMes($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $topProcedimientos = $this->dashboardService->getTopProcedimientos($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $topCirujanos = $this->dashboardService->getTopCirujanos($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $topDoctoresSolicitudesRealizadas = $this->dashboardService->getTopDoctoresSolicitudesRealizadas($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $cirugiasPorConvenio = $this->dashboardService->getCirugiasPorConvenio($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $programacionKpis = $this->dashboardService->getProgramacionKpis($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $facturacionTrazabilidad = $this->dashboardService->getCirugiasFacturacionTrazabilidad($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $reingresoMismoDiagnostico = $this->dashboardService->getReingresoMismoDiagnostico($startSql, $endSql);
        $cirugiasSinSolicitudPrevia = $this->dashboardService->getCirugiasSinSolicitudPrevia($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $tatRevisionProtocolos = $this->dashboardService->getTatRevisionProtocolos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);

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
            'facturacion_trazabilidad' => $facturacionTrazabilidad,
            'reingreso_mismo_diagnostico' => $reingresoMismoDiagnostico,
            'cirugias_sin_solicitud_previa' => $cirugiasSinSolicitudPrevia,
            'tat_revision_protocolos' => $tatRevisionProtocolos,
            'afiliacion_filter' => $afiliacionFilter,
            'afiliacion_options' => $afiliacionOptions,
            'afiliacion_categoria_filter' => $afiliacionCategoriaFilter,
            'afiliacion_categoria_options' => $afiliacionCategoriaOptions,
            'sede_filter' => $sedeFilter,
            'sede_options' => $sedeOptions,
            'kpi_cards' => $this->buildKpiCards(
                $totalCirugias,
                $sinFacturar,
                $duracionPromedio,
                $estadoProtocolos,
                $programacionKpis,
                $facturacionTrazabilidad,
                $reingresoMismoDiagnostico,
                $cirugiasSinSolicitudPrevia,
                $tatRevisionProtocolos
            ),
        ];
    }

    /**
     * @param array<string, mixed> $dateRange
     * @param array<int, array{value:string,label:string}> $afiliacionOptions
     * @param array<int, array{value:string,label:string}> $afiliacionCategoriaOptions
     * @param array<int, array{value:string,label:string}> $sedeOptions
     * @return array<int, array{label:string,value:string}>
     */
    private function buildDashboardFiltersSummary(
        array $dateRange,
        string $afiliacionFilter,
        array $afiliacionOptions,
        string $afiliacionCategoriaFilter,
        array $afiliacionCategoriaOptions,
        string $sedeFilter,
        array $sedeOptions
    ): array {
        $filters = [
            ['label' => 'Desde', 'value' => (string) ($dateRange['start'] ?? '')],
            ['label' => 'Hasta', 'value' => (string) ($dateRange['end'] ?? '')],
        ];

        $afiliacionFilter = $this->normalizeAfiliacionFilter($afiliacionFilter);
        if ($afiliacionFilter !== '') {
            $afiliacionLabel = $afiliacionFilter;
            foreach ($afiliacionOptions as $option) {
                if ((string) ($option['value'] ?? '') === $afiliacionFilter) {
                    $afiliacionLabel = (string) ($option['label'] ?? $afiliacionFilter);
                    break;
                }
            }
            $filters[] = ['label' => 'Afiliación', 'value' => $afiliacionLabel];
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        if ($afiliacionCategoriaFilter !== '') {
            $afiliacionCategoriaLabel = $afiliacionCategoriaFilter;
            foreach ($afiliacionCategoriaOptions as $option) {
                if ((string) ($option['value'] ?? '') === $afiliacionCategoriaFilter) {
                    $afiliacionCategoriaLabel = (string) ($option['label'] ?? $afiliacionCategoriaFilter);
                    break;
                }
            }
            $filters[] = ['label' => 'Categoría de afiliación', 'value' => $afiliacionCategoriaLabel];
        }

        $sedeFilter = $this->normalizeSedeFilter($sedeFilter);
        if ($sedeFilter !== '') {
            $sedeLabel = $sedeFilter;
            foreach ($sedeOptions as $option) {
                if ((string) ($option['value'] ?? '') === $sedeFilter) {
                    $sedeLabel = (string) ($option['label'] ?? $sedeFilter);
                    break;
                }
            }
            $filters[] = ['label' => 'Sede', 'value' => $sedeLabel];
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $estadoProtocolos
     * @param array<string, mixed> $programacionKpis
     * @param array<string, mixed> $facturacionTrazabilidad
     * @param array<string, mixed> $reingresoMismoDiagnostico
     * @param array<string, mixed> $cirugiasSinSolicitudPrevia
     * @param array<string, mixed> $tatRevisionProtocolos
     * @return array<int, array{label:string,value:string,hint:string}>
     */
    private function buildKpiCards(
        int $totalCirugias,
        int $sinFacturar,
        string $duracionPromedio,
        array $estadoProtocolos,
        array $programacionKpis,
        array $facturacionTrazabilidad,
        array $reingresoMismoDiagnostico,
        array $cirugiasSinSolicitudPrevia,
        array $tatRevisionProtocolos
    ): array {
        $programadas = (int) ($programacionKpis['programadas'] ?? 0);
        $realizadas = (int) ($programacionKpis['realizadas'] ?? 0);
        $suspendidas = (int) ($programacionKpis['suspendidas'] ?? 0);
        $reprogramadas = (int) ($programacionKpis['reprogramadas'] ?? 0);
        $atendidos = (int) ($facturacionTrazabilidad['atendidos'] ?? $totalCirugias);
        $facturados = (int) ($facturacionTrazabilidad['facturados'] ?? 0);
        $pendientePago = (int) ($facturacionTrazabilidad['pendiente_pago'] ?? 0);
        $cancelados = (int) ($facturacionTrazabilidad['cancelados'] ?? 0);
        $cumplimiento = (float) ($programacionKpis['cumplimiento'] ?? 0);
        $tasaSuspendidas = (float) ($programacionKpis['tasa_suspendidas'] ?? 0);
        $tasaReprogramacion = (float) ($programacionKpis['tasa_reprogramacion'] ?? 0);
        $leadTimePromedioDias = (float) ($programacionKpis['tiempo_promedio_solicitud_cirugia_dias'] ?? 0);
        $backlogSinResolucion = (int) ($programacionKpis['backlog_sin_resolucion'] ?? 0);
        $backlogEdadPromedioDias = (float) ($programacionKpis['backlog_edad_promedio_dias'] ?? 0);
        $completadasTotal = (int) ($programacionKpis['completadas_total'] ?? 0);
        $completadasConEvidencia = (int) ($programacionKpis['completadas_con_evidencia'] ?? 0);
        $completadasConEvidenciaPct = (float) ($programacionKpis['completadas_con_evidencia_pct'] ?? 0);
        $lateralidadEvaluable = (int) ($programacionKpis['lateralidad_evaluable'] ?? 0);
        $lateralidadConcordante = (int) ($programacionKpis['lateralidad_concordante'] ?? 0);
        $lateralidadConcordanciaPct = (float) ($programacionKpis['lateralidad_concordancia_pct'] ?? 0);
        $reingresosTotal = (int) ($reingresoMismoDiagnostico['total'] ?? 0);
        $reingresosTasa = (float) ($reingresoMismoDiagnostico['tasa'] ?? 0);
        $reingresosEpisodios = (int) ($reingresoMismoDiagnostico['episodios'] ?? 0);
        $cirugiasSinSolicitudPreviaTotal = (int) ($cirugiasSinSolicitudPrevia['total'] ?? 0);
        $cirugiasSinSolicitudPreviaPct = (float) ($cirugiasSinSolicitudPrevia['porcentaje'] ?? 0);
        $tatRevisionMuestra = (int) ($tatRevisionProtocolos['muestra'] ?? 0);
        $tatRevisionPromedioHoras = $tatRevisionProtocolos['tat_promedio_horas'] ?? null;
        $tatRevisionMedianaHoras = $tatRevisionProtocolos['tat_mediana_horas'] ?? null;
        $tatRevisionP90Horas = $tatRevisionProtocolos['tat_p90_horas'] ?? null;

        return [
            ['label' => 'Cirugías en el periodo', 'value' => (string) $totalCirugias, 'hint' => 'Total de protocolos quirúrgicos en el rango'],
            ['label' => 'Atendidos', 'value' => (string) $atendidos, 'hint' => $atendidos > 0 ? ('Base quirúrgica del período') : 'Sin atenciones en el rango'],
            ['label' => 'Facturados', 'value' => (string) $facturados, 'hint' => $atendidos > 0 ? ($this->formatPercent(($facturados * 100) / max(1, $atendidos)) . ' de atendidos') : '0.0% de atendidos'],
            ['label' => 'Pendiente de pago', 'value' => (string) $pendientePago, 'hint' => 'Facturación en estado pendiente/cartera/crédito'],
            ['label' => 'Cancelados', 'value' => (string) $cancelados, 'hint' => 'Solicitudes suspendidas/canceladas en programación'],
            ['label' => 'Protocolos revisados', 'value' => (string) ((int) ($estadoProtocolos['revisado'] ?? 0)), 'hint' => 'Protocolos completos y validados'],
            ['label' => 'Cirugías sin facturar', 'value' => (string) $sinFacturar, 'hint' => 'Protocolos sin registro en facturación'],
            ['label' => 'Duración promedio', 'value' => $duracionPromedio, 'hint' => 'Tiempo quirúrgico promedio por protocolo'],
            ['label' => 'Cumplimiento programación', 'value' => $this->formatPercent($cumplimiento), 'hint' => $realizadas . ' realizadas / ' . $programadas . ' programadas'],
            ['label' => 'Tasa de suspendidas', 'value' => $this->formatPercent($tasaSuspendidas), 'hint' => $suspendidas . ' suspendidas'],
            ['label' => 'Tasa de reprogramación', 'value' => $this->formatPercent($tasaReprogramacion), 'hint' => $reprogramadas . ' reprogramadas'],
            ['label' => 'Reingreso mismo CIE-10', 'value' => $this->formatPercent($reingresosTasa), 'hint' => $reingresosTotal . ' reingresos / ' . $reingresosEpisodios . ' episodios'],
            ['label' => 'Tiempo solicitud → cirugía', 'value' => $this->formatDays($leadTimePromedioDias), 'hint' => 'Promedio en solicitudes confirmadas'],
            ['label' => 'Backlog sin resolución', 'value' => (string) $backlogSinResolucion, 'hint' => 'Programadas sin cirugía confirmada'],
            ['label' => 'Edad promedio backlog', 'value' => $this->formatDays($backlogEdadPromedioDias), 'hint' => 'Antigüedad de solicitudes abiertas'],
            ['label' => 'Completadas con evidencia', 'value' => $this->formatPercent($completadasConEvidenciaPct), 'hint' => $completadasConEvidencia . ' con match / ' . $completadasTotal . ' completadas'],
            ['label' => 'Concordancia de lateralidad', 'value' => $this->formatPercent($lateralidadConcordanciaPct), 'hint' => $lateralidadConcordante . ' concordantes / ' . $lateralidadEvaluable . ' evaluables'],
            ['label' => 'Cirugías sin solicitud previa', 'value' => $this->formatPercent($cirugiasSinSolicitudPreviaPct), 'hint' => $cirugiasSinSolicitudPreviaTotal . ' de ' . $totalCirugias . ' cirugías'],
            ['label' => 'TAT revisión promedio', 'value' => $this->formatHours($tatRevisionPromedioHoras), 'hint' => $tatRevisionMuestra . ' protocolos revisados'],
            ['label' => 'TAT revisión mediana', 'value' => $this->formatHours($tatRevisionMedianaHoras), 'hint' => 'Caso central de revisión'],
            ['label' => 'TAT revisión P90', 'value' => $this->formatHours($tatRevisionP90Horas), 'hint' => '90% revisado antes de este tiempo'],
        ];
    }

    /**
     * @return array{start:DateTimeImmutable,end:DateTimeImmutable}
     */
    private function resolveDateRange(Request $request): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('-30 days');
        $end = $today;

        $startRaw = trim((string) $request->query('start_date', ''));
        if ($startRaw !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $startRaw);
            if ($parsed instanceof DateTimeImmutable) {
                $start = $parsed;
            }
        }

        $endRaw = trim((string) $request->query('end_date', ''));
        if ($endRaw !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $endRaw);
            if ($parsed instanceof DateTimeImmutable) {
                $end = $parsed;
            }
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function resolveAfiliacionFilter(Request $request): string
    {
        return $this->normalizeAfiliacionFilter(trim((string) $request->query('afiliacion', '')));
    }

    private function resolveAfiliacionCategoriaFilter(Request $request): string
    {
        return $this->normalizeAfiliacionCategoriaFilter(trim((string) $request->query('afiliacion_categoria', '')));
    }

    private function resolveSedeFilter(Request $request): string
    {
        return $this->normalizeSedeFilter(trim((string) $request->query('sede', '')));
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

    private function normalizeSedeFilter(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, 'ceib')) {
            return 'CEIBOS';
        }
        if (str_contains($value, 'matriz')) {
            return 'MATRIZ';
        }

        return '';
    }

    /**
     * @param array{start:DateTimeImmutable,end:DateTimeImmutable} $range
     * @return array{start:string,end:string,label:string}
     */
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

    private function formatHours(mixed $value): string
    {
        if ($value === null || !is_numeric($value) || (float) $value < 0) {
            return '—';
        }

        return number_format((float) $value, 2) . ' h';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function obtenerStaffPorEspecialidad(): array
    {
        $especialidades = ['Cirujano Oftalmólogo', 'Anestesiologo', 'Asistente'];
        $staff = [
            'Cirujano Oftalmólogo' => [],
            'Anestesiologo' => [],
            'Asistente' => [],
        ];

        foreach ($especialidades as $especialidad) {
            $stmt = $this->pdo->prepare('SELECT nombre FROM users WHERE especialidad LIKE ? ORDER BY nombre');
            $stmt->execute([$especialidad]);
            $staff[$especialidad] = array_map(
                static fn(array $row): string => (string) ($row['nombre'] ?? ''),
                $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
            );
        }

        return $staff;
    }

    private function requireLegacyAuth(Request $request): JsonResponse|RedirectResponse|null
    {
        if (LegacySessionAuth::isAuthenticated($request)) {
            return null;
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        return redirect('/auth/login?auth_required=1');
    }

    /**
     * @param array<int, string> $permissions
     */
    private function hasAnyPermission(Request $request, array $permissions): bool
    {
        $session = LegacySessionAuth::readSession($request);
        $normalized = $this->normalizePermissions($session['permisos'] ?? []);

        if (in_array('superuser', $normalized, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (in_array($permission, $normalized, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePermissions(mixed $value): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if (is_array($value)) {
            $permissions = [];
            foreach ($value as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim($item);
                if ($item === '' || in_array($item, $permissions, true)) {
                    continue;
                }
                $permissions[] = $item;
            }

            return $permissions;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->normalizePermissions($decoded);
            }

            return [trim($value)];
        }

        return [];
    }
}
