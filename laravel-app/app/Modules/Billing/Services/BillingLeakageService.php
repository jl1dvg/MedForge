<?php

namespace App\Modules\Billing\Services;

use App\Modules\Shared\Support\AfiliacionDimensionService;
use PDO;

class BillingLeakageService
{
    /** @var array<string,bool> */
    private array $columnExistsCache = [];
    private AfiliacionDimensionService $afiliacionDimensions;

    public function __construct(private readonly PDO $db)
    {
        $this->afiliacionDimensions = new AfiliacionDimensionService($db);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   total:int,
     *   avg_aging:float|null,
     *   por_afiliacion:array{labels:array<int,string>,totals:array<int,int>},
     *   oldest:array<int,array<string,mixed>>
     * }
     */
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
            $afiliacionLabels[] = (string) ($row['afiliacion'] ?? 'Sin afiliación');
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

    private function getBaseSql(): string
    {
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(base.afiliacion), ''), '')";
        $dimensionContext = $this->afiliacionDimensions->buildContext($rawAfiliacionExpr, 'acm');
        $sql = <<<'SQL'
            SELECT
                base.form_id,
                base.hc_number,
                base.fecha,
                base.afiliacion,
                %AFILIACION_CATEGORIA_EXPR% AS afiliacion_categoria,
                %AFILIACION_EMPRESA_EXPR% AS empresa_seguro,
                %AFILIACION_SEGURO_EXPR% AS seguro,
                base.sede,
                base.paciente,
                base.procedimiento,
                base.tipo
            FROM (
                SELECT
                    pr.form_id,
                    pr.hc_number,
                    CASE
                        WHEN CAST(pr.fecha AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                        ELSE pr.fecha
                    END AS fecha,
                    pa.afiliacion,
                    %SEDE_EXPR% AS sede,
                    CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                    pr.procedimiento_proyectado AS procedimiento,
                    CASE
                        WHEN pr.procedimiento_proyectado LIKE 'Imagenes%' THEN 'imagen'
                        WHEN pr.procedimiento_proyectado LIKE 'Servicios oftalmologicos generales%' THEN 'consulta'
                        ELSE 'no_quirurgico'
                    END AS tipo
                FROM procedimiento_proyectado pr
                INNER JOIN patient_data pa ON pa.hc_number = pr.hc_number
                LEFT JOIN protocolo_data pd ON pd.form_id = pr.form_id
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
                    CASE
                        WHEN CAST(pd.fecha_inicio AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                        ELSE pd.fecha_inicio
                    END AS fecha,
                    pa.afiliacion,
                    %SEDE_EXPR% AS sede,
                    CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname) AS paciente,
                    TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) AS procedimiento,
                    CASE
                        WHEN TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) LIKE 'Imagenes%' THEN 'imagen'
                        WHEN TRIM(CONCAT(pd.membrete, ' ', pd.lateralidad)) LIKE 'Servicios oftalmologicos generales%' THEN 'consulta'
                        ELSE 'quirurgico'
                    END AS tipo
                FROM protocolo_data pd
                INNER JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
                INNER JOIN patient_data pa ON pa.hc_number = pd.hc_number
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
            %AFILIACION_JOIN%
        SQL;

        $sql = str_replace('%SEDE_EXPR%', $this->sedeExpression('pr'), $sql);
        $sql = str_replace('%AFILIACION_JOIN%', $dimensionContext['join'], $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_EXPR%', $dimensionContext['categoria_expr'], $sql);
        $sql = str_replace('%AFILIACION_EMPRESA_EXPR%', $dimensionContext['empresa_key_expr'], $sql);
        $sql = str_replace('%AFILIACION_SEGURO_EXPR%', $dimensionContext['seguro_key_expr'], $sql);

        return $sql;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, string> $params
     */
    private function buildWhere(array $filters, array &$params): string
    {
        $conditions = [];

        if (!empty($filters['fecha_desde'])) {
            $conditions[] = 'base.fecha >= :fecha_desde';
            $params[':fecha_desde'] = (string) $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = 'base.fecha <= :fecha_hasta';
            $params[':fecha_hasta'] = (string) $filters['fecha_hasta'];
        }

        $sede = $this->normalizeSedeFilter($filters['sede'] ?? null);
        if ($sede !== '') {
            $conditions[] = 'base.sede = :sede';
            $params[':sede'] = $sede;
        }

        $categoria = $this->afiliacionDimensions->normalizeCategoriaFilter((string) ($filters['categoria'] ?? ''));
        if ($categoria !== '') {
            $conditions[] = 'base.afiliacion_categoria = :categoria';
            $params[':categoria'] = $categoria;
        }

        $empresa = $this->afiliacionDimensions->normalizeEmpresaFilter((string) ($filters['empresa_seguro'] ?? ''));
        if ($empresa !== '') {
            $conditions[] = 'base.empresa_seguro = :empresa_seguro';
            $params[':empresa_seguro'] = $empresa;
        }

        $seguro = $this->afiliacionDimensions->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguro !== '') {
            $conditions[] = 'base.seguro = :seguro';
            $params[':seguro'] = $seguro;
        }

        return $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';
    }

    private function normalizeSedeFilter(mixed $value): string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, 'ceib')) {
            return 'CEIBOS';
        }
        if (str_contains($raw, 'matriz') || str_contains($raw, 'villa')) {
            return 'MATRIZ';
        }

        return '';
    }

    private function sedeExpression(string $alias): string
    {
        $parts = [];
        if ($this->columnExists('procedimiento_proyectado', 'sede_departamento')) {
            $parts[] = "NULLIF(TRIM({$alias}.sede_departamento), '')";
        }
        if ($this->columnExists('procedimiento_proyectado', 'id_sede')) {
            $parts[] = "NULLIF(TRIM({$alias}.id_sede), '')";
        }

        $rawExpr = "''";
        if ($parts !== []) {
            $rawExpr = 'COALESCE(' . implode(', ', $parts) . ", '')";
        }
        $normalized = "LOWER(TRIM({$rawExpr}))";

        return "CASE
            WHEN {$normalized} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$normalized} LIKE '%matriz%' OR {$normalized} LIKE '%villa%' THEN 'MATRIZ'
            ELSE ''
        END";
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        $exists = (int) $stmt->fetchColumn() > 0;
        $this->columnExistsCache[$key] = $exists;

        return $exists;
    }
}
