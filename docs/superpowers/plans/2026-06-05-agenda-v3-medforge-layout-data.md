# Agenda V3 MedForge Layout And Real Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Embed Agenda V3 inside the existing MedForge layout, remove duplicate internal shell navigation, and make real SigCenter/legacy data render reliably.

**Architecture:** Keep `agenda/v3-shell.blade.php` as a MedForge Blade shell and turn `public/agenda-v3/app.jsx` into a content-only React orchestrator. Backend normalization in `AgendaV3Controller` guarantees real appointments have visible resource IDs, while frontend helpers prevent read-only SigCenter records from calling V3 write endpoints.

**Tech Stack:** Laravel 12, PHPUnit Feature tests with SQLite in-memory, Blade, React 18 UMD/Babel standalone assets in `public/agenda-v3`.

---

## File Structure

- Modify: `laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php`
  - Fix doctor sync SQL.
  - Log sync failures.
  - Populate `user_id`.
  - Normalize legacy `procedimiento_proyectado` rows with visible fallback doctor/sala/tipo.
- Modify: `public/agenda-v3/app.jsx`
  - Remove internal app shell header/sidebar/logo.
  - Read active view from `?view=`.
  - Keep only Agenda content and controls.
  - Use `_dbId`/`_readonly` for write actions.
- Modify: `public/agenda-v3/shell.css`
  - Convert `.app` and content layout styles from full-screen grid to embedded content.
  - Remove or neutralize full-screen body overflow rules and internal sidebar/header styles.
- Modify: `public/agenda-v3/components.jsx`
  - Add null-safe catalog lookup helpers.
  - Add display fallbacks for missing resources.
- Modify: `public/agenda-v3/calendar.jsx`
  - Ensure calendar renders with fallback resources and no null crashes.
  - Guard week view when a sede has no doctors.
- Modify: `public/agenda-v3/flowboard.jsx`
  - Ensure cards use null-safe labels.
  - Keep SigCenter rows read-only.
- Modify: `public/agenda-v3/modals.jsx`
  - Hide or disable V3 write actions for read-only rows.
- Modify: `laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php`
  - Point Agenda-related sidebar items to query-param views in the single shell.
- Add: `laravel-app/tests/Feature/AgendaV3ControllerTest.php`
  - Cover config doctor sync and real legacy cita normalization.

## Task 1: Add Feature Test Coverage For Agenda V3 Real Data

**Files:**
- Create: `laravel-app/tests/Feature/AgendaV3ControllerTest.php`

- [ ] **Step 1: Create the failing Feature test**

Create `laravel-app/tests/Feature/AgendaV3ControllerTest.php` with this content:

