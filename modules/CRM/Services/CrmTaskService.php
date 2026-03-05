<?php

namespace Modules\CRM\Services;

use DateTimeImmutable;
use Modules\CRM\Models\TaskModel;
use Modules\Notifications\Services\PusherConfigService;
use Modules\Notifications\Services\ReminderConfigService;
use PDO;
use Throwable;

class CrmTaskService
{
    private TaskModel $tasks;
    private PusherConfigService $pusherConfig;
    private ReminderConfigService $reminderConfig;

    public function __construct(PDO $pdo)
    {
        $this->tasks = new TaskModel($pdo);
        $this->pusherConfig = new PusherConfigService($pdo);
        $this->reminderConfig = new ReminderConfigService($pdo);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters, int $companyId, int $viewerId, bool $isAdmin): array
    {
        $filters['company_id'] = $companyId;
        $filters['viewer_id'] = $viewerId;
        $filters['is_admin'] = $isAdmin;

        return $this->tasks->list($filters);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, int $companyId, int $userId): array
    {
        $payload['company_id'] = $companyId;
        $created = $this->tasks->create($payload, $userId);
        $this->notifyTaskCreated($created, $userId);

        return $created;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function update(int $taskId, int $companyId, array $payload, ?int $userId = null): ?array
    {
        $task = $this->tasks->find($taskId, $companyId);
        if (!$task) {
            return null;
        }

        if (isset($payload['status']) && $payload['status'] !== '') {
            $this->tasks->updateStatus($taskId, $companyId, (string) $payload['status'], $userId);
        }

        $detailPayload = array_intersect_key($payload, array_flip([
            'title',
            'description',
            'priority',
            'assigned_to',
            'category',
            'tags',
            'metadata',
        ]));
        if ($detailPayload) {
            $this->tasks->updateDetails($taskId, $companyId, $detailPayload);
        }

        $schedulePayload = array_intersect_key($payload, array_flip([
            'due_at',
            'due_date',
            'remind_at',
            'remind_channel',
            'assigned_to',
            'priority',
        ]));
        if ($schedulePayload) {
            $this->tasks->reschedule($taskId, $companyId, $schedulePayload);
        }

        return $this->tasks->find($taskId, $companyId);
    }

    /**
     * @param array<string, mixed> $task
     */
    private function notifyTaskCreated(array $task, int $actorUserId): void
    {
        if (!$this->reminderConfig->isCrmTaskCreationNotificationEnabled()) {
            return;
        }

        if (!$this->reminderConfig->isReminderEventEnabled('crm_task')) {
            return;
        }

        $taskId = (int) ($task['id'] ?? 0);
        if ($taskId <= 0) {
            return;
        }

        $status = strtolower(trim((string) ($task['status'] ?? 'pendiente')));
        if (in_array($status, ['completada', 'cancelada'], true)) {
            return;
        }

        $assignedTo = isset($task['assigned_to']) ? (int) $task['assigned_to'] : 0;
        if ($assignedTo <= 0) {
            return;
        }
        $audience = [$assignedTo];

        $channels = $this->pusherConfig->getNotificationChannels();
        $dueAtIso = $this->formatReminderDate(
            $this->resolveDueAt($task),
            DateTimeImmutable::ATOM
        );
        $remindAtIso = $this->formatReminderDate($task['remind_at'] ?? null, DateTimeImmutable::ATOM);
        $nowIso = (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM);

        $payload = [
            'id' => $this->resolveSourceReference($task, $taskId),
            'full_name' => trim((string) ($task['assigned_name'] ?? '')) ?: 'Tarea CRM',
            'task_id' => $taskId,
            'reminder_id' => null,
            'title' => trim((string) ($task['title'] ?? 'Tarea CRM')),
            'description' => trim((string) ($task['description'] ?? '')),
            'estado' => $status,
            'assigned_to' => $assignedTo,
            'assigned_name' => trim((string) ($task['assigned_name'] ?? '')),
            'source_module' => trim((string) ($task['source_module'] ?? '')),
            'source_ref_id' => trim((string) ($task['source_ref_id'] ?? '')),
            'hc_number' => trim((string) ($task['hc_number'] ?? '')),
            'form_id' => trim((string) ($task['form_id'] ?? '')),
            'entity_type' => trim((string) ($task['entity_type'] ?? '')),
            'entity_id' => trim((string) ($task['entity_id'] ?? '')),
            'due_at' => $dueAtIso,
            'remind_at' => $remindAtIso,
            'created_at' => $nowIso,
            'reminder_type' => 'crm_task_created',
            'reminder_label' => 'Nueva tarea CRM asignada',
            'reminder_context' => 'Se creó una tarea y requiere seguimiento.',
            'task_url' => $this->buildCrmTaskUrl($task),
            'audience_user_ids' => $audience,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'channels' => $channels,
        ];

        try {
            $this->pusherConfig->trigger($payload, null, PusherConfigService::EVENT_CRM_TASK_REMINDER);
        } catch (Throwable) {
            // No bloquea la creación de tareas si falla la publicación en tiempo real.
        }
    }

    /**
     * @param array<string, mixed> $task
     */
    private function resolveDueAt(array $task): ?string
    {
        $dueAt = trim((string) ($task['due_at'] ?? ''));
        if ($dueAt !== '') {
            return $dueAt;
        }

        $dueDate = trim((string) ($task['due_date'] ?? ''));
        if ($dueDate !== '') {
            return $dueDate . ' 23:59:59';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $task
     */
    private function resolveSourceReference(array $task, int $taskId): string
    {
        $sourceRefId = trim((string) ($task['source_ref_id'] ?? ''));
        return $sourceRefId !== '' ? $sourceRefId : (string) $taskId;
    }

    /**
     * @param array<string, mixed> $task
     */
    private function buildCrmTaskUrl(array $task): string
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        $sourceModule = strtolower(trim((string) ($task['source_module'] ?? '')));
        $sourceRefId = trim((string) ($task['source_ref_id'] ?? ''));

        $path = '/crm';
        if ($sourceModule === 'solicitudes' && $sourceRefId !== '') {
            $path = '/solicitudes/' . rawurlencode($sourceRefId) . '/crm';
        } elseif ($sourceModule === 'examenes' && $sourceRefId !== '') {
            $path = '/examenes/' . rawurlencode($sourceRefId) . '/crm';
        }

        return $base !== '' ? ($base . $path) : $path;
    }

    /**
     * @param mixed $raw
     */
    private function formatReminderDate($raw, string $format): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format($format);
        } catch (Throwable) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return null;
            }
            return date($format, $timestamp);
        }
    }
}
