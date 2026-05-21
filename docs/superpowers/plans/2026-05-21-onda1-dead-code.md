# Onda 1 — Código Muerto: Usuarios + EditorProtocolos

> **For agentic workers:** Use **superpowers:executing-plans** to run this plan task by task in the same session. Steps use checkbox (`- [ ]`) syntax.
>
> **Execution mode:** executing-plans (2 modules, pure deletion, ~1 hour total)
>
> **Master roadmap:** `docs/superpowers/specs/2026-05-21-legacy-zero-roadmap.md`

**Goal:** Delete `modules/Usuarios/` and `modules/EditorProtocolos/` — both are dead code because their route prefixes (`/usuarios`, `/roles`, `/protocolos`) are already intercepted by the bridge in `public/index.php` before the legacy router ever runs.

**Architecture:** The bridge in `public/index.php` sends all requests with prefix `/usuarios`, `/roles`, and `/protocolos` to Laravel (`v2_kernel.php`). The legacy routes registered by these modules are never reached in production. After handling the one cross-module class dependency (Autoresponder imports `RolModel` from Usuarios), both directories can be deleted with `rm -rf`.

**Tech Stack:** PHP 8.x, PDO, legacy bootstrap autoloader (`bootstrap.php` maps `Modules\*` → `modules/`), `public/index.php` bridge.

**Key files:**
- `public/index.php` — bridge (lines 10–22): already includes `/usuarios`, `/roles`, `/protocolos`
- `modules/Usuarios/` — 14 files, 3.4k lines (to delete)
- `modules/EditorProtocolos/` — 11 files, 1.4k lines (to delete)
- `modules/Autoresponder/Controllers/AutoresponderController.php` — line 11: `use Modules\Usuarios\Models\RolModel;` (must fix before delete)
- `models/RolModel.php` — will be created (same pattern as `models/ExamenModel.php`)

---

## Task 1: Verificar bridge y deps de EditorProtocolos

**Files:**
- Read: `public/index.php`
- Read: `modules/EditorProtocolos/routes.php`
- Grep: toda la codebase

- [ ] **Step 1: Confirmar bridge cubre /protocolos**

```bash
grep "protocolos" public/index.php
```
Expected output includes: `'/protocolos'` en `$laravelBridgePrefixes`.

- [ ] **Step 2: Confirmar cero referencias cross-module a EditorProtocolos**

```bash
grep -r "Modules\\\\EditorProtocolos" modules/ --include="*.php" -l
grep -r "EditorProtocolos" modules/ --include="*.php" -l
grep -r "EditorProtocolos" laravel-app/ --include="*.php" -l
```
Expected: solo archivos dentro de `modules/EditorProtocolos/` mismo. Si aparece algo externo, documentarlo — no continuar hasta entender el impacto.

- [ ] **Step 3: Confirmar bridge cubre /usuarios y /roles**

```bash
grep "usuarios\|roles" public/index.php
```
Expected: `'/usuarios'` y `'/roles'` aparecen en `$laravelBridgePrefixes`. (No en `$laravelBridgeExact` — prefixes es correcto para rutas con subpaths.)

- [ ] **Step 4: Eliminar EditorProtocolos**

```bash
rm -rf modules/EditorProtocolos
```

- [ ] **Step 5: Verificar que bootstrap no lo menciona**

```bash
grep -r "EditorProtocolos" bootstrap.php 2>/dev/null || grep -r "EditorProtocolos" . --include="*.php" | grep -v ".git"
```
Expected: cero resultados.

- [ ] **Step 6: Smoke test del router**

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
Expected: `Router OK` sin errores. Si hay fatal errors, leer el mensaje y corregir.

- [ ] **Step 7: Commit**

```bash
git add -A modules/EditorProtocolos
git commit -m "$(cat <<'EOF'
feat(onda1): delete EditorProtocolos — dead code, bridge covers /protocolos

Routes /protocolos/* were already intercepted by the bridge in
public/index.php and served by Laravel. Zero cross-module dependencies.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Resolver dependencia — copiar RolModel a models/

El módulo Autoresponder importa `Modules\Usuarios\Models\RolModel`. Si borramos Usuarios sin resolver esto, Autoresponder fallará en runtime cuando se cargue esa clase. La solución es copiar `RolModel` al directorio `models/` raíz con namespace `Models` (idéntico al patrón usado con `ExamenModel` en la Onda de Examenes).

**Files:**
- Read: `modules/Usuarios/Models/RolModel.php`
- Create: `models/RolModel.php`
- Modify: `modules/Autoresponder/Controllers/AutoresponderController.php` (line 11)

- [ ] **Step 1: Crear models/RolModel.php**

```php
<?php

