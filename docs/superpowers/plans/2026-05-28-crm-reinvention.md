# CRM Reinvention — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rediseñar el CRM de una oportunidad-por-registro-clínico a una oportunidad-por-paciente, con pipeline de 7 etapas, dos fases de propiedad (operativa → comercial), escalación automática configurable, y UI con el design system de MedForge.

**Architecture:** DB migrations agregan phase/last_activity_at/escalation_at a crm_opportunities y source_id/source_type a crm_activities. CrmOpportunityService.upsertFromEvent() reemplaza createFromEvent() — crea la opp si no existe, agrega actividad si ya existe. CrmEscalationService + comando crm:escalate corren diario via Laravel Scheduler. crm:consolidate-opportunities deduplica las 21K opps existentes. El React UI usa CSS variables de medforge-design-system.css en lugar de clases Tailwind hardcodeadas.

**Tech Stack:** PHP 8.2, Laravel 10, PHPUnit (tests), React 18 + TypeScript, Vite, CSS variables MedForge (no Tailwind genérico para el panel CRM)

---

## File Map

**New files:**
- `laravel-app/database/migrations/2026_05_28_200000_add_phase_to_crm_opportunities.php`
- `laravel-app/database/migrations/2026_05_28_200001_add_source_to_crm_activities.php`
- `laravel-app/database/migrations/2026_05_28_200002_unique_contact_id_crm_opportunities.php` *(run AFTER crm:consolidate-opportunities)*
- `laravel-app/app/Modules/CRM/Services/CrmEscalationService.php`
- `laravel-app/app/Console/Commands/CrmEscalate.php`
- `laravel-app/app/Console/Commands/CrmConsolidateOpportunities.php`
- `laravel-app/app/Console/Kernel.php`
- `laravel-app/resources/css/crm-panel.css`

**Modified files:**
- `laravel-app/app/Models/CrmOpportunity.php` — new stage/phase constants, fillable, casts, scopes
- `laravel-app/app/Models/CrmActivity.php` — new types, source fields
- `laravel-app/config/crm.php` — add escalation keys
- `laravel-app/app/Modules/CRM/Services/CrmActivityService.php` — add logClinical()
- `laravel-app/app/Modules/CRM/Services/CrmOpportunityService.php` — add upsertFromEvent(), update changeStage()
- `laravel-app/app/Modules/CRM/Services/CrmStatsService.php` — use last_activity_at, add phase stats
- `laravel-app/app/Modules/CRM/Http/Controllers/CrmOpportunityController.php` — phase filter, last_activity_at sort
- `laravel-app/app/Listeners/CrmOpportunityListener.php` — use upsertFromEvent()
- `laravel-app/resources/views/crm/panel.blade.php` — add crm-panel.css
- `laravel-app/resources/js/crm/types.ts` — Stage, Phase, updated interfaces
- `laravel-app/resources/js/crm/api.ts` — phase filter
- `laravel-app/resources/js/crm/App.tsx` — MedForge layout
- `laravel-app/resources/js/crm/components/StatsBar.tsx` — MedForge
- `laravel-app/resources/js/crm/components/FilterChips.tsx` — MedForge + phase filter
- `laravel-app/resources/js/crm/components/OpportunityTable.tsx` — MedForge table
- `laravel-app/resources/js/crm/components/OpportunityRow.tsx` — MedForge + phase badge + escalation
- `laravel-app/resources/js/crm/components/StageSelector.tsx` — 7 etapas MedForge
- `laravel-app/resources/js/crm/components/DetailPanel.tsx` — MedForge + phase + clinical timeline
- `laravel-app/resources/js/crm/components/ActivityTimeline.tsx` — clinical types + MedForge colors
- `laravel-app/tests/Feature/CrmOpportunityListenerTest.php` — update setUp schema + add upsert test

---

## Task 1: DB Migrations — phase, escalation columns + activity source fields

**Files:**
- Create: `laravel-app/database/migrations/2026_05_28_200000_add_phase_to_crm_opportunities.php`
- Create: `laravel-app/database/migrations/2026_05_28_200001_add_source_to_crm_activities.php`

- [ ] **Step 1: Create migration for crm_opportunities new columns**

```php
// laravel-app/database/migrations/2026_05_28_200000_add_phase_to_crm_opportunities.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            // phase: operational (ejecutivos) | commercial (equipo comercial)
            $table->string('phase', 20)->default('operational')->after('stage');
            // Last time any activity (clinical or manual) was registered
            $table->timestamp('last_activity_at')->nullable()->after('assigned_to');
            // When this opportunity should auto-escalate to commercial
            $table->timestamp('escalation_at')->nullable()->after('last_activity_at');

            $table->index(['phase']);
            $table->index(['escalation_at']);
            $table->index(['last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropIndex(['phase']);
            $table->dropIndex(['escalation_at']);
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn(['phase', 'last_activity_at', 'escalation_at']);
        });
    }
};
```

- [ ] **Step 2: Create migration for crm_activities source fields**

```php
// laravel-app/database/migrations/2026_05_28_200001_add_source_to_crm_activities.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            // source_id + source_type: link to the clinical record (consulta_examenes, solicitud_procedimiento, etc.)
            $table->unsignedBigInteger('source_id')->nullable()->after('user_id');
            $table->string('source_type', 100)->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->dropColumn(['source_id', 'source_type']);
        });
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
cd laravel-app && php artisan migrate
```

Expected output: `Migrating: 2026_05_28_200000_add_phase_to_crm_opportunities` and `2026_05_28_200001_add_source_to_crm_activities` — both show "Migrated".

- [ ] **Step 4: Verify columns exist**

```bash
cd laravel-app && php artisan tinker --execute="DB::select('DESCRIBE crm_opportunities');" | grep -E 'phase|last_activity|escalation'
```

Expected: rows for `phase`, `last_activity_at`, `escalation_at`.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/database/migrations/2026_05_28_200000_add_phase_to_crm_opportunities.php \
        laravel-app/database/migrations/2026_05_28_200001_add_source_to_crm_activities.php
git commit -m "feat(crm): add phase, escalation_at, last_activity_at to crm_opportunities; source fields to crm_activities"
```

---

## Task 2: Update PHP Models + Config

**Files:**
- Modify: `laravel-app/app/Models/CrmOpportunity.php`
- Modify: `laravel-app/app/Models/CrmActivity.php`
- Modify: `laravel-app/config/crm.php`

- [ ] **Step 1: Rewrite CrmOpportunity model**

```php
// laravel-app/app/Models/CrmOpportunity.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmOpportunity extends Model
{
    protected $table = 'crm_opportunities';

    protected $fillable = [
        'contact_id', 'title', 'stage', 'phase',
        'source', 'source_id', 'source_type',
        'assigned_to', 'lost_reason',
        'last_activity_at', 'escalation_at',
    ];

    protected $casts = [
        'contact_id'      => 'integer',
        'source_id'       => 'integer',
        'assigned_to'     => 'integer',
        'last_activity_at'=> 'datetime',
        'escalation_at'   => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // Stages — Phase 1: operational
    public const STAGE_NUEVO        = 'nuevo';
    public const STAGE_CONTACTADO   = 'contactado';
    public const STAGE_EN_EVALUACION = 'en_evaluacion';
    // Stages — Phase 2: commercial
    public const STAGE_PROPUESTA    = 'propuesta';
    public const STAGE_COMPROMETIDO = 'comprometido';
    public const STAGE_GANADO       = 'ganado';
    public const STAGE_PERDIDO      = 'perdido';

    public const STAGES = [
        self::STAGE_NUEVO, self::STAGE_CONTACTADO, self::STAGE_EN_EVALUACION,
        self::STAGE_PROPUESTA, self::STAGE_COMPROMETIDO,
        self::STAGE_GANADO, self::STAGE_PERDIDO,
    ];

    public const COMMERCIAL_STAGES = [
        self::STAGE_PROPUESTA, self::STAGE_COMPROMETIDO,
        self::STAGE_GANADO, self::STAGE_PERDIDO,
    ];

    public const PHASE_OPERATIONAL = 'operational';
    public const PHASE_COMMERCIAL  = 'commercial';

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id')->orderBy('created_at', 'desc');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_GANADO, self::STAGE_PERDIDO]);
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeByPhase($query, string $phase)
    {
        return $query->where('phase', $phase);
    }

    /** Opportunities that should have auto-escalated already. */
    public function scopePendingEscalation($query)
    {
        return $query->where('phase', self::PHASE_OPERATIONAL)
            ->whereNotNull('escalation_at')
            ->where('escalation_at', '<=', now());
    }

    /** Opportunities with no activity for longer than the given hours. */
    public function scopeStaleFor($query, int $hours)
    {
        return $query->active()->where(function ($q) use ($hours): void {
            $q->where('last_activity_at', '<', now()->subHours($hours))
              ->orWhereNull('last_activity_at');
        });
    }
}
```

- [ ] **Step 2: Rewrite CrmActivity model**

```php
// laravel-app/app/Models/CrmActivity.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $table = 'crm_activities';
    public $timestamps = false;

    protected $fillable = [
        'opportunity_id', 'type', 'description',
        'user_id', 'source_id', 'source_type',
    ];

    protected $casts = [
        'opportunity_id' => 'integer',
        'user_id'        => 'integer',
        'source_id'      => 'integer',
        'created_at'     => 'datetime',
    ];

    public const TYPE_NOTA         = 'nota';
    public const TYPE_LLAMADA      = 'llamada';
    public const TYPE_CAMBIO_ETAPA = 'cambio_etapa';
    public const TYPE_EMAIL        = 'email';
    public const TYPE_EXAMEN       = 'examen';
    public const TYPE_SOLICITUD    = 'solicitud';
    public const TYPE_WHATSAPP     = 'whatsapp';

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }
}
```

- [ ] **Step 3: Update config/crm.php**

```php
// laravel-app/config/crm.php
<?php

