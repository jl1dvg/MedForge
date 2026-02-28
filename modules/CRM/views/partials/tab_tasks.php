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
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="task-filter-status" class="form-label mb-1">Estado</label>
                            <select id="task-filter-status" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach (($taskStatuses ?? []) as $status): ?>
                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-assigned" class="form-label mb-1">Responsable</label>
                            <select id="task-filter-assigned" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach (($assignableUsers ?? []) as $user): ?>
                                    <option value="<?= htmlspecialchars((string) ($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-due" class="form-label mb-1">Vencimiento</label>
                            <select id="task-filter-due" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="today">Hoy</option>
                                <option value="week">Próximos 7 días</option>
                                <option value="overdue">Vencidas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-project" class="form-label mb-1">Proyecto ID</label>
                            <input type="text" id="task-filter-project" class="form-control form-control-sm" placeholder="Ej: 45">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-lead" class="form-label mb-1">Lead ID</label>
                            <input type="text" id="task-filter-lead" class="form-control form-control-sm" placeholder="Ej: 120">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-hc" class="form-label mb-1">HC</label>
                            <input type="text" id="task-filter-hc" class="form-control form-control-sm" placeholder="HC del paciente">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-entity-type" class="form-label mb-1">Tipo entidad</label>
                            <input type="text" id="task-filter-entity-type" class="form-control form-control-sm" placeholder="Ej: solicitud">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-entity-id" class="form-label mb-1">Entidad ID</label>
                            <input type="text" id="task-filter-entity-id" class="form-control form-control-sm" placeholder="ID entidad">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-customer" class="form-label mb-1">Cliente ID</label>
                            <input type="text" id="task-filter-customer" class="form-control form-control-sm" placeholder="Ej: 70">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-patient" class="form-label mb-1">Paciente ID</label>
                            <input type="text" id="task-filter-patient" class="form-control form-control-sm" placeholder="ID paciente">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-form" class="form-label mb-1">Formulario ID</label>
                            <input type="text" id="task-filter-form" class="form-control form-control-sm" placeholder="Ej: 9">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-source-module" class="form-label mb-1">Módulo origen</label>
                            <input type="text" id="task-filter-source-module" class="form-control form-control-sm" placeholder="Ej: examenes">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-source-ref" class="form-label mb-1">Referencia origen</label>
                            <input type="text" id="task-filter-source-ref" class="form-control form-control-sm" placeholder="ID referencia">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-episode" class="form-label mb-1">Tipo episodio</label>
                            <input type="text" id="task-filter-episode" class="form-control form-control-sm" placeholder="Ej: postop">
                        </div>
                        <div class="col-md-3">
                            <label for="task-filter-eye" class="form-label mb-1">Ojo</label>
                            <input type="text" id="task-filter-eye" class="form-control form-control-sm" placeholder="OD/OS/Ambos">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                            <button class="btn btn-sm btn-secondary" type="button" id="task-filter-clear">Limpiar filtros</button>
                            <button class="btn btn-sm btn-primary" type="button" id="task-filter-apply">Aplicar filtros</button>
                        </div>
                    </div>
                </div>
            </div>
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
            <div class="d-flex flex-wrap align-items-center justify-content-between mt-2 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 160px;">
                        <span class="input-group-text"><i class="mdi mdi-format-list-numbered"></i></span>
                        <select id="task-page-size" class="form-select">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" id="task-reload-btn">
                        <i class="mdi mdi-refresh"></i>
                    </button>
                </div>
                <div class="text-muted small" id="task-table-info">Mostrando 0 de 0</div>
                <ul class="pagination pagination-sm mb-0" id="task-pagination"></ul>
            </div>
        </div>
    </div>
</div>
