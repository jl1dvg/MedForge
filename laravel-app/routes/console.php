<?php

use App\Modules\Examenes\Services\ImagenesNasIndexService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('imagenes:nas-index
    {--days=7 : Dias hacia atras para buscar candidatos}
    {--limit= : Maximo de form_id a escanear; vacio o 0 = sin limite}
    {--stale-hours=6 : Solo reescanea filas mas viejas que este umbral}
    {--form-id= : Escanea un form_id puntual}
    {--force : Ignora antiguedad del cache}', function (): int {
    /** @var ImagenesNasIndexService $service */
    $service = app(ImagenesNasIndexService::class);

    $this->components->info('Iniciando indexacion NAS de imagenes...');

    $limitOption = $this->option('limit');

    $result = $service->scan([
        'days' => (int) $this->option('days'),
        'limit' => $limitOption === null || $limitOption === '' ? null : (int) $limitOption,
        'stale_hours' => (int) $this->option('stale-hours'),
        'form_id' => $this->option('form-id'),
        'force' => (bool) $this->option('force'),
    ], function (string $event, array $payload): void {
        if ($event !== 'row') {
            return;
        }

        $status = strtoupper((string) ($payload['scan_status'] ?? 'N/A'));
        $formId = (string) ($payload['form_id'] ?? '');
        $hcNumber = (string) ($payload['hc_number'] ?? '');
        $filesCount = (int) ($payload['files_count'] ?? 0);
        $durationMs = (int) ($payload['scan_duration_ms'] ?? 0);

        $this->line(sprintf(
            '[%s] form_id=%s hc=%s files=%d scan=%dms',
            $status,
            $formId,
            $hcNumber,
            $filesCount,
            $durationMs
        ));
    });

    if (!(bool) ($result['success'] ?? false)) {
        $this->components->error((string) ($result['error'] ?? 'No se pudo ejecutar el indexador.'));
        return 1;
    }

    $this->newLine();
    $this->table(
        ['Candidates', 'Processed', 'With files', 'Empty', 'Missing dir', 'Errors', 'Duration ms', 'Avg scan ms'],
        [[
            (int) ($result['candidates'] ?? 0),
            (int) ($result['processed'] ?? 0),
            (int) ($result['with_files'] ?? 0),
            (int) ($result['empty'] ?? 0),
            (int) ($result['missing_dir'] ?? 0),
            (int) ($result['errors'] ?? 0),
            (int) ($result['duration_ms'] ?? 0),
            (int) ($result['avg_scan_ms'] ?? 0),
        ]]
    );

    $this->components->info('Indexacion NAS finalizada.');

    return 0;
})->purpose('Indexa archivos de imagenes del NAS en una tabla local');
