-- Extiende el CRM interno de solicitudes con tablas para notas, adjuntos, tareas y detalles

CREATE TABLE IF NOT EXISTS solicitud_crm_detalles (
    solicitud_id INT NOT NULL,
    crm_lead_id INT DEFAULT NULL,
    responsable_id INT DEFAULT NULL,
    contacto_email VARCHAR(255) DEFAULT NULL,
    contacto_telefono VARCHAR(50) DEFAULT NULL,
    fuente VARCHAR(120) DEFAULT NULL,
    pipeline_stage VARCHAR(120) DEFAULT 'Recibido',
    followers TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (solicitud_id),
    KEY idx_solicitud_crm_detalles_responsable (responsable_id),
    KEY idx_solicitud_crm_detalles_stage (pipeline_stage),
    KEY idx_solicitud_crm_detalles_fuente (fuente),
    CONSTRAINT fk_solicitud_crm_detalles_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_crm_detalles_responsable FOREIGN KEY (responsable_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_solicitud_crm_detalles_lead FOREIGN KEY (crm_lead_id) REFERENCES crm_leads (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitud_crm_notas (
    id INT NOT NULL AUTO_INCREMENT,
    solicitud_id INT NOT NULL,
    autor_id INT DEFAULT NULL,
    nota TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_solicitud_crm_notas_solicitud (solicitud_id),
    KEY idx_solicitud_crm_notas_created_at (created_at),
    CONSTRAINT fk_solicitud_crm_notas_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_crm_notas_autor FOREIGN KEY (autor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitud_crm_adjuntos (
    id INT NOT NULL AUTO_INCREMENT,
    solicitud_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta_relativa VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) DEFAULT NULL,
    tamano_bytes BIGINT DEFAULT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    subido_por INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_solicitud_crm_adjuntos_solicitud (solicitud_id),
    KEY idx_solicitud_crm_adjuntos_usuario (subido_por),
    CONSTRAINT fk_solicitud_crm_adjuntos_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_crm_adjuntos_usuario FOREIGN KEY (subido_por) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitud_crm_tareas (
    id INT NOT NULL AUTO_INCREMENT,
    solicitud_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    estado ENUM('pendiente','en_progreso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    remind_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_solicitud_crm_tareas_solicitud (solicitud_id),
    KEY idx_solicitud_crm_tareas_estado (estado),
    KEY idx_solicitud_crm_tareas_asignado (assigned_to),
    KEY idx_solicitud_crm_tareas_due_date (due_date),
    CONSTRAINT fk_solicitud_crm_tareas_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_crm_tareas_asignado FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_solicitud_crm_tareas_creador FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitud_crm_meta (
    id INT NOT NULL AUTO_INCREMENT,
    solicitud_id INT NOT NULL,
    meta_key VARCHAR(120) NOT NULL,
    meta_value TEXT DEFAULT NULL,
    meta_type VARCHAR(50) DEFAULT 'texto',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_solicitud_crm_meta (solicitud_id, meta_key),
    KEY idx_solicitud_crm_meta_solicitud (solicitud_id),
    CONSTRAINT fk_solicitud_crm_meta_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitud_procedimiento (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
