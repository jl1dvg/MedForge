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
        if (in_array($export, ['csv', 'excel'], true)) {
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
    private function exportInformeParticularesPdf(array $summary, array $filters): Response|RedirectResponse
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
        $totalConsultas = (int) ($summary['total_consultas'] ?? 0);
        $totalProtocolos = (int) ($summary['total_protocolos'] ?? 0);
        $pacientesUnicos = (int) ($summary['pacientes_unicos'] ?? 0);
        $honorarioRealTotal = (float) ($economico['total_honorario_real'] ?? $economico['total_produccion'] ?? 0);
        $operativoEvaluadas = (int) ($operativo['evaluadas'] ?? $totalAtenciones);
        $operativoRealizadas = (int) ($operativo['realizadas'] ?? 0);
        $operativoFacturadas = (int) ($operativo['facturadas'] ?? 0);
        $operativoPendientesFacturar = (int) ($operativo['pendientes_facturar'] ?? 0);
        $operativoPerdidas = (int) ($operativo['perdidas'] ?? 0);
        $operativoSinCierre = (int) ($operativo['sin_cierre'] ?? 0);
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
        $doctoresTop = is_array($economico['doctores_top'] ?? null) ? $economico['doctores_top'] : [];
        $areasTop = is_array($economico['areas_top'] ?? null) ? $economico['areas_top'] : [];

        $particularCount = (int) ($categoriaCounts['particular'] ?? 0);
        $privadoCount = (int) ($categoriaCounts['privado'] ?? 0);
        $particularShare = (float) ($categoriaShare['particular'] ?? 0);
        $privadoShare = (float) ($categoriaShare['privado'] ?? 0);

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
        $desgloseDoctores = is_array($desgloseGerencial['doctores'] ?? null) ? $desgloseGerencial['doctores'] : [];

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
        $queryWithoutExport = array_filter([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'empresa_seguro' => trim((string) ($filters['empresa_seguro'] ?? '')),
            'categoria_cliente' => trim((string) ($filters['categoria_cliente'] ?? '')),
            'categoria_madre_referido' => trim((string) ($filters['categoria_madre_referido'] ?? '')),
            'tipo' => trim((string) ($filters['tipo'] ?? '')),
            'sede' => trim((string) ($filters['sede'] ?? '')),
            'afiliacion' => trim((string) ($filters['afiliacion'] ?? '')),
            'procedimiento' => trim((string) ($filters['procedimiento'] ?? '')),
        ], static fn($value): bool => trim((string) $value) !== '');

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
        $buildTrendRows = static function (array $labels, array $values) use ($formatCurrency): array {
            $rows = [];
            $count = min(count($labels), count($values));
            for ($index = 0; $index < $count; $index++) {
                $rows[] = [
                    trim((string) $labels[$index]) !== '' ? (string) $labels[$index] : 'N/D',
                    $formatCurrency((float) ($values[$index] ?? 0)),
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
        $buildCombinedMoneyMetricRows = static function (array $moneyItems, array $metricItems, int $limit = 10) use ($formatCount, $formatCurrency, $formatPercent, $normalizeLabel): array {
            $order = [];
            $moneyMap = [];
            $metricMap = [];

            foreach ($moneyItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $label = $normalizeLabel($item['valor'] ?? '');
                if (!in_array($label, $order, true)) {
                    $order[] = $label;
                }

                $moneyMap[$label] = [
                    'amount' => (float) ($item['monto'] ?? 0),
                    'percent' => (float) ($item['porcentaje'] ?? 0),
                ];
            }

            foreach ($metricItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $label = $normalizeLabel($item['valor'] ?? '');
                if (!in_array($label, $order, true)) {
                    $order[] = $label;
                }

                $metricMap[$label] = [
                    'count' => (int) ($item['cantidad'] ?? 0),
                    'percent' => (float) ($item['porcentaje'] ?? 0),
                ];
            }

            $rows = [];
            foreach (array_slice($order, 0, $limit) as $label) {
                $metric = $metricMap[$label] ?? ['count' => 0, 'percent' => 0];
                $money = $moneyMap[$label] ?? ['amount' => 0, 'percent' => 0];

                $rows[] = [
                    $label,
                    $formatCount($metric['count']),
                    $formatPercent($metric['percent']),
                    $formatCurrency($money['amount']),
                    $formatPercent($money['percent']),
                ];
            }

            return $rows;
        };

        $categoriaLiderLabel = $particularCount >= $privadoCount ? 'PARTICULAR' : 'PRIVADO';
        $categoriaLiderCount = $particularCount >= $privadoCount ? $particularCount : $privadoCount;
        $categoriaLiderPct = $particularCount >= $privadoCount ? $particularShare : $privadoShare;
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
            [
                'label' => 'Atenciones evaluadas',
                'value' => $formatCount($operativoEvaluadas),
                'note' => 'Universo auditado con la lógica real por categoría de servicio.',
            ],
            [
                'label' => 'Realizadas',
                'value' => $formatCount($operativoRealizadas) . ' (' . $formatPercent($operativoRealizacionRate) . ')',
                'note' => 'Atenciones con evidencia real de servicio realizado.',
            ],
            [
                'label' => 'Facturadas',
                'value' => $formatCount($operativoFacturadas) . ' (' . $formatPercent($operativoFacturacionRate) . ')',
                'note' => 'Atenciones realizadas que ya tienen billing real asociado.',
            ],
            [
                'label' => 'Pendientes de facturar',
                'value' => $formatCount($operativoPendientesFacturar) . ' (' . $formatPercent($operativoPendienteRate) . ')',
                'note' => 'Atenciones realizadas con respaldo operativo o clínico aún sin billing real.',
            ],
            [
                'label' => 'Pérdida operativa',
                'value' => $formatCount($operativoPerdidas) . ' (' . $formatPercent($operativoPerdidaRate) . ')',
                'note' => 'Atenciones no realizadas o perdidas según la lógica real por servicio.',
            ],
            [
                'label' => 'Pacientes únicos',
                'value' => $formatCount($pacientesUnicos),
                'note' => 'Pacientes distintos incluidos en el rango filtrado.',
            ],
        ];

        $temporalKpis = [
            [
                'label' => 'Volumen mes actual',
                'value' => $formatCount($currentMonthCount),
                'note' => $currentMonthLabel,
            ],
            [
                'label' => 'Vs mes anterior',
                'value' => $vsPreviousPct === null ? 'N/D' : (($vsPreviousPct >= 0 ? '↑ ' : '↓ ') . $formatPercent(abs($vsPreviousPct))),
                'note' => $formatCount($currentMonthCount) . ' vs ' . $formatCount($previousMonthCount) . ' (' . $previousMonthLabel . ')',
            ],
            [
                'label' => 'Vs mismo mes año pasado',
                'value' => $vsLastYearPct === null ? 'N/D' : (($vsLastYearPct >= 0 ? '↑ ' : '↓ ') . $formatPercent(abs($vsLastYearPct))),
                'note' => $formatCount($currentMonthCount) . ' vs ' . $formatCount($sameMonthLastYearCount) . ' (' . $sameMonthLastYearLabel . ')',
            ],
            [
                'label' => 'Nuevos vs recurrentes',
                'value' => $formatCount($pacientesNuevos) . ' / ' . $formatCount($pacientesRecurrentes),
                'note' => 'Nuevos ' . $formatPercent($pacientesNuevosPct) . ' | Recurrentes ' . $formatPercent($pacientesRecurrentesPct),
            ],
            [
                'label' => 'Pico operativo por día',
                'value' => $normalizeLabel($peakDay['valor'] ?? 'N/D', 'N/D') . ' (' . $formatCount((int) ($peakDay['cantidad'] ?? 0)) . ')',
                'note' => 'Día con mayor volumen de atención en el rango filtrado.',
            ],
        ];

        $economicKpis = [
            [
                'label' => 'Honorario real acumulado',
                'value' => $formatCurrency($honorarioRealTotal),
                'meaning' => 'Suma del valor económico real único por procedimiento, obtenido desde billing_facturacion_real.',
                'formula' => 'SUM(billing_facturacion_real.monto_honorario) sobre las atenciones filtradas.',
            ],
            [
                'label' => 'Por cobrar estimado',
                'value' => $formatCurrency($operativoPorCobrarEstimado),
                'meaning' => 'Monto estimado pendiente de cobrar en atenciones realizadas sin billing real.',
                'formula' => 'SUM(monto_por_cobrar_estimado) para estados operativos PENDIENTE_FACTURAR.',
            ],
            [
                'label' => 'Pérdida estimada',
                'value' => $formatCurrency($operativoPerdidaEstimada),
                'meaning' => 'Monto estimado de producción perdida por cancelación, ausentismo o pérdida operativa.',
                'formula' => 'SUM(monto_perdida_estimada) según la lógica real por categoría de servicio.',
            ],
            [
                'label' => 'Potencial capturable',
                'value' => $formatCurrency($operativoPotencialCapturable),
                'meaning' => 'Suma de honorario real ya capturado más el valor estimado todavía pendiente de cobro.',
                'formula' => 'Honorario real acumulado + por cobrar estimado.',
            ],
            [
                'label' => 'Cobro sobre realizadas',
                'value' => $formatPercent($operativoFacturacionRate),
                'meaning' => 'Cobertura de facturación real sobre las atenciones efectivamente realizadas.',
                'formula' => 'Atenciones facturadas / Atenciones realizadas.',
            ],
            [
                'label' => 'Ticket facturado real',
                'value' => $formatCurrency($operativoTicketFacturadoReal),
                'meaning' => 'Honorario real promedio por atención con billing real.',
                'formula' => 'Honorario real acumulado / Atenciones facturadas.',
            ],
            [
                'label' => 'Ticket pendiente',
                'value' => $formatCurrency($operativoTicketPendiente),
                'meaning' => 'Valor estimado promedio por cada atención pendiente de facturar.',
                'formula' => 'Por cobrar estimado / Atenciones pendientes de facturar.',
            ],
            [
                'label' => 'Honorario Particular',
                'value' => $formatCurrency($honorarioParticular),
                'meaning' => 'Honorario real acumulado para la categoría cliente Particular.',
                'formula' => 'SUM(monto_honorario) filtrando categoria_cliente = particular.',
            ],
            [
                'label' => 'Honorario Privado',
                'value' => $formatCurrency($honorarioPrivado),
                'meaning' => 'Honorario real acumulado para la categoría cliente Privado.',
                'formula' => 'SUM(monto_honorario) filtrando categoria_cliente = privado.',
            ],
        ];

        $tables = [
            [
                'title' => 'Categoría cliente: volumen + honorario',
                'columns' => ['Categoría', 'Atenciones', '% del total', 'Honorario real'],
                'rows' => [
                    ['PARTICULAR', $formatCount($particularCount), $formatPercent($particularShare), $formatCurrency($honorarioParticular)],
                    ['PRIVADO', $formatCount($privadoCount), $formatPercent($privadoShare), $formatCurrency($honorarioPrivado)],
                    ['TOTAL', $formatCount($totalAtenciones), $formatPercent(100), $formatCurrency($honorarioRealTotal)],
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

                        return [
                            $normalizeLabel($item['afiliacion'] ?? ''),
                            $formatCount($cantidad),
                            $formatPercent($porcentaje),
                        ];
                    },
                    array_slice($topAfiliaciones, 0, 10)
                ),
                'empty_message' => 'Sin afiliaciones para el rango seleccionado.',
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
                'title' => 'Médicos: volumen + honorario',
                'columns' => ['Médico', 'Atenciones', '% del total', 'Honorario', '% del honorario'],
                'rows' => $buildCombinedMoneyMetricRows($doctoresTop, $desgloseDoctores, 10),
                'empty_message' => 'Sin médicos para el rango seleccionado.',
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
                'columns' => ['Valor', 'Atenciones', '%'],
                'rows' => $buildMetricRows($referidoValues, 12),
                'empty_message' => 'Sin datos de origen de referencia.',
            ],
            [
                'title' => 'Origen de referencia: Pacientes únicos',
                'subtitle' => 'Con valor: ' . $formatCount($referidoPacientesUnicosWithValue) . ' | Sin valor: ' . $formatCount($referidoPacientesUnicosWithoutValue),
                'columns' => ['Valor', 'Pacientes únicos', '%'],
                'rows' => $buildMetricRows($referidoPacientesUnicosValues, 12),
                'empty_message' => 'Sin datos de pacientes únicos por referencia.',
            ],
            [
                'title' => 'Origen de referencia: Nuevo paciente',
                'subtitle' => 'Con valor: ' . $formatCount($referidoNuevoPacienteWithValue) . ' | Sin valor: ' . $formatCount($referidoNuevoPacienteWithoutValue),
                'columns' => ['Valor', 'Cantidad', '%'],
                'rows' => $buildMetricRows($referidoNuevoPacienteValues, 12),
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
            'La fuente económica del PDF es exclusivamente billing_facturacion_real, unida por form_id.',
            'Se analiza únicamente billing_facturacion_real.monto_honorario como valor económico único por procedimiento/form_id.',
            'billing_facturacion_real.monto_facturado no se usa en KPI ni totales porque puede repetir el total diario en múltiples form_id y sobrestimar la producción.',
            'Facturas emitidas, número de factura, forma de pago, cliente y área se usan como metadata operativa y no como base de suma monetaria.',
        ];

        $filename = 'kpi_particulares_' . ($dateFrom !== '' ? str_replace('-', '', $dateFrom) : date('Ymd')) . '_' .
            ($dateTo !== '' ? str_replace('-', '', $dateTo) : date('Ymd')) . '.pdf';

        try {
            if (!class_exists(\Mpdf\Mpdf::class)) {
                throw new RuntimeException('La librería mPDF no está disponible en el entorno.');
            }

            $html = view('billing.pdf.particulares-kpi', [
                'generatedAt' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                'filterSummary' => $filterSummary,
                'hallazgosClave' => $hallazgosClave,
                'methodology' => $methodology,
                'generalKpis' => $generalKpis,
                'temporalKpis' => $temporalKpis,
                'economicKpis' => $economicKpis,
                'tables' => $tables,
                'totalAtenciones' => $totalAtenciones,
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
}
