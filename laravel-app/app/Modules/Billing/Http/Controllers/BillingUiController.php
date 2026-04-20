<?php

namespace App\Modules\Billing\Http\Controllers;

use Models\SettingsModel;
use App\Modules\Billing\Services\BillingConsolidadoExportService;
use App\Modules\Billing\Services\BillingDashboardDataService;
use App\Modules\Billing\Services\BillingInformeDataService;
use App\Modules\Billing\Services\BillingInformePacienteService;
use App\Modules\Billing\Services\BillingParticularesReportService;
use App\Modules\Billing\Services\BillingProcedimientosKpiService;
use App\Modules\Billing\Services\HonorariosDashboardDataService;
use App\Modules\Billing\Services\BillingUiService;
use App\Modules\Shared\Support\AfiliacionDimensionService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BillingUiController
{
    private BillingUiService $service;
    private BillingParticularesReportService $particularesReportService;
    private BillingDashboardDataService $dashboardDataService;
    private HonorariosDashboardDataService $honorariosDashboardService;
    private BillingInformeDataService $informeDataService;
    private BillingInformePacienteService $informePacienteService;
    private BillingConsolidadoExportService $consolidadoExportService;
    /** @var array<string, array<string, mixed>> */
    private array $informeConfigs = [];
    private ?SettingsModel $settingsModel = null;
    private static bool $legacyAutoloaderRegistered = false;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        try {
            $sigcenterPdo = DB::connection('sigcenter')->getPdo();
        } catch (\Throwable) {
            $sigcenterPdo = null;
        }
        $this->service = new BillingUiService();
        $this->particularesReportService = new BillingParticularesReportService($pdo);
        $this->dashboardDataService = new BillingDashboardDataService();
        $this->honorariosDashboardService = new HonorariosDashboardDataService();
        $this->informePacienteService = new BillingInformePacienteService($pdo);
        $this->informeDataService = new BillingInformeDataService($pdo, $this->informePacienteService, $sigcenterPdo);
        $this->consolidadoExportService = new BillingConsolidadoExportService($this->informeDataService, $this->informePacienteService);
        $this->informeConfigs = $this->defaultBillingInformeConfigs();
        $this->hydrateBillingInformeConfigsFromSettings();
    }

    public function index(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $mes = trim((string) $request->query('mes', ''));
        $facturas = $this->service->listarFacturas($mes !== '' ? $mes : null);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $facturas,
                'meta' => [
                    'mes' => $mes,
                    'total' => count($facturas),
                ],
            ]);
        }

        return view('billing.v2-index', [
            'pageTitle' => 'Billing',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'mesSeleccionado' => $mes,
            'facturas' => $facturas,
        ]);
    }

    public function noFacturados(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'datatable' => '/v2/api/billing/no-facturados',
                    'afiliaciones' => '/v2/api/billing/afiliaciones',
                    'crear' => '/v2/billing/no-facturados/crear',
                ],
            ]);
        }

        return view('billing.v2-no-facturados', [
            'pageTitle' => 'No Facturados',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'empresaSeguroOptions' => $this->insuranceDimensionOptions('empresa'),
        ]);
    }

    public function detalle(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $formId = trim((string) $request->query('form_id', ''));
        if ($formId === '') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'form_id es obligatorio'], 422);
            }

            return redirect('/v2/billing');
        }

        $detalle = $this->service->obtenerDetalleFactura($formId);
        if ($detalle === null) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Factura no encontrada'], 404);
            }

            return view('billing.v2-detalle-missing', [
                'pageTitle' => 'Factura no encontrada',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'formId' => $formId,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $detalle]);
        }

        return view('billing.v2-detalle', [
            'pageTitle' => 'Detalle de factura',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'detalle' => $detalle,
        ]);
    }

    public function dashboard(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'dashboard_data' => '/v2/billing/dashboard-data',
                    'kpis_procedimientos' => '/v2/api/billing/kpis_procedimientos.php',
                ],
            ]);
        }

        return view('billing.v2-dashboard', [
            'pageTitle' => 'Dashboard Billing',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'afiliacionCategoriaOptions' => $this->insuranceDimensionOptions('categoria'),
            'empresaSeguroOptions' => $this->insuranceDimensionOptions('empresa'),
            'seguroOptions' => $this->insuranceDimensionOptions('seguro'),
        ]);
    }

    public function honorarios(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $cirujanos = $this->service->listarCirujanos();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'honorarios_data' => '/v2/billing/honorarios-data',
                    'cirujanos' => $cirujanos,
                ],
            ]);
        }

        return view('billing.v2-honorarios', [
            'pageTitle' => 'Honorarios médicos',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'cirujanos' => $cirujanos,
            'afiliacionCategoriaOptions' => $this->insuranceDimensionOptions('categoria'),
            'empresaSeguroOptions' => $this->insuranceDimensionOptions('empresa'),
            'seguroOptions' => $this->insuranceDimensionOptions('seguro'),
        ]);
    }

    public function informeIess(Request $request): JsonResponse|RedirectResponse|View
    {
        return $this->renderInformeAfiliacionV2($request, 'iess');
    }

    public function informeIsspol(Request $request): JsonResponse|RedirectResponse|View
    {
        return $this->renderInformeAfiliacionV2($request, 'isspol');
    }

    public function informeIessExcel(Request $request): Response|RedirectResponse
    {
        return $this->exportInformeAfiliacionIessExcel($request);
    }

    public function informeIssfa(Request $request): JsonResponse|RedirectResponse|View
    {
        return $this->renderInformeAfiliacionV2($request, 'issfa');
    }

    public function informeMsp(Request $request): JsonResponse|RedirectResponse|View
    {
        return $this->renderInformeAfiliacionV2($request, 'msp');
    }

    public function informeIessConsolidado(Request $request): Response|RedirectResponse
    {
        $formato = strtoupper((string) $request->query('formato', 'IESS'));
        $zipSolicitado = $request->boolean('zip');

        if (in_array($formato, ['SOAM', 'IESS_SOAM'], true) || $zipSolicitado) {
            return $this->exportConsolidadoIessSoam($request);
        }

        return $this->exportConsolidadoSimple($request, 'iess');
    }

    public function informeIsspolConsolidado(Request $request): Response|RedirectResponse
    {
        return $this->exportConsolidadoSimple($request, 'isspol');
    }

    public function informeIsspolExcel(Request $request): Response|RedirectResponse
    {
        return $this->exportInformeAfiliacionExcel($request, 'ISSPOL');
    }

    public function informeIssfaExcel(Request $request): Response|RedirectResponse
    {
        return $this->exportInformeAfiliacionExcel($request, 'ISSFA');
    }

    public function informeMspExcel(Request $request): Response|RedirectResponse
    {
        return $this->exportInformeAfiliacionExcel($request, 'MSP');
    }

    public function informeIssfaConsolidado(Request $request): Response|RedirectResponse
    {
        return $this->exportConsolidadoSimple($request, 'issfa');
    }

    public function informeMspConsolidado(Request $request): Response|RedirectResponse
    {
        return $this->exportConsolidadoSimple($request, 'msp');
    }

    public function informeParticulares(Request $request): JsonResponse|RedirectResponse|View|Response
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $range = $this->particularesReportService->resolveDateRange(
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', '')
        );
        $filters = [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'empresa_seguro' => (string) $request->query('empresa_seguro', ''),
            'afiliacion' => (string) $request->query('afiliacion', ''),
            'sede' => $this->normalizeSedeFilter((string) $request->query('sede', '')),
            'categoria_cliente' => (string) $request->query('categoria_cliente', ''),
            'categoria_madre_referido' => (string) $request->query('categoria_madre_referido', ''),
            'tipo' => (string) $request->query('tipo', ''),
            'procedimiento' => (string) $request->query('procedimiento', ''),
        ];

        try {
            $baseRows = $this->particularesReportService->obtenerAtencionesParticulares($range['from'], $range['to']);
            $rows = $this->particularesReportService->aplicarFiltros($baseRows, $filters);
            $catalogos = $this->particularesReportService->catalogos($baseRows, $filters);
            $summary = $this->particularesReportService->resumen($rows, $filters);
        } catch (\Throwable) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No se pudo cargar el informe de particulares.'], 500);
            }

            return redirect('/v2/billing')->with('error', 'No se pudo cargar el informe de particulares.');
        }

        $export = strtolower(trim((string) $request->query('export', '')));
        if ($export === 'excel') {
            return $this->exportInformeParticularesExcel($summary, $rows, $filters);
        }
        if ($export === 'csv') {
            return $this->exportInformeParticularesCsv($rows, $filters);
        }
        if ($export === 'pdf') {
            return $this->exportInformeParticularesPdf($summary, $filters);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'filters' => $filters,
                'meta' => [
                    'range' => [
                        'from' => $range['from'],
                        'to' => $range['to'],
                    ],
                    'total' => $summary['total'],
                ],
                'catalogos' => $catalogos,
                'summary' => $summary,
                'data' => $rows,
            ]);
        }

        return view('billing.v2-informe-particulares', [
            'pageTitle' => 'Informe de Atenciones Particulares',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $summary,
            'catalogos' => $catalogos,
        ]);
    }

    public function informeParticularesReferidos(Request $request): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $range = $this->particularesReportService->resolveDateRange(
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', '')
        );

        $filters = [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'empresa_seguro' => (string) $request->query('empresa_seguro', ''),
            'afiliacion' => (string) $request->query('afiliacion', ''),
            'sede' => $this->normalizeSedeFilter((string) $request->query('sede', '')),
            'categoria_cliente' => (string) $request->query('categoria_cliente', ''),
            'categoria_madre_referido' => '',
            'tipo' => (string) $request->query('tipo', ''),
            'procedimiento' => (string) $request->query('procedimiento', ''),
        ];

        $selectedCategory = $this->normalizeReferralDashboardCategory(
            (string) $request->query('categoria_referido', $request->query('categoria_madre_referido', ''))
        );
        if ($selectedCategory === '') {
            $selectedCategory = 'MARKETING';
        }

        try {
            $baseRows = $this->particularesReportService->obtenerAtencionesParticulares($range['from'], $range['to']);
            $comparisonRows = $this->particularesReportService->aplicarFiltros($baseRows, $filters);
            $catalogos = $this->particularesReportService->catalogos($baseRows, $filters);
            $selectedRows = $this->filterRowsByReferralCategories($comparisonRows, [$selectedCategory]);
            $overallSummary = $this->particularesReportService->resumen($comparisonRows, $filters);
            $selectedSummary = $this->particularesReportService->resumen(
                $selectedRows,
                array_merge($filters, ['categoria_madre_referido' => $selectedCategory])
            );
            $dashboard = $this->buildReferralStrategicDashboard(
                $comparisonRows,
                $selectedRows,
                $overallSummary,
                $selectedSummary,
                $filters,
                $selectedCategory
            );
        } catch (\Throwable) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No se pudo cargar el dashboard estratégico de referidos.'], 500);
            }

            return redirect('/v2/informes/particulares')
                ->with('error', 'No se pudo cargar el dashboard estratégico de referidos.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'filters' => $filters,
                'selected_category' => $selectedCategory,
                'summary' => $selectedSummary,
                'dashboard' => $dashboard,
                'data' => $selectedRows,
            ]);
        }

        return view('billing.v2-informe-particulares-referidos', [
            'pageTitle' => 'Dashboard estratégico de referidos',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'filters' => $filters,
            'selectedCategory' => $selectedCategory,
            'rows' => $selectedRows,
            'summary' => $selectedSummary,
            'overallSummary' => $overallSummary,
            'dashboard' => $dashboard,
            'catalogos' => $catalogos,
        ]);
    }

    public function dashboardData(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $payload = $request->all();
        $range = $this->resolveDashboardRange(is_array($payload) ? $payload : []);
        $sedeFilter = $this->normalizeSedeFilter((string) ($payload['sede'] ?? ''));
        $categoriaFilter = trim((string) ($payload['categoria_seguro'] ?? ''));
        $empresaFilter = trim((string) ($payload['empresa_seguro'] ?? ''));
        $seguroFilter = trim((string) ($payload['seguro'] ?? ''));

        try {
            $data = $this->dashboardDataService->buildSummary(
                $range['start']->format('Y-m-d 00:00:00'),
                $range['end']->format('Y-m-d 23:59:59'),
                $sedeFilter,
                $categoriaFilter,
                $empresaFilter,
                $seguroFilter
            );
        } catch (\Throwable) {
            return response()->json(['error' => 'No se pudo cargar el dashboard de billing.'], 500);
        }

        return response()->json([
            'filters' => [
                'date_from' => $range['from'],
                'date_to' => $range['to'],
                'sede' => $sedeFilter,
                'categoria_seguro' => $categoriaFilter,
                'empresa_seguro' => $empresaFilter,
                'seguro' => $seguroFilter,
            ],
            'data' => $data,
        ]);
    }

    public function honorariosData(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $payload = $request->all();
        $range = $this->resolveDashboardRange(is_array($payload) ? $payload : []);
        $filters = [
            'cirujano' => $payload['cirujano'] ?? null,
            'categoria_seguro' => $payload['categoria_seguro'] ?? null,
            'empresa_seguro' => $payload['empresa_seguro'] ?? null,
            'seguro' => $payload['seguro'] ?? null,
        ];
        $rules = is_array($payload['reglas'] ?? null) ? $payload['reglas'] : [];

        try {
            $data = $this->honorariosDashboardService->buildSummary(
                $range['start']->format('Y-m-d 00:00:00'),
                $range['end']->format('Y-m-d 23:59:59'),
                $filters,
                $rules
            );
        } catch (\Throwable) {
            return response()->json(['error' => 'No se pudo cargar el dashboard de honorarios.'], 500);
        }

        return response()->json([
            'filters' => [
                'date_from' => $range['from'],
                'date_to' => $range['to'],
                'cirujano' => $filters['cirujano'],
                'categoria_seguro' => $filters['categoria_seguro'],
                'empresa_seguro' => $filters['empresa_seguro'],
                'seguro' => $filters['seguro'],
            ],
            'data' => $data,
        ]);
    }

    public function kpisProcedimientos(Request $request): Response
    {
        $filters = [
            'company_id' => $request->query('company_id'),
            'year' => $request->query('year'),
            'sede' => $request->query('sede'),
            'tipo_cliente' => $request->query('tipo_cliente', $request->query('tipoCliente')),
            'empresa_seguro' => $request->query('empresa_seguro'),
            'seguro' => $request->query('seguro'),
            'categoria' => $request->query('categoria'),
        ];

        $mode = strtolower(trim((string) $request->query('mode', 'summary')));
        $limit = (int) $request->query('limit', 500);
        if ($limit <= 0) {
            $limit = 500;
        }
        $limit = min($limit, 5000);

        try {
            $pdo = DB::connection()->getPdo();
            $service = new BillingProcedimientosKpiService($pdo);

            if ($mode === 'detail') {
                $data = $service->detail($filters, $limit);

                if (strtolower((string) $request->query('export', '')) === 'csv') {
                    $filename = sprintf('kpi_procedimientos_detalle_%s.csv', date('Ymd_His'));
                    $headers = [
                        'Content-Type' => 'text/csv; charset=utf-8',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ];

                    $handle = fopen('php://temp', 'r+');
                    if ($handle === false) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No se pudo generar el archivo CSV.',
                        ], 500);
                    }

                    fputcsv($handle, ['Fecha', 'Form ID', 'HC', 'Paciente', 'Afiliacion', 'Tipo cliente', 'Categoria', 'Codigo', 'Detalle', 'Valor']);
                    foreach ($data['rows'] ?? [] as $row) {
                        fputcsv($handle, [
                            $row['fecha'] ?? '',
                            $row['form_id'] ?? '',
                            $row['hc_number'] ?? '',
                            $row['paciente'] ?? '',
                            $row['afiliacion'] ?? '',
                            $row['tipo_cliente'] ?? '',
                            $row['categoria'] ?? '',
                            $row['codigo'] ?? '',
                            $row['detalle'] ?? '',
                            $row['valor'] ?? 0,
                        ]);
                    }
                    rewind($handle);
                    $csvContent = stream_get_contents($handle);
                    fclose($handle);

                    return response($csvContent !== false ? $csvContent : '', 200, $headers);
                }
            } else {
                $data = $service->build($filters);
            }

            return response()->json([
                'success' => true,
                'mode' => $mode,
                'data' => $data,
                'filters' => $filters,
            ]);
        } catch (\Throwable $e) {
            $debugEnabled = (string) $request->query('debug') === '1';
            $errorPayload = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            Log::error('[kpis_procedimientos] Error', [
                'error' => $errorPayload,
                'filters' => $filters,
            ]);

            $response = [
                'success' => false,
                'message' => 'No se pudo calcular los KPIs de procedimientos.',
            ];

            if ($debugEnabled) {
                $response['debug'] = $errorPayload;
            }

            return response()->json($response, 500);
        }
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    private function insuranceDimensionOptions(string $type): array
    {
        $service = new AfiliacionDimensionService(DB::connection()->getPdo());

        return match ($type) {
            'categoria' => $service->getCategoriaOptions('Todas las categorías'),
            'empresa' => $service->getEmpresaOptions('Todas las empresas'),
            'seguro' => $service->getSeguroOptions('Todos los seguros'),
            default => [],
        };
    }

    private function isLegacyAuthenticated(Request $request): bool
    {
        return LegacySessionAuth::isAuthenticated($request);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{start:DateTimeImmutable,end:DateTimeImmutable,from:string,to:string}
     */
    private function resolveDashboardRange(array $filters): array
    {
        $now = new DateTimeImmutable('now');
        $fallbackStart = $now->sub(new DateInterval('P90D'));

        $fromRaw = $this->normalizeDateInput($filters['date_from'] ?? null);
        $toRaw = $this->normalizeDateInput($filters['date_to'] ?? null);

        $start = $fromRaw ? $this->parseDate($fromRaw) : null;
        $end = $toRaw ? $this->parseDate($toRaw) : null;

        if (!$start) {
            $start = $fallbackStart;
        }
        if (!$end) {
            $end = $now;
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start,
            'end' => $end,
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
        ];
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $clean = trim((string) $value);
        return $clean !== '' ? $clean : null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function billingInformeConfigs(): array
    {
        return $this->informeConfigs;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultBillingInformeConfigs(): array
    {
        return [
            'iess' => [
                'slug' => 'iess',
                'titulo' => 'Informe IESS',
                'basePath' => '/v2/informes/iess',
                'detailExcelPath' => '/v2/informes/iess/excel',
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
                'afiliaciones' => [
                    'contribuyente voluntario',
                    'conyuge',
                    'conyuge pensionista',
                    'seguro campesino',
                    'seguro campesino jubilado',
                    'seguro general',
                    'seguro general jubilado',
                    'seguro general por montepio',
                    'seguro general tiempo parcial',
                    'iess',
                    'hijos dependientes',
                ],
                'excelButtons' => [
                    [
                        'grupo' => 'IESS',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                        'query' => ['formato' => 'IESS'],
                    ],
                    [
                        'grupo' => 'IESS_SOAM',
                        'label' => 'Descargar SOAM',
                        'class' => 'btn btn-outline-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                        'query' => ['formato' => 'IESS_SOAM'],
                    ],
                ],
                'scrapeButtonLabel' => '📋 Ver todas las atenciones por cobrar',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes IESS',
                'tableHeaderClass' => 'bg-success-light',
            ],
            'isspol' => [
                'slug' => 'isspol',
                'titulo' => 'Informe ISSPOL',
                'basePath' => '/v2/informes/isspol',
                'detailExcelPath' => '/v2/informes/isspol/excel',
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
                'afiliaciones' => ['isspol'],
                'excelButtons' => [
                    [
                        'grupo' => 'ISSPOL',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Obtener código de derivación',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes ISSPOL',
                'enableApellidoFilter' => true,
                'tableHeaderClass' => 'bg-info-light',
            ],
            'issfa' => [
                'slug' => 'issfa',
                'titulo' => 'Informe ISSFA',
                'basePath' => '/v2/informes/issfa',
                'detailExcelPath' => '/v2/informes/issfa/excel',
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
                'afiliaciones' => ['issfa'],
                'excelButtons' => [
                    [
                        'grupo' => 'ISSFA',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Obtener código de derivación',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes ISSFA',
                'enableApellidoFilter' => true,
                'tableHeaderClass' => 'bg-warning-light',
            ],
            'msp' => [
                'slug' => 'msp',
                'titulo' => 'Informe MSP',
                'basePath' => '/v2/informes/msp',
                'detailExcelPath' => '/v2/informes/msp/excel',
                'tableOptions' => [
                    'pageLength' => 25,
                    'defaultOrder' => 'fecha_ingreso_desc',
                ],
                'afiliaciones' => ['msp'],
                'excelButtons' => [
                    [
                        'grupo' => 'MSP',
                        'label' => 'Descargar Excel',
                        'class' => 'btn btn-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
                    ],
                ],
                'scrapeButtonLabel' => '📋 Obtener código de derivación',
                'consolidadoTitulo' => 'Consolidado mensual de pacientes MSP',
                'enableApellidoFilter' => true,
                'tableHeaderClass' => 'bg-primary-light',
            ],
        ];
    }

    private function renderInformeAfiliacionV2(Request $request, string $grupo): JsonResponse|RedirectResponse|View
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $configs = $this->billingInformeConfigs();
        if (!isset($configs[$grupo])) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Informe no disponible'], 404);
            }

            return redirect('/v2/billing');
        }

        $config = $configs[$grupo];
        $pdo = DB::connection()->getPdo();
        $billingController = $this->informeDataService;
        $pacienteService = $this->informePacienteService;
        $scrapingOutput = null;
        $scrapingLimitMessage = '';

        $formIdScrapeRaw = $request->input('form_id_scrape', $request->query('form_id'));
        $formIdsScrape = array_values(array_filter(array_map(
            'trim',
            is_array($formIdScrapeRaw)
                ? $formIdScrapeRaw
                : preg_split('/\s*,\s*/', (string) $formIdScrapeRaw)
        )));

        $maxScrapeBatch = 200;
        if (count($formIdsScrape) > $maxScrapeBatch) {
            $scrapingLimitMessage = "⚠️ Se limitaron las selecciones a los primeros {$maxScrapeBatch} registros para evitar saturar el servidor.";
            $formIdsScrape = array_slice($formIdsScrape, 0, $maxScrapeBatch);
        }

        $hcNumberScrapeRaw = $request->input('hc_number_scrape', $request->query('hc_number'));
        $hcNumbersScrape = [];
        if (is_array($hcNumberScrapeRaw)) {
            $hcNumbersScrape = array_values(array_filter(array_map('trim', $hcNumberScrapeRaw)));
        } elseif (!empty($hcNumberScrapeRaw)) {
            $hcNumbersScrape = array_fill(0, max(count($formIdsScrape), 1), (string) $hcNumberScrapeRaw);
        }

        if ($request->isMethod('post') && $request->has('scrape_derivacion') && $formIdsScrape && $hcNumbersScrape) {
            $outputs = [];

            if (count($hcNumbersScrape) === 1 && count($formIdsScrape) > 1) {
                $hcNumbersScrape = array_fill(0, count($formIdsScrape), $hcNumbersScrape[0]);
            }

            foreach ($formIdsScrape as $index => $formIdScrape) {
                $hcNumberScrape = $hcNumbersScrape[$index] ?? $hcNumbersScrape[0] ?? null;
                if (!$hcNumberScrape) {
                    continue;
                }

                $payload = $billingController->buildDerivacionLookupPayload((string) $formIdScrape, (string) $hcNumberScrape);
                if ($payload !== []) {
                    $codigoLookup = trim((string) ($payload['codigo_derivacion'] ?? ''));
                    $saved = false;
                    if ($codigoLookup !== '') {
                        $saved = $billingController->persistDerivacionLookupPayload($payload);
                        $payload['_saved'] = $saved;
                        if ($saved) {
                            $payload['archivo_derivacion_url'] = $billingController->resolveDerivacionArchivoUrl(
                                (string) $formIdScrape,
                                '/v2/derivaciones/archivo-form'
                            );
                        }
                    }
                    if ($codigoLookup === '') {
                        Log::warning('Billing derivacion lookup sin codigo', [
                            'grupo' => $grupo,
                            'form_id' => (string) $formIdScrape,
                            'hc_number' => (string) $hcNumberScrape,
                            'debug' => $payload['_debug'] ?? null,
                        ]);
                    }
                    $outputs[] = $payload;
                }
            }

            $outputs = array_values(array_filter($outputs, static fn($output) => is_array($output) && $output !== []));
            if (count($outputs) === 1) {
                $scrapingOutput = reset($outputs);
            } elseif ($outputs !== []) {
                $scrapingOutput = $outputs[0];
                $scrapingOutput['procedimientos'] = [];
                $scrapingOutput['_debug_items'] = [];
                $scrapingOutput['_saved_count'] = 0;
                $seen = [];
                foreach ($outputs as $output) {
                    if (!empty($output['_saved'])) {
                        $scrapingOutput['_saved_count']++;
                    }
                    if (!empty($output['_debug'])) {
                        $scrapingOutput['_debug_items'][] = $output['_debug'];
                    }
                    foreach ((array) ($output['procedimientos'] ?? []) as $procedimiento) {
                        $procId = trim((string) ($procedimiento['procedimiento_proyectado']['id'] ?? ''));
                        if ($procId === '' || isset($seen[$procId])) {
                            continue;
                        }

                        $seen[$procId] = true;
                        $scrapingOutput['procedimientos'][] = $procedimiento;
                    }
                }
            }

            if ($scrapingLimitMessage !== '') {
                if (is_array($scrapingOutput)) {
                    $scrapingOutput['_message'] = $scrapingLimitMessage;
                } else {
                    $scrapingOutput = ($scrapingOutput !== null && $scrapingOutput !== '')
                        ? $scrapingLimitMessage . "\n" . $scrapingOutput
                        : $scrapingLimitMessage;
                }
            }
        } elseif ($scrapingLimitMessage !== '') {
            $scrapingOutput = $scrapingLimitMessage;
        }

        $readInput = static function (Request $req, string $key, mixed $default = ''): mixed {
            if ($req->query->has($key)) {
                return $req->query($key);
            }
            if ($req->request->has($key)) {
                return $req->input($key);
            }
            return $default;
        };

        $filtros = [
            'modo' => 'consolidado',
            'billing_id' => $readInput($request, 'billing_id', null),
            'mes' => (string) $readInput($request, 'mes', ''),
            'apellido' => (string) $readInput($request, 'apellido', ''),
            'hc_number' => (string) $readInput($request, 'hc_number', ''),
            'derivacion' => (string) $readInput($request, 'derivacion', ''),
            'afiliacion' => (string) $readInput($request, 'afiliacion', ''),
            'sede' => $this->normalizeSedeFilter((string) $readInput($request, 'sede', '')),
            'vista' => (string) $readInput($request, 'vista', ''),
        ];

        $billingIds = isset($filtros['billing_id']) && $filtros['billing_id'] !== ''
            ? array_values(array_filter(array_map('trim', explode(',', (string) $filtros['billing_id']))))
            : [];

        $mesSeleccionado = $filtros['mes'];
        $vistaParamPresente = $request->query->has('vista') || $request->request->has('vista');
        $vista = $vistaParamPresente ? (string) $filtros['vista'] : '';
        if (!$vistaParamPresente && $mesSeleccionado !== '') {
            $vista = 'rapida';
        }

        $facturas = $billingController->obtenerFacturasDisponibles($mesSeleccionado !== '' ? $mesSeleccionado : null);
        if ($billingIds === [] && $facturas !== []) {
            $pacienteService->preloadPatientDetails(array_map(
                static fn(array $factura): string => (string) ($factura['hc_number'] ?? ''),
                $facturas
            ));
        }

        $necesitaDerivacion = $vista !== 'rapida' || !empty($filtros['derivacion']);
        $cacheDerivaciones = [];
        $grupos = [];
        if ($necesitaDerivacion) {
            foreach ($facturas as $factura) {
                $formId = $factura['form_id'] ?? '';
                if (!isset($cacheDerivaciones[$formId])) {
                    $cacheDerivaciones[$formId] = $billingController->obtenerDerivacionPorFormId($formId);
                }
                $derivacion = $cacheDerivaciones[$formId];
                $codigo = $derivacion['codigo_derivacion'] ?? $derivacion['cod_derivacion'] ?? null;
                $keyAgrupacion = $codigo ?: 'SIN_CODIGO';

                $grupos[$keyAgrupacion][] = [
                    'factura' => $factura,
                    'codigo' => $codigo,
                    'form_id' => $formId,
                    'tiene_codigo' => !empty($codigo),
                ];
            }
        }

        $cachePorMes = [];
        $pacientesCache = [];
        $datosCache = [];
        $sedesCache = [];
        $facturasMesSeleccionado = [];
        $formIdsConsolidado = [];
        if ($mesSeleccionado !== '') {
            foreach ($facturas as $factura) {
                $fechaOrdenada = $factura['fecha_ordenada'] ?? null;
                $mes = $fechaOrdenada ? date('Y-m', strtotime((string) $fechaOrdenada)) : '';
                if ($mes !== $mesSeleccionado) {
                    continue;
                }

                $facturasMesSeleccionado[] = $factura;
                $formId = (string) ($factura['form_id'] ?? '');
                if ($formId !== '') {
                    $formIdsConsolidado[] = $formId;
                }
            }

            $pacienteService->preloadPatientDetails(array_map(
                static fn(array $factura): string => (string) ($factura['hc_number'] ?? ''),
                $facturasMesSeleccionado
            ));

            foreach ($facturasMesSeleccionado as $factura) {
                $fechaOrdenada = $factura['fecha_ordenada'] ?? null;
                $mes = $fechaOrdenada ? date('Y-m', strtotime((string) $fechaOrdenada)) : '';
                $hc = (string) ($factura['hc_number'] ?? '');

                if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
                    $paciente = $pacienteService->getPatientDetails($hc);
                    $cachePorMes[$mes]['pacientes'][$hc] = $paciente;
                    $pacientesCache[$hc] = $paciente;
                }
            }
        }

        $formIds = [];
        $datosFacturas = [];
        if ($billingIds !== []) {
            $placeholders = implode(',', array_fill(0, count($billingIds), '?'));
            $stmt = $pdo->prepare("SELECT id, form_id FROM billing_main WHERE id IN ($placeholders)");
            $stmt->execute($billingIds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $formId = (string) ($row['form_id'] ?? '');
                if ($formId === '') {
                    continue;
                }
                $formIds[] = $formId;
                $datos = $billingController->obtenerDatos($formId);
                if ($datos) {
                    $datosFacturas[] = $datos;
                    $datosCache[$formId] = $datos;
                }
            }
        }

        if ($billingIds === [] && $formIdsConsolidado !== []) {
            $billingController->preloadResumenesConsolidado($formIdsConsolidado);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'meta' => [
                    'grupo' => $grupo,
                    'mes' => $mesSeleccionado,
                    'facturas' => count($facturas),
                    'detalle_mode' => $billingIds !== [],
                ],
                'filters' => $filtros,
            ]);
        }

        $reportHtml = $this->renderLegacyInformeTemplate([
            'pageTitle' => $config['titulo'],
            'scrapingOutput' => $scrapingOutput,
            'filtros' => $filtros,
            'mesSeleccionado' => $mesSeleccionado,
            'vista' => $vista,
            'facturas' => $facturas,
            'grupos' => $grupos,
            'cachePorMes' => $cachePorMes,
            'cacheDerivaciones' => $cacheDerivaciones,
            'billingIds' => $billingIds,
            'formIds' => $formIds,
            'datosFacturas' => $datosFacturas,
            'pacienteService' => $pacienteService,
            'billingController' => $billingController,
            'pacientesCache' => $pacientesCache,
            'datosCache' => $datosCache,
            'sedesCache' => $sedesCache,
            'grupoConfig' => $config,
            'requestQuery' => $request->query(),
            'pdo' => $pdo,
        ]);

        return view('billing.v2-informe-afiliacion-wrapper', [
            'pageTitle' => $config['titulo'],
            'currentUser' => LegacyCurrentUser::resolve($request),
            'reportHtml' => $reportHtml,
            'skipDefaultVendorScripts' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderLegacyInformeTemplate(array $data): string
    {
        $templatePath = resource_path('views/billing/informes/informe_afiliacion.php');
        if (!is_file($templatePath)) {
            return '<div class="alert alert-danger">No se encontró la plantilla del informe.</div>';
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $templatePath;
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    private function redirectLegacyInformePath(string $legacyPath, Request $request): RedirectResponse
    {
        $query = $request->getQueryString();
        $target = $legacyPath . ($query ? '?' . $query : '');

        return redirect($target);
    }

    private function exportConsolidadoSimple(Request $request, string $grupo): Response|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $filters = [
            'mes' => (string) $request->query('mes', ''),
            'afiliacion' => (string) $request->query('afiliacion', ''),
            'apellido' => (string) $request->query('apellido', ''),
            'hc_number' => (string) $request->query('hc_number', ''),
            'derivacion' => (string) $request->query('derivacion', ''),
            'sede' => $this->normalizeSedeFilter((string) $request->query('sede', '')),
            'categoria' => (string) $request->query('categoria', ''),
            'form_ids' => $request->query('form_ids', []),
        ];

        try {
            $export = $this->consolidadoExportService->exportSimple($grupo, $filters);
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar consolidado v2', [
                'grupo' => $grupo,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson() || $request->boolean('debug')) {
                $payload = ['error' => 'No se pudo generar el consolidado.'];
                if ($request->boolean('debug')) {
                    $payload['debug'] = [
                        'message' => $exception->getMessage(),
                        'type' => $exception::class,
                    ];
                }
                return response()->json($payload, 500);
            }

            return redirect('/v2/informes/' . $grupo)
                ->with('error', 'No se pudo generar el consolidado.');
        }

        return response($export['content'], 200, [
            'Content-Type' => $export['content_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function exportInformeAfiliacionExcel(Request $request, string $grupo): Response|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $formId = trim((string) $request->query('form_id', ''));
        if ($formId === '') {
            return redirect('/v2/informes/' . strtolower($grupo))
                ->with('error', 'No se indicó un form_id válido para exportar.');
        }

        try {
            $export = $this->consolidadoExportService->exportIndividualLegacyAdapter($grupo, $formId);
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar excel individual v2', [
                'grupo' => $grupo,
                'form_id' => $formId,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson() || $request->boolean('debug')) {
                $payload = ['error' => 'No se pudo generar el excel individual.'];
                if ($request->boolean('debug')) {
                    $payload['debug'] = [
                        'message' => $exception->getMessage(),
                        'type' => $exception::class,
                    ];
                }

                return response()->json($payload, 500);
            }

            return redirect('/v2/informes/' . strtolower($grupo))
                ->with('error', 'No se pudo generar el excel individual.');
        }

        return response($export['content'], 200, [
            'Content-Type' => $export['content_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function exportInformeAfiliacionIessExcel(Request $request): Response|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $filters = [
            'form_id' => (string) $request->query('form_id', ''),
            'form_ids' => $request->query('form_ids', []),
        ];
        $formato = strtoupper((string) $request->query('formato', 'IESS'));

        try {
            $export = $this->consolidadoExportService->exportIessIndividual($filters, $formato);
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar excel individual IESS v2', [
                'form_id' => $filters['form_id'],
                'formato' => $formato,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson() || $request->boolean('debug')) {
                $payload = ['error' => 'No se pudo generar el excel individual de IESS.'];
                if ($request->boolean('debug')) {
                    $payload['debug'] = [
                        'message' => $exception->getMessage(),
                        'type' => $exception::class,
                    ];
                }

                return response()->json($payload, 500);
            }

            return redirect('/v2/informes/iess')
                ->with('error', 'No se pudo generar el excel individual de IESS.');
        }

        return response($export['content'], 200, [
            'Content-Type' => $export['content_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function exportConsolidadoIessSoam(Request $request): Response|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $filters = [
            'mes' => (string) $request->query('mes', ''),
            'afiliacion' => (string) $request->query('afiliacion', ''),
            'apellido' => (string) $request->query('apellido', ''),
            'hc_number' => (string) $request->query('hc_number', ''),
            'derivacion' => (string) $request->query('derivacion', ''),
            'sede' => $this->normalizeSedeFilter((string) $request->query('sede', '')),
            'categoria' => (string) $request->query('categoria', ''),
            'form_ids' => $request->query('form_ids', []),
        ];

        try {
            $export = $this->consolidadoExportService->exportIessSoam($filters, $request->boolean('zip'));
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar consolidado IESS SOAM v2', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            if ($request->expectsJson() || $request->boolean('debug')) {
                $payload = ['error' => 'No se pudo generar el consolidado SOAM.'];
                if ($request->boolean('debug')) {
                    $payload['debug'] = [
                        'message' => $exception->getMessage(),
                        'type' => $exception::class,
                    ];
                }
                return response()->json($payload, 500);
            }

            return redirect('/v2/informes/iess')
                ->with('error', 'No se pudo generar el consolidado SOAM.');
        }

        return response($export['content'], 200, [
            'Content-Type' => $export['content_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function hydrateBillingInformeConfigsFromSettings(): void
    {
        $settings = $this->resolveSettingsModel();
        if (!($settings instanceof SettingsModel)) {
            return;
        }

        $keys = [];
        foreach (array_keys($this->informeConfigs) as $slug) {
            $keys = array_merge($keys, $this->buildSettingKeysForInformeSlug($slug));
        }

        try {
            $options = $settings->getOptions($keys);
        } catch (\Throwable $exception) {
            Log::warning('No fue posible cargar ajustes de informes Billing v2', [
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        foreach ($this->informeConfigs as $slug => &$config) {
            $this->applySettingOverridesToInformeConfig($config, $options, $slug);
        }
        unset($config);
    }

    /**
     * @return array<int, string>
     */
    private function buildSettingKeysForInformeSlug(string $slug): array
    {
        $prefix = 'billing_informes_' . $slug . '_';

        return [
            $prefix . 'title',
            $prefix . 'base_path',
            $prefix . 'scrape_label',
            $prefix . 'consolidado_title',
            $prefix . 'apellido_filter',
            $prefix . 'afiliaciones',
            $prefix . 'excel_buttons',
            $prefix . 'table_page_length',
            $prefix . 'table_order',
            $prefix . 'table_header_class',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $options
     */
    private function applySettingOverridesToInformeConfig(array &$config, array $options, string $slug): void
    {
        $prefix = 'billing_informes_' . $slug . '_';

        if (!empty($options[$prefix . 'title'])) {
            $config['titulo'] = (string) $options[$prefix . 'title'];
        }
        if (!empty($options[$prefix . 'base_path'])) {
            $basePath = '/' . ltrim((string) $options[$prefix . 'base_path'], '/');
            $config['basePath'] = str_starts_with($basePath, '/v2/') ? $basePath : '/v2' . $basePath;
        }
        if (!empty($options[$prefix . 'scrape_label'])) {
            $config['scrapeButtonLabel'] = (string) $options[$prefix . 'scrape_label'];
        }
        if (!empty($options[$prefix . 'consolidado_title'])) {
            $config['consolidadoTitulo'] = (string) $options[$prefix . 'consolidado_title'];
        }
        if (isset($options[$prefix . 'apellido_filter'])) {
            $config['enableApellidoFilter'] = $this->castBooleanSetting($options[$prefix . 'apellido_filter']);
        }
        if (!empty($options[$prefix . 'afiliaciones'])) {
            $config['afiliaciones'] = $this->parseLineSeparatedSetting((string) $options[$prefix . 'afiliaciones']);
        }
        if (!empty($options[$prefix . 'excel_buttons'])) {
            $config['excelButtons'] = $this->parseExcelButtonsSetting(
                (string) $options[$prefix . 'excel_buttons'],
                is_array($config['excelButtons'] ?? null) ? $config['excelButtons'] : []
            );
        }
        if (!empty($options[$prefix . 'table_page_length'])) {
            $config['tableOptions']['pageLength'] = max(5, (int) $options[$prefix . 'table_page_length']);
        }
        if (!empty($options[$prefix . 'table_order'])) {
            $config['tableOptions']['defaultOrder'] = $this->sanitizeTableOrder((string) $options[$prefix . 'table_order']);
        }
        if (!empty($options[$prefix . 'table_header_class'])) {
            $config['tableHeaderClass'] = trim((string) $options[$prefix . 'table_header_class']);
        }
    }

    private function sanitizeTableOrder(string $order): string
    {
        $allowed = [
            'fecha_ingreso_desc',
            'fecha_ingreso_asc',
            'nombre_asc',
            'nombre_desc',
            'monto_desc',
            'monto_asc',
        ];

        return in_array($order, $allowed, true) ? $order : 'fecha_ingreso_desc';
    }

    private function castBooleanSetting(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['1', 1, 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<int, string>
     */
    private function parseLineSeparatedSetting(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    /**
     * @param array<int, array<string, mixed>> $fallback
     * @return array<int, array<string, string>>
     */
    private function parseExcelButtonsSetting(string $rawValue, array $fallback): array
    {
        $lines = $this->parseLineSeparatedSetting($rawValue);
        $buttons = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            $parts = array_pad($parts, 4, '');
            [$grupo, $label, $class, $icon] = $parts;
            if ($grupo === '') {
                continue;
            }

            $buttons[] = [
                'grupo' => $grupo,
                'label' => $label !== '' ? $label : 'Descargar Excel',
                'class' => $class !== '' ? $class : 'btn btn-success btn-lg me-2',
                'icon' => $icon,
                'query' => $grupo === 'IESS_SOAM' ? ['formato' => 'IESS_SOAM'] : ['formato' => 'IESS'],
            ];
        }

        return $buttons !== [] ? $buttons : $fallback;
    }

    private function ensureLegacyClassAutoloading(): void
    {
        if (self::$legacyAutoloaderRegistered) {
            return;
        }

        $baseDir = base_path('..');
        $prefixes = [
            'Modules\\' => $baseDir . '/modules/',
            'Core\\' => $baseDir . '/core/',
            'Models\\' => $baseDir . '/models/',
            'Helpers\\' => $baseDir . '/helpers/',
            'Services\\' => $baseDir . '/controllers/Services/',
        ];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $legacyBaseDir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }

                $relativeClass = substr($class, $len);
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                $paths = [
                    $legacyBaseDir . $relativePath,
                    $legacyBaseDir . strtolower($relativePath),
                ];

                $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
                $fileName = array_pop($segments) ?: '';
                $lowerDirPath = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $segments));
                if ($lowerDirPath !== '') {
                    $paths[] = rtrim($legacyBaseDir . $lowerDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                }

                foreach ($paths as $path) {
                    if (is_file($path)) {
                        require_once $path;
                        return;
                    }
                }
            }
        }, true, true);

        self::$legacyAutoloaderRegistered = true;
    }

    private function resolveSettingsModel(): ?SettingsModel
    {
        $this->ensureLegacyClassAutoloading();

        if ($this->settingsModel instanceof SettingsModel) {
            return $this->settingsModel;
        }

        try {
            $this->settingsModel = new SettingsModel(DB::connection()->getPdo());
        } catch (RuntimeException $exception) {
            Log::warning('No se pudo inicializar SettingsModel para Billing v2', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }

        return $this->settingsModel;
    }

    private function normalizeReferralDashboardCategory(mixed $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
        if ($normalized === '') {
            return '';
        }

        $upper = strtoupper($normalized);
        if (in_array($upper, ['(NO DEFINIDO)', 'NO DEFINIDO', 'N/A', 'NA', 'NULL', '-', '—'], true)) {
            return '';
        }

        if (in_array($upper, ['MARKETING', 'MEDIOS', 'MARKETING (PROMOCIONES)', 'MARKETING.'], true)) {
            return 'MARKETING';
        }

        return $upper;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $categories
     * @return array<int, array<string, mixed>>
     */
    private function filterRowsByReferralCategories(array $rows, array $categories): array
    {
        $normalizedCategories = [];
        foreach ($categories as $category) {
            $normalized = $this->normalizeReferralDashboardCategory($category);
            if ($normalized !== '') {
                $normalizedCategories[$normalized] = true;
            }
        }

        if ($normalizedCategories === []) {
            return [];
        }

        $filtered = [];
        foreach ($rows as $row) {
            $rowCategory = $this->normalizeReferralDashboardCategory($row['referido_prefactura_por'] ?? null);
            if ($rowCategory !== '' && isset($normalizedCategories[$rowCategory])) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $comparisonRows
     * @param array<int, array<string, mixed>> $selectedRows
     * @param array<string, mixed> $overallSummary
     * @param array<string, mixed> $selectedSummary
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildReferralStrategicDashboard(
        array $comparisonRows,
        array $selectedRows,
        array $overallSummary,
        array $selectedSummary,
        array $filters,
        string $selectedCategory
    ): array {
        $overallMetrics = $this->buildReferralStrategicMetrics($comparisonRows, $overallSummary, $overallSummary, 'CLINICA');
        $selectedMetrics = $this->buildReferralStrategicMetrics(
            $selectedRows,
            $selectedSummary,
            $overallSummary,
            $selectedCategory,
            $this->resolveReferralMarketingBudget($filters, $selectedCategory)
        );

        $comparisonDefinitions = $this->referralComparisonDefinitions($selectedCategory);
        $comparisonMetrics = [];
        foreach ($comparisonDefinitions as $definition) {
            $groupRows = $definition['key'] === $selectedCategory
                ? $selectedRows
                : $this->filterRowsByReferralCategories($comparisonRows, $definition['categories']);

            if ($groupRows === [] && $definition['key'] !== $selectedCategory) {
                continue;
            }

            $groupSummary = $definition['key'] === $selectedCategory
                ? $selectedSummary
                : $this->particularesReportService->resumen($groupRows, $filters);

            $comparisonMetrics[] = $this->buildReferralStrategicMetrics(
                $groupRows,
                $groupSummary,
                $overallSummary,
                $definition['label']
            );
        }

        $comparisonByLabel = [];
        foreach ($comparisonMetrics as $metric) {
            $comparisonByLabel[(string) ($metric['label'] ?? '')] = $metric;
        }

        $classification = $this->classifyReferralPerformance($selectedMetrics, $overallMetrics);
        $selectedMetrics['clasificacion'] = $classification['label'];
        $selectedMetrics['clasificacion_tone'] = $classification['tone'];
        $selectedMetrics['clasificacion_reason'] = $classification['reason'];

        $hallazgos = $this->buildReferralFindings($selectedMetrics, $overallMetrics, $comparisonByLabel);
        $opportunities = $this->buildReferralOpportunities($selectedMetrics, $overallMetrics, $comparisonByLabel);
        $automaticInsights = $this->buildReferralAutomaticInsights($selectedMetrics, $overallMetrics, $comparisonByLabel);
        $trend = $this->buildReferralMonthlyTrend($selectedRows);

        return [
            'selected' => $selectedMetrics,
            'overall' => $overallMetrics,
            'comparison' => $comparisonMetrics,
            'comparison_chart' => [
                'labels' => array_values(array_map(static fn(array $metric): string => (string) $metric['label'], $comparisonMetrics)),
                'atenciones' => array_values(array_map(static fn(array $metric): int => (int) $metric['atenciones'], $comparisonMetrics)),
                'usd' => array_values(array_map(static fn(array $metric): float => (float) $metric['usd_total'], $comparisonMetrics)),
                'ticket' => array_values(array_map(static fn(array $metric): float => (float) $metric['ticket_promedio'], $comparisonMetrics)),
                'cero' => array_values(array_map(static fn(array $metric): float => (float) $metric['tasa_cero'], $comparisonMetrics)),
            ],
            'trend' => $trend,
            'classification' => $classification,
            'hallazgos' => $hallazgos,
            'opportunities' => $opportunities,
            'automatic_insights' => $automaticInsights,
            'budget' => $selectedMetrics['budget'] ?? null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $overallSummary
     * @param array<string, mixed>|null $budget
     * @return array<string, mixed>
     */
    private function buildReferralStrategicMetrics(
        array $rows,
        array $summary,
        array $overallSummary,
        string $label,
        ?array $budget = null
    ): array {
        $economico = is_array($summary['economico'] ?? null) ? $summary['economico'] : [];
        $operativo = is_array($summary['operativo'] ?? null) ? $summary['operativo'] : [];
        $cirugias = is_array($summary['cirugias'] ?? null) ? $summary['cirugias'] : [];
        $pacientesFrecuencia = is_array($summary['pacientes_frecuencia'] ?? null) ? $summary['pacientes_frecuencia'] : [];
        $overallEconomico = is_array($overallSummary['economico'] ?? null) ? $overallSummary['economico'] : [];

        $atenciones = (int) ($summary['total'] ?? 0);
        $pacientesUnicos = (int) ($summary['pacientes_unicos'] ?? 0);
        $usdTotal = round((float) ($economico['total_honorario_real'] ?? $economico['total_produccion'] ?? 0), 2);
        $atencionesConHonorario = (int) ($economico['atenciones_con_honorario'] ?? 0);
        $atencionesFacturadas = (int) ($economico['atenciones_facturadas'] ?? 0);
        $ticketPromedio = $atenciones > 0 ? round($usdTotal / $atenciones, 2) : 0.0;
        $ltv = $pacientesUnicos > 0 ? round($usdTotal / $pacientesUnicos, 2) : 0.0;
        $tasaCero = $atenciones > 0 ? round((max($atenciones - $atencionesConHonorario, 0) / $atenciones) * 100, 2) : 0.0;
        $facturacionRate = (float) ($economico['facturacion_rate'] ?? 0);
        $realizacionRate = (float) ($operativo['realizacion_rate'] ?? 0);
        $conversionProcedimiento = $atenciones > 0 ? round(((int) ($summary['total_protocolos'] ?? 0) / $atenciones) * 100, 2) : 0.0;
        $conversionCirugia = $atenciones > 0 ? round(((int) ($cirugias['realizadas'] ?? 0) / $atenciones) * 100, 2) : 0.0;
        $nuevos = (int) ($pacientesFrecuencia['nuevos'] ?? 0);
        $recurrentes = (int) ($pacientesFrecuencia['recurrentes'] ?? 0);
        $tasaRetorno = $pacientesUnicos > 0 ? round(($recurrentes / $pacientesUnicos) * 100, 2) : 0.0;
        $eficiencia = $pacientesUnicos > 0 ? round($usdTotal / $pacientesUnicos, 2) : 0.0;

        $overallAtenciones = (int) ($overallSummary['total'] ?? 0);
        $overallUsd = round((float) ($overallEconomico['total_honorario_real'] ?? $overallEconomico['total_produccion'] ?? 0), 2);
        $overallTicket = $overallAtenciones > 0 ? round($overallUsd / $overallAtenciones, 2) : 0.0;
        $atencionesShare = $overallAtenciones > 0 ? round(($atenciones / $overallAtenciones) * 100, 2) : 0.0;
        $usdShare = $overallUsd > 0 ? round(($usdTotal / $overallUsd) * 100, 2) : 0.0;

        $patientValueStats = $this->buildReferralPatientValueStats($rows);
        $pacientesSinValor = (int) ($patientValueStats['without_value'] ?? 0);
        $pacientesSinValorRate = $pacientesUnicos > 0 ? round(($pacientesSinValor / $pacientesUnicos) * 100, 2) : 0.0;

        $metrics = [
            'label' => $label,
            'atenciones' => $atenciones,
            'atenciones_share' => $atencionesShare,
            'pacientes_unicos' => $pacientesUnicos,
            'pacientes_share' => (int) ($overallSummary['pacientes_unicos'] ?? 0) > 0
                ? round(($pacientesUnicos / max((int) ($overallSummary['pacientes_unicos'] ?? 0), 1)) * 100, 2)
                : 0.0,
            'usd_total' => $usdTotal,
            'usd_share' => $usdShare,
            'ticket_promedio' => $ticketPromedio,
            'ticket_vs_clinica' => $overallTicket > 0 ? round($ticketPromedio / $overallTicket, 2) : 0.0,
            'tasa_cero' => $tasaCero,
            'atenciones_cero' => max($atenciones - $atencionesConHonorario, 0),
            'facturacion_rate' => $facturacionRate,
            'realizacion_rate' => $realizacionRate,
            'conversion_procedimiento' => $conversionProcedimiento,
            'conversion_cirugia' => $conversionCirugia,
            'atenciones_facturadas' => $atencionesFacturadas,
            'nuevos' => $nuevos,
            'recurrentes' => $recurrentes,
            'tasa_retorno' => $tasaRetorno,
            'ltv' => $ltv,
            'eficiencia' => $eficiencia,
            'pacientes_sin_valor' => $pacientesSinValor,
            'pacientes_sin_valor_rate' => $pacientesSinValorRate,
            'overall_ticket_promedio' => $overallTicket,
        ];

        if (is_array($budget) && (float) ($budget['period_budget'] ?? 0) > 0) {
            $periodBudget = round((float) ($budget['period_budget'] ?? 0), 2);
            $roi = $periodBudget > 0 ? round((($usdTotal - $periodBudget) / $periodBudget) * 100, 2) : null;
            $metrics['budget'] = [
                'annual_budget' => round((float) ($budget['annual_budget'] ?? 0), 2),
                'period_budget' => $periodBudget,
                'days' => (int) ($budget['days'] ?? 0),
                'scope' => (string) ($budget['scope'] ?? ''),
                'roi_pct' => $roi,
            ];
        }

        return $metrics;
    }

    /**
     * @param array<string, mixed> $selected
     * @param array<string, mixed> $overall
     * @return array{label:string,tone:string,reason:string}
     */
    private function classifyReferralPerformance(array $selected, array $overall): array
    {
        $overallTicket = (float) ($overall['ticket_promedio'] ?? 0);
        $selectedTicket = (float) ($selected['ticket_promedio'] ?? 0);
        $tasaCero = (float) ($selected['tasa_cero'] ?? 0);
        $facturacionRate = (float) ($selected['facturacion_rate'] ?? 0);
        $atencionesShare = (float) ($selected['atenciones_share'] ?? 0);

        if ($tasaCero >= 35 || ($facturacionRate < 35 && $selectedTicket < ($overallTicket * 0.85))) {
            return [
                'label' => 'INEFICIENTE',
                'tone' => 'danger',
                'reason' => 'La categoría concentra demasiadas atenciones sin valor o una conversión económica insuficiente.',
            ];
        }

        if ($atencionesShare >= 20 && $selectedTicket < ($overallTicket * 0.9)) {
            return [
                'label' => 'VOLUMEN CON BAJO VALOR',
                'tone' => 'warning',
                'reason' => 'Aporta volumen operativo, pero el ticket promedio está por debajo del promedio de la clínica.',
            ];
        }

        if ($selectedTicket > ($overallTicket * 1.1) && $atencionesShare < 10) {
            return [
                'label' => 'POTENCIAL ALTO NO APROVECHADO',
                'tone' => 'info',
                'reason' => 'La categoría trae pacientes de alto valor, pero todavía con escala limitada.',
            ];
        }

        if ($selectedTicket >= $overallTicket && $tasaCero <= 20 && $facturacionRate >= 50) {
            return [
                'label' => 'ALTO RENDIMIENTO',
                'tone' => 'success',
                'reason' => 'Combina ticket competitivo, baja fricción operativa y buena captura de ingresos.',
            ];
        }

        return [
            'label' => 'DESEMPEÑO MIXTO',
            'tone' => 'secondary',
            'reason' => 'La categoría tiene señales mixtas entre volumen, ticket y eficiencia operativa.',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{total:int,with_value:int,without_value:int}
     */
    private function buildReferralPatientValueStats(array $rows): array
    {
        $patientAmounts = [];
        foreach ($rows as $row) {
            $patientKey = trim((string) ($row['hc_number'] ?? ''));
            if ($patientKey === '') {
                continue;
            }

            $amount = (float) ($row['monto_honorario_real'] ?? 0);
            if ($amount <= 0) {
                $amount = (float) ($row['total_produccion'] ?? 0);
            }

            if (!isset($patientAmounts[$patientKey])) {
                $patientAmounts[$patientKey] = 0.0;
            }
            $patientAmounts[$patientKey] += $amount;
        }

        $withValue = 0;
        $withoutValue = 0;
        foreach ($patientAmounts as $amount) {
            if ((float) $amount > 0) {
                $withValue++;
            } else {
                $withoutValue++;
            }
        }

        return [
            'total' => count($patientAmounts),
            'with_value' => $withValue,
            'without_value' => $withoutValue,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{annual_budget:float,period_budget:float,days:int,scope:string}|null
     */
    private function resolveReferralMarketingBudget(array $filters, string $selectedCategory): ?array
    {
        if ($selectedCategory !== 'MARKETING') {
            return null;
        }

        $annualBudget = strtoupper(trim((string) ($filters['sede'] ?? ''))) !== '' ? 27400.0 : 54800.0;
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $from = DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom) ?: new DateTimeImmutable('today');
        $to = DateTimeImmutable::createFromFormat('Y-m-d', $dateTo) ?: $from;
        if ($to < $from) {
            $to = $from;
        }

        $days = (int) $from->diff($to)->format('%a') + 1;
        $periodBudget = round($annualBudget * ($days / 365), 2);

        return [
            'annual_budget' => $annualBudget,
            'period_budget' => $periodBudget,
            'days' => $days,
            'scope' => strtoupper(trim((string) ($filters['sede'] ?? ''))) !== '' ? 'UNA SEDE' : 'DOS SEDES',
        ];
    }

    /**
     * @return array<int, array{key:string,label:string,categories:array<int,string>}>
     */
    private function referralComparisonDefinitions(string $selectedCategory): array
    {
        $definitions = [
            ['key' => $selectedCategory, 'label' => $selectedCategory, 'categories' => [$selectedCategory]],
            ['key' => 'MARKETING', 'label' => 'MARKETING', 'categories' => ['MARKETING']],
            ['key' => 'REFERIDOS CLIENTES', 'label' => 'REFERIDOS CLIENTES', 'categories' => ['REFERIDOS CLIENTES']],
            ['key' => 'DOCTORES', 'label' => 'DOCTORES', 'categories' => ['DOCTORES INTERNOS', 'DOCTORES EXTERNOS']],
        ];

        $unique = [];
        $result = [];
        foreach ($definitions as $definition) {
            if (isset($unique[$definition['key']])) {
                continue;
            }
            $unique[$definition['key']] = true;
            $result[] = $definition;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $selected
     * @param array<string, mixed> $overall
     * @param array<string, array<string, mixed>> $comparisonByLabel
     * @return array<int, string>
     */
    private function buildReferralFindings(array $selected, array $overall, array $comparisonByLabel): array
    {
        $messages = [];
        $label = (string) ($selected['label'] ?? 'La categoría');
        $overallTicket = (float) ($overall['ticket_promedio'] ?? 0);
        $selectedTicket = (float) ($selected['ticket_promedio'] ?? 0);

        $messages[] = sprintf(
            '%s aporta %s%% de las atenciones y %s%% del ingreso real del período.',
            $label,
            number_format((float) ($selected['atenciones_share'] ?? 0), 2),
            number_format((float) ($selected['usd_share'] ?? 0), 2)
        );

        if ($overallTicket > 0) {
            $messages[] = sprintf(
                'Su ticket promedio es $%s, frente a $%s del promedio de la clínica.',
                number_format($selectedTicket, 2),
                number_format($overallTicket, 2)
            );
        }

        if (isset($comparisonByLabel['REFERIDOS CLIENTES'])) {
            $referidos = $comparisonByLabel['REFERIDOS CLIENTES'];
            $messages[] = sprintf(
                'Comparado con Referidos Clientes, el ticket es $%s vs $%s y la tasa 0 es %s%% vs %s%%.',
                number_format((float) ($selected['ticket_promedio'] ?? 0), 2),
                number_format((float) ($referidos['ticket_promedio'] ?? 0), 2),
                number_format((float) ($selected['tasa_cero'] ?? 0), 2),
                number_format((float) ($referidos['tasa_cero'] ?? 0), 2)
            );
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $selected
     * @param array<string, mixed> $overall
     * @param array<string, array<string, mixed>> $comparisonByLabel
     * @return array<int, string>
     */
    private function buildReferralOpportunities(array $selected, array $overall, array $comparisonByLabel): array
    {
        $messages = [];
        $label = (string) ($selected['label'] ?? 'La categoría');
        $overallTicket = (float) ($overall['ticket_promedio'] ?? 0);

        if ((float) ($selected['tasa_cero'] ?? 0) >= 20) {
            $messages[] = sprintf(
                'Reducir la tasa de atenciones en 0$ de %s%% debería ser la prioridad operativa inmediata.',
                number_format((float) ($selected['tasa_cero'] ?? 0), 2)
            );
        }

        if ((float) ($selected['ticket_promedio'] ?? 0) < ($overallTicket * 0.9) && (float) ($selected['atenciones_share'] ?? 0) >= 15) {
            $messages[] = sprintf(
                '%s necesita elevar calidad de captación: hoy mueve volumen, pero con ticket por debajo de la clínica.',
                $label
            );
        }

        if ((float) ($selected['ticket_promedio'] ?? 0) > ($overallTicket * 1.1) && (float) ($selected['atenciones_share'] ?? 0) < 10) {
            $messages[] = sprintf(
                '%s muestra valor por paciente superior al promedio; conviene escalar inversión o exposición sin deteriorar ticket.',
                $label
            );
        }

        if (isset($comparisonByLabel['DOCTORES']) && (float) ($selected['ticket_promedio'] ?? 0) < (float) ($comparisonByLabel['DOCTORES']['ticket_promedio'] ?? 0)) {
            $messages[] = 'Existe una brecha frente al canal DOCTORES; revisar segmentación, seguimiento y cierres comerciales.';
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $selected
     * @param array<string, mixed> $overall
     * @param array<string, array<string, mixed>> $comparisonByLabel
     * @return array<int, string>
     */
    private function buildReferralAutomaticInsights(array $selected, array $overall, array $comparisonByLabel): array
    {
        $insights = [];
        $label = (string) ($selected['label'] ?? 'La categoría');
        $overallTicket = (float) ($overall['ticket_promedio'] ?? 0);

        if ((float) ($selected['atenciones_share'] ?? 0) > (float) ($selected['usd_share'] ?? 0)) {
            $insights[] = sprintf('%s genera más volumen que valor económico relativo.', $label);
        } else {
            $insights[] = sprintf('%s convierte mejor valor económico que su peso en volumen.', $label);
        }

        if ((float) ($selected['tasa_cero'] ?? 0) >= 25) {
            $insights[] = sprintf('El %s%% de las atenciones de %s no generan ingreso real.', number_format((float) ($selected['tasa_cero'] ?? 0), 2), $label);
        }

        if ($overallTicket > 0) {
            $ratio = (float) ($selected['ticket_vs_clinica'] ?? 0);
            if ($ratio >= 1.1) {
                $insights[] = sprintf('%s trae un ticket promedio %s%% por encima del promedio de la clínica.', $label, number_format(($ratio - 1) * 100, 2));
            } elseif ($ratio <= 0.9) {
                $insights[] = sprintf('%s trae un ticket promedio %s%% por debajo del promedio de la clínica.', $label, number_format((1 - $ratio) * 100, 2));
            }
        }

        if (isset($selected['budget']['roi_pct'])) {
            $roi = (float) ($selected['budget']['roi_pct'] ?? 0);
            $insights[] = sprintf('Con el presupuesto prorrateado del período, el ROI observado es %s%%.', number_format($roi, 2));
        }

        if (isset($comparisonByLabel['REFERIDOS CLIENTES'])) {
            $referidos = $comparisonByLabel['REFERIDOS CLIENTES'];
            if ((float) ($selected['ticket_promedio'] ?? 0) < (float) ($referidos['ticket_promedio'] ?? 0)) {
                $insights[] = 'Referidos Clientes sigue siendo una referencia de mayor valor por paciente.';
            }
        }

        return $insights;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{labels:array<int,string>,counts:array<int,int>,usd:array<int,float>}
     */
    private function buildReferralMonthlyTrend(array $rows): array
    {
        $months = [];
        foreach ($rows as $row) {
            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            if ($timestamp === false) {
                continue;
            }

            $monthKey = date('Y-m', $timestamp);
            if (!isset($months[$monthKey])) {
                $months[$monthKey] = [
                    'count' => 0,
                    'usd' => 0.0,
                ];
            }

            $months[$monthKey]['count']++;
            $amount = (float) ($row['monto_honorario_real'] ?? 0);
            if ($amount <= 0) {
                $amount = (float) ($row['total_produccion'] ?? 0);
            }
            $months[$monthKey]['usd'] += $amount;
        }

        ksort($months);

        $labels = [];
        $counts = [];
        $usd = [];
        foreach ($months as $monthKey => $values) {
            $labels[] = $this->referralMonthLabel($monthKey);
            $counts[] = (int) ($values['count'] ?? 0);
            $usd[] = round((float) ($values['usd'] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'usd' => $usd,
        ];
    }

    private function referralMonthLabel(string $monthKey): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $monthKey . '-01');
        if (!$date instanceof DateTimeImmutable) {
            return $monthKey;
        }

        $monthNames = [
            1 => 'ENE',
            2 => 'FEB',
            3 => 'MAR',
            4 => 'ABR',
            5 => 'MAY',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AGO',
            9 => 'SEP',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DIC',
        ];

        return ($monthNames[(int) $date->format('n')] ?? strtoupper($date->format('M'))) . ' ' . $date->format('y');
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
        if (str_contains($value, 'matriz') || str_contains($value, 'villa')) {
            return 'MATRIZ';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $filters
     */
    /**
     * @return array{
     *   generatedAt:string,
     *   totalAtenciones:int,
     *   filterSummary:array<int,array{label:string,value:string}>,
     *   hallazgosClave:array<int,string>,
     *   methodology:array<int,string>,
     *   generalKpis:array<int,array<string,string>>,
     *   temporalKpis:array<int,array<string,string>>,
     *   economicKpis:array<int,array<string,string>>,
     *   tables:array<int,array<string,mixed>>
     * }
     */
    private function buildInformeParticularesKpiExportPayload(array $summary, array $filters): array
    {
        $economico = is_array($summary['economico'] ?? null) ? $summary['economico'] : [];
        $operativo = is_array($summary['operativo'] ?? null) ? $summary['operativo'] : [];
        $temporal = is_array($summary['temporal'] ?? null) ? $summary['temporal'] : [];
        $procedimientosVolumen = is_array($summary['procedimientos_volumen'] ?? null) ? $summary['procedimientos_volumen'] : [];
        $desgloseGerencial = is_array($summary['desglose_gerencial'] ?? null) ? $summary['desglose_gerencial'] : [];
        $picos = is_array($summary['picos'] ?? null) ? $summary['picos'] : [];
        $pacientesFrecuencia = is_array($summary['pacientes_frecuencia'] ?? null) ? $summary['pacientes_frecuencia'] : [];
        $categoriaCounts = is_array($summary['categoria_counts'] ?? null) ? $summary['categoria_counts'] : [];
        $categoriaShare = is_array($summary['categoria_share'] ?? null) ? $summary['categoria_share'] : [];
        $insuranceBreakdown = is_array($summary['insurance_breakdown'] ?? null) ? $summary['insurance_breakdown'] : [];
        $topAfiliaciones = is_array($summary['top_afiliaciones'] ?? null) ? $summary['top_afiliaciones'] : [];
        $referidoSummary = is_array($summary['referido_prefactura'] ?? null) ? $summary['referido_prefactura'] : [];
        $referidoPacientesUnicosSummary = is_array($summary['referido_prefactura_pacientes_unicos'] ?? null) ? $summary['referido_prefactura_pacientes_unicos'] : [];
        $referidoNuevoPacienteSummary = is_array($summary['referido_prefactura_consulta_nuevo_paciente'] ?? null) ? $summary['referido_prefactura_consulta_nuevo_paciente'] : [];
        $hierarquiaReferidos = is_array($summary['hierarquia_referidos'] ?? null) ? $summary['hierarquia_referidos'] : [];
        $totalAtenciones = (int) ($summary['total'] ?? 0);
        $pacientesUnicos = (int) ($summary['pacientes_unicos'] ?? 0);
        $honorarioRealTotal = (float) ($economico['total_honorario_real'] ?? $economico['total_produccion'] ?? 0);
        $operativoEvaluadas = (int) ($operativo['evaluadas'] ?? $totalAtenciones);
        $operativoRealizadas = (int) ($operativo['realizadas'] ?? 0);
        $operativoFacturadas = (int) ($operativo['facturadas'] ?? 0);
        $operativoPendientesFacturar = (int) ($operativo['pendientes_facturar'] ?? 0);
        $operativoPerdidas = (int) ($operativo['perdidas'] ?? 0);
        $operativoRealizacionRate = (float) ($operativo['realizacion_rate'] ?? 0);
        $operativoFacturacionRate = (float) ($operativo['facturacion_sobre_realizadas_rate'] ?? 0);
        $operativoPendienteRate = (float) ($operativo['pendiente_sobre_realizadas_rate'] ?? 0);
        $operativoPerdidaRate = (float) ($operativo['perdida_rate'] ?? 0);
        $operativoPorCobrarEstimado = (float) ($operativo['por_cobrar_estimado'] ?? 0);
        $operativoPerdidaEstimada = (float) ($operativo['perdida_estimada'] ?? 0);
        $operativoPotencialCapturable = (float) ($operativo['potencial_capturable'] ?? ($honorarioRealTotal + $operativoPorCobrarEstimado));
        $operativoTicketFacturadoReal = (float) ($operativo['ticket_facturado_real'] ?? 0);
        $operativoTicketPendiente = (float) ($operativo['ticket_pendiente'] ?? 0);
        $honorarioPorCategoria = is_array($economico['honorario_por_categoria'] ?? null)
            ? $economico['honorario_por_categoria']
            : (is_array($economico['produccion_por_categoria'] ?? null) ? $economico['produccion_por_categoria'] : []);
        $honorarioParticular = (float) ($honorarioPorCategoria['particular'] ?? 0);
        $honorarioPrivado = (float) ($honorarioPorCategoria['privado'] ?? 0);
        $formasPagoValues = is_array(($economico['formas_pago']['values'] ?? null)) ? $economico['formas_pago']['values'] : [];
        $areasTop = is_array($economico['areas_top'] ?? null) ? $economico['areas_top'] : [];
        $doctorPerformanceRows = is_array($economico['doctores_rendimiento_top'] ?? null) ? $economico['doctores_rendimiento_top'] : [];

        $particularCount = (int) ($categoriaCounts['particular'] ?? 0);
        $privadoCount = (int) ($categoriaCounts['privado'] ?? 0);
        $particularShare = (float) ($categoriaShare['particular'] ?? 0);
        $privadoShare = (float) ($categoriaShare['privado'] ?? 0);
        $ticketPromedioParticular = $particularCount > 0 ? round($honorarioParticular / $particularCount, 2) : 0.0;
        $ticketPromedioPrivado = $privadoCount > 0 ? round($honorarioPrivado / $privadoCount, 2) : 0.0;
        $ticketPromedioCategoriaTotal = $totalAtenciones > 0 ? round($honorarioRealTotal / $totalAtenciones, 2) : 0.0;

        $currentMonthLabel = (string) ($temporal['current_month_label'] ?? 'N/D');
        $currentMonthCount = (int) ($temporal['current_month_count'] ?? 0);
        $previousMonthLabel = (string) ($temporal['previous_month_label'] ?? 'N/D');
        $previousMonthCount = (int) ($temporal['previous_month_count'] ?? 0);
        $sameMonthLastYearLabel = (string) ($temporal['same_month_last_year_label'] ?? 'N/D');
        $sameMonthLastYearCount = (int) ($temporal['same_month_last_year_count'] ?? 0);
        $vsPreviousPct = is_numeric($temporal['vs_previous_pct'] ?? null) ? (float) $temporal['vs_previous_pct'] : null;
        $vsLastYearPct = is_numeric($temporal['vs_same_month_last_year_pct'] ?? null) ? (float) $temporal['vs_same_month_last_year_pct'] : null;
        $temporalTrend = is_array($temporal['trend'] ?? null) ? $temporal['trend'] : [];
        $temporalTrendLabels = is_array($temporalTrend['labels'] ?? null) ? $temporalTrend['labels'] : [];
        $temporalTrendCounts = is_array($temporalTrend['counts'] ?? null) ? $temporalTrend['counts'] : [];

        $pacientesNuevos = (int) ($pacientesFrecuencia['nuevos'] ?? 0);
        $pacientesRecurrentes = (int) ($pacientesFrecuencia['recurrentes'] ?? 0);
        $pacientesNuevosPct = (float) ($pacientesFrecuencia['nuevos_pct'] ?? 0);
        $pacientesRecurrentesPct = (float) ($pacientesFrecuencia['recurrentes_pct'] ?? 0);

        $topProcedimientosVolumen = is_array($procedimientosVolumen['top_10'] ?? null) ? $procedimientosVolumen['top_10'] : [];
        $desgloseSedes = is_array($desgloseGerencial['sedes'] ?? null) ? $desgloseGerencial['sedes'] : [];
        $picosDias = is_array($picos['dias'] ?? null) ? $picos['dias'] : [];
        $peakDay = is_array($picos['peak_day'] ?? null) ? $picos['peak_day'] : ['valor' => 'N/D', 'cantidad' => 0];

        $referidoValues = is_array($referidoSummary['values'] ?? null) ? $referidoSummary['values'] : [];
        $referidoWithValue = (int) ($referidoSummary['with_value'] ?? 0);
        $referidoWithoutValue = (int) ($referidoSummary['without_value'] ?? 0);
        $referidoPacientesUnicosValues = is_array($referidoPacientesUnicosSummary['values'] ?? null) ? $referidoPacientesUnicosSummary['values'] : [];
        $referidoPacientesUnicosWithValue = (int) ($referidoPacientesUnicosSummary['with_value'] ?? 0);
        $referidoPacientesUnicosWithoutValue = (int) ($referidoPacientesUnicosSummary['without_value'] ?? 0);
        $referidoNuevoPacienteValues = is_array($referidoNuevoPacienteSummary['values'] ?? null) ? $referidoNuevoPacienteSummary['values'] : [];
        $referidoNuevoPacienteWithValue = (int) ($referidoNuevoPacienteSummary['with_value'] ?? 0);
        $referidoNuevoPacienteWithoutValue = (int) ($referidoNuevoPacienteSummary['without_value'] ?? 0);
        $hierarquiaPares = is_array($hierarquiaReferidos['pares'] ?? null) ? $hierarquiaReferidos['pares'] : [];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $empresaSeguroFilter = trim((string) ($filters['empresa_seguro'] ?? ''));
        $empresaSeguroOptions = $this->insuranceDimensionOptions('empresa');
        $empresaSeguroLabel = '';
        foreach ($empresaSeguroOptions as $option) {
            if (!is_array($option)) {
                continue;
            }
            $optionValue = trim((string) ($option['value'] ?? ''));
            if ($optionValue !== '' && $optionValue === $empresaSeguroFilter) {
                $empresaSeguroLabel = trim((string) ($option['label'] ?? ''));
                break;
            }
        }
        if ($empresaSeguroLabel === '' && $empresaSeguroFilter !== '') {
            $empresaSeguroLabel = strtoupper(str_replace('_', ' ', $empresaSeguroFilter));
        }

        $insuranceBreakdownTitle = trim((string) ($insuranceBreakdown['title'] ?? 'Empresas de seguro'));
        $insuranceBreakdownItemLabel = trim((string) ($insuranceBreakdown['item_label'] ?? 'Empresa de seguro'));

        $dateRangeValue = 'Últimos 30 días';
        if ($dateFrom !== '' || $dateTo !== '') {
            $dateRangeValue = ($dateFrom !== '' ? $dateFrom : '...') . ' a ' . ($dateTo !== '' ? $dateTo : '...');
        }

        $filterSummary = [
            ['label' => 'Rango de fechas', 'value' => $dateRangeValue],
            ['label' => 'Sede', 'value' => strtoupper(trim((string) ($filters['sede'] ?? ''))) ?: 'Todas'],
            ['label' => 'Empresa de seguro', 'value' => $empresaSeguroLabel !== '' ? strtoupper($empresaSeguroLabel) : 'Todas'],
            ['label' => 'Seguro / plan', 'value' => strtoupper(trim((string) ($filters['afiliacion'] ?? ''))) ?: 'Todos'],
            ['label' => 'Categoría cliente', 'value' => ucfirst(strtolower(trim((string) ($filters['categoria_cliente'] ?? '')))) ?: 'Todas'],
            ['label' => 'Categoría madre referido', 'value' => strtoupper(trim((string) ($filters['categoria_madre_referido'] ?? ''))) ?: 'Todas'],
            ['label' => 'Tipo de atención', 'value' => strtoupper(trim((string) ($filters['tipo'] ?? ''))) ?: 'Todos'],
            ['label' => 'Procedimiento', 'value' => trim((string) ($filters['procedimiento'] ?? '')) ?: 'Todos'],
        ];

        $formatCurrency = static fn(float $amount): string => '$' . number_format($amount, 2);
        $formatPercent = static fn(float $value): string => number_format($value, 2) . '%';
        $formatCount = static fn(int $value): string => number_format($value);
        $normalizeLabel = static function (mixed $value, string $default = 'SIN DATO'): string {
            $text = trim((string) $value);
            return $text !== '' ? strtoupper($text) : $default;
        };
        $buildMetricRows = static function (array $items, int $limit = 8) use ($formatCount, $formatPercent, $normalizeLabel): array {
            $rows = [];
            foreach (array_slice($items, 0, $limit) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $rows[] = [
                    $normalizeLabel($item['valor'] ?? ''),
                    $formatCount((int) ($item['cantidad'] ?? 0)),
                    $formatPercent((float) ($item['porcentaje'] ?? 0)),
                ];
            }
            return $rows;
        };
        $buildMoneyRows = static function (array $items, int $limit = 8) use ($formatCurrency, $formatPercent, $normalizeLabel): array {
            $rows = [];
            foreach (array_slice($items, 0, $limit) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $rows[] = [
                    $normalizeLabel($item['valor'] ?? ''),
                    $formatCurrency((float) ($item['monto'] ?? 0)),
                    $formatPercent((float) ($item['porcentaje'] ?? 0)),
                ];
            }
            return $rows;
        };
        $buildTrendCountRows = static function (array $labels, array $values) use ($formatCount): array {
            $rows = [];
            $count = min(count($labels), count($values));
            for ($index = 0; $index < $count; $index++) {
                $rows[] = [
                    trim((string) $labels[$index]) !== '' ? (string) $labels[$index] : 'N/D',
                    $formatCount((int) ($values[$index] ?? 0)),
                ];
            }
            return $rows;
        };
        $buildReferidoRows = static function (array $items, int $limit = 12) use ($formatCount, $formatCurrency, $formatPercent, $normalizeLabel): array {
            $rows = [];
            foreach (array_slice($items, 0, $limit) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $rows[] = [
                    $normalizeLabel($item['valor'] ?? ''),
                    $formatCount((int) ($item['cantidad'] ?? 0)),
                    $formatPercent((float) ($item['porcentaje'] ?? 0)),
                    $formatCurrency((float) ($item['monto'] ?? 0)),
                    $formatCurrency((float) ($item['ticket_promedio'] ?? 0)),
                ];
            }
            return $rows;
        };
        $buildDoctorPerformanceRows = static function (array $items, int $limit = 10) use ($formatCount, $formatCurrency, $formatPercent): array {
            $rows = [];
            foreach (array_slice($items, 0, $limit) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $rows[] = [
                    trim((string) ($item['valor'] ?? 'SIN DOCTOR')) ?: 'SIN DOCTOR',
                    $formatCount((int) ($item['cantidad_total'] ?? 0)),
                    $formatCount((int) ($item['cantidad_con_honorario'] ?? 0)),
                    $formatPercent(((float) ($item['tasa_cero'] ?? 0)) * 100),
                    $formatCurrency((float) ($item['monto'] ?? 0)),
                    $formatCurrency((float) ($item['ticket_promedio'] ?? 0)),
                    number_format((float) ($item['score_rendimiento'] ?? 0), 2),
                    trim((string) ($item['clasificacion'] ?? 'POR REVISAR')) ?: 'POR REVISAR',
                ];
            }
            return $rows;
        };

        $topProcedimientoLider = is_array($topProcedimientosVolumen[0] ?? null) ? $topProcedimientosVolumen[0] : [];
        $topProcedimientoLabel = $normalizeLabel($topProcedimientoLider['valor'] ?? '', '');
        $topProcedimientoCount = (int) ($topProcedimientoLider['cantidad'] ?? 0);
        $topProcedimientoPct = (float) ($topProcedimientoLider['porcentaje'] ?? 0);

        $hallazgosClave = [];
        if ($operativoEvaluadas > 0) {
            $hallazgosClave[] = sprintf(
                'Se realizaron %d de %d atenciones evaluadas (%s).',
                $operativoRealizadas,
                $operativoEvaluadas,
                $formatPercent($operativoRealizacionRate)
            );
            $hallazgosClave[] = sprintf(
                'Se facturaron %d de las realizadas (%s) y %d quedaron pendientes de cobro (%s).',
                $operativoFacturadas,
                $formatPercent($operativoFacturacionRate),
                $operativoPendientesFacturar,
                $formatPercent($operativoPendienteRate)
            );
            $hallazgosClave[] = sprintf(
                'La pérdida operativa fue de %d casos (%s) con una pérdida estimada de %s.',
                $operativoPerdidas,
                $formatPercent($operativoPerdidaRate),
                $formatCurrency($operativoPerdidaEstimada)
            );
        }
        if ($topProcedimientoLabel !== '' && $topProcedimientoCount > 0) {
            $hallazgosClave[] = sprintf(
                'El procedimiento más frecuente fue %s con %d atenciones (%s).',
                $topProcedimientoLabel,
                $topProcedimientoCount,
                $formatPercent($topProcedimientoPct)
            );
        }
        if ((int) ($peakDay['cantidad'] ?? 0) > 0) {
            $hallazgosClave[] = sprintf(
                'El pico operativo por día fue %s con %d atenciones.',
                $normalizeLabel($peakDay['valor'] ?? 'N/D', 'N/D'),
                (int) ($peakDay['cantidad'] ?? 0)
            );
        }
        $hallazgosClave = array_slice($hallazgosClave, 0, 4);

        $generalKpis = [
            ['label' => 'Atenciones evaluadas', 'value' => $formatCount($operativoEvaluadas), 'note' => 'Universo auditado con la lógica real por categoría de servicio.'],
            ['label' => 'Realizadas', 'value' => $formatCount($operativoRealizadas) . ' (' . $formatPercent($operativoRealizacionRate) . ')', 'note' => 'Atenciones con evidencia real de servicio realizado.'],
            ['label' => 'Facturadas', 'value' => $formatCount($operativoFacturadas) . ' (' . $formatPercent($operativoFacturacionRate) . ')', 'note' => 'Atenciones realizadas que ya tienen billing real asociado.'],
            ['label' => 'Pendientes de facturar', 'value' => $formatCount($operativoPendientesFacturar) . ' (' . $formatPercent($operativoPendienteRate) . ')', 'note' => 'Atenciones realizadas aún sin billing real.'],
            ['label' => 'Pérdida operativa', 'value' => $formatCount($operativoPerdidas) . ' (' . $formatPercent($operativoPerdidaRate) . ')', 'note' => 'Atenciones no realizadas o perdidas según la lógica real.'],
            ['label' => 'Pacientes únicos', 'value' => $formatCount($pacientesUnicos), 'note' => 'Pacientes distintos incluidos en el rango filtrado.'],
        ];

        $temporalKpis = [
            ['label' => 'Volumen mes actual', 'value' => $formatCount($currentMonthCount), 'note' => $currentMonthLabel],
            ['label' => 'Vs mes anterior', 'value' => $vsPreviousPct === null ? 'N/D' : (($vsPreviousPct >= 0 ? '↑ ' : '↓ ') . $formatPercent(abs($vsPreviousPct))), 'note' => $formatCount($currentMonthCount) . ' vs ' . $formatCount($previousMonthCount) . ' (' . $previousMonthLabel . ')'],
            ['label' => 'Vs mismo mes año pasado', 'value' => $vsLastYearPct === null ? 'N/D' : (($vsLastYearPct >= 0 ? '↑ ' : '↓ ') . $formatPercent(abs($vsLastYearPct))), 'note' => $formatCount($currentMonthCount) . ' vs ' . $formatCount($sameMonthLastYearCount) . ' (' . $sameMonthLastYearLabel . ')'],
            ['label' => 'Nuevos vs recurrentes', 'value' => $formatCount($pacientesNuevos) . ' / ' . $formatCount($pacientesRecurrentes), 'note' => 'Nuevos ' . $formatPercent($pacientesNuevosPct) . ' | Recurrentes ' . $formatPercent($pacientesRecurrentesPct)],
            ['label' => 'Pico operativo por día', 'value' => $normalizeLabel($peakDay['valor'] ?? 'N/D', 'N/D') . ' (' . $formatCount((int) ($peakDay['cantidad'] ?? 0)) . ')', 'note' => 'Día con mayor volumen de atención en el rango filtrado.'],
        ];

        $economicKpis = [
            ['label' => 'Honorario real acumulado', 'value' => $formatCurrency($honorarioRealTotal), 'meaning' => 'Suma del valor económico real único por procedimiento desde billing_facturacion_real.', 'formula' => 'SUM(billing_facturacion_real.monto_honorario) sobre las atenciones filtradas.'],
            ['label' => 'Por cobrar estimado', 'value' => $formatCurrency($operativoPorCobrarEstimado), 'meaning' => 'Monto estimado pendiente de cobrar en atenciones realizadas sin billing real.', 'formula' => 'SUM(monto_por_cobrar_estimado) para PENDIENTE_FACTURAR.'],
            ['label' => 'Pérdida estimada', 'value' => $formatCurrency($operativoPerdidaEstimada), 'meaning' => 'Monto estimado de producción perdida por cancelación, ausentismo o pérdida operativa.', 'formula' => 'SUM(monto_perdida_estimada) según la lógica real por categoría.'],
            ['label' => 'Potencial capturable', 'value' => $formatCurrency($operativoPotencialCapturable), 'meaning' => 'Honorario real ya capturado más el valor estimado todavía pendiente de cobro.', 'formula' => 'Honorario real acumulado + por cobrar estimado.'],
            ['label' => 'Cobro sobre realizadas', 'value' => $formatPercent($operativoFacturacionRate), 'meaning' => 'Cobertura de facturación real sobre las atenciones efectivamente realizadas.', 'formula' => 'Atenciones facturadas / Atenciones realizadas.'],
            ['label' => 'Ticket facturado real', 'value' => $formatCurrency($operativoTicketFacturadoReal), 'meaning' => 'Honorario real promedio por atención con billing real.', 'formula' => 'Honorario real acumulado / Atenciones facturadas.'],
            ['label' => 'Ticket pendiente', 'value' => $formatCurrency($operativoTicketPendiente), 'meaning' => 'Valor estimado promedio por cada atención pendiente de facturar.', 'formula' => 'Por cobrar estimado / Atenciones pendientes de facturar.'],
            ['label' => 'Ticket Particular', 'value' => $formatCurrency($ticketPromedioParticular), 'meaning' => 'Honorario real promedio por atención de categoría Particular.', 'formula' => 'Honorario particular / Atenciones particulares.'],
            ['label' => 'Ticket Privado', 'value' => $formatCurrency($ticketPromedioPrivado), 'meaning' => 'Honorario real promedio por atención de categoría Privado.', 'formula' => 'Honorario privado / Atenciones privadas.'],
        ];

        $tables = [
            [
                'title' => 'Categoría cliente: volumen + honorario',
                'columns' => ['Categoría', 'Atenciones', '% del total', 'Honorario real', 'Ticket prom.'],
                'rows' => [
                    ['PARTICULAR', $formatCount($particularCount), $formatPercent($particularShare), $formatCurrency($honorarioParticular), $formatCurrency($ticketPromedioParticular)],
                    ['PRIVADO', $formatCount($privadoCount), $formatPercent($privadoShare), $formatCurrency($honorarioPrivado), $formatCurrency($ticketPromedioPrivado)],
                    ['TOTAL', $formatCount($totalAtenciones), $formatPercent(100), $formatCurrency($honorarioRealTotal), $formatCurrency($ticketPromedioCategoriaTotal)],
                ],
                'empty_message' => 'Sin datos de categoría para el rango seleccionado.',
            ],
            [
                'title' => 'Tendencia mensual de volumen',
                'columns' => ['Mes', 'Atenciones'],
                'rows' => $buildTrendCountRows($temporalTrendLabels, $temporalTrendCounts),
                'empty_message' => 'Sin tendencia de volumen disponible.',
            ],
            [
                'title' => 'Top procedimientos por volumen',
                'columns' => ['Procedimiento', 'Atenciones', '% del total'],
                'rows' => $buildMetricRows($topProcedimientosVolumen, 10),
                'empty_message' => 'Sin procedimientos para el rango seleccionado.',
            ],
            [
                'title' => $insuranceBreakdownTitle,
                'columns' => [$insuranceBreakdownItemLabel, 'Atenciones', '% del total'],
                'rows' => array_map(
                    static function (array $item) use ($formatCount, $formatPercent, $normalizeLabel, $totalAtenciones): array {
                        $cantidad = (int) ($item['cantidad'] ?? 0);
                        $porcentaje = $totalAtenciones > 0 ? round(($cantidad / $totalAtenciones) * 100, 2) : 0.0;
                        return [$normalizeLabel($item['afiliacion'] ?? ''), $formatCount($cantidad), $formatPercent($porcentaje)];
                    },
                    array_slice($topAfiliaciones, 0, 10)
                ),
                'empty_message' => 'Sin afiliaciones para el rango seleccionado.',
            ],
            [
                'title' => 'Calificación de rendimiento médico',
                'subtitle' => 'Score compuesto: 40% producción + 30% ticket + 20% atenciones pagadas - 10% tasa 0.',
                'columns' => ['Médico', 'Atenc.', 'C/Hon.', '% 0', 'Honorario real', 'Ticket prom. real', 'Score', 'Nivel'],
                'rows' => $buildDoctorPerformanceRows($doctorPerformanceRows, 10),
                'empty_message' => 'Sin médicos para el rango seleccionado.',
            ],
            [
                'title' => 'Formas de pago',
                'columns' => ['Forma de pago', 'Atenciones', '%'],
                'rows' => $buildMetricRows($formasPagoValues, 8),
                'empty_message' => 'Sin formas de pago registradas en el rango.',
            ],
            [
                'title' => 'Áreas con mayor honorario real',
                'columns' => ['Área', 'Honorario', '% del honorario'],
                'rows' => $buildMoneyRows($areasTop, 8),
                'empty_message' => 'Sin áreas con honorario en el rango.',
            ],
            [
                'title' => 'Desglose por sede',
                'columns' => ['Sede', 'Atenciones', '% del total'],
                'rows' => $buildMetricRows($desgloseSedes, 10),
                'empty_message' => 'Sin sedes para el rango seleccionado.',
            ],
            [
                'title' => 'Picos por día',
                'columns' => ['Día', 'Atenciones', '% del total'],
                'rows' => $buildMetricRows($picosDias, 7),
                'empty_message' => 'Sin picos operativos para el rango seleccionado.',
            ],
            [
                'title' => 'Origen de referencia: Total de atenciones',
                'subtitle' => 'Con valor: ' . $formatCount($referidoWithValue) . ' | Sin valor: ' . $formatCount($referidoWithoutValue),
                'columns' => ['Valor', 'Atenciones', '%', 'USD', 'Ticket prom.'],
                'rows' => $buildReferidoRows($referidoValues, 12),
                'empty_message' => 'Sin datos de origen de referencia.',
            ],
            [
                'title' => 'Origen de referencia: Pacientes únicos',
                'subtitle' => 'Con valor: ' . $formatCount($referidoPacientesUnicosWithValue) . ' | Sin valor: ' . $formatCount($referidoPacientesUnicosWithoutValue),
                'columns' => ['Valor', 'Pacientes únicos', '%', 'USD acumulado', 'Ticket prom. paciente'],
                'rows' => $buildReferidoRows($referidoPacientesUnicosValues, 12),
                'empty_message' => 'Sin datos de pacientes únicos por referencia.',
            ],
            [
                'title' => 'Origen de referencia: Nuevo paciente',
                'subtitle' => 'Con valor: ' . $formatCount($referidoNuevoPacienteWithValue) . ' | Sin valor: ' . $formatCount($referidoNuevoPacienteWithoutValue),
                'columns' => ['Valor', 'Cantidad', '%', 'USD', 'Ticket prom.'],
                'rows' => $buildReferidoRows($referidoNuevoPacienteValues, 12),
                'empty_message' => 'Sin datos de nuevo paciente por referencia.',
            ],
            [
                'title' => 'Jerarquía de referencias',
                'subtitle' => '% en categoría = participación de la subcategoría dentro de su categoría madre.',
                'columns' => ['Categoría madre', 'Subcategoría', 'Cantidad', '% en categoría'],
                'rows' => array_map(
                    static function (array $item) use ($formatCount, $formatPercent, $normalizeLabel): array {
                        return [
                            $normalizeLabel($item['categoria'] ?? ''),
                            $normalizeLabel($item['subcategoria'] ?? ''),
                            $formatCount((int) ($item['cantidad'] ?? 0)),
                            $formatPercent((float) ($item['porcentaje_en_categoria'] ?? 0)),
                        ];
                    },
                    array_slice($hierarquiaPares, 0, 15)
                ),
                'empty_message' => 'Sin jerarquía de referencias para el rango seleccionado.',
            ],
        ];

        $methodology = [
            'El universo del informe considera atenciones de categoría cliente Particular o Privado dentro del rango filtrado y aplica una lógica real específica por categoría de servicio.',
            'Cirugías, PNI, servicios oftalmológicos e imágenes se clasifican en realizadas, pendientes de facturar o pérdida según evidencia clínica, operativa, técnica y económica disponible.',
            'La fuente económica del reporte es exclusivamente billing_facturacion_real, unida por form_id.',
            'Se analiza únicamente billing_facturacion_real.monto_honorario como valor económico único por procedimiento/form_id.',
            'Los tickets promedio por referencia y categoría se calculan sobre el divisor operativo correspondiente a cada KPI.',
            'La calificación médica usa score compuesto para priorizar rendimiento económico real y penalizar alta tasa de atenciones en 0.',
        ];

        return [
            'generatedAt' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
            'totalAtenciones' => $totalAtenciones,
            'filterSummary' => $filterSummary,
            'hallazgosClave' => $hallazgosClave,
            'methodology' => $methodology,
            'generalKpis' => $generalKpis,
            'temporalKpis' => $temporalKpis,
            'economicKpis' => $economicKpis,
            'tables' => $tables,
        ];
    }

    private function exportInformeParticularesPdf(array $summary, array $filters): Response|RedirectResponse
    {
        $payload = $this->buildInformeParticularesKpiExportPayload($summary, $filters);
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        $filename = 'kpi_particulares_' . ($dateFrom !== '' ? str_replace('-', '', $dateFrom) : date('Ymd')) . '_' .
            ($dateTo !== '' ? str_replace('-', '', $dateTo) : date('Ymd')) . '.pdf';

        try {
            if (!class_exists(\Mpdf\Mpdf::class)) {
                throw new RuntimeException('La librería mPDF no está disponible en el entorno.');
            }

            $html = view('billing.pdf.particulares-kpi', [
                'generatedAt' => $payload['generatedAt'],
                'filterSummary' => $payload['filterSummary'],
                'hallazgosClave' => $payload['hallazgosClave'],
                'methodology' => $payload['methodology'],
                'generalKpis' => $payload['generalKpis'],
                'temporalKpis' => $payload['temporalKpis'],
                'economicKpis' => $payload['economicKpis'],
                'tables' => $payload['tables'],
                'totalAtenciones' => $payload['totalAtenciones'],
            ])->render();

            $pdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
            ]);

            $pdf->SetTitle('KPI Informe Particulares');
            $pdf->WriteHTML($html);
            $content = (string) $pdf->Output('', 'S');

            if (strncmp($content, '%PDF-', 5) !== 0) {
                throw new RuntimeException('El contenido generado no es un PDF válido.');
            }

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => (string) strlen($content),
            ]);
        } catch (\Throwable $exception) {
            Log::error('billing.particulares.export_pdf.error', [
                'error' => $exception->getMessage(),
            ]);

            $redirect = '/v2/informes/particulares';
            if (!empty($queryWithoutExport)) {
                $redirect .= '?' . http_build_query($queryWithoutExport);
            }

            return redirect($redirect)->with('error', 'No se pudo generar el PDF de KPI de particulares.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function exportInformeParticularesExcel(array $summary, array $rows, array $filters): Response
    {
        try {
            $payload = $this->buildInformeParticularesKpiExportPayload($summary, $filters);
            $dateFrom = trim((string) ($filters['date_from'] ?? ''));
            $dateTo = trim((string) ($filters['date_to'] ?? ''));
            $suffix = date('Ymd_His');
            if ($dateFrom !== '' && $dateTo !== '') {
                $suffix = str_replace('-', '', $dateFrom) . '_' . str_replace('-', '', $dateTo);
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Resumen KPI');
            $row = 1;

            $this->writeExcelMergedTitle($sheet, $row, 'Informe de Atenciones Particulares', 'H');
            $row++;
            $sheet->setCellValue("A{$row}", 'Generado:');
            $sheet->setCellValue("B{$row}", (string) $payload['generatedAt']);
            $sheet->setCellValue("D{$row}", 'Total atenciones:');
            $sheet->setCellValueExplicit("E{$row}", (string) ($payload['totalAtenciones'] ?? 0), DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Filtros aplicados', 'H');
            $filterRows = [];
            foreach ($payload['filterSummary'] as $filter) {
                $filterRows[] = [(string) ($filter['label'] ?? ''), (string) ($filter['value'] ?? '')];
            }
            $row = $this->writeExcelTable($sheet, $row, ['Filtro', 'Valor'], $filterRows, 'Sin filtros específicos.', [24, 58]);

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Hallazgos clave', 'H');
            $hallazgoRows = array_map(static fn(string $item): array => [$item], array_values($payload['hallazgosClave']));
            $row = $this->writeExcelTable($sheet, $row, ['Hallazgo'], $hallazgoRows, 'No hubo hallazgos destacados.', [92]);

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Metodología', 'H');
            $methodologyRows = array_map(static fn(string $item): array => [$item], array_values($payload['methodology']));
            $row = $this->writeExcelTable($sheet, $row, ['Criterio'], $methodologyRows, 'Sin metodología documentada.', [92]);

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Generales', 'H');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Detalle'],
                $this->normalizeExcelRows(is_array($payload['generalKpis']) ? $payload['generalKpis'] : [], ['label', 'value', 'note']),
                'Sin KPI generales.',
                [28, 18, 50]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Temporales', 'H');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Detalle'],
                $this->normalizeExcelRows(is_array($payload['temporalKpis']) ? $payload['temporalKpis'] : [], ['label', 'value', 'note']),
                'Sin KPI temporales.',
                [28, 18, 50]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Económicos', 'H');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Qué significa', 'Cómo se calcula'],
                $this->normalizeExcelRows(is_array($payload['economicKpis']) ? $payload['economicKpis'] : [], ['label', 'value', 'meaning', 'formula']),
                'Sin KPI económicos.',
                [24, 16, 30, 32]
            );

            foreach (is_array($payload['tables']) ? $payload['tables'] : [] as $table) {
                $title = trim((string) ($table['title'] ?? 'Tabla'));
                $subtitle = trim((string) ($table['subtitle'] ?? ''));
                $headers = array_values(array_map(static fn($value): string => (string) $value, is_array($table['columns'] ?? null) ? $table['columns'] : []));
                $tableRows = [];
                foreach (is_array($table['rows'] ?? null) ? $table['rows'] : [] as $tableRow) {
                    $tableRows[] = array_map(static fn($value): string => (string) $value, is_array($tableRow) ? $tableRow : []);
                }

                $row += 2;
                $row = $this->writeExcelSectionHeader($sheet, $row, $title, 'H');
                if ($subtitle !== '') {
                    $sheet->setCellValue("A{$row}", $subtitle);
                    $sheet->mergeCells("A{$row}:H{$row}");
                    $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($this->excelNoticeStyle('EFF6FF', '1D4ED8'));
                    $row++;
                }

                $row = $this->writeExcelTable(
                    $sheet,
                    $row,
                    $headers,
                    $tableRows,
                    trim((string) ($table['empty_message'] ?? 'Sin datos.'))
                );
            }

            $sheet->freezePane('A4');
            foreach (['A' => 28, 'B' => 18, 'C' => 18, 'D' => 20, 'E' => 18, 'F' => 18, 'G' => 16, 'H' => 22] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Detalle');
            $headers = [
                'Fecha', 'HC', 'Nombre', 'Empresa seguro', 'Afiliacion', 'Categoria cliente', 'Sede',
                'Estado encuentro', 'Estado realizacion', 'Tipo atencion', 'Procedimiento proyectado', 'Doctor',
                'Facturacion', 'Estado facturacion operativa', 'Monto estimado', 'Honorario real', 'Billing ID',
                'Fecha facturacion', 'Numero factura', 'Factura ID', 'Formas pago', 'Cliente facturacion',
                'Area facturacion', 'Referido prefactura por', 'Especificar referido prefactura',
            ];

            foreach ($headers as $index => $header) {
                $column = $this->excelColumnByIndex($index);
                $detailSheet->setCellValue("{$column}1", $header);
            }
            $lastDetailColumn = $this->excelColumnByIndex(count($headers) - 1);
            $detailSheet->getStyle("A1:{$lastDetailColumn}1")->applyFromArray($this->excelTableHeaderStyle());
            $detailSheet->setAutoFilter("A1:{$lastDetailColumn}1");

            $detailRow = 1;
            foreach ($rows as $item) {
                $detailRow++;
                $fechaRaw = trim((string) ($item['fecha'] ?? ''));
                $fecha = $fechaRaw !== '' && strtotime($fechaRaw) !== false ? date('d/m/Y H:i', strtotime($fechaRaw)) : '';
                $fechaFacturacionRaw = trim((string) ($item['fecha_facturacion'] ?? ''));
                $fechaFacturacion = $fechaFacturacionRaw !== '' && strtotime($fechaFacturacionRaw) !== false ? date('d/m/Y H:i', strtotime($fechaFacturacionRaw)) : '';
                $facturado = (bool) ($item['facturado'] ?? false);
                $montoEstimado = (float) ($item['monto_por_cobrar_estimado'] ?? 0);
                if ($montoEstimado <= 0) {
                    $montoEstimado = (float) ($item['monto_perdida_estimada'] ?? 0);
                }

                $values = [
                    $fecha,
                    (string) ($item['hc_number'] ?? ''),
                    trim((string) ($item['nombre_completo'] ?? '')),
                    trim((string) ($item['empresa_seguro'] ?? '')),
                    trim((string) ($item['afiliacion'] ?? '')),
                    trim((string) ($item['categoria_cliente'] ?? '')),
                    trim((string) ($item['sede'] ?? '')),
                    trim((string) ($item['estado_encuentro'] ?? '')),
                    trim((string) ($item['estado_realizacion'] ?? '')),
                    trim((string) ($item['tipo_atencion'] ?? '')),
                    trim((string) ($item['procedimiento_proyectado'] ?? '')),
                    trim((string) ($item['doctor'] ?? '')),
                    $facturado ? 'FACTURADO' : 'PENDIENTE',
                    trim((string) ($item['estado_facturacion_operativa'] ?? '')),
                    number_format($montoEstimado, 2, '.', ''),
                    number_format((float) ($item['monto_honorario_real'] ?? $item['total_produccion'] ?? 0), 2, '.', ''),
                    (string) ($item['billing_id'] ?? ''),
                    $fechaFacturacion,
                    trim((string) ($item['numero_factura'] ?? '')),
                    trim((string) ($item['factura_id'] ?? '')),
                    trim((string) ($item['formas_pago'] ?? '')),
                    trim((string) ($item['cliente_facturacion'] ?? '')),
                    trim((string) ($item['area_facturacion'] ?? '')),
                    trim((string) ($item['referido_prefactura_por'] ?? '')),
                    trim((string) ($item['especificar_referido_prefactura'] ?? '')),
                ];

                foreach ($values as $index => $value) {
                    $column = $this->excelColumnByIndex($index);
                    $detailSheet->setCellValueExplicit("{$column}{$detailRow}", $value, DataType::TYPE_STRING);
                }
            }

            if ($detailRow > 1) {
                $detailSheet->getStyle("A1:{$lastDetailColumn}{$detailRow}")->applyFromArray($this->excelTableBodyStyle());
                $detailSheet->getStyle("A2:{$lastDetailColumn}{$detailRow}")->getAlignment()->setWrapText(true);
            }
            $detailSheet->freezePane('A2');

            $writer = new Xlsx($spreadsheet);
            $stream = fopen('php://temp', 'r+');
            $writer->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream) ?: '';
            fclose($stream);
            $spreadsheet->disconnectWorksheets();

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="informe_particulares_' . $suffix . '.xlsx"',
                'Content-Length' => (string) strlen($content),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            Log::error('billing.particulares.export_excel.error', ['error' => $exception->getMessage()]);

            return response('No se pudo generar el Excel de KPI de particulares.', 500);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     */
    private function exportInformeParticularesCsv(array $rows, array $filters): Response
    {
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        $suffix = date('Ymd_His');
        if ($from !== '' && $to !== '') {
            $suffix = str_replace('-', '', $from) . '_' . str_replace('-', '', $to);
        }
        $filename = 'informe_particulares_' . $suffix . '.csv';

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return response('No se pudo generar el archivo CSV.', 500);
        }

        fputcsv($handle, [
            'Fecha',
            'HC',
            'Nombre',
            'Empresa seguro',
            'Afiliacion',
            'Categoria cliente',
            'Sede',
            'Estado encuentro',
            'Estado realizacion',
            'Tipo atencion',
            'Procedimiento proyectado',
            'Doctor',
            'Facturacion',
            'Estado facturacion operativa',
            'Monto estimado',
            'Honorario real',
            'Billing ID',
            'Fecha facturacion',
            'Numero factura',
            'Factura ID',
            'Formas pago',
            'Cliente facturacion',
            'Area facturacion',
            'Referido prefactura por',
            'Especificar referido prefactura',
            'Sin tarifa estimable',
            'Sin costo configurado',
            'Codigo tarifario',
            'Detalle tarifario',
            'Estado tarifa',
            'Motivo tarifa',
            'Nivel tarifa',
            'Codigo match',
            'Descripcion match',
        ]);

        foreach ($rows as $row) {
            $fechaRaw = trim((string) ($row['fecha'] ?? ''));
            $fecha = $fechaRaw !== '' && strtotime($fechaRaw) !== false ? date('d/m/Y H:i', strtotime($fechaRaw)) : '';
            $fechaFacturacionRaw = trim((string) ($row['fecha_facturacion'] ?? ''));
            $fechaFacturacion = $fechaFacturacionRaw !== '' && strtotime($fechaFacturacionRaw) !== false
                ? date('d/m/Y H:i', strtotime($fechaFacturacionRaw))
                : '';
            $facturado = (bool) ($row['facturado'] ?? false);
            $estadoRealizacion = trim((string) ($row['estado_realizacion'] ?? ''));
            $estadoFacturacionOperativa = trim((string) ($row['estado_facturacion_operativa'] ?? ''));
            $montoEstimado = (float) ($row['monto_por_cobrar_estimado'] ?? 0);
            if ($montoEstimado <= 0) {
                $montoEstimado = (float) ($row['monto_perdida_estimada'] ?? 0);
            }

            fputcsv($handle, [
                $fecha,
                (string) ($row['hc_number'] ?? ''),
                trim((string) ($row['nombre_completo'] ?? '')),
                trim((string) ($row['empresa_seguro'] ?? '')),
                trim((string) ($row['afiliacion'] ?? '')),
                trim((string) ($row['categoria_cliente'] ?? '')),
                trim((string) ($row['sede'] ?? '')),
                trim((string) ($row['estado_encuentro'] ?? '')),
                $estadoRealizacion,
                trim((string) ($row['tipo_atencion'] ?? '')),
                trim((string) ($row['procedimiento_proyectado'] ?? '')),
                trim((string) ($row['doctor'] ?? '')),
                $facturado ? 'FACTURADO' : 'PENDIENTE',
                $estadoFacturacionOperativa,
                number_format($montoEstimado, 2, '.', ''),
                number_format((float) ($row['monto_honorario_real'] ?? $row['total_produccion'] ?? 0), 2, '.', ''),
                (string) ($row['billing_id'] ?? ''),
                $fechaFacturacion,
                trim((string) ($row['numero_factura'] ?? '')),
                trim((string) ($row['factura_id'] ?? '')),
                trim((string) ($row['formas_pago'] ?? '')),
                trim((string) ($row['cliente_facturacion'] ?? '')),
                trim((string) ($row['area_facturacion'] ?? '')),
                trim((string) ($row['referido_prefactura_por'] ?? '')),
                trim((string) ($row['especificar_referido_prefactura'] ?? '')),
                (bool) ($row['sin_tarifa_estimable'] ?? false) ? 'SI' : 'NO',
                (bool) ($row['tarifa_sin_costo_configurado'] ?? false) ? 'SI' : 'NO',
                trim((string) ($row['tarifa_codigo'] ?? '')),
                trim((string) ($row['tarifa_detalle'] ?? '')),
                trim((string) ($row['tarifa_lookup_status'] ?? '')),
                trim((string) ($row['tarifa_lookup_reason'] ?? '')),
                trim((string) ($row['tarifa_level_title'] ?? $row['tarifa_level_key'] ?? '')),
                trim((string) ($row['tarifa_codigo_match'] ?? '')),
                trim((string) ($row['tarifa_descripcion_match'] ?? '')),
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        $csvBody = "\xEF\xBB\xBF" . ($csvContent !== false ? $csvContent : '');

        return response($csvBody, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function writeExcelMergedTitle(Worksheet $sheet, int $row, string $title, string $lastColumn = 'H'): void
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function excelNoticeStyle(string $fillColor, string $textColor): array
    {
        return [
            'font' => [
                'italic' => true,
                'color' => ['rgb' => $textColor],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BFDBFE'],
                ],
            ],
            'alignment' => [
                'wrapText' => true,
                'vertical' => Alignment::VERTICAL_TOP,
            ],
        ];
    }

    private function writeExcelSectionHeader(Worksheet $sheet, int $row, string $title, string $lastColumn = 'H'): int
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '0F172A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        return $row + 1;
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $rows
     * @param array<int,int|float> $widths
     */
    private function writeExcelTable(
        Worksheet $sheet,
        int $row,
        array $headers,
        array $rows,
        string $emptyMessage,
        array $widths = []
    ): int {
        if ($headers === []) {
            return $row;
        }

        $lastColumn = $this->excelColumnByIndex(count($headers) - 1);
        foreach ($headers as $index => $header) {
            $column = $this->excelColumnByIndex($index);
            $sheet->setCellValue("{$column}{$row}", $header);
            if (isset($widths[$index])) {
                $sheet->getColumnDimension($column)->setWidth((float) $widths[$index]);
            }
        }
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($this->excelTableHeaderStyle());
        $bodyStart = $row + 1;

        if ($rows === []) {
            $sheet->setCellValue("A{$bodyStart}", $emptyMessage);
            $sheet->mergeCells("A{$bodyStart}:{$lastColumn}{$bodyStart}");
            $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$bodyStart}")->applyFromArray($this->excelTableBodyStyle());
            $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$bodyStart}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$bodyStart}")->getAlignment()->setWrapText(true);

            return $bodyStart;
        }

        $currentRow = $bodyStart;
        foreach ($rows as $dataRow) {
            foreach ($headers as $index => $_header) {
                $column = $this->excelColumnByIndex($index);
                $sheet->setCellValueExplicit("{$column}{$currentRow}", (string) ($dataRow[$index] ?? ''), DataType::TYPE_STRING);
            }
            $currentRow++;
        }

        $endRow = $currentRow - 1;
        $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$endRow}")->applyFromArray($this->excelTableBodyStyle());
        $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$endRow}")->getAlignment()->setWrapText(true);

        return $endRow;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $keys
     * @return array<int,array<int,string>>
     */
    private function normalizeExcelRows(array $rows, array $keys): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $normalizedRow = [];
            foreach ($keys as $key) {
                $normalizedRow[] = trim((string) ($row[$key] ?? ''));
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function excelTableHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '0F172A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function excelTableBodyStyle(): array
    {
        return [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
            ],
        ];
    }

    private function excelColumnByIndex(int $index): string
    {
        $index = max(0, $index);
        $column = '';

        do {
            $remainder = $index % 26;
            $column = chr(65 + $remainder) . $column;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $column;
    }
}
