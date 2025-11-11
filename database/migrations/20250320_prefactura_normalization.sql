-- Normaliza procedimientos y diagnósticos de prefactura y genera vistas analíticas

SET @schema := DATABASE();

SET @prefactura_id_column_type := (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = 'prefactura_paciente'
      AND COLUMN_NAME = 'id'
    LIMIT 1
);
SET @prefactura_id_column_type := IFNULL(@prefactura_id_column_type, 'BIGINT UNSIGNED');

SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS prefactura_detalle_procedimientos (\n',
    '    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n',
    '    prefactura_id ', @prefactura_id_column_type, ' NOT NULL,\n',
    '    posicion INT UNSIGNED NOT NULL DEFAULT 0,\n',
    '    external_id VARCHAR(64) NULL,\n',
    '    proc_interno VARCHAR(255) NULL,\n',
    '    codigo VARCHAR(64) NULL,\n',
    '    descripcion VARCHAR(255) NULL,\n',
    '    lateralidad VARCHAR(32) NULL,\n',
    '    observaciones TEXT NULL,\n',
    '    precio_base DECIMAL(12,2) NULL,\n',
    '    precio_tarifado DECIMAL(12,2) NULL,\n',
    '    raw JSON NULL,\n',
    '    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n',
    '    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n',
    '    UNIQUE KEY uniq_prefactura_proc_pos (prefactura_id, posicion),\n',
    '    INDEX idx_prefactura_proc_prefactura (prefactura_id),\n',
    '    CONSTRAINT fk_prefactura_proc_prefactura FOREIGN KEY (prefactura_id) REFERENCES prefactura_paciente(id) ON DELETE CASCADE\n',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS prefactura_detalle_diagnosticos (\n',
    '    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n',
    '    prefactura_id ', @prefactura_id_column_type, ' NOT NULL,\n',
    '    posicion INT UNSIGNED NOT NULL DEFAULT 0,\n',
    '    diagnostico_codigo VARCHAR(64) NULL,\n',
    '    descripcion VARCHAR(255) NULL,\n',
    '    lateralidad VARCHAR(32) NULL,\n',
    '    evidencia TEXT NULL,\n',
    '    observaciones TEXT NULL,\n',
    '    raw JSON NULL,\n',
    '    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n',
    '    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n',
    '    UNIQUE KEY uniq_prefactura_diag_pos (prefactura_id, posicion),\n',
    '    INDEX idx_prefactura_diag_prefactura (prefactura_id),\n',
    '    CONSTRAINT fk_prefactura_diag_prefactura FOREIGN KEY (prefactura_id) REFERENCES prefactura_paciente(id) ON DELETE CASCADE\n',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := CONCAT(
    'CREATE TABLE IF NOT EXISTS prefactura_payload_audit (\n',
    '    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n',
    '    prefactura_id ', @prefactura_id_column_type, ' NULL,\n',
    '    hc_number VARCHAR(64) NULL,\n',
    '    form_id VARCHAR(64) NULL,\n',
    '    source VARCHAR(64) NOT NULL,\n',
    '    payload_hash CHAR(64) NOT NULL,\n',
    '    payload_json JSON NOT NULL,\n',
    '    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n',
    '    INDEX idx_prefactura_payload_prefactura (prefactura_id),\n',
    '    UNIQUE KEY uniq_prefactura_payload (prefactura_id, payload_hash),\n',
    '    CONSTRAINT fk_prefactura_payload_prefactura FOREIGN KEY (prefactura_id) REFERENCES prefactura_paciente(id) ON DELETE SET NULL\n',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice compuesto para acelerar los cálculos de transiciones
SET @idx_name := 'idx_estado_form_estado_fecha';
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema
              AND table_name = 'procedimiento_proyectado_estado'
              AND index_name = @idx_name
        ),
        'SELECT "idx_estado_form_estado_fecha ya existe"',
        'ALTER TABLE procedimiento_proyectado_estado ADD INDEX `idx_estado_form_estado_fecha` (`form_id`, `estado`, `fecha_hora_cambio`);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP VIEW IF EXISTS procedimiento_estado_transiciones;
CREATE ALGORITHM=MERGE VIEW procedimiento_estado_transiciones AS
SELECT
    h1.form_id,
    h1.estado AS estado_inicio,
    h1.fecha_hora_cambio AS fecha_inicio,
    nxt.next_fecha AS fecha_fin,
    TIMESTAMPDIFF(MINUTE, h1.fecha_hora_cambio, nxt.next_fecha) AS duracion_minutos,
    (
        SELECT COUNT(*)
        FROM procedimiento_proyectado_estado h4
        WHERE h4.form_id = h1.form_id
          AND h4.fecha_hora_cambio <= h1.fecha_hora_cambio
    ) AS orden_estado
FROM procedimiento_proyectado_estado h1
LEFT JOIN (
    SELECT
        h2.form_id,
        h2.fecha_hora_cambio,
        MIN(h3.fecha_hora_cambio) AS next_fecha
    FROM procedimiento_proyectado_estado h2
    LEFT JOIN procedimiento_proyectado_estado h3
        ON h3.form_id = h2.form_id
       AND h3.fecha_hora_cambio > h2.fecha_hora_cambio
    GROUP BY h2.form_id, h2.fecha_hora_cambio
) AS nxt
    ON nxt.form_id = h1.form_id
   AND nxt.fecha_hora_cambio = h1.fecha_hora_cambio;

DROP TEMPORARY TABLE IF EXISTS tmp_prefactura_indices;
CREATE TEMPORARY TABLE tmp_prefactura_indices (
    idx INT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=MEMORY;

INSERT INTO tmp_prefactura_indices (idx)
SELECT
    ones.n
    + tens.n * 10
    + hundreds.n * 100
    + thousands.n * 1000 AS idx
FROM
    (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS ones
    CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS tens
    CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS hundreds
    CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS thousands
ORDER BY idx;

INSERT INTO prefactura_detalle_procedimientos (
    prefactura_id,
    posicion,
    external_id,
    proc_interno,
    codigo,
    descripcion,
    lateralidad,
    observaciones,
    precio_base,
    precio_tarifado,
    raw
)
SELECT
    pp.id AS prefactura_id,
    idx.idx AS posicion,
    JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].id'))),
    JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].procInterno'))),
    JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].procCodigo'))),
    COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].procDetalle'))),
        JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].procedimiento')))
    ),
    JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].ojoId'))),
    JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].observaciones'))),
    NULLIF(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].precioBase'))), ',', '.'), ''),
    NULLIF(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, '].procPrecio'))), ',', '.'), ''),
    JSON_EXTRACT(pp.procedimientos, CONCAT('$[', idx.idx, ']'))
