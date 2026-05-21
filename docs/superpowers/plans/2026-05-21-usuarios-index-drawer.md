# Usuarios Index + Drawer — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Spec:** `docs/superpowers/specs/2026-05-21-usuarios-index-drawer-design.md`

**Goal:** Rediseñar `/usuarios` — tabla de 5 columnas + drawer lateral de edición rápida (rol + permisos con herencia), sin accordion de privilegios, sin `confirm()` nativo, sin CSS inline.

**Architecture:** La vista `v2-index.blade.php` incluye el HTML del drawer y un modal de confirmación de eliminación. El JS (`usuarios-index.js`) maneja todo el comportamiento cliente: abrir/cerrar drawer, focus trap, Escape, ordenación de tabla, filtros, preview de herencia de permisos al cambiar rol, y modal de eliminación. El controlador deja de pasar `privilegedSummary` y agrega `rolesWithPermissions` (mapa roleId → array de permission keys) para que el JS pueda calcular herencia. El POST al guardar usa el endpoint existente `/usuarios/{id}` — sin cambios de backend.

**Tech Stack:** PHP 8.x, Laravel 11, Blade, Vanilla JS (ES2020 IIFE), Vite (ya configurado), Bootstrap 5 (clases ya en uso en el proyecto).

---

## File Map

| Archivo | Acción | Responsabilidad |
|---------|--------|-----------------|
| `laravel-app/resources/js/v2/usuarios-index.js` | **Crear** | Drawer, sort, filtros, herencia, modal delete |
| `laravel-app/vite.config.js` | **Modificar** | Registrar el nuevo entry point |
| `laravel-app/resources/views/usuarios/v2-index.blade.php` | **Modificar** | Tabla 5 cols + drawer HTML + modal HTML |
| `laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php` | **Modificar** | Quitar `privilegedSummary`, agregar `rolesWithPermissions` |

---

## Task 1: Crear usuarios-index.js y registrarlo en Vite

**Files:**
- Create: `laravel-app/resources/js/v2/usuarios-index.js`
- Modify: `laravel-app/vite.config.js`

- [ ] **Step 1: Crear el archivo JS con skeleton vacío**

Crea `laravel-app/resources/js/v2/usuarios-index.js`:

```js
(function () {
    'use strict';

    // Populated by v2-index.blade.php via window.__USUARIOS_INDEX__
    const CONFIG = window.__USUARIOS_INDEX__ || {};
    const permissionGroups   = CONFIG.permissionGroups   || {};
    const rolesWithPermissions = CONFIG.rolesWithPermissions || {};
    const currentUserId      = CONFIG.currentUserId      || 0;
    const canManageUsers     = CONFIG.canManageUsers     || false;

    document.addEventListener('DOMContentLoaded', function () {
        initFilters();
        initSort();
        initDrawer();
        initDeleteModal();
    });

    // ─── FILTERS ─────────────────────────────────────────────────────────────

    function initFilters() {
        const buscar       = document.getElementById('uf-buscar');
        const especialidad = document.getElementById('uf-especialidad');
        const rol          = document.getElementById('uf-rol');
        const estado       = document.getElementById('uf-estado');
        const limpiar      = document.getElementById('uf-limpiar');
        const count        = document.getElementById('uf-count');
        const rows         = Array.from(document.querySelectorAll('tbody tr[data-search]'));

        function apply() {
            const q   = buscar      ? buscar.value.trim().toLowerCase()      : '';
            const esp = especialidad ? especialidad.value.trim().toLowerCase() : '';
            const r   = rol         ? rol.value.trim()                        : '';
            const est = estado      ? estado.value.trim()                     : '';

            let shown = 0;
            rows.forEach(function (row) {
                let ok = true;
                if (q   && !(row.dataset.search      || '').includes(q))   ok = false;
                if (ok && esp && (row.dataset.especialidad || '').toLowerCase() !== esp) ok = false;
                if (ok && r   && (row.dataset.roleId      || '') !== r)    ok = false;
                if (ok && est === 'approved' && row.dataset.approved !== '1') ok = false;
                if (ok && est === 'pending'  && row.dataset.approved === '1') ok = false;

                row.classList.toggle('d-none', !ok);
                if (ok) shown++;
            });

            if (count) count.textContent = shown + ' usuario' + (shown !== 1 ? 's' : '');
        }

        if (buscar)       buscar.addEventListener('input', apply);
        if (especialidad) especialidad.addEventListener('change', apply);
        if (rol)          rol.addEventListener('change', apply);
        if (estado)       estado.addEventListener('change', apply);
        if (limpiar) {
            limpiar.addEventListener('click', function () {
                if (buscar)       buscar.value = '';
                if (especialidad) especialidad.value = '';
                if (rol)          rol.value = '';
                if (estado)       estado.value = '';
                apply();
            });
        }

        apply();
    }

    // ─── SORT ────────────────────────────────────────────────────────────────

    function initSort() {
        const table   = document.querySelector('.usuarios-table');
        if (!table) return;

        const headers  = table.querySelectorAll('thead th[data-sort]');
        const collator = new Intl.Collator('es', { sensitivity: 'base' });

        headers.forEach(function (th) {
            th.style.cursor = 'pointer';
            th.setAttribute('aria-sort', 'none');

            th.addEventListener('click', function () {
                const current  = th.getAttribute('aria-sort');
                const dir      = current === 'ascending' ? 'descending' : 'ascending';
                const colIndex = Array.prototype.indexOf.call(th.parentElement.children, th);
                const tbody    = table.querySelector('tbody');
                if (!tbody) return;

                headers.forEach(function (h) { h.setAttribute('aria-sort', 'none'); });
                th.setAttribute('aria-sort', dir);

                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort(function (a, b) {
                    const cellA = a.children[colIndex];
                    const cellB = b.children[colIndex];
                    const va = cellA ? (cellA.dataset.sortValue || cellA.textContent || '').trim() : '';
                    const vb = cellB ? (cellB.dataset.sortValue || cellB.textContent || '').trim() : '';
                    const cmp = collator.compare(va, vb);
                    return dir === 'ascending' ? cmp : -cmp;
                });
                rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    }

    // ─── DRAWER ──────────────────────────────────────────────────────────────

    let activeRow = null;

    function initDrawer() {
        const drawer   = document.getElementById('usuarios-drawer');
        const closeBtn = document.getElementById('usuarios-drawer-close');
        if (!drawer) return;

        // Click on any row cell (except action buttons) opens drawer
        document.querySelectorAll('tbody tr[data-user]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('button, a, form')) return;
                openDrawer(row, drawer);
            });
            // Also wire the "Editar" button in the row
            const editBtn = row.querySelector('.row-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openDrawer(row, drawer);
                });
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () { closeDrawer(drawer); });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !drawer.classList.contains('d-none')) {
                closeDrawer(drawer);
            }
        });

        // Tab "Acceso" / "Actividad"
        drawer.querySelectorAll('.drawer-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.dataset.tab;
                drawer.querySelectorAll('.drawer-tab-btn').forEach(function (b) {
                    b.classList.toggle('active', b.dataset.tab === target);
                });
                drawer.querySelectorAll('.drawer-tab-panel').forEach(function (p) {
                    p.classList.toggle('d-none', p.dataset.tab !== target);
                });
            });
        });

        // Role select change → update inherited preview
        const roleSelect = document.getElementById('drawer-role-id');
        if (roleSelect) {
            roleSelect.addEventListener('change', function () {
                updateInheritedPreview(drawer, roleSelect.value);
            });
        }

        // Permission profile template
        const profileSelect = document.getElementById('drawer-permission-profile');
        const profileApply  = document.getElementById('drawer-profile-apply');
        if (profileApply && profileSelect) {
            profileApply.addEventListener('click', function () {
                applyPermissionProfile(drawer, profileSelect.value);
            });
        }

        // Accordion groups
        drawer.querySelectorAll('.perm-group-head').forEach(function (head) {
            head.addEventListener('click', function () {
                const body = head.nextElementSibling;
                if (!body) return;
                const isOpen = !body.classList.contains('d-none');
                body.classList.toggle('d-none', isOpen);
                head.setAttribute('aria-expanded', String(!isOpen));
            });
        });
    }

    function openDrawer(row, drawer) {
        const userData = JSON.parse(row.dataset.user || '{}');

        // Mark active row
        if (activeRow) activeRow.classList.remove('table-active');
        activeRow = row;
        row.classList.add('table-active');

        // Populate header
        const avatarEl = drawer.querySelector('.drawer-avatar');
        const nameEl   = drawer.querySelector('.drawer-user-name');
        const metaEl   = drawer.querySelector('.drawer-user-meta');
        const linkEl   = drawer.querySelector('.drawer-profile-link');
        const deleteBtn = drawer.querySelector('.drawer-delete-btn');

        if (avatarEl) {
            const initial = (userData.display_full_name || userData.username || 'U').charAt(0).toUpperCase();
            avatarEl.textContent = initial;
            if (userData.profile_photo_url) {
                avatarEl.innerHTML = '<img src="' + userData.profile_photo_url + '" alt="" class="w-100 h-100 rounded" style="object-fit:cover">';
            }
        }
        if (nameEl) nameEl.textContent = userData.display_full_name || userData.username;
        if (metaEl) metaEl.textContent = (userData.username || '') + ' · ' + (userData.especialidad || '');
        if (linkEl) linkEl.href = '/usuarios/' + userData.id + '/edit';
        if (deleteBtn) {
            deleteBtn.dataset.userId   = userData.id;
            deleteBtn.dataset.username = userData.username;
            deleteBtn.disabled = (userData.id === currentUserId);
        }

        // Populate role select
        const roleSelect = document.getElementById('drawer-role-id');
        if (roleSelect) roleSelect.value = String(userData.role_id || '');

        // Populate form action
        const drawerForm = drawer.querySelector('form.drawer-form');
        if (drawerForm) drawerForm.action = '/usuarios/' + userData.id;

        // Populate permissions
        populatePermissions(drawer, userData);

        // Show drawer (remove d-none on the panel)
        drawer.classList.remove('d-none');
        drawer.removeAttribute('hidden');

        // Focus close button for keyboard users
        const closeBtn = document.getElementById('usuarios-drawer-close');
        if (closeBtn) closeBtn.focus();
    }

    function closeDrawer(drawer) {
        drawer.classList.add('d-none');
        drawer.setAttribute('hidden', '');
        if (activeRow) {
            activeRow.classList.remove('table-active');
            activeRow.focus();
            activeRow = null;
        }
    }

    function populatePermissions(drawer, userData) {
        const directPerms = new Set(userData.permisos_lista || []);
        const roleId      = String(userData.role_id || '');
        const rolePerms   = new Set(rolesWithPermissions[roleId] || []);

        drawer.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            const key       = cb.value;
            const isDirect  = directPerms.has(key);
            const inherited = !isDirect && rolePerms.has(key);

            cb.checked  = isDirect || inherited;
            cb.disabled = inherited;

            const inheritedTag = cb.closest('.perm-check') && cb.closest('.perm-check').querySelector('.inherited-tag');
            if (inheritedTag) inheritedTag.classList.toggle('d-none', !inherited);
        });
    }

    function updateInheritedPreview(drawer, newRoleId) {
        const rolePerms = new Set(rolesWithPermissions[String(newRoleId)] || []);

        drawer.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            // Only change inherited state — don't uncheck direct permissions
            if (cb.dataset.direct === '1') return;

            const inherited = rolePerms.has(cb.value);
            cb.checked  = inherited;
            cb.disabled = inherited;

            const inheritedTag = cb.closest('.perm-check') && cb.closest('.perm-check').querySelector('.inherited-tag');
            if (inheritedTag) inheritedTag.classList.toggle('d-none', !inherited);
        });
    }

    function applyPermissionProfile(drawer, profileKey) {
        const profiles = (window.__USUARIOS_INDEX__ || {}).permissionProfiles || {};
        const profile  = profiles[profileKey];
        if (!profile || !Array.isArray(profile.permissions)) return;

        const selected = new Set(profile.permissions);
        drawer.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            if (!cb.disabled) {
                cb.checked = selected.has(cb.value);
            }
        });
    }

    // ─── DELETE MODAL ────────────────────────────────────────────────────────

    function initDeleteModal() {
        const modal       = document.getElementById('delete-user-modal');
        const cancelBtn   = document.getElementById('delete-modal-cancel');
        const confirmForm = document.getElementById('delete-modal-form');
        const userLabel   = document.getElementById('delete-modal-username');
        if (!modal) return;

        document.addEventListener('click', function (e) {
            const deleteBtn = e.target.closest('.drawer-delete-btn');
            if (!deleteBtn) return;

            const userId   = deleteBtn.dataset.userId;
            const username = deleteBtn.dataset.username || 'este usuario';

            if (userLabel)   userLabel.textContent = username;
            if (confirmForm) confirmForm.action = '/usuarios/' + userId + '/delete';

            modal.classList.remove('d-none');
            modal.removeAttribute('hidden');
            if (cancelBtn) cancelBtn.focus();
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                modal.classList.add('d-none');
                modal.setAttribute('hidden', '');
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('d-none')) {
                modal.classList.add('d-none');
                modal.setAttribute('hidden', '');
            }
        });
    }

})();
```