return [
    'urgency_threshold_hours' => [
        'whatsapp' => env('CRM_URGENCY_WA_HOURS', 6),
        'default'  => env('CRM_URGENCY_DEFAULT_HOURS', 48),
    ],

    'escalacion' => [
        // Days stuck in 'contactado' before escalating to commercial
        'dias_contactado'     => (int) env('CRM_ESC_DIAS_CONTACTADO', 7),
        // Days stuck in 'en_evaluacion' before escalating to commercial
        'dias_en_evaluacion'  => (int) env('CRM_ESC_DIAS_EN_EVALUACION', 14),
    ],
];
```

- [ ] **Step 4: Verify PHP parse**

```bash
cd laravel-app && php -l app/Models/CrmOpportunity.php && php -l app/Models/CrmActivity.php && php -l config/crm.php
```

Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Models/CrmOpportunity.php laravel-app/app/Models/CrmActivity.php laravel-app/config/crm.php
git commit -m "feat(crm): new stage/phase constants, activity clinical types, escalation config"
```

---

## Task 3: CrmActivityService — add logClinical()

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Services/CrmActivityService.php`

- [ ] **Step 1: Write failing test**

Add to `laravel-app/tests/Feature/CrmOpportunityListenerTest.php` at the end of the class (before the closing `}`):

```php
public function test_log_clinical_creates_activity_with_source(): void
{
    $contact = \App\Models\CrmContact::query()->create([
        'name' => 'Test', 'phone' => '0999000001', 'resolution' => 'provisional', 'source' => 'examen',
    ]);
    $opp = \App\Models\CrmOpportunity::query()->create([
        'contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'examen',
    ]);

    $service = app(\App\Modules\CRM\Services\CrmActivityService::class);
    $activity = $service->logClinical(
        opportunityId: $opp->id,
        type: \App\Models\CrmActivity::TYPE_EXAMEN,
        description: 'OCT Macular realizado',
        sourceId: 42,
        sourceType: 'consulta_examenes',
    );

    $this->assertEquals('examen', $activity->type);
    $this->assertEquals(42, $activity->source_id);
    $this->assertEquals('consulta_examenes', $activity->source_type);
}
```

- [ ] **Step 2: Update test setUp to include new columns**

In `CrmOpportunityListenerTest::setUp()`, the inline `Schema::create('crm_opportunities', ...)` block must include the new columns. Find the block and replace it:

```php
Schema::create('crm_opportunities', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('contact_id')->index();
    $table->string('title', 255);
    $table->string('stage', 30)->default('nuevo');
    $table->string('phase', 20)->default('operational');
    $table->string('source', 30)->default('manual');
    $table->unsignedBigInteger('source_id')->nullable();
    $table->string('source_type', 255)->nullable();
    $table->unsignedBigInteger('assigned_to')->nullable();
    $table->string('lost_reason', 500)->nullable();
    $table->timestamp('last_activity_at')->nullable();
    $table->timestamp('escalation_at')->nullable();
    $table->timestamps();
});
Schema::create('crm_activities', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('opportunity_id')->index();
    $table->string('type', 30)->default('nota');
    $table->text('description');
    $table->unsignedBigInteger('user_id')->nullable();
    $table->unsignedBigInteger('source_id')->nullable();
    $table->string('source_type', 100)->nullable();
    $table->timestamp('created_at')->useCurrent();
});
```

- [ ] **Step 3: Run test to verify it fails**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php --filter=test_log_clinical
```

Expected: `Error: Call to undefined method ... logClinical()`

- [ ] **Step 4: Add logClinical() to CrmActivityService**

```php
// laravel-app/app/Modules/CRM/Services/CrmActivityService.php
<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmActivity;

class CrmActivityService
{
    public function log(
        int $opportunityId,
        string $type,
        string $description,
        ?int $userId = null,
        ?int $sourceId = null,
        ?string $sourceType = null,
    ): CrmActivity {
        return CrmActivity::query()->create([
            'opportunity_id' => $opportunityId,
            'type'           => $type,
            'description'    => $description,
            'user_id'        => $userId,
            'source_id'      => $sourceId,
            'source_type'    => $sourceType,
        ]);
    }

    public function logStageChange(int $opportunityId, string $fromStage, string $toStage, ?int $userId = null): CrmActivity
    {
        return $this->log(
            opportunityId: $opportunityId,
            type: CrmActivity::TYPE_CAMBIO_ETAPA,
            description: "Etapa cambiada de '{$fromStage}' a '{$toStage}'",
            userId: $userId,
        );
    }

    public function logSystemEvent(int $opportunityId, string $description): CrmActivity
    {
        return $this->log(
            opportunityId: $opportunityId,
            type: CrmActivity::TYPE_NOTA,
            description: $description,
        );
    }

    public function logClinical(
        int $opportunityId,
        string $type,
        string $description,
        int $sourceId,
        string $sourceType,
        ?int $userId = null,
    ): CrmActivity {
        return $this->log(
            opportunityId: $opportunityId,
            type: $type,
            description: $description,
            userId: $userId,
            sourceId: $sourceId,
            sourceType: $sourceType,
        );
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php --filter=test_log_clinical
```

Expected: `PASS`

- [ ] **Step 6: Run full test suite**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add laravel-app/app/Modules/CRM/Services/CrmActivityService.php \
        laravel-app/tests/Feature/CrmOpportunityListenerTest.php
git commit -m "feat(crm): CrmActivityService.logClinical() with source_id/source_type; update test schema"
```

---

## Task 4: CrmOpportunityService — upsertFromEvent() + escalation_at logic

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Services/CrmOpportunityService.php`

- [ ] **Step 1: Write failing tests**

Add to `laravel-app/tests/Feature/CrmOpportunityListenerTest.php`:

```php
public function test_upsert_creates_opportunity_when_contact_has_none(): void
{
    $contact = \App\Models\CrmContact::query()->create([
        'name' => 'Nuevo', 'phone' => '0999000010', 'resolution' => 'provisional', 'source' => 'examen',
    ]);

    $service = app(\App\Modules\CRM\Services\CrmOpportunityService::class);
    $opp = $service->upsertFromEvent(
        contact: $contact,
        title: 'Examen: OCT Macular',
        source: 'examen',
        sourceId: 99,
        sourceType: 'consulta_examenes',
    );

    $this->assertDatabaseHas('crm_opportunities', [
        'contact_id' => $contact->id,
        'stage'      => 'nuevo',
        'phase'      => 'operational',
    ]);
    $this->assertDatabaseHas('crm_activities', [
        'opportunity_id' => $opp->id,
        'type'           => 'examen',
        'source_id'      => 99,
    ]);
}

public function test_upsert_creates_activity_when_contact_already_has_opportunity(): void
{
    $contact = \App\Models\CrmContact::query()->create([
        'name' => 'Existente', 'phone' => '0999000011', 'resolution' => 'provisional', 'source' => 'examen',
    ]);
    \App\Models\CrmOpportunity::query()->create([
        'contact_id' => $contact->id, 'title' => 'Primera vez', 'stage' => 'contactado', 'source' => 'examen',
    ]);

    $before = \App\Models\CrmOpportunity::query()->where('contact_id', $contact->id)->count();

    $service = app(\App\Modules\CRM\Services\CrmOpportunityService::class);
    $service->upsertFromEvent(
        contact: $contact,
        title: 'Examen: Angiografía',
        source: 'examen',
        sourceId: 100,
        sourceType: 'consulta_examenes',
    );

    // No new opportunity created
    $this->assertEquals($before, \App\Models\CrmOpportunity::query()->where('contact_id', $contact->id)->count());
    // Activity created
    $this->assertDatabaseHas('crm_activities', ['source_id' => 100, 'type' => 'examen']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php --filter=test_upsert
```

Expected: `Error: Call to undefined method ... upsertFromEvent()`

- [ ] **Step 3: Rewrite CrmOpportunityService**

