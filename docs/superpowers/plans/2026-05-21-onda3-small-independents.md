# Onda 3 — Pequeños Independientes: AI, Search, CiveExtension, MailTemplates, Insumos, KPI, Doctores

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development** to execute this plan. Dispatch a fresh subagent per task (one subagent per module). Each module is independent — they can be analyzed in parallel but must be committed sequentially.
>
> **Execution mode:** subagent-driven (7 módulos, paralelizables, sin dependencias entre ellos)
>
> **Master roadmap:** `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`

**Goal:** Migrar 7 módulos pequeños (197–3.4k líneas cada uno) que comparten el mismo patrón: cero dependencias entrantes cross-module, cero v2 routes existentes. Para cada uno: crear controlador/servicios en `laravel-app/app/Modules/<Mod>/`, registrar rutas en `laravel-app/routes/v2/<mod>.php`, agregar prefijo al bridge, y eliminar `modules/<Modulo>/`.

**Architecture:** Patrón idéntico para los 7:
1. Leer código legacy completo
2. Crear parity en Laravel (`laravel-app/app/Modules/<Mod>/`)
3. Registrar rutas en `laravel-app/routes/v2/<mod>.php` + incluir en `routes/api.php`
4. Agregar prefijo a `$laravelBridgePrefixes` en `public/index.php`
5. Verificar grep de cero referencias externas
6. `rm -rf modules/<Modulo>/`
7. Commit

**Prerequisitos:** Onda 2 completada.

**Importante antes de implementar cada módulo:** Verificar si ya existe algún módulo equivalente en `laravel-app/app/Modules/` — algunos pueden estar parcialmente portados.

```bash
ls laravel-app/app/Modules/
```

---

## Task 1: AI — portar 2 endpoints de generación con IA

**Legacy:** `modules/AI/` — 2 archivos, 197 líneas
- `Services/AIConfigService.php`
- `routes.php` → `POST /ai/enfermedad`, `POST /ai/plan`
- Usa `use Controllers\AIController` (nota: no `Modules\AI\Controllers` — el controller está en `controllers/` raíz)

**Files a leer:**
- `modules/AI/routes.php`
- `modules/AI/Services/AIConfigService.php`
- `controllers/AIController.php` (raíz — namespace `Controllers`, no `Modules\AI`)

**Files a crear:**
- `laravel-app/app/Modules/AI/Http/Controllers/AIController.php`
- `laravel-app/app/Modules/AI/Services/AIConfigService.php`
- `laravel-app/routes/v2/ai.php`
- Modify: `laravel-app/routes/api.php`
- Modify: `public/index.php`

- [ ] **Step 1: Leer código legacy**

```bash
cat modules/AI/routes.php
cat modules/AI/Services/AIConfigService.php
cat controllers/AIController.php
```

- [ ] **Step 2: Verificar existencia en Laravel**

```bash
ls laravel-app/app/Modules/ | grep -i ai
find laravel-app/app/Modules -name "*AI*" -o -name "*Ai*" 2>/dev/null
```

- [ ] **Step 3: Verificar cero refs externas**

```bash
grep -r "Controllers\\\\AIController\|Modules\\\\AI" modules/ --include="*.php" -l | grep -v "modules/AI/"
grep -r "/ai/" laravel-app/routes/ --include="*.php"
```

- [ ] **Step 4: Crear AIConfigService en Laravel**

Copiar `modules/AI/Services/AIConfigService.php` a `laravel-app/app/Modules/AI/Services/AIConfigService.php`. Cambiar namespace a `App\Modules\AI\Services`. Reemplazar acceso legacy a config/settings por `DB::table('settings')` o `config()` de Laravel.

- [ ] **Step 5: Crear AIController en Laravel**

Crear `laravel-app/app/Modules/AI/Http/Controllers/AIController.php` portando la lógica de `controllers/AIController.php`. Ambos endpoints son POST que reciben datos del usuario y llaman a la API de IA (OpenAI/Anthropic). Usar `Illuminate\Http\Request` y `Illuminate\Http\JsonResponse`.

