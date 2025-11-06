<?php
/** @var array $roles */
/** @var array $permissions */
/** @var array $selectedPermissions */
/** @var array $usuario */
/** @var array $errors */
/** @var string $formAction */
/** @var string $method */

$usuario = $usuario ?? [];
$errors = $errors ?? [];
$selectedPermissions = $selectedPermissions ?? [];

if (!function_exists('usuarios_form_old')) {
    function usuarios_form_old(array $usuario, string $key, string $default = ''): string
    {
        return htmlspecialchars($usuario[$key] ?? $default, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('usuarios_form_checked')) {
    function usuarios_form_checked(array $usuario, string $key): string
    {
        return !empty($usuario[$key]) ? 'checked' : '';
    }
}

if (!function_exists('usuarios_permission_id')) {
    function usuarios_permission_id(string $key): string
    {
        $sanitized = preg_replace('/[^a-z0-9_-]/i', '_', $key);
        return 'perm_' . $sanitized;
    }
}
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Usuarios</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/usuarios">Usuarios</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Formulario</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-lg-8">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Datos del usuario</h4>
                </div>
                <div class="box-body">
                    <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre de usuario *</label>
                                <input type="text" name="username" class="form-control" value="<?= usuarios_form_old($usuario, 'username'); ?>" required>
                                <?php if (!empty($errors['username'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo electrónico</label>
                                <input type="email" name="email" class="form-control" value="<?= usuarios_form_old($usuario, 'email'); ?>">
                                <?php if (!empty($errors['email'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre completo</label>
                                <input type="text" name="nombre" class="form-control" value="<?= usuarios_form_old($usuario, 'nombre'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cédula</label>
                                <input type="text" name="cedula" class="form-control" value="<?= usuarios_form_old($usuario, 'cedula'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registro</label>
                                <input type="text" name="registro" class="form-control" value="<?= usuarios_form_old($usuario, 'registro'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sede</label>
                                <input type="text" name="sede" class="form-control" value="<?= usuarios_form_old($usuario, 'sede'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Especialidad</label>
                                <input type="text" name="especialidad" class="form-control" value="<?= usuarios_form_old($usuario, 'especialidad'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subespecialidad</label>
                                <input type="text" name="subespecialidad" class="form-control" value="<?= usuarios_form_old($usuario, 'subespecialidad'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Firma</label>
                                <?php
                                $firmaPath = $usuario['firma'] ?? null;
                                if ($firmaPath && !preg_match('/^https?:/i', $firmaPath)) {
                                    $firmaPath = rtrim(BASE_URL, '/') . '/' . ltrim($firmaPath, '/');
                                }
                                ?>
                                <?php if (!empty($usuario['firma'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($firmaPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Firma actual" class="img-fluid border rounded" style="max-height: 120px;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_firma" id="remove_firma" value="1">
                                        <label class="form-check-label" for="remove_firma">Eliminar firma actual</label>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="firma_file" class="form-control" accept="image/png,image/jpeg,image/webp">
                                <small class="text-muted">Formatos permitidos: PNG, JPG o WEBP. Tamaño máximo 2&nbsp;MB.</small>
                                <?php if (!empty($errors['firma_file'])): ?>
                                    <div class="text-danger small mt-1"><?= htmlspecialchars($errors['firma_file'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Foto de perfil</label>
                                <?php
                                $fotoPath = $usuario['profile_photo'] ?? null;
                                if ($fotoPath && !preg_match('/^https?:/i', $fotoPath)) {
                                    $fotoPath = rtrim(BASE_URL, '/') . '/' . ltrim($fotoPath, '/');
                                }
                                ?>
                                <?php if (!empty($usuario['profile_photo'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($fotoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil actual" class="img-thumbnail" style="max-height: 120px;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_profile_photo" id="remove_profile_photo" value="1">
                                        <label class="form-check-label" for="remove_profile_photo">Eliminar foto actual</label>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="profile_photo_file" class="form-control" accept="image/png,image/jpeg,image/webp">
                                <small class="text-muted">Recomendado 400x400px. Tamaño máximo 2&nbsp;MB.</small>
                                <?php if (!empty($errors['profile_photo_file'])): ?>
                                    <div class="text-danger small mt-1"><?= htmlspecialchars($errors['profile_photo_file'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <?= isset($usuario['id']) ? '(dejar en blanco para mantener)' : '*'; ?></label>
                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                                <?php if (!empty($errors['password'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rol</label>
                                <select name="role_id" class="form-select">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= (int) $rol['id']; ?>" <?= (isset($usuario['role_id']) && (int) $usuario['role_id'] === (int) $rol['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($rol['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['role_id'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['role_id'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_approved" id="is_approved" <?= usuarios_form_checked($usuario, 'is_approved'); ?>>
                                    <label class="form-check-label" for="is_approved">Usuario aprobado</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_subscribed" id="is_subscribed" <?= usuarios_form_checked($usuario, 'is_subscribed'); ?>>
                                    <label class="form-check-label" for="is_subscribed">Recibe notificaciones</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div>
                            <h5 class="mb-3">Permisos</h5>
                            <?php foreach ($permissions as $group => $items): ?>
                                <div class="mb-3">
                                    <p class="fw-bold mb-2"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="row g-2">
                                        <?php foreach ($items as $key => $label): ?>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <?php $permId = usuarios_permission_id($key); ?>
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" id="<?= htmlspecialchars($permId, ENT_QUOTES, 'UTF-8'); ?>" <?= in_array($key, $selectedPermissions, true) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="<?= htmlspecialchars($permId, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="/usuarios" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
