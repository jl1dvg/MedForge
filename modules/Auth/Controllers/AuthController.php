<?php

namespace Modules\Auth\Controllers;

use Core\Auth;
use Core\BaseController;
use Core\Permissions;
use PDO;
use PDOException;

class AuthController extends BaseController
{
    private function loginViewPath(): string
    {
        // Ruta absoluta y explícita para evitar que __DIR__ apunte a /Controllers
        return BASE_PATH . '/modules/Auth/views/login.php';
    }

    private function loginViewData(array $overrides = []): array
    {
        $defaults = [
            'title' => 'Iniciar sesión',
            'bodyClass' => 'hold-transition auth-body',
            'styles' => ['css/auth.css'],
        ];

        $status = null;
        if (isset($_GET['expired'])) {
            $status = [
                'type' => 'warning',
                'message' => 'Tu sesión expiró. Inicia sesión nuevamente para continuar.',
            ];
        } elseif (isset($_GET['logged_out'])) {
            $status = [
                'type' => 'success',
                'message' => 'Has cerrado sesión correctamente.',
            ];
        } elseif (isset($_GET['auth_required'])) {
            $status = [
                'type' => 'info',
                'message' => 'Necesitas iniciar sesión para acceder a esa sección.',
            ];
        }

        if ($status) {
            $defaults['status'] = $status;
        }

        if (isset($overrides['styles'])) {
            $overrides['styles'] = array_merge($defaults['styles'], (array) $overrides['styles']);
        }

        $merged = array_merge($defaults, $overrides);
        $merged['styles'] = array_values(array_unique($merged['styles'] ?? $defaults['styles']));

        return $merged;
    }

    public function loginForm()
    {
        $this->render($this->loginViewPath(), $this->loginViewData());
    }

    public function login()
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.id, u.username, u.password, u.permisos, u.role_id, r.permissions AS role_permissions
                 FROM users u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE u.username = :username
                 LIMIT 1"
            );
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $permissions = Permissions::merge($user['permisos'] ?? [], $user['role_permissions'] ?? []);
                $roleId = isset($user['role_id']) ? (int) $user['role_id'] : null;

                Auth::login($user['id'], $permissions, $roleId);
                $_SESSION['username'] = $user['username'] ?? $username; // ← importante para el header

                header('Location: /dashboard');
                exit;
            } else {
                $this->render($this->loginViewPath(), $this->loginViewData([
                    'error' => 'Credenciales incorrectas. Verifica tu usuario o contraseña.',
                    'formData' => [
                        'username' => $username,
                    ],
                ]));
            }
        } catch (PDOException $e) {
            $this->render($this->loginViewPath(), $this->loginViewData([
                'title' => 'Error de conexión',
                'error' => 'No pudimos conectarnos con la base de datos. Intenta más tarde.',
            ]));
        }
    }

    public function logout()
    {
        Auth::logout();
        header('Location: /auth/login?logged_out=1');
        exit;
    }
}
