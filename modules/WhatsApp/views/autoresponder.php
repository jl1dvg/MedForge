<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */
/** @var array $templates */
/** @var string|null $templatesError */

$escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$renderLines = static fn (string $value): string => nl2br($escape($value), false);

$extractPlaceholders = static function (string $text): array {
    if ($text === '') {
        return [];
    }

    preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);
    if (empty($matches[1])) {
        return [];
    }

    $numbers = array_map(static fn ($value) => (int) $value, $matches[1]);
    $numbers = array_values(array_filter(array_unique($numbers), static fn ($value) => $value > 0));

    return $numbers;
};

$prepareTemplateCatalog = static function (array $templates) use ($extractPlaceholders): array {
    $catalog = [];

    foreach ($templates as $template) {
        if (!is_array($template)) {
            continue;
        }

        $name = isset($template['name']) ? trim((string) $template['name']) : '';
        $language = isset($template['language']) ? trim((string) $template['language']) : '';
        if ($name === '' || $language === '') {
            continue;
        }

        $category = isset($template['category']) ? trim((string) $template['category']) : '';
        $components = [];
        if (isset($template['components']) && is_array($template['components'])) {
            foreach ($template['components'] as $component) {
                if (!is_array($component)) {
                    continue;
                }

                $type = strtoupper(trim((string) ($component['type'] ?? '')));
                if ($type === '') {
                    continue;
                }

                $entry = ['type' => $type];

                if (isset($component['format'])) {
                    $entry['format'] = strtoupper(trim((string) $component['format']));
                }

                if (isset($component['text']) && is_string($component['text'])) {
                    $entry['text'] = trim($component['text']);
                    $entry['placeholders'] = $extractPlaceholders($entry['text']);
                }

                if ($type === 'BUTTONS' && isset($component['buttons']) && is_array($component['buttons'])) {
                    $entry['buttons'] = [];
                    foreach ($component['buttons'] as $index => $button) {
                        if (!is_array($button)) {
                            continue;
                        }

                        $buttonType = strtoupper(trim((string) ($button['type'] ?? '')));
                        $buttonEntry = [
                            'type' => $buttonType,
                            'index' => $index,
                            'text' => isset($button['text']) ? trim((string) $button['text']) : '',
                        ];

                        if ($buttonType === 'URL' && isset($button['url']) && is_string($button['url'])) {
                            $buttonEntry['placeholders'] = $extractPlaceholders($button['url']);
                        }

                        if ($buttonType === 'COPY_CODE' && isset($button['example']) && is_array($button['example'])) {
                            $buttonEntry['placeholders'] = $extractPlaceholders(implode(' ', $button['example']));
                        }

                        $entry['buttons'][] = $buttonEntry;
                    }
                }

                $components[] = $entry;
            }
        }

        $catalog[] = [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
        ];
    }

    return $catalog;
};

