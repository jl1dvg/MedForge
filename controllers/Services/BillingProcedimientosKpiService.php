<?php

namespace Services;

use DateTimeImmutable;
use PDO;

class BillingProcedimientosKpiService
{
    private PDO $db;

    private array $monthLabels = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    private array $categoryOrder = [
        'Cirugía',
        'Consulta Externa',
        'Exámenes',
        'PNI',
        'Otros',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function build(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $sede = $this->normalizeFilter($filters['sede'] ?? null);
        $tipoCliente = $this->normalizeFilter($filters['tipo_cliente'] ?? ($filters['tipoCliente'] ?? null));

        $rows = $this->fetchProcedimientos($year, $sede, $tipoCliente);

        $monthlyTotals = array_fill(1, 12, 0.0);
        $monthlyCounts = array_fill(1, 12, 0);
        $categorySeries = [];
        $categoryCountSeries = [];
        foreach ($this->categoryOrder as $category) {
            $categorySeries[$category] = array_fill(1, 12, 0.0);
            $categoryCountSeries[$category] = array_fill(1, 12, 0);
        }

        foreach ($rows as $row) {
            $month = (int) ($row['mes'] ?? 0);
            if ($month < 1 || $month > 12) {
                continue;
            }
            $category = $this->mapCategory($row['categoria_id'] ?? null, $row['categoria'] ?? null, $row['cirugia'] ?? null);
            $total = (float) ($row['total'] ?? 0);
            $count = (int) ($row['cantidad'] ?? 0);

            $monthlyTotals[$month] += $total;
            $monthlyCounts[$month] += $count;
            $categorySeries[$category][$month] += $total;
            $categoryCountSeries[$category][$month] += $count;
        }

        $monthlyTotals = array_map(static fn($value) => round($value, 2), $monthlyTotals);
        $monthlyCounts = array_map(static fn($value) => (int) $value, $monthlyCounts);
        $categorySeries = array_map(static function (array $series) {
            return array_map(static fn($value) => round($value, 2), $series);
        }, $categorySeries);
        $categoryCountSeries = array_map(static function (array $series) {
            return array_map(static fn($value) => (int) $value, $series);
        }, $categoryCountSeries);

        $categoryTotals = [];
        $categoryCounts = [];
        foreach ($categorySeries as $category => $series) {
            $categoryTotals[$category] = round(array_sum($series), 2);
            $categoryCounts[$category] = (int) array_sum($categoryCountSeries[$category] ?? []);
        }

        $annualTotal = round(array_sum($monthlyTotals), 2);
        $annualCount = (int) array_sum($monthlyCounts);
        $currentMonth = $this->resolveCurrentMonth($monthlyTotals, $year);
        $ytdTotal = $currentMonth ? round(array_sum(array_slice($monthlyTotals, 0, $currentMonth)), 2) : 0.0;
        $ytdCount = $currentMonth ? (int) array_sum(array_slice($monthlyCounts, 0, $currentMonth)) : 0;
        $runRate = $currentMonth ? round($ytdTotal / $currentMonth, 2) : 0.0;
        $runRateCount = $currentMonth ? round($ytdCount / $currentMonth, 2) : 0.0;

        $momGrowth = $this->calculateMoMGrowth($monthlyTotals, $currentMonth);

        $bestMonth = $this->resolveExtremeMonth($monthlyTotals, 'max');
        $worstMonth = $this->resolveExtremeMonth($monthlyTotals, 'min');

        $topCategory = $this->resolveTopCategory($categoryTotals, $annualTotal);
        $topThree = $this->resolveTopThreeCategories($categoryTotals, $annualTotal);

        $cirugiaShare = $annualTotal > 0
            ? round((($categoryTotals['Cirugía'] ?? 0) / $annualTotal) * 100, 2)
            : 0.0;

        $summaryTable = [];
        foreach ($this->categoryOrder as $category) {
            $series = $categorySeries[$category] ?? array_fill(1, 12, 0.0);
            $total = $categoryTotals[$category] ?? 0.0;
            $share = $annualTotal > 0 ? round(($total / $annualTotal) * 100, 2) : 0.0;
            $peak = $this->resolveExtremeMonth($series, 'max');
            $avg = round($total / 12, 2);
            $count = (int) ($categoryCounts[$category] ?? 0);

            $summaryTable[] = [
                'category' => $category,
                'total' => $total,
                'count' => $count,
                'share' => $share,
                'peak_month' => $peak['label'] ?? null,
                'avg_monthly' => $avg,
            ];
        }

        return [
            'filters' => [
                'year' => $year,
                'sede' => $sede,
                'tipo_cliente' => $tipoCliente,
            ],
            'labels' => array_values($this->monthLabels),
            'monthly_totals' => array_values($monthlyTotals),
            'monthly_counts' => array_values($monthlyCounts),
            'categories' => [
                'order' => $this->categoryOrder,
                'series' => $this->normalizeSeriesOutput($categorySeries),
                'totals' => $categoryTotals,
                'count_series' => $this->normalizeSeriesOutput($categoryCountSeries),
                'counts' => $categoryCounts,
            ],
            'kpis' => [
                'annual_total' => $annualTotal,
                'annual_count' => $annualCount,
                'ytd_total' => $ytdTotal,
                'ytd_count' => $ytdCount,
                'run_rate' => $runRate,
                'run_rate_count' => $runRateCount,
                'mom_growth' => $momGrowth,
                'best_month' => $bestMonth,
                'worst_month' => $worstMonth,
                'top_category' => $topCategory,
                'top_three' => $topThree,
                'cirugia_share' => $cirugiaShare,
            ],
            'summary_table' => $summaryTable,
        ];
    }

    private function fetchProcedimientos(int $year, ?string $sede, ?string $tipoCliente): array
    {
        $dateExpr = "COALESCE(
            NULLIF(NULLIF(pd.fecha_inicio, '0000-00-00'), '0000-00-00 00:00:00'),
            NULLIF(NULLIF(pp.fecha, '0000-00-00'), '0000-00-00 00:00:00'),
            bm.created_at
        )";
        $ppCatalogTextExpr = $this->normalizeSqlCatalogText("COALESCE(pp.procedimiento_proyectado, '')");
        $bpCatalogTextExpr = $this->normalizeSqlCatalogText("COALESCE(bp.proc_detalle, '')");
        $bpCodigoExpr = "UPPER(TRIM(NULLIF(bp.proc_codigo, '')))";
        $pcCodigoExpr = "UPPER(TRIM(pc.codigo))";
        $derivacionesJoin = $this->tableExists('derivaciones_form_id')
            ? 'LEFT JOIN derivaciones_form_id df ON df.form_id = bm.form_id'
            : '';
        $sedeExpr = $this->normalizeSqlKey($this->resolveSedeExpr($derivacionesJoin !== ''));
        $afiliacionExpr = $this->resolveAffiliationExpr($derivacionesJoin !== '');
        $clienteContext = $this->resolveClienteCategoryContext($afiliacionExpr);
        $catalogJoin = '';
        $categorySelect = 'NULL AS categoria_id';
        $groupByCatalog = '';

        if ($this->tableExists('proc_catalogo')) {
            $categorySelect = 'pc.categoria_id AS categoria_id';
            $groupByCatalog = ', pc.categoria_id';

            if ($this->columnExists('proc_catalogo', 'codigo')) {
                $catalogJoin = "LEFT JOIN proc_catalogo pc
                                ON (
                                    ($bpCodigoExpr <> '' AND ($pcCodigoExpr COLLATE utf8mb4_unicode_ci) = ($bpCodigoExpr COLLATE utf8mb4_unicode_ci))
                                    OR INSTR(CONCAT('-', $ppCatalogTextExpr, '-'), CONCAT('-', ($pcCodigoExpr COLLATE utf8mb4_unicode_ci), '-')) > 0
                                    OR INSTR(CONCAT('-', $bpCatalogTextExpr, '-'), CONCAT('-', ($pcCodigoExpr COLLATE utf8mb4_unicode_ci), '-')) > 0
                                )";
            } elseif ($this->columnExists('proc_catalogo', 'raw_norm')) {
                $catalogJoin = "LEFT JOIN proc_catalogo pc
                                ON (pc.raw_norm COLLATE utf8mb4_unicode_ci)
                                 = (" . $this->normalizeSqlKey("COALESCE(NULLIF(bp.proc_detalle, ''), bp.proc_codigo, '')") . " COLLATE utf8mb4_unicode_ci)";
            } else {
                $categorySelect = 'NULL AS categoria_id';
                $groupByCatalog = '';
            }
        }

        $sql = "
            SELECT
                MONTH($dateExpr) AS mes,
                $categorySelect,
                pr.categoria AS categoria,
                pr.cirugia AS cirugia,
                SUM(bp.proc_precio) AS total,
                COUNT(*) AS cantidad
            FROM billing_procedimientos bp
            INNER JOIN billing_main bm ON bm.id = bp.billing_id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            $derivacionesJoin
            LEFT JOIN procedimientos pr ON pr.id = bp.procedimiento_id
            $catalogJoin
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            {$clienteContext['join']}
            WHERE YEAR($dateExpr) = :year
        ";

        $params = [':year' => $year];

        if ($sede && $sede !== 'todos') {
            $sql .= " AND $sedeExpr LIKE :sede";
            $params[':sede'] = '%' . $sede . '%';
        }

        if ($tipoCliente && $tipoCliente !== 'todos') {
            $sql .= " AND {$clienteContext['expr']} = :cliente_categoria";
            $params[':cliente_categoria'] = $tipoCliente;
        }

        $sql .= " GROUP BY mes$groupByCatalog, pr.categoria, pr.cirugia ORDER BY mes ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function detail(array $filters, int $limit = 500): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $sede = $this->normalizeFilter($filters['sede'] ?? null);
        $tipoCliente = $this->normalizeFilter($filters['tipo_cliente'] ?? ($filters['tipoCliente'] ?? null));
        $categoria = trim((string) ($filters['categoria'] ?? ''));
        $limit = max(1, min($limit, 5000));

        $rows = $this->fetchDetalleProcedimientos($year, $sede, $tipoCliente, $limit);
        $output = [];

        foreach ($rows as $row) {
            $mapped = $this->mapCategory($row['categoria_id'] ?? null, $row['categoria'] ?? null, $row['cirugia'] ?? null);
            if ($categoria !== '' && strcasecmp($mapped, $categoria) !== 0) {
                continue;
            }

            $output[] = [
                'fecha' => $row['fecha'] ?? null,
                'form_id' => $row['form_id'] ?? null,
                'hc_number' => $row['hc_number'] ?? null,
                'paciente' => $row['paciente'] ?? null,
                'afiliacion' => $row['afiliacion'] ?? null,
                'tipo_cliente' => $row['tipo_cliente'] ?? null,
                'categoria' => $mapped,
                'codigo' => $row['codigo'] ?? null,
                'detalle' => $row['detalle'] ?? null,
                'valor' => round((float) ($row['valor'] ?? 0), 2),
            ];
        }

        return [
            'filters' => [
                'year' => $year,
                'sede' => $sede,
                'tipo_cliente' => $tipoCliente,
                'categoria' => $categoria,
            ],
            'total' => count($output),
            'rows' => $output,
        ];
    }

