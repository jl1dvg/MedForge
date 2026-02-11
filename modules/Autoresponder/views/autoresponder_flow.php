<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */
/** @var array $templates */
/** @var string|null $templatesError */
/** @var array $contract */
/** @var string $brand */

$escape = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$extractPlaceholders = static function (string $text): array {
    if ($text === '') {
        return [];
    }

    preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);
    if (empty($matches[1])) {
        return [];
    }

    $numbers = array_map(static fn($value) => (int) $value, $matches[1]);
    $numbers = array_values(array_filter(array_unique($numbers), static fn($value) => $value > 0));

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
$templatesJson = htmlspecialchars(json_encode($templateCatalog, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');

$rolesCatalog = [];
foreach (($roles ?? []) as $role) {
    if (!is_array($role)) {
        continue;
    }
    $roleId = isset($role['id']) ? (int) $role['id'] : 0;
    $roleName = isset($role['name']) ? trim((string) $role['name']) : '';
    if ($roleId <= 0 || $roleName === '') {
        continue;
    }
    $rolesCatalog[] = ['id' => $roleId, 'name' => $roleName];
}

$editorState = [
    'variables' => $editorFlow['variables'] ?? [],
    'scenarios' => $editorFlow['scenarios'] ?? [],
    'menu' => $editorFlow['menu'] ?? [],
    'settings' => $editorFlow['settings'] ?? [],
];

$autoresponderBootstrap = [
    'brand' => $brand,
    'flow' => $editorState,
    'contract' => $contract,
    'roles' => $rolesCatalog,
    'api' => [
        'publish' => '/whatsapp/api/flowmaker/publish',
    ],
];

$flowEditorJson = htmlspecialchars(
    json_encode($autoresponderBootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}',
    ENT_QUOTES,
    'UTF-8'
);

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
    .flow-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1.5rem;
    }
    @media (min-width: 1200px) {
        .flow-grid {
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 0.8fr);
        }
    }
    .flow-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 0.9rem;
        background: #ffffff;
        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06);
    }
    .flow-card__header {
        padding: 1rem 1.25rem 0.75rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .flow-card__body {
        padding: 1rem 1.25rem 1.25rem;
    }
    .flow-card__meta {
        font-size: 0.85rem;
        color: #64748b;
    }
    .flow-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }
