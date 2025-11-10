<?php

namespace Controllers;

use PDO;
use Models\Cirugia;

class ReporteCirugiasController
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerCirugias()
    {
        $sql = "SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                       pr.fecha_inicio, pr.id, pr.membrete, pr.form_id, pr.hora_inicio, pr.hora_fin, pr.printed,
                       pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, 
                       pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pr.diagnosticos_previos, pp.procedimiento_proyectado,
                       pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, 
                       pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante, pr.status,
                       CASE WHEN bm.id IS NOT NULL THEN 1 ELSE 0 END AS existeBilling
                FROM patient_data p 
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
                ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($row) => new Cirugia($row), $rows);
    }

    /**
     * Devuelve solo los campos mínimos requeridos para la vista de la tabla en cirugias.php
     */
    public function obtenerListaCirugias()
    {
        $sql = "SELECT 
                    p.hc_number, 
                    p.fname, 
                    p.lname, 
                    p.lname2, 
                    p.afiliacion, 
                    pr.fecha_inicio, 
                    pr.membrete, 
                    pr.form_id, 
                    pr.printed, 
                    pr.status,
                    CASE WHEN bm.id IS NOT NULL THEN 1 ELSE 0 END AS existeBilling
                FROM protocolo_data pr
                INNER JOIN patient_data p ON p.hc_number = pr.hc_number
                LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
                ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($row) => new Cirugia($row), $rows);
    }

    public function obtenerCirugiaPorId(string $form_id, string $hc_number): ?Cirugia
    {
        $sql = "SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                   pr.fecha_inicio, pr.id, pr.membrete, pr.form_id, pr.procedimiento_id, pr.hora_inicio, pr.hora_fin, pr.printed,
                   pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, 
                   pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pr.diagnosticos_previos, pp.procedimiento_proyectado,
                   pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, 
                   pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante, pr.status, pr.insumos, pr.medicamentos
            FROM patient_data p 
            INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            WHERE pr.form_id = ? AND p.hc_number = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc_number]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? new \Models\Cirugia($result) : null;
    }

    public function obtenerInsumosDisponibles(string $afiliacion): array
    {
        $afiliacion = strtolower($afiliacion);

        $sql = "
        SELECT 
            id, categoria,
            IF(:afiliacion LIKE '%issfa%' AND producto_issfa <> '', producto_issfa, nombre) AS nombre_final,
            codigo_isspol, codigo_issfa, codigo_iess, codigo_msp
        FROM insumos
        GROUP BY id
        ORDER BY nombre_final
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['afiliacion' => $afiliacion]);

        $insumosDisponibles = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $categoria = $row['categoria'];
            $id = $row['id'];
            $insumosDisponibles[$categoria][$id] = [
                'id' => $id,
                'nombre' => trim($row['nombre_final']),
                'codigo_isspol' => $row['codigo_isspol'],
                'codigo_issfa' => $row['codigo_issfa'],
                'codigo_iess' => $row['codigo_iess'],
                'codigo_msp' => $row['codigo_msp']
            ];
        }

        return $insumosDisponibles;
    }

    public function obtenerInsumosPorProtocolo(string $procedimiento_id, ?string $jsonInsumosProtocolo): array
    {
        if (!empty($jsonInsumosProtocolo)) {
            return json_decode($jsonInsumosProtocolo, true);
        }

        $sql = "SELECT insumos FROM insumos_pack WHERE procedimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procedimiento_id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return json_decode($row['insumos'] ?? '[]', true);
    }
}