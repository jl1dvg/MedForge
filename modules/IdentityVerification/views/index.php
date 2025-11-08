<?php
/** @var array $certifications */
/** @var array $errors */
/** @var array $old */
/** @var array|null $selectedPatient */
/** @var array|null $selectedCertification */
/** @var string|null $status */
?>
<div class="content-header">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h3 class="page-title mb-2">Certificación biométrica de pacientes</h3>
            <p class="text-muted mb-0">Registre la firma, el documento y el rostro del paciente para validar su identidad en futuras atenciones.</p>
        </div>
        <div>
            <a href="/pacientes" class="btn btn-secondary"><i class="mdi mdi-arrow-left"></i> Volver a pacientes</a>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h4 class="box-title mb-0">Registrar o actualizar certificación</h4>
                    <span class="badge bg-primary">Captura inicial</span>
                </div>
                <div class="box-body">
                    <?php if (!empty($status)): ?>
                        <?php if ($status === 'stored'): ?>
                            <div class="alert alert-success">
                                <i class="mdi mdi-check-circle"></i> La certificación del paciente se guardó correctamente. El registro está listo para verificaciones faciales.
                            </div>
                        <?php elseif ($status === 'stored_pending'): ?>
                            <div class="alert alert-warning">
                                <i class="mdi mdi-alert"></i> La certificación se guardó pero aún faltan datos obligatorios. Capture la firma, rostro y ambos lados del documento para finalizar.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Revise los datos ingresados:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $message): ?>
                                    <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selectedPatient)): ?>
                        <div class="alert alert-info">
                            <div class="d-flex flex-column">
                                <strong>Paciente seleccionado:</strong>
                                <span>
                                    <?= htmlspecialchars($selectedPatient['full_name'] !== '' ? $selectedPatient['full_name'] : 'Historia clínica ' . ($selectedPatient['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <small class="text-muted">
                                    HC: <?= htmlspecialchars($selectedPatient['hc_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($selectedPatient['cedula'])): ?>
                                        · Cédula registrada: <?= htmlspecialchars($selectedPatient['cedula'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedPatient['afiliacion'])): ?>
                                        · Afiliación: <?= htmlspecialchars($selectedPatient['afiliacion'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </small>
                                <?php if (!empty($selectedCertification)): ?>
                                    <div class="mt-2">
                                        <?php
                                        $statusBadge = [
                                            'verified' => 'success',
                                            'pending' => 'warning',
                                            'revoked' => 'danger',
                                        ];
                                        $badge = $statusBadge[$selectedCertification['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>">Estado: <?= htmlspecialchars(ucfirst($selectedCertification['status']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($selectedCertification['completeness']['missing'])): ?>
                                            <div class="text-muted small mt-1">Faltan: <?= htmlspecialchars(implode(', ', $selectedCertification['completeness']['missing']), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?>
                                            <div class="text-success small mt-1">Certificación completa. Puede realizar check-ins faciales.</div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small mt-2">Este paciente aún no tiene certificación biométrica registrada.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="patientCertificationForm" action="/pacientes/certificaciones" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="patientId" class="form-label">Historia clínica / ID interno<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="patientId" name="patient_id"
                                   value="<?= htmlspecialchars($old['patient_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Ej: HC000123" required>
                        </div>
                        <div class="mb-3">
                            <label for="documentNumber" class="form-label">Cédula de identidad<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="documentNumber" name="document_number"
                                   value="<?= htmlspecialchars($old['document_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Número de documento" required>
                        </div>
                        <div class="mb-3">
                            <label for="documentType" class="form-label">Tipo de documento</label>
                            <select class="form-select" id="documentType" name="document_type">
                                <?php
                                $documentType = $old['document_type'] ?? 'cedula';
                                $documentOptions = [
                                    'cedula' => 'Cédula de identidad',
                                    'pasaporte' => 'Pasaporte',
                                    'otro' => 'Otro',
                                ];
                                foreach ($documentOptions as $value => $label):
                                ?>
                                    <option value="<?= $value ?>" <?= $documentType === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-block">Firma manuscrita del paciente<span class="text-danger">*</span></label>
                            <div class="signature-pad" data-target="signature">
                                <canvas id="patientSignatureCanvas" width="520" height="220" class="border rounded bg-white"></canvas>
                                <div class="mt-2 d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-action="clear-signature">Limpiar</button>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="signatureUpload">Cargar imagen</button>
                                    <input type="file" accept="image/*" class="d-none" id="signatureUpload">
                                </div>
                            </div>
                            <input type="hidden" name="signature_data" id="signatureDataField">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Firma de la cédula (opcional)</label>
                            <div class="signature-pad" data-target="document-signature">
                                <canvas id="documentSignatureCanvas" width="520" height="160" class="border rounded bg-white"></canvas>
                                <div class="mt-2 d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-action="clear-document-signature">Limpiar</button>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="documentSignatureUpload">Cargar imagen</button>
                                    <input type="file" accept="image/*" class="d-none" id="documentSignatureUpload">
                                </div>
                            </div>
                            <input type="hidden" name="document_signature_data" id="documentSignatureDataField">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Fotografías del documento (frontal y reverso, requeridas para completar la certificación)</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="file" name="document_front" accept="image/*,application/pdf" class="form-control">
                                    <small class="text-muted">Frontal</small>
                                </div>
                                <div class="col-md-6">
                                    <input type="file" name="document_back" accept="image/*,application/pdf" class="form-control">
                                    <small class="text-muted">Reverso</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-block">Captura facial del paciente<span class="text-danger">*</span></label>
                            <div class="face-capture" data-target="face">
                                <div class="ratio ratio-4x3 bg-dark rounded position-relative overflow-hidden">
                                    <video id="faceCaptureVideo" autoplay playsinline class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"></video>
                                    <canvas id="faceCaptureCanvas" class="position-absolute top-0 start-0 w-100 h-100 d-none"></canvas>
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-action="start-camera">Iniciar cámara</button>
                                    <button class="btn btn-sm btn-outline-success" type="button" data-action="capture-face">Capturar</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-action="reset-face">Resetear</button>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="faceUpload">Cargar imagen</button>
                                    <input type="file" accept="image/*" class="d-none" id="faceUpload">
                                </div>
                                <small class="text-muted d-block mt-2">Para mayor precisión, procure un fondo claro y buena iluminación.</small>
                            </div>
                            <input type="hidden" name="face_image" id="faceImageDataField">
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save"></i> Guardar certificación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="box mb-3">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h4 class="box-title mb-0">Verificación rápida</h4>
                    <span class="badge bg-info">Atención en curso</span>
                </div>
                <div class="box-body">
                    <p class="text-muted">Use este formulario durante la admisión para capturar el rostro actual del paciente y validar contra la certificación registrada.</p>
                    <form id="verificationForm" action="/pacientes/certificaciones/verificar" method="post">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="verificationPatientId" class="form-label">Historia clínica / ID interno</label>
                                <input type="text" class="form-control" id="verificationPatientId" name="patient_id" placeholder="HC000123">
                            </div>
                            <div class="col-md-6">
                                <label for="verificationCertificationId" class="form-label">ID certificación</label>
                                <input type="number" class="form-control" id="verificationCertificationId" name="certification_id" min="1">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label d-block">Captura facial (actual)</label>
                            <div class="face-capture" data-target="verification-face">
                                <div class="ratio ratio-4x3 bg-dark rounded position-relative overflow-hidden">
                                    <video id="verificationFaceVideo" autoplay playsinline class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"></video>
                                    <canvas id="verificationFaceCanvas" class="position-absolute top-0 start-0 w-100 h-100 d-none"></canvas>
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-action="start-verification-camera">Iniciar cámara</button>
                                    <button class="btn btn-sm btn-outline-success" type="button" data-action="capture-verification-face">Capturar</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-action="reset-verification-face">Resetear</button>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="verificationFaceUpload">Cargar imagen</button>
                                    <input type="file" accept="image/*" class="d-none" id="verificationFaceUpload">
                                </div>
                            </div>
                            <input type="hidden" name="face_image" id="verificationFaceDataField" required>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-success"><i class="mdi mdi-shield-check"></i> Verificar identidad</button>
                        </div>
                    </form>
                    <div id="verificationResult" class="mt-3" hidden>
                        <div class="alert" role="alert"></div>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h4 class="box-title mb-0">Certificaciones registradas recientemente</h4>
                    <small class="text-muted">Últimos <?= count($certifications) ?> registros</small>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Paciente</th>
                                    <th>Documento</th>
                                    <th>Estado</th>
                                    <th>Última verificación</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($certifications)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Todavía no existen pacientes certificados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($certifications as $cert): ?>
                                    <tr>
                                        <td>#<?= (int) $cert['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($cert['full_name'] ?: 'Sin nombre registrado', ENT_QUOTES, 'UTF-8') ?></strong><br>
                                            <small class="text-muted">HC: <?= htmlspecialchars($cert['patient_id'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars(strtoupper($cert['document_type']), ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($cert['document_number'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $statusBadge = [
                                                'verified' => 'success',
                                                'pending' => 'warning',
                                                'revoked' => 'danger',
                                            ];
                                            $badge = $statusBadge[$cert['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($cert['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($cert['last_verification_at'])): ?>
                                                <span class="d-block"><?= htmlspecialchars($cert['last_verification_at'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <small class="text-muted">Resultado: <?= htmlspecialchars($cert['last_verification_result'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Sin verificaciones</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
