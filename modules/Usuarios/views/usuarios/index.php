<?php
/** @var array $usuarios */
/** @var array $roleMap */
/** @var array $permissionLabels */
/** @var string|null $status */
/** @var string|null $error */

use Core\Permissions;

$status = $status ?? null;
$error = $error ?? null;
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

                        .usuarios-table thead th[data-sort]
                        {
                            cursor: pointer;
                            user-select: none;
                        }

                        .usuarios-table thead th[data-sort] .sort-indicator {
                            margin-left: 0.35rem;
                            font-size: 0.75em;
                            opacity: 0.6;
                        }
                    </style>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle usuarios-table">
                            <thead class="bg-primary">
                                <tr>
                                    <th scope="col">Foto</th>
                                    <th scope="col" data-sort="username" aria-sort="none">Usuario <span class="sort-indicator">⇅</span></th>
                                    <th scope="col" data-sort="nombre" aria-sort="none">Nombre <span class="sort-indicator">⇅</span></th>
                                    <th scope="col">Correo</th>
                                    <th scope="col">Rol</th>
                                    <th scope="col">Permisos</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No hay usuarios registrados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <?php
                                        $username = (string) ($usuario['username'] ?? '');
                                        $nombre = (string) ($usuario['nombre'] ?? '');
                                        $profilePhotoUrl = format_profile_photo_url($usuario['profile_photo'] ?? null);
                                        $displayName = $nombre !== '' ? $nombre : $username;
                                        $initial = $displayName !== '' ? $displayName : 'Usuario';
                                        $initial = mb_strtoupper(mb_substr($initial, 0, 1, 'UTF-8'), 'UTF-8');
                                        $usernameSortValue = mb_strtolower($username, 'UTF-8');
                                        $nombreSortValue = mb_strtolower($nombre, 'UTF-8');
                                        ?>
                                        <tr>
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
                                                <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
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
                                            </td>
                                            <td class="text-end">
                                                <?php if ($canEditUsers): ?>
                                                    <a href="/usuarios/edit?id=<?= (int) $usuario['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="mdi mdi-pencil"></i> Editar
                                                    </a>
                                                    <form action="/usuarios/delete" method="POST" class="d-inline-block" onsubmit="return confirm('¿Deseas eliminar a <?= htmlspecialchars($usuario['username'] ?? 'este usuario', ENT_QUOTES, 'UTF-8'); ?>?');">
                                                        <input type="hidden" name="id" value="<?= (int) $usuario['id']; ?>">
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
        });
    });
});
JS;
?>
