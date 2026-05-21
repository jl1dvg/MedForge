# Onda 4 — Medianos con Contexto: IdentityVerification, CronManager, Cirugias, Pacientes

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development** to execute this plan. One subagent per module. **Ejecutar en orden estricto** — IdentityVerification y CronManager liberan dependencias entrantes de Pacientes.
>
> **Execution mode:** subagent-driven, secuencial (4 módulos, coordinación por dependencias)
>
> **Master roadmap:** `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`

**Goal:** Migrar 4 módulos de complejidad media. Pacientes tiene 14 dependencias entrantes — las Ondas 3 y 4 eliminan la mayoría antes de llegar a Pacientes.

**Orden obligatorio:**
1. **IdentityVerification** (6 rutas, 5 deps entrantes — estas son referencias desde Pacientes y Cirugias que se resuelven al portar Pacientes, pero el módulo mismo no tiene deps externas hacia otros)
2. **CronManager** (4 rutas, motor de crons — contiene `ExamenesReminderService` ya movido aquí en sesión anterior)
3. **Cirugias** (11 rutas, 8k líneas, con v2 routes existentes)
4. **Pacientes** (6 rutas, 3.4k líneas, 14 deps entrantes — migrar de último)

**Prerequisitos:** Ondas 1–3 completadas.

**Estado conocido:**
- `modules/CronManager/Services/ExamenesReminderService.php` — ya existe, fue movido aquí desde `modules/Examenes` en sesión anterior
- Laravel ya tiene rutas v2 para Cirugias (13 rutas en `laravel-app/routes/v2/cirugias.php`) y Pacientes (11 rutas en `laravel-app/routes/v2/pacientes.php`)

---

## Task 1: IdentityVerification — portar verificaciones biométricas y certificaciones

**Legacy:** `modules/IdentityVerification/` — 11 archivos, 2.9k líneas
- `Controllers/VerificationController.php`
- `Models/VerificationModel.php`
- `Services/`: ConsentDocumentService, FaceRecognitionService, MissingEvidenceEscalationService, PythonBiometricClient, SignatureAnalysisService, VerificationPolicyService
- `views/`: consent_document.php, index.php
- `routes.php` → 6 rutas bajo `/pacientes/certificaciones/`

**Rutas legacy:**
- `GET /pacientes/certificaciones`
- `POST /pacientes/certificaciones`
- `GET /pacientes/certificaciones/detalle`
- `GET /pacientes/certificaciones/comprobante`
- `POST /pacientes/certificaciones/verificar`
- `POST /pacientes/certificaciones/eliminar`

**Files a leer:**
```bash
cat modules/IdentityVerification/routes.php
cat modules/IdentityVerification/Controllers/VerificationController.php
cat modules/IdentityVerification/Services/ConsentDocumentService.php
cat modules/IdentityVerification/Services/FaceRecognitionService.php
cat modules/IdentityVerification/Services/PythonBiometricClient.php
cat modules/IdentityVerification/Services/VerificationPolicyService.php
cat modules/IdentityVerification/Models/VerificationModel.php
```

**Contexto crítico:** `PythonBiometricClient` hace llamadas a un servicio Python. Mantener la misma lógica de HTTP/subprocess. `FaceRecognitionService` y `SignatureAnalysisService` probablemente llaman al cliente Python.

**Files a crear:**
- `laravel-app/app/Modules/IdentityVerification/Http/Controllers/VerificationController.php`
- `laravel-app/app/Modules/IdentityVerification/Services/{ConsentDocumentService,FaceRecognitionService,MissingEvidenceEscalationService,PythonBiometricClient,SignatureAnalysisService,VerificationPolicyService}.php`
- `laravel-app/routes/v2/identity_verification.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Verificar existencia en Laravel**

```bash
ls laravel-app/app/Modules/ | grep -i identity
find laravel-app -name "*Verification*" -o -name "*Biometric*" | head -10
```

- [ ] **Step 3: Verificar dependencias entrantes**

```bash
grep -r "Modules\\\\IdentityVerification\|VerificationController\|ConsentDocument\|FaceRecognition" modules/ --include="*.php" -l | grep -v "modules/IdentityVerification/"
```
Expected: 5 referencias entrantes (desde Pacientes y posiblemente Cirugias). Documentarlas. Estas referencias se romperán cuando se migre Pacientes en Task 4, pero se resuelven apuntando a la versión Laravel.

- [ ] **Step 4: Portar todos los servicios** (namespace `App\Modules\IdentityVerification\Services\...`)

`PythonBiometricClient`: el legacy probablemente usa `shell_exec()` o `Process::run()`. En Laravel usar `Symfony\Component\Process\Process`.

- [ ] **Step 5: Crear routes/v2/identity_verification.php**

```php
<?php

