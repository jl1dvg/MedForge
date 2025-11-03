-- Índices para optimizar consultas de pacientes y coberturas.
SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'prefactura_paciente'
              AND index_name = 'idx_prefactura_paciente_hc_fecha'
        ),
        'SELECT "idx_prefactura_paciente_hc_fecha ya existe";',
        'ALTER TABLE prefactura_paciente ADD INDEX idx_prefactura_paciente_hc_fecha (hc_number, fecha_vigencia);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'consulta_data'
              AND index_name = 'idx_consulta_data_hc_fecha'
        ),
        'SELECT "idx_consulta_data_hc_fecha ya existe";',
        'ALTER TABLE consulta_data ADD INDEX idx_consulta_data_hc_fecha (hc_number, fecha);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query := (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'solicitud_procedimiento'
              AND index_name = 'idx_solicitud_procedimiento_hc_fecha'
        ),
        'SELECT "idx_solicitud_procedimiento_hc_fecha ya existe";',
        'ALTER TABLE solicitud_procedimiento ADD INDEX idx_solicitud_procedimiento_hc_fecha (hc_number, created_at);'
    )
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
