# CRM Reinvention — Plan A: Core (Migraciones, Modelos, Eventos, Servicios)

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development**. Dispatch a fresh subagent per task con spec + quality review.
>
> **Execution mode:** subagent-driven (~1 día)
>
> **Parte de:** CRM Reinvention — continúa en `2026-05-28-onda5-crm-b-api.md`
>
> **Prerequisito:** ninguno — este plan es la base de todos los demás.

**Goal:** Crear las 3 tablas CRM centrales, sus modelos Eloquent, el sistema de eventos Laravel, y los 4 servicios del dominio CRM (ContactResolver, Opportunity, Activity, Stats).

**Architecture:** Event-driven. Tres tablas (`crm_contacts`, `crm_opportunities`, `crm_activities`) con sus modelos. Tres eventos (`WhatsappLeadQualified`, `SolicitudCreada`, `ExamenSolicitado`) escuchados por `CrmOpportunityListener` via `EventServiceProvider`. Los servicios encapsulan toda la lógica de negocio; los controladores (Plan B) solo orquestan.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite (tests), PHPUnit. Módulo en `laravel-app/app/Modules/CRM/`. Modelos en `laravel-app/app/Models/`.

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|----------------|
| `database/migrations/2026_05_28_100000_create_crm_contacts_table.php` | Create | Tabla crm_contacts con campos y enums |
| `database/migrations/2026_05_28_100001_create_crm_opportunities_table.php` | Create | Tabla crm_opportunities con FK y morph |
| `database/migrations/2026_05_28_100002_create_crm_activities_table.php` | Create | Tabla crm_activities con timeline |
| `database/migrations/2026_05_28_100003_add_crm_opportunity_id_to_solicitudes.php` | Create | FK inversa en tabla solicitudes |
| `app/Models/CrmContact.php` | Create | Eloquent model con relaciones y scopes |
| `app/Models/CrmOpportunity.php` | Create | Eloquent model con relaciones y casts |
| `app/Models/CrmActivity.php` | Create | Eloquent model |
| `app/Events/Crm/WhatsappLeadQualified.php` | Create | Evento: lead WA calificado |
| `app/Events/Crm/SolicitudCreada.php` | Create | Evento: nueva solicitud creada |
| `app/Events/Crm/ExamenSolicitado.php` | Create | Evento: examen sin confirmación |
| `app/Listeners/CrmOpportunityListener.php` | Create | Listener que escucha los 3 eventos |
| `app/Providers/EventServiceProvider.php` | Create | Registra eventos → listener |
| `app/Providers/AppServiceProvider.php` | Modify | Registra EventServiceProvider |
| `app/Modules/CRM/Services/CrmContactResolverService.php` | Create | Deduplicación y resolución de contactos |
| `app/Modules/CRM/Services/CrmOpportunityService.php` | Create | CRUD + cambios de etapa |
| `app/Modules/CRM/Services/CrmActivityService.php` | Create | Registro de actividades |
| `app/Modules/CRM/Services/CrmStatsService.php` | Create | KPIs del panel |
| `tests/Feature/CrmContactResolverServiceTest.php` | Create | Tests de deduplicación |
| `tests/Feature/CrmOpportunityListenerTest.php` | Create | Tests del listener (evento → oportunidad) |

---

## Task 1: Migraciones — tres tablas CRM

**Files:**
- Create: `laravel-app/database/migrations/2026_05_28_100000_create_crm_contacts_table.php`
- Create: `laravel-app/database/migrations/2026_05_28_100001_create_crm_opportunities_table.php`
- Create: `laravel-app/database/migrations/2026_05_28_100002_create_crm_activities_table.php`
- Create: `laravel-app/database/migrations/2026_05_28_100003_add_crm_opportunity_id_to_solicitudes.php`

- [ ] **Step 1: Crear migración crm_contacts**

```php
<?php
// laravel-app/database/migrations/2026_05_28_100000_create_crm_contacts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('cedula', 30)->nullable();
            // provisional | identified | linked
            $table->string('resolution', 20)->default('provisional');
            // whatsapp | solicitud | examen | manual
            $table->string('source', 30)->default('manual');
            $table->timestamps();

            $table->unique(['cedula'], 'uq_crm_contacts_cedula');
            $table->index(['phone']);
            $table->index(['resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
```

