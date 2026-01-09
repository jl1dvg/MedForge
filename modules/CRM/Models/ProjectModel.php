<?php

namespace Modules\CRM\Models;

use PDO;

class ProjectModel
{
    private PDO $pdo;

    private const STATUSES = ['planificado', 'en_proceso', 'en_espera', 'completado', 'cancelado'];

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
                p.id,
                p.title,
                p.description,
                p.status,
                p.lead_id,
                p.customer_id,
                p.hc_number,
                p.form_id,
                p.source_module,
                p.source_ref_id,
                p.episode_type,
                p.eye,
                p.owner_id,
                p.start_date,
                p.due_date,
                p.created_by,
                p.created_at,
                p.updated_at,
                owner.nombre AS owner_name,
                l.name AS lead_name,
                customer.name AS customer_name
            FROM crm_projects p
            LEFT JOIN users owner ON p.owner_id = owner.id
            LEFT JOIN crm_leads l ON p.lead_id = l.id
            LEFT JOIN crm_customers customer ON p.customer_id = customer.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['owner_id'])) {
            $sql .= " AND p.owner_id = :owner";
            $params[':owner'] = (int) $filters['owner_id'];
        }

        if (!empty($filters['lead_id'])) {
            $sql .= " AND p.lead_id = :lead";
            $params[':lead'] = (int) $filters['lead_id'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND p.customer_id = :customer";
            $params[':customer'] = (int) $filters['customer_id'];
        }

        if (!empty($filters['hc_number'])) {
            $sql .= " AND p.hc_number = :hc_number";
            $params[':hc_number'] = (string) $filters['hc_number'];
        }

        if (!empty($filters['form_id'])) {
            $sql .= " AND p.form_id = :form_id";
            $params[':form_id'] = (int) $filters['form_id'];
        }

        if (!empty($filters['source_module'])) {
            $sql .= " AND p.source_module = :source_module";
            $params[':source_module'] = (string) $filters['source_module'];
        }

        if (!empty($filters['source_ref_id'])) {
            $sql .= " AND p.source_ref_id = :source_ref_id";
            $params[':source_ref_id'] = (string) $filters['source_ref_id'];
        }

        if (!empty($filters['episode_type'])) {
            $sql .= " AND p.episode_type = :episode_type";
            $params[':episode_type'] = (string) $filters['episode_type'];
        }

        if (!empty($filters['eye'])) {
            $sql .= " AND p.eye = :eye";
            $params[':eye'] = (string) $filters['eye'];
        }

        $sql .= ' ORDER BY p.updated_at DESC';

        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 100;
        $sql .= ' LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.title,
                p.description,
                p.status,
                p.lead_id,
                p.customer_id,
                p.hc_number,
                p.form_id,
                p.source_module,
                p.source_ref_id,
                p.episode_type,
                p.eye,
                p.owner_id,
                p.start_date,
                p.due_date,
                p.created_by,
                p.created_at,
                p.updated_at,
                owner.nombre AS owner_name,
                l.name AS lead_name,
                customer.name AS customer_name
            FROM crm_projects p
            LEFT JOIN users owner ON p.owner_id = owner.id
            LEFT JOIN crm_leads l ON p.lead_id = l.id
            LEFT JOIN crm_customers customer ON p.customer_id = customer.id
            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        return $project ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $status = $this->sanitizeStatus($data['status'] ?? null);
        $owner = !empty($data['owner_id']) ? (int) $data['owner_id'] : null;
        $lead = !empty($data['lead_id']) ? (int) $data['lead_id'] : null;
        $customer = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;
        $hcNumber = $this->nullableString($data['hc_number'] ?? null);
        $formId = !empty($data['form_id']) ? (int) $data['form_id'] : null;
        $sourceModule = $this->nullableString($data['source_module'] ?? null);
        $sourceRefId = $this->nullableString($data['source_ref_id'] ?? null);
        $episodeType = $this->nullableString($data['episode_type'] ?? null);
        $eye = $this->nullableString($data['eye'] ?? null);

        $title = trim((string) ($data['title'] ?? ''));
        $description = $this->nullableString($data['description'] ?? null);
        $startDate = $this->nullableString($data['start_date'] ?? null);
        $dueDate = $this->nullableString($data['due_date'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_projects
                (
                    title,
                    description,
                    status,
                    owner_id,
                    lead_id,
                    customer_id,
                    hc_number,
                    form_id,
                    source_module,
                    source_ref_id,
                    episode_type,
                    eye,
                    start_date,
                    due_date,
                    created_by
                )
            VALUES
                (
                    :title,
                    :description,
                    :status,
                    :owner,
                    :lead,
                    :customer,
                    :hc_number,
                    :form_id,
                    :source_module,
                    :source_ref_id,
                    :episode_type,
                    :eye,
                    :start_date,
                    :due_date,
                    :created_by
                )
        ");

        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':owner', $owner, $owner ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':lead', $lead, $lead ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer', $customer, $customer ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':hc_number', $hcNumber, $hcNumber !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':form_id', $formId, $formId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':source_module', $sourceModule, $sourceModule !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':source_ref_id', $sourceRefId, $sourceRefId !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':episode_type', $episodeType, $episodeType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':eye', $eye, $eye !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':start_date', $startDate, $startDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':due_date', $dueDate, $dueDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function findByFormId(int $formId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM crm_projects WHERE form_id = :form_id LIMIT 1');
        $stmt->execute([':form_id' => $formId]);
        $projectId = $stmt->fetchColumn();

        return $projectId ? $this->find((int) $projectId) : null;
    }

    public function findBySource(string $sourceModule, string $sourceRefId): ?array
    {
        $sourceModule = trim($sourceModule);
        $sourceRefId = trim($sourceRefId);
        if ($sourceModule === '' || $sourceRefId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM crm_projects WHERE source_module = :source_module AND source_ref_id = :source_ref_id LIMIT 1');
        $stmt->execute([
            ':source_module' => $sourceModule,
            ':source_ref_id' => $sourceRefId,
        ]);
        $projectId = $stmt->fetchColumn();

        return $projectId ? $this->find((int) $projectId) : null;
    }

    public function findOpenByLeadId(int $leadId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id
            FROM crm_projects
            WHERE lead_id = :lead_id
              AND status NOT IN ("completado", "cancelado")
            ORDER BY updated_at DESC
            LIMIT 1
        ');
        $stmt->execute([':lead_id' => $leadId]);
        $projectId = $stmt->fetchColumn();

        return $projectId ? $this->find((int) $projectId) : null;
    }

    public function findRecentOpenByHcEpisodeEye(string $hcNumber, string $episodeType, string $eye): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id
            FROM crm_projects
            WHERE hc_number = :hc_number
              AND episode_type = :episode_type
              AND eye = :eye
              AND status NOT IN ("completado", "cancelado")
            ORDER BY updated_at DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':hc_number' => $hcNumber,
            ':episode_type' => $episodeType,
            ':eye' => $eye,
        ]);
        $projectId = $stmt->fetchColumn();

        return $projectId ? $this->find((int) $projectId) : null;
    }

    public function updateLinks(int $id, array $data): ?array
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        $allowClear = !empty($data['allow_clear']); // Permite limpiar campos con null, no sobrescribir valores existentes.
        $fields = [];
        $params = [':id' => $id];
        $types = [];

        $updates = [
            'lead_id' => [
                'present' => array_key_exists('lead_id', $data),
                'value' => !empty($data['lead_id']) ? (int) $data['lead_id'] : null,
            ],
            'customer_id' => [
                'present' => array_key_exists('customer_id', $data),
                'value' => !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            ],
            'hc_number' => [
                'present' => array_key_exists('hc_number', $data),
                'value' => $this->nullableString($data['hc_number'] ?? null),
            ],
            'form_id' => [
                'present' => array_key_exists('form_id', $data),
                'value' => !empty($data['form_id']) ? (int) $data['form_id'] : null,
            ],
            'source_module' => [
                'present' => array_key_exists('source_module', $data),
                'value' => $this->nullableString($data['source_module'] ?? null),
            ],
            'source_ref_id' => [
                'present' => array_key_exists('source_ref_id', $data),
                'value' => $this->nullableString($data['source_ref_id'] ?? null),
            ],
            'episode_type' => [
                'present' => array_key_exists('episode_type', $data),
                'value' => $this->nullableString($data['episode_type'] ?? null),
            ],
            'eye' => [
                'present' => array_key_exists('eye', $data),
                'value' => $this->nullableString($data['eye'] ?? null),
            ],
        ];
        $typeMap = [
            'lead_id' => PDO::PARAM_INT,
            'customer_id' => PDO::PARAM_INT,
            'hc_number' => PDO::PARAM_STR,
            'form_id' => PDO::PARAM_INT,
            'source_module' => PDO::PARAM_STR,
            'source_ref_id' => PDO::PARAM_STR,
            'episode_type' => PDO::PARAM_STR,
            'eye' => PDO::PARAM_STR,
        ];

        foreach ($updates as $column => $entry) {
            if (empty($entry['present'])) {
                continue;
            }

            $value = $entry['value'] ?? null;
            $current = $project[$column] ?? null;

            if ($allowClear && ($value === null || $value === '')) {
                $value = null;
            } elseif ($current !== null && $current !== '') {
                continue;
            } elseif ($value === null || $value === '') {
                continue;
            }

            $fields[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
            $types[':' . $column] = $value === null ? PDO::PARAM_NULL : ($typeMap[$column] ?? PDO::PARAM_STR);
        }

        if (!$fields) {
            return $project;
        }

        $sql = 'UPDATE crm_projects SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $types[$key] ?? (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();

        return $this->find($id);
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $status = $this->sanitizeStatus($status);
        $stmt = $this->pdo->prepare('UPDATE crm_projects SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        return $this->find($id);
    }

    public function update(int $id, array $data): ?array
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        $allowClear = !empty($data['allow_clear']);
        $fields = [];
        $params = [':id' => $id];
        $types = [];

        $updates = [
            'status' => [
                'present' => array_key_exists('status', $data),
                'value' => $this->sanitizeStatus($data['status'] ?? null),
            ],
            'owner_id' => [
                'present' => array_key_exists('owner_id', $data),
                'value' => !empty($data['owner_id']) ? (int) $data['owner_id'] : null,
            ],
            'start_date' => [
                'present' => array_key_exists('start_date', $data),
                'value' => $this->nullableString($data['start_date'] ?? null),
            ],
            'due_date' => [
                'present' => array_key_exists('due_date', $data),
                'value' => $this->nullableString($data['due_date'] ?? null),
            ],
            'description' => [
                'present' => array_key_exists('description', $data),
                'value' => $this->nullableString($data['description'] ?? null),
            ],
        ];
        $typeMap = [
            'status' => PDO::PARAM_STR,
            'owner_id' => PDO::PARAM_INT,
            'start_date' => PDO::PARAM_STR,
            'due_date' => PDO::PARAM_STR,
            'description' => PDO::PARAM_STR,
        ];

        foreach ($updates as $column => $entry) {
            if (empty($entry['present'])) {
                continue;
            }

            $value = $entry['value'] ?? null;
            $current = $project[$column] ?? null;

            if ($allowClear && ($value === null || $value === '')) {
                $value = null;
            } elseif ($current !== null && $current !== '' && $current === $value) {
                continue;
            }

            $fields[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
            $types[':' . $column] = $value === null ? PDO::PARAM_NULL : ($typeMap[$column] ?? PDO::PARAM_STR);
        }

        if (!$fields) {
            return $project;
        }

        $sql = 'UPDATE crm_projects SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $types[$key] ?? (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();

        return $this->find($id);
    }

    private function sanitizeStatus(?string $status): string
    {
        if (!$status) {
            return 'planificado';
        }

        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            return 'planificado';
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
}
