# Onda 5-C — CRM Entities: Projects, Tasks, Tickets, Proposals + Bridge + Delete

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development**. Dispatch a fresh subagent per task.
>
> **Execution mode:** subagent-driven (~2 días)
>
> **Prerequisito:** Onda 5-B (CRM Leads) completa.

**Goal:** Portar los 4 dominios CRM restantes (Projects, Tasks, Tickets, Proposals), agregar `/crm` y `/leads` al bridge, y eliminar `modules/CRM/`.

**Architecture:** Añadir métodos a `CrmReadController` y `CrmWriteController` existentes, agrupados por entidad. Una vez las 31 rutas legacy tienen parity en v2, agregar el bridge y borrar.

**Gap de esta sesión — rutas sin parity v2:**

| Entidad | Rutas legacy |
|---------|-------------|
| Projects | `GET /crm/projects`, `GET /crm/projects/{id}`, `POST /crm/projects`, `PATCH /crm/projects/{id}`, `POST /crm/projects/status` |
| Tasks | `GET /crm/tasks`, `POST /crm/tasks`, `PATCH /crm/tasks/{id}`, `POST /crm/tasks/status` |
| Tickets | `GET /crm/tickets`, `POST /crm/tickets`, `POST /crm/tickets/reply` |
| Proposals | `GET /crm/proposals`, `GET /crm/proposals/{id}`, `POST /crm/proposals`, `POST /crm/proposals/perfex/parse`, `POST /crm/proposals/status` |

---

## Task 1: Leer código legacy de Projects, Tasks, Tickets, Proposals

**Files:**
- Read: `modules/CRM/Controllers/CRMController.php` — métodos project*, task*, ticket*, proposal*
- Read: `modules/CRM/Services/CrmProjectService.php`
- Read: `modules/CRM/Services/CrmTaskService.php`
- Read: `modules/CRM/Services/TaskService.php`
- Read: `modules/CRM/Services/PerfexEstimatesParser.php`
- Read: `modules/CRM/Models/ProjectModel.php`
- Read: `modules/CRM/Models/TaskModel.php`
- Read: `modules/CRM/Models/TicketModel.php`
- Read: `modules/CRM/Models/ProposalModel.php`

- [ ] **Step 1: Leer todos los servicios**

```bash
for f in modules/CRM/Services/*.php; do
  echo "=== $f ==="; cat "$f"; echo ""
done
```

- [ ] **Step 2: Leer todos los models**

```bash
for f in modules/CRM/Models/*.php; do
  echo "=== $f ==="; cat "$f"; echo ""
done
```

- [ ] **Step 3: Leer métodos de CRMController para Projects/Tasks/Tickets/Proposals**

```bash
grep -n "function.*roject\|function.*ask\|function.*icket\|function.*roposal" \
  modules/CRM/Controllers/CRMController.php
```

- [ ] **Step 4: Verificar si ya existen servicios Laravel para estas entidades**

```bash
find laravel-app/app/Modules/CRM -name "*.php" | sort
```

Si `CrmProjectService`, `CrmTaskService`, etc. ya están en Laravel, reutilizarlos.

---

## Task 2: Portar Projects (5 rutas)

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Agregar métodos de Projects a CrmReadController**

```php
public function projects(Request $request): JsonResponse
{
    // Adaptar lógica del GET /crm/projects
    // Lista paginada de proyectos con filtros opcionales
    // Usar DB::table('crm_projects') o Eloquent si existe
}

public function project(Request $request, int $id): JsonResponse
{
    // Adaptar GET /crm/projects/{id}
    // Retorna un proyecto completo con sus tareas
}
```

- [ ] **Step 2: Agregar métodos de Projects a CrmWriteController**

```php
public function createProject(Request $request): JsonResponse
{
    // POST /crm/projects
}

public function updateProject(Request $request, int $id): JsonResponse
{
    // PATCH /crm/projects/{id}
}

public function updateProjectStatus(Request $request): JsonResponse
{
    // POST /crm/projects/status
    // Actualiza el estado de un proyecto (activo/completado/cancelado)
}
```

- [ ] **Step 3: Registrar rutas en v2/crm.php**

```php
// Projects — en el grupo de middleware crm.view,crm.manage:
Route::get('/crm/projects', [CrmReadController::class, 'projects']);
Route::get('/crm/projects/{id}', [CrmReadController::class, 'project'])->whereNumber('id');
Route::post('/crm/projects', [CrmWriteController::class, 'createProject']);
Route::patch('/crm/projects/{id}', [CrmWriteController::class, 'updateProject'])->whereNumber('id');
Route::post('/crm/projects/status', [CrmWriteController::class, 'updateProjectStatus']);

// Aliases API:
Route::get('/api/crm/projects', [CrmReadController::class, 'projects']);
Route::get('/api/crm/projects/{id}', [CrmReadController::class, 'project'])->whereNumber('id');
Route::post('/api/crm/projects', [CrmWriteController::class, 'createProject']);
Route::patch('/api/crm/projects/{id}', [CrmWriteController::class, 'updateProject'])->whereNumber('id');
Route::post('/api/crm/projects/status', [CrmWriteController::class, 'updateProjectStatus']);
```

