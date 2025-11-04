<?php

namespace Modules\Reporting\Controllers;

use Modules\Reporting\Services\ReportService;
use PDO;

class ReportController
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private PDO $pdo;
    private ReportService $service;

    public function __construct(PDO $pdo, ReportService $service)
    {
        $this->pdo = $pdo;
        $this->service = $service;
    }

    public function index(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'reports' => $this->service->getAvailableReports(),
        ], self::JSON_FLAGS);
    }

    public function show(string $slug): void
    {
        $template = $this->service->resolveTemplate($slug);

        if ($template === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Reporte no encontrado',
                'slug' => $slug,
            ], self::JSON_FLAGS);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'slug' => $slug,
            'template' => $template,
        ], self::JSON_FLAGS);
    }

    public function render(string $slug, array $data = []): string
    {
        return $this->service->render($slug, $data);
    }

    public function renderIfExists(string $slug, array $data = []): ?string
    {
        return $this->service->renderIfExists($slug, $data);
    }
}
