# Patrones SQL optimizados

Este documento resume las prácticas adoptadas para mantener las consultas compatibles con índices existentes y evitar regresiones de rendimiento.

## Búsquedas case-insensitive

* Evitar `LOWER()`/`UPPER()` sobre columnas indexadas.
* Utilizar colaciones `utf8mb4_unicode_ci` directamente en la cláusula `WHERE` (`columna COLLATE utf8mb4_unicode_ci = ? / IN (...)`).
* Normalizar los parámetros en PHP (ej. `trim()`) antes de construir las consultas.

## Filtros por rango de fechas

* Reemplazar filtros basados en `MONTH()`/`YEAR()` o `DATE_FORMAT()` por rangos `[inicio, fin)` calculados en PHP.
* Acompañar los rangos con índices en base de datos, por ejemplo:

  ```sql
  CREATE INDEX idx_protocolo_fecha_inicio ON protocolo_data (fecha_inicio);
  CREATE INDEX idx_consulta_fecha ON consulta_data (fecha);
  ```

## Limpieza de datos en la aplicación

* Validar y recortar entradas antes de usarlas en `LIKE`.
* Evitar `TRIM(columna)` en SQL cuando sea posible; preferir limpiar los datos en la aplicación o mediante columnas derivadas.

## Verificación y planes de ejecución

* Ejecutar los scripts de `tools/tests` para asegurar que los patrones prohibidos no reaparezcan.
* Capturar y adjuntar planes de ejecución actualizados ejecutando los SQL contenidos en `tools/sql/explain/billing_and_exports.sql`.

Mantener esta guía actualizada ante nuevos patrones o ajustes en la base de datos.
