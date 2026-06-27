<?php

use App\Jobs\EvaluateSolicitudesSlaJob;
use App\Jobs\SendSolicitudReminderJob;
use App\Modules\Solicitudes\Services\SolicitudesSigcenterSyncService;
use App\Modules\Agenda\Services\IndexAdmisionesSyncService;
use App\Modules\Billing\Services\BillingInformeDataService;
use App\Modules\Billing\Services\BillingInformePacienteService;
use App\Modules\Billing\Services\FacturacionRealSyncService;
use App\Modules\Derivaciones\Services\DerivacionesBatchSyncService;
use App\Modules\Examenes\Services\ImagenesNasIndexService;
use App\Modules\Examenes\Services\ImagenesSigcenterIndexService;
use App\Modules\Examenes\Services\NasImagenesService;
use App\Modules\Farmacia\Services\RecetasConciliacionSyncService;
use App\Modules\Shared\Support\AfiliacionDimensionService;
use App\Modules\Shared\Support\SettingsOptionResolver;
use App\Modules\Solicitudes\Services\SolicitudesChecklistBackfillService;
use App\Modules\Solicitudes\Services\SolicitudesPrefacturaService;
use App\Modules\Whatsapp\Services\ConversationAttributionService;
use App\Modules\Whatsapp\Services\ConversationAbandonmentMonitorService;
use App\Modules\Whatsapp\Services\ConversationOpsService;
use App\Modules\Whatsapp\Services\FlowRuntimeShadowCompareService;
use App\Modules\Whatsapp\Services\FlowRuntimeShadowObserverService;
use App\Modules\Whatsapp\Services\WhatsappAppointmentReminderService;
use App\Modules\Whatsapp\Services\WhatsappDailyRescueReportService;
use App\Modules\Whatsapp\Services\WhatsappHandoffAutoAssignService;
use App\Modules\Whatsapp\Services\WhatsappOperationalAttributionService;
use App\Modules\Whatsapp\Services\WhatsappOperationalBaselineService;
use App\Modules\Whatsapp\Services\WhatsappOperationalDecisionService;
use App\Modules\Whatsapp\Services\WhatsappOperationalQueueService;
use App\Modules\Whatsapp\Services\WhatsappOperationalEventService;
use App\Modules\Whatsapp\Services\WhatsappRescueMetricsService;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationAttribution;
use App\Models\WhatsappMessage;
use App\Modules\CronManager\Repositories\CronScheduleRepository;
use App\Modules\CronManager\Services\CronRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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

Artisan::command('whatsapp:attribution-backfill
    {--conversation-id= : Recalcula una conversación puntual}
    {--from-date= : Solo conversaciones creadas desde YYYY-MM-DD}
    {--to-date= : Solo conversaciones creadas hasta YYYY-MM-DD}
    {--limit=0 : Límite total de conversaciones a procesar}
    {--chunk=200 : Tamaño de lote por iteración}
    {--only-missing : Solo procesa conversaciones sin atribución}
    {--dry-run : Solo estima cuántas conversaciones entrarían}', function (): int {
    /** @var ConversationAttributionService $service */
    $service = app(ConversationAttributionService::class);

    $conversationId = (int) ($this->option('conversation-id') ?? 0);
    $limit = max(0, (int) ($this->option('limit') ?? 0));
    $chunk = max(50, min(1000, (int) ($this->option('chunk') ?? 200)));
    $onlyMissing = (bool) $this->option('only-missing');
    $dryRun = (bool) $this->option('dry-run');
    $fromDate = trim((string) ($this->option('from-date') ?? ''));
    $toDate = trim((string) ($this->option('to-date') ?? ''));

    $query = WhatsappConversation::query()->orderBy('id');

    if ($conversationId > 0) {
        $query->where('id', $conversationId);
    }

    if ($fromDate !== '') {
        $query->whereDate('created_at', '>=', $fromDate);
    }

    if ($toDate !== '') {
        $query->whereDate('created_at', '<=', $toDate);
    }

    if ($onlyMissing) {
        $query->whereNotExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('whatsapp_conversation_attributions as wca')
                ->whereColumn('wca.conversation_id', 'whatsapp_conversations.id');
        });
    }

    $candidateCount = (clone $query)->count();

    if ($dryRun) {
        $this->table(
            ['Filtro', 'Valor'],
            [
                ['conversation_id', $conversationId > 0 ? (string) $conversationId : 'todos'],
                ['from_date', $fromDate !== '' ? $fromDate : 'sin filtro'],
                ['to_date', $toDate !== '' ? $toDate : 'sin filtro'],
                ['only_missing', $onlyMissing ? 'true' : 'false'],
                ['limit', $limit > 0 ? (string) $limit : 'sin límite'],
                ['chunk', (string) $chunk],
                ['candidate_count', (string) $candidateCount],
            ]
        );

        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    $processed = 0;
    $created = 0;
    $updated = 0;
    $errors = 0;

    $runner = function (WhatsappConversation $conversation) use ($service, &$processed, &$created, &$updated, &$errors, $limit): bool {
        if ($limit > 0 && $processed >= $limit) {
            return false;
        }

        try {
            $existing = WhatsappConversationAttribution::query()
                ->where('conversation_id', $conversation->id)
                ->exists();

            $service->syncConversation($conversation);

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }

            $processed++;
        } catch (\Throwable $e) {
            $errors++;
            $processed++;
            $this->warn('Error en conversación #' . $conversation->id . ': ' . $e->getMessage());
        }

        return true;
    };

    if ($conversationId > 0) {
        $conversation = $query->first();
        if (!$conversation instanceof WhatsappConversation) {
            $this->error('No existe la conversación solicitada.');
            return 1;
        }

        $runner($conversation);
    } else {
        $query->chunkById($chunk, function ($conversations) use ($runner, $limit, &$processed): bool {
            foreach ($conversations as $conversation) {
                if (!$runner($conversation)) {
                    return false;
                }
            }

            return $limit <= 0 || $processed < $limit;
        });
    }

    $this->newLine();
    $this->table(
        ['Resultado', 'Valor'],
        [
            ['candidate_count', (string) $candidateCount],
            ['processed', (string) $processed],
            ['created', (string) $created],
            ['updated', (string) $updated],
            ['errors', (string) $errors],
        ]
    );

    $this->info('Backfill de atribución completado.');
    return $errors > 0 ? 1 : 0;
})->purpose('Reconstruye atribución histórica de conversaciones WhatsApp para el dashboard analítico');

Artisan::command('whatsapp:operational-events-backfill
    {--from= : Fecha inicial YYYY-MM-DD}
    {--to= : Fecha final YYYY-MM-DD}
    {--dry-run : Muestra lo que se insertaría sin escribir}', function (): int {
    /** @var WhatsappOperationalEventService $service */
    $service = app(WhatsappOperationalEventService::class);

    $from = trim((string) ($this->option('from') ?? ''));
    $to = trim((string) ($this->option('to') ?? ''));
    $dryRun = (bool) $this->option('dry-run');

    $summary = $service->backfillLegacyHandoffEvents(
        $from !== '' ? $from : null,
        $to !== '' ? $to : null,
        $dryRun
    );

    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Modo', $dryRun ? 'dry-run' : 'write'],
            ['Desde', $from !== '' ? $from : 'sin filtro'],
            ['Hasta', $to !== '' ? $to : 'sin filtro'],
            ['Eventos procesados', (string) ($summary['processed'] ?? 0)],
            ['Eventos creados', (string) ($summary['created'] ?? 0)],
            ['Eventos omitidos/idempotentes', (string) ($summary['skipped'] ?? 0)],
            ['Eventos sin conversation_id', (string) ($summary['missing_conversation'] ?? 0)],
        ]
    );

    $mappings = $summary['mappings'] ?? [];
    if (is_array($mappings) && $mappings !== []) {
        $this->newLine();
        $this->table(
            ['Mapeo', 'Total'],
            collect($mappings)
                ->map(fn ($count, $mapping): array => [(string) $mapping, (string) $count])
                ->values()
                ->all()
        );
    }

    return 0;
})->purpose('Backfill canónico de eventos operacionales WhatsApp desde whatsapp_handoff_events');

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

Artisan::command('whatsapp:handoff-auto-assign
    {--dry-run : Solo muestra candidatos sin asignar (read-only)}
    {--json : Emite resultado completo como JSON (implica dry-run si se combina)}
    {--limit=100 : Máximo de conversaciones calientes a procesar}
    {--max-age-hours=72 : Solo revisa handoffs recientes dentro de esta ventana}', function (): int {
    /** @var WhatsappHandoffAutoAssignService $service */
    $service = app(WhatsappHandoffAutoAssignService::class);

    $dryRun = (bool) $this->option('dry-run');
    $jsonOutput = (bool) $this->option('json');

    try {
        $result = $service->run([
            'dry_run' => $dryRun,
            'limit' => (int) $this->option('limit'),
            'max_age_hours' => (int) $this->option('max-age-hours'),
        ]);

        if ($jsonOutput) {
            $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
            $wouldAssign = array_values(array_filter($rows, static fn (array $r): bool => ($r['status'] ?? '') === 'would_assign'));
            $skipped = array_values(array_filter($rows, static fn (array $r): bool => ($r['status'] ?? '') === 'skipped'));

            $output = [
                'ok'              => empty($result['error']),
                'mode'            => $result['mode'] ?? ($dryRun ? 'dry_run' : 'live'),
                'read_only'       => (bool) ($result['read_only'] ?? $dryRun),
                'limit'           => (int) $this->option('limit'),
                'evaluated'       => (int) ($result['evaluated'] ?? 0),
                'eligible'        => (int) ($result['eligible'] ?? 0),
                'skipped'         => (int) ($result['skipped'] ?? 0),
                'would_assign'    => $wouldAssign,
                'skipped_reasons' => $result['skipped_reasons'] ?? [],
                'skipped_sample'  => array_slice($skipped, 0, 10),
                'db_writes'       => (int) ($result['db_writes'] ?? 0),
            ];
            if (!empty($result['error'])) {
                $output['error'] = (string) $result['error'];
            }
            $this->line((string) json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        }

        if (!empty($result['error'])) {
            $this->warn((string) $result['error']);
        }

        $this->table(
            ['Mode', 'Evaluated', 'Eligible', 'Assigned', 'Would assign', 'Supervisor', 'Skipped', 'DB writes'],
            [[
                (string) ($result['mode'] ?? ($dryRun ? 'dry_run' : 'live')),
                (int) ($result['evaluated'] ?? 0),
                (int) ($result['eligible'] ?? 0),
                (int) ($result['assigned'] ?? 0),
                (int) ($result['would_assign'] ?? 0),
                (int) ($result['supervisor'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['db_writes'] ?? 0),
            ]]
        );

        $skippedReasons = $result['skipped_reasons'] ?? [];
        if (is_array($skippedReasons) && $skippedReasons !== []) {
            $this->table(
                ['Skip reason', 'Count'],
                collect($skippedReasons)->map(fn ($count, $reason): array => [(string) $reason, (int) $count])->values()->all()
            );
        }

        $byTopic = $result['by_topic'] ?? [];
        if (is_array($byTopic) && $byTopic !== []) {
            $this->table(
                ['Topic', 'Count'],
                collect($byTopic)->map(fn ($count, $topic): array => [(string) $topic, (int) $count])->values()->all()
            );
        }

        $rows = array_slice(is_array($result['rows'] ?? null) ? $result['rows'] : [], 0, 25);
        if ($rows !== []) {
            $this->table(
                ['Conversation', 'Handoff', 'Topic', 'Category', 'Bucket', 'Priority', 'Status', 'Assigned to'],
                array_map(static fn (array $row): array => [
                    (int) ($row['conversation_id'] ?? 0),
                    (int) ($row['handoff_id'] ?? 0),
                    (string) ($row['topic'] ?? ''),
                    (string) ($row['category'] ?? ''),
                    (string) ($row['bucket'] ?? ''),
                    (string) ($row['priority'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) data_get($row, 'assigned_to.name', ''),
                ], $rows)
            );
        }

        return 0;
    } catch (\Throwable $e) {
        $this->warn('No fue posible autoasignar handoffs WhatsApp.');
        $this->line($e->getMessage());

        return 1;
    }
})->purpose('Autoasigna oportunidades calientes de WhatsApp a agentes disponibles');

Artisan::command('whatsapp:operational-alerts
    {--date= : Fecha YYYY-MM-DD (por defecto hoy)}
    {--json : Emite resultado completo como JSON}
    {--summary : Emite solo resumen por severidad y tipo}
    {--limit=200 : Máximo de alertas a evaluar}
    {--category=all : Filtrar por categoría: all|captacion|operacion|ambiguo}
    {--severity=all : Filtrar por severidad: all|critical|high|medium|low}', function (): int {
    /** @var \App\Modules\Whatsapp\Services\WhatsappOperationalAlertService $service */
    $service = app(\App\Modules\Whatsapp\Services\WhatsappOperationalAlertService::class);

    $dateOption = trim((string) ($this->option('date') ?? ''));
    $date = $dateOption !== '' ? $dateOption : now()->toDateString();

    try {
        $result = $service->alerts([
            'date'     => $date,
            'category' => (string) ($this->option('category') ?? 'all'),
            'severity' => (string) ($this->option('severity') ?? 'all'),
            'limit'    => (int) $this->option('limit'),
        ]);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        }

        if ((bool) $this->option('summary')) {
            $this->table(
                ['Mode', 'Date', 'Evaluated', 'Alerts total', 'Critical', 'High', 'Medium', 'Low'],
                [[
                    $result['mode'],
                    $result['date'],
                    $result['evaluated'],
                    $result['alerts_total'],
                    $result['summary']['critical'] ?? 0,
                    $result['summary']['high'] ?? 0,
                    $result['summary']['medium'] ?? 0,
                    $result['summary']['low'] ?? 0,
                ]]
            );

            $byType = $result['by_type'] ?? [];
            if (is_array($byType) && $byType !== []) {
                $this->table(
                    ['Alert type', 'Count'],
                    collect($byType)->map(fn ($c, $t): array => [(string) $t, (int) $c])->values()->all()
                );
            }

            return 0;
        }

        // Full table output
        $this->table(
            ['Mode', 'Date', 'Evaluated', 'Alerts total', 'Critical', 'High', 'Medium', 'Low', 'DB writes'],
            [[
                $result['mode'],
                $result['date'],
                $result['evaluated'],
                $result['alerts_total'],
                $result['summary']['critical'] ?? 0,
                $result['summary']['high'] ?? 0,
                $result['summary']['medium'] ?? 0,
                $result['summary']['low'] ?? 0,
                $result['db_writes'],
            ]]
        );

        $byType = $result['by_type'] ?? [];
        if (is_array($byType) && $byType !== []) {
            $this->table(
                ['Alert type', 'Count'],
                collect($byType)->map(fn ($c, $t): array => [(string) $t, (int) $c])->values()->all()
            );
        }

        $alerts = array_slice(is_array($result['alerts'] ?? null) ? $result['alerts'] : [], 0, 30);
        if ($alerts !== []) {
            $this->table(
                ['Severity', 'Type', 'Conv', 'Handoff', 'Topic', 'Category', 'Bucket', 'Wait(m)', 'Agent', 'Suggested action'],
                array_map(static fn (array $a): array => [
                    (string) ($a['severity'] ?? ''),
                    (string) ($a['alert_type'] ?? ''),
                    (int) ($a['conversation_id'] ?? 0),
                    (int) ($a['handoff_id'] ?? 0),
                    (string) ($a['topic'] ?? ''),
                    (string) ($a['category'] ?? ''),
                    (string) ($a['bucket'] ?? ''),
                    (int) ($a['waiting_minutes'] ?? 0),
                    (string) ($a['assigned_user_name'] ?? '—'),
                    (string) ($a['suggested_action'] ?? ''),
                ], $alerts)
            );
        }

        return 0;
    } catch (\Throwable $e) {
        $this->warn('Error generando alertas operacionales WhatsApp.');
        $this->line($e->getMessage());

        return 1;
    }
})->purpose('Motor de alertas operacionales WhatsApp (read-only)');

