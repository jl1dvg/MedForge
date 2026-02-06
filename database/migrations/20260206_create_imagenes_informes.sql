-- Informes para procedimientos proyectados de imágenes

CREATE TABLE IF NOT EXISTS imagenes_informes (
    id INT NOT NULL AUTO_INCREMENT,
    form_id VARCHAR(64) NOT NULL,
    hc_number VARCHAR(64) DEFAULT NULL,
    tipo_examen VARCHAR(255) NOT NULL,
    plantilla VARCHAR(50) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_imagenes_informe_form (form_id),
    KEY idx_imagenes_informe_form (form_id),
    KEY idx_imagenes_informe_form_tipo (form_id, tipo_examen),
    KEY idx_imagenes_informe_hc (hc_number),
    KEY idx_imagenes_informe_plantilla (plantilla),
    CONSTRAINT fk_imagenes_informe_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_imagenes_informe_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
