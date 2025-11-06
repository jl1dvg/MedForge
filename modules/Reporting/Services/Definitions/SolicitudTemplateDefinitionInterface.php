<?php

namespace Modules\Reporting\Services\Definitions;

interface SolicitudTemplateDefinitionInterface
{
    public function getIdentifier(): string;

    /**
     * @return list<string>
     */
    public function getPages(): array;

    /**
     * @return array<string, string>
     */
    public function getOrientations(): array;

    public function getCss(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getMpdfOptions(): array;

    public function getDefaultOrientation(): string;

    public function buildFilename(string $formId, string $hcNumber): string;

    public function getReportSlug(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getReportOptions(): array;

    /**
     * @return list<string>
     */
    public function getAppendViews(): array;

    /**
     * Determina si la definición aplica para los datos proporcionados.
     *
     * @param array<string, mixed> $data
     */
    public function matches(array $data): bool;

    public function isFallback(): bool;
}