Artisan::command('whatsapp:rescue-metrics
    {--from= : Fecha inicial YYYY-MM-DD}
    {--to= : Fecha final exclusiva YYYY-MM-DD}
    {--days=7 : Ventana en días si no se envían fechas}', function (): int {
    /** @var WhatsappRescueMetricsService $service */
    $service = app(WhatsappRescueMetricsService::class);

    $fromOption = trim((string) $this->option('from'));
    $toOption = trim((string) $this->option('to'));
    $days = max(1, min(90, (int) $this->option('days')));

    $to = $toOption !== ''
        ? \Illuminate\Support\Carbon::parse($toOption)->startOfDay()
        : now()->startOfDay()->addDay();
    $from = $fromOption !== ''
        ? \Illuminate\Support\Carbon::parse($fromOption)->startOfDay()
        : $to->copy()->subDays($days);

    $metrics = $service->summary($from, $to);

    $this->line('Periodo: ' . data_get($metrics, 'period.from') . ' → ' . data_get($metrics, 'period.to'));

    $this->table(
        ['Metric', 'Value'],
        collect((array) ($metrics['handoffs'] ?? []))
            ->map(fn ($value, $key): array => [(string) $key, (int) $value])
            ->values()
            ->all()
    );

    $hot = (array) ($metrics['hot_opportunities'] ?? []);
    $this->table(
        ['hot_opportunities', 'Value'],
        [
            ['total', (int) ($hot['total'] ?? 0)],
            ['booked', (int) ($hot['booked'] ?? 0)],
        ]
    );

    $reminders = (array) ($metrics['reminders'] ?? []);
    $this->table(
        ['reminders', 'Value'],
        [
            ['sent_to_confirmation', (int) ($reminders['sent_to_confirmation'] ?? 0)],
            ['failed', (int) ($reminders['failed'] ?? 0)],
        ]
    );

    $failureReasons = (array) ($reminders['failure_reasons'] ?? []);
    if ($failureReasons !== []) {
        $this->table(
            ['failure_reason', 'Count'],
            collect($failureReasons)
                ->map(fn ($count, $reason): array => [(string) $reason, (int) $count])
                ->values()
                ->all()
        );
    }

    return 0;
})->purpose('Mide si rescates operativos de WhatsApp terminan en respuesta, confirmación o cita');

Artisan::command('whatsapp:daily-rescue-report
    {--date= : Día a reportar YYYY-MM-DD}
    {--from= : Fecha/hora inicial}
    {--to= : Fecha/hora final exclusiva}
    {--json : Imprime el payload completo en JSON}', function (): int {
    /** @var WhatsappDailyRescueReportService $service */
    $service = app(WhatsappDailyRescueReportService::class);

    $date = trim((string) $this->option('date'));
    $fromOption = trim((string) $this->option('from'));
    $toOption = trim((string) $this->option('to'));

    if ($fromOption !== '' || $toOption !== '') {
        $to = $toOption !== '' ? \Illuminate\Support\Carbon::parse($toOption) : now();
        $from = $fromOption !== '' ? \Illuminate\Support\Carbon::parse($fromOption) : $to->copy()->subDay();
    } else {
        $from = $date !== ''
            ? \Illuminate\Support\Carbon::parse($date)->startOfDay()
            : now()->startOfDay();
        $to = $from->copy()->addDay();
    }

    $report = $service->summary($from, $to, now());

    if ((bool) $this->option('json')) {
        $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $this->line('Reporte diario WhatsApp: ' . data_get($report, 'period.from') . ' → ' . data_get($report, 'period.to'));
    $this->table(
        ['Bucket', 'Abiertas', 'Citas', 'Total', 'Conv.'],
        collect((array) ($report['buckets'] ?? []))
            ->map(fn (array $bucket, string $name): array => [
                $name,
                (int) ($bucket['open'] ?? 0),
                (int) ($bucket['booked'] ?? 0),
                (int) ($bucket['total'] ?? 0),
                ((float) ($bucket['conversion_rate'] ?? 0.0)) . '%',
            ])
            ->values()
            ->all()
    );

    $this->table(
        ['Operación', 'Valor'],
        collect((array) ($report['operations'] ?? []))
            ->map(fn ($value, $key): array => [(string) $key, (int) $value])
            ->values()
            ->all()
    );

    $this->table(
        ['Recordatorios', 'Valor'],
        collect((array) ($report['reminders'] ?? []))
            ->except('failure_reasons')
            ->map(fn ($value, $key): array => [(string) $key, (int) $value])
            ->values()
            ->all()
    );

    $this->table(
        ['Tasa', 'Valor'],
        collect((array) ($report['rates'] ?? []))
            ->map(fn ($value, $key): array => [(string) $key, ((float) $value) . '%'])
            ->values()
            ->all()
    );

    return 0;
})->purpose('Genera el reporte diario de rescate operacional de WhatsApp');

