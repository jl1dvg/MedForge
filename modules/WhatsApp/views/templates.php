<?php
/** @var array $config */
/** @var array $categories */
/** @var array $languages */
/** @var array $integrationErrors */
/** @var array $integrationWarnings */
/** @var array $bootstrap */
/** @var bool $isIntegrationReady */

$scripts = $scripts ?? [];
$integrationWarnings = $integrationWarnings ?? [];
$isIntegrationReady = $isIntegrationReady ?? (($config['enabled'] ?? false) && empty($integrationErrors ?? []));
$bootstrapJson = htmlspecialchars(json_encode($bootstrap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Plantillas de WhatsApp</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item">WhatsApp</li>
                        <li class="breadcrumb-item active" aria-current="page">Plantillas</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-600 text-muted small">Cuenta vinculada</div>
            <div class="fw-600">
                <?= htmlspecialchars($config['brand'] ?? 'Sin marca', ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <a href="/whatsapp/autoresponder" class="btn btn-sm btn-outline-primary mt-2">
                <i class="mdi mdi-flowchart me-1"></i>Ver flujo de autorespuesta
            </a>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div
                class="box"
                id="whatsapp-templates-root"
                data-bootstrap="<?= $bootstrapJson; ?>"
                data-endpoint-list="/whatsapp/api/templates"
                data-endpoint-create="/whatsapp/api/templates"
                data-endpoint-update="/whatsapp/api/templates/{id}"
                data-endpoint-delete="/whatsapp/api/templates/{id}/delete"
                data-enabled="<?= $isIntegrationReady ? '1' : '0'; ?>"
            >
                <div class="box-header with-border d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="box-title mb-0">Gestión de plantillas oficiales</h4>
                        <p class="text-muted mb-0">
                            Sincroniza, crea y edita las plantillas disponibles en tu cuenta de WhatsApp Cloud API para usarlas en tus campañas y automatizaciones.
                        </p>
                    </div>
                    <div class="text-end">
                        <span class="badge <?= $isIntegrationReady ? 'bg-success-light text-success' : 'bg-warning-light text-warning'; ?> fw-600">
                            <?= $isIntegrationReady ? 'Integración lista' : 'Acción requerida'; ?>
                        </span>
                    </div>
                </div>
                <div class="box-body">
                    <?php if (!empty($integrationErrors)): ?>
                        <div class="alert alert-warning">
                            <h5 class="mb-2"><i class="mdi mdi-alert-outline"></i> Revisa la configuración</h5>
                            <ul class="mb-2 ps-3">
                                <?php foreach ($integrationErrors as $error): ?>
                                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="/settings?section=whatsapp" class="btn btn-sm btn-outline-warning">Ir a configuración</a>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($integrationErrors) && !empty($integrationWarnings)): ?>
                        <div class="alert alert-info">
                            <h5 class="mb-2"><i class="mdi mdi-information-outline"></i> Integración con observaciones</h5>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($integrationWarnings as $warning): ?>
                                    <li><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" data-action="refresh" <?= $isIntegrationReady ? '' : 'disabled'; ?>>
                                <i class="mdi mdi-refresh"></i> Sincronizar plantillas
                            </button>
                            <button type="button" class="btn btn-success" data-action="new-template" <?= $isIntegrationReady ? '' : 'disabled'; ?>>
                                <i class="mdi mdi-plus"></i> Nueva plantilla
                            </button>
                        </div>
                        <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                <input type="search" class="form-control" id="template-search" placeholder="Buscar" autocomplete="off">
                            </div>
                            <select class="form-select form-select-sm" id="template-status-filter">
                                <option value="">Estado</option>
                                <option value="approved">Aprobada</option>
                                <option value="pending">Pendiente</option>
                                <option value="rejected">Rechazada</option>
                                <option value="disabled">Deshabilitada</option>
                            </select>
                            <select class="form-select form-select-sm" id="template-language-filter">
                                <option value="">Idioma</option>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?= htmlspecialchars($language['code'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select form-select-sm" id="template-category-filter">
                                <option value="">Categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['value'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="alert d-none" role="alert" data-feedback></div>

                    <div class="table-responsive rounded border position-relative">
                        <table class="table table-striped align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Idioma</th>
                                    <th>Estado</th>
                                    <th>Calidad</th>
                                    <th>Actualizada</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-table-body></tbody>
                        </table>
                        <div class="text-center py-5 d-none" data-loading-indicator>
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-3 mb-0 text-muted">Cargando plantillas desde WhatsApp...</p>
                        </div>
                        <div class="text-center py-5 d-none" data-empty-state>
                            <i class="mdi mdi-whatsapp text-success" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0 text-muted">No hay plantillas que coincidan con los filtros seleccionados.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="whatsapp-template-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form data-template-form>
                <div class="modal-header">
                    <h5 class="modal-title">Nueva plantilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" data-modal-feedback></div>
                    <input type="hidden" name="template_id" data-field="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="template-name" class="form-label">Nombre de la plantilla</label>
                            <input type="text" class="form-control" id="template-name" data-field="name" placeholder="ej: recordatorio_cita" required>
                            <small class="text-muted">Utiliza solo minúsculas, guiones bajos y números.</small>
                        </div>
                        <div class="col-md-3">
                            <label for="template-language" class="form-label">Idioma</label>
                            <select class="form-select" id="template-language" data-field="language" required>
                                <option value="">Selecciona un idioma</option>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?= htmlspecialchars($language['code'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="template-category" class="form-label">Categoría</label>
                            <select class="form-select" id="template-category" data-field="category" required>
                                <option value="">Selecciona una categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['value'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="template-allow-category-change" data-field="allow_category_change">
                        <label class="form-check-label" for="template-allow-category-change">Permitir que Meta ajuste la categoría automáticamente</label>
                    </div>
                    <div class="mt-4">
                        <label for="template-components" class="form-label">Componentes (JSON)</label>
                        <textarea class="form-control font-monospace" id="template-components" data-field="components" rows="10" placeholder='[
    {
        "type": "BODY",
        "text": "Hola {{1}}, tu cita es el {{2}} a las {{3}}."
    }
]'></textarea>
                        <small class="text-muted d-block mt-2">
                            Incluye los componentes en formato JSON siguiendo la estructura oficial de Meta. Puedes añadir HEADER, BODY, FOOTER y BUTTONS.
                        </small>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger" data-action="delete-template" hidden>Eliminar plantilla</button>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="whatsapp-template-preview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vista previa de la plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-1" data-preview-name></h6>
                        <p class="text-muted mb-0 small" data-preview-language></p>
                    </div>
                    <span class="badge bg-primary-light text-primary fw-600" data-preview-status></span>
                </div>
                <div class="border rounded p-3 bg-light">
                    <pre class="mb-0" data-preview-json></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
