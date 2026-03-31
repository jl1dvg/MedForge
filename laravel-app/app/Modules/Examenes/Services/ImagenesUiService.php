<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use App\Models\Tarifario2014;
use App\Modules\Codes\Services\CodePriceService;
use App\Modules\Shared\Support\AfiliacionDimensionService;
use DateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;

class ImagenesUiService
{
    private PDO $db;
    private AfiliacionDimensionService $afiliacionDimensions;

    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    /** @var array<string,array{id:int,codigo:string,descripcion:string}|null> */
    private array $tarifaCodeCache = [];

    /** @var array<string,array{id:int,codigo:string,descripcion:string}|null> */
    private array $tarifaDescriptionResolveCache = [];

    /** @var array<int,array<string,float>> */
    private array $codePriceCache = [];

    /** @var array<string,array{categoria:string,afiliacion_raw:string,empresa_seguro:string}>|null */
    private ?array $afiliacionCategoriaMapCache = null;

    /** @var array<int,array{id:int,codigo:string,descripcion:string,descripcion_norm:string,short_description_norm:string}>|null */
    private ?array $tarifaDescriptionIndexCache = null;

    /** @var array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}>|null */
    private ?array $codePriceLevelsCache = null;

    /** @var array<string,string|null> */
    private array $levelKeyResolveCache = [];

