<?php

namespace Modules\Reporting\Services\Definitions;

use Modules\Reporting\Support\SolicitudDataFormatter;

class ArraySolicitudTemplateDefinition implements SolicitudTemplateDefinitionInterface
{
    /** @var list<string> */
    private array $pages;

    /** @var array<string, string> */
    private array $orientations;

    /** @var array<string, mixed> */
    private array $mpdfOptions;

    /** @var list<string> */
    private array $matchers;

    /** @var list<string> */
    private array $appendViews;

    private ?string $css;

    private string $defaultOrientation;

    private string $filenamePattern;

    private bool $fallback;

    private ?string $reportSlug = null;

    /** @var array<string, mixed> */
    private array $reportOptions = [];

    /**
     * @param list<string> $pages
     * @param array<string, mixed> $options
     * @param list<string> $matchers
     */
    public function __construct(
        private string $identifier,
        array $pages,
        array $options = [],
        array $matchers = [],
        bool $fallback = false
    ) {
        $this->pages = array_values(array_map('strval', $pages));
        $this->orientations = [];

        foreach (($options['orientations'] ?? []) as $page => $orientation) {
            if (!is_string($page) || $page === '') {
                continue;
            }

            $orientation = strtoupper((string) $orientation);
            if ($orientation !== 'P' && $orientation !== 'L') {
                continue;
            }

            $this->orientations[$page] = $orientation;
        }

        $this->css = isset($options['css']) && is_string($options['css']) && $options['css'] !== ''
            ? $options['css']
            : null;

        $this->mpdfOptions = isset($options['mpdf']) && is_array($options['mpdf'])
            ? $options['mpdf']
            : [];

        $this->defaultOrientation = strtoupper((string) ($options['orientation'] ?? 'P'));
        if ($this->defaultOrientation !== 'P' && $this->defaultOrientation !== 'L') {
            $this->defaultOrientation = 'P';
        }

        $this->filenamePattern = is_string($options['filename_pattern'] ?? null)
            ? (string) $options['filename_pattern']
            : '%1$s_%2$s_%3$s.pdf';

        $this->matchers = array_values(array_map('strval', $matchers));
        $this->fallback = $fallback;

        $appendViews = $options['append_views'] ?? [];
        $this->appendViews = [];
        if (is_array($appendViews)) {
            foreach ($appendViews as $view) {
                if (!is_string($view)) {
                    continue;
                }

                $view = trim($view);
                if ($view === '') {
                    continue;
                }

                $this->appendViews[] = $view;
            }
        }

        $report = $options['report'] ?? null;
        if (is_string($report) && $report !== '') {
            $this->reportSlug = $report;
        } elseif (is_array($report) && isset($report['slug'])) {
            $slug = is_string($report['slug']) ? trim($report['slug']) : '';
            if ($slug !== '') {
                $this->reportSlug = $slug;
                $reportOptions = $report['options'] ?? [];
                if (is_array($reportOptions)) {
                    $this->reportOptions = $reportOptions;
                }
            }
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function getOrientations(): array
    {
        return $this->orientations;
    }

    public function getCss(): ?string
    {
        return $this->css;
    }

    public function getMpdfOptions(): array
    {
        return $this->mpdfOptions;
    }

    public function getDefaultOrientation(): string
    {
        return $this->defaultOrientation;
    }

    public function buildFilename(string $formId, string $hcNumber): string
    {
        return sprintf($this->filenamePattern, $this->identifier, $formId, $hcNumber);
    }

    public function getReportSlug(): ?string
    {
        return $this->reportSlug;
    }

    public function getReportOptions(): array
    {
        return $this->reportOptions;
    }

    public function getAppendViews(): array
    {
        return $this->appendViews;
    }

    public function matches(array $data): bool
    {
        if ($this->matchers === []) {
            return false;
        }

        $slug = SolicitudDataFormatter::slugify($data['aseguradora']['slug'] ?? $data['aseguradoraSlug'] ?? null);
        $nombre = SolicitudDataFormatter::slugify($data['aseguradora']['nombre'] ?? $data['aseguradoraNombre'] ?? null);
        $afiliacion = SolicitudDataFormatter::slugify($data['paciente']['afiliacion'] ?? null);

        foreach ($this->matchers as $matcher) {
            $matcher = trim($matcher);
            if ($matcher === '') {
                continue;
            }

            if ($matcher === '*') {
                return true;
            }

            if (strncmp($matcher, 'regex:', 6) === 0) {
                $pattern = substr($matcher, 6);
                if ($pattern === '') {
                    continue;
                }

                if ((is_string($slug) && @preg_match($pattern, $slug) === 1)
                    || (is_string($nombre) && @preg_match($pattern, $nombre) === 1)
                    || (is_string($afiliacion) && @preg_match($pattern, $afiliacion) === 1)) {
                    return true;
                }

                continue;
            }

            $normalized = SolicitudDataFormatter::slugify($matcher);

            if ($normalized !== null
                && ($normalized === $slug || $normalized === $nombre || $normalized === $afiliacion)) {
                return true;
            }
        }

        return false;
    }

    public function isFallback(): bool
    {
        return $this->fallback;
    }
}

