<?php
/** @var array $usuarios */
/** @var array $roleMap */
/** @var array $permissionLabels */
/** @var string|null $status */
/** @var string|null $error */
$status = $status ?? null;
$error = $error ?? null;
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
        <a href="/usuarios/create" class="btn btn-primary"><i class="mdi mdi-account-plus"></i> Nuevo usuario</a>
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
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Permisos</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No hay usuarios registrados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($usuario['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($usuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
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
                                                <a href="/usuarios/edit?id=<?= (int) $usuario['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                    <i class="mdi mdi-pencil"></i> Editar
                                                </a>
                                                <form action="/usuarios/delete" method="POST" class="d-inline-block" onsubmit="return confirm('¿Deseas eliminar a <?= htmlspecialchars($usuario['username'] ?? 'este usuario', ENT_QUOTES, 'UTF-8'); ?>?');">
                                                    <input type="hidden" name="id" value="<?= (int) $usuario['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="mdi mdi-delete"></i> Eliminar
                                                    </button>
                                                </form>
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
