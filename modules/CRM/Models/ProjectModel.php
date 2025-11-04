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

        $title = trim((string) ($data['title'] ?? ''));
        $description = $this->nullableString($data['description'] ?? null);
        $startDate = $this->nullableString($data['start_date'] ?? null);
        $dueDate = $this->nullableString($data['due_date'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_projects
                (title, description, status, owner_id, lead_id, customer_id, start_date, due_date, created_by)
            VALUES
                (:title, :description, :status, :owner, :lead, :customer, :start_date, :due_date, :created_by)
        ");

        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':owner', $owner, $owner ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':lead', $lead, $lead ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer', $customer, $customer ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':start_date', $startDate, $startDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':due_date', $dueDate, $dueDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $status = $this->sanitizeStatus($status);
        $stmt = $this->pdo->prepare('UPDATE crm_projects SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

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
