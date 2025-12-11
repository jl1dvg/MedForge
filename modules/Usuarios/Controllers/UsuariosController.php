<?php

namespace Modules\Usuarios\Controllers;

use Core\BaseController;
use Core\Permissions;
use Modules\Usuarios\Models\RolModel;
use Modules\Usuarios\Models\UsuarioModel;
use Modules\Usuarios\Support\PermissionRegistry;
use Modules\Usuarios\Support\UserMediaValidator;
use PDO;

class UsuariosController extends BaseController
{
    private const MEDIA_TYPES = [
        'firma' => [
            'input' => 'firma_file',
            'remove' => 'remove_firma',
            'directory' => UserMediaValidator::TYPE_SEAL,
            'path_key' => 'firma',
            'meta_prefix' => 'firma',
        ],
        'signature' => [
            'input' => 'signature_file',
            'remove' => 'remove_signature',
            'directory' => UserMediaValidator::TYPE_SIGNATURE,
            'path_key' => 'signature_path',
            'meta_prefix' => 'signature',
        ],
    ];

    private const PROFILE_MAX_SIZE = 2097152; // 2MB
    private const PROFILE_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private UsuarioModel $usuarios;
    private RolModel $roles;
    private UserMediaValidator $mediaValidator;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->usuarios = new UsuarioModel($pdo);
        $this->roles = new RolModel($pdo);
        $this->mediaValidator = new UserMediaValidator();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.view', 'admin.usuarios.manage', 'admin.usuarios']);
        $usuarios = $this->usuarios->all();
        $labels = PermissionRegistry::all();

        foreach ($usuarios as &$usuario) {
            $usuario['permisos_lista'] = Permissions::normalize($usuario['permisos'] ?? null);
        }
        unset($usuario);

