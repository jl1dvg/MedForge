<?php

declare(strict_types=1);

namespace App\Modules\KPI\Models;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class KpiSnapshotModel
{
    public function upsert(KpiSnapshot $snapshot): void
    {
        DB::statement(
            <<<'SQL'
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
            SQL,
            [
                ':kpi_key' => $snapshot->kpiKey,
                ':period_start' => $snapshot->periodStart->format('Y-m-d'),
                ':period_end' => $snapshot->periodEnd->format('Y-m-d'),
                ':granularity' => $snapshot->granularity,
                ':dimension_hash' => $snapshot->dimensionHash(),
                ':dimensions_json' => $snapshot->dimensionsJson(),
                ':value' => $snapshot->value,
                ':numerator' => $snapshot->numerator,
                ':denominator' => $snapshot->denominator,
                ':extra_json' => $snapshot->extraJson(),
                ':computed_at' => ($snapshot->computedAt ?? new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ':source_version' => $snapshot->sourceVersion,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSnapshots(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, ?string $dimensionHash = null): array
    {
        $query = DB::table('kpi_snapshots')
            ->select([
                'kpi_key',
                'period_start',
                'period_end',
                'period_granularity',
                'dimension_hash',
                'dimensions_json',
                'value',
                'numerator',
                'denominator',
                'extra_json',
                'computed_at',
                'source_version',
            ])
            ->where('kpi_key', $kpiKey)
            ->where('period_start', '>=', $start->format('Y-m-d'))
            ->where('period_end', '<=', $end->format('Y-m-d'))
            ->orderBy('period_start', 'asc');

        if ($dimensionHash !== null) {
            $query->where('dimension_hash', $dimensionHash);
        }

        $rows = $query->get()->toArray();

        return array_map(static function (object $row): array {
            return [
                'kpi_key' => $row->kpi_key,
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
                'granularity' => $row->period_granularity,
                'dimensions' => $row->dimensions_json ? json_decode((string) $row->dimensions_json, true, 512, JSON_THROW_ON_ERROR) : [],
                'value' => (float) $row->value,
                'numerator' => $row->numerator !== null ? (float) $row->numerator : null,
                'denominator' => $row->denominator !== null ? (float) $row->denominator : null,
                'extra' => $row->extra_json ? json_decode((string) $row->extra_json, true, 512, JSON_THROW_ON_ERROR) : null,
                'computed_at' => $row->computed_at,
                'source_version' => $row->source_version,
            ];
        }, $rows);
    }

    public function latestSnapshot(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, ?string $dimensionHash = null): ?array
    {
        $query = DB::table('kpi_snapshots')
            ->select([
                'kpi_key',
                'period_start',
                'period_end',
                'period_granularity',
                'dimension_hash',
                'dimensions_json',
                'value',
                'numerator',
                'denominator',
                'extra_json',
                'computed_at',
                'source_version',
            ])
            ->where('kpi_key', $kpiKey)
            ->where('period_start', '>=', $start->format('Y-m-d'))
            ->where('period_end', '<=', $end->format('Y-m-d'))
            ->orderBy('period_end', 'desc')
            ->limit(1);

        if ($dimensionHash !== null) {
            $query->where('dimension_hash', $dimensionHash);
        }

        $row = $query->first();

        if ($row === null) {
            return null;
        }

        return [
            'kpi_key' => $row->kpi_key,
            'period_start' => $row->period_start,
            'period_end' => $row->period_end,
            'granularity' => $row->period_granularity,
            'dimensions' => $row->dimensions_json ? json_decode((string) $row->dimensions_json, true, 512, JSON_THROW_ON_ERROR) : [],
            'value' => (float) $row->value,
            'numerator' => $row->numerator !== null ? (float) $row->numerator : null,
            'denominator' => $row->denominator !== null ? (float) $row->denominator : null,
            'extra' => $row->extra_json ? json_decode((string) $row->extra_json, true, 512, JSON_THROW_ON_ERROR) : null,
            'computed_at' => $row->computed_at,
            'source_version' => $row->source_version,
        ];
    }
}
