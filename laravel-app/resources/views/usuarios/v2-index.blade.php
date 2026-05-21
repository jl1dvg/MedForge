@php
    /** @var array<int, array<string, mixed>> $users */
    /** @var array<int, string> $roleMap */
    /** @var array<string, string> $permissionLabels */
    /** @var array<string, array<string, string>> $permissionGroups */
    /** @var array<string, string[]> $rolesWithPermissions */
    /** @var array<string, mixed> $permissionProfiles */

    $users               = $users               ?? [];
    $roleMap             = $roleMap             ?? [];
    $permissionLabels    = $permissionLabels    ?? [];
    $permissionGroups    = $permissionGroups    ?? [];
    $rolesWithPermissions = $rolesWithPermissions ?? [];
    $permissionProfiles  = $permissionProfiles  ?? [];
    $warnings            = $warnings            ?? [];
    $canManageUsers      = !empty($canManageUsers);
    $currentUserId       = isset($currentUserId) ? (int) $currentUserId : 0;

    $especialidadesFiltro = [
        ''                         => 'Todas',
        'Cirujano Oftalmólogo'     => 'Cirujano Oftalmólogo',
        'Residente'                => 'Residente',
        'Anestesiologo'            => 'Anestesiólogo',
        'Asistente'                => 'Asistente',
        'Optometrista'             => 'Optometrista',
        'Enfermera'                => 'Enfermera',
        'Administrativo'           => 'Administrativo',
        'Facturación'              => 'Facturación',
        'Sistemas'                 => 'Sistemas',
        'Coordinación Quirúrgica'  => 'Coordinación Quirúrgica',
        'Admisión'                 => 'Admisión',
        'Imagenología'             => 'Imagenología',
    ];
@endphp

