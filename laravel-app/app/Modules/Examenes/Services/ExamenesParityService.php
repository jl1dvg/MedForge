<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use App\Events\Crm\ExamenEstadoCambiado;
use App\Events\Crm\ExamenSolicitado;
use App\Models\WhatsappConversation;
use App\Modules\Shared\Support\AfiliacionDimensionService;
use App\Modules\Whatsapp\Services\ConversationWriteService;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Services\LeadConfigurationService;
use Models\ExamenModel;
use Modules\Examenes\Services\ExamenCrmService;
use Modules\Examenes\Services\ExamenEstadoService;
use Modules\Examenes\Services\ExamenMailLogService;
use Modules\Notifications\Services\PusherConfigService;
use PDO;
use RuntimeException;
use Throwable;

class ExamenesParityService
{
    private const PUSHER_CHANNEL = 'examenes-kanban';
    private const STORAGE_PATH = 'uploads/examenes';

    private static bool $legacyAutoloaderRegistered = false;

    private ExamenModel $examenModel;

    private ExamenEstadoService $estadoService;

    private LeadConfigurationService $leadConfig;

    private PusherConfigService $pusherConfig;
    private ExamenCrmService $crmService;
    private AfiliacionDimensionService $afiliacionDimensions;
    private ConversationWriteService $whatsappWriter;
    private ExamenMailLogService $mailLogService;

