<?php

namespace App\Modules\Billing\Services;

use DateTimeImmutable;
use PDO;

class BillingParticularesReportService
{
    private PDO $db;
    /** @var array<string, bool> */
    private array $columnExistsCache = [];
    /** @var array<string, array{categoria:string,afiliacion_raw:string}>|null */
    private ?array $afiliacionCategoriaMapCache = null;
    /** @var array<int, string> */
    private const EXCLUDED_ATTENTION_TYPES = [
        'consulta optometria',
    ];

    /** @var array<int, string> */
    private const MONTH_LABELS = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @return array{from:string,to:string,date_from:string,date_to:string}
     */
    public function resolveDateRange(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = trim((string) $dateFrom);
        $dateTo = trim((string) $dateTo);

        $start = $this->parseDateInput($dateFrom, false);
        $end = $this->parseDateInput($dateTo, true);

        if ($start instanceof DateTimeImmutable || $end instanceof DateTimeImmutable) {
            if (!($start instanceof DateTimeImmutable) && $end instanceof DateTimeImmutable) {
                $start = $end->setTime(0, 0, 0);
            }
            if (!($end instanceof DateTimeImmutable) && $start instanceof DateTimeImmutable) {
                $end = $start->setTime(23, 59, 59);
            }
            if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable) {
                if ($start > $end) {
                    [$start, $end] = [$end->setTime(0, 0, 0), $start->setTime(23, 59, 59)];
                }

                return [
                    'from' => $start->format('Y-m-d H:i:s'),
                    'to' => $end->format('Y-m-d H:i:s'),
                    'date_from' => $start->format('Y-m-d'),
                    'date_to' => $end->format('Y-m-d'),
                ];
            }
        }

        $end = (new DateTimeImmutable('now'))->setTime(23, 59, 59);
        $start = $end->modify('-29 days')->setTime(0, 0, 0);