</style>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Flow de WhatsApp</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item">WhatsApp</li>
                        <li class="breadcrumb-item active" aria-current="page">Flow</li>
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

        <?php if (!empty($templatesError)): ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    <strong>Plantillas:</strong> <?= $escape($templatesError); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <h4 class="box-title mb-1">Flow Builder</h4>
                        <p class="text-muted small mb-0">Diseña escenarios con cards: <strong>Cuando</strong> → <strong>Entonces</strong>. Sin JSON, sin código.</p>
                    </div>
                    <div class="flow-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="simulate-flow">
                            <i class="mdi mdi-flask-outline me-1"></i>Probar
                        </button>
                        <div class="btn-group btn-group-sm" data-scenario-mode-toggle>
                            <button type="button" class="btn btn-outline-secondary active" data-mode="simple">
                                <i class="mdi mdi-account-check-outline me-1"></i>Modo simple
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-mode="advanced">
                                <i class="mdi mdi-cog-outline me-1"></i>Avanzado
                            </button>
                        </div>
                    </div>
                </div>
                <div class="box-body">
                    <form method="post" action="/whatsapp/autoresponder" data-autoresponder-form>
                        <input type="hidden" name="template_catalog" value="<?= $templatesJson; ?>" data-template-catalog>
                        <input type="hidden" name="flow_payload" id="flow_payload" value="">
                        <script type="application/json" data-flow-bootstrap><?= $flowEditorJson; ?></script>

                        <div class="alert alert-danger d-none" data-validation-errors role="alert"></div>
                        <div class="alert alert-success d-none" data-submit-feedback role="alert"></div>

                        <div class="flow-card mb-4 flow-card--guide">
                            <div class="flow-card__header">
                                <div>
                                    <h5 class="mb-1">Guía rápida del Flow</h5>
                                    <p class="flow-card__meta mb-0">Crea el recorrido con cards: <strong>Cuando</strong> → <strong>Entonces</strong>.</p>
                                </div>
                                <span class="badge bg-info-light text-info">Tutorial</span>
                            </div>
                            <div class="flow-card__body">
                                <div class="row g-3">
                                    <div class="col-12 col-lg-7">
                                        <ol class="flow-guide">
                                            <li><strong>Escenarios:</strong> define condiciones (Cuando) y acciones (Entonces). Se ejecuta el primero que cumpla.</li>
                                            <li><strong>Menú principal:</strong> se envía cuando el usuario escribe “hola”, “menú” o similares.</li>
                                            <li><strong>Variables:</strong> conecta datos como cédula, nombre o teléfono para reutilizarlos en respuestas.</li>
                                            <li><strong>Simulador:</strong> prueba un mensaje y valida qué escenario se activaría.</li>
                                        </ol>
                                        <div class="mt-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" data-setting-free-mode>
                                                <label class="form-check-label">Modo libre (sin validaciones automáticas)</label>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                Desactiva el flujo automático de consentimiento/identificación para que todo sea controlado por escenarios.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-5">
                                        <div class="flow-example">
                                            <div class="flow-example__title">Ejemplo rápido</div>
                                            <div class="flow-example__item">
                                                <span class="badge bg-primary-light text-primary">Cuando</span>
                                                <span>El mensaje contiene “agendar”.</span>
                                            </div>
                                            <div class="flow-example__item">
                                                <span class="badge bg-success-light text-success">Entonces</span>
                                                <span>Enviar botones + guardar estado <code>agendar_cita</code>.</span>
                                            </div>
                                            <div class="flow-example__item">
                                                <span class="badge bg-warning-light text-warning">Tip</span>
                                                <span>Ordena escenarios de mayor prioridad a menor.</span>
                                            </div>
                                            <div class="flow-example__item">
                                                <span class="badge bg-secondary-light text-secondary">Variables</span>
                                                <span><code>{{brand}}</code>, <code>{{context.cedula}}</code>, <code>{{context.patient.full_name}}</code></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flow-grid">
                            <div class="flow-column">
                                <div class="flow-card mb-4 flow-card--scenarios" data-scenarios-panel>
                                    <div class="flow-card__header">
                                        <div>
                                            <h5 class="mb-1">Escenarios</h5>
                                            <p class="flow-card__meta mb-0">Define condiciones y acciones. Se evalúan de arriba hacia abajo.</p>
                                        </div>
                                        <div class="flow-actions">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="expand-all-scenarios" title="Expandir todos"><i class="mdi mdi-arrow-expand-all"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="collapse-all-scenarios" title="Colapsar todos"><i class="mdi mdi-arrow-collapse-all"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="expand-advanced-scenarios" title="Mostrar avanzados"><i class="mdi mdi-star-circle-outline"></i></button>
                                            <button type="button" class="btn btn-sm btn-primary" data-action="add-scenario">
                                                <i class="mdi mdi-plus me-1"></i>Nuevo escenario
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flow-card__body">
                                        <div class="d-flex flex-column gap-3" data-scenario-list></div>
                                    </div>
                                </div>

                                <div class="flow-card mb-4 flow-card--menu" data-menu-panel>
                                    <div class="flow-card__header">
                                        <div>
                                            <h5 class="mb-1">Menú principal</h5>
                                            <p class="flow-card__meta mb-0">Este mensaje se envía cuando el usuario escribe “hola” o “menú”.</p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reset-menu">
                                            <i class="mdi mdi-restore"></i> Restaurar
                                        </button>
                                    </div>
                                    <div class="flow-card__body" data-menu-editor></div>
                                </div>
                            </div>

                            <div class="flow-column">
                                <div class="flow-card mb-4 flow-card--stages" data-stages-panel>
                                    <div class="flow-card__header">
                                        <div>
                                            <h5 class="mb-1">Etapas del recorrido</h5>
                                            <p class="flow-card__meta mb-0">Agrupa escenarios por etapa y personaliza el nombre visible.</p>
                                        </div>
                                        <div class="flow-actions">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reset-stages">
                                                <i class="mdi mdi-restore"></i> Restaurar
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" data-action="add-stage">
                                                <i class="mdi mdi-plus me-1"></i>Agregar etapa
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flow-card__body">
                                        <div class="small text-muted mb-2">
                                            Estas etapas alimentan el selector de escenarios y el mapa del flujo. Puedes renombrarlas o agregar nuevas.
                                        </div>
                                        <div class="d-flex flex-column gap-2" data-stages-editor></div>
                                    </div>
                                </div>

                                <div class="flow-card mb-4 flow-card--variables" data-variables-panel>
                                    <div class="flow-card__header">
                                        <div>
                                            <h5 class="mb-1">Variables</h5>
                                            <p class="flow-card__meta mb-0">Define de dónde se obtiene cada dato y si debe persistir.</p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reset-variables">
                                            <i class="mdi mdi-restore"></i> Restaurar
                                        </button>
                                    </div>
                                    <div class="flow-card__body" data-variable-list></div>
                                </div>

                                <div class="flow-card flow-card--simulation" data-simulation-panel>
                                    <div class="flow-card__header">
                                        <div>
                                            <h5 class="mb-1">Simulador</h5>
                                            <p class="flow-card__meta mb-0">Prueba rápidamente qué escenario se ejecutaría.</p>
                                        </div>
                                    </div>
                                    <div class="flow-card__body">
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

                        <div class="d-flex justify-content-end mt-4">
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

<template id="stage-row-template">
    <div class="stage-row border rounded-3 p-3" data-stage-row>
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-primary-light text-primary" data-stage-type></span>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small text-muted">Nombre de etapa</label>
                        <input type="text" class="form-control form-control-sm" data-stage-label>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small text-muted">Descripción</label>
                        <input type="text" class="form-control form-control-sm" data-stage-description placeholder="¿Qué ocurre en esta etapa?">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-stage">
                <i class="mdi mdi-close"></i>
            </button>
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
