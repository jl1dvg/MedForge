<?php

namespace Modules\WhatsApp\Controllers;

use Core\Auth;
use Core\BaseController;
use Core\Permissions;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Shared\Services\SchemaInspector;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Services\ConversationService;
use Modules\WhatsApp\Services\Messenger;
use Modules\WhatsApp\Services\TemplateManager;
use PDO;
use Throwable;

class ChatController extends BaseController
{
    private ConversationService $conversations;
    private Messenger $messenger;
    private WhatsAppSettings $settings;
    private SchemaInspector $schemaInspector;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->conversations = new ConversationService($pdo);
        $this->messenger = new Messenger($pdo);
        $this->settings = new WhatsAppSettings($pdo);
        $this->schemaInspector = new SchemaInspector($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.view', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $config = $this->settings->get();
        $isEnabled = (bool) ($config['enabled'] ?? false);
        $authUser = Auth::user();
        $permissions = $authUser['permisos'] ?? [];
        $canAssign = Permissions::containsAny($permissions, ['whatsapp.chat.assign', 'whatsapp.manage', 'administrativo']);
        $pusher = new PusherConfigService($this->pdo);

        $this->render(BASE_PATH . '/modules/WhatsApp/views/chat.php', [
            'pageTitle' => 'Chat de WhatsApp',
            'config' => $config,
            'isIntegrationEnabled' => $isEnabled,
            'currentUser' => $authUser,
            'canAssign' => $canAssign,
            'realtime' => $pusher->getPublicConfig(),
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

    public function listAgents(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.assign', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $roleId = $this->getQueryInt('role_id');
        $agents = $this->conversations->listAgents($roleId);

        $this->json(['ok' => true, 'data' => $agents]);
    }

    public function assignConversation(int $conversationId): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.assign', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $authUser = Auth::user();
        $currentUserId = isset($authUser['id']) ? (int) $authUser['id'] : 0;
        if ($currentUserId <= 0) {
            $this->json(['ok' => false, 'error' => 'Usuario no válido'], 401);

            return;
        }

        $payload = $this->getBody();
        $targetUserId = isset($payload['user_id']) ? (int) $payload['user_id'] : $currentUserId;

        if ($targetUserId !== $currentUserId && !Permissions::containsAny($authUser['permisos'] ?? [], ['whatsapp.manage'])) {
            $this->json(['ok' => false, 'error' => 'No tienes permisos para asignar a otro agente'], 403);

            return;
        }

        $summary = $this->conversations->getConversationSummary($conversationId);
        if ($summary === null) {
            $this->json(['ok' => false, 'error' => 'Conversación no encontrada'], 404);

            return;
        }

        if (!empty($summary['assigned_user_id']) && (int) $summary['assigned_user_id'] !== $targetUserId) {
            $this->json(['ok' => false, 'error' => 'La conversación ya está asignada a otro agente.'], 409);

            return;
        }

        if (!empty($summary['assigned_user_id']) && (int) $summary['assigned_user_id'] === $targetUserId) {
            $conversation = $this->conversations->getConversationWithMessages($conversationId, 150);
            $this->json(['ok' => true, 'data' => $conversation]);

            return;
        }

        $roleId = isset($summary['handoff_role_id']) ? (int) $summary['handoff_role_id'] : null;
        if ($roleId !== null && $roleId > 0) {
            $agent = $this->resolveAgent($targetUserId);
            if ($agent === null) {
                $this->json(['ok' => false, 'error' => 'El agente seleccionado no es válido.'], 422);

                return;
            }
            if (!empty($agent['role_id']) && (int) $agent['role_id'] !== $roleId && !Permissions::containsAny($authUser['permisos'] ?? [], ['whatsapp.manage'])) {
                $this->json(['ok' => false, 'error' => 'El agente no pertenece al equipo requerido.'], 403);

                return;
            }
        }

        $assigned = $this->conversations->assignConversation($conversationId, $targetUserId);
        if (!$assigned) {
            $this->json(['ok' => false, 'error' => 'No fue posible asignar la conversación.'], 409);

            return;
        }

        $conversation = $this->conversations->getConversationWithMessages($conversationId, 150);
        $this->json(['ok' => true, 'data' => $conversation]);
    }

    public function transferConversation(int $conversationId): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.assign', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $authUser = Auth::user();
        $currentUserId = isset($authUser['id']) ? (int) $authUser['id'] : 0;
        if ($currentUserId <= 0) {
            $this->json(['ok' => false, 'error' => 'Usuario no válido'], 401);

            return;
        }

        $payload = $this->getBody();
        $targetUserId = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;
        $note = isset($payload['note']) ? trim((string) $payload['note']) : null;
        if ($targetUserId <= 0) {
            $this->json(['ok' => false, 'error' => 'Debes indicar un agente para transferir.'], 422);

            return;
        }

        $summary = $this->conversations->getConversationSummary($conversationId);
        if ($summary === null) {
            $this->json(['ok' => false, 'error' => 'Conversación no encontrada'], 404);

            return;
        }

        if (!empty($summary['assigned_user_id']) && (int) $summary['assigned_user_id'] !== $currentUserId
            && !Permissions::containsAny($authUser['permisos'] ?? [], ['whatsapp.manage'])) {
            $this->json(['ok' => false, 'error' => 'Solo el agente asignado puede transferir esta conversación.'], 403);

            return;
        }

        $agent = $this->resolveAgent($targetUserId);
        if ($agent === null) {
            $this->json(['ok' => false, 'error' => 'El agente seleccionado no es válido.'], 422);

            return;
        }

        $roleId = isset($summary['handoff_role_id']) ? (int) $summary['handoff_role_id'] : null;
        if ($roleId !== null && $roleId > 0 && !empty($agent['role_id']) && (int) $agent['role_id'] !== $roleId
            && !Permissions::containsAny($authUser['permisos'] ?? [], ['whatsapp.manage'])) {
            $this->json(['ok' => false, 'error' => 'El agente no pertenece al equipo requerido.'], 403);

            return;
        }

        if (!$this->conversations->transferConversation($conversationId, $targetUserId, $note)) {
            $this->json(['ok' => false, 'error' => 'No fue posible transferir la conversación.'], 500);

            return;
        }

        $conversation = $this->conversations->getConversationWithMessages($conversationId, 150);
        $this->json(['ok' => true, 'data' => $conversation]);
    }

    private function resolveAgent(int $userId): ?array
    {
        $agents = $this->conversations->listAgents();
        foreach ($agents as $agent) {
            if ((int) ($agent['id'] ?? 0) === $userId) {
                return $agent;
            }
        }

        return null;
    }

    public function sendMessage(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.send', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $payload = $this->getBody();
        $message = isset($payload['message']) ? trim((string) $payload['message']) : '';
        $template = $this->normalizeTemplatePayload($payload['template'] ?? null);
        $attachment = $this->handleAttachmentUpload();

        if ($message === '' && $template === null && $attachment === null) {
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

        $previewUrl = (bool) ($payload['preview_url'] ?? false);

        if ($template !== null) {
            $templateSent = $this->messenger->sendTemplateMessage($waNumber, $template);
            if (!$templateSent) {
                $this->json(['ok' => false, 'error' => 'No fue posible enviar la plantilla de WhatsApp.'], 500);

                return;
            }
        }

        if ($attachment !== null) {
            $sent = false;
            if ($attachment['type'] === 'image') {
                $sent = $this->messenger->sendImageMessage($waNumber, $attachment['url'], [
                    'caption' => $message !== '' ? $message : null,
                ]);
            } elseif ($attachment['type'] === 'audio') {
                $sent = $this->messenger->sendAudioMessage($waNumber, $attachment['url']);
            } else {
                $sent = $this->messenger->sendDocumentMessage($waNumber, $attachment['url'], [
                    'caption' => $message !== '' ? $message : null,
                    'filename' => $attachment['filename'] ?? null,
                ]);
            }

            if (!$sent) {
                $this->json(['ok' => false, 'error' => 'No fue posible enviar el adjunto.'], 500);

                return;
            }

            if ($message !== '' && $attachment['type'] === 'audio') {
                $textSent = $this->messenger->sendTextMessage($waNumber, $message, [
                    'preview_url' => $previewUrl,
                ]);

                if (!$textSent) {
                    $this->json(['ok' => false, 'error' => 'No fue posible enviar el mensaje adicional.'], 500);

                    return;
                }
            }
        } elseif ($message !== '') {
            $result = $this->messenger->sendTextMessage($waNumber, $message, [
                'preview_url' => $previewUrl,
            ]);

            if (!$result) {
                $this->json(['ok' => false, 'error' => 'No fue posible enviar el mensaje. Verifica la integración con WhatsApp Cloud API.'], 500);

                return;
            }
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

    public function searchPatients(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.send', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        $search = $this->getQuery('search') ?? '';
        $search = trim($search);
        if ($search === '') {
            $this->json(['ok' => true, 'data' => []]);

            return;
        }

        $phoneColumns = $this->resolvePhoneColumns();
        $nameExpression = $this->resolveNameExpression();
        $phoneExpression = $this->resolvePhoneExpression($phoneColumns);

        if ($nameExpression === null) {
            $nameExpression = 'p.hc_number';
        }

        $selectPhone = $phoneExpression !== null ? $phoneExpression . ' AS phone' : 'NULL AS phone';
        $sql = "SELECT p.hc_number, {$nameExpression} AS full_name, {$selectPhone}
                FROM patient_data p";

        $conditions = ['p.hc_number LIKE :search'];
        $params = [':search' => '%' . $search . '%'];

        $nameSearchColumns = $this->resolveNameSearchColumns();
        foreach ($nameSearchColumns as $index => $column) {
            $key = ':name' . $index;
            $conditions[] = $column . ' LIKE ' . $key;
            $params[$key] = '%' . $search . '%';
        }

        if (!empty($phoneColumns)) {
            foreach ($phoneColumns as $index => $column) {
                $key = ':phone' . $index;
                $conditions[] = $column . ' LIKE ' . $key;
                $params[$key] = '%' . $search . '%';
            }
        }

        $sql .= ' WHERE (' . implode(' OR ', $conditions) . ')';
        $sql .= ' ORDER BY full_name ASC LIMIT 15';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];

        foreach ($rows as $row) {
            $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
            $result[] = [
                'hc_number' => $row['hc_number'] ?? null,
                'full_name' => $row['full_name'] ?? null,
                'phone' => $phone !== '' ? $phone : null,
            ];
        }

        $this->json(['ok' => true, 'data' => $result]);
    }

    public function listAvailableTemplates(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.chat.send', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->preventCaching();

        try {
            $templates = new TemplateManager($this->pdo);
            $result = $templates->listTemplates(['limit' => 250]);
            $data = $result['data'] ?? [];

            $this->json(['ok' => true, 'data' => $data]);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
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

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeTemplatePayload(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($raw)) {
            return null;
        }

        $name = trim((string) ($raw['name'] ?? ''));
        $language = trim((string) ($raw['language'] ?? ''));

        if ($name === '' || $language === '') {
            return null;
        }

        $payload = [
            'name' => $name,
            'language' => $language,
        ];

        if (!empty($raw['category'])) {
            $payload['category'] = (string) $raw['category'];
        }

        if (!empty($raw['components']) && is_array($raw['components'])) {
            $payload['components'] = $raw['components'];
        }

        return $payload;
    }

    /**
     * @return array{url: string, type: string, filename?: string}|null
     */
    private function handleAttachmentUpload(): ?array
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            return null;
        }

        $file = $_FILES['attachment'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return null;
        }

        $tmpName = (string) $file['tmp_name'];
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $originalName = trim((string) ($file['name'] ?? 'adjunto'));
        $safeName = preg_replace('/[^A-Za-z0-9_\.-]+/', '_', $originalName) ?? 'adjunto';
        $safeName = trim($safeName, '_');
        if ($safeName === '') {
            $safeName = 'adjunto';
        }

        $subdir = date('Ymd');
        $baseDir = rtrim(PUBLIC_PATH . '/uploads/whatsapp/' . $subdir, '/');
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            return null;
        }

        $destName = uniqid('wa_', true) . '_' . $safeName;
        $destPath = $baseDir . '/' . $destName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            return null;
        }

        $relativePath = 'uploads/whatsapp/' . $subdir . '/' . $destName;
        $baseUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
        $publicUrl = $baseUrl !== '' ? $baseUrl . '/' . $relativePath : '/' . $relativePath;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $destPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        $type = $this->resolveAttachmentType((string) $mimeType, $safeName);

        return [
            'url' => $publicUrl,
            'type' => $type,
            'filename' => $safeName,
        ];
    }

