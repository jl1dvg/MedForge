<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\AutoresponderFlowRepository;
use Modules\WhatsApp\Repositories\ContactConsentRepository;
use Modules\WhatsApp\Services\Messenger;
use Modules\WhatsApp\Services\ConversationService;
use Modules\WhatsApp\Services\ScenarioEngine;
use Modules\WhatsApp\Services\PatientLookupService;
use Modules\WhatsApp\Support\AutoresponderFlow;
use Modules\WhatsApp\Support\DataProtectionFlow;
use Modules\WhatsApp\Repositories\AutoresponderSessionRepository;
use PDO;
use function file_get_contents;
use function hash_equals;
use function is_array;
use function json_decode;
use function ltrim;
use function mb_strtolower;
use function preg_replace;
use function strlen;
use function str_contains;
use function strtr;
use function trim;

class WebhookController extends BaseController
{
    private Messenger $messenger;
    private string $verifyToken;
    /**
     * @var array<string, mixed>
     */
    private array $flow;
    private DataProtectionFlow $dataProtection;
    private ConversationService $conversations;
    private ?ScenarioEngine $scenarioEngine = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->messenger = new Messenger($pdo);
        $this->conversations = new ConversationService($pdo);
        $repository = new AutoresponderFlowRepository($pdo);
        $settings = new WhatsAppSettings($pdo);
        $config = $settings->get();
        $brand = trim((string) ($config['brand'] ?? ''));
        if ($brand === '') {
            $brand = $this->messenger->getBrandName();
        }