Artisan::command('whatsapp:operational-baseline
    {--date= : Día del snapshot YYYY-MM-DD}
    {--json : Imprime el payload completo en JSON}
    {--persist : Guarda o actualiza el snapshot histórico}', function (): int {
    /** @var WhatsappOperationalBaselineService $service */
    $service = app(WhatsappOperationalBaselineService::class);

    $dateOption = trim((string) $this->option('date'));
    $date = $dateOption !== ''
        ? \Illuminate\Support\Carbon::parse($dateOption)->startOfDay()
        : now()->startOfDay();

    $baseline = $service->baseline($date, now(), (bool) $this->option('persist'));

    if ((bool) $this->option('json')) {
        $this->line((string) json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $this->line('Baseline operacional WhatsApp: ' . data_get($baseline, 'snapshot_date'));
    $this->table(
        ['Bucket', 'Total', 'Sin dueño', 'Asignadas', 'Autoasig.', '1ra resp.', 'Citas', 'Conv.', 'Edad prom.', 'Cola prom.'],
        collect((array) ($baseline['buckets'] ?? []))
            ->map(fn (array $bucket, string $name): array => [
                $name,
                (int) ($bucket['total_conversations'] ?? 0),
                (int) ($bucket['unassigned'] ?? 0),
                (int) ($bucket['assigned'] ?? 0),
                (int) ($bucket['autoassigned'] ?? 0),
                (int) ($bucket['with_first_response'] ?? 0),
                (int) ($bucket['with_booking'] ?? 0),
                ((float) ($bucket['conversion_rate'] ?? 0.0)) . '%',
                ((float) ($bucket['age_average_minutes'] ?? 0.0)) . ' min',
                ((float) ($bucket['queue_wait_average_minutes'] ?? 0.0)) . ' min',
            ])
            ->values()
            ->all()
    );

    $this->table(
        ['Métrica estratégica', 'Valor'],
        [
            ['bookings_after_operational_intervention', (int) data_get($baseline, 'bookings_after_operational_intervention.total', 0)],
            ['reminder_confirmations', (int) data_get($baseline, 'reminders.confirmed', 0)],
            ['reminder_failures', (int) data_get($baseline, 'reminders.failed', 0)],
            ['persisted', (bool) $this->option('persist') ? 'sí' : 'no'],
        ]
    );

    return 0;
})->purpose('Genera la línea base operacional diaria de WhatsApp por buckets');

Artisan::command('whatsapp:operational-decisions
    {--date= : Fecha de evaluación YYYY-MM-DD (default: hoy)}
    {--summary-only : Devuelve solo el bloque summary, sin decisiones individuales}
    {--action= : Filtra por recommended_action}
    {--bucket= : Filtra por bucket (hot_open|hot_needs_template|rescue|backlog|lost)}
    {--priority= : Filtra por priority (high|medium|normal|low)}
    {--risk= : Filtra por risk_level (high|medium|low|closed)}
    {--limit= : Limita la cantidad de decisiones devueltas}
    {--json : Imprime el resultado en JSON}', function (): int {
    /** @var WhatsappOperationalDecisionService $service */
    $service = app(WhatsappOperationalDecisionService::class);

    $dateOption = trim((string) $this->option('date'));
    $asOf = $dateOption !== ''
        ? \Illuminate\Support\Carbon::parse($dateOption)->endOfDay()
        : now();

    $result = $service->evaluate($asOf);

    // ── Filtering ──────────────────────────────────────────────────────────
    $filterAction   = trim((string) ($this->option('action') ?? ''));
    $filterBucket   = trim((string) ($this->option('bucket') ?? ''));
    $filterPriority = trim((string) ($this->option('priority') ?? ''));
    $filterRisk     = trim((string) ($this->option('risk') ?? ''));
    $limitRaw       = $this->option('limit');
    $limit          = $limitRaw !== null && $limitRaw !== '' ? max(1, (int) $limitRaw) : null;
    $summaryOnly    = (bool) $this->option('summary-only');

    $decisions = $result['decisions'];

    if ($filterAction !== '') {
        $decisions = array_values(array_filter($decisions,
            fn (array $d): bool => ($d['recommended_action'] ?? '') === $filterAction));
    }
    if ($filterBucket !== '') {
        $decisions = array_values(array_filter($decisions,
            fn (array $d): bool => ($d['bucket'] ?? '') === $filterBucket));
    }
    if ($filterPriority !== '') {
        $decisions = array_values(array_filter($decisions,
            fn (array $d): bool => ($d['priority'] ?? '') === $filterPriority));
    }
    if ($filterRisk !== '') {
        $decisions = array_values(array_filter($decisions,
            fn (array $d): bool => ($d['risk_level'] ?? '') === $filterRisk));
    }

    // summary reflects the full filtered set (before limit)
    $filteredSummary = $service->summarizeDecisions($decisions);
    $filteredSummary['filter_applied'] = array_filter([
        'action'   => $filterAction ?: null,
        'bucket'   => $filterBucket ?: null,
        'priority' => $filterPriority ?: null,
        'risk'     => $filterRisk ?: null,
        'limit'    => $limit,
    ]);

    // apply limit after summary
    if ($limit !== null) {
        $decisions = array_slice($decisions, 0, $limit);
    }

    // ── Output ─────────────────────────────────────────────────────────────
    if ((bool) $this->option('json')) {
        if ($summaryOnly) {
            $this->line((string) json_encode([
                'date'         => $result['date'],
                'generated_at' => $result['generated_at'],
                'summary'      => $filteredSummary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        }

        $this->line((string) json_encode([
            'date'         => $result['date'],
            'generated_at' => $result['generated_at'],
            'summary'      => $filteredSummary,
            'decisions'    => $decisions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $this->line('Decision Engine Operacional WhatsApp — ' . $result['date']);
    $this->line('');

    $this->table(
        ['Métrica', 'Valor'],
        [
            ['total_evaluated (filtrado)', (int) ($filteredSummary['total_evaluated'] ?? 0)],
            ['eligible_for_autoassign', (int) ($filteredSummary['eligible_for_autoassign'] ?? 0)],
            ['eligible_for_rescue', (int) ($filteredSummary['eligible_for_rescue'] ?? 0)],
            ['eligible_for_supervisor_alert', (int) ($filteredSummary['eligible_for_supervisor_alert'] ?? 0)],
            ['already_converted', (int) ($filteredSummary['already_converted'] ?? 0)],
        ]
    );

    if ($summaryOnly) {
        $this->table(
            ['Acción recomendada', 'Total'],
            collect((array) ($filteredSummary['by_recommended_action'] ?? []))
                ->map(fn (int $count, string $action): array => [$action, $count])
                ->values()
                ->all()
        );

        return 0;
    }

    $this->table(
        ['conv_id', 'bucket', 'action', 'priority', 'risk', 'opportunity', 'autoassign', 'rescue', 'supervisor', 'reason'],
        array_map(fn (array $d): array => [
            (int) ($d['conversation_id'] ?? 0),
            (string) ($d['bucket'] ?? ''),
            (string) ($d['recommended_action'] ?? ''),
            (string) ($d['priority'] ?? ''),
            (string) ($d['risk_level'] ?? ''),
            (string) ($d['opportunity_level'] ?? ''),
            (bool) ($d['eligible_for_autoassign'] ?? false) ? 'sí' : 'no',
            (bool) ($d['eligible_for_rescue'] ?? false) ? 'sí' : 'no',
            (bool) ($d['eligible_for_supervisor_alert'] ?? false) ? 'sí' : 'no',
            mb_strimwidth((string) ($d['reason'] ?? ''), 0, 60, '…'),
        ], $decisions)
    );

    return 0;
})->purpose('Evalúa conversaciones operacionales y produce decisiones recomendadas (solo lectura)');

Artisan::command('whatsapp:operational-queues
    {--date= : Fecha de evaluación YYYY-MM-DD (default: hoy)}
    {--queue= : Cola a mostrar: supervisor|rescue|all (default: all)}
    {--summary-only : Devuelve solo el bloque summary}
    {--limit= : Limita la cantidad de items por cola}
    {--json : Salida JSON}', function (): int {
    /** @var WhatsappOperationalQueueService $service */
    $service = app(WhatsappOperationalQueueService::class);

    $dateOption = trim((string) ($this->option('date') ?? ''));
    $asOf = $dateOption !== ''
        ? \Illuminate\Support\Carbon::parse($dateOption)->endOfDay()
        : now();

    $queueOpt = strtolower(trim((string) ($this->option('queue') ?? 'all')));
    $validQueues = ['assignment', 'supervisor', 'rescue', 'all'];
    if (!in_array($queueOpt, $validQueues, true)) {
        $this->error('Cola inválida: "' . $queueOpt . '". Valores válidos: ' . implode(', ', $validQueues));

        return 1;
    }

    $limitRaw = $this->option('limit');
    $limit    = $limitRaw !== null && $limitRaw !== '' ? max(1, (int) $limitRaw) : null;

    $result = $service->queues($asOf, ['queue' => $queueOpt, 'limit' => $limit]);

    if ((bool) $this->option('json')) {
        if ((bool) $this->option('summary-only')) {
            $this->line((string) json_encode([
                'date'         => $result['date'],
                'generated_at' => $result['generated_at'],
                'summary'      => $result['summary'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        }

        $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    // ── Table output ──────────────────────────────────────────────────────
    $this->line('Colas Operacionales WhatsApp — ' . $result['date']);
    $this->line('');

    if ((bool) $this->option('summary-only') || $queueOpt === 'all') {
        $summary = $result['summary'];
        $this->line('── Summary ─────────────────────────────────────────────────');
        $this->table(
            ['Cola', 'Métrica', 'Valor'],
            [
                ['assignment', 'total', (int) ($summary['assignment_queue']['total'] ?? 0)],
                ['assignment', 'high_risk', (int) ($summary['assignment_queue']['high_risk'] ?? 0)],
                ['assignment', 'eligible_for_autoassign', (int) ($summary['assignment_queue']['eligible_for_autoassign'] ?? 0)],
                ['supervisor', 'total', (int) ($summary['supervisor_queue']['total'] ?? 0)],
                ['supervisor', 'high_risk', (int) ($summary['supervisor_queue']['high_risk'] ?? 0)],
                ['supervisor', 'over_sla', (int) ($summary['supervisor_queue']['over_sla'] ?? 0)],
                ['rescue', 'total', (int) ($summary['rescue_queue']['total'] ?? 0)],
                ['rescue', 'rescue_followup', (int) ($summary['rescue_queue']['rescue_followup'] ?? 0)],
                ['rescue', 'send_template_or_review', (int) ($summary['rescue_queue']['send_template_or_review'] ?? 0)],
                ['no_action', 'converted', (int) ($summary['no_action']['converted'] ?? 0)],
                ['no_action', 'already_handled', (int) ($summary['no_action']['already_handled'] ?? 0)],
                ['no_action', 'backlog', (int) ($summary['no_action']['backlog'] ?? 0)],
                ['no_action', 'lost', (int) ($summary['no_action']['lost'] ?? 0)],
            ]
        );

        if ((bool) $this->option('summary-only')) {
            return 0;
        }
    }

    if (in_array($queueOpt, ['assignment', 'all'], true)) {
        $items = $result['queues']['assignment'] ?? $result['items'] ?? [];
        if ($items !== []) {
            $this->line('── Assignment Queue ─────────────────────────────────────────');
            $this->table(
                ['conv_id', 'bucket', 'priority', 'risk', 'autoassign', 'has_booking', 'reason'],
                array_map(fn (array $item): array => [
                    (int) ($item['conversation_id'] ?? 0),
                    (string) ($item['bucket'] ?? ''),
                    (string) ($item['priority'] ?? ''),
                    (string) ($item['risk_level'] ?? ''),
                    (bool) ($item['eligible_for_autoassign'] ?? false) ? 'sí' : 'no',
                    (bool) ($item['has_attributed_booking'] ?? false) ? 'sí' : 'no',
                    mb_strimwidth((string) ($item['reason'] ?? ''), 0, 55, '…'),
                ], $items)
            );
        }
    }

    if (in_array($queueOpt, ['supervisor', 'all'], true)) {
        $items = $result['queues']['supervisor'] ?? $result['items'] ?? [];
        if ($items !== []) {
            $this->line('── Supervisor Queue ─────────────────────────────────────────');
            $this->table(
                ['conv_id', 'bucket', 'priority', 'risk', 'autoasign', 'supervisor', 'has_booking', 'reason'],
                array_map(fn (array $item): array => [
                    (int) ($item['conversation_id'] ?? 0),
                    (string) ($item['bucket'] ?? ''),
                    (string) ($item['priority'] ?? ''),
                    (string) ($item['risk_level'] ?? ''),
                    (bool) ($item['eligible_for_autoassign'] ?? false) ? 'sí' : 'no',
                    (bool) ($item['eligible_for_supervisor_alert'] ?? false) ? 'sí' : 'no',
                    (bool) ($item['has_attributed_booking'] ?? false) ? 'sí' : 'no',
                    mb_strimwidth((string) ($item['reason'] ?? ''), 0, 55, '…'),
                ], $items)
            );
        }
    }

    if (in_array($queueOpt, ['rescue', 'all'], true)) {
        $items = $result['queues']['rescue'] ?? $result['items'] ?? [];
        if ($items !== []) {
            $this->line('── Rescue Queue ─────────────────────────────────────────────');
            $this->table(
                ['conv_id', 'bucket', 'action', 'priority', 'risk', 'rescue', 'has_booking', 'reason'],
                array_map(fn (array $item): array => [
                    (int) ($item['conversation_id'] ?? 0),
                    (string) ($item['bucket'] ?? ''),
                    (string) ($item['recommended_action'] ?? ''),
                    (string) ($item['priority'] ?? ''),
                    (string) ($item['risk_level'] ?? ''),
                    (bool) ($item['eligible_for_rescue'] ?? false) ? 'sí' : 'no',
                    (bool) ($item['has_attributed_booking'] ?? false) ? 'sí' : 'no',
                    mb_strimwidth((string) ($item['reason'] ?? ''), 0, 55, '…'),
                ], $items)
            );
        }
    }

    return 0;
})->purpose('Colas operacionales de supervisor y rescate — solo lectura');

Artisan::command('whatsapp:operational-queue-audit
    {--date= : Fecha de evaluación YYYY-MM-DD (default: hoy)}
    {--json : Salida JSON}', function (): int {
    /** @var WhatsappOperationalDecisionService $decisionService */
    $decisionService = app(WhatsappOperationalDecisionService::class);
    /** @var WhatsappOperationalQueueService $queueService */
    $queueService = app(WhatsappOperationalQueueService::class);

    $dateOption = trim((string) ($this->option('date') ?? ''));
    $asOf = $dateOption !== ''
        ? \Illuminate\Support\Carbon::parse($dateOption)->endOfDay()
        : now()->endOfDay();

    // ── Decision Engine ───────────────────────────────────────────────────
    $evalResult  = $decisionService->evaluate($asOf);
    $decisions   = $evalResult['decisions'];
    $decisionDate = $evalResult['date'];

    $decisionByAction = [];
    $decisionConvIds  = [];
    foreach ($decisions as $d) {
        $action = (string) ($d['recommended_action'] ?? 'unknown');
        $decisionByAction[$action] = ($decisionByAction[$action] ?? 0) + 1;
        $decisionConvIds[] = (int) ($d['conversation_id'] ?? 0);
    }

    // ── Queue Service — classify each decision into its queue bucket ───────
    $assignmentItems = $queueService->buildAssignmentQueue($decisions);
    $supervisorItems = $queueService->buildSupervisorQueue($decisions);
    $rescueItems     = $queueService->buildRescueQueue($decisions);

    // Actions that go into no_action bucket in queues
    $noActionActions = [
        WhatsappOperationalDecisionService::ACTION_NO_ACTION_CONVERTED,
        WhatsappOperationalDecisionService::ACTION_ALREADY_HANDLED,
        WhatsappOperationalDecisionService::ACTION_HOLD_BACKLOG,
        WhatsappOperationalDecisionService::ACTION_NO_ACTION_LOST,
    ];

    $assignmentConvIds = array_column($assignmentItems, 'conversation_id');
    $supervisorConvIds = array_column($supervisorItems, 'conversation_id');
    $rescueConvIds     = array_column($rescueItems, 'conversation_id');

    $noActionConvIds = [];
    foreach ($decisions as $d) {
        if (in_array($d['recommended_action'] ?? '', $noActionActions, true)) {
            $noActionConvIds[] = (int) ($d['conversation_id'] ?? 0);
        }
    }

    $allAccountedConvIds = array_unique(array_merge(
        $assignmentConvIds,
        $supervisorConvIds,
        $rescueConvIds,
        $noActionConvIds
    ));

    $assignmentTotal  = count($assignmentItems);
    $supervisorTotal  = count($supervisorItems);
    $rescueTotal      = count($rescueItems);
    $noActionTotal    = count($noActionConvIds);
    $totalAccounted   = count($allAccountedConvIds);

    // ── Diff ──────────────────────────────────────────────────────────────
    $unaccountedConvIds = array_values(array_diff($decisionConvIds, $allAccountedConvIds));
    $diffCount = count($unaccountedConvIds);

    // Group unaccounted by action
    $unaccountedByAction = [];
    foreach ($decisions as $d) {
        $convId = (int) ($d['conversation_id'] ?? 0);
        if (in_array($convId, $unaccountedConvIds, true)) {
            $action = (string) ($d['recommended_action'] ?? 'unknown');
            $unaccountedByAction[$action] = ($unaccountedByAction[$action] ?? 0) + 1;
        }
    }

    // Build explanation strings
    $explanations = [];
    if ($diffCount === 0) {
        $explanations[] = 'All decisions are accounted for in supervisor, rescue, or no_action buckets.';
    } else {
        foreach ($unaccountedByAction as $action => $count) {
            $explanations[] = "Action '{$action}': {$count} conversation(s) not mapped to any queue bucket.";
        }
        $explanations[] = "Total unaccounted: {$diffCount} conversation(s). "
            . 'These decisions exist in the Decision Engine but are not classified into supervisor, rescue, or no_action queues.';
    }

    $payload = [
        'date'            => $decisionDate,
        'generated_at'    => now()->format('Y-m-d H:i:s'),
        'decision_engine' => [
            'total'     => count($decisions),
            'by_action' => $decisionByAction,
        ],
        'queues' => [
            'total_accounted'  => $totalAccounted,
            'assignment_total' => $assignmentTotal,
            'supervisor_total' => $supervisorTotal,
            'rescue_total'     => $rescueTotal,
            'no_action_total'  => $noActionTotal,
        ],
        'diff' => [
            'count'            => $diffCount,
            'by_action'        => $unaccountedByAction,
            'conversation_ids' => $unaccountedConvIds,
            'explanation'      => $explanations,
        ],
    ];

    if ((bool) $this->option('json')) {
        $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    // ── Table output ──────────────────────────────────────────────────────
    $this->line('Queue Consistency Audit — ' . $decisionDate);
    $this->line('');

    $this->table(
        ['Sección', 'Métrica', 'Valor'],
        [
            ['decision_engine', 'total', count($decisions)],
            ['queues', 'total_accounted', $totalAccounted],
            ['queues', 'assignment_total', $assignmentTotal],
            ['queues', 'supervisor_total', $supervisorTotal],
            ['queues', 'rescue_total', $rescueTotal],
            ['queues', 'no_action_total', $noActionTotal],
            ['diff', 'count', $diffCount],
        ]
    );

    if ($diffCount > 0) {
        $this->line('── Unaccounted by action ─────────────────────────────────────');
        $this->table(
            ['Action', 'Count'],
            array_map(
                fn (string $action, int $count): array => [$action, $count],
                array_keys($unaccountedByAction),
                array_values($unaccountedByAction)
            )
        );

        $this->line('── Explanation ──────────────────────────────────────────────');
        foreach ($explanations as $line) {
            $this->line('  • ' . $line);
        }
    } else {
        $this->line('✓ No discrepancy found — all decisions accounted for.');
    }

    return 0;
})->purpose('Audita consistencia entre Decision Engine y Operational Queues — solo lectura');

Artisan::command('whatsapp:operational-attribution
    {--date= : Día a recalcular YYYY-MM-DD}
    {--from= : Inicio explícito YYYY-MM-DD HH:MM:SS}
    {--to= : Fin explícito YYYY-MM-DD HH:MM:SS}
    {--json : Imprime el resumen en JSON}', function (): int {
    /** @var WhatsappOperationalAttributionService $service */
    $service = app(WhatsappOperationalAttributionService::class);

    $fromOption = trim((string) $this->option('from'));
    $toOption = trim((string) $this->option('to'));
    $dateOption = trim((string) $this->option('date'));

    if ($fromOption !== '' || $toOption !== '') {
        $from = $fromOption !== ''
            ? \Illuminate\Support\Carbon::parse($fromOption)
            : now()->startOfDay();
        $to = $toOption !== ''
            ? \Illuminate\Support\Carbon::parse($toOption)
            : $from->copy()->addDay();
    } else {
        $from = $dateOption !== ''
            ? \Illuminate\Support\Carbon::parse($dateOption)->startOfDay()
            : now()->startOfDay();
        $to = $from->copy()->addDay();
    }

    $summary = $service->refresh($from, $to);
    $payload = [
        'period' => [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ],
        'summary' => $summary,
    ];

    if ((bool) $this->option('json')) {
        $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $this->line('Atribución operacional WhatsApp');
    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Desde', $payload['period']['from']],
            ['Hasta', $payload['period']['to']],
            ['Bookings procesadas', (string) $summary['processed']],
            ['Atribuciones creadas', (string) $summary['created']],
            ['Atribuciones actualizadas', (string) $summary['updated']],
            ['Bookings sin atribución', (string) $summary['skipped']],
        ]
    );

    return 0;
})->purpose('Calcula atribuciones persistentes entre eventos operacionales y citas WhatsApp');

Artisan::command('whatsapp:attribution-audit
    {--from= : Inicio del período YYYY-MM-DD}
    {--to= : Fin del período YYYY-MM-DD}
    {--json : Salida JSON completa}', function (): int {

    // ── Event types that qualify as operational interventions (mirrors service) ──
    $validEventTypes = [
        'requested', 'handoff_created', 'requeued', 'handoff_requeued',
        'auto_assigned', 'assigned', 'agent_taken',
        'first_response_after_assignment', 'abandonment_escalated',
        'template_rescue', 'template_rescue_sent',
        'reminder_rescue', 'reminder_rescue_sent', 'supervisor_alerted',
    ];

    $fromOption = trim((string) $this->option('from'));
    $toOption   = trim((string) $this->option('to'));
    $from = $fromOption !== '' ? \Illuminate\Support\Carbon::parse($fromOption)->startOfDay() : now()->startOfDay();
    $to   = $toOption   !== '' ? \Illuminate\Support\Carbon::parse($toOption)->endOfDay()     : $from->copy()->endOfDay();

    $this->line("Auditoría de atribución operacional");
    $this->line("Período: {$from->format('Y-m-d H:i:s')} → {$to->format('Y-m-d H:i:s')}");
    $this->line(str_repeat('─', 80));

    // ── Fetch bookings in range ──
    $bookings = \Illuminate\Support\Facades\DB::table('whatsapp_sigcenter_bookings as b')
        ->leftJoin('whatsapp_conversations as c', 'c.id', '=', 'b.conversation_id')
        ->select([
            'b.id as booking_id',
            'b.conversation_id as booking_conversation_id',
            'b.status as booking_status',
            \Illuminate\Support\Facades\DB::raw('COALESCE(b.wa_number, c.wa_number) AS booking_wa_number'),
            \Illuminate\Support\Facades\DB::raw('COALESCE(b.patient_hc_number, c.patient_hc_number) AS booking_patient_hc_number'),
            \Illuminate\Support\Facades\DB::raw('COALESCE(b.booked_at, b.created_at) AS booking_at'),
            'c.display_name as conv_display_name',
            'c.needs_human as conv_needs_human',
        ])
        ->whereIn('b.status', ['created', 'confirmed'])
        ->whereRaw('COALESCE(b.booked_at, b.created_at) >= ?', [$from->format('Y-m-d H:i:s')])
        ->whereRaw('COALESCE(b.booked_at, b.created_at) < ?', [$to->format('Y-m-d H:i:s')])
        ->orderBy('b.id')
        ->get();

    if ($bookings->isEmpty()) {
        $this->warn("No se encontraron citas en el período.");
        return 0;
    }

    $this->line("Citas encontradas: {$bookings->count()}");
    $this->newLine();

    $auditRows = [];
    $causas    = [];

    foreach ($bookings as $booking) {
        $bookingAt     = \Carbon\CarbonImmutable::parse((string) $booking->booking_at);
        $conversationId = $booking->booking_conversation_id !== null ? (int) $booking->booking_conversation_id : null;
        $waNumber       = trim((string) ($booking->booking_wa_number ?? ''));
        $hcNumber       = trim((string) ($booking->booking_patient_hc_number ?? ''));

        // ── Diagnose each of the 3 attribution strategies ──
        $strategies = [];

        // 1. Same conversation (7-day window)
        if ($conversationId !== null) {
            $allConvEvents = \Illuminate\Support\Facades\DB::table('whatsapp_handoff_events as e')
                ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
                ->where('h.conversation_id', $conversationId)
                ->whereIn('e.event_type', $validEventTypes)
                ->select(['e.id', 'e.event_type', 'e.created_at', 'h.id as handoff_id'])
                ->orderByDesc('e.created_at')
                ->get();

            if ($allConvEvents->isEmpty()) {
                $strategies['same_conversation_7d'] = [
                    'result'  => 'NO_EVENTS',
                    'reason'  => 'No hay eventos operacionales en ningún handoff de esta conversación',
                    'count'   => 0,
                    'closest' => null,
                ];
            } else {
                $inWindow = $allConvEvents->filter(function ($e) use ($bookingAt) {
                    $eAt = \Carbon\CarbonImmutable::parse((string) $e->created_at);
                    return $eAt >= $bookingAt->subDays(7) && $eAt < $bookingAt;
                });
                $afterBooking = $allConvEvents->filter(function ($e) use ($bookingAt) {
                    return \Carbon\CarbonImmutable::parse((string) $e->created_at) >= $bookingAt;
                });
                $closest = $allConvEvents->first();
                $closestAt = $closest ? \Carbon\CarbonImmutable::parse((string) $closest->created_at) : null;
                $diffMin   = $closestAt ? (int) $closestAt->diffInMinutes($bookingAt) : null;
                $diffSign  = $closestAt && $closestAt < $bookingAt ? '-' : '+';

                if ($inWindow->isNotEmpty()) {
                    $strategies['same_conversation_7d'] = [
                        'result'  => 'FOUND',
                        'reason'  => "Encontró {$inWindow->count()} evento(s) dentro de la ventana de 7 días",
                        'count'   => $inWindow->count(),
                        'closest' => $closest ? (array) $closest : null,
                    ];
                } elseif ($afterBooking->isNotEmpty() && $inWindow->isEmpty()) {
                    $strategies['same_conversation_7d'] = [
                        'result'  => 'EVENTS_AFTER_BOOKING',
                        'reason'  => "Hay {$afterBooking->count()} evento(s) pero todos POSTERIORES a la cita (evento posterior no atribuye)",
                        'count'   => $allConvEvents->count(),
                        'closest' => $closest ? (array) $closest : null,
                        'diff'    => "{$diffSign}{$diffMin} min desde la cita",
                    ];
                } else {
                    $strategies['same_conversation_7d'] = [
                        'result'  => 'OUTSIDE_WINDOW',
                        'reason'  => "Hay {$allConvEvents->count()} evento(s) pero fuera de la ventana de 7 días previos",
                        'count'   => $allConvEvents->count(),
                        'closest' => $closest ? (array) $closest : null,
                        'diff'    => "{$diffSign}{$diffMin} min desde la cita",
                    ];
                }
            }
        } else {
            $strategies['same_conversation_7d'] = [
                'result' => 'NO_CONVERSATION',
                'reason' => 'booking.conversation_id es NULL — cita sin conversación vinculada',
                'count'  => 0,
                'closest' => null,
            ];
        }

        // 2. Same wa_number (72h window)
        if ($waNumber !== '') {
            $allWaEvents = \Illuminate\Support\Facades\DB::table('whatsapp_handoff_events as e')
                ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
                ->leftJoin('whatsapp_conversations as c', 'c.id', '=', 'h.conversation_id')
                ->where('c.wa_number', $waNumber)
                ->whereIn('e.event_type', $validEventTypes)
                ->select(['e.id', 'e.event_type', 'e.created_at', 'h.conversation_id'])
                ->orderByDesc('e.created_at')
                ->get();

            if ($allWaEvents->isEmpty()) {
                $strategies['same_wa_number_72h'] = [
                    'result' => 'NO_EVENTS',
                    'reason' => "No hay eventos operacionales para wa_number={$waNumber} en ningún período",
                    'count'  => 0,
                    'closest' => null,
                ];
            } else {
                $inWindow = $allWaEvents->filter(function ($e) use ($bookingAt) {
                    $eAt = \Carbon\CarbonImmutable::parse((string) $e->created_at);
                    return $eAt >= $bookingAt->subHours(72) && $eAt < $bookingAt;
                });
                $closest  = $allWaEvents->first();
                $closestAt = $closest ? \Carbon\CarbonImmutable::parse((string) $closest->created_at) : null;
                $diffMin   = $closestAt ? (int) $closestAt->diffInMinutes($bookingAt) : null;
                $diffSign  = $closestAt && $closestAt < $bookingAt ? '-' : '+';

                if ($inWindow->isNotEmpty()) {
                    $strategies['same_wa_number_72h'] = [
                        'result' => 'FOUND',
                        'reason' => "Encontró {$inWindow->count()} evento(s) dentro de 72h",
                        'count'  => $inWindow->count(),
                        'closest' => $closest ? (array) $closest : null,
                    ];
                } else {
                    $strategies['same_wa_number_72h'] = [
                        'result' => 'OUTSIDE_WINDOW',
                        'reason' => "Hay {$allWaEvents->count()} evento(s) pero fuera de 72h previas. Evento más cercano: {$diffSign}{$diffMin} min",
                        'count'  => $allWaEvents->count(),
                        'closest' => $closest ? (array) $closest : null,
                        'diff'   => "{$diffSign}{$diffMin} min desde la cita",
                    ];
                }
            }
        } else {
            $strategies['same_wa_number_72h'] = [
                'result' => 'NO_WA_NUMBER',
                'reason' => 'wa_number vacío o NULL en booking y conversación',
                'count'  => 0,
                'closest' => null,
            ];
        }

        // 3. Same HC number (72h window)
        if ($hcNumber !== '') {
            $allHcEvents = \Illuminate\Support\Facades\DB::table('whatsapp_handoff_events as e')
                ->join('whatsapp_handoffs as h', 'h.id', '=', 'e.handoff_id')
                ->leftJoin('whatsapp_conversations as c', 'c.id', '=', 'h.conversation_id')
                ->where('c.patient_hc_number', $hcNumber)
                ->whereIn('e.event_type', $validEventTypes)
                ->select(['e.id', 'e.event_type', 'e.created_at', 'h.conversation_id'])
                ->orderByDesc('e.created_at')
                ->get();

            if ($allHcEvents->isEmpty()) {
                $strategies['same_patient_hc_number_72h'] = [
                    'result' => 'NO_EVENTS',
                    'reason' => "No hay eventos operacionales para hc_number={$hcNumber}",
                    'count'  => 0,
                    'closest' => null,
                ];
            } else {
                $inWindow = $allHcEvents->filter(function ($e) use ($bookingAt) {
                    $eAt = \Carbon\CarbonImmutable::parse((string) $e->created_at);
                    return $eAt >= $bookingAt->subHours(72) && $eAt < $bookingAt;
                });
                $closest  = $allHcEvents->first();
                $closestAt = $closest ? \Carbon\CarbonImmutable::parse((string) $closest->created_at) : null;
                $diffMin   = $closestAt ? (int) $closestAt->diffInMinutes($bookingAt) : null;
                $diffSign  = $closestAt && $closestAt < $bookingAt ? '-' : '+';

                if ($inWindow->isNotEmpty()) {
                    $strategies['same_patient_hc_number_72h'] = [
                        'result' => 'FOUND',
                        'reason' => "Encontró {$inWindow->count()} evento(s) dentro de 72h",
                        'count'  => $inWindow->count(),
                        'closest' => $closest ? (array) $closest : null,
                    ];
                } else {
                    $strategies['same_patient_hc_number_72h'] = [
                        'result' => 'OUTSIDE_WINDOW',
                        'reason' => "Hay {$allHcEvents->count()} evento(s) pero fuera de 72h. Evento más cercano: {$diffSign}{$diffMin} min",
                        'count'  => $allHcEvents->count(),
                        'closest' => $closest ? (array) $closest : null,
                        'diff'   => "{$diffSign}{$diffMin} min",
                    ];
                }
            }
        } else {
            $strategies['same_patient_hc_number_72h'] = [
                'result' => 'NO_HC_NUMBER',
                'reason' => 'patient_hc_number vacío o NULL en booking y conversación',
                'count'  => 0,
                'closest' => null,
            ];
        }

        // ── Determine final verdict ──
        $attributed = collect($strategies)->contains(fn ($s) => $s['result'] === 'FOUND');
        $verdict    = $attributed ? 'ATRIBUIDA' : 'NO ATRIBUIDA';

        // Determine primary rejection cause
        $cause = 'SIN_CAUSA';
        if (!$attributed) {
            $results = collect($strategies)->pluck('result');
            if ($results->contains('EVENTS_AFTER_BOOKING')) {
                $cause = 'EVENTOS_POSTERIORES_A_CITA';
            } elseif ($results->contains('OUTSIDE_WINDOW')) {
                $cause = 'FUERA_DE_VENTANA';
            } elseif ($results->contains('NO_EVENTS') && !$results->contains('NO_CONVERSATION') && !$results->contains('NO_WA_NUMBER') && !$results->contains('NO_HC_NUMBER')) {
                $cause = 'SIN_EVENTOS_OPERACIONALES';
            } elseif ($results->every(fn ($r) => in_array($r, ['NO_CONVERSATION', 'NO_WA_NUMBER', 'NO_HC_NUMBER', 'NO_EVENTS']))) {
                $cause = 'SIN_IDENTIDAD_VINCULADA';
            } else {
                $cause = 'SIN_EVENTOS_EN_VENTANA';
            }
        }
        $causas[] = $cause;

        $auditRows[] = [
            'booking_id'      => $booking->booking_id,
            'status'          => $booking->booking_status,
            'conversation_id' => $conversationId,
            'conv_name'       => $booking->conv_display_name ?? '—',
            'wa_number'       => $waNumber ?: '—',
            'hc_number'       => $hcNumber ?: '—',
            'booking_at'      => $bookingAt->format('Y-m-d H:i:s'),
            'verdict'         => $verdict,
            'cause'           => $cause,
            'strategies'      => $strategies,
        ];

        // ── Print per-booking report ──
        $this->line("┌─ Booking #{$booking->booking_id} ─── {$verdict} ─── Causa: {$cause}");
        $this->line("│  Estado:    {$booking->booking_status}");
        $this->line("│  Cita:      {$bookingAt->format('Y-m-d H:i:s')}");
        $this->line("│  Conv:      " . ($conversationId ? "#{$conversationId} ({$booking->conv_display_name})" : 'NULL — sin conversación vinculada'));
        $this->line("│  WA:        " . ($waNumber ?: 'NULL'));
        $this->line("│  HC:        " . ($hcNumber ?: 'NULL'));
        $this->newLine();

        foreach ($strategies as $strategy => $info) {
            $icon = match ($info['result']) {
                'FOUND'                => '✓',
                'NO_EVENTS'            => '✗',
                'NO_CONVERSATION'      => '○',
                'NO_WA_NUMBER'         => '○',
                'NO_HC_NUMBER'         => '○',
                'OUTSIDE_WINDOW'       => '⊘',
                'EVENTS_AFTER_BOOKING' => '⚠',
                default                => '?',
            };
            $this->line("│  [{$icon}] {$strategy}");
            $this->line("│      → {$info['reason']}");
            if (!empty($info['closest'])) {
                $ev = $info['closest'];
                $this->line("│      Evento más cercano: #{$ev['id']} tipo={$ev['event_type']} at={$ev['created_at']}");
            }
        }
        $this->line("└" . str_repeat('─', 79));
        $this->newLine();
    }

    // ── Summary ──
    $this->line("═══ RESUMEN ═══════════════════════════════════════════════════════════════════");
    $this->table(
        ['Causa de no atribución', 'Citas'],
        collect($causas)->countBy()->map(fn ($n, $k) => [$k, $n])->values()->all()
    );

    // Dominant cause
    $dominant = collect($causas)->countBy()->sortDesc()->keys()->first();
    $this->newLine();
    $this->line("Principal causa: {$dominant}");

    // Recommendation
    $this->newLine();
    $this->line("─── Recomendación mínima ───────────────────────────────────────────────────────");
    match ($dominant) {
        'EVENTOS_POSTERIORES_A_CITA' => $this->line(
            "Las citas se registran ANTES de que el evento operacional ocurra.\n" .
            "Modificación mínima: permitir también eventos POSTERIORES a la cita\n" .
            "dentro de una ventana corta (ej. +2h) para capturar confirmaciones\n" .
            "que llegan después del booking."
        ),
        'FUERA_DE_VENTANA' => $this->line(
            "Hay eventos operacionales pero más de 7 días / 72h antes de la cita.\n" .
            "Modificación mínima: ampliar ventana de same_conversation a 14d\n" .
            "y same_wa / same_hc a 7d (168h), aceptando menor confianza."
        ),
        'SIN_EVENTOS_OPERACIONALES' => $this->line(
            "Las conversaciones vinculadas no tienen ningún evento operacional registrado.\n" .
            "Modificación mínima: revisar si el scraper/bot está emitiendo eventos\n" .
            "handoff_created / auto_assigned correctamente."
        ),
        'SIN_IDENTIDAD_VINCULADA' => $this->line(
            "Las citas no tienen conversation_id, wa_number ni hc_number usables.\n" .
            "Modificación mínima: asegurar que el proceso de booking\n" .
            "persista wa_number y patient_hc_number en whatsapp_sigcenter_bookings."
        ),
        default => $this->line(
            "Revisar los detalles por cita para determinar la acción correcta."
        ),
    };

    if ((bool) $this->option('json')) {
        $this->newLine();
        $this->line((string) json_encode(['bookings' => $auditRows, 'dominant_cause' => $dominant], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return 0;
})->purpose('Auditoría diagnóstica: explica por qué cada cita no pudo atribuirse a un evento operacional');

Artisan::command('whatsapp:monitor-abandonment
    {--dry-run : Solo muestra conversaciones candidatas sin encolarlas}
    {--limit=100 : Máximo de conversaciones a revisar/encolar}
    {--max-age-hours=72 : Solo vigila sesiones recientes dentro de esta ventana}', function (): int {
    /** @var ConversationAbandonmentMonitorService $service */
    $service = app(ConversationAbandonmentMonitorService::class);

    try {
        $result = $service->scan([
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => (int) $this->option('limit'),
            'max_age_hours' => (int) $this->option('max-age-hours'),
        ]);

        if (!empty($result['error'])) {
            $this->warn((string) $result['error']);
        }

        $this->table(
            ['Scanned', 'Candidates', 'Nudged', 'Closed', 'Escalated', 'Skipped'],
            [[
                (int) ($result['scanned'] ?? 0),
                (int) ($result['candidates'] ?? 0),
                (int) ($result['nudged'] ?? 0),
                (int) ($result['closed'] ?? 0),
                (int) ($result['enqueued'] ?? 0),
                (int) ($result['skipped'] ?? 0),
            ]]
        );

        $rows = array_map(static fn (array $row): array => [
            (string) ($row['conversation_id'] ?? ''),
            (string) ($row['state_label'] ?? $row['state'] ?? ''),
            (string) ($row['idle_minutes'] ?? ''),
            (string) ($row['threshold_minutes'] ?? ''),
            (string) ($row['action'] ?? ''),
            (string) ($row['patient'] ?? ''),
            (string) ($row['wa_number'] ?? ''),
        ], $result['rows'] ?? []);

        if ($rows !== []) {
            $this->newLine();
            $this->table(
                ['Conversation', 'Estado', 'Min inactivo', 'Umbral', 'Acción', 'Paciente', 'WA'],
                $rows
            );
        }

        return 0;
    } catch (\Throwable $e) {
        $this->warn('No fue posible ejecutar el monitor de abandono con la DB configurada.');
        $this->line($e->getMessage());

        return 0;
    }
})->purpose('Detecta sesiones estancadas del bot, envía nudge y cierra o escala según criticidad');

Artisan::command('whatsapp:abandonment-audit
    {--max-age-hours=72 : Solo revisa sesiones recientes dentro de esta ventana}', function (): int {
    /** @var ConversationAbandonmentMonitorService $service */
    $service = app(ConversationAbandonmentMonitorService::class);

    try {
        $result = $service->audit([
            'max_age_hours' => (int) $this->option('max-age-hours'),
        ]);

        if (!empty($result['error'])) {
            $this->warn((string) $result['error']);
        }

        $sessionRows = array_map(static fn (array $row): array => [
            (string) ($row['state_label'] ?? $row['state'] ?? ''),
            (string) ($row['total'] ?? 0),
            (string) ($row['over_threshold'] ?? 0),
            (string) ($row['nudged'] ?? 0),
            (string) ($row['closed'] ?? 0),
            (string) ($row['escalated'] ?? 0),
        ], $result['sessions'] ?? []);

        if ($sessionRows !== []) {
            $this->table(
                ['Estado', 'Total', 'Fuera SLA', 'Nudged', 'Closed', 'Escalated'],
                $sessionRows
            );
        }

        $handoffs = is_array($result['handoffs'] ?? null) ? $result['handoffs'] : [];
        if ($handoffs !== []) {
            $this->newLine();
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Handoffs activos', (string) ($handoffs['active'] ?? 0)],
                    ['En cola', (string) ($handoffs['queued'] ?? 0)],
                    ['Asignados', (string) ($handoffs['assigned'] ?? 0)],
                    ['>24h', (string) ($handoffs['older_than_24h'] ?? 0)],
                ]
            );

            $topicRows = [];
            foreach ((array) ($handoffs['topics'] ?? []) as $topic => $count) {
                $topicRows[] = [(string) $topic, (string) $count];
            }

            if ($topicRows !== []) {
                $this->newLine();
                $this->table(['Topic', 'Count'], $topicRows);
            }
        }

        return 0;
    } catch (\Throwable $e) {
        $this->warn('No fue posible ejecutar la auditoría de abandono con la DB configurada.');
        $this->line($e->getMessage());

        return 0;
    }
})->purpose('Audita abandono del bot y backlog humano actual');

Artisan::command('whatsapp:appointment-reminders
    {window : Ventana a ejecutar (24h o 2h)}
    {--dry-run : Solo muestra candidatos sin enviar}
    {--limit=200 : Máximo de recordatorios a evaluar}
    {--for-date= : Fuerza una fecha YYYY-MM-DD en lugar de la ventana natural}
    {--override-wa= : Envía al número indicado en formato 593... en lugar del paciente real}
    {--ignore-window : Ignora la validación de 24h/2h y usa solo la fecha indicada}
    {--first=0 : Toma solo los primeros N eventos del día filtrado}', function (): int {
    /** @var WhatsappAppointmentReminderService $service */
    $service = app(WhatsappAppointmentReminderService::class);

    try {
        $result = $service->dispatchWindow(
            trim((string) $this->argument('window')),
            (bool) $this->option('dry-run'),
            (int) $this->option('limit'),
            [
                'for_date' => trim((string) $this->option('for-date')),
                'override_wa_number' => trim((string) $this->option('override-wa')),
                'ignore_window' => (bool) $this->option('ignore-window'),
                'first_only' => (int) $this->option('first'),
            ]
        );

        if (!empty($result['error'])) {
            $this->warn((string) $result['error']);
        }

        $this->table(
            ['Scanned', 'Candidates', 'Sent', 'Failed', 'Skipped'],
            [[
                (int) ($result['scanned'] ?? 0),
                (int) ($result['candidates'] ?? 0),
                (int) ($result['sent'] ?? 0),
                (int) ($result['failed'] ?? 0),
                (int) ($result['skipped'] ?? 0),
            ]]
        );

        $rows = array_map(static fn (array $row): array => [
            (string) ($row['form_id'] ?? ''),
            (string) ($row['hc_number'] ?? ''),
            (string) ($row['source_type'] ?? ''),
            (string) ($row['patient_name'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['wa_number'] ?? ''),
            (string) ($row['reason'] ?? ''),
        ], $result['rows'] ?? []);

        if ($rows !== []) {
            $this->newLine();
            $this->table(
                ['Form', 'HC', 'Tipo', 'Paciente', 'Estado', 'WA', 'Detalle'],
                $rows
            );
        }

        return 0;
    } catch (\Throwable $e) {
        $this->warn('No fue posible ejecutar los recordatorios automáticos.');
        $this->line($e->getMessage());

        return 0;
    }
})->purpose('Envía recordatorios automáticos de consultas e imágenes por WhatsApp');

Artisan::command('whatsapp:sigcenter-doctor-catalog-sync
    {--dry-run : Solo calcula cuántas filas se reconstruirían}
    {--only-active=1 : Mantiene solo filas activas derivadas de users}', function (): int {
    $table = 'whatsapp_sigcenter_doctor_catalog';

    if (!Schema::hasTable($table)) {
        $this->error('La tabla whatsapp_sigcenter_doctor_catalog no existe. Ejecuta migraciones primero.');
        return 1;
    }

    if (!Schema::hasTable('users')) {
        $this->error('La tabla users no existe en la base configurada.');
        return 1;
    }

    // ── Config maps ──────────────────────────────────────────────────────
    /** @var array<int, string> $sedesMap  [sede_id => sede_nombre] */
    $sedesMap = config('medforge.sedes', []);

    /** @var array<string, array{label: string, catalog_key: string}> $subspecialtiesMap */
    $subspecialtiesMap = config('medforge.subspecialties', []);

    // ── Parse helpers ────────────────────────────────────────────────────

    /**
     * "1,16" → [['sede_id'=>'1','sede_nombre'=>'Villa Club'], ['sede_id'=>'16','sede_nombre'=>'Ceibos']]
     */
    $parseSedes = static function (string $rawSede) use ($sedesMap): array {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $rawSede))) as $id) {
            $intId = (int) $id;
            if (isset($sedesMap[$intId])) {
                $result[] = ['sede_id' => (string) $intId, 'sede_nombre' => $sedesMap[$intId]];
            }
        }
        return $result;
    };

    /**
     * "segmento_anterior,glaucoma" → ['oftalmologo general', 'glaucoma']  (catalog_key values)
     */
    $parseSubs = static function (string $rawSub) use ($subspecialtiesMap): array {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $rawSub))) as $slug) {
            if (isset($subspecialtiesMap[$slug])) {
                $result[] = $subspecialtiesMap[$slug]['catalog_key'];
            }
        }
        return $result;
    };

    $nullableString = static function (mixed $value, int $maxLength): ?string {
        $string = trim((string) $value);
        return $string === '' ? null : mb_substr($string, 0, $maxLength, 'UTF-8');
    };

    $nullableText = static function (mixed $value): ?string {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    };

    // ── Fetch source rows ────────────────────────────────────────────────
    $rows = DB::table('users')
        ->select(['id', 'nombre', 'email', 'profile_photo', 'especialidad', 'subespecialidad', 'id_trabajador', 'sede'])
        ->whereNotNull('id_trabajador')
        ->whereNotNull('subespecialidad')
        ->where('subespecialidad', '<>', '')
        ->where(function ($query): void {
            $query->where('especialidad', 'Cirujano Oftalmólogo')
                ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
        })
        ->orderBy('id')
        ->get();

    $now = now();
    $payload = [];
    $ignoredRows = [];

    foreach ($rows as $row) {
        $sedes = $parseSedes((string) ($row->sede ?? ''));
        $subs  = $parseSubs((string) ($row->subespecialidad ?? ''));

        if ($sedes === [] || $subs === []) {
            $ignoredRows[] = sprintf(
                'id=%s sede="%s" sub="%s"',
                $row->id ?? '?',
                $row->sede ?? '',
                $row->subespecialidad ?? ''
            );
            continue;
        }

        foreach ($sedes as $sede) {
            foreach ($subs as $catalogKey) {
                $key = implode('|', [
                    (string) $row->id_trabajador,
                    $catalogKey,
                    $sede['sede_id'],
                ]);

                $payload[$key] = [
                    'source_user_id'       => $row->id !== null ? (int) $row->id : null,
                    'trabajador_id'        => trim((string) $row->id_trabajador),
                    'doctor_nombre'        => trim((string) ($row->nombre ?? '')),
                    'doctor_email'         => $nullableString($row->email ?? null, 191),
                    'doctor_profile_photo' => $nullableText($row->profile_photo ?? null),
                    'especialidad'         => $nullableString($row->especialidad ?? null, 191),
                    'subespecialidad'      => $catalogKey,
                    'sede_id'              => $sede['sede_id'],
                    'sede_nombre'          => $sede['sede_nombre'],
                    'active'               => true,
                    'last_synced_at'       => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }
    }

    $existingCount = DB::table($table)->count();
    $newCount      = count($payload);
    $doctorCount   = count(array_unique(array_map(
        static fn (array $item): string => (string) $item['trabajador_id'],
        array_values($payload)
    )));

    $this->table(
        ['Métrica', 'Valor'],
        [
            ['rows_from_users',       (string) $rows->count()],
            ['distinct_doctors',      (string) $doctorCount],
            ['catalog_rows_new',      (string) $newCount],
            ['catalog_rows_existing', (string) $existingCount],
            ['ignored_rows',          (string) count($ignoredRows)],
            ['mode',                  (bool) $this->option('dry-run') ? 'dry-run' : 'write'],
        ]
    );

    if ($ignoredRows !== []) {
        $this->newLine();
        $this->warn('Filas ignoradas (sede o subespecialidad no reconocidas en config):');
        foreach ($ignoredRows as $info) {
            $this->line('- ' . $info);
        }
    }

    if ((bool) $this->option('dry-run')) {
        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    DB::transaction(function () use ($table, $payload): void {
        DB::table($table)->delete();

        foreach (array_chunk(array_values($payload), 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    });

    $this->info('Catálogo médico-sede sincronizado.');
    return 0;

})->purpose('Reconstruye el catálogo normalizado de médicos y sedes para el flujo de WhatsApp');

Artisan::command('whatsapp:sigcenter-availability-sync
    {--days=7 : Días hacia adelante a sincronizar, contando hoy}
    {--specialty=oftalmologo general : Subespecialidad a sincronizar}
    {--sede= : Filtra una sede específica (16 o 1)}
    {--dry-run : Solo calcula filas sin escribir}', function (): int {
    $availabilityTable = 'whatsapp_sigcenter_doctor_availability';
    $catalogTable = 'whatsapp_sigcenter_doctor_catalog';

    if (!Schema::hasTable($availabilityTable)) {
        $this->error('La tabla whatsapp_sigcenter_doctor_availability no existe. Ejecuta migraciones primero.');
        return 1;
    }

    if (!Schema::hasTable($catalogTable)) {
        $this->error('La tabla whatsapp_sigcenter_doctor_catalog no existe. Sin catálogo local no se puede sincronizar disponibilidad.');
        return 1;
    }

    $days = max(0, min(7, (int) $this->option('days')));
    $specialty = trim(mb_strtolower((string) $this->option('specialty'), 'UTF-8'));
    $sedeFilter = trim((string) $this->option('sede'));
    $dryRun = (bool) $this->option('dry-run');
    $today = now()->startOfDay();
    $endDate = $today->copy()->addDays($days);
    $syncAt = now();

    $doctors = DB::table($catalogTable)
        ->select(['trabajador_id', 'doctor_nombre', 'especialidad', 'subespecialidad', 'sede_id', 'sede_nombre'])
        ->where('active', true)
        ->whereRaw('LOWER(TRIM(subespecialidad)) = ?', [$specialty])
        ->when($sedeFilter !== '', static fn ($query) => $query->where('sede_id', $sedeFilter))
        ->orderBy('doctor_nombre')
        ->orderBy('sede_id')
        ->get();

    $this->table(
        ['Parámetro', 'Valor'],
        [
            ['specialty', $specialty],
            ['date_from', $today->toDateString()],
            ['date_to', $endDate->toDateString()],
            ['doctor_rows', (string) $doctors->count()],
            ['mode', $dryRun ? 'dry-run' : 'write'],
        ]
    );

    if ($doctors->isEmpty()) {
        $this->warn('No hay doctores en el catálogo local para ese filtro.');
        return 0;
    }

    /** @var \App\Modules\Whatsapp\Services\FlowSigcenterAgendaService $agenda */
    $agenda = app(\App\Modules\Whatsapp\Services\FlowSigcenterAgendaService::class);

    $parseSlots = static function (array $slots): array {
        $normalized = array_values(array_filter(array_map(static function (mixed $slot): ?string {
            $value = trim((string) $slot);
            return $value === '' ? null : $value;
        }, $slots)));

        if ($normalized === []) {
            return [0, null, null, []];
        }

        $firstStart = null;
        $lastEnd = null;

        foreach ($normalized as $slot) {
            [$start, $end] = array_pad(array_map('trim', explode('-', $slot, 2)), 2, null);
            if ($start !== null && preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) === 1 && $firstStart === null) {
                $firstStart = $start;
            }
            if ($end !== null && preg_match('/^\d{2}:\d{2}:\d{2}$/', $end) === 1) {
                $lastEnd = $end;
            }
        }

        return [count($normalized), $firstStart, $lastEnd, $normalized];
    };

    $payload = [];
    $stats = [
        'doctors_scanned' => 0,
        'dates_found' => 0,
        'rows_prepared' => 0,
        'times_with_data' => 0,
        'times_empty' => 0,
        'errors' => 0,
    ];

    foreach ($doctors as $doctor) {
        $stats['doctors_scanned']++;

        try {
            $daysResult = $agenda->execute(
                ['operation' => 'list_days', 'send_result' => false],
                [
                    'trabajador_id' => (string) $doctor->trabajador_id,
                    'sede_id' => (string) $doctor->sede_id,
                ],
                [],
                false
            );
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->warn(sprintf(
                'Error consultando días para %s (%s / sede %s): %s',
                (string) $doctor->doctor_nombre,
                (string) $doctor->trabajador_id,
                (string) $doctor->sede_id,
                $e->getMessage()
            ));
            continue;
        }

        $dates = is_array($daysResult['data']['fechas'] ?? null) ? $daysResult['data']['fechas'] : [];
        foreach ($dates as $date) {
            $dateValue = trim((string) $date);
            if ($dateValue === '') {
                continue;
            }

            try {
                $parsed = \Illuminate\Support\Carbon::parse($dateValue)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if ($parsed->lt($today) || $parsed->gt($endDate)) {
                continue;
            }

            $stats['dates_found']++;

            try {
                $timesResult = $agenda->execute(
                    ['operation' => 'list_times', 'send_result' => false],
                    [
                        'trabajador_id' => (string) $doctor->trabajador_id,
                        'sede_id' => (string) $doctor->sede_id,
                        'fecha' => $parsed->toDateString(),
                    ],
                    [],
                    false
                );
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf(
                    'Error consultando horarios para %s (%s / %s / %s): %s',
                    (string) $doctor->doctor_nombre,
                    (string) $doctor->trabajador_id,
                    (string) $doctor->sede_id,
                    $parsed->toDateString(),
                    $e->getMessage()
                ));
                continue;
            }

            $slots = is_array($timesResult['data']['horarios'] ?? null) ? $timesResult['data']['horarios'] : [];
            [$slotCount, $firstStart, $lastEnd, $normalizedSlots] = $parseSlots($slots);

            if ($slotCount > 0) {
                $stats['times_with_data']++;
            } else {
                $stats['times_empty']++;
            }

            $payload[] = [
                'trabajador_id' => (string) $doctor->trabajador_id,
                'doctor_nombre' => trim((string) $doctor->doctor_nombre),
                'especialidad' => trim((string) ($doctor->especialidad ?? '')),
                'subespecialidad' => trim((string) $doctor->subespecialidad),
                'sede_id' => (string) $doctor->sede_id,
                'sede_nombre' => trim((string) $doctor->sede_nombre),
                'fecha' => $parsed->toDateString(),
                'available_slots_count' => $slotCount,
                'first_slot_start' => $firstStart,
                'last_slot_end' => $lastEnd,
                'raw_slots' => json_encode($normalizedSlots, JSON_UNESCAPED_UNICODE),
                'active' => $slotCount > 0,
                'last_synced_at' => $syncAt,
                'created_at' => $syncAt,
                'updated_at' => $syncAt,
            ];
            $stats['rows_prepared']++;
        }
    }

    $this->newLine();
    $this->table(
        ['Métrica', 'Valor'],
        [
            ['doctors_scanned', (string) $stats['doctors_scanned']],
            ['dates_found', (string) $stats['dates_found']],
            ['rows_prepared', (string) $stats['rows_prepared']],
            ['times_with_data', (string) $stats['times_with_data']],
            ['times_empty', (string) $stats['times_empty']],
            ['errors', (string) $stats['errors']],
        ]
    );

    if ($dryRun) {
        $previewRows = collect($payload)->take(10)->map(static fn (array $row): array => [
            $row['doctor_nombre'],
            $row['sede_nombre'],
            $row['fecha'],
            (string) $row['available_slots_count'],
            (string) ($row['first_slot_start'] ?? '—'),
            (string) ($row['last_slot_end'] ?? '—'),
        ])->all();

        if ($previewRows !== []) {
            $this->newLine();
            $this->table(
                ['Doctor', 'Sede', 'Fecha', 'Bloques', 'Inicio', 'Fin'],
                $previewRows
            );
        }

        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    DB::transaction(function () use ($availabilityTable, $specialty, $sedeFilter, $today, $endDate, $payload, $syncAt): void {
        DB::table($availabilityTable)
            ->whereRaw('LOWER(TRIM(subespecialidad)) = ?', [$specialty])
            ->when($sedeFilter !== '', static fn ($query) => $query->where('sede_id', $sedeFilter))
            ->whereBetween('fecha', [$today->toDateString(), $endDate->toDateString()])
            ->delete();

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table($availabilityTable)->insert($chunk);
        }

        DB::table($availabilityTable)
            ->whereRaw('LOWER(TRIM(subespecialidad)) = ?', [$specialty])
            ->where('fecha', '>=', $today->toDateString())
            ->where('last_synced_at', '<', $syncAt)
            ->update([
                'active' => false,
                'updated_at' => $syncAt,
            ]);
    });

    $this->info('Disponibilidad sincronizada.');
    return 0;
})->purpose('Sincroniza disponibilidad local por médico, sede y fecha para flujos de agenda por fecha');

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

    $cacheDir = trim((string) (config('nas-imagenes.cache_dir') ?? ''));
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
            return;
        }

        if ($event === 'missing') {
            $this->warn(sprintf(
                '[MISSING] marcados=%s from=%s to=%s',
                (string) ($payload['count'] ?? 0),
                (string) ($payload['from'] ?? '—'),
                (string) ($payload['to'] ?? '—')
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
        ['From', 'To', 'Source', 'Total', 'Processed', 'Sent', 'Skipped', 'Missing', 'Errors', 'Duration ms'],
        [[
            (string) ($result['from'] ?? '—'),
            (string) ($result['to'] ?? '—'),
            (string) ($result['source'] ?? '—'),
            (int) ($result['total_rows'] ?? 0),
            (int) ($result['processed_rows'] ?? 0),
            (int) ($result['sent_rows'] ?? 0),
            (int) ($result['skipped_rows'] ?? 0),
            (int) ($result['missing_marked_rows'] ?? 0),
            (int) ($result['error_rows'] ?? 0),
            (int) ($result['duration_ms'] ?? 0),
        ]]
    );

    $solStats = $result['solicitudes_sync'] ?? [];
    if ($solStats !== []) {
        if (isset($solStats['error'])) {
            $this->warn('[SOLICITUDES-SYNC] Error: ' . $solStats['error']);
        } else {
            $this->table(
                ['Sol. matcheadas', 'Actualizadas', 'Etapa avanzada', 'Notas agregadas', 'Errores'],
                [[
                    (int) ($solStats['matched'] ?? 0),
                    (int) ($solStats['updated'] ?? 0),
                    (int) ($solStats['advanced'] ?? 0),
                    (int) ($solStats['noted'] ?? 0),
                    (int) ($solStats['errors'] ?? 0),
                ]]
            );
        }
    }

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

Artisan::command('solicitudes:evaluar-sla', function (): int {
    $this->line(sprintf('[%s] Evaluando SLAs de solicitudes...', now()->format('Y-m-d H:i:s')));
    EvaluateSolicitudesSlaJob::dispatchSync();
    $this->line(sprintf('[%s] Evaluación SLA completada.', now()->format('Y-m-d H:i:s')));
    return 0;
})->purpose('Evalúa SLAs de solicitudes activas y crea tareas CRM para casos críticos o vencidos');

// ---------------------------------------------------------------------------
// solicitudes:enviar-recordatorios
// Despacha recordatorios quirúrgicos (preop 2d, preop 24h, postop) para
// solicitudes en estado "programada" según ventanas de fecha de cirugía.
// ---------------------------------------------------------------------------
Artisan::command('solicitudes:enviar-recordatorios
    {--dry-run : Solo lista cuántos recordatorios se despacharían sin enviarlos}', function (): int {
    $ahora  = now();
    $dryRun = (bool) $this->option('dry-run');

    $ventanas = [
        'preop_2d'  => ['dias' => 2,  'label' => '2 días antes de cirugía'],
        'preop_24h' => ['dias' => 1,  'label' => '24 h antes de cirugía'],
        'postop'    => ['dias' => -1, 'label' => '1 día después de cirugía'],
    ];

    $totalDespachados = 0;

    foreach ($ventanas as $tipo => ['dias' => $dias, 'label' => $label]) {
        $fecha = $ahora->copy()->addDays($dias)->toDateString();

        $rows = DB::table('solicitud_procedimiento')
            ->whereDate('fecha', $fecha)
            ->where('estado', 'programada')
            ->select(['id', 'fecha'])
            ->get();

        $this->line(sprintf('[%s] %s — %s: %d solicitudes', $ahora->format('H:i:s'), $tipo, $label, $rows->count()));

        if (!$dryRun) {
            foreach ($rows as $row) {
                SendSolicitudReminderJob::dispatch((int) $row->id, $tipo, (string) $row->fecha);
            }
        }

        $totalDespachados += $rows->count();
    }

    $this->newLine();
    $this->info($dryRun
        ? "Dry-run completado. {$totalDespachados} recordatorios pendientes de despacho."
        : "Recordatorios despachados: {$totalDespachados}."
    );

    return 0;
})->purpose('Despacha recordatorios quirúrgicos para solicitudes programadas con cirugía inminente o reciente');

// ---------------------------------------------------------------------------
// solicitudes:crm-sync
// Sincroniza datos de SigCenter con solicitud_procedimiento y avanza etapas
// kanban automáticamente usando IndexAdmisionesSyncService.
// ---------------------------------------------------------------------------
Artisan::command('solicitudes:crm-sync
    {--lookback=7  : Días hacia atrás desde hoy}
    {--lookahead=7 : Días hacia adelante desde hoy}
    {--from-date=  : Fecha inicial YYYY-MM-DD (tiene prioridad sobre --lookback)}
    {--to-date=    : Fecha final YYYY-MM-DD (tiene prioridad sobre --lookahead)}
    {--extractor=auto : Driver de extracción: auto, db o scraper}', function (): int {
    /** @var IndexAdmisionesSyncService $syncService */
    $syncService = app(IndexAdmisionesSyncService::class);

    $this->line(sprintf('[%s] solicitudes:crm-sync — sincronizando SigCenter...', now()->format('Y-m-d H:i:s')));

    $result = $syncService->sync([
        'lookback'  => (int) $this->option('lookback'),
        'lookahead' => (int) $this->option('lookahead'),
        'from_date' => $this->option('from-date') ?: null,
        'to_date'   => $this->option('to-date') ?: null,
        'extractor' => trim((string) ($this->option('extractor') ?? 'auto')),
    ]);

    if (!(bool) ($result['success'] ?? false)) {
        $this->error((string) ($result['error'] ?? 'No se pudo sincronizar con SigCenter.'));
        $this->line(sprintf('[%s] solicitudes:crm-sync — error.', now()->format('Y-m-d H:i:s')));
        return 1;
    }

    $this->newLine();
    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Rango desde',             (string) ($result['from']     ?? '—')],
            ['Rango hasta',             (string) ($result['to']       ?? '—')],
            ['Filas obtenidas',         (string) ($result['fetched']  ?? ($result['rows'] ?? 0))],
            ['Sincronizadas',           (string) ($result['synced']   ?? ($result['matched'] ?? 0))],
            ['Actualizadas',            (string) ($result['updated']  ?? 0)],
            ['Etapas avanzadas',        (string) ($result['advanced'] ?? 0)],
            ['Errores',                 (string) ($result['errors']   ?? 0)],
        ]
    );

    $this->line(sprintf('[%s] solicitudes:crm-sync — completado.', now()->format('Y-m-d H:i:s')));
    return (int) (($result['errors'] ?? 0) > 0);
})->purpose('Sincroniza datos de SigCenter (cirugías programadas) con solicitudes y avanza etapas kanban');

