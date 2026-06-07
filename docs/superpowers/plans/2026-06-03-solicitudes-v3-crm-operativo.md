# Solicitudes V3 CRM Operativo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a clean reusable CRM V3 API layer and connect Solicitudes V3 React CRM/prefactura actions to real persisted backend behavior without changing the current visual design.

**Architecture:** Add `/v3/crm/...` for shared CRM case data/actions and `/v3/solicitudes/...` only for surgical request data. Reuse existing V2 services internally where they are correct, but expose normalized payloads and action responses for React. Replace local-only UI mutations in Solicitudes V3 with API calls, loading/error states, and refreshed case data.

**Tech Stack:** Laravel/PHP, MySQL via existing DB layer/services, React/TypeScript, Vite, Node test runner, PHPUnit feature tests.

---

## File Map

- Create `laravel-app/routes/v3/crm.php`: shared CRM V3 API routes.
- Create `laravel-app/routes/v3/solicitudes.php`: Solicitudes V3 domain-specific routes.
- Modify `laravel-app/routes/api.php`: mount V3 route files under `Route::prefix('v3')`.
- Create `laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php`: shared CRM case read/write endpoint controller.
- Create `laravel-app/app/Modules/CRM/Services/CrmCaseService.php`: normalizes CRM case payloads and delegates to Solicitudes/CRM services.
- Create `laravel-app/app/Modules/CRM/Services/CrmCaseActivityService.php`: builds real activity timeline from notes, tasks, comms, proposals, documents and CRM activities.
- Modify `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesReadController.php`: expose V3-compatible solicitud detail/prefactura methods or delegate to existing detail method.
- Modify `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php`: keep existing V2 methods intact; only add wrappers if the V3 controller delegates here.
- Modify `laravel-app/app/Modules/Solicitudes/Services/SolicitudesReadParityService.php`: ensure existing query methods expose enough data for V3 normalizers.
- Modify `laravel-app/resources/js/solicitudes-v3/types.ts`: add normalized CRM V3 types.
- Modify `laravel-app/resources/js/solicitudes-v3/api.ts`: add CRM V3 fetch/mutate functions and mappers.
- Modify `laravel-app/resources/js/solicitudes-v3/App.tsx`: remove fake local CRM mutations and wire real handlers.
- Modify `laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx`: keep layout, remove duplicate checklist in seguimiento, add real action forms/states.
- Modify `laravel-app/resources/js/solicitudes-v3/Prefactura.tsx`: consume real prefactura fields and show V2-compatible aptitud clinica.
- Modify `laravel-app/resources/css/solicitudes-v3.css` if the textarea/action error states need small layout fixes; do not redesign.
- Create `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`: PHP feature coverage for shared CRM case endpoints.
- Create `laravel-app/tests/Feature/SolicitudesV3PrefacturaContractTest.php`: PHP feature coverage for prefactura diagnosis/cobertura/agenda contract.
- Modify `laravel-app/resources/js/solicitudes-v3/api.test.ts`: mapper tests for CRM V3 and prefactura.

---

## Task 1: Mount V3 Route Surface

**Files:**
- Create: `laravel-app/routes/v3/crm.php`
- Create: `laravel-app/routes/v3/solicitudes.php`
- Modify: `laravel-app/routes/api.php`
- Test: `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`

- [ ] **Step 1: Write the failing route smoke tests**

Add `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CrmV3CaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_v3_crm_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('v3.crm.cases.show'));
        $this->assertTrue(Route::has('v3.crm.cases.notes.store'));
        $this->assertTrue(Route::has('v3.crm.cases.tasks.store'));
        $this->assertTrue(Route::has('v3.crm.cases.whatsapp.store'));
        $this->assertTrue(Route::has('v3.crm.cases.email.store'));
        $this->assertTrue(Route::has('v3.crm.cases.proposals.store'));
    }
}
```

- [ ] **Step 2: Run the route smoke test and verify failure**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=v3_crm_routes_are_registered
```

Expected: fail because the route names do not exist.

- [ ] **Step 3: Add route files**

Create `laravel-app/routes/v3/crm.php`:

```php
<?php

