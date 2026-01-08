-- Agrega campos transversales y SLA a crm_tasks para unificación de tareas (idempotente)

SET @schema_name = DATABASE();

SET @status_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'status'
);
SET @sql = IF(
    @status_exists > 0,
    "ALTER TABLE crm_tasks MODIFY COLUMN status ENUM('pendiente','en_progreso','en_proceso','bloqueada','completada','cancelada') NOT NULL DEFAULT 'pendiente'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @priority_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'priority'
);
SET @sql = IF(@priority_exists = 0, "ALTER TABLE crm_tasks ADD COLUMN priority ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media' AFTER status", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @company_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'company_id'
);
SET @sql = IF(@company_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @due_at_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'due_at'
);
SET @sql = IF(@due_at_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN due_at DATETIME NULL AFTER due_date', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @category_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'category'
);
SET @sql = IF(@category_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN category VARCHAR(32) NULL AFTER priority', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tags_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'tags'
);
SET @sql = IF(@tags_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN tags JSON NULL AFTER category', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @metadata_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'metadata'
);
SET @sql = IF(@metadata_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN metadata JSON NULL AFTER tags', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @lead_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'lead_id'
);
SET @sql = IF(@lead_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN lead_id INT NULL AFTER project_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @customer_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'customer_id'
);
SET @sql = IF(@customer_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN customer_id INT NULL AFTER lead_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @hc_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'hc_number'
);
SET @sql = IF(@hc_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN hc_number VARCHAR(64) NULL AFTER customer_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @form_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'form_id'
);
SET @sql = IF(@form_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN form_id INT NULL AFTER hc_number', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @source_module_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'source_module'
);
SET @sql = IF(@source_module_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN source_module VARCHAR(32) NULL AFTER form_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @source_ref_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'source_ref_id'
);
SET @sql = IF(@source_ref_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN source_ref_id VARCHAR(64) NULL AFTER source_module', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @episode_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'episode_type'
);
SET @sql = IF(@episode_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN episode_type VARCHAR(32) NULL AFTER source_ref_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @eye_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'eye'
);
SET @sql = IF(@eye_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN eye VARCHAR(8) NULL AFTER episode_type', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @remind_at_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'remind_at'
);
SET @sql = IF(@remind_at_exists = 0, 'ALTER TABLE crm_tasks ADD COLUMN remind_at DATETIME NULL AFTER due_at', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @remind_channel_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND COLUMN_NAME = 'remind_channel'
);
SET @sql = IF(@remind_channel_exists = 0, "ALTER TABLE crm_tasks ADD COLUMN remind_channel ENUM('whatsapp','email','in_app') NOT NULL DEFAULT 'in_app' AFTER remind_at", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_lead_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_lead'
);
SET @sql = IF(@idx_lead_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_lead (lead_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_assigned_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_assigned'
);
SET @sql = IF(@idx_company_assigned_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_assigned (company_id, assigned_to, status, due_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_hc_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_hc'
);
SET @sql = IF(@idx_company_hc_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_hc (company_id, hc_number, due_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_form_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_form'
);
SET @sql = IF(@idx_company_form_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_form (company_id, form_id, due_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_company_source_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_tasks_company_source'
);
SET @sql = IF(@idx_company_source_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_tasks_company_source (company_id, source_module, source_ref_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_customer_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_customer'
);
SET @sql = IF(@idx_customer_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_customer (customer_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @reminders_company_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_task_reminders'
      AND COLUMN_NAME = 'company_id'
);
SET @sql = IF(@reminders_company_exists = 0, 'ALTER TABLE crm_task_reminders ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER task_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_reminders_company_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_task_reminders'
      AND INDEX_NAME = 'idx_crm_task_reminders_company'
);
SET @sql = IF(@idx_reminders_company_exists = 0, 'ALTER TABLE crm_task_reminders ADD INDEX idx_crm_task_reminders_company (company_id, remind_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_hc_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_hc'
);
SET @sql = IF(@idx_hc_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_hc (hc_number)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_form_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_form'
);
SET @sql = IF(@idx_form_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_form (form_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_source_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_source'
);
SET @sql = IF(@idx_source_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_source (source_module, source_ref_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_due_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_due_at'
);
SET @sql = IF(@idx_due_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_due_at (due_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_remind_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_tasks'
      AND INDEX_NAME = 'idx_crm_tasks_remind_at'
);
SET @sql = IF(@idx_remind_exists = 0, 'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_remind_at (remind_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @reminder_channel_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'crm_task_reminders'
      AND COLUMN_NAME = 'channel'
);
SET @sql = IF(@reminder_channel_exists > 0, "ALTER TABLE crm_task_reminders MODIFY COLUMN channel ENUM('whatsapp','email','in_app') NOT NULL DEFAULT 'in_app'", 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS crm_task_evidence (
    id INT NOT NULL AUTO_INCREMENT,
    task_id INT NOT NULL,
    company_id INT NOT NULL DEFAULT 1,
    evidence_type VARCHAR(40) NOT NULL,
    payload TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_task_evidence_task (task_id),
    KEY idx_crm_task_evidence_company (company_id),
    CONSTRAINT fk_crm_task_evidence_task FOREIGN KEY (task_id) REFERENCES crm_tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_task_evidence_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_task_templates (
    id INT NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL DEFAULT 1,
    task_type VARCHAR(64) NOT NULL,
    whatsapp_template VARCHAR(128) NOT NULL,
    variables JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_task_templates_company (company_id),
    KEY idx_crm_task_templates_type (task_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
