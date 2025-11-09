<?php
/** @var array $config */
/** @var bool $isIntegrationEnabled */

$brand = trim((string) ($config['brand'] ?? 'MedForge')) ?: 'MedForge';
$phoneNumber = $config['phone_number_id'] ?? '';
?>
<div class="content-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h3 class="page-title">Chat de WhatsApp</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item">WhatsApp</li>
                <li class="breadcrumb-item active" aria-current="page">Chat</li>
            </ol>
        </div>
        <div class="text-end">
            <div class="fw-600 text-muted small">Cuenta vinculada</div>
            <div class="fw-600"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if ($phoneNumber !== ''): ?>
                <div class="text-muted small">Número emisor: <?= htmlspecialchars($phoneNumber, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div
                class="box"
                id="whatsapp-chat-root"
                data-enabled="<?= $isIntegrationEnabled ? '1' : '0'; ?>"
                data-endpoint-list="/whatsapp/api/conversations"
                data-endpoint-conversation="/whatsapp/api/conversations/{id}"
                data-endpoint-send="/whatsapp/api/messages"
                data-brand="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>"
            >
                <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="box-title mb-0">Mensajería humana en vivo</h4>
                        <p class="text-muted mb-0">Consulta conversaciones sincronizadas y envía mensajes manuales reutilizando la integración de WhatsApp Cloud API.</p>
                    </div>
                    <div>
                        <span class="badge <?= $isIntegrationEnabled ? 'bg-success-light text-success' : 'bg-warning-light text-warning'; ?> fw-600">
                            <?= $isIntegrationEnabled ? 'Integración activa' : 'Integración pendiente'; ?>
                        </span>
                    </div>
                </div>
                <div class="box-body">
                    <?php if (!$isIntegrationEnabled): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <h5 class="mb-2"><i class="mdi mdi-alert-outline"></i> Configura WhatsApp Cloud API antes de comenzar</h5>
                            <p class="mb-2">Necesitas habilitar la integración en <a href="/settings?section=whatsapp" class="alert-link">Ajustes → WhatsApp</a> para enviar mensajes desde este panel.</p>
                            <p class="mb-0 small">Aun sin la integración, puedes revisar el historial guardado por el webhook.</p>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="card shadow-none border rounded h-100">
                                <div class="card-body p-3 d-flex flex-column">
                                    <form class="mb-3" data-new-conversation-form>
                                        <h5 class="card-title">Nueva conversación</h5>
                                        <div class="mb-2">
                                            <label for="waNumber" class="form-label">Número de WhatsApp</label>
                                            <input type="text" class="form-control form-control-sm" id="waNumber" name="wa_number" placeholder="+593..." required>
                                        </div>
                                        <div class="mb-2">
                                            <label for="waName" class="form-label">Nombre (opcional)</label>
                                            <input type="text" class="form-control form-control-sm" id="waName" name="display_name" placeholder="Paciente o contacto">
                                        </div>
                                        <div class="mb-2">
                                            <label for="waMessage" class="form-label">Mensaje inicial</label>
                                            <textarea class="form-control form-control-sm" id="waMessage" name="message" rows="3" placeholder="Escribe el primer mensaje" required></textarea>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" value="1" id="waPreview" name="preview_url">
                                            <label class="form-check-label" for="waPreview">Permitir vista previa de enlaces</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm w-100" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                            <i class="mdi mdi-send"></i> Enviar mensaje
                                        </button>
                                        <div class="small text-muted mt-2" data-new-conversation-feedback></div>
                                    </form>

                                    <div class="mb-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                            <input type="search" class="form-control" placeholder="Buscar conversación" autocomplete="off" data-conversation-search>
                                        </div>
                                    </div>

                                    <div class="flex-grow-1 overflow-auto" style="max-height: 480px;" data-conversation-list>
                                        <div class="text-center text-muted py-5 small" data-empty-state>
                                            <i class="mdi mdi-forum text-primary" style="font-size: 2.5rem;"></i>
                                            <p class="mt-2 mb-0">Aún no hay conversaciones registradas.</p>
                                            <p class="mb-0">Los mensajes que reciba el webhook se mostrarán aquí automáticamente.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="card shadow-none border rounded h-100">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center" data-chat-header>
                                    <div>
                                        <h5 class="card-title mb-0">Selecciona una conversación</h5>
                                        <small class="text-muted" data-chat-subtitle>El historial aparecerá a la derecha.</small>
                                    </div>
                                    <span class="badge bg-primary-light text-primary d-none" data-unread-indicator></span>
                                </div>
                                <div class="card-body p-0 d-flex flex-column" style="min-height: 540px;">
                                    <div class="flex-grow-1 overflow-auto p-3" data-chat-messages>
                                        <div class="text-center text-muted py-5" data-chat-empty>
                                            <i class="mdi mdi-whatsapp" style="font-size: 3rem;"></i>
                                            <p class="mt-2 mb-0">Selecciona un contacto para ver el historial y continuar la conversación.</p>
                                        </div>
                                    </div>
                                    <div class="border-top p-3" data-chat-composer>
                                        <form class="d-flex flex-column gap-2" data-message-form>
                                            <div class="form-floating">
                                                <textarea class="form-control" placeholder="Escribe un mensaje" id="chatMessage" style="height: 120px;" required <?= $isIntegrationEnabled ? '' : 'disabled'; ?>></textarea>
                                                <label for="chatMessage">Mensaje</label>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1" id="chatPreview" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                                    <label class="form-check-label" for="chatPreview">Permitir vista previa de enlaces</label>
                                                </div>
                                                <button type="submit" class="btn btn-primary" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                                    <i class="mdi mdi-send"></i> Enviar
                                                </button>
                                            </div>
                                            <div class="alert alert-danger d-none" role="alert" data-chat-error></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
