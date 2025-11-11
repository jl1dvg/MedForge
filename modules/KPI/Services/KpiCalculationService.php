<?php

declare(strict_types=1);

namespace Modules\KPI\Services;

use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Modules\KPI\Models\KpiSnapshot;
use Modules\KPI\Models\KpiSnapshotModel;
use Modules\KPI\Support\KpiRegistry;
use PDO;
use RuntimeException;

class KpiCalculationService
{
    private const REINGRESO_WINDOW_DAYS = 30;
    private KpiSnapshotModel $snapshotModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->snapshotModel = new KpiSnapshotModel($pdo);
    }

    /**
     * @param DatePeriod $period Periodo que define fechas de inicio inclusivas.
     * @param array<int, string> $kpiKeys
     *
     * @return array<int, array<string, mixed>>
     */
    public function recalculateRange(DatePeriod $period, array $kpiKeys = []): array
    {
        $keys = $this->resolveKpiKeys($kpiKeys);
        $results = [];

        foreach ($period as $date) {
            if (!$date instanceof DateTimeInterface) {
                continue;
            }

            $results = array_merge($results, $this->recalculateForDate(DateTimeImmutable::createFromInterface($date), $keys));
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function recalculateSingle(string $kpiKey, DateTimeInterface $periodStart): array
    {
        $keys = $this->resolveKpiKeys([$kpiKey]);
        $results = $this->recalculateForDate(DateTimeImmutable::createFromInterface($periodStart), $keys);

        return $results[$kpiKey] ?? [];
    }

    /**
     * @param array<int, string> $keys
     *
     * @return array<string, array<string, mixed>>
     */
    private function recalculateForDate(DateTimeImmutable $periodStart, array $keys): array
    {
        $periodStart = $periodStart->setTime(0, 0, 0);
        $periodEnd = $periodStart;

        $grouped = $this->groupByCalculator($keys);
        $results = [];

        foreach ($grouped as $calculator => $calculatorKeys) {
            $calculatorResults = match ($calculator) {
                'solicitudes' => $this->calculateSolicitudes($periodStart, $periodEnd, $calculatorKeys),
                'crm_tasks' => $this->calculateCrmTasks($periodStart, $periodEnd, $calculatorKeys),
                'protocolos_revision' => $this->calculateProtocolosRevision($periodStart, $periodEnd, $calculatorKeys),
                'reingresos' => $this->calculateReingresosMismoDiagnostico($periodStart, $periodEnd, $calculatorKeys),
                default => throw new RuntimeException(sprintf('Calculadora "%s" no implementada.', $calculator)),
            };

            foreach ($calculatorResults as $kpiKey => $payload) {
                $snapshot = new KpiSnapshot(
                    kpiKey: $kpiKey,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    granularity: 'daily',
                    dimensions: $payload['dimensions'] ?? [],
                    value: (float) $payload['value'],
                    numerator: isset($payload['numerator']) ? (float) $payload['numerator'] : null,
                    denominator: isset($payload['denominator']) ? (float) $payload['denominator'] : null,
                    extra: $payload['extra'] ?? null,
                    sourceVersion: KpiRegistry::SOURCE_VERSION,
                );

                $this->snapshotModel->upsert($snapshot);
                $results[$kpiKey] = [
                    'value' => $snapshot->value,
                    'numerator' => $snapshot->numerator,
                    'denominator' => $snapshot->denominator,
                    'extra' => $snapshot->extra,
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                ];
            }
        }

        return $results;
    }

    /**
     * @param array<int, string> $keys
     *
     * @return array<string, array<int, string>>
     */
    private function groupByCalculator(array $keys): array
    {
        $definitions = KpiRegistry::all();
        $grouped = [];

        foreach ($keys as $key) {
            if (!isset($definitions[$key])) {
                continue;
            }

            $calculator = $definitions[$key]['calculator'];
            $grouped[$calculator] ??= [];
            $grouped[$calculator][] = $key;
        }

        return $grouped;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, array<string, mixed>>
     */
    private function calculateSolicitudes(DateTimeImmutable $start, DateTimeImmutable $end, array $keys): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*) AS registradas,
                SUM(CASE WHEN TRIM(COALESCE(sp.turno, '')) != '' THEN 1 ELSE 0 END) AS agendadas,
                SUM(CASE WHEN LOWER(COALESCE(sp.prioridad, '')) = 'urgente' AND TRIM(COALESCE(sp.turno, '')) = '' THEN 1 ELSE 0 END) AS urgentes_sin_turno
            FROM solicitud_procedimiento sp
            WHERE sp.procedimiento IS NOT NULL
              AND sp.procedimiento != ''
              AND sp.procedimiento != 'SELECCIONE'
              AND COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['registradas' => 0, 'agendadas' => 0, 'urgentes_sin_turno' => 0];

        $cirugiaStmt = $this->pdo->prepare(
            <<<'SQL'
            SELECT COUNT(DISTINCT sp.id) AS total
            FROM solicitud_procedimiento sp
            INNER JOIN protocolo_data pr ON pr.form_id = sp.form_id AND pr.hc_number = sp.hc_number
            WHERE sp.procedimiento IS NOT NULL
              AND sp.procedimiento != ''
              AND sp.procedimiento != 'SELECCIONE'
              AND COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio_solicitud AND :fin_solicitud
              AND pr.fecha_inicio BETWEEN :inicio_protocolo AND :fin_protocolo
            SQL
        );

        $cirugiaStmt->execute([
            ':inicio_solicitud' => $start->format('Y-m-d 00:00:00'),
            ':fin_solicitud' => $end->format('Y-m-d 23:59:59'),
            ':inicio_protocolo' => $start->format('Y-m-d 00:00:00'),
            ':fin_protocolo' => $end->format('Y-m-d 23:59:59'),
        ]);
        $conCirugia = (int) ($cirugiaStmt->fetchColumn() ?: 0);

        $registradas = (int) ($totals['registradas'] ?? 0);
        $agendadas = (int) ($totals['agendadas'] ?? 0);
        $urgentes = (int) ($totals['urgentes_sin_turno'] ?? 0);
        $conversion = $registradas > 0 ? round(($agendadas / $registradas) * 100, 2) : 0.0;

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = match ($key) {
                'solicitudes.registradas' => ['value' => $registradas],
                'solicitudes.agendadas' => ['value' => $agendadas],
                'solicitudes.urgentes_sin_turno' => ['value' => $urgentes],
                'solicitudes.con_cirugia' => ['value' => $conCirugia],
                'solicitudes.conversion_agendada' => [
                    'value' => $conversion,
                    'numerator' => $agendadas,
                    'denominator' => $registradas,
                    'extra' => ['registradas' => $registradas, 'agendadas' => $agendadas],
                ],
                default => ['value' => 0],
            };
        }

        return $results;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, array<string, mixed>>
     */
    private function calculateCrmTasks(DateTimeImmutable $start, DateTimeImmutable $end, array $keys): array
    {
        $sql = <<<'SQL'
            SELECT
                SUM(CASE WHEN t.estado IN ('pendiente', 'en_progreso') THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN t.estado IN ('completada', 'completado') THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN t.estado IN ('pendiente', 'en_progreso') AND t.due_date IS NOT NULL AND t.due_date < CURDATE() THEN 1 ELSE 0 END) AS vencidas
            FROM solicitud_crm_tareas t
            INNER JOIN solicitud_procedimiento sp ON sp.id = t.solicitud_id
            WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pendientes = (int) ($row['pendientes'] ?? 0);
        $completadas = (int) ($row['completadas'] ?? 0);
        $vencidas = (int) ($row['vencidas'] ?? 0);
        $total = $pendientes + $completadas;
        $avance = $total > 0 ? round(($completadas / $total) * 100, 2) : 0.0;

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = match ($key) {
                'crm.tareas.pendientes' => ['value' => $pendientes],
                'crm.tareas.completadas' => ['value' => $completadas],
                'crm.tareas.vencidas' => ['value' => $vencidas],
                'crm.tareas.avance' => [
                    'value' => $avance,
                    'numerator' => $completadas,
                    'denominator' => $total,
                    'extra' => ['pendientes' => $pendientes, 'completadas' => $completadas, 'total' => $total],
                ],
                default => ['value' => 0],
            };
        }

        return $results;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, array<string, mixed>>
     */
    private function calculateProtocolosRevision(DateTimeImmutable $start, DateTimeImmutable $end, array $keys): array
    {
        $sql = <<<'SQL'
            SELECT
                pr.status,
                pr.membrete,
                pr.dieresis,
                pr.exposicion,
                pr.hallazgo,
                pr.operatorio,
                pr.complicaciones_operatorio,
                pr.datos_cirugia,
                pr.procedimientos,
                pr.lateralidad,
                pr.tipo_anestesia,
                pr.diagnosticos,
                pp.procedimiento_proyectado,
                pr.cirujano_1,
                pr.instrumentista,
                pr.cirujano_2,
                pr.circulante,
                pr.primer_ayudante,
                pr.anestesiologo,
                pr.segundo_ayudante,
                pr.ayudante_anestesia,
                pr.tercer_ayudante
            FROM protocolo_data pr
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $invalidValues = ['CENTER', 'undefined'];
        $incompletos = 0;
        $revisados = 0;
        $noRevisados = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ((int) ($row['status'] ?? 0) === 1) {
                $revisados++;
                continue;
            }

            $required = [
                $row['membrete'],
                $row['dieresis'],
                $row['exposicion'],
                $row['hallazgo'],
                $row['operatorio'],
                $row['complicaciones_operatorio'],
                $row['datos_cirugia'],
                $row['procedimientos'],
                $row['lateralidad'],
                $row['tipo_anestesia'],
                $row['diagnosticos'],
                $row['procedimiento_proyectado'],
            ];

            $staff = [
                $row['cirujano_1'],
                $row['instrumentista'],
                $row['cirujano_2'],
                $row['circulante'],
                $row['primer_ayudante'],
                $row['anestesiologo'],
                $row['segundo_ayudante'],
                $row['ayudante_anestesia'],
                $row['tercer_ayudante'],
            ];

            $invalid = false;

            foreach ($required as $value) {
                if ($value === null || $value === '') {
                    $invalid = true;
                    break;
                }

                foreach ($invalidValues as $invalidValue) {
                    if (stripos((string) $value, $invalidValue) !== false) {
                        $invalid = true;
                        break 2;
                    }
                }
            }

            if (trim((string) ($row['cirujano_1'] ?? '')) === '') {
                $invalid = true;
            }

            $staffCount = 0;
            if (!$invalid) {
                foreach ($staff as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $containsInvalid = false;
                    foreach ($invalidValues as $invalidValue) {
                        if (stripos((string) $value, $invalidValue) !== false) {
                            $containsInvalid = true;
                            break;
                        }
                    }

                    if ($containsInvalid) {
                        $invalid = true;
                        break;
                    }

                    $staffCount++;
                }
            }

            if (!$invalid && $staffCount >= 5) {
                $noRevisados++;
            } else {
                $incompletos++;
            }
        }

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = match ($key) {
                'protocolos.revision.revisados' => ['value' => $revisados],
                'protocolos.revision.no_revisados' => ['value' => $noRevisados],
                'protocolos.revision.incompletos' => ['value' => $incompletos],
                default => ['value' => 0],
            };
        }

        return $results;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, array<string, mixed>>
     */
    private function calculateReingresosMismoDiagnostico(DateTimeImmutable $start, DateTimeImmutable $end, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $windowStart = $start->modify(sprintf('-%d days', self::REINGRESO_WINDOW_DAYS));

        $sql = <<<'SQL'
            SELECT
                p.form_id,
                p.hc_number,
                p.fecha_inicio,
                dx.dx_code
            FROM protocolo_data p
            INNER JOIN diagnosticos_asignados dx ON dx.form_id = p.form_id
                AND dx.fuente = 'protocolo'
                AND dx.definitivo = 1
            WHERE p.fecha_inicio BETWEEN :inicio_ventana AND :fin
              AND p.hc_number IS NOT NULL
              AND p.hc_number != ''
              AND dx.dx_code IS NOT NULL
              AND dx.dx_code != ''
            ORDER BY p.hc_number, dx.dx_code, p.fecha_inicio, p.form_id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':inicio_ventana' => $windowStart->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        /** @var array<string, array<int, DateTimeImmutable>> $history */
        $history = [];
        $totalEpisodes = [];
        $readmissions = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rawDate = $row['fecha_inicio'] ?? '';

            if ($rawDate === null || $rawDate === '') {
                continue;
            }

            try {
                $eventDate = new DateTimeImmutable((string) $rawDate);
            } catch (Exception) {
                continue;
            }

            $patient = trim((string) ($row['hc_number'] ?? ''));
            $diagnosis = trim((string) ($row['dx_code'] ?? ''));

            if ($patient === '' || $diagnosis === '') {
                continue;
            }

            $patientDiagnosisKey = $patient . '|' . $diagnosis;
            $history[$patientDiagnosisKey] ??= [];

            $cutoff = $eventDate->modify(sprintf('-%d days', self::REINGRESO_WINDOW_DAYS));
            $history[$patientDiagnosisKey] = array_values(array_filter(
                $history[$patientDiagnosisKey],
                static fn (DateTimeImmutable $date): bool => $date >= $cutoff
            ));

            if ($eventDate >= $start && $eventDate <= $end) {
                $formId = trim((string) ($row['form_id'] ?? ''));
                $episodeKey = $formId !== ''
                    ? $formId . '|' . $diagnosis
                    : $eventDate->format(DateTimeImmutable::ATOM) . '|' . $patientDiagnosisKey;

                if (!isset($totalEpisodes[$episodeKey])) {
                    $totalEpisodes[$episodeKey] = true;

                    if ($history[$patientDiagnosisKey] !== []) {
                        $readmissions[$episodeKey] = true;
                    }
                }
            }

            $history[$patientDiagnosisKey][] = $eventDate;
        }

        $totalCount = count($totalEpisodes);
        $readmissionsCount = count($readmissions);
        $rate = $totalCount > 0 ? round(($readmissionsCount / $totalCount) * 100, 2) : 0.0;

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = match ($key) {
                'reingresos.mismo_diagnostico.total' => [
                    'value' => $readmissionsCount,
                    'extra' => [
                        'episodios' => $totalCount,
                        'ventana_dias' => self::REINGRESO_WINDOW_DAYS,
                    ],
                ],
                'reingresos.mismo_diagnostico.tasa' => [
                    'value' => $rate,
                    'numerator' => $readmissionsCount,
                    'denominator' => $totalCount,
                    'extra' => [
                        'episodios' => $totalCount,
                        'ventana_dias' => self::REINGRESO_WINDOW_DAYS,
                    ],
                ],
                default => ['value' => 0],
            };
        }

        return $results;
    }

    /**
     * @param array<int, string> $kpiKeys
     * @return array<int, string>
     */
    private function resolveKpiKeys(array $kpiKeys): array
    {
        if ($kpiKeys === []) {
            return array_keys(KpiRegistry::all());
        }

        $definitions = KpiRegistry::all();

        return array_values(array_filter($kpiKeys, static function (string $key) use ($definitions): bool {
            return isset($definitions[$key]);
        }));
    }
}