        return [
            'from' => $start->format('Y-m-d H:i:s'),
            'to' => $end->format('Y-m-d H:i:s'),
            'date_from' => $start->format('Y-m-d'),
            'date_to' => $end->format('Y-m-d'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerAtencionesParticulares(string $fechaInicio, string $fechaFin): array
    {
        $sedeExpr = $this->sedeExpression('pp');
        $estadoExpr = $this->encuentroEstadoExpression('pp');
        $atendidoCondition = $this->attendedEncounterCondition('pp');
        $referidoPrefacturaExpr = $this->referidoPrefacturaExpression('pp');
        $especificarReferidoExpr = $this->especificarReferidoPrefacturaExpression('pp');

        $sql = <<<SQL
            SELECT *
            FROM (
                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'consulta' AS tipo,
                    cd.form_id,
                    cd.fecha AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura
                FROM patient_data p
                INNER JOIN consulta_data cd ON cd.hc_number = p.hc_number
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = cd.form_id
                WHERE cd.fecha BETWEEN ? AND ?
                  AND %ATENDIDO_WHERE%

                UNION ALL

                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'protocolo' AS tipo,
                    pd.form_id,
                    pd.fecha_inicio AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura
                FROM patient_data p
                INNER JOIN protocolo_data pd ON pd.hc_number = p.hc_number
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = pd.form_id
                WHERE pd.fecha_inicio BETWEEN ? AND ?
                  AND %ATENDIDO_WHERE%
            ) AS atenciones
            WHERE atenciones.fecha IS NOT NULL
              AND atenciones.fecha NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')
            ORDER BY atenciones.fecha DESC, atenciones.form_id DESC
        SQL;
        $sql = str_replace('%SEDE_EXPR%', $sedeExpr, $sql);
        $sql = str_replace('%ESTADO_EXPR%', $estadoExpr, $sql);
        $sql = str_replace('%ATENDIDO_WHERE%', $atendidoCondition, $sql);
        $sql = str_replace('%REFERIDO_PREFACTURA_EXPR%', $referidoPrefacturaExpr, $sql);
        $sql = str_replace('%ESPECIFICAR_REFERIDO_EXPR%', $especificarReferidoExpr, $sql);

        $params = [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return [];
        }

        $enrichedRows = [];
        foreach ($rows as $row) {
            $mappedAffiliation = $this->resolveMappedAffiliation((string) ($row['afiliacion'] ?? ''));
            if ($mappedAffiliation === null) {
                continue;
            }

            $categoriaCliente = strtolower(trim((string) ($mappedAffiliation['categoria'] ?? '')));
            if (!$this->isParticularReportCategory($categoriaCliente)) {
                continue;
            }

            $mappedRawAffiliation = trim((string) ($mappedAffiliation['afiliacion_raw'] ?? ''));
            $row['afiliacion_original'] = $row['afiliacion'] ?? null;
            if ($mappedRawAffiliation !== '') {
                $row['afiliacion'] = $mappedRawAffiliation;
            }
            $row['categoria_cliente'] = $categoriaCliente;
            $tipoAtencion = $this->resolveAttentionType((string) ($row['procedimiento_proyectado'] ?? ''));
            if ($this->isExcludedAttentionType($tipoAtencion)) {
                continue;
            }

            $row['tipo_atencion'] = $tipoAtencion;
            $enrichedRows[] = $row;
        }

        return $enrichedRows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function aplicarFiltros(array $rows, array $filters): array
    {
        $afiliacion = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
        $sede = $this->normalizeSedeFilter($filters['sede'] ?? null);
        $tipoAtencion = strtoupper(trim((string) ($filters['tipo'] ?? '')));
        $procedimiento = strtolower(trim((string) ($filters['procedimiento'] ?? '')));
        $categoriaCliente = strtolower(trim((string) ($filters['categoria_cliente'] ?? '')));
        $categoriaMadreReferido = $this->normalizeReferralValue($filters['categoria_madre_referido'] ?? null);
        $dateFromTs = $this->parseDateTimestamp((string) ($filters['date_from'] ?? ''), false);
        $dateToTs = $this->parseDateTimestamp((string) ($filters['date_to'] ?? ''), true);

        if ($categoriaCliente !== '' && !$this->isParticularReportCategory($categoriaCliente)) {
            $categoriaCliente = '';
        }

        $resultado = [];
        foreach ($rows as $row) {
            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            if ($timestamp === false) {
                continue;
            }

            $afiliacionRow = strtolower(trim((string) ($row['afiliacion'] ?? '')));
            $sedeRow = $this->normalizeSedeFilter($row['sede'] ?? null);
            $tipoAtencionRow = strtoupper(trim((string) ($row['tipo_atencion'] ?? '')));
            $procedimientoRow = strtolower((string) ($row['procedimiento_proyectado'] ?? ''));
            $categoriaRow = strtolower(trim((string) ($row['categoria_cliente'] ?? '')));
            $categoriaMadreReferidoRow = $this->normalizeReferralValue($row['referido_prefactura_por'] ?? null);
            $estadoEncuentro = (string) ($row['estado_encuentro'] ?? '');

            if ($dateFromTs !== null && $timestamp < $dateFromTs) {
                continue;
            }
            if ($dateToTs !== null && $timestamp > $dateToTs) {
                continue;
            }
            if (!$this->isEncounterAttended($estadoEncuentro)) {
                continue;
            }
            if ($afiliacion !== '' && $afiliacionRow !== $afiliacion) {
                continue;
            }
            if ($sede !== '' && $sedeRow !== $sede) {
                continue;
            }
            if ($tipoAtencion !== '' && $tipoAtencionRow !== $tipoAtencion) {
                continue;
            }
            if ($categoriaCliente !== '' && $categoriaRow !== $categoriaCliente) {
                continue;
            }
            if ($categoriaMadreReferido !== '' && $categoriaMadreReferidoRow !== $categoriaMadreReferido) {
                continue;
            }
            if ($procedimiento !== '' && !str_contains($procedimientoRow, $procedimiento)) {
                continue;
            }

            $resultado[] = $row;
        }

        return $resultado;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function agruparPorMes(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            if ($timestamp === false) {
                continue;
            }

            $mes = date('Y-m', $timestamp);
            if (!isset($grouped[$mes])) {
                $grouped[$mes] = [];
            }
            $grouped[$mes][] = $row;
        }

        krsort($grouped);

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{
     *     total:int,
     *     total_consultas:int,
     *     total_protocolos:int,
     *     pacientes_unicos:int,
     *     categoria_counts:array{particular:int,privado:int},
     *     categoria_share:array{particular:float,privado:float},
     *     top_afiliaciones:array<int, array{afiliacion:string,cantidad:int}>,
     *     referido_prefactura:array{
     *         with_value:int,
     *         without_value:int,
     *         top_values:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         values:array<int, array{valor:string,cantidad:int,porcentaje:float}>
     *     },
     *     referido_prefactura_pacientes_unicos:array{
     *         with_value:int,
     *         without_value:int,
     *         top_values:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         values:array<int, array{valor:string,cantidad:int,porcentaje:float}>
     *     },
     *     referido_prefactura_consulta_nuevo_paciente:array{
     *         with_value:int,
     *         without_value:int,
     *         top_values:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         values:array<int, array{valor:string,cantidad:int,porcentaje:float}>
     *     },
     *     especificar_referido_prefactura:array{
     *         with_value:int,
     *         without_value:int,
     *         top_values:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         values:array<int, array{valor:string,cantidad:int,porcentaje:float}>
     *     },
     *     hierarquia_referidos:array{
     *         categorias:array<int, array{
     *             categoria:string,
     *             cantidad:int,
     *             porcentaje_total:float,
     *             subcategorias:array<int, array{
     *                 subcategoria:string,
     *                 cantidad:int,
     *                 porcentaje_en_categoria:float,
     *                 porcentaje_total:float
     *             }>
     *         }>,
     *         pares:array<int, array{
     *             categoria:string,
     *             categoria_total:int,
     *             subcategoria:string,
     *             cantidad:int,
     *             porcentaje_en_categoria:float,
     *             porcentaje_total:float
     *         }>
     *     },
     *     temporal:array{
     *         current_month_label:string,
     *         current_month_count:int,
     *         previous_month_label:string,
     *         previous_month_count:int,
     *         same_month_last_year_label:string,
     *         same_month_last_year_count:int,
     *         vs_previous_pct:float|null,
     *         vs_same_month_last_year_pct:float|null,
     *         trend:array{
     *             labels:array<int, string>,
     *             counts:array<int, int>
     *         }
     *     },
     *     procedimientos_volumen:array{
     *         top_10:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         concentracion:array{
     *             top_3_pct:float,
     *             top_5_pct:float,
     *             top_3_count:int,
     *             top_5_count:int
     *         }
     *     },
     *     desglose_gerencial:array{
     *         sedes:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         doctores:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         afiliaciones:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         categorias:array<int, array{valor:string,cantidad:int,porcentaje:float}>
     *     },
     *     picos:array{
     *         dias:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         horas:array<int, array{valor:string,cantidad:int,porcentaje:float}>,
     *         peak_day:array{valor:string,cantidad:int},
     *         peak_hour:array{valor:string,cantidad:int}
     *     },
     *     pacientes_frecuencia:array{
     *         nuevos:int,
     *         recurrentes:int,
     *         nuevos_pct:float,
     *         recurrentes_pct:float
     *     }
     * }
     */
    public function resumen(array $rows): array
    {
        $conteoAfiliacion = [];
        $pacientesUnicos = [];
        $pacienteAtenciones = [];
        $totalConsultas = 0;
        $totalProtocolos = 0;
        $categoriaCounts = [
            'particular' => 0,
            'privado' => 0,
        ];
        $monthCounts = [];
        $procedureCounts = [];
        $sedeCounts = [];
        $doctorCounts = [];
        $categoriaGerencialCounts = [];
        $referidoCounts = [];
        $referidoWithValue = 0;
        $referidoWithoutValue = 0;
        $referidoUniquePatientsByCategory = [];
        $referidoUniquePatientsWithValue = [];
        $referidoUniquePatientsWithoutValue = [];
        $referidoNewPatientConsultationCounts = [];
        $referidoNewPatientConsultationWithValue = 0;
        $referidoNewPatientConsultationWithoutValue = 0;
        $especificarCounts = [];
        $especificarWithValue = 0;
        $especificarWithoutValue = 0;
        $hierarchy = [];
        $dayCounts = [
            'LUNES' => 0,
            'MARTES' => 0,
            'MIERCOLES' => 0,
            'JUEVES' => 0,
            'VIERNES' => 0,
            'SABADO' => 0,
            'DOMINGO' => 0,
        ];
        $hourCounts = array_fill(0, 24, 0);

        foreach ($rows as $row) {
            $afiliacion = strtoupper(trim((string) ($row['afiliacion'] ?? '')));
            if ($afiliacion === '') {
                $afiliacion = 'SIN AFILIACION';
            }
            if (!isset($conteoAfiliacion[$afiliacion])) {
                $conteoAfiliacion[$afiliacion] = 0;
            }
            $conteoAfiliacion[$afiliacion]++;

            $hcNumber = trim((string) ($row['hc_number'] ?? ''));
            if ($hcNumber !== '') {
                $pacientesUnicos[$hcNumber] = true;
                if (!isset($pacienteAtenciones[$hcNumber])) {
                    $pacienteAtenciones[$hcNumber] = 0;
                }
                $pacienteAtenciones[$hcNumber]++;
            }

            $tipo = strtolower(trim((string) ($row['tipo'] ?? '')));
            if ($tipo === 'consulta') {
                $totalConsultas++;
            } elseif ($tipo === 'protocolo') {
                $totalProtocolos++;
            }

            $categoria = strtolower(trim((string) ($row['categoria_cliente'] ?? '')));
            if ($this->isParticularReportCategory($categoria)) {
                $categoriaCounts[$categoria] = (int) ($categoriaCounts[$categoria] ?? 0) + 1;
                $categoriaGerencial = strtoupper($categoria);
                if (!isset($categoriaGerencialCounts[$categoriaGerencial])) {
                    $categoriaGerencialCounts[$categoriaGerencial] = 0;
                }
                $categoriaGerencialCounts[$categoriaGerencial]++;
            }

            $sede = strtoupper(trim((string) ($row['sede'] ?? '')));
            if ($sede === '') {
                $sede = 'SIN SEDE';
            }
            if (!isset($sedeCounts[$sede])) {
                $sedeCounts[$sede] = 0;
            }
            $sedeCounts[$sede]++;

            $doctor = strtoupper(trim((string) ($row['doctor'] ?? '')));
            if ($doctor === '') {
                $doctor = 'SIN DOCTOR';
            }
            if (!isset($doctorCounts[$doctor])) {
                $doctorCounts[$doctor] = 0;
            }
            $doctorCounts[$doctor]++;

            $procedure = $this->resolveProcedureVolumeLabel((string) ($row['procedimiento_proyectado'] ?? ''));
            if (!isset($procedureCounts[$procedure])) {
                $procedureCounts[$procedure] = 0;
            }
            $procedureCounts[$procedure]++;

            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            if ($timestamp !== false) {
                $monthKey = date('Y-m', $timestamp);
                if (!isset($monthCounts[$monthKey])) {
                    $monthCounts[$monthKey] = 0;
                }
                $monthCounts[$monthKey]++;

                $dayName = $this->weekdayName((int) date('N', $timestamp));
                if (!isset($dayCounts[$dayName])) {
                    $dayCounts[$dayName] = 0;
                }
                $dayCounts[$dayName]++;

                $hour = (int) date('G', $timestamp);
                if (isset($hourCounts[$hour])) {
                    $hourCounts[$hour]++;
                }
            }

            $referidoValue = $this->normalizeReferralValue($row['referido_prefactura_por'] ?? null);
            if ($referidoValue === '') {
                $referidoWithoutValue++;
            } else {
                $referidoWithValue++;
                if (!isset($referidoCounts[$referidoValue])) {
                    $referidoCounts[$referidoValue] = 0;
                }
                $referidoCounts[$referidoValue]++;
            }
            if ($hcNumber !== '') {
                if ($referidoValue === '') {
                    $referidoUniquePatientsWithoutValue[$hcNumber] = true;
                } else {
                    if (!isset($referidoUniquePatientsByCategory[$referidoValue])) {
                        $referidoUniquePatientsByCategory[$referidoValue] = [];
                    }
                    $referidoUniquePatientsByCategory[$referidoValue][$hcNumber] = true;
                    $referidoUniquePatientsWithValue[$hcNumber] = true;
                }
            }
            if ($this->isNewPatientConsultationProcedure($procedure)) {
                if ($referidoValue === '') {
                    $referidoNewPatientConsultationWithoutValue++;
                } else {
                    $referidoNewPatientConsultationWithValue++;
                    if (!isset($referidoNewPatientConsultationCounts[$referidoValue])) {
                        $referidoNewPatientConsultationCounts[$referidoValue] = 0;
                    }
                    $referidoNewPatientConsultationCounts[$referidoValue]++;
                }
            }

            $especificarValue = $this->normalizeReferralValue($row['especificar_referido_prefactura'] ?? null);
            if ($especificarValue === '') {
                $especificarWithoutValue++;
            } else {
                $especificarWithValue++;
                if (!isset($especificarCounts[$especificarValue])) {
                    $especificarCounts[$especificarValue] = 0;
                }
                $especificarCounts[$especificarValue]++;
            }

            $hierarchyCategory = $referidoValue !== '' ? $referidoValue : 'SIN CATEGORIA';
            $hierarchySubcategory = $especificarValue !== '' ? $especificarValue : 'SIN SUBCATEGORIA';

            if (!isset($hierarchy[$hierarchyCategory])) {
                $hierarchy[$hierarchyCategory] = [
                    'cantidad' => 0,
                    'subcategorias' => [],
                ];
            }

            $hierarchy[$hierarchyCategory]['cantidad']++;

            if (!isset($hierarchy[$hierarchyCategory]['subcategorias'][$hierarchySubcategory])) {
                $hierarchy[$hierarchyCategory]['subcategorias'][$hierarchySubcategory] = 0;
            }
            $hierarchy[$hierarchyCategory]['subcategorias'][$hierarchySubcategory]++;
        }

        arsort($conteoAfiliacion);
        $top = array_slice($conteoAfiliacion, 0, 5, true);

        $topAfiliaciones = [];
        foreach ($top as $afiliacion => $cantidad) {
            $topAfiliaciones[] = [
                'afiliacion' => $afiliacion,
                'cantidad' => (int) $cantidad,
            ];
        }

        $totalRows = count($rows);
        $categoriaShare = [
            'particular' => $totalRows > 0 ? round(($categoriaCounts['particular'] / $totalRows) * 100, 2) : 0.0,
            'privado' => $totalRows > 0 ? round(($categoriaCounts['privado'] / $totalRows) * 100, 2) : 0.0,
        ];
        ksort($monthCounts);
        $trendMonthCounts = array_slice($monthCounts, -12, null, true);

        $trendLabels = [];
        foreach (array_keys($trendMonthCounts) as $monthKey) {
            $trendLabels[] = $this->monthLabel($monthKey);
        }

        $monthKeys = array_keys($monthCounts);
        $currentMonthKey = !empty($monthKeys) ? (string) end($monthKeys) : '';
        $currentMonthCount = $currentMonthKey !== '' ? (int) ($monthCounts[$currentMonthKey] ?? 0) : 0;
        $previousMonthKey = $currentMonthKey !== '' ? date('Y-m', strtotime($currentMonthKey . '-01 -1 month')) : '';
        $previousMonthCount = $previousMonthKey !== '' ? (int) ($monthCounts[$previousMonthKey] ?? 0) : 0;
        $lastYearMonthKey = $currentMonthKey !== '' ? date('Y-m', strtotime($currentMonthKey . '-01 -1 year')) : '';
        $lastYearMonthCount = $lastYearMonthKey !== '' ? (int) ($monthCounts[$lastYearMonthKey] ?? 0) : 0;

        $sortedProcedureCounts = $procedureCounts;
        arsort($sortedProcedureCounts);

        $top3Procedures = array_slice($sortedProcedureCounts, 0, 3, true);
        $top5Procedures = array_slice($sortedProcedureCounts, 0, 5, true);
        $top3ProcedureCount = array_sum($top3Procedures);
        $top5ProcedureCount = array_sum($top5Procedures);

        $pacientesUnicosCount = count($pacienteAtenciones);
        $pacientesNuevos = 0;
        $pacientesRecurrentes = 0;
        foreach ($pacienteAtenciones as $attentions) {
            if ((int) $attentions <= 1) {
                $pacientesNuevos++;
            } else {
                $pacientesRecurrentes++;
            }
        }

        $hourRows = [];
        foreach ($hourCounts as $hour => $count) {
            $hourRows[] = [
                'valor' => sprintf('%02d:00', (int) $hour),
                'cantidad' => (int) $count,
                'porcentaje' => $totalRows > 0 ? round((((int) $count) / $totalRows) * 100, 2) : 0.0,
            ];
        }

        $peakDay = 'LUNES';
        $peakDayCount = 0;
        foreach ($dayCounts as $day => $count) {
            if ((int) $count > $peakDayCount) {
                $peakDay = $day;
                $peakDayCount = (int) $count;
            }
        }

        $peakHour = '00:00';
        $peakHourCount = 0;
        foreach ($hourRows as $item) {
            $count = (int) ($item['cantidad'] ?? 0);
            if ($count > $peakHourCount) {
                $peakHour = (string) ($item['valor'] ?? '00:00');
                $peakHourCount = $count;
            }
        }

        $hierarchyCategories = [];
        $hierarchyPairs = [];

        foreach ($hierarchy as $category => $meta) {
            $categoryTotal = (int) ($meta['cantidad'] ?? 0);
            $children = is_array($meta['subcategorias'] ?? null) ? $meta['subcategorias'] : [];
            arsort($children);

            $childrenPayload = [];
            foreach ($children as $subcategory => $count) {
                $countInt = (int) $count;
                $pctInCategory = $categoryTotal > 0 ? round(($countInt / $categoryTotal) * 100, 2) : 0.0;
                $pctTotal = $totalRows > 0 ? round(($countInt / $totalRows) * 100, 2) : 0.0;

                $childItem = [
                    'subcategoria' => (string) $subcategory,
                    'cantidad' => $countInt,
                    'porcentaje_en_categoria' => $pctInCategory,
                    'porcentaje_total' => $pctTotal,
                ];

                $childrenPayload[] = $childItem;
                $hierarchyPairs[] = [
                    'categoria' => (string) $category,
                    'categoria_total' => $categoryTotal,
                    'subcategoria' => (string) $subcategory,
                    'cantidad' => $countInt,
                    'porcentaje_en_categoria' => $pctInCategory,
                    'porcentaje_total' => $pctTotal,
                ];
            }

            $hierarchyCategories[] = [
                'categoria' => (string) $category,
                'cantidad' => $categoryTotal,
                'porcentaje_total' => $totalRows > 0 ? round(($categoryTotal / $totalRows) * 100, 2) : 0.0,
                'subcategorias' => $childrenPayload,
            ];
        }

        usort($hierarchyCategories, static function (array $a, array $b): int {
            $countCmp = ((int) ($b['cantidad'] ?? 0)) <=> ((int) ($a['cantidad'] ?? 0));
            if ($countCmp !== 0) {
                return $countCmp;
            }

            return strcmp((string) ($a['categoria'] ?? ''), (string) ($b['categoria'] ?? ''));
        });

        usort($hierarchyPairs, static function (array $a, array $b): int {
            $categoryTotalCmp = ((int) ($b['categoria_total'] ?? 0)) <=> ((int) ($a['categoria_total'] ?? 0));
            if ($categoryTotalCmp !== 0) {
                return $categoryTotalCmp;
            }

            $categoryCmp = strcmp((string) ($a['categoria'] ?? ''), (string) ($b['categoria'] ?? ''));
            if ($categoryCmp !== 0) {
                return $categoryCmp;
            }

            $countCmp = ((int) ($b['cantidad'] ?? 0)) <=> ((int) ($a['cantidad'] ?? 0));
            if ($countCmp !== 0) {
                return $countCmp;
            }

            return strcmp((string) ($a['subcategoria'] ?? ''), (string) ($b['subcategoria'] ?? ''));
        });

        $referidoUniquePatientCounts = [];
        foreach ($referidoUniquePatientsByCategory as $category => $patients) {
            $referidoUniquePatientCounts[(string) $category] = count($patients);
        }
        $referidoUniquePatientsWithValueCount = count($referidoUniquePatientsWithValue);
        $referidoUniquePatientsWithoutValueCount = count($referidoUniquePatientsWithoutValue);

        return [
            'total' => $totalRows,
            'total_consultas' => $totalConsultas,
            'total_protocolos' => $totalProtocolos,
            'pacientes_unicos' => count($pacientesUnicos),
            'categoria_counts' => $categoriaCounts,
            'categoria_share' => $categoriaShare,
            'top_afiliaciones' => $topAfiliaciones,
            'referido_prefactura' => [
                'with_value' => $referidoWithValue,
                'without_value' => $referidoWithoutValue,
                'top_values' => $this->metricValues($referidoCounts, 5, $referidoWithValue),
                'values' => $this->metricValues($referidoCounts, null, $referidoWithValue),
            ],
            'referido_prefactura_pacientes_unicos' => [
                'with_value' => $referidoUniquePatientsWithValueCount,
                'without_value' => $referidoUniquePatientsWithoutValueCount,
                'top_values' => $this->metricValues($referidoUniquePatientCounts, 5, $referidoUniquePatientsWithValueCount),
                'values' => $this->metricValues($referidoUniquePatientCounts, null, $referidoUniquePatientsWithValueCount),
            ],
            'referido_prefactura_consulta_nuevo_paciente' => [
                'with_value' => $referidoNewPatientConsultationWithValue,
                'without_value' => $referidoNewPatientConsultationWithoutValue,
                'top_values' => $this->metricValues($referidoNewPatientConsultationCounts, 5, $referidoNewPatientConsultationWithValue),
                'values' => $this->metricValues($referidoNewPatientConsultationCounts, null, $referidoNewPatientConsultationWithValue),
            ],
            'especificar_referido_prefactura' => [
                'with_value' => $especificarWithValue,
                'without_value' => $especificarWithoutValue,
                'top_values' => $this->metricValues($especificarCounts, 5, $especificarWithValue),
                'values' => $this->metricValues($especificarCounts, null, $especificarWithValue),
            ],
            'hierarquia_referidos' => [
                'categorias' => $hierarchyCategories,
                'pares' => $hierarchyPairs,
            ],
            'temporal' => [
                'current_month_label' => $currentMonthKey !== '' ? $this->monthLabel($currentMonthKey) : 'N/D',
                'current_month_count' => $currentMonthCount,
                'previous_month_label' => $previousMonthKey !== '' ? $this->monthLabel($previousMonthKey) : 'N/D',
                'previous_month_count' => $previousMonthCount,
                'same_month_last_year_label' => $lastYearMonthKey !== '' ? $this->monthLabel($lastYearMonthKey) : 'N/D',
                'same_month_last_year_count' => $lastYearMonthCount,
                'vs_previous_pct' => $this->percentageChange($currentMonthCount, $previousMonthCount),
                'vs_same_month_last_year_pct' => $this->percentageChange($currentMonthCount, $lastYearMonthCount),
                'trend' => [
                    'labels' => $trendLabels,
                    'counts' => array_values($trendMonthCounts),
                ],
            ],
            'procedimientos_volumen' => [
                'top_10' => $this->metricValues($sortedProcedureCounts, 10, $totalRows),
                'concentracion' => [
                    'top_3_pct' => $totalRows > 0 ? round(($top3ProcedureCount / $totalRows) * 100, 2) : 0.0,
                    'top_5_pct' => $totalRows > 0 ? round(($top5ProcedureCount / $totalRows) * 100, 2) : 0.0,
                    'top_3_count' => (int) $top3ProcedureCount,
                    'top_5_count' => (int) $top5ProcedureCount,
                ],
            ],
            'desglose_gerencial' => [
                'sedes' => $this->metricValues($sedeCounts, 10, $totalRows),
                'doctores' => $this->metricValues($doctorCounts, 10, $totalRows),
                'afiliaciones' => $this->metricValues($conteoAfiliacion, 10, $totalRows),
                'categorias' => $this->metricValues($categoriaGerencialCounts, null, $totalRows),
            ],
            'picos' => [
                'dias' => $this->metricValues($dayCounts, null, $totalRows),
                'horas' => $hourRows,
                'peak_day' => [
                    'valor' => $peakDay,
                    'cantidad' => $peakDayCount,
                ],
                'peak_hour' => [
                    'valor' => $peakHour,
                    'cantidad' => $peakHourCount,
                ],
            ],
            'pacientes_frecuencia' => [
                'nuevos' => $pacientesNuevos,
                'recurrentes' => $pacientesRecurrentes,
                'nuevos_pct' => $pacientesUnicosCount > 0 ? round(($pacientesNuevos / $pacientesUnicosCount) * 100, 2) : 0.0,
                'recurrentes_pct' => $pacientesUnicosCount > 0 ? round(($pacientesRecurrentes / $pacientesUnicosCount) * 100, 2) : 0.0,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{
     *     meses:array<int, array{value:string,label:string}>,
     *     afiliaciones:array<int, string>,
     *     tipos_atencion:array<int, string>,
     *     sedes:array<int, string>,
     *     categorias:array<int, array{value:string,label:string}>,
     *     categorias_madre_referido:array<int, string>
     * }
     */
    public function catalogos(array $rows): array
    {
        $meses = [];
        $afiliaciones = [];
        $tiposAtencion = [];
        $sedes = [];
        $categorias = [];
        $categoriasMadreReferido = [];

        foreach ($rows as $row) {
            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            if ($timestamp !== false) {
                $value = date('Y-m', $timestamp);
                $meses[$value] = [
                    'value' => $value,
                    'label' => $this->monthLabel($value),
                ];
            }

            $afiliacion = strtolower(trim((string) ($row['afiliacion'] ?? '')));
            if ($afiliacion !== '') {
                $afiliaciones[$afiliacion] = $afiliacion;
            }

            $tipoAtencion = strtoupper(trim((string) ($row['tipo_atencion'] ?? '')));
            if ($tipoAtencion !== '') {
                $tiposAtencion[$tipoAtencion] = $tipoAtencion;
            }

            $categoria = strtolower(trim((string) ($row['categoria_cliente'] ?? '')));
            if ($this->isParticularReportCategory($categoria)) {
                $categorias[$categoria] = $categoria;
            }

            $categoriaMadreReferido = $this->normalizeReferralValue($row['referido_prefactura_por'] ?? null);
            if ($categoriaMadreReferido !== '') {
                $categoriasMadreReferido[$categoriaMadreReferido] = $categoriaMadreReferido;
            }

            $sede = $this->normalizeSedeFilter($row['sede'] ?? null);
            if ($sede !== '') {
                $sedes[$sede] = $sede;
            }
        }

        krsort($meses);
        ksort($afiliaciones);
        ksort($tiposAtencion);
        ksort($categoriasMadreReferido);
        uksort($categorias, static function (string $a, string $b): int {
            $order = ['particular' => 1, 'privado' => 2];
            return ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b);
        });
        if (!isset($sedes['MATRIZ'])) {
            $sedes['MATRIZ'] = 'MATRIZ';
        }
        if (!isset($sedes['CEIBOS'])) {
            $sedes['CEIBOS'] = 'CEIBOS';
        }
        uksort($sedes, static function (string $a, string $b): int {
            $order = ['MATRIZ' => 1, 'CEIBOS' => 2];
            return ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b);
        });

        return [
            'meses' => array_values($meses),
            'afiliaciones' => array_values($afiliaciones),
            'tipos_atencion' => array_values($tiposAtencion),
            'sedes' => array_values($sedes),
            'categorias' => array_map(static function (string $categoria): array {
                return [
                    'value' => $categoria,
                    'label' => ucfirst($categoria),
                ];
            }, array_values($categorias)),
            'categorias_madre_referido' => array_values($categoriasMadreReferido),
        ];
    }

    public function monthLabel(string $yyyyMm): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $yyyyMm, $matches) !== 1) {
            return $yyyyMm;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $label = self::MONTH_LABELS[$month] ?? $yyyyMm;

        return $label . ' ' . $year;
    }

    private function parseDateInput(string $date, bool $endOfDay): ?DateTimeImmutable
    {
        $date = trim($date);
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00');
        if (!($parsed instanceof DateTimeImmutable)) {
            return null;
        }

        return $endOfDay ? $parsed->setTime(23, 59, 59) : $parsed->setTime(0, 0, 0);
    }

    private function parseDateTimestamp(string $date, bool $endOfDay): ?int
    {
        $parsed = $this->parseDateInput($date, $endOfDay);
        return $parsed instanceof DateTimeImmutable ? $parsed->getTimestamp() : null;
    }

    /**
     * @return array{categoria:string,afiliacion_raw:string}|null
     */
    private function resolveMappedAffiliation(string $afiliacion): ?array
    {
        $key = $this->normalizeAffiliationKey($afiliacion);
        if ($key === '') {
            return null;
        }

        $map = $this->afiliacionCategoriaMap();
        if (!isset($map[$key])) {
            return null;
        }

        return $map[$key];
    }

    /**
     * @return array<string, array{categoria:string,afiliacion_raw:string}>
     */
    private function afiliacionCategoriaMap(): array
    {
        if (is_array($this->afiliacionCategoriaMapCache)) {
            return $this->afiliacionCategoriaMapCache;
        }

        if (
            !$this->columnExists('afiliacion_categoria_map', 'afiliacion_norm')
            || !$this->columnExists('afiliacion_categoria_map', 'categoria')
            || !$this->columnExists('afiliacion_categoria_map', 'afiliacion_raw')
        ) {
            $this->afiliacionCategoriaMapCache = [];
            return $this->afiliacionCategoriaMapCache;
        }

        $stmt = $this->db->query(
            "SELECT afiliacion_norm, categoria, afiliacion_raw
             FROM afiliacion_categoria_map
             WHERE TRIM(COALESCE(afiliacion_norm, '')) <> ''"
        );
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $map = [];
        foreach ($rows as $row) {
            $key = $this->normalizeAffiliationKey((string) ($row['afiliacion_norm'] ?? ''));
            if ($key === '') {
                continue;
            }

            $map[$key] = [
                'categoria' => $this->normalizeClientCategory((string) ($row['categoria'] ?? '')),
                'afiliacion_raw' => trim((string) ($row['afiliacion_raw'] ?? '')),
            ];
        }

        $this->afiliacionCategoriaMapCache = $map;

        return $this->afiliacionCategoriaMapCache;
    }

    private function normalizeClientCategory(string $category): string
    {
        return strtolower(trim($category));
    }

    private function isParticularReportCategory(string $category): bool
    {
        return in_array(strtolower(trim($category)), ['particular', 'privado'], true);
    }

    private function normalizeAffiliationKey(string $value): string
    {
        $value = $this->normalizeAffiliationText($value);
        if ($value === '') {
            return '';
        }

        return str_replace([' ', '-'], '_', $value);
    }

    private function normalizeAffiliationText(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function percentageChange(int $current, int $base): ?float
    {
        if ($base <= 0) {
            return null;
        }

        return round((($current - $base) / $base) * 100, 2);
    }

    private function weekdayName(int $isoDay): string
    {
        return match ($isoDay) {
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
            default => 'LUNES',
        };
    }

    private function resolveProcedureVolumeLabel(string $procedimientoProyectado): string
    {
        $raw = trim($procedimientoProyectado);
        if ($raw === '') {
            return 'SIN PROCEDIMIENTO';
        }

        $parts = explode(' - ', $raw);
        $detail = count($parts) > 2 ? trim(implode(' - ', array_slice($parts, 2))) : $raw;
        $detail = preg_replace('/ - (AO|OD|OI|AMBOS OJOS|OJO DERECHO|OJO IZQUIERDO)$/i', '', $detail) ?? $detail;
        $detail = strtoupper(trim($detail));

        return $detail !== '' ? $detail : 'SIN PROCEDIMIENTO';
    }

    private function isNewPatientConsultationProcedure(string $procedureLabel): bool
    {
        $normalized = $this->normalizeAffiliationText($procedureLabel);
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'consulta oftalmologica nuevo paciente');
    }

    private function resolveAttentionType(string $procedimientoProyectado): string
    {
        $raw = trim($procedimientoProyectado);
        if ($raw === '') {
            return 'SIN TIPO';
        }

        $parts = preg_split('/\s*-\s*/', $raw, 2);
        $type = strtoupper(trim((string) ($parts[0] ?? '')));

        return $type !== '' ? $type : 'SIN TIPO';
    }

    private function isExcludedAttentionType(string $type): bool
    {
        $normalized = $this->normalizeAffiliationText($type);
        return in_array($normalized, self::EXCLUDED_ATTENTION_TYPES, true);
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
        if (!empty($parts)) {
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

        $exists = ((int) $stmt->fetchColumn()) > 0;
        $this->columnExistsCache[$key] = $exists;

        return $exists;
    }

    private function encuentroEstadoExpression(string $alias): string
    {
        if ($this->columnExists('procedimiento_proyectado', 'estado_agenda')) {
            return "TRIM(COALESCE({$alias}.estado_agenda, ''))";
        }

        return "''";
    }

    private function attendedEncounterCondition(string $alias): string
    {
        if (!$this->columnExists('procedimiento_proyectado', 'estado_agenda')) {
            return '1=1';
        }

        $estadoExpr = "UPPER(TRIM(COALESCE({$alias}.estado_agenda, '')))";
        return "($estadoExpr LIKE 'ATENDID%' OR $estadoExpr LIKE 'PAGAD%' OR $estadoExpr LIKE 'TERMINAD%')";
    }

    private function isEncounterAttended(string $status): bool
    {
        $status = strtoupper(trim($status));
        if ($status === '') {
            return false;
        }

        return str_starts_with($status, 'ATENDID')
            || str_starts_with($status, 'PAGAD')
            || str_starts_with($status, 'TERMINAD');
    }

    private function referidoPrefacturaExpression(string $alias): string
    {
        $parts = [];
        if ($this->columnExists('procedimiento_proyectado', 'referido_prefactura_por')) {
            $parts[] = "NULLIF(TRIM({$alias}.referido_prefactura_por), '')";
        }
        if ($this->columnExists('procedimiento_proyectado', 'id_procedencia')) {
            $parts[] = "NULLIF(TRIM({$alias}.id_procedencia), '')";
        }

        if (empty($parts)) {
            return "''";
        }

        return 'COALESCE(' . implode(', ', $parts) . ", '')";
    }

    private function especificarReferidoPrefacturaExpression(string $alias): string
    {
        $parts = [];
        if ($this->columnExists('procedimiento_proyectado', 'especificar_referido_prefactura')) {
            $parts[] = "NULLIF(TRIM({$alias}.especificar_referido_prefactura), '')";
        }
        if ($this->columnExists('procedimiento_proyectado', 'especificar_por')) {
            $parts[] = "NULLIF(TRIM({$alias}.especificar_por), '')";
        }
        if ($this->columnExists('procedimiento_proyectado', 'especificarpor')) {
            $parts[] = "NULLIF(TRIM({$alias}.especificarpor), '')";
        }

        if (empty($parts)) {
            return "''";
        }

        return 'COALESCE(' . implode(', ', $parts) . ", '')";
    }

    private function normalizeReferralValue(mixed $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
        if ($normalized === '') {
            return '';
        }

        $upper = strtoupper($normalized);
        $emptyTokens = ['(NO DEFINIDO)', 'NO DEFINIDO', 'N/A', 'NA', 'NULL', '-', '—'];
        if (in_array($upper, $emptyTokens, true)) {
            return '';
        }

        return $upper;
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{valor:string,cantidad:int,porcentaje:float}>
     */
    private function metricValues(array $counts, ?int $limit = null, ?int $totalForShare = null): array
    {
        if (empty($counts)) {
            return [];
        }

        arsort($counts);
        if ($limit !== null && $limit > 0) {
            $counts = array_slice($counts, 0, $limit, true);
        }

        $total = $totalForShare ?? array_sum($counts);
        if ($total < 1) {
            $total = 0;
        }

        $result = [];
        foreach ($counts as $valor => $cantidad) {
            $result[] = [
                'valor' => (string) $valor,
                'cantidad' => (int) $cantidad,
                'porcentaje' => $total > 0 ? round((((int) $cantidad) / $total) * 100, 2) : 0.0,
            ];
        }

        return $result;
    }
}
