<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAiAgentRun;
use Illuminate\Support\Str;

class FlowAiAgentPreviewService
{
    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService = new KnowledgeBaseService(),
        private readonly FlowAiAgentToolRegistryService $toolRegistry = new FlowAiAgentToolRegistryService(),
    ) {
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $input
     * @param array<string, mixed> $contextBefore
     * @return array<string, mixed>
     */
    public function preview(array $action, array $input, array $contextBefore = []): array
    {
        $text = trim((string) ($input['text'] ?? ''));
        $filters = $this->resolveFilters($action, $contextBefore);
        $documents = $this->knowledgeBaseService->listDocuments($text, array_merge(['status' => 'published'], $filters), 5);
        $tools = $this->toolRegistry->run($input, $contextBefore, is_array($action['tools'] ?? null) ? $action['tools'] : []);
        $classification = $this->classify($text, $documents, $action);
        $confidence = $this->confidence($text, $documents, $classification);
        $fallbackMessage = trim((string) ($action['fallback_message'] ?? 'No encontré grounding suficiente en la Knowledge Base para responder con seguridad.'));
        $draftResponse = $this->buildResponse($documents, $classification, $action);
        $evaluation = $this->evaluate($text, $documents, $draftResponse, $classification, $confidence, $tools, $contextBefore, $action);
        $handoffReasons = $this->resolveHandoffReasons($text, $confidence, $tools, $evaluation, $action);
        $fallbackUsed = $this->shouldUseFallback($confidence, $evaluation, $action);
        $response = $fallbackUsed ? $fallbackMessage : $draftResponse;
        $suggestedHandoff = $handoffReasons !== [];
        $decision = $this->resolveDecision($fallbackUsed, $suggestedHandoff);
        $scorecard = [
            'confidence' => $confidence,
            'grounding' => (float) ($evaluation['grounding']['score'] ?? 0),
            'safety' => (float) ($evaluation['safety']['score'] ?? 0),
            'handoff' => (float) ($evaluation['handoff']['score'] ?? 0),
            'overall' => round(((float) $confidence + (float) ($evaluation['grounding']['score'] ?? 0) + (float) ($evaluation['safety']['score'] ?? 0)) / 3, 2),
        ];
        $contextAfter = array_merge($contextBefore, [
            'ai_last_classification' => $classification,
            'ai_last_confidence' => $confidence,
            'ai_last_response' => $response,
            'ai_last_source_count' => count($documents),
            'ai_last_decision' => $decision,
            'ai_last_fallback_used' => $fallbackUsed,
            'ai_last_handoff_reasons' => $handoffReasons,
            'ai_last_scores' => $scorecard,
            'ai_last_evaluation' => $evaluation,
            'ai_tools' => $tools,
        ]);

        $run = WhatsappAiAgentRun::query()->create([
            'wa_number' => trim((string) ($input['wa_number'] ?? '')),
            'scenario_id' => $action['scenario_id'] ?? null,
            'action_index' => max(0, (int) ($action['action_index'] ?? 0)),
            'input_text' => $text,
            'filters' => $filters,
            'matched_documents' => array_map(static fn (array $document): array => [
                'id' => $document['id'] ?? null,
                'title' => $document['title'] ?? null,
                'slug' => $document['slug'] ?? null,
                'status' => $document['status'] ?? null,
                'source_type' => $document['source_type'] ?? null,
                'metadata' => $document['metadata'] ?? [],
            ], $documents),
            'response_text' => $response,
            'classification' => $classification,
            'confidence' => $confidence,
            'suggested_handoff' => $suggestedHandoff,
            'decision' => $decision,
            'fallback_used' => $fallbackUsed,
            'handoff_reasons' => $handoffReasons,
            'scorecard' => $scorecard,
            'evaluation' => $evaluation,
            'context_before' => $contextBefore,
            'context_after' => $contextAfter,
            'source' => 'preview',
        ]);

        return [
            'ok' => true,
            'mode' => 'preview',
            'run_id' => (int) $run->id,
            'response' => $response,
            'classification' => $classification,
            'confidence' => $confidence,
            'suggested_handoff' => $suggestedHandoff,
            'decision' => $decision,
            'fallback_used' => $fallbackUsed,
            'handoff_reasons' => $handoffReasons,
            'scores' => $scorecard,
            'evaluation' => $evaluation,
            'sources' => $documents,
            'filters' => $filters,
            'tools' => $tools,
            'context_after' => $contextAfter,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(int $limit = 8): array
    {
        return [
            'stats' => [
                'total_runs' => WhatsappAiAgentRun::query()->count(),
                'handoff_suggested' => WhatsappAiAgentRun::query()->where('suggested_handoff', true)->count(),
                'fallback_runs' => WhatsappAiAgentRun::query()->where('fallback_used', true)->count(),
                'high_confidence' => WhatsappAiAgentRun::query()->where('confidence', '>=', 0.75)->count(),
                'avg_confidence' => round((float) (WhatsappAiAgentRun::query()->avg('confidence') ?? 0), 2),
                'avg_grounding' => round((float) (WhatsappAiAgentRun::query()->avg('scorecard->grounding') ?? 0), 2),
                'avg_safety' => round((float) (WhatsappAiAgentRun::query()->avg('scorecard->safety') ?? 0), 2),
            ],
            'runs' => $this->recent($limit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 12): array
    {
        $limit = max(1, min($limit, 50));

        return WhatsappAiAgentRun::query()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsappAiAgentRun $run): array => [
                'id' => (int) $run->id,
                'wa_number' => $run->wa_number,
                'scenario_id' => $run->scenario_id,
                'action_index' => (int) $run->action_index,
                'input_text' => $run->input_text,
                'classification' => $run->classification,
                'confidence' => (float) $run->confidence,
                'suggested_handoff' => (bool) $run->suggested_handoff,
                'decision' => $run->decision,
                'fallback_used' => (bool) ($run->fallback_used ?? false),
                'handoff_reasons' => is_array($run->handoff_reasons) ? $run->handoff_reasons : [],
                'scores' => is_array($run->scorecard) ? $run->scorecard : [],
                'evaluation' => is_array($run->evaluation) ? $run->evaluation : [],
                'response_text' => $run->response_text,
                'filters' => is_array($run->filters) ? $run->filters : [],
                'matched_documents' => is_array($run->matched_documents) ? $run->matched_documents : [],
                'created_at' => $run->created_at?->format('Y-m-d H:i:s'),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $contextBefore
     * @return array<string, string>
     */
    private function resolveFilters(array $action, array $contextBefore): array
    {
        $filters = [];
        $raw = is_array($action['kb_filters'] ?? null) ? $action['kb_filters'] : [];

        foreach (['sede', 'especialidad', 'tipo_contenido', 'audiencia', 'vigencia'] as $field) {
            $value = trim((string) ($raw[$field] ?? $contextBefore[$field] ?? ''));
            if ($value !== '') {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @param array<string, mixed> $action
     */
    private function buildResponse(array $documents, string $classification, array $action): string
    {
        if ($documents === []) {
            return trim((string) ($action['fallback_message'] ?? 'No encontré grounding suficiente en la Knowledge Base para responder con seguridad.'));
        }

        $first = $documents[0];
        $prefix = trim((string) ($action['intro'] ?? 'Respuesta sugerida con grounding:'));
        $summary = trim((string) ($first['summary'] ?? ''));
        $content = trim((string) ($first['content'] ?? ''));
        $body = $summary !== '' ? $summary : Str::limit($content, 220, '…');

        return trim($prefix . ' ' . $body . ' [' . $classification . ']');
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @param array<string, mixed> $action
     */
    private function classify(string $text, array $documents, array $action): string
    {
        if (($action['classification'] ?? '') !== '') {
            return (string) $action['classification'];
        }

        $normalized = Str::lower($text);

        return match (true) {
            str_contains($normalized, 'cita') || str_contains($normalized, 'agend') => 'scheduling',
            str_contains($normalized, 'seguro') || str_contains($normalized, 'cobertura') => 'coverage',
            str_contains($normalized, 'consent') || str_contains($normalized, 'datos') => 'consent',
            str_contains($normalized, 'resultado') || str_contains($normalized, 'examen') => 'results',
            $documents !== [] => (string) (($documents[0]['metadata']['tipo_contenido'] ?? 'general') ?: 'general'),
            default => 'general',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     */
    private function confidence(string $text, array $documents, string $classification): float
    {
        if ($documents === []) {
            return 0.22;
        }

        $normalized = Str::lower(trim($text));
        $score = 0.55 + min(0.25, count($documents) * 0.06);
        if ($normalized !== '' && str_contains(Str::lower((string) ($documents[0]['content'] ?? '')), $normalized)) {
            $score += 0.1;
        }
        if ($classification !== 'general') {
            $score += 0.05;
        }

        return round(min(0.97, $score), 2);
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @param array<string, mixed> $tools
     * @param array<string, mixed> $contextBefore
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function evaluate(
        string $text,
        array $documents,
        string $response,
        string $classification,
        float $confidence,
        array $tools,
        array $contextBefore,
        array $action
    ): array {
        $groundingReasons = [];
        $groundingScore = $documents === [] ? 0.1 : min(0.95, 0.55 + (count($documents) * 0.1));
        $normalized = Str::lower(trim($text));
        $primaryDocument = $documents[0] ?? [];
        $primaryContent = Str::lower(trim((string) ($primaryDocument['content'] ?? '')));

        if ($documents === []) {
            $groundingReasons[] = 'no_documents';
        }
        if ($normalized !== '' && $primaryContent !== '' && str_contains($primaryContent, $normalized)) {
            $groundingScore += 0.1;
        }
        if ($classification !== 'general') {
            $groundingScore += 0.05;
        }
        $groundingScore = round(min(0.97, $groundingScore), 2);

        $safetyReasons = [];
        $safetyScore = 0.92;
        if (str_contains($response, '{{')) {
            $safetyScore -= 0.25;
            $safetyReasons[] = 'unresolved_placeholders';
        }
        if ($documents === [] && !$this->isFallbackResponse($response, $action)) {
            $safetyScore -= 0.45;
            $safetyReasons[] = 'ungrounded_freeform_response';
        }
        if (!($tools['window_status']['can_send_freeform'] ?? true)) {
            $safetyScore -= 0.15;
            $safetyReasons[] = 'window_closed';
        }
        if ($confidence < 0.35) {
            $safetyScore -= 0.1;
            $safetyReasons[] = 'low_confidence';
        }
        $safetyScore = round(max(0.05, $safetyScore), 2);

        $handoffReasons = [];
        if ($this->actionRequestsHandoff($action)) {
            $handoffReasons[] = 'node_requested_handoff';
        }
        if ($confidence < (float) ($action['handoff_threshold'] ?? 0.45)) {
            $handoffReasons[] = 'low_confidence';
        }
        if ($groundingScore < 0.45) {
            $handoffReasons[] = 'no_grounding';
        }
        if ($safetyScore < 0.6) {
            $handoffReasons[] = 'safety_guardrail';
        }
        if (!($tools['window_status']['can_send_freeform'] ?? true)) {
            $handoffReasons[] = 'window_closed';
        }
        if ($this->userExplicitlyRequestsHuman($text)) {
            $handoffReasons[] = 'user_requested_human';
        }

        $handoffWeight = 0.0;
        foreach (array_unique($handoffReasons) as $reason) {
            $handoffWeight += match ($reason) {
                'node_requested_handoff' => 0.25,
                'low_confidence' => 0.35,
                'no_grounding' => 0.30,
                'safety_guardrail' => 0.35,
                'window_closed' => 0.15,
                'user_requested_human' => 0.30,
                default => 0.1,
            };
        }

        return [
            'grounding' => [
                'score' => $groundingScore,
                'status' => $groundingScore >= 0.75 ? 'strong' : ($groundingScore >= 0.45 ? 'partial' : 'weak'),
                'reasons' => array_values(array_unique($groundingReasons)),
                'matched_sources' => count($documents),
            ],
            'safety' => [
                'score' => $safetyScore,
                'status' => $safetyScore >= 0.75 ? 'safe' : ($safetyScore >= 0.6 ? 'review' : 'blocked'),
                'reasons' => array_values(array_unique($safetyReasons)),
            ],
            'handoff' => [
                'score' => round(min(0.99, $handoffWeight), 2),
                'reasons' => array_values(array_unique($handoffReasons)),
                'should_handoff' => $handoffReasons !== [],
            ],
            'context' => [
                'state' => $contextBefore['state'] ?? null,
                'classification' => $classification,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $tools
     * @param array<string, mixed> $evaluation
     * @param array<string, mixed> $action
     * @return array<int, string>
     */
    private function resolveHandoffReasons(
        string $text,
        float $confidence,
        array $tools,
        array $evaluation,
        array $action
    ): array {
        $reasons = is_array($evaluation['handoff']['reasons'] ?? null) ? $evaluation['handoff']['reasons'] : [];

        if ($confidence < (float) ($action['handoff_threshold'] ?? 0.45) && !in_array('low_confidence', $reasons, true)) {
            $reasons[] = 'low_confidence';
        }
        if (!($tools['window_status']['can_send_freeform'] ?? true) && !in_array('window_closed', $reasons, true)) {
            $reasons[] = 'window_closed';
        }
        if ($this->userExplicitlyRequestsHuman($text) && !in_array('user_requested_human', $reasons, true)) {
            $reasons[] = 'user_requested_human';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string, mixed> $evaluation
     * @param array<string, mixed> $action
     */
    private function shouldUseFallback(float $confidence, array $evaluation, array $action): bool
    {
        $replyThreshold = (float) ($action['reply_threshold'] ?? $action['handoff_threshold'] ?? 0.45);

        return $confidence < $replyThreshold
            || (float) ($evaluation['grounding']['score'] ?? 0) < 0.45
            || (float) ($evaluation['safety']['score'] ?? 0) < 0.6;
    }

    private function resolveDecision(bool $fallbackUsed, bool $suggestedHandoff): string
    {
        return match (true) {
            $fallbackUsed && $suggestedHandoff => 'fallback_handoff',
            $fallbackUsed => 'fallback',
            $suggestedHandoff => 'respond_handoff',
            default => 'respond',
        };
    }

    /**
     * @param array<string, mixed> $action
     */
    private function actionRequestsHandoff(array $action): bool
    {
        foreach (['handoff', 'request_handoff', 'force_handoff'] as $field) {
            if (array_key_exists($field, $action) && filter_var($action[$field], FILTER_VALIDATE_BOOL)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function isFallbackResponse(string $response, array $action): bool
    {
        return trim($response) === trim((string) ($action['fallback_message'] ?? 'No encontré grounding suficiente en la Knowledge Base para responder con seguridad.'));
    }

    private function userExplicitlyRequestsHuman(string $text): bool
    {
        $normalized = Str::lower(trim($text));
        if ($normalized === '') {
            return false;
        }

        foreach (['humano', 'asesor', 'agente', 'persona', 'operador'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