        $roles = $this->roles->all();
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['id']] = $role['name'];
        }

        $status = $_GET['status'] ?? null;
        $error = $_GET['error'] ?? null;

        $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/index.php', [
            'pageTitle' => 'Usuarios',
            'usuarios' => $usuarios,
            'roleMap' => $roleMap,
            'status' => $status,
            'error' => $error,
            'permissionLabels' => $labels,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
        $roles = $this->roles->all();
        $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/form.php', [
            'pageTitle' => 'Nuevo usuario',
            'roles' => $roles,
            'permissions' => PermissionRegistry::groups(),
            'selectedPermissions' => [],
            'formAction' => '/usuarios/create',
            'method' => 'POST',
            'usuario' => [
                'is_subscribed' => 0,
                'is_approved' => 0,
            ],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
        $data = $this->collectInput(true);
        [$data, $uploadErrors, $pendingUploads] = $this->handleMediaUploads($data, null);
        $errors = array_merge($this->validate($data, true), $uploadErrors);

        if (!empty($errors)) {
            $this->rollbackUploads($pendingUploads);
            $formData = $data;
            unset($formData['password']);
            $formData['firma'] = null;
            $formData['profile_photo'] = null;
            $formData['signature_path'] = null;
            $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/form.php', [
                'pageTitle' => 'Nuevo usuario',
                'roles' => $this->roles->all(),
                'permissions' => PermissionRegistry::groups(),
                'selectedPermissions' => PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []),
                'formAction' => '/usuarios/create',
                'method' => 'POST',
                'usuario' => $formData,
                'errors' => $errors,
            ]);
            return;
        }

        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->usuarios->create($data);
        $this->finalizeUploads($pendingUploads);
        header('Location: /usuarios?status=created');
        exit;
    }

    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        $usuario = $this->usuarios->find($id);
        if (!$usuario) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        $selectedPermissions = Permissions::normalize($usuario['permisos'] ?? null);

        $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/form.php', [
            'pageTitle' => 'Editar usuario',
            'roles' => $this->roles->all(),
            'permissions' => PermissionRegistry::groups(),
            'selectedPermissions' => $selectedPermissions,
            'formAction' => '/usuarios/edit?id=' . $id,
            'method' => 'POST',
            'usuario' => $usuario,
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        $existing = $this->usuarios->find($id);
        if (!$existing) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        $data = $this->collectInput(false, $existing);
        [$data, $uploadErrors, $pendingUploads] = $this->handleMediaUploads($data, $existing);
        $errors = array_merge($this->validate($data, false), $uploadErrors);

        if (!empty($errors)) {
            $this->rollbackUploads($pendingUploads);
            $formData = $data;
            unset($formData['password']);
            $formData['firma'] = $existing['firma'] ?? null;
            $formData['profile_photo'] = $existing['profile_photo'] ?? null;
            $formData['signature_path'] = $existing['signature_path'] ?? null;
            $usuario = array_merge($existing, $formData);
            $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/form.php', [
                'pageTitle' => 'Editar usuario',
                'roles' => $this->roles->all(),
                'permissions' => PermissionRegistry::groups(),
                'selectedPermissions' => PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []),
                'formAction' => '/usuarios/edit?id=' . $id,
                'method' => 'POST',
                'usuario' => $usuario,
                'errors' => $errors,
            ]);
            return;
        }

        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->usuarios->update($id, $data);
        $this->finalizeUploads($pendingUploads);

        // Si el usuario editado es el mismo autenticado, refrescar permisos en sesión
        if ((int) ($_SESSION['user_id'] ?? 0) === $id) {
            $_SESSION['permisos'] = Permissions::normalize($data['permisos'] ?? ($_SESSION['permisos'] ?? []));
        }

        header('Location: /usuarios?status=updated');
        exit;
    }

    public function destroy(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        if ((int) ($_SESSION['user_id'] ?? 0) === $id) {
            header('Location: /usuarios?error=cannot_delete_self');
            exit;
        }

        $usuario = $this->usuarios->find($id);
        if (!$usuario) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        $this->usuarios->delete($id);
        $this->deleteFile($usuario['firma'] ?? null);
        $this->deleteFile($usuario['signature_path'] ?? null);
        $this->deleteFile($usuario['profile_photo'] ?? null);
        header('Location: /usuarios?status=deleted');
        exit;
    }

    public function media(): void
    {
        $this->requireAuth();

        $requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_SESSION['user_id'] ?? 0);
        if ($requestedId <= 0) {
            $this->json(['error' => 'missing_user_id'], 400);
            return;
        }

        if (!$this->canAccessMedia($requestedId)) {
            $this->json(['error' => 'forbidden'], 403);
            return;
        }

        $usuario = $this->usuarios->find($requestedId);
        if (!$usuario) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }

        $this->json([
            'seal' => $this->serializeMediaPayload($usuario, 'firma'),
            'signature' => $this->serializeMediaPayload($usuario, 'signature_path'),
        ]);
    }

    private function collectInput(bool $isCreate, ?array $existing = null): array
    {
        $permissions = PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []);

        $data = [
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'cedula' => trim((string) ($_POST['cedula'] ?? '')),
            'registro' => trim((string) ($_POST['registro'] ?? '')),
            'sede' => trim((string) ($_POST['sede'] ?? '')),
            'especialidad' => trim((string) ($_POST['especialidad'] ?? '')),
            'subespecialidad' => trim((string) ($_POST['subespecialidad'] ?? '')),
            'is_subscribed' => isset($_POST['is_subscribed']) ? 1 : 0,
            'is_approved' => isset($_POST['is_approved']) ? 1 : 0,
            'role_id' => $this->resolveRoleId($_POST['role_id'] ?? null),
            'permisos' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
            'firma' => $existing['firma'] ?? null,
            'firma_mime' => $existing['firma_mime'] ?? null,
            'firma_size' => $existing['firma_size'] ?? null,
            'firma_hash' => $existing['firma_hash'] ?? null,
            'firma_updated_at' => $existing['firma_updated_at'] ?? null,
            'firma_updated_by' => $existing['firma_updated_by'] ?? null,
            'profile_photo' => $existing['profile_photo'] ?? null,
            'signature_path' => $existing['signature_path'] ?? null,
            'signature_mime' => $existing['signature_mime'] ?? null,
            'signature_size' => $existing['signature_size'] ?? null,
            'signature_hash' => $existing['signature_hash'] ?? null,
            'signature_updated_at' => $existing['signature_updated_at'] ?? null,
            'signature_updated_by' => $existing['signature_updated_by'] ?? null,
        ];

        $password = isset($_POST['password']) ? trim((string) $_POST['password']) : '';
        if ($isCreate || $password !== '') {
            $data['password'] = $password;
        }

        return $data;
    }

    private function validate(array $data, bool $isCreate): array
    {
        $errors = [];

        if ($data['username'] === '') {
            $errors['username'] = 'El nombre de usuario es obligatorio.';
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El correo electrónico no es válido.';
        }

        if ($isCreate && (!isset($data['password']) || $data['password'] === '')) {
            $errors['password'] = 'La contraseña es obligatoria.';
        }

        if (!$isCreate && array_key_exists('password', $data) && $data['password'] === '') {
            unset($data['password']);
        }

        if ($data['role_id'] !== null && !$this->roles->find($data['role_id'])) {
            $errors['role_id'] = 'El rol seleccionado no existe.';
        }

        return $errors;
    }

    protected function canAccessMedia(int $userId): bool
    {
        $currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($currentUserId > 0 && $currentUserId === $userId) {
            return true;
        }

        return $this->hasPermission([
            'administrativo',
            'admin.usuarios.manage',
            'admin.usuarios.view',
            'admin.usuarios',
            'superuser',
        ]);
    }

    private function serializeMediaPayload(array $usuario, string $pathKey): ?array
    {
        $prefix = $pathKey === 'firma' ? 'firma' : 'signature';
        $path = $usuario[$pathKey] ?? null;

        if (!$path) {
            return null;
        }

        return [
            'path' => $path,
            'url' => $this->normalizeMediaUrl($path),
            'mime' => $usuario[$prefix . '_mime'] ?? null,
            'size' => isset($usuario[$prefix . '_size']) ? (int) $usuario[$prefix . '_size'] : null,
            'hash' => $usuario[$prefix . '_hash'] ?? null,
            'updated_at' => $usuario[$prefix . '_updated_at'] ?? null,
            'updated_by' => isset($usuario[$prefix . '_updated_by']) ? (int) $usuario[$prefix . '_updated_by'] : null,
        ];
    }

    private function normalizeMediaUrl(string $path): string
    {
        if (preg_match('#^(?:https?:)?//#i', $path)) {
            return $path;
        }

        $normalized = '/' . ltrim($path, '/');
        return rtrim(BASE_URL, '/') . $normalized;
    }

    private function resolveRoleId($roleId): ?int
    {
        if ($roleId === null || $roleId === '') {
            return null;
        }

        $value = (int) $roleId;
        return $value > 0 ? $value : null;
    }

    private function handleMediaUploads(array $data, ?array $existing): array
    {
        $errors = [];
        $pending = ['delete' => [], 'new' => []];

        foreach (self::MEDIA_TYPES as $type => $config) {
            [$data, $error] = $this->processMediaType($config, $data, $existing, $pending);
            if ($error !== null) {
                $errors[$config['input']] = $error;
            }
        }

        [$data['profile_photo'], $photoErrors] = $this->processProfilePhoto(
            $data['profile_photo'] ?? null,
            $existing['profile_photo'] ?? null,
            isset($_POST['remove_profile_photo']),
            $pending
        );
        if ($photoErrors !== null) {
            $errors['profile_photo_file'] = $photoErrors;
        }

        return [$data, $errors, $pending];
    }

    private function processMediaType(array $config, array $data, ?array $existing, array &$pending): array
    {
        $pathKey = $config['path_key'];
        $metaPrefix = $config['meta_prefix'];
        $removeRequested = isset($_POST[$config['remove']]);
        $existingPath = is_array($existing) ? ($existing[$pathKey] ?? null) : null;
        $currentPath = $data[$pathKey] ?? $existingPath;

        if ($removeRequested && $existingPath) {
            $this->markForDeletion($pending, $existingPath);
            $currentPath = null;
            $this->clearMediaMetadata($data, $metaPrefix, true);
        }

        if (!$this->hasUploadedFile($config['input'])) {
            return [$this->updateMediaPath($data, $pathKey, $currentPath), null];
        }

        $validation = $this->mediaValidator->validate($_FILES[$config['input']]);
        if ($validation['error'] !== null) {
            return [$data, $validation['error']];
        }

        $filename = $this->mediaValidator->generateFilename($validation['extension']);
        $destination = $this->mediaValidator->destinationFor($config['directory'], $filename, BASE_PATH);
        $destinationDir = dirname($destination['absolute']);

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            return [$data, 'No se pudo preparar el directorio de carga.'];
        }

        if (!move_uploaded_file($_FILES[$config['input']]['tmp_name'], $destination['absolute'])) {
            return [$data, 'No se pudo guardar el archivo subido.'];
        }

        $publicPath = $destination['public'];
        $this->markForNew($pending, $publicPath);

        if ($existingPath && !$removeRequested) {
            $this->markForDeletion($pending, $existingPath);
        }

        $currentPath = $publicPath;
        $this->applyMediaMetadata($data, $metaPrefix, $validation);

        return [$this->updateMediaPath($data, $pathKey, $currentPath), null];
    }

    private function processProfilePhoto(?string $current, ?string $existing, bool $removeRequested, array &$pending): array
    {
        $currentPath = $current ?? $existing;

        if ($removeRequested && $existing) {
            $this->markForDeletion($pending, $existing);
            $currentPath = null;
        }

        if (!$this->hasUploadedFile('profile_photo_file')) {
            return [$currentPath, null];
        }

        $file = $_FILES['profile_photo_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [$currentPath, $this->uploadErrorMessage($file['error'])];
        }

        if (($file['size'] ?? 0) > self::PROFILE_MAX_SIZE) {
            return [$currentPath, 'El archivo excede el tamaño máximo permitido (2 MB).'];
        }

        $mime = $this->detectMimeType($file['tmp_name'] ?? '');
        if ($mime === null || !isset(self::PROFILE_MIME_TYPES[$mime])) {
            return [$currentPath, 'El archivo debe ser una imagen PNG, JPG o WEBP.'];
        }

        $extension = self::PROFILE_MIME_TYPES[$mime];
        $filename = $this->generateFilename($extension);
        $destinationDir = BASE_PATH . '/public/uploads/users';
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            return [$currentPath, 'No se pudo preparar el directorio de carga.'];
        }

        $destination = $destinationDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return [$currentPath, 'No se pudo guardar el archivo subido.'];
        }

        $publicPath = '/uploads/users/' . $filename;
        $this->markForNew($pending, $publicPath);

        if ($existing && !$removeRequested) {
            $this->markForDeletion($pending, $existing);
        }

        return [$publicPath, null];
    }

    private function markForDeletion(array &$pending, string $path): void
    {
        if (!in_array($path, $pending['delete'], true)) {
            $pending['delete'][] = $path;
        }
    }

    private function markForNew(array &$pending, string $path): void
    {
        if (!in_array($path, $pending['new'], true)) {
            $pending['new'][] = $path;
        }
    }

    private function applyMediaMetadata(array &$data, string $prefix, array $validation): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $data[$prefix . '_mime'] = $validation['mime'];
        $data[$prefix . '_size'] = $validation['size'];
        $data[$prefix . '_hash'] = $validation['hash'];
        $data[$prefix . '_updated_at'] = $timestamp;
        $data[$prefix . '_updated_by'] = $userId ?: null;
    }

    private function clearMediaMetadata(array &$data, string $prefix, bool $withAudit = false): void
    {
        $data[$prefix . '_mime'] = null;
        $data[$prefix . '_size'] = null;
        $data[$prefix . '_hash'] = null;

        if ($withAudit) {
            $data[$prefix . '_updated_at'] = date('Y-m-d H:i:s');
            $data[$prefix . '_updated_by'] = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        }
    }

    private function updateMediaPath(array $data, string $pathKey, ?string $path): array
    {
        $data[$pathKey] = $path;

        return $data;
    }

    private function hasUploadedFile(string $inputName): bool
    {
        return isset($_FILES[$inputName]) && is_array($_FILES[$inputName]) && ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function detectMimeType(string $path): ?string
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }

        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);

        return $mime;
    }

    private function generateFilename(string $extension): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $random = bin2hex(openssl_random_pseudo_bytes(16));
        }

        return date('YmdHis') . '_' . $random . '.' . $extension;
    }

    private function deleteFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        $normalized = '/' . ltrim($path, '/');
        if (!str_starts_with($normalized, '/uploads/users/')) {
            return;
        }

        $absolute = BASE_PATH . '/public' . $normalized;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'La carga del archivo fue interrumpida.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal para procesar archivos.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión del servidor bloqueó la carga del archivo.',
            default => 'No se pudo cargar el archivo. Intenta nuevamente.',
        };
    }

    private function finalizeUploads(array $pending): void
    {
        $paths = array_unique($pending['delete'] ?? []);
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }

    private function rollbackUploads(array $pending): void
    {
        $paths = array_unique($pending['new'] ?? []);
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }
}
