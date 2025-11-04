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

    public function loginForm()
    {
        $this->render($this->loginViewPath(), [
            'title' => 'Iniciar sesión',
            'bodyClass' => 'hold-transition theme-primary bg-img',
        ]);
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
                $this->render($this->loginViewPath(), [
                    'title' => 'Iniciar sesión',
                    'error' => 'Credenciales incorrectas',
                    'bodyClass' => 'hold-transition theme-primary bg-img',
                ]);
            }
        } catch (PDOException $e) {
            $this->render($this->loginViewPath(), [
                'title' => 'Error de conexión',
                'error' => 'Error: ' . $e->getMessage(),
                'bodyClass' => 'hold-transition theme-primary bg-img',
            ]);
        }
    }

    public function logout()
    {
        Auth::logout();
        header('Location: /auth/login');
        exit;
    }
}