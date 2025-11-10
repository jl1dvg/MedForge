<?php

namespace Modules\WhatsApp\Services;

use Modules\WhatsApp\Repositories\AutoresponderSessionRepository;
use function in_array;
use function is_array;
use function is_scalar;
use function preg_match;
use function preg_replace;
use function strtolower;
use function str_replace;
use function trim;

class AutoresponderEngine
{
    private Messenger $messenger;
    private ConversationService $conversations;
    private AutoresponderSessionRepository $sessions;
    private PatientLookupService $patientLookup;
    /**
     * @var array<string, mixed>
     */
    private array $flow;

    public function __construct(
        Messenger $messenger,
        ConversationService $conversations,
        AutoresponderSessionRepository $sessions,
        PatientLookupService $patientLookup,
        array $flow
    ) {
        $this->messenger = $messenger;
        $this->conversations = $conversations;
        $this->sessions = $sessions;
        $this->patientLookup = $patientLookup;
        $this->flow = $flow;
    }

    /**
     * @param array<string, mixed> $message
     */
    public function handleIncoming(string $sender, string $text, array $message): void
    {
        $conversationId = $this->conversations->findConversationIdByNumber($sender);
        if ($conversationId === null) {
            $conversationId = $this->conversations->ensureConversation($sender);
        }

        $session = $this->sessions->findByConversationId($conversationId) ?? [
            'scenario_id' => 'default',
            'node_id' => null,
            'awaiting' => null,
            'context' => [],
        ];

        $keyword = $this->normalizeKeyword($text);

        if ($session['node_id'] !== null && $session['awaiting'] === 'input') {
            $result = $this->processInput($conversationId, $sender, $session, $text, $message);
            if ($result) {
                return;
            }
        }

        if ($session['node_id'] !== null && $session['awaiting'] === 'response') {
            $result = $this->processResponse($conversationId, $sender, $session, $keyword, $text, $message);
            if ($result) {
                return;
            }
        }

        if ($this->triggerShortcut($conversationId, $sender, $keyword, $message)) {
            return;
        }

        $entryKeywords = $this->flow['entry_keywords'] ?? [];
        if (in_array($keyword, $entryKeywords, true)) {
            $this->enterNode($conversationId, $sender, 'menu', ['reset' => true]);

            return;
        }

        $fallback = $this->flow['fallback']['messages'] ?? [];
        $this->dispatchMessages($sender, $fallback, $session['context'] ?? []);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function processInput(int $conversationId, string $sender, array $session, string $text, array $message): bool
    {
        $node = $this->findNode($session['node_id']);
        if ($node === null || $node['type'] !== 'input') {
            return false;
        }

        $input = $node['input'] ?? [];
        $value = $this->normalizeInputValue($text, $input['normalize'] ?? 'trim');
        $pattern = $input['pattern'] ?? '';

        $regex = $this->compilePattern($pattern);

        if ($regex !== null && @preg_match($regex, $value) !== 1) {
            $messages = $input['error_messages'] ?? [];
            if (!empty($messages)) {
                $this->dispatchMessages($sender, $messages, $session['context'] ?? [], $message);
            }
            $this->sessions->upsert($conversationId, $sender, [
                'scenario_id' => $session['scenario_id'],
                'node_id' => $node['id'],
                'awaiting' => 'input',
                'context' => $session['context'] ?? [],
                'last_payload' => $message,
            ]);

            return true;
        }

        $context = $session['context'] ?? [];
        $context[$input['field'] ?? 'value'] = $value;

        $this->sessions->upsert($conversationId, $sender, [
            'scenario_id' => $session['scenario_id'],
            'node_id' => $node['id'],
            'awaiting' => null,
            'context' => $context,
            'last_payload' => $message,
        ]);

        $next = $node['next'] ?? null;
        if ($next !== null) {
            $this->enterNode($conversationId, $sender, $next, ['context' => $context]);
        }

        return true;
    }

    private function compilePattern(string $pattern): ?string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }

        $escaped = str_replace('~', '\\~', $pattern);
        $regex = '~' . $escaped . '~u';

        if (@preg_match($regex, '') === false) {
            return null;
        }

