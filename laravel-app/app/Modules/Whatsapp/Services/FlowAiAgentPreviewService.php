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
        $draftResponse = $this->buildResponse($text, $documents, $classification, $action);
        $evaluation = $this->evaluate($text, $documents, $draftResponse, $classification, $confidence, $tools, $contextBefore, $action);
        $handoffReasons = $this->resolveHandoffReasons($text, $confidence, $tools, $evaluation, $action, $documents);
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
        $primaryDocument = $documents[0] ?? [];
        $triageMetadata = is_array($primaryDocument['metadata'] ?? null) ? $primaryDocument['metadata'] : [];
        $fallbackSpecialty = trim((string) ($action['fallback_specialty'] ?? ''));
        $fallbackDestination = trim((string) ($action['fallback_destination'] ?? 'agenda'));
        $suggestedSpecialty = trim((string) ($triageMetadata['especialidad'] ?? ''));
        $triageDestination = trim((string) ($triageMetadata['destino'] ?? ''));
        $triageUrgency = trim((string) ($triageMetadata['nivel_urgencia'] ?? ''));

        if ($suggestedSpecialty === '' && $fallbackUsed && $fallbackSpecialty !== '') {
            $suggestedSpecialty = $fallbackSpecialty;
            $triageDestination = $triageDestination !== '' ? $triageDestination : $fallbackDestination;
            $triageUrgency = $triageUrgency !== '' ? $triageUrgency : 'normal';
        }

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
            'triage_especialidad_sugerida' => $suggestedSpecialty,
            'triage_destino' => $triageDestination,
            'triage_nivel_urgencia' => $triageUrgency,
        ]);

        if ($suggestedSpecialty !== '') {
            $contextAfter['subespecialidad'] = $suggestedSpecialty;
            $contextAfter['subespecialidad_nombre'] = $suggestedSpecialty;
        }

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
    private function buildResponse(string $text, array $documents, string $classification, array $action): string
    {
        if ($documents === []) {
            return trim((string) ($action['fallback_message'] ?? 'No encontré grounding suficiente en la Knowledge Base para responder con seguridad.'));
        }

        $first = $documents[0];
        $content = trim((string) ($first['content'] ?? ''));
        $body = $this->bestGroundedAnswer($text, $content);

        if ($body === '') {
            $summary = trim((string) ($first['summary'] ?? ''));
            $body = $summary !== '' ? $summary : Str::limit($content, 220, '…');
        }

        return trim($body);
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

    private function bestGroundedAnswer(string $text, string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (preg_match('/Mensaje breve sugerido al paciente:\s*(.+)$/uis', $content, $matches) === 1) {
            $suggested = trim((string) ($matches[1] ?? ''));
            if ($suggested !== '') {
                return Str::limit($suggested, 700, '…');
            }
        }

        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n{2,}/', $content) ?: [])));
        if ($paragraphs === []) {
            $paragraphs = [$content];
        }

        $tokens = $this->searchTokens($text);
        $bestParagraph = '';
        $bestScore = -1;

        foreach ($paragraphs as $paragraph) {
            $haystack = $this->normalizeSearchText($paragraph);
            $score = 0;

            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestParagraph = $paragraph;
            }
        }

        $answer = trim(preg_replace('/^[^:\n]{3,80}:\s*/u', '', $bestParagraph) ?? $bestParagraph);

        if ($this->looksLikeYesNoQuestion($text) && $this->paragraphSupportsAffirmative($answer)) {
            $answer = 'Sí. ' . lcfirst($answer);
        }

        return Str::limit($answer, 700, '…');
    }

    /**
     * @return array<int, string>
     */
    private function searchTokens(string $search): array
    {
        $normalized = $this->normalizeSearchText($search);
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $stopWords = [
            'a', 'al', 'con', 'de', 'del', 'el', 'en', 'la', 'las', 'lo', 'los',
            'para', 'por', 'que', 'se', 'si', 'su', 'sus', 'un', 'una', 'unas',
            'unos', 'y',
        ];

        return array_values(array_unique(array_filter($tokens, static function (string $token) use ($stopWords): bool {
            return mb_strlen($token) >= 3 && !in_array($token, $stopWords, true);
        })));
    }

    private function normalizeSearchText(string $value): string
    {
        $value = Str::ascii(Str::lower($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function looksLikeYesNoQuestion(string $text): bool
    {
        $normalized = $this->normalizeSearchText($text);

        return str_starts_with($normalized, 'atienden')
            || str_starts_with($normalized, 'hay')
            || str_starts_with($normalized, 'tienen')
            || str_starts_with($normalized, 'puedo')
            || str_starts_with($normalized, 'se puede');
    }

    private function paragraphSupportsAffirmative(string $paragraph): bool
    {
        $normalized = $this->normalizeSearchText($paragraph);

        return str_contains($normalized, 'atiende')
            || str_contains($normalized, 'pueden')
            || str_contains($normalized, 'puede')
            || str_contains($normalized, 'disponibilidad');
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
        array $action,
        array $documents = []
    ): array {
        $reasons = is_array($evaluation['handoff']['reasons'] ?? null) ? $evaluation['handoff']['reasons'] : [];
        $primaryDocument = $documents[0] ?? [];
        $metadata = is_array($primaryDocument['metadata'] ?? null) ? $primaryDocument['metadata'] : [];
        $destination = trim((string) ($metadata['destino'] ?? ''));
        $urgency = trim((string) ($metadata['nivel_urgencia'] ?? ''));
        $defaultRouteWithoutHandoff = filter_var($action['default_route_without_handoff'] ?? false, FILTER_VALIDATE_BOOL);
        $fallbackSpecialty = trim((string) ($action['fallback_specialty'] ?? ''));

        if (
            $confidence < (float) ($action['handoff_threshold'] ?? 0.45)
            && !in_array('low_confidence', $reasons, true)
            && !($defaultRouteWithoutHandoff && $fallbackSpecialty !== '' && $documents === [])
        ) {
            $reasons[] = 'low_confidence';
        }
        if (!($tools['window_status']['can_send_freeform'] ?? true) && !in_array('window_closed', $reasons, true)) {
            $reasons[] = 'window_closed';
        }
        if ($this->userExplicitlyRequestsHuman($text) && !in_array('user_requested_human', $reasons, true)) {
            $reasons[] = 'user_requested_human';
        }
        if ($destination !== '' && str_starts_with($destination, 'handoff') && !in_array('triage_urgent', $reasons, true)) {
            $reasons[] = 'triage_urgent';
        }
        if (in_array($urgency, ['emergente', 'alta'], true) && ($action['handoff_on_high_urgency'] ?? true) && !in_array('triage_high_urgency', $reasons, true)) {
            $reasons[] = 'triage_high_urgency';
        }

        if ($defaultRouteWithoutHandoff && $fallbackSpecialty !== '' && $documents === []) {
            $reasons = array_values(array_filter($reasons, static fn (string $reason): bool => !in_array($reason, ['low_confidence', 'no_grounding'], true)));
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
