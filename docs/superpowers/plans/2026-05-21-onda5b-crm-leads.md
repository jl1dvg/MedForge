# Onda 5-B — CRM Leads: Portar Detail, Profile, Mail, Convert

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development**. Dispatch a fresh subagent per task con spec + quality review.
>
> **Execution mode:** subagent-driven (~1.5 días)
>
> **Parte de:** Onda 5 fragmentada — continúa en `2026-05-21-onda5c-crm-entities.md`
>
> **Prerequisito:** Onda 5-A (Billing) completa.

**Goal:** Portar las 8 rutas de Leads que faltan en v2: detail, profile, mail compose/send, lead update completo (PUT), convert, y las 2 páginas UI (`/crm`, `/leads`).

**Architecture:** CRM en Laravel ya tiene `CrmReadController` (leads list, meta, metrics), `CrmWriteController` (create, update básico, status), `CrmProposalController` (pdf, email, whatsapp). El gap es: el detalle individual del lead, su perfil, el flujo de mail por lead, y el convert-to-patient. Todo va a `CrmReadController` (reads) y `CrmWriteController` (writes). La UI (`/crm`, `/leads`) va a un nuevo `CrmUiController`.

**Tech Stack:** Laravel 12, `laravel-app/app/Modules/CRM/`, `laravel-app/routes/v2/crm.php`.

**Dependencia crítica:** `modules/CRM/Models/LeadModel.php` usa `Modules\WhatsApp\Services\Messenger`. En el controller Laravel nuevo, usar `App\Modules\Whatsapp\Services\...` equivalente. WhatsApp legacy aún existe (se elimina en Onda 5-D).

**Gap de esta sesión:**

| Ruta legacy | Método controller | Acción |
|------------|------------------|--------|
| `GET /crm` | CRMController::index() | Crear CrmUiController::index() |
| `GET /leads` | CRMController::index() (alias) | Alias en v2 |
| `PUT /crm/leads/{id}` | CRMController::updateLead() | Agregar a CrmWriteController |
| `GET /crm/leads/{id}` | CRMController::getLead() | Agregar a CrmReadController |
| `GET /crm/leads/{id}/profile` | LeadController::profile() | Agregar a CrmReadController |
| `GET /crm/leads/{id}/mail/compose` | LeadController::mailCompose() | Agregar a CrmReadController |
| `POST /crm/leads/{id}/mail/send-template` | LeadController::sendTemplate() | Agregar a CrmWriteController |
| `POST /crm/leads/convert` | CRMController::convertLead() | Agregar a CrmWriteController |

---

## Task 1: Leer código legacy de CRM Leads

**Files:**
- Read: `modules/CRM/Controllers/CRMController.php`
- Read: `modules/CRM/Controllers/LeadController.php`
- Read: `modules/CRM/Models/LeadModel.php`
- Read: `modules/CRM/Services/LeadCrmCoreService.php`
- Read: `modules/CRM/Services/LeadResolverService.php`
- Read: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Read: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`

- [ ] **Step 1: Leer controllers legacy**

```bash
cat modules/CRM/Controllers/CRMController.php
cat modules/CRM/Controllers/LeadController.php
```

Mapear cada método al gap de rutas arriba.

- [ ] **Step 2: Leer LeadModel para entender la dep de WhatsApp**

```bash
grep -n "WhatsApp\|Messenger\|WhatsAppModule" modules/CRM/Models/LeadModel.php | head -10
grep -n "public function" modules/CRM/Models/LeadModel.php
```

Identificar qué métodos de LeadModel usan WhatsApp. En el controller Laravel, usar `App\Modules\Whatsapp\Services\CloudApiTransportService` o el equivalente ya portado.

- [ ] **Step 3: Leer controllers Laravel existentes**

```bash
cat laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php
cat laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php
```

Entender el patrón de respuesta (JsonResponse, uso de Request, autenticación).

- [ ] **Step 4: Verificar servicios disponibles en Laravel CRM**

```bash
find laravel-app/app/Modules/CRM/Services -name "*.php" | sort
```

Identificar si ya hay servicios para lead detail/profile/mail. Si existen, reutilizarlos.

---

## Task 2: Crear CrmUiController — GET /crm y GET /leads

**Files:**
- Create: `laravel-app/app/Modules/CRM/Http/Controllers/CrmUiController.php`
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Ver qué renderiza CRMController::index() en legacy**

```bash
grep -A 30 "function index" modules/CRM/Controllers/CRMController.php | head -35
```

Determinar si es una SPA (Vite/React) o una vista PHP clásica.

- [ ] **Step 2: Crear CrmUiController**

```php
<?php
// laravel-app/app/Modules/CRM/Http/Controllers/CrmUiController.php
namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CrmUiController extends Controller
{
    public function index(Request $request): View
    {
        // Si es SPA: return view('crm.index') con los assets Vite
        // Si es server-side: portar la lógica de CRMController::index()
        return view('crm.index');
    }
}
```

Adaptar según lo que devuelve el legacy (SPA embed vs. PHP render).

- [ ] **Step 3: Agregar rutas UI a v2/crm.php**

```php
use App\Modules\CRM\Http\Controllers\CrmUiController;