namespace Models;

use PDO;

class RolModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM roles ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        return $role ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (name, description, permissions) VALUES (:name, :description, :permissions)'
        );
        $stmt->execute([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE roles SET name = :name, description = :description, permissions = :permissions, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );

        return $stmt->execute([
            'id'          => $id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? null,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM roles WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
```

Save to `models/RolModel.php`. The bootstrap autoloader maps `Models\RolModel` → `models/RolModel.php` — no additional registration needed.

- [ ] **Step 2: Actualizar import en Autoresponder**

In `modules/Autoresponder/Controllers/AutoresponderController.php`, change line 11:

```php
// OLD:
use Modules\Usuarios\Models\RolModel;

// NEW:
use Models\RolModel;
```

- [ ] **Step 3: Verificar que no hay más referencias a Modules\Usuarios en Autoresponder**

```bash
grep -n "Modules\\\\Usuarios" modules/Autoresponder/Controllers/AutoresponderController.php
```
Expected: cero resultados.

- [ ] **Step 4: Verificar que Models\RolModel es accesible**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
\$model = new Models\RolModel(\$GLOBALS['pdo']);
\$roles = \$model->all();
echo 'RolModel OK, roles: ' . count(\$roles) . PHP_EOL;
"
```
Expected: `RolModel OK, roles: N` (N >= 0).

- [ ] **Step 5: Commit**

```bash
git add models/RolModel.php modules/Autoresponder/Controllers/AutoresponderController.php
git commit -m "$(cat <<'EOF'
refactor(onda1): move RolModel to models/ root to decouple Autoresponder from Usuarios

Autoresponder imported Modules\Usuarios\Models\RolModel. Copy moved to
models/RolModel.php with namespace Models to match bootstrap autoloader mapping.
Prerequisite for deleting modules/Usuarios/.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Verificar y eliminar modules/Usuarios/

**Files:**
- `modules/Usuarios/` — eliminar completo
- Grep: toda la codebase

- [ ] **Step 1: Confirmar cero referencias a Modules\Usuarios en todo el proyecto**

```bash
grep -r "Modules\\\\Usuarios" modules/ --include="*.php" -l
grep -r "Modules\\\\Usuarios" laravel-app/ --include="*.php" -l
grep -r "Modules\\\\Usuarios" controllers/ --include="*.php" -l 2>/dev/null
```
Expected: cero resultados en todos los comandos. Si hay algo, leer el archivo y resolverlo antes de continuar.

- [ ] **Step 2: Confirmar que bridge tiene /usuarios y /roles**

```bash
grep "laravelBridgePrefixes" public/index.php
```
Expected: el array incluye `'/usuarios'` y `'/roles'`.

- [ ] **Step 3: Eliminar Usuarios**

```bash
rm -rf modules/Usuarios
```

- [ ] **Step 4: Smoke test completo del router**

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
Expected: `Router OK` sin errores.

- [ ] **Step 5: Confirmar que Autoresponder carga sin errores (class resolution)**

```bash
php -r "
define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');
require_once 'bootstrap.php';
\$c = new Modules\Autoresponder\Controllers\AutoresponderController(\$GLOBALS['pdo']);
echo 'Autoresponder OK' . PHP_EOL;
"
```
Expected: `Autoresponder OK` sin errores. Si hay fatal, leer el mensaje — significa que hay otro import de Usuarios que se perdió.

- [ ] **Step 6: Commit final**

```bash
git add -A modules/Usuarios
git commit -m "$(cat <<'EOF'
feat(onda1): delete Usuarios — dead code, bridge covers /usuarios and /roles

Routes /usuarios/* and /roles/* were already intercepted by the bridge in
public/index.php and served by Laravel. RolModel moved to models/ root in
previous commit to decouple Autoresponder. Zero remaining dependencies.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

- `modules/Usuarios/` — eliminado ✅
- `modules/EditorProtocolos/` — eliminado ✅
- `models/RolModel.php` — creado con namespace `Models` ✅
- `modules/Autoresponder/Controllers/AutoresponderController.php` — import actualizado ✅
- Módulos legacy restantes: 24 → 22 (–2)
- Líneas eliminadas: ~4.4k

**Siguiente:** Onda 2 → `docs/superpowers/plans/2026-05-21-onda2-quick-completions.md`
