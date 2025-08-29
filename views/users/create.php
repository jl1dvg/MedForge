<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\UserController;

$controller = new UserController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;

    // Manejo de carga de archivo de firma
    if (isset($_FILES['firma']) && $_FILES['firma']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../public/assets/firmas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = uniqid('firma_') . '.' . pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['firma']['tmp_name'], $filePath)) {
            $data['firma'] = '/public/assets/firmas/' . $filename;
        } else {
            $data['firma'] = '';
        }
    } else {
        $data['firma'] = '';
    }

    $success = $controller->getUserModel()->createUser($data);
    if ($success) {
        echo 'ok';
    } else {
        echo 'error';
    }
    exit();
}
?>
<form method="POST" enctype="multipart/form-data">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Nuevo Usuario</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Cerrar"></button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Usuario:</label>
                    <input type="text" class="form-control" name="username" required maxlength="50">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña:</label>
                    <input type="password" class="form-control" name="password" required maxlength="255"
                           autocomplete="new-password"></div>
                <div class="col-md-6">
                    <label class="form-label">Email:</label>
                    <input type="email" class="form-control" name="email" required maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nombre:</label>
                    <input type="text" class="form-control" name="nombre" required maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cédula:</label>
                    <input type="text" class="form-control" name="cedula" required maxlength="20">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Registro:</label>
                    <input type="text" class="form-control" name="registro" maxlength="50">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sede:</label>
                    <input type="text" class="form-control" name="sede" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Firma (imagen):</label>
                    <input type="file" class="form-control" name="firma" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Especialidad:</label>
                    <select class="form-select" name="especialidad" required>
                        <option value="">Seleccione una especialidad</option>
                        <option value="Anestesiologo">Anestesiólogo</option>
                        <option value="Asistente">Asistente</option>
                        <option value="Cirujano Oftalmólogo">Cirujano Oftalmólogo</option>
                        <option value="Enfermera">Enfermera</option>
                        <option value="Optometrista">Optometrista</option>
                        <option value="Administrativo">Administrativo</option>
                        <option value="Facturación">Facturación</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subespecialidad:</label>
                    <input type="text" class="form-control" name="subespecialidad" maxlength="100">
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check me-3">
                        <input type="checkbox" class="form-check-input" name="is_subscribed" id="is_subscribed"
                               value="1">
                        <label class="form-check-label" for="is_subscribed">Suscrito</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_approved" id="is_approved" value="1">
                        <label class="form-check-label" for="is_approved">Aprobado</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Permisos:</label>
                    <select class="form-select" name="permisos" required>
                        <option value="">Seleccione un tipo de permiso</option>
                        <option value="clinico">Clínico</option>
                        <option value="facturacion">Facturación</option>
                        <option value="administrativo">Administrativo</option>
                        <option value="superuser">Superusuario</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-success">Crear Usuario</button>
        </div>
    </div>
</form>