        return $regex;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function processResponse(int $conversationId, string $sender, array $session, string $keyword, string $text, array $message): bool
    {
        $node = $this->findNode($session['node_id']);
        if ($node === null || $node['type'] !== 'message') {
            return false;
        }

        foreach ($node['responses'] ?? [] as $response) {
            if (!$this->matchesKeyword($keyword, $response['keywords'] ?? [])) {
                continue;
            }

            $context = $session['context'] ?? [];
            if (isset($response['clear_context'])) {
                foreach ($response['clear_context'] as $key) {
                    unset($context[$key]);
                }
            }

            if (isset($response['set_context']) && is_array($response['set_context'])) {
                foreach ($response['set_context'] as $key => $value) {
                    $context[$key] = $value;
                }
            }

            if (!empty($response['messages'])) {
                $this->dispatchMessages($sender, $response['messages'], $context, $message);
            }

            $this->sessions->upsert($conversationId, $sender, [
                'scenario_id' => $session['scenario_id'],
                'node_id' => $node['id'],
                'awaiting' => null,
                'context' => $context,
                'last_payload' => $message,
            ]);

            $this->enterNode($conversationId, $sender, $response['target'], ['context' => $context]);

            return true;
        }

        if (!empty($node['next'])) {
            $this->enterNode($conversationId, $sender, $node['next'], ['context' => $session['context'] ?? []]);

            return true;
        }

        return false;
    }

