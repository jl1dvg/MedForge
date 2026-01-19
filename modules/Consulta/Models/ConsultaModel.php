<?php

namespace Modules\Consulta\Models;

use PDO;

class ConsultaModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerConsultaConProcedimiento(string $form_id, string $hc): array
    {
        $sql = "SELECT 
                cd.*,
                pp.doctor AS procedimiento_doctor,
                pp.procedimiento_proyectado AS procedimiento_nombre,
                u.first_name AS doctor_fname,
                u.middle_name AS doctor_mname,
                u.last_name AS doctor_lname,
                u.second_last_name AS doctor_lname2,
                u.cedula AS doctor_cedula,
                u.firma AS doctor_firma,
                u.signature_path AS doctor_signature_path
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = cd.form_id AND pp.hc_number = cd.hc_number
            LEFT JOIN users u
                ON UPPER(TRIM(pp.doctor)) = u.nombre_norm
                OR UPPER(TRIM(pp.doctor)) = u.nombre_norm_rev
            WHERE cd.form_id = ? AND cd.hc_number = ?
            ORDER BY cd.fecha DESC
            LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerDxDeConsulta(string $form_id): array
    {
        $sql = "SELECT * FROM diagnosticos_asignados
                WHERE form_id = ? ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDxDerivacion(string $form_id): array
    {
        $sql = "SELECT diagnostico
                FROM derivaciones_form_id
                WHERE form_id = ? ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
}