- [ ] **Step 2: Crear migración crm_opportunities**

```php
<?php
// laravel-app/database/migrations/2026_05_28_100001_create_crm_opportunities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('title', 255);
            // nuevo | en_contacto | interesado | propuesta_enviada | ganado | perdido
            $table->string('stage', 30)->default('nuevo');
            // whatsapp | solicitud | examen | manual
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['stage']);
            $table->index(['source', 'source_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
```

- [ ] **Step 3: Crear migración crm_activities**

```php
<?php
// laravel-app/database/migrations/2026_05_28_100002_create_crm_activities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            // nota | llamada | cambio_etapa | email
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['opportunity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
```

- [ ] **Step 4: Crear migración FK inversa en solicitudes**

```php
<?php
// laravel-app/database/migrations/2026_05_28_100003_add_crm_opportunity_id_to_solicitudes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table): void {
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table): void {
            $table->dropColumn('crm_opportunity_id');
        });
    }
};
```

- [ ] **Step 5: Correr migraciones y verificar**

```bash
cd laravel-app && php artisan migrate
```

Esperado: 4 migraciones nuevas corridas sin errores.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_05_28_100000_create_crm_contacts_table.php \
        database/migrations/2026_05_28_100001_create_crm_opportunities_table.php \
        database/migrations/2026_05_28_100002_create_crm_activities_table.php \
        database/migrations/2026_05_28_100003_add_crm_opportunity_id_to_solicitudes.php
