<?php

namespace Modules\Examenes\Models;

use DateTime;
use DateTimeImmutable;
use Modules\CRM\Services\LeadConfigurationService;
use PDO;

class ExamenModel
{
    private PDO $db;
    /** @var array<string, bool>|null */
    private ?array $examenColumns = null;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function fetchExamenesConDetallesFiltrado(array $filtros = []): array
    {
        $derivacionCodigoExpr = $this->selectExamenColumn('derivacion_codigo');
        $derivacionPedidoExpr = $this->selectExamenColumn('derivacion_pedido_id');
        $derivacionLateralidadExpr = $this->selectExamenColumn('derivacion_lateralidad');
        $derivacionVigenciaExpr = $this->selectExamenColumn('derivacion_fecha_vigencia_sel');
        $derivacionPrefacturaExpr = $this->selectExamenColumn('derivacion_prefactura');

        $sql = "SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                ce.examen_codigo,
                ce.examen_nombre,
                pp.doctor,
                ce.solicitante,
                ce.estado,
                ce.prioridad,
                ce.lateralidad,
                ce.observaciones,
                {$derivacionCodigoExpr} AS derivacion_codigo,
                {$derivacionPedidoExpr} AS derivacion_pedido_id,
                {$derivacionLateralidadExpr} AS derivacion_lateralidad,
                {$derivacionVigenciaExpr} AS derivacion_fecha_vigencia_sel,
                {$derivacionPrefacturaExpr} AS derivacion_prefactura,
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
                      AND LOWER(TRIM(pp.doctor)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
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
            LEFT JOIN procedimiento_proyectado pp ON ce.form_id = pp.form_id
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
            $sql .= " AND pp.doctor COLLATE utf8mb4_unicode_ci LIKE ?";
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

    public function obtenerEstadosPorHc(string $hcNumber): array
    {
        $derivacionCodigoExpr = $this->selectExamenColumn('derivacion_codigo');
        $derivacionPedidoExpr = $this->selectExamenColumn('derivacion_pedido_id');
        $derivacionLateralidadExpr = $this->selectExamenColumn('derivacion_lateralidad');
        $derivacionVigenciaExpr = $this->selectExamenColumn('derivacion_fecha_vigencia_sel');
        $derivacionPrefacturaExpr = $this->selectExamenColumn('derivacion_prefactura');

        $sql = "SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.examen_codigo,
                ce.examen_nombre,
                ce.doctor,
                ce.solicitante,
                ce.estado,
                ce.prioridad,
                ce.lateralidad,
                ce.observaciones,
                {$derivacionCodigoExpr} AS derivacion_codigo,
                {$derivacionPedidoExpr} AS derivacion_pedido_id,
                {$derivacionLateralidadExpr} AS derivacion_lateralidad,
                {$derivacionVigenciaExpr} AS derivacion_fecha_vigencia_sel,
                {$derivacionPrefacturaExpr} AS derivacion_prefactura,
                ce.turno,
                ce.consulta_fecha,
                ce.created_at,
                ce.updated_at,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                detalles.crm_lead_id AS crm_lead_id,
                responsable.nombre AS crm_responsable_nombre,
                responsable.profile_photo AS crm_responsable_avatar,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
            FROM consulta_examenes ce
            INNER JOIN patient_data pd ON pd.hc_number = ce.hc_number
            LEFT JOIN examen_crm_detalles detalles ON detalles.examen_id = ce.id
            LEFT JOIN users responsable ON responsable.id = detalles.responsable_id
            WHERE ce.hc_number = :hc
            ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':hc' => $hcNumber]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerExamenPorFormHc(string $formId, string $hcNumber, ?int $examenId = null): ?array
    {
        $derivacionCodigoExpr = $this->selectExamenColumn('derivacion_codigo');
        $derivacionPedidoExpr = $this->selectExamenColumn('derivacion_pedido_id');
        $derivacionLateralidadExpr = $this->selectExamenColumn('derivacion_lateralidad');
        $derivacionVigenciaExpr = $this->selectExamenColumn('derivacion_fecha_vigencia_sel');
        $derivacionPrefacturaExpr = $this->selectExamenColumn('derivacion_prefactura');

        $sql = "SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.examen_codigo,
                ce.examen_nombre,
                ce.doctor,
                ce.solicitante,
                ce.estado,
                ce.prioridad,
                ce.lateralidad,
                ce.observaciones,
                {$derivacionCodigoExpr} AS derivacion_codigo,
                {$derivacionPedidoExpr} AS derivacion_pedido_id,
                {$derivacionLateralidadExpr} AS derivacion_lateralidad,
                {$derivacionVigenciaExpr} AS derivacion_fecha_vigencia_sel,
                {$derivacionPrefacturaExpr} AS derivacion_prefactura,
                ce.turno,
                ce.consulta_fecha,
                ce.created_at,
                ce.updated_at,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                detalles.crm_lead_id AS crm_lead_id,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                responsable.nombre AS crm_responsable_nombre,
                responsable.profile_photo AS crm_responsable_avatar,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM consulta_examenes ce
            INNER JOIN patient_data pd ON pd.hc_number = ce.hc_number
            LEFT JOIN examen_crm_detalles detalles ON detalles.examen_id = ce.id
            LEFT JOIN users responsable ON responsable.id = detalles.responsable_id
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
                SELECT source_ref_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN status IN ('pendiente','en_progreso','en_proceso') THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN status IN ('pendiente','en_progreso','en_proceso') THEN COALESCE(due_at, CONCAT(due_date, ' 23:59:59')) END) AS proximo_vencimiento
                FROM crm_tasks
                WHERE source_module = 'examenes'
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = ce.id
            WHERE ce.form_id = :form_id
              AND ce.hc_number = :hc_number";

        $params = [
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ];

        if ($examenId !== null && $examenId > 0) {
            $sql .= " AND ce.id = :id";
            $params[':id'] = $examenId;
        }

        $sql .= " ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC
                  LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row && isset($row['full_name'])) {
            $row['full_name'] = trim((string) $row['full_name']) !== ''
                ? trim((string) $row['full_name'])
                : null;
        }

        return $row;
    }

