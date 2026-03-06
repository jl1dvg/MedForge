<?php

require_once __DIR__ . '/Support/LegacyLoader.php';

use Core\Router;
use Modules\Reporting\Controllers\ReportController;
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
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $target = '/v2/reports/protocolo/pdf' . ($query !== '' ? ('?' . $query) : '');
        header('Location: ' . $target, true, 302);
    });

    $router->get('/reports/cobertura/pdf', static function (\PDO $pdo): void {
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $target = '/v2/reports/cobertura/pdf' . ($query !== '' ? ('?' . $query) : '');
        header('Location: ' . $target, true, 302);
    });

    $router->get('/reports/cobertura/pdf-template', static function (\PDO $pdo): void {
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $separator = $query !== '' ? '&' : '?';
        $target = '/v2/reports/cobertura/pdf' . ($query !== '' ? ('?' . $query) : '') . $separator . 'variant=template';
        header('Location: ' . $target, true, 302);
    });

    $router->get('/reports/cobertura/pdf-html', static function (\PDO $pdo): void {
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $separator = $query !== '' ? '&' : '?';
        $target = '/v2/reports/cobertura/pdf' . ($query !== '' ? ('?' . $query) : '') . $separator . 'variant=appendix';
        header('Location: ' . $target, true, 302);
    });


    $router->get('/reports/cobertura/pdf-queue', static function (\PDO $pdo): void {
        $formId = trim((string) ($_GET['form_id'] ?? ''));
        $hcNumber = trim((string) ($_GET['hc_number'] ?? ''));
        $variant = trim((string) ($_GET['variant'] ?? 'template'));
        if ($variant === '') {
            $variant = 'template';
        }

        if ($formId === '' || $hcNumber === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Faltan parámetros obligatorios.',
                'required' => ['form_id', 'hc_number'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $target = '/v2/reports/cobertura/pdf?' . http_build_query([
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'variant' => $variant,
        ], '', '&', PHP_QUERY_RFC3986);

        $jobId = 'v2-' . bin2hex(random_bytes(8));
        $statusUrl = '/reports/cobertura/pdf-queue/status?' . http_build_query([
            'id' => $jobId,
            'target' => $target,
        ], '', '&', PHP_QUERY_RFC3986);

        http_response_code(202);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'strategy' => 'strangler-v2',
            'job_id' => $jobId,
            'status_url' => $statusUrl,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });

    $router->get('/reports/cobertura/pdf-queue/status', static function (\PDO $pdo): void {
        $id = trim((string) ($_GET['id'] ?? ''));
        $target = trim((string) ($_GET['target'] ?? ''));

        if ($id === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Parámetro id inválido.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if ($target === '' || !str_starts_with($target, '/v2/reports/cobertura/pdf')) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Parámetro target inválido.',
                'required' => ['target'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'job' => [
                'id' => $id,
                'status' => 'completed',
                'strategy' => 'strangler-v2',
                'progress' => 100,
                'download_url' => $target,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });

    $router->get('/reports/consulta/pdf', static function (\PDO $pdo): void {
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $target = '/v2/reports/consulta/pdf' . ($query !== '' ? ('?' . $query) : '');
        header('Location: ' . $target, true, 302);
    });

    $router->get('/reports/cirugias/descanso/pdf', static function (\PDO $pdo): void {
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $target = '/v2/reports/cirugias/descanso/pdf' . ($query !== '' ? ('?' . $query) : '');
        header('Location: ' . $target, true, 302);
    });
};
