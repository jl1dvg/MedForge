<?php

use App\Modules\Examenes\Services\ImagenesNasIndexService;
use App\Modules\Examenes\Services\NasImagenesService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('imagenes:nas-index
    {--days=7 : Dias hacia atras para buscar candidatos}
    {--from-date= : Fecha inicial YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --days}
    {--to-date= : Fecha final YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --days}
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
        'from_date' => $this->option('from-date'),
        'to_date' => $this->option('to-date'),
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

Artisan::command('imagenes:nas-diagnose
    {hc_number? : HC number de prueba}
    {form_id? : Form ID de prueba}
    {--file= : Nombre exacto del archivo a abrir; si se omite usa el primero de la lista}', function (): int {
    /** @var NasImagenesService $service */
    $service = app(NasImagenesService::class);
    $diagnostics = $service->diagnostics();

    $cacheDir = trim((string) (env('NAS_IMAGES_CACHE_DIR') ?? ''));
    if ($cacheDir === '') {
        $cacheDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'medforge_nas_cache';
    }

    $this->components->info('Diagnóstico NAS');
    $this->table(
        ['Campo', 'Valor'],
        [
            ['Disponible', !empty($diagnostics['available']) ? 'SI' : 'NO'],
            ['Transporte activo', (string) ($diagnostics['transport'] ?? 'n/a')],
            ['Mount path', (string) ($diagnostics['mount_path'] ?? '—')],
            ['Mount listo', !empty($diagnostics['mount_ready']) ? 'SI' : 'NO'],
            ['Mount legible', !empty($diagnostics['mount_readable']) ? 'SI' : 'NO'],
            ['SSH host', (string) ($diagnostics['host'] ?? '—')],
            ['SSH port', (string) ($diagnostics['port'] ?? '—')],
            ['SSH user', (string) ($diagnostics['username'] ?? '—')],
            ['Base path', (string) ($diagnostics['base_path'] ?? '—')],
            ['ext-ssh2', !empty($diagnostics['ssh2_available']) ? 'SI' : 'NO'],
            ['phpseclib', !empty($diagnostics['phpseclib_available']) ? 'SI' : 'NO'],
            ['Cache dir', $cacheDir],
            ['Cache writable', is_dir($cacheDir) ? (is_writable($cacheDir) ? 'SI' : 'NO') : (is_writable(dirname($cacheDir)) ? 'SI (padre)' : 'NO')],
            ['Último error', (string) ($diagnostics['last_error'] ?? '—')],
        ]
    );

    $hcNumber = trim((string) $this->argument('hc_number'));
    $formId = trim((string) $this->argument('form_id'));
    if ($hcNumber === '' || $formId === '') {
        $this->newLine();
        $this->components->warn('No se ejecutó prueba de lectura. Pasa hc_number y form_id para medir listFiles/openFile.');
        return !empty($diagnostics['available']) ? 0 : 1;
    }

    $this->newLine();
    $this->components->info(sprintf('Prueba de lectura: hc=%s form_id=%s', $hcNumber, $formId));

    $startedAt = microtime(true);
    $files = $service->listFiles($hcNumber, $formId);
    $listDurationMs = (int) round((microtime(true) - $startedAt) * 1000);
    $listError = $service->getLastError();

    $selectedFile = trim((string) $this->option('file'));
    if ($selectedFile === '' && $files !== []) {
        $selectedFile = trim((string) ($files[0]['name'] ?? ''));
    }

    $openDurationMs = null;
    $openSize = null;
    $openError = null;
    if ($selectedFile !== '') {
        $startedAt = microtime(true);
        $opened = $service->openFile($hcNumber, $formId, $selectedFile);
        $openDurationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $openError = $service->getLastError();
        if ($opened && !empty($opened['stream'])) {
            $openSize = (int) ($opened['size'] ?? 0);
            fclose($opened['stream']);
        }
    }

    $this->table(
        ['Prueba', 'Resultado'],
        [
            ['listFiles ms', (string) $listDurationMs],
            ['files encontrados', (string) count($files)],
            ['error listFiles', $listError ?? '—'],
            ['archivo probado', $selectedFile !== '' ? $selectedFile : '—'],
            ['openFile ms', $openDurationMs !== null ? (string) $openDurationMs : '—'],
            ['size bytes', $openSize !== null ? (string) $openSize : '—'],
            ['error openFile', $openError ?? '—'],
        ]
    );

    return 0;
})->purpose('Diagnostica si el NAS se esta leyendo por mount local o SFTP y mide una prueba real');