    private ?CodePriceService $codePriceService = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DB::connection()->getPdo();
        $this->afiliacionDimensions = new AfiliacionDimensionService($this->db);
    }

    /**
     * @param array<string,mixed> $query
     * @return array{filters:array<string,string>,rows:array<int,array<string,mixed>>,afiliacionOptions:array<int,array{value:string,label:string}>,seguroOptions:array<int,array{value:string,label:string}>}
     */
    public function imagenesRealizadas(array $query): array
    {
        $filters = $this->buildFilters($query);
        $filters['afiliacion_match_mode'] = 'grouped';
        $rows = $this->fetchImagenesRealizadas($filters, true);
        $rows = array_map(fn(array $row): array => $this->decorateImagenRow($row), $rows);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'afiliacionOptions' => $this->getImagenesAfiliacionOptions(),
            'seguroOptions' => $this->getImagenesSeguroOptions((string) ($filters['afiliacion'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array{filters:array<string,string>,rows:array<int,array<string,mixed>>,dashboard:array<string,mixed>,afiliacionOptions:array<int,array{value:string,label:string}>,afiliacionCategoriaOptions:array<int,array{value:string,label:string}>,seguroOptions:array<int,array{value:string,label:string}>,sedeOptions:array<int,array{value:string,label:string}>,filtersSummary:array<int,array{label:string,value:string}>,detailRows:array<int,array<string,mixed>>}
     */
    public function imagenesDashboard(array $query): array
    {
        $filters = $this->buildFilters($query);
        $filters['afiliacion_match_mode'] = 'grouped';
        $rows = $this->fetchImagenesRealizadas($filters, true);
        $rows = array_map(fn(array $row): array => $this->decorateImagenRow($row), $rows);
        $solicitudes = $this->fetchImagenesSolicitudPipeline($filters);
        $dashboard = $this->buildImagenesDashboardSummary($rows, $filters, $solicitudes);
        $detailRows = $this->buildImagenesDashboardDetailRows($rows);
        [$afiliacionOptions, $afiliacionCategoriaOptions, $seguroOptions, $sedeOptions] = $this->resolveImagenesDashboardAffiliationOptions($filters);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'dashboard' => $dashboard,
            'detailRows' => $detailRows,
            'filtersSummary' => $this->buildImagenesDashboardFiltersSummary($filters, $afiliacionOptions, $afiliacionCategoriaOptions, $seguroOptions, $sedeOptions),
            'afiliacionOptions' => $afiliacionOptions,
            'afiliacionCategoriaOptions' => $afiliacionCategoriaOptions,
            'seguroOptions' => $seguroOptions,
            'sedeOptions' => $sedeOptions,
        ];
    }

    public function actualizarProcedimientoProyectado(int $id, string $tipoExamen): bool
    {
        $stmt = $this->db->prepare('UPDATE procedimiento_proyectado SET procedimiento_proyectado = :tipo_examen WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_examen', $tipoExamen, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function eliminarProcedimientoProyectado(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM procedimiento_proyectado WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * @param array<string,mixed> $query
     * @return array{
     *     filters:array<string,string>,
     *     filtersSummary:array<int,array{label:string,value:string}>,
     *     dashboard:array<string,mixed>,
     *     detailRows:array<int,array<string,mixed>>,
     *     requestRows:array<int,array<string,mixed>>,
     *     report:array<string,mixed>,
     *     total:int
     * }
     */
    public function imagenesDashboardExportPayload(array $query): array
    {
        $payload = $this->imagenesDashboard($query);
        $dashboard = is_array($payload['dashboard'] ?? null) ? $payload['dashboard'] : [];
        $detailRows = is_array($payload['detailRows'] ?? null) ? $payload['detailRows'] : [];
        $requestRows = $this->buildImagenesDashboardSolicitudRows(is_array($payload['filters'] ?? null) ? $payload['filters'] : []);
        $filtersSummary = is_array($payload['filtersSummary'] ?? null) ? $payload['filtersSummary'] : [];

        return [
            'filters' => $payload['filters'],
            'filtersSummary' => $filtersSummary,
            'dashboard' => $dashboard,
            'detailRows' => $detailRows,
            'requestRows' => $requestRows,
            'report' => $this->buildImagenesDashboardReport(
                $dashboard,
                $detailRows,
                $filtersSummary,
                is_array($payload['filters'] ?? null) ? $payload['filters'] : []
            ),
            'total' => count($detailRows),
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,string>
     */
    private function buildFilters(array $query): array
    {
        $fechaInicio = trim((string) ($query['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($query['fecha_fin'] ?? ''));
        $hcNumber = trim((string) ($query['hc_number'] ?? ''));
        $formId = trim((string) ($query['form_id'] ?? ''));
        $hasDirectExamContext = $hcNumber !== '' || $formId !== '';

        return [
            'fecha_inicio' => $hasDirectExamContext && $fechaInicio === ''
                ? ''
                : $this->normalizeDateFilter($fechaInicio, 'first day of this month'),
            'fecha_fin' => $hasDirectExamContext && $fechaFin === ''
                ? ''
                : $this->normalizeDateFilter($fechaFin, 'last day of this month'),
            'afiliacion' => trim((string) ($query['afiliacion'] ?? '')),
            'afiliacion_categoria' => trim((string) ($query['afiliacion_categoria'] ?? '')),
            'seguro' => trim((string) ($query['seguro'] ?? '')),
            'sede' => trim((string) ($query['sede'] ?? '')),
            'tipo_examen' => trim((string) ($query['tipo_examen'] ?? '')),
            'paciente' => trim((string) ($query['paciente'] ?? '')),
            'estado_agenda' => trim((string) ($query['estado_agenda'] ?? '')),
            'hc_number' => $hcNumber,
            'form_id' => $formId,
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array{0:array<int,array{value:string,label:string}>,1:array<int,array{value:string,label:string}>,2:array<int,array{value:string,label:string}>,3:array<int,array{value:string,label:string}>}
     */
    private function resolveImagenesDashboardAffiliationOptions(array $filters): array
    {
        $sedeOptions = [
            ['value' => '', 'label' => 'Todas las sedes'],
            ['value' => 'MATRIZ', 'label' => 'MATRIZ'],
            ['value' => 'CEIBOS', 'label' => 'CEIBOS'],
        ];

        return [
            $this->getImagenesAfiliacionOptions(),
            $this->getImagenesAfiliacionCategoriaOptions(),
            $this->getImagenesSeguroOptions((string) ($filters['afiliacion'] ?? '')),
            $sedeOptions,
        ];
    }

    private function normalizeDateFilter(string $input, string $fallback): string
    {
        if ($input !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $input);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return (new DateTime($fallback))->format('Y-m-d');
    }

    /**
     * @param array{
     *     fecha_inicio?: string,
     *     fecha_fin?: string,
     *     afiliacion?: string,
     *     afiliacion_match_mode?: string,
     *     afiliacion_categoria?: string,
     *     sede?: string,
     *     tipo_examen?: string,
     *     paciente?: string,
     *     estado_agenda?: string,
     *     hc_number?: string,
     *     form_id?: string
     * } $filters
     * @return array<int,array<string,mixed>>
     */
    private function fetchImagenesRealizadas(array $filters = [], bool $includeFacturado = false): array
    {
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $displayAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), 'Sin afiliación')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr, 'iacm');
        $afiliacionLabelExpr = $this->afiliacionLabelExpr($rawAfiliacionExpr, 'iacm');
        $afiliacionExactExpr = 'TRIM(' . $this->normalizeSqlText($displayAfiliacionExpr) . ')';
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm');
        $seguroKeyExpr = $this->seguroKeyExpr($rawAfiliacionExpr, 'iacm');
        $seguroLabelExpr = $this->seguroLabelExpr($rawAfiliacionExpr, 'iacm');
        $sedeExpr = $this->imagenesSedeExpr();
        $imagenInformeJoin = "LEFT JOIN (
                SELECT
                    ii.form_id,
                    MAX(ii.id) AS informe_id,
                    MAX(ii.firmado_por) AS informe_firmado_por,
                    MAX(ii.updated_at) AS informe_actualizado,
                    COUNT(*) AS informes_total
                FROM imagenes_informes ii
                WHERE ii.form_id IS NOT NULL AND TRIM(ii.form_id) <> ''
                GROUP BY ii.form_id
            ) ii ON ii.form_id = pp.form_id";
        $nasAvailable = $this->tableExists('imagenes_sigcenter_index') && $this->columnExists('imagenes_sigcenter_index', 'form_id');
        $nasSelect = $nasAvailable
            ? "COALESCE(ini.has_files, 0) AS nas_has_files,
               COALESCE(ini.files_count, 0) AS nas_files_count,
               COALESCE(ini.image_count, 0) AS nas_image_count,
               COALESCE(ini.pdf_count, 0) AS nas_pdf_count,
               COALESCE(NULLIF(TRIM(ini.scan_status), ''), '') AS nas_scan_status,
               ini.last_scanned_at AS nas_last_scanned_at"
            : "0 AS nas_has_files,
               0 AS nas_files_count,
               0 AS nas_image_count,
               0 AS nas_pdf_count,
               '' AS nas_scan_status,
               NULL AS nas_last_scanned_at";
        $nasJoin = $nasAvailable ? "LEFT JOIN imagenes_sigcenter_index ini ON TRIM(COALESCE(ini.form_id, '')) = TRIM(COALESCE(pp.form_id, ''))" : '';

        $facturacionSql = $this->buildImagenesFacturacionSql($includeFacturado);

        $sql = "SELECT
                pp.id,
                pp.form_id,
                pp.hc_number,
                CASE
                    WHEN pp.fecha IS NOT NULL AND pp.hora IS NOT NULL THEN CONCAT(pp.fecha, ' ', pp.hora)
                    WHEN pp.fecha IS NOT NULL THEN pp.fecha
                    ELSE NULL
                END AS fecha_examen,
                {$displayAfiliacionExpr} AS afiliacion,
                {$afiliacionLabelExpr} AS empresa_seguro,
                {$afiliacionKeyExpr} AS afiliacion_key,
                {$seguroLabelExpr} AS seguro_label,
                {$categoriaContext['expr']} AS afiliacion_categoria,
                CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) AS full_name,
                COALESCE(NULLIF(TRIM(pd.hc_number), ''), pp.hc_number) AS cedula,
                NULLIF(TRIM(pp.procedimiento_proyectado), '') AS tipo_examen,
                NULL AS examen_nombre,
                NULL AS examen_codigo,
                NULL AS imagen_ruta,
                NULL AS imagen_nombre,
                {$sedeExpr} AS sede,
                pp.estado_agenda,
                ii.informe_id AS informe_id,
                ii.informe_firmado_por AS informe_firmado_por,
                ii.informe_actualizado AS informe_actualizado,
                COALESCE(ii.informes_total, 0) AS informes_total,
                {$nasSelect},
                {$facturacionSql['select']}
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
            {$imagenInformeJoin}
            {$nasJoin}
            {$categoriaContext['join']}
            {$facturacionSql['join']}
            WHERE pp.estado_agenda IS NOT NULL
              AND TRIM(pp.estado_agenda) <> ''
              AND UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'IMAGENES%'";

        $params = [];

        if (!empty($filters['fecha_inicio']) && !empty($filters['fecha_fin'])) {
            $sql .= ' AND pp.fecha BETWEEN :fecha_inicio AND :fecha_fin';
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }

        $afiliacionFilterRaw = trim((string) ($filters['afiliacion'] ?? ''));
        $afiliacionMatchMode = trim((string) ($filters['afiliacion_match_mode'] ?? 'grouped'));
        if ($afiliacionFilterRaw !== '') {
            if ($afiliacionMatchMode === 'exact') {
                $sql .= " AND {$afiliacionExactExpr} = :afiliacion_filter_match";
                $params[':afiliacion_filter_match'] = $this->normalizeAfiliacionExactFilter($afiliacionFilterRaw);
            } else {
                $afiliacionFilter = $this->normalizeAfiliacionFilter($afiliacionFilterRaw);
                if ($afiliacionFilter !== '') {
                    $sql .= " AND {$afiliacionKeyExpr} = :afiliacion_filter_match";
                    $params[':afiliacion_filter_match'] = $afiliacionFilter;
                }
            }
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $sql .= " AND {$categoriaContext['expr']} = :afiliacion_categoria_filter_match";
            $params[':afiliacion_categoria_filter_match'] = $afiliacionCategoriaFilter;
        }

        $seguroFilter = $this->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $sql .= " AND {$seguroKeyExpr} = :seguro_filter_match";
            $params[':seguro_filter_match'] = $seguroFilter;
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sql .= " AND {$sedeExpr} = :sede_filter_match";
            $params[':sede_filter_match'] = $sedeFilter;
        }

        if (!empty($filters['tipo_examen'])) {
            $sql .= ' AND TRIM(pp.procedimiento_proyectado) LIKE :tipo_examen';
            $params[':tipo_examen'] = '%' . $filters['tipo_examen'] . '%';
        }

        if (!empty($filters['paciente'])) {
            $sql .= " AND (
                pd.hc_number LIKE :paciente_hc
                OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente_nombre
                OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente_nombre_alt
            )";
            $pacienteLike = '%' . $filters['paciente'] . '%';
            $params[':paciente_hc'] = $pacienteLike;
            $params[':paciente_nombre'] = $pacienteLike;
            $params[':paciente_nombre_alt'] = $pacienteLike;
        }

        if (!empty($filters['estado_agenda'])) {
            $sql .= ' AND TRIM(pp.estado_agenda) = :estado_agenda';
            $params[':estado_agenda'] = $filters['estado_agenda'];
        }

        if (!empty($filters['hc_number'])) {
            $sql .= " AND TRIM(COALESCE(pp.hc_number, '')) = :hc_number";
            $params[':hc_number'] = trim((string) $filters['hc_number']);
        }

        if (!empty($filters['form_id'])) {
            $sql .= " AND TRIM(COALESCE(pp.form_id, '')) = :form_id";
            $params[':form_id'] = trim((string) $filters['form_id']);
        }

        $sql .= ' ORDER BY pp.fecha DESC, pp.hora DESC, pp.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        foreach ($rows as &$row) {
            $row = $this->mergeImagenBillingEvidence($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{select:string,join:string}
     */
    private function buildImagenesFacturacionSql(bool $includeFacturado): array
    {
        $defaultSelect = "0 AS facturado,
               NULL AS billing_id,
               NULL AS fecha_facturacion,
               NULL AS fecha_atencion,
               0 AS total_produccion,
               0 AS procedimientos_facturados,
               NULL AS numero_factura,
               NULL AS factura_id,
               NULL AS estado_facturacion_raw,
               NULL AS real_billing_id,
               NULL AS real_fecha_facturacion,
               NULL AS real_fecha_atencion,
               0 AS real_total_produccion,
               0 AS real_procedimientos_facturados,
               NULL AS real_numero_factura,
               NULL AS real_factura_id,
               NULL AS real_estado_facturacion_raw,
               NULL AS public_billing_id,
               NULL AS public_fecha_facturacion,
               0 AS public_total_produccion,
               0 AS public_procedimientos_facturados";
        if (!$includeFacturado) {
            return ['select' => $defaultSelect, 'join' => ''];
        }

        $billingSources = $this->buildImagenesBillingAggregateSources();

        return [
            'select' => "0 AS facturado,
               NULL AS billing_id,
               NULL AS fecha_facturacion,
               NULL AS fecha_atencion,
               0 AS total_produccion,
               0 AS procedimientos_facturados,
               NULL AS numero_factura,
               NULL AS factura_id,
               NULL AS estado_facturacion_raw,
               NULLIF(TRIM(COALESCE(bfr.billing_id, '')), '') AS real_billing_id,
               bfr.fecha_facturacion AS real_fecha_facturacion,
               bfr.fecha_atencion AS real_fecha_atencion,
               COALESCE(bfr.total_produccion, 0) AS real_total_produccion,
               COALESCE(bfr.procedimientos_facturados, 0) AS real_procedimientos_facturados,
               NULLIF(TRIM(COALESCE(bfr.numero_factura, '')), '') AS real_numero_factura,
               NULLIF(TRIM(COALESCE(bfr.factura_id, '')), '') AS real_factura_id,
               NULLIF(TRIM(COALESCE(bfr.estado_facturacion_raw, '')), '') AS real_estado_facturacion_raw,
               NULLIF(TRIM(COALESCE(bpub.billing_id, '')), '') AS public_billing_id,
               bpub.fecha_facturacion AS public_fecha_facturacion,
               COALESCE(bpub.total_produccion, 0) AS public_total_produccion,
               COALESCE(bpub.procedimientos_facturados, 0) AS public_procedimientos_facturados",
            'join' => "LEFT JOIN ({$billingSources['real_subquery']}) bfr ON bfr.form_id = pp.form_id
            LEFT JOIN ({$billingSources['public_subquery']}) bpub ON bpub.form_id = pp.form_id",
        ];
    }

    /**
     * @return array{real_subquery:string,public_subquery:string}
     */
    private function buildImagenesBillingAggregateSources(): array
    {
        $hasFacturacionReal = $this->tableExists('billing_facturacion_real')
            && $this->columnExists('billing_facturacion_real', 'form_id')
            && $this->columnExists('billing_facturacion_real', 'monto_honorario');
        $hasBillingPublico = $this->tableExists('billing_main')
            && $this->columnExists('billing_main', 'id')
            && $this->columnExists('billing_main', 'form_id')
            && $this->tableExists('billing_procedimientos')
            && $this->columnExists('billing_procedimientos', 'billing_id')
            && $this->columnExists('billing_procedimientos', 'proc_precio');

        $publicFechaExpr = $hasBillingPublico && $this->columnExists('billing_main', 'created_at')
            ? "MAX(
                    CASE
                        WHEN CAST(bm.created_at AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                        ELSE bm.created_at
                    END
                ) AS fecha_facturacion"
            : 'NULL AS fecha_facturacion';

        $realSubquery = $hasFacturacionReal
            ? "SELECT
                    NULLIF(TRIM(COALESCE(bfr.form_id, '')), '') AS form_id,
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
                    MAX(NULLIF(TRIM(COALESCE(bfr.numero_factura, '')), '')) AS numero_factura,
                    MAX(NULLIF(TRIM(COALESCE(bfr.factura_id, '')), '')) AS factura_id,
                    MAX(NULLIF(TRIM(COALESCE(bfr.estado, '')), '')) AS estado_facturacion_raw,
                    COALESCE(SUM(bfr.monto_honorario), 0) AS total_produccion,
                    COUNT(*) AS procedimientos_facturados
                FROM billing_facturacion_real bfr
                WHERE bfr.form_id IS NOT NULL AND TRIM(bfr.form_id) <> ''
                GROUP BY bfr.form_id"
            : "SELECT
                    CAST(NULL AS CHAR(50)) AS form_id,
                    CAST(NULL AS CHAR(50)) AS billing_id,
                    CAST(NULL AS DATETIME) AS fecha_facturacion,
                    CAST(NULL AS DATETIME) AS fecha_atencion,
                    CAST(NULL AS CHAR(50)) AS numero_factura,
                    CAST(NULL AS CHAR(50)) AS factura_id,
                    CAST(NULL AS CHAR(100)) AS estado_facturacion_raw,
                    0 AS total_produccion,
                    0 AS procedimientos_facturados
                LIMIT 0";
        $publicSubquery = $hasBillingPublico
            ? "SELECT
                    NULLIF(TRIM(COALESCE(bm.form_id, '')), '') AS form_id,
                    MAX(CAST(bm.id AS CHAR)) AS billing_id,
                    {$publicFechaExpr},
                    COALESCE(SUM(COALESCE(bp.proc_precio, 0)), 0) AS total_produccion,
                    COUNT(*) AS procedimientos_facturados
                FROM billing_procedimientos bp
                INNER JOIN billing_main bm ON bm.id = bp.billing_id
                WHERE bm.form_id IS NOT NULL AND TRIM(bm.form_id) <> ''
                GROUP BY bm.form_id"
            : "SELECT
                    CAST(NULL AS CHAR(50)) AS form_id,
                    CAST(NULL AS CHAR(50)) AS billing_id,
                    CAST(NULL AS DATETIME) AS fecha_facturacion,
                    0 AS total_produccion,
                    0 AS procedimientos_facturados
                LIMIT 0";

        return [
            'real_subquery' => $realSubquery,
            'public_subquery' => $publicSubquery,
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array<string,int|float|null>
     */
    private function fetchImagenesSolicitudPipeline(array $filters): array
    {
        $default = [
            'solicitudes_total' => 0,
            'solicitudes_agendadas' => 0,
            'solicitudes_realizadas' => 0,
            'solicitudes_informadas' => 0,
            'solicitudes_facturadas' => 0,
            'solicitudes_agendadas_al_corte' => 0,
            'solicitudes_realizadas_al_corte' => 0,
            'solicitudes_realizadas_post_corte' => 0,
            'solicitudes_sin_agenda' => 0,
            'solicitudes_agendadas_pendientes' => 0,
            'solicitudes_pendientes_vigentes' => 0,
            'solicitudes_canceladas' => 0,
            'solicitudes_ausentes' => 0,
            'solicitudes_sin_agenda_monto_estimado' => 0.0,
            'solicitudes_sin_agenda_sin_tarifa' => 0,
            'conversion_solicitud_realizacion_pct' => null,
            'cumplimiento_realizacion_al_corte_pct' => null,
            'cumplimiento_realizacion_acumulado_pct' => null,
        ];

        if (!$this->tableExists('consulta_examenes')) {
            return $default;
        }

        $flowSubquery = $this->buildImagenesSolicitudFlowSubquery();
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(flow.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr, 'iacm_solicitudes');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm_solicitudes');
        $seguroKeyExpr = $this->seguroKeyExpr($rawAfiliacionExpr, 'iacm_solicitudes');
        $sedeExpr = $this->imagenesSedeExprFromFields('flow.sede_departamento', 'flow.id_sede');
        $fechaSolicitudExpr = $this->safeSqlDateExpr('flow.fecha_solicitud');
        $fechaAgendaFlowExpr = $this->safeSqlDateExpr('flow.fecha_agenda');
        $fechaRealizacionFlowExpr = $this->safeSqlDateExpr('flow.fecha_realizacion');
        $agendaPendienteExpr = "(COALESCE(flow.has_agenda, 0) = 1
                AND COALESCE(flow.realizada, 0) = 0
                AND COALESCE(flow.cancelada, 0) = 0
                AND COALESCE(flow.ausente, 0) = 0
                AND ({$fechaAgendaFlowExpr} IS NULL OR {$fechaAgendaFlowExpr} >= :fecha_hoy_agendada_pendiente))";
        $agendaPendienteVigenteExpr = "(COALESCE(flow.has_agenda, 0) = 1
                AND COALESCE(flow.realizada, 0) = 0
                AND COALESCE(flow.cancelada, 0) = 0
                AND COALESCE(flow.ausente, 0) = 0
                AND ({$fechaAgendaFlowExpr} IS NULL OR {$fechaAgendaFlowExpr} >= :fecha_hoy_pendiente_vigente))";
        $ausenteExpr = "(COALESCE(flow.realizada, 0) = 0
                AND COALESCE(flow.cancelada, 0) = 0
                AND (
                    COALESCE(flow.ausente, 0) = 1
                    OR (
                        COALESCE(flow.has_agenda, 0) = 1
                        AND {$fechaAgendaFlowExpr} IS NOT NULL
                        AND {$fechaAgendaFlowExpr} < :fecha_hoy_ausente
                    )
                ))";
        $pendienteVigenteExpr = "(COALESCE(flow.realizada, 0) = 0
                AND COALESCE(flow.cancelada, 0) = 0
                AND (
                    COALESCE(flow.has_agenda, 0) = 0
                    OR {$agendaPendienteVigenteExpr}
                ))";

        $sql = "SELECT
                COUNT(*) AS solicitudes_total,
                COALESCE(SUM(CASE WHEN COALESCE(flow.has_agenda, 0) = 1 THEN 1 ELSE 0 END), 0) AS solicitudes_agendadas,
                COALESCE(SUM(CASE WHEN COALESCE(flow.realizada, 0) = 1 THEN 1 ELSE 0 END), 0) AS solicitudes_realizadas,
                COALESCE(SUM(CASE WHEN COALESCE(flow.informada, 0) = 1 THEN 1 ELSE 0 END), 0) AS solicitudes_informadas,
                COALESCE(SUM(CASE WHEN COALESCE(flow.facturada, 0) = 1 THEN 1 ELSE 0 END), 0) AS solicitudes_facturadas,
                COALESCE(SUM(CASE WHEN {$fechaAgendaFlowExpr} IS NOT NULL AND {$fechaAgendaFlowExpr} <= :fecha_corte_agenda THEN 1 ELSE 0 END), 0) AS solicitudes_agendadas_al_corte,
                COALESCE(SUM(CASE WHEN {$fechaRealizacionFlowExpr} IS NOT NULL AND {$fechaRealizacionFlowExpr} <= :fecha_corte_realizada_lte THEN 1 ELSE 0 END), 0) AS solicitudes_realizadas_al_corte,
                COALESCE(SUM(CASE WHEN {$fechaRealizacionFlowExpr} IS NOT NULL AND {$fechaRealizacionFlowExpr} > :fecha_corte_realizada_gt THEN 1 ELSE 0 END), 0) AS solicitudes_realizadas_post_corte,
                COALESCE(SUM(CASE WHEN COALESCE(flow.has_agenda, 0) = 0 THEN 1 ELSE 0 END), 0) AS solicitudes_sin_agenda,
                COALESCE(SUM(CASE WHEN {$agendaPendienteExpr} THEN 1 ELSE 0 END), 0) AS solicitudes_agendadas_pendientes,
                COALESCE(SUM(CASE WHEN {$pendienteVigenteExpr} THEN 1 ELSE 0 END), 0) AS solicitudes_pendientes_vigentes,
                COALESCE(SUM(CASE WHEN COALESCE(flow.cancelada, 0) = 1 AND COALESCE(flow.realizada, 0) = 0 THEN 1 ELSE 0 END), 0) AS solicitudes_canceladas,
                COALESCE(SUM(CASE WHEN {$ausenteExpr} THEN 1 ELSE 0 END), 0) AS solicitudes_ausentes
            FROM ({$flowSubquery}) flow
            LEFT JOIN patient_data pd ON pd.hc_number = flow.hc_number
            {$categoriaContext['join']}
            WHERE flow.examen_nombre IS NOT NULL
              AND TRIM(flow.examen_nombre) <> ''";

        $params = [];
        $fechaCorte = trim((string) ($filters['fecha_fin'] ?? '')) !== '' ? trim((string) ($filters['fecha_fin'] ?? '')) : (new DateTimeImmutable('today'))->format('Y-m-d');
        $params[':fecha_corte_agenda'] = $fechaCorte;
        $params[':fecha_corte_realizada_lte'] = $fechaCorte;
        $params[':fecha_corte_realizada_gt'] = $fechaCorte;
        $fechaHoy = (new DateTimeImmutable('today'))->format('Y-m-d');
        $params[':fecha_hoy_agendada_pendiente'] = $fechaHoy;
        $params[':fecha_hoy_pendiente_vigente'] = $fechaHoy;
        $params[':fecha_hoy_ausente'] = $fechaHoy;

        $fechaInicio = trim((string) ($filters['fecha_inicio'] ?? ''));
        if ($fechaInicio !== '') {
            $sql .= " AND {$fechaSolicitudExpr} >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = trim((string) ($filters['fecha_fin'] ?? ''));
        if ($fechaFin !== '') {
            $sql .= " AND {$fechaSolicitudExpr} <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        if ($afiliacionFilter !== '') {
            $sql .= " AND {$afiliacionKeyExpr} = :afiliacion_filter_match";
            $params[':afiliacion_filter_match'] = $afiliacionFilter;
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $sql .= " AND {$categoriaContext['expr']} = :afiliacion_categoria_filter_match";
            $params[':afiliacion_categoria_filter_match'] = $afiliacionCategoriaFilter;
        }

        $seguroFilter = $this->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $sql .= " AND {$seguroKeyExpr} = :seguro_filter_match";
            $params[':seguro_filter_match'] = $seguroFilter;
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sql .= " AND {$sedeExpr} = :sede_filter_match";
            $params[':sede_filter_match'] = $sedeFilter;
        }

        $tipoExamen = trim((string) ($filters['tipo_examen'] ?? ''));
        if ($tipoExamen !== '') {
            $sql .= ' AND (
                    flow.examen_nombre LIKE :tipo_examen
                    OR flow.examen_codigo LIKE :tipo_examen
                    OR TRIM(COALESCE(flow.procedimientos_match, "")) LIKE :tipo_examen
                )';
            $params[':tipo_examen'] = '%' . $tipoExamen . '%';
        }

        $paciente = trim((string) ($filters['paciente'] ?? ''));
        if ($paciente !== '') {
            $sql .= " AND (
                    flow.hc_number LIKE :paciente_hc
                    OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente_nombre
                    OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente_nombre_alt
                )";
            $pacienteLike = '%' . $paciente . '%';
            $params[':paciente_hc'] = $pacienteLike;
            $params[':paciente_nombre'] = $pacienteLike;
            $params[':paciente_nombre_alt'] = $pacienteLike;
        }

        $estadoAgenda = trim((string) ($filters['estado_agenda'] ?? ''));
        if ($estadoAgenda !== '') {
            $sql .= ' AND TRIM(COALESCE(flow.estados_agenda, "")) LIKE :estado_agenda_like';
            $params[':estado_agenda_like'] = '%' . $estadoAgenda . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row === []) {
            return $default;
        }

        $sinAgendaEstimate = $this->fetchImagenesSolicitudesSinAgendaEstimate($filters);

        $total = (int) ($row['solicitudes_total'] ?? 0);
        $realizadas = (int) ($row['solicitudes_realizadas'] ?? 0);
        $realizadasAlCorte = (int) ($row['solicitudes_realizadas_al_corte'] ?? 0);

        return [
            'solicitudes_total' => $total,
            'solicitudes_agendadas' => (int) ($row['solicitudes_agendadas'] ?? 0),
            'solicitudes_realizadas' => $realizadas,
            'solicitudes_informadas' => (int) ($row['solicitudes_informadas'] ?? 0),
            'solicitudes_facturadas' => (int) ($row['solicitudes_facturadas'] ?? 0),
            'solicitudes_agendadas_al_corte' => (int) ($row['solicitudes_agendadas_al_corte'] ?? 0),
            'solicitudes_realizadas_al_corte' => $realizadasAlCorte,
            'solicitudes_realizadas_post_corte' => (int) ($row['solicitudes_realizadas_post_corte'] ?? 0),
            'solicitudes_sin_agenda' => (int) ($row['solicitudes_sin_agenda'] ?? 0),
            'solicitudes_agendadas_pendientes' => (int) ($row['solicitudes_agendadas_pendientes'] ?? 0),
            'solicitudes_pendientes_vigentes' => (int) ($row['solicitudes_pendientes_vigentes'] ?? 0),
            'solicitudes_canceladas' => (int) ($row['solicitudes_canceladas'] ?? 0),
            'solicitudes_ausentes' => (int) ($row['solicitudes_ausentes'] ?? 0),
            'solicitudes_sin_agenda_monto_estimado' => round((float) ($sinAgendaEstimate['monto_estimado'] ?? 0), 2),
            'solicitudes_sin_agenda_sin_tarifa' => (int) ($sinAgendaEstimate['sin_tarifa'] ?? 0),
            'conversion_solicitud_realizacion_pct' => $total > 0 ? round(($realizadas * 100) / $total, 1) : null,
            'cumplimiento_realizacion_al_corte_pct' => $total > 0 ? round(($realizadasAlCorte * 100) / $total, 1) : null,
            'cumplimiento_realizacion_acumulado_pct' => $total > 0 ? round(($realizadas * 100) / $total, 1) : null,
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array{monto_estimado:float,sin_tarifa:int}
     */
    private function fetchImagenesSolicitudesSinAgendaEstimate(array $filters): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return ['monto_estimado' => 0.0, 'sin_tarifa' => 0];
        }

        $flowSubquery = $this->buildImagenesSolicitudFlowSubquery();
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(flow.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr, 'iacm_solicitudes_loss');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm_solicitudes_loss');
        $seguroKeyExpr = $this->seguroKeyExpr($rawAfiliacionExpr, 'iacm_solicitudes_loss');
        $sedeExpr = $this->imagenesSedeExprFromFields('flow.sede_departamento', 'flow.id_sede');
        $fechaSolicitudExpr = $this->safeSqlDateExpr('flow.fecha_solicitud');

        $sql = "SELECT
                TRIM(COALESCE(flow.examen_codigo, '')) AS examen_codigo,
                TRIM(COALESCE(flow.examen_nombre, '')) AS examen_nombre,
                {$rawAfiliacionExpr} AS afiliacion,
                {$rawAfiliacionExpr} AS seguro_label,
                {$rawAfiliacionExpr} AS empresa_seguro,
                {$categoriaContext['expr']} AS afiliacion_categoria,
                COUNT(*) AS solicitudes_count
            FROM ({$flowSubquery}) flow
            LEFT JOIN patient_data pd ON pd.hc_number = flow.hc_number
            {$categoriaContext['join']}
            WHERE flow.examen_nombre IS NOT NULL
              AND TRIM(flow.examen_nombre) <> ''
              AND COALESCE(flow.has_agenda, 0) = 0";

        $params = [];

        $fechaInicio = trim((string) ($filters['fecha_inicio'] ?? ''));
        if ($fechaInicio !== '') {
            $sql .= " AND {$fechaSolicitudExpr} >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = trim((string) ($filters['fecha_fin'] ?? ''));
        if ($fechaFin !== '') {
            $sql .= " AND {$fechaSolicitudExpr} <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        if ($afiliacionFilter !== '') {
            $sql .= " AND {$afiliacionKeyExpr} = :afiliacion_filter_match";
            $params[':afiliacion_filter_match'] = $afiliacionFilter;
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $sql .= " AND {$categoriaContext['expr']} = :afiliacion_categoria_filter_match";
            $params[':afiliacion_categoria_filter_match'] = $afiliacionCategoriaFilter;
        }

        $seguroFilter = $this->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $sql .= " AND {$seguroKeyExpr} = :seguro_filter_match";
            $params[':seguro_filter_match'] = $seguroFilter;
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sql .= " AND {$sedeExpr} = :sede_filter_match";
            $params[':sede_filter_match'] = $sedeFilter;
        }

        $tipoExamen = trim((string) ($filters['tipo_examen'] ?? ''));
        if ($tipoExamen !== '') {
            $sql .= ' AND (
                    flow.examen_nombre LIKE :tipo_examen
                    OR flow.examen_codigo LIKE :tipo_examen
                    OR TRIM(COALESCE(flow.procedimientos_match, "")) LIKE :tipo_examen
                )';
            $params[':tipo_examen'] = '%' . $tipoExamen . '%';
        }

        $paciente = trim((string) ($filters['paciente'] ?? ''));
        if ($paciente !== '') {
            $sql .= " AND (
                    flow.hc_number LIKE :paciente_hc
                    OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente_nombre
                    OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente_nombre_alt
                )";
            $pacienteLike = '%' . $paciente . '%';
            $params[':paciente_hc'] = $pacienteLike;
            $params[':paciente_nombre'] = $pacienteLike;
            $params[':paciente_nombre_alt'] = $pacienteLike;
        }

        $estadoAgenda = trim((string) ($filters['estado_agenda'] ?? ''));
        if ($estadoAgenda !== '') {
            $sql .= ' AND TRIM(COALESCE(flow.estados_agenda, "")) LIKE :estado_agenda_like';
            $params[':estado_agenda_like'] = '%' . $estadoAgenda . '%';
        }

        $sql .= " GROUP BY
                TRIM(COALESCE(flow.examen_codigo, '')),
                TRIM(COALESCE(flow.examen_nombre, '')),
                {$rawAfiliacionExpr},
                {$categoriaContext['expr']}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $montoEstimado = 0.0;
        $sinTarifa = 0;
        $tarifarioCache = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                continue;
            }
            $codigo = trim((string) ($row['examen_codigo'] ?? ''));
            $categoria = trim((string) ($row['afiliacion_categoria'] ?? ''));
            $solicitudesCount = max(1, (int) ($row['solicitudes_count'] ?? 1));
            $tarifa = null;
            if ($codigo !== '') {
                $tarifaCacheKey = $codigo . '|' . $categoria;
                if (!array_key_exists($tarifaCacheKey, $tarifarioCache)) {
                    $tarifarioCache[$tarifaCacheKey] = $this->obtenerTarifarioPorCodigo($codigo, $categoria);
                }
                $tarifa = $tarifarioCache[$tarifaCacheKey];
            }

            $amount = $this->resolveImagenTarifaPendiente($codigo, $row, is_array($tarifa) ? $tarifa : null);
            if ($amount > 0) {
                $montoEstimado += $amount * $solicitudesCount;
            } else {
                $sinTarifa += $solicitudesCount;
            }
        }

        return [
            'monto_estimado' => round($montoEstimado, 2),
            'sin_tarifa' => $sinTarifa,
        ];
    }

    private function buildImagenesSolicitudFlowSubquery(): string
    {
        $fechaSolicitudExpr = $this->safeSqlDateExpr("COALESCE(
                NULLIF(CAST(ce.consulta_fecha AS CHAR), ''),
                NULLIF(CAST(ce.created_at AS CHAR), '')
            )");
        $fechaAgendaPpExpr = $this->safeSqlDateExpr('pp.fecha');
        $imagenInformeJoin = "LEFT JOIN (
                SELECT
                    ii.form_id,
                    COUNT(*) AS informes_total,
                    MAX(
                        CASE
                            WHEN CAST(ii.updated_at AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
                            ELSE ii.updated_at
                        END
                    ) AS informe_actualizado
                FROM imagenes_informes ii
                WHERE ii.form_id IS NOT NULL AND TRIM(ii.form_id) <> ''
                GROUP BY ii.form_id
            ) ii ON ii.form_id = pp.form_id";
        $nasAvailable = $this->tableExists('imagenes_sigcenter_index') && $this->columnExists('imagenes_sigcenter_index', 'form_id');
        $nasJoin = $nasAvailable ? "LEFT JOIN imagenes_sigcenter_index ini ON TRIM(COALESCE(ini.form_id, '')) = TRIM(COALESCE(pp.form_id, ''))" : '';
        $nasEvidenceExpr = $nasAvailable
            ? '(COALESCE(ini.has_files, 0) = 1 OR COALESCE(ini.files_count, 0) > 0)'
            : '0 = 1';
        $billingSources = $this->buildImagenesBillingAggregateSources();
        $statusExpr = "LOWER(TRIM(COALESCE(pp.estado_agenda, '')))";
        $canceladaExpr = "({$statusExpr} LIKE '%cancel%' OR {$statusExpr} LIKE '%anul%')";
        $ausenteExpr = "({$statusExpr} LIKE '%ausent%' OR {$statusExpr} LIKE '%no show%' OR {$statusExpr} LIKE '%no asist%' OR {$statusExpr} LIKE '%inasistent%')";
        $realBillingEvidenceExpr = "(
                NULLIF(TRIM(COALESCE(bfr.billing_id, '')), '') IS NOT NULL
                OR NULLIF(TRIM(COALESCE(bfr.numero_factura, '')), '') IS NOT NULL
                OR NULLIF(TRIM(COALESCE(bfr.factura_id, '')), '') IS NOT NULL
                OR bfr.fecha_facturacion IS NOT NULL
                OR bfr.fecha_atencion IS NOT NULL
                OR COALESCE(bfr.procedimientos_facturados, 0) > 0
                OR COALESCE(bfr.total_produccion, 0) > 0
            )";
        $publicBillingEvidenceExpr = "(
                NULLIF(TRIM(COALESCE(bpub.billing_id, '')), '') IS NOT NULL
                OR bpub.fecha_facturacion IS NOT NULL
                OR COALESCE(bpub.procedimientos_facturados, 0) > 0
                OR COALESCE(bpub.total_produccion, 0) > 0
            )";
        $informadaExpr = '(COALESCE(ii.informes_total, 0) > 0)';
        $atendidoPagadoExpr = "({$statusExpr} LIKE '%atendid%' OR {$statusExpr} LIKE '%pagad%')";
        $realizadaExpr = "({$realBillingEvidenceExpr} OR {$publicBillingEvidenceExpr} OR {$informadaExpr} OR {$nasEvidenceExpr} OR {$atendidoPagadoExpr})";
        $agendaDateExpr = "MIN(
                CASE
                    WHEN pp.estado_agenda IS NOT NULL AND TRIM(pp.estado_agenda) <> ''
                    THEN {$fechaAgendaPpExpr}
                    ELSE NULL
                END
            )";
        $realizacionDateExpr = "MIN(
                CASE
                    WHEN {$realizadaExpr}
                    THEN COALESCE(
                        {$fechaAgendaPpExpr},
                        NULLIF(CAST(bfr.fecha_atencion AS CHAR), ''),
                        NULLIF(CAST(bfr.fecha_facturacion AS CHAR), ''),
                        NULLIF(CAST(bpub.fecha_facturacion AS CHAR), ''),
                        NULLIF(CAST(ii.informe_actualizado AS CHAR), '')
                    )
                    ELSE NULL
                END
            )";

        return "SELECT
                ce.id AS solicitud_id,
                ce.hc_number,
                ce.form_id AS solicitud_form_id,
                TRIM(COALESCE(ce.examen_codigo, '')) AS examen_codigo,
                TRIM(COALESCE(ce.examen_nombre, '')) AS examen_nombre,
                {$fechaSolicitudExpr} AS fecha_solicitud,
                MAX(CASE WHEN pp.estado_agenda IS NOT NULL AND TRIM(pp.estado_agenda) <> '' THEN 1 ELSE 0 END) AS has_agenda,
                MAX(CASE WHEN {$realizadaExpr} THEN 1 ELSE 0 END) AS realizada,
                MAX(CASE WHEN {$informadaExpr} THEN 1 ELSE 0 END) AS informada,
                MAX(CASE WHEN ({$realBillingEvidenceExpr} OR {$publicBillingEvidenceExpr}) THEN 1 ELSE 0 END) AS facturada,
                MAX(CASE WHEN {$canceladaExpr} THEN 1 ELSE 0 END) AS cancelada,
                MAX(CASE WHEN {$ausenteExpr} THEN 1 ELSE 0 END) AS ausente,
                {$agendaDateExpr} AS fecha_agenda,
                {$realizacionDateExpr} AS fecha_realizacion,
                MAX(NULLIF(TRIM(pp.afiliacion), '')) AS afiliacion,
                MAX(NULLIF(TRIM(pp.doctor), '')) AS doctor_solicitante,
                MAX(NULLIF(TRIM(pp.sede_departamento), '')) AS sede_departamento,
                MAX(NULLIF(TRIM(pp.id_sede), '')) AS id_sede,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(pp.estado_agenda), '') ORDER BY pp.estado_agenda SEPARATOR ' | ') AS estados_agenda,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(pp.procedimiento_proyectado), '') ORDER BY pp.procedimiento_proyectado SEPARATOR ' || ') AS procedimientos_match
            FROM consulta_examenes ce
            LEFT JOIN procedimiento_proyectado pp
                ON pp.hc_number = ce.hc_number
               AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ''))) LIKE 'IMAGENES%'
               AND (
                    (
                        TRIM(COALESCE(ce.examen_codigo, '')) <> ''
                        AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ''))) LIKE CONCAT('%', UPPER(TRIM(ce.examen_codigo)), '%')
                    )
                    OR (
                        TRIM(COALESCE(ce.form_id, '')) <> ''
                        AND pp.form_id = ce.form_id
                    )
               )
               AND (
                    pp.form_id = ce.form_id
                    OR {$fechaAgendaPpExpr} IS NULL
                    OR {$fechaSolicitudExpr} IS NULL
                    OR {$fechaAgendaPpExpr} >= {$fechaSolicitudExpr}
               )
            {$imagenInformeJoin}
            {$nasJoin}
            LEFT JOIN ({$billingSources['real_subquery']}) bfr ON bfr.form_id = pp.form_id
            LEFT JOIN ({$billingSources['public_subquery']}) bpub ON bpub.form_id = pp.form_id
            WHERE ce.examen_nombre IS NOT NULL
              AND TRIM(ce.examen_nombre) <> ''
            GROUP BY
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.examen_codigo,
                ce.examen_nombre,
                {$fechaSolicitudExpr}";
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mergeImagenBillingEvidence(array $row): array
    {
        $realEvidence = $this->hasImagenBillingSourceEvidence($row, 'real');
        $publicEvidence = $this->hasImagenBillingSourceEvidence($row, 'public');
        $source = $realEvidence ? 'real' : ($publicEvidence ? 'public' : null);

        $row['facturado'] = $source !== null ? 1 : 0;
        $row['billing_id'] = $source !== null ? trim((string) ($row[$source . '_billing_id'] ?? '')) : null;
        $row['fecha_facturacion'] = $source !== null ? ($row[$source . '_fecha_facturacion'] ?? null) : null;
        $row['fecha_atencion'] = $source === 'real' ? ($row['real_fecha_atencion'] ?? null) : null;
        $row['total_produccion'] = $source !== null ? (float) ($row[$source . '_total_produccion'] ?? 0) : 0.0;
        $row['procedimientos_facturados'] = $source !== null ? (int) ($row[$source . '_procedimientos_facturados'] ?? 0) : 0;
        $row['numero_factura'] = $source === 'real' ? trim((string) ($row['real_numero_factura'] ?? '')) : null;
        $row['factura_id'] = $source === 'real' ? trim((string) ($row['real_factura_id'] ?? '')) : null;
        $row['estado_facturacion_raw'] = $source === 'real'
            ? trim((string) ($row['real_estado_facturacion_raw'] ?? ''))
            : null;
        $row['billing_source'] = $source;

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hasImagenBillingSourceEvidence(array $row, string $prefix): bool
    {
        $billingId = trim((string) ($row[$prefix . '_billing_id'] ?? ''));
        $fechaFacturacion = trim((string) ($row[$prefix . '_fecha_facturacion'] ?? ''));
        $procedimientosFacturados = (int) ($row[$prefix . '_procedimientos_facturados'] ?? 0);
        $totalProduccion = (float) ($row[$prefix . '_total_produccion'] ?? 0);

        if ($prefix === 'real') {
            $numeroFactura = trim((string) ($row['real_numero_factura'] ?? ''));
            $facturaId = trim((string) ($row['real_factura_id'] ?? ''));
            $fechaAtencion = trim((string) ($row['real_fecha_atencion'] ?? ''));

            return $billingId !== ''
                || $numeroFactura !== ''
                || $facturaId !== ''
                || $fechaFacturacion !== ''
                || $fechaAtencion !== ''
                || $procedimientosFacturados > 0
                || $totalProduccion > 0;
        }

        return $billingId !== ''
            || $fechaFacturacion !== ''
            || $procedimientosFacturados > 0
            || $totalProduccion > 0;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function getImagenesAfiliacionOptions(): array
    {
        return $this->afiliacionDimensions->getEmpresaOptions('Todas las empresas');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function getImagenesAfiliacionCategoriaOptions(): array
    {
        return $this->afiliacionDimensions->getCategoriaOptions('Todas las categorías');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function getImagenesSeguroOptions(string $empresaFilter = ''): array
    {
        return $this->afiliacionDimensions->getSeguroOptions('Todos los seguros', $empresaFilter);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorateImagenRow(array $row): array
    {
        $estadoAgenda = trim((string) ($row['estado_agenda'] ?? ''));
        $estadoRealizacion = $this->resolveImagenRealizationState($row);
        $estadoFacturacion = $this->resolveImagenBillingState(
            $estadoRealizacion,
            (int) ($row['facturado'] ?? 0) === 1,
            (string) ($row['estado_facturacion_raw'] ?? '')
        );
        $estadoInforme = $this->resolveImagenInformeState($row);

        $row['estado_realizacion'] = $estadoRealizacion;
        $row['estado_facturacion'] = $estadoFacturacion;
        $row['estado_informe'] = $estadoInforme;
        $row['informado'] = $estadoInforme === 'INFORMADA';
        $row['pendiente_informar'] = $estadoInforme === 'PENDIENTE_INFORMAR';
        $row['cita_generada'] = $this->isImagenCitaGeneradaEstado($estadoAgenda);
        $row['examen_realizado'] = $this->isImagenEstadoRealizado($estadoRealizacion);
        $row['nas_has_files'] = ((int) ($row['nas_has_files'] ?? 0) === 1) || ((int) ($row['nas_files_count'] ?? 0) > 0) ? 1 : 0;

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveImagenRealizationState(array $row): string
    {
        $facturado = (int) ($row['facturado'] ?? 0) === 1;
        $nasHasFiles = (int) ($row['nas_has_files'] ?? 0) === 1 || (int) ($row['nas_files_count'] ?? 0) > 0;
        $informado = !empty($row['informe_id']) || (int) ($row['informes_total'] ?? 0) > 0;
        $estadoAgenda = trim((string) ($row['estado_agenda'] ?? ''));

        if ($facturado) {
            return 'FACTURADA';
        }
        if ($nasHasFiles) {
            return 'REALIZADA_CON_ARCHIVOS';
        }
        if ($informado) {
            return 'REALIZADA_INFORMADA';
        }
        if ($this->isImagenEstadoAtendidoOPagado($estadoAgenda)) {
            return 'REALIZADA_AGENDA_CERRADA';
        }
        if ($this->isImagenEstadoCancelado($estadoAgenda)) {
            return 'CANCELADA';
        }
        if ($this->isImagenEstadoAusente($estadoAgenda)) {
            return 'AUSENTE';
        }
        if ($this->isImagenAgendaVencidaSinCierre($row)) {
            return 'AUSENTE';
        }

        return 'SIN_CIERRE_OPERATIVO';
    }

    private function resolveImagenBillingState(string $estadoRealizacion, bool $facturado, string $estadoFacturacionRaw = ''): string
    {
        $estadoFacturacionRawNorm = $this->normalizarTexto($estadoFacturacionRaw);

        if ($estadoFacturacionRawNorm !== '') {
            if (str_contains($estadoFacturacionRawNorm, 'cancel') || str_contains($estadoFacturacionRawNorm, 'anul')) {
                return 'CANCELADA';
            }

            if (
                str_contains($estadoFacturacionRawNorm, 'pend')
                || str_contains($estadoFacturacionRawNorm, 'credito')
                || str_contains($estadoFacturacionRawNorm, 'cartera')
            ) {
                return 'PENDIENTE_PAGO';
            }
        }

        if ($facturado || $estadoRealizacion === 'FACTURADA') {
            return 'FACTURADA';
        }
        if (in_array($estadoRealizacion, ['REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA', 'REALIZADA_AGENDA_CERRADA'], true)) {
            return 'PENDIENTE_FACTURAR';
        }

        return 'SIN_FACTURACION';
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveImagenInformeState(array $row): string
    {
        $informado = !empty($row['informe_id']) || (int) ($row['informes_total'] ?? 0) > 0;
        $nasHasFiles = (int) ($row['nas_has_files'] ?? 0) === 1 || (int) ($row['nas_files_count'] ?? 0) > 0;

        if ($informado) {
            return 'INFORMADA';
        }
        if ($nasHasFiles) {
            return 'PENDIENTE_INFORMAR';
        }

        return 'SIN_EVIDENCIA_TECNICA';
    }

    private function isImagenEstadoRealizado(string $estadoRealizacion): bool
    {
        return in_array($estadoRealizacion, ['FACTURADA', 'REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA', 'REALIZADA_AGENDA_CERRADA'], true);
    }

    private function isImagenCitaGeneradaEstado(string $estado): bool
    {
        $estadoNorm = $this->normalizarTexto($estado);
        if ($estadoNorm === '') {
            return false;
        }

        foreach (['cancel', 'anul', 'no show', 'no asist'] as $keyword) {
            if (str_contains($estadoNorm, $keyword)) {
                return false;
            }
        }

        return true;
    }

    private function isImagenEstadoCancelado(string $estado): bool
    {
        $normalized = $this->normalizarTexto($estado);

        return $normalized !== '' && (str_contains($normalized, 'cancel') || str_contains($normalized, 'anul'));
    }

    private function isImagenEstadoAusente(string $estado): bool
    {
        $normalized = $this->normalizarTexto($estado);
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'ausent')
            || str_contains($normalized, 'no show')
            || str_contains($normalized, 'no asist')
            || str_contains($normalized, 'inasistent');
    }

    private function isImagenEstadoAtendidoOPagado(string $estado): bool
    {
        $normalized = $this->normalizarTexto($estado);
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'atendid')
            || str_contains($normalized, 'pagad');
    }

    /**
     * @param array<string,mixed> $row
     */
    private function isImagenAgendaVencidaSinCierre(array $row): bool
    {
        $estadoAgenda = trim((string) ($row['estado_agenda'] ?? ''));
        if (!$this->isImagenCitaGeneradaEstado($estadoAgenda)) {
            return false;
        }

        $fechaExamenRaw = trim((string) ($row['fecha_examen'] ?? ''));
        if ($fechaExamenRaw === '') {
            return false;
        }

        $fechaExamenTs = strtotime($fechaExamenRaw);
        if ($fechaExamenTs === false) {
            return false;
        }

        $today = new DateTimeImmutable('today');

        return $fechaExamenTs < $today->getTimestamp();
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $texto = preg_replace('/\p{Mn}/u', '', $normalized) ?? $texto;
            }
        }

        $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
        $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function parseProcedimientoImagen(?string $raw): array
    {
        $texto = trim((string) $raw);
        $ojo = '';

        if ($texto !== '' && preg_match('/\s-\s(AMBOS OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/i', $texto, $match)) {
            $ojo = strtoupper(trim((string) ($match[1] ?? '')));
            $texto = trim(substr($texto, 0, -strlen((string) ($match[0] ?? ''))));
        }

        if ($texto !== '') {
            $partes = preg_split('/\s-\s/', $texto) ?: [];
            if (isset($partes[0]) && strcasecmp(trim((string) $partes[0]), 'IMAGENES') === 0) {
                array_shift($partes);
            }
            if (isset($partes[0]) && preg_match('/^IMA[-_]/i', trim((string) $partes[0]))) {
                array_shift($partes);
            }
            $texto = trim(implode(' - ', array_map(static fn($item): string => trim((string) $item), $partes)));
        }

        $ojoMap = [
            'OD' => 'Derecho',
            'OI' => 'Izquierdo',
            'AO' => 'Ambos ojos',
            'DERECHO' => 'Derecho',
            'IZQUIERDO' => 'Izquierdo',
            'AMBOS OJOS' => 'Ambos ojos',
        ];

        return [
            'texto' => $texto,
            'ojo' => $ojoMap[$ojo] ?? $ojo,
        ];
    }

    private function extraerCodigoTarifario(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        if (preg_match('/\b(\d{6})\b/', $texto, $match) === 1) {
            return trim((string) ($match[1] ?? ''));
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerTarifarioPorCodigo(string $codigo, ?string $afiliacionCategoria = null): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $afiliacionCategoria = $this->normalizarTexto((string) $afiliacionCategoria);

        if ($afiliacionCategoria === 'publico') {
            $tarifaPublica = $this->obtenerTarifario2014PorCodigo($codigo);
            if (is_array($tarifaPublica) && $tarifaPublica !== []) {
                return $tarifaPublica;
            }
        }

        if (
            $this->tableExists('tarifario_procedimientos')
            && $this->columnExists('tarifario_procedimientos', 'codigo')
            && $this->columnExists('tarifario_procedimientos', 'descripcion')
        ) {
            $stmt = $this->db->prepare(
                'SELECT codigo, descripcion, short_description
                 FROM tarifario_procedimientos
                 WHERE codigo = :codigo
                 LIMIT 1'
            );
            $stmt->execute([':codigo' => $codigo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && $row !== []) {
                return $row;
            }
        }

        return $this->obtenerTarifario2014PorCodigo($codigo);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function obtenerTarifario2014PorCodigo(string $codigo): ?array
    {
        if (
            !$this->tableExists('tarifario_2014')
            || !$this->columnExists('tarifario_2014', 'codigo')
            || !$this->columnExists('tarifario_2014', 'descripcion')
        ) {
            return null;
        }

        $select = $this->columnExists('tarifario_2014', 'valor_facturar_nivel3')
            ? 'codigo, descripcion, short_description, valor_facturar_nivel3'
            : 'codigo, descripcion, short_description';
        $stmt = $this->db->prepare(
            "SELECT {$select}
             FROM tarifario_2014
             WHERE codigo = :codigo
             LIMIT 1"
        );
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && $row !== []) {
            return $row;
        }

        $codigoSinCeros = ltrim($codigo, '0');
        if ($codigoSinCeros === '' || $codigoSinCeros === $codigo) {
            return null;
        }

        $stmt->execute([':codigo' => $codigoSinCeros]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function normalizarDetalleEstudio012A(string $detalle, string $tarifaDesc): string
    {
        $detalle = trim(preg_replace('/\s+/', ' ', $detalle) ?? '');
        $tarifaDesc = trim(preg_replace('/\s+/', ' ', $tarifaDesc) ?? '');
        if ($detalle === '') {
            return '';
        }

        $detalleNorm = $this->normalizarTexto($detalle);
        $tarifaNorm = $this->normalizarTexto($tarifaDesc);
        if ($detalleNorm !== '' && $detalleNorm === $tarifaNorm) {
            return '';
        }

        $detalle = preg_replace('/^OCT\s+/iu', '', $detalle) ?? $detalle;

        return trim($detalle, " -\t\n\r\0\x0B");
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function buildImagenesDashboardDetailRows(array $rows): array
    {
        $output = [];
        $tarifarioCache = [];

        foreach ($rows as $row) {
            $tipoRaw = trim((string) ($row['tipo_examen'] ?? ''));
            $parsedTipo = $this->parseProcedimientoImagen($tipoRaw);
            $tipoTexto = trim((string) ($parsedTipo['texto'] ?? ''));
            $codigo = (string) ($this->extraerCodigoTarifario($tipoTexto !== '' ? $tipoTexto : $tipoRaw) ?? '');
            $nombreTarifario = '';

            if ($codigo !== '') {
                $tarifaCacheKey = $codigo . '|' . trim((string) ($row['afiliacion_categoria'] ?? ''));
                if (!isset($tarifarioCache[$tarifaCacheKey])) {
                    $tarifarioCache[$tarifaCacheKey] = $this->obtenerTarifarioPorCodigo(
                        $codigo,
                        (string) ($row['afiliacion_categoria'] ?? '')
                    );
                }
                $tarifa = $tarifarioCache[$tarifaCacheKey];
                if (is_array($tarifa) && $tarifa !== []) {
                    $nombreTarifario = trim((string) ($tarifa['descripcion'] ?? ($tarifa['short_description'] ?? '')));
                }
            }

            $detalle = $tipoTexto !== '' ? $tipoTexto : $tipoRaw;
            if ($codigo !== '') {
                $detalle = trim((string) (preg_replace('/\b' . preg_quote($codigo, '/') . '\b\s*[-:]?\s*/iu', '', $detalle) ?? $detalle));
            }
            $detalle = trim($detalle, " -\t\n\r\0\x0B");

            if ($codigo !== '' && $nombreTarifario !== '') {
                $examen = $codigo . ' - ' . $nombreTarifario;
                $suffix = $this->normalizarDetalleEstudio012A($detalle, $nombreTarifario);
                if ($suffix !== '') {
                    $examen .= ' - ' . $suffix;
                }
            } elseif ($codigo !== '') {
                $examen = $codigo . ' - ' . ($detalle !== '' ? $detalle : 'SIN DETALLE');
            } else {
                $examen = $detalle !== '' ? $detalle : 'SIN CÓDIGO';
            }

            $ojo = trim((string) ($parsedTipo['ojo'] ?? ''));
            if ($ojo !== '') {
                $examen .= ' - ' . $ojo;
            }

            $estadoAgenda = trim((string) ($row['estado_agenda'] ?? ''));
            $estadoRealizacion = (string) ($row['estado_realizacion'] ?? $this->resolveImagenRealizationState($row));
            $estadoFacturacion = (string) ($row['estado_facturacion'] ?? $this->resolveImagenBillingState(
                $estadoRealizacion,
                (int) ($row['facturado'] ?? 0) === 1,
                (string) ($row['estado_facturacion_raw'] ?? '')
            ));
            $estadoInforme = (string) ($row['estado_informe'] ?? $this->resolveImagenInformeState($row));
            $informado = $estadoInforme === 'INFORMADA';
            $pendienteInformar = $estadoInforme === 'PENDIENTE_INFORMAR';
            $totalProduccion = round((float) ($row['total_produccion'] ?? 0), 2);
            $facturado = (int) ($row['facturado'] ?? 0) === 1;
            $afiliacionCategoriaKey = trim((string) ($row['afiliacion_categoria'] ?? ''));
            $esPendienteFacturar = $estadoFacturacion === 'PENDIENTE_FACTURAR';
            $montoPendienteEstimado = $esPendienteFacturar ? $this->resolveImagenTarifaPendiente($codigo, $row, $tarifa ?? null) : 0.0;
            $sinTarifaPublica = $esPendienteFacturar && $afiliacionCategoriaKey === 'publico' && $montoPendienteEstimado <= 0;

            $output[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'form_id' => trim((string) ($row['form_id'] ?? '')),
                'fecha_examen' => $this->formatDashboardDate((string) ($row['fecha_examen'] ?? '')),
                'hc_number' => trim((string) ($row['hc_number'] ?? '')),
                'paciente' => trim((string) ($row['full_name'] ?? '')),
                'empresa_seguro' => trim((string) ($row['empresa_seguro'] ?? '')),
                'afiliacion' => trim((string) ($row['afiliacion'] ?? '')),
                'afiliacion_categoria' => $this->formatCategoriaLabel($afiliacionCategoriaKey),
                'afiliacion_categoria_key' => $afiliacionCategoriaKey,
                'sede' => trim((string) ($row['sede'] ?? '')),
                'estado_agenda' => $estadoAgenda,
                'cita_generada' => $this->isImagenCitaGeneradaEstado($estadoAgenda),
                'examen_realizado' => $this->isImagenEstadoRealizado($estadoRealizacion),
                'estado_realizacion' => $estadoRealizacion,
                'estado_facturacion' => $estadoFacturacion,
                'estado_facturacion_raw' => trim((string) ($row['estado_facturacion_raw'] ?? '')),
                'estado_informe' => $estadoInforme,
                'informado' => $informado,
                'pendiente_informar' => $pendienteInformar,
                'nas_has_files' => ((int) ($row['nas_has_files'] ?? 0) === 1) || ((int) ($row['nas_files_count'] ?? 0) > 0),
                'nas_files_count' => (int) ($row['nas_files_count'] ?? 0),
                'nas_scan_status' => trim((string) ($row['nas_scan_status'] ?? '')),
                'nas_last_scanned_at' => $this->formatDashboardDate((string) ($row['nas_last_scanned_at'] ?? '')),
                'facturado' => $facturado,
                'billing_source' => trim((string) ($row['billing_source'] ?? '')),
                'billing_id' => trim((string) ($row['billing_id'] ?? '')),
                'numero_factura' => trim((string) ($row['numero_factura'] ?? '')),
                'factura_id' => trim((string) ($row['factura_id'] ?? '')),
                'produccion' => $totalProduccion,
                'procedimientos_facturados' => (int) ($row['procedimientos_facturados'] ?? 0),
                'fecha_facturacion' => $this->formatDashboardDate((string) ($row['fecha_facturacion'] ?? '')),
                'monto_pendiente_estimado' => round($montoPendienteEstimado, 2),
                'sin_tarifa_publica' => $sinTarifaPublica,
                'codigo' => $codigo,
                'examen' => $examen,
            ];
        }

        return $output;
    }

    /**
     * @param array<string,string> $filters
     * @return array<int,array<string,mixed>>
     */
    private function buildImagenesDashboardSolicitudRows(array $filters): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return [];
        }

        $flowSubquery = $this->buildImagenesSolicitudFlowSubquery();
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(flow.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $empresaSeguroExpr = $this->afiliacionLabelExpr($rawAfiliacionExpr, 'iacm_solicitudes_export');
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr, 'iacm_solicitudes_export');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm_solicitudes_export');
        $seguroKeyExpr = $this->seguroKeyExpr($rawAfiliacionExpr, 'iacm_solicitudes_export');
        $sedeExpr = $this->imagenesSedeExprFromFields('flow.sede_departamento', 'flow.id_sede');

        $sql = "SELECT
                flow.solicitud_id,
                flow.hc_number,
                flow.solicitud_form_id,
                TRIM(COALESCE(flow.examen_codigo, '')) AS examen_codigo,
                TRIM(COALESCE(flow.examen_nombre, '')) AS examen_nombre,
                flow.fecha_solicitud,
                flow.fecha_agenda,
                flow.fecha_realizacion,
                COALESCE(flow.has_agenda, 0) AS has_agenda,
                COALESCE(flow.realizada, 0) AS realizada,
                COALESCE(flow.informada, 0) AS informada,
                COALESCE(flow.facturada, 0) AS facturada,
                COALESCE(flow.cancelada, 0) AS cancelada,
                COALESCE(flow.ausente, 0) AS ausente,
                COALESCE(NULLIF(TRIM(flow.doctor_solicitante), ''), 'Sin asignar') AS doctor_solicitante,
                TRIM(COALESCE(flow.estados_agenda, '')) AS estados_agenda,
                TRIM(COALESCE(flow.procedimientos_match, '')) AS procedimientos_match,
                {$empresaSeguroExpr} AS empresa_seguro,
                {$rawAfiliacionExpr} AS afiliacion,
                {$categoriaContext['expr']} AS afiliacion_categoria,
                {$sedeExpr} AS sede,
                CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) AS paciente
            FROM ({$flowSubquery}) flow
            LEFT JOIN patient_data pd ON pd.hc_number = flow.hc_number
            {$categoriaContext['join']}
            WHERE flow.examen_nombre IS NOT NULL
              AND TRIM(flow.examen_nombre) <> ''";

        $params = [];

        $fechaInicio = trim((string) ($filters['fecha_inicio'] ?? ''));
        if ($fechaInicio !== '') {
            $sql .= ' AND flow.fecha_solicitud >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = trim((string) ($filters['fecha_fin'] ?? ''));
        if ($fechaFin !== '') {
            $sql .= ' AND flow.fecha_solicitud <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin;
        }

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        if ($afiliacionFilter !== '') {
            $sql .= " AND {$afiliacionKeyExpr} = :afiliacion_filter_match";
            $params[':afiliacion_filter_match'] = $afiliacionFilter;
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $sql .= " AND {$categoriaContext['expr']} = :afiliacion_categoria_filter_match";
            $params[':afiliacion_categoria_filter_match'] = $afiliacionCategoriaFilter;
        }

        $seguroFilter = $this->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $sql .= " AND {$seguroKeyExpr} = :seguro_filter_match";
            $params[':seguro_filter_match'] = $seguroFilter;
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sql .= " AND {$sedeExpr} = :sede_filter_match";
            $params[':sede_filter_match'] = $sedeFilter;
        }

        $tipoExamen = trim((string) ($filters['tipo_examen'] ?? ''));
        if ($tipoExamen !== '') {
            $sql .= ' AND (
                    flow.examen_nombre LIKE :tipo_examen
                    OR flow.examen_codigo LIKE :tipo_examen
                    OR TRIM(COALESCE(flow.procedimientos_match, "")) LIKE :tipo_examen
                )';
            $params[':tipo_examen'] = '%' . $tipoExamen . '%';
        }

        $paciente = trim((string) ($filters['paciente'] ?? ''));
        if ($paciente !== '') {
            $sql .= " AND (
                    flow.hc_number LIKE :paciente_hc
                    OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente_nombre
                    OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente_nombre_alt
                )";
            $pacienteLike = '%' . $paciente . '%';
            $params[':paciente_hc'] = $pacienteLike;
            $params[':paciente_nombre'] = $pacienteLike;
            $params[':paciente_nombre_alt'] = $pacienteLike;
        }

        $estadoAgenda = trim((string) ($filters['estado_agenda'] ?? ''));
        if ($estadoAgenda !== '') {
            $sql .= ' AND TRIM(COALESCE(flow.estados_agenda, "")) LIKE :estado_agenda_like';
            $params[':estado_agenda_like'] = '%' . $estadoAgenda . '%';
        }

        $sql .= ' ORDER BY flow.fecha_solicitud DESC, flow.solicitud_id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $today = new DateTimeImmutable('today');
        $todayTs = $today->getTimestamp();
        $output = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasAgenda = (int) ($row['has_agenda'] ?? 0) === 1;
            $realizada = (int) ($row['realizada'] ?? 0) === 1;
            $cancelada = (int) ($row['cancelada'] ?? 0) === 1;
            $ausenteExplicita = (int) ($row['ausente'] ?? 0) === 1;
            $fechaAgendaRaw = trim((string) ($row['fecha_agenda'] ?? ''));
            $fechaAgendaTs = $fechaAgendaRaw !== '' ? strtotime($fechaAgendaRaw) : false;
            $agendaVencidaSinCierre = $hasAgenda && !$realizada && !$cancelada && $fechaAgendaTs !== false && $fechaAgendaTs < $todayTs;
            $ausenteCohorte = !$realizada && !$cancelada && ($ausenteExplicita || $agendaVencidaSinCierre);
            $pendienteVigente = !$realizada && !$cancelada && (
                !$hasAgenda
                || (!$ausenteCohorte && ($fechaAgendaRaw === '' || $fechaAgendaTs === false || $fechaAgendaTs >= $todayTs))
            );

            $output[] = [
                'solicitud_id' => (int) ($row['solicitud_id'] ?? 0),
                'fecha_solicitud' => $this->formatDashboardDate((string) ($row['fecha_solicitud'] ?? '')),
                'hc_number' => trim((string) ($row['hc_number'] ?? '')),
                'paciente' => trim((string) ($row['paciente'] ?? '')),
                'doctor_solicitante' => trim((string) ($row['doctor_solicitante'] ?? 'Sin asignar')),
                'solicitud_form_id' => trim((string) ($row['solicitud_form_id'] ?? '')),
                'examen_codigo' => trim((string) ($row['examen_codigo'] ?? '')),
                'examen_nombre' => trim((string) ($row['examen_nombre'] ?? '')),
                'empresa_seguro' => trim((string) ($row['empresa_seguro'] ?? '')),
                'afiliacion' => trim((string) ($row['afiliacion'] ?? '')),
                'afiliacion_categoria' => $this->formatCategoriaLabel((string) ($row['afiliacion_categoria'] ?? '')),
                'sede' => trim((string) ($row['sede'] ?? '')),
                'agendada' => $hasAgenda,
                'fecha_agenda' => $this->formatDashboardDate((string) ($row['fecha_agenda'] ?? '')),
                'realizada' => $realizada,
                'fecha_realizacion' => $this->formatDashboardDate((string) ($row['fecha_realizacion'] ?? '')),
                'informada' => (int) ($row['informada'] ?? 0) === 1,
                'facturada' => (int) ($row['facturada'] ?? 0) === 1,
                'cancelada' => $cancelada,
                'ausente_cohorte' => $ausenteCohorte,
                'pendiente_vigente' => $pendienteVigente,
                'estados_agenda' => trim((string) ($row['estados_agenda'] ?? '')),
                'procedimientos_match' => trim((string) ($row['procedimientos_match'] ?? '')),
            ];
        }

        return $output;
    }

    /**
     * @param array<string,mixed> $dashboard
     * @param array<int,array<string,mixed>> $detailRows
     * @param array<int,array{label:string,value:string}> $filtersSummary
     * @param array<string,string> $filters
     * @return array<string,mixed>
     */
    private function buildImagenesDashboardReport(
        array $dashboard,
        array $detailRows,
        array $filtersSummary,
        array $filters
    ): array {
        $cards = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
        $meta = is_array($dashboard['meta'] ?? null) ? $dashboard['meta'] : [];
        $charts = is_array($dashboard['charts'] ?? null) ? $dashboard['charts'] : [];
        $total = count($detailRows);
        $solicitudesTotal = (int) ($meta['solicitudes_total'] ?? 0);
        $solicitudesAgendadas = (int) ($meta['solicitudes_agendadas'] ?? 0);
        $solicitudesRealizadas = (int) ($meta['solicitudes_realizadas'] ?? 0);
        $solicitudesAgendadasAlCorte = (int) ($meta['solicitudes_agendadas_al_corte'] ?? 0);
        $solicitudesRealizadasAlCorte = (int) ($meta['solicitudes_realizadas_al_corte'] ?? 0);
        $solicitudesRealizadasPostCorte = (int) ($meta['solicitudes_realizadas_post_corte'] ?? 0);
        $solicitudesSinAgenda = (int) ($meta['solicitudes_sin_agenda'] ?? 0);
        $solicitudesSinAgendaMontoEstimado = (float) ($meta['solicitudes_sin_agenda_monto_estimado'] ?? 0);
        $solicitudesSinAgendaSinTarifa = (int) ($meta['solicitudes_sin_agenda_sin_tarifa'] ?? 0);
        $solicitudesAgendadasPendientes = (int) ($meta['solicitudes_agendadas_pendientes'] ?? 0);
        $solicitudesPendientesVigentes = (int) ($meta['solicitudes_pendientes_vigentes'] ?? 0);
        $conversionSolicitudRealizacionPct = ($meta['conversion_solicitud_realizacion_pct'] ?? null) !== null
            ? (float) $meta['conversion_solicitud_realizacion_pct']
            : null;
        $cumplimientoRealizacionAlCortePct = ($meta['cumplimiento_realizacion_al_corte_pct'] ?? null) !== null
            ? (float) $meta['cumplimiento_realizacion_al_corte_pct']
            : null;
        $cumplimientoRealizacionAcumuladoPct = ($meta['cumplimiento_realizacion_acumulado_pct'] ?? null) !== null
            ? (float) $meta['cumplimiento_realizacion_acumulado_pct']
            : null;
        $atendidos = (int) ($this->reportCardValue($cards, 'Atendidos') ?? 0);
        $informadas = (int) ($this->reportCardValue($cards, 'Informadas') ?? 0);
        $facturados = (int) ($this->reportCardValue($cards, 'Facturados') ?? 0);
        $pendientesFacturar = (int) ($meta['pendientes_facturar'] ?? 0);
        $pendientesFacturarPublico = (int) ($meta['pendientes_facturar_publico'] ?? 0);
        $pendientesFacturarPrivado = (int) ($meta['pendientes_facturar_privado'] ?? 0);
        $pendientesFacturarOtros = (int) ($meta['pendientes_facturar_otros'] ?? 0);
        $montoPendienteEstimado = (float) ($meta['monto_pendiente_estimado'] ?? 0);
        $montoPendienteEstimadoPublico = (float) ($meta['monto_pendiente_estimado_publico'] ?? 0);
        $pendientesFacturarSinTarifa = (int) ($meta['pendientes_facturar_sin_tarifa'] ?? 0);
        $pendientesFacturarPublicoSinTarifa = (int) ($meta['pendientes_facturar_publico_sin_tarifa'] ?? 0);
        $produccionFacturada = (float) ($meta['produccion_facturada'] ?? 0);
        $produccionFacturadaPublico = (float) ($meta['produccion_facturada_publico'] ?? 0);
        $produccionFacturadaPrivado = (float) ($meta['produccion_facturada_privado'] ?? 0);
        $sla48 = trim((string) ($this->reportCardText($cards, 'SLA informe <= 48h') ?? '—'));
        $cumplimientoCita = trim((string) ($this->reportCardText($cards, 'Cumplimiento cita->realización') ?? '—'));
        $tatPromedio = ($meta['tat_promedio_horas'] ?? null) !== null ? number_format((float) $meta['tat_promedio_horas'], 2) . ' h' : '—';
        $tatMediana = ($meta['tat_mediana_horas'] ?? null) !== null ? number_format((float) $meta['tat_mediana_horas'], 2) . ' h' : '—';
        $tatP90 = ($meta['tat_p90_horas'] ?? null) !== null ? number_format((float) $meta['tat_p90_horas'], 2) . ' h' : '—';
        $canceladasOperativas = (int) ($meta['canceladas_operativas'] ?? $this->reportCardValue($cards, 'Cancelados del periodo') ?? 0);
        $ausentesOperativos = (int) ($meta['ausentes_operativos'] ?? 0);
        $sinCierre = (int) ($meta['pendientes_operativos'] ?? $this->reportCardValue($cards, 'Pendientes operativos') ?? 0);
        $insuranceBreakdown = is_array($meta['insurance_breakdown'] ?? null) ? $meta['insurance_breakdown'] : [];
        $insuranceBreakdownTitle = trim((string) ($insuranceBreakdown['title'] ?? 'Empresas de seguro'));
        $insuranceBreakdownItemLabel = trim((string) ($insuranceBreakdown['item_label'] ?? 'Empresa de seguro'));
        $traficoLabels = is_array($charts['trafico_dia_semana']['labels'] ?? null) ? $charts['trafico_dia_semana']['labels'] : [];
        $traficoValues = is_array($charts['trafico_dia_semana']['values'] ?? null) ? $charts['trafico_dia_semana']['values'] : [];
        $mixLabels = is_array($charts['mix_codigos']['labels'] ?? null) ? $charts['mix_codigos']['labels'] : [];
        $mixValues = is_array($charts['mix_codigos']['values'] ?? null) ? $charts['mix_codigos']['values'] : [];
        $doctorSolicitanteLabels = is_array($charts['top_doctores_solicitantes']['labels'] ?? null) ? $charts['top_doctores_solicitantes']['labels'] : [];
        $doctorSolicitanteValues = is_array($charts['top_doctores_solicitantes']['values'] ?? null) ? $charts['top_doctores_solicitantes']['values'] : [];
        $topExamenesSolicitadosLabels = is_array($charts['top_examenes_solicitados']['labels'] ?? null) ? $charts['top_examenes_solicitados']['labels'] : [];
        $topExamenesSolicitadosValues = is_array($charts['top_examenes_solicitados']['values'] ?? null) ? $charts['top_examenes_solicitados']['values'] : [];
        $insuranceLabels = is_array($charts['analisis_seguro']['labels'] ?? null) ? $charts['analisis_seguro']['labels'] : [];
        $insuranceValues = is_array($charts['analisis_seguro']['values'] ?? null) ? $charts['analisis_seguro']['values'] : [];
        $diaPico = '—';
        $traficoPico = 0;
        foreach ($traficoValues as $index => $value) {
            $totalDia = (int) $value;
            if ($totalDia > $traficoPico) {
                $traficoPico = $totalDia;
                $diaPico = trim((string) ($traficoLabels[$index] ?? '—'));
            }
        }

        $hallazgos = [];
        if ($solicitudesTotal > 0) {
            $hallazgos[] = sprintf(
                'Se registraron %s solicitudes; %s quedaron agendadas al corte, %s se realizaron al corte (%s%%) y %s se realizaron después del corte.',
                number_format($solicitudesTotal),
                number_format($solicitudesAgendadasAlCorte),
                number_format($solicitudesRealizadasAlCorte),
                number_format($cumplimientoRealizacionAlCortePct ?? 0, 1),
                number_format($solicitudesRealizadasPostCorte)
            );
            if ($solicitudesPendientesVigentes > 0 || $solicitudesSinAgenda > 0) {
                $hallazgos[] = sprintf(
                    'A la fecha siguen abiertas %s solicitudes de esa cohorte; %s no tienen agenda y %s conservan agenda vigente sin cierre técnico.',
                    number_format($solicitudesPendientesVigentes),
                    number_format($solicitudesSinAgenda),
                    number_format($solicitudesAgendadasPendientes)
                );
            }
            if (($meta['solicitudes_ausentes'] ?? 0) > 0) {
                $hallazgos[] = sprintf(
                    'Se detectan %s ausentes de cohorte, agrupando estados explícitos y agendas vencidas sin evidencia de realización.',
                    number_format((int) ($meta['solicitudes_ausentes'] ?? 0))
                );
            }
            if ($solicitudesSinAgendaMontoEstimado > 0) {
                $hallazgos[] = sprintf(
                    'La pérdida económica estimada por solicitudes aún no agendadas asciende a $%s.',
                    number_format($solicitudesSinAgendaMontoEstimado, 2)
                );
            }
        }
        $hallazgos[] = sprintf(
            'En la operación del periodo se analizaron %s agendas; %s atendidos, %s informados y %s facturados.',
            number_format($total),
            number_format($atendidos),
            number_format($informadas),
            number_format($facturados)
        );
        if ($total > 0) {
            $hallazgos[] = sprintf(
                'La operación tuvo %s agendas y logró un cumplimiento cita->realización de %s.',
                number_format($total),
                $cumplimientoCita
            );
        }
        if ($pendientesFacturar > 0) {
            $desglosePendiente = [];
            if ($pendientesFacturarPublico > 0) {
                $desglosePendiente[] = number_format($pendientesFacturarPublico) . ' públicos';
            }
            if ($pendientesFacturarPrivado > 0) {
                $desglosePendiente[] = number_format($pendientesFacturarPrivado) . ' privados';
            }
            if ($pendientesFacturarOtros > 0) {
                $desglosePendiente[] = number_format($pendientesFacturarOtros) . ' particulares/otros';
            }
            $hallazgos[] = sprintf(
                'El backlog atendido sin facturar es de %s casos%s.',
                number_format($pendientesFacturar),
                $desglosePendiente !== [] ? (': ' . implode(', ', $desglosePendiente)) : ''
            );
        }
        if ($montoPendienteEstimado > 0) {
            $hallazgos[] = sprintf(
                'El pendiente estimado valorizado asciende a $%s para estudios realizados sin billing real.',
                number_format($montoPendienteEstimado, 2)
            );
        }
        if ($pendientesFacturarSinTarifa > 0) {
            $hallazgos[] = sprintf(
                'Hay %s casos pendientes de facturar sin tarifa resoluble por código/categoría; requieren revisión tarifaria.',
                number_format($pendientesFacturarSinTarifa)
            );
        }
        if ($sla48 !== '—') {
            $hallazgos[] = sprintf('El SLA de informe <= 48h se ubica en %s con TAT promedio de %s.', $sla48, $tatPromedio);
        }

        $methodology = [
            'El reporte separa dos universos: cohorte de solicitudes y operación del periodo.',
            'La cohorte parte de consulta_examenes y cruza contra procedimiento_proyectado por hc_number + examen_codigo contenido en el procedimiento; form_id queda como apoyo.',
            'El filtro de fechas aplica a la solicitud; el cumplimiento al corte usa fecha_fin como fecha de corte de cohorte.',
            'Si una solicitud del rango se realiza después de fecha_fin, se clasifica como realizada posterior al corte y no mejora el cumplimiento al corte.',
            'La operación del periodo considera procedimientos de IMAGENES dentro del rango filtrado con estado de agenda no vacío, aunque la solicitud original sea anterior.',
            'La realización se clasifica con evidencia operativa, informes registrados y presencia de archivos en NAS o índice NAS.',
            'Las ausencias agrupan estados explícitos de ausente y agendas ya vencidas sin evidencia de realización.',
            'La facturación se resuelve con evidencia combinada de billing_facturacion_real y billing_main + billing_procedimientos.',
            'El pendiente estimado solo aplica a estudios realizados sin billing real; no incluye registros ya facturados en cartera o pendiente de pago.',
            'La valorización del backlog usa código de examen + categoría/afiliación cuando existe tarifa resoluble; los casos sin tarifa quedan identificados para auditoría.',
        ];

        $executiveKpis = [
            ['label' => 'Solicitudes de exámenes', 'value' => $this->reportCardText($cards, 'Solicitudes de exámenes') ?? '0', 'note' => 'Cohorte base del rango filtrado.'],
            ['label' => 'Agendas del periodo', 'value' => $this->reportCardText($cards, 'Agendas del periodo') ?? '0', 'note' => 'Universo operativo filtrado por fecha de agenda.'],
            ['label' => 'Atendidos', 'value' => $this->reportCardText($cards, 'Atendidos') ?? '0', 'note' => $this->reportCardText($cards, 'Atendidos', 'hint') ?? ''],
            ['label' => 'Facturados', 'value' => $this->reportCardText($cards, 'Facturados') ?? '0', 'note' => $this->reportCardText($cards, 'Facturados', 'hint') ?? ''],
            ['label' => 'Pendiente de facturar', 'value' => $this->reportCardText($cards, 'Pendiente de facturar') ?? '0', 'note' => $this->reportCardText($cards, 'Pendiente de facturar', 'hint') ?? ''],
            ['label' => 'Producción facturada', 'value' => '$' . number_format($produccionFacturada, 2), 'note' => 'Monto real facturado consolidado en el rango.'],
        ];

        $cohortKpis = [
            ['label' => 'Solicitudes de exámenes', 'value' => $this->reportCardText($cards, 'Solicitudes de exámenes') ?? '0', 'note' => $this->reportCardText($cards, 'Solicitudes de exámenes', 'hint') ?? ''],
            ['label' => 'Agendadas al corte', 'value' => $this->reportCardText($cards, 'Agendadas al corte') ?? '0', 'note' => $this->reportCardText($cards, 'Agendadas al corte', 'hint') ?? ''],
            ['label' => 'Realizadas al corte', 'value' => $this->reportCardText($cards, 'Realizadas al corte') ?? '0', 'note' => $this->reportCardText($cards, 'Realizadas al corte', 'hint') ?? ''],
            ['label' => 'Realizadas posterior al corte', 'value' => $this->reportCardText($cards, 'Realizadas posterior al corte') ?? '0', 'note' => $this->reportCardText($cards, 'Realizadas posterior al corte', 'hint') ?? ''],
            ['label' => 'Cumplimiento al corte', 'value' => $this->reportCardText($cards, 'Cumplimiento al corte') ?? '—', 'note' => $this->reportCardText($cards, 'Cumplimiento al corte', 'hint') ?? ''],
            ['label' => 'Pérdida económica por no agendar', 'value' => $this->reportCardText($cards, 'Pérdida económica por no agendar') ?? '$0.00', 'note' => $this->reportCardText($cards, 'Pérdida económica por no agendar', 'hint') ?? ''],
            ['label' => 'Solicitudes sin agenda', 'value' => number_format($solicitudesSinAgenda), 'note' => 'Solicitudes registradas sin cruce operativo en procedimiento_proyectado.'],
            ['label' => 'Ausentes de cohorte', 'value' => number_format((int) ($meta['solicitudes_ausentes'] ?? 0)), 'note' => 'Agrupa ausencias explícitas y agendas vencidas sin evidencia de realización.'],
            ['label' => 'Pendientes vigentes de cohorte', 'value' => number_format($solicitudesPendientesVigentes), 'note' => 'Solicitudes aún abiertas: sin agenda o con agenda vigente sin cierre técnico.'],
        ];

        $operationalKpis = [
            ['label' => 'Agendas del periodo', 'value' => $this->reportCardText($cards, 'Agendas del periodo') ?? '0', 'note' => $this->reportCardText($cards, 'Agendas del periodo', 'hint') ?? ''],
            ['label' => 'Atendidos', 'value' => $this->reportCardText($cards, 'Atendidos') ?? '0', 'note' => $this->reportCardText($cards, 'Atendidos', 'hint') ?? ''],
            ['label' => 'Informadas', 'value' => $this->reportCardText($cards, 'Informadas') ?? '0', 'note' => $this->reportCardText($cards, 'Informadas', 'hint') ?? ''],
            ['label' => 'Facturados', 'value' => $this->reportCardText($cards, 'Facturados') ?? '0', 'note' => $this->reportCardText($cards, 'Facturados', 'hint') ?? ''],
            ['label' => 'Cumplimiento cita->realización', 'value' => $cumplimientoCita, 'note' => $this->reportCardText($cards, 'Cumplimiento cita->realización', 'hint') ?? ''],
            ['label' => 'Cancelados del periodo', 'value' => number_format($canceladasOperativas), 'note' => 'Cierre operativo cancelado en agenda dentro del periodo.'],
            ['label' => 'Pérdida operativa', 'value' => number_format($canceladasOperativas + $ausentesOperativos), 'note' => 'Cancelados + ausentes del periodo; ausente incluye agendas vencidas sin cierre.'],
            ['label' => 'Pendientes operativos', 'value' => number_format($sinCierre), 'note' => 'Agendas vigentes o del día sin evidencia de realización ni cierre final.'],
            ['label' => 'Pendiente de facturar', 'value' => $this->reportCardText($cards, 'Pendiente de facturar') ?? '0', 'note' => $this->reportCardText($cards, 'Pendiente de facturar', 'hint') ?? ''],
        ];

        $qualityKpis = [
            ['label' => 'SLA informe <= 48h', 'value' => $sla48, 'note' => $this->reportCardText($cards, 'SLA informe <= 48h', 'hint') ?? ''],
            ['label' => 'TAT promedio', 'value' => $tatPromedio, 'note' => 'Tiempo promedio entre examen e informe.'],
            ['label' => 'TAT mediana', 'value' => $tatMediana, 'note' => 'Mitad de los informes queda por debajo de este tiempo.'],
            ['label' => 'TAT P90', 'value' => $tatP90, 'note' => '90% de los informes cae bajo este umbral.'],
            ['label' => 'Día pico de tráfico', 'value' => $diaPico, 'note' => $traficoPico > 0 ? ($traficoPico . ' estudios en el día pico') : 'Sin datos'],
        ];

        $economicKpis = [
            [
                'label' => 'Producción facturada',
                'value' => '$' . number_format($produccionFacturada, 2),
                'meaning' => 'Monto facturado real consolidado en el rango.',
                'formula' => 'SUM(total_produccion) de la fuente de billing priorizada por form_id.',
            ],
            [
                'label' => 'Pendiente de facturar',
                'value' => number_format($pendientesFacturar),
                'meaning' => 'Estudios realizados con evidencia técnica pero todavía sin billing real.',
                'formula' => 'Estado realizado con evidencia técnica y sin evidencia de billing.',
            ],
            [
                'label' => 'Pendiente de pago',
                'value' => number_format((int) ($meta['pendientes_pago'] ?? 0)),
                'meaning' => 'Billing emitido cuyo estado real permanece en pendiente/cartera.',
                'formula' => 'Estado de facturación real marcado como pendiente/cartera; no incluye pendientes aún sin billing.',
            ],
            [
                'label' => 'Pendiente estimado',
                'value' => '$' . number_format($montoPendienteEstimado, 2),
                'meaning' => 'Valor potencial del backlog sin facturar, valorizado por código y categoría cuando existe tarifa.',
                'formula' => 'SUM de la tarifa resoluble por código/categoría para pendientes de facturar; excluye cartera/pendiente de pago.',
            ],
            [
                'label' => 'Ticket promedio facturado',
                'value' => '$' . number_format((float) ($meta['ticket_promedio_facturado'] ?? 0), 2),
                'meaning' => 'Ingreso promedio por estudio con billing real.',
                'formula' => 'Producción facturada / estudios facturados.',
            ],
            [
                'label' => 'Procedimientos facturados',
                'value' => number_format((int) ($meta['procedimientos_facturados'] ?? 0)),
                'meaning' => 'Volumen de procedimientos con billing real en el periodo.',
                'formula' => 'SUM(procedimientos_facturados) en la fuente de billing priorizada.',
            ],
        ];

        $operationalTables = [
            [
                'title' => 'Top exámenes realizados',
                'subtitle' => 'Volumen operativo por examen a partir de agendas del periodo.',
                'columns' => ['Examen realizado', 'Casos'],
                'rows' => $this->buildImagenesDashboardMetricRows($mixLabels, $mixValues),
                'empty_message' => 'Sin volumen operativo para el rango seleccionado.',
            ],
            [
                'title' => 'Backlog de facturación por categoría',
                'subtitle' => 'Separación entre casos ya facturados y backlog atendido pendiente.',
                'columns' => ['Categoría', 'Facturados', 'Pendiente facturar', 'Pendiente estimado'],
                'rows' => array_values(array_filter([
                    ['Pública', number_format((int) ($meta['facturados_publico'] ?? 0)), number_format($pendientesFacturarPublico), $pendientesFacturarPublico > 0 ? '$' . number_format($montoPendienteEstimadoPublico, 2) : '—'],
                    ['Privada', number_format((int) ($meta['facturados_privado'] ?? 0)), number_format($pendientesFacturarPrivado), '—'],
                    ['Particular / otros', number_format((int) ($meta['facturados_otros'] ?? 0)), number_format($pendientesFacturarOtros), '—'],
                ], static fn(array $row): bool => ((int) str_replace(',', '', $row[1])) > 0 || ((int) str_replace(',', '', $row[2])) > 0)),
                'empty_message' => 'Sin backlog de facturación para el rango seleccionado.',
            ],
            [
                'title' => 'Rendimiento económico',
                'subtitle' => 'Producción real facturada vs oportunidad económica aún abierta en pendientes de facturar.',
                'columns' => ['Métrica', 'Valor'],
                'rows' => [
                    ['Producción facturada real', '$' . number_format($produccionFacturada, 2)],
                    ['Pendiente estimado', '$' . number_format($montoPendienteEstimado, 2)],
                    ['Ticket promedio facturado', '$' . number_format((float) ($meta['ticket_promedio_facturado'] ?? 0), 2)],
                    ['Procedimientos facturados', number_format((int) ($meta['procedimientos_facturados'] ?? 0))],
                ],
                'empty_message' => 'Sin datos económicos para el rango seleccionado.',
            ],
            [
                'title' => $insuranceBreakdownTitle,
                'subtitle' => 'Agrupación del volumen por empresa; al elegir una empresa, se desglosa por plan.',
                'columns' => [$insuranceBreakdownItemLabel, 'Estudios'],
                'rows' => $this->buildImagenesDashboardMetricRows($insuranceLabels, $insuranceValues),
                'empty_message' => 'Sin datos de seguro para el rango seleccionado.',
            ],
        ];

        $cohortTables = [
            [
                'title' => 'Top 10 doctores solicitantes',
                'subtitle' => 'Conteo de solicitudes del rango usando consulta_examenes y cruce operativo por form_id/hc_number.',
                'columns' => ['Doctor solicitante', 'Solicitudes'],
                'rows' => $this->buildImagenesDashboardMetricRows($doctorSolicitanteLabels, $doctorSolicitanteValues),
                'empty_message' => 'Sin solicitudes de exámenes asociadas para el rango seleccionado.',
            ],
            [
                'title' => 'Top exámenes solicitados',
                'subtitle' => 'Exámenes más pedidos en la cohorte filtrada.',
                'columns' => ['Examen solicitado', 'Solicitudes'],
                'rows' => $this->buildImagenesDashboardMetricRows($topExamenesSolicitadosLabels, $topExamenesSolicitadosValues),
                'empty_message' => 'Sin exámenes solicitados para el rango seleccionado.',
            ],
        ];

        return [
            'scopeNotice' => 'Este reporte separa cohorte de solicitudes, operación del periodo, evidencia técnica NAS/informe y cierre económico para estudios de imágenes.',
            'filtersSummary' => $filtersSummary,
            'hallazgosClave' => $hallazgos,
            'methodology' => $methodology,
            'executiveKpis' => $executiveKpis,
            'cohortKpis' => $cohortKpis,
            'operationalKpis' => $operationalKpis,
            'qualityKpis' => $qualityKpis,
            'generalKpis' => [],
            'temporalKpis' => [],
            'economicKpis' => $economicKpis,
            'operationalTables' => $operationalTables,
            'cohortTables' => $cohortTables,
            'tables' => array_merge($operationalTables, $cohortTables),
            'totalAtenciones' => $total,
            'rangeLabel' => trim((string) ($filters['fecha_inicio'] ?? '')) !== '' && trim((string) ($filters['fecha_fin'] ?? '')) !== ''
                ? trim((string) ($filters['fecha_inicio'] ?? '')) . ' a ' . trim((string) ($filters['fecha_fin'] ?? ''))
                : '',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     */
    private function reportCardText(array $cards, string $label, string $field = 'value'): ?string
    {
        foreach ($cards as $card) {
            if (trim((string) ($card['label'] ?? '')) !== $label) {
                continue;
            }

            return trim((string) ($card[$field] ?? ''));
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     */
    private function reportCardValue(array $cards, string $label): ?int
    {
        $value = $this->reportCardText($cards, $label);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/[^\d-]/', '', $value);
    }

    /**
     * @param array<int,mixed> $labels
     * @param array<int,mixed> $values
     * @return array<int,array{0:string,1:string}>
     */
    private function buildImagenesDashboardMetricRows(array $labels, array $values): array
    {
        $rows = [];
        $count = min(count($labels), count($values));
        for ($index = 0; $index < $count; $index++) {
            $label = strtoupper(trim((string) ($labels[$index] ?? '')));
            $value = (int) ($values[$index] ?? 0);
            if ($label === '') {
                $label = 'SIN DATO';
            }

            $rows[] = [$label, number_format($value)];
        }

        return $rows;
    }

    /**
     * @param array<string,string> $filters
     * @return array{labels:array<int,string>,values:array<int,int>}
     */
    private function fetchTopDoctoresSolicitantes(array $filters, int $limit = 10): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return ['labels' => [], 'values' => []];
        }

        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm_top_doctor');
        $sedeExpr = $this->imagenesSedeExpr();

        $sql = "SELECT
                COALESCE(NULLIF(TRIM(pp.doctor), ''), 'Sin asignar') AS doctor_solicitante,
                COUNT(*) AS total_examenes
            FROM consulta_examenes ce
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ce.form_id
            LEFT JOIN patient_data pd ON pd.hc_number = ce.hc_number
            {$categoriaContext['join']}
            WHERE ce.examen_nombre IS NOT NULL
              AND TRIM(ce.examen_nombre) <> ''";

        $params = [];

        $fechaInicio = trim((string) ($filters['fecha_inicio'] ?? ''));
        if ($fechaInicio !== '') {
            $sql .= ' AND DATE(COALESCE(ce.consulta_fecha, ce.created_at)) >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = trim((string) ($filters['fecha_fin'] ?? ''));
        if ($fechaFin !== '') {
            $sql .= ' AND DATE(COALESCE(ce.consulta_fecha, ce.created_at)) <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin;
        }

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        if ($afiliacionFilter !== '') {
            $sql .= " AND {$afiliacionKeyExpr} = :afiliacion_filter_match";
            $params[':afiliacion_filter_match'] = $afiliacionFilter;
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $sql .= " AND {$categoriaContext['expr']} = :afiliacion_categoria_filter_match";
            $params[':afiliacion_categoria_filter_match'] = $afiliacionCategoriaFilter;
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sql .= " AND {$sedeExpr} = :sede_filter_match";
            $params[':sede_filter_match'] = $sedeFilter;
        }

        $tipoExamen = trim((string) ($filters['tipo_examen'] ?? ''));
        if ($tipoExamen !== '') {
            $sql .= ' AND (
                    ce.examen_nombre LIKE :tipo_examen
                    OR ce.examen_codigo LIKE :tipo_examen
                    OR TRIM(COALESCE(pp.procedimiento_proyectado, "")) LIKE :tipo_examen
                )';
            $params[':tipo_examen'] = '%' . $tipoExamen . '%';
        }

        $paciente = trim((string) ($filters['paciente'] ?? ''));
        if ($paciente !== '') {
            $sql .= " AND (
                    ce.hc_number LIKE :paciente_hc
                    OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente_nombre
                    OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente_nombre_alt
                )";
            $pacienteLike = '%' . $paciente . '%';
            $params[':paciente_hc'] = $pacienteLike;
            $params[':paciente_nombre'] = $pacienteLike;
            $params[':paciente_nombre_alt'] = $pacienteLike;
        }

        $estadoAgenda = trim((string) ($filters['estado_agenda'] ?? ''));
        if ($estadoAgenda !== '') {
            $sql .= ' AND TRIM(COALESCE(pp.estado_agenda, "")) = :estado_agenda';
            $params[':estado_agenda'] = $estadoAgenda;
        }

        $sql .= ' GROUP BY COALESCE(NULLIF(TRIM(pp.doctor), \'\'), \'Sin asignar\')
                  ORDER BY total_examenes DESC, doctor_solicitante ASC
                  LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $values = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = trim((string) ($row['doctor_solicitante'] ?? 'Sin asignar'));
            $values[] = (int) ($row['total_examenes'] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array{labels:array<int,string>,values:array<int,int>}
     */
    private function fetchTopExamenesSolicitados(array $filters, int $limit = 10): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return ['labels' => [], 'values' => []];
        }

        $flowSubquery = $this->buildImagenesSolicitudFlowSubquery();
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(flow.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr, 'iacm_top_exam_sol');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm_top_exam_sol');
        $seguroKeyExpr = $this->seguroKeyExpr($rawAfiliacionExpr, 'iacm_top_exam_sol');
        $sedeExpr = $this->imagenesSedeExprFromFields('flow.sede_departamento', 'flow.id_sede');
        $fechaSolicitudExpr = $this->safeSqlDateExpr('flow.fecha_solicitud');
        $examExpr = "CASE
                WHEN TRIM(COALESCE(flow.examen_codigo, '')) <> '' AND TRIM(COALESCE(flow.examen_nombre, '')) <> ''
                    THEN CONCAT(TRIM(flow.examen_codigo), ' - ', TRIM(flow.examen_nombre))
                WHEN TRIM(COALESCE(flow.examen_nombre, '')) <> ''
                    THEN TRIM(flow.examen_nombre)
                WHEN TRIM(COALESCE(flow.examen_codigo, '')) <> ''
                    THEN TRIM(flow.examen_codigo)
                ELSE 'Sin examen'
            END";

        $sql = "SELECT
                {$examExpr} AS examen_solicitado,
                COUNT(*) AS total_solicitudes
            FROM ({$flowSubquery}) flow
            LEFT JOIN patient_data pd ON pd.hc_number = flow.hc_number
            {$categoriaContext['join']}
            WHERE flow.examen_nombre IS NOT NULL
              AND TRIM(flow.examen_nombre) <> ''";

        $params = [];

        $fechaInicio = trim((string) ($filters['fecha_inicio'] ?? ''));
        if ($fechaInicio !== '') {
            $sql .= " AND {$fechaSolicitudExpr} >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = trim((string) ($filters['fecha_fin'] ?? ''));
        if ($fechaFin !== '') {
            $sql .= " AND {$fechaSolicitudExpr} <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        if ($afiliacionFilter !== '') {
            $sql .= " AND {$afiliacionKeyExpr} = :afiliacion_filter_match";
            $params[':afiliacion_filter_match'] = $afiliacionFilter;
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $sql .= " AND {$categoriaContext['expr']} = :afiliacion_categoria_filter_match";
            $params[':afiliacion_categoria_filter_match'] = $afiliacionCategoriaFilter;
        }

        $seguroFilter = $this->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $sql .= " AND {$seguroKeyExpr} = :seguro_filter_match";
            $params[':seguro_filter_match'] = $seguroFilter;
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sql .= " AND {$sedeExpr} = :sede_filter_match";
            $params[':sede_filter_match'] = $sedeFilter;
        }

        $tipoExamen = trim((string) ($filters['tipo_examen'] ?? ''));
        if ($tipoExamen !== '') {
            $sql .= ' AND (
                    flow.examen_nombre LIKE :tipo_examen
                    OR flow.examen_codigo LIKE :tipo_examen
                    OR TRIM(COALESCE(flow.procedimientos_match, "")) LIKE :tipo_examen
                )';
            $params[':tipo_examen'] = '%' . $tipoExamen . '%';
        }

        $paciente = trim((string) ($filters['paciente'] ?? ''));
        if ($paciente !== '') {
            $sql .= " AND (
                    flow.hc_number LIKE :paciente_hc
                    OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente_nombre
                    OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente_nombre_alt
                )";
            $pacienteLike = '%' . $paciente . '%';
            $params[':paciente_hc'] = $pacienteLike;
            $params[':paciente_nombre'] = $pacienteLike;
            $params[':paciente_nombre_alt'] = $pacienteLike;
        }

        $estadoAgenda = trim((string) ($filters['estado_agenda'] ?? ''));
        if ($estadoAgenda !== '') {
            $sql .= ' AND TRIM(COALESCE(flow.estados_agenda, "")) LIKE :estado_agenda_like';
            $params[':estado_agenda_like'] = '%' . $estadoAgenda . '%';
        }

        $sql .= " GROUP BY {$examExpr}
                  ORDER BY total_solicitudes DESC, examen_solicitado ASC
                  LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $values = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = trim((string) ($row['examen_solicitado'] ?? 'Sin examen'));
            $values[] = (int) ($row['total_solicitudes'] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function formatDashboardDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d-m-Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $filters
     * @return array<string,mixed>
     */
    private function buildImagenesDashboardSummary(array $rows, array $filters, array $solicitudes = []): array
    {
        $today = new DateTimeImmutable('today');
        $todayTs = $today->getTimestamp();
        $total = count($rows);
        $solicitudesTotal = (int) ($solicitudes['solicitudes_total'] ?? 0);
        $solicitudesAgendadas = (int) ($solicitudes['solicitudes_agendadas'] ?? 0);
        $solicitudesRealizadas = (int) ($solicitudes['solicitudes_realizadas'] ?? 0);
        $solicitudesInformadas = (int) ($solicitudes['solicitudes_informadas'] ?? 0);
        $solicitudesFacturadas = (int) ($solicitudes['solicitudes_facturadas'] ?? 0);
        $solicitudesAgendadasAlCorte = (int) ($solicitudes['solicitudes_agendadas_al_corte'] ?? 0);
        $solicitudesRealizadasAlCorte = (int) ($solicitudes['solicitudes_realizadas_al_corte'] ?? 0);
        $solicitudesRealizadasPostCorte = (int) ($solicitudes['solicitudes_realizadas_post_corte'] ?? 0);
        $solicitudesSinAgenda = (int) ($solicitudes['solicitudes_sin_agenda'] ?? 0);
        $solicitudesSinAgendaMontoEstimado = (float) ($solicitudes['solicitudes_sin_agenda_monto_estimado'] ?? 0);
        $solicitudesSinAgendaSinTarifa = (int) ($solicitudes['solicitudes_sin_agenda_sin_tarifa'] ?? 0);
        $solicitudesAgendadasPendientes = (int) ($solicitudes['solicitudes_agendadas_pendientes'] ?? 0);
        $solicitudesPendientesVigentes = (int) ($solicitudes['solicitudes_pendientes_vigentes'] ?? 0);
        $solicitudesCanceladas = (int) ($solicitudes['solicitudes_canceladas'] ?? 0);
        $solicitudesAusentes = (int) ($solicitudes['solicitudes_ausentes'] ?? 0);
        $conversionSolicitudRealizacionPct = isset($solicitudes['conversion_solicitud_realizacion_pct'])
            ? (float) $solicitudes['conversion_solicitud_realizacion_pct']
            : null;
        $cumplimientoRealizacionAlCortePct = isset($solicitudes['cumplimiento_realizacion_al_corte_pct'])
            ? (float) $solicitudes['cumplimiento_realizacion_al_corte_pct']
            : null;
        $cumplimientoRealizacionAcumuladoPct = isset($solicitudes['cumplimiento_realizacion_acumulado_pct'])
            ? (float) $solicitudes['cumplimiento_realizacion_acumulado_pct']
            : null;

        $informados = 0;
        $noInformados = 0;
        $pendientesInformar = 0;
        $facturados = 0;
        $pendientesFacturar = 0;
        $atendidosPendientesFacturar = 0;
        $pendientesPago = 0;
        $facturacionCancelada = 0;
        $facturadosEInformados = 0;
        $facturadosSinInformar = 0;
        $informadosSinFacturar = 0;
        $citasGeneradas = 0;
        $examenesRealizados = 0;
        $canceladas = 0;
        $ausentes = 0;
        $sinCierre = 0;
        $produccionFacturada = 0.0;
        $produccionFacturadaPublico = 0.0;
        $produccionFacturadaPrivado = 0.0;
        $procedimientosFacturados = 0;
        $facturadosPublico = 0;
        $facturadosPrivado = 0;
        $facturadosOtros = 0;
        $pendientesFacturarPublico = 0;
        $pendientesFacturarPrivado = 0;
        $pendientesFacturarOtros = 0;
        $montoPendienteEstimadoTotal = 0.0;
        $montoPendienteEstimadoPublico = 0.0;
        $pendientesFacturarSinTarifa = 0;
        $pendientesFacturarPublicoSinTarifa = 0;
        $estadoRealCounts = [
            'FACTURADA' => 0,
            'REALIZADA_CON_ARCHIVOS' => 0,
            'REALIZADA_INFORMADA' => 0,
            'CANCELADA' => 0,
            'AUSENTE' => 0,
            'SIN_CIERRE_OPERATIVO' => 0,
        ];
        $tatHoras = [];
        $sla48Cumple = 0;
        $sla48Total = 0;
        $dailyMap = [];
        $traficoSemana = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];
        $traficoSemanaLabels = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $mixMap = [];
        $tarifarioCache = [];
        $aging = ['0-2 días' => 0, '3-7 días' => 0, '8-14 días' => 0, '15+ días' => 0];
        $empresaSeguroCounts = [];
        $seguroCounts = [];
        $empresaSeguroFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        $selectedEmpresaSeguro = '';

        foreach ($rows as $row) {
            $estadoAgenda = trim((string) ($row['estado_agenda'] ?? ''));
            $estadoRealizacion = (string) ($row['estado_realizacion'] ?? $this->resolveImagenRealizationState($row));
            $estadoFacturacion = (string) ($row['estado_facturacion'] ?? $this->resolveImagenBillingState(
                $estadoRealizacion,
                (int) ($row['facturado'] ?? 0) === 1,
                (string) ($row['estado_facturacion_raw'] ?? '')
            ));
            $estadoInforme = (string) ($row['estado_informe'] ?? $this->resolveImagenInformeState($row));
            $informado = $estadoInforme === 'INFORMADA';
            $facturado = (int) ($row['facturado'] ?? 0) === 1;
            $realizado = $this->isImagenEstadoRealizado($estadoRealizacion);
            $afiliacionCategoria = trim((string) ($row['afiliacion_categoria'] ?? ''));
            $esPublico = $afiliacionCategoria === 'publico';
            $esPrivado = $afiliacionCategoria === 'privado';
            $esOtro = !$esPublico && !$esPrivado;
            $empresaSeguroLabel = strtoupper(trim((string) ($row['empresa_seguro'] ?? '')));
            if ($empresaSeguroLabel === '') {
                $empresaSeguroLabel = 'SIN CONVENIO';
            }
            $empresaSeguroKey = $this->normalizeAfiliacionFilter((string) ($row['afiliacion_key'] ?? $empresaSeguroLabel));
            if (!isset($empresaSeguroCounts[$empresaSeguroLabel])) {
                $empresaSeguroCounts[$empresaSeguroLabel] = 0;
            }
            $empresaSeguroCounts[$empresaSeguroLabel]++;
            if ($empresaSeguroFilter !== '' && $empresaSeguroKey === $empresaSeguroFilter) {
                if ($selectedEmpresaSeguro === '') {
                    $selectedEmpresaSeguro = $empresaSeguroLabel;
                }
                $seguroLabel = strtoupper(trim((string) ($row['seguro_label'] ?? $row['afiliacion'] ?? '')));
                if ($seguroLabel === '') {
                    $seguroLabel = 'SIN CONVENIO';
                }
                if (!isset($seguroCounts[$seguroLabel])) {
                    $seguroCounts[$seguroLabel] = 0;
                }
                $seguroCounts[$seguroLabel]++;
            }
            $fechaExamenRaw = trim((string) ($row['fecha_examen'] ?? ''));
            $fechaExamenTs = $fechaExamenRaw !== '' ? strtotime($fechaExamenRaw) : false;
            $fechaExamenDia = $fechaExamenTs !== false ? date('Y-m-d', $fechaExamenTs) : '';
            if ($fechaExamenTs !== false) {
                $weekDay = (int) date('N', $fechaExamenTs);
                if (isset($traficoSemana[$weekDay])) {
                    $traficoSemana[$weekDay]++;
                }
            }

            if ($this->isImagenCitaGeneradaEstado($estadoAgenda)) {
                $citasGeneradas++;
            }
            if ($realizado) {
                $examenesRealizados++;
            }

            if (!isset($estadoRealCounts[$estadoRealizacion])) {
                $estadoRealCounts[$estadoRealizacion] = 0;
            }
            $estadoRealCounts[$estadoRealizacion]++;

            if ($fechaExamenDia !== '' && $realizado) {
                if (!isset($dailyMap[$fechaExamenDia])) {
                    $dailyMap[$fechaExamenDia] = ['realizados' => 0, 'informados' => 0];
                }
                $dailyMap[$fechaExamenDia]['realizados']++;
            }

            if ($informado) {
                $informados++;
                if ($fechaExamenDia !== '') {
                    if (!isset($dailyMap[$fechaExamenDia])) {
                        $dailyMap[$fechaExamenDia] = ['realizados' => 0, 'informados' => 0];
                    }
                    $dailyMap[$fechaExamenDia]['informados']++;
                }
            } else {
                $noInformados++;
            }

            if ($facturado || $estadoFacturacion === 'FACTURADA') {
                $facturados++;
            }
            if ($estadoFacturacion === 'PENDIENTE_PAGO') {
                $pendientesPago++;
            }
            if ($estadoFacturacion === 'CANCELADA') {
                $facturacionCancelada++;
            }

            $produccionRow = max(0.0, (float) ($row['total_produccion'] ?? 0));
            $produccionFacturada += $produccionRow;
            $procedimientosFacturados += max(0, (int) ($row['procedimientos_facturados'] ?? 0));
            if ($facturado || $estadoFacturacion === 'FACTURADA') {
                if ($esPublico) {
                    $facturadosPublico++;
                    $produccionFacturadaPublico += $produccionRow;
                } elseif ($esPrivado) {
                    $facturadosPrivado++;
                    $produccionFacturadaPrivado += $produccionRow;
                } elseif ($esOtro) {
                    $facturadosOtros++;
                }
            }

            if ($estadoRealizacion === 'CANCELADA') {
                $canceladas++;
            } elseif ($estadoRealizacion === 'AUSENTE') {
                $ausentes++;
            } elseif ($estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
                $sinCierre++;
            }

            $esPendienteFacturar = $estadoFacturacion === 'PENDIENTE_FACTURAR';
            if ($esPendienteFacturar) {
                $pendientesFacturar++;
                $atendidosPendientesFacturar++;
                if ($esPublico) {
                    $pendientesFacturarPublico++;
                } elseif ($esPrivado) {
                    $pendientesFacturarPrivado++;
                } elseif ($esOtro) {
                    $pendientesFacturarOtros++;
                }
            }
            if ($estadoInforme === 'PENDIENTE_INFORMAR') {
                $pendientesInformar++;
            }

            if ($informado && $facturado) {
                $facturadosEInformados++;
            } elseif ($facturado && !$informado) {
                $facturadosSinInformar++;
            } elseif (!$facturado && $informado) {
                $informadosSinFacturar++;
            }

            if ($informado) {
                $informeActualizadoRaw = trim((string) ($row['informe_actualizado'] ?? ''));
                $informeActualizadoTs = $informeActualizadoRaw !== '' ? strtotime($informeActualizadoRaw) : false;
                if ($fechaExamenTs !== false && $informeActualizadoTs !== false && $informeActualizadoTs >= $fechaExamenTs) {
                    $horas = ($informeActualizadoTs - $fechaExamenTs) / 3600;
                    $tatHoras[] = $horas;
                    $sla48Total++;
                    if ($horas <= 48) {
                        $sla48Cumple++;
                    }
                }
            } elseif ($fechaExamenTs !== false && $estadoInforme === 'PENDIENTE_INFORMAR') {
                $dias = max(0, (int) floor(($todayTs - $fechaExamenTs) / 86400));
                if ($dias <= 2) {
                    $aging['0-2 días']++;
                } elseif ($dias <= 7) {
                    $aging['3-7 días']++;
                } elseif ($dias <= 14) {
                    $aging['8-14 días']++;
                } else {
                    $aging['15+ días']++;
                }
            }

            $tipoRaw = trim((string) ($row['tipo_examen'] ?? ''));
            $parsedTipo = $this->parseProcedimientoImagen($tipoRaw);
            $tipoTexto = trim((string) ($parsedTipo['texto'] ?? ''));
            $codigo = (string) ($this->extraerCodigoTarifario($tipoTexto !== '' ? $tipoTexto : $tipoRaw) ?? '');
            $nombreTarifario = '';

            if ($codigo !== '') {
                $tarifaCacheKey = $codigo . '|' . trim((string) ($row['afiliacion_categoria'] ?? ''));
                if (!isset($tarifarioCache[$tarifaCacheKey])) {
                    $tarifarioCache[$tarifaCacheKey] = $this->obtenerTarifarioPorCodigo(
                        $codigo,
                        (string) ($row['afiliacion_categoria'] ?? '')
                    );
                }
                $tarifa = $tarifarioCache[$tarifaCacheKey];
                if (is_array($tarifa) && $tarifa !== []) {
                    $nombreTarifario = trim((string) ($tarifa['descripcion'] ?? ($tarifa['short_description'] ?? '')));
                }
            }

            if ($esPendienteFacturar) {
                $montoEstimadoPendiente = $this->resolveImagenTarifaPendiente($codigo, $row, $tarifa ?? null);
                if ($montoEstimadoPendiente > 0) {
                    $montoPendienteEstimadoTotal += $montoEstimadoPendiente;
                    if ($esPublico) {
                        $montoPendienteEstimadoPublico += $montoEstimadoPendiente;
                    }
                } else {
                    $pendientesFacturarSinTarifa++;
                    if ($esPublico) {
                        $pendientesFacturarPublicoSinTarifa++;
                    }
                }
            }

            $detalle = $tipoTexto !== '' ? $tipoTexto : $tipoRaw;
            if ($codigo !== '') {
                $detalle = trim((string) (preg_replace('/\b' . preg_quote($codigo, '/') . '\b\s*[-:]?\s*/iu', '', $detalle) ?? $detalle));
            }
            $detalle = trim($detalle, " -\t\n\r\0\x0B");

            if ($codigo !== '' && $nombreTarifario !== '') {
                $label = $codigo . ' - ' . $nombreTarifario;
                $suffix = $this->normalizarDetalleEstudio012A($detalle, $nombreTarifario);
                if ($suffix !== '') {
                    $label .= ' - ' . $suffix;
                }
            } elseif ($codigo !== '') {
                $label = $codigo . ' - ' . ($detalle !== '' ? $detalle : 'SIN DETALLE');
            } else {
                $label = $detalle !== '' ? $detalle : 'SIN CÓDIGO';
            }

            $mixMap[$label] = ($mixMap[$label] ?? 0) + 1;
        }

        arsort($mixMap);
        $mixTop = array_slice($mixMap, 0, 8, true);
        $doctorSolicitanteTop = $this->fetchTopDoctoresSolicitantes($filters, 10);
        $topExamenesSolicitados = $this->fetchTopExamenesSolicitados($filters, 10);

        $seriesStart = trim((string) ($filters['fecha_inicio'] ?? ''));
        $seriesEnd = trim((string) ($filters['fecha_fin'] ?? ''));
        if ($seriesStart !== '' && $seriesEnd !== '') {
            $dStart = DateTimeImmutable::createFromFormat('Y-m-d', $seriesStart);
            $dEnd = DateTimeImmutable::createFromFormat('Y-m-d', $seriesEnd);
            if ($dStart instanceof DateTimeImmutable && $dEnd instanceof DateTimeImmutable && $dStart <= $dEnd) {
                $days = (int) $dStart->diff($dEnd)->days;
                if ($days <= 120) {
                    for ($cursor = $dStart; $cursor <= $dEnd; $cursor = $cursor->modify('+1 day')) {
                        $key = $cursor->format('Y-m-d');
                        if (!isset($dailyMap[$key])) {
                            $dailyMap[$key] = ['realizados' => 0, 'informados' => 0];
                        }
                    }
                }
            }
        }
        ksort($dailyMap);

        $tatPromedio = $tatHoras !== [] ? array_sum($tatHoras) / count($tatHoras) : null;
        $tatMediana = $this->calcularPercentil($tatHoras, 0.50);
        $tatP90 = $this->calcularPercentil($tatHoras, 0.90);
        $sla48Pct = $sla48Total > 0 ? ($sla48Cumple * 100 / $sla48Total) : null;
        $cumplimientoCitaPct = $citasGeneradas > 0 ? ($examenesRealizados * 100 / $citasGeneradas) : null;
        $ticketPromedioFacturado = $facturados > 0 ? ($produccionFacturada / $facturados) : 0.0;
        $produccionPromedioPorEstudio = $total > 0 ? ($produccionFacturada / $total) : 0.0;
        $canceladasOperativas = $canceladas;
        $ausentesOperativos = $ausentes;
        $canceladas = $solicitudesCanceladas;
        $ausentes = $solicitudesAusentes;
        $perdidas = $canceladas + $ausentes;
        $perdidasOperativas = $canceladasOperativas + $ausentesOperativos;
        $maxTraficoValor = $traficoSemana !== [] ? max($traficoSemana) : 0;
        $maxTraficoDiaNum = 0;
        if ($maxTraficoValor > 0) {
            foreach ($traficoSemana as $dayNum => $totalDia) {
                if ($totalDia === $maxTraficoValor) {
                    $maxTraficoDiaNum = (int) $dayNum;
                    break;
                }
            }
        }
        $maxTraficoDiaLabel = $maxTraficoDiaNum > 0 ? (string) ($traficoSemanaLabels[$maxTraficoDiaNum] ?? '') : '—';
        $rangeLabel = trim((string) ($filters['fecha_inicio'] ?? '')) . ' a ' . trim((string) ($filters['fecha_fin'] ?? ''));
        $rangeLabel = trim($rangeLabel, ' a');
        $insuranceBreakdownMode = $empresaSeguroFilter !== '' ? 'seguro' : 'empresa';
        $insuranceBreakdownTitle = $insuranceBreakdownMode === 'seguro'
            ? 'Planes de seguro' . ($selectedEmpresaSeguro !== '' ? ' de ' . $selectedEmpresaSeguro : '')
            : 'Empresas de seguro';
        $insuranceBreakdownItemLabel = $insuranceBreakdownMode === 'seguro'
            ? 'Plan de seguro'
            : 'Empresa de seguro';
        $insuranceBreakdownCounts = $insuranceBreakdownMode === 'seguro' ? $seguroCounts : $empresaSeguroCounts;
        arsort($insuranceBreakdownCounts);
        $insuranceBreakdownTop = array_slice($insuranceBreakdownCounts, 0, 10, true);

        return [
            'cards' => [
                ['label' => 'Solicitudes de exámenes', 'value' => $solicitudesTotal, 'hint' => 'Base consulta_examenes según filtros del periodo'],
                ['label' => 'Agendadas al corte', 'value' => $solicitudesAgendadasAlCorte, 'hint' => $solicitudesTotal > 0 ? (number_format(($solicitudesAgendadasAlCorte * 100) / $solicitudesTotal, 1) . '% de solicitudes al ' . $rangeLabel) : '0.0% de solicitudes'],
                ['label' => 'Realizadas al corte', 'value' => $solicitudesRealizadasAlCorte, 'hint' => $solicitudesTotal > 0 ? (number_format(($solicitudesRealizadasAlCorte * 100) / $solicitudesTotal, 1) . '% de solicitudes al ' . $rangeLabel) : '0.0% de solicitudes'],
                ['label' => 'Realizadas posterior al corte', 'value' => $solicitudesRealizadasPostCorte, 'hint' => $solicitudesRealizadasPostCorte > 0 ? 'Solicitudes del rango que se cerraron después de la fecha fin' : 'Sin cierres posteriores'],
                ['label' => 'Cumplimiento al corte', 'value' => $cumplimientoRealizacionAlCortePct !== null ? (number_format($cumplimientoRealizacionAlCortePct, 1) . '%') : '—', 'hint' => $solicitudesTotal > 0 ? ($solicitudesRealizadasAlCorte . ' realizadas de ' . $solicitudesTotal . ' solicitudes') : 'Sin solicitudes'],
                ['label' => 'Pérdida económica por no agendar', 'value' => '$' . number_format($solicitudesSinAgendaMontoEstimado, 2), 'hint' => $solicitudesSinAgenda > 0 ? (number_format($solicitudesSinAgenda) . ' solicitudes sin agenda' . ($solicitudesSinAgendaSinTarifa > 0 ? '; ' . number_format($solicitudesSinAgendaSinTarifa) . ' sin tarifa resoluble' : '')) : 'Sin solicitudes sin agenda'],
                ['label' => 'Agendas del periodo', 'value' => $total, 'hint' => $rangeLabel !== '' ? ('Rango agenda: ' . $rangeLabel) : 'Sin rango'],
                ['label' => 'Atendidos', 'value' => $examenesRealizados, 'hint' => $total > 0 ? (number_format(($examenesRealizados * 100) / $total, 1) . '% de agendas') : '0.0% de agendas'],
                ['label' => 'Facturados', 'value' => $facturados, 'hint' => $examenesRealizados > 0 ? (number_format(($facturados * 100) / $examenesRealizados, 1) . '% de atendidos') : '0.0% de atendidos'],
                ['label' => 'Solicitudes sin agenda', 'value' => $solicitudesSinAgenda, 'hint' => $solicitudesTotal > 0 ? (number_format(($solicitudesSinAgenda * 100) / $solicitudesTotal, 1) . '% de solicitudes') : '0.0% de solicitudes'],
                ['label' => 'Ausentes de cohorte', 'value' => $solicitudesAusentes, 'hint' => 'Agrupa ausencias explícitas y agendas vencidas sin evidencia de realización'],
                ['label' => 'Pendientes vigentes de cohorte', 'value' => $solicitudesPendientesVigentes, 'hint' => $solicitudesPendientesVigentes > 0 ? (number_format($solicitudesSinAgenda) . ' sin agenda / ' . number_format($solicitudesAgendadasPendientes) . ' con agenda vigente') : 'Sin solicitudes abiertas vigentes'],
                ['label' => 'Pendiente de pago', 'value' => $pendientesPago, 'hint' => 'Billing ya emitido con estado pendiente/cartera; no incluye estudios aún sin facturar'],
                ['label' => 'Cancelados del periodo', 'value' => $canceladasOperativas, 'hint' => 'Cierre operativo cancelado en agenda'],
                ['label' => 'Pendiente de facturar', 'value' => $pendientesFacturar, 'hint' => 'Realizadas con evidencia técnica aún sin billing real; no entran en pendiente de pago hasta emitir billing'],
                ['label' => 'Pérdida operativa', 'value' => $perdidasOperativas, 'hint' => $canceladasOperativas . ' canceladas, ' . $ausentesOperativos . ' ausentes (incluye agendas vencidas sin cierre)'],
                ['label' => 'Pendientes operativos', 'value' => $sinCierre, 'hint' => 'Agendas vigentes o del día sin evidencia de realización ni cierre final'],
                ['label' => 'Producción facturada', 'value' => '$' . number_format($produccionFacturada, 2), 'hint' => 'Monto real facturado en el rango.'],
                ['label' => 'Pendiente estimado', 'value' => '$' . number_format($montoPendienteEstimadoTotal, 2), 'hint' => $pendientesFacturarSinTarifa > 0 ? ('Valorizado por código/categoría; ' . $pendientesFacturarSinTarifa . ' sin tarifa resoluble') : 'Estimado valorizado por código y categoría; excluye pendientes de pago'],
                ['label' => 'Ticket promedio facturado', 'value' => '$' . number_format($ticketPromedioFacturado, 2), 'hint' => $facturados > 0 ? ('Promedio por ' . $facturados . ' estudios facturados') : 'Sin estudios facturados'],
                ['label' => 'Procedimientos facturados', 'value' => $procedimientosFacturados, 'hint' => '$' . number_format($produccionPromedioPorEstudio, 2) . ' promedio por estudio'],
                ['label' => 'Día pico de tráfico', 'value' => $maxTraficoDiaLabel, 'hint' => $maxTraficoValor > 0 ? ($maxTraficoValor . ' estudios') : 'Sin datos'],
                ['label' => 'Informadas', 'value' => $informados, 'hint' => $total > 0 ? (number_format(($informados * 100) / $total, 1) . '% del total') : '0.0% del total'],
                ['label' => 'Cumplimiento cita->realización', 'value' => $cumplimientoCitaPct !== null ? (number_format($cumplimientoCitaPct, 1) . '%') : '—', 'hint' => 'Objetivo: subir conversión de cita a examen'],
                ['label' => 'SLA informe <= 48h', 'value' => $sla48Pct !== null ? (number_format($sla48Pct, 1) . '%') : '—', 'hint' => $sla48Total > 0 ? ($sla48Cumple . ' de ' . $sla48Total . ' informes') : 'Sin datos de TAT'],
            ],
            'meta' => [
                'tat_promedio_horas' => $tatPromedio !== null ? round($tatPromedio, 2) : null,
                'tat_mediana_horas' => $tatMediana !== null ? round($tatMediana, 2) : null,
                'tat_p90_horas' => $tatP90 !== null ? round($tatP90, 2) : null,
                'produccion_facturada' => round($produccionFacturada, 2),
                'produccion_facturada_publico' => round($produccionFacturadaPublico, 2),
                'produccion_facturada_privado' => round($produccionFacturadaPrivado, 2),
                'ticket_promedio_facturado' => round($ticketPromedioFacturado, 2),
                'procedimientos_facturados' => $procedimientosFacturados,
                'produccion_promedio_por_estudio' => round($produccionPromedioPorEstudio, 2),
                'pendientes_informar' => $pendientesInformar,
                'pendientes_facturar' => $pendientesFacturar,
                'pendientes_operativos' => $sinCierre,
                'atendidos_pendientes_facturar' => $atendidosPendientesFacturar,
                'pendientes_facturar_publico' => $pendientesFacturarPublico,
                'pendientes_facturar_privado' => $pendientesFacturarPrivado,
                'pendientes_facturar_otros' => $pendientesFacturarOtros,
                'pendientes_facturar_sin_tarifa' => $pendientesFacturarSinTarifa,
                'pendientes_facturar_publico_sin_tarifa' => $pendientesFacturarPublicoSinTarifa,
                'monto_pendiente_estimado' => round($montoPendienteEstimadoTotal, 2),
                'monto_pendiente_estimado_publico' => round($montoPendienteEstimadoPublico, 2),
                'facturados_publico' => $facturadosPublico,
                'facturados_privado' => $facturadosPrivado,
                'facturados_otros' => $facturadosOtros,
                'solicitudes_total' => $solicitudesTotal,
                'solicitudes_agendadas' => $solicitudesAgendadas,
                'solicitudes_realizadas' => $solicitudesRealizadas,
                'solicitudes_informadas' => $solicitudesInformadas,
                'solicitudes_facturadas' => $solicitudesFacturadas,
                'solicitudes_agendadas_al_corte' => $solicitudesAgendadasAlCorte,
                'solicitudes_realizadas_al_corte' => $solicitudesRealizadasAlCorte,
                'solicitudes_realizadas_post_corte' => $solicitudesRealizadasPostCorte,
                'solicitudes_sin_agenda' => $solicitudesSinAgenda,
                'solicitudes_sin_agenda_monto_estimado' => round($solicitudesSinAgendaMontoEstimado, 2),
                'solicitudes_sin_agenda_sin_tarifa' => $solicitudesSinAgendaSinTarifa,
                'solicitudes_agendadas_pendientes' => $solicitudesAgendadasPendientes,
                'solicitudes_pendientes_vigentes' => $solicitudesPendientesVigentes,
                'solicitudes_canceladas' => $solicitudesCanceladas,
                'solicitudes_ausentes' => $solicitudesAusentes,
                'conversion_solicitud_realizacion_pct' => $conversionSolicitudRealizacionPct,
                'cumplimiento_realizacion_al_corte_pct' => $cumplimientoRealizacionAlCortePct,
                'cumplimiento_realizacion_acumulado_pct' => $cumplimientoRealizacionAcumuladoPct,
                'pendientes_pago' => $pendientesPago,
                'cancelados' => $canceladas,
                'facturacion_cancelada' => $facturacionCancelada,
                'perdidas' => $perdidas,
                'sin_cierre_operativo' => $sinCierre,
                'insurance_breakdown' => [
                    'mode' => $insuranceBreakdownMode,
                    'title' => $insuranceBreakdownTitle,
                    'item_label' => $insuranceBreakdownItemLabel,
                    'selected_company' => $selectedEmpresaSeguro,
                ],
            ],
            'charts' => [
                'solicitudes_pipeline' => [
                    'labels' => ['Solicitadas', 'Agendadas al corte', 'Realizadas al corte', 'Realizadas posterior', 'Pendientes vigentes'],
                    'values' => [
                        $solicitudesTotal,
                        $solicitudesAgendadasAlCorte,
                        $solicitudesRealizadasAlCorte,
                        $solicitudesRealizadasPostCorte,
                        $solicitudesPendientesVigentes,
                    ],
                ],
                'serie_diaria' => [
                    'labels' => array_keys($dailyMap),
                    'realizados' => array_values(array_map(static fn(array $item): int => (int) ($item['realizados'] ?? 0), $dailyMap)),
                    'informados' => array_values(array_map(static fn(array $item): int => (int) ($item['informados'] ?? 0), $dailyMap)),
                ],
                'mix_codigos' => [
                    'labels' => array_keys($mixTop),
                    'values' => array_values($mixTop),
                ],
                'top_doctores_solicitantes' => [
                    'labels' => $doctorSolicitanteTop['labels'],
                    'values' => $doctorSolicitanteTop['values'],
                ],
                'top_examenes_solicitados' => [
                    'labels' => $topExamenesSolicitados['labels'],
                    'values' => $topExamenesSolicitados['values'],
                ],
                'aging_backlog' => [
                    'labels' => array_keys($aging),
                    'values' => array_values($aging),
                ],
                'trazabilidad' => [
                    'labels' => ['Atendidos', 'Facturados', 'Pendiente de pago', 'Cancelados'],
                    'values' => [
                        $examenesRealizados,
                        $facturados,
                        $pendientesPago,
                        $canceladasOperativas,
                    ],
                ],
                'citas_vs_realizados' => [
                    'labels' => ['Agendas del periodo', 'Realizadas', 'Pérdida operativa', 'Pendientes operativos'],
                    'values' => [$total, $examenesRealizados, $perdidasOperativas, $sinCierre],
                ],
                'trafico_dia_semana' => [
                    'labels' => array_values($traficoSemanaLabels),
                    'values' => array_values($traficoSemana),
                ],
                'backlog_facturacion_categoria' => [
                    'labels' => ['Facturados', 'Pend. facturar'],
                    'datasets' => [
                        [
                            'label' => 'Pública',
                            'values' => [$facturadosPublico, $pendientesFacturarPublico],
                        ],
                        [
                            'label' => 'Privada',
                            'values' => [$facturadosPrivado, $pendientesFacturarPrivado],
                        ],
                        [
                            'label' => 'Particular / otros',
                            'values' => [$facturadosOtros, $pendientesFacturarOtros],
                        ],
                    ],
                ],
                'rendimiento_economico' => [
                    'labels' => ['Facturado real', 'Pendiente estimado'],
                    'values' => [
                        round($produccionFacturada, 2),
                        round($montoPendienteEstimadoTotal, 2),
                    ],
                ],
                'analisis_seguro' => [
                    'labels' => array_keys($insuranceBreakdownTop),
                    'values' => array_values($insuranceBreakdownTop),
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $tarifa
     */
    private function resolveImagenTarifaPublicaNivel3(?array $tarifa): float
    {
        if (!is_array($tarifa) || $tarifa === []) {
            return 0.0;
        }

        return round(max(0.0, (float) ($tarifa['valor_facturar_nivel3'] ?? 0)), 2);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed>|null $tarifa
     */
    private function resolveImagenTarifaPendiente(?string $codigo, array $row, ?array $tarifa = null): float
    {
        $codigo = strtoupper(trim((string) $codigo));
        $levelKey = $this->resolveImagenTarifaLevelKey($row);
        if ($levelKey !== null) {
            if ($codigo !== '') {
                $code = $this->findImagenTarifaCode($codigo);
                if ($code !== null) {
                    $prices = $this->pricesForTarifaCode((int) ($code['id'] ?? 0));
                    $amount = round((float) ($prices[$levelKey] ?? 0.0), 2);
                    if ($amount > 0) {
                        return $amount;
                    }
                }
            }

            $descriptionCandidate = trim((string) ($row['examen_nombre'] ?? ($row['examen'] ?? '')));
            if ($descriptionCandidate !== '') {
                $fallbackCode = $this->findImagenTarifaCodeByDescription($descriptionCandidate, $codigo);
                if ($fallbackCode !== null) {
                    $isSameCode = $codigo !== '' && strtoupper(trim((string) ($fallbackCode['codigo'] ?? ''))) === $codigo;
                    if (!$isSameCode) {
                        $prices = $this->pricesForTarifaCode((int) ($fallbackCode['id'] ?? 0));
                        $amount = round((float) ($prices[$levelKey] ?? 0.0), 2);
                        if ($amount > 0) {
                            return $amount;
                        }
                    }
                }
            }
        }

        $afiliacionCategoria = $this->normalizarTexto((string) ($row['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoria === 'publico') {
            return $this->resolveImagenTarifaPublicaNivel3($tarifa);
        }

        return 0.0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveImagenTarifaLevelKey(array $row): ?string
    {
        $levels = $this->codePriceLevels();
        if ($levels === []) {
            return null;
        }

        $categoria = $this->normalizarTexto((string) ($row['afiliacion_categoria'] ?? ''));
        $candidates = [];
        foreach ([
            (string) ($row['afiliacion'] ?? ''),
            (string) ($row['seguro_label'] ?? ''),
            (string) ($row['empresa_seguro'] ?? ''),
            (string) ($row['afiliacion_categoria'] ?? ''),
        ] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $candidates[] = $candidate;

            $mapped = $this->resolveImagenMappedAffiliation($candidate);
            if ($mapped !== null) {
                $mappedRaw = trim((string) ($mapped['afiliacion_raw'] ?? ''));
                if ($mappedRaw !== '') {
                    $candidates[] = $mappedRaw;
                }
                $empresaSeguro = trim((string) ($mapped['empresa_seguro'] ?? ''));
                if ($empresaSeguro !== '') {
                    $candidates[] = $empresaSeguro;
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

        foreach ($candidates as $candidate) {
            $levelKey = $this->findImagenTarifaLevelKeyBySimilarity($candidate, $levels, $categoria);
            if ($levelKey !== null) {
                return $levelKey;
            }
        }

        return null;
    }

    /**
     * @return array{id:int,codigo:string,descripcion:string}|null
     */
    private function findImagenTarifaCode(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        if (array_key_exists($codigo, $this->tarifaCodeCache)) {
            return $this->tarifaCodeCache[$codigo];
        }

        $codigoSinCeros = ltrim($codigo, '0');
        $code = Tarifario2014::query()
            ->select(['id', 'codigo', 'descripcion'])
            ->where(function ($builder) use ($codigo, $codigoSinCeros): void {
                $builder->where('codigo', $codigo);
                if ($codigoSinCeros !== '' && $codigoSinCeros !== $codigo) {
                    $builder->orWhere('codigo', $codigoSinCeros);
                }
            })
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 ELSE 1 END', [$codigo])
            ->first();

        if ($code === null) {
            return $this->tarifaCodeCache[$codigo] = null;
        }

        return $this->tarifaCodeCache[$codigo] = [
            'id' => (int) $code->id,
            'codigo' => trim((string) ($code->codigo ?? '')),
            'descripcion' => trim((string) ($code->descripcion ?? '')),
        ];
    }

    /**
     * @return array{id:int,codigo:string,descripcion:string}|null
     */
    private function findImagenTarifaCodeByDescription(string $description, string $codigoHint = ''): ?array
    {
        $descriptionNorm = $this->normalizarTexto($description);
        if ($descriptionNorm === '') {
            return null;
        }

        $codigoHint = strtoupper(trim($codigoHint));
        $cacheKey = $descriptionNorm . '|' . $codigoHint;
        if (array_key_exists($cacheKey, $this->tarifaDescriptionResolveCache)) {
            return $this->tarifaDescriptionResolveCache[$cacheKey];
        }

        $index = $this->tarifaDescriptionIndex();
        if ($index === []) {
            return $this->tarifaDescriptionResolveCache[$cacheKey] = null;
        }

        $bestRow = null;
        $bestScore = 0.0;

        foreach ($index as $row) {
            $candidateNorms = array_values(array_filter([
                trim((string) ($row['descripcion_norm'] ?? '')),
                trim((string) ($row['short_description_norm'] ?? '')),
            ]));

            foreach ($candidateNorms as $candidateNorm) {
                if ($candidateNorm === '') {
                    continue;
                }

                $score = 0.0;
                if ($candidateNorm === $descriptionNorm) {
                    $score = 100.0;
                } elseif (str_contains($candidateNorm, $descriptionNorm) || str_contains($descriptionNorm, $candidateNorm)) {
                    $score = 92.0;
                } else {
                    similar_text($descriptionNorm, $candidateNorm, $percent);
                    $score = (float) $percent;
                }

                if ($score <= 0) {
                    continue;
                }

                if ($codigoHint !== '' && strtoupper(trim((string) ($row['codigo'] ?? ''))) === $codigoHint) {
                    $score += 1.5;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRow = $row;
                }
            }
        }

        if (!is_array($bestRow) || $bestScore < 78.0) {
            return $this->tarifaDescriptionResolveCache[$cacheKey] = null;
        }

        return $this->tarifaDescriptionResolveCache[$cacheKey] = [
            'id' => (int) ($bestRow['id'] ?? 0),
            'codigo' => trim((string) ($bestRow['codigo'] ?? '')),
            'descripcion' => trim((string) ($bestRow['descripcion'] ?? '')),
        ];
    }

    /**
     * @return array<string,array{categoria:string,afiliacion_raw:string,empresa_seguro:string}>|null
     */
    private function resolveImagenMappedAffiliation(string $afiliacion): ?array
    {
        $key = $this->normalizeImagenAffiliationKey($afiliacion);
        if ($key === '') {
            return null;
        }

        $map = $this->imagenAfiliacionCategoriaMap();
        if (!isset($map[$key])) {
            return null;
        }

        return $map[$key];
    }

    private function normalizeImagenAffiliationKey(string $value): string
    {
        $value = $this->normalizarTexto($value);
        if ($value === '') {
            return '';
        }

        return str_replace([' ', '-'], '_', $value);
    }

    /**
     * @return array<string,array{categoria:string,afiliacion_raw:string,empresa_seguro:string}>
     */
    private function imagenAfiliacionCategoriaMap(): array
    {
        if (is_array($this->afiliacionCategoriaMapCache)) {
            return $this->afiliacionCategoriaMapCache;
        }

        if (
            !$this->columnExists('afiliacion_categoria_map', 'afiliacion_norm')
            || !$this->columnExists('afiliacion_categoria_map', 'categoria')
            || !$this->columnExists('afiliacion_categoria_map', 'afiliacion_raw')
        ) {
            return $this->afiliacionCategoriaMapCache = [];
        }

        $empresaSeguroSelect = $this->columnExists('afiliacion_categoria_map', 'empresa_seguro')
            ? 'empresa_seguro'
            : "'' AS empresa_seguro";

        $stmt = $this->db->query(
            "SELECT afiliacion_norm, categoria, afiliacion_raw, {$empresaSeguroSelect}
             FROM afiliacion_categoria_map
             WHERE TRIM(COALESCE(afiliacion_norm, '')) <> ''"
        );
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $map = [];
        foreach ($rows as $row) {
            $key = $this->normalizeImagenAffiliationKey((string) ($row['afiliacion_norm'] ?? ''));
            if ($key === '') {
                continue;
            }

            $map[$key] = [
                'categoria' => $this->normalizarTexto((string) ($row['categoria'] ?? '')),
                'afiliacion_raw' => trim((string) ($row['afiliacion_raw'] ?? '')),
                'empresa_seguro' => $this->afiliacionDimensions->resolveEmpresaLabel(
                    trim((string) ($row['empresa_seguro'] ?? '')) !== ''
                        ? (string) ($row['empresa_seguro'] ?? '')
                        : (string) ($row['afiliacion_raw'] ?? '')
                ),
            ];
        }

        return $this->afiliacionCategoriaMapCache = $map;
    }

    /**
     * @return array<int,array{id:int,codigo:string,descripcion:string,descripcion_norm:string,short_description_norm:string}>
     */
    private function tarifaDescriptionIndex(): array
    {
        if (is_array($this->tarifaDescriptionIndexCache)) {
            return $this->tarifaDescriptionIndexCache;
        }

        if (!$this->tableExists('tarifario_2014') || !$this->columnExists('tarifario_2014', 'codigo') || !$this->columnExists('tarifario_2014', 'descripcion')) {
            return $this->tarifaDescriptionIndexCache = [];
        }

        $select = $this->columnExists('tarifario_2014', 'short_description')
            ? ['id', 'codigo', 'descripcion', 'short_description']
            : ['id', 'codigo', 'descripcion'];

        $rows = Tarifario2014::query()->select($select)->get();
        $index = [];
        foreach ($rows as $row) {
            $index[] = [
                'id' => (int) $row->id,
                'codigo' => trim((string) ($row->codigo ?? '')),
                'descripcion' => trim((string) ($row->descripcion ?? '')),
                'descripcion_norm' => $this->normalizarTexto((string) ($row->descripcion ?? '')),
                'short_description_norm' => $this->normalizarTexto((string) ($row->short_description ?? '')),
            ];
        }

        return $this->tarifaDescriptionIndexCache = $index;
    }

    /**
     * @return array<string,float>
     */
    private function pricesForTarifaCode(int $codeId): array
    {
        if ($codeId <= 0) {
            return [];
        }

        if (array_key_exists($codeId, $this->codePriceCache)) {
            return $this->codePriceCache[$codeId];
        }

        try {
            return $this->codePriceCache[$codeId] = $this->codePriceService()->pricesForCode($codeId, $this->codePriceLevels());
        } catch (\Throwable) {
            return $this->codePriceCache[$codeId] = [];
        }
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
        } catch (\Throwable) {
            $this->codePriceLevelsCache = [];
        }

        return $this->codePriceLevelsCache;
    }

    /**
     * @param array<int, array{level_key:string,storage_key:string,title:string,category:string,source:string}> $levels
     */
    private function findImagenTarifaLevelKeyBySimilarity(string $candidate, array $levels, string $categoriaPreferida = ''): ?string
    {
        $candidateNorm = $this->normalizarTexto($candidate);
        if ($candidateNorm === '') {
            return null;
        }

        $categoriaPreferida = $this->normalizarTexto($categoriaPreferida);
        $cacheKey = $candidateNorm . '|' . $categoriaPreferida;
        if (array_key_exists($cacheKey, $this->levelKeyResolveCache)) {
            return $this->levelKeyResolveCache[$cacheKey];
        }

        $candidateTokens = $this->tokenizeImagenLookup($candidateNorm);
        $bestLevelKey = null;
        $bestScore = 0.0;

        foreach ($levels as $level) {
            $levelKey = trim((string) ($level['level_key'] ?? ''));
            if ($levelKey === '') {
                continue;
            }

            $titleNorm = $this->normalizarTexto((string) ($level['title'] ?? ''));
            $keyNorm = $this->normalizarTexto($levelKey);
            $score = max(
                $this->scoreImagenLookupSimilarity($candidateNorm, $candidateTokens, $titleNorm),
                $this->scoreImagenLookupSimilarity($candidateNorm, $candidateTokens, $keyNorm)
            );

            if ($score <= 0.0) {
                continue;
            }

            $levelCategory = $this->normalizarTexto((string) ($level['category'] ?? ''));
            if ($categoriaPreferida !== '' && $levelCategory === $categoriaPreferida) {
                $score += 4.0;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLevelKey = $levelKey;
            }
        }

        if ($bestScore < 72.0) {
            return $this->levelKeyResolveCache[$cacheKey] = null;
        }

        return $this->levelKeyResolveCache[$cacheKey] = $bestLevelKey;
    }

    /**
     * @param array<int,string> $candidateTokens
     */
    private function scoreImagenLookupSimilarity(string $candidateNorm, array $candidateTokens, string $probeNorm): float
    {
        $probeNorm = trim($probeNorm);
        if ($candidateNorm === '' || $probeNorm === '') {
            return 0.0;
        }

        if ($candidateNorm === $probeNorm) {
            return 100.0;
        }

        if (str_contains($probeNorm, $candidateNorm) || str_contains($candidateNorm, $probeNorm)) {
            return 94.0;
        }

        similar_text($candidateNorm, $probeNorm, $percent);
        $score = (float) $percent;

        $probeTokens = $this->tokenizeImagenLookup($probeNorm);
        if ($candidateTokens !== [] && $probeTokens !== []) {
            $intersection = array_intersect($candidateTokens, $probeTokens);
            $union = array_unique(array_merge($candidateTokens, $probeTokens));
            $jaccard = $union !== [] ? (count($intersection) / count($union)) * 100.0 : 0.0;

            $score = max($score, $jaccard + (count($intersection) >= 2 ? 12.0 : 0.0));
        }

        return min(100.0, $score);
    }

    /**
     * @return array<int,string>
     */
    private function tokenizeImagenLookup(string $text): array
    {
        $text = $this->normalizarTexto($text);
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', $text) ?: [],
            static fn (string $token): bool => $token !== '' && strlen($token) > 2
        ));
    }

    /**
     * @param array<int,float|int> $values
     */
    private function calcularPercentil(array $values, float $percent): ?float
    {
        if ($values === []) {
            return null;
        }

        $percent = max(0.0, min(1.0, $percent));
        sort($values, SORT_NUMERIC);
        $count = count($values);
        if ($count === 1) {
            return (float) $values[0];
        }

        $index = ($count - 1) * $percent;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        if ($lower === $upper) {
            return (float) $values[$lower];
        }

        $weight = $index - $lower;
        return ((float) $values[$lower] * (1 - $weight)) + ((float) $values[$upper] * $weight);
    }

    /**
     * @param array<string,string> $filters
     * @param array<int,array{value:string,label:string}> $afiliacionOptions
     * @param array<int,array{value:string,label:string}> $afiliacionCategoriaOptions
     * @param array<int,array{value:string,label:string}> $sedeOptions
     * @return array<int,array{label:string,value:string}>
     */
    private function buildImagenesDashboardFiltersSummary(
        array $filters,
        array $afiliacionOptions = [],
        array $afiliacionCategoriaOptions = [],
        array $seguroOptions = [],
        array $sedeOptions = []
    ): array {
        $summary = [];
        $map = [
            'fecha_inicio' => 'Desde',
            'fecha_fin' => 'Hasta',
            'tipo_examen' => 'Tipo examen',
            'paciente' => 'Paciente/Cédula',
            'estado_agenda' => 'Estado agenda',
        ];

        foreach ($map as $key => $label) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $summary[] = ['label' => $label, 'value' => $value];
        }

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string) ($filters['afiliacion'] ?? ''));
        if ($afiliacionFilter !== '') {
            $afiliacionLabel = $afiliacionFilter;
            foreach ($afiliacionOptions as $option) {
                if ((string) ($option['value'] ?? '') === $afiliacionFilter) {
                    $afiliacionLabel = (string) ($option['label'] ?? $afiliacionFilter);
                    break;
                }
            }
            $summary[] = ['label' => 'Empresa de seguro', 'value' => $afiliacionLabel];
        }

        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        if ($afiliacionCategoriaFilter !== '') {
            $categoriaLabel = $afiliacionCategoriaFilter;
            foreach ($afiliacionCategoriaOptions as $option) {
                if ((string) ($option['value'] ?? '') === $afiliacionCategoriaFilter) {
                    $categoriaLabel = (string) ($option['label'] ?? $afiliacionCategoriaFilter);
                    break;
                }
            }
            $summary[] = ['label' => 'Categoría de seguro', 'value' => $categoriaLabel];
        }

        $seguroFilter = $this->normalizeSeguroFilter((string) ($filters['seguro'] ?? ''));
        if ($seguroFilter !== '') {
            $seguroLabel = $seguroFilter;
            foreach ($seguroOptions as $option) {
                if ((string) ($option['value'] ?? '') === $seguroFilter) {
                    $seguroLabel = (string) ($option['label'] ?? $seguroFilter);
                    break;
                }
            }
            $summary[] = ['label' => 'Seguro / plan', 'value' => $seguroLabel];
        }

        $sedeFilter = $this->normalizeSedeFilter((string) ($filters['sede'] ?? ''));
        if ($sedeFilter !== '') {
            $sedeLabel = $sedeFilter;
            foreach ($sedeOptions as $option) {
                if ((string) ($option['value'] ?? '') === $sedeFilter) {
                    $sedeLabel = (string) ($option['label'] ?? $sedeFilter);
                    break;
                }
            }
            $summary[] = ['label' => 'Sede', 'value' => $sedeLabel];
        }

        return $summary;
    }

    private function normalizeAfiliacionFilter(string $afiliacionFilter): string
    {
        return $this->afiliacionDimensions->normalizeEmpresaFilter($afiliacionFilter);
    }

    private function normalizeAfiliacionExactFilter(string $afiliacionFilter): string
    {
        $value = strtolower(trim($afiliacionFilter));
        if ($value === '') {
            return '';
        }

        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
    }

    private function normalizeSeguroFilter(string $seguroFilter): string
    {
        return $this->afiliacionDimensions->normalizeSeguroFilter($seguroFilter);
    }

    private function normalizeAfiliacionCategoriaFilter(string $categoryFilter): string
    {
        return $this->afiliacionDimensions->normalizeCategoriaFilter($categoryFilter);
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
        if (str_contains($value, 'matriz')) {
            return 'MATRIZ';
        }

        return '';
    }

    private function imagenesSedeExpr(): string
    {
        return $this->imagenesSedeExprFromFields('pp.sede_departamento', 'pp.id_sede');
    }

    private function imagenesSedeExprFromFields(string $sedeDepartamentoExpr, string $idSedeExpr): string
    {
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF({$sedeDepartamentoExpr}, ''), NULLIF({$idSedeExpr}, ''), '')))";

        return "CASE
            WHEN {$rawExpr} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$rawExpr} LIKE '%matriz%' THEN 'MATRIZ'
            ELSE ''
        END";
    }

    private function formatCategoriaLabel(string $key): string
    {
        return $this->afiliacionDimensions->formatCategoriaLabel($key);
    }

    private function afiliacionGroupKeyExpr(string $rawAffiliationExpr, string $mapAlias = 'acm'): string
    {
        $context = $this->resolveAfiliacionDimensionsContext($rawAffiliationExpr, $mapAlias);

        return $context['empresa_key_expr'];
    }

    private function afiliacionLabelExpr(string $rawAffiliationExpr, string $mapAlias = 'acm'): string
    {
        $context = $this->resolveAfiliacionDimensionsContext($rawAffiliationExpr, $mapAlias);

        return $context['empresa_label_expr'];
    }

    private function seguroLabelExpr(string $rawAffiliationExpr, string $mapAlias = 'acm'): string
    {
        $context = $this->resolveAfiliacionDimensionsContext($rawAffiliationExpr, $mapAlias);

        return $context['seguro_label_expr'];
    }

    private function seguroKeyExpr(string $rawAffiliationExpr, string $mapAlias = 'acm'): string
    {
        $context = $this->resolveAfiliacionDimensionsContext($rawAffiliationExpr, $mapAlias);

        return $context['seguro_key_expr'];
    }

    /**
     * @return array{join:string,expr:string}
     */
    private function resolveAfiliacionDimensionsContext(string $rawAffiliationExpr, string $mapAlias = 'acm'): array
    {
        return $this->afiliacionDimensions->buildContext($rawAffiliationExpr, $mapAlias);
    }

    /**
     * @return array{join:string,expr:string}
     */
    private function resolveAfiliacionCategoriaContext(string $rawAffiliationExpr, string $mapAlias = 'acm'): array
    {
        $context = $this->resolveAfiliacionDimensionsContext($rawAffiliationExpr, $mapAlias);

        return ['join' => $context['join'], 'expr' => $context['categoria_expr']];
    }

    private function normalizeSqlText(string $expr): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$expr}, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N'), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'))";
    }

    private function normalizeSqlKey(string $expr): string
    {
        $normalized = $this->normalizeSqlText($expr);

        return "REPLACE(REPLACE({$normalized}, ' ', '_'), '-', '_')";
    }

    private function safeSqlDateExpr(string $expr): string
    {
        return "CASE
            WHEN CAST({$expr} AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
            ELSE DATE({$expr})
        END";
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => $table]);
        $exists = (int) $stmt->fetchColumn() > 0;
        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        $exists = (int) $stmt->fetchColumn() > 0;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }
}
