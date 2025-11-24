<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */
/** @var array $templates */
/** @var string|null $templatesError */
/** @var array $inboxMessages */

$escape = static fn(?string $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$renderLines = static fn(string $value): string => nl2br($escape($value), false);

$extractPlaceholders = static function (string $text): array {
    if ($text === '') {
        return [];
    }

    preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);
    if (empty($matches[1])) {
        return [];
    }

    $numbers = array_map(static fn($value) => (int)$value, $matches[1]);
    $numbers = array_values(array_filter(array_unique($numbers), static fn($value) => $value > 0));

    return $numbers;
};

$prepareTemplateCatalog = static function (array $templates) use ($extractPlaceholders): array {
    $catalog = [];

    foreach ($templates as $template) {
        if (!is_array($template)) {
            continue;
        }

        $name = isset($template['name']) ? trim((string)$template['name']) : '';
        $language = isset($template['language']) ? trim((string)$template['language']) : '';
        if ($name === '' || $language === '') {
            continue;
        }

        $category = isset($template['category']) ? trim((string)$template['category']) : '';
        $components = [];
        if (isset($template['components']) && is_array($template['components'])) {
            foreach ($template['components'] as $component) {
                if (!is_array($component)) {
                    continue;
                }

                $type = strtoupper(trim((string)($component['type'] ?? '')));
                if ($type === '') {
                    continue;
                }

                $entry = ['type' => $type];

                if (isset($component['format'])) {
                    $entry['format'] = strtoupper(trim((string)$component['format']));
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

                        $buttonType = strtoupper(trim((string)($button['type'] ?? '')));
                        $buttonEntry = [
                            'type' => $buttonType,
                            'index' => $index,
                            'text' => isset($button['text']) ? trim((string)$button['text']) : '',
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
    $category = strtoupper((string)($templateMeta['category'] ?? ''));
    if ($category === '') {
        $category = 'SIN CATEGORÍA';
    }
    $templateCategories[$category] = ($templateCategories[$category] ?? 0) + 1;
}
$templatesJson = htmlspecialchars(json_encode($templateCatalog, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');
$editorState = [
    'variables' => $editorFlow['variables'] ?? [],
    'scenarios' => $editorFlow['scenarios'] ?? [],
    'menu' => $editorFlow['menu'] ?? [],
];
$flowEditorJson = htmlspecialchars(json_encode($editorState, JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8');

$getConsentValue = static function (array $consent, string $key): string {
    $value = $consent[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
};

$getConsentLines = static function (array $consent): array {
    $lines = $consent['intro_lines'] ?? [];
    if (!is_array($lines)) {
        return [];
    }

    $normalized = [];
    foreach ($lines as $line) {
        if (!is_string($line)) {
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed !== '') {
            $normalized[] = $trimmed;
        }
    }

    return $normalized;
};

$getConsentButton = static function (array $consent, string $key) use ($getConsentValue): string {
    $buttons = $consent['buttons'] ?? [];
    if (!is_array($buttons)) {
        return '';
    }

    $value = $buttons[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
};

$missingCredentials = [];
if (empty($config['phone_number_id'])) {
    $missingCredentials[] = 'ID del número de teléfono';
}
if (empty($config['business_account_id'])) {
    $missingCredentials[] = 'ID de la cuenta de empresa';
}
if (empty($config['access_token'])) {
    $missingCredentials[] = 'Token de acceso';
}
$hasRegistryLookup = trim((string)($config['registry_lookup_url'] ?? '')) !== '';

$renderPreviewMessage = static function ($message) use ($escape, $renderLines): string {
    if (!is_array($message)) {
        return '<p class="mb-0">' . $renderLines((string)$message) . '</p>';
    }

    $body = $renderLines((string)($message['body'] ?? ''));
    $type = $message['type'] ?? 'text';
    $badge = '';
    if ($type === 'buttons') {
        $badge = '<span class="badge bg-primary-light text-primary ms-1">Botones</span>';
    } elseif ($type === 'list') {
        $badge = '<span class="badge bg-success-light text-success ms-1">Lista</span>';
    } elseif ($type === 'template') {
        $badge = '<span class="badge bg-info-light text-info ms-1">Plantilla</span>';
    } elseif ($type === 'image') {
        $badge = '<span class="badge bg-purple-light text-purple ms-1">Imagen</span>';
    } elseif ($type === 'document') {
        $badge = '<span class="badge bg-indigo-light text-indigo ms-1">Documento</span>';
    } elseif ($type === 'location') {
        $badge = '<span class="badge bg-secondary-light text-secondary ms-1">Ubicación</span>';
    }

    $extras = [];
    if (!empty($message['header'])) {
        $extras[] = '<div class="small text-muted">Encabezado: ' . $renderLines((string)$message['header']) . '</div>';
    }
    if (!empty($message['footer'])) {
        $extras[] = '<div class="small text-muted">Pie: ' . $renderLines((string)$message['footer']) . '</div>';
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

    if ($type === 'image') {
        $link = isset($message['link']) ? $escape((string)$message['link']) : '';
        if ($link !== '') {
            $extras[] = '<div class="small text-muted">URL de imagen: <a href="' . $link . '" target="_blank" rel="noopener">' . $link . '</a></div>';
        }
        if (!empty($message['caption'])) {
            $extras[] = '<div class="small text-muted">Pie: ' . $renderLines((string)$message['caption']) . '</div>';
        }
    }

    if ($type === 'document') {
        $link = isset($message['link']) ? $escape((string)$message['link']) : '';
        $filename = isset($message['filename']) ? $escape((string)$message['filename']) : '';
        if ($link !== '') {
            $label = $filename !== '' ? $filename : $link;
            $extras[] = '<div class="small text-muted">Archivo: <a href="' . $link . '" target="_blank" rel="noopener">' . $label . '</a></div>';
        }
        if (!empty($message['caption'])) {
            $extras[] = '<div class="small text-muted">Descripción: ' . $renderLines((string)$message['caption']) . '</div>';
        }
    }

    if ($type === 'location') {
        $latitude = isset($message['latitude']) ? (float)$message['latitude'] : null;
        $longitude = isset($message['longitude']) ? (float)$message['longitude'] : null;
        if ($latitude !== null && $longitude !== null) {
            $coords = $escape(number_format($latitude, 6)) . ', ' . $escape(number_format($longitude, 6));
            $extras[] = '<div class="small text-muted">Coordenadas: ' . $coords . '</div>';
        }
        if (!empty($message['name'])) {
            $extras[] = '<div class="small text-muted">Nombre: ' . $escape((string)$message['name']) . '</div>';
        }
        if (!empty($message['address'])) {
            $extras[] = '<div class="small text-muted">Dirección: ' . $renderLines((string)$message['address']) . '</div>';
        }
    }

    if ($type === 'template' && !empty($message['template']) && is_array($message['template'])) {
        $template = $message['template'];
        $details = [];
        if (!empty($template['name'])) {
            $details[] = '<div><span class="fw-600">Nombre:</span> ' . $escape((string)$template['name']) . '</div>';
        }
        if (!empty($template['language'])) {
            $details[] = '<div><span class="fw-600">Idioma:</span> ' . $escape((string)$template['language']) . '</div>';
        }
        if (!empty($template['category'])) {
            $details[] = '<div><span class="fw-600">Categoría:</span> ' . $escape((string)$template['category']) . '</div>';
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

    $auto = array_filter(array_map(static fn($value) => is_string($value) ? trim($value) : '', $auto));

    return array_values(array_filter($keywords, static fn($keyword) => $keyword !== '' && !in_array($keyword, $auto, true)));
};

$entry = $flow['entry'] ?? [];
$options = $flow['options'] ?? [];
$fallback = $flow['fallback'] ?? [];
$meta = $flow['meta'] ?? [];
$consent = is_array($flow['consent'] ?? null) ? $flow['consent'] : [];
$brand = $meta['brand'] ?? ($config['brand'] ?? 'MedForge');
$webhookUrl = $config['webhook_url'] ?? (rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/') . '/whatsapp/webhook');
$webhookToken = trim((string)($config['webhook_verify_token'] ?? 'medforge-whatsapp'));

$editorEntry = $editorFlow['entry'] ?? [];
$editorOptions = $editorFlow['options'] ?? [];
$editorFallback = $editorFlow['fallback'] ?? [];
$editorConsent = is_array($editorFlow['consent'] ?? null) ? $editorFlow['consent'] : [];

$consentIntro = $getConsentLines($consent);
$editorConsentIntro = $getConsentLines($editorConsent);

$consentPrompt = $getConsentValue($consent, 'consent_prompt');
$consentRetry = $getConsentValue($consent, 'consent_retry');
$consentDeclined = $getConsentValue($consent, 'consent_declined');
$consentIdentifierRequest = $getConsentValue($consent, 'identifier_request');
$consentIdentifierRetry = $getConsentValue($consent, 'identifier_retry');
$consentCheck = $getConsentValue($consent, 'confirmation_check');
$consentReview = $getConsentValue($consent, 'confirmation_review');
$consentMenu = $getConsentValue($consent, 'confirmation_menu');
$consentRecorded = $getConsentValue($consent, 'confirmation_recorded');

$editorConsentPrompt = $getConsentValue($editorConsent, 'consent_prompt');
$editorConsentRetry = $getConsentValue($editorConsent, 'consent_retry');
$editorConsentDeclined = $getConsentValue($editorConsent, 'consent_declined');
$editorConsentIdentifierRequest = $getConsentValue($editorConsent, 'identifier_request');
$editorConsentIdentifierRetry = $getConsentValue($editorConsent, 'identifier_retry');
$editorConsentCheck = $getConsentValue($editorConsent, 'confirmation_check');
$editorConsentReview = $getConsentValue($editorConsent, 'confirmation_review');
$editorConsentMenu = $getConsentValue($editorConsent, 'confirmation_menu');
$editorConsentRecorded = $getConsentValue($editorConsent, 'confirmation_recorded');

$consentButtons = [
    'accept' => $getConsentButton($consent, 'accept') ?: 'Sí, autorizo',
    'decline' => $getConsentButton($consent, 'decline') ?: 'No, gracias',
];
$editorConsentButtons = [
    'accept' => $getConsentButton($editorConsent, 'accept') ?: 'Sí, autorizo',
    'decline' => $getConsentButton($editorConsent, 'decline') ?: 'No, gracias',
];

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

        <div class="col-12 col-xl-4">
            <div class="box mb-4">
                <div class="box-header with-border d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <h4 class="box-title mb-1">Guía rápida del autorespondedor</h4>
                        <p class="text-muted small mb-0">Sigue estos recursos antes de publicar cambios.</p>
                    </div>
                    <span class="badge bg-light text-muted fw-600">Nuevo</span>
                </div>
                <div class="box-body">
                    <div class="ratio ratio-16x9 rounded overflow-hidden mb-3 bg-dark position-relative">
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <i class="mdi mdi-play-circle-outline text-white fs-1"></i>
                            <div class="text-white-50 small">Video introductorio (2 min)</div>
                        </div>
                        <a class="stretched-link" target="_blank" rel="noopener"
                           href="https://medforge.help/whatsapp/autoresponder" aria-label="Ver video de introducción"></a>
                    </div>
                    <ul class="list-unstyled small mb-3 d-flex flex-column gap-2">
                        <li class="d-flex align-items-start gap-2">
                            <i class="mdi mdi-check-circle-outline text-success fs-5"></i>
                            <div>
                                <span class="fw-600">Checklist de publicación</span>
                                <div class="text-muted">Verifica variables, escenarios y menú antes de guardar.</div>
                            </div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="mdi mdi-file-document-outline text-primary fs-5"></i>
                            <div>
                                <span class="fw-600">Guía paso a paso</span>
                                <div class="text-muted">Aprende cómo se evalúan los mensajes entrantes y el orden de ejecución.</div>
                            </div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="mdi mdi-whatsapp text-success fs-5"></i>
                            <div>
                                <span class="fw-600">Buenas prácticas de Meta</span>
                                <div class="text-muted">Plantillas, tiempos de respuesta y políticas actualizadas.</div>
                            </div>
                        </li>
                    </ul>
                    <div class="d-flex flex-column gap-2">
                        <a class="btn btn-sm btn-primary" target="_blank" rel="noopener"
                           href="https://medforge.help/whatsapp/autoresponder#checklist">
                            <i class="mdi mdi-clipboard-check-outline me-1"></i>Ver checklist
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                           href="https://www.facebook.com/business/help/2055875911190067">
                            <i class="mdi mdi-book-open-variant-outline me-1"></i>Políticas de plantillas Meta
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($missingCredentials)): ?>
                <div class="alert alert-warning mb-4" role="alert">
                    <strong>Completa la configuración de Meta.</strong>
                    <div class="small mb-0">Faltan: <?= $escape(implode(', ', $missingCredentials)); ?>. Actualiza los campos en <a href="/settings?section=whatsapp" class="alert-link">Ajustes → WhatsApp</a> para habilitar las plantillas y mensajes interactivos.</div>
                </div>
            <?php endif; ?>

            <?php if ($hasRegistryLookup): ?>
                <div class="alert alert-info mb-4" role="alert">
                    <strong>Consulta externa habilitada.</strong>
                    <div class="small mb-0">Si aún no cuentas con un endpoint oficial del Registro Civil, deja el campo <code>whatsapp_registry_lookup_url</code> vacío en <a href="/settings?section=whatsapp" class="alert-link">Ajustes → WhatsApp</a> para trabajar solo con la base local.</div>
                </div>
            <?php endif; ?>

            <div class="box mb-4" data-inbox-root data-endpoint="/whatsapp/api/inbox" data-poll-interval="6000">
                <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="box-title mb-0">Mensajes recientes</h4>
                        <p class="text-muted mb-0 small">Visualiza el último intercambio sin recargar la página.</p>
                    </div>
                    <span class="badge bg-light text-muted fw-600" data-inbox-count>0</span>
                </div>
                <div class="box-body">
                    <div class="wa-inbox-empty text-muted small" data-inbox-empty>
                        Aún no se registran mensajes.
                    </div>
                    <ul class="wa-inbox-list list-unstyled mb-0" data-inbox-list></ul>
                </div>
                <script type="application/json" data-inbox-bootstrap><?= $inboxBootstrapJson; ?></script>
            </div>

            <div class="box mb-4">
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
                    <p class="small text-muted mb-0">Recuerda habilitar las suscripciones de mensajes entrantes para
                        este número en el panel de Meta.</p>
                </div>
            </div>

            <?php if (!empty($templatesError)): ?>
                <div class="alert alert-warning mb-4">
                    <strong>No pudimos sincronizar las plantillas:</strong> <?= $escape($templatesError); ?>
                </div>
            <?php endif; ?>

            <div class="box mb-4">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Plantillas disponibles</h4>
                    <p class="text-muted mb-0 small">Reutiliza tus mensajes aprobados para flujos automáticos.</p>
                </div>
                <div class="box-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-600 display-6 mb-0"><?= (int)$templateCount; ?></div>
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
                                    <span class="badge bg-light text-dark"><?= (int)$count; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="small text-muted mb-0">Aún no se han sincronizado plantillas. Puedes crearlas desde
                            Meta o desde el administrador de plantillas.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="box mb-4">
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
                                        <div class="small text-muted">
                                            Sugerencia: <?= $escape($option['followup']); ?></div>
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

            <div class="box mb-4">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Consentimiento y protección de datos</h4>
                    <p class="text-muted mb-0 small">Mensajes previos a validar la historia clínica.</p>
                </div>
                <div class="box-body d-flex flex-column gap-3">
                    <div>
                        <div class="small text-uppercase text-muted fw-600">Introducción</div>
                        <?php if (!empty($consentIntro)): ?>
                            <ul class="small ps-3 mb-0">
                                <?php foreach ($consentIntro as $line): ?>
                                    <li><?= $escape($line); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="small text-muted mb-0">Se utilizará el mensaje predeterminado antes de solicitar la autorización.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="small text-uppercase text-muted fw-600">Solicitud de autorización</div>
                        <p class="small mb-1"><?= $escape($consentPrompt); ?></p>
                        <div class="d-flex gap-2 flex-wrap small">
                            <span class="badge bg-success-light text-success"><?= $escape($consentButtons['accept']); ?></span>
                            <span class="badge bg-danger-light text-danger"><?= $escape($consentButtons['decline']); ?></span>
                        </div>
                        <?php if ($consentRetry !== ''): ?>
                            <p class="small text-muted mb-0 mt-2">Recordatorio: <?= $escape($consentRetry); ?></p>
                        <?php endif; ?>
                        <?php if ($consentDeclined !== ''): ?>
                            <p class="small text-muted mb-0">Respuesta ante rechazo: <?= $escape($consentDeclined); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="small text-uppercase text-muted fw-600">Solicitud de historia clínica</div>
                        <p class="small mb-1"><?= $escape($consentIdentifierRequest); ?></p>
                        <?php if ($consentIdentifierRetry !== ''): ?>
                            <p class="small text-muted mb-0">Cuando no hay coincidencias: <?= $escape($consentIdentifierRetry); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="small text-uppercase text-muted fw-600">Confirmación final</div>
                        <ul class="small ps-3 mb-0 d-flex flex-column gap-1">
                            <li><?= $escape($consentCheck); ?></li>
                            <li><?= $escape($consentReview); ?></li>
                            <li><?= $escape($consentMenu); ?></li>
                            <li><?= $escape($consentRecorded); ?></li>
                        </ul>
                    </div>
                    <p class="small text-muted mb-0">Puedes utilizar <code>{{brand}}</code>, <code>{{terms_url}}</code> y <code>{{history_number}}</code> para personalizar los mensajes.</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Editar flujo</h4>
                    <p class="text-muted mb-0 small">Actualiza palabras clave, mensajes y botones interactivos. Los
                        cambios se guardan al enviar.</p>
                </div>
                <div class="box-body">
                    <form method="post" action="/whatsapp/autoresponder" data-autoresponder-form>
                        <input type="hidden" name="template_catalog" value="<?= $templatesJson; ?>"
                               data-template-catalog>
                        <input type="hidden" name="flow_payload" id="flow_payload" value="">
                        <script type="application/json" data-flow-bootstrap><?= $flowEditorJson; ?></script>

                        <div class="alert alert-danger d-none" data-validation-errors role="alert"></div>

                        <div class="box mb-4" data-variables-panel>
                            <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h5 class="mb-1">Variables y fuentes de datos</h5>
                                    <p class="text-muted small mb-0">Configura de dónde se obtiene cada valor y si debe guardarse.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reset-variables">
                                    <i class="mdi mdi-restore"></i> Restaurar
                                </button>
                            </div>
                            <div class="box-body" data-variable-list></div>
                        </div>

                        <div class="box mb-4" data-scenarios-panel>
                            <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h5 class="mb-1">Escenarios</h5>
                                    <p class="text-muted small mb-0">Cada escenario evalúa condiciones y dispara acciones en orden.</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="simulate-flow">
                                        <i class="mdi mdi-flask-outline me-1"></i> Simular
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" data-action="add-scenario">
                                        <i class="mdi mdi-plus me-1"></i> Nuevo escenario
                                    </button>
                                </div>
                            </div>
                        <div class="box-body">
                            <div class="alert alert-info d-flex align-items-start gap-2 small">
                                <i class="mdi mdi-lightbulb-on-outline fs-4 text-info"></i>
                                <div>
                                    <strong>Tip:</strong> describe qué resuelve cada escenario, añade condiciones claras y confirma las acciones esperadas. Puedes duplicar ejemplos sugeridos o crear los tuyos desde cero.
                                </div>
                            </div>
                            <div class="scenario-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="apply-journey-preset">
                                        <i class="mdi mdi-timeline-clock-outline me-1"></i> Aplicar recorrido paciente
                                    </button>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-action="expand-all-scenarios" title="Expandir todos los escenarios" aria-label="Expandir todos los escenarios">
                                            <i class="mdi mdi-arrow-expand-all"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-action="collapse-all-scenarios" title="Colapsar todos los escenarios" aria-label="Colapsar todos los escenarios">
                                            <i class="mdi mdi-arrow-collapse-all"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-action="expand-advanced-scenarios" title="Expandir solo los escenarios avanzados" aria-label="Expandir solo los escenarios avanzados">
                                            <i class="mdi mdi-star-circle-outline"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="btn-group btn-group-sm" data-scenario-mode-toggle>
                                    <button type="button" class="btn btn-outline-secondary active" data-mode="simple">
                                        <i class="mdi mdi-account-check-outline me-1"></i> Modo simple
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-mode="advanced">
                                        <i class="mdi mdi-cog-outline me-1"></i> Modo avanzado
                                    </button>
                                </div>
                            </div>
                            <div class="row g-3 align-items-stretch" data-scenarios-layout>
                                <div class="col-12 col-xxl-8">
                                    <div class="d-flex flex-column gap-3" data-scenario-canvas>
                                        <div class="card border-0 shadow-sm" data-scenario-overview>
                                            <div class="card-body py-3">
                                                <h6 class="fw-600 mb-2">Orden de evaluación</h6>
                                                <p class="text-muted small mb-3">Los escenarios se evalúan de arriba hacia abajo. El primero que cumpla todas sus condiciones será el que responda al contacto.</p>
                                                <div class="d-flex flex-column gap-2" data-scenario-summary></div>
                                            </div>
                                        </div>
                                        <div class="card border-0 shadow-sm" data-journey-map-card>
                                            <div class="card-body py-3">
                                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                                                    <div>
                                                        <h6 class="fw-600 mb-1">Mapa del recorrido</h6>
                                                        <p class="text-muted small mb-0">Visualiza cómo progresa el paciente entre etapas. Haz clic en un paso para saltar a su configuración.</p>
                                                    </div>
                                                    <div class="journey-map__legend d-flex align-items-center gap-3 small text-muted">
                                                        <span class="d-inline-flex align-items-center gap-1"><span class="journey-map__legend-dot journey-map__legend-dot--active"></span> Abierto</span>
                                                        <span class="d-inline-flex align-items-center gap-1"><span class="journey-map__legend-dot journey-map__legend-dot--invalid"></span> Requiere atención</span>
                                                    </div>
                                                </div>
                                                <div class="journey-map__viewport">
                                                    <div class="journey-map" data-journey-map></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card border-0 shadow-sm" data-suggested-scenarios></div>
                                        <div data-scenario-list></div>
                                    </div>
                                </div>
                                <div class="col-12 col-xxl-4">
                                    <div class="card border-0 shadow-sm h-100" data-simulation-panel>
                                        <div class="card-body d-flex flex-column">
                                            <h6 class="fw-600 mb-1">Simulador paso a paso</h6>
                                            <p class="text-muted small mb-3">Escribe uno o más mensajes y revisa qué escenario se activaría, con sus condiciones evaluadas.</p>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted">Mensaje de prueba</label>
                                                <textarea class="form-control form-control-sm" rows="3" placeholder="Hola, quiero agendar una cita" data-simulation-input></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small text-muted">Usar mensaje reciente</label>
                                                <select class="form-select form-select-sm" data-simulation-replay>
                                                    <option value="">Selecciona un mensaje de la bandeja</option>
                                                </select>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <div class="form-check form-switch small">
                                                        <input class="form-check-input" type="checkbox" checked data-simulation-first-time>
                                                        <label class="form-check-label">Es primera vez</label>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="form-check form-switch small">
                                                        <input class="form-check-input" type="checkbox" data-simulation-has-consent>
                                                        <label class="form-check-label">Tiene consentimiento</label>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small text-muted mb-1">Estado actual</label>
                                                    <input type="text" class="form-control form-control-sm" value="inicio" data-simulation-state>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small text-muted mb-1">Campo pendiente</label>
                                                    <input type="text" class="form-control form-control-sm" placeholder="cedula" data-simulation-awaiting>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small text-muted mb-1">Minutos desde última interacción</label>
                                                    <input type="number" class="form-control form-control-sm" value="999" min="0" data-simulation-minutes>
                                                </div>
                                                <div class="col-6">
                                                    <div class="form-check form-switch small mt-3">
                                                        <input class="form-check-input" type="checkbox" data-simulation-patient-found>
                                                        <label class="form-check-label">Paciente localizado</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-primary flex-grow-1" data-action="run-simulation">
                                                    <i class="mdi mdi-play-circle-outline me-1"></i>Probar flujo
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reset-simulation">
                                                    <i class="mdi mdi-delete-outline"></i>
                                                </button>
                                            </div>
                                            <div class="mt-3 border-top pt-3 overflow-auto" style="max-height: 240px;" data-simulation-log></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="box" data-menu-panel>
                        <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                    <h5 class="mb-1">Constructor de menú</h5>
                                    <p class="text-muted small mb-0">Define el mensaje principal y las opciones disponibles.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reset-menu">
                                    <i class="mdi mdi-restore"></i> Restaurar
                                </button>
                            </div>
                            <div class="box-body" data-menu-editor>
                                <div class="alert alert-info small" role="alert">
                                    Diseña el mensaje de bienvenida con botones o listas interactivas. Agrega etiquetas y palabras clave para que el sistema identifique cada intención.
                                </div>
                            </div>
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

<template id="variable-row-template">
    <div class="row g-3 align-items-center mb-3" data-variable-row>
        <div class="col-md-3">
            <div class="fw-600" data-variable-key></div>
            <div class="text-muted small" data-variable-description></div>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Etiqueta</label>
            <input type="text" class="form-control form-control-sm" data-variable-label>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Fuente</label>
            <select class="form-select form-select-sm" data-variable-source></select>
        </div>
        <div class="col-md-2">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" data-variable-persist>
                <label class="form-check-label small">Persistir</label>
            </div>
        </div>
    </div>
</template>

<template id="scenario-card-template">
    <div class="scenario-card border rounded-3 shadow-sm p-3" data-scenario>
        <input type="hidden" data-scenario-id>
        <div class="scenario-card__header d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="d-flex align-items-start gap-2 flex-grow-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="toggle-scenario" aria-expanded="false">
                    <i class="mdi mdi-chevron-right"></i>
                </button>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-600" data-scenario-title>Nuevo escenario</span>
                        <span class="badge bg-secondary-subtle text-secondary" data-scenario-stage-label>Personalizado</span>
                    </div>
                    <div class="text-muted small" data-scenario-summary-preview></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="drag-handle text-muted" data-drag-handle title="Arrastra para reordenar">
                    <i class="mdi mdi-drag-vertical"></i>
                </span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" data-action="move-up"><i class="mdi mdi-arrow-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-action="move-down"><i class="mdi mdi-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger" data-action="remove-scenario"><i class="mdi mdi-close"></i></button>
                </div>
            </div>
        </div>
        <div class="scenario-card__body mt-3" data-scenario-body>
            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <label class="form-label small text-muted mb-1">Nombre del escenario</label>
                    <input type="text" class="form-control form-control-sm" placeholder="Nombre del escenario" data-scenario-name>
                </div>
                <div class="col-lg-5">
                    <label class="form-label small text-muted mb-1">Etapa del recorrido</label>
                    <select class="form-select form-select-sm" data-scenario-stage></select>
                    <div class="text-muted small mt-1" data-scenario-stage-help></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted mb-1">Descripción</label>
                <textarea class="form-control form-control-sm" rows="2" placeholder="Describe el objetivo" data-scenario-description></textarea>
            </div>
            <div class="form-check form-switch form-switch-sm mb-3">
                <input class="form-check-input" type="checkbox" data-scenario-intercept>
                <label class="form-check-label small">Responder antes que el menú de bienvenida</label>
            </div>
            <p class="text-muted small" data-scenario-intercept-help>
                Cuando está desactivado, el mensaje de bienvenida y el menú responderán primero a palabras como 'hola' o 'menú'.
            </p>
            <div class="scenario-conditions mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Condiciones</h6>
                    <button type="button" class="btn btn-xs btn-outline-primary" data-action="add-condition"><i class="mdi mdi-plus"></i> Añadir condición</button>
                </div>
                <div data-condition-list></div>
            </div>
            <div class="scenario-actions">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Acciones</h6>
                    <button type="button" class="btn btn-xs btn-outline-primary" data-action="add-action"><i class="mdi mdi-plus"></i> Añadir acción</button>
                </div>
                <div data-action-list></div>
            </div>
        </div>
    </div>
</template>

<template id="condition-row-template">
    <div class="card border-1 border-light bg-light-subtle p-3 mb-2" data-condition>
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <select class="form-select form-select-sm" data-condition-type></select>
            </div>
            <div class="col-md-7" data-condition-fields></div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-condition"><i class="mdi mdi-close"></i></button>
            </div>
        </div>
        <div class="mt-2">
            <div class="text-muted small" data-condition-help></div>
        </div>
    </div>
</template>

<template id="action-row-template">
    <div class="card border-1 border-secondary-subtle p-3 mb-2" data-action>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <div class="flex-grow-1">
                <select class="form-select form-select-sm mb-2" data-action-type></select>
                <div data-action-fields></div>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-action="action-up"><i class="mdi mdi-arrow-up"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-action="action-down"><i class="mdi mdi-arrow-down"></i></button>
                <button type="button" class="btn btn-outline-danger" data-action="remove-action"><i class="mdi mdi-close"></i></button>
            </div>
        </div>
        <div class="mt-2">
            <div class="text-muted small" data-action-help></div>
        </div>
    </div>
</template>

<template id="menu-option-template">
    <div class="border rounded-3 p-3 mb-3" data-menu-option>
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
            <div class="flex-grow-1">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Identificador</label>
                        <input type="text" class="form-control form-control-sm" data-option-id>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Título</label>
                        <input type="text" class="form-control form-control-sm" data-option-title>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted">Palabras clave</label>
                        <input type="text" class="form-control form-control-sm" data-option-keywords placeholder="menu, opcion">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-menu-option"><i class="mdi mdi-close"></i></button>
        </div>
        <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-600">Acciones</span>
                <button type="button" class="btn btn-xs btn-outline-primary" data-action="add-option-action"><i class="mdi mdi-plus"></i> Añadir acción</button>
            </div>
            <div data-option-action-list></div>
        </div>
    </div>
</template>

<template id="button-row-template">
    <div class="row g-2 align-items-center mb-2" data-button-row>
        <div class="col-md-6">
            <input type="text" class="form-control form-control-sm" placeholder="Título" data-button-title>
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" placeholder="Identificador" data-button-id>
        </div>
        <div class="col-md-2 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-button"><i class="mdi mdi-close"></i></button>
        </div>
    </div>
</template>

<template id="context-row-template">
    <div class="row g-2 align-items-center mb-2" data-context-row>
        <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" placeholder="Clave" data-context-key>
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" placeholder="Valor" data-context-value>
        </div>
        <div class="col-md-2 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-context"><i class="mdi mdi-close"></i></button>
        </div>
    </div>
</template>

<template id="menu-list-section-template">
    <div class="border rounded-3 p-3 mb-3" data-list-section>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Título de la sección</label>
                <input type="text" class="form-control form-control-sm" data-section-title placeholder="Opciones generales">
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-action="section-up"><i class="mdi mdi-arrow-up"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-action="section-down"><i class="mdi mdi-arrow-down"></i></button>
                <button type="button" class="btn btn-outline-danger" data-action="remove-section"><i class="mdi mdi-close"></i></button>
            </div>
        </div>
        <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-600">Opciones</span>
                <button type="button" class="btn btn-xs btn-outline-primary" data-action="add-row"><i class="mdi mdi-plus"></i> Añadir opción</button>
            </div>
            <div data-section-rows></div>
        </div>
    </div>
</template>

<template id="menu-list-row-template">
    <div class="row g-2 align-items-center mb-2" data-list-row>
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" placeholder="Identificador" data-row-id>
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" placeholder="Título" data-row-title>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" placeholder="Descripción" data-row-description>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-row"><i class="mdi mdi-close"></i></button>
        </div>
    </div>
</template>


$inboxBootstrapJson = htmlspecialchars(json_encode($inboxMessages ?? [], JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');
