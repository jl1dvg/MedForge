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
$warnings = $warnings ?? [];
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
                    <div id="userUploadA11yStatus" class="visually-hidden" aria-live="polite"></div>
                    <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data">
                        <?php if (!empty($warnings)): ?>
                            <div class="alert alert-warning">
                                <p class="mb-2 fw-semibold"><i class="mdi mdi-alert"></i> Posibles duplicados detectados:</p>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($warnings as $warning): ?>
                                        <li><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert" aria-live="assertive" tabindex="-1" data-validation-alert>
                                <i class="mdi mdi-alert-circle-outline"></i> Revisa los campos marcados para continuar.
                            </div>
                        <?php endif; ?>
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
                                <label class="form-label">WhatsApp del agente</label>
                                <input type="text" name="whatsapp_number" class="form-control" value="<?= usuarios_form_old($usuario, 'whatsapp_number'); ?>" placeholder="+593...">
                                <small class="text-muted">Se usará para notificaciones de handoff.</small>
                                <?php if (!empty($errors['whatsapp_number'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['whatsapp_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">Notificaciones WhatsApp</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_notify" name="whatsapp_notify" <?= usuarios_form_checked($usuario, 'whatsapp_notify'); ?>>
                                    <label class="form-check-label" for="whatsapp_notify">Recibir alertas de handoff</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="first_name" class="form-control" maxlength="100"
                                       pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                       value="<?= usuarios_form_old($usuario, 'first_name'); ?>" required>
                                <?php if (!empty($errors['first_name'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Segundo nombre</label>
                                <input type="text" name="middle_name" class="form-control" maxlength="100"
                                       pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                       value="<?= usuarios_form_old($usuario, 'middle_name'); ?>">
                                <?php if (!empty($errors['middle_name'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['middle_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Primer apellido *</label>
                                <input type="text" name="last_name" class="form-control" maxlength="100"
                                       pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                       value="<?= usuarios_form_old($usuario, 'last_name'); ?>" required>
                                <?php if (!empty($errors['last_name'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Segundo apellido</label>
                                <input type="text" name="second_last_name" class="form-control" maxlength="100"
                                       pattern="[A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-'\"\.\s]+"
                                       value="<?= usuarios_form_old($usuario, 'second_last_name'); ?>">
                                <?php if (!empty($errors['second_last_name'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['second_last_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="birth_date" class="form-control" value="<?= usuarios_form_old($usuario, 'birth_date'); ?>">
                                <?php if (!empty($errors['birth_date'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['birth_date'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Identificación nacional</label>
                                <input type="text" name="national_id" class="form-control" maxlength="32" pattern="[A-Za-z0-9-]{4,32}"
                                       value="<?= usuarios_form_old($usuario, 'national_id'); ?>" placeholder="Se mantiene si se deja en blanco al editar">
                                <small class="text-muted">Se almacena de forma protegida. Usa solo letras, números y guiones.</small>
                                <?php if (!empty($usuario['national_id_masked'])): ?>
                                    <div class="text-muted small">Actual: <?= htmlspecialchars($usuario['national_id_masked'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($errors['national_id'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['national_id'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pasaporte</label>
                                <input type="text" name="passport_number" class="form-control" maxlength="32" pattern="[A-Za-z0-9-]{4,32}"
                                       value="<?= usuarios_form_old($usuario, 'passport_number'); ?>" placeholder="Se mantiene si se deja en blanco al editar">
                                <small class="text-muted">Se almacena de forma protegida. Usa solo letras, números y guiones.</small>
                                <?php if (!empty($usuario['passport_number_masked'])): ?>
                                    <div class="text-muted small">Actual: <?= htmlspecialchars($usuario['passport_number_masked'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($errors['passport_number'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['passport_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Nombre completo (solo lectura)</label>
                                <?php
                                $fullName = trim(implode(' ', array_filter([
                                    $usuario['first_name'] ?? '',
                                    $usuario['middle_name'] ?? '',
                                    $usuario['last_name'] ?? '',
                                    $usuario['second_last_name'] ?? '',
                                ], static fn($v) => (string) $v !== '')));
                                ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                <small class="text-muted">Se compone automáticamente a partir de los campos anteriores.</small>
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
                                <label class="form-label" for="especialidad">Especialidad</label>
                                <?php
                                $especialidades = [
                                    '' => 'Seleccionar',
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
                                $especialidadActual = usuarios_form_old($usuario, 'especialidad');
                                ?>
                                <select name="especialidad" id="especialidad" class="form-select">
                                    <?php foreach ($especialidades as $valor => $label): ?>
                                        <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?= $especialidadActual === $valor ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="subespecialidad">Subespecialidad</label>
                                <input type="text" name="subespecialidad" id="subespecialidad" class="form-control" value="<?= usuarios_form_old($usuario, 'subespecialidad'); ?>">
                                <small class="text-muted">Se habilita solo para Cirujano Oftalmólogo.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <?= isset($usuario['id']) ? '(dejar en blanco para mantener)' : '*'; ?></label>
                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                                <?php if (!empty($errors['password'])): ?>
                                    <div class="text-danger small"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sello</label>
                                <?php
                                $firmaPath = $usuario['firma'] ?? null;
                                if ($firmaPath && !preg_match('/^https?:/i', $firmaPath)) {
                                    $firmaPath = rtrim(BASE_URL, '/') . '/' . ltrim($firmaPath, '/');
                                }
                                ?>
                                <?php if (!empty($usuario['firma'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($firmaPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Sello actual" class="img-fluid border rounded" style="max-height: 120px;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_firma" id="remove_firma" value="1">
                                        <label class="form-check-label" for="remove_firma">Eliminar sello actual</label>
                                    </div>
                                <?php endif; ?>
                                <div class="drop-zone mt-2" data-upload-drop-zone="firma_file" tabindex="0" aria-label="Zona de carga para sello" aria-describedby="firma_help">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                        <div class="fw-semibold">Arrastra y suelta tu sello o usa el botón de selección.</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="firma_file">
                                            <i class="mdi mdi-upload"></i> Seleccionar archivo
                                        </button>
                                    </div>
                                    <div class="progress progress-xs mt-2 d-none" data-upload-progress="firma_file" aria-hidden="true">
                                        <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                    <?php $firmaError = $errors['firma_file'] ?? null; ?>
                                    <div class="text-danger small mt-2 <?= $firmaError ? '' : 'd-none'; ?>" data-upload-error="firma_file" role="alert">
                                        <?= $firmaError ? htmlspecialchars($firmaError, ENT_QUOTES, 'UTF-8') : ''; ?>
                                    </div>
                                    <div class="mt-2" data-upload-preview="firma_file"></div>
                                    <div class="text-muted small mt-2" id="firma_help">Formatos permitidos: PNG, WEBP o SVG. Tamaño máximo 2&nbsp;MB. Dimensiones recomendadas: 800x400 px. La zona es accesible con teclado.</div>
                                    <input type="file" name="firma_file" id="firma_file" class="form-control mt-2" accept="image/png,image/webp,image/svg+xml">
                                </div>
                                <div class="mt-2">
                                    <label class="form-label mb-1">Estado del sello</label>
                                    <select name="seal_status" class="form-select form-select-sm">
                                        <option value="pending" <?= (usuarios_form_old($usuario, 'seal_status', 'pending') === 'pending') ? 'selected' : ''; ?>>Pendiente de revisión</option>
                                        <option value="verified" <?= (usuarios_form_old($usuario, 'seal_status', 'pending') === 'verified') ? 'selected' : ''; ?>>Verificado</option>
                                        <option value="not_provided" <?= (usuarios_form_old($usuario, 'seal_status', 'pending') === 'not_provided') ? 'selected' : ''; ?>>No proporcionado</option>
                                    </select>
                                    <?php if (!empty($errors['seal_status'])): ?>
                                        <div class="text-danger small"><?= htmlspecialchars($errors['seal_status'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Firma digital</label>
                                <?php
                                $signaturePath = $usuario['signature_path'] ?? null;
                                if ($signaturePath && !preg_match('/^https?:/i', $signaturePath)) {
                                    $signaturePath = rtrim(BASE_URL, '/') . '/' . ltrim($signaturePath, '/');
                                }
                                ?>
                                <?php if (!empty($usuario['signature_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($signaturePath, ENT_QUOTES, 'UTF-8'); ?>" alt="Firma digital" class="img-fluid border rounded" style="max-height: 120px;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_signature" id="remove_signature" value="1">
                                        <label class="form-check-label" for="remove_signature">Eliminar firma digital actual</label>
                                    </div>
                                <?php endif; ?>
                                <div class="drop-zone mt-2" data-upload-drop-zone="signature_file" tabindex="0" aria-label="Zona de carga para firma digital" aria-describedby="signature_help">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                        <div class="fw-semibold">Arrastra o pega tu firma digital para previsualizarla al instante.</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="signature_file">
                                            <i class="mdi mdi-upload"></i> Seleccionar archivo
                                        </button>
                                    </div>
                                    <div class="progress progress-xs mt-2 d-none" data-upload-progress="signature_file" aria-hidden="true">
                                        <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                    <?php $signatureError = $errors['signature_file'] ?? null; ?>
                                    <div class="text-danger small mt-2 <?= $signatureError ? '' : 'd-none'; ?>" data-upload-error="signature_file" role="alert">
                                        <?= $signatureError ? htmlspecialchars($signatureError, ENT_QUOTES, 'UTF-8') : ''; ?>
                                    </div>
                                    <div class="mt-2" data-upload-preview="signature_file"></div>
                                    <div class="text-muted small mt-2" id="signature_help">Formatos permitidos: PNG, WEBP o SVG. Máximo 2&nbsp;MB. Dimensiones sugeridas hasta 1600x900 px. Totalmente navegable con teclado.</div>
                                    <input type="file" name="signature_file" id="signature_file" class="form-control mt-2" accept="image/png,image/webp,image/svg+xml">
                                </div>
                                <div class="mt-2">
                                    <label class="form-label mb-1">Estado de la firma</label>
                                    <select name="signature_status" class="form-select form-select-sm">
                                        <option value="pending" <?= (usuarios_form_old($usuario, 'signature_status', 'pending') === 'pending') ? 'selected' : ''; ?>>Pendiente de revisión</option>
                                        <option value="verified" <?= (usuarios_form_old($usuario, 'signature_status', 'pending') === 'verified') ? 'selected' : ''; ?>>Verificada</option>
                                        <option value="not_provided" <?= (usuarios_form_old($usuario, 'signature_status', 'pending') === 'not_provided') ? 'selected' : ''; ?>>No proporcionada</option>
                                    </select>
                                    <?php if (!empty($errors['signature_status'])): ?>
                                        <div class="text-danger small"><?= htmlspecialchars($errors['signature_status'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sello + firma combinados</label>
                                <?php
                                $sealSignaturePath = $usuario['seal_signature_path'] ?? null;
                                if ($sealSignaturePath && !preg_match('/^https?:/i', $sealSignaturePath)) {
                                    $sealSignaturePath = rtrim(BASE_URL, '/') . '/' . ltrim($sealSignaturePath, '/');
                                }
                                ?>
                                <?php if (!empty($usuario['seal_signature_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($sealSignaturePath, ENT_QUOTES, 'UTF-8'); ?>" alt="Sello y firma combinados" class="img-fluid border rounded" style="max-height: 120px;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_seal_signature" id="remove_seal_signature" value="1">
                                        <label class="form-check-label" for="remove_seal_signature">Eliminar imagen combinada actual</label>
                                    </div>
                                <?php endif; ?>
                                <div class="drop-zone mt-2" data-upload-drop-zone="seal_signature_file" tabindex="0" aria-label="Zona de carga para sello y firma combinados" aria-describedby="seal_signature_help">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                        <div class="fw-semibold">Sube una sola imagen con sello y firma ya combinados.</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="seal_signature_file">
                                            <i class="mdi mdi-upload"></i> Seleccionar archivo
                                        </button>
                                    </div>
                                    <div class="progress progress-xs mt-2 d-none" data-upload-progress="seal_signature_file" aria-hidden="true">
                                        <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                    <?php $sealSignatureError = $errors['seal_signature_file'] ?? null; ?>
                                    <div class="text-danger small mt-2 <?= $sealSignatureError ? '' : 'd-none'; ?>" data-upload-error="seal_signature_file" role="alert">
                                        <?= $sealSignatureError ? htmlspecialchars($sealSignatureError, ENT_QUOTES, 'UTF-8') : ''; ?>
                                    </div>
                                    <div class="mt-2" data-upload-preview="seal_signature_file"></div>
                                    <div class="text-muted small mt-2" id="seal_signature_help">Formatos permitidos: PNG, WEBP o SVG. Máximo 2&nbsp;MB.</div>
                                    <input type="file" name="seal_signature_file" id="seal_signature_file" class="form-control mt-2" accept="image/png,image/webp,image/svg+xml">
                                </div>
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
                                <div class="drop-zone mt-2" data-upload-drop-zone="profile_photo_file" tabindex="0" aria-label="Zona de carga para foto de perfil" aria-describedby="profile_photo_help">
                                    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                        <div class="fw-semibold">Arrastra tu foto o utiliza el botón para explorar archivos.</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-upload-trigger="profile_photo_file">
                                            <i class="mdi mdi-upload"></i> Seleccionar foto
                                        </button>
                                    </div>
                                    <div class="progress progress-xs mt-2 d-none" data-upload-progress="profile_photo_file" aria-hidden="true">
                                        <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                    <?php $photoError = $errors['profile_photo_file'] ?? null; ?>
                                    <div class="text-danger small mt-2 <?= $photoError ? '' : 'd-none'; ?>" data-upload-error="profile_photo_file" role="alert">
                                        <?= $photoError ? htmlspecialchars($photoError, ENT_QUOTES, 'UTF-8') : ''; ?>
                                    </div>
                                    <div class="mt-2" data-upload-preview="profile_photo_file"></div>
                                    <div class="text-muted small mt-2" id="profile_photo_help">Formatos permitidos: PNG, JPG o WEBP. Máximo 2&nbsp;MB. Recomendado 400x400 px y fondo neutro. La zona admite teclado y arrastrar y soltar.</div>
                                    <input type="file" name="profile_photo_file" id="profile_photo_file" class="form-control mt-2" accept="image/png,image/jpeg,image/webp">
                                </div>
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
                    <script>
                        (function () {
                            const especialidadSelect = document.getElementById('especialidad');
                            const subespecialidadInput = document.getElementById('subespecialidad');
                            if (!especialidadSelect || !subespecialidadInput) {
                                return;
                            }

                            const toggleSubespecialidad = () => {
                                const isOftalmologo = especialidadSelect.value === 'Cirujano Oftalmólogo';
                                subespecialidadInput.disabled = !isOftalmologo;
                                if (!isOftalmologo) {
                                    subespecialidadInput.value = '';
                                }
                            };

                            especialidadSelect.addEventListener('change', toggleSubespecialidad);
                            toggleSubespecialidad();
                        })();
                    </script>
                </div>
            </div>
        </div>
    </div>
</section>
