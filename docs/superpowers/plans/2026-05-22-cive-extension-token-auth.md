# CiveExtension Token-Based Authentication Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar la dependencia de la sesión de MedForge en la extensión Chrome, reemplazándola con autenticación por ID de extensión, para que los doctores (que nunca ingresan a MedForge) puedan usar la extensión todo el tiempo que estén en SigCenter.

**Architecture:** La extensión Chrome envía automáticamente el header `Origin: chrome-extension://<id>` en todas sus requests al background service worker. El nuevo middleware `CiveExtensionAuth` valida ese ID contra los IDs conocidos guardados en `app_settings`. El endpoint de config se vuelve público (sin auth) para que la extensión pueda arrancar sin sesión. Como segunda capa, se soporta un header `X-CiveExtension-Key` para llamadas server-to-server.

**Tech Stack:** Laravel 11 (middleware, AppSetting model, Cache facade), PHP 8.2, Chrome Extension Manifest V3 (background service worker, `chrome.runtime.id`).

---

## Contexto importante

- **`Origin: chrome-extension://<id>`** — Chrome lo setea automáticamente en todas las requests cross-origin del background service worker. No puede ser suplantado por una extensión diferente.
- **`app_settings` table** — clave `cive_extension_extension_id_local` y `cive_extension_extension_id_remote` ya contienen los IDs de extensión.
- **`ConsultasCors` middleware** — corre ANTES que `CiveExtensionAuth`. Maneja OPTIONS preflight y agrega headers CORS. Para OPTIONS retorna 204 sin llamar al siguiente middleware.
- **Config endpoint** (`/api/cive-extension/config`) — actualmente requiere `app.auth`. Debe volverse público para que la extensión pueda arrancar sin sesión activa en MedForge.
- **Rutas de extensión en `routes/api.php`** — bloque agregado recientemente con solo `consultas.cors` (sin auth). Este plan agrega `cive.extension.auth` a ese bloque.

---

## File Map

| Acción | Archivo |
|--------|---------|
| **Crear** | `laravel-app/app/Http/Middleware/CiveExtensionAuth.php` |
| **Crear** | `laravel-app/tests/Unit/Http/Middleware/CiveExtensionAuthTest.php` |
| **Modificar** | `laravel-app/bootstrap/app.php` — registrar alias `cive.extension.auth` |
| **Modificar** | `laravel-app/app/Http/Middleware/ConsultasCors.php` — fix duplicate, agregar `X-CiveExtension-Key` a allowed headers |
| **Modificar** | `laravel-app/routes/v2/cive_extension.php` — config sin auth, CORS en config, auth en health |
| **Modificar** | `laravel-app/routes/api.php` — agregar `cive.extension.auth` al bloque de rutas de extensión |
| **Modificar** | `cive_extention/background.js` — cambiar config fetch a `credentials: 'omit'` |

---

## Task 1: Crear middleware `CiveExtensionAuth`

**Files:**
- Create: `laravel-app/app/Http/Middleware/CiveExtensionAuth.php`
- Create: `laravel-app/tests/Unit/Http/Middleware/CiveExtensionAuthTest.php`

- [ ] **Step 1: Escribir los tests primero**

