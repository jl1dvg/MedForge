<?php

namespace Modules\CRM\Services;

use InvalidArgumentException;
use Modules\CRM\Models\ProjectModel;
use PDO;

class CrmProjectService
{
    private ProjectModel $projects;

    public function __construct(PDO $pdo)
    {
        $this->projects = new ProjectModel($pdo);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        return $this->projects->list($filters);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        return $this->projects->find($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, int $userId): array
    {
        return $this->projects->create($payload, $userId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function linkFromSource(array $payload, int $userId): array
    {
        $sourceModule = trim((string) ($payload['source_module'] ?? ''));
        $sourceRefId = trim((string) ($payload['source_ref_id'] ?? ''));

        if ($sourceModule === '' || $sourceRefId === '') {
            throw new InvalidArgumentException('source_module y source_ref_id son requeridos');
        }

        $existing = $this->projects->findBySource($sourceModule, $sourceRefId);
        if ($existing) {
            $updated = $this->projects->updateLinks($existing['id'], $payload);
            return $updated ?? $existing;
        }

        if (empty($payload['title'])) {
            $payload['title'] = strtoupper($sourceModule) . ' #' . $sourceRefId;
        }

        return $this->projects->create($payload, $userId);
    }
}
