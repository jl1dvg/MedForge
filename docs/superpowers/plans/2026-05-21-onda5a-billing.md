# Onda 5-A — Billing: Cerrar Gap, Bridge, Delete

> **For agentic workers:** Use **superpowers:executing-plans** para ejecutar este plan tarea a tarea en la misma sesión.
>
> **Execution mode:** executing-plans (~0.5 días, gap pequeño, principalmente verificación)
>
> **Parte de:** Onda 5 fragmentada — ver `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`
>
> **Prerequisito:** Ondas 1–4 completas.

**Goal:** Cerrar el gap de 6 rutas legacy de Billing sin equivalente v2, portar 2 rutas hardcodeadas de `public/index.php`, agregar `/billing` e `/informes` al bridge, y eliminar `modules/Billing/`.

**Architecture:** Billing en Laravel ya tiene `BillingUiController`, `BillingReadController` y `BillingWriteController` cubriendo 52 rutas. El gap son rutas de informes AJAX (`/informes/api/*`), un export de lote IESS, y 2 exports hardcodeados en `public/index.php`. Todas van al `BillingUiController` o `BillingWriteController` existentes.

**Tech Stack:** Laravel 12, `laravel-app/routes/v2/billing.php`, `laravel-app/app/Modules/Billing/Http/Controllers/`, `public/index.php`.

**Gap identificado:**

| Ruta legacy | Estado | Acción |
|------------|--------|--------|
| `GET /billing/excel` | Hardcoded en `public/index.php` | Portar a Laravel |
| `GET /billing/exportar_mes` | Hardcoded en `public/index.php` | Portar a Laravel |
| `GET/POST /informes/api/detalle-factura` | Falta en v2 | Portar |
| `POST /informes/api/scrapear-codigo` | Falta en v2 | Portar |
| `GET /informes/iess/excel-lote` | Falta en v2 | Portar |
| `GET/POST /informes/iess/prueba` | Endpoint de prueba/dev | Verificar y omitir |
| `GET /views/billing/no_facturados.php` | Redirect a `/billing/no-facturados` | Omitir (URL legada muerta) |
| `POST /views/billing/components/crear_desde_no_facturado.php` | Form POST legacy | Verificar si tiene tráfico activo |

---

## Task 1: Leer código de las rutas faltantes

**Files:**
- Read: `modules/Billing/routes.php`
- Read: `modules/Billing/Controllers/InformesController.php`
- Read: `modules/Billing/Controllers/BillingController.php`
- Read: `laravel-app/app/Modules/Billing/Http/Controllers/BillingUiController.php`
- Read: `laravel-app/app/Modules/Billing/Http/Controllers/BillingWriteController.php`

- [ ] **Step 1: Leer InformesController completo**

```bash
cat modules/Billing/Controllers/InformesController.php
```

Buscar los métodos: `ajaxDetalleFactura()`, `ajaxScrapearCodigoDerivacion()`, `generarExcelIessLote()`, `informeIessPrueba()`.

- [ ] **Step 2: Leer BillingController (métodos de export)**

```bash
grep -A 40 "function generarExcel\b" modules/Billing/Controllers/BillingController.php
grep -A 40 "function exportarPlanillasPorMes" modules/Billing/Controllers/BillingController.php
```

- [ ] **Step 3: Verificar si `/informes/iess/prueba` tiene tráfico real**

```bash
# Buscar referencias en frontend JS/PHP
grep -r "iess/prueba" laravel-app/resources/ --include="*.js" --include="*.vue" --include="*.php" 2>/dev/null
grep -r "iess/prueba" modules/ --include="*.php" 2>/dev/null
```

Si cero referencias → es endpoint de desarrollo, se omite (no se porta a Laravel).

- [ ] **Step 4: Verificar si `/views/billing/components/crear_desde_no_facturado.php` tiene tráfico**

```bash
grep -r "crear_desde_no_facturado" laravel-app/resources/ --include="*.js" --include="*.vue" 2>/dev/null
grep -r "crear_desde_no_facturado" modules/ --include="*.php" 2>/dev/null
```

Si cero referencias → URL legada muerta (el form ahora usa `/billing/no-facturados/crear` en v2).

---

## Task 2: Portar /informes/api/detalle-factura y /informes/api/scrapear-codigo

**Files:**
- Modify: `laravel-app/app/Modules/Billing/Http/Controllers/BillingWriteController.php` (o `BillingReadController` según la lógica)
- Modify: `laravel-app/routes/v2/billing.php`

- [ ] **Step 1: Agregar métodos al controller Laravel apropiado**