- [ ] **Step 2: Registrar en vite.config.js**

En `laravel-app/vite.config.js`, agrega la línea dentro del array `input`:

```js
// Después de 'resources/js/v2/user-edit.js':
'resources/js/v2/usuarios-index.js',
```

El bloque input queda así (fragmento relevante):
```js
'resources/js/v2/user-edit.js',
'resources/js/v2/usuarios-index.js',
'resources/js/v2/settings-index.js',
```

- [ ] **Step 3: Verificar que Vite compila sin error**

```bash
cd laravel-app && npm run build 2>&1 | tail -20
```

Expected: `✓ built in Xs` sin errores. Si hay error de sintaxis JS, corregirlo.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/resources/js/v2/usuarios-index.js laravel-app/vite.config.js
git commit -m "$(cat <<'EOF'
feat(usuarios): add usuarios-index.js — drawer, sort, filters, delete modal

Vanilla JS IIFE for the /usuarios redesign. Handles drawer open/close/Escape,
focus management, permission inheritance preview on role change, accordion groups,
client-side sort, real-time filters, and delete confirmation modal.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Actualizar UsuariosUiController::index()

**Files:**
- Modify: `laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php`

- [ ] **Step 1: Agregar método privado fetchRolesWithPermissions()**

Busca el método `fetchRoles()` (cerca de la línea 440). Agrega justo después de él:

