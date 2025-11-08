<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */

$escape = static fn(?string $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$renderMessage = static fn(string $message): string => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), false);
$renderButtons = static function (array $buttons) use ($escape): string {
    if (empty($buttons)) {
        return '';
    }

    $items = [];
    foreach ($buttons as $button) {
        $title = $escape($button['title'] ?? '');
        $id = $escape($button['id'] ?? '');
        if ($title === '') {
            continue;
        }

        $items[] = '<li class="d-flex align-items-center justify-content-between gap-2"><span>'
            . $title . '</span><code class="bg-light px-2 py-1 rounded">' . $id . '</code></li>';
    }

    if (empty($items)) {
        return '';
    }

    return '<ul class="list-unstyled mb-0 small mt-2">' . implode('', $items) . '</ul>';
};
$formatKeywords = static function ($keywords) use ($escape): string {
    if (!is_array($keywords)) {
        return '';
    }

    $clean = array_filter(array_map(static fn($keyword): string => $escape((string)$keyword), $keywords));

    return implode(', ', $clean);
};
$displayMessage = static function ($message) use ($renderMessage, $renderButtons): string {
    if (is_array($message)) {
        $body = $renderMessage((string)($message['body'] ?? ''));
        $type = (string)($message['type'] ?? 'text');
        $header = isset($message['header']) ? $renderMessage((string)$message['header']) : '';
        $footer = isset($message['footer']) ? $renderMessage((string)$message['footer']) : '';

        $extra = '';
        if ($type === 'buttons') {
            $extra .= '<div class="small text-uppercase text-muted fw-600 mt-2">Botones</div>'
                . $renderButtons($message['buttons'] ?? []);
        }

        if ($header !== '') {
            $extra = '<div class="badge bg-secondary-light text-secondary me-2">Encabezado</div>'
                . '<div class="small mt-1">' . $header . '</div>' . $extra;
        }

        if ($footer !== '') {
            $extra .= '<div class="small text-muted mt-2">' . $footer . '</div>';
        }

        return '<div class="small">' . $body . '</div>' . $extra;
    }

    return '<div class="small">' . $renderMessage((string)$message) . '</div>';
};
$editableKeywords = static function (array $section): array {
    $keywords = [];
    if (isset($section['keywords']) && is_array($section['keywords'])) {
        foreach ($section['keywords'] as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }
            $keywords[] = trim($keyword);
        }
    }

    $auto = [];
    if (isset($section['messages']) && is_array($section['messages'])) {
        foreach ($section['messages'] as $message) {
            if (!is_array($message)) {
                continue;
            }
            if (($message['type'] ?? '') !== 'buttons') {
                continue;
            }
            foreach ($message['buttons'] ?? [] as $button) {
                if (!is_array($button)) {
                    continue;
                }
                if (isset($button['id']) && is_string($button['id'])) {
                    $auto[] = trim($button['id']);
                }
                if (isset($button['title']) && is_string($button['title'])) {
                    $auto[] = trim($button['title']);
                }
            }
        }
    }

    if (empty($auto)) {
        return array_values(array_filter($keywords, static fn($keyword) => $keyword !== ''));
    }

    $autoLookup = array_filter(array_map(static fn($keyword) => $keyword !== '' ? $keyword : null, $auto));

    return array_values(array_filter($keywords, static function ($keyword) use ($autoLookup) {
        return $keyword !== '' && !in_array($keyword, $autoLookup, true);
    }));
};

