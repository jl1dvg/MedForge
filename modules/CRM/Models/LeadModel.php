<?php

namespace Modules\CRM\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\Shared\Services\PatientIdentityService;
use Modules\Shared\Services\SchemaInspector;
use Modules\WhatsApp\Services\Messenger as WhatsAppMessenger;
use Modules\WhatsApp\WhatsAppModule;
use RuntimeException;

class LeadModel
{
    private PDO $pdo;
    private LeadConfigurationService $configService;
    private WhatsAppMessenger $whatsapp;
    private PatientIdentityService $identityService;
    private ?bool $crmCustomerHasHcNumber = null;
    private SchemaInspector $schemaInspector;
    private ?bool $hasLeadNameColumns = null;
    private static ?bool $leadNameColumnsAvailable = null;
    /**
     * @var array<string, string>
     */
    private array $notifiedStageCache = [];
    private const STAGE_NOTIFY_COOLDOWN_MINUTES = 30;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->configService = new LeadConfigurationService($pdo);
        $this->whatsapp = WhatsAppModule::messenger($pdo);
        $this->identityService = new PatientIdentityService($pdo);
        $this->schemaInspector = new SchemaInspector($pdo);
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
        $customerHcSelect = $this->getCustomerHcSelect();
        $sql = "
            SELECT
                l.id,
                l.hc_number,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.last_stage_notified,
                l.last_stage_notified_at,
                l.source,
                l.notes,
                l.customer_id,
                l.assigned_to,
                l.created_by,
                l.created_at,
                l.updated_at,
                u.nombre AS assigned_name,
                c.name AS customer_name,
                $customerHcSelect
            FROM crm_leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN crm_customers c ON l.customer_id = c.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'sin_estado') {
                $sql .= " AND (l.status IS NULL OR l.status = '')";
            } else {
                $status = $this->configService->normalizeStage($filters['status'], false);
                if ($status !== '') {
                    $sql .= " AND l.status = :status";
                    $params[':status'] = $status;
                }
            }
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (l.name LIKE :search OR l.email LIKE :search OR l.phone LIKE :search OR l.hc_number LIKE :search OR c.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['source'])) {
            $sql .= " AND l.source = :source";
            $params[':source'] = $filters['source'];
        }

        $sql .= " ORDER BY l.updated_at DESC";

        $limit = isset($filters['limit']) ? max(1, (int)$filters['limit']) : 50;
        $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
        $sql .= " LIMIT :limit OFFSET :offset";

        $debugParams = $params;
        $debugParams[':limit'] = $limit;
        $debugParams[':offset'] = $offset;

        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logSqlException($e, $sql ?? null, $debugParams ?? []);
            throw $e;
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM crm_leads l
            LEFT JOIN crm_customers c ON l.customer_id = c.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'sin_estado') {
                $sql .= " AND (l.status IS NULL OR l.status = '')";
            } else {
                $status = $this->configService->normalizeStage($filters['status'], false);
                if ($status !== '') {
                    $sql .= " AND l.status = :status";
                    $params[':status'] = $status;
                }
            }
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = :assigned_to";
            $params[':assigned_to'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (l.name LIKE :search OR l.email LIKE :search OR l.phone LIKE :search OR l.hc_number LIKE :search OR c.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['source'])) {
            $sql .= " AND l.source = :source";
            $params[':source'] = $filters['source'];
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function findById(int $id): ?array
    {
        $customerHcSelect = $this->getCustomerHcSelect();
        $stmt = $this->pdo->prepare("
            SELECT
                l.id,
                l.hc_number,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.last_stage_notified,
                l.last_stage_notified_at,
                l.source,
                l.notes,
                l.customer_id,
                l.assigned_to,
                l.created_by,
                l.created_at,
                l.updated_at,
                u.nombre AS assigned_name,
                c.name AS customer_name,
                $customerHcSelect
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

    public function find(int $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * @return array{lead: array<string, mixed>, patient: array<string, mixed>|null}|null
     */
    public function fetchProfileById(int $id): ?array
    {
        $lead = $this->findById($id);
        if (!$lead) {
            return null;
        }

        $patient = null;
        if (!empty($lead['hc_number'])) {
            $patient = $this->identityService->findPatient((string) $lead['hc_number']);
        }

        return [
            'lead' => $lead,
            'patient' => $patient,
        ];
    }

    public function findByHcNumber(string $hcNumber): ?array
    {
        $normalized = $this->identityService->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            return null;
        }

        $customerHcSelect = $this->getCustomerHcSelect();
        $stmt = $this->pdo->prepare("
            SELECT
                l.id,
                l.hc_number,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.last_stage_notified,
                l.last_stage_notified_at,
                l.source,
                l.notes,
                l.customer_id,
                l.assigned_to,
                l.created_by,
                l.created_at,
                l.updated_at,
                u.nombre AS assigned_name,
                c.name AS customer_name,
                $customerHcSelect
            FROM crm_leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN crm_customers c ON l.customer_id = c.id
            WHERE l.hc_number = :hc
            LIMIT 1
        ");

        $stmt->execute([':hc' => $normalized]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lead ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $hcNumber = $this->identityService->normalizeHcNumber((string)($data['hc_number'] ?? ''));
        if ($hcNumber === '') {
            throw new InvalidArgumentException('El campo hc_number es obligatorio.');
        }

        $this->assertHcNumberAvailable($hcNumber);

        $status = $this->sanitizeStatus($data['status'] ?? null);
        $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
        $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;

        $name = trim((string)($data['name'] ?? ''));
        [$firstName, $lastName] = $this->splitNameParts($name);
        $email = $this->nullableString($data['email'] ?? null);
        $phone = $this->nullableString($data['phone'] ?? null);
        $source = $this->nullableString($data['source'] ?? null);
        $notes = $this->nullableString($data['notes'] ?? null);

        $identity = $this->identityService->ensureIdentity($hcNumber, [
            'customer' => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'source' => $source,
            ],
            'patient' => [
                'name' => $name,
                'celular' => $phone,
            ],
        ]);

        if (!$customerId && !empty($identity['customer_id'])) {
            $customerId = (int)$identity['customer_id'];
        }

        $hasSplitNames = $this->hasLeadNameColumns();

        $columns = ['hc_number', 'name'];
        $placeholders = [':hc_number', ':name'];

        if ($hasSplitNames) {
            $columns[] = 'first_name';
            $columns[] = 'last_name';
            $placeholders[] = ':first_name';
            $placeholders[] = ':last_name';
        }

        $columns = array_merge($columns, ['email', 'phone', 'status', 'source', 'notes', 'assigned_to', 'customer_id', 'created_by']);
        $placeholders = array_merge($placeholders, [':email', ':phone', ':status', ':source', ':notes', ':assigned_to', ':customer_id', ':created_by']);

        $sql = sprintf(
            'INSERT INTO crm_leads (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $debugParams = [
            ':hc_number' => $hcNumber,
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':status' => $status,
            ':source' => $source,
            ':notes' => $notes,
            ':assigned_to' => $assignedTo,
            ':customer_id' => $customerId,
            ':created_by' => $userId ?: null,
        ];

        if ($hasSplitNames) {
            $debugParams[':first_name'] = $firstName !== '' ? $firstName : null;
            $debugParams[':last_name'] = $lastName !== '' ? $lastName : null;
        }

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':hc_number', $hcNumber);
        $stmt->bindValue(':name', $name);

        if ($hasSplitNames) {
            $stmt->bindValue(':first_name', $firstName !== '' ? $firstName : null, $firstName !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':last_name', $lastName !== '' ? $lastName : null, $lastName !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        }

        $stmt->bindValue(':email', $email, $email !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':phone', $phone, $phone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':source', $source, $source !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':notes', $notes, $notes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':assigned_to', $assignedTo, $assignedTo ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':customer_id', $customerId, $customerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logSqlException($e, $sql ?? null, $debugParams ?? []);

            if ($this->isUnknownNameColumn($e)) {
                $this->hasLeadNameColumns = false;
                self::$leadNameColumnsAvailable = false;
                $retrySql = 'INSERT INTO crm_leads (hc_number, name, email, phone, status, source, notes, assigned_to, customer_id, created_by) '
                    . 'VALUES (:hc_number, :name, :email, :phone, :status, :source, :notes, :assigned_to, :customer_id, :created_by)';

                $retryParams = [
                    ':hc_number' => $hcNumber,
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':status' => $status,
                    ':source' => $source,
                    ':notes' => $notes,
                    ':assigned_to' => $assignedTo,
                    ':customer_id' => $customerId,
                    ':created_by' => $userId ?: null,
                ];

                $retryStmt = $this->pdo->prepare($retrySql);
                foreach ($retryParams as $key => $value) {
                    $paramType = PDO::PARAM_STR;
                    if (in_array($key, [':assigned_to', ':customer_id', ':created_by'], true)) {
                        $paramType = $value !== null ? PDO::PARAM_INT : PDO::PARAM_NULL;
                    } elseif (in_array($key, [':email', ':phone', ':source', ':notes'], true)) {
                        $paramType = $value !== null ? PDO::PARAM_STR : PDO::PARAM_NULL;
                    }

                    $retryStmt->bindValue($key, $value, $paramType);
                }

                try {
                    $retryStmt->execute();
                } catch (PDOException $retryException) {
                    $this->logSqlException($retryException, $retrySql, $retryParams);
                    throw $retryException;
                }
            } else {
                throw $e;
            }
        }

        $lead = $this->findByHcNumber($hcNumber);
        if ($lead) {
            $this->notifyLeadEvent('created', $lead, [
                'created_by' => $userId,
            ]);
        }

        return $lead;
    }

    public function update(string $hcNumber, array $data): ?array
    {
        $normalized = $this->identityService->normalizeHcNumber($hcNumber);
        $lead = $this->findByHcNumber($normalized);
        if (!$lead) {
            return null;
        }

        // Mantener hc_number inmutable y evitar side-effects inesperados.
        unset($data['hc_number']);

        $fields = [];
        $params = [':current_hc' => $lead['hc_number']];
        $types = [':current_hc' => PDO::PARAM_STR];

        $providedName = array_key_exists('name', $data) ? trim((string)$data['name']) : null;
        $providedFirst = array_key_exists('first_name', $data) ? trim((string)$data['first_name']) : null;
        $providedLast = array_key_exists('last_name', $data) ? trim((string)$data['last_name']) : null;
        $hasSplitNames = $this->hasLeadNameColumns();

        if ($providedName !== null) {
            if ($providedName === '') {
                $providedName = (string)($lead['name'] ?? '');
            }
            [$derivedFirst, $derivedLast] = $this->splitNameParts($providedName);
            $firstValue = $providedFirst !== null ? $providedFirst : $derivedFirst;
            $lastValue = $providedLast !== null ? $providedLast : $derivedLast;

            $fields[] = 'name = :name';
            $params[':name'] = $providedName;
            $types[':name'] = PDO::PARAM_STR;

            if ($hasSplitNames) {
                $fields[] = 'first_name = :first_name';
                $params[':first_name'] = $firstValue !== '' ? $firstValue : null;
                $types[':first_name'] = $firstValue !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL;

                $fields[] = 'last_name = :last_name';
                $params[':last_name'] = $lastValue !== '' ? $lastValue : null;
                $types[':last_name'] = $lastValue !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL;
            }
        } elseif ($providedFirst !== null || $providedLast !== null) {
            $firstValue = $providedFirst !== null ? $providedFirst : trim((string)($lead['first_name'] ?? ''));
            $lastValue = $providedLast !== null ? $providedLast : trim((string)($lead['last_name'] ?? ''));
            $composedName = trim(($firstValue ? $firstValue . ' ' : '') . $lastValue);

            if ($composedName !== '') {
                $fields[] = 'name = :name';
                $params[':name'] = $composedName;
                $types[':name'] = PDO::PARAM_STR;
            }

            if ($hasSplitNames) {
                $fields[] = 'first_name = :first_name';
                $params[':first_name'] = $firstValue !== '' ? $firstValue : null;
                $types[':first_name'] = $firstValue !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL;

                $fields[] = 'last_name = :last_name';
                $params[':last_name'] = $lastValue !== '' ? $lastValue : null;
                $types[':last_name'] = $lastValue !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL;
            }
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
            $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
            $params[':assigned_to'] = $assignedTo;
            $types[':assigned_to'] = $assignedTo !== null ? PDO::PARAM_INT : PDO::PARAM_NULL;
        }

        if (array_key_exists('customer_id', $data)) {
            $fields[] = 'customer_id = :customer_id';
            $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
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

        $sql = 'UPDATE crm_leads SET ' . implode(', ', $fields) . ' WHERE hc_number = :current_hc';

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $types[$key] ?? PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logSqlException($e, $sql ?? null, $params ?? []);

            if ($this->isUnknownNameColumn($e)) {
                $this->hasLeadNameColumns = false;
                self::$leadNameColumnsAvailable = false;
                $fallbackFields = array_values(array_filter($fields, static function (string $field): bool {
                    return $field !== 'first_name = :first_name' && $field !== 'last_name = :last_name';
                }));

                $fallbackParams = $params;
                unset($fallbackParams[':first_name'], $fallbackParams[':last_name']);

                $fallbackTypes = $types;
                unset($fallbackTypes[':first_name'], $fallbackTypes[':last_name']);

                if ($fallbackFields) {
                    $retrySql = 'UPDATE crm_leads SET ' . implode(', ', $fallbackFields) . ' WHERE hc_number = :current_hc';
                    $retryStmt = $this->pdo->prepare($retrySql);

                    foreach ($fallbackParams as $key => $value) {
                        $retryStmt->bindValue($key, $value, $fallbackTypes[$key] ?? PDO::PARAM_STR);
                    }

                    try {
                        $retryStmt->execute();
                    } catch (PDOException $retryException) {
                        $this->logSqlException($retryException, $retrySql, $fallbackParams);
                        throw $retryException;
                    }
                }
            } else {
                throw $e;
            }
        }

        $actualizado = $this->findByHcNumber($lead['hc_number']);
        if ($actualizado) {
            $identity = $this->identityService->ensureIdentity($actualizado['hc_number'], [
                'customer' => [
                    'name' => $actualizado['name'] ?? '',
                    'email' => $actualizado['email'] ?? null,
                    'phone' => $actualizado['phone'] ?? null,
                    'source' => $actualizado['source'] ?? null,
                ],
                'patient' => [
                    'name' => $actualizado['name'] ?? '',
                    'celular' => $actualizado['phone'] ?? null,
                ],
            ]);

            if (!empty($identity['customer_id']) && (int)($actualizado['customer_id'] ?? 0) !== (int)$identity['customer_id']) {
                $this->attachCustomer($actualizado['hc_number'], (int)$identity['customer_id']);
                $actualizado = $this->findByHcNumber($actualizado['hc_number']);
            }

            $this->notifyLeadEvent('updated', $actualizado, [
                'previous' => $lead,
                'changes' => $data,
            ]);
        }

        return $actualizado;
    }

    public function updateById(int $leadId, array $data): ?array
    {
        $lead = $this->findById($leadId);
        if (!$lead || empty($lead['hc_number'])) {
            return null;
        }

        unset($data['hc_number']);

        return $this->update((string)$lead['hc_number'], $data);
    }

    public function updateStatus(string $hcNumber, string $status): ?array
    {
        $normalized = $this->identityService->normalizeHcNumber($hcNumber);
        $anterior = $this->findByHcNumber($normalized);
        if (!$anterior) {
            return null;
        }

        $status = $this->sanitizeStatus($status);
        $stmt = $this->pdo->prepare('UPDATE crm_leads SET status = :status WHERE hc_number = :hc');
        $stmt->execute([':status' => $status, ':hc' => $anterior['hc_number']]);

        $actualizado = $this->findByHcNumber($anterior['hc_number']);
        if ($actualizado) {
            $this->notifyLeadEvent('status_updated', $actualizado, [
                'previous' => $anterior,
            ]);
        }

        return $actualizado;
    }

    public function updateStatusById(int $leadId, string $status): ?array
    {
        $lead = $this->findById($leadId);
        if (!$lead || empty($lead['hc_number'])) {
            return null;
        }

        return $this->updateStatus((string)$lead['hc_number'], $status);
    }

    /**
     * @return array{total:int, by_status: array<string,int>, lost: array{count:int, percentage:float}}
     */
    public function getMetrics(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) as total FROM crm_leads GROUP BY status');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pipeline = $this->getStatuses();
        $counts = [];
        $normalizedMap = [];
        $total = 0;

        foreach ($pipeline as $stage) {
            $counts[$stage] = 0;
            $normalized = $this->configService->normalizeStage($stage, false);
            if ($normalized !== '') {
                $normalizedMap[$normalized] = $stage;
            }
        }

        $unknown = [];
        $withoutStatus = 0;

        foreach ($rows as $row) {
            $status = $row['status'] ?? null;
            $count = (int)($row['total'] ?? 0);
            $total += $count;

            if ($status === null || $status === '') {
                $withoutStatus += $count;
                continue;
            }

            $normalized = $this->configService->normalizeStage($status, false);
            if ($normalized !== '' && isset($normalizedMap[$normalized])) {
                $counts[$normalizedMap[$normalized]] = ($counts[$normalizedMap[$normalized]] ?? 0) + $count;
                continue;
            }

            $unknown[$status] = ($unknown[$status] ?? 0) + $count;
        }

        $byStatus = $counts;
        if ($withoutStatus > 0) {
            $byStatus['Sin estado'] = $withoutStatus;
        }

        foreach ($unknown as $label => $count) {
            $byStatus[$label] = $count;
        }

        $lostStage = $this->configService->normalizeStage($this->configService->getLostStage() ?? '', false);
        $lostCount = 0;
        foreach ($byStatus as $status => $count) {
            $normalizedStatus = $this->configService->normalizeStage((string)$status, false);
            if ($lostStage !== '' && $normalizedStatus === $lostStage) {
                $lostCount += $count;
            }
        }

        $lostPercentage = $total > 0 ? round(($lostCount / $total) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'lost' => [
                'count' => $lostCount,
                'percentage' => $lostPercentage,
            ],
        ];
    }

    public function attachCustomer(string $hcNumber, int $customerId): void
    {
        $normalized = $this->identityService->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE crm_leads SET customer_id = :customer WHERE hc_number = :hc');
        $stmt->execute([
            ':customer' => $customerId,
            ':hc' => $normalized,
        ]);
    }

    public function convertToCustomer(string $hcNumber, array $customerPayload): ?array
    {
        $lead = $this->findByHcNumber($hcNumber);
        if (!$lead) {
            return null;
        }

        $identity = $this->identityService->ensureIdentity($lead['hc_number'], [
            'customer' => array_merge(
                [
                    'name' => $lead['name'],
                    'email' => $lead['email'],
                    'phone' => $lead['phone'],
                    'source' => $lead['source'],
                ],
                $customerPayload
            ),
            'patient' => [
                'name' => $lead['name'],
                'celular' => $lead['phone'],
            ],
        ]);

        $customerId = $lead['customer_id'] ? (int)$lead['customer_id'] : (int)($identity['customer_id'] ?? 0);
        if ($customerId <= 0) {
            $customerId = $this->upsertCustomer($lead, $customerPayload);
        }

        $this->attachCustomer($lead['hc_number'], $customerId);
        $this->attachCustomerToProjects($lead, $customerId);
        $actualizado = $this->updateStatus($lead['hc_number'], $this->configService->getWonStage());

        if ($actualizado) {
            $this->notifyLeadEvent('converted', $actualizado, [
                'customer_id' => $customerId,
            ]);
        }

        return $actualizado;
    }

    private function attachCustomerToProjects(array $lead, int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }

        $leadId = (int) ($lead['id'] ?? 0);
        $hcNumber = (string) ($lead['hc_number'] ?? '');

        if ($leadId <= 0 && $hcNumber === '') {
            return;
        }

        $sql = '
            UPDATE crm_projects
            SET customer_id = :customer_id
            WHERE customer_id IS NULL
              AND (
                lead_id = :lead_id
                OR hc_number = :hc_number
              )
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customerId,
            ':lead_id' => $leadId,
            ':hc_number' => $hcNumber,
        ]);
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

        $templatePayload = $this->resolveStageTemplatePayload($event, $lead, $context);
        if ($templatePayload !== null && $this->whatsapp->sendTemplateMessage($phones, $templatePayload)) {
            if (isset($templatePayload['_stage'])) {
                $this->markStageNotified((string)($lead['hc_number'] ?? ''), (string)$templatePayload['_stage']);
            }

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
                $phones[] = (string)$lead[$key];
            }
        }

        if (!empty($context['phone'])) {
            $phones[] = (string)$context['phone'];
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
        $name = trim((string)($lead['name'] ?? ''));

        if ($name === '') {
            return 'Hola 👋';
        }

        return 'Hola ' . $name . ' 👋';
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $context
     */
    private function resolveStageTemplatePayload(string $event, array $lead, array $context = []): ?array
    {
        $stage = null;

        if ($event === 'created') {
            $stage = $lead['status'] ?? null;
        } elseif ($event === 'status_updated') {
            $stage = $lead['status'] ?? null;
        } elseif ($event === 'updated') {
            $previousStatus = $context['previous']['status'] ?? null;
            if (($lead['status'] ?? null) !== $previousStatus) {
                $stage = $lead['status'] ?? null;
            }
        } elseif ($event === 'converted') {
            $stage = $this->configService->getWonStage();
        }

        if (!is_string($stage) || trim($stage) === '') {
            return null;
        }

        if (!$this->shouldSendTemplateForStage($lead, $stage)) {
            return null;
        }

        $templateConfig = $this->configService->findStageTemplate($stage);
        if ($templateConfig === null) {
            return null;
        }

        $components = $this->hydrateTemplateComponents($templateConfig['components'] ?? [], $lead, $context);
        if (empty($components)) {
            $components = $this->buildDefaultTemplateComponents($lead);
        }

        return [
            'name' => $templateConfig['template'],
            'language' => $templateConfig['language'] ?? 'es',
            'components' => $components,
            '_stage' => $stage,
        ];
    }

    /**
     * @param array<string, mixed> $lead
     */
    private function buildDefaultTemplateComponents(array $lead): array
    {
        $parameters = array_values(array_filter([
            $this->buildTextParameter($lead['name'] ?? null),
            $this->buildTextParameter($lead['status'] ?? null),
            $this->buildTextParameter($lead['hc_number'] ?? null),
        ]));

        if (empty($parameters)) {
            return [];
        }

        return [
            [
                'type' => 'body',
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function hydrateTemplateComponents(array $components, array $lead, array $context = []): array
    {
        $replacements = $this->buildPlaceholderMap($lead, $context);

        return $this->replacePlaceholdersRecursively($components, $replacements);
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function buildPlaceholderMap(array $lead, array $context = []): array
    {
        $brand = $this->whatsapp->getBrandName();
        $status = (string)($lead['status'] ?? '');
        $source = (string)($lead['source'] ?? '');
        $assigned = (string)($lead['assigned_name'] ?? '');
        $hcNumber = (string)($lead['hc_number'] ?? '');

        return array_filter([
            '{{nombre}}' => (string)($lead['name'] ?? ''),
            '{{paciente}}' => (string)($lead['name'] ?? ''),
            '{{estado}}' => $status,
            '{{origen}}' => $source,
            '{{asesor}}' => $assigned,
            '{{hc}}' => $hcNumber,
            '{{historia}}' => $hcNumber,
            '{{brand}}' => $brand,
            '{{marca}}' => $brand,
            '{{fuente}}' => $source,
            '{{telefono}}' => (string)($lead['phone'] ?? ($context['phone'] ?? '')),
        ], static fn($value) => (string)$value !== '');
    }

    /**
     * @param array|string|int|float|null $value
     * @param array<string, string> $replacements
     * @return array|string|int|float|null
     */
    private function replacePlaceholdersRecursively($value, array $replacements)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replacePlaceholdersRecursively($item, $replacements);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $rendered = strtr($value, $replacements);

        return trim($rendered);
    }

    /**
     * @param mixed $value
     */
    private function buildTextParameter($value): ?array
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        return ['type' => 'text', 'text' => $text];
    }

    private function shouldSendTemplateForStage(array $lead, string $stage): bool
    {
        $hcNumber = (string)($lead['hc_number'] ?? '');
        if ($hcNumber === '') {
            return true;
        }

        $normalizedStage = mb_strtolower(trim($stage));
        $cacheKey = $hcNumber . '|' . $normalizedStage;
        if (isset($this->notifiedStageCache[$cacheKey])) {
            return false;
        }

        $lastNotifiedStage = mb_strtolower(trim((string)($lead['last_stage_notified'] ?? '')));
        $lastNotifiedAt = $lead['last_stage_notified_at'] ?? null;

        if ($lastNotifiedStage === $normalizedStage && $this->isWithinCooldown($lastNotifiedAt)) {
            return false;
        }

        $this->notifiedStageCache[$cacheKey] = gmdate('c');

        return true;
    }

    private function markStageNotified(string $hcNumber, string $stage): void
    {
        $hcNumber = $this->identityService->normalizeHcNumber($hcNumber);
        if ($hcNumber === '' || !$this->hasLeadNotificationColumns()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE crm_leads SET last_stage_notified = :stage, last_stage_notified_at = NOW() WHERE hc_number = :hc LIMIT 1'
        );
        $stmt->execute([
            ':stage' => trim($stage),
            ':hc' => $hcNumber,
        ]);

        $cacheKey = $hcNumber . '|' . mb_strtolower(trim($stage));
        $this->notifiedStageCache[$cacheKey] = gmdate('c');
    }

    private function isWithinCooldown($lastNotifiedAt): bool
    {
        if ($lastNotifiedAt === null || $lastNotifiedAt === '') {
            return false;
        }

        $timestamp = strtotime((string)$lastNotifiedAt);
        if ($timestamp === false) {
            return false;
        }

        $cooldownSeconds = self::STAGE_NOTIFY_COOLDOWN_MINUTES * 60;

        return (time() - $timestamp) < $cooldownSeconds;
    }

    private function hasLeadNameColumns(): bool
    {
        if ($this->hasLeadNameColumns !== null) {
            return $this->hasLeadNameColumns;
        }

        if (self::$leadNameColumnsAvailable !== null) {
            $this->hasLeadNameColumns = self::$leadNameColumnsAvailable;
            return $this->hasLeadNameColumns;
        }

        try {
            $firstExists = (bool)$this->pdo->query("SHOW COLUMNS FROM crm_leads LIKE 'first_name'")->fetchColumn();
            $lastExists = (bool)$this->pdo->query("SHOW COLUMNS FROM crm_leads LIKE 'last_name'")->fetchColumn();
            $this->hasLeadNameColumns = $firstExists && $lastExists;
            self::$leadNameColumnsAvailable = $this->hasLeadNameColumns;
        } catch (\Throwable $t) {
            $this->hasLeadNameColumns = false;
            self::$leadNameColumnsAvailable = false;
        }

        return $this->hasLeadNameColumns;
    }

    private function isUnknownNameColumn(PDOException $e): bool
    {
        $message = $e->getMessage();
        return stripos($message, "Unknown column 'first_name'") !== false
            || stripos($message, "Unknown column 'last_name'") !== false;
    }

    private function hasLeadNotificationColumns(): bool
    {
        return $this->schemaInspector->tableHasColumn('crm_leads', 'last_stage_notified')
            && $this->schemaInspector->tableHasColumn('crm_leads', 'last_stage_notified_at');
    }

    private function upsertCustomer(array $lead, array $payload): int
    {
        $hcNumber = $this->identityService->normalizeHcNumber((string)($lead['hc_number'] ?? ($payload['hc_number'] ?? '')));
        if ($hcNumber !== '') {
            $existingByHc = $this->findCustomerBy('hc_number', $hcNumber);
            if ($existingByHc) {
                return $existingByHc;
            }
        } else {
            throw new RuntimeException('No se pudo determinar el hc_number para sincronizar el cliente.');
        }

        if (!empty($payload['customer_id'])) {
            return (int)$payload['customer_id'];
        }

        $email = trim((string)($payload['email'] ?? $lead['email'] ?? ''));
        if ($email !== '') {
            $existing = $this->findCustomerBy('email', $email);
            if ($existing) {
                return $existing;
            }
        }

        $phone = trim((string)($payload['phone'] ?? $lead['phone'] ?? ''));
        if ($phone !== '' && $phone !== null) {
            $existing = $this->findCustomerBy('phone', $phone);
            if ($existing) {
                return $existing;
            }
        }

        $externalRef = trim((string)($payload['external_ref'] ?? 'lead-' . $lead['id']));
        if ($externalRef !== '') {
            $existing = $this->findCustomerBy('external_ref', $externalRef);
            if ($existing) {
                return $existing;
            }
        }

        $sql = "
            INSERT INTO crm_customers
                (hc_number, type, name, email, phone, document, gender, birthdate, city, address, marital_status, affiliation, nationality, workplace, source, external_ref)
            VALUES
                (:hc_number, :type, :name, :email, :phone, :document, :gender, :birthdate, :city, :address, :marital_status, :affiliation, :nationality, :workplace, :source, :external_ref)
        ";

        $params = [
            ':hc_number' => $hcNumber,
            ':type' => $payload['type'] ?? 'person',
            ':name' => trim((string)($payload['name'] ?? $lead['name'] ?? 'Lead sin nombre')),
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
        ];

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logSqlException($e, $sql ?? null, $params ?? []);
            throw $e;
        }

        return (int)$this->pdo->lastInsertId();
    }

    private function findCustomerBy(string $column, string $value): ?int
    {
        $allowed = ['hc_number', 'email', 'phone', 'external_ref'];
        if (!in_array($column, $allowed, true)) {
            return null;
        }

        if ($column === 'hc_number' && !$this->crmCustomersHasHcNumber()) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM crm_customers WHERE $column = :value LIMIT 1");
        $stmt->execute([':value' => $value]);
        $id = $stmt->fetchColumn();

        return $id ? (int)$id : null;
    }

    private function assertHcNumberAvailable(string $hcNumber, ?string $current = null): void
    {
        $normalized = $this->identityService->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            throw new InvalidArgumentException('El campo hc_number es obligatorio.');
        }

        $stmt = $this->pdo->prepare('SELECT hc_number FROM crm_leads WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $normalized]);
        $existing = $stmt->fetchColumn();

        if ($existing && $existing !== $current) {
            throw new RuntimeException('El número de historia clínica ya está asociado a otro lead.');
        }
    }

    private function sanitizeStatus(?string $status): string
    {
        return $this->configService->normalizeStage($status);
    }

    private function getCustomerHcSelect(): string
    {
        if ($this->crmCustomersHasHcNumber()) {
            return 'c.hc_number AS customer_hc_number';
        }

        return 'l.hc_number AS customer_hc_number';
    }

    private function crmCustomersHasHcNumber(): bool
    {
        if ($this->crmCustomerHasHcNumber !== null) {
            return $this->crmCustomerHasHcNumber;
        }

        $this->crmCustomerHasHcNumber = $this->schemaInspector->tableHasColumn('crm_customers', 'hc_number');

        return $this->crmCustomerHasHcNumber;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitNameParts(string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $trimmed, 2) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';

        return [trim($first), trim($last)];
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logSqlException(PDOException $e, ?string $sql = null, array $params = []): void
    {
        $dbName = null;
        try {
            $dbName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (\Throwable $t) {
            // ignore
        }

        error_log('SQL ERROR: ' . $e->getMessage());
        error_log('SQL DB: ' . ($dbName ?: 'unknown'));
        error_log('SQL QUERY: ' . ($sql ?? 'no-sql-var'));
        error_log('SQL PARAMS: ' . json_encode($params ?? []));

        // Extra debug: verify the column exists in the actual connected DB at failure time.
        try {
            $cols = $this->pdo->query('SHOW COLUMNS FROM crm_leads')->fetchAll(PDO::FETCH_COLUMN);
            error_log('crm_leads columns: ' . implode(',', $cols));
        } catch (\Throwable $t) {
            // ignore
        }
    }
}
