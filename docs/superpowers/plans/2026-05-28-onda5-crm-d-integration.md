# CRM Reinvention — Plan D: Integración de Módulos + Migración Legacy + Cleanup

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development**.
>
> **Execution mode:** subagent-driven (~1 día)
>
> **Prerequisito:** Planes A, B y C completos.

**Goal:** Conectar WhatsApp, Solicitudes y Examenes al pipeline CRM disparando los eventos del Plan A. Migrar leads legacy a `crm_contacts + crm_opportunities`. Eliminar `modules/CRM/` y agregar al bridge. Dejar el sistema limpio.

**Architecture:** Tres puntos de disparo de eventos en módulos existentes. Un comando Artisan para migración de datos. El bridge (strangler) redirige las rutas legacy de CRM a Laravel. El directorio `modules/CRM/` se elimina una vez que todas las rutas tienen parity.

**Tech Stack:** Laravel 12, PHP 8.2+, Artisan commands, strangler bridge existente.

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|----------------|
| `app/Modules/Whatsapp/Services/WhatsappLeadService.php` | Modify | Disparar `WhatsappLeadQualified` en `createFromConversation()` |
| `app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php` | Modify | Disparar `SolicitudCreada` al crear nueva solicitud |
| `app/Modules/Examenes/Services/ExamenesParityService.php` | Modify | Disparar `ExamenSolicitado` en el punto correcto |
| `app/Console/Commands/CrmMigrateLegacyLeads.php` | Create | Comando artisan `crm:migrate-legacy-leads` |
| `modules/CRM/` (legacy) | Delete | Eliminado después de confirmar parity |
| `public/router.php` o equivalente bridge | Modify | Agregar rutas `/crm` y `/leads` al bridge |

---

## Task 1: Disparar evento desde WhatsApp

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/WhatsappLeadService.php`

- [ ] **Step 1: Localizar el punto de disparo en WhatsappLeadService**

```bash
grep -n "WhatsappLead::query.*create\|return \[" \
  laravel-app/app/Modules/Whatsapp/Services/WhatsappLeadService.php | head -10
```

Buscar el método `createFromConversation` — el evento debe dispararse después de crear el `WhatsappLead` (dentro de la transacción, antes del return).

- [ ] **Step 2: Agregar import y disparo del evento**

En `WhatsappLeadService.php`, agregar el import al inicio:

```php
use App\Events\Crm\WhatsappLeadQualified;
```

Dentro de la transacción DB en `createFromConversation`, después de crear el `$lead` de WhatsappLead, agregar:

```php
// Disparar evento CRM (queued — no bloquea la transacción)
WhatsappLeadQualified::dispatch($lead, $actorUserId);
```

- [ ] **Step 3: Verificar que los tests de WhatsApp siguen pasando**

```bash
cd laravel-app && php artisan test tests/Feature/WhatsappConversationOpsControllerTest.php
```

Esperado: todos los tests previos pasan (el evento usa queue, no afecta el resultado).

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Whatsapp/Services/WhatsappLeadService.php
git commit -m "feat(crm): dispatch WhatsappLeadQualified event from WhatsappLeadService"
```

---

## Task 2: Disparar evento desde Solicitudes

**Files:**
- Modify: `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php`

- [ ] **Step 1: Localizar dónde se crea una nueva solicitud**

```bash
grep -n "function store\|solicitudes.*insert\|DB::table.*solicitudes" \
  laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php | head -10
```

Identificar el método que crea una solicitud nueva (`store` o equivalente).

- [ ] **Step 2: Agregar import y disparo del evento**

```php
use App\Events\Crm\SolicitudCreada;
```

Después de confirmar que la solicitud fue creada exitosamente, agregar:

```php
// Alimentar pipeline CRM (queued)
SolicitudCreada::dispatch(
    solicitudId: (int) $newId,
    solicitudData: [
        'paciente_nombre'   => (string) ($request->input('paciente_nombre', '') ?: $request->input('nombre', '')),
        'paciente_cedula'   => (string) ($request->input('cedula', '') ?: $request->input('paciente_cedula', '')),
        'paciente_telefono' => (string) ($request->input('telefono', '') ?: $request->input('celular', '')),
        'servicio'          => (string) ($request->input('servicio', '') ?: $request->input('tipo_solicitud', 'Solicitud médica')),
    ],
);
```

> **Nota:** Los nombres exactos de los campos del request deben verificarse leyendo el método real. Los campos arriba son candidatos — usar los que realmente existen en la solicitud.

- [ ] **Step 3: Verificar que no hay regresiones**

```bash
cd laravel-app && php artisan test
```

