<?php
/** @var array $templates */
/** @var string $selectedKey */
/** @var array|null $selectedTemplate */
/** @var string|null $status */

$templates = $templates ?? [];
$selectedKey = $selectedKey ?? '';
$selectedTemplate = $selectedTemplate ?? null;
$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$scripts = $scripts ?? [];
$scripts[] = 'assets/vendor_components/ckeditor/ckeditor.js';
$inlineScripts = $inlineScripts ?? [];
$inlineScripts[] = <<<'JS'
(function () {
    const FORM = document.querySelector('[data-template-form]');
    if (!FORM) {
        return;
    }

    const subjectInput = FORM.querySelector('[data-template-subject]');
    const htmlInput = FORM.querySelector('[data-template-html]');
    const textInput = FORM.querySelector('[data-template-text]');
    const previewSubject = document.querySelector('[data-template-preview-subject]');
    const previewHtml = document.querySelector('[data-template-preview-html]');
    const previewText = document.querySelector('[data-template-preview-text]');

    const dummyData = {
        '{PACIENTE}': 'María Lopez',
        '{HC}': '0102030405',
        '{PROC}': 'Cirugía de catarata',
        '{PLAN}': 'Control post operatorio',
        '{FORM_ID}': 'FORM-12345',
        '{PDF_URL}': 'https://www.cive.ec/derivaciones/FORM-12345.pdf',
    };

    function applyTemplate(value) {
        if (!value) {
            return '';
        }

        let output = value;
        Object.keys(dummyData).forEach(function (key) {
            output = output.split(key).join(dummyData[key]);
        });
        return output;
    }

    let htmlEditor = null;
    if (window.CKEDITOR && htmlInput && !CKEDITOR.instances.templateHtml) {
        htmlEditor = CKEDITOR.replace('templateHtml', {
            toolbar: [
                {name: 'basicstyles', items: ['Bold', 'Italic', 'Underline']},
                {name: 'links', items: ['Link', 'Unlink']},
                {name: 'paragraph', items: ['BulletedList', 'NumberedList']},
                {name: 'clipboard', items: ['Undo', 'Redo']},
                {name: 'editing', items: ['RemoveFormat']},
            ],
            removePlugins: 'elementspath',
            resize_enabled: false,
        });
    }

    function getHtmlValue() {
        if (htmlEditor) {
            return htmlEditor.getData();
        }
        return htmlInput ? htmlInput.value : '';
    }

    function refreshPreview() {
        if (previewSubject && subjectInput) {
            previewSubject.textContent = applyTemplate(subjectInput.value) || '—';
        }
        if (previewHtml) {
            const html = applyTemplate(getHtmlValue());
            previewHtml.innerHTML = html || '<em class="text-muted">Sin contenido HTML</em>';
        }
        if (previewText && textInput) {
            const text = applyTemplate(textInput.value);
            previewText.textContent = text || '—';
        }
    }

    [subjectInput, htmlInput, textInput].forEach(function (input) {
        if (!input) {
            return;
        }
        input.addEventListener('input', refreshPreview);
    });

    if (htmlEditor) {
        htmlEditor.on('change', refreshPreview);
        htmlEditor.on('instanceReady', refreshPreview);
    }

    refreshPreview();
})();
JS;
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h4 class="mb-1">Plantillas de cobertura</h4>
            <p class="text-muted mb-0">Administra los textos y destinatarios para las solicitudes de cobertura por correo.</p>
        </div>
    </div>

    <?php if ($status === 'updated'): ?>
        <div class="alert alert-success">Plantilla guardada correctamente.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Plantillas</h6>
                    <a class="btn btn-sm btn-outline-primary" href="/mail-templates/cobertura/new">Nueva</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($templates as $template): ?>
                        <?php
                        $key = $template['template_key'] ?? '';
                        $isActive = $key === $selectedKey;
                        ?>
                        <a class="list-group-item list-group-item-action<?= $isActive ? ' active' : '' ?>"
                           href="/mail-templates/cobertura/<?= $escape($key) ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><?= $escape($template['name'] ?? $key) ?></div>
                                    <small class="text-muted d-block"><?= $escape($key) ?></small>
                                </div>
                                <?php if (!empty($template['enabled'])): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($templates)): ?>
                        <div class="list-group-item text-muted">No hay plantillas registradas.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Detalle de plantilla</h6>
                </div>
                <div class="card-body">
                    <?php if (!$selectedTemplate): ?>
                        <p class="text-muted mb-0">Selecciona una plantilla para editarla.</p>
                    <?php else: ?>
                        <form method="post" action="/mail-templates/cobertura/<?= $escape($selectedKey) ?>"
                              data-template-form>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="templateKey">Key</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="templateKey"
                                        name="template_key"
                                        value="<?= $escape($selectedTemplate['template_key'] ?? '') ?>"
                                        <?= $selectedKey !== 'new' ? 'readonly' : '' ?>
                                        required
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="templateName">Nombre</label>
                                    <input type="text" class="form-control" id="templateName" name="name"
                                           value="<?= $escape($selectedTemplate['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="templateSubject">Asunto</label>
                                    <input type="text" class="form-control" id="templateSubject" name="subject_template"
                                           data-template-subject
                                           value="<?= $escape($selectedTemplate['subject_template'] ?? '') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="templateHtml">Mensaje HTML</label>
                                    <textarea class="form-control" id="templateHtml" rows="6" name="body_template_html"
                                              data-template-html><?= $escape($selectedTemplate['body_template_html'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="templateText">Mensaje texto plano</label>
                                    <textarea class="form-control" id="templateText" rows="6" name="body_template_text"
                                              data-template-text><?= $escape($selectedTemplate['body_template_text'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="recipientsTo">Para</label>
                                    <input type="text" class="form-control" id="recipientsTo" name="recipients_to"
                                           placeholder="correo@cive.ec"
                                           value="<?= $escape($selectedTemplate['recipients_to'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="recipientsCc">CC</label>
                                    <input type="text" class="form-control" id="recipientsCc" name="recipients_cc"
                                           placeholder="correo@cive.ec"
                                           value="<?= $escape($selectedTemplate['recipients_cc'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="templateEnabled"
                                               name="enabled" value="1"
                                            <?= !empty($selectedTemplate['enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="templateEnabled">Activa</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">Guardar</button>
                                <a class="btn btn-outline-secondary" href="/mail-templates/cobertura">Cancelar</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Vista previa (datos dummy)</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Asunto</div>
                        <div class="fw-semibold" data-template-preview-subject>—</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">HTML</div>
                            <div class="border rounded p-2" style="min-height: 160px;" data-template-preview-html></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Texto plano</div>
                            <pre class="border rounded p-2" style="min-height: 160px; white-space: pre-wrap;" data-template-preview-text>—</pre>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-light text-dark">{PACIENTE}</span>
                        <span class="badge bg-light text-dark">{HC}</span>
                        <span class="badge bg-light text-dark">{PROC}</span>
                        <span class="badge bg-light text-dark">{PLAN}</span>
                        <span class="badge bg-light text-dark">{FORM_ID}</span>
                        <span class="badge bg-light text-dark">{PDF_URL}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
