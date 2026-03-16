<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

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

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DB::connection()->getPdo();
        $this->afiliacionDimensions = new AfiliacionDimensionService($this->db);
    }

    /**
     * @param array<string,mixed> $query
     * @return array{filters:array<string,string>,rows:array<int,array<string,mixed>>}
     */
    public function imagenesRealizadas(array $query): array
    {
        $filters = $this->buildFilters($query);
        $filters['afiliacion_match_mode'] = 'exact';
        $rows = $this->fetchImagenesRealizadas($filters, true);
        $rows = array_map(fn(array $row): array => $this->decorateImagenRow($row), $rows);

        return [
            'filters' => $filters,
            'rows' => $rows,
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
        $dashboard = $this->buildImagenesDashboardSummary($rows, $filters);
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
     *     report:array<string,mixed>,
     *     total:int
     * }
     */
    public function imagenesDashboardExportPayload(array $query): array
    {
        $payload = $this->imagenesDashboard($query);
        $dashboard = is_array($payload['dashboard'] ?? null) ? $payload['dashboard'] : [];
        $detailRows = is_array($payload['detailRows'] ?? null) ? $payload['detailRows'] : [];
        $filtersSummary = is_array($payload['filtersSummary'] ?? null) ? $payload['filtersSummary'] : [];

        return [
            'filters' => $payload['filters'],
            'filtersSummary' => $filtersSummary,
            'dashboard' => $dashboard,
            'detailRows' => $detailRows,
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

        return [
            'fecha_inicio' => $this->normalizeDateFilter($fechaInicio, 'first day of this month'),
            'fecha_fin' => $this->normalizeDateFilter($fechaFin, 'last day of this month'),
            'afiliacion' => trim((string) ($query['afiliacion'] ?? '')),
            'afiliacion_categoria' => trim((string) ($query['afiliacion_categoria'] ?? '')),
            'seguro' => trim((string) ($query['seguro'] ?? '')),
            'sede' => trim((string) ($query['sede'] ?? '')),
            'tipo_examen' => trim((string) ($query['tipo_examen'] ?? '')),
            'paciente' => trim((string) ($query['paciente'] ?? '')),
            'estado_agenda' => trim((string) ($query['estado_agenda'] ?? '')),
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
            $this->getImagenesSeguroOptions(),
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
     *     estado_agenda?: string
     * } $filters
     * @return array<int,array<string,mixed>>
     */
    private function fetchImagenesRealizadas(array $filters = [], bool $includeFacturado = false): array
    {
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $displayAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), 'Sin afiliación')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr, 'iacm');
        $afiliacionExactExpr = 'TRIM(' . $this->normalizeSqlText($displayAfiliacionExpr) . ')';
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm');
        $seguroKeyExpr = $this->seguroKeyExpr($rawAfiliacionExpr, 'iacm');
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
        $nasAvailable = $this->tableExists('imagenes_nas_index') && $this->columnExists('imagenes_nas_index', 'form_id');
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
        $nasJoin = $nasAvailable ? 'LEFT JOIN imagenes_nas_index ini ON ini.form_id = pp.form_id' : '';

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
                {$afiliacionKeyExpr} AS afiliacion_key,
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
                pd.hc_number LIKE :paciente
                OR CONCAT_WS(' ', TRIM(pd.lname), TRIM(pd.lname2), TRIM(pd.fname), TRIM(pd.mname)) LIKE :paciente
                OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente
            )";
            $params[':paciente'] = '%' . $filters['paciente'] . '%';
        }

        if (!empty($filters['estado_agenda'])) {
            $sql .= ' AND TRIM(pp.estado_agenda) = :estado_agenda';
            $params[':estado_agenda'] = $filters['estado_agenda'];
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
            'join' => "LEFT JOIN ({$realSubquery}) bfr ON bfr.form_id = pp.form_id
            LEFT JOIN ({$publicSubquery}) bpub ON bpub.form_id = pp.form_id",
        ];
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
    private function getImagenesSeguroOptions(): array
    {
        return $this->afiliacionDimensions->getSeguroOptions('Todos los seguros');
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
        if ($this->isImagenEstadoCancelado($estadoAgenda)) {
            return 'CANCELADA';
        }
        if ($this->isImagenEstadoAusente($estadoAgenda)) {
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
        if (in_array($estadoRealizacion, ['REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'], true)) {
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
        return in_array($estadoRealizacion, ['FACTURADA', 'REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'], true);
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
            $esPendienteFacturar = !$facturado && in_array($estadoRealizacion, ['REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'], true);
            $montoPendienteEstimado = 0.0;
            $sinTarifaPublica = false;

            if ($esPendienteFacturar && $afiliacionCategoriaKey === 'publico') {
                $montoPendienteEstimado = $this->resolveImagenTarifaPublicaNivel3($tarifa ?? null);
                $sinTarifaPublica = $montoPendienteEstimado <= 0;
            }

            $output[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'form_id' => trim((string) ($row['form_id'] ?? '')),
                'fecha_examen' => $this->formatDashboardDate((string) ($row['fecha_examen'] ?? '')),
                'hc_number' => trim((string) ($row['hc_number'] ?? '')),
                'paciente' => trim((string) ($row['full_name'] ?? '')),
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
        $atendidos = (int) ($this->reportCardValue($cards, 'Atendidos') ?? 0);
        $informadas = (int) ($this->reportCardValue($cards, 'Informadas') ?? 0);
        $facturados = (int) ($this->reportCardValue($cards, 'Facturados') ?? 0);
        $pendientesFacturar = (int) ($meta['pendientes_facturar'] ?? 0);
        $pendientesFacturarPublico = (int) ($meta['pendientes_facturar_publico'] ?? 0);
        $pendientesFacturarPrivado = (int) ($meta['pendientes_facturar_privado'] ?? 0);
        $montoPendienteEstimadoPublico = (float) ($meta['monto_pendiente_estimado_publico'] ?? 0);
        $pendientesFacturarPublicoSinTarifa = (int) ($meta['pendientes_facturar_publico_sin_tarifa'] ?? 0);
        $produccionFacturada = (float) ($meta['produccion_facturada'] ?? 0);
        $produccionFacturadaPublico = (float) ($meta['produccion_facturada_publico'] ?? 0);
        $produccionFacturadaPrivado = (float) ($meta['produccion_facturada_privado'] ?? 0);
        $sla48 = trim((string) ($this->reportCardText($cards, 'SLA informe <= 48h') ?? '—'));
        $cumplimientoCita = trim((string) ($this->reportCardText($cards, 'Cumplimiento cita->realización') ?? '—'));
        $tatPromedio = ($meta['tat_promedio_horas'] ?? null) !== null ? number_format((float) $meta['tat_promedio_horas'], 2) . ' h' : '—';
        $tatMediana = ($meta['tat_mediana_horas'] ?? null) !== null ? number_format((float) $meta['tat_mediana_horas'], 2) . ' h' : '—';
        $tatP90 = ($meta['tat_p90_horas'] ?? null) !== null ? number_format((float) $meta['tat_p90_horas'], 2) . ' h' : '—';
        $traficoLabels = is_array($charts['trafico_dia_semana']['labels'] ?? null) ? $charts['trafico_dia_semana']['labels'] : [];
        $traficoValues = is_array($charts['trafico_dia_semana']['values'] ?? null) ? $charts['trafico_dia_semana']['values'] : [];
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
        $hallazgos[] = sprintf(
            'Se analizaron %s estudios; %s atendidos, %s informados y %s facturados.',
            number_format($total),
            number_format($atendidos),
            number_format($informadas),
            number_format($facturados)
        );
        if ($pendientesFacturar > 0) {
            $hallazgos[] = sprintf(
                'El backlog atendido sin facturar es de %s casos: %s públicos y %s privados.',
                number_format($pendientesFacturar),
                number_format($pendientesFacturarPublico),
                number_format($pendientesFacturarPrivado)
            );
        }
        if ($montoPendienteEstimadoPublico > 0) {
            $hallazgos[] = sprintf(
                'El pendiente público estimado asciende a $%s usando tarifario 2014 nivel 3.',
                number_format($montoPendienteEstimadoPublico, 2)
            );
        }
        if ($pendientesFacturarPublicoSinTarifa > 0) {
            $hallazgos[] = sprintf(
                'Hay %s casos públicos sin tarifa nivel 3 disponible; requieren revisión tarifaria.',
                number_format($pendientesFacturarPublicoSinTarifa)
            );
        }
        if ($sla48 !== '—') {
            $hallazgos[] = sprintf('El SLA de informe <= 48h se ubica en %s con TAT promedio de %s.', $sla48, $tatPromedio);
        }

        $methodology = [
            'El universo considera procedimientos de IMAGENES dentro del rango filtrado con estado de agenda no vacío.',
            'La realización se clasifica con evidencia operativa, informes registrados y presencia de archivos en NAS o índice NAS.',
            'La facturación se resuelve con evidencia combinada de billing_facturacion_real y billing_main + billing_procedimientos.',
            'Para backlog público se estima el pendiente con tarifario_2014 nivel 3 cuando existe coincidencia de código.',
            'Los casos públicos sin tarifa nivel 3 quedan identificados por separado para auditoría.',
        ];

        $generalKpis = [
            ['label' => 'Total estudios', 'value' => $this->reportCardText($cards, 'Total estudios') ?? '0', 'note' => $this->reportCardText($cards, 'Total estudios', 'hint') ?? ''],
            ['label' => 'Atendidos', 'value' => $this->reportCardText($cards, 'Atendidos') ?? '0', 'note' => $this->reportCardText($cards, 'Atendidos', 'hint') ?? ''],
            ['label' => 'Informadas', 'value' => $this->reportCardText($cards, 'Informadas') ?? '0', 'note' => $this->reportCardText($cards, 'Informadas', 'hint') ?? ''],
            ['label' => 'Facturados', 'value' => $this->reportCardText($cards, 'Facturados') ?? '0', 'note' => $this->reportCardText($cards, 'Facturados', 'hint') ?? ''],
            ['label' => 'Pendiente de facturar', 'value' => $this->reportCardText($cards, 'Pendiente de facturar') ?? '0', 'note' => $this->reportCardText($cards, 'Pendiente de facturar', 'hint') ?? ''],
            ['label' => 'Pérdida', 'value' => $this->reportCardText($cards, 'Pérdida') ?? '0', 'note' => $this->reportCardText($cards, 'Pérdida', 'hint') ?? ''],
        ];

        $temporalKpis = [
            ['label' => 'SLA informe <= 48h', 'value' => $sla48, 'note' => $this->reportCardText($cards, 'SLA informe <= 48h', 'hint') ?? ''],
            ['label' => 'TAT promedio', 'value' => $tatPromedio, 'note' => 'Tiempo promedio entre examen e informe.'],
            ['label' => 'TAT mediana', 'value' => $tatMediana, 'note' => 'Mitad de los informes queda por debajo de este tiempo.'],
            ['label' => 'TAT P90', 'value' => $tatP90, 'note' => '90% de los informes cae bajo este umbral.'],
            ['label' => 'Cumplimiento cita->realización', 'value' => $cumplimientoCita, 'note' => $this->reportCardText($cards, 'Cumplimiento cita->realización', 'hint') ?? ''],
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
                'label' => 'Facturado público',
                'value' => '$' . number_format($produccionFacturadaPublico, 2),
                'meaning' => 'Producción real ya cerrada para afiliaciones públicas.',
                'formula' => 'SUM(total_produccion) donde afiliacion_categoria = publico y existe evidencia de billing.',
            ],
            [
                'label' => 'Facturado privado',
                'value' => '$' . number_format($produccionFacturadaPrivado, 2),
                'meaning' => 'Producción real ya cerrada para afiliaciones privadas.',
                'formula' => 'SUM(total_produccion) donde afiliacion_categoria = privado y existe evidencia de billing.',
            ],
            [
                'label' => 'Pendiente facturar pública',
                'value' => number_format($pendientesFacturarPublico),
                'meaning' => 'Casos públicos atendidos sin billing cerrado.',
                'formula' => 'Estado realizado con evidencia técnica y sin evidencia de billing.',
            ],
            [
                'label' => 'Pendiente facturar privada',
                'value' => number_format($pendientesFacturarPrivado),
                'meaning' => 'Casos privados atendidos sin billing real.',
                'formula' => 'Estado realizado con evidencia técnica y sin evidencia de billing.',
            ],
            [
                'label' => 'Pendiente estimado público',
                'value' => '$' . number_format($montoPendienteEstimadoPublico, 2),
                'meaning' => 'Valor potencial pendiente en públicos.',
                'formula' => 'SUM(valor_facturar_nivel3) de tarifario_2014 para públicos pendientes de facturar.',
            ],
        ];

        $tables = [
            [
                'title' => 'Backlog de facturación por categoría',
                'subtitle' => 'Separación entre casos ya facturados y backlog atendido pendiente.',
                'columns' => ['Categoría', 'Facturados', 'Pendiente facturar', 'Pendiente estimado'],
                'rows' => [
                    ['Pública', number_format((int) ($meta['facturados_publico'] ?? 0)), number_format($pendientesFacturarPublico), '$' . number_format($montoPendienteEstimadoPublico, 2)],
                    ['Privada', number_format((int) ($meta['facturados_privado'] ?? 0)), number_format($pendientesFacturarPrivado), '—'],
                ],
                'empty_message' => 'Sin backlog de facturación para el rango seleccionado.',
            ],
            [
                'title' => 'Rendimiento económico',
                'subtitle' => 'Producción real facturada vs oportunidad económica pública aún abierta.',
                'columns' => ['Métrica', 'Valor'],
                'rows' => [
                    ['Producción facturada real', '$' . number_format($produccionFacturada, 2)],
                    ['Pendiente estimado público', '$' . number_format($montoPendienteEstimadoPublico, 2)],
                    ['Ticket promedio facturado', '$' . number_format((float) ($meta['ticket_promedio_facturado'] ?? 0), 2)],
                    ['Procedimientos facturados', number_format((int) ($meta['procedimientos_facturados'] ?? 0))],
                ],
                'empty_message' => 'Sin datos económicos para el rango seleccionado.',
            ],
        ];

        return [
            'scopeNotice' => 'Este reporte consolida actividad operativa, evidencia técnica NAS/informe y cierre económico para estudios de imágenes.',
            'filtersSummary' => $filtersSummary,
            'hallazgosClave' => $hallazgos,
            'methodology' => $methodology,
            'generalKpis' => $generalKpis,
            'temporalKpis' => $temporalKpis,
            'economicKpis' => $economicKpis,
            'tables' => $tables,
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
    private function buildImagenesDashboardSummary(array $rows, array $filters): array
    {
        $today = new DateTimeImmutable('today');
        $todayTs = $today->getTimestamp();
        $total = count($rows);

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
        $pendientesFacturarPublico = 0;
        $pendientesFacturarPrivado = 0;
        $montoPendienteEstimadoPublico = 0.0;
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
                }
            }

            if ($estadoRealizacion === 'CANCELADA') {
                $canceladas++;
            } elseif ($estadoRealizacion === 'AUSENTE') {
                $ausentes++;
            } elseif ($estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
                $sinCierre++;
            }

            $esPendienteFacturar = !$facturado && in_array($estadoRealizacion, ['REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'], true);
            if ($esPendienteFacturar) {
                $pendientesFacturar++;
                $atendidosPendientesFacturar++;
                if ($esPublico) {
                    $pendientesFacturarPublico++;
                } elseif ($esPrivado) {
                    $pendientesFacturarPrivado++;
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

            if ($esPendienteFacturar && $esPublico) {
                $montoEstimadoPublico = $this->resolveImagenTarifaPublicaNivel3($tarifa ?? null);
                if ($montoEstimadoPublico > 0) {
                    $montoPendienteEstimadoPublico += $montoEstimadoPublico;
                } else {
                    $pendientesFacturarPublicoSinTarifa++;
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
        $perdidas = $canceladas + $ausentes;
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

        return [
            'cards' => [
                ['label' => 'Total estudios', 'value' => $total, 'hint' => $rangeLabel !== '' ? ('Rango: ' . $rangeLabel) : 'Sin rango'],
                ['label' => 'Atendidos', 'value' => $examenesRealizados, 'hint' => $total > 0 ? (number_format(($examenesRealizados * 100) / $total, 1) . '% del total') : '0.0% del total'],
                ['label' => 'Facturados', 'value' => $facturados, 'hint' => $examenesRealizados > 0 ? (number_format(($facturados * 100) / $examenesRealizados, 1) . '% de atendidos') : '0.0% de atendidos'],
                ['label' => 'Pendiente de pago', 'value' => $pendientesPago, 'hint' => 'Estado en facturación real marcado como pendiente/cartera'],
                ['label' => 'Cancelados', 'value' => $canceladas, 'hint' => 'Cierre operativo cancelado en agenda'],
                ['label' => 'Pendiente de facturar', 'value' => $pendientesFacturar, 'hint' => 'Realizadas con evidencia técnica aún sin billing real'],
                ['label' => 'Atendidos pendientes facturar', 'value' => $atendidosPendientesFacturar, 'hint' => $atendidosPendientesFacturar > 0 ? ($pendientesFacturarPublico . ' públicos / ' . $pendientesFacturarPrivado . ' privados') : 'Sin backlog atendido'],
                ['label' => 'Pendiente facturar pública', 'value' => $pendientesFacturarPublico, 'hint' => $pendientesFacturarPublico > 0 ? ('$' . number_format($montoPendienteEstimadoPublico, 2) . ' estimado nivel 3') : 'Sin backlog público'],
                ['label' => 'Pendiente facturar privada', 'value' => $pendientesFacturarPrivado, 'hint' => $pendientesFacturarPrivado > 0 ? 'Casos atendidos sin billing real' : 'Sin backlog privado'],
                ['label' => 'Facturación cancelada', 'value' => $facturacionCancelada, 'hint' => 'Registros con estado cancelado/anulado en facturación'],
                ['label' => 'Pérdida', 'value' => $perdidas, 'hint' => $canceladas . ' canceladas, ' . $ausentes . ' ausentes'],
                ['label' => 'Producción facturada', 'value' => '$' . number_format($produccionFacturada, 2), 'hint' => 'Monto real facturado en el rango.'],
                ['label' => 'Pendiente estimado público', 'value' => '$' . number_format($montoPendienteEstimadoPublico, 2), 'hint' => $pendientesFacturarPublicoSinTarifa > 0 ? ($pendientesFacturarPublicoSinTarifa . ' públicos sin tarifa nivel 3') : 'Estimado con tarifario 2014 nivel 3'],
                ['label' => 'Ticket promedio facturado', 'value' => '$' . number_format($ticketPromedioFacturado, 2), 'hint' => $facturados > 0 ? ('Promedio por ' . $facturados . ' estudios facturados') : 'Sin estudios facturados'],
                ['label' => 'Procedimientos facturados', 'value' => $procedimientosFacturados, 'hint' => '$' . number_format($produccionPromedioPorEstudio, 2) . ' promedio por estudio'],
                ['label' => 'Día pico de tráfico', 'value' => $maxTraficoDiaLabel, 'hint' => $maxTraficoValor > 0 ? ($maxTraficoValor . ' estudios') : 'Sin datos'],
                ['label' => 'Informadas', 'value' => $informados, 'hint' => $total > 0 ? (number_format(($informados * 100) / $total, 1) . '% del total') : '0.0% del total'],
                ['label' => 'Cumplimiento cita->realización', 'value' => $cumplimientoCitaPct !== null ? (number_format($cumplimientoCitaPct, 1) . '%') : '—', 'hint' => 'Objetivo: subir conversión de cita a examen'],
                ['label' => 'Facturados e informados', 'value' => $facturadosEInformados, 'hint' => 'Cruce OK'],
                ['label' => 'Facturados sin informar', 'value' => $facturadosSinInformar, 'hint' => 'Debe tender a 0'],
                ['label' => 'Informados sin facturar', 'value' => $informadosSinFacturar, 'hint' => 'Pendiente de facturar'],
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
                'atendidos_pendientes_facturar' => $atendidosPendientesFacturar,
                'pendientes_facturar_publico' => $pendientesFacturarPublico,
                'pendientes_facturar_privado' => $pendientesFacturarPrivado,
                'pendientes_facturar_publico_sin_tarifa' => $pendientesFacturarPublicoSinTarifa,
                'monto_pendiente_estimado_publico' => round($montoPendienteEstimadoPublico, 2),
                'facturados_publico' => $facturadosPublico,
                'facturados_privado' => $facturadosPrivado,
                'pendientes_pago' => $pendientesPago,
                'cancelados' => $canceladas,
                'facturacion_cancelada' => $facturacionCancelada,
                'perdidas' => $perdidas,
                'sin_cierre_operativo' => $sinCierre,
            ],
            'charts' => [
                'serie_diaria' => [
                    'labels' => array_keys($dailyMap),
                    'realizados' => array_values(array_map(static fn(array $item): int => (int) ($item['realizados'] ?? 0), $dailyMap)),
                    'informados' => array_values(array_map(static fn(array $item): int => (int) ($item['informados'] ?? 0), $dailyMap)),
                ],
                'mix_codigos' => [
                    'labels' => array_keys($mixTop),
                    'values' => array_values($mixTop),
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
                        $canceladas,
                    ],
                ],
                'citas_vs_realizados' => [
                    'labels' => ['Citas generadas', 'Realizadas', 'Pérdida'],
                    'values' => [$citasGeneradas, $examenesRealizados, $perdidas],
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
                    ],
                ],
                'rendimiento_economico' => [
                    'labels' => ['Facturado real', 'Pendiente público estimado'],
                    'values' => [
                        round($produccionFacturada, 2),
                        round($montoPendienteEstimadoPublico, 2),
                    ],
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
            $summary[] = ['label' => 'Seguro', 'value' => $seguroLabel];
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
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF(pp.sede_departamento, ''), NULLIF(pp.id_sede, ''), '')))";

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
