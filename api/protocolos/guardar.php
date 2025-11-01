<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204); // No Content
    exit;
}

require_once __DIR__ . '/../../bootstrap.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Modules\Cirugias\Services\CirugiaService;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        throw new Exception("JSON mal formado");
    }

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