git commit -m "feat(crm): add crm_contacts, crm_opportunities, crm_activities migrations"
```

---

## Task 2: Modelos Eloquent

**Files:**
- Create: `laravel-app/app/Models/CrmContact.php`
- Create: `laravel-app/app/Models/CrmOpportunity.php`
- Create: `laravel-app/app/Models/CrmActivity.php`

- [ ] **Step 1: Crear CrmContact**

```php
<?php
// laravel-app/app/Models/CrmContact.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    protected $table = 'crm_contacts';

    protected $fillable = [
        'patient_id', 'name', 'phone', 'email',
        'cedula', 'resolution', 'source',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const RESOLUTION_PROVISIONAL = 'provisional';
    public const RESOLUTION_IDENTIFIED  = 'identified';
    public const RESOLUTION_LINKED      = 'linked';

    public const SOURCE_WHATSAPP  = 'whatsapp';
    public const SOURCE_SOLICITUD = 'solicitud';
    public const SOURCE_EXAMEN    = 'examen';
    public const SOURCE_MANUAL    = 'manual';

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'contact_id');
    }

    public function scopeProvisional($query)
    {
        return $query->where('resolution', self::RESOLUTION_PROVISIONAL);
    }

    public function scopeByPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    public function scopeByCedula($query, string $cedula)
    {
        return $query->where('cedula', $cedula);
    }
}
```

- [ ] **Step 2: Crear CrmOpportunity**

```php
<?php
// laravel-app/app/Models/CrmOpportunity.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmOpportunity extends Model
{
    protected $table = 'crm_opportunities';

    protected $fillable = [
        'contact_id', 'title', 'stage', 'source',
        'source_id', 'source_type', 'assigned_to', 'lost_reason',
    ];

    protected $casts = [
        'contact_id'  => 'integer',
        'source_id'   => 'integer',
        'assigned_to' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public const STAGE_NUEVO            = 'nuevo';
    public const STAGE_EN_CONTACTO      = 'en_contacto';
    public const STAGE_INTERESADO       = 'interesado';
    public const STAGE_PROPUESTA        = 'propuesta_enviada';
    public const STAGE_GANADO           = 'ganado';
    public const STAGE_PERDIDO          = 'perdido';

    public const STAGES = [
        self::STAGE_NUEVO,
        self::STAGE_EN_CONTACTO,
        self::STAGE_INTERESADO,
        self::STAGE_PROPUESTA,
        self::STAGE_GANADO,
        self::STAGE_PERDIDO,
    ];

    public const SOURCE_ENTRY_STAGE = [
        'whatsapp'  => self::STAGE_NUEVO,
        'solicitud' => self::STAGE_INTERESADO,
        'examen'    => self::STAGE_PROPUESTA,
        'manual'    => self::STAGE_NUEVO,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id')->orderBy('created_at', 'desc');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo('sourceable', 'source_type', 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_GANADO, self::STAGE_PERDIDO]);
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeUrgent($query, int $waHours = 6, int $defaultHours = 48)
    {
        return $query->active()->where(function ($q) use ($waHours, $defaultHours): void {
            $q->where(function ($sub) use ($waHours): void {
                $sub->where('source', 'whatsapp')
                    ->where('updated_at', '<', now()->subHours($waHours));
            })->orWhere(function ($sub) use ($defaultHours): void {
                $sub->whereIn('source', ['solicitud', 'examen'])
                    ->where('updated_at', '<', now()->subHours($defaultHours));
            });
        });
    }
}
```

- [ ] **Step 3: Crear CrmActivity**

```php
<?php
// laravel-app/app/Models/CrmActivity.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $table = 'crm_activities';
    public $timestamps = false;

    protected $fillable = [
        'opportunity_id', 'type', 'description', 'user_id',
    ];

    protected $casts = [
        'opportunity_id' => 'integer',
        'user_id'        => 'integer',
        'created_at'     => 'datetime',
    ];

    public const TYPE_NOTA         = 'nota';
    public const TYPE_LLAMADA      = 'llamada';
    public const TYPE_CAMBIO_ETAPA = 'cambio_etapa';
    public const TYPE_EMAIL        = 'email';

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/CrmContact.php app/Models/CrmOpportunity.php app/Models/CrmActivity.php
git commit -m "feat(crm): add CrmContact, CrmOpportunity, CrmActivity Eloquent models"
```

---

## Task 3: Eventos Laravel

**Files:**
- Create: `laravel-app/app/Events/Crm/WhatsappLeadQualified.php`
- Create: `laravel-app/app/Events/Crm/SolicitudCreada.php`
- Create: `laravel-app/app/Events/Crm/ExamenSolicitado.php`

- [ ] **Step 1: Crear directorio y WhatsappLeadQualified**

```bash
mkdir -p laravel-app/app/Events/Crm
```

```php
<?php
// laravel-app/app/Events/Crm/WhatsappLeadQualified.php

namespace App\Events\Crm;

use App\Models\WhatsappLead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappLeadQualified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsappLead $lead,
        public readonly ?int $actorUserId = null,
    ) {}
}
```

- [ ] **Step 2: Crear SolicitudCreada**

```php
<?php
// laravel-app/app/Events/Crm/SolicitudCreada.php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudCreada
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $solicitudData  Snapshot mínimo: id, paciente_nombre, paciente_cedula, paciente_telefono, servicio
     */
    public function __construct(
        public readonly int $solicitudId,
        public readonly array $solicitudData,
    ) {}
}
```

- [ ] **Step 3: Crear ExamenSolicitado**

```php
<?php
// laravel-app/app/Events/Crm/ExamenSolicitado.php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamenSolicitado
{
    use Dispatchable, SerializesModels;

    /**
     * Se dispara cuando un examen queda ordenado sin pago ni confirmación operativa.
     *
     * @param array<string, mixed> $examenData  id, paciente_nombre, paciente_cedula, paciente_telefono, descripcion_examen
     */
    public function __construct(
        public readonly int $examenId,
        public readonly array $examenData,
    ) {}
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Events/Crm/
git commit -m "feat(crm): add WhatsappLeadQualified, SolicitudCreada, ExamenSolicitado events"
```

---

## Task 4: CrmContactResolverService (lógica de deduplicación)

**Files:**
- Create: `laravel-app/app/Modules/CRM/Services/CrmContactResolverService.php`
- Create: `laravel-app/tests/Feature/CrmContactResolverServiceTest.php`

- [ ] **Step 1: Escribir tests que fallan**

```php
<?php
// laravel-app/tests/Feature/CrmContactResolverServiceTest.php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Modules\CRM\Services\CrmContactResolverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmContactResolverServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_contacts');
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
    }

    public function test_creates_provisional_contact_when_no_cedula(): void
    {
        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'María González',
            cedula: null,
            source: 'whatsapp',
        );

        $this->assertInstanceOf(CrmContact::class, $contact);
        $this->assertEquals('provisional', $contact->resolution);
        $this->assertNull($contact->cedula);
    }

    public function test_creates_identified_contact_when_cedula_provided(): void
    {
        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'Carlos Mendoza',
            cedula: '0912345678',
            source: 'solicitud',
        );

        $this->assertEquals('identified', $contact->resolution);
        $this->assertEquals('0912345678', $contact->cedula);
    }

    public function test_reuses_existing_contact_by_cedula(): void
    {
        CrmContact::query()->create([
            'name' => 'Carlos', 'phone' => '+593991111111',
            'cedula' => '0912345678', 'resolution' => 'identified', 'source' => 'whatsapp',
        ]);

        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593992222222',
            name: 'Carlos Mendoza',
            cedula: '0912345678',
            source: 'solicitud',
        );

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals('Carlos Mendoza', $contact->fresh()->name);
    }

    public function test_reuses_provisional_contact_by_phone(): void
    {
        CrmContact::query()->create([
            'name' => 'María', 'phone' => '+593991234567',
            'cedula' => null, 'resolution' => 'provisional', 'source' => 'whatsapp',
        ]);

        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'María González',
            cedula: null,
            source: 'whatsapp',
        );

        $this->assertEquals(1, CrmContact::query()->count());
    }

    public function test_upgrades_provisional_to_identified_when_cedula_arrives(): void
    {
        CrmContact::query()->create([
            'name' => 'Ana', 'phone' => '+593991234567',
            'cedula' => null, 'resolution' => 'provisional', 'source' => 'whatsapp',
        ]);

        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'Ana Torres',
            cedula: '1712345678',
            source: 'whatsapp',
        );

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals('identified', $contact->fresh()->resolution);
        $this->assertEquals('1712345678', $contact->fresh()->cedula);
    }
}
```

- [ ] **Step 2: Correr tests para confirmar que fallan**

```bash
cd laravel-app && php artisan test tests/Feature/CrmContactResolverServiceTest.php
```

Esperado: ERROR — `CrmContactResolverService` no existe todavía.

- [ ] **Step 3: Implementar CrmContactResolverService**

```php
<?php
// laravel-app/app/Modules/CRM/Services/CrmContactResolverService.php

