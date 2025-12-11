-- Bloqueos de agenda creados desde el CRM/Kanban

CREATE TABLE IF NOT EXISTS crm_calendar_blocks (
    id INT NOT NULL AUTO_INCREMENT,
    solicitud_id INT NOT NULL,
    doctor VARCHAR(255) DEFAULT NULL,
    sala VARCHAR(255) DEFAULT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calendar_blocks_solicitud (solicitud_id),
    KEY idx_calendar_blocks_rango (fecha_inicio, fecha_fin),
    CONSTRAINT fk_calendar_blocks_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_blocks_usuario FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

