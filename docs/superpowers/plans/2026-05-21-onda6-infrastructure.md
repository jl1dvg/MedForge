# Onda 6 — Infraestructura Compartida: Reporting (engine), Shared, Notifications, Core, Flujo

> **For agentic workers:** Use **superpowers:executing-plans** to run this plan task by task in the same session. Es la limpieza final — verificación + borrado.
>
> **Execution mode:** executing-plans (infraestructura, muere sola cuando sus consumidores desaparecen)
>
> **Master roadmap:** `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`

**Goal:** Eliminar los últimos 5 módulos de infraestructura compartida y limpiar el bootstrap/autoloader. Con Ondas 1–5 completadas, ninguno de estos módulos tiene consumers activos — verificar con grep y borrar.

**Prerequisitos:** Ondas 1–5 completadas. Todos los módulos de negocio eliminados.

**Módulos a eliminar:**
| Módulo | Archivos | Líneas | Deps entrantes según roadmap |
|--------|----------|--------|------------------------------|
| Reporting (engine) | 49 | 20k | 3 (Billing + Cirugias ya migrados) |
| Shared | 2 | 729 | 3 |
| Notifications | 1 | 262 | 5 |
| Core | 2 | 85 | — |
| Flujo | 2 | 222 | 0 |

**Orden:** Flujo y Core primero (sin deps), luego Shared y Notifications, luego Reporting engine (el más grande — verificar que Billing y Cirugias ya no lo usan).

---

## Task 1: Verificación completa de dependencias antes de borrar

Antes de borrar cualquier cosa, verificar que NADA referencia estos módulos desde el código activo.

- [ ] **Step 1: Verificar estado de modules/ — deben quedar solo los de Onda 6**

```bash
ls modules/
```
Expected: solo `Reporting/`, `Shared/`, `Notifications/`, `Core/`, `Flujo/` (y `Consulta/` si aún existe — verificar).

Si aparece algún módulo que debería haber sido eliminado en Ondas anteriores, DETENER y resolverlo primero.

- [ ] **Step 2: Grep de Reporting engine**

```bash
# ¿Algo en Laravel usa el motor de Reporting?
grep -r "Modules\\\\Reporting\|ReportService\|LegacyLoader\|reporting_bootstrap_legacy" laravel-app/ --include="*.php" | grep -v ".git"
# ¿Algo en modules/ restantes lo usa?
grep -r "Modules\\\\Reporting\|ReportService" modules/ --include="*.php" | grep -v "modules/Reporting/"
# ¿Algo en controllers/ raíz lo usa?
grep -r "Modules\\\\Reporting\|ReportService" controllers/ --include="*.php" 2>/dev/null
```
Expected: cero resultados. Si hay algo, leerlo y resolverlo antes de continuar.

- [ ] **Step 3: Grep de Shared**

```bash
grep -r "Modules\\\\Shared" modules/ --include="*.php" | grep -v "modules/Shared/"
grep -r "Modules\\\\Shared" laravel-app/ --include="*.php"
grep -r "Modules\\\\Shared" controllers/ --include="*.php" 2>/dev/null
```

- [ ] **Step 4: Grep de Notifications**

```bash
grep -r "Modules\\\\Notifications" modules/ --include="*.php" | grep -v "modules/Notifications/"
grep -r "Modules\\\\Notifications" laravel-app/ --include="*.php"
grep -r "Modules\\\\Notifications" controllers/ --include="*.php" 2>/dev/null
```

- [ ] **Step 5: Grep de Core (módulo legacy, no el Core/ del bootstrap)**

```bash
find modules/Core -name "*.php" | sort
cat modules/Core/*.php
# ¿Qué exporta Core?
grep -r "Modules\\\\Core" modules/ --include="*.php" | grep -v "modules/Core/"
grep -r "Modules\\\\Core" laravel-app/ --include="*.php"
```

Nota: el directorio `Core/` del módulo NO es `Core\Router`, `Core\ModuleLoader`, etc. — esos son del bootstrap en `core/`. Son distintos. Verificar.

- [ ] **Step 6: Grep de Flujo**

```bash
find modules/Flujo -name "*.php" | sort
grep -r "Modules\\\\Flujo" modules/ --include="*.php" | grep -v "modules/Flujo/"
grep -r "Modules\\\\Flujo" laravel-app/ --include="*.php"
```

- [ ] **Step 7: Verificar bootstrap.php — módulos registrados explícitamente**

```bash
cat bootstrap.php | grep -i "reporting\|shared\|notifications\|flujo"
```
Algunos bootstraps registran módulos explícitamente (no solo via ModuleLoader). Identificar si hay entradas que deban eliminarse.

- [ ] **Step 8: Verificar public/index.php — referencias residuales**

```bash
grep -i "reporting\|shared\|notifications\|core\|flujo" public/index.php
```
Expected: solo el bridge array (que no menciona estos módulos). Si hay referencias hardcodeadas, resolver.

