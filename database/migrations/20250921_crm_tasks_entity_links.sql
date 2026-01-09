-- Extiende crm_tasks con entidad/paciente y nuevos índices de unificación (idempotente)

SET @schema_name = DATABASE();

SET @entity_type_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'entity_type'
);
SET @sql = IF(@entity_type_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN entity_type VARCHAR(32) NULL AFTER project_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @entity_id_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'entity_id'
);
SET @sql = IF(@entity_id_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN entity_id VARCHAR(64) NULL AFTER entity_type', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @patient_id_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'patient_id'
);
SET @sql = IF(@patient_id_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN patient_id VARCHAR(80) NULL AFTER hc_number', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_project_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_project'
);
SET @sql = IF(@idx_company_project_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_project (company_id, project_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_lead_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_lead'
);
SET @sql = IF(@idx_company_lead_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_lead (company_id, lead_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_hc_basic_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_hc_basic'
);
SET @sql = IF(@idx_company_hc_basic_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_hc_basic (company_id, hc_number)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_entity_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_entity'
);
SET @sql = IF(@idx_company_entity_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_entity (company_id, entity_type, entity_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_due_status_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_due_status'
);
SET @sql = IF(@idx_company_due_status_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_due_status (company_id, due_at, status)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
