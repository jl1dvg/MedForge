<?php

namespace Models;

use PDO;
use DateTime;

class SolicitudModel
{
    protected $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function fetchSolicitudesConDetallesFiltrado(array $filtros = []): array
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
                cd.fecha,
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
              AND sp.doctor != 'SELECCIONE'";


        // 🧩 Filtros dinámicos
        $params = [];

        if (!empty($filtros['afiliacion'])) {
            $sql .= " AND LOWER(pd.afiliacion) LIKE ?";
            $params[] = '%' . strtolower($filtros['afiliacion']) . '%';
        }

        if (!empty($filtros['doctor'])) {
            $sql .= " AND LOWER(sp.doctor) LIKE ?";
            $params[] = '%' . strtolower($filtros['doctor']) . '%';
        }

        if (!empty($filtros['prioridad'])) {
            // Ejemplo: prioridad puede ser 'normal', 'pendiente' o 'urgente'
            $sql .= " AND LOWER(sp.prioridad) = ?";
            $params[] = strtolower($filtros['prioridad']);
        }

        if (!empty($filtros['fechaTexto']) && str_contains($filtros['fechaTexto'], ' - ')) {
            [$inicio, $fin] = explode(' - ', $filtros['fechaTexto']);
            $inicioDate = DateTime::createFromFormat('d-m-Y', trim($inicio));
            $finDate = DateTime::createFromFormat('d-m-Y', trim($fin));

            if ($inicioDate && $finDate) {
                $sql .= " AND DATE(cd.fecha) BETWEEN ? AND ?";
                $params[] = $inicioDate->format('Y-m-d');
                $params[] = $finDate->format('Y-m-d');
            }
        }

        $sql .= " ORDER BY cd.fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchTurneroSolicitudes(array $estados = []): array
    {
        $estados = array_values(array_filter(array_map('trim', $estados)));
        if (empty($estados)) {
            $estados = ['Listo para Agenda'];
        }

        $placeholders = implode(', ', array_fill(0, count($estados), '?'));

        $sql = "SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                sp.estado,
                sp.prioridad,
                sp.created_at
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            WHERE sp.estado IN ($placeholders)
            ORDER BY sp.created_at ASC, sp.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($estados);

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

    public function obtenerConsultaDeSolicitud($form_id)
    {
        $sql = "SELECT * FROM consulta_data
                WHERE form_id = ? ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // una sola fila
    }

    public function obtenerDatosYCirujanoSolicitud($form_id, $hc)
    {
        $sql = "SELECT sp.*, u.*
            FROM solicitud_procedimiento sp
            LEFT JOIN users u 
                ON LOWER(TRIM(sp.doctor)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
            WHERE sp.form_id = ? AND sp.hc_number = ?
            ORDER BY sp.created_at DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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