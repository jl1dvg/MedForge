-- Auditoria y plan de reversion para whatsapp:handoff-auto-assign
-- Fecha: 2026-06-24
-- No ejecutar updates sin revisar los SELECT primero.

-- 1) Detalle completo de conversaciones modificadas por autoassign.
-- Incluye: conversation_id, wa_number, fecha, usuario, asignacion previa inferida,
-- bucket operacional, topic, origen, respuesta posterior y actividad posterior.
SELECT
  e.id AS auto_event_id,
  h.conversation_id,
  h.wa_number,
  e.created_at AS auto_assigned_at,
  h.assigned_agent_id AS assigned_user_id,
  COALESCE(NULLIF(u.nombre, ''), u.username, CONCAT('Usuario ', h.assigned_agent_id)) AS assigned_user_name,
  (
    SELECT h2.assigned_agent_id
    FROM whatsapp_handoffs h2
    WHERE h2.conversation_id = h.conversation_id
      AND h2.id <> h.id
      AND h2.assigned_agent_id IS NOT NULL
      AND h2.created_at < e.created_at
    ORDER BY h2.created_at DESC, h2.id DESC
    LIMIT 1
  ) AS inferred_previous_assigned_user_id,
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM whatsapp_handoff_events e2
      WHERE e2.handoff_id = h.id
        AND e2.id < e.id
        AND e2.event_type IN ('assigned', 'auto_assigned', 'transferred')
    ) THEN 1 ELSE 0
  END AS had_prior_assignment_same_handoff,
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM whatsapp_handoffs h2
      WHERE h2.conversation_id = h.conversation_id
        AND h2.id <> h.id
        AND h2.assigned_agent_id IS NOT NULL
        AND h2.created_at < e.created_at
    ) THEN 1 ELSE 0
  END AS had_prior_assignment_same_conversation,
  CASE
    WHEN TIMESTAMPDIFF(MINUTE, COALESCE(h.queued_at, c.handoff_requested_at, c.last_message_at, c.created_at), e.created_at) <= 1440
      THEN CASE
        WHEN inbound.latest_inbound_at IS NOT NULL
          AND inbound.latest_inbound_at >= DATE_SUB(e.created_at, INTERVAL 24 HOUR)
          THEN 'HOT_OPEN'
        ELSE 'HOT_NEEDS_TEMPLATE'
      END
    WHEN TIMESTAMPDIFF(MINUTE, COALESCE(h.queued_at, c.handoff_requested_at, c.last_message_at, c.created_at), e.created_at) <= 10080
      THEN 'RESCUE'
    WHEN TIMESTAMPDIFF(MINUTE, COALESCE(h.queued_at, c.handoff_requested_at, c.last_message_at, c.created_at), e.created_at) <= 43200
      THEN 'BACKLOG'
    ELSE 'LOST'
  END AS operational_bucket_at_assignment,
  h.topic,
  COALESCE(NULLIF(a.source_category, ''), 'unknown') AS origin,
  (
    SELECT MIN(COALESCE(m.message_timestamp, m.created_at))
    FROM whatsapp_messages m
    WHERE m.conversation_id = h.conversation_id
      AND m.direction = 'outbound'
      AND COALESCE(m.message_timestamp, m.created_at) > e.created_at
  ) AS first_outbound_after_autoassign_at,
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM whatsapp_messages m
      WHERE m.conversation_id = h.conversation_id
        AND COALESCE(m.message_timestamp, m.created_at) > e.created_at
    ) THEN 1 ELSE 0
  END AS has_any_message_after_autoassign,
  c.last_message_at,
  c.last_message_direction,
  c.needs_human AS current_needs_human,
  c.assigned_user_id AS current_conversation_assigned_user_id,
  h.status AS current_handoff_status,
  h.assigned_agent_id AS current_handoff_assigned_user_id
