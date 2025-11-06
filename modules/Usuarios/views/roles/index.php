<?php
/** @var array $roles */
/** @var array $permissionLabels */
/** @var string|null $status */
/** @var string|null $error */

use Core\Permissions;

$status = $status ?? null;
$error = $error ?? null;
$sessionPermissions = Permissions::normalize($_SESSION['permisos'] ?? []);
$canCreateRoles = Permissions::containsAny($sessionPermissions, ['administrativo', 'admin.roles.manage', 'admin.roles']);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Roles</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/usuarios">Usuarios</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Roles</li>
                    </ol>
                </nav>
            </div>
        </div>
        <?php if ($canCreateRoles): ?>
            <a href="/roles/create" class="btn btn-primary"><i class="mdi mdi-shield-plus"></i> Nuevo rol</a>
        <?php endif; ?>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <?php if ($status === 'created'): ?>
                        <div class="alert alert-success">Rol creado correctamente.</div>
                    <?php elseif ($status === 'updated'): ?>
                        <div class="alert alert-success">Rol actualizado correctamente.</div>
                    <?php elseif ($status === 'deleted'): ?>
                        <div class="alert alert-success">Rol eliminado correctamente.</div>
                    <?php endif; ?>

                    <?php if ($error === 'not_found'): ?>
                        <div class="alert alert-warning">No se encontró el rol solicitado.</div>
                    <?php elseif ($error === 'role_in_use'): ?>
                        <div class="alert alert-danger">No se puede eliminar un rol asignado a usuarios.</div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Permisos</th>
                                    <th>Usuarios asignados</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($roles)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No hay roles registrados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($roles as $rol): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rol['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($rol['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php if (empty($rol['permissions_list'])): ?>
                                                    <span class="badge bg-secondary">Sin permisos</span>
                                                <?php else: ?>
                                                    <?php foreach ($rol['permissions_list'] as $permiso): ?>
                                                        <?php $label = $permissionLabels[$permiso] ?? $permiso; ?>
                                                        <span class="badge bg-light text-dark border border-secondary me-1 mb-1"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?= (int) ($rol['users_count'] ?? 0); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($canCreateRoles): ?>
                                                    <a href="/roles/edit?id=<?= (int) $rol['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="mdi mdi-pencil"></i> Editar
                                                    </a>
                                                    <form action="/roles/delete" method="POST" class="d-inline-block" onsubmit="return confirm('¿Eliminar el rol <?= htmlspecialchars($rol['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?');">
                                                        <input type="hidden" name="id" value="<?= (int) $rol['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="mdi mdi-delete"></i> Eliminar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Solo lectura</span>
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
