# Onda 2 — Casi-Listos: Agenda, Derivaciones, Reporting (rutas), Mail

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development** to execute this plan. Dispatch a fresh subagent per task with spec + quality review after each.
>
> **Execution mode:** subagent-driven (4 modules, rutas activas pendientes de portar)
>
> **Master roadmap:** `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`

**Goal:** Completar la paridad de 4 módulos que ya tienen v2 routes parciales en Laravel, agregar sus prefijos al bridge, y eliminar cada `modules/<Modulo>/` del legacy.

**Architecture:**
- Agenda y Derivaciones: casi completos — solo falta una ruta de UI en cada uno y luego agregar el prefijo al bridge.
- Reporting (rutas solamente): el listing `/reports` y `/reports/{slug}` son JSON APIs simples que van al `ReportService` — el motor (49 archivos) NO se toca, vive hasta Onda 6.
- Mail: cero v2 routes. Crear parity completa en Laravel (3 endpoints + 1 alias) y agregar `/mailbox` al bridge.

**Prerequisitos:** Onda 1 completada. Laravel app en `laravel-app/`. Tests corren con `cd laravel-app && php artisan test`.

**Orden de ejecución:** Agenda → Derivaciones → Reporting (rutas) → Mail (en ese orden, cada uno en su propio subagent)

---

## Task 1: Agenda — agregar bridge y eliminar módulo

**Contexto:** Laravel ya tiene `GET /agenda`, `GET /agenda/visitas/{id}`, `POST /agenda/estado` en `laravel-app/routes/v2/agenda.php`. El bridge NO tiene `/agenda` aún. Las 2 rutas legacy (`/agenda` y `/agenda/visitas/{visitaId}`) son código muerto una vez que agregamos el prefijo.

**Files:**
- Modify: `public/index.php` — agregar `/agenda` a `$laravelBridgePrefixes`
- Delete: `modules/Agenda/` (6 archivos, ~1k líneas)

- [ ] **Step 1: Verificar cobertura completa de rutas**

```bash
# Legacy routes:
cat modules/Agenda/routes.php

# v2 routes:
cat laravel-app/routes/v2/agenda.php
```
Confirmar que cada ruta legacy tiene su equivalente en v2. Expected: `GET /agenda` → `AgendaReadController::index`, `GET /agenda/visitas/{id}` → `AgendaReadController::visita`.

- [ ] **Step 2: Verificar cero referencias cross-module a Agenda**

```bash
grep -r "Modules\\\\Agenda" modules/ --include="*.php" -l
grep -r "Modules\\\\Agenda" laravel-app/ --include="*.php" -l
```
Expected: solo archivos de `modules/Agenda/` mismo.

- [ ] **Step 3: Agregar /agenda al bridge**

En `public/index.php`, línea con `$laravelBridgePrefixes`, agregar `'/agenda'`:

```php
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos', '/examenes', '/imagenes', '/agenda'];
```

- [ ] **Step 4: Eliminar módulo Agenda**

```bash
rm -rf modules/Agenda
```

- [ ] **Step 5: Smoke test del router**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader;
use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 6: Commit**