- [ ] **Step 4: Verificar**

```bash
cd laravel-app && php artisan route:list | grep "crm/projects"
```

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php
git add laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5c): port CRM Projects CRUD (5 routes) to Laravel

GET/POST /crm/projects, GET/PATCH /crm/projects/{id}, POST /crm/projects/status

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Portar Tasks (4 rutas)

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Agregar métodos de Tasks**

```php
// En CrmReadController:
public function tasks(Request $request): JsonResponse
{
    // GET /crm/tasks — lista de tareas con filtros (proyecto, asignado, estado)
}

// En CrmWriteController:
public function createTask(Request $request): JsonResponse
{
    // POST /crm/tasks
}

public function updateTask(Request $request, int $id): JsonResponse
{
    // PATCH /crm/tasks/{id}
}

public function updateTaskStatus(Request $request): JsonResponse
{
    // POST /crm/tasks/status
}
```

- [ ] **Step 2: Registrar rutas**

```php
Route::get('/crm/tasks', [CrmReadController::class, 'tasks']);
Route::post('/crm/tasks', [CrmWriteController::class, 'createTask']);
Route::patch('/crm/tasks/{id}', [CrmWriteController::class, 'updateTask'])->whereNumber('id');
Route::post('/crm/tasks/status', [CrmWriteController::class, 'updateTaskStatus']);

Route::get('/api/crm/tasks', [CrmReadController::class, 'tasks']);
Route::post('/api/crm/tasks', [CrmWriteController::class, 'createTask']);
Route::patch('/api/crm/tasks/{id}', [CrmWriteController::class, 'updateTask'])->whereNumber('id');
Route::post('/api/crm/tasks/status', [CrmWriteController::class, 'updateTaskStatus']);
```

- [ ] **Step 3: Verificar y commit**

```bash
cd laravel-app && php artisan route:list | grep "crm/tasks"

git add laravel-app/app/Modules/CRM/Http/Controllers/
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5c): port CRM Tasks CRUD (4 routes) to Laravel

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Portar Tickets (3 rutas)

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Agregar métodos de Tickets**

```php
// En CrmReadController:
public function tickets(Request $request): JsonResponse
{
    // GET /crm/tickets — lista de tickets de soporte
}

// En CrmWriteController:
public function createTicket(Request $request): JsonResponse
{
    // POST /crm/tickets
}

public function replyTicket(Request $request): JsonResponse
{
    // POST /crm/tickets/reply — agrega respuesta a un ticket
}
```

- [ ] **Step 2: Registrar rutas**

```php
Route::get('/crm/tickets', [CrmReadController::class, 'tickets']);
Route::post('/crm/tickets', [CrmWriteController::class, 'createTicket']);
Route::post('/crm/tickets/reply', [CrmWriteController::class, 'replyTicket']);

Route::get('/api/crm/tickets', [CrmReadController::class, 'tickets']);
Route::post('/api/crm/tickets', [CrmWriteController::class, 'createTicket']);
Route::post('/api/crm/tickets/reply', [CrmWriteController::class, 'replyTicket']);
```

- [ ] **Step 3: Verificar y commit**

```bash
cd laravel-app && php artisan route:list | grep "crm/tickets"

git add laravel-app/app/Modules/CRM/Http/Controllers/
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5c): port CRM Tickets (3 routes) to Laravel

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Portar Proposals CRUD (5 rutas adicionales)

Las rutas `proposals/{id}/pdf`, `proposals/{id}/send-email`, `proposals/{id}/send-whatsapp` **ya existen en v2**. El gap son: list, create, parse-perfex, status.

**Files:**
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmReadController.php`
- Modify: `laravel-app/app/Modules/CRM/Http/Controllers/CrmWriteController.php`
- Modify: `laravel-app/app/Modules/CRM/Services/CrmProposalService.php` (si aplica)
- Modify: `laravel-app/routes/v2/crm.php`

- [ ] **Step 1: Leer lógica de proposals en legacy**

```bash
grep -A 40 "function.*roposal\|function.*proposal" modules/CRM/Controllers/CRMController.php
cat modules/CRM/Services/PerfexEstimatesParser.php
```

`PerfexEstimatesParser` parsea presupuestos de Perfex CRM (sistema externo). Portarlo a `laravel-app/app/Modules/CRM/Services/PerfexEstimatesParser.php`.

- [ ] **Step 2: Portar PerfexEstimatesParser si no existe en Laravel**

```bash
ls laravel-app/app/Modules/CRM/Services/
```

Si no está:
```php
// Copiar modules/CRM/Services/PerfexEstimatesParser.php a:
// laravel-app/app/Modules/CRM/Services/PerfexEstimatesParser.php
// Cambiar namespace a App\Modules\CRM\Services
```

- [ ] **Step 3: Agregar métodos de Proposals**

```php
// En CrmReadController:
public function proposals(Request $request): JsonResponse
{
    // GET /crm/proposals — lista de propuestas
}

public function proposal(Request $request, int $id): JsonResponse
{
    // GET /crm/proposals/{id} — detalle de propuesta
}

