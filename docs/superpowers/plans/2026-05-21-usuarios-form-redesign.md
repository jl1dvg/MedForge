# Usuarios Form Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `v2-form.blade.php` with a three-tab layout (Perfil / Acceso / Documentos), visible inherited-permission states, and a styled delete-confirmation modal.

**Architecture:** A single `<form>` wraps all three tab panels — tabs control visibility only, not form structure. JS handles tab switching, inherited-permission marking on load and on role change, accordion for permission groups, and the delete modal. The controller gains `rolesWithPermissions` in its payload so JS can compute inherited vs. direct permissions.

**Tech Stack:** Laravel Blade, plain IIFE JavaScript (no bundled deps), Bootstrap 5 utilities, `usuarios.css` (custom), Vite pipeline already configured.

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php` | Modify | Add `rolesWithPermissions` to `renderForm()` return array |
| `laravel-app/resources/views/usuarios/v2-edit.blade.php` | Delete | Orphan file — never rendered |
| `laravel-app/resources/css/usuarios.css` | Extend | Form header, tab bar, tab panels, section labels, field grid, upload grid, footer |
| `laravel-app/resources/js/v2/user-edit.js` | Rewrite | Tabs, inherited perms, role change, accordion, delete modal, profile apply, full-name, subespecialidad |
| `laravel-app/resources/views/usuarios/v2-form.blade.php` | Rewrite | Three-tab layout with persistent header and footer |

---

## Task 1: Controller — add `rolesWithPermissions` + delete orphan

**Files:**
- Modify: `laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php` (renderForm return block ~line 414)
- Delete: `laravel-app/resources/views/usuarios/v2-edit.blade.php`

- [ ] **Step 1.1: Verify current renderForm return block**

```bash
grep -n "rolesWithPermissions\|renderForm\|v2-form\|v2-edit" \
  laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php | head -20
```

Expected: `renderForm` defined, `v2-form` referenced, no `rolesWithPermissions` in the return yet.

- [ ] **Step 1.2: Add `rolesWithPermissions` to renderForm payload**

Find this block in `UsuariosUiController.php` (inside `renderForm()`):

```php
        return view('usuarios.v2-form', [
            'pageTitle' => $context['pageTitle'] ?? 'Usuarios',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'user' => $user,
            'roles' => $this->fetchRoles(),
            'permissions' => LegacyPermissionCatalog::groups(),
            'permissionProfiles' => config('permission_profiles', []),
            'selectedPermissions' => $context['selectedPermissions'] ?? [],
```

Add `'rolesWithPermissions'` on a new line after `'permissions'`:

```php
        return view('usuarios.v2-form', [
            'pageTitle' => $context['pageTitle'] ?? 'Usuarios',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'user' => $user,
            'roles' => $this->fetchRoles(),
            'permissions' => LegacyPermissionCatalog::groups(),
            'rolesWithPermissions' => $this->fetchRolesWithPermissions(),
            'permissionProfiles' => config('permission_profiles', []),
            'selectedPermissions' => $context['selectedPermissions'] ?? [],
```

- [ ] **Step 1.3: Verify PHP syntax**

```bash
php -l laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 1.4: Delete the orphan view**

```bash
rm laravel-app/resources/views/usuarios/v2-edit.blade.php
```

- [ ] **Step 1.5: Confirm deletion**

```bash
ls laravel-app/resources/views/usuarios/
```

Expected: `v2-edit.blade.php` is no longer listed.

- [ ] **Step 1.6: Commit**

```bash
git add laravel-app/app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php
git rm laravel-app/resources/views/usuarios/v2-edit.blade.php
git commit -m "$(cat <<'EOF'
feat(usuarios): add rolesWithPermissions to form payload, delete orphan v2-edit view

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: CSS — form-specific styles in `usuarios.css`

**Files:**
- Modify: `laravel-app/resources/css/usuarios.css` (append at end, after line 299)

- [ ] **Step 2.1: Append form styles to `usuarios.css`**

Open `laravel-app/resources/css/usuarios.css` and append the following block at the very end:

```css
/* ═══════════════════════════════════════════════════════════════════════════
   FORM v2 — User header, tabs, panels, sections, footer
   ═══════════════════════════════════════════════════════════════════════════ */

/* ─── User header ─────────────────────────────────────────────────────────── */

.form-user-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem 0.5rem 0 0;
    padding: 0.875rem 1.25rem;
    margin-bottom: 0;
}

.form-user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #0891b2;
    color: #fff;
    font-weight: 700;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.form-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.form-user-name {
    font-weight: 600;
    font-size: 1.05rem;
    color: #0f172a;
    line-height: 1.3;
}

.form-user-sub {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.1rem;
}

.form-user-badge-approved {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    background: #dcfce7;
    color: #166534;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 20px;
}

.form-user-badge-pending {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    background: #fef3c7;
    color: #92400e;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 20px;
}

/* ─── Tab bar ─────────────────────────────────────────────────────────────── */

.form-tab-bar {
    display: flex;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 1rem;
    gap: 0;
}

.form-tab-btn {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.65rem 1rem;
    font-size: 0.88rem;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    white-space: nowrap;
}

.form-tab-btn:hover {
    color: #0f172a;
}

.form-tab-btn.active,
.form-tab-btn[aria-selected="true"] {
    color: #0891b2;
    border-bottom-color: #0891b2;
    font-weight: 600;
}

/* ─── Tab panels ──────────────────────────────────────────────────────────── */

.form-tab-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-top: none;
    padding: 1.5rem 1.25rem;
}

.form-tab-panel[hidden] {
    display: none;
}

/* ─── Section labels inside tabs ──────────────────────────────────────────── */

.form-section-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    margin-bottom: 0.75rem;
    margin-top: 1.5rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid #f1f5f9;
}

.form-section-label:first-child {
    margin-top: 0;
}

/* ─── Field grid ──────────────────────────────────────────────────────────── */

.form-field-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.875rem 1.25rem;
}

@media (max-width: 991px) {
    .form-field-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 575px) {
    .form-field-grid {
        grid-template-columns: 1fr;
    }
}

.form-field-grid .col-full {
    grid-column: 1 / -1;
}

