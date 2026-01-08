<?php

namespace Controllers;

use Core\BaseController;
use Modules\CRM\Models\TaskModel;
use Modules\CRM\Services\TaskService;
use Modules\WhatsApp\Services\ConversationService;
use Modules\WhatsApp\WhatsAppModule;
use PDO;
use Throwable;

class TaskController extends BaseController
{
    private TaskModel $tasks;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->tasks = new TaskModel($pdo);
    }

    public function listTasks(): void
    {
        $this->requireAuth();

        try {
            $filters = [];
            $companyId = $this->currentCompanyId();
            $filters['company_id'] = $companyId;
            $filters['viewer_id'] = $this->currentUserId();
            $filters['is_admin'] = $this->isAdminUser();

            if (($assigned = $this->getQueryInt('assigned_to')) !== null) {
                $filters['assigned_to'] = $assigned;
            }
            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($due = $this->getQuery('due')) !== null) {
                $filters['due'] = $due;
            }
            if (($hcNumber = $this->getQuery('hc')) !== null) {
                $filters['hc_number'] = $hcNumber;
            }
            if (($formId = $this->getQueryInt('form_id')) !== null) {
                $filters['form_id'] = $formId;
            }
            if (($projectId = $this->getQueryInt('project_id')) !== null) {
                $filters['project_id'] = $projectId;
            }
            if (($leadId = $this->getQueryInt('lead_id')) !== null) {
                $filters['lead_id'] = $leadId;
            }
            if (($customerId = $this->getQueryInt('customer_id')) !== null) {
                $filters['customer_id'] = $customerId;
            }
            if (($sourceModule = $this->getQuery('source_module')) !== null) {
                $filters['source_module'] = $sourceModule;
            }
            if (($sourceRef = $this->getQuery('source_ref_id')) !== null) {
                $filters['source_ref_id'] = $sourceRef;
            }
            if (($episodeType = $this->getQuery('episode_type')) !== null) {
                $filters['episode_type'] = $episodeType;
            }
            if (($eye = $this->getQuery('eye')) !== null) {
                $filters['eye'] = $eye;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $tasks = $this->tasks->list($filters);
            $this->json(['ok' => true, 'data' => $tasks]);
        } catch (Throwable $exception) {
            error_log('[TaskController] listTasks failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudieron cargar las tareas', 500, 'tasks_list_failed');
        }
    }

    public function showTask(int $taskId): void
    {
        $this->requireAuth();

        $task = $this->getAccessibleTask($taskId);
        if (!$task) {
            return;
        }

        $this->json(['ok' => true, 'data' => $task]);
    }

    public function createTask(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            $this->respondError('El título es requerido', 422, 'title_required');
            return;
        }

        try {
            $companyId = $this->currentCompanyId();
            $generated = [];
            $task = $this->tasks->create(
                [
                    'company_id' => $companyId,
                    'project_id' => $payload['project_id'] ?? null,
                    'lead_id' => $payload['lead_id'] ?? null,
                    'customer_id' => $payload['customer_id'] ?? null,
                    'hc_number' => $payload['hc_number'] ?? ($payload['hc'] ?? null),
                    'form_id' => $payload['form_id'] ?? null,
                    'source_module' => $payload['source_module'] ?? null,
                    'source_ref_id' => $payload['source_ref_id'] ?? null,
                    'episode_type' => $payload['episode_type'] ?? null,
                    'eye' => $payload['eye'] ?? null,
                    'title' => $title,
                    'description' => $payload['description'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'priority' => $payload['priority'] ?? null,
                    'category' => $payload['category'] ?? null,
                    'tags' => $payload['tags'] ?? null,
                    'metadata' => $payload['metadata'] ?? null,
                    'assigned_to' => $payload['assigned_to'] ?? null,
                    'due_at' => $payload['due_at'] ?? null,
                    'due_date' => $payload['due_date'] ?? null,
                    'remind_at' => $payload['remind_at'] ?? null,
                    'remind_channel' => $payload['remind_channel'] ?? null,
                ],
                $this->currentUserId()
            );

            if (!empty($payload['auto_templates'])) {
                $service = new TaskService($this->pdo);
                $generated = $service->createEpisodeDefaults($task, $this->currentUserId());
            }

            $response = ['ok' => true, 'data' => $task];
            if (!empty($generated)) {
                $response['generated'] = $generated;
            }

            $this->json($response, 201);
        } catch (Throwable $exception) {
            error_log('[TaskController] createTask failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudo crear la tarea', 500, 'task_create_failed');
        }
    }

    public function updateStatus(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;
        $status = isset($payload['status']) ? (string) $payload['status'] : '';

        if ($taskId <= 0 || $status === '') {
            $this->respondError('task_id y status son requeridos', 422, 'validation_error');
            return;
        }

        try {
            $task = $this->getAccessibleTask($taskId);
            if (!$task) {
                return;
            }

            $task = $this->tasks->updateStatus($taskId, $this->currentCompanyId(), $status, $this->currentUserId());
            if (!$task) {
                $this->respondError('Tarea no encontrada', 404, 'task_not_found');
                return;
            }

            $this->json(['ok' => true, 'data' => $task]);
        } catch (Throwable $exception) {
            error_log('[TaskController] updateStatus failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudo actualizar la tarea', 500, 'task_status_failed');
        }
    }

    public function reschedule(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;

        if ($taskId <= 0) {
            $this->respondError('task_id es requerido', 422, 'validation_error');
            return;
        }

        try {
            $task = $this->getAccessibleTask($taskId);
            if (!$task) {
                return;
            }

            $task = $this->tasks->reschedule($taskId, $this->currentCompanyId(), $payload);
            if (!$task) {
                $this->respondError('Tarea no encontrada', 404, 'task_not_found');
                return;
            }

            $this->json(['ok' => true, 'data' => $task]);
        } catch (Throwable $exception) {
            error_log('[TaskController] reschedule failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudo reprogramar la tarea', 500, 'task_reschedule_failed');
        }
    }

    public function updateTask(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;

        if ($taskId <= 0) {
            $this->respondError('task_id es requerido', 422, 'validation_error');
            return;
        }

        try {
            $task = $this->getAccessibleTask($taskId);
            if (!$task) {
                return;
            }

            $task = $this->tasks->updateDetails($taskId, $this->currentCompanyId(), $payload);
            if (!$task) {
                $this->respondError('Tarea no encontrada', 404, 'task_not_found');
                return;
            }

            $this->json(['ok' => true, 'data' => $task]);
        } catch (Throwable $exception) {
            error_log('[TaskController] updateTask failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudo actualizar la tarea', 500, 'task_update_failed');
        }
    }

    public function summary(): void
    {
        $this->requireAuth();

        try {
            $summary = $this->tasks->summary($this->currentCompanyId(), [
                'viewer_id' => $this->currentUserId(),
                'is_admin' => $this->isAdminUser(),
            ]);

            $this->json(['ok' => true, 'data' => $summary]);
        } catch (Throwable $exception) {
            error_log('[TaskController] summary failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudo cargar el resumen', 500, 'task_summary_failed');
        }
    }

    public function sendWhatsAppTemplate(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;
        $phone = trim((string) ($payload['phone'] ?? ''));
        $template = $payload['template'] ?? null;

        if ($taskId <= 0 || $phone === '' || !is_array($template)) {
            $this->respondError('task_id, phone y template son requeridos', 422, 'validation_error');
            return;
        }

        $task = $this->getAccessibleTask($taskId);
        if (!$task) {
            return;
        }

        $messenger = WhatsAppModule::messenger($this->pdo);
        if (!$messenger->isEnabled()) {
            $this->respondError('WhatsApp no está habilitado', 503, 'whatsapp_disabled');
            return;
        }

        try {
            $sent = $messenger->sendTemplateMessage($phone, $template);
            if (!$sent) {
                $this->respondError('No se pudo enviar la plantilla', 500, 'whatsapp_send_failed');
                return;
            }

            $conversationId = null;
            try {
                $conversationService = new ConversationService($this->pdo);
                $conversationId = $conversationService->ensureConversation($phone);
            } catch (Throwable $exception) {
                $conversationId = null;
            }

            $this->tasks->addEvidence($taskId, $this->currentCompanyId(), 'whatsapp_template', [
                'phone' => $phone,
                'conversation_id' => $conversationId,
                'wamid' => null,
                'template_name' => $template['name'] ?? null,
                'template' => $template,
            ], $this->currentUserId());

            if (!empty($payload['mark_completed'])) {
                $task = $this->tasks->updateStatus($taskId, $this->currentCompanyId(), 'completada', $this->currentUserId()) ?? $task;
            } else {
                $task = $this->tasks->find($taskId, $this->currentCompanyId()) ?? $task;
            }

            $this->json(['ok' => true, 'data' => $task, 'sent' => true]);
        } catch (Throwable $exception) {
            error_log('[TaskController] sendWhatsAppTemplate failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->respondError('No se pudo enviar la plantilla', 500, 'whatsapp_send_failed');
        }
    }

    public function openChat(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;
        $phone = trim((string) ($payload['phone'] ?? ''));

        if ($phone === '') {
            $this->respondError('phone es requerido', 422, 'validation_error');
            return;
        }

        if ($taskId > 0) {
            $task = $this->getAccessibleTask($taskId);
            if (!$task) {
                return;
            }
        }

        $normalized = preg_replace('/\\D+/', '', $phone);
        $waUrl = 'https://wa.me/' . $normalized;
        $appUrl = '/whatsapp/chat?number=' . urlencode($normalized);

        $this->json(['ok' => true, 'data' => ['wa_me' => $waUrl, 'app' => $appUrl]]);
    }

    private function getBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $data = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            $this->bodyCache = is_array($decoded) ? $decoded : [];
            return $this->bodyCache;
        }

        if (!empty($data)) {
            $this->bodyCache = $data;
            return $this->bodyCache;
        }

        $decoded = json_decode(file_get_contents('php://input'), true);
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
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
        if (!isset($_GET[$key])) {
            return null;
        }

        if ($_GET[$key] === '' || $_GET[$key] === null) {
            return null;
        }

        $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);
        return $value === false ? null : (int) $value;
    }

    private function getAccessibleTask(int $taskId): ?array
    {
        $companyId = $this->currentCompanyId();
        $task = $this->tasks->find($taskId, $companyId);

        if (!$task) {
            $this->respondError('Tarea no encontrada', 404, 'task_not_found');
            return null;
        }

        if (!$this->isAdminUser()) {
            $userId = $this->currentUserId();
            $assigned = isset($task['assigned_to']) ? (int) $task['assigned_to'] : 0;
            $created = isset($task['created_by']) ? (int) $task['created_by'] : 0;
            if ($assigned !== $userId && $created !== $userId) {
                $this->respondError('Tarea no encontrada', 404, 'task_not_found');
                return null;
            }
        }

        return $task;
    }

    private function respondError(string $message, int $status, string $code): void
    {
        $this->json(['ok' => false, 'error' => $message, 'code' => $code], $status);
    }
}
