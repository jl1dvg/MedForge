<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

class WhatsappExecutiveReportService
{
    private const EXECUTIVE_REPORT_CACHE_TTL = 600;

    private const PERIODS = [
        'hoy' => ['label' => 'Hoy', 'days' => 1],
        '7d' => ['label' => 'Últimos 7 días', 'days' => 7],
        '30d' => ['label' => 'Últimos 30 días', 'days' => 30],
        '90d' => ['label' => 'Últimos 90 días', 'days' => 90],
    ];

    public function __construct(
        private readonly KpiDashboardService $kpiDashboardService = new KpiDashboardService()
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function buildExecutiveReportPayload(array $query, bool $forceRefresh = false): array
    {
        $start = microtime(true);

        $period = $this->normalizePeriod((string) ($query['period'] ?? '30d'));
        $agentId = isset($query['agent_id']) && $query['agent_id'] !== '' ? (int) $query['agent_id'] : null;

        $filters = ['period' => $period, 'agent_id' => $agentId];

        $cacheKey = 'whatsapp_report:' . $this->executiveReportCacheHash($filters);

        if ($forceRefresh) {
            try {
                Cache::forget($cacheKey);
            } catch (\Throwable) {
                // Redis no disponible: continuar sin cache.
            }
        }

        $cacheHit = true;
        $compute = function () use ($filters, &$cacheHit): array {
            $cacheHit = false;

            return $this->computeExecutiveReportPayload($filters);
        };

        try {
            $payload = Cache::remember($cacheKey, self::EXECUTIVE_REPORT_CACHE_TTL, $compute);
        } catch (\Throwable) {
            $payload = $compute();
        }

        $payload['timings']['total'] = round((microtime(true) - $start) * 1000, 2);
        $payload['timings']['cache_hit'] = $cacheHit;
        $payload['timings']['cache_key'] = $cacheKey;
        $payload['timings']['ttl'] = self::EXECUTIVE_REPORT_CACHE_TTL;
        $payload['timings']['total_ms'] = $payload['timings']['total'];

        return $payload;
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function executiveReportCacheHash(array $filters): string
    {
        return md5(json_encode([
            'period' => $filters['period']['key'],
            'agent_id' => $filters['agent_id'],
        ]));
    }

    private function normalizePeriod(string $key): array
    {
        $key = array_key_exists($key, self::PERIODS) ? $key : '30d';
        $days = self::PERIODS[$key]['days'];

        $today = new DateTimeImmutable('today');
        $from = $today->modify('-' . ($days - 1) . ' days');

        return [
            'key' => $key,
            'label' => self::PERIODS[$key]['label'],
            'from' => $from,
            'to' => $today,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function computeExecutiveReportPayload(array $filters): array
    {
        $timings = [];
        $start = microtime(true);
        $t = $start;
        $mark = function (string $label) use (&$timings, &$t): void {
            $timings[$label] = round((microtime(true) - $t) * 1000, 2);
            $t = microtime(true);
        };

        $period = $filters['period'];
        $agentId = $filters['agent_id'];

        $dashboard = $this->kpiDashboardService->buildDashboard($period['from'], $period['to'], null, $agentId, 15);
        $mark('build_dashboard');

        $prevDays = $period['from']->diff($period['to'])->days + 1;
        $prevTo = $period['from']->modify('-1 day');
        $prevFrom = $prevTo->modify('-' . ($prevDays - 1) . ' days');
        $prevDashboard = $this->kpiDashboardService->buildDashboard($prevFrom, $prevTo, null, $agentId, 15);
        $mark('build_dashboard_prev');

        $report = $this->mapToReport($dashboard, $prevDashboard, $period);
        $mark('map_report');

        $timings['total'] = round((microtime(true) - $start) * 1000, 2);

        return [
            'report' => $report,
            'filters' => [
                'period' => $period['key'],
                'agent_id' => $agentId,
            ],
            'timings' => $timings,
        ];
    }

    /**
     * @param array<string,mixed> $d
     * @param array<string,mixed> $prev
     * @param array<string,mixed> $period
     */
    private function mapToReport(array $d, array $prev, array $period): array
    {
        $s = $d['summary'];
        $ps = $prev['summary'];
        $analytics = $d['analytics'];
        $aSummary = $analytics['summary'];

        $conversationsNew = (int) ($s['conversations_new'] ?? 0);
        $prevConversations = (int) ($ps['conversations_new'] ?? 0);
        $messagesIn = (int) ($s['messages_inbound'] ?? 0);
        $messagesOut = (int) ($s['messages_outbound'] ?? 0);
        $peopleInbound = (int) ($s['people_inbound'] ?? 0);
        $prevPeopleInbound = (int) ($ps['people_inbound'] ?? 0);

        $attentionRate = (float) ($s['attention_rate'] ?? 0);
        $prevAttentionRate = (float) ($ps['attention_rate'] ?? 0);
        $medianFirstResp = $s['median_first_human_response_minutes'] ?? null;
        $prevMedianFirstResp = $ps['median_first_human_response_minutes'] ?? null;
        $p75FirstResp = $s['p75_first_human_response_minutes'] ?? null;
        $slaRate = (float) ($s['sla_response_rate'] ?? 0);

        $bookings = (int) ($aSummary['booked_conversations'] ?? 0);
        $prevBookings = (int) ($prev['analytics']['summary']['booked_conversations'] ?? 0);
        $bookingRate = (float) ($aSummary['booking_rate'] ?? 0);
        $prevBookingRate = (float) ($prev['analytics']['summary']['booking_rate'] ?? 0);
        $bookingPatients = (int) ($s['sigcenter_booking_patients'] ?? 0);
        $bookingFailures = (int) ($s['sigcenter_booking_failures'] ?? 0);

        $handoffs = (int) ($aSummary['handoff_conversations'] ?? 0);
        $handoffRate = (float) ($aSummary['handoff_rate'] ?? 0);
        $containmentRate = round(100 - $handoffRate, 1);
        $prevContainmentRate = round(100 - (float) ($prev['analytics']['summary']['handoff_rate'] ?? 0), 1);
        $identificationRate = (float) ($aSummary['identification_rate'] ?? 0);

        $pct = static fn (float $cur, float $prev): float => $prev > 0 ? round(($cur - $prev) / $prev * 1000, 1) / 10 : 0.0;

        $deltas = [
            'conversations' => $pct((float) $conversationsNew, (float) $prevConversations),
            'people' => $pct((float) $peopleInbound, (float) $prevPeopleInbound),
            'attentionRate' => round($attentionRate - $prevAttentionRate, 1),
            'medianResp' => $medianFirstResp !== null && $prevMedianFirstResp !== null
                ? round($medianFirstResp - $prevMedianFirstResp, 1) : 0.0,
            'bookings' => $pct((float) $bookings, (float) $prevBookings),
            'bookingRate' => round($bookingRate - $prevBookingRate, 1),
            'containment' => round($containmentRate - $prevContainmentRate, 1),
        ];

        $trendLabels = $d['trends']['labels'] ?? [];
        $trend = [];
        foreach ($trendLabels as $i => $label) {
            $trend[] = [
                'label' => substr((string) $label, 5),
                'conversaciones' => (int) ($d['trends']['conversations'][$i] ?? 0),
                'atendidas' => null,
                'bot' => null,
                'citas' => (int) ($d['trends']['sigcenter_bookings'][$i] ?? 0),
            ];
        }

        $sources = array_map(static fn (array $row): array => [
            'id' => $row['source_category'] ?? $row['key'] ?? '',
            'label' => $row['label'] ?? ($row['source_category'] ?? 'Sin clasificar'),
            'total' => (int) ($row['total'] ?? 0),
            'share' => (float) ($row['pct'] ?? $row['share'] ?? 0),
            'identified' => (int) ($row['identified'] ?? 0),
            'bookings' => (int) ($row['booked'] ?? $row['bookings'] ?? 0),
            'bookingRate' => (float) ($row['booking_rate'] ?? 0),
        ], $analytics['sources'] ?? []);

        $intents = array_map(static fn (array $row): array => [
            'label' => $row['label'] ?? $row['intent'] ?? 'Sin clasificar',
            'total' => (int) ($row['total'] ?? 0),
            'share' => (float) ($row['pct'] ?? $row['share'] ?? 0),
        ], $analytics['intents'] ?? []);

        $lifecycle = array_map(static fn (array $row): array => [
            'label' => $row['label'] ?? $row['lifecycle_category'] ?? 'Sin clasificar',
            'total' => (int) ($row['total'] ?? 0),
            'share' => (float) ($row['pct'] ?? $row['share'] ?? 0),
            'identified' => (int) ($row['identified'] ?? 0),
            'bookings' => (int) ($row['booked'] ?? $row['bookings'] ?? 0),
            'bookingRate' => (float) ($row['booking_rate'] ?? 0),
        ], $analytics['lifecycle'] ?? []);

        $funnel = array_map(static fn (array $row): array => [
            'label' => $row['label'] ?? $row['stage'] ?? '',
            'value' => (int) ($row['total'] ?? $row['value'] ?? 0),
        ], $analytics['funnel'] ?? []);

        $frictions = array_map(static fn (array $row): array => [
            'label' => $row['label'] ?? $row['friction'] ?? 'Sin clasificar',
            'total' => (int) ($row['total'] ?? 0),
            'share' => (float) ($row['pct'] ?? $row['share'] ?? 0),
        ], $analytics['frictions'] ?? []);

        $agents = array_map(static fn (array $row): array => [
            'name' => $row['agent_name'] ?? '',
            'attended' => (int) ($row['conversations_attended'] ?? $row['assigned_count'] ?? 0),
            'avgRespMin' => isset($row['avg_response_minutes']) ? round((float) $row['avg_response_minutes'], 1) : null,
        ], $d['breakdowns']['human_attention_by_agent'] ?? []);

        $teams = array_map(static fn (array $row): array => [
            'name' => $row['role_name'] ?? 'Sin rol',
            'total' => (int) ($row['total'] ?? 0),
            'queued' => (int) ($row['queued'] ?? 0),
            'assigned' => (int) ($row['assigned'] ?? 0),
            'resolved' => (int) ($row['resolved'] ?? 0),
        ], $d['breakdowns']['handoffs_by_role'] ?? []);

        $insights = $this->buildInsights($conversationsNew, $deltas, $sources, $attentionRate, $medianFirstResp, $bookings, $bookingRate, $containmentRate);
        $recommendations = $this->buildRecommendations($deltas, $bookingRate, $frictions, $s['people_lost'] ?? 0);

        $slaTarget = (int) ($s['sla_target_minutes'] ?? 15);

        return [
            'period' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'fromLabel' => $period['from']->format('d/m/Y'),
                'toLabel' => $period['to']->format('d/m/Y'),
            ],
            'sede' => ['id' => 'todas', 'label' => 'Todas las sedes'],
            'generatedAt' => (new DateTimeImmutable())->format('d/m/Y H:i'),
            'slaTarget' => $slaTarget,
            'summary' => [
                'conversationsNew' => $conversationsNew,
                'peopleInbound' => $peopleInbound,
                'messagesIn' => $messagesIn,
                'messagesOut' => $messagesOut,
                'messagesTotal' => $messagesIn + $messagesOut,
                'attentionRate' => $attentionRate,
                'attendedHuman' => (int) ($s['conversations_attended_human'] ?? 0),
                'lostNeedsHuman' => (int) ($s['conversations_lost_needs_human'] ?? 0),
                'resolvedBot' => (int) ($s['conversations_resolved_by_bot'] ?? 0),
                'resolved' => (int) ($s['conversations_resolved'] ?? 0),
                'medianFirstResp' => $medianFirstResp,
                'p75FirstResp' => $p75FirstResp,
                'slaRate' => $slaRate,
                'bookings' => $bookings,
                'bookingPatients' => $bookingPatients,
                'bookingFailures' => $bookingFailures,
                'bookingRate' => $bookingRate,
                'handoffs' => $handoffs,
                'handoffRate' => $handoffRate,
                'identificationRate' => $identificationRate,
                'containmentRate' => $containmentRate,
                'csat' => null,
                'reactivationRate' => $aSummary['reactivated_patients'] ?? null,
                'deltas' => $deltas,
            ],
            'trend' => $trend,
            'sources' => $sources,
            'intents' => $intents,
            'lifecycle' => $lifecycle,
            'funnel' => $funnel,
            'frictions' => $frictions,
            'agents' => $agents,
            'teams' => $teams,
            'insights' => $insights,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $sources
     * @param array<string,float> $deltas
     */
    private function buildInsights(
        int $conv,
        array $deltas,
        array $sources,
        float $attentionRate,
        ?float $medianFirstResp,
        int $bookings,
        float $bookingRate,
        float $containmentRate
    ): array {
        $top = $sources[0] ?? null;
        $insights = [];

        $insights[] = [
            'tone' => $deltas['conversations'] >= 0 ? 'success' : 'warning',
            'title' => 'Demanda del canal',
            'body' => sprintf(
                'El canal recibió %s conversaciones nuevas, %s de %s%% frente al período anterior.%s',
                number_format($conv, 0, ',', '.'),
                $deltas['conversations'] >= 0 ? 'un alza' : 'una baja',
                abs($deltas['conversations']),
                $top !== null ? sprintf(' %s concentra el %s%% del origen.', $top['label'], $top['share']) : ''
            ),
        ];

        $insights[] = [
            'tone' => $attentionRate >= 85 ? 'success' : ($attentionRate >= 75 ? 'warning' : 'danger'),
            'title' => 'Cobertura humana',
            'body' => sprintf(
                'Se atendió al %s%% de las conversaciones que escalaron a una persona%s.',
                $attentionRate,
                $medianFirstResp !== null ? sprintf(', con una mediana de %s min a la primera respuesta', $medianFirstResp) : ''
            ),
        ];

        $insights[] = [
            'tone' => $bookingRate >= 20 ? 'success' : 'warning',
            'title' => 'Conversión a cita',
            'body' => sprintf(
                '%s citas se agendaron desde WhatsApp (%s%% de quienes escribieron).',
                number_format($bookings, 0, ',', '.'),
                $bookingRate
            ),
        ];

        $insights[] = [
            'tone' => $containmentRate >= 62 ? 'success' : 'warning',
            'title' => 'Automatización',
            'body' => sprintf(
                'El bot contuvo el %s%% de las conversaciones sin intervención humana (%s%s pts vs. período anterior).',
                $containmentRate,
                $deltas['containment'] >= 0 ? '+' : '',
                $deltas['containment']
            ),
        ];

        return $insights;
    }

    /**
     * @param array<string,float> $deltas
     * @param array<int,array<string,mixed>> $frictions
     */
    private function buildRecommendations(array $deltas, float $bookingRate, array $frictions, int $lost): array
    {
        $recs = [];
        if ($lost > 0) {
            $recs[] = sprintf('Cerrar la brecha de %d conversaciones perdidas: reforzar la atención humana en las franjas de mayor demanda.', $lost);
        }
        if ($bookingRate < 22) {
            $recs[] = 'Elevar la conversión a cita revisando los segmentos de seguimiento y reactivación, que suelen convertir mejor que la captación nueva.';
        }
        $topFriction = $frictions[0] ?? null;
        if ($topFriction !== null) {
            $recs[] = sprintf('Reducir la fricción "%s" (%s%% de los handoffs) afinando el flujo del bot en Flowmaker.', strtolower((string) $topFriction['label']), $topFriction['share']);
        }
        if ($deltas['medianResp'] > 0) {
            $recs[] = sprintf('La mediana de primera respuesta subió %s min: revisar la asignación automática fuera de horario pico.', $deltas['medianResp']);
        }
        $recs[] = 'Activar medición de costo por conversación e ingreso atribuido para cerrar el caso de ROI del canal (KPI en implementación).';

        return array_slice($recs, 0, 5);
    }
}
