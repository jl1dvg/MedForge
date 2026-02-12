-- Agrega trazabilidad de creación/actualización y actor en patient_data
SET @schema := DATABASE();

-- created_at
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND column_name = 'created_at'
        ),
        'SELECT "patient_data.created_at ya existe"',
        'ALTER TABLE patient_data ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- updated_at
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND column_name = 'updated_at'
        ),
        'SELECT "patient_data.updated_at ya existe"',
        'ALTER TABLE patient_data ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- created_by_type
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND column_name = 'created_by_type'
        ),
        'SELECT "patient_data.created_by_type ya existe"',
        'ALTER TABLE patient_data ADD COLUMN created_by_type VARCHAR(20) NULL AFTER updated_at'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- created_by_identifier
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND column_name = 'created_by_identifier'
        ),
        'SELECT "patient_data.created_by_identifier ya existe"',
        'ALTER TABLE patient_data ADD COLUMN created_by_identifier VARCHAR(191) NULL AFTER created_by_type'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- updated_by_type
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND column_name = 'updated_by_type'
        ),
        'SELECT "patient_data.updated_by_type ya existe"',
        'ALTER TABLE patient_data ADD COLUMN updated_by_type VARCHAR(20) NULL AFTER created_by_identifier'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- updated_by_identifier
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND column_name = 'updated_by_identifier'
        ),
        'SELECT "patient_data.updated_by_identifier ya existe"',
        'ALTER TABLE patient_data ADD COLUMN updated_by_identifier VARCHAR(191) NULL AFTER updated_by_type'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para auditoría de última actualización
SET @idx_name := 'idx_patient_data_updated_at';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND index_name = @idx_name
        ),
        'SELECT "idx_patient_data_updated_at ya existe"',
        'ALTER TABLE patient_data ADD INDEX `idx_patient_data_updated_at` (`updated_at`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
