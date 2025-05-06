<?php

namespace Controllers;

use PDO;

class GuardarPrefacturaController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): array
    {
        $sql = "
            INSERT INTO prefactura_paciente (
                sede, area, afiliacion, parentesco, 
                hc_number, tipo_afiliacion, 
                numero_aprobacion, tipo_plan, 
                fecha_registro, fecha_vigencia, 
                cod_derivacion, num_secuencial_derivacion, 
                num_historia, examen_fisico, 
                observaciones, procedimientos, diagnosticos
            ) 
            VALUES (
                :sede, :area, :afiliacion, :parentesco, 
                :hc_number, :tipo_afiliacion, :numero_aprobacion, :tipo_plan,
                :fecha_registro, :fecha_vigencia, :cod_derivacion, :num_secuencial_derivacion,
                :num_historia, :examen_fisico, :observaciones, :procedimientos, :diagnosticos
            )
            ON DUPLICATE KEY UPDATE 
                sede = VALUES(sede),
                area = VALUES(area),
                afiliacion = VALUES(afiliacion),
                parentesco = VALUES(parentesco),
                tipo_afiliacion = VALUES(tipo_afiliacion),
                numero_aprobacion = VALUES(numero_aprobacion),
                tipo_plan = VALUES(tipo_plan),
                fecha_registro = VALUES(fecha_registro),
                fecha_vigencia = VALUES(fecha_vigencia),
                cod_derivacion = VALUES(cod_derivacion),
                num_secuencial_derivacion = VALUES(num_secuencial_derivacion),
                num_historia = VALUES(num_historia),
                examen_fisico = VALUES(examen_fisico),
                observaciones = VALUES(observaciones),
                procedimientos = VALUES(procedimientos),
                diagnosticos = VALUES(diagnosticos)
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':sede' => $data['sede'] ?? null,
            ':area' => $data['area'] ?? null,
            ':afiliacion' => $data['afiliacion'] ?? null,
            ':parentesco' => $data['parentesco'] ?? null,
            ':hc_number' => $data['hcNumber'] ?? null,
            ':tipo_afiliacion' => $data['tipoAfiliacion'] ?? null,
            ':numero_aprobacion' => $data['numeroAprobacion'] ?? null,
            ':tipo_plan' => $data['tipoPlan'] ?? null,
            ':fecha_registro' => $data['fechaRegistro'] ?? null,
            ':fecha_vigencia' => $data['fechaVigencia'] ?? null,
            ':cod_derivacion' => $data['codDerivacion'] ?? null,
            ':num_secuencial_derivacion' => $data['numSecuencialDerivacion'] ?? null,
            ':num_historia' => $data['numHistoria'] ?? null,
            ':examen_fisico' => $data['examenFisico'] ?? null,
            ':observaciones' => $data['observacion'] ?? null,
            ':procedimientos' => isset($data['procedimientos']) ? json_encode($data['procedimientos']) : null,
            ':diagnosticos' => isset($data['diagnosticos']) ? json_encode($data['diagnosticos']) : null
        ]);

        return ["success" => true, "message" => "Datos guardados correctamente."];
    }
}