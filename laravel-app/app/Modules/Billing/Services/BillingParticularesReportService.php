<?php

namespace App\Modules\Billing\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;

class BillingParticularesReportService
{
    private PDO $db;

    /** @var array<int, string> */
    private const EXCLUDED_AFFILIATIONS = [
        'isspol',
        'issfa',
        'iess',
        'msp',
        'contribuyente voluntario',
        'conyuge',
        'conyuge pensionista',
        'seguro campesino',
        'seguro campesino jubilado',
        'seguro general',
        'seguro general jubilado',
        'seguro general por montepío',
        'seguro general por montepio',
        'seguro general tiempo parcial',
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
     * @return array{from:string,to:string,mes:string}
     */
    public function resolveDateRange(?string $mes): array
    {
        $mes = trim((string) $mes);
        if ($mes !== '' && preg_match('/^\d{4}-\d{2}$/', $mes) === 1) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mes . '-01 00:00:00');
            if ($start instanceof DateTimeImmutable) {
                $end = $start->modify('last day of this month')->setTime(23, 59, 59);

                return [
                    'from' => $start->format('Y-m-d H:i:s'),
                    'to' => $end->format('Y-m-d H:i:s'),
                    'mes' => $mes,
                ];
            }
        }

        $now = new DateTimeImmutable('now');
        $start = $now->sub(new DateInterval('P5M'))->modify('first day of this month')->setTime(0, 0, 0);

        return [
            'from' => $start->format('Y-m-d H:i:s'),
            'to' => $now->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            'mes' => '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerAtencionesParticulares(string $fechaInicio, string $fechaFin): array
    {
        $excludedPlaceholders = implode(',', array_fill(0, count(self::EXCLUDED_AFFILIATIONS), '?'));

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
                    pp.procedimiento_proyectado,
                    pp.doctor
                FROM patient_data p
                INNER JOIN consulta_data cd ON cd.hc_number = p.hc_number
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = cd.form_id
                WHERE cd.fecha BETWEEN ? AND ?
                  AND LOWER(TRIM(COALESCE(p.afiliacion, ''))) NOT IN ($excludedPlaceholders)

                UNION ALL

                SELECT
                    p.hc_number,
                    CONCAT_WS(' ', p.fname, p.lname, p.lname2) AS nombre_completo,
                    'protocolo' AS tipo,
                    pd.form_id,
                    pd.fecha_inicio AS fecha,
                    p.afiliacion,
                    pp.procedimiento_proyectado,
                    pp.doctor
                FROM patient_data p
                INNER JOIN protocolo_data pd ON pd.hc_number = p.hc_number
                INNER JOIN procedimiento_proyectado pp ON pp.hc_number = p.hc_number AND pp.form_id = pd.form_id
                WHERE pd.fecha_inicio BETWEEN ? AND ?
                  AND LOWER(TRIM(COALESCE(p.afiliacion, ''))) NOT IN ($excludedPlaceholders)
            ) AS atenciones
            WHERE atenciones.fecha IS NOT NULL
              AND atenciones.fecha NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')
            ORDER BY atenciones.fecha DESC, atenciones.form_id DESC
        SQL;

        $params = array_merge(
            [$fechaInicio, $fechaFin],
            self::EXCLUDED_AFFILIATIONS,
            [$fechaInicio, $fechaFin],
            self::EXCLUDED_AFFILIATIONS
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function aplicarFiltros(array $rows, array $filters): array
    {
        $mes = trim((string) ($filters['mes'] ?? ''));
        $semana = (int) ($filters['semana'] ?? 0);
        $afiliacion = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
        $tipo = strtolower(trim((string) ($filters['tipo'] ?? '')));
        $procedimiento = strtolower(trim((string) ($filters['procedimiento'] ?? '')));

        if (!in_array($tipo, ['', 'consulta', 'protocolo'], true)) {
            $tipo = '';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = '';
        }
        if ($semana < 1 || $semana > 5) {
            $semana = 0;
        }

        $resultado = [];
        foreach ($rows as $row) {
            $timestamp = strtotime((string) ($row['fecha'] ?? ''));
            if ($timestamp === false) {
                continue;
            }

            $mesRow = date('Y-m', $timestamp);
            $dia = (int) date('j', $timestamp);
            $afiliacionRow = strtolower(trim((string) ($row['afiliacion'] ?? '')));
            $tipoRow = strtolower(trim((string) ($row['tipo'] ?? '')));
            $procedimientoRow = strtolower((string) ($row['procedimiento_proyectado'] ?? ''));

            if ($mes !== '' && $mesRow !== $mes) {
                continue;
            }
            if ($semana > 0 && !$this->matchesWeekBucket($dia, $semana)) {
                continue;
            }
            if ($afiliacion !== '' && $afiliacionRow !== $afiliacion) {
                continue;
            }
            if ($tipo !== '' && $tipoRow !== $tipo) {
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
     * @return array{total:int,top_afiliaciones:array<int, array{afiliacion:string,cantidad:int}>}
     */
    public function resumen(array $rows): array
    {
        $conteoAfiliacion = [];
        foreach ($rows as $row) {
            $afiliacion = strtoupper(trim((string) ($row['afiliacion'] ?? '')));
            if ($afiliacion === '') {
                $afiliacion = 'SIN AFILIACION';
            }
            if (!isset($conteoAfiliacion[$afiliacion])) {
                $conteoAfiliacion[$afiliacion] = 0;
            }
            $conteoAfiliacion[$afiliacion]++;
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

        return [
            'total' => count($rows),
            'top_afiliaciones' => $topAfiliaciones,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{meses:array<int, array{value:string,label:string}>,afiliaciones:array<int, string>}
     */
    public function catalogos(array $rows): array
    {
        $meses = [];
        $afiliaciones = [];

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
        }

        krsort($meses);
        ksort($afiliaciones);

        return [
            'meses' => array_values($meses),
            'afiliaciones' => array_values($afiliaciones),
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

    private function matchesWeekBucket(int $dayOfMonth, int $bucket): bool
    {
        return match ($bucket) {
            1 => $dayOfMonth >= 1 && $dayOfMonth <= 7,
            2 => $dayOfMonth >= 8 && $dayOfMonth <= 14,
            3 => $dayOfMonth >= 15 && $dayOfMonth <= 21,
            4 => $dayOfMonth >= 22 && $dayOfMonth <= 28,
            5 => $dayOfMonth >= 29,
            default => true,
        };
    }
}
