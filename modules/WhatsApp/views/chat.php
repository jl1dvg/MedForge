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

$whatsappGuideBasePath = defined('BASE_PATH')
    ? rtrim((string)BASE_PATH, '/\\')
    : dirname(__DIR__, 3);
$whatsappGuideDir = $whatsappGuideBasePath . '/docs/whatsapp-chat-guide';
$whatsappChatGuide = [
    'flow.daily' => [
        'title' => 'Flujo diario recomendado',
        'description' => 'Orden sugerido para operar chats sin perder casos',
        'file' => 'flow-daily-operation.md',
        'markdown' => "### Orden recomendado\n1) Definir tu estado.\n2) Elegir filtro de cola.\n3) Abrir chat y revisar contexto.\n4) Responder o enviar plantilla.\n5) Tomar o derivar si requiere agente.\n6) Cerrar cuando quede atendido.",
    ],
    'panel.my-status' => [
        'title' => 'Mi estado',
        'description' => 'Disponible, ausente o desconectado',
        'file' => 'panel-my-status.md',
        'markdown' => "### Que hace?\nActualiza tu disponibilidad para asignacion de conversaciones.",
    ],
    'panel.search' => [
        'title' => 'Buscar conversacion',
        'description' => 'Filtrar por nombre, HC o numero',
        'file' => 'panel-search-conversation.md',
        'markdown' => "### Que hace?\nFiltra la lista para encontrar un contacto rapido.",
    ],
    'panel.filters' => [
        'title' => 'Filtros de cola',
        'description' => 'Priorizar casos por tipo de atencion',
        'file' => 'panel-filters.md',
        'markdown' => "### Que hace?\nSepara chats en colas operativas: mis activas, ventana 24h, plantilla, handoff y todas.",
    ],
    'panel.conversation-list' => [
        'title' => 'Lista de conversaciones',
        'description' => 'Abrir y trabajar un chat',
        'file' => 'panel-conversation-list.md',
        'markdown' => "### Que hace?\nAl seleccionar una fila se abre historial, acciones y estado del contacto.",
    ],
    'chat.open-chat' => [
        'title' => 'Abrir en WhatsApp',
        'description' => 'Abrir chat externo en wa.me',
        'file' => 'chat-open-whatsapp.md',
        'markdown' => "### Que hace?\nAbre el numero en WhatsApp Web para apoyo externo al panel.",
    ],
    'chat.copy-number' => [
        'title' => 'Copiar numero',
        'description' => 'Copiar numero del contacto',
        'file' => 'chat-copy-number.md',
        'markdown' => "### Que hace?\nCopia el numero para compartirlo con otra area o validar datos.",
    ],
    'chat.close-conversation' => [
        'title' => 'Cerrar conversacion',
        'description' => 'Marcar como atendida',
        'file' => 'chat-close-conversation.md',
        'markdown' => "### Que hace?\nCierra el caso, limpia asignacion y lo deja fuera de atencion activa.",
    ],
    'chat.delete-conversation' => [
        'title' => 'Eliminar conversacion',
        'description' => 'Eliminar historial del chat',
        'file' => 'chat-delete-conversation.md',
        'markdown' => "### Que hace?\nElimina la conversacion y su historial. Es una accion irreversible.",
    ],
    'chat.message-input' => [
        'title' => 'Caja de mensaje',
        'description' => 'Escribir respuesta al paciente',
        'file' => 'chat-message-input.md',
        'markdown' => "### Que hace?\nPermite redactar la respuesta. Enter envia y Shift+Enter agrega salto de linea.",
    ],
    'chat.attachment' => [
        'title' => 'Adjuntar archivo',
        'description' => 'Agregar imagen, audio o documento',
        'file' => 'chat-attachment.md',
        'markdown' => "### Que hace?\nAdjunta evidencia o documento al mensaje actual antes de enviar.",
    ],
    'chat.send-message' => [
        'title' => 'Enviar respuesta',
        'description' => 'Enviar mensaje en chat abierto',
        'file' => 'chat-send-message.md',
        'markdown' => "### Que hace?\nEnvia texto y/o adjunto a la conversacion seleccionada.",
    ],
    'warning.open-template' => [
        'title' => 'Enviar plantilla (fuera de 24h)',
        'description' => 'Reabrir conversacion con plantilla aprobada',
        'file' => 'warning-open-template.md',
        'markdown' => "### Que hace?\nCuando la ventana 24h esta cerrada, abre la pestaña Nuevo para enviar plantilla oficial.",
    ],
    'handoff.take-chat' => [
        'title' => 'Tomar chat',
        'description' => 'Asignarte una conversacion pendiente',
        'file' => 'handoff-take-chat.md',
        'markdown' => "### Que hace?\nTe asigna el chat para que puedas responder y gestionarlo.",
    ],
    'handoff.transfer' => [
        'title' => 'Derivar chat',
        'description' => 'Transferir a otro agente con nota',
        'file' => 'handoff-transfer-chat.md',
        'markdown' => "### Que hace?\nReasigna la conversacion al agente destino y registra nota operativa.",
    ],
    'new.patient-search' => [
        'title' => 'Buscar paciente',
        'description' => 'Autocompletar datos para nuevo chat',
        'file' => 'new-patient-search.md',
        'markdown' => "### Que hace?\nBusca paciente y completa numero/nombre para iniciar conversacion sin errores.",
    ],
    'new.template-toggle' => [
        'title' => 'Enviar plantilla oficial',
        'description' => 'Habilitar envio por plantilla',
        'file' => 'new-template-toggle.md',
        'markdown' => "### Que hace?\nActiva el panel de plantillas oficiales para conversaciones fuera de ventana.",
    ],
    'new.template-select' => [
        'title' => 'Seleccionar plantilla',
        'description' => 'Elegir plantilla y completar variables',
        'file' => 'new-template-select.md',
        'markdown' => "### Que hace?\nCarga plantilla aprobada, variables y vista previa antes de enviar.",
    ],
    'new.send-initial' => [
        'title' => 'Enviar mensaje inicial',
        'description' => 'Crear o reusar conversacion y enviar',
        'file' => 'new-send-initial.md',
        'markdown' => "### Que hace?\nEnvia primer mensaje del contacto. Si aplica, envia plantilla oficial.",
    ],
];

