<?php

namespace Modules\Reporting\Controllers;

use Modules\Reporting\Services\ReportService;
use Modules\Reporting\Support\RenderContext;
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

        if ($template !== null) {
            header('Content-Type: application/json');
            echo json_encode([
                'slug' => $slug,
                'template' => $template,
                'type' => 'html',
            ], self::JSON_FLAGS);
            return;
        }

        $definition = $this->service->getPdfTemplate($slug);

        if ($definition !== null) {
            header('Content-Type: application/json');
            echo json_encode([
                'slug' => $slug,
                'template' => $definition->getTemplatePath(),
                'type' => 'pdf-template',
                'fields' => array_keys($definition->getFieldMap()),
            ], self::JSON_FLAGS);
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Reporte no encontrado',
            'slug' => $slug,
        ], self::JSON_FLAGS);
    }

    public function render(string $slug, array $data = [], bool $asFragment = false): string
    {
        if (!$asFragment) {
            return $this->service->render($slug, $data);
        }

        return RenderContext::withFragment(fn () => $this->service->render($slug, $data));
    }

    public function renderIfExists(string $slug, array $data = [], bool $asFragment = false): ?string
    {
        if (!$asFragment) {
            return $this->service->renderIfExists($slug, $data);
        }

        return RenderContext::withFragment(fn () => $this->service->renderIfExists($slug, $data));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function renderDocument(string $slug, array $data = [], array $options = []): array
    {
        return $this->service->renderDocument($slug, $data, $options);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function renderPdf(string $slug, array $data = [], array $options = []): string
    {
        return $this->service->renderPdf($slug, $data, $options);
    }
}
