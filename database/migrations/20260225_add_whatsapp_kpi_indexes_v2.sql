SET @tbl := 'whatsapp_messages';

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_messages_conversation_direction_time'
    ),
    'SELECT "idx_whatsapp_messages_conversation_direction_time ya existe";',
    'ALTER TABLE whatsapp_messages ADD INDEX idx_whatsapp_messages_conversation_direction_time (conversation_id, direction, message_timestamp);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tbl := 'whatsapp_handoffs';

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_handoffs_status_assigned_until'
    ),
    'SELECT "idx_whatsapp_handoffs_status_assigned_until ya existe";',
    'ALTER TABLE whatsapp_handoffs ADD INDEX idx_whatsapp_handoffs_status_assigned_until (status, assigned_until);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_handoffs_assigned_at'
    ),
    'SELECT "idx_whatsapp_handoffs_assigned_at ya existe";',
    'ALTER TABLE whatsapp_handoffs ADD INDEX idx_whatsapp_handoffs_assigned_at (assigned_at);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
