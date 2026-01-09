<div class="tab-pane fade" id="crm-tab-tickets" role="tabpanel" aria-labelledby="crm-tab-tickets-link">
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="table-responsive rounded card-table shadow-sm">
                <table class="table table-striped table-sm align-middle" id="crm-tickets-table">
                    <thead class="bg-info text-white">
                        <tr>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Reporta</th>
                            <th>Asignado</th>
                            <th>Relacionado</th>
                            <th>Actualizado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="col-xl-5">
            <?php if ($permissions['manageTickets']): ?>
                <div class="box mb-3">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Nuevo ticket interno</h5>
                    </div>
                    <div class="box-body">
                        <form id="ticket-form" class="space-y-2">
                        <div class="mb-2">
                            <label for="ticket-subject" class="form-label">Asunto *</label>
                            <input type="text" class="form-control" id="ticket-subject" name="subject" required>
                        </div>
                        <div class="mb-2">
                            <label for="ticket-message" class="form-label">Detalle *</label>
                            <textarea class="form-control" id="ticket-message" name="message" rows="3" required></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="ticket-priority" class="form-label">Prioridad</label>
                                <select class="form-select" id="ticket-priority" name="priority">
                                    <?php foreach (($ticketPriorities ?? []) as $priority): ?>
                                        <option value="<?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords($priority), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ticket-status" class="form-label">Estado</label>
                                <select class="form-select" id="ticket-status" name="status">
                                    <?php foreach (($ticketStatuses ?? []) as $status): ?>
                                        <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="ticket-assigned" class="form-label">Asignado a</label>
                            <select class="form-select" id="ticket-assigned" name="assigned_to">
                                <option value="">Sin asignar</option>
                                <?php include __DIR__ . '/user_options.php'; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="ticket-lead" class="form-label">Lead</label>
                                <select class="form-select" id="ticket-lead" name="related_lead_id" data-placeholder="Ninguno">
                                    <option value="">Ninguno</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ticket-project" class="form-label">Proyecto</label>
                                <select class="form-select" id="ticket-project" name="related_project_id" data-placeholder="Ninguno">
                                    <option value="">Ninguno</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-info w-100 text-white">Crear ticket</button>
                        </form>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Responder ticket</h5>
                        <span class="badge bg-light text-muted" id="ticket-reply-selected">Sin selección</span>
                    </div>
                    <div class="box-body">
                        <form id="ticket-reply-form" class="space-y-2">
                        <input type="hidden" id="ticket-reply-id" name="ticket_id" value="">
                        <div class="alert alert-info mb-3" id="ticket-reply-helper">Selecciona un ticket en la tabla para responder.</div>
                        <div class="mb-2">
                            <label for="ticket-reply-message" class="form-label">Mensaje *</label>
                            <textarea class="form-control" id="ticket-reply-message" name="message" rows="3" required disabled></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="ticket-reply-status" class="form-label">Actualizar estado</label>
                            <select class="form-select" id="ticket-reply-status" name="status" disabled>
                                <?php foreach (($ticketStatuses ?? []) as $status): ?>
                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info w-100 text-white" disabled>Enviar respuesta</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Tu rol no permite crear ni responder tickets en el CRM.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
