<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappFlowShadowRun;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class FlowRuntimeShadowObserverService
{
    public function __construct(
        private readonly FlowRuntimeShadowCompareService $compareService = new FlowRuntimeShadowCompareService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $message
     */
    public function observeWebhookInput(array $input, array $message): void
    {
        if (!$this->shadowEnabled()) {
            return;
        }

        $this->persistShadowRun($input, $message, $this->resolveSource('webhook'));
    }

    /**
     * @return array{processed:int,skipped:int,source:string}
     */
    public function syncRecentInboundMessages(int $limit = 50): array
    {
        if (!$this->shadowEnabled()) {
            return ['processed' => 0, 'skipped' => 0, 'source' => $this->resolveSource('db_sync')];
        }

        $processed = 0;
        $skipped = 0;
        $source = $this->resolveSource('db_sync');

        $messages = WhatsappMessage::query()
            ->with('whatsapp_conversation')
            ->where('direction', 'inbound')
            ->whereNotNull('body')
            ->where(function ($query): void {
                $query
                    ->whereIn('message_type', ['text', 'interactive', 'button'])
                    ->orWhereNull('message_type');
            })
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 500)))
            ->get();

        foreach ($messages as $message) {
            $messageId = $this->nullableString($message->wa_message_id);
            if ($messageId !== null && WhatsappFlowShadowRun::query()->where('inbound_message_id', $messageId)->exists()) {
                $skipped++;
                continue;
            }

            $conversation = $message->whatsapp_conversation;
            $waNumber = $conversation?->wa_number;
            $text = $this->nullableString($message->body);

            if ($waNumber === null || $text === null) {
                $skipped++;
                continue;
            }

            $sessionContext = [];
            if ($conversation?->whatsapp_autoresponder_session !== null) {
                $sessionContext = is_array($conversation->whatsapp_autoresponder_session->context)
                    ? $conversation->whatsapp_autoresponder_session->context
                    : [];
            }

            $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
            $rawPayload['id'] = $rawPayload['id'] ?? $messageId;
            $rawPayload['from'] = $rawPayload['from'] ?? $waNumber;
            $rawPayload['type'] = $rawPayload['type'] ?? ($message->message_type ?: 'text');

            $this->persistShadowRun([
                'wa_number' => $waNumber,
                'text' => $text,
                'context' => $sessionContext,
            ], $rawPayload, $source);

            $processed++;
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'source' => $source,
        ];
    }

    private function shadowEnabled(): bool
    {
        if (!(bool) config('whatsapp.migration.automation.enabled', false)) {
            return false;
        }

        return (bool) config('whatsapp.migration.automation.compare_with_legacy', true);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $message
     */
    private function persistShadowRun(array $input, array $message, string $source): void
    {
        try {
            $result = $this->compareService->compare($input);
            $number = trim((string) ($input['wa_number'] ?? ''));
            $conversationId = $number !== ''
                ? WhatsappConversation::query()->where('wa_number', $number)->value('id')
                : null;
            $parity = is_array($result['parity'] ?? null) ? $result['parity'] : [];
            $executionPreview = is_array($result['execution_preview'] ?? null) ? $result['execution_preview'] : [];

            WhatsappFlowShadowRun::query()->create([
                'source' => $source,
                'wa_number' => $number !== '' ? $number : null,
                'conversation_id' => is_numeric($conversationId) ? (int) $conversationId : null,
                'inbound_message_id' => $this->nullableString($message['id'] ?? null),
                'message_text' => $this->nullableString($input['text'] ?? null),
                'same_match' => (bool) ($parity['same_match'] ?? false),
                'same_scenario' => (bool) ($parity['same_scenario'] ?? false),
                'same_handoff' => (bool) ($parity['same_handoff'] ?? false),
                'same_action_types' => (bool) ($parity['same_action_types'] ?? false),
                'input_payload' => $input,
                'parity_payload' => array_merge($parity, [
                    'dry_run' => (bool) config('whatsapp.migration.automation.dry_run', true),
                    'execution_preview' => $executionPreview,
                ]),
                'laravel_payload' => array_merge((array) ($result['laravel'] ?? []), [
                    'execution_preview' => $executionPreview,
                ]),
                'legacy_payload' => $result['legacy'] ?? [],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('whatsapp.flow_shadow.observe_failed', [
                'error' => $exception->getMessage(),
                'source' => $source,
                'wa_number' => $input['wa_number'] ?? null,
                'message_id' => $message['id'] ?? null,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 25, bool $mismatchesOnly = false): array
    {
        $query = WhatsappFlowShadowRun::query()->latest('id');

        if ($mismatchesOnly) {
            $query->where(function ($builder): void {
                $builder
                    ->where('same_match', false)
                    ->orWhere('same_scenario', false)
                    ->orWhere('same_handoff', false)
                    ->orWhere('same_action_types', false);
            });
        }

        return $query
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (WhatsappFlowShadowRun $run): array => [
                'id' => (int) $run->id,
                'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
                'source' => (string) $run->source,
                'wa_number' => $run->wa_number,
                'conversation_id' => $run->conversation_id,
                'inbound_message_id' => $run->inbound_message_id,
                'message_text' => $run->message_text,
                'parity' => [
                    'same_match' => (bool) $run->same_match,
                    'same_scenario' => (bool) $run->same_scenario,
                    'same_handoff' => (bool) $run->same_handoff,
                    'same_action_types' => (bool) $run->same_action_types,
                    'mismatch_reasons' => is_array($run->parity_payload['mismatch_reasons'] ?? null) ? array_values($run->parity_payload['mismatch_reasons']) : [],
                ],
                'execution_mode' => $run->parity_payload['execution_preview']['mode'] ?? ($run->parity_payload['dry_run'] ?? false ? 'dry_run' : 'observe_only'),
                'execution_preview' => is_array($run->parity_payload['execution_preview'] ?? null) ? $run->parity_payload['execution_preview'] : [],
                'laravel_scenario' => $run->laravel_payload['scenario']['id'] ?? null,
                'legacy_scenario' => $run->legacy_payload['scenario']['id'] ?? null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $limit = 250): array
    {
        $rows = $this->recent($limit, false);
        $total = count($rows);
        $mismatchRows = array_values(array_filter($rows, static fn (array $row): bool => !empty($row['parity']['mismatch_reasons'])));

        $reasonCounts = [];
        $scenarioPairs = [];
        foreach ($mismatchRows as $row) {
            foreach (($row['parity']['mismatch_reasons'] ?? []) as $reason) {
                $reason = (string) $reason;
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }

            $pair = sprintf('%s -> %s', $row['laravel_scenario'] ?? '-', $row['legacy_scenario'] ?? '-');
            $scenarioPairs[$pair] = ($scenarioPairs[$pair] ?? 0) + 1;
        }

        arsort($reasonCounts);
        arsort($scenarioPairs);

        return [
            'total_runs' => $total,
            'mismatch_runs' => count($mismatchRows),
            'dry_run_runs' => count(array_filter($rows, static fn (array $row): bool => ($row['execution_mode'] ?? null) === 'dry_run')),
            'mismatch_rate' => $total > 0 ? round((count($mismatchRows) / $total) * 100, 2) : null,
            'top_mismatch_reasons' => array_map(
                static fn (string $reason, int $count): array => ['reason' => $reason, 'count' => $count],
                array_keys(array_slice($reasonCounts, 0, 6, true)),
                array_values(array_slice($reasonCounts, 0, 6, true))
            ),
            'top_scenario_gaps' => array_map(
                static fn (string $pair, int $count): array => ['pair' => $pair, 'count' => $count],
                array_keys(array_slice($scenarioPairs, 0, 6, true)),
                array_values(array_slice($scenarioPairs, 0, 6, true))
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(int $limit = 250): array
    {
        $summary = $this->summary($limit);
        $totalRuns = (int) ($summary['total_runs'] ?? 0);
        $dryRunRuns = (int) ($summary['dry_run_runs'] ?? 0);
        $mismatchRuns = (int) ($summary['mismatch_runs'] ?? 0);
        $mismatchRate = $totalRuns > 0 ? ($mismatchRuns / $totalRuns) * 100 : null;

        $minimumRuns = 20;
        $maximumMismatchRate = 10.0;

        $checks = [
            [
                'key' => 'minimum_shadow_runs',
                'label' => 'Minimum shadow runs',
                'expected' => '>=' . $minimumRuns,
                'actual' => $totalRuns,
                'passed' => $totalRuns >= $minimumRuns,
            ],
            [
                'key' => 'dry_run_enabled',
                'label' => 'Dry-run enabled',
                'expected' => 'true',
                'actual' => $dryRunRuns > 0 ? 'true' : ((bool) config('whatsapp.migration.automation.dry_run', true) ? 'configured' : 'false'),
                'passed' => (bool) config('whatsapp.migration.automation.dry_run', true),
            ],
            [
                'key' => 'mismatch_rate',
                'label' => 'Mismatch rate',
                'expected' => '<=' . $maximumMismatchRate . '%',
                'actual' => $mismatchRate !== null ? round($mismatchRate, 2) . '%' : 'n/a',
                'passed' => $mismatchRate !== null && $mismatchRate <= $maximumMismatchRate,
            ],
        ];

        $blocking = array_values(array_map(
            static fn (array $check): string => (string) $check['key'],
            array_filter($checks, static fn (array $check): bool => empty($check['passed']))
        ));

        return [
            'ready_for_phase_7' => $blocking === [],
            'blocking_checks' => $blocking,
            'checks' => $checks,
            'summary' => $summary,
        ];
    }

    private function resolveSource(string $base): string
    {
        return (bool) config('whatsapp.migration.automation.dry_run', true)
            ? $base . '_dry_run'
            : $base . '_observe';
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
