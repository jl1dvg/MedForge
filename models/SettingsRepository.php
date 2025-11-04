<?php

namespace Models;

use PDO;
use PDOException;
use RuntimeException;

class SettingsRepository
{
    private PDO $pdo;
    private string $table;
    private string $nameColumn = 'name';
    private string $valueColumn = 'value';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->table = $this->resolveTable();
        $this->resolveColumns();
    }

    public function getOptions(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = sprintf(
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

    public function updateOptions(array $options): int
    {
        if (empty($options)) {
            return 0;
        }

        $updateSql = sprintf(
            'UPDATE %s SET %s = :value WHERE %s = :name',
            $this->table,
            $this->valueColumn,
            $this->nameColumn
        );
        $insertSql = sprintf(
            'INSERT INTO %s (%s, %s) VALUES (:name, :value)',
            $this->table,
            $this->nameColumn,
            $this->valueColumn
        );

        $updated = 0;
        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare($updateSql);
            $insertStmt = $this->pdo->prepare($insertSql);

            foreach ($options as $name => $value) {
                $updateStmt->execute([
                    ':name' => $name,
                    ':value' => $value,
                ]);
                $affected = $updateStmt->rowCount();

                if ($affected === 0) {
                    $insertStmt->execute([
                        ':name' => $name,
                        ':value' => $value,
                    ]);
                    $affected = $insertStmt->rowCount();
                }

                $updated += $affected;
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $updated;
    }

    private function resolveTable(): string
    {
        $candidates = ['tbloptions', 'options', 'app_settings', 'settings'];

        foreach ($candidates as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No se encontró una tabla de configuración válida.');
    }

    private function resolveColumns(): void
    {
        $stmt = $this->pdo->query('SHOW COLUMNS FROM ' . $this->table);
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $name = null;
        $value = null;
        foreach ($columns as $column) {
            if ($name === null && stripos($column, 'name') !== false) {
                $name = $column;
            }
            if ($value === null && (
                stripos($column, 'value') !== false
                || stripos($column, 'setting') !== false
                || stripos($column, 'option') !== false
            )) {
                $value = $column;
            }
        }

        if ($name === null || $value === null) {
            throw new RuntimeException('No fue posible detectar las columnas de configuración.');
        }

        $this->nameColumn = $name;
        $this->valueColumn = $value;
    }

    private function tableExists(string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }
}
