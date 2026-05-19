<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services\Traits;

use DateTime;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * DB utility methods shared across Solicitudes write services.
 * Requires the consuming class to have `private readonly PDO $db`.
 */
trait SolicitudesDbHelperTrait
{
    /** @var array<string, array<int, string>> */
    private array $columnsCache = [];

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    private ?int $companyIdCache = null;

    // -------------------------------------------------------------------------
    // Table/column introspection
    // -------------------------------------------------------------------------

    private function tableExists(string $table): bool
    {
        // Todas las tablas del módulo tienen migración confirmada.
        // Esta verificación dinámica fue scaffolding de la migración incremental;
        // ya no es necesaria. Los guards en los call sites son dead-code candidatos
        // para limpieza en Fase E del plan de migración.
        return true;
    }

    /** @return array<int, string> */
    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->columnsCache)) {
            return $this->columnsCache[$table];
        }

        if (!$this->tableExists($table)) {
            $this->columnsCache[$table] = [];
            return [];
        }

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            $rows = [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = trim((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        $this->columnsCache[$table] = $columns;

        return $columns;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    // -------------------------------------------------------------------------
    // Generic DML helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $payload */
    private function insertRow(string $table, array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $columns  = [];
        $holders  = [];
        $bindings = [];

        foreach ($payload as $column => $value) {
            $key        = ':' . $column;
            $columns[]  = '`' . $column . '`';
            $holders[]  = $key;
            $bindings[$key] = $value;
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '', $table),
            implode(', ', $columns),
            implode(', ', $holders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $bindings
     */
    private function updateRow(string $table, array $payload, string $where, array $bindings = []): int
    {
        if ($payload === []) {
            return 0;
        }

        $sets   = [];
        $params = $bindings;

        foreach ($payload as $column => $value) {
            $key      = ':set_' . $column;
            $sets[]   = '`' . $column . '` = ' . $key;
            $params[$key] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            str_replace('`', '', $table),
            implode(', ', $sets),
            $where
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Value normalizers
    // -------------------------------------------------------------------------

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $formats = ['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 16 ? $value . ':00' : $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
            $format = strlen($value) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Task metadata helpers (shared between Kanban and CRM services)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function decodeTaskMetadata(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractChecklistSlugFromTaskRow(array $row): string
    {
        $slug = $this->normalizeKanbanSlug((string) ($row['checklist_slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        $metadata = $this->decodeTaskMetadata($row['metadata'] ?? null);
        if (is_array($metadata)) {
            return $this->normalizeKanbanSlug((string) ($metadata['checklist_slug'] ?? ''));
        }

        return '';
    }

    private function mergeChecklistTaskMetadata(mixed $currentValue, string $slug, string $title): ?string
    {
        $metadata = $this->decodeTaskMetadata($currentValue) ?? [];
        $metadata['task_key']         = trim((string) ($metadata['task_key'] ?? '')) !== '' ? $metadata['task_key'] : 'checklist:' . $slug;
        $metadata['checklist_slug']   = $slug;
        $metadata['checklist_label']  = trim((string) ($metadata['checklist_label'] ?? '')) !== '' ? $metadata['checklist_label'] : $title;

        return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // -------------------------------------------------------------------------
    // Row fetchers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function fetchSolicitudById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM solicitud_procedimiento WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function fetchCrmDetalleRow(int $solicitudId): ?array
    {
        if (!$this->tableExists('solicitud_crm_detalles')) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM solicitud_crm_detalles WHERE solicitud_id = :solicitud_id LIMIT 1');
        $stmt->execute([':solicitud_id' => $solicitudId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function legacyStateBySolicitud(int $solicitudId): string
    {
        $stmt = $this->db->prepare('SELECT estado FROM solicitud_procedimiento WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $solicitudId]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : '';
    }

    private function assertSolicitudExists(int $solicitudId): void
    {
        if ($this->fetchSolicitudById($solicitudId) === null) {
            throw new RuntimeException('Solicitud no encontrada');
        }
    }

    private function resolveCompanyId(): int
    {
        if ($this->companyIdCache !== null) {
            return $this->companyIdCache;
        }

        try {
            $stmt  = $this->db->query('SELECT company_id FROM crm_tasks WHERE company_id IS NOT NULL LIMIT 1');
            $value = $stmt ? (int) $stmt->fetchColumn() : 0;
            if ($value > 0) {
                $this->companyIdCache = $value;
                return $value;
            }
        } catch (Throwable) {
            // ignore
        }

        $this->companyIdCache = 1;

        return 1;
    }

    // -------------------------------------------------------------------------
    // Note helper (used by Kanban reminders and CRM write operations)
    // -------------------------------------------------------------------------

    private function insertNota(int $solicitudId, ?int $autorId, string $nota): void
    {
        $columns = $this->tableColumns('solicitud_crm_notas');
        if ($columns === []) {
            return;
        }

        $payload = ['solicitud_id' => $solicitudId, 'nota' => $nota];
        if (in_array('autor_id', $columns, true)) {
            $payload['autor_id'] = $autorId;
        }
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = date('Y-m-d H:i:s');
        }

        $this->insertRow('solicitud_crm_notas', $payload);
    }

    // -------------------------------------------------------------------------
    // Task priority normalizer (used by Kanban and CRM services)
    // -------------------------------------------------------------------------

    private function normalizeTaskPriority(mixed $priority): string
    {
        $raw = trim((string) ($priority ?? ''));
        if ($raw === '') {
            return 'media';
        }

        $key = str_replace([' ', '-'], '_', mb_strtolower($raw, 'UTF-8'));

        return match ($key) {
            'low', 'baja'                                          => 'baja',
            'high', 'alta'                                         => 'alta',
            'urgent', 'urgente', 'critical', 'critica', 'crítica'  => 'urgente',
            default                                                => 'media',
        };
    }

    // -------------------------------------------------------------------------
    // Flexible datetime parser (used by CRM bloqueo and derivacion)
    // -------------------------------------------------------------------------

    private function parseFlexibleDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        $str = trim((string) ($value ?? ''));
        if ($str === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($str);
        } catch (Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Slug/label normalizers — require $this->stateMachine (SolicitudesStateMachineService)
    // -------------------------------------------------------------------------

    private function normalizeKanbanSlug(string $value): string
    {
        return $this->stateMachine->normalizeKanbanSlug($value);
    }
}