// En el grupo de middleware existente:
Route::get('/crm', [CrmUiController::class, 'index']);
Route::get('/leads', [CrmUiController::class, 'index']); // alias
```

- [ ] **Step 4: Verificar**

```bash
cd laravel-app && php artisan route:list | grep -E "^GET.*/(crm|leads)$"
```

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmUiController.php
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5b): add CrmUiController for GET /crm and GET /leads UI routes

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Portar GET /crm/leads/{id} y GET /crm/leads/{id}/profile

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Leer la lógica de getLead() y profile() en legacy**

```bash
grep -A 40 "function getLead\|function lead\b" modules/CRM/Controllers/CRMController.php
grep -A 40 "function profile" modules/CRM/Controllers/LeadController.php
```

- [ ] **Step 2: Agregar métodos a CrmReadController**

```php
public function lead(Request $request, int $id): JsonResponse
{
    // Adaptar lógica de CRMController::getLead() o equivalente
    // Usar DB::table('leads') o el modelo Eloquent/QueryBuilder existente
    // Retornar: response()->json($lead)
}

public function leadProfile(Request $request, int $id): JsonResponse
{
    // Adaptar lógica de LeadController::profile()
    // Incluye datos del lead + historial + actividad
    // Retornar: response()->json($profile)
}
```

- [ ] **Step 3: Registrar rutas**

```php
// En el grupo app.permission:administrativo,crm.view,crm.manage:
Route::get('/crm/leads/{id}', [CrmReadController::class, 'lead'])->whereNumber('id');
Route::get('/crm/leads/{id}/profile', [CrmReadController::class, 'leadProfile'])->whereNumber('id');

Route::get('/api/crm/leads/{id}', [CrmReadController::class, 'lead'])->whereNumber('id');
Route::get('/api/crm/leads/{id}/profile', [CrmReadController::class, 'leadProfile'])->whereNumber('id');
```

- [ ] **Step 4: Verificar**

```bash
cd laravel-app && php artisan route:list | grep "leads/{id}"
```

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5b): port GET /crm/leads/{id} and /profile to CrmReadController

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Portar GET /crm/leads/{id}/mail/compose y POST /mail/send-template

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`
- Modify: `laravel-app/routes/v2/crm.php`

**Nota:** CRM legacy usa `Modules\Mail\Services\NotificationMailer` y `Modules\Mail\Services\MailProfileService`. Esas ya existen en `App\Modules\Mail\Services\...` (portadas en Onda 2). Usar la versión Laravel.

- [ ] **Step 1: Leer lógica de compose y sendTemplate en legacy**

```bash
grep -A 30 "function mailCompose\|function compose" modules/CRM/Controllers/LeadController.php
grep -A 40 "function sendTemplate\|function send_template" modules/CRM/Controllers/LeadController.php
```

- [ ] **Step 2: Agregar mailCompose() a CrmReadController**

```php
public function mailCompose(Request $request, int $id): JsonResponse|View
{
    // Carga datos del lead + templates disponibles
    // Retorna el compose form (JSON para SPA o View para server-side)
}
```

- [ ] **Step 3: Agregar sendTemplate() a CrmWriteController**

```php
public function sendLeadMailTemplate(Request $request, int $id): JsonResponse
{
    // Usa App\Modules\Mail\Services\NotificationMailer (ya en Laravel)
    // Envía el template seleccionado al email del lead
    // Retorna: response()->json(['ok' => true])
}
```

- [ ] **Step 4: Registrar rutas**

```php
Route::get('/crm/leads/{id}/mail/compose', [CrmReadController::class, 'mailCompose'])->whereNumber('id');
Route::post('/crm/leads/{id}/mail/send-template', [CrmWriteController::class, 'sendLeadMailTemplate'])->whereNumber('id');

Route::get('/api/crm/leads/{id}/mail/compose', [CrmReadController::class, 'mailCompose'])->whereNumber('id');
Route::post('/api/crm/leads/{id}/mail/send-template', [CrmWriteController::class, 'sendLeadMailTemplate'])->whereNumber('id');
```