/* ─── Upload grid (Documentos tab) ───────────────────────────────────────── */

.form-upload-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}

@media (max-width: 575px) {
    .form-upload-grid {
        grid-template-columns: 1fr;
    }
}

/* ─── Persistent footer ───────────────────────────────────────────────────── */

.form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-top: none;
    padding: 0.875rem 1.25rem;
    border-radius: 0 0 0.5rem 0.5rem;
}
```

- [ ] **Step 2.2: Verify no syntax errors (count braces)**

```bash
node -e "
const fs = require('fs');
const css = fs.readFileSync('laravel-app/resources/css/usuarios.css', 'utf8');
const open = (css.match(/{/g) || []).length;
const close = (css.match(/}/g) || []).length;
console.log('Open braces:', open, '  Close braces:', close);
if (open !== close) process.exit(1);
"
```

Expected: equal counts, exits 0.

- [ ] **Step 2.3: Commit**

```bash
git add laravel-app/resources/css/usuarios.css
git commit -m "$(cat <<'EOF'
feat(usuarios): add form tab/header/footer styles to usuarios.css

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: JS — rewrite `user-edit.js`

**Files:**
- Rewrite: `laravel-app/resources/js/v2/user-edit.js`

- [ ] **Step 3.1: Write the new `user-edit.js`**

Replace the entire content of `laravel-app/resources/js/v2/user-edit.js` with:

```javascript
/**
 * user-edit.js — Usuarios v2 form: tabs, inherited permissions, role change,
 *                accordion, delete modal, permission profile apply, full-name
 *                auto-compose, subespecialidad enable/disable.
 */
(function () {
    'use strict';

    /* ── Config from Blade ──────────────────────────────────────────────── */
    var cfg = window.__USUARIOS_V2_EDIT__ || {};
    var permissionProfiles   = cfg.permissionProfiles   || {};
    var rolesWithPermissions = cfg.rolesWithPermissions || {};
    var currentRoleId        = String(cfg.currentRoleId || '');
    // directPerms = permissions the user holds directly (not via role)
    var directPerms = new Set(Array.isArray(cfg.directPermissions) ? cfg.directPermissions : []);

    /* ── Tab switching ──────────────────────────────────────────────────── */
    var tabBtns   = Array.from(document.querySelectorAll('.form-tab-btn'));
    var tabPanels = Array.from(document.querySelectorAll('.form-tab-panel'));

    function activateTab(targetTab) {
        tabBtns.forEach(function (btn) {
            var active = btn.dataset.tab === targetTab;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        tabPanels.forEach(function (panel) {
            if (panel.dataset.tab === targetTab) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.dataset.tab);
        });
    });

    /* ── Permission accordion ───────────────────────────────────────────── */
    var groupHeads = Array.from(document.querySelectorAll('.perm-group-head'));

    groupHeads.forEach(function (head) {
        head.addEventListener('click', function () {
            var body = head.nextElementSibling;
            if (!body) return;
            var isOpen = !body.hasAttribute('hidden');
            body.toggleAttribute('hidden', isOpen);
            head.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            var icon = head.querySelector('.perm-chevron');
            if (icon) {
                icon.style.transform = isOpen ? '' : 'rotate(180deg)';
            }
        });
    });

    /* ── Inherited permissions ──────────────────────────────────────────── */
    var allPermInputs = Array.from(document.querySelectorAll('input.perm-check[name="permissions[]"]'));

    function getInheritedSet(roleId) {
        var perms = rolesWithPermissions[String(roleId)];
        return new Set(Array.isArray(perms) ? perms : []);
    }

    function applyInheritedState(inheritedSet) {
        allPermInputs.forEach(function (input) {
            var isInherited = inheritedSet.has(input.value);
            // Superuser permission: respect canAssignSuperuser (Blade adds disabled to the input)
            if (input.hasAttribute('data-superuser-locked')) {
                return;
            }
            if (isInherited) {
                input.checked  = true;
                input.disabled = true;
                input.setAttribute('aria-label', input.value + ' (heredado del rol)');
                // Add inherited tag if not present
                var wrap = input.closest('.form-check');
                if (wrap && !wrap.querySelector('.inherited-tag')) {
                    var tag = document.createElement('span');
                    tag.className = 'inherited-tag';
                    tag.textContent = 'rol';
                    wrap.appendChild(tag);
                }
            } else {
                input.disabled = false;
                input.removeAttribute('aria-label');
                // Restore direct perm state
                if (!input.checked) {
                    input.checked = directPerms.has(input.value);
                }
                // Remove inherited tag if present
                var wrap = input.closest('.form-check');
                if (wrap) {
                    var tag = wrap.querySelector('.inherited-tag');
                    if (tag) tag.remove();
                }
            }
        });
    }

    // Track user changes to direct permissions
    allPermInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.disabled) return;
            if (input.checked) {
                directPerms.add(input.value);
            } else {
                directPerms.delete(input.value);
            }
        });
    });

    // Initialize on load
    applyInheritedState(getInheritedSet(currentRoleId));

    /* ── Role change → update inherited preview ─────────────────────────── */
    var roleSelect = document.getElementById('form-role-id');
    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            applyInheritedState(getInheritedSet(roleSelect.value));
        });
    }

    /* ── Permission profile apply ───────────────────────────────────────── */
    var applyButton   = document.getElementById('apply_permission_profile');
    var profileSelect = document.getElementById('permission_profile');

    if (applyButton && profileSelect) {
        applyButton.addEventListener('click', function () {
            var key = profileSelect.value;
            if (!key || !permissionProfiles[key] || !Array.isArray(permissionProfiles[key].permissions)) {
                return;
            }
            var profileSet = new Set(permissionProfiles[key].permissions);
            allPermInputs.forEach(function (input) {
                if (input.disabled) return; // don't touch inherited
                var checked = profileSet.has(input.value);
                input.checked = checked;
                if (checked) {
                    directPerms.add(input.value);
                } else {
                    directPerms.delete(input.value);
                }
            });
        });
    }

    /* ── Full name auto-compose ─────────────────────────────────────────── */
    var fullNameInput = document.getElementById('display_full_name');
    var nameFields    = ['first_name', 'middle_name', 'last_name', 'second_last_name']
        .map(function (n) { return document.querySelector('input[name="' + n + '"]'); })
        .filter(Boolean);

    function updateFullName() {
        if (!fullNameInput) return;
        fullNameInput.value = nameFields
            .map(function (f) { return (f.value || '').trim(); })
            .filter(Boolean)
            .join(' ');
    }

    nameFields.forEach(function (f) { f.addEventListener('input', updateFullName); });

    /* ── Subespecialidad enable/disable ─────────────────────────────────── */
    var especialidadSelect  = document.getElementById('especialidad');
    var subespecialidadInput = document.getElementById('subespecialidad');

    function toggleSubespecialidad() {
        if (!especialidadSelect || !subespecialidadInput) return;
        var isOftalmologo = especialidadSelect.value === 'Cirujano Oftalmólogo';
        subespecialidadInput.disabled = !isOftalmologo;
        if (!isOftalmologo) subespecialidadInput.value = '';
    }

    if (especialidadSelect) {
        especialidadSelect.addEventListener('change', toggleSubespecialidad);
        toggleSubespecialidad();
    }

    /* ── Delete confirmation modal ──────────────────────────────────────── */
    var deleteBtn   = document.querySelector('.form-delete-btn');
    var deleteModal = document.getElementById('delete-user-modal');
    var cancelBtn   = document.getElementById('delete-modal-cancel');

    function openDeleteModal() {
        if (!deleteModal) return;
        deleteModal.removeAttribute('hidden');
        if (cancelBtn) cancelBtn.focus();
    }

    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.setAttribute('hidden', '');
        if (deleteBtn) deleteBtn.focus();
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', openDeleteModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeDeleteModal);
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) closeDeleteModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && deleteModal && !deleteModal.hasAttribute('hidden')) {
            closeDeleteModal();
        }
    });

}());
```

