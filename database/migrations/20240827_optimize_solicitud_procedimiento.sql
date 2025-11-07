-- Auditoría y optimización de índices para solicitud_procedimiento.

-- 1. Auditar los índices existentes.
SHOW INDEXES FROM solicitud_procedimiento;

-- 2. Crear índice para acelerar joins por form_id.
SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'solicitud_procedimiento'
              AND index_name = 'idx_solicitud_procedimiento_form_id'
        ),
        'SELECT "idx_solicitud_procedimiento_form_id ya existe";',
        'ALTER TABLE solicitud_procedimiento ADD INDEX idx_solicitud_procedimiento_form_id (form_id);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Crear índice compuesto para órdenes por hc_number y created_at DESC.
--    Primero verifica si ya existe un índice equivalente.
SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'solicitud_procedimiento'
              AND index_name = 'idx_solicitud_procedimiento_hc_created_desc'
        ),
        'SELECT "idx_solicitud_procedimiento_hc_created_desc ya existe";',
        'ALTER TABLE solicitud_procedimiento ADD INDEX idx_solicitud_procedimiento_hc_created_desc (hc_number, created_at DESC);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Crear índice por fecha para filtros directos en dashboards.
SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'solicitud_procedimiento'
              AND index_name = 'idx_solicitud_procedimiento_fecha'
        ),
        'SELECT "idx_solicitud_procedimiento_fecha ya existe";',
        'ALTER TABLE solicitud_procedimiento ADD INDEX idx_solicitud_procedimiento_fecha (fecha);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Mostrar los índices resultantes tras las modificaciones.
SHOW INDEXES FROM solicitud_procedimiento;

-- 6. Ejecutar EXPLAIN para validar el uso de índices en consultas críticas.
EXPLAIN FORMAT=JSON
SELECT procedimiento, created_at, tipo, form_id
FROM solicitud_procedimiento
WHERE hc_number = 'HC_PLACEHOLDER'
  AND procedimiento != ''
  AND procedimiento != 'SELECCIONE'
ORDER BY created_at DESC
LIMIT 50;

EXPLAIN FORMAT=JSON
SELECT sp.id, sp.fecha, sp.procedimiento, p.fname, p.lname, p.hc_number
FROM solicitud_procedimiento sp
JOIN patient_data p
  ON sp.hc_number COLLATE utf8mb4_unicode_ci = p.hc_number COLLATE utf8mb4_unicode_ci
WHERE sp.procedimiento IS NOT NULL
  AND sp.procedimiento != ''
  AND sp.procedimiento != 'SELECCIONE'
ORDER BY sp.fecha DESC
LIMIT 5;
