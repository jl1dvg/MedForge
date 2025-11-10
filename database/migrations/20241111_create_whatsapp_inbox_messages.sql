CREATE TABLE IF NOT EXISTS whatsapp_inbox_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wa_number VARCHAR(32) NOT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    message_type VARCHAR(32) NOT NULL,
    message_body TEXT NOT NULL,
    message_id VARCHAR(128) DEFAULT NULL,
    payload LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_inbox_number (wa_number),
    INDEX idx_whatsapp_inbox_created_at (created_at),
    INDEX idx_whatsapp_inbox_direction (direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
