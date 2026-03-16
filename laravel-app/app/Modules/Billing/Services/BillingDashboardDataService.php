<?php

namespace App\Modules\Billing\Services;

use App\Modules\Shared\Support\AfiliacionDimensionService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;

class BillingDashboardDataService
{
    private PDO $db;
    private BillingLeakageService $leakageService;
    private AfiliacionDimensionService $afiliacionDimensions;

    public function __construct(?PDO $db = null, ?BillingLeakageService $leakageService = null)
    {
        $this->db = $db ?? DB::connection()->getPdo();
        $this->leakageService = $leakageService ?? new BillingLeakageService($this->db);
        $this->afiliacionDimensions = new AfiliacionDimensionService($this->db);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(
        string $start,
        string $end,
        string $sedeFilter = '',
        string $categoriaFilter = '',
        string $empresaFilter = '',
        string $seguroFilter = ''
    ): array
    {
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $categoriaFilterValue = $this->afiliacionDimensions->normalizeCategoriaFilter($categoriaFilter);
        $empresaFilterValue = $this->afiliacionDimensions->normalizeEmpresaFilter($empresaFilter);
        $seguroFilterValue = $this->afiliacionDimensions->normalizeSeguroFilter($seguroFilter);
        $billingRows = $this->fetchBillingRows(
            $start,
            $end,
            $sedeFilterValue,
            $categoriaFilterValue,
            $empresaFilterValue,
            $seguroFilterValue
        );
        $kpis = $this->buildKpis($billingRows);

        $facturasTotal = (int) ($kpis['total_facturas'] ?? 0);

        $leakageFilters = [
            'fecha_desde' => $start,
            'fecha_hasta' => $end,
            'sede' => $sedeFilterValue,
            'categoria' => $categoriaFilterValue,
            'empresa_seguro' => $empresaFilterValue,
            'seguro' => $seguroFilterValue,
        ];
        $leakage = $this->leakageService->getLeakageSummary($leakageFilters, 10);
        $leakageTotal = (int) ($leakage['total'] ?? 0);
        $denominator = $facturasTotal + $leakageTotal;
        $leakage['porcentaje'] = $denominator > 0 ? round(($leakageTotal / $denominator) * 100, 2) : 0.0;

        return [
            'range' => [
                'start' => $this->formatRangeDate($start),
                'end' => $this->formatRangeDate($end),
            ],
            'kpis' => $kpis,
            'series' => [
                'por_dia' => $this->groupByDate($billingRows),
                'por_afiliacion' => $this->groupByAffiliation($billingRows),
                'top_procedimientos' => $this->getTopProcedimientos(
                    $start,
                    $end,
                    20,
                    $sedeFilterValue,
                    $categoriaFilterValue,
                    $empresaFilterValue,
                    $seguroFilterValue
                ),
            ],
            'leakage' => $leakage,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBillingRows(
        string $start,
        string $end,
        string $sedeFilter = '',
        string $categoriaFilter = '',
        string $empresaFilter = '',
        string $seguroFilter = ''
    ): array
    {
        $dateExpr = $this->safeBillingDateExpr();
        $sedeExpr = $this->sedeExpr('pp');
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pa.afiliacion), ''), '')";
        $dimensionContext = $this->afiliacionDimensions->buildContext($rawAfiliacionExpr, 'acm');

        $sql = "
            SELECT
                bm.id,
                {$dateExpr} AS fecha,
                COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') AS afiliacion,
                {$sedeExpr} AS sede,
                COALESCE(proc.total, 0) + COALESCE(der.total, 0) + COALESCE(ins.total, 0)
                    + COALESCE(ane.total, 0) + COALESCE(oxi.total, 0) AS total_facturado,
                COALESCE(proc.items_count, 0) + COALESCE(der.items_count, 0) + COALESCE(ins.items_count, 0)
                    + COALESCE(ane.items_count, 0) + COALESCE(oxi.items_count, 0) AS total_items
            FROM billing_main bm
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            {$dimensionContext['join']}
            LEFT JOIN (
                SELECT billing_id, SUM(proc_precio) AS total, COUNT(*) AS items_count
                FROM billing_procedimientos
                GROUP BY billing_id
            ) AS proc ON proc.billing_id = bm.id
            LEFT JOIN (
                SELECT billing_id, SUM(precio_afiliacion * COALESCE(cantidad, 1)) AS total, COUNT(*) AS items_count
                FROM billing_derechos
                GROUP BY billing_id
            ) AS der ON der.billing_id = bm.id
            LEFT JOIN (
                SELECT billing_id, SUM(precio * COALESCE(cantidad, 1)) AS total, COUNT(*) AS items_count
                FROM billing_insumos
                GROUP BY billing_id
            ) AS ins ON ins.billing_id = bm.id
            LEFT JOIN (
                SELECT billing_id, SUM(precio) AS total, COUNT(*) AS items_count
                FROM billing_anestesia
                GROUP BY billing_id
            ) AS ane ON ane.billing_id = bm.id
            LEFT JOIN (
                SELECT billing_id, SUM(precio) AS total, COUNT(*) AS items_count
                FROM billing_oxigeno
                GROUP BY billing_id
            ) AS oxi ON oxi.billing_id = bm.id
            WHERE {$dateExpr} BETWEEN :inicio AND :fin
              AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
              AND (:categoria_filter = '' OR {$dimensionContext['categoria_expr']} = :categoria_filter_match)
              AND (:empresa_filter = '' OR {$dimensionContext['empresa_key_expr']} = :empresa_filter_match)
              AND (:seguro_filter = '' OR {$dimensionContext['seguro_key_expr']} = :seguro_filter_match)
            ORDER BY fecha ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':sede_filter' => $sedeFilter,
            ':sede_filter_match' => $sedeFilter,
            ':categoria_filter' => $categoriaFilter,
            ':categoria_filter_match' => $categoriaFilter,
            ':empresa_filter' => $empresaFilter,
            ':empresa_filter_match' => $empresaFilter,
            ':seguro_filter' => $seguroFilter,
            ':seguro_filter_match' => $seguroFilter,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int|float>
     */
    private function buildKpis(array $rows): array
    {
        $totalFacturas = count($rows);
        $montoTotal = 0.0;
        $totalItems = 0;

        foreach ($rows as $row) {
            $montoTotal += (float) ($row['total_facturado'] ?? 0);
            $totalItems += (int) ($row['total_items'] ?? 0);
        }

        $ticketPromedio = $totalFacturas > 0 ? $montoTotal / $totalFacturas : 0.0;
        $itemsPromedio = $totalFacturas > 0 ? $totalItems / $totalFacturas : 0.0;

        return [
            'total_facturas' => $totalFacturas,
            'monto_total' => round($montoTotal, 2),
            'ticket_promedio' => round($ticketPromedio, 2),
            'items_promedio' => round($itemsPromedio, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{labels:array<int,string>,totals:array<int,float>}
     */
    private function groupByDate(array $rows): array
    {
        $totalsByDate = [];

        foreach ($rows as $row) {
            $fechaRaw = (string) ($row['fecha'] ?? '');
            if ($fechaRaw === '') {
                $fecha = 'Sin fecha';
            } else {
                try {
                    $fecha = (new DateTimeImmutable($fechaRaw))->format('Y-m-d');
                } catch (\Throwable) {
                    $fecha = 'Sin fecha';
                }
            }

            $totalsByDate[$fecha] = ($totalsByDate[$fecha] ?? 0) + (float) ($row['total_facturado'] ?? 0);
        }

        ksort($totalsByDate);

        return [
            'labels' => array_keys($totalsByDate),
            'totals' => array_map(static fn($value) => round((float) $value, 2), array_values($totalsByDate)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{labels:array<int,string>,totals:array<int,float>}
     */
    private function groupByAffiliation(array $rows): array
    {
        $totalsByAffiliation = [];

        foreach ($rows as $row) {
            $afiliacion = (string) ($row['afiliacion'] ?? 'Sin afiliación');
            $totalsByAffiliation[$afiliacion] = ($totalsByAffiliation[$afiliacion] ?? 0) + (float) ($row['total_facturado'] ?? 0);
        }

        arsort($totalsByAffiliation);

        return [
            'labels' => array_keys($totalsByAffiliation),
            'totals' => array_map(static fn($value) => round((float) $value, 2), array_values($totalsByAffiliation)),
        ];
    }

    /**
     * @return array{labels:array<int,string>,totals:array<int,float>}
     */
    private function getTopProcedimientos(
        string $start,
        string $end,
        int $limit = 20,
        string $sedeFilter = '',
        string $categoriaFilter = '',
        string $empresaFilter = '',
        string $seguroFilter = ''
    ): array
    {
        $dateExpr = $this->safeBillingDateExpr();
        $sedeExpr = $this->sedeExpr('pp');
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pa.afiliacion), ''), '')";
        $dimensionContext = $this->afiliacionDimensions->buildContext($rawAfiliacionExpr, 'acm');

        $sql = "SELECT COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle') AS procedimiento,
                       SUM(bp.proc_precio) AS total
                FROM billing_procedimientos bp
                INNER JOIN billing_main bm ON bm.id = bp.billing_id
                LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
                LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
                {$dimensionContext['join']}
                WHERE {$dateExpr} BETWEEN :inicio AND :fin
                  AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
                  AND (:categoria_filter = '' OR {$dimensionContext['categoria_expr']} = :categoria_filter_match)
                  AND (:empresa_filter = '' OR {$dimensionContext['empresa_key_expr']} = :empresa_filter_match)
                  AND (:seguro_filter = '' OR {$dimensionContext['seguro_key_expr']} = :seguro_filter_match)
                GROUP BY COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle')
                ORDER BY total DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':sede_filter', $sedeFilter);
        $stmt->bindValue(':sede_filter_match', $sedeFilter);
        $stmt->bindValue(':categoria_filter', $categoriaFilter);
        $stmt->bindValue(':categoria_filter_match', $categoriaFilter);
        $stmt->bindValue(':empresa_filter', $empresaFilter);
        $stmt->bindValue(':empresa_filter_match', $empresaFilter);
        $stmt->bindValue(':seguro_filter', $seguroFilter);
        $stmt->bindValue(':seguro_filter_match', $seguroFilter);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $totals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = (string) ($row['procedimiento'] ?? 'Sin detalle');
            $totals[] = round((float) ($row['total'] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
        ];
    }

    private function formatRangeDate(string $value): string
    {
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
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

    private function safeBillingDateExpr(): string
    {
        return "COALESCE(
            CASE
                WHEN CAST(pd.fecha_inicio AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                ELSE pd.fecha_inicio
            END,
            CASE
                WHEN CAST(pp.fecha AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                ELSE pp.fecha
            END,
            CASE
                WHEN CAST(bm.created_at AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                ELSE bm.created_at
            END
        )";
    }

    private function sedeExpr(string $alias): string
    {
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF({$alias}.sede_departamento, ''), NULLIF({$alias}.id_sede, ''), '')))";

        return "CASE
            WHEN {$rawExpr} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$rawExpr} LIKE '%matriz%' OR {$rawExpr} LIKE '%villa%' THEN 'MATRIZ'
            ELSE ''
        END";
    }
}