FROM prefactura_paciente pp
INNER JOIN tmp_prefactura_indices idx ON idx.idx < COALESCE(JSON_LENGTH(pp.procedimientos), 0)
WHERE pp.procedimientos IS NOT NULL
  AND pp.procedimientos != ''
  AND JSON_VALID(pp.procedimientos)
ON DUPLICATE KEY UPDATE
    proc_interno = VALUES(proc_interno),
    codigo = VALUES(codigo),
    descripcion = VALUES(descripcion),
    lateralidad = VALUES(lateralidad),
    observaciones = VALUES(observaciones),
    precio_base = VALUES(precio_base),
    precio_tarifado = VALUES(precio_tarifado),
    raw = VALUES(raw);

-- Backfill de diagnósticos existentes almacenados en JSON
INSERT INTO prefactura_detalle_diagnosticos (
    prefactura_id,
    posicion,
    diagnostico_codigo,
    descripcion,
    lateralidad,
    evidencia,
    observaciones,
    raw
)
SELECT
    pp.id AS prefactura_id,
    idx.idx AS posicion,
    JSON_UNQUOTE(JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, '].idDiagnostico'))),
    COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, '].diagnostico'))),
        JSON_UNQUOTE(JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, '].descripcion')))
    ),
    JSON_UNQUOTE(JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, '].ojo'))),
    JSON_UNQUOTE(JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, '].evidencia'))),
    JSON_UNQUOTE(JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, '].observaciones'))),
    JSON_EXTRACT(pp.diagnosticos, CONCAT('$[', idx.idx, ']'))
FROM prefactura_paciente pp
INNER JOIN tmp_prefactura_indices idx ON idx.idx < COALESCE(JSON_LENGTH(pp.diagnosticos), 0)
WHERE pp.diagnosticos IS NOT NULL
  AND pp.diagnosticos != ''
  AND JSON_VALID(pp.diagnosticos)
ON DUPLICATE KEY UPDATE
    diagnostico_codigo = VALUES(diagnostico_codigo),
    descripcion = VALUES(descripcion),
    lateralidad = VALUES(lateralidad),
    evidencia = VALUES(evidencia),
    observaciones = VALUES(observaciones),
    raw = VALUES(raw);

DROP TEMPORARY TABLE IF EXISTS tmp_prefactura_indices;
