<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\UserController;

$id = $_GET['id'] ?? null;

if (!$id) {
    die('ID de usuario no especificado.');
}

$controller = new UserController($pdo);
$user = $controller->getUserModel()->getUserById($id);

if (!$user) {
    die('Usuario no encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $success = $controller->getUserModel()->updateUser($id, $data);
    if ($success) {
        echo 'ok';
    } else {
        echo 'error';
    }
    exit();
}
?>
<form method="POST">
    <div class="modal-header">
        <h5 class="modal-title">Editar Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body">
        <div class="form-group mb-3">
            <label class="form-label">Usuario:</label>
            <input type="text" class="form-control rounded shadow-sm" name="username"
                   value="<?= htmlspecialchars($user['username']) ?>" required maxlength="50">
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Email:</label>
            <div class="input-group rounded shadow-sm">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" name="email"
                       value="<?= htmlspecialchars($user['email']) ?>" required maxlength="100">
            </div>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Nombre:</label>
            <input type="text" class="form-control rounded shadow-sm" name="nombre"
                   value="<?= htmlspecialchars($user['nombre']) ?>" required maxlength="255">
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Cédula:</label>
            <div class="input-group rounded shadow-sm">
                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                <input type="text" class="form-control" name="cedula"
                       value="<?= htmlspecialchars($user['cedula']) ?>" required maxlength="20">
            </div>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Registro:</label>
            <input type="text" class="form-control rounded shadow-sm" name="registro"
                   value="<?= htmlspecialchars($user['registro']) ?>" maxlength="50">
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Sede:</label>
            <input type="text" class="form-control rounded shadow-sm" name="sede"
                   value="<?= htmlspecialchars($user['sede']) ?>" maxlength="100">
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Firma (ruta):</label>
            <input type="text" class="form-control rounded shadow-sm" name="firma"
                   value="<?= htmlspecialchars($user['firma']) ?>" maxlength="255">
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Especialidad:</label>
            <select class="form-control rounded shadow-sm" name="especialidad" required>
                <option value="">Seleccione</option>
                <option value="Anestesiologo" <?= $user['especialidad'] === 'Anestesiologo' ? 'selected' : '' ?>>Anestesiólogo</option>
                <option value="Asistente" <?= $user['especialidad'] === 'Asistente' ? 'selected' : '' ?>>Asistente</option>
                <option value="Cirujano Oftalmólogo" <?= $user['especialidad'] === 'Cirujano Oftalmólogo' ? 'selected' : '' ?>>Cirujano Oftalmólogo</option>
                <option value="Enfermera" <?= $user['especialidad'] === 'Enfermera' ? 'selected' : '' ?>>Enfermera</option>
                <option value="Optometrista" <?= $user['especialidad'] === 'Optometrista' ? 'selected' : '' ?>>Optometrista</option>
                <option value="Administrativo" <?= $user['especialidad'] === 'Administrativo' ? 'selected' : '' ?>>Administrativo</option>
                <option value="Facturación" <?= $user['especialidad'] === 'Facturación' ? 'selected' : '' ?>>Facturación</option>
            </select>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Subespecialidad:</label>
            <input type="text" class="form-control rounded shadow-sm" name="subespecialidad"
                   value="<?= htmlspecialchars($user['subespecialidad']) ?>" maxlength="100">
        </div>
        <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" id="is_subscribed"
                   name="is_subscribed" <?= $user['is_subscribed'] ? 'checked' : '' ?> value="1">
            <label class="form-check-label" for="is_subscribed">Suscrito</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" id="is_approved"
                   name="is_approved" <?= $user['is_approved'] ? 'checked' : '' ?> value="1">
            <label class="form-check-label" for="is_approved">Aprobado</label>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Permisos:</label>
            <select class="form-control rounded shadow-sm" name="permisos" required>
                <option value="">Seleccione</option>
                <option value="clinico" <?= $user['permisos'] === 'clinico' ? 'selected' : '' ?>>Clínico</option>
                <option value="facturacion" <?= $user['permisos'] === 'facturacion' ? 'selected' : '' ?>>Facturación</option>
                <option value="administrativo" <?= $user['permisos'] === 'administrativo' ? 'selected' : '' ?>>Administrativo</option>
                <option value="superuser" <?= $user['permisos'] === 'superuser' ? 'selected' : '' ?>>Superusuario</option>
            </select>
            <?php if ($user['permisos']): ?>
                <span class="badge bg-primary mt-2 text-capitalize"><?= htmlspecialchars($user['permisos']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
    </div>
</form>