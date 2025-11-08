CREATE TABLE IF NOT EXISTS whatsapp_contact_consent (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wa_number VARCHAR(32) NOT NULL,
    cedula VARCHAR(32) NOT NULL,
    patient_hc_number VARCHAR(32) DEFAULT NULL,
    patient_full_name VARCHAR(255) DEFAULT NULL,
    consent_status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    consent_source ENUM('local','registry','manual') NOT NULL DEFAULT 'local',
    consent_asked_at DATETIME DEFAULT NULL,
    consent_responded_at DATETIME DEFAULT NULL,
    extra_payload JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contact_identifier (wa_number, cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
