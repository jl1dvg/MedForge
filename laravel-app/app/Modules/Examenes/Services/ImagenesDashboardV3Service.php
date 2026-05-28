<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class ImagenesDashboardV3Service
{
    private const MAX_INTERACTIVE_DAYS = 120;
    private const MAX_DETAIL_PER_PAGE = 100;
    private const CACHE_TTL_SECONDS = 600;

    private PDO $db;

    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    /** @var array<string,array<int,string>|null> */
    private array $tableColumnsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DB::connection()->getPdo();
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function dashboardData(array $query): array
    {
        $filters = $this->normalizeFilters($query);
        $cacheKey = 'imagenes-dashboard-v3:' . md5(json_encode($filters, JSON_THROW_ON_ERROR));
        $refresh = trim((string) ($query['refresh'] ?? '')) === '1';

        $build = fn(): array => $this->buildDashboardData($filters);

        return $refresh ? $build() : Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, $build);
    }

    /**
     * @param array<string,mixed> $query
     * @return array{filters:array<string,mixed>,page:int,per_page:int,total:int,rows:array<int,array<string,mixed>>,message:string|null}
     */
    public function detailRows(array $query): array
    {
        $filters = $this->normalizeFilters($query);
        if ($filters['summary_mode']) {
            return [
                'filters' => $filters,
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => 0,
                'rows' => [],
                'message' => 'Rango mayor a 120 días; use export/resumen para evitar cargar detalle masivo.',
            ];
        }

        $params = [
            ':fecha_inicio' => $filters['fecha_inicio'],
            ':fecha_fin' => $filters['fecha_fin'],
        ];
        $where = $this->operationWhereSql($filters, $params);
        $countSql = "SELECT COUNT(*) AS total FROM procedimiento_proyectado pp WHERE {$where}";
        $total = (int) ($this->fetchOne($countSql, $params)['total'] ?? 0);
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $billingJoin = $this->billingRealAggregateJoinSql('pp.form_id');
        $informeJoin = $this->informeAggregateJoinSql();
        $nasJoin = $this->nasAggregateJoinSql();
        $sql = "SELECT
                pp.form_id,
                pp.hc_number,
                pp.fecha,
                pp.hora,
                pp.procedimiento_proyectado,
                pp.estado_agenda,
                COALESCE(pp.afiliacion, '') AS afiliacion,
                COALESCE(pp.doctor, '') AS doctor,
                {$this->sedeExpr('pp')} AS sede,
                CASE WHEN bfr.form_id IS NOT NULL THEN 1 ELSE 0 END AS billing_real,
                COALESCE(bfr.monto_honorario_real, 0) AS monto_honorario_real,
                COALESCE(bfr.monto_facturado_real, 0) AS monto_facturado_real,
                COALESCE(bfr.estado_facturacion_raw, '') AS estado_facturacion_raw,
                COALESCE(ii.informes_total, 0) AS informes_total,
                COALESCE(nas.has_files, 0) AS nas_has_files,
                COALESCE(nas.files_count, 0) AS nas_files_count
            FROM procedimiento_proyectado pp
            {$billingJoin}
            {$informeJoin}
            {$nasJoin}
            WHERE {$where}
            ORDER BY pp.fecha DESC, pp.hora DESC, pp.form_id DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $filters['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['estado_realizacion'] = $this->rowRealizationState($row);
            $row['estado_facturacion'] = $this->rowBillingState($row);
            $rows[] = $row;
        }

        return [
            'filters' => $filters,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $total,
            'rows' => $rows,
            'message' => null,
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function exportPayload(array $query): array
    {
        $payload = $this->dashboardData($query);
        $payload['methodology'] = [
            'Solicitudes recibidas se calculan desde consulta_examenes por fecha de solicitud.',
            'Operación se calcula desde procedimiento_proyectado por fecha de agenda/procedimiento.',
            'Facturado real usa billing_facturacion_real agregado por form_id antes de unirse a operación.',
            'Pendiente de facturar significa realizado con evidencia técnica y sin billing real.',
            'Pendiente de cobrar significa billing real emitido con estado pendiente, crédito o cartera.',
        ];

        return $payload;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function buildDashboardData(array $filters): array
    {
        $started = microtime(true);
        $operation = $this->operationMetrics($filters);
        $requests = $this->requestMetrics($filters);
        $tops = $this->topMetrics($filters);
        $tops['causas_perdida'] = $this->lossCausesFromMetrics($operation, $requests);

        $pendingAmount = (float) ($operation['monto_pendiente_facturar_estimado'] ?? 0);
        $lossAmount = (float) ($operation['monto_perdida_estimada'] ?? 0) + (float) ($requests['solicitudes_sin_agenda_monto_estimado'] ?? 0);

        return [
            'filters' => $filters,
            'meta' => [
                'summary_mode' => $filters['summary_mode'],
                'max_interactive_days' => self::MAX_INTERACTIVE_DAYS,
                'cacheable' => true,
                'generated_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
                'elapsed_ms' => round((microtime(true) - $started) * 1000, 2),
            ],
            'executive' => [
                'facturado_real' => (float) ($operation['monto_facturado_real'] ?? 0),
                'honorario_real' => (float) ($operation['monto_honorario_real'] ?? 0),
                'pendiente_de_facturar' => (int) ($operation['pendiente_de_facturar'] ?? 0),
                'pendiente_de_cobrar' => (int) ($operation['pendiente_de_pago'] ?? 0),
                'perdida_estimada' => round($lossAmount, 2),
                'oportunidad_recuperacion' => round($pendingAmount + (float) ($requests['solicitudes_sin_agenda_monto_estimado'] ?? 0), 2),
            ],
            'solicitudes' => $requests,
            'operacion' => $operation,
            'billing' => [
                'estudios_con_billing_real' => (int) ($operation['estudios_con_billing_real'] ?? 0),
                'monto_honorario_real' => (float) ($operation['monto_honorario_real'] ?? 0),
                'monto_facturado_real' => (float) ($operation['monto_facturado_real'] ?? 0),
                'realizados_sin_billing_real' => (int) ($operation['realizados_sin_billing_real'] ?? 0),
                'pendiente_de_pago' => (int) ($operation['pendiente_de_pago'] ?? 0),
                'pendiente_de_facturar' => (int) ($operation['pendiente_de_facturar'] ?? 0),
            ],
            'oportunidad' => [
                'solicitudes_sin_agenda_valorizadas' => (float) ($requests['solicitudes_sin_agenda_monto_estimado'] ?? 0),
                'ausentes_cancelados_valorizados' => (float) ($operation['monto_perdida_estimada'] ?? 0),
                'realizados_sin_factura' => (int) ($operation['realizados_sin_billing_real'] ?? 0),
                'casos_sin_tarifa_estimable' => (int) ($operation['casos_sin_tarifa_estimable'] ?? 0) + (int) ($requests['solicitudes_sin_agenda'] ?? 0),
                'tops' => $tops,
            ],
            'charts' => [
                'funnel' => [
                    'labels' => ['Solicitudes', 'Agendadas', 'Realizadas', 'Facturadas'],
                    'values' => [
                        (int) ($requests['solicitudes_recibidas'] ?? 0),
                        (int) ($requests['solicitudes_agendadas'] ?? 0),
                        (int) ($requests['solicitudes_realizadas_al_corte'] ?? 0),
                        (int) ($operation['estudios_con_billing_real'] ?? 0),
                    ],
                ],
                'money' => [
                    'labels' => ['Facturado real', 'Pendiente facturar', 'Pérdida estimada'],
                    'values' => [
                        (float) ($operation['monto_facturado_real'] ?? 0),
                        $pendingAmount,
                        $lossAmount,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $query): array
    {
        $start = $this->normalizeDate((string) ($query['fecha_inicio'] ?? ''), 'first day of this month');
        $end = $this->normalizeDate((string) ($query['fecha_fin'] ?? ''), 'last day of this month');
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $days = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;

        return [
            'fecha_inicio' => $start,
            'fecha_fin' => $end,
            'days' => $days,
            'summary_mode' => $days > self::MAX_INTERACTIVE_DAYS,
            'sede' => trim((string) ($query['sede'] ?? '')),
            'afiliacion' => trim((string) ($query['afiliacion'] ?? '')),
            'seguro' => trim((string) ($query['seguro'] ?? '')),
            'tipo_examen' => trim((string) ($query['tipo_examen'] ?? '')),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => min(self::MAX_DETAIL_PER_PAGE, max(1, (int) ($query['per_page'] ?? 50))),
        ];
    }

    private function normalizeDate(string $input, string $fallback): string
    {
        $input = trim($input);
        $date = $input !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $input) : false;

        return $date instanceof DateTimeImmutable
            ? $date->format('Y-m-d')
            : (new DateTimeImmutable($fallback))->format('Y-m-d');
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,int|float>
     */
    private function operationMetrics(array $filters): array
    {
        if (!$this->tableExists('procedimiento_proyectado')) {
            return $this->emptyOperationMetrics();
        }

        $billingJoin = $this->billingRealAggregateJoinSql('pp.form_id');
        $informeJoin = $this->informeAggregateJoinSql();
        $nasJoin = $this->nasAggregateJoinSql();
        $status = $this->normalizedStatusExpr('pp.estado_agenda');
        $billingState = $this->normalizedStatusExpr('bfr.estado_facturacion_raw');
        $realizedExpr = "(bfr.form_id IS NOT NULL OR COALESCE(ii.informes_total, 0) > 0 OR COALESCE(nas.has_files, 0) = 1 OR COALESCE(nas.files_count, 0) > 0 OR {$status} LIKE '%atendid%' OR {$status} LIKE '%pagad%')";
        $cancelExpr = "({$status} LIKE '%cancel%' OR {$status} LIKE '%anul%')";
        $absentExpr = "({$status} LIKE '%ausen%' OR {$status} LIKE '%no asis%')";
        $pendingPayExpr = "(bfr.form_id IS NOT NULL AND ({$billingState} LIKE '%pend%' OR {$billingState} LIKE '%credito%' OR {$billingState} LIKE '%cartera%'))";
        $pendingBillingExpr = "({$realizedExpr} AND bfr.form_id IS NULL)";
        $lossExpr = "(({$cancelExpr} OR {$absentExpr}) AND NOT {$realizedExpr})";
        $params = [
            ':fecha_inicio' => $filters['fecha_inicio'],
            ':fecha_fin' => $filters['fecha_fin'],
        ];
        $where = $this->operationWhereSql($filters, $params);
        $avgExpr = "CASE WHEN SUM(CASE WHEN bfr.form_id IS NOT NULL THEN 1 ELSE 0 END) > 0
            THEN COALESCE(SUM(bfr.monto_honorario_real), 0) / SUM(CASE WHEN bfr.form_id IS NOT NULL THEN 1 ELSE 0 END)
            ELSE 0 END";
        $sql = "SELECT
                COUNT(*) AS agendas_periodo,
                SUM(CASE WHEN {$realizedExpr} THEN 1 ELSE 0 END) AS atendidas,
                SUM(CASE WHEN NOT {$realizedExpr} THEN 1 ELSE 0 END) AS no_atendidas,
                SUM(CASE WHEN NOT {$realizedExpr} AND NOT {$cancelExpr} AND NOT {$absentExpr} THEN 1 ELSE 0 END) AS sin_cierre_operativo,
                SUM(CASE WHEN COALESCE(nas.has_files, 0) = 1 OR COALESCE(nas.files_count, 0) > 0 THEN 1 ELSE 0 END) AS con_archivos_nas,
                SUM(CASE WHEN COALESCE(ii.informes_total, 0) > 0 THEN 1 ELSE 0 END) AS con_informe,
                SUM(CASE WHEN {$realizedExpr} AND COALESCE(ii.informes_total, 0) = 0 THEN 1 ELSE 0 END) AS pendientes_informar,
                SUM(CASE WHEN bfr.form_id IS NOT NULL THEN 1 ELSE 0 END) AS estudios_con_billing_real,
                COALESCE(SUM(bfr.monto_honorario_real), 0) AS monto_honorario_real,
                COALESCE(SUM(bfr.monto_facturado_real), 0) AS monto_facturado_real,
                SUM(CASE WHEN {$pendingBillingExpr} THEN 1 ELSE 0 END) AS realizados_sin_billing_real,
                SUM(CASE WHEN {$pendingBillingExpr} THEN 1 ELSE 0 END) AS pendiente_de_facturar,
                SUM(CASE WHEN {$pendingPayExpr} THEN 1 ELSE 0 END) AS pendiente_de_pago,
                SUM(CASE WHEN {$cancelExpr} AND NOT {$realizedExpr} THEN 1 ELSE 0 END) AS canceladas,
                SUM(CASE WHEN {$absentExpr} AND NOT {$realizedExpr} THEN 1 ELSE 0 END) AS ausentes,
                SUM(CASE WHEN {$lossExpr} THEN 1 ELSE 0 END) AS perdida_operativa,
                SUM(CASE WHEN {$pendingBillingExpr} THEN 1 ELSE 0 END) * ({$avgExpr}) AS monto_pendiente_facturar_estimado,
                SUM(CASE WHEN {$lossExpr} THEN 1 ELSE 0 END) * ({$avgExpr}) AS monto_perdida_estimada,
                CASE WHEN ({$avgExpr}) = 0 THEN SUM(CASE WHEN {$pendingBillingExpr} THEN 1 ELSE 0 END) ELSE 0 END AS casos_sin_tarifa_estimable
            FROM procedimiento_proyectado pp
            {$billingJoin}
            {$informeJoin}
            {$nasJoin}
            WHERE {$where}";

        $row = $this->fetchOne($sql, $params);
        $metrics = $this->emptyOperationMetrics();
        foreach ($metrics as $key => $default) {
            $value = $row[$key] ?? $default;
            $metrics[$key] = is_float($default) ? round((float) $value, 2) : (int) $value;
        }
        $metrics['aging_pendientes_informe_0_2'] = 0;
        $metrics['aging_pendientes_informe_3_7'] = 0;
        $metrics['aging_pendientes_informe_8_14'] = 0;
        $metrics['aging_pendientes_informe_15_plus'] = 0;

        return $metrics;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,int|float|null>
     */
    private function requestMetrics(array $filters): array
    {
        $default = [
            'solicitudes_recibidas' => 0,
            'solicitudes_agendadas' => 0,
            'solicitudes_sin_agenda' => 0,
            'solicitudes_realizadas_al_corte' => 0,
            'solicitudes_realizadas_despues_corte' => 0,
            'solicitudes_vigentes' => 0,
            'solicitudes_vencidas' => 0,
            'solicitudes_ausentes' => 0,
            'solicitudes_canceladas' => 0,
            'solicitudes_sin_agenda_monto_estimado' => 0.0,
        ];
        if (!$this->tableExists('consulta_examenes')) {
            return $default;
        }

        $status = $this->normalizedStatusExpr('pp.estado_agenda');
        $realizedExpr = "(bfr.form_id IS NOT NULL OR COALESCE(ii.informes_total, 0) > 0 OR COALESCE(nas.has_files, 0) = 1 OR COALESCE(nas.files_count, 0) > 0 OR {$status} LIKE '%atendid%' OR {$status} LIKE '%pagad%')";
        $cancelExpr = "({$status} LIKE '%cancel%' OR {$status} LIKE '%anul%')";
        $absentExpr = "({$status} LIKE '%ausen%' OR {$status} LIKE '%no asis%')";
        $billingJoin = $this->billingRealAggregateJoinSql('pp.form_id');
        $informeJoin = $this->informeAggregateJoinSql();
        $nasJoin = $this->nasAggregateJoinSql();
        $ceDate = $this->dateOnlyExpr('ce.consulta_fecha');
        $params = [
            ':fecha_inicio' => $filters['fecha_inicio'],
            ':fecha_fin' => $filters['fecha_fin'],
            ':fecha_corte_lte' => $filters['fecha_fin'],
            ':fecha_corte_gt' => $filters['fecha_fin'],
            ':fecha_hoy_vigente' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            ':fecha_hoy_vencida' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ];
        $filterSql = $this->operationFilterSql($filters, $params, 'pp');
        $sql = "SELECT
                COUNT(*) AS solicitudes_recibidas,
                SUM(CASE WHEN pp.form_id IS NOT NULL THEN 1 ELSE 0 END) AS solicitudes_agendadas,
                SUM(CASE WHEN pp.form_id IS NULL THEN 1 ELSE 0 END) AS solicitudes_sin_agenda,
                SUM(CASE WHEN {$realizedExpr} AND pp.fecha <= :fecha_corte_lte THEN 1 ELSE 0 END) AS solicitudes_realizadas_al_corte,
                SUM(CASE WHEN {$realizedExpr} AND pp.fecha > :fecha_corte_gt THEN 1 ELSE 0 END) AS solicitudes_realizadas_despues_corte,
                SUM(CASE WHEN pp.form_id IS NOT NULL AND NOT {$realizedExpr} AND NOT {$cancelExpr} AND NOT {$absentExpr} AND (pp.fecha IS NULL OR pp.fecha >= :fecha_hoy_vigente) THEN 1 ELSE 0 END) AS solicitudes_vigentes,
                SUM(CASE WHEN pp.form_id IS NOT NULL AND NOT {$realizedExpr} AND NOT {$cancelExpr} AND NOT {$absentExpr} AND pp.fecha < :fecha_hoy_vencida THEN 1 ELSE 0 END) AS solicitudes_vencidas,
                SUM(CASE WHEN {$absentExpr} AND NOT {$realizedExpr} THEN 1 ELSE 0 END) AS solicitudes_ausentes,
                SUM(CASE WHEN {$cancelExpr} AND NOT {$realizedExpr} THEN 1 ELSE 0 END) AS solicitudes_canceladas
            FROM consulta_examenes ce
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ce.form_id AND COALESCE(pp.sigcenter_present, 1) = 1
            {$billingJoin}
            {$informeJoin}
            {$nasJoin}
            WHERE {$ceDate} BETWEEN :fecha_inicio AND :fecha_fin
            {$filterSql}";
        $row = $this->fetchOne($sql, $params);

        foreach ($default as $key => $fallback) {
            if ($key === 'solicitudes_sin_agenda_monto_estimado') {
                continue;
            }
            $default[$key] = (int) ($row[$key] ?? 0);
        }

        return $default;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function topMetrics(array $filters): array
    {
        return [
            'examenes' => $this->topOperationDimension($filters, 'pp.procedimiento_proyectado', 'examen'),
            'sedes' => $this->topOperationDimension($filters, $this->sedeExpr('pp'), 'sede'),
            'seguros' => $this->topOperationDimension($filters, 'COALESCE(pp.afiliacion, \'\')', 'seguro'),
            'doctores_solicitantes' => $this->topRequestDoctors($filters),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private function topOperationDimension(array $filters, string $expr, string $labelKey): array
    {
        if (!$this->tableExists('procedimiento_proyectado')) {
            return [];
        }

        $params = [
            ':fecha_inicio' => $filters['fecha_inicio'],
            ':fecha_fin' => $filters['fecha_fin'],
        ];
        $sql = "SELECT label, COUNT(*) AS total
            FROM (
                SELECT COALESCE(NULLIF(TRIM({$expr}), ''), 'Sin dato') AS label
                FROM procedimiento_proyectado pp
                WHERE {$this->operationWhereSql($filters, $params)}
            ) ranked
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT 8";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                $labelKey => (string) ($row['label'] ?? 'Sin dato'),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private function topRequestDoctors(array $filters): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return [];
        }

        $params = [
            ':fecha_inicio' => $filters['fecha_inicio'],
            ':fecha_fin' => $filters['fecha_fin'],
        ];
        $filterSql = $this->operationFilterSql($filters, $params, 'pp');
        $ceDate = $this->dateOnlyExpr('ce.consulta_fecha');
        $doctorExpr = $this->consultaDoctorSolicitanteExpr();
        $sql = "SELECT doctor, COUNT(*) AS total
            FROM (
                SELECT {$doctorExpr} AS doctor
                FROM consulta_examenes ce
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ce.form_id AND COALESCE(pp.sigcenter_present, 1) = 1
                WHERE {$ceDate} BETWEEN :fecha_inicio AND :fecha_fin
                {$filterSql}
            ) ranked
            GROUP BY doctor
            ORDER BY total DESC, doctor ASC
            LIMIT 8";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'doctor' => (string) ($row['doctor'] ?? 'Sin doctor'),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $rows;
    }

    private function consultaDoctorSolicitanteExpr(): string
    {
        if ($this->columnExists('consulta_examenes', 'doctor_solicitante')) {
            return "COALESCE(NULLIF(TRIM(ce.doctor_solicitante), ''), 'Sin doctor')";
        }
        if ($this->columnExists('consulta_examenes', 'doctor')) {
            return "COALESCE(NULLIF(TRIM(ce.doctor), ''), NULLIF(TRIM(ce.solicitante), ''), 'Sin doctor')";
        }
        if ($this->columnExists('consulta_examenes', 'solicitante')) {
            return "COALESCE(NULLIF(TRIM(ce.solicitante), ''), 'Sin doctor')";
        }

        return "'Sin doctor'";
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    /**
     * @param array<string,int|float> $operation
     * @param array<string,int|float|null> $requests
     * @return array<int,array<string,mixed>>
     */
    private function lossCausesFromMetrics(array $operation, array $requests): array
    {
        $rows = [
            ['causa' => 'Solicitudes sin agenda', 'total' => (int) ($requests['solicitudes_sin_agenda'] ?? 0)],
            ['causa' => 'Cancelados', 'total' => (int) ($operation['canceladas'] ?? 0)],
            ['causa' => 'Ausentes', 'total' => (int) ($operation['ausentes'] ?? 0)],
            ['causa' => 'Realizados sin factura', 'total' => (int) ($operation['realizados_sin_billing_real'] ?? 0)],
            ['causa' => 'Sin cierre operativo', 'total' => (int) ($operation['sin_cierre_operativo'] ?? 0)],
        ];
        usort($rows, static fn(array $a, array $b): int => (int) $b['total'] <=> (int) $a['total']);

        return array_values(array_filter($rows, static fn(array $row): bool => (int) $row['total'] > 0));
    }

    /**
     * @return array<string,int|float>
     */
    private function emptyOperationMetrics(): array
    {
        return [
            'agendas_periodo' => 0,
            'atendidas' => 0,
            'no_atendidas' => 0,
            'sin_cierre_operativo' => 0,
            'con_archivos_nas' => 0,
            'con_informe' => 0,
            'pendientes_informar' => 0,
            'estudios_con_billing_real' => 0,
            'monto_honorario_real' => 0.0,
            'monto_facturado_real' => 0.0,
            'realizados_sin_billing_real' => 0,
            'pendiente_de_facturar' => 0,
            'pendiente_de_pago' => 0,
            'canceladas' => 0,
            'ausentes' => 0,
            'perdida_operativa' => 0,
            'monto_pendiente_facturar_estimado' => 0.0,
            'monto_perdida_estimada' => 0.0,
            'casos_sin_tarifa_estimable' => 0,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $params
     */
    private function operationWhereSql(array $filters, array &$params): string
    {
        return "pp.fecha BETWEEN :fecha_inicio AND :fecha_fin
            AND COALESCE(pp.sigcenter_present, 1) = 1
            AND pp.estado_agenda IS NOT NULL
            AND TRIM(pp.estado_agenda) <> ''
            AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ''))) LIKE 'IMAGENES%'"
            . $this->operationFilterSql($filters, $params, 'pp');
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $params
     */
    private function operationFilterSql(array $filters, array &$params, string $alias): string
    {
        $sql = '';
        $sede = mb_strtolower(trim((string) ($filters['sede'] ?? '')));
        if ($sede !== '') {
            $sql .= " AND LOWER(TRIM({$this->sedeExpr($alias)})) = :filtro_sede";
            $params[':filtro_sede'] = $sede;
        }

        $afiliacion = mb_strtolower(trim((string) ($filters['afiliacion'] ?? '')));
        if ($afiliacion !== '') {
            $sql .= " AND LOWER(TRIM(COALESCE({$alias}.afiliacion, ''))) LIKE :filtro_afiliacion";
            $params[':filtro_afiliacion'] = '%' . $afiliacion . '%';
        }

        $tipoExamen = mb_strtolower(trim((string) ($filters['tipo_examen'] ?? '')));
        if ($tipoExamen !== '') {
            $sql .= " AND LOWER(TRIM(COALESCE({$alias}.procedimiento_proyectado, ''))) LIKE :filtro_tipo_examen";
            $params[':filtro_tipo_examen'] = '%' . $tipoExamen . '%';
        }

        return $sql;
    }

    private function billingRealAggregateJoinSql(string $formIdExpr): string
    {
        if (
            !$this->tableExists('billing_facturacion_real')
            || !$this->columnExists('billing_facturacion_real', 'form_id')
            || !$this->columnExists('billing_facturacion_real', 'monto_honorario')
        ) {
            return "LEFT JOIN (
                SELECT
                    CAST(NULL AS CHAR(50)) AS form_id,
                    CAST(NULL AS DATETIME) AS fecha_facturacion,
                    CAST(NULL AS DATETIME) AS fecha_atencion,
                    0 AS monto_honorario_real,
                    0 AS monto_facturado_real,
                    0 AS procedimientos_facturados,
                    CAST(NULL AS CHAR(100)) AS numero_factura,
                    CAST(NULL AS CHAR(100)) AS factura_id,
                    CAST(NULL AS CHAR(100)) AS estado_facturacion_raw
                WHERE 1 = 0
            ) bfr ON bfr.form_id = CAST({$formIdExpr} AS CHAR)";
        }

        return "LEFT JOIN (
                SELECT
                    CAST(form_id AS CHAR) AS form_id,
                    MAX(fecha_facturacion) AS fecha_facturacion,
                    MAX(fecha_atencion) AS fecha_atencion,
                    COALESCE(SUM(monto_honorario), 0) AS monto_honorario_real,
                    COALESCE(SUM(" . ($this->columnExists('billing_facturacion_real', 'monto_facturado') ? 'monto_facturado' : '0') . "), 0) AS monto_facturado_real,
                    COUNT(*) AS procedimientos_facturados,
                    MAX(" . ($this->columnExists('billing_facturacion_real', 'numero_factura') ? "NULLIF(TRIM(numero_factura), '')" : 'NULL') . ") AS numero_factura,
                    MAX(" . ($this->columnExists('billing_facturacion_real', 'factura_id') ? "NULLIF(TRIM(factura_id), '')" : 'NULL') . ") AS factura_id,
                    MAX(" . ($this->columnExists('billing_facturacion_real', 'estado') ? "NULLIF(TRIM(estado), '')" : 'NULL') . ") AS estado_facturacion_raw
                FROM billing_facturacion_real
                WHERE form_id IS NOT NULL AND TRIM(CAST(form_id AS CHAR)) <> ''
                GROUP BY CAST(form_id AS CHAR)
            ) bfr ON bfr.form_id = CAST({$formIdExpr} AS CHAR)";
    }

    private function informeAggregateJoinSql(): string
    {
        if (!$this->tableExists('imagenes_informes')) {
            return "LEFT JOIN (
                SELECT CAST(NULL AS CHAR(50)) AS form_id, 0 AS informes_total, CAST(NULL AS DATETIME) AS informe_actualizado
                WHERE 1 = 0
            ) ii ON ii.form_id = CAST(pp.form_id AS CHAR)";
        }

        return "LEFT JOIN (
                SELECT CAST(form_id AS CHAR) AS form_id, COUNT(*) AS informes_total, MAX(updated_at) AS informe_actualizado
                FROM imagenes_informes
                WHERE form_id IS NOT NULL AND TRIM(CAST(form_id AS CHAR)) <> ''
                GROUP BY CAST(form_id AS CHAR)
            ) ii ON ii.form_id = CAST(pp.form_id AS CHAR)";
    }

    private function nasAggregateJoinSql(): string
    {
        $table = $this->tableExists('imagenes_nas_index') ? 'imagenes_nas_index' : ($this->tableExists('imagenes_sigcenter_index') ? 'imagenes_sigcenter_index' : '');
        if ($table === '') {
            return "LEFT JOIN (
                SELECT CAST(NULL AS CHAR(50)) AS form_id, 0 AS has_files, 0 AS files_count
                WHERE 1 = 0
            ) nas ON nas.form_id = CAST(pp.form_id AS CHAR)";
        }

        return "LEFT JOIN (
                SELECT CAST(form_id AS CHAR) AS form_id, MAX(COALESCE(has_files, 0)) AS has_files, MAX(COALESCE(files_count, 0)) AS files_count
                FROM {$table}
                WHERE form_id IS NOT NULL AND TRIM(CAST(form_id AS CHAR)) <> ''
                GROUP BY CAST(form_id AS CHAR)
            ) nas ON nas.form_id = CAST(pp.form_id AS CHAR)";
    }

    private function normalizedStatusExpr(string $column): string
    {
        return "LOWER(TRIM(COALESCE({$column}, '')))";
    }

    private function dateOnlyExpr(string $column): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "date({$column})"
            : "DATE({$column})";
    }

    private function sedeExpr(string $alias): string
    {
        if ($this->columnExists('procedimiento_proyectado', 'sede')) {
            return "COALESCE(NULLIF(TRIM({$alias}.sede), ''), NULLIF(TRIM({$alias}.id_sede), ''), 'Sin sede')";
        }

        return "COALESCE(NULLIF(TRIM({$alias}.id_sede), ''), 'Sin sede')";
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function fetchOne(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function rowRealizationState(array $row): string
    {
        $status = mb_strtolower(trim((string) ($row['estado_agenda'] ?? '')));
        if ((int) ($row['billing_real'] ?? 0) === 1) {
            return 'FACTURADA';
        }
        if ((int) ($row['nas_has_files'] ?? 0) === 1 || (int) ($row['nas_files_count'] ?? 0) > 0) {
            return 'REALIZADA_CON_ARCHIVOS';
        }
        if ((int) ($row['informes_total'] ?? 0) > 0) {
            return 'REALIZADA_INFORMADA';
        }
        if (str_contains($status, 'atendid') || str_contains($status, 'pagad')) {
            return 'REALIZADA_AGENDA_CERRADA';
        }
        if (str_contains($status, 'cancel') || str_contains($status, 'anul')) {
            return 'CANCELADA';
        }
        if (str_contains($status, 'ausen') || str_contains($status, 'no asis')) {
            return 'AUSENTE';
        }

        return 'SIN_CIERRE_OPERATIVO';
    }

    /**
     * @param array<string,mixed> $row
     */
    private function rowBillingState(array $row): string
    {
        $raw = mb_strtolower(trim((string) ($row['estado_facturacion_raw'] ?? '')));
        if ($raw !== '' && (str_contains($raw, 'pend') || str_contains($raw, 'credito') || str_contains($raw, 'cartera'))) {
            return 'PENDIENTE_PAGO';
        }
        if ((int) ($row['billing_real'] ?? 0) === 1) {
            return 'FACTURADA';
        }

        $realization = $this->rowRealizationState($row);

        return in_array($realization, ['REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA', 'REALIZADA_AGENDA_CERRADA'], true)
            ? 'PENDIENTE_FACTURAR'
            : 'SIN_FACTURACION';
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        return $this->tableExistsCache[$table] = $this->columnsForTable($table) !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }
        if (!$this->tableExists($table)) {
            return $this->columnExistsCache[$key] = false;
        }

        $columns = $this->columnsForTable($table) ?? [];
        return $this->columnExistsCache[$key] = in_array($column, $columns, true);
    }

    /**
     * @return array<int,string>|null
     */
    private function columnsForTable(string $table): ?array
    {
        if (array_key_exists($table, $this->tableColumnsCache)) {
            return $this->tableColumnsCache[$table];
        }

        try {
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $this->db->query('PRAGMA table_info(' . $table . ')');
                $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
                if ($rows === []) {
                    return $this->tableColumnsCache[$table] = null;
                }

                return $this->tableColumnsCache[$table] = array_values(array_filter(array_map(
                    static fn(array $row): string => (string) ($row['name'] ?? ''),
                    $rows
                )));
            }

            $stmt = $this->db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
            $stmt->execute([':table' => $table]);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($columns) || $columns === []) {
                return $this->tableColumnsCache[$table] = null;
            }

            return $this->tableColumnsCache[$table] = array_values(array_map('strval', $columns));
        } catch (Throwable) {
            return $this->tableColumnsCache[$table] = null;
        }
    }
}