    private function triggerShortcut(int $conversationId, string $sender, string $keyword, array $message): bool
    {
        foreach ($this->flow['shortcuts'] ?? [] as $shortcut) {
            if (!is_array($shortcut)) {
                continue;
            }

            if (!$this->matchesKeyword($keyword, $shortcut['keywords'] ?? [])) {
                continue;
            }

            $context = [];

            if (isset($shortcut['clear_context'])) {
                $context = $this->removeContextKeys([], $shortcut['clear_context']);
            }

            $this->sessions->upsert($conversationId, $sender, [
                'scenario_id' => $shortcut['id'] ?? 'shortcut',
                'node_id' => null,
                'awaiting' => null,
                'context' => $context,
                'last_payload' => $message,
            ]);

            $this->enterNode($conversationId, $sender, $shortcut['target'], ['context' => $context, 'reset' => true]);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function enterNode(int $conversationId, string $sender, string $nodeId, array $options = []): void
    {
        $node = $this->findNode($nodeId);
        if ($node === null) {
            return;
        }

        $context = $options['context'] ?? [];
        if (!empty($options['reset'])) {
            $context = [];
        }

        if (!empty($node['messages'])) {
            $this->dispatchMessages($sender, $node['messages'], $context);
        }

        if ($node['type'] === 'decision') {
            $this->sessions->upsert($conversationId, $sender, [
                'scenario_id' => $options['scenario'] ?? 'default',
                'node_id' => $nodeId,
                'awaiting' => null,
                'context' => $context,
            ]);

            $this->resolveDecision($conversationId, $sender, $node, $context);

            return;
        }

        $awaiting = $node['type'] === 'input' ? 'input' : (!empty($node['responses']) ? 'response' : null);

        $this->sessions->upsert($conversationId, $sender, [
            'scenario_id' => $options['scenario'] ?? 'default',
            'node_id' => $nodeId,
            'awaiting' => $awaiting,
            'context' => $context,
        ]);

        if ($node['type'] === 'message' && empty($node['responses']) && !empty($node['next'])) {
            $this->enterNode($conversationId, $sender, $node['next'], ['context' => $context]);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    private function resolveDecision(int $conversationId, string $sender, array $node, array $context): void
    {
        foreach ($node['branches'] ?? [] as $branch) {
            if (!$this->evaluateCondition($branch['condition'] ?? [], $context, $sender)) {
                continue;
            }

            if (!empty($branch['clear_context'])) {
                foreach ($branch['clear_context'] as $key) {
                    unset($context[$key]);
                }
            }

            if (isset($branch['set_context']) && is_array($branch['set_context'])) {
                foreach ($branch['set_context'] as $key => $value) {
                    $context[$key] = $value;
                }
            }

            if (!empty($branch['messages'])) {
                $this->dispatchMessages($sender, $branch['messages'], $context);
            }

            $this->sessions->upsert($conversationId, $sender, [
                'scenario_id' => 'default',
                'node_id' => $node['id'],
                'awaiting' => null,
                'context' => $context,
            ]);

            $this->enterNode($conversationId, $sender, $branch['next'], ['context' => $context]);

            return;
        }

        $fallback = $this->flow['fallback']['messages'] ?? [];
        $this->dispatchMessages($sender, $fallback, $context);
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function evaluateCondition(array $condition, array &$context, string $sender): bool
    {
        $type = $condition['type'] ?? 'always';
        if ($type === 'always') {
            return true;
        }

        $field = $condition['field'] ?? null;
        $value = $field !== null ? ($context[$field] ?? null) : null;

        if ($type === 'has_value') {
            return $value !== null && $value !== '';
        }

        if ($type === 'equals') {
            return (string) $value === (string) ($condition['value'] ?? '');
        }

        if ($type === 'not_equals') {
            return (string) $value !== (string) ($condition['value'] ?? '');
        }

        if ($type === 'patient_exists') {
            if (!is_string($value) || $value === '') {
                return false;
            }

            $patient = $this->patientLookup->findLocalByHistoryNumber($value);

            if ($patient === null) {
                return false;
            }

            $context['patient'] = $patient;
            $this->conversations->ensureConversation($sender, [
                'patient_hc_number' => $patient['hc_number'] ?? $value,
                'patient_full_name' => $patient['full_name'] ?? null,
            ]);

            return true;
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $context
     */
    private function dispatchMessages(string $recipient, array $messages, array $context = [], array $rawMessage = []): void
    {
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $type = $message['type'] ?? 'text';
            $body = isset($message['body']) ? $this->renderPlaceholders($message['body'], $context) : null;

            if ($type === 'text') {
                $this->messenger->sendTextMessage($recipient, $body ?? '');
                continue;
            }

            if ($type === 'buttons') {
                $buttons = $message['buttons'] ?? [];
                $options = [];
                if (!empty($message['header'])) {
                    $options['header'] = $this->renderPlaceholders((string) $message['header'], $context);
                }
                if (!empty($message['footer'])) {
                    $options['footer'] = $this->renderPlaceholders((string) $message['footer'], $context);
                }

                $this->messenger->sendInteractiveButtons($recipient, $body ?? '', $buttons, $options);

                continue;
            }

            if ($type === 'list') {
                $options = ['button' => $message['button'] ?? 'Seleccionar'];
                if (!empty($message['footer'])) {
                    $options['footer'] = $this->renderPlaceholders((string) $message['footer'], $context);
                }

                $this->messenger->sendInteractiveList($recipient, $body ?? '', $message['sections'] ?? [], $options);

                continue;
            }

            if ($type === 'template' && isset($message['template'])) {
                $this->messenger->sendTemplateMessage($recipient, $message['template']);
            }
        }
    }

    private function matchesKeyword(string $keyword, array $keywords): bool
    {
        foreach ($keywords as $candidate) {
            if ($keyword === $candidate) {
                return true;
            }
        }

        return false;
    }

    private function findNode(string $nodeId): ?array
    {
        foreach ($this->flow['nodes'] ?? [] as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (($node['id'] ?? null) === $nodeId) {
                return $node;
            }
        }

        return null;
    }

    private function normalizeKeyword(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text ?? '');

        return $text;
    }

    private function normalizeInputValue(string $value, string $strategy): string
    {
        $value = trim($value);

        switch ($strategy) {
            case 'digits':
                return preg_replace('/\D+/', '', $value ?? '') ?? '';
            case 'uppercase':
                return mb_strtoupper($value);
            case 'lowercase':
                return mb_strtolower($value);
            default:
                return $value;
        }
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
                    $replacements['{{' . $key . '.' . $subKey . '}}'] = (string) $subValue;
                }
            }
        }

        $brand = $this->flow['meta']['brand'] ?? 'MedForge';
        $replacements['{{brand}}'] = $brand;

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function removeContextKeys(array $context, array $keys): array
    {
        foreach ($keys as $key) {
            unset($context[$key]);
        }

        return $context;
    }
}

