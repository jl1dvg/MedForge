-- Campos para preselección de derivación en solicitud_procedimiento

SET @tbl := 'solicitud_procedimiento';

-- derivacion_codigo
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='derivacion_codigo'),
    'SELECT "derivacion_codigo ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN derivacion_codigo VARCHAR(80) NULL AFTER sigcenter_response'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- derivacion_pedido_id
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='derivacion_pedido_id'),
    'SELECT "derivacion_pedido_id ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN derivacion_pedido_id VARCHAR(50) NULL AFTER derivacion_codigo'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- derivacion_lateralidad
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='derivacion_lateralidad'),
    'SELECT "derivacion_lateralidad ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN derivacion_lateralidad VARCHAR(32) NULL AFTER derivacion_pedido_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- derivacion_fecha_vigencia_sel
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='derivacion_fecha_vigencia_sel'),
    'SELECT "derivacion_fecha_vigencia_sel ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN derivacion_fecha_vigencia_sel VARCHAR(20) NULL AFTER derivacion_lateralidad'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
