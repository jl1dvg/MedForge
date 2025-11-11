<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Services\ConversationService;
use Modules\WhatsApp\Services\Messenger;
use PDO;
use Throwable;

class ChatController extends BaseController
{
    private ConversationService $conversations;
    private Messenger $messenger;
    private WhatsAppSettings $settings;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->conversations = new ConversationService($pdo);
        $this->messenger = new Messenger($pdo);
        $this->settings = new WhatsAppSettings($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $config = $this->settings->get();
        $isEnabled = (bool) ($config['enabled'] ?? false);

        $this->render(BASE_PATH . '/modules/WhatsApp/views/chat.php', [
            'pageTitle' => 'Chat de WhatsApp',
            'config' => $config,
            'isIntegrationEnabled' => $isEnabled,
            'scripts' => ['js/pages/whatsapp-chat.js'],
        ]);
    }

    public function listConversations(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $search = $this->getQuery('search');
        $limit = $this->getQueryInt('limit');
        if ($limit === null || $limit <= 0 || $limit > 100) {
            $limit = 25;
        }

        $data = $this->conversations->listConversations($search ?? '', $limit);
        $this->json(['ok' => true, 'data' => $data]);
    }

    public function showConversation(int $conversationId): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $conversation = $this->conversations->getConversationWithMessages($conversationId, 150);
        if ($conversation === null) {
            $this->json(['ok' => false, 'error' => 'Conversación no encontrada'], 404);

            return;
        }

        $this->json(['ok' => true, 'data' => $conversation]);
    }

    public function sendMessage(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.send', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $payload = $this->getBody();
        $message = isset($payload['message']) ? trim((string) $payload['message']) : '';
        if ($message === '') {
            $this->json(['ok' => false, 'error' => 'El mensaje no puede estar vacío'], 422);

            return;
        }

        $conversationId = null;
        $waNumber = null;
        $displayName = null;

        if (!empty($payload['conversation_id'])) {
            $conversationId = (int) $payload['conversation_id'];
            $summary = $this->conversations->getConversationSummary($conversationId);
            if ($summary === null) {
                $this->json(['ok' => false, 'error' => 'La conversación indicada no existe'], 404);

                return;
            }

            $waNumber = $summary['wa_number'];
            $displayName = $summary['display_name'];
        } elseif (!empty($payload['wa_number'])) {
            $waNumber = (string) $payload['wa_number'];
            $displayName = isset($payload['display_name']) ? trim((string) $payload['display_name']) : null;
        } else {
            $this->json(['ok' => false, 'error' => 'Debes indicar una conversación o un número de WhatsApp'], 422);

            return;
        }

        try {
            if ($displayName !== null && $displayName !== '') {
                $conversationId = $this->conversations->ensureConversation($waNumber, [
                    'display_name' => $displayName,
                ]);
            } elseif ($conversationId === null) {
                $conversationId = $this->conversations->ensureConversation($waNumber);
            }
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No fue posible preparar la conversación: ' . $exception->getMessage()], 422);

            return;
        }

        $result = $this->messenger->sendTextMessage($waNumber, $message, [
            'preview_url' => (bool) ($payload['preview_url'] ?? false),
        ]);

        if (!$result) {
            $this->json(['ok' => false, 'error' => 'No fue posible enviar el mensaje. Verifica la integración con WhatsApp Cloud API.'], 500);

            return;
        }

        $conversation = $this->conversations->getConversationWithMessages($conversationId, 150);
        if ($conversation === null) {
            $conversation = $this->conversations->getConversationSummary($conversationId);
        }

        $this->json([
            'ok' => true,
            'data' => [
                'conversation' => $conversation,
            ],
        ]);
    }

    private function preventCaching(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function getQuery(string $key): ?string
    {
        if (!isset($_GET[$key])) {
            return null;
        }

        $value = trim((string) $_GET[$key]);

        return $value === '' ? null : $value;
    }

    private function getQueryInt(string $key): ?int
    {
        $value = $this->getQuery($key);

        return $value === null ? null : (int) $value;
    }

    private function getBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $data = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $this->bodyCache = is_array($decoded) ? $decoded : [];

            return $this->bodyCache;
        }

        if (!empty($data)) {
            $this->bodyCache = $data;

            return $this->bodyCache;
        }

        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
    }
}
