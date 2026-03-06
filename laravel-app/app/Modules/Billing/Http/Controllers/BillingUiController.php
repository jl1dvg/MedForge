<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Services\BillingDashboardDataService;
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
use Symfony\Component\HttpFoundation\Response;

class BillingUiController
{
    private BillingUiService $service;
    private BillingDashboardDataService $dashboardDataService;
    private HonorariosDashboardDataService $honorariosDashboardService;

    public function __construct()
    {
        $this->service = new BillingUiService();
        $this->dashboardDataService = new BillingDashboardDataService();
        $this->honorariosDashboardService = new HonorariosDashboardDataService();
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

        $serviceClass = '\\Services\\BillingProcedimientosKpiService';
        if (!class_exists($serviceClass)) {
            $candidatePaths = [
                base_path('controllers/Services/BillingProcedimientosKpiService.php'),
                base_path('../controllers/Services/BillingProcedimientosKpiService.php'),
                dirname(base_path()) . '/controllers/Services/BillingProcedimientosKpiService.php',
            ];

            foreach ($candidatePaths as $candidatePath) {
                $resolved = realpath($candidatePath);
                if ($resolved !== false && is_file($resolved)) {
                    require_once $resolved;
                    break;
                }
            }
        }

        if (!class_exists($serviceClass)) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio de KPI no disponible.',
            ], 500);
        }

        try {
            $pdo = DB::connection()->getPdo();
            /** @var \Services\BillingProcedimientosKpiService $service */
            $service = new $serviceClass($pdo);

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
