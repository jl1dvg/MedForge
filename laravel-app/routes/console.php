<?php

use App\Modules\Agenda\Services\IndexAdmisionesSyncService;
use App\Modules\Billing\Services\BillingInformeDataService;
use App\Modules\Billing\Services\BillingInformePacienteService;
use App\Modules\Billing\Services\FacturacionRealSyncService;
use App\Modules\Examenes\Services\ImagenesNasIndexService;
use App\Modules\Examenes\Services\ImagenesSigcenterIndexService;
use App\Modules\Examenes\Services\NasImagenesService;
use App\Modules\Farmacia\Services\RecetasConciliacionSyncService;
use App\Modules\Shared\Support\AfiliacionDimensionService;
use App\Modules\Solicitudes\Services\SolicitudesChecklistBackfillService;
use App\Modules\Solicitudes\Services\SolicitudesPrefacturaService;
use App\Modules\Whatsapp\Services\ConversationOpsService;
use App\Modules\Whatsapp\Services\FlowRuntimeShadowCompareService;
use App\Modules\Whatsapp\Services\FlowRuntimeShadowObserverService;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('solicitudes:phase3-backfill-checklist
    {--dry-run : Solo calcula cuántas solicitudes requieren backfill}
    {--limit=0 : Limita cuántas solicitudes procesa}', function (): int {
    /** @var SolicitudesChecklistBackfillService $service */
    $service = app(SolicitudesChecklistBackfillService::class);

    $summary = $service->summary();
    $dryRun = (bool) $this->option('dry-run');
    $limit = max(0, (int) $this->option('limit'));

    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Solicitudes totales', (string) ($summary['total'] ?? 0)],
            ['Solicitudes candidatas', (string) ($summary['candidatas'] ?? 0)],
            ['Etapas esperadas por solicitud', (string) ($summary['esperadas_por_solicitud'] ?? 0)],
            ['Modo', $dryRun ? 'dry-run' : 'write'],
            ['Límite', $limit > 0 ? (string) $limit : 'sin límite'],
        ]
    );

    $result = $service->run($dryRun, $limit);

    $this->newLine();
    $this->table(
        ['Resultado', 'Valor'],
        [
            ['Candidatas evaluadas', (string) ($result['candidatas'] ?? 0)],
            ['Solicitudes procesadas', (string) ($result['procesadas'] ?? 0)],
            ['Filas de checklist insertadas', (string) ($result['filas_insertadas'] ?? 0)],
            ['Estados normalizados', (string) ($result['estados_actualizados'] ?? 0)],
        ]
    );

    if ($dryRun) {
        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    $this->info('Backfill de checklist completado.');
    return 0;
})->purpose('Siembra o completa checklist operativo de Solicitudes para cerrar la fase 3');

