<?php

namespace Modules\Examenes\Models;

use PDO;
use DateTime;
use DateTimeImmutable;
use Modules\CRM\Services\LeadConfigurationService;

class ExamenesModel
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
                pd.celular AS paciente_celular,
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
                cd.diagnosticos,
                sp.turno,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                responsable.nombre AS crm_responsable_nombre,
                responsable.profile_photo AS crm_responsable_avatar,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ''
                      AND LOWER(TRIM(sp.doctor)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS doctor_avatar,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN (
                SELECT solicitud_id, COUNT(*) AS total_notas
                FROM solicitud_crm_notas
                GROUP BY solicitud_id
            ) notas ON notas.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id, COUNT(*) AS total_adjuntos
                FROM solicitud_crm_adjuntos
                GROUP BY solicitud_id
            ) adjuntos ON adjuntos.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN estado IN ('pendiente','en_progreso') THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN estado IN ('pendiente','en_progreso') THEN due_date END) AS proximo_vencimiento
                FROM solicitud_crm_tareas
                GROUP BY solicitud_id
            ) tareas ON tareas.solicitud_id = sp.id
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
            $estados = ['Llamado', 'En atención'];
        }

        $placeholders = implode(', ', array_fill(0, count($estados), '?'));

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
            WHERE sp.estado IN ($placeholders)
            ORDER BY CASE WHEN sp.turno IS NULL THEN 1 ELSE 0 END,
                     sp.turno ASC,
                     sp.created_at ASC,
                     sp.id ASC";

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

    public function actualizarEstado(int $id, string $estado): array
    {
        $this->db->beginTransaction();

        try {
            $datosPreviosStmt = $this->db->prepare("SELECT
                    sp.id,
                    sp.form_id,
                    sp.estado,
                    sp.turno,
                    sp.hc_number,
                    sp.procedimiento,
                    sp.prioridad,
                    sp.doctor,
                    sp.tipo,
                    sp.afiliacion,
                    COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                    CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
                FROM solicitud_procedimiento sp
                LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
                LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                WHERE sp.id = :id
                FOR UPDATE");
            $datosPreviosStmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $datosPreviosStmt->execute();
            $datosPrevios = $datosPreviosStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $sql = "UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                throw new \RuntimeException('Error al preparar la consulta');
            }

            $stmt->bindParam(':estado', $estado, \PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, \PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new \RuntimeException('No se pudo actualizar el estado');
            }

            $turno = null;
            if (strcasecmp($estado, 'Recibido') === 0) {
                $turno = $this->asignarTurnoSiNecesario($id);
            }

            $datosStmt = $this->db->prepare("SELECT
                    sp.id,
                    sp.form_id,
                    sp.estado,
                    sp.turno,
                    sp.hc_number,
                    sp.procedimiento,
                    sp.prioridad,
                    sp.doctor,
                    sp.tipo,
                    sp.afiliacion,
                    COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                    CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
                FROM solicitud_procedimiento sp
                LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
                LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                WHERE sp.id = :id");
            $datosStmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $datosStmt->execute();
            $datos = $datosStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->db->commit();

            return [
                'id'           => $id,
                'form_id'      => $datos['form_id'] ?? null,
                'hc_number'    => $datos['hc_number'] ?? null,
                'estado'       => $datos['estado'] ?? $estado,
                'turno'        => $datos['turno'] ?? $turno,
                'full_name'    => isset($datos['full_name']) && trim((string) $datos['full_name']) !== ''
                    ? trim((string) $datos['full_name'])
                    : null,
                'procedimiento' => $datos['procedimiento'] ?? null,
                'prioridad'     => $datos['prioridad'] ?? null,
                'doctor'        => $datos['doctor'] ?? null,
                'tipo'          => $datos['tipo'] ?? null,
                'afiliacion'    => $datos['afiliacion'] ?? null,
                'fecha_programada' => $datos['fecha_programada'] ?? null,
                'estado_anterior' => $datosPrevios['estado'] ?? null,
                'turno_anterior'  => $datosPrevios['turno'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerSolicitudBasicaPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT
                sp.id,
                sp.form_id,
                sp.hc_number,
                sp.estado,
                sp.prioridad,
                sp.doctor,
                sp.tipo,
                sp.procedimiento,
                sp.afiliacion,
                sp.turno,
                COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
            LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            WHERE sp.id = :id
            LIMIT 1");
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return null;
        }

        if (isset($row['full_name'])) {
            $row['full_name'] = trim((string) $row['full_name']) !== ''
                ? trim((string) $row['full_name'])
                : null;
        }

        return $row;
    }

    public function buscarSolicitudesProgramadas(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $columnas = [
            'sp.id',
            'sp.form_id',
            'sp.hc_number',
            'sp.estado',
            'sp.prioridad',
            'sp.procedimiento',
            'sp.doctor',
            'sp.tipo',
            'sp.afiliacion',
            'sp.turno',
            'COALESCE(cd.fecha, sp.fecha) AS fecha_programada',
            'pd.fecha_caducidad',
        ];

        if ($this->consultaDataTieneColumna('quirofano')) {
            $columnas[] = 'cd.quirofano';
        } else {
            $columnas[] = 'NULL AS quirofano';
        }

        $columnas[] = "CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name";

        $sql = sprintf(
            "SELECT\n                %s\n            FROM solicitud_procedimiento sp\n            INNER JOIN patient_data pd ON pd.hc_number = sp.hc_number\n            LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id\n            WHERE COALESCE(cd.fecha, sp.fecha) BETWEEN :desde AND :hasta\n            ORDER BY COALESCE(cd.fecha, sp.fecha) ASC, sp.id ASC",
            implode(",\n                ", $columnas)
        );

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':desde', $desde->format('Y-m-d H:i:s'));
        $stmt->bindValue(':hasta', $hasta->format('Y-m-d H:i:s'));
        $stmt->execute();

        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($resultados as &$row) {
            if (isset($row['full_name'])) {
                $row['full_name'] = trim((string) $row['full_name']) !== ''
                    ? trim((string) $row['full_name'])
                    : null;
            }
        }
        unset($row);

        return $resultados;
    }

    private function consultaDataTieneColumna(string $columna): bool
    {
        static $cache = [];

        if (array_key_exists($columna, $cache)) {
            return $cache[$columna];
        }

        $sql = "SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'consulta_data'
              AND COLUMN_NAME = :columna";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
        $stmt->execute();

        $cache[$columna] = ((int) $stmt->fetchColumn()) > 0;

        return $cache[$columna];
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

    public function listarUsuariosAsignables(): array
    {
        $service = new LeadConfigurationService($this->db);

        return $service->getAssignableUsers();
    }

    public function obtenerFuentesCrm(): array
    {
        $service = new LeadConfigurationService($this->db);

        return $service->getSources();
    }
}
