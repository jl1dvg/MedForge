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

        foreach (['sede', 'especialidad', 'tipo_contenido', 'audiencia', 'vigencia', 'nivel_urgencia', 'destino'] as $field) {
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
            $tokens = $this->searchTokens($search);
            $documents = array_values(array_filter($documents, function (WhatsappKnowledgeDocument $document) use ($tokens): bool {
                $metadata = is_array($document->metadata) ? $document->metadata : [];
                $haystack = $this->normalizeSearchText(implode(' ', array_filter([
                    $document->title,
                    $document->summary,
                    $document->content,
                    $document->source_label,
                    $this->metadataSearchText($metadata),
                ])));

                if ($tokens === []) {
                    return false;
                }

                $matches = 0;
                foreach ($tokens as $token) {
                    if (!str_contains($haystack, $token)) {
                        continue;
                    }
                    $matches++;
                }

                $minimumMatches = max(1, (int) ceil(count($tokens) * 0.6));

                return $matches >= $minimumMatches;
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
        $metadata = $this->normalizeMetadata($payload);

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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateDocument(int $documentId, array $payload, ?int $userId = null): array
    {
        /** @var WhatsappKnowledgeDocument|null $document */
        $document = WhatsappKnowledgeDocument::query()->find($documentId);
        if (!$document) {
            throw new RuntimeException('El documento de Knowledge Base no existe.');
        }

        $title = trim((string) ($payload['title'] ?? $document->title));
        $content = trim((string) ($payload['content'] ?? $document->content));
        if ($title === '' || $content === '') {
            throw new RuntimeException('La Knowledge Base requiere título y contenido.');
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata = $this->normalizeMetadata($payload, $metadata);

        $previousStatus = (string) $document->status;
        $status = trim((string) ($payload['status'] ?? $previousStatus)) ?: 'draft';

        $document->fill([
            'title' => $title,
            'summary' => $this->buildSummary($content),
            'content' => $content,
            'status' => $status,
            'source_type' => trim((string) ($payload['source_type'] ?? $document->source_type)) ?: 'manual',
            'source_label' => trim((string) ($payload['source_label'] ?? $document->source_label)),
            'metadata' => $metadata,
            'updated_by_user_id' => $userId,
        ]);

        if ($status === 'published' && $previousStatus !== 'published') {
            $document->published_at = now();
        }

        if ($status !== 'published') {
            $document->published_at = null;
        }

        $document->save();

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
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function normalizeMetadata(array $payload, array $existing = []): array
    {
        $core = [
            'sede' => trim((string) ($payload['sede'] ?? ($existing['sede'] ?? ''))),
            'especialidad' => trim((string) ($payload['especialidad'] ?? ($existing['especialidad'] ?? ''))),
            'tipo_contenido' => trim((string) ($payload['tipo_contenido'] ?? ($existing['tipo_contenido'] ?? 'faq'))),
            'audiencia' => trim((string) ($payload['audiencia'] ?? ($existing['audiencia'] ?? 'paciente'))),
            'vigencia' => trim((string) ($payload['vigencia'] ?? ($existing['vigencia'] ?? 'vigente'))),
        ];

        $extraPayload = $payload['metadata'] ?? [];
        $extraExisting = $existing;
        foreach (array_keys($core) as $reservedKey) {
            unset($extraExisting[$reservedKey]);
        }

        $extra = is_array($extraExisting) ? $extraExisting : [];
        if (is_array($extraPayload)) {
            foreach ($extraPayload as $key => $value) {
                if (!is_string($key) || $key === '' || array_key_exists($key, $core)) {
                    continue;
                }

                $extra[$key] = $this->normalizeMetadataValue($value);
            }
        }

        return array_merge($core, $extra);
    }

    private function normalizeMetadataValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(function (mixed $item): string {
                if (is_scalar($item)) {
                    return trim((string) $item);
                }

                return '';
            }, $value), static fn (string $item): bool => $item !== ''));
        }

        return $value;
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

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadataSearchText(array $metadata): string
    {
        $parts = [];

        foreach ($metadata as $value) {
            if (is_string($value) && trim($value) !== '') {
                $parts[] = $value;
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_scalar($item) && trim((string) $item) !== '') {
                        $parts[] = (string) $item;
                    }
                }
            }
        }

        return implode(' ', $parts);
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