namespace App\Modules\CRM\Services;

use App\Models\CrmContact;
use Illuminate\Support\Facades\DB;

class CrmContactResolverService
{
    /**
     * Encuentra o crea un contacto CRM resolviendo la identidad con la estrategia correcta.
     *
     * Prioridad: cédula (fuerte) > teléfono provisional (débil)
     */
    public function resolve(
        string $phone,
        string $name,
        ?string $cedula,
        string $source,
        ?int $patientId = null,
    ): CrmContact {
        return DB::transaction(function () use ($phone, $name, $cedula, $source, $patientId): CrmContact {
            // 1. Match fuerte por cédula
            if ($cedula !== null && $cedula !== '') {
                $existing = CrmContact::query()->byCedula($cedula)->first();

                if ($existing instanceof CrmContact) {
                    $existing->fill(['name' => $name, 'phone' => $phone]);
                    if ($patientId !== null && $existing->patient_id === null) {
                        $existing->patient_id = $patientId;
                        $existing->resolution = CrmContact::RESOLUTION_LINKED;
                    }
                    $existing->save();
                    return $existing;
                }

                // ¿Existe contacto provisional con ese teléfono? Upgrade.
                $provisional = CrmContact::query()->byPhone($phone)->provisional()->first();
                if ($provisional instanceof CrmContact) {
                    $provisional->fill([
                        'name'       => $name,
                        'cedula'     => $cedula,
                        'resolution' => $patientId !== null
                            ? CrmContact::RESOLUTION_LINKED
                            : CrmContact::RESOLUTION_IDENTIFIED,
                        'patient_id' => $patientId,
                    ]);
                    $provisional->save();
                    return $provisional;
                }

                // Crear nuevo contacto identificado
                return CrmContact::query()->create([
                    'name'       => $name,
                    'phone'      => $phone,
                    'cedula'     => $cedula,
                    'resolution' => $patientId !== null
                        ? CrmContact::RESOLUTION_LINKED
                        : CrmContact::RESOLUTION_IDENTIFIED,
                    'source'     => $source,
                    'patient_id' => $patientId,
                ]);
            }

            // 2. Match débil por teléfono (provisional)
            $byPhone = CrmContact::query()->byPhone($phone)->first();
            if ($byPhone instanceof CrmContact) {
                return $byPhone;
            }

            // 3. Crear provisional
            return CrmContact::query()->create([
                'name'       => $name,
                'phone'      => $phone,
                'cedula'     => null,
                'resolution' => CrmContact::RESOLUTION_PROVISIONAL,
                'source'     => $source,
                'patient_id' => $patientId,
            ]);
        });
    }

