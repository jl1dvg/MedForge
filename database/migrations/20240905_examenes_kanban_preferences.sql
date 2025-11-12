-- Preferencias iniciales para el tablero de exámenes
INSERT INTO app_settings (category, name, value, type, autoload) VALUES
    ('examenes', 'examenes_kanban_sort', 'creado_desc', 'text', 0),
    ('examenes', 'examenes_kanban_column_limit', '0', 'number', 0)
ON DUPLICATE KEY UPDATE
    value = value;