use App\Modules\IdentityVerification\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo,pacientes.view'])->group(function (): void {
    Route::get('/pacientes/certificaciones', [VerificationController::class, 'index']);
    Route::post('/pacientes/certificaciones', [VerificationController::class, 'store']);
    Route::get('/pacientes/certificaciones/detalle', [VerificationController::class, 'show']);
    Route::get('/pacientes/certificaciones/comprobante', [VerificationController::class, 'consentDocument']);
    Route::post('/pacientes/certificaciones/verificar', [VerificationController::class, 'verify']);
    Route::post('/pacientes/certificaciones/eliminar', [VerificationController::class, 'destroy']);
});
```

Nota: estas rutas están bajo `/pacientes/` prefix. El bridge tendrá `/pacientes` cuando migremos Pacientes en Task 4. Por ahora, agregar las rutas a Laravel pero NO agregar `/pacientes` al bridge todavía (Pacientes aún está en legacy). Si la ruta `GET /pacientes/certificaciones` no funciona en producción por conflicto, agregar solo el sub-prefix `/pacientes/certificaciones` al bridge.

- [ ] **Step 6: Registrar en routes/api.php**

```php
require __DIR__ . '/v2/identity_verification.php';
```

- [ ] **Step 7: Verificar cero refs externas, eliminar módulo, commit**

```bash
grep -r "Modules\\\\IdentityVerification" modules/ --include="*.php" | grep -v "modules/IdentityVerification/"
# Las refs desde Pacientes/Cirugias quedarán rotas hasta que esos módulos migren — es aceptable

rm -rf modules/IdentityVerification

cd laravel-app && php artisan route:list | grep "certificaciones"

git add laravel-app/routes/v2/identity_verification.php laravel-app/routes/api.php
git add laravel-app/app/Modules/IdentityVerification/
git add -A modules/IdentityVerification
git commit -m "$(cat <<'EOF'
feat(onda4): migrate IdentityVerification to Laravel, delete legacy

Ported 6 /pacientes/certificaciones routes to app/Modules/IdentityVerification.
Routes registered in Laravel; /pacientes bridge will be added when Pacientes
module migrates in Task 4.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: CronManager — portar gestor de tareas programadas

**Legacy:** `modules/CronManager/` — 7 archivos, 3k líneas
- `Controllers/CronManagerController.php`
- `Repositories/CronTaskRepository.php`
- `Services/CronRunner.php` — orquestador de todas las tareas cron del sistema
- `Services/ExamenesReminderService.php` — YA EXISTE aquí (movido en sesión de Examenes)
- `index.php`
- `views/index.php`
- `routes.php` → 4 rutas: `GET /cron-manager`, `POST /cron-manager/run`, `POST /cron-manager/run/{slug}`, `POST /cron-manager/settings/{slug}`

**Contexto crítico:** `CronRunner` es el motor de todos los crons del sistema. Invocar sus tareas en Laravel requiere asegurar que los servicios que llama estén disponibles. Revisar qué otros módulos usa:

```bash
grep -n "use " modules/CronManager/Services/CronRunner.php | head -30
```

Verificar que los servicios importados en CronRunner ya están en Laravel (portados en Ondas anteriores) o están en `modules/` (se portarán en Ondas posteriores — en ese caso, el CronRunner Laravel necesita llamarlos via PHP legacy o portarlos inline).

