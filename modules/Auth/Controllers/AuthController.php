<?php

namespace Modules\Auth\Controllers;

use Core\Auth;
use Core\BaseController;
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
            $stmt = $this->pdo->prepare("SELECT id, username, password, permisos FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Guardar sesión usando tu estructura anterior
                Auth::login($user['id'], $user['permisos'] ?? []);
                $_SESSION['username'] = $user['username'] ?? $username; // ← importante para el header
                $_SESSION['permisos'] = $_SESSION['permisos'] ?? [];
                $_SESSION['session_active'] = true;
                $_SESSION['session_start_time'] = time();
                $_SESSION['last_activity_time'] = time();

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