    private function fetchDetalleProcedimientos(int $year, ?string $sede, ?string $tipoCliente, int $limit): array
    {
        $dateExpr = "COALESCE(
            NULLIF(NULLIF(pd.fecha_inicio, '0000-00-00'), '0000-00-00 00:00:00'),
            NULLIF(NULLIF(pp.fecha, '0000-00-00'), '0000-00-00 00:00:00'),
            bm.created_at
        )";
        $ppCatalogTextExpr = $this->normalizeSqlCatalogText("COALESCE(pp.procedimiento_proyectado, '')");
        $bpCatalogTextExpr = $this->normalizeSqlCatalogText("COALESCE(bp.proc_detalle, '')");
        $bpCodigoExpr = "UPPER(TRIM(NULLIF(bp.proc_codigo, '')))";
        $pcCodigoExpr = "UPPER(TRIM(pc.codigo))";
        $derivacionesJoin = $this->tableExists('derivaciones_form_id')
            ? 'LEFT JOIN derivaciones_form_id df ON df.form_id = bm.form_id'
            : '';
        $sedeExpr = $this->normalizeSqlKey($this->resolveSedeExpr($derivacionesJoin !== ''));
        $afiliacionExpr = $this->resolveAffiliationExpr($derivacionesJoin !== '');
        $clienteContext = $this->resolveClienteCategoryContext($afiliacionExpr);
        $catalogJoin = '';
        $categorySelect = 'NULL AS categoria_id';

