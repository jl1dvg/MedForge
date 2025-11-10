<?php

namespace Modules\Shared\Services;

use PDO;
use PDOException;

final class PatientContextService
{
    private PDO $pdo;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{
     *     hc_number: string,
     *     clinic: array{patient: ?array<string, mixed>},
     *     crm: array{
     *         customers: array<int, array<string, mixed>>,
     *         primary_customer: ?array<string, mixed>,
     *         leads: array<int, array<string, mixed>>,
     *         primary_lead: ?array<string, mixed>
     *     },
     *     communications: array{
     *         conversations: array<int, array<string, mixed>>,
     *         primary_conversation: ?array<string, mixed>
     *     }
     * }
     */
    public function getContext(string $hcNumber): array
    {
        $hcNumber = trim($hcNumber);

        if ($hcNumber === '') {
            return [
                'hc_number' => $hcNumber,
                'clinic' => ['patient' => null],
                'crm' => [
                    'customers' => [],
                    'primary_customer' => null,
                    'leads' => [],
                    'primary_lead' => null,
                ],
                'communications' => [
                    'conversations' => [],
                    'primary_conversation' => null,
                ],
            ];
        }

        if (isset($this->cache[$hcNumber])) {
            return $this->cache[$hcNumber];
        }

        $patient = $this->fetchClinicPatient($hcNumber);
        $identifiers = $this->buildPatientIdentifiers($hcNumber, $patient);

        $crm = $this->fetchCrmData($identifiers);
        $communications = $this->fetchCommunications($identifiers);

        return $this->cache[$hcNumber] = [
            'hc_number' => $hcNumber,
            'clinic' => [
                'patient' => $patient,
            ],
            'crm' => $crm,
            'communications' => $communications,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchClinicPatient(string $hcNumber): ?array
    {
        $row = $this->safeQuery(function () use ($hcNumber) {
            $sql = <<<'SQL'
                SELECT
                    hc_number,
                    fname,
                    mname,
                    lname,
                    lname2,
                    afiliacion,
                    celular,
                    telefono,
                    email,
                    cedula,
                    ciudad,
                    fecha_nacimiento
                FROM patient_data
                WHERE hc_number = :hc
                LIMIT 1
            SQL;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':hc' => $hcNumber]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row === false ? null : $row;
        });

        if (!is_array($row)) {
            return null;
        }

        $row['full_name'] = $this->buildFullName([
            'fname' => $row['fname'] ?? null,
            'mname' => $row['mname'] ?? null,
            'lname' => $row['lname'] ?? null,
            'lname2' => $row['lname2'] ?? null,
        ]);

        return $row;
    }

    /**
     * @param array<string, mixed>|null $patient
     *
     * @return array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * }
     */
    private function buildPatientIdentifiers(string $hcNumber, ?array $patient): array
    {
        $phones = [];
        $emails = [];
        $documents = [];

        if ($patient !== null) {
            foreach (['celular', 'telefono'] as $field) {
                $phone = $this->normalizePhone($patient[$field] ?? null);
                if ($phone !== null) {
                    $phones[] = $phone;
                }
            }

            $email = $this->sanitizeString($patient['email'] ?? null);
            if ($email !== null) {
                $emails[] = $email;
            }

            $document = $this->sanitizeString($patient['cedula'] ?? null);
            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return [
            'hc_number' => $hcNumber,
            'phones' => array_values(array_unique(array_filter($phones))),
            'emails' => array_values(array_unique(array_filter($emails))),
            'documents' => array_values(array_unique(array_filter($documents))),
        ];
    }

    /**
     * @param array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * } $identifiers
     *
     * @return array{
     *     customers: array<int, array<string, mixed>>,
     *     primary_customer: ?array<string, mixed>,
     *     leads: array<int, array<string, mixed>>,
     *     primary_lead: ?array<string, mixed>
     * }
     */
    private function fetchCrmData(array $identifiers): array
    {
        $customers = $this->fetchCrmCustomers($identifiers);
        $leads = $this->fetchCrmLeads($identifiers, $customers);

        return [
            'customers' => $customers,
            'primary_customer' => $customers[0] ?? null,
            'leads' => $leads,
            'primary_lead' => $leads[0] ?? null,
        ];
    }

    /**
     * @param array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * } $identifiers
     * @param array<int, array<string, mixed>> $customers
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCrmLeads(array $identifiers, array $customers): array
    {
        $customerIds = array_values(array_unique(array_filter(array_map(
            static fn(array $customer): ?int => isset($customer['id']) ? (int) $customer['id'] : null,
            $customers
        ))));

        return $this->safeQuery(function () use ($identifiers, $customerIds) {
            $conditions = [];
            $params = [];

            if (!empty($customerIds)) {
                $placeholders = [];
                foreach ($customerIds as $index => $id) {
                    $key = ':customer_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $id;
                }

                $conditions[] = 'customer_id IN (' . implode(', ', $placeholders) . ')';
            }

            foreach ($identifiers['emails'] as $index => $email) {
                $key = ':lead_email_' . $index;
                $conditions[] = 'email = ' . $key;
                $params[$key] = $email;
            }

            foreach ($identifiers['phones'] as $index => $phone) {
                $key = ':lead_phone_' . $index;
                $conditions[] = 'phone = ' . $key;
                $params[$key] = $phone;
            }

            if (empty($conditions)) {
                return [];
            }

            $sql = 'SELECT id, name, email, phone, status, source, customer_id, updated_at '
                . 'FROM crm_leads '
                . 'WHERE ' . implode(' OR ', array_map(static fn(string $fragment): string => '(' . $fragment . ')', $conditions))
                . ' ORDER BY updated_at DESC LIMIT 10';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($rows)) {
                return [];
            }

            foreach ($rows as &$row) {
                $row['score'] = $this->scoreLeadMatch($row, $identifiers, $customerIds);
            }

            unset($row);

            usort($rows, static function (array $a, array $b): int {
                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0)
                    ?: strtotime((string) ($b['updated_at'] ?? '')) <=> strtotime((string) ($a['updated_at'] ?? ''))
                    ?: ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            });

            return array_map(static function (array $row): array {
                unset($row['score']);

                return $row;
            }, $rows);
        }, []);
    }

    /**
     * @param array<string, mixed> $lead
     * @param array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * } $identifiers
     * @param int[] $customerIds
     */
    private function scoreLeadMatch(array $lead, array $identifiers, array $customerIds): int
    {
        $score = 0;

        if (!empty($lead['customer_id']) && in_array((int) $lead['customer_id'], $customerIds, true)) {
            $score += 60;
        }

        $leadEmail = $this->sanitizeString($lead['email'] ?? null);
        if ($leadEmail !== null && in_array($leadEmail, $identifiers['emails'], true)) {
            $score += 25;
        }

        $leadPhone = $this->normalizePhone($lead['phone'] ?? null);
        if ($leadPhone !== null && in_array($leadPhone, $identifiers['phones'], true)) {
            $score += 25;
        }

        return $score;
    }

    /**
     * @param array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * } $identifiers
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCrmCustomers(array $identifiers): array
    {
        return $this->safeQuery(function () use ($identifiers) {
            $conditions = [];
            $params = [];

            $externalRef = 'patient:' . $identifiers['hc_number'];
            $conditions[] = 'external_ref = :external_ref';
            $params[':external_ref'] = $externalRef;

            foreach ($identifiers['documents'] as $index => $document) {
                $key = ':document_' . $index;
                $conditions[] = 'document = ' . $key;
                $params[$key] = $document;
            }

            foreach ($identifiers['phones'] as $index => $phone) {
                $key = ':phone_' . $index;
                $conditions[] = 'phone = ' . $key;
                $params[$key] = $phone;
            }

            foreach ($identifiers['emails'] as $index => $email) {
                $key = ':email_' . $index;
                $conditions[] = 'email = ' . $key;
                $params[$key] = $email;
            }

            $conditions = array_unique($conditions);

            $sql = 'SELECT id, name, email, phone, document, affiliation, source, external_ref, updated_at '
                . 'FROM crm_customers';

            if (!empty($conditions)) {
                $sql .= ' WHERE ' . implode(' OR ', array_map(static fn(string $fragment): string => '(' . $fragment . ')', $conditions));
            }

            $sql .= ' ORDER BY updated_at DESC LIMIT 10';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($rows)) {
                return [];
            }

            foreach ($rows as &$row) {
                $row['score'] = $this->scoreCustomerMatch($row, $identifiers, $externalRef);
            }

            unset($row);

            usort($rows, static function (array $a, array $b): int {
                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0)
                    ?: strtotime((string) ($b['updated_at'] ?? '')) <=> strtotime((string) ($a['updated_at'] ?? ''))
                    ?: ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            });

            return array_map(function (array $row): array {
                $row['normalized_phone'] = $this->normalizePhone($row['phone'] ?? null);
                unset($row['score']);

                return $row;
            }, $rows);
        }, []);
    }

    /**
     * @param array<string, mixed> $customer
     * @param array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * } $identifiers
     */
    private function scoreCustomerMatch(array $customer, array $identifiers, string $externalRef): int
    {
        $score = 0;

        if ($this->sanitizeString($customer['external_ref'] ?? null) === $externalRef) {
            $score += 80;
        }

        $document = $this->sanitizeString($customer['document'] ?? null);
        if ($document !== null && in_array($document, $identifiers['documents'], true)) {
            $score += 40;
        }

        $phone = $this->normalizePhone($customer['phone'] ?? null);
        if ($phone !== null && in_array($phone, $identifiers['phones'], true)) {
            $score += 30;
        }

        $email = $this->sanitizeString($customer['email'] ?? null);
        if ($email !== null && in_array($email, $identifiers['emails'], true)) {
            $score += 20;
        }

        return $score;
    }

    /**
     * @param array{
     *     hc_number: string,
     *     phones: string[],
     *     emails: string[],
     *     documents: string[]
     * } $identifiers
     *
     * @return array{
     *     conversations: array<int, array<string, mixed>>,
     *     primary_conversation: ?array<string, mixed>
     * }
     */
    private function fetchCommunications(array $identifiers): array
    {
        $conversations = $this->safeQuery(function () use ($identifiers) {
            $conditions = [];
            $params = [];

            $conditions[] = 'patient_hc_number = :hc';
            $params[':hc'] = $identifiers['hc_number'];

            foreach ($identifiers['phones'] as $index => $phone) {
                $key = ':wa_' . $index;
                $conditions[] = 'wa_number = ' . $key;
                $params[$key] = $phone;
            }

            $sql = 'SELECT id, wa_number, display_name, patient_hc_number, patient_full_name, last_message_at,'
                . ' last_message_direction, last_message_type, last_message_preview, unread_count, created_at, updated_at '
                . 'FROM whatsapp_conversations';

            if (!empty($conditions)) {
                $sql .= ' WHERE ' . implode(' OR ', array_map(static fn(string $fragment): string => '(' . $fragment . ')', $conditions));
            }

            $sql .= ' ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC, id DESC LIMIT 5';

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($rows)) {
                return [];
            }

            foreach ($rows as &$row) {
                $row['messages'] = $this->fetchRecentMessages((int) ($row['id'] ?? 0));
            }

            unset($row);

            return $rows;
        }, []);

        return [
            'conversations' => $conversations,
            'primary_conversation' => $conversations[0] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRecentMessages(int $conversationId): array
    {
        if ($conversationId <= 0) {
            return [];
        }

        return $this->safeQuery(function () use ($conversationId) {
            $sql = 'SELECT id, direction, message_type, body, status, message_timestamp, created_at '
                . 'FROM whatsapp_messages '
                . 'WHERE conversation_id = :id '
                . 'ORDER BY COALESCE(message_timestamp, created_at) DESC, id DESC '
                . 'LIMIT 10';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $conversationId]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        }, []);
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     * @param T|null $fallback
     *
     * @return T|null
     */
    private function safeQuery(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (PDOException $exception) {
            if ($this->isMissingRelationError($exception)) {
                return $fallback;
            }

            throw $exception;
        }
    }

    private function isMissingRelationError(PDOException $exception): bool
    {
        $sqlState = $exception->getCode();
        if ($sqlState === '42S02' || $sqlState === '42S22') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'no such table')
            || str_contains($message, 'no such column')
            || str_contains($message, 'doesn\'t exist');
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === null) {
            return null;
        }

        $digits = ltrim($digits, '+');

        if ($digits === '') {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * @param array{fname?: mixed, mname?: mixed, lname?: mixed, lname2?: mixed} $parts
     */
    private function buildFullName(array $parts): string
    {
        $pieces = array_filter([
            $this->sanitizeString($parts['fname'] ?? null),
            $this->sanitizeString($parts['mname'] ?? null),
            $this->sanitizeString($parts['lname'] ?? null),
            $this->sanitizeString($parts['lname2'] ?? null),
        ]);

        return trim(implode(' ', $pieces));
    }
}
