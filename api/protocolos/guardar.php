<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../laravel-app/vendor/autoload.php';

use Helpers\CorsHelper;

header('Content-Type: application/json; charset=UTF-8');

CorsHelper::prepare('EXTENSION_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
]);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Modules\Cirugias\Services\CirugiaService;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        throw new Exception("JSON mal formado");
    }

    $data['audit_source'] = $data['audit_source'] ?? 'cive_extension_protocolos';

    $service = new CirugiaService($pdo);
    $response = $service->guardarDesdeApi($data);
    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error interno en el servidor",
        "error" => $e->getMessage(),
        "line" => $e->getLine(),
        "file" => $e->getFile()
    ]);
}
