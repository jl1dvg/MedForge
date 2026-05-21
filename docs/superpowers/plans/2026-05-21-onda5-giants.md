# Onda 5 — Gigantes: CRM, Billing, WhatsApp, Autoresponder

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development** to execute this plan. One session per module. **Orden obligatorio por dependencias.**
>
> **Execution mode:** subagent-driven, sesiones independientes (una sesión por módulo)
>
> **Master roadmap:** `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`

**Goal:** Migrar los 4 módulos más grandes del sistema. CRM y Billing pueden trabajarse concurrentemente pero Billing depende de CRM para algunos datos. WhatsApp va después (18 deps entrantes, la mayoría ya resueltas por Ondas 1–4). Autoresponder va de último (4 deps: WhatsApp + otros ya migrados).

**Orden de ejecución:**
1. **CRM** (31 archivos, 9.4k líneas, 20 v2 routes existentes)
2. **Billing** (37 archivos, 13k líneas, 52 v2 routes existentes) — puede iniciarse en paralelo con CRM
3. **WhatsApp** (29 archivos, 15k líneas, 60 v2 routes existentes) — después de CRM+Billing
4. **Autoresponder** (11 archivos, 5.3k líneas, 0 v2 routes) — después de WhatsApp

**Prerequisitos:** Ondas 1–4 completadas.

**Estrategia para cada módulo:**
1. Leer la lista completa de rutas legacy y v2 existentes
2. Identificar el gap (rutas/lógica sin parity)
3. Portar el gap
4. Verificar que todos los servicios que otros módulos usaban están en Laravel
5. Agregar prefijo al bridge
6. Grep cero refs externas
7. `rm -rf modules/<Modulo>/`

---

## Task 1: CRM — completar parity del módulo CRM

**Legacy:** `modules/CRM/` — 31 archivos, 9.4k líneas, 31 rutas activas
**Laravel v2:** `laravel-app/routes/v2/crm.php` — ~20 rutas

**Inicio de sesión — leer el estado actual:**
```bash
find modules/CRM -name "*.php" | sort
cat modules/CRM/routes.php
cat laravel-app/routes/v2/crm.php
find laravel-app/app/Modules/CRM -name "*.php" | sort 2>/dev/null || echo "No CRM module in Laravel yet"
```

**Análisis de gap:**
```bash
# Rutas legacy que faltan en v2:
grep "router->" modules/CRM/routes.php
grep "Route::" laravel-app/routes/v2/crm.php
```

**Proceso completo:**

- [ ] **Step 1: Auditoría completa**
  - Listar todas las rutas legacy
  - Listar todas las rutas v2
  - Identificar gap: rutas legacy sin equivalente v2

- [ ] **Step 2: Leer controladores y servicios legacy**

```bash
for f in $(find modules/CRM -name "*.php" | sort); do
  echo "=== $f ==="; head -30 "$f"; echo ""
done
```

- [ ] **Step 3: Leer módulo CRM en Laravel**

```bash
find laravel-app/app/Modules/CRM -name "*.php" -exec grep -l "" {} \; | sort
```

- [ ] **Step 4: Verificar dependencias entrantes**

```bash
grep -r "Modules\\\\CRM" modules/ --include="*.php" -l | grep -v "modules/CRM/"
```
Expected: 1 referencia entrante (según roadmap). Si es desde WhatsApp o Autoresponder, documentar para resolverlo cuando esos migren.

- [ ] **Step 5: Portar gap de rutas/lógica**

Para cada ruta sin parity:
- Crear método en el controlador Laravel correspondiente
- Agregar ruta en `laravel-app/routes/v2/crm.php`
- Si la lógica es compleja, crear un servicio dedicado

- [ ] **Step 6: Portar servicios referenciados desde otros módulos**

```bash
grep -r "use Modules\\\\CRM" modules/ --include="*.php" | grep -v "modules/CRM/"
```
Los servicios que WhatsApp o Autoresponder usan deben estar en Laravel antes de que esos módulos migren. Portarlos ahora.

