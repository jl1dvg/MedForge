<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Shared\Support\LegacyPermissionResolver;
use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Usuarios\Support\SensitiveDataProtector;
use App\Modules\Usuarios\Support\UserMediaValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UsuariosUiController
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
        'seal_signature' => [
            'input' => 'seal_signature_file',
            'remove' => 'remove_seal_signature',
            'directory' => UserMediaValidator::TYPE_SEAL_SIGNATURE,
            'path_key' => 'seal_signature_path',
            'meta_prefix' => 'seal_signature',
        ],
    ];

    private const PROFILE_MAX_SIZE = 2097152;
    private const PROFILE_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const VERIFICATION_STATES = ['pending', 'verified', 'not_provided'];

    private UserMediaValidator $mediaValidator;
    private SensitiveDataProtector $protector;

    public function __construct()
    {
        $this->mediaValidator = new UserMediaValidator();
        $this->protector = new SensitiveDataProtector();
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $roles = $this->fetchRoles();
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[(int) $role['id']] = (string) $role['name'];
        }

        $rows = DB::table('users as u')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select('u.*', 'r.name as role_name')
            ->orderBy('u.username')
            ->get();

        $users = $rows->map(function (object $row) use ($roleMap): array {
            $user = (array) $row;
            $user['permisos_lista'] = LegacyPermissionCatalog::normalize($user['permisos'] ?? []);
            $user = $this->hydrateSensitiveFields($user);
            $user['display_full_name'] = $this->buildDisplayFullName($user);
            $user['profile_photo_url'] = $this->normalizeMediaUrl($user['profile_photo'] ?? null);
            $user['profile_completeness'] = $this->profileCompleteness($user);
            $user['seal_status'] = $this->normalizeStatus((string) ($user['seal_status'] ?? ''), !empty($user['firma']));
            $user['signature_status'] = $this->normalizeStatus((string) ($user['signature_status'] ?? ''), !empty($user['signature_path']));
            $user['role_label'] = $roleMap[(int) ($user['role_id'] ?? 0)] ?? 'Sin asignar';

            return $user;
        })->all();

        return view('usuarios.v2-index', [
            'pageTitle' => 'Usuarios',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'users' => $users,
            'roleMap' => $roleMap,
            'permissionLabels' => LegacyPermissionCatalog::all(),
            'status' => session('status'),
            'warnings' => session('warnings', []),
            'canManageUsers' => LegacyPermissionCatalog::containsAny(
                LegacyPermissionResolver::resolve($request),
                ['administrativo', 'admin.usuarios.manage', 'admin.usuarios']
            ),
            'currentUserId' => LegacySessionAuth::userId($request),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return $this->renderForm($request, [
            'pageTitle' => 'Nuevo usuario',
            'user' => [
                'is_subscribed' => 0,
                'is_approved' => 0,
                'whatsapp_notify' => 0,
                'seal_status' => 'pending',
                'signature_status' => 'pending',
            ],
            'selectedPermissions' => [],
            'validationErrors' => [],
            'warnings' => [],
            'formAction' => '/usuarios',
            'mode' => 'create',
            'canDelete' => false,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $data = $this->collectInput($request, true);
        [$data, $uploadErrors, $pendingUploads] = $this->handleMediaUploads($request, $data, null);
        $validationErrors = array_merge($this->validate($data, true), $uploadErrors);
        $warnings = $this->duplicateWarnings($data, null);

        if ($validationErrors !== []) {
            $this->rollbackUploads($pendingUploads);
            unset($data['password']);
            $data['firma'] = null;
            $data['profile_photo'] = null;
            $data['signature_path'] = null;
            $data['seal_signature_path'] = null;

            return $this->renderForm($request, [
                'pageTitle' => 'Nuevo usuario',
                'user' => $data,
                'selectedPermissions' => LegacyPermissionCatalog::sanitizeSelection((array) $request->input('permissions', [])),
                'validationErrors' => $validationErrors,
                'warnings' => $warnings,
                'formAction' => '/usuarios',
                'mode' => 'create',
                'canDelete' => false,
                'status' => null,
            ]);
        }

        $data = $this->normalizeWhatsappFields($data);
        if (isset($data['password'])) {
            $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        $data = $this->transformSensitiveFields($data);
        $data = $this->applyVerificationStatuses($data, null, LegacySessionAuth::userId($request));

        try {
            $createdId = $this->createUser($data);
        } catch (\Throwable $exception) {
            $createdId = 0;
            report($exception);
        }

        if ($createdId <= 0) {
            $this->rollbackUploads($pendingUploads);
            unset($data['password']);
            $data['firma'] = null;
            $data['profile_photo'] = null;
            $data['signature_path'] = null;
            $data['seal_signature_path'] = null;
            $validationErrors['general'] = 'No se pudo guardar el usuario. Verifica que la base de datos tenga las columnas de medios actualizadas.';

            return $this->renderForm($request, [
                'pageTitle' => 'Nuevo usuario',
                'user' => $data,
                'selectedPermissions' => LegacyPermissionCatalog::sanitizeSelection((array) $request->input('permissions', [])),
                'validationErrors' => $validationErrors,
                'warnings' => $warnings,
                'formAction' => '/usuarios',
                'mode' => 'create',
                'canDelete' => false,
                'status' => null,
            ]);
        }

        $this->finalizeUploads($pendingUploads);

        return redirect('/usuarios')
            ->with('status', 'created')
            ->with('warnings', $warnings);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $user = $this->findUser($id);
        if ($user === null) {
            return redirect('/usuarios')->with('status', 'not_found');
        }

        $user = $this->hydrateSensitiveFields($user);

        return $this->renderForm($request, [
            'pageTitle' => 'Editar usuario',
            'user' => $user,
            'selectedPermissions' => LegacyPermissionCatalog::normalize($user['permisos'] ?? []),
            'validationErrors' => [],
            'warnings' => [],
            'formAction' => '/usuarios/' . $id,
            'mode' => 'edit',
            'canDelete' => LegacySessionAuth::userId($request) !== $id,
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, int $id): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $existing = $this->findUser($id);
        if ($existing === null) {
            return redirect('/usuarios')->with('status', 'not_found');
        }

        $existing = $this->hydrateSensitiveFields($existing);
        $data = $this->collectInput($request, false, $existing);
        [$data, $uploadErrors, $pendingUploads] = $this->handleMediaUploads($request, $data, $existing);
        $validationErrors = array_merge($this->validate($data, false), $uploadErrors);
        $warnings = $this->duplicateWarnings($data, $id);

        if ($validationErrors !== []) {
            $this->rollbackUploads($pendingUploads);
            unset($data['password']);
            $data['firma'] = $existing['firma'] ?? null;
            $data['profile_photo'] = $existing['profile_photo'] ?? null;
            $data['signature_path'] = $existing['signature_path'] ?? null;
            $data['seal_signature_path'] = $existing['seal_signature_path'] ?? null;

            return $this->renderForm($request, [
                'pageTitle' => 'Editar usuario',
                'user' => array_merge($existing, $data),
                'selectedPermissions' => LegacyPermissionCatalog::sanitizeSelection((array) $request->input('permissions', [])),
                'validationErrors' => $validationErrors,
                'warnings' => $warnings,
                'formAction' => '/usuarios/' . $id,
                'mode' => 'edit',
                'canDelete' => LegacySessionAuth::userId($request) !== $id,
                'status' => null,
            ]);
        }

        $data = $this->normalizeWhatsappFields($data);
        if (isset($data['password'])) {
            $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        $data = $this->transformSensitiveFields($data);
        $data = $this->applyVerificationStatuses($data, $existing, LegacySessionAuth::userId($request));

        try {
            $updated = $this->updateUser($id, $data);
        } catch (\Throwable $exception) {
            $updated = false;
            report($exception);
        }

        if (!$updated) {
            $this->rollbackUploads($pendingUploads);
            unset($data['password']);
            $data['firma'] = $existing['firma'] ?? null;
            $data['profile_photo'] = $existing['profile_photo'] ?? null;
            $data['signature_path'] = $existing['signature_path'] ?? null;
            $data['seal_signature_path'] = $existing['seal_signature_path'] ?? null;
            $validationErrors['general'] = 'No se pudo guardar los cambios. Verifica que la base de datos tenga las columnas de medios actualizadas.';

            return $this->renderForm($request, [
                'pageTitle' => 'Editar usuario',
                'user' => array_merge($existing, $data),
                'selectedPermissions' => LegacyPermissionCatalog::sanitizeSelection((array) $request->input('permissions', [])),
                'validationErrors' => $validationErrors,
                'warnings' => $warnings,
                'formAction' => '/usuarios/' . $id,
                'mode' => 'edit',
                'canDelete' => LegacySessionAuth::userId($request) !== $id,
                'status' => null,
            ]);
        }

        $this->finalizeUploads($pendingUploads);

        if (LegacySessionAuth::userId($request) === $id) {
            $this->syncLegacySessionState(
                $request,
                LegacyPermissionCatalog::normalize($data['permisos'] ?? []),
                $data['role_id'] ?? null
            );
        }

        return redirect('/usuarios/' . $id . '/edit')
            ->with('status', 'updated')
            ->with('warnings', $warnings);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        if (LegacySessionAuth::userId($request) === $id) {
            return redirect('/usuarios')->with('status', 'cannot_delete_self');
        }

        $user = $this->findUser($id);
        if ($user === null) {
            return redirect('/usuarios')->with('status', 'not_found');
        }

        DB::table('users')->where('id', $id)->delete();
        $this->deleteFile($user['firma'] ?? null);
        $this->deleteFile($user['signature_path'] ?? null);
        $this->deleteFile($user['seal_signature_path'] ?? null);
        $this->deleteFile($user['profile_photo'] ?? null);

        return redirect('/usuarios')->with('status', 'deleted');
    }

    public function media(Request $request): JsonResponse|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $requestedId = (int) ($request->query('id', LegacySessionAuth::userId($request) ?? 0));
        if ($requestedId <= 0) {
            return response()->json(['error' => 'missing_user_id'], 400);
        }

        if (!$this->canAccessMedia($request, $requestedId)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $user = $this->findUser($requestedId);
        if ($user === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $payload = [
            'user' => $this->buildUserIdentityPayload($user),
            'seal' => $this->serializeMediaPayload($user, 'firma'),
            'signature' => $this->serializeMediaPayload($user, 'signature_path'),
            'seal_signature' => $this->serializeMediaPayload($user, 'seal_signature_path'),
        ];

        return response()->json($payload, 200, $this->mediaCachingHeaders($payload));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderForm(Request $request, array $context): View
    {
        $user = is_array($context['user'] ?? null) ? $context['user'] : [];
        $user['display_full_name'] = $this->buildDisplayFullName($user);
        $user['firma_url'] = $this->normalizeMediaUrl($user['firma'] ?? null);
        $user['signature_url'] = $this->normalizeMediaUrl($user['signature_path'] ?? null);
        $user['seal_signature_url'] = $this->normalizeMediaUrl($user['seal_signature_path'] ?? null);
        $user['profile_photo_url'] = $this->normalizeMediaUrl($user['profile_photo'] ?? null);

        return view('usuarios.v2-form', [
            'pageTitle' => $context['pageTitle'] ?? 'Usuarios',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'user' => $user,
            'roles' => $this->fetchRoles(),
            'permissions' => LegacyPermissionCatalog::groups(),
            'permissionProfiles' => config('permission_profiles', []),
            'selectedPermissions' => $context['selectedPermissions'] ?? [],
            'validationErrors' => $context['validationErrors'] ?? [],
            'warnings' => $context['warnings'] ?? [],
            'formAction' => $context['formAction'] ?? '/usuarios',
            'mode' => $context['mode'] ?? 'edit',
            'canDelete' => (bool) ($context['canDelete'] ?? false),
            'status' => $context['status'] ?? null,
        ]);
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function fetchRoles(): array
    {
        return DB::table('roles')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUser(int $id): ?array
    {
        $row = DB::table('users as u')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select('u.*', 'r.name as role_name')
            ->where('u.id', $id)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function collectInput(Request $request, bool $isCreate, ?array $existing = null): array
    {
        $permissions = LegacyPermissionCatalog::sanitizeSelection((array) $request->input('permissions', []));

        $nationalId = $this->normalizeSensitive((string) $request->input('national_id', ''));
        $passportNumber = $this->normalizeSensitive((string) $request->input('passport_number', ''));

        if (!$isCreate) {
            if ($nationalId === '' && isset($existing['national_id'])) {
                $nationalId = (string) $existing['national_id'];
            }
            if ($passportNumber === '' && isset($existing['passport_number'])) {
                $passportNumber = (string) $existing['passport_number'];
            }
        }

        $data = [
            'username' => trim((string) $request->input('username', '')),
            'email' => trim((string) $request->input('email', '')),
            'whatsapp_number' => trim((string) $request->input('whatsapp_number', '')),
            'whatsapp_notify' => $request->boolean('whatsapp_notify') ? 1 : 0,
            'first_name' => $this->normalizeNameInput((string) $request->input('first_name', '')),
            'middle_name' => $this->normalizeNameInput((string) $request->input('middle_name', '')),
            'last_name' => $this->normalizeNameInput((string) $request->input('last_name', '')),
            'second_last_name' => $this->normalizeNameInput((string) $request->input('second_last_name', '')),
            'birth_date' => $this->normalizeDate($request->input('birth_date')),
            'cedula' => trim((string) $request->input('cedula', '')),
            'national_id' => $nationalId,
            'passport_number' => $passportNumber,
            'registro' => trim((string) $request->input('registro', '')),
            'sede' => trim((string) $request->input('sede', '')),
            'especialidad' => trim((string) $request->input('especialidad', '')),
            'subespecialidad' => trim((string) $request->input('subespecialidad', '')),
            'is_subscribed' => $request->boolean('is_subscribed') ? 1 : 0,
            'is_approved' => $request->boolean('is_approved') ? 1 : 0,
            'seal_status' => $this->sanitizeStatus((string) $request->input('seal_status', $existing['seal_status'] ?? 'pending')),
            'signature_status' => $this->sanitizeStatus((string) $request->input('signature_status', $existing['signature_status'] ?? 'pending')),
            'role_id' => $this->resolveRoleId($request->input('role_id')),
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
            'seal_signature_path' => $existing['seal_signature_path'] ?? null,
            'seal_signature_mime' => $existing['seal_signature_mime'] ?? null,
            'seal_signature_size' => $existing['seal_signature_size'] ?? null,
            'seal_signature_hash' => $existing['seal_signature_hash'] ?? null,
            'seal_signature_updated_at' => $existing['seal_signature_updated_at'] ?? null,
            'seal_signature_updated_by' => $existing['seal_signature_updated_by'] ?? null,
        ];

        $data['nombre'] = $this->buildLegacyFullName($data);

        $password = trim((string) $request->input('password', ''));
        if ($isCreate || $password !== '') {
            $data['password'] = $password;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validate(array $data, bool $isCreate): array
    {
        $errors = [];

        if (trim((string) ($data['username'] ?? '')) === '') {
            $errors['username'] = 'El nombre de usuario es obligatorio.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El correo electrónico no es válido.';
        }

        $whatsappNumber = trim((string) ($data['whatsapp_number'] ?? ''));
        if ($whatsappNumber !== '' && $this->normalizeWhatsappNumber($whatsappNumber) === null) {
            $errors['whatsapp_number'] = 'El número de WhatsApp no es válido.';
        }

        if (!empty($data['whatsapp_notify']) && $whatsappNumber === '') {
            $errors['whatsapp_number'] = 'Debes registrar un número para activar notificaciones de WhatsApp.';
        }

        if (($data['birth_date'] ?? null) !== null && !$this->isValidDate((string) $data['birth_date'])) {
            $errors['birth_date'] = 'La fecha de nacimiento no es válida.';
        }

        $nameFields = [
            'first_name' => 'Nombre',
            'last_name' => 'Primer apellido',
            'middle_name' => 'Segundo nombre',
            'second_last_name' => 'Segundo apellido',
        ];

        if (trim((string) ($data['first_name'] ?? '')) === '') {
            $errors['first_name'] = 'El nombre es obligatorio.';
        }

        if (trim((string) ($data['last_name'] ?? '')) === '') {
            $errors['last_name'] = 'El primer apellido es obligatorio.';
        }

        foreach ($nameFields as $field => $label) {
            $value = trim((string) ($data[$field] ?? ''));
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

        if ($isCreate && trim((string) ($data['password'] ?? '')) === '') {
            $errors['password'] = 'La contraseña es obligatoria.';
        }

        if (($data['role_id'] ?? null) !== null && !$this->roleExists((int) $data['role_id'])) {
            $errors['role_id'] = 'El rol seleccionado no existe.';
        }

        $nationalId = trim((string) ($data['national_id'] ?? ''));
        if ($nationalId !== '' && !$this->isValidIdentityValue($nationalId)) {
            $errors['national_id'] = 'La identificación nacional debe tener entre 4 y 32 caracteres alfanuméricos.';
        }

        $passportNumber = trim((string) ($data['passport_number'] ?? ''));
        if ($passportNumber !== '' && !$this->isValidIdentityValue($passportNumber)) {
            $errors['passport_number'] = 'El número de pasaporte debe tener entre 4 y 32 caracteres alfanuméricos.';
        }

        if (!in_array((string) ($data['seal_status'] ?? ''), self::VERIFICATION_STATES, true)) {
            $errors['seal_status'] = 'El estado del sello no es válido.';
        }

        if (!in_array((string) ($data['signature_status'] ?? ''), self::VERIFICATION_STATES, true)) {
            $errors['signature_status'] = 'El estado de la firma no es válido.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array{delete: array<int, string>, new: array<int, string>}}
     */
    private function handleMediaUploads(Request $request, array $data, ?array $existing): array
    {
        $errors = [];
        $pending = ['delete' => [], 'new' => []];

        foreach (self::MEDIA_TYPES as $config) {
            [$data, $error] = $this->processMediaType($request, $config, $data, $existing, $pending);
            if ($error !== null) {
                $errors[$config['input']] = $error;
            }
        }

        [$data['profile_photo'], $photoError] = $this->processProfilePhoto(
            $request,
            isset($data['profile_photo']) ? (string) $data['profile_photo'] : null,
            isset($existing['profile_photo']) ? (string) $existing['profile_photo'] : null,
            $request->boolean('remove_profile_photo'),
            $pending
        );
        if ($photoError !== null) {
            $errors['profile_photo_file'] = $photoError;
        }

        return [$data, $errors, $pending];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @param array{delete: array<int, string>, new: array<int, string>} $pending
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function processMediaType(Request $request, array $config, array $data, ?array $existing, array &$pending): array
    {
        $pathKey = (string) $config['path_key'];
        $metaPrefix = (string) $config['meta_prefix'];
        $removeRequested = $request->boolean((string) $config['remove']);
        $existingPath = is_array($existing) ? ($existing[$pathKey] ?? null) : null;
        $currentPath = $data[$pathKey] ?? $existingPath;

        if ($removeRequested && is_string($existingPath) && $existingPath !== '') {
            $this->markForDeletion($pending, $existingPath);
            $currentPath = null;
            $this->clearMediaMetadata($data, $metaPrefix, true, LegacySessionAuth::userId($request));
        }

        if (!$request->files->has((string) $config['input'])) {
            $data[$pathKey] = $currentPath;

            return [$data, null];
        }

        $file = $request->file((string) $config['input']);
        $validation = $this->mediaValidator->validate($file instanceof UploadedFile ? $file : null);
        if ($validation['error'] !== null) {
            return [$data, $validation['error']];
        }

        if (!$file instanceof UploadedFile) {
            return [$data, 'No se pudo procesar el archivo subido.'];
        }

        $filename = $this->mediaValidator->generateFilename((string) $validation['extension']);
        $destination = $this->mediaValidator->destinationFor((string) $config['directory'], $filename, $this->legacyPublicRoot());
        $destinationDir = dirname($destination['absolute']);

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            return [$data, 'No se pudo preparar el directorio de carga.'];
        }

        try {
            $file->move($destinationDir, $filename);
        } catch (\Throwable) {
            return [$data, 'No se pudo guardar el archivo subido.'];
        }

        $publicPath = $destination['public'];
        $this->markForNew($pending, $publicPath);

        if (is_string($existingPath) && $existingPath !== '' && !$removeRequested) {
            $this->markForDeletion($pending, $existingPath);
        }

        $data[$pathKey] = $publicPath;
        $this->applyMediaMetadata($data, $metaPrefix, $validation, LegacySessionAuth::userId($request));

        return [$data, null];
    }

    /**
     * @param array{delete: array<int, string>, new: array<int, string>} $pending
     * @return array{0: string|null, 1: string|null}
     */
    private function processProfilePhoto(
        Request $request,
        ?string $current,
        ?string $existing,
        bool $removeRequested,
        array &$pending
    ): array {
        $currentPath = $current ?? $existing;

        if ($removeRequested && is_string($existing) && $existing !== '') {
            $this->markForDeletion($pending, $existing);
            $currentPath = null;
        }

        if (!$request->files->has('profile_photo_file')) {
            return [$currentPath, null];
        }

        $file = $request->file('profile_photo_file');
        if (!$file instanceof UploadedFile) {
            return [$currentPath, 'No se pudo procesar el archivo subido.'];
        }

        if (!$file->isValid()) {
            return [$currentPath, $this->uploadErrorMessage($file->getError())];
        }

        if ((int) ($file->getSize() ?? 0) > self::PROFILE_MAX_SIZE) {
            return [$currentPath, 'El archivo excede el tamaño máximo permitido (2 MB).'];
        }

        $mime = $this->detectMimeType((string) $file->getPathname());
        if ($mime === null || !isset(self::PROFILE_MIME_TYPES[$mime])) {
            return [$currentPath, 'El archivo debe ser una imagen PNG, JPG o WEBP.'];
        }

        $filename = $this->generateFilename(self::PROFILE_MIME_TYPES[$mime]);
        $destinationDir = $this->legacyPublicRoot() . '/uploads/users';
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            return [$currentPath, 'No se pudo preparar el directorio de carga.'];
        }

        try {
            $file->move($destinationDir, $filename);
        } catch (\Throwable) {
            return [$currentPath, 'No se pudo guardar el archivo subido.'];
        }

        $publicPath = '/uploads/users/' . $filename;
        $this->markForNew($pending, $publicPath);

        if (is_string($existing) && $existing !== '' && !$removeRequested) {
            $this->markForDeletion($pending, $existing);
        }

        return [$publicPath, null];
    }

    /**
     * @param array{delete: array<int, string>, new: array<int, string>} $pending
     */
    private function markForDeletion(array &$pending, string $path): void
    {
        if (!in_array($path, $pending['delete'], true)) {
            $pending['delete'][] = $path;
        }
    }

    /**
     * @param array{delete: array<int, string>, new: array<int, string>} $pending
     */
    private function markForNew(array &$pending, string $path): void
    {
        if (!in_array($path, $pending['new'], true)) {
            $pending['new'][] = $path;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array{error:string|null,extension:string|null,mime:string|null,size:int|null,hash:string|null,width:int|null,height:int|null} $validation
     */
    private function applyMediaMetadata(array &$data, string $prefix, array $validation, ?int $userId): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');

        $data[$prefix . '_mime'] = $validation['mime'];
        $data[$prefix . '_size'] = $validation['size'];
        $data[$prefix . '_hash'] = $validation['hash'];
        $data[$prefix . '_updated_at'] = $timestamp;
        $data[$prefix . '_updated_by'] = $userId;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function clearMediaMetadata(array &$data, string $prefix, bool $withAudit, ?int $userId): void
    {
        $data[$prefix . '_mime'] = null;
        $data[$prefix . '_size'] = null;
        $data[$prefix . '_hash'] = null;

        if ($withAudit) {
            $data[$prefix . '_updated_at'] = now()->format('Y-m-d H:i:s');
            $data[$prefix . '_updated_by'] = $userId;
        }
    }

    /**
     * @param array{delete: array<int, string>, new: array<int, string>} $pending
     */
    private function finalizeUploads(array $pending): void
    {
        foreach (array_unique($pending['delete']) as $path) {
            $this->deleteFile($path);
        }
    }

    /**
     * @param array{delete: array<int, string>, new: array<int, string>} $pending
     */
    private function rollbackUploads(array $pending): void
    {
        foreach (array_unique($pending['new']) as $path) {
            $this->deleteFile($path);
        }
    }

    private function deleteFile(?string $path): void
    {
        if (!is_string($path) || $path === '') {
            return;
        }

        $normalized = '/' . ltrim($path, '/');
        if (!str_starts_with($normalized, '/uploads/users/')) {
            return;
        }

        $absolute = $this->legacyPublicRoot() . $normalized;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function normalizeNameInput(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return mb_substr($normalized, 0, 100, 'UTF-8');
    }

    private function isValidNameCharacters(string $value): bool
    {
        return !preg_match('/[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'"\s]/u', $value);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildLegacyFullName(array $data): string
    {
        $parts = array_filter([
            trim((string) ($data['first_name'] ?? '')),
            trim((string) ($data['middle_name'] ?? '')),
            trim((string) ($data['last_name'] ?? '')),
            trim((string) ($data['second_last_name'] ?? '')),
        ], static fn ($value): bool => $value !== '');

        return trim(implode(' ', $parts));
    }

    /**
     * @param array<string, mixed> $user
     */
    private function buildDisplayFullName(array $user): string
    {
        $fullName = trim((string) ($user['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $structured = trim(implode(' ', array_filter([
            trim((string) ($user['first_name'] ?? '')),
            trim((string) ($user['middle_name'] ?? '')),
            trim((string) ($user['last_name'] ?? '')),
            trim((string) ($user['second_last_name'] ?? '')),
        ], static fn ($value): bool => $value !== '')));

        if ($structured !== '') {
            return $structured;
        }

        $legacy = trim((string) ($user['nombre'] ?? ''));
        if ($legacy !== '') {
            return $legacy;
        }

        return trim((string) ($user['username'] ?? ''));
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = date_create_from_format('Y-m-d', (string) $value);

        return $date ? $date->format('Y-m-d') : null;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function normalizeSensitive(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', $value) ?? '';

        return mb_substr($normalized, 0, 64, 'UTF-8');
    }

    private function isValidIdentityValue(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9-]{4,32}$/', $value);
    }

    private function sanitizeStatus(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, self::VERIFICATION_STATES, true) ? $normalized : 'pending';
    }

    private function normalizeStatus(string $status, bool $hasMedia): string
    {
        if (!$hasMedia) {
            return 'not_provided';
        }

        $normalized = $this->sanitizeStatus($status);

        return $normalized === 'not_provided' ? 'pending' : $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function duplicateWarnings(array $data, ?int $excludeId): array
    {
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['birth_date'])) {
            return [];
        }

        $query = DB::table('users')
            ->select(['id', 'username', 'first_name', 'last_name', 'birth_date'])
            ->where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->where('birth_date', $data['birth_date']);

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        $matches = $query->get();
        if ($matches->isEmpty()) {
            return [];
        }

        $warnings = [];
        foreach ($matches as $match) {
            $warnings[] = sprintf(
                'Posible duplicado con %s (ID %d, nacimiento %s).',
                trim((string) (($match->first_name ?? '') . ' ' . ($match->last_name ?? ''))),
                (int) ($match->id ?? 0),
                (string) ($match->birth_date ?? 'sin fecha')
            );
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function transformSensitiveFields(array $data): array
    {
        $data['national_id_encrypted'] = $this->protector->encrypt(isset($data['national_id']) ? (string) $data['national_id'] : null);
        $data['passport_number_encrypted'] = $this->protector->encrypt(isset($data['passport_number']) ? (string) $data['passport_number'] : null);

        unset($data['national_id'], $data['passport_number']);

        return $data;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function hydrateSensitiveFields(array $user): array
    {
        $user['national_id'] = $this->protector->decrypt(isset($user['national_id_encrypted']) ? (string) $user['national_id_encrypted'] : null);
        $user['passport_number'] = $this->protector->decrypt(isset($user['passport_number_encrypted']) ? (string) $user['passport_number_encrypted'] : null);
        $user['national_id_masked'] = $this->protector->mask(isset($user['national_id']) ? (string) $user['national_id'] : null);
        $user['passport_number_masked'] = $this->protector->mask(isset($user['passport_number']) ? (string) $user['passport_number'] : null);

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function applyVerificationStatuses(array $data, ?array $existing, ?int $actorUserId): array
    {
        $timestamp = now()->format('Y-m-d H:i:s');

        $previousSealPath = $existing['firma'] ?? null;
        $currentSealPath = $data['firma'] ?? $previousSealPath;
        $sealHasChanged = $previousSealPath !== $currentSealPath;
        $sealHasMedia = !empty($currentSealPath);
        $sealStatus = $sealHasChanged ? 'pending' : ($data['seal_status'] ?? ($existing['seal_status'] ?? null));
        $data['seal_status'] = $this->normalizeStatus((string) $sealStatus, $sealHasMedia);
        if ($sealHasChanged || ($existing !== null && ($existing['seal_status'] ?? null) !== $data['seal_status'])) {
            $data['seal_status_updated_at'] = $timestamp;
            $data['seal_status_updated_by'] = $actorUserId;
        }

        $previousSignaturePath = $existing['signature_path'] ?? null;
        $currentSignaturePath = $data['signature_path'] ?? $previousSignaturePath;
        $signatureHasChanged = $previousSignaturePath !== $currentSignaturePath;
        $signatureHasMedia = !empty($currentSignaturePath);
        $signatureStatus = $signatureHasChanged ? 'pending' : ($data['signature_status'] ?? ($existing['signature_status'] ?? null));
        $data['signature_status'] = $this->normalizeStatus((string) $signatureStatus, $signatureHasMedia);
        if ($signatureHasChanged || ($existing !== null && ($existing['signature_status'] ?? null) !== $data['signature_status'])) {
            $data['signature_status_updated_at'] = $timestamp;
            $data['signature_status_updated_by'] = $actorUserId;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{label:string,class:string,ratio:float}
     */
    private function profileCompleteness(array $user): array
    {
        $checks = [
            'nombre' => !empty($user['first_name']) && !empty($user['last_name']),
            'contacto' => !empty($user['email']),
            'sello' => !empty($user['firma']),
            'firma' => !empty($user['signature_path']),
        ];

        $completed = count(array_filter($checks));
        $total = count($checks);
        $ratio = $total > 0 ? $completed / $total : 0.0;

        if ($ratio >= 0.99) {
            return ['label' => 'Completo', 'class' => 'bg-success', 'ratio' => $ratio];
        }

        if ($ratio >= 0.5) {
            return ['label' => 'Parcial', 'class' => 'bg-warning text-dark', 'ratio' => $ratio];
        }

        return ['label' => 'Incompleto', 'class' => 'bg-danger', 'ratio' => $ratio];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeWhatsappFields(array $data): array
    {
        $raw = trim((string) ($data['whatsapp_number'] ?? ''));
        if ($raw === '') {
            $data['whatsapp_number'] = null;
            $data['whatsapp_notify'] = 0;

            return $data;
        }

        $normalized = $this->normalizeWhatsappNumber($raw);
        $data['whatsapp_number'] = $normalized ?? $raw;

        return $data;
    }

    private function normalizeWhatsappNumber(string $number): ?string
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        if (str_starts_with($number, '+')) {
            $digits = '+' . preg_replace('/\D+/', '', substr($number, 1));

            return strlen($digits) > 1 ? $digits : null;
        }

        $digits = preg_replace('/\D+/', '', $number);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $countryCode = $this->defaultCountryCode();
        if ($countryCode !== '' && !str_starts_with($digits, $countryCode)) {
            $digits = $countryCode . $digits;
        }

        return '+' . $digits;
    }

    private function defaultCountryCode(): string
    {
        try {
            $value = DB::table('app_settings')
                ->where('name', 'whatsapp_cloud_default_country_code')
                ->value('value');
        } catch (\Throwable) {
            $value = null;
        }

        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function resolveRoleId(mixed $roleId): ?int
    {
        if ($roleId === null || $roleId === '') {
            return null;
        }

        $value = (int) $roleId;

        return $value > 0 ? $value : null;
    }

    private function roleExists(int $roleId): bool
    {
        return DB::table('roles')->where('id', $roleId)->exists();
    }

    private function canAccessMedia(Request $request, int $userId): bool
    {
        $currentUserId = LegacySessionAuth::userId($request);
        if ($currentUserId !== null && $currentUserId === $userId) {
            return true;
        }

        return LegacyPermissionCatalog::containsAny(
            LegacyPermissionResolver::resolve($request),
            ['administrativo', 'admin.usuarios.manage', 'admin.usuarios.view', 'admin.usuarios', 'superuser']
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array{id:int|null,full_name:string|null,legacy_full_name:string|null,first_name:string|null,middle_name:string|null,last_name:string|null,second_last_name:string|null,username:string|null,email:string|null}
     */
    private function buildUserIdentityPayload(array $user): array
    {
        return [
            'id' => isset($user['id']) ? (int) $user['id'] : null,
            'full_name' => $this->safeString($user['full_name'] ?? null) ?? $this->safeString($user['nombre'] ?? null),
            'legacy_full_name' => $this->safeString($user['nombre'] ?? null),
            'first_name' => $this->safeString($user['first_name'] ?? null),
            'middle_name' => $this->safeString($user['middle_name'] ?? null),
            'last_name' => $this->safeString($user['last_name'] ?? null),
            'second_last_name' => $this->safeString($user['second_last_name'] ?? null),
            'username' => $this->safeString($user['username'] ?? null),
            'email' => $this->safeString($user['email'] ?? null),
        ];
    }

    private function safeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function serializeMediaPayload(array $user, string $pathKey): ?array
    {
        $prefix = match ($pathKey) {
            'firma' => 'firma',
            'seal_signature_path' => 'seal_signature',
            default => 'signature',
        };

        $path = $user[$pathKey] ?? null;
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return [
            'path' => $path,
            'url' => $this->normalizeMediaUrl($path),
            'mime' => $user[$prefix . '_mime'] ?? null,
            'size' => isset($user[$prefix . '_size']) ? (int) $user[$prefix . '_size'] : null,
            'hash' => $user[$prefix . '_hash'] ?? null,
            'updated_at' => $user[$prefix . '_updated_at'] ?? null,
            'updated_by' => isset($user[$prefix . '_updated_by']) ? (int) $user[$prefix . '_updated_by'] : null,
        ];
    }

    private function normalizeMediaUrl(mixed $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $trimmed)) {
            return $trimmed;
        }

        return '/' . ltrim($trimmed, '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function mediaCachingHeaders(array $payload): array
    {
        $ttl = 900;
        $stale = 120;
        $hashSource = json_encode([
            $payload['seal']['hash'] ?? null,
            $payload['signature']['hash'] ?? null,
            $payload['seal_signature']['hash'] ?? null,
            $payload['seal']['updated_at'] ?? null,
            $payload['signature']['updated_at'] ?? null,
            $payload['seal_signature']['updated_at'] ?? null,
        ]);

        $headers = [
            'Cache-Control' => 'public, max-age=' . $ttl . ', stale-while-revalidate=' . $stale,
            'CDN-Cache-Control' => 'public, max-age=' . $ttl . ', stale-while-revalidate=' . $stale,
        ];

        if ($hashSource !== false) {
            $headers['ETag'] = '"' . sha1($hashSource) . '"';
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createUser(array $data): int
    {
        $payload = $this->preparePayload($data, true);

        return (int) DB::table('users')->insertGetId($payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateUser(int $id, array $data): bool
    {
        $payload = $this->preparePayload($data, false);
        if ($payload === []) {
            return false;
        }

        DB::table('users')->where('id', $id)->update($payload);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, bool $isCreate): array
    {
        $defaults = [
            'username' => '',
            'password' => null,
            'email' => '',
            'whatsapp_number' => null,
            'whatsapp_notify' => 0,
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'second_last_name' => '',
            'birth_date' => null,
            'is_subscribed' => 0,
            'is_approved' => 0,
            'nombre' => '',
            'cedula' => '',
            'national_id_encrypted' => null,
            'passport_number_encrypted' => null,
            'registro' => '',
            'sede' => '',
            'firma' => null,
            'firma_mime' => null,
            'firma_size' => null,
            'firma_hash' => null,
            'firma_created_at' => null,
            'firma_created_by' => null,
            'firma_updated_at' => null,
            'firma_updated_by' => null,
            'firma_verified_at' => null,
            'firma_verified_by' => null,
            'firma_deleted_at' => null,
            'firma_deleted_by' => null,
            'seal_status' => 'pending',
            'seal_status_updated_at' => null,
            'seal_status_updated_by' => null,
            'profile_photo' => null,
            'signature_path' => null,
            'signature_mime' => null,
            'signature_size' => null,
            'signature_hash' => null,
            'signature_created_at' => null,
            'signature_created_by' => null,
            'signature_updated_at' => null,
            'signature_updated_by' => null,
            'signature_verified_at' => null,
            'signature_verified_by' => null,
            'signature_deleted_at' => null,
            'signature_deleted_by' => null,
            'signature_status' => 'pending',
            'signature_status_updated_at' => null,
            'signature_status_updated_by' => null,
            'seal_signature_path' => null,
            'seal_signature_mime' => null,
            'seal_signature_size' => null,
            'seal_signature_hash' => null,
            'seal_signature_created_at' => null,
            'seal_signature_created_by' => null,
            'seal_signature_updated_at' => null,
            'seal_signature_updated_by' => null,
            'seal_signature_verified_at' => null,
            'seal_signature_verified_by' => null,
            'seal_signature_deleted_at' => null,
            'seal_signature_deleted_by' => null,
            'especialidad' => '',
            'subespecialidad' => '',
            'permisos' => '[]',
            'role_id' => null,
        ];

        $payload = [];
        foreach ($defaults as $column => $default) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            } elseif ($isCreate && $default !== null) {
                $payload[$column] = $default;
            }
        }

        if (!$isCreate && array_key_exists('password', $payload) && !$payload['password']) {
            unset($payload['password']);
        }

        return $payload;
    }

    /**
     * @param array<int, string> $permissions
     */
    private function syncLegacySessionState(Request $request, array $permissions, ?int $roleId): void
    {
        $sessionId = LegacySessionAuth::sessionId($request);
        if ($sessionId === '') {
            return;
        }

        $originalName = session_name();
        $originalId = session_id();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;

        if ($wasActive) {
            @session_write_close();
        }

        session_name('PHPSESSID');
        session_id($sessionId);

        if (@session_start()) {
            $_SESSION['permisos'] = LegacyPermissionCatalog::normalize($permissions);
            $_SESSION['role_id'] = $roleId;
            @session_write_close();
        }

        if ($originalName !== '') {
            @session_name($originalName);
        }

        if ($originalId !== '') {
            @session_id($originalId);
        }
    }

    private function legacyPublicRoot(): string
    {
        return dirname(base_path()) . '/public';
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
}
