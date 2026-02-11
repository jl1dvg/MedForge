ALTER TABLE whatsapp_conversations
    ADD COLUMN IF NOT EXISTS handoff_role_id INT NULL AFTER handoff_notes,
    ADD COLUMN IF NOT EXISTS assigned_user_id INT NULL AFTER handoff_role_id,
    ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL AFTER assigned_user_id,
    ADD COLUMN IF NOT EXISTS handoff_requested_at DATETIME NULL AFTER assigned_at,
    ADD INDEX IF NOT EXISTS idx_whatsapp_conversations_handoff_role (handoff_role_id, needs_human, last_message_at),
    ADD INDEX IF NOT EXISTS idx_whatsapp_conversations_assigned_user (assigned_user_id, updated_at);
