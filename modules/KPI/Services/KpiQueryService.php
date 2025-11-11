<?php

declare(strict_types=1);

namespace Modules\KPI\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use Modules\KPI\Models\KpiSnapshotModel;
use Modules\KPI\Support\KpiRegistry;
use PDO;

class KpiQueryService
{
    private KpiSnapshotModel $snapshotModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->snapshotModel = new KpiSnapshotModel($pdo);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listAvailable(): array
    {
        return KpiRegistry::all();
    }

    /**
     * @param array<string, scalar> $dimensions
     * @return array<int, array<string, mixed>>
     */
    public function getSnapshots(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, array $dimensions = [], bool $ensureFresh = false): array
    {
        $dimensionHash = $this->dimensionHash($dimensions);
        $snapshots = $this->snapshotModel->listSnapshots($kpiKey, $start, $end, $dimensionHash);

        if ($ensureFresh && $snapshots === []) {
            $calculationPeriod = $this->buildDailyPeriod($start, $end);
            $calculator = new KpiCalculationService($this->pdo);
            $calculator->recalculateRange($calculationPeriod, [$kpiKey]);
            $snapshots = $this->snapshotModel->listSnapshots($kpiKey, $start, $end, $dimensionHash);
        }

        return $snapshots;
    }

    /**
     * @param array<string, scalar> $dimensions
     * @return array<string, mixed>|null
     */
    public function getLatestSnapshot(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, array $dimensions = [], bool $ensureFresh = false): ?array
    {
        $dimensionHash = $this->dimensionHash($dimensions);
        $snapshot = $this->snapshotModel->latestSnapshot($kpiKey, $start, $end, $dimensionHash);

        if ($ensureFresh && $snapshot === null) {
            $calculator = new KpiCalculationService($this->pdo);
            $calculator->recalculateRange($this->buildDailyPeriod($start, $end), [$kpiKey]);
            $snapshot = $this->snapshotModel->latestSnapshot($kpiKey, $start, $end, $dimensionHash);
        }

        return $snapshot;
    }

    /**
     * @param array<string, scalar> $dimensions
     * @return array<string, mixed>|null
     */
    public function getAggregatedValue(string $kpiKey, DateTimeInterface $start, DateTimeInterface $end, array $dimensions = [], bool $ensureFresh = false): ?array
    {
        $snapshots = $this->getSnapshots($kpiKey, $start, $end, $dimensions, $ensureFresh);
        if ($snapshots === []) {
            return null;
        }

        $definition = KpiRegistry::get($kpiKey);
        $valueType = $definition['value_type'] ?? 'count';
        $totalValue = 0.0;
        $totalNumerator = 0.0;
        $totalDenominator = 0.0;

        foreach ($snapshots as $snapshot) {
            $value = (float) ($snapshot['value'] ?? 0);
            $numerator = $snapshot['numerator'] !== null ? (float) $snapshot['numerator'] : null;
            $denominator = $snapshot['denominator'] !== null ? (float) $snapshot['denominator'] : null;

            if ($valueType === 'percentage' && $numerator !== null && $denominator !== null) {
                $totalNumerator += $numerator;
                $totalDenominator += $denominator;
            } else {
                $totalValue += $value;
            }
        }

        if ($valueType === 'percentage') {
            $value = $totalDenominator > 0 ? round(($totalNumerator / $totalDenominator) * 100, 2) : 0.0;
            return [
                'value' => $value,
                'numerator' => $totalNumerator,
                'denominator' => $totalDenominator,
                'periods' => count($snapshots),
            ];
        }

        return [
            'value' => $totalValue,
            'periods' => count($snapshots),
        ];
    }

    private function dimensionHash(array $dimensions): ?string
    {
        if ($dimensions === []) {
            return null;
        }

        ksort($dimensions);
        return hash('sha256', json_encode($dimensions, JSON_THROW_ON_ERROR));
    }

    private function buildDailyPeriod(DateTimeInterface $start, DateTimeInterface $end): DatePeriod
    {
        $startDay = DateTimeImmutable::createFromInterface($start)->setTime(0, 0, 0);
        $endDay = DateTimeImmutable::createFromInterface($end)->setTime(0, 0, 0)->add(new DateInterval('P1D'));

        return new DatePeriod($startDay, new DateInterval('P1D'), $endDay);
    }
}
