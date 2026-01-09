<?php
/** @var array $leadStatuses */
/** @var array $leadSources */
/** @var array $projectStatuses */
/** @var array $taskStatuses */
/** @var array $ticketStatuses */
/** @var array $ticketPriorities */
/** @var array $assignableUsers */
/** @var array $initialLeads */
/** @var array $initialProjects */
/** @var array $initialTasks */
/** @var array $initialTickets */
$styles = $styles ?? [];
$scripts = $scripts ?? [];
$permissions = array_merge([
    'manageLeads' => false,
    'manageProjects' => false,
    'manageTasks' => false,
    'manageTickets' => false,
], $permissions ?? []);

$bootstrap = [
    'leadStatuses' => $leadStatuses ?? [],
    'leadSources' => $leadSources ?? [],
    'projectStatuses' => $projectStatuses ?? [],
    'taskStatuses' => $taskStatuses ?? [],
    'ticketStatuses' => $ticketStatuses ?? [],
    'ticketPriorities' => $ticketPriorities ?? [],
    'assignableUsers' => $assignableUsers ?? [],
    'initialLeads' => $initialLeads ?? [],
    'initialProjects' => $initialProjects ?? [],
    'initialTasks' => $initialTasks ?? [],
    'initialTickets' => $initialTickets ?? [],
    'initialProposals' => $initialProposals ?? [],
    'proposalStatuses' => $proposalStatuses ?? [],
    'permissions' => $permissions,
];

$bootstrapJson = htmlspecialchars(json_encode($bootstrap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">CRM médico</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">CRM</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <style>
        .crm-project-focus {
            outline: 2px solid rgba(14, 165, 233, 0.8);
            outline-offset: -2px;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
        }
        .crm-project-row {
            cursor: pointer;
        }
        .crm-project-row:hover {
            background-color: rgba(25, 135, 84, 0.08);
        }
        #projectDetailModal .modal-content {
            height: 90vh;
        }
        #projectDetailModal .modal-body {
            overflow-y: auto;
            max-height: calc(90vh - 120px);
        }
    </style>
    <div class="row">
        <div class="col-12">
            <div class="box" id="crm-root" data-bootstrap="<?= $bootstrapJson ?>">
                <?php include __DIR__ . '/partials/toolbar.php'; ?>

                <div class="box-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="crm-tab-leads-link" data-bs-toggle="tab" href="#crm-tab-leads" role="tab" aria-controls="crm-tab-leads" aria-selected="true">Leads</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="crm-tab-projects-link" data-bs-toggle="tab" href="#crm-tab-projects" role="tab" aria-controls="crm-tab-projects" aria-selected="false">Proyectos</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="crm-tab-tasks-link" data-bs-toggle="tab" href="#crm-tab-tasks" role="tab" aria-controls="crm-tab-tasks" aria-selected="false">Tareas</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="crm-tab-tickets-link" data-bs-toggle="tab" href="#crm-tab-tickets" role="tab" aria-controls="crm-tab-tickets" aria-selected="false">Tickets</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="crm-tab-proposals-link" data-bs-toggle="tab" href="#crm-tab-proposals" role="tab" aria-controls="crm-tab-proposals" aria-selected="false">Propuestas</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <?php include __DIR__ . '/partials/tab_leads.php'; ?>
                        <?php include __DIR__ . '/partials/tab_projects.php'; ?>
                        <?php include __DIR__ . '/partials/tab_tasks.php'; ?>
                        <?php include __DIR__ . '/partials/tab_tickets.php'; ?>
                        <?php include __DIR__ . '/partials/tab_proposals.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/partials/modals.php'; ?>
