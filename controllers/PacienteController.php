<?php

namespace Controllers;

use PDO;

class PacienteController
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    // Obtiene los pacientes con su última fecha de consulta
    public function obtenerPacientesConUltimaConsulta()
    {
        $sql = "
            SELECT 
                p.hc_number,
            CONCAT(p.fname, ' ', p.lname, ' ', p.lname2) AS full_name, 
            MAX(cd.fecha) AS ultima_fecha,  -- Obtener la fecha más reciente
            cd.diagnosticos, 
            (SELECT pp.doctor 
             FROM consulta_data cd2 
             INNER JOIN procedimiento_proyectado pp 
             ON cd2.form_id = pp.form_id 
             WHERE cd2.hc_number = p.hc_number 
             ORDER BY cd2.fecha DESC 
             LIMIT 1) AS doctor,  -- Subconsulta para obtener el último doctor
            p.fecha_caducidad, 
            p.afiliacion 
        FROM patient_data p
        INNER JOIN consulta_data cd ON p.hc_number = cd.hc_number
        GROUP BY p.hc_number
            ORDER BY 
                ultima_fecha DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}