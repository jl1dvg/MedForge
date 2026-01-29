<?php

namespace Modules\CRM\Models;

use PDO;

class TaskModel
{
    private PDO $pdo;

    private const STATUSES = ['pendiente', 'en_progreso', 'en_proceso', 'bloqueada', 'completada', 'cancelada'];
    private const PRIORITIES = ['baja', 'media', 'alta', 'urgente'];
    private const REMINDER_CHANNELS = ['whatsapp', 'email', 'in_app'];
    private const CATEGORIES = ['preop', 'postop', 'admin', 'billing'];

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
        if (empty($filters['company_id'])) {
            return [];
        }

        $companyId = (int) $filters['company_id'];
        $viewerId = isset($filters['viewer_id']) ? (int) $filters['viewer_id'] : null;
        $isAdmin = (bool) ($filters['is_admin'] ?? false);
        $dueExpression = "COALESCE(t.due_at, CONCAT(t.due_date, ' 23:59:59'))";
        $sql = "
            SELECT
                t.id,
                t.company_id,
                t.project_id,
                t.entity_type,
                t.entity_id,
                t.lead_id,
                t.customer_id,
                t.hc_number,
                t.patient_id,
                t.form_id,
                t.source_module,
                t.source_ref_id,
                t.episode_type,
                t.eye,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.category,
                t.tags,
                t.metadata,
                t.assigned_to,
                t.created_by,
                t.due_date,
                t.due_at,
                t.remind_at,
                t.remind_channel,
                t.completed_at,
                t.created_at,
                t.updated_at,
                TIMESTAMPDIFF(MINUTE, NOW(), $dueExpression) AS minutes_to_due,
                assignee.nombre AS assigned_name,
                creator.nombre AS created_name,
                p.title AS project_title
            FROM crm_tasks t
            LEFT JOIN users assignee ON t.assigned_to = assignee.id
            LEFT JOIN users creator ON t.created_by = creator.id
            LEFT JOIN crm_projects p ON t.project_id = p.id
            WHERE t.company_id = :company_id
        ";

        $params = [
            ':company_id' => $companyId,
        ];

        if (!empty($filters['project_id'])) {
            $sql .= ' AND t.project_id = :project';
            $params[':project'] = (int) $filters['project_id'];
        }

