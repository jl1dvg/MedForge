<?php

namespace App\Modules\Billing\Services;

use App\Models\Tarifario2014;
use App\Modules\Codes\Services\CodePriceService;
use DateTimeImmutable;
use PDO;
use Throwable;

class BillingParticularesReportService
{
    private PDO $db;
    /** @var array<string, bool> */
    private array $columnExistsCache = [];
    /** @var array<string, float> */
    private array $tarifaLookupCache = [];
    /** @var array<string, array{id:int,codigo:string,descripcion:string}> */
    private array $tarifaCodeCache = [];
    /** @var array<string, array{categoria:string,afiliacion_raw:string}>|null */
    private ?array $afiliacionCategoriaMapCache = null;
    /** @var array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}>|null */
    private ?array $codePriceLevelsCache = null;
    private ?CodePriceService $codePriceService = null;
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
        $economicsJoin = $this->economicsJoinDefinition();

        $sql = <<<SQL
            SELECT
                atenciones.hc_number,
                atenciones.nombre_completo,
                atenciones.tipo,
                atenciones.fuente_atencion,
                atenciones.form_id,
                atenciones.fecha,
                atenciones.afiliacion,
                atenciones.sede,
                atenciones.estado_encuentro,
                atenciones.procedimiento_proyectado,
                atenciones.doctor,
                atenciones.referido_prefactura_por,
                atenciones.especificar_referido_prefactura,
                atenciones.protocolo_id,
                atenciones.protocolo_status_ok,
                atenciones.protocolo_firmado,
                atenciones.fecha_firma,
                atenciones.protocolo_firmado_por,
                atenciones.consulta_fecha,
                atenciones.consulta_diagnosticos,
                econ.billing_id,
                econ.fecha_facturacion,
                econ.fecha_atencion,
                COALESCE(econ.total_produccion, 0) AS total_produccion,
                COALESCE(econ.monto_honorario_real, 0) AS monto_honorario_real,
                COALESCE(econ.monto_facturado_real, 0) AS monto_facturado_real,
                COALESCE(econ.procedimientos_facturados, 0) AS procedimientos_facturados,
                econ.formas_pago,
                econ.numero_factura,
                econ.factura_id,
                econ.cliente_facturacion,
                econ.area_facturacion,
                econ.estado_facturacion_raw
            FROM (
                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'consulta' AS tipo,
                    'consulta' AS fuente_atencion,
                    cd.form_id,
                    cd.fecha AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura,
                    NULL AS protocolo_id,
                    0 AS protocolo_status_ok,
                    0 AS protocolo_firmado,
                    NULL AS fecha_firma,
                    NULL AS protocolo_firmado_por,
                    cd.fecha AS consulta_fecha,
                    NULLIF(TRIM(COALESCE(cd.diagnosticos, '')), '') AS consulta_diagnosticos
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
                    'protocolo' AS fuente_atencion,
                    pd.form_id,
                    pd.fecha_inicio AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura,
                    pd.procedimiento_id AS protocolo_id,
                    CASE WHEN COALESCE(pd.status, 0) = 1 THEN 1 ELSE 0 END AS protocolo_status_ok,
                    CASE
                        WHEN NULLIF(TRIM(COALESCE(pd.fecha_firma, '')), '') IS NOT NULL
                          OR NULLIF(TRIM(COALESCE(pd.protocolo_firmado_por, '')), '') IS NOT NULL
                        THEN 1 ELSE 0
                    END AS protocolo_firmado,
                    pd.fecha_firma,
                    pd.protocolo_firmado_por,
                    NULL AS consulta_fecha,
                    NULL AS consulta_diagnosticos
                FROM patient_data p
                INNER JOIN protocolo_data pd ON pd.hc_number = p.hc_number
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = pd.form_id
                WHERE pd.fecha_inicio BETWEEN ? AND ?
                  AND (%ATENDIDO_WHERE% OR %SURGERY_WHERE%)

                UNION ALL

                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'agenda_cirugia' AS tipo,
                    'agenda_cirugia' AS fuente_atencion,
                    pp.form_id,
                    pp.fecha AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura,
                    NULL AS protocolo_id,
                    0 AS protocolo_status_ok,
                    0 AS protocolo_firmado,
                    NULL AS fecha_firma,
                    NULL AS protocolo_firmado_por,
                    NULL AS consulta_fecha,
                    NULL AS consulta_diagnosticos
                FROM patient_data p
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number
                LEFT JOIN protocolo_data pd ON pd.hc_number = p.hc_number AND pd.form_id = pp.form_id
                WHERE pp.fecha BETWEEN ? AND ?
                  AND %SURGERY_WHERE%
                  AND pd.form_id IS NULL

                UNION ALL

                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'agenda_pni' AS tipo,
                    'agenda_pni' AS fuente_atencion,
                    pp.form_id,
                    pp.fecha AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura,
                    NULL AS protocolo_id,
                    0 AS protocolo_status_ok,
                    0 AS protocolo_firmado,
                    NULL AS fecha_firma,
                    NULL AS protocolo_firmado_por,
                    cd.fecha AS consulta_fecha,
                    NULLIF(TRIM(COALESCE(cd.diagnosticos, '')), '') AS consulta_diagnosticos
                FROM patient_data p
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number
                LEFT JOIN consulta_data cd ON cd.hc_number = p.hc_number AND cd.form_id = pp.form_id
                WHERE pp.fecha BETWEEN ? AND ?
                  AND %PNI_WHERE%
                  AND NOT (
                    cd.form_id IS NOT NULL
                    AND %ATENDIDO_WHERE%
                  )

                UNION ALL

                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'agenda_servicio_oftalmo' AS tipo,
                    'agenda_servicio_oftalmo' AS fuente_atencion,
                    pp.form_id,
                    pp.fecha AS fecha,
                    p.afiliacion,
                    %SEDE_EXPR% AS sede,
                    %ESTADO_EXPR% AS estado_encuentro,
                    pp.procedimiento_proyectado,
                    pp.doctor,
                    %REFERIDO_PREFACTURA_EXPR% AS referido_prefactura_por,
                    %ESPECIFICAR_REFERIDO_EXPR% AS especificar_referido_prefactura,
                    NULL AS protocolo_id,
                    0 AS protocolo_status_ok,
                    0 AS protocolo_firmado,
                    NULL AS fecha_firma,
                    NULL AS protocolo_firmado_por,
                    cd.fecha AS consulta_fecha,
                    NULLIF(TRIM(COALESCE(cd.diagnosticos, '')), '') AS consulta_diagnosticos
                FROM patient_data p
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number
                LEFT JOIN consulta_data cd ON cd.hc_number = p.hc_number AND cd.form_id = pp.form_id
                WHERE pp.fecha BETWEEN ? AND ?
                  AND %SERVICIO_OFTALMO_WHERE%
                  AND NOT (
                    cd.form_id IS NOT NULL
                    AND %ATENDIDO_WHERE%
                  )
            ) AS atenciones
            %ECON_JOIN_SQL%
            WHERE atenciones.fecha IS NOT NULL
              AND atenciones.fecha NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')
            ORDER BY atenciones.fecha DESC, atenciones.form_id DESC
        SQL;
        $sql = str_replace('%SEDE_EXPR%', $sedeExpr, $sql);
        $sql = str_replace('%ESTADO_EXPR%', $estadoExpr, $sql);
        $sql = str_replace('%ATENDIDO_WHERE%', $atendidoCondition, $sql);
        $sql = str_replace('%SURGERY_WHERE%', $this->surgeryAttentionCondition('pp'), $sql);
        $sql = str_replace('%PNI_WHERE%', $this->pniAttentionCondition('pp'), $sql);
        $sql = str_replace('%SERVICIO_OFTALMO_WHERE%', $this->ophthalmologyServiceAttentionCondition('pp'), $sql);
        $sql = str_replace('%REFERIDO_PREFACTURA_EXPR%', $referidoPrefacturaExpr, $sql);
        $sql = str_replace('%ESPECIFICAR_REFERIDO_EXPR%', $especificarReferidoExpr, $sql);
        $sql = str_replace('%ECON_JOIN_SQL%', $economicsJoin, $sql);

