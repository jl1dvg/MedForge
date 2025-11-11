<?php

namespace Modules\Search\Services;

use PDO;
use Throwable;
use Modules\Shared\Services\SchemaInspector;

class GlobalSearchService
{
    private PDO $pdo;
    private int $limit;
    private SchemaInspector $schemaInspector;
    private ?bool $crmCustomerHasHcNumber = null;

    public function __construct(PDO $pdo, int $limit = 5)
    {
        $this->pdo = $pdo;
        $this->limit = max(1, $limit);
        $this->schemaInspector = new SchemaInspector($pdo);
    }

    public function search(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $sections = [];

        $this->appendSection($sections, 'customers', 'Clientes', fn () => $this->searchCustomers($term));
        $this->appendSection($sections, 'leads', 'Leads', fn () => $this->searchLeads($term));
        $this->appendSection($sections, 'projects', 'Proyectos', fn () => $this->searchProjects($term));
        $this->appendSection($sections, 'tasks', 'Tareas', fn () => $this->searchTasks($term));
        $this->appendSection($sections, 'tickets', 'Tickets', fn () => $this->searchTickets($term));
        $this->appendSection($sections, 'patients', 'Pacientes', fn () => $this->searchPatients($term));
        $this->appendSection($sections, 'users', 'Usuarios', fn () => $this->searchUsers($term));

        return $sections;
    }

    private function appendSection(array &$sections, string $type, string $label, callable $callback): void
    {
        $items = $this->safeExecute($callback);

        if (!empty($items)) {
            $sections[] = [
                'type' => $type,
                'label' => $label,
                'items' => $items,
            ];
        }
    }

    private function safeExecute(callable $callback): array
    {
        try {
            $result = $callback();

            return is_array($result) ? $result : [];
        } catch (Throwable $exception) {
            error_log('GlobalSearchService error: ' . $exception->getMessage());

            return [];
        }
    }

    private function searchCustomers(string $term): array
    {
        $hasHcNumber = $this->crmCustomersHasHcNumber();

        $columns = ['id', 'name', 'email', 'phone', 'affiliation', 'document', 'source'];
        if ($hasHcNumber) {
            $columns[] = 'hc_number';
        }

        $conditions = [
            'name LIKE :name',
            'email LIKE :email',
            'phone LIKE :phone',
            'document LIKE :document',
        ];

        if ($hasHcNumber) {
            $conditions[] = 'hc_number LIKE :hc';
        }

        $sql = sprintf(
            'SELECT %s FROM crm_customers WHERE %s ORDER BY name ASC LIMIT :limit',
            implode(', ', $columns),
            implode(' OR ', $conditions)
        );

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':name', $like);
        $stmt->bindValue(':email', $like);
        $stmt->bindValue(':phone', $like);
        $stmt->bindValue(':document', $like);
        if ($hasHcNumber) {
            $stmt->bindValue(':hc', $like);
        }
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $title = $this->string($row['name'] ?? '');

            if ($id === null || $title === '') {
                continue;
            }

