# Onda 5-D — WhatsApp + Autoresponder: Verificar, Bridge, Delete

> **For agentic workers:** Use **superpowers:executing-plans** para ejecutar este plan en la misma sesión.
>
> **Execution mode:** executing-plans (~0.5 días, principalmente verificación y borrado)
>
> **Prerequisito:** Ondas 5-A, 5-B, 5-C completas.

**Goal:** Verificar que las 24 rutas legacy de WhatsApp tienen cobertura en el módulo Laravel (60 rutas v2), agregar `/whatsapp` al bridge, y eliminar `modules/WhatsApp/` y `modules/Autoresponder/` (código muerto — reemplazado por Flowmaker en Laravel).

**Architecture:**
- WhatsApp legacy usa paths `/whatsapp/api/X` → el SPA moderno llama a `/X` directamente (rutas v2 sin prefijo `/whatsapp/api/`)
- La verificación clave es confirmar que ningún frontend activo llama a los paths legacy
- Autoresponder: `GET/POST /whatsapp/autoresponder` es el editor PHP antiguo — reemplazado por Flowmaker SPA en Laravel
- `FlowmakerController` legacy (`/whatsapp/flowmaker`, `/whatsapp/api/flowmaker/*`) ya existe en Laravel como `FlowmakerReadController` + `FlowmakerWriteController`

**Estado conocido:**
- `POST /whatsapp/webhook` → ya en `$laravelBridgeExact` en `public/index.php`
- Laravel `Whatsapp` module: 21 controllers, 28 services — muy completo
- `WhatsappUiController` tiene: `chat()`, `templates()`, `dashboard()`, `flowmaker()`, etc.

---

## Task 1: Verificar cobertura de rutas UI de WhatsApp

Las rutas UI legacy son: `GET /whatsapp/templates`, `GET /whatsapp/chat`, `GET /whatsapp/dashboard`. Estas deben estar cubiertas por `WhatsappUiController` en Laravel.

**Files:**
- Read: `laravel-app/routes/web.php` — rutas UI de WhatsApp
- Read: `laravel-app/app/Modules/Whatsapp/Http/Controllers/WhatsappUiController.php`

- [ ] **Step 1: Verificar rutas UI en web.php**

```bash
grep -n "whatsapp\|chat\|templates\|dashboard" \
  laravel-app/routes/web.php | head -20
```

Confirmar que `GET /whatsapp/chat`, `GET /whatsapp/templates`, `GET /whatsapp/dashboard` están registradas.

- [ ] **Step 2: Si faltan, agregarlas**

Si `GET /whatsapp/chat` no está en `routes/web.php` o `routes/v2/whatsapp.php`, agregar:

```php
// En laravel-app/routes/v2/whatsapp.php, dentro del grupo de middleware:
Route::get('/whatsapp/chat', [WhatsappUiController::class, 'chat']);
Route::get('/whatsapp/templates', [WhatsappUiController::class, 'templates']);
Route::get('/whatsapp/dashboard', [WhatsappUiController::class, 'dashboard']);
Route::get('/whatsapp/flowmaker', [WhatsappUiController::class, 'flowmaker']);
```

- [ ] **Step 3: Verificar que /whatsapp/flowmaker existe en Laravel**

```bash
cd laravel-app && php artisan route:list | grep "whatsapp/flowmaker"
```

Expected: aparece con `WhatsappUiController::flowmaker`.

---

## Task 2: Verificar que el SPA no llama a rutas legacy /whatsapp/api/*

El frontend React llama a la API. Si usa `/conversations` (path v2) en lugar de `/whatsapp/api/conversations` (path legacy), las rutas legacy son código muerto.

- [ ] **Step 1: Buscar llamadas a /whatsapp/api/ en el frontend**

```bash
grep -r "whatsapp/api/" laravel-app/resources/ --include="*.js" --include="*.ts" --include="*.vue" 2>/dev/null | head -20
grep -r "whatsapp/api/" laravel-app/resources/ --include="*.js" --include="*.ts" 2>/dev/null | wc -l
```

