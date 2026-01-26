-- Agenda interna para citas relacionadas con Sigcenter

CREATE TABLE IF NOT EXISTS agenda_citas (
    id INT NOT NULL AUTO_INCREMENT,
    solicitud_id INT NOT NULL,
    sigcenter_agenda_id VARCHAR(50) DEFAULT NULL,
    sigcenter_pedido_id VARCHAR(50) DEFAULT NULL,
    sigcenter_factura_id VARCHAR(50) DEFAULT NULL,
    fecha_inicio DATETIME DEFAULT NULL,
    fecha_llegada DATETIME DEFAULT NULL,
    payload LONGTEXT DEFAULT NULL,
    response LONGTEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_agenda_citas_solicitud (solicitud_id),
    KEY idx_agenda_citas_sigcenter_agenda (sigcenter_agenda_id),
    CONSTRAINT fk_agenda_citas_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE,
    CONSTRAINT fk_agenda_citas_usuario FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
