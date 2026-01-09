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
