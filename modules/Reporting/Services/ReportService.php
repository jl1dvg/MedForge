<?php

namespace Modules\Reporting\Services;

use InvalidArgumentException;
use Modules\Reporting\Services\Definitions\PdfTemplateDefinitionInterface;
use Modules\Reporting\Services\Definitions\PdfTemplateRegistry;
use Modules\Reporting\Support\PdfDestinationNormalizer;

/**
 * @psalm-type RenderedDocument = array{
 *     type: 'html'|'template',
 *     content: string,
 *     filename: string,
 *     options: array<string, mixed>
 * }
 */

class ReportService
{
    private string $templatesPath;

    /**
     * Cached map of slug => absolute template path.
     *
     * @var array<string, string>
     */
    private array $templateMap = [];

    private PdfRenderer $pdfRenderer;

    private PdfTemplateRenderer $pdfTemplateRenderer;

    private PdfTemplateRegistry $pdfTemplateRegistry;

    public function __construct(
        ?string $templatesPath = null,
        ?PdfRenderer $pdfRenderer = null,
        ?PdfTemplateRegistry $pdfTemplateRegistry = null,
        ?PdfTemplateRenderer $pdfTemplateRenderer = null
    )
    {
        $defaultPath = dirname(__DIR__) . '/Templates/reports';
        $basePath = $templatesPath ?? $defaultPath;
        $this->templatesPath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->pdfRenderer = $pdfRenderer ?? new PdfRenderer();
        $this->pdfTemplateRegistry = $pdfTemplateRegistry ?? PdfTemplateRegistry::fromConfig();
        $this->pdfTemplateRenderer = $pdfTemplateRenderer ?? new PdfTemplateRenderer();
    }

    /**
     * Returns metadata for all templates available in the module.
     *
     * @return array<int, array<string, string>>
     */
    public function getAvailableReports(): array
    {
        $templates = $this->loadTemplates();
        ksort($templates);

        $reports = [];
        foreach ($templates as $slug => $path) {
            $reports[] = [
                'slug' => $slug,
                'filename' => basename($path),
                'path' => $path,
                'type' => 'html',
            ];
        }

        foreach ($this->pdfTemplateRegistry->all() as $definition) {
            $reports[] = [
                'slug' => $definition->getIdentifier(),
                'filename' => basename($definition->getTemplatePath()),
                'path' => $definition->getTemplatePath(),
                'type' => 'pdf-template',
            ];
        }

        return $reports;
    }

    public function resolveTemplate(string $identifier): ?string
    {
        $templates = $this->loadTemplates();
        $slug = $this->normalizeIdentifier($identifier);

        return $templates[$slug] ?? null;
    }

    public function render(string $identifier, array $data = []): string
    {
        $template = $this->resolveTemplate($identifier);

        if ($template === null) {
            throw new InvalidArgumentException(sprintf('Template "%s" no encontrado.', $identifier));
        }

        return $this->renderTemplate($template, $data);
    }

    public function renderIfExists(string $identifier, array $data = []): ?string
    {
        $template = $this->resolveTemplate($identifier);

        if ($template === null) {
            return null;
        }

        return $this->renderTemplate($template, $data);
    }

    public function hasPdfTemplate(string $identifier): bool
    {
        $slug = $this->normalizeIdentifier($identifier);

        return $this->pdfTemplateRegistry->get($slug) !== null;
    }

    public function getPdfTemplate(string $identifier): ?PdfTemplateDefinitionInterface
    {
        $slug = $this->normalizeIdentifier($identifier);

        return $this->pdfTemplateRegistry->get($slug);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return RenderedDocument
     */
    public function renderDocument(string $identifier, array $data = [], array $options = []): array
    {
        $slug = $this->normalizeIdentifier($identifier);
        $definition = $this->pdfTemplateRegistry->get($slug);

        $destination = PdfDestinationNormalizer::normalize($options['destination'] ?? 'S');
        $filename = $options['filename'] ?? ($slug !== '' ? $slug . '.pdf' : 'documento.pdf');

        if ($definition !== null) {
            $content = $this->pdfTemplateRenderer->render($definition, $data, [
                'filename' => $filename,
                'destination' => $destination,
                'font_family' => $options['font_family'] ?? null,
                'font_size' => $options['font_size'] ?? null,
                'line_height' => $options['line_height'] ?? null,
                'text_color' => $options['text_color'] ?? null,
                'overrides' => $options['overrides'] ?? null,
            ]);

            return [
                'type' => 'template',
                'content' => $content,
                'filename' => $filename,
                'options' => [
                    'destination' => $destination,
                ],
            ];
        }

        $html = $this->render($identifier, $data);
        $css = $options['css'] ?? null;
        $mpdfOptions = isset($options['mpdf']) && is_array($options['mpdf']) ? $options['mpdf'] : [];

        $content = $this->pdfRenderer->renderHtml($html, [
            'css' => $css,
            'mpdf' => $mpdfOptions,
            'filename' => $filename,
            'destination' => $destination,
        ]);

        return [
            'type' => 'html',
            'content' => $content,
            'filename' => $filename,
            'options' => [
                'destination' => $destination,
                'css' => $css,
                'mpdf' => $mpdfOptions,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function renderPdf(string $identifier, array $data = [], array $options = []): string
    {
        $options['destination'] = PdfDestinationNormalizer::normalize($options['destination'] ?? 'S');

        $document = $this->renderDocument($identifier, $data, $options);

        return $document['content'];
    }

    /**
     * @return array<string, string>
     */
    private function loadTemplates(): array
    {
        if ($this->templateMap !== []) {
            return $this->templateMap;
        }

        if (!is_dir($this->templatesPath)) {
            return $this->templateMap;
        }

        $files = glob($this->templatesPath . '/*.php') ?: [];

        foreach ($files as $file) {
            $slug = basename($file, '.php');
            $this->templateMap[$slug] = $file;
        }

        return $this->templateMap;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $identifier = str_replace('\\', '/', $identifier);
        $identifier = basename($identifier);

        if (substr($identifier, -4) === '.php') {
            $identifier = substr($identifier, 0, -4);
        }

        return $identifier;
    }

    private function renderTemplate(string $template, array $data): string
    {
        if (!is_file($template)) {
            throw new InvalidArgumentException(sprintf('La plantilla %s no existe.', $template));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $template;

        return (string) ob_get_clean();
    }
}
