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
        $data = $this->collectInput(true);
        $errors = $this->validate($data, true);

        if (!empty($errors)) {
            $formData = $data;
            unset($formData['password']);
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
        header('Location: /usuarios?status=created');
        exit;
    }

    public function edit(): void
    {
        $this->requireAuth();
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
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        $data = $this->collectInput(false);
        $errors = $this->validate($data, false);

        if (!empty($errors)) {
            $formData = $data;
            unset($formData['password']);
            $usuario = array_merge($this->usuarios->find($id) ?? [], $formData);
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
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            header('Location: /usuarios?error=not_found');
            exit;
        }

        if ((int) ($_SESSION['user_id'] ?? 0) === $id) {
            header('Location: /usuarios?error=cannot_delete_self');
            exit;
        }

        $this->usuarios->delete($id);
        header('Location: /usuarios?status=deleted');
        exit;
    }

    private function collectInput(bool $isCreate): array
    {
        $permissions = PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []);

        $data = [
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'cedula' => trim((string) ($_POST['cedula'] ?? '')),
            'registro' => trim((string) ($_POST['registro'] ?? '')),
            'sede' => trim((string) ($_POST['sede'] ?? '')),
            'firma' => $_POST['firma'] ?? null,
            'especialidad' => trim((string) ($_POST['especialidad'] ?? '')),
            'subespecialidad' => trim((string) ($_POST['subespecialidad'] ?? '')),
            'is_subscribed' => isset($_POST['is_subscribed']) ? 1 : 0,
            'is_approved' => isset($_POST['is_approved']) ? 1 : 0,
            'role_id' => $this->resolveRoleId($_POST['role_id'] ?? null),
            'permisos' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
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
}