- [ ] **Step 6: Crear routes/v2/ai.php**

```php
<?php

use App\Modules\AI\Http\Controllers\AIController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::post('/ai/enfermedad', [AIController::class, 'generarEnfermedad']);
    Route::post('/ai/plan', [AIController::class, 'generarPlan']);
});
```

- [ ] **Step 7: Registrar en routes/api.php**

```php
require __DIR__ . '/v2/ai.php';
```

- [ ] **Step 8: Agregar /ai al bridge**

```php
$laravelBridgePrefixes = [..., '/ai'];
```

- [ ] **Step 9: Verificar rutas**

```bash
cd laravel-app && php artisan route:list | grep "/ai"
```

- [ ] **Step 10: Eliminar módulo AI**

```bash
rm -rf modules/AI
```

Nota: `controllers/AIController.php` en la raíz también puede eliminarse si no tiene otras referencias. Verificar:
```bash
grep -r "AIController" modules/ --include="*.php" | grep -v "modules/AI/"
grep -r "Controllers\\\\AIController" . --include="*.php" | grep -v ".git"
```
Si cero referencias, eliminar `controllers/AIController.php`.

- [ ] **Step 11: Smoke test + commit**

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

git add public/index.php laravel-app/routes/v2/ai.php laravel-app/routes/api.php
git add laravel-app/app/Modules/AI/
git add -A modules/AI
git add controllers/AIController.php 2>/dev/null || true
git commit -m "$(cat <<'EOF'
feat(onda3): migrate AI module to Laravel, delete legacy

Ported POST /ai/enfermedad and POST /ai/plan to app/Modules/AI.
Bridge now intercepts /ai prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Search — portar búsqueda global

**Legacy:** `modules/Search/` — 3 archivos, 709 líneas
- `Controllers/SearchController.php`
- `Services/GlobalSearchService.php`
- `routes.php` → `GET /search`, `POST /search/history/clear`

**Files a leer:**
- `modules/Search/routes.php`
- `modules/Search/Controllers/SearchController.php`
- `modules/Search/Services/GlobalSearchService.php`

**Files a crear:**
- `laravel-app/app/Modules/Search/Http/Controllers/SearchController.php`
- `laravel-app/app/Modules/Search/Services/GlobalSearchService.php`
- `laravel-app/routes/v2/search.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy**

```bash
cat modules/Search/routes.php
cat modules/Search/Controllers/SearchController.php
cat modules/Search/Services/GlobalSearchService.php
```

- [ ] **Step 2: Verificar existencia en Laravel y refs externas**

```bash
ls laravel-app/app/Modules/ | grep -i search
grep -r "Modules\\\\Search" modules/ --include="*.php" -l | grep -v "modules/Search/"
```

- [ ] **Step 3: Portar GlobalSearchService**

Crear `laravel-app/app/Modules/Search/Services/GlobalSearchService.php`. El servicio probablemente consulta múltiples tablas (pacientes, solicitudes, etc.). Usar `DB::select()` o Query Builder. Namespace: `App\Modules\Search\Services`.

- [ ] **Step 4: Portar SearchController**

Crear `laravel-app/app/Modules/Search/Http/Controllers/SearchController.php`. Métodos: `index(Request $request)` y `clearHistory(Request $request)`.

- [ ] **Step 5: Crear routes/v2/search.php**

```php
<?php

use App\Modules\Search\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/search', [SearchController::class, 'index']);
    Route::post('/search/history/clear', [SearchController::class, 'clearHistory']);
});
```

- [ ] **Step 6: Registrar, agregar bridge, eliminar, commit**

```bash
# Agregar require en routes/api.php
# Agregar '/search' a $laravelBridgePrefixes en public/index.php
rm -rf modules/Search

cd laravel-app && php artisan route:list | grep "/search"

git add public/index.php laravel-app/routes/v2/search.php laravel-app/routes/api.php
git add laravel-app/app/Modules/Search/
git add -A modules/Search
git commit -m "$(cat <<'EOF'
feat(onda3): migrate Search to Laravel, delete legacy

