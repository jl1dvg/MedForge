<?php
/** @var array $config */
/** @var array $flow */
/** @var array $editorFlow */
/** @var array|null $status */
/** @var array $templates */
/** @var string|null $templatesError */

$escape = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$renderLines = static fn(string $value): string => nl2br($escape($value), false);

$brand = $escape($editorFlow['meta']['brand'] ?? ($config['brand'] ?? 'MedForge'));
$flowJson = $escape(json_encode($editorFlow, JSON_UNESCAPED_UNICODE) ?: '{}');

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
$keywordLegend = $flow['meta']['keywordLegend'] ?? [];
?>

<div class="page-header">
    <h1 class="page-title">Flujo de autorespuesta por escenarios</h1>
    <p class="text-muted mb-0">Configura palabras clave globales, accesos directos y escenarios condicionales para el asistente virtual de WhatsApp.</p>
</div>

<?php if (!empty($status)): ?>
    <div class="alert alert-<?= $escape($status['type'] ?? 'info'); ?>">
        <?= $renderLines((string)($status['message'] ?? '')); ?>
    </div>
<?php endif; ?>

<?php if (!empty($missingCredentials)): ?>
    <div class="alert alert-warning">
        <strong>Faltan credenciales para enviar mensajes:</strong>
        <?= $renderLines(implode(', ', $missingCredentials)); ?>.
        Completa la configuración del conector antes de activar el flujo.
    </div>
<?php endif; ?>

<?php if ($templatesError !== null): ?>
    <div class="alert alert-danger">
        <strong>No se pudo obtener la lista de plantillas:</strong> <?= $escape($templatesError); ?>
    </div>
<?php endif; ?>

<form method="post" action="/whatsapp/autoresponder" data-autoresponder-form>
    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()); ?>">
    <input type="hidden" name="flow_payload" id="flow_payload" value="">
    <input type="hidden" data-flow-source value="<?= $flowJson; ?>">
    <input type="hidden" data-template-catalog value="<?= $escape(json_encode($templates, JSON_UNESCAPED_UNICODE) ?: '[]'); ?>">

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Palabras clave iniciales</h2>
        </div>
        <div class="card-body">
            <p class="text-muted">Define los términos que activan el menú principal. Escríbelos separados por comas o líneas.</p>
            <textarea class="form-control" rows="2" data-entry-keywords placeholder="Ej: menu, inicio, hola"></textarea>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="card-title h5 mb-0">Accesos directos</h2>
                <small class="text-muted">Palabras clave disponibles en cualquier momento que envían al usuario a un escenario específico.</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-shortcut"><i class="mdi mdi-plus"></i> Añadir acceso</button>
        </div>
        <div class="card-body" data-shortcut-list></div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="card-title h5 mb-0">Escenarios y pasos</h2>
                <small class="text-muted">Cada escenario puede ser un mensaje, una captura de datos o una decisión condicional.</small>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-action="add-node"><i class="mdi mdi-plus"></i> Añadir escenario</button>
        </div>
        <div class="card-body" data-node-list></div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Respuesta por defecto</h2>
        </div>
        <div class="card-body" data-fallback-editor data-fallback-title="<?= $escape($editorFlow['fallback']['title'] ?? 'Sin coincidencia'); ?>" data-fallback-description="<?= $escape($editorFlow['fallback']['description'] ?? 'Mensaje cuando no hay coincidencias.'); ?>">
            <p class="text-muted">Mensaje enviado cuando ninguna palabra coincide o no se cumple ninguna condición.</p>
            <div data-fallback-messages></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Resumen de palabras clave</h2>
        </div>
        <div class="card-body">
            <?php if (empty($keywordLegend)): ?>
                <p class="text-muted mb-0">Los accesos directos se mostrarán aquí cuando los configures.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($keywordLegend as $title => $keywords): ?>
                        <li class="mb-2">
                            <strong><?= $escape($title); ?>:</strong>
                            <?php foreach ($keywords as $keyword): ?>
                                <span class="badge bg-light text-body border me-1"><?= $escape($keyword); ?></span>
                            <?php endforeach; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="/whatsapp/autoresponder" class="btn btn-outline-secondary">Descartar cambios</a>
        <button type="submit" class="btn btn-primary">Guardar flujo</button>
    </div>
