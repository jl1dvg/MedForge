<?php
require_once __DIR__ . '/../../bootstrap.php';

use Services\BillingProcedimientosKpiService;

header('Content-Type: application/json; charset=utf-8');

if (!class_exists(BillingProcedimientosKpiService::class)) {
    require_once __DIR__ . '/../../controllers/Services/BillingProcedimientosKpiService.php';
}

$filters = [
    'company_id' => $_GET['company_id'] ?? null,
    'year' => $_GET['year'] ?? null,
    'sede' => $_GET['sede'] ?? null,
    'tipo_cliente' => $_GET['tipo_cliente'] ?? ($_GET['tipoCliente'] ?? null),
    'categoria' => $_GET['categoria'] ?? null,
];

try {
    $service = new BillingProcedimientosKpiService($pdo);
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'summary')));
    $limit = (int) ($_GET['limit'] ?? 500);
    if ($limit <= 0) {
        $limit = 500;
    }
    $limit = min($limit, 5000);

    if ($mode === 'detail') {
        $data = $service->detail($filters, $limit);

        if (strtolower((string) ($_GET['export'] ?? '')) === 'csv') {
            $filename = sprintf('kpi_procedimientos_detalle_%s.csv', date('Ymd_His'));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Fecha', 'Form ID', 'HC', 'Paciente', 'Afiliacion', 'Tipo cliente', 'Categoria', 'Codigo', 'Detalle', 'Valor']);
            foreach ($data['rows'] ?? [] as $row) {
                fputcsv($output, [
                    $row['fecha'] ?? '',
                    $row['form_id'] ?? '',
                    $row['hc_number'] ?? '',
                    $row['paciente'] ?? '',
                    $row['afiliacion'] ?? '',
                    $row['tipo_cliente'] ?? '',
                    $row['categoria'] ?? '',
                    $row['codigo'] ?? '',
                    $row['detalle'] ?? '',
                    $row['valor'] ?? 0,
                ]);
            }
            fclose($output);
            exit;
        }
    } else {
        $data = $service->build($filters);
    }

    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'data' => $data,
        'filters' => $filters,
    ]);
} catch (Throwable $e) {
    $debugEnabled = isset($_GET['debug']) && $_GET['debug'] === '1';
    $errorPayload = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];

    error_log('[kpis_procedimientos] Error: ' . json_encode([
        'error' => $errorPayload,
        'filters' => $filters,
    ], JSON_UNESCAPED_UNICODE));

    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'No se pudo calcular los KPIs de procedimientos.',
    ];

    if ($debugEnabled) {
        $response['debug'] = $errorPayload;
    }

    echo json_encode($response);
}
