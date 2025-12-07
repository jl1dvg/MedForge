-- Checklist de etapas por solicitud (kanban basado en to-dos)
CREATE TABLE IF NOT EXISTS solicitud_checklist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT UNSIGNED NOT NULL,
    etapa_slug VARCHAR(50) NOT NULL,
    completado_at DATETIME NULL,
    completado_por INT UNSIGNED NULL,
    nota VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY solicitud_etapa_unique (solicitud_id, etapa_slug),
    KEY solicitud_idx (solicitud_id),
    KEY completado_idx (completado_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de cambios para trazabilidad (quién marcó/desmarcó/forzó)
CREATE TABLE IF NOT EXISTS solicitud_checklist_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT UNSIGNED NOT NULL,
    etapa_slug VARCHAR(50) NOT NULL,
    accion ENUM('completar','desmarcar','forzar') NOT NULL,
    actor_id INT UNSIGNED NULL,
    nota VARCHAR(255) NULL,
    old_completado_at DATETIME NULL,
    new_completado_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY solicitud_idx (solicitud_id),
    KEY etapa_idx (etapa_slug),
    KEY created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
