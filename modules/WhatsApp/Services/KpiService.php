<?php

namespace Modules\WhatsApp\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\KpiRepository;
use PDO;

class KpiService
{
    private const DEFAULT_SLA_TARGET_MINUTES = 15;

    private KpiRepository $repository;
    private WhatsAppSettings $settings;

    public function __construct(PDO $pdo)
    {
        $this->repository = new KpiRepository($pdo);
        $this->settings = new WhatsAppSettings($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboardKpis(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $roleId = null,
        ?int $agentId = null,
        ?int $slaTargetMinutes = null
    ): array {
        $from = $startDate->setTime(0, 0, 0);
        $toExclusive = $endDate->setTime(0, 0, 0)->modify('+1 day');

        $fromDateTime = $from->format('Y-m-d H:i:s');
        $toDateTimeExclusive = $toExclusive->format('Y-m-d H:i:s');
        $slaTargetMinutes = $this->resolveSlaTargetMinutes($slaTargetMinutes);

        $summaryRaw = $this->repository->fetchSummary($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $slaRaw = $this->repository->fetchSlaSummary($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, $slaTargetMinutes);
        $liveQueueRaw = $this->repository->fetchLiveQueueSummary($roleId, $agentId);
        $transferTotal = $this->repository->fetchTransferSummary($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $reopenRaw = $this->repository->fetchReopenSummary($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $outcomeRaw = $this->repository->fetchConversationOutcomeSummary($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $fallbackMessages = $this->repository->fetchFallbackMessageCount($fromDateTime, $toDateTimeExclusive);
        $topMenuOptions = $this->repository->fetchTopMenuOptionBreakdown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 8);

        $conversationTrendRaw = $this->repository->fetchConversationTrend($fromDateTime, $toDateTimeExclusive);
        $messageTrendRaw = $this->repository->fetchMessageTrend($fromDateTime, $toDateTimeExclusive);
        $queuedTrendRaw = $this->repository->fetchQueuedHandoffTrend($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $resolvedTrendRaw = $this->repository->fetchResolvedHandoffTrend($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $transferTrendRaw = $this->repository->fetchTransferTrend($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);

        $roleBreakdown = $this->repository->fetchRoleBreakdown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $agentPerformance = $this->repository->fetchAgentPerformance($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);
        $transferByAgent = $this->repository->fetchTransferByAgent($fromDateTime, $toDateTimeExclusive, $roleId, $agentId);

        $labels = $this->buildDateLabels($from, $toExclusive);
        $series = [
            'conversations' => $this->mapDailyTotals($conversationTrendRaw),
            'messages_inbound' => $this->mapDailyDirectionalTotals($messageTrendRaw, 'inbound'),
            'messages_outbound' => $this->mapDailyDirectionalTotals($messageTrendRaw, 'outbound'),
            'handoffs_queued' => $this->mapDailyTotals($queuedTrendRaw),
            'handoffs_resolved' => $this->mapDailyTotals($resolvedTrendRaw),
            'handoff_transfers' => $this->mapDailyTotals($transferTrendRaw),
        ];

        $trends = [];
        foreach ($series as $key => $valuesByDay) {
            $values = [];
            foreach ($labels as $day) {
                $values[] = (int) ($valuesByDay[$day] ?? 0);
            }
            $trends[$key] = $values;
        }

        $messagesInbound = (int) ($summaryRaw['messages']['inbound'] ?? 0);
        $messagesOutbound = (int) ($summaryRaw['messages']['outbound'] ?? 0);
        $inboundConversations = (int) ($outcomeRaw['inbound_conversations'] ?? 0);
        $handoffConversations = (int) ($outcomeRaw['handoff_conversations'] ?? 0);
        $autoserviceConversations = (int) ($outcomeRaw['autoservice_conversations'] ?? max(0, $inboundConversations - $handoffConversations));

        $outboundStatus = $summaryRaw['outbound_status'] ?? [];
        $outboundRead = (int) ($outboundStatus['read'] ?? 0);
        $outboundDelivered = (int) ($outboundStatus['delivered'] ?? 0);
        $outboundSent = (int) ($outboundStatus['sent'] ?? 0);
        $outboundFailed = (int) ($outboundStatus['failed'] ?? 0);

        $deliveredOrRead = $outboundDelivered + $outboundRead;
        $readRate = $messagesOutbound > 0 ? round(($outboundRead / $messagesOutbound) * 100, 2) : 0.0;
        $deliveryRate = $messagesOutbound > 0 ? round(($deliveredOrRead / $messagesOutbound) * 100, 2) : 0.0;
        $failureRate = $messagesOutbound > 0 ? round(($outboundFailed / $messagesOutbound) * 100, 2) : 0.0;
        $handoffRate = $inboundConversations > 0 ? round(($handoffConversations / $inboundConversations) * 100, 2) : 0.0;
        $autoserviceRate = $inboundConversations > 0 ? round(($autoserviceConversations / $inboundConversations) * 100, 2) : 0.0;
        $fallbackRate = $messagesInbound > 0 ? round(($fallbackMessages / $messagesInbound) * 100, 2) : 0.0;

        $handoffStatus = $summaryRaw['handoffs'] ?? [];
        $handoffsQueued = (int) ($handoffStatus['queued'] ?? 0);
        $handoffsAssigned = (int) ($handoffStatus['assigned'] ?? 0);
        $handoffsResolved = (int) ($handoffStatus['resolved'] ?? 0);
        $handoffsExpired = (int) ($handoffStatus['expired'] ?? 0);
        $handoffsTotal = $handoffsQueued + $handoffsAssigned + $handoffsResolved + $handoffsExpired;
        if ($handoffsTotal === 0) {
            foreach ($handoffStatus as $value) {
                $handoffsTotal += (int) $value;
            }
        }

        $avgFirstResponseSeconds = $summaryRaw['avg_first_response_seconds'];
        $avgAssignmentSeconds = $summaryRaw['avg_assignment_seconds'];

        $slaAssignmentsTotal = (int) ($slaRaw['total_assigned'] ?? 0);
        $slaAssignmentsInTarget = (int) ($slaRaw['within_target'] ?? 0);
        $slaAssignmentsRate = $slaAssignmentsTotal > 0
            ? round(($slaAssignmentsInTarget / $slaAssignmentsTotal) * 100, 2)
            : 0.0;

        $liveQueueQueued = (int) ($liveQueueRaw['queued'] ?? 0);
        $liveQueueAssigned = (int) ($liveQueueRaw['assigned'] ?? 0);
        $liveQueueAssignedOverdue = (int) ($liveQueueRaw['assigned_overdue'] ?? 0);
        $liveQueueExpired = (int) ($liveQueueRaw['expired'] ?? 0);
        $liveQueueTotal = (int) ($liveQueueRaw['total_open'] ?? ($liveQueueQueued + $liveQueueAssigned + $liveQueueAssignedOverdue));

        $resolvedForReopen = (int) ($reopenRaw['resolved_total'] ?? 0);
        $reopened24h = (int) ($reopenRaw['reopened_24h'] ?? 0);
        $reopened72h = (int) ($reopenRaw['reopened_72h'] ?? 0);
        $reopenRate24h = $resolvedForReopen > 0 ? round(($reopened24h / $resolvedForReopen) * 100, 2) : 0.0;
        $reopenRate72h = $resolvedForReopen > 0 ? round(($reopened72h / $resolvedForReopen) * 100, 2) : 0.0;

        $normalizedAgentPerformance = $this->normalizeAgentPerformance($agentPerformance);
        $filterOptions = $this->buildFilterOptions($roleBreakdown, $normalizedAgentPerformance);

        return [
            'period' => [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $endDate->format('Y-m-d'),
                'days' => count($labels),
            ],
            'filters' => [
                'role_id' => $roleId,
                'agent_id' => $agentId,
            ],
            'summary' => [
                'conversations_new' => (int) ($summaryRaw['conversations_new'] ?? 0),
                'contacts_active' => (int) ($summaryRaw['contacts_active'] ?? 0),
                'messages_inbound' => $messagesInbound,
                'messages_outbound' => $messagesOutbound,
                'messages_total' => $messagesInbound + $messagesOutbound,
                'inbound_conversations' => $inboundConversations,
                'handoff_conversations' => $handoffConversations,
                'autoservice_conversations' => $autoserviceConversations,
                'outbound_sent' => $outboundSent,
                'outbound_delivered' => $outboundDelivered,
                'outbound_read' => $outboundRead,
                'outbound_failed' => $outboundFailed,
                'outbound_read_rate' => $readRate,
                'outbound_delivery_rate' => $deliveryRate,
                'outbound_failure_rate' => $failureRate,
                'handoff_rate' => $handoffRate,
                'autoservice_rate' => $autoserviceRate,
                'fallback_messages' => $fallbackMessages,
                'fallback_rate' => $fallbackRate,
                'handoffs_total' => $handoffsTotal,
                'handoffs_queued' => $handoffsQueued,
                'handoffs_assigned' => $handoffsAssigned,
                'handoffs_resolved' => $handoffsResolved,
                'handoffs_expired' => $handoffsExpired,
                'avg_first_response_seconds' => $avgFirstResponseSeconds !== null ? round((float) $avgFirstResponseSeconds, 2) : null,
                'avg_first_response_minutes' => $avgFirstResponseSeconds !== null ? round(((float) $avgFirstResponseSeconds) / 60, 2) : null,
                'avg_handoff_assignment_seconds' => $avgAssignmentSeconds !== null ? round((float) $avgAssignmentSeconds, 2) : null,
                'avg_handoff_assignment_minutes' => $avgAssignmentSeconds !== null ? round(((float) $avgAssignmentSeconds) / 60, 2) : null,
                'sla_target_minutes' => $slaTargetMinutes,
                'sla_assignments_total' => $slaAssignmentsTotal,
                'sla_assignments_in_target' => $slaAssignmentsInTarget,
                'sla_assignments_rate' => $slaAssignmentsRate,
                'live_queue_total' => $liveQueueTotal,
                'live_queue_queued' => $liveQueueQueued,
                'live_queue_assigned' => $liveQueueAssigned,
                'live_queue_assigned_overdue' => $liveQueueAssignedOverdue,
                'live_queue_expired' => $liveQueueExpired,
                'handoff_transfers' => $transferTotal,
                'resolved_for_reopen' => $resolvedForReopen,
                'reopened_24h' => $reopened24h,
                'reopened_72h' => $reopened72h,
                'reopen_rate_24h' => $reopenRate24h,
                'reopen_rate_72h' => $reopenRate72h,
            ],
            'trends' => [
                'labels' => $labels,
                'conversations' => $trends['conversations'],
                'messages_inbound' => $trends['messages_inbound'],
                'messages_outbound' => $trends['messages_outbound'],
                'handoffs_queued' => $trends['handoffs_queued'],
                'handoffs_resolved' => $trends['handoffs_resolved'],
                'handoff_transfers' => $trends['handoff_transfers'],
            ],
            'breakdowns' => [
                'outbound_status' => $this->normalizeStatusBreakdown($outboundStatus),
                'top_menu_options' => $topMenuOptions,
                'handoffs_by_role' => $roleBreakdown,
                'handoffs_by_agent' => $normalizedAgentPerformance,
                'handoff_transfers_by_agent' => $transferByAgent,
            ],
            'options' => $filterOptions,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildDrilldown(
        string $metric,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $roleId = null,
        ?int $agentId = null,
        int $page = 1,
        int $limit = 50,
        ?int $slaTargetMinutes = null
    ): array {
        $metric = strtolower(trim($metric));
        if ($metric === '') {
            throw new InvalidArgumentException('Debes indicar una métrica para el drill-down.');
        }

        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;

        $from = $startDate->setTime(0, 0, 0);
        $toExclusive = $endDate->setTime(0, 0, 0)->modify('+1 day');

        $payload = $this->repository->fetchDrilldown(
            $metric,
            $from->format('Y-m-d H:i:s'),
            $toExclusive->format('Y-m-d H:i:s'),
            $roleId,
            $agentId,
            $limit,
            $offset,
            $this->resolveSlaTargetMinutes($slaTargetMinutes)
        );

        $total = (int) ($payload['total'] ?? 0);
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return [
            'metric' => $payload['metric'] ?? $metric,
            'columns' => $payload['columns'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    private function resolveSlaTargetMinutes(?int $providedTarget): int
    {
        $target = $providedTarget;

        if ($target === null || $target <= 0) {
            $config = $this->settings->get();
            $raw = isset($config['handoff_sla_target_minutes'])
                ? (int) $config['handoff_sla_target_minutes']
                : self::DEFAULT_SLA_TARGET_MINUTES;
            $target = $raw;
        }

        if ($target <= 0) {
            $target = self::DEFAULT_SLA_TARGET_MINUTES;
        }

        return min(1440, $target);
    }

    /**
     * @return array<int, string>
     */
    private function buildDateLabels(DateTimeImmutable $startInclusive, DateTimeImmutable $endExclusive): array
    {
        $period = new DatePeriod($startInclusive, new DateInterval('P1D'), $endExclusive);
        $labels = [];
        foreach ($period as $day) {
            $labels[] = $day->format('Y-m-d');
        }

        return $labels;
    }

    /**
     * @param array<int, array{period_date:string,total:int}> $rows
     * @return array<string, int>
     */
    private function mapDailyTotals(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $periodDate = $row['period_date'] ?? '';
            if ($periodDate === '') {
                continue;
            }
            $map[$periodDate] = (int) ($row['total'] ?? 0);
        }

        return $map;
    }

    /**
     * @param array<int, array{period_date:string,direction:string,total:int}> $rows
     * @return array<string, int>
     */
    private function mapDailyDirectionalTotals(array $rows, string $direction): array
    {
        $direction = strtolower(trim($direction));
        $map = [];
        foreach ($rows as $row) {
            $periodDate = $row['period_date'] ?? '';
            $rowDirection = strtolower((string) ($row['direction'] ?? ''));
            if ($periodDate === '' || $rowDirection !== $direction) {
                continue;
            }
            $map[$periodDate] = (int) ($row['total'] ?? 0);
        }

        return $map;
    }

    /**
     * @param array<string, int> $statusMap
     * @return array<int, array{status:string,total:int}>
     */
    private function normalizeStatusBreakdown(array $statusMap): array
    {
        if ($statusMap === []) {
            return [];
        }

        ksort($statusMap);
        $result = [];
        foreach ($statusMap as $status => $total) {
            $result[] = [
                'status' => (string) $status,
                'total' => (int) $total,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAgentPerformance(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $assignedCount = (int) ($row['assigned_count'] ?? 0);
            $resolvedCount = (int) ($row['resolved_count'] ?? 0);
            $resolutionRate = $assignedCount > 0 ? round(($resolvedCount / $assignedCount) * 100, 2) : 0.0;

            $avgAssignmentSeconds = isset($row['avg_assignment_seconds']) && $row['avg_assignment_seconds'] !== null
                ? (float) $row['avg_assignment_seconds']
                : null;
            $avgResolutionSeconds = isset($row['avg_resolution_seconds']) && $row['avg_resolution_seconds'] !== null
                ? (float) $row['avg_resolution_seconds']
                : null;

            $result[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'agent_name' => (string) ($row['agent_name'] ?? ''),
                'assigned_count' => $assignedCount,
                'active_count' => (int) ($row['active_count'] ?? 0),
                'resolved_count' => $resolvedCount,
                'resolution_rate' => $resolutionRate,
                'avg_assignment_seconds' => $avgAssignmentSeconds !== null ? round($avgAssignmentSeconds, 2) : null,
                'avg_assignment_minutes' => $avgAssignmentSeconds !== null ? round($avgAssignmentSeconds / 60, 2) : null,
                'avg_resolution_seconds' => $avgResolutionSeconds !== null ? round($avgResolutionSeconds, 2) : null,
                'avg_resolution_minutes' => $avgResolutionSeconds !== null ? round($avgResolutionSeconds / 60, 2) : null,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string,mixed>> $roleBreakdown
     * @param array<int, array<string,mixed>> $agentBreakdown
     * @return array{roles:array<int,array{id:int,name:string}>,agents:array<int,array{id:int,name:string}>}
     */
    private function buildFilterOptions(array $roleBreakdown, array $agentBreakdown): array
    {
        $roles = [];
        foreach ($roleBreakdown as $row) {
            $roleId = isset($row['role_id']) ? (int) $row['role_id'] : 0;
            if ($roleId <= 0 || isset($roles[$roleId])) {
                continue;
            }

            $roles[$roleId] = [
                'id' => $roleId,
                'name' => (string) ($row['role_name'] ?? ('Rol #' . $roleId)),
            ];
        }

        $agents = [];
        foreach ($agentBreakdown as $row) {
            $agentId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            if ($agentId <= 0 || isset($agents[$agentId])) {
                continue;
            }

            $agents[$agentId] = [
                'id' => $agentId,
                'name' => (string) ($row['agent_name'] ?? ('Agente #' . $agentId)),
            ];
        }

        return [
            'roles' => array_values($roles),
            'agents' => array_values($agents),
        ];
    }
}
