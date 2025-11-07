-- Optimiza índices para procedimientos proyectados y facturación

SET @schema := DATABASE();

-- Asegurar índice único en procedimiento_proyectado(form_id)
SET @idx_name := 'uniq_procedimiento_proyectado_form_id';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND index_name = @idx_name
        ),
        'SELECT "Index uniq_procedimiento_proyectado_form_id ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD UNIQUE KEY `uniq_procedimiento_proyectado_form_id` (`form_id`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para búsquedas por paciente y fecha
SET @idx_name := 'idx_procedimiento_proyectado_hc_fecha';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND index_name = @idx_name
        ),
        'SELECT "Index idx_procedimiento_proyectado_hc_fecha ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD INDEX `idx_procedimiento_proyectado_hc_fecha` (`hc_number`, `fecha`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para filtros por fecha y estado de agenda
SET @idx_name := 'idx_procedimiento_proyectado_fecha_estado';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND index_name = @idx_name
        ),
        'SELECT "Index idx_procedimiento_proyectado_fecha_estado ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD INDEX `idx_procedimiento_proyectado_fecha_estado` (`fecha`, `estado_agenda`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para protocolo_data(form_id)
SET @idx_name := 'idx_protocolo_data_form_id';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'protocolo_data'
              AND index_name = @idx_name
        ),
        'SELECT "Index idx_protocolo_data_form_id ya existe"',
        'ALTER TABLE protocolo_data ADD INDEX `idx_protocolo_data_form_id` (`form_id`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice compuesto para protocolo_data(fecha_inicio, status)
SET @idx_name := 'idx_protocolo_data_fecha_status';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'protocolo_data'
              AND index_name = @idx_name
        ),
        'SELECT "Index idx_protocolo_data_fecha_status ya existe"',
        'ALTER TABLE protocolo_data ADD INDEX `idx_protocolo_data_fecha_status` (`fecha_inicio`, `status`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para billing_main(form_id)
SET @idx_name := 'idx_billing_main_form_id';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'billing_main'
              AND index_name = @idx_name
        ),
        'SELECT "Index idx_billing_main_form_id ya existe"',
        'ALTER TABLE billing_main ADD INDEX `idx_billing_main_form_id` (`form_id`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
