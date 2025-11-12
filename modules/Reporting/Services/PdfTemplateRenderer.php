<?php

namespace Modules\Reporting\Services;

use InvalidArgumentException;
use Modules\Reporting\Services\Definitions\PdfTemplateDefinitionInterface;
use Modules\Reporting\Support\PdfDestinationNormalizer;
use setasign\Fpdi\Tcpdf\Fpdi;
use Stringable;

class PdfTemplateRenderer
{
    private string $templatesPath;

    public function __construct(?string $templatesPath = null)
    {
        $defaultPath = BASE_PATH . '/storage/reporting/templates';
        $this->templatesPath = rtrim($templatesPath ?? $defaultPath, DIRECTORY_SEPARATOR);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function render(PdfTemplateDefinitionInterface $definition, array $data, array $options = []): string
    {
        $pdfPath = $this->resolveTemplate($definition->getTemplatePath());

        if (!is_file($pdfPath)) {
            throw new InvalidArgumentException(sprintf('El archivo de plantilla "%s" no existe.', $pdfPath));
        }

        $values = $definition->transformData($data);
        $overrides = $options['overrides'] ?? [];
        if (is_array($overrides)) {
            $values = array_merge($values, $overrides);
        }

        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $totalPages = $pdf->setSourceFile($pdfPath);
        $fieldMap = $definition->getFieldMap();
        $pages = $this->collectPages($fieldMap, $totalPages, $definition->getTemplatePages());

        $pageIndexMap = [];
        foreach ($pages as $pageNumber) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['orientation'] ?? ($size['width'] > $size['height'] ? 'L' : 'P');
            $dimensions = [$size['width'], $size['height']];

            $pdf->AddPage($orientation, $dimensions);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
            $pageIndexMap[$pageNumber] = $pdf->getPage();
        }

        [$r, $g, $b] = $this->resolveColor($options['text_color'] ?? null, $definition->getTextColor());
        $pdf->SetTextColor($r, $g, $b);

        $fontFamily = (string) ($options['font_family'] ?? $definition->getFontFamily());
        $fontSize = (float) ($options['font_size'] ?? $definition->getFontSize());
        $pdf->SetFont($fontFamily, '', $fontSize);

        $defaultLineHeight = (float) ($options['line_height'] ?? $definition->getLineHeight());

        foreach ($fieldMap as $field => $position) {
            $value = $values[$field] ?? ($position['value'] ?? null);
            if ($value === null) {
                continue;
            }

            $text = $this->stringifyFieldValue($value);
            if ($text === null) {
                continue;
            }

            $pageNumber = isset($position['page']) && is_numeric($position['page']) ? (int) $position['page'] : 1;
            $pageNumber = $pageNumber >= 1 ? $pageNumber : 1;

            $targetPage = $pageIndexMap[$pageNumber] ?? null;
            if ($targetPage === null) {
                continue;
            }

            $x = isset($position['x']) ? (float) $position['x'] : null;
            $y = isset($position['y']) ? (float) $position['y'] : null;

            if ($x === null || $y === null) {
                continue;
            }

            $pdf->setPage($targetPage);
            $pdf->SetXY($x, $y);

            $width = isset($position['width']) ? (float) $position['width'] : 0.0;
            $height = isset($position['height']) ? (float) $position['height'] : 0.0;
            $align = strtoupper((string) ($position['align'] ?? 'L'));
            $cellLineHeight = isset($position['line_height']) && is_numeric($position['line_height'])
                ? (float) $position['line_height']
                : $defaultLineHeight;
            $border = $position['border'] ?? 0;
            $multiline = (bool) ($position['multiline'] ?? false);

            if ($width > 0.0) {
                $effectiveHeight = $height > 0.0 ? $height : $cellLineHeight;

                if ($multiline || strpos($text, "\n") !== false || strpos($text, "\r") !== false) {
                    $pdf->MultiCell(
                        $width,
                        $cellLineHeight,
                        $text,
                        $border,
                        $align,
                        false,
                        1,
                        '',
                        '',
                        true,
                        0,
                        false,
                        true,
                        $effectiveHeight,
                        'T',
                        false
                    );
                } else {
                    $pdf->Cell($width, $effectiveHeight, $text, $border, 0, $align, false, '', 0, false, 'T', 'M');
                }
            } else {
                $pdf->Write(0, $text);
            }
        }

        $filename = (string) ($options['filename'] ?? $definition->getIdentifier() . '.pdf');
        $destination = PdfDestinationNormalizer::normalize($options['destination'] ?? 'S');

        return $pdf->Output($filename, $destination);
    }

    private function stringifyFieldValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $item) {
                $string = $this->stringifyFieldValue($item);

                if ($string === null) {
                    continue;
                }

                $parts[] = $string;
            }

            if ($parts === []) {
                return null;
            }

            return implode(PHP_EOL, $parts);
        }

        if ($value instanceof Stringable) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $fieldMap
     * @return list<int>
     */
    private function collectPages(array $fieldMap, int $totalPages, array $extraPages = []): array
    {
        $pages = [];

        foreach ($extraPages as $page) {
            if (!is_int($page)) {
                continue;
            }

            if ($page < 1) {
                continue;
            }

            if ($totalPages > 0 && $page > $totalPages) {
                continue;
            }

            $pages[] = $page;
        }

        foreach ($fieldMap as $position) {
            $page = isset($position['page']) && is_numeric($position['page'])
                ? (int) $position['page']
                : 1;

            if ($page < 1) {
                continue;
            }

            if ($totalPages > 0 && $page > $totalPages) {
                continue;
            }

            $pages[] = $page;
        }

        $pages[] = 1;
        $pages = array_values(array_unique($pages));
        sort($pages);

        return $pages;
    }

    /**
     * @param mixed $preferred
     * @param array{0:int,1:int,2:int} $fallback
     * @return array{0:int,1:int,2:int}
     */
    private function resolveColor(mixed $preferred, array $fallback): array
    {
        if (is_array($preferred) && count($preferred) >= 3) {
            return [
                (int) ($preferred[0] ?? $fallback[0]),
                (int) ($preferred[1] ?? $fallback[1]),
                (int) ($preferred[2] ?? $fallback[2]),
            ];
        }

        return $fallback;
    }

    private function resolveTemplate(string $templatePath): string
    {
        $path = trim($templatePath);

        if ($path === '') {
            throw new InvalidArgumentException('La ruta de la plantilla PDF no puede estar vacía.');
        }

        if ($path[0] === '/' || str_starts_with($path, BASE_PATH)) {
            return $path;
        }

        return $this->templatesPath . '/' . ltrim($path, '/');
    }
}