**Files a leer:**
```bash
cat modules/CronManager/routes.php
cat modules/CronManager/Controllers/CronManagerController.php
cat modules/CronManager/Services/CronRunner.php
cat modules/CronManager/Repositories/CronTaskRepository.php
cat modules/CronManager/Services/ExamenesReminderService.php
```

**Files a crear:**
- `laravel-app/app/Modules/CronManager/Http/Controllers/CronManagerController.php`
- `laravel-app/app/Modules/CronManager/Services/CronRunner.php`
- `laravel-app/app/Modules/CronManager/Services/ExamenesReminderService.php`
- `laravel-app/app/Modules/CronManager/Repositories/CronTaskRepository.php`
- `laravel-app/routes/v2/cron_manager.php`
- Modify: `laravel-app/routes/api.php`, `public/index.php`

- [ ] **Step 1: Leer código legacy completo** (ver comandos arriba)

- [ ] **Step 2: Mapear dependencias del CronRunner**

```bash
grep "^use " modules/CronManager/Services/CronRunner.php
```

Para cada dependencia externa (servicios de otros módulos):
- Si el módulo ya migró a Laravel → apuntar al namespace `App\Modules\...`
- Si el módulo aún está en legacy → mantener el path legacy por ahora o portar el servicio inline

- [ ] **Step 3: Verificar existencia en Laravel**

```bash
ls laravel-app/app/Modules/ | grep -i cron
find laravel-app -name "*Cron*" | head -10
```

Verificar si `laravel-app/routes/console.php` ya tiene comandos Artisan equivalentes a los cron tasks del legacy.

- [ ] **Step 4: Portar servicios y controladores**

Para el `CronRunner` en Laravel: si depende de servicios que aún están en módulos legacy no migrados, crear una capa adaptadora que cargue el bootstrap legacy e invoque esos servicios via `require`. Esta es una solución temporal aceptable — se limpia cuando esos módulos migren.

```php
// Ejemplo de adaptador temporal si un servicio no está en Laravel aún:
private function invocarServicioLegacy(string $slug): void
{
    $basePath = dirname(base_path()); // repo root
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $basePath);
        define('PUBLIC_PATH', $basePath . '/public');
        require_once $basePath . '/bootstrap.php';
    }
    // Instanciar servicio legacy
    $service = new \Modules\SomeModule\Services\SomeService($this->pdo);
    $service->run();
}
```

- [ ] **Step 5: Crear routes/v2/cron_manager.php**

```php
<?php

use App\Modules\CronManager\Http\Controllers\CronManagerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:administrativo'])->group(function (): void {
    Route::get('/cron-manager', [CronManagerController::class, 'index']);
    Route::post('/cron-manager/run', [CronManagerController::class, 'runAll']);
    Route::post('/cron-manager/run/{slug}', [CronManagerController::class, 'runTask']);
    Route::post('/cron-manager/settings/{slug}', [CronManagerController::class, 'updateSettings']);
});
```

- [ ] **Step 6: Agregar /cron-manager al bridge**

```php
$laravelBridgePrefixes = [..., '/cron-manager'];
```

- [ ] **Step 7: Tests mínimos**

```bash
cd laravel-app && php artisan test --filter=CronManager 2>/dev/null || echo "No CronManager tests"
```
Crear un test básico que verifica `GET /cron-manager` responde 200.

- [ ] **Step 8: Eliminar CronManager, commit**