Esperado: suite completa sin errores.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php
git commit -m "feat(crm): dispatch SolicitudCreada event from SolicitudesWriteController"
```

---

## Task 3: Disparar evento desde Examenes

**Files:**
- Modify: `laravel-app/app/Modules/Examenes/Services/ExamenesParityService.php`

- [ ] **Step 1: Identificar el punto de trigger correcto**

```bash
grep -n "function\|estado\|pendiente\|sin_pago\|confirmacion" \
  laravel-app/app/Modules/Examenes/Services/ExamenesParityService.php | head -30
```

Buscar el método que registra un examen nuevo o cambia su estado a "pendiente de pago/confirmación". El evento `ExamenSolicitado` debe dispararse cuando el examen queda en estado sin confirmación operativa.

- [ ] **Step 2: Agregar import y disparo del evento**

```php
use App\Events\Crm\ExamenSolicitado;
```

En el punto correcto (después de registrar el examen sin confirmación):

```php
// Alimentar pipeline CRM solo si el examen queda pendiente de confirmación (queued)
ExamenSolicitado::dispatch(
    examenId: (int) $examenId,
    examenData: [
        'paciente_nombre'    => (string) ($pacienteNombre ?? ''),
        'paciente_cedula'    => (string) ($cedula ?? ''),
        'paciente_telefono'  => (string) ($telefono ?? ''),
        'descripcion_examen' => (string) ($tipoExamen ?? 'Examen solicitado'),
    ],
);
```

> **Nota:** Las variables exactas dependen del contexto del método. Adaptar a los nombres reales disponibles en ese punto de ejecución.

- [ ] **Step 3: Verificar que no hay regresiones**

```bash
cd laravel-app && php artisan test
```

Esperado: suite completa sin errores.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Examenes/Services/ExamenesParityService.php
git commit -m "feat(crm): dispatch ExamenSolicitado event from ExamenesParityService"
```

---

## Task 4: Comando de migración de leads legacy

**Files:**
- Create: `laravel-app/app/Console/Commands/CrmMigrateLegacyLeads.php`

- [ ] **Step 1: Crear el comando**

```php
<?php
// laravel-app/app/Console/Commands/CrmMigrateLegacyLeads.php

namespace App\Console\Commands;

use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmMigrateLegacyLeads extends Command
{
    protected $signature   = 'crm:migrate-legacy-leads {--dry-run : Solo reporta, no escribe}';
    protected $description = 'Migra leads del CRM legacy (crm_leads) a crm_contacts + crm_opportunities';

    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmActivityService $activityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!DB::getSchemaBuilder()->hasTable('crm_leads')) {
            $this->error('Tabla crm_leads no existe — nada que migrar.');
            return 1;
        }

        $leads = DB::table('crm_leads')->orderBy('id')->get();
        $this->info("Leads a migrar: {$leads->count()}");

        $migrated = 0;
        $skipped  = 0;

        foreach ($leads as $lead) {
            $phone  = (string) ($lead->phone ?? '');
            $cedula = (string) ($lead->hc_number ?? '');
            $name   = (string) ($lead->name ?? 'Paciente');
            $status = (string) ($lead->status ?? 'nuevo');

            if ($phone === '' && $cedula === '') {
                $this->warn("  Skip lead #{$lead->id} — sin teléfono ni cédula");
                $skipped++;
                continue;
            }

            $stage = match ($status) {
                'contactado'    => CrmOpportunity::STAGE_EN_CONTACTO,
                'propuesta'     => CrmOpportunity::STAGE_PROPUESTA,
                'ganado', 'won' => CrmOpportunity::STAGE_GANADO,
                'perdido'       => CrmOpportunity::STAGE_PERDIDO,
                default         => CrmOpportunity::STAGE_NUEVO,
            };

            if ($dryRun) {
                $this->line("  [dry] Lead #{$lead->id} → contacto ({$name}) + oportunidad ({$stage})");
                $migrated++;
                continue;
            }

            $contact = $this->contactResolver->resolve(
                phone: $phone ?: '+000',
                name: $name,
                cedula: $cedula ?: null,
                source: (string) ($lead->source ?? 'manual'),
            );

            $opp = CrmOpportunity::query()->create([
                'contact_id'  => $contact->id,
                'title'       => 'Lead migrado: ' . $name,
                'stage'       => $stage,
                'source'      => (string) ($lead->source ?? 'manual'),
                'source_id'   => $lead->id,
                'source_type' => 'legacy_crm_lead',
                'assigned_to' => $lead->assigned_to ?? null,
            ]);

            $this->activityService->logSystemEvent(
                $opp->id,
                "Migrado desde crm_leads legacy (ID: {$lead->id}, status original: {$status})",
            );

            $migrated++;
        }

        $this->info("Migrados: {$migrated} | Saltados: {$skipped}");
        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribió nada. Correr sin --dry-run para ejecutar.');
        }

        return 0;
    }
}
```

