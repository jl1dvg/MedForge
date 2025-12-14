(function () {
    'use strict';

    const MAX_SIZE_BYTES = 2 * 1024 * 1024;

    const FIELD_CONFIG = {
        firma_file: {
            label: 'sello',
            accepted: ['image/png', 'image/webp', 'image/svg+xml'],
        },
        signature_file: {
            label: 'firma digital',
            accepted: ['image/png', 'image/webp', 'image/svg+xml'],
        },
        profile_photo_file: {
            label: 'foto de perfil',
            accepted: ['image/png', 'image/jpeg', 'image/webp'],
        },
    };

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    function setLiveStatus(message) {
        const region = document.getElementById('userUploadA11yStatus');
        if (region) {
            region.textContent = message || '';
        }
    }

    function formatBytes(bytes) {
        if (!bytes || Number.isNaN(bytes)) {
            return '0 B';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** exponent;
        return `${value.toFixed(exponent === 0 ? 0 : 1)} ${units[exponent]}`;
    }

    function renderPreview(previewBox, file, dataUrl) {
        if (!previewBox) {
            return;
        }
        previewBox.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex align-items-center gap-2';

        const img = document.createElement('img');
        img.src = dataUrl;
        img.alt = `Vista previa de ${file.name}`;
        img.className = 'border rounded';
        img.style.maxHeight = '96px';
        img.style.maxWidth = '160px';

        const text = document.createElement('div');
        text.className = 'small text-muted';
        text.textContent = `${file.name} · ${formatBytes(file.size)}`;

        wrapper.appendChild(img);
        wrapper.appendChild(text);
        previewBox.appendChild(wrapper);
    }

    function setupUpload(fieldId, config) {
        const input = document.getElementById(fieldId);
        if (!input) {
            return;
        }

        const dropZone = document.querySelector(`[data-upload-drop-zone="${fieldId}"]`);
        const progressContainer = document.querySelector(`[data-upload-progress="${fieldId}"]`);
        const progressBar = progressContainer?.querySelector('.progress-bar') || null;
        const errorBox = document.querySelector(`[data-upload-error="${fieldId}"]`);
        const previewBox = document.querySelector(`[data-upload-preview="${fieldId}"]`);
        const triggers = document.querySelectorAll(`[data-upload-trigger="${fieldId}"]`);

        const clearError = () => {
            if (errorBox) {
                errorBox.textContent = '';
                errorBox.classList.add('d-none');
            }
        };

        const setError = (message) => {
            if (errorBox) {
                errorBox.textContent = message || '';
                errorBox.classList.toggle('d-none', !message);
            }
            if (message) {
                setLiveStatus(message);
            }
        };

        const resetProgress = () => {
            if (progressContainer && progressBar) {
                progressContainer.classList.add('d-none');
                progressBar.style.width = '0%';
                progressBar.textContent = '0%';
            }
        };

        const setProgress = (percent) => {
            if (!progressContainer || !progressBar) {
                return;
            }
            const value = Math.min(100, Math.max(0, percent));
            progressContainer.classList.remove('d-none');
            progressBar.style.width = `${value}%`;
            progressBar.textContent = `${Math.round(value)}%`;
            if (value >= 100) {
                setTimeout(resetProgress, 900);
            }
        };

        const handleFile = (file) => {
            clearError();
            if (!file) {
                return;
            }

            if (file.size > MAX_SIZE_BYTES) {
                setError('El archivo supera el límite de 2 MB.');
                return;
            }

            if (file.type && !config.accepted.includes(file.type)) {
                setError('Formato no permitido. Usa un archivo de imagen válido.');
                return;
            }

            setLiveStatus(`Cargando ${config.label}`);
            const reader = new FileReader();

            reader.onprogress = (event) => {
                if (event.lengthComputable) {
                    const percent = (event.loaded / event.total) * 100;
                    setProgress(percent);
                }
            };

            reader.onerror = () => {
                setError('No se pudo leer el archivo. Intenta nuevamente.');
                resetProgress();
            };

            reader.onload = () => {
                setProgress(100);
                renderPreview(previewBox, file, reader.result);
                setLiveStatus(`Vista previa lista para ${file.name}`);
            };

            reader.readAsDataURL(file);
        };

        input.addEventListener('change', () => handleFile(input.files && input.files[0]));

        if (dropZone) {
            ['dragenter', 'dragover'].forEach((eventName) => {
                dropZone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropZone.classList.add('drop-zone--active');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropZone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropZone.classList.remove('drop-zone--active');
                });
            });

            dropZone.addEventListener('drop', (event) => {
                const file = event.dataTransfer?.files?.[0];
                handleFile(file);
            });

            dropZone.addEventListener('click', () => input.click());

            dropZone.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    input.click();
                }
            });
        }

        triggers.forEach((button) => {
            button.addEventListener('click', () => input.click());
        });

        const initialError = errorBox?.textContent?.trim();
        if (initialError) {
            setLiveStatus(initialError);
        }
    }

    function focusValidationAlert() {
        const alert = document.querySelector('[data-validation-alert]');
        if (alert) {
            alert.focus();
        }
    }

    ready(() => {
        Object.entries(FIELD_CONFIG).forEach(([fieldId, config]) => setupUpload(fieldId, config));
        focusValidationAlert();
    });
})();
