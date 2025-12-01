-- Añade campos específicos de lente/insumo para estadísticas y planificación
-- Campos: lente_id, lente_nombre, lente_poder, lente_observacion, incision

SET @tbl := 'solicitud_procedimiento';

-- lente_id
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='lente_id'),
    'SELECT "lente_id ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN lente_id VARCHAR(50) NULL AFTER producto'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lente_nombre
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='lente_nombre'),
    'SELECT "lente_nombre ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN lente_nombre VARCHAR(255) NULL AFTER lente_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lente_poder
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='lente_poder'),
    'SELECT "lente_poder ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN lente_poder VARCHAR(50) NULL AFTER lente_nombre'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lente_observacion
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='lente_observacion'),
    'SELECT "lente_observacion ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN lente_observacion TEXT NULL AFTER lente_poder'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- incision
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='incision'),
    'SELECT "incision ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN incision VARCHAR(50) NULL AFTER lente_observacion'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