        if (!empty($filters['entity_type'])) {
            $sql .= ' AND t.entity_type = :entity_type';
            $params[':entity_type'] = (string) $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $sql .= ' AND t.entity_id = :entity_id';
            $params[':entity_id'] = (string) $filters['entity_id'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = :assigned';
            $params[':assigned'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['lead_id'])) {
            $sql .= ' AND t.lead_id = :lead_id';
            $params[':lead_id'] = (int) $filters['lead_id'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND t.customer_id = :customer_id';
            $params[':customer_id'] = (int) $filters['customer_id'];
        }

        if (!empty($filters['hc_number'])) {
            $sql .= ' AND t.hc_number = :hc_number';
            $params[':hc_number'] = (string) $filters['hc_number'];
        }

        if (!empty($filters['patient_id'])) {
            $sql .= ' AND t.patient_id = :patient_id';
            $params[':patient_id'] = (string) $filters['patient_id'];
        }

        if (!empty($filters['form_id'])) {
            $sql .= ' AND t.form_id = :form_id';
            $params[':form_id'] = (int) $filters['form_id'];
        }

        if (!empty($filters['source_module'])) {
            $sql .= ' AND t.source_module = :source_module';
            $params[':source_module'] = (string) $filters['source_module'];
        }

        if (!empty($filters['source_ref_id'])) {
            $sql .= ' AND t.source_ref_id = :source_ref_id';
            $params[':source_ref_id'] = (string) $filters['source_ref_id'];
        }

        if (!empty($filters['episode_type'])) {
            $sql .= ' AND t.episode_type = :episode_type';
            $params[':episode_type'] = (string) $filters['episode_type'];
        }

        if (!empty($filters['eye'])) {
            $sql .= ' AND t.eye = :eye';
            $params[':eye'] = (string) $filters['eye'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND t.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!$isAdmin && $viewerId) {
            $sql .= ' AND (t.assigned_to = :viewer_id OR t.created_by = :viewer_id)';
            $params[':viewer_id'] = $viewerId;
        }

        if (!empty($filters['due'])) {
            switch ($filters['due']) {
                case 'today':
                    $sql .= " AND DATE($dueExpression) = CURRENT_DATE()";
                    break;
                case 'overdue':
                    $sql .= " AND $dueExpression IS NOT NULL AND $dueExpression < NOW() AND t.status NOT IN ('completada', 'cancelada')";
                    break;
                case 'week':
                    $sql .= " AND $dueExpression >= CURRENT_DATE() AND $dueExpression < DATE_ADD(CURRENT_DATE(), INTERVAL 8 DAY)";
                    break;
            }
        }

        $sql .= " ORDER BY $dueExpression IS NULL, $dueExpression ASC, t.updated_at DESC";

        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 50;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$tasks) {
            return [];
        }

        $taskIds = array_column($tasks, 'id');
        $reminders = $this->getReminders($taskIds, $companyId);

        foreach ($tasks as &$task) {
            $taskId = (int) $task['id'];
            $task['reminders'] = $reminders[$taskId] ?? [];
            if (empty($task['remind_at']) && !empty($task['reminders'])) {
                $task['remind_at'] = $task['reminders'][0]['remind_at'] ?? null;
                $task['remind_channel'] = $task['reminders'][0]['channel'] ?? null;
            }

            $this->applyComputedFields($task);
        }

        return $tasks;
    }

    public function count(array $filters = []): int
    {
        if (empty($filters['company_id'])) {
            return 0;
        }

        $companyId = (int) $filters['company_id'];
        $viewerId = isset($filters['viewer_id']) ? (int) $filters['viewer_id'] : null;
        $isAdmin = (bool) ($filters['is_admin'] ?? false);
        $dueExpression = "COALESCE(t.due_at, CONCAT(t.due_date, ' 23:59:59'))";
        $sql = "
            SELECT COUNT(*) AS total
            FROM crm_tasks t
            WHERE t.company_id = :company_id
        ";

        $params = [
            ':company_id' => $companyId,
        ];

        if (!empty($filters['project_id'])) {
            $sql .= ' AND t.project_id = :project';
            $params[':project'] = (int) $filters['project_id'];
        }

        if (!empty($filters['entity_type'])) {
            $sql .= ' AND t.entity_type = :entity_type';
            $params[':entity_type'] = (string) $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $sql .= ' AND t.entity_id = :entity_id';
            $params[':entity_id'] = (string) $filters['entity_id'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = :assigned';
            $params[':assigned'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['lead_id'])) {
            $sql .= ' AND t.lead_id = :lead_id';
            $params[':lead_id'] = (int) $filters['lead_id'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND t.customer_id = :customer_id';
            $params[':customer_id'] = (int) $filters['customer_id'];
        }

        if (!empty($filters['hc_number'])) {
            $sql .= ' AND t.hc_number = :hc_number';
            $params[':hc_number'] = (string) $filters['hc_number'];
        }

        if (!empty($filters['patient_id'])) {
            $sql .= ' AND t.patient_id = :patient_id';
            $params[':patient_id'] = (string) $filters['patient_id'];
        }

        if (!empty($filters['form_id'])) {
            $sql .= ' AND t.form_id = :form_id';
            $params[':form_id'] = (int) $filters['form_id'];
        }

        if (!empty($filters['source_module'])) {
            $sql .= ' AND t.source_module = :source_module';
            $params[':source_module'] = (string) $filters['source_module'];
        }

        if (!empty($filters['source_ref_id'])) {
            $sql .= ' AND t.source_ref_id = :source_ref_id';
            $params[':source_ref_id'] = (string) $filters['source_ref_id'];
        }

        if (!empty($filters['episode_type'])) {
            $sql .= ' AND t.episode_type = :episode_type';
            $params[':episode_type'] = (string) $filters['episode_type'];
        }

        if (!empty($filters['eye'])) {
            $sql .= ' AND t.eye = :eye';
            $params[':eye'] = (string) $filters['eye'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND t.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!$isAdmin && $viewerId) {
            $sql .= ' AND (t.assigned_to = :viewer_id OR t.created_by = :viewer_id)';
            $params[':viewer_id'] = $viewerId;
        }

        if (!empty($filters['due'])) {
            switch ($filters['due']) {
                case 'today':
                    $sql .= " AND DATE($dueExpression) = CURRENT_DATE()";
                    break;
                case 'overdue':
                    $sql .= " AND $dueExpression IS NOT NULL AND $dueExpression < NOW() AND t.status NOT IN ('completada', 'cancelada')";
                    break;
                case 'week':
                    $sql .= " AND $dueExpression >= CURRENT_DATE() AND $dueExpression < DATE_ADD(CURRENT_DATE(), INTERVAL 8 DAY)";
                    break;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function find(int $id, int $companyId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.id,
                t.company_id,
                t.project_id,
                t.entity_type,
                t.entity_id,
                t.lead_id,
                t.customer_id,
                t.hc_number,
                t.patient_id,
                t.form_id,
                t.source_module,
                t.source_ref_id,
                t.episode_type,
                t.eye,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.category,
                t.tags,
                t.metadata,
                t.assigned_to,
                t.created_by,
                t.due_date,
                t.due_at,
                t.remind_at,
                t.remind_channel,
                t.completed_at,
                t.created_at,
                t.updated_at,
                TIMESTAMPDIFF(MINUTE, NOW(), COALESCE(t.due_at, CONCAT(t.due_date, ' 23:59:59'))) AS minutes_to_due,
                assignee.nombre AS assigned_name,
                creator.nombre AS created_name,
                p.title AS project_title
            FROM crm_tasks t
            LEFT JOIN users assignee ON t.assigned_to = assignee.id
            LEFT JOIN users creator ON t.created_by = creator.id
            LEFT JOIN crm_projects p ON t.project_id = p.id
            WHERE t.id = :id
              AND t.company_id = :company_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':company_id' => $companyId,
        ]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return null;
        }

        $task['reminders'] = $this->getReminders([$task['id']], $companyId)[$task['id']] ?? [];
        if (empty($task['remind_at']) && !empty($task['reminders'])) {
            $task['remind_at'] = $task['reminders'][0]['remind_at'] ?? null;
            $task['remind_channel'] = $task['reminders'][0]['channel'] ?? null;
        }

        $this->applyComputedFields($task);

        return $task;
    }

    public function create(array $data, int $userId): array
    {
        $companyId = !empty($data['company_id']) ? (int) $data['company_id'] : 0;
        $status = $this->sanitizeStatus($data['status'] ?? null);
        $priority = $this->sanitizePriority($data['priority'] ?? null);
        $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $project = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $entityType = $this->nullableString($data['entity_type'] ?? null);
        $entityId = $this->nullableString($data['entity_id'] ?? null);
        $leadId = !empty($data['lead_id']) ? (int) $data['lead_id'] : null;
        $customerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;

        $title = trim((string) ($data['title'] ?? ''));
        $description = $this->nullableString($data['description'] ?? null);
        $dueDate = $this->normalizeDate($data['due_date'] ?? null);
        $dueAt = $this->normalizeDateTime($data['due_at'] ?? null);
        if ($dueAt && !$dueDate) {
            $dueDate = date('Y-m-d', strtotime($dueAt));
        } elseif (!$dueAt && $dueDate) {
            $dueAt = $dueDate . ' 23:59:59';
        }
        $remindAt = $this->normalizeDateTime($data['remind_at'] ?? null);
        $remindChannel = $this->sanitizeReminderChannel($data['remind_channel'] ?? null);
        $category = $this->sanitizeCategory($data['category'] ?? null);
        $tags = $this->normalizeJson($data['tags'] ?? null);
        $metadata = $this->normalizeJson($data['metadata'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_tasks
                (
                    company_id,
                    project_id,
                    entity_type,
                    entity_id,
                    lead_id,
                    customer_id,
                    hc_number,
                    patient_id,
                    form_id,
                    source_module,
                    source_ref_id,
                    episode_type,
                    eye,
                    title,
                    description,
                    status,
                    priority,
                    category,
                    tags,
                    metadata,
                    assigned_to,
                    created_by,
                    due_date,
                    due_at,
                    remind_at,
                    remind_channel
                )
            VALUES
                (
                    :company_id,
                    :project_id,
                    :entity_type,
                    :entity_id,
                    :lead_id,
                    :customer_id,
                    :hc_number,
                    :patient_id,
                    :form_id,
                    :source_module,
                    :source_ref_id,
                    :episode_type,
                    :eye,
                    :title,
                    :description,
                    :status,
                    :priority,
                    :category,
                    :tags,
                    :metadata,
                    :assigned_to,
                    :created_by,
                    :due_date,
                    :due_at,
                    :remind_at,
                    :remind_channel
                )
        ");

        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $project, $project ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':entity_type', $entityType, $entityType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':entity_id', $entityId, $entityId !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':lead_id', $leadId, $leadId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer_id', $customerId, $customerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $hcNumber = $this->nullableString($data['hc_number'] ?? null);
        $patientId = $this->nullableString($data['patient_id'] ?? null);
        $sourceModule = $this->nullableString($data['source_module'] ?? null);
        $sourceRef = $this->nullableString($data['source_ref_id'] ?? null);
        $episodeType = $this->nullableString($data['episode_type'] ?? null);
        $eye = $this->nullableString($data['eye'] ?? null);
        $formId = !empty($data['form_id']) ? (int) $data['form_id'] : null;

        $stmt->bindValue(':hc_number', $hcNumber, $hcNumber !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':patient_id', $patientId, $patientId !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':form_id', $formId, $formId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':source_module', $sourceModule, $sourceModule !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':source_ref_id', $sourceRef, $sourceRef !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':episode_type', $episodeType, $episodeType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':eye', $eye, $eye !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':priority', $priority);
        $stmt->bindValue(':category', $category, $category !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tags', $tags, $tags !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':metadata', $metadata, $metadata !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':assigned_to', $assignedTo, $assignedTo ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':due_date', $dueDate, $dueDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':due_at', $dueAt, $dueAt !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':remind_at', $remindAt, $remindAt !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':remind_channel', $remindChannel);
        $stmt->execute();

        $taskId = (int) $this->pdo->lastInsertId();

        if ($remindAt) {
            $this->scheduleReminder($taskId, $companyId, $remindAt, $remindChannel);
        }

        return $this->find($taskId, $companyId);
    }

    public function updateStatus(int $taskId, int $companyId, string $status, ?int $userId = null): ?array
    {
        $status = $this->sanitizeStatus($status);
        $completedAt = $status === 'completada' ? date('Y-m-d H:i:s') : null;

        $stmt = $this->pdo->prepare('UPDATE crm_tasks SET status = :status, completed_at = :completed_at, updated_at = NOW() WHERE id = :id AND company_id = :company_id');
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':completed_at', $completedAt, $completedAt !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $taskId, PDO::PARAM_INT);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->find($taskId, $companyId);
    }

    public function reschedule(int $taskId, int $companyId, array $payload): ?array
    {
        $dueAt = $this->normalizeDateTime($payload['due_at'] ?? null);
        $dueDate = $this->normalizeDate($payload['due_date'] ?? null);
        if ($dueAt && !$dueDate) {
            $dueDate = date('Y-m-d', strtotime($dueAt));
        } elseif (!$dueAt && $dueDate) {
            $dueAt = $dueDate . ' 23:59:59';
        }

        $remindAt = $this->normalizeDateTime($payload['remind_at'] ?? null);
        $remindChannel = $this->sanitizeReminderChannel($payload['remind_channel'] ?? null);
        $assignedTo = isset($payload['assigned_to']) && $payload['assigned_to'] !== '' ? (int) $payload['assigned_to'] : null;
        $priority = isset($payload['priority']) ? $this->sanitizePriority($payload['priority']) : null;

        $updates = [];
        $params = [':id' => $taskId];

        if ($dueAt !== null || array_key_exists('due_at', $payload) || array_key_exists('due_date', $payload)) {
            $updates[] = 'due_at = :due_at';
            $updates[] = 'due_date = :due_date';
            $params[':due_at'] = $dueAt;
            $params[':due_date'] = $dueDate;
        }

        if ($remindAt !== null || array_key_exists('remind_at', $payload)) {
            $updates[] = 'remind_at = :remind_at';
            $updates[] = 'remind_channel = :remind_channel';
            $params[':remind_at'] = $remindAt;
            $params[':remind_channel'] = $remindChannel;
        }

        if (array_key_exists('assigned_to', $payload)) {
            $updates[] = 'assigned_to = :assigned_to';
            $params[':assigned_to'] = $assignedTo;
        }

        if ($priority !== null) {
            $updates[] = 'priority = :priority';
            $params[':priority'] = $priority;
        }

        if (!$updates) {
            return $this->find($taskId, $companyId);
        }

        $updates[] = 'updated_at = NOW()';
        $sql = 'UPDATE crm_tasks SET ' . implode(', ', $updates) . ' WHERE id = :id AND company_id = :company_id';
        $stmt = $this->pdo->prepare($sql);
        $params[':company_id'] = $companyId;

        foreach ($params as $key => $value) {
            if (in_array($key, [':due_at', ':due_date', ':remind_at'], true)) {
                $stmt->bindValue($key, $value, $value !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                continue;
            }

            if ($key === ':assigned_to') {
                $stmt->bindValue($key, $value, $value !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                continue;
            }

            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        if ($remindAt) {
            $this->scheduleReminder($taskId, $companyId, $remindAt, $remindChannel);
        }

        return $this->find($taskId, $companyId);
    }

    public function scheduleReminder(int $taskId, int $companyId, ?string $remindAt, string $channel = 'in_app'): void
    {
        $remindAt = $this->normalizeDateTime($remindAt);
        if (!$remindAt) {
            return;
        }

        $channel = $this->sanitizeReminderChannel($channel);

        $this->pdo->prepare('UPDATE crm_tasks SET remind_at = :remind_at, remind_channel = :channel, updated_at = NOW() WHERE id = :id AND company_id = :company_id')
            ->execute([
                ':remind_at' => $remindAt,
                ':channel' => $channel,
                ':id' => $taskId,
                ':company_id' => $companyId,
            ]);

        $this->pdo->prepare('DELETE FROM crm_task_reminders WHERE task_id = :task_id AND company_id = :company_id')
            ->execute([
                ':task_id' => $taskId,
                ':company_id' => $companyId,
            ]);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_task_reminders (task_id, company_id, remind_at, channel)
            VALUES (:task_id, :company_id, :remind_at, :channel)
        ");

        $stmt->execute([
            ':task_id' => $taskId,
            ':company_id' => $companyId,
            ':remind_at' => $remindAt,
            ':channel' => $channel,
        ]);
    }

    public function addEvidence(int $taskId, int $companyId, string $type, array $payload, ?int $userId = null): void
    {
        $type = trim($type);
        if ($type === '') {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO crm_task_evidence (task_id, company_id, evidence_type, payload, created_by)
            VALUES (:task_id, :company_id, :evidence_type, :payload, :created_by)
        ');

        $stmt->execute([
            ':task_id' => $taskId,
            ':company_id' => $companyId,
            ':evidence_type' => $type,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':created_by' => $userId ?: null,
        ]);
    }

    private function getReminders(array $taskIds, int $companyId): array
    {
        if (!$taskIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare("SELECT task_id, remind_at, channel FROM crm_task_reminders WHERE company_id = ? AND task_id IN ($placeholders) ORDER BY remind_at ASC");
        $stmt->bindValue(1, $companyId, PDO::PARAM_INT);
        foreach ($taskIds as $index => $taskId) {
            $stmt->bindValue($index + 2, (int) $taskId, PDO::PARAM_INT);
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

    public function updateDetails(int $taskId, int $companyId, array $payload): ?array
    {
        $updates = [];
        $params = [':id' => $taskId, ':company_id' => $companyId];

        if (array_key_exists('title', $payload)) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title !== '') {
                $updates[] = 'title = :title';
                $params[':title'] = $title;
            }
        }

        if (array_key_exists('description', $payload)) {
            $description = $this->nullableString($payload['description'] ?? null);
            $updates[] = 'description = :description';
            $params[':description'] = $description;
        }

        if (array_key_exists('priority', $payload)) {
            $updates[] = 'priority = :priority';
            $params[':priority'] = $this->sanitizePriority($payload['priority']);
        }

        if (array_key_exists('assigned_to', $payload)) {
            $assignedTo = $payload['assigned_to'] !== null && $payload['assigned_to'] !== '' ? (int) $payload['assigned_to'] : null;
            $updates[] = 'assigned_to = :assigned_to';
            $params[':assigned_to'] = $assignedTo;
        }

        if (array_key_exists('category', $payload)) {
            $category = $this->sanitizeCategory($payload['category'] ?? null);
            $updates[] = 'category = :category';
            $params[':category'] = $category;
        }

        if (array_key_exists('tags', $payload)) {
            $tags = $this->normalizeJson($payload['tags'] ?? null);
            $updates[] = 'tags = :tags';
            $params[':tags'] = $tags;
        }

        if (array_key_exists('metadata', $payload)) {
            $metadata = $this->normalizeJson($payload['metadata'] ?? null);
            $updates[] = 'metadata = :metadata';
            $params[':metadata'] = $metadata;
        }

        if (!$updates) {
            return $this->find($taskId, $companyId);
        }

        $updates[] = 'updated_at = NOW()';
        $sql = 'UPDATE crm_tasks SET ' . implode(', ', $updates) . ' WHERE id = :id AND company_id = :company_id';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            if (in_array($key, [':description', ':tags', ':metadata', ':category', ':title', ':priority'], true)) {
                $stmt->bindValue($key, $value, $value !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                continue;
            }

            if ($key === ':assigned_to') {
                $stmt->bindValue($key, $value, $value !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                continue;
            }

            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $this->find($taskId, $companyId);
    }

    public function summary(int $companyId, array $filters = []): array
    {
        $viewerId = isset($filters['viewer_id']) ? (int) $filters['viewer_id'] : null;
        $isAdmin = (bool) ($filters['is_admin'] ?? false);
        $dueExpression = "COALESCE(due_at, CONCAT(due_date, ' 23:59:59'))";

        $baseWhere = 'company_id = :company_id';
        $params = [':company_id' => $companyId];

        if (!$isAdmin && $viewerId) {
            $baseWhere .= ' AND (assigned_to = :viewer_id OR created_by = :viewer_id)';
            $params[':viewer_id'] = $viewerId;
        }

        $summarySql = "
            SELECT
                SUM(CASE WHEN $dueExpression IS NOT NULL AND $dueExpression < NOW() AND status NOT IN ('completada','cancelada') THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN DATE($dueExpression) = CURRENT_DATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN $dueExpression >= CURRENT_DATE() AND $dueExpression < DATE_ADD(CURRENT_DATE(), INTERVAL 8 DAY) THEN 1 ELSE 0 END) AS week
            FROM crm_tasks
            WHERE $baseWhere
        ";

        $stmt = $this->pdo->prepare($summarySql);
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['overdue' => 0, 'today' => 0, 'week' => 0];

        $byAssigneeSql = "
            SELECT
                assigned_to,
                SUM(CASE WHEN $dueExpression IS NOT NULL AND $dueExpression < NOW() AND status NOT IN ('completada','cancelada') THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN DATE($dueExpression) = CURRENT_DATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN $dueExpression >= CURRENT_DATE() AND $dueExpression < DATE_ADD(CURRENT_DATE(), INTERVAL 8 DAY) THEN 1 ELSE 0 END) AS week
            FROM crm_tasks
            WHERE $baseWhere
            GROUP BY assigned_to
        ";

        $stmt = $this->pdo->prepare($byAssigneeSql);
        $stmt->execute($params);
        $byAssignee = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byModuleSql = "
            SELECT
                source_module,
                SUM(CASE WHEN $dueExpression IS NOT NULL AND $dueExpression < NOW() AND status NOT IN ('completada','cancelada') THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN DATE($dueExpression) = CURRENT_DATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN $dueExpression >= CURRENT_DATE() AND $dueExpression < DATE_ADD(CURRENT_DATE(), INTERVAL 8 DAY) THEN 1 ELSE 0 END) AS week
            FROM crm_tasks
            WHERE $baseWhere
            GROUP BY source_module
        ";

        $stmt = $this->pdo->prepare($byModuleSql);
        $stmt->execute($params);
        $byModule = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'totals' => $totals,
            'by_assigned_to' => $byAssignee,
            'by_source_module' => $byModule,
        ];
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

    private function sanitizePriority(?string $priority): string
    {
        if (!$priority) {
            return 'media';
        }

        $priority = strtolower(trim($priority));
        if (!in_array($priority, self::PRIORITIES, true)) {
            return 'media';
        }

        return $priority;
    }

    private function sanitizeReminderChannel(?string $channel): string
    {
        if (!$channel) {
            return 'in_app';
        }

        $channel = strtolower(trim($channel));
        if (!in_array($channel, self::REMINDER_CHANNELS, true)) {
            return 'in_app';
        }

        return $channel;
    }

    private function sanitizeCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $category = strtolower(trim($category));
        if ($category === '') {
            return null;
        }

        if (!in_array($category, self::CATEGORIES, true)) {
            return null;
        }

        return $category;
    }

    private function normalizeJson($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            return $encoded === false ? null : $encoded;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
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

    private function normalizeDate($value): ?string
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

        return date('Y-m-d', $timestamp);
    }

    /**
     * @param array<string, mixed> $task
     */
    private function applyComputedFields(array &$task): void
    {
        $dueAt = $task['due_at'] ?? null;
        $dueDate = $task['due_date'] ?? null;
        $minutesToDue = isset($task['minutes_to_due']) ? (int) $task['minutes_to_due'] : null;

        if (!$dueAt && $dueDate) {
            $dueAt = $dueDate . ' 23:59:59';
            $task['due_at'] = $dueAt;
        }

        if (!$dueDate && $dueAt) {
            $task['due_date'] = substr((string) $dueAt, 0, 10);
            $dueDate = $task['due_date'];
        }

        if (!$dueAt) {
            $task['is_overdue'] = false;
            $task['sla_minutes_overdue'] = 0;
            $task['risk_level'] = null;
            return;
        }

        $status = $task['status'] ?? '';
        $isCompleted = in_array($status, ['completada', 'cancelada'], true);

        if ($minutesToDue === null) {
            $dueTimestamp = strtotime((string) $dueAt);
            $nowTimestamp = time();
            if ($dueTimestamp === false) {
                $task['is_overdue'] = false;
                $task['sla_minutes_overdue'] = 0;
                $task['risk_level'] = null;
                return;
            }
            $minutesToDue = (int) round(($dueTimestamp - $nowTimestamp) / 60);
        }

        if ($minutesToDue === null) {
            $task['is_overdue'] = false;
            $task['sla_minutes_overdue'] = 0;
            $task['risk_level'] = null;
            return;
        }

        $isOverdue = $minutesToDue < 0 && !$isCompleted;
        $task['is_overdue'] = $isOverdue;
        $task['sla_minutes_overdue'] = $isOverdue ? abs($minutesToDue) : 0;

        if ($isCompleted) {
            $task['risk_level'] = 'verde';
            return;
        }

        if ($minutesToDue <= 0) {
            $task['risk_level'] = 'rojo';
            return;
        }

        if ($minutesToDue <= 240) {
            $task['risk_level'] = 'amarillo';
            return;
        }

        $task['risk_level'] = 'verde';
    }
}