---

## Task 2: Eliminar Flujo y Core

Módulos más pequeños (2 archivos cada uno), sin dependencias entrantes.

- [ ] **Step 1: Leer contenido de Flujo**

```bash
find modules/Flujo -name "*.php" | sort
cat modules/Flujo/*.php
```

- [ ] **Step 2: Confirmar cero refs (ya verificado en Task 1)**

Si el grep del Task 1 Step 6 dio cero, proceder.

- [ ] **Step 3: Eliminar Flujo**

```bash
rm -rf modules/Flujo
```

- [ ] **Step 4: Leer contenido de Core (módulo)**

```bash
find modules/Core -name "*.php" | sort
cat modules/Core/*.php
```

Asegurarse de que NO borrar `core/` (bootstrap) — solo `modules/Core/`.

- [ ] **Step 5: Eliminar Core (módulo)**

```bash
rm -rf modules/Core
```

- [ ] **Step 6: Smoke test**

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

- [ ] **Step 7: Commit**

```bash
git add -A modules/Flujo modules/Core
git commit -m "$(cat <<'EOF'
feat(onda6): delete Flujo and Core legacy modules — zero consumers

Both modules had no active routes and zero incoming cross-module references.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Eliminar Notifications

- [ ] **Step 1: Leer Notifications**

```bash
find modules/Notifications -name "*.php" | sort
cat modules/Notifications/*.php
```

- [ ] **Step 2: Confirmar cero refs (verificado en Task 1 Step 4)**

Si hay refs residuales, trazar a qué módulo las tiene y verificar que ese módulo fue eliminado en Ondas anteriores (o que la referencia es en código muerto — grep para confirmarlo).

- [ ] **Step 3: Eliminar**

```bash
rm -rf modules/Notifications
```

- [ ] **Step 4: Smoke test + commit**

```bash
php -r "
define('BASE_PATH', __DIR__); define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"

git add -A modules/Notifications
git commit -m "$(cat <<'EOF'
feat(onda6): delete Notifications legacy module — zero consumers

All 5 incoming references were from modules deleted in Ondas 1-5.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Eliminar Shared

- [ ] **Step 1: Leer Shared**

```bash
find modules/Shared -name "*.php" | sort
cat modules/Shared/*.php
```

`Shared` probablemente contiene helpers, traits o constantes usadas transversalmente. Con todos los módulos de negocio ya eliminados, debe tener cero consumers.

- [ ] **Step 2: Confirmar cero refs**

```bash
grep -r "Modules\\\\Shared" . --include="*.php" | grep -v ".git" | grep -v "modules/Shared/"
```

- [ ] **Step 3: Verificar que Laravel no importa nada de Shared vía path hardcodeado**

```bash
grep -r "modules/Shared\|Modules\\\\Shared" laravel-app/ --include="*.php"
grep -r "modules/Shared" laravel-app/routes/console.php
```

- [ ] **Step 4: Eliminar**

```bash
rm -rf modules/Shared
```

- [ ] **Step 5: Smoke test + commit**

```bash
php -r "
define('BASE_PATH', __DIR__); define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"

git add -A modules/Shared
git commit -m "$(cat <<'EOF'
feat(onda6): delete Shared legacy module — zero consumers

All 3 incoming references were from modules deleted in previous ondas.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Eliminar Reporting engine (el mayor)

El motor de Reporting (49 archivos, 20k líneas) es el más complejo. Sus consumidores (Billing, Cirugias) ya migraron en Ondas 4–5. El `routes.php` fue eliminado en Onda 2.

- [ ] **Step 1: Verificar estructura del motor**

```bash
find modules/Reporting -name "*.php" | sort
ls modules/Reporting/
```

- [ ] **Step 2: Confirmar cero refs desde Laravel**

```bash
grep -r "Modules\\\\Reporting\|ReportService\|reporting_bootstrap_legacy\|LegacyLoader" laravel-app/ --include="*.php"
grep -r "modules/Reporting" laravel-app/ --include="*.php"
```
Expected: cero resultados.

- [ ] **Step 3: Confirmar cero refs desde el resto del sistema**

```bash
grep -r "Modules\\\\Reporting\|ReportService\|reporting_bootstrap" . --include="*.php" | grep -v ".git" | grep -v "modules/Reporting/"
```
Expected: cero resultados.

- [ ] **Step 4: Confirmar que /reports está en el bridge y las rutas existen en Laravel**

```bash
grep "reports" public/index.php
cd laravel-app && php artisan route:list | grep "/reports"
```
Expected: `/reports` en el bridge, múltiples rutas Laravel activas.

- [ ] **Step 5: Eliminar Reporting engine**

```bash
rm -rf modules/Reporting
```

- [ ] **Step 6: Smoke test final**

```bash
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

- [ ] **Step 7: Commit**

```bash
git add -A modules/Reporting
git commit -m "$(cat <<'EOF'
feat(onda6): delete Reporting engine — last legacy module eliminated

Billing and Cirugias (migrated in Ondas 4-5) were the only consumers.
Laravel covers all /reports/* routes. Zero legacy code remains in modules/.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Limpieza final del bootstrap y public/index.php

Con `modules/` vacío, eliminar código que ya no tiene propósito.

- [ ] **Step 1: Verificar que modules/ está vacío**

```bash
ls modules/
```
Expected: directorio vacío (o solo `.gitkeep`).

- [ ] **Step 2: Limpiar public/index.php**

Con todos los módulos migrados, el bloque de código legacy en `public/index.php` puede simplificarse. El archivo actualmente tiene:
1. Bridge → sigue siendo necesario (mantener)
2. `require_once bootstrap.php` → puede eliminarse si ya no hay rutas legacy
3. `ModuleLoader::register(...)` → puede eliminarse (no hay módulos que cargar)
4. `$router->dispatch(...)` → puede eliminarse
5. Rutas hardcodeadas residuales de Billing → ya eliminadas en Onda 5
6. La ruta `/reportes/estadistica_flujo` → verificar si fue portada a Laravel

```bash
# Verificar ruta de estadistica_flujo:
grep "estadistica_flujo" public/index.php
grep "estadistica_flujo" laravel-app/routes/ -r --include="*.php"
```

Si `GET /reportes/estadistica_flujo` existe en Laravel, eliminar el bloque de `public/index.php`. Si no, portarla antes.

- [ ] **Step 3: Simplificar public/index.php**

Después de la limpieza, `public/index.php` debe quedar solo con el bridge. Algo así:

```php
<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = '/public/index.php';
if (strncmp($requestPath, $basePath, strlen($basePath)) === 0) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$laravelBridgeExact = ['/auth/login', '/auth/logout', '/whatsapp/webhook', '/solicitudes/guardar.php', '/api/solicitudes/guardar.php'];
$laravelBridgePrefixes = [
    '/v2', '/usuarios', '/roles', '/feedback', '/protocolos',
    '/examenes', '/imagenes', '/agenda', '/derivaciones', '/reports',
    '/mailbox', '/mail', '/ai', '/search', '/api/cive-extension',
    '/mail-templates', '/insumos', '/kpis', '/doctores',
    '/pacientes', '/cirugias', '/cron-manager', '/crm', '/billing',
    '/whatsapp', '/autoresponder', '/reportes',
];

if (in_array($requestPath, $laravelBridgeExact, true)) {
    require __DIR__ . '/v2_kernel.php';
    exit;
}

foreach ($laravelBridgePrefixes as $prefix) {
    if ($requestPath === $prefix || strncmp($requestPath, $prefix . '/', strlen($prefix) + 1) === 0) {
        require __DIR__ . '/v2_kernel.php';
        exit;
    }
}

// Si llega aquí, no hay ruta que coincida
http_response_code(404);
echo 'Ruta no encontrada: ' . htmlspecialchars($requestPath, ENT_QUOTES, 'UTF-8');
```

- [ ] **Step 4: Verificar que no hay imports de módulos legacy en bootstrap.php**

```bash
grep -n "modules\/" bootstrap.php | grep -v "ModuleLoader"
grep -n "Modules\\\\" bootstrap.php
```

- [ ] **Step 5: Test de integración final**

```bash
# El router legacy ya no tiene módulos — el bootstrap debería cargar sin errores
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
echo 'Bootstrap OK' . PHP_EOL;
"

# Tests Laravel completos
cd laravel-app && php artisan test 2>&1 | tail -30
```

- [ ] **Step 6: Verificar modules/ vacío definitivamente**

```bash
find modules/ -name "*.php" | wc -l
```
Expected: `0`

- [ ] **Step 7: Commit final**

```bash
git add public/index.php bootstrap.php
git add -A modules/
git commit -m "$(cat <<'EOF'
feat(onda6): legacy-zero achieved — clean up bootstrap and public/index.php

modules/ is now empty. public/index.php simplified to bridge-only.
All PHP legacy code migrated to Laravel 12.

Total: 26 modules, ~100k lines eliminated over 6 waves.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado final — Legacy-Zero

| Onda | Módulos eliminados | Líneas eliminadas (acum.) |
|------|--------------------|--------------------------|
| 1 | Usuarios, EditorProtocolos | ~4.4k |
| 2 | Agenda, Derivaciones, Reporting/routes.php, Mail | ~13k |
| 3 | AI, Search, CiveExtension, MailTemplates, Insumos, KPI, Doctores | ~21k |
| 4 | IdentityVerification, CronManager, Cirugias, Pacientes | ~38k |
| 5 | CRM, Billing, WhatsApp, Autoresponder | ~81k |
| 6 | Reporting engine, Shared, Notifications, Core, Flujo | **~100k → 0** |

**MedForge es ahora 100% Laravel 12. 🎉**
