# CRM Reinvention — Plan B: API (Controladores + Rutas)

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development**.
>
> **Execution mode:** subagent-driven (~0.5 días)
>
> **Prerequisito:** Plan A completo — las 3 tablas y los servicios deben existir.

**Goal:** Exponer los servicios del Plan A como endpoints JSON que consume la SPA React del Plan C.

**Architecture:** `CrmOpportunityController` (list+show+update), `CrmContactController` (show+update+merge), `CrmActivityController` (store), `CrmStatsController` (index), `CrmUiController` (shell Blade que monta la SPA). Patrón idéntico al `CrmReadController` existente: `Auth::check()`, `JsonResponse`, sin Eloquent en controllers.

**Tech Stack:** Laravel 12, rutas en `laravel-app/routes/v2/crm.php`, namespace `App\Modules\CRM\Http\Controllers`.

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|----------------|
| `app/Modules/CRM/Http/Controllers/CrmOpportunityController.php` | Create | index, show, store, update (stage + assign) |
| `app/Modules/CRM/Http/Controllers/CrmContactController.php` | Create | show, update (cédula), merge |
| `app/Modules/CRM/Http/Controllers/CrmActivityController.php` | Create | store (nota/llamada) |
| `app/Modules/CRM/Http/Controllers/CrmStatsController.php` | Create | index (KPIs) |
| `app/Modules/CRM/Http/Controllers/CrmUiController.php` | Create | Blade shell que monta React |
| `routes/v2/crm.php` | Modify | Agregar 10 rutas nuevas |
| `tests/Feature/CrmOpportunityControllerTest.php` | Create | Tests de integración API |

---

## Task 1: CrmOpportunityController

**Files:**
- Create: `laravel-app/app/Modules/CRM/Http/Controllers/CrmOpportunityController.php`
- Create: `laravel-app/tests/Feature/CrmOpportunityControllerTest.php`

- [ ] **Step 1: Escribir tests que fallan**

```php
<?php
// laravel-app/tests/Feature/CrmOpportunityControllerTest.php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmOpportunityControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_activities', 'crm_opportunities', 'crm_contacts', 'users', 'roles'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('roles', fn (Blueprint $t) => $t->id());
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('cedula')->default('');
            $table->string('registro')->default('');
            $table->string('sede')->default('');
            $table->string('especialidad')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
        });
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name'); $table->string('phone');
            $table->string('email')->nullable(); $table->string('cedula')->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual'); $table->timestamps();
        });
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('contact_id')->index();
            $table->string('title'); $table->string('stage', 30)->default('nuevo');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable(); $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota'); $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function makeUser(): User
    {
        return User::query()->create(['username' => 'test', 'email' => 'test@test.com']);
    }

    private function makeContact(): CrmContact
    {
        return CrmContact::query()->create(['name' => 'María', 'phone' => '+5931', 'source' => 'whatsapp']);
    }

    public function test_index_returns_paginated_opportunities(): void
    {
        $contact = $this->makeContact();
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Op 1', 'stage' => 'nuevo', 'source' => 'whatsapp']);
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Op 2', 'stage' => 'interesado', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->getJson('/api/v2/crm/opportunities')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_stage(): void
    {
        $contact = $this->makeContact();
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Nuevo', 'stage' => 'nuevo', 'source' => 'whatsapp']);
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Interesado', 'stage' => 'interesado', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->getJson('/api/v2/crm/opportunities?stage=nuevo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.stage', 'nuevo');
    }

    public function test_update_changes_stage(): void
    {
        $contact = $this->makeContact();
        $opp = CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'whatsapp']);

        $this->actingAs($this->makeUser())
            ->patchJson("/api/v2/crm/opportunities/{$opp->id}", ['stage' => 'en_contacto'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'en_contacto');
    }

    public function test_update_rejects_invalid_stage(): void
    {
        $contact = $this->makeContact();
        $opp = CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'whatsapp']);

        $this->actingAs($this->makeUser())
            ->patchJson("/api/v2/crm/opportunities/{$opp->id}", ['stage' => 'etapa_falsa'])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Correr tests para confirmar que fallan**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityControllerTest.php
```

Esperado: FAIL — rutas no existen todavía.

- [ ] **Step 3: Crear CrmOpportunityController**

