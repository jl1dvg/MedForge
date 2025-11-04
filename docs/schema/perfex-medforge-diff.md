# Diferencias de esquema entre Perfex CRM y MedForge

Este documento resume los cambios realizados para adaptar las tablas de ajustes y permisos de Perfex CRM al modelo unificado de MedForge. Sirve como guía para comprender qué estructuras se reemplazan, cómo se migran los datos y qué consideraciones hay que tener al ejecutar las migraciones.

## Ajustes de aplicación

| Concepto | Perfex (`tbloptions`) | MedForge (`app_settings`) |
| --- | --- | --- |
| Finalidad | Almacenar pares `name`/`value` sin clasificación ni metadatos adicionales. | Centralizar la configuración con categorías, tipo de dato y control de autoload. |
| Columnas relevantes | `id`, `name`, `value`, `autoload` (booleano), `active`. | `id`, `category`, `name`, `value`, `type`, `autoload`, `created_at`, `updated_at`. |
| Índices | Índice simple en `name`. | Índice único `uq_app_settings_name`, índices secundarios por `category` y `autoload`. |
| Problemas detectados | Valores duplicados o huérfanos; difícil clasificar opciones; incompatibilidad con las nuevas pantallas de ajustes. | Resuelve duplicados mediante índice único y permite segmentar opciones por contexto funcional. |

### Estrategia de migración

1. Crear la tabla `app_settings` si no existe y asegurar que las columnas `category`, `type` y `autoload` están disponibles.
2. Copiar los datos desde `tbloptions`, asignando categorías heurísticas:
   - Información corporativa → `general`.
   - Logotipos y PDF → `branding`.
   - SMTP y firmas → `email`.
   - Localización (idioma, zona horaria, formato) → `localization`.
   - Flags de notificaciones → `notifications`.
   - Todo lo no clasificado se marca como `legacy` para revisión manual posterior.
3. El índice único evita duplicados y, cuando una entrada ya existe, la migración solo actualiza `value`, `autoload` y la categoría (manteniendo la categoría previa si no era `legacy/general`).

## Permisos y roles

| Concepto | Perfex | MedForge |
| --- | --- | --- |
| Catálogo de permisos | `tblpermissions` y `tblstaffpermissions` almacenan permisos atómicos con flags `can_view`, `can_edit`, etc. | Los permisos se modelan como cadenas simbólicas (`dashboard.view`, `pacientes.manage`, etc.) y se agrupan por rol. |
| Asignación a usuarios | `tblstaffpermissions` (por usuario) y `tblrolepermissions` + `tblroles` (por rol). | Columna `users.permisos` con JSON y referencia `users.role_id` a la tabla `roles`. |
| Superusuario | Flag `tblstaff.admin`. | Permiso especial `superuser` almacenado en la lista JSON. |
| Compatibilidad | Estructura orientada a módulos de Perfex (clientes, proyectos, etc.). | Permisos específicos para MedForge (pacientes, cirugías, insumos, administración). |

### Estrategia de migración

1. Crear la tabla `roles` y agregar las columnas/relaciones necesarias en `users` (`permisos`, `role_id`, FK e índice auxiliar).
2. Construir un mapa de equivalencias entre los `shortname` de Perfex y los permisos actuales:
   - `customers`, `leads` → `pacientes.view` y `pacientes.manage`.
   - `projects`, `tasks` → `cirugias.manage`.
   - `items`, `expenses` → `insumos.manage`.
   - `staff` → `admin.usuarios`; `roles` → `admin.roles`; `settings` → `settings.manage`; `reports` → `reportes.view`.
   - Permisos sin correspondencia directa se etiquetan como `legacy.<shortname>` para que el equipo los revise manualmente.
3. Agregar permisos agregados por usuario a partir de `tblstaffpermissions`, agrupándolos por correo electrónico (clave compartida entre `tblstaff` y `users`).
4. Migrar roles existentes desde `tblroles`, preservando el identificador original y adjuntando el conjunto de permisos traducidos.
5. Copiar la asignación de roles (`tblstaff.role*`) al nuevo campo `users.role_id` y promover a superusuario (`["superuser"]`) a quienes tenían `admin = 1`.
6. Asegurar que los usuarios sin permisos explícitos queden con `[]` para evitar valores `NULL`.

## Scripts asociados

El archivo [`database/migrations/20240703_settings_permissions_alignment.sql`](../../database/migrations/20240703_settings_permissions_alignment.sql) implementa la lógica descrita anteriormente: verifica la existencia de tablas, crea los índices necesarios y migra los datos de forma idempotente.

## Consideraciones adicionales

- Los permisos etiquetados como `legacy.*` deben revisarse después de la migración para decidir si se sustituyen por permisos nativos de MedForge o se eliminan.
- Si la instalación original de Perfex tenía personalizaciones no estándar (campos adicionales en `tblstaff` o `tbloptions`), la migración conservará los valores en `legacy`, pero será necesario ajustar manualmente la categorización.
- Las migraciones se diseñaron para ejecutarse múltiples veces sin efectos secundarios; utilizan `ON DUPLICATE KEY` y verificaciones en `information_schema` para evitar errores cuando la estructura ya está actualizada.
