<?php
/** @var array $config */
/** @var bool $isIntegrationEnabled */

$brand = trim((string)($config['brand'] ?? 'MedForge')) ?: 'MedForge';
$currentUser = $currentUser ?? null;
$currentUserId = is_array($currentUser) ? (int)($currentUser['id'] ?? 0) : 0;
$currentRoleId = is_array($currentUser) ? (int)($currentUser['role_id'] ?? 0) : 0;
$canAssign = !empty($canAssign);
$realtime = $realtime ?? null;
$phoneNumber = $config['phone_number_id'] ?? '';
?>
<style>
    .whatsapp-patient-results {
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.06);
    }
    .whatsapp-patient-results .list-group-item {
        border: 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        padding: 10px 12px;
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .whatsapp-patient-results .list-group-item:last-child {
        border-bottom: 0;
    }
    .whatsapp-patient-results .list-group-item:hover {
        background-color: #f6f9ff;
        box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.1);
    }
    .whatsapp-patient-results .patient-name {
        font-weight: 600;
        color: #1f2a44;
    }
    .whatsapp-patient-results .patient-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }
    .whatsapp-patient-results .badge {
        font-weight: 600;
        letter-spacing: 0.2px;
    }
    .whatsapp-patient-results .badge-soft {
        background-color: #eef2ff;
        color: #2b3a67;
        border: 1px solid rgba(43, 58, 103, 0.15);
    }
    .whatsapp-patient-results .badge-soft-success {
        background-color: #e8f7ef;
        color: #1b7f4f;
        border: 1px solid rgba(27, 127, 79, 0.15);
    }
    .whatsapp-patient-results .badge-soft-muted {
        background-color: #f3f4f6;
        color: #6b7280;
        border: 1px solid rgba(107, 114, 128, 0.2);
    }
    .whatsapp-chat-bubble {
        max-width: 72%;
        border-radius: 14px;
    }
    .whatsapp-chat-bubble .card-body {
        padding: 0.7rem 0.9rem 0.6rem;
    }
    .whatsapp-chat-bubble .chat-text-start p {
        font-size: 0.92rem;
        line-height: 1.35;
    }
    .whatsapp-chat-media {
        margin-top: 0.5rem;
    }
    .whatsapp-chat-media img {
        max-width: 100%;
        border-radius: 10px;
        display: block;
    }
    .whatsapp-chat-doc {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 0.6rem;
        background: rgba(15, 23, 42, 0.04);
        border-radius: 10px;
    }
    .whatsapp-chat-doc .doc-name {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1f2937;
    }
    .whatsapp-chat-doc .doc-meta {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .whatsapp-chat-meta span + span::before {
        content: '•';
        margin: 0 6px;
        color: #94a3b8;
    }
    .whatsapp-chat-meta .meta-warning {
        color: #b45309;
        font-weight: 600;
    }
    .whatsapp-chat-meta .meta-active {
        color: #0f766e;
        font-weight: 600;
    }
    .whatsapp-status {
        margin-left: 6px;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .whatsapp-status.read {
        color: #0d6efd;
    }
    .whatsapp-status.failed {
        color: #dc3545;
    }
    .whatsapp-typing {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        border-radius: 16px;
        background: #f1f5f9;
        color: #64748b;
        font-size: 0.85rem;
        margin-bottom: 12px;
    }
    .whatsapp-typing .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #94a3b8;
        animation: whatsappTyping 1.2s infinite;
    }
    .whatsapp-typing .dot:nth-child(2) { animation-delay: 0.2s; }
    .whatsapp-typing .dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes whatsappTyping {
        0%, 100% { opacity: 0.3; transform: translateY(0); }
        50% { opacity: 1; transform: translateY(-2px); }
    }
    .whatsapp-attachment-preview {
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: #f8fafc;
        padding: 8px 10px;
        border-radius: 10px;
        cursor: pointer;
    }
    .whatsapp-attachment-thumb {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .wa-inline-toast-stack {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        z-index: 1080;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        width: min(320px, calc(100vw - 2rem));
        pointer-events: none;
    }
    .wa-inline-toast {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        background: #111827;
        color: #f9fafb;
        border-radius: 10px;
        padding: 0.5rem 0.6rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.32);
        font-size: 0.82rem;
        line-height: 1.25;
        pointer-events: auto;
    }
    .wa-inline-toast i {
        color: #22c55e;
        font-size: 1rem;
        margin-top: 1px;
    }
    .wa-inline-toast .wa-inline-toast__text {
        flex: 1;
        min-width: 0;
    }
    .wa-inline-toast .btn-close {
        filter: invert(1);
        opacity: 0.7;
        transform: scale(0.82);
    }
</style>
<section class="content">
    <div
            class="row"
            id="whatsapp-chat-root"
            data-enabled="<?= $isIntegrationEnabled ? '1' : '0'; ?>"
            data-endpoint-list="/whatsapp/api/conversations"
            data-endpoint-conversation="/whatsapp/api/conversations/{id}"
            data-endpoint-send="/whatsapp/api/messages"
            data-endpoint-media="/whatsapp/api/media/{id}"
            data-endpoint-patients="/whatsapp/api/patients"
            data-endpoint-templates="/whatsapp/api/chat-templates"
            data-endpoint-agents="/whatsapp/api/agents"
            data-endpoint-agent-presence="/whatsapp/api/agent-presence"
            data-endpoint-assign="/whatsapp/api/conversations/{id}/assign"
            data-endpoint-transfer="/whatsapp/api/conversations/{id}/transfer"
            data-endpoint-close="/whatsapp/api/conversations/{id}/close"
            data-endpoint-delete="/whatsapp/api/conversations/{id}/delete"
            data-brand="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"
            data-current-user-id="<?= $currentUserId; ?>"
            data-current-role-id="<?= $currentRoleId; ?>"
            data-can-assign="<?= $canAssign ? '1' : '0'; ?>"
    >
        <div class="col-lg-3 col-12">
            <div class="box">
                <div class="box-header">
                    <ul class="nav nav-tabs customtab nav-justified" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#messages" role="tab">Chat</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#contacts" role="tab">Nuevo</a>
                        </li>
                    </ul>
                </div>
                <div class="box-body">
                    <div class="mb-3">
                        <label class="form-label mb-1" for="agentPresenceSelect">Mi estado</label>
                        <select class="form-select form-select-sm" id="agentPresenceSelect" data-agent-presence>
                            <option value="available">Disponible</option>
                            <option value="away">Ausente</option>
                            <option value="offline">Desconectado</option>
                        </select>
                        <div class="small text-muted mt-1" data-agent-presence-feedback></div>
                    </div>
                    <!-- Tab panes -->
                    <div class="tab-content">
                        <div class="tab-pane active" id="messages" role="tabpanel">
                            <div class="chat-box-one-side3">
                                <div class="mb-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                        <input type="search" class="form-control" placeholder="Buscar conversación"
                                               autocomplete="off" data-conversation-search>
                                    </div>
                                </div>
                                <div class="media-list media-list-hover" data-conversation-list>
                                    <div class="media flex-column align-items-center py-5 text-center text-muted"
                                         data-empty-state>
                                        <i class="mdi mdi-forum text-primary" style="font-size: 2.5rem;"></i>
                                        <p class="mt-2 mb-1">Aún no hay conversaciones registradas.</p>
                                        <p class="mb-0 small">Los mensajes recibidos aparecerán automáticamente en
                                            esta lista.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="contacts" role="tabpanel">
                            <div class="chat-box-one-side3">
                                <form class="p-10" data-new-conversation-form>
                                    <h5 class="mb-3">Iniciar conversación</h5>
                                    <div class="mb-2">
                                        <label for="patientSearch" class="form-label">Buscar paciente</label>
                                        <input type="search" class="form-control form-control-sm" id="patientSearch"
                                               placeholder="Nombre, historia clínica o teléfono" autocomplete="off"
                                               data-patient-search>
                                        <div class="list-group mt-2 d-none whatsapp-patient-results" data-patient-results></div>
                                    </div>
                                    <div class="mb-2">
                                        <label for="waNumber" class="form-label">Número de WhatsApp</label>
                                        <input type="text" class="form-control form-control-sm" id="waNumber"
                                               name="wa_number" placeholder="+593..." required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="waName" class="form-label">Nombre (opcional)</label>
                                        <input type="text" class="form-control form-control-sm" id="waName"
                                               name="display_name" placeholder="Paciente o contacto">
                                    </div>
                                    <div class="mb-3">
                                        <label for="waMessage" class="form-label">Mensaje inicial</label>
                                        <textarea class="form-control form-control-sm" id="waMessage" name="message"
                                                  rows="4" placeholder="Escribe el primer mensaje" required></textarea>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="waUseTemplate"
                                               data-template-toggle>
                                        <label class="form-check-label" for="waUseTemplate">Enviar plantilla oficial</label>
                                    </div>
                                    <div class="d-none" data-template-panel>
                                        <div class="mb-2">
                                            <label for="waTemplate" class="form-label">Plantilla</label>
                                            <select class="form-select form-select-sm" id="waTemplate" data-template-select>
                                                <option value="">Selecciona una plantilla</option>
                                            </select>
                                        </div>
                                        <div class="mb-3" data-template-fields></div>
                                        <div class="mb-3">
                                            <label class="form-label">Vista previa</label>
                                            <div class="border rounded p-2 bg-light small" data-template-preview style="white-space: pre-line;">
                                                Selecciona una plantilla para ver la vista previa.
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit"
                                            class="btn btn-primary btn-sm w-100" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                        <i class="mdi mdi-send"></i> Enviar mensaje
                                    </button>
                                    <div class="small mt-2" data-new-conversation-feedback></div>
                                    <?php if (!$isIntegrationEnabled): ?>
                                        <div class="alert alert-warning mt-3 mb-0" role="alert">
                                            Debes habilitar la integración de WhatsApp Cloud API para enviar mensajes
                                            manualmente.
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-9 col-12">
            <div class="row">
                <div class="col-xxxl-8 col-lg-7 col-12">
                    <div class="box">
                        <div class="box-header">
                            <div class="media align-items-top p-0" data-chat-header>
                                <div class="avatar avatar-lg status-success mx-0 d-flex align-items-center justify-content-center bg-primary-light text-primary"
                                     data-chat-avatar>
                                    <img class="d-none w-p100 h-p100 rounded-circle" alt="Avatar" data-chat-avatar-img>
                                    <span class="fw-600" data-chat-avatar-initials>WA</span>
                                </div>
                                <div class="d-lg-flex d-block justify-content-between align-items-center w-p100">
                                    <div class="media-body mb-lg-0 mb-20">
                                        <p class="fs-16" data-chat-title>Selecciona una conversación</p>
                                        <p class="fs-12 mb-0" data-chat-subtitle>El historial aparecerá cuando elijas un
                                            contacto.</p>
                                        <div class="mt-1 d-flex flex-wrap gap-2">
                                            <span class="badge bg-warning-light text-warning d-none" data-chat-needs-human>Requiere agente</span>
                                            <span class="badge bg-success-light text-success d-none" data-chat-assigned></span>
                                            <span class="badge bg-info-light text-info d-none" data-chat-first-contact>Contacto inicial</span>
                                        </div>
                                        <div class="fs-12 text-muted d-flex flex-wrap gap-2 align-items-center whatsapp-chat-meta" data-chat-meta>
                                            <span data-chat-last-seen></span>
                                            <span class="d-none" data-chat-assigned-compact></span>
                                            <span class="d-none" data-chat-team></span>
                                            <span class="d-none" data-chat-bot-status></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <ul class="list-inline mb-0 fs-18">
                                            <li class="list-inline-item"><span
                                                        class="badge bg-primary-light text-primary d-none"
                                                        data-unread-indicator></span></li>
                                        </ul>
                                        <div class="btn-group" role="group" data-chat-actions>
                                            <a class="btn btn-outline-success disabled" href="#" target="_blank" rel="noopener" data-action-open-chat>
                                                <i class="mdi mdi-whatsapp"></i>
                                            </a>
                                            <button class="btn btn-outline-secondary" type="button" data-action-copy-number disabled>
                                                <i class="mdi mdi-content-copy"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" type="button" data-action-close-conversation disabled title="Cerrar conversación">
                                                <i class="mdi mdi-check-circle-outline"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" type="button" data-action-delete-conversation disabled title="Eliminar conversación">
                                                <i class="mdi mdi-delete-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="chat-box-one2" data-chat-messages style="max-height: 520px; overflow-y: auto;">
                                <div class="text-center text-muted py-5" data-chat-empty>
                                    <i class="mdi mdi-whatsapp" style="font-size: 3rem;"></i>
                                    <p class="mt-2 mb-1">Selecciona un contacto para ver el historial y continuar la
                                        conversación.</p>
                                    <?php if (!$isIntegrationEnabled): ?>
                                        <p class="mb-0 small">Aunque la integración no esté activa, puedes revisar los
                                            mensajes
                                            recibidos.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer no-border" data-chat-composer>
                            <form class="d-md-flex d-block justify-content-between align-items-center bg-white p-5 rounded10 b-1 overflow-hidden"
                                  data-message-form>
                        <textarea class="form-control b-0 py-10" id="chatMessage" rows="2"
                                  placeholder="Escribe algo..." <?= $isIntegrationEnabled ? '' : 'disabled'; ?> required></textarea>
                                <div class="d-flex flex-wrap justify-content-between align-items-center mt-md-0 mt-30 gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-attachment-trigger <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                            <i class="mdi mdi-paperclip"></i>
                                        </button>
                                        <input type="file" class="d-none" data-attachment-input
                                               accept="image/*,audio/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                        <div class="small text-muted d-none whatsapp-attachment-preview" data-attachment-preview></div>
                                    </div>
                                    <button type="submit"
                                            class="btn btn-primary" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                        Enviar
                                    </button>
                                </div>
                            </form>
                            <div class="alert alert-danger d-none mt-3" role="alert" data-chat-error></div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-lg-5 col-12">
                    <div class="box">
                        <div class="box-header no-border">
                            <h4 class="box-title">Resumen de la integración</h4>
                        </div>
                        <div class="box-body pt-0">
                            <div class="mb-3">
                                <div class="fw-600 text-muted small">Marca conectada</div>
                                <div class="fw-600"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if ($phoneNumber !== ''): ?>
                                    <div class="text-muted small">Número
                                        emisor: <?= htmlspecialchars($phoneNumber, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <span class="badge <?= $isIntegrationEnabled ? 'bg-success-light text-success' : 'bg-warning-light text-warning'; ?>">
                                <?= $isIntegrationEnabled ? 'Integración activa' : 'Integración pendiente'; ?>
                                </span>
                            </div>
                            <?php if (!$isIntegrationEnabled): ?>
                                <div class="alert alert-warning" role="alert">
                                Configura tu cuenta en <a href="/settings?section=whatsapp" class="alert-link">Ajustes
                                        &rarr; WhatsApp</a> para habilitar el envío manual.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="box-body pt-0" data-conversation-meta>
                            <h5 class="mb-3">Detalles del contacto</h5>
                            <div class="alert alert-warning d-none mb-3" role="alert" data-chat-template-warning>
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        Este contacto no ha iniciado conversación. WhatsApp Cloud API no entregará mensajes libres.
                                        Envía una plantilla aprobada para abrir la ventana de 24h.
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-action-open-template>
                                        Enviar plantilla
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="mdi mdi-account text-muted"></i>
                                </div>
                                <div>
                                    <div class="fw-600" data-detail-name>Selecciona una conversación</div>
                                    <div class="text-muted" data-detail-number>El número aparecerá aquí.</div>
                                </div>
                            </div>
                            <dl class="row mb-0">
                                <dt class="col-6 text-muted">Paciente</dt>
                                <dd class="col-6" data-detail-patient>—</dd>
                                <dt class="col-6 text-muted">Historia clínica</dt>
                                <dd class="col-6" data-detail-hc>—</dd>
                                <dt class="col-6 text-muted">Último mensaje</dt>
                                <dd class="col-6" data-detail-last>—</dd>
                                <dt class="col-6 text-muted">Mensajes sin leer</dt>
                                <dd class="col-6" data-detail-unread>—</dd>
                                <dt class="col-6 text-muted">Estado</dt>
                                <dd class="col-6" data-detail-handoff>—</dd>
                                <dt class="col-6 text-muted">Notas</dt>
                                <dd class="col-6" data-detail-notes>—</dd>
                            </dl>

                            <div class="border-top pt-3 mt-3" data-handoff-panel>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Asignación</h6>
                                    <span class="badge bg-secondary-light text-secondary" data-handoff-badge>Sin asignar</span>
                                </div>
                                <div class="small text-muted mb-2" data-handoff-queue>Equipo: —</div>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-primary d-none" data-action="take-conversation">
                                        <i class="mdi mdi-account-check-outline me-1"></i>Tomar chat
                                    </button>
                                </div>
                                <div>
                                    <label class="form-label small text-muted">Derivar a agente</label>
                                    <div class="d-flex gap-2">
                                        <select class="form-select form-select-sm" data-transfer-agent></select>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="transfer-conversation">Derivar</button>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2" placeholder="Nota para el agente (opcional)" data-transfer-note>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php if (is_array($realtime)): ?>
    <script>
        window.MEDF_PusherConfig = <?= json_encode($realtime, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>;
    </script>
<?php endif; ?>