```bash
git add public/index.php
git add -A modules/Agenda
git commit -m "$(cat <<'EOF'
feat(onda2): delete Agenda — add /agenda bridge prefix, Laravel already covers all routes

GET /agenda and GET /agenda/visitas/{id} are fully implemented in
laravel-app/app/Modules/Agenda. Bridge now intercepts /agenda prefix.
Zero cross-module dependencies.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Derivaciones — portar UI index, agregar bridge, eliminar módulo

**Contexto:** Laravel tiene en `laravel-app/routes/v2/derivaciones.php`: `POST /derivaciones/datatable`, `GET /derivaciones/archivo-form`, `GET /derivaciones/archivo/{id}`, `POST /derivaciones/scrap`. Falta `GET /derivaciones` (la vista principal de la SPA). El `DerivacionesUiController` ya existe en Laravel (`laravel-app/routes/web.php` registra `GET /v2/derivaciones`). Necesitamos que `GET /derivaciones` (sin `/v2`) también funcione.

**Files:**
- Read: `laravel-app/routes/web.php` — confirmar `GET /v2/derivaciones`
- Read: `laravel-app/routes/v2/derivaciones.php` — rutas API actuales
- Modify: `laravel-app/routes/v2/derivaciones.php` — agregar `GET /derivaciones`
- Modify: `public/index.php` — agregar `/derivaciones` al bridge
- Delete: `modules/Derivaciones/` (5 archivos, ~1.7k líneas)

- [ ] **Step 1: Leer el controlador UI de derivaciones en Laravel**

```bash
find laravel-app/app/Modules/Derivaciones -name "*.php" | sort
cat laravel-app/routes/web.php | grep -A3 "derivaciones"
```

- [ ] **Step 2: Agregar GET /derivaciones a v2 routes**

En `laravel-app/routes/v2/derivaciones.php`, dentro del grupo de middleware existente, agregar:

```php
Route::get('/derivaciones', [DerivacionesReadController::class, 'index']);
```

(verificar primero que `DerivacionesReadController` o `DerivacionesUiController` tiene un método `index` — si no, agregar el método que renderiza la vista Vite.)

- [ ] **Step 3: Verificar que GET /derivaciones responde en Laravel**

```bash
cd laravel-app && php artisan route:list | grep "derivaciones"
```
Expected: `GET /derivaciones` aparece en la lista.

- [ ] **Step 4: Verificar cero referencias cross-module**

```bash
grep -r "Modules\\\\Derivaciones" modules/ --include="*.php" -l | grep -v "modules/Derivaciones/"
grep -r "DerivacionesSyncService\|DerivacionesService" modules/ --include="*.php" -l | grep -v "modules/Derivaciones/"
```
Expected: cero resultados externos.

Nota: `laravel-app/routes/console.php` hace referencia a `DerivacionesSyncService` via path string hardcodeado para el Artisan command `derivaciones:scrape-missing`. Leer ese comando y verificar si depende del archivo legacy o si tiene su propia implementación.

- [ ] **Step 5: Agregar /derivaciones al bridge**

```php
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos', '/examenes', '/imagenes', '/agenda', '/derivaciones'];
```

- [ ] **Step 6: Eliminar módulo Derivaciones**

```bash
rm -rf modules/Derivaciones
```

- [ ] **Step 7: Tests**

```bash
cd laravel-app && php artisan test --filter=Derivaciones 2>/dev/null || echo "No Derivaciones tests"
```

- [ ] **Step 8: Commit**

```bash
git add public/index.php laravel-app/routes/v2/derivaciones.php
git add -A modules/Derivaciones
git commit -m "$(cat <<'EOF'
feat(onda2): delete Derivaciones — port GET /derivaciones UI route to Laravel, add bridge

Added GET /derivaciones to laravel-app/routes/v2/derivaciones.php.
Bridge now intercepts /derivaciones prefix. All 4 API routes were
already in Laravel. Zero remaining cross-module dependencies.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Reporting (rutas) — portar /reports listing, agregar bridge, matar routes.php

**Contexto:** El módulo Reporting tiene DOS partes:
1. `routes.php` + redirectores PDF (Onda 2) — queremos matar esto
2. El motor: 49 archivos, `ReportService`, `LegacyLoader`, etc. (Onda 6) — NO tocar aún

Las rutas PDF (`/reports/protocolo/pdf`, `/reports/cobertura/pdf`, etc.) ya son redirectores a v2 en el propio `routes.php` y también duplicados en `laravel-app/routes/api.php`. Las rutas de listing (`GET /reports`, `GET /reports/{slug}`) devuelven JSON. El `ReportController::index()` devuelve `{ reports: [...] }` y `::show()` devuelve metadata del template.

**Files:**
- Read: `modules/Reporting/routes.php` — lista completa de rutas legacy
- Read: `modules/Reporting/Controllers/ReportController.php`
- Read: `laravel-app/routes/v2/reporting.php`
- Modify: `laravel-app/routes/v2/reporting.php` — agregar GET /reports y GET /reports/{slug}
- Read: `laravel-app/app/Modules/Reporting/Http/Controllers/ReportingReadController.php` — verificar si tiene index/show
- Modify: `public/index.php` — agregar `/reports` al bridge
- Delete: `modules/Reporting/routes.php` SOLAMENTE (el resto del motor NO se elimina)

- [ ] **Step 1: Auditar rutas legacy vs. v2**

```bash
# Rutas legacy:
grep "router->" modules/Reporting/routes.php

# Rutas v2 actuales:
cat laravel-app/routes/v2/reporting.php
```

Mapear cada ruta legacy a su equivalente. Las PDF routes ya redirigen a v2. Identificar cuáles faltan.

- [ ] **Step 2: Leer ReportingReadController para ver métodos disponibles**

```bash
grep "public function" laravel-app/app/Modules/Reporting/Http/Controllers/ReportingReadController.php
```

- [ ] **Step 3: Agregar GET /reports y GET /reports/{slug} a v2/reporting.php**

Dentro del grupo de middleware en `laravel-app/routes/v2/reporting.php`:

```php
// Listing de reportes disponibles
Route::get('/reports', [ReportingReadController::class, 'index']);
// Metadata de un reporte específico
Route::get('/reports/{slug}', [ReportingReadController::class, 'show']);
```

Si `index()` y `show()` no existen en `ReportingReadController`, agregarlos. El `index()` devuelve los reportes disponibles (puede llamar a `ReportService::getAvailableReports()` si ya está portado, o devolver un array hardcoded con los slugs conocidos). El `show()` recibe `$slug` y devuelve metadata.