$templateCatalog = $prepareTemplateCatalog($templates ?? []);
$templateCount = count($templateCatalog);
$templateCategories = [];
foreach ($templateCatalog as $templateMeta) {
    $category = strtoupper((string) ($templateMeta['category'] ?? ''));
    if ($category === '') {
        $category = 'SIN CATEGORÍA';
    }
    $templateCategories[$category] = ($templateCategories[$category] ?? 0) + 1;
}
$templatesJson = htmlspecialchars(json_encode($templateCatalog, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');

$renderPreviewMessage = static function ($message) use ($escape, $renderLines): string {
    if (!is_array($message)) {
        return '<p class="mb-0">' . $renderLines((string) $message) . '</p>';
    }

    $body = $renderLines((string) ($message['body'] ?? ''));
    $type = $message['type'] ?? 'text';
    $badge = '';
    if ($type === 'buttons') {
        $badge = '<span class="badge bg-primary-light text-primary ms-1">Botones</span>';
    } elseif ($type === 'list') {
        $badge = '<span class="badge bg-success-light text-success ms-1">Lista</span>';
    } elseif ($type === 'template') {
        $badge = '<span class="badge bg-info-light text-info ms-1">Plantilla</span>';
    }

    $extras = [];
    if (!empty($message['header'])) {
        $extras[] = '<div class="small text-muted">Encabezado: ' . $renderLines((string) $message['header']) . '</div>';
    }
    if (!empty($message['footer'])) {
        $extras[] = '<div class="small text-muted">Pie: ' . $renderLines((string) $message['footer']) . '</div>';
    }

    if ($type === 'buttons' && !empty($message['buttons']) && is_array($message['buttons'])) {
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

    if ($type === 'list' && !empty($message['sections']) && is_array($message['sections'])) {
        $sectionBlocks = [];
        foreach ($message['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }

            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $title = $escape($row['title'] ?? '');
                $id = $escape($row['id'] ?? '');
                if ($title === '') {
                    continue;
                }

                $desc = '';
                if (!empty($row['description'])) {
                    $desc = '<small class="text-muted d-block">' . $escape($row['description']) . '</small>';
                }

                $rows[] = '<li class="mb-1"><span class="fw-600">' . $title . '</span>' . ($id !== '' ? ' <code>' . $id . '</code>' : '') . $desc . '</li>';
            }

            if (empty($rows)) {
                continue;
            }

            $sectionTitle = isset($section['title']) && $section['title'] !== '' ? '<div class="fw-600">' . $escape($section['title']) . '</div>' : '';
            $sectionBlocks[] = '<div class="small text-muted">' . $sectionTitle . '<ul class="small list-unstyled mb-0">' . implode('', $rows) . '</ul></div>';
        }

        if (!empty($sectionBlocks)) {
            $extras[] = '<div class="mt-2">' . implode('', $sectionBlocks) . '</div>';
        }
    }

    if ($type === 'template' && !empty($message['template']) && is_array($message['template'])) {
        $template = $message['template'];
        $details = [];
        if (!empty($template['name'])) {
            $details[] = '<div><span class="fw-600">Nombre:</span> ' . $escape((string) $template['name']) . '</div>';
        }
        if (!empty($template['language'])) {
            $details[] = '<div><span class="fw-600">Idioma:</span> ' . $escape((string) $template['language']) . '</div>';
        }
        if (!empty($template['category'])) {
            $details[] = '<div><span class="fw-600">Categoría:</span> ' . $escape((string) $template['category']) . '</div>';
        }

        if (!empty($details)) {
            $extras[] = '<div class="mt-2 text-muted small">' . implode('', $details) . '</div>';
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
<style>
    [data-message].has-validation-error {
        border: 1px solid #dc3545;
        box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.1);
    }

    [data-message].has-validation-error .card-body,
    [data-message].has-validation-error.card-body {
        border-color: inherit;
    }

    .is-invalid[data-template-parameter] {
        background-color: #fff5f5;
    }

    /* Prevent stacked boxes from stretching to full height in the left column */
    @media (min-width: 1200px) {
      .col-xl-4 .box { height: auto; }
    }
</style>
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
            <div class="box">
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

            <?php if (!empty($templatesError)): ?>
                <div class="alert alert-warning mb-0">
                    <strong>No pudimos sincronizar las plantillas:</strong> <?= $escape($templatesError); ?>
                </div>
            <?php endif; ?>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Plantillas disponibles</h4>
                    <p class="text-muted mb-0 small">Reutiliza tus mensajes aprobados para flujos automáticos.</p>
                </div>
                <div class="box-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-600 display-6 mb-0"><?= (int) $templateCount; ?></div>
                            <div class="small text-muted">plantillas listas</div>
                        </div>
                        <a href="/whatsapp/templates" class="btn btn-outline-primary btn-sm">
                            Gestionar
                        </a>
                    </div>
                    <?php if (!empty($templateCategories)): ?>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($templateCategories as $category => $count): ?>
                                <li class="d-flex justify-content-between align-items-center">
                                    <span><?= $escape($category); ?></span>
                                    <span class="badge bg-light text-dark"><?= (int) $count; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="small text-muted mb-0">Aún no se han sincronizado plantillas. Puedes crearlas desde Meta o desde el administrador de plantillas.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="box">
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
                        <input type="hidden" name="template_catalog" value="<?= $templatesJson; ?>" data-template-catalog>
                        <input type="hidden" name="flow_payload" id="flow_payload" value="">

                        <div class="alert alert-danger d-none" data-validation-errors role="alert"></div>

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
    const validationAlert = form.querySelector('[data-validation-errors]');
    let validationErrors = [];

    const resetValidationState = () => {
        validationErrors = [];
        if (validationAlert) {
            validationAlert.classList.add('d-none');
            validationAlert.innerHTML = '';
        }
        form.querySelectorAll('[data-message].has-validation-error').forEach((element) => {
            element.classList.remove('has-validation-error');
        });
        form.querySelectorAll('[data-template-parameter].is-invalid').forEach((element) => {
            element.classList.remove('is-invalid');
        });
    };

    const recordValidationError = (message, element) => {
        validationErrors.push({ message, element });
        if (element) {
            element.classList.add('has-validation-error');
        }
    };

    const presentValidationErrors = () => {
        if (!validationAlert || validationErrors.length === 0) {
            return;
        }

        const items = validationErrors.map((entry) => `<li>${entry.message}</li>`).join('');
        validationAlert.innerHTML = `<strong>Revisa los siguientes puntos antes de guardar:</strong><ul class="mb-0">${items}</ul>`;
        validationAlert.classList.remove('d-none');

        const firstError = validationErrors[0];
        if (firstError && firstError.element && typeof firstError.element.scrollIntoView === 'function') {
            firstError.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    const messageTemplate = document.getElementById('message-template');
    const buttonTemplate = document.getElementById('button-template');
    const templateCatalogInput = form.querySelector('[data-template-catalog]');
    let templateCatalog = [];

    if (templateCatalogInput) {
        try {
            templateCatalog = JSON.parse(templateCatalogInput.value || '[]');
        } catch (error) {
            console.warn('No fue posible interpretar el catálogo de plantillas', error);
            templateCatalog = [];
        }
        templateCatalogInput.name = '';
    }

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

    const createRowElement = (data = {}) => {
        const row = document.createElement('div');
        row.className = 'input-group input-group-sm mb-2';
        row.setAttribute('data-row', '');
        row.innerHTML = `
            <span class="input-group-text">Título</span>
            <input type="text" class="form-control row-title" placeholder="Ej: Confirmar">
            <span class="input-group-text">ID</span>
            <input type="text" class="form-control row-id" placeholder="Identificador">
            <input type="text" class="form-control row-description" placeholder="Descripción opcional">
            <button type="button" class="btn btn-outline-danger" data-action="remove-row"><i class="mdi mdi-close"></i></button>
        `;

        if (data.title) {
            row.querySelector('.row-title').value = data.title;
        }
        if (data.id) {
            row.querySelector('.row-id').value = data.id;
        }
        if (data.description) {
            row.querySelector('.row-description').value = data.description;
        }

        return row;
    };

    const createSectionElement = (data = {}) => {
        const section = document.createElement('div');
        section.className = 'border rounded-3 p-3 mb-3';
        section.setAttribute('data-section', '');
        section.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <input type="text" class="form-control section-title" placeholder="Título de la sección (opcional)">
                <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-section"><i class="mdi mdi-close"></i></button>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small text-muted">Opciones</div>
                <button type="button" class="btn btn-xs btn-outline-secondary" data-action="add-row">Añadir opción</button>
            </div>
            <div data-rows></div>
        `;

        if (data.title) {
            section.querySelector('.section-title').value = data.title;
        }

        const rowsContainer = section.querySelector('[data-rows]');
        if (Array.isArray(data.rows)) {
            data.rows.forEach((rowData) => {
                rowsContainer.appendChild(createRowElement(rowData));
            });
        }

        return section;
    };

    const hydrateRow = (rowElement) => {
        const removeButton = rowElement.querySelector('[data-action="remove-row"]');
        if (removeButton && !removeButton.dataset.bound) {
            removeButton.dataset.bound = '1';
            removeButton.addEventListener('click', () => rowElement.remove());
        }
    };

    const hydrateSection = (sectionElement) => {
        const removeButton = sectionElement.querySelector('[data-action="remove-section"]');
        if (removeButton && !removeButton.dataset.bound) {
            removeButton.dataset.bound = '1';
            removeButton.addEventListener('click', () => sectionElement.remove());
        }

        const addRowButton = sectionElement.querySelector('[data-action="add-row"]');
        if (addRowButton && !addRowButton.dataset.bound) {
            addRowButton.dataset.bound = '1';
            addRowButton.addEventListener('click', () => {
                const container = sectionElement.querySelector('[data-rows]');
                const row = createRowElement();
                container.appendChild(row);
                hydrateRow(row);
            });
        }

        sectionElement.querySelectorAll('[data-row]').forEach((row) => hydrateRow(row));
    };

    const ensureListControls = (messageElement) => {
        const listContainer = messageElement.querySelector('[data-list]');
        if (!listContainer) {
            return;
        }

        const sectionsWrapper = listContainer.querySelector('[data-sections]');
        if (!sectionsWrapper) {
            return;
        }

        sectionsWrapper.querySelectorAll('[data-section]').forEach((section) => hydrateSection(section));

        const addSectionButton = listContainer.querySelector('[data-action="add-section"]');
        if (addSectionButton && !addSectionButton.dataset.bound) {
            addSectionButton.dataset.bound = '1';
            addSectionButton.addEventListener('click', () => {
                const section = createSectionElement();
                sectionsWrapper.appendChild(section);
                hydrateSection(section);
            });
        }
    };

    const findTemplateMeta = (name, language) => {
        if (!name || !language) {
            return null;
        }

        return templateCatalog.find((template) => {
            return template.name === name && template.language === language;
        }) || null;
    };

    const renderTemplateSummary = (summaryElement, meta, fallback) => {
        if (!summaryElement) {
            return;
        }

        if (!meta && !fallback) {
            summaryElement.innerHTML = '<div class="fw-600">Sin plantilla seleccionada</div><div>Elige una plantilla para ver sus variables y completar los parámetros.</div>';
            return;
        }

        const name = meta?.name || fallback?.name || '';
        const language = meta?.language || fallback?.language || '';
        const category = meta?.category || fallback?.category || '';

        summaryElement.innerHTML = `
            <div class="fw-600">${name} · ${language}</div>
            <div class="text-muted">Categoría: ${category || 'Sin categoría'}</div>
        `;
    };

    const extractExistingComponents = (componentField) => {
        if (!componentField) {
            return { body: [], header: [], buttons: {} };
        }

        try {
            const parsed = JSON.parse(componentField.value || '[]');
            const existing = { body: [], header: [], buttons: {} };

            parsed.forEach((component) => {
                if (!component || typeof component !== 'object') {
                    return;
                }

                const type = (component.type || '').toUpperCase();
                if (type === 'BODY' && Array.isArray(component.parameters)) {
                    existing.body = component.parameters;
                }
                if (type === 'HEADER' && Array.isArray(component.parameters)) {
                    existing.header = component.parameters;
                }
                if (type === 'BUTTON' && Array.isArray(component.parameters)) {
                    const index = Number.isInteger(component.index) ? component.index : 0;
                    existing.buttons[index] = component.parameters;
                }
            });

            return existing;
        } catch (error) {
            return { body: [], header: [], buttons: {} };
        }
    };

    const buildTemplateParameters = (messageElement, meta) => {
        const container = messageElement.querySelector('.template-parameters');
        const componentsField = messageElement.querySelector('.template-components');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!meta) {
            return;
        }

        const existing = extractExistingComponents(componentsField);
        const blocks = [];

        meta.components.forEach((component) => {
            const type = (component.type || '').toUpperCase();
            if (type === 'BODY' && Array.isArray(component.placeholders) && component.placeholders.length > 0) {
                const group = document.createElement('div');
                group.className = 'mb-3';
                group.innerHTML = '<label class="form-label small">Variables del cuerpo</label>';
                component.placeholders.forEach((placeholder) => {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control mb-2';
                    input.placeholder = `Valor para {{${placeholder}}}`;
                    input.setAttribute('data-template-parameter', '');
                    input.setAttribute('data-component', 'BODY');
                    input.setAttribute('data-placeholder', String(placeholder));
                    const existingParam = existing.body[placeholder - 1] || {};
                    if (typeof existingParam.text === 'string') {
                        input.value = existingParam.text;
                    }
                    group.appendChild(input);
                });
                blocks.push(group);
            }

            if (type === 'HEADER' && component.format === 'TEXT' && Array.isArray(component.placeholders) && component.placeholders.length > 0) {
                const group = document.createElement('div');
                group.className = 'mb-3';
                group.innerHTML = '<label class="form-label small">Variables del encabezado</label>';
                component.placeholders.forEach((placeholder) => {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control mb-2';
                    input.placeholder = `Valor para encabezado {{${placeholder}}}`;
                    input.setAttribute('data-template-parameter', '');
                    input.setAttribute('data-component', 'HEADER');
                    input.setAttribute('data-placeholder', String(placeholder));
                    const existingParam = existing.header[placeholder - 1] || {};
                    if (typeof existingParam.text === 'string') {
                        input.value = existingParam.text;
                    }
                    group.appendChild(input);
                });
                blocks.push(group);
            }

            if (type === 'BUTTONS' && Array.isArray(component.buttons)) {
                component.buttons.forEach((button) => {
                    const subType = (button.type || '').toUpperCase();
                    const index = Number.isInteger(button.index) ? button.index : 0;
                    const placeholders = Array.isArray(button.placeholders) ? button.placeholders : [];
                    if (placeholders.length === 0) {
                        return;
                    }

                    const group = document.createElement('div');
                    group.className = 'mb-3';
                    const labelText = button.text ? `${button.text} (${subType})` : `Botón ${index + 1} (${subType})`;
                    group.innerHTML = `<label class="form-label small">${labelText}</label>`;

                    placeholders.forEach((placeholder) => {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control mb-2';
                        input.placeholder = `Valor para botón {{${placeholder}}}`;
                        input.setAttribute('data-template-parameter', '');
                        input.setAttribute('data-component', 'BUTTON');
                        input.setAttribute('data-index', String(index));
                        input.setAttribute('data-placeholder', String(placeholder));
                        const existingButtonParams = existing.buttons[index] || [];
                        const existingParam = existingButtonParams[placeholder - 1] || {};
                        const candidate = existingParam.text || existingParam.payload;
                        if (typeof candidate === 'string') {
                            input.value = candidate;
                        }
                        group.appendChild(input);
                    });

                    blocks.push(group);
                });
            }
        });

        if (blocks.length === 0) {
            const note = document.createElement('p');
            note.className = 'small text-muted mb-0';
            note.textContent = 'Esta plantilla no requiere variables. Se enviará tal como está configurada en Meta.';
            container.appendChild(note);
        } else {
            blocks.forEach((block) => container.appendChild(block));
        }

    };

    const ensureTemplateControls = (messageElement) => {
        const templateContainer = messageElement.querySelector('[data-template]');
        if (!templateContainer) {
            return;
        }

        const select = templateContainer.querySelector('.template-selector');
        const nameField = templateContainer.querySelector('.template-name');
        const languageField = templateContainer.querySelector('.template-language');
        const categoryField = templateContainer.querySelector('.template-category');
        const summaryElement = templateContainer.querySelector('.template-summary');
        const componentsField = templateContainer.querySelector('.template-components');

        if (select && !select.dataset.loaded) {
            templateCatalog.forEach((template) => {
                const option = document.createElement('option');
                option.value = `${template.name}::${template.language}`;
                const categoryLabel = template.category ? template.category : 'Sin categoría';
                option.textContent = `${template.name} · ${template.language} (${categoryLabel})`;
                select.appendChild(option);
            });
            select.dataset.loaded = '1';
        }

        const selectedName = nameField?.value?.trim();
        const selectedLanguage = languageField?.value?.trim();
        const meta = findTemplateMeta(selectedName, selectedLanguage);
        if (select && selectedName && selectedLanguage) {
            const value = `${selectedName}::${selectedLanguage}`;
            if (!Array.from(select.options).some((option) => option.value === value)) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = value;
                fallbackOption.textContent = `${selectedName} · ${selectedLanguage} (no sincronizada)`;
                select.appendChild(fallbackOption);
            }
            select.value = value;
        }

        renderTemplateSummary(summaryElement, meta, {
            name: selectedName,
            language: selectedLanguage,
            category: categoryField?.value?.trim() || '',
        });

        const parametersContainer = templateContainer.querySelector('.template-parameters');
        const currentKey = templateContainer.dataset.renderedTemplate || '';
        const nextKey = meta ? `${meta.name}::${meta.language}` : '';
        if (!parametersContainer || parametersContainer.childElementCount === 0 || currentKey !== nextKey) {
            buildTemplateParameters(messageElement, meta);
            templateContainer.dataset.renderedTemplate = nextKey;
        }

        if (select && !select.dataset.bound) {
            select.dataset.bound = '1';
            select.addEventListener('change', () => {
                const value = select.value;
                if (!value) {
                    if (nameField) nameField.value = '';
                    if (languageField) languageField.value = '';
                    if (categoryField) categoryField.value = '';
                    renderTemplateSummary(summaryElement, null, null);
                    buildTemplateParameters(messageElement, null);
                    templateContainer.dataset.renderedTemplate = '';
                    if (componentsField) {
                        componentsField.value = '[]';
                    }
                    return;
                }

                const [name, language] = value.split('::');
                const selectedMeta = findTemplateMeta(name, language);
                if (nameField) nameField.value = name || '';
                if (languageField) languageField.value = language || '';
                if (categoryField) categoryField.value = selectedMeta?.category || '';
                renderTemplateSummary(summaryElement, selectedMeta, null);
                buildTemplateParameters(messageElement, selectedMeta);
                templateContainer.dataset.renderedTemplate = selectedMeta ? `${selectedMeta.name}::${selectedMeta.language}` : '';
                if (componentsField) {
                    componentsField.value = '[]';
                }
            });
        }
    };

    const toggleMessageFields = (messageElement) => {
        const type = messageElement.querySelector('.message-type')?.value || 'text';
        const buttonsContainer = messageElement.querySelector('[data-buttons]');
        const listContainer = messageElement.querySelector('[data-list]');
        const templateContainer = messageElement.querySelector('[data-template]');
        const headerField = messageElement.querySelector('.message-header');
        const footerField = messageElement.querySelector('.message-footer');

        if (buttonsContainer) {
            buttonsContainer.classList.toggle('d-none', type !== 'buttons');
        }
        if (listContainer) {
            listContainer.classList.toggle('d-none', type !== 'list');
        }
        if (templateContainer) {
            templateContainer.classList.toggle('d-none', type !== 'template');
        }

        if (type === 'template') {
            if (headerField) headerField.setAttribute('disabled', 'disabled');
            if (footerField) footerField.setAttribute('disabled', 'disabled');
            ensureTemplateControls(messageElement);
        } else {
            if (headerField) headerField.removeAttribute('disabled');
            if (footerField) footerField.removeAttribute('disabled');
        }

        if (type === 'list') {
            ensureListControls(messageElement);
            const wrapper = messageElement.querySelector('[data-sections]');
            if (wrapper && !wrapper.querySelector('[data-section]')) {
                const section = createSectionElement();
                wrapper.appendChild(section);
                hydrateSection(section);
            }
        }
    };

    const hydrateMessage = (messageElement) => {
        const typeField = messageElement.querySelector('.message-type');
        const removeMessageButton = messageElement.querySelector('[data-action="remove-message"]');
        const addButton = messageElement.querySelector('[data-action="add-button"]');
        const presetButtons = messageElement.querySelectorAll('[data-action="preset"]');

        if (typeField) {
            typeField.addEventListener('change', () => toggleMessageFields(messageElement));
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

        ensureListControls(messageElement);
        ensureTemplateControls(messageElement);
        toggleMessageFields(messageElement);
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

    const collectListData = (messageElement) => {
        const buttonLabel = messageElement.querySelector('.list-button')?.value?.trim() || 'Ver opciones';
        const sections = [];
        messageElement.querySelectorAll('[data-section]').forEach((sectionElement) => {
            const rows = [];
            sectionElement.querySelectorAll('[data-row]').forEach((rowElement) => {
                const title = rowElement.querySelector('.row-title')?.value?.trim() || '';
                const id = rowElement.querySelector('.row-id')?.value?.trim() || '';
                if (title === '' || id === '') {
                    return;
                }
                const description = rowElement.querySelector('.row-description')?.value?.trim() || '';
                const row = { title, id };
                if (description !== '') {
                    row.description = description;
                }
                rows.push(row);
            });

            if (rows.length === 0) {
                return;
            }

            const title = sectionElement.querySelector('.section-title')?.value?.trim() || '';
            sections.push({ title, rows });
        });

        return { button: buttonLabel, sections };
    };

    const collectTemplateData = (messageElement, contextLabel, messageIndex) => {
        const sectionDescription = contextLabel ? `en la sección "${contextLabel}"` : 'en esta sección';
        const messageDescription = `${sectionDescription} (mensaje ${messageIndex + 1})`;
        const name = messageElement.querySelector('.template-name')?.value?.trim() || '';
        const language = messageElement.querySelector('.template-language')?.value?.trim() || '';

        if (name === '' || language === '') {
            recordValidationError(`Selecciona una plantilla aprobada ${messageDescription}.`, messageElement);
            return null;
        }

        const category = messageElement.querySelector('.template-category')?.value?.trim() || '';
        const componentsField = messageElement.querySelector('.template-components');
        const meta = findTemplateMeta(name, language);

        messageElement.querySelectorAll('[data-template-parameter]').forEach((input) => {
            input.classList.remove('is-invalid');
        });

        if (!meta) {
            recordValidationError(`La plantilla "${name}" (${language}) ya no está disponible; vuelve a seleccionarla ${messageDescription}.`, messageElement);
            return null;
        }

        const components = [];
        let messageHasErrors = false;

        const appendParameters = (type, parameters, extra = {}) => {
            if (!parameters || parameters.length === 0) {
                return;
            }
            components.push(Object.assign({ type, parameters }, extra));
        };

        const bodyComponent = meta.components.find((component) => component.type === 'BODY');
        if (bodyComponent && Array.isArray(bodyComponent.placeholders) && bodyComponent.placeholders.length > 0) {
            const missing = [];
            const parameters = [];
            bodyComponent.placeholders.forEach((placeholder) => {
                const input = messageElement.querySelector(`[data-template-parameter][data-component="BODY"][data-placeholder="${placeholder}"]`);
                const value = input?.value?.trim() || '';
                if (!input || value === '') {
                    missing.push(placeholder);
                    if (input) {
                        input.classList.add('is-invalid');
                    }
                    return;
                }
                parameters.push({ type: 'text', text: value });
            });
            if (missing.length > 0) {
                messageHasErrors = true;
                const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders} del cuerpo de la plantilla ${messageDescription}.`, messageElement);
            } else {
                appendParameters('BODY', parameters);
            }
        }

        const headerComponent = meta.components.find((component) => component.type === 'HEADER' && component.format === 'TEXT');
        if (headerComponent && Array.isArray(headerComponent.placeholders) && headerComponent.placeholders.length > 0) {
            const missing = [];
            const parameters = [];
            headerComponent.placeholders.forEach((placeholder) => {
                const input = messageElement.querySelector(`[data-template-parameter][data-component="HEADER"][data-placeholder="${placeholder}"]`);
                const value = input?.value?.trim() || '';
                if (!input || value === '') {
                    missing.push(placeholder);
                    if (input) {
                        input.classList.add('is-invalid');
                    }
                    return;
                }
                parameters.push({ type: 'text', text: value });
            });
            if (missing.length > 0) {
                messageHasErrors = true;
                const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders} del encabezado ${messageDescription}.`, messageElement);
            } else {
                appendParameters('HEADER', parameters);
            }
        }

        meta.components
            .filter((component) => component.type === 'BUTTONS' && Array.isArray(component.buttons))
            .forEach((component) => {
                component.buttons.forEach((button) => {
                    if (!Array.isArray(button.placeholders) || button.placeholders.length === 0) {
                        return;
                    }

                    const missing = [];
                    const parameters = [];
                    button.placeholders.forEach((placeholder) => {
                        const input = messageElement.querySelector(`[data-template-parameter][data-component="BUTTON"][data-index="${button.index}"][data-placeholder="${placeholder}"]`);
                        const value = input?.value?.trim() || '';
                        if (!input || value === '') {
                            missing.push(placeholder);
                            if (input) {
                                input.classList.add('is-invalid');
                            }
                            return;
                        }
                        parameters.push({ type: 'text', text: value });
                    });

                    if (missing.length > 0) {
                        messageHasErrors = true;
                        const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                        const buttonLabel = button.text ? ` del botón "${button.text}"` : '';
                        recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders}${buttonLabel} ${messageDescription}.`, messageElement);
                    } else {
                        appendParameters('BUTTON', parameters, {
                            sub_type: button.type,
                            index: button.index,
                        });
                    }
                });
            });

        if (messageHasErrors) {
            return null;
        }

        if (componentsField) {
            componentsField.value = JSON.stringify(components);
        }

        return {
            name,
            language,
            category,
            components,
        };
    };

    const collectMessages = (container, contextLabel = '') => {
        const messages = [];
        container.querySelectorAll('[data-message]').forEach((messageElement, index) => {
            messageElement.classList.remove('has-validation-error');
            const type = messageElement.querySelector('.message-type')?.value || 'text';
            const body = messageElement.querySelector('.message-body')?.value?.trim() || '';
            const header = messageElement.querySelector('.message-header')?.value?.trim() || '';
            const footer = messageElement.querySelector('.message-footer')?.value?.trim() || '';

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
            } else if (type === 'list') {
                const listData = collectListData(messageElement);
                if (!listData.sections.length) {
                    return;
                }
                payload.button = listData.button;
                payload.sections = listData.sections;
                if (payload.body === '') {
                    payload.body = 'Selecciona una opción para continuar';
                }
            } else if (type === 'template') {
                const template = collectTemplateData(messageElement, contextLabel, index);
                if (!template) {
                    return;
                }
                payload.template = template;
            } else if (body === '') {
                return;
            }

            messages.push(payload);
        });
        return messages;
    };

    const collectSection = (sectionElement, defaultLabel = '') => {
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
            const titleField = sectionElement.querySelector('[data-field="title"]');
            const rawTitle = titleField?.value?.trim() || '';
            const contextLabel = rawTitle !== '' ? rawTitle : defaultLabel;
            data.messages = collectMessages(messagesContainer, contextLabel);
        }
        return data;
    };

    const collectOption = (optionElement) => {
        const option = collectSection(optionElement, 'Opción del menú');
        option.id = optionElement.querySelector('.option-id')?.value || '';
        return option;
    };

    form.addEventListener('submit', (event) => {
        resetValidationState();

        const entrySection = form.querySelector('[data-section="entry"]');
        const fallbackSection = form.querySelector('[data-section="fallback"]');
        const optionSections = Array.from(form.querySelectorAll('[data-option]'));

        const payload = {
            entry: collectSection(entrySection, 'Mensaje de bienvenida'),
            fallback: collectSection(fallbackSection, 'Fallback'),
            options: optionSections.map((element) => collectOption(element)),
        };

        if (validationErrors.length > 0) {
            event.preventDefault();
            presentValidationErrors();
            return;
        }

        flowField.value = JSON.stringify(payload);
    });
})();
</script>
