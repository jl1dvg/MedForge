<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */

$escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$renderLines = static fn (string $value): string => nl2br($escape($value), false);

$renderPreviewMessage = static function ($message) use ($escape, $renderLines): string {
    if (!is_array($message)) {
        return '<p class="mb-0">' . $renderLines((string) $message) . '</p>';
    }

    $body = $renderLines((string) ($message['body'] ?? ''));
    $badge = ($message['type'] ?? 'text') === 'buttons'
        ? '<span class="badge bg-primary-light text-primary ms-1">Botones</span>'
        : '';

    $extras = [];
    if (!empty($message['header'])) {
        $extras[] = '<div class="small text-muted">Encabezado: ' . $renderLines((string) $message['header']) . '</div>';
    }
    if (!empty($message['footer'])) {
        $extras[] = '<div class="small text-muted">Pie: ' . $renderLines((string) $message['footer']) . '</div>';
    }

    if (($message['type'] ?? '') === 'buttons' && !empty($message['buttons']) && is_array($message['buttons'])) {
        $items = [];
        foreach ($message['buttons'] as $button) {
            if (!is_array($button)) {
                continue;
            }
            $title = $escape($button['title'] ?? '');
            $id = $escape($button['id'] ?? '');
            if ($title === '') {
                continue;
            }
            $label = $id !== '' ? '<code class="ms-2">' . $id . '</code>' : '';
            $items[] = '<li class="d-flex justify-content-between align-items-center"><span>' . $title . '</span>' . $label . '</li>';
        }
        if (!empty($items)) {
            $extras[] = '<div class="small text-muted">Botones:</div><ul class="small list-unstyled mb-0">' . implode('', $items) . '</ul>';
        }
    }

    $extraBlock = empty($extras) ? '' : '<div class="mt-2 d-flex flex-column gap-1">' . implode('', $extras) . '</div>';

    return '<p class="mb-0">' . $body . $badge . '</p>' . $extraBlock;
};

$formatKeywords = static function ($keywords) use ($escape): string {
    if (!is_array($keywords)) {
        return '';
    }

    $clean = [];
    foreach ($keywords as $keyword) {
        if (!is_string($keyword)) {
            continue;
        }
        $keyword = trim($keyword);
        if ($keyword === '') {
            continue;
        }
        $clean[] = $escape($keyword);
    }

    return implode(', ', $clean);
};

$editableKeywords = static function (array $section): array {
    $keywords = [];
    if (isset($section['keywords']) && is_array($section['keywords'])) {
        foreach ($section['keywords'] as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $clean = trim($keyword);
            if ($clean !== '') {
                $keywords[] = $clean;
            }
        }
    }

    $auto = [];
    if (!empty($section['messages']) && is_array($section['messages'])) {
        foreach ($section['messages'] as $message) {
            if (!is_array($message) || ($message['type'] ?? '') !== 'buttons') {
                continue;
            }
            foreach ($message['buttons'] ?? [] as $button) {
                if (!is_array($button)) {
                    continue;
                }
                foreach (['id', 'title'] as $field) {
                    if (!empty($button[$field]) && is_string($button[$field])) {
                        $auto[] = trim($button[$field]);
                    }
                }
            }
        }
    }

    if (empty($auto)) {
        return $keywords;
    }

    $auto = array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $auto));

    return array_values(array_filter($keywords, static fn ($keyword) => $keyword !== '' && !in_array($keyword, $auto, true)));
};