Artisan::command('whatsapp:phase1-smoke', function (): int {
    $enabled = (bool) config('whatsapp.migration.enabled', false);
    $uiEnabled = (bool) config('whatsapp.migration.ui.enabled', false);
    $apiReadEnabled = (bool) config('whatsapp.migration.api.read_enabled', false);
    $writeEnabled = (bool) config('whatsapp.migration.api.write_enabled', false);
    $webhookEnabled = (bool) config('whatsapp.migration.api.webhook_enabled', false);
    $fallback = (bool) config('whatsapp.migration.fallback_to_legacy', true);
    $compare = (bool) config('whatsapp.migration.compare_with_legacy', true);
    $automationEnabled = (bool) config('whatsapp.migration.automation.enabled', false);
    $automationCompare = (bool) config('whatsapp.migration.automation.compare_with_legacy', true);
    $automationFallback = (bool) config('whatsapp.migration.automation.fallback_to_legacy', true);
    $automationDryRun = (bool) config('whatsapp.migration.automation.dry_run', true);

    try {
        $conversationCount = class_exists(WhatsappConversation::class) ? WhatsappConversation::query()->count() : 0;
        $messageCount = class_exists(WhatsappMessage::class) ? WhatsappMessage::query()->count() : 0;
        $dbStatus = 'ok';
    } catch (\Throwable $e) {
        $conversationCount = -1;
        $messageCount = -1;
        $dbStatus = 'unavailable: ' . $e->getMessage();
    }

    $this->table(
        ['Flag', 'Value'],
        [
            ['WHATSAPP_LARAVEL_ENABLED', $enabled ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_UI_ENABLED', $uiEnabled ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_API_READ_ENABLED', $apiReadEnabled ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_API_WRITE_ENABLED', $writeEnabled ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_WEBHOOK_ENABLED', $webhookEnabled ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_FALLBACK_TO_LEGACY', $fallback ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_COMPARE_WITH_LEGACY', $compare ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_AUTOMATION_ENABLED', $automationEnabled ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_AUTOMATION_COMPARE_WITH_LEGACY', $automationCompare ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_AUTOMATION_FALLBACK_TO_LEGACY', $automationFallback ? 'true' : 'false'],
            ['WHATSAPP_LARAVEL_AUTOMATION_DRY_RUN', $automationDryRun ? 'true' : 'false'],
            ['db_status', $dbStatus],
            ['whatsapp_conversations', (string) $conversationCount],
            ['whatsapp_messages', (string) $messageCount],
        ]
    );

    $this->newLine();
    $this->line('Rutas fase 1 esperadas:');
    $this->line('GET /v2/whatsapp/chat');
    $this->line('GET /v2/whatsapp/api/conversations');
    $this->line('GET /v2/whatsapp/api/conversations/{id}');
    $this->line('POST /v2/whatsapp/api/conversations/{id}/messages');
    $this->line('GET /whatsapp/webhook');
    $this->line('POST /whatsapp/webhook');
    $this->line('GET /v2/whatsapp/webhook');
    $this->line('POST /v2/whatsapp/webhook');

    if (!$enabled || !$apiReadEnabled) {
        $this->warn('La lectura Laravel de WhatsApp sigue desactivada. Se mantendrá fallback a legacy si está habilitado.');
        return 0;
    }

    if (!$writeEnabled || !$webhookEnabled) {
        $this->warn('Laravel ya puede leer conversaciones, pero escritura o webhook siguen parcialmente desactivados por flag.');
        return 0;
    }

    $this->info('Fase 1 extendida de WhatsApp habilitada: lectura, escritura y webhook.');
    return 0;
})->purpose('Verifica flags y estado base de la fase 1 de WhatsApp');

Artisan::command('whatsapp:flowmaker-shadow
    {wa_number : Número WhatsApp para simular}
    {text : Mensaje entrante a comparar}
    {--context= : JSON opcional de contexto a inyectar en la simulación}
    {--json : Devuelve solo el payload JSON}', function (): int {
    /** @var FlowRuntimeShadowCompareService $service */
    $service = app(FlowRuntimeShadowCompareService::class);

    $context = [];
    $rawContext = trim((string) ($this->option('context') ?? ''));
    if ($rawContext !== '') {
        $decoded = json_decode($rawContext, true);
        if (!is_array($decoded)) {
            $this->error('El contexto debe ser un JSON válido.');
            return 1;
        }

        $context = $decoded;
    }

    $result = $service->compare([
        'wa_number' => trim((string) $this->argument('wa_number')),
        'text' => trim((string) $this->argument('text')),
        'context' => $context,
    ]);

    if ((bool) $this->option('json')) {
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        return 0;
    }

    $this->table(
        ['Check', 'Value'],
        [
            ['legacy_source', (string) ($result['sources']['legacy'] ?? 'unknown')],
            ['same_match', !empty($result['parity']['same_match']) ? 'true' : 'false'],
            ['same_scenario', !empty($result['parity']['same_scenario']) ? 'true' : 'false'],
            ['same_handoff', !empty($result['parity']['same_handoff']) ? 'true' : 'false'],
            ['same_action_types', !empty($result['parity']['same_action_types']) ? 'true' : 'false'],
            ['laravel_scenario', (string) ($result['laravel']['scenario']['id'] ?? '-')],
            ['legacy_scenario', (string) ($result['legacy']['scenario']['id'] ?? '-')],
        ]
    );

    return 0;
})->purpose('Compara en modo sombra la simulación Laravel del autorespondedor contra la fuente legacy');

Artisan::command('whatsapp:flowmaker-shadow-runs
    {--limit=25 : Número máximo de runs a mostrar}
    {--mismatches : Solo muestra runs con diferencias}
    {--json : Devuelve solo el payload JSON}', function (): int {
    /** @var FlowRuntimeShadowObserverService $service */
    $service = app(FlowRuntimeShadowObserverService::class);

    $runs = $service->recent(
        (int) $this->option('limit'),
        (bool) $this->option('mismatches')
    );

    if ((bool) $this->option('json')) {
        $this->line(json_encode($runs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]');
        return 0;
    }

    $this->table(
        ['ID', 'Fecha', 'Número', 'Laravel', 'Legacy', 'Match', 'Scenario', 'Handoff', 'Actions'],
        array_map(static fn (array $run): array => [
            (string) ($run['id'] ?? '-'),
            (string) ($run['created_at'] ?? '-'),
            (string) ($run['wa_number'] ?? '-'),
            (string) ($run['laravel_scenario'] ?? '-'),
            (string) ($run['legacy_scenario'] ?? '-'),
            !empty($run['parity']['same_match']) ? 'true' : 'false',
            !empty($run['parity']['same_scenario']) ? 'true' : 'false',
            !empty($run['parity']['same_handoff']) ? 'true' : 'false',
            !empty($run['parity']['same_action_types']) ? 'true' : 'false',
        ], $runs)
    );

    return 0;
})->purpose('Lista los runs recientes del shadow runtime del webhook de WhatsApp');

