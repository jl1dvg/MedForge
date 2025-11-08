<?php
/** @var array $message */
$type = $message['type'] ?? 'text';
$body = $message['body'] ?? '';
$header = $message['header'] ?? '';
$footer = $message['footer'] ?? '';
$buttons = $message['buttons'] ?? [];
?>
<div class="card card-body shadow-sm mb-3" data-message>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <label class="form-label small text-muted mb-1">Tipo de mensaje</label>
            <select class="form-select form-select-sm message-type">
                <option value="text"<?= ($type === 'text') ? ' selected' : ''; ?>>Texto</option>
                <option value="buttons"<?= ($type === 'buttons') ? ' selected' : ''; ?>>Botones interactivos</option>
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
</div>