    /**
     * Vincula un contacto provisional a un patient_id una vez identificado.
     */
    public function linkToPatient(CrmContact $contact, int $patientId): CrmContact
    {
        $contact->patient_id = $patientId;
        $contact->resolution = CrmContact::RESOLUTION_LINKED;
        $contact->save();
        return $contact;
    }
}
```

- [ ] **Step 4: Correr tests y verificar que pasan**

```bash
cd laravel-app && php artisan test tests/Feature/CrmContactResolverServiceTest.php
```

Esperado: 5 tests, 5 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/CRM/Services/CrmContactResolverService.php \
        tests/Feature/CrmContactResolverServiceTest.php
git commit -m "feat(crm): add CrmContactResolverService with deduplication logic"
```

---

## Task 5: CrmOpportunityService y CrmActivityService

**Files:**
- Create: `laravel-app/app/Modules/CRM/Services/CrmOpportunityService.php`
- Create: `laravel-app/app/Modules/CRM/Services/CrmActivityService.php`

- [ ] **Step 1: Crear CrmActivityService**

```php
<?php
// laravel-app/app/Modules/CRM/Services/CrmActivityService.php

namespace App\Modules\CRM\Services;

use App\Models\CrmActivity;

class CrmActivityService
{
    public function log(
        int $opportunityId,
        string $type,
        string $description,
        ?int $userId = null,
    ): CrmActivity {
        return CrmActivity::query()->create([
            'opportunity_id' => $opportunityId,
            'type'           => $type,
            'description'    => $description,
            'user_id'        => $userId,
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
            userId: null,
        );
    }
}
```

- [ ] **Step 2: Crear CrmOpportunityService**

```php
<?php
// laravel-app/app/Modules/CRM/Services/CrmOpportunityService.php

namespace App\Modules\CRM\Services;

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
     * Crea una oportunidad vinculada a un contacto con entrada inteligente por fuente.
     */
    public function createFromEvent(
        CrmContact $contact,
        string $title,
        string $source,
        ?int $sourceId = null,
        ?string $sourceType = null,
        ?int $assignedTo = null,
    ): CrmOpportunity {
        $stage = CrmOpportunity::SOURCE_ENTRY_STAGE[$source] ?? CrmOpportunity::STAGE_NUEVO;

        return DB::transaction(function () use ($contact, $title, $source, $sourceId, $sourceType, $assignedTo, $stage): CrmOpportunity {
            $opportunity = CrmOpportunity::query()->create([
                'contact_id'  => $contact->id,
                'title'       => $title,
                'stage'       => $stage,
                'source'      => $source,
                'source_id'   => $sourceId,
                'source_type' => $sourceType,
                'assigned_to' => $assignedTo,
            ]);

            $this->activityService->logSystemEvent(
                $opportunity->id,
                "Oportunidad creada automáticamente desde {$source}" . ($sourceId ? " #{$sourceId}" : ''),
            );

            return $opportunity;
        });
    }

    /**
     * Avanza la etapa de una oportunidad y registra la actividad.
     */
    public function changeStage(CrmOpportunity $opportunity, string $newStage, ?int $userId = null, ?string $lostReason = null): CrmOpportunity
    {
        if (!in_array($newStage, CrmOpportunity::STAGES, true)) {
            throw new RuntimeException("Etapa inválida: {$newStage}");
        }

        $fromStage = $opportunity->stage;

        DB::transaction(function () use ($opportunity, $newStage, $lostReason, $userId, $fromStage): void {
            $opportunity->stage = $newStage;
            if ($newStage === CrmOpportunity::STAGE_PERDIDO && $lostReason !== null) {
                $opportunity->lost_reason = $lostReason;
            }
            $opportunity->save();

            $this->activityService->logStageChange($opportunity->id, $fromStage, $newStage, $userId);
        });

        return $opportunity->fresh();
    }

    /**
     * Asigna la oportunidad a un agente comercial.
     */
    public function assign(CrmOpportunity $opportunity, int $userId): CrmOpportunity
    {
        $opportunity->assigned_to = $userId;
        $opportunity->save();
        return $opportunity;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/CRM/Services/CrmOpportunityService.php \
        app/Modules/CRM/Services/CrmActivityService.php
git commit -m "feat(crm): add CrmOpportunityService and CrmActivityService"
```

