<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Examenes\Models\ExamenModel;
use Modules\Examenes\Services\ExamenEstadoService;
use Modules\Examenes\Services\ExamenReminderService;
use Modules\Examenes\Services\ExamenReportExcelService;
use Modules\Examenes\Services\ExamenSettingsService as LegacyExamenSettingsService;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Reporting\Services\ReportService;
use PDO;
use Throwable;

class ExamenesReportingService
{
    private PDO $db;
    private ExamenModel $examenModel;
    private ExamenEstadoService $estadoService;
    private LegacyExamenSettingsService $settingsService;
    private PusherConfigService $pusherConfig;

    public function __construct(?PDO $pdo = null)
    {
        LegacyExamenesRuntime::boot();

        $this->db = $pdo ?? DB::connection()->getPdo();
        $this->examenModel = new ExamenModel($this->db);
        $this->estadoService = new ExamenEstadoService();
        $this->settingsService = new LegacyExamenSettingsService($this->db);
        $this->pusherConfig = new PusherConfigService($this->db);
    }

    /**
     * @return array{formats:array<int,string>,quickMetrics:array<string,array<string,string>>}
     */
    public function reportingConfig(): array
    {
        return [
            'formats' => $this->settingsService->getReportFormats(),
            'quickMetrics' => $this->settingsService->getQuickMetrics(),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $permissions
     * @return array{status:int,content:string,headers:array<string,string>}
     */
    public function generatePdf(array $payload, array $permissions = []): array
    {
        if (!$this->canExport($permissions)) {
            return $this->jsonError('No tienes permisos para exportar reportes.', 403);
        }

        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        if ($format !== 'pdf') {
            return $this->jsonError('Formato no soportado.', 422);
        }

        $allowedFormats = $this->settingsService->getReportFormats();
        if (!in_array('pdf', $allowedFormats, true)) {
            return $this->jsonError('El formato PDF está deshabilitado en configuración.', 422);
        }

        $quickMetric = trim((string) ($payload['quickMetric'] ?? ''));
        if ($quickMetric !== '' && !$this->isQuickMetricAllowed($quickMetric)) {
            return $this->jsonError('Quick report no permitido en configuración.', 422);
        }

        $filtersInput = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];

        try {
            $reportData = $this->buildReportData($filtersInput, $quickMetric);
            $filename = 'examenes_' . date('Ymd_His') . '.pdf';
            $generatedAt = (new DateTimeImmutable('now'))->format('d-m-Y H:i');

            $reportService = new ReportService();
            $pdf = $reportService->renderPdf('examenes_kanban', [
                'titulo' => 'Reporte de exámenes',
                'generatedAt' => $generatedAt,
                'filters' => $reportData['filtersSummary'],
                'total' => count($reportData['rows']),
                'rows' => $reportData['rows'],
                'metricLabel' => $reportData['metricLabel'],
            ], [
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
                Log::warning('examenes.report.pdf.invalid', [
                    'preview' => substr($pdf, 0, 200),
                ]);

                return $this->jsonError('No se pudo generar el PDF (contenido inválido).', 500);
            }

            return [
                'status' => 200,
                'content' => $pdf,
                'headers' => [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                    'Content-Length' => (string) strlen($pdf),
                    'X-Content-Type-Options' => 'nosniff',
                ],
            ];
        } catch (Throwable $e) {
            Log::error('examenes.report.pdf.error', [
                'error' => $e->getMessage(),
            ]);

            return $this->jsonError('No se pudo generar el reporte.', 500);
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $permissions
     * @return array{status:int,content:string,headers:array<string,string>}
     */
    public function generateExcel(array $payload, array $permissions = []): array
    {
        if (!$this->canExport($permissions)) {
            return $this->jsonError('No tienes permisos para exportar reportes.', 403);
        }

        $format = strtolower(trim((string) ($payload['format'] ?? 'excel')));
        if ($format !== 'excel') {
            return $this->jsonError('Formato no soportado.', 422);
        }

        $allowedFormats = $this->settingsService->getReportFormats();
        if (!in_array('excel', $allowedFormats, true)) {
            return $this->jsonError('El formato Excel está deshabilitado en configuración.', 422);
        }

        $quickMetric = trim((string) ($payload['quickMetric'] ?? ''));
        if ($quickMetric !== '' && !$this->isQuickMetricAllowed($quickMetric)) {
            return $this->jsonError('Quick report no permitido en configuración.', 422);
        }

        $filtersInput = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];

        try {
            $reportData = $this->buildReportData($filtersInput, $quickMetric);
            $filename = 'examenes_' . date('Ymd_His') . '.xlsx';
            $generatedAt = (new DateTimeImmutable('now'))->format('d-m-Y H:i');

            $excelService = new ExamenReportExcelService();
            $content = $excelService->render($reportData['rows'], $reportData['filtersSummary'], [
                'title' => 'Reporte de exámenes',
                'generated_at' => $generatedAt,
                'metric_label' => $reportData['metricLabel'],
                'total' => count($reportData['rows']),
            ]);

            if ($content === '' || strncmp($content, 'PK', 2) !== 0) {
                Log::warning('examenes.report.excel.invalid', [
                    'preview' => substr($content, 0, 32),
                ]);

                return $this->jsonError('No se pudo generar el Excel (contenido inválido).', 500);
            }

            return [
                'status' => 200,
                'content' => $content,
                'headers' => [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Length' => (string) strlen($content),
                    'X-Content-Type-Options' => 'nosniff',
                ],
            ];
        } catch (Throwable $e) {
            Log::error('examenes.report.excel.error', [
                'error' => $e->getMessage(),
            ]);

            return $this->jsonError('No se pudo generar el reporte.', 500);
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function dispatchReminders(array $payload): array
    {
        $hours = isset($payload['horas']) ? (int) $payload['horas'] : 24;
        $service = new ExamenReminderService($this->db, $this->pusherConfig);
        $dispatched = $service->dispatchUpcoming($hours);

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'dispatched' => $dispatched,
                'count' => count($dispatched),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $filtersInput
     * @return array{filters:array<string,string|null>,rows:array<int,array<string,mixed>>,filtersSummary:array<int,array<string,string>>,metricLabel:?string}
     */
    private function buildReportData(array $filtersInput, string $quickMetric): array
    {
        $filters = $this->sanitizeReportFilters($filtersInput);

        $queryFilters = [
            'doctor' => $filters['doctor'],
            'afiliacion' => $filters['afiliacion'],
            'prioridad' => $filters['prioridad'],
        ];

        $rows = $this->examenModel->fetchExamenesConDetallesFiltrado($queryFilters);
        $rows = array_map(fn(array $row): array => $this->transformExamenRow($row), $rows);
        $rows = $this->estadoService->enrichExamenes($rows);
        $rows = $this->applySearchFilter($rows, (string) ($filters['search'] ?? ''));
        $rows = $this->applyDateRangeFilter($rows, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        if (!empty($filters['estado'])) {
            $estadoSlug = $this->estadoService->normalizeSlug((string) $filters['estado']);
            $rows = array_values(array_filter(
                $rows,
                fn(array $row): bool => $this->estadoService->normalizeSlug((string) ($row['kanban_estado'] ?? ($row['estado'] ?? ''))) === $estadoSlug
            ));
        }

        $metricConfig = $this->getQuickMetricConfig($quickMetric);
        $metricLabel = $metricConfig['label'] ?? null;
        if ($metricConfig !== []) {
            $rows = $this->applyQuickMetricFilter($rows, $metricConfig);
        }

        return [
            'filters' => $filters,
            'rows' => $rows,
            'filtersSummary' => $this->buildReportFiltersSummary($filters, $metricLabel),
            'metricLabel' => $metricLabel,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,string|null>
     */
    private function sanitizeReportFilters(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $doctor = trim((string) ($filters['doctor'] ?? ''));
        $afiliacion = trim((string) ($filters['afiliacion'] ?? ''));
        $prioridad = trim((string) ($filters['prioridad'] ?? ''));
        $estado = trim((string) ($filters['estado'] ?? ''));

        $allowedPriorities = ['normal', 'pendiente', 'urgente'];
        if ($prioridad !== '' && !in_array(strtolower($prioridad), $allowedPriorities, true)) {
            $prioridad = '';
        }

        $dateFrom = $this->normalizeDateInput($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDateInput($filters['date_to'] ?? null);
        if ($dateFrom === null && $dateTo === null && !empty($filters['fechaTexto'])) {
            [$dateFrom, $dateTo] = $this->parseDateRange((string) $filters['fechaTexto']);
        }

        return [
            'search' => $search !== '' ? $search : null,
            'doctor' => $doctor !== '' ? $doctor : null,
            'afiliacion' => $afiliacion !== '' ? $afiliacion : null,
            'prioridad' => $prioridad !== '' ? $prioridad : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'estado' => $estado !== '' ? $estado : null,
        ];
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('d-m-Y', $value);
        } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);
        } else {
            try {
                $date = new DateTimeImmutable($value);
            } catch (Throwable) {
                $date = null;
            }
        }

        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : null;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function parseDateRange(string $value): array
    {
        if (!str_contains($value, ' - ')) {
            $single = $this->normalizeDateInput($value);
            return [$single, $single];
        }

        [$from, $to] = explode(' - ', $value, 2);

        return [
            $this->normalizeDateInput($from),
            $this->normalizeDateInput($to),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function applySearchFilter(array $rows, string $search): array
    {
        $term = trim($search);
        if ($term === '') {
            return $rows;
        }

        $term = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
        $keys = ['full_name', 'hc_number', 'examen_nombre', 'procedimiento', 'doctor', 'afiliacion', 'estado', 'kanban_estado', 'crm_pipeline_stage'];

        return array_values(array_filter($rows, static function (array $row) use ($term, $keys): bool {
            foreach ($keys as $key) {
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $haystack = function_exists('mb_strtolower')
                    ? mb_strtolower((string) $value, 'UTF-8')
                    : strtolower((string) $value);

                if (str_contains($haystack, $term)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function applyDateRangeFilter(array $rows, ?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom === null && $dateTo === null) {
            return $rows;
        }

        $from = $dateFrom !== null ? DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom) : null;
        $to = $dateTo !== null ? DateTimeImmutable::createFromFormat('Y-m-d', $dateTo) : null;
        if ($from instanceof DateTimeImmutable) {
            $from = $from->setTime(0, 0, 0);
        }
        if ($to instanceof DateTimeImmutable) {
            $to = $to->setTime(23, 59, 59);
        }

        return array_values(array_filter($rows, function (array $row) use ($from, $to): bool {
            $value = $row['consulta_fecha'] ?? ($row['created_at'] ?? null);
            $date = $this->parseFecha($value);
            if (!$date instanceof DateTimeImmutable) {
                return false;
            }
            if ($from instanceof DateTimeImmutable && $date < $from) {
                return false;
            }
            if ($to instanceof DateTimeImmutable && $date > $to) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<string,string>
     */
    private function getQuickMetricConfig(string $quickMetric): array
    {
        $map = $this->settingsService->getQuickMetrics();
        return $map[$quickMetric] ?? [];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $metricConfig
     * @return array<int,array<string,mixed>>
     */
    private function applyQuickMetricFilter(array $rows, array $metricConfig): array
    {
        if (isset($metricConfig['estado'])) {
            $estadoSlug = $this->estadoService->normalizeSlug($metricConfig['estado']);
            return array_values(array_filter($rows, function (array $row) use ($estadoSlug): bool {
                $rawEstado = $row['kanban_estado'] ?? ($row['estado'] ?? '');
                return $this->estadoService->normalizeSlug((string) $rawEstado) === $estadoSlug;
            }));
        }

        if (isset($metricConfig['sla_status'])) {
            return array_values(array_filter(
                $rows,
                static fn(array $row): bool => ($row['sla_status'] ?? '') === $metricConfig['sla_status']
            ));
        }

        return $rows;
    }

    /**
     * @param array<string,string|null> $filters
     * @return array<int,array<string,string>>
     */
    private function buildReportFiltersSummary(array $filters, ?string $metricLabel): array
    {
        $summary = [];

        if (!empty($filters['search'])) {
            $summary[] = ['label' => 'Buscar', 'value' => (string) $filters['search']];
        }
        if (!empty($filters['doctor'])) {
            $summary[] = ['label' => 'Doctor', 'value' => (string) $filters['doctor']];
        }
        if (!empty($filters['afiliacion'])) {
            $summary[] = ['label' => 'Afiliación', 'value' => (string) $filters['afiliacion']];
        }
        if (!empty($filters['prioridad'])) {
            $summary[] = ['label' => 'Prioridad', 'value' => (string) $filters['prioridad']];
        }
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $summary[] = [
                'label' => 'Fecha',
                'value' => sprintf('%s a %s', $filters['date_from'] ?? '—', $filters['date_to'] ?? '—'),
            ];
        }
        if (!empty($filters['estado'])) {
            $summary[] = ['label' => 'Estado/Columna', 'value' => (string) $filters['estado']];
        }
        if ($metricLabel !== null && $metricLabel !== '') {
            $summary[] = ['label' => 'Quick report', 'value' => $metricLabel];
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function transformExamenRow(array $row): array
    {
        if (empty($row['fecha'] ?? null)) {
            $row['fecha'] = $row['consulta_fecha'] ?? ($row['created_at'] ?? null);
        }
        if (empty($row['procedimiento'] ?? null)) {
            $row['procedimiento'] = $row['examen_nombre'] ?? ($row['examen_codigo'] ?? null);
        }
        if (empty($row['tipo'] ?? null)) {
            $row['tipo'] = $row['examen_codigo'] ?? ($row['examen_nombre'] ?? null);
        }
        if (empty($row['observacion'] ?? null)) {
            $row['observacion'] = $row['observaciones'] ?? null;
        }
        if (empty($row['ojo'] ?? null)) {
            $row['ojo'] = $row['lateralidad'] ?? null;
        }
        if (!empty($row['derivacion_fecha_vigencia_sel']) && empty($row['derivacion_fecha_vigencia'])) {
            $row['derivacion_fecha_vigencia'] = $row['derivacion_fecha_vigencia_sel'];
        }
        $row['derivacion_status'] = $this->resolveDerivacionVigenciaStatus(
            isset($row['derivacion_fecha_vigencia']) ? (string) $row['derivacion_fecha_vigencia'] : null
        );

        return $row;
    }

    private function resolveDerivacionVigenciaStatus(?string $fechaVigencia): ?string
    {
        if ($fechaVigencia === null || trim($fechaVigencia) === '') {
            return null;
        }

        $dt = $this->parseFecha($fechaVigencia);
        if (!$dt instanceof DateTimeImmutable) {
            return null;
        }

        $today = new DateTimeImmutable('today');
        return $dt >= $today ? 'vigente' : 'vencida';
    }

    private function parseFecha(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        $string = is_string($value) ? trim($value) : '';
        if ($string === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $string);
            if ($dt instanceof DateTimeImmutable) {
                return $format === 'Y-m-d' ? $dt->setTime(0, 0) : $dt;
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function isQuickMetricAllowed(string $quickMetric): bool
    {
        return array_key_exists($quickMetric, $this->settingsService->getQuickMetrics());
    }

    /**
     * @param array<int,string> $permissions
     */
    private function canExport(array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        $normalized = array_map('trim', $permissions);
        foreach (['reportes.export', 'reportes.view', 'examenes.view', 'administrativo', 'examenes.manage'] as $permission) {
            if (in_array($permission, $normalized, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status:int,content:string,headers:array<string,string>}
     */
    private function jsonError(string $message, int $status): array
    {
        return [
            'status' => $status,
            'content' => json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"error":"' . addslashes($message) . '"}',
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        ];
    }
}
