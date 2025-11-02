<?php
/** @var array $permissions */
/** @var array $selectedPermissions */
/** @var array $role */
/** @var array $errors */
/** @var string $formAction */
/** @var string $method */

$role = $role ?? [];
$errors = $errors ?? [];
$selectedPermissions = $selectedPermissions ?? [];

if (!function_exists('roles_form_old')) {
    function roles_form_old(array $role, string $key, string $default = ''): string
    {
        return htmlspecialchars($role[$key] ?? $default, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('roles_permission_id')) {
    function roles_permission_id(string $key): string
    {
        $sanitized = preg_replace('/[^a-z0-9_-]/i', '_', $key);
        return 'role_perm_' . $sanitized;
    }
}
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Roles</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/roles">Roles</a></li>
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
                    <h4 class="box-title">Datos del rol</h4>
                </div>
                <div class="box-body">
                    <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nombre del rol *</label>
                            <input type="text" name="name" class="form-control" value="<?= roles_form_old($role, 'name'); ?>" required>
                            <?php if (!empty($errors['name'])): ?>
                                <div class="text-danger small"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" rows="3" class="form-control"><?= roles_form_old($role, 'description'); ?></textarea>
                        </div>

                        <h5 class="mb-3">Permisos</h5>
                        <?php foreach ($permissions as $group => $items): ?>
                            <div class="mb-3">
                                <p class="fw-bold mb-2"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="row g-2">
                                    <?php foreach ($items as $key => $label): ?>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <?php $permId = roles_permission_id($key); ?>
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

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="/roles" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
