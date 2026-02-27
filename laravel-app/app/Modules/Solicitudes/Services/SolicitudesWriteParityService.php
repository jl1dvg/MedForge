<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use DateTime;
use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

class SolicitudesWriteParityService
{
    /**
     * @var array<int, array{slug:string,label:string,order:int,column:string,required:bool}>
     */
    private const DEFAULT_STAGES = [
        ['slug' => 'recibida', 'label' => 'Recibida', 'order' => 10, 'column' => 'recibida', 'required' => true],
        ['slug' => 'llamado', 'label' => 'Llamado', 'order' => 20, 'column' => 'llamado', 'required' => true],
        ['slug' => 'en-atencion', 'label' => 'En atencion', 'order' => 30, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'revision-codigos', 'label' => 'Cobertura', 'order' => 40, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'espera-documentos', 'label' => 'Documentacion', 'order' => 50, 'column' => 'espera-documentos', 'required' => true],
        ['slug' => 'apto-oftalmologo', 'label' => 'Apto oftalmologo', 'order' => 60, 'column' => 'apto-oftalmologo', 'required' => true],
        ['slug' => 'apto-anestesia', 'label' => 'Apto anestesia', 'order' => 70, 'column' => 'apto-anestesia', 'required' => true],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda', 'order' => 80, 'column' => 'listo-para-agenda', 'required' => true],
        ['slug' => 'programada', 'label' => 'Programada', 'order' => 90, 'column' => 'programada', 'required' => true],
    ];

    private const TURNERO_STATE_MAP = [
        'recibido' => 'Recibido',
        'recibida' => 'Recibido',
        'llamado' => 'Llamado',
        'en atencion' => 'En atención',
        'en atención' => 'En atención',
        'atendido' => 'Atendido',
    ];

    private const META_CIRUGIA_CONFIRMADA_KEYS = [
        'cirugia_confirmada_form_id',
        'cirugia_confirmada_hc_number',
        'cirugia_confirmada_fecha_inicio',
        'cirugia_confirmada_lateralidad',
        'cirugia_confirmada_membrete',
        'cirugia_confirmada_by',
        'cirugia_confirmada_at',
    ];

    /** @var array<string, array<int, string>> */
    private array $columnsCache = [];

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    private ?int $companyIdCache = null;

