<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\KpiDashboardService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KpiReadController
{
    public function __construct(
        private readonly KpiDashboardService $service = new KpiDashboardService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            return response()->json([
                'ok' => true,
                'data' => $this->service->buildDashboard(
                    $start,
                    $end,
                    $this->nullableInt($request->query('role_id')),
                    $this->nullableInt($request->query('agent_id')),
                    $this->nullableInt($request->query('sla_target_minutes'))
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function drilldown(Request $request): JsonResponse
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);
            $metric = trim((string) $request->query('metric', ''));
            if ($metric === '') {
                throw new InvalidArgumentException('Debes indicar la métrica en ?metric=...');
            }

            return response()->json([
                'ok' => true,
                'data' => $this->service->buildDrilldown(
                    $metric,
                    $start,
                    $end,
                    $this->nullableInt($request->query('role_id')),
                    $this->nullableInt($request->query('agent_id')),
                    max(1, (int) $request->query('page', 1)),
                    max(1, min(200, (int) $request->query('limit', 50)))
                ),
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolveDateRange($request);
        $roleId = $this->nullableInt($request->query('role_id'));
        $agentId = $this->nullableInt($request->query('agent_id'));
        $slaTargetMinutes = $this->nullableInt($request->query('sla_target_minutes'));
        $rows = $this->service->exportDashboardCsvRows($start, $end, $roleId, $agentId, $slaTargetMinutes);
        $filename = sprintf(
            'whatsapp-kpi-%s-a-%s.csv',
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        return response()->streamDownload(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function resolveDateRange(Request $request): array
    {
        $today = new DateTimeImmutable('today');
        $from = $this->parseDate(trim((string) $request->query('date_from', ''))) ?? $today->modify('-29 days');
        $to = $this->parseDate(trim((string) $request->query('date_to', ''))) ?? $today;

        if ($from > $to) {
            throw new InvalidArgumentException('date_from no puede ser mayor que date_to.');
        }

        $days = (int) $from->diff($to)->days + 1;
        if ($days > 366) {
            throw new InvalidArgumentException('El rango máximo permitido es de 366 días.');
        }

        return [$from, $to];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw new InvalidArgumentException('Formato de fecha inválido. Usa YYYY-MM-DD.');
        }

        return $date->setTime(0, 0, 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }
}
