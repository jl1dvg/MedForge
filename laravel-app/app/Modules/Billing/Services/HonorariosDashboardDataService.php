<?php

namespace App\Modules\Billing\Services;

use App\Modules\Shared\Support\AfiliacionDimensionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class HonorariosDashboardDataService
{
    private PDO $db;
    private AfiliacionDimensionService $afiliacionDimensions;
    private HonorariosSettingsService $settingsService;
    /** @var array<string, true>|null */
    private ?array $allowedDoctorKeys = null;
    /** @var array<string, string>|null */
    private ?array $doctorDisplayByKey = null;
    /** @var array<string, float|null> */
    private array $honorarioCodigoCache = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connection()->getPdo();
        $this->afiliacionDimensions = new AfiliacionDimensionService($this->db);
        $this->settingsService = new HonorariosSettingsService();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public function buildSummary(string $start, string $end, array $filters, array $rules): array
    {
        $rows = $this->fetchProcedimientos($start, $end, $filters);
        $normalizedRules = $this->settingsService->rules();

        $kpis = $this->buildKpis($rows, $normalizedRules);

        return [
            'kpis' => $kpis,
            'series' => [
                'por_afiliacion' => $this->groupByAffiliation($rows, $normalizedRules),
                'por_cirujano' => $this->groupBySurgeon($rows, $normalizedRules),
                'por_tipo' => $this->groupByType($rows, $normalizedRules),
                'top_procedimientos' => $this->getTopProcedimientosFromRows($rows, 15),
            ],
            'table_mode' => $this->doctorCanonicalKey((string) ($filters['doctor'] ?? $filters['cirujano'] ?? '')) !== '' ? 'detalle' : 'resumen',
            'table' => $this->doctorCanonicalKey((string) ($filters['doctor'] ?? $filters['cirujano'] ?? '')) !== ''
                ? $this->buildAttentionTable($rows, $normalizedRules)
                : $this->buildSurgeonTable($rows, $normalizedRules),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function fetchProcedimientos(string $start, string $end, array $filters): array
    {
        $dateExpr = $this->safeAttentionDateExpr();
        $rawAfiliacionExpr = $this->rawAttentionAffiliationExpr();
        $dimensionContext = $this->afiliacionDimensions->buildContext($rawAfiliacionExpr, 'acm');
        $patientJoin = $this->patientJoinDefinition();
        $atencionIdExpr = $this->columnExists('procedimiento_proyectado', 'id') ? 'pp.id' : 'pp.form_id';
        $hcExpr = 'pp.hc_number';
        $sedeExpr = $this->sedeExpr();
        $estadoAgendaExpr = "COALESCE(pp.estado_agenda, '')";
        $patientNameExpr = $this->patientNameExpr();
        $bfrPacienteExpr = "NULLIF(TRIM(bfr.paciente), '')";
        $bfrProcedimientoExpr = "COALESCE(NULLIF(TRIM(bfr.procedimiento), ''), '')";
        $bfrCodigoExpr = "SUBSTRING_INDEX(COALESCE(NULLIF(TRIM(bfr.procedimiento), ''), ''), ' | ', 1)";
        $bfrMontoHonorarioExpr = $this->normalizedMoneySql('bfr.monto_honorario');
        $bfrMontoFacturadoExpr = $this->normalizedMoneySql('bfr.monto_facturado');
        $bfrNumeroFacturaExpr = "COALESCE(NULLIF(TRIM(bfr.numero_factura), ''), '')";
        $bfrFacturaIdExpr = "COALESCE(NULLIF(TRIM(bfr.factura_id), ''), '')";
        $bfrEstadoExpr = "COALESCE(NULLIF(TRIM(bfr.estado), ''), '')";
        $bfrRealizadoPorExpr = "COALESCE(NULLIF(TRIM(bfr.realizado_por), ''), '')";
        $bpJoin = $this->billingProcedimientosJoinDefinition();
        $isPublicExpr = "({$dimensionContext['categoria_expr']} = 'publico')";
        $sql = "
            SELECT
                {$atencionIdExpr} AS atencion_id,
                pp.form_id,
                {$hcExpr} AS hc_number,
                {$sedeExpr} AS sede,
                COALESCE(NULLIF(TRIM(CAST(bfr.factura_id AS CHAR)), ''), NULLIF(TRIM(CAST(bm.id AS CHAR)), ''), pp.form_id) AS billing_id,
                {$dateExpr} AS fecha,
                COALESCE(NULLIF(TRIM({$rawAfiliacionExpr}), ''), 'Sin afiliación') AS afiliacion,
                {$dimensionContext['categoria_expr']} AS categoria_seguro,
                {$dimensionContext['empresa_label_expr']} AS empresa_seguro,
                COALESCE(NULLIF(TRIM(pp.doctor), ''), 'Sin doctor') AS doctor,
                COALESCE({$bfrPacienteExpr}, {$patientNameExpr}) AS paciente,
                COALESCE(NULLIF(TRIM({$bfrCodigoExpr}), ''), NULLIF(TRIM(bp_agg.proc_codigo), ''), '') AS proc_codigo,
                COALESCE(NULLIF(TRIM({$bfrProcedimientoExpr}), ''), NULLIF(TRIM(bp_agg.proc_detalle), ''), '') AS proc_detalle,
                COALESCE(NULLIF(TRIM(pp.procedimiento_proyectado), ''), '') AS procedimiento_proyectado,
                '' AS procedimiento_categoria,
                '' AS procedimiento_cirugia,
                CASE
                    WHEN bfr.form_id IS NOT NULL THEN {$bfrMontoHonorarioExpr}
                    WHEN {$isPublicExpr} AND bm.id IS NOT NULL THEN COALESCE(bp_agg.total_procedimientos, 0)
                    ELSE 0
                END AS total_procedimientos,
                CASE
                    WHEN bfr.form_id IS NOT NULL THEN 1
                    WHEN {$isPublicExpr} AND bm.id IS NOT NULL THEN COALESCE(bp_agg.procedimientos_count, 0)
                    ELSE 1
                END AS procedimientos_count,
                CASE
                    WHEN bfr.form_id IS NOT NULL THEN {$bfrMontoFacturadoExpr}
                    WHEN {$isPublicExpr} AND bm.id IS NOT NULL THEN COALESCE(bp_agg.total_procedimientos, 0)
                    ELSE 0
                END AS monto_facturado,
                COALESCE(NULLIF(TRIM({$bfrNumeroFacturaExpr}), ''), '') AS numero_factura,
                COALESCE(NULLIF(TRIM({$bfrFacturaIdExpr}), ''), NULLIF(TRIM(CAST(bm.id AS CHAR)), ''), '') AS factura_id,
                COALESCE(NULLIF(TRIM({$bfrEstadoExpr}), ''), CASE WHEN {$isPublicExpr} AND bm.id IS NOT NULL THEN 'BILLING PUBLICO' ELSE '' END) AS estado_facturacion,
                {$bfrRealizadoPorExpr} AS realizado_por,
                CASE WHEN bfr.form_id IS NOT NULL OR ({$isPublicExpr} AND bm.id IS NOT NULL) THEN 1 ELSE 0 END AS has_facturacion,
                CASE WHEN bfr.form_id IS NOT NULL THEN 'billing_facturacion_real' WHEN {$isPublicExpr} AND bm.id IS NOT NULL THEN 'billing_main' ELSE '' END AS facturacion_fuente,
                CASE WHEN pdh.form_id IS NULL THEN 0 ELSE 1 END AS has_protocolo,
                {$estadoAgendaExpr} AS estado_agenda,
                NULL AS honorario_codigo
            FROM procedimiento_proyectado pp
            LEFT JOIN billing_facturacion_real bfr
              ON TRIM(CAST(bfr.form_id AS CHAR)) = TRIM(CAST(pp.form_id AS CHAR))
            LEFT JOIN billing_main bm
              ON TRIM(CAST(bm.form_id AS CHAR)) = TRIM(CAST(pp.form_id AS CHAR))
            {$bpJoin}
            LEFT JOIN (
                SELECT DISTINCT TRIM(CAST(form_id AS CHAR)) AS form_id
                FROM protocolo_data
                WHERE form_id IS NOT NULL AND TRIM(CAST(form_id AS CHAR)) <> ''
            ) pdh ON pdh.form_id = TRIM(CAST(pp.form_id AS CHAR))
            {$patientJoin}
            {$dimensionContext['join']}
            WHERE {$dateExpr} BETWEEN :inicio AND :fin
              AND COALESCE(pp.sigcenter_present, 1) = 1
        ";
        $sql .= " AND UPPER(TRIM(COALESCE(pp.estado_agenda, ''))) NOT LIKE 'CANCELADO%'";

        $params = [
            ':inicio' => $start,
            ':fin' => $end,
        ];

        $sedeFilter = $this->normalizeSedeFilter($filters['sede'] ?? null);
        if ($sedeFilter !== '') {
            $sql .= " AND UPPER(TRIM({$sedeExpr})) = :sede";
            $params[':sede'] = $sedeFilter;
        }

        $categoriaFilters = $this->normalizeCategoriaFilters($filters['categoria_seguro'] ?? null);
        if ($categoriaFilters !== []) {
            $placeholders = [];
            foreach ($categoriaFilters as $index => $categoriaFilter) {
                $placeholder = ':categoria_seguro_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $categoriaFilter;
            }
            $sql .= " AND {$dimensionContext['categoria_expr']} IN (" . implode(', ', $placeholders) . ")";
        }

        $empresaFilters = $this->normalizeEmpresaFilters($filters['empresa_seguro'] ?? null);
        if ($empresaFilters !== []) {
            $placeholders = [];
            foreach ($empresaFilters as $index => $empresaFilter) {
                $placeholder = ':empresa_seguro_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $empresaFilter;
            }
            $sql .= " AND {$dimensionContext['empresa_key_expr']} IN (" . implode(', ', $placeholders) . ")";
        }

        $seguroFilters = $this->normalizeSeguroFilters($filters['seguro'] ?? null);
        if ($seguroFilters !== []) {
            $placeholders = [];
            foreach ($seguroFilters as $index => $seguroFilter) {
                $placeholder = ':seguro_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $seguroFilter;
            }
            $sql .= " AND {$dimensionContext['seguro_key_expr']} IN (" . implode(', ', $placeholders) . ")";
        }

        $sql .= " ORDER BY fecha ASC, pp.form_id ASC, atencion_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tipoFilters = $this->normalizeTipoFilters($filters['tipo_procedimiento'] ?? null);
        $doctorFilter = $this->doctorCanonicalKey((string) ($filters['doctor'] ?? $filters['cirujano'] ?? ''));
        $allowedDoctorKeys = $this->allowedDoctorKeys();

        $output = [];
        foreach ($rows as $row) {
            $tipo = $this->clasificarTipoProcedimiento($row);
            if ($tipoFilters !== [] && !in_array($tipo, $tipoFilters, true)) {
                continue;
            }
            $rowDoctorKey = $this->doctorCanonicalKey((string) ($row['doctor'] ?? ''));
            if ($allowedDoctorKeys !== [] && ($rowDoctorKey === '' || !isset($allowedDoctorKeys[$rowDoctorKey]))) {
                continue;
            }
            if ($doctorFilter !== '' && $rowDoctorKey !== $doctorFilter) {
                continue;
            }

            $row['doctor'] = $this->doctorDisplayName((string) ($row['doctor'] ?? ''));
            $row['tipo_procedimiento'] = $tipo;
            $codigoFacturacion = strtoupper(trim((string) ($row['proc_codigo'] ?? '')));
            [$codigoTarifario, $detalleTarifario] = $this->parseProcedureCodeDetail((string) ($row['procedimiento_proyectado'] ?? ''));
            if ($codigoFacturacion === '' && $codigoTarifario !== '') {
                $row['proc_codigo'] = $codigoTarifario;
            }
            if (trim((string) ($row['proc_detalle'] ?? '')) === '' && $detalleTarifario !== '') {
                $row['proc_detalle'] = $codigoTarifario !== ''
                    ? $codigoTarifario . ' | ' . $detalleTarifario
                    : $detalleTarifario;
            }
            $row['proc_codigo_facturacion'] = $codigoFacturacion;
            $row['proc_codigo_proyectado'] = $codigoTarifario;
            $row['honorario_codigo'] = $this->honorarioCodigoPorCodigo((string) ($row['proc_codigo'] ?? ''));
            $output[] = $row;
        }

        Log::info('billing.honorarios.rows_debug', [
            'start' => $start,
            'end' => $end,
            'filters' => $filters,
            'sql_count' => count($rows),
            'output_count' => count($output),
            'doctor_filter' => $doctorFilter,
            'tipo_filters' => $tipoFilters,
            'sample' => array_slice(array_map(static function (array $row): array {
                return [
                    'form_id' => $row['form_id'] ?? null,
                    'fecha' => $row['fecha'] ?? null,
                    'doctor' => $row['doctor'] ?? null,
                    'paciente' => $row['paciente'] ?? null,
                    'sede' => $row['sede'] ?? null,
                    'has_facturacion' => $row['has_facturacion'] ?? null,
                    'monto_honorario' => $row['total_procedimientos'] ?? null,
                    'monto_facturado' => $row['monto_facturado'] ?? null,
                ];
            }, $output), 0, 5),
        ]);

        return $output;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float> $rules
     * @return array<string, int|float>
     */
    private function buildKpis(array $rows, array $rules): array
    {
        $caseIds = [];
        $totalProcedimientos = 0;
        $totalProduccion = 0.0;
        $totalHonorarios = 0.0;

        foreach ($rows as $row) {
            $caseIds[(string) ($row['billing_id'] ?? '')] = true;
            $totalProcedimientos += (int) ($row['procedimientos_count'] ?? 0);
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalProduccion += $produccion;
            $totalHonorarios += $this->calcularHonorarioLinea($row, $rules);
        }

        unset($caseIds['']);
        $totalCasos = count($caseIds);
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
                + $this->calcularHonorarioLinea($row, $rules);
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
            $doctor = (string) ($row['doctor'] ?? 'Sin doctor');
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $totalsBySurgeon[$doctor] = ($totalsBySurgeon[$doctor] ?? 0) + $produccion;
            $honorariosBySurgeon[$doctor] = ($honorariosBySurgeon[$doctor] ?? 0)
                + $this->calcularHonorarioLinea($row, $rules);
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
            $doctor = (string) ($row['doctor'] ?? 'Sin doctor');
            if (!isset($table[$doctor])) {
                $table[$doctor] = [
                    'cirujano' => $doctor,
                    'tipos' => [],
                    'casos_map' => [],
                    'casos' => 0,
                    'procedimientos' => 0,
                    'produccion' => 0.0,
                    'honorarios' => 0.0,
                ];
            }

            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $tipo = (string) ($row['tipo_procedimiento'] ?? 'otros');
            $table[$doctor]['tipos'][$tipo] = true;
            $table[$doctor]['casos_map'][(string) ($row['billing_id'] ?? '')] = true;
            $table[$doctor]['procedimientos'] += (int) ($row['procedimientos_count'] ?? 0);
            $table[$doctor]['produccion'] += $produccion;
            $table[$doctor]['honorarios'] += $this->calcularHonorarioLinea($row, $rules);
        }

        usort($table, static fn($a, $b) => ((float) ($b['produccion'] ?? 0) <=> (float) ($a['produccion'] ?? 0)));

        return array_map(static function ($row) {
            unset($row['casos_map']['']);
            $row['casos'] = count($row['casos_map']);
            $row['tipo'] = implode(', ', array_map(static function (string $tipo): string {
                return match ($tipo) {
                    'cirugias' => 'Cirugías',
                    'imagenes' => 'Imágenes',
                    'pni' => 'PNI',
                    'servicios_oftalmologicos' => 'Servicios oftalmológicos',
                    default => 'Otros',
                };
            }, array_keys($row['tipos'])));
            unset($row['tipos'], $row['casos_map']);
            $row['produccion'] = round((float) ($row['produccion'] ?? 0), 2);
            $row['honorarios'] = round((float) ($row['honorarios'] ?? 0), 2);
            return $row;
        }, $table);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    private function buildAttentionTable(array $rows, array $rules): array
    {
        $table = [];

        foreach ($rows as $row) {
            $produccion = (float) ($row['total_procedimientos'] ?? 0);
            $honorario = $this->calcularHonorarioLinea($row, $rules);
            $tipo = (string) ($row['tipo_procedimiento'] ?? 'otros');
            $categoriaSeguro = strtolower(trim((string) ($row['categoria_seguro'] ?? '')));
            $rule = $this->resolveHonorarioRule($rules, $tipo, $categoriaSeguro);
            if ($this->debeUsarHonorarioCodigo($tipo, $categoriaSeguro)) {
                $rule['modo'] = 'honorario_codigo';
                $rule['fuente'] = 'catalogo_no_publico';
            }
            $table[] = [
                'fecha' => $this->formatDate((string) ($row['fecha'] ?? '')),
                'form_id' => (string) ($row['form_id'] ?? ''),
                'hc_number' => (string) ($row['hc_number'] ?? ''),
                'sede' => (string) ($row['sede'] ?? ''),
                'paciente' => trim((string) ($row['paciente'] ?? '')) ?: 'Paciente sin nombre',
                'cirujano' => (string) ($row['doctor'] ?? 'Sin doctor'),
                'realizado_por' => trim((string) ($row['realizado_por'] ?? '')),
                'tipo' => $this->formatTipo((string) ($row['tipo_procedimiento'] ?? 'otros')),
                'procedimiento' => trim((string) ($row['proc_detalle'] ?? '')) ?: trim((string) ($row['procedimiento_proyectado'] ?? '')) ?: 'Sin detalle',
                'proc_codigo' => (string) ($row['proc_codigo'] ?? ''),
                'proc_codigo_facturacion' => (string) ($row['proc_codigo_facturacion'] ?? ''),
                'proc_codigo_proyectado' => (string) ($row['proc_codigo_proyectado'] ?? ''),
                'honorario_codigo' => is_numeric($row['honorario_codigo'] ?? null) ? round((float) $row['honorario_codigo'], 2) : null,
                'categoria_seguro' => (string) ($row['categoria_seguro'] ?? ''),
                'honorario_rule' => $rule,
                'afiliacion' => (string) ($row['afiliacion'] ?? 'Sin afiliación'),
                'empresa_seguro' => (string) ($row['empresa_seguro'] ?? ''),
                'produccion' => round($produccion, 2),
                'honorarios' => round($honorario, 2),
                'monto_facturado' => round((float) ($row['monto_facturado'] ?? 0), 2),
                'numero_factura' => (string) ($row['numero_factura'] ?? ''),
                'factura_id' => (string) ($row['factura_id'] ?? ''),
                'has_facturacion' => (int) ($row['has_facturacion'] ?? 0),
                'facturacion_fuente' => (string) ($row['facturacion_fuente'] ?? ''),
                'has_protocolo' => (int) ($row['has_protocolo'] ?? 0),
                'estado_facturacion' => $this->estadoHonorario(
                    (int) ($row['has_facturacion'] ?? 0),
                    (int) ($row['has_protocolo'] ?? 0),
                    (string) ($row['tipo_procedimiento'] ?? 'otros'),
                    (string) ($row['estado_facturacion'] ?? '')
                ),
            ];
        }

        usort($table, static fn(array $a, array $b): int => strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? '')));

        return $table;
    }

    private function estadoHonorario(int $hasFacturacion, int $hasProtocolo, string $tipo, string $estadoFacturacion): string
    {
        if ($hasFacturacion === 1) {
            return trim($estadoFacturacion) ?: 'Facturada';
        }

        if (($this->normalizeTipoFilter($tipo) ?: $tipo) === 'cirugias' && $hasProtocolo !== 1) {
            return 'Sin protocolo';
        }

        return 'Pendiente facturación';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, float> $rules
     * @return array{labels:array<int,string>,totals:array<int,float>,honorarios:array<int,float>}
     */
    private function groupByType(array $rows, array $rules): array
    {
        $labelsMap = [
            'cirugias' => 'Cirugías',
            'imagenes' => 'Imágenes',
            'pni' => 'PNI',
            'servicios_oftalmologicos' => 'Servicios oftalmológicos',
            'otros' => 'Otros',
        ];
        $totals = [];
        $honorarios = [];

        foreach ($rows as $row) {
            $tipo = (string) ($row['tipo_procedimiento'] ?? 'otros');
            $label = $labelsMap[$tipo] ?? 'Otros';
            $totals[$label] = ($totals[$label] ?? 0) + (float) ($row['total_procedimientos'] ?? 0);
            $honorarios[$label] = ($honorarios[$label] ?? 0) + $this->calcularHonorarioLinea($row, $rules);
        }

        arsort($totals);
        $labels = array_keys($totals);

        return [
            'labels' => $labels,
            'totals' => array_map(static fn($label) => round((float) ($totals[$label] ?? 0), 2), $labels),
            'honorarios' => array_map(static fn($label) => round((float) ($honorarios[$label] ?? 0), 2), $labels),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{labels:array<int,string>,totals:array<int,float>}
     */
    private function getTopProcedimientosFromRows(array $rows, int $limit): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $label = trim((string) ($row['proc_detalle'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['proc_codigo'] ?? '')) ?: 'Sin detalle';
            }
            $totals[$label] = ($totals[$label] ?? 0) + (float) ($row['total_procedimientos'] ?? 0);
        }

        arsort($totals);
        $totals = array_slice($totals, 0, $limit, true);

        return [
            'labels' => array_keys($totals),
            'totals' => array_map(static fn($value) => round((float) $value, 2), array_values($totals)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{labels:array<int,string>,totals:array<int,float>}
     */
    private function getTopProcedimientos(string $start, string $end, array $filters, int $limit): array
    {
        $dateExpr = $this->safeBillingDateExpr();
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pa.afiliacion), ''), '')";
        $dimensionContext = $this->afiliacionDimensions->buildContext($rawAfiliacionExpr, 'acm');
        $sql = "
            SELECT COALESCE(NULLIF(TRIM(bp.proc_detalle), ''), bp.proc_codigo, 'Sin detalle') AS procedimiento,
                   SUM(bp.proc_precio) AS total
            FROM billing_procedimientos bp
            INNER JOIN billing_main bm ON bm.id = bp.billing_id
            LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id AND COALESCE(pp.sigcenter_present, 1) = 1
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            {$dimensionContext['join']}
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

        $categoriaFilter = $this->afiliacionDimensions->normalizeCategoriaFilter((string) ($filters['categoria_seguro'] ?? ''));
        if ($categoriaFilter !== '') {
            $sql .= " AND {$dimensionContext['categoria_expr']} = :categoria_seguro";
            $params[':categoria_seguro'] = $categoriaFilter;
        }

        $empresaFilter = $this->afiliacionDimensions->normalizeEmpresaFilter((string) ($filters['empresa_seguro'] ?? ''));
        if ($empresaFilter !== '') {
            $sql .= " AND {$dimensionContext['empresa_key_expr']} = :empresa_seguro";
            $params[':empresa_seguro'] = $empresaFilter;
        }

        $seguroFilter = $this->afiliacionDimensions->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $sql .= " AND {$dimensionContext['seguro_key_expr']} = :seguro";
            $params[':seguro'] = $seguroFilter;
        }

        $sql .= " GROUP BY procedimiento ORDER BY total DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $params[':inicio']);
        $stmt->bindValue(':fin', $params[':fin']);
        if (isset($params[':cirujano'])) {
            $stmt->bindValue(':cirujano', $params[':cirujano']);
        }
        if (isset($params[':categoria_seguro'])) {
            $stmt->bindValue(':categoria_seguro', $params[':categoria_seguro']);
        }
        if (isset($params[':empresa_seguro'])) {
            $stmt->bindValue(':empresa_seguro', $params[':empresa_seguro']);
        }
        if (isset($params[':seguro'])) {
            $stmt->bindValue(':seguro', $params[':seguro']);
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
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $rules
     */
    private function calcularHonorarioLinea(array $row, array $rules): float
    {
        if ((int) ($row['has_facturacion'] ?? 0) === 0) {
            return 0.0;
        }

        $produccion = (float) ($row['total_procedimientos'] ?? 0);
        $tipo = (string) ($row['tipo_procedimiento'] ?? 'otros');
        $categoriaSeguro = strtolower(trim((string) ($row['categoria_seguro'] ?? '')));
        $honorarioCodigo = $row['honorario_codigo'] ?? null;
        if ($this->debeUsarHonorarioCodigo($tipo, $categoriaSeguro)) {
            return is_numeric($honorarioCodigo) ? (float) $honorarioCodigo : 0.0;
        }

        $rule = $this->resolveHonorarioRule($rules, $tipo, $categoriaSeguro);

        if (($rule['modo'] ?? '') === 'honorario_codigo') {
            return is_numeric($honorarioCodigo) ? (float) $honorarioCodigo : 0.0;
        }

        $percentage = is_numeric($rule['porcentaje'] ?? null) ? (float) $rule['porcentaje'] : 0.0;

        return $produccion * ($percentage / 100);
    }

    private function debeUsarHonorarioCodigo(string $tipo, string $categoriaSeguro): bool
    {
        $tipo = $this->normalizeTipoFilter($tipo) ?: $tipo;
        $categoriaSeguro = $this->afiliacionDimensions->normalizeCategoriaFilter($categoriaSeguro) ?: $categoriaSeguro;

        return in_array($tipo, ['cirugias', 'pni'], true) && $categoriaSeguro !== 'publico';
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    private function resolveHonorarioRule(array $rules, string $tipo, string $categoria): array
    {
        $tipo = $this->normalizeTipoFilter($tipo) ?: 'otros';
        $categoria = $this->afiliacionDimensions->normalizeCategoriaFilter($categoria) ?: 'otros';
        $fallback = ['modo' => 'porcentaje', 'porcentaje' => 30.0];

        foreach ([
            [$tipo, $categoria],
            [$tipo, '*'],
            ['*', $categoria],
            ['*', '*'],
        ] as [$tipoMatch, $categoriaMatch]) {
            foreach ($rules as $rule) {
                if (($rule['tipo_atencion'] ?? '') !== $tipoMatch || ($rule['categoria_afiliacion'] ?? '') !== $categoriaMatch) {
                    continue;
                }

                return is_array($rule) ? $rule : $fallback;
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTipoFilters(mixed $value): array
    {
        return $this->normalizeFilterValues($value, fn(string $item): string => $this->normalizeTipoFilter($item));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeCategoriaFilters(mixed $value): array
    {
        return $this->normalizeFilterValues($value, fn(string $item): string => $this->afiliacionDimensions->normalizeCategoriaFilter($item));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeEmpresaFilters(mixed $value): array
    {
        return $this->normalizeFilterValues($value, fn(string $item): string => $this->afiliacionDimensions->normalizeEmpresaFilter($item));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSeguroFilters(mixed $value): array
    {
        return $this->normalizeFilterValues($value, fn(string $item): string => $this->afiliacionDimensions->normalizeSeguroFilter($item));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeFilterValues(mixed $value, callable $normalizer): array
    {
        $items = is_array($value) ? $value : [$value];
        $normalized = [];
        foreach ($items as $item) {
            $item = $normalizer((string) $item);
            if ($item === '') {
                continue;
            }
            $normalized[$item] = $item;
        }

        return array_values($normalized);
    }

    private function safeAttentionDateExpr(): string
    {
        return "CASE
            WHEN CAST(pp.fecha AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
            ELSE pp.fecha
        END";
    }

    private function rawAttentionAffiliationExpr(): string
    {
        return "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), '')";
    }

    private function billingProcedimientosJoinDefinition(): string
    {
        return "
            LEFT JOIN (
                SELECT
                    billing_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(TRIM(proc_codigo), '') ORDER BY id SEPARATOR ' | '), ' | ', 1) AS proc_codigo,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(TRIM(proc_detalle), '') ORDER BY id SEPARATOR ' | '), ' | ', 1) AS proc_detalle,
                    COALESCE(SUM({$this->normalizedMoneySql('proc_precio')}), 0) AS total_procedimientos,
                    COUNT(*) AS procedimientos_count
                FROM billing_procedimientos
                GROUP BY billing_id
            ) AS bp_agg ON bp_agg.billing_id = bm.id
        ";
    }

    private function facturacionRealJoinDefinition(): string
    {
        if (
            !$this->columnExists('billing_facturacion_real', 'form_id')
            || !$this->columnExists('billing_facturacion_real', 'monto_honorario')
        ) {
            return "
                LEFT JOIN (
                    SELECT
                        CAST(NULL AS CHAR(50)) AS form_id,
                        CAST(NULL AS CHAR(255)) AS paciente,
                        CAST(NULL AS CHAR(100)) AS proc_codigo,
                        CAST(NULL AS CHAR(255)) AS proc_detalle,
                        CAST(NULL AS CHAR(255)) AS afiliacion,
                        0 AS monto_honorario,
                        0 AS monto_facturado,
                        CAST(NULL AS CHAR(100)) AS numero_factura,
                        CAST(NULL AS CHAR(100)) AS factura_id,
                        CAST(NULL AS CHAR(100)) AS estado_facturacion,
                        CAST(NULL AS CHAR(255)) AS realizado_por,
                        0 AS procedimientos_count
                    WHERE 1 = 0
                ) AS bfr ON bfr.form_id = TRIM(CAST(pp.form_id AS CHAR))
            ";
        }

        $pacienteExpr = $this->columnExists('billing_facturacion_real', 'paciente')
            ? "MAX(NULLIF(TRIM(paciente), ''))"
            : "CAST(NULL AS CHAR(255))";
        $codigoExpr = $this->columnExists('billing_facturacion_real', 'codigos_producto')
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(codigos_producto), '') ORDER BY codigos_producto SEPARATOR ' | ')"
            : "''";
        $procedimientoExpr = $this->columnExists('billing_facturacion_real', 'procedimiento')
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(procedimiento), '') ORDER BY procedimiento SEPARATOR ' | ')"
            : "''";
        $afiliacionExpr = $this->columnExists('billing_facturacion_real', 'afiliacion')
            ? "MAX(NULLIF(TRIM(afiliacion), ''))"
            : "CAST(NULL AS CHAR(255))";
        $montoHonorarioExpr = $this->normalizedMoneySql('monto_honorario');
        $montoFacturadoExpr = $this->columnExists('billing_facturacion_real', 'monto_facturado')
            ? $this->normalizedMoneySql('monto_facturado')
            : '0';
        $numeroFacturaExpr = $this->columnExists('billing_facturacion_real', 'numero_factura')
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(numero_factura), '') ORDER BY numero_factura SEPARATOR ' | ')"
            : "''";
        $facturaIdExpr = $this->columnExists('billing_facturacion_real', 'factura_id')
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(factura_id), '') ORDER BY factura_id SEPARATOR ' | ')"
            : "''";
        $estadoExpr = $this->columnExists('billing_facturacion_real', 'estado')
            ? "MAX(NULLIF(TRIM(estado), ''))"
            : "''";
        $realizadoPorExpr = $this->columnExists('billing_facturacion_real', 'realizado_por')
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(realizado_por), '') ORDER BY realizado_por SEPARATOR ' | ')"
            : "''";

        return "
            LEFT JOIN (
                SELECT
                    TRIM(CAST(form_id AS CHAR)) AS form_id,
                    {$pacienteExpr} AS paciente,
                    {$codigoExpr} AS proc_codigo,
                    {$procedimientoExpr} AS proc_detalle,
                    {$afiliacionExpr} AS afiliacion,
                    COALESCE(SUM({$montoHonorarioExpr}), 0) AS monto_honorario,
                    COALESCE(SUM({$montoFacturadoExpr}), 0) AS monto_facturado,
                    {$numeroFacturaExpr} AS numero_factura,
                    {$facturaIdExpr} AS factura_id,
                    {$estadoExpr} AS estado_facturacion,
                    {$realizadoPorExpr} AS realizado_por,
                    COUNT(*) AS procedimientos_count
                FROM billing_facturacion_real
                GROUP BY TRIM(CAST(form_id AS CHAR))
            ) bfr ON bfr.form_id = TRIM(CAST(pp.form_id AS CHAR))
        ";
    }

    private function patientNameExpr(): string
    {
        return "NULLIF(TRIM(CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname)), '')";
    }

    private function patientJoinDefinition(): string
    {
        return "
            LEFT JOIN (
                SELECT
                    TRIM(CAST(hc_number AS CHAR)) AS hc_number,
                    MAX(NULLIF(TRIM(lname), '')) AS lname,
                    MAX(NULLIF(TRIM(lname2), '')) AS lname2,
                    MAX(NULLIF(TRIM(fname), '')) AS fname,
                    MAX(NULLIF(TRIM(mname), '')) AS mname
                FROM patient_data
                GROUP BY TRIM(CAST(hc_number AS CHAR))
            ) pa ON pa.hc_number = TRIM(CAST(pp.hc_number AS CHAR))
        ";
    }

    private function sedeExpr(): string
    {
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF(pp.sede_departamento, ''), NULLIF(CAST(pp.id_sede AS CHAR), ''), '')))";

        return "CASE
            WHEN {$rawExpr} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$rawExpr} LIKE '%matriz%' OR {$rawExpr} LIKE '%villa%' THEN 'MATRIZ'
            ELSE ''
        END";
    }

    private function normalizeSedeFilter(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function normalizedMoneySql(string $column): string
    {
        return "CAST(
            CASE
                WHEN {$column} IS NULL THEN '0'
                WHEN CAST({$column} AS CHAR) REGEXP '^-?[0-9]+,[0-9]{1,4}$'
                    THEN REPLACE(CAST({$column} AS CHAR), ',', '.')
                ELSE REPLACE(REPLACE(REPLACE(CAST({$column} AS CHAR), '$', ''), ',', ''), ' ', '')
            END AS DECIMAL(14,4)
        )";
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

    /**
     * @param array<string, mixed> $row
     */
    private function clasificarTipoProcedimiento(array $row): string
    {
        $codigo = strtoupper(trim((string) ($row['proc_codigo'] ?? '')));
        $texto = $this->normalizeText(implode(' ', [
            $row['proc_detalle'] ?? '',
            $row['procedimiento_proyectado'] ?? '',
            $row['procedimiento_categoria'] ?? '',
            $row['procedimiento_cirugia'] ?? '',
        ]));

        if (in_array($codigo, ['281339'], true) || $this->textContainsPni($texto)) {
            return 'pni';
        }

        if (
            str_contains($texto, 'SERVICIOS OFTALMOLOGICOS GENERALES')
            || preg_match('/\bSER\s*-?\s*OFT\b/', $texto) === 1
            || str_starts_with($codigo, 'SER-OFT')
        ) {
            return 'servicios_oftalmologicos';
        }

        $codigosImagen = [
            '76512', '92081', '92225',
            '281010', '281021', '281032', '281229',
            '281186', '281197', '281230', '281306', '281295',
        ];
        if (
            in_array($codigo, $codigosImagen, true)
            || str_contains($texto, 'IMAGEN')
            || str_contains($texto, 'EXAMEN')
        ) {
            return 'imagenes';
        }

        if (
            str_contains($texto, 'CIRUG')
            || str_contains($texto, 'QUIROF')
            || str_contains($texto, 'PROTOCOLO')
        ) {
            return 'cirugias';
        }

        return 'otros';
    }

    private function normalizeTipoFilter(string $value): string
    {
        $value = $this->normalizeText($value);
        return match ($value) {
            'CIRUGIA', 'CIRUGIAS' => 'cirugias',
            'IMAGEN', 'IMAGENES' => 'imagenes',
            'PNI' => 'pni',
            'SERVICIOS_OFTALMOLOGICOS', 'SERVICIOS OFTALMOLOGICOS', 'SERVICIO OFTALMOLOGICO', 'OFTALMOLOGICOS', 'SER OFT' => 'servicios_oftalmologicos',
            'OTROS' => 'otros',
            default => '',
        };
    }

    private function textContainsPni(string $text): bool
    {
        return $text !== '' && (
            str_starts_with($text, 'PNI')
            || str_contains($text, ' PNI ')
            || str_contains($text, '(PNI')
            || str_contains($text, '/PNI')
            || str_contains($text, '-PNI')
        );
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = mb_strtoupper($value, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function doctorCanonicalKey(string $value): string
    {
        $normalized = $this->normalizeDoctorReference($value);
        if ($normalized === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, static fn($token): bool => $token !== ''));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    private function normalizeDoctorReference(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
        if ($normalized === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = $ascii;
        }

        $normalized = mb_strtoupper($normalized, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    private function doctorDisplayName(string $value): string
    {
        $key = $this->doctorCanonicalKey($value);
        if ($key === '') {
            return trim($value) !== '' ? trim($value) : 'Sin doctor';
        }

        $displayByKey = $this->doctorDisplayByKey();

        return $displayByKey[$key] ?? (trim($value) !== '' ? trim($value) : 'Sin doctor');
    }

    /**
     * @return array<string, string>
     */
    private function doctorDisplayByKey(): array
    {
        if ($this->doctorDisplayByKey !== null) {
            return $this->doctorDisplayByKey;
        }

        $displayByKey = [];
        try {
            $stmt = $this->db->query(
                "SELECT DISTINCT NULLIF(TRIM(nombre), '') AS doctor
                 FROM users
                 WHERE nombre IS NOT NULL
                   AND TRIM(nombre) <> ''
                   AND (
                     UPPER(TRIM(especialidad)) = 'CIRUJANO OFTALMÓLOGO'
                     OR UPPER(TRIM(especialidad)) = 'CIRUJANO OFTALMOLOGO'
                   )
                 ORDER BY doctor ASC"
            );
            while ($row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false) {
                $doctor = trim((string) ($row['doctor'] ?? ''));
                $key = $this->doctorCanonicalKey($doctor);
                if ($key !== '' && $doctor !== '' && !isset($displayByKey[$key])) {
                    $displayByKey[$key] = $doctor;
                }
            }
        } catch (\Throwable) {
            $displayByKey = [];
        }

        $this->doctorDisplayByKey = $displayByKey;
        $this->allowedDoctorKeys = array_fill_keys(array_keys($displayByKey), true);

        return $this->doctorDisplayByKey;
    }

    /**
     * @return array<string, true>
     */
    private function allowedDoctorKeys(): array
    {
        if ($this->allowedDoctorKeys !== null) {
            return $this->allowedDoctorKeys;
        }

        $this->doctorDisplayByKey();

        return $this->allowedDoctorKeys ?? [];
    }

    private function honorarioCodigoPorCodigo(string $codigo): ?float
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }
        if (str_contains($codigo, '|')) {
            $codigo = strtoupper(trim(strtok($codigo, '|') ?: $codigo));
        }
        if (array_key_exists($codigo, $this->honorarioCodigoCache)) {
            return $this->honorarioCodigoCache[$codigo];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT MAX(honorario_medico)
                 FROM tarifario_2014
                 WHERE honorario_medico IS NOT NULL
                   AND (
                     UPPER(TRIM(codigo)) = :codigo_codigo
                     OR UPPER(TRIM(modifier)) = :codigo_modifier
                     OR UPPER(TRIM(CONCAT_WS('-', NULLIF(TRIM(codigo), ''), NULLIF(TRIM(modifier), '')))) = :codigo_compuesto
                     OR UPPER(TRIM(LEADING '0' FROM codigo)) = TRIM(LEADING '0' FROM :codigo_trim)
                   )"
            );
            $stmt->execute([
                ':codigo_codigo' => $codigo,
                ':codigo_modifier' => $codigo,
                ':codigo_compuesto' => $codigo,
                ':codigo_trim' => $codigo,
            ]);
            $value = $stmt->fetchColumn();
            $this->honorarioCodigoCache[$codigo] = is_numeric($value) ? (float) $value : null;
        } catch (\Throwable $exception) {
            Log::warning('billing.honorarios.honorario_codigo_error', [
                'codigo' => $codigo,
                'message' => $exception->getMessage(),
            ]);
            $this->honorarioCodigoCache[$codigo] = null;
        }

        return $this->honorarioCodigoCache[$codigo];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseProcedureCodeDetail(string $raw): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if ($text === '') {
            return ['', ''];
        }

        if (preg_match('/^\s*([A-Z]{2,5}(?:-[A-Z0-9]{2,10}){1,3}|\d{5,6})\s*\|\s*(.+)$/i', $text, $matches) === 1) {
            return [strtoupper(trim((string) $matches[1])), trim((string) $matches[2])];
        }

        if (preg_match('/^\s*[^-]+?\s*-\s*([A-Z]{2,5}(?:-[A-Z0-9]{2,10}){1,3}|\d{5,6})\s*-\s*(.+)$/i', $text, $matches) === 1) {
            return [strtoupper(trim((string) $matches[1])), trim((string) $matches[2])];
        }

        if (preg_match('/-\s*(\d{5,6})\s*-\s*(.+)$/', $text, $matches) === 1) {
            return [trim((string) $matches[1]), trim((string) $matches[2])];
        }

        return ['', $text];
    }

    private function formatTipo(string $tipo): string
    {
        return match ($tipo) {
            'cirugias' => 'Cirugías',
            'imagenes' => 'Imágenes',
            'pni' => 'PNI',
            'servicios_oftalmologicos' => 'Servicios oftalmológicos',
            default => 'Otros',
        };
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : $value;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
            $stmt->execute([$column]);

            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return false;
        }
    }
}
