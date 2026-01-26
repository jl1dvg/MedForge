<?php

namespace Modules\Billing\Services;

use DateTimeImmutable;
use PDO;

class BillingDashboardService
{
    private PDO $db;
    private NoFacturadosService $noFacturadosService;

    public function __construct(PDO $db, ?NoFacturadosService $noFacturadosService = null)
    {
        $this->db = $db;
        $this->noFacturadosService = $noFacturadosService ?? new NoFacturadosService($db);
    }

    public function buildSummary(string $start, string $end): array
    {
        $billingRows = $this->fetchBillingRows($start, $end);
        $kpis = $this->buildKpis($billingRows);

        $facturasTotal = $kpis['total_facturas'] ?? 0;

        $leakageFilters = [
            'fecha_desde' => $start,
            'fecha_hasta' => $end,
        ];
        $leakage = $this->noFacturadosService->getLeakageSummary($leakageFilters, 10);
        $leakageTotal = $leakage['total'] ?? 0;
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
                'top_procedimientos' => $this->getTopProcedimientos($start, $end, 20),
            ],
            'leakage' => $leakage,
        ];
    }

    private function fetchBillingRows(string $start, string $end): array
    {
        $sql = "
            SELECT
                bm.id,
                COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) AS fecha,
                COALESCE(NULLIF(TRIM(pa.afiliacion), ''), 'Sin afiliación') AS afiliacion,
                COALESCE(proc.total, 0) + COALESCE(der.total, 0) + COALESCE(ins.total, 0)
                    + COALESCE(ane.total, 0) + COALESCE(oxi.total, 0) AS total_facturado,
                COALESCE(proc.items_count, 0) + COALESCE(der.items_count, 0) + COALESCE(ins.items_count, 0)
                    + COALESCE(ane.items_count, 0) + COALESCE(oxi.items_count, 0) AS total_items
            FROM billing_main bm
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
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
            WHERE COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) BETWEEN :inicio AND :fin
            ORDER BY fecha ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

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
            'totals' => array_map(static fn($value) => round($value, 2), array_values($totalsByDate)),
        ];
    }

    private function groupByAffiliation(array $rows): array
    {
        $totalsByAffiliation = [];

        foreach ($rows as $row) {
            $afiliacion = $row['afiliacion'] ?? 'Sin afiliación';
            $totalsByAffiliation[$afiliacion] = ($totalsByAffiliation[$afiliacion] ?? 0) + (float) ($row['total_facturado'] ?? 0);
        }

        arsort($totalsByAffiliation);

        return [
            'labels' => array_keys($totalsByAffiliation),
            'totals' => array_map(static fn($value) => round($value, 2), array_values($totalsByAffiliation)),
        ];
    }

    private function getTopProcedimientos(string $start, string $end, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle') AS procedimiento,
                    SUM(bp.proc_precio) AS total
             FROM billing_procedimientos bp
             INNER JOIN billing_main bm ON bm.id = bp.billing_id
             LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
             LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
             WHERE COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) BETWEEN :inicio AND :fin
             GROUP BY COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
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

    private function formatRangeDate(string $value): string
    {
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }
}
