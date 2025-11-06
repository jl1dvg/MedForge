-- Ajusta el pipeline CRM para alinearlo con configuraciones dinámicas
ALTER TABLE crm_leads
    MODIFY status VARCHAR(191) NOT NULL DEFAULT 'Recibido';

UPDATE crm_leads
SET status = CASE status
    WHEN 'nuevo' THEN 'Recibido'
    WHEN 'en_proceso' THEN 'Contacto inicial'
    WHEN 'convertido' THEN 'Cerrado'
    WHEN 'perdido' THEN 'Perdido'
    ELSE status
END;

INSERT INTO app_settings (category, name, value, type, autoload) VALUES
    ('crm', 'crm_pipeline_stages', '["Recibido","Contacto inicial","Seguimiento","Docs completos","Autorizado","Agendado","Cerrado","Perdido"]', 'json', 0),
    ('crm', 'crm_kanban_sort', 'fecha_desc', 'text', 0),
    ('crm', 'crm_kanban_column_limit', '0', 'number', 0)
ON DUPLICATE KEY UPDATE
    value = value;
