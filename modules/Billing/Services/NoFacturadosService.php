<?php

namespace Modules\Billing\Services;

use PDO;

class NoFacturadosService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listar(array $filters, int $start, int $length): array
    {
        $baseSql = <<<'SQL'
            SELECT
                base.form_id,
                base.hc_number,
                base.fecha,
                base.afiliacion,
                base.paciente,
                base.procedimiento,
                base.tipo,
                base.estado_revision,
                base.valor_estimado
            FROM (
                SELECT
                    pr.form_id,
                    pr.hc_number,
                    pr.fecha AS fecha,
                    pa.afiliacion,
                    CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                    pr.procedimiento_proyectado AS procedimiento,
                    'no_quirurgico' AS tipo,
                    NULL AS estado_revision,
                    0 AS valor_estimado
                FROM procedimiento_proyectado pr
                INNER JOIN patient_data pa ON pa.hc_number = pr.hc_number
                LEFT JOIN protocolo_data pd ON pd.form_id = pr.form_id
                WHERE pd.form_id IS NULL
                  AND NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pr.form_id)

                UNION ALL

                SELECT
                    pd.form_id,
                    pd.hc_number,
                    pd.fecha_inicio AS fecha,
                    pa.afiliacion,
                    CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                    TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) AS procedimiento,
                    'quirurgico' AS tipo,
                    pd.status AS estado_revision,
                    0 AS valor_estimado
                FROM protocolo_data pd
                INNER JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
                INNER JOIN patient_data pa ON pa.hc_number = pd.hc_number
                WHERE NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pd.form_id)
            ) AS base
        SQL;

        $conditions = [];
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $conditions[] = 'base.fecha >= :fecha_desde';
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = 'base.fecha <= :fecha_hasta';
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }

        if (!empty($filters['afiliacion'])) {
            $conditions[] = 'base.afiliacion = :afiliacion';
            $params[':afiliacion'] = $filters['afiliacion'];
        }

        if ($filters['estado_revision'] !== '' && $filters['estado_revision'] !== null) {
            $conditions[] = 'COALESCE(base.estado_revision, 0) = :estado_revision';
            $params[':estado_revision'] = (int)$filters['estado_revision'];
        }

        if (!empty($filters['tipo'])) {
            $conditions[] = 'base.tipo = :tipo';
            $params[':tipo'] = $filters['tipo'];
        }

        if (!empty($filters['busqueda'])) {
            $conditions[] = '(base.hc_number LIKE :busqueda OR base.paciente LIKE :busqueda)';
            $params[':busqueda'] = '%' . $filters['busqueda'] . '%';
        }

        if (!empty($filters['procedimiento'])) {
            $conditions[] = 'base.procedimiento LIKE :procedimiento';
            $params[':procedimiento'] = '%' . $filters['procedimiento'] . '%';
        }

        if ($filters['valor_min'] !== '' && $filters['valor_min'] !== null) {
            $conditions[] = 'base.valor_estimado >= :valor_min';
            $params[':valor_min'] = (float)$filters['valor_min'];
        }

        if ($filters['valor_max'] !== '' && $filters['valor_max'] !== null) {
            $conditions[] = 'base.valor_estimado <= :valor_max';
            $params[':valor_max'] = (float)$filters['valor_max'];
        }

        $where = $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';

        $countSql = 'SELECT COUNT(*) FROM (' . $baseSql . ') AS conteo' . $where;
        $stmtCount = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $recordsFiltered = (int) $stmtCount->fetchColumn();

        $totalSql = 'SELECT COUNT(*) FROM (' . $baseSql . ') AS total_base';
        $totalCount = (int) $this->db->query($totalSql)->fetchColumn();

        $summarySql = 'SELECT tipo, COUNT(*) AS cantidad, SUM(valor_estimado) AS total_valor FROM (' . $baseSql . ') AS resumen' . $where . ' GROUP BY tipo';
        $stmtSummary = $this->db->prepare($summarySql);
        foreach ($params as $key => $value) {
            $stmtSummary->bindValue($key, $value);
        }
        $stmtSummary->execute();
        $summaryRows = $stmtSummary->fetchAll(PDO::FETCH_ASSOC);

        $dataSql = $baseSql . $where . ' ORDER BY base.fecha DESC, base.form_id DESC LIMIT :start, :length';
        $stmt = $this->db->prepare($dataSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resumen = [
            'total' => $recordsFiltered,
            'monto' => 0.0,
            'quirurgicos' => ['cantidad' => 0, 'monto' => 0.0],
            'no_quirurgicos' => ['cantidad' => 0, 'monto' => 0.0],
        ];

        foreach ($summaryRows as $row) {
            $tipo = $row['tipo'] ?? '';
            $cantidad = (int)($row['cantidad'] ?? 0);
            $monto = (float)($row['total_valor'] ?? 0);

            if ($tipo === 'quirurgico') {
                $resumen['quirurgicos']['cantidad'] = $cantidad;
                $resumen['quirurgicos']['monto'] = $monto;
            }

            if ($tipo === 'no_quirurgico') {
                $resumen['no_quirurgicos']['cantidad'] = $cantidad;
                $resumen['no_quirurgicos']['monto'] = $monto;
            }

            $resumen['monto'] += $monto;
        }

        return [
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => $resumen,
        ];
    }
}