Ported GET /search and POST /search/history/clear to app/Modules/Search.
Bridge now intercepts /search prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: CiveExtension — portar 3 endpoints de API para la extensión Chrome

**Legacy:** `modules/CiveExtension/` — 6 archivos, 685 líneas
- `Controllers/ConfigController.php`, `Controllers/HealthController.php`
- `Models/HealthCheckModel.php`
- `Services/ConfigService.php`, `Services/HealthCheckService.php`
- `routes.php` → `GET /api/cive-extension/config`, `POST /api/cive-extension/health-check`, `GET /api/cive-extension/health-checks`

**Contexto:** La extensión Chrome usa el dominio `asistentecive.consulmed.me`. Los endpoints son para configuración y health checks de la extensión. No cambiar los paths ni la estructura de respuesta JSON.

**Files a leer:**
```bash
cat modules/CiveExtension/routes.php
cat modules/CiveExtension/Controllers/ConfigController.php
cat modules/CiveExtension/Controllers/HealthController.php
cat modules/CiveExtension/Services/ConfigService.php
cat modules/CiveExtension/Services/HealthCheckService.php
cat modules/CiveExtension/Models/HealthCheckModel.php
```

**Files a crear:**
- `laravel-app/app/Modules/CiveExtension/Http/Controllers/ConfigController.php`
- `laravel-app/app/Modules/CiveExtension/Http/Controllers/HealthController.php`
- `laravel-app/app/Modules/CiveExtension/Services/ConfigService.php`
- `laravel-app/app/Modules/CiveExtension/Services/HealthCheckService.php`
- `laravel-app/app/Modules/CiveExtension/Models/HealthCheckModel.php` (o Query Builder directo)
- `laravel-app/routes/v2/cive_extension.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Verificar refs externas**

```bash
grep -r "Modules\\\\CiveExtension" modules/ --include="*.php" -l | grep -v "modules/CiveExtension/"
grep -r "cive-extension\|CiveExtension" laravel-app/ --include="*.php" | head -10
```

- [ ] **Step 3: Portar servicios y controladores** (namespace `App\Modules\CiveExtension\...`)

- [ ] **Step 4: Crear routes/v2/cive_extension.php**

```php
<?php

use App\Modules\CiveExtension\Http\Controllers\ConfigController;
use App\Modules\CiveExtension\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Estos endpoints son consumidos por la extensión Chrome — no requieren sesión web
// sino auth por API token o sin auth si es interna
Route::get('/api/cive-extension/config', [ConfigController::class, 'show']);
Route::post('/api/cive-extension/health-check', [HealthController::class, 'run']);
Route::get('/api/cive-extension/health-checks', [HealthController::class, 'index']);
```

Verificar el mecanismo de autenticación del legacy (¿session? ¿sin auth? ¿IP whitelist?) y replicarlo.

- [ ] **Step 5: Agregar al bridge**

El prefijo `/api/cive-extension` → agregar `/api/cive-extension` a `$laravelBridgePrefixes` en `public/index.php`.

Nota: si ya hay un prefijo `/api` en el bridge, estas rutas ya están cubiertas. Verificar:
```bash
grep "laravelBridgePrefixes\|laravelBridgeExact" public/index.php
```

- [ ] **Step 6: Eliminar, smoke test, commit**

```bash
rm -rf modules/CiveExtension

php -r "
define('BASE_PATH', __DIR__); define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"

git add public/index.php laravel-app/routes/v2/cive_extension.php laravel-app/routes/api.php
git add laravel-app/app/Modules/CiveExtension/
git add -A modules/CiveExtension
git commit -m "$(cat <<'EOF'
feat(onda3): migrate CiveExtension to Laravel, delete legacy

Ported 3 Chrome extension API endpoints to app/Modules/CiveExtension.
Bridge intercepts /api/cive-extension prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: MailTemplates — portar gestión de plantillas de email de cobertura