Artisan::command('whatsapp:flowmaker-shadow-sync
    {--limit=50 : Número máximo de mensajes inbound recientes a revisar}
    {--json : Devuelve solo el payload JSON}', function (): int {
    /** @var FlowRuntimeShadowObserverService $service */
    $service = app(FlowRuntimeShadowObserverService::class);

    $result = $service->syncRecentInboundMessages((int) $this->option('limit'));

    if ((bool) $this->option('json')) {
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        return 0;
    }

    $this->table(
        ['Metric', 'Value'],
        [
            ['source', (string) ($result['source'] ?? '-')],
            ['processed', (string) ($result['processed'] ?? 0)],
            ['skipped', (string) ($result['skipped'] ?? 0)],
        ]
    );

    return 0;
})->purpose('Genera shadow-runs desde mensajes inbound ya persistidos por legacy/DB compartida');

Artisan::command('whatsapp:flowmaker-shadow-summary
    {--limit=250 : Número máximo de runs a considerar}
    {--json : Devuelve solo el payload JSON}', function (): int {
    /** @var FlowRuntimeShadowObserverService $service */
    $service = app(FlowRuntimeShadowObserverService::class);

    $summary = $service->summary((int) $this->option('limit'));

    if ((bool) $this->option('json')) {
        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        return 0;
    }

    $this->table(
        ['Metric', 'Value'],
        [
            ['total_runs', (string) ($summary['total_runs'] ?? 0)],
            ['mismatch_runs', (string) ($summary['mismatch_runs'] ?? 0)],
            ['dry_run_runs', (string) ($summary['dry_run_runs'] ?? 0)],
        ]
    );

    $this->newLine();
    $this->line('Top mismatch reasons:');
    foreach (($summary['top_mismatch_reasons'] ?? []) as $row) {
        $this->line('- ' . ($row['reason'] ?? '-') . ': ' . ($row['count'] ?? 0));
    }

    $this->newLine();
    $this->line('Top scenario gaps:');
    foreach (($summary['top_scenario_gaps'] ?? []) as $row) {
        $this->line('- ' . ($row['pair'] ?? '-') . ': ' . ($row['count'] ?? 0));
    }

    return 0;
})->purpose('Resume mismatches y runs dry-run del shadow runtime de WhatsApp');

Artisan::command('whatsapp:flowmaker-readiness
    {--limit=250 : Número máximo de runs a considerar}
    {--json : Devuelve solo el payload JSON}', function (): int {
    /** @var FlowRuntimeShadowObserverService $service */
    $service = app(FlowRuntimeShadowObserverService::class);

    $readiness = $service->readiness((int) $this->option('limit'));

    if ((bool) $this->option('json')) {
        $this->line(json_encode($readiness, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        return 0;
    }

    $this->table(
        ['Check', 'Expected', 'Actual', 'Passed'],
        array_map(static fn (array $check): array => [
            (string) ($check['label'] ?? $check['key'] ?? '-'),
            (string) ($check['expected'] ?? '-'),
            (string) ($check['actual'] ?? '-'),
            !empty($check['passed']) ? 'true' : 'false',
        ], $readiness['checks'] ?? [])
    );

    $this->newLine();
    $this->line('ready_for_phase_7: ' . (!empty($readiness['ready_for_phase_7']) ? 'true' : 'false'));
    if (!empty($readiness['blocking_checks'])) {
        $this->line('blocking_checks: ' . implode(', ', $readiness['blocking_checks']));
    }

    return !empty($readiness['ready_for_phase_7']) ? 0 : 1;
})->purpose('Evalúa si la paridad del shadow runtime permite cerrar Fase 6');

Artisan::command('whatsapp:handoff-requeue-expired {--dry-run : Solo muestra los handoffs vencidos sin reencolarlos}', function (): int {
    /** @var ConversationOpsService $service */
    $service = app(ConversationOpsService::class);

    try {
        if ((bool) $this->option('dry-run')) {
            $preview = $service->previewExpiredHandoffs();
            $this->table(
                ['Expired handoffs', 'IDs'],
                [[
                    (int) ($preview['count'] ?? 0),
                    implode(', ', array_map(static fn (int $id): string => (string) $id, $preview['ids'] ?? [])),
                ]]
            );

            return 0;
        }

        $result = $service->requeueExpired();
        $this->table(
            ['Requeued', 'IDs'],
            [[
                (int) ($result['count'] ?? 0),
                implode(', ', array_map(static fn (int $id): string => (string) $id, $result['ids'] ?? [])),
            ]]
        );

        return 0;
    } catch (\Throwable $e) {
        $this->warn('No fue posible consultar o reencolar handoffs vencidos con la DB configurada.');
        $this->line($e->getMessage());

        return 0;
    }
})->purpose('Reencola handoffs vencidos del inbox WhatsApp Laravel');

Artisan::command('derivaciones:scrape
    {form_id : Form ID / pedido ID a consultar}
    {hc_number : HC number del paciente}
    {--solicitud-id= : Si se envía, también actualiza derivacion_* en solicitud_procedimiento}', function (): int {
    $formId = trim((string) $this->argument('form_id'));
    $hcNumber = trim((string) $this->argument('hc_number'));
    $solicitudIdOption = trim((string) ($this->option('solicitud-id') ?? ''));
    $solicitudId = $solicitudIdOption !== '' ? (int) $solicitudIdOption : null;

    /** @var SolicitudesPrefacturaService $service */
    $service = app(SolicitudesPrefacturaService::class);

    try {
        $result = $service->rescrapeDerivacion($formId, $hcNumber, $solicitudId);
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];

    $this->table(
        ['Campo', 'Valor'],
        [
            ['form_id', (string) ($payload['form_id'] ?? $formId)],
            ['hc_number', (string) ($payload['hc_number'] ?? $hcNumber)],
            ['codigo_derivacion', (string) ($payload['codigo_derivacion'] ?? '')],
            ['fecha_registro', (string) ($payload['fecha_registro'] ?? '')],
            ['fecha_vigencia', (string) ($payload['fecha_vigencia'] ?? '')],
            ['archivo_path', (string) ($payload['archivo_path'] ?? '')],
            ['lookup_form_id', (string) ($result['lookup_form_id'] ?? '')],
            ['saved_legacy', !empty($result['saved']) ? 'SI' : 'NO'],
            ['exit_code', (string) ($result['exit_code'] ?? '')],
        ]
    );

    $rawOutput = trim((string) ($result['raw_output'] ?? ''));
    if ($rawOutput !== '') {
        $this->newLine();
        $this->line($rawOutput);
    }

    return 0;
})->purpose('Ejecuta el scraper de derivaciones via Python y opcionalmente actualiza solicitud_procedimiento');

