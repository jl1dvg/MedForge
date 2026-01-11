-- Agrega referencia al proyecto CRM en solicitud_crm_detalles (idempotente)

SET @project_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'solicitud_crm_detalles'
      AND COLUMN_NAME = 'crm_project_id'
);

SET @sql = IF(
    @project_column_exists = 0,
    'ALTER TABLE solicitud_crm_detalles ADD COLUMN crm_project_id INT NULL AFTER crm_lead_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @project_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'solicitud_crm_detalles'
      AND INDEX_NAME = 'idx_solicitud_crm_detalles_project'
);

SET @sql = IF(
    @project_index_exists = 0,
    'ALTER TABLE solicitud_crm_detalles ADD INDEX idx_solicitud_crm_detalles_project (crm_project_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @project_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'solicitud_crm_detalles'
      AND COLUMN_NAME = 'crm_project_id'
      AND REFERENCED_TABLE_NAME = 'crm_projects'
);

SET @sql = IF(
    @project_fk_exists = 0,
    'ALTER TABLE solicitud_crm_detalles ADD CONSTRAINT fk_solicitud_crm_detalles_project FOREIGN KEY (crm_project_id) REFERENCES crm_projects (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