```php
<?php
// laravel-app/tests/Unit/Http/Middleware/CiveExtensionAuthTest.php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CiveExtensionAuth;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CiveExtensionAuthTest extends TestCase
{
    private CiveExtensionAuth $middleware;
    private \Closure $next;
    private bool $nextCalled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CiveExtensionAuth();
        $this->nextCalled = false;
        $this->next = function () {
            $this->nextCalled = true;
            return response('ok', 200);
        };
        Cache::flush();
    }

    public function test_allows_request_from_known_remote_extension_id(): void
    {
        AppSetting::query()->insert([
            ['name' => 'cive_extension_extension_id_remote', 'value' => 'abcdefghijklmnopABCDEFGHIJKLMNOP'],
            ['name' => 'cive_extension_extension_id_local',  'value' => 'localidlocalidlocalidlocalidloca'],
        ]);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('Origin', 'chrome-extension://abcdefghijklmnopABCDEFGHIJKLMNOP');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_request_from_known_local_extension_id(): void
    {
        AppSetting::query()->insert([
            ['name' => 'cive_extension_extension_id_remote', 'value' => 'remoteid'],
            ['name' => 'cive_extension_extension_id_local',  'value' => 'localid'],
        ]);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('Origin', 'chrome-extension://localid');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_request_from_unknown_extension_id(): void
    {
        AppSetting::query()->insert([
            ['name' => 'cive_extension_extension_id_remote', 'value' => 'knownremoteid'],
        ]);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('Origin', 'chrome-extension://unknownextensionid');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_allows_request_with_valid_secret_key_and_no_origin(): void
    {
        config(['services.cive_extension.secret_key' => 'mysecretkey123']);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('X-CiveExtension-Key', 'mysecretkey123');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_request_with_invalid_secret_key(): void
    {
        config(['services.cive_extension.secret_key' => 'correctkey']);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('X-CiveExtension-Key', 'wrongkey');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_no_origin_and_no_key(): void
    {
        $request = Request::create('/consultas/guardar', 'POST');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_request_when_secret_key_not_configured(): void
    {
        config(['services.cive_extension.secret_key' => '']);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('X-CiveExtension-Key', 'anykey');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

```bash
cd laravel-app && php artisan test tests/Unit/Http/Middleware/CiveExtensionAuthTest.php 2>&1
```

Esperado: errores de clase no encontrada (`CiveExtensionAuth`).

- [ ] **Step 3: Crear el middleware**

```php
<?php
// laravel-app/app/Http/Middleware/CiveExtensionAuth.php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica requests provenientes de la extensión Chrome CiveExtension.
 *
 * Dos mecanismos:
 *  1. Header Origin: chrome-extension://<id> — Chrome lo setea automáticamente.
 *     Validamos el ID contra los IDs conocidos en app_settings.
 *  2. Header X-CiveExtension-Key — para llamadas server-to-server.
 *     El valor debe coincidir con CIVE_EXTENSION_SECRET_KEY en .env.
 *
 * OPTIONS preflight es manejado por ConsultasCors antes de llegar aquí.
 */
class CiveExtensionAuth
{
    private const CACHE_KEY = 'cive_extension_allowed_ids';
    private const CACHE_TTL = 300; // 5 minutos

    public function handle(Request $request, Closure $next): Response
    {
        $origin = trim((string) $request->headers->get('Origin', ''));

        if (str_starts_with(strtolower($origin), 'chrome-extension://')) {
            $extensionId = substr($origin, strlen('chrome-extension://'));
            if ($this->isAllowedExtensionId($extensionId)) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Extensión no autorizada.',
            ], 403);
        }

        // Sin Origin de extensión → verificar API key para llamadas server-to-server
        $secretKey = trim((string) config('services.cive_extension.secret_key', ''));
        $providedKey = trim((string) $request->headers->get('X-CiveExtension-Key', ''));

        if ($secretKey !== '' && $providedKey !== '' && hash_equals($secretKey, $providedKey)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'No autorizado.',
        ], 401);
    }

    private function isAllowedExtensionId(string $id): bool
    {
        if (trim($id) === '') {
            return false;
        }

        $allowedIds = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $rows = AppSetting::query()
                ->whereIn('name', [
                    'cive_extension_extension_id_remote',
                    'cive_extension_extension_id_local',
                ])
                ->pluck('value')
                ->all();

            return array_filter(array_map('trim', $rows), fn(string $v) => $v !== '');
        });

        return in_array($id, $allowedIds, true);
    }
}
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

```bash
cd laravel-app && php artisan test tests/Unit/Http/Middleware/CiveExtensionAuthTest.php 2>&1
```

Esperado: todos los tests en verde.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Http/Middleware/CiveExtensionAuth.php \
        laravel-app/tests/Unit/Http/Middleware/CiveExtensionAuthTest.php