Artisan::command('derivaciones:scrape-missing
    {--limit=200 : Máximo de formularios por corrida}
    {--max-attempts=3 : Máximo de intentos por form_id}
    {--cooldown-hours=6 : Horas mínimas entre reintentos}', function (): int {
    $syncServiceClass = 'Modules\\Derivaciones\\Services\\DerivacionesSyncService';
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', dirname(base_path()));
    }
    if (!class_exists($syncServiceClass)) {
        $syncServicePath = dirname(base_path()) . '/modules/Derivaciones/Services/DerivacionesSyncService.php';
        if (!is_file($syncServicePath)) {
            $this->components->error('No se encontró DerivacionesSyncService.');
            return 1;
        }

        require_once $syncServicePath;
    }

    $pdo = DB::connection()->getPdo();
    /** @var object{scrapeMissingDerivationsBatch:callable} $service */
    $service = new $syncServiceClass($pdo);

    try {
        $result = $service->scrapeMissingDerivationsBatch(
            (int) $this->option('limit'),
            (int) $this->option('max-attempts'),
            (int) $this->option('cooldown-hours')
        );
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $details = is_array($result['details'] ?? null) ? $result['details'] : [];
    $this->table(
        ['Status', 'Message', 'Processed', 'Success', 'Failed', 'Skipped'],
        [[
            (string) ($result['status'] ?? ''),
            (string) ($result['message'] ?? ''),
            (int) ($details['processed'] ?? 0),
            (int) ($details['success'] ?? 0),
            (int) ($details['failed'] ?? 0),
            (int) ($details['skipped'] ?? 0),
        ]]
    );

    return (($result['status'] ?? '') === 'success') ? 0 : 1;
})->purpose('Scrapea derivaciones faltantes exclusivamente via Python batch');

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

    $cacheDir = trim((string) (env('IMAGENES_CACHE_DIR') ?? env('NAS_IMAGES_CACHE_DIR') ?? ''));
    if ($cacheDir === '') {
        $cacheDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'medforge_imagenes_cache';
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

Artisan::command('imagenes:sigcenter-index
    {--days=7 : Dias hacia atras para buscar candidatos}
    {--from-date= : Fecha inicial YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --days}
    {--to-date= : Fecha final YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --days}
    {--limit= : Maximo de form_id a escanear; vacio o 0 = sin limite}
    {--stale-hours=6 : Solo reescanea filas mas viejas que este umbral}
    {--form-id= : Escanea un form_id puntual}
    {--force : Ignora antiguedad del cache}', function (): int {
    /** @var ImagenesSigcenterIndexService $service */
    $service = app(ImagenesSigcenterIndexService::class);

    $this->components->info('Iniciando indexacion Sigcenter de imagenes...');

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
        $verifiedFiles = (int) ($payload['verified_files_count'] ?? 0);
        $durationMs = (int) ($payload['scan_duration_ms'] ?? 0);

        $this->line(sprintf(
            '[%s] form_id=%s hc=%s files=%d verified=%d scan=%dms',
            $status,
            $formId,
            $hcNumber,
            $filesCount,
            $verifiedFiles,
            $durationMs
        ));
    });

    if (!(bool) ($result['success'] ?? false)) {
        $this->components->error((string) ($result['error'] ?? 'No se pudo ejecutar el indexador.'));
        return 1;
    }

    $this->newLine();
    $this->table(
        ['Candidates', 'Processed', 'With files', 'With DB rows', 'No mapping', 'Empty', 'Errors', 'Duration ms', 'Avg scan ms'],
        [[
            (int) ($result['candidates'] ?? 0),
            (int) ($result['processed'] ?? 0),
            (int) ($result['with_files'] ?? 0),
            (int) ($result['with_db_rows'] ?? 0),
            (int) ($result['no_mapping'] ?? 0),
            (int) ($result['empty'] ?? 0),
            (int) ($result['errors'] ?? 0),
            (int) ($result['duration_ms'] ?? 0),
            (int) ($result['avg_scan_ms'] ?? 0),
        ]]
    );

    $this->components->info('Indexacion Sigcenter finalizada.');

    return 0;
})->purpose('Indexa evidencia de imagenes de Sigcenter en una tabla local por form_id');