```php
<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaV3ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        foreach ([
            'agenda_citas_v3',
            'agenda_bloqueos',
            'agenda_horarios',
            'agenda_tipos_cita',
            'agenda_salas',
            'agenda_medicos',
            'agenda_sedes',
            'procedimiento_proyectado',
            'patient_data',
            'visitas',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->default('');
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('especialidad')->nullable();
            $table->string('subespecialidad')->nullable();
            $table->string('sede')->nullable();
        });

        Schema::create('agenda_sedes', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('label', 80);
            $table->string('abrev', 8)->default('');
            $table->time('apertura')->default('08:00:00');
            $table->time('cierre')->default('18:00:00');
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_medicos', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('nombre', 120);
            $table->string('especialidad', 120)->default('');
            $table->json('areas');
            $table->string('sede_id', 32);
            $table->string('color', 16)->default('#5156be');
            $table->string('iniciales', 4)->default('');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_salas', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('sede_id', 32);
            $table->string('label', 80);
            $table->string('tipo', 32);
            $table->string('area', 32);
            $table->unsignedTinyInteger('cap')->default(1);
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_tipos_cita', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('label', 120);
            $table->string('area', 32);
            $table->unsignedSmallInteger('dur_minutos')->default(20);
            $table->json('requiere_tipo_sala');
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_horarios', function (Blueprint $table): void {
            $table->id();
            $table->string('medico_id', 32);
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('sede_id', 32);
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_bloqueos', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 8);
            $table->string('ref_id', 32);
            $table->date('fecha');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('motivo', 200)->default('');
            $table->string('tipo', 32)->default('otro');
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('agenda_citas_v3', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('sede_id', 32);
            $table->string('medico_id', 32);
            $table->string('sala_id', 32);
            $table->string('tipo_id', 32);
            $table->string('paciente', 200);
            $table->string('hc_number', 64)->default('');
            $table->unsignedTinyInteger('edad')->nullable();
            $table->string('afiliacion', 64)->default('');
            $table->string('tel', 32)->default('');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('estado', 32)->default('agendado');
            $table->string('whatsapp_estado', 32)->default('na');
            $table->time('hora_llegada')->nullable();
            $table->time('hora_sala')->nullable();
            $table->time('hora_consulta')->nullable();
            $table->time('hora_fin_atencion')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('sobreturno')->default(false);
            $table->boolean('hc_llena')->default(false);
            $table->json('hc_data')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number', 64)->unique();
            $table->string('fname', 191)->nullable();
            $table->string('mname', 191)->nullable();
            $table->string('lname', 191)->nullable();
            $table->string('lname2', 191)->nullable();
        });

        Schema::create('visitas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha_visita')->nullable();
            $table->time('hora_llegada')->nullable();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number', 64)->index();
            $table->string('procedimiento_proyectado', 191)->nullable();
            $table->string('doctor', 191)->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('sede_departamento', 191)->nullable();
            $table->string('estado_agenda', 64)->nullable();
            $table->string('afiliacion', 64)->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->integer('visita_id')->nullable();
        });

        DB::table('agenda_sedes')->insert([
            ['id' => 'ceibos', 'label' => 'Ceibos', 'abrev' => 'CB', 'apertura' => '08:00:00', 'cierre' => '18:00:00', 'activo' => true],
        ]);

        DB::table('agenda_salas')->insert([
            ['id' => 's_cons1', 'sede_id' => 'ceibos', 'label' => 'Consultorio 1', 'tipo' => 'consultorio', 'area' => 'consulta', 'cap' => 1, 'activo' => true],
        ]);

        DB::table('agenda_tipos_cita')->insert([
            ['id' => 't_cons', 'label' => 'Consulta oftalmológica', 'area' => 'consulta', 'dur_minutos' => 20, 'requiere_tipo_sala' => '["consultorio"]', 'activo' => true],
        ]);
    }

    public function test_config_syncs_real_doctors_from_users(): void
    {
        $user = User::query()->create([
            'username' => 'doctor',
            'email' => 'doctor@test.com',
            'nombre' => 'DRA. MARIA LOPEZ',
            'especialidad' => 'OFTALMOLOGIA',
            'subespecialidad' => 'RETINA',
            'sede' => 'Ceibos',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/api/agenda/config');

        $response->assertOk();
        $response->assertJsonPath('MEDICOS.0.id', 'usr_' . $user->id);
        $response->assertJsonPath('MEDICOS.0.sede', 'ceibos');

        $this->assertDatabaseHas('agenda_medicos', [
            'id' => 'usr_' . $user->id,
            'user_id' => $user->id,
            'sede_id' => 'ceibos',
            'activo' => true,
        ]);
    }

    public function test_legacy_sigcenter_citas_have_visible_fallback_resources(): void
    {
        $user = User::query()->create([
            'username' => 'agenda',
            'email' => 'agenda@test.com',
            'nombre' => 'DRA. MARIA LOPEZ',
            'especialidad' => 'OFTALMOLOGIA',
            'subespecialidad' => 'RETINA',
            'sede' => 'Ceibos',
        ]);

        DB::table('patient_data')->insert([
            'hc_number' => 'HC-300',
            'fname' => 'Ana',
            'mname' => null,
            'lname' => 'Vera',
            'lname2' => 'Mora',
        ]);

        DB::table('procedimiento_proyectado')->insert([
            'id' => 501,
            'hc_number' => 'HC-300',
            'procedimiento_proyectado' => 'CONSULTA - SER-OFT-004 - CONSULTA OFTALMOLOGICA',
            'doctor' => 'DOCTOR NO SINCRONIZADO',
            'fecha' => '2026-06-05',
            'hora' => '09:15:00',
            'sede_departamento' => 'Ceibos',
            'estado_agenda' => 'CONFIRMADO',
            'afiliacion' => 'IESS',
            'sigcenter_present' => true,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/api/agenda/citas?fecha=2026-06-05&sede=ceibos');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', 501);
        $response->assertJsonPath('data.0._source', 'pp');
        $response->assertJsonPath('data.0._readonly', true);
        $response->assertJsonPath('data.0.medico_id', 'usr_' . $user->id);
        $response->assertJsonPath('data.0.sala_id', 's_cons1');
        $response->assertJsonPath('data.0.tipo_id', 't_cons');
        $response->assertJsonPath('data.0.paciente', 'Ana Vera Mora');
        $response->assertJsonPath('data.0.estado', 'confirmado');
    }
}
```

