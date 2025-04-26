<?php

namespace Models;

use PDO;

class ProtocoloModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerProtocolo(string $form_id, string $hc_number): ?array
    {
        $sql = "SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.afiliacion, p.sexo, p.ciudad,
                       pr.form_id, pr.fecha_inicio, pr.hora_inicio, pr.fecha_fin, pr.hora_fin, pr.cirujano_1, pr.instrumentista,
                       pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, pr.segundo_ayudante,
                       pr.ayudante_anestesia, pr.tercer_ayudante, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo,
                       pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos, pr.lateralidad,
                       pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado, pr.procedimiento_id
                FROM patient_data p
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                WHERE pr.form_id = ? AND p.hc_number = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc_number]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function obtenerNombreProcedimientoProyectado(?string $texto): string
    {
        if (!$texto) return '';
        $parts = explode(' - ', $texto);
        return $parts[2] ?? '';
    }

    public function obtenerCodigosProcedimientos(string $proceduresJson): string
    {
        $procedures = json_decode($proceduresJson, true);
        $codes = [];
        if (is_array($procedures)) {
            foreach ($procedures as $proc) {
                if (isset($proc['procInterno'])) {
                    $parts = explode(' - ', $proc['procInterno']);
                    if (isset($parts[1])) {
                        $codes[] = $parts[1];
                    }
                }
            }
        }
        return implode('/', $codes);
    }
}
