<?php

namespace Modules\Cirugias\Services;

use Modules\Cirugias\Models\Cirugia;
use PDO;

class CirugiasDashboardService
{
    public function __construct(private PDO $db)
    {
    }

    public function getTotalCirugias(string $start, string $end): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN :inicio AND :fin"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getCirugiasSinFacturar(string $start, string $end): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM protocolo_data pr
             LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
               AND bm.id IS NULL"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getDuracionPromedioMinutos(string $start, string $end): float
    {
        $stmt = $this->db->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin))
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN :inicio AND :fin
               AND hora_inicio IS NOT NULL
               AND hora_fin IS NOT NULL"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        return (float) $stmt->fetchColumn();
    }

    public function getEstadoProtocolos(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT pr.status, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo,
                    pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia,
                    pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos,
                    pp.procedimiento_proyectado, pr.fecha_inicio, pr.hora_inicio, pr.hora_fin
             FROM protocolo_data pr
             LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
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

    public function getCirugiasPorMes(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(fecha_inicio, '%Y-%m') AS mes, COUNT(*) AS total
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN :inicio AND :fin
             GROUP BY DATE_FORMAT(fecha_inicio, '%Y-%m')
             ORDER BY mes ASC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['mes'];
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getTopProcedimientos(string $start, string $end, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(membrete), '') AS procedimiento, COUNT(*) AS total
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN :inicio AND :fin
             GROUP BY NULLIF(TRIM(membrete), '')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
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

    public function getTopCirujanos(string $start, string $end, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(cirujano_1), '') AS cirujano, COUNT(*) AS total
             FROM protocolo_data
             WHERE fecha_inicio BETWEEN :inicio AND :fin
             GROUP BY NULLIF(TRIM(cirujano_1), '')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
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

    public function getCirugiasPorConvenio(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT NULLIF(TRIM(p.afiliacion), '') AS afiliacion, COUNT(*) AS total
             FROM protocolo_data pr
             INNER JOIN patient_data p ON p.hc_number = pr.hc_number
             WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
             GROUP BY NULLIF(TRIM(p.afiliacion), '')
             ORDER BY total DESC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $labels = [];
        $totals = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['afiliacion'] ?: 'Sin convenio';
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }
}
