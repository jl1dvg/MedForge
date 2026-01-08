<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\TaskModel;
use PDO;

class CrmTaskService
{
    private TaskModel $tasks;

    public function __construct(PDO $pdo)
    {
        $this->tasks = new TaskModel($pdo);
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

        return $this->tasks->create($payload, $userId);
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
}
