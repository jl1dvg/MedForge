-- Campos de trazabilidad para agendamiento Sigcenter en solicitud_procedimiento

SET @tbl := 'solicitud_procedimiento';

-- sigcenter_agenda_id
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='sigcenter_agenda_id'),
    'SELECT "sigcenter_agenda_id ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN sigcenter_agenda_id VARCHAR(50) NULL AFTER incision'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigcenter_fecha_inicio
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='sigcenter_fecha_inicio'),
    'SELECT "sigcenter_fecha_inicio ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN sigcenter_fecha_inicio DATETIME NULL AFTER sigcenter_agenda_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigcenter_trabajador_id
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='sigcenter_trabajador_id'),
    'SELECT "sigcenter_trabajador_id ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN sigcenter_trabajador_id VARCHAR(50) NULL AFTER sigcenter_fecha_inicio'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigcenter_procedimiento_id
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='sigcenter_procedimiento_id'),
    'SELECT "sigcenter_procedimiento_id ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN sigcenter_procedimiento_id INT NULL AFTER sigcenter_trabajador_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigcenter_payload
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='sigcenter_payload'),
    'SELECT "sigcenter_payload ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN sigcenter_payload LONGTEXT NULL AFTER sigcenter_procedimiento_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sigcenter_response
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name=@tbl AND table_schema=DATABASE() AND column_name='sigcenter_response'),
    'SELECT "sigcenter_response ya existe"',
    'ALTER TABLE solicitud_procedimiento ADD COLUMN sigcenter_response LONGTEXT NULL AFTER sigcenter_payload'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
