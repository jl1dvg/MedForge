<?php
/** @var array $message */
$type = $message['type'] ?? 'text';
$body = $message['body'] ?? '';
$header = $message['header'] ?? '';
$footer = $message['footer'] ?? '';
$buttons = $message['buttons'] ?? [];
$listButton = $message['button'] ?? 'Ver opciones';
$listSections = $message['sections'] ?? [];
$templateData = $message['template'] ?? ['name' => '', 'language' => '', 'category' => '', 'components' => []];
$templateName = is_array($templateData) ? ($templateData['name'] ?? '') : '';
$templateLanguage = is_array($templateData) ? ($templateData['language'] ?? '') : '';
$templateCategory = is_array($templateData) ? ($templateData['category'] ?? '') : '';
$templateComponents = is_array($templateData) ? ($templateData['components'] ?? []) : [];
$templateComponentsJson = htmlspecialchars(json_encode($templateComponents, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');
?>
<div class="card card-body shadow-sm mb-3" data-message>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <label class="form-label small text-muted mb-1">Tipo de mensaje</label>
            <select class="form-select form-select-sm message-type">
                <option value="text"<?= ($type === 'text') ? ' selected' : ''; ?>>Texto</option>
                <option value="buttons"<?= ($type === 'buttons') ? ' selected' : ''; ?>>Botones interactivos</option>
                <option value="list"<?= ($type === 'list') ? ' selected' : ''; ?>>Lista o menú</option>
                <option value="template"<?= ($type === 'template') ? ' selected' : ''; ?>>Plantilla aprobada</option>
            </select>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-message">
            <i class="mdi mdi-delete-outline me-1"></i>Eliminar
        </button>
    </div>
    <div class="mt-3">
        <label class="form-label">Contenido del mensaje</label>
        <textarea class="form-control message-body" rows="3" placeholder="Escribe la respuesta que se enviará."><?= $escape((string) $body); ?></textarea>
    </div>
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <label class="form-label small">Encabezado (opcional)</label>
            <input type="text" class="form-control message-header" value="<?= $escape((string) $header); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Pie (opcional)</label>
            <input type="text" class="form-control message-footer" value="<?= $escape((string) $footer); ?>">
        </div>
    </div>
    <div class="mt-3<?= ($type === 'buttons') ? '' : ' d-none'; ?>" data-buttons>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
            <div class="small fw-600 text-muted">Botones interactivos</div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-xs btn-outline-secondary" data-action="preset" data-preset="yesno">Añadir Sí / No</button>
                <button type="button" class="btn btn-xs btn-outline-secondary" data-action="preset" data-preset="menu">Añadir "Menú"</button>
                <button type="button" class="btn btn-xs btn-outline-primary" data-action="add-button">
                    <i class="mdi mdi-plus"></i> Botón vacío
                </button>
            </div>
        </div>
        <div data-button-list>
            <?php if (is_array($buttons)): ?>
                <?php foreach ($buttons as $button): ?>
                    <div class="input-group input-group-sm mb-2" data-button>
                        <span class="input-group-text">Título</span>
                        <input type="text" class="form-control button-title" value="<?= $escape((string) ($button['title'] ?? '')); ?>" placeholder="Texto del botón">
                        <span class="input-group-text">ID</span>
                        <input type="text" class="form-control button-id" value="<?= $escape((string) ($button['id'] ?? '')); ?>" placeholder="Identificador opcional">
                        <button type="button" class="btn btn-outline-danger" data-action="remove-button"><i class="mdi mdi-close"></i></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p class="small text-muted mb-0">Máximo 3 botones por mensaje. Puedes usar las plantillas rápidas para añadir opciones comunes.</p>
    </div>
    <div class="mt-3<?= ($type === 'list') ? '' : ' d-none'; ?>" data-list>
        <div class="mb-3">
            <label class="form-label small">Texto del botón de apertura</label>
            <input type="text" class="form-control list-button" value="<?= $escape((string) $listButton); ?>" placeholder="Ejemplo: Ver opciones">
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small fw-600 text-muted">Secciones del menú</div>
            <button type="button" class="btn btn-xs btn-outline-primary" data-action="add-section">
                <i class="mdi mdi-plus"></i> Añadir sección
            </button>
        </div>
        <div data-sections>
            <?php if (is_array($listSections) && !empty($listSections)): ?>
                <?php foreach ($listSections as $section): ?>
                    <div class="border rounded-3 p-3 mb-3" data-section>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="text" class="form-control section-title" placeholder="Título de la sección (opcional)" value="<?= $escape((string) ($section['title'] ?? '')); ?>">
                            <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-section"><i class="mdi mdi-close"></i></button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small text-muted">Opciones</div>
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-action="add-row">Añadir opción</button>
                        </div>
                        <div data-rows>
                            <?php foreach (($section['rows'] ?? []) as $row): ?>
                                <div class="input-group input-group-sm mb-2" data-row>
                                    <span class="input-group-text">Título</span>
                                    <input type="text" class="form-control row-title" value="<?= $escape((string) ($row['title'] ?? '')); ?>" placeholder="Ej: Confirmar cita">
                                    <span class="input-group-text">ID</span>
                                    <input type="text" class="form-control row-id" value="<?= $escape((string) ($row['id'] ?? '')); ?>" placeholder="Identificador">
                                    <input type="text" class="form-control row-description" value="<?= $escape((string) ($row['description'] ?? '')); ?>" placeholder="Descripción opcional">
                                    <button type="button" class="btn btn-outline-danger" data-action="remove-row"><i class="mdi mdi-close"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p class="small text-muted mb-0">Hasta 10 secciones con un máximo de 10 opciones cada una. El ID de cada opción se envía como respuesta.</p>
    </div>
    <div class="mt-3<?= ($type === 'template') ? '' : ' d-none'; ?>" data-template>
        <input type="hidden" class="template-name" value="<?= $escape((string) $templateName); ?>">
        <input type="hidden" class="template-language" value="<?= $escape((string) $templateLanguage); ?>">
        <input type="hidden" class="template-category" value="<?= $escape((string) $templateCategory); ?>">
        <textarea class="d-none template-components" data-template-components><?= $templateComponentsJson; ?></textarea>
        <div class="mb-3">
            <label class="form-label">Selecciona una plantilla</label>
            <select class="form-select template-selector">
                <option value="">Elige una plantilla aprobada</option>
            </select>
        </div>
        <div class="alert alert-light border template-summary small mb-3" role="status">
            <div class="fw-600">Sin plantilla seleccionada</div>
            <div>Elige una plantilla para ver sus variables y completar los parámetros.</div>
        </div>
        <div class="template-parameters"></div>
        <p class="small text-muted mb-0">Las variables se envían a WhatsApp exactamente como las completes aquí. Asegúrate de respetar el formato esperado (fechas, enlaces, etc.).</p>
    </div>
</div>