    public function __construct(private readonly PDO $db)
    {
        $this->ensureLegacyClassAutoloading();

        $this->examenModel = new ExamenModel($this->db);
        $this->estadoService = new ExamenEstadoService();
        $this->leadConfig = new LeadConfigurationService($this->db);
        $this->pusherConfig = new PusherConfigService($this->db);
        $this->crmService = new ExamenCrmService($this->db);
        $this->afiliacionDimensions = app(AfiliacionDimensionService::class);
        $this->whatsappWriter = new ConversationWriteService();
        $this->mailLogService = new ExamenMailLogService($this->db);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function kanbanData(array $payload): array
    {
        $filtros = $this->sanitizeKanbanFilters($payload);
        if ($filtros['fechaTexto'] === '' && $filtros['date_from'] === null && $filtros['date_to'] === null) {
            $today = new DateTimeImmutable('today');
            $filtros['date_from'] = $today->sub(new DateInterval('P30D'))->format('Y-m-d');
            $filtros['date_to'] = $today->format('Y-m-d');
        }

        $kanbanPreferences = [
            'sort' => 'fecha_desc',
            'column_limit' => 0,
        ];
        $pipelineStages = [];
        $responsables = [];
        $fuentes = [];

        try {
            $kanbanPreferences = $this->leadConfig->getKanbanPreferences(LeadConfigurationService::CONTEXT_EXAMENES);
        } catch (Throwable) {
            // Degradar a defaults cuando la configuración CRM no esté disponible.
        }

        try {
            $pipelineStages = $this->leadConfig->getPipelineStages();
        } catch (Throwable) {
            // Degradar a lista vacía cuando no existan tablas de CRM.
        }

        $examenes = $this->examenModel->fetchExamenesConDetallesFiltrado($filtros);
        $examenes = array_map(fn(array $row): array => $this->transformExamenRow($row), $examenes);
        $examenes = $this->estadoService->enrichExamenes($examenes);
        $examenes = $this->agruparExamenesPorSolicitud($examenes);

        if ($this->isTruthy($filtros['con_pendientes'] ?? null)) {
            $examenes = array_values(array_filter(
                $examenes,
                static fn(array $item): bool => (int) ($item['pendientes_estudios_total'] ?? 0) > 0
            ));
        }

        if (($filtros['mostrar_completados'] ?? false) !== true) {
            $examenes = array_values(array_filter(
                $examenes,
                static fn(array $item): bool => (string) ($item['kanban_estado'] ?? '') !== 'completado'
            ));
        }

        $examenes = $this->ordenarExamenes($examenes, (string) ($kanbanPreferences['sort'] ?? 'fecha_desc'));
        $examenes = $this->limitarExamenesPorEstado($examenes, (int) ($kanbanPreferences['column_limit'] ?? 0));

        try {
            $responsables = array_map(
                fn(array $usuario): array => $this->transformResponsable($usuario),
                $this->leadConfig->getAssignableUsers()
            );
        } catch (Throwable) {
            $responsables = [];
        }

        try {
            $fuentes = $this->leadConfig->getSources();
        } catch (Throwable) {
            $fuentes = [];
        }

        $afiliaciones = $this->distinctSortedValues($examenes, 'afiliacion');
        $doctores = $this->distinctSortedValues($examenes, 'doctor');
        $sedes = $this->distinctSortedValues($examenes, 'sede');

        return [
            'status' => 200,
            'payload' => [
                'data' => $examenes,
                'options' => [
                    'afiliaciones' => $afiliaciones,
                    'afiliacion_categorias' => $this->afiliacionDimensions->getCategoriaOptions('Todas'),
                    'empresas_seguro' => $this->afiliacionDimensions->getEmpresaOptions('Todas'),
                    'planes_seguro' => $this->afiliacionDimensions->getSeguroOptions('Todas', $filtros['empresa_seguro'] ?? ''),
                    'sedes' => $sedes,
                    'doctores' => $doctores,
                    'metrics' => $this->buildKanbanMetrics($examenes),
                    'crm' => [
                        'responsables' => $responsables,
                        'etapas' => $pipelineStages,
                        'fuentes' => $fuentes,
                        'kanban' => $kanbanPreferences,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function actualizarEstado(array $payload, ?int $userId): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $estado = trim((string) ($payload['estado'] ?? ''));
        if ($estado === '') {
            $estado = trim((string) ($payload['etapa'] ?? ''));
        }
        $origen = trim((string) ($payload['origen'] ?? 'kanban'));
        $observacion = isset($payload['observacion']) ? trim((string) $payload['observacion']) : null;

        if ($id <= 0 || $estado === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Datos incompletos',
                ],
            ];
        }

        $resultado = $this->examenModel->actualizarEstado(
            $id,
            $estado,
            $userId,
            $origen !== '' ? $origen : 'kanban',
            $observacion
        );

        $this->pusherConfig->trigger(
            $resultado + [
                'channels' => $this->pusherConfig->getNotificationChannels(),
            ],
            self::PUSHER_CHANNEL,
            PusherConfigService::EVENT_STATUS_UPDATED
        );

        // Notify CRM pipeline about the exam state change
        ExamenEstadoCambiado::dispatch(
            examenId: $id,
            nuevoEstado: (string) ($resultado['estado'] ?? $estado),
            estadoAnterior: (string) ($resultado['estado_anterior'] ?? ''),
            actorUserId: $userId,
        );

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'estado' => $resultado['estado'] ?? $estado,
                'turno' => $resultado['turno'] ?? null,
                'estado_anterior' => $resultado['estado_anterior'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function turneroData(array $query): array
    {
        $estados = $this->parseEstados($query['estado'] ?? null);

        $examenes = $this->examenModel->fetchTurneroExamenes($estados);

        foreach ($examenes as &$examen) {
            $nombreCompleto = trim((string) ($examen['full_name'] ?? ''));
            $examen['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
            $examen['turno'] = isset($examen['turno']) ? (int) $examen['turno'] : null;

            $estadoNormalizado = $this->normalizarEstadoTurnero((string) ($examen['estado'] ?? ''));
            $examen['estado'] = $estadoNormalizado ?? ($examen['estado'] ?? null);
            $examen['hora'] = null;
            $examen['fecha'] = null;

            if (!empty($examen['created_at'])) {
                $timestamp = strtotime((string) $examen['created_at']);
                if ($timestamp !== false) {
                    $examen['hora'] = date('H:i', $timestamp);
                    $examen['fecha'] = date('d/m/Y', $timestamp);
                }
            }
        }
        unset($examen);

        return [
            'status' => 200,
            'payload' => [
                'data' => $examenes,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function turneroLlamar(array $payload, ?int $userId): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : null;
        $turno = isset($payload['turno']) ? (int) $payload['turno'] : null;
        $estadoSolicitado = isset($payload['estado']) ? trim((string) $payload['estado']) : 'Turno llamado';
        $estadoNormalizado = $this->normalizarEstadoTurnero($estadoSolicitado);

        if ($estadoNormalizado === null) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Estado no permitido para el turnero',
                ],
            ];
        }

        if ((!$id || $id <= 0) && (!$turno || $turno <= 0)) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Debe especificar un ID o número de turno',
                ],
            ];
        }

        $registro = $this->examenModel->llamarTurno(
            $id,
            $turno,
            $estadoNormalizado,
            $userId,
            'turnero'
        );

        if (!$registro) {
            return [
                'status' => 404,
                'payload' => [
                    'success' => false,
                    'error' => 'No se encontró el examen indicado',
                ],
            ];
        }

        $nombreCompleto = trim((string) ($registro['full_name'] ?? ''));
        $registro['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
        $registro['estado'] = $this->normalizarEstadoTurnero((string) ($registro['estado'] ?? ''))
            ?? ($registro['estado'] ?? null);

        try {
            $this->pusherConfig->trigger(
                [
                    'id' => (int) ($registro['id'] ?? $id ?? 0),
                    'turno' => $registro['turno'] ?? $turno,
                    'estado' => $registro['estado'] ?? $estadoNormalizado,
                    'hc_number' => $registro['hc_number'] ?? null,
                    'full_name' => $registro['full_name'] ?? null,
                    'kanban_estado' => $registro['kanban_estado'] ?? ($registro['estado'] ?? null),
                    'triggered_by' => $userId,
                ],
                self::PUSHER_CHANNEL,
                PusherConfigService::EVENT_TURNERO_UPDATED
            );
        } catch (Throwable) {
            // Ignorar errores de notificación para no bloquear la operación principal.
        }

        if (isset($resumen['detalle']) && is_array($resumen['detalle'])) {
            $resumen['whatsapp_context'] = $this->queryWhatsappContext($resumen['detalle']);
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $registro,
            ],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmOptions(): array
    {
        try {
            $kanbanPreferences = $this->leadConfig->getKanbanPreferences(LeadConfigurationService::CONTEXT_EXAMENES);
            $responsables = array_values(array_filter(
                $this->leadConfig->getAssignableUsers(),
                static fn(array $usuario): bool => !isset($usuario['activo']) || (int) $usuario['activo'] === 1
            ));
            $pipelineStages = $this->leadConfig->getPipelineStages();
            $fuentes = $this->leadConfig->getSources();
        } catch (Throwable) {
            $kanbanPreferences = [
                'sort' => 'fecha_desc',
                'column_limit' => 0,
            ];
            $responsables = [];
            $pipelineStages = [];
            $fuentes = [];
        }

        return [
            'status' => 200,
            'payload' => [
                'options' => [
                    'crm' => [
                        'responsables' => $responsables,
                        'etapas' => $pipelineStages,
                        'fuentes' => $fuentes,
                        'kanban' => $kanbanPreferences,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmEnviarWhatsapp(int $examenId, array $payload, ?int $userId): array
    {
        $message = trim((string) ($payload['message'] ?? $payload['mensaje'] ?? ''));
        if ($message === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Escribe un mensaje de WhatsApp antes de enviar.',
                ],
            ];
        }

        try {
            $resumen = $this->crmService->obtenerResumen($examenId);
            $detalle = is_array($resumen['detalle'] ?? null) ? $resumen['detalle'] : [];
            $conversationId = isset($payload['conversation_id']) ? (int) $payload['conversation_id'] : 0;
            $phone = trim((string) ($payload['phone'] ?? $payload['telefono'] ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($detalle['crm_contacto_telefono'] ?? $detalle['paciente_celular'] ?? ''));
            }

            $conversation = $conversationId > 0
                ? WhatsappConversation::query()->find($conversationId)
                : $this->findWhatsappConversationByPhone($phone);

            if (!$conversation instanceof WhatsappConversation) {
                throw new RuntimeException('No hay conversación WhatsApp vinculada. Abre el chat V2 o inicia con una plantilla aprobada.');
            }

            if ((int) ($conversation->assigned_user_id ?? 0) <= 0 && $userId !== null) {
                $conversation->assigned_user_id = $userId;
                $conversation->assigned_at = now();
                $conversation->needs_human = true;
                $conversation->save();
            }

            $result = $this->whatsappWriter->sendTextToConversation((int) $conversation->id, $message, false, $userId);
            $this->crmService->registrarNota(
                $examenId,
                sprintf("WhatsApp enviado a +%s:\n%s", ltrim((string) $conversation->wa_number, '+'), $message),
                $userId
            );

            $resumen = $this->crmService->obtenerResumen($examenId);
            if (isset($resumen['detalle']) && is_array($resumen['detalle'])) {
                $resumen['whatsapp_context'] = $this->queryWhatsappContext($resumen['detalle']);
            }
        } catch (RuntimeException $e) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo enviar el WhatsApp.',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'message' => 'Mensaje WhatsApp enviado.',
                'whatsapp' => $result,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmEnviarEmail(int $examenId, array $payload, ?int $userId): array
    {
        try {
            $resumen = $this->crmService->obtenerResumen($examenId);
            $detalle = is_array($resumen['detalle'] ?? null) ? $resumen['detalle'] : [];
            $to = trim((string) ($payload['to'] ?? $payload['email'] ?? $detalle['crm_contacto_email'] ?? ''));
            $subject = trim((string) ($payload['subject'] ?? $payload['asunto'] ?? ''));
            $body = trim((string) ($payload['body'] ?? $payload['mensaje'] ?? ''));

            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Indica un correo de destino válido.');
            }
            if ($subject === '') {
                throw new RuntimeException('Indica un asunto para el correo.');
            }
            if ($body === '') {
                throw new RuntimeException('Escribe el cuerpo del correo antes de enviar.');
            }

            Mail::raw($body, static function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });

            $this->mailLogService->create([
                'examen_id' => $examenId,
                'form_id' => $detalle['form_id'] ?? null,
                'hc_number' => $detalle['hc_number'] ?? null,
                'to_emails' => $to,
                'subject' => $subject,
                'body_text' => $body,
                'channel' => 'email',
                'sent_by_user_id' => $userId,
                'status' => 'sent',
                'sent_at' => now()->toDateTimeString(),
            ]);

            $this->crmService->registrarNota(
                $examenId,
                sprintf("Correo enviado a %s\nAsunto: %s\n\n%s", $to, $subject, $body),
                $userId
            );

            $resumen = $this->crmService->obtenerResumen($examenId);
            if (isset($resumen['detalle']) && is_array($resumen['detalle'])) {
                $resumen['whatsapp_context'] = $this->queryWhatsappContext($resumen['detalle']);
            }
        } catch (RuntimeException $e) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo enviar el correo.',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'message' => 'Correo enviado.',
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmCrearPropuesta(int $examenId, array $payload, ?int $userId): array
    {
        try {
            $resumen = $this->crmService->crearPropuesta($examenId, $payload, $userId);
            if (isset($resumen['detalle']) && is_array($resumen['detalle'])) {
                $resumen['whatsapp_context'] = $this->queryWhatsappContext($resumen['detalle']);
            }
        } catch (RuntimeException $e) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo crear la propuesta CRM.',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'message' => 'Propuesta CRM creada.',
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmResumen(int $examenId): array
    {
        try {
            $resumen = $this->crmService->obtenerResumen($examenId);
        } catch (Throwable $e) {
            Log::error('examenes.crm_resumen.error', [
                'examen_id' => $examenId,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo cargar el detalle CRM',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $permissions
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmBootstrap(int $examenId, array $payload, ?int $userId, array $permissions = []): array
    {
        try {
            $resultado = $this->crmService->bootstrapChecklist(
                $examenId,
                $payload,
                $userId,
                $permissions
            );
        } catch (RuntimeException $e) {
            $status = (int) ($e->getCode() ?: 422);
            if ($status < 400 || $status >= 500) {
                $status = 422;
            }

            return [
                'status' => $status,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo sincronizar el checklist con CRM',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => ['success' => true] + $resultado,
        ];
    }

    /**
     * @param array<int,string> $permissions
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmChecklistState(int $examenId, array $permissions = []): array
    {
        try {
            $resultado = $this->crmService->checklistState($examenId, $permissions);
        } catch (RuntimeException $e) {
            $status = (int) ($e->getCode() ?: 422);
            if ($status < 400 || $status >= 500) {
                $status = 422;
            }

            return [
                'status' => $status,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo cargar el checklist',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => ['success' => true] + $resultado,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $permissions
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmActualizarChecklist(int $examenId, array $payload, ?int $userId, array $permissions = []): array
    {
        $etapa = trim((string) ($payload['etapa_slug'] ?? $payload['etapa'] ?? ''));
        $completado = isset($payload['completado']) ? (bool) $payload['completado'] : true;

        if ($etapa === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Etapa requerida',
                ],
            ];
        }

        try {
            $resultado = $this->crmService->syncChecklistStage(
                $examenId,
                $etapa,
                $completado,
                $userId,
                $permissions
            );
        } catch (RuntimeException $e) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo sincronizar el checklist con CRM',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'checklist' => $resultado['checklist'] ?? [],
                'checklist_progress' => $resultado['checklist_progress'] ?? [],
                'tasks' => $resultado['tasks'] ?? [],
                'lead_id' => $resultado['lead_id'] ?? null,
                'project_id' => $resultado['project_id'] ?? null,
                'kanban_estado' => $resultado['kanban_estado'] ?? null,
                'kanban_estado_label' => $resultado['kanban_estado_label'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmRegistrarBloqueo(int $examenId, array $payload, ?int $userId): array
    {
        try {
            $resultado = $this->crmService->registrarBloqueoAgenda($examenId, $payload, $userId);
        } catch (Throwable $e) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo registrar el bloqueo de agenda',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resultado,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmGuardarDetalles(int $examenId, array $payload, ?int $userId): array
    {
        try {
            $this->crmService->guardarDetalles($examenId, $payload, $userId);
            $resumen = $this->crmService->obtenerResumen($examenId);
            $detalle = is_array($resumen['detalle'] ?? null) ? $resumen['detalle'] : [];

            $this->pusherConfig->trigger(
                [
                    'examen_id' => $examenId,
                    'crm_lead_id' => $detalle['crm_lead_id'] ?? null,
                    'pipeline_stage' => $detalle['crm_pipeline_stage'] ?? null,
                    'responsable_id' => $detalle['crm_responsable_id'] ?? null,
                    'responsable_nombre' => $detalle['crm_responsable_nombre'] ?? null,
                    'fuente' => $detalle['crm_fuente'] ?? null,
                    'contacto_email' => $detalle['crm_contacto_email'] ?? null,
                    'contacto_telefono' => $detalle['crm_contacto_telefono'] ?? null,
                    'paciente_nombre' => $detalle['paciente_nombre'] ?? null,
                    'examen_nombre' => $detalle['examen_nombre'] ?? null,
                    'doctor' => $detalle['doctor'] ?? null,
                    'prioridad' => $detalle['prioridad'] ?? null,
                    'kanban_estado' => $detalle['estado'] ?? null,
                    'channels' => $this->pusherConfig->getNotificationChannels(),
                ],
                self::PUSHER_CHANNEL,
                PusherConfigService::EVENT_CRM_UPDATED
            );
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudieron guardar los cambios',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmAgregarNota(int $examenId, array $payload, ?int $userId): array
    {
        $nota = trim((string) ($payload['nota'] ?? ''));
        if ($nota === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'La nota no puede estar vacía',
                ],
            ];
        }

        try {
            $this->crmService->registrarNota($examenId, $nota, $userId);
            $resumen = $this->crmService->obtenerResumen($examenId);
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo registrar la nota',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmGuardarTarea(int $examenId, array $payload, ?int $userId): array
    {
        try {
            $this->crmService->registrarTarea($examenId, $payload, $userId);
            $resumen = $this->crmService->obtenerResumen($examenId);
        } catch (Throwable $e) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo crear la tarea',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmActualizarTarea(int $examenId, array $payload, ?int $userId = null): array
    {
        $tareaId = isset($payload['tarea_id']) ? (int) $payload['tarea_id'] : 0;
        $estado = isset($payload['estado']) ? (string) $payload['estado'] : '';

        if ($tareaId <= 0 || $estado === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Datos incompletos',
                ],
            ];
        }

        try {
            $this->crmService->actualizarTarea($examenId, $tareaId, $payload, $userId);
            $resumen = $this->crmService->obtenerResumen($examenId);
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo actualizar la tarea',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function crmSubirAdjunto(
        int $examenId,
        ?UploadedFile $archivo,
        ?string $descripcion,
        ?int $userId
    ): array {
        if (!$archivo instanceof UploadedFile) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'No se recibió el archivo',
                ],
            ];
        }

        if (!$archivo->isValid()) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'El archivo es inválido',
                ],
            ];
        }

        $nombreOriginal = trim((string) $archivo->getClientOriginalName());
        if ($nombreOriginal === '') {
            $nombreOriginal = 'adjunto';
        }

        $mimeType = $archivo->getClientMimeType();
        $tamano = $archivo->getSize();

        $carpetaBase = rtrim((string) public_path(self::STORAGE_PATH . '/' . $examenId), DIRECTORY_SEPARATOR);
        if (!is_dir($carpetaBase) && !mkdir($carpetaBase, 0775, true) && !is_dir($carpetaBase)) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo preparar la carpeta de adjuntos',
                ],
            ];
        }

        $nombreLimpio = preg_replace('/[^A-Za-z0-9_\\.-]+/', '_', $nombreOriginal) ?? '';
        $nombreLimpio = trim($nombreLimpio, '_');
        if ($nombreLimpio === '') {
            $nombreLimpio = 'adjunto';
        }

        $destinoNombre = uniqid('crm_', true) . '_' . $nombreLimpio;
        $destinoRuta = $carpetaBase . DIRECTORY_SEPARATOR . $destinoNombre;

        try {
            $archivo->move($carpetaBase, $destinoNombre);
        } catch (Throwable) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo guardar el archivo',
                ],
            ];
        }

        $rutaRelativa = self::STORAGE_PATH . '/' . $examenId . '/' . $destinoNombre;
        $descripcion = $descripcion !== null ? trim($descripcion) : null;

        try {
            $this->crmService->registrarAdjunto(
                $examenId,
                $nombreOriginal,
                $rutaRelativa,
                is_string($mimeType) ? $mimeType : null,
                is_numeric($tamano) ? (int) $tamano : null,
                $userId,
                ($descripcion !== null && $descripcion !== '') ? $descripcion : null
            );

            $resumen = $this->crmService->obtenerResumen($examenId);
        } catch (Throwable) {
            @unlink($destinoRuta);

            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'error' => 'No se pudo registrar el adjunto',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $resumen,
            ],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function apiEstadoGet(?string $hcNumber): array
    {
        $hcNumber = trim((string) ($hcNumber ?? ''));

        if ($hcNumber === '') {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Parámetro hcNumber requerido',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => $this->obtenerEstadosPorHc($hcNumber),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function apiEstadoPost(array $payload, ?int $userId): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($id <= 0 && isset($payload['examen_id'])) {
            $id = (int) $payload['examen_id'];
        }

        if ($id <= 0) {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Parámetro id requerido para actualizar el examen',
                ],
            ];
        }

        $campos = [
            'estado' => $payload['estado'] ?? null,
            'doctor' => $payload['doctor'] ?? null,
            'solicitante' => $payload['solicitante'] ?? null,
            'consulta_fecha' => $payload['consulta_fecha'] ?? ($payload['fecha'] ?? null),
            'prioridad' => $payload['prioridad'] ?? null,
            'observaciones' => $payload['observaciones'] ?? ($payload['observacion'] ?? null),
            'examen_nombre' => $payload['examen_nombre'] ?? ($payload['examen'] ?? null),
            'examen_codigo' => $payload['examen_codigo'] ?? null,
            'lateralidad' => $payload['lateralidad'] ?? ($payload['ojo'] ?? null),
            'turno' => $payload['turno'] ?? null,
        ];

        $resultado = $this->examenModel->actualizarExamenParcial(
            $id,
            $campos,
            $userId,
            'api_estado',
            isset($payload['observacion']) ? trim((string) $payload['observacion']) : null
        );

        $status = (!is_array($resultado) || (($resultado['success'] ?? false) === false)) ? 422 : 200;

        return [
            'status' => $status,
            'payload' => is_array($resultado) ? $resultado : ['success' => false],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function derivacionDetalle(?string $hcNumber, ?string $formId, ?int $examenId, ?int $userId): array
    {
        $hcNumber = trim((string) ($hcNumber ?? ''));
        $formId = trim((string) ($formId ?? ''));
        $examenId = $examenId !== null && $examenId > 0 ? $examenId : null;

        if ($hcNumber === '' || $formId === '') {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Faltan parámetros para consultar la derivación.',
                ],
            ];
        }

        try {
            $derivacion = $this->ensureDerivacion($formId, $hcNumber, $examenId);
        } catch (Throwable) {
            return [
                'status' => 200,
                'payload' => [
                    'success' => true,
                    'has_derivacion' => false,
                    'derivacion_status' => 'error',
                    'derivacion' => null,
                ],
            ];
        }

        if (!$derivacion) {
            return [
                'status' => 200,
                'payload' => [
                    'success' => true,
                    'has_derivacion' => false,
                    'derivacion_status' => 'missing',
                    'message' => 'No hay derivación registrada para este examen.',
                    'derivacion' => null,
                ],
            ];
        }

        $vigenciaStatus = $this->resolveDerivacionVigenciaStatus(
            isset($derivacion['fecha_vigencia']) && is_string($derivacion['fecha_vigencia'])
                ? $derivacion['fecha_vigencia']
                : null
        );

        $estadoSugerido = null;
        if ($vigenciaStatus) {
            $examen = $this->examenModel->obtenerExamenPorFormHc($formId, $hcNumber, $examenId);
            if ($examen) {
                $estadoSugerido = $this->resolveEstadoPorDerivacion($vigenciaStatus, (string) ($examen['estado'] ?? ''));
                if ($estadoSugerido !== null) {
                    $this->actualizarEstadoPorFormHc(
                        $formId,
                        $hcNumber,
                        $estadoSugerido,
                        $userId,
                        'derivacion_vigencia',
                        'Actualizado por vigencia de derivación'
                    );
                }
            }
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'has_derivacion' => true,
                'derivacion_status' => 'ok',
                'message' => null,
                'derivacion' => $derivacion,
                'vigencia_status' => $vigenciaStatus,
                'estado_sugerido' => $estadoSugerido,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function derivacionPreseleccion(array $payload): array
    {
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $examenId = isset($payload['examen_id']) ? (int) $payload['examen_id'] : null;

        if ($hcNumber === '' || $formId === '') {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Faltan parámetros para consultar derivaciones disponibles.',
                ],
            ];
        }

        $seleccion = null;
        if ($examenId !== null && $examenId > 0) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }

        if (!$seleccion) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        if (!empty($seleccion['derivacion_pedido_id'])) {
            return [
                'status' => 200,
                'payload' => [
                    'success' => true,
                    'selected' => [
                        'codigo_derivacion' => $seleccion['derivacion_codigo'] ?? null,
                        'pedido_id_mas_antiguo' => $seleccion['derivacion_pedido_id'] ?? null,
                        'lateralidad' => $seleccion['derivacion_lateralidad'] ?? null,
                        'fecha_vigencia' => $seleccion['derivacion_fecha_vigencia_sel'] ?? null,
                        'prefactura' => $seleccion['derivacion_prefactura'] ?? null,
                    ],
                    'needs_selection' => false,
                    'options' => [],
                ],
            ];
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'message' => 'No se encontró el script de admisiones.',
                ],
            ];
        }

        $cmd = sprintf(
            'python3 %s %s --group --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $rawOutput = trim(implode("\n", $output));
        $parsed = null;

        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim((string) $output[$i]);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
                break;
            }
        }

        if (!$parsed) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'message' => 'No se pudo interpretar la respuesta del scraper de admisiones.',
                    'raw_output' => $rawOutput,
                    'exit_code' => $exitCode,
                ],
            ];
        }

        $grouped = is_array($parsed['grouped'] ?? null) ? $parsed['grouped'] : [];
        $options = [];
        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }

            $data = is_array($item['data'] ?? null) ? $item['data'] : [];

            $options[] = [
                'codigo_derivacion' => $item['codigo_derivacion'] ?? null,
                'pedido_id_mas_antiguo' => $item['pedido_id_mas_antiguo'] ?? null,
                'lateralidad' => $item['lateralidad'] ?? null,
                'fecha_vigencia' => $data['fecha_grupo'] ?? null,
                'prefactura' => $data['prefactura'] ?? null,
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'selected' => null,
                'needs_selection' => true,
                'options' => $options,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function guardarDerivacionPreseleccion(array $payload): array
    {
        $examenId = isset($payload['examen_id']) ? (int) $payload['examen_id'] : null;
        $codigo = trim((string) ($payload['codigo_derivacion'] ?? ''));
        $pedidoId = trim((string) ($payload['pedido_id_mas_antiguo'] ?? ''));
        $lateralidad = trim((string) ($payload['lateralidad'] ?? ''));
        $vigencia = trim((string) ($payload['fecha_vigencia'] ?? ''));
        $prefactura = trim((string) ($payload['prefactura'] ?? ''));

        if (!$examenId || $codigo === '' || $pedidoId === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'message' => 'Datos incompletos para guardar la derivación seleccionada.',
                ],
            ];
        }

        $saved = $this->examenModel->guardarDerivacionPreseleccion($examenId, [
            'derivacion_codigo' => $codigo,
            'derivacion_pedido_id' => $pedidoId,
            'derivacion_lateralidad' => $lateralidad !== '' ? $lateralidad : null,
            'derivacion_fecha_vigencia_sel' => $vigencia !== '' ? $vigencia : null,
            'derivacion_prefactura' => $prefactura !== '' ? $prefactura : null,
        ]);

        if ($saved) {
            // Fetch patient identifier for CRM — best effort, failure is non-fatal
            $hcNumber = '';
            try {
                $stmt = $this->db->prepare('SELECT hc_number FROM consulta_examenes WHERE id = ? LIMIT 1');
                $stmt->execute([$examenId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $hcNumber = is_array($row) ? (string) ($row['hc_number'] ?? '') : '';
            } catch (\Throwable) {
                // non-fatal
            }

            ExamenSolicitado::dispatch(
                examenId: $examenId,
                examenData: [
                    'paciente_nombre'    => '',
                    'paciente_cedula'    => $hcNumber,
                    'paciente_telefono'  => '',
                    'descripcion_examen' => 'Examen con derivación preseleccionada',
                ],
            );
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => $saved,
            ],
        ];
    }

    /**
     * @return array{success:bool,hcNumber:string,total:int,examenes:array<int,array<string,mixed>>}
     */
    private function obtenerEstadosPorHc(string $hcNumber): array
    {
        $examenes = $this->examenModel->obtenerEstadosPorHc($hcNumber);
        $examenes = array_map(fn(array $row): array => $this->transformExamenRow($row), $examenes);
        $examenes = $this->estadoService->enrichExamenes($examenes);

        return [
            'success' => true,
            'hcNumber' => $hcNumber,
            'total' => count($examenes),
            'examenes' => $examenes,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transformExamenRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);
        $categoriaKey = strtolower(trim((string) ($row['afiliacion_categoria_key'] ?? '')));
        $row['afiliacion_categoria'] = $categoriaKey !== ''
            ? $this->afiliacionDimensions->formatCategoriaLabel($categoriaKey)
            : '';

        if (empty($row['fecha'] ?? null)) {
            $row['fecha'] = $row['consulta_fecha'] ?? $row['created_at'] ?? null;
        }

        if (empty($row['procedimiento'] ?? null)) {
            $row['procedimiento'] = $row['examen_nombre'] ?? $row['examen_codigo'] ?? null;
        }

        if (empty($row['tipo'] ?? null)) {
            $row['tipo'] = $row['examen_codigo'] ?? $row['examen_nombre'] ?? null;
        }

        if (empty($row['observacion'] ?? null)) {
            $row['observacion'] = $row['observaciones'] ?? null;
        }

        if (empty($row['ojo'] ?? null)) {
            $row['ojo'] = $row['lateralidad'] ?? null;
        }

        $dias = 0;
        $fechaReferencia = $row['consulta_fecha'] ?? $row['created_at'] ?? null;
        if ($fechaReferencia) {
            $dt = $this->parseFecha($fechaReferencia);
            if ($dt) {
                $dias = max(0, (int) floor((time() - $dt->getTimestamp()) / 86400));
            }
        }

        $row['dias_transcurridos'] = $dias;

        if (!empty($row['derivacion_fecha_vigencia_sel']) && empty($row['derivacion_fecha_vigencia'])) {
            $row['derivacion_fecha_vigencia'] = $row['derivacion_fecha_vigencia_sel'];
        }
        $row['derivacion_status'] = $this->resolveDerivacionVigenciaStatus(
            isset($row['derivacion_fecha_vigencia']) && is_string($row['derivacion_fecha_vigencia'])
                ? $row['derivacion_fecha_vigencia']
                : null
        );

        return $row;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{
     *   afiliacion:string,afiliacion_categoria:string,empresa_seguro:string,plan_seguro:string,sede:string,doctor:string,
     *   prioridad:string,estado:string,responsable_id:string,fechaTexto:string,date_from:?string,date_to:?string,search:string,
     *   con_pendientes:string,mostrar_completados:bool
     * }
     */
    private function sanitizeKanbanFilters(array $payload): array
    {
        $dateFrom = $this->normalizeDateInput($payload['date_from'] ?? null);
        $dateTo = $this->normalizeDateInput($payload['date_to'] ?? null);
        $fechaTexto = trim((string) ($payload['fechaTexto'] ?? ''));

        if (($dateFrom === null || $dateTo === null) && $fechaTexto !== '') {
            [$parsedFrom, $parsedTo] = $this->parseDateRange($fechaTexto);
            $dateFrom ??= $parsedFrom;
            $dateTo ??= $parsedTo;
        }

        return [
            'afiliacion' => trim((string) ($payload['afiliacion'] ?? '')),
            'afiliacion_categoria' => $this->afiliacionDimensions->normalizeCategoriaFilter((string) ($payload['afiliacion_categoria'] ?? '')),
            'empresa_seguro' => $this->afiliacionDimensions->normalizeEmpresaFilter((string) ($payload['empresa_seguro'] ?? '')),
            'plan_seguro' => $this->afiliacionDimensions->normalizeSeguroFilter((string) ($payload['plan_seguro'] ?? '')),
            'sede' => $this->normalizeSedeFilter((string) ($payload['sede'] ?? '')),
            'doctor' => trim((string) ($payload['doctor'] ?? '')),
            'prioridad' => trim((string) ($payload['prioridad'] ?? '')),
            'estado' => trim((string) ($payload['estado'] ?? '')),
            'responsable_id' => $this->isTruthy($payload['crm_sin_responsable'] ?? null)
                ? 'sin_asignar'
                : trim((string) ($payload['responsable_id'] ?? '')),
            'fechaTexto' => $fechaTexto,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => trim((string) ($payload['search'] ?? '')),
            'con_pendientes' => (string) ($payload['con_pendientes'] ?? ''),
            'mostrar_completados' => filter_var($payload['mostrar_completados'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function parseDateRange(string $rangeText): array
    {
        $rangeText = trim($rangeText);
        if ($rangeText === '') {
            return [null, null];
        }

        if (!str_contains($rangeText, ' - ')) {
            $date = $this->normalizeDateInput($rangeText);
            return [$date, $date];
        }

        [$from, $to] = explode(' - ', $rangeText, 2);

        return [$this->normalizeDateInput($from), $this->normalizeDateInput($to)];
    }

    private function normalizeSedeFilter(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (str_contains($value, 'ceib')) {
            return 'CEIBOS';
        }
        if (str_contains($value, 'matriz') || str_contains($value, 'villa')) {
            return 'MATRIZ';
        }

        return strtoupper($value);
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function agruparExamenesPorSolicitud(array $examenes): array
    {
        $agrupados = [];

        foreach ($examenes as $examen) {
            $formId = trim((string) ($examen['form_id'] ?? ''));
            $hcNumber = trim((string) ($examen['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $key = $formId . '|' . $hcNumber;
            $estadoEstudio = $this->normalizarEstadoCoberturaEstudio((string) ($examen['estado'] ?? ''));

            if (!isset($agrupados[$key])) {
                $agrupados[$key] = $examen;
                $agrupados[$key]['estudios'] = [];
                $agrupados[$key]['resumen_estudios'] = [
                    'total' => 0,
                    'aprobados' => 0,
                    'pendientes' => 0,
                    'rechazados' => 0,
                    'sin_respuesta' => 0,
                ];
                $agrupados[$key]['kanban_rank'] = $this->rankEstadoKanban((string) ($examen['kanban_estado'] ?? $examen['estado'] ?? ''));
            }

            $agrupados[$key]['resumen_estudios']['total']++;
            if (isset($agrupados[$key]['resumen_estudios'][$estadoEstudio])) {
                $agrupados[$key]['resumen_estudios'][$estadoEstudio]++;
            }

            $agrupados[$key]['estudios'][] = [
                'id' => $examen['id'] ?? null,
                'codigo' => $examen['examen_codigo'] ?? null,
                'nombre' => $examen['examen_nombre'] ?? null,
                'estado' => $examen['estado'] ?? null,
                'estado_cobertura' => $estadoEstudio,
                'updated_at' => $examen['updated_at'] ?? ($examen['consulta_fecha'] ?? $examen['created_at'] ?? null),
            ];

            $rank = $this->rankEstadoKanban((string) ($examen['kanban_estado'] ?? $examen['estado'] ?? ''));
            if ($rank < (int) $agrupados[$key]['kanban_rank']) {
                $agrupados[$key]['kanban_rank'] = $rank;
                $agrupados[$key]['estado'] = $examen['estado'] ?? $agrupados[$key]['estado'];
                $agrupados[$key]['kanban_estado'] = $examen['kanban_estado']
                    ?? ($agrupados[$key]['kanban_estado'] ?? $agrupados[$key]['estado']);
                $agrupados[$key]['kanban_estado_label'] = $examen['kanban_estado_label']
                    ?? ($agrupados[$key]['kanban_estado_label'] ?? $agrupados[$key]['estado']);
            }
        }

        foreach ($agrupados as &$item) {
            $total = (int) ($item['resumen_estudios']['total'] ?? 0);
            $aprobados = (int) ($item['resumen_estudios']['aprobados'] ?? 0);
            $pendientes = (int) ($item['resumen_estudios']['pendientes'] ?? 0);
            $rechazados = (int) ($item['resumen_estudios']['rechazados'] ?? 0);
            $sinRespuesta = (int) ($item['resumen_estudios']['sin_respuesta'] ?? 0);
            $noAprobados = $pendientes + $rechazados + $sinRespuesta;

            if ($total > 0 && $aprobados > 0 && $noAprobados > 0) {
                $item['estado'] = 'Parcial';
                $item['kanban_estado'] = 'parcial';
                $item['kanban_estado_label'] = 'Parcial';
            } elseif ($total > 0 && $aprobados === $total) {
                $item['estado'] = 'Listo para agenda';
                $item['kanban_estado'] = 'listo-para-agenda';
                $item['kanban_estado_label'] = 'Listo para agenda';
            }

            $pendientesTotal = $pendientes + $sinRespuesta;
            $item['alert_pendientes_estudios'] = $pendientesTotal > 0;
            $item['pendientes_estudios_total'] = $pendientesTotal;
            unset($item['kanban_rank']);
        }
        unset($item);

        return array_values($agrupados);
    }

    private function normalizarEstadoCoberturaEstudio(string $estado): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($estado), 'UTF-8') : strtolower(trim($estado));
        if ($value === '') {
            return 'sin_respuesta';
        }
        if (str_contains($value, 'aprobad')) {
            return 'aprobados';
        }
        if (str_contains($value, 'rechaz')) {
            return 'rechazados';
        }
        if (str_contains($value, 'pend') || str_contains($value, 'revision') || str_contains($value, 'recibid')) {
            return 'pendientes';
        }

        return 'sin_respuesta';
    }

    private function rankEstadoKanban(string $estado): int
    {
        $slug = $this->estadoService->normalizeSlug($estado);

        $rank = [
            'recibido' => 0,
            'llamado' => 1,
            'revision-cobertura' => 2,
            'parcial' => 3,
            'listo-para-agenda' => 4,
            'completado' => 5,
        ];

        return $rank[$slug] ?? 99;
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function transformResponsable(array $usuario): array
    {
        $usuario['avatar'] = $this->formatProfilePhoto($usuario['avatar'] ?? ($usuario['profile_photo'] ?? null));

        if (isset($usuario['profile_photo'])) {
            $usuario['profile_photo'] = $this->formatProfilePhoto($usuario['profile_photo']);
        }

        return $usuario;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function ordenarExamenes(array $examenes, string $criterio): array
    {
        $criterio = strtolower(trim($criterio));

        $comparador = match ($criterio) {
            'fecha_asc' => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'consulta_fecha', true),
            'creado_desc' => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'created_at', false),
            'creado_asc' => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'created_at', true),
            default => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'consulta_fecha', false),
        };

        usort($examenes, $comparador);

        return $examenes;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compararPorFecha(array $a, array $b, string $campo, bool $ascendente): int
    {
        $valorA = $this->parseFecha($a[$campo] ?? null);
        $valorB = $this->parseFecha($b[$campo] ?? null);

        if ($valorA === $valorB) {
            return 0;
        }

        if ($ascendente) {
            return $valorA <=> $valorB;
        }

        return $valorB <=> $valorA;
    }

    private function resolveDerivacionVigenciaStatus(?string $fechaVigencia): ?string
    {
        if (!$fechaVigencia) {
            return null;
        }

        $dt = $this->parseFecha($fechaVigencia);
        if (!$dt) {
            return null;
        }

        $hoy = new DateTimeImmutable('today');

        return $dt >= $hoy ? 'vigente' : 'vencida';
    }

    private function resolveEstadoPorDerivacion(?string $vigenciaStatus, string $estadoActual): ?string
    {
        if (!$vigenciaStatus) {
            return null;
        }

        $slug = $this->estadoService->normalizeSlug($estadoActual);
        if ($slug === '') {
            $slug = 'recibido';
        }

        if ($slug === 'completado') {
            return null;
        }

        if ($vigenciaStatus === 'vencida') {
            return $slug !== 'revision-cobertura' ? 'Revisión de cobertura' : null;
        }

        if ($vigenciaStatus === 'vigente') {
            if (in_array($slug, ['recibido', 'llamado', 'revision-cobertura'], true)) {
                return 'Listo para agenda';
            }
        }

        return null;
    }

    private function actualizarEstadoPorFormHc(
        string $formId,
        string $hcNumber,
        string $estado,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): void {
        if ($formId === '' || $hcNumber === '') {
            return;
        }

        $examenes = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);
        foreach ($examenes as $registro) {
            $id = isset($registro['id']) ? (int) $registro['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $this->examenModel->actualizarExamenParcial(
                $id,
                ['estado' => $estado],
                $changedBy,
                $origen,
                $observacion
            );
        }
    }

    private function ensureDerivacion(string $formId, string $hcNumber, ?int $examenId = null): ?array
    {
        $seleccion = null;
        if ($examenId !== null && $examenId > 0) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }
        if (!$seleccion) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        $lookupFormId = (string) ($seleccion['derivacion_pedido_id'] ?? $formId);
        $hasSelection = trim((string) ($seleccion['derivacion_pedido_id'] ?? '')) !== '';

        if ($hasSelection) {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($lookupFormId);
            if ($derivacion) {
                return $derivacion;
            }
        } else {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($formId);
            if ($derivacion) {
                return $derivacion;
            }
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return null;
        }

        $cmd = sprintf(
            'python3 %s %s %s',
            escapeshellarg($script),
            escapeshellarg($lookupFormId),
            escapeshellarg($hcNumber)
        );

        try {
            @exec($cmd);
        } catch (Throwable) {
            // Silenciar para no romper flujo.
        }

        return $this->examenModel->obtenerDerivacionPorFormId($lookupFormId) ?: null;
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function limitarExamenesPorEstado(array $examenes, int $limitePorColumna): array
    {
        if ($limitePorColumna <= 0) {
            return $examenes;
        }

        $contadores = [];
        $filtrados = [];

        foreach ($examenes as $examen) {
            $estadoBase = $examen['kanban_estado'] ?? $examen['estado'] ?? 'Pendiente';
            $estado = strtolower(trim((string) $estadoBase));
            $contadores[$estado] = ($contadores[$estado] ?? 0) + 1;

            if ($contadores[$estado] <= $limitePorColumna) {
                $filtrados[] = $examen;
            }
        }

        return $filtrados;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function distinctSortedValues(array $rows, string $key): array
    {
        $values = array_values(array_unique(array_filter(array_map(
            static fn(array $row): ?string => isset($row[$key]) ? trim((string) $row[$key]) : null,
            $rows
        ), static fn(?string $value): bool => $value !== null && $value !== '')));

        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }

    /**
     * @param array<int,array<string,mixed>> $examenes
     * @return array<string,mixed>
     */
    private function buildKanbanMetrics(array $examenes): array
    {
        $total = count($examenes);
        $porEstado = [];
        $sinResponsable = 0;
        $conPendientes = 0;

        foreach ($examenes as $examen) {
            $estado = (string) ($examen['kanban_estado'] ?? $examen['estado'] ?? 'sin_estado');
            $porEstado[$estado] = ($porEstado[$estado] ?? 0) + 1;

            if (empty($examen['crm_responsable_id'])) {
                $sinResponsable++;
            }
            if ((int) ($examen['pendientes_estudios_total'] ?? 0) > 0) {
                $conPendientes++;
            }
        }

        return [
            'total' => $total,
            'por_estado' => $porEstado,
            'sin_responsable' => $sinResponsable,
            'con_pendientes' => $conPendientes,
        ];
    }

    private function parseFecha(mixed $valor): ?DateTimeImmutable
    {
        if (empty($valor)) {
            return null;
        }

        if ($valor instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($valor);
        }

        $string = is_string($valor) ? trim($valor) : '';
        if ($string === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $string);
            if ($dt instanceof DateTimeImmutable) {
                if ($format === 'Y-m-d') {
                    return $dt->setTime(0, 0);
                }

                return $dt;
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }

    private function normalizarEstadoTurnero(string $estado): ?string
    {
        $mapa = [
            'recibido' => 'Recibido',
            'llamado' => 'Turno llamado',
            'turno llamado' => 'Turno llamado',
            'turno_llamado' => 'Turno llamado',
            'turno-llamado' => 'Turno llamado',
            'revision-cobertura' => 'Revisión de Cobertura',
            'revision-de-cobertura' => 'Revisión de Cobertura',
            'revision-codigos' => 'Revisión de Cobertura',
            'revision-de-codigos' => 'Revisión de Cobertura',
            'revision de cobertura' => 'Revisión de Cobertura',
            'revision cobertura' => 'Revisión de Cobertura',
            'listo para agenda' => 'Listo para Agenda',
            'en atencion' => 'En atención',
            'en atención' => 'En atención',
            'atendido' => 'Atendido',
        ];

        $estadoLimpio = trim($estado);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($estadoLimpio, 'UTF-8')
            : strtolower($estadoLimpio);
        $clave = strtr($clave, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return $mapa[$clave] ?? null;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function parseEstados(mixed $raw): array
    {
        if (is_array($raw)) {
            $estados = array_map(static fn(mixed $value): string => trim((string) $value), $raw);
            return array_values(array_filter($estados, static fn(string $value): bool => $value !== ''));
        }

        $input = trim((string) ($raw ?? ''));
        if ($input === '') {
            return [];
        }

        $estados = array_map('trim', explode(',', $input));

        return array_values(array_filter($estados, static fn(string $value): bool => $value !== ''));
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) ($value ?? '')));

        return in_array($normalized, ['1', 'true', 'si', 'sí', 'yes'], true);
    }

    private function projectRootPath(): string
    {
        return realpath(base_path('..')) ?: base_path('..');
    }

    /**
     * @param array<string,mixed> $detalle
     * @return array<string,mixed>|null
     */
    private function queryWhatsappContext(array $detalle): ?array
    {
        $phone = trim((string) ($detalle['crm_contacto_telefono'] ?? $detalle['paciente_celular'] ?? ''));
        $conversation = $this->findWhatsappConversationByPhone($phone);
        if (!$conversation instanceof WhatsappConversation) {
            return null;
        }

        return [
            'conversation_id' => (int) $conversation->id,
            'phone' => (string) $conversation->wa_number,
            'url' => '/v2/whatsapp?conversation_id=' . rawurlencode((string) $conversation->id),
        ];
    }

    private function findWhatsappConversationByPhone(string $phone): ?WhatsappConversation
    {
        $normalized = $this->normalizeWhatsappPhone($phone);
        if ($normalized === '') {
            return null;
        }

        return WhatsappConversation::query()->where('wa_number', $normalized)->first();
    }

    private function normalizeWhatsappPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '593' . substr($digits, 1);
        }
        if (!str_starts_with($digits, '593') && strlen($digits) === 9) {
            $digits = '593' . $digits;
        }

        return $digits;
    }

    private function ensureLegacyClassAutoloading(): void
    {
        if (self::$legacyAutoloaderRegistered) {
            return;
        }

        $baseDir = realpath(base_path('..')) ?: base_path('..');

        $prefixes = [
            'Modules\\' => $baseDir . '/modules/',
            'Core\\' => $baseDir . '/core/',
            'Controllers\\' => $baseDir . '/controllers/',
            'Models\\' => $baseDir . '/models/',
            'Helpers\\' => $baseDir . '/helpers/',
            'Services\\' => $baseDir . '/controllers/Services/',
        ];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $legacyBaseDir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }

                $relativeClass = substr($class, $len);
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                $paths = [
                    $legacyBaseDir . $relativePath,
                    $legacyBaseDir . strtolower($relativePath),
                ];

                $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
                $fileName = array_pop($segments) ?: '';
                $lowerDirPath = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $segments));
                if ($lowerDirPath !== '') {
                    $paths[] = rtrim($legacyBaseDir . $lowerDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                }

                foreach ($paths as $path) {
                    if (is_file($path)) {
                        require_once $path;
                        return;
                    }
                }
            }
        }, true, true);

        self::$legacyAutoloaderRegistered = true;
    }
}