```php
// laravel-app/app/Modules/CRM/Services/CrmOpportunityService.php
<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrmOpportunityService
{
    public function __construct(
        private readonly CrmActivityService $activityService,
    ) {}

    /**
     * Source → initial stage mapping (only for new opportunities).
     */
    private const SOURCE_ENTRY_STAGE = [
        'whatsapp'  => CrmOpportunity::STAGE_NUEVO,
        'solicitud' => CrmOpportunity::STAGE_NUEVO,
        'examen'    => CrmOpportunity::STAGE_NUEVO,
        'manual'    => CrmOpportunity::STAGE_NUEVO,
    ];

    /**
     * Clinical source → activity type mapping.
     */
    private const SOURCE_ACTIVITY_TYPE = [
        'whatsapp'  => CrmActivity::TYPE_WHATSAPP,
        'solicitud' => CrmActivity::TYPE_SOLICITUD,
        'examen'    => CrmActivity::TYPE_EXAMEN,
        'manual'    => CrmActivity::TYPE_NOTA,
    ];

    /**
     * Creates opportunity if contact has none; otherwise adds a clinical activity.
     * This is the main entry point from the listener.
     */
    public function upsertFromEvent(
        CrmContact $contact,
        string $title,
        string $source,
        ?int $sourceId = null,
        ?string $sourceType = null,
        ?int $assignedTo = null,
    ): CrmOpportunity {
        return DB::transaction(function () use ($contact, $title, $source, $sourceId, $sourceType, $assignedTo): CrmOpportunity {
            $existing = CrmOpportunity::query()->where('contact_id', $contact->id)->first();

            if ($existing instanceof CrmOpportunity) {
                // Contact already has an opportunity — add activity and update last_activity_at
                $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
                if ($sourceId !== null) {
                    $this->activityService->logClinical(
                        opportunityId: $existing->id,
                        type: $activityType,
                        description: $title,
                        sourceId: $sourceId,
                        sourceType: $sourceType ?? '',
                    );
                } else {
                    $this->activityService->logSystemEvent($existing->id, $title);
                }
                $this->touchLastActivity($existing);
                return $existing;
            }

            // No opportunity yet — create it
            $stage = self::SOURCE_ENTRY_STAGE[$source] ?? CrmOpportunity::STAGE_NUEVO;
            $escalationAt = $this->computeEscalationAt($stage);

            $opp = CrmOpportunity::query()->create([
                'contact_id'      => $contact->id,
                'title'           => $title,
                'stage'           => $stage,
                'phase'           => CrmOpportunity::PHASE_OPERATIONAL,
                'source'          => $source,
                'source_id'       => $sourceId,
                'source_type'     => $sourceType,
                'assigned_to'     => $assignedTo,
                'last_activity_at'=> now(),
                'escalation_at'   => $escalationAt,
            ]);

            $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
            if ($sourceId !== null) {
                $this->activityService->logClinical(
                    opportunityId: $opp->id,
                    type: $activityType,
                    description: $title,
                    sourceId: $sourceId,
                    sourceType: $sourceType ?? '',
                );
            } else {
                $this->activityService->logSystemEvent($opp->id, "Oportunidad creada desde {$source}");
            }

            return $opp;
        });
    }

    /**
     * Changes stage, handles phase transition, and recalculates escalation_at.
     */
    public function changeStage(
        CrmOpportunity $opportunity,
        string $newStage,
        ?int $userId = null,
        ?string $lostReason = null,
    ): CrmOpportunity {
        if (!in_array($newStage, CrmOpportunity::STAGES, true)) {
            throw new RuntimeException("Etapa inválida: {$newStage}");
        }

        $fromStage = $opportunity->stage;

        DB::transaction(function () use ($opportunity, $newStage, $lostReason, $userId, $fromStage): void {
            $opportunity->stage = $newStage;

            if ($newStage === CrmOpportunity::STAGE_PERDIDO && $lostReason !== null) {
                $opportunity->lost_reason = $lostReason;
            }

            // Auto-transition to commercial phase when reaching propuesta or beyond
            if (in_array($newStage, CrmOpportunity::COMMERCIAL_STAGES, true)) {
                $opportunity->phase        = CrmOpportunity::PHASE_COMMERCIAL;
                $opportunity->escalation_at = null; // No more auto-escalation needed
            } else {
                $opportunity->escalation_at = $this->computeEscalationAt($newStage);
            }

            $opportunity->last_activity_at = now();
            $opportunity->save();

            $this->activityService->logStageChange($opportunity->id, $fromStage, $newStage, $userId);
        });

        return $opportunity->fresh();
    }

    /**
     * Assigns the opportunity to an agent and updates last_activity_at.
     */
    public function assign(CrmOpportunity $opportunity, int $userId): CrmOpportunity
    {
        $opportunity->assigned_to      = $userId;
        $opportunity->last_activity_at = now();
        $opportunity->save();
        return $opportunity;
    }

    /**
     * Escalates to commercial phase (called by CrmEscalationService).
     */
    public function escalateToCommercial(CrmOpportunity $opportunity): void
    {
        DB::transaction(function () use ($opportunity): void {
            $opportunity->phase        = CrmOpportunity::PHASE_COMMERCIAL;
            $opportunity->escalation_at = null;
            $opportunity->save();

            $daysSince = (int) $opportunity->last_activity_at?->diffInDays(now()) ?? 0;
            $this->activityService->logSystemEvent(
                $opportunity->id,
                "Escalado automáticamente a Comercial — sin actividad por {$daysSince} días",
            );
        });
    }

    private function touchLastActivity(CrmOpportunity $opportunity): void
    {
        $opportunity->last_activity_at = now();
        // Also refresh escalation_at since there was activity
        $opportunity->escalation_at = $this->computeEscalationAt($opportunity->stage);
        $opportunity->save();
    }

    private function computeEscalationAt(string $stage): ?\Carbon\Carbon
    {
        $days = match ($stage) {
            CrmOpportunity::STAGE_CONTACTADO    => (int) config('crm.escalacion.dias_contactado', 7),
            CrmOpportunity::STAGE_EN_EVALUACION => (int) config('crm.escalacion.dias_en_evaluacion', 14),
            default                              => null,
        };

        return $days !== null ? now()->addDays($days) : null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php --filter=test_upsert
```

Expected: both tests `PASS`.

- [ ] **Step 5: Run full listener test suite**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add laravel-app/app/Modules/CRM/Services/CrmOpportunityService.php \
        laravel-app/tests/Feature/CrmOpportunityListenerTest.php
git commit -m "feat(crm): upsertFromEvent() — creates opp or adds activity; phase transition + escalation_at logic"
```

---

## Task 5: CrmEscalationService + crm:escalate command

**Files:**
- Create: `laravel-app/app/Modules/CRM/Services/CrmEscalationService.php`
- Create: `laravel-app/app/Console/Commands/CrmEscalate.php`

- [ ] **Step 1: Write failing test**

Create `laravel-app/tests/Feature/CrmEscalationServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmEscalationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmEscalationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_activities', 'crm_opportunities', 'crm_contacts'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name', 255);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('cedula', 30)->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual');
            $table->timestamps();
        });
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('title', 255);
            $table->string('stage', 30)->default('nuevo');
            $table->string('phase', 20)->default('operational');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('escalation_at')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_escalate_moves_overdue_operational_to_commercial(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'Test', 'phone' => '0999000001', 'resolution' => 'provisional', 'source' => 'examen',
        ]);
        $opp = CrmOpportunity::query()->create([
            'contact_id'   => $contact->id,
            'title'        => 'Overdue',
            'stage'        => CrmOpportunity::STAGE_CONTACTADO,
            'phase'        => CrmOpportunity::PHASE_OPERATIONAL,
            'source'       => 'examen',
            'escalation_at'=> now()->subDay(),
        ]);

        app(CrmEscalationService::class)->run(dryRun: false);

        $opp->refresh();
        $this->assertEquals(CrmOpportunity::PHASE_COMMERCIAL, $opp->phase);
        $this->assertNull($opp->escalation_at);
        $this->assertDatabaseHas('crm_activities', [
            'opportunity_id' => $opp->id,
            'type'           => 'cambio_etapa',
        ]);
    }

    public function test_escalate_dry_run_does_not_mutate(): void
    {
        $contact = CrmContact::query()->create([
            'name' => 'DryTest', 'phone' => '0999000002', 'resolution' => 'provisional', 'source' => 'examen',
        ]);
        CrmOpportunity::query()->create([
            'contact_id'   => $contact->id,
            'title'        => 'Dry',
            'stage'        => CrmOpportunity::STAGE_CONTACTADO,
            'phase'        => CrmOpportunity::PHASE_OPERATIONAL,
            'source'       => 'examen',
            'escalation_at'=> now()->subDay(),
        ]);

        app(CrmEscalationService::class)->run(dryRun: true);

        $this->assertDatabaseHas('crm_opportunities', ['phase' => CrmOpportunity::PHASE_OPERATIONAL]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd laravel-app && php artisan test tests/Feature/CrmEscalationServiceTest.php
```

Expected: `Error: Class "App\Modules\CRM\Services\CrmEscalationService" not found`

- [ ] **Step 3: Create CrmEscalationService**

```php
// laravel-app/app/Modules/CRM/Services/CrmEscalationService.php
<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmOpportunity;

class CrmEscalationService
{
    public function __construct(
        private readonly CrmOpportunityService $opportunityService,
    ) {}

    /**
     * Finds all operational opportunities past their escalation_at and promotes them to commercial.
     *
     * @return array{escalated: int, skipped: int}
     */
    public function run(bool $dryRun = false): array
    {
        $pending = CrmOpportunity::query()
            ->pendingEscalation()
            ->with('contact')
            ->get();

        $escalated = 0;

        foreach ($pending as $opp) {
            if (!$dryRun) {
                $this->opportunityService->escalateToCommercial($opp);
            }
            $escalated++;
        }

        return ['escalated' => $escalated, 'skipped' => 0];
    }
}
```

- [ ] **Step 4: Create crm:escalate command**

```php
// laravel-app/app/Console/Commands/CrmEscalate.php
<?php

namespace App\Console\Commands;

use App\Modules\CRM\Services\CrmEscalationService;
use Illuminate\Console\Command;

class CrmEscalate extends Command
{
    protected $signature = 'crm:escalate {--dry-run : Reporta sin escribir}';
    protected $description = 'Escala oportunidades operativas inactivas al equipo comercial';

    public function __construct(
        private readonly CrmEscalationService $escalationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribirá nada.');
        }

        ['escalated' => $escalated] = $this->escalationService->run($dryRun);

        $this->info("Oportunidades escaladas: {$escalated}");

        return 0;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd laravel-app && php artisan test tests/Feature/CrmEscalationServiceTest.php
```

Expected: both tests `PASS`.

- [ ] **Step 6: Verify command runs**

```bash
cd laravel-app && php artisan crm:escalate --dry-run
```

Expected: `Modo dry-run — no se escribirá nada.` and `Oportunidades escaladas: 0`

- [ ] **Step 7: Commit**

```bash
git add laravel-app/app/Modules/CRM/Services/CrmEscalationService.php \
        laravel-app/app/Console/Commands/CrmEscalate.php \
        laravel-app/tests/Feature/CrmEscalationServiceTest.php
git commit -m "feat(crm): CrmEscalationService + crm:escalate command with dry-run"
```

---

## Task 6: crm:consolidate-opportunities command + UNIQUE migration

**Files:**
- Create: `laravel-app/app/Console/Commands/CrmConsolidateOpportunities.php`
- Create: `laravel-app/database/migrations/2026_05_28_200002_unique_contact_id_crm_opportunities.php`

- [ ] **Step 1: Create the consolidation command**

```php
// laravel-app/app/Console/Commands/CrmConsolidateOpportunities.php
<?php

namespace App\Console\Commands;

use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmConsolidateOpportunities extends Command
{
    protected $signature = 'crm:consolidate-opportunities
                            {--dry-run : Solo reporta, no escribe}
                            {--limit=500 : Contactos a procesar}';

    protected $description = 'Consolida múltiples oportunidades por contacto en una sola (una por paciente)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribirá nada.');
        }

        // Find contacts with more than one opportunity
        $contactIds = DB::table('crm_opportunities')
            ->select('contact_id')
            ->groupBy('contact_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->pluck('contact_id');

        $this->info("Contactos con oportunidades duplicadas: {$contactIds->count()}");

        $totalMerged = 0;

        foreach ($contactIds as $contactId) {
            $opps = CrmOpportunity::query()
                ->where('contact_id', $contactId)
                ->orderBy('created_at', 'asc')
                ->get();

            $canonical = $opps->first();
            $extras    = $opps->slice(1);

            if ($dryRun) {
                $this->line("[dry] Contact #{$contactId}: keep opp #{$canonical->id}, merge " . $extras->count() . " into it");
                $totalMerged += $extras->count();
                continue;
            }

            DB::transaction(function () use ($canonical, $extras): void {
                foreach ($extras as $extra) {
                    // Move all activities from extra to canonical
                    CrmActivity::query()
                        ->where('opportunity_id', $extra->id)
                        ->update(['opportunity_id' => $canonical->id]);

                    // Create a merge activity on canonical
                    CrmActivity::query()->create([
                        'opportunity_id' => $canonical->id,
                        'type'           => CrmActivity::TYPE_NOTA,
                        'description'    => "Registro consolidado desde opp #{$extra->id} ({$extra->source} #{$extra->source_id})",
                        'user_id'        => null,
                    ]);

                    // Update source tables to point to canonical
                    if ($extra->source_id) {
                        $this->updateSourceTable($extra->source_type, $extra->source_id, $canonical->id);
                    }

                    $extra->delete();
                }

                // Determine best stage from clinical records
                $bestStage = $this->mapStageFromClinical($canonical->contact_id);
                $canonical->stage            = $bestStage;
                $canonical->last_activity_at = $canonical->last_activity_at ?? now();
                $canonical->save();
            });

            $totalMerged += $extras->count();
        }

        $this->info("Oportunidades consolidadas/eliminadas: {$totalMerged}");
        $this->info('Si ejecutaste sin dry-run, corre: php artisan migrate (para agregar UNIQUE constraint)');

        return 0;
    }

    private function updateSourceTable(?string $sourceType, int $sourceId, int $canonicalOppId): void
    {
        $table = match ($sourceType) {
            'solicitud_procedimiento' => 'solicitud_procedimiento',
            'consulta_examenes'       => 'consulta_examenes',
            default                   => null,
        };

        if ($table !== null) {
            DB::table($table)->where('id', $sourceId)->update(['crm_opportunity_id' => $canonicalOppId]);
        }
    }

    private function mapStageFromClinical(int $contactId): string
    {
        // Check solicitud states in priority order
        $hasEnProceso = DB::table('solicitud_procedimiento')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'solicitud_procedimiento.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->where('solicitud_procedimiento.estado', 'en_proceso')
            ->exists();

        if ($hasEnProceso) {
            return CrmOpportunity::STAGE_EN_EVALUACION;
        }

        $hasAprobada = DB::table('solicitud_procedimiento')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'solicitud_procedimiento.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->where('solicitud_procedimiento.estado', 'aprobada')
            ->exists();

        if ($hasAprobada) {
            return CrmOpportunity::STAGE_CONTACTADO;
        }

        // Find most recent clinical record date
        $latestExamen = DB::table('consulta_examenes')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'consulta_examenes.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->max('consulta_examenes.consulta_fecha');

        $latestSolicitud = DB::table('solicitud_procedimiento')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'solicitud_procedimiento.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->max('solicitud_procedimiento.fecha');

        $latest = max($latestExamen, $latestSolicitud);

        if ($latest === null) {
            return CrmOpportunity::STAGE_NUEVO;
        }

        $daysSince = now()->diffInDays($latest);

        if ($daysSince <= 30) {
            return CrmOpportunity::STAGE_NUEVO;
        }
        if ($daysSince <= 90) {
            return CrmOpportunity::STAGE_CONTACTADO;
        }

        // Older than 90 days — treat as historical (already served)
        return CrmOpportunity::STAGE_GANADO;
    }
}
```

- [ ] **Step 2: Create the UNIQUE constraint migration**

> **IMPORTANT:** This migration is run AFTER `crm:consolidate-opportunities` succeeds. Do not run it before.

```php
// laravel-app/database/migrations/2026_05_28_200002_unique_contact_id_crm_opportunities.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->unique('contact_id', 'crm_opportunities_contact_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropUnique('crm_opportunities_contact_id_unique');
        });
    }
};
```

- [ ] **Step 3: Test dry-run**

```bash
cd laravel-app && php artisan crm:consolidate-opportunities --dry-run --limit=10
```

Expected: shows contacts with duplicates, reports count, does not modify DB.

- [ ] **Step 4: Verify PHP parse**

```bash
cd laravel-app && php -l app/Console/Commands/CrmConsolidateOpportunities.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Console/Commands/CrmConsolidateOpportunities.php \
        laravel-app/database/migrations/2026_05_28_200002_unique_contact_id_crm_opportunities.php
