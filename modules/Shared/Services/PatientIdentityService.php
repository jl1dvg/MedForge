<?php

declare(strict_types=1);

namespace Modules\Shared\Services;

use InvalidArgumentException;
use PDO;

/**
 * Coordina la sincronización de identidades de pacientes/clients usando hc_number.
 */
class PatientIdentityService
{
    private PDO $pdo;
    private ?bool $crmCustomerHasHcNumber = null;
    private SchemaInspector $schemaInspector;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->schemaInspector = new SchemaInspector($pdo);
    }

    public function normalizeHcNumber(string $hcNumber): string
    {
        return strtoupper(trim($hcNumber));
    }

    /**
     * Garantiza que existan y estén sincronizados los registros de paciente y cliente CRM.
     *
     * @param array<string, mixed> $context
     *
     * @return array{hc_number: string, customer_id: int|null, patient: array<string, mixed>|null}
     */
    public function ensureIdentity(string $hcNumber, array $context = []): array
    {
        $normalized = $this->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            throw new InvalidArgumentException('El número de historia clínica es obligatorio.');
        }

        $customerId = $this->syncCustomer($normalized, $context['customer'] ?? $context);
        $patient = $this->syncPatient($normalized, $context['patient'] ?? $context);

        return [
            'hc_number' => $normalized,
            'customer_id' => $customerId,
            'patient' => $patient,
        ];
    }

    /**
     * Crea o actualiza un cliente CRM basado en hc_number.
     *
     * @param array<string, mixed> $data
     */
    public function syncCustomer(string $hcNumber, array $data = []): ?int
    {
        $normalized = $this->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            throw new InvalidArgumentException('El número de historia clínica es obligatorio.');
        }

        if (!$this->crmCustomersHasHcNumber()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM crm_customers WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $normalized]);
        $existingId = $stmt->fetchColumn();

        $fields = $this->extractCustomerFields($data);
        if ($existingId) {
            if ($fields) {
                $this->updateCustomer((int) $existingId, $fields);
            }

            return (int) $existingId;
        }

        if (empty($fields)) {
            return null;
        }

        return $this->createCustomer($normalized, $fields);
    }

    /**
     * Crea o actualiza un registro clínico asociado al hc_number.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public function syncPatient(string $hcNumber, array $data = []): ?array
    {
        $normalized = $this->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            throw new InvalidArgumentException('El número de historia clínica es obligatorio.');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM patient_data WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $normalized]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $fields = $this->extractPatientFields($data);
        if ($existing) {
            if ($fields) {
                $sets = [];
                $params = [':hc' => $normalized];
                foreach ($fields as $column => $value) {
                    $sets[] = sprintf('%s = :%s', $column, $column);
                    $params[':' . $column] = $value;
                }

                if ($sets) {
                    $sql = 'UPDATE patient_data SET ' . implode(', ', $sets) . ' WHERE hc_number = :hc';
                    $update = $this->pdo->prepare($sql);
                    $update->execute($params);
                }
            }

            return $this->findPatient($normalized);
        }

        if (empty($fields)) {
            return null;
        }

        $columns = ['hc_number'];
        $placeholders = [':hc_number'];
        $params = [':hc_number' => $normalized];

        // Garantizar columnas básicas para evitar restricciones NOT NULL
        $fields['fname'] = $fields['fname'] ?? '';
        $fields['lname'] = $fields['lname'] ?? 'SIN APELLIDO';
        $fields['mname'] = $fields['mname'] ?? '';
        $fields['lname2'] = $fields['lname2'] ?? '';

        foreach ($fields as $column => $value) {
            $columns[] = $column;
            $placeholders[] = ':' . $column;
            $params[':' . $column] = $value;
        }

        $sql = sprintf(
            'INSERT INTO patient_data (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $insert = $this->pdo->prepare($sql);
        $insert->execute($params);

        return $this->findPatient($normalized);
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
     * Asegura o actualiza un lead basado en hc_number.
     *
     * @param array<string, mixed> $data
     */
    public function syncLead(string $hcNumber, array $data = [], bool $createIfMissing = false): ?array
    {
        $normalized = $this->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            throw new InvalidArgumentException('El número de historia clínica es obligatorio.');
        }

        $stmt = $this->pdo->prepare('SELECT id FROM crm_leads WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $normalized]);
        $existingId = $stmt->fetchColumn();

        $fields = $this->extractLeadFields($data);
        if ($existingId) {
            if ($fields) {
                $sets = [];
                $params = [':hc' => $normalized];
                foreach ($fields as $column => $value) {
                    $sets[] = sprintf('%s = :%s', $column, $column);
                    $params[':' . $column] = $value;
                }

                $sql = 'UPDATE crm_leads SET ' . implode(', ', $sets) . ' WHERE hc_number = :hc';
                $update = $this->pdo->prepare($sql);
                $update->execute($params);
            }

            return $this->fetchLeadByHcNumber($normalized);
        }

        if (!$createIfMissing || empty($fields)) {
            return null;
        }

        $defaults = $fields + [
            'name' => 'Paciente ' . $normalized,
            'status' => 'nuevo',
            'source' => 'clinical',
        ];

        $sql = <<<'SQL'
            INSERT INTO crm_leads
                (hc_number, name, email, phone, status, source, notes, assigned_to, customer_id, created_by)
            VALUES
                (:hc_number, :name, :email, :phone, :status, :source, :notes, :assigned_to, :customer_id, :created_by)
        SQL;

        $insert = $this->pdo->prepare($sql);
        $insert->execute([
            ':hc_number' => $normalized,
            ':name' => $defaults['name'] ?? 'Paciente ' . $normalized,
            ':email' => $defaults['email'] ?? null,
            ':phone' => $defaults['phone'] ?? null,
            ':status' => $defaults['status'] ?? 'nuevo',
            ':source' => $defaults['source'] ?? 'clinical',
            ':notes' => $defaults['notes'] ?? null,
            ':assigned_to' => $defaults['assigned_to'] ?? null,
            ':customer_id' => $defaults['customer_id'] ?? null,
            ':created_by' => $defaults['created_by'] ?? null,
        ]);

        return $this->fetchLeadByHcNumber($normalized);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPatient(string $hcNumber): ?array
    {
        $normalized = $this->normalizeHcNumber($hcNumber);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM patient_data WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $normalized]);

        $patient = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $patient ?: null;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function extractCustomerFields(array $fields): array
    {
        $map = [
            'type',
            'name',
            'email',
            'phone',
            'document',
            'gender',
            'birthdate',
            'city',
            'address',
            'marital_status',
            'affiliation',
            'nationality',
            'workplace',
            'source',
            'external_ref',
        ];

        $filtered = [];
        foreach ($map as $column) {
            if (array_key_exists($column, $fields)) {
                $filtered[$column] = $this->prepareNullable($fields[$column]);
            }
        }

        if (empty($filtered['name']) && !empty($fields['full_name'])) {
            $filtered['name'] = (string) $fields['full_name'];
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function extractPatientFields(array $fields): array
    {
        $map = [
            'fname',
            'mname',
            'lname',
            'lname2',
            'afiliacion',
            'fecha_nacimiento',
            'sexo',
            'celular',
            'ciudad',
        ];

        $filtered = [];
        foreach ($map as $column) {
            if (array_key_exists($column, $fields)) {
                $filtered[$column] = $this->prepareNullable($fields[$column]);
            }
        }

        if (empty($filtered['fname']) && !empty($fields['name'])) {
            $parts = $this->splitName((string) $fields['name']);
            $filtered['fname'] = $parts['fname'];
            $filtered['mname'] = $parts['mname'];
            $filtered['lname'] = $parts['lname'];
            $filtered['lname2'] = $parts['lname2'];
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function extractLeadFields(array $fields): array
    {
        $map = [
            'name',
            'email',
            'phone',
            'status',
            'source',
            'notes',
            'assigned_to',
            'customer_id',
            'created_by',
        ];

        $filtered = [];
        foreach ($map as $column) {
            if (array_key_exists($column, $fields)) {
                $filtered[$column] = $this->prepareNullable($fields[$column]);
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateCustomer(int $id, array $fields): void
    {
        $sets = [];
        $params = [':id' => $id];

        foreach ($fields as $column => $value) {
            $sets[] = sprintf('%s = :%s', $column, $column);
            $params[':' . $column] = $value;
        }

        if (!$sets) {
            return;
        }

        $sql = 'UPDATE crm_customers SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createCustomer(string $hcNumber, array $fields): int
    {
        $sql = <<<'SQL'
            INSERT INTO crm_customers
                (hc_number, type, name, email, phone, document, gender, birthdate, city, address, marital_status, affiliation, nationality, workplace, source, external_ref)
            VALUES
                (:hc_number, :type, :name, :email, :phone, :document, :gender, :birthdate, :city, :address, :marital_status, :affiliation, :nationality, :workplace, :source, :external_ref)
        SQL;

        $defaults = $fields + [
            'type' => 'person',
            'name' => $fields['name'] ?? 'Paciente ' . $hcNumber,
        ];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':hc_number' => $hcNumber,
            ':type' => $defaults['type'] ?? 'person',
            ':name' => $defaults['name'] ?? 'Paciente ' . $hcNumber,
            ':email' => $defaults['email'] ?? null,
            ':phone' => $defaults['phone'] ?? null,
            ':document' => $defaults['document'] ?? null,
            ':gender' => $defaults['gender'] ?? null,
            ':birthdate' => $defaults['birthdate'] ?? null,
            ':city' => $defaults['city'] ?? null,
            ':address' => $defaults['address'] ?? null,
            ':marital_status' => $defaults['marital_status'] ?? null,
            ':affiliation' => $defaults['affiliation'] ?? null,
            ':nationality' => $defaults['nationality'] ?? null,
            ':workplace' => $defaults['workplace'] ?? null,
            ':source' => $defaults['source'] ?? 'unknown',
            ':external_ref' => $defaults['external_ref'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function fetchLeadByHcNumber(string $hcNumber): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM crm_leads WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $hcNumber]);

        $lead = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $lead ?: null;
    }

    private function prepareNullable(mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @return array{fname: string, mname: string, lname: string, lname2: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) === 0) {
            return [
                'fname' => '',
                'mname' => '',
                'lname' => 'SIN APELLIDO',
                'lname2' => '',
            ];
        }

        $first = array_shift($parts);
        $last = count($parts) ? array_pop($parts) : 'SIN APELLIDO';
        $second = count($parts) ? array_shift($parts) : '';
        $extra = count($parts) ? implode(' ', $parts) : '';

        return [
            'fname' => $first ?? '',
            'mname' => $second ?? '',
            'lname' => $last ?? 'SIN APELLIDO',
            'lname2' => $extra,
        ];
    }
}
