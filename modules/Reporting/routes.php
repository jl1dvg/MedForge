<?php

require_once __DIR__ . '/Support/LegacyLoader.php';

use Core\Router;
use Controllers\PdfController;
use Modules\Reporting\Controllers\ReportController;
use Modules\Reporting\Services\AsyncReportQueueService;
use Modules\Reporting\Services\ReportService;

reporting_bootstrap_legacy();

return static function (Router $router, \PDO $pdo): void {
    $router->get('/reports', static function (\PDO $pdo): void {
        $controller = new ReportController($pdo, new ReportService());
        $controller->index();
    });

    $router->get('/reports/{slug}', static function (\PDO $pdo, string $slug): void {
        $controller = new ReportController($pdo, new ReportService());
        $controller->show($slug);
    });

    $router->get('/reports/protocolo/pdf', static function (\PDO $pdo): void {
        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;
        $mode = $_GET['modo'] ?? 'completo';

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $controller = new PdfController($pdo);
        $controller->generarProtocolo($formId, $hcNumber, false, $mode);
    });

    $router->get('/reports/cobertura/pdf', static function (\PDO $pdo): void {
        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $controller = new PdfController($pdo);
        $controller->generateCobertura($formId, $hcNumber);
    });

    $router->get('/reports/cobertura/pdf-template', static function (\PDO $pdo): void {
        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $controller = new PdfController($pdo);
        $controller->generateCobertura($formId, $hcNumber, 'template');
    });

    $router->get('/reports/cobertura/pdf-html', static function (\PDO $pdo): void {
        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $controller = new PdfController($pdo);
        $controller->generateCobertura($formId, $hcNumber, 'appendix');
    });


    $router->get('/reports/cobertura/pdf-queue', static function (\PDO $pdo): void {
        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;
        $variant = $_GET['variant'] ?? 'template';

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $service = new AsyncReportQueueService($pdo);
        $jobId = $service->enqueueCobertura((string) $formId, (string) $hcNumber, (string) $variant);

        http_response_code(202);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'job_id' => $jobId,
            'status_url' => '/reports/cobertura/pdf-queue/status?id=' . $jobId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });

    $router->get('/reports/cobertura/pdf-queue/status', static function (\PDO $pdo): void {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Parámetro id inválido.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $service = new AsyncReportQueueService($pdo);
        $job = $service->getJob($id);

        if ($job === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Job no encontrado.',
                'id' => $id,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'job' => $job,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });

    $router->get('/reports/consulta/pdf', static function (\PDO $pdo): void {
        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $controller = new PdfController($pdo);
        $controller->generateConsultaIess($formId, $hcNumber);
    });
};
