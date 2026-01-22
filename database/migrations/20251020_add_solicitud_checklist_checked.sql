ALTER TABLE solicitud_checklist
    ADD COLUMN checked TINYINT(1) NOT NULL DEFAULT 0 AFTER etapa_slug;

UPDATE solicitud_checklist
SET checked = IF(completado_at IS NULL, 0, 1);
