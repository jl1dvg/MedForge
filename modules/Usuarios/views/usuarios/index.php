<?php
/** @var array $usuarios */
/** @var array $roleMap */
/** @var array $permissionLabels */
/** @var string|null $status */
/** @var string|null $error */

/** @var array $warnings */

use Core\Permissions;

$status = $status ?? null;
$error = $error ?? null;
$warnings = $warnings ?? [];
$sessionPermissions = Permissions::normalize($_SESSION['permisos'] ?? []);
$canCreateUsers = Permissions::containsAny($sessionPermissions, ['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
$canEditUsers = $canCreateUsers;
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Usuarios</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
                    </ol>
                </nav>
            </div>
        </div>
        <?php if ($canCreateUsers): ?>
            <a href="/usuarios/create" class="btn btn-primary"><i class="mdi mdi-account-plus"></i> Nuevo usuario</a>
        <?php endif; ?>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <?php if ($status === 'created'): ?>
                        <div class="alert alert-success">Usuario creado correctamente.</div>
                    <?php elseif ($status === 'updated'): ?>
                        <div class="alert alert-success">Usuario actualizado correctamente.</div>
                    <?php elseif ($status === 'deleted'): ?>
                        <div class="alert alert-success">Usuario eliminado correctamente.</div>
                    <?php endif; ?>

                    <?php if (!empty($warnings)): ?>
                        <div class="alert alert-warning">
                            <p class="mb-2 fw-semibold"><i class="mdi mdi-alert"></i> Avisos importantes:</p>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($warnings as $warning): ?>
                                    <li><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($error === 'not_found'): ?>
                        <div class="alert alert-warning">No se encontró el usuario solicitado.</div>
                    <?php elseif ($error === 'cannot_delete_self'): ?>
                        <div class="alert alert-danger">No puedes eliminar tu propio usuario.</div>
                    <?php endif; ?>
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

                        .usuarios-filters .btn {
                            white-space: nowrap;
                        }
                    </style>
                    <?php
                    // Filtros (cliente) para listado de usuarios
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

                    $rolesFiltro = ['' => 'Todos'];
                    foreach (($roleMap ?? []) as $rid => $rname) {
                        $rolesFiltro[(string)$rid] = (string)$rname;
                    }
                    ?>

                    <div class="card mb-3 usuarios-filters">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4">
                                    <label class="form-label" for="usuariosFiltroBuscar">Buscar</label>
                                    <input type="text" id="usuariosFiltroBuscar" class="form-control"
                                           placeholder="Nombre, usuario, correo…">
                                    <div class="form-text">Filtra en tiempo real sin recargar.</div>
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label" for="usuariosFiltroEspecialidad">Especialidad</label>
                                    <select id="usuariosFiltroEspecialidad" class="form-select">
                                        <?php foreach ($especialidadesFiltro as $valor => $label): ?>
                                            <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label" for="usuariosFiltroRol">Rol</label>
                                    <select id="usuariosFiltroRol" class="form-select">
                                        <?php foreach ($rolesFiltro as $valor => $label): ?>
                                            <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
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
                                    <span class="badge bg-light text-dark border align-self-center"
                                          id="usuariosFiltroCount">0 mostrados</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle usuarios-table">
                            <thead class="bg-primary">
                            <tr>
                                <th scope="col">Foto</th>
                                <th scope="col" data-sort="username" aria-sort="none">Usuario <span
                                            class="sort-indicator">⇅</span></th>
                                <th scope="col" data-sort="full_name" aria-sort="none">Nombre <span
                                            class="sort-indicator">⇅</span></th>
                                <th scope="col">Correo</th>
                                <th scope="col">Rol</th>
                                <th scope="col">Permisos</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Perfil</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No hay usuarios registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <?php
                                    $username = (string)($usuario['username'] ?? '');
                                    $fullName = trim((string)($usuario['display_full_name'] ?? ''));
                                    if ($fullName === '') {
                                        $fullName = trim((string)($usuario['nombre'] ?? ''));
                                    }
                                    $fullName = $fullName !== '' ? $fullName : trim(implode(' ', array_filter([
                                            $usuario['first_name'] ?? '',
                                            $usuario['middle_name'] ?? '',
                                            $usuario['last_name'] ?? '',
                                            $usuario['second_last_name'] ?? '',
                                    ], static fn($v) => (string)$v !== '')));
                                    if ($fullName === '') {
                                        $fullName = $username;
                                    }
                                    $profilePhotoUrl = format_profile_photo_url($usuario['profile_photo'] ?? null);
                                    $displayName = $fullName !== '' ? $fullName : $username;
                                    $initial = $displayName !== '' ? $displayName : 'Usuario';
                                    $initial = mb_strtoupper(mb_substr($initial, 0, 1, 'UTF-8'), 'UTF-8');
                                    $usernameSortValue = mb_strtolower($username, 'UTF-8');
                                    $nombreSortValue = mb_strtolower(trim(implode(' ', array_filter([
                                            $usuario['last_name'] ?? '',
                                            $usuario['second_last_name'] ?? '',
                                            $usuario['first_name'] ?? '',
                                            $usuario['middle_name'] ?? '',
                                            $fullName !== '' ? $fullName : $username,
                                    ], static fn($v) => (string)$v !== ''))), 'UTF-8');
                                    ?>
                                    <?php
                                    $especialidadRow = trim((string)($usuario['especialidad'] ?? ''));
                                    $roleIdRow = (string)($usuario['role_id'] ?? '');
                                    $isApprovedRow = !empty($usuario['is_approved']) ? '1' : '0';
                                    $searchIndexRow = mb_strtolower(trim(implode(' ', array_filter([
                                            $username,
                                            $fullName,
                                            (string)($usuario['email'] ?? ''),
                                            $especialidadRow,
                                            (string)($roleMap[$usuario['role_id']] ?? ''),
                                    ], static fn($v) => (string)$v !== ''))), 'UTF-8');
                                    ?>
                                    <tr data-especialidad="<?= htmlspecialchars(mb_strtolower($especialidadRow, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-role-id="<?= htmlspecialchars($roleIdRow, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-approved="<?= htmlspecialchars($isApprovedRow, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-search="<?= htmlspecialchars($searchIndexRow, ENT_QUOTES, 'UTF-8'); ?>">
                                        <td class="text-center">
                                            <?php if ($profilePhotoUrl): ?>
                                                <img src="<?= htmlspecialchars($profilePhotoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt="Foto de <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>"
                                                     class="usuarios-avatar">
                                            <?php else: ?>
                                                <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary text-white fw-semibold usuarios-avatar">
                                                        <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-sort-value="<?= htmlspecialchars($usernameSortValue, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td data-sort-value="<?= htmlspecialchars($nombreSortValue, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($roleMap[$usuario['role_id']] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (empty($usuario['permisos_lista'])): ?>
                                                <span class="badge bg-secondary">Sin permisos</span>
                                            <?php else: ?>
                                                <?php foreach ($usuario['permisos_lista'] as $permiso): ?>
                                                    <?php $label = $permissionLabels[$permiso] ?? $permiso; ?>
                                                    <span class="badge bg-light text-dark border border-secondary me-1 mb-1"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($usuario['is_approved'])): ?>
                                                <span class="badge bg-success">Aprobado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            <?php endif; ?>
                                            <?php if (!empty($usuario['is_subscribed'])): ?>
                                                <span class="badge bg-info">Suscrito</span>
                                            <?php endif; ?>
                                            <div class="mt-1 small text-muted">
                                                <span class="badge bg-light text-dark border">Sello: <?= htmlspecialchars($usuario['seal_status'] ?? 'no disponible', ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="badge bg-light text-dark border">Firma: <?= htmlspecialchars($usuario['signature_status'] ?? 'no disponible', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php $completeness = $usuario['profile_completeness'] ?? ['label' => 'N/D', 'class' => 'bg-secondary', 'ratio' => 0]; ?>
                                            <span class="badge <?= htmlspecialchars($completeness['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?= htmlspecialchars($completeness['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (isset($completeness['ratio'])): ?>
                                                    (<?= number_format(($completeness['ratio'] ?? 0) * 100, 0); ?>%)
                                                <?php endif; ?>
                                                </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($canEditUsers): ?>
                                                <a href="/usuarios/edit?id=<?= (int)$usuario['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary me-1">
                                                    <i class="mdi mdi-pencil"></i> Editar
                                                </a>
                                                <form action="/usuarios/delete" method="POST" class="d-inline-block"
                                                      onsubmit="return confirm('¿Deseas eliminar a <?= htmlspecialchars($usuario['username'] ?? 'este usuario', ENT_QUOTES, 'UTF-8'); ?>?');">
                                                    <input type="hidden" name="id" value="<?= (int)$usuario['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="mdi mdi-delete"></i> Eliminar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">Sin permisos de edición</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$inlineScripts[] = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('.usuarios-table');
    if (!table) {
        return;
    }

    const headers = table.querySelectorAll('thead th[data-sort]');
    if (!headers.length) {
        return;
    }

    const collator = new Intl.Collator('es', { sensitivity: 'base', numeric: false });

    // --- Filtros (cliente) ---
    const filtroBuscar = document.getElementById('usuariosFiltroBuscar');
    const filtroEspecialidad = document.getElementById('usuariosFiltroEspecialidad');
    const filtroRol = document.getElementById('usuariosFiltroRol');
    const filtroEstado = document.getElementById('usuariosFiltroEstado');
    const filtroLimpiar = document.getElementById('usuariosFiltroLimpiar');
    const filtroCount = document.getElementById('usuariosFiltroCount');

    const applyFilters = () => {
        const q = (filtroBuscar ? (filtroBuscar.value || '').trim().toLowerCase() : '');
        const esp = (filtroEspecialidad ? (filtroEspecialidad.value || '').trim().toLowerCase() : '');
        const rol = (filtroRol ? (filtroRol.value || '').trim() : '');
        const est = (filtroEstado ? (filtroEstado.value || '').trim() : '');

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody ? tbody.querySelectorAll('tr[data-search]') : []);

        let shown = 0;
        rows.forEach((row) => {
            const rowSearch = (row.dataset.search || '');
            const rowEsp = (row.dataset.especialidad || '');
            const rowRole = (row.dataset.roleId || '');
            const rowApproved = (row.dataset.approved || '0');

            let ok = true;
            if (q && !rowSearch.includes(q)) ok = false;
            if (ok && esp && rowEsp !== esp) ok = false;
            if (ok && rol && rowRole !== rol) ok = false;
            if (ok && est === 'approved' && rowApproved !== '1') ok = false;
            if (ok && est === 'pending' && rowApproved !== '0') ok = false;

            if (ok) {
                row.classList.remove('usuarios-hidden');
                shown++;
            } else {
                row.classList.add('usuarios-hidden');
            }
        });

        // Manejo del row "No hay usuarios" (cuando aplica) o conteo
        if (filtroCount) {
            filtroCount.textContent = `${shown} mostrados`;
        }
    };

    const bindFilterEvents = () => {
        if (filtroBuscar) filtroBuscar.addEventListener('input', applyFilters);
        if (filtroEspecialidad) filtroEspecialidad.addEventListener('change', applyFilters);
        if (filtroRol) filtroRol.addEventListener('change', applyFilters);
        if (filtroEstado) filtroEstado.addEventListener('change', applyFilters);
        if (filtroLimpiar) {
            filtroLimpiar.addEventListener('click', () => {
                if (filtroBuscar) filtroBuscar.value = '';
                if (filtroEspecialidad) filtroEspecialidad.value = '';
                if (filtroRol) filtroRol.value = '';
                if (filtroEstado) filtroEstado.value = '';
                applyFilters();
            });
        }
    };

    bindFilterEvents();
    applyFilters();

    headers.forEach((header) => {
        header.addEventListener('click', () => {
            const tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }

            const currentSort = header.getAttribute('aria-sort');
            const newDirection = currentSort === 'ascending' ? 'descending' : 'ascending';

            headers.forEach((otherHeader) => {
                otherHeader.setAttribute('aria-sort', 'none');
            });
            header.setAttribute('aria-sort', newDirection);

            const columnIndex = Array.prototype.indexOf.call(header.parentElement.children, header);
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((rowA, rowB) => {
                const cellA = rowA.children[columnIndex];
                const cellB = rowB.children[columnIndex];

                const valueA = cellA ? (cellA.dataset.sortValue || cellA.textContent || '') : '';
                const valueB = cellB ? (cellB.dataset.sortValue || cellB.textContent || '') : '';

                const comparison = collator.compare(valueA.trim(), valueB.trim());
                return newDirection === 'ascending' ? comparison : -comparison;
            });

            rows.forEach((row) => {
                tbody.appendChild(row);
            });

            applyFilters();
        });
    });
});
JS;
?>