```php
/**
 * @return array<string, list<string>>  roleId (string) → list of permission keys
 */
private function fetchRolesWithPermissions(): array
{
    $rows = DB::table('roles')->select(['id', 'permissions'])->get();
    $map  = [];

    foreach ($rows as $row) {
        $perms = LegacyPermissionCatalog::normalize($row->permissions ?? []);
        $map[(string) $row->id] = $perms;
    }

    return $map;
}
```

- [ ] **Step 2: Actualizar index() — quitar privilegedSummary, agregar rolesWithPermissions**

En el método `index()` (línea ~75), localiza el bloque `return view('usuarios.v2-index', [...])`.

Elimina la línea:
```php
'privilegedSummary' => $this->buildPrivilegedUsersSummary($users),
```

Agrega en su lugar:
```php
'rolesWithPermissions' => $this->fetchRolesWithPermissions(),
```

El bloque completo de datos al view queda:
```php
return view('usuarios.v2-index', [
    'pageTitle'           => 'Usuarios',
    'currentUser'         => LegacyCurrentUser::resolve($request),
    'users'               => $users,
    'roleMap'             => $roleMap,
    'permissionLabels'    => LegacyPermissionCatalog::all(),
    'status'              => session('status'),
    'warnings'            => session('warnings', []),
    'canManageUsers'      => LegacyPermissionCatalog::containsAny(
        LegacyPermissionResolver::resolve($request),
        ['administrativo', 'admin.usuarios.manage', 'admin.usuarios']
    ),
    'currentUserId'       => $this->currentUserId(),
    'rolesWithPermissions' => $this->fetchRolesWithPermissions(),
    'permissionGroups'    => LegacyPermissionCatalog::groups(),
    'permissionProfiles'  => config('permission_profiles', []),
]);
```

- [ ] **Step 3: Smoke test del router**

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

Expected: `Router OK`

- [ ] **Step 4: Verificar que la ruta Laravel sigue respondiendo (Laravel router)**

```bash
cd laravel-app && php artisan route:list --path=usuarios 2>&1 | head -15
```

Expected: lista las rutas GET/POST de `/usuarios`.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php
git commit -m "$(cat <<'EOF'
refactor(usuarios): replace privilegedSummary with rolesWithPermissions in index()

Removes the accordion summary data (eliminated from UI) and adds
rolesWithPermissions + permissionGroups for drawer permission inheritance
preview. No route or endpoint changes.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Refactorizar v2-index.blade.php

**Files:**
- Modify: `laravel-app/resources/views/usuarios/v2-index.blade.php`

Reemplaza el contenido completo del archivo con la versión siguiente. Lee el archivo original primero para no perder la lógica de `$especialidadesFiltro` ni el mapa de usuarios.

- [ ] **Step 1: Escribir el nuevo v2-index.blade.php**

