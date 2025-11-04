<?php

namespace Modules\CRM\Controllers;

use Core\BaseController;
use Modules\CRM\Models\LeadModel;
use Modules\CRM\Models\ProjectModel;
use Modules\CRM\Models\TaskModel;
use Modules\CRM\Models\TicketModel;
use PDO;
use Throwable;

class CRMController extends BaseController
{
    private LeadModel $leads;
    private ProjectModel $projects;
    private TaskModel $tasks;
    private TicketModel $tickets;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->leads = new LeadModel($pdo);
        $this->projects = new ProjectModel($pdo);
        $this->tasks = new TaskModel($pdo);
        $this->tickets = new TicketModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/index.php',
            [
                'pageTitle' => 'CRM',
                'leadStatuses' => $this->leads->getStatuses(),
                'leadSources' => $this->leads->getSources(),
                'projectStatuses' => $this->projects->getStatuses(),
                'taskStatuses' => $this->tasks->getStatuses(),
                'ticketStatuses' => $this->tickets->getStatuses(),
                'ticketPriorities' => $this->tickets->getPriorities(),
                'assignableUsers' => $this->getAssignableUsers(),
                'initialLeads' => $this->leads->list(['limit' => 50]),
                'initialProjects' => $this->projects->list(['limit' => 50]),
                'initialTasks' => $this->tasks->list(['limit' => 50]),
                'initialTickets' => $this->tickets->list(['limit' => 50]),
                'scripts' => ['js/pages/crm.js'],
            ]
        );
    }

    public function listLeads(): void
    {
        $this->requireAuth();

        try {
            $filters = [];
            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($assigned = $this->getQueryInt('assigned_to')) !== null) {
                $filters['assigned_to'] = $assigned;
            }
            if (($search = $this->getQuery('q')) !== null) {
                $filters['search'] = $search;
            }
            if (($source = $this->getQuery('source')) !== null) {
                $filters['source'] = $source;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $leads = $this->leads->list($filters);
            $this->json(['ok' => true, 'data' => $leads]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los leads'], 500);
        }
    }

    public function createLead(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $this->json(['ok' => false, 'error' => 'El nombre es requerido'], 422);
            return;
        }

        try {
            $lead = $this->leads->create(
                [
                    'name' => $name,
                    'email' => $payload['email'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'source' => $payload['source'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'assigned_to' => $payload['assigned_to'] ?? null,
                    'customer_id' => $payload['customer_id'] ?? null,
                ],
                $this->getCurrentUserId()
            );

            $this->json(['ok' => true, 'data' => $lead], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el lead'], 500);
        }
    }

    public function updateLead(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $leadId = isset($payload['lead_id']) ? (int) $payload['lead_id'] : 0;

        if ($leadId <= 0) {
            $this->json(['ok' => false, 'error' => 'lead_id es requerido'], 422);
            return;
        }

        try {
            $updated = $this->leads->update($leadId, $payload);
            if (!$updated) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
                return;
            }

            $this->json(['ok' => true, 'data' => $updated]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el lead'], 500);
        }
    }

    public function convertLead(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $leadId = isset($payload['lead_id']) ? (int) $payload['lead_id'] : 0;

        if ($leadId <= 0) {
            $this->json(['ok' => false, 'error' => 'lead_id es requerido'], 422);
            return;
        }

        try {
            $converted = $this->leads->convertToCustomer($leadId, $payload['customer'] ?? []);
            if (!$converted) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
                return;
            }

            $this->json(['ok' => true, 'data' => $converted]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo convertir el lead'], 500);
        }
    }

    public function listProjects(): void
    {
        $this->requireAuth();

        try {
            $filters = [];
            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($owner = $this->getQueryInt('owner_id')) !== null) {
                $filters['owner_id'] = $owner;
            }
            if (($lead = $this->getQueryInt('lead_id')) !== null) {
                $filters['lead_id'] = $lead;
            }
            if (($customer = $this->getQueryInt('customer_id')) !== null) {
                $filters['customer_id'] = $customer;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $projects = $this->projects->list($filters);
            $this->json(['ok' => true, 'data' => $projects]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los proyectos'], 500);
        }
    }

    public function createProject(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'El título es requerido'], 422);
            return;
        }

        try {
            $project = $this->projects->create(
                [
                    'title' => $title,
                    'description' => $payload['description'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'owner_id' => $payload['owner_id'] ?? null,
                    'lead_id' => $payload['lead_id'] ?? null,
                    'customer_id' => $payload['customer_id'] ?? null,
                    'start_date' => $payload['start_date'] ?? null,
                    'due_date' => $payload['due_date'] ?? null,
                ],
                $this->getCurrentUserId()
            );

            $this->json(['ok' => true, 'data' => $project], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el proyecto'], 500);
        }
    }

    public function updateProjectStatus(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $projectId = isset($payload['project_id']) ? (int) $payload['project_id'] : 0;
        $status = isset($payload['status']) ? (string) $payload['status'] : '';

        if ($projectId <= 0 || $status === '') {
            $this->json(['ok' => false, 'error' => 'project_id y status son requeridos'], 422);
            return;
        }

        try {
            $project = $this->projects->updateStatus($projectId, $status);
            if (!$project) {
                $this->json(['ok' => false, 'error' => 'Proyecto no encontrado'], 404);
                return;
            }

            $this->json(['ok' => true, 'data' => $project]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el proyecto'], 500);
        }
    }

    public function listTasks(): void
    {
        $this->requireAuth();

        try {
            $filters = [];
            if (($project = $this->getQueryInt('project_id')) !== null) {
                $filters['project_id'] = $project;
            }
            if (($assigned = $this->getQueryInt('assigned_to')) !== null) {
                $filters['assigned_to'] = $assigned;
            }
            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $tasks = $this->tasks->list($filters);
            $this->json(['ok' => true, 'data' => $tasks]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar las tareas'], 500);
        }
    }

    public function createTask(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $title = trim((string) ($payload['title'] ?? ''));
        $projectId = isset($payload['project_id']) ? (int) $payload['project_id'] : 0;

        if ($title === '') {
            $this->json(['ok' => false, 'error' => 'El título de la tarea es requerido'], 422);
            return;
        }

        if ($projectId <= 0) {
            $this->json(['ok' => false, 'error' => 'project_id es requerido'], 422);
            return;
        }

        try {
            $task = $this->tasks->create(
                [
                    'project_id' => $projectId,
                    'title' => $title,
                    'description' => $payload['description'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'assigned_to' => $payload['assigned_to'] ?? null,
                    'due_date' => $payload['due_date'] ?? null,
                    'remind_at' => $payload['remind_at'] ?? null,
                    'remind_channel' => $payload['remind_channel'] ?? null,
                ],
                $this->getCurrentUserId()
            );

            $this->json(['ok' => true, 'data' => $task], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear la tarea'], 500);
        }
    }

    public function updateTaskStatus(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;
        $status = isset($payload['status']) ? (string) $payload['status'] : '';

        if ($taskId <= 0 || $status === '') {
            $this->json(['ok' => false, 'error' => 'task_id y status son requeridos'], 422);
            return;
        }

        try {
            $task = $this->tasks->updateStatus($taskId, $status, $this->getCurrentUserId());
            if (!$task) {
                $this->json(['ok' => false, 'error' => 'Tarea no encontrada'], 404);
                return;
            }

            $this->json(['ok' => true, 'data' => $task]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar la tarea'], 500);
        }
    }

    public function listTickets(): void
    {
        $this->requireAuth();

        try {
            $filters = [];
            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($assigned = $this->getQueryInt('assigned_to')) !== null) {
                $filters['assigned_to'] = $assigned;
            }
            if (($priority = $this->getQuery('priority')) !== null) {
                $filters['priority'] = $priority;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $tickets = $this->tickets->list($filters);
            $this->json(['ok' => true, 'data' => $tickets]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los tickets'], 500);
        }
    }

    public function createTicket(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $subject = trim((string) ($payload['subject'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if ($subject === '' || $message === '') {
            $this->json(['ok' => false, 'error' => 'subject y message son requeridos'], 422);
            return;
        }

        try {
            $ticket = $this->tickets->create(
                [
                    'subject' => $subject,
                    'status' => $payload['status'] ?? null,
                    'priority' => $payload['priority'] ?? null,
                    'reporter_id' => $payload['reporter_id'] ?? $this->getCurrentUserId(),
                    'assigned_to' => $payload['assigned_to'] ?? null,
                    'related_lead_id' => $payload['related_lead_id'] ?? null,
                    'related_project_id' => $payload['related_project_id'] ?? null,
                    'message' => $message,
                ],
                $this->getCurrentUserId()
            );

            $this->json(['ok' => true, 'data' => $ticket], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el ticket'], 500);
        }
    }

    public function replyTicket(): void
    {
        $this->requireAuth();

        $payload = $this->getBody();
        $ticketId = isset($payload['ticket_id']) ? (int) $payload['ticket_id'] : 0;
        $message = trim((string) ($payload['message'] ?? ''));

        if ($ticketId <= 0 || $message === '') {
            $this->json(['ok' => false, 'error' => 'ticket_id y message son requeridos'], 422);
            return;
        }

        try {
            $ticket = $this->tickets->find($ticketId);
            if (!$ticket) {
                $this->json(['ok' => false, 'error' => 'Ticket no encontrado'], 404);
                return;
            }

            $this->tickets->addMessage($ticketId, $this->getCurrentUserId(), $message);

            if (!empty($payload['status'])) {
                $this->tickets->updateStatus($ticketId, (string) $payload['status']);
            }

            $updated = $this->tickets->find($ticketId);
            $this->json(['ok' => true, 'data' => $updated]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo registrar la respuesta'], 500);
        }
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

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    private function getAssignableUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre FROM users ORDER BY nombre');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

        return (int) $_GET[$key];
    }
}
