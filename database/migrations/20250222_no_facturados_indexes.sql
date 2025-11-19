-- Índices para acelerar filtros de procedimientos no facturados
SET @schema := DATABASE();

-- Índice por fecha y afiliación en procedimiento_proyectado
SET @idx_name := 'idx_no_facturados_fecha_afiliacion';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND index_name = @idx_name
        ),
        'SELECT "idx_no_facturados_fecha_afiliacion ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD INDEX `idx_no_facturados_fecha_afiliacion` (`fecha`, `afiliacion`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice por estado de revisión y fecha en protocolo_data
SET @idx_name := 'idx_no_facturados_estado_fecha';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'protocolo_data'
              AND index_name = @idx_name
        ),
        'SELECT "idx_no_facturados_estado_fecha ya existe"',
        'ALTER TABLE protocolo_data ADD INDEX `idx_no_facturados_estado_fecha` (`status`, `fecha_inicio`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice de afiliación en patient_data
SET @idx_name := 'idx_no_facturados_afiliacion';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'patient_data'
              AND index_name = @idx_name
        ),
        'SELECT "idx_no_facturados_afiliacion ya existe"',
        'ALTER TABLE patient_data ADD INDEX `idx_no_facturados_afiliacion` (`afiliacion`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice auxiliar para búsquedas por precio estimado cuando se habilite
SET @idx_name := 'idx_no_facturados_valor';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado'
              AND index_name = @idx_name
        ),
        'SELECT "idx_no_facturados_valor ya existe"',
        'ALTER TABLE procedimiento_proyectado ADD INDEX `idx_no_facturados_valor` (`hora`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