$entry = $flow['entry'] ?? [];
$options = $flow['options'] ?? [];
$fallback = $flow['fallback'] ?? [];
$meta = $flow['meta'] ?? [];
$brand = $meta['brand'] ?? ($config['brand'] ?? 'MedForge');
$webhookUrl = $config['webhook_url'] ?? (rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/') . '/whatsapp/webhook');
$webhookToken = trim((string) ($config['webhook_verify_token'] ?? 'medforge-whatsapp'));

$editorEntry = $editorFlow['entry'] ?? [];
$editorOptions = $editorFlow['options'] ?? [];
$editorFallback = $editorFlow['fallback'] ?? [];

$statusType = is_array($status) ? ($status['type'] ?? 'info') : null;
$statusMessage = is_array($status) ? ($status['message'] ?? '') : '';

switch ($statusType) {
    case 'success':
        $alertClass = 'alert-success';
        break;
    case 'warning':
        $alertClass = 'alert-warning';
        break;
    case 'danger':
    case 'error':
        $alertClass = 'alert-danger';
        break;
    default:
        $alertClass = 'alert-info';
}
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Autorespuesta de WhatsApp</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item">WhatsApp</li>
                        <li class="breadcrumb-item active" aria-current="page">Autorespuesta</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-600 text-muted small">Canal activo</div>
            <div class="fw-600"><?= $escape($brand); ?></div>
            <div class="mt-2 d-flex gap-2 justify-content-end">
                <a href="/whatsapp/templates" class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-whatsapp me-1"></i>Plantillas
                </a>
                <a href="/settings?section=whatsapp" class="btn btn-sm btn-primary">
                    <i class="mdi mdi-cog-outline me-1"></i>Ajustes
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row g-4">
        <?php if ($statusMessage !== ''): ?>
            <div class="col-12">
                <div class="alert <?= $alertClass; ?> alert-dismissible fade show" role="alert">
                    <?= $escape($statusMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12 col-xl-4 d-flex flex-column gap-4">
            <div class="box h-100">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Webhook conectado</h4>
                    <p class="text-muted mb-0 small">Comparte estos datos con Meta para validar el webhook.</p>
                </div>
                <div class="box-body">
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-600 text-muted">URL del webhook</label>
                        <input type="text" class="form-control" readonly value="<?= $escape($webhookUrl); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-600 text-muted">Token de verificación</label>
                        <input type="text" class="form-control" readonly value="<?= $escape($webhookToken); ?>">
                    </div>
                    <p class="small text-muted mb-0">Recuerda habilitar las suscripciones de mensajes entrantes para este número en el panel de Meta.</p>
                </div>
            </div>

            <div class="box h-100">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Secuencia del flujo</h4>
                    <p class="text-muted mb-0 small">Visualiza qué responde el bot en cada paso.</p>
                </div>
                <div class="box-body">
                    <ol class="list-unstyled step-list mb-0 d-flex flex-column gap-3">
                        <li class="border rounded-3 p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <span class="badge bg-primary me-2">Inicio</span>
                                    <span class="fw-600"><?= $escape($entry['title'] ?? 'Mensaje de bienvenida'); ?></span>
                                </div>
                                <?php if (!empty($entry['keywords'])): ?>
                                    <div class="small text-muted text-end">
                                        <?= $formatKeywords($entry['keywords']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 d-flex flex-column gap-2">
                                <?php foreach (($entry['messages'] ?? []) as $message): ?>
                                    <div class="bg-white border rounded-3 p-2 shadow-sm">
                                        <?= $renderPreviewMessage($message); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </li>

                        <?php foreach ($options as $option): ?>
                            <li class="border rounded-3 p-3">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div>
                                        <span class="badge bg-success me-2">Opción</span>
                                        <span class="fw-600"><?= $escape($option['title'] ?? 'Opción'); ?></span>
                                    </div>
                                    <?php if (!empty($option['keywords'])): ?>
                                        <div class="small text-muted text-end">
                                            <?= $formatKeywords($option['keywords']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 d-flex flex-column gap-2">
                                    <?php foreach (($option['messages'] ?? []) as $message): ?>
                                        <div class="bg-light border rounded-3 p-2">
                                            <?= $renderPreviewMessage($message); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($option['followup'])): ?>
                                        <div class="small text-muted">Sugerencia: <?= $escape($option['followup']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>

                        <li class="border rounded-3 p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <span class="badge bg-warning text-dark me-2">Fallback</span>
                                    <span class="fw-600"><?= $escape($fallback['title'] ?? 'Sin coincidencia'); ?></span>
                                </div>
                                <?php if (!empty($fallback['keywords'])): ?>
                                    <div class="small text-muted text-end">
                                        <?= $formatKeywords($fallback['keywords']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 d-flex flex-column gap-2">
                                <?php foreach (($fallback['messages'] ?? []) as $message): ?>
                                    <div class="bg-white border rounded-3 p-2 shadow-sm">
                                        <?= $renderPreviewMessage($message); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Editar flujo</h4>
                    <p class="text-muted mb-0 small">Actualiza palabras clave, mensajes y botones interactivos. Los cambios se guardan al enviar.</p>
                </div>
                <div class="box-body">
                    <form method="post" action="/whatsapp/autoresponder" data-autoresponder-form>
                        <input type="hidden" name="flow_payload" id="flow_payload" value="">

                        <div class="flow-step mb-4" data-section="entry">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-0">Mensaje de bienvenida</h5>
                                    <p class="text-muted small mb-0">Se envía al iniciar la conversación o cuando escriben "menú".</p>
                                </div>
                                <span class="small text-muted">Usa comas o saltos de línea para separar las palabras clave.</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Título interno</label>
                                    <input type="text" class="form-control" data-field="title" value="<?= $escape($editorEntry['title'] ?? 'Mensaje de bienvenida'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" class="form-control" data-field="description" value="<?= $escape($editorEntry['description'] ?? 'Primer contacto que recibe el usuario.'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Palabras clave</label>
                                    <textarea class="form-control" rows="2" data-field="keywords" placeholder="menu, hola, inicio"><?= $escape(implode(", ", $editableKeywords($editorEntry))); ?></textarea>
                                </div>
                            </div>
                            <div class="mt-3" data-messages>
                                <?php foreach (($editorEntry['messages'] ?? []) as $message): ?>
                                    <?php include __DIR__ . '/partials/autoresponder-message.php'; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-action="add-message">
                                <i class="mdi mdi-plus"></i> Añadir respuesta
                            </button>
                        </div>

                        <?php foreach ($editorOptions as $option): ?>
                            <div class="flow-step mb-4 border-top pt-4" data-option>
                                <input type="hidden" class="option-id" value="<?= $escape($option['id'] ?? ''); ?>">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-0"><?= $escape($option['title'] ?? 'Opción personalizada'); ?></h5>
                                        <p class="text-muted small mb-0">Palabras clave que disparan esta respuesta específica.</p>
                                    </div>
                                    <span class="badge bg-success-light text-success">Opción del menú</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Título interno</label>
                                        <input type="text" class="form-control" data-field="title" value="<?= $escape($option['title'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Descripción</label>
                                        <input type="text" class="form-control" data-field="description" value="<?= $escape($option['description'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Palabras clave</label>
                                        <textarea class="form-control" rows="2" data-field="keywords" placeholder="1, opcion 1, informacion"><?= $escape(implode(", ", $editableKeywords($option))); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Siguiente paso sugerido</label>
                                        <input type="text" class="form-control" data-field="followup" value="<?= $escape($option['followup'] ?? ''); ?>" placeholder="Ej. Responde 'menu' para volver al inicio">
                                    </div>
                                </div>
                                <div class="mt-3" data-messages>
                                    <?php foreach (($option['messages'] ?? []) as $message): ?>
                                        <?php include __DIR__ . '/partials/autoresponder-message.php'; ?>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-action="add-message">
                                    <i class="mdi mdi-plus"></i> Añadir respuesta
                                </button>
                            </div>
                        <?php endforeach; ?>

                        <div class="flow-step border-top pt-4" data-section="fallback">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-0">Fallback</h5>
                                    <p class="text-muted small mb-0">Mensaje cuando no se reconoce ninguna palabra clave.</p>
                                </div>
                                <span class="badge bg-warning text-dark">Rescate</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Título interno</label>
                                    <input type="text" class="form-control" data-field="title" value="<?= $escape($editorFallback['title'] ?? 'Sin coincidencia'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" class="form-control" data-field="description" value="<?= $escape($editorFallback['description'] ?? 'Mensaje cuando no se reconoce la solicitud.'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Palabras clave</label>
                                    <textarea class="form-control" rows="2" data-field="keywords" placeholder="sin coincidencia, ayuda"><?= $escape(implode(", ", $editableKeywords($editorFallback))); ?></textarea>
                                </div>
                            </div>
                            <div class="mt-3" data-messages>
                                <?php foreach (($editorFallback['messages'] ?? []) as $message): ?>
                                    <?php include __DIR__ . '/partials/autoresponder-message.php'; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-action="add-message">
                                <i class="mdi mdi-plus"></i> Añadir respuesta
                            </button>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
                            <span class="small text-muted">Los cambios se aplicarán inmediatamente después de guardar.</span>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save-outline me-1"></i>Guardar flujo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<template id="message-template">
    <?php $message = ['type' => 'text', 'body' => '', 'header' => '', 'footer' => '', 'buttons' => []];
    include __DIR__ . '/partials/autoresponder-message.php';
    ?>
</template>

<template id="button-template">
    <div class="input-group input-group-sm mb-2" data-button>
        <span class="input-group-text">Título</span>
        <input type="text" class="form-control button-title" placeholder="Texto del botón">
        <span class="input-group-text">ID</span>
        <input type="text" class="form-control button-id" placeholder="Identificador opcional">
        <button type="button" class="btn btn-outline-danger" data-action="remove-button"><i class="mdi mdi-close"></i></button>
    </div>
</template>

<script>
(function () {
    const form = document.querySelector('[data-autoresponder-form]');
    if (!form) {
        return;
    }

    const flowField = document.getElementById('flow_payload');
    const messageTemplate = document.getElementById('message-template');
    const buttonTemplate = document.getElementById('button-template');

    const addButtonRow = (messageElement, data = {}) => {
        if (!buttonTemplate) {
            return null;
        }
        const list = messageElement.querySelector('[data-button-list]');
        if (!list) {
            return null;
        }
        const clone = buttonTemplate.content.firstElementChild.cloneNode(true);
        list.appendChild(clone);
        const titleField = clone.querySelector('.button-title');
        const idField = clone.querySelector('.button-id');
        if (titleField && data.title) {
            titleField.value = data.title;
        }
        if (idField && data.id) {
            idField.value = data.id;
        }
        const removeBtn = clone.querySelector('[data-action="remove-button"]');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => clone.remove());
        }

        return clone;
    };

    const hasButton = (messageElement, title, id) => {
        const list = messageElement.querySelectorAll('[data-button]');
        for (const item of list) {
            const existingTitle = item.querySelector('.button-title')?.value?.trim().toLowerCase();
            const existingId = item.querySelector('.button-id')?.value?.trim().toLowerCase();
            if ((title && existingTitle === title.toLowerCase()) || (id && existingId === id.toLowerCase())) {
                return true;
            }
        }
        return false;
    };

    const applyPreset = (messageElement, preset) => {
        if (preset === 'yesno') {
            if (!hasButton(messageElement, 'Sí', 'si')) {
                addButtonRow(messageElement, { title: 'Sí', id: 'si' });
            }
            if (!hasButton(messageElement, 'No', 'no')) {
                addButtonRow(messageElement, { title: 'No', id: 'no' });
            }
        }
        if (preset === 'menu') {
            if (!hasButton(messageElement, 'Menú', 'menu')) {
                addButtonRow(messageElement, { title: 'Menú', id: 'menu' });
            }
        }
    };

    const toggleButtons = (messageElement) => {
        const type = messageElement.querySelector('.message-type')?.value || 'text';
        const container = messageElement.querySelector('[data-buttons]');
        if (!container) {
            return;
        }
        if (type === 'buttons') {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    };

    const hydrateMessage = (messageElement) => {
        const typeField = messageElement.querySelector('.message-type');
        const removeMessageButton = messageElement.querySelector('[data-action="remove-message"]');
        const addButton = messageElement.querySelector('[data-action="add-button"]');
        const presetButtons = messageElement.querySelectorAll('[data-action="preset"]');

        if (typeField) {
            typeField.addEventListener('change', () => toggleButtons(messageElement));
        }
        if (removeMessageButton) {
            removeMessageButton.addEventListener('click', () => messageElement.remove());
        }
        if (addButton) {
            addButton.addEventListener('click', () => addButtonRow(messageElement));
        }
        presetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const preset = button.getAttribute('data-preset');
                if (preset) {
                    applyPreset(messageElement, preset);
                }
            });
        });
        messageElement.querySelectorAll('[data-button]').forEach((item) => {
            const remove = item.querySelector('[data-action="remove-button"]');
            if (remove) {
                remove.addEventListener('click', () => item.remove());
            }
        });

        toggleButtons(messageElement);
    };

    form.querySelectorAll('[data-messages]').forEach((container) => {
        container.querySelectorAll('[data-message]').forEach((message) => hydrateMessage(message));
        const addMessageButton = container.parentElement?.querySelector('[data-action="add-message"]');
        if (addMessageButton && messageTemplate) {
            addMessageButton.addEventListener('click', () => {
                const clone = messageTemplate.content.firstElementChild.cloneNode(true);
                container.appendChild(clone);
                hydrateMessage(clone);
            });
        }
    });

    const collectButtons = (messageElement) => {
        const buttons = [];
        messageElement.querySelectorAll('[data-button]').forEach((item) => {
            const title = item.querySelector('.button-title')?.value?.trim() || '';
            const id = item.querySelector('.button-id')?.value?.trim() || '';
            if (title !== '') {
                buttons.push({ title, id });
            }
        });
        return buttons;
    };

    const collectMessages = (container) => {
        const messages = [];
        container.querySelectorAll('[data-message]').forEach((messageElement) => {
            const type = messageElement.querySelector('.message-type')?.value || 'text';
            const body = messageElement.querySelector('.message-body')?.value?.trim() || '';
            const header = messageElement.querySelector('.message-header')?.value?.trim() || '';
            const footer = messageElement.querySelector('.message-footer')?.value?.trim() || '';

            if (body === '') {
                return;
            }

            const payload = { type, body };
            if (header !== '') {
                payload.header = header;
            }
            if (footer !== '') {
                payload.footer = footer;
            }
            if (type === 'buttons') {
                const buttons = collectButtons(messageElement);
                if (buttons.length === 0) {
                    return;
                }
                payload.buttons = buttons;
            }

            messages.push(payload);
        });
        return messages;
    };

    const collectSection = (sectionElement) => {
        if (!sectionElement) {
            return {};
        }
        const data = {};
        sectionElement.querySelectorAll('[data-field]').forEach((field) => {
            const key = field.getAttribute('data-field');
            if (!key) {
                return;
            }
            data[key] = field.value;
        });
        const messagesContainer = sectionElement.querySelector('[data-messages]');
        if (messagesContainer) {
            data.messages = collectMessages(messagesContainer);
        }
        return data;
    };

    const collectOption = (optionElement) => {
        const option = collectSection(optionElement);
        option.id = optionElement.querySelector('.option-id')?.value || '';
        return option;
    };

    form.addEventListener('submit', (event) => {
        const entrySection = form.querySelector('[data-section="entry"]');
        const fallbackSection = form.querySelector('[data-section="fallback"]');
        const optionSections = Array.from(form.querySelectorAll('[data-option]'));

        const payload = {
            entry: collectSection(entrySection),
            fallback: collectSection(fallbackSection),
            options: optionSections.map((element) => collectOption(element)),
        };

        flowField.value = JSON.stringify(payload);
    });
})();
</script>
