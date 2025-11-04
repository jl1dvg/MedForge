<?php

namespace Modules\CRM\Models;

use PDO;

class TaskModel
{
    private PDO $pdo;

    private const STATUSES = ['pendiente', 'en_progreso', 'bloqueada', 'completada'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    public function list(array $filters = []): array
    {
        $sql = "
            SELECT
                t.id,
                t.project_id,
                t.title,
                t.description,
                t.status,
                t.assigned_to,
                t.created_by,
                t.due_date,
                t.completed_at,
                t.created_at,
                t.updated_at,
                assignee.nombre AS assigned_name,
                creator.nombre AS created_name,
                p.title AS project_title
            FROM crm_tasks t
            LEFT JOIN users assignee ON t.assigned_to = assignee.id
            LEFT JOIN users creator ON t.created_by = creator.id
            LEFT JOIN crm_projects p ON t.project_id = p.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= ' AND t.project_id = :project';
            $params[':project'] = (int) $filters['project_id'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = :assigned';
            $params[':assigned'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND t.status = :status';
            $params[':status'] = $filters['status'];
        }

        $sql .= ' ORDER BY t.due_date IS NULL, t.due_date ASC, t.updated_at DESC';

        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 200;
        $sql .= ' LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$tasks) {
            return [];
        }

        $taskIds = array_column($tasks, 'id');
        $reminders = $this->getReminders($taskIds);

        foreach ($tasks as &$task) {
            $taskId = (int) $task['id'];
            $task['reminders'] = $reminders[$taskId] ?? [];
        }

        return $tasks;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.id,
                t.project_id,
                t.title,
                t.description,
                t.status,
                t.assigned_to,
                t.created_by,
                t.due_date,
                t.completed_at,
                t.created_at,
                t.updated_at,
                assignee.nombre AS assigned_name,
                creator.nombre AS created_name,
                p.title AS project_title
            FROM crm_tasks t
            LEFT JOIN users assignee ON t.assigned_to = assignee.id
            LEFT JOIN users creator ON t.created_by = creator.id
            LEFT JOIN crm_projects p ON t.project_id = p.id
            WHERE t.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return null;
        }

        $task['reminders'] = $this->getReminders([$task['id']])[$task['id']] ?? [];

        return $task;
    }

    public function create(array $data, int $userId): array
    {
        $status = $this->sanitizeStatus($data['status'] ?? null);
        $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $project = !empty($data['project_id']) ? (int) $data['project_id'] : null;

        $title = trim((string) ($data['title'] ?? ''));
        $description = $this->nullableString($data['description'] ?? null);
        $dueDate = $this->nullableString($data['due_date'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_tasks
                (project_id, title, description, status, assigned_to, created_by, due_date)
            VALUES
                (:project_id, :title, :description, :status, :assigned_to, :created_by, :due_date)
        ");

        $stmt->bindValue(':project_id', $project, $project ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':assigned_to', $assignedTo, $assignedTo ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':due_date', $dueDate, $dueDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        $taskId = (int) $this->pdo->lastInsertId();

        if (!empty($data['remind_at'])) {
            $this->scheduleReminder($taskId, $data['remind_at'], $data['remind_channel'] ?? 'in_app');
        }

        return $this->find($taskId);
    }

    public function updateStatus(int $taskId, string $status, ?int $userId = null): ?array
    {
        $status = $this->sanitizeStatus($status);
        $completedAt = $status === 'completada' ? date('Y-m-d H:i:s') : null;

        $stmt = $this->pdo->prepare('UPDATE crm_tasks SET status = :status, completed_at = :completed_at WHERE id = :id');
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':completed_at', $completedAt, $completedAt !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $taskId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->find($taskId);
    }

    public function scheduleReminder(int $taskId, ?string $remindAt, string $channel = 'in_app'): void
    {
        $remindAt = $this->normalizeDateTime($remindAt);
        if (!$remindAt) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_task_reminders (task_id, remind_at, channel)
            VALUES (:task_id, :remind_at, :channel)
        ");

        $stmt->execute([
            ':task_id' => $taskId,
            ':remind_at' => $remindAt,
            ':channel' => in_array($channel, ['email', 'in_app'], true) ? $channel : 'in_app',
        ]);
    }

    private function getReminders(array $taskIds): array
    {
        if (!$taskIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare("SELECT task_id, remind_at, channel FROM crm_task_reminders WHERE task_id IN ($placeholders) ORDER BY remind_at ASC");
        foreach ($taskIds as $index => $taskId) {
            $stmt->bindValue($index + 1, (int) $taskId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $taskId = (int) $row['task_id'];
            $grouped[$taskId][] = [
                'remind_at' => $row['remind_at'],
                'channel' => $row['channel'],
            ];
        }

        return $grouped;
    }

    private function sanitizeStatus(?string $status): string
    {
        if (!$status) {
            return 'pendiente';
        }

        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            return 'pendiente';
        }

        return $status;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
