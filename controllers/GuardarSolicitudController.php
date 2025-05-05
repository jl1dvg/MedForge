<?php

namespace Controllers;

use PDO;

class GuardarSolicitudController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'], $data['solicitudes'])) {
            return ["success" => false, "message" => "Datos no válidos o incompletos"];
        }

        $sql = "INSERT INTO solicitud_procedimiento 
                (hc_number, form_id, secuencia, tipo, afiliacion, procedimiento, doctor, fecha, duracion, ojo, prioridad, producto, observacion) 
                VALUES (:hc, :form_id, :secuencia, :tipo, :afiliacion, :procedimiento, :doctor, :fecha, :duracion, :ojo, :prioridad, :producto, :observacion)
                ON DUPLICATE KEY UPDATE 
                    tipo = VALUES(tipo),
                    afiliacion = VALUES(afiliacion),
                    procedimiento = VALUES(procedimiento),
                    doctor = VALUES(doctor),
                    fecha = VALUES(fecha),
                    duracion = VALUES(duracion),
                    ojo = VALUES(ojo),
                    prioridad = VALUES(prioridad),
                    producto = VALUES(producto),
                    observacion = VALUES(observacion)";

        $stmt = $this->db->prepare($sql);

        foreach ($data['solicitudes'] as $solicitud) {
            $stmt->execute([
                ':hc' => $data['hcNumber'],
                ':form_id' => $data['form_id'],
                ':secuencia' => $solicitud['secuencia'] ?? null,
                ':tipo' => $solicitud['tipo'] ?? null,
                ':afiliacion' => $solicitud['afiliacion'] ?? null,
                ':procedimiento' => $solicitud['procedimiento'] ?? null,
                ':doctor' => $solicitud['doctor'] ?? null,
                ':fecha' => $solicitud['fecha'] ?? null,
                ':duracion' => $solicitud['duracion'] ?? null,
                ':ojo' => $solicitud['ojo'] ?? null,
                ':prioridad' => $solicitud['prioridad'] ?? null,
                ':producto' => $solicitud['producto'] ?? null,
                ':observacion' => $solicitud['observacion'] ?? null,
            ]);
        }

        return ["success" => true, "message" => "Solicitudes guardadas o actualizadas correctamente"];
    }
}