    private function resolveAttachmentType(string $mimeType, string $filename = ''): string
    {
        $mimeType = strtolower($mimeType);
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }
        if (in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true)) {
            return 'audio';
        }

        return 'document';
    }

    /**
     * @return array<int, string>
     */
    private function resolvePhoneColumns(): array
    {
        $candidates = [
            'phone',
            'phone_number',
            'telefono',
            'tel',
            'celular',
            'mobile',
            'movil',
            'whatsapp',
            'wa_number',
            'contact_phone',
            'contact_phone_number',
        ];

        $columns = [];
        foreach ($candidates as $column) {
            if ($this->schemaInspector->tableHasColumn('patient_data', $column)) {
                $columns[] = 'p.`' . $column . '`';
            }
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    private function resolveNameSearchColumns(): array
    {
        $candidates = [
            'full_name',
            'fullname',
            'nombre_completo',
            'nombreCompleto',
            'name',
            'nombre',
            'fname',
            'mname',
            'lname',
            'lname2',
            'first_name',
            'middle_name',
            'last_name',
            'second_last_name',
        ];

        $columns = [];
        foreach ($candidates as $column) {
            if ($this->schemaInspector->tableHasColumn('patient_data', $column)) {
                $columns[] = 'p.`' . $column . '`';
            }
        }

        return $columns;
    }

    private function resolveNameExpression(): ?string
    {
        $directNames = [
            'full_name',
            'fullname',
            'nombre_completo',
            'nombreCompleto',
            'name',
            'nombre',
        ];

        foreach ($directNames as $column) {
            if ($this->schemaInspector->tableHasColumn('patient_data', $column)) {
                return 'p.`' . $column . '`';
            }
        }

        $parts = [];
        foreach (['fname', 'mname', 'lname', 'lname2', 'first_name', 'middle_name', 'last_name', 'second_last_name'] as $column) {
            if ($this->schemaInspector->tableHasColumn('patient_data', $column)) {
                $parts[] = 'p.`' . $column . '`';
            }
        }

        if (empty($parts)) {
            return null;
        }

        return 'TRIM(CONCAT_WS(" ", ' . implode(', ', $parts) . '))';
    }

    private function resolvePhoneExpression(array $columns): ?string
    {
        if (empty($columns)) {
            return null;
        }

        return 'COALESCE(' . implode(', ', $columns) . ')';
    }
}
