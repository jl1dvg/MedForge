-- Consultas optimizadas: ejecutar `EXPLAIN` para validar uso de índices.

EXPLAIN SELECT
    pr.form_id,
    pr.hc_number,
    pd.fecha_inicio AS fecha,
    pd.status,
    pd.membrete,
    pa.afiliacion
FROM protocolo_data pd
JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
JOIN patient_data pa ON pa.hc_number = pd.hc_number
WHERE NOT EXISTS (
    SELECT 1 FROM billing_main bm WHERE bm.form_id = pd.form_id
)
  AND pd.fecha_inicio >= '2024-12-01'
  AND pa.afiliacion COLLATE utf8mb4_unicode_ci IN (
      'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
      'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
      'seguro general por montepío', 'seguro general tiempo parcial', 'iess', 'hijos dependientes'
  );

EXPLAIN SELECT
    pd.form_id
FROM protocolo_data pd
JOIN patient_data pa ON pa.hc_number = pd.hc_number
WHERE pd.fecha_inicio >= '2025-01-01'
  AND pd.fecha_inicio < '2025-02-01'
  AND pa.afiliacion COLLATE utf8mb4_unicode_ci = 'seguro general'
  AND pd.status = 1;
