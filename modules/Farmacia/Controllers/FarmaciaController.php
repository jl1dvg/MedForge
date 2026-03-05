<?php

namespace Modules\Farmacia\Controllers;

use Core\BaseController;
use DateTimeImmutable;
use Helpers\JsonLogger;
use Models\RecetaModel;
use Modules\Reporting\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;
use Throwable;

class FarmaciaController extends BaseController
{
    private RecetaModel $recetaModel;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->recetaModel = new RecetaModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $payload = $this->buildDashboardPayload();

        $data = [
            'pageTitle' => 'Estadísticas de Farmacia',
            'filters' => $payload['filters'],
            'dashboard' => $payload['dashboard'],
            'rows' => $payload['detail_rows'],
            'doctorOptions' => $payload['doctor_options'],
            'afiliacionOptions' => $payload['afiliacion_options'],
            'sedeOptions' => $payload['sede_options'],
            'estadoOptions' => $payload['estado_options'],
            'viaOptions' => $payload['via_options'],
            'localidadOptions' => $payload['localidad_options'],
            'departamentoOptions' => $payload['departamento_options'],
            'scripts' => [
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                'js/pages/farmacia-dashboard.js',
            ],
        ];

        $this->render(__DIR__ . '/../views/index.php', $data);
    }

    public function exportPdf(): void
    {
        $this->requireAuth();

        $payload = $this->buildDashboardPayload();
        $filtersSummary = $this->buildDashboardFiltersSummary(
            $payload['filters'],
            $payload['doctor_options'],
            $payload['afiliacion_options'],
            $payload['estado_options'],
            $payload['via_options'],
            $payload['sede_options']
        );
        $filename = 'dashboard_farmacia_' . date('Ymd_His') . '.pdf';

        try {
            $reportService = new ReportService();
            $pdf = $reportService->renderPdf('farmacia_dashboard', [
                'titulo' => 'Dashboard de KPIs de recetas',
                'generatedAt' => (new DateTimeImmutable('now'))->format('d-m-Y H:i'),
                'filters' => $filtersSummary,
                'cards' => is_array($payload['dashboard']['cards'] ?? null) ? $payload['dashboard']['cards'] : [],
                'meta' => is_array($payload['dashboard']['meta'] ?? null) ? $payload['dashboard']['meta'] : [],
                'rows' => $payload['detail_rows'],
                'total' => count($payload['detail_rows']),
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
            return;
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'farmacia_dashboard_export',
                'Error exportando PDF del dashboard de recetas',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->currentUserId(),
                ]
            );

            $this->json(['error' => 'No se pudo generar el PDF (ref: ' . $errorId . ')'], 500);
        }
    }

    public function exportExcel(): void
    {
        $this->requireAuth();

        $payload = $this->buildDashboardPayload();
        $filtersSummary = $this->buildDashboardFiltersSummary(
            $payload['filters'],
            $payload['doctor_options'],
            $payload['afiliacion_options'],
            $payload['estado_options'],
            $payload['via_options'],
            $payload['sede_options']
        );
        $filename = 'dashboard_farmacia_' . date('Ymd_His') . '.xlsx';

        try {
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
            $sheet->setCellValueExplicit("E{$row}", (string)count($payload['detail_rows']), DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("D{$row}")->getFont()->setBold(true);

            $row += 2;
            $sheet->setCellValue("A{$row}", 'Filtros aplicados');
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            if (empty($filtersSummary)) {
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

            $cards = is_array($payload['dashboard']['cards'] ?? null) ? $payload['dashboard']['cards'] : [];
            foreach ($cards as $card) {
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
                'Localidad',
                'Departamento',
                'Médico',
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

            foreach ($payload['detail_rows'] as $index => $item) {
                $detailRow++;
                $values = [
                    (string)($index + 1),
                    (string)($item['fecha_receta'] ?? '—'),
                    (string)($item['localidad'] ?? ''),
                    (string)($item['departamento'] ?? ''),
                    (string)($item['doctor'] ?? ''),
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
                'A' => 6,
                'B' => 18,
                'C' => 14,
                'D' => 14,
                'E' => 28,
                'F' => 28,
                'G' => 16,
                'H' => 16,
                'I' => 48,
                'J' => 34,
                'K' => 18,
                'L' => 11,
                'M' => 14,
                'N' => 16,
                'O' => 13,
                'P' => 32,
                'Q' => 44,
            ] as $column => $width) {
                $detailSheet->getColumnDimension($column)->setWidth($width);
            }

            if ($detailRow > 1) {
                foreach (['I', 'P', 'Q'] as $wrapColumn) {
                    $detailSheet->getStyle("{$wrapColumn}2:{$wrapColumn}{$detailRow}")
                        ->getAlignment()
                        ->setWrapText(true);
                }
            }

            $writer = new Xlsx($spreadsheet);
            $stream = fopen('php://temp', 'r+');
            $writer->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream) ?: '';
            fclose($stream);
            $spreadsheet->disconnectWorksheets();

            if ($content === '' || strncmp($content, 'PK', 2) !== 0) {
                $this->json(['error' => 'No se pudo generar el Excel (contenido inválido).'], 500);
                return;
            }

            if (!headers_sent()) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($content));
                header('X-Content-Type-Options: nosniff');
            }

            echo $content;
            return;
        } catch (Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            JsonLogger::log(
                'farmacia_dashboard_export',
                'Error exportando Excel del dashboard de recetas',
                $e,
                [
                    'error_id' => $errorId,
                    'user_id' => $this->currentUserId(),
                ]
            );

            $this->json(['error' => 'No se pudo generar el Excel (ref: ' . $errorId . ')'], 500);
        }
    }

    /**
     * @return array{
     *     filters: array<string, string>,
     *     dashboard: array<string, mixed>,
     *     detail_rows: array<int, array<string, mixed>>,
     *     doctor_options: array<int, string>,
     *     afiliacion_options: array<int, string>,
     *     sede_options: array<int, string>,
     *     estado_options: array<int, string>,
     *     via_options: array<int, string>,
     *     localidad_options: array<int, string>,
     *     departamento_options: array<int, string>
     * }
     */
    private function buildDashboardPayload(): array
    {
        $filters = $this->resolveDashboardFilters();
        $rows = $this->recetaModel->obtenerDashboardRows($filters);
        $detailRows = $this->buildDashboardDetailRows($rows);
        $dashboard = $this->buildDashboardSummary($rows, $filters);
        $dateFilter = [
            'fecha_inicio' => $filters['fecha_inicio'],
            'fecha_fin' => $filters['fecha_fin'],
        ];

        return [
            'filters' => $filters,
            'dashboard' => $dashboard,
            'detail_rows' => $detailRows,
            'doctor_options' => $this->recetaModel->listarDoctores($dateFilter),
            'afiliacion_options' => $this->recetaModel->listarAfiliaciones($dateFilter),
            'sede_options' => $this->recetaModel->listarSedes($dateFilter),
            'estado_options' => $this->recetaModel->listarEstadosReceta($dateFilter),
            'via_options' => $this->recetaModel->listarVias($dateFilter),
            'localidad_options' => $this->recetaModel->listarLocalidades($dateFilter),
            'departamento_options' => $this->recetaModel->listarDepartamentos($dateFilter),
        ];
    }

    /**
     * @return array{
     *     fecha_inicio: string,
     *     fecha_fin: string,
     *     doctor: string,
     *     afiliacion: string,
     *     estado_receta: string,
     *     via: string,
     *     producto: string,
     *     sede: string,
     *     localidad: string,
     *     departamento: string
     * }
     */
    private function resolveDashboardFilters(): array
    {
        $today = new DateTimeImmutable('today');
        $defaultEnd = $today->format('Y-m-d');
        $defaultStart = $today->modify('-29 days')->format('Y-m-d');

        $fechaInicio = $this->normalizeDateFilter((string)($_GET['fecha_inicio'] ?? ''), $defaultStart);
        $fechaFin = $this->normalizeDateFilter((string)($_GET['fecha_fin'] ?? ''), $defaultEnd);

        if ($fechaFin < $fechaInicio) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'doctor' => $this->normalizeTextFilter((string)($_GET['doctor'] ?? ''), 120),
            'afiliacion' => $this->normalizeTextFilter((string)($_GET['afiliacion'] ?? ''), 120),
            'estado_receta' => $this->normalizeTextFilter((string)($_GET['estado_receta'] ?? ''), 120),
            'via' => $this->normalizeTextFilter((string)($_GET['via'] ?? ''), 120),
            'producto' => $this->normalizeTextFilter((string)($_GET['producto'] ?? ''), 120),
            'sede' => $this->normalizeSedeFilter((string)($_GET['sede'] ?? '')),
            'localidad' => $this->normalizeTextFilter((string)($_GET['localidad'] ?? ''), 120),
            'departamento' => $this->normalizeTextFilter((string)($_GET['departamento'] ?? ''), 120),
        ];
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

    private function normalizeDateFilter(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return (new DateTimeImmutable($fallback))->format('Y-m-d');
    }

    private function normalizeTextFilter(string $value, int $maxLength = 120): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, $maxLength);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array{fecha_inicio:string,fecha_fin:string} $filters
     * @return array<string, mixed>
     */
    private function buildDashboardSummary(array $rows, array $filters): array
    {
        $totalItems = count($rows);
        $episodios = [];
        $pacientes = [];
        $medicos = [];
        $productos = [];

        $totalCantidad = 0;
        $totalFarmacia = 0;
        $sinDespacho = 0;
        $parcialDespacho = 0;
        $completoDespacho = 0;
        $sobreDespacho = 0;

        $tatHoras = [];
        $sla24Total = 0;
        $sla24Cumple = 0;

        $serieDiaria = [];
        $estadoMap = [];
        $viaMap = [];
        $afiliacionMap = [];
        $doctorMap = [];
        $productoMap = [];
        $localidadMap = [];
        $departamentoMap = [];

        foreach ($rows as $row) {
            $formId = trim((string)($row['form_id'] ?? ''));
            if ($formId !== '') {
                $episodios[$formId] = true;
            }

            $hcNumber = trim((string)($row['hc_number'] ?? ''));
            if ($hcNumber !== '') {
                $pacientes[$hcNumber] = true;
            }

            $doctor = trim((string)($row['doctor'] ?? 'Sin médico'));
            if ($doctor !== '') {
                if ($doctor !== 'Sin médico') {
                    $medicos[$doctor] = true;
                }
                $doctorMap[$doctor] = (int)($doctorMap[$doctor] ?? 0) + 1;
            }

            $producto = trim((string)($row['producto'] ?? 'Sin producto'));
            if ($producto !== '') {
                if ($producto !== 'Sin producto') {
                    $productos[$producto] = true;
                }
                $productoMap[$producto] = (int)($productoMap[$producto] ?? 0) + 1;
            }

            $estado = trim((string)($row['estado_receta'] ?? 'Sin estado'));
            $via = trim((string)($row['via'] ?? 'Sin vía'));
            $afiliacion = trim((string)($row['afiliacion'] ?? 'Sin afiliación'));
            $localidad = trim((string)($row['localidad'] ?? 'Sin localidad'));
            $departamento = trim((string)($row['departamento'] ?? 'Sin departamento'));

            $estadoMap[$estado] = (int)($estadoMap[$estado] ?? 0) + 1;
            $viaMap[$via] = (int)($viaMap[$via] ?? 0) + 1;
            $afiliacionMap[$afiliacion] = (int)($afiliacionMap[$afiliacion] ?? 0) + 1;
            $localidadMap[$localidad] = (int)($localidadMap[$localidad] ?? 0) + 1;
            $departamentoMap[$departamento] = (int)($departamentoMap[$departamento] ?? 0) + 1;

            $cantidad = max(0, (int)($row['cantidad'] ?? 0));
            $farmacia = max(0, (int)($row['total_farmacia'] ?? 0));
            $totalCantidad += $cantidad;
            $totalFarmacia += $farmacia;

            if ($farmacia <= 0) {
                $sinDespacho++;
            } elseif ($cantidad > 0 && $farmacia < $cantidad) {
                $parcialDespacho++;
            } elseif ($cantidad > 0 && $farmacia === $cantidad) {
                $completoDespacho++;
            } elseif ($cantidad > 0 && $farmacia > $cantidad) {
                $sobreDespacho++;
            } else {
                $completoDespacho++;
            }

            $dateKey = $this->extractDateKey((string)($row['fecha_receta'] ?? ''));
            if ($dateKey !== '') {
                if (!isset($serieDiaria[$dateKey])) {
                    $serieDiaria[$dateKey] = [
                        'recetas' => 0,
                        'cantidad' => 0,
                        'farmacia' => 0,
                    ];
                }

                $serieDiaria[$dateKey]['recetas']++;
                $serieDiaria[$dateKey]['cantidad'] += $cantidad;
                $serieDiaria[$dateKey]['farmacia'] += $farmacia;
            }

            $createdTs = strtotime((string)($row['fecha_receta'] ?? ''));
            $updatedTs = strtotime((string)($row['fecha_actualizacion'] ?? ''));
            if ($farmacia > 0 && $createdTs !== false && $updatedTs !== false && $updatedTs >= $createdTs) {
                $tat = ($updatedTs - $createdTs) / 3600;
                $tatHoras[] = $tat;
                $sla24Total++;
                if ($tat <= 24) {
                    $sla24Cumple++;
                }
            }
        }

        $rangeStart = DateTimeImmutable::createFromFormat('Y-m-d', (string)$filters['fecha_inicio']);
        $rangeEnd = DateTimeImmutable::createFromFormat('Y-m-d', (string)$filters['fecha_fin']);
        if ($rangeStart instanceof DateTimeImmutable && $rangeEnd instanceof DateTimeImmutable && $rangeStart <= $rangeEnd) {
            $days = (int)$rangeStart->diff($rangeEnd)->days;
            if ($days <= 120) {
                for ($cursor = $rangeStart; $cursor <= $rangeEnd; $cursor = $cursor->modify('+1 day')) {
                    $dateKey = $cursor->format('Y-m-d');
                    if (!isset($serieDiaria[$dateKey])) {
                        $serieDiaria[$dateKey] = [
                            'recetas' => 0,
                            'cantidad' => 0,
                            'farmacia' => 0,
                        ];
                    }
                }
            }
        }
        ksort($serieDiaria);

        arsort($productoMap);
        arsort($doctorMap);
        arsort($estadoMap);
        arsort($viaMap);
        arsort($afiliacionMap);
        arsort($localidadMap);
        arsort($departamentoMap);

        $topProductos = array_slice($productoMap, 0, 8, true);
        $topDoctores = array_slice($doctorMap, 0, 8, true);

        $episodiosTotal = count($episodios);
        $pacientesTotal = count($pacientes);
        $medicosActivos = count($medicos);
        $productosDistintos = count($productos);
        $promedioItemsPorEpisodio = $episodiosTotal > 0 ? ($totalItems / $episodiosTotal) : 0.0;
        $coberturaDespacho = $totalCantidad > 0 ? (($totalFarmacia * 100) / $totalCantidad) : null;
        $sinDespachoPct = $totalItems > 0 ? (($sinDespacho * 100) / $totalItems) : null;
        $parcialDespachoPct = $totalItems > 0 ? (($parcialDespacho * 100) / $totalItems) : null;
        $sla24Pct = $sla24Total > 0 ? (($sla24Cumple * 100) / $sla24Total) : null;

        $tatPromedio = !empty($tatHoras) ? array_sum($tatHoras) / count($tatHoras) : null;
        $tatMediana = $this->calcularPercentil($tatHoras, 0.50);
        $tatP90 = $this->calcularPercentil($tatHoras, 0.90);

        $diaPico = '—';
        $diaPicoTotal = 0;
        foreach ($serieDiaria as $dateKey => $dayValues) {
            $recetasDia = (int)($dayValues['recetas'] ?? 0);
            if ($recetasDia > $diaPicoTotal) {
                $diaPicoTotal = $recetasDia;
                $diaPico = $this->formatShortDate($dateKey);
            }
        }

        return [
            'cards' => [
                [
                    'label' => 'Ítems de recetas',
                    'value' => $totalItems,
                    'hint' => 'Líneas de medicación emitidas en el rango',
                ],
                [
                    'label' => 'Episodios con receta',
                    'value' => $episodiosTotal,
                    'hint' => 'Formularios únicos con medicación',
                ],
                [
                    'label' => 'Pacientes únicos',
                    'value' => $pacientesTotal,
                    'hint' => 'HC distintas con prescripción',
                ],
                [
                    'label' => 'Médicos activos',
                    'value' => $medicosActivos,
                    'hint' => 'Profesionales que recetaron en el periodo',
                ],
                [
                    'label' => 'Productos distintos',
                    'value' => $productosDistintos,
                    'hint' => 'Variedad de fármacos prescritos',
                ],
                [
                    'label' => 'Unidades prescritas',
                    'value' => $totalCantidad,
                    'hint' => 'Suma de cantidad solicitada',
                ],
                [
                    'label' => 'Ítems por episodio',
                    'value' => number_format($promedioItemsPorEpisodio, 2),
                    'hint' => 'Promedio de líneas por formulario',
                ],
                [
                    'label' => 'Día pico',
                    'value' => $diaPico,
                    'hint' => $diaPicoTotal > 0 ? ($diaPicoTotal . ' ítems de receta') : 'Sin actividad',
                ],
            ],
            'meta' => [
                'tat_promedio_horas' => $tatPromedio !== null ? round($tatPromedio, 2) : null,
                'tat_mediana_horas' => $tatMediana !== null ? round($tatMediana, 2) : null,
                'tat_p90_horas' => $tatP90 !== null ? round($tatP90, 2) : null,
                'sla_24h_pct' => $sla24Pct !== null ? round($sla24Pct, 2) : null,
            ],
            'charts' => [
                'serie_diaria' => [
                    'labels' => array_keys($serieDiaria),
                    'recetas' => array_values(array_map(static fn(array $item): int => (int)($item['recetas'] ?? 0), $serieDiaria)),
                    'cantidad' => array_values(array_map(static fn(array $item): int => (int)($item['cantidad'] ?? 0), $serieDiaria)),
                    'farmacia' => array_values(array_map(static fn(array $item): int => (int)($item['farmacia'] ?? 0), $serieDiaria)),
                ],
                'top_productos' => [
                    'labels' => array_keys($topProductos),
                    'values' => array_values($topProductos),
                ],
                'top_doctores' => [
                    'labels' => array_keys($topDoctores),
                    'values' => array_values($topDoctores),
                ],
                'estado_receta' => [
                    'labels' => array_keys($estadoMap),
                    'values' => array_values($estadoMap),
                ],
                'vias' => [
                    'labels' => array_keys($viaMap),
                    'values' => array_values($viaMap),
                ],
                'afiliacion' => [
                    'labels' => array_keys($afiliacionMap),
                    'values' => array_values($afiliacionMap),
                ],
                'localidad' => [
                    'labels' => array_keys($localidadMap),
                    'values' => array_values($localidadMap),
                ],
                'departamento' => [
                    'labels' => array_keys($departamentoMap),
                    'values' => array_values($departamentoMap),
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildDashboardDetailRows(array $rows): array
    {
        $detail = [];
        foreach ($rows as $row) {
            $cantidad = max(0, (int)($row['cantidad'] ?? 0));
            $farmacia = max(0, (int)($row['total_farmacia'] ?? 0));
            $coverage = $cantidad > 0 ? round(($farmacia * 100) / $cantidad, 1) : null;

            if ($farmacia <= 0) {
                $estadoDespacho = 'Sin despacho';
            } elseif ($cantidad > 0 && $farmacia < $cantidad) {
                $estadoDespacho = 'Parcial';
            } elseif ($cantidad > 0 && $farmacia > $cantidad) {
                $estadoDespacho = 'Sobre despacho';
            } else {
                $estadoDespacho = 'Completo';
            }

            $diagnostico = $this->normalizeDiagnosticoDisplay((string)($row['diagnostico'] ?? ''));
            $edadPaciente = $this->calculatePatientAge(
                (string)($row['fecha_nacimiento'] ?? ''),
                (string)($row['fecha_receta'] ?? '')
            );

            $detail[] = [
                'id' => (int)($row['id'] ?? 0),
                'fecha_receta' => $this->formatDashboardDate((string)($row['fecha_receta'] ?? '')),
                'form_id' => trim((string)($row['form_id'] ?? '')),
                'hc_number' => trim((string)($row['hc_number'] ?? '')),
                'localidad' => trim((string)($row['localidad'] ?? 'Sin localidad')),
                'departamento' => trim((string)($row['departamento'] ?? 'Sin departamento')),
                'doctor' => trim((string)($row['doctor'] ?? '')),
                'afiliacion' => trim((string)($row['afiliacion'] ?? '')),
                'sede' => trim((string)($row['sede'] ?? 'Sin sede')),
                'producto' => trim((string)($row['producto'] ?? '')),
                'via' => trim((string)($row['via'] ?? '')),
                'estado_receta' => trim((string)($row['estado_receta'] ?? '')),
                'cantidad' => $cantidad,
                'total_farmacia' => $farmacia,
                'diagnostico' => $diagnostico,
                'paciente_nombre' => trim((string)($row['paciente_nombre'] ?? 'Sin paciente')),
                'cedula_paciente' => trim((string)($row['cedula_paciente'] ?? '')),
                'edad_paciente' => $edadPaciente !== null ? (string)$edadPaciente : '—',
                'cobertura' => $coverage !== null ? number_format($coverage, 1) . '%' : '—',
                'estado_despacho' => $estadoDespacho,
                'dosis' => trim((string)($row['dosis'] ?? '')),
                'procedimiento_proyectado' => trim((string)($row['procedimiento_proyectado'] ?? '')),
            ];
        }

        return $detail;
    }

    private function formatDashboardDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d-m-Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    private function extractDateKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }

    private function formatShortDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d-m');
        } catch (Throwable) {
            return $value;
        }
    }

    private function normalizeDiagnosticoDisplay(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Sin diagnóstico';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $items = [];
            foreach ($decoded as $item) {
                if (is_array($item)) {
                    $codigo = trim((string)($item['dx_code'] ?? $item['idDiagnostico'] ?? $item['codigo'] ?? ''));
                    $descripcion = trim((string)($item['descripcion'] ?? $item['diagnostico'] ?? ''));
                    if ($codigo !== '' && $descripcion !== '') {
                        $items[] = $codigo . ' - ' . $descripcion;
                        continue;
                    }
                    if ($descripcion !== '') {
                        $items[] = $descripcion;
                        continue;
                    }
                    if ($codigo !== '') {
                        $items[] = $codigo;
                    }
                    continue;
                }

                if (is_string($item)) {
                    $text = trim($item);
                    if (
                        $text !== ''
                        && !preg_match('/motivo\s+de\s+consulta/iu', $text)
                    ) {
                        $items[] = $text;
                    }
                }
            }

            $items = array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
            if (!empty($items)) {
                return implode('; ', $items);
            }
        }

        $normalized = preg_replace('/\s+/', ' ', $value) ?? $value;
        if (preg_match('/diagn[oó]stic(?:o|a)s?\s*[:\-]\s*(.+)$/iu', $normalized, $match)) {
            $normalized = trim((string)($match[1] ?? ''));
        }

        $normalized = preg_replace('/^motivo(?:\s+de)?\s+consulta\s*[:\-]\s*/iu', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'Sin diagnóstico';
    }

    private function calculatePatientAge(string $birthDate, string $referenceDate): ?int
    {
        $birthDate = trim($birthDate);
        if ($birthDate === '' || $birthDate === '0000-00-00') {
            return null;
        }

        try {
            $birth = new DateTimeImmutable($birthDate);
        } catch (Throwable) {
            return null;
        }

        $referenceDate = trim($referenceDate);
        try {
            $reference = $referenceDate !== '' ? new DateTimeImmutable($referenceDate) : new DateTimeImmutable('now');
        } catch (Throwable) {
            $reference = new DateTimeImmutable('now');
        }

        return $birth->diff($reference)->y;
    }

    /**
     * @param array<int, float|int> $values
     */
    private function calcularPercentil(array $values, float $percent): ?float
    {
        if (empty($values)) {
            return null;
        }

        $percent = max(0.0, min(1.0, $percent));
        sort($values, SORT_NUMERIC);
        $n = count($values);
        if ($n === 1) {
            return (float)$values[0];
        }

        $index = ($n - 1) * $percent;
        $lower = (int)floor($index);
        $upper = (int)ceil($index);

        if ($lower === $upper) {
            return (float)$values[$lower];
        }

        $weight = $index - $lower;
        return ((float)$values[$lower] * (1 - $weight)) + ((float)$values[$upper] * $weight);
    }

    /**
     * @param array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     afiliacion:string,
     *     estado_receta:string,
     *     via:string,
     *     producto:string,
     *     sede:string,
     *     localidad:string,
     *     departamento:string
     * } $filters
     * @param array<int, string> $doctorOptions
     * @param array<int, string> $afiliacionOptions
     * @param array<int, string> $estadoOptions
     * @param array<int, string> $viaOptions
     * @param array<int, string> $sedeOptions
     * @return array<int, array{label:string,value:string}>
     */
    private function buildDashboardFiltersSummary(
        array $filters,
        array $doctorOptions = [],
        array $afiliacionOptions = [],
        array $estadoOptions = [],
        array $viaOptions = [],
        array $sedeOptions = []
    ): array {
        $summary = [
            ['label' => 'Desde', 'value' => $filters['fecha_inicio']],
            ['label' => 'Hasta', 'value' => $filters['fecha_fin']],
        ];

        if ($filters['doctor'] !== '') {
            $summary[] = [
                'label' => 'Médico',
                'value' => in_array($filters['doctor'], $doctorOptions, true) ? $filters['doctor'] : $filters['doctor'],
            ];
        }
        if ($filters['afiliacion'] !== '') {
            $summary[] = [
                'label' => 'Afiliación',
                'value' => in_array($filters['afiliacion'], $afiliacionOptions, true) ? $filters['afiliacion'] : $filters['afiliacion'],
            ];
        }
        if ($filters['estado_receta'] !== '') {
            $summary[] = [
                'label' => 'Estado receta',
                'value' => in_array($filters['estado_receta'], $estadoOptions, true) ? $filters['estado_receta'] : $filters['estado_receta'],
            ];
        }
        if ($filters['via'] !== '') {
            $summary[] = [
                'label' => 'Vía',
                'value' => in_array($filters['via'], $viaOptions, true) ? $filters['via'] : $filters['via'],
            ];
        }
        if ($filters['sede'] !== '') {
            $summary[] = [
                'label' => 'Sede',
                'value' => in_array($filters['sede'], $sedeOptions, true) ? $filters['sede'] : $filters['sede'],
            ];
        }
        if ($filters['localidad'] !== '') {
            $summary[] = [
                'label' => 'Localidad',
                'value' => $filters['localidad'],
            ];
        }
        if ($filters['departamento'] !== '') {
            $summary[] = [
                'label' => 'Departamento',
                'value' => $filters['departamento'],
            ];
        }
        if ($filters['producto'] !== '') {
            $summary[] = [
                'label' => 'Producto',
                'value' => $filters['producto'],
            ];
        }

        return $summary;
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
