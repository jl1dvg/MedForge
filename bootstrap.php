<?php
// Iniciar sesión si aún no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar Composer Autoload (si tienes vendor/)
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Cargar conexión a la base de datos
require_once __DIR__ . '/config/database.php';

// Definir constantes de rutas (útiles para incluir archivos desde cualquier nivel)
define('BASE_PATH', __DIR__);
define('CONTROLLER_PATH', BASE_PATH . '/controllers');
define('MODEL_PATH', BASE_PATH . '/models');
define('VIEW_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');