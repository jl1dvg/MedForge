-- Bloqueos de agenda para CRM de exámenes

CREATE TABLE IF NOT EXISTS examen_crm_calendar_blocks (
    id INT NOT NULL AUTO_INCREMENT,
    examen_id INT UNSIGNED NOT NULL,
    doctor VARCHAR(255) DEFAULT NULL,
    sala VARCHAR(255) DEFAULT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_examen_calendar_blocks_examen (examen_id),
    KEY idx_examen_calendar_blocks_rango (fecha_inicio, fecha_fin),
    CONSTRAINT fk_examen_calendar_blocks_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_calendar_blocks_usuario FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