Artisan::command('index-admisiones:sync
    {--lookback=14 : Dias hacia atras desde hoy}
    {--lookahead=14 : Dias hacia adelante desde hoy}
    {--from-date= : Fecha inicial YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --lookback}
    {--to-date= : Fecha final YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --lookahead}
    {--extractor=auto : Driver de extracción: auto, scraper o db}', function (): int {
    /** @var IndexAdmisionesSyncService $service */
    $service = app(IndexAdmisionesSyncService::class);

    $fromDate = $this->option('from-date');
    $toDate = $this->option('to-date');
    $lookback = (int) $this->option('lookback');
    $lookahead = (int) $this->option('lookahead');
    $extractor = trim((string) ($this->option('extractor') ?? 'auto'));

    $this->line(sprintf(
        '[%s] [start] index-admisiones:sync lookback=%d lookahead=%d from_date=%s to_date=%s extractor=%s',
        now()->format('Y-m-d H:i:s'),
        $lookback,
        $lookahead,
        $fromDate !== null && $fromDate !== '' ? (string) $fromDate : '—',
        $toDate !== null && $toDate !== '' ? (string) $toDate : '—',
        $extractor !== '' ? $extractor : 'auto'
    ));

    $result = $service->sync([
        'lookback' => $lookback,
        'lookahead' => $lookahead,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'extractor' => $extractor,
    ], function (string $event, array $payload): void {
        if ($event === 'row') {
            $this->line(sprintf(
                '[ROW] form_id=%s hc=%s estado=%s fecha=%s',
                (string) ($payload['form_id'] ?? ''),
                (string) ($payload['hc_number'] ?? ''),
                (string) ($payload['estado'] ?? '—'),
                (string) ($payload['fecha'] ?? '—')
            ));
            return;
        }

        if ($event === 'skip') {
            $this->line(sprintf(
                '[SKIP] form_id=%s hc=%s reason=%s',
                (string) ($payload['form_id'] ?? ''),
                (string) ($payload['hc_number'] ?? ''),
                (string) ($payload['reason'] ?? 'unknown')
            ));
            return;
        }

        if ($event === 'error') {
            $this->error(sprintf(
                '[ERROR] form_id=%s hc=%s error=%s',
                (string) ($payload['form_id'] ?? ''),
                (string) ($payload['hc_number'] ?? ''),
                (string) ($payload['error'] ?? 'unknown')
            ));
        }
    });

    if (!(bool) ($result['success'] ?? false)) {
        $this->error((string) ($result['error'] ?? 'No se pudo sincronizar index-admisiones.'));
        $this->line(sprintf('[%s] [error] index-admisiones:sync', now()->format('Y-m-d H:i:s')));
        return 1;
    }

    $this->newLine();
    $this->table(
        ['From', 'To', 'Source', 'Total', 'Processed', 'Sent', 'Skipped', 'Errors', 'Duration ms'],
        [[
            (string) ($result['from'] ?? '—'),
            (string) ($result['to'] ?? '—'),
            (string) ($result['source'] ?? '—'),
            (int) ($result['total_rows'] ?? 0),
            (int) ($result['processed_rows'] ?? 0),
            (int) ($result['sent_rows'] ?? 0),
            (int) ($result['skipped_rows'] ?? 0),
            (int) ($result['error_rows'] ?? 0),
            (int) ($result['duration_ms'] ?? 0),
        ]]
    );

    $this->line(sprintf('[%s] [done] index-admisiones:sync', now()->format('Y-m-d H:i:s')));

    return 0;
})->purpose('Sincroniza index-admisiones desde Sigcenter hacia tablas locales usando DB/SSH o scraper');

