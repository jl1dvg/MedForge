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
$scripts = array_merge($scripts ?? [], [
    'js/pages/crm.js',
]);
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
                            <a class="nav-link active" data-bs-toggle="tab" href="#crm-tab-leads" role="tab">Leads</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#crm-tab-projects" role="tab">Proyectos</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#crm-tab-tasks" role="tab">Tareas</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#crm-tab-tickets" role="tab">Tickets</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#crm-tab-proposals" role="tab">Propuestas</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="crm-tab-leads" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-xl-7">
                                    <div class="table-responsive rounded card-table shadow-sm">
                                        <table class="table table-striped table-sm align-middle" id="crm-leads-table">
                                            <thead class="bg-primary text-white">
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>Contacto</th>
                                                    <th>Estado</th>
                                                    <th>Origen</th>
                                                    <th>Asignado</th>
                                                    <th>Actualizado</th>
                                                    <th class="text-end">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-xl-5">
                                    <?php if ($permissions['manageLeads']): ?>
                                        <div class="box mb-3">
                                            <div class="box-header with-border">
                                                <h5 class="box-title mb-0">Nuevo lead</h5>
                                            </div>
                                            <div class="box-body">
                                                <form id="lead-form" class="space-y-2">
                                                <div class="mb-2">
                                                    <label for="lead-name" class="form-label">Nombre del contacto *</label>
                                                    <input type="text" class="form-control" id="lead-name" name="name" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="lead-hc-number" class="form-label">Historia clínica *</label>
                                                    <input type="text" class="form-control" id="lead-hc-number" name="hc_number" required>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="lead-email" class="form-label">Correo</label>
                                                        <input type="email" class="form-control" id="lead-email" name="email" placeholder="correo@ejemplo.com">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="lead-phone" class="form-label">Teléfono</label>
                                                        <input type="text" class="form-control" id="lead-phone" name="phone">
                                                    </div>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="lead-status" class="form-label">Estado</label>
                                                        <select class="form-select" id="lead-status" name="status">
                                                            <?php foreach (($leadStatuses ?? []) as $status): ?>
                                                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="lead-source" class="form-label">Origen</label>
                                                        <input list="lead-sources" class="form-control" id="lead-source" name="source" placeholder="Campaña, referido...">
                                                        <datalist id="lead-sources">
                                                            <?php foreach (($leadSources ?? []) as $source): ?>
                                                                <option value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"></option>
                                                            <?php endforeach; ?>
                                                        </datalist>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="lead-assigned" class="form-label">Asignado a</label>
                                                    <select class="form-select" id="lead-assigned" name="assigned_to">
                                                        <option value="">Sin asignar</option>
                                                        <?php foreach (($assignableUsers ?? []) as $user): ?>
                                                            <option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="lead-notes" class="form-label">Notas</label>
                                                    <textarea class="form-control" id="lead-notes" name="notes" rows="3"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary w-100">Guardar lead</button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="box">
                                            <div class="box-header with-border d-flex justify-content-between align-items-center">
                                                <h5 class="box-title mb-0">Convertir a paciente / cliente</h5>
                                                <span class="badge bg-light text-muted" id="convert-lead-selected">Sin selección</span>
                                            </div>
                                            <div class="box-body">
                                                <form id="lead-convert-form" class="space-y-2">
                                                <input type="hidden" id="convert-lead-hc" name="hc_number" value="">
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
                                                    <button type="submit" class="btn btn-success w-100" disabled>Convertir lead</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            No tienes permisos para crear o convertir leads en el CRM.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="crm-tab-projects" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-xl-7">
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
                                                    <th class="text-end">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-xl-5">
                                    <?php if ($permissions['manageProjects']): ?>
                                        <div class="box">
                                            <div class="box-header with-border">
                                                <h5 class="box-title mb-0">Nuevo proyecto clínico</h5>
                                            </div>
                                            <div class="box-body">
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
                                                            <?php foreach (($assignableUsers ?? []) as $user): ?>
                                                                <option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
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
                                                    <button type="submit" class="btn btn-success w-100">Registrar proyecto</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            No cuentas con permisos para crear proyectos dentro del CRM.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="crm-tab-tasks" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-xl-7">
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
                                <div class="col-xl-5">
                                    <?php if ($permissions['manageTasks']): ?>
                                        <div class="box">
                                            <div class="box-header with-border">
                                                <h5 class="box-title mb-0">Nueva tarea</h5>
                                            </div>
                                            <div class="box-body">
                                                <form id="task-form" class="space-y-2">
                                                <div class="mb-2">
                                                    <label for="task-project" class="form-label">Proyecto *</label>
                                                    <select class="form-select" id="task-project" name="project_id" required data-placeholder="Selecciona un proyecto">
                                                        <option value="">Selecciona un proyecto</option>
                                                    </select>
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
                                                            <?php foreach (($assignableUsers ?? []) as $user): ?>
                                                                <option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
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
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            No puedes crear tareas en el CRM con el rol asignado.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="crm-tab-tickets" role="tabpanel">
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
                                                        <?php foreach (($assignableUsers ?? []) as $user): ?>
                                                            <option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
                        <div class="tab-pane fade" id="crm-tab-proposals" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-xl-6">
                                    <div class="card h-100">
                                        <div class="card-header d-flex align-items-center justify-content-between">
                                            <div>
                                                <h5 class="mb-0">Propuestas recientes</h5>
                                                <small class="text-muted">Seguimiento rápido de cotizaciones</small>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <select class="form-select form-select-sm" id="proposal-status-filter">
                                                    <option value="">Todas</option>
                                                    <?php foreach (($proposalStatuses ?? []) as $statusOption): ?>
                                                        <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($statusOption), ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-outline-secondary btn-sm" id="proposal-refresh-btn">
                                                    <i class="mdi mdi-refresh"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive rounded card-table shadow-sm">
                                                <table class="table table-sm align-middle" id="crm-proposals-table">
                                                    <thead class="bg-info text-white">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Lead</th>
                                                            <th>Estado</th>
                                                            <th class="text-end">Total</th>
                                                            <th>Vence</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-6">
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
                        </div>
                    </div>
                </div>
            </div>