Leer la lógica de `InformesController::ajaxDetalleFactura()` (es GET/POST, devuelve JSON con detalle de factura) y `ajaxScrapearCodigoDerivacion()` (POST, llama scraper). Agregar al controller Laravel que más corresponda:

En `BillingWriteController` para scrapear, en `BillingReadController` para detalle:

```php
// En BillingReadController:
public function detalleFactura(Request $request): JsonResponse
{
    // Copiar lógica de InformesController::ajaxDetalleFactura()
    // Usa PDO o DB::select() según el patrón del controller existente
}

// En BillingWriteController:
public function scrapearCodigo(Request $request): JsonResponse
{
    // Copiar lógica de InformesController::ajaxScrapearCodigoDerivacion()
}
```

- [ ] **Step 2: Registrar rutas en v2/billing.php**

Dentro del grupo de middleware `app.permission:administrativo,billing.iess.view,billing.manage`:

```php
Route::match(['GET', 'POST'], '/informes/api/detalle-factura', [BillingReadController::class, 'detalleFactura']);
Route::post('/informes/api/scrapear-codigo', [BillingWriteController::class, 'scrapearCodigo']);
```

- [ ] **Step 3: Verificar rutas registradas**

```bash
cd laravel-app && php artisan route:list | grep "informes/api"
```

Expected: aparecen `informes/api/detalle-factura` y `informes/api/scrapear-codigo`.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Billing/Http/Controllers/BillingReadController.php
git add laravel-app/app/Modules/Billing/Http/Controllers/BillingWriteController.php
git add laravel-app/routes/v2/billing.php
git commit -m "$(cat <<'EOF'
feat(onda5a): port /informes/api/detalle-factura and scrapear-codigo to Laravel

These AJAX endpoints were missing from v2/billing.php. Added methods to
BillingReadController and BillingWriteController.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Portar /informes/iess/excel-lote

**Files:**
- Modify: `laravel-app/app/Modules/Billing/Http/Controllers/BillingUiController.php`
- Modify: `laravel-app/routes/v2/billing.php`

- [ ] **Step 1: Leer lógica de generarExcelIessLote()**

```bash
grep -A 50 "function generarExcelIessLote" modules/Billing/Controllers/InformesController.php
```

Identificar: ¿usa PhpSpreadsheet? ¿devuelve un archivo descargable?

- [ ] **Step 2: Verificar que PhpSpreadsheet está disponible en Laravel**

```bash
grep "phpspreadsheet\|PhpOffice" laravel-app/composer.json
```

Si no está, agregarlo:
```bash
cd laravel-app && composer require phpoffice/phpspreadsheet
```

- [ ] **Step 3: Agregar método informeIessExcelLote() a BillingUiController**

```php
public function informeIessExcelLote(Request $request): Response|RedirectResponse
{
    // Copiar lógica de InformesController::generarExcelIessLote()
    // Adaptar acceso a DB: usar DB::select() en lugar de $pdo->query()
    // Mantener la misma respuesta de descarga de archivo
}
```

- [ ] **Step 4: Registrar ruta**

```php
// En el grupo billing.iess.view:
Route::get('/informes/iess/excel-lote', [BillingUiController::class, 'informeIessExcelLote']);
```

- [ ] **Step 5: Verificar**

```bash
cd laravel-app && php artisan route:list | grep "excel-lote"
```

- [ ] **Step 6: Commit**

```bash
git add laravel-app/app/Modules/Billing/Http/Controllers/BillingUiController.php
git add laravel-app/routes/v2/billing.php
git commit -m "$(cat <<'EOF'
feat(onda5a): port /informes/iess/excel-lote to Laravel BillingUiController

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Portar /billing/excel y /billing/exportar_mes (hardcoded en public/index.php)

**Files:**
- Modify: `laravel-app/app/Modules/Billing/Http/Controllers/BillingUiController.php`
- Modify: `laravel-app/routes/v2/billing.php`
- Modify: `public/index.php` — eliminar los 2 bloques hardcodeados

- [ ] **Step 1: Leer los métodos legacy**

```bash
grep -A 40 "function generarExcel\b" modules/Billing/Controllers/BillingController.php
grep -A 40 "function exportarPlanillasPorMes" modules/Billing/Controllers/BillingController.php
```

- [ ] **Step 2: Leer los bloques en public/index.php**

```bash
grep -n "billing/excel\|billing/exportar_mes" public/index.php
```

Ver exactamente líneas `~101–145` de `public/index.php` para los 2 bloques if.

- [ ] **Step 3: Agregar métodos a BillingUiController**

```php
public function generarExcel(Request $request): Response
{
    $this->authorize(['administrativo', 'billing.export', 'billing.manage']);
    $formId = $request->query('form_id');
    $grupo  = (string) $request->query('grupo', '');

    if (!$formId) {
        abort(400, 'Falta parámetro form_id');
    }

    // Copiar lógica de BillingController::generarExcel($formId, $grupo)
    // Adaptar acceso a DB
}

