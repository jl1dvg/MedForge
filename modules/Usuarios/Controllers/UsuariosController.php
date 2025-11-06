<?php

namespace Modules\Usuarios\Controllers;

use Core\BaseController;
use Core\Permissions;
use Modules\Usuarios\Models\RolModel;
use Modules\Usuarios\Models\UsuarioModel;
use Modules\Usuarios\Support\PermissionRegistry;
use PDO;

class UsuariosController extends BaseController
{
    private const UPLOAD_MAX_SIZE = 2097152; // 2MB
    private const ALLOWED_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private UsuarioModel $usuarios;
    private RolModel $roles;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->usuarios = new UsuarioModel($pdo);
        $this->roles = new RolModel($pdo);
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
        $this->deleteFile($usuario['profile_photo'] ?? null);
        header('Location: /usuarios?status=deleted');
        exit;
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
            'profile_photo' => $existing['profile_photo'] ?? null,
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

        [$data['firma'], $firmaErrors] = $this->processSingleUpload(
            'firma_file',
            $data['firma'] ?? null,
            $existing['firma'] ?? null,
            isset($_POST['remove_firma']),
            $pending
        );
        if ($firmaErrors !== null) {
            $errors['firma_file'] = $firmaErrors;
        }

        [$data['profile_photo'], $photoErrors] = $this->processSingleUpload(
            'profile_photo_file',
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

    private function processSingleUpload(string $inputName, ?string $current, ?string $existing, bool $removeRequested, array &$pending): array
    {
        $currentPath = $current ?? $existing;

        if ($removeRequested && $existing) {
            if (!in_array($existing, $pending['delete'], true)) {
                $pending['delete'][] = $existing;
            }
            $currentPath = null;
        }

        if (!$this->hasUploadedFile($inputName)) {
            return [$currentPath, null];
        }

        $file = $_FILES[$inputName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [$currentPath, $this->uploadErrorMessage($file['error'])];
        }

        if (($file['size'] ?? 0) > self::UPLOAD_MAX_SIZE) {
            return [$currentPath, 'El archivo excede el tamaño máximo permitido (2 MB).'];
        }

        $mime = $this->detectMimeType($file['tmp_name'] ?? '');
        if ($mime === null || !isset(self::ALLOWED_MIME_TYPES[$mime])) {
            return [$currentPath, 'El archivo debe ser una imagen PNG, JPG o WEBP.'];
        }

        $extension = self::ALLOWED_MIME_TYPES[$mime];
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
        if (!in_array($publicPath, $pending['new'], true)) {
            $pending['new'][] = $publicPath;
        }

        if ($existing && !$removeRequested && !in_array($existing, $pending['delete'], true)) {
            $pending['delete'][] = $existing;
        }

        return [$publicPath, null];
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
