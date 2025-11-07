<?php
/** @var array $config */
/** @var array $flow */

$escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$renderMessage = static fn (string $message): string => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), false);

$entry = $flow['entry'] ?? [];
$options = $flow['options'] ?? [];
$fallback = $flow['fallback'] ?? [];
$meta = $flow['meta'] ?? [];
$keywordLegend = $meta['keywordLegend'] ?? [];
$brand = $meta['brand'] ?? ($config['brand'] ?? 'MedForge');
?>
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
        </div>
    </div>
</div>

<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="box-title mb-0">Diagrama general del flujo</h4>
                        <p class="text-muted mb-0">Visualiza las palabras clave y los mensajes que se envían en cada paso del autorrespondedor.</p>
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
                                                <span class="badge bg-primary-light text-primary"><?= $escape($keyword); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($entry['messages'])): ?>
                                <div class="mt-3">
                                    <?php foreach ($entry['messages'] as $message): ?>
                                        <div class="bg-white border rounded-3 p-3 mb-2 shadow-sm">
                                            <div class="fw-600 text-muted small mb-1"><i class="mdi mdi-robot-outline me-1"></i>Mensaje enviado</div>
                                            <div class="small"><?= $renderMessage($message); ?></div>
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
                                                <p class="text-muted small mb-2">Respuestas que se disparan con las palabras clave listadas.</p>
                                            </div>
                                            <?php if (!empty($option['keywords'])): ?>
                                                <div class="text-end">
                                                    <div class="fw-600 text-muted small text-uppercase">Palabras clave</div>
                                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                                        <?php foreach ($option['keywords'] as $keyword): ?>
                                                            <span class="badge bg-success-light text-success"><?= $escape($keyword); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($option['messages'])): ?>
                                            <div class="mt-3">
                                                <?php foreach ($option['messages'] as $message): ?>
                                                    <div class="bg-light border rounded-3 p-3 mb-2">
                                                        <div class="fw-600 text-muted small mb-1"><i class="mdi mdi-message-text-outline me-1"></i>Mensaje enviado</div>
                                                        <div class="small"><?= $renderMessage($message); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($option['followup'])): ?>
                                            <div class="mt-3 pt-3 border-top">
                                                <div class="fw-600 text-muted small text-uppercase mb-1">Siguiente paso sugerido</div>
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
                                            <div class="fw-600 text-muted small mb-1"><i class="mdi mdi-robot-outline me-1"></i>Mensaje enviado</div>
                                            <div class="small"><?= $renderMessage($message); ?></div>
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
                    <div class="box-header with-border">
                        <h4 class="box-title mb-0">Leyenda de palabras clave</h4>
                        <p class="text-muted mb-0">Usa estas palabras o frases para extender el flujo en el controlador.</p>
                    </div>
                    <div class="box-body">
                        <div class="row g-3">
                            <?php foreach ($keywordLegend as $section => $keywords): ?>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="fw-600 mb-2"><?= $escape($section); ?></div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($keywords as $keyword): ?>
                                                <span class="badge bg-secondary-light text-secondary"><?= $escape($keyword); ?></span>
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
    </div>
</section>
