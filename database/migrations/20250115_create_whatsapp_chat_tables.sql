CREATE TABLE IF NOT EXISTS whatsapp_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wa_number VARCHAR(32) NOT NULL,
    display_name VARCHAR(191) DEFAULT NULL,
    patient_hc_number VARCHAR(32) DEFAULT NULL,
    patient_full_name VARCHAR(191) DEFAULT NULL,
    last_message_at DATETIME DEFAULT NULL,
    last_message_direction ENUM('inbound','outbound') DEFAULT NULL,
    last_message_type VARCHAR(32) DEFAULT NULL,
    last_message_preview VARCHAR(500) DEFAULT NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_conversations_number (wa_number),
    KEY idx_whatsapp_conversations_last_message_at (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    wa_message_id VARCHAR(191) DEFAULT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    message_type VARCHAR(32) NOT NULL DEFAULT 'text',
    body TEXT,
    raw_payload JSON DEFAULT NULL,
    status VARCHAR(32) DEFAULT NULL,
    message_timestamp DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_whatsapp_messages_conversation (conversation_id),
    KEY idx_whatsapp_messages_timestamp (conversation_id, message_timestamp, id),
    CONSTRAINT fk_whatsapp_messages_conversation FOREIGN KEY (conversation_id)
        REFERENCES whatsapp_conversations (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