---

## Task 6: CrmStatsService

**Files:**
- Create: `laravel-app/app/Modules/CRM/Services/CrmStatsService.php`

- [ ] **Step 1: Crear CrmStatsService**

```php
<?php
// laravel-app/app/Modules/CRM/Services/CrmStatsService.php

namespace App\Modules\CRM\Services;

use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\DB;

class CrmStatsService
{
    public function panelStats(): array
    {
        $waHours      = (int) config('crm.urgency_threshold_hours.whatsapp', 6);
        $defaultHours = (int) config('crm.urgency_threshold_hours.default', 48);

        $active = CrmOpportunity::query()->active()->count();

        $urgent = CrmOpportunity::query()->urgent($waHours, $defaultHours)->count();

        $wonThisMonth = CrmOpportunity::query()
            ->where('stage', CrmOpportunity::STAGE_GANADO)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $avgResponseHours = DB::table('crm_activities')
            ->join('crm_opportunities', 'crm_activities.opportunity_id', '=', 'crm_opportunities.id')
            ->where('crm_activities.type', 'cambio_etapa')
            ->whereRaw("crm_activities.description LIKE '%nuevo%en_contacto%'")
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
            'urgent'           => $urgent,
            'active'           => $active,
            'won_this_month'   => $wonThisMonth,
            'avg_response_h'   => round((float) ($avgResponseHours ?? 0), 1),
            'conversion_rate'  => $conversionRate,
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
}
```

- [ ] **Step 2: Crear archivo de config CRM**

```php
<?php
// laravel-app/config/crm.php

return [
    'urgency_threshold_hours' => [
        'whatsapp' => env('CRM_URGENCY_WA_HOURS', 6),
        'default'  => env('CRM_URGENCY_DEFAULT_HOURS', 48),
    ],
];
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/CRM/Services/CrmStatsService.php config/crm.php
git commit -m "feat(crm): add CrmStatsService and crm config with urgency thresholds"
```

---

## Task 7: CrmOpportunityListener y EventServiceProvider

**Files:**
- Create: `laravel-app/app/Listeners/CrmOpportunityListener.php`
- Create: `laravel-app/app/Providers/EventServiceProvider.php`
- Modify: `laravel-app/app/Providers/AppServiceProvider.php`
- Create: `laravel-app/tests/Feature/CrmOpportunityListenerTest.php`

- [ ] **Step 1: Escribir tests que fallan**

```php
<?php
// laravel-app/tests/Feature/CrmOpportunityListenerTest.php

namespace Tests\Feature;

use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\WhatsappLead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmOpportunityListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_activities', 'crm_opportunities', 'crm_contacts', 'whatsapp_leads'] as $t) {
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
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('whatsapp_leads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->string('wa_number', 30);
            $table->string('display_name', 255)->nullable();
            $table->string('hc_number', 100)->nullable();
            $table->string('cedula', 30)->nullable();
            $table->string('patient_full_name', 255)->nullable();
            $table->text('motivo_baja');
            $table->string('status', 30)->default('pendiente');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_whatsapp_lead_creates_nuevo_opportunity(): void
    {
        $wlead = WhatsappLead::query()->create([
            'conversation_id' => 1,
            'wa_number'       => '+593991234567',
            'display_name'    => 'María González',
            'cedula'          => null,
            'motivo_baja'     => 'Interesada en cirugía',
        ]);

        event(new WhatsappLeadQualified($wlead));

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('nuevo', CrmOpportunity::query()->first()->stage);
        $this->assertEquals('whatsapp', CrmOpportunity::query()->first()->source);
    }

    public function test_solicitud_creada_creates_interesado_opportunity(): void
    {
        event(new SolicitudCreada(
            solicitudId: 42,
            solicitudData: [
                'paciente_nombre'   => 'Carlos Mendoza',
                'paciente_cedula'   => '0912345678',
                'paciente_telefono' => '+593987654321',
                'servicio'          => 'Consulta cardiología',
            ],
        ));

        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('interesado', CrmOpportunity::query()->first()->stage);
        $this->assertEquals(42, CrmOpportunity::query()->first()->source_id);
    }

    public function test_duplicate_cedula_reuses_contact(): void
    {
        CrmContact::query()->create([
            'name' => 'Carlos', 'phone' => '+593987654321',
            'cedula' => '0912345678', 'resolution' => 'identified', 'source' => 'whatsapp',
        ]);

        event(new SolicitudCreada(
            solicitudId: 42,
            solicitudData: [
                'paciente_nombre'   => 'Carlos Mendoza',
                'paciente_cedula'   => '0912345678',
                'paciente_telefono' => '+593987654321',
                'servicio'          => 'Consulta cardiología',
            ],
        ));

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals(1, CrmOpportunity::query()->count());
    }
}
```

