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
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="project-filter-status" class="form-label mb-1">Estado</label>
                    <select id="project-filter-status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (($projectStatuses ?? []) as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="project-filter-owner" class="form-label mb-1">Responsable</label>
                    <select id="project-filter-owner" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (($assignableUsers ?? []) as $user): ?>
                            <option value="<?= htmlspecialchars((string) ($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="project-filter-lead" class="form-label mb-1">Lead ID</label>
                    <input type="text" id="project-filter-lead" class="form-control form-control-sm" placeholder="Ej: 120">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-customer" class="form-label mb-1">Cliente ID</label>
                    <input type="text" id="project-filter-customer" class="form-control form-control-sm" placeholder="Ej: 55">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-hc" class="form-label mb-1">HC</label>
                    <input type="text" id="project-filter-hc" class="form-control form-control-sm" placeholder="HC del paciente">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-source-module" class="form-label mb-1">Módulo origen</label>
                    <input type="text" id="project-filter-source-module" class="form-control form-control-sm" placeholder="Ej: solicitudes">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-source-ref" class="form-label mb-1">Referencia origen</label>
                    <input type="text" id="project-filter-source-ref" class="form-control form-control-sm" placeholder="ID referencia">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-form" class="form-label mb-1">Formulario ID</label>
                    <input type="text" id="project-filter-form" class="form-control form-control-sm" placeholder="Ej: 8">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-episode" class="form-label mb-1">Tipo episodio</label>
                    <input type="text" id="project-filter-episode" class="form-control form-control-sm" placeholder="Ej: cirugía">
                </div>
                <div class="col-md-3">
                    <label for="project-filter-eye" class="form-label mb-1">Ojo</label>
                    <input type="text" id="project-filter-eye" class="form-control form-control-sm" placeholder="OD/OS/Ambos">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                    <button class="btn btn-sm btn-secondary" type="button" id="project-filter-clear">Limpiar filtros</button>
                    <button class="btn btn-sm btn-primary" type="button" id="project-filter-apply">Aplicar filtros</button>
                </div>
            </div>
        </div>
    </div>
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
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-2 gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 160px;">
                <span class="input-group-text"><i class="mdi mdi-format-list-numbered"></i></span>
                <select id="project-page-size" class="form-select">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="project-reload-btn">
                <i class="mdi mdi-refresh"></i>
            </button>
        </div>
        <div class="text-muted small" id="project-table-info">Mostrando 0 de 0</div>
        <ul class="pagination pagination-sm mb-0" id="project-pagination"></ul>
    </div>
</div>
