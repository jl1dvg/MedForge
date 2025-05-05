<?php

namespace Controllers;

use PDO;

class GuardarProyeccionController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'], $data['procedimiento_proyectado'])) {
            return ["success" => false, "message" => "Datos faltantes o incompletos"];
        }

        $hcNumber = $data['hcNumber'];
        $form_id = $data['form_id'];
        $procedimiento = $data['procedimiento_proyectado'];
        $doctor = $data['doctor'] ?? null;

        // Guardar datos del paciente si hay nombres o afiliación
        if (isset($data['lname'], $data['fname'])) {
            $sqlPatient = "
                INSERT INTO patient_data (hc_number, lname, lname2, fname, mname, afiliacion, fecha_caducidad)
                VALUES (:hc, :lname, :lname2, :fname, :mname, :afiliacion, :caducidad)
                ON DUPLICATE KEY UPDATE 
                    lname = VALUES(lname),
                    lname2 = VALUES(lname2),
                    fname = VALUES(fname),
                    mname = VALUES(mname),
                    afiliacion = VALUES(afiliacion),
                    fecha_caducidad = VALUES(fecha_caducidad)
            ";

            $stmt = $this->db->prepare($sqlPatient);
            $stmt->execute([
                ':hc' => $hcNumber,
                ':lname' => $data['lname'] ?? null,
                ':lname2' => $data['lname2'] ?? null,
                ':fname' => $data['fname'] ?? null,
                ':mname' => $data['mname'] ?? null,
                ':afiliacion' => $data['afiliacion'] ?? null,
                ':caducidad' => $data['fechaCaducidad'] ?? null,
            ]);
        }

        // Guardar procedimiento proyectado
        $sql = "
            INSERT INTO procedimiento_proyectado (form_id, procedimiento_proyectado, doctor, hc_number)
            VALUES (:form_id, :procedimiento, :doctor, :hc)
            ON DUPLICATE KEY UPDATE 
                procedimiento_proyectado = VALUES(procedimiento_proyectado),
                doctor = VALUES(doctor)
        ";

        $stmt2 = $this->db->prepare($sql);
        $stmt2->execute([
            ':form_id' => $form_id,
            ':procedimiento' => $procedimiento,
            ':doctor' => $doctor,
            ':hc' => $hcNumber
        ]);

        return ["success" => true, "message" => "Datos guardados correctamente"];
    }
}