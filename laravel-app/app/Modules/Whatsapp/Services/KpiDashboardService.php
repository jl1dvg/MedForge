<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Shared\Support\SettingsOptionResolver;
use App\Modules\Whatsapp\Support\BusinessHoursCalculator;
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
        $hasMessagesTable = Schema::hasTable('whatsapp_messages');

        $summary = [
            'conversations_new' => $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_conversations WHERE created_at >= ? AND created_at < ?',
                [$fromSql, $toSql]
            ),
            'messages_inbound' => $hasMessagesTable ? $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_messages WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?',
                ['inbound', $fromSql, $toSql]
            ) : 0,
            'messages_outbound' => $hasMessagesTable ? $this->scalarInt(
                'SELECT COUNT(*) FROM whatsapp_messages WHERE direction = ? AND message_timestamp >= ? AND message_timestamp < ?',
                ['outbound', $fromSql, $toSql]
            ) : 0,
        ];

        $human = $this->humanAttentionSummary($fromSql, $toSql, $roleId, $agentId, $slaTargetMinutes);
        $queue = $this->queueSummary($roleId, $agentId);
        $window = $this->conversationWindowSummary($roleId, $agentId);
        $sla = $this->slaSummary($fromSql, $toSql, $roleId, $agentId, $slaTargetMinutes);
        $transfers = $this->transferSummary($fromSql, $toSql, $roleId, $agentId);
        $bookings = $this->sigcenterBookingSummary($fromSql, $toSql);
        $humanAppointmentAttribution = $this->humanAppointmentAttribution($fromSql, $toSql, $roleId, $agentId);
        $analytics = $this->conversationAnalytics($fromSql, $toSql, $roleId, $agentId);
        $reminders = $this->appointmentReminderAnalytics($reminderFromSql, $reminderToSql);
        $closeReasons = $this->closeReasonSummary($fromSql, $toSql, $roleId, $agentId);
        $operationalInbox = $this->operationalInboxSummary($roleId, $agentId);

        $summary = array_merge($summary, $human, $queue, $window, $sla, [
            'handoff_transfers' => $transfers,
            'peak_open_conversations' => (int) ($human['peak_open_conversations'] ?? 0),
        ], $bookings, $humanAppointmentAttribution['summary'], $closeReasons, $operationalInbox);

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
                'messages_inbound' => $hasMessagesTable ? $this->mapTrend(
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
                ) : $this->emptyTrend($from, $toExclusive),
                'messages_outbound' => $hasMessagesTable ? $this->mapTrend(
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
                ) : $this->emptyTrend($from, $toExclusive),
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
                'human_attributed_appointments' => $this->mapTrend(
                    $humanAppointmentAttribution['trend_rows'],
                    $from,
                    $toExclusive
                ),
            ],
            'breakdowns' => [
                'handoffs_by_role' => $this->handoffsByRole($fromSql, $toSql, $roleId, $agentId),
                'handoffs_by_agent' => $this->handoffsByAgent($fromSql, $toSql, $roleId, $agentId),
                'human_attention_by_agent' => $this->humanAttentionByAgent($fromSql, $toSql, $roleId, $agentId),
                'agent_live_status' => $this->agentLiveStatus($roleId, $agentId),
                'human_response_by_queue' => $this->humanResponseByQueue($fromSql, $toSql, $roleId, $agentId),
                'sigcenter_bookings_by_sede' => $this->sigcenterBookingsBySede($fromSql, $toSql),
                'sigcenter_bookings_by_source' => $this->sigcenterBookingsBySource($fromSql, $toSql),
                'human_attributed_appointments_by_agent' => $humanAppointmentAttribution['by_agent'],
                'human_attributed_appointments_by_sede' => $humanAppointmentAttribution['by_sede'],
                'human_attributed_appointments_by_source' => $humanAppointmentAttribution['by_source'],
            ],
            'analytics' => $analytics,
            'reminders' => $reminders,
            'marketing' => [
                'funnel_by_source' => $this->conversationFunnelBySource(
                    $this->conversationAnalyticsBaseSubquery($fromSql, $toSql, $roleId, $agentId, 'mktg_funnel')
                ),
                'lost_by_source' => $this->lostLeadsBySource($fromSql, $toSql, $roleId, $agentId),
            ],
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
            'human_attributed_appointments_strong' => 'Citas Sigcenter atribuibles a atención humana (24h)',
            'human_attributed_appointment_conversations_strong' => 'Conversaciones humanas con cita atribuible (24h)',
            'human_attributed_appointments_medium' => 'Citas Sigcenter atribuibles a atención humana (72h)',
            'sigcenter_bookings_created' => 'Citas Sigcenter creadas por bot/integración',
            'sigcenter_booking_patients' => 'Pacientes agendados por bot/integración',
            'sigcenter_booking_failures' => 'Citas Sigcenter fallidas desde WhatsApp',
        ] as $key => $label) {
            $rows[] = ['summary', $label, $summary[$key] ?? null, null];
        }

        $rows[] = ['breakdown', 'Citas atribuibles a atención humana por agente', null, null];
        foreach (($breakdowns['human_attributed_appointments_by_agent'] ?? []) as $row) {
            $rows[] = [
                'human_attributed_appointments_by_agent',
                (string) ($row['agent_name'] ?? ''),
                (int) ($row['appointment_slots'] ?? 0),
                'Conversaciones ' . ((int) ($row['conversations'] ?? 0)) . ' · Pacientes ' . ((int) ($row['patients'] ?? 0)),
            ];
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
        if (!Schema::hasTable('whatsapp_conversations')) {
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
     * Embudo de conversión agrupado por origen (ad, organic_direct, campaign_outbound).
     *
     * @param array{sql:string,params:array<int|string,mixed>} $base
     * @return array<int, array<string, mixed>>
     */
    private function conversationFunnelBySource(array $base): array
    {
        $rows = DB::select(
            'SELECT
                source_category,
                COUNT(*) AS total,
                SUM(is_identified) AS identified,
                SUM(has_handoff) AS handoffs,
                SUM(has_booking) AS booked
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY source_category
             ORDER BY total DESC',
            $base['params']
        );

        $order = ['ad' => 0, 'organic_direct' => 1, 'campaign_outbound' => 2];

        $result = array_map(function ($row): array {
            $total      = (int) ($row->total ?? 0);
            $identified = (int) ($row->identified ?? 0);
            $handoffs   = (int) ($row->handoffs ?? 0);
            $booked     = (int) ($row->booked ?? 0);
            $source     = (string) ($row->source_category ?? 'unknown');

            return [
                'source_category'     => $source,
                'source_label'        => $this->sourceCategoryLabel($source),
                'total'               => $total,
                'identified'          => $identified,
                'identification_rate' => $total > 0 ? round(($identified / $total) * 100, 1) : 0.0,
                'handoffs'            => $handoffs,
                'handoff_rate'        => $total > 0 ? round(($handoffs / $total) * 100, 1) : 0.0,
                'booked'              => $booked,
                'booking_rate'        => $total > 0 ? round(($booked / $total) * 100, 1) : 0.0,
            ];
        }, $rows);

        usort($result, fn ($a, $b) => ($order[$a['source_category']] ?? 99) <=> ($order[$b['source_category']] ?? 99));

        return $result;
    }

    /**
     * Leads de ads que no recibieron atención humana — separa responsabilidad marketing vs operaciones.
     *
     * @return array{ads_total:int,ads_lost_no_human:int,ads_lost_no_assignment:int,ads_abandoned_with_handoff:int}
     */
    private function lostLeadsBySource(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [
                'ads_total' => 0,
                'ads_lost_no_human' => 0,
                'ads_lost_no_assignment' => 0,
                'ads_abandoned_with_handoff' => 0,
            ];
        }

        $scope        = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'lost_leads');
        $reply        = $this->humanReplySubquery($roleId, $agentId, 'lost_leads');
        $handoffStart = $this->handoffStartSubquery($roleId, $agentId, 'lost_leads');

        $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
            ? 'LEFT JOIN whatsapp_conversation_attributions attr ON attr.conversation_id = inbound.conversation_id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category) attr ON 1 = 0';

        $sql = 'SELECT
                    inbound.last_inbound_at,
                    COALESCE(attr.source_category, "unknown") AS source_category,
                    human.first_human_reply_at,
                    handoff.first_handoff_at,
                    inbound.handoff_requested_at,
                    (SELECT MIN(h2.assigned_at) FROM whatsapp_handoffs h2
                     WHERE h2.conversation_id = inbound.conversation_id
                       AND h2.assigned_agent_id IS NOT NULL) AS first_assignment_at
                FROM (' . $scope['sql'] . ') inbound
                ' . $attributionJoin . '
                LEFT JOIN (' . $reply['sql'] . ') human ON human.conversation_id = inbound.conversation_id
                LEFT JOIN (' . $handoffStart['sql'] . ') handoff ON handoff.conversation_id = inbound.conversation_id';

        $params = array_merge(
            array_values($scope['params']),
            array_values($reply['params']),
            array_values($handoffStart['params'])
        );

        $rows         = DB::select($sql, $params);
        $threshold24h = Carbon::now()->subHours(24);

        $adsTotal            = 0;
        $adsLostNoHuman      = 0;
        $adsLostNoAssignment = 0;
        $adsAbandonedHandoff = 0;

        foreach ($rows as $row) {
            $isAd = in_array((string) ($row->source_category ?? ''), ['ad', 'ads'], true);
            if (!$isAd) {
                continue;
            }

            $adsTotal++;
            $hasHuman      = isset($row->first_human_reply_at);
            $hasHandoff    = isset($row->first_handoff_at) || isset($row->handoff_requested_at);
            $hasAssignment = isset($row->first_assignment_at);
            $lastInbound   = isset($row->last_inbound_at) ? Carbon::parse((string) $row->last_inbound_at) : null;
            $isOld         = $lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h);

            if (!$hasHuman) {
                $adsLostNoHuman++;
            }
            if ($hasHandoff && !$hasAssignment) {
                $adsLostNoAssignment++;
            }
            if ($hasHandoff && !$hasHuman && $isOld) {
                $adsAbandonedHandoff++;
            }
        }

        return [
            'ads_total'                  => $adsTotal,
            'ads_lost_no_human'          => $adsLostNoHuman,
            'ads_lost_no_assignment'     => $adsLostNoAssignment,
            'ads_abandoned_with_handoff' => $adsAbandonedHandoff,
        ];
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
                CASE
                    WHEN COALESCE(NULLIF(initial_intent, ""), "unknown") = "unknown"
                        THEN COALESCE(NULLIF(conversation_type, ""), "unknown")
                    ELSE COALESCE(NULLIF(initial_intent, ""), "unknown")
                END AS initial_intent,
                COUNT(*) AS total,
                SUM(has_booking) AS bookings,
                SUM(has_handoff) AS handoffs
             FROM (' . $base['sql'] . ') analytics_base
             GROUP BY CASE
                    WHEN COALESCE(NULLIF(initial_intent, ""), "unknown") = "unknown"
                        THEN COALESCE(NULLIF(conversation_type, ""), "unknown")
                    ELSE COALESCE(NULLIF(initial_intent, ""), "unknown")
                END
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
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return [];
        }

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

        $hasMessagesTable = Schema::hasTable('whatsapp_messages');
        $referralType = $hasMessagesTable ? $this->jsonTextExtract('mi.raw_payload', '$.referral.source_type') : '""';
        $referralSourceId = $hasMessagesTable ? $this->jsonTextExtract('mi.raw_payload', '$.referral.source_id') : '""';
        $referralHeadline = $hasMessagesTable ? $this->jsonTextExtract('mi.raw_payload', '$.referral.headline') : '""';
        $referralMediaType = $hasMessagesTable ? $this->jsonTextExtract('mi.raw_payload', '$.referral.media_type') : '""';
        $hasAttributionTable = Schema::hasTable('whatsapp_conversation_attributions');
        $sessionState = $this->jsonTextExtract('s.context', '$.state');
        $sessionConsent = $this->jsonBooleanIsTrue('s.context', '$.consent');
        $attributionJoin = $hasAttributionTable
            ? 'LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category, NULL AS source_type, NULL AS source_id, NULL AS headline, NULL AS media_type, NULL AS initial_intent, NULL AS conversation_type, NULL AS patient_segment) a ON 1 = 0';
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
        $messageJoin = $hasMessagesTable
            ? 'LEFT JOIN (
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
                LEFT JOIN whatsapp_messages mi ON mi.id = first_inbound.first_inbound_id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS direction) ma ON 1 = 0
                LEFT JOIN (SELECT NULL AS conversation_id, NULL AS message_timestamp, NULL AS raw_payload) mi ON 1 = 0';

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
                ' . $messageJoin . '
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
            'faq' => 'Información general',
            'outbound_followup' => 'Seguimiento saliente',
            'campaign_response' => 'Seguimiento saliente',
            'patient_return' => 'Paciente de retorno',
            'post_op_followup' => 'Seguimiento postconsulta',
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
            'agenda_esperando_sede_inicio' => 'Esperando sede',
            'agenda_esperando_sede_directa' => 'Esperando sede',
            'agenda_esperando_procedimiento' => 'Esperando procedimiento',
            'agenda_esperando_dia' => 'Esperando día',
            'agenda_esperando_fecha_general' => 'Esperando fecha',
            'agenda_esperando_horario' => 'Esperando horario',
            'agenda_esperando_horario_general_fecha' => 'Esperando horario',
            'agenda_filtro_sector' => 'Filtro de sector',
            'agenda_confirmar_cita_fecha_general' => 'Pendiente de confirmación',
            'agenda_esperando_medico_general_por_fecha' => 'Esperando médico',
            'agenda_esperando_doctor_directo' => 'Esperando médico',
            'open_unresolved' => 'Abierta sin resolver',
            default => 'Sin fricción clasificada',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function humanAttentionSummary(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, int $slaTargetMinutes = 15): array
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return $this->humanAttentionSummaryFromConversations($fromSql, $toSql, $roleId, $agentId, $slaTargetMinutes);
        }

        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_attention');
        $reply = $this->humanReplySubquery($roleId, $agentId, 'human_attention');
        $handoffStart = $this->handoffStartSubquery($roleId, $agentId, 'human_attention');

        $sql = 'SELECT
                    inbound.conversation_id,
                    inbound.wa_number,
                    inbound.needs_human,
                    inbound.assigned_user_id,
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
        $peopleHandoffSet = [];
        $peopleLostSet = [];
        $attended = 0;
        $lost = 0;
        $lostWithHandoff = 0;
        $abandoned = 0;
        $abandonedNeedsHuman = 0;
        $abandonedWithHandoff = 0;
        $resolved = 0;
        $responseSeconds = [];
        $lostNeedsHuman  = 0;
        $resolvedByBot   = 0;
        $businessSeconds = [];
        $bhCalc          = $this->businessHoursCalculator();

        foreach ($rows as $row) {
            $waNumber = (string) ($row->wa_number ?? '');
            if ($waNumber !== '') {
                $peopleInboundSet[$waNumber] = true;
            }

            $lastInbound = isset($row->last_inbound_at) ? Carbon::parse((string) $row->last_inbound_at) : null;
            $firstInbound = isset($row->first_inbound_at) ? Carbon::parse((string) $row->first_inbound_at) : null;
            $rawFirstReply = isset($row->first_human_reply_at) ? Carbon::parse((string) $row->first_human_reply_at) : null;
            // Only count reply as "attended in this period" if it came AFTER the first inbound of this period.
            // Replies from previous periods must not inflate cobertura.
            $firstReply = ($rawFirstReply !== null && ($firstInbound === null || $rawFirstReply->greaterThanOrEqualTo($firstInbound)))
                ? $rawFirstReply
                : null;
            $handoffRequestedAt = isset($row->handoff_requested_at) ? Carbon::parse((string) $row->handoff_requested_at) : null;
            $firstHandoffAt = isset($row->first_handoff_at) ? Carbon::parse((string) $row->first_handoff_at) : null;
            $responseStart = $handoffRequestedAt ?? $firstHandoffAt;

            if ($responseStart !== null && $waNumber !== '') {
                $peopleHandoffSet[$waNumber] = true;
            }

            $isAssigned = !empty($row->assigned_user_id);
            // "Attended" = human replied (in this period) OR conversation has been assigned to an agent.
            // Assignment without reply still means a human took responsibility.
            if ($firstReply !== null || $isAssigned) {
                $attended++;
                if ($waNumber !== '') {
                    $peopleAttendedSet[$waNumber] = true;
                }
                if ($responseStart !== null && $firstReply !== null && $firstReply->greaterThanOrEqualTo($responseStart)) {
                    $clockSecs = $responseStart->diffInSeconds($firstReply);
                    $responseSeconds[] = $clockSecs;
                    $bizSecs = $bhCalc->businessSecondsElapsed($responseStart, $firstReply);
                    if ($bizSecs >= 0) {
                        $businessSeconds[] = $bizSecs;
                    }
                }
                if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
                    $resolved++;
                }
            } else {
                $lost++;
                $needsHuman = (bool) ($row->needs_human ?? false);
                if ($needsHuman) {
                    $lostNeedsHuman++;
                } else {
                    $resolvedByBot++;
                }
                if ($responseStart !== null) {
                    $lostWithHandoff++;
                }
                if ($waNumber !== '') {
                    $peopleLostSet[$waNumber] = true;
                }
                if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
                    $abandoned++;
                    if ((bool) ($row->needs_human ?? false)) {
                        $abandonedNeedsHuman++;
                    }
                    if ($responseStart !== null) {
                        $abandonedWithHandoff++;
                    }
                }
            }
        }

        $peopleInbound = count($peopleInboundSet);
        $peopleAttended = count($peopleAttendedSet);
        $peopleHandoff = count($peopleHandoffSet);
        $peopleLost = count($peopleLostSet);
        $avgSeconds = $responseSeconds !== [] ? array_sum($responseSeconds) / count($responseSeconds) : null;
        $medianSeconds = $this->median($responseSeconds);

        $intervalPeak = $this->peakOpenConversations($fromSql, $toSql, $roleId, $agentId);

        return [
            'people_inbound' => $peopleInbound,
            'people_handoff' => $peopleHandoff,
            'inbound_conversations_human' => count($rows),
            'conversations_attended_human' => $attended,
            'people_attended_human' => $peopleAttended,
            'conversations_lost' => $lost,
            'people_lost' => $peopleLost,
            'conversations_lost_with_handoff' => $lostWithHandoff,
            'attention_rate' => $peopleHandoff > 0 ? round(($peopleAttended / $peopleHandoff) * 100, 2) : 0.0,
            'loss_rate' => $peopleHandoff > 0 ? round(($peopleLost / $peopleHandoff) * 100, 2) : 0.0,
            'conversations_abandoned' => $abandoned,
            'conversations_abandoned_needs_human' => $abandonedNeedsHuman,
            'conversations_abandoned_with_handoff' => $abandonedWithHandoff,
            'abandonment_rate' => $peopleInbound > 0 ? round(($abandoned / $peopleInbound) * 100, 2) : 0.0,
            'conversations_resolved' => $resolved,
            'avg_first_human_response_seconds' => $avgSeconds !== null ? round($avgSeconds, 2) : null,
            'avg_first_human_response_minutes' => $avgSeconds !== null ? round($avgSeconds / 60, 2) : null,
            'median_first_human_response_seconds' => $medianSeconds !== null ? round($medianSeconds, 2) : null,
            'median_first_human_response_minutes' => $medianSeconds !== null ? round($medianSeconds / 60, 2) : null,
            'p75_first_human_response_minutes' => ($p75s = $this->percentile($responseSeconds, 75)) !== null ? (int) round($p75s / 60) : null,
            'peak_open_conversations' => $intervalPeak['count'],
            'peak_open_at' => $intervalPeak['at'],
            'conversations_lost_needs_human'               => $lostNeedsHuman,
            'conversations_resolved_by_bot'                => $resolvedByBot,
            'p75_business_first_human_response_minutes'    => ($p75b = $this->percentile($businessSeconds, 75)) !== null
                ? round($p75b / 60, 1) : null,
            'median_business_first_human_response_minutes' => ($medb = $this->median($businessSeconds)) !== null
                ? round($medb / 60, 1) : null,
            // SLA real: % de conversaciones respondidas dentro del target (segundos laborales)
            'sla_response_rate' => count($businessSeconds) > 0
                ? round(count(array_filter($businessSeconds, fn ($s) => $s <= $slaTargetMinutes * 60)) / count($businessSeconds) * 100, 1)
                : (count($responseSeconds) > 0
                    ? round(count(array_filter($responseSeconds, fn ($s) => $s <= $slaTargetMinutes * 60)) / count($responseSeconds) * 100, 1)
                    : 0.0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function humanAttentionSummaryFromConversations(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, int $slaTargetMinutes): array
    {
        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, 'human_attention_fallback');
        $sql = 'SELECT
                    COUNT(*) AS total,
                    COUNT(DISTINCT c.wa_number) AS people_inbound,
                    SUM(CASE WHEN c.assigned_user_id IS NOT NULL THEN 1 ELSE 0 END) AS attended,
                    COUNT(DISTINCT CASE WHEN c.assigned_user_id IS NOT NULL THEN c.wa_number END) AS people_attended,
                    SUM(CASE WHEN c.needs_human = 1 THEN 1 ELSE 0 END) AS needs_human,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL THEN 1 ELSE 0 END) AS lost_needs_human,
                    COUNT(DISTINCT CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL THEN c.wa_number END) AS people_lost,
                    SUM(CASE WHEN c.needs_human = 0 THEN 1 ELSE 0 END) AS resolved_by_bot
                FROM whatsapp_conversations c
                LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                WHERE c.created_at >= ? AND c.created_at < ?';
        $params = [$fromSql, $toSql];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }

        $row = DB::selectOne($sql, $params) ?? (object) [];
        $peopleInbound = (int) ($row->people_inbound ?? 0);
        $peopleAttended = (int) ($row->people_attended ?? 0);
        $needsHuman = (int) ($row->needs_human ?? 0);
        $peopleLost = (int) ($row->people_lost ?? 0);
        $lostNeedsHuman = (int) ($row->lost_needs_human ?? 0);
        $attended = (int) ($row->attended ?? 0);
        $resolvedByBot = (int) ($row->resolved_by_bot ?? 0);

        return [
            'people_inbound' => $peopleInbound,
            'people_handoff' => $needsHuman,
            'inbound_conversations_human' => (int) ($row->total ?? 0),
            'conversations_attended_human' => $attended,
            'people_attended_human' => $peopleAttended,
            'conversations_lost' => $lostNeedsHuman,
            'people_lost' => $peopleLost,
            'conversations_lost_with_handoff' => 0,
            'attention_rate' => $needsHuman > 0 ? round(($attended / $needsHuman) * 100, 2) : 0.0,
            'loss_rate' => $needsHuman > 0 ? round(($lostNeedsHuman / $needsHuman) * 100, 2) : 0.0,
            'conversations_abandoned' => 0,
            'conversations_abandoned_needs_human' => 0,
            'conversations_abandoned_with_handoff' => 0,
            'abandonment_rate' => 0.0,
            'conversations_resolved' => $resolvedByBot,
            'avg_first_human_response_seconds' => null,
            'avg_first_human_response_minutes' => null,
            'median_first_human_response_seconds' => null,
            'median_first_human_response_minutes' => null,
            'p75_first_human_response_minutes' => null,
            'peak_open_conversations' => 0,
            'peak_open_at' => null,
            'conversations_lost_needs_human' => $lostNeedsHuman,
            'conversations_resolved_by_bot' => $resolvedByBot,
            'p75_business_first_human_response_minutes' => null,
            'median_business_first_human_response_minutes' => null,
            'sla_response_rate' => 0.0,
            'sla_target_minutes' => $slaTargetMinutes,
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
            'operational_status_requires_attention_today' => 0,
            'operational_status_requires_attention_week'  => 0,
            'operational_status_requires_attention_older' => 0,
            'priority_critical_today'  => 0,
            'priority_critical_week'   => 0,
            'priority_critical_older'  => 0,
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
        $oneDayAgo    = Carbon::now()->subHours(24)->format('Y-m-d H:i:s');
        $sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $params = [$fourHoursAgo, $twentyFourHoursAgo,
                   $oneDayAgo,
                   $oneDayAgo, $sevenDaysAgo,
                   $sevenDaysAgo,
                   $oneDayAgo,
                   $oneDayAgo, $sevenDaysAgo,
                   $sevenDaysAgo];
        $sql = 'SELECT
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "" AND NOT (' . $scheduledSql . ') THEN 1 ELSE 0 END) AS status_new,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL THEN 1 ELSE 0 END) AS status_requires_attention,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND c.last_message_direction = "inbound" THEN 1 ELSE 0 END) AS status_in_progress,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NOT NULL AND (c.last_message_direction <> "inbound" OR c.last_message_direction IS NULL) THEN 1 ELSE 0 END) AS status_waiting_patient,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "" AND (' . $scheduledSql . ') THEN 1 ELSE 0 END) AS status_scheduled,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "resolved" THEN 1 ELSE 0 END) AS status_resolved,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' = "followup_closed" THEN 1 ELSE 0 END) AS status_closed_followup,
                    SUM(CASE WHEN c.needs_human = 0 AND ' . $closedReasonSql . ' IN ("not_interested", "no_response", "duplicate", "scheduled_elsewhere") THEN 1 ELSE 0 END) AS status_closed_other,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.created_at >= ? THEN 1 ELSE 0 END) AS req_attention_today,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.created_at < ? AND c.created_at >= ? THEN 1 ELSE 0 END) AS req_attention_week,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.created_at < ? THEN 1 ELSE 0 END) AS req_attention_older,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0 AND c.created_at >= ? THEN 1 ELSE 0 END) AS priority_critical_today,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0 AND c.created_at < ? AND c.created_at >= ? THEN 1 ELSE 0 END) AS priority_critical_week,
                    SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0 AND c.created_at < ? THEN 1 ELSE 0 END) AS priority_critical_older,
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
        $defaults['operational_status_requires_attention_today'] = (int) ($row->req_attention_today ?? 0);
        $defaults['operational_status_requires_attention_week']  = (int) ($row->req_attention_week  ?? 0);
        $defaults['operational_status_requires_attention_older'] = (int) ($row->req_attention_older ?? 0);
        $defaults['priority_critical_today']  = (int) ($row->priority_critical_today ?? 0);
        $defaults['priority_critical_week']   = (int) ($row->priority_critical_week  ?? 0);
        $defaults['priority_critical_older']  = (int) ($row->priority_critical_older ?? 0);
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
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [
                'live_queue_queued' => 0,
                'live_queue_assigned' => 0,
                'live_queue_assigned_overdue' => 0,
                'live_queue_total' => 0,
                'live_queue_today' => 0,
                'live_queue_backlog' => 0,
            ];
        }

        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'live_queue');
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $sql = 'SELECT
                    h.status,
                    h.assigned_until,
                    COALESCE(h.queued_at, h.created_at) AS entered_at
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
        $queuedToday   = 0;
        $queuedBacklog = 0;
        $threshold24hQ = Carbon::now()->subHours(24);

        foreach ($rows as $row) {
            $status = (string) ($row->status ?? '');
            $assignedUntil = $row->assigned_until ?? null;
            $enteredAt = isset($row->entered_at) ? Carbon::parse((string) $row->entered_at) : null;
            $isToday   = $enteredAt !== null && $enteredAt->greaterThan($threshold24hQ);

            if ($status === 'queued') {
                $queued++;
                $isToday ? $queuedToday++ : $queuedBacklog++;
                continue;
            }

            if ($status !== 'assigned') {
                continue;
            }

            if ($assignedUntil === null || (string) $assignedUntil > $now) {
                $assigned++;
                $isToday ? $queuedToday++ : $queuedBacklog++;
                continue;
            }

            $overdue++;
            $isToday ? $queuedToday++ : $queuedBacklog++;
        }

        return [
            'live_queue_queued' => $queued,
            'live_queue_assigned' => $assigned,
            'live_queue_assigned_overdue' => $overdue,
            'live_queue_total' => $queued + $assigned + $overdue,
            'live_queue_today'   => $queuedToday,
            'live_queue_backlog' => $queuedBacklog,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationWindowSummary(?int $roleId, ?int $agentId): array
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return [
                'queue_conversations_total' => 0,
                'queue_window_open' => 0,
                'queue_needs_template' => 0,
                'queue_awaiting_template_reply' => 0,
                'queue_window_open_rate' => 0.0,
                'queue_needs_template_rate' => 0.0,
                'queue_awaiting_template_reply_rate' => 0.0,
            ];
        }

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
                WHERE c.needs_human = 1';
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
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [
                'sla_target_minutes' => $targetMinutes,
                'sla_assignments_total' => 0,
                'sla_assignments_in_target' => 0,
                'sla_assignments_rate' => 0.0,
            ];
        }

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
        if (!Schema::hasTable('whatsapp_handoff_events') || !Schema::hasTable('whatsapp_handoffs')) {
            return 0;
        }

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
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

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
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

        // Muestra carga vigente e histórica reciente por agente para no perder handoffs ya cerrados.
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'agent');
        $sql = 'SELECT
                    h.assigned_agent_id AS user_id,
                    ' . $this->agentNameSql('u', 'h.assigned_agent_id', 'Usuario #') . ' AS agent_name,
                    COUNT(*) AS assigned_count,
                    SUM(CASE WHEN h.status = "assigned" THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN h.status = "resolved" THEN 1 ELSE 0 END) AS resolved_count
                FROM whatsapp_handoffs h
                LEFT JOIN users u ON u.id = h.assigned_agent_id
                WHERE h.assigned_agent_id IS NOT NULL
                  AND h.status IN ("assigned", "queued", "resolved")';
        $params = [];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_values($filter['params']);
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
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_agent');
        $reply = $this->firstHumanReplyByAgentSubquery($scope, $roleId, $agentId, 'human_agent');

        // Traer filas individuales para calcular P75 en PHP (evita distorsión por outliers)
        $sql = 'SELECT
                    first_reply.assigned_agent_id AS user_id,
                    ' . $this->agentNameSql('u', 'first_reply.assigned_agent_id', 'Usuario #') . ' AS agent_name,
                    first_reply.assigned_at,
                    first_reply.first_human_reply_at,
                    ' . ($this->isSqlite()
                        ? '(julianday(first_reply.first_human_reply_at) - julianday(first_reply.assigned_at)) * 86400'
                        : 'TIMESTAMPDIFF(SECOND, first_reply.assigned_at, first_reply.first_human_reply_at)') . ' AS response_seconds
                FROM (' . $reply['sql'] . ') first_reply
                LEFT JOIN users u ON u.id = first_reply.assigned_agent_id
                ORDER BY first_reply.assigned_agent_id';

        $rows = DB::select($sql, array_values($reply['params']));

        $bhCalc = $this->businessHoursCalculator();
        $agents = [];
        foreach ($rows as $row) {
            $uid = (int) ($row->user_id ?? 0);
            if (!isset($agents[$uid])) {
                $agents[$uid] = [
                    'user_id'     => $uid,
                    'agent_name'  => (string) ($row->agent_name ?? ''),
                    'seconds'     => [],
                    'biz_seconds' => [],
                ];
            }
            $assignedAt = isset($row->assigned_at) ? Carbon::parse((string) $row->assigned_at) : null;
            $repliedAt  = isset($row->first_human_reply_at) ? Carbon::parse((string) $row->first_human_reply_at) : null;

            if ($assignedAt !== null && $repliedAt !== null && $repliedAt->greaterThanOrEqualTo($assignedAt)) {
                $clock = $assignedAt->diffInSeconds($repliedAt);
                $biz   = $bhCalc->businessSecondsElapsed($assignedAt, $repliedAt);
                $agents[$uid]['seconds'][]     = $clock;
                $agents[$uid]['biz_seconds'][] = $biz;
            }
        }

        return array_values(array_map(function (array $agent): array {
            $p75    = $this->percentile($agent['seconds'], 75);
            $p75biz = $this->percentile($agent['biz_seconds'], 75);
            return [
                'user_id'                        => $agent['user_id'],
                'agent_name'                     => $agent['agent_name'],
                'attended_conversations'          => count($agent['seconds']),
                'p75_first_response_minutes'      => $p75 !== null ? round($p75 / 60, 1) : null,
                'p75_business_response_minutes'   => $p75biz !== null ? round($p75biz / 60, 1) : null,
            ];
        }, $agents));
    }

    /**
     * Estado en vivo por agente: conversaciones asignadas, sin leer y espera máxima.
     *
     * @return array<int, array<string, mixed>>
     */
    public function agentLiveStatus(?int $roleId = null, ?int $agentId = null): array
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return [];
        }

        $filter = $this->conversationScopeFilterSql('c', 'u', $roleId, $agentId, 'agent_live');
        $lastMsgAt = Schema::hasColumn('whatsapp_conversations', 'last_message_at')
            ? 'c.last_message_at'
            : 'NULL';
        $cutoff7d = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $unreadWaitMinutesSql = $this->isSqlite()
            ? 'CAST(((julianday("now") - julianday(' . $lastMsgAt . ')) * 1440) AS INTEGER)'
            : 'TIMESTAMPDIFF(MINUTE, ' . $lastMsgAt . ', NOW())';

        $sql = 'SELECT
                    c.assigned_user_id AS user_id,
                    ' . $this->agentNameSql('u', 'c.assigned_user_id', 'Agente') . ' AS agent_name,
                    COUNT(*) AS active_conversations,
                    SUM(CASE WHEN c.unread_count > 0 THEN 1 ELSE 0 END) AS unread_conversations,
                    MAX(CASE WHEN c.unread_count > 0 AND c.last_message_direction = "inbound"
                             THEN ' . $unreadWaitMinutesSql . '
                             ELSE 0 END) AS max_unread_wait_minutes
                FROM whatsapp_conversations c
                LEFT JOIN users u ON u.id = c.assigned_user_id
                WHERE c.needs_human = 1
                  AND c.assigned_user_id IS NOT NULL
                  AND COALESCE(' . $lastMsgAt . ', c.updated_at, c.created_at) >= ?';

        $params = [$cutoff7d];
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
            $params = array_merge($params, array_values($filter['params']));
        }
        $sql .= ' GROUP BY c.assigned_user_id, agent_name
                  ORDER BY unread_conversations DESC, active_conversations DESC';

        return array_map(fn ($row) => [
            'user_id'              => (int) ($row->user_id ?? 0),
            'agent_name'           => (string) ($row->agent_name ?? ''),
            'active_conversations' => (int) ($row->active_conversations ?? 0),
            'unread_conversations' => (int) ($row->unread_conversations ?? 0),
            'max_unread_wait_minutes' => (int) ($row->max_unread_wait_minutes ?? 0),
        ], DB::select($sql, $params));
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

        $bhCalc  = $this->businessHoursCalculator();
        $buckets = [];
        foreach (DB::select($sql, $params) as $row) {
            $queue = $this->operationalQueueFromHandoffRow($row);
            if (!isset($buckets[$queue])) {
                $buckets[$queue] = [
                    'queue'            => $queue,
                    'label'            => $this->operationalQueueLabel($queue),
                    'total_handoffs'   => 0,
                    'attended_handoffs' => 0,
                    'pending_handoffs' => 0,
                    'response_seconds' => [],
                    'biz_seconds'      => [],
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
                    $buckets[$queue]['biz_seconds'][]      = $bhCalc->businessSecondsElapsed($responseStart, $firstReply);
                }
            } else {
                $buckets[$queue]['pending_handoffs']++;
            }
        }

        $order = ['critical_backlog' => 0, 'captacion' => 1, 'operacion' => 2, 'informacion' => 3];
        $rows = array_map(function (array $bucket): array {
            $seconds    = $bucket['response_seconds'];
            $bizSeconds = $bucket['biz_seconds'] ?? [];
            $p75        = $this->percentile($seconds, 75);
            $median     = $this->median($seconds);
            $p75biz     = $this->percentile($bizSeconds, 75);

            unset($bucket['response_seconds'], $bucket['biz_seconds']);
            $bucket['p75_first_response_minutes']    = $p75 !== null ? round($p75 / 60, 1) : null;
            $bucket['median_first_response_minutes'] = $median !== null ? round($median / 60, 1) : null;
            $bucket['p75_business_response_minutes'] = $p75biz !== null ? round($p75biz / 60, 1) : null;
            $bucket['response_rate'] = $bucket['total_handoffs'] > 0
                ? round(($bucket['attended_handoffs'] / $bucket['total_handoffs']) * 100, 1)
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
        if (!Schema::hasTable('whatsapp_handoff_events') || !Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

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

    /**
     * @return array<int, array{source_category:string,source_label:string,total:int}>
     */
    private function sigcenterBookingsBySource(string $fromSql, string $toSql): array
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return [];
        }

        $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
            ? 'LEFT JOIN whatsapp_conversation_attributions attr ON attr.conversation_id = b.conversation_id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category) attr ON 1 = 0';

        return array_map(fn ($row) => [
            'source_category' => (string) ($row->source_category ?? 'unknown'),
            'source_label' => $this->sourceCategoryLabel((string) ($row->source_category ?? 'unknown')),
            'total' => (int) ($row->total ?? 0),
        ], DB::select(
            'SELECT source_category, COUNT(*) AS total
             FROM (
                SELECT COALESCE(NULLIF(attr.source_category, ""), "unknown") AS source_category
                FROM whatsapp_sigcenter_bookings b
                ' . $attributionJoin . '
                WHERE b.status = "created" AND b.created_at >= ? AND b.created_at < ?
             ) booking_sources
             GROUP BY source_category
             ORDER BY total DESC, source_category ASC',
            [$fromSql, $toSql]
        ));
    }

    /**
     * @return array{
     *     summary:array<string,int>,
     *     trend_rows:array<int,array{period_date:string,total:int}>,
     *     by_agent:array<int,array<string,mixed>>,
     *     by_sede:array<int,array<string,mixed>>,
     *     by_source:array<int,array<string,mixed>>
     * }
     */
    private function humanAppointmentAttribution(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $empty = $this->emptyHumanAppointmentAttribution();
        if (!Schema::hasTable('procedimiento_proyectado')
            || !Schema::hasColumn('procedimiento_proyectado', 'hc_number')
            || !Schema::hasColumn('procedimiento_proyectado', 'fecha')
            || !Schema::hasTable('whatsapp_conversations')
        ) {
            return $empty;
        }

        $events = $this->humanAppointmentEventRows($fromSql, $toSql, $roleId, $agentId);
        if ($events === []) {
            return $empty;
        }

        $conversations = [];
        $phoneTails = [];
        foreach ($events as $row) {
            $conversationId = (int) ($row->conversation_id ?? 0);
            $eventAt = isset($row->event_at) ? Carbon::parse((string) $row->event_at) : null;
            if ($conversationId <= 0 || $eventAt === null) {
                continue;
            }

            $waNumber = (string) ($row->wa_number ?? '');
            $tail = $this->phoneTail($waNumber);
            $patientHcNumber = trim((string) ($row->patient_hc_number ?? ''));
            if ($patientHcNumber === '' && $tail !== '') {
                $phoneTails[$tail] = true;
            }

            $agentFromEvent = (int) ($row->agent_id ?? 0);
            $assignedUserId = (int) ($row->assigned_user_id ?? 0);
            $agentForEvent = $agentFromEvent > 0 ? $agentFromEvent : ($assignedUserId > 0 ? $assignedUserId : null);

            if (!isset($conversations[$conversationId])) {
                $conversations[$conversationId] = [
                    'conversation_id' => $conversationId,
                    'wa_number' => $waNumber,
                    'phone_tail' => $tail,
                    'patient_hc_number' => $patientHcNumber,
                    'source_category' => (string) ($row->source_category ?? 'unknown'),
                    'first_human_at' => $eventAt,
                    'last_human_at' => $eventAt,
                    'last_agent_id' => $agentForEvent,
                ];
                continue;
            }

            if ($eventAt->lessThan($conversations[$conversationId]['first_human_at'])) {
                $conversations[$conversationId]['first_human_at'] = $eventAt;
            }
            if ($eventAt->greaterThanOrEqualTo($conversations[$conversationId]['last_human_at'])) {
                $conversations[$conversationId]['last_human_at'] = $eventAt;
                if ($agentForEvent !== null) {
                    $conversations[$conversationId]['last_agent_id'] = $agentForEvent;
                }
            }
        }

        if ($conversations === []) {
            return $empty;
        }

        $hcByTail = $this->patientHcByPhoneTail(array_keys($phoneTails));
        $conversationsByHc = [];
        foreach ($conversations as $conversation) {
            $hcNumber = $conversation['patient_hc_number'];
            if ($hcNumber === '' && $conversation['phone_tail'] !== '') {
                $hcNumber = (string) ($hcByTail[$conversation['phone_tail']] ?? '');
            }
            if ($hcNumber === '') {
                continue;
            }
            $conversation['patient_hc_number'] = $hcNumber;
            $conversationsByHc[$hcNumber][] = $conversation;
        }

        if ($conversationsByHc === []) {
            return $empty;
        }

        $appointments = [];
        $hcNumbers = array_keys($conversationsByHc);
        foreach (array_chunk($hcNumbers, 500) as $hcChunk) {
            $query = DB::table('procedimiento_proyectado')
                ->select([
                    'form_id',
                    'hc_number',
                    'fecha',
                    'created_at',
                ])
                ->whereIn('hc_number', $hcChunk)
                ->whereNotNull('fecha');

            if (Schema::hasColumn('procedimiento_proyectado', 'hora')) {
                $query->addSelect('hora');
            }
            if (Schema::hasColumn('procedimiento_proyectado', 'sede_departamento')) {
                $query->addSelect('sede_departamento');
            }
            if (Schema::hasColumn('procedimiento_proyectado', 'sigcenter_present')) {
                $query->where('sigcenter_present', 1);
            }

            foreach ($query->get() as $appointment) {
                $appointments[] = $appointment;
            }
        }

        if ($appointments === []) {
            return $empty;
        }

        $botExclusions = $this->botAppointmentExclusions($fromSql, $toSql);
        $agentIds = [];
        $strongSlots = [];
        $strongForms = [];
        $strongConversations = [];
        $strongPatients = [];
        $mediumSlots = [];
        $mediumForms = [];
        $mediumConversations = [];
        $mediumPatients = [];
        $weakSlots = [];
        $trendSlotDates = [];
        $agentGroups = [];
        $sedeGroups = [];
        $sourceGroups = [];

        foreach ($appointments as $appointment) {
            $hcNumber = trim((string) ($appointment->hc_number ?? ''));
            $appointmentDate = $this->dateOnly($appointment->fecha ?? null);
            if ($hcNumber === '' || $appointmentDate === '' || empty($conversationsByHc[$hcNumber])) {
                continue;
            }

            $appointmentTime = $this->timeOnly($appointment->hora ?? null);
            $createdAt = isset($appointment->created_at) ? Carbon::parse((string) $appointment->created_at) : null;
            if ($createdAt === null) {
                continue;
            }

            $slotKey = $hcNumber . '|' . $appointmentDate . '|' . $appointmentTime;
            if (isset($botExclusions['hc_slots'][$slotKey])
                || (isset($botExclusions['hc_dates'][$hcNumber . '|' . $appointmentDate]) && $appointmentTime === '')
            ) {
                continue;
            }

            foreach ($conversationsByHc[$hcNumber] as $conversation) {
                $conversationId = (int) $conversation['conversation_id'];
                if (isset($botExclusions['conversation_dates'][$conversationId . '|' . $appointmentDate])) {
                    continue;
                }

                $firstHumanAt = $conversation['first_human_at'];
                $lastHumanAt = $conversation['last_human_at'];
                $minAppointmentDate = $firstHumanAt->copy()->startOfDay();
                $maxAppointmentDate = $firstHumanAt->copy()->addDays(30)->endOfDay();
                $appointmentDay = Carbon::parse($appointmentDate)->startOfDay();
                if ($appointmentDay->lessThan($minAppointmentDate) || $appointmentDay->greaterThan($maxAppointmentDate)) {
                    continue;
                }

                $strongWindowStart = $firstHumanAt->copy()->subMinutes(15);
                $strongWindowEnd = $lastHumanAt->copy()->addDay();
                $mediumWindowEnd = $lastHumanAt->copy()->addDays(3);
                $weakWindowEnd = $firstHumanAt->copy()->addDays(30);
                $formId = (string) ($appointment->form_id ?? $slotKey);
                $agentIdForGroup = (int) ($conversation['last_agent_id'] ?? 0);
                $sourceCategory = (string) ($conversation['source_category'] ?? 'unknown');
                $sourceCategory = $sourceCategory !== '' ? $sourceCategory : 'unknown';
                $sedeNombre = trim((string) ($appointment->sede_departamento ?? ''));
                $sedeNombre = $sedeNombre !== '' ? $sedeNombre : 'Sin sede';

                if ($createdAt->betweenIncluded($strongWindowStart, $strongWindowEnd)) {
                    $strongSlots[$slotKey] = true;
                    $strongForms[$formId] = true;
                    $strongConversations[$conversationId] = true;
                    $strongPatients[$hcNumber] = true;
                    $trendSlotDates[$slotKey] = $createdAt->toDateString();
                    $mediumSlots[$slotKey] = true;
                    $mediumForms[$formId] = true;
                    $mediumConversations[$conversationId] = true;
                    $mediumPatients[$hcNumber] = true;

                    if ($agentIdForGroup > 0) {
                        $agentIds[$agentIdForGroup] = true;
                        if (!isset($agentGroups[$agentIdForGroup])) {
                            $agentGroups[$agentIdForGroup] = [
                                'user_id' => $agentIdForGroup,
                                'agent_name' => '',
                                'slot_keys' => [],
                                'conversation_ids' => [],
                                'patient_hcs' => [],
                            ];
                        }
                        $agentGroups[$agentIdForGroup]['slot_keys'][$slotKey] = true;
                        $agentGroups[$agentIdForGroup]['conversation_ids'][$conversationId] = true;
                        $agentGroups[$agentIdForGroup]['patient_hcs'][$hcNumber] = true;
                    }

                    if (!isset($sedeGroups[$sedeNombre])) {
                        $sedeGroups[$sedeNombre] = [
                            'sede_nombre' => $sedeNombre,
                            'slot_keys' => [],
                            'conversation_ids' => [],
                            'patient_hcs' => [],
                        ];
                    }
                    $sedeGroups[$sedeNombre]['slot_keys'][$slotKey] = true;
                    $sedeGroups[$sedeNombre]['conversation_ids'][$conversationId] = true;
                    $sedeGroups[$sedeNombre]['patient_hcs'][$hcNumber] = true;

                    if (!isset($sourceGroups[$sourceCategory])) {
                        $sourceGroups[$sourceCategory] = [
                            'source_category' => $sourceCategory,
                            'source_label' => $this->sourceCategoryLabel($sourceCategory),
                            'slot_keys' => [],
                            'conversation_ids' => [],
                            'patient_hcs' => [],
                        ];
                    }
                    $sourceGroups[$sourceCategory]['slot_keys'][$slotKey] = true;
                    $sourceGroups[$sourceCategory]['conversation_ids'][$conversationId] = true;
                    $sourceGroups[$sourceCategory]['patient_hcs'][$hcNumber] = true;
                    continue;
                }

                if ($createdAt->betweenIncluded($strongWindowStart, $mediumWindowEnd)) {
                    $mediumSlots[$slotKey] = true;
                    $mediumForms[$formId] = true;
                    $mediumConversations[$conversationId] = true;
                    $mediumPatients[$hcNumber] = true;
                    continue;
                }

                if ($createdAt->betweenIncluded($strongWindowStart, $weakWindowEnd)) {
                    $weakSlots[$slotKey] = true;
                }
            }
        }

        $agentNames = $this->agentNamesById(array_keys($agentIds));
        $byAgent = array_values(array_map(function (array $group) use ($agentNames): array {
            $userId = (int) $group['user_id'];
            return [
                'user_id' => $userId,
                'agent_name' => (string) ($agentNames[$userId] ?? ('Agente #' . $userId)),
                'appointment_slots' => count($group['slot_keys']),
                'conversations' => count($group['conversation_ids']),
                'patients' => count($group['patient_hcs']),
            ];
        }, $agentGroups));
        usort($byAgent, fn (array $a, array $b): int => ($b['appointment_slots'] <=> $a['appointment_slots']) ?: strcmp($a['agent_name'], $b['agent_name']));

        $bySede = array_values(array_map(fn (array $group): array => [
            'sede_nombre' => (string) $group['sede_nombre'],
            'appointment_slots' => count($group['slot_keys']),
            'conversations' => count($group['conversation_ids']),
            'patients' => count($group['patient_hcs']),
        ], $sedeGroups));
        usort($bySede, fn (array $a, array $b): int => ($b['appointment_slots'] <=> $a['appointment_slots']) ?: strcmp($a['sede_nombre'], $b['sede_nombre']));

        $bySource = array_values(array_map(fn (array $group): array => [
            'source_category' => (string) $group['source_category'],
            'source_label' => (string) $group['source_label'],
            'appointment_slots' => count($group['slot_keys']),
            'conversations' => count($group['conversation_ids']),
            'patients' => count($group['patient_hcs']),
        ], $sourceGroups));
        usort($bySource, fn (array $a, array $b): int => ($b['appointment_slots'] <=> $a['appointment_slots']) ?: strcmp($a['source_label'], $b['source_label']));

        $trendCounts = [];
        foreach ($trendSlotDates as $date) {
            $trendCounts[$date] = ($trendCounts[$date] ?? 0) + 1;
        }
        ksort($trendCounts);

        return [
            'summary' => [
                'human_attributed_appointments_strong' => count($strongSlots),
                'human_attributed_forms_strong' => count($strongForms),
                'human_attributed_appointment_conversations_strong' => count($strongConversations),
                'human_attributed_appointment_patients_strong' => count($strongPatients),
                'human_attributed_appointments_medium' => count($mediumSlots),
                'human_attributed_forms_medium' => count($mediumForms),
                'human_attributed_appointment_conversations_medium' => count($mediumConversations),
                'human_attributed_appointment_patients_medium' => count($mediumPatients),
                'human_attributed_appointments_weak' => count($weakSlots),
            ],
            'trend_rows' => array_map(
                fn (string $date, int $total): array => ['period_date' => $date, 'total' => $total],
                array_keys($trendCounts),
                array_values($trendCounts)
            ),
            'by_agent' => array_slice($byAgent, 0, 20),
            'by_sede' => array_slice($bySede, 0, 20),
            'by_source' => array_slice($bySource, 0, 20),
        ];
    }

    /**
     * @return array{
     *     summary:array<string,int>,
     *     trend_rows:array<int,array{period_date:string,total:int}>,
     *     by_agent:array<int,array<string,mixed>>,
     *     by_sede:array<int,array<string,mixed>>,
     *     by_source:array<int,array<string,mixed>>
     * }
     */
    private function emptyHumanAppointmentAttribution(): array
    {
        return [
            'summary' => [
                'human_attributed_appointments_strong' => 0,
                'human_attributed_forms_strong' => 0,
                'human_attributed_appointment_conversations_strong' => 0,
                'human_attributed_appointment_patients_strong' => 0,
                'human_attributed_appointments_medium' => 0,
                'human_attributed_forms_medium' => 0,
                'human_attributed_appointment_conversations_medium' => 0,
                'human_attributed_appointment_patients_medium' => 0,
                'human_attributed_appointments_weak' => 0,
            ],
            'trend_rows' => [],
            'by_agent' => [],
            'by_sede' => [],
            'by_source' => [],
        ];
    }

    /**
     * @return array<int, object>
     */
    private function humanAppointmentEventRows(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $allowedAgents = $this->allowedAgentIds($roleId, $agentId);
        if (($roleId !== null && $roleId > 0 || $agentId !== null && $agentId > 0) && $allowedAgents === []) {
            return [];
        }

        $patientHcSql = Schema::hasColumn('whatsapp_conversations', 'patient_hc_number') ? 'c.patient_hc_number' : 'NULL';
        $assignedUserSql = Schema::hasColumn('whatsapp_conversations', 'assigned_user_id') ? 'c.assigned_user_id' : 'NULL';
        $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
            ? 'LEFT JOIN whatsapp_conversation_attributions attr ON attr.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category) attr ON 1 = 0';
        $selectPrefix = 'c.id AS conversation_id, c.wa_number, ' . $patientHcSql . ' AS patient_hc_number, ' . $assignedUserSql . ' AS assigned_user_id, COALESCE(NULLIF(attr.source_category, ""), "unknown") AS source_category';
        $queries = [];
        $params = [];

        if (Schema::hasTable('whatsapp_messages')
            && Schema::hasColumn('whatsapp_messages', 'sender_type')
            && Schema::hasColumn('whatsapp_messages', 'sender_id')
        ) {
            $eventAt = 'COALESCE(m.message_timestamp, m.created_at)';
            $queries[] = 'SELECT ' . $selectPrefix . ', ' . $eventAt . ' AS event_at, m.sender_id AS agent_id
                          FROM whatsapp_messages m
                          INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                          ' . $attributionJoin . '
                          WHERE m.direction = "outbound"
                            AND m.sender_type = "agent"
                            AND m.sender_id IS NOT NULL
                            AND ' . $eventAt . ' >= ?
                            AND ' . $eventAt . ' < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if (Schema::hasTable('whatsapp_handoffs')
            && Schema::hasColumn('whatsapp_handoffs', 'assigned_agent_id')
        ) {
            $eventAt = Schema::hasColumn('whatsapp_handoffs', 'assigned_at')
                ? 'COALESCE(h.assigned_at, h.created_at)'
                : 'h.created_at';
            $queries[] = 'SELECT ' . $selectPrefix . ', ' . $eventAt . ' AS event_at, h.assigned_agent_id AS agent_id
                          FROM whatsapp_handoffs h
                          INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                          ' . $attributionJoin . '
                          WHERE h.assigned_agent_id IS NOT NULL
                            AND ' . $eventAt . ' >= ?
                            AND ' . $eventAt . ' < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if (Schema::hasTable('whatsapp_handoff_events')
            && Schema::hasTable('whatsapp_handoffs')
            && Schema::hasColumn('whatsapp_handoff_events', 'actor_user_id')
        ) {
            $queries[] = 'SELECT ' . $selectPrefix . ', e.created_at AS event_at, e.actor_user_id AS agent_id
                          FROM whatsapp_handoff_events e
                          INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                          INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                          ' . $attributionJoin . '
                          WHERE e.actor_user_id IS NOT NULL
                            AND e.created_at >= ?
                            AND e.created_at < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if ($queries === []) {
            return [];
        }

        $rows = DB::select(implode(' UNION ALL ', $queries), $params);
        if ($allowedAgents === null) {
            return $rows;
        }

        return array_values(array_filter($rows, function (object $row) use ($allowedAgents): bool {
            $agentId = (int) ($row->agent_id ?? 0);
            $assignedUserId = (int) ($row->assigned_user_id ?? 0);
            return ($agentId > 0 && isset($allowedAgents[$agentId]))
                || ($assignedUserId > 0 && isset($allowedAgents[$assignedUserId]));
        }));
    }

    /**
     * @return ?array<int,true>
     */
    private function allowedAgentIds(?int $roleId, ?int $agentId): ?array
    {
        if ($agentId !== null && $agentId > 0) {
            return [$agentId => true];
        }
        if ($roleId === null || $roleId <= 0 || !Schema::hasTable('users') || !Schema::hasColumn('users', 'role_id')) {
            return null;
        }

        $ids = [];
        foreach (DB::table('users')->select('id')->where('role_id', $roleId)->get() as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    /**
     * @param array<int,string> $phoneTails
     * @return array<string,string>
     */
    private function patientHcByPhoneTail(array $phoneTails): array
    {
        if ($phoneTails === []
            || !Schema::hasTable('patient_data')
            || !Schema::hasColumn('patient_data', 'hc_number')
            || !Schema::hasColumn('patient_data', 'celular')
        ) {
            return [];
        }

        $wanted = array_fill_keys($phoneTails, true);
        $matches = [];
        foreach (DB::table('patient_data')->select(['hc_number', 'celular'])->whereNotNull('celular')->get() as $row) {
            $tail = $this->phoneTail((string) ($row->celular ?? ''));
            $hcNumber = trim((string) ($row->hc_number ?? ''));
            if ($tail !== '' && $hcNumber !== '' && isset($wanted[$tail])) {
                $matches[$tail] = $hcNumber;
            }
        }
        return $matches;
    }

    /**
     * @return array{conversation_dates:array<string,true>,hc_slots:array<string,true>,hc_dates:array<string,true>}
     */
    private function botAppointmentExclusions(string $fromSql, string $toSql): array
    {
        $exclusions = [
            'conversation_dates' => [],
            'hc_slots' => [],
            'hc_dates' => [],
        ];

        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return $exclusions;
        }

        $query = DB::table('whatsapp_sigcenter_bookings')
            ->select(['conversation_id', 'wa_number', 'created_at', 'booked_at', 'status'])
            ->whereIn('status', ['created', 'confirmed'])
            ->where('created_at', '>=', $fromSql)
            ->where('created_at', '<', $toSql);

        if (Schema::hasColumn('whatsapp_sigcenter_bookings', 'patient_hc_number')) {
            $query->addSelect('patient_hc_number');
        }
        if (Schema::hasColumn('whatsapp_sigcenter_bookings', 'fecha_inicio')) {
            $query->addSelect('fecha_inicio');
        }

        foreach ($query->get() as $booking) {
            $conversationId = (int) ($booking->conversation_id ?? 0);
            $hcNumber = trim((string) ($booking->patient_hc_number ?? ''));
            $appointmentDate = $this->dateOnly($booking->fecha_inicio ?? null);
            if ($appointmentDate === '') {
                $appointmentDate = $this->dateOnly($booking->booked_at ?? $booking->created_at ?? null);
            }
            $appointmentTime = $this->timeOnly($booking->fecha_inicio ?? null);

            if ($conversationId > 0 && $appointmentDate !== '') {
                $exclusions['conversation_dates'][$conversationId . '|' . $appointmentDate] = true;
            }
            if ($hcNumber !== '' && $appointmentDate !== '') {
                $exclusions['hc_dates'][$hcNumber . '|' . $appointmentDate] = true;
                $exclusions['hc_slots'][$hcNumber . '|' . $appointmentDate . '|' . $appointmentTime] = true;
            }
        }

        return $exclusions;
    }

    /**
     * @param array<int,int|string> $agentIds
     * @return array<int,string>
     */
    private function agentNamesById(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if ($agentIds === [] || !Schema::hasTable('users')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($agentIds), '?'));
        $rows = DB::select(
            'SELECT id, ' . $this->agentNameSql(null, 'id', 'Agente #') . ' AS agent_name
             FROM users
             WHERE id IN (' . $placeholders . ')',
            $agentIds
        );

        $names = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id > 0) {
                $names[$id] = (string) ($row->agent_name ?? ('Agente #' . $id));
            }
        }
        return $names;
    }

    private function phoneTail(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return $digits !== '' ? substr($digits, -9) : '';
    }

    private function dateOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return Carbon::parse((string) $value)->toDateString();
    }

    private function timeOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return Carbon::parse((string) $value)->format('H:i:s');
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
     * @return array<int, int>
     */
    private function emptyTrend(DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        return array_fill(0, count($this->dateLabels($from, $toExclusive)), 0);
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
            if (Schema::hasTable('whatsapp_handoffs')) {
                $conditions[] = '(' . $conversationAlias . '.assigned_user_id = ? OR EXISTS (
                SELECT 1 FROM whatsapp_handoffs wh_scope
                WHERE wh_scope.conversation_id = ' . $conversationAlias . '.id
                  AND wh_scope.assigned_agent_id = ?
            ))';
                $params[$scope . '_agent_current']    = $agentId;
                $params[$scope . '_agent_historical'] = $agentId;
            } else {
                $conditions[] = $conversationAlias . '.assigned_user_id = ?';
                $params[$scope . '_agent_current'] = $agentId;
            }
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
        if (!Schema::hasTable('whatsapp_messages')) {
            $sql = 'SELECT
                        c.id AS conversation_id,
                        c.wa_number,
                        c.needs_human,
                        c.assigned_user_id,
                        ' . $handoffRequestedSelect . ',
                        c.created_at AS first_inbound_at,
                        COALESCE(c.last_message_at, c.updated_at, c.created_at) AS last_inbound_at
                    FROM whatsapp_conversations c
                    LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id
                    WHERE c.created_at >= ?
                      AND c.created_at < ?';
            $params = [$fromSql, $toSql];
            if ($filter['where'] !== '') {
                $sql .= ' AND ' . $filter['where'];
                $params = array_merge($params, array_values($filter['params']));
            }

            return ['sql' => $sql, 'params' => $params];
        }

        $sql = 'SELECT
                    c.id AS conversation_id,
                    c.wa_number,
                    c.needs_human,
                    c.assigned_user_id,
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
        $sql .= ' GROUP BY c.id, c.wa_number, c.needs_human, c.assigned_user_id';
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
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasTable('whatsapp_handoffs')) {
            return [
                'sql' => 'SELECT NULL AS conversation_id, NULL AS first_human_reply_at WHERE 1 = 0',
                'params' => [],
            ];
        }

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
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasTable('whatsapp_handoffs')) {
            return [
                'sql' => 'SELECT NULL AS conversation_id, NULL AS assigned_agent_id, NULL AS assigned_at, NULL AS first_human_reply_at WHERE 1 = 0',
                'params' => [],
            ];
        }

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
                SUM(CASE WHEN delivered_at IS NOT NULL OR responded_at IS NOT NULL THEN 1 ELSE 0 END) AS delivered,
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
        $summary['response_rate'] = $summary['delivered'] > 0 ? round(($summary['responded'] / $summary['delivered']) * 100, 1) : 0.0;
        $summary['confirmation_rate'] = $summary['responded'] > 0 ? round(($summary['confirmed'] / $summary['responded']) * 100, 1) : 0.0;
        $summary['agent_rate'] = $summary['responded'] > 0 ? round(($summary['agent_requested'] / $summary['responded']) * 100, 1) : 0.0;

        $breakdownRows = DB::select(
            'SELECT
                source_type,
                reminder_window,
                COUNT(*) AS total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN delivered_at IS NOT NULL OR responded_at IS NOT NULL THEN 1 ELSE 0 END) AS delivered,
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
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(war.payload, "$.patient_name")), ""),
                    NULLIF(TRIM(CONCAT_WS(" ", NULLIF(pd.fname, ""), NULLIF(pd.mname, ""), NULLIF(pd.lname, ""), NULLIF(pd.lname2, ""))), ""),
                    NULLIF(wc.patient_full_name, ""),
                    NULLIF(wc.display_name, ""),
                    war.hc_number
                ) AS patient_name
             FROM whatsapp_appointment_reminders war
             LEFT JOIN whatsapp_conversations wc ON wc.id = war.conversation_id
             LEFT JOIN patient_data pd ON pd.hc_number = war.hc_number
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
                $delivered = (int) ($row->delivered ?? 0);
                $responded = (int) ($row->responded ?? 0);

                return [
                    'source_type' => (string) ($row->source_type ?? ''),
                    'source_label' => $this->reminderSourceLabel((string) ($row->source_type ?? '')),
                    'reminder_window' => (string) ($row->reminder_window ?? ''),
                    'window_label' => $this->reminderWindowLabel((string) ($row->reminder_window ?? '')),
                    'total' => $total,
                    'sent' => (int) ($row->sent ?? 0),
                    'delivered' => $delivered,
                    'failed' => (int) ($row->failed ?? 0),
                    'responded' => $responded,
                    'confirmed' => (int) ($row->confirmed ?? 0),
                    'agent_requested' => (int) ($row->agent_requested ?? 0),
                    'response_rate' => $delivered > 0 ? round(($responded / $delivered) * 100, 1) : 0.0,
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

    private function businessHoursCalculator(): BusinessHoursCalculator
    {
        $raw      = (string) $this->settingValue('whatsapp_handoff_business_schedule', '{}');
        $schedule = json_decode($raw, true);
        $schedule = is_array($schedule) ? $schedule : [];
        $timezone = (string) $this->settingValue('whatsapp_handoff_business_timezone', 'America/Guayaquil');
        $rawHols  = (string) $this->settingValue('whatsapp_handoff_business_holidays', '');
        $holidays = array_filter(array_map('trim', explode("\n", $rawHols)));
        return new BusinessHoursCalculator($schedule, $timezone, array_values($holidays));
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

    private function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $index = ($p / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        return (float) $values[$lower] + $fraction * ((float) ($values[$upper] ?? $values[$lower]) - (float) $values[$lower]);
    }
}
