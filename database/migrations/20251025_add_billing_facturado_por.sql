-- Agrega el usuario que facturó en billing_main si aún no existe

SET @schema := DATABASE();
SET @column_name := 'facturado_por';

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'billing_main'
              AND column_name = @column_name
        ),
        'SELECT "Column facturado_por ya existe en billing_main"',
        'ALTER TABLE billing_main ADD COLUMN facturado_por INT NULL AFTER form_id;'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_name := 'idx_billing_main_facturado_por';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'billing_main'
              AND index_name = @idx_name
        ),
        'SELECT "Index idx_billing_main_facturado_por ya existe"',
        'ALTER TABLE billing_main ADD INDEX `idx_billing_main_facturado_por` (`facturado_por`);'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
