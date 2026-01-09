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
</div>