- [ ] **Step 2: Run the new tests and verify they fail**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364/laravel-app
php artisan test --filter=AgendaV3ControllerTest
```

Expected: at least one failure proving the current implementation does not sync doctors or does not provide fallback resources for legacy rows.

- [ ] **Step 3: Commit the failing tests**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git add laravel-app/tests/Feature/AgendaV3ControllerTest.php
git commit -m "test(agenda-v3): cover real data normalization"
```

Expected: commit succeeds with only the new test file.

## Task 2: Fix Backend Real-Data Normalization

**Files:**
- Modify: `laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php`
- Test: `laravel-app/tests/Feature/AgendaV3ControllerTest.php`

- [ ] **Step 1: Fix `syncMedicosFromPP()` SQL and insert `user_id`**

In `AgendaV3Controller::syncMedicosFromPP()`, replace the `DB::select()` SQL with:

```php
$usersRaw = DB::select(
    "SELECT id, TRIM(COALESCE(nombre,'')) AS nombre,
            TRIM(COALESCE(especialidad,'')) AS especialidad,
            TRIM(COALESCE(subespecialidad,'')) AS subespecialidad,
            TRIM(COALESCE(sede,'')) AS sede
     FROM users
     WHERE especialidad IS NOT NULL AND TRIM(especialidad) != ''
       AND nombre IS NOT NULL AND TRIM(nombre) != ''
     ORDER BY nombre ASC"
);
```

In the `updateOrInsert()` payload for `agenda_medicos`, add:

```php
'user_id' => (int) $u->id,
```

- [ ] **Step 2: Log sync failures instead of swallowing them**

Add `use Illuminate\Support\Facades\Log;` at the top of `AgendaV3Controller.php`.

Replace:

```php
} catch (\Throwable) {
    // Non-fatal
}
```

with:

```php
} catch (\Throwable $e) {
    Log::warning('Agenda V3 doctor sync failed', [
        'error' => $e->getMessage(),
    ]);
}
```

- [ ] **Step 3: Add fallback helper methods**

Add these private methods near the other helper methods in `AgendaV3Controller.php`:

```php
private function fallbackMedicoId(string $sedeId): string
{
    $id = DB::table('agenda_medicos')
        ->where('activo', true)
        ->where('sede_id', $sedeId)
        ->orderBy('nombre')
        ->value('id');

    if (is_string($id) && $id !== '') {
        return $id;
    }

    $any = DB::table('agenda_medicos')
        ->where('activo', true)
        ->orderBy('nombre')
        ->value('id');

    return is_string($any) && $any !== '' ? $any : '';
}

private function fallbackTipoId(string $area): string
{
    $id = DB::table('agenda_tipos_cita')
        ->where('activo', true)
        ->where('area', $area)
        ->orderBy('id')
        ->value('id');

    if (is_string($id) && $id !== '') {
        return $id;
    }

    $any = DB::table('agenda_tipos_cita')
        ->where('activo', true)
        ->orderBy('id')
        ->value('id');

    return is_string($any) && $any !== '' ? $any : '';
}

private function fallbackSalaId(string $sedeId, string $area): string
{
    $id = DB::table('agenda_salas')
        ->where('activo', true)
        ->where('sede_id', $sedeId)
        ->where('area', $area)
        ->orderBy('id')
        ->value('id');

    if (is_string($id) && $id !== '') {
        return $id;
    }

    $any = DB::table('agenda_salas')
        ->where('activo', true)
        ->where('sede_id', $sedeId)
        ->orderBy('id')
        ->value('id');

    return is_string($any) && $any !== '' ? $any : '';
}
```

