<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Reporting\Services\ImagenesPdfCacheService;
use App\Modules\Reporting\Services\ReportPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateImagenesPdfCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly string $type,
        public readonly array $params,
        public readonly ?string $formId = null,
    ) {
        $this->onQueue((string) config('queue.imagenes_pdf_queue', env('IMAGENES_PDF_QUEUE', 'pdfs')));
    }

    public function handle(ImagenesPdfCacheService $cache, ReportPdfService $pdfService): void
    {
        $type = trim($this->type);
        if ($type === '') {
            return;
        }

        try {
            $cache->remember($type, $this->params, $this->formId, function () use ($type, $pdfService): ?array {
                return $this->generate($type, $pdfService);
            });
        } catch (Throwable $e) {
            Log::warning('imagenes.pdf_cache_warm.failed', [
                'type' => $this->type,
                'form_id' => $this->formId,
                'params' => $this->safeParamsForLog(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{content:string,filename:string}|null
     */
    private function generate(string $type, ReportPdfService $pdfService): ?array
    {
        $formId = trim((string) ($this->params['form_id'] ?? ''));
        $hcNumber = trim((string) ($this->params['hc_number'] ?? ''));

        if ($formId === '' || $hcNumber === '') {
            return null;
        }

        return match ($type) {
            'imagenes-012b' => $pdfService->generateInforme012BPdf($formId, $hcNumber),
            'imagenes-012b-package-single' => $pdfService->generateInforme012BPackagePdf([[
                'form_id' => $formId,
                'hc_number' => $hcNumber,
            ]]),
            'imagenes-012a-cobertura' => $pdfService->generateCobertura012APdf(
                $formId,
                $hcNumber,
                $this->numericParam('examen_id'),
                $this->arrayParam('selected_items')
            ),
            default => null,
        };
    }

    private function numericParam(string $key): ?int
    {
        $value = $this->params[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arrayParam(string $key): array
    {
        $value = $this->params[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeParamsForLog(): array
    {
        return array_intersect_key($this->params, array_flip([
            'form_id',
            'hc_number',
            'examen_id',
        ]));
    }
}
