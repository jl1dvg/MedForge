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

> **Nota sobre hosting compartido:** Algunos proveedores exponen el binario `php` como CGI/FPM y, al ejecutar el script,
> agregan automáticamente la cabecera `Content-type: text/html`. En esos entornos puedes usar `php -q tools/explain_audit.php`
> o `php-cli tools/explain_audit.php` para forzar la salida en modo consola. El script también redirige los mensajes de
> error a `php://stderr`, por lo que incluso bajo CGI los errores de conexión o sintaxis se mostrarán correctamente.

## Uso básico

Listar los escenarios disponibles:

```bash
php tools/explain_audit.php --list
```

Ejecutar todos los escenarios (por defecto):

```bash
php tools/explain_audit.php
```

Ejecutar un subconjunto de escenarios separados por coma:

```bash
php tools/explain_audit.php --scenario protocolos_detalle,crm_leads_list
```

Obtener la salida en formato JSON para analizarla con otras herramientas:

```bash
php tools/explain_audit.php --json > explain.json
```

## Escenarios incluidos

| Escenario | Descripción | Tablas principales | Índices recomendados |
|-----------|-------------|--------------------|----------------------|
| `protocolos_detalle` | Recuperación del protocolo completo (`ProtocoloModel::obtenerProtocolo`). | `patient_data`, `protocolo_data`, `procedimiento_proyectado` | Índices compuestos en `(form_id)`, `(hc_number, form_id)` y `(hc_number, fecha_inicio)`.
| `dashboard_diagnosticos` | Agregación de diagnósticos masivos (`DashboardModel::getDiagnosticosFrecuentes`). | `consulta_data` | Índices en `(hc_number, fecha)` y considerar partición/paginación.
| `crm_leads_list` | Listado de leads con filtros opcionales. | `crm_leads`, `users`, `crm_customers` | Índices compuestos en `(status, updated_at)`, `(assigned_to, updated_at)` y soporte para búsquedas `LIKE`.
| `crm_tasks_list` | Listado de tareas ordenado por `due_date` y `updated_at`. | `crm_tasks`, `users`, `crm_projects` | Índices compuestos en `(status, due_date, updated_at)` y `(project_id, due_date)`.
| `procedimientos_por_fecha` | Detección de visitas duplicadas en la proyección de procedimientos. | `visitas` | Índice en `(hc_number, fecha_visita)`.

Cada escenario utiliza parámetros representativos basados en los flujos reales. Es posible ajustar estos valores editando el arreglo `params` dentro del script para reproducir casos reales detectados con logs.

## Pasos siguientes sugeridos

1. Ejecutar el script contra la base de datos productiva o un respaldo actualizado.
2. Analizar el resultado de `EXPLAIN` para identificar accesos `ALL` o `index` que puedan optimizarse con los índices sugeridos.
3. Registrar los hallazgos y priorizar las migraciones de índices correspondientes.
4. Repetir la medición después de aplicar los cambios para validar la mejora en los planes de ejecución.

