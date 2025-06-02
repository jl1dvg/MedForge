<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id, password, permisos FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['permisos'] = $user['permisos'];
            $_SESSION['session_active'] = true;
            $_SESSION['session_start_time'] = time();
            $_SESSION['last_activity_time'] = time();

            header('Location: ../views/main.php');
            exit;
        } else {
            echo 'Credenciales incorrectas';
        }
    } catch (PDOException $e) {
        echo 'Error de conexión: ' . $e->getMessage();
    }
}