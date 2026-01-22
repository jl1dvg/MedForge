<?php

namespace Modules\Solicitudes\Services;

use DateTimeImmutable;
use Modules\CRM\Models\LeadModel;
use Modules\CRM\Models\TaskModel;
use Modules\CRM\Services\CrmProjectService;
use Modules\CRM\Services\CrmTaskService;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\CRM\Services\LeadCrmCoreService;
use Modules\WhatsApp\Services\Messenger as WhatsAppMessenger;
use Modules\WhatsApp\WhatsAppModule;
use Modules\Solicitudes\Services\CalendarBlockService;
use Modules\Solicitudes\Services\SolicitudEstadoService;
use Helpers\JsonLogger;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class SolicitudCrmService
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
    private CalendarBlockService $calendarBlocks;
    private SolicitudEstadoService $estadoService;

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
        $this->calendarBlocks = new CalendarBlockService($pdo);
        $this->estadoService = new SolicitudEstadoService($pdo);
    }

    public function obtenerResponsables(): array
    {
        return $this->leadConfig->getAssignableUsers();
    }

    public function obtenerFuentes(): array
    {
        return $this->leadConfig->getSources();
    }

    public function obtenerResumen(int $solicitudId): array
    {
        try {
            $detalle = $this->obtenerDetalleSolicitud($solicitudId);
            if (!$detalle) {
                throw new RuntimeException('Solicitud no encontrada');
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
                    $crmResumen = $this->crmCore->getResumen((int) $lead['id'], LeadCrmCoreService::CONTEXT_SOLICITUD, $solicitudId);
                }
            }

            $project = $this->ensureCrmProject(
                $solicitudId,
                $detalle,
                !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null,
                null
            );

            return [
                'detalle' => $detalle,
                'notas' => $this->obtenerNotas($solicitudId),
                'adjuntos' => $this->obtenerAdjuntos($solicitudId),
                'tareas' => $this->obtenerTareas($solicitudId),
                'campos_personalizados' => $this->obtenerCamposPersonalizados($solicitudId),
                'lead' => $lead,
                'crm_resumen' => $crmResumen,
                'project' => $project,
                'bloqueos_agenda' => $this->calendarBlocks->listarPorSolicitud($solicitudId),
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo obtener el detalle CRM: ' . ($exception->getMessage() ?: get_class($exception)),
                0,
                $exception
            );
        }
    }

    public function registrarBloqueoAgenda(int $solicitudId, array $payload, ?int $usuarioId = null): array
    {
        $bloqueo = $this->calendarBlocks->registrar($solicitudId, $payload, $usuarioId);
        $resumen = $this->obtenerResumen($solicitudId);
        $resumen['ultimo_bloqueo'] = $bloqueo;

        return $resumen;
    }

    public function guardarDetalles(int $solicitudId, array $data, ?int $usuarioId = null): void
    {
        $detalleActual = $this->obtenerDetalleSolicitud($solicitudId);
        if (!$detalleActual) {
            throw new RuntimeException('Solicitud no encontrada');
        }

        $responsableId = isset($data['responsable_id']) && $data['responsable_id'] !== ''
            ? (int) $data['responsable_id']
            : null;

        $etapa = $this->normalizarEtapa($data['pipeline_stage'] ?? null);
        $fuente = $this->normalizarTexto($data['fuente'] ?? null);
        $contactoEmail = $this->normalizarTexto($data['contacto_email'] ?? null);
        $contactoTelefono = $this->normalizarTexto($data['contacto_telefono'] ?? null);
        $seguidores = $this->normalizarSeguidores($data['seguidores'] ?? []);

        $crmLeadId = $detalleActual['crm_lead_id'] ?? null;
        try {
            $crmLeadId = $this->sincronizarLead(
                $solicitudId,
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
        } catch (Throwable $exception) {
            error_log('CRM ▶ No se pudo sincronizar el lead: ' . ($exception->getMessage() ?: get_class($exception)));
        }

        $jsonSeguidores = !empty($seguidores) ? json_encode($seguidores, JSON_UNESCAPED_UNICODE) : null;

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO solicitud_crm_detalles (solicitud_id, crm_lead_id, crm_project_id, responsable_id, pipeline_stage, fuente, contacto_email, contacto_telefono, followers)
                 VALUES (:solicitud_id, :crm_lead_id, :crm_project_id, :responsable_id, :pipeline_stage, :fuente, :contacto_email, :contacto_telefono, :followers)
                 ON DUPLICATE KEY UPDATE
                    crm_lead_id = VALUES(crm_lead_id),
                    crm_project_id = VALUES(crm_project_id),
                    responsable_id = VALUES(responsable_id),
                    pipeline_stage = VALUES(pipeline_stage),
                    fuente = VALUES(fuente),
                    contacto_email = VALUES(contacto_email),
                    contacto_telefono = VALUES(contacto_telefono),
                    followers = VALUES(followers)'
            );

            $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
            $stmt->bindValue(':crm_lead_id', $crmLeadId, $crmLeadId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(
                ':crm_project_id',
                !empty($detalleActual['crm_project_id']) ? (int) $detalleActual['crm_project_id'] : null,
                !empty($detalleActual['crm_project_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $stmt->bindValue(':responsable_id', $responsableId, $responsableId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':pipeline_stage', $etapa);
            $stmt->bindValue(':fuente', $fuente, $fuente !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':contacto_email', $contactoEmail, $contactoEmail !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':contacto_telefono', $contactoTelefono, $contactoTelefono !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':followers', $jsonSeguidores, $jsonSeguidores !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();

            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $this->guardarCamposPersonalizados($solicitudId, $data['custom_fields']);
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $detallePosterior = $this->safeObtenerDetalleSolicitud($solicitudId);
        $this->notifyWhatsAppEvent(
            $solicitudId,
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

    public function registrarNota(int $solicitudId, string $nota, ?int $autorId): void
    {
        $nota = trim(strip_tags($nota));
        if ($nota === '') {
            throw new RuntimeException('La nota no puede estar vacía');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_notas (solicitud_id, autor_id, nota) VALUES (:solicitud_id, :autor_id, :nota)'
        );
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':autor_id', $autorId, $autorId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nota', $nota, PDO::PARAM_STR);
        $stmt->execute();

        $this->notifyWhatsAppEvent(
            $solicitudId,
            'note_added',
            [
                'nota' => $nota,
                'autor_id' => $autorId,
                'autor_nombre' => $this->obtenerNombreUsuario($autorId),
            ]
        );
    }

    /**
     * @return array{name?:string,email?:string,hc_number?:string,descripcion?:string}|null
     */
    public function obtenerContactoPaciente(int $solicitudId): ?array
    {
        $detalle = $this->safeObtenerDetalleSolicitud($solicitudId);
        if ($detalle === null) {
            return null;
        }

        $context = array_filter([
            'name' => $this->normalizarTexto($detalle['paciente_nombre'] ?? null),
            'email' => $this->normalizarTexto($detalle['crm_contacto_email'] ?? null),
            'hc_number' => $this->normalizarTexto($detalle['hc_number'] ?? null),
            'descripcion' => $this->normalizarTexto($detalle['procedimiento'] ?? null),
        ], static fn($value) => $value !== null && $value !== '');

        return $context !== [] ? $context : null;
    }

    public function registrarTarea(int $solicitudId, array $data, ?int $autorId): void
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
        $detalle = $this->safeObtenerDetalleSolicitud($solicitudId) ?? [];
        $leadId = !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null;
        $hcNumber = $this->normalizarTexto($detalle['hc_number'] ?? null);
        $project = $this->ensureCrmProject($solicitudId, $detalle, $leadId, $autorId);

        $tarea = $this->taskService->create(
            [
                'project_id' => $project['id'] ?? null,
                'entity_type' => 'solicitud',
                'entity_id' => (string) $solicitudId,
                'lead_id' => $leadId,
                'hc_number' => $hcNumber,
                'patient_id' => $hcNumber,
                'source_module' => 'solicitudes',
                'source_ref_id' => (string) $solicitudId,
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
            $solicitudId,
            'task_created',
            [
                'tarea' => $tareaContexto,
                'autor_id' => $autorId,
                'autor_nombre' => $this->obtenerNombreUsuario($autorId),
            ]
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function completarCoberturaMailTask(int $solicitudId, ?int $usuarioId, array $metadata = []): void
    {
        $detalle = $this->safeObtenerDetalleSolicitud($solicitudId) ?? [];
        $leadId = !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null;
        $project = $this->ensureCrmProject($solicitudId, $detalle, $leadId, $usuarioId);
        $projectId = isset($project['id']) ? (int) $project['id'] : null;
        $companyId = $this->resolveCompanyId();
        $taskKey = $this->buildTaskKey($solicitudId, 'cobertura-mail');

        JsonLogger::log(
            'crm',
            'CRM ▶ Completar tarea de cobertura por correo',
            null,
            [
                'solicitud_id' => $solicitudId,
                'company_id' => $companyId,
                'task_key' => $taskKey,
                'user_id' => $usuarioId,
            ]
        );

        $task = $this->fetchChecklistTaskByKey($solicitudId, $companyId, $taskKey);
        if (!$task) {
            $task = $this->fetchTaskByTitle($solicitudId, $companyId, 'Solicitar cobertura');
        }
        if (!$task) {
            $revisionKey = $this->buildTaskKey($solicitudId, 'revision-codigos');
            $task = $this->fetchChecklistTaskByKey($solicitudId, $companyId, $revisionKey);
        }
        if (!$task) {
            $task = $this->fetchTaskByTitleLike($solicitudId, $companyId, 'cobertura');
        }

        $payloadMetadata = array_filter(
            array_merge(
                [
                    'task_key' => $taskKey,
                    'context' => 'cobertura_mail',
                ],
                $metadata
            ),
            static fn($value) => $value !== null && $value !== ''
        );

        if ($task) {
            $updated = $this->taskService->update(
                (int) $task['id'],
                $companyId,
                [
                    'status' => 'completada',
                    'title' => 'Solicitar cobertura',
                    'description' => 'Correo de cobertura enviado.',
                    'project_id' => $projectId,
                    'lead_id' => $leadId,
                    'metadata' => $payloadMetadata,
                ],
                $usuarioId
            );
            JsonLogger::log(
                'crm',
                'CRM ▶ Tarea de cobertura actualizada',
                null,
                [
                    'solicitud_id' => $solicitudId,
                    'company_id' => $companyId,
                    'task_id' => (int) $task['id'],
                    'updated' => $updated !== null,
                ]
            );
            return;
        }

        $created = $this->taskService->create(
            [
                'project_id' => $projectId,
                'entity_type' => 'solicitud',
                'entity_id' => (string) $solicitudId,
                'lead_id' => $leadId,
                'hc_number' => $detalle['hc_number'] ?? null,
                'patient_id' => $detalle['hc_number'] ?? null,
                'form_id' => $detalle['form_id'] ?? null,
                'source_module' => 'solicitudes',
                'source_ref_id' => (string) $solicitudId,
                'title' => 'Solicitar cobertura',
                'description' => 'Correo de cobertura enviado.',
                'status' => 'completada',
                'assigned_to' => !empty($detalle['crm_responsable_id']) ? (int) $detalle['crm_responsable_id'] : null,
                'metadata' => $payloadMetadata,
            ],
            $companyId,
            $usuarioId ?? 0
        );
        JsonLogger::log(
            'crm',
            'CRM ▶ Tarea de cobertura creada',
            null,
            [
                'solicitud_id' => $solicitudId,
                'company_id' => $companyId,
                'task_id' => (int) ($created['id'] ?? 0),
            ]
        );
    }

    public function actualizarEstadoTarea(int $solicitudId, int $tareaId, string $estado): void
    {
        $estadoNormalizado = $this->normalizarEstadoTarea($estado);
        $companyId = $this->resolveCompanyId();
        $task = $this->taskModel->find($tareaId, $companyId);
        if (!$task || ($task['source_module'] ?? null) !== 'solicitudes' || (string) ($task['source_ref_id'] ?? '') !== (string) $solicitudId) {
            return;
        }

        $this->taskService->update($tareaId, $companyId, ['status' => $estadoNormalizado]);

        $tarea = $this->obtenerTareaPorId($solicitudId, $tareaId);
        if ($tarea !== null) {
            $this->notifyWhatsAppEvent(
                $solicitudId,
                'task_status_updated',
                [
                    'tarea' => $tarea,
                ]
            );
        }
    }

    public function registrarAdjunto(
        int $solicitudId,
        string $nombreOriginal,
        string $rutaRelativa,
        ?string $mimeType,
        ?int $tamano,
        ?int $usuarioId,
        ?string $descripcion = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_adjuntos (solicitud_id, nombre_original, ruta_relativa, mime_type, tamano_bytes, descripcion, subido_por)
             VALUES (:solicitud_id, :nombre_original, :ruta_relativa, :mime_type, :tamano_bytes, :descripcion, :subido_por)'
        );

        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre_original', $nombreOriginal, PDO::PARAM_STR);
        $stmt->bindValue(':ruta_relativa', $rutaRelativa, PDO::PARAM_STR);
        $stmt->bindValue(':mime_type', $mimeType, $mimeType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tamano_bytes', $tamano, $tamano !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':subido_por', $usuarioId, $usuarioId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        $this->notifyWhatsAppEvent(
            $solicitudId,
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
    private function notifyWhatsAppEvent(int $solicitudId, string $evento, array $contexto = []): void
    {
        if (!$this->whatsapp->isEnabled()) {
            return;
        }

        $detalle = $contexto['detalle'] ?? $this->safeObtenerDetalleSolicitud($solicitudId);
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
        $solicitudId = isset($detalle['id']) ? (int) $detalle['id'] : 0;
        $paciente = trim((string) ($detalle['paciente_nombre'] ?? ''));
        $marca = $this->whatsapp->getBrandName();
        $tituloSolicitud = $solicitudId > 0
            ? 'Solicitud #' . $solicitudId . ($paciente !== '' ? ' · ' . $paciente : '')
            : ($paciente !== '' ? $paciente : 'Solicitud CRM');

        switch ($evento) {
            case 'details_updated':
                $actual = $detalle['crm_pipeline_stage'] ?? ($detalle['pipeline_stage'] ?? null);
                $anterior = $contexto['detalle_anterior']['crm_pipeline_stage'] ?? null;
                $responsable = $detalle['crm_responsable_nombre'] ?? null;
                $lineas = [
                    '🔄 Actualización CRM - ' . $marca,
                    $tituloSolicitud,
                ];

                if (!empty($detalle['procedimiento'])) {
                    $lineas[] = 'Procedimiento: ' . $detalle['procedimiento'];
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

                $lineas[] = 'Ver detalle: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'note_added':
                $nota = trim((string) ($contexto['nota'] ?? ''));
                $autor = trim((string) ($contexto['autor_nombre'] ?? ''));
                $lineas = [
                    '📝 Nueva nota en CRM - ' . $marca,
                    $tituloSolicitud,
                ];
                if ($autor !== '') {
                    $lineas[] = 'Autor: ' . $autor;
                }
                if ($nota !== '') {
                    $lineas[] = 'Nota: ' . $this->truncateText($nota, 320);
                }
                $lineas[] = 'Revisa el historial: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'task_created':
                $tarea = $contexto['tarea'] ?? [];
                $lineas = [
                    '✅ Nueva tarea CRM - ' . $marca,
                    $tituloSolicitud,
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
                $lineas[] = 'Gestiona la tarea: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'task_status_updated':
                $tarea = $contexto['tarea'] ?? [];
                $lineas = [
                    '📌 Actualización de tarea CRM - ' . $marca,
                    $tituloSolicitud,
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
                $lineas[] = 'Ver tablero: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'attachment_uploaded':
                $adjunto = $contexto['adjunto'] ?? [];
                $autorAdjunto = trim((string) ($contexto['usuario_nombre'] ?? ''));
                $lineas = [
                    '📎 Nuevo adjunto en CRM - ' . $marca,
                    $tituloSolicitud,
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
                $lineas[] = 'Consulta los documentos: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            default:
                return '';
        }
    }

    private function buildSolicitudUrl(int $solicitudId): string
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        $path = '/solicitudes/' . $solicitudId . '/crm';

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

    private function obtenerTareaPorId(int $solicitudId, int $tareaId): ?array
    {
        $companyId = $this->resolveCompanyId();
        $stmt = $this->pdo->prepare(
            'SELECT id, title AS titulo, description AS descripcion, status AS estado, assigned_to, due_date, due_at, remind_at, completed_at'
            . ' FROM crm_tasks WHERE id = :id AND company_id = :company_id AND source_module = "solicitudes" AND source_ref_id = :source_ref_id LIMIT 1'
        );
        $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $solicitudId, PDO::PARAM_STR);
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

    private function safeObtenerDetalleSolicitud(int $solicitudId): ?array
    {
        try {
            return $this->obtenerDetalleSolicitud($solicitudId);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function obtenerDetalleSolicitud(int $solicitudId): ?array
    {
        $sql = <<<'SQL'
            SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                sp.estado,
                sp.prioridad,
                sp.doctor,
                sp.procedimiento,
                sp.ojo,
                sp.created_at,
                sp.observacion,
                sp.turno,
                cd.fecha AS fecha_consulta,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                CONCAT(TRIM(pd.fname), ' ', TRIM(pd.mname), ' ', TRIM(pd.lname), ' ', TRIM(pd.lname2)) AS paciente_nombre,
                detalles.crm_lead_id AS crm_lead_id,
                detalles.crm_project_id AS crm_project_id,
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
                      AND LOWER(TRIM(sp.doctor)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
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
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN crm_leads cl ON detalles.crm_lead_id = cl.id
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
                SELECT source_ref_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN status <> 'completada' THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN status <> 'completada' THEN COALESCE(due_at, CONCAT(due_date, " 23:59:59")) END) AS proximo_vencimiento
                FROM crm_tasks
                WHERE source_module = 'solicitudes'
                  AND company_id = :company_id
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = sp.id
            WHERE sp.id = :solicitud_id
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':solicitud_id' => $solicitudId,
            ':company_id' => $this->resolveCompanyId(),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (empty($row['paciente_nombre']) || empty($row['hc_number'])) {
            throw new RuntimeException('Solicitud incompleta: datos de paciente no encontrados');
        }

        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        $row['crm_pipeline_stage'] = $this->normalizarEtapa($row['crm_pipeline_stage'] ?? null);

        $row['seguidores'] = $this->decodificarSeguidores($row['crm_followers'] ?? null);
        unset($row['crm_followers']);

        $row['dias_en_estado'] = $this->calcularDiasEnEstado($row['created_at'] ?? null);

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $userPermissions
     * @return array<string, mixed>
     */
    public function bootstrapChecklist(int $solicitudId, array $payload, ?int $usuarioId, array $userPermissions = []): array
    {
        $detalle = $this->obtenerDetalleSolicitud($solicitudId);
        if (!$detalle) {
            throw new RuntimeException('Solicitud no encontrada', 404);
        }

        $hcNumber = $this->normalizarTexto($payload['hc_number'] ?? $detalle['hc_number'] ?? null);
        if (!$hcNumber) {
            throw new RuntimeException('Falta el número de historia clínica para sincronizar el CRM', 422);
        }

        $leadId = $this->sincronizarLead(
            $solicitudId,
            $detalle,
            [
                'hc_number' => $hcNumber,
                'crm_lead_id' => $detalle['crm_lead_id'] ?? null,
                'etapa' => $detalle['crm_pipeline_stage'] ?? null,
            ],
            $usuarioId
        );

        $project = $this->ensureCrmProject($solicitudId, $detalle, $leadId, $usuarioId);
        $projectId = isset($project['id']) ? (int) $project['id'] : null;

        $this->persistCrmMap($solicitudId, $leadId, $projectId);

        $enriched = $this->estadoService->enrichSolicitudes(
            [
                [
                    'id' => $solicitudId,
                    'estado' => $detalle['estado'] ?? '',
                ],
            ],
            $userPermissions
        );
        $checklist = $enriched[0]['checklist'] ?? [];
        $progress = $enriched[0]['checklist_progress'] ?? [];

        $tasks = $this->syncChecklistTasks($solicitudId, $detalle, $leadId, $projectId, $checklist, $usuarioId);
        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($solicitudId, $companyId);
        $checklist = $this->syncChecklistFromTasks($solicitudId, $checklist, $existingTasks, $usuarioId, $userPermissions);
        $refreshed = $this->estadoService->enrichSolicitudes(
            [
                [
                    'id' => $solicitudId,
                    'estado' => $detalle['estado'] ?? '',
                ],
            ],
            $userPermissions
        );
        $checklist = $refreshed[0]['checklist'] ?? $checklist;
        $progress = $refreshed[0]['checklist_progress'] ?? $progress;

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
    public function checklistState(int $solicitudId, array $userPermissions = []): array
    {
        $detalle = $this->obtenerDetalleSolicitud($solicitudId);
        if (!$detalle) {
            throw new RuntimeException('Solicitud no encontrada', 404);
        }

        $enriched = $this->estadoService->enrichSolicitudes(
            [
                [
                    'id' => $solicitudId,
                    'estado' => $detalle['estado'] ?? '',
                ],
            ],
            $userPermissions
        );
        $checklist = $enriched[0]['checklist'] ?? [];
        $progress = $enriched[0]['checklist_progress'] ?? [];

        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($solicitudId, $companyId);
        $tasks = [];
        foreach ($existingTasks as $taskKey => $task) {
            $tasks[] = $this->formatChecklistTask($task, (string) $taskKey);
        }

        $progress = $this->computeChecklistProgress($checklist, $progress);

        return [
            'lead_id' => !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null,
            'project_id' => !empty($detalle['crm_project_id']) ? (int) $detalle['crm_project_id'] : null,
            'tasks' => $tasks,
            'checklist' => $checklist,
            'checklist_progress' => $progress,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @return array<string, mixed>
     */
    public function syncChecklistStage(
        int $solicitudId,
        string $etapaSlug,
        bool $completado,
        ?int $usuarioId,
        array $userPermissions = []
    ): array {
        $resultado = $this->estadoService->actualizarEtapa(
            $solicitudId,
            $etapaSlug,
            $completado,
            $usuarioId,
            $userPermissions
        );

        $detalle = $this->safeObtenerDetalleSolicitud($solicitudId) ?? [];
        $leadId = !empty($detalle['crm_lead_id']) ? (int) $detalle['crm_lead_id'] : null;
        $project = $this->ensureCrmProject($solicitudId, $detalle, $leadId, $usuarioId);
        $projectId = isset($project['id']) ? (int) $project['id'] : null;

        if ($leadId || $projectId) {
            $this->persistCrmMap($solicitudId, $leadId, $projectId);
        }

        $checklist = $resultado['checklist'] ?? [];
        $tasks = $this->syncChecklistTasks($solicitudId, $detalle, $leadId, $projectId, $checklist, $usuarioId);

        return $resultado + [
            'lead_id' => $leadId,
            'project_id' => $projectId,
            'tasks' => $tasks,
        ];
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

    private function sincronizarLead(int $solicitudId, array $detalle, array $payload, ?int $usuarioId): int
    {
        $leadId = null;
        $hcNumber = $this->normalizarTexto($payload['hc_number'] ?? ($detalle['hc_number'] ?? null));

        if (!empty($payload['crm_lead_id'])) {
            $leadId = (int) $payload['crm_lead_id'];
        } elseif (!empty($detalle['crm_lead_id'])) {
            $leadId = (int) $detalle['crm_lead_id'];
        }

        if ($leadId) {
            $existente = $this->leadModel->findById($leadId);
            if (!$existente) {
                $leadId = null;
            } elseif (!$hcNumber && !empty($existente['hc_number'])) {
                $hcNumber = (string) $existente['hc_number'];
            }
        }

        if (!$hcNumber) {
            throw new RuntimeException(
                'No se pudo asociar la solicitud con el CRM: falta el número de historia clínica del paciente.',
                422
            );
        }

        $nombre = trim((string) ($detalle['paciente_nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Solicitud #' . $solicitudId;
        }

        $leadData = [
            'name' => $nombre,
            'email' => $payload['contacto_email'] ?? ($detalle['crm_contacto_email'] ?? null),
            'phone' => $payload['contacto_telefono'] ?? ($detalle['crm_contacto_telefono'] ?? $detalle['paciente_celular'] ?? null),
            'source' => $payload['fuente'] ?? ($detalle['crm_fuente'] ?? null),
            'assigned_to' => $payload['responsable_id'] ?? ($detalle['crm_responsable_id'] ?? null),
            'status' => $this->mapearEtapaALeadStatus($payload['etapa'] ?? ($detalle['crm_pipeline_stage'] ?? null)),
            'notes' => $detalle['observacion'] ?? null,
        ];

        $lead = $this->crmCore->saveLeadFromContext(
            LeadCrmCoreService::CONTEXT_SOLICITUD,
            $solicitudId,
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

    private function obtenerNotas(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.nota, n.created_at, n.autor_id, u.nombre AS autor_nombre
             FROM solicitud_crm_notas n
             LEFT JOIN users u ON n.autor_id = u.id
             WHERE n.solicitud_id = :solicitud_id
             ORDER BY n.created_at DESC
             LIMIT 100'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerAdjuntos(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.nombre_original, a.ruta_relativa, a.mime_type, a.tamano_bytes, a.descripcion, a.created_at, a.subido_por, u.nombre AS subido_por_nombre
             FROM solicitud_crm_adjuntos a
             LEFT JOIN users u ON a.subido_por = u.id
             WHERE a.solicitud_id = :solicitud_id
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

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

    private function obtenerTareas(int $solicitudId): array
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
               AND t.source_module = "solicitudes"
               AND t.source_ref_id = :source_ref_id
             ORDER BY
                CASE WHEN t.status IN ("pendiente", "en_progreso", "en_proceso") THEN 0 ELSE 1 END,
                COALESCE(t.due_date, DATE(t.due_at)) IS NULL,
                COALESCE(t.due_date, DATE(t.due_at)) ASC,
                t.created_at DESC'
        );
        $stmt->execute([
            ':company_id' => $companyId,
            ':source_ref_id' => (string) $solicitudId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerCamposPersonalizados(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, meta_key, meta_value, meta_type, created_at, updated_at
             FROM solicitud_crm_meta
             WHERE solicitud_id = :solicitud_id
             ORDER BY meta_key'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

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

    private function guardarCamposPersonalizados(int $solicitudId, array $campos): void
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

        $stmtDelete = $this->pdo->prepare('DELETE FROM solicitud_crm_meta WHERE solicitud_id = :solicitud_id');
        $stmtDelete->execute([':solicitud_id' => $solicitudId]);

        if (empty($limpios)) {
            return;
        }

        $stmtInsert = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_meta (solicitud_id, meta_key, meta_value, meta_type)
             VALUES (:solicitud_id, :meta_key, :meta_value, :meta_type)'
        );

        foreach ($limpios as $key => $info) {
            $stmtInsert->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
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
     * @param array<string, mixed> $detalle
     * @return array<string, mixed>|null
     */
    private function ensureCrmProject(int $solicitudId, array $detalle, ?int $leadId, ?int $userId): ?array
    {
        try {
            $paciente = trim((string) ($detalle['paciente_nombre'] ?? ''));
            $titulo = 'Solicitud #' . $solicitudId;
            if ($paciente !== '') {
                $titulo .= ' · ' . $paciente;
            }

            $payload = [
                'title' => $titulo,
                'description' => $detalle['procedimiento'] ?? null,
                'owner_id' => !empty($detalle['crm_responsable_id']) ? (int) $detalle['crm_responsable_id'] : null,
                'lead_id' => $leadId,
                'hc_number' => $detalle['hc_number'] ?? null,
                'form_id' => $detalle['form_id'] ?? null,
                'source_module' => 'solicitudes',
                'source_ref_id' => (string) $solicitudId,
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

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @param array<string, array<string, mixed>> $existingTasks
     * @return array<int, array<string, mixed>>
     */
    private function syncChecklistFromTasks(
        int $solicitudId,
        array $checklist,
        array $existingTasks,
        ?int $usuarioId,
        array $userPermissions
    ): array {
        $needsRefresh = false;

        foreach ($checklist as $item) {
            $slug = $item['slug'] ?? null;
            if (!$slug) {
                continue;
            }

            $key = $this->buildTaskKey($solicitudId, $slug);
            $task = $existingTasks[$key] ?? null;
            if (!$task) {
                continue;
            }

            $taskCompleted = ($task['status'] ?? '') === 'completada';
            if ($taskCompleted && empty($item['completed'])) {
                $this->estadoService->actualizarEtapa(
                    $solicitudId,
                    $slug,
                    true,
                    $usuarioId,
                    $userPermissions,
                    true
                );
                $needsRefresh = true;
            }
        }

        if (!$needsRefresh) {
            return $checklist;
        }

        $detalle = $this->safeObtenerDetalleSolicitud($solicitudId) ?? [];
        $enriched = $this->estadoService->enrichSolicitudes(
            [
                [
                    'id' => $solicitudId,
                    'estado' => $detalle['estado'] ?? '',
                ],
            ],
            $userPermissions
        );

        return $enriched[0]['checklist'] ?? $checklist;
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
        int $solicitudId,
        array $detalle,
        ?int $leadId,
        ?int $projectId,
        array $checklist,
        ?int $usuarioId
    ): array {
        $companyId = $this->resolveCompanyId();
        $existingTasks = $this->fetchChecklistTasks($solicitudId, $companyId);
        $tasks = [];

        foreach ($checklist as $item) {
            $slug = $item['slug'] ?? null;
            if (!$slug) {
                continue;
            }

            $taskKey = $this->buildTaskKey($solicitudId, $slug);
            $desiredStatus = !empty($item['completed']) ? 'completada' : 'pendiente';
            $task = $existingTasks[$taskKey] ?? null;

            if (!$task) {
                $task = $this->fetchChecklistTaskByKey($solicitudId, $companyId, $taskKey);
            }

            $payload = [
                'project_id' => $projectId,
                'entity_type' => 'solicitud',
                'entity_id' => (string) $solicitudId,
                'lead_id' => $leadId,
                'hc_number' => $detalle['hc_number'] ?? null,
                'patient_id' => $detalle['hc_number'] ?? null,
                'form_id' => $detalle['form_id'] ?? null,
                'source_module' => 'solicitudes',
                'source_ref_id' => (string) $solicitudId,
                'title' => $item['label'] ?? $slug,
                'description' => 'Checklist de solicitud',
                'status' => $desiredStatus,
                'metadata' => [
                    'task_key' => $taskKey,
                    'checklist_slug' => $slug,
                    'checklist_label' => $item['label'] ?? $slug,
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
    private function fetchChecklistTasks(int $solicitudId, int $companyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, status, due_at, due_date, metadata, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.task_key')) AS task_key
             FROM crm_tasks
             WHERE company_id = :company_id AND source_module = 'solicitudes' AND source_ref_id = :source_ref_id"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $solicitudId, PDO::PARAM_STR);
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
                $key = $this->buildTaskKey($solicitudId, (string) $meta['checklist_slug']);
            }

            if (!$key) {
                continue;
            }

            $tasks[$key] = $row;
        }

        return $tasks;
    }

    private function fetchChecklistTaskByKey(int $solicitudId, int $companyId, string $taskKey): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, status, due_at, due_date, metadata, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.task_key')) AS task_key
             FROM crm_tasks
             WHERE company_id = :company_id
               AND source_module = 'solicitudes'
               AND source_ref_id = :source_ref_id
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.task_key')) = :task_key
             LIMIT 1"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $solicitudId, PDO::PARAM_STR);
        $stmt->bindValue(':task_key', $taskKey, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchTaskByTitle(int $solicitudId, int $companyId, string $title): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, status, metadata
             FROM crm_tasks
             WHERE company_id = :company_id
               AND source_module = 'solicitudes'
               AND source_ref_id = :source_ref_id
               AND LOWER(title) = LOWER(:title)
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $solicitudId, PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchTaskByTitleLike(int $solicitudId, int $companyId, string $needle): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, status, metadata
             FROM crm_tasks
             WHERE company_id = :company_id
               AND source_module = 'solicitudes'
               AND source_ref_id = :source_ref_id
               AND LOWER(title) LIKE :needle
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':source_ref_id', (string) $solicitudId, PDO::PARAM_STR);
        $stmt->bindValue(':needle', '%' . strtolower($needle) . '%', PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buildTaskKey(int $solicitudId, string $slug): string
    {
        return 'solicitud:' . $solicitudId . ':kanban:' . $slug;
    }

    private function persistCrmMap(int $solicitudId, ?int $leadId, ?int $projectId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_detalles (solicitud_id, crm_lead_id, crm_project_id)
             VALUES (:solicitud_id, :crm_lead_id, :crm_project_id)
             ON DUPLICATE KEY UPDATE
                crm_lead_id = VALUES(crm_lead_id),
                crm_project_id = VALUES(crm_project_id)'
        );
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':crm_lead_id', $leadId, $leadId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':crm_project_id', $projectId, $projectId ? PDO::PARAM_INT : PDO::PARAM_NULL);
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
}