@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Usuarios</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
                    </ol>
                </nav>
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
        @if(($status ?? null) === 'created')
            <div class="alert alert-success alert-dismissible fade show">
                Usuario creado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @elseif(($status ?? null) === 'updated')
            <div class="alert alert-success alert-dismissible fade show">
                Usuario actualizado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @elseif(($status ?? null) === 'deleted')
            <div class="alert alert-success alert-dismissible fade show">
                Usuario eliminado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @elseif(($status ?? null) === 'not_found')
            <div class="alert alert-warning">No se encontró el usuario solicitado.</div>
        @elseif(($status ?? null) === 'cannot_delete_self')
            <div class="alert alert-danger">No puedes eliminar tu propio usuario.</div>
        @endif

        @if($warnings !== [])
            <div class="alert alert-warning">
                <p class="mb-2 fw-semibold"><i class="mdi mdi-alert"></i> Avisos importantes:</p>
                <ul class="mb-0 ps-3">
                    @foreach($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── Filtros ─────────────────────────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label small mb-1 fw-semibold" for="uf-buscar">Buscar</label>
                        <input type="search" id="uf-buscar" class="form-control form-control-sm"
                               placeholder="Nombre, usuario, correo…" autocomplete="off">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label small mb-1 fw-semibold" for="uf-especialidad">Especialidad</label>
                        <select id="uf-especialidad" class="form-select form-select-sm">
                            @foreach($especialidadesFiltro as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label small mb-1 fw-semibold" for="uf-rol">Rol</label>
                        <select id="uf-rol" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach($roleMap as $rId => $rName)
                                <option value="{{ $rId }}">{{ $rName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label small mb-1 fw-semibold" for="uf-estado">Estado</label>
                        <select id="uf-estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="approved">Aprobado</option>
                            <option value="pending">Pendiente</option>
                        </select>
                    </div>
                    <div class="col-lg-1 d-flex align-items-end gap-2">
                        <button type="button" id="uf-limpiar" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                            <i class="mdi mdi-filter-remove"></i>
                        </button>
                        <span id="uf-count" class="badge bg-light text-dark border ms-auto"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Master-detail layout ─────────────────────────────────────────── --}}
        <div class="usuarios-layout">

            {{-- Table --}}
            <div class="usuarios-main">
                <div class="box">
                    <div class="box-body table-responsive p-0">
                        <table class="table table-hover align-middle mb-0 usuarios-table">
                            <thead class="bg-primary text-white">
                            <tr>
                                <th scope="col" style="width:48px"></th>
                                <th scope="col" data-sort="name" aria-sort="none">
                                    Nombre
                                    <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
                                    </svg>
                                </th>
                                <th scope="col" data-sort="especialidad" aria-sort="none">
                                    Especialidad
                                    <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
                                    </svg>
                                </th>
                                <th scope="col">Rol</th>
                                <th scope="col">Estado</th>
                                <th scope="col" style="width:48px"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                @php
                                    $displayName   = trim((string) ($user['display_full_name'] ?? $user['username'] ?? ''));
                                    $username      = (string) ($user['username'] ?? '');
                                    $especialidad  = trim((string) ($user['especialidad'] ?? ''));
                                    $roleId        = (string) ($user['role_id'] ?? '0');
                                    $isApproved    = !empty($user['is_approved']) ? '1' : '0';
                                    $initial       = mb_strtoupper(
                                        mb_substr($displayName !== '' ? $displayName : ($username !== '' ? $username : 'U'), 0, 1, 'UTF-8'),
                                        'UTF-8'
                                    );

                                    $searchIndex = mb_strtolower(trim(implode(' ', array_filter([
                                        $username,
                                        $displayName,
                                        (string) ($user['email'] ?? ''),
                                        $especialidad,
                                        (string) ($user['role_label'] ?? ''),
                                    ], static fn ($v): bool => (string) $v !== ''))), 'UTF-8');

                                    $nameSort = mb_strtolower(trim(implode(' ', array_filter([
                                        $user['last_name'] ?? '',
                                        $user['second_last_name'] ?? '',
                                        $user['first_name'] ?? '',
                                        $user['middle_name'] ?? '',
                                        $displayName !== '' ? $displayName : $username,
                                    ], static fn ($v): bool => (string) $v !== ''))), 'UTF-8');

                                    $userPayload = [
                                        'id'               => (int) $user['id'],
                                        'username'         => $username,
                                        'display_full_name'=> $displayName,
                                        'especialidad'     => $especialidad,
                                        'role_id'          => (string) ($user['role_id'] ?? '0'),
                                        'role_label'       => (string) ($user['role_label'] ?? 'Sin asignar'),
                                        'is_approved'      => !empty($user['is_approved']),
                                        'permisos_lista'   => $user['permisos_lista'] ?? [],
                                        'profile_photo_url'=> $user['profile_photo_url'] ?? null,
                                    ];
                                @endphp
                                <tr
                                    data-user="{{ json_encode($userPayload) }}"
                                    data-search="{{ $searchIndex }}"
                                    data-especialidad="{{ mb_strtolower($especialidad, 'UTF-8') }}"
                                    data-role-id="{{ $roleId }}"
                                    data-approved="{{ $isApproved }}"
                                >
                                    <td class="text-center pe-0">
                                        <span class="u-avatar">
                                            @if(!empty($user['profile_photo_url']))
                                                <img src="{{ $user['profile_photo_url'] }}"
                                                     alt="{{ $displayName }}">
                                            @else
                                                {{ $initial }}
                                            @endif
                                        </span>
                                    </td>
                                    <td data-sort-value="{{ $nameSort }}">
                                        <div class="fw-semibold lh-sm">{{ $displayName ?: $username }}</div>
                                        @if($username !== '')
                                            <div class="text-muted" style="font-size:0.75rem">{{ $username }}</div>
                                        @endif
                                    </td>
                                    <td data-sort-value="{{ mb_strtolower($especialidad, 'UTF-8') }}">
                                        {{ $especialidad ?: '—' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $user['role_label'] ?? 'Sin asignar' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!empty($user['is_approved']))
                                            <span class="badge bg-success">Aprobado</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-center ps-0">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary row-edit-btn"
                                                title="Editar"
                                                aria-label="Editar {{ $displayName ?: $username }}">
                                            <i class="mdi mdi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ── Drawer ───────────────────────────────────────────────────── --}}
            <aside id="usuarios-drawer"
                   class="usuarios-drawer d-none"
                   hidden
                   role="complementary"
                   aria-label="Detalle de usuario">

                {{-- Header --}}
                <div class="drawer-header">
                    <div class="drawer-avatar" aria-hidden="true">U</div>
                    <div class="drawer-user-info">
                        <div class="drawer-user-name">—</div>
                        <div class="drawer-user-meta">—</div>
                    </div>
                    <button type="button"
                            id="usuarios-drawer-close"
                            class="btn-close ms-auto"
                            aria-label="Cerrar detalle de usuario"></button>
                </div>

                {{-- Tabs --}}
                <div class="drawer-tabs" role="tablist">
                    <button class="drawer-tab-btn active"
                            data-tab="acceso"
                            role="tab"
                            aria-selected="true"
                            id="tab-acceso">
                        Acceso
                    </button>
                    <button class="drawer-tab-btn"
                            data-tab="actividad"
                            role="tab"
                            aria-selected="false"
                            id="tab-actividad">
                        Actividad
                    </button>
                </div>

                {{-- Tab: Acceso --}}
                <div class="drawer-tab-panel" data-tab="acceso" role="tabpanel" aria-labelledby="tab-acceso">
                    <form method="POST" class="drawer-form" action="/usuarios/0">
                        @csrf

                        {{-- Rol asignado --}}
                        <div class="drawer-section">
                            <label class="form-label small fw-semibold mb-1" for="drawer-role-id">
                                Rol asignado
                            </label>
                            <select id="drawer-role-id" name="role_id" class="form-select form-select-sm">
                                <option value="0">Sin asignar</option>
                                @foreach($roleMap as $rId => $rName)
                                    <option value="{{ $rId }}">{{ $rName }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Plantilla rápida --}}
                        @if(!empty($permissionProfiles))
                            <div class="drawer-section">
                                <label class="form-label small fw-semibold mb-1" for="drawer-permission-profile">
                                    Plantilla de permisos
                                </label>
                                <div class="d-flex gap-2">
                                    <select id="drawer-permission-profile" class="form-select form-select-sm flex-grow-1">
                                        <option value="">Seleccionar plantilla…</option>
                                        @foreach($permissionProfiles as $profileKey => $profile)
                                            <option value="{{ $profileKey }}">
                                                {{ $profile['label'] ?? $profileKey }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            id="drawer-profile-apply"
                                            class="btn btn-outline-secondary btn-sm">
                                        Aplicar
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Permisos directos --}}
                        <div class="drawer-section">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="form-label small fw-semibold mb-0">Permisos directos</span>
                                <span style="font-size:0.7rem;color:#64748b">
                                    <span class="perm-legend-direct" aria-hidden="true">●</span> directo
                                    <span class="perm-legend-inherited ms-1" aria-hidden="true">○</span> heredado
                                </span>
                            </div>

                            @foreach($permissionGroups as $groupName => $groupPerms)
                                <div class="perm-group">
                                    <button type="button"
                                            class="perm-group-head"
                                            aria-expanded="false">
                                        <span>{{ $groupName }}</span>
                                        <svg class="perm-chevron"
                                             xmlns="http://www.w3.org/2000/svg"
                                             width="12" height="12"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="2"
                                             stroke-linecap="round"
                                             stroke-linejoin="round"
                                             aria-hidden="true">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </button>
                                    <div class="perm-group-body d-none">
                                        <div class="perm-grid">
                                            @foreach($groupPerms as $permKey => $permLabel)
                                                <div class="perm-check">
                                                    <input type="checkbox"
                                                           class="form-check-input"
                                                           name="permissions[]"
                                                           value="{{ $permKey }}"
                                                           id="dp-{{ $permKey }}"
                                                           data-direct="0"
                                                           aria-label="{{ $permLabel }}">
                                                    <label class="form-check-label"
                                                           for="dp-{{ $permKey }}">
                                                        {{ $permLabel }}
                                                    </label>
                                                    <span class="inherited-tag d-none"
                                                          aria-hidden="true">rol</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Footer --}}
                        <div class="drawer-footer">
                            <a class="drawer-profile-link btn btn-outline-secondary btn-sm w-100 mb-2"
                               href="/usuarios/0/edit">
                                Editar perfil completo
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     width="12" height="12"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round"
                                     aria-hidden="true">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15 3 21 3 21 9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                            </a>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                    Guardar cambios
                                </button>
                                @if($canManageUsers)
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm drawer-delete-btn"
                                            data-user-id="0"
                                            data-username="">
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Tab: Actividad --}}
                <div class="drawer-tab-panel d-none"
                     data-tab="actividad"
                     role="tabpanel"
                     aria-labelledby="tab-actividad">
                    <div class="p-4 text-center text-muted">
                        <i class="mdi mdi-history" style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
                        <small>Historial de actividad — próximamente</small>
                    </div>
                </div>
            </aside>
        </div>

        {{-- ── Delete confirmation modal ────────────────────────────────────── --}}
        <div id="delete-user-modal"
             class="modal-overlay d-none"
             hidden
             role="dialog"
             aria-modal="true"
             aria-labelledby="delete-modal-title">
            <div class="modal-dialog-custom">
                <h5 id="delete-modal-title" class="mb-3 text-danger">
                    <i class="mdi mdi-alert-circle me-2"></i>Confirmar eliminación
                </h5>
                <p class="mb-0">
                    ¿Eliminar a <strong id="delete-modal-username">este usuario</strong>?
                    Esta acción no se puede deshacer.
                </p>
                <form id="delete-modal-form" method="POST" action="/usuarios/0/delete">
                    @csrf
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="button"
                                id="delete-modal-cancel"
                                class="btn btn-secondary btn-sm">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="mdi mdi-delete me-1"></i>Eliminar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        window.__USUARIOS_INDEX__ = {!! json_encode([
            'permissionGroups'     => collect($permissionGroups)->map(fn($g) => array_keys($g))->toArray(),
            'rolesWithPermissions' => $rolesWithPermissions,
            'currentUserId'        => $currentUserId,
            'canManageUsers'       => $canManageUsers,
            'permissionProfiles'   => $permissionProfiles,
        ], JSON_UNESCAPED_UNICODE) !!};
    </script>
    @vite(['resources/css/usuarios.css', 'resources/js/v2/usuarios-index.js'])
@endpush