git commit -m "feat(crm): crm:consolidate-opportunities — dedup 21K→5994, clinical stage mapping + UNIQUE migration"
```

---

## Task 7: CrmOpportunityListener + Console/Kernel.php scheduler

**Files:**
- Modify: `laravel-app/app/Listeners/CrmOpportunityListener.php`
- Create: `laravel-app/app/Console/Kernel.php`

- [ ] **Step 1: Update CrmOpportunityListener to use upsertFromEvent**

```php
// laravel-app/app/Listeners/CrmOpportunityListener.php
<?php

namespace App\Listeners;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;

class CrmOpportunityListener
{
    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
    ) {}

    public function handleWhatsappLeadQualified(WhatsappLeadQualified $event): void
    {
        $lead = $event->lead;

        $contact = $this->contactResolver->resolve(
            phone: $lead->wa_number,
            name: $lead->patient_full_name ?? $lead->display_name ?? $lead->wa_number,
            cedula: $lead->cedula,
            source: 'whatsapp',
        );

        $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Lead WhatsApp: ' . ($lead->motivo_baja ?: 'sin motivo registrado'),
            source: 'whatsapp',
            sourceId: $lead->id,
            sourceType: 'whatsapp_lead',
            assignedTo: $event->actorUserId,
        );
    }

    public function handleSolicitudCreada(SolicitudCreada $event): void
    {
        $data = $event->solicitudData;

        $contact = $this->contactResolver->resolve(
            phone: (string) ($data['paciente_telefono'] ?? ''),
            name: (string) ($data['paciente_nombre'] ?? 'Paciente'),
            cedula: $data['paciente_cedula'] ?? null,
            source: 'solicitud',
        );

        $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Solicitud: ' . (string) ($data['servicio'] ?? 'Servicio médico'),
            source: 'solicitud',
            sourceId: $event->solicitudId,
            sourceType: 'solicitud_procedimiento',
        );
    }

    public function handleExamenSolicitado(ExamenSolicitado $event): void
    {
        $data = $event->examenData;

        $contact = $this->contactResolver->resolve(
            phone: (string) ($data['paciente_telefono'] ?? ''),
            name: (string) ($data['paciente_nombre'] ?? 'Paciente'),
            cedula: $data['paciente_cedula'] ?? null,
            source: 'examen',
        );

        $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Examen: ' . (string) ($data['descripcion_examen'] ?? 'Examen solicitado'),
            source: 'examen',
            sourceId: $event->examenId,
            sourceType: 'consulta_examenes',
        );
    }
}
```

- [ ] **Step 2: Create Console/Kernel.php with scheduler**

```php
// laravel-app/app/Console/Kernel.php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Escalate stale operational opportunities to commercial team — runs daily at 08:00
        $schedule->command('crm:escalate')->dailyAt('08:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
```

- [ ] **Step 3: Run listener tests to verify nothing broke**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php
```

Expected: all tests pass.

- [ ] **Step 4: Verify scheduler lists the command**

```bash
cd laravel-app && php artisan schedule:list
```

Expected: shows `crm:escalate` scheduled at `08:00`.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Listeners/CrmOpportunityListener.php \
        laravel-app/app/Console/Kernel.php
git commit -m "feat(crm): listener uses upsertFromEvent(); Kernel scheduler registers crm:escalate daily at 08:00"
```

---

## Task 8: CrmStatsService + CrmOpportunityController updates

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Services/CrmStatsService.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmOpportunityController.php`

- [ ] **Step 1: Update CrmStatsService to use last_activity_at and add phase stats**

```php
// laravel-app/app/Modules/CRM/Services/CrmStatsService.php
<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\DB;

class CrmStatsService
{
    public function panelStats(): array
    {
        $escalaDias = (int) config('crm.escalacion.dias_contactado', 7);

        $active = CrmOpportunity::query()->active()->count();

        // Stale = no activity in escalaDias days AND not yet commercial
        $urgent = CrmOpportunity::query()
            ->active()
            ->where('phase', CrmOpportunity::PHASE_OPERATIONAL)
            ->staleFor($escalaDias * 24)
            ->count();

        $wonThisMonth = CrmOpportunity::query()
            ->where('stage', CrmOpportunity::STAGE_GANADO)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $avgResponseHours = DB::table('crm_activities')
            ->join('crm_opportunities', 'crm_activities.opportunity_id', '=', 'crm_opportunities.id')
            ->where('crm_activities.type', 'cambio_etapa')
            ->whereRaw("crm_activities.description LIKE '%nuevo%contactado%'")
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, crm_opportunities.created_at, crm_activities.created_at)'));

        $total = CrmOpportunity::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $won = CrmOpportunity::query()
            ->where('stage', CrmOpportunity::STAGE_GANADO)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $conversionRate = $total > 0 ? round(($won / $total) * 100) : 0;

        return [
            'urgent'          => $urgent,
            'active'          => $active,
            'won_this_month'  => $wonThisMonth,
            'avg_response_h'  => round((float) ($avgResponseHours ?? 0), 1),
            'conversion_rate' => $conversionRate,
        ];
    }

    /** @return array<string, int> */
    public function byStage(): array
    {
        return CrmOpportunity::query()
            ->active()
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->toArray();
    }

    /** @return array<string, int> */
    public function byPhase(): array
    {
        return CrmOpportunity::query()
            ->active()
            ->selectRaw('phase, COUNT(*) as total')
            ->groupBy('phase')
            ->pluck('total', 'phase')
            ->toArray();
    }
}
```

- [ ] **Step 2: Update CrmOpportunityController to support phase filter and sort by last_activity_at**

In `laravel-app/app/Modules/CRM/Http/Controllers/CrmOpportunityController.php`, update the `index()` method:

```php
public function index(Request $request): JsonResponse
{
    if (!Auth::check()) {
        return response()->json(['error' => 'Sesión expirada'], 401);
    }

    $limit  = min(max((int) $request->query('limit', 25), 1), 100);
    $offset = max((int) $request->query('offset', 0), 0);
    $stage  = trim((string) $request->query('stage', ''));
    $source = trim((string) $request->query('source', ''));
    $phase  = trim((string) $request->query('phase', ''));
    $search = trim((string) $request->query('search', ''));
    $urgent = filter_var($request->query('urgent', false), FILTER_VALIDATE_BOOLEAN);

    $query = CrmOpportunity::query()->with('contact');

    if ($stage !== '') {
        $query->where('stage', $stage);
    }
    if ($source !== '') {
        $query->where('source', $source);
    }
    if ($phase !== '') {
        $query->where('phase', $phase);
    }
    if ($search !== '') {
        $query->whereHas('contact', fn ($q) => $q->where('name', 'like', "%{$search}%")
            ->orWhere('cedula', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
        );
    }
    if ($urgent) {
        $staleDays = (int) config('crm.escalacion.dias_contactado', 7);
        $query->staleFor($staleDays * 24);
    }

    $total = $query->count();
    $rows  = $query->orderByRaw('COALESCE(last_activity_at, created_at) ASC')
        ->limit($limit)->offset($offset)->get();

    return response()->json([
        'data' => $rows,
        'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
    ]);
}
```

Also update the `show()` method to include activities with eager loading:

```php
public function show(int $id, Request $request): JsonResponse
{
    if (!Auth::check()) {
        return response()->json(['error' => 'Sesión expirada'], 401);
    }

    $opp = CrmOpportunity::query()->with(['contact', 'activities'])->findOrFail($id);

    return response()->json(['data' => $opp]);
}
```

- [ ] **Step 3: Update CrmStatsController to return phase stats**

In `laravel-app/app/Modules/CRM/Http/Controllers/CrmStatsController.php`, find the `index()` method and add `by_phase`:

```php
return response()->json([
    'data' => [
        'panel'    => $this->statsService->panelStats(),
        'by_stage' => $this->statsService->byStage(),
        'by_phase' => $this->statsService->byPhase(),
    ],
]);
```

- [ ] **Step 4: Verify PHP parse**

```bash
cd laravel-app && php -l app/Modules/CRM/Services/CrmStatsService.php && \
  php -l app/Modules/CRM/Http/Controllers/CrmOpportunityController.php && \
  php -l app/Modules/CRM/Http/Controllers/CrmStatsController.php
```

Expected: `No syntax errors detected` for all.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/CRM/Services/CrmStatsService.php \
        laravel-app/app/Modules/CRM/Http/Controllers/CrmOpportunityController.php \
        laravel-app/app/Modules/CRM/Http/Controllers/CrmStatsController.php
git commit -m "feat(crm): stats use last_activity_at; phase filter in API; byPhase() stats endpoint"
```

---

## Task 9: crm-panel.css + React types + API

**Files:**
- Create: `laravel-app/resources/css/crm-panel.css`
- Modify: `laravel-app/resources/views/crm/panel.blade.php`
- Modify: `laravel-app/resources/js/crm/types.ts`
- Modify: `laravel-app/resources/js/crm/api.ts`

- [ ] **Step 1: Create crm-panel.css with MedForge tokens**

```css
/* laravel-app/resources/css/crm-panel.css */
/* CRM Panel — uses MedForge design system variables */

/* ── Layout ─────────────────────────────────────────────────── */
.crm-panel-root {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 120px);
  background: var(--bg-soft);
}