- [ ] **Step 4: Use fallback helpers in `fetchPPCitas()`**

In the `array_map()` callback inside `fetchPPCitas()`, after `$medSlug` is calculated, add:

```php
if ($medSlug === '') {
    $medSlug = $this->fallbackMedicoId($sedeSlug);
}
```

After `$tipoLabel` is calculated, add:

```php
$area = 'consulta';
$tipoId = $this->fallbackTipoId($area);
$salaId = $this->fallbackSalaId($sedeSlug, $area);
```

In the returned array, replace:

```php
'medico_id'         => $medSlug,
'sala_id'           => '',
'tipo_id'           => '',
'area'              => 'consulta',
```

with:

```php
'medico_id'         => $medSlug,
'sala_id'           => $salaId,
'tipo_id'           => $tipoId,
'area'              => $area,
```

Replace:

```php
'notas'             => $tipoLabel,
```

with:

```php
'notas'             => trim($tipoLabel . ($doctor !== '' ? ' · SigCenter doctor: ' . $doctor : '')),
```

- [ ] **Step 5: Run backend tests**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364/laravel-app
php artisan test --filter=AgendaV3ControllerTest
```

Expected: `PASS Tests\Feature\AgendaV3ControllerTest`.

- [ ] **Step 6: Run PHP syntax checks**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
php -l laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php
php -l laravel-app/tests/Feature/AgendaV3ControllerTest.php
```

Expected: both commands print `No syntax errors detected`.

- [ ] **Step 7: Commit backend fixes**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git add laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php laravel-app/tests/Feature/AgendaV3ControllerTest.php
git commit -m "fix(agenda-v3): normalize real SigCenter data"
```

Expected: commit includes controller and test updates.

## Task 3: Make Frontend Catalog Helpers Null-Safe

**Files:**
- Modify: `public/agenda-v3/components.jsx`
- Modify: `laravel-app/resources/views/agenda_v2/components.jsx`

- [ ] **Step 1: Replace catalog helpers in `components.jsx`**

Replace the lookup block:

```jsx
const byId = (arr, id) => arr.find((x) => x.id === id) || null;
const medico = (id) => byId(AG.MEDICOS, id);
const sala   = (id) => byId(AG.SALAS, id);
const tipo   = (id) => byId(AG.TIPOS, id);
const area   = (id) => byId(AG.AREAS, id);
const sede   = (id) => byId(AG.SEDES, id);
const estado = (id) => byId(AG.ESTADOS, id);
```

with:

```jsx
const byId = (arr, id) => (arr || []).find((x) => x && x.id === id) || null;
const fallbackArea = { id: "consulta", label: "Consulta", icon: "mdi-stethoscope", color: "#1f9d7a", bg: "#dff5ee", fg: "#17654f" };
const fallbackMedico = { id: "", nombre: "Sin médico asignado", esp: "SigCenter", areas: ["consulta"], sede: "", color: "#7e8299", iniciales: "SC" };
const fallbackSala = { id: "", sede: "", label: "Sin sala asignada", tipo: "consultorio", area: "consulta", cap: 1 };
const fallbackTipo = { id: "", label: "Atención SigCenter", area: "consulta", dur: 20, requiereTipoSala: ["consultorio"] };
const fallbackSede = { id: "", label: "Sede", abrev: "", apertura: "08:00", cierre: "18:00" };
const fallbackEstado = { id: "agendado", label: "Agendado", icon: "mdi-calendar-blank-outline", tone: "info", desc: "" };

