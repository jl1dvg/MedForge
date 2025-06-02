<?php
require_once '../../bootstrap.php';
use Controllers\PacienteController;

header('Content-Type: application/json');

try {
    $controller = new PacienteController($pdo);

    // DataTables parameters
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
    $orderDir = $_POST['order'][0]['dir'] ?? 'asc';

    $columnMap = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
    $orderColumn = $columnMap[$orderColumnIndex] ?? 'hc_number';

    $response = $controller->obtenerPacientesPaginados($start, $length, $search, $orderColumn, strtoupper($orderDir));
    $response['draw'] = intval($draw);

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}