- [ ] **Step 2: Registrar el comando en Kernel (si existe) o verificar autodiscovery**

```bash
grep -r "CrmMigrateLegacyLeads\|Commands\\\\" laravel-app/app/Console/ 2>/dev/null | head -5
```

Si existe un `Kernel.php` con array `$commands`, agregar:
```php
\App\Console\Commands\CrmMigrateLegacyLeads::class,
```

Si no existe Kernel (Laravel 12 usa autodiscovery), no es necesario.

- [ ] **Step 3: Probar dry-run**

```bash
cd laravel-app && php artisan crm:migrate-legacy-leads --dry-run
```

Esperado: lista de leads que se migrarían, sin errores.

- [ ] **Step 4: Ejecutar migración real**

```bash
cd laravel-app && php artisan crm:migrate-legacy-leads
```

Esperado: "Migrados: N | Saltados: M" sin errores.

- [ ] **Step 5: Verificar datos migrados**

```bash
php artisan tinker --execute="echo App\Models\CrmContact::count() . ' contactos, ' . App\Models\CrmOpportunity::count() . ' oportunidades';"
```

Esperado: número mayor que 0 en ambos.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/CrmMigrateLegacyLeads.php
git commit -m "feat(crm): add crm:migrate-legacy-leads artisan command"
```

---

## Task 5: Agregar CRM al bridge (strangler) y eliminar legacy

**Files:**
- Modify: bridge de rutas (verificar archivo exacto con `grep -r "crm\|CRM" public/router.php` o equivalente)
- Delete: `modules/CRM/` (directorio completo)

- [ ] **Step 1: Verificar que todas las rutas CRM tienen parity en Laravel**

```bash
grep -r "Route::\|->get\|->post\|->patch\|->put" laravel-app/routes/v2/crm.php | wc -l
```

Deben estar cubiertas:
- `GET /crm` (UI panel)
- `GET /api/v2/crm/opportunities` y variantes
- `GET /api/v2/crm/contacts/{id}` y variantes
- `GET /api/v2/crm/stats`

- [ ] **Step 2: Encontrar el archivo del bridge**

```bash
grep -rn "crm\|CRM" public/router.php 2>/dev/null | head -10
# Si no está en router.php:
grep -rn "crm\|CRM" modules/ --include="*.php" -l 2>/dev/null | grep -i bridge | head -5
```

- [ ] **Step 3: Agregar rutas CRM al bridge**

En el archivo del bridge, en la sección de rutas que pasan a Laravel, agregar:

```php
// CRM — redirigir a Laravel
'/crm'          => true,
'/api/v2/crm'   => true,
```

El patrón exacto depende de cómo esté escrito el bridge. Seguir el mismo patrón que las demás rutas ya migradas (por ejemplo Billing o WhatsApp).

- [ ] **Step 4: Verificar que el bridge funciona**

```bash
php -S localhost:8080 -t public public/router.php &
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/crm
# Esperado: 302 (redirect a login) o 200 — no 404
```

- [ ] **Step 5: Eliminar módulo CRM legacy**

Solo ejecutar si el bridge está funcionando y todas las rutas tienen parity:

```bash
rm -rf modules/CRM/
```

- [ ] **Step 6: Verificar que el sistema sigue funcionando**

```bash
cd laravel-app && php artisan test
# Esperado: suite completa sin errores
```

- [ ] **Step 7: Commit final**

```bash
git add -A
git commit -m "feat(crm): add CRM routes to bridge, delete legacy modules/CRM/ — reinvention complete"
```

---

## Verificación final del Plan D y de toda la reinvención

```bash
# 1. Tests completos
cd laravel-app && php artisan test
# Esperado: toda la suite pasa

# 2. Verificar que módulo legacy fue eliminado
ls modules/CRM/ 2>/dev/null && echo "AÚN EXISTE" || echo "Eliminado correctamente"

# 3. Verificar que las migraciones están limpias
php artisan migrate:status | grep crm

# 4. Verificar eventos registrados
php artisan event:list | grep -i crm

# 5. Smoke test del panel
php artisan serve &
curl -s http://localhost:8000/crm | grep -c "crm-root"
# Esperado: 1
```

La reinvención CRM está completa cuando:
- ✅ 3 tablas CRM operativas
- ✅ 3 eventos disparándose desde WhatsApp, Solicitudes y Examenes
- ✅ Panel React cargando en `/crm`
- ✅ Leads legacy migrados
- ✅ `modules/CRM/` eliminado
- ✅ Suite de tests sin regresiones
