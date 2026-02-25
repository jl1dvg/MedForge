<?php

use Modules\Usuarios\Controllers\UsuariosController;
use Modules\Usuarios\Support\UserMediaValidator;

require_once __DIR__ . '/../../core/Permissions.php';
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/../../models/SettingsModel.php';
require_once __DIR__ . '/../../modules/Usuarios/Models/UsuarioModel.php';
require_once __DIR__ . '/../../modules/Usuarios/Models/RolModel.php';
require_once __DIR__ . '/../../modules/Usuarios/Support/PermissionRegistry.php';
require_once __DIR__ . '/../../modules/Usuarios/Support/SensitiveDataProtector.php';
require_once __DIR__ . '/../../modules/Usuarios/Support/UserMediaValidator.php';
require_once __DIR__ . '/../../modules/WhatsApp/Config/WhatsAppSettings.php';
require_once __DIR__ . '/../../modules/WhatsApp/Support/PhoneNumberFormatter.php';
require_once __DIR__ . '/../../modules/Usuarios/Controllers/UsuariosController.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class UsuariosControllerProbe extends UsuariosController
{
    public function exposedCanAccessMedia(int $userId): bool
    {
        return $this->canAccessMedia($userId);
    }
}

$failures = [];
$validator = new UserMediaValidator();

function assert_true(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function create_png(int $width, int $height): string
{
    $resource = imagecreatetruecolor($width, $height);
    $path = tempnam(sys_get_temp_dir(), 'media_test_') . '.png';
    imagepng($resource, $path);
    imagedestroy($resource);

    return $path;
}

$validPath = create_png(300, 120);
$validFile = [
    'tmp_name' => $validPath,
    'size' => filesize($validPath),
    'error' => UPLOAD_ERR_OK,
];
$validResult = $validator->validate($validFile);
assert_true($validResult['error'] === null, 'Valid PNG should pass', $failures);
assert_true($validResult['mime'] === 'image/png', 'Valid PNG mime detected', $failures);

$largePath = create_png(2000, 1000);
$largeFile = [
    'tmp_name' => $largePath,
    'size' => filesize($largePath),
    'error' => UPLOAD_ERR_OK,
];
$largeResult = $validator->validate($largeFile);
assert_true($largeResult['error'] !== null, 'Oversized dimensions should fail', $failures);

$txtPath = tempnam(sys_get_temp_dir(), 'media_test_');
file_put_contents($txtPath, 'not an image');
$txtFile = [
    'tmp_name' => $txtPath,
    'size' => filesize($txtPath),
    'error' => UPLOAD_ERR_OK,
];
$txtResult = $validator->validate($txtFile);
assert_true($txtResult['error'] !== null, 'Invalid mime should fail', $failures);

$destination = $validator->destinationFor(UserMediaValidator::TYPE_SIGNATURE, 'sample.svg', BASE_PATH);
assert_true(str_contains($destination['public'], '/uploads/users/signatures/sample.svg'), 'Signature destination should include signatures directory', $failures);

$combinedDestination = $validator->destinationFor(UserMediaValidator::TYPE_SEAL_SIGNATURE, 'combined.svg', BASE_PATH);
assert_true(str_contains($combinedDestination['public'], '/uploads/users/seal-signatures/combined.svg'), 'Combined destination should include seal-signatures directory', $failures);

$pdo = new PDO('sqlite::memory:');
$controller = new UsuariosControllerProbe($pdo);

$_SESSION['user_id'] = 7;
$_SESSION['permisos'] = [];
assert_true($controller->exposedCanAccessMedia(7) === true, 'Users can access their own media', $failures);

$_SESSION['user_id'] = 2;
$_SESSION['permisos'] = ['admin.usuarios.view'];
assert_true($controller->exposedCanAccessMedia(9) === true, 'Admins with view permission can access media', $failures);

$_SESSION['user_id'] = 3;
$_SESSION['permisos'] = [];
assert_true($controller->exposedCanAccessMedia(9) === false, 'Foreign users without permission cannot access media', $failures);

@unlink($validPath);
@unlink($largePath);
@unlink($txtPath);

if ($failures) {
    fwrite(STDERR, "User media tests failed:\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "User media tests passed\n";