```php
<?php
// laravel-app/app/Modules/CRM/Http/Controllers/CrmOpportunityController.php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmOpportunityController
{
    public function __construct(
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmActivityService $activityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $limit  = min(max((int) $request->query('limit', 25), 1), 100);
        $offset = max((int) $request->query('offset', 0), 0);
        $stage  = trim((string) $request->query('stage', ''));
        $source = trim((string) $request->query('source', ''));
        $search = trim((string) $request->query('search', ''));
        $urgent = filter_var($request->query('urgent', false), FILTER_VALIDATE_BOOLEAN);

        $query = CrmOpportunity::query()->with('contact');

        if ($stage !== '') {
            $query->where('stage', $stage);
        }
        if ($source !== '') {
            $query->where('source', $source);
        }
        if ($search !== '') {
            $query->whereHas('contact', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('cedula', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            );
        }
        if ($urgent) {
            $waH  = (int) config('crm.urgency_threshold_hours.whatsapp', 6);
            $defH = (int) config('crm.urgency_threshold_hours.default', 48);
            $query->urgent($waH, $defH);
        }

        $total = $query->count();
        $rows  = $query->orderByRaw("FIELD(stage,'nuevo','en_contacto','interesado','propuesta_enviada') DESC")
            ->orderBy('updated_at', 'asc')
            ->limit($limit)->offset($offset)->get();

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $opp = CrmOpportunity::query()->with(['contact', 'activities'])->find($id);
        if (!$opp instanceof CrmOpportunity) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        return response()->json(['data' => $opp]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        // Registro manual — se crea sin evento
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'title'      => 'required|string|max:255',
            'stage'      => 'sometimes|string|in:' . implode(',', CrmOpportunity::STAGES),
        ]);
        $opp = CrmOpportunity::query()->create([
            'contact_id' => $validated['contact_id'],
            'title'      => $validated['title'],
            'stage'      => $validated['stage'] ?? CrmOpportunity::STAGE_NUEVO,
            'source'     => 'manual',
        ]);
        $this->activityService->logSystemEvent($opp->id, 'Oportunidad creada manualmente');
        return response()->json(['data' => $opp], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $opp = CrmOpportunity::query()->find($id);
        if (!$opp instanceof CrmOpportunity) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        $validated = $request->validate([
            'stage'       => 'sometimes|string|in:' . implode(',', CrmOpportunity::STAGES),
            'assigned_to' => 'sometimes|nullable|integer',
            'lost_reason' => 'sometimes|nullable|string|max:500',
        ]);

        try {
            if (isset($validated['stage'])) {
                $opp = $this->opportunityService->changeStage(
                    $opp,
                    $validated['stage'],
                    Auth::id(),
                    $validated['lost_reason'] ?? null,
                );
            }
            if (array_key_exists('assigned_to', $validated)) {
                $opp = $this->opportunityService->assign($opp, (int) $validated['assigned_to']);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $opp->fresh(['contact', 'activities'])]);
    }
}
```

- [ ] **Step 4: Crear los demás controllers**

```php
<?php
// laravel-app/app/Modules/CRM/Http/Controllers/CrmContactController.php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmContactResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrmContactController
{
    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
    ) {}

    public function show(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $contact = CrmContact::query()->with('opportunities')->find($id);
        if (!$contact instanceof CrmContact) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        return response()->json(['data' => $contact]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $contact = CrmContact::query()->find($id);
        if (!$contact instanceof CrmContact) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        $validated = $request->validate([
            'cedula'     => 'sometimes|string|max:30',
            'patient_id' => 'sometimes|nullable|integer',
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|nullable|email',
        ]);
        if (isset($validated['cedula'])) {
            $contact->cedula = $validated['cedula'];
            $contact->resolution = isset($validated['patient_id'])
                ? CrmContact::RESOLUTION_LINKED
                : CrmContact::RESOLUTION_IDENTIFIED;
        }
        if (array_key_exists('patient_id', $validated)) {
            $contact->patient_id = $validated['patient_id'];
            if ($contact->cedula) {
                $contact->resolution = CrmContact::RESOLUTION_LINKED;
            }
        }
        $contact->fill(array_intersect_key($validated, array_flip(['name', 'email'])));
        $contact->save();
        return response()->json(['data' => $contact->fresh()]);
    }

    public function merge(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $validated = $request->validate(['merge_into_id' => 'required|integer|different:id']);
        $source = CrmContact::query()->find($id);
        $target = CrmContact::query()->find($validated['merge_into_id']);
        if (!$source instanceof CrmContact || !$target instanceof CrmContact) {
            return response()->json(['error' => 'Contacto no encontrado'], 404);
        }
        DB::transaction(function () use ($source, $target): void {
            CrmOpportunity::query()->where('contact_id', $source->id)->update(['contact_id' => $target->id]);
            $source->delete();
        });
        return response()->json(['data' => $target->fresh('opportunities')]);
    }
}
```

