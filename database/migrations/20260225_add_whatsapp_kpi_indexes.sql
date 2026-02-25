SET @tbl := 'whatsapp_conversations';

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_conversations_created_at'
    ),
    'SELECT "idx_whatsapp_conversations_created_at ya existe";',
    'ALTER TABLE whatsapp_conversations ADD INDEX idx_whatsapp_conversations_created_at (created_at);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl := 'whatsapp_messages';

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_messages_message_timestamp'
    ),
    'SELECT "idx_whatsapp_messages_message_timestamp ya existe";',
    'ALTER TABLE whatsapp_messages ADD INDEX idx_whatsapp_messages_message_timestamp (message_timestamp);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_messages_direction_timestamp'
    ),
    'SELECT "idx_whatsapp_messages_direction_timestamp ya existe";',
    'ALTER TABLE whatsapp_messages ADD INDEX idx_whatsapp_messages_direction_timestamp (direction, message_timestamp);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl := 'whatsapp_handoffs';

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_handoffs_queued_at'
    ),
    'SELECT "idx_whatsapp_handoffs_queued_at ya existe";',
    'ALTER TABLE whatsapp_handoffs ADD INDEX idx_whatsapp_handoffs_queued_at (queued_at);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_handoffs_queued_role_agent_status'
    ),
    'SELECT "idx_whatsapp_handoffs_queued_role_agent_status ya existe";',
    'ALTER TABLE whatsapp_handoffs ADD INDEX idx_whatsapp_handoffs_queued_role_agent_status (queued_at, handoff_role_id, assigned_agent_id, status);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl := 'whatsapp_handoff_events';

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_handoff_events_type_created_handoff'
    ),
    'SELECT "idx_whatsapp_handoff_events_type_created_handoff ya existe";',
    'ALTER TABLE whatsapp_handoff_events ADD INDEX idx_whatsapp_handoff_events_type_created_handoff (event_type, created_at, handoff_id);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
