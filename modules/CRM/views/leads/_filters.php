<?php
$leadStatuses = $leadViewData['leadStatuses'] ?? [];
$leadSources = $leadViewData['leadSources'] ?? [];
$assignableUsers = $leadViewData['assignableUsers'] ?? [];
?>
<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <div class="row g-2 align-items-end">
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
                    <option value="sin_estado">Sin estado</option>
                    <?php foreach ($leadStatuses as $status): ?>
                        <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="lead-filter-source" class="form-label mb-1">Origen</label>
                <select id="lead-filter-source" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($leadSources as $source): ?>
                        <option value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="lead-filter-assigned" class="form-label mb-1">Asignado</label>
                <select id="lead-filter-assigned" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($assignableUsers as $user): ?>
                        <option value="<?= htmlspecialchars((string) ($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
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
