<?php

namespace Modules\Examenes\Services;

use DateTimeImmutable;
use Modules\CRM\Models\LeadModel;
use Modules\CRM\Models\TaskModel;
use Modules\CRM\Services\CrmProjectService;
use Modules\CRM\Services\CrmTaskService;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\CRM\Services\LeadCrmCoreService;
use Modules\WhatsApp\Services\Messenger as WhatsAppMessenger;
use Modules\WhatsApp\WhatsAppModule;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class ExamenCrmService
{
    private const ESTADOS_TAREA_VALIDOS = ['pendiente', 'en_progreso', 'completada', 'cancelada'];

    private PDO $pdo;
    private LeadModel $leadModel;
    private LeadConfigurationService $leadConfig;
    private LeadCrmCoreService $crmCore;
    private TaskModel $taskModel;
    private CrmTaskService $taskService;
    private CrmProjectService $projectService;
    private WhatsAppMessenger $whatsapp;
    private ExamenCalendarBlockService $calendarBlocks;
    private ExamenMailLogService $mailLogService;
    private ExamenEstadoService $estadoService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->leadModel = new LeadModel($pdo);
        $this->leadConfig = new LeadConfigurationService($pdo);
        $this->crmCore = new LeadCrmCoreService($pdo);
        $this->taskModel = new TaskModel($pdo);
        $this->taskService = new CrmTaskService($pdo);
        $this->projectService = new CrmProjectService($pdo);
        $this->whatsapp = WhatsAppModule::messenger($pdo);
        $this->calendarBlocks = new ExamenCalendarBlockService($pdo);
        $this->mailLogService = new ExamenMailLogService($pdo);
        $this->estadoService = new ExamenEstadoService();
    }

    public function obtenerResponsables(): array
    {
        return $this->leadConfig->getAssignableUsers();
    }

    public function obtenerFuentes(): array
    {
        return $this->leadConfig->getSources();
    }

    public function obtenerResumen(int $examenId): array
    {
        $detalle = $this->obtenerDetalleExamen($examenId);
        if (!$detalle) {
            throw new RuntimeException('Examen no encontrado');
        }

        $seguidores = $detalle['seguidores'] ?? [];
        unset($detalle['seguidores']);

        if (!empty($seguidores)) {
            $detalle['seguidores'] = $this->obtenerUsuariosPorIds($seguidores);
        } else {
            $detalle['seguidores'] = [];
        }

        $lead = null;
        $crmResumen = null;
        if (!empty($detalle['crm_lead_id'])) {
            $lead = $this->leadModel->findById((int) $detalle['crm_lead_id']);
            if ($lead) {
                $lead['url'] = $this->buildLeadUrl((int) $lead['id']);
                $crmResumen = $this->crmCore->getResumen((int) $lead['id'], LeadCrmCoreService::CONTEXT_EXAMEN, $examenId);
            }
        }

        $project = $this->ensureCrmProject(
            $examenId,
            $detalle,
            !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null,
            null
        );

        return [
            'detalle' => $detalle,
            'notas' => $this->obtenerNotas($examenId),
            'adjuntos' => $this->obtenerAdjuntos($examenId),
            'tareas' => $this->obtenerTareas($examenId),
            'campos_personalizados' => $this->obtenerCamposPersonalizados($examenId),
            'lead' => $lead,
            'crm_resumen' => $crmResumen,
            'project' => $project,
            'bloqueos_agenda' => $this->calendarBlocks->listarPorExamen($examenId),
            'mail_events' => $this->mailLogService->fetchByExamen($examenId, 20),
        ];
    }

    public function registrarBloqueoAgenda(int $examenId, array $payload, ?int $usuarioId = null): array
    {
        $bloqueo = $this->calendarBlocks->registrar($examenId, $payload, $usuarioId);
        $resumen = $this->obtenerResumen($examenId);
        $resumen['ultimo_bloqueo'] = $bloqueo;

        return $resumen;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $userPermissions
     * @return array<string, mixed>
     */
    public function bootstrapChecklist(int $examenId, array $payload, ?int $usuarioId, array $userPermissions = []): array
    {
        $detalle = $this->obtenerDetalleExamen($examenId);
        if (!$detalle) {
            throw new RuntimeException('Examen no encontrado', 404);
        }

        $hcNumber = $this->normalizarTexto($payload['hc_number'] ?? $detalle['hc_number'] ?? null);
        if (!$hcNumber) {
            throw new RuntimeException('Falta el número de historia clínica para sincronizar el CRM', 422);
        }

        $leadId = $this->sincronizarLead(
            $examenId,
            $detalle,
            [
                'hc_number' => $hcNumber,
                'crm_lead_id' => $detalle['crm_lead_id'] ?? null,
                'etapa' => $detalle['crm_pipeline_stage'] ?? null,
            ],
            $usuarioId
        );

        $project = $this->ensureCrmProject($examenId, $detalle, $leadId, $usuarioId);
        $projectId = isset($project['id']) ? (int) $project['id'] : null;

        $this->persistCrmMap($examenId, $leadId);

        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($examenId, $companyId);
        $checklist = $this->buildChecklist($examenId, $existingTasks, $userPermissions);
        $tasks = $this->syncChecklistTasks($examenId, $detalle, $leadId, $projectId, $checklist, $usuarioId);

        $refreshedTasks = $this->fetchChecklistTasks($examenId, $companyId);
        $checklist = $this->buildChecklist($examenId, $refreshedTasks, $userPermissions);
        $progress = $this->computeChecklistProgress($checklist);

        return [
            'lead_id' => $leadId,
            'project_id' => $projectId,
            'tasks' => $tasks,
            'checklist' => $checklist,
            'checklist_progress' => $progress,
        ];
    }

    /**
     * @param array<int, string> $userPermissions
     * @return array<string, mixed>
     */
    public function checklistState(int $examenId, array $userPermissions = []): array
    {
        $detalle = $this->obtenerDetalleExamen($examenId);
        if (!$detalle) {
            throw new RuntimeException('Examen no encontrado', 404);
        }

        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($examenId, $companyId);
        $checklist = $this->buildChecklist($examenId, $existingTasks, $userPermissions);
        $progress = $this->computeChecklistProgress($checklist);

        $tasks = [];
        foreach ($existingTasks as $taskKey => $task) {
            $tasks[] = $this->formatChecklistTask($task, (string) $taskKey);
        }

        return [
            'lead_id' => !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null,
            'project_id' => !empty($detalle['crm_project_id']) ? (int) $detalle['crm_project_id'] : null,
            'tasks' => $tasks,
            'checklist' => $checklist,
            'checklist_progress' => $progress,
        ];
    }

    /**
     * @param array<int, string> $userPermissions
     * @return array<string, mixed>
     */
    public function syncChecklistStage(
        int $examenId,
        string $etapaSlug,
        bool $completado,
        ?int $usuarioId,
        array $userPermissions = []
    ): array {
        $detalle = $this->obtenerDetalleExamen($examenId);
        if (!$detalle) {
            throw new RuntimeException('Examen no encontrado', 404);
        }

        $slugNormalizado = $this->estadoService->normalizeSlug($etapaSlug);
        $stage = $this->findChecklistStage($slugNormalizado);
        if (!$stage) {
            throw new RuntimeException('Etapa inválida para el checklist de exámenes', 422);
        }

        $leadId = !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null;
        if (!$leadId && !empty($detalle['hc_number'])) {
            $leadId = $this->sincronizarLead(
                $examenId,
                $detalle,
                [
                    'hc_number' => $detalle['hc_number'],
                    'etapa' => $slugNormalizado,
                ],
                $usuarioId
            );
        }

        $project = $this->ensureCrmProject($examenId, $detalle, $leadId, $usuarioId);
        $projectId = isset($project['id']) ? (int) $project['id'] : null;
        $this->persistCrmMap($examenId, $leadId);

        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($examenId, $companyId);
        $checklist = $this->buildChecklist($examenId, $existingTasks, $userPermissions);

        $targetIndex = null;
        foreach ($checklist as $index => $item) {
            if (($item['slug'] ?? null) === $slugNormalizado) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            throw new RuntimeException('No se pudo ubicar la etapa solicitada en el checklist', 422);
        }

        $checklist[$targetIndex]['completed'] = $completado;
        $checklist[$targetIndex]['checked'] = $completado;
        $checklist[$targetIndex]['completado'] = $completado;

        $tasks = $this->syncChecklistTasks($examenId, $detalle, $leadId, $projectId, $checklist, $usuarioId);
        $refreshedTasks = $this->fetchChecklistTasks($examenId, $companyId);
        $checklist = $this->buildChecklist($examenId, $refreshedTasks, $userPermissions);
        $progress = $this->computeChecklistProgress($checklist);

        $kanbanStage = $this->resolveKanbanFromChecklist($checklist);
        if ($kanbanStage) {
            $this->actualizarEstadoExamenDesdeChecklist($examenId, $kanbanStage, $usuarioId);
        }

        return [
            'lead_id' => $leadId,
            'project_id' => $projectId,
            'tasks' => $tasks,
            'checklist' => $checklist,
            'checklist_progress' => $progress,
            'kanban_estado' => $kanbanStage['slug'] ?? null,
            'kanban_estado_label' => $kanbanStage['label'] ?? null,
        ];
    }

    public function guardarDetalles(int $examenId, array $data, ?int $usuarioId = null): void
    {
        $detalleActual = $this->obtenerDetalleExamen($examenId);
        if (!$detalleActual) {
            throw new RuntimeException('Examen no encontrado');
        }

        $responsableId = isset($data['responsable_id']) && $data['responsable_id'] !== ''
            ? (int) $data['responsable_id']
            : null;

        $etapa = $this->normalizarEtapa($data['pipeline_stage'] ?? null);
        $fuente = $this->normalizarTexto($data['fuente'] ?? null);
        $contactoEmail = $this->normalizarTexto($data['contacto_email'] ?? null);
        $contactoTelefono = $this->normalizarTexto($data['contacto_telefono'] ?? null);
        $seguidores = $this->normalizarSeguidores($data['seguidores'] ?? []);

        $crmLeadId = $this->sincronizarLead(
            $examenId,
            $detalleActual,
            [
                'crm_lead_id' => $data['crm_lead_id'] ?? null,
                'responsable_id' => $responsableId,
                'fuente' => $fuente,
                'contacto_email' => $contactoEmail,
                'contacto_telefono' => $contactoTelefono,
                'etapa' => $etapa,
            ],
            $usuarioId
        );

        $jsonSeguidores = !empty($seguidores) ? json_encode($seguidores, JSON_UNESCAPED_UNICODE) : null;

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO examen_crm_detalles (examen_id, crm_lead_id, responsable_id, pipeline_stage, fuente, contacto_email, contacto_telefono, followers)
                 VALUES (:examen_id, :crm_lead_id, :responsable_id, :pipeline_stage, :fuente, :contacto_email, :contacto_telefono, :followers)
                 ON DUPLICATE KEY UPDATE
                    crm_lead_id = VALUES(crm_lead_id),
                    responsable_id = VALUES(responsable_id),
                    pipeline_stage = VALUES(pipeline_stage),
                    fuente = VALUES(fuente),
                    contacto_email = VALUES(contacto_email),
                    contacto_telefono = VALUES(contacto_telefono),
                    followers = VALUES(followers)'
            );

            $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
            $stmt->bindValue(':crm_lead_id', $crmLeadId, $crmLeadId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':responsable_id', $responsableId, $responsableId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':pipeline_stage', $etapa);
            $stmt->bindValue(':fuente', $fuente, $fuente !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':contacto_email', $contactoEmail, $contactoEmail !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':contacto_telefono', $contactoTelefono, $contactoTelefono !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':followers', $jsonSeguidores, $jsonSeguidores !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();

            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $this->guardarCamposPersonalizados($examenId, $data['custom_fields']);
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $detallePosterior = $this->safeObtenerDetalleExamen($examenId);
        $this->notifyWhatsAppEvent(
            $examenId,
            'details_updated',
            [
                'detalle' => $detallePosterior,
                'detalle_anterior' => $detalleActual,
                'payload' => [
                    'responsable_id' => $responsableId,
                    'etapa' => $etapa,
                    'fuente' => $fuente,
                    'contacto_email' => $contactoEmail,
                    'contacto_telefono' => $contactoTelefono,
                ],
            ]
        );
    }

    public function registrarNota(int $examenId, string $nota, ?int $autorId): void
    {
        $nota = trim(strip_tags($nota));
        if ($nota === '') {
            throw new RuntimeException('La nota no puede estar vacía');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO examen_crm_notas (examen_id, autor_id, nota) VALUES (:examen_id, :autor_id, :nota)'
        );
        $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
        $stmt->bindValue(':autor_id', $autorId, $autorId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nota', $nota, PDO::PARAM_STR);
        $stmt->execute();

        $this->notifyWhatsAppEvent(
            $examenId,
            'note_added',
            [
                'nota' => $nota,
                'autor_id' => $autorId,
                'autor_nombre' => $this->obtenerNombreUsuario($autorId),
            ]
        );
    }

    /**
     * @return array{name?:string,email?:string,hc_number?:string,form_id?:string,descripcion?:string}|null
     */
    public function obtenerContactoPaciente(int $examenId): ?array
    {
        $detalle = $this->safeObtenerDetalleExamen($examenId);
        if ($detalle === null) {
            return null;
        }

        $context = array_filter([
            'name' => $this->normalizarTexto($detalle['paciente_nombre'] ?? null),
            'email' => $this->normalizarTexto($detalle['crm_contacto_email'] ?? null),
            'hc_number' => $this->normalizarTexto($detalle['hc_number'] ?? null),
            'form_id' => $this->normalizarTexto($detalle['form_id'] ?? null),
            'descripcion' => $this->normalizarTexto($detalle['examen_nombre'] ?? null),
        ], static fn($value) => $value !== null && $value !== '');

        return $context !== [] ? $context : null;
    }

    public function registrarTarea(int $examenId, array $data, ?int $autorId): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new RuntimeException('El título de la tarea es obligatorio');
        }

        $descripcion = $this->normalizarTexto($data['descripcion'] ?? null);
        $estado = $this->normalizarEstadoTarea($data['estado'] ?? 'pendiente');
        $assignedTo = isset($data['assigned_to']) && $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null;
        $dueDate = $this->normalizarFecha($data['due_date'] ?? null);
        $remindAt = $this->normalizarFechaHora($data['remind_at'] ?? null);
        $detalle = $this->safeObtenerDetalleExamen($examenId) ?? [];
        $leadId = !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null;
        $hcNumber = $this->normalizarTexto($detalle['hc_number'] ?? null);
        $project = $this->ensureCrmProject($examenId, $detalle, $leadId, $autorId);

        $tarea = $this->taskService->create(
            [
                'project_id' => $project['id'] ?? null,
                'entity_type' => 'examen',
                'entity_id' => (string) $examenId,
                'lead_id' => $leadId,
                'hc_number' => $hcNumber,
                'patient_id' => $hcNumber,
                'source_module' => 'examenes',
                'source_ref_id' => (string) $examenId,
                'title' => $titulo,
                'description' => $descripcion,
                'status' => $estado,
                'assigned_to' => $assignedTo,
                'due_date' => $dueDate,
                'remind_at' => $remindAt,
                'remind_channel' => $data['remind_channel'] ?? null,
            ],
            $this->resolveCompanyId(),
            $autorId ?? 0
        );

        $tareaContexto = [
            'id' => (int) ($tarea['id'] ?? 0),
            'titulo' => $tarea['title'] ?? $titulo,
            'descripcion' => $tarea['description'] ?? $descripcion,
            'estado' => $tarea['status'] ?? $estado,
            'assigned_to' => $assignedTo,
            'assigned_to_nombre' => $tarea['assigned_name'] ?? $this->obtenerNombreUsuario($assignedTo),
            'due_date' => $tarea['due_date'] ?? $dueDate,
            'remind_at' => $tarea['remind_at'] ?? $remindAt,
        ];

        $this->notifyWhatsAppEvent(
            $examenId,
            'task_created',
            [
                'tarea' => $tareaContexto,
                'autor_id' => $autorId,
                'autor_nombre' => $this->obtenerNombreUsuario($autorId),
            ]
        );
    }

    public function actualizarEstadoTarea(int $examenId, int $tareaId, string $estado): void
    {
        $estadoNormalizado = $this->normalizarEstadoTarea($estado);
        $companyId = $this->resolveCompanyId();
        $task = $this->taskModel->find($tareaId, $companyId);
        if (!$task || ($task['source_module'] ?? null) !== 'examenes' || (string) ($task['source_ref_id'] ?? '') !== (string) $examenId) {
            return;
        }

        $this->taskService->update($tareaId, $companyId, ['status' => $estadoNormalizado]);

        $tarea = $this->obtenerTareaPorId($examenId, $tareaId);
        if ($tarea !== null) {
            $this->notifyWhatsAppEvent(
                $examenId,
                'task_status_updated',
                [
                    'tarea' => $tarea,
                ]
            );
        }
    }

    public function registrarAdjunto(
        int $examenId,
        string $nombreOriginal,
        string $rutaRelativa,
        ?string $mimeType,
        ?int $tamano,
        ?int $usuarioId,
        ?string $descripcion = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO examen_crm_adjuntos (examen_id, nombre_original, ruta_relativa, mime_type, tamano_bytes, descripcion, subido_por)
             VALUES (:examen_id, :nombre_original, :ruta_relativa, :mime_type, :tamano_bytes, :descripcion, :subido_por)'
        );

        $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre_original', $nombreOriginal, PDO::PARAM_STR);
        $stmt->bindValue(':ruta_relativa', $rutaRelativa, PDO::PARAM_STR);
        $stmt->bindValue(':mime_type', $mimeType, $mimeType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tamano_bytes', $tamano, $tamano !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':subido_por', $usuarioId, $usuarioId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        $this->notifyWhatsAppEvent(
            $examenId,
            'attachment_uploaded',
            [
                'adjunto' => [
                    'nombre_original' => $nombreOriginal,
                    'descripcion' => $descripcion,
                    'mime_type' => $mimeType,
                    'tamano' => $tamano,
                ],
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $this->obtenerNombreUsuario($usuarioId),
            ]
        );
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function notifyWhatsAppEvent(int $examenId, string $evento, array $contexto = []): void
    {
        if (!$this->whatsapp->isEnabled()) {
            return;
        }

        $detalle = $contexto['detalle'] ?? $this->safeObtenerDetalleExamen($examenId);
        if ($detalle === null) {
            return;
        }

        $contexto['detalle'] = $detalle;

        if ($evento === 'details_updated' && isset($contexto['detalle_anterior']) && is_array($contexto['detalle_anterior'])) {
            if (!$this->huboCambiosRelevantes($contexto['detalle_anterior'], $detalle, $contexto['payload'] ?? [])) {
                return;
            }
        }

        $telefonos = $this->collectWhatsappPhones($detalle, $contexto);
        if (empty($telefonos)) {
            return;
        }

        $mensaje = $this->buildWhatsAppMessage($evento, $contexto);
        if ($mensaje === '') {
            return;
        }

        $this->whatsapp->sendTextMessage($telefonos, $mensaje);
    }

    /**
     * @param array<string, mixed> $detalle
     * @param array<string, mixed> $contexto
     *
     * @return string[]
     */
    private function collectWhatsappPhones(array $detalle, array $contexto): array
    {
        $telefonos = [];

        foreach (['crm_contacto_telefono', 'paciente_celular', 'contacto_telefono'] as $clave) {
            if (!empty($detalle[$clave])) {
                $telefonos[] = (string) $detalle[$clave];
            }
        }

        if (!empty($contexto['payload']['contacto_telefono'])) {
            $telefonos[] = (string) $contexto['payload']['contacto_telefono'];
        }

        if (!empty($contexto['telefonos_adicionales']) && is_array($contexto['telefonos_adicionales'])) {
            foreach ($contexto['telefonos_adicionales'] as $telefono) {
                if ($telefono) {
                    $telefonos[] = (string) $telefono;
                }
            }
        }

        if (!empty($contexto['tarea']['telefono'])) {
            $telefonos[] = (string) $contexto['tarea']['telefono'];
        }

        return array_values(array_unique(array_filter($telefonos)));
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function buildWhatsAppMessage(string $evento, array $contexto): string
    {
        $detalle = $contexto['detalle'] ?? [];
        $examenId = isset($detalle['id']) ? (int) $detalle['id'] : 0;
        $paciente = trim((string) ($detalle['paciente_nombre'] ?? ''));
        $marca = $this->whatsapp->getBrandName();
        $tituloExamen = $examenId > 0
            ? 'Examen #' . $examenId . ($paciente !== '' ? ' · ' . $paciente : '')
            : ($paciente !== '' ? $paciente : 'Examen CRM');

        switch ($evento) {
            case 'details_updated':
                $actual = $detalle['crm_pipeline_stage'] ?? ($detalle['pipeline_stage'] ?? null);
                $anterior = $contexto['detalle_anterior']['crm_pipeline_stage'] ?? null;
                $responsable = $detalle['crm_responsable_nombre'] ?? null;
                $lineas = [
                    '🔄 Actualización CRM - ' . $marca,
                    $tituloExamen,
                ];

                if (!empty($detalle['examen_nombre'])) {
                    $lineas[] = 'Examen: ' . $detalle['examen_nombre'];
                }

                if ($actual) {
                    if ($anterior && strcasecmp($anterior, $actual) !== 0) {
                        $lineas[] = 'Etapa: ' . $anterior . ' → ' . $actual;
                    } else {
                        $lineas[] = 'Etapa actual: ' . $actual;
                    }
                }

                if ($responsable) {
                    $lineas[] = 'Responsable: ' . $responsable;
                }

                if (!empty($detalle['prioridad'])) {
                    $lineas[] = 'Prioridad: ' . ucfirst((string) $detalle['prioridad']);
                }

                if (!empty($detalle['crm_fuente'])) {
                    $lineas[] = 'Fuente: ' . $detalle['crm_fuente'];
                }

                $lineas[] = 'Ver detalle: ' . $this->buildExamenUrl($examenId);

                return implode("\n", array_filter($lineas));

            case 'note_added':
                $nota = trim((string) ($contexto['nota'] ?? ''));
                $autor = trim((string) ($contexto['autor_nombre'] ?? ''));
                $lineas = [
                    '📝 Nueva nota en CRM - ' . $marca,
                    $tituloExamen,
                ];
                if ($autor !== '') {
                    $lineas[] = 'Autor: ' . $autor;
                }
                if ($nota !== '') {
                    $lineas[] = 'Nota: ' . $this->truncateText($nota, 320);
                }
                $lineas[] = 'Revisa el historial: ' . $this->buildExamenUrl($examenId);

                return implode("\n", array_filter($lineas));

            case 'task_created':
                $tarea = $contexto['tarea'] ?? [];
                $lineas = [
                    '✅ Nueva tarea CRM - ' . $marca,
                    $tituloExamen,
                ];
                if (!empty($tarea['titulo'])) {
                    $lineas[] = 'Tarea: ' . $tarea['titulo'];
                }
                if (!empty($tarea['assigned_to_nombre'])) {
                    $lineas[] = 'Responsable: ' . $tarea['assigned_to_nombre'];
                }
                if (!empty($tarea['estado'])) {
                    $lineas[] = 'Estado inicial: ' . $this->humanizeStatus((string) $tarea['estado']);
                }
                if (!empty($tarea['due_date'])) {
                    $fecha = $this->formatDateTime($tarea['due_date'], 'd/m/Y');
                    if ($fecha) {
                        $lineas[] = 'Vencimiento: ' . $fecha;
                    }
                }
                if (!empty($tarea['remind_at'])) {
                    $recordatorio = $this->formatDateTime($tarea['remind_at'], 'd/m/Y H:i');
                    if ($recordatorio) {
                        $lineas[] = 'Recordatorio: ' . $recordatorio;
                    }
                }
                $lineas[] = 'Gestiona la tarea: ' . $this->buildExamenUrl($examenId);

                return implode("\n", array_filter($lineas));

            case 'task_status_updated':
                $tarea = $contexto['tarea'] ?? [];
                $lineas = [
                    '📌 Actualización de tarea CRM - ' . $marca,
                    $tituloExamen,
                ];
                if (!empty($tarea['titulo'])) {
                    $lineas[] = 'Tarea: ' . $tarea['titulo'];
                }
                if (!empty($tarea['estado'])) {
                    $lineas[] = 'Estado actual: ' . $this->humanizeStatus((string) $tarea['estado']);
                }
                if (!empty($tarea['assigned_to_nombre'])) {
                    $lineas[] = 'Responsable: ' . $tarea['assigned_to_nombre'];
                }
                if (!empty($tarea['due_date'])) {
                    $fecha = $this->formatDateTime($tarea['due_date'], 'd/m/Y');
                    if ($fecha) {
                        $lineas[] = 'Vencimiento: ' . $fecha;
                    }
                }
                $lineas[] = 'Ver tablero: ' . $this->buildExamenUrl($examenId);

                return implode("\n", array_filter($lineas));

            case 'attachment_uploaded':
                $adjunto = $contexto['adjunto'] ?? [];
                $autorAdjunto = trim((string) ($contexto['usuario_nombre'] ?? ''));
                $lineas = [
                    '📎 Nuevo adjunto en CRM - ' . $marca,
                    $tituloExamen,
                ];
                if (!empty($adjunto['nombre_original'])) {
                    $lineas[] = 'Archivo: ' . $adjunto['nombre_original'];
                }
                if (!empty($adjunto['descripcion'])) {
                    $lineas[] = 'Descripción: ' . $this->truncateText((string) $adjunto['descripcion'], 200);
                }
                if ($autorAdjunto !== '') {
                    $lineas[] = 'Cargado por: ' . $autorAdjunto;
                }
                $lineas[] = 'Consulta los documentos: ' . $this->buildExamenUrl($examenId);

                return implode("\n", array_filter($lineas));

            default:
                return '';
        }
    }

    private function buildExamenUrl(int $examenId): string
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        $path = '/examenes/' . $examenId . '/crm';

        if ($base === '') {
            return $path;
        }

        return $base . $path;
    }

    private function formatDateTime(?string $valor, string $formato): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            $fecha = new DateTimeImmutable($valor);

            return $fecha->format($formato);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function truncateText(string $texto, int $limite): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite - 1) . '…';
    }

    private function humanizeStatus(string $estado): string
    {
        $estado = str_replace('_', ' ', $estado);

        return ucwords($estado);
    }

    /**
     * @param array<string, mixed> $anterior
     * @param array<string, mixed> $actual
     * @param array<string, mixed> $payload
     */
    private function huboCambiosRelevantes(array $anterior, array $actual, array $payload): bool
    {
        $comparaciones = [
            'crm_pipeline_stage',
            'crm_responsable_id',
            'crm_contacto_telefono',
            'crm_contacto_email',
            'crm_fuente',
        ];

        foreach ($comparaciones as $clave) {
            $previo = $anterior[$clave] ?? null;
            $nuevo = $actual[$clave] ?? null;

            if ($clave === 'crm_pipeline_stage') {
                $previo = $this->normalizarEtapa($previo ?? null);
                $nuevo = $this->normalizarEtapa($nuevo ?? null);
            }

            if ($previo != $nuevo) {
                return true;
            }
        }

        if (!empty($payload['contacto_telefono']) && $payload['contacto_telefono'] !== ($anterior['crm_contacto_telefono'] ?? null)) {
            return true;
        }

        if (!empty($payload['contacto_email']) && $payload['contacto_email'] !== ($anterior['crm_contacto_email'] ?? null)) {
            return true;
        }

        if (!empty($payload['fuente']) && $payload['fuente'] !== ($anterior['crm_fuente'] ?? null)) {
            return true;
        }

        return false;
    }

    private function obtenerNombreUsuario(?int $usuarioId): ?string
    {
        if (!$usuarioId) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT nombre FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $nombre = $stmt->fetchColumn();

        return $nombre ? (string) $nombre : null;
    }

    private function obtenerTareaPorId(int $examenId, int $tareaId): ?array
    {
        $companyId = $this->resolveCompanyId();
        $stmt = $this->pdo->prepare(
            'SELECT id, title AS titulo, description AS descripcion, status AS estado, assigned_to, due_date, due_at, remind_at, completed_at'
            . ' FROM crm_tasks WHERE id = :id AND company_id = :company_id AND source_module = "examenes" AND source_ref_id = :source_ref_id LIMIT 1'
        );
        $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $examenId, PDO::PARAM_STR);
        $stmt->execute();

        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tarea) {
            return null;
        }

        if (empty($tarea['due_date']) && !empty($tarea['due_at'])) {
            $tarea['due_date'] = substr((string) $tarea['due_at'], 0, 10);
        }

        $tarea['assigned_to_nombre'] = $this->obtenerNombreUsuario(isset($tarea['assigned_to']) ? (int) $tarea['assigned_to'] : null);

        return $tarea;
    }

    private function safeObtenerDetalleExamen(int $examenId): ?array
    {
        try {
            return $this->obtenerDetalleExamen($examenId);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function obtenerDetalleExamen(int $examenId): ?array
    {
        $sql = <<<'SQL'
            SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.estado,
                ce.prioridad,
                ce.doctor,
                ce.solicitante,
                ce.examen_nombre,
                ce.examen_codigo,
                ce.lateralidad,
                ce.observaciones,
                ce.created_at,
                ce.turno,
                ce.consulta_fecha,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                CONCAT(TRIM(pd.fname), ' ', TRIM(pd.mname), ' ', TRIM(pd.lname), ' ', TRIM(pd.lname2)) AS paciente_nombre,
                detalles.crm_lead_id AS crm_lead_id,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                detalles.followers AS crm_followers,
                responsable.nombre AS crm_responsable_nombre,
                responsable.email AS crm_responsable_email,
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
                cl.status  AS crm_lead_status,
                cl.source  AS crm_lead_source,
                cl.updated_at AS crm_lead_updated_at,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM consulta_examenes ce
            INNER JOIN patient_data pd ON ce.hc_number = pd.hc_number
            LEFT JOIN consulta_data cd ON ce.hc_number = cd.hc_number AND ce.form_id = cd.form_id
            LEFT JOIN examen_crm_detalles detalles ON detalles.examen_id = ce.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN crm_leads cl ON detalles.crm_lead_id = cl.id
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
                       MIN(CASE WHEN status IN ('pendiente','en_progreso','en_proceso') THEN COALESCE(due_at, CONCAT(due_date, " 23:59:59")) END) AS proximo_vencimiento
                FROM crm_tasks
                WHERE source_module = 'examenes'
                  AND company_id = :company_id
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = ce.id
            WHERE ce.id = :examen_id
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':examen_id' => $examenId,
            ':company_id' => $this->resolveCompanyId(),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        $row['crm_pipeline_stage'] = $this->normalizarEtapa($row['crm_pipeline_stage'] ?? null);

        $row['seguidores'] = $this->decodificarSeguidores($row['crm_followers'] ?? null);
        unset($row['crm_followers']);

        $row['dias_en_estado'] = $this->calcularDiasEnEstado($row['created_at'] ?? null);

        return $row;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return function_exists('asset') ? asset($path) : $path;
    }

    private function sincronizarLead(int $examenId, array $detalle, array $payload, ?int $usuarioId): ?int
    {
        $leadId = null;

        if (!empty($payload['crm_lead_id'])) {
            $leadId = (int) $payload['crm_lead_id'];
        } elseif (!empty($detalle['crm_lead_id'])) {
            $leadId = (int) $detalle['crm_lead_id'];
        }

        $nombre = trim((string) ($detalle['paciente_nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Examen #' . $examenId;
        }

        $hcNumber = $this->normalizarTexto($payload['hc_number'] ?? ($detalle['hc_number'] ?? null));
        $status = $this->mapearEtapaALeadStatus($payload['etapa'] ?? ($detalle['crm_pipeline_stage'] ?? null));

        if ($leadId) {
            $existente = $this->leadModel->findById($leadId);
            if (!$existente) {
                $leadId = null;
            } elseif ($hcNumber === null && !empty($existente['hc_number'])) {
                $hcNumber = (string) $existente['hc_number'];
            }
        }

        // Sin HC no podemos crear ni resolver un lead; devolvemos null para no impedir el guardado del resto de campos.
        if ($hcNumber === null) {
            return null;
        }

        $leadData = [
            'name' => $nombre,
            'email' => $payload['contacto_email'] ?? ($detalle['crm_contacto_email'] ?? null),
            'phone' => $payload['contacto_telefono'] ?? ($detalle['crm_contacto_telefono'] ?? $detalle['paciente_celular'] ?? null),
            'source' => $payload['fuente'] ?? ($detalle['crm_fuente'] ?? null),
            'assigned_to' => $payload['responsable_id'] ?? ($detalle['crm_responsable_id'] ?? null),
            'status' => $status,
            'notes' => $detalle['observacion'] ?? null,
        ];

        $lead = $this->crmCore->saveLeadFromContext(
            LeadCrmCoreService::CONTEXT_EXAMEN,
            $examenId,
            $hcNumber,
            $leadData,
            $usuarioId
        );

        return (int) ($lead['id'] ?? 0);
    }

    private function buildLeadUrl(int $leadId): string
    {
        return '/crm?lead=' . $leadId;
    }

    private function mapearEtapaALeadStatus(?string $etapa): string
    {
        return $this->leadConfig->normalizeStage($etapa);
    }

    private function obtenerUsuariosPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, nombre, email FROM users WHERE id IN ($placeholders) ORDER BY nombre");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerNotas(int $examenId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.nota, n.created_at, n.autor_id, u.nombre AS autor_nombre
             FROM examen_crm_notas n
             LEFT JOIN users u ON n.autor_id = u.id
             WHERE n.examen_id = :examen_id
             ORDER BY n.created_at DESC
             LIMIT 100'
        );
        $stmt->execute([':examen_id' => $examenId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerAdjuntos(int $examenId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.nombre_original, a.ruta_relativa, a.mime_type, a.tamano_bytes, a.descripcion, a.created_at, a.subido_por, u.nombre AS subido_por_nombre
             FROM examen_crm_adjuntos a
             LEFT JOIN users u ON a.subido_por = u.id
             WHERE a.examen_id = :examen_id
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([':examen_id' => $examenId]);

        $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($adjuntos as &$adjunto) {
            $ruta = (string) ($adjunto['ruta_relativa'] ?? '');
            if ($ruta !== '' && function_exists('asset')) {
                $adjunto['url'] = asset($ruta);
            } else {
                $adjunto['url'] = $ruta;
            }
        }
        unset($adjunto);

        return $adjuntos;
    }

    private function obtenerTareas(int $examenId): array
    {
        $companyId = $this->resolveCompanyId();
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.title AS titulo, t.description AS descripcion, t.status AS estado, t.assigned_to, t.created_by,
                    COALESCE(t.due_date, DATE(t.due_at)) AS due_date, t.remind_at, t.created_at, t.completed_at,
                    asignado.nombre AS assigned_name, creador.nombre AS created_name
             FROM crm_tasks t
             LEFT JOIN users asignado ON t.assigned_to = asignado.id
             LEFT JOIN users creador ON t.created_by = creador.id
             WHERE t.company_id = :company_id
               AND t.source_module = "examenes"
               AND t.source_ref_id = :source_ref_id
             ORDER BY
                CASE WHEN t.status IN ("pendiente", "en_progreso", "en_proceso") THEN 0 ELSE 1 END,
                COALESCE(t.due_date, DATE(t.due_at)) IS NULL,
                COALESCE(t.due_date, DATE(t.due_at)) ASC,
                t.created_at DESC'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':source_ref_id' => (string) $examenId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerCamposPersonalizados(int $examenId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, meta_key, meta_value, meta_type, created_at, updated_at
             FROM examen_crm_meta
             WHERE examen_id = :examen_id
             ORDER BY meta_key'
        );
        $stmt->execute([':examen_id' => $examenId]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'key' => $row['meta_key'],
                'value' => $row['meta_value'],
                'type' => $row['meta_type'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $result;
    }

    private function guardarCamposPersonalizados(int $examenId, array $campos): void
    {
        $limpios = [];
        foreach ($campos as $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $key = $this->normalizarClave($campo['key'] ?? null);
            if ($key === null) {
                continue;
            }

            $valor = $this->normalizarTexto($campo['value'] ?? null);
            $tipo = $this->normalizarTipoCampo($campo['type'] ?? 'texto');

            $limpios[$key] = [
                'value' => $valor,
                'type' => $tipo,
            ];
        }

        $stmtDelete = $this->pdo->prepare('DELETE FROM examen_crm_meta WHERE examen_id = :examen_id');
        $stmtDelete->execute([':examen_id' => $examenId]);

        if (empty($limpios)) {
            return;
        }

        $stmtInsert = $this->pdo->prepare(
            'INSERT INTO examen_crm_meta (examen_id, meta_key, meta_value, meta_type)
             VALUES (:examen_id, :meta_key, :meta_value, :meta_type)'
        );

        foreach ($limpios as $key => $info) {
            $stmtInsert->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':meta_key', $key, PDO::PARAM_STR);
            $stmtInsert->bindValue(':meta_value', $info['value'], $info['value'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtInsert->bindValue(':meta_type', $info['type'], PDO::PARAM_STR);
            $stmtInsert->execute();
        }
    }

    private function normalizarEtapa(?string $etapa): string
    {
        return $this->leadConfig->normalizeStage($etapa);
    }

    private function normalizarTexto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim(strip_tags((string) $valor));

        return $valor === '' ? null : $valor;
    }

    private function normalizarSeguidores($seguidores): array
    {
        if (!is_array($seguidores)) {
            return [];
        }

        $ids = [];
        foreach ($seguidores as $seguidor) {
            if ($seguidor === '' || $seguidor === null) {
                continue;
            }

            $ids[] = (int) $seguidor;
        }

        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }

    private function normalizarEstadoTarea(?string $estado): string
    {
        $estado = trim((string) $estado);
        foreach (self::ESTADOS_TAREA_VALIDOS as $valido) {
            if (strcasecmp($estado, $valido) === 0) {
                return $valido;
            }
        }

        return 'pendiente';
    }

    private function normalizarFecha(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $fecha = trim($fecha);
        $formatos = ['Y-m-d', 'd-m-Y', 'd/m/Y'];

        foreach ($formatos as $formato) {
            $dt = DateTimeImmutable::createFromFormat($formato, $fecha);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizarFechaHora(?string $fechaHora): ?string
    {
        if ($fechaHora === null || $fechaHora === '') {
            return null;
        }

        $fechaHora = trim($fechaHora);
        $formatos = ['Y-m-d H:i', 'Y-m-d\TH:i', 'd-m-Y H:i', 'd/m/Y H:i'];

        foreach ($formatos as $formato) {
            $dt = DateTimeImmutable::createFromFormat($formato, $fechaHora);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function normalizarClave($clave): ?string
    {
        if ($clave === null) {
            return null;
        }

        $clave = trim((string) $clave);
        if ($clave === '') {
            return null;
        }

        $clave = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $clave);

        return $clave === '' ? null : $clave;
    }

    private function normalizarTipoCampo(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));
        $permitidos = ['texto', 'numero', 'fecha', 'lista'];

        return in_array($tipo, $permitidos, true) ? $tipo : 'texto';
    }

    private function decodificarSeguidores(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizarSeguidores($decoded);
    }

    private function calcularDiasEnEstado(?string $fechaCreacion): ?int
    {
        if (empty($fechaCreacion)) {
            return null;
        }

        try {
            $inicio = new DateTimeImmutable($fechaCreacion);
            $hoy = new DateTimeImmutable('now');
            $diff = $inicio->diff($hoy);

            return (int) $diff->days;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $tasksByKey
     * @param array<int, string> $userPermissions
     * @return array<int, array<string, mixed>>
     */
    private function buildChecklist(int $examenId, array $tasksByKey, array $userPermissions = []): array
    {
        $stages = $this->estadoService->getStages();
        $checklist = [];

        $primerPendienteOrden = null;
        foreach ($stages as $stage) {
            if (!($stage['required'] ?? false)) {
                continue;
            }

            $slug = (string) ($stage['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $taskKey = $this->buildTaskKey($examenId, $slug);
            $task = $tasksByKey[$taskKey] ?? null;
            $completed = ($task['status'] ?? '') === 'completada';

            if (!$completed && $primerPendienteOrden === null) {
                $primerPendienteOrden = (int) ($stage['order'] ?? 0);
            }
        }

        foreach ($stages as $stage) {
            $slug = (string) ($stage['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $taskKey = $this->buildTaskKey($examenId, $slug);
            $task = $tasksByKey[$taskKey] ?? null;
            $completed = ($task['status'] ?? '') === 'completada';
            $order = (int) ($stage['order'] ?? 0);
            $required = (bool) ($stage['required'] ?? false);
            $canToggle = true;
            if ($required && $primerPendienteOrden !== null && $order > $primerPendienteOrden) {
                $canToggle = false;
            }

            $checklist[] = [
                'slug' => $slug,
                'label' => (string) ($stage['label'] ?? $slug),
                'column' => $stage['column'] ?? $slug,
                'order' => $order,
                'required' => $required,
                'completed' => $completed,
                'checked' => $completed,
                'completado' => $completed,
                'completado_at' => $task['completed_at'] ?? null,
                'task_id' => isset($task['id']) ? (int) $task['id'] : null,
                'can_toggle' => $canToggle || in_array('examenes.checklist.override', $userPermissions, true),
            ];
        }

        usort(
            $checklist,
            static fn(array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0)
        );

        return $checklist;
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    private function computeChecklistProgress(array $checklist, array $fallback = []): array
    {
        if (empty($checklist)) {
            return $fallback;
        }

        $total = count($checklist);
        $completed = 0;
        $next = null;

        foreach ($checklist as $item) {
            if (!empty($item['completed'])) {
                $completed++;
                continue;
            }

            if ($next === null) {
                $next = $item;
            }
        }

        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        if ($next === null && (!empty($fallback['next_slug']) || !empty($fallback['next_label']))) {
            $next = [
                'slug' => $fallback['next_slug'] ?? null,
                'label' => $fallback['next_label'] ?? null,
            ];
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'next_slug' => $next['slug'] ?? null,
            'next_label' => $next['label'] ?? null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @return array<int, array<string, mixed>>
     */
    private function syncChecklistTasks(
        int $examenId,
        array $detalle,
        ?int $leadId,
        ?int $projectId,
        array $checklist,
        ?int $usuarioId
    ): array {
        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($examenId, $companyId);
        $tasks = [];

        foreach ($checklist as $item) {
            $slug = $item['slug'] ?? null;
            if (!$slug) {
                continue;
            }

            $taskKey = $this->buildTaskKey($examenId, (string) $slug);
            $desiredStatus = !empty($item['completed']) ? 'completada' : 'pendiente';
            $task = $existingTasks[$taskKey] ?? null;

            if (!$task) {
                $task = $this->fetchChecklistTaskByKey($examenId, $companyId, $taskKey);
            }

            $payload = [
                'project_id' => $projectId,
                'entity_type' => 'examen',
                'entity_id' => (string) $examenId,
                'lead_id' => $leadId,
                'hc_number' => $detalle['hc_number'] ?? null,
                'patient_id' => $detalle['hc_number'] ?? null,
                'form_id' => $detalle['form_id'] ?? null,
                'source_module' => 'examenes',
                'source_ref_id' => (string) $examenId,
                'title' => $item['label'] ?? (string) $slug,
                'description' => 'Checklist de examen',
                'status' => $desiredStatus,
                'metadata' => [
                    'task_key' => $taskKey,
                    'checklist_slug' => (string) $slug,
                    'checklist_label' => $item['label'] ?? (string) $slug,
                ],
            ];

            if (!$task) {
                $task = $this->taskService->create($payload, $companyId, $usuarioId ?? 0);
            } else {
                $updatePayload = [
                    'status' => $desiredStatus,
                    'title' => $payload['title'],
                    'description' => $payload['description'],
                    'project_id' => $projectId,
                    'lead_id' => $leadId,
                    'metadata' => $payload['metadata'],
                ];

                $task = $this->taskService->update(
                    (int) $task['id'],
                    $companyId,
                    $updatePayload,
                    $usuarioId
                ) ?? $task;
            }

            if (!empty($task)) {
                $tasks[] = $this->formatChecklistTask($task, $taskKey);
            }
        }

        return $tasks;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchChecklistTasks(int $examenId, int $companyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                id,
                title,
                status,
                due_at,
                due_date,
                completed_at,
                metadata,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.task_key')) AS task_key
             FROM crm_tasks
             WHERE company_id = :company_id
               AND source_module = 'examenes'
               AND source_ref_id = :source_ref_id"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $examenId, PDO::PARAM_STR);
        $stmt->execute();

        $tasks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['task_key'] ?? null;
            $meta = null;
            if (!$key && !empty($row['metadata'])) {
                $meta = json_decode((string) $row['metadata'], true);
                if (is_array($meta) && !empty($meta['task_key'])) {
                    $key = (string) $meta['task_key'];
                }
            }

            if (!$key && is_array($meta) && !empty($meta['checklist_slug'])) {
                $key = $this->buildTaskKey($examenId, (string) $meta['checklist_slug']);
            }

            if (!$key) {
                continue;
            }

            $tasks[$key] = $row;
        }

        return $tasks;
    }

    private function fetchChecklistTaskByKey(int $examenId, int $companyId, string $taskKey): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                id,
                title,
                status,
                due_at,
                due_date,
                completed_at,
                metadata,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.task_key')) AS task_key
             FROM crm_tasks
             WHERE company_id = :company_id
               AND source_module = 'examenes'
               AND source_ref_id = :source_ref_id
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.task_key')) = :task_key
             LIMIT 1"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $examenId, PDO::PARAM_STR);
        $stmt->bindValue(':task_key', $taskKey, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buildTaskKey(int $examenId, string $slug): string
    {
        return 'examen:' . $examenId . ':kanban:' . $slug;
    }

    private function persistCrmMap(int $examenId, ?int $leadId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO examen_crm_detalles (examen_id, crm_lead_id)
             VALUES (:examen_id, :crm_lead_id)
             ON DUPLICATE KEY UPDATE
                crm_lead_id = VALUES(crm_lead_id)'
        );
        $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
        $stmt->bindValue(':crm_lead_id', $leadId, $leadId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function formatChecklistTask(array $task, string $taskKey): array
    {
        $checklistSlug = null;
        if (!empty($task['metadata'])) {
            $meta = json_decode((string) $task['metadata'], true);
            if (is_array($meta) && !empty($meta['checklist_slug'])) {
                $checklistSlug = (string) $meta['checklist_slug'];
            }
        }

        return [
            'id' => (int) ($task['id'] ?? 0),
            'title' => $task['title'] ?? '',
            'status' => $task['status'] ?? '',
            'task_key' => $taskKey,
            'checklist_slug' => $checklistSlug,
            'due_at' => $task['due_at'] ?? null,
            'due_date' => $task['due_date'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findChecklistStage(string $slug): ?array
    {
        foreach ($this->estadoService->getStages() as $stage) {
            if (($stage['slug'] ?? '') === $slug) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @return array<string, mixed>|null
     */
    private function resolveKanbanFromChecklist(array $checklist): ?array
    {
        if ($checklist === []) {
            return null;
        }

        usort(
            $checklist,
            static fn(array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0)
        );

        $selected = $checklist[0];
        foreach ($checklist as $item) {
            if (!($item['required'] ?? false) && empty($item['completed'])) {
                continue;
            }

            if (!empty($item['completed'])) {
                $selected = $item;
                continue;
            }

            if (!($item['required'] ?? false)) {
                continue;
            }

            break;
        }

        return [
            'slug' => $selected['slug'] ?? null,
            'label' => $selected['label'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $kanbanStage
     */
    private function actualizarEstadoExamenDesdeChecklist(
        int $examenId,
        array $kanbanStage,
        ?int $changedBy = null
    ): void
    {
        $slug = isset($kanbanStage['slug']) ? (string) $kanbanStage['slug'] : '';
        if ($slug === '') {
            return;
        }

        $labelMap = [
            'recibido' => 'Recibido',
            'llamado' => 'Llamado',
            'revision-cobertura' => 'Revision de cobertura',
            'listo-para-agenda' => 'Listo para agenda',
            'completado' => 'Completado',
        ];

        $estado = $labelMap[$slug] ?? (string) ($kanbanStage['label'] ?? $slug);
        $stmtPrevio = $this->pdo->prepare('SELECT estado FROM consulta_examenes WHERE id = :id LIMIT 1');
        $stmtPrevio->bindValue(':id', $examenId, PDO::PARAM_INT);
        $stmtPrevio->execute();
        $estadoAnterior = $stmtPrevio->fetchColumn();
        $estadoAnterior = $estadoAnterior !== false ? trim((string) $estadoAnterior) : null;

        if ($estadoAnterior !== null && strcasecmp($estadoAnterior, $estado) === 0) {
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE consulta_examenes SET estado = :estado WHERE id = :id');
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $examenId, PDO::PARAM_INT);
        $stmt->execute();

        $this->registrarCambioEstadoChecklist($examenId, $estadoAnterior, $estado, $changedBy);
    }

    private function registrarCambioEstadoChecklist(
        int $examenId,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?int $changedBy = null
    ): void {
        $nuevo = trim($estadoNuevo);
        if ($nuevo === '') {
            return;
        }

        $anterior = $estadoAnterior !== null ? trim($estadoAnterior) : null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO examen_estado_log
                    (examen_id, estado_anterior, estado_nuevo, changed_by, origen, observacion)
                 VALUES
                    (:examen_id, :estado_anterior, :estado_nuevo, :changed_by, :origen, :observacion)'
            );
            $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
            $stmt->bindValue(':estado_anterior', $anterior !== '' ? $anterior : null, $anterior !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':estado_nuevo', $nuevo, PDO::PARAM_STR);
            $stmt->bindValue(':changed_by', $changedBy, $changedBy !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':origen', 'crm_checklist', PDO::PARAM_STR);
            $stmt->bindValue(':observacion', 'Actualización desde checklist de CRM', PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('No se pudo registrar examen_estado_log (checklist) para examen #' . $examenId . ': ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $detalle
     * @return array<string, mixed>|null
     */
    private function ensureCrmProject(int $examenId, array $detalle, ?int $leadId, ?int $userId): ?array
    {
        try {
            $paciente = trim((string) ($detalle['paciente_nombre'] ?? ''));
            $titulo = 'Examen #' . $examenId;
            if ($paciente !== '') {
                $titulo .= ' · ' . $paciente;
            }

            $payload = [
                'title' => $titulo,
                'description' => $detalle['examen_nombre'] ?? null,
                'owner_id' => !empty($detalle['crm_responsable_id']) ? (int) $detalle['crm_responsable_id'] : null,
                'lead_id' => $leadId,
                'hc_number' => $detalle['hc_number'] ?? null,
                'form_id' => $detalle['form_id'] ?? null,
                'source_module' => 'examenes',
                'source_ref_id' => (string) $examenId,
            ];

            return $this->projectService->linkFromSource($payload, $userId ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCompanyId(): int
    {
        return isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
    }
}