        if ($this->tableExists('proc_catalogo')) {
            $categorySelect = 'pc.categoria_id AS categoria_id';
            if ($this->columnExists('proc_catalogo', 'codigo')) {
                $catalogJoin = "LEFT JOIN proc_catalogo pc
                                ON (
                                    ($bpCodigoExpr <> '' AND ($pcCodigoExpr COLLATE utf8mb4_unicode_ci) = ($bpCodigoExpr COLLATE utf8mb4_unicode_ci))
                                    OR INSTR(CONCAT('-', $ppCatalogTextExpr, '-'), CONCAT('-', ($pcCodigoExpr COLLATE utf8mb4_unicode_ci), '-')) > 0
                                    OR INSTR(CONCAT('-', $bpCatalogTextExpr, '-'), CONCAT('-', ($pcCodigoExpr COLLATE utf8mb4_unicode_ci), '-')) > 0
                                )";
            } elseif ($this->columnExists('proc_catalogo', 'raw_norm')) {
                $catalogJoin = "LEFT JOIN proc_catalogo pc
                                ON (pc.raw_norm COLLATE utf8mb4_unicode_ci)
                                 = (" . $this->normalizeSqlKey("COALESCE(NULLIF(bp.proc_detalle, ''), bp.proc_codigo, '')") . " COLLATE utf8mb4_unicode_ci)";
            }
        }

