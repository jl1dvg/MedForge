(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
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
            if (clearButton) {
                clearButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    pad.clear();
                });
            }
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

        return pad;
    }

    function setupFaceCapture(config) {
        const video = document.getElementById(config.videoId);
        const canvas = document.getElementById(config.canvasId);
        const hiddenInput = document.getElementById(config.inputId);
        if (!video || !canvas || !hiddenInput) {
            return {
                syncInput() {},
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

    function setupVerificationForm(faceCapture) {
        const form = document.getElementById('verificationForm');
        const resultContainer = document.getElementById('verificationResult');
        if (!form || !resultContainer) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            faceCapture?.syncInput?.();

            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                const alertBox = resultContainer.querySelector('.alert');
                resultContainer.hidden = false;

                if (!response.ok || !data.ok) {
                    alertBox.className = 'alert alert-danger';
                    let errorMessage = data.message ? String(data.message) : 'No se pudo validar la identidad del paciente.';
                    if (Array.isArray(data.missing) && data.missing.length > 0) {
                        errorMessage += `<br><small>Faltante: ${data.missing.join(', ')}</small>`;
                    }
                    alertBox.innerHTML = errorMessage;
                    return;
                }

                const faceScore = data.faceScore != null ? `Rostro: ${data.faceScore}%` : 'Rostro no evaluado';
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
                let extraHtml = faceScore;
                if (data.consentUrl) {
                    extraHtml += `<br><a class="btn btn-sm btn-outline-light mt-2" href="${data.consentUrl}" target="_blank" rel="noopener">Generar documento de atención</a>`;
                }
                alertBox.innerHTML = `<strong>${statusLabel}</strong><br>${extraHtml}`;
            } catch (error) {
                console.error('Error en la verificación', error);
                const alertBox = resultContainer.querySelector('.alert');
                resultContainer.hidden = false;
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Ocurrió un error inesperado al verificar la identidad.';
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    }

    ready(function () {
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

        const certificationForm = document.getElementById('patientCertificationForm');
        certificationForm?.addEventListener('submit', () => {
            patientSignaturePad?.syncHiddenInput?.();
            documentSignaturePad?.syncHiddenInput?.();
            faceCapture?.syncInput?.();
        });

        setupVerificationForm(verificationFaceCapture);
    });
})();
