<?php
$leadStatuses = $leadViewData['leadStatuses'] ?? [];
$leadSources = $leadViewData['leadSources'] ?? [];
$assignableUsers = $leadViewData['assignableUsers'] ?? [];
$permissions = $leadViewData['permissions'] ?? [];
$canManageLeads = (bool)($permissions['manageLeads'] ?? false);
?>
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
                            <?php foreach ($leadStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="lead-bulk-source" class="form-label">Origen</label>
                        <select id="lead-bulk-source" class="form-select">
                            <option value="">Sin cambio</option>
                            <?php foreach ($leadSources as $source): ?>
                                <option value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="lead-bulk-assigned" class="form-label">Asignar a</label>
                        <select id="lead-bulk-assigned" class="form-select">
                            <option value="">Sin cambio</option>
                            <?php foreach ($assignableUsers as $user): ?>
                                <option value="<?= htmlspecialchars((string) ($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
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

<?php if ($canManageLeads): ?>
<div class="modal fade" id="lead-modal" tabindex="-1" aria-labelledby="lead-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lead-modal-label">Nuevo lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="lead-form-helper">Completa los campos y guarda.</p>
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
                            <?php foreach ($leadStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="lead-source" class="form-label">Origen</label>
                        <input list="lead-sources" class="form-control" id="lead-source" name="source" placeholder="Campaña, referido...">
                        <datalist id="lead-sources">
                            <?php foreach ($leadSources as $source): ?>
                                <option value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="mb-2">
                    <label for="lead-assigned" class="form-label">Asignado a</label>
                    <select class="form-select" id="lead-assigned" name="assigned_to">
                        <option value="">Sin asignar</option>
                        <?php foreach ($assignableUsers as $user): ?>
                            <option value="<?= htmlspecialchars((string) ($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
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
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="lead-detail-modal" tabindex="-1" aria-labelledby="lead-detail-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lead-detail-label">Detalle del lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="lead-detail-body">
                <div class="text-center text-muted py-4">Selecciona un lead para ver el detalle.</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lead-email-modal" tabindex="-1" aria-labelledby="lead-email-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lead-email-label">Enviar correo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="lead-email-form" class="space-y-2">
                    <div class="mb-2">
                        <label class="form-label" for="lead-email-to">Para</label>
                        <input type="email" class="form-control" id="lead-email-to" name="to" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="lead-email-subject">Asunto</label>
                        <input type="text" class="form-control" id="lead-email-subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="lead-email-body">Mensaje</label>
                        <textarea class="form-control" id="lead-email-body" name="body" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Enviar</button>
                </form>
            </div>
        </div>
    </div>
</div>