Artisan::command('billing:facturacion-real-sync
    {--start= : Mes inicial YYYY-MM}
    {--end= : Mes final YYYY-MM}
    {--extractor=auto : Driver de extracción: auto, scraper, csv o db}
    {--csv-path= : Ruta del CSV origen. Puede incluir {month} para reemplazar YYYY-MM}', function (): int {
    /** @var FacturacionRealSyncService $service */
    $service = app(FacturacionRealSyncService::class);

    $defaultMonth = now()->format('Y-m');
    $start = trim((string) ($this->option('start') ?? ''));
    $end = trim((string) ($this->option('end') ?? ''));
    $extractor = trim((string) ($this->option('extractor') ?? 'auto'));
    $csvPath = trim((string) ($this->option('csv-path') ?? ''));
    $start = $start !== '' ? $start : $defaultMonth;
    $end = $end !== '' ? $end : $start;

    $this->line(sprintf(
        '[%s] [start] billing:facturacion-real-sync start=%s end=%s extractor=%s',
        now()->format('Y-m-d H:i:s'),
        $start,
        $end,
        $extractor !== '' ? $extractor : 'auto'
    ));

    try {
        $result = $service->sync([
            'start' => $start,
            'end' => $end,
            'extractor' => $extractor,
            'csv_path' => $csvPath,
        ], function (string $event, array $payload): void {
            if ($event === 'row') {
                $this->line(sprintf(
                    '[ROW] form_id=%s factura_id=%s numero_factura=%s monto_honorario=%s mes=%s',
                    (string) ($payload['form_id'] ?? ''),
                    (string) ($payload['factura_id'] ?? ''),
                    (string) ($payload['numero_factura'] ?? ''),
                    (string) ($payload['monto_honorario'] ?? ''),
                    (string) ($payload['source_month'] ?? '')
                ));
                return;
            }

            if ($event === 'error') {
                $this->error(sprintf(
                    '[ERROR] form_id=%s factura_id=%s mes=%s error=%s',
                    (string) ($payload['form_id'] ?? ''),
                    (string) ($payload['factura_id'] ?? ''),
                    (string) ($payload['source_month'] ?? ''),
                    (string) ($payload['error'] ?? 'unknown')
                ));
            }
        });
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        $this->line(sprintf('[%s] [error] billing:facturacion-real-sync', now()->format('Y-m-d H:i:s')));
        return 1;
    }

    if (!(bool) ($result['success'] ?? false)) {
        $this->error((string) ($result['error'] ?? 'No se pudo sincronizar facturación real.'));
        $this->line(sprintf('[%s] [error] billing:facturacion-real-sync', now()->format('Y-m-d H:i:s')));
        return 1;
    }

    $this->newLine();
    $this->table(
        ['From', 'To', 'Source', 'Months', 'Total', 'Sent', 'Errors', 'Duration ms'],
        [[
            (string) ($result['from'] ?? '—'),
            (string) ($result['to'] ?? '—'),
            (string) ($result['source'] ?? '—'),
            implode(', ', (array) ($result['months'] ?? [])),
            (int) ($result['total_rows'] ?? 0),
            (int) ($result['sent_rows'] ?? 0),
            (int) ($result['error_rows'] ?? 0),
            (int) ($result['duration_ms'] ?? 0),
        ]]
    );

    $this->line(sprintf('[%s] [done] billing:facturacion-real-sync', now()->format('Y-m-d H:i:s')));

    return 0;
})->purpose('Sincroniza facturación real consolidada por form_id desde Sigcenter hacia billing_facturacion_real');

Artisan::command('farmacia:conciliar-recetas
    {--lookback=14 : Dias hacia atras desde hoy}
    {--lookahead=0 : Dias hacia adelante desde hoy}
    {--from-date= : Fecha inicial YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --lookback}
    {--to-date= : Fecha final YYYY-MM-DD (inclusive). Si se usa, tiene prioridad sobre --lookahead}', function (): int {
    /** @var RecetasConciliacionSyncService $service */
    $service = app(RecetasConciliacionSyncService::class);

    $today = now()->startOfDay();
    $fromDateOption = trim((string) ($this->option('from-date') ?? ''));
    $toDateOption = trim((string) ($this->option('to-date') ?? ''));
    $lookback = (int) $this->option('lookback');
    $lookahead = (int) $this->option('lookahead');

    $fromDate = $fromDateOption !== '' ? $fromDateOption : $today->copy()->subDays($lookback)->format('Y-m-d');
    $toDate = $toDateOption !== '' ? $toDateOption : $today->copy()->addDays($lookahead)->format('Y-m-d');

    $this->line(sprintf(
        '[%s] [start] farmacia:conciliar-recetas from_date=%s to_date=%s',
        now()->format('Y-m-d H:i:s'),
        $fromDate,
        $toDate
    ));

    try {
        $result = $service->sync([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ], function (string $event, array $payload): void {
            if ($event === 'row') {
                $this->line(sprintf(
                    '[ROW] receta_id=%s pedido_id=%s tipo_match=%s fecha_receta=%s depto=%s',
                    (string) ($payload['receta_id'] ?? ''),
                    (string) ($payload['pedido_id'] ?? ''),
                    (string) ($payload['tipo_match'] ?? ''),
                    (string) ($payload['fecha_receta'] ?? ''),
                    (string) ($payload['departamento_factura'] ?? '')
                ));
                return;
            }

            if ($event === 'error') {
                $this->error(sprintf(
                    '[ERROR] receta_id=%s pedido_id=%s error=%s',
                    (string) ($payload['receta_id'] ?? ''),
                    (string) ($payload['pedido_id'] ?? ''),
                    (string) ($payload['error'] ?? 'unknown')
                ));
            }
        });
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        $this->line(sprintf('[%s] [error] farmacia:conciliar-recetas', now()->format('Y-m-d H:i:s')));
        return 1;
    }

    if (!(bool) ($result['success'] ?? false)) {
        $this->error((string) ($result['error'] ?? 'No se pudo conciliar recetas.'));
        $this->line(sprintf('[%s] [error] farmacia:conciliar-recetas', now()->format('Y-m-d H:i:s')));
        return 1;
    }

    $this->newLine();
    $this->table(
        ['From', 'To', 'Source', 'Total', 'Sent', 'Errors', 'Duration ms'],
        [[
            (string) ($result['from'] ?? '—'),
            (string) ($result['to'] ?? '—'),
            (string) ($result['source'] ?? '—'),
            (int) ($result['total_rows'] ?? 0),
            (int) ($result['sent_rows'] ?? 0),
            (int) ($result['error_rows'] ?? 0),
            (int) ($result['duration_ms'] ?? 0),
        ]]
    );

    $this->line(sprintf('[%s] [done] farmacia:conciliar-recetas', now()->format('Y-m-d H:i:s')));

    return 0;
})->purpose('Concilia recetas emitidas contra facturación de farmacia en una tabla local derivada');

