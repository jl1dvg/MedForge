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
                <div class="box-header with-border d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="box-title mb-0">Gestión de leads, proyectos, tareas y tickets</h4>
                        <p class="text-muted mb-0">Controla el ciclo completo desde la captación del paciente hasta el seguimiento interno.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-light text-primary fw-600" id="crm-leads-count">Leads: 0</span>
                        <span class="badge bg-success-light text-success fw-600" id="crm-projects-count">Proyectos: 0</span>
                        <span class="badge bg-warning-light text-warning fw-600" id="crm-tasks-count">Tareas: 0</span>
                        <span class="badge bg-info-light text-info fw-600" id="crm-tickets-count">Tickets: 0</span>
                    </div>
                </div>

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
                        <div class="tab-pane fade show active" id="crm-tab-leads" role="tabpanel" aria-labelledby="crm-tab-leads-link">
                            <?php include __DIR__ . '/leads/index.php'; ?>
                        </div>
                        <div class="tab-pane fade" id="crm-tab-projects" role="tabpanel" aria-labelledby="crm-tab-projects-link">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Listado de proyectos</h5>
                                    <p class="text-muted small mb-0">Selecciona un proyecto para ver el detalle completo.</p>
                                </div>
                                <?php if ($permissions['manageProjects']): ?>
                                    <button type="button" class="btn btn-success" id="project-create-btn">
                                        <i class="mdi mdi-plus"></i> Nuevo proyecto
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if (!$permissions['manageProjects']): ?>
                                <div class="alert alert-info mb-3">
                                    No cuentas con permisos para crear proyectos dentro del CRM.
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive rounded card-table shadow-sm">
                                <table class="table table-striped table-sm align-middle" id="crm-projects-table">
                                    <thead class="bg-success text-white">
                                        <tr>
                                            <th>Proyecto</th>
                                            <th>Estado</th>
                                            <th>Lead</th>
                                            <th>Responsable</th>
                                            <th>Inicio</th>
                                            <th>Entrega</th>
                                            <th class="text-end">Actualización</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="crm-tab-tasks" role="tabpanel" aria-labelledby="crm-tab-tasks-link">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1">Listado de tareas</h5>
                                            <p class="text-muted small mb-0">Gestiona las tareas del CRM y sus recordatorios.</p>
                                        </div>
                                        <?php if ($permissions['manageTasks']): ?>
                                            <button type="button" class="btn btn-warning text-white" data-bs-toggle="modal" data-bs-target="#taskModal">
                                                <i class="mdi mdi-plus"></i> Nueva tarea
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$permissions['manageTasks']): ?>
                                        <div class="alert alert-info mb-3">
                                            No puedes crear tareas en el CRM con el rol asignado.
                                        </div>
                                    <?php endif; ?>
                                    <div id="crm-tasks-summary" class="row g-2 mb-3"></div>
                                    <div class="table-responsive rounded card-table shadow-sm">
                                        <table class="table table-striped table-sm align-middle" id="crm-tasks-table">
                                            <thead class="bg-warning text-white">
                                                <tr>
                                                    <th>Tarea</th>
                                                    <th>Proyecto</th>
                                                    <th>Asignado</th>
                                                    <th>Estado</th>
                                                    <th>Entrega</th>
                                                    <th>Recordatorios</th>
                                                    <th class="text-end">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <?php if ($permissions['manageTasks']): ?>
                                <div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="taskModalLabel">Nueva tarea</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="task-form" class="space-y-2">
                                                <div class="mb-2">
                                                    <label for="task-project" class="form-label">Proyecto</label>
                                                    <select class="form-select" id="task-project" name="project_id" data-placeholder="Selecciona un proyecto">
                                                        <option value="">Sin proyecto</option>
                                                    </select>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="task-lead" class="form-label">Lead (ID)</label>
                                                        <input type="number" class="form-control" id="task-lead" name="lead_id" placeholder="Opcional">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="task-hc" class="form-label">HC</label>
                                                        <input type="text" class="form-control" id="task-hc" name="hc_number" placeholder="Historia clínica">
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="task-title" class="form-label">Título de la tarea *</label>
                                                    <input type="text" class="form-control" id="task-title" name="title" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="task-description" class="form-label">Descripción</label>
                                                    <textarea class="form-control" id="task-description" name="description" rows="3"></textarea>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="task-status" class="form-label">Estado</label>
                                                        <select class="form-select" id="task-status" name="status">
                                                            <?php foreach (($taskStatuses ?? []) as $status): ?>
                                                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                    <label for="task-assigned" class="form-label">Asignado a</label>
                                                    <select class="form-select" id="task-assigned" name="assigned_to">
                                                        <option value="">Sin asignar</option>
                                                        <?php include __DIR__ . '/partials/user_options.php'; ?>
                                                    </select>
                                                </div>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="task-due" class="form-label">Fecha límite</label>
                                                        <input type="date" class="form-control" id="task-due" name="due_date">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="task-remind" class="form-label">Recordar en</label>
                                                        <input type="datetime-local" class="form-control" id="task-remind" name="remind_at">
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="task-channel" class="form-label">Canal de recordatorio</label>
                                                    <select class="form-select" id="task-channel" name="remind_channel">
                                                        <option value="in_app">Notificación interna</option>
                                                        <option value="email">Correo electrónico</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-warning w-100 text-white">Crear tarea</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="crm-tab-tickets" role="tabpanel" aria-labelledby="crm-tab-tickets-link">
                            <div class="row g-3">
                                <div class="col-xl-7">
                                    <div class="table-responsive rounded card-table shadow-sm">
                                        <table class="table table-striped table-sm align-middle" id="crm-tickets-table">
                                            <thead class="bg-info text-white">
                                                <tr>
                                                    <th>Asunto</th>
                                                    <th>Estado</th>
                                                    <th>Prioridad</th>
                                                    <th>Reporta</th>
                                                    <th>Asignado</th>
                                                    <th>Relacionado</th>
                                                    <th>Actualizado</th>
                                                    <th class="text-end">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-xl-5">
                                    <?php if ($permissions['manageTickets']): ?>
                                        <div class="box mb-3">
                                            <div class="box-header with-border">
                                                <h5 class="box-title mb-0">Nuevo ticket interno</h5>
                                            </div>
                                            <div class="box-body">
                                                <form id="ticket-form" class="space-y-2">
                                                <div class="mb-2">
                                                    <label for="ticket-subject" class="form-label">Asunto *</label>
                                                    <input type="text" class="form-control" id="ticket-subject" name="subject" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="ticket-message" class="form-label">Detalle *</label>
                                                    <textarea class="form-control" id="ticket-message" name="message" rows="3" required></textarea>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="ticket-priority" class="form-label">Prioridad</label>
                                                        <select class="form-select" id="ticket-priority" name="priority">
                                                            <?php foreach (($ticketPriorities ?? []) as $priority): ?>
                                                                <option value="<?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords($priority), ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="ticket-status" class="form-label">Estado</label>
                                                        <select class="form-select" id="ticket-status" name="status">
                                                            <?php foreach (($ticketStatuses ?? []) as $status): ?>
                                                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="ticket-assigned" class="form-label">Asignado a</label>
                                                    <select class="form-select" id="ticket-assigned" name="assigned_to">
                                                        <option value="">Sin asignar</option>
                                                        <?php include __DIR__ . '/partials/user_options.php'; ?>
                                                    </select>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="ticket-lead" class="form-label">Lead</label>
                                                        <select class="form-select" id="ticket-lead" name="related_lead_id" data-placeholder="Ninguno">
                                                            <option value="">Ninguno</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="ticket-project" class="form-label">Proyecto</label>
                                                        <select class="form-select" id="ticket-project" name="related_project_id" data-placeholder="Ninguno">
                                                            <option value="">Ninguno</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-info w-100 text-white">Crear ticket</button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="box">
                                            <div class="box-header with-border d-flex justify-content-between align-items-center">
                                                <h5 class="box-title mb-0">Responder ticket</h5>
                                                <span class="badge bg-light text-muted" id="ticket-reply-selected">Sin selección</span>
                                            </div>
                                            <div class="box-body">
                                                <form id="ticket-reply-form" class="space-y-2">
                                                <input type="hidden" id="ticket-reply-id" name="ticket_id" value="">
                                                <div class="alert alert-info mb-3" id="ticket-reply-helper">Selecciona un ticket en la tabla para responder.</div>
                                                <div class="mb-2">
                                                    <label for="ticket-reply-message" class="form-label">Mensaje *</label>
                                                    <textarea class="form-control" id="ticket-reply-message" name="message" rows="3" required disabled></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="ticket-reply-status" class="form-label">Actualizar estado</label>
                                                    <select class="form-select" id="ticket-reply-status" name="status" disabled>
                                                        <?php foreach (($ticketStatuses ?? []) as $status): ?>
                                                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-info w-100 text-white" disabled>Enviar respuesta</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            Tu rol no permite crear ni responder tickets en el CRM.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="crm-tab-proposals" role="tabpanel" aria-labelledby="crm-tab-proposals-link">
                            <div class="card mb-3">
                                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                                    <div>
                                        <h5 class="mb-0">Propuestas</h5>
                                    </div>
                                    <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                                        <button class="btn btn-primary btn-sm" id="proposal-new-btn">
                                            <i class="fa-regular fa-plus me-1"></i> Nueva propuesta
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" id="proposal-pipeline-btn" title="Ver pipeline">
                                            <i class="fa-solid fa-grip-vertical"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" id="proposal-export-btn" title="Exportar PDF">
                                            <i class="fa-regular fa-file-pdf"></i>
                                        </button>
                                        <div class="input-group input-group-sm" style="max-width: 240px;">
                                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                                            <input type="search" class="form-control" id="proposal-search" placeholder="Buscar #, asunto o destinatario">
                                        </div>
                                        <select class="form-select form-select-sm" id="proposal-status-filter" style="min-width: 140px;">
                                            <option value="">Todos</option>
                                            <?php foreach (($proposalStatuses ?? []) as $statusOption): ?>
                                                <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($statusOption), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline-secondary btn-sm" id="proposal-refresh-btn" title="Recargar">
                                            <i class="mdi mdi-refresh"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-xl-7">
                                            <div class="table-responsive rounded card-table shadow-sm">
                                                <table class="table table-sm align-middle mb-0" id="crm-proposals-table">
                                                    <thead class="bg-info text-white">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Asunto</th>
                                                            <th>Para</th>
                                                            <th class="text-end">Total</th>
                                                            <th>Estado</th>
                                                            <th class="text-end"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="col-xl-5">
                                            <div class="card mb-3">
                                                <div class="card-header d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <small class="text-muted text-uppercase d-block">Detalle</small>
                                                        <h5 class="mb-0" id="proposal-preview-title">Selecciona una propuesta</h5>
                                                    </div>
                                                    <span class="badge bg-secondary" id="proposal-preview-status">—</span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="d-flex flex-wrap gap-3 mb-3">
                                                        <div>
                                                            <div class="text-muted text-uppercase small">Número</div>
                                                            <div id="proposal-preview-number" class="fw-semibold">—</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-muted text-uppercase small">Para</div>
                                                            <div id="proposal-preview-to" class="fw-semibold">—</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-muted text-uppercase small">Válida hasta</div>
                                                            <div id="proposal-preview-valid" class="fw-semibold">—</div>
                                                        </div>
                                                        <div class="ms-auto">
                                                            <div class="text-muted text-uppercase small">Total</div>
                                                            <div id="proposal-preview-total" class="fw-bold fs-5">—</div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <button class="btn btn-primary btn-sm" id="proposal-preview-open" disabled>
                                                            <i class="mdi mdi-eye"></i> Ver detalle
                                                        </button>
                                                        <button class="btn btn-outline-secondary btn-sm" id="proposal-preview-refresh" disabled>
                                                            <i class="mdi mdi-refresh"></i> Actualizar
                                                        </button>
                                                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#proposal-create-collapse" aria-expanded="false" aria-controls="proposal-create-collapse">
                                                            <i class="mdi mdi-pencil-outline"></i> Crear/editar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse" id="proposal-create-collapse">
                                                <div class="card mb-3">
                                                    <div class="card-header">
                                                        <h5 class="mb-0">Nueva propuesta rápida</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <form id="proposal-form" class="row g-3">
                                                        <div class="col-12">
                                                            <label class="form-label">Lead</label>
                                                            <select class="form-select form-select-sm" id="proposal-lead"></select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Título</label>
                                                            <input type="text" class="form-control form-control-sm" id="proposal-title" placeholder="Ej: Procedimiento ambulatorio">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Válida hasta</label>
                                                            <input type="date" class="form-control form-control-sm" id="proposal-valid-until">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Impuesto (%)</label>
                                                            <input type="number" class="form-control form-control-sm" step="0.01" id="proposal-tax-rate" value="0">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Notas internas</label>
                                                            <textarea class="form-control form-control-sm" rows="2" id="proposal-notes"></textarea>
                                                        </div>
                                                    </form>
                                                    </div>
                                                </div>

                                                <div class="card mb-3">
                                                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                                                        <strong>Detalle económico</strong>
                                                        <div class="ms-auto d-flex gap-2">
                                                        <button class="btn btn-outline-primary btn-sm" id="proposal-add-package-btn">
                                                            <i class="mdi mdi-package-variant-closed"></i> Agregar paquete
                                                        </button>
                                                        <button class="btn btn-outline-primary btn-sm" id="proposal-add-code-btn">
                                                            <i class="mdi mdi-magnify"></i> Buscar código
                                                        </button>
                                                        <button class="btn btn-outline-primary btn-sm" id="proposal-add-custom-btn">
                                                            <i class="mdi mdi-plus"></i> Línea manual
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm align-middle" id="proposal-items-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Concepto</th>
                                                                    <th class="text-center" style="width: 75px;">Cant.</th>
                                                                    <th class="text-center" style="width: 100px;">Precio</th>
                                                                    <th class="text-center" style="width: 80px;">Desc %</th>
                                                                    <th class="text-end" style="width: 110px;">Total</th>
                                                                    <th style="width: 40px;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="proposal-items-body">
                                                                <tr class="text-center text-muted" data-empty-row>
                                                                    <td colspan="6">Agrega un paquete o código para iniciar</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
                                                    <div class="totals">
                                                        <div>Subtotal: <strong id="proposal-subtotal">$0.00</strong></div>
                                                        <div>Impuesto: <strong id="proposal-tax">$0.00</strong></div>
                                                        <div>Total: <strong id="proposal-total">$0.00</strong></div>
                                                    </div>
                                                    <button class="btn btn-success" id="proposal-save-btn">
                                                        <i class="mdi mdi-send"></i> Crear propuesta
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="proposal-package-modal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Seleccionar paquete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="input-group input-group-sm mb-3">
                                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                                <input type="search" class="form-control" id="proposal-package-search" placeholder="Buscar paquete">
                                            </div>
                                            <div id="proposal-package-list" class="row g-2"></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="proposal-code-modal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Buscar código</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="input-group input-group-sm mb-3">
                                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                                <input type="search" class="form-control" id="proposal-code-search-input" placeholder="Código o descripción">
                                                <button class="btn btn-primary" id="proposal-code-search-btn">Buscar</button>
                                            </div>
                                            <div class="table-responsive" style="max-height: 400px;">
                                                <table class="table table-sm align-middle">
                                                    <thead>
                                                        <tr>
                                                            <th>Código</th>
                                                            <th>Descripción</th>
                                                            <th class="text-end">Referencia</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="proposal-code-results">
                                                        <tr class="text-center text-muted" data-empty-row>
                                                            <td colspan="4">Inicia una búsqueda</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="proposal-detail-modal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div class="w-100">
                                                <div class="d-flex align-items-center gap-2">
                                                    <h5 class="mb-0" id="proposal-detail-title">Propuesta</h5>
                                                    <span class="badge bg-secondary" id="proposal-detail-status">—</span>
                                                </div>
                                                <small class="text-muted" id="proposal-detail-subtitle">Selecciona una propuesta para ver el detalle</small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div id="proposal-detail-loading" class="text-center text-muted py-4 d-none">
                                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                                Cargando propuesta...
                                            </div>
                                            <div id="proposal-detail-empty" class="alert alert-info mb-3">
                                                Selecciona una propuesta para ver la información completa.
                                            </div>
                                            <div id="proposal-detail-content" class="d-none">
                                                <div class="row g-3">
                                                    <div class="col-lg-8">
                                                        <div class="card border mb-3">
                                                            <div class="card-header d-flex align-items-center justify-content-between">
                                                                <strong>Ítems</strong>
                                                                <span class="badge bg-light text-dark" id="proposal-detail-items-count">0 ítems</span>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm align-middle">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Concepto</th>
                                                                                <th class="text-center" style="width: 90px;">Cant.</th>
                                                                                <th class="text-end" style="width: 110px;">Precio</th>
                                                                                <th class="text-end" style="width: 100px;">Desc.</th>
                                                                                <th class="text-end" style="width: 120px;">Total</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="proposal-detail-items-body">
                                                                            <tr class="text-center text-muted">
                                                                                <td colspan="5">Sin ítems</td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border">
                                                            <div class="card-header d-flex align-items-center gap-2">
                                                                <strong>Notas y términos</strong>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-3">
                                                                    <h6 class="text-muted text-uppercase small mb-1">Notas</h6>
                                                                    <p class="mb-0 text-break" id="proposal-detail-notes">—</p>
                                                                </div>
                                                                <div>
                                                                    <h6 class="text-muted text-uppercase small mb-1">Términos</h6>
                                                                    <p class="mb-0 text-break" id="proposal-detail-terms">—</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <div class="card border mb-3">
                                                            <div class="card-header d-flex align-items-center justify-content-between">
                                                                <strong>Resumen</strong>
                                                                <select id="proposal-detail-status-select" class="form-select form-select-sm" aria-label="Actualizar estado"></select>
                                                            </div>
                                                            <div class="card-body small">
                                                                <div class="mb-2">
                                                                    <div class="text-muted text-uppercase small">Lead/Cliente</div>
                                                                    <div id="proposal-detail-lead" class="fw-semibold">—</div>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <div class="text-muted text-uppercase small">Válida hasta</div>
                                                                    <div id="proposal-detail-valid-until" class="fw-semibold">—</div>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <div class="text-muted text-uppercase small">Creada</div>
                                                                    <div id="proposal-detail-created" class="fw-semibold">—</div>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <div class="text-muted text-uppercase small">Impuesto</div>
                                                                    <div id="proposal-detail-tax-rate" class="fw-semibold">—</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border mb-3">
                                                            <div class="card-header"><strong>Totales</strong></div>
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between">
                                                                    <span class="text-muted">Subtotal</span>
                                                                    <span id="proposal-detail-subtotal" class="fw-semibold">$0.00</span>
                                                                </div>
                                                                <div class="d-flex justify-content-between">
                                                                    <span class="text-muted">Descuento</span>
                                                                    <span id="proposal-detail-discount" class="fw-semibold">$0.00</span>
                                                                </div>
                                                                <div class="d-flex justify-content-between">
                                                                    <span class="text-muted">Impuestos</span>
                                                                    <span id="proposal-detail-tax" class="fw-semibold">$0.00</span>
                                                                </div>
                                                                <hr class="my-2">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <span class="fw-bold">Total</span>
                                                                    <span id="proposal-detail-total" class="fw-bold fs-5">$0.00</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border">
                                                            <div class="card-header"><strong>Hitos</strong></div>
                                                            <div class="card-body" id="proposal-detail-timeline">
                                                                <p class="text-muted mb-0">Sin actividad registrada</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Lead detail modal appended -->
