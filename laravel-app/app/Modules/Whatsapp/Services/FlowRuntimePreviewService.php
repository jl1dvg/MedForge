<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Shared\Support\SettingsOptionResolver;
use App\Models\WhatsappAutoresponderSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FlowRuntimePreviewService
{
    private ?SettingsOptionResolver $settingsResolver = null;

    public function __construct(
        private readonly FlowmakerService $flowmakerService = new FlowmakerService(),
        private readonly FlowAiAgentPreviewService $aiAgentPreviewService = new FlowAiAgentPreviewService(),
        private readonly FlowSigcenterAgendaService $sigcenterAgendaService = new FlowSigcenterAgendaService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulate(array $input): array
    {
        return $this->simulateAgainstFlow($this->flowmakerService->getActiveFlowPayload(), $input);
    }

    /**
     * @param array<string, mixed> $flow
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulateAgainstFlow(array $flow, array $input): array
    {
        $waNumber = trim((string) ($input['wa_number'] ?? ''));
        $text = trim((string) ($input['text'] ?? ''));
        $session = $waNumber !== ''
            ? WhatsappAutoresponderSession::query()->where('wa_number', $waNumber)->first()
            : null;
        $sessionContext = is_array($session?->context) ? $session->context : [];
        $overrideContext = is_array($input['context'] ?? null) ? $input['context'] : [];
        $context = array_merge($sessionContext, $overrideContext);
        if (!isset($context['state'])) {
            $context['state'] = 'inicio';
        }
        $context = $this->seedPatientContextFromConversation($context, $waNumber);
        $context = $this->captureAwaitingInput($context, $text);

        $message = [
            'id' => 'preview-' . substr(md5($waNumber . '|' . $text), 0, 12),
            'type' => 'text',
            'text' => ['body' => $text],
        ];
        $facts = $this->buildFacts($waNumber, $text, $session, $context, $message);
        $scenarios = $this->orderedScenariosForSimulation($flow['scenarios'] ?? [], (string) ($input['scenario_id'] ?? ''));

        foreach ($scenarios as $scenario) {
            if (!is_array($scenario)) {
                continue;
            }
            if (!$this->scenarioIsPublished($scenario)) {
                continue;
            }

            if (!$this->scenarioMatches($scenario, $facts)) {
                continue;
            }

            $result = $this->simulateActions($scenario['actions'] ?? [], $context, $input, (string) ($scenario['id'] ?? ''));

            return [
                'ok' => true,
                'matched' => true,
                'scenario' => [
                    'id' => $scenario['id'] ?? null,
                    'name' => $scenario['name'] ?? null,
                    'stage' => $scenario['stage'] ?? null,
                ],
                'facts' => $facts,
                'actions' => $result['actions'],
                'context_before' => $context,
                'context_after' => $result['context'],
                'handoff_requested' => (bool) ($result['context']['handoff_requested'] ?? false),
                'session_snapshot' => $session !== null ? [
                    'scenario_id' => $session->scenario_id,
                    'node_id' => $session->node_id,
                    'awaiting' => $session->awaiting,
                    'last_interaction_at' => $session->last_interaction_at?->format('Y-m-d H:i:s'),
                ] : null,
            ];
        }

        return [
            'ok' => true,
            'matched' => false,
            'scenario' => null,
            'facts' => $facts,
            'actions' => [],
            'context_before' => $context,
            'context_after' => $context,
            'handoff_requested' => false,
            'session_snapshot' => $session !== null ? [
                'scenario_id' => $session->scenario_id,
                'node_id' => $session->node_id,
                'awaiting' => $session->awaiting,
                'last_interaction_at' => $session->last_interaction_at?->format('Y-m-d H:i:s'),
            ] : null,
        ];
    }

    /**
     * @param mixed $scenarios
     * @return array<int, mixed>
     */
    private function orderedScenariosForSimulation(mixed $scenarios, string $preferredScenarioId): array
    {
        if (!is_array($scenarios) || $preferredScenarioId === '') {
            return is_array($scenarios) ? array_values($scenarios) : [];
        }

        $preferred = [];
        $others = [];
        foreach ($scenarios as $scenario) {
            if (is_array($scenario) && (string) ($scenario['id'] ?? '') === $preferredScenarioId) {
                $preferred[] = $scenario;
                continue;
            }
            $others[] = $scenario;
        }

        return array_values(array_merge($preferred, $others));
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $facts
     */
    private function scenarioMatches(array $scenario, array $facts): bool
    {
        foreach (($scenario['conditions'] ?? []) as $condition) {
            if (!is_array($condition) || !$this->evaluateCondition($condition, $facts)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function evaluateCondition(array $condition, array $facts): bool
    {
        $type = $condition['type'] ?? 'always';

        return match ($type) {
            'always' => true,
            'all' => $this->conditionsAllMatch($condition, $facts),
            'any' => $this->conditionsAnyMatch($condition, $facts),
            'is_first_time' => (bool) ($condition['value'] ?? false) === (bool) ($facts['is_first_time'] ?? false),
            'has_consent' => (bool) ($condition['value'] ?? false) === (bool) ($facts['has_consent'] ?? false),
            'state_is' => ($facts['state'] ?? null) === ($condition['value'] ?? null),
            'state_equals' => ($facts['state'] ?? null) === ($condition['value'] ?? null),
            'awaiting_is' => ($facts['awaiting_field'] ?? null) === ($condition['value'] ?? null),
            'message_in' => $this->messageIn($condition, $facts),
            'message_contains' => $this->messageContains($condition, $facts),
            'message_equals' => $this->messageIn(['values' => [$condition['value'] ?? '']], $facts),
            'message_matches' => $this->messageMatches($condition, $facts),
            'last_interaction_gt' => (int) ($facts['minutes_since_last'] ?? 0) >= max(0, (int) ($condition['minutes'] ?? 0)),
            'patient_found' => (bool) ($facts['patient_found'] ?? false),
            'context_flag' => $this->contextFlagMatches($condition, $facts),
            'context_equals' => $this->contextValueMatches($condition, $facts, false),
            'context_contains' => $this->contextValueMatches($condition, $facts, true),
            default => false,
        };
    }

    private function conditionsAllMatch(array $condition, array $facts): bool
    {
        $conditions = is_array($condition['conditions'] ?? null) ? $condition['conditions'] : [];
        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $child) {
            if (!is_array($child) || !$this->evaluateCondition($child, $facts)) {
                return false;
            }
        }

        return true;
    }

    private function conditionsAnyMatch(array $condition, array $facts): bool
    {
        $conditions = is_array($condition['conditions'] ?? null) ? $condition['conditions'] : [];
        foreach ($conditions as $child) {
            if (is_array($child) && $this->evaluateCondition($child, $facts)) {
                return true;
            }
        }

        return false;
    }

    private function contextValueMatches(array $condition, array $facts, bool $contains): bool
    {
        $field = (string) ($condition['field'] ?? $condition['variable'] ?? $condition['key'] ?? '');
        if ($field === '') {
            return false;
        }

        $actual = $this->normalizeText((string) ($facts[$field] ?? ''));
        $expected = $this->normalizeText((string) ($condition['value'] ?? ''));

        return $contains
            ? $expected !== '' && str_contains($actual, $expected)
            : $actual === $expected;
    }

    /**
     * @param array<int, mixed> $actions
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array{actions: array<int, array<string, mixed>>, context: array<string, mixed>}
     */
    private function simulateActions(array $actions, array $context, array $input, string $scenarioId): array
    {
        $emitted = [];
        unset($context['handoff_requested'], $context['handoff_reasons'], $context['handoff_note']);

        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                continue;
            }

            $type = (string) ($action['type'] ?? '');
            if ($type === '') {
                continue;
            }

            if ($type === 'lookup_patient') {
                $context = $this->lookupPatient($action, $context, $input);
                $emitted[] = [
                    'type' => $type,
                    'field' => $action['field'] ?? 'cedula',
                    'patient_found' => (bool) ($context['patient_found'] ?? false),
                    'identifier' => $context['cedula'] ?? $context['identifier'] ?? null,
                    'patient' => $context['patient'] ?? null,
                ];
                continue;
            }

            if ($type === 'conditional') {
                $matches = $this->actionConditionMatches(is_array($action['condition'] ?? null) ? $action['condition'] : [], $context);
                $branch = $matches ? ($action['then'] ?? []) : ($action['else'] ?? []);
                $emitted[] = [
                    'type' => $type,
                    'condition' => $action['condition'] ?? null,
                    'matched' => $matches,
                    'branch' => $matches ? 'then' : 'else',
                ];
                if (is_array($branch)) {
                    $run = $this->simulateActions($branch, $context, $input, $scenarioId);
                    $context = $run['context'];
                    $emitted = array_merge($emitted, $run['actions']);
                }
                continue;
            }

            if (in_array($type, ['send_message', 'send_buttons', 'send_list', 'send_template', 'send_sequence'], true)) {
                $emitted[] = [
                    'type' => $type,
                    'message' => $action['message'] ?? null,
                    'template' => $action['template'] ?? null,
                    'messages' => $action['messages'] ?? null,
                ];
                continue;
            }

            if ($type === 'set_state') {
                $context['state'] = $action['state'] ?? 'inicio';
                $emitted[] = ['type' => $type, 'state' => $context['state']];
                continue;
            }

            if ($type === 'set_context') {
                foreach (($action['values'] ?? []) as $key => $value) {
                    $context[$key] = $value;
                }
                $emitted[] = ['type' => $type, 'values' => $action['values'] ?? []];
                continue;
            }

            if ($type === 'store_consent') {
                $context['consent'] = (bool) ($action['value'] ?? true);
                $emitted[] = ['type' => $type, 'value' => $context['consent']];
                continue;
            }

            if ($type === 'handoff_agent') {
                $context['handoff_requested'] = true;
                if (isset($action['role_id']) && is_numeric($action['role_id'])) {
                    $context['handoff_role_id'] = (int) $action['role_id'];
                }
                if (isset($action['note']) && is_string($action['note'])) {
                    $context['handoff_note'] = $action['note'];
                }
                $emitted[] = [
                    'type' => $type,
                    'role_id' => $context['handoff_role_id'] ?? null,
                    'note' => $context['handoff_note'] ?? null,
                ];
                continue;
            }

            if ($type === 'conditional') {
                $emitted[] = ['type' => $type, 'condition' => $action['condition'] ?? null];
                continue;
            }

            if ($type === 'ai_agent') {
                $preview = $this->aiAgentPreviewService->preview(
                    array_merge($action, [
                        'scenario_id' => $scenarioId,
                        'action_index' => $index,
                    ]),
                    $input,
                    $context
                );
                $context = is_array($preview['context_after'] ?? null) ? $preview['context_after'] : $context;
                if (!empty($preview['suggested_handoff'])) {
                    $context['handoff_requested'] = true;
                    $context['handoff_reasons'] = is_array($preview['handoff_reasons'] ?? null) ? $preview['handoff_reasons'] : [];
                    $context['handoff_note'] = $this->buildAiHandoffNote($context['handoff_reasons']);
                }
                $emitted[] = [
                    'type' => $type,
                    'response' => $preview['response'] ?? null,
                    'classification' => $preview['classification'] ?? null,
                    'confidence' => $preview['confidence'] ?? null,
                    'suggested_handoff' => (bool) ($preview['suggested_handoff'] ?? false),
                    'decision' => $preview['decision'] ?? null,
                    'fallback_used' => (bool) ($preview['fallback_used'] ?? false),
                    'handoff_reasons' => $preview['handoff_reasons'] ?? [],
                    'scores' => $preview['scores'] ?? [],
                    'evaluation' => $preview['evaluation'] ?? [],
                    'tools' => $preview['tools'] ?? [],
                    'sources' => array_map(
                        static fn (array $document): array => [
                            'id' => $document['id'] ?? null,
                            'title' => $document['title'] ?? null,
                        ],
                        array_values(array_filter($preview['sources'] ?? [], 'is_array'))
                    ),
                    'run_id' => $preview['run_id'] ?? null,
                ];
                continue;
            }

            if ($type === 'sigcenter_agenda') {
                if ($this->shouldRequirePatientIdentifierForAgenda($action, $context) && !$this->hasPatientIdentifier($context)) {
                    $context['state'] = 'esperando_cedula';
                    $context['awaiting_field'] = 'cedula';
                    $emitted[] = [
                        'type' => 'request_patient_identifier',
                        'message' => [
                            'type' => 'text',
                            'body' => 'Antes de agendar, por favor escribe tu número de cédula.',
                        ],
                    ];
                    continue;
                }
                $preview = $this->sigcenterAgendaService->preview($action, $context, $input);
                $context[(string) $preview['store_result_as']] = [
                    'operation' => $preview['operation'],
                    'ready' => $preview['ready'],
                    'preview_only' => true,
                ];
                if (!empty($preview['send_result'])) {
                    if (is_string($preview['save_response_as'] ?? null) && $preview['save_response_as'] !== '') {
                        $context['awaiting_field'] = $preview['save_response_as'];
                    }
                    if (is_string($preview['next_state'] ?? null) && $preview['next_state'] !== '') {
                        $context['state'] = $preview['next_state'];
                    }
                }
                $emitted[] = $preview;
                continue;
            }

            if ($type === 'upsert_patient_from_context') {
                $context = $this->upsertPatientFromContext($context);
                $emitted[] = [
                    'type' => $type,
                    'identifier' => $context['cedula'] ?? $context['identifier'] ?? null,
                ];
                continue;
            }

            if ($type === 'goto_menu') {
                $emitted[] = [
                    'type' => $type,
                    'message' => $this->mainMenuMessage(),
                ];
                continue;
            }

            if ($type === 'show_active_booking') {
                $emitted[] = [
                    'type' => $type,
                    'message' => [
                        'type' => 'text',
                        'body' => 'Muestra la cita vigente del paciente si existe; si no existe, informa que no tiene citas registradas.',
                    ],
                ];
                continue;
            }

            if ($type === 'show_specialties_catalog') {
                $emitted[] = [
                    'type' => $type,
                    'message' => [
                        'type' => 'text',
                        'body' => 'Muestra el catálogo actual de especialidades disponibles.',
                    ],
                ];
                continue;
            }

            if ($type === 'persist_lead_capture') {
                $emitted[] = [
                    'type' => $type,
                    'message' => [
                        'type' => 'meta',
                        'body' => 'Persiste correo y origen del lead en atribución de conversación y CRM.',
                    ],
                ];
                continue;
            }

            $emitted[] = ['type' => $type];
        }

        return ['actions' => $emitted, 'context' => $context];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function seedPatientContextFromConversation(array $context, string $waNumber): array
    {
        if ($waNumber === '') {
            return $context;
        }

        $conversation = DB::table('whatsapp_conversations')->where('wa_number', $waNumber)->first();
        if ($conversation === null) {
            return $context;
        }

        $identifier = $this->normalizeIdentifier((string) ($conversation->patient_hc_number ?? ''));
        if ($identifier !== '') {
            $context['cedula'] ??= $identifier;
            $context['identifier'] ??= $identifier;
            $context['current_identifier'] ??= $identifier;
            $context['patient_found'] = true;
        }

        $fullName = trim((string) ($conversation->patient_full_name ?? ''));
        if ($fullName !== '' && !isset($context['patient'])) {
            $context['patient'] = [
                'hc_number' => $identifier,
                'full_name' => $fullName,
            ];
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function captureAwaitingInput(array $context, string $text): array
    {
        $field = $context['awaiting_field'] ?? null;
        if (!is_string($field) || trim($field) === '' || trim($text) === '') {
            return $context;
        }

        $value = trim($text);
        $context[trim($field)] = $value;
        if (in_array(trim($field), ['cedula', 'identificacion', 'identifier', 'hc_number'], true)) {
            $context['cedula'] = $this->normalizeIdentifier($value);
            $context['identifier'] = $context['cedula'];
            $context['current_identifier'] = $context['cedula'];
        }
        unset($context['awaiting_field']);

        return $context;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function lookupPatient(array $action, array $context, array $input): array
    {
        $field = trim((string) ($action['field'] ?? 'cedula'));
        $source = trim((string) ($action['source'] ?? 'context'));
        $identifier = $source === 'message'
            ? $this->normalizeIdentifier((string) ($input['text'] ?? ''))
            : $this->normalizeIdentifier((string) ($context[$field] ?? $context['cedula'] ?? $context['identifier'] ?? ''));

        if ($identifier === '') {
            $context['patient_found'] = false;
            return $context;
        }

        $context['cedula'] = $identifier;
        $context['identifier'] = $identifier;
        $context['current_identifier'] = $identifier;

        $patient = DB::table('patient_data')
            ->whereRaw("REPLACE(TRIM(COALESCE(hc_number, '')), ' ', '') = ?", [$identifier])
            ->first();

        if ($patient === null) {
            $context['patient_found'] = false;
            return $context;
        }

        $fullName = $this->patientFullName((array) $patient);
        $context['patient_found'] = true;
        $context['patient'] = [
            'hc_number' => $identifier,
            'full_name' => $fullName !== '' ? $fullName : $identifier,
        ];

        return $context;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function actionConditionMatches(array $condition, array $context): bool
    {
        $type = (string) ($condition['type'] ?? 'always');

        return match ($type) {
            'always' => true,
            'all' => $this->actionConditionsAllMatch($condition, $context),
            'any' => $this->actionConditionsAnyMatch($condition, $context),
            'patient_found' => (bool) ($condition['value'] ?? true) === (bool) ($context['patient_found'] ?? isset($context['patient'])),
            'context_flag' => $this->contextActionFlagMatches($condition, $context),
            'context_equals' => $this->contextActionValueMatches($condition, $context, false),
            'context_contains' => $this->contextActionValueMatches($condition, $context, true),
            'state_equals' => ($context['state'] ?? null) === ($condition['value'] ?? null),
            default => false,
        };
    }

    private function actionConditionsAllMatch(array $condition, array $context): bool
    {
        $conditions = is_array($condition['conditions'] ?? null) ? $condition['conditions'] : [];
        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $child) {
            if (!is_array($child) || !$this->actionConditionMatches($child, $context)) {
                return false;
            }
        }

        return true;
    }

    private function actionConditionsAnyMatch(array $condition, array $context): bool
    {
        $conditions = is_array($condition['conditions'] ?? null) ? $condition['conditions'] : [];
        foreach ($conditions as $child) {
            if (is_array($child) && $this->actionConditionMatches($child, $context)) {
                return true;
            }
        }

        return false;
    }

    private function contextActionValueMatches(array $condition, array $context, bool $contains): bool
    {
        $key = (string) ($condition['field'] ?? $condition['variable'] ?? $condition['key'] ?? '');
        if ($key === '') {
            return false;
        }

        $actual = $this->normalizeText((string) ($context[$key] ?? ''));
        $expected = $this->normalizeText((string) ($condition['value'] ?? ''));

        return $contains
            ? $expected !== '' && str_contains($actual, $expected)
            : $actual === $expected;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function contextActionFlagMatches(array $condition, array $context): bool
    {
        $key = (string) ($condition['key'] ?? '');
        if ($key === '') {
            return false;
        }

        if (!array_key_exists('value', $condition)) {
            return !empty($context[$key]);
        }

        return ($context[$key] ?? null) == $condition['value'];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function upsertPatientFromContext(array $context): array
    {
        $identifier = $this->normalizeIdentifier((string) ($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? ''));
        if ($identifier !== '') {
            $context['cedula'] = $identifier;
            $context['identifier'] = $identifier;
            $context['current_identifier'] = $identifier;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function hasPatientIdentifier(array $context): bool
    {
        return $this->normalizeIdentifier((string) ($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function shouldRequirePatientIdentifierForAgenda(array $action, array $context): bool
    {
        $operation = (string) ($action['operation'] ?? '');
        $state = (string) ($context['state'] ?? '');

        return $operation === 'book_appointment'
            || str_starts_with($state, 'agenda_')
            || !empty($context['consent']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function patientFullName(array $row): string
    {
        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter([
            (string) ($row['fname'] ?? ''),
            (string) ($row['mname'] ?? ''),
            (string) ($row['lname'] ?? ''),
            (string) ($row['lname2'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== ''))) ?? '');
    }

    private function normalizeIdentifier(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: trim($value);
    }

    /**
     * @param array<int, string> $reasons
     */
    private function buildAiHandoffNote(array $reasons): string
    {
        if ($reasons === []) {
            return 'AI Agent sugirió handoff.';
        }

        return 'AI Agent sugirió handoff por: ' . implode(', ', $reasons) . '.';
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function scenarioIsPublished(array $scenario): bool
    {
        return (string) ($scenario['status'] ?? 'published') === 'published';
    }

    /**
     * @param array<string, mixed>|WhatsappAutoresponderSession|null $session
     * @param array<string, mixed> $context
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function buildFacts(string $waNumber, string $text, ?WhatsappAutoresponderSession $session, array $context, array $message): array
    {
        $normalized = $this->normalizeText($text);
        $lastInteraction = $session?->last_interaction_at;
        $facts = [
            'is_first_time' => $session === null && empty($context['consent']),
            'state' => $context['state'] ?? 'inicio',
            'awaiting_field' => $context['awaiting_field'] ?? null,
            'has_consent' => !empty($context['consent']),
            'message' => $normalized,
            'raw_message' => $text,
            'patient_found' => isset($context['patient']),
            'consent_identifier' => $context['identifier'] ?? null,
            'current_identifier' => $context['cedula'] ?? ($context['identifier'] ?? null),
            'wa_number' => $waNumber,
            'message_id' => $message['id'] ?? null,
        ];

        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if ($digits !== '') {
            $facts['digits'] = $digits;
        }

        if ($lastInteraction instanceof Carbon) {
            $facts['minutes_since_last'] = $lastInteraction->diffInMinutes(now());
        }

        return $facts;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9 ]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageIn(array $condition, array $facts): bool
    {
        $values = $this->conditionTextList($condition, 'values');
        $needle = (string) ($facts['message'] ?? '');
        foreach ($values as $value) {
            if (is_string($value) && $needle === $this->normalizeText($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageContains(array $condition, array $facts): bool
    {
        $keywords = $this->conditionTextList($condition, 'keywords');
        $needle = (string) ($facts['message'] ?? '');
        foreach ($keywords as $keyword) {
            if (is_string($keyword) && $keyword !== '' && str_contains($needle, $this->normalizeText($keyword))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageMatches(array $condition, array $facts): bool
    {
        $pattern = $condition['pattern'] ?? $condition['value'] ?? '';
        if (!is_string($pattern) || trim($pattern) === '') {
            return false;
        }
        $regex = '~' . str_replace('~', '\\~', trim($pattern)) . '~u';
        foreach ([(string) ($facts['raw_message'] ?? ''), (string) ($facts['message'] ?? ''), (string) ($facts['digits'] ?? '')] as $candidate) {
            if ($candidate !== '' && @preg_match($regex, $candidate) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @return array<int, string>
     */
    private function conditionTextList(array $condition, string $listKey): array
    {
        $values = $condition[$listKey] ?? null;
        if (is_array($values)) {
            return array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $values
            ), static fn (string $value): bool => $value !== ''));
        }

        $value = $condition['value'] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map(
                static fn (string $item): string => trim($item),
                explode(',', $value)
            ), static fn (string $item): bool => $item !== ''));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function contextFlagMatches(array $condition, array $facts): bool
    {
        $key = $condition['key'] ?? '';
        if (!is_string($key) || $key === '') {
            return false;
        }
        $value = $facts[$key] ?? null;
        if (!array_key_exists('value', $condition)) {
            return (bool) $value;
        }
        return $condition['value'] == $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function mainMenuMessage(): array
    {
        return [
            'type' => 'list',
            'body' => "👁️ *¿En qué puedo ayudarte hoy?*",
            'button_text' => '✨ Ver opciones',
            'sections' => [[
                'title' => 'Opciones',
                'rows' => $this->mainMenuRows(),
            ]],
        ];
    }

    /**
     * @return array<int, array{id:string,title:string,description:string}>
     */
    private function mainMenuRows(): array
    {
        $options = $this->settingsOptions([
            'whatsapp_menu_agendar_enabled',
            'whatsapp_menu_consultar_cita_enabled',
            'whatsapp_menu_servicios_sedes_enabled',
            'whatsapp_menu_promociones_enabled',
            'whatsapp_menu_ayuda_enabled',
        ]);

        $catalog = [
            ['id' => 'agendar', 'title' => '📅 Agendar cita', 'description' => 'Programa una nueva cita médica', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_agendar_enabled', true)],
            ['id' => 'consultar_cita', 'title' => '📄 Consultar cita', 'description' => 'Revisa tu cita vigente', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_consultar_cita_enabled', true)],
            ['id' => 'servicios_y_sedes', 'title' => '📍 Servicios y sedes', 'description' => 'Sedes, horarios y especialidades', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_servicios_sedes_enabled', true)],
            ['id' => 'promociones', 'title' => '🎁 Promociones', 'description' => 'Consulta campañas vigentes', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_promociones_enabled', true)],
            ['id' => 'ayuda', 'title' => '🆘 Ayuda', 'description' => 'Hablar con un asesor', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_ayuda_enabled', true)],
        ];

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
            ],
            array_filter($catalog, static fn (array $row): bool => (bool) ($row['enabled'] ?? false))
        ));
    }

    /**
     * @param array<string,string> $options
     */
    private function settingFlag(array $options, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        return in_array(strtolower(trim((string) $options[$key])), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<int,string> $keys
     * @return array<string,string>
     */
    private function settingsOptions(array $keys): array
    {
        if ($this->settingsResolver === null) {
            $this->settingsResolver = new SettingsOptionResolver();
        }

        return $this->settingsResolver->getOptions($keys);
    }
}
