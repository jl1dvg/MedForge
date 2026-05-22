<?php

declare(strict_types=1);

namespace App\Modules\KPI\Http\Controllers;

use App\Modules\KPI\Services\KpiQueryService;
use App\Modules\KPI\Support\KpiRegistry;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class KpiController
{
    public function __construct(private readonly KpiQueryService $queryService)
    {
    }

    // GET /kpis
    public function index(): JsonResponse
    {
        return response()->json([
            'kpis' => $this->queryService->listAvailable(),
        ]);
    }

    // GET /kpis/{kpiKey}
    public function show(Request $request, string $kpiKey): JsonResponse
    {
        try {
            $definition = KpiRegistry::get($kpiKey);
        } catch (Throwable) {
            return response()->json(['error' => 'KPI no encontrado'], 404);
        }

        $params = $request->query();
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
            return response()->json([
                'kpi' => $kpiKey,
                'definition' => $definition,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'dimensions' => $dimensions,
                'aggregate' => $data,
            ]);
        }

        $snapshots = $this->queryService->getSnapshots($kpiKey, $start, $end, $dimensions, $ensureFresh);
        return response()->json([
            'kpi' => $kpiKey,
            'definition' => $definition,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'dimensions' => $dimensions,
            'snapshots' => $snapshots,
        ]);
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
