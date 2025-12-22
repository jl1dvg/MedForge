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
                            <div class="card mb-3 shadow-sm border-0 bg-light">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div>
                                            <h5 class="mb-1">Resumen del pipeline</h5>
                                            <p class="text-muted mb-0">Visualiza el avance de los leads y accede rápido a los estados.</p>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <a class="btn btn-primary" href="#lead-form">
                                                <i class="mdi mdi-plus"></i> Nuevo lead
                                            </a>
                                            <button class="btn btn-outline-primary" type="button" id="lead-refresh-btn">
                                                <i class="mdi mdi-refresh"></i> Recargar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3" id="lead-status-summary"></div>
                                    <div class="row g-2 align-items-end mt-1">
                                        <div class="col-md-4">
                                            <label for="lead-search" class="form-label mb-1">Buscar</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                                <input type="search" class="form-control" id="lead-search" placeholder="Nombre, email, teléfono o HC">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="lead-filter-status" class="form-label mb-1">Estado</label>
                                            <select id="lead-filter-status" class="form-select form-select-sm">
                                                <option value="">Todos</option>
                                                <?php foreach (($leadStatuses ?? []) as $status): ?>
                                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="lead-filter-source" class="form-label mb-1">Origen</label>
                                            <select id="lead-filter-source" class="form-select form-select-sm">
                                                <option value="">Todos</option>
                                                <?php foreach (($leadSources ?? []) as $source): ?>
                                                    <option value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="lead-filter-assigned" class="form-label mb-1">Asignado</label>
                                            <select id="lead-filter-assigned" class="form-select form-select-sm">
                                                <option value="">Todos</option>
                                                <?php foreach (($assignableUsers ?? []) as $user): ?>
                                                    <option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-12 d-flex flex-wrap gap-2 mt-2">
                                            <button class="btn btn-sm btn-secondary" type="button" id="lead-clear-filters">
                                                Limpiar filtros
                                            </button>
                                            <span class="text-muted small">Los filtros se aplican de inmediato sobre la tabla.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-xl-7">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <div class="input-group input-group-sm" style="width: 160px;">
                                                <span class="input-group-text"><i class="mdi mdi-format-list-numbered"></i></span>
                                                <select id="lead-page-size" class="form-select">
                                                    <option value="10">10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="-1">Todos</option>
                                                </select>
                                            </div>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" id="lead-export-btn">
                                                <i class="mdi mdi-export"></i> Exportar
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" id="lead-bulk-actions-btn" data-bs-toggle="modal" data-bs-target="#lead-bulk-modal">
                                                <i class="mdi mdi-format-list-checks"></i> Acciones masivas
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" id="lead-reload-table">
                                                <i class="mdi mdi-refresh"></i>
                                            </button>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <div class="input-group input-group-sm" style="width: 220px;">
                                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                                <input type="search" class="form-control" id="lead-table-search" placeholder="Buscar en tabla">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive rounded card-table shadow-sm">
                                        <table class="table table-striped table-sm align-middle" id="crm-leads-table">
                                            <thead class="bg-primary text-white">
                                                <tr>
                                                    <th class="text-center" style="width:32px;">
                                                        <input type="checkbox" id="lead-select-all" class="form-check-input">
                                                    </th>
                                                    <th style="width:70px;">#</th>
                                                    <th>Nombre</th>
                                                    <th>Contacto</th>
                                                    <th>Estado</th>
                                                    <th>Origen</th>
                                                    <th>Etiquetas</th>
                                                    <th>Asignado</th>
                                                    <th>Actualizado</th>
                                                    <th class="text-end">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mt-2 gap-2">
                                        <div class="text-muted small" id="lead-table-info">Mostrando 0 de 0</div>
                                        <ul class="pagination pagination-sm mb-0" id="lead-pagination"></ul>
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

                            <div class="modal fade" id="lead-bulk-modal" tabindex="-1" aria-labelledby="lead-bulk-modal-label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="lead-bulk-modal-label">Acciones masivas</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="lead-bulk-delete">
                                                        <label class="form-check-label" for="lead-bulk-delete">Eliminar seleccionados</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="lead-bulk-lost">
                                                        <label class="form-check-label" for="lead-bulk-lost">Marcar como perdido</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="lead-bulk-public">
                                                        <label class="form-check-label" for="lead-bulk-public">Visibilidad pública</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="lead-bulk-status" class="form-label">Cambiar estado</label>
                                                    <select id="lead-bulk-status" class="form-select">
                                                        <option value="">Sin cambio</option>
                                                        <?php foreach (($leadStatuses ?? []) as $status): ?>
                                                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="lead-bulk-source" class="form-label">Origen</label>
                                                    <select id="lead-bulk-source" class="form-select">
                                                        <option value="">Sin cambio</option>
                                                        <?php foreach (($leadSources ?? []) as $source): ?>
                                                            <option value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="lead-bulk-assigned" class="form-label">Asignar a</label>
                                                    <select id="lead-bulk-assigned" class="form-select">
                                                        <option value="">Sin cambio</option>
                                                        <?php foreach (($assignableUsers ?? []) as $user): ?>
                                                            <option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <p class="text-muted small mt-3 mb-0" id="lead-bulk-helper">Selecciona al menos un lead para aplicar los cambios.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            <button type="button" class="btn btn-primary" id="lead-bulk-apply">Aplicar</button>
                                        </div>
                                    </div>
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
                        <div class="tab-pane fade" id="crm-tab-proposals" role="tabpanel">
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
