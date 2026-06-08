# CRM Phase 0 — Procedure Rules Governance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the `crm_procedure_rules` table, its model, a seed command that bootstraps rules from existing solicitud codes, and a validation Artisan command that confirms zero unclassified codes before Phase 2 go-live.

**Architecture:** Pure infrastructure — no behavior changes to any existing CRM flow. A new migration creates the table. A new Eloquent model with a `forCodigo()` lookup method (with cache) mirrors the `CrmStageMapping` pattern. An Artisan command reads distinct `procedimiento` values from `solicitud_procedimiento` (last 90 days) and either inserts stub rules or reports coverage gaps.

**Tech Stack:** Laravel 10, MySQL, PHPUnit (SQLite in-memory for tests), `/usr/bin/php8.3-cli`

**Branch:** `feat/crm-phase0-procedure-rules` (branch off `main`, never push directly to `main`)

**Working directory:** `/home/user/MedForge/laravel-app`

**Spec reference:** `docs/superpowers/specs/2026-06-08-crm-opportunity-model-redesign.md` — sections "New Table: crm_procedure_rules" and "Phase 0 — Rule Governance"

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/2026_06_08_000001_create_crm_procedure_rules_table.php` | Create | Table DDL |
| `app/Models/CrmProcedureRule.php` | Create | Eloquent model + `forCodigo()` cache lookup |
| `app/Console/Commands/CrmSeedProcedureRules.php` | Create | Artisan command: read distinct codes → upsert stub rules |
| `app/Console/Commands/CrmValidateProcedureRules.php` | Create | Artisan command: report codes with no active rule |
| `tests/Feature/CrmProcedureRuleModelTest.php` | Create | Model cache lookup, fallback behavior |
| `tests/Feature/CrmSeedProcedureRulesCommandTest.php` | Create | Seeder command: creates stubs, skips existing, reports count |
| `tests/Feature/CrmValidateProcedureRulesCommandTest.php` | Create | Validator command: exit 0 when all covered, exit 1 + list when gaps |

---

## Task 1: Branch setup

- [ ] **1.1 Create and checkout the feature branch**

```bash
git checkout main
git pull origin main
git checkout -b feat/crm-phase0-procedure-rules
```

Expected: `Switched to a new branch 'feat/crm-phase0-procedure-rules'`

---

## Task 2: Migration

**Files:**
- Create: `database/migrations/2026_06_08_000001_create_crm_procedure_rules_table.php`

- [ ] **2.1 Create the migration file**

```bash
/usr/bin/php8.3-cli artisan make:migration create_crm_procedure_rules_table --create=crm_procedure_rules
```

Then replace its content entirely:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->string('nombre', 200);
            // 'unica' | 'recurrente' | 'diagnostico'
            $table->string('tipo', 20)->default('unica');
            // null unless tipo = 'recurrente'
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            // Future: categoria, subcategoria, especialidad, tipo_servicio
            $table->timestamps();

            $table->index('grupo_codigo');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_procedure_rules');
    }
};
```

- [ ] **2.2 Run the migration**

```bash
/usr/bin/php8.3-cli artisan migrate
```

Expected output includes: `create_crm_procedure_rules_table ........... 2ms DONE`

- [ ] **2.3 Commit**

```bash
git add database/migrations/
git commit -m "feat(crm): create crm_procedure_rules table migration"
```

---

## Task 3: Eloquent Model

**Files:**
- Create: `app/Models/CrmProcedureRule.php`

- [ ] **3.1 Write the failing test first**

