<?php

namespace Modules\Reporting\Services\Definitions;

use InvalidArgumentException;

/**
 * Implementación simple basada en arreglos para describir plantillas PDF.
 */
class ArrayPdfTemplateDefinition implements PdfTemplateDefinitionInterface
{
    /**
     * @param array<string, array<string, mixed>> $fieldMap
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $identifier,
        private readonly string $templatePath,
        private readonly array $fieldMap,
        private readonly array $options = []
    ) {
        if ($identifier === '') {
            throw new InvalidArgumentException('El identificador de la plantilla no puede estar vacío.');
        }

        if ($templatePath === '') {
            throw new InvalidArgumentException('La ruta del PDF base no puede estar vacía.');
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getFieldMap(): array
    {
        return $this->fieldMap;
    }

    public function transformData(array $data): array
    {
        $defaults = $this->options['defaults'] ?? [];

        if (!is_array($defaults)) {
            return $data;
        }

        return $defaults + $data;
    }

    public function getFontFamily(): string
    {
        return (string) ($this->options['font_family'] ?? 'helvetica');
    }

    public function getFontSize(): float
    {
        $size = $this->options['font_size'] ?? 10;

        return is_numeric($size) ? (float) $size : 10.0;
    }

    public function getLineHeight(): float
    {
        $height = $this->options['line_height'] ?? 4.5;

        return is_numeric($height) ? (float) $height : 4.5;
    }

    public function getTextColor(): array
    {
        $color = $this->options['text_color'] ?? [0, 0, 0];

        if (!is_array($color) || count($color) < 3) {
            return [0, 0, 0];
        }

        return [
            (int) ($color[0] ?? 0),
            (int) ($color[1] ?? 0),
            (int) ($color[2] ?? 0),
        ];
    }
}
