ALTER TABLE whatsapp_conversations
    ADD COLUMN needs_human TINYINT(1) NOT NULL DEFAULT 0 AFTER last_message_preview,
    ADD COLUMN handoff_notes VARCHAR(255) DEFAULT NULL AFTER needs_human,
    ADD INDEX idx_whatsapp_conversations_needs_human (needs_human),
    ADD INDEX idx_whatsapp_conversations_needs_human_last_message (needs_human, last_message_at),
    ADD INDEX idx_whatsapp_conversations_needs_human_updated (needs_human, updated_at);
