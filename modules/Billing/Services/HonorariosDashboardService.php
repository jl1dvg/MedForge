<?php

namespace Modules\Billing\Services;

use PDO;

class HonorariosDashboardService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getCirujanos(): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT NULLIF(TRIM(cirujano_1), '') AS cirujano
            FROM protocolo_data
            WHERE cirujano_1 IS NOT NULL
              AND TRIM(cirujano_1) != ''
            ORDER BY cirujano ASC
        ");
        $stmt->execute();

        return array_values(array_filter(array_map(static function ($row) {
            return $row['cirujano'] ?? null;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [])));
    }

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

    private function fetchProcedimientos(string $start, string $end, array $filters): array
    {
        $sql = "
            SELECT
                bm.id AS billing_id,
                COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) AS fecha,
                COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') AS afiliacion,
                COALESCE(NULLIF(TRIM(pd.cirujano_1), ''), 'Sin cirujano') AS cirujano,
                SUM(bp.proc_precio) AS total_procedimientos,
                COUNT(bp.id) AS procedimientos_count
            FROM billing_main bm
            INNER JOIN billing_procedimientos bp ON bp.billing_id = bm.id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            WHERE COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) BETWEEN :inicio AND :fin
        ";

        $params = [
            ':inicio' => $start,
            ':fin' => $end,
        ];

        if (!empty($filters['cirujano'])) {
            $sql .= " AND TRIM(pd.cirujano_1) = :cirujano";
            $params[':cirujano'] = $filters['cirujano'];
        }

        if (!empty($filters['afiliacion'])) {
            $sql .= " AND COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') = :afiliacion";
            $params[':afiliacion'] = $filters['afiliacion'];
        }

        $sql .= " GROUP BY bm.id, fecha, afiliacion, cirujano ORDER BY fecha ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

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
            $totalHonorarios += $this->calcularHonorario($produccion, $row['afiliacion'] ?? '', $rules);
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

    private function groupByAffiliation(array $rows, array $rules): array
    {
        $totalsByAffiliation = [];
        $honorariosByAffiliation = [];

        foreach ($rows as $row) {
            $afiliacion = $row['afiliacion'] ?? 'Sin afiliación';
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalsByAffiliation[$afiliacion] = ($totalsByAffiliation[$afiliacion] ?? 0) + $produccion;
            $honorariosByAffiliation[$afiliacion] = ($honorariosByAffiliation[$afiliacion] ?? 0)
                + $this->calcularHonorario($produccion, $afiliacion, $rules);
        }

        arsort($totalsByAffiliation);

        $labels = array_keys($totalsByAffiliation);
        $totals = array_values($totalsByAffiliation);
        $honorarios = array_map(
            static fn($label) => round($honorariosByAffiliation[$label] ?? 0, 2),
            $labels
        );

        return [
            'labels' => $labels,
            'totals' => array_map(static fn($value) => round($value, 2), $totals),
            'honorarios' => $honorarios,
        ];
    }

    private function groupBySurgeon(array $rows, array $rules): array
    {
        $totalsBySurgeon = [];
        $honorariosBySurgeon = [];

        foreach ($rows as $row) {
            $cirujano = $row['cirujano'] ?? 'Sin cirujano';
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalsBySurgeon[$cirujano] = ($totalsBySurgeon[$cirujano] ?? 0) + $produccion;
            $honorariosBySurgeon[$cirujano] = ($honorariosBySurgeon[$cirujano] ?? 0)
                + $this->calcularHonorario($produccion, $row['afiliacion'] ?? '', $rules);
        }

        arsort($totalsBySurgeon);

        $labels = array_keys($totalsBySurgeon);
        $totals = array_values($totalsBySurgeon);
        $honorarios = array_map(
            static fn($label) => round($honorariosBySurgeon[$label] ?? 0, 2),
            $labels
        );

        return [
            'labels' => $labels,
            'totals' => array_map(static fn($value) => round($value, 2), $totals),
            'honorarios' => $honorarios,
        ];
    }

    private function buildSurgeonTable(array $rows, array $rules): array
    {
        $table = [];

        foreach ($rows as $row) {
            $cirujano = $row['cirujano'] ?? 'Sin cirujano';
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
            $table[$cirujano]['honorarios'] += $this->calcularHonorario($produccion, $row['afiliacion'] ?? '', $rules);
        }

        usort($table, static fn($a, $b) => ($b['produccion'] <=> $a['produccion']));

        return array_map(static function ($row) {
            $row['produccion'] = round((float) $row['produccion'], 2);
            $row['honorarios'] = round((float) $row['honorarios'], 2);
            return $row;
        }, $table);
    }

    private function getTopProcedimientos(string $start, string $end, array $filters, int $limit): array
    {
        $sql = "
            SELECT COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle') AS procedimiento,
                   SUM(bp.proc_precio) AS total
            FROM billing_procedimientos bp
            INNER JOIN billing_main bm ON bm.id = bp.billing_id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            WHERE COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) BETWEEN :inicio AND :fin
        ";

        $params = [
            ':inicio' => $start,
            ':fin' => $end,
        ];

        if (!empty($filters['cirujano'])) {
            $sql .= " AND TRIM(pd.cirujano_1) = :cirujano";
            $params[':cirujano'] = $filters['cirujano'];
        }

        if (!empty($filters['afiliacion'])) {
            $sql .= " AND COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') = :afiliacion";
            $params[':afiliacion'] = $filters['afiliacion'];
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
            $labels[] = $row['procedimiento'] ?: 'Sin detalle';
            $totals[] = round((float) ($row['total'] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
        ];
    }

    private function calcularHonorario(float $produccion, string $afiliacion, array $rules): float
    {
        $normalized = strtoupper(trim($afiliacion));
        $percentage = $rules[$normalized] ?? $rules['DEFAULT'] ?? 0;
        return $produccion * ($percentage / 100);
    }

    private function normalizeRules(array $rules): array
    {
        $normalized = [
            'IESS' => 30,
            'ISSFA' => 35,
            'ISSPOL' => 35,
            'DEFAULT' => 30,
        ];

        foreach ($rules as $key => $value) {
            $value = is_numeric($value) ? (float) $value : null;
            if ($value === null) {
                continue;
            }
            $normalized[strtoupper(trim((string) $key))] = $value;
        }

        return $normalized;
    }
}
