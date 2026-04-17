<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappKnowledgeDocument;
use Illuminate\Support\Str;
use RuntimeException;

class KnowledgeBaseService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDocuments(string $search = '', array $filters = [], int $limit = 25): array
    {
        $limit = max(1, min($limit, 100));
        $search = trim($search);

        $query = WhatsappKnowledgeDocument::query()
            ->orderByRaw("case when status = 'published' then 0 else 1 end")
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', (string) $filters['status']);
        }

        if (($filters['source_type'] ?? '') !== '') {
            $query->where('source_type', (string) $filters['source_type']);
        }

        /** @var array<int, WhatsappKnowledgeDocument> $documents */
        $documents = $query->get()->all();

        foreach (['sede', 'especialidad', 'tipo_contenido', 'audiencia', 'vigencia'] as $field) {
            $expected = trim((string) ($filters[$field] ?? ''));
            if ($expected === '') {
                continue;
            }

            $expected = Str::lower($expected);
            $documents = array_values(array_filter($documents, function (WhatsappKnowledgeDocument $document) use ($field, $expected): bool {
                $metadata = is_array($document->metadata) ? $document->metadata : [];
                $current = Str::lower(trim((string) ($metadata[$field] ?? '')));

                return $current === $expected;
            }));
        }

        if ($search !== '') {
            $needle = Str::lower($search);
            $documents = array_values(array_filter($documents, function (WhatsappKnowledgeDocument $document) use ($needle): bool {
                $metadata = is_array($document->metadata) ? $document->metadata : [];
                $haystack = Str::lower(implode(' ', array_filter([
                    $document->title,
                    $document->summary,
                    $document->content,
                    $document->source_label,
                    $metadata['sede'] ?? null,
                    $metadata['especialidad'] ?? null,
                    $metadata['tipo_contenido'] ?? null,
                    $metadata['audiencia'] ?? null,
                ])));

                return str_contains($haystack, $needle);
            }));
        }

        $documents = array_slice($documents, 0, $limit);

        return array_map(fn (WhatsappKnowledgeDocument $document): array => $this->serializeDocument($document), $documents);
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(int $limit = 8): array
    {
        return [
            'stats' => [
                'total' => WhatsappKnowledgeDocument::query()->count(),
                'published' => WhatsappKnowledgeDocument::query()->where('status', 'published')->count(),
                'draft' => WhatsappKnowledgeDocument::query()->where('status', 'draft')->count(),
                'sources' => WhatsappKnowledgeDocument::query()->distinct('source_type')->count('source_type'),
            ],
            'documents' => $this->listDocuments('', [], $limit),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createDocument(array $payload, ?int $userId = null): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        if ($title === '' || $content === '') {
            throw new RuntimeException('La Knowledge Base requiere título y contenido.');
        }

        $slug = $this->uniqueSlug($title);
        $metadata = [
            'sede' => trim((string) ($payload['sede'] ?? '')),
            'especialidad' => trim((string) ($payload['especialidad'] ?? '')),
            'tipo_contenido' => trim((string) ($payload['tipo_contenido'] ?? 'faq')),
            'audiencia' => trim((string) ($payload['audiencia'] ?? 'paciente')),
            'vigencia' => trim((string) ($payload['vigencia'] ?? 'vigente')),
        ];

        $document = WhatsappKnowledgeDocument::query()->create([
            'title' => $title,
            'slug' => $slug,
            'summary' => $this->buildSummary($content),
            'content' => $content,
            'status' => trim((string) ($payload['status'] ?? 'draft')) ?: 'draft',
            'source_type' => trim((string) ($payload['source_type'] ?? 'manual')) ?: 'manual',
            'source_label' => trim((string) ($payload['source_label'] ?? 'Flowmaker KB')),
            'metadata' => $metadata,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
            'published_at' => (($payload['status'] ?? 'draft') === 'published') ? now() : null,
        ]);

        return $this->serializeDocument($document);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'kb-doc';
        $counter = 1;

        while (WhatsappKnowledgeDocument::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = ($base !== '' ? $base : 'kb-doc') . '-' . $counter;
        }

        return $slug;
    }

    private function buildSummary(string $content): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $content) ?? ''), 180, '…');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(WhatsappKnowledgeDocument $document): array
    {
        $metadata = is_array($document->metadata) ? $document->metadata : [];

        return [
            'id' => (int) $document->id,
            'title' => (string) $document->title,
            'slug' => (string) $document->slug,
            'summary' => $document->summary,
            'content' => (string) $document->content,
            'status' => (string) $document->status,
            'source_type' => (string) $document->source_type,
            'source_label' => $document->source_label,
            'metadata' => $metadata,
            'published_at' => optional($document->published_at)?->format('Y-m-d H:i:s'),
            'updated_at' => optional($document->updated_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