    public function obtenerExamenesPorFormHc(string $formId, string $hcNumber): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.examen_codigo,
                ce.examen_nombre,
                ce.estado,
                ce.doctor,
                ce.solicitante,
                ce.consulta_fecha,
                ce.created_at
             FROM consulta_examenes ce
             WHERE ce.form_id = :form_id
               AND ce.hc_number = :hc_number
             ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerDoctorProcedimientoProyectado(string $formId, string $hcNumber): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT pp.doctor
             FROM procedimiento_proyectado pp
             WHERE pp.form_id = :form_id
               AND pp.hc_number = :hc_number
               AND pp.doctor IS NOT NULL
               AND TRIM(pp.doctor) <> ''
             ORDER BY pp.id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        $value = $stmt->fetchColumn();
        if ($value === false) {
            $stmt = $this->db->prepare(
                "SELECT pp.doctor
                 FROM procedimiento_proyectado pp
                 WHERE pp.hc_number = :hc_number
                   AND pp.doctor IS NOT NULL
                   AND TRIM(pp.doctor) <> ''
                   AND pp.doctor NOT LIKE '%optometría%'
                 ORDER BY pp.form_id DESC, pp.id DESC
                 LIMIT 1"
            );
            $stmt->execute([
                ':hc_number' => $hcNumber,
            ]);
            $value = $stmt->fetchColumn();
        }

        if ($value === false) {
            return null;
        }