const medico = (id) => byId(AG.MEDICOS, id) || { ...fallbackMedico, id: id || "" };
const sala   = (id) => byId(AG.SALAS, id) || { ...fallbackSala, id: id || "" };
const tipo   = (id) => byId(AG.TIPOS, id) || { ...fallbackTipo, id: id || "" };
const area   = (id) => byId(AG.AREAS, id) || { ...fallbackArea, id: id || "consulta" };
const sede   = (id) => byId(AG.SEDES, id) || { ...fallbackSede, id: id || "" };
const estado = (id) => byId(AG.ESTADOS, id) || { ...fallbackEstado, id: id || "agendado" };
```

- [ ] **Step 2: Verify fallback helper names exist**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
php -r '$s=file_get_contents("public/agenda-v3/components.jsx"); foreach(["fallbackMedico","fallbackSala","fallbackTipo","fallbackSede","fallbackEstado"] as $needle){ if(strpos($s,$needle)===false){ fwrite(STDERR,"missing $needle\n"); exit(1); }} echo "components fallbacks present\n";'
```

Expected: `components fallbacks present`.

- [ ] **Step 3: Mirror the same helper change in Blade asset copy**

Apply the same helper block to `laravel-app/resources/views/agenda_v2/components.jsx` so duplicate static copies do not drift.

- [ ] **Step 4: Commit helper changes**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git add public/agenda-v3/components.jsx laravel-app/resources/views/agenda_v2/components.jsx
git commit -m "fix(agenda-v3): make catalog lookups null safe"
```

Expected: commit includes only the component helper files that changed.

## Task 4: Embed React App In MedForge Layout

**Files:**
- Modify: `public/agenda-v3/app.jsx`
- Modify: `public/agenda-v3/shell.css`
- Modify: `laravel-app/resources/views/agenda_v2/app.jsx`
- Modify: `laravel-app/resources/views/agenda_v2/shell.css`

- [ ] **Step 1: Add URL view helper in `app.jsx`**

Near the top of `public/agenda-v3/app.jsx`, after `TWEAK_DEFAULTS`, add:

```jsx
const VALID_VIEWS = ["agenda", "flowboard", "miagenda", "config", "spec"];

function viewFromUrl() {
  const params = new URLSearchParams(window.location.search || "");
  const view = params.get("view") || "agenda";
  return VALID_VIEWS.includes(view) ? view : "agenda";
}
```

Replace:

```jsx
const [view,       setView]       = React.useState("agenda");
```

with:

```jsx
const [view] = React.useState(viewFromUrl);
```

- [ ] **Step 2: Remove internal nav rendering**

Remove the `nav` array block and remove these JSX sections from the return:

```jsx
<div className="app-logo">...</div>
<div className="app-header">...</div>
<div className="app-sidebar">...</div>
```

Keep the content under `.app-content`, modals, toast, and tweaks panel.

- [ ] **Step 3: Add content-level toolbar for sede and context**

Inside `.page-head .actions`, keep existing view buttons and add the sede switch for agenda/flowboard views:

```jsx
{(view==="agenda"||view==="flowboard"||view==="miagenda"||view==="config") && (
  <div className="sede-switch">
    {AG.SEDES.map((s) => (
      <button key={s.id} className={sedeId === s.id ? "on" : ""} onClick={() => setSedeId(s.id)}>
        <i className="mdi mdi-map-marker-outline"></i>{s.label}
      </button>
    ))}
  </div>
)}
```

Keep the existing badge and `Nueva cita` button for `agenda`.

- [ ] **Step 4: Use `_dbId` and `_readonly` in write handlers**

Add this helper inside `App()` before action handlers:

```jsx
function editableDbId(id) {
  const cita = citas.find((c) => c.id === id);
  if (!cita || cita._readonly || cita._dbId === null || cita._dbId === undefined) return null;
  return cita._dbId;
}
```

In `openConsulta`, `finishConsulta`, `saveCita`, `cancelCita`, and `advance`, replace `parseInt(id.replace('C', ''))` with `editableDbId(id)` for existing rows. If `editableDbId()` returns `null`, show:

```jsx
notify("Esta cita viene de SigCenter y no se edita desde Agenda V3 todavía", "info");
return;
```

For create-new cita, keep the existing POST behavior.

- [ ] **Step 5: Convert `.app` CSS to embedded layout**

In `public/agenda-v3/shell.css`, replace:

```css
html, body, #root { height: 100%; margin: 0; }
body {
  background: var(--bg-soft);
  font-family: var(--font-body);
  color: var(--fg-1);
  -webkit-font-smoothing: antialiased;
  overflow: hidden;
}
```

with:

```css
#agenda-v3-root { min-height: calc(100vh - 140px); }
#agenda-v3-root * { box-sizing: border-box; }
body {
  font-family: var(--font-body);
  color: var(--fg-1);
  -webkit-font-smoothing: antialiased;
}
```

Replace `.app` with:

```css
.app {
  min-height: calc(100vh - 140px);
  background: var(--bg-soft);
  display: flex;
  flex-direction: column;
}
```

Remove `.app-logo`, `.app-header`, and `.app-sidebar` style blocks from `public/agenda-v3/shell.css`.

Replace:

```css
.app-content { grid-area: content; overflow: hidden; display: flex; flex-direction: column; }
```

with:

```css
.app-content { overflow: hidden; display: flex; flex-direction: column; min-height: calc(100vh - 140px); }
```

- [ ] **Step 6: Mirror app/shell changes in Blade asset copy**

Apply the same changes to:

```text
laravel-app/resources/views/agenda_v2/app.jsx
laravel-app/resources/views/agenda_v2/shell.css
```

- [ ] **Step 7: Verify duplicate shell classes are not rendered by app**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
rg -n "className=\"app-logo|className=\"app-header|className=\"app-sidebar|const nav =|setView\\(" public/agenda-v3/app.jsx
```

