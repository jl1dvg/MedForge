<?php

namespace Models;

use PDO;
use PDOException;
use RuntimeException;

class SettingsModel
{
    private PDO $pdo;
    private string $table;
    private string $nameColumn = 'name';
    private string $valueColumn = 'value';
    private ?string $categoryColumn = null;
    private ?string $typeColumn = null;
    private ?string $autoloadColumn = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo   = $pdo;
        $this->table = $this->resolveTable();
        $this->resolveColumns();
    }

    public function all(): array
    {
        $sql = sprintf('SELECT * FROM %s ORDER BY %s ASC', $this->table, $this->nameColumn);
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOption(string $name): ?string
    {
        $sql = sprintf('SELECT %s FROM %s WHERE %s = ? LIMIT 1', $this->valueColumn, $this->table, $this->nameColumn);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function getOptions(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql          = sprintf(
            'SELECT %1$s, %2$s FROM %3$s WHERE %1$s IN (%4$s)',
            $this->nameColumn,
            $this->valueColumn,
            $this->table,
            $placeholders
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($keys));

        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $options[$row[$this->nameColumn]] = $row[$this->valueColumn];
        }

        return $options;
    }

    public function getByCategory(string $category): array
    {
        if ($this->categoryColumn === null) {
            return [];
        }

        $sql  = sprintf('SELECT * FROM %s WHERE %s = :category ORDER BY %s ASC', $this->table, $this->categoryColumn, $this->nameColumn);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':category' => $category]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createOption(
        string $name,
        string $value,
        ?string $category = null,
        string $type = 'text',
        bool $autoload = false
    ): bool {
        [$columns, $placeholders, $params] = $this->buildInsertPayload($name, $value, $category, $type, $autoload);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function updateOption(string $name, array $changes): bool
    {
        $fields = [];
        $params = [':name' => $name];

        if (array_key_exists('value', $changes)) {
            $fields[]         = sprintf('%s = :value', $this->valueColumn);
            $params[':value'] = $changes['value'];
        }

        if ($this->categoryColumn !== null && array_key_exists('category', $changes)) {
            $fields[]            = sprintf('%s = :category', $this->categoryColumn);
            $params[':category'] = $changes['category'];
        }

        if ($this->typeColumn !== null && array_key_exists('type', $changes)) {
            $fields[]         = sprintf('%s = :type', $this->typeColumn);
            $params[':type'] = $changes['type'];
        }

        if ($this->autoloadColumn !== null && array_key_exists('autoload', $changes)) {
            $fields[]              = sprintf('%s = :autoload', $this->autoloadColumn);
            $params[':autoload'] = (int) $changes['autoload'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql  = sprintf('UPDATE %s SET %s WHERE %s = :name', $this->table, implode(', ', $fields), $this->nameColumn);
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function deleteOption(string $name): bool
    {
        $sql  = sprintf('DELETE FROM %s WHERE %s = ?', $this->table, $this->nameColumn);
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([$name]);
    }

    public function updateOptions(array $options, ?string $category = null, string $type = 'text', bool $autoload = false): int
    {
        if (empty($options)) {
            return 0;
        }

        $updated = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($options as $name => $payload) {
                $value         = $payload;
                $optionCategory = $category;
                $optionType     = $type;
                $optionAutoload = $autoload;

                if (is_array($payload)) {
                    $value = (string) ($payload['value'] ?? '');
                    if (array_key_exists('category', $payload)) {
                        $optionCategory = $payload['category'];
                    }
                    if (array_key_exists('type', $payload)) {
                        $optionType = $payload['type'];
                    }
                    if (array_key_exists('autoload', $payload)) {
                        $optionAutoload = (bool) $payload['autoload'];
                    }
                } else {
                    $value = (string) $value;
                }

                $affected = $this->upsertOption($name, $value, $optionCategory, $optionType, $optionAutoload);
                $updated += $affected;
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $updated;
    }

    private function upsertOption(
        string $name,
        string $value,
        ?string $category,
        string $type,
        bool $autoload
    ): int {
        [$columns, $placeholders, $params] = $this->buildInsertPayload($name, $value, $category, $type, $autoload);

        $updateAssignments = [sprintf('%s = :value', $this->valueColumn)];
        if ($this->categoryColumn !== null && $category !== null) {
            $updateAssignments[] = sprintf('%s = :category', $this->categoryColumn);
        }
        if ($this->typeColumn !== null) {
            $updateAssignments[] = sprintf('%s = :type', $this->typeColumn);
        }
        if ($this->autoloadColumn !== null) {
            $updateAssignments[] = sprintf('%s = :autoload', $this->autoloadColumn);
        }

        $updateSql = sprintf(
            'UPDATE %s SET %s WHERE %s = :name',
            $this->table,
            implode(', ', $updateAssignments),
            $this->nameColumn
        );

        $insertSql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $updateStmt = $this->pdo->prepare($updateSql);
        $updateStmt->execute($params);
        $affected = $updateStmt->rowCount();

        if ($affected === 0) {
            if ($this->optionExists($name)) {
                return 0;
            }

            $insertStmt = $this->pdo->prepare($insertSql);
            $insertStmt->execute($params);
            $affected = $insertStmt->rowCount();
        }

        return $affected;
    }

    private function buildInsertPayload(
        string $name,
        string $value,
        ?string $category,
        string $type,
        bool $autoload
    ): array {
        $columns      = [$this->nameColumn, $this->valueColumn];
        $placeholders = [':name', ':value'];
        $params       = [
            ':name'  => $name,
            ':value' => $value,
        ];

        if ($this->categoryColumn !== null) {
            $columns[]      = $this->categoryColumn;
            $placeholders[] = ':category';
            $params[':category'] = $category;
        }

        if ($this->typeColumn !== null) {
            $columns[]      = $this->typeColumn;
            $placeholders[] = ':type';
            $params[':type'] = $type;
        }

        if ($this->autoloadColumn !== null) {
            $columns[]      = $this->autoloadColumn;
            $placeholders[] = ':autoload';
            $params[':autoload'] = (int) $autoload;
        }

        return [$columns, $placeholders, $params];
    }

    private function optionExists(string $name): bool
    {
        $sql  = sprintf('SELECT 1 FROM %s WHERE %s = :name LIMIT 1', $this->table, $this->nameColumn);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $name]);

        return (bool) $stmt->fetchColumn();
    }

    private function resolveTable(): string
    {
        $candidates = ['app_settings', 'settings', 'tbloptions', 'options'];

        foreach ($candidates as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No se encontró una tabla de configuración válida.');
    }

    private function resolveColumns(): void
    {
        $stmt    = $this->pdo->query('SHOW COLUMNS FROM ' . $this->table);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nameColumn  = null;
        $valueColumn = null;

        foreach ($columns as $column) {
            $field = $column['Field'];

            if ($nameColumn === null && stripos($field, 'name') !== false) {
                $nameColumn = $field;
            }

            if ($valueColumn === null && (stripos($field, 'value') !== false || stripos($field, 'setting') !== false)) {
                $valueColumn = $field;
            }

            if ($this->categoryColumn === null && stripos($field, 'category') !== false) {
                $this->categoryColumn = $field;
            }

            if ($this->typeColumn === null && stripos($field, 'type') !== false) {
                $this->typeColumn = $field;
            }

            if ($this->autoloadColumn === null && stripos($field, 'autoload') !== false) {
                $this->autoloadColumn = $field;
            }
        }

        if ($nameColumn === null || $valueColumn === null) {
            throw new RuntimeException('No fue posible detectar las columnas de configuración.');
        }

        $this->nameColumn  = $nameColumn;
        $this->valueColumn = $valueColumn;
    }

    private function tableExists(string $table): bool
    {
        $sql  = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }
}