.crm-panel-header {
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border-soft);
  padding: .875rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.crm-panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

/* ── KPI Cards ───────────────────────────────────────────────── */
.crm-kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: .75rem;
  margin-bottom: 1.25rem;
}

.crm-kpi-card {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1rem 1.125rem;
  box-shadow: var(--shadow-xs);
}

.crm-kpi-value {
  font-family: var(--font-display);
  font-size: 1.875rem;
  font-weight: 700;
  line-height: 1;
  color: var(--fg-1);
  margin-bottom: .25rem;
}

.crm-kpi-label {
  font-size: .6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--fg-mute);
}

.crm-kpi-card.urgent .crm-kpi-value { color: var(--danger); }
.crm-kpi-card.urgent { border-color: var(--danger-light); background: #fdf4f6; }

/* ── Filter Chips ────────────────────────────────────────────── */
.crm-filter-row {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.crm-chip {
  display: inline-flex;
  align-items: center;
  gap: .375rem;
  padding: .375rem .75rem;
  border-radius: var(--radius-pill);
  border: 1.5px solid var(--border);
  background: var(--bg-surface);
  color: var(--fg-2);
  font-size: .75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--dur-fast) var(--ease-out);
}

.crm-chip:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.crm-chip.active {
  border-color: var(--primary);
  background: var(--primary-fade);
  color: var(--primary);
}

/* ── Table ───────────────────────────────────────────────────── */
.crm-table-wrap {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}

.crm-table {
  width: 100%;
  border-collapse: collapse;
}

.crm-table th {
  padding: .625rem .75rem;
  background: var(--bg-soft);
  color: var(--fg-1);
  font-size: .6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  text-align: left;
  border-bottom: 1px solid var(--border-soft);
}

.crm-table td {
  padding: .6875rem .75rem;
  border-bottom: 1px solid var(--border-soft);
  color: var(--fg-2);
  vertical-align: middle;
  font-size: .8125rem;
}

.crm-table tbody tr {
  cursor: pointer;
  transition: background-color var(--dur-fast) var(--ease-out);
}

.crm-table tbody tr:hover { background: #f8fafd; }
.crm-table tbody tr.escalating { background: #fff9ec; }
.crm-table tbody tr.escalating:hover { background: #fff3d4; }
.crm-table tbody tr:last-child td { border-bottom: none; }

/* ── Stage Badges ────────────────────────────────────────────── */
.crm-stage-badge {
  display: inline-flex;
  align-items: center;
  padding: .2rem .55rem;
  border-radius: var(--radius-pill);
  font-size: .6875rem;
  font-weight: 700;
}

.crm-stage-badge.nuevo          { background: var(--info-light);    color: var(--info); }
.crm-stage-badge.contactado     { background: var(--warning-light);  color: var(--warning-hover); }
.crm-stage-badge.en_evaluacion  { background: var(--cat-optometria-bg); color: var(--cat-optometria-fg); }
.crm-stage-badge.propuesta      { background: var(--primary-fade);   color: var(--primary); }
.crm-stage-badge.comprometido   { background: var(--cat-visita-bg);  color: var(--cat-visita-fg); }
.crm-stage-badge.ganado         { background: var(--success-light);  color: var(--success); }
.crm-stage-badge.perdido        { background: var(--danger-light);   color: var(--danger); }

/* ── Phase Badges ────────────────────────────────────────────── */
.crm-phase-badge {
  display: inline-flex;
  align-items: center;
  padding: .15rem .45rem;
  border-radius: var(--radius-pill);
  font-size: .625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.crm-phase-badge.operational { background: var(--info-light);    color: var(--info); }
.crm-phase-badge.commercial  { background: var(--success-light); color: var(--success); }

/* ── Escalation warning ──────────────────────────────────────── */
.crm-escalation-warn {
  font-size: .6875rem;
  font-weight: 600;
  color: var(--warning-hover);
}

/* ── Activity Timeline ───────────────────────────────────────── */
.crm-timeline { position: relative; padding-left: 1.25rem; }
.crm-timeline::before {
  content: '';
  position: absolute;
  left: .4375rem;
  top: 0; bottom: 0;
  width: 2px;
  background: var(--border);
}

.crm-timeline-item {
  position: relative;
  margin-bottom: .75rem;
}

.crm-timeline-dot {
  position: absolute;
  left: -1.125rem;
  top: .375rem;
  width: .625rem;
  height: .625rem;
  border-radius: 50%;
}

.crm-timeline-dot.nota         { background: var(--warning); }
.crm-timeline-dot.llamada      { background: var(--info); }
.crm-timeline-dot.cambio_etapa { background: var(--primary); }
.crm-timeline-dot.email        { background: #ec4899; }
.crm-timeline-dot.examen       { background: var(--cat-examen); }
.crm-timeline-dot.solicitud    { background: var(--cat-consulta); }
.crm-timeline-dot.whatsapp     { background: #22c55e; }

.crm-timeline-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius-sm);
  padding: .5rem .75rem;
  box-shadow: var(--shadow-xs);
}

.crm-timeline-card.examen     { border-left: 3px solid var(--cat-examen); }
.crm-timeline-card.solicitud  { border-left: 3px solid var(--cat-consulta); }
.crm-timeline-card.whatsapp   { border-left: 3px solid #22c55e; }

.crm-timeline-desc { font-size: .75rem; color: var(--fg-2); line-height: 1.45; }
.crm-timeline-meta { font-size: .6875rem; color: var(--fg-mute); margin-top: .2rem; }

/* ── Detail Panel ────────────────────────────────────────────── */
.crm-detail-panel {
  position: fixed;
  inset-block: 0;
  right: 0;
  width: min(50%, 680px);
  background: var(--bg-surface);
  border-left: 1px solid var(--border);
  box-shadow: var(--shadow-md);
  z-index: 1050;
  display: flex;
  flex-direction: column;
}

.crm-detail-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}

.crm-detail-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.crm-detail-left {
  flex: 0 0 45%;
  border-right: 1px solid var(--border-soft);
  overflow-y: auto;
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.crm-detail-right {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 1.25rem;
  background: var(--bg-soft);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* ── Stage Selector ──────────────────────────────────────────── */
.crm-stage-selector { display: flex; flex-wrap: wrap; gap: .375rem; }

.crm-stage-btn {
  padding: .3rem .65rem;
  border-radius: var(--radius-pill);
  border: 1.5px solid var(--border);
  background: var(--bg-soft);
  color: var(--fg-mute);
  font-size: .6875rem;
  font-weight: 700;
  cursor: pointer;
  transition: all var(--dur-fast) var(--ease-out);
}

.crm-stage-btn:hover { border-color: var(--primary); color: var(--primary); }
.crm-stage-btn:disabled { opacity: .45; cursor: not-allowed; }

.crm-stage-btn.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

/* ── Contact info block ──────────────────────────────────────── */
.crm-contact-info {
  background: var(--bg-soft);
  border-radius: var(--radius-sm);
  padding: .625rem .875rem;
}

.crm-section-label {
  font-size: .6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--fg-mute);
  margin-bottom: .5rem;
}

/* ── Action buttons ──────────────────────────────────────────── */
.crm-action-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .375rem;
  margin-top: auto;
}
```

- [ ] **Step 2: Add crm-panel.css to panel.blade.php**

In `laravel-app/resources/views/crm/panel.blade.php`, add to `@push('styles')`:

```blade
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/crm-panel.css') }}">
    {{-- Or if using Vite: @vite('resources/css/crm-panel.css') --}}
@endpush
```

Check if the blade file already has `@push('styles')`. If it does, add the link inside it. If the file uses Vite (`@vite(...)`), use `@vite('resources/css/crm-panel.css')` instead of `asset()`.

- [ ] **Step 3: Update types.ts**

```typescript
// laravel-app/resources/js/crm/types.ts
export type Resolution = 'provisional' | 'identified' | 'linked';
export type Source = 'whatsapp' | 'solicitud' | 'examen' | 'manual';
export type Phase = 'operational' | 'commercial';
export type Stage =
  | 'nuevo'
  | 'contactado'
  | 'en_evaluacion'
  | 'propuesta'
  | 'comprometido'
  | 'ganado'
  | 'perdido';

export type ActivityType =
  | 'nota' | 'llamada' | 'cambio_etapa' | 'email'
  | 'examen' | 'solicitud' | 'whatsapp';

export interface CrmContact {
  id: number;
  patient_id: number | null;
  name: string;
  phone: string;
  email: string | null;
  cedula: string | null;
  resolution: Resolution;
  source: Source;
  created_at: string;
  updated_at: string;
}

export interface CrmActivity {
  id: number;
  opportunity_id: number;
  type: ActivityType;
  description: string;
  user_id: number | null;
  source_id: number | null;
  source_type: string | null;
  created_at: string;
}

export interface CrmOpportunity {
  id: number;
  contact_id: number;
  title: string;
  stage: Stage;
  phase: Phase;
  source: Source;
  source_id: number | null;
  source_type: string | null;
  assigned_to: number | null;
  lost_reason: string | null;
  last_activity_at: string | null;
  escalation_at: string | null;
  created_at: string;
  updated_at: string;
  contact?: CrmContact;
  activities?: CrmActivity[];
}

export interface PanelStats {
  urgent: number;
  active: number;
  won_this_month: number;
  avg_response_h: number;
  conversion_rate: number;
}

export interface ApiMeta {
  total: number;
  limit: number;
  offset: number;
}

export interface OpportunitiesResponse {
  data: CrmOpportunity[];
  meta: ApiMeta;
}
```

- [ ] **Step 4: Update api.ts to add phase filter**

```typescript
// laravel-app/resources/js/crm/api.ts
import axios from 'axios';
import type { CrmOpportunity, CrmContact, CrmActivity, OpportunitiesResponse, PanelStats } from './types';

const client = axios.create({ baseURL: '/v2/crm', headers: { 'X-Requested-With': 'XMLHttpRequest' } });

export interface OpportunityFilters {
  stage?: string;
  source?: string;
  phase?: string;
  search?: string;
  urgent?: boolean;
  limit?: number;
  offset?: number;
}

export const api = {
  opportunities: {
    list: (filters: OpportunityFilters = {}): Promise<OpportunitiesResponse> =>
      client.get('/opportunities', { params: filters }).then(r => r.data),

    get: (id: number): Promise<CrmOpportunity> =>
      client.get(`/opportunities/${id}`).then(r => r.data.data),

    update: (id: number, payload: Partial<Pick<CrmOpportunity, 'stage' | 'phase' | 'assigned_to' | 'lost_reason'>>): Promise<CrmOpportunity> =>
      client.patch(`/opportunities/${id}`, payload).then(r => r.data.data),

    addActivity: (id: number, type: string, description: string): Promise<CrmActivity> =>
      client.post(`/opportunities/${id}/activities`, { type, description }).then(r => r.data.data),
  },

  contacts: {
    update: (id: number, payload: Partial<CrmContact>): Promise<CrmContact> =>
      client.patch(`/contacts/${id}`, payload).then(r => r.data.data),

    merge: (id: number, mergeIntoId: number): Promise<CrmContact> =>
      client.post(`/contacts/${id}/merge`, { merge_into_id: mergeIntoId }).then(r => r.data.data),
  },

  stats: {
    panel: (): Promise<{ panel: PanelStats; by_stage: Record<string, number>; by_phase: Record<string, number> }> =>
      client.get('/stats').then(r => r.data.data),
  },
};
```

- [ ] **Step 5: Verify TypeScript**

```bash
cd laravel-app && npx tsc --noEmit
```

Expected: no TypeScript errors.

- [ ] **Step 6: Commit**

```bash
git add laravel-app/resources/css/crm-panel.css \
        laravel-app/resources/views/crm/panel.blade.php \
        laravel-app/resources/js/crm/types.ts \
        laravel-app/resources/js/crm/api.ts
git commit -m "feat(crm): crm-panel.css MedForge design system; update TS types (new stages, phase, last_activity_at)"
```

---

## Task 10: React components — full MedForge redesign

**Files:**
- Modify: `laravel-app/resources/js/crm/App.tsx`
- Modify: `laravel-app/resources/js/crm/components/StatsBar.tsx`
- Modify: `laravel-app/resources/js/crm/components/FilterChips.tsx`
- Modify: `laravel-app/resources/js/crm/components/OpportunityTable.tsx`
- Modify: `laravel-app/resources/js/crm/components/OpportunityRow.tsx`
- Modify: `laravel-app/resources/js/crm/components/StageSelector.tsx`
- Modify: `laravel-app/resources/js/crm/components/ActivityTimeline.tsx`
- Modify: `laravel-app/resources/js/crm/components/DetailPanel.tsx`
- Modify: `laravel-app/resources/js/crm/hooks/useOpportunities.ts`

- [ ] **Step 1: Update useOpportunities.ts to support phase filter**

```typescript
// laravel-app/resources/js/crm/hooks/useOpportunities.ts
import { useState, useEffect, useCallback } from 'react';
import { api, type OpportunityFilters } from '../api';
import type { CrmOpportunity, ApiMeta } from '../types';

export function useOpportunities(filters: OpportunityFilters) {
  const [data, setData] = useState<CrmOpportunity[]>([]);
  const [meta, setMeta] = useState<ApiMeta>({ total: 0, limit: 25, offset: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.opportunities.list(filters);
      setData(res.data);
      setMeta(res.meta);
    } catch {
      setError('No se pudo cargar las oportunidades. Verifica tu sesión.');
    } finally {
      setLoading(false);
    }
  }, [JSON.stringify(filters)]);

  useEffect(() => { void load(); }, [load]);

  return { data, meta, loading, error, refresh: load };
}
```

- [ ] **Step 2: Rewrite App.tsx with MedForge layout**

```tsx
// laravel-app/resources/js/crm/App.tsx
import React, { useState, useCallback } from 'react';
import type { CrmOpportunity, Phase } from './types';
import type { ActiveFilters } from './components/FilterChips';
import { useOpportunities } from './hooks/useOpportunities';
import { useStats } from './hooks/useStats';
import { StatsBar } from './components/StatsBar';
import { FilterChips } from './components/FilterChips';
import { OpportunityTable } from './components/OpportunityTable';
import { DetailPanel } from './components/DetailPanel';

const DEFAULT_FILTERS: ActiveFilters = { stage: '', source: '', phase: '', urgent: false, search: '' };

export default function App() {
  const [filters, setFilters] = useState<ActiveFilters>(DEFAULT_FILTERS);
  const [selected, setSelected] = useState<CrmOpportunity | null>(null);

  const apiFilters = {
    stage: filters.stage || undefined,
    source: filters.source || undefined,
    phase: filters.phase || undefined,
    urgent: filters.urgent || undefined,
    search: filters.search || undefined,
  };

  const { data, meta, loading, error, refresh } = useOpportunities(apiFilters);
  const { stats } = useStats();

  const handleFilterChange = useCallback((partial: Partial<ActiveFilters>) => {
    setFilters(f => ({ ...f, ...partial }));
  }, []);

  const handleUpdated = useCallback((updated: CrmOpportunity) => {
    setSelected(updated);
    void refresh();
  }, [refresh]);

  return (
    <div className="crm-panel-root">
      <div className="crm-panel-header">
        <div>
          <h1 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: 'var(--fg-1)', fontFamily: 'var(--font-display)' }}>
            Pipeline Comercial
          </h1>
          <p style={{ margin: 0, fontSize: '.75rem', color: 'var(--fg-mute)' }}>
            Oportunidades centralizadas — todas las fuentes
          </p>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem' }}>
          {stats && stats.urgent > 0 && (
            <span style={{
              background: 'var(--danger-light)', color: 'var(--danger)',
              fontSize: '.75rem', fontWeight: 700, padding: '.25rem .75rem', borderRadius: 'var(--radius-pill)',
            }}>
              {stats.urgent} sin contactar
            </span>
          )}
          <button className="btn btn-primary btn-sm">+ Nueva oportunidad</button>
        </div>
      </div>

      <div className="crm-panel-body">
        {error && (
          <div style={{
            marginBottom: '1rem', background: 'var(--danger-light)', border: `1px solid var(--danger)`,
            color: 'var(--danger)', padding: '.75rem 1rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
          }}>
            {error}
          </div>
        )}
        <StatsBar stats={stats} />
        <FilterChips filters={filters} total={meta.total} urgentCount={stats?.urgent ?? 0} onChange={handleFilterChange} />
        <OpportunityTable opportunities={data} loading={loading} onSelect={setSelected} />
      </div>

      {selected && (
        <DetailPanel opportunity={selected} onClose={() => setSelected(null)} onUpdated={handleUpdated} />
      )}
    </div>
  );
}
```

- [ ] **Step 3: Rewrite StatsBar.tsx with MedForge**

```tsx
// laravel-app/resources/js/crm/components/StatsBar.tsx
import React from 'react';
import type { PanelStats } from '../types';

interface Props { stats: PanelStats | null }

const CARDS = [
  { key: 'urgent',          label: 'Sin contactar',  urgent: true  },
  { key: 'active',          label: 'Activas',         urgent: false },
  { key: 'won_this_month',  label: 'Ganadas mes',     urgent: false },
  { key: 'avg_response_h',  label: 'Respuesta prom.', urgent: false },
  { key: 'conversion_rate', label: 'Conversión',      urgent: false },
] as const;

export function StatsBar({ stats }: Props) {
  return (
    <div className="crm-kpi-grid">
      {CARDS.map(({ key, label, urgent }) => (
        <div key={key} className={`crm-kpi-card${urgent ? ' urgent' : ''}`}>
          <div className="crm-kpi-value">
            {stats ? String((stats as Record<string, number>)[key]) : '—'}
            {key === 'conversion_rate' && stats ? '%' : ''}
            {key === 'avg_response_h' && stats ? 'h' : ''}
          </div>
          <div className="crm-kpi-label">{label}</div>
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 4: Rewrite FilterChips.tsx with MedForge + phase filter**

```tsx
// laravel-app/resources/js/crm/components/FilterChips.tsx
import React from 'react';
import type { Stage, Phase } from '../types';

export interface ActiveFilters {
  stage: Stage | '';
  source: string;
  phase: Phase | '';
  urgent: boolean;
  search: string;
}

interface Props {
  filters: ActiveFilters;
  total: number;
  urgentCount: number;
  onChange: (partial: Partial<ActiveFilters>) => void;
}

const STAGES: { value: Stage | ''; label: string }[] = [
  { value: '', label: 'Todas las etapas' },
  { value: 'nuevo', label: 'Nuevo' },
  { value: 'contactado', label: 'Contactado' },
  { value: 'en_evaluacion', label: 'En evaluación' },
  { value: 'propuesta', label: 'Propuesta' },
  { value: 'comprometido', label: 'Comprometido' },
  { value: 'ganado', label: 'Ganado' },
  { value: 'perdido', label: 'Perdido' },
];

export function FilterChips({ filters, total, urgentCount, onChange }: Props) {
  return (
    <div style={{ marginBottom: '1rem' }}>
      <div className="crm-filter-row" style={{ marginBottom: '.5rem' }}>
        <span style={{ fontSize: '.75rem', color: 'var(--fg-mute)', marginRight: '.25rem' }}>
          {total} oportunidades
        </span>
        {urgentCount > 0 && (
          <button
            className={`crm-chip${filters.urgent ? ' active' : ''}`}
            onClick={() => onChange({ urgent: !filters.urgent })}
          >
            ⚠ Sin contactar ({urgentCount})
          </button>
        )}
        <button
          className={`crm-chip${filters.phase === 'operational' ? ' active' : ''}`}
          onClick={() => onChange({ phase: filters.phase === 'operational' ? '' : 'operational' })}
        >
          Operativo
        </button>
        <button
          className={`crm-chip${filters.phase === 'commercial' ? ' active' : ''}`}
          onClick={() => onChange({ phase: filters.phase === 'commercial' ? '' : 'commercial' })}
        >
          Comercial
        </button>
        <input
          type="text"
          placeholder="Buscar paciente..."
          value={filters.search}
          onChange={e => onChange({ search: e.target.value })}
          style={{
            height: '2rem', padding: '.25rem .625rem', borderRadius: 'var(--radius-pill)',
            border: '1.5px solid var(--border)', fontSize: '.75rem', outline: 'none',
            background: 'var(--bg-surface)', color: 'var(--fg-1)', marginLeft: 'auto',
          }}
        />
      </div>
      <div className="crm-filter-row">
        {STAGES.map(({ value, label }) => (
          <button
            key={value}
            className={`crm-chip${filters.stage === value ? ' active' : ''}`}
            onClick={() => onChange({ stage: value as Stage | '' })}
          >
            {label}
          </button>
        ))}
      </div>
    </div>
  );
}
```

- [ ] **Step 5: Rewrite OpportunityTable.tsx**

```tsx
// laravel-app/resources/js/crm/components/OpportunityTable.tsx
import React from 'react';
import type { CrmOpportunity } from '../types';
import { OpportunityRow } from './OpportunityRow';

interface Props {
  opportunities: CrmOpportunity[];
  loading: boolean;
  onSelect: (opp: CrmOpportunity) => void;
}

const HEADERS = ['Paciente', 'Etapa', 'Fase', 'Origen', 'Última actividad', 'Acción'];

export function OpportunityTable({ opportunities, loading, onSelect }: Props) {
  return (
    <div className="crm-table-wrap">
      <table className="crm-table">
        <thead>
          <tr>
            {HEADERS.map(h => <th key={h}>{h}</th>)}
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr><td colSpan={6} style={{ textAlign: 'center', padding: '2.5rem', color: 'var(--fg-mute)', fontSize: '.8125rem' }}>
              Cargando...
            </td></tr>
          )}
          {!loading && opportunities.length === 0 && (
            <tr><td colSpan={6} style={{ textAlign: 'center', padding: '2.5rem', color: 'var(--fg-mute)', fontSize: '.8125rem' }}>
              No hay oportunidades con estos filtros
            </td></tr>
          )}
          {!loading && opportunities.map(opp => (
            <OpportunityRow key={opp.id} opp={opp} onClick={onSelect} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

- [ ] **Step 6: Rewrite OpportunityRow.tsx with MedForge + phase + escalation**

```tsx
// laravel-app/resources/js/crm/components/OpportunityRow.tsx
import React from 'react';
import type { CrmOpportunity, Stage, Source, Phase } from '../types';

const STAGE_LABEL: Record<Stage, string> = {
  nuevo: 'Nuevo', contactado: 'Contactado', en_evaluacion: 'En evaluación',
  propuesta: 'Propuesta', comprometido: 'Comprometido', ganado: 'Ganado', perdido: 'Perdido',
};

const SOURCE_LABEL: Record<Source, string> = {
  whatsapp: 'WhatsApp', solicitud: 'Solicitud', examen: 'Examen', manual: 'Manual',
};

const PHASE_LABEL: Record<Phase, string> = {
  operational: 'Operativo',
  commercial: 'Comercial',
};

const ACTION_LABEL: Partial<Record<Stage, string>> = {
  nuevo: 'Contactar', contactado: 'Avanzar', en_evaluacion: 'Avanzar', propuesta: 'Seguimiento',
};

function timeAgo(dateStr: string | null): { label: string; urgentDays: number } {
  if (!dateStr) return { label: 'Sin actividad', urgentDays: 999 };
  const diffH = (Date.now() - new Date(dateStr).getTime()) / 3_600_000;
  const days = Math.floor(diffH / 24);
  if (diffH < 1) return { label: 'hace < 1h', urgentDays: 0 };
  if (diffH < 24) return { label: `hace ${Math.floor(diffH)}h`, urgentDays: 0 };
  return { label: `hace ${days}d`, urgentDays: days };
}

function daysUntilEscalation(escalationAt: string | null): number | null {
  if (!escalationAt) return null;
  const diff = (new Date(escalationAt).getTime() - Date.now()) / 86_400_000;
  return diff > 0 ? Math.ceil(diff) : 0;
}

interface Props {
  opp: CrmOpportunity;
  onClick: (opp: CrmOpportunity) => void;
}

export function OpportunityRow({ opp, onClick }: Props) {
  const { label: timeLabel, urgentDays } = timeAgo(opp.last_activity_at);
  const daysLeft = daysUntilEscalation(opp.escalation_at);
  const isEscalating = daysLeft !== null && daysLeft <= 2 && opp.phase === 'operational';

  return (
    <tr className={isEscalating ? 'escalating' : ''} onClick={() => onClick(opp)}>
      <td>
        <div style={{ fontWeight: 700, color: 'var(--fg-1)', fontSize: '.8125rem' }}>
          {opp.contact?.name ?? '—'}
        </div>
        <div style={{ fontSize: '.6875rem', color: 'var(--fg-mute)', marginTop: '.1rem' }}>
          {opp.contact?.cedula ?? opp.contact?.phone ?? '—'}
        </div>
      </td>
      <td>
        <span className={`crm-stage-badge ${opp.stage}`}>{STAGE_LABEL[opp.stage]}</span>
      </td>
      <td>
        <span className={`crm-phase-badge ${opp.phase}`}>{PHASE_LABEL[opp.phase]}</span>
        {isEscalating && (
          <div className="crm-escalation-warn" style={{ marginTop: '.2rem' }}>
            Escala en {daysLeft}d
          </div>
        )}
      </td>
      <td style={{ color: 'var(--fg-3)', fontSize: '.75rem' }}>{SOURCE_LABEL[opp.source]}</td>
      <td style={{ fontSize: '.75rem', color: urgentDays > 7 ? 'var(--danger)' : urgentDays > 3 ? 'var(--warning-hover)' : 'var(--fg-mute)' }}>
        {timeLabel}
      </td>
      <td>
        {ACTION_LABEL[opp.stage] && (
          <button
            className="btn btn-sm btn-primary-light"
            onClick={e => { e.stopPropagation(); onClick(opp); }}
          >
            {ACTION_LABEL[opp.stage]}
          </button>
        )}
      </td>
    </tr>
  );
}
```

- [ ] **Step 7: Rewrite StageSelector.tsx with 7 stages MedForge**

```tsx
// laravel-app/resources/js/crm/components/StageSelector.tsx
import React from 'react';
import type { Stage } from '../types';

const STAGES: { value: Stage; label: string; phase: 'operational' | 'commercial' }[] = [
  { value: 'nuevo',         label: 'Nuevo',         phase: 'operational' },
  { value: 'contactado',    label: 'Contactado',    phase: 'operational' },
  { value: 'en_evaluacion', label: 'En evaluación', phase: 'operational' },
  { value: 'propuesta',     label: 'Propuesta',     phase: 'commercial'  },
  { value: 'comprometido',  label: 'Comprometido',  phase: 'commercial'  },
  { value: 'ganado',        label: 'Ganado',        phase: 'commercial'  },
  { value: 'perdido',       label: 'Perdido',       phase: 'commercial'  },
];

interface Props {
  current: Stage;
  onChange: (s: Stage) => void;
  loading?: boolean;
}

export function StageSelector({ current, onChange, loading }: Props) {
  return (
    <div className="crm-stage-selector">
      {STAGES.map(({ value, label }) => (
        <button
          key={value}
          disabled={loading}
          onClick={() => onChange(value)}
          className={`crm-stage-btn${current === value ? ' active' : ''}`}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
```

- [ ] **Step 8: Rewrite ActivityTimeline.tsx with clinical types + MedForge**

```tsx
// laravel-app/resources/js/crm/components/ActivityTimeline.tsx
import React from 'react';
import type { CrmActivity, ActivityType } from '../types';

const TYPE_LABEL: Record<ActivityType, string> = {
  nota: 'Nota', llamada: 'Llamada', cambio_etapa: 'Cambio de etapa',
  email: 'Email', examen: 'Examen', solicitud: 'Solicitud', whatsapp: 'WhatsApp',
};

function formatDate(d: string): string {
  const diff = (Date.now() - new Date(d).getTime()) / 60_000;
  if (diff < 60) return `Hace ${Math.floor(diff)} min`;
  if (diff < 1440) return `Hace ${Math.floor(diff / 60)}h`;
  return `Hace ${Math.floor(diff / 1440)}d`;
}

interface Props { activities: CrmActivity[] }

export function ActivityTimeline({ activities }: Props) {
  if (activities.length === 0) {
    return (
      <p style={{ fontSize: '.8125rem', color: 'var(--fg-mute)', textAlign: 'center', padding: '1rem 0' }}>
        Sin actividades registradas
      </p>
    );
  }
  return (
    <div className="crm-timeline">
      <div style={{ display: 'flex', flexDirection: 'column', gap: '.625rem' }}>
        {activities.map(a => (
          <div key={a.id} className="crm-timeline-item">
            <div className={`crm-timeline-dot ${a.type}`} />
            <div className={`crm-timeline-card ${['examen', 'solicitud', 'whatsapp'].includes(a.type) ? a.type : ''}`}>
              <div className="crm-timeline-desc">{a.description}</div>
              <div className="crm-timeline-meta">
                {TYPE_LABEL[a.type]} · {formatDate(a.created_at)} · {a.user_id ? `Usuario #${a.user_id}` : 'Sistema'}
                {a.source_id && (
                  <span style={{ marginLeft: '.375rem', color: 'var(--primary)', fontWeight: 600 }}>
                    #{a.source_id}
                  </span>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
```

- [ ] **Step 9: Rewrite DetailPanel.tsx with MedForge**

```tsx
// laravel-app/resources/js/crm/components/DetailPanel.tsx
import React, { useState } from 'react';
import type { CrmOpportunity, Stage } from '../types';
import { api } from '../api';
import { StageSelector } from './StageSelector';
import { ActivityTimeline } from './ActivityTimeline';
import { NoteForm } from './NoteForm';

interface Props {
  opportunity: CrmOpportunity;
  onClose: () => void;
  onUpdated: (opp: CrmOpportunity) => void;
}

const RESOLUTION_STYLE: Record<string, React.CSSProperties> = {
  provisional: { background: 'var(--warning-light)', color: 'var(--warning-hover)' },
  identified:  { background: 'var(--info-light)',    color: 'var(--info)' },
  linked:      { background: 'var(--success-light)', color: 'var(--success)' },
};

const RESOLUTION_LABEL: Record<string, string> = {
  provisional: 'Provisional', identified: 'Identificado', linked: 'Vinculado',
};

const PHASE_STYLE: Record<string, React.CSSProperties> = {
  operational: { background: 'var(--info-light)', color: 'var(--info)' },
  commercial:  { background: 'var(--success-light)', color: 'var(--success)' },
};

export function DetailPanel({ opportunity: initial, onClose, onUpdated }: Props) {
  const [opp, setOpp] = useState(initial);
  const [stageLoading, setStageLoading] = useState(false);

  const handleStageChange = async (stage: Stage) => {
    setStageLoading(true);
    const updated = await api.opportunities.update(opp.id, { stage });
    setOpp(updated);
    onUpdated(updated);
    setStageLoading(false);
  };

  const handleSaveNote = async (type: string, description: string) => {
    await api.opportunities.addActivity(opp.id, type, description);
    const refreshed = await api.opportunities.get(opp.id);
    setOpp(refreshed);
    onUpdated(refreshed);
  };

  const contact = opp.contact;
  const resolution = contact?.resolution ?? 'provisional';

  return (
    <div className="crm-detail-panel">
      <div className="crm-detail-header">
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
          <div>
            <h2 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: 'var(--fg-1)', fontFamily: 'var(--font-display)' }}>
              {contact?.name ?? '—'}
            </h2>
            <div style={{ display: 'flex', alignItems: 'center', gap: '.375rem', marginTop: '.375rem', flexWrap: 'wrap' }}>
              <span className="crm-phase-badge" style={PHASE_STYLE[opp.phase]}>
                {opp.phase === 'operational' ? 'Operativo' : 'Comercial'}
              </span>
              <span style={{
                fontSize: '.6875rem', fontWeight: 700, padding: '.15rem .45rem',
                borderRadius: 'var(--radius-pill)', ...RESOLUTION_STYLE[resolution],
              }}>
                {RESOLUTION_LABEL[resolution]}
              </span>
            </div>
          </div>
          <button
            onClick={onClose}
            style={{
              width: '2rem', height: '2rem', borderRadius: 'var(--radius-sm)',
              border: '1px solid var(--border)', background: 'transparent',
              cursor: 'pointer', color: 'var(--fg-mute)', fontSize: '1rem', lineHeight: 1,
            }}
          >
            ×
          </button>
        </div>
      </div>

      <div className="crm-detail-body">
        {/* Left column: info + actions */}
        <div className="crm-detail-left">
          <div>
            <p className="crm-section-label">Contacto</p>
            <div className="crm-contact-info">
              <p style={{ margin: '0 0 .25rem', fontSize: '.8125rem', color: 'var(--fg-2)' }}>{contact?.phone ?? '—'}</p>
              {contact?.cedula && <p style={{ margin: '0 0 .25rem', fontSize: '.8125rem', color: 'var(--fg-2)' }}>{contact.cedula}</p>}
              {contact?.email && <p style={{ margin: 0, fontSize: '.8125rem', color: 'var(--primary)' }}>{contact.email}</p>}
            </div>
          </div>

          <div>
            <p className="crm-section-label">Etapa actual</p>
            <StageSelector current={opp.stage} onChange={s => { void handleStageChange(s); }} loading={stageLoading} />
          </div>

          <div className="crm-action-grid">
            <button className="btn btn-warning btn-sm" style={{ color: 'var(--fg-1)' }}>
              <i className="mdi mdi-phone" /> Llamar
            </button>
            <button className="btn btn-info btn-sm">
              <i className="mdi mdi-email" /> Email
            </button>
            <button className="btn btn-sm" style={{
              gridColumn: '1 / -1', background: 'var(--danger-light)',
              color: 'var(--danger)', border: '1px solid var(--danger-light)',
            }}>
              Marcar como perdido
            </button>
          </div>
        </div>

        {/* Right column: activity */}
        <div className="crm-detail-right">
          <div>
            <p className="crm-section-label">Registrar actividad</p>
            <NoteForm onSave={handleSaveNote} />
          </div>
          <div>
            <p className="crm-section-label">Historial</p>
            <ActivityTimeline activities={opp.activities ?? []} />
          </div>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 10: Run TypeScript check**

```bash
cd laravel-app && npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 11: Build the frontend**

```bash
cd laravel-app && npm run build 2>&1 | tail -20
```

Expected: build completes with no errors. Output shows `dist/assets/` files generated.

- [ ] **Step 12: Commit**

```bash
git add laravel-app/resources/js/crm/ laravel-app/resources/views/crm/
git commit -m "feat(crm): full React panel redesign — MedForge design system, 7 stages, phase badges, escalation indicator"
```

---

## Final verification

- [ ] **Verify all PHP files parse cleanly**

```bash
cd laravel-app && find app -name "*.php" | xargs -I{} php -l {} 2>&1 | grep -v "No syntax"
```

Expected: no output (all files parse without errors).

- [ ] **Run all tests**

```bash
cd laravel-app && php artisan test
```

Expected: all tests pass.

- [ ] **Full build**

```bash
cd laravel-app && npm run build
```

Expected: successful build.

- [ ] **Final commit**

```bash
git add -A
git status  # verify nothing unexpected
git commit -m "feat(crm): CRM reinvention complete — grain-per-patient, 2-phase pipeline, MedForge UI"
```
