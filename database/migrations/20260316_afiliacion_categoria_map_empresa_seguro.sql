ALTER TABLE afiliacion_categoria_map
    ADD COLUMN empresa_seguro VARCHAR(255) NULL AFTER categoria;

UPDATE afiliacion_categoria_map
SET empresa_seguro = CASE
    WHEN TRIM(COALESCE(empresa_seguro, '')) <> '' THEN TRIM(empresa_seguro)
    WHEN afiliacion_norm = '' THEN 'Sin convenio'
    WHEN afiliacion_norm REGEXP '(^|_)iess($|_)|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|hijos_dependientes|conyuge|pensionista' THEN 'IESS'
    WHEN afiliacion_norm LIKE 'issfa%' THEN 'ISSFA'
    WHEN afiliacion_norm LIKE 'isspol%' THEN 'ISSPOL'
    WHEN afiliacion_norm LIKE 'salud%' THEN 'SALUD'
    WHEN afiliacion_norm LIKE 'plan_vital%' THEN 'PLAN VITAL'
    WHEN afiliacion_norm LIKE 'sweaden%' THEN 'SWEADEN CIA SEG'
    WHEN afiliacion_norm LIKE 'salus%' THEN 'SALUS'
    WHEN afiliacion_norm LIKE 'msp%' OR afiliacion_norm LIKE '%ministerio_salud%' THEN 'MSP'
    ELSE COALESCE(NULLIF(TRIM(afiliacion_raw), ''), 'Sin convenio')
END;
