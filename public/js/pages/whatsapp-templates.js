(function () {
    'use strict';

    function onReady(callback) {
        if (document.readyState !== 'loading') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback);
        }
    }

    function parseJson(value, fallback) {
        if (typeof value !== 'string' || value.trim() === '') {
            return fallback;
        }

        try {
            var parsed = JSON.parse(value);
            return parsed === null ? fallback : parsed;
        } catch (error) {
            return fallback;
        }
    }

    function formatStatus(status) {
        if (!status) {
            return '<span class="badge bg-secondary-light text-secondary">Desconocido</span>';
        }

        var normalized = String(status).toUpperCase();
        var map = {
            APPROVED: { label: 'Aprobada', classes: 'bg-success-light text-success' },
            PENDING: { label: 'Pendiente', classes: 'bg-warning-light text-warning' },
            REJECTED: { label: 'Rechazada', classes: 'bg-danger-light text-danger' },
            DISABLED: { label: 'Deshabilitada', classes: 'bg-secondary-light text-secondary' },
            IN_APPEAL: { label: 'En apelación', classes: 'bg-info-light text-info' },
            PAUSED: { label: 'Pausada', classes: 'bg-info-light text-info' }
        };

        var entry = map[normalized] || { label: normalized.toLowerCase(), classes: 'bg-secondary-light text-secondary' };

        return '<span class="badge ' + entry.classes + '">' + entry.label + '</span>';
    }

    function formatQuality(score) {
        if (!score) {
            return '<span class="badge bg-secondary-light text-secondary">N/D</span>';
        }

        var normalized = String(score).toUpperCase();
        var map = {
            GREEN: { label: 'Alta', classes: 'bg-success-light text-success' },
            YELLOW: { label: 'Media', classes: 'bg-warning-light text-warning' },
            RED: { label: 'Baja', classes: 'bg-danger-light text-danger' }
        };

        var entry = map[normalized] || { label: normalized.toLowerCase(), classes: 'bg-secondary-light text-secondary' };

        return '<span class="badge ' + entry.classes + '">' + entry.label + '</span>';
    }

    function formatObservation(template) {
        if (!template) {
            return '—';
        }

        var status = (template.status || '').toUpperCase();
        var reason = template.rejected_reason || template.rejection_reason || template.reason || '';

        if (status === 'REJECTED') {
            if (reason) {
                return '<span class="text-danger small" title="' + escapeHtml(reason) + '">' + escapeHtml(reason) + '</span>';
            }
            return '<span class="text-danger small">Rechazada por Meta</span>';
        }

        if (status === 'PENDING') {
            return '<span class="text-warning small">Pendiente de aprobación</span>';
        }

        if (status === 'IN_APPEAL') {
            return '<span class="text-info small">En apelación</span>';
        }

        return '—';
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        try {
            return new Intl.DateTimeFormat('es-EC', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        } catch (error) {
            return date.toISOString();
        }
    }

    function buildRequestOptions(payload) {
        return {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        };
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function (response) {
            return response
                .json()
                .catch(function () {
                    return {};
                })
                .then(function (body) {
                    return { ok: response.ok, status: response.status, body: body };
                });
        });
    }

    function showFeedback(element, type, message) {
        if (!element) {
            return;
        }

        element.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        element.classList.add('alert-' + type);
        element.textContent = message;
    }

    function hideFeedback(element) {
        if (!element) {
            return;
        }

        element.classList.add('d-none');
        element.textContent = '';
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    onReady(function () {
        var root = document.getElementById('whatsapp-templates-root');
        if (!root) {
            return;
        }

        var state = {
            templates: [],
            filtered: [],
            enabled: root.dataset.enabled === '1',
            loading: false,
            selected: null
        };

        var bootstrapData = parseJson(root.dataset.bootstrap, {});
        var endpoints = {
            list: root.dataset.endpointList || '',
            create: root.dataset.endpointCreate || '',
            update: root.dataset.endpointUpdate || '',
            delete: root.dataset.endpointDelete || ''
        };

        var tableBody = root.querySelector('[data-table-body]');
        var loader = root.querySelector('[data-loading-indicator]');
        var emptyState = root.querySelector('[data-empty-state]');
        var feedback = root.querySelector('[data-feedback]');
        var refreshButton = root.querySelector('[data-action="refresh"]');
        var newButton = root.querySelector('[data-action="new-template"]');
        var searchInput = root.querySelector('#template-search');
        var statusFilter = root.querySelector('#template-status-filter');
        var languageFilter = root.querySelector('#template-language-filter');
        var categoryFilter = root.querySelector('#template-category-filter');

        var modalElement = document.getElementById('whatsapp-template-modal');
        var modal = modalElement ? new bootstrap.Modal(modalElement) : null;
        var modalForm = modalElement ? modalElement.querySelector('[data-template-form]') : null;
        var modalTitle = modalElement ? modalElement.querySelector('.modal-title') : null;
        var modalFeedback = modalElement ? modalElement.querySelector('[data-modal-feedback]') : null;
        var deleteButton = modalElement ? modalElement.querySelector('[data-action="delete-template"]') : null;
        var fieldId = modalElement ? modalElement.querySelector('[data-field="id"]') : null;
        var fieldName = modalElement ? modalElement.querySelector('[data-field="name"]') : null;
        var fieldLanguage = modalElement ? modalElement.querySelector('[data-field="language"]') : null;
        var fieldCategory = modalElement ? modalElement.querySelector('[data-field="category"]') : null;
        var headerTypeSelect = modalElement ? modalElement.querySelector('[data-builder-header-type]') : null;
        var headerTextWrapper = modalElement ? modalElement.querySelector('[data-builder-header-text]') : null;
        var headerInput = modalElement ? modalElement.querySelector('[data-builder-header-input]') : null;
        var headerWarning = modalElement ? modalElement.querySelector('[data-builder-header-warning]') : null;
        var bodyInput = modalElement ? modalElement.querySelector('[data-builder-body]') : null;
        var footerInput = modalElement ? modalElement.querySelector('[data-builder-footer]') : null;
        var addButton = modalElement ? modalElement.querySelector('[data-builder-add-button]') : null;
        var buttonsContainer = modalElement ? modalElement.querySelector('[data-builder-buttons]') : null;
        var builderPreview = modalElement ? modalElement.querySelector('[data-builder-preview]') : null;
        var insertVariableButtons = modalElement ? modalElement.querySelectorAll('[data-insert-variable]') : [];
        var fieldAllowCategoryChange = modalElement ? modalElement.querySelector('[data-field="allow_category_change"]') : null;
        var submitButton = modalElement ? modalElement.querySelector('button[type="submit"]') : null;

        var previewElement = document.getElementById('whatsapp-template-preview');
        var previewModal = previewElement ? new bootstrap.Modal(previewElement) : null;
        var previewName = previewElement ? previewElement.querySelector('[data-preview-name]') : null;
        var previewLanguage = previewElement ? previewElement.querySelector('[data-preview-language]') : null;
        var previewStatus = previewElement ? previewElement.querySelector('[data-preview-status]') : null;
        var previewBubble = previewElement ? previewElement.querySelector('[data-preview-bubble]') : null;
        var previewReason = previewElement ? previewElement.querySelector('[data-preview-reason]') : null;


        function setLoading(isLoading) {
            state.loading = isLoading;
            if (!loader || !tableBody || !emptyState) {
                return;
            }

            if (isLoading) {
                loader.classList.remove('d-none');
                emptyState.classList.add('d-none');
                tableBody.innerHTML = '';
            } else {
                loader.classList.add('d-none');
            }
        }

        function applyFilters() {
            var search = searchInput ? searchInput.value.trim().toLowerCase() : '';
            var status = statusFilter ? statusFilter.value.trim().toLowerCase() : '';
            var language = languageFilter ? languageFilter.value.trim().toLowerCase() : '';
            var category = categoryFilter ? categoryFilter.value.trim().toLowerCase() : '';

            state.filtered = state.templates.filter(function (template) {
                var matchesSearch = true;
                if (search) {
                    var target = [template.name, template.language, template.category, template.status]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();
                    matchesSearch = target.indexOf(search) !== -1;
                }

                var matchesStatus = !status || (template.status && template.status.toLowerCase() === status);
                var matchesLanguage = !language || (template.language && template.language.toLowerCase() === language);
                var matchesCategory = !category || (template.category && template.category.toLowerCase() === category);

                return matchesSearch && matchesStatus && matchesLanguage && matchesCategory;
            });

            renderTemplates();
        }

        function renderTemplates() {
            if (!tableBody || !emptyState) {
                return;
            }

            tableBody.innerHTML = '';

            if (state.loading) {
                return;
            }

            if (state.filtered.length === 0) {
                emptyState.classList.remove('d-none');
                return;
            }

            emptyState.classList.add('d-none');

            state.filtered.forEach(function (template) {
                var tr = document.createElement('tr');

                var nameCell = document.createElement('td');
                nameCell.classList.add('fw-600');
                nameCell.textContent = template.name || '';
                tr.appendChild(nameCell);

                var categoryCell = document.createElement('td');
                categoryCell.textContent = template.category || '—';
                tr.appendChild(categoryCell);

                var languageCell = document.createElement('td');
                languageCell.textContent = template.language || '—';
                tr.appendChild(languageCell);

                var statusCell = document.createElement('td');
                statusCell.innerHTML = formatStatus(template.status);
                tr.appendChild(statusCell);

                var qualityCell = document.createElement('td');
                qualityCell.innerHTML = formatQuality(template.quality_score);
                tr.appendChild(qualityCell);

                var observationCell = document.createElement('td');
                observationCell.innerHTML = formatObservation(template);
                tr.appendChild(observationCell);

                var updatedCell = document.createElement('td');
                updatedCell.textContent = formatDate(template.last_updated_time);
                tr.appendChild(updatedCell);

                var actionsCell = document.createElement('td');
                actionsCell.classList.add('text-end');

                var actionsGroup = document.createElement('div');
                actionsGroup.className = 'btn-group btn-group-sm';
                actionsGroup.setAttribute('role', 'group');

                var serialized = encodeURIComponent(JSON.stringify(template));

                var previewButton = document.createElement('button');
                previewButton.type = 'button';
                previewButton.className = 'btn btn-outline-primary';
                previewButton.setAttribute('data-action', 'preview');
                previewButton.setAttribute('data-template', serialized);
                previewButton.innerHTML = '<i class="mdi mdi-eye"></i>';
                actionsGroup.appendChild(previewButton);

                var editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'btn btn-outline-secondary';
                editButton.setAttribute('data-action', 'edit');
                editButton.setAttribute('data-template', serialized);
                editButton.innerHTML = '<i class="mdi mdi-pencil"></i>';
                actionsGroup.appendChild(editButton);

                actionsCell.appendChild(actionsGroup);
                tr.appendChild(actionsCell);

                tableBody.appendChild(tr);
            });
        }

        function loadTemplates() {
            if (!endpoints.list) {
                return;
            }

            setLoading(true);
            hideFeedback(feedback);

            var url = new URL(endpoints.list, window.location.origin);
            url.searchParams.set('limit', '250');

            fetchJson(url.toString(), { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.body && response.body.error ? response.body.error : 'No fue posible sincronizar las plantillas.');
                    }

                    var templates = Array.isArray(response.body && response.body.data) ? response.body.data : [];
                    state.templates = templates.map(function (template) {
                        return {
                            id: template.id || '',
                            name: template.name || '',
                            category: template.category || '',
                            language: template.language || '',
                            status: template.status || '',
                            quality_score: template.quality_score || '',
                            components: template.components || [],
                            rejected_reason: template.rejected_reason || template.rejection_reason || template.reason || '',
                            last_updated_time: template.last_updated_time || ''
                        };
                    });

                    applyFilters();
                })
                .catch(function (error) {
                    state.templates = [];
                    state.filtered = [];
                    showFeedback(feedback, 'danger', error.message);
                })
                .finally(function () {
                    setLoading(false);
                    renderTemplates();
                });
        }

        function resetFormForCreate() {
            if (!modalForm || !fieldName || !fieldLanguage || !fieldCategory || !deleteButton) {
                return;
            }

            modalForm.reset();
            fieldId.value = '';
            fieldName.removeAttribute('readonly');
            fieldName.classList.remove('bg-light');
            if (typeof fieldName.dataset.originalValue !== 'undefined') {
                delete fieldName.dataset.originalValue;
            }

            if (fieldAllowCategoryChange) {
                fieldAllowCategoryChange.checked = false;
            }

            resetBuilder();

            deleteButton.hidden = true;
            deleteButton.dataset.templateId = '';
            deleteButton.dataset.templateName = '';
            if (modalTitle) {
                modalTitle.textContent = 'Nueva plantilla';
            }
            hideFeedback(modalFeedback);
        }

        function openCreateModal() {
            if (!modal) {
                return;
            }

            resetFormForCreate();
            modal.show();
        }

        function openEditModal(template) {
            if (!modal || !template) {
                return;
            }

            resetFormForCreate();

            if (fieldId) {
                fieldId.value = template.id || '';
            }

            if (fieldName) {
                fieldName.value = template.name || '';
                fieldName.setAttribute('readonly', 'readonly');
                fieldName.classList.add('bg-light');
            }

            if (fieldLanguage) {
                fieldLanguage.value = template.language || '';
            }

            if (fieldCategory) {
                fieldCategory.value = template.category || '';
            }

            if (fieldAllowCategoryChange) {
                fieldAllowCategoryChange.checked = Boolean(template.allow_category_change);
            }

            hydrateBuilderFromComponents(template.components || []);

            if (deleteButton) {
                deleteButton.hidden = false;
                deleteButton.dataset.templateId = template.id || '';
                deleteButton.dataset.templateName = template.name || '';
            }

            if (modalTitle) {
                modalTitle.textContent = 'Editar plantilla';
            }

            modal.show();
        }

        function openPreview(template) {
            if (!previewModal || !template) {
                return;
            }

            if (previewName) {
                previewName.textContent = template.name || '';
            }

            if (previewLanguage) {
                var languageEntry = (bootstrapData.languages || []).find(function (lang) {
                    return lang.code === template.language;
                });
                var languageLabel = languageEntry ? languageEntry.name + ' (' + languageEntry.code + ')' : template.language;
                previewLanguage.textContent = languageLabel || '';
            }

            if (previewStatus) {
                previewStatus.innerHTML = formatStatus(template.status);
            }

            if (previewReason) {
                previewReason.innerHTML = formatObservation(template);
            }

            if (previewBubble) {
                previewBubble.innerHTML = buildPreviewHtmlFromComponents(template.components || []);
            }

            previewModal.show();
        }

        function resetBuilder() {
            if (headerTypeSelect) {
                headerTypeSelect.value = 'none';
            }
            if (headerInput) {
                headerInput.value = '';
            }
            if (headerTextWrapper) {
                headerTextWrapper.classList.add('d-none');
            }
            if (headerWarning) {
                headerWarning.classList.add('d-none');
            }
            if (bodyInput) {
                bodyInput.value = '';
            }
            if (footerInput) {
                footerInput.value = '';
            }
            if (buttonsContainer) {
                buttonsContainer.innerHTML = '';
            }
            renderBuilderPreview();
        }

        function getNextVariableNumber(text) {
            var regex = /\{\{\s*(\d+)\s*\}\}/g;
            var max = 0;
            var match;
            while ((match = regex.exec(text)) !== null) {
                var num = parseInt(match[1], 10);
                if (!isNaN(num) && num > max) {
                    max = num;
                }
            }
            return max + 1;
        }

        function insertVariable(target) {
            if (!target) {
                return;
            }
            var value = target.value || '';
            var next = getNextVariableNumber(value);
            var token = '{{' + next + '}}';

            if (typeof target.selectionStart === 'number') {
                var start = target.selectionStart;
                var end = target.selectionEnd;
                target.value = value.substring(0, start) + token + value.substring(end);
                target.selectionStart = target.selectionEnd = start + token.length;
            } else {
                target.value = value + (value ? ' ' : '') + token;
            }

            renderBuilderPreview();
        }

        function buildComponents() {
            var components = [];

            var headerType = headerTypeSelect ? headerTypeSelect.value : 'none';
            var headerText = headerInput ? headerInput.value.trim() : '';
            if (headerType === 'text' && headerText !== '') {
                components.push({
                    type: 'HEADER',
                    format: 'TEXT',
                    text: headerText
                });
            }

            var bodyText = bodyInput ? bodyInput.value.trim() : '';
            if (bodyText !== '') {
                components.push({
                    type: 'BODY',
                    text: bodyText
                });
            }

            var footerText = footerInput ? footerInput.value.trim() : '';
            if (footerText !== '') {
                components.push({
                    type: 'FOOTER',
                    text: footerText
                });
            }

            var buttons = collectButtons();
            if (buttons.length) {
                components.push({
                    type: 'BUTTONS',
                    buttons: buttons
                });
            }

            return components;
        }

        function collectButtons() {
            if (!buttonsContainer) {
                return [];
            }

            var rows = buttonsContainer.querySelectorAll('[data-button-row]');
            var buttons = [];

            rows.forEach(function (row) {
                var typeSelect = row.querySelector('[data-button-type]');
                var labelInput = row.querySelector('[data-button-label]');
                var urlInput = row.querySelector('[data-button-url]');
                var phoneInput = row.querySelector('[data-button-phone]');

                var type = typeSelect ? typeSelect.value : 'QUICK_REPLY';
                var label = labelInput ? labelInput.value.trim() : '';

                if (!label) {
                    return;
                }

                if (type === 'URL') {
                    var url = urlInput ? urlInput.value.trim() : '';
                    if (!url) {
                        return;
                    }
                    buttons.push({
                        type: 'URL',
                        text: label,
                        url: url
                    });
                } else if (type === 'PHONE_NUMBER') {
                    var phone = phoneInput ? phoneInput.value.trim() : '';
                    if (!phone) {
                        return;
                    }
                    buttons.push({
                        type: 'PHONE_NUMBER',
                        text: label,
                        phone_number: phone
                    });
                } else {
                    buttons.push({
                        type: 'QUICK_REPLY',
                        text: label
                    });
                }
            });

            return buttons;
        }

        function buildPreviewHtmlFromComponents(components) {
            if (!components || !components.length) {
                return '<span class="text-muted">Sin contenido para previsualizar.</span>';
            }

            var header = '';
            var body = '';
            var footer = '';
            var buttons = [];

            components.forEach(function (component) {
                if (!component || !component.type) {
                    return;
                }
                var type = String(component.type).toUpperCase();
                if (type === 'HEADER' && component.text) {
                    header = component.text;
                } else if (type === 'BODY' && component.text) {
                    body = component.text;
                } else if (type === 'FOOTER' && component.text) {
                    footer = component.text;
                } else if (type === 'BUTTONS' && Array.isArray(component.buttons)) {
                    buttons = component.buttons;
                }
            });

            var html = '';
            if (header) {
                html += '<div class="fw-600 mb-2">' + escapeHtml(header) + '</div>';
            }
            if (body) {
                html += '<div class="mb-2">' + escapeHtml(body) + '</div>';
            }
            if (footer) {
                html += '<div class="text-muted small">' + escapeHtml(footer) + '</div>';
            }
            if (buttons.length) {
                html += '<div class="mt-2 d-grid gap-1">';
                buttons.forEach(function (button) {
                    var label = button.text || 'Botón';
                    html += '<div class="preview-button">' + escapeHtml(label) + '</div>';
                });
                html += '</div>';
            }

            return html || '<span class="text-muted">Sin contenido para previsualizar.</span>';
        }

        function renderBuilderPreview() {
            if (!builderPreview) {
                return;
            }

            var components = buildComponents();
            builderPreview.innerHTML = buildPreviewHtmlFromComponents(components);
        }

        function createButtonRow(data) {
            var row = document.createElement('div');
            row.className = 'border rounded p-2';
            row.setAttribute('data-button-row', '1');

            var top = document.createElement('div');
            top.className = 'd-flex flex-wrap align-items-center gap-2 mb-2';

            var typeSelect = document.createElement('select');
            typeSelect.className = 'form-select form-select-sm w-auto';
            typeSelect.setAttribute('data-button-type', '1');
            [
                { value: 'QUICK_REPLY', label: 'Respuesta rápida' },
                { value: 'URL', label: 'Enlace' },
                { value: 'PHONE_NUMBER', label: 'Teléfono' }
            ].forEach(function (optionData) {
                var option = document.createElement('option');
                option.value = optionData.value;
                option.textContent = optionData.label;
                typeSelect.appendChild(option);
            });

            var labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'form-control form-control-sm flex-grow-1';
            labelInput.placeholder = 'Texto del botón';
            labelInput.setAttribute('data-button-label', '1');

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-outline-danger btn-sm';
            removeButton.innerHTML = '<i class="mdi mdi-close"></i>';

            top.appendChild(typeSelect);
            top.appendChild(labelInput);
            top.appendChild(removeButton);

            var urlInput = document.createElement('input');
            urlInput.type = 'text';
            urlInput.className = 'form-control form-control-sm';
            urlInput.placeholder = 'https://tusitio.com/{{1}}';
            urlInput.setAttribute('data-button-url', '1');

            var phoneInput = document.createElement('input');
            phoneInput.type = 'text';
            phoneInput.className = 'form-control form-control-sm';
            phoneInput.placeholder = '+593...';
            phoneInput.setAttribute('data-button-phone', '1');

            row.appendChild(top);
            row.appendChild(urlInput);
            row.appendChild(phoneInput);

            function syncButtonFields() {
                var type = typeSelect.value;
                urlInput.classList.toggle('d-none', type !== 'URL');
                phoneInput.classList.toggle('d-none', type !== 'PHONE_NUMBER');
            }

            typeSelect.addEventListener('change', function () {
                syncButtonFields();
                renderBuilderPreview();
            });
            labelInput.addEventListener('input', renderBuilderPreview);
            urlInput.addEventListener('input', renderBuilderPreview);
            phoneInput.addEventListener('input', renderBuilderPreview);
            removeButton.addEventListener('click', function () {
                row.remove();
                renderBuilderPreview();
            });

            if (data) {
                typeSelect.value = data.type || 'QUICK_REPLY';
                labelInput.value = data.text || '';
                if (data.type === 'URL') {
                    urlInput.value = data.url || '';
                } else if (data.type === 'PHONE_NUMBER') {
                    phoneInput.value = data.phone_number || '';
                }
            }

            syncButtonFields();

            return row;
        }

        function hydrateBuilderFromComponents(components) {
            resetBuilder();

            if (!Array.isArray(components)) {
                return;
            }

            var headerFound = false;

            components.forEach(function (component) {
                if (!component || !component.type) {
                    return;
                }
                var type = String(component.type).toUpperCase();

                if (type === 'HEADER') {
                    headerFound = true;
                    var format = String(component.format || 'TEXT').toUpperCase();
                    if (format === 'TEXT') {
                        if (headerTypeSelect) {
                            headerTypeSelect.value = 'text';
                        }
                        if (headerTextWrapper) {
                            headerTextWrapper.classList.remove('d-none');
                        }
                        if (headerInput) {
                            headerInput.value = component.text || '';
                        }
                        if (headerWarning) {
                            headerWarning.classList.add('d-none');
                        }
                    } else if (headerWarning) {
                        headerWarning.classList.remove('d-none');
                    }
                }

                if (type === 'BODY' && bodyInput) {
                    bodyInput.value = component.text || '';
                }

                if (type === 'FOOTER' && footerInput) {
                    footerInput.value = component.text || '';
                }

                if (type === 'BUTTONS' && Array.isArray(component.buttons) && buttonsContainer) {
                    component.buttons.forEach(function (button) {
                        var row = createButtonRow(button);
                        buttonsContainer.appendChild(row);
                    });
                }
            });

            if (!headerFound && headerWarning) {
                headerWarning.classList.add('d-none');
            }

            renderBuilderPreview();
        }

        function gatherPayload() {
            if (!fieldName || !fieldLanguage || !fieldCategory) {
                throw new Error('El formulario no está completo.');
            }

            var name = fieldName.value.trim();
            var language = fieldLanguage.value.trim();
            var category = fieldCategory.value.trim();

            if (!name) {
                throw new Error('El nombre de la plantilla es obligatorio.');
            }

            if (!language) {
                throw new Error('Selecciona un idioma.');
            }

            if (!category) {
                throw new Error('Selecciona una categoría.');
            }

            if (!bodyInput || bodyInput.value.trim() === '') {
                throw new Error('El cuerpo del mensaje es obligatorio.');
            }

            var components = buildComponents();
            if (!components.length) {
                throw new Error('Debes completar el cuerpo del mensaje.');
            }

            return {
                name: name,
                language: language,
                category: category,
                components: components,
                allow_category_change: fieldAllowCategoryChange ? fieldAllowCategoryChange.checked : false
            };
        }

        function handleFormSubmit(event) {
            event.preventDefault();
            if (!modal || !state.enabled) {
                showFeedback(modalFeedback, 'warning', 'La integración con WhatsApp no está lista.');
                return;
            }

            var templateId = fieldId ? fieldId.value.trim() : '';
            var isEdit = templateId !== '';
            var endpoint = isEdit ? endpoints.update.replace('{id}', encodeURIComponent(templateId)) : endpoints.create;

            var payload;
            try {
                payload = gatherPayload();
            } catch (error) {
                showFeedback(modalFeedback, 'danger', error.message);
                return;
            }

            hideFeedback(modalFeedback);
            if (submitButton) {
                submitButton.disabled = true;
            }

            fetchJson(endpoint, buildRequestOptions(payload))
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.body && response.body.error ? response.body.error : 'Ocurrió un error al guardar la plantilla.');
                    }

                    modal.hide();
                    showFeedback(feedback, 'success', isEdit ? 'Plantilla actualizada correctamente.' : 'Plantilla creada correctamente.');
                    loadTemplates();
                })
                .catch(function (error) {
                    showFeedback(modalFeedback, 'danger', error.message);
                })
                .finally(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        }

        function handleDelete() {
            if (!state.enabled || !deleteButton || !deleteButton.dataset.templateId) {
                return;
            }

            var templateId = deleteButton.dataset.templateId;
            var templateName = deleteButton.dataset.templateName || '';
            if (!templateId) {
                return;
            }

            if (!window.confirm('¿Seguro que deseas eliminar esta plantilla de WhatsApp? Esta acción no se puede deshacer.')) {
                return;
            }

            var endpoint = endpoints.delete.replace('{id}', encodeURIComponent(templateId));
            deleteButton.disabled = true;

            var payload = {};
            if (templateName) {
                payload.name = templateName;
            }

            fetchJson(endpoint, buildRequestOptions(payload))
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.body && response.body.error ? response.body.error : 'No fue posible eliminar la plantilla.');
                    }

                    modal.hide();
                    showFeedback(feedback, 'success', 'Plantilla eliminada correctamente.');
                    loadTemplates();
                })
                .catch(function (error) {
                    showFeedback(modalFeedback, 'danger', error.message);
                })
                .finally(function () {
                    deleteButton.disabled = false;
                });
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                if (!state.enabled) {
                    showFeedback(feedback, 'warning', 'Configura tu cuenta de WhatsApp Cloud API antes de sincronizar.');
                    return;
                }
                loadTemplates();
            });
        }

        if (newButton) {
            newButton.addEventListener('click', function () {
                if (!state.enabled) {
                    showFeedback(feedback, 'warning', 'Completa la configuración de WhatsApp antes de crear nuevas plantillas.');
                    return;
                }
                openCreateModal();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', applyFilters);
        }

        if (languageFilter) {
            languageFilter.addEventListener('change', applyFilters);
        }

        if (categoryFilter) {
            categoryFilter.addEventListener('change', applyFilters);
        }

        if (headerTypeSelect) {
            headerTypeSelect.addEventListener('change', function () {
                var value = headerTypeSelect.value;
                if (headerTextWrapper) {
                    headerTextWrapper.classList.toggle('d-none', value !== 'text');
                }
                if (headerWarning) {
                    headerWarning.classList.add('d-none');
                }
                renderBuilderPreview();
            });
        }

        if (headerInput) {
            headerInput.addEventListener('input', renderBuilderPreview);
        }

        if (bodyInput) {
            bodyInput.addEventListener('input', renderBuilderPreview);
        }

        if (footerInput) {
            footerInput.addEventListener('input', renderBuilderPreview);
        }

        if (addButton && buttonsContainer) {
            addButton.addEventListener('click', function () {
                var rows = buttonsContainer.querySelectorAll('[data-button-row]');
                if (rows.length >= 3) {
                    showFeedback(modalFeedback, 'warning', 'Solo puedes agregar hasta 3 botones.');
                    return;
                }
                hideFeedback(modalFeedback);
                var row = createButtonRow();
                buttonsContainer.appendChild(row);
                renderBuilderPreview();
            });
        }

        if (insertVariableButtons && insertVariableButtons.length) {
            insertVariableButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = button.getAttribute('data-insert-variable');
                    if (target === 'header') {
                        insertVariable(headerInput);
                    } else if (target === 'body') {
                        insertVariable(bodyInput);
                    } else if (target === 'footer') {
                        insertVariable(footerInput);
                    }
                });
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                var button = target.closest('button[data-action]');
                if (!button) {
                    return;
                }

                var action = button.getAttribute('data-action');
                var encoded = button.getAttribute('data-template');
                var template = null;
                if (encoded) {
                    try {
                        template = JSON.parse(decodeURIComponent(encoded));
                    } catch (error) {
                        template = null;
                    }
                }

                if (action === 'edit') {
                    openEditModal(template);
                } else if (action === 'preview') {
                    openPreview(template);
                }
            });
        }

        if (modalForm) {
            modalForm.addEventListener('submit', handleFormSubmit);
        }

        if (deleteButton) {
            deleteButton.addEventListener('click', handleDelete);
        }

        if (state.enabled) {
            loadTemplates();
        }
    });
})();
