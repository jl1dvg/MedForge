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
        <div class="mb-3">
            <label>Usuario:</label>
            <input type="text" class="form-control" name="username"
                   value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Email:</label>
            <input type="email" class="form-control" name="email"
                   value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Nombre:</label>
            <input type="text" class="form-control" name="nombre"
                   value="<?= htmlspecialchars($user['nombre']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Cédula:</label>
            <input type="text" class="form-control" name="cedula"
                   value="<?= htmlspecialchars($user['cedula']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Registro:</label>
            <input type="text" class="form-control" name="registro"
                   value="<?= htmlspecialchars($user['registro']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Sede:</label>
            <input type="text" class="form-control" name="sede"
                   value="<?= htmlspecialchars($user['sede']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Firma (ruta):</label>
            <input type="text" class="form-control" name="firma"
                   value="<?= htmlspecialchars($user['firma']) ?>">
        </div>
        <div class="mb-3">
            <label>Especialidad:</label>
            <input type="text" class="form-control" name="especialidad"
                   value="<?= htmlspecialchars($user['especialidad']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Subespecialidad:</label>
            <input type="text" class="form-control" name="subespecialidad"
                   value="<?= htmlspecialchars($user['subespecialidad']) ?>">
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input"
                   name="is_subscribed" <?= $user['is_subscribed'] ? 'checked' : '' ?> value="1">
            <label class="form-check-label">Suscrito</label>
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input"
                   name="is_approved" <?= $user['is_approved'] ? 'checked' : '' ?> value="1">
            <label class="form-check-label">Aprobado</label>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
    </div>
</form>