// ---------------------------------------------------------------------------
// solicitudes:derivaciones-refresh
// Refresca los campos derivacion_numero_sel y derivacion_fecha_vigencia_sel
// en solicitud_procedimiento cruzando con las tablas de derivaciones.
// ---------------------------------------------------------------------------
Artisan::command('solicitudes:derivaciones-refresh
    {--dry-run           : Solo muestra las solicitudes que se actualizarían}
    {--solo-sin-numero   : Solo procesa solicitudes sin derivacion_numero_sel}
    {--limit=0           : Límite de solicitudes a procesar (0 = sin límite)}', function (): int {
    $dryRun   = (bool) $this->option('dry-run');
    $soloSin  = (bool) $this->option('solo-sin-numero');
    $limit    = max(0, (int) $this->option('limit'));

    $this->line(sprintf('[%s] solicitudes:derivaciones-refresh — buscando candidatas...', now()->format('Y-m-d H:i:s')));

    // Cruzamos solicitud_procedimiento con las 3 tablas de derivaciones por hc_number.
    // Nos quedamos con el referral_code y la vigencia más reciente por hc_number.
    $query = DB::table('solicitud_procedimiento as sp')
        ->select([
            'sp.id',
            'sp.hc_number',
            'sp.derivacion_numero_sel',
            DB::raw('r.referral_code AS nuevo_numero'),
            DB::raw('COALESCE(r.valid_until, f.fecha_vigencia) AS nueva_vigencia'),
        ])
        ->join('derivaciones_forms as f', 'f.hc_number', '=', 'sp.hc_number')
        ->join('derivaciones_referral_forms as df', 'df.form_id', '=', 'f.id')
        ->join('derivaciones_referrals as r', 'r.id', '=', 'df.referral_id')
        ->whereRaw("sp.afiliacion REGEXP 'IESS|ISSFA|ISSPOL|MSP'")
        ->whereNotIn('sp.estado', ['completado', 'completada', 'cancelado', 'anulado'])
        ->whereNotNull('r.referral_code')
        ->orderByDesc('sp.id');

    if ($soloSin) {
        $query->whereNull('sp.derivacion_numero_sel');
    }

    if ($limit > 0) {
        $query->limit($limit);
    }

    $rows = $query->get();

    $this->line("Solicitudes candidatas: {$rows->count()}");

    if ($dryRun) {
        $this->table(
            ['ID', 'HC', 'Número actual', 'Nuevo número', 'Nueva vigencia'],
            $rows->take(25)->map(fn ($r) => [
                (string) $r->id,
                (string) $r->hc_number,
                (string) ($r->derivacion_numero_sel ?? '—'),
                (string) ($r->nuevo_numero ?? '—'),
                (string) ($r->nueva_vigencia ?? '—'),
            ])->all()
        );
        if ($rows->count() > 25) {
            $this->line('... (mostrando primeros 25 de ' . $rows->count() . ')');
        }
        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    $actualizado = 0;
    $sinNumero   = 0;

    foreach ($rows as $row) {
        $nuevoNumero  = trim((string) ($row->nuevo_numero ?? ''));
        $nuevaVigencia = $row->nueva_vigencia !== null ? trim((string) $row->nueva_vigencia) : null;

        if ($nuevoNumero === '') {
            $sinNumero++;
            continue;
        }

        DB::table('solicitud_procedimiento')
            ->where('id', (int) $row->id)
            ->update([
                'derivacion_numero_sel'        => $nuevoNumero,
                'derivacion_fecha_vigencia_sel' => $nuevaVigencia !== '' ? $nuevaVigencia : null,
            ]);

        $actualizado++;
    }

    $this->newLine();
    $this->table(
        ['Resultado', 'Valor'],
        [
            ['Candidatas evaluadas', (string) $rows->count()],
            ['Actualizadas',         (string) $actualizado],
            ['Sin número en origen', (string) $sinNumero],
        ]
    );

    $this->info('Refresh de derivaciones completado.');
    return 0;
})->purpose('Refresca derivacion_numero_sel y derivacion_fecha_vigencia_sel desde las tablas de derivaciones');