    public function __construct(
        private readonly PDO $db,
        private readonly SolicitudesReadParityService $readService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function apiEstadoGet(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM solicitud_procedimiento WHERE hc_number = :hc ORDER BY created_at DESC');
        $stmt->execute([':hc' => $hcNumber]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'success' => true,
            'hcNumber' => $hcNumber,
            'total' => count($rows),
            'solicitudes' => $rows,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function apiEstadoPost(array $payload): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($id <= 0 && isset($payload['solicitud_id'])) {
            $id = (int) $payload['solicitud_id'];
        }

        if ($id <= 0) {
            throw new RuntimeException('Parámetro id requerido para actualizar la solicitud');
        }

        $campos = [];
        foreach ([
            'estado',
            'doctor',
            'fecha',
            'prioridad',
            'observacion',
            'procedimiento',
            'producto',
            'ojo',
            'afiliacion',
            'duracion',
            'lente_id',
            'lente_nombre',
            'lente_poder',
            'lente_observacion',
            'incision',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $campos[$field] = $payload[$field];
            }
        }

        return $this->actualizarSolicitudParcial($id, $campos);
    }

    /**
     * @return array<string,mixed>
     */
    public function actualizarEstado(
        int $id,
        int $formId,
        string $estado,
        bool $completado,
        bool $force,
        ?int $userId,
        ?string $nota = null,
    ): array {
        if ($id <= 0 && $formId > 0) {
            $id = $this->findIdByFormId($formId) ?? 0;
        }

        $stageSlug = $this->normalizeKanbanSlug($estado);
        if ($id <= 0 || $stageSlug === '') {
            throw new RuntimeException('Datos incompletos');
        }

        $row = $this->fetchSolicitudById($id);
        if ($row === null) {
            throw new RuntimeException('Solicitud no encontrada');
        }

        if (!$force && $completado && !$this->canCompleteStage($id, $stageSlug, (string) ($row['estado'] ?? ''))) {
            throw new RuntimeException('Debe completar etapas previas antes de continuar.');
        }

        $this->upsertChecklistRow($id, $stageSlug, $completado, $userId, $nota);

        $legacyState = (string) ($row['estado'] ?? '');
        $hasChecklistTable = $this->tableExists('solicitud_checklist');
        if ($hasChecklistTable) {
            $checklistRows = $this->queryChecklistRows($id);
            [$checklist, $progress, $kanbanState] = $this->buildChecklistContext($legacyState, $checklistRows);
            $nextState = (string) ($kanbanState['slug'] ?? $stageSlug);
            $nextStateLabel = (string) ($kanbanState['label'] ?? $this->kanbanLabel($nextState));
        } else {
            [$checklist, $progress] = $this->buildChecklistContext($stageSlug, []);
            $nextState = $stageSlug;
            $nextStateLabel = $this->kanbanLabel($nextState);
        }

        $updatePayload = ['estado' => $nextState];
        if ($this->hasColumn('solicitud_procedimiento', 'updated_at')) {
            $updatePayload['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->updateRow('solicitud_procedimiento', $updatePayload, 'id = :id', [':id' => $id]);

        $fresh = $this->fetchSolicitudById($id);

        return [
            'kanban_estado' => $nextState,
            'kanban_estado_label' => $nextStateLabel,
            'estado' => $nextState,
            'estado_label' => $nextStateLabel,
            'turno' => isset($fresh['turno']) ? (int) $fresh['turno'] : null,
            'checklist' => $checklist,
            'checklist_progress' => $progress,
            'estado_anterior' => $legacyState,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function guardarDetallesCirugia(int $solicitudId, array $payload): array
    {
        if (isset($payload['updates']) && is_array($payload['updates'])) {
            $updates = $payload['updates'];
            $allowed = [
                'estado',
                'doctor',
                'fecha',
                'prioridad',
                'observacion',
                'procedimiento',
                'producto',
                'ojo',
                'afiliacion',
                'duracion',
                'lente_id',
                'lente_nombre',
                'lente_poder',
                'lente_observacion',
                'incision',
            ];

            $campos = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $updates)) {
                    $campos[$key] = $updates[$key];
                }
            }

            $resultado = $this->actualizarSolicitudParcial($solicitudId, $campos);
            if (($resultado['success'] ?? false) !== true) {
                throw new RuntimeException((string) ($resultado['message'] ?? 'No se pudieron guardar los cambios'));
            }

            return [
                'success' => true,
                'message' => (string) ($resultado['message'] ?? 'Cambios guardados'),
                'data' => $resultado['data'] ?? null,
            ];
        }

        $hcNumber = $payload['hcNumber'] ?? ($payload['hc_number'] ?? null);
        if ($hcNumber && isset($payload['form_id'], $payload['solicitudes']) && is_array($payload['solicitudes'])) {
            $resultado = $this->guardarSolicitudesBatchUpsert($payload);
            if (($resultado['success'] ?? false) !== true) {
                throw new RuntimeException((string) ($resultado['message'] ?? 'No se pudieron guardar las solicitudes'));
            }

            return $resultado;
        }

        throw new RuntimeException('Datos no válidos o incompletos');
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function guardarDerivacionPreseleccion(?int $solicitudId, array $payload): bool
    {
        $codigo = trim((string) ($payload['codigo_derivacion'] ?? ''));
        $pedidoId = trim((string) ($payload['pedido_id_mas_antiguo'] ?? ''));
        $lateralidad = trim((string) ($payload['lateralidad'] ?? ''));
        $vigencia = trim((string) ($payload['fecha_vigencia'] ?? ''));
        $prefactura = trim((string) ($payload['prefactura'] ?? ''));

        if (($solicitudId ?? 0) <= 0) {
            $solicitudId = isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : 0;
        }

        if ($solicitudId <= 0) {
            $formId = trim((string) ($payload['form_id'] ?? ''));
            $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
            if ($formId !== '' && $hcNumber !== '') {
                $stmt = $this->db->prepare('SELECT id FROM solicitud_procedimiento WHERE form_id = :form_id AND hc_number = :hc ORDER BY id DESC LIMIT 1');
                $stmt->execute([':form_id' => $formId, ':hc' => $hcNumber]);
                $resolved = $stmt->fetchColumn();
                $solicitudId = $resolved !== false ? (int) $resolved : 0;
            }
        }

        if ($solicitudId <= 0 || $codigo === '' || $pedidoId === '') {
            throw new RuntimeException('Datos incompletos para guardar la derivación seleccionada.');
        }

        $set = [];
        $params = [':id' => $solicitudId];

        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_codigo')) {
            $set[] = 'derivacion_codigo = :codigo';
            $params[':codigo'] = $codigo;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_pedido_id')) {
            $set[] = 'derivacion_pedido_id = :pedido';
            $params[':pedido'] = $pedidoId;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_lateralidad')) {
            $set[] = 'derivacion_lateralidad = :lateralidad';
            $params[':lateralidad'] = $lateralidad !== '' ? $lateralidad : null;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_fecha_vigencia_sel')) {
            $set[] = 'derivacion_fecha_vigencia_sel = :vigencia';
            $params[':vigencia'] = $vigencia !== '' ? $vigencia : null;
        }
        if ($this->hasColumn('solicitud_procedimiento', 'derivacion_prefactura')) {
            $set[] = 'derivacion_prefactura = :prefactura';
            $params[':prefactura'] = $prefactura !== '' ? $prefactura : null;
        }

        if ($set === []) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE solicitud_procedimiento SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $existsStmt = $this->db->prepare('SELECT 1 FROM solicitud_procedimiento WHERE id = :id LIMIT 1');
        $existsStmt->execute([':id' => $solicitudId]);

        return $existsStmt->fetchColumn() !== false;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function turneroLlamar(?int $id, ?int $turno, string $nuevoEstado): ?array
    {
        $estadoNormalizado = $this->normalizeTurneroEstado($nuevoEstado);
        if ($estadoNormalizado === null) {
            throw new RuntimeException('Estado no permitido para el turnero');
        }

        $this->db->beginTransaction();

        try {
            $registro = null;
            if (($turno ?? 0) > 0) {
                $stmt = $this->db->prepare('SELECT id, turno, estado FROM solicitud_procedimiento WHERE turno = :turno FOR UPDATE');
                $stmt->execute([':turno' => $turno]);
                $registro = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $stmt = $this->db->prepare('SELECT id, turno, estado FROM solicitud_procedimiento WHERE id = :id FOR UPDATE');
                $stmt->execute([':id' => $id]);
                $registro = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($registro === null && ($id ?? 0) > 0) {
                    $fallback = $this->db->prepare('SELECT id, turno, estado FROM solicitud_procedimiento WHERE form_id = :form_id ORDER BY id DESC LIMIT 1 FOR UPDATE');
                    $fallback->execute([':form_id' => $id]);
                    $registro = $fallback->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }

            if ($registro === null) {
                $this->db->rollBack();
                return null;
            }

            $estadoActual = $this->normalizeTurneroEstado((string) ($registro['estado'] ?? ''));
            if ($estadoActual === null) {
                $this->db->rollBack();
                return null;
            }

            if (empty($registro['turno'])) {
                $registro['turno'] = $this->asignarTurnoSiNecesario((int) $registro['id']);
            }

            $update = $this->db->prepare('UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id');
            $update->execute([
                ':estado' => $estadoNormalizado,
                ':id' => (int) $registro['id'],
            ]);

            $detailStmt = $this->db->prepare('SELECT
                    sp.id,
                    sp.turno,
                    sp.estado,
                    sp.hc_number,
                    sp.form_id,
                    sp.prioridad,
                    sp.created_at,
                    TRIM(CONCAT_WS(" ", NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""), NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), ""))) AS full_name
                FROM solicitud_procedimiento sp
                INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
                WHERE sp.id = :id');
            $detailStmt->execute([':id' => (int) $registro['id']]);
            $detalles = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $this->db->commit();
            return $detalles;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmGuardarDetalles(int $solicitudId, array $payload, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        if (!$this->tableExists('solicitud_crm_detalles')) {
            throw new RuntimeException('Tabla solicitud_crm_detalles no disponible');
        }

        $responsableId = $this->nullableInt($payload['responsable_id'] ?? null);
        $pipelineStage = $this->nullableString($payload['pipeline_stage'] ?? null);
        $fuente = $this->nullableString($payload['fuente'] ?? null);
        $contactoEmail = $this->nullableString($payload['contacto_email'] ?? null);
        $contactoTelefono = $this->nullableString($payload['contacto_telefono'] ?? null);
        $followers = $this->normalizeFollowers($payload['seguidores'] ?? []);
        $followersJson = $followers !== [] ? json_encode($followers, JSON_UNESCAPED_UNICODE) : null;

        $existing = $this->fetchCrmDetalleRow($solicitudId);
        $columns = $this->tableColumns('solicitud_crm_detalles');
        $companyId = $this->resolveCompanyId();

        $data = [];
        $data['solicitud_id'] = $solicitudId;
        if (in_array('crm_lead_id', $columns, true)) {
            $data['crm_lead_id'] = $this->nullableInt($payload['crm_lead_id'] ?? null);
        }
        if (in_array('crm_project_id', $columns, true)) {
            $data['crm_project_id'] = $existing['crm_project_id'] ?? null;
        }
        if (in_array('responsable_id', $columns, true)) {
            $data['responsable_id'] = $responsableId;
        }
        if (in_array('pipeline_stage', $columns, true)) {
            $data['pipeline_stage'] = $pipelineStage;
        }
        if (in_array('fuente', $columns, true)) {
            $data['fuente'] = $fuente;
        }
        if (in_array('contacto_email', $columns, true)) {
            $data['contacto_email'] = $contactoEmail;
        }
        if (in_array('contacto_telefono', $columns, true)) {
            $data['contacto_telefono'] = $contactoTelefono;
        }
        if (in_array('followers', $columns, true)) {
            $data['followers'] = $followersJson;
        }
        if (in_array('company_id', $columns, true)) {
            $data['company_id'] = $companyId;
        }

        if ($existing === null) {
            if (in_array('created_at', $columns, true)) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $this->insertRow('solicitud_crm_detalles', $data);
        } else {
            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            unset($data['solicitud_id']);
            $this->updateRow('solicitud_crm_detalles', $data, 'solicitud_id = :solicitud_id', [':solicitud_id' => $solicitudId]);
        }

        if (isset($payload['custom_fields']) && is_array($payload['custom_fields'])) {
            $this->guardarCrmMeta($solicitudId, $payload['custom_fields']);
        }

        return $this->readService->crmResumen($solicitudId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmRegistrarBloqueo(int $solicitudId, array $payload, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $columns = $this->tableColumns('crm_calendar_blocks');
        if ($columns === []) {
            throw new RuntimeException('Tabla crm_calendar_blocks no disponible');
        }

        $base = $this->fetchSolicitudBloqueoBase($solicitudId);
        if ($base === null) {
            throw new RuntimeException('No se encontró la solicitud para bloquear agenda');
        }

        $inicio = $this->parseFlexibleDateTime($payload['fecha_inicio'] ?? ($base['fecha_programada'] ?? null));
        if (!$inicio instanceof DateTimeImmutable) {
            throw new RuntimeException('La fecha/hora de inicio es obligatoria');
        }

        $fin = $this->parseFlexibleDateTime($payload['fecha_fin'] ?? null);
        if (!$fin instanceof DateTimeImmutable) {
            $duracionMinutos = max(15, (int) ($payload['duracion_minutos'] ?? 60));
            $fin = $inicio->add(new DateInterval(sprintf('PT%dM', $duracionMinutos)));
        }

        if ($fin <= $inicio) {
            throw new RuntimeException('La hora de fin debe ser posterior al inicio');
        }

        $doctor = $this->nullableString($payload['doctor'] ?? ($base['doctor'] ?? null));
        $sala = $this->nullableString($payload['sala'] ?? ($payload['quirofano'] ?? ($base['sala'] ?? null)));
        $motivo = $this->nullableString($payload['motivo'] ?? null);
        $now = date('Y-m-d H:i:s');

        $insert = ['solicitud_id' => $solicitudId];
        if (in_array('doctor', $columns, true)) {
            $insert['doctor'] = $doctor;
        }
        if (in_array('sala', $columns, true)) {
            $insert['sala'] = $sala;
        }
        if (in_array('fecha_inicio', $columns, true)) {
            $insert['fecha_inicio'] = $inicio->format('Y-m-d H:i:s');
        }
        if (in_array('fecha_fin', $columns, true)) {
            $insert['fecha_fin'] = $fin->format('Y-m-d H:i:s');
        }
        if (in_array('motivo', $columns, true)) {
            $insert['motivo'] = $motivo;
        }
        if (in_array('created_by', $columns, true)) {
            $insert['created_by'] = $userId;
        }
        if (in_array('created_at', $columns, true)) {
            $insert['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $insert['updated_at'] = $now;
        }

        $this->insertRow('crm_calendar_blocks', $insert);
        $bloqueoId = (int) $this->db->lastInsertId();

        $resumen = $this->readService->crmResumen($solicitudId);
        $resumen['ultimo_bloqueo'] = [
            'id' => $bloqueoId > 0 ? $bloqueoId : null,
            'solicitud_id' => $solicitudId,
            'doctor' => $doctor,
            'sala' => $sala,
            'fecha_inicio' => $inicio->format(DateTimeImmutable::ATOM),
            'fecha_fin' => $fin->format(DateTimeImmutable::ATOM),
            'motivo' => $motivo,
            'created_by' => $userId,
        ];

        return $resumen;
    }

    /**
     * @return array<string,mixed>
     */
    public function crmSubirAdjunto(
        int $solicitudId,
        string $nombreOriginal,
        string $rutaRelativa,
        ?string $mimeType,
        ?int $tamanoBytes,
        ?int $usuarioId,
        ?string $descripcion = null
    ): array {
        $this->assertSolicitudExists($solicitudId);

        $columns = $this->tableColumns('solicitud_crm_adjuntos');
        if ($columns === []) {
            throw new RuntimeException('Tabla solicitud_crm_adjuntos no disponible');
        }

        $now = date('Y-m-d H:i:s');
        $insert = ['solicitud_id' => $solicitudId];
        if (in_array('nombre_original', $columns, true)) {
            $insert['nombre_original'] = $nombreOriginal;
        }
        if (in_array('ruta_relativa', $columns, true)) {
            $insert['ruta_relativa'] = $rutaRelativa;
        }
        if (in_array('mime_type', $columns, true)) {
            $insert['mime_type'] = $mimeType;
        }
        if (in_array('tamano_bytes', $columns, true)) {
            $insert['tamano_bytes'] = $tamanoBytes;
        }
        if (in_array('descripcion', $columns, true)) {
            $insert['descripcion'] = $this->nullableString($descripcion);
        }
        if (in_array('subido_por', $columns, true)) {
            $insert['subido_por'] = $usuarioId;
        }
        if (in_array('created_at', $columns, true)) {
            $insert['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $insert['updated_at'] = $now;
        }

        $this->insertRow('solicitud_crm_adjuntos', $insert);

        return $this->readService->crmResumen($solicitudId);
    }

    /**
     * @return array<string,mixed>
     */
    public function crmAgregarNota(int $solicitudId, string $nota, ?int $autorId): array
    {
        $this->assertSolicitudExists($solicitudId);
        $nota = trim(strip_tags($nota));
        if ($nota === '') {
            throw new RuntimeException('La nota no puede estar vacía');
        }

        $columns = $this->tableColumns('solicitud_crm_notas');
        if ($columns === []) {
            throw new RuntimeException('Tabla solicitud_crm_notas no disponible');
        }

        $payload = [
            'solicitud_id' => $solicitudId,
            'nota' => $nota,
        ];
        if (in_array('autor_id', $columns, true)) {
            $payload['autor_id'] = $autorId;
        }
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = date('Y-m-d H:i:s');
        }

        $this->insertRow('solicitud_crm_notas', $payload);

        return $this->readService->crmResumen($solicitudId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmGuardarTarea(int $solicitudId, array $payload, ?int $autorId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $title = trim((string) ($payload['titulo'] ?? $payload['title'] ?? ''));
        if ($title === '') {
            $title = 'Tarea solicitud #' . $solicitudId;
        }

        $status = strtolower(trim((string) ($payload['estado'] ?? $payload['status'] ?? 'pendiente')));
        if (!in_array($status, ['pendiente', 'en_progreso', 'en_proceso', 'completada', 'cancelada'], true)) {
            $status = 'pendiente';
        }

        $columns = $this->tableColumns('crm_tasks');
        if ($columns === []) {
            throw new RuntimeException('Tabla crm_tasks no disponible');
        }

        $now = date('Y-m-d H:i:s');
        $task = [];
        if (in_array('company_id', $columns, true)) {
            $task['company_id'] = $this->resolveCompanyId();
        }
        if (in_array('source_module', $columns, true)) {
            $task['source_module'] = 'solicitudes';
        }
        if (in_array('source_ref_id', $columns, true)) {
            $task['source_ref_id'] = (string) $solicitudId;
        }
        if (in_array('title', $columns, true)) {
            $task['title'] = $title;
        }
        if (in_array('description', $columns, true)) {
            $task['description'] = $this->nullableString($payload['descripcion'] ?? $payload['description'] ?? null);
        }
        if (in_array('status', $columns, true)) {
            $task['status'] = $status;
        }
        if (in_array('assigned_to', $columns, true)) {
            $task['assigned_to'] = $this->nullableInt($payload['assigned_to'] ?? $payload['asignado_a'] ?? null);
        }
        if (in_array('created_by', $columns, true)) {
            $task['created_by'] = $autorId;
        }
        if (in_array('due_date', $columns, true)) {
            $task['due_date'] = $this->normalizeDate($payload['due_date'] ?? $payload['fecha_vencimiento'] ?? null);
        }
        if (in_array('due_at', $columns, true)) {
            $task['due_at'] = $this->normalizeDateTime($payload['due_at'] ?? $payload['fecha_hora_vencimiento'] ?? null);
        }
        if (in_array('remind_at', $columns, true)) {
            $task['remind_at'] = $this->normalizeDateTime($payload['remind_at'] ?? null);
        }
        if (in_array('checklist_slug', $columns, true)) {
            $task['checklist_slug'] = $this->nullableString($payload['checklist_slug'] ?? $payload['etapa_slug'] ?? null);
        }
        if (in_array('task_key', $columns, true)) {
            $task['task_key'] = $this->nullableString($payload['task_key'] ?? null);
        }
        if (in_array('priority', $columns, true)) {
            $task['priority'] = $this->normalizeTaskPriority($payload['priority'] ?? $payload['prioridad'] ?? null);
        }
        if (in_array('completed_at', $columns, true)) {
            $task['completed_at'] = $status === 'completada' ? $now : null;
        }
        if (in_array('created_at', $columns, true)) {
            $task['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $task['updated_at'] = $now;
        }

        $this->insertRow('crm_tasks', $task);

        return $this->readService->crmResumen($solicitudId);
    }

    /**
     * @return array<string,mixed>
     */
    public function crmActualizarTareaEstado(int $solicitudId, int $tareaId, string $estado): array
    {
        $this->assertSolicitudExists($solicitudId);

        $estado = strtolower(trim($estado));
        if (!in_array($estado, ['pendiente', 'en_progreso', 'en_proceso', 'completada', 'cancelada'], true)) {
            throw new RuntimeException('Estado de tarea inválido');
        }

        $columns = $this->tableColumns('crm_tasks');
        if ($columns === []) {
            throw new RuntimeException('Tabla crm_tasks no disponible');
        }

        $payload = [];
        if (in_array('status', $columns, true)) {
            $payload['status'] = $estado;
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('completed_at', $columns, true)) {
            $payload['completed_at'] = $estado === 'completada' ? date('Y-m-d H:i:s') : null;
        }

        $where = 'id = :id';
        $bindings = [':id' => $tareaId];
        if (in_array('source_module', $columns, true)) {
            $where .= ' AND source_module = :source_module';
            $bindings[':source_module'] = 'solicitudes';
        }
        if (in_array('source_ref_id', $columns, true)) {
            $where .= ' AND source_ref_id = :source_ref_id';
            $bindings[':source_ref_id'] = (string) $solicitudId;
        }
        if (in_array('company_id', $columns, true)) {
            $where .= ' AND company_id = :company_id';
            $bindings[':company_id'] = $this->resolveCompanyId();
        }

        $updated = $this->updateRow('crm_tasks', $payload, $where, $bindings);
        if ($updated <= 0) {
            throw new RuntimeException('No se pudo actualizar la tarea');
        }

        return $this->readService->crmResumen($solicitudId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmBootstrap(int $solicitudId, array $payload, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $legacyState = $this->legacyStateBySolicitud($solicitudId);
        $stageIndex = $this->stageIndex($this->normalizeKanbanSlug($legacyState));

        foreach (self::DEFAULT_STAGES as $index => $stage) {
            $slug = $stage['slug'];
            $exists = $this->checklistRowExists($solicitudId, $slug);
            if ($exists) {
                continue;
            }

            $completed = $stageIndex !== null && $index <= $stageIndex;
            $this->upsertChecklistRow($solicitudId, $slug, $completed, $userId, null);
        }

        $result = $this->crmChecklistState($solicitudId);

        if (($payload['force_estado_sync'] ?? false) === true) {
            $this->syncSolicitudEstadoFromChecklist($solicitudId);
            $result = $this->crmChecklistState($solicitudId);
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function crmChecklistState(int $solicitudId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $legacyState = $this->legacyStateBySolicitud($solicitudId);
        $rows = $this->queryChecklistRows($solicitudId);
        [$checklist, $progress] = $this->buildChecklistContext($legacyState, $rows);

        $resumen = $this->readService->crmResumen($solicitudId);
        $detalle = is_array($resumen['detalle'] ?? null) ? (array) $resumen['detalle'] : [];

        return [
            'checklist' => $checklist,
            'checklist_progress' => $progress,
            'tasks' => $resumen['tareas'] ?? [],
            'lead_id' => $detalle['crm_lead_id'] ?? null,
            'project_id' => $detalle['crm_project_id'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function crmActualizarChecklist(int $solicitudId, string $etapa, bool $completado, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $slug = $this->normalizeKanbanSlug($etapa);
        if ($slug === '') {
            throw new RuntimeException('Etapa requerida');
        }

        if (!$this->tableExists('solicitud_checklist')) {
            if ($completado) {
                $payload = ['estado' => $slug];
                if ($this->hasColumn('solicitud_procedimiento', 'updated_at')) {
                    $payload['updated_at'] = date('Y-m-d H:i:s');
                }
                $this->updateRow('solicitud_procedimiento', $payload, 'id = :id', [':id' => $solicitudId]);
            }

            return $this->crmChecklistState($solicitudId);
        }

        $this->upsertChecklistRow($solicitudId, $slug, $completado, $userId, null);
        $this->syncSolicitudEstadoFromChecklist($solicitudId);

        return $this->crmChecklistState($solicitudId);
    }

    /**
     * @return array<string,mixed>
     */
    public function confirmarConciliacionCirugia(int $solicitudId, string $protocoloFormId, ?int $userId): array
    {
        if ($solicitudId <= 0) {
            throw new RuntimeException('Solicitud inválida', 422);
        }

        $protocoloFormId = trim($protocoloFormId);
        if ($protocoloFormId === '') {
            throw new RuntimeException('Debes indicar el protocolo a asociar.', 422);
        }

        $solicitud = $this->readService->obtenerSolicitudConciliacionPorId($solicitudId);
        if ($solicitud === null) {
            throw new RuntimeException('No se encontró la solicitud.', 404);
        }

        $protocolo = $this->readService->obtenerProtocoloConciliacionPorFormId($protocoloFormId);
        if ($protocolo === null) {
            throw new RuntimeException('No se encontró el protocolo seleccionado.', 404);
        }

        $hcSolicitud = trim((string) ($solicitud['hc_number'] ?? ''));
        $hcProtocolo = trim((string) ($protocolo['hc_number'] ?? ''));
        if ($hcSolicitud === '' || $hcProtocolo === '' || $hcSolicitud !== $hcProtocolo) {
            throw new RuntimeException('El protocolo no pertenece al mismo paciente.', 422);
        }

        $fechaSolicitudTs = strtotime((string) ($solicitud['fecha_solicitud'] ?? '')) ?: 0;
        $fechaProtocoloTs = strtotime((string) ($protocolo['fecha_inicio'] ?? '')) ?: 0;
        if ($fechaSolicitudTs > 0 && $fechaProtocoloTs > 0 && $fechaProtocoloTs < $fechaSolicitudTs) {
            throw new RuntimeException('El protocolo debe ser posterior a la solicitud.', 422);
        }

        $lateralidadSolicitud = trim((string) ($solicitud['ojo_resuelto'] ?? ($solicitud['ojo'] ?? '')));
        $lateralidadProtocolo = trim((string) ($protocolo['lateralidad'] ?? ''));
        if (!$this->readService->lateralidadesCompatibles($lateralidadSolicitud, $lateralidadProtocolo)) {
            throw new RuntimeException('La lateralidad del protocolo no es compatible con la solicitud.', 422);
        }

        $this->guardarConfirmacionCirugiaMeta($solicitudId, $protocolo, $userId);

        $nota = sprintf(
            'Confirmación manual de cirugía con protocolo %s (%s).',
            (string) ($protocolo['form_id'] ?? ''),
            (string) ($protocolo['lateralidad'] ?? 'sin lateralidad')
        );

        $this->completarChecklistConciliacion($solicitudId, $userId, $nota);
        $tareasActualizadas = $this->completarTodasLasTareasConciliacion($solicitudId);

        $updatePayload = ['estado' => 'completado'];
        if ($this->hasColumn('solicitud_procedimiento', 'updated_at')) {
            $updatePayload['updated_at'] = date('Y-m-d H:i:s');
        }
        $this->updateRow('solicitud_procedimiento', $updatePayload, 'id = :id', [':id' => $solicitudId]);

        $checklistState = $this->crmChecklistState($solicitudId);

        return [
            'message' => 'Solicitud confirmada y marcada como completada.',
            'estado' => 'completado',
            'checklist' => $checklistState['checklist'] ?? [],
            'checklist_progress' => $checklistState['checklist_progress'] ?? [],
            'tareas_actualizadas' => $tareasActualizadas,
            'protocolo_confirmado' => [
                'form_id' => trim((string) ($protocolo['form_id'] ?? '')),
                'hc_number' => trim((string) ($protocolo['hc_number'] ?? '')),
                'fecha_inicio' => $protocolo['fecha_inicio'] ?? null,
                'lateralidad' => trim((string) ($protocolo['lateralidad'] ?? '')),
                'membrete' => trim((string) ($protocolo['membrete'] ?? '')),
                'status' => isset($protocolo['status']) ? (int) $protocolo['status'] : null,
                'confirmado_at' => date('Y-m-d H:i:s'),
                'confirmado_by_id' => $userId ?: null,
            ],
        ];
    }

    private function completarChecklistConciliacion(int $solicitudId, ?int $userId, ?string $note): void
    {
        if (!$this->tableExists('solicitud_checklist')) {
            return;
        }

        foreach (self::DEFAULT_STAGES as $stage) {
            $slug = (string) ($stage['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $this->upsertChecklistRow($solicitudId, $slug, true, $userId, $note);
        }
    }

    private function completarTodasLasTareasConciliacion(int $solicitudId): int
    {
        $totalActualizadas = 0;
        $now = date('Y-m-d H:i:s');

        if ($this->tableExists('crm_tasks')) {
            $columns = $this->tableColumns('crm_tasks');
            if (
                in_array('status', $columns, true)
                && in_array('source_module', $columns, true)
                && in_array('source_ref_id', $columns, true)
            ) {
                $payload = [
                    'status' => 'completada',
                ];
                if (in_array('completed_at', $columns, true)) {
                    $payload['completed_at'] = $now;
                }
                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = $now;
                }

                $where = 'source_module = :source_module AND source_ref_id = :source_ref_id';
                $bindings = [
                    ':source_module' => 'solicitudes',
                    ':source_ref_id' => (string) $solicitudId,
                    ':estado_actual' => 'completada',
                ];

                if (in_array('company_id', $columns, true)) {
                    $where .= ' AND company_id = :company_id';
                    $bindings[':company_id'] = $this->resolveCompanyId();
                }

                $where .= ' AND (status IS NULL OR status <> :estado_actual)';
                $totalActualizadas += $this->updateRow('crm_tasks', $payload, $where, $bindings);
            }
        }

        if ($this->tableExists('solicitud_crm_tareas')) {
            $columns = $this->tableColumns('solicitud_crm_tareas');
            if (in_array('estado', $columns, true) && in_array('solicitud_id', $columns, true)) {
                $payload = [
                    'estado' => 'completada',
                ];
                if (in_array('completed_at', $columns, true)) {
                    $payload['completed_at'] = $now;
                }
                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = $now;
                }

                $where = 'solicitud_id = :solicitud_id AND (estado IS NULL OR estado <> :estado_actual)';
                $bindings = [
                    ':solicitud_id' => $solicitudId,
                    ':estado_actual' => 'completada',
                ];

                $totalActualizadas += $this->updateRow('solicitud_crm_tareas', $payload, $where, $bindings);
            }
        }

        return $totalActualizadas;
    }

    /**
     * @param array<string,mixed> $campos
     * @return array<string,mixed>
     */
    private function actualizarSolicitudParcial(int $id, array $campos): array
    {
        $limpiar = static function (mixed $valor): mixed {
            if (is_string($valor)) {
                $valor = trim($valor);
                if ($valor === '' || strtoupper($valor) === 'SELECCIONE') {
                    return null;
                }

                return $valor;
            }

            return $valor === '' ? null : $valor;
        };

        $normFecha = static function (mixed $valor): ?string {
            $valor = is_string($valor) ? trim($valor) : $valor;
            if (!$valor) {
                return null;
            }

            if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $valor)) {
                return $valor;
            }

            if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $valor)) {
                $format = strlen($valor) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
                $date = DateTime::createFromFormat($format, $valor);
                if ($date instanceof DateTime) {
                    return $date->format('Y-m-d H:i:s');
                }
            }

            $formats = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, (string) $valor);
                if ($date instanceof DateTime) {
                    return $date->format(strlen($format) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }

            return null;
        };

        $permitidos = [
            'estado',
            'doctor',
            'fecha',
            'prioridad',
            'observacion',
            'procedimiento',
            'producto',
            'ojo',
            'afiliacion',
            'duracion',
            'lente_id',
            'lente_nombre',
            'lente_poder',
            'lente_observacion',
            'incision',
        ];

        $columns = $this->tableColumns('solicitud_procedimiento');
        $set = [];
        $params = [':id' => $id];

        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $campos)) {
                continue;
            }
            if (!in_array($campo, $columns, true)) {
                continue;
            }

            $valor = $campos[$campo];
            if ($campo === 'fecha') {
                $valor = $normFecha($valor);
            } elseif ($campo === 'prioridad') {
                $valor = is_string($valor) ? strtoupper(trim($valor)) : $valor;
            } elseif ($campo === 'ojo' && is_array($valor)) {
                $valor = implode(',', array_filter(array_map(static fn(mixed $item): mixed => $limpiar($item), $valor)));
            } else {
                $valor = $limpiar($valor);
            }

            $set[] = "`{$campo}` = :{$campo}";
            $params[":{$campo}"] = $valor;
        }

        if ($set === []) {
            return ['success' => false, 'message' => 'No se enviaron campos para actualizar'];
        }

        $sql = 'UPDATE solicitud_procedimiento SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $stmtData = $this->db->prepare('SELECT sp.*, COALESCE(cd.fecha, sp.fecha) AS fecha_programada
            FROM solicitud_procedimiento sp
            LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            WHERE sp.id = :id');
        $stmtData->execute([':id' => $id]);
        $row = $stmtData->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'success' => true,
            'message' => 'Solicitud actualizada correctamente',
            'rows_affected' => $stmt->rowCount(),
            'data' => $row,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function guardarSolicitudesBatchUpsert(array $data): array
    {
        $hcNumber = (string) ($data['hcNumber'] ?? ($data['hc_number'] ?? ''));
        $formId = (string) ($data['form_id'] ?? '');
        $solicitudes = $data['solicitudes'] ?? null;

        if ($hcNumber === '' || $formId === '' || !is_array($solicitudes)) {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        $clean = static function (mixed $value): mixed {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '' || in_array(mb_strtoupper($value), ['SELECCIONE', 'NINGUNO'], true)) {
                    return null;
                }

                return $value;
            }

            return $value === '' ? null : $value;
        };

        $normPrioridad = static function (mixed $value): string {
            $value = is_string($value) ? mb_strtoupper(trim($value)) : $value;

            return ($value === 'SI' || $value === 1 || $value === '1' || $value === true) ? 'SI' : 'NO';
        };

        $normFecha = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : $value;
            if (!$value) {
                return null;
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value)) {
                return $value;
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
                $format = strlen($value) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
                $dt = DateTime::createFromFormat($format, $value);
                if ($dt instanceof DateTime) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }

            $formats = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, (string) $value);
                if ($dt instanceof DateTime) {
                    return $dt->format(strlen($format) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }

            return null;
        };

        $missing = [];
        foreach ($solicitudes as $idx => $solicitud) {
            if (!is_array($solicitud)) {
                continue;
            }
            $procedimiento = $clean($solicitud['procedimiento'] ?? null);
            if ($procedimiento === null) {
                $missing[] = $solicitud['secuencia'] ?? ($idx + 1);
            }
        }

        if ($missing !== []) {
            return [
                'success' => false,
                'message' => 'El procedimiento es obligatorio en todas las solicitudes (faltante en: ' . implode(', ', $missing) . ')',
            ];
        }

        $sql = 'INSERT INTO solicitud_procedimiento
            (hc_number, form_id, secuencia, tipo, afiliacion, procedimiento, doctor, fecha, duracion, ojo, prioridad, producto, observacion, sesiones, lente_id, lente_nombre, lente_poder, lente_observacion, incision)
            VALUES (:hc, :form_id, :secuencia, :tipo, :afiliacion, :procedimiento, :doctor, :fecha, :duracion, :ojo, :prioridad, :producto, :observacion, :sesiones, :lente_id, :lente_nombre, :lente_poder, :lente_observacion, :incision)
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
                observacion = VALUES(observacion),
                sesiones = VALUES(sesiones),
                lente_id = VALUES(lente_id),
                lente_nombre = VALUES(lente_nombre),
                lente_poder = VALUES(lente_poder),
                lente_observacion = VALUES(lente_observacion),
                incision = VALUES(incision)';

        $stmt = $this->db->prepare($sql);

        foreach ($solicitudes as $solicitud) {
            if (!is_array($solicitud)) {
                continue;
            }

            $secuencia = $solicitud['secuencia'] ?? null;
            $tipo = $clean($solicitud['tipo'] ?? null);
            $afiliacion = $clean($solicitud['afiliacion'] ?? null);
            $procedimiento = $clean($solicitud['procedimiento'] ?? null);
            $doctor = $clean($solicitud['doctor'] ?? null);
            $fecha = $normFecha($solicitud['fecha'] ?? null);
            $duracion = $clean($solicitud['duracion'] ?? null);
            $prioridad = $normPrioridad($solicitud['prioridad'] ?? 'NO');
            $producto = $clean($solicitud['producto'] ?? null);
            $observacion = $clean($solicitud['observacion'] ?? null);
            $sesiones = $clean($solicitud['sesiones'] ?? null);

            $ojoValue = $solicitud['ojo'] ?? null;
            if (is_array($ojoValue)) {
                $ojoValue = implode(',', array_values(array_filter(array_map($clean, $ojoValue))));
            } else {
                $ojoValue = $clean($ojoValue);
            }

            $lenteId = $clean($solicitud['lente_id'] ?? null);
            $lenteNombre = $clean($solicitud['lente_nombre'] ?? null);
            $lentePoder = $clean($solicitud['lente_poder'] ?? null);
            $lenteObs = $clean($solicitud['lente_observacion'] ?? null);
            $incision = $clean($solicitud['incision'] ?? null);

            $detalles = $solicitud['detalles'] ?? [];
            if (is_array($detalles)) {
                $detallePlano = null;
                foreach ($detalles as $detalle) {
                    if (!is_array($detalle)) {
                        continue;
                    }
                    $detallePlano = $detalle;
                    if (!empty($detalle['principal']) || !empty($detalle['tipo'])) {
                        break;
                    }
                }

                if (is_array($detallePlano)) {
                    $lenteId = $lenteId ?: $clean($detallePlano['id_lente_intraocular'] ?? ($detallePlano['lente_id'] ?? null));
                    $lenteNombre = $lenteNombre ?: $clean($detallePlano['lente'] ?? ($detallePlano['lente_nombre'] ?? null));
                    $lentePoder = $lentePoder ?: $clean($detallePlano['poder'] ?? ($detallePlano['lente_poder'] ?? null));
                    $lenteObs = $lenteObs ?: $clean($detallePlano['observaciones'] ?? ($detallePlano['lente_observacion'] ?? null));
                    $incision = $incision ?: $clean($detallePlano['incision'] ?? null);
                    if (!$ojoValue) {
                        $ojoValue = $clean($detallePlano['lateralidad'] ?? null);
                    }
                }
            }

            $stmt->execute([
                ':hc' => $hcNumber,
                ':form_id' => $formId,
                ':secuencia' => $secuencia,
                ':tipo' => $tipo,
                ':afiliacion' => $afiliacion,
                ':procedimiento' => $procedimiento,
                ':doctor' => $doctor,
                ':fecha' => $fecha,
                ':duracion' => $duracion,
                ':ojo' => $ojoValue,
                ':prioridad' => $prioridad,
                ':producto' => $producto,
                ':observacion' => $observacion,
                ':sesiones' => $sesiones,
                ':lente_id' => $lenteId,
                ':lente_nombre' => $lenteNombre,
                ':lente_poder' => $lentePoder,
                ':lente_observacion' => $lenteObs,
                ':incision' => $incision,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Solicitudes guardadas o actualizadas correctamente',
            'total' => count($solicitudes),
        ];
    }

    private function syncSolicitudEstadoFromChecklist(int $solicitudId): void
    {
        $legacyState = $this->legacyStateBySolicitud($solicitudId);
        $rows = $this->queryChecklistRows($solicitudId);
        [, , $kanban] = $this->buildChecklistContext($legacyState, $rows);

        $payload = ['estado' => $kanban['slug'] ?? $legacyState];
        if ($this->hasColumn('solicitud_procedimiento', 'updated_at')) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->updateRow('solicitud_procedimiento', $payload, 'id = :id', [':id' => $solicitudId]);
    }

    private function checklistRowExists(int $solicitudId, string $slug): bool
    {
        if (!$this->tableExists('solicitud_checklist')) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id FROM solicitud_checklist WHERE solicitud_id = :id AND etapa_slug = :slug LIMIT 1');
        $stmt->execute([':id' => $solicitudId, ':slug' => $slug]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string,mixed> $customFields
     */
    private function guardarCrmMeta(int $solicitudId, array $customFields): void
    {
        if (!$this->tableExists('solicitud_crm_meta')) {
            return;
        }

        $columns = $this->tableColumns('solicitud_crm_meta');
        $now = date('Y-m-d H:i:s');

        foreach ($customFields as $key => $value) {
            $metaKey = trim((string) $key);
            if ($metaKey === '') {
                continue;
            }

            $metaValue = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $existing = $this->db->prepare('SELECT id FROM solicitud_crm_meta WHERE solicitud_id = :solicitud_id AND meta_key = :meta_key LIMIT 1');
            $existing->execute([
                ':solicitud_id' => $solicitudId,
                ':meta_key' => $metaKey,
            ]);
            $existingId = $existing->fetchColumn();

            if ($existingId !== false) {
                $update = [];
                if (in_array('meta_value', $columns, true)) {
                    $update['meta_value'] = $metaValue;
                }
                if (in_array('meta_type', $columns, true)) {
                    $update['meta_type'] = 'string';
                }
                if (in_array('updated_at', $columns, true)) {
                    $update['updated_at'] = $now;
                }

                if ($update !== []) {
                    $this->updateRow('solicitud_crm_meta', $update, 'id = :id', [':id' => (int) $existingId]);
                }
                continue;
            }

            $insert = [
                'solicitud_id' => $solicitudId,
                'meta_key' => $metaKey,
            ];
            if (in_array('meta_value', $columns, true)) {
                $insert['meta_value'] = $metaValue;
            }
            if (in_array('meta_type', $columns, true)) {
                $insert['meta_type'] = 'string';
            }
            if (in_array('created_at', $columns, true)) {
                $insert['created_at'] = $now;
            }
            if (in_array('updated_at', $columns, true)) {
                $insert['updated_at'] = $now;
            }

            $this->insertRow('solicitud_crm_meta', $insert);
        }
    }

    /**
     * @param array<string,mixed> $protocolo
     */
    private function guardarConfirmacionCirugiaMeta(int $solicitudId, array $protocolo, ?int $usuarioId): void
    {
        $formId = trim((string) ($protocolo['form_id'] ?? ''));
        if ($formId === '') {
            throw new RuntimeException('No se puede guardar confirmación sin form_id de protocolo.');
        }

        if (!$this->tableExists('solicitud_crm_meta')) {
            return;
        }

        $columns = $this->tableColumns('solicitud_crm_meta');
        if (!in_array('solicitud_id', $columns, true) || !in_array('meta_key', $columns, true)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $metaValues = [
            'cirugia_confirmada_form_id' => $formId,
            'cirugia_confirmada_hc_number' => trim((string) ($protocolo['hc_number'] ?? '')),
            'cirugia_confirmada_fecha_inicio' => trim((string) ($protocolo['fecha_inicio'] ?? '')),
            'cirugia_confirmada_lateralidad' => trim((string) ($protocolo['lateralidad'] ?? '')),
            'cirugia_confirmada_membrete' => trim((string) ($protocolo['membrete'] ?? '')),
            'cirugia_confirmada_by' => $usuarioId ? (string) $usuarioId : '',
            'cirugia_confirmada_at' => $now,
        ];

        $metaTypes = [
            'cirugia_confirmada_form_id' => 'texto',
            'cirugia_confirmada_hc_number' => 'texto',
            'cirugia_confirmada_fecha_inicio' => 'fecha',
            'cirugia_confirmada_lateralidad' => 'texto',
            'cirugia_confirmada_membrete' => 'texto',
            'cirugia_confirmada_by' => 'numero',
            'cirugia_confirmada_at' => 'fecha',
        ];

        $placeholders = implode(', ', array_fill(0, count(self::META_CIRUGIA_CONFIRMADA_KEYS), '?'));
        $deleteSql = "DELETE FROM solicitud_crm_meta
             WHERE solicitud_id = ?
               AND meta_key IN ($placeholders)";

        $params = array_merge([$solicitudId], self::META_CIRUGIA_CONFIRMADA_KEYS);
        $deleteStmt = $this->db->prepare($deleteSql);
        $deleteStmt->execute($params);

        foreach (self::META_CIRUGIA_CONFIRMADA_KEYS as $metaKey) {
            $value = $metaValues[$metaKey] ?? '';
            if ($value === '') {
                continue;
            }

            $insert = [
                'solicitud_id' => $solicitudId,
                'meta_key' => $metaKey,
            ];

            if (in_array('meta_value', $columns, true)) {
                $insert['meta_value'] = $value;
            }
            if (in_array('meta_type', $columns, true)) {
                $insert['meta_type'] = $metaTypes[$metaKey] ?? 'texto';
            }
            if (in_array('created_at', $columns, true)) {
                $insert['created_at'] = $now;
            }
            if (in_array('updated_at', $columns, true)) {
                $insert['updated_at'] = $now;
            }

            $this->insertRow('solicitud_crm_meta', $insert);
        }
    }

    /**
     * @return array<int,int>
     */
    private function normalizeFollowers(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function canCompleteStage(int $solicitudId, string $targetSlug, string $legacyState): bool
    {
        [$checklist] = $this->buildChecklistContext($legacyState, $this->queryChecklistRows($solicitudId));

        $targetOrder = null;
        foreach (self::DEFAULT_STAGES as $stage) {
            if ($stage['slug'] === $targetSlug) {
                $targetOrder = (int) $stage['order'];
                break;
            }
        }

        if ($targetOrder === null) {
            return true;
        }

        foreach ($checklist as $item) {
            $itemOrder = (int) ($item['order'] ?? 0);
            if ($itemOrder >= $targetOrder) {
                continue;
            }

            if (!empty($item['required']) && empty($item['completed'])) {
                return false;
            }
        }

        return true;
    }

    private function upsertChecklistRow(int $solicitudId, string $slug, bool $completed, ?int $userId, ?string $note): void
    {
        if (!$this->tableExists('solicitud_checklist')) {
            return;
        }

        $columns = $this->tableColumns('solicitud_checklist');
        $stmt = $this->db->prepare('SELECT id FROM solicitud_checklist WHERE solicitud_id = :solicitud_id AND etapa_slug = :etapa_slug LIMIT 1');
        $stmt->execute([
            ':solicitud_id' => $solicitudId,
            ':etapa_slug' => $slug,
        ]);
        $id = $stmt->fetchColumn();

        $now = date('Y-m-d H:i:s');
        $completedAt = $completed ? $now : null;

        if ($id !== false) {
            $payload = [];
            if (in_array('completado_at', $columns, true)) {
                $payload['completado_at'] = $completedAt;
            }
            if (in_array('completado_por', $columns, true)) {
                $payload['completado_por'] = $completed ? $userId : null;
            }
            if (in_array('nota', $columns, true) && $note !== null) {
                $payload['nota'] = trim($note);
            }
            if (in_array('updated_at', $columns, true)) {
                $payload['updated_at'] = $now;
            }

            if ($payload !== []) {
                $this->updateRow('solicitud_checklist', $payload, 'id = :id', [':id' => (int) $id]);
            }
            return;
        }

        $payload = [
            'solicitud_id' => $solicitudId,
            'etapa_slug' => $slug,
        ];
        if (in_array('completado_at', $columns, true)) {
            $payload['completado_at'] = $completedAt;
        }
        if (in_array('completado_por', $columns, true)) {
            $payload['completado_por'] = $completed ? $userId : null;
        }
        if (in_array('nota', $columns, true) && $note !== null) {
            $payload['nota'] = trim($note);
        }
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = $now;
        }

        $this->insertRow('solicitud_checklist', $payload);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryChecklistRows(int $solicitudId): array
    {
        if (!$this->tableExists('solicitud_checklist')) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT etapa_slug, completado_at FROM solicitud_checklist WHERE solicitud_id = :id ORDER BY id');
        $stmt->execute([':id' => $solicitudId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int,array<string,mixed>> $checklistRows
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    private function buildChecklistContext(string $legacyState, array $checklistRows): array
    {
        $bySlug = [];
        foreach ($checklistRows as $row) {
            $slug = $this->normalizeKanbanSlug((string) ($row['etapa_slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $bySlug[$slug] = [
                'completed' => !empty($row['completado_at']),
                'completado_at' => $row['completado_at'] !== null ? (string) $row['completado_at'] : null,
            ];
        }

        $legacySlug = $this->normalizeKanbanSlug($legacyState);
        $stageIndex = $this->stageIndex($legacySlug);

        $checklist = [];
        foreach (self::DEFAULT_STAGES as $index => $stage) {
            $slug = $stage['slug'];
            $fromDb = $bySlug[$slug] ?? null;
            $completed = $fromDb['completed'] ?? false;

            if ($fromDb === null && $stageIndex !== null) {
                $completed = $index <= $stageIndex;
            }

            $checklist[] = [
                'slug' => $slug,
                'label' => $stage['label'],
                'order' => $stage['order'],
                'required' => $stage['required'],
                'completed' => $completed,
                'completado_at' => $fromDb['completado_at'] ?? null,
                'can_toggle' => true,
            ];
        }

        $total = count($checklist);
        $completedCount = count(array_filter($checklist, static fn(array $item): bool => !empty($item['completed'])));
        $percent = $total > 0 ? round(($completedCount / $total) * 100, 1) : 0.0;

        $next = null;
        foreach ($checklist as $item) {
            if (!empty($item['required']) && empty($item['completed'])) {
                $next = $item;
                break;
            }
        }

        $progress = [
            'total' => $total,
            'completed' => $completedCount,
            'percent' => $percent,
            'next_slug' => $next['slug'] ?? null,
            'next_label' => $next['label'] ?? null,
        ];

        if ($legacySlug === 'completado') {
            $kanbanSlug = 'completado';
        } elseif ($legacySlug === 'programada') {
            $kanbanSlug = 'programada';
        } elseif (in_array($legacySlug, ['recibida', 'llamado'], true)) {
            $kanbanSlug = $legacySlug;
        } elseif ($next !== null) {
            $stage = $this->stageBySlug((string) $next['slug']);
            $kanbanSlug = (string) ($stage['column'] ?? $next['slug']);
        } else {
            $kanbanSlug = 'programada';
        }

        return [
            $checklist,
            $progress,
            [
                'slug' => $kanbanSlug,
                'label' => $this->kanbanLabel($kanbanSlug),
            ],
        ];
    }

    private function stageIndex(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        foreach (self::DEFAULT_STAGES as $index => $stage) {
            if ($stage['slug'] === $slug || $stage['column'] === $slug) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{slug:string,label:string,order:int,column:string,required:bool}|null
     */
    private function stageBySlug(string $slug): ?array
    {
        foreach (self::DEFAULT_STAGES as $stage) {
            if ($stage['slug'] === $slug) {
                return $stage;
            }
        }

        return null;
    }

    private function kanbanLabel(string $slug): string
    {
        foreach (self::DEFAULT_STAGES as $stage) {
            if ($stage['column'] === $slug || $stage['slug'] === $slug) {
                return $stage['label'];
            }
        }

        return ucfirst(str_replace('-', ' ', $slug));
    }

    private function normalizeKanbanSlug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}/u', '', $normalized) ?? $value;
            }
        }

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
        ]);

        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9\s\-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', '-', trim($value)) ?? $value;

        return trim($value, '-');
    }

    private function normalizeTurneroEstado(string $estado): ?string
    {
        $limpio = trim($estado);
        if ($limpio === '') {
            return null;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($limpio, 'UTF-8') : strtolower($limpio);

        return self::TURNERO_STATE_MAP[$key] ?? null;
    }

    private function normalizeTaskPriority(mixed $priority): string
    {
        $raw = trim((string) ($priority ?? ''));
        if ($raw === '') {
            return 'media';
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
        $key = str_replace([' ', '-'], '_', $key);

        return match ($key) {
            'low', 'baja' => 'baja',
            'high', 'alta' => 'alta',
            'urgent', 'urgente', 'critical', 'critica', 'crítica' => 'urgente',
            'normal', 'medium', 'media' => 'media',
            default => 'media',
        };
    }

    private function asignarTurnoSiNecesario(int $id): ?int
    {
        $consulta = $this->db->prepare('SELECT turno FROM solicitud_procedimiento WHERE id = :id FOR UPDATE');
        $consulta->execute([':id' => $id]);
        $actual = $consulta->fetchColumn();

        if ($actual !== false && $actual !== null) {
            return (int) $actual;
        }

        $maxStmt = $this->db->query('SELECT turno FROM solicitud_procedimiento WHERE turno IS NOT NULL ORDER BY turno DESC LIMIT 1 FOR UPDATE');
        $maxTurno = $maxStmt ? (int) $maxStmt->fetchColumn() : 0;
        $siguiente = $maxTurno + 1;

        $update = $this->db->prepare('UPDATE solicitud_procedimiento SET turno = :turno WHERE id = :id AND turno IS NULL');
        $update->execute([
            ':turno' => $siguiente,
            ':id' => $id,
        ]);

        if ($update->rowCount() === 0) {
            $consulta->execute([':id' => $id]);
            $actual = $consulta->fetchColumn();

            return $actual !== false ? (int) $actual : null;
        }

        return $siguiente;
    }

    private function findIdByFormId(int $formId): ?int
    {
        if ($formId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id FROM solicitud_procedimiento WHERE form_id = :form_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':form_id' => $formId]);
        $result = $stmt->fetchColumn();

        return $result !== false ? (int) $result : null;
    }

    private function assertSolicitudExists(int $solicitudId): void
    {
        if ($this->fetchSolicitudById($solicitudId) === null) {
            throw new RuntimeException('Solicitud no encontrada');
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchSolicitudById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM solicitud_procedimiento WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCrmDetalleRow(int $solicitudId): ?array
    {
        if (!$this->tableExists('solicitud_crm_detalles')) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM solicitud_crm_detalles WHERE solicitud_id = :solicitud_id LIMIT 1');
        $stmt->execute([':solicitud_id' => $solicitudId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function legacyStateBySolicitud(int $solicitudId): string
    {
        $stmt = $this->db->prepare('SELECT estado FROM solicitud_procedimiento WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $solicitudId]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : '';
    }

    private function resolveCompanyId(): int
    {
        if ($this->companyIdCache !== null) {
            return $this->companyIdCache;
        }

        try {
            $stmt = $this->db->query('SELECT company_id FROM crm_tasks WHERE company_id IS NOT NULL LIMIT 1');
            $value = $stmt ? (int) $stmt->fetchColumn() : 0;
            if ($value > 0) {
                $this->companyIdCache = $value;
                return $value;
            }
        } catch (Throwable) {
            // ignore
        }

        $this->companyIdCache = 1;

        return 1;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = false;

        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE :table');
            $stmt->execute([':table' => $table]);
            $exists = $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            $exists = false;
        }

        if (!$exists) {
            try {
                $sql = 'SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1';
                $this->db->query($sql);
                $exists = true;
            } catch (Throwable) {
                $exists = false;
            }
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->columnsCache)) {
            return $this->columnsCache[$table];
        }

        if (!$this->tableExists($table)) {
            $this->columnsCache[$table] = [];
            return [];
        }

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            $rows = [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = trim((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        $this->columnsCache[$table] = $columns;

        return $columns;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function insertRow(string $table, array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $columns = [];
        $holders = [];
        $bindings = [];

        foreach ($payload as $column => $value) {
            $key = ':' . $column;
            $columns[] = '`' . $column . '`';
            $holders[] = $key;
            $bindings[$key] = $value;
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '', $table),
            implode(', ', $columns),
            implode(', ', $holders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $bindings
     */
    private function updateRow(string $table, array $payload, string $where, array $bindings = []): int
    {
        if ($payload === []) {
            return 0;
        }

        $sets = [];
        $params = $bindings;

        foreach ($payload as $column => $value) {
            $key = ':set_' . $column;
            $sets[] = '`' . $column . '` = ' . $key;
            $params[$key] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            str_replace('`', '', $table),
            implode(', ', $sets),
            $where
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $formats = ['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 16 ? $value . ':00' : $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
            $format = strlen($value) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchSolicitudBloqueoBase(int $solicitudId): ?array
    {
        $selectFecha = 'sp.fecha AS fecha_programada';
        $selectSala = 'NULL AS sala';
        $join = '';

        $canJoinConsultaData = $this->tableExists('consulta_data')
            && $this->hasColumn('consulta_data', 'hc_number')
            && $this->hasColumn('consulta_data', 'form_id');

        if ($canJoinConsultaData) {
            $join = ' LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id';
            if ($this->hasColumn('consulta_data', 'fecha')) {
                $selectFecha = 'COALESCE(cd.fecha, sp.fecha) AS fecha_programada';
            }
            if ($this->hasColumn('consulta_data', 'quirofano')) {
                $selectSala = 'cd.quirofano AS sala';
            }
        }

        $stmt = $this->db->prepare(
            sprintf(
                'SELECT sp.id, sp.doctor, %s, %s
                 FROM solicitud_procedimiento sp%s
                 WHERE sp.id = :id
                 LIMIT 1',
                $selectFecha,
                $selectSala,
                $join
            )
        );
        $stmt->execute([':id' => $solicitudId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function parseFlexibleDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }

        foreach ([
            'Y-m-d H:i:s',
            DateTimeImmutable::ATOM,
            'Y-m-d\TH:i',
            'd/m/Y H:i',
            'd-m-Y H:i',
            'Y-m-d',
        ] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $raw);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