```bash
rm -rf modules/CronManager

git add public/index.php laravel-app/routes/v2/cron_manager.php laravel-app/routes/api.php
git add laravel-app/app/Modules/CronManager/
git add -A modules/CronManager
git commit -m "$(cat <<'EOF'
feat(onda4): migrate CronManager to Laravel, delete legacy

Ported CronRunner, ExamenesReminderService, and 4 routes to
app/Modules/CronManager. Bridge now intercepts /cron-manager prefix.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Cirugias — completar parity y eliminar módulo

**Legacy:** `modules/Cirugias/` — 27 archivos, 8k líneas
- 3 controllers, 3 models, 2 services, 1 helper, vistas, docs
- 11 rutas activas (con redirectores condicionales a v2 ya existentes)

**Estado:** Laravel ya tiene `laravel-app/routes/v2/cirugias.php` con 13 rutas. El legacy tiene redirectores condicionales via `CIRUGIAS_V2_UI_ENABLED` env flag.

**Files a leer primero:**
```bash
cat modules/Cirugias/routes.php
cat laravel-app/routes/v2/cirugias.php
# Ver qué rutas legacy NO tienen equivalente en v2:
diff <(grep "router->" modules/Cirugias/routes.php | sort) \
     <(grep "Route::" laravel-app/routes/v2/cirugias.php | sort)
