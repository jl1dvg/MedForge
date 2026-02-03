<?php

namespace Modules\Solicitudes\Services;

use DateTimeImmutable;
use Models\SolicitudModel;
use PDO;

class SolicitudKpiService
{
    private const TERMINAL_STATES = [
        'atendido', 'atendida', 'cancelado', 'cancelada', 'cerrado', 'cerrada',
        'suspendido', 'suspendida', 'facturado', 'facturada', 'reprogramado', 'reprogramada',
        'pagado', 'pagada', 'no procede', 'no_procede', 'no-procede', 'cerrado sin atención',
        'facturada-cerrada', 'protocolo-completo', 'completado',
    ];

    private const CANCEL_STATES = ['cancelado', 'cancelada', 'suspendido', 'suspendida', 'no procede', 'no_procede', 'no-procede'];
    private const REPROGRAM_STATES = ['reprogramado', 'reprogramada'];
    private const NO_SHOW_STATES = ['no show', 'no-show', 'no_show', 'no procede', 'no_procede', 'no-procede'];

    private PDO $pdo;
    private SolicitudModel $model;
    private SolicitudSettingsService $settingsService;
    private SolicitudEstadoService $estadoService;
    private ?array $lastQueryContext = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new SolicitudModel($pdo);
        $this->settingsService = new SolicitudSettingsService($pdo);
        $this->estadoService = new SolicitudEstadoService($pdo);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildKpi(array $filters): array
    {
        try {
            $rows = $this->model->fetchKpiBase($filters);
        } catch (\PDOException $e) {
            $this->lastQueryContext = $this->model->getLastKpiQueryContext();
            throw $e;
        }

        $this->lastQueryContext = $this->model->getLastKpiQueryContext();
        $ids = array_values(array_filter(array_map(static fn(array $row) => (int) ($row['id'] ?? 0), $rows)));

        try {
            $checklists = $this->model->fetchChecklistCompletions($ids);
        } catch (\PDOException $e) {
            $this->lastQueryContext = $this->model->getLastKpiQueryContext();
            throw $e;
        }

        $this->lastQueryContext = $this->model->getLastKpiQueryContext() ?? $this->lastQueryContext;

        $now = new DateTimeImmutable('now');
        $granularity = $filters['granularity'] ?? 'day';

        $createdSeries = [];
        $closedSeries = [];
        $backlogTotal = 0;
        $backlogByEstado = [];
        $topProcedimientos = [];
        $slaCounts = [
            'vencido' => 0,
            'critico' => 0,
            'advertencia' => 0,
            'en_rango' => 0,
            'sin_fecha' => 0,
            'cerrado' => 0,
        ];
        $overdueHours = [];
        $riskRows = [];
        $leadTimes = [];
        $cyclePendingToGestion = [];
        $cycleGestionToProgramado = [];
        $cycleProgramadoToClosed = [];
        $agingRows = [];
        $pendienteAptoAnestesia = [];
        $pendienteAptoOftalmo = [];
        $pendienteCobertura = [];
        $documentosFaltantes = [];
        $reprogramaciones = [];
        $cancelMotivos = [];
        $reprogramMotivos = [];
        $missingCritical = [];
        $responsableMetrics = [];
        $doctorMetrics = [];
        $afiliacionMetrics = [];
        $leadByAfiliacion = [];
        $turnosSeries = [];
        $turnoWaitTimes = [];

        $totalRows = count($rows);
        $openRows = 0;
        $cancelCount = 0;
        $reprogramCount = 0;

        foreach ($rows as $row) {
            $estadoRaw = (string) ($row['estado'] ?? '');
            $estado = $this->normalizeEstado($estadoRaw);
            $createdAt = $this->parseDate($row['created_at'] ?? null);
            $fechaProgramada = $this->parseDate($row['fecha_programada'] ?? null);
            $checklist = $checklists[(int) ($row['id'] ?? 0)] ?? [];

            $isTerminal = $this->isTerminal($estado);
            $sla = $this->computeSla($row, $isTerminal, $now);
            $row['sla_status'] = $sla['sla_status'];
            $row['sla_hours_remaining'] = $sla['sla_hours_remaining'];

            $periodCreated = $createdAt ? $this->formatPeriod($createdAt, $granularity) : null;
            if ($periodCreated) {
                $createdSeries[$periodCreated] = ($createdSeries[$periodCreated] ?? 0) + 1;
            }

            if ($isTerminal) {
                $closedAt = $this->resolveClosedAt($row, $checklist, $createdAt);
                if ($closedAt) {
                    $periodClosed = $this->formatPeriod($closedAt, $granularity);
                    $closedSeries[$periodClosed] = ($closedSeries[$periodClosed] ?? 0) + 1;
                }

                if ($createdAt && $closedAt) {
                    $leadHours = $this->diffHours($createdAt, $closedAt);
                    if ($leadHours !== null) {
                        $leadTimes[] = $leadHours;
                    }
                    $this->addLeadTimeForResponsable($responsableMetrics, $row, $leadHours);
                    $this->addLeadTimeForAfiliacion($leadByAfiliacion, $row, $leadHours);
                }
            } else {
                $openRows++;
                $backlogTotal++;
                $backlogByEstado[$estado] = ($backlogByEstado[$estado] ?? 0) + 1;
                $this->addBacklogForResponsable($responsableMetrics, $row);
            }

            $procedimiento = trim((string) ($row['procedimiento'] ?? ''));
            if ($procedimiento !== '' && strtoupper($procedimiento) !== 'SELECCIONE') {
                $topProcedimientos[$procedimiento] = ($topProcedimientos[$procedimiento] ?? 0) + 1;
            }

            $slaCounts[$sla['sla_status']] = ($slaCounts[$sla['sla_status']] ?? 0) + 1;
            if ($sla['sla_status'] === 'vencido' && $sla['sla_hours_remaining'] !== null) {
                $overdueHours[] = abs($sla['sla_hours_remaining']);
            }
            if (!$isTerminal && $sla['sla_hours_remaining'] !== null) {
                $riskRows[] = array_merge($row, [
                    'estado' => $estado,
                    'sla_hours_remaining' => $sla['sla_hours_remaining'],
                ]);
            }

            $this->appendCycleTimes(
                $cyclePendingToGestion,
                $cycleGestionToProgramado,
                $cycleProgramadoToClosed,
                $createdAt,
                $checklist,
                $this->resolveClosedAt($row, $checklist, $createdAt),
                $isTerminal
            );

            $this->addAgingRow($agingRows, $row, $estado, $createdAt, $checklist, $now);

            if ($estado === 'apto-anestesia' && !$isTerminal) {
                $pendienteAptoAnestesia[] = $row;
            }
            if ($estado === 'apto-oftalmologo' && !$isTerminal) {
                $pendienteAptoOftalmo[] = $row;
            }
            if ($estado === 'revision-codigos' && !$isTerminal) {
                $pendienteCobertura[] = $row;
            }
            if (!$isTerminal && (int) ($row['crm_total_adjuntos'] ?? 0) === 0) {
                $documentosFaltantes[] = $row;
            }

            if (in_array($estado, self::REPROGRAM_STATES, true)) {
                $reprogramCount++;
                $reprogramaciones[] = $row;
                $this->addMotivo($reprogramMotivos, $row['observacion'] ?? null);
            }

            if (in_array($estado, self::CANCEL_STATES, true)) {
                $cancelCount++;
                $this->addMotivo($cancelMotivos, $row['observacion'] ?? null);
            }

            $this->addMissingCritical($missingCritical, $row);
            $this->addResponsableThroughput($responsableMetrics, $row, $isTerminal);
            $this->addDoctorMetrics($doctorMetrics, $row, $estado, $isTerminal);
            $this->addAfiliacionMetrics($afiliacionMetrics, $row, $estado, $isTerminal);

            if (!empty($row['turno'])) {
                if ($createdAt) {
                    $periodTurno = $this->formatPeriod($createdAt, $granularity);
                    $turnosSeries[$periodTurno] = ($turnosSeries[$periodTurno] ?? 0) + 1;
                }
                $enAtencion = $this->parseDate($checklist['en-atencion'] ?? null);
                if ($createdAt && $enAtencion) {
                    $wait = $this->diffHours($createdAt, $enAtencion);
                    if ($wait !== null) {
                        $turnoWaitTimes[] = $wait;
                    }
                }
            }
        }

        $topProcedimientosResult = $this->formatTopProcedimientos($topProcedimientos, $totalRows);
        $riskRows = $this->topRiskRows($riskRows);
        $agingRows = $this->sortAgingRows($agingRows);

        $leadStats = $this->buildStats($leadTimes);
        $cyclePendingStats = $this->buildStats($cyclePendingToGestion);
        $cycleGestionStats = $this->buildStats($cycleGestionToProgramado);
        $cycleProgramadoStats = $this->buildStats($cycleProgramadoToClosed);
        $turnoWaitStats = $this->buildStats($turnoWaitTimes);

        $overdueAvg = $this->average($overdueHours);
        $overduePercent = $openRows > 0 ? round(($slaCounts['vencido'] / $openRows) * 100, 2) : 0;
        $cancelRate = $totalRows > 0 ? round(($cancelCount / $totalRows) * 100, 2) : 0;
        $reprogramRate = $totalRows > 0 ? round(($reprogramCount / $totalRows) * 100, 2) : 0;

        $netFlow = $this->buildNetFlow($createdSeries, $closedSeries);

        return [
            'filters' => $filters,
            'definitions' => $this->buildDefinitions(),
            'data' => [
                'throughput' => [
                    'created_series' => $this->formatSeries($createdSeries),
                    'closed_series' => $this->formatSeries($closedSeries),
                    'backlog_total' => $backlogTotal,
                    'backlog_by_estado' => $backlogByEstado,
                    'net_flow' => $netFlow,
                    'top_procedimientos' => $topProcedimientosResult,
                ],
                'times' => [
                    'lead_time' => $leadStats,
                    'cycle_times' => [
                        'pendiente_a_gestion' => $cyclePendingStats,
                        'gestion_a_programado' => $cycleGestionStats,
                        'programado_a_cerrado' => $cycleProgramadoStats,
                    ],
                    'aging' => $agingRows,
                ],
                'sla' => [
                    'by_status' => $slaCounts,
                    'overdue_percent_open' => $overduePercent,
                    'avg_overdue_hours' => $overdueAvg,
                    'top_risk' => $riskRows,
                ],
                'pendientes' => [
                    'apto_anestesia' => $this->formatList($pendienteAptoAnestesia),
                    'apto_oftalmologia' => $this->formatList($pendienteAptoOftalmo),
                    'coberturas' => $this->formatList($pendienteCobertura),
                    'documentos_faltantes' => $this->formatList($documentosFaltantes),
                    'reprogramaciones' => [
                        'total' => count($reprogramaciones),
                        'motivos' => $this->formatMotivos($reprogramMotivos),
                    ],
                ],
                'calidad' => [
                    'tasa_cancelacion' => $cancelRate,
                    'tasa_reprogramacion' => $reprogramRate,
                    'motivos_cancelacion' => $this->formatMotivos($cancelMotivos),
                    'motivos_reprogramacion' => $this->formatMotivos($reprogramMotivos),
                    'sin_datos_criticos' => $missingCritical,
                ],
                'rendimiento' => [
                    'por_responsable' => $this->formatResponsableMetrics($responsableMetrics),
                    'por_doctor' => $this->formatDoctorMetrics($doctorMetrics),
                    'por_afiliacion' => $this->formatAfiliacionMetrics($afiliacionMetrics),
                    'lead_time_por_afiliacion' => $this->formatLeadByAfiliacion($leadByAfiliacion),
                ],
                'turnero' => [
                    'turnos_generados' => $this->formatSeries($turnosSeries),
                    'tiempo_espera' => $turnoWaitStats,
                    'no_shows' => $this->countNoShows($rows),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @param string $type
     * @return array{rows: array<int, array<string, mixed>>, title: string, metric_label: string}
     */
    public function buildExportData(array $filters, string $type): array
    {
        try {
            $rows = $this->model->fetchKpiBase($filters);
        } catch (\PDOException $e) {
            $this->lastQueryContext = $this->model->getLastKpiQueryContext();
            throw $e;
        }
        $this->lastQueryContext = $this->model->getLastKpiQueryContext();
        $ids = array_values(array_filter(array_map(static fn(array $row) => (int) ($row['id'] ?? 0), $rows)));
        try {
            $checklists = $this->model->fetchChecklistCompletions($ids);
        } catch (\PDOException $e) {
            $this->lastQueryContext = $this->model->getLastKpiQueryContext();
            throw $e;
        }
        $this->lastQueryContext = $this->model->getLastKpiQueryContext() ?? $this->lastQueryContext;
        $now = new DateTimeImmutable('now');

        $filtered = [];
        $metricLabel = '';
        foreach ($rows as $row) {
            $estado = $this->normalizeEstado((string) ($row['estado'] ?? ''));
            $isTerminal = $this->isTerminal($estado);
            $sla = $this->computeSla($row, $isTerminal, $now);
            $row['sla_status'] = $sla['sla_status'];
            $row['sla_hours_remaining'] = $sla['sla_hours_remaining'];
            $row['estado'] = $estado;

            if ($type === 'pendientes_cobertura' && $estado === 'revision-codigos' && !$isTerminal) {
                $filtered[] = $row;
                $metricLabel = 'Pendientes de cobertura';
            }
            if ($type === 'pendientes_apto_anestesia' && $estado === 'apto-anestesia' && !$isTerminal) {
                $filtered[] = $row;
                $metricLabel = 'Pendientes apto anestesia';
            }
            if ($type === 'pendientes_apto_oftalmologia' && $estado === 'apto-oftalmologo' && !$isTerminal) {
                $filtered[] = $row;
                $metricLabel = 'Pendientes apto oftalmología';
            }
            if ($type === 'vencidas_criticas' && in_array($sla['sla_status'], ['vencido', 'critico'], true)) {
                $filtered[] = $row;
                $metricLabel = 'Vencidas y críticas';
            }
            if ($type === 'backlog_estado_responsable' && !$isTerminal) {
                $filtered[] = $row;
                $metricLabel = 'Backlog por estado/responsable';
            }
        }

        $title = match ($type) {
            'pendientes_cobertura' => 'Pendientes de cobertura',
            'pendientes_apto_anestesia' => 'Pendientes apto anestesia',
            'pendientes_apto_oftalmologia' => 'Pendientes apto oftalmología',
            'vencidas_criticas' => 'Solicitudes vencidas/críticas',
            'backlog_estado_responsable' => 'Backlog por estado/responsable',
            default => 'Exportable KPI solicitudes',
        };

        return [
            'rows' => $filtered,
            'title' => $title,
            'metric_label' => $metricLabel,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastQueryContext(): ?array
    {
        return $this->lastQueryContext;
    }

    private function normalizeEstado(string $estado): string
    {
        return $this->estadoService->normalizeSlug($estado);
    }

    private function isTerminal(string $estado): bool
    {
        return in_array($estado, self::TERMINAL_STATES, true);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{sla_status: string, sla_hours_remaining: float|null}
     */
    private function computeSla(array $row, bool $isTerminal, DateTimeImmutable $now): array
    {
        $slaWarningHours = $this->settingsService->getSlaWarningHours();
        $slaCriticalHours = $this->settingsService->getSlaCriticalHours();
        $fechaProgramada = $this->parseDate($row['fecha_programada'] ?? null);
        $createdAt = $this->parseDate($row['created_at'] ?? null);

        $deadline = $fechaProgramada ?? $createdAt;
        $hoursRemaining = null;
        $slaStatus = 'sin_fecha';

        if ($deadline instanceof DateTimeImmutable) {
            $hoursRemaining = ($deadline->getTimestamp() - $now->getTimestamp()) / 3600;

            if ($isTerminal) {
                $slaStatus = 'cerrado';
            } elseif ($hoursRemaining < 0) {
                $slaStatus = 'vencido';
            } elseif ($hoursRemaining <= $slaCriticalHours) {
                $slaStatus = 'critico';
            } elseif ($hoursRemaining <= $slaWarningHours) {
                $slaStatus = 'advertencia';
            } else {
                $slaStatus = 'en_rango';
            }
        } elseif ($createdAt instanceof DateTimeImmutable) {
            $elapsed = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;
            if ($elapsed >= $slaWarningHours) {
                $slaStatus = 'advertencia';
            }
        }

        return [
            'sla_status' => $slaStatus,
            'sla_hours_remaining' => $hoursRemaining !== null ? round($hoursRemaining, 2) : null,
        ];
    }

    private function resolveClosedAt(array $row, array $checklist, ?DateTimeImmutable $createdAt): ?DateTimeImmutable
    {
        $candidates = [
            $checklist['completado'] ?? null,
            $checklist['programada'] ?? null,
            $row['fecha_programada'] ?? null,
            $row['fecha_referencia'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $date = $this->parseDate($candidate);
            if ($date) {
                return $date;
            }
        }

        return $createdAt;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatPeriod(DateTimeImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->format('o-\WW'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    private function diffHours(DateTimeImmutable $start, DateTimeImmutable $end): ?float
    {
        $diff = $end->getTimestamp() - $start->getTimestamp();
        return $diff >= 0 ? round($diff / 3600, 2) : null;
    }

    private function formatSeries(array $series): array
    {
        ksort($series);
        $result = [];
        foreach ($series as $period => $value) {
            $result[] = [
                'period' => $period,
                'value' => $value,
            ];
        }
        return $result;
    }

    private function buildNetFlow(array $createdSeries, array $closedSeries): array
    {
        $periods = array_unique(array_merge(array_keys($createdSeries), array_keys($closedSeries)));
        sort($periods);
        $result = [];
        foreach ($periods as $period) {
            $created = $createdSeries[$period] ?? 0;
            $closed = $closedSeries[$period] ?? 0;
            $result[] = [
                'period' => $period,
                'created' => $created,
                'closed' => $closed,
                'net' => $created - $closed,
            ];
        }
        return $result;
    }

    private function formatTopProcedimientos(array $procedimientos, int $totalRows): array
    {
        arsort($procedimientos);
        $result = [];
        foreach (array_slice($procedimientos, 0, 10, true) as $procedimiento => $count) {
            $percent = $totalRows > 0 ? round(($count / $totalRows) * 100, 2) : 0;
            $result[] = [
                'procedimiento' => $procedimiento,
                'count' => $count,
                'percent' => $percent,
            ];
        }
        return $result;
    }

    private function buildStats(array $values): array
    {
        if (empty($values)) {
            return [
                'average_hours' => null,
                'p50_hours' => null,
                'p75_hours' => null,
                'p90_hours' => null,
                'count' => 0,
            ];
        }

        sort($values);
        return [
            'average_hours' => $this->average($values),
            'p50_hours' => $this->percentile($values, 0.5),
            'p75_hours' => $this->percentile($values, 0.75),
            'p90_hours' => $this->percentile($values, 0.9),
            'count' => count($values),
        ];
    }

    private function average(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }
        return round(array_sum($values) / count($values), 2);
    }

    private function percentile(array $values, float $percent): ?float
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        $index = (int) ceil($percent * $count) - 1;
        $index = max(0, min($count - 1, $index));
        return $values[$index] ?? null;
    }

    private function appendCycleTimes(
        array &$pendingToGestion,
        array &$gestionToProgramado,
        array &$programadoToClosed,
        ?DateTimeImmutable $createdAt,
        array $checklist,
        ?DateTimeImmutable $closedAt,
        bool $isTerminal
    ): void {
        $recibida = $this->parseDate($checklist['recibida'] ?? null) ?? $createdAt;
        $enAtencion = $this->parseDate($checklist['en-atencion'] ?? null);
        $programada = $this->parseDate($checklist['programada'] ?? null);

        if ($recibida && $enAtencion) {
            $diff = $this->diffHours($recibida, $enAtencion);
            if ($diff !== null) {
                $pendingToGestion[] = $diff;
            }
        }

        if ($enAtencion && $programada) {
            $diff = $this->diffHours($enAtencion, $programada);
            if ($diff !== null) {
                $gestionToProgramado[] = $diff;
            }
        }

        if ($programada && $closedAt && $isTerminal) {
            $diff = $this->diffHours($programada, $closedAt);
            if ($diff !== null) {
                $programadoToClosed[] = $diff;
            }
        }
    }

    private function addAgingRow(array &$agingRows, array $row, string $estado, ?DateTimeImmutable $createdAt, array $checklist, DateTimeImmutable $now): void
    {
        $lastCompleted = null;
        foreach ($checklist as $dateValue) {
            $candidate = $this->parseDate($dateValue);
            if ($candidate && (!$lastCompleted || $candidate > $lastCompleted)) {
                $lastCompleted = $candidate;
            }
        }

        $start = $lastCompleted ?? $createdAt;
        if (!$start) {
            return;
        }

        $agingHours = $this->diffHours($start, $now);
        if ($agingHours === null) {
            return;
        }

        $agingRows[] = [
            'id' => $row['id'] ?? null,
            'full_name' => $row['full_name'] ?? null,
            'estado' => $estado,
            'responsable' => $row['crm_responsable_nombre'] ?? null,
            'aging_hours' => $agingHours,
            'desde' => $start->format(DateTimeImmutable::ATOM),
        ];
    }

    private function sortAgingRows(array $agingRows): array
    {
        usort($agingRows, static fn(array $a, array $b) => ($b['aging_hours'] ?? 0) <=> ($a['aging_hours'] ?? 0));
        return $agingRows;
    }

    private function topRiskRows(array $rows): array
    {
        usort($rows, static fn(array $a, array $b) => ($a['sla_hours_remaining'] ?? 0) <=> ($b['sla_hours_remaining'] ?? 0));
        return array_slice($rows, 0, 20);
    }

    private function formatList(array $rows): array
    {
        return [
            'total' => count($rows),
            'rows' => array_slice($rows, 0, 200),
        ];
    }

    private function addMotivo(array &$bucket, mixed $motivo): void
    {
        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return;
        }
        $bucket[$motivo] = ($bucket[$motivo] ?? 0) + 1;
    }

    private function formatMotivos(array $bucket): array
    {
        arsort($bucket);
        $result = [];
        foreach (array_slice($bucket, 0, 10, true) as $motivo => $count) {
            $result[] = ['motivo' => $motivo, 'count' => $count];
        }
        return $result;
    }

    private function addMissingCritical(array &$missing, array $row): void
    {
        $procedimiento = trim((string) ($row['procedimiento'] ?? ''));
        $doctor = trim((string) ($row['doctor'] ?? ''));
        $afiliacion = trim((string) ($row['afiliacion'] ?? ''));
        $fechaProgramada = trim((string) ($row['fecha_programada'] ?? ''));

        $missingFlags = [
            'procedimiento' => ($procedimiento === '' || strtoupper($procedimiento) === 'SELECCIONE'),
            'doctor' => ($doctor === '' || strtoupper($doctor) === 'SELECCIONE'),
            'afiliacion' => ($afiliacion === '' || strtoupper($afiliacion) === 'SELECCIONE'),
            'fecha_tentativa' => ($fechaProgramada === ''),
        ];

        if (in_array(true, $missingFlags, true)) {
            $missing[] = [
                'id' => $row['id'] ?? null,
                'full_name' => $row['full_name'] ?? null,
                'missing' => array_keys(array_filter($missingFlags)),
            ];
        }
    }

    private function addResponsableThroughput(array &$bucket, array $row, bool $isTerminal): void
    {
        $responsableId = (string) ($row['crm_responsable_id'] ?? 'sin_asignar');
        if (!isset($bucket[$responsableId])) {
            $bucket[$responsableId] = [
                'responsable_id' => $row['crm_responsable_id'] ?? null,
                'responsable_nombre' => $row['crm_responsable_nombre'] ?? 'Sin responsable',
                'cerradas' => 0,
                'lead_times' => [],
                'backlog' => 0,
            ];
        }

        if ($isTerminal) {
            $bucket[$responsableId]['cerradas']++;
        }
    }

    private function addBacklogForResponsable(array &$bucket, array $row): void
    {
        $responsableId = (string) ($row['crm_responsable_id'] ?? 'sin_asignar');
        if (!isset($bucket[$responsableId])) {
            $bucket[$responsableId] = [
                'responsable_id' => $row['crm_responsable_id'] ?? null,
                'responsable_nombre' => $row['crm_responsable_nombre'] ?? 'Sin responsable',
                'cerradas' => 0,
                'lead_times' => [],
                'backlog' => 0,
            ];
        }

        $bucket[$responsableId]['backlog']++;
    }

    private function addLeadTimeForResponsable(array &$bucket, array $row, ?float $leadHours): void
    {
        if ($leadHours === null) {
            return;
        }
        $responsableId = (string) ($row['crm_responsable_id'] ?? 'sin_asignar');
        if (!isset($bucket[$responsableId])) {
            $bucket[$responsableId] = [
                'responsable_id' => $row['crm_responsable_id'] ?? null,
                'responsable_nombre' => $row['crm_responsable_nombre'] ?? 'Sin responsable',
                'cerradas' => 0,
                'lead_times' => [],
                'backlog' => 0,
            ];
        }
        $bucket[$responsableId]['lead_times'][] = $leadHours;
    }

    private function formatResponsableMetrics(array $bucket): array
    {
        $result = [];
        foreach ($bucket as $entry) {
            $leadStats = $this->buildStats($entry['lead_times'] ?? []);
            $result[] = [
                'responsable_id' => $entry['responsable_id'],
                'responsable_nombre' => $entry['responsable_nombre'],
                'cerradas' => $entry['cerradas'],
                'backlog' => $entry['backlog'],
                'lead_time_promedio_horas' => $leadStats['average_hours'],
            ];
        }

        usort($result, static fn(array $a, array $b) => ($b['cerradas'] ?? 0) <=> ($a['cerradas'] ?? 0));
        return $result;
    }

    private function addDoctorMetrics(array &$bucket, array $row, string $estado, bool $isTerminal): void
    {
        $doctor = trim((string) ($row['doctor'] ?? 'Sin doctor'));
        if ($doctor === '') {
            $doctor = 'Sin doctor';
        }
        if (!isset($bucket[$doctor])) {
            $bucket[$doctor] = [
                'doctor' => $doctor,
                'creadas' => 0,
                'programadas' => 0,
                'realizadas' => 0,
            ];
        }
        $bucket[$doctor]['creadas']++;
        if ($estado === 'programada') {
            $bucket[$doctor]['programadas']++;
        }
        if ($isTerminal) {
            $bucket[$doctor]['realizadas']++;
        }
    }

    private function formatDoctorMetrics(array $bucket): array
    {
        $result = array_values($bucket);
        usort($result, static fn(array $a, array $b) => ($b['creadas'] ?? 0) <=> ($a['creadas'] ?? 0));
        return $result;
    }

    private function addAfiliacionMetrics(array &$bucket, array $row, string $estado, bool $isTerminal): void
    {
        $afiliacion = trim((string) ($row['afiliacion'] ?? 'Sin afiliación'));
        if ($afiliacion === '') {
            $afiliacion = 'Sin afiliación';
        }
        if (!isset($bucket[$afiliacion])) {
            $bucket[$afiliacion] = [
                'afiliacion' => $afiliacion,
                'creadas' => 0,
                'programadas' => 0,
                'realizadas' => 0,
            ];
        }

        $bucket[$afiliacion]['creadas']++;
        if ($estado === 'programada') {
            $bucket[$afiliacion]['programadas']++;
        }
        if ($isTerminal) {
            $bucket[$afiliacion]['realizadas']++;
        }
    }

    private function formatAfiliacionMetrics(array $bucket): array
    {
        $result = array_values($bucket);
        usort($result, static fn(array $a, array $b) => ($b['creadas'] ?? 0) <=> ($a['creadas'] ?? 0));
        return $result;
    }

    private function addLeadTimeForAfiliacion(array &$bucket, array $row, ?float $leadHours): void
    {
        if ($leadHours === null) {
            return;
        }
        $afiliacion = trim((string) ($row['afiliacion'] ?? 'Sin afiliación'));
        if ($afiliacion === '') {
            $afiliacion = 'Sin afiliación';
        }
        if (!isset($bucket[$afiliacion])) {
            $bucket[$afiliacion] = [];
        }
        $bucket[$afiliacion][] = $leadHours;
    }

    private function formatLeadByAfiliacion(array $bucket): array
    {
        $result = [];
        foreach ($bucket as $afiliacion => $values) {
            $result[] = [
                'afiliacion' => $afiliacion,
                'lead_time_promedio_horas' => $this->average($values),
                'p50_horas' => $this->percentile($values, 0.5),
                'p90_horas' => $this->percentile($values, 0.9),
                'count' => count($values),
            ];
        }
        usort($result, static fn(array $a, array $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
        return $result;
    }

    private function countNoShows(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $estado = $this->normalizeEstado((string) ($row['estado'] ?? ''));
            if (in_array($estado, self::NO_SHOW_STATES, true)) {
                $count++;
            }
        }
        return $count;
    }

    private function buildDefinitions(): array
    {
        return [
            'throughput.created_series' => [
                'name' => 'Solicitudes creadas por periodo',
                'formula' => 'COUNT(*) agrupado por fecha de created_at.',
                'source_fields' => ['solicitud_procedimiento.created_at'],
            ],
            'throughput.closed_series' => [
                'name' => 'Solicitudes cerradas por periodo',
                'formula' => 'COUNT(*) con estado terminal agrupado por fecha de cierre estimada.',
                'source_fields' => ['solicitud_procedimiento.estado', 'solicitud_checklist.completado_at', 'consulta_data.fecha', 'solicitud_procedimiento.fecha'],
            ],
            'throughput.backlog_total' => [
                'name' => 'Backlog actual',
                'formula' => 'Solicitudes abiertas (estado no terminal).',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'throughput.backlog_by_estado' => [
                'name' => 'Backlog por estado',
                'formula' => 'Conteo de abiertas agrupadas por estado/columna.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'throughput.net_flow' => [
                'name' => 'Net flow',
                'formula' => 'Creadas - Cerradas por periodo.',
                'source_fields' => ['solicitud_procedimiento.created_at', 'solicitud_procedimiento.estado'],
            ],
            'throughput.top_procedimientos' => [
                'name' => 'Top procedimientos',
                'formula' => 'Top 10 procedimientos por volumen y porcentaje del total.',
                'source_fields' => ['solicitud_procedimiento.procedimiento'],
            ],
            'times.lead_time' => [
                'name' => 'Lead time',
                'formula' => 'Tiempo desde created_at hasta cierre estimado (completado/programada/fecha).',
                'source_fields' => ['solicitud_procedimiento.created_at', 'solicitud_checklist.completado_at', 'consulta_data.fecha'],
            ],
            'times.cycle_times.pendiente_a_gestion' => [
                'name' => 'Cycle time pendiente → gestión',
                'formula' => 'Diferencia entre etapa recibida y en-atención.',
                'source_fields' => ['solicitud_checklist.completado_at'],
            ],
            'times.cycle_times.gestion_a_programado' => [
                'name' => 'Cycle time gestión → programado',
                'formula' => 'Diferencia entre en-atención y programada.',
                'source_fields' => ['solicitud_checklist.completado_at'],
            ],
            'times.cycle_times.programado_a_cerrado' => [
                'name' => 'Cycle time programado → cerrado',
                'formula' => 'Diferencia entre programada y cierre estimado.',
                'source_fields' => ['solicitud_checklist.completado_at', 'consulta_data.fecha'],
            ],
            'times.aging' => [
                'name' => 'Aging en estado actual',
                'formula' => 'Ahora - última etapa completada (o created_at).',
                'source_fields' => ['solicitud_checklist.completado_at', 'solicitud_procedimiento.created_at'],
            ],
            'sla.by_status' => [
                'name' => 'Conteo por SLA',
                'formula' => 'Clasificación por vencido/crítico/advertencia/en_rango/sin_fecha/cerrado.',
                'source_fields' => ['consulta_data.fecha', 'solicitud_procedimiento.created_at', 'solicitud_procedimiento.estado'],
            ],
            'sla.overdue_percent_open' => [
                'name' => '% vencidas sobre abiertas',
                'formula' => 'Vencidas / abiertas.',
                'source_fields' => ['consulta_data.fecha', 'solicitud_procedimiento.estado'],
            ],
            'sla.avg_overdue_hours' => [
                'name' => 'Tiempo promedio vencido',
                'formula' => 'Promedio de horas vencidas (deadline - ahora).',
                'source_fields' => ['consulta_data.fecha', 'solicitud_procedimiento.created_at'],
            ],
            'sla.top_risk' => [
                'name' => 'Top solicitudes en riesgo',
                'formula' => 'Top 20 por menor sla_hours_remaining.',
                'source_fields' => ['consulta_data.fecha', 'solicitud_procedimiento.created_at'],
            ],
            'pendientes.apto_anestesia' => [
                'name' => 'Pendientes apto anestesia',
                'formula' => 'Estado = apto-anestesia y no terminal.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'pendientes.apto_oftalmologia' => [
                'name' => 'Pendientes apto oftalmología',
                'formula' => 'Estado = apto-oftalmologo y no terminal.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'pendientes.coberturas' => [
                'name' => 'Pendientes de cobertura',
                'formula' => 'Estado = revision-codigos y no terminal.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'pendientes.documentos_faltantes' => [
                'name' => 'Documentos faltantes',
                'formula' => 'Solicitudes abiertas sin adjuntos CRM.',
                'source_fields' => ['solicitud_crm_adjuntos'],
            ],
            'pendientes.reprogramaciones' => [
                'name' => 'Reprogramaciones',
                'formula' => 'Estado reprogramado/reprogramada.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'calidad.tasa_cancelacion' => [
                'name' => 'Tasa de cancelación',
                'formula' => 'Canceladas / total.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'calidad.tasa_reprogramacion' => [
                'name' => 'Tasa de reprogramación',
                'formula' => 'Reprogramadas / total.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
            'calidad.motivos_cancelacion' => [
                'name' => 'Motivos de cancelación',
                'formula' => 'Top observaciones en canceladas.',
                'source_fields' => ['solicitud_procedimiento.observacion'],
            ],
            'calidad.motivos_reprogramacion' => [
                'name' => 'Motivos de reprogramación',
                'formula' => 'Top observaciones en reprogramadas.',
                'source_fields' => ['solicitud_procedimiento.observacion'],
            ],
            'calidad.sin_datos_criticos' => [
                'name' => 'Solicitudes sin datos críticos',
                'formula' => 'Faltan procedimiento/doctor/afiliación/fecha tentativa.',
                'source_fields' => ['solicitud_procedimiento.procedimiento', 'solicitud_procedimiento.doctor', 'patient_data.afiliacion', 'consulta_data.fecha'],
            ],
            'rendimiento.por_responsable' => [
                'name' => 'Rendimiento por responsable',
                'formula' => 'Cerradas, backlog y lead time promedio por responsable.',
                'source_fields' => ['solicitud_crm_detalles.responsable_id', 'solicitud_procedimiento.estado'],
            ],
            'rendimiento.por_doctor' => [
                'name' => 'Volumen por doctor',
                'formula' => 'Creadas/programadas/realizadas por doctor.',
                'source_fields' => ['solicitud_procedimiento.doctor', 'solicitud_procedimiento.estado'],
            ],
            'rendimiento.por_afiliacion' => [
                'name' => 'Volumen por afiliación',
                'formula' => 'Creadas/programadas/realizadas por afiliación.',
                'source_fields' => ['patient_data.afiliacion', 'solicitud_procedimiento.estado'],
            ],
            'rendimiento.lead_time_por_afiliacion' => [
                'name' => 'Lead time por afiliación',
                'formula' => 'Promedio y percentiles de lead time por afiliación.',
                'source_fields' => ['patient_data.afiliacion', 'solicitud_procedimiento.created_at'],
            ],
            'turnero.turnos_generados' => [
                'name' => 'Turnos generados',
                'formula' => 'Conteo de solicitudes con turno asignado.',
                'source_fields' => ['solicitud_procedimiento.turno', 'solicitud_procedimiento.created_at'],
            ],
            'turnero.tiempo_espera' => [
                'name' => 'Tiempo de espera',
                'formula' => 'Turno creado a en-atención (checklist).',
                'source_fields' => ['solicitud_procedimiento.created_at', 'solicitud_checklist.completado_at'],
            ],
            'turnero.no_shows' => [
                'name' => 'No shows',
                'formula' => 'Estados no show/no procede.',
                'source_fields' => ['solicitud_procedimiento.estado'],
            ],
        ];
    }
}
