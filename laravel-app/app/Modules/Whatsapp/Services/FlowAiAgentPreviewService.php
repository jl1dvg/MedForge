<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAiAgentRun;
use Illuminate\Support\Str;

class FlowAiAgentPreviewService
{
    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService = new KnowledgeBaseService(),
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
        $classification = $this->classify($text, $documents, $action);
        $confidence = $this->confidence($text, $documents, $classification);
        $suggestedHandoff = $confidence < (float) ($action['handoff_threshold'] ?? 0.45);
        $response = $this->buildResponse($documents, $classification, $action);
        $contextAfter = array_merge($contextBefore, [
            'ai_last_classification' => $classification,
            'ai_last_confidence' => $confidence,
            'ai_last_response' => $response,
            'ai_last_source_count' => count($documents),
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
            'sources' => $documents,
            'filters' => $filters,
            'tools' => [],
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
                'high_confidence' => WhatsappAiAgentRun::query()->where('confidence', '>=', 0.75)->count(),
                'avg_confidence' => round((float) (WhatsappAiAgentRun::query()->avg('confidence') ?? 0), 2),
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
}
