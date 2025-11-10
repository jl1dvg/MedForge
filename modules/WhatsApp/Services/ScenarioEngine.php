<?php

namespace Modules\WhatsApp\Services;

use DateTimeImmutable;
use Modules\WhatsApp\Repositories\AutoresponderSessionRepository;
use Modules\WhatsApp\Repositories\ContactConsentRepository;

use function array_map;
use function is_array;
use function is_scalar;
use function is_string;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function str_contains;
use function trim;

class ScenarioEngine
{
    private Messenger $messenger;
    private ConversationService $conversations;
    private AutoresponderSessionRepository $sessions;
    private PatientLookupService $patientLookup;
    private ContactConsentRepository $consentRepository;
    /**
     * @var array<string, mixed>
     */
    private array $flow;

    public function __construct(
        Messenger $messenger,
        ConversationService $conversations,
        AutoresponderSessionRepository $sessions,
        PatientLookupService $patientLookup,
        ContactConsentRepository $consentRepository,
        array $flow
    ) {
        $this->messenger = $messenger;
        $this->conversations = $conversations;
        $this->sessions = $sessions;
        $this->patientLookup = $patientLookup;
        $this->consentRepository = $consentRepository;
        $this->flow = $flow;
    }

    /**
     * @param array<string, mixed> $message
     */
    public function handleIncoming(string $sender, string $text, array $message): bool
    {
        $conversationId = $this->conversations->findConversationIdByNumber($sender);
        if ($conversationId === null) {
            $conversationId = $this->conversations->ensureConversation($sender);
        }

        $session = $this->sessions->findByConversationId($conversationId);
        $context = is_array($session['context'] ?? null) ? $session['context'] : [];
        if (!isset($context['state'])) {
            $context['state'] = 'inicio';
        }

        $facts = $this->buildFacts($sender, $text, $session, $context, $message);
        $handled = false;

        foreach ($this->flow['scenarios'] ?? [] as $scenario) {
            if (!$this->scenarioMatches($scenario, $facts)) {
                continue;
            }

            $result = $this->executeActions($scenario['actions'] ?? [], [
                'conversationId' => $conversationId,
                'sender' => $sender,
                'message' => $message,
                'text' => $text,
                'context' => $context,
                'facts' => $facts,
            ]);

            $context = $result['context'];
            $facts = $this->buildFacts($sender, $text, $session, $context, $message);

            $awaiting = isset($context['awaiting_field']) ? 'input' : null;
            $this->sessions->upsert($conversationId, $sender, [
                'scenario_id' => (string) ($scenario['id'] ?? 'scenario'),
                'node_id' => null,
                'awaiting' => $awaiting,
                'context' => $context,
                'last_payload' => $message,
            ]);

            $handled = true;
            break;
        }

        if ($handled) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $session
     * @param array<string, mixed> $context
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function buildFacts(string $sender, string $text, ?array $session, array $context, array $message): array
    {
        $now = new DateTimeImmutable();
        $normalized = $this->normalizeText($text);

        $lastInteraction = null;
        if (isset($session['last_interaction_at']) && is_string($session['last_interaction_at'])) {
            $lastInteraction = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $session['last_interaction_at']);
        }

        $hasConsent = false;
        $consentRecord = $this->consentRepository->findByNumber($sender);
        if ($consentRecord !== null) {
            $hasConsent = ($consentRecord['consent_status'] ?? '') === 'accepted';
        }

        $facts = [
            'is_first_time' => $session === null,
            'state' => $context['state'] ?? 'inicio',
            'awaiting_field' => $context['awaiting_field'] ?? null,
            'has_consent' => $hasConsent || !empty($context['consent']),
            'message' => $normalized,
            'raw_message' => $text,
            'patient_found' => isset($context['patient']),
        ];

        if ($lastInteraction instanceof DateTimeImmutable) {
            $diff = $lastInteraction->diff($now);
            $facts['minutes_since_last'] = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        }

        $facts['consent_identifier'] = $consentRecord['identifier'] ?? null;
        $facts['current_identifier'] = $context['cedula'] ?? ($context['identifier'] ?? null);

        return $facts;
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $facts
     */
    private function scenarioMatches(array $scenario, array $facts): bool
    {
        foreach ($scenario['conditions'] ?? [] as $condition) {
            if (!$this->evaluateCondition($condition, $facts)) {
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

        switch ($type) {
            case 'always':
                return true;
            case 'is_first_time':
                return (bool) ($condition['value'] ?? false) === (bool) ($facts['is_first_time'] ?? false);
            case 'has_consent':
                return (bool) ($condition['value'] ?? false) === (bool) ($facts['has_consent'] ?? false);
            case 'state_is':
                return ($facts['state'] ?? null) === ($condition['value'] ?? null);
            case 'awaiting_is':
                return ($facts['awaiting_field'] ?? null) === ($condition['value'] ?? null);
            case 'message_in':
                $values = $condition['values'] ?? [];
                if (!is_array($values)) {
                    return false;
                }
                $needle = $facts['message'] ?? '';
                foreach ($values as $value) {
                    if ($needle === $value) {
                        return true;
                    }
                }

                return false;
            case 'message_contains':
                $keywords = $condition['keywords'] ?? [];
                if (!is_array($keywords)) {
                    return false;
                }
                $needle = $facts['message'] ?? '';
                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && str_contains($needle, $keyword)) {
                        return true;
                    }
                }

                return false;
            case 'message_matches':
                $pattern = $condition['pattern'] ?? '';
                if (!is_string($pattern) || $pattern === '') {
                    return false;
                }

                $regex = $this->compileRegex($pattern);
                if ($regex === null) {
                    return false;
                }

                return preg_match($regex, (string) ($facts['raw_message'] ?? '')) === 1;
            case 'last_interaction_gt':
                $minutes = (int) ($condition['minutes'] ?? 0);
                if ($minutes <= 0) {
                    return true;
                }
                return (int) ($facts['minutes_since_last'] ?? 0) >= $minutes;
            case 'patient_found':
                return (bool) ($facts['patient_found'] ?? false);
            case 'context_flag':
                $key = $condition['key'] ?? '';
                if (!is_string($key) || $key === '') {
                    return false;
                }
                $value = $facts[$key] ?? null;
                if (!array_key_exists('value', $condition)) {
                    return (bool) $value;
                }

                return $condition['value'] == $value;
            default:
                return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @param array<string, mixed> $env
     * @return array{context: array<string, mixed>}
     */
    private function executeActions(array $actions, array $env): array
    {
        $context = $env['context'] ?? [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            if ($type === '') {
                continue;
            }

            if (in_array($type, ['send_message', 'send_buttons', 'send_list'], true)) {
                $message = $action['message'] ?? null;
                if (is_array($message)) {
                    $this->dispatchMessage($env['sender'], $message, $context);
                }
                continue;
            }

            if ($type === 'send_template') {
                if (isset($action['template']) && is_array($action['template'])) {
                    $this->messenger->sendTemplateMessage($env['sender'], $action['template']);
                }
                continue;
            }

            if ($type === 'set_state') {
                $context['state'] = $action['state'] ?? 'inicio';
                continue;
            }

            if ($type === 'set_context') {
                foreach (($action['values'] ?? []) as $key => $value) {
                    if (!is_scalar($value)) {
                        continue;
                    }
                    $context[$key] = (string) $value;
                }
                continue;
            }

            if ($type === 'store_consent') {
                $context['consent'] = (bool) ($action['value'] ?? true);
                $this->persistConsent($env['sender'], $context);
                continue;
            }

            if ($type === 'lookup_patient') {
                $field = $action['field'] ?? 'cedula';
                $source = $action['source'] ?? 'message';
                $identifier = '';
                if ($source === 'context') {
                    $identifier = (string) ($context[$field] ?? '');
                } else {
                    $identifier = $this->normalizeIdentifier($env['text'] ?? '');
                    if ($identifier !== '') {
                        $context[$field] = $identifier;
                    }
                }

                if ($identifier !== '') {
                    $patient = $this->patientLookup->findLocalByHistoryNumber($identifier);
                    if ($patient !== null) {
                        $context['patient'] = $patient;
                        $this->conversations->ensureConversation($env['sender'], [
                            'patient_hc_number' => $patient['hc_number'] ?? $identifier,
                            'patient_full_name' => $patient['full_name'] ?? null,
                        ]);
                    }
                }
                continue;
            }

            if ($type === 'conditional') {
                $condition = $action['condition'] ?? [];
                $facts = $this->buildFacts($env['sender'], $env['text'], null, $context, $env['message']);
                if ($this->evaluateCondition($condition, $facts)) {
                    $result = $this->executeActions($action['then'] ?? [], $env + ['context' => $context]);
                    $context = $result['context'];
                } else {
                    $result = $this->executeActions($action['else'] ?? [], $env + ['context' => $context]);
                    $context = $result['context'];
                }
                continue;
            }

            if ($type === 'goto_menu') {
                $this->sendMenu($env['sender'], $context);
                continue;
            }

            if ($type === 'upsert_patient_from_context') {
                $identifier = $context['cedula'] ?? ($context['identifier'] ?? null);
                if (is_string($identifier) && $identifier !== '') {
                    $this->conversations->ensureConversation($env['sender'], [
                        'patient_hc_number' => $identifier,
                        'patient_full_name' => $context['patient']['full_name'] ?? ($context['nombre'] ?? null),
                    ]);
                }
                continue;
            }

            if ($type === 'handoff_agent') {
                $context['handoff_requested'] = true;
                continue;
            }
        }

        return ['context' => $context];
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $context
     */
    private function dispatchMessage(string $recipient, array $message, array $context): void
    {
        $type = $message['type'] ?? 'text';
        $body = isset($message['body']) ? $this->renderPlaceholders((string) $message['body'], $context) : '';

        if ($type === 'buttons') {
            $buttons = [];
            foreach ($message['buttons'] ?? [] as $button) {
                if (!is_array($button)) {
                    continue;
                }
                $id = trim((string) ($button['id'] ?? ''));
                $title = trim((string) ($button['title'] ?? ''));
                if ($id === '' || $title === '') {
                    continue;
                }
                $buttons[] = ['id' => $id, 'title' => $title];
            }

            if (empty($buttons)) {
                $this->messenger->sendTextMessage($recipient, $body);

                return;
            }

            $this->messenger->sendInteractiveButtons($recipient, $body, $buttons, [
                'header' => isset($message['header']) ? $this->renderPlaceholders((string) $message['header'], $context) : null,
                'footer' => isset($message['footer']) ? $this->renderPlaceholders((string) $message['footer'], $context) : null,
            ]);

            return;
        }

        if ($type === 'list') {
            $sections = $message['sections'] ?? [];
            $this->messenger->sendInteractiveList($recipient, $body, $sections, [
                'button' => $message['button'] ?? 'Seleccionar',
                'footer' => isset($message['footer']) ? $this->renderPlaceholders((string) $message['footer'], $context) : null,
            ]);

            return;
        }

        $this->messenger->sendTextMessage($recipient, $body);
    }

    private function sendMenu(string $recipient, array $context): void
    {
        $menu = $this->flow['menu'] ?? [];
        if (empty($menu)) {
            return;
        }

        $message = $menu['message'] ?? null;
        if (is_array($message)) {
            $this->dispatchMessage($recipient, $message, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function persistConsent(string $sender, array $context): void
    {
        $identifier = $context['cedula'] ?? ($context['identifier'] ?? null);
        if (!is_string($identifier) || $identifier === '') {
            return;
        }

        $payload = [
            'wa_number' => $sender,
            'identifier' => $identifier,
            'cedula' => $identifier,
            'consent_status' => ($context['consent'] ?? false) ? 'accepted' : 'declined',
            'consent_source' => 'scenario',
        ];

        $this->consentRepository->startOrUpdate($payload);
        $this->consentRepository->markConsent($sender, $identifier, (bool) ($context['consent'] ?? false));
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return preg_replace('/\s+/', ' ', $text) ?? '';
    }

    private function normalizeIdentifier(string $text): string
    {
        $digits = preg_replace('/\D+/', '', $text);

        return $digits ?? '';
    }

    private function compileRegex(string $pattern): ?string
    {
        $delimiter = '~';
        if (str_contains($pattern, $delimiter)) {
            $delimiter = '/';
        }

        $regex = $delimiter . $pattern . $delimiter . 'u';
        set_error_handler(static function () {
        });
        $valid = @preg_match($regex, '') !== false;
        restore_error_handler();

        return $valid ? $regex : null;
    }

    private function renderPlaceholders(string $text, array $context): string
    {
        if ($text === '') {
            return '';
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{context.' . $key . '}}'] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (!is_scalar($subValue)) {
                        continue;
                    }
                    $replacements['{{context.' . $key . '.' . $subKey . '}}'] = (string) $subValue;
                }
            }
        }

        $replacements['{{brand}}'] = $this->flow['meta']['brand'] ?? 'MedForge';

        return strtr($text, $replacements);
    }
}