        $doctor = trim((string) $value);
        return $doctor !== '' ? $doctor : null;
    }

    public function obtenerConsultaPorFormHc(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                u.id AS doctor_user_id,
                u.first_name AS doctor_fname,
                u.middle_name AS doctor_mname,
                u.last_name AS doctor_lname,
                u.second_last_name AS doctor_lname2,
                u.cedula AS doctor_cedula,
                u.signature_path AS doctor_signature_path,
                u.firma AS doctor_firma,
                u.nombre AS doctor_nombre
             FROM consulta_data cd
             LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = cd.form_id AND pp.hc_number = cd.hc_number
             LEFT JOIN users u
                ON UPPER(TRIM(pp.doctor)) = u.nombre_norm
                OR UPPER(TRIM(pp.doctor)) = u.nombre_norm_rev
             WHERE cd.form_id = :form_id
               AND cd.hc_number = :hc_number
             LIMIT 1"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row ?: null;
    }

    public function obtenerDerivacionPorFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                rf.id AS derivacion_id,
                r.referral_code AS cod_derivacion,
                r.referral_code AS codigo_derivacion,
                f.iess_form_id AS form_id,
                f.hc_number,
                f.fecha_creacion,
                f.fecha_registro,
                COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                f.referido,
                f.diagnostico,
                f.sede,
                f.parentesco,
                f.archivo_derivacion_path
             FROM derivaciones_forms f
             LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
             LEFT JOIN derivaciones_referrals r ON r.id = rf.referral_id
             WHERE f.iess_form_id = ?
             ORDER BY COALESCE(rf.linked_at, f.updated_at) DESC, f.id DESC
             LIMIT 1"
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['id'] = $row['derivacion_id'] ?? null;
            return $row;
        }

        $stmtLegacy = $this->db->prepare("SELECT * FROM derivaciones_form_id WHERE form_id = ?");
        $stmtLegacy->execute([$formId]);
        return $stmtLegacy->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerDerivacionPreseleccion(int $examenId): ?array
    {
        $codigoExpr = $this->selectExamenColumn('derivacion_codigo');
        $pedidoExpr = $this->selectExamenColumn('derivacion_pedido_id');
        $lateralidadExpr = $this->selectExamenColumn('derivacion_lateralidad');
        $vigenciaExpr = $this->selectExamenColumn('derivacion_fecha_vigencia_sel');
        $prefacturaExpr = $this->selectExamenColumn('derivacion_prefactura');

        $stmt = $this->db->prepare(
            "SELECT
                {$codigoExpr} AS derivacion_codigo,
                {$pedidoExpr} AS derivacion_pedido_id,
                {$lateralidadExpr} AS derivacion_lateralidad,
                {$vigenciaExpr} AS derivacion_fecha_vigencia_sel,
                {$prefacturaExpr} AS derivacion_prefactura
             FROM consulta_examenes ce
             WHERE ce.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $examenId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $row;
    }

    public function obtenerDerivacionPreseleccionPorFormHc(string $formId, string $hcNumber): ?array
    {
        $codigoExpr = $this->selectExamenColumn('derivacion_codigo');
        $pedidoExpr = $this->selectExamenColumn('derivacion_pedido_id');
        $lateralidadExpr = $this->selectExamenColumn('derivacion_lateralidad');
        $vigenciaExpr = $this->selectExamenColumn('derivacion_fecha_vigencia_sel');
        $prefacturaExpr = $this->selectExamenColumn('derivacion_prefactura');

        $stmt = $this->db->prepare(
            "SELECT
                ce.id,
                {$codigoExpr} AS derivacion_codigo,
                {$pedidoExpr} AS derivacion_pedido_id,
                {$lateralidadExpr} AS derivacion_lateralidad,
                {$vigenciaExpr} AS derivacion_fecha_vigencia_sel,
                {$prefacturaExpr} AS derivacion_prefactura
             FROM consulta_examenes ce
             WHERE ce.form_id = :form_id
               AND ce.hc_number = :hc
             ORDER BY ce.id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc' => $hcNumber,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $row;
    }

    public function guardarDerivacionPreseleccion(int $examenId, array $data): bool
    {
        $base = $this->obtenerExamenBasico($examenId);
        if (!$base) {
            return false;
        }

        $set = [];
        $params = [
            ':form_id' => $base['form_id'],
            ':hc_number' => $base['hc_number'],
        ];

        if ($this->hasExamenColumn('derivacion_codigo')) {
            $set[] = 'derivacion_codigo = :codigo';
            $params[':codigo'] = $data['derivacion_codigo'] ?? null;
        }
        if ($this->hasExamenColumn('derivacion_pedido_id')) {
            $set[] = 'derivacion_pedido_id = :pedido_id';
            $params[':pedido_id'] = $data['derivacion_pedido_id'] ?? null;
        }
        if ($this->hasExamenColumn('derivacion_lateralidad')) {
            $set[] = 'derivacion_lateralidad = :lateralidad';
            $params[':lateralidad'] = $data['derivacion_lateralidad'] ?? null;
        }
        if ($this->hasExamenColumn('derivacion_fecha_vigencia_sel')) {
            $set[] = 'derivacion_fecha_vigencia_sel = :vigencia';
            $params[':vigencia'] = $data['derivacion_fecha_vigencia_sel'] ?? null;
        }
        if ($this->hasExamenColumn('derivacion_prefactura')) {
            $set[] = 'derivacion_prefactura = :prefactura';
            $params[':prefactura'] = $data['derivacion_prefactura'] ?? null;
        }

        if ($set === []) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE consulta_examenes SET ' . implode(', ', $set) . ' WHERE form_id = :form_id AND hc_number = :hc_number'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function actualizarExamenParcial(
        int $id,
        array $campos,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): array
    {
        $limpiar = static function ($valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
                if ($valor === '' || strtoupper($valor) === 'SELECCIONE') {
                    return null;
                }
                return $valor;
            }

            return $valor === '' ? null : $valor;
        };

        $normalizarFecha = static function ($valor): ?string {
            $valor = is_string($valor) ? trim($valor) : $valor;
            if (!$valor) {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', (string) $valor)) {
                return strlen((string) $valor) === 10 ? $valor . ' 00:00:00' : (string) $valor;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', (string) $valor)) {
                $format = strlen((string) $valor) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
                $date = \DateTime::createFromFormat($format, (string) $valor);
                if ($date instanceof \DateTime) {
                    return $date->format('Y-m-d H:i:s');
                }
            }

            $formats = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, (string) $valor);
                if ($date instanceof \DateTime) {
                    return $date->format('Y-m-d H:i:s');
                }
            }

            return null;
        };

        $permitidos = [
            'estado',
            'doctor',
            'solicitante',
            'consulta_fecha',
            'prioridad',
            'observaciones',
            'examen_nombre',
            'examen_codigo',
            'lateralidad',
            'turno',
        ];

        $set = [];
        $params = [':id' => $id];
        $estadoAnterior = null;

        if (array_key_exists('estado', $campos)) {
            $stmtEstado = $this->db->prepare('SELECT estado FROM consulta_examenes WHERE id = :id LIMIT 1');
            $stmtEstado->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtEstado->execute();
            $value = $stmtEstado->fetchColumn();
            $estadoAnterior = $value !== false ? trim((string) $value) : null;
        }

        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $campos)) {
                continue;
            }

            $valor = $campos[$campo];
            if ($campo === 'consulta_fecha') {
                $valor = $normalizarFecha($valor);
            } elseif ($campo === 'prioridad') {
                $valor = is_string($valor) ? strtoupper(trim($valor)) : $valor;
            } elseif ($campo === 'turno') {
                $valor = is_numeric($valor) ? (int) $valor : null;
            } else {
                $valor = $limpiar($valor);
            }

            $set[] = "{$campo} = :{$campo}";
            $params[":{$campo}"] = $valor;
        }

        if (empty($set)) {
            return ['success' => false, 'message' => 'No se enviaron campos para actualizar'];
        }

        $sql = 'UPDATE consulta_examenes SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->rowCount();

        $stmtDatos = $this->db->prepare(
            "SELECT
                ce.*,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name
             FROM consulta_examenes ce
             LEFT JOIN patient_data pd ON pd.hc_number = ce.hc_number
             WHERE ce.id = :id"
        );
        $stmtDatos->execute([':id' => $id]);
        $row = $stmtDatos->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row && array_key_exists('estado', $campos)) {
            $estadoNuevo = trim((string) ($row['estado'] ?? ''));
            $this->registrarCambioEstado(
                $id,
                $estadoAnterior,
                $estadoNuevo,
                $changedBy,
                $origen ?? 'api_estado',
                $observacion
            );
        }

        return [
            'success' => true,
            'message' => 'Examen actualizado correctamente',
            'rows_affected' => $rows,
            'data' => $row,
        ];
    }

    private function obtenerExamenBasico(int $examenId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, form_id, hc_number
             FROM consulta_examenes
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $examenId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? $examenId),
            'form_id' => (string) ($row['form_id'] ?? ''),
            'hc_number' => (string) ($row['hc_number'] ?? ''),
        ];
    }

    private function selectExamenColumn(string $column, string $alias = 'ce', string $fallback = 'NULL'): string
    {
        if ($this->hasExamenColumn($column)) {
            return sprintf('%s.`%s`', $alias, $column);
        }
        return $fallback;
    }

    private function hasExamenColumn(string $column): bool
    {
        $columns = $this->loadExamenColumns();
        return isset($columns[$column]);
    }

    /**
     * @return array<string, bool>
     */
    private function loadExamenColumns(): array
    {
        if ($this->examenColumns !== null) {
            return $this->examenColumns;
        }

        $stmt = $this->db->prepare(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'consulta_examenes'"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->examenColumns = [];
        foreach ($rows as $name) {
            if (!is_string($name)) {
                continue;
            }
            $this->examenColumns[$name] = true;
        }

        return $this->examenColumns;
    }

    public function actualizarEstado(
        int $id,
        string $estado,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): array
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
            if (strcasecmp($estado, 'Llamado') === 0) {
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

            $this->registrarCambioEstado(
                $id,
                isset($datosPrevios['estado']) ? (string) $datosPrevios['estado'] : null,
                isset($datos['estado']) ? (string) $datos['estado'] : $estado,
                $changedBy,
                $origen ?? 'kanban',
                $observacion
            );

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

    public function llamarTurno(
        ?int $id,
        ?int $turno,
        string $nuevoEstado = 'Llamado',
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): ?array
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

            $this->registrarCambioEstado(
                (int) $registro['id'],
                isset($registro['estado']) ? (string) $registro['estado'] : null,
                isset($detalles['estado']) ? (string) $detalles['estado'] : $nuevoEstado,
                $changedBy,
                $origen ?? 'turnero',
                $observacion
            );

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

    private function registrarCambioEstado(
        int $examenId,
        ?string $estadoAnterior,
        ?string $estadoNuevo,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): void {
        $nuevo = trim((string) ($estadoNuevo ?? ''));
        if ($nuevo === '') {
            return;
        }

        $anterior = $estadoAnterior !== null ? trim((string) $estadoAnterior) : null;
        if ($anterior !== null && strcasecmp($anterior, $nuevo) === 0) {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO examen_estado_log
                    (examen_id, estado_anterior, estado_nuevo, changed_by, origen, observacion)
                 VALUES
                    (:examen_id, :estado_anterior, :estado_nuevo, :changed_by, :origen, :observacion)'
            );
            $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
            $stmt->bindValue(
                ':estado_anterior',
                $anterior !== '' ? $anterior : null,
                $anterior !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
            );
            $stmt->bindValue(':estado_nuevo', $nuevo, PDO::PARAM_STR);
            $stmt->bindValue(':changed_by', $changedBy, $changedBy !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':origen', $origen !== null && $origen !== '' ? $origen : null, $origen !== null && $origen !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(
                ':observacion',
                $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null,
                $observacion !== null && trim($observacion) !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
            );
            $stmt->execute();
        } catch (\Throwable $exception) {
            error_log('No se pudo registrar examen_estado_log para examen #' . $examenId . ': ' . $exception->getMessage());
        }
    }

    public function listarUsuariosAsignables(): array
    {
        $service = new LeadConfigurationService($this->db);

        $usuarios = $service->getAssignableUsers();
        $filtrados = array_filter($usuarios, static function (array $usuario): bool {
            $especialidad = trim((string) ($usuario['especialidad'] ?? ''));
            return $especialidad === 'Cirujano Oftalmólogo';
        });

        return array_values($filtrados);
    }

    public function obtenerFuentesCrm(): array
    {
        $service = new LeadConfigurationService($this->db);

        return $service->getSources();
    }

    /**
     * @param array{
     *     fecha_inicio?: string,
     *     fecha_fin?: string,
     *     afiliacion?: string,
     *     tipo_examen?: string,
     *     paciente?: string,
     *     estado_agenda?: string
     * } $filters
     */
    public function fetchImagenesRealizadas(array $filters = []): array
    {
        $sql = "SELECT
                pp.id,
                pp.form_id,
                pp.hc_number,
                CASE
                    WHEN pp.fecha IS NOT NULL AND pp.hora IS NOT NULL
                        THEN CONCAT(pp.fecha, ' ', pp.hora)
                    WHEN pp.fecha IS NOT NULL
                        THEN pp.fecha
                    ELSE NULL
                END AS fecha_examen,
                COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), 'Sin afiliación') AS afiliacion,
                CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) AS full_name,
                COALESCE(NULLIF(TRIM(pd.hc_number), ''), pp.hc_number) AS cedula,
                NULLIF(TRIM(pp.procedimiento_proyectado), '') AS tipo_examen,
                NULL AS examen_nombre,
                NULL AS examen_codigo,
                NULL AS imagen_ruta,
                NULL AS imagen_nombre,
                pp.estado_agenda,
                ii.id AS informe_id,
                ii.firmado_por AS informe_firmado_por,
                ii.updated_at AS informe_actualizado
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
            LEFT JOIN imagenes_informes ii ON ii.form_id = pp.form_id
            WHERE pp.estado_agenda IS NOT NULL
              AND TRIM(pp.estado_agenda) <> ''
              AND LOWER(TRIM(pp.estado_agenda)) <> 'agendado'
              AND UPPER(TRIM(pp.procedimiento_proyectado)) LIKE 'IMAGENES%'";

        $params = [];

        if (!empty($filters['fecha_inicio']) && !empty($filters['fecha_fin'])) {
            $sql .= " AND pp.fecha BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }

        if (!empty($filters['afiliacion'])) {
            $sql .= " AND COALESCE(NULLIF(TRIM(pp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), '') LIKE :afiliacion";
            $params[':afiliacion'] = '%' . $filters['afiliacion'] . '%';
        }

        if (!empty($filters['tipo_examen'])) {
            $sql .= " AND TRIM(pp.procedimiento_proyectado) LIKE :tipo_examen";
            $params[':tipo_examen'] = '%' . $filters['tipo_examen'] . '%';
        }

        if (!empty($filters['paciente'])) {
            $sql .= " AND (pd.hc_number LIKE :paciente OR CONCAT_WS(' ', TRIM(pd.fname), TRIM(pd.mname), TRIM(pd.lname), TRIM(pd.lname2)) LIKE :paciente)";
            $params[':paciente'] = '%' . $filters['paciente'] . '%';
        }

        if (!empty($filters['estado_agenda'])) {
            $sql .= " AND TRIM(pp.estado_agenda) = :estado_agenda";
            $params[':estado_agenda'] = $filters['estado_agenda'];
        }

        $sql .= " ORDER BY pp.fecha DESC, pp.hora DESC, pp.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function actualizarProcedimientoProyectado(int $id, string $tipoExamen): bool
    {
        $stmt = $this->db->prepare('UPDATE procedimiento_proyectado SET procedimiento_proyectado = :tipo_examen WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_examen', $tipoExamen, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function eliminarProcedimientoProyectado(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM procedimiento_proyectado WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function obtenerProcedimientoProyectadoPorFormHc(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pp.id, pp.form_id, pp.hc_number, pp.procedimiento_proyectado, pp.fecha, pp.hora, pp.afiliacion, pp.estado_agenda
             FROM procedimiento_proyectado pp
             WHERE pp.form_id = :form_id AND pp.hc_number = :hc_number
             LIMIT 1'
        );
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_STR);
        $stmt->bindValue(':hc_number', $hcNumber, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function obtenerInformeImagen(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, form_id, hc_number, tipo_examen, plantilla, payload_json, firmado_por, created_by, updated_by, created_at, updated_at
             FROM imagenes_informes
             WHERE form_id = :form_id
             LIMIT 1'
        );
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function guardarInformeImagen(
        string $formId,
        ?string $hcNumber,
        string $tipoExamen,
        string $plantilla,
        string $payloadJson,
        ?int $userId,
        ?int $firmadoPor
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO imagenes_informes
                (form_id, hc_number, tipo_examen, plantilla, payload_json, firmado_por, created_by, updated_by)
             VALUES
                (:form_id, :hc_number, :tipo_examen, :plantilla, :payload_json, :firmado_por, :created_by, :updated_by)
             ON DUPLICATE KEY UPDATE
                hc_number = VALUES(hc_number),
                tipo_examen = VALUES(tipo_examen),
                plantilla = VALUES(plantilla),
                payload_json = VALUES(payload_json),
                firmado_por = VALUES(firmado_por),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_STR);
        $stmt->bindValue(':hc_number', $hcNumber, $hcNumber !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo_examen', $tipoExamen, PDO::PARAM_STR);
        $stmt->bindValue(':plantilla', $plantilla, PDO::PARAM_STR);
        $stmt->bindValue(':payload_json', $payloadJson, PDO::PARAM_STR);
        $stmt->bindValue(':firmado_por', $firmadoPor, $firmadoPor !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':updated_by', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        return $stmt->execute();
    }
}
