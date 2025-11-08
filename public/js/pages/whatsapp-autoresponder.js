(function () {
        const form = document.querySelector('[data-autoresponder-form]');
        if (!form) {
            return;
        }

        const flowField = document.getElementById('flow_payload');
        const validationAlert = form.querySelector('[data-validation-errors]');
        let validationErrors = [];

        const slugify = (value) => {
            if (!value) {
                return '';
            }

            let base = value.toString();
            if (typeof base.normalize === 'function') {
                base = base.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }

            const normalized = base.toLowerCase();

            return normalized
                .replace(/[^a-z0-9_-]+/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_+|_+$/g, '')
                .slice(0, 32);
        };

        const resetValidationState = () => {
            validationErrors = [];
            if (validationAlert) {
                validationAlert.classList.add('d-none');
                validationAlert.innerHTML = '';
            }
            form.querySelectorAll('[data-message].has-validation-error').forEach((element) => {
                element.classList.remove('has-validation-error');
            });
            form.querySelectorAll('[data-template-parameter].is-invalid').forEach((element) => {
                element.classList.remove('is-invalid');
            });
            form.querySelectorAll('.template-selector.is-invalid').forEach((element) => {
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

        const messageTemplate = document.getElementById('message-template');
        const buttonTemplate = document.getElementById('button-template');
        const templateCatalogInput = form.querySelector('[data-template-catalog]');
        let templateCatalog = [];

        if (templateCatalogInput) {
            try {
                templateCatalog = JSON.parse(templateCatalogInput.value || '[]');
            } catch (error) {
                console.warn('No fue posible interpretar el catálogo de plantillas', error);
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
            const typeField = messageElement.querySelector('.message-type');
            const removeMessageButton = messageElement.querySelector('[data-action="remove-message"]');
            const addButton = messageElement.querySelector('[data-action="add-button"]');
            const presetButtons = messageElement.querySelectorAll('[data-action="preset"]');

            if (typeField) {
                typeField.addEventListener('change', () => toggleMessageFields(messageElement));
            }
            if (removeMessageButton) {
                removeMessageButton.addEventListener('click', () => messageElement.remove());
            }
            if (addButton) {
                addButton.addEventListener('click', () => addButtonRow(messageElement));
            }
            presetButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const preset = button.getAttribute('data-preset');
                    if (preset) {
                        applyPreset(messageElement, preset);
                    }
                });
            });
            messageElement.querySelectorAll('[data-button]').forEach((item) => {
                const remove = item.querySelector('[data-action="remove-button"]');
                if (remove) {
                    remove.addEventListener('click', () => item.remove());
                }
            });

            ensureListControls(messageElement);
            ensureTemplateControls(messageElement);
            toggleMessageFields(messageElement);
        };

        form.querySelectorAll('[data-messages]').forEach((container) => {
            container.querySelectorAll('[data-message]').forEach((message) => hydrateMessage(message));
            const addMessageButton = container.parentElement?.querySelector('[data-action="add-message"]');
            if (addMessageButton && messageTemplate) {
                addMessageButton.addEventListener('click', () => {
                    const clone = messageTemplate.content.firstElementChild.cloneNode(true);
                    container.appendChild(clone);
                    hydrateMessage(clone);
                });
            }
        });

        const collectButtons = (messageElement) => {
            const buttons = [];
            messageElement.querySelectorAll('[data-button]').forEach((item) => {
                const title = item.querySelector('.button-title')?.value?.trim() || '';
                if (title === '') {
                    return;
                }

                const idField = item.querySelector('.button-id');
                let id = idField?.value?.trim() || '';

                if (id === '') {
                    id = slugify(title);
                    if (idField) {
                        idField.value = id;
                    }
                }

                if (id === '') {
                    return;
                }

                buttons.push({title, id});
            });
            return buttons;
        };

        const collectListData = (messageElement) => {
            const buttonLabel = messageElement.querySelector('.list-button')?.value?.trim() || 'Ver opciones';
            const sections = [];
            messageElement.querySelectorAll('[data-section]').forEach((sectionElement) => {
                const rows = [];
                sectionElement.querySelectorAll('[data-row]').forEach((rowElement) => {
                    const title = rowElement.querySelector('.row-title')?.value?.trim() || '';
                    if (title === '') {
                        return;
                    }
                    const idField = rowElement.querySelector('.row-id');
                    let id = idField?.value?.trim() || '';
                    if (id === '') {
                        id = slugify(title);
                        if (idField) {
                            idField.value = id;
                        }
                    }
                    if (id === '') {
                        return;
                    }
                    const description = rowElement.querySelector('.row-description')?.value?.trim() || '';
                    const row = {title, id};
                    if (description !== '') {
                        row.description = description;
                    }
                    rows.push(row);
                });

                if (rows.length === 0) {
                    return;
                }

                const title = sectionElement.querySelector('.section-title')?.value?.trim() || '';
                sections.push({title, rows});
            });

            return {button: buttonLabel, sections};
        };

        const collectTemplateData = (messageElement, contextLabel, messageIndex) => {
            const sectionDescription = contextLabel ? `en la sección "${contextLabel}"` : 'en esta sección';
            const messageDescription = `${sectionDescription} (mensaje ${messageIndex + 1})`;
            const name = messageElement.querySelector('.template-name')?.value?.trim() || '';
            const language = messageElement.querySelector('.template-language')?.value?.trim() || '';

            if (name === '' || language === '') {
                recordValidationError(`Selecciona una plantilla aprobada ${messageDescription}.`, messageElement);
                return null;
            }

            const category = messageElement.querySelector('.template-category')?.value?.trim() || '';
            const componentsField = messageElement.querySelector('.template-components');
            const meta = findTemplateMeta(name, language);
            const select = messageElement.querySelector('.template-selector');

            messageElement.querySelectorAll('[data-template-parameter]').forEach((input) => {
                input.classList.remove('is-invalid');
            });
            if (select) {
                select.classList.remove('is-invalid');
            }

            if (!meta) {
                recordValidationError(`La plantilla "${name}" (${language}) ya no está disponible; vuelve a seleccionarla ${messageDescription}.`, messageElement);
                if (select) {
                    select.classList.add('is-invalid');
                }
                return null;
            }

            const components = [];
            let messageHasErrors = false;

            const appendParameters = (type, parameters, extra = {}) => {
                if (!parameters || parameters.length === 0) {
                    return;
                }
                components.push(Object.assign({type, parameters}, extra));
            };

            const bodyComponent = meta.components.find((component) => component.type === 'BODY');
            if (bodyComponent && Array.isArray(bodyComponent.placeholders) && bodyComponent.placeholders.length > 0) {
                const missing = [];
                const parameters = [];
                bodyComponent.placeholders.forEach((placeholder) => {
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
                    messageHasErrors = true;
                    const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                    recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders} del cuerpo de la plantilla ${messageDescription}.`, messageElement);
                } else {
                    appendParameters('BODY', parameters);
                }
            }

            const headerComponent = meta.components.find((component) => component.type === 'HEADER' && component.format === 'TEXT');
            if (headerComponent && Array.isArray(headerComponent.placeholders) && headerComponent.placeholders.length > 0) {
                const missing = [];
                const parameters = [];
                headerComponent.placeholders.forEach((placeholder) => {
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
                    messageHasErrors = true;
                    const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                    recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders} del encabezado ${messageDescription}.`, messageElement);
                } else {
                    appendParameters('HEADER', parameters);
                }
            }

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
                            messageHasErrors = true;
                            const placeholders = missing.map((value) => `{{${value}}}`).join(', ');
                            const buttonLabel = button.text ? ` del botón "${button.text}"` : '';
                            recordValidationError(`Completa ${missing.length > 1 ? 'los parámetros' : 'el parámetro'} ${placeholders}${buttonLabel} ${messageDescription}.`, messageElement);
                        } else {
                            appendParameters('BUTTON', parameters, {
                                sub_type: button.type,
                                index: button.index,
                            });
                        }
                    });
                });

            if (messageHasErrors) {
                return null;
            }

            if (componentsField) {
                componentsField.value = JSON.stringify(components);
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
                        recordValidationError(`Completa al menos una opción con título ${messageDescription}.`, messageElement);
                        messageHasErrors = true;
                    } else {
                        payload.button = listData.button;
                        payload.sections = listData.sections;
                        if (payload.body === '') {
                            payload.body = 'Selecciona una opción para continuar';
                        }
                    }
                } else if (type === 'template') {
                    const template = collectTemplateData(messageElement, contextLabel, index);
                    if (!template) {
                        messageHasErrors = true;
                    } else {
                        payload.template = template;
                    }
                } else if (body === '') {
                    return;
                }

                if (messageHasErrors) {
                    return;
                }

                messages.push(payload);
            });
            return messages;
        };

        const collectSection = (sectionElement, defaultLabel = '') => {
            if (!sectionElement) {
                return {};
            }
            const data = {};
            sectionElement.querySelectorAll('[data-field]').forEach((field) => {
                const key = field.getAttribute('data-field');
                if (!key) {
                    return;
                }
                data[key] = field.value;
            });
            const messagesContainer = sectionElement.querySelector('[data-messages]');
            if (messagesContainer) {
                const titleField = sectionElement.querySelector('[data-field="title"]');
                const rawTitle = titleField?.value?.trim() || '';
                const contextLabel = rawTitle !== '' ? rawTitle : defaultLabel;
                data.messages = collectMessages(messagesContainer, contextLabel);
            }
            return data;
        };

        const collectOption = (optionElement) => {
            const option = collectSection(optionElement, 'Opción del menú');
            option.id = optionElement.querySelector('.option-id')?.value || '';
            return option;
        };

        form.addEventListener('submit', (event) => {
            resetValidationState();

            const entrySection = form.querySelector('[data-section="entry"]');
            const fallbackSection = form.querySelector('[data-section="fallback"]');
            const optionSections = Array.from(form.querySelectorAll('[data-option]'));

            const payload = {
                entry: collectSection(entrySection, 'Mensaje de bienvenida'),
                fallback: collectSection(fallbackSection, 'Fallback'),
                options: optionSections.map((element) => collectOption(element)),
            };

            if (validationErrors.length > 0) {
                event.preventDefault();
                presentValidationErrors();
                return;
            }

            flowField.value = JSON.stringify(payload);
        });
    })();
