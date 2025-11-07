-- Ajustes para habilitar la sección de Inteligencia Artificial en MedForge
INSERT INTO app_settings (category, name, value, type, autoload) VALUES
    ('ai', 'ai_provider', 'openai', 'text', 0),
    ('ai', 'ai_openai_api_key', '', 'password', 0),
    ('ai', 'ai_openai_endpoint', 'https://api.openai.com/v1/responses', 'text', 0),
    ('ai', 'ai_openai_model', 'gpt-4o-mini', 'text', 0),
    ('ai', 'ai_openai_max_output_tokens', '400', 'number', 0),
    ('ai', 'ai_openai_organization', '', 'text', 0),
    ('ai', 'ai_enable_consultas_enfermedad', '1', 'checkbox', 0),
    ('ai', 'ai_enable_consultas_plan', '1', 'checkbox', 0)
ON DUPLICATE KEY UPDATE
    category = VALUES(category),
    type = VALUES(type),
    autoload = VALUES(autoload);
