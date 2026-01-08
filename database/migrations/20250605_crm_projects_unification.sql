-- Agrega campos transversales a crm_projects para unificación de casos (idempotente)

SET @schema_name = DATABASE();

SET @hc_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND COLUMN_NAME = 'hc_number'
);
SET @sql = IF(@hc_exists = 0, 'ALTER TABLE crm_projects ADD COLUMN hc_number VARCHAR(64) NULL AFTER customer_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @form_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND COLUMN_NAME = 'form_id'
);
SET @sql = IF(@form_exists = 0, 'ALTER TABLE crm_projects ADD COLUMN form_id INT NULL AFTER hc_number', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @source_module_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND COLUMN_NAME = 'source_module'
);
SET @sql = IF(@source_module_exists = 0, 'ALTER TABLE crm_projects ADD COLUMN source_module VARCHAR(32) NULL AFTER form_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @source_ref_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND COLUMN_NAME = 'source_ref_id'
);
SET @sql = IF(@source_ref_exists = 0, 'ALTER TABLE crm_projects ADD COLUMN source_ref_id VARCHAR(64) NULL AFTER source_module', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @episode_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND COLUMN_NAME = 'episode_type'
);
SET @sql = IF(@episode_exists = 0, 'ALTER TABLE crm_projects ADD COLUMN episode_type VARCHAR(32) NULL AFTER source_ref_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @eye_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND COLUMN_NAME = 'eye'
);
SET @sql = IF(@eye_exists = 0, 'ALTER TABLE crm_projects ADD COLUMN eye VARCHAR(8) NULL AFTER episode_type', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_hc_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND INDEX_NAME = 'idx_crm_projects_hc'
);
SET @sql = IF(@idx_hc_exists = 0, 'ALTER TABLE crm_projects ADD INDEX idx_crm_projects_hc (hc_number)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_form_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND INDEX_NAME = 'idx_crm_projects_form'
);
SET @sql = IF(@idx_form_exists = 0, 'ALTER TABLE crm_projects ADD INDEX idx_crm_projects_form (form_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_source_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND INDEX_NAME = 'idx_crm_projects_source'
);
SET @sql = IF(@idx_source_exists = 0, 'ALTER TABLE crm_projects ADD INDEX idx_crm_projects_source (source_module, source_ref_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @form_duplicates = (
    SELECT COUNT(*)
    FROM (
        SELECT form_id
        FROM crm_projects
        WHERE form_id IS NOT NULL
        GROUP BY form_id
        HAVING COUNT(*) > 1
    ) dup
);

SET @uq_form_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND INDEX_NAME = 'uq_crm_projects_form_id'
);
SET @sql = IF(@uq_form_exists = 0 AND @form_duplicates = 0, 'ALTER TABLE crm_projects ADD UNIQUE KEY uq_crm_projects_form_id (form_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT form_id, COUNT(*) AS total
FROM crm_projects
WHERE form_id IS NOT NULL
GROUP BY form_id
HAVING COUNT(*) > 1;

SET @idx_clinical_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_projects'
      AND INDEX_NAME = 'idx_crm_projects_clinical'
);
SET @sql = IF(@idx_clinical_exists = 0, 'ALTER TABLE crm_projects ADD INDEX idx_crm_projects_clinical (hc_number, episode_type, eye)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