- [ ] **Step 2: Interpretar resultado**

**Si cero resultados** → el SPA usa rutas v2 directas (`/conversations`, `/agents`, etc.) → las 20 rutas legacy `/whatsapp/api/*` son código muerto → continuar.

**Si hay resultados** → el SPA aún llama a paths legacy. Para cada path legacy encontrado:
  - Verificar si existe una ruta v2 equivalente en `laravel-app/routes/v2/whatsapp.php`
  - Agregar un alias en v2/whatsapp.php con el path legacy apuntando al mismo controller:
    ```php
    Route::get('/whatsapp/api/conversations', [ConversationReadController::class, 'index']);
    ```

- [ ] **Step 3: Verificar específicamente /whatsapp/api/flowmaker/contract y /publish**

```bash
grep -r "api/flowmaker/contract\|api/flowmaker/publish" \
  laravel-app/resources/ --include="*.js" --include="*.ts" --include="*.vue" 2>/dev/null
```

Si hay referencias → agregar alias en v2/whatsapp.php:
```php
Route::get('/whatsapp/api/flowmaker/contract', [FlowmakerReadController::class, 'contract']);
Route::post('/whatsapp/api/flowmaker/publish', [FlowmakerWriteController::class, 'publish']);
```

Si cero → son paths del Autoresponder legacy (ya muerto), ignorar.

---

## Task 3: Verificar cero referencias cross-module hacia WhatsApp legacy

- [ ] **Step 1: Grep de dependencias**

```bash
grep -r "Modules\\\\WhatsApp\|WhatsApp\\\\" \
  modules/ --include="*.php" | \
  grep -v "modules/WhatsApp/\|modules/Autoresponder/"
```

Con CRM eliminado en 5-C, las únicas referencias restantes deberían ser:
- `modules/examenes/` (si aún existe — verificar)
- `modules/CRM/Models/LeadModel.php` → eliminado en 5-C

Expected: cero resultados externos. Si aparece algo, leerlo y resolverlo.

- [ ] **Step 2: Verificar Shared y Notifications legacy**

WhatsApp legacy usa `Modules\Notifications\Services\PusherConfigService` y `Modules\Shared\Services\SchemaInspector`. Estos módulos aún existen (se eliminarán en Onda 6). Verificar que la versión Laravel de WhatsApp **no** usa las versiones legacy:

```bash
grep -r "Modules\\\\Notifications\|Modules\\\\Shared" \
  laravel-app/app/Modules/Whatsapp/ --include="*.php"
```

Expected: usa `App\Modules\Shared\...` o equivalente Laravel, no el legacy.

---

## Task 4: Agregar /whatsapp al bridge y eliminar WhatsApp legacy

**Files:**
- Modify: `public/index.php`
- Delete: `modules/WhatsApp/`

- [ ] **Step 1: Agregar /whatsapp al bridge**

En `public/index.php`, el array `$laravelBridgePrefixes`:

```php
$laravelBridgePrefixes = [
    '/v2', '/usuarios', '/roles', '/feedback', '/protocolos',
    '/examenes', '/imagenes', '/agenda', '/derivaciones', '/reports',
    '/mailbox', '/mail', '/ai', '/search', '/api/cive-extension',
    '/mail-templates', '/insumos', '/kpis', '/doctores',
    '/pacientes', '/cirugias', '/cron-manager',
    '/billing', '/informes',
    '/crm', '/leads',
    '/whatsapp',   // ← Onda 5-D (webhook ya estaba en $laravelBridgeExact)
];
```

Nota: `POST /whatsapp/webhook` seguirá funcionando — el prefix `/whatsapp` en `$laravelBridgePrefixes` captura todas las rutas `/whatsapp/*`, y el `$laravelBridgeExact` que tenía el webhook es redundante pero inofensivo.

