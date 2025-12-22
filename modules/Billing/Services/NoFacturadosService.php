<?php

namespace Modules\Billing\Services;

use PDO;

class NoFacturadosService
{
    public function __construct(private readonly PDO $db)
    {
    }

    private function getBaseSql(): string
    {
        return <<<'SQL'
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
                    CASE
                        WHEN pr.procedimiento_proyectado LIKE 'Imagenes%' THEN 'imagen'
                        ELSE 'no_quirurgico'
                    END AS tipo,
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
                    CASE
                        WHEN TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) LIKE 'Imagenes%' THEN 'imagen'
                        ELSE 'quirurgico'
                    END AS tipo,
                    pd.status AS estado_revision,
                    0 AS valor_estimado
                FROM protocolo_data pd
                INNER JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
                INNER JOIN patient_data pa ON pa.hc_number = pd.hc_number
                WHERE NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pd.form_id)
            ) AS base
        SQL;
    }

    public function listar(array $filters, int $start, int $length): array
    {
        $baseSql = $this->getBaseSql();

        $conditions = [];
        $params = [];
        $afiliaciones = array_values(array_filter(array_map('trim', (array)($filters['afiliacion'] ?? [])), static fn($value) => $value !== ''));

        if (!empty($filters['fecha_desde'])) {
            $conditions[] = 'base.fecha >= :fecha_desde';
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = 'base.fecha <= :fecha_hasta';
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }

        if (!empty($afiliaciones)) {
            $placeholders = [];
            foreach ($afiliaciones as $index => $afiliacion) {
                $placeholder = ':afiliacion_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $afiliacion;
            }
            $conditions[] = 'base.afiliacion IN (' . implode(', ', $placeholders) . ')';
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
            // Duplicated placeholders are not allowed in some PDO drivers, use two distinct ones
            $conditions[] = '(base.hc_number LIKE :busqueda_hc OR base.paciente LIKE :busqueda_paciente)';
            $params[':busqueda_hc'] = '%' . $filters['busqueda'] . '%';
            $params[':busqueda_paciente'] = '%' . $filters['busqueda'] . '%';
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

        $countSql = 'SELECT COUNT(*) FROM (' . $baseSql . ') AS base' . $where;
        $stmtCount = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $recordsFiltered = (int) $stmtCount->fetchColumn();

        $totalSql = 'SELECT COUNT(*) FROM (' . $baseSql . ') AS total_base';
        $totalCount = (int) $this->db->query($totalSql)->fetchColumn();

        $summarySql = 'SELECT base.tipo, COUNT(*) AS cantidad, SUM(base.valor_estimado) AS total_valor FROM (' . $baseSql . ') AS base' . $where . ' GROUP BY base.tipo';
        $stmtSummary = $this->db->prepare($summarySql);
        foreach ($params as $key => $value) {
            $stmtSummary->bindValue($key, $value);
        }
        $stmtSummary->execute();
        $summaryRows = $stmtSummary->fetchAll(PDO::FETCH_ASSOC);

        $limitStart = max(0, (int) $start);
        $limitLength = max(1, (int) $length);

        $dataSql = $baseSql . $where . ' ORDER BY base.paciente ASC, base.fecha DESC, base.form_id DESC LIMIT ' . $limitStart . ', ' . $limitLength;
        $stmt = $this->db->prepare($dataSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function (array $row): array {
            if (($row['tipo'] ?? '') === 'imagen') {
                $row['procedimiento'] = $this->sanitizeImagenNombre($row['procedimiento'] ?? '');
            }
            return $row;
        }, $data);

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

    public function listarAfiliaciones(): array
    {
        $baseSql = $this->getBaseSql();
        $sql = 'SELECT DISTINCT TRIM(base.afiliacion) AS afiliacion FROM (' . $baseSql . ') AS base WHERE base.afiliacion IS NOT NULL AND TRIM(base.afiliacion) <> \'\' ORDER BY afiliacion';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function sanitizeImagenNombre(string $nombre): string
    {
        $clean = trim($nombre);
        $clean = preg_replace('/^Imagenes\\s*-\\s*[^-]+\\s*-\\s*/i', '', $clean) ?? $clean;
        return $clean;
    }
}
