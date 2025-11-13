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
                CASE 
                    WHEN TRIM(COALESCE(sp.ojo, '')) <> '' THEN sp.ojo
                    WHEN JSON_EXTRACT(sp.detalles_json, '$[0].lateralidad') IS NOT NULL
                         AND JSON_UNQUOTE(JSON_EXTRACT(sp.detalles_json, '$[0].lateralidad')) <> ''
                    THEN JSON_UNQUOTE(JSON_EXTRACT(sp.detalles_json, '$[0].lateralidad'))
                    ELSE NULL
                END AS ojo,
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
              AND sp.procedimiento <> ''
              AND sp.procedimiento != 'SELECCIONE' 
              AND sp.doctor != 'SELECCIONE'";


        // 🧩 Filtros dinámicos
        $params = [];

        if (!empty($filtros['afiliacion'])) {
            $sql .= " AND pd.afiliacion COLLATE utf8mb4_unicode_ci LIKE ?";
            $params[] = '%' . trim($filtros['afiliacion']) . '%';
        }

        if (!empty($filtros['doctor'])) {
            $sql .= " AND sp.doctor COLLATE utf8mb4_unicode_ci LIKE ?";
            $params[] = '%' . trim($filtros['doctor']) . '%';
        }

        if (!empty($filtros['prioridad'])) {
            // Ejemplo: prioridad puede ser 'normal', 'pendiente' o 'urgente'
            $sql .= " AND sp.prioridad COLLATE utf8mb4_unicode_ci = ?";
            $params[] = trim($filtros['prioridad']);
        }

        if (!empty($filtros['fechaTexto']) && str_contains($filtros['fechaTexto'], ' - ')) {
            [$inicio, $fin] = explode(' - ', $filtros['fechaTexto']);
            $inicio = DateTime::createFromFormat('d-m-Y', trim($inicio))->format('Y-m-d');
            $fin = DateTime::createFromFormat('d-m-Y', trim($fin))->format('Y-m-d');
            $sql .= " AND DATE(cd.fecha) BETWEEN ? AND ?";
            $params[] = $inicio;
            $params[] = $fin;
        }

        $sql .= " ORDER BY COALESCE(cd.fecha, sp.created_at) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchTurneroSolicitudes(array $estados = []): array
    {
        $normalizar = static function ($estado): string {
            $estado = is_string($estado) ? trim($estado) : '';

            if ($estado === '') {
                return '';
            }

            return function_exists('mb_strtolower')
                ? mb_strtolower($estado, 'UTF-8')
                : strtolower($estado);
        };

        $estadosNormalizados = array_values(array_filter(array_map($normalizar, $estados)));

        if (empty($estadosNormalizados)) {
            $estadosNormalizados = array_map($normalizar, ['Recibido', 'Llamado', 'En atención', 'En atencion']);
        }

        $estadosNormalizados = array_values(array_unique(array_filter($estadosNormalizados)));

        if (empty($estadosNormalizados)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($estadosNormalizados), '?'));

        $sql = "SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                sp.estado,
                sp.prioridad,
                sp.created_at,
                sp.turno
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            WHERE LOWER(sp.estado) IN ($placeholders)
              AND sp.turno IS NOT NULL
            ORDER BY CASE WHEN sp.turno IS NULL THEN 1 ELSE 0 END,
                     sp.turno ASC,
                     sp.created_at ASC,
                     sp.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($estadosNormalizados);

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

    public function llamarTurno(?int $id, ?int $turno, string $nuevoEstado = 'Llamado'): ?array
    {
        $this->db->beginTransaction();

        try {
            if ($turno !== null && $turno > 0) {
                $sql = "SELECT sp.id, sp.turno, sp.estado FROM solicitud_procedimiento sp WHERE sp.turno = :turno FOR UPDATE";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':turno', $turno, \PDO::PARAM_INT);
            } else {
                $sql = "SELECT sp.id, sp.turno, sp.estado FROM solicitud_procedimiento sp WHERE sp.id = :id FOR UPDATE";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
            }

            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                $this->db->rollBack();
                return null;
            }

            $estadoActualNormalizado = $this->normalizarEstadoTurnero((string) ($registro['estado'] ?? ''));

            if ($estadoActualNormalizado === null) {
                $this->db->rollBack();
                return null;
            }

            if (empty($registro['turno'])) {
                $registro['turno'] = $this->asignarTurnoSiNecesario((int) $registro['id']);
            }

            $update = $this->db->prepare('UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id');
            $update->bindParam(':estado', $nuevoEstado, \PDO::PARAM_STR);
            $update->bindParam(':id', $registro['id'], \PDO::PARAM_INT);
            $update->execute();

            $detallesStmt = $this->db->prepare("SELECT
                    sp.id,
                    sp.turno,
                    sp.estado,
                    sp.hc_number,
                    sp.form_id,
                    sp.prioridad,
                    sp.created_at,
                    CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
                FROM solicitud_procedimiento sp
                INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
                WHERE sp.id = :id");

            $detallesStmt->bindParam(':id', $registro['id'], \PDO::PARAM_INT);
            $detallesStmt->execute();
            $detalles = $detallesStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->db->commit();

            return $detalles;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function normalizarEstadoTurnero(string $estado): ?string
    {
        $mapa = [
            'recibido' => 'Recibido',
            'llamado' => 'Llamado',
            'en atencion' => 'En atención',
            'en atención' => 'En atención',
            'atendido' => 'Atendido',
        ];

        $estadoLimpio = trim($estado);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($estadoLimpio, 'UTF-8')
            : strtolower($estadoLimpio);

        return $mapa[$clave] ?? null;
    }

    private function asignarTurnoSiNecesario(int $id): ?int
    {
        $consulta = $this->db->prepare('SELECT turno FROM solicitud_procedimiento WHERE id = :id FOR UPDATE');
        $consulta->bindParam(':id', $id, \PDO::PARAM_INT);
        $consulta->execute();
        $actual = $consulta->fetchColumn();

        if ($actual !== false && $actual !== null) {
            return (int) $actual;
        }

        $maxStmt = $this->db->query('SELECT turno FROM solicitud_procedimiento WHERE turno IS NOT NULL ORDER BY turno DESC LIMIT 1 FOR UPDATE');
        $maxTurno = $maxStmt ? (int) $maxStmt->fetchColumn() : 0;
        $siguiente = $maxTurno + 1;

        $update = $this->db->prepare('UPDATE solicitud_procedimiento SET turno = :turno WHERE id = :id AND turno IS NULL');
        $update->bindParam(':turno', $siguiente, \PDO::PARAM_INT);
        $update->bindParam(':id', $id, \PDO::PARAM_INT);
        $update->execute();

        if ($update->rowCount() === 0) {
            $consulta->execute();
            $actual = $consulta->fetchColumn();
            return $actual !== false ? (int) $actual : null;
        }

        return $siguiente;
    }
}