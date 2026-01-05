<?php
$permissions = $leadViewData['permissions'] ?? [];
$canManageLeads = (bool)($permissions['manageLeads'] ?? false);
$leadStatuses = $leadViewData['leadStatuses'] ?? [];
$leadSources = $leadViewData['leadSources'] ?? [];
$assignableUsers = $leadViewData['assignableUsers'] ?? [];
?>
<div class="row g-3" id="lead-table-section">
    <div class="col-12 col-xl-8">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">
            <div class="d-none d-lg-flex align-items-center gap-2 flex-wrap">
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
            <div class="dropdown d-lg-none crm-toolbar-dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Acciones
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item js-toolbar-action" type="button" data-target="#lead-export-btn">Exportar</button></li>
                    <li><button class="dropdown-item js-toolbar-action" type="button" data-target="#lead-bulk-actions-btn">Acciones masivas</button></li>
                    <li><button class="dropdown-item js-toolbar-action" type="button" data-target="#lead-reload-table">Recargar</button></li>
                </ul>
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
    <div class="col-12 col-xl-4">
        <?php if ($canManageLeads): ?>
            <div class="box mb-3">
                <div class="box-header with-border">
                    <h5 class="box-title mb-0">Convertir a paciente / cliente</h5>
                </div>
                <div class="box-body">
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
