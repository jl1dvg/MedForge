-- Bitácora operativa para mailbox de exámenes

CREATE TABLE IF NOT EXISTS examen_mail_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NULL,
    form_id VARCHAR(50) NULL,
    hc_number VARCHAR(50) NULL,
    to_emails TEXT NOT NULL,
    cc_emails TEXT NULL,
    subject VARCHAR(255) NOT NULL,
    body_text MEDIUMTEXT NULL,
    body_html MEDIUMTEXT NULL,
    channel ENUM('email', 'sms', 'whatsapp', 'in_app') NOT NULL DEFAULT 'email',
    sent_by_user_id INT NULL,
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_examen_mail_log_examen (examen_id),
    KEY idx_examen_mail_log_status (status),
    KEY idx_examen_mail_log_sent_at (sent_at),
    KEY idx_examen_mail_log_user (sent_by_user_id),
    CONSTRAINT fk_examen_mail_log_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_mail_log_user FOREIGN KEY (sent_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_estado_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    estado_anterior VARCHAR(80) NULL,
    estado_nuevo VARCHAR(80) NOT NULL,
    changed_by INT NULL,
    origen VARCHAR(60) NULL,
    observacion VARCHAR(255) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_examen_estado_log_examen (examen_id),
    KEY idx_examen_estado_log_changed_at (changed_at),
    KEY idx_examen_estado_log_user (changed_by),
    CONSTRAINT fk_examen_estado_log_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_estado_log_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