- [ ] **Step 7: Verificar cobertura de permisos**

Las rutas CRM legacy tienen lógica de permisos (`crm.view`, `crm.manage`, etc.). Verificar que los middlewares en v2 tienen los mismos permisos.

- [ ] **Step 8: Tests**

```bash
cd laravel-app && php artisan test --filter=CRM
```

- [ ] **Step 9: Agregar /crm al bridge**

```php
$laravelBridgePrefixes = [..., '/crm'];
```

- [ ] **Step 10: Grep cero refs externas**

```bash
grep -r "Modules\\\\CRM" modules/ --include="*.php" | grep -v "modules/CRM/"
```

- [ ] **Step 11: Eliminar módulo CRM**

```bash
rm -rf modules/CRM

php -r "
define('BASE_PATH', __DIR__); define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"
```

- [ ] **Step 12: Commit**

```bash
git add public/index.php laravel-app/routes/v2/crm.php
git add laravel-app/app/Modules/CRM/
git add -A modules/CRM
git commit -m "$(cat <<'EOF'
feat(onda5): delete CRM legacy — complete parity in Laravel, add bridge

Ported remaining N routes and services. Bridge now intercepts /crm prefix.
31 legacy files deleted.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Billing — completar parity del módulo de facturación

**Legacy:** `modules/Billing/` — 37 archivos, 13k líneas, 27 rutas activas
**Laravel v2:** `laravel-app/routes/v2/billing.php` — ~52 rutas

Nota: `public/index.php` tiene 2 rutas legacy hardcodeadas para Billing fuera del ModuleLoader:
- `GET /billing/excel` → `BillingController::generarExcel()`
- `GET /billing/exportar_mes` → `BillingController::exportarPlanillasPorMes()`

Estas también deben portarse a Laravel y luego eliminarse de `public/index.php`.

**Inicio de sesión — leer el estado actual:**
```bash
find modules/Billing -name "*.php" | sort
cat modules/Billing/routes.php
cat laravel-app/routes/v2/billing.php
find laravel-app/app/Modules/Billing -name "*.php" | sort 2>/dev/null
# Rutas legacy hardcodeadas en index.php:
grep -n "billing" public/index.php
```

**Proceso completo:**

- [ ] **Step 1: Auditoría de rutas** (igual que CRM Task 1)

Incluir las 2 rutas hardcodeadas en `public/index.php` en el inventario.

- [ ] **Step 2: Leer controladores y servicios legacy**

```bash
for f in $(find modules/Billing -name "*.php" | sort); do
  echo "=== $f ==="; cat "$f"; echo ""
done 2>&1 | head -500
```

- [ ] **Step 3: Verificar dependencias entrantes**

```bash
grep -r "Modules\\\\Billing\|BillingController" modules/ --include="*.php" -l | grep -v "modules/Billing/"
grep -r "Modules\\\\Billing\|BillingController" public/ --include="*.php"
```

- [ ] **Step 4: Portar gap de rutas/lógica incluyendo generarExcel y exportarPlanillasPorMes**

Las rutas de Excel probablemente usan PhpSpreadsheet — verificar si ya está en composer.json de Laravel:
```bash
grep "phpspreadsheet\|PhpOffice" laravel-app/composer.json
```

- [ ] **Step 5: Eliminar las rutas hardcodeadas de public/index.php**

Una vez que `GET /billing/excel` y `GET /billing/exportar_mes` estén en Laravel, eliminar los bloques correspondientes de `public/index.php` (líneas ~101–145).

- [ ] **Step 6: Tests + bridge + eliminar**

```bash
cd laravel-app && php artisan test --filter=Billing

# Agregar /billing al bridge:
# $laravelBridgePrefixes = [..., '/billing'];

rm -rf modules/Billing