public function exportarPlanillasPorMes(Request $request): Response
{
    $this->authorize(['administrativo', 'billing.export', 'billing.manage']);
    $mes   = $request->query('mes');
    $grupo = (string) $request->query('grupo', '');

    if (!$mes) {
        abort(400, 'Falta parámetro mes');
    }

    // Copiar lógica de BillingController::exportarPlanillasPorMes($mes, $grupo)
}
```

- [ ] **Step 4: Registrar rutas en v2/billing.php**

```php
// En el grupo billing.export,billing.manage:
Route::get('/billing/excel', [BillingUiController::class, 'generarExcel']);
Route::get('/billing/exportar_mes', [BillingUiController::class, 'exportarPlanillasPorMes']);
```

- [ ] **Step 5: Eliminar bloques hardcodeados de public/index.php**

Eliminar los 2 bloques if de `public/index.php` (aproximadamente líneas 101–145) que manejan `/billing/excel` y `/billing/exportar_mes`. Estos son los únicos 2 bloques de lógica de negocio que quedan hardcodeados en ese archivo para Billing.

Verificar resultado:
```bash
grep -n "billing" public/index.php
```
Expected: cero resultados (ya no quedan referencias a billing en index.php).

- [ ] **Step 6: Verificar rutas**

```bash
cd laravel-app && php artisan route:list | grep "billing/excel\|exportar_mes"
```

- [ ] **Step 7: Commit**

```bash
git add public/index.php
git add laravel-app/app/Modules/Billing/Http/Controllers/BillingUiController.php
git add laravel-app/routes/v2/billing.php
git commit -m "$(cat <<'EOF'
feat(onda5a): port /billing/excel and /billing/exportar_mes to Laravel

Removed hardcoded Billing routes from public/index.php.
Added generarExcel() and exportarPlanillasPorMes() to BillingUiController.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Verificar cero refs externas, agregar bridge, delete

**Files:**
- Modify: `public/index.php` — agregar `/billing` e `/informes` a `$laravelBridgePrefixes`
- Delete: `modules/Billing/`

- [ ] **Step 1: Grep de dependencias externas hacia Billing**

```bash
grep -r "Modules\\\\Billing\|BillingController\|InformesController" \
  modules/ --include="*.php" | grep -v "modules/Billing/"

grep -r "Modules\\\\Billing" laravel-app/ --include="*.php"
```

Expected: cero resultados externos. Si aparece algo, leerlo y resolver antes de continuar.

- [ ] **Step 2: Verificar cobertura completa de rutas**

```bash
# Rutas legacy activas (excluir las ya identificadas como muertas)
grep "router->" modules/Billing/routes.php

# Confirmar que v2 las cubre todas
cd laravel-app && php artisan route:list | grep -E "billing|informes"
```

- [ ] **Step 3: Agregar /billing e /informes al bridge**

En `public/index.php`, agregar al array `$laravelBridgePrefixes`:

```php
$laravelBridgePrefixes = [
    '/v2', '/usuarios', '/roles', '/feedback', '/protocolos',
    '/examenes', '/imagenes', '/agenda', '/derivaciones', '/reports',
    '/mailbox', '/mail', '/ai', '/search', '/api/cive-extension',
    '/mail-templates', '/insumos', '/kpis', '/doctores',
    '/pacientes', '/cirugias', '/cron-manager',
    '/billing', '/informes',   // ← Onda 5-A
];
```

- [ ] **Step 4: Smoke test del bridge**

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

- [ ] **Step 5: Eliminar modules/Billing/**

```bash
rm -rf modules/Billing
```

- [ ] **Step 6: Smoke test post-delete**

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

- [ ] **Step 7: Tests Laravel**

```bash
cd laravel-app && php artisan test --filter=Billing 2>&1 | tail -15
```

- [ ] **Step 8: Commit final**

```bash
git add public/index.php
git add -A modules/Billing
git commit -m "$(cat <<'EOF'
feat(onda5a): delete Billing legacy — add bridge, all routes covered in Laravel

Added /billing and /informes to bridge prefixes. Verified zero cross-module
dependencies. Deleted modules/Billing/ (37 files, 13k lines).

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

- `modules/Billing/` — eliminado ✅
- Bridge: `/billing` + `/informes` agregados ✅
- `public/index.php`: sin rutas hardcodeadas de Billing ✅
- Líneas eliminadas: ~13k

**Siguiente:** `docs/superpowers/plans/2026-05-21-onda5b-crm-leads.md`