```blade
@php
    /** @var array<int, array<string, mixed>> $users */
    /** @var array<int, string> $roleMap */
    /** @var array<string, list<string>> $rolesWithPermissions */
    /** @var array<string, array<string, string>> $permissionGroups */
    /** @var array<string, mixed> $permissionProfiles */

    $users              = $users ?? [];
    $roleMap            = $roleMap ?? [];
    $rolesWithPermissions = $rolesWithPermissions ?? [];
    $permissionGroups   = $permissionGroups ?? [];
    $permissionProfiles = $permissionProfiles ?? [];
    $warnings           = $warnings ?? [];
    $canManageUsers     = !empty($canManageUsers);
    $currentUserId      = isset($currentUserId) ? (int) $currentUserId : 0;

    $especialidadesFiltro = [
        '' => 'Todas',
        'Cirujano Oftalmólogo' => 'Cirujano Oftalmólogo',
        'Residente' => 'Residente',
        'Anestesiologo' => 'Anestesiólogo',
        'Asistente' => 'Asistente',
        'Optometrista' => 'Optometrista',
        'Enfermera' => 'Enfermera',
        'Administrativo' => 'Administrativo',
        'Facturación' => 'Facturación',
        'Sistemas' => 'Sistemas',
        'Coordinación Quirúrgica' => 'Coordinación Quirúrgica',
        'Admisión' => 'Admisión',
        'Imagenología' => 'Imagenología',
    ];
@endphp

@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Usuarios</h3>
                <nav><ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
                </ol></nav>
            </div>
            <div class="d-flex gap-2">
                <a href="/roles" class="btn btn-outline-primary btn-sm">Administrar roles</a>
                @if($canManageUsers)
                    <a href="/usuarios/create" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-account-plus"></i> Nuevo usuario
                    </a>
                @endif
            </div>
        </div>
    </div>

    <section class="content">
        {{-- Flash messages --}}
        @if(($status ?? null) === 'created')
            <div class="alert alert-success alert-dismissible fade show">Usuario creado correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @elseif(($status ?? null) === 'updated')
            <div class="alert alert-success alert-dismissible fade show">Usuario actualizado correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @elseif(($status ?? null) === 'deleted')
            <div class="alert alert-success alert-dismissible fade show">Usuario eliminado correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @elseif(($status ?? null) === 'not_found')
            <div class="alert alert-warning">No se encontró el usuario solicitado.</div>
        @elseif(($status ?? null) === 'cannot_delete_self')
            <div class="alert alert-danger">No puedes eliminar tu propio usuario.</div>
        @endif

        @if($warnings !== [])
            <div class="alert alert-warning">
                <p class="mb-2 fw-semibold"><i class="mdi mdi-alert"></i> Avisos:</p>
                <ul class="mb-0 ps-3">@foreach($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Layout: tabla + drawer side by side --}}
        <div class="d-flex gap-0 align-items-start" id="usuarios-layout">

            {{-- ─── PANEL PRINCIPAL ─── --}}
            <div class="flex-grow-1 min-w-0">

                {{-- Filtros --}}
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label small fw-semibold mb-1" for="uf-buscar">Buscar</label>
                                <input type="text" id="uf-buscar" class="form-control form-control-sm" placeholder="Nombre, usuario, correo…">
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label small fw-semibold mb-1" for="uf-especialidad">Especialidad</label>
                                <select id="uf-especialidad" class="form-select form-select-sm">
                                    @foreach($especialidadesFiltro as $val => $lbl)
                                        <option value="{{ mb_strtolower($val, 'UTF-8') }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label small fw-semibold mb-1" for="uf-rol">Rol</label>
                                <select id="uf-rol" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    @foreach($roleMap as $roleId => $roleName)
                                        <option value="{{ $roleId }}">{{ $roleName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label small fw-semibold mb-1" for="uf-estado">Estado</label>
                                <select id="uf-estado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="approved">Aprobado</option>
                                    <option value="pending">Pendiente</option>
                                </select>
                            </div>
                            <div class="col-lg-1 d-flex gap-2 align-items-center">
                                <button type="button" id="uf-limpiar" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                    <i class="mdi mdi-filter-remove"></i>
                                </button>
                                <span class="badge bg-light text-dark border" id="uf-count">0 usuarios</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabla --}}
                <div class="box">
                    <div class="box-body table-responsive">
                        <table class="table table-hover align-middle mb-0 usuarios-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width:44px"></th>
                                    <th scope="col" data-sort="name" aria-sort="none">
                                        Nombre
                                        <svg class="ms-1" width="10" height="10" viewBox="0 0 10 14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 1v12M1 9l4 4 4-4M1 5l4-4 4 4" opacity=".4"/></svg>
                                    </th>
                                    <th scope="col" data-sort="especialidad" aria-sort="none">
                                        Especialidad
                                        <svg class="ms-1" width="10" height="10" viewBox="0 0 10 14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 1v12M1 9l4 4 4-4M1 5l4-4 4 4" opacity=".4"/></svg>
                                    </th>
                                    <th scope="col">Rol</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" style="width:80px"></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                @php
                                    $userId       = (int) ($user['id'] ?? 0);
                                    $username     = (string) ($user['username'] ?? '');
                                    $displayName  = trim((string) ($user['display_full_name'] ?? $username));
                                    $initial      = mb_strtoupper(mb_substr($displayName ?: 'U', 0, 1, 'UTF-8'), 'UTF-8');
                                    $especialidad = trim((string) ($user['especialidad'] ?? ''));
                                    $roleId       = (int) ($user['role_id'] ?? 0);
                                    $isApproved   = !empty($user['is_approved']);
                                    $directPerms  = $user['permisos_lista'] ?? [];
                                    $nameSort     = mb_strtolower(trim(implode(' ', array_filter([
                                        $user['last_name'] ?? '',
                                        $user['first_name'] ?? '',
                                        $displayName,
                                    ], static fn($v): bool => (string)$v !== ''))), 'UTF-8');
                                    $searchIndex  = mb_strtolower(implode(' ', array_filter([
                                        $username, $displayName,
                                        (string) ($user['email'] ?? ''),
                                        $especialidad,
                                        $user['role_label'] ?? '',
                                    ], static fn($v): bool => (string)$v !== '')), 'UTF-8');

                                    // JSON payload for the drawer
                                    $userJson = json_encode([
                                        'id'                => $userId,
                                        'username'          => $username,
                                        'display_full_name' => $displayName,
                                        'especialidad'      => $especialidad,
                                        'role_id'           => $roleId,
                                        'role_label'        => $user['role_label'] ?? 'Sin asignar',
                                        'is_approved'       => $isApproved,
                                        'permisos_lista'    => $directPerms,
                                        'profile_photo_url' => $user['profile_photo_url'] ?? null,
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                                @endphp
                                <tr
                                    tabindex="0"
                                    role="button"
                                    aria-label="Editar usuario {{ $displayName }}"
                                    data-user="{{ $userJson }}"
                                    data-search="{{ $searchIndex }}"
                                    data-especialidad="{{ mb_strtolower($especialidad, 'UTF-8') }}"
                                    data-role-id="{{ $roleId }}"
                                    data-approved="{{ $isApproved ? '1' : '0' }}"
                                >
                                    <td>
                                        @if(!empty($user['profile_photo_url']))
                                            <img src="{{ $user['profile_photo_url'] }}" alt=""
                                                 width="32" height="32"
                                                 class="rounded-2" style="object-fit:cover">
                                        @else
                                            <span class="avatar avatar-sm rounded-2 d-inline-flex align-items-center justify-content-center bg-secondary text-white fw-bold"
                                                  style="width:32px;height:32px;font-size:12px">{{ $initial }}</span>
                                        @endif
                                    </td>
                                    <td data-sort-value="{{ $nameSort }}">
                                        <div class="fw-semibold lh-sm">{{ $displayName ?: '—' }}</div>
                                        <div class="text-muted small">{{ $username }}</div>
                                    </td>
                                    <td data-sort-value="{{ mb_strtolower($especialidad, 'UTF-8') }}">
                                        {{ $especialidad ?: '—' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $user['role_label'] ?? 'Sin asignar' }}</span>
                                    </td>
                                    <td>
                                        @if($isApproved)
                                            <span class="badge bg-success-subtle text-success">Aprobado</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary row-edit-btn"
                                                aria-label="Editar {{ $displayName }}">
                                            <i class="mdi mdi-pencil"></i> Editar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">No hay usuarios registrados.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>{{-- /panel principal --}}

            {{-- ─── DRAWER ─── --}}
            <div id="usuarios-drawer"
                 class="d-none ms-3 bg-white border rounded shadow-sm"
                 style="width:340px;flex-shrink:0;max-height:calc(100vh - 120px);overflow-y:auto;position:sticky;top:16px"
                 hidden
                 aria-label="Panel de edición de usuario">

                {{-- Header --}}
                <div class="d-flex align-items-center gap-2 p-3 border-bottom">
                    <span class="drawer-avatar avatar rounded-2 d-inline-flex align-items-center justify-content-center bg-secondary text-white fw-bold"
                          style="width:36px;height:36px;font-size:13px;flex-shrink:0">?</span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="drawer-user-name fw-semibold text-truncate">—</div>
                        <div class="drawer-user-meta small text-muted text-truncate">—</div>
                    </div>
                    <button type="button" id="usuarios-drawer-close"
                            class="btn btn-sm btn-outline-secondary"
                            aria-label="Cerrar panel">✕</button>
                </div>

                {{-- Tabs --}}
                <div class="d-flex border-bottom">
                    <button type="button" class="drawer-tab-btn btn btn-link text-decoration-none flex-fill py-2 fw-semibold small active border-bottom border-2 border-primary rounded-0"
                            data-tab="acceso" aria-selected="true">Acceso</button>
                    <button type="button" class="drawer-tab-btn btn btn-link text-decoration-none text-muted flex-fill py-2 small rounded-0"
                            data-tab="actividad" aria-selected="false">Actividad</button>
                </div>

                {{-- Tab: Acceso --}}
                <div class="drawer-tab-panel p-3" data-tab="acceso">
                    <form class="drawer-form" method="POST" action="#">
                        @csrf

                        {{-- Rol --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="drawer-role-id">Rol asignado</label>
                            <select id="drawer-role-id" name="role_id" class="form-select form-select-sm">
                                <option value="0">Sin rol</option>
                                @foreach($roleMap as $rid => $rname)
                                    <option value="{{ $rid }}">{{ $rname }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Plantilla rápida --}}
                        @if(!empty($permissionProfiles))
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="drawer-permission-profile">Plantilla rápida</label>
                            <div class="input-group input-group-sm">
                                <select id="drawer-permission-profile" class="form-select form-select-sm">
                                    <option value="">Selecciona plantilla…</option>
                                    @foreach($permissionProfiles as $pk => $profile)
                                        <option value="{{ $pk }}">{{ $profile['label'] ?? $pk }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="drawer-profile-apply" class="btn btn-outline-primary btn-sm">Aplicar</button>
                            </div>
                        </div>
                        @endif

                        {{-- Leyenda herencia --}}
                        <div class="d-flex gap-3 mb-2 small text-muted">
                            <span><span class="badge bg-primary" style="font-size:8px">■</span> Directo</span>
                            <span><span class="badge border text-primary" style="font-size:8px;background:transparent">■</span> Heredado del rol</span>
                        </div>

                        {{-- Grupos de permisos (acordeón) --}}
                        <div class="mb-3">
                            <div class="small fw-semibold text-uppercase text-muted mb-2" style="letter-spacing:.05em">Permisos directos</div>
                            @foreach($permissionGroups as $groupName => $groupPerms)
                                <div class="perm-group border rounded mb-2">
                                    <button type="button"
                                            class="perm-group-head w-100 d-flex justify-content-between align-items-center px-2 py-1 bg-light border-0 text-start small fw-semibold"
                                            aria-expanded="false">
                                        <span>{{ $groupName }}</span>
                                        <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
                                    </button>
                                    <div class="perm-group-body d-none px-2 py-2">
                                        <div class="row g-1">
                                            @foreach($groupPerms as $permKey => $permLabel)
                                                <div class="col-6">
                                                    <label class="perm-check d-flex align-items-start gap-1 small">
                                                        <input type="checkbox"
                                                               name="permissions[]"
                                                               value="{{ $permKey }}"
                                                               class="form-check-input mt-0"
                                                               data-direct="0">
                                                        <span>
                                                            {{ $permLabel }}
                                                            <span class="inherited-tag badge bg-primary-subtle text-primary d-none" style="font-size:8px">rol</span>
                                                        </span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Link perfil completo --}}
                        <a href="#" class="drawer-profile-link d-flex justify-content-between align-items-center p-2 bg-light border rounded text-decoration-none text-secondary small mb-3">
                            <span><i class="mdi mdi-account-edit"></i> Editar perfil completo</span>
                            <span>↗</span>
                        </a>

                        {{-- Footer --}}
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Guardar cambios</button>
                            @if($canManageUsers)
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm drawer-delete-btn"
                                        data-user-id=""
                                        data-username="">
                                    <i class="mdi mdi-delete"></i>
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Tab: Actividad --}}
                <div class="drawer-tab-panel d-none p-3" data-tab="actividad">
                    <p class="text-muted small text-center py-4">Historial de actividad próximamente.</p>
                </div>
            </div>{{-- /drawer --}}

        </div>{{-- /usuarios-layout --}}

        {{-- ─── MODAL ELIMINACIÓN ─── --}}
        <div id="delete-user-modal"
             class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
             style="z-index:1050;background:rgba(0,0,0,.5)"
             hidden
             role="dialog"
             aria-modal="true"
             aria-labelledby="delete-modal-title">
            <div class="bg-white rounded shadow p-4" style="max-width:400px;width:100%">
                <h5 id="delete-modal-title" class="mb-2">Eliminar usuario</h5>
                <p class="text-muted mb-4">
                    ¿Deseas eliminar a <strong id="delete-modal-username">—</strong>?
                    Esta acción no se puede deshacer.
                </p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" id="delete-modal-cancel" class="btn btn-outline-secondary">Cancelar</button>
                    <form id="delete-modal-form" method="POST" action="#">
                        @csrf
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>

    </section>
@endsection

@push('scripts')
    <script>
        window.__USUARIOS_INDEX__ = {
            permissionGroups:    @json($permissionGroups),
            rolesWithPermissions: @json($rolesWithPermissions),
            permissionProfiles:  @json($permissionProfiles),
            currentUserId:       @json($currentUserId),
            canManageUsers:      @json($canManageUsers),
        };
    </script>
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/usuarios-index.js')
    @else
        <script src="/js/v2/usuarios-index.js"></script>
    @endif
@endpush
```

