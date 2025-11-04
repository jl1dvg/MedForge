<?php

namespace Modules\Reporting\Services;

use InvalidArgumentException;

class ReportService
{
    private string $templatesPath;

    /**
     * Cached map of slug => absolute template path.
     *
     * @var array<string, string>
     */
    private array $templateMap = [];

    public function __construct(?string $templatesPath = null)
    {
        $basePath = $templatesPath ?? dirname(__DIR__) . '/Templates';
        $this->templatesPath = rtrim($basePath, DIRECTORY_SEPARATOR);
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
