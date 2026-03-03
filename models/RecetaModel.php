<?php

namespace Models;

use PDO;

class RecetaModel
{
    private const DOCTOR_FALLBACK = 'Sin médico';
    private const AFILIACION_FALLBACK = 'Sin afiliación';
    private const ESTADO_FALLBACK = 'Sin estado';
    private const VIA_FALLBACK = 'Sin vía';
    private const PRODUCTO_FALLBACK = 'Sin producto';
    private const LOCALIDAD_FALLBACK = 'Sin localidad';
    private const DEPARTAMENTO_FALLBACK = 'Sin departamento';
    private const DIAGNOSTICO_FALLBACK = 'Sin diagnóstico';
    private const PATIENT_FALLBACK = 'Sin paciente';

    private PDO $db;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerReporte(array $filtros): array
    {
        $sql = "
            SELECT 
                re.created_at AS fecha_receta,
                re.producto,
                COALESCE(re.cantidad, 0) AS cantidad,
                COALESCE(re.cantidad, 0) AS cantidad_prescrita,
                COALESCE(re.total_farmacia, 0) AS total_farmacia,
                re.dosis,
                {$this->doctorExpression()} AS doctor,
                pp.procedimiento_proyectado,
                pp.hc_number,
                {$this->afiliacionExpression()} AS afiliacion
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros);

        $sql .= " ORDER BY re.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function productosMasRecetados(array $filtros): array
    {
        $sql = "
            SELECT 
                re.producto,
                COUNT(*) AS veces_recetado
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros, false, true, false, false, false);

        $sql .= " GROUP BY re.producto ORDER BY veces_recetado DESC LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorDoctor(array $filtros): array
    {
        $sql = "
            SELECT 
                {$this->doctorExpression()} AS doctor,
                COUNT(*) AS total_recetas,
                SUM(re.total_farmacia) AS total_unidades
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros, true, true, true, true, true);

