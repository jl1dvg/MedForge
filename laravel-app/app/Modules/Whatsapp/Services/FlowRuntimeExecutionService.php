<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAutoresponderSession;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FlowRuntimeExecutionService
{
    public function __construct(
        private readonly FlowmakerService $flowmakerService = new FlowmakerService(),
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly CloudApiTransportService $transport = new CloudApiTransportService(),
        private readonly FlowSigcenterAgendaService $sigcenterAgendaService = new FlowSigcenterAgendaService(),
        private readonly FlowAiAgentPreviewService $aiAgentPreviewService = new FlowAiAgentPreviewService(),
    ) {
    }

    /**
     * @param array<string, mixed> $messagePayload
     * @return array{executed:bool,matched:bool,scenario_id:?string,messages_sent:int,handoff_requested:bool,reason:?string}
     */
    public function executeInbound(WhatsappConversation $conversation, WhatsappMessage $inboundMessage, array $messagePayload): array
    {
        if (!(bool) config('whatsapp.migration.automation.enabled', false)) {
            return $this->result(false, false, null, 0, false, 'automation_disabled');
        }

        if ((bool) ($conversation->assigned_user_id ?? false)) {
            return $this->result(false, false, null, 0, false, 'conversation_assigned');
        }

        $text = trim((string) ($inboundMessage->body ?? ''));
        if ($text === '') {
            return $this->result(false, false, null, 0, false, 'empty_text');
        }

        $flow = $this->flowmakerService->getActiveFlowPayload();
        $session = WhatsappAutoresponderSession::query()
            ->where('conversation_id', $conversation->id)
            ->first();

        $context = is_array($session?->context) ? $session->context : [];
        if (!isset($context['state'])) {
            $context['state'] = 'inicio';
        }
        $context = $this->seedPatientContextFromConversation($context, $conversation);
        $context = $this->captureAwaitingInput($context, $text);

        $facts = $this->buildFacts($conversation, $inboundMessage, $session, $context, $text, $messagePayload);

        foreach (($flow['scenarios'] ?? []) as $scenario) {
            if (!is_array($scenario) || !$this->scenarioIsPublished($scenario)) {
                continue;
            }

            if ($this->shouldSkipCatchAllFallbackDuringAgenda($scenario, $facts)) {
                continue;
            }

            if (!$this->scenarioMatches($scenario, $facts)) {
                continue;
            }

            $run = $this->executeActions($scenario['actions'] ?? [], $context, $conversation, $inboundMessage, $text, (string) ($scenario['id'] ?? ''));
            $context = $run['context'];

            WhatsappAutoresponderSession::query()->updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'wa_number' => (string) $conversation->wa_number,
                    'scenario_id' => (string) ($scenario['id'] ?? 'scenario'),
                    'node_id' => null,
                    'awaiting' => isset($context['awaiting_field']) ? 'input' : null,
                    'context' => $context,
                    'last_payload' => $messagePayload,
                    'last_interaction_at' => now(),
                ]
            );

            if (!empty($context['handoff_requested'])) {
                $this->markConversationForHandoff($conversation, $scenario, $context);
            }

            return $this->result(true, true, (string) ($scenario['id'] ?? 'scenario'), $run['messages_sent'], !empty($context['handoff_requested']), null);
        }

        WhatsappAutoresponderSession::query()->updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'wa_number' => (string) $conversation->wa_number,
                'scenario_id' => $session?->scenario_id,
                'node_id' => $session?->node_id,
                'awaiting' => isset($context['awaiting_field']) ? 'input' : null,
                'context' => $context,
                'last_payload' => $messagePayload,
                'last_interaction_at' => now(),
            ]
        );

        return $this->result(true, false, null, 0, false, 'no_matching_scenario');
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
     * @param array<int, mixed> $actions
     * @param array<string, mixed> $context
     * @return array{context:array<string,mixed>,messages_sent:int}
     */
    private function executeActions(array $actions, array $context, WhatsappConversation $conversation, WhatsappMessage $inboundMessage, string $text, string $scenarioId, bool $resetFlags = true): array
    {
        $messagesSent = 0;
        if ($resetFlags) {
            unset($context['handoff_requested'], $context['handoff_reasons'], $context['handoff_note']);
        }

        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                continue;
            }

            $type = (string) ($action['type'] ?? '');
            if ($type === '') {
                continue;
            }

            if ($type === 'lookup_patient') {
                $context = $this->lookupPatient($action, $context, $conversation, $text);
                continue;
            }

            if ($type === 'conditional') {
                $branch = $this->actionConditionMatches(is_array($action['condition'] ?? null) ? $action['condition'] : [], $context)
                    ? ($action['then'] ?? [])
                    : ($action['else'] ?? []);
                if (is_array($branch)) {
                    $run = $this->executeActions($branch, $context, $conversation, $inboundMessage, $text, $scenarioId, false);
                    $context = $run['context'];
                    $messagesSent += $run['messages_sent'];
                }
                continue;
            }

            if (in_array($type, ['send_message', 'send_buttons', 'send_list'], true)) {
                $message = $action['message'] ?? null;
                if (is_array($message)) {
                    $this->sendFlowMessage($conversation, $message, $context);
                    $messagesSent++;
                }
                continue;
            }

            if ($type === 'send_sequence') {
                foreach (($action['messages'] ?? []) as $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    $this->sendFlowMessage($conversation, $message, $context);
                    $messagesSent++;
                }
                continue;
            }

            if ($type === 'send_template' && is_array($action['template'] ?? null)) {
                $this->sendTemplate($conversation, $action['template']);
                $messagesSent++;
                continue;
            }

            if ($type === 'sigcenter_agenda') {
                if ($this->shouldRequirePatientIdentifierForAgenda($action, $context) && !$this->hasPatientIdentifier($context, $conversation)) {
                    $this->sendFlowMessage($conversation, [
                        'type' => 'text',
                        'body' => 'Antes de agendar, por favor escribe tu número de cédula.',
                    ], $context);
                    $messagesSent++;
                    $context['state'] = 'esperando_cedula';
                    $context['awaiting_field'] = 'cedula';
                    continue;
                }

                $preview = $this->sigcenterAgendaService->execute($action, $context, [
                    'wa_number' => $conversation->wa_number,
                    'text' => $text,
                    'conversation_id' => $conversation->id,
                    'current_identifier' => $context['current_identifier'] ?? $conversation->patient_hc_number,
                    'cedula' => $context['cedula'] ?? $conversation->patient_hc_number,
                ], $this->bookingIsConfirmed($action, $context, $text));

                $context[(string) ($preview['store_result_as'] ?? 'sigcenter_result')] = [
                    'operation' => $preview['operation'] ?? null,
                    'ready' => $preview['ready'] ?? false,
                    'data' => $preview['data'] ?? null,
                    'executed_at' => now()->toISOString(),
                ];

                if (!empty($preview['send_result']) && is_array($preview['outbound_message'] ?? null)) {
                    $this->sendFlowMessage($conversation, $preview['outbound_message'], $context);
                    $messagesSent++;
                    if (is_string($preview['save_response_as'] ?? null) && $preview['save_response_as'] !== '') {
                        $context['awaiting_field'] = $preview['save_response_as'];
                    }
                    if (is_string($preview['next_state'] ?? null) && $preview['next_state'] !== '') {
                        $context['state'] = $preview['next_state'];
                    }
                }
                continue;
            }

            if ($type === 'set_state') {
                $context['state'] = (string) ($action['state'] ?? 'inicio');
                continue;
            }

            if ($type === 'set_context') {
                foreach (($action['values'] ?? []) as $key => $value) {
                    if (is_scalar($value)) {
                        $context[(string) $key] = $value;
                    }
                }
                continue;
            }

            if ($type === 'upsert_patient_from_context') {
                $context = $this->upsertPatientFromContext($context, $conversation);
                continue;
            }

            if ($type === 'goto_menu') {
                $this->sendFlowMessage($conversation, $this->mainMenuMessage(), $context);
                $messagesSent++;
                continue;
            }

            if ($type === 'store_consent') {
                $context['consent'] = (bool) ($action['value'] ?? true);
                continue;
            }

            if ($type === 'handoff_agent') {
                $context['handoff_requested'] = true;
                if (isset($action['role_id']) && is_numeric($action['role_id'])) {
                    $context['handoff_role_id'] = (int) $action['role_id'];
                }
                if (isset($action['note']) && is_string($action['note'])) {
                    $context['handoff_note'] = $this->renderPlaceholders($action['note'], $context);
                }
                continue;
            }

            if ($type === 'ai_agent') {
                $preview = $this->aiAgentPreviewService->preview(
                    array_merge($action, [
                        'scenario_id' => $scenarioId,
                        'action_index' => $index,
                    ]),
                    [
                        'wa_number' => $conversation->wa_number,
                        'text' => $text,
                        'conversation_id' => $conversation->id,
                    ],
                    $context
                );
                $context = is_array($preview['context_after'] ?? null) ? $preview['context_after'] : $context;
                $response = trim((string) ($preview['response'] ?? ''));
                if ($response !== '') {
                    $this->sendFlowMessage($conversation, ['type' => 'text', 'body' => $response], $context);
                    $messagesSent++;
                }
                if (!empty($preview['suggested_handoff'])) {
                    $context['handoff_requested'] = true;
                    $context['handoff_reasons'] = is_array($preview['handoff_reasons'] ?? null) ? $preview['handoff_reasons'] : [];
                    $context['handoff_note'] = 'AI Agent sugirió handoff.';
                }
            }
        }

        return ['context' => $context, 'messages_sent' => $messagesSent];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function seedPatientContextFromConversation(array $context, WhatsappConversation $conversation): array
    {
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
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function lookupPatient(array $action, array $context, WhatsappConversation $conversation, string $text): array
    {
        $field = trim((string) ($action['field'] ?? 'cedula'));
        $source = trim((string) ($action['source'] ?? 'context'));
        $identifier = $source === 'message'
            ? $this->normalizeIdentifier($text)
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
            'fname' => (string) ($patient->fname ?? ''),
            'mname' => (string) ($patient->mname ?? ''),
            'lname' => (string) ($patient->lname ?? ''),
            'lname2' => (string) ($patient->lname2 ?? ''),
        ];

        $conversation->fill([
            'patient_hc_number' => $identifier,
            'patient_full_name' => $context['patient']['full_name'],
            'needs_human' => false,
        ]);
        $conversation->save();

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
            'patient_found' => (bool) ($condition['value'] ?? true) === (bool) ($context['patient_found'] ?? isset($context['patient'])),
            'context_flag' => $this->contextActionFlagMatches($condition, $context),
            default => false,
        };
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
    private function upsertPatientFromContext(array $context, WhatsappConversation $conversation): array
    {
        $identifier = $this->normalizeIdentifier((string) ($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? ''));
        if ($identifier === '') {
            return $context;
        }

        $context['cedula'] = $identifier;
        $context['identifier'] = $identifier;
        $context['current_identifier'] = $identifier;

        $conversation->fill([
            'patient_hc_number' => $identifier,
            'patient_full_name' => $conversation->patient_full_name ?: ($conversation->display_name ?: null),
        ]);
        $conversation->save();

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function hasPatientIdentifier(array $context, WhatsappConversation $conversation): bool
    {
        return $this->normalizeIdentifier((string) ($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? $conversation->patient_hc_number ?? '')) !== '';
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
     * @return array{type:string,body:string,buttons:array<int,array{id:string,title:string}>}
     */
    private function mainMenuMessage(): array
    {
        return [
            'type' => 'buttons',
            'body' => '¿En qué puedo ayudarte?',
            'buttons' => [
                ['id' => 'especialidades0', 'title' => 'Agendar cita'],
                ['id' => 'ayuda', 'title' => 'Ayuda'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $facts
     */
    private function shouldSkipCatchAllFallbackDuringAgenda(array $scenario, array $facts): bool
    {
        $state = (string) ($facts['state'] ?? '');
        if (!str_starts_with($state, 'agenda_')) {
            return false;
        }

        $conditions = array_values(array_filter($scenario['conditions'] ?? [], 'is_array'));
        if ($conditions === []) {
            return true;
        }

        $hasAgendaSpecificCondition = false;
        foreach ($conditions as $condition) {
            $type = (string) ($condition['type'] ?? '');
            if (in_array($type, ['state_is', 'awaiting_is'], true)) {
                $hasAgendaSpecificCondition = true;
                break;
            }
        }

        if ($hasAgendaSpecificCondition) {
            return false;
        }

        $id = mb_strtolower((string) ($scenario['id'] ?? ''), 'UTF-8');
        $name = mb_strtolower((string) ($scenario['name'] ?? ''), 'UTF-8');

        return str_contains($id, 'fallback') || str_contains($name, 'fallback');
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function bookingIsConfirmed(array $action, array $context, string $text): bool
    {
        $operation = (string) ($action['operation'] ?? '');
        if (!in_array($operation, ['agendar', 'book', 'book_appointment'], true)) {
            return false;
        }

        foreach ([$action['confirmed'] ?? null, $action['confirmation_granted'] ?? null] as $value) {
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        foreach (['sigcenter_booking_confirmed', 'appointment_confirmed', 'cita_confirmada'] as $key) {
            $value = $context[$key] ?? null;
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        $contextKey = $action['confirmation_context_key'] ?? null;
        if (is_string($contextKey) && $contextKey !== '') {
            $value = $context[$contextKey] ?? null;
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        return in_array($this->normalizeText($text), [
            'confirmo',
            'confirmar',
            'si confirmo',
            'si agendar',
            'agendar',
            'crear cita',
            'acepto',
        ], true);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $context
     */
    private function sendFlowMessage(WhatsappConversation $conversation, array $message, array $context): void
    {
        $config = $this->transportConfig();
        $recipient = $this->normalizePhoneNumber((string) $conversation->wa_number, $config['default_country_code']);
        if ($recipient === '') {
            throw new RuntimeException('Número de WhatsApp inválido para automatización.');
        }

        $type = (string) ($message['type'] ?? 'text');
        $body = $this->renderPlaceholders((string) ($message['body'] ?? ''), $context);
        $transportResult = match ($type) {
            'buttons' => $this->transport->sendInteractiveButtons(
                $config['phone_number_id'],
                $config['access_token'],
                $config['api_version'],
                $recipient,
                $body,
                is_array($message['buttons'] ?? null) ? $message['buttons'] : [],
                isset($message['header']) ? $this->renderPlaceholders((string) $message['header'], $context) : null,
                isset($message['footer']) ? $this->renderPlaceholders((string) $message['footer'], $context) : null,
            ),
            'list' => $this->transport->sendInteractiveList(
                $config['phone_number_id'],
                $config['access_token'],
                $config['api_version'],
                $recipient,
                $body,
                is_array($message['sections'] ?? null) ? $message['sections'] : [],
                (string) ($message['button_text'] ?? $message['button'] ?? 'Seleccionar'),
                isset($message['footer']) ? $this->renderPlaceholders((string) $message['footer'], $context) : null,
            ),
            default => $this->transport->sendText(
                $config['phone_number_id'],
                $config['access_token'],
                $config['api_version'],
                $recipient,
                $body,
                (bool) ($message['preview_url'] ?? false),
            ),
        };

        $this->persistOutbound($conversation, $type === 'buttons' || $type === 'list' ? 'interactive' : $type, $body, $transportResult);
    }

    /**
     * @param array<string, mixed> $template
     */
    private function sendTemplate(WhatsappConversation $conversation, array $template): void
    {
        $config = $this->transportConfig();
        $recipient = $this->normalizePhoneNumber((string) $conversation->wa_number, $config['default_country_code']);
        $name = trim((string) ($template['name'] ?? $template['code'] ?? ''));
        $language = trim((string) ($template['language'] ?? 'es'));
        if ($recipient === '' || $name === '') {
            throw new RuntimeException('Plantilla de Flowmaker incompleta.');
        }

        $transportResult = $this->transport->sendTemplate(
            $config['phone_number_id'],
            $config['access_token'],
            $config['api_version'],
            $recipient,
            $name,
            $language,
            is_array($template['components'] ?? null) ? $template['components'] : [],
        );

        $this->persistOutbound($conversation, 'template', $name, $transportResult);
    }

    /**
     * @param array{wa_message_id:string,status:string,raw:array<string,mixed>} $transportResult
     */
    private function persistOutbound(WhatsappConversation $conversation, string $type, string $body, array $transportResult): void
    {
        $sentAt = now();
        DB::transaction(function () use ($conversation, $type, $body, $transportResult, $sentAt): void {
            WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $transportResult['wa_message_id'],
                'direction' => 'outbound',
                'message_type' => $type,
                'body' => $body !== '' ? $body : null,
                'raw_payload' => $transportResult['raw'],
                'status' => $transportResult['status'],
                'message_timestamp' => $sentAt,
                'sent_at' => $sentAt,
            ]);

            $conversation->fill([
                'last_message_at' => $sentAt,
                'last_message_direction' => 'outbound',
                'last_message_type' => $type,
                'last_message_preview' => mb_substr($body !== '' ? $body : '[' . $type . ']', 0, 512),
            ]);
            $conversation->save();
        });
    }

    /**
     * @return array{enabled:bool,phone_number_id:string,access_token:string,api_version:string,default_country_code:string}
     */
    private function transportConfig(): array
    {
        $config = $this->configService->get();
        $dryRun = (bool) config('whatsapp.migration.automation.dry_run', true);
        if ($dryRun) {
            config()->set('whatsapp.transport.dry_run', true);
            $config['enabled'] = true;
            $config['phone_number_id'] = $config['phone_number_id'] !== '' ? $config['phone_number_id'] : 'dry-run-phone';
            $config['access_token'] = $config['access_token'] !== '' ? $config['access_token'] : 'dry-run-token';
        }

        if (!$config['enabled'] || $config['phone_number_id'] === '' || $config['access_token'] === '') {
            throw new RuntimeException('La automatización de WhatsApp V2 no tiene Cloud API configurado.');
        }

        return [
            'enabled' => (bool) $config['enabled'],
            'phone_number_id' => (string) $config['phone_number_id'],
            'access_token' => (string) $config['access_token'],
            'api_version' => (string) $config['api_version'],
            'default_country_code' => (string) $config['default_country_code'],
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $context
     */
    private function markConversationForHandoff(WhatsappConversation $conversation, array $scenario, array $context): void
    {
        $note = 'Escalado desde Flowmaker';
        if (!empty($scenario['name'])) {
            $note .= ': ' . (string) $scenario['name'];
        }
        if (!empty($context['handoff_note']) && is_string($context['handoff_note'])) {
            $note .= ' · ' . trim($context['handoff_note']);
        }

        $conversation->fill([
            'needs_human' => true,
            'handoff_notes' => $note,
            'handoff_role_id' => isset($context['handoff_role_id']) && is_numeric($context['handoff_role_id']) ? (int) $context['handoff_role_id'] : null,
            'handoff_requested_at' => now(),
        ]);
        $conversation->save();
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
        $type = (string) ($condition['type'] ?? 'always');

        return match ($type) {
            'always' => true,
            'is_first_time' => (bool) ($condition['value'] ?? false) === (bool) ($facts['is_first_time'] ?? false),
            'has_consent' => (bool) ($condition['value'] ?? false) === (bool) ($facts['has_consent'] ?? false),
            'state_is' => ($facts['state'] ?? null) === ($condition['value'] ?? null),
            'awaiting_is' => ($facts['awaiting_field'] ?? null) === ($condition['value'] ?? null),
            'message_in' => $this->messageIn($condition, $facts),
            'message_contains' => $this->messageContains($condition, $facts),
            'message_matches' => $this->messageMatches($condition, $facts),
            'last_interaction_gt' => (int) ($facts['minutes_since_last'] ?? 0) >= max(0, (int) ($condition['minutes'] ?? 0)),
            'patient_found' => (bool) ($facts['patient_found'] ?? false),
            'context_flag' => $this->contextFlagMatches($condition, $facts),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageIn(array $condition, array $facts): bool
    {
        $needle = (string) ($facts['message'] ?? '');
        foreach ($this->conditionTextList($condition, 'values') as $value) {
            if ($needle === $this->normalizeText($value)) {
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
        $needle = (string) ($facts['message'] ?? '');
        foreach ($this->conditionTextList($condition, 'keywords') as $keyword) {
            $keyword = $this->normalizeText($keyword);
            if ($keyword !== '' && str_contains($needle, $keyword)) {
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
        $pattern = trim((string) ($condition['pattern'] ?? $condition['value'] ?? ''));
        if ($pattern === '') {
            return false;
        }

        $regex = '~' . str_replace('~', '\\~', $pattern) . '~u';
        foreach ([(string) ($facts['raw_message'] ?? ''), (string) ($facts['message'] ?? ''), (string) ($facts['digits'] ?? '')] as $candidate) {
            if ($candidate !== '' && @preg_match($regex, $candidate) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function contextFlagMatches(array $condition, array $facts): bool
    {
        $key = (string) ($condition['key'] ?? '');
        if ($key === '') {
            return false;
        }

        $value = $facts[$key] ?? null;
        if (!array_key_exists('value', $condition)) {
            return (bool) $value;
        }

        return $condition['value'] == $value;
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
                static fn ($value): string => trim((string) $value),
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
     * @param array<string, mixed> $context
     * @param array<string, mixed> $messagePayload
     * @return array<string, mixed>
     */
    private function buildFacts(
        WhatsappConversation $conversation,
        WhatsappMessage $inboundMessage,
        ?WhatsappAutoresponderSession $session,
        array $context,
        string $text,
        array $messagePayload,
    ): array {
        $normalized = $this->normalizeText($text);
        $facts = [
            'is_first_time' => $session === null && empty($context['consent']),
            'state' => $context['state'] ?? 'inicio',
            'awaiting_field' => $context['awaiting_field'] ?? null,
            'has_consent' => !empty($context['consent']),
            'message' => $normalized,
            'raw_message' => $text,
            'patient_found' => isset($context['patient']) || trim((string) ($conversation->patient_hc_number ?? '')) !== '',
            'consent_identifier' => $context['identifier'] ?? null,
            'current_identifier' => $context['cedula'] ?? ($context['identifier'] ?? $conversation->patient_hc_number),
            'wa_number' => $conversation->wa_number,
            'message_id' => $messagePayload['id'] ?? $inboundMessage->wa_message_id,
        ];

        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if ($digits !== '') {
            $facts['digits'] = $digits;
        }

        if ($session?->last_interaction_at instanceof Carbon) {
            $facts['minutes_since_last'] = $session->last_interaction_at->diffInMinutes(now());
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function scenarioIsPublished(array $scenario): bool
    {
        return (string) ($scenario['status'] ?? 'published') === 'published';
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
     * @param array<string, mixed> $context
     */
    private function renderPlaceholders(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*}}/', function (array $matches) use ($context): string {
            $key = (string) $matches[1];
            $value = data_get($context, $key);
            if ($value === null && str_starts_with($key, 'context.')) {
                $value = data_get($context, substr($key, 8));
            }
            if (is_scalar($value)) {
                return (string) $value;
            }

            return '';
        }, $text) ?? $text;
    }

    private function normalizePhoneNumber(string $phoneNumber, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?: '';
        if ($digits === '') {
            return '';
        }

        if ($defaultCountryCode !== '' && !str_starts_with($digits, $defaultCountryCode)) {
            return $defaultCountryCode . ltrim($digits, '0');
        }

        return $digits;
    }

    /**
     * @return array{executed:bool,matched:bool,scenario_id:?string,messages_sent:int,handoff_requested:bool,reason:?string}
     */
    private function result(bool $executed, bool $matched, ?string $scenarioId, int $messagesSent, bool $handoffRequested, ?string $reason): array
    {
        return [
            'executed' => $executed,
            'matched' => $matched,
            'scenario_id' => $scenarioId,
            'messages_sent' => $messagesSent,
            'handoff_requested' => $handoffRequested,
            'reason' => $reason,
        ];
    }
}
