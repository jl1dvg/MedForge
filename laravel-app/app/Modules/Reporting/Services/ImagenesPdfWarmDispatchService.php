<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Jobs\GenerateImagenesPdfCacheJob;

class ImagenesPdfWarmDispatchService
{
    public function dispatchForExamCase(string $formId, string $hcNumber, ?int $examenId = null): int
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);

        if ($formId === '' || $hcNumber === '') {
            return 0;
        }

        $baseParams = [
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ];

        GenerateImagenesPdfCacheJob::dispatch('imagenes-012b', $baseParams, $formId);
        GenerateImagenesPdfCacheJob::dispatch('imagenes-012a-cobertura', array_filter([
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'examen_id' => $examenId,
        ], static fn ($value): bool => $value !== null && $value !== ''), $formId);

        return 2;
    }
}