<div class="modal fade" id="lead-detail-modal" tabindex="-1" aria-labelledby="lead-detail-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content data">
            <div class="modal-header">
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h4 class="modal-title d-flex align-items-center" id="lead-detail-label">
                        <span id="lead-detail-title">#— - Lead</span>
                    </h4>
                    <a href="#" class="lead-print-btn text-muted d-flex align-items-center" role="button">
                        <i class="fa-solid fa-print me-2"></i>
                        <span>Print</span>
                    </a>
                </div>
            </div>
            <div class="modal-body" id="lead-detail-body">
                <input type="hidden" id="lead-detail-id" value="">
                <div class="top-lead-menu">
                    <div class="horizontal-scrollable-tabs mb-3">
                        <div class="horizontal-tabs">
                            <ul class="nav nav-tabs nav-tabs-horizontal nav-tabs-segmented" role="tablist">
                                <li role="presentation" class="active">
                                    <a href="#tab_lead_profile" aria-controls="tab_lead_profile" role="tab" data-bs-toggle="tab">
                                        <i class="fa-regular fa-user menu-icon"></i>
                                        Profile
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#tab_proposals_leads" aria-controls="tab_proposals_leads" role="tab" data-bs-toggle="tab">
                                        <i class="fa-regular fa-file-lines menu-icon"></i>
                                        Proposals
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#tab_tasks_leads" aria-controls="tab_tasks_leads" role="tab" data-bs-toggle="tab">
                                        <i class="fa-regular fa-circle-check menu-icon"></i>
                                        Tasks
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#attachments" aria-controls="attachments" role="tab" data-bs-toggle="tab">
                                        <i class="fa-solid fa-paperclip menu-icon"></i>
                                        Attachments
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#lead_reminders" aria-controls="lead_reminders" role="tab" data-bs-toggle="tab">
                                        <i class="fa-regular fa-bell menu-icon"></i>
                                        Reminders
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#lead_notes" aria-controls="lead_notes" role="tab" data-bs-toggle="tab">
                                        <i class="fa-regular fa-note-sticky menu-icon"></i>
                                        Notes <span class="badge" id="lead-notes-count">0</span>
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#lead_activity" aria-controls="lead_activity" role="tab" data-bs-toggle="tab">
                                        <i class="fa-solid fa-grip-lines-vertical menu-icon"></i>
                                        Activity Log
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="tab_lead_profile">
                        <div class="lead-wrapper">
                            <div class="d-flex align-items-center justify-content-end gap-2 mb-2">
                                <div class="lead-edit d-none" id="lead-detail-edit-actions">
                                    <button type="button" class="btn btn-primary lead-top-btn" id="lead-detail-save">Save</button>
                                </div>
                                <a href="#" class="btn btn-primary lead-top-btn lead-view" id="lead-detail-convert">
                                    <i class="fa-regular fa-user"></i>
                                    Convert to customer
                                </a>
                                <button type="button" class="btn btn-default lead-top-btn lead-view" id="lead-detail-edit">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <div class="btn-group lead-view" id="lead-more-btn">
                                    <button type="button" class="btn btn-default dropdown-toggle lead-top-btn" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        More
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" id="lead-more-dropdown">
                                        <li><a href="#" class="dropdown-item">Mark as lost</a></li>
                                        <li><a href="#" class="dropdown-item">Mark as junk</a></li>
                                        <li><a href="#" class="dropdown-item text-danger">Delete lead</a></li>
                                    </ul>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="lead-view" id="lead-view-section">
                                <div class="row">
                                    <div class="col-md-4 col-xs-12 lead-information-col">
                                        <div class="lead-info-heading"><h4>Lead Information</h4></div>
                                        <dl>
                                            <dt class="text-muted">Name</dt>
                                            <dd class="fw-semibold" id="lead-view-name">—</dd>
                                            <dt class="text-muted">Position</dt>
                                            <dd id="lead-view-position">—</dd>
                                            <dt class="text-muted">Email</dt>
                                            <dd id="lead-view-email">—</dd>
                                            <dt class="text-muted">Website</dt>
                                            <dd id="lead-view-website">—</dd>
                                            <dt class="text-muted">Phone</dt>
                                            <dd id="lead-view-phone">—</dd>
                                            <dt class="text-muted">Lead value</dt>
                                            <dd id="lead-view-value">—</dd>
                                            <dt class="text-muted">Company</dt>
                                            <dd id="lead-view-company">—</dd>
                                            <dt class="text-muted">Address</dt>
                                            <dd id="lead-view-address">—</dd>
                                            <dt class="text-muted">City</dt>
                                            <dd id="lead-view-city">—</dd>
                                            <dt class="text-muted">State</dt>
                                            <dd id="lead-view-state">—</dd>
                                            <dt class="text-muted">Country</dt>
                                            <dd id="lead-view-country">—</dd>
                                            <dt class="text-muted">Zip Code</dt>
                                            <dd id="lead-view-zip">—</dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-4 col-xs-12 lead-information-col">
                                        <div class="lead-info-heading"><h4>General Information</h4></div>
                                        <dl>
                                            <dt class="text-muted">Status</dt>
                                            <dd id="lead-view-status" class="mb-2">—</dd>
                                            <dt class="text-muted">Source</dt>
                                            <dd id="lead-view-source">—</dd>
                                            <dt class="text-muted">Default language</dt>
                                            <dd id="lead-view-language">—</dd>
                                            <dt class="text-muted">Assigned</dt>
                                            <dd id="lead-view-assigned">—</dd>
                                            <dt class="text-muted">Tags</dt>
                                            <dd id="lead-view-tags">—</dd>
                                            <dt class="text-muted">Created</dt>
                                            <dd id="lead-view-created">—</dd>
                                            <dt class="text-muted">Last Contact</dt>
                                            <dd id="lead-view-last-contact">—</dd>
                                            <dt class="text-muted">Public</dt>
                                            <dd id="lead-view-public">—</dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-4 col-xs-12 lead-information-col">
                                        <div class="lead-info-heading"><h4>Extra</h4></div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-md-12">
                                        <dl>
                                            <dt class="text-muted">Description</dt>
                                            <dd id="lead-view-description">—</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="lead-edit d-none" id="lead-edit-section">
                                <form id="lead-detail-edit-form" class="row g-2">
                                    <div class="col-12">
                                        <p class="text-muted small mb-2">Datos demográficos provienen de Historia Clínica.</p>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead-detail-status" class="control-label">Status</label>
                                            <select id="lead-detail-status" name="status" class="form-select">
                                                <option value="">Seleccionar</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead-detail-source" class="control-label">Source</label>
                                            <input type="text" class="form-control" id="lead-detail-source" name="source">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead-detail-assigned" class="control-label">Assigned</label>
                                            <select id="lead-detail-assigned" name="assigned_to" class="form-select">
                                                <option value="">Sin asignar</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lead-detail-name" class="control-label">Name</label>
                                            <input type="text" class="form-control" id="lead-detail-name" name="name">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-email" class="control-label">Email</label>
                                            <input type="email" class="form-control" id="lead-detail-email" name="email">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-phone" class="control-label">Phone</label>
                                            <input type="text" class="form-control" id="lead-detail-phone" name="phone">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-company" class="control-label">Company</label>
                                            <input type="text" class="form-control bg-light" id="lead-detail-company" name="company" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lead-detail-address" class="control-label">Address</label>
                                            <textarea id="lead-detail-address" name="address" class="form-control bg-light" rows="2" readonly></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-city" class="control-label">City</label>
                                            <input type="text" class="form-control bg-light" id="lead-detail-city" name="city" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-state" class="control-label">State</label>
                                            <input type="text" class="form-control bg-light" id="lead-detail-state" name="state" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-zip" class="control-label">Zip</label>
                                            <input type="text" class="form-control bg-light" id="lead-detail-zip" name="zip" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="lead-detail-description" class="control-label">Description</label>
                                            <textarea id="lead-detail-description" name="description" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="lead-latest-activity mb-3 lead-view">
                                <div class="lead-info-heading"><h4>Latest Activity</h4></div>
                                <div id="lead-latest-activity">En desarrollo</div>
                            </div>
                            <div class="lead-edit d-none" id="lead-edit-footer">
                                <hr>
                                <button type="button" class="btn btn-primary pull-right" id="lead-detail-save-footer">Save</button>
                                <button type="button" class="btn btn-default pull-right me-2" id="lead-detail-cancel">Close</button>
                            </div>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="tab_proposals_leads">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="tab_tasks_leads">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Casos / Proyectos vinculados</h5>
                                    <?php if ($permissions['manageProjects']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="lead-project-create">Crear caso</button>
                                    <?php endif; ?>
                                </div>
                                <div id="lead-projects-empty" class="text-muted small">Sin proyectos vinculados.</div>
                                <div class="list-group" id="lead-projects-list"></div>
                            </div>
                            <div class="col-lg-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Tareas del lead</h5>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="lead-tasks-refresh">Refrescar</button>
                                </div>
                                <div id="lead-tasks-empty" class="text-muted small">Sin tareas vinculadas.</div>
                                <div class="list-group" id="lead-tasks-list"></div>
                            </div>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="attachments">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="lead_reminders">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="lead_notes">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="lead_activity">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lead-convert-modal" tabindex="-1" aria-labelledby="lead-convert-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content data">
            <div class="modal-header">
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h4 class="modal-title" id="lead-convert-label">Convertir lead a cliente</h4>
                </div>
            </div>
            <div class="modal-body">
                <div class="horizontal-scrollable-tabs mb-3">
                    <div class="horizontal-tabs">
                        <ul class="nav nav-tabs nav-tabs-horizontal nav-tabs-segmented" role="tablist">
                            <li role="presentation" class="active">
                                <a href="#tab_convert_profile" aria-controls="tab_convert_profile" role="tab" data-bs-toggle="tab">Datos</a>
                            </li>
                            <li role="presentation">
                                <a href="#tab_convert_summary" aria-controls="tab_convert_summary" role="tab" data-bs-toggle="tab">Resumen</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="tab_convert_profile">
                        <form id="lead-convert-form" class="space-y-2">
                            <input type="hidden" id="convert-lead-hc" name="hc_number" value="">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="text-muted small">Lead seleccionado:</div>
                                <span id="convert-lead-selected" class="badge bg-secondary">Sin selección</span>
                            </div>
                            <div class="alert alert-info mb-3" id="convert-helper">Selecciona un lead en la tabla para precargar los datos.</div>
                            <div class="mb-2">
                                <label for="convert-name" class="form-label">Nombre completo</label>
                                <input type="text" class="form-control" id="convert-name" name="customer_name" placeholder="Nombre del paciente">
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="convert-email" class="form-label">Correo</label>
                                    <input type="email" class="form-control" id="convert-email" name="customer_email">
                                </div>
                                <div class="col-md-6">
                                    <label for="convert-phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="convert-phone" name="customer_phone">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="convert-document" class="form-label">Documento</label>
                                    <input type="text" class="form-control" id="convert-document" name="customer_document">
                                </div>
                                <div class="col-md-6">
                                    <label for="convert-external" class="form-label">Referencia externa</label>
                                    <input type="text" class="form-control" id="convert-external" name="customer_external_ref">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="convert-affiliation" class="form-label">Afiliación</label>
                                <input type="text" class="form-control" id="convert-affiliation" name="customer_affiliation">
                            </div>
                            <div class="mb-3">
                                <label for="convert-address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" id="convert-address" name="customer_address">
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success" disabled>Convertir lead</button>
                            </div>
                        </form>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="tab_convert_summary">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="projectCreateModal" tabindex="-1" aria-labelledby="project-create-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="project-create-label">Nuevo proyecto clínico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="project-form" class="space-y-2">
                    <div class="mb-2">
                        <label for="project-title" class="form-label">Nombre del proyecto *</label>
                        <input type="text" class="form-control" id="project-title" name="title" required>
                    </div>
                    <div class="mb-2">
                        <label for="project-description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="project-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="project-status" class="form-label">Estado</label>
                            <select class="form-select" id="project-status" name="status">
                                <?php foreach (($projectStatuses ?? []) as $status): ?>
                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="project-owner" class="form-label">Responsable</label>
                            <select class="form-select" id="project-owner" name="owner_id">
                                <option value="">Sin asignar</option>
                                <?php include __DIR__ . '/partials/user_options.php'; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="project-lead" class="form-label">Lead asociado</label>
                            <select class="form-select" id="project-lead" name="lead_id" data-placeholder="Sin lead">
                                <option value="">Sin lead</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="project-customer" class="form-label">ID Cliente</label>
                            <input type="number" class="form-control" id="project-customer" name="customer_id" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="project-start" class="form-label">Fecha inicio</label>
                            <input type="date" class="form-control" id="project-start" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label for="project-due" class="form-label">Fecha entrega</label>
                            <input type="date" class="form-control" id="project-due" name="due_date">
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Registrar proyecto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="projectDetailModal" tabindex="-1" aria-labelledby="project-detail-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-md-down modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex flex-column">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="modal-title mb-0" id="project-detail-label">Detalle del proyecto</h5>
                        <span class="badge bg-secondary" id="project-detail-status">—</span>
                    </div>
                    <small class="text-muted" id="project-detail-subtitle">Selecciona un proyecto para ver el detalle.</small>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a class="btn btn-outline-secondary btn-sm" id="project-detail-open" href="#" target="_blank" rel="noopener">
                        Abrir en CRM
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="project-detail-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="project-detail-overview-tab" data-bs-toggle="tab" data-bs-target="#project-detail-overview" type="button" role="tab" aria-controls="project-detail-overview" aria-selected="true">Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="project-detail-tasks-tab" data-bs-toggle="tab" data-bs-target="#project-detail-tasks" type="button" role="tab" aria-controls="project-detail-tasks" aria-selected="false">Tareas</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="project-detail-overview" role="tabpanel" aria-labelledby="project-detail-overview-tab">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h6 class="text-uppercase text-muted small mb-1">Overview</h6>
                                <div class="fw-semibold" id="project-detail-title">—</div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center" id="project-detail-action-bar">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="project-detail-edit-btn">
                                    <i class="mdi mdi-pencil-outline"></i> Editar
                                </button>
                                <select class="form-select form-select-sm" id="project-detail-status-select" style="min-width: 160px;" disabled>
                                    <option value="">Cambiar estado</option>
                                </select>
                                <select class="form-select form-select-sm" id="project-detail-owner-select" style="min-width: 180px;" disabled>
                                    <option value="">Asignar responsable</option>
                                </select>
                                <button type="button" class="btn btn-success btn-sm d-none" id="project-detail-save-btn">
                                    <i class="mdi mdi-content-save-outline"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="project-detail-cancel-btn">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-5 text-muted">Proyecto / ID</dt>
                                            <dd class="col-sm-7 fw-semibold" id="project-detail-project-id">—</dd>
                                            <dt class="col-sm-5 text-muted">Solicitud #</dt>
                                            <dd class="col-sm-7" id="project-detail-request">—</dd>
                                            <dt class="col-sm-5 text-muted">Paciente / Lead</dt>
                                            <dd class="col-sm-7" id="project-detail-lead">—</dd>
                                            <dt class="col-sm-5 text-muted">Estado</dt>
                                            <dd class="col-sm-7">
                                                <span id="project-detail-status-text">—</span>
                                            </dd>
                                            <dt class="col-sm-5 text-muted">Responsable</dt>
                                            <dd class="col-sm-7">
                                                <span id="project-detail-owner">—</span>
                                            </dd>
                                            <dt class="col-sm-5 text-muted">Inicio</dt>
                                            <dd class="col-sm-7">
                                                <span id="project-detail-start">—</span>
                                                <input type="date" class="form-control form-control-sm d-none mt-1" id="project-detail-start-input">
                                            </dd>
                                            <dt class="col-sm-5 text-muted">Entrega</dt>
                                            <dd class="col-sm-7">
                                                <span id="project-detail-due">—</span>
                                                <input type="date" class="form-control form-control-sm d-none mt-1" id="project-detail-due-input">
                                            </dd>
                                            <dt class="col-sm-5 text-muted">Actualizado</dt>
                                            <dd class="col-sm-7" id="project-detail-updated">—</dd>
                                        </dl>
                                        <hr class="my-3">
                                        <h6 class="text-muted text-uppercase small mb-2">Descripción</h6>
                                        <p class="mb-0" id="project-detail-description">—</p>
                                        <textarea class="form-control form-control-sm d-none mt-2" id="project-detail-description-input" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="card border mb-3">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <strong>Tareas</strong>
                                        <span class="badge bg-light text-muted" id="project-detail-tasks-summary">—</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">Abiertas / Total</span>
                                            <span class="fw-semibold" id="project-detail-tasks-count">0 / 0</span>
                                        </div>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" id="project-detail-tasks-progress"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card border">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <strong>Días restantes</strong>
                                        <span class="badge bg-light text-muted" id="project-detail-days-label">—</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">Hasta entrega</span>
                                            <span class="fw-semibold" id="project-detail-days-remaining">—</span>
                                        </div>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: 0%;" id="project-detail-days-progress"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="project-detail-tasks" role="tabpanel" aria-labelledby="project-detail-tasks-tab">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div class="d-flex flex-wrap gap-2 align-items-center" id="project-tasks-filters">
                                <button type="button" class="btn btn-outline-secondary btn-sm active" data-status-filter="all">Todas <span class="badge bg-light text-muted ms-1" data-count="all">0</span></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-status-filter="pendiente">Pendiente <span class="badge bg-light text-muted ms-1" data-count="pendiente">0</span></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-status-filter="en_progreso">En progreso <span class="badge bg-light text-muted ms-1" data-count="en_progreso">0</span></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-status-filter="completada">Completada <span class="badge bg-light text-muted ms-1" data-count="completada">0</span></button>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="project-tasks-export">
                                    <i class="mdi mdi-file-export-outline"></i> Exportar CSV
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="project-tasks-reload">
                                    <i class="mdi mdi-refresh"></i> Recargar
                                </button>
                            </div>
                        </div>
                        <div id="project-tasks-loading" class="text-muted small mb-2 d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Cargando tareas...
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle" id="project-tasks-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Inicio</th>
                                        <th>Entrega</th>
                                        <th>Asignado</th>
                                        <th>Prioridad</th>
                                        <th>Tags</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="project-tasks-body">
                                    <tr class="text-center text-muted" data-empty-row>
                                        <td colspan="9">Sin tareas registradas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="project-tasks-empty" class="text-muted small mt-2 d-none">No hay tareas asociadas.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
