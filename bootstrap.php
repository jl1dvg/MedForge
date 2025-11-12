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

        // Probamos con diferentes capitalizaciones (por compatibilidad con servidores sensibles a mayúsculas)
        $file = $baseDir . $relativePath;
        if (file_exists($file)) {
            require_once $file;
            return;
        }

        $altFile = $baseDir . strtolower($relativePath);
        if (file_exists($altFile)) {
            require_once $altFile;
            return;
        }

        $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
        $fileName = array_pop($segments);
        $lowerDirPath = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $segments));
        $normalizedPath = rtrim($baseDir . $lowerDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if ($lowerDirPath !== '' && file_exists($normalizedPath)) {
            require_once $normalizedPath;
            return;
        }
    }
});

// === Cargar variables de entorno (.env) ===
use Dotenv\Dotenv;

if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable(__DIR__);

    if (method_exists($dotenv, 'safeLoad')) {
        $dotenv->safeLoad();
    } else {
        $dotenv->load();
    }
}

// Ahora puedes usar $_ENV['OPENAI_API_KEY'] o getenv('OPENAI_API_KEY')

// Cargar conexión a la base de datos (se puede omitir en entornos de prueba locales)
$skipDbConnectionRaw = $_ENV['SKIP_DB_CONNECTION'] ?? getenv('SKIP_DB_CONNECTION');

if (is_bool($skipDbConnectionRaw)) {
    $skipDbConnection = $skipDbConnectionRaw;
} elseif ($skipDbConnectionRaw !== null) {
    $skipDbConnection = filter_var((string) $skipDbConnectionRaw, FILTER_VALIDATE_BOOLEAN);
} else {
    $skipDbConnection = false;
}

$pdo = null;

if (!$skipDbConnection) {
    $pdo = require_once __DIR__ . '/config/database.php';
}

// Definir constantes de rutas
define('BASE_PATH', __DIR__);
define('CONTROLLER_PATH', BASE_PATH . '/controllers');
define('MODEL_PATH', BASE_PATH . '/models');
define('HELPER_PATH', BASE_PATH . '/helpers');
define('VIEW_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');

// URL base del sitio
function determineBaseUrl(): string
{
    $envBaseUrl = $_ENV['BASE_URL'] ?? $_SERVER['BASE_URL'] ?? getenv('BASE_URL');

    if (!empty($envBaseUrl)) {
        return rtrim($envBaseUrl, '/') . '/';
    }

    if (!empty($_SERVER['HTTP_HOST']) || !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $scheme = 'http';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? null;

        if (!empty($forwardedProto)) {
            $scheme = str_contains($forwardedProto, 'https') ? 'https' : 'http';
        } elseif (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
        ) {
            $scheme = 'https';
        }

        $hostHeader = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        $hostParts = array_map('trim', explode(',', $hostHeader));
        $host = $hostParts[0] ?? '';

        $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? null;
        $portSegment = '';
        if ($port && is_numeric($port)) {
            $port = (int) $port;
            $isStandardPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
            if (!$isStandardPort) {
                $portSegment = ':' . $port;
            }
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        $baseUrl = $scheme . '://' . $host . $portSegment;

        if (!empty($directory) && $directory !== '.') {
            $baseUrl .= $directory[0] === '/' ? $directory : '/' . $directory;
        }

        return rtrim($baseUrl, '/') . '/';
    }

    return '/';
}

define('BASE_URL', determineBaseUrl());

function isAbsoluteAssetPath(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (strpos($path, '//') === 0) {
        return true;
    }

    return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $path);
}

function shouldPreserveRelativePath(string $path): bool
{
    return strpos($path, './') === 0 || strpos($path, '../') === 0;
}

function buildAssetUrl(string $path, string $prefix = ''): string
{
    if ($path === '') {
        return $path;
    }

    if (isAbsoluteAssetPath($path) || shouldPreserveRelativePath($path)) {
        return $path;
    }

    $normalizedPath = ltrim($path, '/');

    if ($prefix !== '') {
        $normalizedPath = rtrim($prefix, '/') . '/' . $normalizedPath;
    }

    $baseUrl = rtrim(BASE_URL, '/');

    if ($baseUrl === '' || $baseUrl === '/') {
        return '/' . ltrim($normalizedPath, '/');
    }

    return $baseUrl . '/' . ltrim($normalizedPath, '/');
}

// Helper para generar URLs públicas
function asset($path)
{
    return buildAssetUrl($path);
}

function img($path)
{
    return buildAssetUrl($path, 'images');
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
