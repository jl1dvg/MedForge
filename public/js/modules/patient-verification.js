(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    const escapeMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    };

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => escapeMap[char] ?? char);
    }

    class CanvasSignaturePad {
        constructor(canvas, hiddenInput) {
            this.canvas = canvas;
            this.hiddenInput = hiddenInput;
            this.context = canvas.getContext('2d');
            this.isDrawing = false;
            this.hasContent = false;
            this.strokeStyle = '#111827';
            this.lineWidth = 2.2;
            this._initCanvas();
            this._bindEvents();
        }

        _initCanvas() {
            this.canvas.style.touchAction = 'none';
            this.clear();
        }

        _bindEvents() {
            const start = (event) => {
                event.preventDefault();
                this.isDrawing = true;
                const pos = this._getPosition(event);
                this.context.beginPath();
                this.context.moveTo(pos.x, pos.y);
            };

            const move = (event) => {
                if (!this.isDrawing) {
                    return;
                }
                event.preventDefault();
                const pos = this._getPosition(event);
                this.context.lineTo(pos.x, pos.y);
                this.context.strokeStyle = this.strokeStyle;
                this.context.lineWidth = this.lineWidth;
                this.context.lineCap = 'round';
                this.context.lineJoin = 'round';
                this.context.stroke();
                this.hasContent = true;
            };

            const end = (event) => {
                if (!this.isDrawing) {
                    return;
                }
                event.preventDefault();
                this.isDrawing = false;
                this.syncHiddenInput();
            };

            this.canvas.addEventListener('pointerdown', start);
            this.canvas.addEventListener('pointermove', move);
            this.canvas.addEventListener('pointerup', end);
            this.canvas.addEventListener('pointerleave', end);
            this.canvas.addEventListener('pointercancel', end);
        }

        _getPosition(event) {
            const rect = this.canvas.getBoundingClientRect();
            const clientX = event.clientX || (event.touches && event.touches[0]?.clientX) || 0;
            const clientY = event.clientY || (event.touches && event.touches[0]?.clientY) || 0;
            return {
                x: (clientX - rect.left) * (this.canvas.width / rect.width),
                y: (clientY - rect.top) * (this.canvas.height / rect.height)
            };
        }

        clear() {
            this.context.save();
            this.context.setTransform(1, 0, 0, 1, 0, 0);
            this.context.fillStyle = '#ffffff';
            this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.context.restore();
            this.context.beginPath();
            this.hasContent = false;
            if (this.hiddenInput) {
                this.hiddenInput.value = '';
            }
        }

        loadFromDataUrl(dataUrl) {
            if (!dataUrl) {
                return;
            }
            const image = new Image();
            image.onload = () => {
                this.context.save();
                this.context.setTransform(1, 0, 0, 1, 0, 0);
                this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.context.fillStyle = '#ffffff';
                this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
                const ratio = Math.min(
                    this.canvas.width / image.width,
                    this.canvas.height / image.height
                );
                const drawWidth = image.width * ratio;
                const drawHeight = image.height * ratio;
                const offsetX = (this.canvas.width - drawWidth) / 2;
                const offsetY = (this.canvas.height - drawHeight) / 2;
                this.context.drawImage(image, offsetX, offsetY, drawWidth, drawHeight);
                this.context.restore();
                this.hasContent = true;
                this.syncHiddenInput();
            };
            image.src = dataUrl;
        }

        syncHiddenInput() {
            if (!this.hiddenInput) {
                return '';
            }
            if (!this.hasContent) {
                this.hiddenInput.value = '';
                return '';
            }
            const data = this.canvas.toDataURL('image/png');
            this.hiddenInput.value = data;
            return data;
        }
    }

    function setupSignaturePad(config) {
        const canvas = document.getElementById(config.canvasId);
        const hiddenInput = document.getElementById(config.inputId);
        if (!canvas || !hiddenInput) {
            return null;
        }

        const pad = new CanvasSignaturePad(canvas, hiddenInput);

        if (config.clearAction) {
            const clearButton = document.querySelector(`[data-action="${config.clearAction}"]`);
            clearButton?.addEventListener('click', (event) => {
                event.preventDefault();
                pad.clear();
            });
        }

        if (config.loadInputId) {
            const uploadInput = document.getElementById(config.loadInputId);
            const loadButton = document.querySelector(`[data-action="load-from-file"][data-input="${config.loadInputId}"]`);
            if (loadButton && uploadInput) {
                loadButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    uploadInput.click();
                });
                uploadInput.addEventListener('change', () => {
                    const file = uploadInput.files && uploadInput.files[0];
                    if (!file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => {
                        pad.loadFromDataUrl(reader.result);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }

        return {
            syncHiddenInput: () => pad.syncHiddenInput(),
            clear: () => pad.clear(),
            load: (dataUrl) => pad.loadFromDataUrl(dataUrl),
            hasContent: () => pad.hasContent,
        };
    }

    function setupFaceCapture(config) {
        const video = document.getElementById(config.videoId);
        const canvas = document.getElementById(config.canvasId);
        const hiddenInput = document.getElementById(config.inputId);
        if (!video || !canvas || !hiddenInput) {
            return {
                syncInput() {},
                reset() {},
                stop() {},
            };
        }

        const context = canvas.getContext('2d');
        let mediaStream = null;
        let hasCapture = false;

        function stopStream() {
            if (mediaStream) {
                mediaStream.getTracks().forEach((track) => track.stop());
                mediaStream = null;
            }
            video.srcObject = null;
        }

        async function startCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('El navegador no permite acceso a la cámara. Puede cargar una imagen manualmente.');
                return;
            }
            try {
                mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                video.srcObject = mediaStream;
                video.classList.remove('d-none');
                canvas.classList.add('d-none');
            } catch (error) {
                console.error('No fue posible iniciar la cámara', error);
                alert('No se pudo acceder a la cámara. Verifique los permisos del navegador.');
            }
        }

        function captureFrame() {
            if (mediaStream && video.videoWidth > 0) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                hiddenInput.value = canvas.toDataURL('image/png');
                canvas.classList.remove('d-none');
                video.classList.add('d-none');
                hasCapture = true;
            }
        }

        function resetCapture() {
            stopStream();
            context.clearRect(0, 0, canvas.width, canvas.height);
            hiddenInput.value = '';
            hasCapture = false;
            canvas.classList.add('d-none');
            video.classList.remove('d-none');
        }

        if (config.startAction) {
            const startButton = document.querySelector(`[data-action="${config.startAction}"]`);
            startButton?.addEventListener('click', (event) => {
                event.preventDefault();
                startCamera();
            });
        }

        if (config.captureAction) {
            const captureButton = document.querySelector(`[data-action="${config.captureAction}"]`);
            captureButton?.addEventListener('click', (event) => {
                event.preventDefault();
                captureFrame();
            });
        }

        if (config.resetAction) {
            const resetButton = document.querySelector(`[data-action="${config.resetAction}"]`);
            resetButton?.addEventListener('click', (event) => {
                event.preventDefault();
                resetCapture();
            });
        }

        if (config.loadInputId) {
            const uploadInput = document.getElementById(config.loadInputId);
            const loadButton = document.querySelector(`[data-action="load-from-file"][data-input="${config.loadInputId}"]`);
            if (uploadInput && loadButton) {
                loadButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    uploadInput.click();
                });
                uploadInput.addEventListener('change', () => {
                    const file = uploadInput.files && uploadInput.files[0];
                    if (!file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => {
                        const image = new Image();
                        image.onload = () => {
                            canvas.width = image.width;
                            canvas.height = image.height;
                            context.drawImage(image, 0, 0, canvas.width, canvas.height);
                            hiddenInput.value = canvas.toDataURL('image/png');
                            canvas.classList.remove('d-none');
                            video.classList.add('d-none');
                            hasCapture = true;
                        };
                        image.src = reader.result;
                    };
                    reader.readAsDataURL(file);
                });
            }
        }

        return {
            syncInput() {
                if (hasCapture && hiddenInput.value === '' && !canvas.classList.contains('d-none')) {
                    hiddenInput.value = canvas.toDataURL('image/png');
                }
                return hiddenInput.value;
            },
            reset: resetCapture,
            stop: stopStream,
        };
    }

    function setBadgeState(element, state) {
        if (!element) {
            return;
        }
        element.classList.remove('bg-secondary', 'bg-success', 'bg-warning');
        if (state === 'ready') {
            element.classList.add('bg-success');
            element.textContent = 'Completo';
        } else if (state === 'partial') {
            element.classList.add('bg-warning');
            element.textContent = 'Pendiente parcial';
        } else {
            element.classList.add('bg-secondary');
            element.textContent = 'Pendiente';
        }
    }

    ready(function () {
        const stepOrder = ['lookup', 'register', 'checkin'];
        const stepperBadges = Array.from(document.querySelectorAll('#verificationStepper [data-step]'));
        const stepPanels = Array.from(document.querySelectorAll('.wizard-panel[data-step-panel]'));
        const summaryContainer = document.getElementById('certificationSummary');
        const missingDataAlert = document.getElementById('registrationMissingData');
        const registrationStatusBadge = document.getElementById('registrationStatusBadge');
        const checkinStatusBadge = document.getElementById('checkinStatusBadge');
        const checkinInstructions = document.getElementById('checkinInstructions');
        const checkinSignatureBlock = document.getElementById('checkinSignatureCapture');
        const verificationResult = document.getElementById('verificationResult');
        const consentWrapper = document.getElementById('consentDownloadWrapper');
        const consentLink = document.getElementById('consentDownloadLink');

        const registrationForm = document.getElementById('patientCertificationForm');
        const checkinForm = document.getElementById('verificationCheckinForm');
        const lookupForm = document.getElementById('verificationLookupForm');

        const registrationPatientId = document.getElementById('registrationPatientId');
        const registrationDocumentNumber = document.getElementById('registrationDocumentNumber');
        const registrationDocumentType = document.getElementById('registrationDocumentType');

        const checkinCertificationId = document.getElementById('checkinCertificationId');
        const checkinPatientId = document.getElementById('checkinPatientId');
        const checkinPatientLabel = document.getElementById('checkinPatientLabel');
        const checkinDocumentLabel = document.getElementById('checkinDocumentLabel');

        const signatureBadge = document.querySelector('[data-field-state="signature"]');
        const faceBadge = document.querySelector('[data-field-state="face"]');

        const patientSignaturePad = setupSignaturePad({
            canvasId: 'patientSignatureCanvas',
            inputId: 'signatureDataField',
            clearAction: 'clear-signature',
            loadInputId: 'signatureUpload'
        });
        const documentSignaturePad = setupSignaturePad({
            canvasId: 'documentSignatureCanvas',
            inputId: 'documentSignatureDataField',
            clearAction: 'clear-document-signature',
            loadInputId: 'documentSignatureUpload'
        });
        const verificationSignaturePad = setupSignaturePad({
            canvasId: 'verificationSignatureCanvas',
            inputId: 'verificationSignatureDataField',
            clearAction: 'clear-verification-signature',
            loadInputId: 'verificationSignatureUpload'
        });

        const faceCapture = setupFaceCapture({
            videoId: 'faceCaptureVideo',
            canvasId: 'faceCaptureCanvas',
            inputId: 'faceImageDataField',
            startAction: 'start-camera',
            captureAction: 'capture-face',
            resetAction: 'reset-face',
            loadInputId: 'faceUpload'
        });
        const verificationFaceCapture = setupFaceCapture({
            videoId: 'verificationFaceVideo',
            canvasId: 'verificationFaceCanvas',
            inputId: 'verificationFaceDataField',
            startAction: 'start-verification-camera',
            captureAction: 'capture-verification-face',
            resetAction: 'reset-verification-face',
            loadInputId: 'verificationFaceUpload'
        });

        const state = {
            step: 'lookup',
            certification: null,
            requiresSignature: false,
        };

        function setStep(step) {
            state.step = step;
            const index = stepOrder.indexOf(step);
            stepperBadges.forEach((badge) => {
                const badgeStep = badge.dataset.step;
                const badgeIndex = stepOrder.indexOf(badgeStep);
                badge.classList.remove('text-bg-primary', 'text-bg-secondary', 'text-bg-success');
                if (badgeIndex < index) {
                    badge.classList.add('text-bg-success');
                } else if (badgeIndex === index) {
                    badge.classList.add('text-bg-primary');
                } else {
                    badge.classList.add('text-bg-secondary');
                }
            });

            stepPanels.forEach((panel) => {
                const panelStep = panel.dataset.stepPanel;
                if (panelStep === step) {
                    panel.classList.remove('d-none');
                } else {
                    panel.classList.add('d-none');
                }
            });
        }

        function renderSummary(certification) {
            if (!summaryContainer) {
                return;
            }
            if (!certification) {
                summaryContainer.innerHTML = '<p class="text-muted mb-0">Busque un paciente para visualizar su estado, los datos faltantes y la última verificación registrada.</p>';
                return;
            }
            const missing = [];
            const hasSignature = Boolean(certification.signature_path && certification.signature_template);
            const hasFace = Boolean(certification.face_image_path && certification.face_template);
            if (!hasSignature) {
                missing.push('Firma manuscrita');
            }
            if (!hasFace) {
                missing.push('Captura facial');
            }
            if (!certification.document_number) {
                missing.push('Número de documento');
            }

            const statusKey = String(certification.status ?? 'pending').toLowerCase();
            const statusInfo = {
                verified: { badge: 'success', label: 'Verificada' },
                pending: { badge: 'warning', label: 'Pendiente' },
                expired: { badge: 'danger', label: 'Vencida' },
                revoked: { badge: 'danger', label: 'Revocada' },
            }[statusKey] || { badge: 'secondary', label: 'Sin certificación' };

            let statusAlert;
            if (statusKey === 'expired') {
                statusAlert = '<div class="alert alert-danger mt-3 mb-0"><strong>Certificación vencida.</strong> Capture nuevamente rostro y firma para habilitar el check-in.</div>';
            } else if (missing.length > 0) {
                statusAlert = `<div class="alert alert-warning mt-3"><strong>Datos faltantes:</strong> ${missing.map(escapeHtml).join(' · ')}</div>`;
            } else {
                statusAlert = '<div class="alert alert-success mt-3 mb-0"><strong>Certificación completa.</strong> Puede continuar con el check-in facial.</div>';
            }
            const lastVerification = certification.last_verification_at
                ? `<span class="d-block">${escapeHtml(certification.last_verification_at)}</span><small class="text-muted">Resultado: ${escapeHtml(certification.last_verification_result ?? 'N/A')}</small>`
                : '<span class="text-muted">Sin verificaciones registradas</span>';

            summaryContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div class="flex-grow-1">
                        <div class="mb-2">
                            <strong>Paciente:</strong>
                            <div>${escapeHtml(certification.full_name ?? 'Sin nombre registrado')}</div>
                            <small class="text-muted">HC: ${escapeHtml(certification.patient_id)}</small>
                        </div>
                        <div class="mb-2">
                            <strong>Documento:</strong>
                            <div>${escapeHtml((certification.document_type || '').toUpperCase())} · ${escapeHtml(certification.document_number || 'Sin registrar')}</div>
                        </div>
                        <div class="mb-2">
                            <strong>Estado de certificación:</strong>
                            <span class="badge bg-${statusInfo.badge}">${escapeHtml(statusInfo.label)}</span>
                        </div>
                        <div>
                            <strong>Última verificación:</strong>
                            ${lastVerification}
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete-certification">
                            <i class="mdi mdi-delete"></i> Eliminar certificación
                        </button>
                        <small class="d-block text-muted mt-2">Se eliminarán las capturas actuales y podrá registrar nuevamente los datos biométricos.</small>
                    </div>
                    </div>
                ${statusAlert}
            `;

            const deleteButton = summaryContainer.querySelector('[data-action="delete-certification"]');
            if (deleteButton) {
                deleteButton.dataset.id = String(certification.id ?? '');
                deleteButton.dataset.patient = String(certification.patient_id ?? '');
                deleteButton.dataset.document = String(certification.document_number ?? '');
            }
        }

        function updateRegistrationStep(certification, patientId, documentNumber) {
            setStep('register');
            if (registrationPatientId) {
                registrationPatientId.value = patientId ?? '';
            }
            if (registrationDocumentNumber) {
                registrationDocumentNumber.value = documentNumber ?? certification?.document_number ?? '';
            }
            if (registrationDocumentType) {
                if (certification?.document_type) {
                    registrationDocumentType.value = certification.document_type;
                } else {
                    registrationDocumentType.value = registrationDocumentType.dataset.default || 'cedula';
                }
            }

            const hasSignature = Boolean(certification?.signature_path && certification?.signature_template);
            const hasFace = Boolean(certification?.face_image_path && certification?.face_template);

            setBadgeState(signatureBadge, hasSignature ? 'ready' : 'pending');
            setBadgeState(faceBadge, hasFace ? 'ready' : 'pending');

            if (missingDataAlert) {
                if (!certification) {
                    missingDataAlert.classList.add('d-none');
                    missingDataAlert.textContent = '';
                } else {
                    const missingPieces = [];
                    if (!hasSignature) {
                        missingPieces.push('firma manuscrita');
                    }
                    if (!hasFace) {
                        missingPieces.push('captura facial');
                    }
                    if (!certification.document_number) {
                        missingPieces.push('número de documento');
                    }
                    if (missingPieces.length > 0) {
                        missingDataAlert.textContent = `Faltan por completar: ${missingPieces.join(', ')}.`;
                        missingDataAlert.classList.remove('d-none');
                    } else {
                        missingDataAlert.textContent = '';
                        missingDataAlert.classList.add('d-none');
                    }
                }
            }

            if (registrationStatusBadge) {
                registrationStatusBadge.textContent = certification ? 'Certificación existente' : 'Nuevo registro';
                registrationStatusBadge.classList.remove('bg-primary', 'bg-success', 'bg-warning');
                registrationStatusBadge.classList.add(certification && certification.status === 'verified' ? 'bg-success' : 'bg-primary');
            }

            patientSignaturePad?.clear?.();
            documentSignaturePad?.clear?.();
            faceCapture.reset();
        }

        function updateCheckinStep(certification) {
            if (!certification) {
                return;
            }
            setStep('checkin');
            state.requiresSignature = !certification.face_template && !!certification.signature_template;
            if (checkinCertificationId) {
                checkinCertificationId.value = certification.id ?? '';
            }
            if (checkinPatientId) {
                checkinPatientId.value = certification.patient_id ?? '';
            }
            if (checkinPatientLabel) {
                checkinPatientLabel.value = certification.patient_id ?? '';
            }
            if (checkinDocumentLabel) {
                const label = `${(certification.document_type || '').toUpperCase()} · ${certification.document_number || 'Sin registrar'}`;
                checkinDocumentLabel.value = label;
            }

            if (checkinStatusBadge) {
                checkinStatusBadge.textContent = certification.status === 'verified' ? 'Listo para check-in' : 'Certificación pendiente';
                checkinStatusBadge.classList.remove('bg-info', 'bg-warning', 'bg-success');
                checkinStatusBadge.classList.add(certification.status === 'verified' ? 'bg-success' : 'bg-warning');
            }

            if (checkinInstructions) {
                checkinInstructions.textContent = state.requiresSignature
                    ? 'Esta certificación aún no cuenta con plantilla facial. Capture la firma actual del paciente para continuar.'
                    : 'Capture el rostro del paciente para validar su identidad.';
            }

            if (checkinSignatureBlock) {
                if (state.requiresSignature) {
                    checkinSignatureBlock.classList.remove('d-none');
                } else {
                    checkinSignatureBlock.classList.add('d-none');
                }
            }

            verificationResult?.classList.add('d-none');
            consentWrapper?.classList.add('d-none');
            consentLink?.setAttribute('href', '#');
            verificationSignaturePad?.clear?.();
            verificationFaceCapture.reset();
            verificationSignaturePad?.syncHiddenInput?.();
            verificationFaceCapture.syncInput();
        }

        async function fetchCertification(patientId, documentNumber) {
            const params = new URLSearchParams();
            if (patientId) {
                params.set('patient_id', patientId);
            }
            const url = `/pacientes/certificaciones/detalle?${params.toString()}`;
            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    throw new Error('No encontrado');
                }
                const payload = await response.json();
                if (!payload.ok) {
                    throw new Error('No encontrado');
                }
                return payload.data;
            } catch (error) {
                if (documentNumber) {
                    registrationDocumentNumber.value = documentNumber;
                }
                return null;
            }
        }

        lookupForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(lookupForm);
            const patientId = String(formData.get('patient_id') || '').trim();
            const documentNumber = String(formData.get('document_number') || '').trim();
            if (!patientId) {
                alert('Ingrese un identificador de paciente.');
                return;
            }

            const submitButton = lookupForm.querySelector('button[type="submit"]');
            submitButton && (submitButton.disabled = true);
            const certification = await fetchCertification(patientId, documentNumber);
            submitButton && (submitButton.disabled = false);

            state.certification = certification;
            renderSummary(certification);

            if (certification) {
                registrationPatientId.value = certification.patient_id;
                registrationDocumentNumber.value = certification.document_number || documentNumber;
                if (registrationDocumentType && certification.document_type) {
                    registrationDocumentType.value = certification.document_type;
                }

                const hasSignature = Boolean(certification.signature_path && certification.signature_template);
                const hasFace = Boolean(certification.face_image_path && certification.face_template);
                setBadgeState(signatureBadge, hasSignature ? 'ready' : 'pending');
                setBadgeState(faceBadge, hasFace ? 'ready' : 'pending');

                if (hasSignature && hasFace && certification.status === 'verified') {
                    updateCheckinStep(certification);
                } else {
                    updateRegistrationStep(certification, certification.patient_id, certification.document_number || documentNumber);
                }
            } else {
                updateRegistrationStep(null, patientId, documentNumber);
                renderSummary(null);
            }
        });

        document.querySelector('[data-action="start-registration"]')?.addEventListener('click', () => {
            const patientId = document.getElementById('lookupPatientId')?.value.trim();
            const documentNumber = document.getElementById('lookupDocument')?.value.trim();
            if (!patientId) {
                alert('Debe ingresar la historia clínica para iniciar el registro.');
                return;
            }
            state.certification = null;
            renderSummary(null);
            updateRegistrationStep(null, patientId, documentNumber);
        });

        document.querySelector('[data-action="back-to-lookup"]')?.addEventListener('click', () => {
            setStep('lookup');
        });

        document.querySelector('[data-action="back-to-registration"]')?.addEventListener('click', () => {
            if (state.certification) {
                updateRegistrationStep(state.certification, state.certification.patient_id, state.certification.document_number);
            } else {
                setStep('register');
            }
        });

        summaryContainer?.addEventListener('click', async (event) => {
            const target = event.target instanceof Element ? event.target.closest('[data-action="delete-certification"]') : null;
            if (!target) {
                return;
            }
            event.preventDefault();

            const certificationId = target.dataset.id || '';
            const patientId = target.dataset.patient || '';
            const documentNumber = target.dataset.document || '';

            if (!certificationId && !patientId) {
                return;
            }

            const confirmMessage = patientId
                ? `¿Desea eliminar la certificación biométrica registrada para el paciente ${patientId}?`
                : '¿Desea eliminar la certificación biométrica seleccionada?';
            if (!window.confirm(confirmMessage)) {
                return;
            }

            target.disabled = true;

            try {
                const formData = new FormData();
                if (certificationId) {
                    formData.append('certification_id', certificationId);
                }
                if (patientId) {
                    formData.append('patient_id', patientId);
                }

                const response = await fetch('/pacientes/certificaciones/eliminar', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No se pudo eliminar la certificación.');
                }

                state.certification = null;
                state.requiresSignature = false;

                updateRegistrationStep(null, patientId || '', documentNumber || '');

                if (summaryContainer) {
                    const message = escapeHtml(payload.message || 'La certificación biométrica se eliminó correctamente.');
                    summaryContainer.innerHTML = `<div class="alert alert-success mb-2">${message}</div><p class="text-muted mb-0">Puede volver a capturar los datos biométricos en el formulario de registro.</p>`;
                }

                if (missingDataAlert) {
                    missingDataAlert.classList.add('d-none');
                    missingDataAlert.textContent = '';
                }

                if (registrationPatientId) {
                    registrationPatientId.value = patientId || registrationPatientId.value;
                }
                if (registrationDocumentNumber && documentNumber) {
                    registrationDocumentNumber.value = documentNumber;
                }

                if (checkinCertificationId) {
                    checkinCertificationId.value = '';
                }
                if (checkinPatientId) {
                    checkinPatientId.value = patientId || '';
                }
                if (checkinPatientLabel) {
                    checkinPatientLabel.value = patientId || '';
                }
                if (checkinDocumentLabel) {
                    const label = documentNumber ? `CEDULA · ${documentNumber}` : '';
                    checkinDocumentLabel.value = label;
                }

                if (checkinStatusBadge) {
                    checkinStatusBadge.textContent = 'En espera';
                    checkinStatusBadge.classList.remove('bg-success', 'bg-warning');
                    if (!checkinStatusBadge.classList.contains('bg-info')) {
                        checkinStatusBadge.classList.add('bg-info');
                    }
                }

                if (checkinInstructions) {
                    checkinInstructions.textContent = 'Capture el rostro del paciente para validar su identidad. Si la certificación no tiene plantilla facial aún, se le solicitará la firma.';
                }

                verificationResult?.classList.add('d-none');
                consentWrapper?.classList.add('d-none');
                consentLink?.setAttribute('href', '#');

                verificationSignaturePad?.clear?.();
                verificationFaceCapture.reset();
                verificationFaceCapture.syncInput();

                setBadgeState(signatureBadge, 'pending');
                setBadgeState(faceBadge, 'pending');
            } catch (error) {
                console.error('Error al eliminar la certificación', error);
                alert(error instanceof Error ? error.message : 'No se pudo eliminar la certificación.');
            } finally {
                target.disabled = false;
            }
        });

        registrationForm?.addEventListener('submit', () => {
            patientSignaturePad?.syncHiddenInput?.();
            documentSignaturePad?.syncHiddenInput?.();
            faceCapture?.syncInput?.();
        });

        checkinForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            verificationSignaturePad?.syncHiddenInput?.();
            verificationFaceCapture?.syncInput?.();

            const submitButton = checkinForm.querySelector('button[type="submit"]');
            submitButton && (submitButton.disabled = true);

            try {
                const formData = new FormData(checkinForm);
                const response = await fetch(checkinForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const data = await response.json();
                const alertBox = verificationResult?.querySelector('.alert');
                if (!alertBox || !verificationResult) {
                    return;
                }

                verificationResult.classList.remove('d-none');

                if (!response.ok || !data.ok) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message ? String(data.message) : 'No se pudo validar la identidad del paciente.';
                    consentWrapper?.classList.add('d-none');
                    return;
                }

                const signatureScore = data.signatureScore != null ? `Firma: ${Number(data.signatureScore).toFixed(2)}%` : 'Firma no evaluada';
                const faceScore = data.faceScore != null ? `Rostro: ${Number(data.faceScore).toFixed(2)}%` : 'Rostro no evaluado';

                let statusClass = 'alert-warning';
                let statusLabel = 'Revisión manual requerida';
                if (data.result === 'approved') {
                    statusClass = 'alert-success';
                    statusLabel = 'Paciente verificado';
                } else if (data.result === 'rejected') {
                    statusClass = 'alert-danger';
                    statusLabel = 'Verificación rechazada';
                }

                alertBox.className = `alert ${statusClass}`;
                alertBox.innerHTML = `<strong>${statusLabel}</strong><br>${signatureScore} · ${faceScore}`;

                if (data.consentDocument) {
                    consentWrapper?.classList.remove('d-none');
                    consentLink?.setAttribute('href', `/${data.consentDocument}`);
                } else {
                    consentWrapper?.classList.add('d-none');
                }

                if (state.certification) {
                    state.certification.last_verification_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    state.certification.last_verification_result = data.result;
                    renderSummary(state.certification);
                }
            } catch (error) {
                console.error('Error en la verificación', error);
                const alertBox = verificationResult?.querySelector('.alert');
                if (alertBox && verificationResult) {
                    verificationResult.classList.remove('d-none');
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = 'Ocurrió un error inesperado al verificar la identidad.';
                }
            } finally {
                submitButton && (submitButton.disabled = false);
            }
        });

        // Inicialización por defecto
        setStep('lookup');
        renderSummary(state.certification);
    });
})();