git commit -m "feat(cive-extension): add CiveExtensionAuth middleware — validates extension ID from Origin header"
```

---

## Task 2: Registrar el middleware y agregar secret key al config

**Files:**
- Modify: `laravel-app/bootstrap/app.php`
- Modify: `laravel-app/config/services.php`

- [ ] **Step 1: Agregar alias en bootstrap/app.php**

Abrir `laravel-app/bootstrap/app.php`. Dentro del bloque `->withMiddleware(function (Middleware $middleware): void {`, dentro del array de `$middleware->alias([...]`, agregar después de `'consultas.cors' => ConsultasCors::class,`:

```php
            'cive.extension.auth' => CiveExtensionAuth::class,
```

Agregar el import al tope del archivo (junto a los otros use):

```php
use App\Http\Middleware\CiveExtensionAuth;
```

El bloque de aliases debe quedar así:
```php
        $middleware->alias([
            'app.auth' => RequireAppSession::class,
            'app.permission' => RequireAppPermission::class,
            'legacy.auth' => RequireLegacySession::class,
            'legacy.permission' => RequireLegacyPermission::class,
            'legacy.alias' => MarkLegacyAliasUsage::class,
            'consultas.cors' => ConsultasCors::class,
            'cive.extension.auth' => CiveExtensionAuth::class,
            'whatsapp.feature' => EnsureWhatsappFeatureEnabled::class,
        ]);
```

- [ ] **Step 2: Agregar secret key a config/services.php**

Abrir `laravel-app/config/services.php`. Agregar al array:

```php
    'cive_extension' => [
        'secret_key' => env('CIVE_EXTENSION_SECRET_KEY', ''),
    ],
```

- [ ] **Step 3: Agregar CIVE_EXTENSION_SECRET_KEY al .env de producción**

Generar una clave segura:
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Agregar al archivo `.env` de producción (en el servidor):
```
CIVE_EXTENSION_SECRET_KEY=<el valor generado>
```

También agregar al `.env.example` del repo con valor vacío:
```
CIVE_EXTENSION_SECRET_KEY=
```

- [ ] **Step 4: Verificar que Laravel carga la config**

```bash
cd laravel-app && php artisan config:clear && php artisan tinker --execute="echo config('services.cive_extension.secret_key', 'NOT_SET');"
```

Esperado: si está en `.env` debe mostrar el valor. Si no está, muestra vacío (no error).

- [ ] **Step 5: Commit**

```bash
git add laravel-app/bootstrap/app.php laravel-app/config/services.php laravel-app/.env.example
git commit -m "feat(cive-extension): register cive.extension.auth alias and add secret key config"
```

---

## Task 3: Corregir ConsultasCors — eliminar duplicado y agregar X-CiveExtension-Key

**Files:**
- Modify: `laravel-app/app/Http/Middleware/ConsultasCors.php`

- [ ] **Step 1: Abrir el archivo**

Archivo: `laravel-app/app/Http/Middleware/ConsultasCors.php`

Estado actual del array `allowedOrigins`:
```php
    private array $allowedOrigins = [
        'http://cive.ddns.net',
        'https://cive.ddns.net',
        'http://sigcenter.ddns.net',
        'https://sigcenter.ddns.net',
        'http://sigcenter.ddns.net:18093',
        'http://sigcenter.ddns.net:18093',   // ← DUPLICADO
        'https://sigcenter.ddns.net:18093',
        'http://192.168.1.13:8085',
        'http://localhost:8085',
        'http://127.0.0.1:8085',
        'https://asistentecive.consulmed.me',
        'https://cive.consulmed.me',
    ];
```

Estado actual del método `withCorsHeaders`:
```php
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-Id, X-Requested-With');
```

- [ ] **Step 2: Aplicar los dos cambios**

**Cambio 1** — eliminar el `http://sigcenter.ddns.net:18093` duplicado (la segunda ocurrencia):

```php
    private array $allowedOrigins = [
        'http://cive.ddns.net',
        'https://cive.ddns.net',
        'http://sigcenter.ddns.net',
        'https://sigcenter.ddns.net',
        'http://sigcenter.ddns.net:18093',
        'https://sigcenter.ddns.net:18093',
        'http://192.168.1.13:8085',
        'http://localhost:8085',
        'http://127.0.0.1:8085',
        'https://asistentecive.consulmed.me',
        'https://cive.consulmed.me',
    ];
```

**Cambio 2** — agregar `X-CiveExtension-Key` a los headers permitidos:

```php
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-Id, X-Requested-With, X-CiveExtension-Key');
```

- [ ] **Step 3: Verificar que las rutas siguen respondiendo**

```bash
cd laravel-app && php artisan route:list --path=consultas 2>&1
```

Esperado: lista de rutas sin errores.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Http/Middleware/ConsultasCors.php
git commit -m "fix(cive-extension): remove duplicate CORS origin, add X-CiveExtension-Key to allowed headers"
```

---

## Task 4: Actualizar rutas — config público, nueva auth en endpoints de extensión

**Files:**
- Modify: `laravel-app/routes/v2/cive_extension.php`
- Modify: `laravel-app/routes/api.php`

### Parte A: `cive_extension.php`

- [ ] **Step 1: Ver el estado actual**

```php
// laravel-app/routes/v2/cive_extension.php — estado actual
Route::middleware('app.auth')->prefix('api/cive-extension')->group(function (): void {
    Route::get('/config', [ConfigController::class, 'show']);

    Route::middleware('app.permission:settings.manage,administrativo')->group(function (): void {
        Route::post('/health-check', [HealthController::class, 'run']);
        Route::get('/health-checks', [HealthController::class, 'index']);
    });
});
```

- [ ] **Step 2: Reemplazar con el nuevo contenido**

```php
<?php

use App\Modules\CiveExtension\Http\Controllers\ConfigController;
use App\Modules\CiveExtension\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// /api/cive-extension routes — consumed by Chrome extension (asistentecive.consulmed.me)

// Config es público — la extensión lo llama al arrancar sin sesión activa en MedForge.
// ConsultasCors agrega headers CORS para que el background service worker pueda acceder.
Route::middleware('consultas.cors')->prefix('api/cive-extension')->group(function (): void {
    Route::get('/config', [ConfigController::class, 'show']);
});

// Health checks requieren sesión de admin MedForge (solo lo usa el administrador desde la UI).
Route::middleware('app.auth')->prefix('api/cive-extension')->group(function (): void {
    Route::middleware('app.permission:settings.manage,administrativo')->group(function (): void {
        Route::post('/health-check', [HealthController::class, 'run']);
        Route::get('/health-checks', [HealthController::class, 'index']);
    });
});
```

### Parte B: `api.php` — agregar `cive.extension.auth` al bloque de extensión

- [ ] **Step 3: Localizar el bloque agregado recientemente**

En `laravel-app/routes/api.php`, el bloque que empieza con:
```php
Route::middleware(['consultas.cors'])->group(function (): void {
    foreach ([
        '/consultas/guardar',
        ...
```

- [ ] **Step 4: Cambiar el middleware del bloque**

Reemplazar:
```php
Route::middleware(['consultas.cors'])->group(function (): void {
```

Por:
```php
Route::middleware(['consultas.cors', 'cive.extension.auth'])->group(function (): void {
```

- [ ] **Step 5: Verificar que las rutas se registran correctamente**

```bash
cd laravel-app && php artisan route:list --path=consultas 2>&1
cd laravel-app && php artisan route:list --path=api/cive-extension 2>&1
```

Esperado: rutas listadas sin errores.

- [ ] **Step 6: Commit**

```bash
git add laravel-app/routes/v2/cive_extension.php laravel-app/routes/api.php
git commit -m "feat(cive-extension): make config endpoint public, add cive.extension.auth to extension API routes"
```

---

## Task 5: Actualizar background.js — config sin credenciales

**Files:**
- Modify: `cive_extention/background.js`

El config endpoint ahora es público. La función `fetchRemoteConfig` actualmente usa `credentials: 'include'`. Esto ya no es necesario para el config (y semánticamente incorrecto ya que el endpoint no requiere sesión).

- [ ] **Step 1: Localizar `fetchRemoteConfig` en background.js**

Buscar la función `fetchRemoteConfig` (~línea 93):

```javascript
async function fetchRemoteConfig(reason = "auto") {
  const endpoint = await determineControlEndpoint();
  try {
    const response = await fetch(endpoint, {
      method: "GET",
      credentials: "include",   // ← cambiar esto
    });
```

- [ ] **Step 2: Cambiar `credentials: "include"` por `credentials: "omit"`**

```javascript
async function fetchRemoteConfig(reason = "auto") {
  const endpoint = await determineControlEndpoint();
  try {
    const response = await fetch(endpoint, {
      method: "GET",
      credentials: "omit",   // Config es público, no requiere cookies de sesión
    });
```

- [ ] **Step 3: Verificar que el cambio es solo esa línea**

```bash
grep -n "credentials" /Users/jorgeluisdevera/PhpstormProjects/MedForge/cive_extention/background.js
```

Esperado: `credentials: "omit"` en `fetchRemoteConfig`, `credentials: "include"` en el handler `apiRequest` (ese debe quedar con `include`).

- [ ] **Step 4: Commit**

```bash
git add cive_extention/background.js
git commit -m "feat(cive-extension): fetch remote config without session credentials — endpoint is now public"
```

---

## Task 6: Verificación end-to-end

- [ ] **Step 1: Correr la suite de tests completa**

```bash
cd laravel-app && php artisan test 2>&1
```

Esperado: todos los tests pasan incluido `CiveExtensionAuthTest`.

- [ ] **Step 2: Verificar rutas finales**

```bash
cd laravel-app && php artisan route:list --path=consultas 2>&1
cd laravel-app && php artisan route:list --path=api/cive-extension 2>&1
cd laravel-app && php artisan route:list --path=api/proyecciones 2>&1
```

Confirmar que:
- `/consultas/guardar` y similares tienen middleware `consultas.cors, cive.extension.auth`
- `/api/cive-extension/config` tiene middleware `consultas.cors` (sin `app.auth`)
- `/api/cive-extension/health-check` tiene middleware `app.auth, app.permission`

- [ ] **Step 3: Test manual de CORS preflight**

Desde terminal local (simula el browser):
```bash
curl -v -X OPTIONS \
  -H "Origin: chrome-extension://testid" \
  -H "Access-Control-Request-Method: POST" \
  https://cive.consulmed.me/consultas/guardar
```

Esperado: respuesta 204 con `Access-Control-Allow-Origin: chrome-extension://testid`.

- [ ] **Step 4: Test manual de config sin sesión**

```bash
curl -v https://cive.consulmed.me/api/cive-extension/config
```

Esperado: JSON con `success: true` y el config, sin cookies.

- [ ] **Step 5: Desplegar a producción**

```bash
# En el servidor de producción
git fetch origin && git reset --hard origin/main
php artisan config:clear
php artisan route:clear
```

- [ ] **Step 6: Agregar la extension ID a app_settings en producción (si no existe)**

Verificar que `cive_extension_extension_id_remote` esté en `app_settings` con el ID real de la extensión en Chrome Web Store. Si no está:

```sql
INSERT INTO app_settings (name, value) 
VALUES ('cive_extension_extension_id_remote', '<chrome-extension-id>')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

El ID real de la extensión se puede ver en `chrome://extensions` cuando está instalada.

---

## Resumen de cambios de seguridad

| Endpoint | Antes | Después |
|----------|-------|---------|
| `GET /api/cive-extension/config` | `app.auth` (requiere sesión MedForge) | público + CORS |
| `POST /api/cive-extension/health-check` | `app.auth` + permission | `app.auth` + permission (sin cambio) |
| `POST /consultas/guardar` y aliases | solo `consultas.cors` (❌ sin auth) | `consultas.cors` + `cive.extension.auth` |
| `GET /consultas/anterior` y aliases | solo `consultas.cors` (❌ sin auth) | `consultas.cors` + `cive.extension.auth` |
| `GET/POST /api/solicitudes/estado` | solo `consultas.cors` (❌ sin auth) | `consultas.cors` + `cive.extension.auth` |
| `GET/POST /api/proyecciones/*.php` | solo `consultas.cors` (❌ sin auth) | `consultas.cors` + `cive.extension.auth` |