- [ ] **Step 2: Smoke test del bridge**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 3: Eliminar modules/WhatsApp/**

```bash
rm -rf modules/WhatsApp
```

- [ ] **Step 4: Smoke test post-delete**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 5: Tests Laravel**

```bash
cd laravel-app && php artisan test --filter=Whatsapp 2>&1 | tail -15
```

Los 29 fallos pre-existentes (`WhatsappCampaignsTest`, `WhatsappWebhookControllerTest`, `ImagenesNasIndexServiceTest`) son conocidos — no bloquean. Verificar que no hayan aparecido nuevos fallos.

- [ ] **Step 6: Commit**

```bash
git add public/index.php
git add -A modules/WhatsApp
git commit -m "$(cat <<'EOF'
feat(onda5d): delete WhatsApp legacy — add /whatsapp bridge prefix

All 24 legacy routes covered by 60 v2 routes in app/Modules/Whatsapp.
Bridge now intercepts all /whatsapp/* traffic (webhook was already in exact list).
Deleted modules/WhatsApp/ (29 files, 15.3k lines).

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Eliminar Autoresponder (código muerto)

**Files:**
- Delete: `modules/Autoresponder/`

Autoresponder **no necesita migración**:
- `GET/POST /whatsapp/autoresponder` → editor PHP legacy, reemplazado por Flowmaker SPA
- `GET /whatsapp/flowmaker` → ya en `WhatsappUiController::flowmaker()` en Laravel
- `GET /whatsapp/api/flowmaker/contract` → ya en `FlowmakerReadController::contract()`
- `POST /whatsapp/api/flowmaker/publish` → ya en `FlowmakerWriteController::publish()`

Con el bridge `/whatsapp` activo:
- `/whatsapp/flowmaker` → Laravel → `WhatsappUiController::flowmaker()` ✅
- `/whatsapp/autoresponder` → Laravel → no existe ruta → 404 (comportamiento correcto — URL muerta)

- [ ] **Step 1: Confirmar cero referencias externas a Autoresponder**

```bash
grep -r "Modules\\\\Autoresponder" modules/ --include="*.php" | \
  grep -v "modules/Autoresponder/"
grep -r "Modules\\\\Autoresponder" laravel-app/ --include="*.php"
```

Expected: cero resultados. (WhatsApp legacy ya fue eliminado en Task 4 — era el único consumer).

- [ ] **Step 2: Confirmar que /whatsapp/flowmaker funciona en Laravel**

```bash
cd laravel-app && php artisan route:list | grep "whatsapp/flowmaker"
```

Expected: aparece `WhatsappUiController::flowmaker`.

- [ ] **Step 3: Eliminar Autoresponder**

```bash
rm -rf modules/Autoresponder
```

- [ ] **Step 4: Smoke test final**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 5: Commit final**

```bash
git add -A modules/Autoresponder
git commit -m "$(cat <<'EOF'
feat(onda5d): delete Autoresponder legacy — dead code, replaced by Flowmaker

GET/POST /whatsapp/autoresponder: old PHP editor replaced by Flowmaker SPA.
GET /whatsapp/flowmaker: handled by WhatsappUiController::flowmaker().
GET /whatsapp/api/flowmaker/contract: handled by FlowmakerReadController.
POST /whatsapp/api/flowmaker/publish: handled by FlowmakerWriteController.
Deleted modules/Autoresponder/ (11 files, 5.3k lines).

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado final de Onda 5

| Sesión | Módulo | Estado |
|--------|--------|--------|
| 5-A | Billing | ✅ eliminado |
| 5-B | CRM Leads | ✅ portado |
| 5-C | CRM Entities + delete | ✅ eliminado |
| 5-D | WhatsApp + Autoresponder | ✅ eliminados |

- Módulos legacy restantes: solo Onda 6 (Reporting engine, Shared, Notifications, Core, Flujo)
- Líneas eliminadas en Onda 5: ~43k (Billing 13k + CRM 9.4k + WhatsApp 15.3k + Autoresponder 5.3k)
- Total acumulado: ~81k

**Siguiente:** `docs/superpowers/plans/2026-05-21-onda6-infrastructure.md`
