<?php

namespace Modules\Usuarios\Controllers;

use Core\BaseController;
use Core\Permissions;
use Modules\Usuarios\Models\RolModel;
use Modules\Usuarios\Models\UserMediaHistoryModel;
use Modules\Usuarios\Models\UsuarioModel;
use Modules\Usuarios\Support\PermissionRegistry;
use Modules\Usuarios\Support\SensitiveDataProtector;
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
            'type' => UserMediaValidator::TYPE_SEAL,
        ],
        'signature' => [
            'input' => 'signature_file',
            'remove' => 'remove_signature',
            'directory' => UserMediaValidator::TYPE_SIGNATURE,
            'path_key' => 'signature_path',
            'meta_prefix' => 'signature',
            'type' => UserMediaValidator::TYPE_SIGNATURE,
        ],
    ];

    private const PROFILE_MAX_SIZE = 2097152; // 2MB
    private const PROFILE_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const VERIFICATION_STATES = ['pending', 'verified', 'not_provided'];

    private UsuarioModel $usuarios;
    private RolModel $roles;
    private UserMediaHistoryModel $mediaHistory;
    private UserMediaValidator $mediaValidator;
    private SensitiveDataProtector $protector;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->usuarios = new UsuarioModel($pdo);
        $this->roles = new RolModel($pdo);
        $this->mediaHistory = new UserMediaHistoryModel($pdo);
        $this->mediaValidator = new UserMediaValidator();
        $this->protector = new SensitiveDataProtector();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.view', 'admin.usuarios.manage', 'admin.usuarios']);
        $usuarios = $this->usuarios->all();
        $labels = PermissionRegistry::all();

        foreach ($usuarios as &$usuario) {
            $usuario['permisos_lista'] = Permissions::normalize($usuario['permisos'] ?? null);
            $usuario = $this->hydrateSensitiveFields($usuario);
            $usuario['profile_completeness'] = $this->profileCompleteness($usuario);
            $usuario['seal_status'] = $this->normalizeStatus($usuario['seal_status'] ?? null, (bool) ($usuario['firma'] ?? null));
            $usuario['signature_status'] = $this->normalizeStatus($usuario['signature_status'] ?? null, (bool) ($usuario['signature_path'] ?? null));
        }
        unset($usuario);

        $roles = $this->roles->all();
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['id']] = $role['name'];
        }

        $status = $_GET['status'] ?? null;
        $error = $_GET['error'] ?? null;
        $warnings = $_SESSION['user_warnings'] ?? [];
        unset($_SESSION['user_warnings']);

        $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/index.php', [
            'pageTitle' => 'Usuarios',
            'usuarios' => $usuarios,
            'roleMap' => $roleMap,
            'status' => $status,
            'error' => $error,
            'warnings' => $warnings,
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
            'warnings' => [],
            'mediaHistory' => [],
            'restorableMedia' => [
                UserMediaValidator::TYPE_SEAL => [],
                UserMediaValidator::TYPE_SIGNATURE => [],
            ],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.usuarios.manage', 'admin.usuarios']);
        $data = $this->collectInput(true);
        [$data, $uploadErrors, $pendingUploads] = $this->handleMediaUploads($data, null);
        $errors = array_merge($this->validate($data, true), $uploadErrors);
        $warnings = $this->duplicateWarnings($data, null);

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
                'warnings' => $warnings,
            ]);
            return;
        }

        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $data = $this->transformSensitiveFields($data);
        $data = $this->applyVerificationStatuses($data, null, $pendingUploads['history']);

        if (!empty($warnings)) {
            $_SESSION['user_warnings'] = $warnings;
        }

        $userId = $this->usuarios->create($data);
        $this->finalizeUploads($pendingUploads);
        $this->flushMediaHistory($userId, $pendingUploads['history']);
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

        $usuario = $this->hydrateSensitiveFields($usuario);

        $selectedPermissions = Permissions::normalize($usuario['permisos'] ?? null);
        $mediaHistory = $this->mediaHistory->recentForUser($id, 12);
        $restorable = [
            UserMediaValidator::TYPE_SEAL => $this->mediaHistory->restorableVersions($id, UserMediaValidator::TYPE_SEAL, 5),
            UserMediaValidator::TYPE_SIGNATURE => $this->mediaHistory->restorableVersions($id, UserMediaValidator::TYPE_SIGNATURE, 5),
        ];

        $this->render(BASE_PATH . '/modules/Usuarios/views/usuarios/form.php', [
            'pageTitle' => 'Editar usuario',
            'roles' => $this->roles->all(),
            'permissions' => PermissionRegistry::groups(),
            'selectedPermissions' => $selectedPermissions,
            'formAction' => '/usuarios/edit?id=' . $id,
            'method' => 'POST',
            'usuario' => $usuario,
            'errors' => [],
            'warnings' => [],
            'mediaHistory' => $mediaHistory,
            'restorableMedia' => $restorable,
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

        $existing = $this->hydrateSensitiveFields($existing);

        $data = $this->collectInput(false, $existing);
        [$data, $uploadErrors, $pendingUploads] = $this->handleMediaUploads($data, $existing);
        $errors = array_merge($this->validate($data, false), $uploadErrors);
        $warnings = $this->duplicateWarnings($data, $id);

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
                'warnings' => $warnings,
                'mediaHistory' => $this->mediaHistory->recentForUser($id, 12),
                'restorableMedia' => [
                    UserMediaValidator::TYPE_SEAL => $this->mediaHistory->restorableVersions($id, UserMediaValidator::TYPE_SEAL, 5),
                    UserMediaValidator::TYPE_SIGNATURE => $this->mediaHistory->restorableVersions($id, UserMediaValidator::TYPE_SIGNATURE, 5),
                ],
            ]);
            return;
        }

        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $data = $this->transformSensitiveFields($data);
        $data = $this->applyVerificationStatuses($data, $existing, $pendingUploads['history']);

        if (!empty($warnings)) {
            $_SESSION['user_warnings'] = $warnings;
        }

        $this->usuarios->update($id, $data);
        $this->finalizeUploads($pendingUploads);
        $this->flushMediaHistory($id, $pendingUploads['history']);

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

        $payload = [
            'user' => $this->buildUserIdentityPayload($usuario),
            'seal' => $this->serializeMediaPayload($usuario, 'firma'),
            'signature' => $this->serializeMediaPayload($usuario, 'signature_path'),
        ];

        $this->applyMediaCachingHeaders($payload);

        $this->json($payload);
    }

    private function collectInput(bool $isCreate, ?array $existing = null): array
    {
        $permissions = PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []);

        $nationalIdInput = $this->normalizeSensitive($_POST['national_id'] ?? '');
        $passportInput = $this->normalizeSensitive($_POST['passport_number'] ?? '');

        if (!$isCreate) {
            if ($nationalIdInput === '' && isset($existing['national_id'])) {
                $nationalIdInput = (string) $existing['national_id'];
            }
            if ($passportInput === '' && isset($existing['passport_number'])) {
                $passportInput = (string) $existing['passport_number'];
            }
        }

        $data = [
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'first_name' => $this->normalizeNameInput($_POST['first_name'] ?? ''),
            'middle_name' => $this->normalizeNameInput($_POST['middle_name'] ?? ''),
            'last_name' => $this->normalizeNameInput($_POST['last_name'] ?? ''),
            'second_last_name' => $this->normalizeNameInput($_POST['second_last_name'] ?? ''),
            'birth_date' => $this->normalizeDate($_POST['birth_date'] ?? null),
            'cedula' => trim((string) ($_POST['cedula'] ?? '')),
            'national_id' => $nationalIdInput,
            'passport_number' => $passportInput,
            'registro' => trim((string) ($_POST['registro'] ?? '')),
            'sede' => trim((string) ($_POST['sede'] ?? '')),
            'especialidad' => trim((string) ($_POST['especialidad'] ?? '')),
            'subespecialidad' => trim((string) ($_POST['subespecialidad'] ?? '')),
            'is_subscribed' => isset($_POST['is_subscribed']) ? 1 : 0,
            'is_approved' => isset($_POST['is_approved']) ? 1 : 0,
            'seal_status' => $this->sanitizeStatus($_POST['seal_status'] ?? ($existing['seal_status'] ?? null)),
            'signature_status' => $this->sanitizeStatus($_POST['signature_status'] ?? ($existing['signature_status'] ?? null)),
            'role_id' => $this->resolveRoleId($_POST['role_id'] ?? null),
            'permisos' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
            'firma' => $existing['firma'] ?? null,
            'firma_mime' => $existing['firma_mime'] ?? null,
            'firma_size' => $existing['firma_size'] ?? null,
            'firma_hash' => $existing['firma_hash'] ?? null,
            'firma_created_at' => $existing['firma_created_at'] ?? null,
            'firma_created_by' => $existing['firma_created_by'] ?? null,
            'firma_updated_at' => $existing['firma_updated_at'] ?? null,
            'firma_updated_by' => $existing['firma_updated_by'] ?? null,
            'firma_verified_at' => $existing['firma_verified_at'] ?? null,
            'firma_verified_by' => $existing['firma_verified_by'] ?? null,
            'firma_deleted_at' => $existing['firma_deleted_at'] ?? null,
            'firma_deleted_by' => $existing['firma_deleted_by'] ?? null,
            'profile_photo' => $existing['profile_photo'] ?? null,
            'signature_path' => $existing['signature_path'] ?? null,
            'signature_mime' => $existing['signature_mime'] ?? null,
            'signature_size' => $existing['signature_size'] ?? null,
            'signature_hash' => $existing['signature_hash'] ?? null,
            'signature_created_at' => $existing['signature_created_at'] ?? null,
            'signature_created_by' => $existing['signature_created_by'] ?? null,
            'signature_updated_at' => $existing['signature_updated_at'] ?? null,
            'signature_updated_by' => $existing['signature_updated_by'] ?? null,
            'signature_verified_at' => $existing['signature_verified_at'] ?? null,
            'signature_verified_by' => $existing['signature_verified_by'] ?? null,
            'signature_deleted_at' => $existing['signature_deleted_at'] ?? null,
            'signature_deleted_by' => $existing['signature_deleted_by'] ?? null,
        ];

        $data['nombre'] = $this->buildFullName($data);

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

        if ($data['birth_date'] !== null && !$this->isValidDate($data['birth_date'])) {
            $errors['birth_date'] = 'La fecha de nacimiento no es válida.';
        }

        $nameFields = [
            'first_name' => 'Nombre',
            'last_name' => 'Primer apellido',
            'middle_name' => 'Segundo nombre',
            'second_last_name' => 'Segundo apellido',
        ];

        if ($data['first_name'] === '') {
            $errors['first_name'] = 'El nombre es obligatorio.';
        }

        if ($data['last_name'] === '') {
            $errors['last_name'] = 'El primer apellido es obligatorio.';
        }

        foreach ($nameFields as $field => $label) {
            $value = $data[$field] ?? '';
            if ($value === '') {
                continue;
            }

            if (!$this->isValidNameCharacters($value)) {
                $errors[$field] = $label . ' contiene caracteres no permitidos.';
            }

            if (mb_strlen($value, 'UTF-8') > 100) {
                $errors[$field] = $label . ' no puede exceder 100 caracteres.';
            }
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

        if ($data['national_id'] !== '' && !$this->isValidIdentityValue($data['national_id'])) {
            $errors['national_id'] = 'La identificación nacional debe tener entre 4 y 32 caracteres alfanuméricos.';
        }

        if ($data['passport_number'] !== '' && !$this->isValidIdentityValue($data['passport_number'])) {
            $errors['passport_number'] = 'El número de pasaporte debe tener entre 4 y 32 caracteres alfanuméricos.';
        }

        if (!in_array($data['seal_status'], self::VERIFICATION_STATES, true)) {
            $errors['seal_status'] = 'El estado del sello no es válido.';
        }

        if (!in_array($data['signature_status'], self::VERIFICATION_STATES, true)) {
            $errors['signature_status'] = 'El estado de la firma no es válido.';
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

    private function applyMediaCachingHeaders(array $payload): void
    {
        if (headers_sent()) {
            return;
        }

        $ttl = 900;
        $stale = 120;
        $hashSource = json_encode([
            $payload['seal']['hash'] ?? null,
            $payload['signature']['hash'] ?? null,
            $payload['seal']['updated_at'] ?? null,
            $payload['signature']['updated_at'] ?? null,
        ]);

        header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=' . $stale);
        header('CDN-Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=' . $stale);

        if ($hashSource !== false) {
            header('ETag: "' . sha1($hashSource) . '"');
        }
    }

    private function buildUserIdentityPayload(array $usuario): array
    {
        $fullName = $this->safeString($usuario['full_name'] ?? null)
            ?? $this->safeString($usuario['nombre'] ?? null);

        return [
            'id' => isset($usuario['id']) ? (int) $usuario['id'] : null,
            'full_name' => $fullName,
            'legacy_full_name' => $this->safeString($usuario['nombre'] ?? null),
            'first_name' => $this->safeString($usuario['first_name'] ?? null),
            'middle_name' => $this->safeString($usuario['middle_name'] ?? null),
            'last_name' => $this->safeString($usuario['last_name'] ?? null),
            'second_last_name' => $this->safeString($usuario['second_last_name'] ?? null),
            'username' => $this->safeString($usuario['username'] ?? null),
            'email' => $this->safeString($usuario['email'] ?? null),
        ];
    }

    private function safeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
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
            'created_at' => $usuario[$prefix . '_created_at'] ?? null,
            'created_by' => isset($usuario[$prefix . '_created_by']) ? (int) $usuario[$prefix . '_created_by'] : null,
            'updated_at' => $usuario[$prefix . '_updated_at'] ?? null,
            'updated_by' => isset($usuario[$prefix . '_updated_by']) ? (int) $usuario[$prefix . '_updated_by'] : null,
            'verified_at' => $usuario[$prefix . '_verified_at'] ?? null,
            'verified_by' => isset($usuario[$prefix . '_verified_by']) ? (int) $usuario[$prefix . '_verified_by'] : null,
            'deleted_at' => $usuario[$prefix . '_deleted_at'] ?? null,
            'deleted_by' => isset($usuario[$prefix . '_deleted_by']) ? (int) $usuario[$prefix . '_deleted_by'] : null,
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
        $pending = ['delete' => [], 'new' => [], 'history' => []];

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
        $mediaType = $config['type'];
        $removeRequested = isset($_POST[$config['remove']]);
        $restoreVersion = isset($_POST['restore_' . $pathKey . '_version']) ? (int) $_POST['restore_' . $pathKey . '_version'] : null;
        $existingPath = is_array($existing) ? ($existing[$pathKey] ?? null) : null;
        $currentPath = $data[$pathKey] ?? $existingPath;

        if ($restoreVersion && ($existing['id'] ?? null)) {
            $restored = $this->mediaHistory->findVersion((int) $existing['id'], $mediaType, $restoreVersion);
            if ($restored && !empty($restored['path'])) {
                $currentPath = $restored['path'];
                $this->applyRestoredMetadata($data, $metaPrefix, $restored);
                $pending['history'][] = $this->buildHistoryEvent($mediaType, 'restore', $restored, $existingPath);
                $removeRequested = false;
            }
        }

        if ($removeRequested && $existingPath) {
            $this->markForDeletion($pending, $existingPath);
            $currentPath = null;
            $this->clearMediaMetadata($data, $metaPrefix, true);
            $pending['history'][] = $this->buildHistoryEvent($mediaType, 'delete', $this->captureMetadata($existing, $metaPrefix, $existingPath), $existingPath);
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
        $this->applyMediaMetadata($data, $metaPrefix, $validation, true);
        $pending['history'][] = $this->buildHistoryEvent($mediaType, $existingPath ? 'replace' : 'upload', $this->captureMetadata($data, $metaPrefix, $publicPath), $existingPath);

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

    private function applyMediaMetadata(array &$data, string $prefix, array $validation, bool $isNewAsset): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $data[$prefix . '_mime'] = $validation['mime'];
        $data[$prefix . '_size'] = $validation['size'];
        $data[$prefix . '_hash'] = $validation['hash'];
        $data[$prefix . '_updated_at'] = $timestamp;
        $data[$prefix . '_updated_by'] = $userId ?: null;

        if ($isNewAsset) {
            $data[$prefix . '_created_at'] = $timestamp;
            $data[$prefix . '_created_by'] = $userId ?: null;
            $data[$prefix . '_deleted_at'] = null;
            $data[$prefix . '_deleted_by'] = null;
            $data[$prefix . '_verified_at'] = null;
            $data[$prefix . '_verified_by'] = null;
        }
    }

    private function clearMediaMetadata(array &$data, string $prefix, bool $withAudit = false): void
    {
        $data[$prefix . '_mime'] = null;
        $data[$prefix . '_size'] = null;
        $data[$prefix . '_hash'] = null;
        $data[$prefix . '_created_at'] = null;
        $data[$prefix . '_created_by'] = null;
        $data[$prefix . '_verified_at'] = null;
        $data[$prefix . '_verified_by'] = null;
        $data[$prefix . '_deleted_at'] = $withAudit ? date('Y-m-d H:i:s') : null;
        $data[$prefix . '_deleted_by'] = $withAudit && isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        if ($withAudit) {
            $data[$prefix . '_updated_at'] = date('Y-m-d H:i:s');
            $data[$prefix . '_updated_by'] = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        }
    }

    private function captureMetadata(array $source, string $prefix, ?string $path): array
    {
        return [
            'path' => $path,
            'mime' => $source[$prefix . '_mime'] ?? null,
            'size' => $source[$prefix . '_size'] ?? null,
            'hash' => $source[$prefix . '_hash'] ?? null,
            'acted_at' => $source[$prefix . '_updated_at'] ?? date('Y-m-d H:i:s'),
            'acted_by' => $source[$prefix . '_updated_by'] ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null),
        ];
    }

    private function buildHistoryEvent(string $mediaType, string $action, array $meta, ?string $previousPath = null): array
    {
        $meta['previous_path'] = $previousPath;
        $meta['status'] = $action;
        $meta['media_type'] = $mediaType;
        $meta['action'] = $action;
        $meta['acted_at'] = $meta['acted_at'] ?? date('Y-m-d H:i:s');
        $meta['acted_by'] = $meta['acted_by'] ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);

        return $meta;
    }

    private function applyRestoredMetadata(array &$data, string $prefix, array $record): void
    {
        $data[$prefix . '_mime'] = $record['mime'] ?? null;
        $data[$prefix . '_size'] = $record['size'] ?? null;
        $data[$prefix . '_hash'] = $record['hash'] ?? null;
        $data[$prefix . '_created_at'] = $record['acted_at'] ?? date('Y-m-d H:i:s');
        $data[$prefix . '_created_by'] = isset($record['acted_by']) ? (int) $record['acted_by'] : null;
        $data[$prefix . '_updated_at'] = $record['acted_at'] ?? date('Y-m-d H:i:s');
        $data[$prefix . '_updated_by'] = isset($record['acted_by']) ? (int) $record['acted_by'] : null;
        $data[$prefix . '_deleted_at'] = null;
        $data[$prefix . '_deleted_by'] = null;
        $data[$prefix . '_verified_at'] = null;
        $data[$prefix . '_verified_by'] = null;
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

    private function logSoftDeletion(string $path): void
    {
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $message = sprintf("[%s] Media soft-deleted (preserved): %s\n", date('c'), $path);
        @file_put_contents($logDir . '/user_media.log', $message, FILE_APPEND);
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
        // Soft delete: keep historical assets for potential restores.
        $paths = array_unique($pending['delete'] ?? []);
        foreach ($paths as $path) {
            if (!empty($path)) {
                $this->logSoftDeletion($path);
            }
        }
    }

    private function rollbackUploads(array $pending): void
    {
        $paths = array_unique($pending['new'] ?? []);
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }

    private function flushMediaHistory(int $userId, array $events): void
    {
        if ($userId <= 0 || empty($events)) {
            return;
        }

        foreach ($events as $event) {
            if (empty($event['media_type']) || empty($event['action'])) {
                continue;
            }

            $this->mediaHistory->record($userId, $event['media_type'], $event['action'], $event);
        }
    }

    private function normalizeNameInput($value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));
        return mb_substr($normalized, 0, 100, 'UTF-8');
    }

    private function isValidNameCharacters(string $value): bool
    {
        return !preg_match("/[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'"\s]/u", $value);
    }

    private function buildFullName(array $data): string
    {
        $parts = array_filter([
            $data['first_name'] ?? '',
            $data['middle_name'] ?? '',
            $data['last_name'] ?? '',
            $data['second_last_name'] ?? '',
        ], static fn($v) => (string) $v !== '');

        return trim(implode(' ', $parts));
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = date_create_from_format('Y-m-d', (string) $value);

        return $date ? $date->format('Y-m-d') : null;
    }

    private function isValidDate(?string $date): bool
    {
        if ($date === null) {
            return true;
        }

        $dt = date_create_from_format('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }

    private function normalizeSensitive($value): string
    {
        $normalized = preg_replace('/\s+/', '', (string) $value) ?? '';
        return mb_substr($normalized, 0, 64, 'UTF-8');
    }

    private function isValidIdentityValue(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9-]{4,32}$/', $value);
    }

    private function sanitizeStatus($value): string
    {
        $normalized = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($normalized, self::VERIFICATION_STATES, true) ? $normalized : 'pending';
    }

    private function normalizeStatus(?string $status, bool $hasMedia): string
    {
        if (!$hasMedia) {
            return 'not_provided';
        }

        $normalized = $this->sanitizeStatus($status);
        return $normalized === 'not_provided' ? 'pending' : $normalized;
    }

    private function duplicateWarnings(array $data, ?int $excludeId): array
    {
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['birth_date'])) {
            return [];
        }

        $matches = $this->usuarios->findPotentialDuplicates($data['first_name'], $data['last_name'], $data['birth_date'], $excludeId);
        if (empty($matches)) {
            return [];
        }

        $warnings = [];
        foreach ($matches as $match) {
            $warnings[] = sprintf(
                'Posible duplicado con %s (ID %d, nacimiento %s).',
                trim(($match['first_name'] ?? '') . ' ' . ($match['last_name'] ?? '')),
                (int) ($match['id'] ?? 0),
                $match['birth_date'] ?? 'sin fecha'
            );
        }

        return $warnings;
    }

    private function transformSensitiveFields(array $data): array
    {
        $data['national_id_encrypted'] = $this->protector->encrypt($data['national_id'] ?? null);
        $data['passport_number_encrypted'] = $this->protector->encrypt($data['passport_number'] ?? null);

        unset($data['national_id'], $data['passport_number']);

        return $data;
    }

    private function hydrateSensitiveFields(array $usuario): array
    {
        $usuario['national_id'] = $this->protector->decrypt($usuario['national_id_encrypted'] ?? null);
        $usuario['passport_number'] = $this->protector->decrypt($usuario['passport_number_encrypted'] ?? null);
        $usuario['national_id_masked'] = $this->protector->mask($usuario['national_id']);
        $usuario['passport_number_masked'] = $this->protector->mask($usuario['passport_number']);

        return $usuario;
    }

    private function applyVerificationStatuses(array $data, ?array $existing, ?array &$pendingHistory = null): array
    {
        $timestamp = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $previousSealPath = $existing['firma'] ?? null;
        $currentSealPath = $data['firma'] ?? $previousSealPath;
        $sealHasChanged = $previousSealPath !== $currentSealPath;
        $sealHasMedia = !empty($currentSealPath);
        $sealStatus = $sealHasChanged ? 'pending' : ($data['seal_status'] ?? ($existing['seal_status'] ?? null));
        $data['seal_status'] = $this->normalizeStatus($sealStatus, $sealHasMedia);
        if ($sealHasChanged || ($existing && ($existing['seal_status'] ?? null) !== $data['seal_status'])) {
            $data['seal_status_updated_at'] = $timestamp;
            $data['seal_status_updated_by'] = $userId;
        }

        if ($data['seal_status'] === 'verified') {
            $data['firma_verified_at'] = $timestamp;
            $data['firma_verified_by'] = $userId;
            if (is_array($pendingHistory)) {
                $pendingHistory[] = $this->buildHistoryEvent(UserMediaValidator::TYPE_SEAL, 'verify', $this->captureMetadata($data, 'firma', $currentSealPath), $previousSealPath);
            }
        } else {
            $data['firma_verified_at'] = null;
            $data['firma_verified_by'] = null;
        }

        $previousSignaturePath = $existing['signature_path'] ?? null;
        $currentSignaturePath = $data['signature_path'] ?? $previousSignaturePath;
        $signatureHasChanged = $previousSignaturePath !== $currentSignaturePath;
        $signatureHasMedia = !empty($currentSignaturePath);
        $signatureStatus = $signatureHasChanged ? 'pending' : ($data['signature_status'] ?? ($existing['signature_status'] ?? null));
        $data['signature_status'] = $this->normalizeStatus($signatureStatus, $signatureHasMedia);
        if ($signatureHasChanged || ($existing && ($existing['signature_status'] ?? null) !== $data['signature_status'])) {
            $data['signature_status_updated_at'] = $timestamp;
            $data['signature_status_updated_by'] = $userId;
        }

        if ($data['signature_status'] === 'verified') {
            $data['signature_verified_at'] = $timestamp;
            $data['signature_verified_by'] = $userId;
            if (is_array($pendingHistory)) {
                $pendingHistory[] = $this->buildHistoryEvent(UserMediaValidator::TYPE_SIGNATURE, 'verify', $this->captureMetadata($data, 'signature', $currentSignaturePath), $previousSignaturePath);
            }
        } else {
            $data['signature_verified_at'] = null;
            $data['signature_verified_by'] = null;
        }

        return $data;
    }

    private function profileCompleteness(array $usuario): array
    {
        $checks = [
            'nombre' => !empty($usuario['first_name']) && !empty($usuario['last_name']),
            'contacto' => !empty($usuario['email']),
            'sello' => !empty($usuario['firma']),
            'firma' => !empty($usuario['signature_path']),
        ];

        $completed = count(array_filter($checks));
        $total = count($checks);
        $ratio = $total > 0 ? $completed / $total : 0;

        if ($ratio >= 0.99) {
            return ['label' => 'Completo', 'class' => 'bg-success', 'ratio' => $ratio];
        }

        if ($ratio >= 0.5) {
            return ['label' => 'Parcial', 'class' => 'bg-warning text-dark', 'ratio' => $ratio];
        }

        return ['label' => 'Incompleto', 'class' => 'bg-secondary', 'ratio' => $ratio];
    }
}
