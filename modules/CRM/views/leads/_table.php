<?php
$permissions = $leadViewData['permissions'] ?? [];
$canManageLeads = (bool)($permissions['manageLeads'] ?? false);
$leadStatuses = $leadViewData['leadStatuses'] ?? [];
$leadSources = $leadViewData['leadSources'] ?? [];
$assignableUsers = $leadViewData['assignableUsers'] ?? [];
?>
<div class="row g-3" id="lead-table-section">
    <div class="col-12 col-xl-12">
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
</div>
