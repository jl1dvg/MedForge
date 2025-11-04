<?php

namespace Modules\CRM\Models;

use PDO;

class LeadModel
{
    private PDO $pdo;

    private const STATUSES = ['nuevo', 'en_proceso', 'convertido', 'perdido'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(array $filters = []): array
    {
        $sql = "
            SELECT
                l.id,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.source,
                l.notes,
                l.customer_id,
                l.assigned_to,
                l.created_by,
                l.created_at,
                l.updated_at,
                u.nombre AS assigned_name,
                c.name AS customer_name
            FROM crm_leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN crm_customers c ON l.customer_id = c.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= " AND l.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (l.name LIKE :search OR l.email LIKE :search OR l.phone LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['source'])) {
            $sql .= " AND l.source = :source";
            $params[':source'] = $filters['source'];
        }

        $sql .= " ORDER BY l.updated_at DESC";

        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 100;
        $sql .= " LIMIT :limit";

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
                l.id,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.source,
                l.notes,
                l.customer_id,
                l.assigned_to,
                l.created_by,
                l.created_at,
                l.updated_at,
                u.nombre AS assigned_name,
                c.name AS customer_name
            FROM crm_leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN crm_customers c ON l.customer_id = c.id
            WHERE l.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lead ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $status = $this->sanitizeStatus($data['status'] ?? null);
        $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $customerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;

        $name = trim((string) ($data['name'] ?? ''));
        $email = $this->nullableString($data['email'] ?? null);
        $phone = $this->nullableString($data['phone'] ?? null);
        $source = $this->nullableString($data['source'] ?? null);
        $notes = $this->nullableString($data['notes'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_leads
                (name, email, phone, status, source, notes, assigned_to, customer_id, created_by)
            VALUES
                (:name, :email, :phone, :status, :source, :notes, :assigned_to, :customer_id, :created_by)
        ");

        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':email', $email, $email !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':phone', $phone, $phone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':source', $source, $source !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':notes', $notes, $notes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':assigned_to', $assignedTo, $assignedTo ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer_id', $customerId, $customerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?array
    {
        $lead = $this->find($id);
        if (!$lead) {
            return null;
        }

        $fields = [];
        $params = [':id' => $id];
        $types = [':id' => PDO::PARAM_INT];

        if (array_key_exists('name', $data)) {
            $fields[] = 'name = :name';
            $params[':name'] = trim((string) $data['name']);
            $types[':name'] = PDO::PARAM_STR;
        }

        if (array_key_exists('email', $data)) {
            $fields[] = 'email = :email';
            $email = $this->nullableString($data['email']);
            $params[':email'] = $email;
            $types[':email'] = $email !== null ? PDO::PARAM_STR : PDO::PARAM_NULL;
        }

        if (array_key_exists('phone', $data)) {
            $fields[] = 'phone = :phone';
            $phone = $this->nullableString($data['phone']);
            $params[':phone'] = $phone;
            $types[':phone'] = $phone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL;
        }

        if (array_key_exists('source', $data)) {
            $fields[] = 'source = :source';
            $source = $this->nullableString($data['source']);
            $params[':source'] = $source;
            $types[':source'] = $source !== null ? PDO::PARAM_STR : PDO::PARAM_NULL;
        }

        if (array_key_exists('notes', $data)) {
            $fields[] = 'notes = :notes';
            $notes = $this->nullableString($data['notes']);
            $params[':notes'] = $notes;
            $types[':notes'] = $notes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL;
        }

        if (array_key_exists('assigned_to', $data)) {
            $fields[] = 'assigned_to = :assigned_to';
            $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
            $params[':assigned_to'] = $assignedTo;
            $types[':assigned_to'] = $assignedTo !== null ? PDO::PARAM_INT : PDO::PARAM_NULL;
        }

        if (array_key_exists('customer_id', $data)) {
            $fields[] = 'customer_id = :customer_id';
            $customerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;
            $params[':customer_id'] = $customerId;
            $types[':customer_id'] = $customerId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL;
        }

        if (array_key_exists('status', $data)) {
            $fields[] = 'status = :status';
            $status = $this->sanitizeStatus($data['status']);
            $params[':status'] = $status;
            $types[':status'] = PDO::PARAM_STR;
        }

        if (!$fields) {
            return $lead;
        }

        $sql = 'UPDATE crm_leads SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $types[$key] ?? PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();

        return $this->find($id);
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $status = $this->sanitizeStatus($status);
        $stmt = $this->pdo->prepare('UPDATE crm_leads SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        return $this->find($id);
    }

    public function attachCustomer(int $id, int $customerId): void
    {
        $stmt = $this->pdo->prepare('UPDATE crm_leads SET customer_id = :customer WHERE id = :id');
        $stmt->execute([
            ':customer' => $customerId,
            ':id' => $id,
        ]);
    }

    public function convertToCustomer(int $id, array $customerPayload): ?array
    {
        $lead = $this->find($id);
        if (!$lead) {
            return null;
        }

        $customerId = $lead['customer_id'] ? (int) $lead['customer_id'] : $this->upsertCustomer($lead, $customerPayload);
        $this->attachCustomer($id, $customerId);
        $this->updateStatus($id, 'convertido');

        return $this->find($id);
    }

    private function upsertCustomer(array $lead, array $payload): int
    {
        if (!empty($payload['customer_id'])) {
            return (int) $payload['customer_id'];
        }

        $email = trim((string) ($payload['email'] ?? $lead['email'] ?? ''));
        if ($email !== '') {
            $existing = $this->findCustomerBy('email', $email);
            if ($existing) {
                return $existing;
            }
        }

        $phone = trim((string) ($payload['phone'] ?? $lead['phone'] ?? ''));
        if ($phone !== '' && $phone !== null) {
            $existing = $this->findCustomerBy('phone', $phone);
            if ($existing) {
                return $existing;
            }
        }

        $externalRef = trim((string) ($payload['external_ref'] ?? 'lead-' . $lead['id']));
        if ($externalRef !== '') {
            $existing = $this->findCustomerBy('external_ref', $externalRef);
            if ($existing) {
                return $existing;
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_customers
                (type, name, email, phone, document, gender, birthdate, city, address, marital_status, affiliation, nationality, workplace, source, external_ref)
            VALUES
                (:type, :name, :email, :phone, :document, :gender, :birthdate, :city, :address, :marital_status, :affiliation, :nationality, :workplace, :source, :external_ref)
        ");

        $stmt->execute([
            ':type' => $payload['type'] ?? 'person',
            ':name' => trim((string) ($payload['name'] ?? $lead['name'] ?? 'Lead sin nombre')),
            ':email' => $email !== '' ? $email : null,
            ':phone' => $phone !== '' ? $phone : null,
            ':document' => $this->nullableString($payload['document'] ?? null),
            ':gender' => $this->nullableString($payload['gender'] ?? null),
            ':birthdate' => $this->nullableString($payload['birthdate'] ?? null),
            ':city' => $this->nullableString($payload['city'] ?? null),
            ':address' => $this->nullableString($payload['address'] ?? null),
            ':marital_status' => $this->nullableString($payload['marital_status'] ?? null),
            ':affiliation' => $this->nullableString($payload['affiliation'] ?? null),
            ':nationality' => $this->nullableString($payload['nationality'] ?? null),
            ':workplace' => $this->nullableString($payload['workplace'] ?? null),
            ':source' => $payload['source'] ?? ($lead['source'] ?? 'lead'),
            ':external_ref' => $externalRef !== '' ? $externalRef : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function findCustomerBy(string $column, string $value): ?int
    {
        $allowed = ['email', 'phone', 'external_ref'];
        if (!in_array($column, $allowed, true)) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM crm_customers WHERE $column = :value LIMIT 1");
        $stmt->execute([':value' => $value]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private function sanitizeStatus(?string $status): string
    {
        if (!$status) {
            return 'nuevo';
        }

        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            return 'nuevo';
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
