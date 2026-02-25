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
                base.informado,
                base.informe_actualizado,
                base.estado_agenda,
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
                        WHEN pr.procedimiento_proyectado LIKE 'Servicios oftalmologicos generales%' THEN 'consulta'
                        ELSE 'no_quirurgico'
                    END AS tipo,
                    NULL AS estado_revision,
                    CASE
                        WHEN UPPER(pr.procedimiento_proyectado) LIKE 'IMAGENES%' THEN CASE WHEN ii.id IS NULL THEN 0 ELSE 1 END
                        ELSE NULL
                    END AS informado,
                    ii.updated_at AS informe_actualizado,
                    pr.estado_agenda AS estado_agenda,
                    0 AS valor_estimado
                FROM procedimiento_proyectado pr
                INNER JOIN patient_data pa ON pa.hc_number = pr.hc_number
                LEFT JOIN protocolo_data pd ON pd.form_id = pr.form_id
                LEFT JOIN imagenes_informes ii ON ii.form_id = pr.form_id
                WHERE pd.form_id IS NULL
                  AND NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pr.form_id)
                  AND (
                        UPPER(pr.procedimiento_proyectado) NOT LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES%'
                        OR (
                            UPPER(pr.procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE%'
                            OR UPPER(pr.procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA%'
                            OR UPPER(pr.procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL%'
                            OR UPPER(pr.procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA%'
                            OR UPPER(pr.procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-007 - REVISION DE EXAMENES%'
                        )
                  )

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
                        WHEN TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) LIKE 'Servicios oftalmologicos generales%' THEN 'consulta'
                        ELSE 'quirurgico'
                    END AS tipo,
                    pd.status AS estado_revision,
                    CASE
                        WHEN UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) LIKE 'IMAGENES%' THEN CASE WHEN ii.id IS NULL THEN 0 ELSE 1 END
                        ELSE NULL
                    END AS informado,
                    ii.updated_at AS informe_actualizado,
                    pr.estado_agenda AS estado_agenda,
                    0 AS valor_estimado
                FROM protocolo_data pd
                INNER JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
                INNER JOIN patient_data pa ON pa.hc_number = pd.hc_number
                LEFT JOIN imagenes_informes ii ON ii.form_id = pd.form_id
                WHERE NOT EXISTS (SELECT 1 FROM billing_main bm WHERE bm.form_id = pd.form_id)
                  AND (
                        UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) NOT LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES%'
                        OR (
                            UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE%'
                            OR UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA%'
                            OR UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL%'
                            OR UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA%'
                            OR UPPER(TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad))) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-007 - REVISION DE EXAMENES%'
                        )
                  )
            ) AS base
        SQL;
    }

    public function listar(array $filters, int $start, int $length): array
    {
        $baseSql = $this->getBaseSql();

        $params = [];
        $where = $this->buildWhere($filters, $params);

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
                $parts = $this->parseImagenProcedimiento($row['procedimiento']);
                if ($parts) {
                    $row['procedimiento_codigo'] = $parts['codigo'];
                    $row['procedimiento_detalle'] = $parts['detalle'];
                    $row['procedimiento_display'] = $parts['codigo'] . ' (' . $parts['detalle'] . ')';
                }
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

    public function getLeakageSummary(array $filters, int $oldestLimit = 10): array
    {
        $baseSql = $this->getBaseSql();
        $params = [];
        $where = $this->buildWhere($filters, $params);

        $summarySql = 'SELECT COUNT(*) AS total, AVG(DATEDIFF(CURDATE(), base.fecha)) AS avg_aging FROM (' . $baseSql . ') AS base' . $where;
        $stmtSummary = $this->db->prepare($summarySql);
        foreach ($params as $key => $value) {
            $stmtSummary->bindValue($key, $value);
        }
        $stmtSummary->execute();
        $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'avg_aging' => null];

        $afiliacionSql = 'SELECT COALESCE(NULLIF(TRIM(base.afiliacion), \'\'), \'Sin afiliación\') AS afiliacion, COUNT(*) AS total FROM (' . $baseSql . ') AS base' . $where . ' GROUP BY COALESCE(NULLIF(TRIM(base.afiliacion), \'\'), \'Sin afiliación\') ORDER BY total DESC';
        $stmtAfiliacion = $this->db->prepare($afiliacionSql);
        foreach ($params as $key => $value) {
            $stmtAfiliacion->bindValue($key, $value);
        }
        $stmtAfiliacion->execute();
        $afiliacionLabels = [];
        $afiliacionTotals = [];
        while ($row = $stmtAfiliacion->fetch(PDO::FETCH_ASSOC)) {
            $afiliacionLabels[] = $row['afiliacion'];
            $afiliacionTotals[] = (int) ($row['total'] ?? 0);
        }

        $oldestSql = 'SELECT base.form_id, base.hc_number, base.fecha, base.afiliacion, base.paciente, base.procedimiento, base.tipo, DATEDIFF(CURDATE(), base.fecha) AS dias_pendiente FROM (' . $baseSql . ') AS base' . $where . ' ORDER BY base.fecha ASC LIMIT :limit';
        $stmtOldest = $this->db->prepare($oldestSql);
        foreach ($params as $key => $value) {
            $stmtOldest->bindValue($key, $value);
        }
        $stmtOldest->bindValue(':limit', max(1, $oldestLimit), PDO::PARAM_INT);
        $stmtOldest->execute();
        $oldest = $stmtOldest->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'avg_aging' => $summary['avg_aging'] !== null ? (float) $summary['avg_aging'] : null,
            'por_afiliacion' => [
                'labels' => $afiliacionLabels,
                'totals' => $afiliacionTotals,
            ],
            'oldest' => $oldest,
        ];
    }

    public function listarAfiliaciones(): array
    {
        $baseSql = $this->getBaseSql();
        $sql = 'SELECT DISTINCT TRIM(base.afiliacion) AS afiliacion FROM (' . $baseSql . ') AS base WHERE base.afiliacion IS NOT NULL AND TRIM(base.afiliacion) <> \'\' ORDER BY afiliacion';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function parseImagenProcedimiento(string $texto): ?array
    {
        $nombre = $this->sanitizeImagenNombre($texto);
        preg_match_all('/\d{3,}/', $nombre, $allCodes);
        $codes = $allCodes[0] ?? [];
        if (empty($codes)) {
            return null;
        }

        $codigo = null;
        foreach (array_reverse($codes) as $code) {
            if (strlen($code) >= 5) {
                $codigo = $code;
                break;
            }
        }
        $codigo ??= end($codes);

        $detalle = preg_replace('/^.*?' . preg_quote($codigo, '/') . '\s*-\s*/', '', $nombre) ?? $nombre;

        return [
            'codigo' => $codigo,
            'detalle' => trim($detalle),
        ];
    }

    private function sanitizeImagenNombre(string $nombre): string
    {
        $clean = trim($nombre);
        if (preg_match('/(\\d{3,}.*)$/', $clean, $matches)) {
            return trim($matches[1]);
        }

        $clean = preg_replace('/^Imagenes\\s*-\\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/^Dia-\\d+\\s*-\\s*/i', '', $clean) ?? $clean;
        return trim($clean);
    }

    private function buildWhere(array $filters, array &$params): string
    {
        $conditions = [];
        $afiliaciones = array_values(array_filter(array_map('trim', (array)($filters['afiliacion'] ?? [])), static fn($value) => $value !== ''));
        $estadoRevision = $filters['estado_revision'] ?? null;
        $tipo = $filters['tipo'] ?? null;
        $busqueda = $filters['busqueda'] ?? null;
        $procedimiento = $filters['procedimiento'] ?? null;
        $valorMin = $filters['valor_min'] ?? null;
        $valorMax = $filters['valor_max'] ?? null;
        $informado = $filters['informado'] ?? null;
        $estadosAgenda = array_values(array_filter(array_map('trim', (array)($filters['estado_agenda'] ?? [])), static fn($value) => $value !== ''));

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

        if ($estadoRevision !== '' && $estadoRevision !== null) {
            $conditions[] = 'COALESCE(base.estado_revision, 0) = :estado_revision';
            $params[':estado_revision'] = (int)$estadoRevision;
        }

        if (!empty($tipo)) {
            $conditions[] = 'base.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        if (!empty($estadosAgenda)) {
            $placeholders = [];
            $orConditions = [];
            foreach ($estadosAgenda as $index => $estadoAgenda) {
                if (strcasecmp($estadoAgenda, 'NULL') === 0) {
                    $orConditions[] = '(base.estado_agenda IS NULL OR TRIM(base.estado_agenda) = \'\')';
                    continue;
                }
                $placeholder = ':estado_agenda_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = strtoupper($estadoAgenda);
            }

            if (!empty($placeholders)) {
                $orConditions[] = 'UPPER(base.estado_agenda) IN (' . implode(', ', $placeholders) . ')';
            }

            if (!empty($orConditions)) {
                $conditions[] = '(' . implode(' OR ', $orConditions) . ')';
            }
        }

        if (!empty($busqueda)) {
            $conditions[] = '(base.hc_number LIKE :busqueda_hc OR base.paciente LIKE :busqueda_paciente)';
            $params[':busqueda_hc'] = '%' . $busqueda . '%';
            $params[':busqueda_paciente'] = '%' . $busqueda . '%';
        }

        if (!empty($procedimiento)) {
            $conditions[] = 'base.procedimiento LIKE :procedimiento';
            $params[':procedimiento'] = '%' . $procedimiento . '%';
        }

        if ($valorMin !== '' && $valorMin !== null) {
            $conditions[] = 'base.valor_estimado >= :valor_min';
            $params[':valor_min'] = (float)$valorMin;
        }

        if ($valorMax !== '' && $valorMax !== null) {
            $conditions[] = 'base.valor_estimado <= :valor_max';
            $params[':valor_max'] = (float)$valorMax;
        }

        if ($informado !== '' && $informado !== null && ($informado === '0' || $informado === '1' || $informado === 0 || $informado === 1)) {
            // Solo aplica a registros de imágenes; para otros tipos no restringe el resultado.
            $conditions[] = '(base.tipo <> \'imagen\' OR COALESCE(base.informado, 0) = :informado)';
            $params[':informado'] = (int)$informado;
        }

        return $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';
    }
}