// ---------------------------------------------------------------------------
// solicitudes:marcar-vencidas
// Detecta solicitudes públicas con derivación vencida y sin tarea CRM abierta,
// y crea una tarea CRM de alerta para gestión inmediata.
// ---------------------------------------------------------------------------
Artisan::command('solicitudes:marcar-vencidas
    {--dry-run : Solo muestra cuántas solicitudes están vencidas sin escribir}', function (): int {
    $dryRun  = (bool) $this->option('dry-run');
    $now     = now();
    $hoy     = $now->toDateString();

    $this->line(sprintf('[%s] solicitudes:marcar-vencidas — detectando solicitudes con derivación vencida...', $now->format('Y-m-d H:i:s')));

    // Solicitudes públicas con vigencia expirada y estado aún activo.
    $vencidas = DB::table('solicitud_procedimiento as sp')
        ->select(['sp.id', 'sp.hc_number', 'sp.estado', 'sp.afiliacion', 'sp.derivacion_fecha_vigencia_sel', 'sc.responsable_id'])
        ->leftJoin('solicitud_crm_detalles as sc', 'sc.solicitud_id', '=', 'sp.id')
        ->whereRaw("sp.afiliacion REGEXP 'IESS|ISSFA|ISSPOL|MSP'")
        ->whereNotNull('sp.derivacion_fecha_vigencia_sel')
        ->whereDate('sp.derivacion_fecha_vigencia_sel', '<', $hoy)
        ->whereNotIn('sp.estado', ['completado', 'completada', 'cancelado', 'anulado', 'programada'])
        ->whereNotExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('crm_tasks')
                ->where('source_module', 'solicitudes')
                ->whereColumn('source_ref_id', DB::raw('CAST(sp.id AS CHAR)'))
                ->where('category', 'derivacion_vencida')
                ->whereNotIn('status', ['completada', 'cancelada']);
        })
        ->get();

    $this->line("Solicitudes con derivación vencida sin tarea abierta: {$vencidas->count()}");

    if ($vencidas->isEmpty()) {
        $this->info('No hay solicitudes con derivación vencida pendientes de atención.');
        return 0;
    }

    if ($dryRun) {
        $this->table(
            ['ID', 'HC', 'Estado', 'Vigencia expirada'],
            $vencidas->take(20)->map(fn ($r) => [
                (string) $r->id,
                (string) $r->hc_number,
                (string) $r->estado,
                (string) $r->derivacion_fecha_vigencia_sel,
            ])->all()
        );
        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    $creadas = 0;
    $nowStr  = $now->toDateTimeString();

    foreach ($vencidas as $row) {
        $diasVencida = (int) now()->diffInDays($row->derivacion_fecha_vigencia_sel);

        DB::table('crm_tasks')->insert([
            'source_module' => 'solicitudes',
            'source_ref_id' => (string) $row->id,
            'title'         => "🔴 Derivación IESS vencida — Solicitud #{$row->id}",
            'description'   => "La autorización IESS (vigencia: {$row->derivacion_fecha_vigencia_sel}) venció hace {$diasVencida} días. Renovar derivación urgente.",
            'status'        => 'pendiente',
            'priority'      => 'urgente',
            'category'      => 'derivacion_vencida',
            'assigned_to'   => $row->responsable_id ?? null,
            'due_date'      => $hoy,
            'due_at'        => $nowStr,
            'created_at'    => $nowStr,
            'updated_at'    => $nowStr,
        ]);

        $creadas++;
    }

    $this->newLine();
    $this->table(
        ['Resultado', 'Valor'],
        [
            ['Solicitudes evaluadas', (string) $vencidas->count()],
            ['Tareas CRM creadas',    (string) $creadas],
        ]
    );

    $this->info('Detección de solicitudes vencidas completada.');
    return 0;
})->purpose('Crea tareas CRM urgentes para solicitudes IESS con derivación vencida y sin gestión abierta');

