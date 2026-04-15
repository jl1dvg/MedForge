<?php

namespace App\Modules\Whatsapp\Services;

class FlowRuntimeShadowCompareService
{
    public function __construct(
        private readonly FlowmakerService $flowmakerService = new FlowmakerService(),
        private readonly LegacyFlowSourceService $legacyFlowSource = new LegacyFlowSourceService(),
        private readonly FlowRuntimePreviewService $previewService = new FlowRuntimePreviewService(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function compare(array $input): array
    {
        $laravelFlow = $this->flowmakerService->getActiveFlowPayload();
        $legacy = $this->legacyFlowSource->load();
        $legacyFlow = is_array($legacy['flow'] ?? null) ? $legacy['flow'] : [];

        $laravelResult = $this->previewService->simulateAgainstFlow($laravelFlow, $input);
        $legacyResult = $this->previewService->simulateAgainstFlow($legacyFlow, $input);

        $laravelActionTypes = $this->extractActionTypes($laravelResult);
        $legacyActionTypes = $this->extractActionTypes($legacyResult);
        $parity = [
            'same_match' => (bool) ($laravelResult['matched'] ?? false) === (bool) ($legacyResult['matched'] ?? false),
            'same_scenario' => ($laravelResult['scenario']['id'] ?? null) === ($legacyResult['scenario']['id'] ?? null),
            'same_handoff' => (bool) ($laravelResult['handoff_requested'] ?? false) === (bool) ($legacyResult['handoff_requested'] ?? false),
            'same_action_types' => $laravelActionTypes === $legacyActionTypes,
        ];
        $parity['mismatch_reasons'] = $this->mismatchReasons($parity, $laravelResult, $legacyResult);
        $executionPreview = $this->executionPreview($laravelResult);

        return [
            'ok' => true,
            'flags' => [
                'automation_enabled' => (bool) config('whatsapp.migration.automation.enabled', false),
                'compare_with_legacy' => (bool) config('whatsapp.migration.automation.compare_with_legacy', true),
                'fallback_to_legacy' => (bool) config('whatsapp.migration.automation.fallback_to_legacy', true),
                'dry_run' => (bool) config('whatsapp.migration.automation.dry_run', true),
            ],
            'sources' => [
                'laravel' => 'active_version',
                'legacy' => (string) ($legacy['source'] ?? 'empty'),
            ],
            'parity' => $parity,
            'execution_preview' => $executionPreview,
            'laravel' => $laravelResult,
            'legacy' => $legacyResult,
        ];
    }

    /**
     * @param array<string, mixed> $parity
     * @param array<string, mixed> $laravelResult
     * @param array<string, mixed> $legacyResult
     * @return array<int, string>
     */
    public function mismatchReasons(array $parity, array $laravelResult, array $legacyResult): array
    {
        $reasons = [];

        if (empty($parity['same_match'])) {
            $reasons[] = 'match';
        }

        if (empty($parity['same_scenario'])) {
            $reasons[] = 'scenario';
        }

        if (empty($parity['same_handoff'])) {
            $reasons[] = 'handoff';
        }

        if (empty($parity['same_action_types'])) {
            $reasons[] = 'action_types';
        }

        if (($laravelResult['matched'] ?? false) && empty($laravelResult['actions'])) {
            $reasons[] = 'laravel_without_actions';
        }

        if (($legacyResult['matched'] ?? false) && empty($legacyResult['actions'])) {
            $reasons[] = 'legacy_without_actions';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string, mixed> $laravelResult
     * @return array<string, mixed>
     */
    public function executionPreview(array $laravelResult): array
    {
        $actions = is_array($laravelResult['actions'] ?? null) ? $laravelResult['actions'] : [];

        return [
            'mode' => (bool) config('whatsapp.migration.automation.dry_run', true) ? 'dry_run' : 'observe_only',
            'matched' => (bool) ($laravelResult['matched'] ?? false),
            'scenario_id' => $laravelResult['scenario']['id'] ?? null,
            'action_types' => $this->extractActionTypes($laravelResult),
            'handoff_requested' => (bool) ($laravelResult['handoff_requested'] ?? false),
            'would_send_count' => count(array_filter($actions, static fn ($action): bool => in_array((string) ($action['type'] ?? ''), [
                'send_message',
                'send_buttons',
                'send_list',
                'send_template',
                'send_sequence',
            ], true))),
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<int, string>
     */
    private function extractActionTypes(array $result): array
    {
        $types = [];

        foreach (($result['actions'] ?? []) as $action) {
            if (!is_array($action) || !isset($action['type']) || !is_string($action['type'])) {
                continue;
            }

            $types[] = $action['type'];
        }

        return $types;
    }
}