FROM whatsapp_handoff_events e
JOIN whatsapp_handoffs h ON h.id = e.handoff_id
JOIN whatsapp_conversations c ON c.id = h.conversation_id
LEFT JOIN users u ON u.id = h.assigned_agent_id
LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = h.conversation_id
LEFT JOIN (
  SELECT conversation_id, MAX(COALESCE(message_timestamp, created_at)) AS latest_inbound_at
  FROM whatsapp_messages
  WHERE direction = 'inbound'
  GROUP BY conversation_id
) inbound ON inbound.conversation_id = h.conversation_id
WHERE e.event_type = 'auto_assigned'
  AND e.actor_user_id IS NULL
ORDER BY e.created_at ASC, e.id ASC;

-- 2) Conteo de candidatos de rollback conservador.
SELECT COUNT(*) AS rollback_level_a_candidates
FROM whatsapp_handoff_events e
JOIN whatsapp_handoffs h ON h.id = e.handoff_id
JOIN whatsapp_conversations c ON c.id = h.conversation_id
WHERE e.event_type = 'auto_assigned'
  AND e.actor_user_id IS NULL
  AND h.status = 'assigned'
  AND c.needs_human = 1
  AND c.assigned_user_id = h.assigned_agent_id
  AND NOT EXISTS (
    SELECT 1
    FROM whatsapp_messages m
    WHERE m.conversation_id = h.conversation_id
      AND m.direction = 'outbound'
      AND COALESCE(m.message_timestamp, m.created_at) > e.created_at
  )
  AND NOT EXISTS (
    SELECT 1
    FROM whatsapp_handoff_events e2
    WHERE e2.handoff_id = h.id
      AND e2.created_at > e.created_at
      AND e2.event_type IN ('assigned', 'transferred', 'resolved')
  );

-- 3) Rollback nivel A.
-- Por defecto termina con ROLLBACK. Revisar conteos antes de cambiar a COMMIT.
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_whatsapp_autoassign_rollback AS
SELECT
  e.id AS auto_event_id,
  h.id AS handoff_id,
  h.conversation_id,
  h.assigned_agent_id
FROM whatsapp_handoff_events e
JOIN whatsapp_handoffs h ON h.id = e.handoff_id
JOIN whatsapp_conversations c ON c.id = h.conversation_id
WHERE e.event_type = 'auto_assigned'
  AND e.actor_user_id IS NULL
  AND h.status = 'assigned'
  AND c.needs_human = 1
  AND c.assigned_user_id = h.assigned_agent_id
  AND NOT EXISTS (
    SELECT 1
    FROM whatsapp_messages m
    WHERE m.conversation_id = h.conversation_id
      AND m.direction = 'outbound'
      AND COALESCE(m.message_timestamp, m.created_at) > e.created_at
  )
  AND NOT EXISTS (
    SELECT 1
    FROM whatsapp_handoff_events e2
    WHERE e2.handoff_id = h.id
      AND e2.created_at > e.created_at
      AND e2.event_type IN ('assigned', 'transferred', 'resolved')
  );

SELECT COUNT(*) AS rows_to_rollback FROM tmp_whatsapp_autoassign_rollback;

UPDATE whatsapp_conversations c
JOIN tmp_whatsapp_autoassign_rollback r ON r.conversation_id = c.id
SET
  c.assigned_user_id = NULL,
  c.assigned_at = NULL,
  c.needs_human = 1;

UPDATE whatsapp_handoffs h
JOIN tmp_whatsapp_autoassign_rollback r ON r.handoff_id = h.id
SET
  h.status = 'queued',
  h.assigned_agent_id = NULL,
  h.assigned_at = NULL,
  h.assigned_until = NULL;

INSERT INTO whatsapp_handoff_events (handoff_id, event_type, actor_user_id, notes, created_at)
SELECT
  r.handoff_id,
  'autoassign_rollback',
  NULL,
  CONCAT('Rollback planned from auto_event_id=', r.auto_event_id),
  NOW()
FROM tmp_whatsapp_autoassign_rollback r;

-- Mantener ROLLBACK hasta aprobacion explicita.
ROLLBACK;
-- COMMIT;
