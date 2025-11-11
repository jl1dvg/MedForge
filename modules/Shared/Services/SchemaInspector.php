<?php

declare(strict_types=1);

namespace Modules\Shared\Services;

use PDO;
use Throwable;

/**
 * Utilidad sencilla para consultar metadatos del esquema con caché en memoria.
 */
class SchemaInspector
{
    private PDO $pdo;

    /**
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table) . '.' . strtolower($column);

        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        $exists = false;

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            $exists = $this->fallbackHasColumn($table, $column);
        }

        self::$columnCache[$cacheKey] = $exists;

        return $exists;
    }

    private function fallbackHasColumn(string $table, string $column): bool
    {
        try {
            $sql = sprintf('SHOW COLUMNS FROM %s LIKE :column', $this->quoteIdentifier($table));
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':column' => $column]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
