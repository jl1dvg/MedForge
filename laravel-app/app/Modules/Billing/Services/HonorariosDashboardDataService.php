<?php

namespace App\Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use PDO;

class HonorariosDashboardDataService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connection()->getPdo();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public function buildSummary(string $start, string $end, array $filters, array $rules): array
    {
        $rows = $this->fetchProcedimientos($start, $end, $filters);
        $normalizedRules = $this->normalizeRules($rules);

        $kpis = $this->buildKpis($rows, $normalizedRules);

        return [
            'kpis' => $kpis,
            'series' => [
                'por_afiliacion' => $this->groupByAffiliation($rows, $normalizedRules),
                'por_cirujano' => $this->groupBySurgeon($rows, $normalizedRules),
                'top_procedimientos' => $this->getTopProcedimientos($start, $end, $filters, 15),
            ],
            'table' => $this->buildSurgeonTable($rows, $normalizedRules),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function fetchProcedimientos(string $start, string $end, array $filters): array
    {
        $dateExpr = $this->safeBillingDateExpr();
        $sql = "
            SELECT
                bm.id AS billing_id,
                {$dateExpr} AS fecha,
                COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') AS afiliacion,
                COALESCE(NULLIF(TRIM(pd.cirujano_1), ''), 'Sin cirujano') AS cirujano,
                SUM(bp.proc_precio) AS total_procedimientos,
                COUNT(bp.id) AS procedimientos_count
            FROM billing_main bm
            INNER JOIN billing_procedimientos bp ON bp.billing_id = bm.id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            WHERE {$dateExpr} BETWEEN :inicio AND :fin
        ";

        $params = [
            ':inicio' => $start,
            ':fin' => $end,
        ];

        if (!empty($filters['cirujano'])) {
            $sql .= " AND TRIM(pd.cirujano_1) = :cirujano";
            $params[':cirujano'] = (string) $filters['cirujano'];
        }

        if (!empty($filters['afiliacion'])) {
            $sql .= " AND COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') = :afiliacion";
            $params[':afiliacion'] = (string) $filters['afiliacion'];
        }

        $sql .= " GROUP BY bm.id, fecha, afiliacion, cirujano ORDER BY fecha ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float> $rules
     * @return array<string, int|float>
     */
    private function buildKpis(array $rows, array $rules): array
    {
        $totalCasos = count($rows);
        $totalProcedimientos = 0;
        $totalProduccion = 0.0;
        $totalHonorarios = 0.0;

        foreach ($rows as $row) {
            $totalProcedimientos += (int) ($row['procedimientos_count'] ?? 0);
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalProduccion += $produccion;
            $totalHonorarios += $this->calcularHonorario($produccion, (string) ($row['afiliacion'] ?? ''), $rules);
        }

        $ticketPromedio = $totalCasos > 0 ? $totalProduccion / $totalCasos : 0.0;
        $honorarioPromedio = $totalCasos > 0 ? $totalHonorarios / $totalCasos : 0.0;

        return [
            'total_casos' => $totalCasos,
            'total_procedimientos' => $totalProcedimientos,
            'total_produccion' => round($totalProduccion, 2),
            'honorarios_estimados' => round($totalHonorarios, 2),
            'ticket_promedio' => round($ticketPromedio, 2),
            'honorario_promedio' => round($honorarioPromedio, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float> $rules
     * @return array{labels:array<int,string>,totals:array<int,float>,honorarios:array<int,float>}
     */
    private function groupByAffiliation(array $rows, array $rules): array
    {
        $totalsByAffiliation = [];
        $honorariosByAffiliation = [];

        foreach ($rows as $row) {
            $afiliacion = (string) ($row['afiliacion'] ?? 'Sin afiliación');
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalsByAffiliation[$afiliacion] = ($totalsByAffiliation[$afiliacion] ?? 0) + $produccion;
            $honorariosByAffiliation[$afiliacion] = ($honorariosByAffiliation[$afiliacion] ?? 0)
                + $this->calcularHonorario($produccion, $afiliacion, $rules);
        }

        arsort($totalsByAffiliation);

        $labels = array_keys($totalsByAffiliation);
        $totals = array_values($totalsByAffiliation);
        $honorarios = array_map(
            static fn($label) => round((float) ($honorariosByAffiliation[$label] ?? 0), 2),
            $labels
        );

        return [
            'labels' => $labels,
            'totals' => array_map(static fn($value) => round((float) $value, 2), $totals),
            'honorarios' => $honorarios,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float> $rules
     * @return array{labels:array<int,string>,totals:array<int,float>,honorarios:array<int,float>}
     */
    private function groupBySurgeon(array $rows, array $rules): array
    {
        $totalsBySurgeon = [];
        $honorariosBySurgeon = [];

        foreach ($rows as $row) {
            $cirujano = (string) ($row['cirujano'] ?? 'Sin cirujano');
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalsBySurgeon[$cirujano] = ($totalsBySurgeon[$cirujano] ?? 0) + $produccion;
            $honorariosBySurgeon[$cirujano] = ($honorariosBySurgeon[$cirujano] ?? 0)
                + $this->calcularHonorario($produccion, (string) ($row['afiliacion'] ?? ''), $rules);
        }

        arsort($totalsBySurgeon);

        $labels = array_keys($totalsBySurgeon);
        $totals = array_values($totalsBySurgeon);
        $honorarios = array_map(
            static fn($label) => round((float) ($honorariosBySurgeon[$label] ?? 0), 2),
            $labels
        );

        return [
            'labels' => $labels,
            'totals' => array_map(static fn($value) => round((float) $value, 2), $totals),
            'honorarios' => $honorarios,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float> $rules
     * @return array<int, array<string, mixed>>
     */
    private function buildSurgeonTable(array $rows, array $rules): array
    {
        $table = [];

        foreach ($rows as $row) {
            $cirujano = (string) ($row['cirujano'] ?? 'Sin cirujano');
            if (!isset($table[$cirujano])) {
                $table[$cirujano] = [
                    'cirujano' => $cirujano,
                    'casos' => 0,
                    'procedimientos' => 0,
                    'produccion' => 0.0,
                    'honorarios' => 0.0,
                ];
            }

            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $table[$cirujano]['casos'] += 1;
            $table[$cirujano]['procedimientos'] += (int) ($row['procedimientos_count'] ?? 0);
            $table[$cirujano]['produccion'] += $produccion;
            $table[$cirujano]['honorarios'] += $this->calcularHonorario($produccion, (string) ($row['afiliacion'] ?? ''), $rules);
        }

        usort($table, static fn($a, $b) => ((float) ($b['produccion'] ?? 0) <=> (float) ($a['produccion'] ?? 0)));

        return array_map(static function ($row) {
            $row['produccion'] = round((float) ($row['produccion'] ?? 0), 2);
            $row['honorarios'] = round((float) ($row['honorarios'] ?? 0), 2);
            return $row;
        }, $table);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{labels:array<int,string>,totals:array<int,float>}
     */
    private function getTopProcedimientos(string $start, string $end, array $filters, int $limit): array
    {
        $dateExpr = $this->safeBillingDateExpr();
        $sql = "
            SELECT COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle') AS procedimiento,
                   SUM(bp.proc_precio) AS total
            FROM billing_procedimientos bp
            INNER JOIN billing_main bm ON bm.id = bp.billing_id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            WHERE {$dateExpr} BETWEEN :inicio AND :fin
        ";

        $params = [
            ':inicio' => $start,
            ':fin' => $end,
        ];

        if (!empty($filters['cirujano'])) {
            $sql .= " AND TRIM(pd.cirujano_1) = :cirujano";
            $params[':cirujano'] = (string) $filters['cirujano'];
        }

        if (!empty($filters['afiliacion'])) {
            $sql .= " AND COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') = :afiliacion";
            $params[':afiliacion'] = (string) $filters['afiliacion'];
        }

        $sql .= " GROUP BY procedimiento ORDER BY total DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $params[':inicio']);
        $stmt->bindValue(':fin', $params[':fin']);
        if (isset($params[':cirujano'])) {
            $stmt->bindValue(':cirujano', $params[':cirujano']);
        }
        if (isset($params[':afiliacion'])) {
            $stmt->bindValue(':afiliacion', $params[':afiliacion']);
        }
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

    /**
     * @param array<string, float> $rules
     */
    private function calcularHonorario(float $produccion, string $afiliacion, array $rules): float
    {
        $normalized = strtoupper(trim($afiliacion));
        $percentage = $rules[$normalized] ?? $rules['DEFAULT'] ?? 0;
        return $produccion * ($percentage / 100);
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<string, float>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [
            'IESS' => 30.0,
            'ISSFA' => 35.0,
            'ISSPOL' => 35.0,
            'DEFAULT' => 30.0,
        ];

        foreach ($rules as $key => $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $normalized[strtoupper(trim((string) $key))] = (float) $value;
        }

        return $normalized;
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
}
