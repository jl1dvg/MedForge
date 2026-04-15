<?php

namespace App\Modules\Whatsapp\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KpiDashboardService
{
    private const DEFAULT_SLA_TARGET_MINUTES = 15;

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

        $summary = array_merge($summary, $human, $queue, $window, $sla, [
            'handoff_transfers' => $transfers,
            'peak_open_conversations' => (int) ($human['peak_open_conversations'] ?? 0),
        ]);

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
            ],
            'breakdowns' => [
                'handoffs_by_role' => $this->handoffsByRole($fromSql, $toSql, $roleId, $agentId),
                'handoffs_by_agent' => $this->handoffsByAgent($fromSql, $toSql, $roleId, $agentId),
                'human_attention_by_agent' => $this->humanAttentionByAgent($fromSql, $toSql, $roleId, $agentId),
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
            'conversations_lost' => 'Conversaciones perdidas',
            'people_lost' => 'Personas perdidas',
            'attention_rate' => 'Tasa de atención',
            'loss_rate' => 'Tasa de pérdida',
            'avg_first_human_response_minutes' => '1ra respuesta humana (min)',
            'conversations_abandoned' => 'Conversaciones abandonadas',
            'conversations_resolved' => 'Conversaciones resueltas',
            'live_queue_total' => 'Cola activa',
            'queue_window_open' => 'Ventana 24h abierta',
            'queue_needs_template' => 'Requiere plantilla',
            'queue_awaiting_template_reply' => 'Esperando respuesta a plantilla',
            'sla_assignments_rate' => 'SLA asignación (%)',
            'handoff_transfers' => 'Transferencias',
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
    private function humanAttentionSummary(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_attention');
        $reply = $this->humanReplySubquery($roleId, $agentId, 'human_attention');

        $sql = 'SELECT
                    inbound.conversation_id,
                    inbound.wa_number,
                    inbound.first_inbound_at,
                    inbound.last_inbound_at,
                    human.first_human_reply_at
                FROM (' . $scope['sql'] . ') inbound
                LEFT JOIN (' . $reply['sql'] . ') human
                    ON human.conversation_id = inbound.conversation_id';

        $rows = DB::select($sql, array_values($scope['params'] + $reply['params']));
        $threshold24h = Carbon::now()->subHours(24);
        $peopleInboundSet = [];
        $peopleAttendedSet = [];
        $peopleLostSet = [];
        $attended = 0;
        $lost = 0;
        $abandoned = 0;
        $resolved = 0;
        $responseSeconds = [];

        foreach ($rows as $row) {
            $waNumber = (string) ($row->wa_number ?? '');
            if ($waNumber !== '') {
                $peopleInboundSet[$waNumber] = true;
            }

            $firstInbound = isset($row->first_inbound_at) ? Carbon::parse((string) $row->first_inbound_at) : null;
            $lastInbound = isset($row->last_inbound_at) ? Carbon::parse((string) $row->last_inbound_at) : null;
            $firstReply = isset($row->first_human_reply_at) ? Carbon::parse((string) $row->first_human_reply_at) : null;

            if ($firstReply !== null) {
                $attended++;
                if ($waNumber !== '') {
                    $peopleAttendedSet[$waNumber] = true;
                }
                if ($firstInbound !== null) {
                    $responseSeconds[] = $firstInbound->diffInSeconds($firstReply);
                }
                if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
                    $resolved++;
                }
            } else {
                $lost++;
                if ($waNumber !== '') {
                    $peopleLostSet[$waNumber] = true;
                }
                if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
                    $abandoned++;
                }
            }
        }

        $peopleInbound = count($peopleInboundSet);
        $peopleAttended = count($peopleAttendedSet);
        $peopleLost = count($peopleLostSet);
        $avgSeconds = $responseSeconds !== [] ? array_sum($responseSeconds) / count($responseSeconds) : null;

        $intervalPeak = $this->peakOpenConversations($fromSql, $toSql, $roleId, $agentId);

        return [
            'people_inbound' => $peopleInbound,
            'inbound_conversations_human' => count($rows),
            'conversations_attended_human' => $attended,
            'people_attended_human' => $peopleAttended,
            'conversations_lost' => $lost,
            'people_lost' => $peopleLost,
            'attention_rate' => $peopleInbound > 0 ? round(($peopleAttended / $peopleInbound) * 100, 2) : 0.0,
            'loss_rate' => $peopleInbound > 0 ? round(($peopleLost / $peopleInbound) * 100, 2) : 0.0,
            'conversations_abandoned' => $abandoned,
            'abandonment_rate' => $peopleInbound > 0 ? round(($abandoned / $peopleInbound) * 100, 2) : 0.0,
            'conversations_resolved' => $resolved,
            'avg_first_human_response_seconds' => $avgSeconds !== null ? round($avgSeconds, 2) : null,
            'avg_first_human_response_minutes' => $avgSeconds !== null ? round($avgSeconds / 60, 2) : null,
            'peak_open_conversations' => $intervalPeak['count'],
            'peak_open_at' => $intervalPeak['at'],
        ];
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
                        ? 'AVG((julianday(first_reply.first_human_reply_at) - julianday(first_reply.first_inbound_at)) * 86400)'
                        : 'AVG(TIMESTAMPDIFF(SECOND, first_reply.first_inbound_at, first_reply.first_human_reply_at))') . ' AS avg_first_response_seconds
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

        $rows = DB::select($sql, array_merge([Carbon::now()->format('Y-m-d H:i:s')], array_values($scope['params'] + $reply['params'])));
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
        $condition = $attended ? 'IS NOT NULL' : 'IS NULL';
        $base = 'FROM (' . $scope['sql'] . ') inbound
                 LEFT JOIN (' . $reply['sql'] . ') human ON human.conversation_id = inbound.conversation_id
                 INNER JOIN whatsapp_conversations c ON c.id = inbound.conversation_id
                 WHERE human.first_human_reply_at ' . $condition;
        $params = array_values($scope['params'] + $reply['params']);

        $total = (int) (DB::selectOne('SELECT COUNT(*) AS total ' . $base, $params)->total ?? 0);
        $rows = DB::select(
            'SELECT c.id, c.wa_number, c.display_name, c.patient_full_name, inbound.first_inbound_at, inbound.last_inbound_at, human.first_human_reply_at ' . $base . '
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
        $params = [];
        if ($roleId !== null && $roleId > 0) {
            $conditions[] = $userAlias . '.role_id = ?';
            $params[$scope . '_role'] = $roleId;
        }
        if ($agentId !== null && $agentId > 0) {
            $conditions[] = $conversationAlias . '.assigned_user_id = ?';
            $params[$scope . '_agent'] = $agentId;
        }

        return ['where' => implode(' AND ', $conditions), 'params' => $params];
    }

    /**
     * @return array{sql:string,params:array<string,mixed>}
     */
    private function inboundScopeSubquery(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId, $scope);
        $sql = 'SELECT
                    c.id AS conversation_id,
                    c.wa_number,
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
     * @param array{sql:string,params:array<string,mixed>} $inboundScope
     * @return array{sql:string,params:array<string,mixed>}
     */
    private function firstHumanReplyByAgentSubquery(array $inboundScope, ?int $roleId, ?int $agentId, string $scope): array
    {
        $filter = $this->handoffFilterSql('h', $roleId, $agentId, $scope . '_first_reply');
        $sql = 'SELECT
                    inbound.conversation_id,
                    inbound.first_inbound_at,
                    h.assigned_agent_id,
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
        $sql .= ' GROUP BY inbound.conversation_id, inbound.first_inbound_at, h.assigned_agent_id';

        return ['sql' => $sql, 'params' => $params];
    }
}