- [ ] **Step 3.2: Verify JS syntax**

```bash
node --check laravel-app/resources/js/v2/user-edit.js
```

Expected: exits 0, no output.

- [ ] **Step 3.3: Commit**

```bash
git add laravel-app/resources/js/v2/user-edit.js
git commit -m "$(cat <<'EOF'
feat(usuarios): rewrite user-edit.js — tabs, inherited perms, role change, accordion, delete modal

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Blade — rewrite `v2-form.blade.php`

**Files:**
- Rewrite: `laravel-app/resources/views/usuarios/v2-form.blade.php`

- [ ] **Step 4.1: Write the new `v2-form.blade.php`**

Replace the entire content of `laravel-app/resources/views/usuarios/v2-form.blade.php` with:

```blade
@php
    /** @var array<string, mixed>               $user */
    /** @var array<int, array{id:int,name:string}> $roles */
    /** @var array<string, array<string, string>>  $permissions */
    /** @var array<int, string>                 $selectedPermissions */
    /** @var array<string, list<string>>        $rolesWithPermissions */
    /** @var array<string, string>              $validationErrors */
    /** @var array<int, string>                 $warnings */
    /** @var string                             $formAction */
    /** @var string                             $mode */

    $user               = $user               ?? [];
    $roles              = $roles              ?? [];
    $permissions        = $permissions        ?? [];
    $selectedPermissions = $selectedPermissions ?? [];
    $rolesWithPermissions = $rolesWithPermissions ?? [];
    $validationErrors   = $validationErrors   ?? [];
    $warnings           = $warnings           ?? [];
    $mode               = $mode               ?? 'edit';
    $isCreate           = $mode === 'create';
    $canAssignSuperuser = !empty($canAssignSuperuser);

    /* ── Helper closures ───────────────────────────────────────────────── */
    $fieldValue = static function (string $key, string $default = '') use ($user): string {
        return htmlspecialchars((string) ($user[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    };

    $isChecked = static function (string $key) use ($user): string {
        return !empty($user[$key]) ? 'checked' : '';
    };

    /* ── Identity fields ───────────────────────────────────────────────── */
    $fullName   = trim((string) ($user['display_full_name'] ?? ''));
    $handle     = (string) ($user['username'] ?? '');
    $isApproved = !empty($user['is_approved']);
    $avatarInitials = strtoupper(
        substr((string) ($user['first_name'] ?? 'N'), 0, 1) .
        substr((string) ($user['last_name']  ?? ''),  0, 1)
    ) ?: 'N';

    /* ── Especialidades list ───────────────────────────────────────────── */
    $especialidades = [
        '' => 'Seleccionar',
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

    /* ── Direct permissions for JS (selected minus inherited by current role) */
    $inheritedByCurrentRole = $rolesWithPermissions[(string) ($user['role_id'] ?? '')] ?? [];
    $inheritedSet           = array_flip($inheritedByCurrentRole);
    $directPermissions      = array_values(array_filter(
        $selectedPermissions,
        fn($p) => !isset($inheritedSet[$p])
    ));
@endphp

@extends('layouts.medforge')

@section('content')

{{-- ── Content header ──────────────────────────────────────────────────── --}}
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">{{ $isCreate ? 'Nuevo usuario' : 'Editar usuario' }}</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                    <li class="breadcrumb-item"><a href="/usuarios">Usuarios</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ $isCreate ? 'Nuevo' : ($handle ?: 'Editar') }}
                    </li>
                </ol>
            </nav>
        </div>
        <a href="/usuarios" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<section class="content">

    {{-- Alerts --}}
    @if(($status ?? null) === 'updated')
        <div class="alert alert-success">Cambios guardados correctamente.</div>
    @endif

    @if($warnings !== [])
        <div class="alert alert-warning">
            <p class="mb-2 fw-semibold"><i class="mdi mdi-alert"></i> Posibles duplicados detectados:</p>
            <ul class="mb-0 ps-3">
                @foreach($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($validationErrors !== [])
        <div class="alert alert-danger" role="alert" aria-live="assertive" tabindex="-1" data-validation-alert>
            <i class="mdi mdi-alert-circle-outline"></i> Revisa los campos marcados para continuar.
            @if(!empty($validationErrors['general']))
                <div class="mt-2">{{ $validationErrors['general'] }}</div>
            @endif
        </div>
    @endif

    {{-- ── User header ────────────────────────────────────────────────── --}}
    <div class="form-user-header">
        <div class="form-user-avatar">
            @if(!$isCreate && !empty($user['profile_photo_url']))
                <img src="{{ $user['profile_photo_url'] }}" alt="Foto de perfil">
            @else
                {{ $avatarInitials }}
            @endif
        </div>
        <div>
            <div class="form-user-name">
                {{ $isCreate ? 'Nuevo usuario' : ($fullName ?: $handle) }}
            </div>
            @if(!$isCreate)
                <div class="form-user-sub">
                    <span>{{ $handle }}</span>
                    @if($isApproved)
                        <span class="form-user-badge-approved">✓ Aprobado</span>
                    @else
                        <span class="form-user-badge-pending">● Pendiente</span>
                    @endif
                    @if(!empty($user['especialidad']))
                        <span>· {{ $user['especialidad'] }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ── Tab bar ─────────────────────────────────────────────────────── --}}
    <div class="form-tab-bar" role="tablist" aria-label="Secciones del perfil">
        <button class="form-tab-btn active"
                data-tab="perfil"
                id="tab-btn-perfil"
                role="tab"
                aria-selected="true"
                aria-controls="tab-panel-perfil"
                type="button">
            Perfil
        </button>
        <button class="form-tab-btn"
                data-tab="acceso"
                id="tab-btn-acceso"
                role="tab"
                aria-selected="false"
                aria-controls="tab-panel-acceso"
                type="button">
            Acceso
        </button>
        <button class="form-tab-btn"
                data-tab="documentos"
                id="tab-btn-documentos"
                role="tab"
                aria-selected="false"
                aria-controls="tab-panel-documentos"
                type="button">
            Documentos
        </button>
    </div>

    {{-- ═══ FORM wraps all tabs ═══════════════════════════════════════════ --}}
    <div id="userUploadA11yStatus" class="visually-hidden" aria-live="polite"></div>

    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- ════════════════════════════════════════════════════════════
             TAB: PERFIL
             ════════════════════════════════════════════════════════════ --}}
        <div class="form-tab-panel"
             id="tab-panel-perfil"
             data-tab="perfil"
             role="tabpanel"
             aria-labelledby="tab-btn-perfil">

            {{-- Identidad --}}
            <div class="form-section-label">Identidad</div>
            <div class="form-field-grid">

                {{-- Usuario (disabled) --}}
                <div>
                    <label class="form-label" for="username">Usuario</label>
                    <input type="text"
                           name="username"
                           id="username"
                           class="form-control"
                           value="{!! $fieldValue('username') !!}"
                           {{ $isCreate ? 'required' : 'readonly' }}>
                    @if(!empty($validationErrors['username']))
                        <div class="text-danger small">{{ $validationErrors['username'] }}</div>
                    @endif
                </div>

                {{-- Correo electrónico --}}
                <div>
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control"
                           value="{!! $fieldValue('email') !!}">
                    @if(!empty($validationErrors['email']))
                        <div class="text-danger small">{{ $validationErrors['email'] }}</div>
                    @endif
                </div>

                {{-- Especialidad --}}
                <div>
                    <label class="form-label" for="especialidad">Especialidad</label>
                    <select name="especialidad" id="especialidad" class="form-select">
                        @foreach($especialidades as $value => $label)
                            <option value="{{ $value }}"
                                    {{ ($user['especialidad'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nombre --}}
                <div>
                    <label class="form-label" for="first_name">Nombre *</label>
                    <input type="text"
                           name="first_name"
                           id="first_name"
                           class="form-control"
                           maxlength="100"
                           pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'&quot;\.\s]+"
                           value="{!! $fieldValue('first_name') !!}"
                           required>
                    @if(!empty($validationErrors['first_name']))
                        <div class="text-danger small">{{ $validationErrors['first_name'] }}</div>
                    @endif
                </div>

                {{-- Segundo nombre --}}
                <div>
                    <label class="form-label" for="middle_name">Segundo nombre</label>
                    <input type="text"
                           name="middle_name"
                           id="middle_name"
                           class="form-control"
                           maxlength="100"
                           pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'&quot;\.\s]+"
                           value="{!! $fieldValue('middle_name') !!}">
                    @if(!empty($validationErrors['middle_name']))
                        <div class="text-danger small">{{ $validationErrors['middle_name'] }}</div>
                    @endif
                </div>

                {{-- Primer apellido --}}
                <div>
                    <label class="form-label" for="last_name">Primer apellido *</label>
                    <input type="text"
                           name="last_name"
                           id="last_name"
                           class="form-control"
                           maxlength="100"
                           pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'&quot;\.\s]+"
                           value="{!! $fieldValue('last_name') !!}"
                           required>
                    @if(!empty($validationErrors['last_name']))
                        <div class="text-danger small">{{ $validationErrors['last_name'] }}</div>
                    @endif
                </div>

                {{-- Segundo apellido --}}
                <div>
                    <label class="form-label" for="second_last_name">Segundo apellido</label>
                    <input type="text"
                           name="second_last_name"
                           id="second_last_name"
                           class="form-control"
                           maxlength="100"
                           pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'&quot;\.\s]+"
                           value="{!! $fieldValue('second_last_name') !!}">
                    @if(!empty($validationErrors['second_last_name']))
                        <div class="text-danger small">{{ $validationErrors['second_last_name'] }}</div>
                    @endif
                </div>

                {{-- Fecha de nacimiento --}}
                <div>
                    <label class="form-label" for="birth_date">Fecha de nacimiento</label>
                    <input type="date"
                           name="birth_date"
                           id="birth_date"
                           class="form-control"
                           value="{!! $fieldValue('birth_date') !!}">
                    @if(!empty($validationErrors['birth_date']))
                        <div class="text-danger small">{{ $validationErrors['birth_date'] }}</div>
                    @endif
                </div>

                {{-- Identificación nacional --}}
                <div>
                    <label class="form-label" for="national_id">Identificación nacional</label>
                    <input type="text"
                           name="national_id"
                           id="national_id"
                           class="form-control"
                           maxlength="32"
                           pattern="[A-Za-z0-9-]{4,32}"
                           value="{!! $fieldValue('national_id') !!}"
                           placeholder="{{ $isCreate ? 'Letras, números y guiones' : 'Se mantiene si se deja en blanco' }}">
                    <small class="text-muted">Almacenada de forma protegida.</small>
                    @if(!empty($user['national_id_masked']))
                        <div class="text-muted small">Actual: {{ $user['national_id_masked'] }}</div>
                    @endif
                    @if(!empty($validationErrors['national_id']))
                        <div class="text-danger small">{{ $validationErrors['national_id'] }}</div>
                    @endif
                </div>

                {{-- Pasaporte --}}
                <div>
                    <label class="form-label" for="passport_number">Pasaporte</label>
                    <input type="text"
                           name="passport_number"
                           id="passport_number"
                           class="form-control"
                           maxlength="32"
                           pattern="[A-Za-z0-9-]{4,32}"
                           value="{!! $fieldValue('passport_number') !!}"
                           placeholder="{{ $isCreate ? 'Letras, números y guiones' : 'Se mantiene si se deja en blanco' }}">
                    <small class="text-muted">Almacenado de forma protegida.</small>
                    @if(!empty($user['passport_number_masked']))
                        <div class="text-muted small">Actual: {{ $user['passport_number_masked'] }}</div>
                    @endif
                    @if(!empty($validationErrors['passport_number']))
                        <div class="text-danger small">{{ $validationErrors['passport_number'] }}</div>
                    @endif
                </div>

                {{-- Cédula --}}
                <div>
                    <label class="form-label" for="cedula">Cédula</label>
                    <input type="text"
                           name="cedula"
                           id="cedula"
                           class="form-control"
                           value="{!! $fieldValue('cedula') !!}">
                </div>

                {{-- Registro --}}
                <div>
                    <label class="form-label" for="registro">Registro</label>
                    <input type="text"
                           name="registro"
                           id="registro"
                           class="form-control"
                           value="{!! $fieldValue('registro') !!}">
                </div>

                {{-- Sede --}}
                <div>
                    <label class="form-label" for="sede">Sede</label>
                    <input type="text"
                           name="sede"
                           id="sede"
                           class="form-control"
                           value="{!! $fieldValue('sede') !!}">
                </div>

                {{-- Subespecialidad --}}
                <div>
                    <label class="form-label" for="subespecialidad">Subespecialidad</label>
                    <input type="text"
                           name="subespecialidad"
                           id="subespecialidad"
                           class="form-control"
                           value="{!! $fieldValue('subespecialidad') !!}">
                    <small class="text-muted">Solo para Cirujano Oftalmólogo.</small>
                </div>

                {{-- WhatsApp --}}
                <div>
                    <label class="form-label" for="whatsapp_number">WhatsApp del agente</label>
                    <input type="text"
                           name="whatsapp_number"
                           id="whatsapp_number"
                           class="form-control"
                           value="{!! $fieldValue('whatsapp_number') !!}"
                           placeholder="+593...">
                    <small class="text-muted">Notificaciones de handoff.</small>
                    @if(!empty($validationErrors['whatsapp_number']))
                        <div class="text-danger small">{{ $validationErrors['whatsapp_number'] }}</div>
                    @endif
                </div>

                {{-- Nombre completo (readonly computed) --}}
                <div class="col-full">
                    <label class="form-label" for="display_full_name">Nombre completo</label>
                    <input type="text"
                           id="display_full_name"
                           class="form-control"
                           value="{{ $fullName }}"
                           readonly>
                    <small class="text-muted">Se compone automáticamente a partir de los nombres y apellidos.</small>
                </div>

                {{-- Password (create only) --}}
                @if($isCreate)
                    <div>
                        <label class="form-label" for="password">Contraseña *</label>
                        <input type="password"
                               name="password"
                               id="password"
                               class="form-control"
                               autocomplete="new-password"
                               required>
                        @if(!empty($validationErrors['password']))
                            <div class="text-danger small">{{ $validationErrors['password'] }}</div>
                        @endif
                    </div>
                @endif

            </div>{{-- /form-field-grid --}}

            {{-- Foto de perfil --}}
            <div class="form-section-label">Foto de perfil</div>
            @if(!empty($user['profile_photo_url']))
                <div class="mb-2">
                    <img src="{{ $user['profile_photo_url'] }}"
                         alt="Foto de perfil actual"
                         class="img-thumbnail"
                         style="max-height:120px;">
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox"
                           name="remove_profile_photo" id="remove_profile_photo" value="1">
                    <label class="form-check-label" for="remove_profile_photo">Eliminar foto actual</label>
                </div>
            @endif
            <div class="drop-zone p-3 border rounded"
                 data-upload-drop-zone="profile_photo_file"
                 tabindex="0"
                 aria-label="Zona de carga para foto de perfil"
                 aria-describedby="profile_photo_help">
                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Arrastra tu foto o usa el botón para explorar archivos.</div>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-upload-trigger="profile_photo_file">
                        <i class="mdi mdi-upload"></i> Seleccionar foto
                    </button>
                </div>
                <div class="progress progress-xs mt-2 d-none"
                     data-upload-progress="profile_photo_file" aria-hidden="true">
                    <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                </div>
                <div class="text-danger small mt-2 {{ !empty($validationErrors['profile_photo_file']) ? '' : 'd-none' }}"
                     data-upload-error="profile_photo_file" role="alert">
                    {{ $validationErrors['profile_photo_file'] ?? '' }}
                </div>
                <div class="mt-2" data-upload-preview="profile_photo_file"></div>
                <div class="text-muted small mt-2" id="profile_photo_help">
                    PNG, JPG o WEBP · Máx 2 MB · Recomendado 400×400 px
                </div>
                <input type="file"
                       name="profile_photo_file"
                       id="profile_photo_file"
                       class="form-control mt-2"
                       accept="image/png,image/jpeg,image/webp">
            </div>

            {{-- Estado de la cuenta --}}
            <div class="form-section-label">Estado de la cuenta</div>
            <div class="d-flex flex-column gap-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="is_approved" id="is_approved"
                           {{ $isChecked('is_approved') }}>
                    <label class="form-check-label" for="is_approved">Cuenta aprobada</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="is_subscribed" id="is_subscribed"
                           {{ $isChecked('is_subscribed') }}>
                    <label class="form-check-label" for="is_subscribed">Suscripción activa</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="whatsapp_notify" id="whatsapp_notify"
                           {{ $isChecked('whatsapp_notify') }}>
                    <label class="form-check-label" for="whatsapp_notify">Notificaciones WhatsApp</label>
                </div>
            </div>

        </div>{{-- /tab-panel-perfil --}}

        {{-- ════════════════════════════════════════════════════════════
             TAB: ACCESO
             ════════════════════════════════════════════════════════════ --}}
        <div class="form-tab-panel"
             id="tab-panel-acceso"
             data-tab="acceso"
             role="tabpanel"
             aria-labelledby="tab-btn-acceso"
             hidden>

            {{-- Rol --}}
            <div class="form-section-label">Rol asignado</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="form-role-id">Rol</label>
                    <select name="role_id" id="form-role-id" class="form-select">
                        <option value="">Sin asignar</option>
                        @foreach($roles as $role)
                            <option value="{{ $role['id'] }}"
                                    {{ (int) ($user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' }}>
                                {{ $role['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @if(!empty($validationErrors['role_id']))
                        <div class="text-danger small">{{ $validationErrors['role_id'] }}</div>
                    @endif
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="alert alert-info mb-0 py-2 px-3 small">
                        <i class="mdi mdi-information-outline"></i>
                        Al cambiar el rol, los permisos heredados se actualizan al instante.
                    </div>
                </div>
            </div>

            @if(!$canAssignSuperuser)
                <div class="alert alert-warning mb-3">
                    El permiso <strong>superusuario</strong> queda bloqueado en este formulario.
                    Solo otro superusuario puede otorgarlo o retirarlo.
                </div>
            @endif

            {{-- Plantilla rápida --}}
            @if(!empty($permissionProfiles))
                <div class="form-section-label">Plantilla rápida</div>
                <div class="d-flex gap-2 mb-3">
                    <select id="permission_profile" class="form-select">
                        <option value="">Selecciona una plantilla…</option>
                        @foreach($permissionProfiles as $profileKey => $profile)
                            <option value="{{ $profileKey }}">
                                {{ $profile['label'] ?? $profileKey }}
                                @if(!empty($profile['description']))
                                    — {{ $profile['description'] }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <button type="button" id="apply_permission_profile" class="btn btn-outline-primary">
                        Aplicar
                    </button>
                </div>
                <small class="text-muted d-block mb-3">
                    Reemplaza solo los permisos directos (no afecta los heredados del rol).
                </small>
            @endif

            {{-- Leyenda --}}
            <div class="form-section-label">Permisos</div>
            <div class="perm-legend mb-3">
                <span class="perm-legend-direct">●</span> directo &nbsp;
                <span class="perm-legend-inherited">○</span> heredado del rol (no editable)
            </div>

            {{-- Accordion de grupos --}}
            @foreach($permissions as $group => $items)
                @php
                    $groupSlug = preg_replace('/[^a-z0-9]/i', '-', strtolower($group));
                @endphp
                <div class="perm-group">
                    <div class="perm-group-head"
                         role="button"
                         tabindex="0"
                         aria-expanded="false"
                         aria-controls="perm-body-{{ $groupSlug }}">
                        <span>{{ $group }}</span>
                        <i class="mdi mdi-chevron-down perm-chevron" aria-hidden="true"></i>
                    </div>
                    <div class="perm-group-body" id="perm-body-{{ $groupSlug }}" hidden>
                        <div class="row g-2">
                            @foreach($items as $permission => $label)
                                @php
                                    $permId            = 'perm_' . preg_replace('/[^a-z0-9_-]/i', '_', $permission);
                                    $isSuperuser       = $permission === 'superuser';
                                    $isSelected        = in_array($permission, $selectedPermissions, true);
                                    $isLockedSuperuser = $isSuperuser && !$canAssignSuperuser;
                                @endphp
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input perm-check"
                                               type="checkbox"
                                               name="permissions[]"
                                               value="{{ $permission }}"
                                               id="{{ $permId }}"
                                               {{ $isSelected ? 'checked' : '' }}
                                               {{ $isLockedSuperuser ? 'disabled data-superuser-locked' : '' }}>
                                        <label class="form-check-label" for="{{ $permId }}">
                                            {{ $label }}
                                            @if($isSuperuser)
                                                <span class="badge bg-danger ms-1">Crítico</span>
                                            @endif
                                        </label>
                                        @if($isSuperuser)
                                            <div class="small text-muted">
                                                Otorga acceso irrestricto a todo el sistema.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

        </div>{{-- /tab-panel-acceso --}}

        {{-- ════════════════════════════════════════════════════════════
             TAB: DOCUMENTOS
             ════════════════════════════════════════════════════════════ --}}
        <div class="form-tab-panel"
             id="tab-panel-documentos"
             data-tab="documentos"
             role="tabpanel"
             aria-labelledby="tab-btn-documentos"
             hidden>

            <div class="form-section-label">Documentos médicos</div>
            <div class="form-upload-grid">

                {{-- Sello --}}
                <div>
                    <p class="fw-semibold mb-2">Sello</p>
                    @if(!empty($user['firma_url']))
                        <div class="mb-2">
                            <img src="{{ $user['firma_url'] }}"
                                 alt="Sello actual"
                                 class="img-fluid border rounded"
                                 style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="remove_firma" id="remove_firma" value="1">
                            <label class="form-check-label" for="remove_firma">Eliminar sello actual</label>
                        </div>
                    @endif
                    <div class="drop-zone p-3 border rounded"
                         data-upload-drop-zone="firma_file"
                         tabindex="0"
                         aria-label="Zona de carga para sello"
                         aria-describedby="firma_help">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <div class="fw-semibold">Arrastra o usa el botón.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-upload-trigger="firma_file">
                                <i class="mdi mdi-upload"></i> Seleccionar
                            </button>
                        </div>
                        <div class="progress progress-xs mt-2 d-none"
                             data-upload-progress="firma_file" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div class="text-danger small mt-2 {{ !empty($validationErrors['firma_file']) ? '' : 'd-none' }}"
                             data-upload-error="firma_file" role="alert">
                            {{ $validationErrors['firma_file'] ?? '' }}
                        </div>
                        <div class="mt-2" data-upload-preview="firma_file"></div>
                        <div class="text-muted small mt-2" id="firma_help">PNG, WEBP o SVG · Máx 2 MB</div>
                        <input type="file"
                               name="firma_file"
                               id="firma_file"
                               class="form-control mt-2"
                               accept="image/png,image/webp,image/svg+xml">
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1" for="seal_status">Estado del sello</label>
                        <select name="seal_status" id="seal_status" class="form-select form-select-sm">
                            <option value="pending"       {{ ($user['seal_status'] ?? 'pending') === 'pending'       ? 'selected' : '' }}>Pendiente de revisión</option>
                            <option value="verified"      {{ ($user['seal_status'] ?? 'pending') === 'verified'      ? 'selected' : '' }}>Verificado</option>
                            <option value="not_provided"  {{ ($user['seal_status'] ?? 'pending') === 'not_provided'  ? 'selected' : '' }}>No proporcionado</option>
                        </select>
                        @if(!empty($validationErrors['seal_status']))
                            <div class="text-danger small">{{ $validationErrors['seal_status'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- Firma digital --}}
                <div>
                    <p class="fw-semibold mb-2">Firma digital</p>
                    @if(!empty($user['signature_url']))
                        <div class="mb-2">
                            <img src="{{ $user['signature_url'] }}"
                                 alt="Firma digital actual"
                                 class="img-fluid border rounded"
                                 style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="remove_signature" id="remove_signature" value="1">
                            <label class="form-check-label" for="remove_signature">Eliminar firma digital actual</label>
                        </div>
                    @endif
                    <div class="drop-zone p-3 border rounded"
                         data-upload-drop-zone="signature_file"
                         tabindex="0"
                         aria-label="Zona de carga para firma digital"
                         aria-describedby="signature_help">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <div class="fw-semibold">Arrastra o usa el botón.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-upload-trigger="signature_file">
                                <i class="mdi mdi-upload"></i> Seleccionar
                            </button>
                        </div>
                        <div class="progress progress-xs mt-2 d-none"
                             data-upload-progress="signature_file" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div class="text-danger small mt-2 {{ !empty($validationErrors['signature_file']) ? '' : 'd-none' }}"
                             data-upload-error="signature_file" role="alert">
                            {{ $validationErrors['signature_file'] ?? '' }}
                        </div>
                        <div class="mt-2" data-upload-preview="signature_file"></div>
                        <div class="text-muted small mt-2" id="signature_help">PNG, WEBP o SVG · Máx 2 MB</div>
                        <input type="file"
                               name="signature_file"
                               id="signature_file"
                               class="form-control mt-2"
                               accept="image/png,image/webp,image/svg+xml">
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1" for="signature_status">Estado de la firma</label>
                        <select name="signature_status" id="signature_status" class="form-select form-select-sm">
                            <option value="pending"       {{ ($user['signature_status'] ?? 'pending') === 'pending'       ? 'selected' : '' }}>Pendiente de revisión</option>
                            <option value="verified"      {{ ($user['signature_status'] ?? 'pending') === 'verified'      ? 'selected' : '' }}>Verificada</option>
                            <option value="not_provided"  {{ ($user['signature_status'] ?? 'pending') === 'not_provided'  ? 'selected' : '' }}>No proporcionada</option>
                        </select>
                        @if(!empty($validationErrors['signature_status']))
                            <div class="text-danger small">{{ $validationErrors['signature_status'] }}</div>
                        @endif
                    </div>
                </div>

                {{-- Sello + firma combinados --}}
                <div>
                    <p class="fw-semibold mb-2">Sello + firma combinados</p>
                    @if(!empty($user['seal_signature_url']))
                        <div class="mb-2">
                            <img src="{{ $user['seal_signature_url'] }}"
                                 alt="Sello y firma combinados"
                                 class="img-fluid border rounded"
                                 style="max-height:120px;">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="remove_seal_signature" id="remove_seal_signature" value="1">
                            <label class="form-check-label" for="remove_seal_signature">Eliminar imagen combinada actual</label>
                        </div>
                    @endif
                    <div class="drop-zone p-3 border rounded"
                         data-upload-drop-zone="seal_signature_file"
                         tabindex="0"
                         aria-label="Zona de carga para sello y firma combinados"
                         aria-describedby="seal_signature_help">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <div class="fw-semibold">Sello y firma en una sola imagen.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-upload-trigger="seal_signature_file">
                                <i class="mdi mdi-upload"></i> Seleccionar
                            </button>
                        </div>
                        <div class="progress progress-xs mt-2 d-none"
                             data-upload-progress="seal_signature_file" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div class="text-danger small mt-2 {{ !empty($validationErrors['seal_signature_file']) ? '' : 'd-none' }}"
                             data-upload-error="seal_signature_file" role="alert">
                            {{ $validationErrors['seal_signature_file'] ?? '' }}
                        </div>
                        <div class="mt-2" data-upload-preview="seal_signature_file"></div>
                        <div class="text-muted small mt-2" id="seal_signature_help">PNG, WEBP o SVG · Máx 2 MB</div>
                        <input type="file"
                               name="seal_signature_file"
                               id="seal_signature_file"
                               class="form-control mt-2"
                               accept="image/png,image/webp,image/svg+xml">
                    </div>
                </div>

            </div>{{-- /form-upload-grid --}}

        </div>{{-- /tab-panel-documentos --}}

        {{-- ── Footer (persistent) ─────────────────────────────────────── --}}
        <div class="form-footer">
            @if(!$isCreate && !empty($user['id']) && !empty($canDelete))
                <button type="button"
                        class="btn btn-outline-danger form-delete-btn"
                        data-user-id="{{ (int) $user['id'] }}"
                        data-username="{{ $user['username'] ?? '' }}">
                    <i class="mdi mdi-alert-outline"></i> Eliminar usuario
                </button>
            @else
                <div><!-- spacer --></div>
            @endif
            <button type="submit" class="btn btn-primary">
                Guardar cambios
            </button>
        </div>

    </form>{{-- /form --}}

    {{-- ── Delete confirmation modal ───────────────────────────────────── --}}
    @if(!$isCreate && !empty($user['id']) && !empty($canDelete))
        <div id="delete-user-modal"
             class="modal-overlay"
             hidden
             role="dialog"
             aria-modal="true"
             aria-labelledby="delete-modal-title">
            <div class="modal-dialog-custom">
                <h5 id="delete-modal-title" class="mb-3">
                    <i class="mdi mdi-alert-outline text-danger"></i> Confirmar eliminación
                </h5>
                <p>
                    ¿Eliminar a
                    <strong>{{ $user['username'] ?? 'este usuario' }}</strong>?
                </p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button"
                            id="delete-modal-cancel"
                            class="btn btn-outline-secondary">
                        Cancelar
                    </button>
                    <form method="POST"
                          action="/usuarios/{{ (int) $user['id'] }}/delete">
                        @csrf
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ── JS config block ─────────────────────────────────────────────── --}}
    <script>
    window.__USUARIOS_V2_EDIT__ = {
        permissionProfiles:   @json($permissionProfiles ?? []),
        rolesWithPermissions: @json($rolesWithPermissions),
        currentRoleId:        "{{ (string) ($user['role_id'] ?? '') }}",
        directPermissions:    @json(array_values($directPermissions)),
    };
    </script>

</section>
@endsection

@push('scripts')
    <script src="/js/modules/user-media-upload.js"></script>
    @vite(['resources/css/usuarios.css', 'resources/js/v2/user-edit.js'])
@endpush
```

- [ ] **Step 4.2: Verify PHP/Blade syntax**

```bash
php -l laravel-app/resources/views/usuarios/v2-form.blade.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4.3: Commit**

```bash
git add laravel-app/resources/views/usuarios/v2-form.blade.php
git commit -m "$(cat <<'EOF'
feat(usuarios): rewrite v2-form.blade.php — tabs Perfil/Acceso/Documentos, inherited perms, delete modal

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Build + smoke check

**Files:** none modified (build artifacts not committed)

- [ ] **Step 5.1: Build assets**

```bash
cd laravel-app && npm run build 2>&1 | tail -20
```

Expected: Build completes with no errors. `public/build/manifest.json` updated.

- [ ] **Step 5.2: Verify manifested outputs**

```bash
node -e "
const m = JSON.parse(require('fs').readFileSync('laravel-app/public/build/manifest.json'));
const check = ['resources/css/usuarios.css','resources/js/v2/user-edit.js'];
check.forEach(k => {
  if (!m[k]) { console.error('MISSING:', k); process.exit(1); }
  console.log('OK:', k, '->', m[k].file);
});
"
```

Expected: Both keys present, exits 0.

- [ ] **Step 5.3: Quick smoke — check delete modal HTML**

```bash
grep -c "delete-user-modal\|form-tab-panel\|form-tab-btn\|perm-group" \
  laravel-app/resources/views/usuarios/v2-form.blade.php
```

Expected: output ≥ 4 (at least one match per pattern).

- [ ] **Step 5.4: Final commit (build summary)**

No files to commit (build artifacts are gitignored). Confirm with:

```bash
git status
```

Expected: `nothing to commit, working tree clean`.

---

## Self-review

### Spec coverage check

| Spec requirement | Covered by |
|---|---|
| Tabs: Perfil / Acceso / Documentos | Task 4 (Blade structure) |
| Header: avatar, name, handle, badge, specialty | Task 4 (`form-user-header`) |
| Header create mode: "Nuevo usuario", no handle/badge | Task 4 (`@if(!$isCreate)`) |
| Tab Perfil: identity grid 3-col | Task 4 + Task 2 (`.form-field-grid`) |
| Tab Perfil: photo upload zone | Task 4 (preserved drop-zone HTML) |
| Tab Perfil: estado switches | Task 4 (`is_approved`, `is_subscribed`, `whatsapp_notify`) |
| Tab Acceso: rol select | Task 4 (`#form-role-id`) |
| Tab Acceso: instant inherited preview on role change | Task 3 (role select listener) |
| Tab Acceso: permission profile template | Task 4 + Task 3 (`apply_permission_profile`) |
| Tab Acceso: accordion groups collapsed by default | Task 4 (`hidden` on body) + Task 3 (accordion listener) |
| Tab Acceso: inherited = disabled + "rol" tag | Task 3 (`applyInheritedState`) |
| Tab Acceso: legend direct/inherited | Task 4 (`.perm-legend`) |
| Tab Documentos: sello, firma, sello+firma zones | Task 4 (three drop zones) |
| Tab Documentos: seal_status, signature_status selects | Task 4 (preserved from original) |
| Footer persistent: Eliminar + Guardar | Task 4 + Task 2 (`.form-footer`) |
| Eliminar: modal (not `confirm()`) | Task 4 (modal HTML) + Task 3 (modal open/close) |
| Modal: POST to `/usuarios/{id}/delete` | Task 4 (form inside modal) |
| Modal: Escape to close | Task 3 (keydown listener) |
| `rolesWithPermissions` in controller payload | Task 1 |
| `v2-edit.blade.php` deleted | Task 1 |
| JS config: `rolesWithPermissions`, `currentRoleId`, `directPermissions` | Task 4 (script block) |
| CSS: no inline styles in template (form styles in CSS file) | Task 2 |
| Legacy upload script preserved | Task 4 (`@push('scripts')`) |
| `@vite` directly (no MedforgeAssets guard) | Task 4 |

### Placeholder scan
No TBD, TODO, or vague instructions found.

### Type consistency
- `$roles` accessed as `$role['id']` / `$role['name']` ✓ (array, not object)
- `$rolesWithPermissions` is `array<string, list<string>>` — keyed by string roleId ✓
- JS `rolesWithPermissions[String(roleId)]` uses `String()` coercion to match string keys ✓
- Delete form action uses `(int) $user['id']` cast for safety ✓
- `directPermissions` computed via `array_values()` to ensure JSON encodes as array ✓
