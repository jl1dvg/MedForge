<?php
require_once __DIR__ . '/bootstrap.php';

// Verifica si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    // Si no está autenticado, redirige al login
    header('Location: views/login.php');
    exit();
} else {
    // Si está autenticado, redirige al Dashboard
    header('Location: views/main.php');
    exit();
}