Nota: El `ReportService` legacy usa el motor de 49 archivos. Para los endpoints de listing NO hace falta replicar el motor — puede devolver una lista estática de slugs o consultar la DB directamente. Evaluar la opción más simple que no introduzca dependencia en el motor legacy.

- [ ] **Step 4: Verificar rutas registradas**

```bash
cd laravel-app && php artisan route:list | grep "/reports"
```

- [ ] **Step 5: Agregar /reports al bridge**

```php
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos', '/examenes', '/imagenes', '/agenda', '/derivaciones', '/reports'];
```

- [ ] **Step 6: Verificar que el motor de Reporting NO se elimina**

```bash
ls modules/Reporting/
```
Expected: archivos del motor siguen ahí (`Services/`, `Support/`, `Controllers/`, `Views/`, etc.).

- [ ] **Step 7: Eliminar SOLO modules/Reporting/routes.php**

```bash
rm modules/Reporting/routes.php
```

- [ ] **Step 8: Smoke test del router (el motor ya no está registrado como módulo)**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader;
use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 9: Commit**

```bash
git add public/index.php laravel-app/routes/v2/reporting.php
git add laravel-app/app/Modules/Reporting/Http/Controllers/ReportingReadController.php 2>/dev/null || true
git add modules/Reporting/routes.php
git commit -m "$(cat <<'EOF'
feat(onda2): kill Reporting legacy routes — port /reports listing to Laravel, add bridge

Added GET /reports and GET /reports/{slug} to v2/reporting.php.
Bridge now intercepts /reports prefix. Deleted only modules/Reporting/routes.php —
the engine (49 files) stays until Onda 6 when Billing+Cirugias are migrated.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Mail — parity completa en Laravel, agregar bridge, eliminar módulo

**Contexto:** El módulo Mail tiene 4 rutas activas con cero equivalentes en Laravel:
- `GET /mailbox` → `MailboxController::index()` (renderiza la SPA de mailbox)
- `GET /mail` → alias de `/mailbox`
- `GET /mailbox/feed` → JSON: lista de mensajes (multi-source: solicitudes, examenes, cobertura, whatsapp, tickets)
- `POST /mailbox/compose` → envía email via SMTP

El `MailboxController` ya fue parcialmente limpiado en sesiones anteriores (se removieron las dependencias de `ExamenCrmService` y `ExamenMailLogService`). Los servicios clave son `MailboxService` (feed/query) y `NotificationMailer` (compose/send).

**Files a leer antes de implementar:**
- `modules/Mail/Controllers/MailboxController.php` — lógica completa de index/feed/compose
- `modules/Mail/Services/MailboxService.php` — servicio de feed
- `modules/Mail/Services/NotificationMailer.php` — servicio de envío
- `modules/Mail/Services/MailProfileService.php` — perfil de mail (SMTP config)
- `modules/Mail/views/index.php` — vista del mailbox

**Files a crear/modificar:**
- Create: `laravel-app/app/Modules/Mail/Http/Controllers/MailboxController.php`
- Create: `laravel-app/app/Modules/Mail/Services/MailboxService.php`
- Create: `laravel-app/app/Modules/Mail/Services/NotificationMailer.php`
- Create: `laravel-app/app/Modules/Mail/Services/MailProfileService.php`
- Create: `laravel-app/routes/v2/mail.php`
- Modify: `laravel-app/routes/api.php` — agregar `require` para mail.php
- Modify: `public/index.php` — agregar `/mailbox` y `/mail` al bridge
- Delete: `modules/Mail/`

- [ ] **Step 1: Leer código legacy completo**

```bash
cat modules/Mail/Controllers/MailboxController.php
cat modules/Mail/Services/MailboxService.php
cat modules/Mail/Services/NotificationMailer.php
cat modules/Mail/Services/MailProfileService.php
```

Comprender qué hace cada servicio, qué tablas usa, qué responde.

- [ ] **Step 2: Verificar si ya existe algún módulo Mail en Laravel**

```bash
ls laravel-app/app/Modules/ | grep -i mail
find laravel-app/app/Modules -name "*Mail*" -o -name "*Mailbox*" | head -20
```

Si existe, leer lo que ya hay para no duplicar trabajo.

- [ ] **Step 3: Crear estructura de directorios Laravel**

```bash
mkdir -p laravel-app/app/Modules/Mail/Http/Controllers
mkdir -p laravel-app/app/Modules/Mail/Services
```

- [ ] **Step 4: Portar MailProfileService**

Crear `laravel-app/app/Modules/Mail/Services/MailProfileService.php` con namespace `App\Modules\Mail\Services`. Copiar lógica de `modules/Mail/Services/MailProfileService.php`, reemplazando el acceso a `SettingsModel` legacy por `DB::table('settings')` de Laravel.

- [ ] **Step 5: Portar NotificationMailer**

Crear `laravel-app/app/Modules/Mail/Services/NotificationMailer.php`. Usar `Illuminate\Mail\Mailer` o Swift/PHPMailer según el legacy. Si el legacy usa PHPMailer directamente, mantenerlo (está disponible via composer).

- [ ] **Step 6: Portar MailboxService**

Crear `laravel-app/app/Modules/Mail/Services/MailboxService.php`. El servicio consulta múltiples tablas (solicitudes, examenes, whatsapp, etc.) para el feed unificado. Usar `DB::select()` o Query Builder. Conservar la misma lógica de filtrado y paginación.

- [ ] **Step 7: Crear MailboxController en Laravel**

```php
// laravel-app/app/Modules/Mail/Http/Controllers/MailboxController.php
namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mail\Services\MailboxService;
use App\Modules\Mail\Services\NotificationMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MailboxController extends Controller
{
    public function __construct(
        private readonly MailboxService $mailboxService,
        private readonly NotificationMailer $mailer,
    ) {}

    public function index(): Response
    {
        // Renderizar vista Blade/Vite o retornar HTML
        return response()->view('mail.index');
    }

    public function feed(Request $request): JsonResponse
    {
        $filters = $request->only(['sources', 'limit', 'query', 'contact']);
        $messages = $this->mailboxService->getMessages($filters);
        return response()->json(['messages' => $messages]);
    }

    public function compose(Request $request): JsonResponse
    {
        // Delegar a NotificationMailer
        $result = $this->mailer->send($request->all());
        return response()->json($result);
    }
}
```

- [ ] **Step 8: Crear routes/v2/mail.php**

```php
<?php

