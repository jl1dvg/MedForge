<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Shared\Support\SettingsOptionResolver;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class KpiDashboardService
{
    private const DEFAULT_SLA_TARGET_MINUTES = 15;

    private ?SettingsOptionResolver $settingsResolver = null;
    private ?array $settingsOptionCache = null;

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $roleId = null,
        ?int $agentId = null,
        ?int $slaTargetMinutes = null
    ): array {
        $from = $startDate->setTime(0, 0, 0);
        $toExclusive = $endDate->setTime(0, 0, 0)->modify('+1 day');
        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $toExclusive->format('Y-m-d H:i:s');
        [$reminderFromSql, $reminderToSql] = $this->localReminderDateRangeSql($from, $endDate);
        $slaTargetMinutes = $this->resolveSlaTargetMinutes($slaTargetMinutes);

        $summary = [
            'conversations_new' => $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_conversations WHERE created_at >= ? AND created_at < ?',
                [$fromSql, $toSql]
            ),
            'messages_inbound' => $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_messages WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?',
                ['inbound', $fromSql, $toSql]
            ),
            'messages_outbound' => $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_messages WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?',
                ['outbound', $fromSql, $toSql]
            ),
        ];

        $human = $this->humanAttentionSummary($fromSql, $toSql, $roleId, $agentId);
        $queue = $this->queueSummary($roleId, $agentId);
        $window = $this->conversationWindowSummary($roleId, $agentId);
        $sla = $this->slaSummary($fromSql, $toSql, $roleId, $agentId, $slaTargetMinutes);
        $transfers = $this->transferSummary($fromSql, $toSql, $roleId, $agentId);
        $bookings = $this->sigcenterBookingSummary($fromSql, $toSql);
        $analytics = $this->conversationAnalytics($fromSql, $toSql, $roleId, $agentId);
        $reminders = $this->appointmentReminderAnalytics($reminderFromSql, $reminderToSql);
        $closeReasons = $this->closeReasonSummary($fromSql, $toSql, $roleId, $agentId);
        $operationalInbox = $this->operationalInboxSummary($roleId, $agentId);

        $summary = array_merge($summary, $human, $queue, $window, $sla, [
            'handoff_transfers' => $transfers,
            'peak_open_conversations' => (int) ($human['peak_open_conversations'] ?? 0),
        ], $bookings, $closeReasons, $operationalInbox);

        return [
            'period' => [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $endDate->format('Y-m-d'),
                'days' => iterator_count(new DatePeriod($from, new DateInterval('P1D'), $toExclusive)),
            ],
            'filters' => [
                'role_id' => $roleId,
                'agent_id' => $agentId,
                'sla_target_minutes' => $slaTargetMinutes,
            ],
            'summary' => $summary,
            'trends' => [
                'labels' => $this->dateLabels($from, $toExclusive),
                'conversations' => $this->mapTrend(
                    $this->trendRows(
                        'SELECT DATE(created_at) AS period_date, COUNT(*) AS total
                         FROM whatsapp_conversations
                         WHERE created_at >= ? AND created_at < ?
                         GROUP BY DATE(created_at)
                         ORDER BY period_date ASC',
                        [$fromSql, $toSql]
                    ),
                    $from,
                    $toExclusive
                ),
                'messages_inbound' => $this->mapTrend(
                    $this->trendRows(
                        'SELECT DATE(message_timestamp) AS period_date, COUNT(*) AS total
                         FROM whatsapp_messages
                         WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?
                         GROUP BY DATE(message_timestamp)
                         ORDER BY period_date ASC',
                        ['inbound', $fromSql, $toSql]
                    ),
                    $from,
                    $toExclusive
                ),
                'messages_outbound' => $this->mapTrend(
                    $this->trendRows(
                        'SELECT DATE(message_timestamp) AS period_date, COUNT(*) AS total
                         FROM whatsapp_messages
                         WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?
                         GROUP BY DATE(message_timestamp)
                         ORDER BY period_date ASC',
                        ['outbound', $fromSql, $toSql]
                    ),
                    $from,
                    $toExclusive
                ),
                'handoff_transfers' => $this->mapTrend(
                    $this->transferTrendRows($fromSql, $toSql, $roleId, $agentId),
                    $from,
                    $toExclusive
                ),
                'sigcenter_bookings' => $this->mapTrend(
                    $this->sigcenterBookingTrendRows($fromSql, $toSql),
                    $from,
                    $toExclusive
                ),
            ],
            'breakdowns' => [
                'handoffs_by_role' => $this->handoffsByRole($fromSql, $toSql, $roleId, $agentId),
                'handoffs_by_agent' => $this->handoffsByAgent($fromSql, $toSql, $roleId, $agentId),
                'human_attention_by_agent' => $this->humanAttentionByAgent($fromSql, $toSql, $roleId, $agentId),
                'human_response_by_queue' => $this->humanResponseByQueue($fromSql, $toSql, $roleId, $agentId),
                'sigcenter_bookings_by_sede' => $this->sigcenterBookingsBySede($fromSql, $toSql),
            ],
            'analytics' => $analytics,
            'reminders' => $reminders,
            'options' => [
                'roles' => $this->roleOptions(),
                'agents' => $this->agentOptions(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDrilldown(
        string $metric,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $roleId = null,
        ?int $agentId = null,
        int $page = 1,
        int $limit = 50
    ): array {
        $metric = strtolower(trim($metric));
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;
        $fromSql = $startDate->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $toSql = $endDate->setTime(0, 0, 0)->modify('+1 day')->format('Y-m-d H:i:s');

        return match ($metric) {
            'conversations_new' => $this->drilldownConversations($fromSql, $toSql, $limit, $offset),
            'messages_inbound' => $this->drilldownMessages($fromSql, $toSql, 'inbound', $limit, $offset),
            'messages_outbound' => $this->drilldownMessages($fromSql, $toSql, 'outbound', $limit, $offset),
            'conversations_attended_human' => $this->drilldownHumanAttention($fromSql, $toSql, $roleId, $agentId, true, $limit, $offset),
            'conversations_lost' => $this->drilldownHumanAttention($fromSql, $toSql, $roleId, $agentId, false, $limit, $offset),
            'live_queue_total' => $this->drilldownLiveQueue($roleId, $agentId, $limit, $offset),
            'queue_needs_template' => $this->drilldownNeedsTemplate($roleId, $agentId, $limit, $offset),
            'sla_assignments_total' => $this->drilldownSlaAssignments($fromSql, $toSql, $roleId, $agentId, $limit, $offset),
            'handoff_transfers' => $this->drilldownTransfers($fromSql, $toSql, $roleId, $agentId, $limit, $offset),
            default => throw new InvalidArgumentException('La métrica solicitada aún no tiene drilldown en Laravel.'),
        };
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    public function exportDashboardCsvRows(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $roleId = null,
        ?int $agentId = null,
        ?int $slaTargetMinutes = null
    ): array {
        $dashboard = $this->buildDashboard($startDate, $endDate, $roleId, $agentId, $slaTargetMinutes);
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $breakdowns = is_array($dashboard['breakdowns'] ?? null) ? $dashboard['breakdowns'] : [];

        $rows = [
            ['section', 'label', 'value', 'detail'],
            ['period', 'Desde', $dashboard['period']['date_from'] ?? '', null],
            ['period', 'Hasta', $dashboard['period']['date_to'] ?? '', null],
            ['period', 'Días', $dashboard['period']['days'] ?? 0, null],
        ];

        foreach ([
            'conversations_new' => 'Conversaciones nuevas',
            'messages_inbound' => 'Mensajes inbound',
            'messages_outbound' => 'Mensajes outbound',
            'people_inbound' => 'Personas que escribieron',
            'conversations_attended_human' => 'Conversaciones atendidas',
            'people_attended_human' => 'Personas atendidas',
            'conversations_lost' => 'Conversaciones sin respuesta humana',
            'people_lost' => 'Personas sin respuesta humana',
            'conversations_lost_with_handoff' => 'Conversaciones sin respuesta humana con handoff',
            'attention_rate' => 'Cobertura humana',
            'loss_rate' => 'Tasa sin respuesta humana',
            'avg_first_human_response_minutes' => 'Tiempo a primera respuesta humana desde handoff (min)',
            'median_first_human_response_minutes' => 'Tiempo mediano a primera respuesta humana desde handoff (min)',
            'conversations_abandoned' => 'Conversaciones inactivas >24h sin respuesta humana',
            'conversations_abandoned_with_handoff' => 'Sin respuesta humana con handoff >24h',
            'conversations_resolved' => 'Conversaciones resueltas',
            'live_queue_total' => 'Cola activa',
            'queue_window_open' => 'Ventana 24h abierta',
            'queue_needs_template' => 'Requiere plantilla',
            'queue_awaiting_template_reply' => 'Esperando respuesta a plantilla',
            'sla_assignments_rate' => 'SLA asignación (%)',
            'handoff_transfers' => 'Transferencias',
            'sigcenter_bookings_created' => 'Citas Sigcenter creadas desde WhatsApp',
            'sigcenter_booking_patients' => 'Pacientes agendados desde WhatsApp',
            'sigcenter_booking_failures' => 'Citas Sigcenter fallidas desde WhatsApp',
        ] as $key => $label) {
            $rows[] = ['summary', $label, $summary[$key] ?? null, null];
        }

        $rows[] = ['breakdown', 'Atención humana por agente', null, null];
        foreach (($breakdowns['human_attention_by_agent'] ?? []) as $row) {
            $rows[] = [
                'human_attention_by_agent',
                (string) ($row['agent_name'] ?? ''),
                (int) ($row['attended_conversations'] ?? 0),
                isset($row['avg_first_response_minutes']) ? 'Promedio ' . $row['avg_first_response_minutes'] . ' min' : null,
            ];
        }

        $rows[] = ['breakdown', 'Handoffs por equipo', null, null];
        foreach (($breakdowns['handoffs_by_role'] ?? []) as $row) {
            $rows[] = [
                'handoffs_by_role',
                (string) ($row['role_name'] ?? ''),
                (int) ($row['total'] ?? 0),
                'Cola ' . ((int) ($row['queued'] ?? 0)) . ' · Asignadas ' . ((int) ($row['assigned'] ?? 0)) . ' · Resueltas ' . ((int) ($row['resolved'] ?? 0)),
            ];
        }

        $rows[] = ['breakdown', 'Carga por agente', null, null];
        foreach (($breakdowns['handoffs_by_agent'] ?? []) as $row) {
            $rows[] = [
                'handoffs_by_agent',
                (string) ($row['agent_name'] ?? ''),
                (int) ($row['assigned_count'] ?? 0),
                'Activas ' . ((int) ($row['active_count'] ?? 0)) . ' · Resueltas ' . ((int) ($row['resolved_count'] ?? 0)),
            ];
        }

        return $rows;
    }

    private function resolveSlaTargetMinutes(?int $provided): int
    {
        $value = $provided ?? self::DEFAULT_SLA_TARGET_MINUTES;
        if ($value <= 0) {
            $value = self::DEFAULT_SLA_TARGET_MINUTES;
        }

        return min(1440, $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationAnalytics(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        if (!Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_messages')) {
            return [
                'summary' => [
                    'total_conversations' => 0,
                    'conversations_from_ads' => 0,
                    'conversations_organic' => 0,
                    'conversations_outbound_started' => 0,
                    'identified_conversations' => 0,
                    'booked_conversations' => 0,
                    'handoff_conversations' => 0,
                    'new_patients' => 0,
                    'returning_patients' => 0,
                    'reactivated_patients' => 0,
                    'avg_lead_score' => 0.0,
                    'high_value_leads' => 0,
                    'medium_value_leads' => 0,
                    'low_value_leads' => 0,
                    'captacion_conversations' => 0,
                    'operacion_conversations' => 0,
                    'seguimiento_clinico_conversations' => 0,
                    'reactivacion_conversations' => 0,
                    'identification_rate' => 0.0,
                    'booking_rate' => 0.0,
                    'handoff_rate' => 0.0,
                ],
                'lifecycle' => [],
                'sources' => [],
                'funnel' => [],
                'outcomes' => [],
                'intents' => [],
                'conversation_types' => [],
                'segments' => [],
                'lead_scores' => [],
                'frictions' => [],
                'insights' => [],
                'ads' => [],
            ];
        }

        $base = $this->conversationAnalyticsBaseSubquery($fromSql, $toSql, $roleId, $agentId, 'analytics');
        $totals = $this->selectOne('SELECT COUNT(*) AS total FROM (' . $base['sql'] . ') analytics_base', $base['params']);
        $total = (int) ($totals->total ?? 0);

        return [
            'summary' => $this->conversationAnalyticsSummary($base),
            'lifecycle' => $this->conversationLifecycleBreakdown($base, $total),
            'sources' => $this->conversationSourcesBreakdown($base, $total),
            'funnel' => $this->conversationFunnel($base, $total),
            'outcomes' => $this->conversationOutcomesBreakdown($base, $total),
            'intents' => $this->conversationIntentsBreakdown($base, $total),
            'conversation_types' => $this->conversationTypesBreakdown($base, $total),
            'segments' => $this->conversationPatientSegmentsBreakdown($base, $total),
            'lead_scores' => $this->conversationLeadScoreBreakdown($base, $total),
            'frictions' => $this->conversationFrictionBreakdown($base, $total),
            'insights' => $this->conversationAutomatedInsights($base, $total),
            'ads' => $this->adsPerformanceBreakdown($base),
        ];
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<string, int|float>
     */
    private function conversationAnalyticsSummary(array $base): array
    {
        $row = $this->selectOne(
            'SELECT
                COUNT(*) AS total_conversations,
                SUM(CASE WHEN source_category = "ad" THEN 1 ELSE 0 END) AS conversations_from_ads,
                SUM(CASE WHEN source_category = "organic_direct" THEN 1 ELSE 0 END) AS conversations_organic,
                SUM(CASE WHEN source_category = "campaign_outbound" THEN 1 ELSE 0 END) AS conversations_outbound_started,
                SUM(is_identified) AS identified_conversations,
                SUM(has_booking) AS booked_conversations,
                SUM(has_handoff) AS handoff_conversations,
                SUM(CASE WHEN patient_segment = "new_patient" THEN 1 ELSE 0 END) AS new_patients,
                SUM(CASE WHEN patient_segment = "returning_patient" THEN 1 ELSE 0 END) AS returning_patients,
                SUM(CASE WHEN patient_segment = "reactivated_patient" THEN 1 ELSE 0 END) AS reactivated_patients,
                AVG(lead_score) AS avg_lead_score,
                SUM(CASE WHEN lead_score_bucket = "high" THEN 1 ELSE 0 END) AS high_value_leads,
                SUM(CASE WHEN lead_score_bucket = "medium" THEN 1 ELSE 0 END) AS medium_value_leads,
                SUM(CASE WHEN lead_score_bucket = "low" THEN 1 ELSE 0 END) AS low_value_leads,
                SUM(CASE WHEN lifecycle_category = "captacion" THEN 1 ELSE 0 END) AS captacion_conversations,
                SUM(CASE WHEN lifecycle_category = "operacion" THEN 1 ELSE 0 END) AS operacion_conversations,
                SUM(CASE WHEN lifecycle_category = "seguimiento_clinico" THEN 1 ELSE 0 END) AS seguimiento_clinico_conversations,
                SUM(CASE WHEN lifecycle_category = "reactivacion" THEN 1 ELSE 0 END) AS reactivacion_conversations
             FROM (' . $base['sql'] . ') analytics_base',
            $base['params']
        );

        $total = (int) ($row->total_conversations ?? 0);
        $identified = (int) ($row->identified_conversations ?? 0);
        $booked = (int) ($row->booked_conversations ?? 0);
        $handoff = (int) ($row->handoff_conversations ?? 0);

        return [
            'total_conversations' => $total,
            'conversations_from_ads' => (int) ($row->conversations_from_ads ?? 0),
            'conversations_organic' => (int) ($row->conversations_organic ?? 0),
            'conversations_outbound_started' => (int) ($row->conversations_outbound_started ?? 0),
            'identified_conversations' => $identified,
            'booked_conversations' => $booked,
            'handoff_conversations' => $handoff,
            'new_patients' => (int) ($row->new_patients ?? 0),
            'returning_patients' => (int) ($row->returning_patients ?? 0),
            'reactivated_patients' => (int) ($row->reactivated_patients ?? 0),
            'avg_lead_score' => round((float) ($row->avg_lead_score ?? 0), 1),
            'high_value_leads' => (int) ($row->high_value_leads ?? 0),
            'medium_value_leads' => (int) ($row->medium_value_leads ?? 0),
            'low_value_leads' => (int) ($row->low_value_leads ?? 0),
            'captacion_conversations' => (int) ($row->captacion_conversations ?? 0),
            'operacion_conversations' => (int) ($row->operacion_conversations ?? 0),
            'seguimiento_clinico_conversations' => (int) ($row->seguimiento_clinico_conversations ?? 0),
            'reactivacion_conversations' => (int) ($row->reactivacion_conversations ?? 0),
            'identification_rate' => $total > 0 ? round(($identified / $total) * 100, 1) : 0.0,
            'booking_rate' => $total > 0 ? round(($booked / $total) * 100, 1) : 0.0,
            'handoff_rate' => $total > 0 ? round(($handoff / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationLifecycleBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT *
             FROM (
                SELECT
                    COALESCE(NULLIF(lifecycle_category, ""), "unknown") AS lifecycle_category,
                    COUNT(*) AS total,
                    SUM(is_identified) AS identified,
                    SUM(has_booking) AS bookings,
                    SUM(has_handoff) AS handoffs
                FROM (' . $base['sql'] . ') analytics_base
                GROUP BY COALESCE(NULLIF(lifecycle_category, ""), "unknown")
             ) lifecycle_breakdown
             ORDER BY
                CASE lifecycle_breakdown.lifecycle_category
                    WHEN "captacion" THEN 1
                    WHEN "operacion" THEN 2
                    WHEN "seguimiento_clinico" THEN 3
                    WHEN "reactivacion" THEN 4
                    ELSE 5
                END',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $category = (string) ($row->lifecycle_category ?? 'unknown');
            $value = (int) ($row->total ?? 0);
            $bookings = (int) ($row->bookings ?? 0);

            return [
                'lifecycle_category' => $category,
                'lifecycle_label' => $this->lifecycleCategoryLabel($category),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
                'identified' => (int) ($row->identified ?? 0),
                'bookings' => $bookings,
                'handoffs' => (int) ($row->handoffs ?? 0),
                'booking_rate' => $value > 0 ? round(($bookings / $value) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationSourcesBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT
                source_category,
                COUNT(*) AS total,
                SUM(is_identified) AS identified,
                SUM(has_booking) AS bookings,
                SUM(has_handoff) AS handoffs
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY source_category
             ORDER BY total DESC, source_category ASC',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $source = (string) ($row->source_category ?? 'unknown');
            $sourceTotal = (int) ($row->total ?? 0);
            $identified = (int) ($row->identified ?? 0);
            $bookings = (int) ($row->bookings ?? 0);
            $handoffs = (int) ($row->handoffs ?? 0);

            return [
                'source_category' => $source,
                'source_label' => $this->sourceCategoryLabel($source),
                'total' => $sourceTotal,
                'share' => $total > 0 ? round(($sourceTotal / $total) * 100, 1) : 0.0,
                'identified' => $identified,
                'bookings' => $bookings,
                'handoffs' => $handoffs,
                'booking_rate' => $sourceTotal > 0 ? round(($bookings / $sourceTotal) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationFunnel(array $base, int $total): array
    {
        $row = $this->selectOne(
            'SELECT
                COUNT(*) AS started,
                SUM(has_consent) AS consented,
                SUM(is_identified) AS identified,
                SUM(entered_scheduling) AS scheduled,
                SUM(reached_confirmation) AS confirmed,
                SUM(has_booking) AS booked
             FROM (' . $base['sql'] . ') analytics_base',
            $base['params']
        );

        $steps = [
            ['key' => 'started', 'label' => 'Iniciaron conversación', 'value' => (int) ($row->started ?? 0)],
            ['key' => 'consented', 'label' => 'Dieron consentimiento', 'value' => (int) ($row->consented ?? 0)],
            ['key' => 'identified', 'label' => 'Paciente identificado', 'value' => (int) ($row->identified ?? 0)],
            ['key' => 'scheduled', 'label' => 'Entraron a agendamiento', 'value' => (int) ($row->scheduled ?? 0)],
            ['key' => 'confirmed', 'label' => 'Llegaron a confirmación', 'value' => (int) ($row->confirmed ?? 0)],
            ['key' => 'booked', 'label' => 'Cita creada', 'value' => (int) ($row->booked ?? 0)],
        ];

        $previous = $total > 0 ? $total : 0;
        foreach ($steps as $index => $step) {
            $value = (int) $step['value'];
            $steps[$index]['rate_from_start'] = $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
            $steps[$index]['rate_to_next'] = $previous > 0 ? round(($value / $previous) * 100, 1) : 0.0;
            $previous = $value > 0 ? $value : 0;
        }

        return $steps;
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationOutcomesBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT
                outcome_category,
                COUNT(*) AS total
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY outcome_category
             ORDER BY total DESC, outcome_category ASC',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $category = (string) ($row->outcome_category ?? 'unknown');
            $value = (int) ($row->total ?? 0);

            return [
                'outcome_category' => $category,
                'outcome_label' => $this->outcomeCategoryLabel($category),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationIntentsBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT
                COALESCE(NULLIF(initial_intent, ""), "unknown") AS initial_intent,
                COUNT(*) AS total,
                SUM(has_booking) AS bookings,
                SUM(has_handoff) AS handoffs
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY COALESCE(NULLIF(initial_intent, ""), "unknown")
             ORDER BY total DESC, initial_intent ASC',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $intent = (string) ($row->initial_intent ?? 'unknown');
            $value = (int) ($row->total ?? 0);
            $bookings = (int) ($row->bookings ?? 0);

            return [
                'initial_intent' => $intent,
                'intent_label' => $this->initialIntentLabel($intent),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
                'bookings' => $bookings,
                'handoffs' => (int) ($row->handoffs ?? 0),
                'booking_rate' => $value > 0 ? round(($bookings / $value) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationPatientSegmentsBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT
                COALESCE(NULLIF(patient_segment, ""), "unknown") AS patient_segment,
                COUNT(*) AS total,
                SUM(is_identified) AS identified,
                SUM(has_booking) AS bookings
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY COALESCE(NULLIF(patient_segment, ""), "unknown")
             ORDER BY total DESC, patient_segment ASC',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $segment = (string) ($row->patient_segment ?? 'unknown');
            $value = (int) ($row->total ?? 0);
            $bookings = (int) ($row->bookings ?? 0);

            return [
                'patient_segment' => $segment,
                'segment_label' => $this->patientSegmentLabel($segment),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
                'identified' => (int) ($row->identified ?? 0),
                'bookings' => $bookings,
                'booking_rate' => $value > 0 ? round(($bookings / $value) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationTypesBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT
                COALESCE(NULLIF(conversation_type, ""), "unknown") AS conversation_type,
                COUNT(*) AS total,
                SUM(has_booking) AS bookings,
                SUM(has_handoff) AS handoffs
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY COALESCE(NULLIF(conversation_type, ""), "unknown")
             ORDER BY total DESC, conversation_type ASC',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $type = (string) ($row->conversation_type ?? 'unknown');
            $value = (int) ($row->total ?? 0);
            $bookings = (int) ($row->bookings ?? 0);

            return [
                'conversation_type' => $type,
                'type_label' => $this->conversationTypeLabel($type),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
                'bookings' => $bookings,
                'handoffs' => (int) ($row->handoffs ?? 0),
                'booking_rate' => $value > 0 ? round(($bookings / $value) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationLeadScoreBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT *
             FROM (
                SELECT
                    COALESCE(NULLIF(lead_score_bucket, ""), "low") AS lead_score_bucket,
                    COUNT(*) AS total,
                    ROUND(AVG(lead_score), 1) AS avg_score,
                    SUM(has_booking) AS bookings
                FROM (' . $base['sql'] . ') analytics_base
                GROUP BY COALESCE(NULLIF(lead_score_bucket, ""), "low")
             ) score_breakdown
             ORDER BY
                CASE score_breakdown.lead_score_bucket
                    WHEN "high" THEN 1
                    WHEN "medium" THEN 2
                    WHEN "low" THEN 3
                    ELSE 4
                END',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $bucket = (string) ($row->lead_score_bucket ?? 'low');
            $value = (int) ($row->total ?? 0);
            $bookings = (int) ($row->bookings ?? 0);

            return [
                'lead_score_bucket' => $bucket,
                'bucket_label' => $this->leadScoreBucketLabel($bucket),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
                'avg_score' => round((float) ($row->avg_score ?? 0), 1),
                'bookings' => $bookings,
                'booking_rate' => $value > 0 ? round(($bookings / $value) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string>>
     */
    private function conversationFrictionBreakdown(array $base, int $total): array
    {
        $rows = DB::select(
            'SELECT
                COALESCE(NULLIF(friction_state, ""), "none") AS friction_state,
                COUNT(*) AS total
             FROM (' . $base['sql'] . ') analytics_base
             WHERE friction_state <> "none"
             GROUP BY COALESCE(NULLIF(friction_state, ""), "none")
             ORDER BY total DESC, friction_state ASC',
            $base['params']
        );

        return array_map(function ($row) use ($total): array {
            $state = (string) ($row->friction_state ?? 'none');
            $value = (int) ($row->total ?? 0);

            return [
                'friction_state' => $state,
                'friction_label' => $this->frictionStateLabel($state),
                'total' => $value,
                'share' => $total > 0 ? round(($value / $total) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, string>>
     */
    private function conversationAutomatedInsights(array $base, int $total): array
    {
        if ($total === 0) {
            return [];
        }

        $summary = $this->conversationAnalyticsSummary($base);
        $sources = $this->conversationSourcesBreakdown($base, $total);
        $intents = $this->conversationIntentsBreakdown($base, $total);
        $frictions = $this->conversationFrictionBreakdown($base, $total);
        $leadScores = $this->conversationLeadScoreBreakdown($base, $total);
        $lifecycle = $this->conversationLifecycleBreakdown($base, $total);
        $ads = $this->adsPerformanceBreakdown($base);

        $insights = [];

        $topSource = $sources[0] ?? null;
        if (is_array($topSource)) {
            $insights[] = [
                'title' => 'Fuente dominante',
                'body' => $topSource['source_label'] . ' concentra ' . $topSource['share'] . '% de las conversaciones nuevas del periodo.',
            ];
        }

        $topLifecycle = $lifecycle[0] ?? null;
        if (is_array($topLifecycle)) {
            $insights[] = [
                'title' => 'Mix del canal',
                'body' => $topLifecycle['lifecycle_label'] . ' lidera con ' . $topLifecycle['share'] . '% del canal y conversión a cita de ' . $topLifecycle['booking_rate'] . '%.',
            ];
        }

        $topIntent = $intents[0] ?? null;
        if (is_array($topIntent)) {
            $insights[] = [
                'title' => 'Intención más frecuente',
                'body' => $topIntent['intent_label'] . ' representa ' . $topIntent['share'] . '% de la demanda nueva y convierte ' . $topIntent['booking_rate'] . '% a cita.',
            ];
        }

        $topFriction = $frictions[0] ?? null;
        if (is_array($topFriction)) {
            $insights[] = [
                'title' => 'Mayor fricción',
                'body' => $topFriction['friction_label'] . ' concentra ' . $topFriction['share'] . '% del total analizado. Ese es el primer punto a corregir en flujo u operación.',
            ];
        }

        $topScore = $leadScores[0] ?? null;
        if (is_array($topScore)) {
            $insights[] = [
                'title' => 'Calidad de lead',
                'body' => 'Promedio de score ' . ($summary['avg_lead_score'] ?? 0) . '. El bucket ' . $topScore['bucket_label'] . ' agrupa ' . $topScore['share'] . '% de conversaciones.',
            ];
        }

        $topAd = $ads[0] ?? null;
        if (is_array($topAd) && (int) ($summary['conversations_from_ads'] ?? 0) > 0) {
            $insights[] = [
                'title' => 'Ad más rentable',
                'body' => $topAd['headline'] . ' lidera Ads con ' . $topAd['bookings'] . ' citas y conversión de ' . $topAd['booking_rate'] . '%.',
            ];
        }

        return array_slice($insights, 0, 5);
    }

    /**
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, int|float|string|null>>
     */
    private function adsPerformanceBreakdown(array $base): array
    {
        $rows = DB::select(
            'SELECT
                NULLIF(referral_source_id, "") AS source_id,
                NULLIF(referral_headline, "") AS headline,
                NULLIF(referral_media_type, "") AS media_type,
                NULLIF(a.platform, "") AS platform,
                COUNT(*) AS conversations,
                SUM(is_identified) AS identified,
                SUM(has_booking) AS bookings,
                SUM(has_handoff) AS handoffs
             FROM (' . $base['sql'] . ') analytics_base
             LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = analytics_base.conversation_id
             WHERE analytics_base.source_category = "ad"
             GROUP BY referral_source_id, referral_headline, referral_media_type, a.platform
             ORDER BY bookings DESC, conversations DESC, referral_source_id ASC
             LIMIT 50',
            $base['params']
        );

        return array_map(static function ($row): array {
            $conversations = (int) ($row->conversations ?? 0);
            $bookings = (int) ($row->bookings ?? 0);

            return [
                'source_id' => $row->source_id !== null ? (string) $row->source_id : null,
                'headline' => $row->headline !== null ? (string) $row->headline : 'Sin headline',
                'media_type' => $row->media_type !== null ? (string) $row->media_type : 'n/d',
                'platform' => $row->platform !== null ? (string) $row->platform : null,
                'platform_label' => match ($row->platform ?? null) {
                    'facebook'  => 'Facebook',
                    'instagram' => 'Instagram',
                    'whatsapp'  => 'WhatsApp',
                    default     => 'Desconocido',
                },
                'conversations' => $conversations,
                'identified' => (int) ($row->identified ?? 0),
                'bookings' => $bookings,
                'handoffs' => (int) ($row->handoffs ?? 0),
                'booking_rate' => $conversations > 0 ? round(($bookings / $conversations) * 100, 1) : 0.0,
            ];
        }, $rows);
    }

    /**
     * @return array{sql:string,params:array<int|string,mixed>}
     */
    private function conversationAnalyticsBaseSubquery(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, $scope . '_conversation');
        $where = 'c.created_at >= ? AND c.created_at < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $where .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $referralType = $this->jsonTextExtract('mi.raw_payload', '$.referral.source_type');
        $referralSourceId = $this->jsonTextExtract('mi.raw_payload', '$.referral.source_id');
        $referralHeadline = $this->jsonTextExtract('mi.raw_payload', '$.referral.headline');
        $referralMediaType = $this->jsonTextExtract('mi.raw_payload', '$.referral.media_type');
        $hasAttributionTable = Schema::hasTable('whatsapp_conversation_attributions');
        $sessionState = $this->jsonTextExtract('s.context', '$.state');
        $sessionConsent = $this->jsonBooleanIsTrue('s.context', '$.consent');
        $attributionJoin = $hasAttributionTable
            ? 'LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category, NULL AS source_type, NULL AS source_id, NULL AS headline, NULL AS media_type, NULL AS initial_intent, NULL AS patient_segment) a ON 1 = 0';
        $sessionJoin = Schema::hasTable('whatsapp_autoresponder_sessions')
            ? 'LEFT JOIN whatsapp_autoresponder_sessions s ON s.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS context) s ON 1 = 0';
        $bookingJoin = Schema::hasTable('whatsapp_sigcenter_bookings')
            ? 'LEFT JOIN (
                    SELECT conversation_id, COUNT(*) AS created_bookings
                    FROM whatsapp_sigcenter_bookings
                    WHERE status = "created"
                    GROUP BY conversation_id
                ) b ON b.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, 0 AS created_bookings) b ON b.conversation_id = c.id';
        $handoffJoin = Schema::hasTable('whatsapp_handoffs')
            ? 'LEFT JOIN (
                    SELECT conversation_id, 1 AS had_handoff
                    FROM whatsapp_handoffs
                    GROUP BY conversation_id
                ) h ON h.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, 0 AS had_handoff) h ON h.conversation_id = c.id';

        $sql = 'SELECT
                    c.id AS conversation_id,
                    c.wa_number,
                    c.created_at AS conversation_created_at,
                    c.patient_hc_number,
                    c.needs_human,
                    c.assigned_user_id,
                    c.handoff_requested_at,
                    ma.direction AS first_message_direction,
                    mi.message_timestamp AS first_inbound_at,
                    COALESCE(NULLIF(a.source_type, ""), ' . $referralType . ') AS referral_source_type,
                    COALESCE(NULLIF(a.source_id, ""), ' . $referralSourceId . ') AS referral_source_id,
                    COALESCE(NULLIF(a.headline, ""), ' . $referralHeadline . ') AS referral_headline,
                    COALESCE(NULLIF(a.media_type, ""), ' . $referralMediaType . ') AS referral_media_type,
                    COALESCE(NULLIF(a.initial_intent, ""), "unknown") AS initial_intent,
                    COALESCE(NULLIF(a.conversation_type, ""), "unknown") AS conversation_type,
                    COALESCE(NULLIF(a.patient_segment, ""), "unknown") AS patient_segment,
                    ' . $sessionState . ' AS session_state,
                    CASE WHEN ' . $sessionConsent . ' THEN 1 ELSE 0 END AS has_consent,
                    CASE WHEN COALESCE(NULLIF(TRIM(c.patient_hc_number), ""), "") <> "" THEN 1 ELSE 0 END AS is_identified,
                    CASE WHEN (
                        LOWER(COALESCE(' . $sessionState . ', "")) LIKE "agenda\_%"
                        OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.trabajador_id') . '), ""), "") <> ""
                        OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.sede_id') . '), ""), "") <> ""
                        OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.procedimiento_id') . '), ""), "") <> ""
                        OR COALESCE(b.created_bookings, 0) > 0
                    ) THEN 1 ELSE 0 END AS entered_scheduling,
                    CASE WHEN (
                        LOWER(COALESCE(' . $sessionState . ', "")) = "agenda_confirmar_cita"
                        OR COALESCE(b.created_bookings, 0) > 0
                    ) THEN 1 ELSE 0 END AS reached_confirmation,
                    CASE WHEN COALESCE(b.created_bookings, 0) > 0 THEN 1 ELSE 0 END AS has_booking,
                    CASE WHEN COALESCE(h.had_handoff, 0) > 0 OR c.handoff_requested_at IS NOT NULL THEN 1 ELSE 0 END AS has_handoff,
                    (
                        (CASE WHEN ' . $sessionConsent . ' THEN 10 ELSE 0 END) +
                        (CASE WHEN COALESCE(NULLIF(TRIM(c.patient_hc_number), ""), "") <> "" THEN 20 ELSE 0 END) +
                        (CASE WHEN (
                            LOWER(COALESCE(' . $sessionState . ', "")) LIKE "agenda\_%"
                            OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.trabajador_id') . '), ""), "") <> ""
                            OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.sede_id') . '), ""), "") <> ""
                            OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.procedimiento_id') . '), ""), "") <> ""
                            OR COALESCE(b.created_bookings, 0) > 0
                        ) THEN 20 ELSE 0 END) +
                        (CASE WHEN (
                            LOWER(COALESCE(' . $sessionState . ', "")) = "agenda_confirmar_cita"
                            OR COALESCE(b.created_bookings, 0) > 0
                        ) THEN 20 ELSE 0 END) +
                        (CASE WHEN COALESCE(b.created_bookings, 0) > 0 THEN 30 ELSE 0 END)
                    ) AS lead_score,
                    CASE
                        WHEN (
                            (CASE WHEN ' . $sessionConsent . ' THEN 10 ELSE 0 END) +
                            (CASE WHEN COALESCE(NULLIF(TRIM(c.patient_hc_number), ""), "") <> "" THEN 20 ELSE 0 END) +
                            (CASE WHEN (
                                LOWER(COALESCE(' . $sessionState . ', "")) LIKE "agenda\_%"
                                OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.trabajador_id') . '), ""), "") <> ""
                                OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.sede_id') . '), ""), "") <> ""
                                OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.procedimiento_id') . '), ""), "") <> ""
                                OR COALESCE(b.created_bookings, 0) > 0
                            ) THEN 20 ELSE 0 END) +
                            (CASE WHEN (
                                LOWER(COALESCE(' . $sessionState . ', "")) = "agenda_confirmar_cita"
                                OR COALESCE(b.created_bookings, 0) > 0
                            ) THEN 20 ELSE 0 END) +
                            (CASE WHEN COALESCE(b.created_bookings, 0) > 0 THEN 30 ELSE 0 END)
                        ) >= 70 THEN "high"
                        WHEN (
                            (CASE WHEN ' . $sessionConsent . ' THEN 10 ELSE 0 END) +
                            (CASE WHEN COALESCE(NULLIF(TRIM(c.patient_hc_number), ""), "") <> "" THEN 20 ELSE 0 END) +
                            (CASE WHEN (
                                LOWER(COALESCE(' . $sessionState . ', "")) LIKE "agenda\_%"
                                OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.trabajador_id') . '), ""), "") <> ""
                                OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.sede_id') . '), ""), "") <> ""
                                OR COALESCE(NULLIF(TRIM(' . $this->jsonTextExtract('s.context', '$.procedimiento_id') . '), ""), "") <> ""
                                OR COALESCE(b.created_bookings, 0) > 0
                            ) THEN 20 ELSE 0 END) +
                            (CASE WHEN (
                                LOWER(COALESCE(' . $sessionState . ', "")) = "agenda_confirmar_cita"
                                OR COALESCE(b.created_bookings, 0) > 0
                            ) THEN 20 ELSE 0 END) +
                            (CASE WHEN COALESCE(b.created_bookings, 0) > 0 THEN 30 ELSE 0 END)
                        ) >= 35 THEN "medium"
                        ELSE "low"
                    END AS lead_score_bucket,
                    CASE
                        WHEN COALESCE(NULLIF(a.source_category, ""), "") <> "" THEN a.source_category
                        WHEN LOWER(COALESCE(' . $referralType . ', "")) = "ad" THEN "ad"
                        WHEN COALESCE(ma.direction, "") = "outbound" THEN "outbound_initiated"
                        WHEN mi.message_timestamp IS NOT NULL THEN "organic_inbound"
                        ELSE "unknown"
                    END AS source_category,
                    CASE
                        WHEN COALESCE(b.created_bookings, 0) > 0 THEN "none"
                        WHEN COALESCE(h.had_handoff, 0) > 0 OR c.handoff_requested_at IS NOT NULL THEN "handoff_required"
                        WHEN LOWER(COALESCE(' . $sessionState . ', "")) = "consentimiento_pendiente" THEN "consent_pending"
                        WHEN LOWER(COALESCE(' . $sessionState . ', "")) = "esperando_cedula" THEN "waiting_identifier"
                        WHEN LOWER(COALESCE(' . $sessionState . ', "")) = "agenda_confirmar_cita" THEN "confirmation_pending"
                        WHEN LOWER(COALESCE(' . $sessionState . ', "")) LIKE "agenda\_esperando\_%" THEN LOWER(COALESCE(' . $sessionState . ', ""))
                        WHEN c.needs_human = 1 THEN "open_unresolved"
                        ELSE "none"
                    END AS friction_state,
                    CASE
                        WHEN COALESCE(NULLIF(a.source_category, ""), "") IN ("ad", "organic_direct")
                            AND COALESCE(NULLIF(a.patient_segment, ""), "unknown") = "new_patient" THEN "captacion"
                        WHEN COALESCE(NULLIF(a.patient_segment, ""), "unknown") = "reactivated_patient"
                            OR COALESCE(NULLIF(a.conversation_type, ""), "unknown") = "patient_return" THEN "reactivacion"
                        WHEN COALESCE(NULLIF(a.source_category, ""), "") IN ("post_consultation", "post_surgery")
                            OR COALESCE(NULLIF(a.conversation_type, ""), "unknown") = "post_op_followup" THEN "seguimiento_clinico"
                        WHEN COALESCE(NULLIF(a.source_category, ""), "") IN ("support_operational", "campaign_outbound")
                            OR COALESCE(NULLIF(a.conversation_type, ""), "unknown") IN ("reschedule", "cancel", "results", "human_help", "campaign_response") THEN "operacion"
                        WHEN COALESCE(NULLIF(a.source_category, ""), "") = "patient_return" THEN "reactivacion"
                        ELSE "captacion"
                    END AS lifecycle_category,
                    CASE
                        WHEN COALESCE(b.created_bookings, 0) > 0 THEN "booking_created"
                        WHEN COALESCE(h.had_handoff, 0) > 0 OR c.handoff_requested_at IS NOT NULL THEN "handoff_human"
                        WHEN c.needs_human = 0 AND COALESCE(NULLIF(TRIM(c.patient_hc_number), ""), "") <> "" THEN "resolved_identified"
                        WHEN c.needs_human = 0 THEN "resolved_without_identification"
                        ELSE "open_or_abandoned"
                    END AS outcome_category
                FROM whatsapp_conversations c
                LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                LEFT JOIN (
                    SELECT m.conversation_id, MIN(m.id) AS first_message_id
                    FROM whatsapp_messages m
                    GROUP BY m.conversation_id
                ) first_any ON first_any.conversation_id = c.id
                LEFT JOIN whatsapp_messages ma ON ma.id = first_any.first_message_id
                LEFT JOIN (
                    SELECT m.conversation_id, MIN(m.id) AS first_inbound_id
                    FROM whatsapp_messages m
                    WHERE m.direction = "inbound"
                    GROUP BY m.conversation_id
                ) first_inbound ON first_inbound.conversation_id = c.id
                LEFT JOIN whatsapp_messages mi ON mi.id = first_inbound.first_inbound_id
                ' . $attributionJoin . '
                ' . $sessionJoin . '
                ' . $bookingJoin . '
                ' . $handoffJoin . '
                WHERE ' . $where;

        return ['sql' => $sql, 'params' => $params];
    }

    private function jsonTextExtract(string $column, string $path): string
    {
        if ($this->isSqlite()) {
            return 'COALESCE(CAST(json_extract(' . $column . ', ' . $this->stringLiteral($path) . ') AS TEXT), "")';
        }

        return 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(' . $column . ', ' . $this->stringLiteral($path) . ')), "")';
    }

    private function jsonBooleanIsTrue(string $column, string $path): string
    {
        $extract = $this->jsonTextExtract($column, $path);
        return 'LOWER(COALESCE(' . $extract . ', "")) IN ("1", "true")';
    }

    private function sourceCategoryLabel(string $category): string
    {
        return match ($category) {
            'ad' => 'Ads',
            'organic_direct' => 'Orgánico directo',
            'campaign_outbound' => 'Campaña saliente',
            'patient_return' => 'Paciente de retorno',
            'post_consultation' => 'Post consulta',
            'post_surgery' => 'Post cirugía',
            'support_operational' => 'Soporte operativo',
            default => 'Sin clasificar',
        };
    }

    private function lifecycleCategoryLabel(string $category): string
    {
        return match ($category) {
            'captacion' => 'Captación',
            'operacion' => 'Operación',
            'seguimiento_clinico' => 'Seguimiento clínico',
            'reactivacion' => 'Reactivación',
            default => 'Sin clasificar',
        };
    }

    private function outcomeCategoryLabel(string $category): string
    {
        return match ($category) {
            'booking_created' => 'Cita creada',
            'handoff_human' => 'Handoff a humano',
            'resolved_identified' => 'Resuelto con paciente identificado',
            'resolved_without_identification' => 'Resuelto sin identificar',
            'open_or_abandoned' => 'Abierta o abandonada',
            default => 'Sin clasificar',
        };
    }

    private function initialIntentLabel(string $intent): string
    {
        return match ($intent) {
            'booking' => 'Agendar cita',
            'reschedule' => 'Reagendar',
            'cancel' => 'Cancelar cita',
            'pricing' => 'Precios',
            'hours_location' => 'Horarios o ubicación',
            'results' => 'Resultados',
            'human_help' => 'Ayuda humana',
            'general_info' => 'Información general',
            'outbound_followup' => 'Seguimiento saliente',
            'other' => 'Otra intención',
            default => 'Sin clasificar',
        };
    }

    private function patientSegmentLabel(string $segment): string
    {
        return match ($segment) {
            'new_patient' => 'Paciente nuevo',
            'returning_patient' => 'Paciente recurrente',
            'reactivated_patient' => 'Paciente reactivado',
            default => 'Sin clasificar',
        };
    }

    private function conversationTypeLabel(string $type): string
    {
        return match ($type) {
            'booking' => 'Agendamiento',
            'reschedule' => 'Reagendamiento',
            'cancel' => 'Cancelación',
            'faq' => 'FAQ / información',
            'results' => 'Resultados',
            'human_help' => 'Ayuda humana',
            'post_op_followup' => 'Seguimiento postoperatorio',
            'campaign_response' => 'Respuesta a campaña',
            'patient_return' => 'Retorno de paciente',
            'other' => 'Otro',
            default => 'Sin clasificar',
        };
    }

    private function leadScoreBucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'high' => 'Alto valor',
            'medium' => 'Valor medio',
            default => 'Valor bajo',
        };
    }

    private function frictionStateLabel(string $state): string
    {
        return match ($state) {
            'consent_pending' => 'Esperando consentimiento',
            'waiting_identifier' => 'Esperando cédula',
            'confirmation_pending' => 'Pendiente de confirmación',
            'handoff_required' => 'Dependencia de humano',
            'agenda_esperando_subespecialidad' => 'Esperando especialidad',
            'agenda_esperando_medico' => 'Esperando médico',
            'agenda_esperando_sede' => 'Esperando sede',
            'agenda_esperando_procedimiento' => 'Esperando procedimiento',
            'agenda_esperando_dia' => 'Esperando día',
            'agenda_esperando_horario' => 'Esperando horario',
            'open_unresolved' => 'Abierta sin resolver',
            default => 'Sin fricción clasificada',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function humanAttentionSummary(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_attention');
        $reply = $this->humanReplySubquery($roleId, $agentId, 'human_attention');
        $handoffStart = $this->handoffStartSubquery($roleId, $agentId, 'human_attention');

        $sql = 'SELECT
                    inbound.conversation_id,
                    inbound.wa_number,
                    inbound.first_inbound_at,
                    inbound.last_inbound_at,
                    inbound.handoff_requested_at,
                    handoff.first_handoff_at,
                    human.first_human_reply_at
                FROM (' . $scope['sql'] . ') inbound
                LEFT JOIN (' . $handoffStart['sql'] . ') handoff
                    ON handoff.conversation_id = inbound.conversation_id
                LEFT JOIN (' . $reply['sql'] . ') human
                    ON human.conversation_id = inbound.conversation_id';

        $rows = DB::select($sql, array_merge(
            array_values($scope['params']),
            array_values($handoffStart['params']),
            array_values($reply['params'])
        ));
        $threshold24h = Carbon::now()->subHours(24);
        $peopleInboundSet = [];
        $peopleAttendedSet = [];
        $peopleLostSet = [];
        $attended = 0;
        $lost = 0;
        $lostWithHandoff = 0;
        $abandoned = 0;
        $abandonedWithHandoff = 0;
        $resolved = 0;
        $responseSeconds = [];

        foreach ($rows as $row) {
            $waNumber = (string) ($row->wa_number ?? '');
            if ($waNumber !== '') {
                $peopleInboundSet[$waNumber] = true;
            }

            $lastInbound = isset($row->last_inbound_at) ? Carbon::parse((string) $row->last_inbound_at) : null;
            $firstReply = isset($row->first_human_reply_at) ? Carbon::parse((string) $row->first_human_reply_at) : null;
            $handoffRequestedAt = isset($row->handoff_requested_at) ? Carbon::parse((string) $row->handoff_requested_at) : null;
            $firstHandoffAt = isset($row->first_handoff_at) ? Carbon::parse((string) $row->first_handoff_at) : null;
            $responseStart = $handoffRequestedAt ?? $firstHandoffAt;

            if ($firstReply !== null) {
                $attended++;
                if ($waNumber !== '') {
                    $peopleAttendedSet[$waNumber] = true;
                }
                if ($responseStart !== null && $firstReply->greaterThanOrEqualTo($responseStart)) {
                    $responseSeconds[] = $responseStart->diffInSeconds($firstReply);
                }
                if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
                    $resolved++;
                }
            } else {
                $lost++;
                if ($responseStart !== null) {
                    $lostWithHandoff++;
                }
                if ($waNumber !== '') {
                    $peopleLostSet[$waNumber] = true;
                }
                if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
                    $abandoned++;
                    if ($responseStart !== null) {
                        $abandonedWithHandoff++;
                    }
                }
            }
        }

        $peopleInbound = count($peopleInboundSet);
        $peopleAttended = count($peopleAttendedSet);
        $peopleLost = count($peopleLostSet);
        $avgSeconds = $responseSeconds !== [] ? array_sum($responseSeconds) / count($responseSeconds) : null;
        $medianSeconds = $this->median($responseSeconds);

        $intervalPeak = $this->peakOpenConversations($fromSql, $toSql, $roleId, $agentId);

        return [
            'people_inbound' => $peopleInbound,
            'inbound_conversations_human' => count($rows),
            'conversations_attended_human' => $attended,
            'people_attended_human' => $peopleAttended,
            'conversations_lost' => $lost,
            'people_lost' => $peopleLost,
            'conversations_lost_with_handoff' => $lostWithHandoff,
            'attention_rate' => $peopleInbound > 0 ? round(($peopleAttended / $peopleInbound) * 100, 2) : 0.0,
            'loss_rate' => $peopleInbound > 0 ? round(($peopleLost / $peopleInbound) * 100, 2) : 0.0,
            'conversations_abandoned' => $abandoned,
            'conversations_abandoned_with_handoff' => $abandonedWithHandoff,
            'abandonment_rate' => $peopleInbound > 0 ? round(($abandoned / $peopleInbound) * 100, 2) : 0.0,
            'conversations_resolved' => $resolved,
            'avg_first_human_response_seconds' => $avgSeconds !== null ? round($avgSeconds, 2) : null,
            'avg_first_human_response_minutes' => $avgSeconds !== null ? round($avgSeconds / 60, 2) : null,
            'median_first_human_response_seconds' => $medianSeconds !== null ? round($medianSeconds, 2) : null,
            'median_first_human_response_minutes' => $medianSeconds !== null ? round($medianSeconds / 60, 2) : null,
            'peak_open_conversations' => $intervalPeak['count'],
            'peak_open_at' => $intervalPeak['at'],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function closeReasonSummary(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $defaults = [
            'conversations_closed_resolved' => 0,
            'conversations_closed_followup' => 0,
            'conversations_closed_not_interested' => 0,
            'conversations_closed_no_response' => 0,
            'whatsapp_followup_leads_created' => 0,
        ];

        if (!Schema::hasColumn('whatsapp_conversations', 'close_reason')
            || !Schema::hasColumn('whatsapp_conversations', 'closed_at')
        ) {
            return $defaults;
        }

        $bindings = [$fromSql, $toSql];
        $userJoin = '';
        $userWhere = '';

        $hasClosedBy = Schema::hasColumn('whatsapp_conversations', 'closed_by_user_id');

        if ($agentId !== null && $agentId > 0 && $hasClosedBy) {
            $userWhere .= ' AND c.closed_by_user_id = ?';
            $bindings[] = $agentId;
        } elseif ($roleId !== null && $roleId > 0 && $hasClosedBy && Schema::hasTable('users')) {
            $userJoin = ' LEFT JOIN users u ON u.id = c.closed_by_user_id';
            $userWhere .= ' AND u.role_id = ?';
            $bindings[] = $roleId;
        }

        $row = DB::selectOne(
            'SELECT
                SUM(CASE WHEN c.close_reason = "resolved" THEN 1 ELSE 0 END) AS resolved,
                SUM(CASE WHEN c.close_reason = "followup_closed" THEN 1 ELSE 0 END) AS followup_closed,
                SUM(CASE WHEN c.close_reason = "not_interested" THEN 1 ELSE 0 END) AS not_interested,
                SUM(CASE WHEN c.close_reason = "no_response" THEN 1 ELSE 0 END) AS no_response
             FROM whatsapp_conversations c' . $userJoin . '
             WHERE c.closed_at >= ? AND c.closed_at < ?' . $userWhere,
            $bindings
        );

        $leadBindings = [$fromSql, $toSql];
        $leadJoin = '';
        $leadWhere = '';
        if (Schema::hasTable('whatsapp_leads')) {
            if ($agentId !== null && $agentId > 0) {
                $leadWhere .= ' AND wl.created_by_user_id = ?';
                $leadBindings[] = $agentId;
            } elseif ($roleId !== null && $roleId > 0 && Schema::hasTable('users')) {
                $leadJoin = ' LEFT JOIN users wu ON wu.id = wl.created_by_user_id';
                $leadWhere .= ' AND wu.role_id = ?';
                $leadBindings[] = $roleId;
            }

            $defaults['whatsapp_followup_leads_created'] = $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_leads wl' . $leadJoin . '
                 WHERE wl.created_at >= ? AND wl.created_at < ?' . $leadWhere,
                $leadBindings
            );
        }

        $defaults['conversations_closed_resolved'] = (int) ($row->resolved ?? 0);
        $defaults['conversations_closed_followup'] = (int) ($row->followup_closed ?? 0);
        $defaults['conversations_closed_not_interested'] = (int) ($row->not_interested ?? 0);
        $defaults['conversations_closed_no_response'] = (int) ($row->no_response ?? 0);

        return $defaults;
    }

    /**
     * Current operational inbox snapshot used by the WhatsApp chat dashboard.
     *
     * @return array<string, int>
     */
    private function operationalInboxSummary(?int $roleId, ?int $agentId): array
    {
        $defaults = [
            'operational_status_new' => 0,
            'operational_status_requires_attention' => 0,
            'operational_status_in_progress' => 0,
            'operational_status_waiting_patient' => 0,
            'operational_status_scheduled' => 0,
            'operational_status_resolved' => 0,
            'operational_status_closed_followup' => 0,
            'operational_status_closed_other' => 0,
            'priority_critical' => 0,
            'priority_high' => 0,
            'priority_normal' => 0,
            'priority_low' => 0,
            'my_active_chats' => 0,
            'limbo_unassigned' => 0,
            'limbo_assigned_inactive' => 0,
            'limbo_waiting_patient_overdue' => 0,
        ];

        if (!Schema::hasTable('whatsapp_conversations')) {
            return $defaults;
        }

        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, 'operational_inbox');
        $hasCloseReason = Schema::hasColumn('whatsapp_conversations', 'close_reason');
        $hasBookings = Schema::hasTable('whatsapp_sigcenter_bookings');
        $closedReasonSql = $hasCloseReason ? 'COALESCE(c.close_reason, "")' : '""';
        $scheduledSql = $hasBookings
            ? 'EXISTS (
                SELECT 1 FROM whatsapp_sigcenter_bookings wsb
                WHERE wsb.conversation_id = c.id
                  AND wsb.status IN ("created", "confirmed")
            )'
            : '0 = 1';

        $fourHoursAgo = Carbon::now()->subHours(4)->format('Y-m-d H:i:s');
        $twentyFourHoursAgo = Carbon::now()->subHours(24)->format('Y-m-d H:i:s');
        $params = [$fourHoursAgo, $twentyFourHoursAgo];
        $sql = 'SELECT
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "" AND NOT (' . $scheduledSql . ') THEN 1 ELSE 0 END) AS status_new,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL THEN 1 ELSE 0 END) AS status_requires_attention,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND c.last_message_direction = "inbound" THEN 1 ELSE 0 END) AS status_in_progress,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND (c.last_message_direction <> "inbound" OR c.last_message_direction IS NULL) THEN 1 ELSE 0 END) AS status_waiting_patient,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "" AND (' . $scheduledSql . ') THEN 1 ELSE 0 END) AS status_scheduled,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "resolved" THEN 1 ELSE 0 END) AS status_resolved,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "followup_closed" THEN 1 ELSE 0 END) AS status_closed_followup,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' IN ("not_interested", "no_response", "duplicate", "scheduled_elsewhere") THEN 1 ELSE 0 END) AS status_closed_other,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0 THEN 1 ELSE 0 END) AS priority_critical,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND c.last_message_direction = "inbound" THEN 1 ELSE 0 END) AS priority_high,
                    SUM(CASE WHEN c.needs_human = 1 AND NOT (c.assigned_user_id IS NULL AND c.unread_count > 0) AND NOT (c.assigned_user_id IS NOT NULL AND c.last_message_direction = "inbound") THEN 1 ELSE 0 END) AS priority_normal,
                    SUM(CASE WHEN c.needs_human = 0 THEN 1 ELSE 0 END) AS priority_low,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL THEN 1 ELSE 0 END) AS limbo_unassigned,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND COALESCE(c.last_message_at, c.updated_at, c.created_at) <= ? THEN 1 ELSE 0 END) AS limbo_assigned_inactive,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND (c.last_message_direction <> "inbound" OR c.last_message_direction IS NULL) AND COALESCE(c.last_message_at, c.updated_at, c.created_at) <= ? THEN 1 ELSE 0 END) AS limbo_waiting_patient_overdue
                FROM whatsapp_conversations c
                LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                WHERE 1 = 1';

        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $row = DB::selectOne($sql, $params) ?? (object) [];

        $defaults['operational_status_new'] = (int) ($row->status_new ?? 0);
        $defaults['operational_status_requires_attention'] = (int) ($row->status_requires_attention ?? 0);
        $defaults['operational_status_in_progress'] = (int) ($row->status_in_progress ?? 0);
        $defaults['operational_status_waiting_patient'] = (int) ($row->status_waiting_patient ?? 0);
        $defaults['operational_status_scheduled'] = (int) ($row->status_scheduled ?? 0);
        $defaults['operational_status_resolved'] = (int) ($row->status_resolved ?? 0);
        $defaults['operational_status_closed_followup'] = (int) ($row->status_closed_followup ?? 0);
        $defaults['operational_status_closed_other'] = (int) ($row->status_closed_other ?? 0);
        $defaults['priority_critical'] = (int) ($row->priority_critical ?? 0);
        $defaults['priority_high'] = (int) ($row->priority_high ?? 0);
        $defaults['priority_normal'] = (int) ($row->priority_normal ?? 0);
        $defaults['priority_low'] = (int) ($row->priority_low ?? 0);
        $defaults['limbo_unassigned'] = (int) ($row->limbo_unassigned ?? 0);
        $defaults['limbo_assigned_inactive'] = (int) ($row->limbo_assigned_inactive ?? 0);
        $defaults['limbo_waiting_patient_overdue'] = (int) ($row->limbo_waiting_patient_overdue ?? 0);

        if ($agentId !== null && $agentId > 0) {
            $defaults['my_active_chats'] = $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_conversations WHERE needs_human = 1 AND assigned_user_id = ?',
                [$agentId]
            );
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function queueSummary(?int $roleId, ?int $agentId): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'live_queue');
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $sql = 'SELECT
                    h.status,
                    h.assigned_until
                FROM whatsapp_handoffs h
                WHERE h.status IN ("queued", "assigned", "expired")';
        $params = [];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $rows = DB::select($sql, $params);
        $queued = 0;
        $assigned = 0;
        $overdue = 0;

        foreach ($rows as $row) {
            $status = (string) ($row->status ?? '');
            $assignedUntil = $row->assigned_until ?? null;

            if ($status === 'queued') {
                $queued++;
                continue;
            }

            if ($status !== 'assigned') {
                continue;
            }

            if ($assignedUntil === null || (string) $assignedUntil > $now) {
                $assigned++;
                continue;
            }

            $overdue++;
        }

        return [
            'live_queue_queued' => $queued,
            'live_queue_assigned' => $assigned,
            'live_queue_assigned_overdue' => $overdue,
            'live_queue_total' => $queued + $assigned + $overdue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationWindowSummary(?int $roleId, ?int $agentId): array
    {
        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, 'conversation_window');
        $threshold24h = Carbon::now()->subHours(24)->format('Y-m-d H:i:s');
        $sql = 'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN inbound.last_inbound_at IS NOT NULL
                               AND inbound.last_inbound_at >= ?
                             THEN 1 ELSE 0 END) AS window_open,
                    SUM(CASE WHEN inbound.last_inbound_at IS NULL
                               OR inbound.last_inbound_at < ?
                             THEN 1 ELSE 0 END) AS needs_template,
                    SUM(CASE WHEN (inbound.last_inbound_at IS NULL
                                    OR inbound.last_inbound_at < ?)
                               AND c.last_message_direction = "outbound"
                               AND c.last_message_type = "template"
                             THEN 1 ELSE 0 END) AS awaiting_template_reply
                FROM whatsapp_conversations c
                LEFT JOIN (
                    SELECT m.conversation_id, MAX(COALESCE(m.message_timestamp, m.created_at)) AS last_inbound_at
                    FROM whatsapp_messages m
                    WHERE m.direction = "inbound"
                    GROUP BY m.conversation_id
                ) inbound ON inbound.conversation_id = c.id
                LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                WHERE EXISTS (
                    SELECT 1 FROM whatsapp_messages m_any WHERE m_any.conversation_id = c.id
                )';
        $params = [$threshold24h, $threshold24h, $threshold24h];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $row = DB::selectOne($sql, $params) ?? (object) [];
        $total = (int) ($row->total ?? 0);
        $windowOpen = (int) ($row->window_open ?? 0);
        $needsTemplate = (int) ($row->needs_template ?? 0);
        $awaiting = (int) ($row->awaiting_template_reply ?? 0);

        return [
            'queue_conversations_total' => $total,
            'queue_window_open' => $windowOpen,
            'queue_needs_template' => $needsTemplate,
            'queue_awaiting_template_reply' => $awaiting,
            'queue_window_open_rate' => $total > 0 ? round(($windowOpen / $total) * 100, 2) : 0.0,
            'queue_needs_template_rate' => $total > 0 ? round(($needsTemplate / $total) * 100, 2) : 0.0,
            'queue_awaiting_template_reply_rate' => $needsTemplate > 0 ? round(($awaiting / $needsTemplate) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slaSummary(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, int $targetMinutes): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'sla');
        $sql = 'SELECT h.queued_at, h.assigned_at
                FROM whatsapp_handoffs h
                WHERE h.queued_at >= ?
                  AND h.queued_at < ?
                  AND h.assigned_at IS NOT NULL';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $rows = DB::select($sql, $params);
        $total = count($rows);
        $within = 0;
        foreach ($rows as $row) {
            $queuedAt = isset($row->queued_at) ? Carbon::parse((string) $row->queued_at) : null;
            $assignedAt = isset($row->assigned_at) ? Carbon::parse((string) $row->assigned_at) : null;
            if ($queuedAt !== null && $assignedAt !== null && $queuedAt->diffInMinutes($assignedAt) <= $targetMinutes) {
                $within++;
            }
        }

        return [
            'sla_target_minutes' => $targetMinutes,
            'sla_assignments_total' => $total,
            'sla_assignments_in_target' => $within,
            'sla_assignments_rate' => $total > 0 ? round(($within / $total) * 100, 2) : 0.0,
        ];
    }

    private function transferSummary(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): int
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'transfer_summary');
        $sql = 'SELECT COUNT(*) AS total
                FROM whatsapp_handoff_events e
                INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                WHERE e.event_type = "transferred"
                  AND e.created_at >= ?
                  AND e.created_at < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        return (int) (DB::selectOne($sql, $params)->total ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handoffsByRole(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'role');
        $sql = 'SELECT
                    h.handoff_role_id AS role_id,
                    COALESCE(r.name, "Sin rol") AS role_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN h.status = "queued" THEN 1 ELSE 0 END) AS queued,
                    SUM(CASE WHEN h.status = "assigned" THEN 1 ELSE 0 END) AS assigned,
                    SUM(CASE WHEN h.status = "resolved" THEN 1 ELSE 0 END) AS resolved
                FROM whatsapp_handoffs h
                LEFT JOIN roles r ON r.id = h.handoff_role_id
                WHERE h.queued_at >= ?
                  AND h.queued_at < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }
        $sql .= ' GROUP BY h.handoff_role_id, role_name ORDER BY total DESC, role_name ASC';

        return array_map(fn ($row) => [
            'role_id' => isset($row->role_id) ? (int) $row->role_id : null,
            'role_name' => (string) ($row->role_name ?? 'Sin rol'),
            'total' => (int) ($row->total ?? 0),
            'queued' => (int) ($row->queued ?? 0),
            'assigned' => (int) ($row->assigned ?? 0),
            'resolved' => (int) ($row->resolved ?? 0),
        ], DB::select($sql, $params));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handoffsByAgent(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'agent');
        $sql = 'SELECT
                    h.assigned_agent_id AS user_id,
                    ' . $this->agentNameSql('u', 'h.assigned_agent_id', 'Usuario #') . ' AS agent_name,
                    COUNT(*) AS assigned_count,
                    SUM(CASE WHEN h.status = "assigned" THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN h.status = "resolved" THEN 1 ELSE 0 END) AS resolved_count
                FROM whatsapp_handoffs h
                LEFT JOIN users u ON u.id = h.assigned_agent_id
                WHERE h.queued_at >= ?
                  AND h.queued_at < ?
                  AND h.assigned_agent_id IS NOT NULL';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }
        $sql .= ' GROUP BY h.assigned_agent_id, agent_name ORDER BY assigned_count DESC, resolved_count DESC, agent_name ASC';

        return array_map(fn ($row) => [
            'user_id' => (int) ($row->user_id ?? 0),
            'agent_name' => (string) ($row->agent_name ?? ''),
            'assigned_count' => (int) ($row->assigned_count ?? 0),
            'active_count' => (int) ($row->active_count ?? 0),
            'resolved_count' => (int) ($row->resolved_count ?? 0),
        ], DB::select($sql, $params));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function humanAttentionByAgent(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_agent');
        $reply = $this->firstHumanReplyByAgentSubquery($scope, $roleId, $agentId, 'human_agent');
        $sql = 'SELECT
                    first_reply.assigned_agent_id AS user_id,
                    ' . $this->agentNameSql('u', 'first_reply.assigned_agent_id', 'Usuario #') . ' AS agent_name,
                    COUNT(*) AS attended_conversations,
                    ' . ($this->isSqlite()
                        ? 'AVG((julianday(first_reply.first_human_reply_at) - julianday(first_reply.assigned_at)) * 86400)'
                        : 'AVG(TIMESTAMPDIFF(SECOND, first_reply.assigned_at, first_reply.first_human_reply_at))') . ' AS avg_first_response_seconds
                FROM (' . $reply['sql'] . ') first_reply
                LEFT JOIN users u ON u.id = first_reply.assigned_agent_id
                GROUP BY first_reply.assigned_agent_id, agent_name
                ORDER BY attended_conversations DESC, agent_name ASC';

        return array_map(fn ($row) => [
            'user_id' => (int) ($row->user_id ?? 0),
            'agent_name' => (string) ($row->agent_name ?? ''),
            'attended_conversations' => (int) ($row->attended_conversations ?? 0),
            'avg_first_response_minutes' => isset($row->avg_first_response_seconds)
                ? round(((float) $row->avg_first_response_seconds) / 60, 2)
                : null,
        ], DB::select($sql, array_values($reply['params'])));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function humanResponseByQueue(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'human_queue');
        $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
            ? 'LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = h.conversation_id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category, NULL AS initial_intent, NULL AS conversation_type, NULL AS patient_segment) a ON 1 = 0';

        $sql = 'SELECT
                    h.id AS handoff_id,
                    h.topic,
                    h.priority,
                    h.status,
                    h.queued_at,
                    h.assigned_at,
                    c.handoff_requested_at,
                    c.assigned_user_id,
                    a.source_category,
                    a.initial_intent,
                    a.conversation_type,
                    a.patient_segment,
                    (
                        SELECT MIN(m.message_timestamp)
                        FROM whatsapp_messages m
                        WHERE m.conversation_id = h.conversation_id
                          AND m.direction = "outbound"
                          AND h.assigned_at IS NOT NULL
                          AND m.message_timestamp >= h.assigned_at
                    ) AS first_human_reply_at
                FROM whatsapp_handoffs h
                INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                ' . $attributionJoin . '
                WHERE COALESCE(h.queued_at, h.assigned_at, h.created_at) >= ?
                  AND COALESCE(h.queued_at, h.assigned_at, h.created_at) < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $buckets = [];
        foreach (DB::select($sql, $params) as $row) {
            $queue = $this->operationalQueueFromHandoffRow($row);
            if (!isset($buckets[$queue])) {
                $buckets[$queue] = [
                    'queue' => $queue,
                    'label' => $this->operationalQueueLabel($queue),
                    'total_handoffs' => 0,
                    'attended_handoffs' => 0,
                    'pending_handoffs' => 0,
                    'response_seconds' => [],
                ];
            }

            $buckets[$queue]['total_handoffs']++;
            $firstReply = isset($row->first_human_reply_at) ? Carbon::parse((string) $row->first_human_reply_at) : null;
            $responseStart = $this->parseNullableCarbon($row->handoff_requested_at ?? null)
                ?? $this->parseNullableCarbon($row->queued_at ?? null)
                ?? $this->parseNullableCarbon($row->assigned_at ?? null);

            if ($firstReply !== null) {
                $buckets[$queue]['attended_handoffs']++;
                if ($responseStart !== null && $firstReply->greaterThanOrEqualTo($responseStart)) {
                    $buckets[$queue]['response_seconds'][] = $responseStart->diffInSeconds($firstReply);
                }
            } else {
                $buckets[$queue]['pending_handoffs']++;
            }
        }

        $order = ['critical_backlog' => 0, 'captacion' => 1, 'operacion' => 2, 'informacion' => 3];
        $rows = array_map(function (array $bucket): array {
            $seconds = $bucket['response_seconds'];
            $avg = $seconds !== [] ? array_sum($seconds) / count($seconds) : null;
            $median = $this->median($seconds);

            unset($bucket['response_seconds']);
            $bucket['avg_first_response_minutes'] = $avg !== null ? round($avg / 60, 2) : null;
            $bucket['median_first_response_minutes'] = $median !== null ? round($median / 60, 2) : null;
            $bucket['response_rate'] = $bucket['total_handoffs'] > 0
                ? round(($bucket['attended_handoffs'] / $bucket['total_handoffs']) * 100, 2)
                : 0.0;

            return $bucket;
        }, array_values($buckets));

        usort($rows, fn (array $left, array $right): int => ($order[$left['queue']] ?? 99) <=> ($order[$right['queue']] ?? 99));

        return $rows;
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    private function transferTrendRows(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'transfer_trend');
        $sql = 'SELECT DATE(e.created_at) AS period_date, COUNT(*) AS total
                FROM whatsapp_handoff_events e
                INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                WHERE e.event_type = "transferred"
                  AND e.created_at >= ?
                  AND e.created_at < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }
        $sql .= ' GROUP BY DATE(e.created_at) ORDER BY period_date ASC';

        return array_map(fn ($row) => [
            'period_date' => (string) ($row->period_date ?? ''),
            'total' => (int) ($row->total ?? 0),
        ], DB::select($sql, $params));
    }

    /**
     * @return array<string, int>
     */
    private function sigcenterBookingSummary(string $fromSql, string $toSql): array
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return [
                'sigcenter_bookings_created' => 0,
                'sigcenter_booking_patients' => 0,
                'sigcenter_booking_failures' => 0,
            ];
        }

        $row = $this->selectOne(
            'SELECT
                SUM(CASE WHEN status = "created" THEN 1 ELSE 0 END) AS created_total,
                COUNT(DISTINCT CASE WHEN status = "created" THEN wa_number ELSE NULL END) AS patient_total,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_total
             FROM whatsapp_sigcenter_bookings
             WHERE created_at >= ? AND created_at < ?',
            [$fromSql, $toSql]
        );

        return [
            'sigcenter_bookings_created' => (int) ($row->created_total ?? 0),
            'sigcenter_booking_patients' => (int) ($row->patient_total ?? 0),
            'sigcenter_booking_failures' => (int) ($row->failed_total ?? 0),
        ];
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    private function sigcenterBookingTrendRows(string $fromSql, string $toSql): array
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return [];
        }

        return $this->trendRows(
            'SELECT DATE(created_at) AS period_date, COUNT(*) AS total
             FROM whatsapp_sigcenter_bookings
             WHERE status = "created" AND created_at >= ? AND created_at < ?
             GROUP BY DATE(created_at)
             ORDER BY period_date ASC',
            [$fromSql, $toSql]
        );
    }

    /**
     * @return array<int, array{sede_id:?string,sede_nombre:string,total:int}>
     */
    private function sigcenterBookingsBySede(string $fromSql, string $toSql): array
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return [];
        }

        return array_map(fn ($row) => [
            'sede_id' => isset($row->sede_id) ? (string) $row->sede_id : null,
            'sede_nombre' => (string) ($row->sede_nombre ?? 'Sin sede'),
            'total' => (int) ($row->total ?? 0),
        ], DB::select(
            'SELECT sede_id, COALESCE(sede_nombre, sede_id, "Sin sede") AS sede_nombre, COUNT(*) AS total
             FROM whatsapp_sigcenter_bookings
             WHERE status = "created" AND created_at >= ? AND created_at < ?
             GROUP BY sede_id, sede_nombre
             ORDER BY total DESC, sede_nombre ASC',
            [$fromSql, $toSql]
        ));
    }

    private function peakOpenConversations(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_intervals');
        $reply = $this->humanReplySubquery($roleId, $agentId, 'human_intervals');

        $sql = 'SELECT inbound.first_inbound_at AS opened_at,
                       COALESCE(human.first_human_reply_at, ?) AS closed_at
                FROM (' . $scope['sql'] . ') inbound
                LEFT JOIN (' . $reply['sql'] . ') human
                    ON human.conversation_id = inbound.conversation_id
                WHERE inbound.first_inbound_at IS NOT NULL';

        $rows = DB::select($sql, array_merge([Carbon::now()->format('Y-m-d H:i:s')], array_values($scope['params']), array_values($reply['params'])));
        $events = [];
        foreach ($rows as $row) {
            $opened = strtotime((string) ($row->opened_at ?? ''));
            $closed = strtotime((string) ($row->closed_at ?? ''));
            if ($opened === false || $closed === false) {
                continue;
            }
            if ($closed < $opened) {
                $closed = $opened;
            }
            $events[] = ['time' => $opened, 'delta' => 1];
            $events[] = ['time' => $closed, 'delta' => -1];
        }

        usort($events, static function (array $left, array $right): int {
            $cmp = $left['time'] <=> $right['time'];
            return $cmp !== 0 ? $cmp : (($right['delta'] ?? 0) <=> ($left['delta'] ?? 0));
        });

        $current = 0;
        $peak = 0;
        $peakAt = null;
        foreach ($events as $event) {
            $current += (int) $event['delta'];
            if ($current > $peak) {
                $peak = $current;
                $peakAt = $event['time'];
            }
        }

        return [
            'count' => $peak,
            'at' => $peakAt !== null ? date('Y-m-d H:i:s', $peakAt) : null,
        ];
    }

    private function drilldownConversations(string $fromSql, string $toSql, int $limit, int $offset): array
    {
        $total = $this->scalarInt('SELECT COUNT(*) FROM whatsapp_conversations WHERE created_at >= ? AND created_at < ?', [$fromSql, $toSql]);
        $rows = DB::select(
            'SELECT id, wa_number, display_name, patient_full_name, created_at, last_message_at, unread_count
             FROM whatsapp_conversations
             WHERE created_at >= ? AND created_at < ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$fromSql, $toSql, $limit, $offset]
        );

        return $this->drilldownPayload(
            'conversations_new',
            [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'created_at', 'label' => 'Creada'],
                ['key' => 'unread_count', 'label' => 'Sin leer'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    private function drilldownMessages(string $fromSql, string $toSql, string $direction, int $limit, int $offset): array
    {
        $total = $this->scalarInt(
            'SELECT COUNT(*) FROM whatsapp_messages WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?',
            [$direction, $fromSql, $toSql]
        );
        $rows = DB::select(
            'SELECT m.id, c.wa_number, c.display_name, m.message_type, m.body, m.status, m.message_timestamp
             FROM whatsapp_messages m
             INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
             WHERE m.direction = ? AND m.message_timestamp >= ? AND m.message_timestamp < ?
             ORDER BY m.message_timestamp DESC, m.id DESC
             LIMIT ? OFFSET ?',
            [$direction, $fromSql, $toSql, $limit, $offset]
        );

        return $this->drilldownPayload(
            'messages_' . $direction,
            [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'message_type', 'label' => 'Tipo'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'message_timestamp', 'label' => 'Fecha'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    private function drilldownLiveQueue(?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'live_queue_drilldown');
        $base = 'FROM whatsapp_handoffs h
                 INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                 LEFT JOIN users u ON u.id = h.assigned_agent_id
                 LEFT JOIN roles r ON r.id = h.handoff_role_id
                 WHERE h.status IN ("queued", "assigned", "expired")';
        $params = [];
        if ($filter['where'] !== '') {
            $base .= ' AND ' . $filter['where'];
            $params = array_values($filter['params']);
        }

        $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $base, $params)->total ?? 0);
        $rows = DB::select(
            'SELECT h.id, c.wa_number, c.display_name, h.status, r.name AS role_name,
                    ' . $this->agentNameSql('u', null, null) . ' AS agent_name,
                    h.queued_at, h.assigned_until ' . $base . '
             ORDER BY h.queued_at DESC, h.id DESC
             LIMIT ? OFFSET ?',
            array_merge($params, [$limit, $offset])
        );

        return $this->drilldownPayload(
            'live_queue_total',
            [
                ['key' => 'id', 'label' => 'Handoff'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'agent_name', 'label' => 'Agente'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    private function drilldownHumanAttention(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, bool $attended, int $limit, int $offset): array
    {
        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_drilldown');
        $reply = $this->humanReplySubquery($roleId, $agentId, 'human_drilldown');
        $handoffStart = $this->handoffStartSubquery($roleId, $agentId, 'human_drilldown');
        $condition = $attended ? 'IS NOT NULL' : 'IS NULL';
        $base = 'FROM (' . $scope['sql'] . ') inbound
                 LEFT JOIN (' . $handoffStart['sql'] . ') handoff ON handoff.conversation_id = inbound.conversation_id
                 LEFT JOIN (' . $reply['sql'] . ') human ON human.conversation_id = inbound.conversation_id
                 INNER JOIN whatsapp_conversations c ON c.id = inbound.conversation_id
                 WHERE human.first_human_reply_at ' . $condition;
        $params = array_merge(
            array_values($scope['params']),
            array_values($handoffStart['params']),
            array_values($reply['params'])
        );

        $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $base, $params)->total ?? 0);
        $rows = DB::select(
            'SELECT c.id, c.wa_number, c.display_name, c.patient_full_name,
                    inbound.first_inbound_at, inbound.last_inbound_at, inbound.handoff_requested_at,
                    handoff.first_handoff_at, human.first_human_reply_at ' . $base . '
             ORDER BY inbound.first_inbound_at DESC, c.id DESC
             LIMIT ? OFFSET ?',
            array_merge($params, [$limit, $offset])
        );

        return $this->drilldownPayload(
            $attended ? 'conversations_attended_human' : 'conversations_lost',
            [
                ['key' => 'id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'patient_full_name', 'label' => 'Paciente'],
                ['key' => 'first_inbound_at', 'label' => '1er inbound'],
                ['key' => 'handoff_requested_at', 'label' => 'Solicitud ayuda'],
                ['key' => 'first_handoff_at', 'label' => 'Ingreso a handoff'],
                ['key' => 'first_human_reply_at', 'label' => '1ra respuesta'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    private function drilldownNeedsTemplate(?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, 'needs_template_drilldown');
        $threshold24h = Carbon::now()->subHours(24)->format('Y-m-d H:i:s');
        $base = 'FROM whatsapp_conversations c
                 LEFT JOIN (
                    SELECT m.conversation_id, MAX(COALESCE(m.message_timestamp, m.created_at)) AS last_inbound_at
                    FROM whatsapp_messages m
                    WHERE m.direction = "inbound"
                    GROUP BY m.conversation_id
                 ) inbound ON inbound.conversation_id = c.id
                 LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                 WHERE EXISTS (
                    SELECT 1 FROM whatsapp_messages m_any WHERE m_any.conversation_id = c.id
                 )
                 AND (inbound.last_inbound_at IS NULL OR inbound.last_inbound_at < ?)';
        $params = [$threshold24h];
        if ($filter['where'] !== '') {
            $base .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $base, $params)->total ?? 0);
        $rows = DB::select(
            'SELECT c.id, c.wa_number, c.display_name, c.last_message_at, c.last_message_direction, c.last_message_type, inbound.last_inbound_at ' . $base . '
             ORDER BY inbound.last_inbound_at ASC, c.last_message_at DESC
             LIMIT ? OFFSET ?',
            array_merge($params, [$limit, $offset])
        );

        return $this->drilldownPayload(
            'queue_needs_template',
            [
                ['key' => 'id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'last_inbound_at', 'label' => 'Último inbound'],
                ['key' => 'last_message_direction', 'label' => 'Última dir.'],
                ['key' => 'last_message_type', 'label' => 'Último tipo'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    private function drilldownSlaAssignments(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'sla_drilldown');
        $base = 'FROM whatsapp_handoffs h
                 INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                 LEFT JOIN users u ON u.id = h.assigned_agent_id
                 LEFT JOIN roles r ON r.id = h.handoff_role_id
                 WHERE h.queued_at >= ?
                   AND h.queued_at < ?
                   AND h.assigned_at IS NOT NULL';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $base .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $base, $params)->total ?? 0);
        $rawRows = DB::select(
            'SELECT h.id, c.wa_number, c.display_name, r.name AS role_name,
                    ' . $this->agentNameSql('u', null, null) . ' AS agent_name,
                    h.queued_at, h.assigned_at ' . $base . '
             ORDER BY h.queued_at DESC, h.id DESC
             LIMIT ? OFFSET ?',
            array_merge($params, [$limit, $offset])
        );

        $rows = array_map(function (object $row): array {
            $queuedAt = isset($row->queued_at) ? Carbon::parse((string) $row->queued_at) : null;
            $assignedAt = isset($row->assigned_at) ? Carbon::parse((string) $row->assigned_at) : null;

            return [
                'id' => (int) ($row->id ?? 0),
                'wa_number' => (string) ($row->wa_number ?? ''),
                'display_name' => (string) ($row->display_name ?? ''),
                'role_name' => (string) ($row->role_name ?? ''),
                'agent_name' => (string) ($row->agent_name ?? ''),
                'queued_at' => $row->queued_at ?? null,
                'assigned_at' => $row->assigned_at ?? null,
                'elapsed_minutes' => ($queuedAt !== null && $assignedAt !== null) ? $queuedAt->diffInMinutes($assignedAt) : null,
            ];
        }, $rawRows);

        return $this->drilldownPayload(
            'sla_assignments_total',
            [
                ['key' => 'id', 'label' => 'Handoff'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'agent_name', 'label' => 'Agente'],
                ['key' => 'elapsed_minutes', 'label' => 'Minutos a asignar'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    private function drilldownTransfers(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'transfer_drilldown');
        $base = 'FROM whatsapp_handoff_events e
                 INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                 INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                 LEFT JOIN users u ON u.id = e.actor_user_id
                 WHERE e.event_type = "transferred"
                   AND e.created_at >= ?
                   AND e.created_at < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $base .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $base, $params)->total ?? 0);
        $rows = DB::select(
            'SELECT e.id, c.wa_number, c.display_name,
                    ' . $this->agentNameSql('u', null, 'Sistema') . ' AS actor_name,
                    e.notes, e.created_at ' . $base . '
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ? OFFSET ?',
            array_merge($params, [$limit, $offset])
        );

        return $this->drilldownPayload(
            'handoff_transfers',
            [
                ['key' => 'id', 'label' => 'Evento'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'display_name', 'label' => 'Contacto'],
                ['key' => 'actor_name', 'label' => 'Actor'],
                ['key' => 'created_at', 'label' => 'Fecha'],
            ],
            $rows,
            $total,
            $limit,
            $offset
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function drilldownPayload(string $metric, array $columns, array $rows, int $total, int $limit, int $offset): array
    {
        $page = (int) floor($offset / $limit) + 1;
        return [
            'metric' => $metric,
            'columns' => $columns,
            'rows' => array_map(fn ($row) => (array) $row, $rows),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    private function selectOne(string $sql, array $params = []): object
    {
        return DB::selectOne($sql, array_values($params)) ?? (object) [];
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function scalarInt(string $sql, array $params = []): int
    {
        $row = $this->selectOne($sql, $params);
        $value = array_values((array) $row)[0] ?? 0;
        return (int) $value;
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    private function trendRows(string $sql, array $params): array
    {
        return array_map(fn ($row) => [
            'period_date' => (string) ($row->period_date ?? ''),
            'total' => (int) ($row->total ?? 0),
        ], DB::select($sql, $params));
    }

    /**
     * @return array<int, string>
     */
    private function dateLabels(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        $labels = [];
        $period = new DatePeriod($from, new DateInterval('P1D'), $toExclusive);
        foreach ($period as $day) {
            $labels[] = $day->format('Y-m-d');
        }
        return $labels;
    }

    /**
     * @param array<int, array{period_date:string,total:int}> $rows
     * @return array<int, int>
     */
    private function mapTrend(array $rows, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        $map = [];
        foreach ($rows as $row) {
            if ($row['period_date'] !== '') {
                $map[$row['period_date']] = $row['total'];
            }
        }

        $series = [];
        foreach ($this->dateLabels($from, $toExclusive) as $label) {
            $series[] = (int) ($map[$label] ?? 0);
        }
        return $series;
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function roleOptions(): array
    {
        return array_map(fn ($row) => [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? ''),
        ], DB::select('SELECT id, name FROM roles ORDER BY name ASC'));
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function agentOptions(): array
    {
        return array_map(fn ($row) => [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->agent_name ?? ''),
        ], DB::select('SELECT id, ' . $this->agentNameSql(null, 'id', 'Usuario #') . ' AS agent_name FROM users ORDER BY agent_name ASC'));
    }

    private function agentNameSql(?string $tableAlias, ?string $idExpression, ?string $fallbackLabel): string
    {
        $prefix = $tableAlias !== null && $tableAlias !== '' ? $tableAlias . '.' : '';
        $fullName = $this->fullNameSql($prefix . 'first_name', $prefix . 'last_name');
        $parts = [
            'NULLIF(TRIM(' . $fullName . '), "")',
            'NULLIF(' . $prefix . 'nombre, "")',
            'NULLIF(' . $prefix . 'username, "")',
        ];

        if ($fallbackLabel !== null) {
            if ($idExpression !== null && $idExpression !== '') {
                $parts[] = $this->concatLabelSql($fallbackLabel, $idExpression);
            } else {
                $parts[] = $this->stringLiteral($fallbackLabel);
            }
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function fullNameSql(string $firstNameExpression, string $lastNameExpression): string
    {
        if ($this->isSqlite()) {
            return 'COALESCE(' . $firstNameExpression . ', "") || " " || COALESCE(' . $lastNameExpression . ', "")';
        }

        return 'CONCAT(COALESCE(' . $firstNameExpression . ', ""), " ", COALESCE(' . $lastNameExpression . ', ""))';
    }

    private function concatLabelSql(string $label, string $idExpression): string
    {
        if ($this->isSqlite()) {
            return $this->stringLiteral($label) . ' || CAST(' . $idExpression . ' AS TEXT)';
        }

        return 'CONCAT(' . $this->stringLiteral($label) . ', CAST(' . $idExpression . ' AS CHAR))';
    }

    private function stringLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @return array{where:string,params:array<string,mixed>}
     */
    private function handoffFilterSql(string $alias, ?int $roleId, ?int $agentId, string $scope): array
    {
        $conditions = [];
        $params = [];

        if ($roleId !== null && $roleId > 0) {
            $conditions[] = $alias . '.handoff_role_id = ?';
            $params[$scope . '_role'] = $roleId;
        }

        if ($agentId !== null && $agentId > 0) {
            $conditions[] = $alias . '.assigned_agent_id = ?';
            $params[$scope . '_agent'] = $agentId;
        }

        return ['where' => implode(' AND ', $conditions), 'params' => $params];
    }

    /**
     * @return array{where:string,params:array<string,mixed>}
     */
    private function conversationScopeFilterSql(string $conversationAlias, string $userAlias, ?int $roleId, ?int $agentId, string $scope): array
    {
        $conditions = [];
        $params     = [];

        if ($roleId !== null && $roleId > 0) {
            $conditions[] = $userAlias . '.role_id = ?';
            $params[$scope . '_role'] = $roleId;
        }

        if ($agentId !== null && $agentId > 0) {
            // Incluye la asignación actual Y conversaciones donde el agente
            // tuvo un handoff histórico (para no perder transferencias).
            $conditions[] = '(' . $conversationAlias . '.assigned_user_id = ? OR EXISTS (
            SELECT 1 FROM whatsapp_handoffs wh_scope
            WHERE wh_scope.conversation_id = ' . $conversationAlias . '.id
              AND wh_scope.assigned_agent_id = ?
        ))';
            $params[$scope . '_agent_current']    = $agentId;
            $params[$scope . '_agent_historical'] = $agentId;
        }

        return ['where' => implode(' AND ', $conditions), 'params' => $params];
    }

    /**
     * @return array{sql:string,params:array<string,mixed>}
     */
    private function inboundScopeSubquery(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, $scope);
        $handoffRequestedSelect = Schema::hasColumn('whatsapp_conversations', 'handoff_requested_at')
            ? 'c.handoff_requested_at'
            : 'NULL AS handoff_requested_at';
        $sql = 'SELECT
                    c.id AS conversation_id,
                    c.wa_number,
                    ' . $handoffRequestedSelect . ',
                    MIN(m.message_timestamp) AS first_inbound_at,
                    MAX(m.message_timestamp) AS last_inbound_at
                FROM whatsapp_messages m
                INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                WHERE m.direction = "inbound"
                  AND m.message_timestamp >= ?
                  AND m.message_timestamp < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }
        $sql .= ' GROUP BY c.id, c.wa_number';
        if (Schema::hasColumn('whatsapp_conversations', 'handoff_requested_at')) {
            $sql .= ', c.handoff_requested_at';
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return array{sql:string,params:array<string,mixed>}
     */
    private function humanReplySubquery(?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, $scope . '_reply');
        $sql = 'SELECT
                    h.conversation_id,
                    MIN(m.message_timestamp) AS first_human_reply_at
                FROM whatsapp_handoffs h
                INNER JOIN whatsapp_messages m
                    ON m.conversation_id = h.conversation_id
                   AND m.direction = "outbound"
                   AND h.assigned_at IS NOT NULL
                   AND m.message_timestamp >= h.assigned_at
                WHERE 1 = 1';
        $params = [];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_values($filter['params']);
        }
        $sql .= ' GROUP BY h.conversation_id';

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return array{sql:string,params:array<string,mixed>}
     */
    private function handoffStartSubquery(?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, $scope . '_start');
        $sql = 'SELECT
                    h.conversation_id,
                    MIN(COALESCE(h.queued_at, h.assigned_at, h.created_at)) AS first_handoff_at
                FROM whatsapp_handoffs h
                WHERE 1 = 1';
        $params = [];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_values($filter['params']);
        }
        $sql .= ' GROUP BY h.conversation_id';

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @param array{sql:string,params:array<string,mixed>} $inboundScope
     * @return array{sql:string,params:array<string,mixed>}
     */
    private function firstHumanReplyByAgentSubquery(array $inboundScope, ?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, $scope . '_first_reply');
        $sql = 'SELECT
                    inbound.conversation_id,
                    h.assigned_agent_id,
                    h.assigned_at,
                    MIN(m.message_timestamp) AS first_human_reply_at
                FROM (' . $inboundScope['sql'] . ') inbound
                INNER JOIN whatsapp_handoffs h ON h.conversation_id = inbound.conversation_id
                INNER JOIN whatsapp_messages m
                    ON m.conversation_id = inbound.conversation_id
                   AND m.direction = "outbound"
                   AND h.assigned_at IS NOT NULL
                   AND m.message_timestamp >= h.assigned_at
                WHERE h.assigned_agent_id IS NOT NULL';
        $params = array_values($inboundScope['params']);
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }
        $sql .= ' GROUP BY inbound.conversation_id, h.assigned_agent_id, h.assigned_at';

        return ['sql' => $sql, 'params' => $params];
    }

    private function operationalQueueFromHandoffRow(object $row): string
    {
        $topic = strtolower(trim((string) ($row->topic ?? '')));
        if (str_starts_with($topic, 'captacion_')) {
            return 'captacion';
        }
        if (str_starts_with($topic, 'operacion_')) {
            return 'operacion';
        }
        if (in_array($topic, ['faq_escalada', 'promociones', 'caso_especial'], true)) {
            return 'informacion';
        }

        $queuedAt = $this->parseNullableCarbon($row->queued_at ?? null);
        $assignedUserId = (int) ($row->assigned_user_id ?? 0);
        if ($assignedUserId <= 0 && $queuedAt !== null && $queuedAt->lessThanOrEqualTo(Carbon::now()->subHours(24))) {
            return 'critical_backlog';
        }

        $sourceCategory = strtolower(trim((string) ($row->source_category ?? '')));
        $initialIntent = strtolower(trim((string) ($row->initial_intent ?? '')));
        $conversationType = strtolower(trim((string) ($row->conversation_type ?? '')));
        $patientSegment = strtolower(trim((string) ($row->patient_segment ?? '')));

        if ((in_array($sourceCategory, ['ad', 'organic_direct'], true) && $patientSegment === 'new_patient')
            || $initialIntent === 'booking'
        ) {
            return 'captacion';
        }

        if (in_array($sourceCategory, ['support_operational', 'campaign_outbound'], true)
            || in_array($conversationType, ['reschedule', 'cancel', 'results', 'human_help', 'campaign_response'], true)
        ) {
            return 'operacion';
        }

        return 'informacion';
    }

    private function operationalQueueLabel(string $queue): string
    {
        return match ($queue) {
            'critical_backlog' => 'Backlog >24h',
            'captacion' => 'Captación',
            'operacion' => 'Operación',
            'informacion' => 'Información',
            default => 'Sin clasificar',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentReminderAnalytics(string $fromSql, string $toSql): array
    {
        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return [
                'summary' => [
                    'total' => 0,
                    'sent' => 0,
                    'delivered' => 0,
                    'failed' => 0,
                    'responded' => 0,
                    'confirmed' => 0,
                    'agent_requested' => 0,
                    'pending' => 0,
                    'delivery_rate' => 0.0,
                    'response_rate' => 0.0,
                    'confirmation_rate' => 0.0,
                    'agent_rate' => 0.0,
                ],
                'config' => $this->appointmentReminderConfigSnapshot(),
                'by_source_window' => [],
                'recent' => [],
            ];
        }

        $summaryRow = $this->selectOne(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = "responded" THEN 1 ELSE 0 END) AS responded,
                SUM(CASE WHEN response_value = "confirmar" THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN response_value = "agente" THEN 1 ELSE 0 END) AS agent_requested,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending
             FROM whatsapp_appointment_reminders
             WHERE created_at >= ? AND created_at < ?',
            [$fromSql, $toSql]
        );

        $summary = [
            'total' => (int) ($summaryRow->total ?? 0),
            'sent' => (int) ($summaryRow->sent ?? 0),
            'delivered' => (int) ($summaryRow->delivered ?? 0),
            'failed' => (int) ($summaryRow->failed ?? 0),
            'responded' => (int) ($summaryRow->responded ?? 0),
            'confirmed' => (int) ($summaryRow->confirmed ?? 0),
            'agent_requested' => (int) ($summaryRow->agent_requested ?? 0),
            'pending' => (int) ($summaryRow->pending ?? 0),
        ];
        $summary['delivery_rate'] = $summary['total'] > 0 ? round(($summary['delivered'] / $summary['total']) * 100, 1) : 0.0;
        $summary['response_rate'] = $summary['total'] > 0 ? round(($summary['responded'] / $summary['total']) * 100, 1) : 0.0;
        $summary['confirmation_rate'] = $summary['responded'] > 0 ? round(($summary['confirmed'] / $summary['responded']) * 100, 1) : 0.0;
        $summary['agent_rate'] = $summary['responded'] > 0 ? round(($summary['agent_requested'] / $summary['responded']) * 100, 1) : 0.0;

        $breakdownRows = DB::select(
            'SELECT
                source_type,
                reminder_window,
                COUNT(*) AS total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = "responded" THEN 1 ELSE 0 END) AS responded,
                SUM(CASE WHEN response_value = "confirmar" THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN response_value = "agente" THEN 1 ELSE 0 END) AS agent_requested
             FROM whatsapp_appointment_reminders
             WHERE created_at >= ? AND created_at < ?
             GROUP BY source_type, reminder_window
             ORDER BY source_type ASC, reminder_window ASC',
            [$fromSql, $toSql]
        );

        $recentRows = DB::select(
            'SELECT
                war.id,
                war.form_id,
                war.hc_number,
                war.wa_number,
                war.source_type,
                war.template_code,
                war.reminder_window,
                war.event_at,
                war.status,
                war.response_value,
                war.sent_at,
                war.delivered_at,
                war.failed_at,
                war.responded_at,
                war.created_at,
                COALESCE(wc.patient_full_name, wc.display_name, war.hc_number) AS patient_name
             FROM whatsapp_appointment_reminders war
             LEFT JOIN whatsapp_conversations wc ON wc.id = war.conversation_id
             WHERE war.created_at >= ? AND war.created_at < ?
             ORDER BY COALESCE(war.sent_at, war.created_at) DESC, war.id DESC
             LIMIT 12',
            [$fromSql, $toSql]
        );

        return [
            'summary' => $summary,
            'config' => $this->appointmentReminderConfigSnapshot(),
            'by_source_window' => array_map(function ($row): array {
                $total = (int) ($row->total ?? 0);
                $responded = (int) ($row->responded ?? 0);

                return [
                    'source_type' => (string) ($row->source_type ?? ''),
                    'source_label' => $this->reminderSourceLabel((string) ($row->source_type ?? '')),
                    'reminder_window' => (string) ($row->reminder_window ?? ''),
                    'window_label' => $this->reminderWindowLabel((string) ($row->reminder_window ?? '')),
                    'total' => $total,
                    'sent' => (int) ($row->sent ?? 0),
                    'delivered' => (int) ($row->delivered ?? 0),
                    'failed' => (int) ($row->failed ?? 0),
                    'responded' => $responded,
                    'confirmed' => (int) ($row->confirmed ?? 0),
                    'agent_requested' => (int) ($row->agent_requested ?? 0),
                    'response_rate' => $total > 0 ? round(($responded / $total) * 100, 1) : 0.0,
                ];
            }, $breakdownRows),
            'recent' => array_map(function ($row): array {
                return [
                    'id' => (int) ($row->id ?? 0),
                    'form_id' => (int) ($row->form_id ?? 0),
                    'hc_number' => trim((string) ($row->hc_number ?? '')),
                    'wa_number' => trim((string) ($row->wa_number ?? '')),
                    'patient_name' => trim((string) ($row->patient_name ?? '')),
                    'source_type' => (string) ($row->source_type ?? ''),
                    'source_label' => $this->reminderSourceLabel((string) ($row->source_type ?? '')),
                    'template_code' => trim((string) ($row->template_code ?? '')),
                    'reminder_window' => trim((string) ($row->reminder_window ?? '')),
                    'window_label' => $this->reminderWindowLabel((string) ($row->reminder_window ?? '')),
                    'event_at' => (string) ($row->event_at ?? ''),
                    'status' => (string) ($row->status ?? ''),
                    'status_label' => $this->reminderStatusLabel((string) ($row->status ?? '')),
                    'response_value' => (string) ($row->response_value ?? ''),
                    'response_label' => $this->reminderResponseLabel((string) ($row->response_value ?? '')),
                    'sent_at' => (string) ($row->sent_at ?? ''),
                    'delivered_at' => (string) ($row->delivered_at ?? ''),
                    'failed_at' => (string) ($row->failed_at ?? ''),
                    'responded_at' => (string) ($row->responded_at ?? ''),
                    'created_at' => (string) ($row->created_at ?? ''),
                ];
            }, $recentRows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentReminderConfigSnapshot(): array
    {
        return [
            'enabled' => $this->settingBoolValue('whatsapp_reminders_enabled', false),
            'timezone' => trim((string) $this->settingValue('whatsapp_reminder_timezone', config('app.timezone', 'America/Guayaquil'))),
            'service_template' => trim((string) $this->settingValue('whatsapp_reminder_service_template_code', 'recordatorio_cita_medica_cive')),
            'imaging_template' => trim((string) $this->settingValue('whatsapp_reminder_imaging_template_code', 'recordatorio_cita_medica_cive')),
            'window_24h_enabled' => $this->settingBoolValue('whatsapp_reminder_window_24h_enabled', true),
            'window_24h_minutes' => (int) $this->settingValue('whatsapp_reminder_window_24h_minutes', 1440),
            'window_2h_enabled' => $this->settingBoolValue('whatsapp_reminder_window_2h_enabled', true),
            'window_2h_minutes' => (int) $this->settingValue('whatsapp_reminder_window_2h_minutes', 120),
            'tolerance_minutes' => (int) $this->settingValue('whatsapp_reminder_window_tolerance_minutes', 15),
            'max_per_patient_per_day' => (int) $this->settingValue('whatsapp_reminder_max_per_patient_per_day', 2),
            'recent_outbound_hours' => (int) $this->settingValue('whatsapp_reminder_skip_if_recent_outbound_hours', 12),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function localReminderDateRangeSql(DateTimeImmutable $from, DateTimeImmutable $endDate): array
    {
        $timezone = trim((string) $this->settingValue(
            'whatsapp_reminder_timezone',
            config('app.timezone', 'America/Guayaquil')
        )) ?: 'America/Guayaquil';

        $fromUtc = Carbon::parse($from->format('Y-m-d') . ' 00:00:00', $timezone)
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
        $toUtc = Carbon::parse($endDate->format('Y-m-d') . ' 00:00:00', $timezone)
            ->addDay()
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');

        return [$fromUtc, $toUtc];
    }

    private function reminderSourceLabel(string $value): string
    {
        return match ($value) {
            'servicios_oftalmologicos_generales' => 'Servicios oftalmológicos generales',
            'imagenes' => 'Imágenes',
            default => $value !== '' ? $value : 'Sin clasificar',
        };
    }

    private function reminderWindowLabel(string $value): string
    {
        return match ($value) {
            '24h' => '24 horas',
            '2h' => '2 horas',
            default => $value !== '' ? $value : 'Sin ventana',
        };
    }

    private function reminderStatusLabel(string $value): string
    {
        return match ($value) {
            'pending' => 'Pendiente',
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            'responded' => 'Respondido',
            default => $value !== '' ? ucfirst($value) : 'Sin estado',
        };
    }

    private function reminderResponseLabel(string $value): string
    {
        return match ($value) {
            'confirmar' => 'Confirmó',
            'agente' => 'Pidió agente',
            default => $value !== '' ? ucfirst($value) : 'Sin respuesta',
        };
    }

    private function settingBoolValue(string $key, bool $default): bool
    {
        $value = $this->settingValue($key, $default ? '1' : '0');

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function settingValue(string $key, mixed $default = null): mixed
    {
        $options = $this->settingsOptionMap();

        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    /**
     * @return array<string, string>
     */
    private function settingsOptionMap(): array
    {
        if ($this->settingsOptionCache !== null) {
            return $this->settingsOptionCache;
        }

        $keys = [
            'whatsapp_reminders_enabled',
            'whatsapp_reminder_timezone',
            'whatsapp_reminder_service_template_code',
            'whatsapp_reminder_imaging_template_code',
            'whatsapp_reminder_window_24h_enabled',
            'whatsapp_reminder_window_24h_minutes',
            'whatsapp_reminder_window_2h_enabled',
            'whatsapp_reminder_window_2h_minutes',
            'whatsapp_reminder_window_tolerance_minutes',
            'whatsapp_reminder_max_per_patient_per_day',
            'whatsapp_reminder_skip_if_recent_outbound_hours',
        ];

        return $this->settingsOptionCache = $this->settingsResolver()->getOptions($keys);
    }

    private function settingsResolver(): SettingsOptionResolver
    {
        return $this->settingsResolver ??= app(SettingsOptionResolver::class);
    }

    private function parseNullableCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, int|float> $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}