use App\Modules\CRM\Http\Controllers\CrmCaseController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'app.auth',
    'app.permission:administrativo,crm.view,crm.manage,solicitudes.view,solicitudes.update,solicitudes.manage',
])->group(function (): void {
    Route::get('/crm/cases/{sourceType}/{sourceId}', [CrmCaseController::class, 'show'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.show');

    Route::patch('/crm/cases/{sourceType}/{sourceId}', [CrmCaseController::class, 'update'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.update');

    Route::post('/crm/cases/{sourceType}/{sourceId}/contacts', [CrmCaseController::class, 'storeContact'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.contacts.store');

    Route::post('/crm/cases/{sourceType}/{sourceId}/notes', [CrmCaseController::class, 'storeNote'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.notes.store');

    Route::delete('/crm/cases/{sourceType}/{sourceId}/notes/{noteId}', [CrmCaseController::class, 'deleteNote'])
        ->whereNumber('sourceId')
        ->whereNumber('noteId')
        ->name('v3.crm.cases.notes.delete');

    Route::post('/crm/cases/{sourceType}/{sourceId}/tasks', [CrmCaseController::class, 'storeTask'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.tasks.store');

    Route::patch('/crm/cases/{sourceType}/{sourceId}/tasks/{taskId}', [CrmCaseController::class, 'updateTask'])
        ->whereNumber('sourceId')
        ->whereNumber('taskId')
        ->name('v3.crm.cases.tasks.update');

    Route::post('/crm/cases/{sourceType}/{sourceId}/whatsapp', [CrmCaseController::class, 'sendWhatsapp'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.whatsapp.store');

    Route::post('/crm/cases/{sourceType}/{sourceId}/email', [CrmCaseController::class, 'sendEmail'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.email.store');

    Route::get('/crm/catalog/codes', [CrmCaseController::class, 'catalogCodes'])
        ->name('v3.crm.catalog.codes');

    Route::get('/crm/catalog/packages', [CrmCaseController::class, 'catalogPackages'])
        ->name('v3.crm.catalog.packages');

    Route::post('/crm/cases/{sourceType}/{sourceId}/proposals', [CrmCaseController::class, 'storeProposal'])
        ->whereNumber('sourceId')
        ->name('v3.crm.cases.proposals.store');

    Route::get('/crm/proposals/{proposalId}/pdf', [CrmCaseController::class, 'proposalPdf'])
        ->whereNumber('proposalId')
        ->name('v3.crm.proposals.pdf');

    Route::post('/crm/proposals/{proposalId}/send-email', [CrmCaseController::class, 'sendProposalEmail'])
        ->whereNumber('proposalId')
        ->name('v3.crm.proposals.email');

    Route::post('/crm/proposals/{proposalId}/send-whatsapp', [CrmCaseController::class, 'sendProposalWhatsapp'])
        ->whereNumber('proposalId')
        ->name('v3.crm.proposals.whatsapp');
});
```

Create `laravel-app/routes/v3/solicitudes.php`:

```php
<?php

use App\Modules\Solicitudes\Http\Controllers\SolicitudesReadController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'app.auth',
    'app.permission:administrativo,solicitudes.view,solicitudes.update,solicitudes.turnero,solicitudes.dashboard.view,solicitudes.manage',
])->group(function (): void {
    Route::get('/solicitudes/{id}/detalle', [SolicitudesReadController::class, 'detalleCompleto'])
        ->whereNumber('id')
        ->name('v3.solicitudes.detalle');
});
```

Modify `laravel-app/routes/api.php` inside the existing API route definitions, after the V2 group:

```php
Route::prefix('v3')->group(function (): void {
    require __DIR__ . '/v3/crm.php';
    require __DIR__ . '/v3/solicitudes.php';
});
```

- [ ] **Step 4: Add a controller shell with explicit unavailable responses**

Create `laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\CRM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmCaseController
{
    public function show(string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function update(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function storeContact(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function storeNote(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function deleteNote(string $sourceType, int $sourceId, int $noteId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function storeTask(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function updateTask(Request $request, string $sourceType, int $sourceId, int $taskId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function sendWhatsapp(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function sendEmail(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function catalogCodes(Request $request): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function catalogPackages(Request $request): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function storeProposal(Request $request, string $sourceType, int $sourceId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function proposalPdf(int $proposalId) { return response('Accion V3 no disponible', 501); }
    public function sendProposalEmail(Request $request, int $proposalId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
    public function sendProposalWhatsapp(Request $request, int $proposalId): JsonResponse { return response()->json(['success' => false, 'error' => 'Accion V3 no disponible'], 501); }
}
```

- [ ] **Step 5: Run the route smoke test and commit**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=v3_crm_routes_are_registered
php -l routes/v3/crm.php
php -l routes/v3/solicitudes.php
php -l app/Modules/CRM/Http/Controllers/CrmCaseController.php
```

Expected: route test passes; all lint commands report `No syntax errors detected`.

Commit:

```bash
git add laravel-app/routes/api.php laravel-app/routes/v3/crm.php laravel-app/routes/v3/solicitudes.php laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php laravel-app/tests/Feature/CrmV3CaseControllerTest.php
git commit -m "feat(crm): add v3 case route surface"
```

---

## Task 2: Build Shared CRM Case Read Contract

**Files:**
- Create: `laravel-app/app/Modules/CRM/Services/CrmCaseService.php`
- Create: `laravel-app/app/Modules/CRM/Services/CrmCaseActivityService.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php`
- Test: `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`

- [ ] **Step 1: Add failing read contract test**

Append to `CrmV3CaseControllerTest`:

```php
public function test_show_solicitud_case_returns_normalized_crm_payload(): void
{
    $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId(['username' => 'crm-v3']);
    $this->actingAs(\App\Models\User::query()->findOrFail($userId));

    \Illuminate\Support\Facades\Schema::create('solicitud_procedimiento', function (\Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('hc_number')->nullable();
        $table->unsignedBigInteger('form_id')->nullable();
        $table->string('estado')->nullable();
        $table->string('sede')->nullable();
        $table->unsignedBigInteger('crm_opportunity_id')->nullable();
        $table->timestamps();
    });
    \Illuminate\Support\Facades\Schema::create('solicitud_crm_detalles', function (\Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('solicitud_id')->index();
        $table->unsignedBigInteger('crm_opportunity_id')->nullable();
        $table->string('responsable_nombre')->nullable();
        $table->string('telefono')->nullable();
        $table->string('email')->nullable();
        $table->string('fuente')->nullable();
        $table->timestamps();
    });
    \Illuminate\Support\Facades\Schema::create('solicitud_crm_notas', function (\Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('solicitud_id')->index();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->text('nota');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('deleted_at')->nullable();
    });
    \Illuminate\Support\Facades\Schema::create('crm_tasks', function (\Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('source_type')->nullable()->index();
        $table->unsignedBigInteger('source_id')->nullable()->index();
        $table->string('title');
        $table->string('priority')->nullable();
        $table->string('status')->nullable();
        $table->unsignedBigInteger('assigned_to')->nullable();
        $table->dateTime('due_at')->nullable();
        $table->timestamps();
    });

    \Illuminate\Support\Facades\DB::table('solicitud_procedimiento')->insert([
        'id' => 275872,
        'full_name' => 'DANIELA VALENTINA MORALES MURILLO',
        'hc_number' => '0932000904',
        'form_id' => 275872,
        'estado' => 'revision-codigos',
        'sede' => 'MATRIZ',
    ]);
    \Illuminate\Support\Facades\DB::table('solicitud_crm_detalles')->insert([
        'solicitud_id' => 275872,
        'responsable_nombre' => 'Coordinación',
        'telefono' => '0987107769',
        'email' => 'paciente@example.com',
        'fuente' => 'Convenio',
    ]);

    $this->getJson('/v3/crm/cases/solicitud/275872')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.case.source_type', 'solicitud')
        ->assertJsonPath('data.case.source_id', 275872)
        ->assertJsonPath('data.contacts.primary_phone', '0987107769')
        ->assertJsonPath('data.contacts.primary_email', 'paciente@example.com')
        ->assertJsonStructure([
            'data' => [
                'case' => ['case_id', 'source_type', 'source_id', 'form_id', 'patient_name', 'stage', 'site'],
                'crm' => ['responsible_name', 'source'],
                'contacts' => ['primary_phone', 'alternate_phones', 'primary_email', 'alternate_emails'],
                'notes',
                'tasks',
                'activity',
                'proposals',
                'documents',
            ],
        ]);
}
```

- [ ] **Step 2: Run the read contract test and verify failure**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=show_solicitud_case_returns_normalized_crm_payload
```

Expected: fail with `501` or missing tables/columns identified by service implementation.

- [ ] **Step 3: Implement CRM case activity service**

Create `laravel-app/app/Modules/CRM/Services/CrmCaseActivityService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use Illuminate\Support\Facades\DB;

class CrmCaseActivityService
{
    public function forCase(string $sourceType, int $sourceId): array
    {
        $events = [];

        if ($sourceType === 'solicitud' && DB::getSchemaBuilder()->hasTable('solicitud_crm_notas')) {
            $notes = DB::table('solicitud_crm_notas')
                ->where('solicitud_id', $sourceId)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            foreach ($notes as $note) {
                $events[] = [
                    'id' => 'note-' . (int) $note->id,
                    'type' => 'note_created',
                    'occurred_at' => (string) $note->created_at,
                    'author' => $this->userName((int) ($note->user_id ?? 0)),
                    'description' => 'Nota creada',
                    'reference' => ['note_id' => (int) $note->id],
                ];
            }
        }

        if (DB::getSchemaBuilder()->hasTable('crm_tasks')) {
            $tasks = DB::table('crm_tasks')
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get();

            foreach ($tasks as $task) {
                $events[] = [
                    'id' => 'task-' . (int) $task->id,
                    'type' => (($task->status ?? '') === 'done') ? 'task_completed' : 'task_updated',
                    'occurred_at' => (string) ($task->updated_at ?? $task->created_at),
                    'author' => $this->userName((int) ($task->assigned_to ?? 0)),
                    'description' => (string) $task->title,
                    'reference' => ['task_id' => (int) $task->id],
                ];
            }
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));

        return array_slice($events, 0, 30);
    }

    private function userName(int $userId): string
    {
        if ($userId <= 0 || ! DB::getSchemaBuilder()->hasTable('users')) {
            return 'Sistema';
        }

        $name = DB::table('users')->where('id', $userId)->value('name')
            ?? DB::table('users')->where('id', $userId)->value('username');

        return trim((string) $name) !== '' ? (string) $name : 'Usuario';
    }
}
```

- [ ] **Step 4: Implement CRM case service read method**

Create `laravel-app/app/Modules/CRM/Services/CrmCaseService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrmCaseService
{
    public function __construct(private readonly CrmCaseActivityService $activityService)
    {
    }

    public function show(string $sourceType, int $sourceId): array
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        if ($sourceType !== 'solicitud') {
            throw new RuntimeException('Tipo de caso no soportado');
        }

        $solicitud = DB::table('solicitud_procedimiento')->where('id', $sourceId)->first();
        if (! $solicitud) {
            throw new RuntimeException('Caso no encontrado');
        }

        $detalle = DB::getSchemaBuilder()->hasTable('solicitud_crm_detalles')
            ? DB::table('solicitud_crm_detalles')->where('solicitud_id', $sourceId)->first()
            : null;

        return [
            'case' => [
                'case_id' => 'solicitud-' . $sourceId,
                'source_type' => 'solicitud',
                'source_id' => $sourceId,
                'solicitud_id' => $sourceId,
                'form_id' => isset($solicitud->form_id) ? (int) $solicitud->form_id : null,
                'paciente_id' => isset($solicitud->paciente_id) ? (int) $solicitud->paciente_id : null,
                'patient_name' => (string) ($solicitud->full_name ?? ''),
                'hc_number' => (string) ($solicitud->hc_number ?? ''),
                'stage' => (string) ($solicitud->estado ?? ''),
                'site' => (string) ($solicitud->sede ?? ''),
            ],
            'crm' => [
                'responsible_id' => isset($detalle->responsable_id) ? (int) $detalle->responsable_id : null,
                'responsible_name' => (string) ($detalle->responsable_nombre ?? 'Coordinación'),
                'source' => (string) ($detalle->fuente ?? '—'),
                'insurance_company' => (string) ($detalle->aseguradora_empresa ?? ''),
                'insurance_plan' => (string) ($detalle->plan_afiliacion ?? ''),
                'insurance_code' => (string) ($detalle->afiliacion_codigo ?? ''),
            ],
            'contacts' => [
                'primary_phone' => (string) ($detalle->telefono ?? ''),
                'alternate_phones' => $this->jsonList($detalle->telefonos_alternos ?? null),
                'primary_email' => (string) ($detalle->email ?? ''),
                'alternate_emails' => $this->jsonList($detalle->emails_alternos ?? null),
            ],
            'notes' => $this->notes($sourceType, $sourceId),
            'tasks' => $this->tasks($sourceType, $sourceId),
            'activity' => $this->activityService->forCase($sourceType, $sourceId),
            'proposals' => $this->proposals($sourceType, $sourceId),
            'documents' => $this->documents($sourceType, $sourceId),
        ];
    }

    private function normalizeSourceType(string $sourceType): string
    {
        return match (strtolower(trim($sourceType))) {
            'solicitud', 'solicitud_procedimiento', 'solicitudes' => 'solicitud',
            default => strtolower(trim($sourceType)),
        };
    }

    private function notes(string $sourceType, int $sourceId): array
    {
        if ($sourceType !== 'solicitud' || ! DB::getSchemaBuilder()->hasTable('solicitud_crm_notas')) {
            return [];
        }

        return DB::table('solicitud_crm_notas')
            ->where('solicitud_id', $sourceId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'body' => (string) $row->nota,
                'author_id' => isset($row->user_id) ? (int) $row->user_id : null,
                'author_name' => 'Usuario',
                'created_at' => (string) $row->created_at,
                'can_delete' => true,
            ])
            ->all();
    }

    private function tasks(string $sourceType, int $sourceId): array
    {
        if (! DB::getSchemaBuilder()->hasTable('crm_tasks')) {
            return [];
        }

        return DB::table('crm_tasks')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('status')
            ->orderBy('due_at')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'status' => (string) ($row->status ?? 'pending'),
                'priority' => (string) ($row->priority ?? 'normal'),
                'assigned_to' => isset($row->assigned_to) ? (int) $row->assigned_to : null,
                'due_at' => $row->due_at ? (string) $row->due_at : null,
            ])
            ->all();
    }

    private function proposals(string $sourceType, int $sourceId): array
    {
        if (! DB::getSchemaBuilder()->hasTable('crm_proposals')) {
            return [];
        }

        return DB::table('crm_proposals')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'status' => (string) ($row->status ?? 'draft'),
                'total' => isset($row->total) ? (float) $row->total : 0.0,
                'created_at' => (string) $row->created_at,
            ])
            ->all();
    }

    private function documents(string $sourceType, int $sourceId): array
    {
        return [];
    }

    private function jsonList(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }
}
```

- [ ] **Step 5: Wire controller show method**

Replace the shell `show` method in `CrmCaseController` and add constructor:

```php
use App\Modules\CRM\Services\CrmCaseService;
use RuntimeException;
use Throwable;

public function __construct(private readonly CrmCaseService $cases)
{
}

public function show(string $sourceType, int $sourceId): JsonResponse
{
    try {
        return response()->json([
            'success' => true,
            'data' => $this->cases->show($sourceType, $sourceId),
        ]);
    } catch (RuntimeException $e) {
        $status = str_contains(strtolower($e->getMessage()), 'no encontrado') ? 404 : 422;
        return response()->json(['success' => false, 'error' => $e->getMessage()], $status);
    } catch (Throwable $e) {
        report($e);
        return response()->json(['success' => false, 'error' => 'No se pudo cargar el caso CRM'], 500);
    }
}
```

- [ ] **Step 6: Run tests and commit**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php
php -l app/Modules/CRM/Services/CrmCaseService.php
php -l app/Modules/CRM/Services/CrmCaseActivityService.php
php -l app/Modules/CRM/Http/Controllers/CrmCaseController.php
```

Expected: all pass.

Commit:

```bash
git add laravel-app/app/Modules/CRM/Services/CrmCaseService.php laravel-app/app/Modules/CRM/Services/CrmCaseActivityService.php laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php laravel-app/tests/Feature/CrmV3CaseControllerTest.php
git commit -m "feat(crm): expose normalized v3 case read contract"
```

---

## Task 3: Implement Notes, Contacts, Responsible and Tasks Mutations

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Services/CrmCaseService.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php`
- Test: `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`

- [ ] **Step 1: Add failing mutation tests**

Append tests:

```php
public function test_store_note_persists_and_returns_refreshed_case(): void
{
    $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId(['username' => 'note-author']);
    $this->actingAs(\App\Models\User::query()->findOrFail($userId));
    $this->seedSolicitudCaseTables(275872);

    $this->postJson('/v3/crm/cases/solicitud/275872/notes', ['body' => 'Paciente confirma disponibilidad'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.notes.0.body', 'Paciente confirma disponibilidad');

    $this->assertDatabaseHas('solicitud_crm_notas', [
        'solicitud_id' => 275872,
        'nota' => 'Paciente confirma disponibilidad',
    ]);
}

public function test_store_task_and_update_task_status_are_persisted(): void
{
    $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId(['username' => 'task-author']);
    $this->actingAs(\App\Models\User::query()->findOrFail($userId));
    $this->seedSolicitudCaseTables(275872);

    $taskId = $this->postJson('/v3/crm/cases/solicitud/275872/tasks', [
        'title' => 'Validar cobertura',
        'priority' => 'alta',
        'due_at' => '2026-06-04 09:00:00',
    ])
        ->assertOk()
        ->json('data.tasks.0.id');

    $this->patchJson('/v3/crm/cases/solicitud/275872/tasks/' . $taskId, ['status' => 'done'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('crm_tasks', [
        'id' => $taskId,
        'source_type' => 'solicitud',
        'source_id' => 275872,
        'status' => 'done',
    ]);
}
```

Add this helper inside the test class:

```php
private function seedSolicitudCaseTables(int $id): void
{
    if (! \Illuminate\Support\Facades\Schema::hasTable('solicitud_procedimiento')) {
        \Illuminate\Support\Facades\Schema::create('solicitud_procedimiento', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('hc_number')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('estado')->nullable();
            $table->string('sede')->nullable();
            $table->timestamps();
        });
    }
    if (! \Illuminate\Support\Facades\Schema::hasTable('solicitud_crm_detalles')) {
        \Illuminate\Support\Facades\Schema::create('solicitud_crm_detalles', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id')->index();
            $table->string('responsable_nombre')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->text('telefonos_alternos')->nullable();
            $table->text('emails_alternos')->nullable();
            $table->timestamps();
        });
    }
    if (! \Illuminate\Support\Facades\Schema::hasTable('solicitud_crm_notas')) {
        \Illuminate\Support\Facades\Schema::create('solicitud_crm_notas', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('nota');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    if (! \Illuminate\Support\Facades\Schema::hasTable('crm_tasks')) {
        \Illuminate\Support\Facades\Schema::create('crm_tasks', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('title');
            $table->string('priority')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->timestamps();
        });
    }

    \Illuminate\Support\Facades\DB::table('solicitud_procedimiento')->updateOrInsert(
        ['id' => $id],
        ['full_name' => 'Paciente Test', 'hc_number' => '0999999999', 'form_id' => $id, 'estado' => 'revision-codigos', 'sede' => 'MATRIZ']
    );
    \Illuminate\Support\Facades\DB::table('solicitud_crm_detalles')->updateOrInsert(
        ['solicitud_id' => $id],
        ['responsable_nombre' => 'Coordinación', 'telefono' => '0987107769', 'email' => 'paciente@example.com']
    );
}
```

- [ ] **Step 2: Run mutation tests and verify failure**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=store_note
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=store_task
```

Expected: fail while controller methods return 501.

- [ ] **Step 3: Add service mutation methods**

Add to `CrmCaseService`:

```php
public function storeNote(string $sourceType, int $sourceId, string $body, ?int $userId): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    if ($sourceType !== 'solicitud') {
        throw new RuntimeException('Notas no soportadas para este tipo de caso');
    }
    $body = trim($body);
    if ($body === '') {
        throw new RuntimeException('La nota no puede estar vacia');
    }

    DB::table('solicitud_crm_notas')->insert([
        'solicitud_id' => $sourceId,
        'user_id' => $userId,
        'nota' => $body,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $this->show($sourceType, $sourceId);
}

public function deleteNote(string $sourceType, int $sourceId, int $noteId, ?int $userId, bool $isAdmin): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    $note = DB::table('solicitud_crm_notas')->where('id', $noteId)->where('solicitud_id', $sourceId)->first();
    if (! $note) {
        throw new RuntimeException('Nota no encontrada');
    }
    if (! $isAdmin && (int) ($note->user_id ?? 0) !== (int) $userId) {
        throw new RuntimeException('No tiene permiso para borrar esta nota');
    }

    DB::table('solicitud_crm_notas')->where('id', $noteId)->update(['deleted_at' => now(), 'updated_at' => now()]);

    return $this->show($sourceType, $sourceId);
}

public function storeTask(string $sourceType, int $sourceId, array $payload, ?int $userId): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    $title = trim((string) ($payload['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('El titulo de la tarea es requerido');
    }

    DB::table('crm_tasks')->insert([
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'title' => $title,
        'priority' => strtolower((string) ($payload['priority'] ?? 'normal')),
        'status' => 'pending',
        'assigned_to' => isset($payload['assigned_to']) ? (int) $payload['assigned_to'] : $userId,
        'due_at' => $payload['due_at'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $this->show($sourceType, $sourceId);
}

public function updateTask(string $sourceType, int $sourceId, int $taskId, array $payload): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    $updates = ['updated_at' => now()];
    foreach (['title', 'priority', 'status', 'assigned_to', 'due_at'] as $field) {
        if (array_key_exists($field, $payload)) {
            $updates[$field] = $payload[$field];
        }
    }

    $affected = DB::table('crm_tasks')
        ->where('id', $taskId)
        ->where('source_type', $sourceType)
        ->where('source_id', $sourceId)
        ->update($updates);

    if ($affected === 0) {
        throw new RuntimeException('Tarea no encontrada');
    }

    return $this->show($sourceType, $sourceId);
}
```

- [ ] **Step 4: Wire controller mutation methods**

Replace methods in `CrmCaseController`:

```php
public function storeNote(Request $request, string $sourceType, int $sourceId): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->storeNote($sourceType, $sourceId, (string) $request->input('body', ''), $request->user()?->id));
}

public function deleteNote(Request $request, string $sourceType, int $sourceId, int $noteId): JsonResponse
{
    $isAdmin = $request->user()?->can('crm.manage') ?? false;
    return $this->jsonAction(fn () => $this->cases->deleteNote($sourceType, $sourceId, $noteId, $request->user()?->id, $isAdmin));
}

public function storeTask(Request $request, string $sourceType, int $sourceId): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->storeTask($sourceType, $sourceId, $request->all(), $request->user()?->id));
}

public function updateTask(Request $request, string $sourceType, int $sourceId, int $taskId): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->updateTask($sourceType, $sourceId, $taskId, $request->all()));
}

private function jsonAction(callable $action): JsonResponse
{
    try {
        return response()->json(['success' => true, 'data' => $action()]);
    } catch (RuntimeException $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        report($e);
        return response()->json(['success' => false, 'error' => 'No se pudo completar la accion'], 500);
    }
}
```

- [ ] **Step 5: Run tests and commit**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php
php -l app/Modules/CRM/Services/CrmCaseService.php
php -l app/Modules/CRM/Http/Controllers/CrmCaseController.php
```

Commit:

```bash
git add laravel-app/app/Modules/CRM/Services/CrmCaseService.php laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php laravel-app/tests/Feature/CrmV3CaseControllerTest.php
git commit -m "feat(crm): persist v3 notes and tasks"
```

---

## Task 4: Implement Communication and Proposal V3 Wrappers

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Services/CrmCaseService.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php`
- Test: `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`

- [ ] **Step 1: Add tests for no-fake communication/proposal behavior**

Append:

```php
public function test_whatsapp_rejects_empty_recipients_and_message(): void
{
    $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId(['username' => 'comm']);
    $this->actingAs(\App\Models\User::query()->findOrFail($userId));
    $this->seedSolicitudCaseTables(275872);

    $this->postJson('/v3/crm/cases/solicitud/275872/whatsapp', ['recipients' => [], 'message' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
}

public function test_proposal_requires_catalog_items(): void
{
    $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId(['username' => 'proposal']);
    $this->actingAs(\App\Models\User::query()->findOrFail($userId));
    $this->seedSolicitudCaseTables(275872);

    $this->postJson('/v3/crm/cases/solicitud/275872/proposals', [
        'items' => [['description' => 'Libre manual', 'price' => 100]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
}
```

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=whatsapp_rejects
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=proposal_requires
```

Expected: fail with 501 until methods are wired.

- [ ] **Step 3: Add real service dependencies**

Add imports to `CrmCaseService`:

```php
use App\Modules\Solicitudes\Services\SolicitudesCommunicationService;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use App\Modules\Solicitudes\Services\SolicitudesWriteParityService;
```

Add helpers to `CrmCaseService`:

```php
private function solicitudesReadService(): SolicitudesReadParityService
{
    return new SolicitudesReadParityService();
}

private function solicitudesCommunicationService(): SolicitudesCommunicationService
{
    return new SolicitudesCommunicationService($this->solicitudesReadService());
}

private function solicitudesWriteService(): SolicitudesWriteParityService
{
    return new SolicitudesWriteParityService(DB::connection()->getPdo(), $this->solicitudesReadService());
}
```

- [ ] **Step 4: Add real communication/proposal service methods**

Add to `CrmCaseService`:

```php
public function sendWhatsapp(string $sourceType, int $sourceId, array $payload, ?int $actorUserId): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    $recipients = array_values(array_filter(array_map('strval', (array) ($payload['recipients'] ?? []))));
    $message = trim((string) ($payload['message'] ?? ''));
    if ($recipients === [] || $message === '') {
        throw new RuntimeException('Debe seleccionar destinatario y escribir mensaje');
    }
    if ($sourceType !== 'solicitud') {
        throw new RuntimeException('WhatsApp no soportado para este tipo de caso');
    }

    foreach ($recipients as $phone) {
        $this->solicitudesCommunicationService()->sendWhatsapp($sourceId, [
            'phone' => $phone,
            'message' => $message,
        ], $actorUserId);
    }

    return $this->show($sourceType, $sourceId);
}

public function sendEmail(string $sourceType, int $sourceId, array $payload, ?int $actorUserId): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    $to = array_values(array_filter(array_map('strval', (array) ($payload['to'] ?? []))));
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    if ($to === [] || $subject === '' || $body === '') {
        throw new RuntimeException('Debe seleccionar destinatario, asunto y cuerpo');
    }
    if ($sourceType !== 'solicitud') {
        throw new RuntimeException('Correo no soportado para este tipo de caso');
    }

    foreach ($to as $email) {
        $this->solicitudesCommunicationService()->sendEmail($sourceId, [
            'to' => $email,
            'subject' => $subject,
            'body' => $body,
        ], $actorUserId);
    }

    return $this->show($sourceType, $sourceId);
}

public function storeProposal(string $sourceType, int $sourceId, array $payload, ?int $actorUserId): array
{
    $sourceType = $this->normalizeSourceType($sourceType);
    $items = (array) ($payload['items'] ?? []);
    foreach ($items as $item) {
        if (! isset($item['catalog_type'], $item['catalog_id'])) {
            throw new RuntimeException('La propuesta solo acepta items de catalogo');
        }
    }
    if ($items === []) {
        throw new RuntimeException('Debe agregar al menos un item de catalogo');
    }
    if ($sourceType !== 'solicitud') {
        throw new RuntimeException('Propuestas no soportadas para este tipo de caso');
    }

    $legacyPayload = $payload;
    $legacyPayload['items'] = array_map(static fn (array $item): array => [
        'catalog_type' => (string) $item['catalog_type'],
        'catalog_id' => (int) $item['catalog_id'],
        'quantity' => (float) ($item['quantity'] ?? 1),
    ], $items);

    $this->solicitudesWriteService()->crmCrearPropuesta($sourceId, $legacyPayload, $actorUserId);

    return $this->show($sourceType, $sourceId);
}
```

This preserves the requirement that the UI must not show fake success: V3 returns success only after the existing real service succeeds.

- [ ] **Step 5: Wire communication/proposal controller methods**

Replace in `CrmCaseController`:

```php
public function sendWhatsapp(Request $request, string $sourceType, int $sourceId): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->sendWhatsapp($sourceType, $sourceId, $request->all(), $request->user()?->id));
}

public function sendEmail(Request $request, string $sourceType, int $sourceId): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->sendEmail($sourceType, $sourceId, $request->all(), $request->user()?->id));
}

public function storeProposal(Request $request, string $sourceType, int $sourceId): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->storeProposal($sourceType, $sourceId, $request->all(), $request->user()?->id));
}
```

- [ ] **Step 6: Run tests and commit**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php
```

Expected: validation tests pass with 422, and real service failures propagate as panel-visible errors instead of fake success.

Commit:

```bash
git add laravel-app/app/Modules/CRM/Services/CrmCaseService.php laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php laravel-app/tests/Feature/CrmV3CaseControllerTest.php
git commit -m "feat(crm): guard v3 communication and proposal actions"
```

---

## Task 5: Add Frontend CRM V3 API and Types

**Files:**
- Modify: `laravel-app/resources/js/solicitudes-v3/types.ts`
- Modify: `laravel-app/resources/js/solicitudes-v3/api.ts`
- Modify: `laravel-app/resources/js/solicitudes-v3/api.test.ts`

- [ ] **Step 1: Add failing mapper/API tests**

Append to `api.test.ts`:

```ts
import { mapCrmCasePayload } from './api';

test('mapCrmCasePayload normalizes contacts notes tasks and activity', () => {
  const mapped = mapCrmCasePayload({
    case: { case_id: 'solicitud-275872', source_type: 'solicitud', source_id: 275872, form_id: 275872, patient_name: 'DANIELA', stage: 'revision-codigos', site: 'MATRIZ' },
    crm: { responsible_name: 'Coordinación', source: 'Convenio', insurance_plan: 'SALUD NIVEL 4' },
    contacts: { primary_phone: '0987107769', alternate_phones: ['0999999999'], primary_email: 'p@example.com', alternate_emails: [] },
    notes: [{ id: 1, body: 'Nota real', author_name: 'Jorge', created_at: '2026-06-03T10:00:00Z', can_delete: true }],
    tasks: [{ id: 2, title: 'Validar cobertura', status: 'pending', priority: 'alta', due_at: null }],
    activity: [{ id: 'note-1', type: 'note_created', occurred_at: '2026-06-03T10:00:00Z', author: 'Jorge', description: 'Nota creada', reference: { note_id: 1 } }],
    proposals: [],
    documents: [],
  });

  expect(mapped.contacts.primaryPhone).toBe('0987107769');
  expect(mapped.notes[0].body).toBe('Nota real');
  expect(mapped.tasks[0].title).toBe('Validar cobertura');
  expect(mapped.activity[0].type).toBe('note_created');
});
```

- [ ] **Step 2: Run test and verify failure**

Run:

```bash
cd laravel-app
node --experimental-strip-types --test resources/js/solicitudes-v3/api.test.ts
```

Expected: fail because `mapCrmCasePayload` does not exist.

- [ ] **Step 3: Add normalized types**

Add to `types.ts`:

```ts
export interface CrmCaseContactState {
  primaryPhone: string;
  alternatePhones: string[];
  primaryEmail: string;
  alternateEmails: string[];
}

export interface CrmCaseNote {
  id: number;
  body: string;
  authorName: string;
  createdAt: string;
  canDelete: boolean;
}

export interface CrmCaseTask {
  id: number;
  title: string;
  status: 'pending' | 'done' | 'cancelled' | string;
  priority: string;
  assignedTo?: number | null;
  dueAt?: string | null;
}

export interface CrmCaseActivity {
  id: string;
  type: string;
  occurredAt: string;
  author: string;
  description: string;
  reference: Record<string, unknown>;
}

export interface CrmCaseState {
  caseId: string;
  sourceType: string;
  sourceId: number;
  responsibleName: string;
  source: string;
  insurancePlan: string;
  contacts: CrmCaseContactState;
  notes: CrmCaseNote[];
  tasks: CrmCaseTask[];
  activity: CrmCaseActivity[];
  proposals: unknown[];
  documents: unknown[];
}
```

- [ ] **Step 4: Add mapper and request helpers**

Add to `api.ts`:

```ts
import type { CrmCaseState } from './types';

const asString = (value: unknown, fallback = ''): string =>
  typeof value === 'string' && value.trim() !== '' ? value : fallback;

const asArray = <T>(value: unknown): T[] => Array.isArray(value) ? value as T[] : [];

export function mapCrmCasePayload(raw: any): CrmCaseState {
  return {
    caseId: asString(raw?.case?.case_id),
    sourceType: asString(raw?.case?.source_type),
    sourceId: Number(raw?.case?.source_id ?? 0),
    responsibleName: asString(raw?.crm?.responsible_name, 'Coordinación'),
    source: asString(raw?.crm?.source, '—'),
    insurancePlan: asString(raw?.crm?.insurance_plan, '—'),
    contacts: {
      primaryPhone: asString(raw?.contacts?.primary_phone, '—'),
      alternatePhones: asArray<string>(raw?.contacts?.alternate_phones),
      primaryEmail: asString(raw?.contacts?.primary_email, '—'),
      alternateEmails: asArray<string>(raw?.contacts?.alternate_emails),
    },
    notes: asArray<any>(raw?.notes).map((n) => ({
      id: Number(n.id),
      body: asString(n.body),
      authorName: asString(n.author_name, 'Usuario'),
      createdAt: asString(n.created_at),
      canDelete: Boolean(n.can_delete),
    })),
    tasks: asArray<any>(raw?.tasks).map((t) => ({
      id: Number(t.id),
      title: asString(t.title),
      status: asString(t.status, 'pending'),
      priority: asString(t.priority, 'normal'),
      assignedTo: t.assigned_to == null ? null : Number(t.assigned_to),
      dueAt: t.due_at ?? null,
    })),
    activity: asArray<any>(raw?.activity).map((a) => ({
      id: asString(a.id),
      type: asString(a.type),
      occurredAt: asString(a.occurred_at),
      author: asString(a.author, 'Sistema'),
      description: asString(a.description),
      reference: (a.reference && typeof a.reference === 'object') ? a.reference : {},
    })),
    proposals: asArray(raw?.proposals),
    documents: asArray(raw?.documents),
  };
}

async function crmJson<T>(url: string, init?: RequestInit): Promise<T> {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...(init?.headers ?? {}) },
    ...init,
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok || body.success === false) {
    throw new Error(body.error || body.message || 'No se pudo completar la acción');
  }
  return body.data as T;
}

export async function fetchCrmCase(sourceType: string, sourceId: number): Promise<CrmCaseState> {
  const data = await crmJson<any>(`/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}`);
  return mapCrmCasePayload(data);
}

export async function createCrmNote(sourceType: string, sourceId: number, body: string): Promise<CrmCaseState> {
  const data = await crmJson<any>(`/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}/notes`, {
    method: 'POST',
    body: JSON.stringify({ body }),
  });
  return mapCrmCasePayload(data);
}
```

- [ ] **Step 5: Run JS tests and commit**

Run:

```bash
cd laravel-app
node --experimental-strip-types --test resources/js/solicitudes-v3/api.test.ts
npx tsc --noEmit
```

Commit:

```bash
git add laravel-app/resources/js/solicitudes-v3/types.ts laravel-app/resources/js/solicitudes-v3/api.ts laravel-app/resources/js/solicitudes-v3/api.test.ts
git commit -m "feat(solicitudes-v3): add crm v3 client contract"
```

---

## Task 6: Wire DetailPanel to Real CRM State

**Files:**
- Modify: `laravel-app/resources/js/solicitudes-v3/App.tsx`
- Modify: `laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx`
- Modify: `laravel-app/resources/css/solicitudes-v3.css`
- Test: `laravel-app/resources/js/solicitudes-v3/api.test.ts`

- [ ] **Step 1: Add UI state contract helpers**

In `DetailPanel.tsx`, introduce action status type:

```ts
type ActionState = {
  loading: boolean;
  error: string | null;
};

const idleAction: ActionState = { loading: false, error: null };
```

- [ ] **Step 2: Remove duplicate checklist from Seguimiento**

Replace the checklist section in `TabSeguimiento` with activity only. The rendered sections must be: procedure bar, details CRM, activity. Keep details CRM fields unchanged except source them from `crmCase` when available.

```tsx
type SeguimientoProps = {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
};

function TabSeguimiento({ sol, crmCase }: SeguimientoProps) {
  const timeline = crmCase?.activity ?? buildTimeline(sol);
  const telefono = crmCase?.contacts.primaryPhone || (sol.detalle.paciente.telefono !== '—' ? sol.detalle.paciente.telefono : sol.crm.telefono);
  const planAfiliacion = crmCase?.insurancePlan || (sol.plan_seguro !== '—' ? sol.plan_seguro : sol.afiliacion_label);
  // render existing JSX, omitting the Checklist operativo section
}
```

- [ ] **Step 3: Wire note save/delete to API**

Modify `DetailPanelProps`:

```ts
import type { CrmCaseState } from './types';

export interface DetailPanelProps {
  sol: Solicitud | null;
  crmCase: CrmCaseState | null;
  crmLoading: boolean;
  crmError: string | null;
  open: boolean;
  onClose: () => void;
  onAddNote: (txt: string) => Promise<void>;
  onDeleteNote: (noteId: number) => Promise<void>;
  // keep existing props for estado/prefactura until their tasks are migrated
}
```

Update `TabNotas`:

```tsx
function TabNotas({
  crmCase,
  onAddNote,
  onDeleteNote,
}: {
  crmCase: CrmCaseState | null;
  onAddNote: (txt: string) => Promise<void>;
  onDeleteNote: (noteId: number) => Promise<void>;
}) {
  const [txt, setTxt] = useState('');
  const [state, setState] = useState<ActionState>(idleAction);
  const notas = crmCase?.notes ?? [];

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!txt.trim()) return;
    setState({ loading: true, error: null });
    try {
      await onAddNote(txt.trim());
      setTxt('');
      setState(idleAction);
    } catch (err) {
      setState({ loading: false, error: err instanceof Error ? err.message : 'No se pudo guardar la nota' });
    }
  };

  return (
    <section>
      <h3 className="psec-title"><i className="mdi mdi-note-text-outline"></i>Notas internas <span className="psec-meta">{notas.length}</span></h3>
      <div className="notes-list">
        {notas.length === 0 && <div className="mini-empty">Aún no hay notas</div>}
        {notas.map((n) => (
          <div className="note-row" key={n.id}>
            <span className="note-av"><DocAvatar name={n.authorName} cls="" /></span>
            <div className="note-body"><div className="nb-txt">{n.body}</div><div className="nb-meta">{n.authorName} · {fmtDateTime(n.createdAt)}</div></div>
            {n.canDelete && <button className="icon-btn" onClick={() => onDeleteNote(n.id)} aria-label="Borrar nota"><i className="mdi mdi-trash-can-outline"></i></button>}
          </div>
        ))}
      </div>
      <form className="add-form col crm-note-form" onSubmit={submit}>
        <textarea className="fld" rows={5} placeholder="Registrar avance del caso…" value={txt} onChange={(e) => setTxt(e.target.value)} />
        {state.error && <div className="form-error">{state.error}</div>}
        <button className="btn-add self-end" type="submit" disabled={state.loading}><i className="mdi mdi-comment-plus-outline"></i>{state.loading ? 'Guardando…' : 'Guardar nota'}</button>
      </form>
    </section>
  );
}
```

- [ ] **Step 4: Fetch and refresh CRM case in App**

Import API methods in `App.tsx`:

```ts
import { fetchCrmCase, createCrmNote } from './api';
import type { CrmCaseState } from './types';
```

Add state:

```ts
const [crmCase, setCrmCase] = useState<CrmCaseState | null>(null);
const [crmLoading, setCrmLoading] = useState(false);
const [crmError, setCrmError] = useState<string | null>(null);
```

Load on selected change:

```ts
useEffect(() => {
  if (!selectedId) {
    setCrmCase(null);
    return;
  }
  setCrmLoading(true);
  setCrmError(null);
  fetchCrmCase('solicitud', selectedId)
    .then(setCrmCase)
    .catch((err) => setCrmError(err instanceof Error ? err.message : 'No se pudo cargar CRM'))
    .finally(() => setCrmLoading(false));
}, [selectedId]);
```

Replace local `addNote` handler:

```ts
const addNote = useCallback(async (txt: string) => {
  if (!selectedId) return;
  const updated = await createCrmNote('solicitud', selectedId, txt);
  setCrmCase(updated);
  showToast('Nota guardada', 'mdi-comment-check-outline');
}, [selectedId, showToast]);
```

Pass new props to `DetailPanel`.

- [ ] **Step 5: Add minimal CSS for existing design**

Add to `solicitudes-v3.css`:

```css
.crm-note-form textarea {
  width: 100%;
  min-height: 120px;
  resize: vertical;
}

.form-error {
  color: var(--danger);
  font-size: 12px;
  font-weight: 700;
}

.icon-btn {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
}
```

- [ ] **Step 6: Run type/build and commit**

Run:

```bash
cd laravel-app
npx tsc --noEmit
npm run build
```

Commit:

```bash
git add laravel-app/resources/js/solicitudes-v3/App.tsx laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx laravel-app/resources/css/solicitudes-v3.css
git commit -m "feat(solicitudes-v3): connect notes and activity to crm v3"
```

---

## Task 7: Wire Tasks, Communication and Proposal UI Without Fake Success

**Files:**
- Modify: `laravel-app/resources/js/solicitudes-v3/api.ts`
- Modify: `laravel-app/resources/js/solicitudes-v3/App.tsx`
- Modify: `laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx`

- [ ] **Step 1: Add frontend API methods**

Add to `api.ts`:

```ts
export async function createCrmTask(sourceType: string, sourceId: number, payload: { title: string; priority: string; due_at?: string | null }): Promise<CrmCaseState> {
  const data = await crmJson<any>(`/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}/tasks`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return mapCrmCasePayload(data);
}

export async function updateCrmTask(sourceType: string, sourceId: number, taskId: number, payload: { status?: string }): Promise<CrmCaseState> {
  const data = await crmJson<any>(`/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}/tasks/${taskId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
  return mapCrmCasePayload(data);
}

export async function sendCrmWhatsapp(sourceType: string, sourceId: number, payload: { recipients: string[]; message: string }): Promise<CrmCaseState> {
  const data = await crmJson<any>(`/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}/whatsapp`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return mapCrmCasePayload(data);
}

export async function sendCrmEmail(sourceType: string, sourceId: number, payload: { to: string[]; cc?: string[]; subject: string; body: string }): Promise<CrmCaseState> {
  const data = await crmJson<any>(`/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}/email`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return mapCrmCasePayload(data);
}
```

- [ ] **Step 2: Replace local task handlers in App**

In `App.tsx`, replace `addTask` and `toggleTask` bodies:

```ts
const addTask = useCallback(async (title: string, priority: string) => {
  if (!selectedId) return;
  const updated = await createCrmTask('solicitud', selectedId, { title, priority });
  setCrmCase(updated);
  showToast('Tarea añadida', 'mdi-playlist-check');
}, [selectedId, showToast]);

const toggleTask = useCallback(async (taskId: number, currentStatus: string) => {
  if (!selectedId) return;
  const updated = await updateCrmTask('solicitud', selectedId, taskId, {
    status: currentStatus === 'done' ? 'pending' : 'done',
  });
  setCrmCase(updated);
}, [selectedId]);
```

- [ ] **Step 3: Update `TabTareas` to render `crmCase.tasks`**

Use `CrmCaseTask` rows:

```tsx
const tareas = crmCase?.tasks ?? [];
{tareas.map((tk) => (
  <div key={tk.id} className={`task-row ${tk.status === 'done' ? 'done' : ''}`} onClick={() => onToggleTask(tk.id, tk.status)}>
    <span className="chk-box">{tk.status === 'done' && <i className="mdi mdi-check"></i>}</span>
    <div className="task-body">
      <div className="task-title">{tk.title}</div>
      <div className="task-meta"><i className="mdi mdi-calendar-blank-outline"></i>{tk.dueAt ? fmtDate(tk.dueAt) : '—'}</div>
    </div>
    <span className={`prio-tag prio-${PRIO_TONE[tk.priority] || 'ok'}`}>{tk.priority}</span>
  </div>
))}
```

- [ ] **Step 4: Replace communication fake toasts with API calls**

In `TabComunicacion`, build recipients from `crmCase.contacts`:

```tsx
const phoneOptions = [crmCase?.contacts.primaryPhone, ...(crmCase?.contacts.alternatePhones ?? [])].filter(Boolean) as string[];
const emailOptions = [crmCase?.contacts.primaryEmail, ...(crmCase?.contacts.alternateEmails ?? [])].filter(Boolean) as string[];
```

Submit WhatsApp:

```tsx
await onSendWhatsapp({ recipients: selectedPhones, message: wa.trim() });
```

Submit email:

```tsx
await onSendEmail({ to: selectedEmails, subject: subject.trim(), body: emailBody.trim() });
```

Show `form-error` if API throws. Do not call `showToast` unless API succeeds.

- [ ] **Step 5: Disable proposal fake builder**

In `TabPropuestas`, remove the hardcoded `onAddProposal` creation. The "Nuevo borrador de propuesta" button opens a real builder state with search inputs. Until catalog search is wired, render:

```tsx
<button className="btn-add full" disabled title="Conecta primero el buscador de catálogo en esta tarea">
  <i className="mdi mdi-file-document-plus-outline"></i>Nuevo borrador de propuesta
</button>
```

This satisfies the no-fake-success requirement while Task 8 implements catalog/proposal creation.

- [ ] **Step 6: Run build and commit**

Run:

```bash
cd laravel-app
npx tsc --noEmit
npm run build
```

Commit:

```bash
git add laravel-app/resources/js/solicitudes-v3/api.ts laravel-app/resources/js/solicitudes-v3/App.tsx laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx
git commit -m "feat(solicitudes-v3): remove fake crm actions"
```

---

## Task 8: Implement Catalog Search and Proposal Builder Skeleton

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php`
- Modify: `laravel-app/app/Modules/CRM/Services/CrmCaseService.php`
- Modify: `laravel-app/resources/js/solicitudes-v3/api.ts`
- Modify: `laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx`
- Test: `laravel-app/tests/Feature/CrmV3CaseControllerTest.php`

- [ ] **Step 1: Add backend catalog route tests**

Append:

```php
public function test_catalog_search_requires_query(): void
{
    $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId(['username' => 'catalog']);
    $this->actingAs(\App\Models\User::query()->findOrFail($userId));

    $this->getJson('/v3/crm/catalog/codes?q=')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', []);
}
```

- [ ] **Step 2: Wire catalog methods**

In `CrmCaseController`:

```php
public function catalogCodes(Request $request): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->catalogCodes(
        trim((string) $request->query('q', '')),
        trim((string) $request->query('affiliation', '')),
        max(1, min(50, (int) $request->query('limit', 20)))
    ));
}

public function catalogPackages(Request $request): JsonResponse
{
    return $this->jsonAction(fn () => $this->cases->catalogPackages(
        trim((string) $request->query('q', '')),
        trim((string) $request->query('affiliation', '')),
        max(1, min(50, (int) $request->query('limit', 20)))
    ));
}
```

In `CrmCaseService`, delegate to existing catalog services:

```php
public function catalogCodes(string $query, string $affiliation, int $limit): array
{
    if ($query === '') {
        return [];
    }

    return (new \App\Modules\Codes\Services\CodesCatalogService())->quickSearch($query, $limit, $affiliation);
}

public function catalogPackages(string $query, string $affiliation, int $limit): array
{
    if ($query === '') {
        return [];
    }

    $packages = new \App\Modules\Codes\Services\CodesPackageService(DB::connection()->getPdo());
    return $packages->list([
        'active' => 1,
        'search' => $query,
        'afiliacion' => $affiliation,
        'limit' => $limit,
        'offset' => 0,
    ]);
}
```

- [ ] **Step 3: Add frontend search APIs**

Add to `api.ts`:

```ts
export async function searchCrmCatalogCodes(query: string, affiliation: string): Promise<any[]> {
  return crmJson<any[]>(`/v3/crm/catalog/codes?q=${encodeURIComponent(query)}&affiliation=${encodeURIComponent(affiliation)}`);
}

export async function searchCrmCatalogPackages(query: string, affiliation: string): Promise<any[]> {
  return crmJson<any[]>(`/v3/crm/catalog/packages?q=${encodeURIComponent(query)}&affiliation=${encodeURIComponent(affiliation)}`);
}
```

- [ ] **Step 4: Implement proposal builder skeleton without manual items**

In `TabPropuestas`, add a search field, tabs for `Codigos`/`Paquetes`, and render selected catalog items. The add action only accepts results returned by search APIs:

```tsx
const [query, setQuery] = useState('');
const [results, setResults] = useState<any[]>([]);
const [selectedItems, setSelectedItems] = useState<any[]>([]);

const runSearch = async () => {
  if (!query.trim()) return;
  const data = await searchCrmCatalogCodes(query.trim(), crmCase?.insurancePlan ?? '');
  setResults(data);
};
```

The create button remains disabled until `selectedItems.length > 0`.

- [ ] **Step 5: Run tests/build and commit**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php --filter=catalog
npx tsc --noEmit
npm run build
```

Commit:

```bash
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmCaseController.php laravel-app/app/Modules/CRM/Services/CrmCaseService.php laravel-app/resources/js/solicitudes-v3/api.ts laravel-app/resources/js/solicitudes-v3/DetailPanel.tsx laravel-app/tests/Feature/CrmV3CaseControllerTest.php
git commit -m "feat(crm): add v3 catalog-backed proposal search"
```

---

## Task 9: Lock Prefactura Contract for Real Diagnoses and Clinical Aptitude

**Files:**
- Modify: `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesReadController.php`
- Modify: `laravel-app/resources/js/solicitudes-v3/api.ts`
- Modify: `laravel-app/resources/js/solicitudes-v3/Prefactura.tsx`
- Test: `laravel-app/tests/Feature/SolicitudesV3PrefacturaContractTest.php`
- Test: `laravel-app/resources/js/solicitudes-v3/api.test.ts`

- [ ] **Step 1: Add PHP contract test for assigned diagnosis**

Create `SolicitudesV3PrefacturaContractTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class SolicitudesV3PrefacturaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_detalle_includes_diagnosticos_asignados_by_form_id(): void
    {
        $userId = DB::table('users')->insertGetId(['username' => 'prefactura']);
        $this->actingAs(\App\Models\User::query()->findOrFail($userId));

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('hc_number')->nullable();
            $table->timestamps();
        });
        Schema::create('diagnosticos_asignados', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->index();
            $table->string('tipo')->nullable();
            $table->string('codigo')->nullable();
            $table->string('descripcion')->nullable();
            $table->unsignedTinyInteger('principal')->nullable();
            $table->string('lateralidad')->nullable();
            $table->timestamps();
        });

        DB::table('solicitud_procedimiento')->insert([
            'id' => 275872,
            'form_id' => 275872,
            'full_name' => 'DANIELA VALENTINA MORALES MURILLO',
            'hc_number' => '0932000904',
        ]);
        DB::table('diagnosticos_asignados')->insert([
            'id' => 70030,
            'form_id' => 275872,
            'tipo' => 'consulta',
            'codigo' => 'H00',
            'descripcion' => 'ORZUELO Y CALACIO',
            'principal' => 1,
            'lateralidad' => 'DERECHO',
        ]);

        $this->getJson('/v3/solicitudes/275872/detalle')
            ->assertOk()
            ->assertJsonPath('data.diagnosticos.0.codigo', 'H00')
            ->assertJsonPath('data.diagnosticos.0.descripcion', 'ORZUELO Y CALACIO')
            ->assertJsonPath('data.diagnosticos.0.lateralidad', 'DERECHO');
    }
}
```

- [ ] **Step 2: Run prefactura contract test**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/SolicitudesV3PrefacturaContractTest.php
```

Expected: pass if previous diagnosis fix remains; fail if route response wraps differently. Adjust only the response path, not the requirement.

- [ ] **Step 3: Add frontend mapper test for diagnosis list**

Extend existing `api.test.ts` with a detail fixture containing:

```ts
diagnosticos: [{ codigo: 'H00', descripcion: 'ORZUELO Y CALACIO', lateralidad: 'DERECHO' }]
```

Assert the mapped detail renders a non-empty diagnosis list.

- [ ] **Step 4: Ensure Prefactura Cirugia remains clinical**

In `Prefactura.tsx`, keep Cirugia tab centered on:

```tsx
<section>
  <h3 className="pf-sec-title">Aptitud clínica</h3>
  {/* Estado anestesia + estado oftalmólogo from real mapped fields */}
</section>
```

If no real preop checklist exists:

```tsx
<div className="mini-empty">Sin checklist preoperatorio registrado</div>
```

- [ ] **Step 5: Run all checks and commit**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/SolicitudesV3PrefacturaContractTest.php
node --experimental-strip-types --test resources/js/solicitudes-v3/api.test.ts
npx tsc --noEmit
npm run build
```

Commit:

```bash
git add laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesReadController.php laravel-app/resources/js/solicitudes-v3/api.ts laravel-app/resources/js/solicitudes-v3/Prefactura.tsx laravel-app/resources/js/solicitudes-v3/api.test.ts laravel-app/tests/Feature/SolicitudesV3PrefacturaContractTest.php
git commit -m "test(solicitudes-v3): lock prefactura real data contract"
```

---

## Task 10: Final Integration Verification

**Files:**
- Modify only files needed to fix integration failures found by the commands below.

- [ ] **Step 1: Run PHP feature tests**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/CrmV3CaseControllerTest.php tests/Feature/SolicitudesV3PrefacturaContractTest.php tests/Feature/CrmLegacyProposalContractTest.php
```

Expected: all pass. If `CrmLegacyProposalContractTest` fails, fix V3 wrappers so they do not break existing V2 proposal behavior.

- [ ] **Step 2: Run frontend tests and typecheck**

Run:

```bash
cd laravel-app
node --experimental-strip-types --test resources/js/solicitudes-v3/api.test.ts
npx tsc --noEmit
npm run build
```

Expected: all pass.

- [ ] **Step 3: Manual browser verification**

Start app with the existing local workflow used in this repo. Open `/v3/solicitudes` and verify:

- Seguimiento has no duplicate checklist.
- Tareas owns the checklist/tasks.
- Notes save through API and show after panel refresh.
- Notes delete only shows for deletable notes.
- WhatsApp/mail do not show success when backend rejects action.
- Propuestas does not show the hardcoded package.
- Prefactura case tab shows `H00 - ORZUELO Y CALACIO` for `form_id=275872` when test data exists.
- Cirugia tab shows clinical aptitude information, not the generic operational checklist.

- [ ] **Step 4: Commit final fixes**

Commit only if Step 1-3 required fixes:

```bash
git add laravel-app
git commit -m "fix(solicitudes-v3): complete crm operative integration"
```
