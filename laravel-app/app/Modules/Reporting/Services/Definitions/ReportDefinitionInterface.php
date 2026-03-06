<?php

namespace App\Modules\Reporting\Services\Definitions;

interface ReportDefinitionInterface
{
    public function template(): string;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function data(array $params): array;
}
