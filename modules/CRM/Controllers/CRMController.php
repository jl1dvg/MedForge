<?php

namespace Modules\CRM\Controllers;

use Core\BaseController;
use Helpers\SecurityAuditLogger;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\CRM\Models\LeadModel;
use Modules\CRM\Models\ProjectModel;
use Modules\CRM\Models\ProposalModel;
use Modules\CRM\Models\TaskModel;
use Modules\CRM\Models\TicketModel;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\CRM\Services\PerfexEstimatesParser;
use Modules\Mail\Services\NotificationMailer;
use PDO;
use RuntimeException;
use Throwable;

class CRMController extends BaseController
{
    private LeadModel $leads;
    private ProjectModel $projects;
    private TaskModel $tasks;
    private TicketModel $tickets;
    private ProposalModel $proposals;
    private LeadConfigurationService $leadConfig;
    private NotificationMailer $mailer;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->leads = new LeadModel($pdo);
        $this->projects = new ProjectModel($pdo);
        $this->tasks = new TaskModel($pdo);
        $this->tickets = new TicketModel($pdo);
        $this->proposals = new ProposalModel($pdo);
        $this->leadConfig = new LeadConfigurationService($pdo);
        $this->mailer = new NotificationMailer($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        $this->auditCrm('crm_dashboard_access');

        $permissions = [
            'manageLeads' => $this->canCrm(['crm.leads.manage']),
            'manageProjects' => $this->canCrm(['crm.projects.manage']),
            'manageTasks' => $this->canCrm(['crm.tasks.manage']),
            'manageTickets' => $this->canCrm(['crm.tickets.manage']),
        ];

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
                'initialTasks' => $this->tasks->list([
                    'limit' => 50,
                    'company_id' => $this->currentCompanyId(),
                    'viewer_id' => $this->currentUserId(),
                    'is_admin' => $this->isAdminUser(),
                ]),
                'initialTickets' => $this->tickets->list(['limit' => 50]),
                'initialProposals' => $this->proposals->list(['limit' => 25]),
                'proposalStatuses' => $this->proposals->getStatuses(),
                'permissions' => $permissions,
                'styles' => ['css/pages/crm/leads.css'],
                'scripts' => ['js/pages/crm.js', 'js/pages/crm/leads.js'],
            ]
        );
    }

    public function listLeads(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

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
            $this->auditCrm('crm_leads_list', ['filters' => $filters]);
            $this->json(['ok' => true, 'data' => $leads]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los leads'], 500);
        }
    }

    public function createLead(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        $payload = $this->getBody();
        $name = trim((string) ($payload['name'] ?? ''));
        $hcNumber = isset($payload['hc_number']) ? trim((string) $payload['hc_number']) : '';

        if ($name === '' || $hcNumber === '') {
            $this->json(['ok' => false, 'error' => 'Los campos name y hc_number son requeridos'], 422);
            return;
        }

        try {
            $lead = $this->leads->create(
                [
                    'hc_number' => $hcNumber,
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

            $this->auditCrm('crm_lead_created', ['hc_number' => $hcNumber]);
            $this->json(['ok' => true, 'data' => $lead], 201);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el lead'], 500);
        }
    }

    public function updateLead(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        $payload = $this->getBody();
        $hcNumber = isset($payload['hc_number']) ? trim((string) $payload['hc_number']) : '';

        if ($hcNumber === '') {
            $this->json(['ok' => false, 'error' => 'hc_number es requerido'], 422);
            return;
        }

        try {
            $updated = $this->leads->update($hcNumber, $payload);
            if (!$updated) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
                return;
            }

            $this->auditCrm('crm_lead_updated', ['hc_number' => $hcNumber]);
            $this->json(['ok' => true, 'data' => $updated]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el lead'], 500);
        }
    }

    public function updateLeadRecordById(int $leadId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        $existing = $this->leads->findById($leadId);
        if (!$existing) {
            $this->json(['ok' => false, 'error' => 'Lead no encontrado', 'error_code' => 'lead_not_found'], 404);
            return;
        }

        try {
            $payload = $this->getBody();
            $updated = $this->leads->updateById($leadId, $payload);
            if (!$updated) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado', 'error_code' => 'lead_not_found'], 404);
                return;
            }

            $this->auditCrm('crm_lead_updated', ['id' => $leadId]);
            $this->json(['ok' => true, 'data' => $updated]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage(), 'error_code' => 'validation_failed'], 422);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_update_failed', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el lead', 'error_code' => 'server_error'], 500);
        }
    }

    public function showLead(int $leadId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        try {
            $lead = $this->leads->findById($leadId);
            if (!$lead) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado', 'error_code' => 'lead_not_found'], 404);
                return;
            }

            $this->json(['ok' => true, 'data' => $lead]);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_load_failed', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);
            $this->json(['ok' => false, 'error' => 'No se pudo cargar el lead', 'error_code' => 'server_error'], 500);
        }
    }

    public function leadProfile(int $leadId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        if ($leadId <= 0) {
            $this->json(['ok' => false, 'success' => false, 'error' => 'leadId inválido'], 422);
            return;
        }

        try {
            $profile = $this->leads->fetchProfileById($leadId);
            if (!$profile) {
                $this->json(['ok' => false, 'success' => false, 'error' => 'Lead no encontrado'], 404);
                return;
            }

            $patient = $profile['patient'] ?? null;
            $computed = $this->buildLeadComputedProfile(is_array($patient) ? $patient : null);

            $this->json([
                'ok' => true,
                'success' => true,
                'data' => [
                    'lead' => $profile['lead'],
                    'patient' => $patient,
                    'computed' => $computed,
                ],
            ]);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_profile_failed', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);
            $this->json(['ok' => false, 'success' => false, 'error' => 'No se pudo cargar el perfil'], 500);
        }
    }

    public function updateLeadStatus(int $leadId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        $payload = $this->getBody();
        $status = isset($payload['status']) ? trim((string) $payload['status']) : '';
        if ($status === '') {
            $this->json(['ok' => false, 'error' => 'El estado es requerido', 'error_code' => 'status_required'], 422);
            return;
        }

        try {
            $updated = $this->leads->updateStatusById($leadId, $status);
            if (!$updated) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado', 'error_code' => 'lead_not_found'], 404);
                return;
            }

            $this->auditCrm('crm_lead_status_updated', [
                'lead_id' => $leadId,
                'status' => $status,
            ]);

            $this->json(['ok' => true, 'data' => $updated]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage(), 'error_code' => 'validation_failed'], 422);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_status_failed', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el estado', 'error_code' => 'server_error'], 500);
        }
    }

    public function leadMeta(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        try {
            $this->json([
                'ok' => true,
                'data' => [
                    'statuses' => $this->leads->getStatuses(),
                    'sources' => $this->leads->getSources(),
                    'assignable' => $this->getAssignableUsers(),
                    'lost_stage' => $this->leadConfig->getLostStage(),
                    'won_stage' => $this->leadConfig->getWonStage(),
                ],
            ]);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_meta_failed', ['message' => $exception->getMessage()]);
            $this->json(['ok' => false, 'error' => 'No se pudo cargar la configuración de leads', 'error_code' => 'server_error'], 500);
        }
    }

    public function leadMetrics(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        try {
            $metrics = $this->leads->getMetrics();
            $this->auditCrm('crm_leads_metrics');
            $this->json(['ok' => true, 'data' => $metrics]);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_metrics_failed', ['message' => $exception->getMessage()]);
            $this->json(['ok' => false, 'error' => 'No se pudieron obtener las métricas', 'error_code' => 'server_error'], 500);
        }
    }

    public function composeLeadMail(int $leadId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        try {
            $lead = $this->leads->findById($leadId);
            if (!$lead) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado', 'error_code' => 'lead_not_found'], 404);
                return;
            }

            $draft = $this->buildLeadMailDraft($lead, $this->getQuery('status') ?: null);
            if ($draft['to'] === '') {
                $this->json(['ok' => false, 'error' => 'El lead no tiene correo electrónico', 'error_code' => 'email_required'], 422);
                return;
            }

            $this->auditCrm('crm_lead_mail_compose', ['lead_id' => $leadId]);
            $this->json(['ok' => true, 'data' => $draft]);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_mail_compose_failed', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);
            $this->json(['ok' => false, 'error' => 'No se pudo preparar el correo', 'error_code' => 'server_error'], 500);
        }
    }

    public function sendLeadMailTemplate(int $leadId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        try {
            $payload = $this->getBody();
            $status = isset($payload['status']) ? (string) $payload['status'] : ($payload['template_key'] ?? null);
            $to = isset($payload['to']) ? trim((string) $payload['to']) : '';
            $subject = isset($payload['subject']) ? trim((string) $payload['subject']) : '';
            $body = isset($payload['body']) ? trim((string) $payload['body']) : '';

            $lead = $this->leads->findById($leadId);
            if (!$lead) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado', 'error_code' => 'lead_not_found'], 404);
                return;
            }

            $draft = $this->buildLeadMailDraft($lead, $status);
            $to = $to !== '' ? $to : $draft['to'];
            $subject = $subject !== '' ? $subject : $draft['subject'];
            $body = $body !== '' ? $body : $draft['body'];

            if ($to === '') {
                $this->json(['ok' => false, 'error' => 'El lead no tiene correo electrónico', 'error_code' => 'email_required'], 422);
                return;
            }

            $result = $this->mailer->sendPatientUpdate($to, $subject, $body);
            if (!$result['success']) {
                $message = $result['error'] ?? 'No se pudo enviar el correo de notificación';
                throw new RuntimeException($message);
            }

            $this->auditCrm('crm_lead_mail_sent', ['lead_id' => $leadId, 'status' => $status]);

            $this->json(['ok' => true, 'data' => ['sent' => true]]);
        } catch (Throwable $exception) {
            SecurityAuditLogger::log('crm_lead_mail_send_failed', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);

            $errorMessage = 'No se pudo enviar el correo';
            if ($exception->getMessage() !== '') {
                $errorMessage .= ': ' . $exception->getMessage();
            }

            $this->json(['ok' => false, 'error' => $errorMessage, 'error_code' => 'server_error'], 500);
        }
    }

    public function convertLead(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.leads.manage');

        $payload = $this->getBody();
        $hcNumber = isset($payload['hc_number']) ? trim((string) $payload['hc_number']) : '';

        if ($hcNumber === '') {
            $this->json(['ok' => false, 'error' => 'hc_number es requerido'], 422);
            return;
        }

        try {
            $converted = $this->leads->convertToCustomer($hcNumber, $payload['customer'] ?? []);
            if (!$converted) {
                $this->json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
                return;
            }

            $this->auditCrm('crm_lead_converted', ['hc_number' => $hcNumber]);
            $this->json(['ok' => true, 'data' => $converted]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo convertir el lead'], 500);
        }
    }

    public function listProjects(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

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
            $this->auditCrm('crm_projects_list', ['filters' => $filters]);
            $this->json(['ok' => true, 'data' => $projects]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los proyectos'], 500);
        }
    }

    public function createProject(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.projects.manage');

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

            $this->auditCrm('crm_project_created', [
                'project_id' => $project['id'] ?? null,
                'lead_id' => $payload['lead_id'] ?? null,
            ]);
            $this->json(['ok' => true, 'data' => $project], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el proyecto'], 500);
        }
    }

    public function updateProjectStatus(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.projects.manage');

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

            $this->auditCrm('crm_project_status_updated', [
                'project_id' => $projectId,
                'status' => $status,
            ]);
            $this->json(['ok' => true, 'data' => $project]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el proyecto'], 500);
        }
    }

    public function listTasks(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

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

            $filters['company_id'] = $this->currentCompanyId();
            $filters['viewer_id'] = $this->currentUserId();
            $filters['is_admin'] = $this->isAdminUser();

            $tasks = $this->tasks->list($filters);
            $this->auditCrm('crm_tasks_list', ['filters' => $filters]);
            $this->json(['ok' => true, 'data' => $tasks]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar las tareas'], 500);
        }
    }

    public function createTask(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.tasks.manage');

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
                $this->getCurrentUserId()
            );

            $this->auditCrm('crm_task_created', [
                'project_id' => $projectId,
                'task_id' => $task['id'] ?? null,
            ]);
            $this->json(['ok' => true, 'data' => $task], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear la tarea'], 500);
        }
    }

    public function updateTaskStatus(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.tasks.manage');

        $payload = $this->getBody();
        $taskId = isset($payload['task_id']) ? (int) $payload['task_id'] : 0;
        $status = isset($payload['status']) ? (string) $payload['status'] : '';

        if ($taskId <= 0 || $status === '') {
            $this->json(['ok' => false, 'error' => 'task_id y status son requeridos'], 422);
            return;
        }

        try {
            $task = $this->tasks->find($taskId, $this->currentCompanyId());
            if (!$task) {
                $this->json(['ok' => false, 'error' => 'Tarea no encontrada'], 404);
                return;
            }

            if (!$this->isAdminUser()) {
                $userId = $this->getCurrentUserId();
                $assigned = isset($task['assigned_to']) ? (int) $task['assigned_to'] : 0;
                $created = isset($task['created_by']) ? (int) $task['created_by'] : 0;
                if ($assigned !== $userId && $created !== $userId) {
                    $this->json(['ok' => false, 'error' => 'Tarea no encontrada'], 404);
                    return;
                }
            }

            $task = $this->tasks->updateStatus($taskId, $this->currentCompanyId(), $status, $this->getCurrentUserId());
            if (!$task) {
                $this->json(['ok' => false, 'error' => 'Tarea no encontrada'], 404);
                return;
            }

            $this->auditCrm('crm_task_status_updated', [
                'task_id' => $taskId,
                'status' => $status,
            ]);
            $this->json(['ok' => true, 'data' => $task]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar la tarea'], 500);
        }
    }

    public function listTickets(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

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
            $this->auditCrm('crm_tickets_list', ['filters' => $filters]);
            $this->json(['ok' => true, 'data' => $tickets]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar los tickets'], 500);
        }
    }

    public function createTicket(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.tickets.manage');

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

            $this->auditCrm('crm_ticket_created', [
                'ticket_id' => $ticket['id'] ?? null,
                'related_project_id' => $payload['related_project_id'] ?? null,
            ]);
            $this->json(['ok' => true, 'data' => $ticket], 201);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el ticket'], 500);
        }
    }

    public function replyTicket(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.tickets.manage');

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
            $this->auditCrm('crm_ticket_replied', [
                'ticket_id' => $ticketId,
                'status' => $payload['status'] ?? null,
            ]);
            $this->json(['ok' => true, 'data' => $updated]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo registrar la respuesta'], 500);
        }
    }

    public function listProposals(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        try {
            $filters = [];
            if (($status = $this->getQuery('status')) !== null) {
                $filters['status'] = $status;
            }
            if (($leadId = $this->getQueryInt('lead_id')) !== null) {
                $filters['lead_id'] = $leadId;
            }
            if (($search = $this->getQuery('q')) !== null) {
                $filters['search'] = $search;
            }
            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $proposals = $this->proposals->list($filters);
            $this->auditCrm('crm_proposals_list', ['filters' => $filters]);
            $this->json(['ok' => true, 'data' => $proposals]);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudieron cargar las propuestas'], 500);
        }
    }

    public function showProposal(int $proposalId): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.view');

        try {
            $proposal = $this->proposals->find($proposalId);
            if (!$proposal) {
                $this->json(['ok' => false, 'error' => 'Propuesta no encontrada'], 404);
                return;
            }

            $this->auditCrm('crm_proposal_viewed', ['proposal_id' => $proposalId]);
            $this->json(['ok' => true, 'data' => $proposal]);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo cargar la propuesta'], 500);
        }
    }

    public function getProposal(int $proposalId): void
    {
        $this->showProposal($proposalId);
    }

    public function parsePerfexEstimates(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.projects.manage');

        $payload = $this->getBody();
        $html = (string) ($payload['html'] ?? '');

        if (trim($html) === '') {
            $this->json(['ok' => false, 'error' => 'El cuerpo HTML es requerido'], 422);
            return;
        }

        try {
            $parser = new PerfexEstimatesParser();
            $parsed = $parser->parse($html);
            $this->auditCrm('crm_proposals_perfex_preview');
            $this->json(['ok' => true, 'data' => $parsed]);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo interpretar el listado de Perfex'], 500);
        }
    }

    public function createProposal(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.projects.manage');

        $payload = $this->getBody();

        try {
            $proposal = $this->proposals->create($payload, $this->getCurrentUserId());
            $this->auditCrm('crm_proposal_created', [
                'proposal_id' => $proposal['id'] ?? null,
                'lead_id' => $payload['lead_id'] ?? null,
            ]);
            $this->json(['ok' => true, 'data' => $proposal], 201);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear la propuesta'], 500);
        }
    }

    public function updateProposalStatus(): void
    {
        $this->requireAuth();
        $this->requireCrmPermission('crm.projects.manage');

        $payload = $this->getBody();
        $proposalId = isset($payload['proposal_id']) ? (int) $payload['proposal_id'] : 0;
        $status = (string) ($payload['status'] ?? '');

        if ($proposalId <= 0 || $status === '') {
            $this->json(['ok' => false, 'error' => 'Datos incompletos'], 422);
            return;
        }

        try {
            $proposal = $this->proposals->updateStatus($proposalId, $status, $this->getCurrentUserId());
            if (!$proposal) {
                $this->json(['ok' => false, 'error' => 'Propuesta no encontrada'], 404);
                return;
            }

            $this->auditCrm('crm_proposal_status_updated', [
                'proposal_id' => $proposalId,
                'status' => $status,
            ]);
            $this->json(['ok' => true, 'data' => $proposal]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el estado'], 500);
        }
    }

    private function auditCrm(string $action, array $context = []): void
    {
        SecurityAuditLogger::log($action, ['module' => 'crm'] + $context);
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

    /**
     * @param array<string, mixed> $lead
     */
    private function buildLeadMailDraft(array $lead, ?string $statusOverride = null): array
    {
        $name = trim((string) ($lead['name'] ?? ''));
        $greeting = $name !== '' ? 'Hola ' . $name : 'Hola';

        $status = $statusOverride !== null && $statusOverride !== ''
            ? $this->leadConfig->normalizeStage($statusOverride, false)
            : ($lead['status'] ?? null);

        $subjectParts = ['Actualización de tu caso'];
        if (is_string($status) && $status !== '') {
            $subjectParts[] = $status;
        }

        $body = [
            $greeting . ' 👋',
            'Te escribimos para actualizar el estado de tu solicitud en MedForge.',
        ];

        if (is_string($status) && $status !== '') {
            $body[] = 'Estado: ' . $status;
        }

        if (!empty($lead['hc_number'])) {
            $body[] = 'Referencia: ' . $lead['hc_number'];
        }

        if (!empty($lead['assigned_name'])) {
            $body[] = 'Asesor asignado: ' . $lead['assigned_name'];
        }

        $body[] = 'Si tienes dudas, responde a este correo y con gusto te ayudamos.';

        return [
            'to' => trim((string) ($lead['email'] ?? '')),
            'subject' => implode(' | ', array_filter($subjectParts)),
            'body' => implode("\n\n", array_filter($body)),
            'context' => [
                'lead_id' => $lead['id'] ?? null,
                'status' => $status,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $patient
     *
     * @return array<string, mixed>
     */
    private function buildLeadComputedProfile(?array $patient): array
    {
        if (!$patient) {
            return [];
        }

        $birthdate = '';
        if (!empty($patient['fecha_nacimiento'])) {
            $birthdate = (string) $patient['fecha_nacimiento'];
        } elseif (!empty($patient['birthdate'])) {
            $birthdate = (string) $patient['birthdate'];
        }

        $age = null;
        if ($birthdate !== '') {
            try {
                $date = new DateTimeImmutable($birthdate);
                $now = new DateTimeImmutable('today');
                $age = $date->diff($now)->y;
            } catch (Throwable $exception) {
                $age = null;
            }
        }

        $address = trim((string) ($patient['address'] ?? $patient['direccion'] ?? $patient['domicilio'] ?? ''));
        $city = trim((string) ($patient['city'] ?? $patient['ciudad'] ?? ''));
        $state = trim((string) ($patient['state'] ?? $patient['provincia'] ?? $patient['region'] ?? ''));
        $zip = trim((string) ($patient['zip'] ?? $patient['codigo_postal'] ?? $patient['postal_code'] ?? ''));
        $country = trim((string) ($patient['country'] ?? $patient['pais'] ?? ''));

        $displayParts = [];
        if ($address !== '') {
            $displayParts[] = $address;
        }

        $cityStateZip = trim(implode(' ', array_filter([$city, $state, $zip])));
        if ($cityStateZip !== '') {
            $displayParts[] = $cityStateZip;
        }

        if ($country !== '') {
            $displayParts[] = $country;
        }

        $displayAddress = $displayParts ? implode(', ', $displayParts) : null;

        return [
            'edad' => $age,
            'display_address' => $displayAddress,
        ];
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    private function getAssignableUsers(): array
    {
        return $this->leadConfig->getAssignableUsers();
    }

    private function requireCrmPermission(string $permission): void
    {
        $this->requirePermission([$permission, 'crm.manage']);
    }

    private function canCrm(array $permissions): bool
    {
        $checks = array_merge($permissions, ['crm.manage']);

        return $this->hasPermission($checks);
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
