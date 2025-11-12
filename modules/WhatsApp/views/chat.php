<?php
/** @var array $config */
/** @var bool $isIntegrationEnabled */

$brand = trim((string)($config['brand'] ?? 'MedForge')) ?: 'MedForge';
$phoneNumber = $config['phone_number_id'] ?? '';
?>
<section class="content">
    <div
            class="row"
            id="whatsapp-chat-root"
            data-enabled="<?= $isIntegrationEnabled ? '1' : '0'; ?>"
            data-endpoint-list="/whatsapp/api/conversations"
            data-endpoint-conversation="/whatsapp/api/conversations/{id}"
            data-endpoint-send="/whatsapp/api/messages"
            data-brand="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"
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
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" value="1" id="waPreview"
                                               name="preview_url">
                                        <label class="form-check-label" for="waPreview">Permitir vista previa de
                                            enlaces</label>
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
                                <div class="avatar avatar-lg status-success mx-0 d-flex align-items-center justify-content-center bg-primary-light text-primary">
                                    <i class="mdi mdi-whatsapp"></i>
                                </div>
                                <div class="d-lg-flex d-block justify-content-between align-items-center w-p100">
                                    <div class="media-body mb-lg-0 mb-20">
                                        <p class="fs-16" data-chat-title>Selecciona una conversación</p>
                                        <p class="fs-12 mb-0" data-chat-subtitle>El historial aparecerá cuando elijas un
                                            contacto.</p>
                                        <p class="fs-12 mb-0" data-chat-last-seen></p>
                                    </div>
                                    <div>
                                        <ul class="list-inline mb-0 fs-18">
                                            <li class="list-inline-item"><span
                                                        class="badge bg-primary-light text-primary d-none"
                                                        data-unread-indicator></span></li>
                                        </ul>
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
                                <div class="d-flex justify-content-between align-items-center mt-md-0 mt-30">
                                    <div class="form-check me-2 mb-0">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               id="chatPreview" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                        <label class="form-check-label" for="chatPreview">Link Preview</label>
                                    </div>
                                    <button type="submit"
                                            class="waves-effect waves-circle btn btn-circle btn-primary" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                        <i class="mdi mdi-send"></i>
                                    </button>
                                </div>
                            </form>
                            <div class="alert alert-danger d-none mt-3" role="alert" data-chat-error></div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-lg-5 col-12">
                    <div class="box h-100">
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
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