</form>

<div class="d-none" data-validation-errors></div>

<template id="shortcut-template">
    <div class="border rounded-3 p-3 mb-3" data-shortcut>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label-sm">Identificador</label>
                <input type="text" class="form-control form-control-sm" data-field="id" placeholder="Ej: menu-shortcut">
            </div>
            <div class="col-md-4">
                <label class="form-label-sm">Título</label>
                <input type="text" class="form-control form-control-sm" data-field="title" placeholder="Título del acceso">
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Escenario destino</label>
                <input type="text" class="form-control form-control-sm" data-field="target" placeholder="ID del escenario">
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-sm btn-outline-danger w-100" data-action="remove-shortcut"><i class="mdi mdi-close"></i></button>
            </div>
        </div>
        <label class="form-label-sm">Palabras clave (separadas por comas)</label>
        <input type="text" class="form-control form-control-sm mb-2" data-field="keywords" placeholder="Ej: menu, inicio">
        <label class="form-label-sm">Limpiar contexto (opcional)</label>
        <input type="text" class="form-control form-control-sm" data-field="clear_context" placeholder="Ej: hc_number, patient">
    </div>
</template>

<template id="node-template">
    <div class="border rounded-3 mb-3" data-node>
        <div class="bg-light border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 w-100">
                <div class="flex-fill">
                    <label class="form-label-sm mb-0">Identificador</label>
                    <input type="text" class="form-control form-control-sm" data-field="id" placeholder="Ej: patient-intro">
                </div>
                <div class="flex-fill">
                    <label class="form-label-sm mb-0">Tipo</label>
                    <select class="form-select form-select-sm" data-field="type">
                        <option value="message">Mensaje</option>
                        <option value="input">Captura de dato</option>
                        <option value="decision">Decisión condicional</option>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger ms-lg-2" data-action="remove-node"><i class="mdi mdi-trash-can"></i></button>
            </div>
        </div>
        <div class="p-3">
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label-sm">Título</label>
                    <input type="text" class="form-control form-control-sm" data-field="title" placeholder="Descripción corta">
                </div>
                <div class="col-md-6">
                    <label class="form-label-sm">Descripción</label>
                    <input type="text" class="form-control form-control-sm" data-field="description" placeholder="Uso interno opcional">
                </div>
            </div>

            <div data-node-section="message">
                <div class="mb-3">
                    <label class="form-label-sm">Mensajes enviados</label>
                    <div data-message-list></div>
                    <button type="button" class="btn btn-xs btn-outline-primary mt-2" data-action="add-message"><i class="mdi mdi-plus"></i> Añadir mensaje</button>
                </div>

                <div class="border rounded-3 p-3" data-response-list-container>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="form-label-sm mb-0">Respuestas esperadas</span>
                        <button type="button" class="btn btn-xs btn-outline-secondary" data-action="add-response"><i class="mdi mdi-plus"></i> Añadir respuesta</button>
                    </div>
                    <div data-response-list></div>
                </div>

                <div class="mt-3" data-next-container>
                    <label class="form-label-sm">Avanzar automáticamente al escenario</label>
                    <input type="text" class="form-control form-control-sm" data-field="next" placeholder="ID del escenario siguiente">
                </div>
            </div>

            <div data-node-section="input" class="d-none">
                <div class="mb-3">
                    <label class="form-label-sm">Mensajes de solicitud</label>
                    <div data-message-list></div>
                    <button type="button" class="btn btn-xs btn-outline-primary mt-2" data-action="add-message"><i class="mdi mdi-plus"></i> Añadir mensaje</button>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label-sm">Campo de contexto</label>
                        <input type="text" class="form-control form-control-sm" data-field="input.field" placeholder="Ej: hc_number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-sm">Normalización</label>
                        <select class="form-select form-select-sm" data-field="input.normalize">
                            <option value="trim">Texto tal cual</option>
                            <option value="digits">Solo dígitos</option>
                            <option value="uppercase">Mayúsculas</option>
                            <option value="lowercase">Minúsculas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-sm">Expresión regular</label>
                        <input type="text" class="form-control form-control-sm" data-field="input.pattern" placeholder="Ej: ^\\d{6,12}$">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-sm">Mensajes de error</label>
                    <div data-error-message-list></div>
                    <button type="button" class="btn btn-xs btn-outline-secondary mt-2" data-action="add-error-message"><i class="mdi mdi-plus"></i> Añadir mensaje</button>
                </div>
                <label class="form-label-sm">Escenario siguiente</label>
                <input type="text" class="form-control form-control-sm" data-field="next" placeholder="ID del siguiente escenario">
            </div>

            <div data-node-section="decision" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="form-label-sm mb-0">Ramas condicionales</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary" data-action="add-branch"><i class="mdi mdi-plus"></i> Añadir condición</button>
                </div>
                <div data-branch-list></div>
            </div>
        </div>
    </div>