- [ ] **Step 2: Verificar que la vista tiene sintaxis Blade válida**

```bash
cd laravel-app && php artisan view:cache 2>&1 | tail -5
```

Expected: sin errores de compilación Blade. Si hay error, leer el mensaje de línea y corregirlo.

- [ ] **Step 3: Commit**

```bash
git add laravel-app/resources/views/usuarios/v2-index.blade.php
git commit -m "$(cat <<'EOF'
feat(usuarios): redesign v2-index — 5-col table + drawer + delete modal

Removes 9-col table, privileged summary accordion, and inline CSS.
New layout: compact 5-col table (avatar/nombre/especialidad/rol/estado)
plus lateral drawer panel with role select, permission accordion, and
inherited-permission markers. Delete confirmation uses custom modal.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Smoke test manual en el navegador

No hay suite de tests automatizados para esta vista. Verificar el flujo completo manualmente.

- [ ] **Step 1: Arrancar el servidor local**

```bash
cd laravel-app && php artisan serve --port=8000 2>&1 &
```

Si el servidor ya está corriendo, omitir este paso.

- [ ] **Step 2: Verificar lista de usuarios**

Abrir `http://localhost:8000/usuarios`.

Checklist visual:
- [ ] La tabla muestra 5 columnas: avatar, nombre+handle, especialidad, rol, estado
- [ ] No aparece el accordion de Superusuarios / Acceso administrativo / Accesos totales
- [ ] Los filtros de buscar / especialidad / rol / estado funcionan en tiempo real
- [ ] El contador "N usuarios" se actualiza al filtrar
- [ ] Ordenar por Nombre funciona (clic en columna)
- [ ] Ordenar por Especialidad funciona