foreach ($whatsappChatGuide as $key => $meta) {
    $fileName = (string)($meta['file'] ?? '');
    $filePath = $fileName !== '' ? $whatsappGuideDir . '/' . $fileName : '';
    $markdown = '';
    if ($filePath !== '' && is_readable($filePath)) {
        $markdown = (string)file_get_contents($filePath);
    }
    if (trim($markdown) === '') {
        $markdown = (string)($meta['markdown'] ?? '');
    }
    $whatsappChatGuide[$key] = [
        'title' => (string)($meta['title'] ?? ''),
        'description' => (string)($meta['description'] ?? ''),
        'markdown' => trim($markdown),
    ];
}
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
    .whatsapp-queue-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .whatsapp-queue-filters .btn {
        border-radius: 999px;
        padding: 0.2rem 0.65rem;
        font-size: 0.76rem;
    }
    .whatsapp-queue-filters .btn .badge {
        margin-left: 0.35rem;
        font-size: 0.68rem;
        vertical-align: middle;
    }
    .whatsapp-chat-bubble {
        max-width: 72%;
        border-radius: 14px;
    }
    .whatsapp-chat-bubble.whatsapp-chat-bubble--continued {
        margin-top: -6px;
    }
    .whatsapp-chat-bubble .card-body {
        padding: 0.7rem 0.9rem 0.6rem;
    }
    .whatsapp-chat-bubble.whatsapp-chat-bubble--continued .card-body {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    .whatsapp-chat-bubble .chat-text-start p {
        font-size: 0.92rem;
        line-height: 1.35;
    }
    .whatsapp-chat-day-separator {
        display: flex;
        justify-content: center;
        margin: 0.75rem 0 0.65rem;
        clear: both;
    }
    .whatsapp-chat-day-separator span {
        background: #f3f4f6;
        color: #6b7280;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 999px;
        padding: 0.15rem 0.65rem;
        font-size: 0.73rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        text-transform: capitalize;
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
    .whatsapp-guide-modal .modal-body {
        max-height: 65vh;
        overflow-y: auto;
    }
    .whatsapp-guide-content h6 {
        margin-top: 0.75rem;
        margin-bottom: 0.45rem;
        font-weight: 700;
    }
    .whatsapp-guide-content p {
        margin-bottom: 0.6rem;
    }
    .whatsapp-guide-content ul {
        padding-left: 1.1rem;
        margin-bottom: 0.65rem;
    }
    .whatsapp-guide-content code {
        background: #eef2ff;
        border-radius: 4px;
        padding: 0.08rem 0.3rem;
        color: #1e293b;
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
            data-template-queue-days="<?= isset($config['template_queue_days']) ? (int) $config['template_queue_days'] : 30; ?>"
            data-chat-group-gap-minutes="<?= isset($config['chat_group_gap_minutes']) ? (int) $config['chat_group_gap_minutes'] : 8; ?>"
            data-current-user-id="<?= $currentUserId; ?>"
            data-current-role-id="<?= $currentRoleId; ?>"
            data-can-assign="<?= $canAssign ? '1' : '0'; ?>"
    >
        <div class="col-lg-3 col-12">
            <div class="box">
                <div class="box-header">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <ul class="nav nav-tabs customtab nav-justified flex-grow-1 mb-0" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#messages" role="tab">Chat</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#contacts" role="tab">Nuevo</a>
                            </li>
                        </ul>
                        <button type="button"
                                class="btn btn-outline-info btn-sm"
                                id="whatsappGuideOpen"
                                title="Abrir guia operativa de WhatsApp"
                                aria-label="Guia operativa de WhatsApp">
                            <i class="mdi mdi-help-circle-outline"></i> Guia operativa
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <div class="mb-3">
                        <label class="form-label mb-1" for="agentPresenceSelect">Mi estado</label>
                        <select class="form-select form-select-sm"
                                id="agentPresenceSelect"
                                data-agent-presence
                                data-guide-open
                                data-guide-key="panel.my-status"
                                title="Actualizar mi estado operativo"
                                aria-label="Actualizar mi estado operativo">
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
                                               autocomplete="off"
                                               data-conversation-search
                                               data-guide-open
                                               data-guide-key="panel.search"
                                               title="Buscar por nombre, HC o numero"
                                               aria-label="Buscar por nombre historia clinica o numero">
                                    </div>
                                </div>
                                <div class="whatsapp-queue-filters mb-2"
                                     data-queue-filter-group
                                     data-guide-open
                                     data-guide-key="panel.filters"
                                     title="Ver guia de filtros de cola"
                                     aria-label="Ver guia de filtros de cola">
                                    <button type="button" class="btn btn-primary btn-sm" data-queue-filter="mine">
                                        Mis activas
                                        <span class="badge bg-light text-dark" data-queue-count="mine">0</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" data-queue-filter="open_window">
                                        Ventana 24h
                                        <span class="badge bg-info-light text-info" data-queue-count="open_window">0</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" data-queue-filter="needs_template">
                                        Requiere plantilla
                                        <span class="badge bg-warning-light text-warning" data-queue-count="needs_template">0</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-queue-filter="awaiting_template_reply">
                                        Esperando respuesta
                                        <span class="badge bg-primary-light text-primary" data-queue-count="awaiting_template_reply">0</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-queue-filter="handoff">
                                        Pendientes por tomar
                                        <span class="badge bg-danger-light text-danger" data-queue-count="handoff">0</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-queue-filter="all">
                                        Todas
                                        <span class="badge bg-secondary-light text-secondary" data-queue-count="all">0</span>
                                    </button>
                                </div>
                                <div class="d-flex justify-content-end mb-2">
                                    <button type="button"
                                            class="btn btn-outline-info btn-sm"
                                            data-guide-open
                                            data-guide-action="open"
                                            data-guide-key="panel.filters"
                                            title="Como usar filtros y colas"
                                            aria-label="Como usar filtros y colas">
                                        <i class="mdi mdi-information-outline"></i> Guia de colas
                                    </button>
                                </div>
                                <div class="media-list media-list-hover"
                                     data-conversation-list
                                     data-guide-open
                                     data-guide-key="panel.conversation-list"
                                     title="Como operar la lista de conversaciones"
                                     aria-label="Como operar la lista de conversaciones">
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
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                        <h5 class="mb-0">Iniciar conversación</h5>
                                        <button type="button"
                                                class="btn btn-outline-info btn-sm"
                                                data-guide-open
                                                data-guide-action="open"
                                                data-guide-key="new.send-initial"
                                                title="Guia para iniciar chat"
                                                aria-label="Guia para iniciar chat">
                                            <i class="mdi mdi-school-outline"></i> Guia
                                        </button>
                                    </div>
                                    <div class="mb-2">
                                        <label for="patientSearch" class="form-label">Buscar paciente</label>
                                        <input type="search" class="form-control form-control-sm" id="patientSearch"
                                               placeholder="Nombre, historia clínica o teléfono" autocomplete="off"
                                               data-patient-search
                                               data-guide-open
                                               data-guide-key="new.patient-search"
                                               title="Buscar paciente para autocompletar"
                                               aria-label="Buscar paciente para autocompletar">
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
                                               data-template-toggle
                                               data-guide-open
                                               data-guide-key="new.template-toggle"
                                               title="Habilitar envio por plantilla oficial"
                                               aria-label="Habilitar envio por plantilla oficial">
                                        <label class="form-check-label" for="waUseTemplate">Enviar plantilla oficial</label>
                                    </div>
                                    <div class="d-none" data-template-panel>
                                        <div class="mb-2">
                                            <label for="waTemplate" class="form-label">Plantilla</label>
                                            <select class="form-select form-select-sm"
                                                    id="waTemplate"
                                                    data-template-select
                                                    data-guide-open
                                                    data-guide-key="new.template-select"
                                                    title="Elegir plantilla oficial"
                                                    aria-label="Elegir plantilla oficial">
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
                                            class="btn btn-primary btn-sm w-100"
                                            data-guide-open
                                            data-guide-key="new.send-initial"
                                            title="Enviar mensaje inicial al contacto"
                                            aria-label="Enviar mensaje inicial al contacto" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
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
                                            <a class="btn btn-outline-success disabled"
                                               href="#"
                                               target="_blank"
                                               rel="noopener"
                                               data-action-open-chat
                                               data-guide-open
                                               data-guide-key="chat.open-chat"
                                               title="Abrir chat en WhatsApp Web"
                                               aria-label="Abrir chat en WhatsApp Web">
                                                <i class="mdi mdi-whatsapp"></i>
                                            </a>
                                            <button class="btn btn-outline-secondary"
                                                    type="button"
                                                    data-action-copy-number
                                                    data-guide-open
                                                    data-guide-key="chat.copy-number"
                                                    title="Copiar numero del contacto"
                                                    aria-label="Copiar numero del contacto"
                                                    disabled>
                                                <i class="mdi mdi-content-copy"></i>
                                            </button>
                                            <button class="btn btn-outline-warning"
                                                    type="button"
                                                    data-action-close-conversation
                                                    data-guide-open
                                                    data-guide-key="chat.close-conversation"
                                                    disabled
                                                    title="Cerrar conversación"
                                                    aria-label="Cerrar conversacion">
                                                <i class="mdi mdi-check-circle-outline"></i>
                                            </button>
                                            <button class="btn btn-outline-danger"
                                                    type="button"
                                                    data-action-delete-conversation
                                                    data-guide-open
                                                    data-guide-key="chat.delete-conversation"
                                                    disabled
                                                    title="Eliminar conversación"
                                                    aria-label="Eliminar conversacion">
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
                        <textarea class="form-control b-0 py-10"
                                  id="chatMessage"
                                  rows="2"
                                  placeholder="Escribe algo..."
                                  data-guide-open
                                  data-guide-key="chat.message-input"
                                  title="Escribir respuesta al paciente"
                                  aria-label="Escribir respuesta al paciente" <?= $isIntegrationEnabled ? '' : 'disabled'; ?> required></textarea>
                                <div class="d-flex flex-wrap justify-content-between align-items-center mt-md-0 mt-30 gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                data-attachment-trigger
                                                data-guide-open
                                                data-guide-key="chat.attachment"
                                                title="Adjuntar archivo"
                                                aria-label="Adjuntar archivo" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                            <i class="mdi mdi-paperclip"></i>
                                        </button>
                                        <input type="file" class="d-none" data-attachment-input
                                               accept="image/*,audio/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                        <div class="small text-muted d-none whatsapp-attachment-preview" data-attachment-preview></div>
                                    </div>
                                    <button type="submit"
                                            class="btn btn-primary"
                                            data-guide-open
                                            data-guide-key="chat.send-message"
                                            title="Enviar respuesta al contacto"
                                            aria-label="Enviar respuesta al contacto" <?= $isIntegrationEnabled ? '' : 'disabled'; ?>>
                                        Enviar
                                    </button>
                                </div>
                            </form>
                            <div class="alert alert-warning d-none mt-2 mb-0" role="alert" data-chat-lock-message></div>
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
                                        Esta conversación está fuera de la ventana de atención de 24h.
                                        Envía una plantilla aprobada para reabrir la conversación antes de escribir mensajes libres.
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
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button"
                                                class="btn btn-outline-info btn-sm"
                                                data-guide-open
                                                data-guide-action="open"
                                                data-guide-key="handoff.transfer"
                                                title="Guia de asignacion y derivacion"
                                                aria-label="Guia de asignacion y derivacion">
                                            <i class="mdi mdi-help-circle-outline"></i> Guia
                                        </button>
                                        <span class="badge bg-secondary-light text-secondary" data-handoff-badge>Sin asignar</span>
                                    </div>
                                </div>
                                <div class="small text-muted mb-2" data-handoff-queue>Equipo: —</div>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button"
                                            class="btn btn-sm btn-primary d-none"
                                            data-action="take-conversation"
                                            data-guide-open
                                            data-guide-key="handoff.take-chat"
                                            title="Tomar chat para atender"
                                            aria-label="Tomar chat para atender">
                                        <i class="mdi mdi-account-check-outline me-1"></i>Tomar chat
                                    </button>
                                </div>
                                <div>
                                    <label class="form-label small text-muted">Derivar a agente</label>
                                    <div class="d-flex gap-2">
                                        <select class="form-select form-select-sm" data-transfer-agent></select>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-action="transfer-conversation"
                                                data-guide-open
                                                data-guide-key="handoff.transfer"
                                                title="Derivar chat a otro agente"
                                                aria-label="Derivar chat a otro agente">Derivar</button>
                                    </div>
                                    <input type="text"
                                           class="form-control form-control-sm mt-2"
                                           placeholder="Nota para el agente (opcional)"
                                           data-transfer-note
                                           data-guide-open
                                           data-guide-key="handoff.transfer"
                                           title="Agregar nota para derivacion"
                                           aria-label="Agregar nota para derivacion">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<div class="modal fade whatsapp-guide-modal" id="whatsappGuideModal" tabindex="-1"
     aria-labelledby="whatsappGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="whatsappGuideModalLabel">Guia operativa de WhatsApp</h5>
                    <small class="text-muted" id="whatsappGuideModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="whatsappGuideBody" class="whatsapp-guide-content">
                    Usa el boton <strong>Guia operativa</strong> para abrir el indice de ayuda.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if (is_array($realtime)): ?>
    <script>
        window.MEDF_PusherConfig = <?= json_encode($realtime, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>;
    </script>
<?php endif; ?>
<script>
    window.__whatsappChatGuide = <?= json_encode(
        $whatsappChatGuide,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;
</script>