// ---------------------------------------------------------------------------
// solicitudes:crm-task-reminders
// Procesa crm_tasks con remind_at <= now() del módulo solicitudes,
// registra el reminder disparado e inserta una nota de seguimiento.
// ---------------------------------------------------------------------------
Artisan::command('solicitudes:crm-task-reminders
    {--dry-run : Solo muestra tareas pendientes de recordatorio sin procesar}
    {--limit=100 : Máximo de tareas a procesar por ejecución}', function (): int {
    $dryRun = (bool) $this->option('dry-run');
    $limit  = max(1, min(500, (int) $this->option('limit')));
    $now    = now();
    $nowStr = $now->toDateTimeString();

    $this->line(sprintf('[%s] solicitudes:crm-task-reminders — procesando recordatorios pendientes...', $now->format('Y-m-d H:i:s')));

    // Tareas activas del módulo solicitudes cuyo remind_at ya pasó
    // y aún no tienen un registro en crm_task_reminders para hoy.
    $tareas = DB::table('crm_tasks as t')
        ->select(['t.id', 't.title', 't.source_ref_id', 't.assigned_to', 't.remind_at', 't.remind_channel'])
        ->where('t.source_module', 'solicitudes')
        ->whereNotIn('t.status', ['completada', 'cancelada'])
        ->whereNotNull('t.remind_at')
        ->where('t.remind_at', '<=', $nowStr)
        ->whereNotExists(function ($sub) use ($now): void {
            $sub->select(DB::raw(1))
                ->from('crm_task_reminders as r')
                ->whereColumn('r.task_id', 't.id')
                ->whereDate('r.created_at', $now->toDateString());
        })
        ->orderBy('t.remind_at')
        ->limit($limit)
        ->get();

    $this->line("Tareas con recordatorio pendiente: {$tareas->count()}");

    if ($tareas->isEmpty()) {
        $this->info('No hay recordatorios de tareas CRM pendientes.');
        return 0;
    }

    if ($dryRun) {
        $this->table(
            ['Task ID', 'Solicitud ID', 'Título', 'Remind at', 'Asignado a'],
            $tareas->map(fn ($t) => [
                (string) $t->id,
                (string) $t->source_ref_id,
                mb_substr((string) $t->title, 0, 50),
                (string) $t->remind_at,
                (string) ($t->assigned_to ?? '—'),
            ])->all()
        );
        $this->info('Dry-run completado. No se escribieron cambios.');
        return 0;
    }

    $procesadas = 0;
    $errores    = 0;
    // company_id para crm_task_reminders (fallback al primer registro de la tarea)
    $defaultCompanyId = (int) DB::table('crm_tasks')->value('company_id') ?: 1;

    foreach ($tareas as $tarea) {
        try {
            // 1. Registrar el recordatorio disparado.
            DB::table('crm_task_reminders')->insert([
                'task_id'    => $tarea->id,
                'company_id' => $defaultCompanyId,
                'remind_at'  => $tarea->remind_at,
                'channel'    => $tarea->remind_channel ?: 'system',
                'created_at' => $nowStr,
            ]);

            // 2. Insertar nota de seguimiento en la solicitud.
            $solicitudId = (int) $tarea->source_ref_id;
            if ($solicitudId > 0) {
                DB::table('solicitud_crm_notas')->insert([
                    'solicitud_id' => $solicitudId,
                    'nota'         => "⏰ Recordatorio de tarea: {$tarea->title}",
                    'autor_id'     => null,
                    'created_at'   => $nowStr,
                ]);
            }

            $procesadas++;
        } catch (\Throwable $e) {
            $errores++;
            \Illuminate\Support\Facades\Log::warning('solicitudes.crm_task_reminders.error', [
                'task_id' => $tarea->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    $this->newLine();
    $this->table(
        ['Resultado', 'Valor'],
        [
            ['Tareas evaluadas', (string) $tareas->count()],
            ['Procesadas',       (string) $procesadas],
            ['Errores',          (string) $errores],
        ]
    );

    $this->info('Recordatorios de tareas CRM procesados.');
    return $errores > 0 ? 1 : 0;
})->purpose('Procesa remind_at de crm_tasks del módulo solicitudes e inserta notas de seguimiento');

Artisan::command('whatsapp:kb-import-triage
    {file? : Ruta al JSON con documentos de triage}
    {--draft : Importa como borrador en lugar de publicado}', function (): int {
    $defaultPath = base_path('app/Modules/Whatsapp/Support/kb-triage-symptoms-seed.json');
    $path = (string) ($this->argument('file') ?: $defaultPath);

    if (!is_file($path)) {
        $this->error("No existe el archivo: {$path}");
        return 1;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        $this->error('El archivo de triage está vacío.');
        return 1;
    }

    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        $this->error('El archivo no contiene un JSON válido.');
        return 1;
    }

    $service = app(\App\Modules\Whatsapp\Services\KnowledgeBaseService::class);
    $draft = (bool) $this->option('draft');
    $created = 0;
    $updated = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $title = trim((string) ($row['title'] ?? ''));
        $slug = \Illuminate\Support\Str::slug($title);
        if ($title === '') {
            continue;
        }

        if ($draft) {
            $row['status'] = 'draft';
        }

        $existing = \App\Models\WhatsappKnowledgeDocument::query()
            ->where('slug', $slug)
            ->orWhere('title', $title)
            ->first();

        if ($existing) {
            $service->updateDocument((int) $existing->id, $row, null);
            $updated++;
            continue;
        }

        $service->createDocument($row, null);
        $created++;
    }

    $this->table(
        ['Resultado', 'Valor'],
        [
            ['Archivo', $path],
            ['Creados', (string) $created],
            ['Actualizados', (string) $updated],
            ['Modo', $draft ? 'draft' : 'published'],
        ]
    );

    return 0;
})->purpose('Carga o actualiza una semilla base de Knowledge Base para triage de síntomas');

