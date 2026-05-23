# Sede + Subespecialidad Multi-select Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace free-text `sede` and `subespecialidad` inputs in the user form with checkboxes backed by stable slug/ID keys, and update the `whatsapp_sigcenter_doctor_catalog` sync to derive catalog rows from those stable keys — eliminating string-matching fragility without changing how the bot queries availability.

**Architecture:** A new `config/medforge.php` file becomes the single source of truth for subspecialties (slug → label + catalog_key) and sedes (id → name). `users.sede` stores comma-separated sede IDs ("1,16"); `users.subespecialidad` stores comma-separated slugs ("segmento_anterior,glaucoma"). The catalog sync translates slugs to `catalog_key` values so the existing bot and availability sync continue to work without modification. A data migration converts existing string values to the new format.

**Tech Stack:** Laravel 10, PHP 8.2, Blade, Vite, vanilla JS (no framework), MySQL, Artisan console commands.

---

## Key Design Decisions

| Column | Old value | New value |
|---|---|---|
| `users.sede` | `"VILLACLUB"` / `"CEIBOS"` / `"CEIBOSVILLACLUB"` | `"1"` / `"16"` / `"1,16"` |
| `users.subespecialidad` | `"oftalmologo general"` | `"segmento_anterior"` |
| `catalog.subespecialidad` | `"oftalmologo general"` | **unchanged** — sync maps slug→catalog_key |

The bot and availability sync query `catalog.subespecialidad` with `'oftalmologo general'` — this stays identical because the config maps `segmento_anterior → catalog_key: 'oftalmologo general'`.

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `config/medforge.php` | **Create** | Canonical subspecialty and sede maps |
| `database/migrations/2026_05_22_000001_migrate_users_sede_subespecialidad_to_ids.php` | **Create** | Data migration: text → IDs/slugs |
| `app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php` | **Modify** | Read `sede[]` / `subespecialidad[]` arrays; pass config to view |
| `resources/views/usuarios/v2-form.blade.php` | **Modify** | Replace text inputs with checkbox groups |
| `resources/js/v2/user-edit.js` | **Modify** | Toggle subespecialidad checkbox group visibility |
| `routes/console.php` | **Modify** | Rewrite catalog sync: remove `expandSedes()`, parse IDs and slugs |

---

## Task 1: Create `config/medforge.php`

**Files:**
- Create: `laravel-app/config/medforge.php`

- [ ] **Step 1: Create the config file**

```php
<?php

/**
 * MedForge — domain constants shared across the application.
 *
 * subspecialties:
 *   Key   = stable slug stored in users.subespecialidad
 *   label = display name shown in the form UI
 *   catalog_key = value stored in whatsapp_sigcenter_doctor_catalog.subespecialidad
 *                 (must match what bot services filter on; backward-compatible)
 *
 * sedes:
 *   Key   = integer sede_id stored in users.sede (comma-separated if multiple)
 *   Value = display name shown in form and stored in catalog.sede_nombre
 */
return [

    'subspecialties' => [
        'segmento_anterior'           => ['label' => 'Segmento Anterior',           'catalog_key' => 'oftalmologo general'],
        'glaucoma'                    => ['label' => 'Glaucoma',                    'catalog_key' => 'glaucoma'],
        'retina_vitreo'               => ['label' => 'Retina y Vítreo',             'catalog_key' => 'retina y vitreo'],
        'oculoplastia'                => ['label' => 'Oculoplástia',               'catalog_key' => 'oculoplastia'],
        'oftalmopediatria'            => ['label' => 'Oftalmopediatría',            'catalog_key' => 'oftalmopediatria'],
        'cornea_refractiva'           => ['label' => 'Córnea y Cirugía Refractiva', 'catalog_key' => 'cornea y cirugia refractiva'],
        'oncologia_ocular'            => ['label' => 'Oncología Ocular',            'catalog_key' => 'oncologia ocular'],
        'contactologia_baja_vision'   => ['label' => 'Contactología y Baja Visión', 'catalog_key' => 'contactologia y baja vision'],
    ],

    'sedes' => [
        1  => 'Villa Club',
        16 => 'Ceibos',
    ],

];
```