$entry = $flow['entry'] ?? [];
$options = $flow['options'] ?? [];
$fallback = $flow['fallback'] ?? [];
$meta = $flow['meta'] ?? [];
$keywordLegend = $meta['keywordLegend'] ?? [];
$brand = $meta['brand'] ?? ($config['brand'] ?? 'MedForge');
$webhookUrl = $config['webhook_url'] ?? (rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/') . '/whatsapp/webhook');
$webhookToken = trim((string)($config['webhook_verify_token'] ?? 'medforge-whatsapp'));

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
<style>
    /* ===== Autoresponder modern UI ===== */
    .flow-layout {
        display: flex;
        gap: 1.25rem;
        flex-wrap: wrap
    }

    .flow-editor-col {
        flex: 1 1 680px;
        min-width: 360px
    }

    .flow-preview-col {
        flex: 1 1 420px;
        min-width: 320px
    }

    .sticky-panel {
        position: sticky;
        top: 1rem
    }

    .chat-preview {
        background: #f6f7fb;
        border: 1px solid #e9edf4;
        border-radius: 14px;
        padding: 14px
    }

    .chat-bubble {
        background: #fff;
        border: 1px solid #e3e7ee;
        border-radius: 12px;
        padding: 10px 12px;
        margin: 8px 0;
        box-shadow: 0 1px 1px rgba(16, 24, 40, .04)
    }

    .chat-bubble.header {
        background: #eef2ff
    }

    .chat-bubble.footer {
        background: #f8fafc
    }

    .chat-meta {
        font-size: .75rem;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .02em;
        margin-bottom: 4px
    }

    .chat-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px
    }

    .chat-buttons .btn {
        padding: 2px 8px;
        font-size: .75rem
    }

    .preview-section-title {
        font-weight: 700;
        color: #374151;
        margin-top: .5rem
    }

    .preview-subtitle {
        font-size: .825rem;
        color: #6b7280;
        margin-bottom: .25rem
    }

    .form-actions-sticky {
        position: sticky;
        bottom: 0;
        background: #fff;
        border-top: 1px solid #eef0f4;
        padding: 12px 0;
        z-index: 5
    }

    .flow-editor-message .message-body {
        min-height: 84px
    }

    /* keyword chips */
    .keyword-input {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        border: 1px dashed #d7dbe5;
        border-radius: 10px;
        padding: 10px
    }

    .keyword-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eef2ff;
        color: #3b49df;
        border: 1px solid #dfe3f8;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .85rem
    }

    .keyword-chip .remove {
        cursor: pointer;
        opacity: .7
    }

    .keyword-chip .remove:hover {
        opacity: 1
    }

    .keyword-input input {
        border: 0;
        min-width: 160px;
        flex: 1 1 160px;
        outline: none;
        background: transparent;
        padding: 4px 6px
    }
</style>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Flujo de autorespuesta</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item">WhatsApp</li>
                        <li class="breadcrumb-item active" aria-current="page">Autoresponder</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-600 text-muted small">Canal activo</div>
            <div class="fw-600">
                <?= $escape($brand); ?>
            </div>
            <a href="/whatsapp/templates" class="btn btn-sm btn-outline-primary mt-2">
                <i class="mdi mdi-whatsapp me-1"></i>Gestionar plantillas
            </a>
        </div>
    </div>
</div>