git add public/index.php laravel-app/routes/v2/billing.php
git add laravel-app/app/Modules/Billing/
git add -A modules/Billing
git commit -m "$(cat <<'EOF'
feat(onda5): delete Billing legacy — complete parity in Laravel, add bridge

Ported remaining routes including /billing/excel and /billing/exportar_mes.
Removed hardcoded Billing routes from public/index.php.
Bridge now intercepts /billing prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: WhatsApp — completar parity del módulo de mensajería

**Legacy:** `modules/WhatsApp/` — 29 archivos, 15k líneas, 24 rutas activas
**Laravel v2:** `laravel-app/routes/v2/whatsapp.php` — ~60 rutas

**Contexto especial:**
- WhatsApp tiene 18 dependencias entrantes (el módulo más dependido del sistema)
- Los tests `WhatsappCampaignsTest` y `WhatsappWebhookControllerTest` tienen 29 fallos pre-existentes (confirmados antes de esta migración — no bloquean)
- `POST /whatsapp/webhook` ya está en `$laravelBridgeExact` en `public/index.php`

**Inicio de sesión:**
```bash
find modules/WhatsApp -name "*.php" | sort
cat modules/WhatsApp/routes.php
cat laravel-app/routes/v2/whatsapp.php
find laravel-app/app/Modules/Whatsapp -name "*.php" | sort 2>/dev/null
```

**Proceso completo:**

- [ ] **Step 1: Auditoría de rutas**

```bash
grep "router->" modules/WhatsApp/routes.php | wc -l
grep "Route::" laravel-app/routes/v2/whatsapp.php | wc -l
```

Mapear cada ruta legacy a su equivalente v2. Con 60 rutas en v2 vs 24 legacy, la mayoría ya está portada.

- [ ] **Step 2: Resolver deps entrantes que quedan**

```bash
grep -r "Modules\\\\WhatsApp\|WhatsApp\\" modules/ --include="*.php" -l | grep -v "modules/WhatsApp/"
```

Con Ondas 1–4 completadas, quedan ~4 referencias (Autoresponder principalmente). Documentar las que vienen de Autoresponder — se resolverán en Task 4.

- [ ] **Step 3: Portar lógica faltante**

- [ ] **Step 4: Verificar webhook en bridge**

```bash
grep "whatsapp" public/index.php
```
`POST /whatsapp/webhook` ya está en `$laravelBridgeExact`. Para el resto de rutas `/whatsapp/*`, agregar `/whatsapp` a `$laravelBridgePrefixes`.

- [ ] **Step 5: Tests**

```bash
cd laravel-app && php artisan test --filter=Whatsapp 2>&1 | tail -20
```
Los 29 fallos pre-existentes son aceptables. Verificar que no haya nuevos fallos.

- [ ] **Step 6: Bridge + eliminar + commit**

```php
$laravelBridgePrefixes = [..., '/whatsapp'];
```

```bash
rm -rf modules/WhatsApp

git add public/index.php laravel-app/routes/v2/whatsapp.php
git add laravel-app/app/Modules/Whatsapp/
git add -A modules/WhatsApp
git commit -m "$(cat <<'EOF'
feat(onda5): delete WhatsApp legacy — complete parity in Laravel, add bridge

Ported remaining routes. Bridge now intercepts /whatsapp prefix
(webhook already in bridge exact list). 29 tests remain pre-existing failures.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Autoresponder — portar de cero (0 rutas v2)

**Legacy:** `modules/Autoresponder/` — 11 archivos, 5.3k líneas, 5 rutas activas
**Laravel v2:** cero rutas existentes

**Dependencias de Autoresponder (4):**
- `Modules\WhatsApp\Config\WhatsAppSettings` — usar versión Laravel
- `Modules\Autoresponder\Repositories\AutoresponderFlowRepository` — portar
- `Modules\WhatsApp\Repositories\InboxRepository` — usar versión Laravel
- `Modules\WhatsApp\Support\AutoresponderFlow` — usar versión Laravel
- `Modules\WhatsApp\Services\TemplateManager` — usar versión Laravel
- `Models\RolModel` — ya en `models/RolModel.php` (portado en Onda 1)

**Inicio de sesión:**
```bash
find modules/Autoresponder -name "*.php" | sort
cat modules/Autoresponder/routes.php
for f in $(find modules/Autoresponder -name "*.php"); do
  echo "=== $f ==="; cat "$f"; done
