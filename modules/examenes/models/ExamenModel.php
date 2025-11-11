<?php

namespace Modules\Examenes\Models;

use DateTime;
use DateTimeImmutable;
use Modules\CRM\Services\LeadConfigurationService;
use PDO;

class ExamenModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function fetchExamenesConDetallesFiltrado(array $filtros = []): array
    {
        $sql = "SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                ce.examen_codigo,
                ce.examen_nombre,
                ce.doctor,
                ce.solicitante,
                ce.estado,
                ce.prioridad,
                ce.lateralidad,
                ce.observaciones,
                ce.turno,
                ce.consulta_fecha,
                ce.created_at,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                detalles.crm_lead_id AS crm_lead_id,
                responsable.nombre AS crm_responsable_nombre,
                responsable.profile_photo AS crm_responsable_avatar,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ''
                      AND LOWER(TRIM(ce.doctor)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS doctor_avatar,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM consulta_examenes ce
            INNER JOIN patient_data pd ON ce.hc_number = pd.hc_number
            LEFT JOIN examen_crm_detalles detalles ON detalles.examen_id = ce.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN (
                SELECT examen_id, COUNT(*) AS total_notas
                FROM examen_crm_notas
                GROUP BY examen_id
            ) notas ON notas.examen_id = ce.id
            LEFT JOIN (
                SELECT examen_id, COUNT(*) AS total_adjuntos
                FROM examen_crm_adjuntos
                GROUP BY examen_id
            ) adjuntos ON adjuntos.examen_id = ce.id
            LEFT JOIN (
                SELECT examen_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN estado IN ('pendiente','en_progreso') THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN estado IN ('pendiente','en_progreso') THEN due_date END) AS proximo_vencimiento
                FROM examen_crm_tareas
                GROUP BY examen_id
            ) tareas ON tareas.examen_id = ce.id
            WHERE ce.examen_nombre IS NOT NULL
              AND ce.examen_nombre <> ''";

        $params = [];

        if (!empty($filtros['afiliacion'])) {
            $sql .= " AND pd.afiliacion COLLATE utf8mb4_unicode_ci LIKE ?";
            $params[] = '%' . trim($filtros['afiliacion']) . '%';
        }

        if (!empty($filtros['doctor'])) {
            $sql .= " AND ce.doctor COLLATE utf8mb4_unicode_ci LIKE ?";
            $params[] = '%' . trim($filtros['doctor']) . '%';
        }

        if (!empty($filtros['prioridad'])) {
            $sql .= " AND ce.prioridad COLLATE utf8mb4_unicode_ci = ?";
            $params[] = trim($filtros['prioridad']);
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND ce.estado COLLATE utf8mb4_unicode_ci = ?";
            $params[] = trim($filtros['estado']);
        }

        if (!empty($filtros['fechaTexto']) && str_contains($filtros['fechaTexto'], ' - ')) {
            [$inicio, $fin] = explode(' - ', $filtros['fechaTexto']);
            $inicioDate = DateTime::createFromFormat('d-m-Y', trim($inicio));
            $finDate = DateTime::createFromFormat('d-m-Y', trim($fin));

            if ($inicioDate && $finDate) {
                $sql .= " AND DATE(ce.consulta_fecha) BETWEEN ? AND ?";
                $params[] = $inicioDate->format('Y-m-d');
                $params[] = $finDate->format('Y-m-d');
            }
        }

        $sql .= " ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchTurneroExamenes(array $estados = []): array
    {
        $estados = array_values(array_filter(array_map('trim', $estados)));
        if (empty($estados)) {
            $estados = ['Llamado', 'En atención'];
        }

        $placeholders = implode(', ', array_fill(0, count($estados), '?'));

        $sql = "SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                ce.estado,
                ce.prioridad,
                ce.created_at,
                ce.turno,
                ce.examen_nombre
            FROM consulta_examenes ce
            INNER JOIN patient_data pd ON ce.hc_number = pd.hc_number
            WHERE ce.estado IN ($placeholders)
            ORDER BY CASE WHEN ce.turno IS NULL THEN 1 ELSE 0 END,
                     ce.turno ASC,
                     ce.created_at ASC,
                     ce.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($estados);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function actualizarEstado(int $id, string $estado): array
    {
        $this->db->beginTransaction();

        try {
            $datosPreviosStmt = $this->db->prepare("SELECT
                    ce.id,
                    ce.form_id,
                    ce.estado,
                    ce.turno,
                    ce.hc_number,
                    ce.examen_nombre,
                    ce.prioridad,
                    ce.doctor,
                    ce.solicitante,
                    ce.consulta_fecha,
                    CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
                FROM consulta_examenes ce
                LEFT JOIN patient_data pd ON pd.hc_number = ce.hc_number
                WHERE ce.id = :id
                FOR UPDATE");
            $datosPreviosStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $datosPreviosStmt->execute();
            $datosPrevios = $datosPreviosStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmt = $this->db->prepare('UPDATE consulta_examenes SET estado = :estado WHERE id = :id');
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $turno = null;
            if (strcasecmp($estado, 'Recibido') === 0) {
                $turno = $this->asignarTurnoSiNecesario($id);
            }

            $datosStmt = $this->db->prepare("SELECT
                    ce.id,
                    ce.form_id,
                    ce.estado,
                    ce.turno,
                    ce.hc_number,
                    ce.examen_nombre,
                    ce.prioridad,
                    ce.doctor,
                    ce.solicitante,
                    ce.consulta_fecha,
                    CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
                FROM consulta_examenes ce
                LEFT JOIN patient_data pd ON pd.hc_number = ce.hc_number
                WHERE ce.id = :id");
            $datosStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $datosStmt->execute();
            $datos = $datosStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->db->commit();

            return [
                'id' => $id,
                'form_id' => $datos['form_id'] ?? null,
                'hc_number' => $datos['hc_number'] ?? null,
                'estado' => $datos['estado'] ?? $estado,
                'turno' => $datos['turno'] ?? $turno,
                'full_name' => isset($datos['full_name']) && trim((string) $datos['full_name']) !== ''
                    ? trim((string) $datos['full_name'])
                    : null,
                'examen_nombre' => $datos['examen_nombre'] ?? null,
                'prioridad' => $datos['prioridad'] ?? null,
                'doctor' => $datos['doctor'] ?? null,
                'solicitante' => $datos['solicitante'] ?? null,
                'consulta_fecha' => $datos['consulta_fecha'] ?? null,
                'estado_anterior' => $datosPrevios['estado'] ?? null,
                'turno_anterior' => $datosPrevios['turno'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerExamenBasicoPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT
                ce.id,
                ce.form_id,
                ce.hc_number,
                ce.estado,
                ce.prioridad,
                ce.examen_nombre,
                ce.doctor,
                ce.solicitante,
                ce.turno,
                ce.consulta_fecha,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
            FROM consulta_examenes ce
            LEFT JOIN patient_data pd ON pd.hc_number = ce.hc_number
            WHERE ce.id = :id
            LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
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

    public function buscarExamenesProgramados(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $sql = "SELECT
                ce.id,
                ce.form_id,
                ce.hc_number,
                ce.estado,
                ce.prioridad,
                ce.examen_nombre,
                ce.doctor,
                ce.solicitante,
                ce.turno,
                ce.consulta_fecha,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
            FROM consulta_examenes ce
            INNER JOIN patient_data pd ON pd.hc_number = ce.hc_number
            WHERE ce.consulta_fecha BETWEEN :desde AND :hasta
            ORDER BY ce.consulta_fecha ASC, ce.id ASC";

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

    public function llamarTurno(?int $id, ?int $turno, string $nuevoEstado = 'Llamado'): ?array
    {
        $this->db->beginTransaction();

        try {
            if ($turno !== null && $turno > 0) {
                $sql = 'SELECT ce.id, ce.turno, ce.estado FROM consulta_examenes ce WHERE ce.turno = :turno FOR UPDATE';
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':turno', $turno, PDO::PARAM_INT);
            } else {
                $sql = 'SELECT ce.id, ce.turno, ce.estado FROM consulta_examenes ce WHERE ce.id = :id FOR UPDATE';
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            }

            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                $this->db->rollBack();
                return null;
            }

            if (empty($registro['turno'])) {
                $registro['turno'] = $this->asignarTurnoSiNecesario((int) $registro['id']);
            }

            $update = $this->db->prepare('UPDATE consulta_examenes SET estado = :estado WHERE id = :id');
            $update->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $update->bindValue(':id', $registro['id'], PDO::PARAM_INT);
            $update->execute();

            $detallesStmt = $this->db->prepare("SELECT
                    ce.id,
                    ce.turno,
                    ce.estado,
                    ce.hc_number,
                    ce.form_id,
                    ce.prioridad,
                    ce.created_at,
                    ce.examen_nombre,
                    CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
                FROM consulta_examenes ce
                INNER JOIN patient_data pd ON ce.hc_number = pd.hc_number
                WHERE ce.id = :id");
            $detallesStmt->bindValue(':id', $registro['id'], PDO::PARAM_INT);
            $detallesStmt->execute();
            $detalles = $detallesStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->db->commit();

            return $detalles;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function asignarTurnoSiNecesario(int $id): ?int
    {
        $consulta = $this->db->prepare('SELECT turno FROM consulta_examenes WHERE id = :id FOR UPDATE');
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
        $actual = $consulta->fetchColumn();

        if ($actual !== false && $actual !== null) {
            return (int) $actual;
        }

        $maxStmt = $this->db->query('SELECT turno FROM consulta_examenes WHERE turno IS NOT NULL ORDER BY turno DESC LIMIT 1 FOR UPDATE');
        $maxTurno = $maxStmt ? (int) $maxStmt->fetchColumn() : 0;
        $siguiente = $maxTurno + 1;

        $update = $this->db->prepare('UPDATE consulta_examenes SET turno = :turno WHERE id = :id AND turno IS NULL');
        $update->bindValue(':turno', $siguiente, PDO::PARAM_INT);
        $update->bindValue(':id', $id, PDO::PARAM_INT);
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
