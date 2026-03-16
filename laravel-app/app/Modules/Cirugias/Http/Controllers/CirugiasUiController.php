<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Services\CirugiaService;
use App\Modules\Cirugias\Services\CirugiasDashboardService;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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
        $payload = $this->buildDashboardExportPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $report = is_array($payload['report'] ?? null) ? $payload['report'] : [];
        $filename = 'dashboard_cirugias_' . date('Ymd_His') . '.pdf';

        try {
            if (!class_exists(\Mpdf\Mpdf::class)) {
                throw new \RuntimeException('La librería mPDF no está disponible en el entorno.');
            }

            $html = view('cirugias.pdf.dashboard-kpi', [
                'generatedAt' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                'filterSummary' => is_array($payload['filtersSummary'] ?? null) ? $payload['filtersSummary'] : [],
                'scopeNotice' => trim((string) ($report['scopeNotice'] ?? '')),
                'hallazgosClave' => is_array($report['hallazgosClave'] ?? null) ? $report['hallazgosClave'] : [],
                'methodology' => is_array($report['methodology'] ?? null) ? $report['methodology'] : [],
                'generalKpis' => is_array($report['generalKpis'] ?? null) ? $report['generalKpis'] : [],
                'temporalKpis' => is_array($report['temporalKpis'] ?? null) ? $report['temporalKpis'] : [],
                'economicKpis' => is_array($report['economicKpis'] ?? null) ? $report['economicKpis'] : [],
                'tables' => is_array($report['tables'] ?? null) ? $report['tables'] : [],
                'totalAtenciones' => (int) ($report['totalAtenciones'] ?? 0),
                'rangeLabel' => trim((string) ($report['rangeLabel'] ?? '')),
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
            $pdf->SetTitle('KPI Dashboard Cirugías');
            $pdf->WriteHTML($html);
            $pdf = (string) $pdf->Output('', 'S');

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
        $payload = $this->buildDashboardExportPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $detailRows = is_array($payload['detailRows'] ?? null) ? $payload['detailRows'] : [];
        $filtersSummary = is_array($payload['filtersSummary'] ?? null) ? $payload['filtersSummary'] : [];
        $report = is_array($payload['report'] ?? null) ? $payload['report'] : [];
        $filename = 'dashboard_cirugias_' . date('Ymd_His') . '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Resumen KPI');
            $generatedAt = (new DateTimeImmutable('now'))->format('d/m/Y H:i');
            $row = 1;

            $this->writeExcelMergedTitle($sheet, $row, 'Dashboard de KPIs quirúrgicos', 'G');
            $row++;
            $sheet->setCellValue("A{$row}", 'Generado:');
            $sheet->setCellValue("B{$row}", $generatedAt);
            $sheet->setCellValue("D{$row}", 'Periodo:');
            $sheet->setCellValue("E{$row}", (string) ($report['rangeLabel'] ?? ''));
            $sheet->setCellValue("F{$row}", 'Registros:');
            $sheet->setCellValueExplicit("G{$row}", (string) count($detailRows), DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);

            $scopeNotice = trim((string) ($report['scopeNotice'] ?? ''));
            if ($scopeNotice !== '') {
                $row += 2;
                $sheet->setCellValue("A{$row}", $scopeNotice);
                $sheet->mergeCells("A{$row}:G{$row}");
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($this->excelNoticeStyle('EFF6FF', '1D4ED8'));
                $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setWrapText(true);
            }

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Filtros aplicados', 'G');
            $filterRows = [];
            foreach ($filtersSummary as $filter) {
                $filterRows[] = [
                    (string) ($filter['label'] ?? ''),
                    (string) ($filter['value'] ?? ''),
                ];
            }
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['Filtro', 'Valor'],
                $filterRows,
                'Sin filtros específicos.',
                [26, 62]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Hallazgos clave', 'G');
            $hallazgosRows = array_map(
                static fn(string $item): array => [$item],
                array_values(array_filter(
                    is_array($report['hallazgosClave'] ?? null) ? $report['hallazgosClave'] : [],
                    static fn($item): bool => trim((string) $item) !== ''
                ))
            );
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['Hallazgo'],
                $hallazgosRows,
                'No hubo suficientes datos para generar hallazgos destacados.',
                [96]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Metodología', 'G');
            $methodologyRows = array_map(
                static fn(string $item): array => [$item],
                array_values(array_filter(
                    is_array($report['methodology'] ?? null) ? $report['methodology'] : [],
                    static fn($item): bool => trim((string) $item) !== ''
                ))
            );
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['Criterio'],
                $methodologyRows,
                'Sin metodología documentada.',
                [96]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Generales', 'G');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Detalle'],
                $this->normalizeExcelRows(is_array($report['generalKpis'] ?? null) ? $report['generalKpis'] : [], ['label', 'value', 'note']),
                'Sin KPI generales para el rango seleccionado.',
                [28, 16, 54]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Temporales', 'G');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Detalle'],
                $this->normalizeExcelRows(is_array($report['temporalKpis'] ?? null) ? $report['temporalKpis'] : [], ['label', 'value', 'note']),
                'Sin KPI temporales para el rango seleccionado.',
                [28, 16, 54]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Económicos', 'G');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Qué significa', 'Cómo se calcula'],
                $this->normalizeExcelRows(is_array($report['economicKpis'] ?? null) ? $report['economicKpis'] : [], ['label', 'value', 'meaning', 'formula']),
                'Sin KPI económicos para el rango seleccionado.',
                [24, 16, 34, 34]
            );

            $tables = is_array($report['tables'] ?? null) ? $report['tables'] : [];
            foreach ($tables as $table) {
                $title = trim((string) ($table['title'] ?? 'Tabla'));
                $subtitle = trim((string) ($table['subtitle'] ?? ''));
                $row += 2;
                $row = $this->writeExcelSectionHeader($sheet, $row, $title, 'G');
                if ($subtitle !== '') {
                    $sheet->setCellValue("A{$row}", $subtitle);
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                    $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setWrapText(true);
                    $row++;
                }
                $headers = array_values(array_map(static fn($value): string => (string) $value, is_array($table['columns'] ?? null) ? $table['columns'] : []));
                $tableRows = [];
                foreach (is_array($table['rows'] ?? null) ? $table['rows'] : [] as $tableRow) {
                    $tableRows[] = array_map(static fn($value): string => (string) $value, is_array($tableRow) ? $tableRow : []);
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
            foreach (['A' => 28, 'B' => 18, 'C' => 28, 'D' => 20, 'E' => 24, 'F' => 18, 'G' => 18] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Detalle');
            $detailHeaders = [
                '#',
                'Fecha cirugía',
                'Form ID',
                'HC',
                'Paciente',
                'Afiliación',
                'Categoría cliente',
                'Sede',
                'Procedimiento',
                'Facturado',
                'Estado facturación operativa',
                'Pendiente pago',
                'Fuente billing',
                'Producción USD',
                'Proc. facturados',
                'Billing ID',
                'Fecha facturación',
            ];

            $detailRow = 1;
            foreach ($detailHeaders as $idx => $label) {
                $column = $this->excelColumnByIndex($idx);
                $detailSheet->setCellValue("{$column}{$detailRow}", $label);
            }
            $lastDetailColumn = $this->excelColumnByIndex(count($detailHeaders) - 1);
            $detailSheet->getStyle("A1:{$lastDetailColumn}1")->applyFromArray($this->excelTableHeaderStyle());
            $detailSheet->setAutoFilter("A1:{$lastDetailColumn}1");

            foreach ($detailRows as $index => $item) {
                $detailRow++;
                $values = [
                    (string) ($index + 1),
                    (string) ($item['fecha_inicio'] ?? '—'),
                    (string) ($item['form_id'] ?? ''),
                    (string) ($item['hc_number'] ?? ''),
                    (string) ($item['paciente'] ?? ''),
                    (string) ($item['afiliacion'] ?? ''),
                    (string) ($item['afiliacion_categoria'] ?? ''),
                    (string) ($item['sede'] ?? ''),
                    (string) ($item['procedimiento_proyectado'] ?? ''),
                    !empty($item['facturado']) ? 'SI' : 'NO',
                    (string) ($item['estado_facturacion_operativa'] ?? ''),
                    !empty($item['pendiente_pago']) ? 'SI' : 'NO',
                    $this->formatBillingSourceLabel((string) ($item['billing_source'] ?? '')),
                    number_format((float) ($item['total_produccion'] ?? 0), 2, '.', ''),
                    (string) ($item['procedimientos_facturados'] ?? 0),
                    (string) ($item['billing_id'] ?? ''),
                    (string) ($item['fecha_facturacion'] ?? '—'),
                ];

                foreach ($values as $idx => $value) {
                    $column = $this->excelColumnByIndex($idx);
                    $detailSheet->setCellValueExplicit("{$column}{$detailRow}", $value, DataType::TYPE_STRING);
                }
            }

            if ($detailRow > 1) {
                $detailSheet->getStyle("A1:{$lastDetailColumn}{$detailRow}")->applyFromArray($this->excelTableBodyStyle());
                $detailSheet->getStyle("I2:I{$detailRow}")->getAlignment()->setWrapText(true);
            }

            $detailSheet->freezePane('A2');
            foreach ([
                'A' => 6, 'B' => 18, 'C' => 14, 'D' => 14, 'E' => 34, 'F' => 24, 'G' => 18, 'H' => 14,
                'I' => 56, 'J' => 12, 'K' => 24, 'L' => 14, 'M' => 16, 'N' => 16, 'O' => 14, 'P' => 14,
                'Q' => 18,
            ] as $column => $width) {
                $detailSheet->getColumnDimension($column)->setWidth($width);
            }

            $writer = new Xlsx($spreadsheet);
            $stream = fopen('php://temp', 'r+');
            $writer->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream) ?: '';
            fclose($stream);
            $spreadsheet->disconnectWorksheets();

            if ($content === '' || strncmp($content, 'PK', 2) !== 0) {
                return response()->json(['error' => 'No se pudo generar el Excel (contenido inválido).'], 500);
            }

            return response((string) $content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($content),
                'X-Content-Type-Options' => 'nosniff',
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
    private function buildDashboardExportPayload(
        array $dateRange,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array {
        $data = $this->buildDashboardPayload($dateRange, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $filtersSummary = $this->buildDashboardFiltersSummary(
            $data['date_range'],
            $afiliacionFilter,
            $data['afiliacion_options'],
            $afiliacionCategoriaFilter,
            $data['afiliacion_categoria_options'],
            $sedeFilter,
            $data['sede_options']
        );

        $startSql = $dateRange['start']->format('Y-m-d 00:00:00');
        $endSql = $dateRange['end']->format('Y-m-d 23:59:59');
        $detailRows = $this->dashboardService->getCirugiasFacturacionDetalle(
            $startSql,
            $endSql,
            $afiliacionFilter,
            $afiliacionCategoriaFilter,
            $sedeFilter
        );

        return [
            'data' => $data,
            'filtersSummary' => $filtersSummary,
            'detailRows' => $detailRows,
            'report' => $this->buildDashboardExportReport($data, $filtersSummary, $detailRows),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,array{label:string,value:string}> $filtersSummary
     * @param array<int,array<string,mixed>> $detailRows
     * @return array<string,mixed>
     */
    private function buildDashboardExportReport(array $data, array $filtersSummary, array $detailRows): array
    {
        $cards = is_array($data['kpi_cards'] ?? null) ? $data['kpi_cards'] : [];
        $facturacion = is_array($data['facturacion_trazabilidad'] ?? null) ? $data['facturacion_trazabilidad'] : [];
        $programacion = is_array($data['programacion_kpis'] ?? null) ? $data['programacion_kpis'] : [];
        $topProcedimientos = is_array($data['top_procedimientos'] ?? null) ? $data['top_procedimientos'] : ['labels' => [], 'totals' => []];
        $topCirujanos = is_array($data['top_cirujanos'] ?? null) ? $data['top_cirujanos'] : ['labels' => [], 'totals' => []];
        $cirugiasPorConvenio = is_array($data['cirugias_por_convenio'] ?? null) ? $data['cirugias_por_convenio'] : ['labels' => [], 'totals' => []];

        $atendidos = (int) ($facturacion['atendidos'] ?? 0);
        $facturados = (int) ($facturacion['facturados'] ?? 0);
        $pendienteFacturar = (int) ($facturacion['pendiente_facturar'] ?? 0);
        $pendientePago = (int) ($facturacion['pendiente_pago'] ?? 0);
        $cancelados = (int) ($facturacion['cancelados'] ?? 0);
        $produccionFacturada = (float) ($facturacion['produccion_facturada'] ?? 0);
        $produccionFacturadaPublico = (float) ($facturacion['produccion_facturada_publico'] ?? 0);
        $produccionFacturadaPrivado = (float) ($facturacion['produccion_facturada_privado'] ?? 0);
        $facturadosPublico = (int) ($facturacion['facturados_publico'] ?? 0);
        $facturadosPrivado = (int) ($facturacion['facturados_privado'] ?? 0);
        $pendientesFacturarPublico = (int) ($facturacion['pendientes_facturar_publico'] ?? 0);
        $pendientesFacturarPrivado = (int) ($facturacion['pendientes_facturar_privado'] ?? 0);
        $ticketPromedioFacturado = (float) ($facturacion['ticket_promedio_facturado'] ?? 0);
        $procedimientosFacturados = (int) ($facturacion['procedimientos_facturados'] ?? 0);
        $rangeLabel = trim((string) ($data['date_range']['label'] ?? ''));
        $cumplimiento = trim((string) ($this->reportCardText($cards, 'Cumplimiento programación') ?? '0.00%'));
        $tasaSuspendidas = trim((string) ($this->reportCardText($cards, 'Tasa de suspendidas') ?? '0.00%'));

        $hallazgos = [];
        $hallazgos[] = sprintf(
            'Se registran %s cirugías atendidas en el periodo; %s ya están facturadas y %s permanecen pendientes de facturar.',
            number_format($atendidos),
            number_format($facturados),
            number_format($pendienteFacturar)
        );
        $hallazgos[] = sprintf(
            'La producción facturada real asciende a $%s, con $%s en públicas y $%s en privadas.',
            number_format($produccionFacturada, 2),
            number_format($produccionFacturadaPublico, 2),
            number_format($produccionFacturadaPrivado, 2)
        );
        if ($pendientePago > 0) {
            $hallazgos[] = sprintf(
                'Existen %s cirugías con factura emitida pero aún en estado de cartera/pendiente/crédito.',
                number_format($pendientePago)
            );
        }
        if ($cancelados > 0) {
            $hallazgos[] = sprintf(
                'Programación reporta %s canceladas/suspendidas en el rango, con una tasa de suspendidas de %s.',
                number_format($cancelados),
                $tasaSuspendidas !== '' ? $tasaSuspendidas : '0.00%'
            );
        }

        $methodology = [
            'El universo considera protocolos quirúrgicos registrados en `protocolo_data` dentro del rango filtrado.',
            'Atendido equivale a cirugía con protocolo en el periodo; cancelado proviene de programación quirúrgica.',
            'La facturación consolida evidencia por `form_id` combinando `billing_facturacion_real` y, para públicas, `billing_main` + `billing_procedimientos`.',
            'Pendiente de facturar corresponde a protocolos atendidos sin evidencia de billing consolidada.',
            'Los montos económicos se expresan en dólares y reflejan únicamente la producción facturada consolidada.',
        ];

        $generalKpis = [
            ['label' => 'Cirugías en el periodo', 'value' => $this->reportCardText($cards, 'Cirugías en el periodo') ?? '0', 'note' => $this->reportCardText($cards, 'Cirugías en el periodo', 'hint') ?? ''],
            ['label' => 'Atendidos', 'value' => $this->reportCardText($cards, 'Atendidos') ?? '0', 'note' => $this->reportCardText($cards, 'Atendidos', 'hint') ?? ''],
            ['label' => 'Facturados', 'value' => $this->reportCardText($cards, 'Facturados') ?? '0', 'note' => $this->reportCardText($cards, 'Facturados', 'hint') ?? ''],
            ['label' => 'Pendiente de facturar', 'value' => $this->reportCardText($cards, 'Pendiente de facturar') ?? '0', 'note' => $this->reportCardText($cards, 'Pendiente de facturar', 'hint') ?? ''],
            ['label' => 'Pendiente de pago', 'value' => $this->reportCardText($cards, 'Pendiente de pago') ?? '0', 'note' => $this->reportCardText($cards, 'Pendiente de pago', 'hint') ?? ''],
            ['label' => 'Cancelados', 'value' => $this->reportCardText($cards, 'Cancelados') ?? '0', 'note' => $this->reportCardText($cards, 'Cancelados', 'hint') ?? ''],
        ];

        $temporalKpis = [
            ['label' => 'Duración promedio', 'value' => $this->reportCardText($cards, 'Duración promedio') ?? '—', 'note' => $this->reportCardText($cards, 'Duración promedio', 'hint') ?? ''],
            ['label' => 'Cumplimiento programación', 'value' => $cumplimiento !== '' ? $cumplimiento : '0.00%', 'note' => $this->reportCardText($cards, 'Cumplimiento programación', 'hint') ?? ''],
            ['label' => 'Tasa de suspendidas', 'value' => $tasaSuspendidas !== '' ? $tasaSuspendidas : '0.00%', 'note' => $this->reportCardText($cards, 'Tasa de suspendidas', 'hint') ?? ''],
            ['label' => 'Tasa de reprogramación', 'value' => $this->reportCardText($cards, 'Tasa de reprogramación') ?? '0.00%', 'note' => $this->reportCardText($cards, 'Tasa de reprogramación', 'hint') ?? ''],
            ['label' => 'TAT revisión promedio', 'value' => $this->reportCardText($cards, 'TAT revisión promedio') ?? '—', 'note' => $this->reportCardText($cards, 'TAT revisión promedio', 'hint') ?? ''],
            ['label' => 'TAT revisión P90', 'value' => $this->reportCardText($cards, 'TAT revisión P90') ?? '—', 'note' => $this->reportCardText($cards, 'TAT revisión P90', 'hint') ?? ''],
        ];

        $economicKpis = [
            [
                'label' => 'Producción facturada',
                'value' => $this->reportCardText($cards, 'Producción facturada') ?? '$0.00',
                'meaning' => 'Monto real facturado consolidado para cirugías del periodo.',
                'formula' => 'SUM(total_produccion) de la fuente de billing priorizada por cirugía.',
            ],
            [
                'label' => 'Facturación pública',
                'value' => $this->reportCardText($cards, 'Facturación pública') ?? '$0.00',
                'meaning' => 'Producción real facturada en afiliaciones públicas.',
                'formula' => 'SUM(total_produccion) donde afiliacion_categoria = publico.',
            ],
            [
                'label' => 'Facturación privada',
                'value' => $this->reportCardText($cards, 'Facturación privada') ?? '$0.00',
                'meaning' => 'Producción real facturada en afiliaciones privadas.',
                'formula' => 'SUM(total_produccion) donde afiliacion_categoria = privado.',
            ],
            [
                'label' => 'Ticket promedio facturado',
                'value' => $this->reportCardText($cards, 'Ticket promedio facturado') ?? '$0.00',
                'meaning' => 'Ingreso promedio por cirugía facturada.',
                'formula' => 'Producción facturada / cirugías facturadas.',
            ],
            [
                'label' => 'Pendiente facturar pública',
                'value' => number_format($pendientesFacturarPublico),
                'meaning' => 'Casos públicos atendidos sin cierre de billing.',
                'formula' => 'Protocolos atendidos públicos sin evidencia de factura consolidada.',
            ],
            [
                'label' => 'Pendiente facturar privada',
                'value' => number_format($pendientesFacturarPrivado),
                'meaning' => 'Casos privados atendidos sin cierre de billing.',
                'formula' => 'Protocolos atendidos privados sin evidencia de factura consolidada.',
            ],
        ];

        $tables = [
            [
                'title' => 'Backlog de facturación por categoría',
                'subtitle' => 'Separación entre cirugías ya facturadas y backlog pendiente.',
                'columns' => ['Categoría', 'Facturados', 'Pendiente facturar', 'Producción facturada'],
                'rows' => [
                    ['Pública', number_format($facturadosPublico), number_format($pendientesFacturarPublico), '$' . number_format($produccionFacturadaPublico, 2)],
                    ['Privada', number_format($facturadosPrivado), number_format($pendientesFacturarPrivado), '$' . number_format($produccionFacturadaPrivado, 2)],
                ],
                'empty_message' => 'Sin backlog de facturación para el rango seleccionado.',
            ],
            [
                'title' => 'Rendimiento económico',
                'subtitle' => 'Vista ejecutiva de producción y ticket quirúrgico.',
                'columns' => ['Métrica', 'Valor'],
                'rows' => [
                    ['Producción facturada real', '$' . number_format($produccionFacturada, 2)],
                    ['Ticket promedio facturado', '$' . number_format($ticketPromedioFacturado, 2)],
                    ['Procedimientos facturados', number_format($procedimientosFacturados)],
                    ['Pendiente de pago', number_format($pendientePago)],
                ],
                'empty_message' => 'Sin datos económicos para el rango seleccionado.',
            ],
            [
                'title' => 'Top procedimientos',
                'subtitle' => 'Procedimientos con mayor volumen quirúrgico en el rango.',
                'columns' => ['Procedimiento', 'Cirugías'],
                'rows' => $this->buildExportChartRows($topProcedimientos),
                'empty_message' => 'Sin procedimientos para el rango seleccionado.',
            ],
            [
                'title' => 'Top cirujanos',
                'subtitle' => 'Cirujanos con más cirugías realizadas.',
                'columns' => ['Cirujano', 'Cirugías'],
                'rows' => $this->buildExportChartRows($topCirujanos),
                'empty_message' => 'Sin cirujanos para el rango seleccionado.',
            ],
            [
                'title' => 'Cirugías por convenio',
                'subtitle' => 'Distribución del volumen por afiliación/convenio.',
                'columns' => ['Convenio', 'Cirugías'],
                'rows' => $this->buildExportChartRows($cirugiasPorConvenio),
                'empty_message' => 'Sin convenios para el rango seleccionado.',
            ],
        ];

        return [
            'scopeNotice' => 'Este reporte consolida actividad quirúrgica, trazabilidad de facturación y producción real en USD para el periodo seleccionado.',
            'filtersSummary' => $filtersSummary,
            'hallazgosClave' => $hallazgos,
            'methodology' => $methodology,
            'generalKpis' => $generalKpis,
            'temporalKpis' => $temporalKpis,
            'economicKpis' => $economicKpis,
            'tables' => $tables,
            'totalAtenciones' => count($detailRows),
            'rangeLabel' => $rangeLabel,
            'programadas' => (int) ($programacion['programadas'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $chart
     * @return array<int,array<int,string>>
     */
    private function buildExportChartRows(array $chart): array
    {
        $labels = array_values(array_map(static fn($value): string => trim((string) $value), is_array($chart['labels'] ?? null) ? $chart['labels'] : []));
        $totals = array_values(is_array($chart['totals'] ?? null) ? $chart['totals'] : []);
        $rows = [];

        foreach ($labels as $index => $label) {
            if ($label === '') {
                continue;
            }

            $rows[] = [
                $label,
                number_format((float) ($totals[$index] ?? 0)),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>
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
        $facturacionTrazabilidad = $this->dashboardService->getCirugiasFacturacionTrazabilidad($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $sinFacturar = (int) ($facturacionTrazabilidad['pendiente_facturar'] ?? 0);
        $duracionPromedioRaw = $this->dashboardService->getDuracionPromedioMinutos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $duracionPromedio = $this->formatMinutes($duracionPromedioRaw);
        $estadoProtocolos = $this->dashboardService->getEstadoProtocolos($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $cirugiasPorMes = $this->dashboardService->getCirugiasPorMes($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $topProcedimientos = $this->dashboardService->getTopProcedimientos($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $topCirujanos = $this->dashboardService->getTopCirujanos($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $topDoctoresSolicitudesRealizadas = $this->dashboardService->getTopDoctoresSolicitudesRealizadas($startSql, $endSql, 10, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $cirugiasPorConvenio = $this->dashboardService->getCirugiasPorConvenio($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $programacionKpis = $this->dashboardService->getProgramacionKpis($startSql, $endSql, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
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
        $pendienteFacturar = (int) ($facturacionTrazabilidad['pendiente_facturar'] ?? $sinFacturar);
        $pendientePago = (int) ($facturacionTrazabilidad['pendiente_pago'] ?? 0);
        $cancelados = (int) ($facturacionTrazabilidad['cancelados'] ?? 0);
        $facturacionCancelada = (int) ($facturacionTrazabilidad['facturacion_cancelada'] ?? 0);
        $produccionFacturada = (float) ($facturacionTrazabilidad['produccion_facturada'] ?? 0);
        $produccionFacturadaPublico = (float) ($facturacionTrazabilidad['produccion_facturada_publico'] ?? 0);
        $produccionFacturadaPrivado = (float) ($facturacionTrazabilidad['produccion_facturada_privado'] ?? 0);
        $facturadosPublico = (int) ($facturacionTrazabilidad['facturados_publico'] ?? 0);
        $facturadosPrivado = (int) ($facturacionTrazabilidad['facturados_privado'] ?? 0);
        $pendientesFacturarPublico = (int) ($facturacionTrazabilidad['pendientes_facturar_publico'] ?? 0);
        $pendientesFacturarPrivado = (int) ($facturacionTrazabilidad['pendientes_facturar_privado'] ?? 0);
        $procedimientosFacturados = (int) ($facturacionTrazabilidad['procedimientos_facturados'] ?? 0);
        $ticketPromedioFacturado = (float) ($facturacionTrazabilidad['ticket_promedio_facturado'] ?? 0);
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
            ['label' => 'Pendiente de facturar', 'value' => (string) $pendienteFacturar, 'hint' => $pendienteFacturar > 0 ? ($pendientesFacturarPublico . ' públicas / ' . $pendientesFacturarPrivado . ' privadas') : 'Sin backlog por facturar'],
            ['label' => 'Pendiente de pago', 'value' => (string) $pendientePago, 'hint' => 'Facturas emitidas con estado pendiente/cartera/crédito'],
            ['label' => 'Cancelados', 'value' => (string) $cancelados, 'hint' => 'Solicitudes suspendidas/canceladas en programación'],
            ['label' => 'Producción facturada', 'value' => $this->formatCurrency($produccionFacturada), 'hint' => $procedimientosFacturados > 0 ? ($procedimientosFacturados . ' procedimientos con billing validado') : 'Sin producción facturada'],
            ['label' => 'Facturación pública', 'value' => $this->formatCurrency($produccionFacturadaPublico), 'hint' => $facturadosPublico > 0 ? ($facturadosPublico . ' cirugías facturadas') : 'Sin cirugías públicas facturadas'],
            ['label' => 'Facturación privada', 'value' => $this->formatCurrency($produccionFacturadaPrivado), 'hint' => $facturadosPrivado > 0 ? ($facturadosPrivado . ' cirugías facturadas') : 'Sin cirugías privadas facturadas'],
            ['label' => 'Ticket promedio facturado', 'value' => $this->formatCurrency($ticketPromedioFacturado), 'hint' => $facturados > 0 ? ('Promedio por cirugía facturada; ' . $facturacionCancelada . ' facturas anuladas/canceladas') : 'Sin facturación validada'],
            ['label' => 'Protocolos revisados', 'value' => (string) ((int) ($estadoProtocolos['revisado'] ?? 0)), 'hint' => 'Protocolos completos y validados'],
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

    private function formatCurrency(float $value): string
    {
        return '$' . number_format($value, 2);
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
     * @param array<int,array<string,mixed>> $cards
     */
    private function reportCardText(array $cards, string $label, string $field = 'value'): ?string
    {
        foreach ($cards as $card) {
            if (trim((string) ($card['label'] ?? '')) !== $label) {
                continue;
            }

            $value = trim((string) ($card[$field] ?? ''));
            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function writeExcelMergedTitle(Worksheet $sheet, int $row, string $title, string $lastColumn = 'G'): void
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

    private function writeExcelSectionHeader(Worksheet $sheet, int $row, string $title, string $lastColumn = 'G'): int
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
                $sheet->setCellValueExplicit(
                    "{$column}{$currentRow}",
                    (string) ($dataRow[$index] ?? ''),
                    DataType::TYPE_STRING
                );
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

    private function formatBillingSourceLabel(string $source): string
    {
        return match (trim($source)) {
            'real' => 'Billing real',
            'public' => 'Billing público',
            default => '',
        };
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
