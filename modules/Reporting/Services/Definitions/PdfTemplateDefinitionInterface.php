<?php

namespace Modules\Reporting\Services\Definitions;

/**
 * Describe una plantilla PDF fija sobre la cual se sobreimprimen datos.
 */
interface PdfTemplateDefinitionInterface
{
    /**
     * Identificador único que se utilizará como slug del reporte.
     */
    public function getIdentifier(): string;

    /**
     * Ruta del archivo PDF base (absoluta o relativa al directorio de plantillas).
     */
    public function getTemplatePath(): string;

    /**
     * Retorna el mapeo de campos a coordenadas.
     *
     * Cada elemento debe incluir al menos las llaves `x` y `y`. Opcionalmente puede
     * definirse `width`, `height`, `align`, `line_height`, `page` y `multiline`.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFieldMap(): array;

    /**
     * Lista de páginas que deben importarse del PDF base, incluso si no poseen campos.
     *
     * @return list<int>
     */
    public function getTemplatePages(): array;

    /**
     * Permite transformar o enriquecer los datos previos a la escritura.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function transformData(array $data): array;

    /**
     * Fuente principal utilizada al escribir el PDF.
     */
    public function getFontFamily(): string;

    /**
     * Tamaño de fuente en puntos.
     */
    public function getFontSize(): float;

    /**
     * Alto de línea por defecto utilizado en celdas multilínea.
     */
    public function getLineHeight(): float;

    /**
     * Color de texto RGB como arreglo de tres enteros.
     *
     * @return array{0:int,1:int,2:int}
     */
    public function getTextColor(): array;
}
