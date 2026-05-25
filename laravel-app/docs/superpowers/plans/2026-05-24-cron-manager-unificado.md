# CronManager Unificado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unificar todos los crons (legacy + artisan) en una tabla `cron_schedule` que el Laravel scheduler y el CronRunner lean en tiempo de ejecución, con UI para editar frecuencia, pausar y ejecutar manualmente.

**Architecture:** Nueva tabla `cron_schedule` es la fuente de verdad. `routes/console.php` registra artisan commands dinámicamente leyendo de esa tabla. `CronRunner` usa `Cron\CronExpression` (ya incluida en Laravel via `dragonmantank/cron-expression`) para evaluar si un legacy task debe correr. `CronManagerController` expone endpoints edit/toggle. La tabla `medforge_cron_tasks` sigue existiendo para los logs de ejecución legacy.

**Tech Stack:** PHP 8.3, Laravel 11, MySQL, `dragonmantank/cron-expression` (ya en vendor), Blade.

---

## File Map

| Acción | Archivo |
|--------|---------|
| Crear | `database/migrations/2026_05_24_000001_create_cron_schedule_table.php` |
| Crear | `database/seeders/CronScheduleSeeder.php` |
| Crear | `app/Modules/CronManager/Repositories/CronScheduleRepository.php` |
| Modificar | `routes/console.php` |
| Modificar | `app/Modules/CronManager/Services/CronRunner.php` |
| Modificar | `app/Modules/CronManager/Http/Controllers/CronManagerController.php` |
| Modificar | `routes/v2/cron_manager.php` |
| Modificar | `resources/views/cron_manager/index.blade.php` |
| Crear | `tests/Feature/CronScheduleControllerTest.php` |

---

## Task 1: Migración `cron_schedule`

**Files:**
- Create: `database/migrations/2026_05_24_000001_create_cron_schedule_table.php`

- [ ] **Step 1: Crear el archivo de migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_schedule', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 255);
            $table->string('command', 500);
            $table->enum('type', ['artisan', 'legacy'])->default('artisan');
            $table->string('cron_expression', 100)->default('*/15 * * * *');
            $table->boolean('enabled')->default(true);
            $table->boolean('run_in_background')->default(true);
            $table->boolean('without_overlapping')->default(true);
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 50)->nullable(); // ok | skipped | failed
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_schedule');
    }
};
```

- [ ] **Step 2: Ejecutar la migración**

```bash
php8.3-cli artisan migrate
```

Resultado esperado: `Migrating: 2026_05_24_000001_create_cron_schedule_table` → `Migrated`

- [ ] **Step 3: Verificar la tabla**

```bash
php8.3-cli artisan tinker --execute="Schema::hasTable('cron_schedule') ? 'OK' : 'FAIL';"
```

Resultado esperado: `OK`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_24_000001_create_cron_schedule_table.php
git commit -m "feat(cron): create cron_schedule table"
```

---

## Task 2: Seeder con todos los crons actuales

**Files:**
- Create: `database/seeders/CronScheduleSeeder.php`

