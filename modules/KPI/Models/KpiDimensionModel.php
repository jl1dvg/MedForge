<?php

declare(strict_types=1);

namespace Modules\KPI\Models;

use PDO;
use PDOException;

class KpiDimensionModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensure(string $key, string $rawValue, string $normalizedValue, ?array $metadata = null): void
    {
        $sql = <<<'SQL'
            INSERT INTO kpi_dimensions (dimension_key, raw_value, normalized_value, metadata_json)
            VALUES (:dimension_key, :raw_value, :normalized_value, :metadata_json)
            ON DUPLICATE KEY UPDATE
                raw_value = VALUES(raw_value),
                metadata_json = VALUES(metadata_json),
                updated_at = CURRENT_TIMESTAMP
        SQL;

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new PDOException('No fue posible preparar la consulta para registrar la dimensión.');
        }

        $stmt->bindValue(':dimension_key', $key);
        $stmt->bindValue(':raw_value', $rawValue);
        $stmt->bindValue(':normalized_value', $normalizedValue);
        $stmt->bindValue(':metadata_json', $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null);
        $stmt->execute();
    }
}