Create `tests/Feature/CrmProcedureRuleModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CrmProcedureRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmProcedureRuleModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_procedure_rules');
        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->string('nombre', 200);
            $table->string('tipo', 20)->default('unica');
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });
        Cache::flush();
    }

    public function test_forCodigo_returns_rule_when_exists(): void
    {
        CrmProcedureRule::create([
            'codigo'   => 'CYP-CCA-001',
            'nombre'   => 'Facoemulsificación',
            'tipo'     => 'unica',
            'activo'   => 1,
        ]);

        $rule = CrmProcedureRule::forCodigo('CYP-CCA-001');

        $this->assertNotNull($rule);
        $this->assertSame('unica', $rule['tipo']);
        $this->assertSame(1, $rule['agrupar_por_ojo']);
        $this->assertSame(1, $rule['genera_oportunidad']);
    }

    public function test_forCodigo_returns_fallback_when_no_rule(): void
    {
        $rule = CrmProcedureRule::forCodigo('NONEXISTENT-CODE');

        $this->assertSame('unica', $rule['tipo']);
        $this->assertSame(1, $rule['agrupar_por_ojo']);
        $this->assertSame(1, $rule['genera_oportunidad']);
        $this->assertNull($rule['grupo_codigo']);
        $this->assertFalse($rule['matched']);
    }

    public function test_forCodigo_returns_fallback_when_rule_inactive(): void
    {
        CrmProcedureRule::create([
            'codigo'  => '66984',
            'nombre'  => 'Cataract surgery',
            'tipo'    => 'unica',
            'activo'  => 0,
        ]);

        $rule = CrmProcedureRule::forCodigo('66984');

        $this->assertFalse($rule['matched']);
    }

    public function test_forCodigo_caches_result(): void
    {
        CrmProcedureRule::create([
            'codigo'  => 'CYP-RVI-009',
            'nombre'  => 'Avastin intravítreo',
            'tipo'    => 'recurrente',
            'activo'  => 1,
        ]);

        CrmProcedureRule::forCodigo('CYP-RVI-009'); // first call — populates cache

        // Delete from DB; cache should still serve the result
        CrmProcedureRule::where('codigo', 'CYP-RVI-009')->delete();

        $rule = CrmProcedureRule::forCodigo('CYP-RVI-009');
        $this->assertSame('recurrente', $rule['tipo']);
    }
}
```

- [ ] **3.2 Run the test to confirm it fails**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmProcedureRuleModelTest.php --no-coverage
```

Expected: FAIL — `Class "App\Models\CrmProcedureRule" not found`

- [ ] **3.3 Create the model**

Create `app/Models/CrmProcedureRule.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CrmProcedureRule extends Model
{
    protected $table = 'crm_procedure_rules';

    protected $fillable = [
        'codigo',
        'grupo_codigo',
        'nombre',
        'tipo',
        'ventana_dias',
        'agrupar_por_ojo',
        'genera_oportunidad',
        'activo',
    ];

    protected $casts = [
        'ventana_dias'      => 'integer',
        'agrupar_por_ojo'   => 'integer',
        'genera_oportunidad'=> 'integer',
        'activo'            => 'integer',
    ];

    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Returns the active rule for a procedure code, or a conservative fallback.
     *
     * The fallback (matched=false) signals the caller that no rule exists yet.
     * Callers must never derive behavior from the code string itself.
     *
     * @return array{tipo:string, grupo_codigo:string|null, ventana_dias:int|null,
     *               agrupar_por_ojo:int, genera_oportunidad:int, matched:bool}
     */
    public static function forCodigo(string $codigo): array
    {
        $cacheKey = 'crm_procedure_rule:' . $codigo;

        return Cache::remember($cacheKey, self::CACHE_TTL, static function () use ($codigo): array {
            $row = static::query()
                ->where('codigo', $codigo)
                ->where('activo', 1)
                ->first();

            if ($row === null) {
                return self::fallback();
            }

            return [
                'tipo'              => $row->tipo,
                'grupo_codigo'      => $row->grupo_codigo,
                'ventana_dias'      => $row->ventana_dias,
                'agrupar_por_ojo'   => $row->agrupar_por_ojo,
                'genera_oportunidad'=> $row->genera_oportunidad,
                'matched'           => true,
            ];
        });
    }

    public static function clearCache(string $codigo): void
    {
        Cache::forget('crm_procedure_rule:' . $codigo);
    }

    /** Conservative fallback when no rule exists. generates_oportunidad=1, tipo=unica. */
    private static function fallback(): array
    {
        return [
            'tipo'              => 'unica',
            'grupo_codigo'      => null,
            'ventana_dias'      => null,
            'agrupar_por_ojo'   => 1,
            'genera_oportunidad'=> 1,
            'matched'           => false,
        ];
    }
}
```

- [ ] **3.4 Run tests — all four must pass**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmProcedureRuleModelTest.php --no-coverage
```

