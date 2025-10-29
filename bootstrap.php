<?php
date_default_timezone_set('America/Guayaquil');
// Habilitar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si aún no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === Cargar Composer Autoload ===
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// === Autocargador manual para módulos ===
spl_autoload_register(function ($class) {
    $prefixes = [
        'Modules\\' => __DIR__ . '/modules/',
        'Core\\' => __DIR__ . '/core/',
        'Controllers\\' => __DIR__ . '/controllers/',
        'Models\\' => __DIR__ . '/models/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        // Normalizamos los separadores
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        // Probamos con y sin capitalización (por compatibilidad con IONOS)
        $file = $baseDir . $relativePath;
        $altFile = $baseDir . strtolower($relativePath);

        if (file_exists($file)) {
            require_once $file;
            return;
        } elseif (file_exists($altFile)) {
            require_once $altFile;
            return;
        }
    }
});

// === Cargar variables de entorno (.env) ===
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Ahora puedes usar $_ENV['OPENAI_API_KEY'] o getenv('OPENAI_API_KEY')

// Cargar conexión a la base de datos
$pdo = require_once __DIR__ . '/config/database.php';

// Definir constantes de rutas
define('BASE_PATH', __DIR__);
define('CONTROLLER_PATH', BASE_PATH . '/controllers');
define('MODEL_PATH', BASE_PATH . '/models');
define('HELPER_PATH', BASE_PATH . '/helpers');
define('VIEW_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');

// URL base del sitio
define('BASE_URL', 'https://asistentecive.consulmed.me/');

// Helper para generar URLs públicas
function asset($path)
{
    return BASE_URL . 'public/' . ltrim($path, '/');
}

// Expiración automática de sesión tras 1 hora de inactividad
if (isset($_SESSION['last_activity_time']) && (time() - $_SESSION['last_activity_time']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: /views/login.php?expired=1");
    exit();
} else {
    $_SESSION['last_activity_time'] = time();
}