```

**Proceso:**
1. Mapear cada ruta legacy a su equivalente v2
2. Identificar rutas sin equivalente → portarlas
3. Verificar que los servicios/modelos en `laravel-app/app/Modules/Cirugias/` cubren toda la lógica de negocio
4. Agregar `/cirugias` al bridge
5. Verificar cero refs externas
6. `rm -rf modules/Cirugias/`

- [ ] **Step 1: Leer rutas legacy y v2**

```bash
cat modules/Cirugias/routes.php
cat laravel-app/routes/v2/cirugias.php
find laravel-app/app/Modules/Cirugias -name "*.php" | sort
```

- [ ] **Step 2: Auditar servicios legacy vs. Laravel**

```bash
grep "^use " modules/Cirugias/Controllers/CirugiasController.php
grep "public function" laravel-app/app/Modules/Cirugias/Http/Controllers/*.php 2>/dev/null | head -30
```

Identificar qué métodos/lógica del legacy no están en los controladores Laravel.

- [ ] **Step 3: Verificar cero refs externas a Cirugias**

```bash
grep -r "Modules\\\\Cirugias\|CirugiasController\|CirugiaService\|ProtocoloModel" modules/ --include="*.php" -l | grep -v "modules/Cirugias/"
```

Nota: EditorProtocolos usaba `ProtocoloModel` (pero EditorProtocolos ya fue eliminado en Onda 1). Verificar que no queda ninguna referencia.

- [ ] **Step 4: Portar rutas/lógica faltante a Laravel**

Para cada ruta en el legacy que no tenga equivalente v2:
- Añadir el método al controlador Laravel correspondiente
- Añadir la ruta en `laravel-app/routes/v2/cirugias.php`

- [ ] **Step 5: Agregar /cirugias al bridge**

```php
$laravelBridgePrefixes = [..., '/cirugias'];
```

- [ ] **Step 6: Tests**

```bash
cd laravel-app && php artisan test --filter=Cirugias
```
Si hay tests fallando, verificar si son pre-existentes o causados por la migración.

- [ ] **Step 7: Eliminar Cirugias, commit**

```bash
rm -rf modules/Cirugias

git add public/index.php laravel-app/routes/v2/cirugias.php
git add laravel-app/app/Modules/Cirugias/
git add -A modules/Cirugias
git commit -m "$(cat <<'EOF'
feat(onda4): delete Cirugias legacy — complete parity in Laravel, add bridge

Ported remaining routes and business logic. Bridge now intercepts
/cirugias prefix. 27 legacy files deleted.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Pacientes — completar parity y eliminar módulo (14 deps entrantes)

**Legacy:** `modules/Pacientes/` — 17 archivos, 3.4k líneas
- `Controllers/Pacientes.php`, `Controllers/PacientesController.php`
- `Helpers/PacientesHelpers.php`
- `Models/PacientesModel.php`
- `Services/Paciente360Service.php`, `Services/PacienteService.php`
- `Support/ViewHelper.php`
- `Tests/PacienteServiceTest.php`
- `views/`: detalles, flujo, index, lista, pacientes_datatable, y components/

**Estado:** Laravel ya tiene 11 rutas en `laravel-app/routes/v2/pacientes.php`.

**Las 14 dependencias entrantes:** referenciadas desde módulos que ya migraron o migrarán antes. Verificar cuáles quedan:

```bash
grep -r "Modules\\\\Pacientes\|PacientesModel\|Paciente360Service\|PacienteService\|PacientesHelpers" modules/ --include="*.php" -l | grep -v "modules/Pacientes/"
```

**Importante:** `Paciente360Service` en este módulo tiene un import de `Models\ExamenModel` (fue actualizado en sesión de Examenes — usa namespace `Models`). Verificar que esto está correcto:

```bash
grep "use Models\\\\ExamenModel\|use Modules\\\\Examenes" modules/Pacientes/Services/Paciente360Service.php
```
Expected: `use Models\ExamenModel;` (ya corregido).

- [ ] **Step 1: Leer rutas y servicios**

```bash
cat modules/Pacientes/routes.php
cat laravel-app/routes/v2/pacientes.php
find laravel-app/app/Modules/Pacientes -name "*.php" | sort
```

- [ ] **Step 2: Auditar lógica de Paciente360Service**

```bash
cat modules/Pacientes/Services/Paciente360Service.php
```
Este servicio agrega datos de múltiples módulos (examenes, solicitudes, cirugias, etc.). Verificar que la versión Laravel puede obtener los mismos datos. Algunos módulos ya migraron, sus datos siguen en la misma DB.

- [ ] **Step 3: Verificar y resolver todas las deps entrantes**

```bash
grep -r "Modules\\\\Pacientes\|Paciente360Service\|PacienteService\|PacientesModel" modules/ --include="*.php" | grep -v "modules/Pacientes/"
```

Para cada referencia encontrada:
- Si el módulo que la tiene ya migró a Laravel → actualizar import en el módulo Laravel
- Si el módulo que la tiene aún está en legacy (Onda 5) → dejar para cuando ese módulo migre (documentar)

- [ ] **Step 4: Portar lógica faltante a Laravel**

```bash
# Comparar rutas legacy vs v2:
grep "router->" modules/Pacientes/routes.php
grep "Route::" laravel-app/routes/v2/pacientes.php
```

Portar rutas/métodos faltantes a `laravel-app/app/Modules/Pacientes/`.

- [ ] **Step 5: Agregar /pacientes al bridge**

```php
$laravelBridgePrefixes = [..., '/pacientes'];
```

Esto también cubre `/pacientes/certificaciones` (IdentityVerification) ya portado en Task 1.

- [ ] **Step 6: Tests**

```bash
cd laravel-app && php artisan test --filter=Pacientes
```

- [ ] **Step 7: Eliminar Pacientes, smoke test, commit**

```bash
rm -rf modules/Pacientes

php -r "
define('BASE_PATH', __DIR__); define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
use Core\ModuleLoader; use Core\Router;
\$pdo = \$GLOBALS['pdo'];
\$router = new Router(\$pdo);
ModuleLoader::register(\$router, \$pdo, BASE_PATH . '/modules');
echo 'Router OK' . PHP_EOL;
"

git add public/index.php laravel-app/routes/v2/pacientes.php
git add laravel-app/app/Modules/Pacientes/
git add -A modules/Pacientes
git commit -m "$(cat <<'EOF'
feat(onda4): delete Pacientes legacy — complete parity in Laravel, add bridge

Ported remaining routes and Paciente360Service aggregation logic.
Bridge now intercepts /pacientes prefix (covers /pacientes/certificaciones too).
14 incoming deps resolved: modules that referenced Pacientes have been migrated
or are in Onda 5 queue.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

| Módulo | Estado |
|--------|--------|
| IdentityVerification | ✅ eliminado |
| CronManager | ✅ eliminado |
| Cirugias | ✅ eliminado |
| Pacientes | ✅ eliminado |

- Módulos legacy restantes: ~11 → 7 (–4)
- Líneas eliminadas (acum.): ~38k
- Bridge ampliado con: `/pacientes` (cubre certificaciones), `/cirugias`, `/cron-manager`

**Siguiente:** Onda 5 → `docs/superpowers/plans/2026-05-21-onda5-giants.md`