```

- [ ] **Step 1: Leer todas las rutas legacy**

```bash
cat modules/Autoresponder/routes.php
```

- [ ] **Step 2: Verificar clases WhatsApp en Laravel**

```bash
find laravel-app/app/Modules/Whatsapp -name "WhatsAppSettings*" -o -name "InboxRepository*" -o -name "AutoresponderFlow*" -o -name "TemplateManager*"
```

Con WhatsApp ya migrado (Task 3), todas estas clases deben existir en `laravel-app/app/Modules/Whatsapp/`. Documentar sus namespaces exactos para usarlos en Autoresponder.

- [ ] **Step 3: Crear estructura Laravel**

```bash
mkdir -p laravel-app/app/Modules/Autoresponder/Http/Controllers
mkdir -p laravel-app/app/Modules/Autoresponder/Repositories
```

- [ ] **Step 4: Portar AutoresponderFlowRepository**

Crear `laravel-app/app/Modules/Autoresponder/Repositories/AutoresponderFlowRepository.php`. Usar `DB::table()` de Laravel en lugar de PDO directo.

- [ ] **Step 5: Portar AutoresponderController**

Crear `laravel-app/app/Modules/Autoresponder/Http/Controllers/AutoresponderController.php`. Reemplazar:
- `use Models\RolModel` → `use App\Modules\Usuarios\...\RolModel` o `DB::table('roles')` directo
- `use Modules\WhatsApp\...` → `use App\Modules\Whatsapp\...` (namespaces Laravel)

- [ ] **Step 6: Crear routes/v2/autoresponder.php**

```php
<?php

use App\Modules\Autoresponder\Http\Controllers\AutoresponderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,autoresponder.manage'])->group(function (): void {
    // Mapear rutas desde modules/Autoresponder/routes.php
    // (leer el routes.php legacy para obtener los paths exactos)
});
```

- [ ] **Step 7: Registrar en routes/api.php**

```php
require __DIR__ . '/v2/autoresponder.php';
```

- [ ] **Step 8: Agregar /autoresponder al bridge**

- [ ] **Step 9: Tests**

```bash
cd laravel-app && php artisan test --filter=Autoresponder 2>/dev/null || echo "No Autoresponder tests"
```

- [ ] **Step 10: Verificar cero refs externas**

```bash
grep -r "Modules\\\\Autoresponder" modules/ --include="*.php" | grep -v "modules/Autoresponder/"
```

- [ ] **Step 11: Eliminar Autoresponder + commit**

```bash
rm -rf modules/Autoresponder

git add public/index.php laravel-app/routes/v2/autoresponder.php laravel-app/routes/api.php
git add laravel-app/app/Modules/Autoresponder/
git add -A modules/Autoresponder
git commit -m "$(cat <<'EOF'
feat(onda5): migrate Autoresponder to Laravel from scratch, delete legacy

Ported AutoresponderController and AutoresponderFlowRepository.
All WhatsApp dependencies now point to app/Modules/Whatsapp.
Bridge now intercepts /autoresponder prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

| Módulo | Estado |
|--------|--------|
| CRM | ✅ eliminado |
| Billing | ✅ eliminado |
| WhatsApp | ✅ eliminado |
| Autoresponder | ✅ eliminado |

- Módulos legacy restantes: 7 → 5 (solo infraestructura compartida)
- `public/index.php`: hardcoded Billing routes eliminadas, bridge limpio
- Líneas eliminadas (acum.): ~81k

**Siguiente:** Onda 6 → `docs/superpowers/plans/2026-05-21-onda6-infrastructure.md`