// ── Wrapper para tareas legacy migradas a Laravel scheduler ──────────────────
// Permite ejecutar tareas del CronRunner (slugs legacy) desde el scheduler de
// Laravel sin depender del cron.php heredado. El scheduler maneja el timing;
// force=true omite el re-chequeo interno del CronRunner.

Artisan::command('cron:legacy-task {slug : Slug de la tarea en cron_schedule}', function (): int {
    $slug = (string) $this->argument('slug');

    try {
        $pdo = DB::connection()->getPdo();
        $runner = new CronRunner($pdo);
        $result = $runner->runBySlug($slug, force: true);

        if ($result === null) {
            $this->warn("Tarea '{$slug}' no encontrada en CronRunner.");
            Log::warning("cron:legacy-task: slug no encontrado", ['slug' => $slug]);
            return 1;
        }

        $status = $result['status'] ?? 'ok';
        $message = $result['message'] ?? '';

        if ($status === 'failed') {
            $this->error("[{$slug}] {$message}");
            Log::error("cron:legacy-task failed", ['slug' => $slug, 'result' => $result]);
            return 1;
        }

        $this->line("[{$slug}] {$status}: {$message}");
        Log::info("cron:legacy-task ok", ['slug' => $slug, 'status' => $status]);
        return 0;
    } catch (\Throwable $e) {
        $this->error("[{$slug}] Exception: " . $e->getMessage());
        Log::error("cron:legacy-task exception", ['slug' => $slug, 'error' => $e->getMessage()]);
        return 1;
    }
})->purpose('Ejecuta una tarea del CronRunner legacy por slug desde el scheduler de Laravel');