// En CrmWriteController:
public function createProposal(Request $request): JsonResponse
{
    // POST /crm/proposals
}

public function parsePerfex(Request $request): JsonResponse
{
    // POST /crm/proposals/perfex/parse
    // Usa App\Modules\CRM\Services\PerfexEstimatesParser
}

public function updateProposalStatus(Request $request): JsonResponse
{
    // POST /crm/proposals/status
}
```

- [ ] **Step 4: Registrar rutas**

```php
Route::get('/crm/proposals', [CrmReadController::class, 'proposals']);
Route::get('/crm/proposals/{id}', [CrmReadController::class, 'proposal'])->whereNumber('id');
Route::post('/crm/proposals', [CrmWriteController::class, 'createProposal']);
Route::post('/crm/proposals/perfex/parse', [CrmWriteController::class, 'parsePerfex']);
Route::post('/crm/proposals/status', [CrmWriteController::class, 'updateProposalStatus']);

Route::get('/api/crm/proposals', [CrmReadController::class, 'proposals']);
Route::get('/api/crm/proposals/{id}', [CrmReadController::class, 'proposal'])->whereNumber('id');
Route::post('/api/crm/proposals', [CrmWriteController::class, 'createProposal']);
Route::post('/api/crm/proposals/perfex/parse', [CrmWriteController::class, 'parsePerfex']);
Route::post('/api/crm/proposals/status', [CrmWriteController::class, 'updateProposalStatus']);
```

- [ ] **Step 5: Verificar**

```bash
cd laravel-app && php artisan route:list | grep "crm/proposals"
```

- [ ] **Step 6: Tests**

```bash
cd laravel-app && php artisan test --filter=CrmProposal 2>/dev/null || echo "Sin tests de propuestas"
```

- [ ] **Step 7: Commit**

```bash
git add laravel-app/app/Modules/CRM/
git add laravel-app/routes/v2/crm.php
git commit -m "$(cat <<'EOF'
feat(onda5c): port CRM Proposals CRUD (5 routes) + PerfexEstimatesParser to Laravel

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Auditoría final, bridge, eliminar CRM

**Files:**
- Modify: `public/index.php` — agregar `/crm` y `/leads` a `$laravelBridgePrefixes`
- Delete: `modules/CRM/`

- [ ] **Step 1: Auditoría completa de rutas — cero gaps**

```bash
echo "=== Rutas legacy ==="
grep -oP "router->\w+\('[^']+'" modules/CRM/routes.php | sort

echo ""
echo "=== Rutas v2 (paths únicos) ==="
cd laravel-app && php artisan route:list --path=crm | grep -v "HEAD"
```

Confirmar que cada ruta legacy tiene equivalente en v2.

- [ ] **Step 2: Verificar cero referencias cross-module hacia CRM**

```bash
grep -r "Modules\\\\CRM\|CRMController\|LeadController\|CrmProjectService\|CrmTaskService" \
  modules/ --include="*.php" | grep -v "modules/CRM/"

grep -r "Modules\\\\CRM" laravel-app/ --include="*.php"
```

Expected: cero resultados externos. Las referencias desde Laravel mismo (`App\Modules\CRM\...`) son válidas.

- [ ] **Step 3: Agregar /crm y /leads al bridge**

En `public/index.php`:

```php
$laravelBridgePrefixes = [
    '/v2', '/usuarios', '/roles', '/feedback', '/protocolos',
    '/examenes', '/imagenes', '/agenda', '/derivaciones', '/reports',
    '/mailbox', '/mail', '/ai', '/search', '/api/cive-extension',
    '/mail-templates', '/insumos', '/kpis', '/doctores',
    '/pacientes', '/cirugias', '/cron-manager',
    '/billing', '/informes',
    '/crm', '/leads',   // ← Onda 5-C
];
```

- [ ] **Step 4: Smoke test del router**

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

- [ ] **Step 5: Eliminar modules/CRM/**

```bash
rm -rf modules/CRM
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

- [ ] **Step 7: Tests completos**

```bash
cd laravel-app && php artisan test --filter=CRM 2>&1 | tail -15
```

- [ ] **Step 8: Commit final**

```bash
git add public/index.php
git add -A modules/CRM
git commit -m "$(cat <<'EOF'
feat(onda5c): delete CRM legacy — complete parity, add /crm and /leads bridge

All 31 legacy routes ported across CrmReadController, CrmWriteController,
CrmProposalController, and CrmUiController. Bridge covers /crm and /leads.
Deleted modules/CRM/ (31 files, 9.4k lines).

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado de 5-C

| Entidad | Rutas portadas |
|---------|---------------|
| Projects | 5 rutas ✅ |
| Tasks | 4 rutas ✅ |
| Tickets | 3 rutas ✅ |
| Proposals | 5 rutas ✅ |
| Bridge | `/crm` + `/leads` ✅ |
| Delete | `modules/CRM/` ✅ |

- Líneas eliminadas: ~9.4k (CRM) + ~13k (Billing) = ~22.4k acumulado en Onda 5

**Siguiente:** `docs/superpowers/plans/2026-05-21-onda5d-whatsapp.md`