</template>

<template id="response-template">
    <div class="border rounded-3 p-3 mb-2" data-response>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label-sm">Identificador</label>
                <input type="text" class="form-control form-control-sm" data-field="id" placeholder="Ej: patient-found-menu">
            </div>
            <div class="col-md-4">
                <label class="form-label-sm">Título</label>
                <input type="text" class="form-control form-control-sm" data-field="title" placeholder="Uso interno">
            </div>
            <div class="col-md-4">
                <label class="form-label-sm">Escenario destino</label>
                <input type="text" class="form-control form-control-sm" data-field="target" placeholder="ID del escenario">
            </div>
        </div>
        <label class="form-label-sm">Palabras clave</label>
        <input type="text" class="form-control form-control-sm mb-2" data-field="keywords" placeholder="Ej: menu, inicio">
        <label class="form-label-sm">Limpiar contexto</label>
        <input type="text" class="form-control form-control-sm mb-2" data-field="clear_context" placeholder="Ej: hc_number">
        <div data-response-message-list></div>
        <button type="button" class="btn btn-xs btn-outline-secondary mt-2" data-action="add-response-message"><i class="mdi mdi-plus"></i> Añadir mensaje</button>
        <button type="button" class="btn btn-xs btn-outline-danger mt-2 float-end" data-action="remove-response"><i class="mdi mdi-close"></i> Eliminar</button>
    </div>
</template>

<template id="branch-template">
    <div class="border rounded-3 p-3 mb-2" data-branch>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label-sm">Identificador</label>
                <input type="text" class="form-control form-control-sm" data-field="id" placeholder="Ej: patient-found">
            </div>
            <div class="col-md-4">
                <label class="form-label-sm">Tipo de condición</label>
                <select class="form-select form-select-sm" data-field="condition.type">
                    <option value="always">Siempre</option>
                    <option value="patient_exists">Paciente registrado</option>
                    <option value="has_value">Dato presente</option>
                    <option value="equals">Igual a...</option>
                    <option value="not_equals">Distinto a...</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label-sm">Escenario siguiente</label>
                <input type="text" class="form-control form-control-sm" data-field="next" placeholder="ID del escenario">
            </div>
        </div>
        <div class="row g-2 mb-2" data-branch-extra></div>
        <div data-branch-message-list></div>
        <button type="button" class="btn btn-xs btn-outline-secondary mt-2" data-action="add-branch-message"><i class="mdi mdi-plus"></i> Añadir mensaje</button>
        <button type="button" class="btn btn-xs btn-outline-danger mt-2 float-end" data-action="remove-branch"><i class="mdi mdi-close"></i> Eliminar</button>
    </div>
</template>

<template id="error-message-template">
    <div class="border rounded-3 p-2 mb-2 d-flex align-items-center" data-error-message>
        <textarea class="form-control form-control-sm me-2" rows="2" placeholder="Mensaje de error"></textarea>
        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-error-message"><i class="mdi mdi-close"></i></button>
    </div>
</template>

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