// ── Schedule por SERVER_ROLE ─────────────────────────────────────────────────
// scraper (staging): tareas de extracción desde fuentes externas.
// production:        tareas de lógica de negocio que leen desde la DB compartida.
// sin SERVER_ROLE:   dev local — corren todas.
(static function (): void {
    $role         = strtolower((string) (getenv('SERVER_ROLE') ?: ''));
    $isScraper    = $role === 'scraper'    || $role === '';
    $isProduction = $role === 'production' || $role === '';
    $handoffAutoAssignScheduled = filter_var(
        getenv('WHATSAPP_LARAVEL_HANDOFF_AUTO_ASSIGN_SCHEDULED') ?: 'false',
        FILTER_VALIDATE_BOOLEAN
    );

    // ── STAGING / SCRAPER ─────────────────────────────────────────────────────
    // Toda la extracción de fuentes externas corre aquí para no saturar producción.
    if ($isScraper) {
        // Ventana amplia ±14 días — mantiene histórico reciente y agenda futura completos
        Schedule::command('index-admisiones:sync --lookback=14 --lookahead=14')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Ventana estrecha hoy + mañana — máxima frescura para cirugías inmediatas
        Schedule::command('index-admisiones:sync --lookback=0 --lookahead=1')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Facturación real desde Sigcenter — mes en curso, idempotente por hash MD5
        Schedule::command('billing:facturacion-real-sync')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Índice de imágenes NAS — ventana 7 días, no reescanea form_id con cache <2 h
        // NAS accesible desde staging vía SSH externo (190.110.204.74:2222)
        Schedule::command('imagenes:nas-index --days=7 --stale-hours=2')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        if ($handoffAutoAssignScheduled) {
            // Autoasignación staging: límite conservador para validar cola sin saturar agentes.
            Schedule::command('whatsapp:handoff-auto-assign --limit=50 --max-age-hours=72')
                ->everyTenMinutes()
                ->withoutOverlapping();
        }
    }

    // ── PRODUCCIÓN ────────────────────────────────────────────────────────────
    if ($isProduction) {
        // Índice de imágenes Sigcenter — Sigcenter DB vive en localhost de producción (127.0.0.1)
        Schedule::command('imagenes:sigcenter-index --days=7 --stale-hours=2')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Escalaciones diarias de oportunidades CRM comerciales estancadas
        Schedule::command('crm:escalate')->dailyAt('08:00');

        // Cierre de handoffs WhatsApp zombies (sin actividad en 7+ días)
        Schedule::command('whatsapp:close-zombie-handoffs --days=7')->dailyAt('02:00');

        if ($handoffAutoAssignScheduled) {
            // Autoasignación de oportunidades calientes WhatsApp sin dueño.
            Schedule::command('whatsapp:handoff-auto-assign --limit=100 --max-age-hours=72')
                ->everyFiveMinutes()
                ->withoutOverlapping();
        }
    }
})();

// ── Scheduler DB-driven ──────────────────────────────────────────────────────
// Las frecuencias viven en la tabla cron_schedule y son editables desde el UI.

(static function (): void {
    try {
        $repo = new CronScheduleRepository();
        foreach ($repo->getEnabled('artisan') as $task) {
            $slug = $task->slug;
            $cmd = Schedule::command($task->command)->cron($task->cron_expression);
            if ($task->without_overlapping) {
                $cmd->withoutOverlapping();
            }
            if ($task->run_in_background) {
                $cmd->runInBackground();
            }
            $cmd->onSuccess(static function () use ($repo, $slug): void {
                $repo->updateExecution($slug, 'ok');
            })->onFailure(static function () use ($repo, $slug): void {
                $repo->updateExecution($slug, 'failed');
            });
        }
    } catch (\Throwable $e) {
        // Si la tabla no existe aún (ej: primera migración), no crashear el scheduler.
        Log::warning('cron_schedule: no se pudo registrar schedule desde DB', ['error' => $e->getMessage()]);
    }
})();
