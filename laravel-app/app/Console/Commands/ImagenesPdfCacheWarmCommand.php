<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Reporting\Services\ImagenesPdfWarmDispatchService;
use Illuminate\Console\Command;

class ImagenesPdfCacheWarmCommand extends Command
{
    protected $signature = 'imagenes:pdf-cache-warm
                            {--form-id= : Form ID del examen}
                            {--hc-number= : Historia clínica del paciente}
                            {--pair=* : Par form_id:hc_number. Puede repetirse}';

    protected $description = 'Encola generación anticipada de PDFs de imágenes usando Redis';

    public function __construct(
        private readonly ImagenesPdfWarmDispatchService $warmDispatchService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cases = $this->casesFromOptions();
        if ($cases === []) {
            $this->error('Debe enviar --form-id y --hc-number, o uno o más --pair=form_id:hc_number.');
            return self::FAILURE;
        }

        $queued = 0;
        foreach ($cases as $case) {
            $queued += $this->warmDispatchService->dispatchForExamCase($case['form_id'], $case['hc_number']);
        }

        $this->info(sprintf('Casos evaluados: %d. Jobs PDF encolados: %d.', count($cases), $queued));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{form_id:string,hc_number:string}>
     */
    private function casesFromOptions(): array
    {
        $cases = [];
        $formId = trim((string) ($this->option('form-id') ?? ''));
        $hcNumber = trim((string) ($this->option('hc-number') ?? ''));

        if ($formId !== '' && $hcNumber !== '') {
            $cases[] = [
                'form_id' => $formId,
                'hc_number' => $hcNumber,
            ];
        }

        $pairs = $this->option('pair');
        if (!is_array($pairs)) {
            return $this->uniqueCases($cases);
        }

        foreach ($pairs as $pair) {
            [$pairFormId, $pairHcNumber] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $pairFormId = trim($pairFormId);
            $pairHcNumber = trim($pairHcNumber);

            if ($pairFormId === '' || $pairHcNumber === '') {
                continue;
            }

            $cases[] = [
                'form_id' => $pairFormId,
                'hc_number' => $pairHcNumber,
            ];
        }

        return $this->uniqueCases($cases);
    }

    /**
     * @param array<int, array{form_id:string,hc_number:string}> $cases
     * @return array<int, array{form_id:string,hc_number:string}>
     */
    private function uniqueCases(array $cases): array
    {
        $seen = [];
        $unique = [];

        foreach ($cases as $case) {
            $key = $case['form_id'] . '|' . $case['hc_number'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $case;
        }

        return $unique;
    }
}
