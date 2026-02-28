<?php

namespace Modules\Cirugias\Services;

use DateTimeImmutable;
use Modules\KPI\Services\KpiQueryService;
use Modules\Cirugias\Models\Cirugia;
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

    public function getTotalCirugias(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = ''
    ): int
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getCirugiasSinFacturar(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = ''
    ): int
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND bm.id IS NULL
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getDuracionPromedioMinutos(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = ''
    ): float
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin))
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND hora_inicio IS NOT NULL
               AND hora_fin IS NOT NULL
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
        ]);

        return (float) $stmt->fetchColumn();
    }

    public function getEstadoProtocolos(
        string $start,
        string $end,
        string $afiliacionFilter = '',
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
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
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(fecha_inicio, '%Y-%m') AS mes, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(membrete), '') AS procedimiento, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(cirujano_1), '') AS cirujano, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        if (!$this->tableExists('solicitud_crm_meta')) {
            return ['labels' => [], 'totals' => []];
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
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

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':afiliacion_filter', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_filter_match', $afiliacionFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter', $afiliacionCategoriaFilterValue);
        $stmt->bindValue(':afiliacion_categoria_filter_match', $afiliacionCategoriaFilterValue);
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionLabelExpr = $this->afiliacionLabelExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $stmt = $this->db->prepare(
            "SELECT {$afiliacionLabelExpr} AS afiliacion, COUNT(*) AS total
             FROM protocolo_data pr
             LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
             {$categoriaContext['join']}
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND (:afiliacion_filter = '' OR {$afiliacionKeyExpr} = :afiliacion_filter_match)
               AND (:afiliacion_categoria_filter = '' OR {$categoriaContext['expr']} = :afiliacion_categoria_filter_match)
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        if (!$this->tableExists('solicitud_crm_meta')) {
            return $this->emptyProgramacionKpis();
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio_solicitud' => $start,
            ':fin_solicitud' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
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
        try {
            $queryService = new KpiQueryService($this->db);
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
        string $afiliacionCategoriaFilter = ''
    ): array
    {
        if (!$this->tableExists('solicitud_procedimiento')) {
            return ['total' => 0, 'porcentaje' => 0.0];
        }

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionFilterValue = $this->normalizeAfiliacionFilter($afiliacionFilter);
        $afiliacionCategoriaFilterValue = $this->normalizeAfiliacionCategoriaFilter($afiliacionCategoriaFilter);
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $sql = <<<'SQL'
            SELECT COUNT(*) AS total
            FROM protocolo_data pr
            LEFT JOIN patient_data p
                ON CONVERT(p.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 = CONVERT(pr.hc_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            %AFILIACION_CATEGORIA_JOIN%
            WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
              AND (:afiliacion_filter = '' OR %AFILIACION_KEY_EXPR% = :afiliacion_filter_match)
              AND (:afiliacion_categoria_filter = '' OR %AFILIACION_CATEGORIA_EXPR% = :afiliacion_categoria_filter_match)
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
            ':afiliacion_filter' => $afiliacionFilterValue,
            ':afiliacion_filter_match' => $afiliacionFilterValue,
            ':afiliacion_categoria_filter' => $afiliacionCategoriaFilterValue,
            ':afiliacion_categoria_filter_match' => $afiliacionCategoriaFilterValue,
        ]);

        $total = (int) $stmt->fetchColumn();
        $totalCirugias = $this->getTotalCirugias($start, $end, $afiliacionFilter, $afiliacionCategoriaFilter);

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