use App\Modules\Mail\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/mailbox', [MailboxController::class, 'index']);
    Route::get('/mail', [MailboxController::class, 'index']);
    Route::get('/mailbox/feed', [MailboxController::class, 'feed']);
    Route::post('/mailbox/compose', [MailboxController::class, 'compose']);
});
```

- [ ] **Step 9: Registrar en laravel-app/routes/api.php**

Buscar la sección de `require` statements y agregar:
```php
require __DIR__ . '/v2/mail.php';
```

- [ ] **Step 10: Tests**

```bash
cd laravel-app && php artisan test --filter=Mailbox 2>/dev/null || echo "No Mailbox tests"
```

Si no hay tests, crear un feature test mínimo:
```bash
# laravel-app/tests/Feature/Modules/Mail/MailboxControllerTest.php
```
Test: GET /mailbox devuelve 200, GET /mailbox/feed devuelve JSON con clave `messages`.

- [ ] **Step 11: Agregar /mailbox y /mail al bridge**

```php
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos', '/examenes', '/imagenes', '/agenda', '/derivaciones', '/reports', '/mailbox', '/mail'];
```

Nota: `/mail` es exacto (no es prefix de otro módulo), así que agregar también a `$laravelBridgeExact` si hay riesgo de colisión, o dejarlo en prefixes si el único uso es `/mail` → `/mail/*`.

- [ ] **Step 12: Verificar cero referencias cross-module a Mail**

```bash
grep -r "Modules\\\\Mail" modules/ --include="*.php" -l | grep -v "modules/Mail/"
grep -r "NotificationMailer\|MailboxService\|MailProfileService" modules/ --include="*.php" -l | grep -v "modules/Mail/"
```

- [ ] **Step 13: Eliminar módulo Mail**

```bash
rm -rf modules/Mail
```

- [ ] **Step 14: Smoke test**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader;
use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 15: Commit**

```bash
git add public/index.php
git add laravel-app/routes/v2/mail.php laravel-app/routes/api.php
git add laravel-app/app/Modules/Mail/
git add laravel-app/tests/Feature/Modules/Mail/ 2>/dev/null || true
git add -A modules/Mail
git commit -m "$(cat <<'EOF'
feat(onda2): migrate Mail to Laravel, delete legacy modules/Mail/

Ported MailboxController (index/feed/compose), MailboxService,
NotificationMailer, and MailProfileService to laravel-app/app/Modules/Mail/.
Bridge now intercepts /mailbox and /mail prefixes.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

| Módulo | Estado |
|--------|--------|
| Agenda | ✅ eliminado |
| Derivaciones | ✅ eliminado |
| Reporting/routes.php | ✅ eliminado (motor permanece) |
| Mail | ✅ eliminado |

- Bridge: `/agenda`, `/derivaciones`, `/reports`, `/mailbox`, `/mail` agregados
- Módulos legacy restantes: 22 → 18 (–4, Reporting engine no cuenta como eliminado aún)
- Líneas eliminadas (acum.): ~13k

**Siguiente:** Onda 3 → `docs/superpowers/plans/2026-05-21-onda3-small-independents.md`