- [ ] **Step 3: Verificar drawer**

- [ ] Clic en cualquier fila → drawer abre a la derecha
- [ ] Header del drawer muestra nombre, username y especialidad del usuario
- [ ] Select de rol tiene el rol actual preseleccionado
- [ ] Los grupos de permisos aparecen en acordeón cerrado
- [ ] Clic en cabecera de grupo lo expande
- [ ] Los permisos heredados del rol aparecen con el tag "rol" y deshabilitados
- [ ] Al cambiar el rol en el select → los permisos heredados se actualizan sin recargar
- [ ] Botón ✕ cierra el drawer
- [ ] Tecla Escape cierra el drawer
- [ ] "Editar perfil completo ↗" lleva a `/usuarios/{id}/edit`

- [ ] **Step 4: Verificar guardar desde drawer**

- [ ] Cambiar el rol de un usuario y hacer clic en "Guardar cambios"
- [ ] La página recarga con flash "Usuario actualizado correctamente"
- [ ] El usuario aparece con el nuevo rol en la tabla

- [ ] **Step 5: Verificar modal de eliminación**

- [ ] En el drawer, clic en el ícono de eliminar (papelera)
- [ ] Aparece el modal con el nombre del usuario
- [ ] "Cancelar" cierra el modal sin eliminar
- [ ] Escape cierra el modal sin eliminar
- [ ] El botón rojo "Eliminar" dispara el POST a `/usuarios/{id}/delete`