- [ ] **Step 2: Verify config loads**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
php artisan tinker --execute="dd(config('medforge.sedes'), config('medforge.subspecialties'));"
```

Expected: array with 2 sedes and 8 subspecialties printed, then exit.

- [ ] **Step 3: Commit**

```bash
git add config/medforge.php
git commit -m "feat: add medforge config with subspecialty and sede canonical maps"
```

---

## Task 2: Data migration — text values → IDs/slugs

**Files:**
- Create: `laravel-app/database/migrations/2026_05_22_000001_migrate_users_sede_subespecialidad_to_ids.php`

> **Context:** `users.sede` currently stores strings like `"VILLACLUB"`, `"CEIBOS"`, `"CEIBOSVILLACLUB"`. We convert to comma-separated IDs. `users.subespecialidad` currently stores `"oftalmologo general"`; we convert to `"segmento_anterior"`. Column types remain `VARCHAR/TEXT` — only data changes.

- [ ] **Step 1: Create the migration file**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
php artisan make:migration migrate_users_sede_subespecialidad_to_ids
```

This creates `database/migrations/YYYY_MM_DD_HHMMSS_migrate_users_sede_subespecialidad_to_ids.php`. Open the file and replace its contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sede: text → comma-separated IDs ────────────────────────────
        // Normalize by stripping whitespace/dashes/underscores and uppercasing,
        // then map to IDs. Values not matching any pattern are left untouched
        // (they will be ignored by the catalog sync until manually corrected).

        // Both sedes
        DB::statement("
            UPDATE users
            SET sede = '1,16'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(sede), ' ', ''), '-', ''), '_', ''))
                  IN ('CEIBOSVILLACLUB', 'VILLACLUBYCEIBOS')
        ");

        // Villa Club only
        DB::statement("
            UPDATE users
            SET sede = '1'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(sede), ' ', ''), '-', ''), '_', ''))
                  = 'VILLACLUB'
        ");

        // Ceibos only
        DB::statement("
            UPDATE users
            SET sede = '16'
            WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(sede), ' ', ''), '-', ''), '_', ''))
                  = 'CEIBOS'
        ");

        // ── Subespecialidad: legacy string → slug ────────────────────────
        DB::statement("
            UPDATE users
            SET subespecialidad = 'segmento_anterior'
            WHERE LOWER(TRIM(subespecialidad)) = 'oftalmologo general'
        ");
    }

    public function down(): void
    {
        // Reverse: slugs → legacy text (approximate; exact original casing may differ)

        DB::statement("
            UPDATE users
            SET subespecialidad = 'oftalmologo general'
            WHERE subespecialidad = 'segmento_anterior'
        ");

        DB::statement("
            UPDATE users
            SET sede = 'CEIBOSVILLACLUB'
            WHERE sede = '1,16'
        ");

        DB::statement("
            UPDATE users
            SET sede = 'VILLACLUB'
            WHERE sede = '1'
        ");

        DB::statement("
            UPDATE users
            SET sede = 'CEIBOS'
            WHERE sede = '16'
        ");
    }
};
```

- [ ] **Step 2: Run migration (will touch real data — check row count first)**

```bash
# Preview: how many users would be affected?
php artisan tinker --execute="
    echo DB::table('users')->whereNotNull('sede')->where('sede','<>','')->count() . ' users with sede\n';
    echo DB::table('users')->where('subespecialidad','oftalmologo general')->count() . ' with legacy subespecialidad\n';
"

# Run migration
php artisan migrate
```

Expected: Migration runs without errors. Check output says `1 migration` ran.

- [ ] **Step 3: Verify data**

```bash
php artisan tinker --execute="
    echo 'Sede values after migration:\n';
    DB::table('users')->select('sede',DB::raw('count(*) as n'))->whereNotNull('sede')->where('sede','<>','')->groupBy('sede')->orderBy('n','desc')->get()->each(fn(\$r) => print \$r->sede.' -> '.\$r->n.'\n'));
    echo '\nSub values after migration:\n';
    DB::table('users')->select('subespecialidad',DB::raw('count(*) as n'))->whereNotNull('subespecialidad')->where('subespecialidad','<>','')->groupBy('subespecialidad')->orderBy('n','desc')->get()->each(fn(\$r) => print \$r->subespecialidad.' -> '.\$r->n.'\n'));
"
```

Expected: sede values are now digits/comma-separated (`"1"`, `"16"`, `"1,16"`). Subespecialidad shows `"segmento_anterior"`, not `"oftalmologo general"`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: migrate users.sede to IDs and users.subespecialidad to slugs"
```

---

## Task 3: Update controller — read arrays, pass config to view

**Files:**
- Modify: `laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php`

> **Context:** The form now POSTs `sede[]` (array of integer IDs) and `subespecialidad[]` (array of slugs) instead of single strings. The controller must join them with commas before storing. The view needs the config maps.

- [ ] **Step 1: Update `buildUserIdentityPayload` — change sede and subespecialidad reading**

Find the two lines (around line 518–520) that read sede and subespecialidad as single strings:

```php
'sede' => trim((string) $request->input('sede', '')),
```
and
```php
'subespecialidad' => trim((string) $request->input('subespecialidad', '')),
```

Replace both with array-reading logic:

```php
'sede' => implode(',', array_values(array_filter(
    array_map('trim', (array) $request->input('sede', []))
))),
```
```php
'subespecialidad' => implode(',', array_values(array_filter(
    array_map('trim', (array) $request->input('subespecialidad', []))
))),
```

- [ ] **Step 2: Add config data to `renderForm()` view return**

Inside `renderForm()`, in the array passed to `view('usuarios.v2-form', [...])`, add two new keys after `'canAssignSuperuser'`:

```php
'subspecialties' => config('medforge.subspecialties', []),
'sedes_config'   => config('medforge.sedes', []),
```

- [ ] **Step 3: Verify PHP syntax**

```bash
php -l app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php
git commit -m "feat: read sede[]/subespecialidad[] as arrays in controller, pass config to form view"
```

---

## Task 4: Update Blade — replace text inputs with checkbox groups

**Files:**
- Modify: `laravel-app/resources/views/usuarios/v2-form.blade.php`

> **Context:** The form currently has `<input type="text">` for sede and subespecialidad. We replace both with checkbox groups. Subespecialidad group is hidden unless especialidad = Cirujano Oftalmólogo. The `$user['sede']` now holds comma-separated IDs ("1,16"); `$user['subespecialidad']` holds comma-separated slugs.

- [ ] **Step 1: Extend the `@php` block at the top of the file to declare new variables**

Inside the existing `@php` block (around line 1–60), after the line declaring `$isCreate`, add:

```php
/* ── Multi-select config ───────────────────────────────────────────── */
$subspecialties  = $subspecialties ?? [];
$sedesConfig     = $sedesConfig    ?? [];
$selectedSedes   = array_filter(array_map('trim', explode(',', (string) ($user['sede'] ?? ''))));
$selectedSubs    = array_filter(array_map('trim', explode(',', (string) ($user['subespecialidad'] ?? ''))));
$isOftalmologo   = ($user['especialidad'] ?? '') === 'Cirujano Oftalmólogo';
```

- [ ] **Step 2: Replace the sede `<input type="text">` block**

Find (around line 367–373):

```blade
{{-- Sede --}}
<div>
    <label class="form-label" for="sede">Sede</label>
    <input type="text"
           name="sede"
           id="sede"
           class="form-control"
           value="{!! $fieldValue('sede') !!}">
</div>
```

Replace with:

```blade
{{-- Sede (multi-select checkboxes) --}}
<div class="col-full">
    <label class="form-label mb-1">Sede</label>
    <div class="d-flex flex-wrap gap-3">
        @foreach($sedesConfig as $sedeId => $sedeName)
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="sede[]"
                   id="sede_{{ $sedeId }}"
                   value="{{ $sedeId }}"
                   {{ in_array((string) $sedeId, $selectedSedes) ? 'checked' : '' }}>
            <label class="form-check-label" for="sede_{{ $sedeId }}">{{ $sedeName }}</label>
        </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 3: Replace the subespecialidad `<input type="text">` block**

Find (around line 375–383):

```blade
{{-- Subespecialidad --}}
<div>
    <label class="form-label" for="subespecialidad">Subespecialidad</label>
    <input type="text"
           name="subespecialidad"
           id="subespecialidad"
           class="form-control"
           value="{!! $fieldValue('subespecialidad') !!}">
    <small class="text-muted">Solo para Cirujano Oftalmólogo.</small>
</div>
```

Replace with:

```blade
{{-- Subespecialidad (multi-select checkboxes, only for Cirujano Oftalmólogo) --}}
<div class="col-full" id="subespecialidad-group" @if(!$isOftalmologo) hidden @endif>
    <label class="form-label mb-1">Subespecialidad</label>
    <small class="text-muted d-block mb-2">Solo para Cirujano Oftalmólogo.</small>
    <div class="perm-grid">
        @foreach($subspecialties as $slug => $sub)
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="subespecialidad[]"
                   id="sub_{{ $slug }}"
                   value="{{ $slug }}"
                   {{ in_array($slug, $selectedSubs) ? 'checked' : '' }}>
            <label class="form-check-label" for="sub_{{ $slug }}">{{ $sub['label'] }}</label>
        </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 4: Build assets and open the form in a browser to verify visually**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
npm run build 2>&1 | tail -10
```

Expected: Build completes with no errors. Open a user edit form: checkboxes for Sede show (Villa Club / Ceibos). For a Cirujano Oftalmólogo user, the 8 subespecialidad checkboxes appear; for other roles, the group is hidden.

- [ ] **Step 5: Commit**

```bash
git add resources/views/usuarios/v2-form.blade.php
git commit -m "feat: replace sede/subespecialidad text inputs with checkbox groups in v2-form"
```

---

## Task 5: Update JS — toggle subespecialidad checkbox group

**Files:**
- Modify: `laravel-app/resources/js/v2/user-edit.js`

> **Context:** The current JS has `toggleSubespecialidad()` which disables a text `<input>` and clears its value. Now we show/hide a `<div id="subespecialidad-group">` and uncheck all checkboxes inside it when hidden.

- [ ] **Step 1: Update the subespecialidad toggle section**

Find the entire subespecialidad block (around lines 180–194):

```javascript
/* ── Subespecialidad enable/disable ─────────────────────────────────── */
var especialidadSelect   = document.getElementById('especialidad');
var subespecialidadInput = document.getElementById('subespecialidad');

function toggleSubespecialidad() {
    if (!especialidadSelect || !subespecialidadInput) return;
    var isOftalmologo = especialidadSelect.value === 'Cirujano Oftalmólogo';
    subespecialidadInput.disabled = !isOftalmologo;
    if (!isOftalmologo) subespecialidadInput.value = '';
}

if (especialidadSelect) {
    especialidadSelect.addEventListener('change', toggleSubespecialidad);
    toggleSubespecialidad();
}
```

Replace it with:

```javascript
/* ── Subespecialidad group show/hide ────────────────────────────────── */
var especialidadSelect      = document.getElementById('especialidad');
var subespecialidadGroup    = document.getElementById('subespecialidad-group');
var subespecialidadCheckboxes = Array.from(
    document.querySelectorAll('input[name="subespecialidad[]"]')
);

function toggleSubespecialidad() {
    if (!especialidadSelect) return;
    var isOftalmologo = especialidadSelect.value === 'Cirujano Oftalmólogo';

    if (subespecialidadGroup) {
        subespecialidadGroup.toggleAttribute('hidden', !isOftalmologo);
    }

    // Uncheck all when hiding so hidden values are not submitted
    if (!isOftalmologo) {
        subespecialidadCheckboxes.forEach(function (cb) { cb.checked = false; });
    }
}

if (especialidadSelect) {
    especialidadSelect.addEventListener('change', toggleSubespecialidad);
    toggleSubespecialidad(); // apply on page load
}
```

- [ ] **Step 2: Verify JS syntax and rebuild**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
node --input-type=module < resources/js/v2/user-edit.js 2>&1 || \
  node -e "require('fs').readFileSync('resources/js/v2/user-edit.js','utf8')" 2>&1 | head -5
npm run build 2>&1 | tail -10
```

Expected: No JS syntax errors; build succeeds.

- [ ] **Step 3: Smoke test in browser**

Open a user form in the browser:
1. Change especialidad to something other than "Cirujano Oftalmólogo" → subespecialidad group disappears
2. Change back to "Cirujano Oftalmólogo" → group reappears with checkboxes
3. Check multiple subespecialidades + sede checkboxes → save → verify the values are preserved on reload

- [ ] **Step 4: Commit**

```bash
git add resources/js/v2/user-edit.js
git commit -m "feat: update user-edit.js to toggle subespecialidad checkbox group visibility"
```

---

## Task 6: Rewrite catalog sync command

**Files:**
- Modify: `laravel-app/routes/console.php`

> **Context:** The `whatsapp:sigcenter-doctor-catalog-sync` command (lines ~677–807) contains an `expandSedes()` closure that parses text like "VILLACLUB" into sede_id arrays. This must be replaced by:
> 1. A `parseSedes()` closure that splits "1,16" into ID arrays using the medforge config
> 2. A `parseSubs()` closure that splits "segmento_anterior,glaucoma" into catalog_key arrays
>
> The catalog `subespecialidad` column continues to store `catalog_key` values (e.g., `"oftalmologo general"`) — so the bot and availability sync are completely unaffected.
>
> The outer loop now iterates `sedes × subs` to produce the cartesian product of rows.

- [ ] **Step 1: Locate the command in `routes/console.php`**

The command starts at line ~677:
```php
Artisan::command('whatsapp:sigcenter-doctor-catalog-sync
```
and ends at line ~807:
```php
})->purpose('Reconstruye el catálogo normalizado de médicos y sedes para el flujo de WhatsApp');
```

Replace the entire body of the closure (everything between `function (): int {` and the closing `}`) with the following:

```php
    $table = 'whatsapp_sigcenter_doctor_catalog';

    if (!Schema::hasTable($table)) {
        $this->error('La tabla whatsapp_sigcenter_doctor_catalog no existe. Ejecuta migraciones primero.');
        return 1;
    }

    if (!Schema::hasTable('users')) {
        $this->error('La tabla users no existe en la base configurada.');
        return 1;
    }

    // ── Config maps ─────────────────────────────────────────────────────
    /** @var array<int, string> $sedesMap  [sede_id => sede_nombre] */
    $sedesMap = config('medforge.sedes', []);

    /** @var array<string, array{label: string, catalog_key: string}> $subspecialtiesMap */
    $subspecialtiesMap = config('medforge.subspecialties', []);

    // ── Parse helpers ────────────────────────────────────────────────────

    /**
     * "1,16" → [['sede_id'=>'1','sede_nombre'=>'Villa Club'], ['sede_id'=>'16','sede_nombre'=>'Ceibos']]
     */
    $parseSedes = static function (string $rawSede) use ($sedesMap): array {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $rawSede))) as $id) {
            $intId = (int) $id;
            if (isset($sedesMap[$intId])) {
                $result[] = ['sede_id' => (string) $intId, 'sede_nombre' => $sedesMap[$intId]];
            }
        }
        return $result;
    };

    /**
     * "segmento_anterior,glaucoma" → ['oftalmologo general', 'glaucoma']  (catalog_key values)
     */
    $parseSubs = static function (string $rawSub) use ($subspecialtiesMap): array {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $rawSub))) as $slug) {
            if (isset($subspecialtiesMap[$slug])) {
                $result[] = $subspecialtiesMap[$slug]['catalog_key'];
            }
        }
        return $result;
    };

    $nullableString = static function (mixed $value, int $maxLength): ?string {
        $string = trim((string) $value);
        return $string === '' ? null : mb_substr($string, 0, $maxLength, 'UTF-8');
    };

    $nullableText = static function (mixed $value): ?string {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    };

    // ── Fetch source rows ────────────────────────────────────────────────
    $rows = DB::table('users')
        ->select(['id', 'nombre', 'email', 'profile_photo', 'especialidad', 'subespecialidad', 'id_trabajador', 'sede'])
        ->whereNotNull('id_trabajador')
        ->whereNotNull('subespecialidad')
        ->where('subespecialidad', '<>', '')
        ->where(function ($query): void {
            $query->where('especialidad', 'Cirujano Oftalmólogo')
                ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
        })
        ->orderBy('id')
        ->get();

    $now = now();
    $payload = [];
    $ignoredRows = [];

    foreach ($rows as $row) {
        $sedes = $parseSedes((string) ($row->sede ?? ''));
        $subs  = $parseSubs((string) ($row->subespecialidad ?? ''));

        if ($sedes === [] || $subs === []) {
            $ignoredRows[] = sprintf(
                'id=%s sede="%s" sub="%s"',
                $row->id ?? '?',
                $row->sede ?? '',
                $row->subespecialidad ?? ''
            );
            continue;
        }

        foreach ($sedes as $sede) {
            foreach ($subs as $catalogKey) {
                $key = implode('|', [
                    (string) $row->id_trabajador,
                    $catalogKey,
                    $sede['sede_id'],
                ]);

                $payload[$key] = [
                    'source_user_id'      => $row->id !== null ? (int) $row->id : null,
                    'trabajador_id'       => trim((string) $row->id_trabajador),
                    'doctor_nombre'       => trim((string) ($row->nombre ?? '')),
                    'doctor_email'        => $nullableString($row->email ?? null, 191),
                    'doctor_profile_photo'=> $nullableText($row->profile_photo ?? null),
                    'especialidad'        => $nullableString($row->especialidad ?? null, 191),
                    'subespecialidad'     => $catalogKey,
                    'sede_id'             => $sede['sede_id'],
                    'sede_nombre'         => $sede['sede_nombre'],
                    'active'              => true,
                    'last_synced_at'      => $now,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }
    }

    $existingCount = DB::table($table)->count();
    $newCount      = count($payload);
    $doctorCount   = count(array_unique(array_map(
        static fn (array $item): string => (string) $item['trabajador_id'],
        array_values($payload)
    )));

    $this->table(
        ['Métrica', 'Valor'],
        [
            ['rows_from_users',     (string) $rows->count()],
            ['distinct_doctors',    (string) $doctorCount],
            ['catalog_rows_new',    (string) $newCount],
            ['catalog_rows_existing', (string) $existingCount],
            ['ignored_rows',        (string) count($ignoredRows)],
            ['mode',                (bool) $this->option('dry-run') ? 'dry-run' : 'write'],
        ]
    );

    if ($ignoredRows !== []) {
        $this->newLine();
        $this->warn('Filas ignoradas (sede o subespecialidad no reconocidas):');
        foreach ($ignoredRows as $info) {
            $this->line('- ' . $info);
        }
    }

    if ((bool) $this->option('dry-run')) {
        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    DB::transaction(function () use ($table, $payload): void {
        DB::table($table)->delete();

        foreach (array_chunk(array_values($payload), 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    });

    $this->info('Catálogo médico-sede sincronizado.');
    return 0;
```

- [ ] **Step 2: Verify PHP syntax**

```bash
php -l routes/console.php
```

Expected: `No syntax errors detected in routes/console.php`

- [ ] **Step 3: Run sync in dry-run mode and verify output**

```bash
php artisan whatsapp:sigcenter-doctor-catalog-sync --dry-run
```

Expected output example:
```
+-------------------------+-------+
| Métrica                 | Valor |
+-------------------------+-------+
| rows_from_users         | 5     |
| distinct_doctors        | 5     |
| catalog_rows_new        | 8     |   ← more rows than before (multi-sub × multi-sede)
| catalog_rows_existing   | 4     |
| ignored_rows            | 0     |
| mode                    | dry-run |
+-------------------------+-------+
Dry-run completado. No se escribieron cambios.
```

If `ignored_rows > 0`, the warning lists which users had unrecognized sede/subespecialidad values. Check `users.sede` and `users.subespecialidad` for those users in the database and correct them manually.

- [ ] **Step 4: Run sync for real**

```bash
php artisan whatsapp:sigcenter-doctor-catalog-sync
```

Expected: `Catálogo médico-sede sincronizado.` with zero ignored rows.

- [ ] **Step 5: Verify catalog contents**

```bash
php artisan tinker --execute="
    DB::table('whatsapp_sigcenter_doctor_catalog')
        ->select('doctor_nombre','subespecialidad','sede_id','sede_nombre')
        ->orderBy('doctor_nombre')
        ->get()
        ->each(fn(\$r) => print \$r->doctor_nombre.' | '.\$r->subespecialidad.' | '.\$r->sede_nombre.'\n');
"
```

Expected: Each doctor appears once per (subespecialidad × sede) combination. The `subespecialidad` column shows `catalog_key` values (e.g., `"oftalmologo general"` for Segmento Anterior doctors — same as before migration). The availability sync and bot are not broken.

- [ ] **Step 6: Commit**

```bash
git add routes/console.php
git commit -m "feat: rewrite catalog sync to parse sede IDs and subespecialidad slugs from config maps"
```

---

## Task 7: Full smoke test

> Verify the complete end-to-end flow works: form save → catalog sync → availability check.

- [ ] **Step 1: Open a Cirujano Oftalmólogo user in the edit form**

Navigate to `/usuarios/{id}/edit` for a doctor who previously had sede and subespecialidad set. Verify:
- Sede checkboxes show Villa Club and/or Ceibos checked correctly
- Subespecialidad group is visible with Segmento Anterior (or other) checked
- All 8 subespecialidad options are displayed

- [ ] **Step 2: Change selections and save**

Check both sedes + add Glaucoma as a second subespecialidad. Submit the form.

Expected: No validation errors. On reload, the form shows the updated selections.

- [ ] **Step 3: Verify database after save**

```bash
php artisan tinker --execute="
    \$u = DB::table('users')->where('id', YOUR_USER_ID)->first();
    echo 'sede: ' . \$u->sede . '\n';
    echo 'sub:  ' . \$u->subespecialidad . '\n';
"
```

Expected:
```
sede: 1,16
sub:  segmento_anterior,glaucoma
```

- [ ] **Step 4: Re-run catalog sync and verify expanded rows**

```bash
php artisan whatsapp:sigcenter-doctor-catalog-sync
```

Then check catalog for that doctor — should now have 4 rows (2 sedes × 2 subespecialidades):

```bash
php artisan tinker --execute="
    DB::table('whatsapp_sigcenter_doctor_catalog')
        ->where('trabajador_id', 'TRABAJADOR_ID')
        ->get(['doctor_nombre','subespecialidad','sede_nombre'])
        ->each(fn(\$r) => print \$r->doctor_nombre.' | '.\$r->subespecialidad.' | '.\$r->sede_nombre.'\n');
"
```

Expected:
```
Dr. Nombre | oftalmologo general | Villa Club
Dr. Nombre | oftalmologo general | Ceibos
Dr. Nombre | glaucoma            | Villa Club
Dr. Nombre | glaucoma            | Ceibos
```

- [ ] **Step 5: Verify availability sync still works**

```bash
php artisan whatsapp:sigcenter-availability-sync --dry-run --days=3
```

Expected: Runs without errors. Finds doctors via `catalog.subespecialidad = 'oftalmologo general'` (unchanged) and reports candidate rows.

- [ ] **Step 6: Final commit**

```bash
git add -A
git status   # confirm only expected files changed
git commit -m "feat: sede/subespecialidad multi-select with stable IDs/slugs — complete"
```

---

## Self-Review Checklist

**Spec coverage:**
- ✅ Sede multi-select checkboxes in form (Task 4)
- ✅ Subespecialidad multi-select checkboxes in form (Task 4)
- ✅ Slugs in `users.subespecialidad` (Tasks 2, 3)
- ✅ IDs in `users.sede` (Tasks 2, 3)
- ✅ Catalog sync reads IDs + slugs, translates to catalog_key (Task 6)
- ✅ Bot/availability sync unchanged (catalog.subespecialidad still stores 'oftalmologo general') (Tasks 6, 7)
- ✅ Data migration for existing values (Task 2)
- ✅ Subespecialidad group hides when not Cirujano Oftalmólogo (Task 5)
- ✅ Config is single source of truth (Task 1)

**Placeholder scan:** None — all steps contain actual code.

**Type consistency:** `parseSedes` returns `array{sede_id: string, sede_nombre: string}[]` used consistently in Task 6. `parseSubs` returns `string[]` (catalog_key values) used consistently.
