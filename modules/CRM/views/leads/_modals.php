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
                                <option value="<?= htmlspecialchars((string)($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0" id="lead-bulk-helper">Selecciona al menos un lead para aplicar los
                    cambios.</p>
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
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="lead-first-name" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="lead-first-name" name="first_name"
                                       placeholder="Ej. Juan">
                            </div>
                            <div class="col-md-6">
                                <label for="lead-last-name" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="lead-last-name" name="last_name"
                                       placeholder="Ej. Pérez">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="lead-name" class="form-label">Nombre completo *</label>
                            <input type="text" class="form-control" id="lead-name" name="name" required>
                            <div class="form-text">Se completará automáticamente con el nombre y apellido si dejas este
                                campo vacío.
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="lead-hc-number" class="form-label">Historia clínica *</label>
                            <input type="text" class="form-control" id="lead-hc-number" name="hc_number" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="lead-email" class="form-label">Correo</label>
                                <input type="email" class="form-control" id="lead-email" name="email"
                                       placeholder="correo@ejemplo.com">
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
                                <input list="lead-sources" class="form-control" id="lead-source" name="source"
                                       placeholder="Campaña, referido...">
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
                                    <option value="<?= htmlspecialchars((string)($user['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($user['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content data">
            <div class="modal-header">
                <h4 class="modal-title" id="lead-detail-label">
                    <span id="lead-detail-title">#— - Lead</span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="lead-detail-body">
                <input type="hidden" id="lead-detail-id" value="">
                <div class="top-lead-menu">
                    <div class="horizontal-scrollable-tabs mb-3">
                        <div class="horizontal-tabs">
                            <ul class="nav nav-tabs nav-tabs-horizontal nav-tabs-segmented" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link active" href="#tab_lead_profile" aria-controls="tab_lead_profile"
                                       role="tab" data-bs-toggle="tab" aria-selected="true">
                                        <i class="fa-regular fa-user menu-icon"></i>
                                        Profile
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" href="#tab_proposals_leads" aria-controls="tab_proposals_leads"
                                       role="tab" data-bs-toggle="tab" aria-selected="false">
                                        <i class="fa-regular fa-file-lines menu-icon"></i>
                                        Proposals
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" href="#tab_tasks_leads" aria-controls="tab_tasks_leads"
                                       role="tab" data-bs-toggle="tab" aria-selected="false">
                                        <i class="fa-regular fa-circle-check menu-icon"></i>
                                        Tasks
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" href="#attachments" aria-controls="attachments" role="tab"
                                       data-bs-toggle="tab" aria-selected="false">
                                        <i class="fa-solid fa-paperclip menu-icon"></i>
                                        Attachments
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" href="#lead_reminders" aria-controls="lead_reminders" role="tab"
                                       data-bs-toggle="tab" aria-selected="false">
                                        <i class="fa-regular fa-bell menu-icon"></i>
                                        Reminders
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" href="#lead_notes" aria-controls="lead_notes" role="tab"
                                       data-bs-toggle="tab" aria-selected="false">
                                        <i class="fa-regular fa-note-sticky menu-icon"></i>
                                        Notes <span class="badge" id="lead-notes-count">0</span>
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" href="#lead_activity" aria-controls="lead_activity" role="tab"
                                       data-bs-toggle="tab" aria-selected="false">
                                        <i class="fa-solid fa-grip-lines-vertical menu-icon"></i>
                                        Activity Log
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade show active" id="tab_lead_profile">
                        <div class="lead-wrapper">
                            <div class="d-flex align-items-center justify-content-end gap-2 mb-2">
                                <div class="lead-edit d-none" id="lead-detail-edit-actions">
                                    <button type="button" class="btn btn-primary lead-top-btn" id="lead-detail-save">
                                        Save
                                    </button>
                                </div>
                                <button type="button" class="btn btn-primary lead-top-btn lead-view"
                                        id="lead-detail-convert">
                                    <i class="fa-regular fa-user"></i>
                                    Convert to customer
                                </button>
                                <button type="button" class="btn btn-default lead-top-btn lead-view"
                                        id="lead-detail-edit">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-default lead-top-btn lead-view"
                                        id="lead-detail-print">
                                    <i class="fa-solid fa-print me-2"></i>
                                </button>
                                <div class="btn-group lead-view" id="lead-more-btn">
                                    <button type="button" class="btn btn-default dropdown-toggle lead-top-btn"
                                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        More
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" id="lead-more-dropdown">
                                        <li><a href="#" class="dropdown-item">Mark as lost</a></li>
                                        <li><a href="#" class="dropdown-item">Mark as junk</a></li>
                                        <li><a href="#" class="dropdown-item text-danger">Delete lead</a></li>
                                    </ul>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="lead-view" id="lead-view-section">
                                <div class="row">
                                    <div class="col-md-4 col-xs-12 lead-information-col">
                                        <div class="lead-info-heading"><h4>Lead Information</h4></div>
                                        <dl>
                                            <dt class="text-muted">Name</dt>
                                            <dd class="fw-semibold" id="lead-view-name">—</dd>
                                            <dt class="text-muted">Position</dt>
                                            <dd id="lead-view-position">—</dd>
                                            <dt class="text-muted">Email</dt>
                                            <dd id="lead-view-email">—</dd>
                                            <dt class="text-muted">Website</dt>
                                            <dd id="lead-view-website">—</dd>
                                            <dt class="text-muted">Phone</dt>
                                            <dd id="lead-view-phone">—</dd>
                                            <dt class="text-muted">Lead value</dt>
                                            <dd id="lead-view-value">—</dd>
                                            <dt class="text-muted">Company</dt>
                                            <dd id="lead-view-company">—</dd>
                                            <dt class="text-muted">Address</dt>
                                            <dd id="lead-view-address">—</dd>
                                            <dt class="text-muted">City</dt>
                                            <dd id="lead-view-city">—</dd>
                                            <dt class="text-muted">State</dt>
                                            <dd id="lead-view-state">—</dd>
                                            <dt class="text-muted">Country</dt>
                                            <dd id="lead-view-country">—</dd>
                                            <dt class="text-muted">Zip Code</dt>
                                            <dd id="lead-view-zip">—</dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-4 col-xs-12 lead-information-col">
                                        <div class="lead-info-heading"><h4>General Information</h4></div>
                                        <dl>
                                            <dt class="text-muted">Status</dt>
                                            <dd id="lead-view-status" class="mb-2">—</dd>
                                            <dt class="text-muted">Source</dt>
                                            <dd id="lead-view-source">—</dd>
                                            <dt class="text-muted">Default language</dt>
                                            <dd id="lead-view-language">—</dd>
                                            <dt class="text-muted">Assigned</dt>
                                            <dd id="lead-view-assigned">—</dd>
                                            <dt class="text-muted">Tags</dt>
                                            <dd id="lead-view-tags">—</dd>
                                            <dt class="text-muted">Created</dt>
                                            <dd id="lead-view-created">—</dd>
                                            <dt class="text-muted">Last Contact</dt>
                                            <dd id="lead-view-last-contact">—</dd>
                                            <dt class="text-muted">Public</dt>
                                            <dd id="lead-view-public">—</dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-4 col-xs-12 lead-information-col">
                                        <div class="lead-info-heading"><h4>Extra</h4></div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="col-md-12">
                                        <dl>
                                            <dt class="text-muted">Description</dt>
                                            <dd id="lead-view-description">—</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <div class="lead-edit d-none" id="lead-edit-section">
                                <form id="lead-detail-edit-form" class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead-detail-status" class="control-label">Status</label>
                                            <select id="lead-detail-status" name="status" class="form-select">
                                                <option value="">Seleccionar</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead-detail-source" class="control-label">Source</label>
                                            <input type="text" class="form-control" id="lead-detail-source"
                                                   name="source">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="lead-detail-assigned" class="control-label">Assigned</label>
                                            <select id="lead-detail-assigned" name="assigned_to" class="form-select">
                                                <option value="">Sin asignar</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lead-detail-name" class="control-label">Name</label>
                                            <input type="text" class="form-control" id="lead-detail-name" name="name">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-email" class="control-label">Email</label>
                                            <input type="email" class="form-control" id="lead-detail-email"
                                                   name="email">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-phone" class="control-label">Phone</label>
                                            <input type="text" class="form-control" id="lead-detail-phone" name="phone">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-company" class="control-label">Company</label>
                                            <input type="text" class="form-control" id="lead-detail-company"
                                                   name="company">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="lead-detail-address" class="control-label">Address</label>
                                            <textarea id="lead-detail-address" name="address" class="form-control"
                                                      rows="2"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-city" class="control-label">City</label>
                                            <input type="text" class="form-control" id="lead-detail-city" name="city">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-state" class="control-label">State</label>
                                            <input type="text" class="form-control" id="lead-detail-state" name="state">
                                        </div>
                                        <div class="form-group">
                                            <label for="lead-detail-zip" class="control-label">Zip</label>
                                            <input type="text" class="form-control" id="lead-detail-zip" name="zip">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="lead-detail-description"
                                                   class="control-label">Description</label>
                                            <textarea id="lead-detail-description" name="description"
                                                      class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="lead-latest-activity mb-3 lead-view">
                                <div class="lead-info-heading"><h4>Latest Activity</h4></div>
                                <div id="lead-latest-activity">En desarrollo</div>
                            </div>
                            <div class="lead-edit d-none" id="lead-edit-footer">
                                <hr>
                                <button type="button" class="btn btn-primary pull-right" id="lead-detail-save-footer">
                                    Save
                                </button>
                                <button type="button" class="btn btn-default pull-right me-2" id="lead-detail-cancel">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="tab_proposals_leads">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="tab_tasks_leads">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="attachments">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="lead_reminders">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="lead_notes">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="lead_activity">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lead-convert-modal" tabindex="-1" aria-labelledby="lead-convert-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content data">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h4 class="modal-title" id="lead-convert-label">Convertir lead a cliente</h4>
                </div>
            </div>
            <div class="modal-body">
                <div class="horizontal-scrollable-tabs mb-3">
                    <div class="horizontal-tabs">
                        <ul class="nav nav-tabs nav-tabs-horizontal nav-tabs-segmented" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" href="#tab_convert_profile"
                                   aria-controls="tab_convert_profile" role="tab" data-bs-toggle="tab"
                                   aria-selected="true">Datos</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" href="#tab_convert_summary" aria-controls="tab_convert_summary"
                                   role="tab" data-bs-toggle="tab" aria-selected="false">Resumen</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade show active" id="tab_convert_profile">
                        <form id="lead-convert-form" class="space-y-2">
                            <input type="hidden" id="convert-lead-hc" name="hc_number" value="">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="text-muted small">Lead seleccionado:</div>
                                <span id="convert-lead-selected" class="badge bg-secondary">Sin selección</span>
                            </div>
                            <div class="alert alert-info mb-3" id="convert-helper">Selecciona un lead en la tabla para
                                precargar los datos.
                            </div>
                            <div class="mb-2">
                                <label for="convert-name" class="form-label">Nombre completo</label>
                                <input type="text" class="form-control" id="convert-name" name="customer_name"
                                       placeholder="Nombre del paciente">
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
                                    <input type="text" class="form-control" id="convert-document"
                                           name="customer_document">
                                </div>
                                <div class="col-md-6">
                                    <label for="convert-external" class="form-label">Referencia externa</label>
                                    <input type="text" class="form-control" id="convert-external"
                                           name="customer_external_ref">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="convert-affiliation" class="form-label">Afiliación</label>
                                <input type="text" class="form-control" id="convert-affiliation"
                                       name="customer_affiliation">
                            </div>
                            <div class="mb-3">
                                <label for="convert-address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" id="convert-address" name="customer_address">
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success" disabled>Convertir lead</button>
                            </div>
                        </form>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="tab_convert_summary">
                        <p class="text-muted">En desarrollo</p>
                    </div>
                </div>
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