            $items[] = [
                'id' => $id,
                'title' => $title,
                'subtitle' => $this->joinParts([$row['email'] ?? null, $row['phone'] ?? null]),
                'url' => '/crm?tab=customers&customer_id=' . $id,
                'badge' => $this->string($row['affiliation'] ?? ''),
                'meta' => $this->buildMeta([
                    $hasHcNumber && !empty($row['hc_number']) ? 'HC: ' . $row['hc_number'] : null,
                    $row['document'] ? 'Documento: ' . $row['document'] : null,
                    $row['source'] ? 'Origen: ' . $row['source'] : null,
                ]),
                'icon' => 'fa-regular fa-address-card',
            ];
        }

        return $items;
    }

    private function searchLeads(string $term): array
    {
        $sql = <<<SQL
            SELECT id, hc_number, name, email, phone, status, source
            FROM crm_leads
            WHERE name LIKE :name
               OR email LIKE :email
               OR phone LIKE :phone
               OR source LIKE :source
               OR hc_number LIKE :hc
               OR CAST(id AS CHAR) LIKE :id_like
            ORDER BY updated_at DESC
            LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':name', $like);
        $stmt->bindValue(':email', $like);
        $stmt->bindValue(':phone', $like);
        $stmt->bindValue(':source', $like);
        $stmt->bindValue(':hc', $like);
        $stmt->bindValue(':id_like', $like);
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $title = $this->string($row['name'] ?? '');
            $leadHc = $this->string($row['hc_number'] ?? '');

            if ($id === null || $title === '') {
                continue;
            }

            $items[] = [
                'id' => $id,
                'title' => $title,
                'subtitle' => $this->joinParts([$row['email'] ?? null, $row['phone'] ?? null]),
                'url' => '/crm?tab=leads&lead_id=' . $id,
                'badge' => $this->humanize($row['status'] ?? ''),
                'meta' => $this->buildMeta([
                    $leadHc !== '' ? 'HC: ' . $leadHc : null,
                    $row['source'] ? 'Origen: ' . $row['source'] : null,
                ]),
                'icon' => 'fa-regular fa-user',
            ];
        }

        return $items;
    }

    private function crmCustomersHasHcNumber(): bool
    {
        if ($this->crmCustomerHasHcNumber !== null) {
            return $this->crmCustomerHasHcNumber;
        }

        $this->crmCustomerHasHcNumber = $this->schemaInspector->tableHasColumn('crm_customers', 'hc_number');

        return $this->crmCustomerHasHcNumber;
    }

    private function searchProjects(string $term): array
    {
        $sql = <<<SQL
            SELECT id, title, status, customer_id, lead_id, due_date
            FROM crm_projects
            WHERE title LIKE :title
               OR description LIKE :description
            ORDER BY updated_at DESC
            LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':title', $like);
        $stmt->bindValue(':description', $like);
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $title = $this->string($row['title'] ?? '');

            if ($id === null || $title === '') {
                continue;
            }

            $dueDate = $this->formatDate($row['due_date'] ?? null);

            $customerId = $this->id($row['customer_id'] ?? null);
            $leadId     = $this->id($row['lead_id'] ?? null);

            $items[] = [
                'id' => $id,
                'title' => $title,
                'subtitle' => $dueDate ? 'Vence: ' . $dueDate : '',
                'url' => '/crm?tab=projects&project_id=' . $id,
                'badge' => $this->humanize($row['status'] ?? ''),
                'meta' => $this->buildMeta([
                    $customerId ? 'Cliente #' . $customerId : null,
                    $leadId ? 'Lead #' . $leadId : null,
                ]),
                'icon' => 'fa-solid fa-diagram-project',
            ];
        }

        return $items;
    }

    private function searchTasks(string $term): array
    {
        $sql = <<<SQL
            SELECT id, title, status, project_id, due_date
            FROM crm_tasks
            WHERE title LIKE :title
               OR description LIKE :description
            ORDER BY updated_at DESC
            LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':title', $like);
        $stmt->bindValue(':description', $like);
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $title = $this->string($row['title'] ?? '');

            if ($id === null || $title === '') {
                continue;
            }

            $dueDate = $this->formatDate($row['due_date'] ?? null);

            $projectId = $this->id($row['project_id'] ?? null);

            $items[] = [
                'id' => $id,
                'title' => $title,
                'subtitle' => $dueDate ? 'Vence: ' . $dueDate : '',
                'url' => '/crm?tab=tasks&task_id=' . $id,
                'badge' => $this->humanize($row['status'] ?? ''),
                'meta' => $this->buildMeta([
                    $projectId ? 'Proyecto #' . $projectId : null,
                ]),
                'icon' => 'fa-regular fa-square-check',
            ];
        }

        return $items;
    }

    private function searchTickets(string $term): array
    {
        $sql = <<<SQL
            SELECT id, subject, status, priority, related_lead_id, related_project_id
            FROM crm_tickets
            WHERE subject LIKE :subject
               OR priority LIKE :priority
               OR CAST(id AS CHAR) LIKE :id_like
            ORDER BY updated_at DESC
            LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':subject', $like);
        $stmt->bindValue(':priority', $like);
        $stmt->bindValue(':id_like', $like);
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $subject = $this->string($row['subject'] ?? '');

            if ($id === null || $subject === '') {
                continue;
            }

            $leadId    = $this->id($row['related_lead_id'] ?? null);
            $projectId = $this->id($row['related_project_id'] ?? null);

            $items[] = [
                'id' => $id,
                'title' => 'Ticket #' . $id,
                'subtitle' => $subject,
                'url' => '/crm?tab=tickets&ticket_id=' . $id,
                'badge' => $this->humanize($row['status'] ?? ''),
                'meta' => $this->buildMeta([
                    $row['priority'] ? 'Prioridad: ' . $this->humanize($row['priority']) : null,
                    $leadId ? 'Lead #' . $leadId : null,
                    $projectId ? 'Proyecto #' . $projectId : null,
                ]),
                'icon' => 'fa-solid fa-ticket',
            ];
        }

        return $items;
    }

    private function searchPatients(string $term): array
    {
        $sql = <<<SQL
            SELECT hc_number, fname, mname, lname, lname2, afiliacion, celular
            FROM patient_data
            WHERE hc_number LIKE :hc
               OR fname LIKE :fname
               OR mname LIKE :mname
               OR lname LIKE :lname
               OR lname2 LIKE :lname2
            ORDER BY fname ASC
            LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':hc', $like);
        $stmt->bindValue(':fname', $like);
        $stmt->bindValue(':mname', $like);
        $stmt->bindValue(':lname', $like);
        $stmt->bindValue(':lname2', $like);
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $hcNumber = $this->string($row['hc_number'] ?? '');
            if ($hcNumber === '') {
                continue;
            }

            $nameParts = array_filter([
                $this->string($row['fname'] ?? ''),
                $this->string($row['mname'] ?? ''),
                $this->string($row['lname'] ?? ''),
                $this->string($row['lname2'] ?? ''),
            ], fn ($value) => $value !== '');

            $fullName = trim(implode(' ', $nameParts));

            if ($fullName === '') {
                $fullName = 'Historia clínica ' . $hcNumber;
            }

            $items[] = [
                'id' => $hcNumber,
                'title' => $fullName,
                'subtitle' => 'HC: ' . $hcNumber,
                'url' => '/pacientes/detalles?hc_number=' . rawurlencode($hcNumber),
                'badge' => $this->string($row['afiliacion'] ?? ''),
                'meta' => $this->buildMeta([
                    $row['celular'] ? 'Celular: ' . $row['celular'] : null,
                ]),
                'icon' => 'fa-solid fa-hospital-user',
            ];
        }

        return $items;
    }

    private function searchUsers(string $term): array
    {
        $sql = <<<SQL
            SELECT id, nombre, username, email, especialidad
            FROM users
            WHERE nombre LIKE :nombre
               OR username LIKE :username
               OR email LIKE :email
               OR cedula LIKE :cedula
            ORDER BY nombre ASC
            LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $like = $this->makeLike($term);
        $stmt->bindValue(':nombre', $like);
        $stmt->bindValue(':username', $like);
        $stmt->bindValue(':email', $like);
        $stmt->bindValue(':cedula', $like);
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = $this->id($row['id'] ?? null);
            $name = $this->string($row['nombre'] ?? '');
            $username = $this->string($row['username'] ?? '');

            if ($id === null || ($name === '' && $username === '')) {
                continue;
            }

            $title = $name !== '' ? $name : $username;

            $items[] = [
                'id' => $id,
                'title' => $title,
                'subtitle' => $this->joinParts([
                    $username !== '' ? '@' . $username : null,
                    $row['email'] ?? null,
                ]),
                'url' => '/usuarios/edit?id=' . $id,
                'badge' => null,
                'meta' => $this->buildMeta([
                    $row['especialidad'] ? 'Especialidad: ' . $row['especialidad'] : null,
                ]),
                'icon' => 'fa-regular fa-id-badge',
            ];
        }

        return $items;
    }

    private function makeLike(string $term): string
    {
        return '%' . $term . '%';
    }

    private function id($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function string($value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function joinParts(array $parts): string
    {
        $filtered = [];

        foreach ($parts as $part) {
            $value = $this->string($part);
            if ($value !== '') {
                $filtered[] = $value;
            }
        }

        return implode(' · ', $filtered);
    }

    private function buildMeta(array $lines): array
    {
        $meta = [];

        foreach ($lines as $line) {
            $value = $this->string($line);
            if ($value !== '') {
                $meta[] = $value;
            }
        }

        return $meta;
    }

    private function humanize(?string $value): string
    {
        $value = $this->string($value ?? '');

        if ($value === '') {
            return '';
        }

        $value = str_replace(['_', '-'], ' ', $value);

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords(strtolower($value));
    }

    private function formatDate($value): ?string
    {
        $value = $this->string($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('d/m/Y', $timestamp);
    }
}
