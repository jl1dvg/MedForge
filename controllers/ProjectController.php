<?php

namespace Controllers;

use Core\BaseController;
use Modules\CRM\Models\ProjectModel;
use Modules\CRM\Models\TaskModel;
use PDO;
use Throwable;

class ProjectController extends BaseController
{
    private ProjectModel $projects;
    private TaskModel $tasks;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->projects = new ProjectModel($pdo);
        $this->tasks = new TaskModel($pdo);
    }

    public function listProjects(): void
    {
        $this->requireAuth();

        try {
            $filters = [];
            $hcNumber = $this->getQuery('hc');
            $leadId = $this->getQueryInt('lead_id');
            $formId = $this->getQueryInt('form_id');

            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($owner = $this->getQueryInt('owner_id')) !== null) {
                $filters['owner_id'] = $owner;
            }
            if (($customer = $this->getQueryInt('customer_id')) !== null) {
                $filters['customer_id'] = $customer;
            }
            if ($formId !== null) {
                $filters['form_id'] = $formId;
            }
            if (($sourceModule = $this->getQuery('source_module')) !== null) {
                $filters['source_module'] = $sourceModule;
            }
            if (($sourceRef = $this->getQuery('source_ref_id')) !== null) {
                $filters['source_ref_id'] = $sourceRef;
            }
            if (($episodeType = $this->getQuery('episode_type')) !== null) {
                $filters['episode_type'] = $episodeType;
            }
            if (($eye = $this->getQuery('eye')) !== null) {
                $filters['eye'] = $eye;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            if ($leadId !== null && $hcNumber !== null && $formId === null) {
                $byLead = $this->projects->list($filters + ['lead_id' => $leadId]);
                $byHc = $this->projects->list($filters + ['hc_number' => $hcNumber]);
                $projects = $this->uniqueProjects(array_merge($byLead, $byHc));
            } else {
                if ($leadId !== null) {
                    $filters['lead_id'] = $leadId;
                }
                if ($hcNumber !== null) {
                    $filters['hc_number'] = $hcNumber;
                }
                $projects = $this->projects->list($filters);
            }

            $this->json(['ok' => true, 'data' => $projects]);
        } catch (Throwable $exception) {
            error_log('[ProjectController] listProjects failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los proyectos'], 500);
        }
    }

    public function showProject(int $id): void
    {
        $this->requireAuth();

        $project = $this->projects->find($id);
        if (!$project) {
            $this->json(['ok' => false, 'error' => 'Proyecto no encontrado'], 404);
            return;
        }

        $this->json(['ok' => true, 'data' => $project]);
    }

    public function createProject(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $title = trim((string) ($payload['title'] ?? ''));
        $hcNumber = $this->nullableString($payload['hc_number'] ?? null);
        $formId = !empty($payload['form_id']) ? (int) $payload['form_id'] : null;
        $leadId = !empty($payload['lead_id']) ? (int) $payload['lead_id'] : null;
        $payload['episode_type'] = $this->normalizeEpisodeType($payload['episode_type'] ?? null);
        $payload['eye'] = $this->normalizeEye($payload['eye'] ?? null);

        if ($formId !== null) {
            $existing = $this->projects->findByFormId($formId);
            if ($existing) {
                $linked = $this->projects->updateLinks($existing['id'], $payload);
                $this->json(['ok' => true, 'data' => $linked, 'linked' => true]);
                return;
            }
        }

        if ($leadId !== null) {
            $existing = $this->projects->findOpenByLeadId($leadId);
            if ($existing) {
                $linked = $this->projects->updateLinks($existing['id'], $payload);
                $this->json(['ok' => true, 'data' => $linked, 'linked' => true]);
                return;
            }
        }

        $episodeType = $payload['episode_type'];
        $eye = $payload['eye'];
        if ($formId === null && $hcNumber !== null && $episodeType && $eye) {
            $existing = $this->projects->findRecentOpenByHcEpisodeEye($hcNumber, $episodeType, $eye);
            if ($existing) {
                $linked = $this->projects->updateLinks($existing['id'], $payload);
                $this->json(['ok' => true, 'data' => $linked, 'linked' => true]);
                return;
            }
        }

        if ($title === '') {
            $title = $this->buildFallbackTitle($hcNumber, $formId, $payload['source_module'] ?? null);
        }

        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'El título es requerido'], 422);
            return;
        }

        try {
            $project = $this->projects->create([
                'title' => $title,
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? null,
                'owner_id' => $payload['owner_id'] ?? null,
                'lead_id' => $leadId,
                'customer_id' => $payload['customer_id'] ?? null,
                'hc_number' => $hcNumber,
                'form_id' => $formId,
                'source_module' => $payload['source_module'] ?? null,
                'source_ref_id' => $payload['source_ref_id'] ?? null,
                'episode_type' => $episodeType,
                'eye' => $eye,
                'start_date' => $payload['start_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
            ],
                $this->currentUserId()
            );

            $this->json(['ok' => true, 'data' => $project], 201);
        } catch (Throwable $exception) {
            error_log('[ProjectController] createProject failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->json(['ok' => false, 'error' => 'No se pudo crear el proyecto'], 500);
        }
    }

    public function linkProject(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $projectId = isset($payload['project_id']) ? (int) $payload['project_id'] : 0;

        if ($projectId <= 0) {
            $this->json(['ok' => false, 'error' => 'project_id es requerido'], 422);
            return;
        }

        if (array_key_exists('episode_type', $payload)) {
            $payload['episode_type'] = $this->normalizeEpisodeType($payload['episode_type']);
        }
        if (array_key_exists('eye', $payload)) {
            $payload['eye'] = $this->normalizeEye($payload['eye']);
        }

        $project = $this->projects->updateLinks($projectId, $payload);
        if (!$project) {
            $this->json(['ok' => false, 'error' => 'Proyecto no encontrado'], 404);
            return;
        }

        $this->json(['ok' => true, 'data' => $project]);
    }

    public function updateStatus(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $projectId = isset($payload['project_id']) ? (int) $payload['project_id'] : 0;
        $status = isset($payload['status']) ? (string) $payload['status'] : '';

        if ($projectId <= 0 || $status === '') {
            $this->json(['ok' => false, 'error' => 'project_id y status son requeridos'], 422);
            return;
        }

        $project = $this->projects->updateStatus($projectId, $status);
        if (!$project) {
            $this->json(['ok' => false, 'error' => 'Proyecto no encontrado'], 404);
            return;
        }

        $this->json(['ok' => true, 'data' => $project]);
    }

    public function listTasks(int $projectId): void
    {
        $this->requireAuth();

        $tasks = $this->tasks->list([
            'project_id' => $projectId,
            'company_id' => $this->currentCompanyId(),
            'viewer_id' => $this->currentUserId(),
            'is_admin' => $this->isAdminUser(),
        ]);
        $this->json(['ok' => true, 'data' => $tasks]);
    }

    public function createTask(int $projectId): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'El título es requerido'], 422);
            return;
        }

        try {
            $task = $this->tasks->create([
                'company_id' => $this->currentCompanyId(),
                'project_id' => $projectId,
                'title' => $title,
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? null,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'due_at' => $payload['due_at'] ?? null,
                'remind_at' => $payload['remind_at'] ?? null,
                'remind_channel' => $payload['remind_channel'] ?? null,
            ],
                $this->currentUserId()
            );

            $this->json(['ok' => true, 'data' => $task], 201);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear la tarea'], 500);
        }
    }

    public function listNotes(int $projectId): void
    {
        $this->requireAuth();

        $this->json(['ok' => true, 'data' => [], 'project_id' => $projectId]);
    }

    public function listMilestones(int $projectId): void
    {
        $this->requireAuth();

        $this->json(['ok' => true, 'data' => [], 'project_id' => $projectId]);
    }

    public function listFiles(int $projectId): void
    {
        $this->requireAuth();

        $this->json(['ok' => true, 'data' => [], 'project_id' => $projectId]);
    }

    private function uniqueProjects(array $projects): array
    {
        $seen = [];
        $unique = [];

        foreach ($projects as $project) {
            $id = (string) ($project['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $unique[] = $project;
        }

        return $unique;
    }

    private function buildFallbackTitle(?string $hcNumber, ?int $formId, ?string $sourceModule): string
    {
        $parts = [];
        if ($sourceModule) {
            $parts[] = ucfirst($sourceModule);
        } else {
            $parts[] = 'Caso';
        }
        if ($formId) {
            $parts[] = 'Form ' . $formId;
        }
        if ($hcNumber) {
            $parts[] = 'HC ' . $hcNumber;
        }

        return trim(implode(' - ', $parts));
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function getBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $data = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            $this->bodyCache = is_array($decoded) ? $decoded : [];
            return $this->bodyCache;
        }

        if (!empty($data)) {
            $this->bodyCache = $data;
            return $this->bodyCache;
        }

        $decoded = json_decode(file_get_contents('php://input'), true);
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
    }

    private function getQuery(string $key): ?string
    {
        if (!isset($_GET[$key])) {
            return null;
        }

        $value = trim((string) $_GET[$key]);

        return $value === '' ? null : $value;
    }

    private function getQueryInt(string $key): ?int
    {
        if (!isset($_GET[$key])) {
            return null;
        }

        if ($_GET[$key] === '' || $_GET[$key] === null) {
            return null;
        }

        $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);
        return $value === false ? null : (int) $value;
    }

    private function normalizeEpisodeType($value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $value = strtolower($value);
        $allowed = ['cirugia', 'examen', 'control'];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function normalizeEye($value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);
        $allowed = ['OD', 'OI', 'AO'];
        return in_array($value, $allowed, true) ? $value : null;
    }
}