```php
<?php
// laravel-app/app/Modules/CRM/Http/Controllers/CrmActivityController.php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmActivityController
{
    public function __construct(private readonly CrmActivityService $activityService) {}

    public function store(Request $request, int $opportunityId): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        if (!CrmOpportunity::query()->find($opportunityId)) {
            return response()->json(['error' => 'No encontrado'], 404);
        }
        $validated = $request->validate([
            'type'        => 'required|string|in:nota,llamada,email',
            'description' => 'required|string|max:2000',
        ]);
        $activity = $this->activityService->log(
            opportunityId: $opportunityId,
            type: $validated['type'],
            description: $validated['description'],
            userId: Auth::id(),
        );
        return response()->json(['data' => $activity], 201);
    }
}
```

```php
<?php
// laravel-app/app/Modules/CRM/Http/Controllers/CrmStatsController.php

namespace App\Modules\CRM\Http\Controllers;

use App\Modules\CRM\Services\CrmStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CrmStatsController
{
    public function __construct(private readonly CrmStatsService $statsService) {}

    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        return response()->json([
            'data' => [
                'panel'    => $this->statsService->panelStats(),
                'by_stage' => $this->statsService->byStage(),
            ],
        ]);
    }
}
```

```php
<?php
// laravel-app/app/Modules/CRM/Http/Controllers/CrmUiController.php

namespace App\Modules\CRM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CrmUiController
{
    public function index(): mixed
    {
        if (!Auth::check()) {
            return redirect('/login');
        }
        return view('crm.panel');
    }
}
```

- [ ] **Step 5: Agregar rutas en `routes/v2/crm.php`**

Agregar al final del archivo existente:

```php
// ── CRM Oportunidades (nuevo pipeline centralizado) ──────────────────────────
Route::get('/crm/opportunities',               [CrmOpportunityController::class, 'index']);
Route::post('/crm/opportunities',              [CrmOpportunityController::class, 'store']);
Route::get('/crm/opportunities/{id}',          [CrmOpportunityController::class, 'show']);
Route::patch('/crm/opportunities/{id}',        [CrmOpportunityController::class, 'update']);
Route::post('/crm/opportunities/{id}/activities', [CrmActivityController::class, 'store']);

// ── CRM Contactos ─────────────────────────────────────────────────────────────
Route::get('/crm/contacts/{id}',               [CrmContactController::class, 'show']);
Route::patch('/crm/contacts/{id}',             [CrmContactController::class, 'update']);
Route::post('/crm/contacts/{id}/merge',        [CrmContactController::class, 'merge']);

// ── CRM Stats ─────────────────────────────────────────────────────────────────
Route::get('/crm/stats',                       [CrmStatsController::class, 'index']);
```

Agregar también en `routes/web.php` (fuera del prefijo v2):

```php
Route::get('/crm', [CrmUiController::class, 'index']);
```

Agregar los imports necesarios al inicio del archivo:

```php
use App\Modules\CRM\Http\Controllers\CrmOpportunityController;
use App\Modules\CRM\Http\Controllers\CrmContactController;
use App\Modules\CRM\Http\Controllers\CrmActivityController;
use App\Modules\CRM\Http\Controllers\CrmStatsController;
use App\Modules\CRM\Http\Controllers\CrmUiController;
```

- [ ] **Step 6: Correr tests de API**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityControllerTest.php --verbose
```

Esperado: 4 tests passed.

- [ ] **Step 7: Correr suite completa**

```bash
cd laravel-app && php artisan test
```

Esperado: sin regresiones.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/CRM/Http/Controllers/CrmOpportunityController.php \
        app/Modules/CRM/Http/Controllers/CrmContactController.php \
        app/Modules/CRM/Http/Controllers/CrmActivityController.php \
        app/Modules/CRM/Http/Controllers/CrmStatsController.php \
        app/Modules/CRM/Http/Controllers/CrmUiController.php \
        routes/v2/crm.php \
        tests/Feature/CrmOpportunityControllerTest.php
git commit -m "feat(crm): add API controllers and routes for opportunities, contacts, stats"
```
