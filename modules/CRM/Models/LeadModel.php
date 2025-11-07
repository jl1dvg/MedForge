<?php

namespace Modules\CRM\Models;

use PDO;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\WhatsApp\Services\Messenger as WhatsAppMessenger;
use Modules\WhatsApp\WhatsAppModule;

class LeadModel
{
    private PDO $pdo;
    private LeadConfigurationService $configService;
    private WhatsAppMessenger $whatsapp;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->configService = new LeadConfigurationService($pdo);
        $this->whatsapp = WhatsAppModule::messenger($pdo);
    }

    public function getStatuses(): array
    {
        return $this->configService->getPipelineStages();
    }

    public function getSources(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT source FROM crm_leads WHERE source IS NOT NULL AND source <> '' ORDER BY source");

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
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

        if (!empty($filters['status'])) {
            $status = $this->configService->normalizeStage($filters['status'], false);
            if ($status !== '') {
                $sql .= " AND l.status = :status";
                $params[':status'] = $status;
            }
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

        $lead = $this->find((int) $this->pdo->lastInsertId());
        if ($lead) {
            $this->notifyLeadEvent('created', $lead, [
                'created_by' => $userId,
            ]);
        }

        return $lead;
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

        $actualizado = $this->find($id);
        if ($actualizado) {
            $this->notifyLeadEvent('updated', $actualizado, [
                'previous' => $lead,
                'changes' => $data,
            ]);
        }

        return $actualizado;
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $anterior = $this->find($id);
        if (!$anterior) {
            return null;
        }

        $status = $this->sanitizeStatus($status);
        $stmt = $this->pdo->prepare('UPDATE crm_leads SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        $actualizado = $this->find($id);
        if ($actualizado) {
            $this->notifyLeadEvent('status_updated', $actualizado, [
                'previous' => $anterior,
            ]);
        }

        return $actualizado;
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
        $actualizado = $this->updateStatus($id, $this->configService->getWonStage());

        if ($actualizado) {
            $this->notifyLeadEvent('converted', $actualizado, [
                'customer_id' => $customerId,
            ]);
        }

        return $actualizado;
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $context
     */
    private function notifyLeadEvent(string $event, array $lead, array $context = []): void
    {
        if (!$this->whatsapp->isEnabled()) {
            return;
        }

        $phones = $this->collectLeadPhones($lead, $context);
        if (empty($phones)) {
            return;
        }

        $message = $this->buildLeadMessage($event, $lead, $context);
        if ($message === '') {
            return;
        }

        $this->whatsapp->sendTextMessage($phones, $message);
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $context
     *
     * @return string[]
     */
    private function collectLeadPhones(array $lead, array $context = []): array
    {
        $phones = [];

        foreach (['phone', 'contact_phone', 'customer_phone'] as $key) {
            if (!empty($lead[$key])) {
                $phones[] = (string) $lead[$key];
            }
        }

        if (!empty($context['phone'])) {
            $phones[] = (string) $context['phone'];
        }

        return array_values(array_unique(array_filter($phones)));
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $context
     */
    private function buildLeadMessage(string $event, array $lead, array $context = []): string
    {
        $brand = $this->whatsapp->getBrandName();
        $greeting = $this->buildLeadGreeting($lead);

        switch ($event) {
            case 'created':
                $lines = [
                    $greeting,
                    'Somos ' . $brand . '.',
                    'Registramos tu solicitud y pronto te contactaremos.',
                ];
                if (!empty($lead['status'])) {
                    $lines[] = 'Estado inicial: ' . $lead['status'];
                }
                if (!empty($lead['source'])) {
                    $lines[] = 'Origen: ' . $lead['source'];
                }
                $lines[] = 'Si necesitas ayuda, responde a este mensaje.';

                return implode("\n", array_filter($lines));

            case 'updated':
                $previous = $context['previous'] ?? [];
                $statusChanged = ($lead['status'] ?? null) !== ($previous['status'] ?? null);
                $assignedChanged = ($lead['assigned_to'] ?? null) !== ($previous['assigned_to'] ?? null);

                if (!$statusChanged && !$assignedChanged) {
                    return '';
                }

                $lines = [$greeting, 'Tenemos novedades desde ' . $brand . '.'];
                if ($statusChanged && !empty($lead['status'])) {
                    $lines[] = 'Tu estado ahora es: ' . $lead['status'];
                }
                if ($assignedChanged) {
                    $asesor = $lead['assigned_name'] ?? 'nuestro equipo';
                    $lines[] = 'Tu asesor asignado es: ' . $asesor;
                }
                $lines[] = 'Seguimos atentos a tus comentarios.';

                return implode("\n", array_filter($lines));

            case 'status_updated':
                $previousStatus = $context['previous']['status'] ?? null;
                if (($lead['status'] ?? null) === $previousStatus) {
                    return '';
                }

                $lines = [$greeting];
                if (!empty($lead['status'])) {
                    $lines[] = 'Actualizamos el estado de tu caso a: ' . $lead['status'];
                } else {
                    $lines[] = 'Tenemos novedades sobre tu caso.';
                }
                $lines[] = 'Gracias por confiar en ' . $brand . '.';

                return implode("\n", array_filter($lines));

            case 'converted':
                $lines = [
                    $greeting,
                    '🎉 ¡Tu proceso con ' . $brand . ' ha sido completado exitosamente!',
                    'En breve nos pondremos en contacto para los siguientes pasos.',
                ];

                return implode("\n", array_filter($lines));

            default:
                return '';
        }
    }

    private function buildLeadGreeting(array $lead): string
    {
        $name = trim((string) ($lead['name'] ?? ''));

        if ($name === '') {
            return 'Hola 👋';
        }

        return 'Hola ' . $name . ' 👋';
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
        return $this->configService->normalizeStage($status);
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