Expected: `PASS  Tests\Feature\CrmProcedureRuleModelTest` with 4 tests passing.

- [ ] **3.5 Commit**

```bash
git add app/Models/CrmProcedureRule.php tests/Feature/CrmProcedureRuleModelTest.php
git commit -m "feat(crm): add CrmProcedureRule model with forCodigo() cache lookup"
```

---

## Task 4: Seed Command

The seed command reads distinct `procedimiento` values from `solicitud_procedimiento` (last 90 days) and upserts stub rules for codes that don't yet have one. Stubs have `tipo='unica'` and `nombre=codigo` — they are placeholders to be classified manually by a coordinator.

**Files:**
- Create: `app/Console/Commands/CrmSeedProcedureRules.php`
- Create: `tests/Feature/CrmSeedProcedureRulesCommandTest.php`

- [ ] **4.1 Write the failing test**

Create `tests/Feature/CrmSeedProcedureRulesCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CrmProcedureRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmSeedProcedureRulesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_procedure_rules');
        Schema::dropIfExists('solicitud_procedimiento');

        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->string('nombre', 200);
            $table->string('tipo', 20)->default('unica');
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('procedimiento', 100)->nullable();
            $table->timestamps();
        });
    }

    public function test_creates_stub_rules_for_new_codes(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CYP-CCA-001', 'created_at' => now()],
            ['procedimiento' => '66984',        'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')
            ->assertExitCode(0);

        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => 'CYP-CCA-001', 'tipo' => 'unica']);
        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => '66984',        'tipo' => 'unica']);
        $this->assertSame(2, CrmProcedureRule::count());
    }

    public function test_skips_codes_that_already_have_a_rule(): void
    {
        CrmProcedureRule::create([
            'codigo' => 'CYP-CCA-001', 'nombre' => 'Facoemulsificación',
            'tipo'   => 'recurrente',  'activo'  => 1,
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CYP-CCA-001', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')
            ->assertExitCode(0);

        // tipo must not have been overwritten
        $this->assertSame('recurrente', CrmProcedureRule::where('codigo', 'CYP-CCA-001')->value('tipo'));
        $this->assertSame(1, CrmProcedureRule::count());
    }

    public function test_ignores_null_procedure_codes(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => null,        'created_at' => now()],
            ['procedimiento' => 'CYP-RVI-009', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')
            ->assertExitCode(0);

        $this->assertSame(1, CrmProcedureRule::count());
        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => 'CYP-RVI-009']);
    }

    public function test_ignores_codes_older_than_90_days_when_window_specified(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'OLD-CODE', 'created_at' => now()->subDays(91)],
            ['procedimiento' => 'NEW-CODE', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules', ['--days' => 90])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('crm_procedure_rules', ['codigo' => 'OLD-CODE']);
        $this->assertDatabaseHas('crm_procedure_rules',    ['codigo' => 'NEW-CODE']);
    }
}
```