        $sql = "
            SELECT
                DATE($dateExpr) AS fecha,
                bm.form_id,
                bm.hc_number,
                CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                $afiliacionExpr AS afiliacion,
                {$clienteContext['expr']} AS tipo_cliente,
                $categorySelect,
                pr.categoria AS categoria,
                pr.cirugia AS cirugia,
                COALESCE(NULLIF(bp.proc_codigo, ''), pc.codigo, '') AS codigo,
                COALESCE(NULLIF(bp.proc_detalle, ''), pp.procedimiento_proyectado, '') AS detalle,
                bp.proc_precio AS valor
            FROM billing_procedimientos bp
            INNER JOIN billing_main bm ON bm.id = bp.billing_id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            $derivacionesJoin
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            LEFT JOIN procedimientos pr ON pr.id = bp.procedimiento_id
            $catalogJoin
            {$clienteContext['join']}
            WHERE YEAR($dateExpr) = :year
        ";

        $params = [':year' => $year];

        if ($sede && $sede !== 'todos') {
            $sql .= " AND $sedeExpr LIKE :sede";
            $params[':sede'] = '%' . $sede . '%';
        }

        if ($tipoCliente && $tipoCliente !== 'todos') {
            $sql .= " AND {$clienteContext['expr']} = :cliente_categoria";
            $params[':cliente_categoria'] = $tipoCliente;
        }

        $sql .= " ORDER BY fecha DESC, bm.form_id DESC LIMIT :limit_rows";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function mapCategory($categoriaId, ?string $categoria, ?string $cirugiaFlag): string
    {
        if ($categoriaId !== null && $categoriaId !== '') {
            switch ((int) $categoriaId) {
                case 5:
                    return 'Cirugía';
                case 2:
                    return 'Consulta Externa';
                case 3:
                    return 'Exámenes';
                case 6:
                    return 'PNI';
            }
        }

        $categoria = strtoupper(trim((string) $categoria));
        $cirugiaFlag = strtoupper(trim((string) $cirugiaFlag));
        if ($cirugiaFlag !== '' && $cirugiaFlag !== '0' && $cirugiaFlag !== 'NO') {
            return 'Cirugía';
        }

        if ($categoria === '') {
            return 'Otros';
        }

        if (str_contains($categoria, 'CIRUG')) {
            return 'Cirugía';
        }

        if (str_contains($categoria, 'PNI')) {
            return 'PNI';
        }

        if (str_contains($categoria, 'CONSULT')) {
            return 'Consulta Externa';
        }

        if (str_contains($categoria, 'IMAGEN') || str_contains($categoria, 'EXAMEN')) {
            return 'Exámenes';
        }

        return 'Otros';
    }

    private function resolveCurrentMonth(array $monthlyTotals, int $year): int
    {
        $lastWithData = 0;
        foreach ($monthlyTotals as $month => $value) {
            if ($value > 0) {
                $lastWithData = (int) $month;
            }
        }

        if ($lastWithData > 0) {
            return $lastWithData;
        }

        $currentYear = (int) (new DateTimeImmutable())->format('Y');
        if ($year === $currentYear) {
            return (int) (new DateTimeImmutable())->format('n');
        }

        return 12;
    }

