<?php
$permissions = $leadViewData['permissions'] ?? [];
$canManageLeads = (bool)($permissions['manageLeads'] ?? false);
?>
<div class="card mb-3 shadow-sm border-0 bg-light crm-sticky-toolbar">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-1">Resumen del pipeline</h5>
                <p class="text-muted mb-0">Visualiza el avance de los leads y accede rápido a los estados.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if ($canManageLeads): ?>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#lead-modal" data-mode="create">
                        <i class="mdi mdi-plus"></i> Nuevo lead
                    </button>
                <?php endif; ?>
                <button class="btn btn-outline-primary" type="button" id="lead-refresh-btn">
                    <i class="mdi mdi-refresh"></i> Recargar
                </button>
                <div class="btn-group" role="group" aria-label="Cambiar vista">
                    <button class="btn btn-outline-secondary active" type="button" id="lead-view-table">Tabla</button>
                    <button class="btn btn-outline-secondary" type="button" id="lead-view-kanban">Kanban</button>
                </div>
            </div>
        </div>
    </div>
</div>