- [ ] **Step 5: Verificar**

```bash
cd laravel-app && php artisan route:list | grep "mail"
```

- [ ] **Step 6: Commit**

```bash
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5b): port lead mail compose and send-template routes to Laravel

Uses App\Modules\Mail\Services\NotificationMailer (ported in Onda 2).

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Portar PUT /crm/leads/{id} y POST /crm/leads/convert

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Leer lógica de updateLead (PUT) y convertLead**

```bash
grep -A 40 "function updateLead\b" modules/CRM/Controllers/CRMController.php
grep -A 50 "function convertLead\|function convert\b" modules/CRM/Controllers/CRMController.php
```

El `updateLead` legacy es un PUT con payload completo (a diferencia del `updateLead` que ya existe en v2 que es PATCH parcial). El `convertLead` crea un paciente desde un lead — verificar que usa Pacientes de alguna forma.

- [ ] **Step 2: Agregar fullUpdateLead() a CrmWriteController**

```php
public function fullUpdateLead(Request $request, int $id): JsonResponse
{
    // Adaptar lógica del PUT legacy
    // Actualiza todos los campos del lead en una sola operación
    // El POST /crm/leads/update existente hace update parcial
    // Este PUT hace update completo
}
```

- [ ] **Step 3: Agregar convertLead() a CrmWriteController**

```php
public function convertLead(Request $request): JsonResponse
{
    // Convierte un lead en paciente
    // Verificar si usa Modules\Pacientes — ahora en App\Modules\Pacientes
    // Crear el registro en patient_data o la tabla correspondiente
    // Actualizar el lead con patient_id
}
```

- [ ] **Step 4: Registrar rutas**

```php
Route::put('/crm/leads/{id}', [CrmWriteController::class, 'fullUpdateLead'])->whereNumber('id');
Route::post('/crm/leads/convert', [CrmWriteController::class, 'convertLead']);

Route::put('/api/crm/leads/{id}', [CrmWriteController::class, 'fullUpdateLead'])->whereNumber('id');
Route::post('/api/crm/leads/convert', [CrmWriteController::class, 'convertLead']);
```

- [ ] **Step 5: Verificar**

```bash
cd laravel-app && php artisan route:list | grep -E "leads/{id}|leads/convert"
```

- [ ] **Step 6: Tests**

```bash
cd laravel-app && php artisan test --filter=CrmLead 2>/dev/null || echo "No CrmLead tests — crear mínimo"
```

Si no hay tests, crear un Feature test básico:
```bash
# laravel-app/tests/Feature/Modules/CRM/CrmLeadTest.php
# Test: GET /crm/leads/1 retorna 200 con estructura {id, nombre, ...}
# Test: POST /crm/leads/convert retorna 200 o 422
```

- [ ] **Step 7: Commit**

```bash
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php
git add laravel-app/routes/v2/crm.php
git add laravel-app/tests/Feature/Modules/CRM/ 2>/dev/null || true
git commit -m "$(cat <<'EOF'
feat(onda5b): port PUT /crm/leads/{id} and POST /crm/leads/convert to Laravel

fullUpdateLead handles complete lead update. convertLead creates patient
from lead using App\Modules\Pacientes services.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado de 5-B

| Ruta | Estado |
|------|--------|
| `GET /crm` | ✅ CrmUiController::index() |
| `GET /leads` | ✅ alias |
| `GET /crm/leads/{id}` | ✅ CrmReadController::lead() |
| `GET /crm/leads/{id}/profile` | ✅ CrmReadController::leadProfile() |
| `GET /crm/leads/{id}/mail/compose` | ✅ CrmReadController::mailCompose() |
| `POST /crm/leads/{id}/mail/send-template` | ✅ CrmWriteController::sendLeadMailTemplate() |
| `PUT /crm/leads/{id}` | ✅ CrmWriteController::fullUpdateLead() |
| `POST /crm/leads/convert` | ✅ CrmWriteController::convertLead() |

CRM legacy **no se elimina aún** — continúa en `2026-05-21-onda5c-crm-entities.md` donde se portan Projects, Tasks, Tickets, Proposals y se hace el delete final.

**Siguiente:** `docs/superpowers/plans/2026-05-21-onda5c-crm-entities.md`
