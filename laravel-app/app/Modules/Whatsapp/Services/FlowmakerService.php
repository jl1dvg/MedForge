<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAutoresponderFlow;
use App\Models\WhatsappAutoresponderFlowVersion;
use App\Models\WhatsappAutoresponderSchedule;
use App\Models\WhatsappAutoresponderSession;
use App\Models\WhatsappAutoresponderStep;
use App\Models\WhatsappAutoresponderStepAction;
use App\Models\WhatsappAutoresponderStepTransition;
use App\Models\WhatsappAutoresponderVersionFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FlowmakerService
{
    private const DEFAULT_FLOW_KEY = 'default';
    private const BUTTON_LIMIT = 3;
    private const STAGE_VALUES = [
        'arrival',
        'validation',
        'consent',
        'menu',
        'scheduling',
        'results',
        'post',
        'custom',
    ];
    private const FLOW_VARIABLES = [
        ['id' => 'nombre', 'label' => 'Nombre del contacto', 'group' => 'Contacto'],
        ['id' => 'telefono', 'label' => 'Teléfono WhatsApp', 'group' => 'Contacto'],
        ['id' => 'wa_number', 'label' => 'Número WhatsApp', 'group' => 'Contacto'],
        ['id' => 'cedula', 'label' => 'Cédula / identificación', 'group' => 'Paciente'],
        ['id' => 'identifier', 'label' => 'Identificador normalizado', 'group' => 'Paciente'],
        ['id' => 'current_identifier', 'label' => 'Identificador actual', 'group' => 'Paciente'],
        ['id' => 'patient_hc_number', 'label' => 'Historia clínica', 'group' => 'Paciente'],
        ['id' => 'es_paciente', 'label' => 'Es paciente registrado', 'group' => 'Paciente'],
        ['id' => 'patient_found', 'label' => 'Paciente encontrado', 'group' => 'Paciente'],
        ['id' => 'patient_new', 'label' => 'Paciente nuevo', 'group' => 'Paciente'],
        ['id' => 'correo', 'label' => 'Correo electrónico', 'group' => 'Paciente'],
        ['id' => 'email', 'label' => 'Email', 'group' => 'Paciente'],
        ['id' => 'ultima_cita', 'label' => 'Última cita', 'group' => 'Agenda'],
        ['id' => 'sucursal', 'label' => 'Sucursal preferida', 'group' => 'Agenda'],
        ['id' => 'agenda_branch', 'label' => 'Rama de agendamiento', 'group' => 'Agenda'],
        ['id' => 'sede_id', 'label' => 'ID de sede', 'group' => 'Agenda Sigcenter'],
        ['id' => 'ID_SEDE', 'label' => 'ID de sede Sigcenter', 'group' => 'Agenda Sigcenter'],
        ['id' => 'sede_id_label', 'label' => 'Etiqueta de sede', 'group' => 'Agenda Sigcenter'],
        ['id' => 'sede_nombre', 'label' => 'Nombre de sede', 'group' => 'Agenda Sigcenter'],
        ['id' => 'trabajador_id', 'label' => 'ID de médico', 'group' => 'Agenda Sigcenter'],
        ['id' => 'trabajador_id_label', 'label' => 'Etiqueta de médico', 'group' => 'Agenda Sigcenter'],
        ['id' => 'medico_nombre', 'label' => 'Nombre de médico', 'group' => 'Agenda Sigcenter'],
        ['id' => 'especialidad', 'label' => 'Especialidad', 'group' => 'Agenda Sigcenter'],
        ['id' => 'subespecialidad', 'label' => 'Subespecialidad', 'group' => 'Agenda Sigcenter'],
        ['id' => 'subespecialidad_nombre', 'label' => 'Nombre de subespecialidad', 'group' => 'Agenda Sigcenter'],
        ['id' => 'procedimiento_id', 'label' => 'ID de procedimiento', 'group' => 'Agenda Sigcenter'],
        ['id' => 'procedimiento_id_label', 'label' => 'Etiqueta de procedimiento', 'group' => 'Agenda Sigcenter'],
        ['id' => 'procedimiento_nombre', 'label' => 'Nombre de procedimiento', 'group' => 'Agenda Sigcenter'],
        ['id' => 'FECHA', 'label' => 'Fecha seleccionada', 'group' => 'Agenda Sigcenter'],
        ['id' => 'fecha_inicio', 'label' => 'Fecha de inicio', 'group' => 'Agenda Sigcenter'],
        ['id' => 'fecha_inicio_raw', 'label' => 'Fecha de inicio sin formato', 'group' => 'Agenda Sigcenter'],
        ['id' => 'hora', 'label' => 'Hora seleccionada', 'group' => 'Agenda Sigcenter'],
        ['id' => 'horario_texto', 'label' => 'Horario seleccionado', 'group' => 'Agenda Sigcenter'],
        ['id' => 'horarios_disponibles', 'label' => 'Horarios disponibles', 'group' => 'Agenda Sigcenter'],
        ['id' => 'sigcenter_booking_status', 'label' => 'Estado de reserva Sigcenter', 'group' => 'Agenda Sigcenter'],
        ['id' => 'resolved_cita_tipo', 'label' => 'Tipo de cita resuelto', 'group' => 'Agenda Sigcenter'],
        ['id' => 'resolved_last_consulta_at', 'label' => 'Última consulta registrada', 'group' => 'Agenda Sigcenter'],
        ['id' => 'resolved_last_surgery_at', 'label' => 'Última cirugía registrada', 'group' => 'Agenda Sigcenter'],
        ['id' => 'resolved_procedimiento_ids', 'label' => 'Procedimientos resueltos', 'group' => 'Agenda Sigcenter'],
        ['id' => 'resolved_procedimiento_reason', 'label' => 'Motivo de procedimiento resuelto', 'group' => 'Agenda Sigcenter'],
        ['id' => 'state', 'label' => 'Estado de conversación', 'group' => 'Sesión'],
        ['id' => 'awaiting_field', 'label' => 'Campo esperado', 'group' => 'Sesión'],
        ['id' => 'handoff_topic', 'label' => 'Motivo de derivación', 'group' => 'Sesión'],
        ['id' => 'handoff_note', 'label' => 'Nota de derivación', 'group' => 'Sesión'],
        ['id' => 'handoff_priority', 'label' => 'Prioridad de derivación', 'group' => 'Sesión'],
        ['id' => 'handoff_reasons', 'label' => 'Razones de derivación', 'group' => 'Sesión'],
        ['id' => 'handoff_requested', 'label' => 'Derivación solicitada', 'group' => 'Sesión'],
        ['id' => 'handoff_role_id', 'label' => 'Rol destino de derivación', 'group' => 'Sesión'],
        ['id' => 'consent', 'label' => 'Consentimiento registrado', 'group' => 'Sesión'],
        ['id' => 'abandonment_monitor', 'label' => 'Monitor de abandono', 'group' => 'Sesión'],
        ['id' => 'off_hours_handoff_pending', 'label' => 'Derivación fuera de horario pendiente', 'group' => 'Sesión'],
        ['id' => 'lead_email', 'label' => 'Correo captado', 'group' => 'CRM'],
        ['id' => 'lead_source', 'label' => 'Fuente del lead', 'group' => 'CRM'],
        ['id' => 'lead_source_label', 'label' => 'Etiqueta de fuente del lead', 'group' => 'CRM'],
        ['id' => 'lead_source_detail', 'label' => 'Detalle de fuente del lead', 'group' => 'CRM'],
        ['id' => 'lead_capture_saved', 'label' => 'Captación de lead guardada', 'group' => 'CRM'],
        ['id' => 'crm_lead_id', 'label' => 'ID de lead CRM', 'group' => 'CRM'],
        ['id' => 'crm_opportunity_id', 'label' => 'ID de oportunidad CRM', 'group' => 'CRM'],
        ['id' => 'triage_destino', 'label' => 'Destino de triage IA', 'group' => 'Inteligencia Artificial'],
        ['id' => 'triage_nivel_urgencia', 'label' => 'Nivel de urgencia IA', 'group' => 'Inteligencia Artificial'],
        ['id' => 'input_text', 'label' => 'Texto entrante', 'group' => 'Entrada'],
        ['id' => 'last_message', 'label' => 'Último mensaje', 'group' => 'Entrada'],
    ];
    private const SIGCENTER_OPERATIONS = [
        ['id' => 'list_specialties', 'label' => 'Listar especialidades'],
        ['id' => 'list_doctors', 'label' => 'Listar médicos'],
        ['id' => 'list_doctors_by_name', 'label' => 'Buscar médicos por nombre'],
        ['id' => 'list_locations', 'label' => 'Listar sedes'],
        ['id' => 'list_procedures', 'label' => 'Listar procedimientos'],
        ['id' => 'list_times', 'label' => 'Listar horarios'],
        ['id' => 'book_appointment', 'label' => 'Agendar cita'],
        ['id' => 'cancel_appointment', 'label' => 'Cancelar cita'],
        ['id' => 'reschedule_appointment', 'label' => 'Reagendar cita'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        $flow = WhatsappAutoresponderFlow::query()
            ->with([
                'whatsapp_autoresponder_flow_version',
                'whatsapp_autoresponder_flow_versions' => fn ($query) => $query->latest('version')->limit(10),
            ])
            ->where('flow_key', self::DEFAULT_FLOW_KEY)
            ->first();

        $activeVersion = $flow?->whatsapp_autoresponder_flow_version;
        $activeVersionId = $activeVersion?->id;

        return [
            'flow' => $flow !== null ? [
                'id' => (int) $flow->id,
                'flow_key' => (string) $flow->flow_key,
                'name' => (string) $flow->name,
                'description' => $flow->description,
                'status' => (string) $flow->status,
                'timezone' => $flow->timezone,
                'active_from' => $flow->active_from?->format('Y-m-d H:i:s'),
                'active_until' => $flow->active_until?->format('Y-m-d H:i:s'),
                'active_version_id' => $activeVersionId,
                'updated_at' => $flow->updated_at?->format('Y-m-d H:i:s'),
            ] : null,
            'active_version' => $activeVersion !== null ? $this->serializeVersion($activeVersion) : null,
            'versions' => $flow?->whatsapp_autoresponder_flow_versions
                ? $flow->whatsapp_autoresponder_flow_versions->map(fn (WhatsappAutoresponderFlowVersion $version): array => $this->serializeVersion($version))->all()
                : [],
            'stats' => [
                'steps' => $activeVersionId ? WhatsappAutoresponderStep::query()->where('flow_version_id', $activeVersionId)->count() : 0,
                'actions' => $activeVersionId ? WhatsappAutoresponderStepAction::query()
                    ->join('whatsapp_autoresponder_steps as s', 's.id', '=', 'whatsapp_autoresponder_step_actions.step_id')
                    ->where('s.flow_version_id', $activeVersionId)
                    ->count() : 0,
                'transitions' => $activeVersionId ? WhatsappAutoresponderStepTransition::query()
                    ->join('whatsapp_autoresponder_steps as s', 's.id', '=', 'whatsapp_autoresponder_step_transitions.step_id')
                    ->where('s.flow_version_id', $activeVersionId)
                    ->count() : 0,
                'filters' => $activeVersionId ? WhatsappAutoresponderVersionFilter::query()->where('flow_version_id', $activeVersionId)->count() : 0,
                'schedules' => $activeVersionId ? WhatsappAutoresponderSchedule::query()->where('flow_version_id', $activeVersionId)->count() : 0,
                'active_sessions' => WhatsappAutoresponderSession::query()->count(),
                'sessions_waiting_input' => WhatsappAutoresponderSession::query()->where('awaiting', 'input')->count(),
                'sessions_waiting_response' => WhatsappAutoresponderSession::query()->where('awaiting', 'response')->count(),
            ],
            'sessions' => WhatsappAutoresponderSession::query()
                ->orderByDesc('last_interaction_at')
                ->limit(20)
                ->get()
                ->map(fn (WhatsappAutoresponderSession $session): array => [
                    'id' => (int) $session->id,
                    'conversation_id' => (int) $session->conversation_id,
                    'wa_number' => (string) $session->wa_number,
                    'scenario_id' => $session->scenario_id,
                    'node_id' => $session->node_id,
                    'awaiting' => $session->awaiting,
                    'last_interaction_at' => $session->last_interaction_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $session->updated_at?->format('Y-m-d H:i:s'),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getContract(): array
    {
        $overview = $this->getOverview();
        $flow = $this->getActiveFlowPayload();

        return [
            'schema' => $flow,
            'constraints' => [
                'buttonLimit' => self::BUTTON_LIMIT,
                'stageValues' => self::STAGE_VALUES,
            ],
            'storage' => [
                'source' => 'laravel-db',
                'flow_key' => self::DEFAULT_FLOW_KEY,
                'active_version_id' => $overview['flow']['active_version_id'] ?? null,
            ],
            'catalogs' => [
                'variables' => $this->flowVariables(),
                'sigcenter_operations' => self::SIGCENTER_OPERATIONS,
            ],
            'overview' => $overview,
        ];
    }

    /**
     * @return array<int, array{id:string,label:string,group:string,token:string}>
     */
    private function flowVariables(): array
    {
        return array_map(
            static fn (array $variable): array => [
                ...$variable,
                'token' => '{{' . $variable['id'] . '}}',
            ],
            self::FLOW_VARIABLES
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getActiveFlowPayload(): array
    {
        $flow = WhatsappAutoresponderFlow::query()
            ->with('whatsapp_autoresponder_flow_version')
            ->where('flow_key', self::DEFAULT_FLOW_KEY)
            ->first();

        $entrySettings = $flow?->whatsapp_autoresponder_flow_version?->entry_settings;
        $payload = is_array($entrySettings) ? ($entrySettings['flow'] ?? $entrySettings) : null;

        return is_array($payload) ? $payload : $this->defaultFlowPayload();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function publish(array $payload, ?int $userId = null): array
    {
        $flowPayload = $payload['flow'] ?? $payload;
        if (!is_array($flowPayload)) {
            throw new InvalidArgumentException('Flowmaker no envió un flujo válido para publicar.');
        }

        $sanitized = $this->sanitizeFlow($flowPayload);

        return DB::transaction(function () use ($sanitized, $userId): array {
            $flow = WhatsappAutoresponderFlow::query()->firstOrCreate(
                ['flow_key' => self::DEFAULT_FLOW_KEY],
                [
                    'name' => 'Flujo principal de WhatsApp',
                    'description' => 'Configuración del flujo de autorespuesta gestionada desde Laravel.',
                    'status' => 'draft',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            $nextVersion = (int) WhatsappAutoresponderFlowVersion::query()
                ->where('flow_id', $flow->id)
                ->max('version') + 1;

            WhatsappAutoresponderFlowVersion::query()
                ->where('flow_id', $flow->id)
                ->where('status', 'published')
                ->update(['status' => 'archived']);

            $version = WhatsappAutoresponderFlowVersion::query()->create([
                'flow_id' => $flow->id,
                'version' => $nextVersion,
                'status' => 'published',
                'changelog' => 'Publicado desde Laravel Flowmaker',
                'entry_settings' => ['flow' => $sanitized],
                'published_at' => now(),
                'published_by' => $userId,
                'created_by' => $userId,
            ]);

            $stepMap = [];
            $publishedScenarios = array_values(array_filter(
                $sanitized['scenarios'],
                fn (array $scenario): bool => $this->scenarioIsPublished($scenario)
            ));

            foreach ($publishedScenarios as $index => $scenario) {
                $step = WhatsappAutoresponderStep::query()->create([
                    'flow_version_id' => $version->id,
                    'step_key' => (string) $scenario['id'],
                    'step_type' => $this->resolveStepType($scenario),
                    'name' => (string) $scenario['name'],
                    'description' => (string) ($scenario['description'] ?? ''),
                    'order_index' => $index,
                    'is_entry_point' => !empty($scenario['intercept_menu']) || (($scenario['stage'] ?? 'custom') === 'arrival'),
                    'settings' => [
                        'scenario' => $scenario,
                        'stage' => $scenario['stage'] ?? 'custom',
                        'published_via' => 'laravel-flowmaker',
                    ],
                ]);

                $stepMap[(string) $scenario['id']] = $step->id;

                foreach (($scenario['actions'] ?? []) as $actionIndex => $action) {
                    WhatsappAutoresponderStepAction::query()->create([
                        'step_id' => $step->id,
                        'action_type' => (string) ($action['type'] ?? 'send_message'),
                        'template_revision_id' => null,
                        'message_body' => $this->extractActionBody($action),
                        'media_url' => $this->extractActionLink($action),
                        'delay_seconds' => isset($action['seconds']) && is_numeric($action['seconds']) ? max(0, (int) $action['seconds']) : 0,
                        'metadata' => $action,
                        'order_index' => $actionIndex,
                    ]);
                }
            }

            foreach ($publishedScenarios as $index => $scenario) {
                $currentStepId = $stepMap[(string) $scenario['id']] ?? null;
                if (!$currentStepId) {
                    continue;
                }

                $nextKey = $publishedScenarios[$index + 1]['id'] ?? null;
                if ($nextKey !== null && isset($stepMap[(string) $nextKey])) {
                    WhatsappAutoresponderStepTransition::query()->create([
                        'step_id' => $currentStepId,
                        'target_step_id' => $stepMap[(string) $nextKey],
                        'condition_label' => null,
                        'condition_type' => 'always',
                        'condition_payload' => null,
                        'priority' => $index,
                    ]);
                }
            }

            $flow->fill([
                'name' => (string) ($sanitized['name'] ?? 'Flujo principal de WhatsApp'),
                'description' => (string) ($sanitized['description'] ?? 'Flujo publicado desde Laravel'),
                'status' => 'active',
                'timezone' => (string) ($sanitized['settings']['timezone'] ?? 'America/Guayaquil'),
                'active_version_id' => $version->id,
                'updated_by' => $userId,
            ])->save();

            return [
                'status' => 'ok',
                'message' => 'El flujo se publicó correctamente desde Laravel.',
                'flow' => $this->getOverview()['flow'],
                'active_version' => $this->serializeVersion($version->fresh()),
            ];
        });
    }

    /**
     * @param array<string, mixed> $flow
     * @return array<string, mixed>
     */
    private function sanitizeFlow(array $flow): array
    {
        $scenarios = array_values(array_filter($flow['scenarios'] ?? [], static fn ($scenario) => is_array($scenario)));
        if ($scenarios === []) {
            throw new InvalidArgumentException('El flujo debe incluir al menos un escenario.');
        }

        $usedIds = [];
        $normalized = [];
        $publishedCount = 0;
        foreach ($scenarios as $index => $scenario) {
            $id = trim((string) ($scenario['id'] ?? ''));
            if ($id === '') {
                $id = 'scenario_' . ($index + 1);
            }
            $id = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '_', $id));
            if (isset($usedIds[$id])) {
                $id .= '_' . ($index + 1);
            }
            $usedIds[$id] = true;

            $stage = trim((string) ($scenario['stage'] ?? 'custom'));
            if (!in_array($stage, self::STAGE_VALUES, true)) {
                $stage = 'custom';
            }

            $name = trim((string) ($scenario['name'] ?? ''));
            if ($name === '') {
                $name = 'Escenario ' . ($index + 1);
            }

            $actions = array_values(array_filter($scenario['actions'] ?? [], static fn ($action) => is_array($action)));
            if ($actions === []) {
                throw new InvalidArgumentException('Cada escenario debe incluir al menos una acción.');
            }

            $status = trim((string) ($scenario['status'] ?? 'published'));
            if (!in_array($status, ['draft', 'published', 'paused'], true)) {
                $status = 'published';
            }
            if ($status === 'published') {
                $publishedCount++;
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'description' => trim((string) ($scenario['description'] ?? '')),
                'status' => $status,
                'stage' => $stage,
                'stage_id' => $stage,
                'stageId' => $stage,
                'intercept_menu' => (bool) ($scenario['intercept_menu'] ?? false),
                'conditions' => array_values(array_filter($scenario['conditions'] ?? [], static fn ($condition) => is_array($condition))),
                'actions' => $actions,
            ];
        }

        if ($publishedCount <= 0) {
            throw new InvalidArgumentException('Debes dejar al menos un escenario en estado published para publicar el flujo.');
        }

        return [
            'name' => trim((string) ($flow['name'] ?? 'Flujo principal de WhatsApp')),
            'description' => trim((string) ($flow['description'] ?? 'Flujo publicado desde Laravel')),
            'settings' => is_array($flow['settings'] ?? null) ? $flow['settings'] : ['timezone' => 'America/Guayaquil'],
            'scenarios' => $normalized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFlowPayload(): array
    {
        return [
            'name' => 'Flujo principal de WhatsApp',
            'description' => 'Esqueleto base para Flowmaker en Laravel.',
            'settings' => [
                'timezone' => 'America/Guayaquil',
                'no_match_fallback_message' => "No entendí tu mensaje 🤔\nEscribe *MENU* para ver las opciones disponibles.",
            ],
            'scenarios' => [
                [
                    'id' => 'primer_contacto',
                    'name' => 'Primer contacto',
                    'description' => 'Saludo inicial',
                    'status' => 'published',
                    'stage' => 'arrival',
                    'stage_id' => 'arrival',
                    'stageId' => 'arrival',
                    'intercept_menu' => true,
                    'conditions' => [],
                    'actions' => [
                        [
                            'type' => 'send_message',
                            'message' => [
                                'type' => 'text',
                                'body' => 'Hola, soy el asistente virtual. ¿En qué te ayudo?',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVersion(WhatsappAutoresponderFlowVersion $version): array
    {
        return [
            'id' => (int) $version->id,
            'version' => (int) $version->version,
            'status' => (string) $version->status,
            'published_at' => $version->published_at?->format('Y-m-d H:i:s'),
            'published_by' => $version->published_by,
            'created_at' => $version->created_at?->format('Y-m-d H:i:s'),
            'entry_settings' => $version->entry_settings,
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function resolveStepType(array $scenario): string
    {
        if (!empty($scenario['intercept_menu']) || (($scenario['stage'] ?? 'custom') === 'arrival')) {
            return 'trigger';
        }

        if (($scenario['stage'] ?? 'custom') === 'validation') {
            return 'condition';
        }

        return 'message';
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function scenarioIsPublished(array $scenario): bool
    {
        return (string) ($scenario['status'] ?? 'published') === 'published';
    }

    /**
     * @param array<string, mixed> $action
     */
    private function extractActionBody(array $action): ?string
    {
        $message = $action['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $body = $message['body'] ?? null;
        return is_string($body) && trim($body) !== '' ? trim($body) : null;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function extractActionLink(array $action): ?string
    {
        $message = $action['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $link = $message['link'] ?? null;
        return is_string($link) && trim($link) !== '' ? trim($link) : null;
    }
}