- [ ] **Step 1: Crear el seeder**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CronScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        $artisan = [
            ['slug' => 'evaluar-sla', 'name' => 'Evaluar SLA solicitudes', 'command' => 'solicitudes:evaluar-sla', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Evalúa y marca solicitudes fuera de SLA.'],
            ['slug' => 'derivaciones-scrape-missing', 'name' => 'Scraping derivaciones faltantes', 'command' => 'derivaciones:scrape-missing --limit=200 --max-attempts=3 --cooldown-hours=6', 'cron_expression' => '0 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Scraping de derivaciones sin código en billing_main.'],
            ['slug' => 'enviar-recordatorios', 'name' => 'Recordatorios quirúrgicos', 'command' => 'solicitudes:enviar-recordatorios', 'cron_expression' => '0 8 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Notificaciones automáticas para cirugías próximas.'],
            ['slug' => 'crm-sync', 'name' => 'Sincronización CRM solicitudes', 'command' => 'solicitudes:crm-sync --lookback=3 --lookahead=14', 'cron_expression' => '0 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza solicitudes con SigCenter en ventana horaria.'],
            ['slug' => 'derivaciones-refresh', 'name' => 'Refresh derivaciones sin número', 'command' => 'solicitudes:derivaciones-refresh --solo-sin-numero', 'cron_expression' => '0 7,14 * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Actualiza derivaciones sin número a las 7am y 2pm.'],
            ['slug' => 'marcar-vencidas', 'name' => 'Marcar solicitudes vencidas', 'command' => 'solicitudes:marcar-vencidas', 'cron_expression' => '0 7 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Marca como vencidas las solicitudes cuyo agendamiento ya pasó.'],
            ['slug' => 'crm-task-reminders', 'name' => 'Recordatorios de tareas CRM', 'command' => 'solicitudes:crm-task-reminders --limit=100', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Dispara avisos cuando vence remind_at de una tarea CRM.'],
            ['slug' => 'handoff-requeue-expired', 'name' => 'Reencolar handoffs WhatsApp', 'command' => 'whatsapp:handoff-requeue-expired', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Reencola conversaciones cuyo tiempo de asignación expiró.'],
            ['slug' => 'flowmaker-shadow-sync', 'name' => 'Flowmaker shadow sync', 'command' => 'whatsapp:flowmaker-shadow-sync --limit=100', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Compara ejecución real vs shadow del flowmaker.'],
            ['slug' => 'monitor-abandonment', 'name' => 'Monitor abandono WhatsApp', 'command' => 'whatsapp:monitor-abandonment --limit=100', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Detecta y escala conversaciones abandonadas.'],
            ['slug' => 'sigcenter-availability-sync', 'name' => 'Disponibilidad SigCenter WhatsApp', 'command' => "whatsapp:sigcenter-availability-sync --days=7 --specialty='oftalmologo general'", 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza disponibilidad de agenda para el bot de citas.'],
            ['slug' => 'appointment-reminders-24h', 'name' => 'Recordatorios cita 24h', 'command' => 'whatsapp:appointment-reminders 24h --limit=200', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Envía recordatorio WhatsApp 24h antes de la cita.'],
            ['slug' => 'appointment-reminders-2h', 'name' => 'Recordatorios cita 2h', 'command' => 'whatsapp:appointment-reminders 2h --limit=200', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Envía recordatorio WhatsApp 2h antes de la cita.'],
            ['slug' => 'nas-index-day', 'name' => 'Índice NAS — 2 días', 'command' => 'imagenes:nas-index --days=2', 'cron_expression' => '0 7-19/2 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Indexa imágenes NAS de los últimos 2 días (cada 2h en horario hábil).'],
            ['slug' => 'nas-index-30days', 'name' => 'Índice NAS — 30 días', 'command' => 'imagenes:nas-index --days=30 --force', 'cron_expression' => '30 2 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Re-indexación nocturna de 30 días.'],
            ['slug' => 'index-admisiones-short', 'name' => 'Admisiones sync corto', 'command' => 'index-admisiones:sync --lookback=1 --lookahead=0 --extractor=scraper', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza admisiones del día cada 15 minutos.'],
            ['slug' => 'index-admisiones-wide', 'name' => 'Admisiones sync amplio', 'command' => 'index-admisiones:sync --lookback=14 --lookahead=14 --extractor=scraper', 'cron_expression' => '0 0,6,12,18 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza admisiones de 14 días pasados y futuros (4 veces al día).'],
            ['slug' => 'billing-facturacion-real', 'name' => 'Facturación real sync', 'command' => 'billing:facturacion-real-sync --extractor=scraper', 'cron_expression' => '0 */4 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza facturación real del mes actual cada 4 horas.'],
            ['slug' => 'farmacia-conciliacion-short', 'name' => 'Conciliación recetas — corto', 'command' => 'farmacia:conciliar-recetas --lookback=14 --lookahead=0', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Concilia recetas de farmacia de los últimos 14 días.'],
            ['slug' => 'farmacia-conciliacion-wide', 'name' => 'Conciliación recetas — amplio', 'command' => 'farmacia:conciliar-recetas --lookback=45 --lookahead=0', 'cron_expression' => '30 2 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Concilia recetas de farmacia de los últimos 45 días (nocturno).'],
        ];

        $legacy = [
            ['slug' => 'cive-index-admisiones-sync', 'name' => 'Scraping index-admisiones (legacy)', 'command' => 'cive-index-admisiones-sync', 'cron_expression' => '0 2 * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza pacientes desde CIVE (legacy).', 'enabled' => 0],
            ['slug' => 'solicitudes-overdue', 'name' => 'Actualizar solicitudes atrasadas', 'command' => 'solicitudes-overdue', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Marca solicitudes vencidas (legacy).'],
            ['slug' => 'solicitudes-reminders', 'name' => 'Recordatorios de cirugías (legacy)', 'command' => 'solicitudes-reminders', 'cron_expression' => '*/10 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Notificaciones para cirugías próximas (legacy).'],
            ['slug' => 'crm-task-reminders-legacy', 'name' => 'Recordatorios CRM (legacy)', 'command' => 'crm-task-reminders', 'cron_expression' => '* * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Dispara avisos CRM (legacy — cubierto por artisan).', 'enabled' => 0],
            ['slug' => 'crm-task-supervisor-escalations', 'name' => 'Escalamientos CRM', 'command' => 'crm-task-supervisor-escalations', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Notifica supervisores cuando una tarea CRM vence.'],
            ['slug' => 'whatsapp-handoff-requeue', 'name' => 'Reencolar handoffs (legacy)', 'command' => 'whatsapp-handoff-requeue', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Reencola conversaciones (legacy — cubierto por artisan).', 'enabled' => 0],
            ['slug' => 'billing-autocreation', 'name' => 'Prefacturación automática', 'command' => 'billing-autocreation', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Crea registros en billing_main para solicitudes listas.'],
            ['slug' => 'stats-refresh', 'name' => 'Estadísticas diarias', 'command' => 'stats-refresh', 'cron_expression' => '0 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Recalcula métricas operativas.'],
            ['slug' => 'kpi-refresh', 'name' => 'Snapshots de KPIs', 'command' => 'kpi-refresh', 'cron_expression' => '0 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Recalcula KPIs para dashboards.'],
            ['slug' => 'ai-sync', 'name' => 'Analítica IA', 'command' => 'ai-sync', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza resultados de análisis IA.'],
            ['slug' => 'cive-extension-health', 'name' => 'Supervisión API CIVE Extension', 'command' => 'cive-extension-health', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Verifica disponibilidad de endpoints de la extensión.'],
            ['slug' => 'identity-verification-expiration', 'name' => 'Caducidad biométrica', 'command' => 'identity-verification-expiration', 'cron_expression' => '0 2 * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Marca certificaciones biométricas vencidas.'],
            ['slug' => 'iess-derivaciones-sync', 'name' => 'Derivaciones IESS sync', 'command' => 'iess-derivaciones-sync', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza derivaciones IESS.'],
            ['slug' => 'iess-derivaciones-scrape-missing', 'name' => 'Scraping derivaciones IESS', 'command' => 'iess-derivaciones-scrape-missing', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Scraping de derivaciones IESS faltantes.'],
            ['slug' => 'iess-billing-sync', 'name' => 'Facturas IESS sync', 'command' => 'iess-billing-sync', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza facturación IESS.'],
            ['slug' => 'solicitudes-crm-sync-legacy', 'name' => 'CRM solicitudes sync (legacy)', 'command' => 'solicitudes-crm-sync', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Reintenta vincular solicitudes sin lead CRM (legacy).', 'enabled' => 0],
            ['slug' => 'solicitudes-derivaciones-refresh', 'name' => 'Derivaciones en solicitudes', 'command' => 'solicitudes-derivaciones-refresh', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Actualiza derivaciones para solicitudes estatales.'],
            ['slug' => 'reporting-async-queue', 'name' => 'Reportes PDF async (DEPRECATED)', 'command' => 'reporting-async-queue', 'cron_expression' => '* * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Deprecado desde 2026-03-06 — reportes en /v2/reports.', 'enabled' => 0],
        ];

        $rows = [];
        foreach ($artisan as $task) {
            $rows[] = array_merge(['type' => 'artisan', 'enabled' => 1], $task);
        }
        foreach ($legacy as $task) {
            $rows[] = array_merge(['type' => 'legacy', 'enabled' => 1], $task);
        }

        foreach ($rows as $row) {
            DB::table('cron_schedule')->updateOrInsert(
                ['slug' => $row['slug']],
                $row
            );
        }
    }
}
```

- [ ] **Step 2: Ejecutar el seeder**

```bash
php8.3-cli artisan db:seed --class=CronScheduleSeeder
```

Resultado esperado: `Seeding: Database\Seeders\CronScheduleSeeder` → done sin errores.

- [ ] **Step 3: Verificar filas insertadas**

```bash
php8.3-cli artisan tinker --execute="echo DB::table('cron_schedule')->count();"
```

Resultado esperado: `38` (20 artisan + 18 legacy).

- [ ] **Step 4: Commit**

```bash
git add database/seeders/CronScheduleSeeder.php
git commit -m "feat(cron): seed cron_schedule with all artisan + legacy tasks"
```

---

## Task 3: CronScheduleRepository

**Files:**
- Create: `app/Modules/CronManager/Repositories/CronScheduleRepository.php`

- [ ] **Step 1: Escribir test primero**

```php
<?php
// tests/Feature/CronScheduleControllerTest.php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CronScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('cron_schedule')->insert([
            'slug' => 'test-task',
            'name' => 'Test Task',
            'command' => 'test:command',
            'type' => 'artisan',
            'cron_expression' => '*/15 * * * *',
            'enabled' => 1,
            'run_in_background' => 1,
            'without_overlapping' => 1,
        ]);
    }

    public function test_toggle_disables_task(): void
    {
        $this->actingAsAdmin()
            ->post('/v2/cron-manager/toggle/test-task')
            ->assertRedirect();

        $this->assertDatabaseHas('cron_schedule', [
            'slug' => 'test-task',
            'enabled' => 0,
        ]);
    }

    public function test_toggle_enables_disabled_task(): void
    {
        DB::table('cron_schedule')->where('slug', 'test-task')->update(['enabled' => 0]);

        $this->actingAsAdmin()
            ->post('/v2/cron-manager/toggle/test-task')
            ->assertRedirect();

        $this->assertDatabaseHas('cron_schedule', [
            'slug' => 'test-task',
            'enabled' => 1,
        ]);
    }

    public function test_edit_updates_cron_expression(): void
    {
        $this->actingAsAdmin()
            ->post('/v2/cron-manager/edit/test-task', [
                'cron_expression' => '0 * * * *',
                'enabled' => '1',
                'run_in_background' => '1',
                'without_overlapping' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cron_schedule', [
            'slug' => 'test-task',
            'cron_expression' => '0 * * * *',
        ]);
    }

    public function test_edit_rejects_invalid_cron_expression(): void
    {
        $this->actingAsAdmin()
            ->post('/v2/cron-manager/edit/test-task', [
                'cron_expression' => 'not-valid',
                'enabled' => '1',
            ])
            ->assertSessionHasErrors('cron_expression');
    }

    // Helper — ajustar según cómo el proyecto maneja autenticación en tests
    private function actingAsAdmin(): static
    {
        // Usa el mecanismo de autenticación existente del proyecto.
        // Si hay un usuario admin en DB, cargarlo así:
        // return $this->actingAs(\App\Models\User::where('role', 'admin')->first());
        return $this; // Placeholder — ver TestCase del proyecto para el patrón correcto
    }
}
```

- [ ] **Step 2: Ejecutar el test para ver que falla**

```bash
php8.3-cli artisan test tests/Feature/CronScheduleControllerTest.php
```

Resultado esperado: errores por rutas/clases no existentes aún.

- [ ] **Step 3: Crear CronScheduleRepository**

```php
<?php
// app/Modules/CronManager/Repositories/CronScheduleRepository.php

declare(strict_types=1);

namespace App\Modules\CronManager\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CronScheduleRepository
{
    /**
     * @return Collection<int, object>
     */
    public function getAll(): Collection
    {
        return DB::table('cron_schedule')->orderByRaw("type = 'artisan' DESC")->orderBy('name')->get();
    }

    public function findBySlug(string $slug): ?object
    {
        return DB::table('cron_schedule')->where('slug', $slug)->first() ?: null;
    }

    public function update(string $slug, array $data): void
    {
        DB::table('cron_schedule')->where('slug', $slug)->update($data);
    }

    public function toggle(string $slug): void
    {
        DB::table('cron_schedule')
            ->where('slug', $slug)
            ->update(['enabled' => DB::raw('1 - enabled')]);
    }

    /**
     * @return Collection<int, object>
     */
    public function getEnabled(string $type): Collection
    {
        return DB::table('cron_schedule')
            ->where('type', $type)
            ->where('enabled', 1)
            ->get();
    }

    public function updateExecution(string $slug, string $status): void
    {
        DB::table('cron_schedule')
            ->where('slug', $slug)
            ->update([
                'last_run_at' => now(),
                'last_status' => $status,
            ]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/CronManager/Repositories/CronScheduleRepository.php \
        tests/Feature/CronScheduleControllerTest.php
git commit -m "feat(cron): add CronScheduleRepository and controller tests"
```

---

## Task 4: routes/console.php — registro dinámico

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Reemplazar el bloque "Crons migrados desde crontab" y todos los Schedule:: hardcodeados**

Localizar en `routes/console.php` el bloque que comienza en `// ── Crons migrados desde crontab` hasta el final del archivo (los `Schedule::command(...)` hardcodeados). Reemplazar TODO ese bloque por:

```php
// ── Scheduler DB-driven ──────────────────────────────────────────────────────
// Las frecuencias viven en la tabla cron_schedule y son editables desde el UI.

use App\Modules\CronManager\Repositories\CronScheduleRepository;

(static function (): void {
    try {
        $repo = new CronScheduleRepository();
        foreach ($repo->getEnabled('artisan') as $task) {
            $cmd = Schedule::command($task->command)->cron($task->cron_expression);
            if ($task->without_overlapping) {
                $cmd->withoutOverlapping();
            }
            if ($task->run_in_background) {
                $cmd->runInBackground();
            }
        }
    } catch (\Throwable $e) {
        // Si la tabla no existe aún (ej: primera migración), no crashear el scheduler.
        \Illuminate\Support\Facades\Log::warning('cron_schedule: no se pudo registrar schedule desde DB', ['error' => $e->getMessage()]);
    }
})();
```

> **Nota:** También eliminar los `Schedule::command(...)` anteriores que estaban hardcodeados (solicitudes:evaluar-sla, derivaciones:scrape-missing, etc.). Todos son reemplazados por el loop de arriba.

- [ ] **Step 2: Verificar que el scheduler sigue funcionando**

```bash
php8.3-cli artisan schedule:list
```

Resultado esperado: los mismos 20 artisan commands listados, con sus frecuencias del seeder. Si la tabla está vacía o no existe, el scheduler no crashea — simplemente no muestra nada.

- [ ] **Step 3: Verificar que los cambios en DB se reflejan inmediatamente**

```bash
# Cambiar frecuencia de un task en DB
php8.3-cli artisan tinker --execute="DB::table('cron_schedule')->where('slug','evaluar-sla')->update(['cron_expression'=>'*/10 * * * *']);"

# Verificar que schedule:list muestra el nuevo valor
php8.3-cli artisan schedule:list | grep evaluar-sla
```

Resultado esperado: `*/10 * * * *` en lugar de `*/30`.

- [ ] **Step 4: Revertir el cambio de prueba**

```bash
php8.3-cli artisan tinker --execute="DB::table('cron_schedule')->where('slug','evaluar-sla')->update(['cron_expression'=>'*/30 * * * *']);"
```

- [ ] **Step 5: Commit**

```bash
git add routes/console.php
git commit -m "feat(cron): register artisan schedule dynamically from cron_schedule table"
```

---

## Task 5: CronRunner — legacy tasks desde DB

**Files:**
- Modify: `app/Modules/CronManager/Services/CronRunner.php`

El CronRunner actualmente usa `interval` (segundos) y `next_run_at` para decidir si una tarea corre. Necesitamos que también respete `enabled` de `cron_schedule` y actualice `last_run_at`/`last_status` ahí.

- [ ] **Step 1: Añadir método `isScheduledToRun` en CronRunner**

En `CronRunner.php`, agregar este método privado debajo de `runDefinition()`:

```php
private function isScheduledToRun(string $slug): bool
{
    try {
        $schedule = \Illuminate\Support\Facades\DB::table('cron_schedule')
            ->where('slug', $slug)
            ->where('type', 'legacy')
            ->first();

        if ($schedule === null || ! $schedule->enabled) {
            return false;
        }

        $expr = new \Cron\CronExpression($schedule->cron_expression);
        $now = new \DateTimeImmutable('now');
        $prevRun = \DateTimeImmutable::createFromMutable($expr->getPreviousRunDate('now', 0, true));

        // Ventana de 310s (cron.php corre cada 5 min + margen de drift)
        $withinWindow = ($now->getTimestamp() - $prevRun->getTimestamp()) <= 310;

        $lastRun = $schedule->last_run_at
            ? new \DateTimeImmutable($schedule->last_run_at)
            : null;

        $notRunRecently = $lastRun === null || $prevRun->getTimestamp() > $lastRun->getTimestamp();

        return $withinWindow && $notRunRecently;
    } catch (\Throwable) {
        return true; // Si falla, dejar correr (fallback seguro)
    }
}

private function updateScheduleExecution(string $slug, string $status): void
{
    try {
        \Illuminate\Support\Facades\DB::table('cron_schedule')
            ->where('slug', $slug)
            ->where('type', 'legacy')
            ->update([
                'last_run_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                'last_status' => $status,
            ]);
    } catch (\Throwable) {
        // No fatal si la tabla no existe
    }
}
```

- [ ] **Step 2: Modificar `runDefinition()` para usar `isScheduledToRun`**

En `runDefinition()`, localizar el bloque que verifica `is_active` y `next_run_at`. Después del check de `is_active`, agregar:

```php
// Respetar cron_schedule.enabled si existe la tabla
if (!$force && !$this->isScheduledToRun($definition['slug'])) {
    return [
        'slug' => $definition['slug'],
        'name' => $definition['name'],
        'status' => 'skipped',
        'message' => 'No programado en este ciclo (cron_schedule).',
        'details' => null,
        'ran' => false,
    ];
}
```

Insertar esto **después** del chequeo de `is_active` y **antes** del chequeo de `next_run_at` existente.

- [ ] **Step 3: Actualizar `cron_schedule` al finalizar cada tarea**

Al final de `runDefinition()`, justo antes del `return`, localizar donde se llama `$this->repository->markSuccess()` / `markFailure()` / `markSkipped()`. Después de cada uno, agregar la llamada a `updateScheduleExecution`:

Buscar en CronRunner.php las llamadas a `$this->repository->markSuccess(...)`, `markFailure(...)` y `markSkipped(...)`. Después de cada una, agregar:

```php
$this->updateScheduleExecution(
    $definition['slug'],
    $result['status'] === 'success' ? 'ok' : $result['status']
);
```

- [ ] **Step 4: Verificar que el legacy cron sigue funcionando**

```bash
php8.3-cli artisan tinker --execute="
\$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
\$runner = new \App\Modules\CronManager\Services\CronRunner(\$pdo);
\$results = \$runner->runAll(true);
echo count(\$results) . ' tasks procesadas';
"
```

Resultado esperado: número de tareas sin excepción.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/CronManager/Services/CronRunner.php
git commit -m "feat(cron): CronRunner respeta cron_schedule enabled y cron_expression"
```

---

## Task 6: Controller + rutas — edit/toggle endpoints

**Files:**
- Modify: `app/Modules/CronManager/Http/Controllers/CronManagerController.php`
- Modify: `routes/v2/cron_manager.php`

- [ ] **Step 1: Agregar rutas en `routes/v2/cron_manager.php`**

```php
// Agregar dentro del grupo existente de middleware:
Route::post('/cron-manager/toggle/{slug}', [CronManagerController::class, 'toggle']);
Route::post('/cron-manager/edit/{slug}', [CronManagerController::class, 'edit']);
```

- [ ] **Step 2: Agregar métodos en `CronManagerController`**

Primero agregar el import al top del archivo:

```php
use App\Modules\CronManager\Repositories\CronScheduleRepository;
use Cron\CronExpression;
```

Luego agregar una propiedad al constructor:

```php
private CronScheduleRepository $scheduleRepository;

public function __construct()
{
    $pdo = DB::connection()->getPdo();
    $this->repository = new CronTaskRepository($pdo);
    $this->scheduleRepository = new CronScheduleRepository();
}
```

Agregar los métodos:

```php
public function toggle(Request $request, string $slug): RedirectResponse
{
    $task = $this->scheduleRepository->findBySlug($slug);

    if ($task === null) {
        return redirect('/v2/cron-manager')->withErrors(['error' => "Tarea '{$slug}' no encontrada."]);
    }

    $this->scheduleRepository->toggle($slug);

    return redirect('/v2/cron-manager');
}

public function edit(Request $request, string $slug): RedirectResponse
{
    $task = $this->scheduleRepository->findBySlug($slug);

    if ($task === null) {
        return redirect('/v2/cron-manager')->withErrors(['error' => "Tarea '{$slug}' no encontrada."]);
    }

    $validated = $request->validate([
        'cron_expression'    => ['required', 'string', 'max:100', function (string $attr, mixed $value, \Closure $fail): void {
            try {
                new CronExpression((string) $value);
            } catch (\Throwable) {
                $fail('La expresión cron no es válida.');
            }
        }],
        'enabled'            => ['nullable', 'in:0,1'],
        'run_in_background'  => ['nullable', 'in:0,1'],
        'without_overlapping'=> ['nullable', 'in:0,1'],
    ]);

    $this->scheduleRepository->update($slug, [
        'cron_expression'     => $validated['cron_expression'],
        'enabled'             => (int) ($validated['enabled'] ?? 0),
        'run_in_background'   => (int) ($validated['run_in_background'] ?? 0),
        'without_overlapping' => (int) ($validated['without_overlapping'] ?? 0),
    ]);

    return redirect('/v2/cron-manager');
}
```

- [ ] **Step 3: Actualizar `index()` para usar `CronScheduleRepository`**

En el método `index()`, reemplazar la obtención de `$tasks`:

```php
public function index(Request $request): View
{
    $results = $request->session()->pull('cron_manager_results');

    $tasks = $this->scheduleRepository->getAll();
    $logs  = $this->prepareLogs($this->repository->getRecentLogs(20));

    return view('cron_manager.index', [
        'pageTitle'   => 'Cron Manager',
        'currentUser' => LegacyCurrentUser::resolve($request),
        'tasks'       => $tasks,
        'logs'        => $logs,
        'results'     => $results,
    ]);
}
```

- [ ] **Step 4: Ejecutar los tests**

```bash
php8.3-cli artisan test tests/Feature/CronScheduleControllerTest.php
```

Resultado esperado: todos los tests pasan. Ajustar `actingAsAdmin()` según el mecanismo de auth del proyecto si es necesario.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/CronManager/Http/Controllers/CronManagerController.php \
        routes/v2/cron_manager.php
git commit -m "feat(cron): add edit/toggle endpoints to CronManagerController"
```

---

## Task 7: UI — blade template unificado

**Files:**
- Modify: `resources/views/cron_manager/index.blade.php`

- [ ] **Step 1: Leer el blade actual para entender la estructura existente**

```bash
head -80 resources/views/cron_manager/index.blade.php
```

Identificar: qué variable se itera para mostrar tareas (antes era `$tasks` de `medforge_cron_tasks`), cómo se construyen los forms de "run".

- [ ] **Step 2: Actualizar la tabla principal**

Reemplazar el loop de la tabla de tareas para usar las columnas de `cron_schedule`. La tabla debe mostrar estas columnas:

| Nombre | Tipo | Frecuencia | Último run | Estado | Acciones |
|--------|------|-----------|-----------|--------|---------|

Código Blade del body de la tabla:

```blade
@foreach($tasks as $task)
<tr class="{{ $task->enabled ? '' : 'opacity-50' }}">
    <td>{{ $task->name }}</td>
    <td>
        <span class="badge {{ $task->type === 'artisan' ? 'badge-primary' : 'badge-secondary' }}">
            {{ $task->type }}
        </span>
    </td>
    <td><code>{{ $task->cron_expression }}</code></td>
    <td>
        @if($task->last_run_at)
            {{ \Carbon\Carbon::parse($task->last_run_at)->diffForHumans() }}
        @else
            —
        @endif
    </td>
    <td>
        @if($task->last_status === 'ok')
            <span class="text-success">✅ ok</span>
        @elseif($task->last_status === 'skipped')
            <span class="text-warning">⚠ skip</span>
        @elseif($task->last_status === 'failed')
            <span class="text-danger">❌ failed</span>
        @else
            —
        @endif
    </td>
    <td class="text-nowrap">
        {{-- Editar --}}
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                data-bs-toggle="modal"
                data-bs-target="#editModal"
                data-slug="{{ $task->slug }}"
                data-name="{{ $task->name }}"
                data-cron="{{ $task->cron_expression }}"
                data-enabled="{{ $task->enabled }}"
                data-bg="{{ $task->run_in_background }}"
                data-overlap="{{ $task->without_overlapping }}">
            ✏
        </button>
        {{-- Ejecutar ahora --}}
        <form method="POST" action="/v2/cron-manager/run/{{ $task->slug }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">▶</button>
        </form>
        {{-- Toggle enabled --}}
        <form method="POST" action="/v2/cron-manager/toggle/{{ $task->slug }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-sm {{ $task->enabled ? 'btn-outline-warning' : 'btn-outline-success' }}">
                {{ $task->enabled ? '⏸' : '▶' }}
            </button>
        </form>
    </td>
</tr>
@endforeach
```

- [ ] **Step 3: Agregar el modal de edición**

Antes del cierre de `</body>` (o donde se colocan los modales en el layout existente):

```blade
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editForm" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar: <span id="editModalTitle"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Frecuencia (cron expression)</label>
                        <input type="text" name="cron_expression" id="editCronExpression"
                               class="form-control font-monospace" required>
                        <div class="form-text text-muted" id="editCronPreview"></div>
                        @error('cron_expression')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="enabled" id="editEnabled"
                               class="form-check-input" value="1">
                        <label class="form-check-label" for="editEnabled">Activo</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="run_in_background" id="editBg"
                               class="form-check-input" value="1">
                        <label class="form-check-label" for="editBg">En background</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="without_overlapping" id="editOverlap"
                               class="form-check-input" value="1">
                        <label class="form-check-label" for="editOverlap">Sin solapamiento</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    const slug = btn.dataset.slug;

    document.getElementById('editForm').action = '/v2/cron-manager/edit/' + slug;
    document.getElementById('editModalTitle').textContent = btn.dataset.name;
    document.getElementById('editCronExpression').value = btn.dataset.cron;
    document.getElementById('editEnabled').checked = btn.dataset.enabled === '1';
    document.getElementById('editBg').checked = btn.dataset.bg === '1';
    document.getElementById('editOverlap').checked = btn.dataset.overlap === '1';
});
</script>
```

- [ ] **Step 4: Verificar la UI en el navegador**

Navegar a `/v2/cron-manager` y verificar:
- Tabla muestra todos los crons (artisan + legacy)
- Badge artisan/legacy visible
- Botón ✏ abre el modal con los valores correctos
- Toggle ⏸/▶ funciona y recarga la página con el estado actualizado
- ▶ ejecuta la tarea y redirige con resultado

- [ ] **Step 5: Commit**

```bash
git add resources/views/cron_manager/index.blade.php
git commit -m "feat(cron): unified CronManager UI with edit modal and toggle"
```

---

## Task 8: PR y limpieza de crontab

- [ ] **Step 1: Push y abrir PR**

```bash
git push -u origin <branch>
gh pr create --title "feat(cron): CronManager unificado con scheduler DB-driven" \
  --body "Migra todos los crons a tabla cron_schedule editable desde UI."
```

- [ ] **Step 2: Limpiar crontab en servidor (después de mergear y hacer pull)**

```bash
EDITOR=nano crontab -e
```

Eliminar las 7 líneas de artisan commands que ya están manejadas por el scheduler (imagenes, index-admisiones ×2, billing, farmacia ×2). Corregir `2>&u1` → `2>&1` en la línea de `schedule:run`.

El crontab final queda con **3 líneas activas:**
```
*/5 * * * * /usr/bin/php8.3-cli /kunden/.../cron.php >> .../cron.log 2>&1
* * * * * cd .../laravel-app && php8.3-cli artisan schedule:run >> storage/logs/laravel-schedule.log 2>&1
* * * * * php8.3-cli .../artisan queue:work --stop-when-empty --max-time=55
```
