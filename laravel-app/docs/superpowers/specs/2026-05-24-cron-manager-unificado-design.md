# CronManager Unificado — Design Spec
**Fecha:** 2026-05-24
**Estado:** Aprobado

## Objetivo

Unificar la gestión de todos los crons (legacy PHP + artisan Laravel scheduler) en una sola UI editable, con control completo de frecuencia, pausa/activación y ejecución manual — sin tocar código.

---

## Modelo de datos

### Tabla `cron_schedule`

```sql
CREATE TABLE cron_schedule (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(100) UNIQUE NOT NULL,
    name                VARCHAR(255) NOT NULL,
    command             VARCHAR(500) NOT NULL,
    type                ENUM('artisan', 'legacy') NOT NULL DEFAULT 'artisan',
    cron_expression     VARCHAR(100) NOT NULL,
    enabled             TINYINT(1) NOT NULL DEFAULT 1,
    run_in_background   TINYINT(1) NOT NULL DEFAULT 1,
    without_overlapping TINYINT(1) NOT NULL DEFAULT 1,
    description         TEXT NULL,
    last_run_at         TIMESTAMP NULL,
    last_status         VARCHAR(50) NULL,   -- ok | skipped | failed
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Filas iniciales (seeder):** los 20 comandos actualmente registrados en `routes/console.php`, más las tareas legacy del `CronRunner`.

---

## Arquitectura

### Registro dinámico del scheduler

`routes/console.php` reemplaza todos los `Schedule::command()` hardcodeados por un loop que lee de DB:

```php
$tasks = DB::table('cron_schedule')
    ->where('enabled', 1)
    ->where('type', 'artisan')
    ->get();

foreach ($tasks as $task) {
    $cmd = Schedule::command($task->command)->cron($task->cron_expression);
    if ($task->without_overlapping) $cmd->withoutOverlapping();
    if ($task->run_in_background) $cmd->runInBackground();
}
```

### CronRunner (legacy)

En lugar de tener las tareas hardcodeadas internamente, `CronRunner` consulta `cron_schedule` donde `type = 'legacy'` y `enabled = 1`. Actualiza `last_run_at` y `last_status` al terminar cada tarea.

### Flujo completo

```
schedule:run (cada minuto)
    └── routes/console.php
           └── lee cron_schedule (type=artisan, enabled=1)
                  └── registra Schedule::command() dinámicamente

cron.php (cada 5 min)
    └── CronRunner
           └── lee cron_schedule (type=legacy, enabled=1)
                  └── ejecuta + actualiza last_run_at / last_status

CronManager UI
    ├── GET  /cron-manager               → lista unificada
    ├── POST /cron-manager/{slug}/edit   → actualiza cron_expression + toggles
    ├── POST /cron-manager/{slug}/toggle → flip enabled
    └── POST /cron-manager/{slug}/run    → ejecución inmediata
```

---

## UI

### Vista principal `/cron-manager`

Tabla unificada con todas las tareas:

| Columna | Descripción |
|---|---|
| Nombre | Descripción legible |
| Tipo | Badge `artisan` / `legacy` |
| Frecuencia | Expresión cron actual |
| Último run | Timestamp relativo + estado (✅ / ⚠ / ❌) |
| Acciones | ✏ editar · ▶ ejecutar ahora · ⏸/▶ toggle enabled |

Botón global **"Ejecutar todos"** (comportamiento actual conservado).

### Modal de edición

Campos:
- **Frecuencia (cron expression):** input texto con preview en lenguaje natural (ej. `0 */4 * * *` → "Cada 4 horas")
- **Activo:** checkbox
- **Sin solapamiento:** checkbox
- **En background:** checkbox

Validación: el cron expression se valida antes de guardar; si es inválido se muestra error inline sin cerrar el modal.

---

## Componentes

| Componente | Tipo | Descripción |
|---|---|---|
| `create_cron_schedule_table` | Migración | Tabla nueva con todos los campos |
| `CronScheduleSeeder` | Seeder | Inserta los 20 artisan commands actuales + tareas legacy |
| `routes/console.php` | Modificación | Reemplaza Schedule hardcodeado por loop dinámico desde DB |
| `CronRunner.php` | Modificación | Lee tareas de `cron_schedule` en vez de hardcoded; actualiza last_run_at/last_status |
| `CronManagerController` | Modificación | Agrega endpoints `edit`, `toggle`; usa `cron_schedule` como fuente |
| `cron_manager/index.blade.php` | Modificación | UI unificada con badge de tipo, columnas last_run/status, modal de edición |
| Rutas nuevas | Rutas | `POST /cron-manager/{slug}/edit` y `POST /cron-manager/{slug}/toggle` |

---

## Validaciones y edge cases

- **Cron expression inválida:** validar con regex o librería antes de persistir. Mostrar error en modal, no guardar.
- **Comando artisan desconocido:** el scheduler simplemente no lo registra si el command no existe — no crashea.
- **Tarea en ejecución + toggle off:** `withoutOverlapping` previene doble ejecución; deshabilitar no mata un proceso ya corriendo.
- **Legacy en extinción:** las tareas `type=legacy` pueden quedarse en la tabla aunque el CronRunner se simplifique o elimine. No bloquean nada.

---

## Fuera de alcance

- Agregar comandos nuevos desde la UI (siempre via código + seeder)
- Historial de ejecuciones (los logs actuales del CronManager se conservan tal cual)
- Notificaciones de fallo (futura iteración)