        $params = [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin];

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
            if (
                $this->isOphthalmologyServiceAttentionType($tipoAtencion)
                && !$this->isAllowedOphthalmologyServiceProcedure((string) ($row['procedimiento_proyectado'] ?? ''))
            ) {
                continue;
            }

            $row['tipo_atencion'] = $tipoAtencion;
            $row['monto_honorario_real'] = round((float) ($row['monto_honorario_real'] ?? 0), 2);
            $row['monto_facturado_real'] = round((float) ($row['monto_facturado_real'] ?? 0), 2);
            $row['total_produccion'] = round((float) ($row['total_produccion'] ?? 0), 2);
            $row['procedimientos_facturados'] = (int) ($row['procedimientos_facturados'] ?? 0);
            $billingId = trim((string) ($row['billing_id'] ?? ''));
            $facturaId = trim((string) ($row['factura_id'] ?? ''));
            $numeroFactura = trim((string) ($row['numero_factura'] ?? ''));
            $hasBillingEvidence = $this->hasBillingEvidence($row);
            $row['facturado'] = $hasBillingEvidence;
            $row['estado_realizacion'] = 'ATENDIDA';
            $row['estado_facturacion_operativa'] = $hasBillingEvidence ? 'FACTURADA' : 'SIN_FACTURACION';
            $row['alerta_revision'] = null;
            $row['tarifa_codigo'] = '';
            $row['tarifa_detalle'] = '';
            $row['monto_estimado_tarifario'] = 0.0;
            $row['monto_por_cobrar_estimado'] = 0.0;
            $row['monto_perdida_estimada'] = 0.0;
            $row['sin_tarifa_estimable'] = false;
            $row['cirugia_realizada'] = false;
            $row['cirugia_perdida'] = false;
            $row['pni_realizada'] = false;
            $row['pni_perdida'] = false;
            $row['servicio_oftalmologico_realizada'] = false;
            $row['servicio_oftalmologico_perdida'] = false;

            if ($this->isPniAttentionType($tipoAtencion)) {
                [$codigoTarifario, $detalleTarifario] = $this->parseProcedureCodeDetail((string) ($row['procedimiento_proyectado'] ?? ''));
                $montoTarifario = $codigoTarifario !== '' ? $this->lookupTarifa($codigoTarifario, $row) : 0.0;
                $estadoRealizacion = $this->resolvePniRealizationState($row, $hasBillingEvidence);
                $estadoFacturacion = $this->resolvePniBillingState($estadoRealizacion);
                $alertaRevision = $this->resolvePniReviewAlert($row, $estadoRealizacion, $hasBillingEvidence);

                $row['estado_realizacion'] = $estadoRealizacion;
                $row['estado_facturacion_operativa'] = $estadoFacturacion;
                $row['alerta_revision'] = $alertaRevision;
                $row['tarifa_codigo'] = $codigoTarifario;
                $row['tarifa_detalle'] = $detalleTarifario;
                $row['monto_estimado_tarifario'] = round($montoTarifario, 2);
                $row['pni_realizada'] = in_array($estadoRealizacion, ['FACTURADA', 'REALIZADA_CONSULTA'], true);
                $row['pni_perdida'] = in_array($estadoRealizacion, ['CANCELADA', 'AUSENTE'], true);

                if ($estadoFacturacion === 'PENDIENTE_FACTURAR') {
                    $row['monto_por_cobrar_estimado'] = round($montoTarifario, 2);
                }
                if ($row['pni_perdida']) {
                    $row['monto_perdida_estimada'] = round($montoTarifario, 2);
                }
                if (
                    $montoTarifario <= 0
                    && (
                        $row['monto_por_cobrar_estimado'] > 0
                        || $row['monto_perdida_estimada'] > 0
                        || $estadoFacturacion === 'PENDIENTE_FACTURAR'
                        || $row['pni_perdida']
                    )
                ) {
                    $row['sin_tarifa_estimable'] = true;
                }
            }

            if ($this->isOphthalmologyServiceAttentionType($tipoAtencion)) {
                [$codigoTarifario, $detalleTarifario] = $this->parseProcedureCodeDetail((string) ($row['procedimiento_proyectado'] ?? ''));
                $montoTarifario = $codigoTarifario !== '' ? $this->lookupTarifa($codigoTarifario, $row) : 0.0;
                $estadoRealizacion = $this->resolveOphthalmologyServiceRealizationState($row, $hasBillingEvidence);
                $estadoFacturacion = $this->resolveOphthalmologyServiceBillingState($estadoRealizacion);
                $alertaRevision = $this->resolveOphthalmologyServiceReviewAlert($row, $estadoRealizacion, $hasBillingEvidence);

                $row['estado_realizacion'] = $estadoRealizacion;
                $row['estado_facturacion_operativa'] = $estadoFacturacion;
                $row['alerta_revision'] = $alertaRevision;
                $row['tarifa_codigo'] = $codigoTarifario;
                $row['tarifa_detalle'] = $detalleTarifario;
                $row['monto_estimado_tarifario'] = round($montoTarifario, 2);
                $row['servicio_oftalmologico_realizada'] = in_array($estadoRealizacion, ['FACTURADA', 'REALIZADA_CONSULTA'], true);
                $row['servicio_oftalmologico_perdida'] = in_array($estadoRealizacion, ['CANCELADA', 'AUSENTE'], true);

                if ($estadoFacturacion === 'PENDIENTE_FACTURAR') {
                    $row['monto_por_cobrar_estimado'] = round($montoTarifario, 2);
                }
                if ($row['servicio_oftalmologico_perdida']) {
                    $row['monto_perdida_estimada'] = round($montoTarifario, 2);
                }
                if (
                    $montoTarifario <= 0
                    && (
                        $row['monto_por_cobrar_estimado'] > 0
                        || $row['monto_perdida_estimada'] > 0
                        || $estadoFacturacion === 'PENDIENTE_FACTURAR'
                        || $row['servicio_oftalmologico_perdida']
                    )
                ) {
                    $row['sin_tarifa_estimable'] = true;
                }
            }

