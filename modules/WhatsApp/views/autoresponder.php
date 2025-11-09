<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */
/** @var array $templates */
/** @var string|null $templatesError */

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
    'autorizo' => $getConsentButton($consent, 'autorizo') ?: 'autorizo',
    'decline' => $getConsentButton($consent, 'decline') ?: 'No',
];
$editorConsentButtons = [
    'autorizo' => $getConsentButton($editorConsent, 'autorizo') ?: 'autorizo',
    'decline' => $getConsentButton($editorConsent, 'decline') ?: 'No',
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
                            <span class="badge bg-success-light text-success"><?= $escape($consentButtons['autorizo']); ?></span>
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

                        <div class="alert alert-danger d-none" data-validation-errors role="alert"></div>

                        <div class="flow-step mb-4" data-section="entry">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-0">Mensaje de bienvenida</h5>
                                    <p class="text-muted small mb-0">Se envía al iniciar la conversación o cuando
                                        escriben "menú".</p>
                                </div>
                                <span class="small text-muted">Usa comas o saltos de línea para separar las palabras clave.</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Título interno</label>
                                    <input type="text" class="form-control" data-field="title"
                                           value="<?= $escape($editorEntry['title'] ?? 'Mensaje de bienvenida'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" class="form-control" data-field="description"
                                           value="<?= $escape($editorEntry['description'] ?? 'Primer contacto que recibe el usuario.'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Palabras clave</label>
                                    <textarea class="form-control" rows="2" data-field="keywords"
                                              placeholder="menu, hola, inicio"><?= $escape(implode(", ", $editableKeywords($editorEntry))); ?></textarea>
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
                                        <p class="text-muted small mb-0">Palabras clave que disparan esta respuesta
                                            específica.</p>
                                    </div>
                                    <span class="badge bg-success-light text-success">Opción del menú</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Título interno</label>
                                        <input type="text" class="form-control" data-field="title"
                                               value="<?= $escape($option['title'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Descripción</label>
                                        <input type="text" class="form-control" data-field="description"
                                               value="<?= $escape($option['description'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Palabras clave</label>
                                        <textarea class="form-control" rows="2" data-field="keywords"
                                                  placeholder="1, opcion 1, informacion"><?= $escape(implode(", ", $editableKeywords($option))); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Siguiente paso sugerido</label>
                                        <input type="text" class="form-control" data-field="followup"
                                               value="<?= $escape($option['followup'] ?? ''); ?>"
                                               placeholder="Ej. Responde 'menu' para volver al inicio">
                                    </div>
                                </div>
                                <div class="mt-3" data-messages>
                                    <?php foreach (($option['messages'] ?? []) as $message): ?>
                                        <?php include __DIR__ . '/partials/autoresponder-message.php'; ?>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-3"
                                        data-action="add-message">
                                    <i class="mdi mdi-plus"></i> Añadir respuesta
                                </button>
                            </div>
                        <?php endforeach; ?>

                        <div class="flow-step border-top pt-4" data-section="fallback">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-0">Fallback</h5>
                                    <p class="text-muted small mb-0">Mensaje cuando no se reconoce ninguna palabra
                                        clave.</p>
                                </div>
                                <span class="badge bg-warning text-dark">Rescate</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Título interno</label>
                                    <input type="text" class="form-control" data-field="title"
                                           value="<?= $escape($editorFallback['title'] ?? 'Sin coincidencia'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" class="form-control" data-field="description"
                                           value="<?= $escape($editorFallback['description'] ?? 'Mensaje cuando no se reconoce la solicitud.'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Palabras clave</label>
                                    <textarea class="form-control" rows="2" data-field="keywords"
                                              placeholder="sin coincidencia, ayuda"><?= $escape(implode(", ", $editableKeywords($editorFallback))); ?></textarea>
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

                        <div class="flow-step border-top pt-4" data-consent>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-0">Consentimiento y validación</h5>
                                    <p class="text-muted small mb-0">Define qué se envía antes de continuar con el flujo automático.</p>
                                </div>
                                <span class="small text-muted">Placeholders disponibles: <code>{{brand}}</code>, <code>{{terms_url}}</code>, <code>{{name}}</code>, <code>{{history_number}}</code>.</span>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Introducción (una línea por mensaje)</label>
                                <textarea class="form-control" rows="3" data-consent-field="intro_lines"><?= $escape(implode("\n", $editorConsentIntro)); ?></textarea>
                                <div class="form-text">Se envía antes de solicitar la autorización. Puedes referenciar {{brand}} o {{terms_url}}.</div>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Mensaje para solicitar la autorización</label>
                                <textarea class="form-control" rows="2" data-consent-field="consent_prompt"><?= $escape($editorConsentPrompt); ?></textarea>
                                <div class="form-text">Si incluyes {{name}}, el mensaje mencionará al paciente cuando esté disponible.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6" data-consent-wrapper>
                                    <label class="form-label">Botón de aceptación</label>
                                    <input type="text" class="form-control" data-consent-field="button_accept"
                                           value="<?= $escape($editorConsentButtons['autorizo'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6" data-consent-wrapper>
                                    <label class="form-label">Botón de rechazo</label>
                                    <input type="text" class="form-control" data-consent-field="button_decline"
                                           value="<?= $escape($editorConsentButtons['decline'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Recordatorio si no responde</label>
                                <textarea class="form-control" rows="2" data-consent-field="consent_retry"><?= $escape($editorConsentRetry); ?></textarea>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Respuesta cuando se rechaza la autorización</label>
                                <textarea class="form-control" rows="2" data-consent-field="consent_declined"><?= $escape($editorConsentDeclined); ?></textarea>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Solicitud del número de historia clínica</label>
                                <textarea class="form-control" rows="2" data-consent-field="identifier_request"><?= $escape($editorConsentIdentifierRequest); ?></textarea>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Mensaje si el número no coincide</label>
                                <textarea class="form-control" rows="2" data-consent-field="identifier_retry"><?= $escape($editorConsentIdentifierRetry); ?></textarea>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Verificación final</label>
                                <textarea class="form-control" rows="2" data-consent-field="confirmation_check"><?= $escape($editorConsentCheck); ?></textarea>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Mensaje para revisar el número detectado</label>
                                <textarea class="form-control" rows="2" data-consent-field="confirmation_review"><?= $escape($editorConsentReview); ?></textarea>
                                <div class="form-text">Incluye {{history_number}} para repetir la historia clínica ingresada.</div>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Instrucciones para continuar</label>
                                <textarea class="form-control" rows="2" data-consent-field="confirmation_menu"><?= $escape($editorConsentMenu); ?></textarea>
                            </div>
                            <div class="mb-3" data-consent-wrapper>
                                <label class="form-label">Confirmación del registro</label>
                                <textarea class="form-control" rows="2" data-consent-field="confirmation_recorded"><?= $escape($editorConsentRecorded); ?></textarea>
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
        <button type="button" class="btn btn-outline-danger" data-action="remove-button"><i class="mdi mdi-close"></i>
        </button>
    </div>
</template>