- [ ] **Step 6: Verificar que el botón "Eliminar" del propio usuario está deshabilitado**

Iniciar sesión con el usuario administrador actual e ir a `/usuarios`. Abrir el drawer del usuario actual. El botón de eliminar debe aparecer deshabilitado.

- [ ] **Step 7: Commit de verificación**

```bash
git commit --allow-empty -m "$(cat <<'EOF'
test(usuarios): smoke test passed — index drawer, filters, delete modal

Manual verification: 5-col table renders, drawer opens/closes with
Escape and ✕, role change updates inherited permissions, save works,
delete modal replaces confirm(), self-delete is disabled.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Resultado esperado

- `/usuarios` muestra tabla limpia de 5 columnas sin accordion de privilegios
- Click en fila → drawer lateral con rol + permisos en acordeón
- Permisos heredados del rol visibles como deshabilitados con tag "rol"
- Cambio de rol en drawer → herencia actualizada en tiempo real
- Guardar desde drawer hace POST tradicional al endpoint existente
- Eliminar usa modal propio, no `confirm()`
- Sin CSS inline en el template
- `usuarios-index.js` compilado por Vite

**Siguiente:** Onda 2 del legacy-zero roadmap → `docs/superpowers/plans/2026-05-21-onda2-quick-completions.md`