**Legacy:** `modules/MailTemplates/` — 5 archivos, 894 líneas
- `Controllers/CoberturaMailTemplateController.php`
- `Models/MailTemplateModel.php`
- `Services/CoberturaMailTemplateService.php`
- `views/cobertura.php`
- `routes.php` → `GET /mail-templates/cobertura`, `GET /mail-templates/cobertura/{key}`, `POST /mail-templates/cobertura/{key}`, `POST /mail-templates/cobertura/resolve`

**Files a leer:**
```bash
cat modules/MailTemplates/routes.php
cat modules/MailTemplates/Controllers/CoberturaMailTemplateController.php
cat modules/MailTemplates/Services/CoberturaMailTemplateService.php
cat modules/MailTemplates/Models/MailTemplateModel.php
```

**Files a crear:**
- `laravel-app/app/Modules/MailTemplates/Http/Controllers/CoberturaMailTemplateController.php`
- `laravel-app/app/Modules/MailTemplates/Services/CoberturaMailTemplateService.php`
- `laravel-app/routes/v2/mail_templates.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Verificar refs externas**

```bash
grep -r "Modules\\\\MailTemplates\|CoberturaMailTemplate\|MailTemplateModel" modules/ --include="*.php" -l | grep -v "modules/MailTemplates/"
grep -r "mail-templates\|MailTemplates" laravel-app/ --include="*.php" | head -10
```

- [ ] **Step 3: Portar servicios y controladores** (namespace `App\Modules\MailTemplates\...`)

La vista `views/cobertura.php` puede portarse como Blade o mantenerse como JSON response si es API-only. Verificar si el controller renderiza HTML o retorna JSON.

- [ ] **Step 4: Crear routes/v2/mail_templates.php y registrar**

```php
<?php

use App\Modules\MailTemplates\Http\Controllers\CoberturaMailTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo'])->group(function (): void {
    Route::get('/mail-templates/cobertura', [CoberturaMailTemplateController::class, 'index']);
    Route::get('/mail-templates/cobertura/{key}', [CoberturaMailTemplateController::class, 'show']);
    Route::post('/mail-templates/cobertura/{key}', [CoberturaMailTemplateController::class, 'save']);
    Route::post('/mail-templates/cobertura/resolve', [CoberturaMailTemplateController::class, 'resolve']);
});
```

- [ ] **Step 5: Agregar /mail-templates al bridge, eliminar, commit**

```bash
# Agregar '/mail-templates' a $laravelBridgePrefixes
rm -rf modules/MailTemplates
git add ... && git commit -m "feat(onda3): migrate MailTemplates to Laravel, delete legacy ..."
```

---

## Task 5: Insumos — portar gestión de insumos, medicamentos y lentes

**Legacy:** `modules/Insumos/` — 8 archivos, 813 líneas
- `Controllers/InsumosController.php`, `Controllers/LentesController.php`
- `Models/LenteModel.php`
- `Services/InsumoService.php`
- `views/`: index.php, lentes.php, medicamentos.php
- `routes.php` → 11 rutas: CRUD de insumos, medicamentos y lentes

**Rutas legacy:**
- `GET /insumos`, `GET /insumos/list`, `POST /insumos/guardar`
- `GET /insumos/medicamentos`, `GET /insumos/medicamentos/list`, `POST /insumos/medicamentos/guardar`, `POST /insumos/medicamentos/eliminar`
- `GET /insumos/lentes`, `GET /insumos/lentes/list`, `POST /insumos/lentes/guardar`, `POST /insumos/lentes/eliminar`

**Files a leer:**
```bash
cat modules/Insumos/routes.php
cat modules/Insumos/Controllers/InsumosController.php
cat modules/Insumos/Controllers/LentesController.php
cat modules/Insumos/Services/InsumoService.php
cat modules/Insumos/Models/LenteModel.php
```

**Files a crear:**
- `laravel-app/app/Modules/Insumos/Http/Controllers/InsumosController.php`
- `laravel-app/app/Modules/Insumos/Http/Controllers/LentesController.php`
- `laravel-app/app/Modules/Insumos/Services/InsumoService.php`
- `laravel-app/routes/v2/insumos.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Verificar refs externas**

