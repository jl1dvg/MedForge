<?php

declare(strict_types=1);

namespace Modules\KPI\Models;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;

class KpiSnapshotModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function upsert(KpiSnapshot $snapshot): void
    {
        $sql = <<<'SQL'
            INSERT INTO kpi_snapshots (
                kpi_key,
                period_start,
                period_end,
                period_granularity,
                dimension_hash,
                dimensions_json,
                value,
                numerator,
                denominator,
                extra_json,
                computed_at,
                source_version
            ) VALUES (
                :kpi_key,
                :period_start,
                :period_end,
                :granularity,
                :dimension_hash,
                :dimensions_json,
                :value,
                :numerator,
                :denominator,
                :extra_json,
                :computed_at,
                :source_version
            )
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                numerator = VALUES(numerator),
                denominator = VALUES(denominator),
                extra_json = VALUES(extra_json),
                computed_at = VALUES(computed_at),
                source_version = VALUES(source_version)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new PDOException('No fue posible preparar la consulta para insertar el snapshot.');
        }

        $stmt->bindValue(':kpi_key', $snapshot->kpiKey);
        $stmt->bindValue(':period_start', $snapshot->periodStart->format('Y-m-d'));
        $stmt->bindValue(':period_end', $snapshot->periodEnd->format('Y-m-d'));
        $stmt->bindValue(':granularity', $snapshot->granularity);
        $stmt->bindValue(':dimension_hash', $snapshot->dimensionHash());
        $stmt->bindValue(':dimensions_json', $snapshot->dimensionsJson());
        $stmt->bindValue(':value', $snapshot->value);
        $stmt->bindValue(':numerator', $snapshot->numerator);
        $stmt->bindValue(':denominator', $snapshot->denominator);
        $stmt->bindValue(':extra_json', $snapshot->extraJson());
        $stmt->bindValue(':computed_at', ($snapshot->computedAt ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
        $stmt->bindValue(':source_version', $snapshot->sourceVersion);
        $stmt->execute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSnapshots(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, ?string $dimensionHash = null): array
    {
        $sql = <<<'SQL'
            SELECT
                kpi_key,
                period_start,
                period_end,
                period_granularity,
                dimension_hash,
                dimensions_json,
                value,
                numerator,
                denominator,
                extra_json,
                computed_at,
                source_version
            FROM kpi_snapshots
            WHERE kpi_key = :kpi_key
              AND period_start >= :start
              AND period_end <= :end
        SQL;

        if ($dimensionHash !== null) {
            $sql .= "\n  AND dimension_hash = :dimension_hash";
        }

        $sql .= "\n ORDER BY period_start ASC";

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new PDOException('No fue posible preparar la consulta para listar snapshots.');
        }

        $stmt->bindValue(':kpi_key', $kpiKey);
        $stmt->bindValue(':start', $start->format('Y-m-d'));
        $stmt->bindValue(':end', $end->format('Y-m-d'));
        if ($dimensionHash !== null) {
            $stmt->bindValue(':dimension_hash', $dimensionHash);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'kpi_key' => $row['kpi_key'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'granularity' => $row['period_granularity'],
                'dimensions' => $row['dimensions_json'] ? json_decode((string) $row['dimensions_json'], true, 512, JSON_THROW_ON_ERROR) : [],
                'value' => (float) $row['value'],
                'numerator' => $row['numerator'] !== null ? (float) $row['numerator'] : null,
                'denominator' => $row['denominator'] !== null ? (float) $row['denominator'] : null,
                'extra' => $row['extra_json'] ? json_decode((string) $row['extra_json'], true, 512, JSON_THROW_ON_ERROR) : null,
                'computed_at' => $row['computed_at'],
                'source_version' => $row['source_version'],
            ];
        }, $rows);
    }

    public function latestSnapshot(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, ?string $dimensionHash = null): ?array
    {
        $sql = <<<'SQL'
            SELECT
                kpi_key,
                period_start,
                period_end,
                period_granularity,
                dimension_hash,
                dimensions_json,
                value,
                numerator,
                denominator,
                extra_json,
                computed_at,
                source_version
            FROM kpi_snapshots
            WHERE kpi_key = :kpi_key
              AND period_start >= :start
              AND period_end <= :end
        SQL;

        if ($dimensionHash !== null) {
            $sql .= "\n  AND dimension_hash = :dimension_hash";
        }

        $sql .= "\n ORDER BY period_end DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new PDOException('No fue posible preparar la consulta para obtener el snapshot más reciente.');
        }

        $stmt->bindValue(':kpi_key', $kpiKey);
        $stmt->bindValue(':start', $start->format('Y-m-d'));
        $stmt->bindValue(':end', $end->format('Y-m-d'));
        if ($dimensionHash !== null) {
            $stmt->bindValue(':dimension_hash', $dimensionHash);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'kpi_key' => $row['kpi_key'],
            'period_start' => $row['period_start'],
            'period_end' => $row['period_end'],
            'granularity' => $row['period_granularity'],
            'dimensions' => $row['dimensions_json'] ? json_decode((string) $row['dimensions_json'], true, 512, JSON_THROW_ON_ERROR) : [],
            'value' => (float) $row['value'],
            'numerator' => $row['numerator'] !== null ? (float) $row['numerator'] : null,
            'denominator' => $row['denominator'] !== null ? (float) $row['denominator'] : null,
            'extra' => $row['extra_json'] ? json_decode((string) $row['extra_json'], true, 512, JSON_THROW_ON_ERROR) : null,
            'computed_at' => $row['computed_at'],
            'source_version' => $row['source_version'],
        ];
    }
}
