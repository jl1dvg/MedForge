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

    // Manejo opcional de contraseña: si viene vacía, no actualizar; si viene, validar y hashear
    if (isset($data['password'])) {
        $password = trim($data['password'] ?? '');
        $password_confirm = trim($data['password_confirm'] ?? '');
        if ($password === '') {
            // No actualizar contraseña
            unset($data['password'], $data['password_confirm']);
        } else {
            if ($password !== $password_confirm) {
                echo 'error:password_mismatch';
                exit();
            }
            // Hash de la nueva contraseña
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            unset($data['password_confirm']);
        }
    }

    // Normalizar checkboxes
    $data['is_subscribed'] = isset($data['is_subscribed']) ? 1 : 0;
    $data['is_approved'] = isset($data['is_approved']) ? 1 : 0;

    // Manejo de carga de archivo de firma (si se sube nueva)
    if (isset($_FILES['firma'])) {
        if ((int)$_FILES['firma']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/assets/firmas/';
            if (!is_dir($uploadDir)) {
                $mk = @mkdir($uploadDir, 0777, true);
                if (!$mk && !is_dir($uploadDir)) {
                    echo 'error:upload_dir';
                    exit();
                }
            }
            $ext = strtolower(pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                echo 'error:upload_type';
                exit();
            }
            $filename = uniqid('firma_') . '.' . $ext;
            $filePath = $uploadDir . $filename;
            if (!is_uploaded_file($_FILES['firma']['tmp_name'])) {
                echo 'error:upload_tmp';
                exit();
            }
            $mv = @move_uploaded_file($_FILES['firma']['tmp_name'], $filePath);
            if ($mv) {
                @chmod($filePath, 0644);
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'asistentecive.consulmed.me';
                $publicRelative = '/public/assets/firmas/' . $filename;
                $data['firma'] = $scheme . '://' . $host . $publicRelative;
            } else {
                echo 'error:upload_move';
                exit();
            }
        } else {
            $code = (int)$_FILES['firma']['error'];
            if ($code === UPLOAD_ERR_NO_FILE) {
                unset($data['firma']);
            } else {
                echo 'error:upload_' . $code;
                exit();
            }
        }
    } else {
        unset($data['firma']);
    }

    $success = $controller->getUserModel()->updateUser($id, $data);
    if ($success) {
        echo 'ok';
    } else {
        echo 'error';
    }
    exit();
}
?>
<form method="POST" enctype="multipart/form-data">
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
            <label class="form-label">Nueva contraseña (opcional):</label>
            <div class="input-group rounded shadow-sm">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" name="password" id="password_edit" maxlength="255"
                       autocomplete="new-password" placeholder="Déjalo vacío para no cambiar">
                <button class="btn btn-outline-secondary" type="button" id="toggle_pass_edit" tabindex="-1">👁️</button>
            </div>
            <small class="text-muted">Si no deseas cambiarla, deja este campo vacío.</small>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Confirmar nueva contraseña:</label>
            <input type="password" class="form-control rounded shadow-sm" name="password_confirm"
                   id="password_confirm_edit" maxlength="255" autocomplete="new-password"
                   placeholder="Repite la nueva contraseña">
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
            <label class="form-label">Firma (imagen):</label>
            <?php if (!empty($user['firma'])): ?>
                <div class="mb-2">
                    <img src="<?= htmlspecialchars($user['firma']) ?>" alt="Firma" style="max-height:80px;">
                </div>
            <?php endif; ?>
            <input type="file" class="form-control" name="firma" id="firma_input" accept="image/*">
            <small class="text-muted">Dejar vacío si no deseas cambiar la firma.</small>
        </div>
        <div class="form-group mb-3">
            <label class="form-label">Especialidad:</label>
            <select class="form-control rounded shadow-sm" name="especialidad" required>
                <option value="">Seleccione</option>
                <option value="Anestesiologo" <?= $user['especialidad'] === 'Anestesiologo' ? 'selected' : '' ?>>
                    Anestesiólogo
                </option>
                <option value="Asistente" <?= $user['especialidad'] === 'Asistente' ? 'selected' : '' ?>>Asistente
                </option>
                <option value="Cirujano Oftalmólogo" <?= $user['especialidad'] === 'Cirujano Oftalmólogo' ? 'selected' : '' ?>>
                    Cirujano Oftalmólogo
                </option>
                <option value="Enfermera" <?= $user['especialidad'] === 'Enfermera' ? 'selected' : '' ?>>Enfermera
                </option>
                <option value="Optometrista" <?= $user['especialidad'] === 'Optometrista' ? 'selected' : '' ?>>
                    Optometrista
                </option>
                <option value="Administrativo" <?= $user['especialidad'] === 'Administrativo' ? 'selected' : '' ?>>
                    Administrativo
                </option>
                <option value="Facturación" <?= $user['especialidad'] === 'Facturación' ? 'selected' : '' ?>>
                    Facturación
                </option>
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
                <option value="facturacion" <?= $user['permisos'] === 'facturacion' ? 'selected' : '' ?>>Facturación
                </option>
                <option value="administrativo" <?= $user['permisos'] === 'administrativo' ? 'selected' : '' ?>>
                    Administrativo
                </option>
                <option value="superuser" <?= $user['permisos'] === 'superuser' ? 'selected' : '' ?>>Superusuario
                </option>
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
<script>
    (function () {
        const form = document.currentScript.closest('form');
        const pass = form.querySelector('#password_edit');
        const pass2 = form.querySelector('#password_confirm_edit');
        const toggle = form.querySelector('#toggle_pass_edit');
        if (toggle && pass) {
            toggle.addEventListener('click', function () {
                const type = pass.getAttribute('type') === 'password' ? 'text' : 'password';
                pass.setAttribute('type', type);
                if (pass2) pass2.setAttribute('type', type);
            });
        }
        form.addEventListener('submit', function (e) {
            if (pass && pass.value.trim() !== '') {
                if (!pass2 || pass.value !== pass2.value) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden.');
                }
            }
        });
    })();
</script>