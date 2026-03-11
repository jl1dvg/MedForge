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
        $this->service = new BillingUiService();
        $this->particularesReportService = new BillingParticularesReportService($pdo);
        $this->dashboardDataService = new BillingDashboardDataService();
        $this->honorariosDashboardService = new HonorariosDashboardDataService();
        $this->informePacienteService = new BillingInformePacienteService($pdo);
        $this->informeDataService = new BillingInformeDataService($pdo, $this->informePacienteService);
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

    public function informeIssfaConsolidado(Request $request): Response|RedirectResponse
    {
        return $this->exportConsolidadoSimple($request, 'issfa');
    }

    public function informeParticulares(Request $request): JsonResponse|RedirectResponse|View
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
            'afiliacion' => (string) $request->query('afiliacion', ''),
            'sede' => $this->normalizeSedeFilter((string) $request->query('sede', '')),
            'categoria_cliente' => (string) $request->query('categoria_cliente', ''),
            'tipo' => (string) $request->query('tipo', ''),
            'procedimiento' => (string) $request->query('procedimiento', ''),
        ];

        try {
            $baseRows = $this->particularesReportService->obtenerAtencionesParticulares($range['from'], $range['to']);
            $rows = $this->particularesReportService->aplicarFiltros($baseRows, $filters);
            $catalogos = $this->particularesReportService->catalogos($baseRows);
            $summary = $this->particularesReportService->resumen($rows);
        } catch (\Throwable) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No se pudo cargar el informe de particulares.'], 500);
            }

            return redirect('/v2/billing')->with('error', 'No se pudo cargar el informe de particulares.');
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

        try {
            $data = $this->dashboardDataService->buildSummary(
                $range['start']->format('Y-m-d 00:00:00'),
                $range['end']->format('Y-m-d 23:59:59'),
                $sedeFilter
            );
        } catch (\Throwable) {
            return response()->json(['error' => 'No se pudo cargar el dashboard de billing.'], 500);
        }

        return response()->json([
            'filters' => [
                'date_from' => $range['from'],
                'date_to' => $range['to'],
                'sede' => $sedeFilter,
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
            'afiliacion' => $payload['afiliacion'] ?? null,
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
                'afiliacion' => $filters['afiliacion'],
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
                    ],
                    [
                        'grupo' => 'IESS_SOAM',
                        'label' => 'Descargar SOAM',
                        'class' => 'btn btn-outline-success btn-lg me-2',
                        'icon' => 'fa fa-file-excel-o',
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

        $maxScrapeBatch = 20;
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
            $script = base_path('../scrapping/scrape_log_admision.py');
            $outputs = [];

            if (count($hcNumbersScrape) === 1 && count($formIdsScrape) > 1) {
                $hcNumbersScrape = array_fill(0, count($formIdsScrape), $hcNumbersScrape[0]);
            }

            foreach ($formIdsScrape as $index => $formIdScrape) {
                $hcNumberScrape = $hcNumbersScrape[$index] ?? $hcNumbersScrape[0] ?? null;
                if (!$hcNumberScrape) {
                    continue;
                }

                $command = sprintf(
                    '/usr/bin/python3 %s %s %s',
                    escapeshellarg($script),
                    escapeshellarg((string) $formIdScrape),
                    escapeshellarg((string) $hcNumberScrape)
                );
                $outputs[] = shell_exec($command);
            }

            $outputs = array_filter($outputs, static fn($output) => $output !== null && $output !== '');
            if (count($outputs) === 1) {
                $scrapingOutput = reset($outputs);
            } elseif ($outputs !== []) {
                $procedimientos = [];
                foreach ($outputs as $output) {
                    $partes = explode('📋 Procedimientos proyectados:', (string) $output);
                    if (isset($partes[1])) {
                        $procedimientos[] = trim($partes[1]);
                    }
                }

                $scrapingOutput = $procedimientos !== []
                    ? "📋 Procedimientos proyectados:\n" . implode("\n", $procedimientos)
                    : implode("\n\n", $outputs);
            }

            if ($scrapingLimitMessage !== '') {
                $scrapingOutput = ($scrapingOutput !== null && $scrapingOutput !== '')
                    ? $scrapingLimitMessage . "\n" . $scrapingOutput
                    : $scrapingLimitMessage;
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
        ];

        $mesSeleccionado = $filtros['mes'];
        $vistaParamPresente = $request->query->has('vista');
        $vista = $vistaParamPresente ? (string) $request->query('vista', '') : '';
        if (!$vistaParamPresente && $mesSeleccionado !== '') {
            $vista = 'rapida';
        }

        $facturas = $billingController->obtenerFacturasDisponibles($mesSeleccionado !== '' ? $mesSeleccionado : null);

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
        if ($mesSeleccionado !== '') {
            foreach ($facturas as $factura) {
                $fechaOrdenada = $factura['fecha_ordenada'] ?? null;
                $mes = $fechaOrdenada ? date('Y-m', strtotime((string) $fechaOrdenada)) : '';
                if ($mes !== $mesSeleccionado) {
                    continue;
                }

                $hc = $factura['hc_number'];
                $formId = $factura['form_id'];

                if (!isset($cachePorMes[$mes]['pacientes'][$hc])) {
                    $paciente = $pacienteService->getPatientDetails((string) $hc);
                    $cachePorMes[$mes]['pacientes'][$hc] = $paciente;
                    $pacientesCache[$hc] = $paciente;
                }

                if ($vista !== 'rapida' && !isset($cachePorMes[$mes]['datos'][$formId])) {
                    $datos = $billingController->obtenerDatos((string) $formId);
                    $cachePorMes[$mes]['datos'][$formId] = $datos;
                    $datosCache[$formId] = $datos;
                }
            }
        }

        $billingIds = isset($filtros['billing_id']) && $filtros['billing_id'] !== ''
            ? array_values(array_filter(array_map('trim', explode(',', (string) $filtros['billing_id']))))
            : [];

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
}