<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <?php if ($statusMessage !== ''): ?>
                <div class="alert <?= $alertClass; ?> alert-dismissible fade show" role="alert">
                    <?= $escape($statusMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="box-title mb-0">Webhook de WhatsApp</h4>
                        <p class="text-muted mb-0">Comparte estos datos con Meta para recibir mensajes entrantes en el
                            autorespondedor.</p>
                    </div>
                    <a href="/settings?section=whatsapp" class="btn btn-sm btn-outline-primary">
                        <i class="mdi mdi-cog-outline me-1"></i>Configurar en ajustes
                    </a>
                </div>
                <div class="box-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-500 text-muted text-uppercase small">URL del webhook</label>
                            <input type="text" class="form-control" value="<?= $escape($webhookUrl); ?>" readonly>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-500 text-muted text-uppercase small">Token de
                                verificación</label>
                            <input type="text" class="form-control" value="<?= $escape($webhookToken); ?>" readonly>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-3">
                        Asegúrate de que el token coincida con el configurado en Meta y recuerda habilitar las
                        suscripciones para el número correspondiente.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="box-title mb-0">Diagrama general del flujo</h4>
                        <p class="text-muted mb-0">Visualiza las palabras clave y los mensajes que se envían en cada
                            paso del autorespondedor.</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary-light text-primary fw-600">Autoresponder activo</span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="d-flex flex-column gap-4">
                        <div class="flow-node border rounded-3 p-4 bg-light">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <span class="badge bg-primary me-2">Inicio</span>
                                    <h5 class="mb-1"><?= $escape($entry['title'] ?? 'Mensaje de bienvenida'); ?></h5>
                                    <p class="text-muted mb-3 small"><?= $escape($entry['description'] ?? 'Mensaje inicial que habilita el menú principal.'); ?></p>
                                </div>
                                <?php if (!empty($entry['keywords'])): ?>
                                    <div class="text-end">
                                        <div class="fw-600 text-muted small text-uppercase">Palabras clave</div>
                                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                                            <?php foreach ($entry['keywords'] as $keyword): ?>
                                                <span class="badge bg-primary-light text-primary"><?= $escape((string)$keyword); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($entry['messages'])): ?>
                                <div class="mt-3">
                                    <?php foreach ($entry['messages'] as $message): ?>
                                        <div class="bg-white border rounded-3 p-3 mb-2 shadow-sm">
                                            <div class="fw-600 text-muted small mb-1"><i
                                                        class="mdi mdi-robot-outline me-1"></i>Mensaje enviado
                                            </div>
                                            <?= $displayMessage($message); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-center">
                            <i class="mdi mdi-arrow-down-thick text-muted" style="font-size: 1.5rem;"></i>
                        </div>

                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                            <?php foreach ($options as $option): ?>
                                <div class="col">
                                    <div class="h-100 border rounded-3 p-4 shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <h5 class="mb-1"><?= $escape($option['title'] ?? 'Opción'); ?></h5>
                                                <p class="text-muted small mb-2"><?= $escape($option['description'] ?? 'Respuestas que se disparan con las palabras clave listadas.'); ?></p>
                                            </div>
                                            <?php if (!empty($option['keywords'])): ?>
                                                <div class="text-end">
                                                    <div class="fw-600 text-muted small text-uppercase">Palabras clave
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                                        <?php foreach ($option['keywords'] as $keyword): ?>
                                                            <span class="badge bg-success-light text-success"><?= $escape((string)$keyword); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($option['messages'])): ?>
                                            <div class="mt-3">
                                                <?php foreach ($option['messages'] as $message): ?>
                                                    <div class="bg-light border rounded-3 p-3 mb-2">
                                                        <div class="fw-600 text-muted small mb-1"><i
                                                                    class="mdi mdi-message-text-outline me-1"></i>Mensaje
                                                            enviado
                                                        </div>
                                                        <?= $displayMessage($message); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($option['followup'])): ?>
                                            <div class="mt-3 pt-3 border-top">
                                                <div class="fw-600 text-muted small text-uppercase mb-1">Siguiente paso
                                                    sugerido
                                                </div>
                                                <p class="small mb-0"><?= $escape($option['followup']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-center">
                            <i class="mdi mdi-arrow-down-thick text-muted" style="font-size: 1.5rem;"></i>
                        </div>

                        <div class="border rounded-3 p-4 bg-light">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <span class="badge bg-warning text-dark me-2">Fallback</span>
                                    <h5 class="mb-1"><?= $escape($fallback['title'] ?? 'Sin coincidencia'); ?></h5>
                                    <p class="text-muted mb-3 small"><?= $escape($fallback['description'] ?? 'Mensaje cuando no se reconoce la solicitud.'); ?></p>
                                </div>
                            </div>
                            <?php if (!empty($fallback['messages'])): ?>
                                <div class="mt-2">
                                    <?php foreach ($fallback['messages'] as $message): ?>
                                        <div class="bg-white border rounded-3 p-3 mb-2 shadow-sm">
                                            <div class="fw-600 text-muted small mb-1"><i
                                                        class="mdi mdi-robot-outline me-1"></i>Mensaje enviado
                                            </div>
                                            <?= $displayMessage($message); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($keywordLegend)): ?>
            <div class="col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between flex-wrap gap-2 align-items-center">
                        <div>
                            <h4 class="box-title mb-0">Leyenda de palabras clave</h4>
                            <p class="text-muted mb-0">Usa estas palabras o frases para extender el flujo en el
                                controlador.</p>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="row g-3">
                            <?php foreach ($keywordLegend as $section => $keywords): ?>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="fw-600 mb-2"><?= $escape((string)$section); ?></div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($keywords as $keyword): ?>
                                                <span class="badge bg-secondary-light text-secondary"><?= $escape((string)$keyword); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex justify-content-between flex-wrap gap-2 align-items-center">
                    <div>
                        <h4 class="box-title mb-0">Configurar respuestas</h4>
                        <p class="text-muted mb-0">Edita los mensajes, botones y palabras clave que se enviarán
                            automáticamente.</p>
                    </div>
                </div>
                <div class="box-body">
                    <div class="flow-layout">
                        <div class="flow-editor-col">
                            <form method="post" action="/whatsapp/autoresponder" id="autoresponder-form"
                                  class="flow-editor-form">
                                <input type="hidden" name="flow_payload" id="flow_payload" value="">

                                <div class="flow-editor-section mb-4" data-section="entry">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-0">Mensaje de bienvenida</h5>
                                            <p class="text-muted small mb-0">Este mensaje se envía cuando el usuario
                                                inicia la conversación o escribe una palabra clave del menú.</p>
                                        </div>
                                        <div class="text-muted small">Palabras clave separadas por coma o salto de
                                            línea.
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Título interno</label>
                                            <input type="text" class="form-control" data-field="title"
                                                   value="<?= $escape($editorEntry['title'] ?? 'Mensaje de bienvenida'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Descripción interna</label>
                                            <input type="text" class="form-control" data-field="description"
                                                   value="<?= $escape($editorEntry['description'] ?? 'Primer contacto que recibe el usuario.'); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Palabras clave</label>
                                            <div class="keyword-input" data-keywords="entry">
                                                <?php foreach (explode(',', $formatKeywords($editableKeywords($editorEntry))) as $kw): $kw = trim($kw);
                                                    if ($kw === '') continue; ?>
                                                    <span class="keyword-chip"
                                                          data-chip><span><?= $escape($kw); ?></span><span
                                                                class="remove" aria-label="Eliminar">×</span></span>
                                                <?php endforeach; ?>
                                                <input type="text" placeholder="Escribe y presiona Enter"/>
                                            </div>
                                            <textarea class="form-control d-none" rows="2" data-field="keywords"
                                                      placeholder="menu, inicio, hola"><?= $formatKeywords($editableKeywords($editorEntry)); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="flow-editor-messages" data-messages>
                                        <?php foreach ($editorEntry['messages'] ?? [] as $message): ?>
                                            <div class="flow-editor-message card card-body shadow-sm mb-3" data-message>
                                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                                    <div class="w-100 w-md-auto">
                                                        <label class="form-label small text-muted mb-1">Tipo de
                                                            mensaje</label>
                                                        <select class="form-select form-select-sm message-type">
                                                            <option value="text"<?= (($message['type'] ?? 'text') === 'text') ? ' selected' : ''; ?>>
                                                                Texto
                                                            </option>
                                                            <option value="buttons"<?= (($message['type'] ?? '') === 'buttons') ? ' selected' : ''; ?>>
                                                                Botones
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger ms-auto remove-message">
                                                        <i class="mdi mdi-delete-outline me-1"></i>Eliminar
                                                    </button>
                                                </div>
                                                <div class="mt-3">
                                                    <label class="form-label">Contenido del mensaje</label>
                                                    <textarea class="form-control message-body" rows="3"
                                                              placeholder="Escribe la respuesta que se enviará."><?= $escape($message['body'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label small">Encabezado (opcional)</label>
                                                        <input type="text" class="form-control message-header"
                                                               value="<?= $escape($message['header'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small">Pie (opcional)</label>
                                                        <input type="text" class="form-control message-footer"
                                                               value="<?= $escape($message['footer'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <div class="message-buttons mt-3<?= (($message['type'] ?? 'text') === 'buttons') ? '' : ' d-none'; ?>">
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                        <div class="fw-600 small text-muted">Botones interactivos</div>
                                                        <button type="button"
                                                                class="btn btn-xs btn-outline-primary add-button">
                                                            <i class="mdi mdi-plus"></i> Añadir botón
                                                        </button>
                                                    </div>
                                                    <div class="button-list">
                                                        <?php foreach ($message['buttons'] ?? [] as $button): ?>
                                                            <div class="input-group input-group-sm button-item mb-2"
                                                                 data-button>
                                                                <span class="input-group-text">Título</span>
                                                                <input type="text" class="form-control button-title"
                                                                       value="<?= $escape($button['title'] ?? ''); ?>"
                                                                       placeholder="Ej. Sí">
                                                                <span class="input-group-text">ID</span>
                                                                <input type="text" class="form-control button-id"
                                                                       value="<?= $escape($button['id'] ?? ''); ?>"
                                                                       placeholder="Identificador opcional">
                                                                <button type="button"
                                                                        class="btn btn-outline-danger remove-button"><i
                                                                            class="mdi mdi-close"></i></button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <p class="text-muted small mb-0">Máximo 3 botones. Se recomienda
                                                        utilizar identificadores cortos como <code>si</code>,
                                                        <code>no</code>, <code>menu</code>.</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm add-message"
                                            data-action="add-message">
                                        <i class="mdi mdi-plus"></i> Añadir mensaje
                                    </button>
                                </div>

                                <?php foreach ($editorOptions as $option): ?>
                                    <div class="flow-editor-section mb-4 border-top pt-4"
                                         data-option="<?= $escape($option['id'] ?? ''); ?>">
                                        <input type="hidden" class="option-id"
                                               value="<?= $escape($option['id'] ?? ''); ?>">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-0"><?= $escape($option['title'] ?? 'Opción'); ?></h5>
                                                <p class="text-muted small mb-0">Configura las respuestas asociadas a
                                                    esta opción del menú.</p>
                                            </div>
                                            <div class="text-muted small">Palabras clave separadas por coma o salto de
                                                línea.
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Título interno</label>
                                                <input type="text" class="form-control" data-field="title"
                                                       value="<?= $escape($option['title'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Descripción interna</label>
                                                <input type="text" class="form-control" data-field="description"
                                                       value="<?= $escape($option['description'] ?? ''); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Palabras clave</label>
                                                <div class="keyword-input"
                                                     data-keywords="<?= $escape($option['id'] ?? ''); ?>">
                                                    <?php foreach (explode(',', $formatKeywords($editableKeywords($option))) as $kw): $kw = trim($kw);
                                                        if ($kw === '') continue; ?>
                                                        <span class="keyword-chip"
                                                              data-chip><span><?= $escape($kw); ?></span><span
                                                                    class="remove" aria-label="Eliminar">×</span></span>
                                                    <?php endforeach; ?>
                                                    <input type="text" placeholder="Escribe y presiona Enter"/>
                                                </div>
                                                <textarea class="form-control d-none" rows="2" data-field="keywords"
                                                          placeholder="opcion 1, información"><?= $formatKeywords($editableKeywords($option)); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="flow-editor-messages" data-messages>
                                            <?php foreach ($option['messages'] ?? [] as $message): ?>
                                                <div class="flow-editor-message card card-body shadow-sm mb-3"
                                                     data-message>
                                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                                        <div class="w-100 w-md-auto">
                                                            <label class="form-label small text-muted mb-1">Tipo de
                                                                mensaje</label>
                                                            <select class="form-select form-select-sm message-type">
                                                                <option value="text"<?= (($message['type'] ?? 'text') === 'text') ? ' selected' : ''; ?>>
                                                                    Texto
                                                                </option>
                                                                <option value="buttons"<?= (($message['type'] ?? '') === 'buttons') ? ' selected' : ''; ?>>
                                                                    Botones
                                                                </option>
                                                            </select>
                                                        </div>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-danger ms-auto remove-message">
                                                            <i class="mdi mdi-delete-outline me-1"></i>Eliminar
                                                        </button>
                                                    </div>
                                                    <div class="mt-3">
                                                        <label class="form-label">Contenido del mensaje</label>
                                                        <textarea class="form-control message-body" rows="3"
                                                                  placeholder="Escribe la respuesta que se enviará."><?= $escape($message['body'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="row g-3 mt-2">
                                                        <div class="col-md-6">
                                                            <label class="form-label small">Encabezado
                                                                (opcional)</label>
                                                            <input type="text" class="form-control message-header"
                                                                   value="<?= $escape($message['header'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small">Pie (opcional)</label>
                                                            <input type="text" class="form-control message-footer"
                                                                   value="<?= $escape($message['footer'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="message-buttons mt-3<?= (($message['type'] ?? 'text') === 'buttons') ? '' : ' d-none'; ?>">
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                            <div class="fw-600 small text-muted">Botones interactivos
                                                            </div>
                                                            <button type="button"
                                                                    class="btn btn-xs btn-outline-primary add-button">
                                                                <i class="mdi mdi-plus"></i> Añadir botón
                                                            </button>
                                                        </div>
                                                        <div class="button-list">
                                                            <?php foreach ($message['buttons'] ?? [] as $button): ?>
                                                                <div class="input-group input-group-sm button-item mb-2"
                                                                     data-button>
                                                                    <span class="input-group-text">Título</span>
                                                                    <input type="text" class="form-control button-title"
                                                                           value="<?= $escape($button['title'] ?? ''); ?>"
                                                                           placeholder="Ej. Sí">
                                                                    <span class="input-group-text">ID</span>
                                                                    <input type="text" class="form-control button-id"
                                                                           value="<?= $escape($button['id'] ?? ''); ?>"
                                                                           placeholder="Identificador opcional">
                                                                    <button type="button"
                                                                            class="btn btn-outline-danger remove-button">
                                                                        <i class="mdi mdi-close"></i></button>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <p class="text-muted small mb-0">Máximo 3 botones. Los IDs se
                                                            utilizarán como palabras clave automáticas.</p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm add-message"
                                                data-action="add-message">
                                            <i class="mdi mdi-plus"></i> Añadir mensaje
                                        </button>
                                        <div class="mt-3">
                                            <label class="form-label">Siguiente paso sugerido</label>
                                            <textarea class="form-control" rows="2" data-field="followup"
                                                      placeholder="Texto que orienta al usuario a la siguiente acción."><?= $escape($option['followup'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top form-actions-sticky">
                                    <div class="text-muted small">Los cambios se aplican al guardar. Usa la vista previa
                                        a la derecha para simular el flujo.
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="mdi mdi-content-save-outline me-1"></i>Guardar flujo
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="flow-preview-col">
                            <div class="sticky-panel">
                                <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-600">Vista previa del flujo</div>
                                            <div class="text-muted small">Se actualiza en tiempo real</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="preview-reset">Restablecer
                                        </button>
                                    </div>
                                </div>
                                <div class="chat-preview" id="autoresponder-preview">
                                    <div class="text-muted small">Comienza a editar a la izquierda para ver aquí un
                                        simulador tipo chat.
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

<template id="message-template">
    <div class="flow-editor-message card card-body shadow-sm mb-3" data-message>
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div class="w-100 w-md-auto">
                <label class="form-label small text-muted mb-1">Tipo de mensaje</label>
                <select class="form-select form-select-sm message-type">
                    <option value="text" selected>Texto</option>
                    <option value="buttons">Botones</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-message">
                <i class="mdi mdi-delete-outline me-1"></i>Eliminar
            </button>
        </div>
        <div class="mt-3">
            <label class="form-label">Contenido del mensaje</label>
            <textarea class="form-control message-body" rows="3"
                      placeholder="Escribe la respuesta que se enviará."></textarea>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <label class="form-label small">Encabezado (opcional)</label>
                <input type="text" class="form-control message-header" value="">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Pie (opcional)</label>
                <input type="text" class="form-control message-footer" value="">
            </div>
        </div>
        <div class="message-buttons mt-3 d-none">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div class="fw-600 small text-muted">Botones interactivos</div>
                <button type="button" class="btn btn-xs btn-outline-primary add-button">
                    <i class="mdi mdi-plus"></i> Añadir botón
                </button>
            </div>
            <div class="button-list"></div>
            <p class="text-muted small mb-0">Máximo 3 botones por mensaje.</p>
        </div>
    </div>
</template>

<template id="button-template">
    <div class="input-group input-group-sm button-item mb-2" data-button>
        <span class="input-group-text">Título</span>
        <input type="text" class="form-control button-title" value="" placeholder="Texto del botón">
        <span class="input-group-text">ID</span>
        <input type="text" class="form-control button-id" value="" placeholder="Identificador opcional">
        <button type="button" class="btn btn-outline-danger remove-button"><i class="mdi mdi-close"></i></button>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        (function () {
            const form = document.getElementById('autoresponder-form');
            if (!form) return;

            const flowField = document.getElementById('flow_payload');
            const messageTemplate = document.getElementById('message-template');
            const buttonTemplate = document.getElementById('button-template');

            // === Keyword chips ===
            const initKeywordInputs = () => {
                document.querySelectorAll('.keyword-input').forEach(wrapper => {
                    const hidden = wrapper.parentElement.querySelector('[data-field="keywords"]');
                    const input = wrapper.querySelector('input');
                    const syncHidden = () => {
                        const values = Array.from(wrapper.querySelectorAll('[data-chip] > span:first-child'))
                            .map(el => el.textContent.trim())
                            .filter(Boolean);
                        hidden.value = values.join(', ');
                    };
                    wrapper.addEventListener('click', () => input.focus());
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            const val = input.value.trim().replace(/,$/, '');
                            if (!val) return;
                            const chip = document.createElement('span');
                            chip.className = 'keyword-chip';
                            chip.setAttribute('data-chip', '');
                            chip.innerHTML = `<span>${val}</span><span class="remove" aria-label="Eliminar">×</span>`;
                            wrapper.insertBefore(chip, input);
                            input.value = '';
                            syncHidden();
                            updatePreview();
                        } else if (e.key === 'Backspace' && input.value === '') {
                            const lastChip = wrapper.querySelector('[data-chip]:last-of-type');
                            if (lastChip) {
                                lastChip.remove();
                                syncHidden();
                                updatePreview();
                            }
                        }
                    });
                    wrapper.addEventListener('click', (e) => {
                        const close = e.target.closest('.remove');
                        if (!close) return;
                        const chip = close.closest('[data-chip]');
                        if (chip) chip.remove();
                        syncHidden();
                        updatePreview();
                    });
                    // Initial sync
                    syncHidden();
                });
            };

            const toggleButtons = (messageElement) => {
                const type = messageElement.querySelector('.message-type').value;
                const buttonsContainer = messageElement.querySelector('.message-buttons');
                if (!buttonsContainer) return;
                buttonsContainer.classList.toggle('d-none', type !== 'buttons');
            };

            const handleAddButton = (messageElement) => {
                const list = messageElement.querySelector('.button-list');
                if (!list || !buttonTemplate) return;
                const clone = buttonTemplate.content.firstElementChild.cloneNode(true);
                list.appendChild(clone);
                clone.querySelector('.remove-button').addEventListener('click', () => {
                    clone.remove();
                    updatePreview();
                });
                updatePreview();
            };

            const hydrateMessage = (messageElement) => {
                messageElement.querySelector('.message-type').addEventListener('change', () => {
                    toggleButtons(messageElement);
                    updatePreview();
                });
                messageElement.querySelector('.remove-message').addEventListener('click', () => {
                    messageElement.remove();
                    updatePreview();
                });
                const addButton = messageElement.querySelector('.add-button');
                if (addButton) {
                    addButton.addEventListener('click', () => handleAddButton(messageElement));
                }
                messageElement.querySelectorAll('.button-item .remove-button').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        const target = event.currentTarget;
                        if (target && target.closest('.button-item')) {
                            target.closest('.button-item').remove();
                            updatePreview();
                        }
                    });
                });
                messageElement.querySelectorAll('input, textarea, select').forEach(el => {
                    el.addEventListener('input', updatePreview);
                    el.addEventListener('change', updatePreview);
                });
                toggleButtons(messageElement);
            };

            form.querySelectorAll('[data-messages]').forEach((container) => {
                container.querySelectorAll('[data-message]').forEach((message) => hydrateMessage(message));
                const parent = container.parentElement;
                if (!parent) return;
                const addButton = parent.querySelector('[data-action="add-message"]');
                if (addButton && messageTemplate) {
                    addButton.addEventListener('click', () => {
                        const clone = messageTemplate.content.firstElementChild.cloneNode(true);
                        container.appendChild(clone);
                        hydrateMessage(clone);
                        updatePreview();
                    });
                }
            });

            const getFieldValue = (root, selector) => {
                const element = root.querySelector(selector);
                return element ? element.value : '';
            };

            const collectButtons = (messageElement) => {
                const items = [];
                messageElement.querySelectorAll('.button-item').forEach((item) => {
                    const title = getFieldValue(item, '.button-title').trim();
                    const id = getFieldValue(item, '.button-id').trim();
                    if (!title) return;
                    items.push({title, id});
                });
                return items;
            };

            const collectMessages = (container) => {
                const messages = [];
                container.querySelectorAll('[data-message]').forEach((messageElement) => {
                    const type = getFieldValue(messageElement, '.message-type') || 'text';
                    const body = getFieldValue(messageElement, '.message-body').trim();
                    const header = getFieldValue(messageElement, '.message-header').trim();
                    const footer = getFieldValue(messageElement, '.message-footer').trim();
                    if (!body) return;
                    const payload = {type, body};
                    if (header) payload.header = header;
                    if (footer) payload.footer = footer;
                    if (type === 'buttons') {
                        const buttons = collectButtons(messageElement);
                        if (!buttons.length) return;
                        payload.buttons = buttons;
                    }
                    messages.push(payload);
                });
                return messages;
            };

            const collectSection = (sectionElement) => {
                if (!sectionElement) return {};
                const data = {};
                sectionElement.querySelectorAll('[data-field]').forEach((field) => {
                    const key = field.getAttribute('data-field');
                    if (!key) return;
                    data[key] = field.value;
                });
                const messagesContainer = sectionElement.querySelector('[data-messages]');
                if (messagesContainer) data.messages = collectMessages(messagesContainer);
                return data;
            };

            const collectOption = (optionElement) => {
                const option = collectSection(optionElement);
                option.id = getFieldValue(optionElement, '.option-id');
                return option;
            };

            const buildPayload = () => {
                const entrySection = form.querySelector('[data-section="entry"]');
                const fallbackSection = form.querySelector('[data-section="fallback"]');
                const optionSections = form.querySelectorAll('[data-option]');
                return {
                    entry: collectSection(entrySection),
                    fallback: collectSection(fallbackSection),
                    options: Array.from(optionSections).map((optionElement) => collectOption(optionElement)),
                };
            };

            const renderMessagePreview = (message) => {
                const body = (message.body || '').replace(/\n/g, '<br>');
                let html = `<div class="chat-bubble">${body}</div>`;
                if (message.header) {
                    html = `<div class=\"chat-bubble header\"><div class=\"chat-meta\">Encabezado</div>${message.header}</div>` + html;
                }
                if (message.footer) {
                    html += `<div class=\"chat-bubble footer\"><div class=\"chat-meta\">Pie</div>${message.footer}</div>`;
                }
                if (message.type === 'buttons' && Array.isArray(message.buttons) && message.buttons.length) {
                    const btns = message.buttons.map(b => `<button type=\"button\" class=\"btn btn-sm btn-outline-primary\">${(b.title || '').toString().substring(0, 24)}</button>`).join('');
                    html += `<div class=\"chat-buttons\">${btns}</div>`;
                }
                return html;
            };

            const renderPreview = (payload) => {
                const container = document.getElementById('autoresponder-preview');
                if (!container) return;
                const parts = [];
                if (payload.entry && Array.isArray(payload.entry.messages)) {
                    parts.push(`<div class=\"preview-section-title\">Inicio</div>`);
                    payload.entry.messages.forEach(m => parts.push(renderMessagePreview(m)));
                }
                if (Array.isArray(payload.options)) {
                    payload.options.forEach(opt => {
                        const title = (opt.title || 'Opción');
                        parts.push(`<div class=\"preview-section-title\">${title}</div>`);
                        if (Array.isArray(opt.messages)) {
                            opt.messages.forEach(m => parts.push(renderMessagePreview(m)));
                        }
                    });
                }
                if (payload.fallback && Array.isArray(payload.fallback.messages)) {
                    parts.push(`<div class=\"preview-section-title\">Fallback</div>`);
                    payload.fallback.messages.forEach(m => parts.push(renderMessagePreview(m)));
                }
                container.innerHTML = parts.length ? parts.join('') : '<div class="text-muted small">Aún no hay contenido para previsualizar.</div>';
            };

            const updatePreview = () => {
                renderPreview(buildPayload());
            };

            form.addEventListener('submit', () => {
                flowField.value = JSON.stringify(buildPayload());
            });

            // Live preview bindings
            form.addEventListener('input', updatePreview);
            form.addEventListener('change', updatePreview);

            document.addEventListener('click', (e) => {
                if (e.target && (e.target.matches('[data-action="add-message"]') || e.target.matches('.remove-message') || e.target.matches('.remove-button') || e.target.matches('.add-button'))) {
                    setTimeout(() => updatePreview(), 0);
                }
            });

            const resetBtn = document.getElementById('preview-reset');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    document.querySelectorAll('.flow-editor-message textarea').forEach(t => t.value = '');
                    document.querySelectorAll('.button-list').forEach(b => b.innerHTML = '');
                    document.querySelectorAll('.keyword-input [data-chip]').forEach(c => c.remove());
                    document.querySelectorAll('.keyword-input input').forEach(i => i.value = '');
                    document.querySelectorAll('[data-field="keywords"]').forEach(h => h.value = '');
                    updatePreview();
                });
            }

            initKeywordInputs();
            updatePreview();
        })();
    });
</script>