Expected: no matches for `app-logo`, `app-header`, `app-sidebar`, `const nav =`, or `setView(` in `public/agenda-v3/app.jsx`.

- [ ] **Step 8: Commit embedded layout changes**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git add public/agenda-v3/app.jsx public/agenda-v3/shell.css laravel-app/resources/views/agenda_v2/app.jsx laravel-app/resources/views/agenda_v2/shell.css
git commit -m "fix(agenda-v3): embed app in MedForge layout"
```

Expected: commit includes only React app and shell CSS changes.

## Task 5: Update MedForge Sidebar Links

**Files:**
- Modify: `laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php`

- [ ] **Step 1: Point menu entries to query-param views**

In `MedforgeNavigation.php`, ensure the Agenda item points to:

```php
'/v2/agenda/v3?view=agenda'
```

Ensure the Flujo de Pacientes item points to:

```php
'/v2/agenda/v3?view=flowboard'
```

Point the Mi agenda and Configuración base entries to:

```php
'/v2/agenda/v3?view=miagenda'
'/v2/agenda/v3?view=config'
```

- [ ] **Step 2: Verify sidebar routes**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
rg -n "/v2/agenda/v3(\\?view=(agenda|flowboard|miagenda|config))?" laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php
```

Expected: MedForge navigation contains query-param view links for Agenda V3.