- [ ] **4.2 Run the test to confirm it fails**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmSeedProcedureRulesCommandTest.php --no-coverage
```

Expected: FAIL — `Command "crm:seed-procedure-rules" not found`

- [ ] **4.3 Create the command**

Create `app/Console/Commands/CrmSeedProcedureRules.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\CrmProcedureRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmSeedProcedureRules extends Command
{
    protected $signature = 'crm:seed-procedure-rules
                            {--days=90 : Look back this many days in solicitud_procedimiento}
                            {--dry-run : Show what would be inserted without writing}';

    protected $description = 'Bootstrap crm_procedure_rules with stub entries for unclassified procedure codes';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $codes = DB::table('solicitud_procedimiento')
            ->whereNotNull('procedimiento')
            ->where('created_at', '>=', now()->subDays($days))
            ->distinct()
            ->pluck('procedimiento')
            ->filter(fn ($c) => $c !== '')
            ->values();

        if ($codes->isEmpty()) {
            $this->info('No procedure codes found in the last ' . $days . ' days.');
            return self::SUCCESS;
        }

        $existing = CrmProcedureRule::whereIn('codigo', $codes)->pluck('codigo')->flip();

        $toInsert = $codes->reject(fn ($c) => $existing->has($c));

        if ($toInsert->isEmpty()) {
            $this->info('All ' . $codes->count() . ' codes already have rules. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] Would insert' : 'Inserting') . ' ' . $toInsert->count() . ' stub rule(s):');
        $this->line($toInsert->implode(', '));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $now = now();
        $rows = $toInsert->map(fn ($codigo) => [
            'codigo'             => $codigo,
            'grupo_codigo'       => null,
            'nombre'             => $codigo, // stub — coordinator must update
            'tipo'               => 'unica',
            'ventana_dias'       => null,
            'agrupar_por_ojo'    => 1,
            'genera_oportunidad' => 1,
            'activo'             => 1,
            'created_at'         => $now,
            'updated_at'         => $now,
        ])->values()->all();

        DB::table('crm_procedure_rules')->insert($rows);

        $this->info('Done. Review and classify stub rules in crm_procedure_rules before Phase 2 activation.');

        return self::SUCCESS;
    }
}
```

- [ ] **4.4 Run tests — all four must pass**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmSeedProcedureRulesCommandTest.php --no-coverage
```

Expected: `PASS  Tests\Feature\CrmSeedProcedureRulesCommandTest` — 4 tests passing.

- [ ] **4.5 Commit**

```bash
git add app/Console/Commands/CrmSeedProcedureRules.php tests/Feature/CrmSeedProcedureRulesCommandTest.php
git commit -m "feat(crm): add crm:seed-procedure-rules command to bootstrap stub rules"
```

---

## Task 5: Validation Command

This command answers the pre-Phase-2 go/no-go question: are there any procedure codes from the last N days that have no active rule? It exits with code 1 and prints the gap list if any exist, or exits 0 if all covered.

**Files:**
- Create: `app/Console/Commands/CrmValidateProcedureRules.php`
- Create: `tests/Feature/CrmValidateProcedureRulesCommandTest.php`

- [ ] **5.1 Write the failing test**

Create `tests/Feature/CrmValidateProcedureRulesCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CrmProcedureRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmValidateProcedureRulesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_procedure_rules');
        Schema::dropIfExists('solicitud_procedimiento');

        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->string('nombre', 200);
            $table->string('tipo', 20)->default('unica');
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('procedimiento', 100)->nullable();
            $table->timestamps();
        });
    }

    public function test_exits_0_when_all_codes_have_active_rules(): void
    {
        CrmProcedureRule::create([
            'codigo' => 'CYP-CCA-001', 'nombre' => 'Faco',
            'tipo'   => 'unica',       'activo'  => 1,
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CYP-CCA-001', 'created_at' => now()],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->assertExitCode(0);
    }

    public function test_exits_1_and_lists_gaps_when_codes_have_no_rule(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'UNCLASSIFIED-CODE', 'created_at' => now()],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->expectsOutputToContain('UNCLASSIFIED-CODE')
            ->assertExitCode(1);
    }

    public function test_inactive_rules_count_as_gaps(): void
    {
        CrmProcedureRule::create([
            'codigo' => '66984', 'nombre' => 'Cataract',
            'tipo'   => 'unica', 'activo'  => 0, // inactive
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => '66984', 'created_at' => now()],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->expectsOutputToContain('66984')
            ->assertExitCode(1);
    }

    public function test_ignores_null_and_old_codes(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => null,        'created_at' => now()],
            ['procedimiento' => 'OLD-CODE',  'created_at' => now()->subDays(91)],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->assertExitCode(0);
    }
}
```