        $sql .= " GROUP BY doctor ORDER BY total_unidades DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorMes(array $filtros): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(re.created_at, '%Y-%m') AS mes,
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros);

        $sql .= " GROUP BY mes ORDER BY mes DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorProducto(array $filtros): array
    {
        $sql = "
            SELECT
                re.producto,
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros);

        $sql .= " GROUP BY re.producto ORDER BY total_recetas DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenPorProductoDoctor(array $filtros): array
    {
        $sql = "
            SELECT
                {$this->doctorExpression()} AS doctor,
                re.producto,
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros);

        $sql .= " GROUP BY doctor, re.producto ORDER BY total_recetas DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenGeneral(array $filtros): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_recetas,
                COALESCE(SUM(re.total_farmacia), 0) AS total_unidades,
                COUNT(DISTINCT {$this->doctorExpression()}) AS total_doctores,
                COUNT(DISTINCT re.producto) AS total_productos
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarDoctores(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->doctorExpression()} AS doctor
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY doctor ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listarAfiliaciones(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->afiliacionExpression()} AS afiliacion
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY afiliacion ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listarEstadosReceta(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->estadoExpression()} AS estado_receta
            FROM recetas_items re
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY estado_receta ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listarVias(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->viaExpression()} AS via
            FROM recetas_items re
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY via ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listarLocalidades(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->localidadExpression()} AS localidad
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY CASE localidad
                    WHEN 'Ceibos' THEN 1
                    WHEN 'Villa Club' THEN 2
                    WHEN '" . self::LOCALIDAD_FALLBACK . "' THEN 99
                    ELSE 50
                 END, localidad ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listarDepartamentos(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->departamentoExpression()} AS departamento
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY CASE departamento
                    WHEN 'Consulta' THEN 1
                    WHEN 'Optometría' THEN 2
                    WHEN 'Imágenes' THEN 3
                    WHEN 'Quirófano' THEN 4
                    WHEN 'PNI' THEN 5
                    WHEN '" . self::DEPARTAMENTO_FALLBACK . "' THEN 99
                    ELSE 50
                 END, departamento ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerDashboardRows(array $filtros): array
    {
        $joinDerivaciones = '';
        if ($this->tableExists('derivaciones_form_id')) {
            $joinCondition = 'df.form_id = re.form_id';
            if ($this->columnExists('derivaciones_form_id', 'hc_number')) {
                $joinCondition .= " AND (
                    NULLIF(TRIM(df.hc_number), '') IS NULL
                    OR NULLIF(TRIM(pp.hc_number), '') IS NULL
                    OR TRIM(df.hc_number) = TRIM(pp.hc_number)
                )";
            }
            $joinDerivaciones = "LEFT JOIN derivaciones_form_id df ON {$joinCondition}";
        }

        $joinConsulta = '';
        if ($this->tableExists('consulta_data')) {
            $joinCondition = 'cd.form_id = re.form_id';
            if ($this->columnExists('consulta_data', 'hc_number')) {
                $joinCondition .= " AND (
                    NULLIF(TRIM(cd.hc_number), '') IS NULL
                    OR NULLIF(TRIM(pp.hc_number), '') IS NULL
                    OR TRIM(cd.hc_number) = TRIM(pp.hc_number)
                )";
            }
            $joinConsulta = "LEFT JOIN consulta_data cd ON {$joinCondition}";
        }

        $joinPaciente = '';
        if ($this->tableExists('patient_data') && $this->columnExists('patient_data', 'hc_number')) {
            $joinPaciente = "LEFT JOIN patient_data pa ON pa.hc_number = pp.hc_number";
        }

        $hasDiagnosticosAsignados = false;
        $joinDiagnosticosAsignados = '';
        if (
            $this->tableExists('diagnosticos_asignados')
            && $this->columnExists('diagnosticos_asignados', 'form_id')
        ) {
            $dxCodeExpr = $this->columnExists('diagnosticos_asignados', 'dx_code')
                ? "NULLIF(TRIM(da.dx_code), '')"
                : "''";
            $dxDescExpr = $this->columnExists('diagnosticos_asignados', 'descripcion')
                ? "NULLIF(TRIM(da.descripcion), '')"
                : "''";

            if ($dxCodeExpr !== "''" || $dxDescExpr !== "''") {
                $hasDiagnosticosAsignados = true;
                $dxTextExpr = "TRIM(CONCAT_WS(' - ', {$dxCodeExpr}, {$dxDescExpr}))";
                $joinDiagnosticosAsignados = "
                    LEFT JOIN (
                        SELECT
                            da.form_id,
                            GROUP_CONCAT(DISTINCT {$dxTextExpr} ORDER BY {$dxTextExpr} SEPARATOR '; ') AS diagnostico_compuesto
                        FROM diagnosticos_asignados da
                        WHERE ({$dxCodeExpr} <> '' OR {$dxDescExpr} <> '')
                        GROUP BY da.form_id
                    ) da_agg ON da_agg.form_id = re.form_id
                ";
            }
        }

        $sql = "
            SELECT
                re.id,
                re.form_id,
                re.created_at AS fecha_receta,
                re.updated_at AS fecha_actualizacion,
                {$this->estadoExpression()} AS estado_receta,
                {$this->productoExpression()} AS producto,
                {$this->viaExpression()} AS via,
                COALESCE(NULLIF(TRIM(re.unidad), ''), 'Sin unidad') AS unidad,
                COALESCE(NULLIF(TRIM(re.dosis), ''), NULLIF(TRIM(re.pauta), ''), '—') AS dosis,
                COALESCE(re.cantidad, 0) AS cantidad,
                COALESCE(re.total_farmacia, 0) AS total_farmacia,
                {$this->doctorExpression()} AS doctor,
                {$this->afiliacionExpression()} AS afiliacion,
                COALESCE(NULLIF(TRIM(pp.procedimiento_proyectado), ''), '') AS procedimiento_proyectado,
                COALESCE(NULLIF(TRIM(pp.hc_number), ''), '') AS hc_number,
                {$this->localidadExpression()} AS localidad,
                {$this->departamentoExpression()} AS departamento,
                {$this->diagnosticoExpression($hasDiagnosticosAsignados)} AS diagnostico,
                {$this->patientNameExpression()} AS paciente_nombre,
                {$this->patientDocumentExpression()} AS cedula_paciente,
                {$this->patientBirthdateExpression()} AS fecha_nacimiento
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = re.form_id
            {$joinDerivaciones}
            {$joinConsulta}
            {$joinPaciente}
            {$joinDiagnosticosAsignados}
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros, true, true, true, true, true, true, true);
        $sql .= " ORDER BY re.created_at DESC, re.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodas(): array
    {
        $sql = "
            SELECT 
                re.*, 
                pp.procedimiento_proyectado, 
                pp.hc_number, 
                pp.afiliacion
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            ORDER BY re.created_at DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function appendDateFilters(string &$sql, array &$params, array $filtros): void
    {
        $fechaInicio = trim((string)($filtros['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($filtros['fecha_fin'] ?? ''));

        if ($fechaInicio !== '') {
            $sql .= " AND DATE(re.created_at) >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }

        if ($fechaFin !== '') {
            $sql .= " AND DATE(re.created_at) <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }
    }

    private function appendStandardFilters(
        string &$sql,
        array &$params,
        array $filtros,
        bool $allowDoctor = true,
        bool $allowProducto = true,
        bool $allowAfiliacion = true,
        bool $allowEstado = true,
        bool $allowVia = true,
        bool $allowLocalidad = true,
        bool $allowDepartamento = true
    ): void {
        $doctor = trim((string)($filtros['doctor'] ?? ''));
        if ($allowDoctor && $doctor !== '') {
            $sql .= " AND {$this->doctorExpression()} = :doctor";
            $params[':doctor'] = $doctor;
        }

        $producto = trim((string)($filtros['producto'] ?? ''));
        if ($allowProducto && $producto !== '') {
            $sql .= " AND re.producto LIKE :producto";
            $params[':producto'] = '%' . $producto . '%';
        }

        $afiliacion = trim((string)($filtros['afiliacion'] ?? ''));
        if ($allowAfiliacion && $afiliacion !== '') {
            $sql .= " AND {$this->afiliacionExpression()} = :afiliacion";
            $params[':afiliacion'] = $afiliacion;
        }

        $estado = trim((string)($filtros['estado_receta'] ?? ''));
        if ($allowEstado && $estado !== '') {
            $sql .= " AND {$this->estadoExpression()} = :estado_receta";
            $params[':estado_receta'] = $estado;
        }

        $via = trim((string)($filtros['via'] ?? ''));
        if ($allowVia && $via !== '') {
            $sql .= " AND {$this->viaExpression()} = :via";
            $params[':via'] = $via;
        }

        $localidad = trim((string)($filtros['localidad'] ?? ''));
        if ($allowLocalidad && $localidad !== '') {
            $sql .= " AND {$this->localidadExpression()} = :localidad";
            $params[':localidad'] = $localidad;
        }

        $departamento = trim((string)($filtros['departamento'] ?? ''));
        if ($allowDepartamento && $departamento !== '') {
            $sql .= " AND {$this->departamentoExpression()} = :departamento";
            $params[':departamento'] = $departamento;
        }
    }

    private function doctorExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(pp.doctor), ''), '" . self::DOCTOR_FALLBACK . "')";
    }

    private function afiliacionExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), '" . self::AFILIACION_FALLBACK . "')";
    }

    private function estadoExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(re.estado_receta), ''), '" . self::ESTADO_FALLBACK . "')";
    }

    private function viaExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(re.vias), ''), '" . self::VIA_FALLBACK . "')";
    }

    private function productoExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(re.producto), ''), '" . self::PRODUCTO_FALLBACK . "')";
    }

    private function localidadExpression(): string
    {
        $sedeExpr = $this->normalizeSqlKey($this->sedeRawExpression());
        $rawExpr = $this->sedeRawExpression();

        return "CASE
            WHEN {$sedeExpr} = '' THEN '" . self::LOCALIDAD_FALLBACK . "'
            WHEN {$sedeExpr} LIKE '%ceibos%' OR {$sedeExpr} LIKE '%cbo%' THEN 'Ceibos'
            WHEN {$sedeExpr} LIKE '%villa_club%' OR {$sedeExpr} LIKE '%vclub%' OR {$sedeExpr} LIKE '%villa%' THEN 'Villa Club'
            ELSE COALESCE(NULLIF(TRIM({$rawExpr}), ''), '" . self::LOCALIDAD_FALLBACK . "')
        END";
    }

    private function departamentoExpression(): string
    {
        $procExpr = $this->normalizeSqlKey($this->procedimientoRawExpression());

        return "CASE
            WHEN {$procExpr} = '' THEN '" . self::DEPARTAMENTO_FALLBACK . "'
            WHEN {$procExpr} LIKE '%optometr%' THEN 'Optometría'
            WHEN {$procExpr} LIKE '%imagen%' THEN 'Imágenes'
            WHEN {$procExpr} LIKE '%pni%' THEN 'PNI'
            WHEN {$procExpr} LIKE '%quir%' OR {$procExpr} LIKE '%cirug%' OR {$procExpr} LIKE '%protocolo%' THEN 'Quirófano'
            ELSE 'Consulta'
        END";
    }

    private function diagnosticoExpression(bool $withDiagnosticosAsignados = false): string
    {
        $candidates = [];

        if ($withDiagnosticosAsignados) {
            $candidates[] = "NULLIF(TRIM(da_agg.diagnostico_compuesto), '')";
        }
        if ($this->tableExists('derivaciones_form_id') && $this->columnExists('derivaciones_form_id', 'diagnostico')) {
            $candidates[] = "NULLIF(TRIM(df.diagnostico), '')";
        }
        if ($this->tableExists('consulta_data') && $this->columnExists('consulta_data', 'diagnosticos')) {
            $candidates[] = "NULLIF(TRIM(cd.diagnosticos), '')";
        }

        $candidates[] = "'" . self::DIAGNOSTICO_FALLBACK . "'";
        return 'COALESCE(' . implode(', ', $candidates) . ')';
    }

    private function patientNameExpression(): string
    {
        if (!$this->tableExists('patient_data') || !$this->columnExists('patient_data', 'hc_number')) {
            return "'" . self::PATIENT_FALLBACK . "'";
        }

        $nameParts = [];
        foreach (['lname', 'lname2', 'fname', 'mname'] as $column) {
            if ($this->columnExists('patient_data', $column)) {
                $nameParts[] = "NULLIF(TRIM(pa.{$column}), '')";
            }
        }

        if (empty($nameParts)) {
            return "'" . self::PATIENT_FALLBACK . "'";
        }

        return "COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', " . implode(', ', $nameParts) . ")), ''),
            '" . self::PATIENT_FALLBACK . "'
        )";
    }

    private function patientDocumentExpression(): string
    {
        $candidates = [];

        $canUsePatientAlias = $this->tableExists('patient_data') && $this->columnExists('patient_data', 'hc_number');

        if ($canUsePatientAlias && $this->columnExists('patient_data', 'cedula')) {
            $candidates[] = "NULLIF(TRIM(pa.cedula), '')";
        }
        if ($canUsePatientAlias && $this->columnExists('patient_data', 'ci')) {
            $candidates[] = "NULLIF(TRIM(pa.ci), '')";
        }
        if ($this->columnExists('procedimiento_proyectado', 'hc_number')) {
            $candidates[] = "NULLIF(TRIM(pp.hc_number), '')";
        }
        if ($canUsePatientAlias) {
            $candidates[] = "NULLIF(TRIM(pa.hc_number), '')";
        }

        $candidates[] = "''";
        return 'COALESCE(' . implode(', ', $candidates) . ')';
    }

    private function patientBirthdateExpression(): string
    {
        if (!$this->tableExists('patient_data') || !$this->columnExists('patient_data', 'hc_number')) {
            return "''";
        }

        $columns = ['fecha_nacimiento', 'birthdate', 'dob', 'DOB'];
        foreach ($columns as $column) {
            if ($this->columnExists('patient_data', $column)) {
                return "COALESCE(NULLIF(TRIM(pa.{$column}), ''), '')";
            }
        }

        return "''";
    }

    private function sedeRawExpression(): string
    {
        $parts = [];

        if ($this->columnExists('procedimiento_proyectado', 'sede_departamento')) {
            $parts[] = "NULLIF(TRIM(pp.sede_departamento), '')";
        }
        if ($this->columnExists('procedimiento_proyectado', 'id_sede')) {
            $parts[] = "NULLIF(TRIM(pp.id_sede), '')";
        }
        $parts[] = "''";

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function procedimientoRawExpression(): string
    {
        if ($this->columnExists('procedimiento_proyectado', 'procedimiento_proyectado')) {
            return "COALESCE(NULLIF(TRIM(pp.procedimiento_proyectado), ''), '')";
        }

        return "''";
    }

    private function normalizeSqlText(string $expr): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($expr, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N'), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'))";
    }

    private function normalizeSqlKey(string $expr): string
    {
        $normalized = $this->normalizeSqlText($expr);
        return "REPLACE(REPLACE($normalized, ' ', '_'), '-', '_')";
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $sql = "SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':table_name' => $table]);

        $this->tableExistsCache[$table] = (bool)$stmt->fetchColumn();
        return $this->tableExistsCache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        if (!$this->tableExists($table)) {
            $this->columnExistsCache[$cacheKey] = false;
            return false;
        }

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

        $this->columnExistsCache[$cacheKey] = (bool)$stmt->fetchColumn();
        return $this->columnExistsCache[$cacheKey];
    }
}
