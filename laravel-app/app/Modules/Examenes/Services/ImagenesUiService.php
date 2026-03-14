<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use DateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;

class ImagenesUiService
{
    private const IESS_AFFILIATIONS = [
        'contribuyente voluntario',
        'conyuge',
        'conyuge pensionista',
        'seguro campesino',
        'seguro campesino jubilado',
        'seguro general',
        'seguro general jubilado',
        'seguro general por montepio',
        'seguro general tiempo parcial',
        'hijos dependientes',
        'sin cobertura',
    ];

    private PDO $db;

    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DB::connection()->getPdo();
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
     * @return array{filters:array<string,string>,rows:array<int,array<string,mixed>>,dashboard:array<string,mixed>,afiliacionOptions:array<int,array{value:string,label:string}>,afiliacionCategoriaOptions:array<int,array{value:string,label:string}>,sedeOptions:array<int,array{value:string,label:string}>,filtersSummary:array<int,array{label:string,value:string}>,detailRows:array<int,array<string,mixed>>}
     */
    public function imagenesDashboard(array $query): array
    {
        $filters = $this->buildFilters($query);
        $filters['afiliacion_match_mode'] = 'grouped';
        $rows = $this->fetchImagenesRealizadas($filters, true);
        $rows = array_map(fn(array $row): array => $this->decorateImagenRow($row), $rows);
        $dashboard = $this->buildImagenesDashboardSummary($rows, $filters);
        $detailRows = $this->buildImagenesDashboardDetailRows($rows);
        [$afiliacionOptions, $afiliacionCategoriaOptions, $sedeOptions] = $this->resolveImagenesDashboardAffiliationOptions($filters);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'dashboard' => $dashboard,
            'detailRows' => $detailRows,
            'filtersSummary' => $this->buildImagenesDashboardFiltersSummary($filters, $afiliacionOptions, $afiliacionCategoriaOptions, $sedeOptions),
            'afiliacionOptions' => $afiliacionOptions,
            'afiliacionCategoriaOptions' => $afiliacionCategoriaOptions,
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
     * @return array{filters:array<string,string>,filtersSummary:array<int,array{label:string,value:string}>,dashboard:array<string,mixed>,detailRows:array<int,array<string,mixed>>,total:int}
     */
    public function imagenesDashboardExportPayload(array $query): array
    {
        $payload = $this->imagenesDashboard($query);

        return [
            'filters' => $payload['filters'],
            'filtersSummary' => $payload['filtersSummary'],
            'dashboard' => $payload['dashboard'],
            'detailRows' => $payload['detailRows'],
            'total' => count($payload['detailRows']),
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
            'sede' => trim((string) ($query['sede'] ?? '')),
            'tipo_examen' => trim((string) ($query['tipo_examen'] ?? '')),
            'paciente' => trim((string) ($query['paciente'] ?? '')),
            'estado_agenda' => trim((string) ($query['estado_agenda'] ?? '')),
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array{0:array<int,array{value:string,label:string}>,1:array<int,array{value:string,label:string}>,2:array<int,array{value:string,label:string}>}
     */
    private function resolveImagenesDashboardAffiliationOptions(array $filters): array
    {
        $fechaInicio = trim((string) ($filters['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($filters['fecha_fin'] ?? ''));
        $sedeOptions = [
            ['value' => '', 'label' => 'Todas las sedes'],
            ['value' => 'MATRIZ', 'label' => 'MATRIZ'],
            ['value' => 'CEIBOS', 'label' => 'CEIBOS'],
        ];

        if ($fechaInicio === '' || $fechaFin === '') {
            return [
                [['value' => '', 'label' => 'Todas'], ['value' => 'iess', 'label' => 'IESS']],
                [['value' => '', 'label' => 'Todas las categorías'], ['value' => 'publico', 'label' => 'Pública'], ['value' => 'privado', 'label' => 'Privada']],
                $sedeOptions,
            ];
        }

        return [
            $this->getImagenesAfiliacionOptions($fechaInicio, $fechaFin),
            $this->getImagenesAfiliacionCategoriaOptions($fechaInicio, $fechaFin),
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
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr);
        $afiliacionExactExpr = 'TRIM(' . $this->normalizeSqlText($displayAfiliacionExpr) . ')';
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm');
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

        $hasFacturacionReal = $this->tableExists('billing_facturacion_real')
            && $this->columnExists('billing_facturacion_real', 'form_id')
            && $this->columnExists('billing_facturacion_real', 'monto_honorario');
        $facturadoSelect = $includeFacturado && $hasFacturacionReal
            ? "CASE
                    WHEN bfr.billing_id IS NULL AND COALESCE(bfr.total_produccion, 0) <= 0 THEN 0
                    ELSE 1
               END AS facturado,
               bfr.billing_id,
               bfr.fecha_facturacion,
               bfr.fecha_atencion,
               COALESCE(bfr.total_produccion, 0) AS total_produccion,
               COALESCE(bfr.procedimientos_facturados, 0) AS procedimientos_facturados,
               bfr.numero_factura,
               bfr.factura_id"
            : "0 AS facturado,
               NULL AS billing_id,
               NULL AS fecha_facturacion,
               NULL AS fecha_atencion,
               0 AS total_produccion,
               0 AS procedimientos_facturados,
               NULL AS numero_factura,
               NULL AS factura_id";
        $facturadoJoin = $includeFacturado && $hasFacturacionReal
            ? "LEFT JOIN (
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
                    MAX(NULLIF(TRIM(COALESCE(bfr.numero_factura, '')), '')) AS numero_factura,
                    MAX(NULLIF(TRIM(COALESCE(bfr.factura_id, '')), '')) AS factura_id,
                    COALESCE(SUM(bfr.monto_honorario), 0) AS total_produccion,
                    COUNT(*) AS procedimientos_facturados
                FROM billing_facturacion_real bfr
                WHERE bfr.form_id IS NOT NULL AND TRIM(bfr.form_id) <> ''
                GROUP BY bfr.form_id
            ) bfr ON bfr.form_id = pp.form_id"
            : '';

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
                {$facturadoSelect}
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
            {$imagenInformeJoin}
            {$nasJoin}
            {$categoriaContext['join']}
            {$facturadoJoin}
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

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function getImagenesAfiliacionOptions(string $startDate, string $endDate): array
    {
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr($rawAfiliacionExpr);
        $afiliacionLabelExpr = $this->afiliacionLabelExpr($rawAfiliacionExpr);

        $sql = "SELECT x.value_key, MAX(x.value_label) AS value_label
            FROM (
                SELECT
                    {$afiliacionKeyExpr} AS value_key,
                    {$afiliacionLabelExpr} AS value_label
                FROM procedimiento_proyectado pp
                LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
                WHERE pp.fecha BETWEEN :fecha_inicio AND :fecha_fin
                  AND pp.estado_agenda IS NOT NULL
                  AND TRIM(pp.estado_agenda) <> ''
                  AND UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'IMAGENES%'
            ) x
            GROUP BY x.value_key
            ORDER BY value_label ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fecha_inicio' => $startDate,
            ':fecha_fin' => $endDate,
        ]);

        $options = [
            ['value' => '', 'label' => 'Todas'],
            ['value' => 'iess', 'label' => 'IESS'],
        ];
        $seen = ['' => true, 'iess' => true];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = trim((string) ($row['value_key'] ?? ''));
            $label = trim((string) ($row['value_label'] ?? ''));
            if ($key === '' || $label === '' || isset($seen[$key])) {
                continue;
            }
            $options[] = ['value' => $key, 'label' => $label];
            $seen[$key] = true;
        }

        return $options;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function getImagenesAfiliacionCategoriaOptions(string $startDate, string $endDate): array
    {
        $rawAfiliacionExpr = "COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '')";
        $categoriaContext = $this->resolveAfiliacionCategoriaContext($rawAfiliacionExpr, 'iacm');

        $sql = "SELECT
                {$categoriaContext['expr']} AS categoria,
                COUNT(*) AS total
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
            {$categoriaContext['join']}
            WHERE pp.fecha BETWEEN :fecha_inicio AND :fecha_fin
              AND pp.estado_agenda IS NOT NULL
              AND TRIM(pp.estado_agenda) <> ''
              AND UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'IMAGENES%'
            GROUP BY {$categoriaContext['expr']}
            ORDER BY total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fecha_inicio' => $startDate,
            ':fecha_fin' => $endDate,
        ]);

        $options = [
            ['value' => '', 'label' => 'Todas las categorías'],
            ['value' => 'publico', 'label' => 'Pública'],
            ['value' => 'privado', 'label' => 'Privada'],
        ];
        $seen = ['' => true, 'publico' => true, 'privado' => true];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = trim((string) ($row['categoria'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $options[] = [
                'value' => $key,
                'label' => $this->formatCategoriaLabel($key),
            ];
            $seen[$key] = true;
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorateImagenRow(array $row): array
    {
        $estadoAgenda = trim((string) ($row['estado_agenda'] ?? ''));
        $estadoRealizacion = $this->resolveImagenRealizationState($row);
        $estadoFacturacion = $this->resolveImagenBillingState($estadoRealizacion, (int) ($row['facturado'] ?? 0) === 1);
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

    private function resolveImagenBillingState(string $estadoRealizacion, bool $facturado): string
    {
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
    private function obtenerTarifarioPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT codigo, descripcion, short_description
             FROM tarifario_procedimientos
             WHERE codigo = :codigo
             LIMIT 1'
        );
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
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
                if (!isset($tarifarioCache[$codigo])) {
                    $tarifarioCache[$codigo] = $this->obtenerTarifarioPorCodigo($codigo);
                }
                $tarifa = $tarifarioCache[$codigo];
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
            $estadoFacturacion = (string) ($row['estado_facturacion'] ?? $this->resolveImagenBillingState($estadoRealizacion, (int) ($row['facturado'] ?? 0) === 1));
            $estadoInforme = (string) ($row['estado_informe'] ?? $this->resolveImagenInformeState($row));
            $informado = $estadoInforme === 'INFORMADA';
            $pendienteInformar = $estadoInforme === 'PENDIENTE_INFORMAR';
            $totalProduccion = round((float) ($row['total_produccion'] ?? 0), 2);

            $output[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'form_id' => trim((string) ($row['form_id'] ?? '')),
                'fecha_examen' => $this->formatDashboardDate((string) ($row['fecha_examen'] ?? '')),
                'hc_number' => trim((string) ($row['hc_number'] ?? '')),
                'paciente' => trim((string) ($row['full_name'] ?? '')),
                'afiliacion' => trim((string) ($row['afiliacion'] ?? '')),
                'estado_agenda' => $estadoAgenda,
                'cita_generada' => $this->isImagenCitaGeneradaEstado($estadoAgenda),
                'examen_realizado' => $this->isImagenEstadoRealizado($estadoRealizacion),
                'estado_realizacion' => $estadoRealizacion,
                'estado_facturacion' => $estadoFacturacion,
                'estado_informe' => $estadoInforme,
                'informado' => $informado,
                'pendiente_informar' => $pendienteInformar,
                'nas_has_files' => ((int) ($row['nas_has_files'] ?? 0) === 1) || ((int) ($row['nas_files_count'] ?? 0) > 0),
                'nas_files_count' => (int) ($row['nas_files_count'] ?? 0),
                'nas_scan_status' => trim((string) ($row['nas_scan_status'] ?? '')),
                'nas_last_scanned_at' => $this->formatDashboardDate((string) ($row['nas_last_scanned_at'] ?? '')),
                'facturado' => (int) ($row['facturado'] ?? 0) === 1,
                'produccion' => $totalProduccion,
                'procedimientos_facturados' => (int) ($row['procedimientos_facturados'] ?? 0),
                'fecha_facturacion' => $this->formatDashboardDate((string) ($row['fecha_facturacion'] ?? '')),
                'codigo' => $codigo,
                'examen' => $examen,
            ];
        }

        return $output;
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
        $facturadosEInformados = 0;
        $facturadosSinInformar = 0;
        $informadosSinFacturar = 0;
        $citasGeneradas = 0;
        $examenesRealizados = 0;
        $canceladas = 0;
        $ausentes = 0;
        $sinCierre = 0;
        $produccionFacturada = 0.0;
        $procedimientosFacturados = 0;
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
            $estadoInforme = (string) ($row['estado_informe'] ?? $this->resolveImagenInformeState($row));
            $informado = $estadoInforme === 'INFORMADA';
            $facturado = (int) ($row['facturado'] ?? 0) === 1;
            $realizado = $this->isImagenEstadoRealizado($estadoRealizacion);
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

            if ($facturado) {
                $facturados++;
            }

            $produccionFacturada += max(0.0, (float) ($row['total_produccion'] ?? 0));
            $procedimientosFacturados += max(0, (int) ($row['procedimientos_facturados'] ?? 0));

            if ($estadoRealizacion === 'CANCELADA') {
                $canceladas++;
            } elseif ($estadoRealizacion === 'AUSENTE') {
                $ausentes++;
            } elseif ($estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
                $sinCierre++;
            }

            if (!$facturado && in_array($estadoRealizacion, ['REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'], true)) {
                $pendientesFacturar++;
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
                if (!isset($tarifarioCache[$codigo])) {
                    $tarifarioCache[$codigo] = $this->obtenerTarifarioPorCodigo($codigo);
                }
                $tarifa = $tarifarioCache[$codigo];
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
                ['label' => 'Realizadas', 'value' => $examenesRealizados, 'hint' => $total > 0 ? (number_format(($examenesRealizados * 100) / $total, 1) . '% del total') : '0.0% del total'],
                ['label' => 'Facturadas', 'value' => $facturados, 'hint' => $examenesRealizados > 0 ? (number_format(($facturados * 100) / $examenesRealizados, 1) . '% de realizadas') : '0.0% de realizadas'],
                ['label' => 'Pendiente de informar', 'value' => $pendientesInformar, 'hint' => $examenesRealizados > 0 ? (number_format(($pendientesInformar * 100) / $examenesRealizados, 1) . '% de realizadas') : '0.0% de realizadas'],
                ['label' => 'Pendiente de facturar', 'value' => $pendientesFacturar, 'hint' => 'Realizadas con evidencia técnica aún sin billing real'],
                ['label' => 'Pérdida', 'value' => $perdidas, 'hint' => $canceladas . ' canceladas, ' . $ausentes . ' ausentes'],
                ['label' => 'Sin cierre operativo', 'value' => $sinCierre, 'hint' => 'Sin NAS, sin informe y sin billing'],
                ['label' => 'Producción facturada', 'value' => '$' . number_format($produccionFacturada, 2), 'hint' => 'Monto real facturado en el rango.'],
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
                'ticket_promedio_facturado' => round($ticketPromedioFacturado, 2),
                'procedimientos_facturados' => $procedimientosFacturados,
                'produccion_promedio_por_estudio' => round($produccionPromedioPorEstudio, 2),
                'pendientes_informar' => $pendientesInformar,
                'pendientes_facturar' => $pendientesFacturar,
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
                    'labels' => ['Facturada', 'Realizada con archivos', 'Realizada informada', 'Sin cierre operativo'],
                    'values' => [
                        (int) ($estadoRealCounts['FACTURADA'] ?? 0),
                        (int) ($estadoRealCounts['REALIZADA_CON_ARCHIVOS'] ?? 0),
                        (int) ($estadoRealCounts['REALIZADA_INFORMADA'] ?? 0),
                        (int) ($estadoRealCounts['SIN_CIERRE_OPERATIVO'] ?? 0),
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
            ],
        ];
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
            $summary[] = ['label' => 'Afiliación', 'value' => $afiliacionLabel];
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
            $summary[] = ['label' => 'Categoría de afiliación', 'value' => $categoriaLabel];
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
        $value = strtolower(trim($afiliacionFilter));
        if ($value === '') {
            return '';
        }

        $comparisonValue = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $comparisonValue = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $comparisonValue)) ?? $comparisonValue);

        if (in_array($comparisonValue, ['sin convenio', 'sin afiliacion'], true) || $value === 'sin_convenio') {
            return 'sin_convenio';
        }
        if ($comparisonValue === 'iess' || in_array($comparisonValue, self::IESS_AFFILIATIONS, true)) {
            return 'iess';
        }

        return $value;
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

    private function normalizeAfiliacionCategoriaFilter(string $categoryFilter): string
    {
        $value = strtolower(trim($categoryFilter));
        if ($value === 'publica') {
            return 'publico';
        }
        if ($value === 'privada') {
            return 'privado';
        }

        return $value;
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
        return match ($key) {
            'publico' => 'Pública',
            'privado' => 'Privada',
            'particular' => 'Particular',
            'fundacional' => 'Fundacional',
            'otros' => 'Otros',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    private function afiliacionGroupKeyExpr(string $rawAffiliationExpr): string
    {
        $col = "LOWER(TRIM(COALESCE({$rawAffiliationExpr}, '')))";

        return "CASE
            WHEN {$col} IN (" . $this->iessAffiliationsSqlList() . ") THEN 'iess'
            WHEN {$col} = '' THEN 'sin_convenio'
            ELSE {$col}
        END";
    }

    private function afiliacionLabelExpr(string $rawAffiliationExpr): string
    {
        $col = "LOWER(TRIM(COALESCE({$rawAffiliationExpr}, '')))";

        return "CASE
            WHEN {$col} IN (" . $this->iessAffiliationsSqlList() . ") THEN 'IESS'
            WHEN {$col} = '' THEN 'Sin convenio'
            ELSE TRIM({$rawAffiliationExpr})
        END";
    }

    private function iessAffiliationsSqlList(): string
    {
        return "'" . implode("','", self::IESS_AFFILIATIONS) . "'";
    }

    /**
     * @return array{join:string,expr:string}
     */
    private function resolveAfiliacionCategoriaContext(string $rawAffiliationExpr, string $mapAlias = 'acm'): array
    {
        $afiliacionNormExpr = $this->normalizeSqlKey($rawAffiliationExpr);
        $fallbackExpr = "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'otros'
            WHEN {$afiliacionNormExpr} LIKE '%particular%' THEN 'particular'
            WHEN {$afiliacionNormExpr} LIKE '%fundacion%' OR {$afiliacionNormExpr} LIKE '%fundacional%' THEN 'fundacional'
            WHEN {$afiliacionNormExpr} REGEXP 'iess|issfa|isspol|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|publico' THEN 'publico'
            ELSE 'privado'
        END";

        if (
            $this->tableExists('afiliacion_categoria_map')
            && $this->columnExists('afiliacion_categoria_map', 'afiliacion_norm')
            && $this->columnExists('afiliacion_categoria_map', 'categoria')
        ) {
            $join = "LEFT JOIN afiliacion_categoria_map {$mapAlias}
                     ON ({$mapAlias}.afiliacion_norm COLLATE utf8mb4_unicode_ci)
                      = ({$afiliacionNormExpr} COLLATE utf8mb4_unicode_ci)";
            $expr = "LOWER(COALESCE(NULLIF({$mapAlias}.categoria, ''), {$fallbackExpr}))";

            return ['join' => $join, 'expr' => $expr];
        }

        return ['join' => '', 'expr' => $fallbackExpr];
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
