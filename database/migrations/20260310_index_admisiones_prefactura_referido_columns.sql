-- Campos para sync_index_admisiones: referido de prefactura
SET @schema := DATABASE();

-- 1) referido_prefactura_por
SET @col_name := 'referido_prefactura_por';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND column_name = @col_name
        ),
        'SELECT "Column referido_prefactura_por ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD COLUMN `referido_prefactura_por` VARCHAR(150) NULL AFTER `afiliacion`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) especificar_referido_prefactura
SET @col_name := 'especificar_referido_prefactura';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND column_name = @col_name
        ),
        'SELECT "Column especificar_referido_prefactura ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD COLUMN `especificar_referido_prefactura` VARCHAR(255) NULL AFTER `referido_prefactura_por`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