            if ($this->isSurgeryAttentionType($tipoAtencion)) {
                [$codigoTarifario, $detalleTarifario] = $this->parseProcedureCodeDetail((string) ($row['procedimiento_proyectado'] ?? ''));
                $montoTarifario = $codigoTarifario !== '' ? $this->lookupTarifa($codigoTarifario, $row) : 0.0;
                $estadoRealizacion = $this->resolveSurgeryRealizationState($row, $hasBillingEvidence);
                $estadoFacturacion = $this->resolveSurgeryBillingState($estadoRealizacion, $hasBillingEvidence);
                $alertaRevision = $this->resolveSurgeryReviewAlert($row, $estadoRealizacion, $estadoFacturacion);

                $row['estado_realizacion'] = $estadoRealizacion;
                $row['estado_facturacion_operativa'] = $estadoFacturacion;
                $row['alerta_revision'] = $alertaRevision;
                $row['tarifa_codigo'] = $codigoTarifario;
                $row['tarifa_detalle'] = $detalleTarifario;
                $row['monto_estimado_tarifario'] = round($montoTarifario, 2);
                $row['cirugia_realizada'] = in_array($estadoRealizacion, ['OPERADA_CONFIRMADA', 'OPERADA_CON_PROTOCOLO', 'OPERADA_OTRO_CENTRO'], true);
                $row['cirugia_perdida'] = in_array($estadoRealizacion, ['CANCELADA', 'SIN_CIERRE_OPERATIVO'], true);

                if ($estadoFacturacion === 'PENDIENTE_FACTURAR') {
                    $row['monto_por_cobrar_estimado'] = round($montoTarifario, 2);
                }
                if ($row['cirugia_perdida']) {
                    $row['monto_perdida_estimada'] = round($montoTarifario, 2);
                }
                if (
                    $montoTarifario <= 0
                    && ($row['monto_por_cobrar_estimado'] > 0 || $row['monto_perdida_estimada'] > 0
                        || in_array($estadoFacturacion, ['PENDIENTE_FACTURAR'], true)
                        || $row['cirugia_perdida'])
                ) {
                    $row['sin_tarifa_estimable'] = true;
                }
            }
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
            if (!$this->shouldIncludeRowForReport($row)) {
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
     *     economico:array{
     *         total_produccion:float,
     *         ticket_promedio_facturado:float,
     *         produccion_promedio_por_atencion:float,
     *         atenciones_facturadas:int,
     *         atenciones_no_facturadas:int,
     *         facturacion_rate:float,
     *         procedimientos_facturados:int,
     *         produccion_por_categoria:array{particular:float,privado:float},
     *         trend:array{labels:array<int,string>,totals:array<int,float>}
     *     },
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
        $produccionTotal = 0.0;
        $honorarioRealTotal = 0.0;
        $atencionesFacturadas = 0;
        $atencionesConHonorario = 0;
        $procedimientosFacturados = 0;
        $produccionPorCategoria = [
            'particular' => 0.0,
            'privado' => 0.0,
        ];
        $totalConsultas = 0;
        $totalProtocolos = 0;
        $categoriaCounts = [
            'particular' => 0,
            'privado' => 0,
        ];
        $monthCounts = [];
        $monthHonorarios = [];
        $procedureCounts = [];
        $sedeCounts = [];
        $doctorCounts = [];
        $doctorHonorario = [];
        $pniEstadoCounts = [
            'FACTURADA' => 0,
            'REALIZADA_CONSULTA' => 0,
            'CANCELADA' => 0,
            'AUSENTE' => 0,
        ];
        $pniPorCobrarDoctor = [];
        $pniPerdidaDoctor = [];
        $pniHonorarioReal = 0.0;
        $pniPorCobrarEstimado = 0.0;
        $pniPerdidaEstimada = 0.0;
        $pniPendientesFacturar = 0;
        $pniFacturadas = 0;
        $pniSinTarifaEstimable = 0;
        $serviciosOftalmologicosEstadoCounts = [
            'FACTURADA' => 0,
            'REALIZADA_CONSULTA' => 0,
            'CANCELADA' => 0,
            'AUSENTE' => 0,
        ];
        $serviciosOftalmologicosPorCobrarDoctor = [];
        $serviciosOftalmologicosPerdidaDoctor = [];
        $serviciosOftalmologicosHonorarioReal = 0.0;
        $serviciosOftalmologicosPorCobrarEstimado = 0.0;
        $serviciosOftalmologicosPerdidaEstimada = 0.0;
        $serviciosOftalmologicosPendientesFacturar = 0;
        $serviciosOftalmologicosFacturadas = 0;
        $serviciosOftalmologicosSinTarifaEstimable = 0;
        $cirugiasEstadoCounts = [
            'OPERADA_CONFIRMADA' => 0,
            'OPERADA_CON_PROTOCOLO' => 0,
            'OPERADA_OTRO_CENTRO' => 0,
            'CANCELADA' => 0,
            'SIN_CIERRE_OPERATIVO' => 0,
        ];
        $cirugiasPorCobrarDoctor = [];
        $cirugiasPerdidaDoctor = [];
        $cirugiasHonorarioReal = 0.0;
        $cirugiasPorCobrarEstimado = 0.0;
        $cirugiasPerdidaEstimada = 0.0;
        $cirugiasPendientesFacturar = 0;
        $cirugiasFacturadasLocales = 0;
        $cirugiasFacturadasExternas = 0;
        $cirugiasSinTarifaEstimable = 0;
        $categoriaGerencialCounts = [];
        $formasPagoCounts = [];
        $clienteHonorario = [];
        $areaHonorario = [];
        $facturasEmitidas = [];
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

            $produccionRow = (float) ($row['total_produccion'] ?? 0);
            $honorarioRealRow = (float) ($row['monto_honorario_real'] ?? 0);
            $produccionBaseRow = $honorarioRealRow > 0 ? $honorarioRealRow : $produccionRow;
            $produccionTotal += $produccionBaseRow;
            $honorarioRealTotal += $produccionBaseRow;
            $procedimientosFacturados += (int) ($row['procedimientos_facturados'] ?? 0);
            $facturadoRow = (bool) ($row['facturado'] ?? false);
            if ($facturadoRow) {
                $atencionesFacturadas++;
            }
            if ($produccionBaseRow > 0) {
                $atencionesConHonorario++;
            }

            $categoria = strtolower(trim((string) ($row['categoria_cliente'] ?? '')));
            if ($this->isParticularReportCategory($categoria)) {
                $categoriaCounts[$categoria] = (int) ($categoriaCounts[$categoria] ?? 0) + 1;
                $produccionPorCategoria[$categoria] = (float) ($produccionPorCategoria[$categoria] ?? 0) + $produccionBaseRow;
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

            if ($this->isPniAttentionType((string) ($row['tipo_atencion'] ?? ''))) {
                $estadoRealizacionPni = strtoupper(trim((string) ($row['estado_realizacion'] ?? '')));
                if ($estadoRealizacionPni === '') {
                    $estadoRealizacionPni = 'AUSENTE';
                }
                if (!isset($pniEstadoCounts[$estadoRealizacionPni])) {
                    $pniEstadoCounts[$estadoRealizacionPni] = 0;
                }
                $pniEstadoCounts[$estadoRealizacionPni]++;

                $estadoFacturacionPni = strtoupper(trim((string) ($row['estado_facturacion_operativa'] ?? '')));
                $montoPorCobrarPniRow = round((float) ($row['monto_por_cobrar_estimado'] ?? 0), 2);
                $montoPerdidaPniRow = round((float) ($row['monto_perdida_estimada'] ?? 0), 2);
                $sinTarifaEstimablePni = (bool) ($row['sin_tarifa_estimable'] ?? false);

                if ($estadoRealizacionPni === 'FACTURADA') {
                    $pniHonorarioReal += $produccionBaseRow;
                    $pniFacturadas++;
                }

                if ($estadoFacturacionPni === 'PENDIENTE_FACTURAR') {
                    $pniPendientesFacturar++;
                    $pniPorCobrarEstimado += $montoPorCobrarPniRow;
                    $pniPorCobrarDoctor[$doctor] = ($pniPorCobrarDoctor[$doctor] ?? 0.0) + $montoPorCobrarPniRow;
                }

                if (in_array($estadoRealizacionPni, ['CANCELADA', 'AUSENTE'], true)) {
                    $pniPerdidaEstimada += $montoPerdidaPniRow;
                    $pniPerdidaDoctor[$doctor] = ($pniPerdidaDoctor[$doctor] ?? 0.0) + $montoPerdidaPniRow;
                }

                if ($sinTarifaEstimablePni) {
                    $pniSinTarifaEstimable++;
                }
            }

            if ($this->isOphthalmologyServiceAttentionType((string) ($row['tipo_atencion'] ?? ''))) {
                $estadoRealizacionServicio = strtoupper(trim((string) ($row['estado_realizacion'] ?? '')));
                if ($estadoRealizacionServicio === '') {
                    $estadoRealizacionServicio = 'AUSENTE';
                }
                if (!isset($serviciosOftalmologicosEstadoCounts[$estadoRealizacionServicio])) {
                    $serviciosOftalmologicosEstadoCounts[$estadoRealizacionServicio] = 0;
                }
                $serviciosOftalmologicosEstadoCounts[$estadoRealizacionServicio]++;

                $estadoFacturacionServicio = strtoupper(trim((string) ($row['estado_facturacion_operativa'] ?? '')));
                $montoPorCobrarServicioRow = round((float) ($row['monto_por_cobrar_estimado'] ?? 0), 2);
                $montoPerdidaServicioRow = round((float) ($row['monto_perdida_estimada'] ?? 0), 2);
                $sinTarifaEstimableServicio = (bool) ($row['sin_tarifa_estimable'] ?? false);

                if ($estadoRealizacionServicio === 'FACTURADA') {
                    $serviciosOftalmologicosHonorarioReal += $produccionBaseRow;
                    $serviciosOftalmologicosFacturadas++;
                }

                if ($estadoFacturacionServicio === 'PENDIENTE_FACTURAR') {
                    $serviciosOftalmologicosPendientesFacturar++;
                    $serviciosOftalmologicosPorCobrarEstimado += $montoPorCobrarServicioRow;
                    $serviciosOftalmologicosPorCobrarDoctor[$doctor] = ($serviciosOftalmologicosPorCobrarDoctor[$doctor] ?? 0.0) + $montoPorCobrarServicioRow;
                }

                if (in_array($estadoRealizacionServicio, ['CANCELADA', 'AUSENTE'], true)) {
                    $serviciosOftalmologicosPerdidaEstimada += $montoPerdidaServicioRow;
                    $serviciosOftalmologicosPerdidaDoctor[$doctor] = ($serviciosOftalmologicosPerdidaDoctor[$doctor] ?? 0.0) + $montoPerdidaServicioRow;
                }

                if ($sinTarifaEstimableServicio) {
                    $serviciosOftalmologicosSinTarifaEstimable++;
                }
            }

            if ($this->isSurgeryAttentionType((string) ($row['tipo_atencion'] ?? ''))) {
                $estadoRealizacion = strtoupper(trim((string) ($row['estado_realizacion'] ?? '')));
                if ($estadoRealizacion === '') {
                    $estadoRealizacion = 'SIN_CIERRE_OPERATIVO';
                }
                if (!isset($cirugiasEstadoCounts[$estadoRealizacion])) {
                    $cirugiasEstadoCounts[$estadoRealizacion] = 0;
                }
                $cirugiasEstadoCounts[$estadoRealizacion]++;

                $estadoFacturacionCirugia = strtoupper(trim((string) ($row['estado_facturacion_operativa'] ?? '')));
                $montoPorCobrarRow = round((float) ($row['monto_por_cobrar_estimado'] ?? 0), 2);
                $montoPerdidaRow = round((float) ($row['monto_perdida_estimada'] ?? 0), 2);
                $sinTarifaEstimable = (bool) ($row['sin_tarifa_estimable'] ?? false);

                if ($estadoRealizacion === 'OPERADA_CONFIRMADA' || $estadoRealizacion === 'OPERADA_CON_PROTOCOLO') {
                    $cirugiasHonorarioReal += $produccionBaseRow;
                } elseif ($estadoRealizacion === 'OPERADA_OTRO_CENTRO') {
                    $cirugiasHonorarioReal += $produccionBaseRow;
                }

                if ($estadoFacturacionCirugia === 'PENDIENTE_FACTURAR') {
                    $cirugiasPendientesFacturar++;
                    $cirugiasPorCobrarEstimado += $montoPorCobrarRow;
                    $cirugiasPorCobrarDoctor[$doctor] = ($cirugiasPorCobrarDoctor[$doctor] ?? 0.0) + $montoPorCobrarRow;
                } elseif ($estadoFacturacionCirugia === 'FACTURADA') {
                    $cirugiasFacturadasLocales++;
                } elseif ($estadoFacturacionCirugia === 'FACTURADA_EXTERNA') {
                    $cirugiasFacturadasExternas++;
                }

                if (in_array($estadoRealizacion, ['CANCELADA', 'SIN_CIERRE_OPERATIVO'], true)) {
                    $cirugiasPerdidaEstimada += $montoPerdidaRow;
                    $cirugiasPerdidaDoctor[$doctor] = ($cirugiasPerdidaDoctor[$doctor] ?? 0.0) + $montoPerdidaRow;
                }

                if ($sinTarifaEstimable) {
                    $cirugiasSinTarifaEstimable++;
                }
            }

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

            $economicoTimestamp = strtotime((string) ($row['fecha_facturacion'] ?? ''));
            if ($economicoTimestamp === false) {
                $economicoTimestamp = $timestamp;
            }
            if ($economicoTimestamp !== false) {
                $economicoMonthKey = date('Y-m', $economicoTimestamp);
                if (!isset($monthHonorarios[$economicoMonthKey])) {
                    $monthHonorarios[$economicoMonthKey] = 0.0;
                }
                $monthHonorarios[$economicoMonthKey] += $produccionBaseRow;
            }

            if ($facturadoRow || $produccionBaseRow > 0) {
                $formasPagoRaw = trim((string) ($row['formas_pago'] ?? ''));
                foreach ($this->explodePipeValues($formasPagoRaw) as $formaPago) {
                    if (!isset($formasPagoCounts[$formaPago])) {
                        $formasPagoCounts[$formaPago] = 0;
                    }
                    $formasPagoCounts[$formaPago]++;
                }

                $cliente = strtoupper(trim((string) ($row['cliente_facturacion'] ?? '')));
                if ($cliente === '') {
                    $cliente = 'SIN CLIENTE';
                }
                if (!isset($clienteHonorario[$cliente])) {
                    $clienteHonorario[$cliente] = 0.0;
                }
                $clienteHonorario[$cliente] += $produccionBaseRow;

                $area = strtoupper(trim((string) ($row['area_facturacion'] ?? '')));
                if ($area === '') {
                    $area = 'SIN AREA';
                }
                if (!isset($areaHonorario[$area])) {
                    $areaHonorario[$area] = 0.0;
                }
                $areaHonorario[$area] += $produccionBaseRow;

                if (!isset($doctorHonorario[$doctor])) {
                    $doctorHonorario[$doctor] = 0.0;
                }
                $doctorHonorario[$doctor] += $produccionBaseRow;

                $facturaKey = trim((string) ($row['factura_id'] ?? ''));
                if ($facturaKey === '') {
                    $facturaKey = trim((string) ($row['numero_factura'] ?? ''));
                }
                if ($facturaKey !== '') {
                    $facturasEmitidas[$facturaKey] = true;
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
        $atencionesNoFacturadas = max($totalRows - $atencionesFacturadas, 0);
        $categoriaShare = [
            'particular' => $totalRows > 0 ? round(($categoriaCounts['particular'] / $totalRows) * 100, 2) : 0.0,
            'privado' => $totalRows > 0 ? round(($categoriaCounts['privado'] / $totalRows) * 100, 2) : 0.0,
        ];
        ksort($monthCounts);
        ksort($monthHonorarios);
        $trendMonthCounts = array_slice($monthCounts, -12, null, true);

        $trendLabels = [];
        foreach (array_keys($trendMonthCounts) as $monthKey) {
            $trendLabels[] = $this->monthLabel($monthKey);
        }

        $economicMonthKeys = array_values(array_unique(array_keys($monthHonorarios)));
        sort($economicMonthKeys);
        $economicMonthKeys = array_slice($economicMonthKeys, -12);
        $economicTrendLabels = array_map(fn(string $monthKey): string => $this->monthLabel($monthKey), $economicMonthKeys);
        $economicTrendHonorarios = [];
        foreach ($economicMonthKeys as $monthKey) {
            $economicTrendHonorarios[] = round((float) ($monthHonorarios[$monthKey] ?? 0), 2);
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
        $pniRealizadas = (int) ($pniEstadoCounts['FACTURADA'] ?? 0)
            + (int) ($pniEstadoCounts['REALIZADA_CONSULTA'] ?? 0);
        $pniCanceladas = (int) ($pniEstadoCounts['CANCELADA'] ?? 0);
        $pniAusentes = (int) ($pniEstadoCounts['AUSENTE'] ?? 0);
        $pniTotal = array_sum($pniEstadoCounts);
        $serviciosOftalmologicosRealizadas = (int) ($serviciosOftalmologicosEstadoCounts['FACTURADA'] ?? 0)
            + (int) ($serviciosOftalmologicosEstadoCounts['REALIZADA_CONSULTA'] ?? 0);
        $serviciosOftalmologicosCanceladas = (int) ($serviciosOftalmologicosEstadoCounts['CANCELADA'] ?? 0);
        $serviciosOftalmologicosAusentes = (int) ($serviciosOftalmologicosEstadoCounts['AUSENTE'] ?? 0);
        $serviciosOftalmologicosTotal = array_sum($serviciosOftalmologicosEstadoCounts);
        $cirugiasRealizadas = (int) ($cirugiasEstadoCounts['OPERADA_CONFIRMADA'] ?? 0)
            + (int) ($cirugiasEstadoCounts['OPERADA_CON_PROTOCOLO'] ?? 0)
            + (int) ($cirugiasEstadoCounts['OPERADA_OTRO_CENTRO'] ?? 0);
        $cirugiasCanceladas = (int) ($cirugiasEstadoCounts['CANCELADA'] ?? 0);
        $cirugiasSinCierre = (int) ($cirugiasEstadoCounts['SIN_CIERRE_OPERATIVO'] ?? 0);
        $cirugiasTotal = array_sum($cirugiasEstadoCounts);

        return [
            'total' => $totalRows,
            'total_consultas' => $totalConsultas,
            'total_protocolos' => $totalProtocolos,
            'economico' => [
                'total_produccion' => round($produccionTotal, 2),
                'total_honorario_real' => round($honorarioRealTotal, 2),
                'ticket_promedio_honorario' => $atencionesConHonorario > 0 ? round($honorarioRealTotal / $atencionesConHonorario, 2) : 0.0,
                'produccion_promedio_por_atencion' => $totalRows > 0 ? round($honorarioRealTotal / $totalRows, 2) : 0.0,
                'atenciones_facturadas' => $atencionesFacturadas,
                'atenciones_con_honorario' => $atencionesConHonorario,
                'atenciones_no_facturadas' => $atencionesNoFacturadas,
                'facturacion_rate' => $totalRows > 0 ? round(($atencionesFacturadas / $totalRows) * 100, 2) : 0.0,
                'cobertura_honorario_rate' => $totalRows > 0 ? round(($atencionesConHonorario / $totalRows) * 100, 2) : 0.0,
                'procedimientos_facturados' => $procedimientosFacturados,
                'facturas_emitidas' => count($facturasEmitidas),
                'produccion_por_categoria' => [
                    'particular' => round((float) ($produccionPorCategoria['particular'] ?? 0), 2),
                    'privado' => round((float) ($produccionPorCategoria['privado'] ?? 0), 2),
                ],
                'honorario_por_categoria' => [
                    'particular' => round((float) ($produccionPorCategoria['particular'] ?? 0), 2),
                    'privado' => round((float) ($produccionPorCategoria['privado'] ?? 0), 2),
                ],
                'formas_pago' => [
                    'values' => $this->metricValues($formasPagoCounts, 8, $atencionesFacturadas),
                ],
                'doctores_top' => $this->moneyMetricValues($doctorHonorario, 10, $honorarioRealTotal),
                'clientes_top' => $this->moneyMetricValues($clienteHonorario, 8, $honorarioRealTotal),
                'areas_top' => $this->moneyMetricValues($areaHonorario, 8, $honorarioRealTotal),
                'trend' => [
                    'labels' => $economicTrendLabels,
                    'totals' => $economicTrendHonorarios,
                    'honorarios' => $economicTrendHonorarios,
                ],
            ],
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
            'pni' => [
                'total' => $pniTotal,
                'realizadas' => $pniRealizadas,
                'facturadas' => (int) ($pniEstadoCounts['FACTURADA'] ?? 0),
                'realizada_consulta' => (int) ($pniEstadoCounts['REALIZADA_CONSULTA'] ?? 0),
                'canceladas' => $pniCanceladas,
                'ausentes' => $pniAusentes,
                'pendientes_facturar' => $pniPendientesFacturar,
                'honorario_real' => round($pniHonorarioReal, 2),
                'por_cobrar_estimado' => round($pniPorCobrarEstimado, 2),
                'perdida_estimada' => round($pniPerdidaEstimada, 2),
                'sin_tarifa_estimable' => $pniSinTarifaEstimable,
                'estados' => $this->metricValues($pniEstadoCounts, null, $pniTotal),
                'doctores_por_cobrar' => $this->moneyMetricValues($pniPorCobrarDoctor, 8, $pniPorCobrarEstimado),
                'doctores_perdida' => $this->moneyMetricValues($pniPerdidaDoctor, 8, $pniPerdidaEstimada),
            ],
            'servicios_oftalmologicos' => [
                'total' => $serviciosOftalmologicosTotal,
                'realizadas' => $serviciosOftalmologicosRealizadas,
                'facturadas' => (int) ($serviciosOftalmologicosEstadoCounts['FACTURADA'] ?? 0),
                'realizada_consulta' => (int) ($serviciosOftalmologicosEstadoCounts['REALIZADA_CONSULTA'] ?? 0),
                'canceladas' => $serviciosOftalmologicosCanceladas,
                'ausentes' => $serviciosOftalmologicosAusentes,
                'pendientes_facturar' => $serviciosOftalmologicosPendientesFacturar,
                'honorario_real' => round($serviciosOftalmologicosHonorarioReal, 2),
                'por_cobrar_estimado' => round($serviciosOftalmologicosPorCobrarEstimado, 2),
                'perdida_estimada' => round($serviciosOftalmologicosPerdidaEstimada, 2),
                'sin_tarifa_estimable' => $serviciosOftalmologicosSinTarifaEstimable,
                'estados' => $this->metricValues($serviciosOftalmologicosEstadoCounts, null, $serviciosOftalmologicosTotal),
                'doctores_por_cobrar' => $this->moneyMetricValues($serviciosOftalmologicosPorCobrarDoctor, 8, $serviciosOftalmologicosPorCobrarEstimado),
                'doctores_perdida' => $this->moneyMetricValues($serviciosOftalmologicosPerdidaDoctor, 8, $serviciosOftalmologicosPerdidaEstimada),
            ],
            'cirugias' => [
                'total' => $cirugiasTotal,
                'realizadas' => $cirugiasRealizadas,
                'operada_confirmada' => (int) ($cirugiasEstadoCounts['OPERADA_CONFIRMADA'] ?? 0),
                'operada_con_protocolo' => (int) ($cirugiasEstadoCounts['OPERADA_CON_PROTOCOLO'] ?? 0),
                'operada_otro_centro' => (int) ($cirugiasEstadoCounts['OPERADA_OTRO_CENTRO'] ?? 0),
                'canceladas' => $cirugiasCanceladas,
                'sin_cierre' => $cirugiasSinCierre,
                'pendientes_facturar' => $cirugiasPendientesFacturar,
                'facturadas_locales' => $cirugiasFacturadasLocales,
                'facturadas_externas' => $cirugiasFacturadasExternas,
                'honorario_real' => round($cirugiasHonorarioReal, 2),
                'por_cobrar_estimado' => round($cirugiasPorCobrarEstimado, 2),
                'perdida_estimada' => round($cirugiasPerdidaEstimada, 2),
                'sin_tarifa_estimable' => $cirugiasSinTarifaEstimable,
                'estados' => $this->metricValues($cirugiasEstadoCounts, null, $cirugiasTotal),
                'doctores_por_cobrar' => $this->moneyMetricValues($cirugiasPorCobrarDoctor, 8, $cirugiasPorCobrarEstimado),
                'doctores_perdida' => $this->moneyMetricValues($cirugiasPerdidaDoctor, 8, $cirugiasPerdidaEstimada),
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

    private function economicsJoinDefinition(): string
    {
        if (
            $this->columnExists('billing_facturacion_real', 'form_id')
            && $this->columnExists('billing_facturacion_real', 'monto_honorario')
        ) {
            return <<<SQL
                LEFT JOIN (
                    SELECT
                        bfr.form_id,
                        MAX(NULLIF(TRIM(COALESCE(bfr.factura_id, '')), '')) AS billing_id,
                        MAX(
                            CASE
                                WHEN CAST(bfr.fecha_facturacion AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                                ELSE bfr.fecha_facturacion
                            END
                        ) AS fecha_facturacion,
                        MAX(
                            CASE
                                WHEN CAST(bfr.fecha_atencion AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                                ELSE bfr.fecha_atencion
                            END
                        ) AS fecha_atencion,
                        COALESCE(SUM(bfr.monto_honorario), 0) AS total_produccion,
                        COALESCE(SUM(bfr.monto_honorario), 0) AS monto_honorario_real,
                        COALESCE(SUM(bfr.monto_facturado), 0) AS monto_facturado_real,
                        COUNT(*) AS procedimientos_facturados,
                        GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(bfr.formas_pago, '')), '') SEPARATOR ' | ') AS formas_pago,
                        GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(bfr.numero_factura, '')), '') SEPARATOR ' | ') AS numero_factura,
                        GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(bfr.factura_id, '')), '') SEPARATOR ' | ') AS factura_id,
                        MAX(NULLIF(TRIM(COALESCE(bfr.cliente, '')), '')) AS cliente_facturacion,
                        MAX(NULLIF(TRIM(COALESCE(bfr.area, '')), '')) AS area_facturacion,
                        MAX(NULLIF(TRIM(COALESCE(bfr.estado, '')), '')) AS estado_facturacion_raw
                    FROM billing_facturacion_real bfr
                    GROUP BY bfr.form_id
                ) AS econ
                  ON econ.form_id = atenciones.form_id
            SQL;
        }

        return <<<SQL
            LEFT JOIN (
                SELECT
                    CAST(NULL AS CHAR(50)) AS form_id,
                    CAST(NULL AS CHAR(50)) AS billing_id,
                    CAST(NULL AS DATETIME) AS fecha_facturacion,
                    CAST(NULL AS DATETIME) AS fecha_atencion,
                    0 AS total_produccion,
                    0 AS monto_honorario_real,
                    0 AS monto_facturado_real,
                    0 AS procedimientos_facturados,
                    CAST(NULL AS CHAR(255)) AS formas_pago,
                    CAST(NULL AS CHAR(50)) AS numero_factura,
                    CAST(NULL AS CHAR(50)) AS factura_id,
                    CAST(NULL AS CHAR(255)) AS cliente_facturacion,
                    CAST(NULL AS CHAR(255)) AS area_facturacion,
                    CAST(NULL AS CHAR(100)) AS estado_facturacion_raw
                WHERE 1 = 0
            ) AS econ
              ON econ.form_id = atenciones.form_id
        SQL;
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

    private function surgeryAttentionCondition(string $alias): string
    {
        $procedureExpr = "UPPER(TRIM(COALESCE({$alias}.procedimiento_proyectado, '')))";
        return "{$procedureExpr} LIKE 'CIRUGIAS%'";
    }

    private function pniAttentionCondition(string $alias): string
    {
        $procedureExpr = "UPPER(TRIM(COALESCE({$alias}.procedimiento_proyectado, '')))";
        return "{$procedureExpr} LIKE '%PNI%'";
    }

    private function ophthalmologyServiceAttentionCondition(string $alias): string
    {
        $procedureExpr = "UPPER(TRIM(COALESCE({$alias}.procedimiento_proyectado, '')))";

        return '('
            . "{$procedureExpr} LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE%'"
            . " OR {$procedureExpr} LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA%'"
            . " OR {$procedureExpr} LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL%'"
            . " OR {$procedureExpr} LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA%'"
            . " OR {$procedureExpr} LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-007 - REVISION DE EXAMENES%'"
            . ')';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function shouldIncludeRowForReport(array $row): bool
    {
        $tipoAtencion = (string) ($row['tipo_atencion'] ?? '');
        if (
            $this->isSurgeryAttentionType($tipoAtencion)
            || $this->isPniAttentionType($tipoAtencion)
            || $this->isOphthalmologyServiceAttentionType($tipoAtencion)
        ) {
            $estadoRealizacion = strtoupper(trim((string) ($row['estado_realizacion'] ?? '')));
            return $estadoRealizacion !== '';
        }

        return $this->isEncounterAttended((string) ($row['estado_encuentro'] ?? ''));
    }

    private function isSurgeryAttentionType(string $type): bool
    {
        return strtoupper(trim($type)) === 'CIRUGIAS';
    }

    private function isPniAttentionType(string $type): bool
    {
        return strtoupper(trim($type)) === 'PNI';
    }

    private function isOphthalmologyServiceAttentionType(string $type): bool
    {
        return strtoupper(trim($type)) === 'SERVICIOS OFTALMOLOGICOS GENERALES';
    }

    private function isAllowedOphthalmologyServiceProcedure(string $procedure): bool
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $procedure) ?? $procedure));
        if ($normalized === '') {
            return false;
        }

        return str_starts_with($normalized, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE')
            || str_starts_with($normalized, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA')
            || str_starts_with($normalized, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL')
            || str_starts_with($normalized, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA')
            || str_starts_with($normalized, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-007 - REVISION DE EXAMENES');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hasBillingEvidence(array $row): bool
    {
        $billingId = trim((string) ($row['billing_id'] ?? ''));
        $facturaId = trim((string) ($row['factura_id'] ?? ''));
        $numeroFactura = trim((string) ($row['numero_factura'] ?? ''));
        $fechaFacturacion = trim((string) ($row['fecha_facturacion'] ?? ''));
        $fechaAtencion = trim((string) ($row['fecha_atencion'] ?? ''));
        $procedimientosFacturados = (int) ($row['procedimientos_facturados'] ?? 0);
        $honorarioReal = (float) ($row['monto_honorario_real'] ?? 0);

        return $billingId !== ''
            || $facturaId !== ''
            || $numeroFactura !== ''
            || $fechaFacturacion !== ''
            || $fechaAtencion !== ''
            || $procedimientosFacturados > 0
            || $honorarioReal > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hasConsultaUtil(array $row): bool
    {
        $consultaFecha = trim((string) ($row['consulta_fecha'] ?? ''));
        $consultaDiagnosticos = trim((string) ($row['consulta_diagnosticos'] ?? ''));

        return $consultaFecha !== '' || $consultaDiagnosticos !== '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolvePniRealizationState(array $row, bool $hasBillingEvidence): string
    {
        if ($hasBillingEvidence) {
            return 'FACTURADA';
        }

        $estadoEncuentro = strtoupper(trim((string) ($row['estado_encuentro'] ?? '')));

        if ($this->hasConsultaUtil($row) && $this->isEncounterAttended($estadoEncuentro)) {
            return 'REALIZADA_CONSULTA';
        }

        if ($estadoEncuentro === 'CANCELADO' || $estadoEncuentro === 'CANCELADA') {
            return 'CANCELADA';
        }

        return 'AUSENTE';
    }

    private function resolvePniBillingState(string $estadoRealizacion): string
    {
        if ($estadoRealizacion === 'FACTURADA') {
            return 'FACTURADA';
        }

        if ($estadoRealizacion === 'REALIZADA_CONSULTA') {
            return 'PENDIENTE_FACTURAR';
        }

        return 'SIN_FACTURACION';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveOphthalmologyServiceRealizationState(array $row, bool $hasBillingEvidence): string
    {
        return $this->resolvePniRealizationState($row, $hasBillingEvidence);
    }

    private function resolveOphthalmologyServiceBillingState(string $estadoRealizacion): string
    {
        return $this->resolvePniBillingState($estadoRealizacion);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolvePniReviewAlert(array $row, string $estadoRealizacion, bool $hasBillingEvidence): ?string
    {
        $estadoEncuentro = strtoupper(trim((string) ($row['estado_encuentro'] ?? '')));
        $fechaAtencion = trim((string) ($row['fecha_atencion'] ?? ''));
        $fechaProgramada = trim((string) ($row['fecha'] ?? ''));
        $honorarioReal = (float) ($row['monto_honorario_real'] ?? 0);

        if ($estadoRealizacion === 'FACTURADA') {
            if ($fechaAtencion === '') {
                return 'FACTURADA_SIN_FECHA_ATENCION';
            }

            if ($honorarioReal <= 0) {
                return 'FACTURADA_SIN_HONORARIO';
            }

            $fechaAtencionTs = strtotime($fechaAtencion);
            $fechaProgramadaTs = strtotime($fechaProgramada);
            if ($fechaAtencionTs !== false && $fechaProgramadaTs !== false && date('Y-m-d', $fechaAtencionTs) > date('Y-m-d', $fechaProgramadaTs)) {
                return 'ATENCION_POSTERIOR_A_FECHA_PROGRAMADA';
            }

            return null;
        }

        if ($estadoRealizacion === 'REALIZADA_CONSULTA') {
            if (in_array($estadoEncuentro, ['CONFIRMADO', 'AGENDADO', 'LLEGADO'], true)) {
                return 'AGENDA_DESACTUALIZADA';
            }

            return 'PENDIENTE_FACTURAR';
        }

        if ($estadoRealizacion === 'AUSENTE' && !$hasBillingEvidence) {
            return 'SIN_CIERRE';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveOphthalmologyServiceReviewAlert(array $row, string $estadoRealizacion, bool $hasBillingEvidence): ?string
    {
        return $this->resolvePniReviewAlert($row, $estadoRealizacion, $hasBillingEvidence);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveSurgeryRealizationState(array $row, bool $hasBillingEvidence): string
    {
        $hasProtocol = trim((string) ($row['fuente_atencion'] ?? '')) === 'protocolo'
            || trim((string) ($row['protocolo_id'] ?? '')) !== ''
            || (int) ($row['protocolo_status_ok'] ?? 0) === 1
            || (int) ($row['protocolo_firmado'] ?? 0) === 1;

        if ($hasProtocol) {
            if ((int) ($row['protocolo_status_ok'] ?? 0) === 1 || (int) ($row['protocolo_firmado'] ?? 0) === 1) {
                return 'OPERADA_CONFIRMADA';
            }

            return 'OPERADA_CON_PROTOCOLO';
        }

        if ($hasBillingEvidence) {
            return 'OPERADA_OTRO_CENTRO';
        }

        $estadoEncuentro = strtoupper(trim((string) ($row['estado_encuentro'] ?? '')));
        if ($estadoEncuentro === 'CANCELADO' || $estadoEncuentro === 'CANCELADA') {
            return 'CANCELADA';
        }

        return 'SIN_CIERRE_OPERATIVO';
    }

    private function resolveSurgeryBillingState(string $estadoRealizacion, bool $hasBillingEvidence): string
    {
        if ($estadoRealizacion === 'OPERADA_OTRO_CENTRO') {
            return 'FACTURADA_EXTERNA';
        }

        if (in_array($estadoRealizacion, ['OPERADA_CONFIRMADA', 'OPERADA_CON_PROTOCOLO'], true)) {
            return $hasBillingEvidence ? 'FACTURADA' : 'PENDIENTE_FACTURAR';
        }

        return 'SIN_FACTURACION';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveSurgeryReviewAlert(array $row, string $estadoRealizacion, string $estadoFacturacion): ?string
    {
        $estadoEncuentro = strtoupper(trim((string) ($row['estado_encuentro'] ?? '')));
        if (
            in_array($estadoRealizacion, ['OPERADA_CONFIRMADA', 'OPERADA_CON_PROTOCOLO'], true)
            && in_array($estadoEncuentro, ['CONFIRMADO', 'AGENDADO', 'LLEGADO'], true)
        ) {
            return 'AGENDA_DESACTUALIZADA';
        }

        if ($estadoFacturacion === 'PENDIENTE_FACTURAR') {
            return 'PENDIENTE_FACTURAR';
        }

        if ($estadoRealizacion === 'OPERADA_OTRO_CENTRO') {
            return 'FACTURADA_SIN_PROTOCOLO_LOCAL';
        }

        if ($estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
            return 'SIN_CIERRE';
        }

        return null;
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

        if (
            preg_match(
                '/^\s*[^-]+?\s*-\s*([A-Z]{2,5}(?:-[A-Z0-9]{2,10}){1,3}|\d{5,6})\s*-\s*(.+)$/i',
                $text,
                $matches
            ) === 1
        ) {
            return [strtoupper(trim((string) $matches[1])), trim((string) $matches[2])];
        }

        if (preg_match('/-\s*(\d{5,6})\s*-\s*(.+)$/', $text, $matches) === 1) {
            return [trim((string) $matches[1]), trim((string) $matches[2])];
        }

        return ['', $text];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lookupTarifa(string $codigo, array $row = []): float
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return 0.0;
        }

        $levelKey = $this->resolveTarifaLevelKey($row);
        $cacheKey = $codigo . '|' . ($levelKey ?? '__sin_nivel__');
        if (array_key_exists($cacheKey, $this->tarifaLookupCache)) {
            return $this->tarifaLookupCache[$cacheKey];
        }

        if ($levelKey === null) {
            $this->tarifaLookupCache[$cacheKey] = 0.0;
            return 0.0;
        }

        try {
            $codeRow = $this->findTarifaCode($codigo);
            if ($codeRow === null) {
                $this->tarifaLookupCache[$cacheKey] = 0.0;
                return 0.0;
            }

            $prices = $this->codePriceService()->pricesForCode((int) $codeRow['id'], $this->codePriceLevels());
            $price = round((float) ($prices[$levelKey] ?? 0), 2);
            $this->tarifaLookupCache[$cacheKey] = $price;

            return $price;
        } catch (Throwable) {
            $this->tarifaLookupCache[$cacheKey] = 0.0;
            return 0.0;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveTarifaLevelKey(array $row): ?string
    {
        $levels = $this->codePriceLevels();
        if ($levels === []) {
            return null;
        }

        $candidates = [];
        foreach ([
            (string) ($row['afiliacion_original'] ?? ''),
            (string) ($row['afiliacion'] ?? ''),
        ] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $candidates[] = $candidate;

            $mapped = $this->resolveMappedAffiliation($candidate);
            if ($mapped !== null) {
                $mappedRaw = trim((string) ($mapped['afiliacion_raw'] ?? ''));
                if ($mappedRaw !== '') {
                    $candidates[] = $mappedRaw;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        foreach ($candidates as $candidate) {
            $levelKey = $this->codePriceService()->resolveLevelKey($candidate, $levels);
            if ($levelKey !== null) {
                return $levelKey;
            }
        }

        return null;
    }

    /**
     * @return array{id:int,codigo:string,descripcion:string}|null
     */
    private function findTarifaCode(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        if (array_key_exists($codigo, $this->tarifaCodeCache)) {
            return $this->tarifaCodeCache[$codigo];
        }

        $codigoSinCeros = ltrim($codigo, '0');
        $query = Tarifario2014::query()
            ->select(['id', 'codigo', 'descripcion'])
            ->where(function ($builder) use ($codigo, $codigoSinCeros): void {
                $builder->where('codigo', $codigo);
                if ($codigoSinCeros !== '' && $codigoSinCeros !== $codigo) {
                    $builder->orWhere('codigo', $codigoSinCeros);
                }
            });

        $code = $query->orderByRaw('CASE WHEN codigo = ? THEN 0 ELSE 1 END', [$codigo])->first();
        if ($code === null) {
            return null;
        }

        $resolved = [
            'id' => (int) $code->id,
            'codigo' => trim((string) ($code->codigo ?? '')),
            'descripcion' => trim((string) ($code->descripcion ?? '')),
        ];

        $this->tarifaCodeCache[$codigo] = $resolved;

        return $resolved;
    }

    private function codePriceService(): CodePriceService
    {
        if ($this->codePriceService instanceof CodePriceService) {
            return $this->codePriceService;
        }

        $this->codePriceService = new CodePriceService();

        return $this->codePriceService;
    }

    /**
     * @return array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}>
     */
    private function codePriceLevels(): array
    {
        if (is_array($this->codePriceLevelsCache)) {
            return $this->codePriceLevelsCache;
        }

        try {
            $this->codePriceLevelsCache = $this->codePriceService()->levels();
        } catch (Throwable) {
            $this->codePriceLevelsCache = [];
        }

        return $this->codePriceLevelsCache;
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

    /**
     * @param array<string, float> $amounts
     * @return array<int, array{valor:string,monto:float,porcentaje:float}>
     */
    private function moneyMetricValues(array $amounts, ?int $limit = null, ?float $totalForShare = null): array
    {
        if (empty($amounts)) {
            return [];
        }

        arsort($amounts);
        if ($limit !== null && $limit > 0) {
            $amounts = array_slice($amounts, 0, $limit, true);
        }

        $total = $totalForShare ?? array_sum($amounts);
        if ($total < 0.0001) {
            $total = 0.0;
        }

        $result = [];
        foreach ($amounts as $valor => $monto) {
            $amount = round((float) $monto, 2);
            $result[] = [
                'valor' => (string) $valor,
                'monto' => $amount,
                'porcentaje' => $total > 0 ? round(($amount / $total) * 100, 2) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function explodePipeValues(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/\s*\|\s*/', $value) ?: [];
        $result = [];
        foreach ($parts as $part) {
            $normalized = strtoupper(trim((string) $part));
            if ($normalized === '') {
                continue;
            }
            $result[$normalized] = $normalized;
        }

        return array_values($result);
    }
}
