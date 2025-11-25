<?php
require_once __DIR__ . '/../../bootstrap.php';

// CORS estricto: solo orígenes permitidos y con credenciales
$allowedOrigins = [
    'http://cive.ddns.net',
    'https://cive.ddns.net',
    'http://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
    'http://localhost:8085',
    'http://127.0.0.1:8085',
    'https://asistentecive.consulmed.me',
    'https://cive.consulmed.me',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origen no permitido']);
    exit;
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=UTF-8');

use Controllers\GuardarProyeccionController;

$controller = new GuardarProyeccionController($pdo);

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Obtener la fecha desde GET (opcional)
$fecha = $_GET['fecha'] ?? date('Y-m-d');

$pacientesOptometria = $controller->obtenerPacientesPorEstado('OPTOMETRIA', $fecha);

// Solo devolver los form_id en un array simple
$formIds = array_column($pacientesOptometria, 'form_id');

echo json_encode($formIds);
