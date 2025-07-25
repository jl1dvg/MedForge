<?php

namespace Models;

use PDO;

class SolicitudModel
{
    protected $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function fetchSolicitudesConDetalles(): array
    {
        $sql = "SELECT 
                    sp.id,
                    sp.hc_number, 
                    sp.form_id,
                    CONCAT(pd.fname, ' ', pd.mname, ' ', pd.lname, ' ', pd.lname2) AS full_name, 
                    sp.tipo,
                    pd.afiliacion,
                    sp.procedimiento,
                    sp.doctor,
                    sp.estado,
                    sp.fecha,
                    sp.duracion,
                    sp.ojo,
                    sp.prioridad,
                    sp.producto,
                    sp.observacion,
                    sp.secuencia,
                    sp.created_at,
                    pd.fecha_caducidad,
                    cd.diagnosticos
                FROM solicitud_procedimiento sp
                INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
                LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
                WHERE sp.procedimiento IS NOT NULL 
                  AND TRIM(sp.procedimiento) != '' 
                  AND sp.procedimiento != 'SELECCIONE' 
                  AND sp.doctor != 'SELECCIONE'
                ORDER BY sp.fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDerivacionPorFormId($form_id)
    {
        $sql = "SELECT * FROM derivaciones_form_id WHERE form_id = ? ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerFechaCreacionSolicitud($form_id, $hc)
    {
        $sql = "SELECT * FROM solicitud_procedimiento
                WHERE form_id = ? AND hc_number = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDxDeSolicitud($form_id)
    {
        $sql = "SELECT * FROM diagnosticos_asignados
                WHERE form_id = ? ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $sql = "UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new \Exception("Error al preparar la consulta");
        }

        $stmt->bindParam(':estado', $estado, \PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new \Exception("No se pudo actualizar el estado");
        }
    }
}