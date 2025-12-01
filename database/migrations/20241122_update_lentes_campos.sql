-- Campos adicionales para lentes: rango dióptrico, paso, inicio de incremento, constantes A y tipo óptico

ALTER TABLE lentes_catalogo
    ADD COLUMN rango_desde DECIMAL(5,2) NULL AFTER poder,
    ADD COLUMN rango_hasta DECIMAL(5,2) NULL AFTER rango_desde,
    ADD COLUMN rango_paso DECIMAL(4,2) NULL AFTER rango_hasta,
    ADD COLUMN rango_inicio_incremento DECIMAL(5,2) NULL AFTER rango_paso,
    ADD COLUMN rango_texto VARCHAR(255) NULL AFTER rango_inicio_incremento,
    ADD COLUMN constante_a DECIMAL(6,2) NULL AFTER rango_texto,
    ADD COLUMN constante_a_us DECIMAL(6,2) NULL AFTER constante_a,
    ADD COLUMN tipo_optico ENUM('una_pieza','multipieza') NULL AFTER constante_a_us;