- [ ] **Step 3: Commit navigation changes**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git add laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php
git commit -m "fix(agenda-v3): route sidebar to embedded views"
```

Expected: commit includes only `MedforgeNavigation.php`.

## Task 6: Harden Calendar, FlowBoard, And Modal Rendering

**Files:**
- Modify: `public/agenda-v3/calendar.jsx`
- Modify: `public/agenda-v3/flowboard.jsx`
- Modify: `public/agenda-v3/modals.jsx`
- Modify: `laravel-app/resources/views/agenda_v2/calendar.jsx`
- Modify: `laravel-app/resources/views/agenda_v2/flowboard.jsx`
- Modify: `laravel-app/resources/views/agenda_v2/modals.jsx`

- [ ] **Step 1: Guard week view when no doctor exists**

In `public/agenda-v3/calendar.jsx`, replace:

```jsx
const medId = fMed || AG.MEDICOS.find((m) => m.sede === sedeId).id;
const m = medico(medId);
```

with:

```jsx
const defaultMed = AG.MEDICOS.find((m) => m.sede === sedeId) || AG.MEDICOS[0] || null;
const medId = fMed || (defaultMed ? defaultMed.id : "");
const m = medico(medId);
```

Before the existing `return (` in `SemanaView`, add:

```jsx
if (!medId) {
  return (
    <Box title="Semana" icon="mdi-calendar-week">
      <div className="muted" style={{ font: "500 12.5px var(--font-body)" }}>
        No hay médicos sincronizados para mostrar la vista semanal.
      </div>
    </Box>
  );
}
```

- [ ] **Step 2: Make FlowBoard cards resilient to missing catalog values**

In `public/agenda-v3/flowboard.jsx`, replace:

```jsx
<div className="proc" style={{ color: a.fg }} onClick={() => onOpen(c.id)}>{tipo(c.tipo).label}</div>
```

with:

```jsx
<div className="proc" style={{ color: a.fg }} onClick={() => onOpen(c.id)}>{tipo(c.tipo).label || c.notas || "Atención SigCenter"}</div>
```

Replace:

```jsx
<span className="chip-tag" style={{ background: a.bg, color: a.fg, fontSize: 9.5, padding: "2px 7px" }}>{s.label}</span>
```

with:

```jsx
<span className="chip-tag" style={{ background: a.bg, color: a.fg, fontSize: 9.5, padding: "2px 7px" }}>{s.label || "Sin sala"}</span>
```

- [ ] **Step 3: Hide write buttons for read-only detail modals**

In `public/agenda-v3/modals.jsx`, inside the detail modal body after the notes block:

```jsx
{c.notas && <div className="det"><div className="l">Notas</div><div className="v" style={{ fontWeight: 400, fontSize: 12.5 }}>{c.notas}</div></div>}
```

add:

```jsx
{c._readonly && (
  <div className="validate warn">
    <i className="mdi mdi-sync"></i>
    <div><b>SigCenter</b>: esta cita es de solo lectura en Agenda V3.</div>
  </div>
)}
```

Replace the WhatsApp resend panel footer:

```jsx
<div style={{ padding: 10, borderTop: "1px solid var(--border-soft)", display: "flex", gap: 8 }}>
  <button className="btn sm btn-outline-success block" onClick={onResend}>
    <i className="mdi mdi-send-outline"></i>{c.whatsapp === "na" ? "Enviar confirmación" : "Reenviar confirmación"}
  </button>
</div>
```

with:

```jsx
{!c._readonly && (
  <div style={{ padding: 10, borderTop: "1px solid var(--border-soft)", display: "flex", gap: 8 }}>
    <button className="btn sm btn-outline-success block" onClick={onResend}>
      <i className="mdi mdi-send-outline"></i>{c.whatsapp === "na" ? "Enviar confirmación" : "Reenviar confirmación"}
    </button>
  </div>
)}
```

Replace the modal footer:

```jsx
<div className="modal-f">
  <button className="btn btn-outline-danger" onClick={onCancel} disabled={finalState}><i className="mdi mdi-close-circle-outline"></i>Cancelar cita</button>
  <button className="btn btn-outline-secondary" onClick={() => onEdit(c)}><i className="mdi mdi-pencil-outline"></i>Editar / reagendar</button>
  {onConsulta && c.estado !== "cancelado" && c.estado !== "ausente" && (
    <button className="btn btn-outline-success" onClick={() => onConsulta(c.id)}><i className="mdi mdi-file-document-edit-outline"></i>{c.estado === "completado" || c.hcLlena ? "Ver historia clínica" : "Abrir consulta"}</button>
  )}
  <div className="spacer"></div>
  {!finalState && idx < 4 && (
    <button className="btn btn-primary" onClick={() => onAdvance(c.id)}>
      <i className="mdi mdi-arrow-right-circle-outline"></i>Avanzar a «{estado(order[idx + 1]).label}»
    </button>
  )}
</div>
```

with:

```jsx
<div className="modal-f">
  {!c._readonly && (
    <React.Fragment>
      <button className="btn btn-outline-danger" onClick={onCancel} disabled={finalState}><i className="mdi mdi-close-circle-outline"></i>Cancelar cita</button>
      <button className="btn btn-outline-secondary" onClick={() => onEdit(c)}><i className="mdi mdi-pencil-outline"></i>Editar / reagendar</button>
      {onConsulta && c.estado !== "cancelado" && c.estado !== "ausente" && (
        <button className="btn btn-outline-success" onClick={() => onConsulta(c.id)}><i className="mdi mdi-file-document-edit-outline"></i>{c.estado === "completado" || c.hcLlena ? "Ver historia clínica" : "Abrir consulta"}</button>
      )}
      <div className="spacer"></div>
      {!finalState && idx < 4 && (
        <button className="btn btn-primary" onClick={() => onAdvance(c.id)}>
          <i className="mdi mdi-arrow-right-circle-outline"></i>Avanzar a «{estado(order[idx + 1]).label}»
        </button>
      )}
    </React.Fragment>
  )}
  {c._readonly && (
    <React.Fragment>
      <span className="muted" style={{ font: "600 12px var(--font-body)", marginRight: "auto" }}>
        <i className="mdi mdi-sync"></i> Registro SigCenter de solo lectura
      </span>
      <button className="btn btn-light" onClick={onClose}>Cerrar</button>
    </React.Fragment>
  )}
</div>
```

- [ ] **Step 4: Mirror calendar, FlowBoard, and modal changes in Blade asset copy**

Apply the same edits to:

```text
laravel-app/resources/views/agenda_v2/calendar.jsx
laravel-app/resources/views/agenda_v2/flowboard.jsx
laravel-app/resources/views/agenda_v2/modals.jsx
```

- [ ] **Step 5: Verify read-only marker and week guard are present**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
php -r '$checks=["public/agenda-v3/calendar.jsx"=>"No hay médicos sincronizados","public/agenda-v3/modals.jsx"=>"solo lectura en Agenda V3","public/agenda-v3/flowboard.jsx"=>"Atención SigCenter"]; foreach($checks as $file=>$needle){ $s=file_get_contents($file); if(strpos($s,$needle)===false){ fwrite(STDERR,"missing $needle in $file\n"); exit(1); }} echo "agenda v3 frontend hardening present\n";'
```

Expected: `agenda v3 frontend hardening present`.

- [ ] **Step 6: Commit frontend hardening**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git add public/agenda-v3/calendar.jsx public/agenda-v3/flowboard.jsx public/agenda-v3/modals.jsx laravel-app/resources/views/agenda_v2/calendar.jsx laravel-app/resources/views/agenda_v2/flowboard.jsx laravel-app/resources/views/agenda_v2/modals.jsx
git commit -m "fix(agenda-v3): harden read-only appointment UI"
```

Expected: commit includes calendar, FlowBoard, and modal changes.

## Task 7: Verify End-To-End Behavior

**Files:**
- No planned edits.

- [ ] **Step 1: Run focused backend tests**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364/laravel-app
php artisan test --filter=AgendaV3ControllerTest
```

Expected: all Agenda V3 controller tests pass.

- [ ] **Step 2: Run existing Agenda scheduling test**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364/laravel-app
php artisan test --filter=AgendaSchedulingControllerTest
```

Expected: existing Agenda scheduling test still passes.

- [ ] **Step 3: Run PHP syntax checks**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
php -l laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php
php -l laravel-app/resources/views/agenda/v3-shell.blade.php
php -l laravel-app/app/Modules/Shared/Support/MedforgeNavigation.php
```

Expected: all commands print `No syntax errors detected`.

- [ ] **Step 4: Inspect frontend source for duplicate shell**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
rg -n "app-logo|app-header|app-sidebar|Volver a MedForge" public/agenda-v3/app.jsx
```

Expected: no matches.

- [ ] **Step 5: Browser verification with local app**

Run the app using the repo's existing Laravel dev command:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364/laravel-app
php artisan serve --host=127.0.0.1 --port=8010
```

Open:

```text
http://127.0.0.1:8010/v2/agenda/v3?view=agenda
http://127.0.0.1:8010/v2/agenda/v3?view=flowboard
```

Expected:

- one MedForge header
- one MedForge sidebar
- Agenda content inside the MedForge content region
- no internal Agenda sidebar
- FlowBoard selected by query param

- [ ] **Step 6: Final git status check**

Run:

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/.worktrees/pr-364
git status --short --branch
```

Expected: clean worktree on `codex/pr-364`.
