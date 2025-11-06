<?php

namespace Modules\Usuarios\Controllers;

use Core\BaseController;
use Core\Permissions;
use Modules\Usuarios\Models\RolModel;
use Modules\Usuarios\Models\UsuarioModel;
use Modules\Usuarios\Support\PermissionRegistry;
use PDO;

class RolesController extends BaseController
{
    private RolModel $roles;
    private UsuarioModel $usuarios;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->roles = new RolModel($pdo);
        $this->usuarios = new UsuarioModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.roles.view', 'admin.roles.manage', 'admin.roles']);
        $roles = $this->roles->all();

        foreach ($roles as &$role) {
            $role['permissions_list'] = Permissions::normalize($role['permissions'] ?? null);
            $role['users_count'] = $this->usuarios->countByRole((int) $role['id']);
        }
        unset($role);

        $this->render(BASE_PATH . '/modules/Usuarios/views/roles/index.php', [
            'pageTitle' => 'Roles',
            'roles' => $roles,
            'permissionLabels' => PermissionRegistry::all(),
            'status' => $_GET['status'] ?? null,
            'error' => $_GET['error'] ?? null,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.roles.manage', 'admin.roles']);
        $this->render(BASE_PATH . '/modules/Usuarios/views/roles/form.php', [
            'pageTitle' => 'Nuevo rol',
            'formAction' => '/roles/create',
            'method' => 'POST',
            'permissions' => PermissionRegistry::groups(),
            'selectedPermissions' => [],
            'role' => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.roles.manage', 'admin.roles']);
        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->render(BASE_PATH . '/modules/Usuarios/views/roles/form.php', [
                'pageTitle' => 'Nuevo rol',
                'formAction' => '/roles/create',
                'method' => 'POST',
                'permissions' => PermissionRegistry::groups(),
                'selectedPermissions' => PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []),
                'role' => $data,
                'errors' => $errors,
            ]);
            return;
        }

        $this->roles->create($data);
        header('Location: /roles?status=created');
        exit;
    }

    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.roles.manage', 'admin.roles']);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            header('Location: /roles?error=not_found');
            exit;
        }

        $role = $this->roles->find($id);
        if (!$role) {
            header('Location: /roles?error=not_found');
            exit;
        }

        $selected = Permissions::normalize($role['permissions'] ?? null);

        $this->render(BASE_PATH . '/modules/Usuarios/views/roles/form.php', [
            'pageTitle' => 'Editar rol',
            'formAction' => '/roles/edit?id=' . $id,
            'method' => 'POST',
            'permissions' => PermissionRegistry::groups(),
            'selectedPermissions' => $selected,
            'role' => $role,
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.roles.manage', 'admin.roles']);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            header('Location: /roles?error=not_found');
            exit;
        }

        if (!$this->roles->find($id)) {
            header('Location: /roles?error=not_found');
            exit;
        }

        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->render(BASE_PATH . '/modules/Usuarios/views/roles/form.php', [
                'pageTitle' => 'Editar rol',
                'formAction' => '/roles/edit?id=' . $id,
                'method' => 'POST',
                'permissions' => PermissionRegistry::groups(),
                'selectedPermissions' => PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []),
                'role' => array_merge($this->roles->find($id) ?? [], $data),
                'errors' => $errors,
            ]);
            return;
        }

        $this->roles->update($id, $data);
        header('Location: /roles?status=updated');
        exit;
    }

    public function destroy(): void
    {
        $this->requireAuth();
        $this->requirePermission(['administrativo', 'admin.roles.manage', 'admin.roles']);
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            header('Location: /roles?error=not_found');
            exit;
        }

        $role = $this->roles->find($id);
        if (!$role) {
            header('Location: /roles?error=not_found');
            exit;
        }

        if ($this->usuarios->countByRole($id) > 0) {
            header('Location: /roles?error=role_in_use');
            exit;
        }

        $this->roles->delete($id);
        header('Location: /roles?status=deleted');
        exit;
    }

    private function collectInput(): array
    {
        $permissions = PermissionRegistry::sanitizeSelection($_POST['permissions'] ?? []);

        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'El nombre del rol es obligatorio.';
        }

        return $errors;
    }
}
