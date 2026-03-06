<?php
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Pacientes\Services\PacienteService;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión expirada']);
    exit;
}

$hc = trim((string)($_GET['hc_number'] ?? ''));
$formId = trim((string)($_GET['form_id'] ?? ''));

if ($hc === '' || $formId === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
}

try {
    $service = new PacienteService($pdo);
    $data = $service->getDetalleSolicitud($hc, $formId);

    if ($data === []) {
        http_response_code(404);
        echo json_encode(['error' => 'No se encontró la solicitud']);
        exit;
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (\Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo recuperar el detalle de la solicitud']);
}
