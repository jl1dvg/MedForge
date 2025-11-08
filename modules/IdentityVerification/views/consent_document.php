<?php
/** @var array $checkin */
/** @var bool $canRenderConsent */
?>
<div class="content-header">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h3 class="page-title mb-2">Documento de consentimiento / atención</h3>
            <p class="text-muted mb-0">Resumen del último check-in biométrico del paciente.</p>
        </div>
        <div>
            <a href="/pacientes/certificaciones" class="btn btn-secondary"><i class="mdi mdi-arrow-left"></i> Volver</a>
            <button type="button" class="btn btn-primary ms-2" onclick="window.print()"><i class="mdi mdi-printer"></i> Imprimir</button>
        </div>
    </div>
</div>

<section class="content">
    <div class="box">
        <div class="box-body">
            <?php if (!$canRenderConsent): ?>
                <div class="alert alert-danger">
                    <strong>Verificación rechazada.</strong> Este registro no puede generar un consentimiento automático. Proceda con la validación manual según el protocolo interno.
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-1">Datos del paciente</h5>
                    <p class="mb-0"><strong>Nombre:</strong> <?= htmlspecialchars($checkin['full_name'] ?: 'Sin registro', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0"><strong>Historia clínica:</strong> <?= htmlspecialchars($checkin['patient_id'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($checkin['cedula'])): ?>
                        <p class="mb-0"><strong>Cédula registrada:</strong> <?= htmlspecialchars($checkin['cedula'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($checkin['afiliacion'])): ?>
                        <p class="mb-0"><strong>Afiliación:</strong> <?= htmlspecialchars($checkin['afiliacion'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-1">Detalle del check-in</h5>
                    <p class="mb-0"><strong>Fecha y hora:</strong> <?= htmlspecialchars($checkin['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0"><strong>Resultado:</strong> <?= htmlspecialchars(ucfirst($checkin['verification_result']), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (isset($checkin['verified_face_score'])): ?>
                        <p class="mb-0"><strong>Puntaje facial:</strong> <?= htmlspecialchars(number_format((float) $checkin['verified_face_score'], 2), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (isset($checkin['created_by'])): ?>
                        <p class="mb-0"><strong>Usuario que registró:</strong> <?= htmlspecialchars((string) $checkin['created_by'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-2">Documento de identidad</h5>
                    <p class="mb-1"><strong>Número:</strong> <?= htmlspecialchars($checkin['document_number'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-3"><strong>Tipo:</strong> <?= htmlspecialchars(strtoupper($checkin['document_type']), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php if (!empty($checkin['document_front_path'])): ?>
                            <div>
                                <small class="text-muted d-block">Anverso</small>
                                <img src="/<?= ltrim($checkin['document_front_path'], '/') ?>" alt="Documento anverso" class="img-thumbnail" style="max-width: 240px;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($checkin['document_back_path'])): ?>
                            <div>
                                <small class="text-muted d-block">Reverso</small>
                                <img src="/<?= ltrim($checkin['document_back_path'], '/') ?>" alt="Documento reverso" class="img-thumbnail" style="max-width: 240px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-2">Firma del consentimiento</h5>
                    <?php if (!empty($checkin['signature_path'])): ?>
                        <div class="border rounded p-3 bg-white">
                            <img src="/<?= ltrim($checkin['signature_path'], '/') ?>" alt="Firma del paciente" class="img-fluid">
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No se registró firma manuscrita en la certificación.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($checkin['metadata']['face_capture'])): ?>
                <div class="mb-4">
                    <h5 class="mb-2">Captura facial del check-in</h5>
                    <img src="/<?= ltrim($checkin['metadata']['face_capture'], '/') ?>" alt="Rostro capturado" class="img-thumbnail" style="max-width: 280px;">
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <p class="mb-1"><strong>Declaración:</strong> El paciente confirma la veracidad de los datos presentados y autoriza la atención según los protocolos vigentes.</p>
                <p class="mb-0"><small>Documento generado automáticamente por el módulo de certificación biométrica de MedForge.</small></p>
            </div>
        </div>
    </div>
</section>
