<?php

declare(strict_types=1);

namespace Modules\KPI\Controllers;

use DateTimeImmutable;
use DateTimeInterface;
use Modules\KPI\Services\KpiQueryService;
use Modules\KPI\Support\KpiRegistry;
use PDO;
use Throwable;

class KpiController
{
    private KpiQueryService $queryService;

    public function __construct(private readonly PDO $pdo)
    {
        $this->queryService = new KpiQueryService($pdo);
    }

    public function index(): void
    {
        $this->respondJson([
            'kpis' => $this->queryService->listAvailable(),
        ]);
    }

    public function show(string $kpiKey): void
    {
        try {
            $definition = KpiRegistry::get($kpiKey);
        } catch (Throwable) {
            $this->respondJson(['error' => 'KPI no encontrado'], 404);
            return;
        }

        $params = $_GET;
        $start = $this->resolveDate($params['start'] ?? null, new DateTimeImmutable('-29 days'));
        $end = $this->resolveDate($params['end'] ?? null, new DateTimeImmutable('today'));

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $ensureFresh = filter_var($params['ensureFresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $aggregate = filter_var($params['aggregate'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $dimensions = $this->extractDimensions($params['dimensions'] ?? []);

        if ($aggregate) {
            $data = $this->queryService->getAggregatedValue($kpiKey, $start, $end, $dimensions, $ensureFresh);
            $this->respondJson([
                'kpi' => $kpiKey,
                'definition' => $definition,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'dimensions' => $dimensions,
                'aggregate' => $data,
            ]);
            return;
        }

        $snapshots = $this->queryService->getSnapshots($kpiKey, $start, $end, $dimensions, $ensureFresh);
        $this->respondJson([
            'kpi' => $kpiKey,
            'definition' => $definition,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'dimensions' => $dimensions,
            'snapshots' => $snapshots,
        ]);
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function resolveDate(?string $value, DateTimeInterface $default): DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return DateTimeImmutable::createFromInterface($default)->setTime(0, 0, 0);
        }

        $formats = ['Y-m-d', 'd/m/Y', DateTimeInterface::ATOM];
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        return DateTimeImmutable::createFromInterface($default)->setTime(0, 0, 0);
    }

    /**
     * @param array<string, mixed> $dimensions
     * @return array<string, scalar>
     */
    private function extractDimensions(array $dimensions): array
    {
        $normalized = [];
        foreach ($dimensions as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $normalized[(string) $key] = (string) $value;
        }

        ksort($normalized);
        return $normalized;
    }
}
