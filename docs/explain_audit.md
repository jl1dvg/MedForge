# Auditoría de consultas con `EXPLAIN`

Este repositorio ahora incluye el script `tools/explain_audit.php` para documentar y automatizar la revisión de planes de ejecución de las consultas más costosas de los módulos señalados (protocolos, dashboards, CRM e insumos).

## Requisitos previos

1. **Acceso a la base de datos MySQL** con los mismos credenciales que utiliza la aplicación.
2. Definir las variables de entorno antes de ejecutar el script si la instancia difiere de los valores por defecto:

   ```bash
   export DB_HOST="<host>"
   export DB_NAME="<database>"
   export DB_USER="<user>"
   export DB_PASSWORD="<password>"
   export DB_CHARSET="utf8mb4"
   export DB_TIMEZONE="-05:00"
   ```

3. PHP 8.1 o superior con la extensión `pdo_mysql` habilitada.

## Uso básico

Listar los escenarios disponibles:

```bash
/usr/bin/php8.3-cli tools/explain_audit.php --list
```

Ejecutar todos los escenarios (por defecto):

```bash
/usr/bin/php8.3-cli tools/explain_audit.php
```

Ejecutar un subconjunto de escenarios separados por coma:

```bash
/usr/bin/php8.3-cli tools/explain_audit.php --scenario protocolos_detalle,crm_leads_list
```

Obtener la salida en formato JSON para analizarla con otras herramientas:

```bash
/usr/bin/php8.3-cli tools/explain_audit.php --json > explain.json
```

## Escenarios incluidos

| Escenario | Descripción | Tablas principales | Índices recomendados |
|-----------|-------------|--------------------|----------------------|
| `protocolos_detalle` | Recuperación del protocolo completo (`ProtocoloModel::obtenerProtocolo`). | `patient_data`, `protocolo_data`, `procedimiento_proyectado` | Índices compuestos en `(form_id)`, `(hc_number, form_id)` y `(hc_number, fecha_inicio)`.
| `dashboard_diagnosticos` | Agregación de diagnósticos masivos (`DashboardModel::getDiagnosticosFrecuentes`). | `consulta_data` | Índices en `(hc_number, fecha)` y considerar partición/paginación.
| `crm_leads_list` | Listado de leads con filtros opcionales. | `crm_leads`, `users`, `crm_customers` | Índices compuestos en `(status, updated_at)`, `(assigned_to, updated_at)` y soporte para búsquedas `LIKE`.
| `crm_tasks_list` | Listado de tareas ordenado por `due_date` y `updated_at`. | `crm_tasks`, `users`, `crm_projects` | Índices compuestos en `(status, due_date, updated_at)` y `(project_id, due_date)`.
| `procedimientos_por_fecha` | Detección de visitas duplicadas en la proyección de procedimientos. | `visitas` | Índice en `(hc_number, fecha_visita)`.
| `billing_form_lookup` | Validación de facturas existentes por `form_id`. | `billing_main` | Índice en `(form_id)`.
| `billing_facturas_mes` | Listado de facturas por rango de fechas cruzando proyección y protocolo. | `billing_main`, `protocolo_data`, `procedimiento_proyectado` | Índices en `(form_id)` y compuestos para fechas (`protocolo_data(fecha_inicio, status)` y `procedimiento_proyectado(fecha, estado_agenda)`).
| `guardar_proyeccion_form_id` | Búsqueda de proyecciones existentes antes de aplicar `ON DUPLICATE KEY`. | `procedimiento_proyectado` | Índice único en `(form_id)`.
| `guardar_proyeccion_horario` | Selección de la primera hora disponible por paciente y fecha. | `procedimiento_proyectado` | Índice compuesto en `(hc_number, fecha)` para la agregación.

Cada escenario utiliza parámetros representativos basados en los flujos reales. Es posible ajustar estos valores editando el arreglo `params` dentro del script para reproducir casos reales detectados con logs.

## Auditoría rápida de índices

Para corroborar los índices disponibles sin revisar manualmente los `CREATE TABLE`, se agregó el script `tools/index_audit.php`. El comando consulta `INFORMATION_SCHEMA.STATISTICS` y resume qué columnas participan en cada índice.

```bash
php tools/index_audit.php
```

La salida enumera los índices de `procedimiento_proyectado`, `protocolo_data` y `billing_main`, e identifica si cada columna crítica (`form_id`, `hc_number`, `fecha`, `estado_agenda`, `fecha_inicio`, `status`) cuenta con cobertura única o compuesta.

## Pasos siguientes sugeridos

1. Ejecutar el script contra la base de datos productiva o un respaldo actualizado.
2. Analizar el resultado de `EXPLAIN` para identificar accesos `ALL` o `index` que puedan optimizarse con los índices sugeridos.
3. Registrar los hallazgos y priorizar las migraciones de índices correspondientes.
4. Repetir la medición después de aplicar los cambios para validar la mejora en los planes de ejecución.