- [ ] **Step 2: Correr tests para confirmar que fallan**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php
```

Esperado: ERROR — listener no existe.

- [ ] **Step 3: Crear CrmOpportunityListener**

```php
<?php
// laravel-app/app/Listeners/CrmOpportunityListener.php

namespace App\Listeners;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CrmOpportunityListener implements ShouldQueue
{
    public string $queue = 'crm';

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

        $this->opportunityService->createFromEvent(
            contact: $contact,
            title: 'Lead WhatsApp: ' . ($lead->motivo_baja),
            source: 'whatsapp',
            sourceId: $lead->id,
            sourceType: 'App\Models\WhatsappLead',
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

        $this->opportunityService->createFromEvent(
            contact: $contact,
            title: 'Solicitud: ' . (string) ($data['servicio'] ?? 'Servicio médico'),
            source: 'solicitud',
            sourceId: $event->solicitudId,
            sourceType: 'solicitud',
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

        $this->opportunityService->createFromEvent(
            contact: $contact,
            title: 'Examen: ' . (string) ($data['descripcion_examen'] ?? 'Examen solicitado'),
            source: 'examen',
            sourceId: $event->examenId,
            sourceType: 'examen',
        );
    }
}
```

- [ ] **Step 4: Crear EventServiceProvider**

```php
<?php
// laravel-app/app/Providers/EventServiceProvider.php

namespace App\Providers;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Listeners\CrmOpportunityListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WhatsappLeadQualified::class => [
            [CrmOpportunityListener::class, 'handleWhatsappLeadQualified'],
        ],
        SolicitudCreada::class => [
            [CrmOpportunityListener::class, 'handleSolicitudCreada'],
        ],
        ExamenSolicitado::class => [
            [CrmOpportunityListener::class, 'handleExamenSolicitado'],
        ],
    ];
}
```

- [ ] **Step 5: Registrar EventServiceProvider en AppServiceProvider**

En `laravel-app/app/Providers/AppServiceProvider.php`, reemplazar el contenido con:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
    }

    public function boot(): void {}
}
```

- [ ] **Step 6: Correr todos los tests y verificar**

```bash
cd laravel-app && php artisan test tests/Feature/CrmOpportunityListenerTest.php tests/Feature/CrmContactResolverServiceTest.php
```

Esperado: 8 tests, 8 passed.

- [ ] **Step 7: Correr suite completa para detectar regresiones**

```bash
cd laravel-app && php artisan test
```

Esperado: todos los tests previos siguen pasando.

- [ ] **Step 8: Commit final**

```bash
git add app/Listeners/CrmOpportunityListener.php \
        app/Providers/EventServiceProvider.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/CrmOpportunityListenerTest.php
git commit -m "feat(crm): add CrmOpportunityListener, EventServiceProvider — event-driven pipeline complete"
```

---

## Verificación final del Plan A

Después de completar todos los tasks, verificar:

```bash
cd laravel-app
php artisan migrate:status | grep crm
# Esperado: crm_contacts, crm_opportunities, crm_activities — Ran

php artisan test tests/Feature/CrmContactResolverServiceTest.php tests/Feature/CrmOpportunityListenerTest.php --verbose
# Esperado: 8 passed

php artisan test
# Esperado: suite completa sin regresiones
```

El Plan A está completo cuando las 3 tablas existen, los 4 servicios están operativos, y los 8 tests pasan. Continuar con `2026-05-28-onda5-crm-b-api.md`.