- [ ] **5.2 Run the test to confirm it fails**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmValidateProcedureRulesCommandTest.php --no-coverage
```

Expected: FAIL — `Command "crm:validate-procedure-rules" not found`

- [ ] **5.3 Create the command**

Create `app/Console/Commands/CrmValidateProcedureRules.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmValidateProcedureRules extends Command
{
    protected $signature = 'crm:validate-procedure-rules
                            {--days=90 : Look-back window in days}';

    protected $description = 'List procedure codes from solicitud_procedimiento with no active crm_procedure_rules entry. Must return 0 gaps before Phase 2 activation.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $gaps = DB::table('solicitud_procedimiento as sp')
            ->selectRaw('DISTINCT sp.procedimiento')
            ->leftJoin('crm_procedure_rules as cpr', function ($join): void {
                $join->on('cpr.codigo', '=', 'sp.procedimiento')
                     ->where('cpr.activo', '=', 1);
            })
            ->whereNotNull('sp.procedimiento')
            ->where('sp.procedimiento', '!=', '')
            ->where('sp.created_at', '>=', now()->subDays($days))
            ->whereNull('cpr.id')
            ->pluck('procedimiento');

        if ($gaps->isEmpty()) {
            $this->info('✓ All procedure codes in the last ' . $days . ' days have active rules. Ready for Phase 2.');
            return self::SUCCESS;
        }

        $this->error($gaps->count() . ' procedure code(s) have no active rule (Phase 2 is NOT safe to activate):');
        foreach ($gaps as $codigo) {
            $this->line('  - ' . $codigo);
        }
        $this->newLine();
        $this->line('Run: php artisan crm:seed-procedure-rules --dry-run');
        $this->line('Then classify each stub in crm_procedure_rules before activating CRM_OPPORTUNITY_MODEL=intent');

        return self::FAILURE;
    }
}
```

- [ ] **5.4 Run tests — all four must pass**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmValidateProcedureRulesCommandTest.php --no-coverage
```

Expected: `PASS  Tests\Feature\CrmValidateProcedureRulesCommandTest` — 4 tests passing.

- [ ] **5.5 Commit**

```bash
git add app/Console/Commands/CrmValidateProcedureRules.php tests/Feature/CrmValidateProcedureRulesCommandTest.php
git commit -m "feat(crm): add crm:validate-procedure-rules command for Phase 2 go/no-go check"
```

---

## Task 6: Full test suite

- [ ] **6.1 Run all three test files together**

```bash
/usr/bin/php8.3-cli artisan test tests/Feature/CrmProcedureRuleModelTest.php tests/Feature/CrmSeedProcedureRulesCommandTest.php tests/Feature/CrmValidateProcedureRulesCommandTest.php --no-coverage
```

Expected: 12 tests, 0 failures.

- [ ] **6.2 Run the full feature suite to check for regressions**

```bash
/usr/bin/php8.3-cli artisan test --testsuite=Feature --no-coverage 2>&1 | tail -10
```

Expected: all tests pass (no new failures).

---

## Task 7: Push and open PR

- [ ] **7.1 Push the branch**

```bash
git push -u origin feat/crm-phase0-procedure-rules
```

- [ ] **7.2 Open a draft PR targeting `main`**

Title: `feat(crm): Phase 0 — procedure rules governance`

Body:
```
## Summary
- Creates `crm_procedure_rules` table (migration)
- Adds `CrmProcedureRule` model with `forCodigo()` cache lookup and conservative fallback
- Adds `crm:seed-procedure-rules` command: bootstraps stub rules from existing solicitud codes
- Adds `crm:validate-procedure-rules` command: Phase 2 go/no-go gate (must exit 0 before CRM_OPPORTUNITY_MODEL=intent)

## No behavior changes
Zero changes to existing CRM flows. This is pure infrastructure (Phase 0 of spec).

## Test plan
- [ ] `php artisan test tests/Feature/CrmProcedureRuleModelTest.php` — 4 tests pass
- [ ] `php artisan test tests/Feature/CrmSeedProcedureRulesCommandTest.php` — 4 tests pass
- [ ] `php artisan test tests/Feature/CrmValidateProcedureRulesCommandTest.php` — 4 tests pass
- [ ] Full feature suite passes with no regressions
- [ ] Run `crm:seed-procedure-rules --dry-run` on staging and review code list
- [ ] Run `crm:validate-procedure-rules` on staging; classify stubs until it exits 0

Spec: docs/superpowers/specs/2026-06-08-crm-opportunity-model-redesign.md
```
