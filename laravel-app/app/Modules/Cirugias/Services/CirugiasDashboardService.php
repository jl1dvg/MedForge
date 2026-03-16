<?php

namespace App\Modules\Cirugias\Services;

use DateTimeImmutable;
use App\Modules\Cirugias\Models\Cirugia;
use PDO;

class CirugiasDashboardService
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

    public function __construct(private PDO $db)
    {
    }

    public function getAfiliacionOptions(string $start, string $end): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionLabelExpr = $this->afiliacionLabelExpr('p');

        $sql = <<<SQL
            SELECT
                x.value_key,
                MAX(x.value_label) AS value_label
            FROM (
                SELECT
                    {$afiliacionKeyExpr} AS value_key,
                    {$afiliacionLabelExpr} AS value_label
                FROM protocolo_data pr
                LEFT JOIN patient_data p
                    ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
            ) x
            GROUP BY x.value_key
            ORDER BY value_label ASC
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $options = [
            ['value' => '', 'label' => 'Todas'],
            ['value' => 'iess', 'label' => 'IESS'],
        ];
        $seen = [
            '' => true,
            'iess' => true,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = trim((string)($row['value_key'] ?? ''));
            $label = trim((string)($row['value_label'] ?? ''));
            if ($key === '' || $label === '' || isset($seen[$key])) {
                continue;
            }

            $options[] = [
                'value' => $key,
                'label' => $label,
            ];
            $seen[$key] = true;
        }

        return $options;
    }

    public function getAfiliacionCategoriaOptions(string $start, string $end): array
    {
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');

        $sql = <<<SQL
            SELECT
                {$categoriaContext['expr']} AS categoria,
                COUNT(*) AS total
            FROM protocolo_data pr
            LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            {$categoriaContext['join']}
            WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
            GROUP BY {$categoriaContext['expr']}
            ORDER BY total DESC
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $options = [
            ['value' => '', 'label' => 'Todas las categorías'],
            ['value' => 'publico', 'label' => 'Pública'],
            ['value' => 'privado', 'label' => 'Privada'],
        ];
        $seen = [
            '' => true,
            'publico' => true,
            'privado' => true,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = trim((string)($row['categoria'] ?? ''));
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

    public function getSedeOptions(string $start, string $end): array
    {
        $sedeExpr = $this->sedeExpr('pp');
        $sql = <<<SQL
            SELECT DISTINCT {$sedeExpr} AS sede
            FROM protocolo_data pr
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
            ORDER BY sede ASC
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $options = [
            ['value' => '', 'label' => 'Todas las sedes'],
            ['value' => 'MATRIZ', 'label' => 'MATRIZ'],
            ['value' => 'CEIBOS', 'label' => 'CEIBOS'],
        ];
        $seen = [
            '' => true,
            'MATRIZ' => true,
            'CEIBOS' => true,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = trim((string)($row['sede'] ?? ''));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = ['value' => $value, 'label' => $value];
            $seen[$value] = true;
        }

        return $options;
    }

    public function getTotalCirugias(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): int
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getCirugiasSinFacturar(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): int
    {
        $trazabilidad = $this->getCirugiasFacturacionTrazabilidad(
            $start,
            $end,
            $afiliacionFilter,
            $afiliacionCategoriaFilter,
            $sedeFilter
        );

        return (int) ($trazabilidad['pendiente_facturar'] ?? 0);
    }

    /**
     * Trazabilidad operativa de cirugías, alineada al enfoque de Informe Particulares.
     *
     * - atendidos: protocolos quirúrgicos en el rango (base = protocolo_data)
     * - facturados: protocolos con evidencia en billing_facturacion_real o billing_procedimientos
     * - pendiente_facturar: protocolos operados aún sin evidencia de billing
     * - pendiente_pago: facturados con estado de cartera/pendiente/crédito
     * - cancelados: solicitudes quirúrgicas canceladas/suspendidas en programación
     */
    public function getCirugiasFacturacionTrazabilidad(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array {
        $rows = $this->fetchCirugiasFacturacionRows(
            $start,
            $end,
            $afiliacionFilter,
            $afiliacionCategoriaFilter,
            $sedeFilter
        );

        $atendidos = count($rows);
        $facturados = 0;
        $pendienteFacturar = 0;
        $pendientePago = 0;
        $facturacionCancelada = 0;
        $produccionFacturada = 0.0;
        $produccionFacturadaPublico = 0.0;
        $produccionFacturadaPrivado = 0.0;
        $facturadosPublico = 0;
        $facturadosPrivado = 0;
        $pendientesFacturarPublico = 0;
        $pendientesFacturarPrivado = 0;
        $procedimientosFacturados = 0;

        foreach ($rows as $row) {
            $estadoFacturacion = (string) ($row['estado_facturacion_operativa'] ?? '');
            $afiliacionCategoria = trim((string) ($row['afiliacion_categoria'] ?? ''));
            $esPublico = $afiliacionCategoria === 'publico';
            $esPrivado = $afiliacionCategoria === 'privado';
            $hasBillingEvidence = (int) ($row['facturado'] ?? 0) === 1;
            $facturadoValido = $hasBillingEvidence && $estadoFacturacion !== 'CANCELADA';

            if ($estadoFacturacion === 'PENDIENTE_PAGO') {
                $pendientePago++;
            }

            if ($estadoFacturacion === 'CANCELADA') {
                $facturacionCancelada++;
                continue;
            }

            if ($facturadoValido) {
                $facturados++;
                $produccionRow = max(0.0, (float) ($row['total_produccion'] ?? 0));
                $procedimientosRow = max(0, (int) ($row['procedimientos_facturados'] ?? 0));

                $produccionFacturada += $produccionRow;
                $procedimientosFacturados += $procedimientosRow;

                if ($esPublico) {
                    $facturadosPublico++;
                    $produccionFacturadaPublico += $produccionRow;
                } elseif ($esPrivado) {
                    $facturadosPrivado++;
                    $produccionFacturadaPrivado += $produccionRow;
                }

                continue;
            }

            $pendienteFacturar++;
            if ($esPublico) {
                $pendientesFacturarPublico++;
            } elseif ($esPrivado) {
                $pendientesFacturarPrivado++;
            }
        }

        $programacion = $this->getProgramacionKpis($start, $end, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);
        $cancelados = (int) ($programacion['suspendidas'] ?? 0);

        return [
            'atendidos' => $atendidos,
            'facturados' => $facturados,
            'pendiente_facturar' => $pendienteFacturar,
            'pendiente_pago' => $pendientePago,
            'cancelados' => max(0, $cancelados),
            'facturacion_cancelada' => $facturacionCancelada,
            'produccion_facturada' => round($produccionFacturada, 2),
            'produccion_facturada_publico' => round($produccionFacturadaPublico, 2),
            'produccion_facturada_privado' => round($produccionFacturadaPrivado, 2),
            'facturados_publico' => $facturadosPublico,
            'facturados_privado' => $facturadosPrivado,
            'pendientes_facturar_publico' => $pendientesFacturarPublico,
            'pendientes_facturar_privado' => $pendientesFacturarPrivado,
            'procedimientos_facturados' => $procedimientosFacturados,
            'ticket_promedio_facturado' => $facturados > 0 ? round($produccionFacturada / $facturados, 2) : 0.0,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getCirugiasFacturacionDetalle(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array {
        return $this->fetchCirugiasFacturacionDetalleRows(
            $start,
            $end,
            $afiliacionFilter,
            $afiliacionCategoriaFilter,
            $sedeFilter
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchCirugiasFacturacionRows(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $facturacionSql = $this->buildCirugiasFacturacionSql();

        $sql = "SELECT DISTINCT
                    pr.form_id,
                    {$categoriaContext['expr']} AS afiliacion_categoria,
                    {$facturacionSql['select']}
                FROM protocolo_data pr
                LEFT JOIN patient_data p
                    ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                LEFT JOIN procedimiento_proyectado pp
                    ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                {$categoriaContext['join']}
                {$facturacionSql['join']}
                WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
                  AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
                  AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
                  AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        foreach ($rows as &$row) {
            $row = $this->mergeCirugiaBillingEvidence($row);
            $row['estado_facturacion_operativa'] = $this->resolveCirugiaDashboardBillingState(
                (int) ($row['facturado'] ?? 0) === 1,
                (string) ($row['estado_facturacion_raw'] ?? '')
            );
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchCirugiasFacturacionDetalleRows(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $facturacionSql = $this->buildCirugiasFacturacionSql('base');

        $baseSql = "SELECT
                        pr.form_id,
                        pr.hc_number,
                        MAX(pr.fecha_inicio) AS fecha_inicio,
                        MAX(COALESCE(p.fname, '')) AS fname,
                        MAX(COALESCE(p.mname, '')) AS mname,
                        MAX(COALESCE(p.lname, '')) AS lname,
                        MAX(COALESCE(p.lname2, '')) AS lname2,
                        MAX(COALESCE(p.afiliacion, '')) AS afiliacion,
                        MAX({$categoriaContext['expr']}) AS afiliacion_categoria,
                        MAX({$sedeExpr}) AS sede,
                        GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(pp.procedimiento_proyectado, '')), '') SEPARATOR ' | ') AS procedimiento_proyectado
                    FROM protocolo_data pr
                    LEFT JOIN patient_data p
                        ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                         = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    LEFT JOIN procedimiento_proyectado pp
                        ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                    {$categoriaContext['join']}
                    WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
                      AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
                      AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
                      AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
                    GROUP BY pr.form_id, pr.hc_number";

        $sql = "SELECT
                    base.form_id,
                    base.hc_number,
                    base.fecha_inicio,
                    base.afiliacion,
                    base.afiliacion_categoria,
                    base.sede,
                    TRIM(CONCAT_WS(' ',
                        NULLIF(TRIM(base.fname), ''),
                        NULLIF(TRIM(base.mname), ''),
                        NULLIF(TRIM(base.lname), ''),
                        NULLIF(TRIM(base.lname2), '')
                    )) AS paciente,
                    COALESCE(base.procedimiento_proyectado, '') AS procedimiento_proyectado,
                    {$facturacionSql['select']}
                FROM ({$baseSql}) base
                {$facturacionSql['join']}
                ORDER BY base.fecha_inicio DESC, base.form_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        foreach ($rows as &$row) {
            $row = $this->mergeCirugiaBillingEvidence($row);
            $estadoFacturacion = $this->resolveCirugiaDashboardBillingState(
                (int) ($row['facturado'] ?? 0) === 1,
                (string) ($row['estado_facturacion_raw'] ?? '')
            );
            $row['estado_facturacion_operativa'] = $estadoFacturacion;
            $row['pendiente_facturar'] = $estadoFacturacion === 'PENDIENTE_FACTURAR';
            $row['pendiente_pago'] = $estadoFacturacion === 'PENDIENTE_PAGO';
            $row['facturacion_cancelada'] = $estadoFacturacion === 'CANCELADA';
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{select:string,join:string}
     */
    private function buildCirugiasFacturacionSql(string $formAlias = 'pr'): array
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
                    COALESCE(SUM(COALESCE(bfr.monto_honorario, 0)), 0) AS total_produccion,
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
            'join' => "LEFT JOIN ({$realSubquery}) bfr ON bfr.form_id = {$formAlias}.form_id
                LEFT JOIN ({$publicSubquery}) bpub ON bpub.form_id = {$formAlias}.form_id",
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mergeCirugiaBillingEvidence(array $row): array
    {
        $afiliacionCategoria = trim((string) ($row['afiliacion_categoria'] ?? ''));
        $realEvidence = $this->hasCirugiaBillingSourceEvidence($row, 'real');
        $publicEvidence = $this->hasCirugiaBillingSourceEvidence($row, 'public');
        $source = null;

        if ($afiliacionCategoria === 'publico' && $publicEvidence) {
            $source = 'public';
        } elseif ($realEvidence) {
            $source = 'real';
        } elseif ($publicEvidence) {
            $source = 'public';
        }

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
    private function hasCirugiaBillingSourceEvidence(array $row, string $prefix): bool
    {
        $billingId = trim((string) ($row[$prefix . '_billing_id'] ?? ''));
        $fechaFacturacion = trim((string) ($row[$prefix . '_fecha_facturacion'] ?? ''));
        $procedimientosFacturados = (int) ($row[$prefix . '_procedimientos_facturados'] ?? 0);
        $totalProduccion = (float) ($row[$prefix . '_total_produccion'] ?? 0);

        if ($prefix === 'real') {
            $numeroFactura = trim((string) ($row['real_numero_factura'] ?? ''));
            $facturaId = trim((string) ($row['real_factura_id'] ?? ''));

            return $billingId !== ''
                || $fechaFacturacion !== ''
                || $numeroFactura !== ''
                || $facturaId !== ''
                || $procedimientosFacturados > 0
                || abs($totalProduccion) > 0.00001;
        }

        return $billingId !== ''
            || $fechaFacturacion !== ''
            || $procedimientosFacturados > 0
            || abs($totalProduccion) > 0.00001;
    }

    private function resolveCirugiaDashboardBillingState(bool $facturado, string $estadoFacturacionRaw = ''): string
    {
        $estadoNorm = $this->normalizeTextValue($estadoFacturacionRaw);

        if ($estadoNorm !== '') {
            if (str_contains($estadoNorm, 'cancel') || str_contains($estadoNorm, 'anul')) {
                return 'CANCELADA';
            }

            if (
                str_contains($estadoNorm, 'pend')
                || str_contains($estadoNorm, 'credito')
                || str_contains($estadoNorm, 'cartera')
            ) {
                return 'PENDIENTE_PAGO';
            }
        }

        return $facturado ? 'FACTURADA' : 'PENDIENTE_FACTURAR';
    }

    public function getDuracionPromedioMinutos(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): float
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin))
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND hora_inicio IS NOT NULL
               AND hora_fin IS NOT NULL
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * TAT de revisión de protocolos (cirugía -> revisión).
     *
     * Se toma como inicio de cirugía, en orden de prioridad:
     * 1) fecha_fin + hora_fin
     * 2) fecha_inicio + hora_fin
     * 3) fecha_inicio + hora_inicio
     * 4) fecha_inicio 00:00:00
     *
     * Y como cierre de revisión: fecha_firma.
     */
    public function getTatRevisionProtocolos(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        if (!$this->columnExists('protocolo_data', 'fecha_firma')) {
            return [
                'muestra' => 0,
                'tat_promedio_horas' => null,
                'tat_mediana_horas' => null,
                'tat_p90_horas' => null,
            ];
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');

        $sql = "SELECT
                    pr.fecha_inicio,
                    pr.fecha_fin,
                    pr.hora_inicio,
                    pr.hora_fin,
                    pr.fecha_firma
                FROM protocolo_data pr
                LEFT JOIN patient_data p
                    ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                LEFT JOIN procedimiento_proyectado pp
                    ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                {$categoriaContext['join']}
                WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
                  AND pr.status = 1
                  AND pr.fecha_firma IS NOT NULL
                  AND TRIM(CAST(pr.fecha_firma AS CHAR)) <> ''
                  AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
                  AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
                  AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $tatHoras = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inicioTs = $this->resolveInicioCirugiaTimestamp($row);
            $firmaTs = $this->parseTimestamp((string)($row['fecha_firma'] ?? ''));
            if ($inicioTs === null || $firmaTs === null || $firmaTs < $inicioTs) {
                continue;
            }

            $tatHoras[] = ($firmaTs - $inicioTs) / 3600;
        }

        if ($tatHoras === []) {
            return [
                'muestra' => 0,
                'tat_promedio_horas' => null,
                'tat_mediana_horas' => null,
                'tat_p90_horas' => null,
            ];
        }

        $promedio = array_sum($tatHoras) / count($tatHoras);
        $mediana = $this->calculatePercentile($tatHoras, 0.50);
        $p90 = $this->calculatePercentile($tatHoras, 0.90);

        return [
            'muestra' => count($tatHoras),
            'tat_promedio_horas' => round($promedio, 2),
            'tat_mediana_horas' => $mediana !== null ? round($mediana, 2) : null,
            'tat_p90_horas' => $p90 !== null ? round($p90, 2) : null,
        ];
    }

    public function getEstadoProtocolos(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT pr.status, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo,
                    pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia,
                    pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos,
                    pp.procedimiento_proyectado, pr.fecha_inicio, pr.hora_inicio, pr.hora_fin
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $counts = [
            'revisado' => 0,
            'no revisado' => 0,
            'incompleto' => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $estado = (new Cirugia($row))->getEstado();
            if (!isset($counts[$estado])) {
                $counts[$estado] = 0;
            }
            $counts[$estado]++;
        }

        return $counts;
    }

    public function getCirugiasPorMes(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(fecha_inicio, '%Y-%m') AS mes, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
             GROUP BY DATE_FORMAT(fecha_inicio, '%Y-%m')
             ORDER BY mes ASC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['mes'];
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getTopProcedimientos(
        string $start,
        string $end,
        int $limit = 10,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(membrete), '') AS procedimiento, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
             GROUP BY NULLIF(TRIM(membrete), '')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':afiliacion_filter', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_filter_match', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter_match', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':sede_filter', $sedeFilterValue);
        $stmt->bindValue(':sede_filter_match', $sedeFilterValue);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['procedimiento'] ?: 'Sin membrete';
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getTopCirujanos(
        string $start,
        string $end,
        int $limit = 10,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(cirujano_1), '') AS cirujano, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
             GROUP BY NULLIF(TRIM(cirujano_1), '')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':afiliacion_filter', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_filter_match', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter_match', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':sede_filter', $sedeFilterValue);
        $stmt->bindValue(':sede_filter_match', $sedeFilterValue);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['cirujano'] ?: 'Sin asignar';
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * Top doctores solicitantes cuyas solicitudes tienen cirugía confirmada.
     *
     * Fuente de doctor:
     * 1) procedimiento_proyectado.doctor (preferido)
     * 2) solicitud_procedimiento.doctor (fallback)
     */
    public function getTopDoctoresSolicitudesRealizadas(
        string $start,
        string $end,
        int $limit = 10,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        if (!$this->tableExists('solicitud_crm_meta')) {
            return ['labels' => [], 'totals' => []];
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeFilterCondition = $this->solicitudSedeFilterCondition('sp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $hasProcedimientoDoctor = $this->tableExists('procedimiento_proyectado')
            && $this->columnExists('procedimiento_proyectado', 'form_id')
            && $this->columnExists('procedimiento_proyectado', 'doctor');
        $doctorExpr = $hasProcedimientoDoctor
            ? "COALESCE(NULLIF(TRIM(pp.doctor), ''), NULLIF(TRIM(sp.doctor), ''), 'Sin asignar')"
            : "COALESCE(NULLIF(TRIM(sp.doctor), ''), 'Sin asignar')";
        $ppJoin = $hasProcedimientoDoctor
            ? "LEFT JOIN (
                    SELECT
                        form_id,
                        MAX(NULLIF(TRIM(doctor), '')) AS doctor
                    FROM procedimiento_proyectado
                    GROUP BY form_id
                ) pp ON pp.form_id = sp.form_id"
            : '';

        $sql = <<<'SQL'
            SELECT
                base.doctor,
                COUNT(*) AS total
            FROM (
                SELECT DISTINCT
                    sp.id,
                    %DOCTOR_EXPR% AS doctor
                FROM solicitud_procedimiento sp
                %PP_JOIN%
                LEFT JOIN consulta_data cd
                    ON CONVERT(cd.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     = CONVERT(sp.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   AND cd.form_id = sp.form_id
                LEFT JOIN patient_data p
                    ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     = CONVERT(sp.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                %AFILIACION_CATEGORIA_JOIN%
                WHERE COALESCE(cd.fecha, sp.fecha) BETWEEN :inicio AND :fin
                  AND sp.procedimiento IS NOT NULL
                  AND TRIM(sp.procedimiento) <> ''
                  AND TRIM(sp.procedimiento) <> 'SELECCIONE'
                  AND (:afiliacion_filter = '' OR %AFILIACION_KEY_EXPR% = :afiliacion_filter_match)
                  AND (:afiliacion_categoria_filter = '' OR %AFILIACION_CATEGORIA_EXPR% = :afiliacion_categoria_filter_match)
                  AND %SEDE_FILTER_CONDITION%
            ) base
            INNER JOIN (
                SELECT
                    solicitud_id
                FROM solicitud_crm_meta
                WHERE meta_key = 'cirugia_confirmada_form_id'
                  AND meta_value IS NOT NULL
                  AND TRIM(meta_value) <> ''
                GROUP BY solicitud_id
            ) meta
                ON meta.solicitud_id = base.id
            GROUP BY base.doctor
            ORDER BY total DESC
            LIMIT :limit
        SQL;
        $sql = str_replace('%AFILIACION_KEY_EXPR%', $afiliacionKeyExpr, $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_JOIN%', $categoriaContext['join'], $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_EXPR%', $categoriaContext['expr'], $sql);
        $sql = str_replace('%DOCTOR_EXPR%', $doctorExpr, $sql);
        $sql = str_replace('%PP_JOIN%', $ppJoin, $sql);
        $sql = str_replace('%SEDE_FILTER_CONDITION%', $sedeFilterCondition, $sql);

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':afiliacion_filter', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_filter_match', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter_match', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':sede_filter', $sedeFilterValue);
        $stmt->bindValue(':sede_filter_match', $sedeFilterValue);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['doctor'] ?: 'Sin asignar';
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getCirugiasPorConvenio(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionLabelExpr = $this->afiliacionLabelExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT {$afiliacionLabelExpr} AS afiliacion, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
               AND (:sede_filter = '' OR {$sedeExpr} = :sede_filter_match)
             GROUP BY {$afiliacionLabelExpr}
             ORDER BY total DESC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['afiliacion'] ?: 'Sin convenio';
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * KPIs de programación quirúrgica basados en solicitudes con fecha programada.
     *
     * Fórmulas:
     * - Cumplimiento = (realizadas / programadas) * 100
     * - Tasa suspendidas = (suspendidas / programadas) * 100
     * - Tasa reprogramación = (reprogramadas / programadas) * 100
     */
    public function getProgramacionKpis(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        if (!$this->tableExists('solicitud_crm_meta')) {
            return $this->emptyProgramacionKpis();
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeFilterCondition = $this->solicitudSedeFilterCondition('sp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $sql = <<<'SQL'
            SELECT
                base.id,
                base.estado,
                base.ojo,
                base.fecha_solicitud,
                meta.protocolo_form_id,
                COALESCE(
                    NULLIF(TRIM(CAST(meta.protocolo_fecha_inicio AS CHAR)), ''),
                    NULLIF(TRIM(CAST(pd.fecha_inicio AS CHAR)), '')
                ) AS protocolo_fecha_inicio,
                COALESCE(
                    NULLIF(TRIM(CAST(meta.protocolo_lateralidad AS CHAR)), ''),
                    NULLIF(TRIM(CAST(pd.lateralidad AS CHAR)), '')
                ) AS protocolo_lateralidad
            FROM (
                SELECT DISTINCT
                    sp.id,
                    sp.ojo,
                    sp.estado,
                    COALESCE(cd.fecha, sp.fecha) AS fecha_solicitud
                FROM solicitud_procedimiento sp
                LEFT JOIN consulta_data cd
                    ON CONVERT(cd.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(sp.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    AND cd.form_id = sp.form_id
                LEFT JOIN patient_data p
                    ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     = CONVERT(sp.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                %AFILIACION_CATEGORIA_JOIN%
                WHERE COALESCE(cd.fecha, sp.fecha) BETWEEN :inicio_solicitud AND :fin_solicitud
                  AND sp.procedimiento IS NOT NULL
                  AND TRIM(sp.procedimiento) <> ''
                  AND TRIM(sp.procedimiento) <> 'SELECCIONE'
                  AND (:afiliacion_filter = '' OR %AFILIACION_KEY_EXPR% = :afiliacion_filter_match)
                  AND (:afiliacion_categoria_filter = '' OR %AFILIACION_CATEGORIA_EXPR% = :afiliacion_categoria_filter_match)
                  AND %SEDE_FILTER_CONDITION%
            ) base
            LEFT JOIN (
                SELECT
                    solicitud_id,
                    MAX(CASE WHEN meta_key = 'cirugia_confirmada_form_id' THEN meta_value END) AS protocolo_form_id,
                    MAX(CASE WHEN meta_key = 'cirugia_confirmada_fecha_inicio' THEN meta_value END) AS protocolo_fecha_inicio,
                    MAX(CASE WHEN meta_key = 'cirugia_confirmada_lateralidad' THEN meta_value END) AS protocolo_lateralidad
                FROM solicitud_crm_meta
                WHERE meta_key IN (
                    'cirugia_confirmada_form_id',
                    'cirugia_confirmada_fecha_inicio',
                    'cirugia_confirmada_lateralidad'
                )
                GROUP BY solicitud_id
            ) meta
                ON meta.solicitud_id = base.id
            LEFT JOIN (
                SELECT
                    CAST(form_id AS CHAR) AS form_id,
                    MAX(fecha_inicio) AS fecha_inicio,
                    MAX(lateralidad) AS lateralidad
                FROM protocolo_data
                GROUP BY CAST(form_id AS CHAR)
            ) pd
                ON CONVERT(pd.form_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(meta.protocolo_form_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
        SQL;
        $sql = str_replace('%AFILIACION_KEY_EXPR%', $afiliacionKeyExpr, $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_JOIN%', $categoriaContext['join'], $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_EXPR%', $categoriaContext['expr'], $sql);
        $sql = str_replace('%SEDE_FILTER_CONDITION%', $sedeFilterCondition, $sql);

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio_solicitud' => $start,
            ':fin_solicitud' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return $this->emptyProgramacionKpis();
        }

        $suspendidosEstados = [
            'suspendido', 'suspendida',
            'cancelado', 'cancelada',
            'no procede', 'no_procede', 'no-procede',
        ];
        $reprogramadosEstados = ['reprogramado', 'reprogramada'];

        $programadas = 0;
        $realizadas = 0;
        $suspendidas = 0;
        $reprogramadas = 0;
        $backlog = 0;
        $backlogEdadAcumulada = 0.0;
        $backlogEdadMuestra = 0;
        $leadTimeAcumulado = 0.0;
        $leadTimeMuestra = 0;
        $completadasTotal = 0;
        $completadasConEvidencia = 0;
        $lateralidadEvaluable = 0;
        $lateralidadConcordante = 0;

        $hoy = new DateTimeImmutable('today');

        foreach ($rows as $row) {
            $programadas++;
            $estado = strtolower(trim((string) ($row['estado'] ?? '')));
            $isSuspendida = in_array($estado, $suspendidosEstados, true);
            $isReprogramada = in_array($estado, $reprogramadosEstados, true);
            $protocoloFormId = trim((string) ($row['protocolo_form_id'] ?? ''));
            $isRealizada = $protocoloFormId !== '';

            if ($isSuspendida) {
                $suspendidas++;
            }
            if ($isReprogramada) {
                $reprogramadas++;
            }
            if ($isRealizada) {
                $realizadas++;
            }

            if ($estado === 'completado') {
                $completadasTotal++;
                if ($isRealizada) {
                    $completadasConEvidencia++;
                }
            }

            $fechaSolicitudTs = strtotime((string) ($row['fecha_solicitud'] ?? '')) ?: 0;
            $fechaProtocoloTs = strtotime((string) ($row['protocolo_fecha_inicio'] ?? '')) ?: 0;

            if (!$isRealizada && !$isSuspendida) {
                $backlog++;
                if ($fechaSolicitudTs > 0) {
                    $fechaSolicitud = (new DateTimeImmutable())->setTimestamp($fechaSolicitudTs);
                    $dias = (float) $fechaSolicitud->diff($hoy)->days;
                    if ($hoy < $fechaSolicitud) {
                        $dias = 0.0;
                    }
                    $backlogEdadAcumulada += $dias;
                    $backlogEdadMuestra++;
                }
            }

            if ($isRealizada && $fechaSolicitudTs > 0 && $fechaProtocoloTs > 0 && $fechaProtocoloTs >= $fechaSolicitudTs) {
                $leadTimeAcumulado += ($fechaProtocoloTs - $fechaSolicitudTs) / 86400;
                $leadTimeMuestra++;
            }

            if ($isRealizada) {
                $solicitudLados = $this->normalizeLateralidad((string) ($row['ojo'] ?? ''));
                $protocoloLados = $this->normalizeLateralidad((string) ($row['protocolo_lateralidad'] ?? ''));

                if ($solicitudLados !== [] && $protocoloLados !== []) {
                    $lateralidadEvaluable++;
                    if ($this->lateralidadCompatible($solicitudLados, $protocoloLados)) {
                        $lateralidadConcordante++;
                    }
                }
            }
        }

        $leadTimePromedioDias = $leadTimeMuestra > 0 ? round($leadTimeAcumulado / $leadTimeMuestra, 2) : 0.0;
        $backlogEdadPromedioDias = $backlogEdadMuestra > 0 ? round($backlogEdadAcumulada / $backlogEdadMuestra, 2) : 0.0;

        return [
            'programadas' => $programadas,
            'realizadas' => $realizadas,
            'suspendidas' => $suspendidas,
            'reprogramadas' => $reprogramadas,
            'cumplimiento' => $this->percentage((float) $realizadas, (float) $programadas),
            'tasa_suspendidas' => $this->percentage((float) $suspendidas, (float) $programadas),
            'tasa_reprogramacion' => $this->percentage((float) $reprogramadas, (float) $programadas),
            'tiempo_promedio_solicitud_cirugia_dias' => $leadTimePromedioDias,
            'backlog_sin_resolucion' => $backlog,
            'backlog_edad_promedio_dias' => $backlogEdadPromedioDias,
            'completadas_total' => $completadasTotal,
            'completadas_con_evidencia' => $completadasConEvidencia,
            'completadas_con_evidencia_pct' => $this->percentage((float) $completadasConEvidencia, (float) $completadasTotal),
            'lateralidad_evaluable' => $lateralidadEvaluable,
            'lateralidad_concordante' => $lateralidadConcordante,
            'lateralidad_concordancia_pct' => $this->percentage((float) $lateralidadConcordante, (float) $lateralidadEvaluable),
        ];
    }

    /**
     * Obtiene KPI de reingresos por mismo diagnóstico CIE-10 desde el módulo KPI.
     */
    public function getReingresoMismoDiagnostico(string $start, string $end): array
    {
        $kpiServiceClass = 'Modules\\KPI\\Services\\KpiQueryService';
        if (!class_exists($kpiServiceClass)) {
            return [
                'total' => 0,
                'tasa' => 0.0,
                'episodios' => 0,
            ];
        }

        try {
            $queryService = new $kpiServiceClass($this->db);
            $startDate = (new DateTimeImmutable($start))->setTime(0, 0, 0);
            $endDate = (new DateTimeImmutable($end))->setTime(0, 0, 0);

            $total = $queryService->getAggregatedValue(
                'reingresos.mismo_diagnostico.total',
                $startDate,
                $endDate,
                [],
                true
            ) ?? ['value' => 0];

            $rate = $queryService->getAggregatedValue(
                'reingresos.mismo_diagnostico.tasa',
                $startDate,
                $endDate,
                [],
                true
            ) ?? ['value' => 0, 'denominator' => 0];

            return [
                'total' => (int) round((float) ($total['value'] ?? 0)),
                'tasa' => round((float) ($rate['value'] ?? 0), 2),
                'episodios' => (int) round((float) ($rate['denominator'] ?? 0)),
            ];
        } catch (\Throwable) {
            return [
                'total' => 0,
                'tasa' => 0.0,
                'episodios' => 0,
            ];
        }
    }

    /**
     * Cirugías realizadas en el periodo que no tienen ninguna solicitud previa del mismo HC.
     *
     * "Previa" se evalúa por fecha (DATE) de solicitud <= fecha de cirugía.
     */
    public function getCirugiasSinSolicitudPrevia(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = '',
        string $sedeFilter = ''
    ): array
    {
        if (!$this->tableExists('solicitud_procedimiento')) {
            return ['total' => 0, 'porcentaje' => 0.0];
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $sedeFilterValue = $this->normalizeSedeFilter($sedeFilter);
        $sedeExpr = $this->sedeExpr('pp');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $sql = <<<'SQL'
            SELECT COUNT(*) AS total
            FROM protocolo_data pr
            LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            %AFILIACION_CATEGORIA_JOIN%
            WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
              AND (:afiliacion_filter = '' OR %AFILIACION_KEY_EXPR% = :afiliacion_filter_match)
              AND (:afiliacion_categoria_filter = '' OR %AFILIACION_CATEGORIA_EXPR% = :afiliacion_categoria_filter_match)
              AND (:sede_filter = '' OR %SEDE_EXPR% = :sede_filter_match)
              AND NOT EXISTS (
                  SELECT 1
                  FROM solicitud_procedimiento sp
                  LEFT JOIN consulta_data cd
                      ON CONVERT(cd.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                       = CONVERT(sp.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                     AND cd.form_id = sp.form_id
                  WHERE CONVERT(UPPER(TRIM(LEADING '0' FROM REPLACE(TRIM(sp.hc_number), ' ', ''))) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                        = CONVERT(UPPER(TRIM(LEADING '0' FROM REPLACE(TRIM(pr.hc_number), ' ', ''))) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    AND DATE(COALESCE(cd.fecha, sp.fecha)) <= DATE(pr.fecha_inicio)
                    AND sp.procedimiento IS NOT NULL
                    AND TRIM(sp.procedimiento) <> ''
                    AND TRIM(sp.procedimiento) <> 'SELECCIONE'
              )
        SQL;
        $sql = str_replace('%AFILIACION_KEY_EXPR%', $afiliacionKeyExpr, $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_JOIN%', $categoriaContext['join'], $sql);
        $sql = str_replace('%AFILIACION_CATEGORIA_EXPR%', $categoriaContext['expr'], $sql);
        $sql = str_replace('%SEDE_EXPR%', $sedeExpr, $sql);

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
            ':sede_filter' => $sedeFilterValue,
            ':sede_filter_match' => $sedeFilterValue,
        ]);

        $total = (int) $stmt->fetchColumn();
        $totalCirugias = $this->getTotalCirugias($start, $end, $afiliacionFilter, $afiliacionCategoriaFilter, $sedeFilter);

        return [
            'total' => $total,
            'porcentaje' => $this->percentage((float) $total, (float) $totalCirugias),
        ];
    }

    private function percentage(float $numerator, float $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    /**
     * @param array<int,float|int> $values
     */
    private function calculatePercentile(array $values, float $percent): ?float
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

    private function resolveInicioCirugiaTimestamp(array $row): ?int
    {
        $fechaInicio = $this->normalizeDate((string)($row['fecha_inicio'] ?? ''));
        $fechaFin = $this->normalizeDate((string)($row['fecha_fin'] ?? ''));
        $horaInicio = $this->normalizeTime((string)($row['hora_inicio'] ?? ''));
        $horaFin = $this->normalizeTime((string)($row['hora_fin'] ?? ''));

        $candidates = [];

        if ($fechaFin !== null && $horaFin !== null) {
            $candidates[] = $fechaFin . ' ' . $horaFin;
        }
        if ($fechaInicio !== null && $horaFin !== null) {
            $candidates[] = $fechaInicio . ' ' . $horaFin;
        }
        if ($fechaInicio !== null && $horaInicio !== null) {
            $candidates[] = $fechaInicio . ' ' . $horaInicio;
        }
        if ($fechaInicio !== null) {
            $candidates[] = $fechaInicio . ' 00:00:00';
        }

        foreach ($candidates as $candidate) {
            $timestamp = $this->parseTimestamp($candidate);
            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        return null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return strlen($value) >= 10 ? substr($value, 0, 10) : null;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if (
            $value === ''
            || $value === '00:00'
            || $value === '00:00:00'
            || str_starts_with($value, '0000-00-00')
        ) {
            return null;
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return null;
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function parseTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false || $timestamp <= 0) {
            return null;
        }

        return $timestamp;
    }

    private function normalizeTextValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return strtolower(strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]));
    }

    private function normalizeAfiliacionFilter(string $afiliacionFilter): string
    {
        $value = strtolower(trim($afiliacionFilter));
        if ($value === 'sin convenio') {
            return 'sin_convenio';
        }

        return $value;
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

    private function normalizeSedeFilter(string $sedeFilter): string
    {
        $value = strtolower(trim($sedeFilter));
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

    private function afiliacionGroupKeyExpr(string $alias): string
    {
        $col = "LOWER(TRIM(COALESCE({$alias}.afiliacion, '')))";
        return "CASE
            WHEN {$col} IN (" . $this->iessAffiliationsSqlList() . ") THEN 'iess'
            WHEN {$col} = '' THEN 'sin_convenio'
            ELSE {$col}
        END";
    }

    private function afiliacionLabelExpr(string $alias): string
    {
        $col = "LOWER(TRIM(COALESCE({$alias}.afiliacion, '')))";
        return "CASE
            WHEN {$col} IN (" . $this->iessAffiliationsSqlList() . ") THEN 'IESS'
            WHEN {$col} = '' THEN 'Sin convenio'
            ELSE TRIM({$alias}.afiliacion)
        END";
    }

    private function iessAffiliationsSqlList(): string
    {
        return "'" . implode("','", self::IESS_AFFILIATIONS) . "'";
    }

    private function sedeExpr(string $alias): string
    {
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF({$alias}.sede_departamento, ''), NULLIF({$alias}.id_sede, ''), '')))";

        return "CASE
            WHEN {$rawExpr} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$rawExpr} LIKE '%matriz%' THEN 'MATRIZ'
            ELSE ''
        END";
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

    private function solicitudSedeFilterCondition(string $solicitudAlias): string
    {
        if (
            !$this->tableExists('procedimiento_proyectado')
            || !$this->columnExists('procedimiento_proyectado', 'form_id')
        ) {
            return "(:sede_filter = '')";
        }

        $sedeExpr = $this->sedeExpr('pp_sede');
        $hcCondition = $this->columnExists('procedimiento_proyectado', 'hc_number')
            ? " AND CONVERT(pp_sede.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT({$solicitudAlias}.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci"
            : '';

        return "(:sede_filter = '' OR EXISTS (
            SELECT 1
            FROM procedimiento_proyectado pp_sede
            WHERE CONVERT(pp_sede.form_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  = CONVERT({$solicitudAlias}.form_id USING utf8mb4) COLLATE utf8mb4_unicode_ci{$hcCondition}
              AND {$sedeExpr} = :sede_filter_match
        ))";
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
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table"
        );
        $stmt->execute([':table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column"
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * @return array<int,string>
     */
    private function normalizeLateralidad(string $raw): array
    {
        $value = strtoupper(trim($raw));
        if ($value === '') {
            return [];
        }

        $normalized = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', '.', ',', ';', '(', ')'],
            ['A', 'E', 'I', 'O', 'U', ' ', ' ', ' ', ' ', ' '],
            $value
        );

        $hasBoth = str_contains($normalized, 'AMBOS')
            || str_contains($normalized, 'BILATERAL')
            || preg_match('/\b(AO|OU)\b/', $normalized) === 1;

        $tokens = preg_split('/[^A-Z0-9]+/', $normalized) ?: [];
        $result = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (in_array($token, ['OD', 'DER', 'DERECHO', 'DERECHA'], true)) {
                $result['OD'] = true;
            } elseif (in_array($token, ['OI', 'IZQ', 'IZQUIERDO', 'IZQUIERDA'], true)) {
                $result['OI'] = true;
            }
        }

        if ($hasBoth) {
            return ['OD', 'OI'];
        }

        return array_keys($result);
    }

    /**
     * @param array<int,string> $solicitudLados
     * @param array<int,string> $protocoloLados
     */
    private function lateralidadCompatible(array $solicitudLados, array $protocoloLados): bool
    {
        if ($solicitudLados === [] || $protocoloLados === []) {
            return false;
        }

        return array_intersect($solicitudLados, $protocoloLados) !== [];
    }

    private function emptyProgramacionKpis(): array
    {
        return [
            'programadas' => 0,
            'realizadas' => 0,
            'suspendidas' => 0,
            'reprogramadas' => 0,
            'cumplimiento' => 0.0,
            'tasa_suspendidas' => 0.0,
            'tasa_reprogramacion' => 0.0,
            'tiempo_promedio_solicitud_cirugia_dias' => 0.0,
            'backlog_sin_resolucion' => 0,
            'backlog_edad_promedio_dias' => 0.0,
            'completadas_total' => 0,
            'completadas_con_evidencia' => 0,
            'completadas_con_evidencia_pct' => 0.0,
            'lateralidad_evaluable' => 0,
            'lateralidad_concordante' => 0,
            'lateralidad_concordancia_pct' => 0.0,
        ];
    }
}
