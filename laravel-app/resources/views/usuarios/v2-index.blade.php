@php
    /** @var array<int, array<string, mixed>> $users */
    /** @var array<int, string> $roleMap */
    /** @var array<string, string> $permissionLabels */

    $users = $users ?? [];
    $roleMap = $roleMap ?? [];
    $permissionLabels = $permissionLabels ?? [];
    $warnings = $warnings ?? [];
    $canManageUsers = !empty($canManageUsers);
    $currentUserId = isset($currentUserId) ? (int) $currentUserId : 0;

    $especialidadesFiltro = [
        '' => 'Todas',
        'Cirujano Oftalmólogo' => 'Cirujano Oftalmólogo',
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
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
                        </ol>
                    </nav>
                </div>
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
            <div class="alert alert-success">Usuario creado correctamente.</div>
        @elseif(($status ?? null) === 'updated')
            <div class="alert alert-success">Usuario actualizado correctamente.</div>
        @elseif(($status ?? null) === 'deleted')
            <div class="alert alert-success">Usuario eliminado correctamente.</div>
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

        <style>
            .usuarios-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                object-fit: cover;
                background-color: #f1f1f1;
            }

            .usuarios-table thead th[data-sort] {
                cursor: pointer;
                user-select: none;
            }

            .usuarios-table thead th[data-sort] .sort-indicator {
                margin-left: 0.35rem;
                font-size: 0.75em;
                opacity: 0.6;
            }

            .usuarios-filters .form-label {
                font-weight: 600;
            }

            .usuarios-hidden {
                display: none !important;
            }
        </style>

        <div class="card mb-3 usuarios-filters">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label" for="usuariosFiltroBuscar">Buscar</label>
                        <input type="text" id="usuariosFiltroBuscar" class="form-control" placeholder="Nombre, usuario, correo…">
                        <div class="form-text">Filtra en tiempo real sin recargar.</div>
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label" for="usuariosFiltroEspecialidad">Especialidad</label>
                        <select id="usuariosFiltroEspecialidad" class="form-select">
                            @foreach($especialidadesFiltro as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label" for="usuariosFiltroRol">Rol</label>
                        <select id="usuariosFiltroRol" class="form-select">
                            <option value="">Todos</option>
                            @foreach($roleMap as $roleId => $roleName)
                                <option value="{{ $roleId }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label" for="usuariosFiltroEstado">Estado</label>
                        <select id="usuariosFiltroEstado" class="form-select">
                            <option value="">Todos</option>
                            <option value="approved">Aprobado</option>
                            <option value="pending">Pendiente</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" id="usuariosFiltroLimpiar">
                            <i class="mdi mdi-filter-remove"></i> Limpiar
                        </button>
                        <span class="badge bg-light text-dark border align-self-center" id="usuariosFiltroCount">0 mostrados</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover align-middle usuarios-table">
                    <thead class="bg-primary">
                    <tr>
                        <th scope="col">Foto</th>
                        <th scope="col" data-sort="username" aria-sort="none">Usuario <span class="sort-indicator">⇅</span></th>
                        <th scope="col" data-sort="full_name" aria-sort="none">Nombre <span class="sort-indicator">⇅</span></th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Permisos</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Perfil</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        @php
                            $displayName = trim((string) ($user['display_full_name'] ?? $user['username'] ?? 'Usuario'));
                            $initial = mb_strtoupper(mb_substr($displayName !== '' ? $displayName : 'Usuario', 0, 1, 'UTF-8'), 'UTF-8');
                            $username = (string) ($user['username'] ?? '');
                            $fullName = (string) ($user['display_full_name'] ?? '');
                            $email = (string) ($user['email'] ?? '');
                            $especialidad = trim((string) ($user['especialidad'] ?? ''));
                            $roleId = (string) ($user['role_id'] ?? '');
                            $isApproved = !empty($user['is_approved']) ? '1' : '0';
                            $searchIndex = mb_strtolower(trim(implode(' ', array_filter([
                                $username,
                                $fullName,
                                $email,
                                $especialidad,
                                (string) ($user['role_label'] ?? ''),
                            ], static fn ($value): bool => (string) $value !== ''))), 'UTF-8');
                            $nameSort = mb_strtolower(trim(implode(' ', array_filter([
                                $user['last_name'] ?? '',
                                $user['second_last_name'] ?? '',
                                $user['first_name'] ?? '',
                                $user['middle_name'] ?? '',
                                $fullName !== '' ? $fullName : $username,
                            ], static fn ($value): bool => (string) $value !== ''))), 'UTF-8');
                            $completeness = $user['profile_completeness'] ?? ['label' => 'N/D', 'class' => 'bg-secondary', 'ratio' => 0];
                        @endphp
                        <tr
                            data-especialidad="{{ mb_strtolower($especialidad, 'UTF-8') }}"
                            data-role-id="{{ $roleId }}"
                            data-approved="{{ $isApproved }}"
                            data-search="{{ $searchIndex }}"
                        >
                            <td class="text-center">
                                @if(!empty($user['profile_photo_url']))
                                    <img src="{{ $user['profile_photo_url'] }}" alt="Foto de {{ $displayName }}" class="usuarios-avatar">
                                @else
                                    <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary text-white fw-semibold usuarios-avatar">
                                        {{ $initial }}
                                    </span>
                                @endif
                            </td>
                            <td data-sort-value="{{ mb_strtolower($username, 'UTF-8') }}">{{ $username }}</td>
                            <td data-sort-value="{{ $nameSort }}">{{ $fullName !== '' ? $fullName : '—' }}</td>
                            <td>{{ $email !== '' ? $email : '—' }}</td>
                            <td>{{ $user['role_label'] ?? 'Sin asignar' }}</td>
                            <td>
                                @if(empty($user['permisos_lista']))
                                    <span class="badge bg-secondary">Sin permisos</span>
                                @else
                                    @foreach($user['permisos_lista'] as $permission)
                                        <span class="badge bg-light text-dark border border-secondary me-1 mb-1">
                                            {{ $permissionLabels[$permission] ?? $permission }}
                                        </span>
                                    @endforeach
                                @endif
                            </td>
                            <td>
                                @if(!empty($user['is_approved']))
                                    <span class="badge bg-success">Aprobado</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                @endif
                                @if(!empty($user['is_subscribed']))
                                    <span class="badge bg-info">Suscrito</span>
                                @endif
                                <div class="mt-1 small text-muted">
                                    <span class="badge bg-light text-dark border">Sello: {{ $user['seal_status'] ?? 'no disponible' }}</span>
                                    <span class="badge bg-light text-dark border">Firma: {{ $user['signature_status'] ?? 'no disponible' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $completeness['class'] ?? 'bg-secondary' }}">
                                    {{ $completeness['label'] ?? 'N/D' }}
                                    @if(isset($completeness['ratio']))
                                        ({{ number_format(((float) $completeness['ratio']) * 100, 0) }}%)
                                    @endif
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/usuarios/{{ (int) $user['id'] }}/edit" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="mdi mdi-pencil"></i> Editar
                                </a>
                                @if($canManageUsers)
                                    <form action="/usuarios/{{ (int) $user['id'] }}/delete" method="POST" class="d-inline-block"
                                          onsubmit="return confirm('¿Deseas eliminar a {{ addslashes($username !== '' ? $username : 'este usuario') }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" {{ (int) $user['id'] === $currentUserId ? 'disabled' : '' }}>
                                            <i class="mdi mdi-delete"></i> Eliminar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.querySelector('.usuarios-table');
            if (!table) {
                return;
            }

            const headers = table.querySelectorAll('thead th[data-sort]');
            const collator = new Intl.Collator('es', { sensitivity: 'base', numeric: false });
            const filtroBuscar = document.getElementById('usuariosFiltroBuscar');
            const filtroEspecialidad = document.getElementById('usuariosFiltroEspecialidad');
            const filtroRol = document.getElementById('usuariosFiltroRol');
            const filtroEstado = document.getElementById('usuariosFiltroEstado');
            const filtroLimpiar = document.getElementById('usuariosFiltroLimpiar');
            const filtroCount = document.getElementById('usuariosFiltroCount');

            const applyFilters = function () {
                const q = (filtroBuscar ? (filtroBuscar.value || '').trim().toLowerCase() : '');
                const esp = (filtroEspecialidad ? (filtroEspecialidad.value || '').trim().toLowerCase() : '');
                const rol = (filtroRol ? (filtroRol.value || '').trim() : '');
                const est = (filtroEstado ? (filtroEstado.value || '').trim() : '');
                const rows = Array.from(table.querySelectorAll('tbody tr[data-search]'));

                let shown = 0;
                rows.forEach(function (row) {
                    const rowSearch = row.dataset.search || '';
                    const rowEsp = row.dataset.especialidad || '';
                    const rowRole = row.dataset.roleId || '';
                    const rowApproved = row.dataset.approved || '0';

                    let ok = true;
                    if (q && !rowSearch.includes(q)) ok = false;
                    if (ok && esp && rowEsp !== esp) ok = false;
                    if (ok && rol && rowRole !== rol) ok = false;
                    if (ok && est === 'approved' && rowApproved !== '1') ok = false;
                    if (ok && est === 'pending' && rowApproved !== '0') ok = false;

                    row.classList.toggle('usuarios-hidden', !ok);
                    if (ok) {
                        shown++;
                    }
                });

                if (filtroCount) {
                    filtroCount.textContent = shown + ' mostrados';
                }
            };

            if (filtroBuscar) filtroBuscar.addEventListener('input', applyFilters);
            if (filtroEspecialidad) filtroEspecialidad.addEventListener('change', applyFilters);
            if (filtroRol) filtroRol.addEventListener('change', applyFilters);
            if (filtroEstado) filtroEstado.addEventListener('change', applyFilters);
            if (filtroLimpiar) {
                filtroLimpiar.addEventListener('click', function () {
                    if (filtroBuscar) filtroBuscar.value = '';
                    if (filtroEspecialidad) filtroEspecialidad.value = '';
                    if (filtroRol) filtroRol.value = '';
                    if (filtroEstado) filtroEstado.value = '';
                    applyFilters();
                });
            }

            applyFilters();

            headers.forEach(function (header) {
                header.addEventListener('click', function () {
                    const tbody = table.querySelector('tbody');
                    if (!tbody) {
                        return;
                    }

                    const currentSort = header.getAttribute('aria-sort');
                    const newDirection = currentSort === 'ascending' ? 'descending' : 'ascending';

                    headers.forEach(function (otherHeader) {
                        otherHeader.setAttribute('aria-sort', 'none');
                    });
                    header.setAttribute('aria-sort', newDirection);

                    const columnIndex = Array.prototype.indexOf.call(header.parentElement.children, header);
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    rows.sort(function (rowA, rowB) {
                        const cellA = rowA.children[columnIndex];
                        const cellB = rowB.children[columnIndex];
                        const valueA = cellA ? (cellA.dataset.sortValue || cellA.textContent || '') : '';
                        const valueB = cellB ? (cellB.dataset.sortValue || cellB.textContent || '') : '';
                        const comparison = collator.compare(valueA.trim(), valueB.trim());

                        return newDirection === 'ascending' ? comparison : -comparison;
                    });

                    rows.forEach(function (row) {
                        tbody.appendChild(row);
                    });

                    applyFilters();
                });
            });
        });
    </script>
@endpush
