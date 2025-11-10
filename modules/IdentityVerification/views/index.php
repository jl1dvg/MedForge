<?php
/** @var array $certifications */
/** @var array $errors */
/** @var array $old */
/** @var array|null $selectedPatient */
/** @var string|null $status */
?>
<div class="content-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h3 class="page-title mb-1">Certificación biométrica de pacientes</h3>
            <p class="text-muted mb-0">Siga los pasos para completar el registro biométrico y realizar el check-in facial en las atenciones.</p>
        </div>
        <div>
            <a href="/pacientes" class="btn btn-secondary"><i class="mdi mdi-arrow-left"></i> Volver a pacientes</a>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-xxl-7 col-xl-8">
            <?php if (!empty($status) && $status === 'stored'): ?>
                <div class="alert alert-success"><i class="mdi mdi-check-circle"></i> La certificación se guardó correctamente.</div>
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

            <div class="box">
                <div class="box-body">
                    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap" id="verificationStepper">
                        <div class="badge rounded-pill text-bg-primary" data-step="lookup">1. Búsqueda</div>
                        <span class="text-muted"><i class="mdi mdi-chevron-right"></i></span>
                        <div class="badge rounded-pill text-bg-secondary" data-step="register">2. Registro biométrico</div>
                        <span class="text-muted"><i class="mdi mdi-chevron-right"></i></span>
                        <div class="badge rounded-pill text-bg-secondary" data-step="checkin">3. Check-in facial</div>
                    </div>

                    <div class="wizard-panel" data-step-panel="lookup">
                        <h5 class="mb-3">1. Buscar paciente</h5>
                        <p class="text-muted">Ingrese la historia clínica o documento para localizar al paciente y verificar si cuenta con certificación biométrica.</p>
                        <form id="verificationLookupForm" class="row g-3">
                            <div class="col-md-6">
                                <label for="lookupPatientId" class="form-label">Historia clínica / ID interno<span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="lookupPatientId" name="patient_id" value="<?= htmlspecialchars($old['patient_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lookupDocument" class="form-label">Cédula del paciente</label>
                                <input type="text" class="form-control" id="lookupDocument" name="document_number" value="<?= htmlspecialchars($old['document_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary"><i class="mdi mdi-account-search"></i> Buscar paciente</button>
                                <button type="button" class="btn btn-outline-secondary" data-action="start-registration">Iniciar registro sin resultados</button>
                            </div>
                        </form>
                        <?php if (!empty($selectedPatient)): ?>
                            <div class="alert alert-info mt-3">
                                <strong>Paciente preseleccionado:</strong>
                                <div><?= htmlspecialchars($selectedPatient['full_name'] ?: ('Historia clínica ' . ($selectedPatient['hc_number'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted">HC: <?= htmlspecialchars($selectedPatient['hc_number'] ?? '', ENT_QUOTES, 'UTF-8') ?><?php if (!empty($selectedPatient['cedula'])): ?> · Cédula registrada: <?= htmlspecialchars($selectedPatient['cedula'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wizard-panel d-none" data-step-panel="register">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">2. Completar certificación biométrica</h5>
                            <span class="badge bg-primary" id="registrationStatusBadge">Nuevo registro</span>
                        </div>
                        <div class="alert alert-warning d-none" id="registrationMissingData"></div>
                        <form id="patientCertificationForm" action="/pacientes/certificaciones" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="patient_id" id="registrationPatientId" value="<?= htmlspecialchars($old['patient_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="registrationDocumentNumber" class="form-label">Cédula de identidad<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="registrationDocumentNumber" name="document_number" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="registrationDocumentType" class="form-label">Tipo de documento</label>
                                    <select class="form-select" id="registrationDocumentType" name="document_type" data-default="cedula">
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
                            </div>

                            <hr class="my-4">

                            <div class="mb-4" data-biometric="signature">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Firma manuscrita del paciente</label>
                                    <span class="badge bg-secondary" data-field-state="signature">Pendiente</span>
                                </div>
                                <div class="signature-pad" data-target="signature">
                                    <canvas id="patientSignatureCanvas" width="520" height="220" class="border rounded bg-white w-100"></canvas>
                                    <div class="mt-2 d-flex gap-2 flex-wrap">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-action="clear-signature">Limpiar</button>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="signatureUpload">Cargar imagen</button>
                                        <input type="file" accept="image/*" class="d-none" id="signatureUpload">
                                    </div>
                                </div>
                                <input type="hidden" name="signature_data" id="signatureDataField">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Firma del documento (opcional)</label>
                                <div class="signature-pad" data-target="document-signature">
                                    <canvas id="documentSignatureCanvas" width="520" height="160" class="border rounded bg-white w-100"></canvas>
                                    <div class="mt-2 d-flex gap-2 flex-wrap">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-action="clear-document-signature">Limpiar</button>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="documentSignatureUpload">Cargar imagen</button>
                                        <input type="file" accept="image/*" class="d-none" id="documentSignatureUpload">
                                    </div>
                                </div>
                                <input type="hidden" name="document_signature_data" id="documentSignatureDataField">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Fotografías del documento (opcional)</label>
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

                            <div class="mb-4" data-biometric="face">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Captura facial del paciente</label>
                                    <span class="badge bg-secondary" data-field-state="face">Pendiente</span>
                                </div>
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
                                    <small class="text-muted d-block mt-2">Para mayor precisión procure buena iluminación y que el rostro ocupe la mayor parte del encuadre.</small>
                                </div>
                                <input type="hidden" name="face_image" id="faceImageDataField">
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-secondary" data-action="back-to-lookup"><i class="mdi mdi-chevron-left"></i> Volver a búsqueda</button>
                                <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> Guardar certificación</button>
                            </div>
                        </form>
                    </div>

                    <div class="wizard-panel d-none" data-step-panel="checkin">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">3. Check-in facial</h5>
                            <span class="badge bg-info" id="checkinStatusBadge">En espera</span>
                        </div>
                        <div class="alert alert-info" id="checkinInstructions">Capture el rostro del paciente para validar su identidad. Si la certificación no tiene plantilla facial aún, se le solicitará la firma.</div>
                        <form id="verificationCheckinForm" action="/pacientes/certificaciones/verificar" method="post">
                            <input type="hidden" name="certification_id" id="checkinCertificationId">
                            <input type="hidden" name="patient_id" id="checkinPatientId">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Historia clínica</label>
                                    <input type="text" class="form-control" id="checkinPatientLabel" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Documento</label>
                                    <input type="text" class="form-control" id="checkinDocumentLabel" readonly>
                                </div>
                            </div>

                            <div class="mb-4" id="checkinFaceCapture">
                                <label class="form-label d-block">Captura facial (momento actual)<span class="text-danger">*</span></label>
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
                                <input type="hidden" name="face_image" id="verificationFaceDataField">
                            </div>

                            <div class="mb-4 d-none" id="checkinSignatureCapture">
                                <label class="form-label d-block">Firma actual del paciente<span class="text-danger">*</span></label>
                                <div class="signature-pad" data-target="verification-signature">
                                    <canvas id="verificationSignatureCanvas" width="520" height="180" class="border rounded bg-white w-100"></canvas>
                                    <div class="mt-2 d-flex gap-2 flex-wrap">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-action="clear-verification-signature">Limpiar</button>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-action="load-from-file" data-input="verificationSignatureUpload">Cargar imagen</button>
                                        <input type="file" accept="image/*" class="d-none" id="verificationSignatureUpload">
                                    </div>
                                </div>
                                <input type="hidden" name="signature_data" id="verificationSignatureDataField">
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-secondary" data-action="back-to-registration"><i class="mdi mdi-chevron-left"></i> Volver al registro</button>
                                <button type="submit" class="btn btn-success"><i class="mdi mdi-shield-check"></i> Verificar identidad</button>
                            </div>
                        </form>
                        <div id="verificationResult" class="mt-4 d-none">
                            <div class="alert" role="alert"></div>
                            <div class="mt-3 d-none" id="consentDownloadWrapper">
                                <a href="#" class="btn btn-outline-primary" id="consentDownloadLink" target="_blank"><i class="mdi mdi-file-download"></i> Abrir documento de consentimiento</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-5 col-xl-4">
            <div class="box mb-3">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Resumen de certificación</h4>
                </div>
                <div class="box-body" id="certificationSummary">
                    <p class="text-muted mb-0">Busque un paciente para visualizar su estado, los datos faltantes y la última verificación registrada.</p>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h4 class="box-title mb-0">Certificaciones recientes</h4>
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
                                    <th>Último check-in</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($certifications)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Todavía no existen certificaciones registradas.</td>
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
                                                'expired' => 'danger',
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
