<?php

namespace Modules\WhatsApp\Repositories;

use InvalidArgumentException;
use PDO;

class KpiRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{
     *     conversations_new:int,
     *     contacts_active:int,
     *     messages:array<string,int>,
     *     outbound_status:array<string,int>,
     *     handoffs:array<string,int>,
     *     avg_assignment_seconds:float|null,
     *     avg_first_response_seconds:float|null
     * }
     */
    public function fetchSummary(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $conversationsNew = $this->fetchScalarInt(
            'SELECT COUNT(*) FROM whatsapp_conversations WHERE created_at >= :from AND created_at < :to',
            [
                ':from' => $fromDateTime,
                ':to' => $toDateTimeExclusive,
            ]
        );

        $contactsActive = $this->fetchScalarInt(
            'SELECT COUNT(DISTINCT c.wa_number)
             FROM whatsapp_messages m
             INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
             WHERE m.message_timestamp >= :from AND m.message_timestamp < :to',
            [
                ':from' => $fromDateTime,
                ':to' => $toDateTimeExclusive,
            ]
        );

        $messages = [];
        $stmtMessages = $this->pdo->prepare(
            'SELECT direction, COUNT(*) AS total
             FROM whatsapp_messages
             WHERE message_timestamp >= :from AND message_timestamp < :to
             GROUP BY direction'
        );
        $stmtMessages->execute([
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ]);
        foreach (($stmtMessages->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $direction = strtolower((string) ($row['direction'] ?? ''));
            if ($direction === '') {
                continue;
            }
            $messages[$direction] = (int) ($row['total'] ?? 0);
        }

        $outboundStatus = [];
        $stmtOutboundStatus = $this->pdo->prepare(
            'SELECT COALESCE(NULLIF(status, \'\'), \'unknown\') AS status, COUNT(*) AS total
             FROM whatsapp_messages
             WHERE direction = \'outbound\'
               AND message_timestamp >= :from
               AND message_timestamp < :to
             GROUP BY COALESCE(NULLIF(status, \'\'), \'unknown\')'
        );
        $stmtOutboundStatus->execute([
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ]);
        foreach (($stmtOutboundStatus->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $status = strtolower((string) ($row['status'] ?? 'unknown'));
            $outboundStatus[$status] = (int) ($row['total'] ?? 0);
        }

        $handoffFilter = $this->buildHandoffFilters('h', $roleId, $agentId, 'summary');

        $handoffs = [];
        $sqlHandoffs = 'SELECT h.status, COUNT(*) AS total
                        FROM whatsapp_handoffs h
                        WHERE h.queued_at >= :summary_from
                          AND h.queued_at < :summary_to';
        if ($handoffFilter['where'] !== '') {
            $sqlHandoffs .= ' AND ' . $handoffFilter['where'];
        }
        $sqlHandoffs .= ' GROUP BY h.status';

        $paramsHandoffs = [
            ':summary_from' => $fromDateTime,
            ':summary_to' => $toDateTimeExclusive,
        ] + $handoffFilter['params'];
        $stmtHandoffs = $this->pdo->prepare($sqlHandoffs);
        $stmtHandoffs->execute($paramsHandoffs);
        foreach (($stmtHandoffs->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $status = strtolower((string) ($row['status'] ?? 'unknown'));
            $handoffs[$status] = (int) ($row['total'] ?? 0);
        }

        $sqlAvgAssignment = 'SELECT AVG(TIMESTAMPDIFF(SECOND, h.queued_at, h.assigned_at)) AS avg_seconds
                             FROM whatsapp_handoffs h
                             WHERE h.queued_at >= :summary_from
                               AND h.queued_at < :summary_to
                               AND h.assigned_at IS NOT NULL';
        if ($handoffFilter['where'] !== '') {
            $sqlAvgAssignment .= ' AND ' . $handoffFilter['where'];
        }
        $avgAssignmentSeconds = $this->fetchScalarFloat($sqlAvgAssignment, $paramsHandoffs);

        $avgFirstResponseSeconds = $this->fetchScalarFloat(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, metrics.first_inbound_at, metrics.first_reply_at)) AS avg_seconds
             FROM (
                SELECT inbound.conversation_id,
                       inbound.first_inbound_at,
                       MIN(outbound.message_timestamp) AS first_reply_at
                FROM (
                    SELECT conversation_id, MIN(message_timestamp) AS first_inbound_at
                    FROM whatsapp_messages
                    WHERE direction = \'inbound\'
                      AND message_timestamp >= :from
                      AND message_timestamp < :to
                    GROUP BY conversation_id
                ) inbound
                INNER JOIN whatsapp_messages outbound
                    ON outbound.conversation_id = inbound.conversation_id
                   AND outbound.direction = \'outbound\'
                   AND outbound.message_timestamp > inbound.first_inbound_at
                GROUP BY inbound.conversation_id, inbound.first_inbound_at
             ) metrics',
            [
                ':from' => $fromDateTime,
                ':to' => $toDateTimeExclusive,
            ]
        );

        return [
            'conversations_new' => $conversationsNew,
            'contacts_active' => $contactsActive,
            'messages' => $messages,
            'outbound_status' => $outboundStatus,
            'handoffs' => $handoffs,
            'avg_assignment_seconds' => $avgAssignmentSeconds,
            'avg_first_response_seconds' => $avgFirstResponseSeconds,
        ];
    }

    /**
     * @return array{total_assigned:int,within_target:int,target_minutes:int}
     */
    public function fetchSlaSummary(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null, int $targetMinutes = 15): array
    {
        $targetMinutes = max(1, min(1440, $targetMinutes));
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'sla');

        $sql = 'SELECT
                    COUNT(*) AS total_assigned,
                    SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, h.queued_at, h.assigned_at) <= :sla_target_minutes THEN 1 ELSE 0 END) AS within_target
                FROM whatsapp_handoffs h
                WHERE h.queued_at >= :sla_from
                  AND h.queued_at < :sla_to
                  AND h.assigned_at IS NOT NULL';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }

        $params = [
            ':sla_from' => $fromDateTime,
            ':sla_to' => $toDateTimeExclusive,
            ':sla_target_minutes' => $targetMinutes,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_assigned' => (int) ($row['total_assigned'] ?? 0),
            'within_target' => (int) ($row['within_target'] ?? 0),
            'target_minutes' => $targetMinutes,
        ];
    }

    /**
     * @return array{queued:int,assigned:int,assigned_overdue:int,expired:int,total_open:int}
     */
    public function fetchLiveQueueSummary(?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'live_queue');

        $sql = 'SELECT
                    SUM(CASE WHEN h.status = \'queued\' THEN 1 ELSE 0 END) AS queued,
                    SUM(CASE WHEN h.status = \'assigned\' AND (h.assigned_until IS NULL OR h.assigned_until > NOW()) THEN 1 ELSE 0 END) AS assigned,
                    SUM(CASE WHEN h.status = \'assigned\' AND h.assigned_until IS NOT NULL AND h.assigned_until <= NOW() THEN 1 ELSE 0 END) AS assigned_overdue,
                    SUM(CASE WHEN h.status = \'expired\' THEN 1 ELSE 0 END) AS expired
                FROM whatsapp_handoffs h
                WHERE h.status IN (\'queued\', \'assigned\', \'expired\')';

        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filter['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $queued = (int) ($row['queued'] ?? 0);
        $assigned = (int) ($row['assigned'] ?? 0);
        $assignedOverdue = (int) ($row['assigned_overdue'] ?? 0);
        $expired = (int) ($row['expired'] ?? 0);

        return [
            'queued' => $queued,
            'assigned' => $assigned,
            'assigned_overdue' => $assignedOverdue,
            'expired' => $expired,
            'total_open' => $queued + $assigned + $assignedOverdue,
        ];
    }

    public function fetchTransferSummary(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): int
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'transfer_summary');

        $sql = 'SELECT COUNT(*)
                FROM whatsapp_handoff_events e
                INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                WHERE e.event_type = \'transferred\'
                  AND e.created_at >= :transfer_summary_from
                  AND e.created_at < :transfer_summary_to';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }

        $params = [
            ':transfer_summary_from' => $fromDateTime,
            ':transfer_summary_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        return $this->fetchScalarInt($sql, $params);
    }

    /**
     * @return array<int, array{user_id:int,agent_name:string,transfer_count:int}>
     */
    public function fetchTransferByAgent(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'transfer_agent');

        $sql = 'SELECT
                    COALESCE(e.actor_user_id, 0) AS user_id,
                    COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\'), NULLIF(u.nombre, \'\'), u.username, \'Sistema\') AS agent_name,
                    COUNT(*) AS transfer_count
                FROM whatsapp_handoff_events e
                INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                LEFT JOIN users u ON u.id = e.actor_user_id
                WHERE e.event_type = \'transferred\'
                  AND e.created_at >= :transfer_agent_from
                  AND e.created_at < :transfer_agent_to';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }

        $sql .= ' GROUP BY user_id, agent_name
                  ORDER BY transfer_count DESC, agent_name ASC';

        $params = [
            ':transfer_agent_from' => $fromDateTime,
            ':transfer_agent_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'agent_name' => (string) ($row['agent_name'] ?? 'Sistema'),
                'transfer_count' => (int) ($row['transfer_count'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array{resolved_total:int,reopened_24h:int,reopened_72h:int}
     */
    public function fetchReopenSummary(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'reopen_summary');

        $resolvedSql = 'SELECT
                            h.id AS handoff_id,
                            h.conversation_id,
                            MIN(e.created_at) AS resolved_at
                        FROM whatsapp_handoff_events e
                        INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                        WHERE e.event_type = \'resolved\'
                          AND e.created_at >= :reopen_summary_from
                          AND e.created_at < :reopen_summary_to';

        if ($filter['where'] !== '') {
            $resolvedSql .= ' AND ' . $filter['where'];
        }

        $resolvedSql .= ' GROUP BY h.id, h.conversation_id';

        $sql = 'SELECT
                    COUNT(*) AS resolved_total,
                    SUM(CASE WHEN reopen.first_inbound_at IS NOT NULL
                              AND reopen.first_inbound_at <= DATE_ADD(reopen.resolved_at, INTERVAL 24 HOUR)
                             THEN 1 ELSE 0 END) AS reopened_24h,
                    SUM(CASE WHEN reopen.first_inbound_at IS NOT NULL
                              AND reopen.first_inbound_at <= DATE_ADD(reopen.resolved_at, INTERVAL 72 HOUR)
                             THEN 1 ELSE 0 END) AS reopened_72h
                FROM (
                    SELECT
                        resolved.handoff_id,
                        resolved.conversation_id,
                        resolved.resolved_at,
                        MIN(m.message_timestamp) AS first_inbound_at
                    FROM (' . $resolvedSql . ') resolved
                    LEFT JOIN whatsapp_messages m
                        ON m.conversation_id = resolved.conversation_id
                       AND m.direction = \'inbound\'
                       AND m.message_timestamp > resolved.resolved_at
                    GROUP BY resolved.handoff_id, resolved.conversation_id, resolved.resolved_at
                ) reopen';

        $params = [
            ':reopen_summary_from' => $fromDateTime,
            ':reopen_summary_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'resolved_total' => (int) ($row['resolved_total'] ?? 0),
            'reopened_24h' => (int) ($row['reopened_24h'] ?? 0),
            'reopened_72h' => (int) ($row['reopened_72h'] ?? 0),
        ];
    }

    /**
     * @return array{inbound_conversations:int,handoff_conversations:int,autoservice_conversations:int}
     */
    public function fetchConversationOutcomeSummary(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'outcome');

        $handoffSql = 'SELECT DISTINCT h.conversation_id
                       FROM whatsapp_handoffs h
                       WHERE h.queued_at >= :outcome_handoff_from
                         AND h.queued_at < :outcome_handoff_to';
        if ($filter['where'] !== '') {
            $handoffSql .= ' AND ' . $filter['where'];
        }

        $sql = 'SELECT
                    COUNT(*) AS inbound_conversations,
                    SUM(CASE WHEN handoff.conversation_id IS NOT NULL THEN 1 ELSE 0 END) AS handoff_conversations
                FROM (
                    SELECT DISTINCT m.conversation_id
                    FROM whatsapp_messages m
                    WHERE m.direction = \'inbound\'
                      AND m.message_timestamp >= :outcome_from
                      AND m.message_timestamp < :outcome_to
                ) inbound
                LEFT JOIN (' . $handoffSql . ') handoff
                    ON handoff.conversation_id = inbound.conversation_id';

        $params = [
            ':outcome_from' => $fromDateTime,
            ':outcome_to' => $toDateTimeExclusive,
            ':outcome_handoff_from' => $fromDateTime,
            ':outcome_handoff_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $inboundConversations = (int) ($row['inbound_conversations'] ?? 0);
        $handoffConversations = (int) ($row['handoff_conversations'] ?? 0);
        $autoserviceConversations = max(0, $inboundConversations - $handoffConversations);

        return [
            'inbound_conversations' => $inboundConversations,
            'handoff_conversations' => $handoffConversations,
            'autoservice_conversations' => $autoserviceConversations,
        ];
    }

    public function fetchFallbackMessageCount(string $fromDateTime, string $toDateTimeExclusive): int
    {
        $patterns = $this->fallbackLikePatterns();

        $conditions = [];
        $params = [
            ':fallback_from' => $fromDateTime,
            ':fallback_to' => $toDateTimeExclusive,
        ];

        foreach ($patterns as $index => $pattern) {
            $param = ':fallback_pattern_' . $index;
            $conditions[] = 'LOWER(m.body) LIKE ' . $param;
            $params[$param] = $pattern;
        }

        if ($conditions === []) {
            return 0;
        }

        $sql = 'SELECT COUNT(*)
                FROM whatsapp_messages m
                WHERE m.direction = \'outbound\'
                  AND m.message_timestamp >= :fallback_from
                  AND m.message_timestamp < :fallback_to
                  AND m.body IS NOT NULL
                  AND (' . implode(' OR ', $conditions) . ')';

        return $this->fetchScalarInt($sql, $params);
    }

    /**
     * @return array<int, array{option_label:string,total:int}>
     */
    public function fetchTopMenuOptionBreakdown(
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId = null,
        ?int $agentId = null,
        int $limit = 8
    ): array {
        $limit = max(1, min(200, $limit));
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'menu_option');

        $selectExpression = 'COALESCE(
                NULLIF(TRIM(
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(m.raw_payload, "$.interactive.button_reply.title")),
                        JSON_UNQUOTE(JSON_EXTRACT(m.raw_payload, "$.interactive.list_reply.title")),
                        JSON_UNQUOTE(JSON_EXTRACT(m.raw_payload, "$.interactive.button_reply.id")),
                        JSON_UNQUOTE(JSON_EXTRACT(m.raw_payload, "$.interactive.list_reply.id")),
                        JSON_UNQUOTE(JSON_EXTRACT(m.raw_payload, "$.button.text")),
                        JSON_UNQUOTE(JSON_EXTRACT(m.raw_payload, "$.button.payload")),
                        m.body
                    )
                ), ""),
                "[Sin opción]"
            )';

        $sql = 'SELECT
                    ' . $selectExpression . ' AS option_label,
                    COUNT(*) AS total
                FROM whatsapp_messages m';

        $params = [
            ':menu_option_from' => $fromDateTime,
            ':menu_option_to' => $toDateTimeExclusive,
        ];

        if ($filter['where'] !== '') {
            $sql .= ' INNER JOIN (
                        SELECT DISTINCT h.conversation_id
                        FROM whatsapp_handoffs h
                        WHERE h.queued_at >= :menu_option_handoff_from
                          AND h.queued_at < :menu_option_handoff_to
                          AND ' . $filter['where'] . '
                     ) hf ON hf.conversation_id = m.conversation_id';
            $params[':menu_option_handoff_from'] = $fromDateTime;
            $params[':menu_option_handoff_to'] = $toDateTimeExclusive;
            $params += $filter['params'];
        }

        $sql .= ' WHERE m.direction = \'inbound\'
                  AND m.message_timestamp >= :menu_option_from
                  AND m.message_timestamp < :menu_option_to
                  AND (
                        m.message_type IN (\'interactive\', \'button\')
                        OR (
                            m.message_type = \'text\'
                            AND m.body IS NOT NULL
                            AND (
                                LOWER(TRIM(m.body)) REGEXP \'^[1-9][0-9]?$\'
                                OR LOWER(TRIM(m.body)) LIKE \'menu_%\'
                            )
                        )
                  )
                GROUP BY option_label
                ORDER BY total DESC, option_label ASC
                LIMIT :menu_option_limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($this->filterParamsForSql($sql, $params) as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
                continue;
            }
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':menu_option_limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['option_label'] ?? ''));
            if ($label === '') {
                $label = '[Sin opción]';
            }
            $result[] = [
                'option_label' => $label,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    public function fetchConversationTrend(string $fromDateTime, string $toDateTimeExclusive): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS period_date, COUNT(*) AS total
             FROM whatsapp_conversations
             WHERE created_at >= :from AND created_at < :to
             GROUP BY DATE(created_at)
             ORDER BY period_date ASC'
        );
        $stmt->execute([
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $periodDate = (string) ($row['period_date'] ?? '');
            if ($periodDate === '') {
                continue;
            }
            $result[] = [
                'period_date' => $periodDate,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{period_date:string,direction:string,total:int}>
     */
    public function fetchMessageTrend(string $fromDateTime, string $toDateTimeExclusive): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(message_timestamp) AS period_date, direction, COUNT(*) AS total
             FROM whatsapp_messages
             WHERE message_timestamp >= :from AND message_timestamp < :to
             GROUP BY DATE(message_timestamp), direction
             ORDER BY period_date ASC'
        );
        $stmt->execute([
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $periodDate = (string) ($row['period_date'] ?? '');
            $direction = strtolower((string) ($row['direction'] ?? ''));
            if ($periodDate === '' || $direction === '') {
                continue;
            }
            $result[] = [
                'period_date' => $periodDate,
                'direction' => $direction,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    public function fetchQueuedHandoffTrend(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'queued_trend');
        $sql = 'SELECT DATE(h.queued_at) AS period_date, COUNT(*) AS total
                FROM whatsapp_handoffs h
                WHERE h.queued_at >= :queued_trend_from
                  AND h.queued_at < :queued_trend_to';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }
        $sql .= ' GROUP BY DATE(h.queued_at) ORDER BY period_date ASC';

        $params = [
            ':queued_trend_from' => $fromDateTime,
            ':queued_trend_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $periodDate = (string) ($row['period_date'] ?? '');
            if ($periodDate === '') {
                continue;
            }
            $result[] = [
                'period_date' => $periodDate,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    public function fetchResolvedHandoffTrend(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'resolved_trend');
        $sql = 'SELECT DATE(e.created_at) AS period_date, COUNT(DISTINCT e.handoff_id) AS total
                FROM whatsapp_handoff_events e
                INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                WHERE e.event_type = \'resolved\'
                  AND e.created_at >= :resolved_trend_from
                  AND e.created_at < :resolved_trend_to';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }
        $sql .= ' GROUP BY DATE(e.created_at) ORDER BY period_date ASC';

        $params = [
            ':resolved_trend_from' => $fromDateTime,
            ':resolved_trend_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $periodDate = (string) ($row['period_date'] ?? '');
            if ($periodDate === '') {
                continue;
            }
            $result[] = [
                'period_date' => $periodDate,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{period_date:string,total:int}>
     */
    public function fetchTransferTrend(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'transfer_trend');
        $sql = 'SELECT DATE(e.created_at) AS period_date, COUNT(*) AS total
                FROM whatsapp_handoff_events e
                INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                WHERE e.event_type = \'transferred\'
                  AND e.created_at >= :transfer_trend_from
                  AND e.created_at < :transfer_trend_to';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }
        $sql .= ' GROUP BY DATE(e.created_at) ORDER BY period_date ASC';

        $params = [
            ':transfer_trend_from' => $fromDateTime,
            ':transfer_trend_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $periodDate = (string) ($row['period_date'] ?? '');
            if ($periodDate === '') {
                continue;
            }
            $result[] = [
                'period_date' => $periodDate,
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{
     *     role_id:int|null,
     *     role_name:string,
     *     total:int,
     *     queued:int,
     *     assigned:int,
     *     resolved:int,
     *     expired:int
     * }>
     */
    public function fetchRoleBreakdown(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'role');
        $sql = 'SELECT
                    h.handoff_role_id AS role_id,
                    COALESCE(r.name, \'Sin rol\') AS role_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN h.status = \'queued\' THEN 1 ELSE 0 END) AS queued,
                    SUM(CASE WHEN h.status = \'assigned\' THEN 1 ELSE 0 END) AS assigned,
                    SUM(CASE WHEN h.status = \'resolved\' THEN 1 ELSE 0 END) AS resolved,
                    SUM(CASE WHEN h.status = \'expired\' THEN 1 ELSE 0 END) AS expired
                FROM whatsapp_handoffs h
                LEFT JOIN roles r ON r.id = h.handoff_role_id
                WHERE h.queued_at >= :role_from
                  AND h.queued_at < :role_to';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }
        $sql .= ' GROUP BY h.handoff_role_id, role_name
                  ORDER BY total DESC, role_name ASC';

        $params = [
            ':role_from' => $fromDateTime,
            ':role_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'role_id' => isset($row['role_id']) ? (int) $row['role_id'] : null,
                'role_name' => (string) ($row['role_name'] ?? 'Sin rol'),
                'total' => (int) ($row['total'] ?? 0),
                'queued' => (int) ($row['queued'] ?? 0),
                'assigned' => (int) ($row['assigned'] ?? 0),
                'resolved' => (int) ($row['resolved'] ?? 0),
                'expired' => (int) ($row['expired'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{
     *     user_id:int,
     *     agent_name:string,
     *     assigned_count:int,
     *     active_count:int,
     *     resolved_count:int,
     *     avg_assignment_seconds:float|null,
     *     avg_resolution_seconds:float|null
     * }>
     */
    public function fetchAgentPerformance(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId = null, ?int $agentId = null): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'agent');
        $sql = 'SELECT
                    h.assigned_agent_id AS user_id,
                    COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\'), NULLIF(u.nombre, \'\'), u.username, CONCAT(\'Usuario #\', h.assigned_agent_id)) AS agent_name,
                    COUNT(*) AS assigned_count,
                    SUM(CASE WHEN h.status = \'assigned\' THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN h.status = \'resolved\' THEN 1 ELSE 0 END) AS resolved_count,
                    AVG(CASE WHEN h.assigned_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, h.queued_at, h.assigned_at) END) AS avg_assignment_seconds,
                    AVG(CASE WHEN resolved_event.resolved_at IS NOT NULL AND h.assigned_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, h.assigned_at, resolved_event.resolved_at) END) AS avg_resolution_seconds
                FROM whatsapp_handoffs h
                LEFT JOIN users u ON u.id = h.assigned_agent_id
                LEFT JOIN (
                    SELECT handoff_id, MIN(created_at) AS resolved_at
                    FROM whatsapp_handoff_events
                    WHERE event_type = \'resolved\'
                    GROUP BY handoff_id
                ) resolved_event ON resolved_event.handoff_id = h.id
                WHERE h.queued_at >= :agent_from
                  AND h.queued_at < :agent_to
                  AND h.assigned_agent_id IS NOT NULL';
        if ($filter['where'] !== '') {
            $sql .= ' AND ' . $filter['where'];
        }
        $sql .= ' GROUP BY h.assigned_agent_id, agent_name
                  ORDER BY assigned_count DESC, resolved_count DESC, agent_name ASC';

        $params = [
            ':agent_from' => $fromDateTime,
            ':agent_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'agent_name' => (string) ($row['agent_name'] ?? ''),
                'assigned_count' => (int) ($row['assigned_count'] ?? 0),
                'active_count' => (int) ($row['active_count'] ?? 0),
                'resolved_count' => (int) ($row['resolved_count'] ?? 0),
                'avg_assignment_seconds' => isset($row['avg_assignment_seconds']) ? (float) $row['avg_assignment_seconds'] : null,
                'avg_resolution_seconds' => isset($row['avg_resolution_seconds']) ? (float) $row['avg_resolution_seconds'] : null,
            ];
        }

        return $result;
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    public function fetchDrilldown(
        string $metric,
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId,
        ?int $agentId,
        int $limit,
        int $offset,
        int $slaTargetMinutes
    ): array {
        $metric = strtolower(trim($metric));
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        return match ($metric) {
            'conversations_new' => $this->fetchConversationDrilldown($fromDateTime, $toDateTimeExclusive, $limit, $offset),
            'contacts_active' => $this->fetchActiveContactsDrilldown($fromDateTime, $toDateTimeExclusive, $limit, $offset),
            'messages_inbound' => $this->fetchMessageDrilldown($fromDateTime, $toDateTimeExclusive, 'inbound', $limit, $offset),
            'messages_outbound' => $this->fetchMessageDrilldown($fromDateTime, $toDateTimeExclusive, 'outbound', $limit, $offset),
            'messages_total' => $this->fetchMessageDrilldown($fromDateTime, $toDateTimeExclusive, null, $limit, $offset),
            'handoffs_total' => $this->fetchHandoffDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, null, $limit, $offset),
            'handoffs_queued' => $this->fetchHandoffDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 'queued', $limit, $offset),
            'handoffs_assigned' => $this->fetchHandoffDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 'assigned', $limit, $offset),
            'handoffs_resolved' => $this->fetchHandoffDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 'resolved', $limit, $offset),
            'handoffs_expired' => $this->fetchHandoffDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 'expired', $limit, $offset),
            'avg_first_response' => $this->fetchFirstResponseDrilldown($fromDateTime, $toDateTimeExclusive, $limit, $offset),
            'avg_handoff_assignment' => $this->fetchAssignmentDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, $limit, $offset),
            'sla_assignments' => $this->fetchSlaDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, $slaTargetMinutes, $limit, $offset),
            'live_queue' => $this->fetchLiveQueueDrilldown($roleId, $agentId, $limit, $offset),
            'handoff_transfers' => $this->fetchTransferDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, $limit, $offset),
            'reopened_24h' => $this->fetchReopenDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 24, $limit, $offset),
            'reopened_72h' => $this->fetchReopenDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 72, $limit, $offset),
            'handoff_rate' => $this->fetchHandoffDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, null, $limit, $offset),
            'autoservice_rate' => $this->fetchAutoserviceDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, $limit, $offset),
            'fallback_rate' => $this->fetchFallbackDrilldown($fromDateTime, $toDateTimeExclusive, $limit, $offset),
            'top_menu_options' => $this->fetchTopMenuOptionsDrilldown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, $limit, $offset),
            default => throw new InvalidArgumentException('Métrica de drill-down no soportada: ' . $metric),
        };
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchConversationDrilldown(string $fromDateTime, string $toDateTimeExclusive, int $limit, int $offset): array
    {
        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_conversations c
                     WHERE c.created_at >= :from AND c.created_at < :to';

        $rowsSql = 'SELECT
                        c.id AS conversation_id,
                        c.wa_number,
                        COALESCE(NULLIF(c.display_name, \"\"), NULLIF(c.patient_full_name, \"\"), c.wa_number) AS contacto,
                        c.patient_hc_number,
                        c.created_at,
                        c.last_message_at,
                        c.last_message_preview
                    FROM whatsapp_conversations c
                    WHERE c.created_at >= :from AND c.created_at < :to
                    ORDER BY c.created_at DESC, c.id DESC';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ];

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'conversations_new',
            'columns' => [
                ['key' => 'conversation_id', 'label' => 'ID'],
                ['key' => 'contacto', 'label' => 'Contacto'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'patient_hc_number', 'label' => 'HC'],
                ['key' => 'created_at', 'label' => 'Creada'],
                ['key' => 'last_message_at', 'label' => 'Último mensaje'],
                ['key' => 'last_message_preview', 'label' => 'Preview'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchActiveContactsDrilldown(string $fromDateTime, string $toDateTimeExclusive, int $limit, int $offset): array
    {
        $countSql = 'SELECT COUNT(DISTINCT c.wa_number)
                     FROM whatsapp_messages m
                     INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                     WHERE m.message_timestamp >= :from
                       AND m.message_timestamp < :to';

        $rowsSql = 'SELECT
                        c.id AS conversation_id,
                        c.wa_number,
                        COALESCE(NULLIF(c.display_name, ""), NULLIF(c.patient_full_name, ""), c.wa_number) AS contacto,
                        COUNT(*) AS messages_total,
                        SUM(CASE WHEN m.direction = "inbound" THEN 1 ELSE 0 END) AS inbound_count,
                        SUM(CASE WHEN m.direction = "outbound" THEN 1 ELSE 0 END) AS outbound_count,
                        MAX(m.message_timestamp) AS last_activity_at
                    FROM whatsapp_messages m
                    INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                    WHERE m.message_timestamp >= :from
                      AND m.message_timestamp < :to
                    GROUP BY c.id, c.wa_number, contacto
                    ORDER BY last_activity_at DESC, c.id DESC';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ];

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'contacts_active',
            'columns' => [
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'contacto', 'label' => 'Contacto'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'messages_total', 'label' => 'Mensajes'],
                ['key' => 'inbound_count', 'label' => 'Inbound'],
                ['key' => 'outbound_count', 'label' => 'Outbound'],
                ['key' => 'last_activity_at', 'label' => 'Última actividad'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchMessageDrilldown(string $fromDateTime, string $toDateTimeExclusive, ?string $direction, int $limit, int $offset): array
    {
        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_messages m
                     WHERE m.message_timestamp >= :from
                       AND m.message_timestamp < :to';

        $rowsSql = 'SELECT
                        m.id AS message_id,
                        c.id AS conversation_id,
                        c.wa_number,
                        COALESCE(NULLIF(c.display_name, \"\"), NULLIF(c.patient_full_name, \"\"), c.wa_number) AS contacto,
                        m.direction,
                        m.message_type,
                        COALESCE(NULLIF(m.body, \"\"), CONCAT(\"[\", UPPER(m.message_type), \"]\")) AS preview,
                        m.status,
                        m.message_timestamp
                    FROM whatsapp_messages m
                    INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                    WHERE m.message_timestamp >= :from
                      AND m.message_timestamp < :to';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ];

        if ($direction !== null) {
            $countSql .= ' AND m.direction = :direction';
            $rowsSql .= ' AND m.direction = :direction';
            $params[':direction'] = $direction;
        }

        $rowsSql .= ' ORDER BY COALESCE(m.message_timestamp, m.created_at) DESC, m.id DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => $direction === null ? 'messages_total' : 'messages_' . $direction,
            'columns' => [
                ['key' => 'message_id', 'label' => 'ID'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'contacto', 'label' => 'Contacto'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'direction', 'label' => 'Dirección'],
                ['key' => 'message_type', 'label' => 'Tipo'],
                ['key' => 'preview', 'label' => 'Contenido'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'message_timestamp', 'label' => 'Fecha'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchHandoffDrilldown(
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId,
        ?int $agentId,
        ?string $status,
        int $limit,
        int $offset
    ): array {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_handoff');

        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_handoffs h
                     WHERE h.queued_at >= :from
                       AND h.queued_at < :to';

        $rowsSql = 'SELECT
                        h.id AS handoff_id,
                        h.conversation_id,
                        h.wa_number,
                        h.status,
                        h.priority,
                        COALESCE(r.name, \"Sin rol\") AS role_name,
                        COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \" \", u.last_name)), \"\"), NULLIF(u.nombre, \"\"), u.username, \"Sin asignar\") AS assigned_agent,
                        h.queued_at,
                        h.assigned_at,
                        h.assigned_until,
                        h.notes
                    FROM whatsapp_handoffs h
                    LEFT JOIN roles r ON r.id = h.handoff_role_id
                    LEFT JOIN users u ON u.id = h.assigned_agent_id
                    WHERE h.queued_at >= :from
                      AND h.queued_at < :to';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ] + $filter['params'];

        if ($filter['where'] !== '') {
            $countSql .= ' AND ' . $filter['where'];
            $rowsSql .= ' AND ' . $filter['where'];
        }

        if ($status !== null) {
            $countSql .= ' AND h.status = :handoff_status';
            $rowsSql .= ' AND h.status = :handoff_status';
            $params[':handoff_status'] = $status;
        }

        $rowsSql .= ' ORDER BY h.queued_at DESC, h.id DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => $status === null ? 'handoffs_total' : 'handoffs_' . $status,
            'columns' => [
                ['key' => 'handoff_id', 'label' => 'Handoff'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'priority', 'label' => 'Prioridad'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'assigned_agent', 'label' => 'Agente'],
                ['key' => 'queued_at', 'label' => 'En cola'],
                ['key' => 'assigned_at', 'label' => 'Asignado'],
                ['key' => 'assigned_until', 'label' => 'TTL'],
                ['key' => 'notes', 'label' => 'Notas'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchFirstResponseDrilldown(string $fromDateTime, string $toDateTimeExclusive, int $limit, int $offset): array
    {
        $metricsSql = 'SELECT
                           inbound.conversation_id,
                           inbound.first_inbound_at,
                           MIN(outbound.message_timestamp) AS first_reply_at,
                           TIMESTAMPDIFF(SECOND, inbound.first_inbound_at, MIN(outbound.message_timestamp)) AS first_response_seconds
                       FROM (
                           SELECT conversation_id, MIN(message_timestamp) AS first_inbound_at
                           FROM whatsapp_messages
                           WHERE direction = \'inbound\'
                             AND message_timestamp >= :from
                             AND message_timestamp < :to
                           GROUP BY conversation_id
                       ) inbound
                       INNER JOIN whatsapp_messages outbound
                           ON outbound.conversation_id = inbound.conversation_id
                          AND outbound.direction = \'outbound\'
                          AND outbound.message_timestamp > inbound.first_inbound_at
                       GROUP BY inbound.conversation_id, inbound.first_inbound_at';

        $countSql = 'SELECT COUNT(*) FROM (' . $metricsSql . ') metrics';

        $rowsSql = 'SELECT
                        metrics.conversation_id,
                        c.wa_number,
                        COALESCE(NULLIF(c.display_name, \"\"), NULLIF(c.patient_full_name, \"\"), c.wa_number) AS contacto,
                        metrics.first_inbound_at,
                        metrics.first_reply_at,
                        metrics.first_response_seconds
                    FROM (' . $metricsSql . ') metrics
                    INNER JOIN whatsapp_conversations c ON c.id = metrics.conversation_id
                    ORDER BY metrics.first_response_seconds DESC, metrics.first_reply_at DESC';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ];

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'avg_first_response',
            'columns' => [
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'contacto', 'label' => 'Contacto'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'first_inbound_at', 'label' => 'Primer inbound'],
                ['key' => 'first_reply_at', 'label' => 'Primera respuesta'],
                ['key' => 'first_response_seconds', 'label' => 'Respuesta (s)'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchAssignmentDrilldown(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_assignment');

        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_handoffs h
                     WHERE h.queued_at >= :from
                       AND h.queued_at < :to
                       AND h.assigned_at IS NOT NULL';

        $rowsSql = 'SELECT
                        h.id AS handoff_id,
                        h.conversation_id,
                        h.wa_number,
                        COALESCE(r.name, \"Sin rol\") AS role_name,
                        COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \" \", u.last_name)), \"\"), NULLIF(u.nombre, \"\"), u.username, \"Sin asignar\") AS assigned_agent,
                        h.queued_at,
                        h.assigned_at,
                        TIMESTAMPDIFF(SECOND, h.queued_at, h.assigned_at) AS assignment_seconds,
                        ROUND(TIMESTAMPDIFF(SECOND, h.queued_at, h.assigned_at) / 60, 2) AS assignment_minutes
                    FROM whatsapp_handoffs h
                    LEFT JOIN roles r ON r.id = h.handoff_role_id
                    LEFT JOIN users u ON u.id = h.assigned_agent_id
                    WHERE h.queued_at >= :from
                      AND h.queued_at < :to
                      AND h.assigned_at IS NOT NULL';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ] + $filter['params'];

        if ($filter['where'] !== '') {
            $countSql .= ' AND ' . $filter['where'];
            $rowsSql .= ' AND ' . $filter['where'];
        }

        $rowsSql .= ' ORDER BY assignment_seconds DESC, h.assigned_at DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'avg_handoff_assignment',
            'columns' => [
                ['key' => 'handoff_id', 'label' => 'Handoff'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'assigned_agent', 'label' => 'Agente'],
                ['key' => 'queued_at', 'label' => 'En cola'],
                ['key' => 'assigned_at', 'label' => 'Asignado'],
                ['key' => 'assignment_seconds', 'label' => 'Tiempo (s)'],
                ['key' => 'assignment_minutes', 'label' => 'Tiempo (min)'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchSlaDrilldown(
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId,
        ?int $agentId,
        int $targetMinutes,
        int $limit,
        int $offset
    ): array {
        $targetMinutes = max(1, min(1440, $targetMinutes));
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_sla');

        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_handoffs h
                     WHERE h.queued_at >= :from
                       AND h.queued_at < :to
                       AND h.assigned_at IS NOT NULL';

        $rowsSql = 'SELECT
                        h.id AS handoff_id,
                        h.conversation_id,
                        h.wa_number,
                        COALESCE(r.name, \"Sin rol\") AS role_name,
                        COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \" \", u.last_name)), \"\"), NULLIF(u.nombre, \"\"), u.username, \"Sin asignar\") AS assigned_agent,
                        h.queued_at,
                        h.assigned_at,
                        TIMESTAMPDIFF(MINUTE, h.queued_at, h.assigned_at) AS assignment_minutes,
                        CASE WHEN TIMESTAMPDIFF(MINUTE, h.queued_at, h.assigned_at) <= :target_minutes THEN 1 ELSE 0 END AS within_sla
                    FROM whatsapp_handoffs h
                    LEFT JOIN roles r ON r.id = h.handoff_role_id
                    LEFT JOIN users u ON u.id = h.assigned_agent_id
                    WHERE h.queued_at >= :from
                      AND h.queued_at < :to
                      AND h.assigned_at IS NOT NULL';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
            ':target_minutes' => $targetMinutes,
        ] + $filter['params'];

        if ($filter['where'] !== '') {
            $countSql .= ' AND ' . $filter['where'];
            $rowsSql .= ' AND ' . $filter['where'];
        }

        $rowsSql .= ' ORDER BY within_sla ASC, assignment_minutes DESC, h.assigned_at DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'sla_assignments',
            'columns' => [
                ['key' => 'handoff_id', 'label' => 'Handoff'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'assigned_agent', 'label' => 'Agente'],
                ['key' => 'queued_at', 'label' => 'En cola'],
                ['key' => 'assigned_at', 'label' => 'Asignado'],
                ['key' => 'assignment_minutes', 'label' => 'Asignación (min)'],
                ['key' => 'within_sla', 'label' => 'Cumple SLA'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchLiveQueueDrilldown(?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_live_queue');

        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_handoffs h
                     WHERE h.status IN (\'queued\', \'assigned\', \'expired\')';

        $rowsSql = 'SELECT
                        h.id AS handoff_id,
                        h.conversation_id,
                        h.wa_number,
                        h.status,
                        CASE
                            WHEN h.status = \'assigned\' AND h.assigned_until IS NOT NULL AND h.assigned_until <= NOW() THEN \'assigned_overdue\'
                            ELSE h.status
                        END AS queue_state,
                        COALESCE(r.name, \"Sin rol\") AS role_name,
                        COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \" \", u.last_name)), \"\"), NULLIF(u.nombre, \"\"), u.username, \"Sin asignar\") AS assigned_agent,
                        h.queued_at,
                        h.assigned_at,
                        h.assigned_until,
                        h.notes
                    FROM whatsapp_handoffs h
                    LEFT JOIN roles r ON r.id = h.handoff_role_id
                    LEFT JOIN users u ON u.id = h.assigned_agent_id
                    WHERE h.status IN (\'queued\', \'assigned\', \'expired\')';

        $params = $filter['params'];

        if ($filter['where'] !== '') {
            $countSql .= ' AND ' . $filter['where'];
            $rowsSql .= ' AND ' . $filter['where'];
        }

        $rowsSql .= ' ORDER BY
                        CASE
                            WHEN h.status = \'queued\' THEN 0
                            WHEN h.status = \'assigned\' AND h.assigned_until IS NOT NULL AND h.assigned_until <= NOW() THEN 1
                            WHEN h.status = \'assigned\' THEN 2
                            ELSE 3
                        END ASC,
                        COALESCE(h.assigned_until, h.queued_at) ASC,
                        h.id DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'live_queue',
            'columns' => [
                ['key' => 'handoff_id', 'label' => 'Handoff'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'queue_state', 'label' => 'Estado cola'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'assigned_agent', 'label' => 'Agente'],
                ['key' => 'queued_at', 'label' => 'En cola'],
                ['key' => 'assigned_at', 'label' => 'Asignado'],
                ['key' => 'assigned_until', 'label' => 'TTL'],
                ['key' => 'notes', 'label' => 'Notas'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchTransferDrilldown(string $fromDateTime, string $toDateTimeExclusive, ?int $roleId, ?int $agentId, int $limit, int $offset): array
    {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_transfer');

        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_handoff_events e
                     INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                     WHERE e.event_type = \'transferred\'
                       AND e.created_at >= :from
                       AND e.created_at < :to';

        $rowsSql = 'SELECT
                        e.id AS event_id,
                        e.handoff_id,
                        h.conversation_id,
                        h.wa_number,
                        COALESCE(r.name, \"Sin rol\") AS role_name,
                        COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \" \", u.last_name)), \"\"), NULLIF(u.nombre, \"\"), u.username, \"Sistema\") AS actor,
                        e.notes,
                        e.created_at
                    FROM whatsapp_handoff_events e
                    INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                    LEFT JOIN roles r ON r.id = h.handoff_role_id
                    LEFT JOIN users u ON u.id = e.actor_user_id
                    WHERE e.event_type = \'transferred\'
                      AND e.created_at >= :from
                      AND e.created_at < :to';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ] + $filter['params'];

        if ($filter['where'] !== '') {
            $countSql .= ' AND ' . $filter['where'];
            $rowsSql .= ' AND ' . $filter['where'];
        }

        $rowsSql .= ' ORDER BY e.created_at DESC, e.id DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'handoff_transfers',
            'columns' => [
                ['key' => 'event_id', 'label' => 'Evento'],
                ['key' => 'handoff_id', 'label' => 'Handoff'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'actor', 'label' => 'Transferido por'],
                ['key' => 'notes', 'label' => 'Notas'],
                ['key' => 'created_at', 'label' => 'Fecha'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchReopenDrilldown(
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId,
        ?int $agentId,
        int $windowHours,
        int $limit,
        int $offset
    ): array {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_reopen');

        $resolvedSql = 'SELECT
                            h.id AS handoff_id,
                            h.conversation_id,
                            h.wa_number,
                            h.handoff_role_id,
                            h.assigned_agent_id,
                            MIN(e.created_at) AS resolved_at
                        FROM whatsapp_handoff_events e
                        INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                        WHERE e.event_type = \'resolved\'
                          AND e.created_at >= :from
                          AND e.created_at < :to';

        if ($filter['where'] !== '') {
            $resolvedSql .= ' AND ' . $filter['where'];
        }

        $resolvedSql .= ' GROUP BY h.id, h.conversation_id, h.wa_number, h.handoff_role_id, h.assigned_agent_id';

        $countSql = 'SELECT COUNT(*)
                     FROM (
                        SELECT
                            resolved.handoff_id,
                            MIN(m.message_timestamp) AS reopened_at
                        FROM (' . $resolvedSql . ') resolved
                        INNER JOIN whatsapp_messages m
                            ON m.conversation_id = resolved.conversation_id
                           AND m.direction = \'inbound\'
                           AND m.message_timestamp > resolved.resolved_at
                           AND m.message_timestamp <= DATE_ADD(resolved.resolved_at, INTERVAL :window_hours HOUR)
                        GROUP BY resolved.handoff_id
                     ) reopen';

        $rowsSql = 'SELECT
                        reopen.handoff_id,
                        reopen.conversation_id,
                        reopen.wa_number,
                        COALESCE(r.name, \"Sin rol\") AS role_name,
                        COALESCE(NULLIF(TRIM(CONCAT(u.first_name, \" \", u.last_name)), \"\"), NULLIF(u.nombre, \"\"), u.username, \"Sin asignar\") AS assigned_agent,
                        reopen.resolved_at,
                        reopen.reopened_at,
                        TIMESTAMPDIFF(MINUTE, reopen.resolved_at, reopen.reopened_at) AS reopen_after_minutes
                    FROM (
                        SELECT
                            resolved.handoff_id,
                            resolved.conversation_id,
                            resolved.wa_number,
                            resolved.handoff_role_id,
                            resolved.assigned_agent_id,
                            resolved.resolved_at,
                            MIN(m.message_timestamp) AS reopened_at
                        FROM (' . $resolvedSql . ') resolved
                        INNER JOIN whatsapp_messages m
                            ON m.conversation_id = resolved.conversation_id
                           AND m.direction = \'inbound\'
                           AND m.message_timestamp > resolved.resolved_at
                           AND m.message_timestamp <= DATE_ADD(resolved.resolved_at, INTERVAL :window_hours HOUR)
                        GROUP BY
                            resolved.handoff_id,
                            resolved.conversation_id,
                            resolved.wa_number,
                            resolved.handoff_role_id,
                            resolved.assigned_agent_id,
                            resolved.resolved_at
                    ) reopen
                    LEFT JOIN roles r ON r.id = reopen.handoff_role_id
                    LEFT JOIN users u ON u.id = reopen.assigned_agent_id
                    ORDER BY reopen.reopened_at DESC, reopen.handoff_id DESC';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
            ':window_hours' => $windowHours,
        ] + $filter['params'];

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => $windowHours === 24 ? 'reopened_24h' : 'reopened_72h',
            'columns' => [
                ['key' => 'handoff_id', 'label' => 'Handoff'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'role_name', 'label' => 'Equipo'],
                ['key' => 'assigned_agent', 'label' => 'Agente'],
                ['key' => 'resolved_at', 'label' => 'Resuelto'],
                ['key' => 'reopened_at', 'label' => 'Reabierto'],
                ['key' => 'reopen_after_minutes', 'label' => 'Reapertura (min)'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchAutoserviceDrilldown(
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId,
        ?int $agentId,
        int $limit,
        int $offset
    ): array {
        $filter = $this->buildHandoffFilters('h', $roleId, $agentId, 'drill_autoservice');

        $handoffExistsSql = 'SELECT 1
                             FROM whatsapp_handoffs h
                             WHERE h.conversation_id = inbound.conversation_id
                               AND h.queued_at >= :autoservice_handoff_from
                               AND h.queued_at < :autoservice_handoff_to';
        if ($filter['where'] !== '') {
            $handoffExistsSql .= ' AND ' . $filter['where'];
        }

        $countSql = 'SELECT COUNT(*)
                     FROM (
                        SELECT DISTINCT m.conversation_id
                        FROM whatsapp_messages m
                        WHERE m.direction = \'inbound\'
                          AND m.message_timestamp >= :from
                          AND m.message_timestamp < :to
                     ) inbound
                     WHERE NOT EXISTS (' . $handoffExistsSql . ')';

        $rowsSql = 'SELECT
                        inbound.conversation_id,
                        c.wa_number,
                        COALESCE(NULLIF(c.display_name, ""), NULLIF(c.patient_full_name, ""), c.wa_number) AS contacto,
                        c.patient_hc_number,
                        inbound.inbound_count,
                        inbound.last_inbound_at,
                        c.last_message_at,
                        c.last_message_preview
                    FROM (
                        SELECT
                            m.conversation_id,
                            COUNT(*) AS inbound_count,
                            MAX(m.message_timestamp) AS last_inbound_at
                        FROM whatsapp_messages m
                        WHERE m.direction = \'inbound\'
                          AND m.message_timestamp >= :from
                          AND m.message_timestamp < :to
                        GROUP BY m.conversation_id
                    ) inbound
                    INNER JOIN whatsapp_conversations c ON c.id = inbound.conversation_id
                    WHERE NOT EXISTS (' . $handoffExistsSql . ')
                    ORDER BY inbound.last_inbound_at DESC, inbound.conversation_id DESC';

        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
            ':autoservice_handoff_from' => $fromDateTime,
            ':autoservice_handoff_to' => $toDateTimeExclusive,
        ] + $filter['params'];

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'autoservice_rate',
            'columns' => [
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'contacto', 'label' => 'Contacto'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'patient_hc_number', 'label' => 'HC'],
                ['key' => 'inbound_count', 'label' => 'Inbound'],
                ['key' => 'last_inbound_at', 'label' => 'Último inbound'],
                ['key' => 'last_message_at', 'label' => 'Último mensaje'],
                ['key' => 'last_message_preview', 'label' => 'Preview'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchFallbackDrilldown(string $fromDateTime, string $toDateTimeExclusive, int $limit, int $offset): array
    {
        $patterns = $this->fallbackLikePatterns();
        $conditions = [];
        $params = [
            ':from' => $fromDateTime,
            ':to' => $toDateTimeExclusive,
        ];

        foreach ($patterns as $index => $pattern) {
            $param = ':fallback_pattern_' . $index;
            $conditions[] = 'LOWER(m.body) LIKE ' . $param;
            $params[$param] = $pattern;
        }

        if ($conditions === []) {
            return [
                'metric' => 'fallback_rate',
                'columns' => [
                    ['key' => 'message_id', 'label' => 'ID'],
                    ['key' => 'conversation_id', 'label' => 'Conversación'],
                    ['key' => 'contacto', 'label' => 'Contacto'],
                    ['key' => 'wa_number', 'label' => 'Número'],
                    ['key' => 'body', 'label' => 'Mensaje fallback'],
                    ['key' => 'message_timestamp', 'label' => 'Fecha'],
                ],
                'rows' => [],
                'total' => 0,
            ];
        }

        $whereFallback = '(' . implode(' OR ', $conditions) . ')';

        $countSql = 'SELECT COUNT(*)
                     FROM whatsapp_messages m
                     WHERE m.direction = \'outbound\'
                       AND m.message_timestamp >= :from
                       AND m.message_timestamp < :to
                       AND m.body IS NOT NULL
                       AND ' . $whereFallback;

        $rowsSql = 'SELECT
                        m.id AS message_id,
                        m.conversation_id,
                        c.wa_number,
                        COALESCE(NULLIF(c.display_name, ""), NULLIF(c.patient_full_name, ""), c.wa_number) AS contacto,
                        m.body,
                        m.status,
                        m.message_timestamp
                    FROM whatsapp_messages m
                    INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                    WHERE m.direction = \'outbound\'
                      AND m.message_timestamp >= :from
                      AND m.message_timestamp < :to
                      AND m.body IS NOT NULL
                      AND ' . $whereFallback . '
                    ORDER BY m.message_timestamp DESC, m.id DESC';

        $result = $this->fetchPaginatedRows($countSql, $rowsSql, $params, $limit, $offset);

        return [
            'metric' => 'fallback_rate',
            'columns' => [
                ['key' => 'message_id', 'label' => 'ID'],
                ['key' => 'conversation_id', 'label' => 'Conversación'],
                ['key' => 'contacto', 'label' => 'Contacto'],
                ['key' => 'wa_number', 'label' => 'Número'],
                ['key' => 'body', 'label' => 'Mensaje fallback'],
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'message_timestamp', 'label' => 'Fecha'],
            ],
            'rows' => $result['rows'],
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{metric:string,columns:array<int,array{key:string,label:string}>,rows:array<int,array<string,mixed>>,total:int}
     */
    private function fetchTopMenuOptionsDrilldown(
        string $fromDateTime,
        string $toDateTimeExclusive,
        ?int $roleId,
        ?int $agentId,
        int $limit,
        int $offset
    ): array {
        $allRows = $this->fetchTopMenuOptionBreakdown($fromDateTime, $toDateTimeExclusive, $roleId, $agentId, 200);
        $totalRows = count($allRows);
        $totalSelections = 0;
        foreach ($allRows as $row) {
            $totalSelections += (int) ($row['total'] ?? 0);
        }

        $pageRows = array_slice($allRows, $offset, $limit);
        $rows = [];
        foreach ($pageRows as $row) {
            $count = (int) ($row['total'] ?? 0);
            $rows[] = [
                'option_label' => (string) ($row['option_label'] ?? '[Sin opción]'),
                'total' => $count,
                'share_percent' => $totalSelections > 0 ? round(($count / $totalSelections) * 100, 2) : 0.0,
            ];
        }

        return [
            'metric' => 'top_menu_options',
            'columns' => [
                ['key' => 'option_label', 'label' => 'Opción'],
                ['key' => 'total', 'label' => 'Selecciones'],
                ['key' => 'share_percent', 'label' => 'Participación (%)'],
            ],
            'rows' => $rows,
            'total' => $totalRows,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{total:int,rows:array<int,array<string,mixed>>}
     */
    private function fetchPaginatedRows(string $countSql, string $rowsSql, array $params, int $limit, int $offset): array
    {
        $countParams = $this->filterParamsForSql($countSql, $params);
        $total = $this->fetchScalarInt($countSql, $countParams);

        $stmt = $this->pdo->prepare($rowsSql . ' LIMIT :limit OFFSET :offset');
        $rowParams = $this->filterParamsForSql($rowsSql, $params);
        foreach ($rowParams as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
                continue;
            }
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows' => is_array($rows) ? $rows : [],
        ];
    }

    /**
     * @return array{where:string,params:array<string,int>}
     */
    private function buildHandoffFilters(string $alias, ?int $roleId, ?int $agentId, string $prefix): array
    {
        $where = [];
        $params = [];

        if ($roleId !== null && $roleId > 0) {
            $paramKey = ':' . $prefix . '_role_id';
            $where[] = $alias . '.handoff_role_id = ' . $paramKey;
            $params[$paramKey] = $roleId;
        }

        if ($agentId !== null && $agentId > 0) {
            $paramKey = ':' . $prefix . '_agent_id';
            $where[] = $alias . '.assigned_agent_id = ' . $paramKey;
            $params[$paramKey] = $agentId;
        }

        return [
            'where' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fallbackLikePatterns(): array
    {
        return [
            '%no te entend%',
            '%no logre identificar%',
            '%no logré identificar%',
            '%no pude entender%',
            '%no comprendi%',
            '%no comprendí%',
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchScalarInt(string $sql, array $params = []): int
    {
        $params = $this->filterParamsForSql($sql, $params);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchScalarFloat(string $sql, array $params = []): ?float
    {
        $params = $this->filterParamsForSql($sql, $params);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function filterParamsForSql(string $sql, array $params): array
    {
        if ($params === []) {
            return [];
        }

        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $matches);
        if (empty($matches[0])) {
            return [];
        }

        $allowed = array_fill_keys(array_unique($matches[0]), true);
        $filtered = [];
        foreach ($params as $key => $value) {
            if (isset($allowed[$key])) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
