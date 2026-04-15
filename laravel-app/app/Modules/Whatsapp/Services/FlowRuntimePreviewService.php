<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAutoresponderSession;
use Carbon\Carbon;

class FlowRuntimePreviewService
{
    public function __construct(
        private readonly FlowmakerService $flowmakerService = new FlowmakerService(),
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

        $message = [
            'id' => 'preview-' . substr(md5($waNumber . '|' . $text), 0, 12),
            'type' => 'text',
            'text' => ['body' => $text],
        ];
        $facts = $this->buildFacts($waNumber, $text, $session, $context, $message);

        foreach (($flow['scenarios'] ?? []) as $scenario) {
            if (!is_array($scenario)) {
                continue;
            }

            if (!$this->scenarioMatches($scenario, $facts)) {
                continue;
            }

            $result = $this->simulateActions($scenario['actions'] ?? [], $context);

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
     * @param array<int, mixed> $actions
     * @param array<string, mixed> $context
     * @return array{actions: array<int, array<string, mixed>>, context: array<string, mixed>}
     */
    private function simulateActions(array $actions, array $context): array
    {
        $emitted = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $type = (string) ($action['type'] ?? '');
            if ($type === '') {
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

            $emitted[] = ['type' => $type];
        }

        return ['actions' => $emitted, 'context' => $context];
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
        $values = $condition['values'] ?? [];
        if (!is_array($values)) {
            return false;
        }
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
        $keywords = $condition['keywords'] ?? [];
        if (!is_array($keywords)) {
            return false;
        }
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
        $pattern = $condition['pattern'] ?? '';
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
}
