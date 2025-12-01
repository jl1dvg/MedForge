-- Migrar datos desde detalles_json a las nuevas columnas planas y eliminar el JSON

-- 1) Copiar datos del primer detalle (o principal) si las columnas están vacías
UPDATE solicitud_procedimiento
SET
    lente_id = COALESCE(
        NULLIF(lente_id, ''),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].id_lente_intraocular')),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lente_id'))
    ),
    lente_nombre = COALESCE(
        NULLIF(lente_nombre, ''),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lente')),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lente_nombre'))
    ),
    lente_poder = COALESCE(
        NULLIF(lente_poder, ''),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].poder')),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lente_poder'))
    ),
    lente_observacion = COALESCE(
        NULLIF(lente_observacion, ''),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].observaciones')),
        JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lente_observacion'))
    ),
    ojo = CASE
        WHEN (ojo IS NULL OR TRIM(ojo) = '')
             AND JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lateralidad')) IS NOT NULL
             AND JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lateralidad')) <> ''
        THEN JSON_UNQUOTE(JSON_EXTRACT(detalles_json, '$[0].lateralidad'))
        ELSE ojo
    END
WHERE detalles_json IS NOT NULL;

-- 2) Eliminar la columna JSON (si existe)
SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'solicitud_procedimiento' AND column_name = 'detalles_json'),
    'ALTER TABLE solicitud_procedimiento DROP COLUMN detalles_json',
    'SELECT "detalles_json ya eliminado"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
