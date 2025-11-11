
SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'consulta_data'
              AND index_name = 'idx_consulta_data_form_hc'
        ),
        'SELECT "idx_consulta_data_form_hc ya existe";',
        'ALTER TABLE consulta_data ADD INDEX idx_consulta_data_form_hc (form_id, hc_number);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS consulta_examenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hc_number VARCHAR(50) NOT NULL,
    form_id VARCHAR(64) NOT NULL,
    consulta_fecha DATETIME NULL,
    doctor VARCHAR(150) NULL,
    solicitante VARCHAR(150) NULL,
    examen_codigo VARCHAR(50) NULL,
    examen_nombre VARCHAR(255) NOT NULL,
    lateralidad VARCHAR(50) NULL,
    prioridad VARCHAR(50) NULL,
    observaciones TEXT NULL,
    estado VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    turno INT NULL,
    crm_lead_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_consulta_examen (form_id, examen_codigo, examen_nombre(120)),
    KEY idx_consulta_examen_hc (hc_number),
    KEY idx_consulta_examen_estado (estado),
    KEY idx_consulta_examen_turno (turno),
    KEY idx_consulta_examen_fecha (consulta_fecha),
    CONSTRAINT fk_consulta_examenes_consulta FOREIGN KEY (form_id, hc_number)
        REFERENCES consulta_data (form_id, hc_number)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_crm_detalles (
    examen_id INT UNSIGNED NOT NULL PRIMARY KEY,
    crm_lead_id INT NULL,
    responsable_id INT NULL,
    pipeline_stage VARCHAR(64) NULL,
    fuente VARCHAR(120) NULL,
    contacto_email VARCHAR(190) NULL,
    contacto_telefono VARCHAR(40) NULL,
    followers JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_examen_crm_detalles_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_crm_detalles_responsable FOREIGN KEY (responsable_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_examen_crm_detalles_lead FOREIGN KEY (crm_lead_id) REFERENCES crm_leads (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_crm_notas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    autor_id INT NULL,
    nota TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_examen_crm_notas_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_crm_notas_autor FOREIGN KEY (autor_id) REFERENCES users (id) ON DELETE SET NULL,
    KEY idx_examen_crm_notas_examen (examen_id),
    KEY idx_examen_crm_notas_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_crm_adjuntos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta_relativa VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    tamano_bytes INT UNSIGNED NULL,
    descripcion VARCHAR(255) NULL,
    subido_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_examen_crm_adjuntos_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_crm_adjuntos_usuario FOREIGN KEY (subido_por) REFERENCES users (id) ON DELETE SET NULL,
    KEY idx_examen_crm_adjuntos_examen (examen_id),
    KEY idx_examen_crm_adjuntos_usuario (subido_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_crm_tareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    estado ENUM('pendiente', 'en_progreso', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
    assigned_to INT NULL,
    created_by INT NULL,
    due_date DATE NULL,
    remind_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_examen_crm_tareas_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_crm_tareas_asignado FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_examen_crm_tareas_creador FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    KEY idx_examen_crm_tareas_examen (examen_id),
    KEY idx_examen_crm_tareas_estado (estado),
    KEY idx_examen_crm_tareas_asignado (assigned_to),
    KEY idx_examen_crm_tareas_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_crm_meta (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    meta_key VARCHAR(120) NOT NULL,
    meta_value TEXT NULL,
    meta_type ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_examen_crm_meta (examen_id, meta_key),
    KEY idx_examen_crm_meta_examen (examen_id),
    CONSTRAINT fk_examen_crm_meta_examen FOREIGN KEY (examen_id) REFERENCES consulta_examenes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
