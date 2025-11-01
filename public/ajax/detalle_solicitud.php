<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../bootstrap.php';

use Modules\Pacientes\Services\PacienteService;

header('Content-Type: application/json');

$hc = $_GET['hc_number'] ?? null;
$form_id = $_GET['form_id'] ?? null;

if (!$hc || !$form_id) {
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
}

$controller = new PacienteService($pdo);
$data = $controller->getDetalleSolicitud($hc, $form_id);

echo json_encode($data);