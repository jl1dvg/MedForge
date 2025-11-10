<?php
use Modules\Pacientes\Services\PacienteService;
use Throwable;

header('Content-Type: application/json');

try {
    $service = new PacienteService($pdo);

    $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 1;
    $start = isset($_POST['start']) ? (int) $_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int) $_POST['length'] : 10;
    $search = $_POST['search']['value'] ?? '';
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? (int) $_POST['order'][0]['column'] : 0;
    $orderDir = isset($_POST['order'][0]['dir']) ? (string) $_POST['order'][0]['dir'] : 'asc';

    $columnMap = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
    $orderColumn = $columnMap[$orderColumnIndex] ?? 'hc_number';

    $response = $service->obtenerPacientesPaginados(
        $start,
        $length,
        $search,
        $orderColumn,
        strtoupper($orderDir)
    );

    $response['draw'] = $draw;

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'draw' => isset($_POST['draw']) ? (int) $_POST['draw'] : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'No se pudo recuperar la información de pacientes',
    ], JSON_UNESCAPED_UNICODE);
}
