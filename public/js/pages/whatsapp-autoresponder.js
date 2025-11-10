(function () {
        const form = document.querySelector('[data-autoresponder-form]');
        if (!form) {
            return;
        }

        const flowField = document.getElementById('flow_payload');
        const validationAlert = form.querySelector('[data-validation-errors]');
        let validationErrors = [];

        const resetValidationState = () => {
            validationErrors = [];
            if (validationAlert) {
                validationAlert.classList.add('d-none');
                validationAlert.innerHTML = '';
            }
            form.querySelectorAll('.has-validation-error').forEach((element) => {
                element.classList.remove('has-validation-error');
            });
            form.querySelectorAll('.is-invalid').forEach((element) => {
                element.classList.remove('is-invalid');
            });
        };

        const recordValidationError = (message, element) => {
            validationErrors.push({message, element});
            if (element) {
                element.classList.add('has-validation-error');
            }
        };

        const presentValidationErrors = () => {
            if (!validationAlert || validationErrors.length === 0) {
                return;
            }

            const items = validationErrors.map((entry) => `<li>${entry.message}</li>`).join('');
            validationAlert.innerHTML = `<strong>Revisa los siguientes puntos antes de guardar:</strong><ul class="mb-0">${items}</ul>`;
            validationAlert.classList.remove('d-none');

            const firstError = validationErrors[0];
            if (firstError && firstError.element && typeof firstError.element.scrollIntoView === 'function') {
                firstError.element.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        };

        const parseKeywords = (value) => {
            return (value || '')
                .split(/[\,\n]/)
                .map((item) => item.trim())
                .filter((item) => item !== '')
                .map((item) => item.toLowerCase());
        };

        const flowSource = form.querySelector('[data-flow-source]');
        let initialFlow = {};
        try {
            initialFlow = JSON.parse(flowSource?.value || '{}');
        } catch (error) {
            initialFlow = {};
        }

        const messageTemplate = document.getElementById('message-template');
        const buttonTemplate = document.getElementById('button-template');
        const shortcutTemplate = document.getElementById('shortcut-template');
        const nodeTemplate = document.getElementById('node-template');
        const responseTemplate = document.getElementById('response-template');
        const branchTemplate = document.getElementById('branch-template');
        const errorMessageTemplate = document.getElementById('error-message-template');

        const templateCatalogInput = form.querySelector('[data-template-catalog]');
        let templateCatalog = [];
        if (templateCatalogInput) {
            try {
                templateCatalog = JSON.parse(templateCatalogInput.value || '[]');
            } catch (error) {
                templateCatalog = [];
            }
            templateCatalogInput.name = '';
        }

        const addButtonRow = (messageElement, data = {}) => {
            if (!buttonTemplate) {
                return null;
            }
            const list = messageElement.querySelector('[data-button-list]');
            if (!list) {
                return null;
            }
            const clone = buttonTemplate.content.firstElementChild.cloneNode(true);
            list.appendChild(clone);
            const titleField = clone.querySelector('.button-title');
            const idField = clone.querySelector('.button-id');
            if (titleField && data.title) {
                titleField.value = data.title;
            }
            if (idField && data.id) {
                idField.value = data.id;
            }
            const removeBtn = clone.querySelector('[data-action="remove-button"]');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => clone.remove());
            }

            return clone;
        };

        const hasButton = (messageElement, title, id) => {
            const list = messageElement.querySelectorAll('[data-button]');
            for (const item of list) {
                const existingTitle = item.querySelector('.button-title')?.value?.trim().toLowerCase();
                const existingId = item.querySelector('.button-id')?.value?.trim().toLowerCase();
                if ((title && existingTitle === title.toLowerCase()) || (id && existingId === id.toLowerCase())) {
                    return true;
                }
            }
            return false;
        };

        const applyPreset = (messageElement, preset) => {
            if (preset === 'yesno') {
                if (!hasButton(messageElement, 'Sí', 'si')) {
                    addButtonRow(messageElement, {title: 'Sí', id: 'si'});
                }
                if (!hasButton(messageElement, 'No', 'no')) {
                    addButtonRow(messageElement, {title: 'No', id: 'no'});
                }
            }
            if (preset === 'menu') {
                if (!hasButton(messageElement, 'Menú', 'menu')) {
                    addButtonRow(messageElement, {title: 'Menú', id: 'menu'});
                }
            }
        };

        const createRowElement = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'input-group input-group-sm mb-2';
            row.setAttribute('data-row', '');
            row.innerHTML = `
            <span class="input-group-text">Título</span>
            <input type="text" class="form-control row-title" placeholder="Ej: Confirmar">
            <span class="input-group-text">ID</span>
            <input type="text" class="form-control row-id" placeholder="Identificador">
            <input type="text" class="form-control row-description" placeholder="Descripción opcional">
            <button type="button" class="btn btn-outline-danger" data-action="remove-row"><i class="mdi mdi-close"></i></button>
        `;

            if (data.title) {
                row.querySelector('.row-title').value = data.title;
            }
            if (data.id) {
                row.querySelector('.row-id').value = data.id;
            }
            if (data.description) {
                row.querySelector('.row-description').value = data.description;
            }

            return row;
        };

        const createSectionElement = (data = {}) => {
            const section = document.createElement('div');
            section.className = 'border rounded-3 p-3 mb-3';
            section.setAttribute('data-section', '');
            section.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <input type="text" class="form-control section-title" placeholder="Título de la sección (opcional)">
                <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-section"><i class="mdi mdi-close"></i></button>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small text-muted">Opciones</div>
                <button type="button" class="btn btn-xs btn-outline-secondary" data-action="add-row">Añadir opción</button>
            </div>
            <div data-rows></div>
        `;

            if (data.title) {
                section.querySelector('.section-title').value = data.title;
            }

            const rowsContainer = section.querySelector('[data-rows]');
            if (Array.isArray(data.rows)) {
                data.rows.forEach((rowData) => {
                    rowsContainer.appendChild(createRowElement(rowData));
                });
            }

            return section;
        };

        const hydrateRow = (rowElement) => {
            const removeButton = rowElement.querySelector('[data-action="remove-row"]');
            if (removeButton && !removeButton.dataset.bound) {
                removeButton.dataset.bound = '1';
                removeButton.addEventListener('click', () => rowElement.remove());
            }
        };

        const hydrateSection = (sectionElement) => {
            const removeButton = sectionElement.querySelector('[data-action="remove-section"]');
            if (removeButton && !removeButton.dataset.bound) {
                removeButton.dataset.bound = '1';
                removeButton.addEventListener('click', () => sectionElement.remove());
            }

            const addRowButton = sectionElement.querySelector('[data-action="add-row"]');
            if (addRowButton && !addRowButton.dataset.bound) {
                addRowButton.dataset.bound = '1';
                addRowButton.addEventListener('click', () => {
                    const container = sectionElement.querySelector('[data-rows]');
                    const row = createRowElement();
                    container.appendChild(row);
                    hydrateRow(row);
                });
            }

            sectionElement.querySelectorAll('[data-row]').forEach((row) => hydrateRow(row));
        };

        const ensureListControls = (messageElement) => {
            const listContainer = messageElement.querySelector('[data-list]');
            if (!listContainer) {
                return;
            }

            const sectionsWrapper = listContainer.querySelector('[data-sections]');
            if (!sectionsWrapper) {
                return;
            }

            sectionsWrapper.querySelectorAll('[data-section]').forEach((section) => hydrateSection(section));

            const addSectionButton = listContainer.querySelector('[data-action="add-section"]');
            if (addSectionButton && !addSectionButton.dataset.bound) {
                addSectionButton.dataset.bound = '1';
                addSectionButton.addEventListener('click', () => {
                    const section = createSectionElement();
                    sectionsWrapper.appendChild(section);
                    hydrateSection(section);
                });
            }
        };

        const findTemplateMeta = (name, language) => {
            if (!name || !language) {
                return null;
            }

            return templateCatalog.find((template) => {
                return template.name === name && template.language === language;
            }) || null;
        };

        const renderTemplateSummary = (summaryElement, meta, fallback) => {
            if (!summaryElement) {
                return;
            }

            if (!meta && !fallback) {
                summaryElement.innerHTML = '<div class="fw-600">Sin plantilla seleccionada</div><div>Elige una plantilla para ver sus variables y completar los parámetros.</div>';
                return;
            }

            const name = meta?.name || fallback?.name || '';
            const language = meta?.language || fallback?.language || '';
            const category = meta?.category || fallback?.category || '';

            summaryElement.innerHTML = `
            <div class="fw-600">${name} · ${language}</div>
            <div class="text-muted">Categoría: ${category || 'Sin categoría'}</div>
        `;
        };

        const extractExistingComponents = (componentField) => {
            if (!componentField) {
                return {body: [], header: [], buttons: {}};
            }

            try {
                const parsed = JSON.parse(componentField.value || '[]');
                const existing = {body: [], header: [], buttons: {}};

                parsed.forEach((component) => {
                    if (!component || typeof component !== 'object') {
                        return;
                    }

                    const type = (component.type || '').toUpperCase();
                    if (type === 'BODY' && Array.isArray(component.parameters)) {
                        existing.body = component.parameters;
                    }
                    if (type === 'HEADER' && Array.isArray(component.parameters)) {
                        existing.header = component.parameters;
                    }
                    if (type === 'BUTTON' && Array.isArray(component.parameters)) {
                        const index = Number.isInteger(component.index) ? component.index : 0;
                        existing.buttons[index] = component.parameters;
                    }
                });

                return existing;
            } catch (error) {
                return {body: [], header: [], buttons: {}};
            }
        };

        const buildTemplateParameters = (messageElement, meta) => {
            const container = messageElement.querySelector('.template-parameters');
            const componentsField = messageElement.querySelector('.template-components');
            if (!container) {
                return;
            }

            container.innerHTML = '';

            if (!meta) {
                return;
            }

            const existing = extractExistingComponents(componentsField);
            const blocks = [];

            meta.components.forEach((component) => {
                const type = (component.type || '').toUpperCase();
                if (type === 'BODY' && Array.isArray(component.placeholders) && component.placeholders.length > 0) {
                    const group = document.createElement('div');
                    group.className = 'mb-3';
                    group.innerHTML = '<label class="form-label small">Variables del cuerpo</label>';
                    component.placeholders.forEach((placeholder) => {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control mb-2';
                        input.placeholder = `Valor para {{${placeholder}}}`;
                        input.setAttribute('data-template-parameter', '');
                        input.setAttribute('data-component', 'BODY');
                        input.setAttribute('data-placeholder', String(placeholder));
                        const existingParam = existing.body[placeholder - 1] || {};
                        if (typeof existingParam.text === 'string') {
                            input.value = existingParam.text;
                        }
                        group.appendChild(input);
                    });
                    blocks.push(group);
                }

                if (type === 'HEADER' && component.format === 'TEXT' && Array.isArray(component.placeholders) && component.placeholders.length > 0) {
                    const group = document.createElement('div');
                    group.className = 'mb-3';
                    group.innerHTML = '<label class="form-label small">Variables del encabezado</label>';
                    component.placeholders.forEach((placeholder) => {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control mb-2';
                        input.placeholder = `Valor para encabezado {{${placeholder}}}`;
                        input.setAttribute('data-template-parameter', '');
                        input.setAttribute('data-component', 'HEADER');
                        input.setAttribute('data-placeholder', String(placeholder));
                        const existingParam = existing.header[placeholder - 1] || {};
                        if (typeof existingParam.text === 'string') {
                            input.value = existingParam.text;
                        }
                        group.appendChild(input);
                    });
                    blocks.push(group);
                }

                if (type === 'BUTTONS' && Array.isArray(component.buttons)) {
                    component.buttons.forEach((button) => {
                        const subType = (button.type || '').toUpperCase();
                        const index = Number.isInteger(button.index) ? button.index : 0;
                        const placeholders = Array.isArray(button.placeholders) ? button.placeholders : [];
                        if (placeholders.length === 0) {
                            return;
                        }

                        const group = document.createElement('div');
                        group.className = 'mb-3';
                        const labelText = button.text ? `${button.text} (${subType})` : `Botón ${index + 1} (${subType})`;
                        group.innerHTML = `<label class="form-label small">${labelText}</label>`;

                        placeholders.forEach((placeholder) => {
                            const input = document.createElement('input');
                            input.type = 'text';
                            input.className = 'form-control mb-2';
                            input.placeholder = `Valor para botón {{${placeholder}}}`;
                            input.setAttribute('data-template-parameter', '');
                            input.setAttribute('data-component', 'BUTTON');
                            input.setAttribute('data-index', String(index));
                            input.setAttribute('data-placeholder', String(placeholder));
                            const existingButtonParams = existing.buttons[index] || [];
                            const existingParam = existingButtonParams[placeholder - 1] || {};
                            const candidate = existingParam.text || existingParam.payload;
                            if (typeof candidate === 'string') {
                                input.value = candidate;
                            }
                            group.appendChild(input);
                        });

                        blocks.push(group);
                    });
                }
            });

            if (blocks.length === 0) {
                const note = document.createElement('p');
                note.className = 'small text-muted mb-0';
                note.textContent = 'Esta plantilla no requiere variables. Se enviará tal como está configurada en Meta.';
                container.appendChild(note);
            } else {
                blocks.forEach((block) => container.appendChild(block));
            }

        };

        const ensureTemplateControls = (messageElement) => {
            const templateContainer = messageElement.querySelector('[data-template]');
            if (!templateContainer) {
                return;
            }

            const select = templateContainer.querySelector('.template-selector');
            const nameField = templateContainer.querySelector('.template-name');
            const languageField = templateContainer.querySelector('.template-language');
            const categoryField = templateContainer.querySelector('.template-category');
            const summaryElement = templateContainer.querySelector('.template-summary');
            const componentsField = templateContainer.querySelector('.template-components');

            if (select && !select.dataset.loaded) {
                templateCatalog.forEach((template) => {
                    const option = document.createElement('option');
                    option.value = `${template.name}::${template.language}`;
                    const categoryLabel = template.category ? template.category : 'Sin categoría';
                    option.textContent = `${template.name} · ${template.language} (${categoryLabel})`;
                    select.appendChild(option);
                });
                select.dataset.loaded = '1';
            }

            const selectedName = nameField?.value?.trim();
            const selectedLanguage = languageField?.value?.trim();
            const meta = findTemplateMeta(selectedName, selectedLanguage);
            if (select && selectedName && selectedLanguage) {
                const value = `${selectedName}::${selectedLanguage}`;
                if (!Array.from(select.options).some((option) => option.value === value)) {
                    const fallbackOption = document.createElement('option');
                    fallbackOption.value = value;
                    fallbackOption.textContent = `${selectedName} · ${selectedLanguage} (no sincronizada)`;
                    select.appendChild(fallbackOption);
                }
                select.value = value;
            }

            renderTemplateSummary(summaryElement, meta, {
                name: selectedName,
                language: selectedLanguage,
                category: categoryField?.value?.trim() || '',
            });

            const parametersContainer = templateContainer.querySelector('.template-parameters');
            const currentKey = templateContainer.dataset.renderedTemplate || '';
            const nextKey = meta ? `${meta.name}::${meta.language}` : '';
            if (!parametersContainer || parametersContainer.childElementCount === 0 || currentKey !== nextKey) {
                buildTemplateParameters(messageElement, meta);
                templateContainer.dataset.renderedTemplate = nextKey;
            }

            if (select && !select.dataset.bound) {
                select.dataset.bound = '1';
                select.addEventListener('change', () => {
                    const value = select.value;
                    if (!value) {
                        if (nameField) nameField.value = '';
                        if (languageField) languageField.value = '';
                        if (categoryField) categoryField.value = '';
                        renderTemplateSummary(summaryElement, null, null);
                        buildTemplateParameters(messageElement, null);
                        templateContainer.dataset.renderedTemplate = '';
                        if (componentsField) {
                            componentsField.value = '[]';
                        }
                        return;
                    }

                    const [name, language] = value.split('::');
                    const selectedMeta = findTemplateMeta(name, language);
                    if (nameField) nameField.value = name || '';
                    if (languageField) languageField.value = language || '';
                    if (categoryField) categoryField.value = selectedMeta?.category || '';
                    renderTemplateSummary(summaryElement, selectedMeta, null);
                    buildTemplateParameters(messageElement, selectedMeta);
                    templateContainer.dataset.renderedTemplate = selectedMeta ? `${selectedMeta.name}::${selectedMeta.language}` : '';
                    if (componentsField) {
                        componentsField.value = '[]';
                    }
                });
            }
        };

        const toggleMessageFields = (messageElement) => {
            const type = messageElement.querySelector('.message-type')?.value || 'text';
            const buttonsContainer = messageElement.querySelector('[data-buttons]');
            const listContainer = messageElement.querySelector('[data-list]');
            const templateContainer = messageElement.querySelector('[data-template]');
            const headerField = messageElement.querySelector('.message-header');
            const footerField = messageElement.querySelector('.message-footer');

            if (buttonsContainer) {
                buttonsContainer.classList.toggle('d-none', type !== 'buttons');
            }
            if (listContainer) {
                listContainer.classList.toggle('d-none', type !== 'list');
            }
            if (templateContainer) {
                templateContainer.classList.toggle('d-none', type !== 'template');
            }

            if (type === 'template') {
                if (headerField) headerField.setAttribute('disabled', 'disabled');
                if (footerField) footerField.setAttribute('disabled', 'disabled');
                ensureTemplateControls(messageElement);
            } else {
                if (headerField) headerField.removeAttribute('disabled');
                if (footerField) footerField.removeAttribute('disabled');
            }

            if (type === 'list') {
                ensureListControls(messageElement);
                const wrapper = messageElement.querySelector('[data-sections]');
                if (wrapper && !wrapper.querySelector('[data-section]')) {
                    const section = createSectionElement();
                    wrapper.appendChild(section);
                    hydrateSection(section);
                }
            }
        };

        const hydrateMessage = (messageElement) => {
            if (!messageElement || messageElement.dataset.hydrated) {
                return;
            }
            messageElement.dataset.hydrated = '1';

            const typeSelector = messageElement.querySelector('.message-type');
            if (typeSelector) {
                typeSelector.addEventListener('change', () => {
                    toggleMessageFields(messageElement);
                });
            }

            const presetButtons = messageElement.querySelectorAll('[data-action="preset"]');
            presetButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const preset = button.getAttribute('data-preset');
                    applyPreset(messageElement, preset);
                });
            });

            const addButton = messageElement.querySelector('[data-action="add-button"]');
            if (addButton) {
                addButton.addEventListener('click', () => {
                    addButtonRow(messageElement);
                });
            }

            ensureListControls(messageElement);
            ensureTemplateControls(messageElement);
            toggleMessageFields(messageElement);

            const removeButton = messageElement.querySelector('[data-action="remove-message"]');
            if (removeButton) {
                removeButton.addEventListener('click', () => {
                    messageElement.remove();
                });
            }
        };

        const collectButtons = (messageElement) => {
            const buttons = [];
            messageElement.querySelectorAll('[data-button]').forEach((buttonElement) => {
                const title = buttonElement.querySelector('.button-title')?.value?.trim() || '';
                const id = buttonElement.querySelector('.button-id')?.value?.trim() || '';
                if (title === '') {
                    return;
                }
                buttons.push({title, id});
            });
            return buttons;
        };

        const collectListData = (messageElement) => {
            const button = messageElement.querySelector('.list-button')?.value?.trim() || 'Seleccionar';
            const sections = [];
            messageElement.querySelectorAll('[data-section]').forEach((sectionElement) => {
                const title = sectionElement.querySelector('.section-title')?.value?.trim() || '';
                const rows = [];
                sectionElement.querySelectorAll('[data-row]').forEach((rowElement) => {
                    const rowTitle = rowElement.querySelector('.row-title')?.value?.trim() || '';
                    const rowId = rowElement.querySelector('.row-id')?.value?.trim() || '';
                    const rowDescription = rowElement.querySelector('.row-description')?.value?.trim() || '';
                    if (rowTitle === '' || rowId === '') {
                        return;
                    }
                    const entry = {id: rowId, title: rowTitle};
                    if (rowDescription !== '') {
                        entry.description = rowDescription;
                    }
                    rows.push(entry);
                });
                if (rows.length > 0) {
                    sections.push({title, rows});
                }
            });
            return {button, sections};
        };

        const collectTemplateData = (messageElement, messageDescription) => {
            const nameField = messageElement.querySelector('.template-name');
            const languageField = messageElement.querySelector('.template-language');
            const categoryField = messageElement.querySelector('.template-category');
            const componentsField = messageElement.querySelector('.template-components');
            const selector = messageElement.querySelector('.template-selector');

            const name = nameField?.value?.trim() || '';
            const language = languageField?.value?.trim() || '';
            const category = categoryField?.value?.trim() || '';

            if (name === '' || language === '') {
                recordValidationError(`Selecciona una plantilla aprobada ${messageDescription}.`, messageElement);
                if (selector) {
                    selector.classList.add('is-invalid');
                }
                return null;
            }

            const meta = findTemplateMeta(name, language);
            const components = [];

            const componentInputs = messageElement.querySelectorAll('[data-template-parameter]');
            componentInputs.forEach((input) => input.classList.remove('is-invalid'));

            if (componentInputs.length > 0 && meta) {
                meta.components
                    .filter((component) => component.type === 'BODY' && Array.isArray(component.placeholders) && component.placeholders.length > 0)
                    .forEach((component) => {
                        const placeholders = component.placeholders || [];
                        const missing = [];
                        const parameters = [];
                        placeholders.forEach((placeholder) => {
                            const input = messageElement.querySelector(`[data-template-parameter][data-component="BODY"][data-placeholder="${placeholder}"]`);
                            const value = input?.value?.trim() || '';
                            if (!input || value === '') {
                                missing.push(placeholder);
                                if (input) {
                                    input.classList.add('is-invalid');
                                }
                                return;
                            }
                            parameters.push({type: 'text', text: value});
                        });
                        if (missing.length > 0) {
                            const placeholdersList = missing.map((value) => `{{${value}}}`).join(', ');
                            recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholdersList} del cuerpo ${messageDescription}.`, messageElement);
                        } else {
                            components.push({type: 'BODY', parameters});
                        }
                    });

                meta.components
                    .filter((component) => component.type === 'HEADER' && component.format === 'TEXT' && Array.isArray(component.placeholders) && component.placeholders.length > 0)
                    .forEach((component) => {
                        const placeholders = component.placeholders || [];
                        const missing = [];
                        const parameters = [];
                        placeholders.forEach((placeholder) => {
                            const input = messageElement.querySelector(`[data-template-parameter][data-component="HEADER"][data-placeholder="${placeholder}"]`);
                            const value = input?.value?.trim() || '';
                            if (!input || value === '') {
                                missing.push(placeholder);
                                if (input) {
                                    input.classList.add('is-invalid');
                                }
                                return;
                            }
                            parameters.push({type: 'text', text: value});
                        });
                        if (missing.length > 0) {
                            const placeholdersList = missing.map((value) => `{{${value}}}`).join(', ');
                            recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholdersList} del encabezado ${messageDescription}.`, messageElement);
                        } else {
                            components.push({type: 'HEADER', parameters});
                        }
                    });

                meta.components
                    .filter((component) => component.type === 'BUTTONS' && Array.isArray(component.buttons))
                    .forEach((component) => {
                        component.buttons.forEach((button) => {
                            if (!Array.isArray(button.placeholders) || button.placeholders.length === 0) {
                                return;
                            }

                            const missing = [];
                            const parameters = [];
                            button.placeholders.forEach((placeholder) => {
                                const input = messageElement.querySelector(`[data-template-parameter][data-component="BUTTON"][data-index="${button.index}"][data-placeholder="${placeholder}"]`);
                                const value = input?.value?.trim() || '';
                                if (!input || value === '') {
                                    missing.push(placeholder);
                                    if (input) {
                                        input.classList.add('is-invalid');
                                    }
                                    return;
                                }
                                parameters.push({type: 'text', text: value});
                            });

                            if (missing.length > 0) {
                                const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                                const buttonLabel = button.text ? ` del botón "${button.text}"` : '';
                                recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders}${buttonLabel} ${messageDescription}.`, messageElement);
                            } else {
                                components.push({
                                    type: 'BUTTON',
                                    parameters,
                                    sub_type: button.type,
                                    index: button.index,
                                });
                            }
                        });
                    });

                if (componentsField) {
                    componentsField.value = JSON.stringify(components);
                }
            }

            return {
                name,
                language,
                category,
                components,
            };
        };

        const collectMessages = (container, contextLabel = '') => {
            const messages = [];
            if (!container) {
                return messages;
            }
            container.querySelectorAll('[data-message]').forEach((messageElement, index) => {
                messageElement.classList.remove('has-validation-error');
                const type = messageElement.querySelector('.message-type')?.value || 'text';
                const body = messageElement.querySelector('.message-body')?.value?.trim() || '';
                const header = messageElement.querySelector('.message-header')?.value?.trim() || '';
                const footer = messageElement.querySelector('.message-footer')?.value?.trim() || '';

                const payload = {type, body};
                const sectionDescription = contextLabel ? `en la sección "${contextLabel}"` : 'en esta sección';
                const messageDescription = `${sectionDescription} (mensaje ${index + 1})`;
                let messageHasErrors = false;

                if (header !== '') {
                    payload.header = header;
                }
                if (footer !== '') {
                    payload.footer = footer;
                }
                if (type === 'buttons') {
                    const buttonElements = messageElement.querySelectorAll('[data-button]');
                    const buttons = collectButtons(messageElement);
                    if (buttonElements.length === 0) {
                        recordValidationError(`Agrega al menos un botón ${messageDescription}.`, messageElement);
                        messageHasErrors = true;
                    } else if (buttons.length === 0) {
                        recordValidationError(`Completa el título de los botones ${messageDescription}.`, messageElement);
                        messageHasErrors = true;
                    } else {
                        payload.buttons = buttons;
                    }
                } else if (type === 'list') {
                    const listData = collectListData(messageElement);
                    const sectionsWrapper = messageElement.querySelector('[data-sections]');
                    const totalSections = sectionsWrapper ? sectionsWrapper.querySelectorAll('[data-section]').length : 0;
                    if (totalSections === 0) {
                        recordValidationError(`Agrega al menos una sección con opciones ${messageDescription}.`, messageElement);
                        messageHasErrors = true;
                    } else if (!listData.sections.length) {
                        recordValidationError(`Completa al menos una opción con título e ID ${messageDescription}.`, messageElement);
                        messageHasErrors = true;
                    } else {
                        payload.button = listData.button;
                        payload.sections = listData.sections;
                        if (payload.body === '') {
                            payload.body = 'Selecciona una opción para continuar';
                        }
                    }
                } else if (type === 'template') {
                    const templateData = collectTemplateData(messageElement, messageDescription);
                    if (!templateData) {
                        messageHasErrors = true;
                    } else {
                        payload.template = templateData;
                        if (!payload.body || payload.body.trim() === '') {
                            payload.body = `Plantilla: ${templateData.name}`;
                        }
                    }
                } else {
                    if (payload.body === '') {
                        recordValidationError(`Completa el contenido del mensaje ${messageDescription}.`, messageElement);
                        messageHasErrors = true;
                    }
                }

                if (!messageHasErrors) {
                    messages.push(payload);
                }
            });
            return messages;
        };

        const appendMessage = (container, defaults = {}) => {
            if (!messageTemplate) {
                return null;
            }
            const element = messageTemplate.content.firstElementChild.cloneNode(true);
            container.appendChild(element);
            hydrateMessage(element);
            if (defaults.type) {
                const typeSelect = element.querySelector('.message-type');
                if (typeSelect) {
                    typeSelect.value = defaults.type;
                }
            }
            if (defaults.body) {
                const bodyField = element.querySelector('.message-body');
                if (bodyField) {
                    bodyField.value = defaults.body;
                }
            }
            toggleMessageFields(element);
            return element;
        };

        const renderMessages = (container, messages) => {
            container.innerHTML = '';
            (messages || []).forEach((message) => {
                const element = appendMessage(container, message);
                if (!element) {
                    return;
                }
                const typeSelect = element.querySelector('.message-type');
                if (typeSelect) {
                    typeSelect.value = message.type || 'text';
                }
                const bodyField = element.querySelector('.message-body');
                if (bodyField) {
                    bodyField.value = message.body || '';
                }
                const headerField = element.querySelector('.message-header');
                if (headerField) {
                    headerField.value = message.header || '';
                }
                const footerField = element.querySelector('.message-footer');
                if (footerField) {
                    footerField.value = message.footer || '';
                }

                toggleMessageFields(element);

                if (message.type === 'buttons' && Array.isArray(message.buttons)) {
                    message.buttons.forEach((button) => addButtonRow(element, button));
                }

                if (message.type === 'list' && Array.isArray(message.sections)) {
                    const wrapper = element.querySelector('[data-sections]');
                    if (wrapper) {
                        message.sections.forEach((section) => {
                            const sectionElement = createSectionElement(section);
                            wrapper.appendChild(sectionElement);
                            hydrateSection(sectionElement);
                        });
                    }
                    const buttonField = element.querySelector('.list-button');
                    if (buttonField) {
                        buttonField.value = message.button || 'Seleccionar';
                    }
                }

                if (message.type === 'template' && message.template) {
                    const nameField = element.querySelector('.template-name');
                    const languageField = element.querySelector('.template-language');
                    const categoryField = element.querySelector('.template-category');
                    const componentsField = element.querySelector('.template-components');
                    if (nameField) nameField.value = message.template.name || '';
                    if (languageField) languageField.value = message.template.language || '';
                    if (categoryField) categoryField.value = message.template.category || '';
                    if (componentsField) componentsField.value = JSON.stringify(message.template.components || []);
                    ensureTemplateControls(element);
                }
            });
        };

        const shortcutList = form.querySelector('[data-shortcut-list]');
        const nodeList = form.querySelector('[data-node-list]');
        const fallbackContainer = form.querySelector('[data-fallback-editor]');
        const fallbackMessagesContainer = fallbackContainer?.querySelector('[data-fallback-messages]');
        const entryKeywordsField = form.querySelector('[data-entry-keywords]');

        const renderShortcut = (data = {}) => {
            if (!shortcutTemplate || !shortcutList) {
                return null;
            }
            const element = shortcutTemplate.content.firstElementChild.cloneNode(true);
            shortcutList.appendChild(element);
            element.querySelector('[data-field="id"]').value = data.id || '';
            element.querySelector('[data-field="title"]').value = data.title || '';
            element.querySelector('[data-field="target"]').value = data.target || '';
            element.querySelector('[data-field="keywords"]').value = (data.keywords || []).join(', ');
            element.querySelector('[data-field="clear_context"]').value = (data.clear_context || []).join(', ');
            const removeButton = element.querySelector('[data-action="remove-shortcut"]');
            if (removeButton) {
                removeButton.addEventListener('click', () => element.remove());
            }
            return element;
        };

        const renderResponse = (container, data = {}) => {
            if (!responseTemplate) {
                return null;
            }
            const element = responseTemplate.content.firstElementChild.cloneNode(true);
            container.appendChild(element);
            element.querySelector('[data-field="id"]').value = data.id || '';
            element.querySelector('[data-field="title"]').value = data.title || '';
            element.querySelector('[data-field="target"]').value = data.target || '';
            element.querySelector('[data-field="keywords"]').value = (data.keywords || []).join(', ');
            element.querySelector('[data-field="clear_context"]').value = (data.clear_context || []).join(', ');
            const removeButton = element.querySelector('[data-action="remove-response"]');
            if (removeButton) {
                removeButton.addEventListener('click', () => element.remove());
            }
            const messageContainer = element.querySelector('[data-response-message-list]');
            const addMessageButton = element.querySelector('[data-action="add-response-message"]');
            if (addMessageButton && messageContainer) {
                addMessageButton.addEventListener('click', () => {
                    appendMessage(messageContainer, {type: 'text'});
                });
            }
            renderMessages(messageContainer, data.messages || []);
            return element;
        };

        const renderErrorMessages = (container, messages = []) => {
            container.innerHTML = '';
            messages.forEach((message) => {
                const entry = errorMessageTemplate.content.firstElementChild.cloneNode(true);
                entry.querySelector('textarea').value = message.body || message || '';
                const removeButton = entry.querySelector('[data-action="remove-error-message"]');
                if (removeButton) {
                    removeButton.addEventListener('click', () => entry.remove());
                }
                container.appendChild(entry);
            });
        };

        const renderBranch = (container, data = {}) => {
            if (!branchTemplate) {
                return null;
            }
            const element = branchTemplate.content.firstElementChild.cloneNode(true);
            container.appendChild(element);
            element.querySelector('[data-field="id"]').value = data.id || '';
            element.querySelector('[data-field="condition.type"]').value = data.condition?.type || 'always';
            element.querySelector('[data-field="next"]').value = data.next || '';
            const extraContainer = element.querySelector('[data-branch-extra]');

            const updateExtraFields = () => {
                if (!extraContainer) {
                    return;
                }
                extraContainer.innerHTML = '';
                const type = element.querySelector('[data-field="condition.type"]').value;
                if (['patient_exists', 'has_value', 'equals', 'not_equals'].includes(type)) {
                    const fieldCol = document.createElement('div');
                    fieldCol.className = 'col-md-6';
                    fieldCol.innerHTML = '<label class="form-label-sm">Campo de contexto</label><input type="text" class="form-control form-control-sm" data-field="condition.field" placeholder="Ej: hc_number">';
                    extraContainer.appendChild(fieldCol);
                }
                if (['equals', 'not_equals'].includes(type)) {
                    const valueCol = document.createElement('div');
                    valueCol.className = 'col-md-6';
                    valueCol.innerHTML = '<label class="form-label-sm">Valor de comparación</label><input type="text" class="form-control form-control-sm" data-field="condition.value" placeholder="Valor a comparar">';
                    extraContainer.appendChild(valueCol);
                }
                if (type === 'patient_exists') {
                    const sourceCol = document.createElement('div');
                    sourceCol.className = 'col-md-6';
                    sourceCol.innerHTML = '<label class="form-label-sm">Fuente de búsqueda</label><select class="form-select form-select-sm" data-field="condition.source"><option value="local">Base de datos local</option><option value="registry">Registro Civil</option><option value="any">Cualquiera disponible</option></select>';
                    extraContainer.appendChild(sourceCol);
                }

                if (data.condition) {
                    const fieldInput = element.querySelector('[data-field="condition.field"]');
                    const valueInput = element.querySelector('[data-field="condition.value"]');
                    const sourceSelect = element.querySelector('[data-field="condition.source"]');
                    if (fieldInput) fieldInput.value = data.condition.field || '';
                    if (valueInput) valueInput.value = data.condition.value || '';
                    if (sourceSelect) sourceSelect.value = data.condition.source || 'any';
                }
            };

            updateExtraFields();
            const typeSelect = element.querySelector('[data-field="condition.type"]');
            if (typeSelect) {
                typeSelect.addEventListener('change', updateExtraFields);
            }

            const removeButton = element.querySelector('[data-action="remove-branch"]');
            if (removeButton) {
                removeButton.addEventListener('click', () => element.remove());
            }

            const addMessageButton = element.querySelector('[data-action="add-branch-message"]');
            const messageContainer = element.querySelector('[data-branch-message-list]');
            if (addMessageButton && messageContainer) {
                addMessageButton.addEventListener('click', () => {
                    appendMessage(messageContainer, {type: 'text'});
                });
            }
            renderMessages(messageContainer, data.messages || []);

            return element;
        };

        const renderNode = (data = {}) => {
            if (!nodeTemplate || !nodeList) {
                return null;
            }
            const element = nodeTemplate.content.firstElementChild.cloneNode(true);
            nodeList.appendChild(element);

            const idField = element.querySelector('[data-field="id"]');
            const typeField = element.querySelector('[data-field="type"]');
            const titleField = element.querySelector('[data-field="title"]');
            const descriptionField = element.querySelector('[data-field="description"]');
            if (idField) idField.value = data.id || '';
            if (typeField) typeField.value = data.type || 'message';
            if (titleField) titleField.value = data.title || '';
            if (descriptionField) descriptionField.value = data.description || '';

            const messageSection = element.querySelector('[data-node-section="message"]');
            const inputSection = element.querySelector('[data-node-section="input"]');
            const decisionSection = element.querySelector('[data-node-section="decision"]');

            const toggleSections = () => {
                const type = typeField.value;
                if (messageSection) messageSection.classList.toggle('d-none', type !== 'message');
                if (inputSection) inputSection.classList.toggle('d-none', type !== 'input');
                if (decisionSection) decisionSection.classList.toggle('d-none', type !== 'decision');
            };
            toggleSections();
            typeField.addEventListener('change', toggleSections);

            const removeButton = element.querySelector('[data-action="remove-node"]');
            if (removeButton) {
                removeButton.addEventListener('click', () => element.remove());
            }

            if (messageSection) {
                const messagesContainer = messageSection.querySelector('[data-message-list]');
                const addMessageButton = messageSection.querySelector('[data-action="add-message"]');
                if (addMessageButton && messagesContainer) {
                    addMessageButton.addEventListener('click', () => {
                        appendMessage(messagesContainer, {type: 'text'});
                    });
                }
                renderMessages(messagesContainer, data.messages || []);

                const responseList = messageSection.querySelector('[data-response-list]');
                const addResponseButton = messageSection.querySelector('[data-action="add-response"]');
                if (addResponseButton && responseList) {
                    addResponseButton.addEventListener('click', () => {
                        renderResponse(responseList, {keywords: []});
                    });
                }
                (data.responses || []).forEach((response) => renderResponse(responseList, response));

                const nextField = messageSection.querySelector('[data-field="next"]');
                if (nextField) {
                    nextField.value = data.next || '';
                }
            }

            if (inputSection) {
                const messagesContainer = inputSection.querySelector('[data-message-list]');
                const addMessageButton = inputSection.querySelector('[data-action="add-message"]');
                if (addMessageButton && messagesContainer) {
                    addMessageButton.addEventListener('click', () => appendMessage(messagesContainer, {type: 'text'}));
                }
                renderMessages(messagesContainer, data.messages || []);

                inputSection.querySelector('[data-field="input.field"]').value = data.input?.field || '';
                inputSection.querySelector('[data-field="input.normalize"]').value = data.input?.normalize || 'trim';
                inputSection.querySelector('[data-field="input.pattern"]').value = data.input?.pattern || '';

                const errorContainer = inputSection.querySelector('[data-error-message-list]');
                const addErrorButton = inputSection.querySelector('[data-action="add-error-message"]');
                if (addErrorButton && errorContainer) {
                    addErrorButton.addEventListener('click', () => {
                        const entry = errorMessageTemplate.content.firstElementChild.cloneNode(true);
                        const removeButton = entry.querySelector('[data-action="remove-error-message"]');
                        if (removeButton) {
                            removeButton.addEventListener('click', () => entry.remove());
                        }
                        errorContainer.appendChild(entry);
                    });
                }
                renderErrorMessages(errorContainer, data.input?.error_messages || []);

                const nextField = inputSection.querySelector('[data-field="next"]');
                if (nextField) {
                    nextField.value = data.next || '';
                }
            }

            if (decisionSection) {
                const branchList = decisionSection.querySelector('[data-branch-list]');
                const addBranchButton = decisionSection.querySelector('[data-action="add-branch"]');
                if (addBranchButton && branchList) {
                    addBranchButton.addEventListener('click', () => renderBranch(branchList, {condition: {type: 'always'}}));
                }
                (data.branches || []).forEach((branch) => renderBranch(branchList, branch));
            }

            return element;
        };

        const populateInitialData = () => {
            if (entryKeywordsField) {
                entryKeywordsField.value = (initialFlow.entry_keywords || []).join(', ');
            }

            (initialFlow.shortcuts || []).forEach((shortcut) => renderShortcut(shortcut));

            (initialFlow.nodes || []).forEach((node) => renderNode(node));

            if (fallbackMessagesContainer) {
                renderMessages(fallbackMessagesContainer, initialFlow.fallback?.messages || []);
                const addFallbackButton = document.createElement('button');
                addFallbackButton.type = 'button';
                addFallbackButton.className = 'btn btn-xs btn-outline-primary mt-2';
                addFallbackButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir mensaje';
                addFallbackButton.addEventListener('click', () => appendMessage(fallbackMessagesContainer, {type: 'text'}));
                fallbackMessagesContainer.parentElement.appendChild(addFallbackButton);
            }

            const addShortcutButton = form.querySelector('[data-action="add-shortcut"]');
            if (addShortcutButton) {
                addShortcutButton.addEventListener('click', () => renderShortcut({keywords: []}));
            }

            const addNodeButton = form.querySelector('[data-action="add-node"]');
            if (addNodeButton) {
                addNodeButton.addEventListener('click', () => renderNode({type: 'message', responses: [], messages: []}));
            }
        };

        const collectShortcuts = () => {
            const shortcuts = [];
            shortcutList?.querySelectorAll('[data-shortcut]').forEach((element) => {
                const id = element.querySelector('[data-field="id"]').value.trim();
                const title = element.querySelector('[data-field="title"]').value.trim();
                const target = element.querySelector('[data-field="target"]').value.trim();
                const keywords = parseKeywords(element.querySelector('[data-field="keywords"]').value);
                const clearContext = parseKeywords(element.querySelector('[data-field="clear_context"]').value);
                if (title === '' || target === '' || keywords.length === 0) {
                    recordValidationError('Completa el identificador, título, destino y palabras clave de cada acceso directo.', element);
                    return;
                }
                shortcuts.push({id, title, target, keywords, clear_context: clearContext});
            });
            return shortcuts;
        };

        const collectResponses = (container, contextLabel) => {
            const responses = [];
            container?.querySelectorAll('[data-response]').forEach((element, index) => {
                const id = element.querySelector('[data-field="id"]').value.trim();
                const title = element.querySelector('[data-field="title"]').value.trim();
                const target = element.querySelector('[data-field="target"]').value.trim();
                const keywords = parseKeywords(element.querySelector('[data-field="keywords"]').value);
                const clearContext = parseKeywords(element.querySelector('[data-field="clear_context"]').value);
                if (title === '' || target === '' || keywords.length === 0) {
                    recordValidationError(`Completa identificador, destino y palabras clave en la respuesta ${index + 1} de "${contextLabel}".`, element);
                    return;
                }
                const messages = collectMessages(element.querySelector('[data-response-message-list]'), `respuesta ${index + 1} de "${contextLabel}"`);
                const response = {id, title, target, keywords, clear_context: clearContext};
                if (messages.length > 0) {
                    response.messages = messages;
                }
                responses.push(response);
            });
            return responses;
        };

        const collectErrorMessages = (container) => {
            const messages = [];
            container?.querySelectorAll('[data-error-message] textarea').forEach((textarea) => {
                const value = textarea.value.trim();
                if (value !== '') {
                    messages.push({type: 'text', body: value});
                }
            });
            return messages;
        };

        const collectBranches = (container, contextLabel) => {
            const branches = [];
            container?.querySelectorAll('[data-branch]').forEach((element, index) => {
                const id = element.querySelector('[data-field="id"]').value.trim();
                const type = element.querySelector('[data-field="condition.type"]').value;
                const next = element.querySelector('[data-field="next"]').value.trim();
                if (next === '') {
                    recordValidationError(`Define el escenario siguiente en la condición ${index + 1} de "${contextLabel}".`, element);
                    return;
                }
                const condition = {type};
                const fieldInput = element.querySelector('[data-field="condition.field"]');
                const valueInput = element.querySelector('[data-field="condition.value"]');
                const sourceSelect = element.querySelector('[data-field="condition.source"]');
                if (fieldInput) {
                    const fieldValue = fieldInput.value.trim();
                    if (fieldValue === '') {
                        recordValidationError(`Indica el campo de contexto para la condición ${index + 1} de "${contextLabel}".`, element);
                        return;
                    }
                    condition.field = fieldValue;
                }
                if (valueInput) {
                    condition.value = valueInput.value.trim();
                }
                if (sourceSelect) {
                    condition.source = sourceSelect.value;
                }
                const messages = collectMessages(element.querySelector('[data-branch-message-list]'), `condición ${index + 1} de "${contextLabel}"`);
                const branch = {id, next, condition};
                if (messages.length > 0) {
                    branch.messages = messages;
                }
                branches.push(branch);
            });
            return branches;
        };

        const collectNodes = () => {
            const nodes = [];
            nodeList?.querySelectorAll('[data-node]').forEach((element, index) => {
                const id = element.querySelector('[data-field="id"]').value.trim();
                const type = element.querySelector('[data-field="type"]').value;
                const title = element.querySelector('[data-field="title"]').value.trim();
                const description = element.querySelector('[data-field="description"]').value.trim();
                if (id === '') {
                    recordValidationError(`El escenario ${index + 1} necesita un identificador.`, element);
                    return;
                }
                const node = {id, type, title, description};
                if (type === 'message') {
                    const messageSection = element.querySelector('[data-node-section="message"]');
                    const messages = collectMessages(messageSection.querySelector('[data-message-list]'), `escenario "${title || id}"`);
                    if (messages.length === 0) {
                        recordValidationError(`Añade al menos un mensaje en el escenario "${title || id}".`, messageSection);
                        return;
                    }
                    node.messages = messages;
                    const responses = collectResponses(messageSection.querySelector('[data-response-list]'), title || id);
                    if (responses.length > 0) {
                        node.responses = responses;
                    }
                    const nextField = messageSection.querySelector('[data-field="next"]');
                    if (nextField && nextField.value.trim() !== '') {
                        node.next = nextField.value.trim();
                    }
                } else if (type === 'input') {
                    const inputSection = element.querySelector('[data-node-section="input"]');
                    const messages = collectMessages(inputSection.querySelector('[data-message-list]'), `escenario "${title || id}"`);
                    if (messages.length === 0) {
                        recordValidationError(`Añade al menos un mensaje de solicitud en el escenario "${title || id}".`, inputSection);
                        return;
                    }
                    node.messages = messages;
                    const field = inputSection.querySelector('[data-field="input.field"]').value.trim();
                    const normalize = inputSection.querySelector('[data-field="input.normalize"]').value;
                    const pattern = inputSection.querySelector('[data-field="input.pattern"]').value.trim();
                    if (field === '') {
                        recordValidationError(`Indica el campo de contexto en el escenario "${title || id}".`, inputSection);
                        return;
                    }
                    node.input = {field, normalize, pattern};
                    const errors = collectErrorMessages(inputSection.querySelector('[data-error-message-list]'));
                    if (errors.length > 0) {
                        node.input.error_messages = errors;
                    }
                    const nextField = inputSection.querySelector('[data-field="next"]');
                    if (!nextField || nextField.value.trim() === '') {
                        recordValidationError(`Define el escenario siguiente para "${title || id}".`, inputSection);
                        return;
                    }
                    node.next = nextField.value.trim();
                } else if (type === 'decision') {
                    const branches = collectBranches(element.querySelector('[data-branch-list]'), title || id);
                    if (branches.length === 0) {
                        recordValidationError(`Añade al menos una condición al escenario "${title || id}".`, element);
                        return;
                    }
                    node.branches = branches;
                }
                nodes.push(node);
            });
            return nodes;
        };

        populateInitialData();

        form.addEventListener('submit', (event) => {
            resetValidationState();

            const payload = {
                version: initialFlow.version || 2,
                entry_keywords: parseKeywords(entryKeywordsField?.value || ''),
                shortcuts: collectShortcuts(),
                nodes: collectNodes(),
                const fallbackMessages = collectMessages(fallbackMessagesContainer, 'fallback');
                if (fallbackMessages.length === 0) {
                    recordValidationError('Define al menos un mensaje en la respuesta por defecto.', fallbackMessagesContainer?.parentElement || null);
                }

                fallback: {
                    title: fallbackContainer?.dataset.fallbackTitle || 'Sin coincidencia',
                    description: fallbackContainer?.dataset.fallbackDescription || 'Mensaje por defecto.',
                    messages: fallbackMessages,
                },
            };

            if (validationErrors.length > 0) {
                event.preventDefault();
                presentValidationErrors();
                return;
            }

            flowField.value = JSON.stringify(payload);
        });
    })();