        $this->flow = AutoresponderFlow::resolve($brand, $repository->load());
        if (!isset($this->flow['meta']) || !is_array($this->flow['meta'])) {
            $this->flow['meta'] = [];
        }
        $this->flow['meta']['brand'] = $brand;
        $this->verifyToken = $this->resolveVerifyToken($config);
        $patientLookup = new PatientLookupService($pdo);
        $consentRepository = new ContactConsentRepository($pdo);
        $sessionRepository = new AutoresponderSessionRepository($pdo);
        $this->scenarioEngine = new ScenarioEngine(
            $this->messenger,
            $this->conversations,
            $sessionRepository,
            $patientLookup,
            $consentRepository,
            $this->flow
        );
        $this->dataProtection = new DataProtectionFlow($this->messenger, $consentRepository, $patientLookup, $settings);
    }

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (strtoupper($method) === 'GET') {
            $this->handleVerification();

            return;
        }

        $this->handleIncoming();
    }

    private function handleVerification(): void
    {
        $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
        $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
        $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

        if ($mode === 'subscribe' && $token !== null && hash_equals($this->verifyToken, (string) $token)) {
            if (!headers_sent()) {
                http_response_code(200);
                header('Content-Type: text/plain; charset=UTF-8');
            }

            echo (string) $challenge;

            return;
        }

        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo 'Verification token mismatch';
    }

    private function handleIncoming(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '[]', true);

        if (!is_array($payload)) {
            $this->json(['ok' => false, 'error' => 'Invalid payload'], 400);

            return;
        }

        foreach ($this->extractMessages($payload) as $message) {
            $this->respondToMessage($message);
        }

        $this->json(['ok' => true]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractMessages(array $payload): array
    {
        $messages = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                if (!isset($change['value']) || !is_array($change['value'])) {
                    continue;
                }

                $value = $change['value'];
                $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];

                foreach (($value['messages'] ?? []) as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $message['metadata'] = $metadata;
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function respondToMessage(array $message): void
    {
        $sender = isset($message['from']) ? ('+' . ltrim((string) $message['from'], '+')) : null;
        if ($sender === null || $sender === '+') {
            return;
        }

        try {
            $recorded = $this->conversations->recordIncoming($message);
            if ($recorded === false) {
                return;
            }
        } catch (\Throwable $exception) {
            error_log('No se pudo registrar el mensaje entrante de WhatsApp: ' . $exception->getMessage());
        }

        $text = $this->extractText($message);
        if ($text === null) {
            return;
        }

        $keyword = $this->normalize($text);

        if ($keyword === '') {
            return;
        }

        if ($this->scenarioEngine instanceof ScenarioEngine && !empty($this->flow['scenarios'])) {
            if ($this->scenarioEngine->handleIncoming($sender, $text, $message)) {
                return;
            }
        }

        if ($this->dataProtection->handle($sender, $keyword, $message, $text)) {
            return;
        }

        $entry = $this->flow['entry'] ?? [];

        if ($this->matchesKeyword($keyword, $entry['keywords'] ?? [], true)) {
            $this->dispatchMessages($sender, $entry['messages'] ?? []);

            return;
        }

        foreach ($this->flow['options'] ?? [] as $option) {
            $keywords = $option['keywords'] ?? [];
            if (!$this->matchesKeyword($keyword, $keywords, true)) {
                continue;
            }

            $this->dispatchMessages($sender, $option['messages'] ?? []);

            return;
        }

        $fallback = $this->flow['fallback'] ?? [];
        $this->dispatchMessages($sender, $fallback['messages'] ?? []);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveVerifyToken(array $config): string
    {
        $token = trim((string) ($config['webhook_verify_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        return (string) (
            $_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN']
            ?? $_ENV['WHATSAPP_VERIFY_TOKEN']
            ?? getenv('WHATSAPP_WEBHOOK_VERIFY_TOKEN')
            ?? getenv('WHATSAPP_VERIFY_TOKEN')
            ?? 'medforge-whatsapp'
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractText(array $message): ?string
    {
        $type = $message['type'] ?? '';

        if ($type === 'text' && isset($message['text']['body'])) {
            return (string) $message['text']['body'];
        }

        if ($type === 'interactive' && isset($message['interactive']) && is_array($message['interactive'])) {
            $interactive = $message['interactive'];
            $interactiveType = $interactive['type'] ?? '';

            if ($interactiveType === 'button_reply') {
                return (string) ($interactive['button_reply']['id'] ?? $interactive['button_reply']['title'] ?? '');
            }

            if ($interactiveType === 'list_reply') {
                return (string) ($interactive['list_reply']['id'] ?? $interactive['list_reply']['title'] ?? '');
            }
        }

        if ($type === 'button' && isset($message['button']['payload'])) {
            return (string) $message['button']['payload'];
        }

        return null;
    }

    /**
     * @param array<int, string> $keywords
     */
    private function matchesKeyword(string $text, array $keywords, bool $allowPartial = false): bool
    {
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }

            $normalizedKeyword = $this->normalize($keyword);
            if ($normalizedKeyword === '') {
                continue;
            }

            if ($text === $normalizedKeyword) {
                return true;
            }

            if ($allowPartial && strlen($normalizedKeyword) > 1 && str_contains($text, $normalizedKeyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $messages
     */
    private function dispatchMessages(string $recipient, array $messages): void
    {
        foreach ($messages as $message) {
            if (is_string($message)) {
                $this->messenger->sendTextMessage($recipient, $message);

                continue;
            }

            if (!is_array($message)) {
                continue;
            }

            $type = isset($message['type']) ? (string) $message['type'] : 'text';
            $body = isset($message['body']) ? (string) $message['body'] : '';
            if ($type === 'buttons') {
                $buttons = [];
                foreach ($message['buttons'] ?? [] as $button) {
                    if (!is_array($button)) {
                        continue;
                    }

                    $id = isset($button['id']) ? (string) $button['id'] : '';
                    $title = isset($button['title']) ? (string) $button['title'] : '';
                    if ($id === '' || $title === '') {
                        continue;
                    }

                    $buttons[] = ['id' => $id, 'title' => $title];
                }

                if (empty($buttons)) {
                    continue;
                }

                $options = [];
                if (!empty($message['header']) && is_string($message['header'])) {
                    $options['header'] = $message['header'];
                }
                if (!empty($message['footer']) && is_string($message['footer'])) {
                    $options['footer'] = $message['footer'];
                }

                $this->messenger->sendInteractiveButtons($recipient, $body, $buttons, $options);

                continue;
            }

            if ($type === 'list') {
                $sections = $message['sections'] ?? [];
                if (!is_array($sections) || empty($sections)) {
                    continue;
                }

                $options = [];
                if (!empty($message['button']) && is_string($message['button'])) {
                    $options['button'] = $message['button'];
                }
                if (!empty($message['header']) && is_string($message['header'])) {
                    $options['header'] = $message['header'];
                }
                if (!empty($message['footer']) && is_string($message['footer'])) {
                    $options['footer'] = $message['footer'];
                }

                if ($body === '') {
                    $body = 'Selecciona una opción para continuar';
                }

                $this->messenger->sendInteractiveList($recipient, $body, $sections, $options);

                continue;
            }

            if ($type === 'template') {
                $template = $message['template'] ?? null;
                if (!is_array($template)) {
                    continue;
                }

                $this->messenger->sendTemplateMessage($recipient, $template);

                continue;
            }

            if ($body === '') {
                continue;
            }

            $this->messenger->sendTextMessage($recipient, $body);
        }
    }

    private function normalize(string $text): string
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
}