```bash
grep -r "Modules\\\\Insumos\|InsumoService\|LenteModel" modules/ --include="*.php" -l | grep -v "modules/Insumos/"
```

Nota: Insumos es utilizado por Cirugias (wizard de cirugías registra insumos). Verificar:
```bash
grep -r "insumos\|Insumos" modules/Cirugias/ --include="*.php"
grep -r "insumos\|Insumos" modules/EditorProtocolos/ --include="*.php" 2>/dev/null
```
Si Cirugias usa `InsumoService`, planear que Cirugias también migre en Onda 4 — el servicio en Laravel estará disponible.

- [ ] **Step 3: Portar servicios y controladores** (namespace `App\Modules\Insumos\...`)

- [ ] **Step 4: Crear routes/v2/insumos.php con las 11 rutas**

```php
<?php

use App\Modules\Insumos\Http\Controllers\InsumosController;
use App\Modules\Insumos\Http\Controllers\LentesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,insumos.view'])->group(function (): void {
    Route::get('/insumos', [InsumosController::class, 'index']);
    Route::get('/insumos/list', [InsumosController::class, 'listar']);
    Route::post('/insumos/guardar', [InsumosController::class, 'guardar']);
    Route::get('/insumos/medicamentos', [InsumosController::class, 'medicamentos']);
    Route::get('/insumos/medicamentos/list', [InsumosController::class, 'listarMedicamentos']);
    Route::post('/insumos/medicamentos/guardar', [InsumosController::class, 'guardarMedicamento']);
    Route::post('/insumos/medicamentos/eliminar', [InsumosController::class, 'eliminarMedicamento']);
    Route::get('/insumos/lentes', [LentesController::class, 'index']);
    Route::get('/insumos/lentes/list', [LentesController::class, 'listar']);
    Route::post('/insumos/lentes/guardar', [LentesController::class, 'guardar']);
    Route::post('/insumos/lentes/eliminar', [LentesController::class, 'eliminar']);
});
```

- [ ] **Step 5: Agregar /insumos al bridge, eliminar, commit**

```bash
rm -rf modules/Insumos
git add ... && git commit -m "feat(onda3): migrate Insumos to Laravel, delete legacy ..."
```

---

## Task 6: KPI — portar dashboard de KPIs

**Legacy:** `modules/KPI/` — 8 archivos, 1.4k líneas
- `Controllers/KpiController.php`
- `Models/KpiDimensionModel.php`, `KpiSnapshot.php`, `KpiSnapshotModel.php`
- `Services/KpiCalculationService.php`, `KpiQueryService.php`
- `Support/KpiRegistry.php`
- `routes.php` → `GET /kpis`, `GET /kpis/{kpiKey}`

**Files a leer:**
```bash
cat modules/KPI/routes.php
cat modules/KPI/Controllers/KpiController.php
cat modules/KPI/Services/KpiCalculationService.php
cat modules/KPI/Services/KpiQueryService.php
cat modules/KPI/Support/KpiRegistry.php
```

**Files a crear:**
- `laravel-app/app/Modules/KPI/Http/Controllers/KpiController.php`
- `laravel-app/app/Modules/KPI/Services/KpiCalculationService.php`
- `laravel-app/app/Modules/KPI/Services/KpiQueryService.php`
- `laravel-app/app/Modules/KPI/Support/KpiRegistry.php`
- `laravel-app/routes/v2/kpi.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Verificar refs externas**

```bash
grep -r "Modules\\\\KPI\|KpiRegistry\|KpiCalculation" modules/ --include="*.php" -l | grep -v "modules/KPI/"
```

- [ ] **Step 3: Portar todos los servicios** (namespace `App\Modules\KPI\...`)

`KpiRegistry` probablemente tiene una lista hardcoded de KPIs disponibles — copiar tal cual. `KpiCalculationService` y `KpiQueryService` hacen queries SQL — usar `DB::select()` o Query Builder.

- [ ] **Step 4: Crear routes/v2/kpi.php**

```php
<?php