    private function calculateMoMGrowth(array $monthlyTotals, int $currentMonth): ?float
    {
        if ($currentMonth < 2) {
            return null;
        }

        $current = $monthlyTotals[$currentMonth] ?? 0.0;
        $previous = $monthlyTotals[$currentMonth - 1] ?? 0.0;

        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function resolveExtremeMonth(array $monthlyTotals, string $mode): ?array
    {
        $values = array_filter($monthlyTotals, static fn($value) => $value > 0);
        if ($values === []) {
            return null;
        }

        $target = $mode === 'min' ? min($values) : max($values);
        $month = array_search($target, $monthlyTotals, true);

        if (!$month) {
            return null;
        }

        return [
            'month' => (int) $month,
            'label' => $this->monthLabels[$month] ?? null,
            'total' => round($target, 2),
        ];
    }

    private function resolveTopCategory(array $totals, float $annualTotal): ?array
    {
        if ($annualTotal <= 0) {
            return null;
        }

        arsort($totals);
        $category = array_key_first($totals);
        $total = $totals[$category] ?? 0.0;

        return [
            'category' => $category,
            'total' => $total,
            'share' => $annualTotal > 0 ? round(($total / $annualTotal) * 100, 2) : 0.0,
        ];
    }

    private function resolveTopThreeCategories(array $totals, float $annualTotal): array
    {
        if ($annualTotal <= 0) {
            return [];
        }

        arsort($totals);
        $top = array_slice($totals, 0, 3, true);
        $result = [];

        foreach ($top as $category => $total) {
            $result[] = [
                'category' => $category,
                'total' => $total,
                'share' => $annualTotal > 0 ? round(($total / $annualTotal) * 100, 2) : 0.0,
            ];
        }

        return $result;
    }

    private function normalizeSeriesOutput(array $series): array
    {
        $output = [];
        foreach ($series as $category => $values) {
            $output[$category] = array_values($values);
        }
        return $output;
    }

    private function resolveClienteCategoryContext(string $rawAffiliationExpr): array
    {
        $afiliacionNormExpr = $this->normalizeSqlKey($rawAffiliationExpr);
        $fallbackExpr = "CASE
            WHEN $afiliacionNormExpr = '' THEN 'otros'
            WHEN $afiliacionNormExpr LIKE '%particular%' THEN 'particular'
            WHEN $afiliacionNormExpr LIKE '%fundacion%' OR $afiliacionNormExpr LIKE '%fundacional%' THEN 'fundacional'
            WHEN $afiliacionNormExpr REGEXP 'iess|issfa|isspol|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario' THEN 'publico'
            ELSE 'privado'
        END";

        if (
            $this->tableExists('afiliacion_categoria_map')
            && $this->columnExists('afiliacion_categoria_map', 'afiliacion_norm')
            && $this->columnExists('afiliacion_categoria_map', 'categoria')
        ) {
            $join = "LEFT JOIN afiliacion_categoria_map acm
                     ON (acm.afiliacion_norm COLLATE utf8mb4_unicode_ci)
                      = ($afiliacionNormExpr COLLATE utf8mb4_unicode_ci)";
            $expr = "LOWER(COALESCE(NULLIF(acm.categoria, ''), $fallbackExpr))";

            return ['join' => $join, 'expr' => $expr];
        }

        return ['join' => '', 'expr' => $fallbackExpr];
    }

    private function resolveAffiliationExpr(bool $hasDerivacionesJoin): string
    {
        $parts = [];

        if ($hasDerivacionesJoin && $this->columnExists('derivaciones_form_id', 'payer')) {
            $parts[] = 'df.payer';
        }
        if ($hasDerivacionesJoin && $this->columnExists('derivaciones_form_id', 'afiliacion_raw')) {
            $parts[] = 'df.afiliacion_raw';
        }
        if ($this->columnExists('procedimiento_proyectado', 'afiliacion')) {
            $parts[] = 'pp.afiliacion';
        }
        if ($this->columnExists('patient_data', 'afiliacion')) {
            $parts[] = 'pa.afiliacion';
        }
        $parts[] = "''";

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function resolveSedeExpr(bool $hasDerivacionesJoin): string
    {
        $parts = [];

        if ($this->columnExists('procedimiento_proyectado', 'sede_departamento')) {
            $parts[] = 'pp.sede_departamento';
        }
        if ($this->columnExists('procedimiento_proyectado', 'id_sede')) {
            $parts[] = 'pp.id_sede';
        }
        if ($hasDerivacionesJoin && $this->columnExists('derivaciones_form_id', 'sede')) {
            $parts[] = 'df.sede';
        }
        $parts[] = "''";

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function normalizeFilter($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
        $value = str_replace([' ', '-'], '_', $value);

        return $value;
    }

    private function normalizeSqlText(string $expr): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($expr, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N'), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'))";
    }

    private function normalizeSqlCatalogText(string $expr): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($expr, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N')), ' ', '-'), '/', '-'), '(', '-'), ')', '-'), '.', '-'), ',', '-'), ':', '-'), ';', '-'), '_', '-'), '--', '-')";
    }

    private function normalizeSqlKey(string $expr): string
    {
        $normalized = $this->normalizeSqlText($expr);

        return "REPLACE(REPLACE($normalized, ' ', '_'), '-', '_')";
    }

    private function tableExists(string $table): bool
    {
        $sql = "SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':table_name' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
                  AND column_name = :column_name
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
