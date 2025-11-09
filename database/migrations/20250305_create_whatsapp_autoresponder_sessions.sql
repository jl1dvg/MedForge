CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    wa_number VARCHAR(32) NOT NULL,
    scenario_id VARCHAR(64) DEFAULT NULL,
    node_id VARCHAR(64) DEFAULT NULL,
    awaiting VARCHAR(32) DEFAULT NULL,
    context JSON DEFAULT NULL,
    last_payload JSON DEFAULT NULL,
    last_interaction_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_autoresponder_sessions_conversation (conversation_id),
    UNIQUE KEY uniq_whatsapp_autoresponder_sessions_number (wa_number),
    KEY idx_whatsapp_autoresponder_sessions_updated (updated_at),
    CONSTRAINT fk_whatsapp_autoresponder_sessions_conversation FOREIGN KEY (conversation_id)
        REFERENCES whatsapp_conversations (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