Artisan::command('billing:sync-derivaciones
    {--limit=200 : Máximo de facturas a procesar por corrida. Usa 0 para sin límite}
    {--only-billed=1 : Prioriza solo billing_main ya facturado}
    {--only-missing=1 : Solo procesa derivaciones faltantes o inconsistentes}
    {--month= : Filtra por mes YYYY-MM usando procedimiento_proyectado.fecha}
    {--procedure-date= : Filtra por fecha exacta YYYY-MM-DD usando procedimiento_proyectado.fecha}
    {--categoria= : Filtra por categoría de seguro, por ejemplo publico o privado}
    {--empresa-seguro= : Filtra por empresa de seguro}
    {--afiliacion-like= : Filtro legacy por afiliación en patient_data.afiliacion}
    {--form-ids= : Procesa varios form_id separados por coma}
    {--form-id= : Procesa un form_id puntual}', function (): int {
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    $limit = (int) $this->option('limit');
    $limitSql = $limit > 0 ? 'LIMIT ' . $limit : '';
    $onlyBilled = (bool) ((int) $this->option('only-billed'));
    $onlyMissing = (bool) ((int) $this->option('only-missing'));
    $month = trim((string) ($this->option('month') ?? ''));
    $procedureDate = trim((string) ($this->option('procedure-date') ?? ''));
    $categoria = trim((string) ($this->option('categoria') ?? ''));
    $empresaSeguro = trim((string) ($this->option('empresa-seguro') ?? ''));
    $afiliacionLike = trim((string) ($this->option('afiliacion-like') ?? ''));
    $formIdsOption = trim((string) ($this->option('form-ids') ?? ''));
    $formId = trim((string) ($this->option('form-id') ?? ''));

    $pdo = DB::connection()->getPdo();
    $pacienteService = new BillingInformePacienteService($pdo);
    $service = new BillingInformeDataService($pdo, $pacienteService);
    $afiliacionDimensions = new AfiliacionDimensionService($pdo);
    $dimensionContext = $afiliacionDimensions->buildContext('pt.afiliacion', 'acm_sync');

    $conditions = [
        "bm.form_id IS NOT NULL",
        "TRIM(bm.form_id) <> ''",
        "bm.hc_number IS NOT NULL",
        "TRIM(bm.hc_number) <> ''",
    ];
    $params = [];

    $explicitFormIds = [];
    if ($formIdsOption !== '') {
        $explicitFormIds = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => trim($value),
            explode(',', $formIdsOption)
        ))));
    }
    if ($formId !== '') {
        $explicitFormIds[] = $formId;
        $explicitFormIds = array_values(array_unique(array_filter($explicitFormIds)));
    }

    if ($explicitFormIds !== []) {
        $placeholders = implode(',', array_fill(0, count($explicitFormIds), '?'));
        $conditions[] = "bm.form_id IN ($placeholders)";
        array_push($params, ...$explicitFormIds);
    } elseif ($formId !== '') {
        $conditions[] = 'bm.form_id = ?';
        $params[] = $formId;
    }

    if ($procedureDate !== '') {
        $conditions[] = <<<'SQL'
EXISTS (
    SELECT 1
    FROM procedimiento_proyectado ppm
    WHERE ppm.form_id = bm.form_id
      AND DATE(ppm.fecha) = ?
)
SQL;
        $params[] = $procedureDate;
    } elseif ($month !== '') {
        $conditions[] = <<<'SQL'
EXISTS (
    SELECT 1
    FROM procedimiento_proyectado ppm
    WHERE ppm.form_id = bm.form_id
      AND DATE(ppm.fecha) BETWEEN ? AND ?
)
SQL;
        $params[] = $month . '-01';
        $params[] = date('Y-m-t', strtotime($month . '-01'));
    }

    if ($afiliacionLike !== '') {
        $conditions[] = 'LOWER(COALESCE(pt.afiliacion, \'\')) LIKE ?';
        $params[] = '%' . strtolower($afiliacionLike) . '%';
    }

    $categoriaKey = $afiliacionDimensions->normalizeCategoriaFilter($categoria);
    if ($categoriaKey !== '') {
        $conditions[] = "{$dimensionContext['categoria_expr']} = ?";
        $params[] = $categoriaKey;
    }

    $empresaKey = $afiliacionDimensions->normalizeEmpresaFilter($empresaSeguro);
    if ($empresaKey !== '') {
        $conditions[] = "{$dimensionContext['empresa_key_expr']} = ?";
        $params[] = $empresaKey;
    }

    if ($onlyBilled) {
        $conditions[] = 'bm.facturado_por IS NOT NULL';
    }

    if ($onlyMissing) {
        $conditions[] = <<<'SQL'
(
    dfi.id IS NULL
    OR dfi.cod_derivacion IS NULL
    OR TRIM(dfi.cod_derivacion) = ''
    OR dfi.archivo_derivacion_path IS NULL
    OR TRIM(dfi.archivo_derivacion_path) = ''
    OR REPLACE(TRIM(COALESCE(dfi.cod_derivacion, '')), ' ', '') NOT REGEXP '^[A-Z0-9._/-]{8,}$'
)
SQL;
    }

    $sql = sprintf(<<<'SQL'
SELECT
    bm.id,
    bm.form_id,
    bm.hc_number,
    bm.facturado_por,
    bm.updated_at,
    dfi.id AS derivacion_id,
    dfi.cod_derivacion,
    dfi.archivo_derivacion_path
FROM billing_main bm
LEFT JOIN derivaciones_form_id dfi
    ON dfi.form_id = bm.form_id
LEFT JOIN patient_data pt
    ON pt.hc_number = bm.hc_number
%s
WHERE %s
ORDER BY
    CASE WHEN dfi.id IS NULL THEN 0 ELSE 1 END ASC,
    CASE WHEN dfi.cod_derivacion IS NULL OR TRIM(dfi.cod_derivacion) = '' THEN 0 ELSE 1 END ASC,
    CASE WHEN dfi.archivo_derivacion_path IS NULL OR TRIM(dfi.archivo_derivacion_path) = '' THEN 0 ELSE 1 END ASC,
    bm.updated_at DESC,
    bm.id DESC
%s
SQL, $dimensionContext['join'], implode("\n  AND ", $conditions), $limitSql);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    if ($rows === []) {
        $this->info('No hay facturas candidatas para sincronizar derivaciones.');
        return 0;
    }

    $processed = 0;
    $updated = 0;
    $missing = 0;
    $errors = 0;

    foreach ($rows as $row) {
        if ($processed > 0 && $processed % 100 === 0) {
            $service->resetRemoteConnections();
            $this->line(sprintf('[INFO] progreso=%d/%d reconectando SSH Sigcenter...', $processed, count($rows)));
        }

        $candidateFormId = trim((string) ($row['form_id'] ?? ''));
        $candidateHcNumber = trim((string) ($row['hc_number'] ?? ''));
        if ($candidateFormId === '' || $candidateHcNumber === '') {
            continue;
        }

        $processed++;

        try {
            $payload = $service->buildDerivacionLookupPayload($candidateFormId, $candidateHcNumber);
            $codigo = trim((string) ($payload['codigo_derivacion'] ?? ''));
            $archivo = trim((string) ($payload['archivo_derivacion_path'] ?? ''));
            $saved = $payload !== [] && (
                $codigo !== ''
                || $archivo !== ''
                || trim((string) ($payload['referido'] ?? '')) !== ''
                || trim((string) ($payload['diagnostico'] ?? '')) !== ''
            )
                ? $service->persistDerivacionLookupPayload($payload)
                : false;

            if ($saved) {
                $updated++;
                $this->line(sprintf(
                    '[OK] form_id=%s hc=%s codigo=%s archivo=%s',
                    $candidateFormId,
                    $candidateHcNumber,
                    $codigo !== '' ? $codigo : '—',
                    $archivo !== '' ? 'SI' : 'NO'
                ));
                continue;
            }

            $missing++;
            $this->warn(sprintf(
                '[MISS] form_id=%s hc=%s codigo=%s archivo=%s',
                $candidateFormId,
                $candidateHcNumber,
                $codigo !== '' ? $codigo : '—',
                $archivo !== '' ? 'SI' : 'NO'
            ));
        } catch (\Throwable $e) {
            $errors++;
            $this->error(sprintf(
                '[ERROR] form_id=%s hc=%s error=%s',
                $candidateFormId,
                $candidateHcNumber,
                $e->getMessage()
            ));
        }
    }

    $this->newLine();
    $this->table(
        ['Candidates', 'Processed', 'Updated', 'Missing', 'Errors'],
        [[count($rows), $processed, $updated, $missing, $errors]]
    );

    return $errors > 0 ? 1 : 0;
})->purpose('Sincroniza derivaciones faltantes priorizando billing_main ya facturado');

Schedule::command('derivaciones:scrape-missing --limit=200 --max-attempts=3 --cooldown-hours=6')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('whatsapp:handoff-requeue-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(static fn (): bool => (bool) config('whatsapp.migration.handoff.requeue_schedule_enabled', false));

Schedule::command('whatsapp:flowmaker-shadow-sync --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(static fn (): bool => (bool) config('whatsapp.migration.automation.enabled', false)
        && (bool) config('whatsapp.migration.automation.compare_with_legacy', true));
