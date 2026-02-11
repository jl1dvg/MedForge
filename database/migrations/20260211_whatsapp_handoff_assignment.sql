SET @tbl := 'whatsapp_conversations';

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'handoff_role_id'),
    'SELECT "handoff_role_id ya existe"',
    'ALTER TABLE whatsapp_conversations ADD COLUMN handoff_role_id INT NULL AFTER handoff_notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'assigned_user_id'),
    'SELECT "assigned_user_id ya existe"',
    'ALTER TABLE whatsapp_conversations ADD COLUMN assigned_user_id INT NULL AFTER handoff_role_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'assigned_at'),
    'SELECT "assigned_at ya existe"',
    'ALTER TABLE whatsapp_conversations ADD COLUMN assigned_at DATETIME NULL AFTER assigned_user_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'handoff_requested_at'),
    'SELECT "handoff_requested_at ya existe"',
    'ALTER TABLE whatsapp_conversations ADD COLUMN handoff_requested_at DATETIME NULL AFTER assigned_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_conversations_handoff_role'
    ),
    'SELECT "idx_whatsapp_conversations_handoff_role ya existe";',
    'ALTER TABLE whatsapp_conversations ADD INDEX idx_whatsapp_conversations_handoff_role (handoff_role_id, needs_human, last_message_at);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = @tbl
          AND index_name = 'idx_whatsapp_conversations_assigned_user'
    ),
    'SELECT "idx_whatsapp_conversations_assigned_user ya existe";',
    'ALTER TABLE whatsapp_conversations ADD INDEX idx_whatsapp_conversations_assigned_user (assigned_user_id, updated_at);'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