use App\Modules\KPI\Http\Controllers\KpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,kpis.view'])->group(function (): void {
    Route::get('/kpis', [KpiController::class, 'index']);
    Route::get('/kpis/{kpiKey}', [KpiController::class, 'show'])->where('kpiKey', '.+');
});
```

- [ ] **Step 5: Agregar /kpis al bridge, eliminar, commit**

```bash
rm -rf modules/KPI
git add ... && git commit -m "feat(onda3): migrate KPI to Laravel, delete legacy ..."
```

---

## Task 7: Doctores — portar listado y detalle de doctores

**Legacy:** `modules/Doctores/` — 5 archivos, 3.4k líneas
- `Controllers/DoctoresController.php`
- `Models/DoctorModel.php`
- `views/`: index.php, show.php
- `routes.php` → `GET /doctores`, `GET /doctores/{doctor}`

**Files a leer:**
```bash
cat modules/Doctores/routes.php
cat modules/Doctores/Controllers/DoctoresController.php
cat modules/Doctores/Models/DoctorModel.php
```

**Files a crear:**
- `laravel-app/app/Modules/Doctores/Http/Controllers/DoctoresController.php`
- `laravel-app/app/Modules/Doctores/Services/DoctoresService.php` (si hay lógica de negocio)
- `laravel-app/routes/v2/doctores.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Verificar refs externas**

```bash
grep -r "Modules\\\\Doctores\|DoctorModel\|DoctoresController" modules/ --include="*.php" -l | grep -v "modules/Doctores/"
grep -r "Modules\\\\Doctores\|DoctorModel" laravel-app/ --include="*.php" | head -10
```

Nota: `laravel-app/routes/console.php` tiene un Artisan command que hace SELECT con campos de doctores (`nombre`, `email`, etc.) directamente sobre la tabla — no usa DoctorModel. Verificar que esto no cambia.

- [ ] **Step 3: Portar DoctorModel y DoctoresController**

Si `DoctorModel` es un model PDO simple, convertirlo a Eloquent o Query Builder en el controller. `DoctoresController::index()` probablemente renderiza una vista — crear el equivalente Blade/Vite o devolver JSON si el frontend es Vue.

- [ ] **Step 4: Crear routes/v2/doctores.php**

```php
<?php

use App\Modules\Doctores\Http\Controllers\DoctoresController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,doctores.view'])->group(function (): void {
    Route::get('/doctores', [DoctoresController::class, 'index']);
    Route::get('/doctores/{doctor}', [DoctoresController::class, 'show'])->whereNumber('doctor');
});
```

- [ ] **Step 5: Agregar /doctores al bridge, eliminar, commit**

```bash
rm -rf modules/Doctores

git add public/index.php laravel-app/routes/v2/doctores.php laravel-app/routes/api.php
git add laravel-app/app/Modules/Doctores/
git add -A modules/Doctores
git commit -m "$(cat <<'EOF'
feat(onda3): migrate Doctores to Laravel, delete legacy

Ported GET /doctores and GET /doctores/{id} to app/Modules/Doctores.
Bridge now intercepts /doctores prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

| Módulo | Estado |
|--------|--------|
| AI | ✅ eliminado |
| Search | ✅ eliminado |
| CiveExtension | ✅ eliminado |
| MailTemplates | ✅ eliminado |
| Insumos | ✅ eliminado |
| KPI | ✅ eliminado |
| Doctores | ✅ eliminado |

- Módulos legacy restantes: ~18 → 11 (–7)
- Líneas eliminadas (acum.): ~21k
- Bridge ampliado con: `/ai`, `/search`, `/api/cive-extension`, `/mail-templates`, `/insumos`, `/kpis`, `/doctores`

**Siguiente:** Onda 4 → `docs/superpowers/plans/2026-05-21-onda4-medium-modules.md`
