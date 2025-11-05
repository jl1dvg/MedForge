ALTER TABLE solicitud_procedimiento
    ADD COLUMN turno INT NULL AFTER estado;

CREATE INDEX idx_solicitud_procedimiento_turno ON solicitud_procedimiento (turno);
