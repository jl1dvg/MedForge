(function () {
    'use strict';

    var root = document.querySelector('[data-feedback-widget]');
    if (!root) {
        return;
    }

    var configRaw = root.getAttribute('data-feedback-config') || '{}';
    var config = {};

    try {
        config = JSON.parse(configRaw);
    } catch (error) {
        config = {};
    }

    var form = root.querySelector('[data-feedback-form]');
    var moduleSelect = root.querySelector('[data-feedback-module]');
    var messageField = root.querySelector('[data-feedback-message]');
    var attachmentField = root.querySelector('[data-feedback-attachment]');
    var alertBox = root.querySelector('[data-feedback-alert]');
    var submitButton = root.querySelector('[data-feedback-submit]');
    var modalElement = root.querySelector('#feedbackWidgetModal');
    var modalInstance = null;

    function resolveModalInstance(element) {
        if (!element || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }

        if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
            return window.bootstrap.Modal.getOrCreateInstance(element);
        }

        if (typeof window.bootstrap.Modal.getInstance === 'function') {
            var existing = window.bootstrap.Modal.getInstance(element);
            if (existing) {
                return existing;
            }
        }

        try {
            return new window.bootstrap.Modal(element);
        } catch (error) {
            return null;
        }
    }

    modalInstance = resolveModalInstance(modalElement);

    function showAlert(type, text) {
        if (!alertBox) {
            return;
        }

        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = text;
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        if (!alertBox) {
            return;
        }

        alertBox.className = 'alert d-none';
        alertBox.textContent = '';
    }

    function setLoadingState(loading) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = loading;
        submitButton.textContent = loading ? 'Enviando...' : 'Enviar';
    }

    function buildModuleOptions() {
        if (!moduleSelect) {
            return;
        }

        var modules = Array.isArray(config.modules) ? config.modules : [];
        var selectedModuleKey = typeof config.selectedModuleKey === 'string' ? config.selectedModuleKey : 'general';

        if (!modules.length) {
            if (moduleSelect.options.length && selectedModuleKey) {
                moduleSelect.value = selectedModuleKey;
            }
            return;
        }

        moduleSelect.innerHTML = '';

        modules.forEach(function (module) {
            if (!module || typeof module !== 'object') {
                return;
            }

            var option = document.createElement('option');
            option.value = String(module.key || 'general');
            option.textContent = String(module.label || 'General / Plataforma');
            option.dataset.label = String(module.label || 'General / Plataforma');

            if (option.value === selectedModuleKey) {
                option.selected = true;
            }

            moduleSelect.appendChild(option);
        });
    }

    async function handleSubmit(event) {
        event.preventDefault();
        hideAlert();

        if (!form || !moduleSelect || !messageField) {
            return;
        }

        var selectedOption = moduleSelect.options[moduleSelect.selectedIndex];
        var message = messageField.value.trim();

        if (message.length < 10) {
            showAlert('warning', 'Agrega un poco más de detalle antes de enviar.');
            messageField.focus();
            return;
        }

        var payload = {
            module_key: moduleSelect.value,
            module_label: selectedOption ? selectedOption.textContent : 'General / Plataforma',
            report_type: String(form.elements.report_type.value || 'suggestion'),
            message: message,
            current_path: String(config.currentPath || window.location.pathname || '/'),
            page_title: String(config.pageTitle || document.title || '')
        };

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var formData = new FormData();
        Object.keys(payload).forEach(function (key) {
            formData.append(key, payload[key]);
        });

        if (attachmentField && attachmentField.files && attachmentField.files[0]) {
            formData.append('attachment', attachmentField.files[0]);
        }

        setLoadingState(true);

        try {
            var response = await fetch('/feedback/api/report', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            var result = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'No se pudo enviar el reporte.');
            }

            showAlert('success', result.message || 'Gracias. Tu reporte fue registrado.');
            form.reset();
            buildModuleOptions();

            window.setTimeout(function () {
                hideAlert();
                if (modalInstance) {
                    modalInstance.hide();
                }
            }, 1200);
        } catch (error) {
            showAlert('danger', error.message || 'No se pudo enviar el reporte.');
        } finally {
            setLoadingState(false);
        }
    }

    buildModuleOptions();

    if (form) {
        form.addEventListener('submit', handleSubmit);
    }

    if (modalElement) {
        modalElement.addEventListener('shown.bs.modal', function () {
            hideAlert();
            if (messageField) {
                messageField.focus();
            }
        });
    }
})();
