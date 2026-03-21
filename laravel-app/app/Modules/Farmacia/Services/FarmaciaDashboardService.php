<?php

declare(strict_types=1);

namespace App\Modules\Farmacia\Services;

use App\Modules\Shared\Support\AfiliacionDimensionService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class FarmaciaDashboardService
{
    private const DOCTOR_FALLBACK = 'Sin médico';
    private const AFILIACION_FALLBACK = 'Sin afiliación';
    private const ESTADO_FALLBACK = 'Sin estado';
    private const VIA_FALLBACK = 'Sin vía';
    private const PRODUCTO_FALLBACK = 'Sin producto';
    private const LOCALIDAD_FALLBACK = 'Sin localidad';
    private const SEDE_FALLBACK = 'Sin sede';
    private const DEPARTAMENTO_FALLBACK = 'Sin departamento';
    private const DIAGNOSTICO_FALLBACK = 'Sin diagnóstico';
    private const PATIENT_FALLBACK = 'Sin paciente';

    private PDO $db;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    private AfiliacionDimensionService $afiliacionDimensions;

    public function __construct()
    {
        $this->db = DB::connection()->getPdo();
        $this->afiliacionDimensions = new AfiliacionDimensionService($this->db);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function dashboard(array $query): array
    {
        $filters = $this->resolveDashboardFilters($query);
        $rows = $this->obtenerDashboardRows($filters);
        $conciliationRows = $this->obtenerConciliacionRows($filters);
        $dateFilter = [
            'fecha_inicio' => $filters['fecha_inicio'],
            'fecha_fin' => $filters['fecha_fin'],
        ];

        return [
            'filters' => $filters,
            'dashboard' => $this->buildDashboardSummary($rows, $filters, $conciliationRows),
            'rows' => $this->buildDashboardDetailRows($rows),
            'conciliationRows' => $this->buildConciliacionDetailRows($conciliationRows),
            'doctorOptions' => $this->listarDoctores($dateFilter),
            'afiliacionOptions' => $this->listarAfiliaciones($dateFilter),
            'afiliacionCategoriaOptions' => $this->listarCategoriasAfiliacion(),
            'empresaAfiliacionOptions' => $this->listarEmpresasAfiliacion(),
            'sedeOptions' => $this->listarSedes($dateFilter),
            'estadoOptions' => $this->listarEstadosReceta($dateFilter),
            'viaOptions' => $this->listarVias($dateFilter),
            'departamentoOptions' => $this->listarDepartamentos($dateFilter),
            'topMedicosOptions' => $this->listarTopMedicosOptions(),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    private function resolveDashboardFilters(array $query): array
    {
        $today = new DateTimeImmutable('today');
        $defaultEnd = $today->format('Y-m-d');
        $defaultStart = $today->modify('-29 days')->format('Y-m-d');

        $fechaInicio = $this->normalizeDateFilter((string)($query['fecha_inicio'] ?? ''), $defaultStart);
        $fechaFin = $this->normalizeDateFilter((string)($query['fecha_fin'] ?? ''), $defaultEnd);

        if ($fechaFin < $fechaInicio) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'doctor' => $this->normalizeTextFilter((string)($query['doctor'] ?? ''), 120),
            'tipo_afiliacion' => $this->afiliacionDimensions->normalizeCategoriaFilter((string)($query['tipo_afiliacion'] ?? '')),
            'empresa_afiliacion' => $this->afiliacionDimensions->normalizeEmpresaFilter((string)($query['empresa_afiliacion'] ?? '')),
            'afiliacion' => $this->afiliacionDimensions->normalizeSeguroFilter((string)($query['afiliacion'] ?? '')),
            'estado_receta' => $this->normalizeTextFilter((string)($query['estado_receta'] ?? ''), 120),
            'via' => $this->normalizeTextFilter((string)($query['via'] ?? ''), 120),
            'producto' => $this->normalizeTextFilter((string)($query['producto'] ?? ''), 120),
            'sede' => $this->normalizeSedeFilter((string)($query['sede'] ?? '')),
            'departamento' => $this->normalizeTextFilter((string)($query['departamento'] ?? ''), 120),
            'top_n_medicos' => (string)$this->normalizeTopMedicos((string)($query['top_n_medicos'] ?? '10')),
        ];
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    private function obtenerDashboardRows(array $filtros): array
    {
        $afiliacionContext = $this->afiliacionDimensionContext();
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
            $joinPaciente = 'LEFT JOIN patient_data pa ON pa.hc_number = pp.hc_number';
        }

        $hasDiagnosticosAsignados = false;
        $joinDiagnosticosAsignados = '';
        if ($this->tableExists('diagnosticos_asignados') && $this->columnExists('diagnosticos_asignados', 'form_id')) {
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
                {$afiliacionContext['categoria_expr']} AS tipo_afiliacion,
                {$afiliacionContext['empresa_label_expr']} AS empresa_afiliacion,
                {$afiliacionContext['seguro_label_expr']} AS afiliacion,
                {$this->sedeExpression()} AS sede,
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
            {$afiliacionContext['join']}
            {$joinDerivaciones}
            {$joinConsulta}
            {$joinPaciente}
            {$joinDiagnosticosAsignados}
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $this->appendStandardFilters($sql, $params, $filtros, true, true, true, true, true, true, true);
        $sql .= ' ORDER BY re.created_at DESC, re.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    private function obtenerConciliacionRows(array $filtros): array
    {
        if (!$this->tableExists('farmacia_recetas_conciliacion')) {
            return [];
        }

        $afiliacionContext = $this->afiliacionDimensionContext();

        $sql = "
            SELECT
                ri.id,
                ri.form_id,
                ri.id_ui AS receta_id_ui,
                ri.estado_receta,
                ri.producto,
                ri.vias,
                ri.unidad,
                ri.pauta,
                ri.dosis,
                ri.cantidad,
                ri.total_farmacia,
                ri.observaciones,
                ri.created_at,
                ri.updated_at AS receta_updated_at,
                frc.receta_id AS conciliacion_receta_id,
                frc.pedido_id,
                frc.cedula_paciente,
                frc.paciente,
                frc.fecha_receta,
                frc.producto_receta_id,
                frc.codigo_producto_receta,
                frc.producto_receta,
                frc.factura_id,
                frc.detalle_factura_id,
                frc.fecha_factura,
                frc.fecha_facturacion,
                frc.departamento_factura,
                frc.cedula_cliente_factura,
                frc.producto_factura_id,
                frc.codigo_producto_factura,
                frc.producto_factura,
                frc.cantidad_facturada,
                frc.precio_unitario_facturado,
                frc.descuento_total_linea,
                frc.descuento_bos_linea,
                frc.monto_linea_neto,
                frc.monto_linea_unitario_neto,
                frc.diff_dias,
                frc.tipo_match,
                frc.source_from,
                frc.source_to,
                frc.matched_at,
                frc.updated_at AS conciliacion_updated_at,
                {$this->doctorExpression()} AS doctor,
                {$afiliacionContext['categoria_expr']} AS tipo_afiliacion,
                {$afiliacionContext['empresa_label_expr']} AS empresa_afiliacion,
                {$afiliacionContext['seguro_label_expr']} AS afiliacion,
                {$this->sedeExpression()} AS sede,
                {$this->departamentoExpression()} AS departamento_clinico
            FROM recetas_items ri
            LEFT JOIN farmacia_recetas_conciliacion frc
              ON CAST(ri.id_ui AS UNSIGNED) = CAST(frc.receta_id AS UNSIGNED)
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ri.form_id
            {$afiliacionContext['join']}
            WHERE 1 = 1
        ";

        $params = [];

        $fechaInicio = trim((string)($filtros['fecha_inicio'] ?? ''));
        if ($fechaInicio !== '') {
            $sql .= ' AND DATE(COALESCE(frc.fecha_receta, ri.created_at)) >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = trim((string)($filtros['fecha_fin'] ?? ''));
        if ($fechaFin !== '') {
            $sql .= ' AND DATE(COALESCE(frc.fecha_receta, ri.created_at)) <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin;
        }

        $doctor = trim((string)($filtros['doctor'] ?? ''));
        if ($doctor !== '') {
            $sql .= " AND {$this->doctorExpression()} = :doctor";
            $params[':doctor'] = $doctor;
        }

        $producto = trim((string)($filtros['producto'] ?? ''));
        if ($producto !== '') {
            $sql .= " AND (
                COALESCE(NULLIF(TRIM(ri.producto), ''), '') LIKE :producto
                OR COALESCE(NULLIF(TRIM(frc.producto_receta), ''), '') LIKE :producto
                OR COALESCE(NULLIF(TRIM(frc.producto_factura), ''), '') LIKE :producto
            )";
            $params[':producto'] = '%' . $producto . '%';
        }

        $afiliacion = trim((string)($filtros['afiliacion'] ?? ''));
        if ($afiliacion !== '') {
            $sql .= " AND {$afiliacionContext['seguro_key_expr']} = :afiliacion";
            $params[':afiliacion'] = $afiliacion;
        }

        $tipoAfiliacion = trim((string)($filtros['tipo_afiliacion'] ?? ''));
        if ($tipoAfiliacion !== '') {
            $sql .= " AND {$afiliacionContext['categoria_expr']} = :tipo_afiliacion";
            $params[':tipo_afiliacion'] = $tipoAfiliacion;
        }

        $empresaAfiliacion = trim((string)($filtros['empresa_afiliacion'] ?? ''));
        if ($empresaAfiliacion !== '') {
            $sql .= " AND {$afiliacionContext['empresa_key_expr']} = :empresa_afiliacion";
            $params[':empresa_afiliacion'] = $empresaAfiliacion;
        }

        $estado = trim((string)($filtros['estado_receta'] ?? ''));
        if ($estado !== '') {
            $sql .= " AND COALESCE(NULLIF(TRIM(ri.estado_receta), ''), '" . self::ESTADO_FALLBACK . "') = :estado_receta";
            $params[':estado_receta'] = $estado;
        }

        $via = trim((string)($filtros['via'] ?? ''));
        if ($via !== '') {
            $sql .= " AND COALESCE(NULLIF(TRIM(ri.vias), ''), '" . self::VIA_FALLBACK . "') = :via";
            $params[':via'] = $via;
        }

        $sede = trim((string)($filtros['sede'] ?? ''));
        if ($sede !== '') {
            $sql .= " AND {$this->sedeExpression()} = :sede";
            $params[':sede'] = $sede;
        }

        $departamento = trim((string)($filtros['departamento'] ?? ''));
        if ($departamento !== '') {
            $sql .= " AND {$this->departamentoExpression()} = :departamento";
            $params[':departamento'] = $departamento;
        }

        $sql .= ' ORDER BY COALESCE(frc.fecha_receta, DATE(ri.created_at)) DESC, ri.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, string>
     */
    private function listarDoctores(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->doctorExpression()} AS doctor
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= ' ORDER BY doctor ASC';

        return $this->fetchColumnList($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, string>
     */
    private function listarAfiliaciones(array $filtros = []): array
    {
        return $this->afiliacionDimensions->getSeguroOptions('Todos los seguros', (string)($filtros['empresa_afiliacion'] ?? ''));
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function listarCategoriasAfiliacion(): array
    {
        return $this->afiliacionDimensions->getCategoriaOptions('Todos los tipos');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function listarEmpresasAfiliacion(): array
    {
        return $this->afiliacionDimensions->getEmpresaOptions('Todas las empresas');
    }

    /**
     * @return array<int, int>
     */
    private function listarTopMedicosOptions(): array
    {
        return [10, 20, 30, 50];
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, string>
     */
    private function listarEstadosReceta(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->estadoExpression()} AS estado_receta
            FROM recetas_items re
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= ' ORDER BY estado_receta ASC';

        return $this->fetchColumnList($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, string>
     */
    private function listarVias(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->viaExpression()} AS via
            FROM recetas_items re
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= ' ORDER BY via ASC';

        return $this->fetchColumnList($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, string>
     */
    private function listarSedes(array $filtros = []): array
    {
        $sql = "
            SELECT DISTINCT {$this->sedeExpression()} AS sede
            FROM recetas_items re
            LEFT JOIN procedimiento_proyectado pp ON re.form_id = pp.form_id
            WHERE 1 = 1
        ";

        $params = [];
        $this->appendDateFilters($sql, $params, $filtros);
        $sql .= " ORDER BY CASE sede
                    WHEN 'MATRIZ' THEN 1
                    WHEN 'CEIBOS' THEN 2
                    WHEN '" . self::SEDE_FALLBACK . "' THEN 99
                    ELSE 50
                 END, sede ASC";

        return $this->fetchColumnList($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<int, string>
     */
    private function listarDepartamentos(array $filtros = []): array
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

        return $this->fetchColumnList($sql, $params);
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

    private function normalizeDateFilter(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return (new DateTimeImmutable($fallback))->format('Y-m-d');
    }

    private function normalizeTextFilter(string $value, int $maxLength = 120): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, $maxLength);
    }

    private function normalizeTopMedicos(string $value): int
    {
        $allowed = [10, 20, 30, 50];
        $value = (int)trim($value);

        return in_array($value, $allowed, true) ? $value : 10;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $filters
     * @param array<int, array<string, mixed>> $conciliationRows
     * @return array<string, mixed>
     */
    private function buildDashboardSummary(array $rows, array $filters, array $conciliationRows = []): array
    {
        $totalItems = count($rows);
        $episodios = [];
        $pacientes = [];
        $medicos = [];
        $productos = [];

        $totalCantidad = 0;
        $totalFarmacia = 0;
        $sinDespacho = 0;
        $parcialDespacho = 0;
        $completoDespacho = 0;
        $sobreDespacho = 0;

        $tatHoras = [];
        $sla24Total = 0;
        $sla24Cumple = 0;

        $serieDiaria = [];
        $estadoMap = [];
        $viaMap = [];
        $afiliacionMap = [];
        $doctorMap = [];
        $productoMap = [];
        $departamentoMap = [];

        foreach ($rows as $row) {
            $formId = trim((string)($row['form_id'] ?? ''));
            if ($formId !== '') {
                $episodios[$formId] = true;
            }

            $hcNumber = trim((string)($row['hc_number'] ?? ''));
            if ($hcNumber !== '') {
                $pacientes[$hcNumber] = true;
            }

            $doctor = trim((string)($row['doctor'] ?? 'Sin médico'));
            if ($doctor !== '') {
                if ($doctor !== 'Sin médico') {
                    $medicos[$doctor] = true;
                }
                $doctorMap[$doctor] = (int)($doctorMap[$doctor] ?? 0) + 1;
            }

            $producto = trim((string)($row['producto'] ?? 'Sin producto'));
            if ($producto !== '') {
                if ($producto !== 'Sin producto') {
                    $productos[$producto] = true;
                }
                $productoMap[$producto] = (int)($productoMap[$producto] ?? 0) + 1;
            }

            $estado = trim((string)($row['estado_receta'] ?? 'Sin estado'));
            $via = trim((string)($row['via'] ?? 'Sin vía'));
            $afiliacion = trim((string)($row['afiliacion'] ?? 'Sin afiliación'));
            $departamento = trim((string)($row['departamento'] ?? 'Sin departamento'));

            $estadoMap[$estado] = (int)($estadoMap[$estado] ?? 0) + 1;
            $viaMap[$via] = (int)($viaMap[$via] ?? 0) + 1;
            $afiliacionMap[$afiliacion] = (int)($afiliacionMap[$afiliacion] ?? 0) + 1;
            $departamentoMap[$departamento] = (int)($departamentoMap[$departamento] ?? 0) + 1;

            $cantidad = max(0, (int)($row['cantidad'] ?? 0));
            $farmacia = max(0, (int)($row['total_farmacia'] ?? 0));
            $totalCantidad += $cantidad;
            $totalFarmacia += $farmacia;

            if ($farmacia <= 0) {
                $sinDespacho++;
            } elseif ($cantidad > 0 && $farmacia < $cantidad) {
                $parcialDespacho++;
            } elseif ($cantidad > 0 && $farmacia === $cantidad) {
                $completoDespacho++;
            } elseif ($cantidad > 0 && $farmacia > $cantidad) {
                $sobreDespacho++;
            } else {
                $completoDespacho++;
            }

            $dateKey = $this->extractDateKey((string)($row['fecha_receta'] ?? ''));
            if ($dateKey !== '') {
                if (!isset($serieDiaria[$dateKey])) {
                    $serieDiaria[$dateKey] = ['recetas' => 0, 'cantidad' => 0, 'farmacia' => 0];
                }
                $serieDiaria[$dateKey]['recetas']++;
                $serieDiaria[$dateKey]['cantidad'] += $cantidad;
                $serieDiaria[$dateKey]['farmacia'] += $farmacia;
            }

            $createdTs = strtotime((string)($row['fecha_receta'] ?? ''));
            $updatedTs = strtotime((string)($row['fecha_actualizacion'] ?? ''));
            if ($farmacia > 0 && $createdTs !== false && $updatedTs !== false && $updatedTs >= $createdTs) {
                $tat = ($updatedTs - $createdTs) / 3600;
                $tatHoras[] = $tat;
                $sla24Total++;
                if ($tat <= 24) {
                    $sla24Cumple++;
                }
            }
        }

        $rangeStart = DateTimeImmutable::createFromFormat('Y-m-d', (string)$filters['fecha_inicio']);
        $rangeEnd = DateTimeImmutable::createFromFormat('Y-m-d', (string)$filters['fecha_fin']);
        if ($rangeStart instanceof DateTimeImmutable && $rangeEnd instanceof DateTimeImmutable && $rangeStart <= $rangeEnd) {
            $days = (int)$rangeStart->diff($rangeEnd)->days;
            if ($days <= 120) {
                for ($cursor = $rangeStart; $cursor <= $rangeEnd; $cursor = $cursor->modify('+1 day')) {
                    $dateKey = $cursor->format('Y-m-d');
                    if (!isset($serieDiaria[$dateKey])) {
                        $serieDiaria[$dateKey] = ['recetas' => 0, 'cantidad' => 0, 'farmacia' => 0];
                    }
                }
            }
        }
        ksort($serieDiaria);

        arsort($productoMap);
        arsort($doctorMap);
        arsort($estadoMap);
        arsort($viaMap);
        arsort($afiliacionMap);
        arsort($departamentoMap);

        $topProductos = array_slice($productoMap, 0, 8, true);
        $topMedicosLimit = max(1, (int)($filters['top_n_medicos'] ?? 10));
        $topDoctores = array_slice($doctorMap, 0, $topMedicosLimit, true);

        $episodiosTotal = count($episodios);
        $pacientesTotal = count($pacientes);
        $medicosActivos = count($medicos);
        $productosDistintos = count($productos);
        $promedioItemsPorEpisodio = $episodiosTotal > 0 ? ($totalItems / $episodiosTotal) : 0.0;
        $coberturaDespacho = $totalCantidad > 0 ? (($totalFarmacia * 100) / $totalCantidad) : null;
        $sinDespachoPct = $totalItems > 0 ? (($sinDespacho * 100) / $totalItems) : null;
        $parcialDespachoPct = $totalItems > 0 ? (($parcialDespacho * 100) / $totalItems) : null;
        $sla24Pct = $sla24Total > 0 ? (($sla24Cumple * 100) / $sla24Total) : null;

        $tatPromedio = !empty($tatHoras) ? array_sum($tatHoras) / count($tatHoras) : null;
        $tatMediana = $this->calcularPercentil($tatHoras, 0.50);
        $tatP90 = $this->calcularPercentil($tatHoras, 0.90);

        $diaPico = '—';
        $diaPicoTotal = 0;
        foreach ($serieDiaria as $dateKey => $dayValues) {
            $recetasDia = (int)($dayValues['recetas'] ?? 0);
            if ($recetasDia > $diaPicoTotal) {
                $diaPicoTotal = $recetasDia;
                $diaPico = $this->formatShortDate($dateKey);
            }
        }

        $baseCards = [
            ['label' => 'Ítems de recetas', 'value' => $totalItems, 'hint' => 'Líneas de medicación emitidas en el rango'],
            ['label' => 'Episodios con receta', 'value' => $episodiosTotal, 'hint' => 'Formularios únicos con medicación'],
            ['label' => 'Pacientes únicos', 'value' => $pacientesTotal, 'hint' => 'HC distintas con prescripción'],
            ['label' => 'Médicos activos', 'value' => $medicosActivos, 'hint' => 'Profesionales que recetaron en el periodo'],
            ['label' => 'Productos distintos', 'value' => $productosDistintos, 'hint' => 'Variedad de fármacos prescritos'],
            ['label' => 'Unidades prescritas', 'value' => $totalCantidad, 'hint' => 'Suma de cantidad solicitada'],
            ['label' => 'Ítems por episodio', 'value' => number_format($promedioItemsPorEpisodio, 2), 'hint' => 'Promedio de líneas por formulario'],
            ['label' => 'Día pico', 'value' => $diaPico, 'hint' => $diaPicoTotal > 0 ? ($diaPicoTotal . ' ítems de receta') : 'Sin actividad'],
        ];

        $baseMeta = [
            'tat_promedio_horas' => $tatPromedio !== null ? round($tatPromedio, 2) : null,
            'tat_mediana_horas' => $tatMediana !== null ? round($tatMediana, 2) : null,
            'tat_p90_horas' => $tatP90 !== null ? round($tatP90, 2) : null,
            'sla_24h_pct' => $sla24Pct !== null ? round($sla24Pct, 2) : null,
            'cobertura_despacho_pct' => $coberturaDespacho !== null ? round($coberturaDespacho, 2) : null,
            'sin_despacho_pct' => $sinDespachoPct !== null ? round($sinDespachoPct, 2) : null,
            'parcial_despacho_pct' => $parcialDespachoPct !== null ? round($parcialDespachoPct, 2) : null,
        ];

        $baseCharts = [
            'serie_diaria' => [
                'labels' => array_keys($serieDiaria),
                'recetas' => array_values(array_map(static fn(array $item): int => (int)($item['recetas'] ?? 0), $serieDiaria)),
                'cantidad' => array_values(array_map(static fn(array $item): int => (int)($item['cantidad'] ?? 0), $serieDiaria)),
                'farmacia' => array_values(array_map(static fn(array $item): int => (int)($item['farmacia'] ?? 0), $serieDiaria)),
            ],
            'top_productos' => ['labels' => array_keys($topProductos), 'values' => array_values($topProductos)],
            'top_doctores' => ['labels' => array_keys($topDoctores), 'values' => array_values($topDoctores)],
            'estado_receta' => ['labels' => array_keys($estadoMap), 'values' => array_values($estadoMap)],
            'vias' => ['labels' => array_keys($viaMap), 'values' => array_values($viaMap)],
            'afiliacion' => ['labels' => array_keys($afiliacionMap), 'values' => array_values($afiliacionMap)],
            'departamento' => ['labels' => array_keys($departamentoMap), 'values' => array_values($departamentoMap)],
        ];

        $conciliationSummary = $this->buildConciliacionSummary($conciliationRows, $topMedicosLimit);

        return [
            'cards' => array_merge($baseCards, $conciliationSummary['cards']),
            'meta' => array_merge($baseMeta, $conciliationSummary['meta']),
            'charts' => array_merge($baseCharts, $conciliationSummary['charts']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{cards:array<int, array<string, mixed>>,meta:array<string, mixed>,charts:array<string, mixed>}
     */
    private function buildConciliacionSummary(array $rows, int $topMedicosLimit = 10): array
    {
        if (empty($rows)) {
            return [
                'cards' => [],
                'meta' => [
                    'economia_neto_total' => null,
                    'economia_descuentos_total' => null,
                    'economia_ticket_promedio' => null,
                    'conciliacion_exacta_pct' => null,
                    'conciliacion_match_pct' => null,
                    'conciliacion_diff_promedio_dias' => null,
                ],
                'charts' => [
                    'serie_economica' => ['labels' => [], 'neto' => [], 'descuentos' => []],
                    'tipos_match' => ['labels' => [], 'values' => [], 'neto' => []],
                    'neto_afiliacion' => ['labels' => [], 'values' => []],
                    'neto_sede' => ['labels' => [], 'values' => []],
                    'neto_doctores' => ['labels' => [], 'values' => []],
                    'departamento_factura' => ['labels' => [], 'values' => []],
                ],
            ];
        }

        $totalRows = count($rows);
        $matchedRows = 0;
        $exactRows = 0;
        $sinMatchRows = 0;
        $netoTotal = 0.0;
        $netoExacto = 0.0;
        $descuentoTotal = 0.0;
        $diffDias = [];
        $serieEconomica = [];
        $tipoMatchMap = ['exacto' => 0, 'cercano' => 0, 'solo_paciente' => 0, 'sin_match' => 0];
        $tipoMatchNetoMap = ['exacto' => 0.0, 'cercano' => 0.0, 'solo_paciente' => 0.0, 'sin_match' => 0.0];
        $afiliacionNetoMap = [];
        $sedeNetoMap = [];
        $doctorNetoMap = [];
        $departamentoFacturaMap = [];

        foreach ($rows as $row) {
            $tipoMatch = trim((string)($row['tipo_match'] ?? 'sin_match'));
            if (!isset($tipoMatchMap[$tipoMatch])) {
                $tipoMatchMap[$tipoMatch] = 0;
                $tipoMatchNetoMap[$tipoMatch] = 0.0;
            }
            $tipoMatchMap[$tipoMatch]++;

            if ($tipoMatch === 'exacto') {
                $exactRows++;
            }
            if ($tipoMatch === 'sin_match') {
                $sinMatchRows++;
            }

            $facturaId = trim((string)($row['factura_id'] ?? ''));
            $montoNeto = (float)($row['monto_linea_neto'] ?? 0);
            $descuentoLinea = (float)($row['descuento_total_linea'] ?? 0) + (float)($row['descuento_bos_linea'] ?? 0);

            if ($facturaId !== '') {
                $matchedRows++;
                $netoTotal += $montoNeto;
                $descuentoTotal += $descuentoLinea;
                $tipoMatchNetoMap[$tipoMatch] += $montoNeto;

                if ($tipoMatch === 'exacto') {
                    $netoExacto += $montoNeto;
                }

                $dateKey = $this->extractDateKey((string)($row['fecha_facturacion'] ?? $row['fecha_factura'] ?? $row['fecha_receta'] ?? ''));
                if ($dateKey !== '') {
                    if (!isset($serieEconomica[$dateKey])) {
                        $serieEconomica[$dateKey] = ['neto' => 0.0, 'descuento' => 0.0];
                    }
                    $serieEconomica[$dateKey]['neto'] += $montoNeto;
                    $serieEconomica[$dateKey]['descuento'] += $descuentoLinea;
                }

                $afiliacion = trim((string)($row['afiliacion'] ?? 'Sin afiliación'));
                $sede = trim((string)($row['sede'] ?? 'Sin sede'));
                $doctor = trim((string)($row['doctor'] ?? 'Sin médico'));
                $departamentoFactura = trim((string)($row['departamento_factura'] ?? 'Sin departamento factura'));

                $afiliacionNetoMap[$afiliacion] = (float)($afiliacionNetoMap[$afiliacion] ?? 0.0) + $montoNeto;
                $sedeNetoMap[$sede] = (float)($sedeNetoMap[$sede] ?? 0.0) + $montoNeto;
                $doctorNetoMap[$doctor] = (float)($doctorNetoMap[$doctor] ?? 0.0) + $montoNeto;
                $departamentoFacturaMap[$departamentoFactura] = (float)($departamentoFacturaMap[$departamentoFactura] ?? 0.0) + $montoNeto;
            }

            $diff = $row['diff_dias'] ?? null;
            if ($facturaId !== '' && $diff !== null && $diff !== '') {
                $diffDias[] = (float)$diff;
            }
        }

        ksort($serieEconomica);
        arsort($afiliacionNetoMap);
        arsort($sedeNetoMap);
        arsort($doctorNetoMap);
        arsort($departamentoFacturaMap);

        $ticketPromedio = $matchedRows > 0 ? ($netoTotal / $matchedRows) : null;
        $exactaPct = $totalRows > 0 ? (($exactRows * 100) / $totalRows) : null;
        $matchPct = $totalRows > 0 ? (($matchedRows * 100) / $totalRows) : null;
        $diffPromedio = !empty($diffDias) ? (array_sum($diffDias) / count($diffDias)) : null;

        return [
            'cards' => [
                ['label' => 'Ingreso neto conciliado', 'value' => $this->formatCurrency($netoTotal), 'hint' => 'Suma neta facturada de recetas conciliadas'],
                ['label' => 'Ingreso neto exacto', 'value' => $this->formatCurrency($netoExacto), 'hint' => 'Valor neto de matches exactos'],
                ['label' => 'Descuentos aplicados', 'value' => $this->formatCurrency($descuentoTotal), 'hint' => 'Descuento total + BOS sobre líneas conciliadas'],
                ['label' => 'Ticket neto promedio', 'value' => $ticketPromedio !== null ? $this->formatCurrency($ticketPromedio) : '—', 'hint' => 'Promedio neto por línea conciliada'],
                ['label' => 'Tasa exacta', 'value' => $exactaPct !== null ? number_format($exactaPct, 1) . '%' : '—', 'hint' => $exactRows . ' recetas exactas sobre ' . $totalRows],
                ['label' => 'Recetas sin match', 'value' => $sinMatchRows, 'hint' => $totalRows > 0 ? number_format(($sinMatchRows * 100) / $totalRows, 1) . '% del total conciliado' : 'Sin datos'],
            ],
            'meta' => [
                'economia_neto_total' => round($netoTotal, 2),
                'economia_descuentos_total' => round($descuentoTotal, 2),
                'economia_ticket_promedio' => $ticketPromedio !== null ? round($ticketPromedio, 2) : null,
                'conciliacion_exacta_pct' => $exactaPct !== null ? round($exactaPct, 2) : null,
                'conciliacion_match_pct' => $matchPct !== null ? round($matchPct, 2) : null,
                'conciliacion_diff_promedio_dias' => $diffPromedio !== null ? round($diffPromedio, 2) : null,
            ],
            'charts' => [
                'serie_economica' => [
                    'labels' => array_keys($serieEconomica),
                    'neto' => array_values(array_map(static fn(array $item): float => round((float)($item['neto'] ?? 0), 2), $serieEconomica)),
                    'descuentos' => array_values(array_map(static fn(array $item): float => round((float)($item['descuento'] ?? 0), 2), $serieEconomica)),
                ],
                'tipos_match' => [
                    'labels' => ['Exacto', 'Cercano', 'Solo paciente', 'Sin match'],
                    'values' => [(int)$tipoMatchMap['exacto'], (int)$tipoMatchMap['cercano'], (int)$tipoMatchMap['solo_paciente'], (int)$tipoMatchMap['sin_match']],
                    'neto' => [round($tipoMatchNetoMap['exacto'], 2), round($tipoMatchNetoMap['cercano'], 2), round($tipoMatchNetoMap['solo_paciente'], 2), round($tipoMatchNetoMap['sin_match'], 2)],
                ],
                'neto_afiliacion' => [
                    'labels' => array_keys(array_slice($afiliacionNetoMap, 0, 8, true)),
                    'values' => array_values(array_map(static fn($v): float => round((float)$v, 2), array_slice($afiliacionNetoMap, 0, 8, true))),
                ],
                'neto_sede' => [
                    'labels' => array_keys(array_slice($sedeNetoMap, 0, 8, true)),
                    'values' => array_values(array_map(static fn($v): float => round((float)$v, 2), array_slice($sedeNetoMap, 0, 8, true))),
                ],
                'neto_doctores' => [
                    'labels' => array_keys(array_slice($doctorNetoMap, 0, $topMedicosLimit, true)),
                    'values' => array_values(array_map(static fn($v): float => round((float)$v, 2), array_slice($doctorNetoMap, 0, $topMedicosLimit, true))),
                ],
                'departamento_factura' => [
                    'labels' => array_keys(array_slice($departamentoFacturaMap, 0, 8, true)),
                    'values' => array_values(array_map(static fn($v): float => round((float)$v, 2), array_slice($departamentoFacturaMap, 0, 8, true))),
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildDashboardDetailRows(array $rows): array
    {
        $detail = [];
        foreach ($rows as $row) {
            $cantidad = max(0, (int)($row['cantidad'] ?? 0));
            $farmacia = max(0, (int)($row['total_farmacia'] ?? 0));
            $coverage = $cantidad > 0 ? round(($farmacia * 100) / $cantidad, 1) : null;
            $estadoDespacho = $farmacia <= 0 ? 'Sin despacho' : (($cantidad > 0 && $farmacia < $cantidad) ? 'Parcial' : (($cantidad > 0 && $farmacia > $cantidad) ? 'Sobre despacho' : 'Completo'));

            $detail[] = [
                'fecha_receta' => $this->formatDashboardDate((string)($row['fecha_receta'] ?? '')),
                'form_id' => trim((string)($row['form_id'] ?? '')),
                'sede' => trim((string)($row['sede'] ?? 'Sin sede')),
                'localidad' => trim((string)($row['localidad'] ?? '')),
                'departamento' => trim((string)($row['departamento'] ?? 'Sin departamento')),
                'doctor' => trim((string)($row['doctor'] ?? '')),
                'tipo_afiliacion' => trim((string)($row['tipo_afiliacion'] ?? '')),
                'empresa_afiliacion' => trim((string)($row['empresa_afiliacion'] ?? '')),
                'afiliacion' => trim((string)($row['afiliacion'] ?? '')),
                'estado_receta' => trim((string)($row['estado_receta'] ?? '')),
                'producto' => trim((string)($row['producto'] ?? '')),
                'cantidad' => $cantidad,
                'total_farmacia' => $farmacia,
                'diagnostico' => $this->normalizeDiagnosticoDisplay((string)($row['diagnostico'] ?? '')),
                'paciente_nombre' => trim((string)($row['paciente_nombre'] ?? 'Sin paciente')),
                'cedula_paciente' => trim((string)($row['cedula_paciente'] ?? '')),
                'edad_paciente' => ($age = $this->calculatePatientAge((string)($row['fecha_nacimiento'] ?? ''), (string)($row['fecha_receta'] ?? ''))) !== null ? (string)$age : '—',
                'cobertura' => $coverage !== null ? number_format($coverage, 1) . '%' : '—',
                'estado_despacho' => $estadoDespacho,
                'dosis' => trim((string)($row['dosis'] ?? '')),
                'procedimiento_proyectado' => trim((string)($row['procedimiento_proyectado'] ?? '')),
            ];
        }

        return $detail;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildConciliacionDetailRows(array $rows): array
    {
        $detail = [];
        foreach ($rows as $row) {
            $tipoMatch = trim((string)($row['tipo_match'] ?? 'sin_match'));
            if ($tipoMatch === 'exacto') {
                continue;
            }

            $descuentos = (float)($row['descuento_total_linea'] ?? 0) + (float)($row['descuento_bos_linea'] ?? 0);
            $detail[] = [
                'fecha_receta' => $this->formatDashboardDate((string)($row['fecha_receta'] ?? '')),
                'fecha_facturacion' => $this->formatDashboardDate((string)($row['fecha_facturacion'] ?? $row['fecha_factura'] ?? '')),
                'tipo_match' => $tipoMatch,
                'sede' => trim((string)($row['sede'] ?? 'Sin sede')),
                'empresa_afiliacion' => trim((string)($row['empresa_afiliacion'] ?? 'Sin empresa')),
                'afiliacion' => trim((string)($row['afiliacion'] ?? 'Sin afiliación')),
                'doctor' => trim((string)($row['doctor'] ?? 'Sin médico')),
                'paciente' => trim((string)($row['paciente'] ?? $row['cedula_paciente'] ?? '')),
                'producto_receta' => trim((string)($row['producto_receta'] ?? $row['producto'] ?? '')),
                'producto_factura' => trim((string)($row['producto_factura'] ?? '')),
                'monto_linea_neto' => $this->formatCurrency((float)($row['monto_linea_neto'] ?? 0)),
                'descuentos' => $this->formatCurrency($descuentos),
                'departamento_factura' => trim((string)($row['departamento_factura'] ?? '')),
            ];
        }

        return array_slice($detail, 0, 20);
    }

    private function formatDashboardDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            try {
                return (new DateTimeImmutable($value))->format('d-m-Y');
            } catch (Throwable) {
                return $value;
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('d-m-Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    private function extractDateKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }

    private function formatShortDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }
        try {
            return (new DateTimeImmutable($value))->format('d-m');
        } catch (Throwable) {
            return $value;
        }
    }

    private function normalizeDiagnosticoDisplay(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Sin diagnóstico';
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $items = [];
            foreach ($decoded as $item) {
                if (is_array($item)) {
                    $codigo = trim((string)($item['dx_code'] ?? $item['idDiagnostico'] ?? $item['codigo'] ?? ''));
                    $descripcion = trim((string)($item['descripcion'] ?? $item['diagnostico'] ?? ''));
                    if ($codigo !== '' && $descripcion !== '') {
                        $items[] = $codigo . ' - ' . $descripcion;
                    } elseif ($descripcion !== '') {
                        $items[] = $descripcion;
                    } elseif ($codigo !== '') {
                        $items[] = $codigo;
                    }
                    continue;
                }
                if (is_string($item)) {
                    $text = trim($item);
                    if ($text !== '' && !preg_match('/motivo\s+de\s+consulta/iu', $text)) {
                        $items[] = $text;
                    }
                }
            }
            $items = array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
            if ($items !== []) {
                return implode('; ', $items);
            }
        }

        $normalized = preg_replace('/\s+/', ' ', $value) ?? $value;
        if (preg_match('/diagn[oó]stic(?:o|a)s?\s*[:\-]\s*(.+)$/iu', $normalized, $match)) {
            $normalized = trim((string)($match[1] ?? ''));
        }
        $normalized = preg_replace('/^motivo(?:\s+de)?\s+consulta\s*[:\-]\s*/iu', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'Sin diagnóstico';
    }

    private function calculatePatientAge(string $birthDate, string $referenceDate): ?int
    {
        $birthDate = trim($birthDate);
        if ($birthDate === '' || $birthDate === '0000-00-00') {
            return null;
        }
        try {
            $birth = new DateTimeImmutable($birthDate);
        } catch (Throwable) {
            return null;
        }

        try {
            $reference = trim($referenceDate) !== '' ? new DateTimeImmutable($referenceDate) : new DateTimeImmutable('now');
        } catch (Throwable) {
            $reference = new DateTimeImmutable('now');
        }

        return $birth->diff($reference)->y;
    }

    /**
     * @param array<int, float|int> $values
     */
    private function calcularPercentil(array $values, float $percent): ?float
    {
        if ($values === []) {
            return null;
        }

        $percent = max(0.0, min(1.0, $percent));
        sort($values, SORT_NUMERIC);
        $n = count($values);
        if ($n === 1) {
            return (float)$values[0];
        }

        $index = ($n - 1) * $percent;
        $lower = (int)floor($index);
        $upper = (int)ceil($index);
        if ($lower === $upper) {
            return (float)$values[$lower];
        }

        $weight = $index - $lower;
        return ((float)$values[$lower] * (1 - $weight)) + ((float)$values[$upper] * $weight);
    }

    private function formatCurrency(float $value): string
    {
        return '$' . number_format($value, 2);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, string>
     */
    private function fetchColumnList(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $values = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $values), static fn(string $value): bool => $value !== ''));
    }

    /**
     * @param array<string, mixed> $filtros
     * @param array<string, mixed> $params
     */
    private function appendDateFilters(string &$sql, array &$params, array $filtros): void
    {
        $fechaInicio = trim((string)($filtros['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($filtros['fecha_fin'] ?? ''));

        if ($fechaInicio !== '') {
            $sql .= ' AND DATE(re.created_at) >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio;
        }

        if ($fechaFin !== '') {
            $sql .= ' AND DATE(re.created_at) <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin;
        }
    }

    /**
     * @param array<string, mixed> $filtros
     * @param array<string, mixed> $params
     */
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
        bool $allowDepartamento = true,
        bool $allowSede = true
    ): void {
        $afiliacionContext = $this->afiliacionDimensionContext();

        $doctor = trim((string)($filtros['doctor'] ?? ''));
        if ($allowDoctor && $doctor !== '') {
            $sql .= " AND {$this->doctorExpression()} = :doctor";
            $params[':doctor'] = $doctor;
        }

        $producto = trim((string)($filtros['producto'] ?? ''));
        if ($allowProducto && $producto !== '') {
            $sql .= ' AND re.producto LIKE :producto';
            $params[':producto'] = '%' . $producto . '%';
        }

        $afiliacion = trim((string)($filtros['afiliacion'] ?? ''));
        if ($allowAfiliacion && $afiliacion !== '') {
            $sql .= " AND {$afiliacionContext['seguro_key_expr']} = :afiliacion";
            $params[':afiliacion'] = $afiliacion;
        }

        $tipoAfiliacion = trim((string)($filtros['tipo_afiliacion'] ?? ''));
        if ($tipoAfiliacion !== '') {
            $sql .= " AND {$afiliacionContext['categoria_expr']} = :tipo_afiliacion";
            $params[':tipo_afiliacion'] = $tipoAfiliacion;
        }

        $empresaAfiliacion = trim((string)($filtros['empresa_afiliacion'] ?? ''));
        if ($empresaAfiliacion !== '') {
            $sql .= " AND {$afiliacionContext['empresa_key_expr']} = :empresa_afiliacion";
            $params[':empresa_afiliacion'] = $empresaAfiliacion;
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

        $sede = trim((string)($filtros['sede'] ?? ''));
        if ($allowSede && $sede !== '') {
            $sql .= " AND {$this->sedeExpression()} = :sede";
            $params[':sede'] = $sede;
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

    /**
     * @return array{
     *   join:string,
     *   categoria_expr:string,
     *   empresa_key_expr:string,
     *   empresa_label_expr:string,
     *   seguro_key_expr:string,
     *   seguro_label_expr:string
     * }
     */
    private function afiliacionDimensionContext(): array
    {
        return $this->afiliacionDimensions->buildContext("COALESCE(NULLIF(TRIM(pp.afiliacion), ''), '')", 'facm');
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

    private function sedeExpression(): string
    {
        $sedeExpr = $this->normalizeSqlKey($this->sedeRawExpression());

        return "CASE
            WHEN {$sedeExpr} = '' THEN '" . self::SEDE_FALLBACK . "'
            WHEN {$sedeExpr} LIKE '%ceib%' OR {$sedeExpr} LIKE '%cbo%' THEN 'CEIBOS'
            WHEN {$sedeExpr} LIKE '%matriz%' OR {$sedeExpr} LIKE '%villa_club%' OR {$sedeExpr} LIKE '%vclub%' OR {$sedeExpr} LIKE '%villa%' THEN 'MATRIZ'
            ELSE UPPER(TRIM({$this->sedeRawExpression()}))
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

        if ($nameParts === []) {
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

        foreach (['fecha_nacimiento', 'birthdate', 'dob', 'DOB'] as $column) {
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
