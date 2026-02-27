CREATE TABLE IF NOT EXISTS whatsapp_agent_presence (
    user_id INT NOT NULL PRIMARY KEY,
    status ENUM('available','away','offline') NOT NULL DEFAULT 'available',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_agent_presence_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

