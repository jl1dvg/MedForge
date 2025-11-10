(function () {
    const form = document.querySelector('[data-autoresponder-form]');
    if (!form) {
        return;
    }

    const flowField = document.getElementById('flow_payload');
    const validationAlert = form.querySelector('[data-validation-errors]');
    const flowBootstrap = form.querySelector('[data-flow-bootstrap]');
    const templateCatalogInput = form.querySelector('[data-template-catalog]');

    const templates = {
        variableRow: document.getElementById('variable-row-template'),
        scenarioCard: document.getElementById('scenario-card-template'),
        conditionRow: document.getElementById('condition-row-template'),
        actionRow: document.getElementById('action-row-template'),
        menuOption: document.getElementById('menu-option-template'),
        buttonRow: document.getElementById('button-row-template'),
        contextRow: document.getElementById('context-row-template'),
    };

    const MENU_BUTTON_LIMIT = 3;

    const VARIABLE_SOURCES = [
        {value: 'context.cedula', label: 'Última cédula ingresada'},
        {value: 'context.state', label: 'Estado actual del flujo'},
        {value: 'context.consent', label: 'Consentimiento registrado'},
        {value: 'context.awaiting_field', label: 'Campo pendiente'},
        {value: 'session.wa_number', label: 'Número de WhatsApp'},
        {value: 'patient.full_name', label: 'Nombre del paciente'},
        {value: 'patient.hc_number', label: 'Historia clínica del paciente'},
    ];

    const CONDITION_OPTIONS = [
        {value: 'always', label: 'Siempre'},
        {value: 'is_first_time', label: 'Es primera vez', input: 'boolean'},
        {value: 'has_consent', label: 'Tiene consentimiento', input: 'boolean'},
        {value: 'state_is', label: 'Estado actual es', input: 'text', placeholder: 'menu_principal'},
        {value: 'awaiting_is', label: 'Campo pendiente es', input: 'text', placeholder: 'cedula'},
        {value: 'message_in', label: 'Mensaje coincide con lista', input: 'keywords', placeholder: 'acepto, si, sí'},
        {value: 'message_contains', label: 'Mensaje contiene', input: 'keywords', placeholder: 'menu, ayuda'},
        {value: 'message_matches', label: 'Mensaje coincide con regex', input: 'pattern', placeholder: '^\\\d{10}$'},
        {value: 'last_interaction_gt', label: 'Última interacción mayor a (minutos)', input: 'number'},
        {value: 'patient_found', label: 'Paciente localizado', input: 'boolean'},
    ];

    const ACTION_OPTIONS = [
        {value: 'send_message', label: 'Enviar mensaje de texto'},
        {value: 'send_buttons', label: 'Enviar botones'},
        {value: 'set_state', label: 'Actualizar estado'},
        {value: 'set_context', label: 'Guardar en contexto'},
        {value: 'store_consent', label: 'Guardar consentimiento'},
        {value: 'lookup_patient', label: 'Validar cédula en BD'},
        {value: 'conditional', label: 'Condicional'},
        {value: 'goto_menu', label: 'Redirigir al menú'},
        {value: 'upsert_patient_from_context', label: 'Guardar paciente con datos actuales'},
    ];

    let templateCatalog = [];
    if (templateCatalogInput) {
        try {
            templateCatalog = JSON.parse(templateCatalogInput.value || '[]');
        } catch (error) {
            console.warn('No fue posible interpretar el catálogo de plantillas', error);
        }
        templateCatalogInput.name = '';
    }

    const bootstrapPayload = parseBootstrap();
    const state = initializeState(bootstrapPayload);
    const defaults = JSON.parse(JSON.stringify(state));

    const variablesPanel = form.querySelector('[data-variable-list]');
    const scenariosPanel = form.querySelector('[data-scenario-list]');
    const menuPanel = form.querySelector('[data-menu-editor]');

    const simulateButton = form.querySelector('[data-action="simulate-flow"]');
    const addScenarioButton = form.querySelector('[data-action="add-scenario"]');
    const resetVariablesButton = form.querySelector('[data-action="reset-variables"]');
    const resetMenuButton = form.querySelector('[data-action="reset-menu"]');

    if (addScenarioButton) {
        addScenarioButton.addEventListener('click', (event) => {
            event.preventDefault();
            state.scenarios.push(createDefaultScenario());
            renderScenarios();
        });
    }

    if (simulateButton) {
        simulateButton.addEventListener('click', (event) => {
            event.preventDefault();
            simulateFlow();
        });
    }

    if (resetVariablesButton) {
        resetVariablesButton.addEventListener('click', (event) => {
            event.preventDefault();
            state.variables = JSON.parse(JSON.stringify(defaults.variables));
            renderVariables();
        });
    }

    if (resetMenuButton) {
        resetMenuButton.addEventListener('click', (event) => {
            event.preventDefault();
            state.menu = JSON.parse(JSON.stringify(defaults.menu));
            renderMenu();
        });
    }

    form.addEventListener('submit', (event) => {
        resetValidation();
        normalizeScenarios();

        const payload = buildPayload();
        const errors = validatePayload(payload);

        if (errors.length > 0) {
            event.preventDefault();
            presentErrors(errors);

            return;
        }

        if (flowField) {
            flowField.value = JSON.stringify(payload);
        }
    });

    renderVariables();
    renderScenarios();
    renderMenu();

    function parseBootstrap() {
        if (!flowBootstrap) {
            return {};
        }
        try {
            return JSON.parse(flowBootstrap.textContent || '{}');
        } catch (error) {
            console.warn('No fue posible interpretar la configuración del flujo', error);

            return {};
        }
    }

    function initializeState(payload) {
        const variables = [];
        const rawVariables = payload.variables || {};
        Object.keys(rawVariables).forEach((key) => {
            const entry = rawVariables[key];
            variables.push({
                key,
                label: entry.label || capitalize(key),
                source: entry.source || 'context.' + key,
                persist: Boolean(entry.persist),
            });
        });

        if (variables.length === 0) {
            variables.push(
                {key: 'cedula', label: 'Cédula', source: 'context.cedula', persist: true},
                {key: 'telefono', label: 'Teléfono', source: 'session.wa_number', persist: true},
                {key: 'nombre', label: 'Nombre completo', source: 'patient.full_name', persist: false},
                {key: 'consentimiento', label: 'Consentimiento', source: 'context.consent', persist: true},
                {key: 'estado', label: 'Estado', source: 'context.state', persist: false},
            );
        }

        const scenarios = Array.isArray(payload.scenarios) && payload.scenarios.length > 0
            ? payload.scenarios
            : [createDefaultScenario()];

        const menu = Object.keys(payload.menu || {}).length > 0 ? payload.menu : createDefaultMenu();

        return {
            variables,
            scenarios,
            menu,
        };
    }

    function renderVariables() {
        if (!variablesPanel || !templates.variableRow) {
            return;
        }
        variablesPanel.innerHTML = '';

        state.variables.forEach((variable) => {
            const clone = templates.variableRow.content.firstElementChild.cloneNode(true);
            const keyLabel = clone.querySelector('[data-variable-key]');
            const description = clone.querySelector('[data-variable-description]');
            const labelInput = clone.querySelector('[data-variable-label]');
            const sourceSelect = clone.querySelector('[data-variable-source]');
            const persistInput = clone.querySelector('[data-variable-persist]');

            if (keyLabel) {
                keyLabel.textContent = variable.key;
            }

            if (description) {
                description.textContent = variableDescription(variable.key);
            }

            if (labelInput) {
                labelInput.value = variable.label || '';
                labelInput.addEventListener('input', () => {
                    variable.label = labelInput.value.trim();
                });
            }

            if (sourceSelect) {
                sourceSelect.innerHTML = VARIABLE_SOURCES.map((option) => {
                    const selected = option.value === variable.source ? 'selected' : '';
                    return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }).join('');
                sourceSelect.value = variable.source;
                sourceSelect.addEventListener('change', () => {
                    variable.source = sourceSelect.value;
                });
            }

            if (persistInput) {
                persistInput.checked = Boolean(variable.persist);
                persistInput.addEventListener('change', () => {
                    variable.persist = persistInput.checked;
                });
            }

            variablesPanel.appendChild(clone);
        });
    }

    function renderScenarios() {
        if (!scenariosPanel || !templates.scenarioCard) {
            return;
        }
        scenariosPanel.innerHTML = '';

        state.scenarios.forEach((scenario, index) => {
            const card = templates.scenarioCard.content.firstElementChild.cloneNode(true);
            card.dataset.index = String(index);

            const idInput = card.querySelector('[data-scenario-id]');
            const nameInput = card.querySelector('[data-scenario-name]');
            const descriptionInput = card.querySelector('[data-scenario-description]');
            const addConditionButton = card.querySelector('[data-action="add-condition"]');
            const addActionButton = card.querySelector('[data-action="add-action"]');
            const moveUpButton = card.querySelector('[data-action="move-up"]');
            const moveDownButton = card.querySelector('[data-action="move-down"]');
            const removeButton = card.querySelector('[data-action="remove-scenario"]');
            const conditionList = card.querySelector('[data-condition-list]');
            const actionList = card.querySelector('[data-action-list]');

            if (idInput) {
                idInput.value = scenario.id || '';
            }

            if (nameInput) {
                nameInput.value = scenario.name || '';
                nameInput.addEventListener('input', () => {
                    scenario.name = nameInput.value;
                    if (!scenario.id && scenario.name.trim() !== '') {
                        scenario.id = slugify(scenario.name);
                    }
                });
            }

            if (descriptionInput) {
                descriptionInput.value = scenario.description || '';
                descriptionInput.addEventListener('input', () => {
                    scenario.description = descriptionInput.value;
                });
            }

            if (addConditionButton) {
                addConditionButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    scenario.conditions = scenario.conditions || [];
                    scenario.conditions.push({type: 'always'});
                    renderConditions(conditionList, scenario);
                });
            }

            if (addActionButton) {
                addActionButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    scenario.actions = scenario.actions || [];
                    scenario.actions.push({type: 'send_message', message: {type: 'text', body: ''}});
                    renderActions(actionList, scenario.actions, scenario);
                });
            }

            if (moveUpButton) {
                moveUpButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === 0) {
                        return;
                    }
                    const temp = state.scenarios[index - 1];
                    state.scenarios[index - 1] = state.scenarios[index];
                    state.scenarios[index] = temp;
                    renderScenarios();
                });
            }

            if (moveDownButton) {
                moveDownButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === state.scenarios.length - 1) {
                        return;
                    }
                    const temp = state.scenarios[index + 1];
                    state.scenarios[index + 1] = state.scenarios[index];
                    state.scenarios[index] = temp;
                    renderScenarios();
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    state.scenarios.splice(index, 1);
                    if (state.scenarios.length === 0) {
                        state.scenarios.push(createDefaultScenario());
                    }
                    renderScenarios();
                });
            }

            renderConditions(conditionList, scenario);
            renderActions(actionList, scenario.actions || [], scenario);

            scenariosPanel.appendChild(card);
        });
    }

    function renderConditions(container, scenario) {
        if (!container || !templates.conditionRow) {
            return;
        }
        container.innerHTML = '';
        scenario.conditions = Array.isArray(scenario.conditions) && scenario.conditions.length > 0
            ? scenario.conditions
            : [{type: 'always'}];

        scenario.conditions.forEach((condition, index) => {
            const row = templates.conditionRow.content.firstElementChild.cloneNode(true);
            const typeSelect = row.querySelector('[data-condition-type]');
            const fieldsContainer = row.querySelector('[data-condition-fields]');
            const removeButton = row.querySelector('[data-action="remove-condition"]');

            if (typeSelect) {
                typeSelect.innerHTML = CONDITION_OPTIONS.map((option) => {
                    const selected = option.value === (condition.type || 'always') ? 'selected' : '';
                    return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }).join('');
                typeSelect.value = condition.type || 'always';
                typeSelect.addEventListener('change', () => {
                    condition.type = typeSelect.value;
                    delete condition.values;
                    delete condition.keywords;
                    delete condition.pattern;
                    delete condition.minutes;
                    delete condition.value;
                    renderConditionFields(fieldsContainer, condition);
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    scenario.conditions.splice(index, 1);
                    renderConditions(container, scenario);
                });
            }

            renderConditionFields(fieldsContainer, condition);

            container.appendChild(row);
        });
    }

    function renderConditionFields(container, condition) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const option = CONDITION_OPTIONS.find((entry) => entry.value === condition.type);
        const inputType = option ? option.input : null;

        if (!inputType) {
            return;
        }

        if (inputType === 'boolean') {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.innerHTML = '<option value="true">Sí</option><option value="false">No</option>';
            select.value = String(condition.value ?? true);
            select.addEventListener('change', () => {
                condition.value = select.value === 'true';
            });
            container.appendChild(select);

            return;
        }

        if (inputType === 'keywords') {
            const textarea = document.createElement('textarea');
            textarea.className = 'form-control form-control-sm';
            textarea.rows = 2;
            textarea.placeholder = option?.placeholder || 'opcion 1, opcion 2';
            textarea.value = Array.isArray(condition.values || condition.keywords)
                ? (condition.values || condition.keywords).join(', ')
                : '';
            textarea.addEventListener('input', () => {
                const values = textarea.value.split(/[,\n]/).map((value) => value.trim()).filter(Boolean);
                if (condition.type === 'message_contains') {
                    condition.keywords = values;
                    delete condition.values;
                } else {
                    condition.values = values.map((value) => value.toLowerCase());
                    delete condition.keywords;
                }
            });
            container.appendChild(textarea);

            return;
        }

        if (inputType === 'pattern') {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = option?.placeholder || '';
            input.value = condition.pattern || '';
            input.addEventListener('input', () => {
                condition.pattern = input.value.trim();
            });
            container.appendChild(input);

            return;
        }

        if (inputType === 'number') {
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.className = 'form-control form-control-sm';
            input.value = condition.minutes ?? '';
            input.addEventListener('input', () => {
                const parsed = parseInt(input.value, 10);
                condition.minutes = Number.isNaN(parsed) ? 0 : parsed;
            });
            container.appendChild(input);

            return;
        }

        if (inputType === 'text') {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = option?.placeholder || '';
            input.value = condition.value || '';
            input.addEventListener('input', () => {
                condition.value = input.value.trim();
            });
            container.appendChild(input);
        }
    }

    function renderActions(container, actions, scope) {
        if (!container || !templates.actionRow) {
            return;
        }
        container.innerHTML = '';

        actions.forEach((action, index) => {
            const row = templates.actionRow.content.firstElementChild.cloneNode(true);
            const typeSelect = row.querySelector('[data-action-type]');
            const fieldsContainer = row.querySelector('[data-action-fields]');
            const upButton = row.querySelector('[data-action="action-up"]');
            const downButton = row.querySelector('[data-action="action-down"]');
            const removeButton = row.querySelector('[data-action="remove-action"]');

            if (!action.type) {
                action.type = 'send_message';
            }

            if (typeSelect) {
                typeSelect.innerHTML = ACTION_OPTIONS.map((option) => {
                    const selected = option.value === action.type ? 'selected' : '';
                    return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }).join('');
                typeSelect.value = action.type;
                typeSelect.addEventListener('change', () => {
                    action.type = typeSelect.value;
                    if (action.type === 'send_message') {
                        action.message = action.message || {type: 'text', body: ''};
                    } else if (action.type === 'send_buttons') {
                        action.message = action.message || {type: 'buttons', body: '', buttons: []};
                    } else if (action.type === 'set_context') {
                        action.values = action.values || {};
                    } else if (action.type === 'store_consent') {
                        action.value = action.value ?? true;
                    } else if (action.type === 'lookup_patient') {
                        action.field = action.field || 'cedula';
                        action.source = action.source || 'message';
                    } else if (action.type === 'conditional') {
                        action.condition = action.condition || {type: 'patient_found', value: true};
                        action.then = Array.isArray(action.then) ? action.then : [];
                        action.else = Array.isArray(action.else) ? action.else : [];
                    }
                    renderActionFields(fieldsContainer, action, scope);
                });
            }

            if (upButton) {
                upButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === 0) {
                        return;
                    }
                    const temp = actions[index - 1];
                    actions[index - 1] = actions[index];
                    actions[index] = temp;
                    renderActions(container, actions, scope);
                });
            }

            if (downButton) {
                downButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === actions.length - 1) {
                        return;
                    }
                    const temp = actions[index + 1];
                    actions[index + 1] = actions[index];
                    actions[index] = temp;
                    renderActions(container, actions, scope);
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    actions.splice(index, 1);
                    renderActions(container, actions, scope);
                });
            }

            renderActionFields(fieldsContainer, action, scope);

            container.appendChild(row);
        });
    }

    function renderActionFields(container, action, scope) {
        if (!container) {
            return;
        }
        container.innerHTML = '';

        if (action.type === 'send_message') {
            action.message = action.message || {type: 'text', body: ''};
            const textarea = document.createElement('textarea');
            textarea.className = 'form-control form-control-sm';
            textarea.rows = 3;
            textarea.placeholder = 'Escribe el mensaje a enviar';
            textarea.value = action.message.body || '';
            textarea.addEventListener('input', () => {
                action.message.type = 'text';
                action.message.body = textarea.value;
            });
            container.appendChild(textarea);

            return;
        }

        if (action.type === 'send_buttons') {
            action.message = action.message || {type: 'buttons', body: '', buttons: []};
            const bodyLabel = document.createElement('label');
            bodyLabel.className = 'form-label small text-muted';
            bodyLabel.textContent = 'Mensaje';

            const bodyInput = document.createElement('textarea');
            bodyInput.className = 'form-control form-control-sm mb-2';
            bodyInput.rows = 3;
            bodyInput.value = action.message.body || '';
            bodyInput.addEventListener('input', () => {
                action.message.type = 'buttons';
                action.message.body = bodyInput.value;
            });

            const buttonsHeader = document.createElement('div');
            buttonsHeader.className = 'd-flex justify-content-between align-items-center mb-2';
            buttonsHeader.innerHTML = '<span class="small fw-600">Botones</span>';

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'btn btn-xs btn-outline-primary';
            addButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir botón';
            addButton.addEventListener('click', () => {
                action.message.buttons = action.message.buttons || [];
                action.message.buttons.push({id: '', title: ''});
                renderButtonsList(buttonsContainer, action);
            });
            buttonsHeader.appendChild(addButton);

            const buttonsContainer = document.createElement('div');
            renderButtonsList(buttonsContainer, action);

            container.appendChild(bodyLabel);
            container.appendChild(bodyInput);
            container.appendChild(buttonsHeader);
            container.appendChild(buttonsContainer);

            return;
        }

        if (action.type === 'set_state') {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = 'Ej. menu_principal';
            input.value = action.state || '';
            input.addEventListener('input', () => {
                action.state = input.value.trim();
            });
            container.appendChild(input);

            return;
        }

        if (action.type === 'set_context') {
            action.values = action.values || {};
            const wrapper = document.createElement('div');
            renderContextList(wrapper, action);

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'btn btn-xs btn-outline-primary mt-2';
            addButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir par clave-valor';
            addButton.addEventListener('click', () => {
                action.values['nuevo_campo'] = '';
                renderContextList(wrapper, action);
            });

            container.appendChild(wrapper);
            container.appendChild(addButton);

            return;
        }

        if (action.type === 'store_consent') {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input me-2';
            checkbox.checked = action.value !== false;
            checkbox.addEventListener('change', () => {
                action.value = checkbox.checked;
            });

            const label = document.createElement('label');
            label.className = 'form-check-label small';
            label.textContent = 'Marcar como aceptado';

            const wrapper = document.createElement('div');
            wrapper.className = 'form-check form-switch';
            wrapper.appendChild(checkbox);
            wrapper.appendChild(label);

            container.appendChild(wrapper);

            return;
        }

        if (action.type === 'lookup_patient') {
            action.field = action.field || 'cedula';
            action.source = action.source || 'message';
            const fieldSelect = document.createElement('select');
            fieldSelect.className = 'form-select form-select-sm mb-2';
            fieldSelect.innerHTML = '<option value="cedula">Cédula / Historia clínica</option>';
            fieldSelect.value = action.field;
            fieldSelect.addEventListener('change', () => {
                action.field = fieldSelect.value;
            });

            const sourceSelect = document.createElement('select');
            sourceSelect.className = 'form-select form-select-sm';
            sourceSelect.innerHTML = '<option value="message">Usar mensaje actual</option><option value="context">Usar valor guardado</option>';
            sourceSelect.value = action.source;
            sourceSelect.addEventListener('change', () => {
                action.source = sourceSelect.value;
            });

            container.appendChild(fieldSelect);
            container.appendChild(sourceSelect);

            return;
        }

        if (action.type === 'conditional') {
            action.condition = action.condition || {type: 'patient_found', value: true};
            action.then = Array.isArray(action.then) ? action.then : [];
            action.else = Array.isArray(action.else) ? action.else : [];

            const conditionSelect = document.createElement('select');
            conditionSelect.className = 'form-select form-select-sm mb-2';
            conditionSelect.innerHTML = '<option value="patient_found">Si existe paciente</option><option value="has_consent">Si tiene consentimiento</option>';
            conditionSelect.value = action.condition.type || 'patient_found';
            conditionSelect.addEventListener('change', () => {
                action.condition.type = conditionSelect.value;
            });

            const thenLabel = document.createElement('div');
            thenLabel.className = 'small text-muted mb-1';
            thenLabel.textContent = 'Si la condición se cumple';

            const thenContainer = document.createElement('div');
            renderActions(thenContainer, action.then, scope);

            const elseLabel = document.createElement('div');
            elseLabel.className = 'small text-muted mt-3 mb-1';
            elseLabel.textContent = 'Si la condición no se cumple';

            const elseContainer = document.createElement('div');
            renderActions(elseContainer, action.else, scope);

            container.appendChild(conditionSelect);
            container.appendChild(thenLabel);
            container.appendChild(thenContainer);
            container.appendChild(elseLabel);
            container.appendChild(elseContainer);

            return;
        }

        if (action.type === 'goto_menu' || action.type === 'upsert_patient_from_context') {
            const info = document.createElement('div');
            info.className = 'text-muted small';
            info.textContent = action.type === 'goto_menu'
                ? 'Mostrará el menú configurado debajo.'
                : 'Asocia la conversación con la cédula actual si no existe en la base local.';
            container.appendChild(info);
        }
    }

    function renderButtonsList(container, action) {
        if (!container || !templates.buttonRow) {
            return;
        }
        container.innerHTML = '';
        const type = action.message.type || 'text';
        if (type !== 'buttons') {
            const hint = document.createElement('p');
            hint.className = 'text-muted small mb-0';
            hint.textContent = 'Este mensaje se enviará como texto simple. Cambia el tipo a "Botones interactivos" para añadir botones.';
            container.appendChild(hint);

            return;
        }

        action.message.buttons = Array.isArray(action.message.buttons) ? action.message.buttons : [];

        action.message.buttons.forEach((button, index) => {
            const row = templates.buttonRow.content.firstElementChild.cloneNode(true);
            const titleInput = row.querySelector('[data-button-title]');
            const idInput = row.querySelector('[data-button-id]');
            const removeButton = row.querySelector('[data-action="remove-button"]');

            if (titleInput) {
                titleInput.value = button.title || '';
                titleInput.addEventListener('input', () => {
                    button.title = titleInput.value;
                });
            }

            if (idInput) {
                idInput.value = button.id || '';
                idInput.addEventListener('input', () => {
                    button.id = idInput.value;
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    action.message.buttons.splice(index, 1);
                    renderButtonsList(container, action);
                });
            }

            container.appendChild(row);
        });
    }

    function renderContextList(container, action) {
        if (!container || !templates.contextRow) {
            return;
        }
        container.innerHTML = '';
        const entries = Object.keys(action.values || {});
        if (entries.length === 0) {
            action.values = {'estado': 'menu_principal'};
        }

        Object.keys(action.values).forEach((key) => {
            const row = templates.contextRow.content.firstElementChild.cloneNode(true);
            const keyInput = row.querySelector('[data-context-key]');
            const valueInput = row.querySelector('[data-context-value]');
            const removeButton = row.querySelector('[data-action="remove-context"]');

            if (keyInput) {
                keyInput.value = key;
                keyInput.addEventListener('input', () => {
                    const newKey = keyInput.value.trim();
                    if (newKey && newKey !== key) {
                        action.values[newKey] = action.values[key];
                        delete action.values[key];
                        renderContextList(container, action);
                    }
                });
            }

            if (valueInput) {
                valueInput.value = action.values[key] || '';
                valueInput.addEventListener('input', () => {
                    action.values[key] = valueInput.value;
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    delete action.values[key];
                    renderContextList(container, action);
                });
            }

            container.appendChild(row);
        });
    }

    function renderMenu() {
        if (!menuPanel) {
            return;
        }
        menuPanel.innerHTML = '';
        state.menu = state.menu || createDefaultMenu();
        state.menu.message = state.menu.message || {type: 'text', body: '', buttons: []};
        state.menu.message.type = state.menu.message.type === 'buttons' ? 'buttons' : 'text';
        if (state.menu.message.type === 'buttons') {
            state.menu.message.buttons = Array.isArray(state.menu.message.buttons) ? state.menu.message.buttons : [];
        } else {
            state.menu.message.buttons = [];
        }
        state.menu.options = Array.isArray(state.menu.options) ? state.menu.options : [];

        const messageGroup = document.createElement('div');
        messageGroup.className = 'mb-4';

        const typeLabel = document.createElement('label');
        typeLabel.className = 'form-label';
        typeLabel.textContent = 'Tipo de mensaje';

        const typeSelect = document.createElement('select');
        typeSelect.className = 'form-select mb-3';
        ['text', 'buttons'].forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value === 'buttons' ? 'Botones interactivos' : 'Mensaje de texto';
            if (state.menu.message.type === value) {
                option.selected = true;
            }
            typeSelect.appendChild(option);
        });

        typeSelect.addEventListener('change', () => {
            state.menu.message.type = typeSelect.value === 'buttons' ? 'buttons' : 'text';
            if (state.menu.message.type !== 'buttons') {
                state.menu.message.buttons = [];
            }
            updateButtonsVisibility();
        });

        const messageLabel = document.createElement('label');
        messageLabel.className = 'form-label';
        messageLabel.textContent = 'Mensaje principal';

        const messageTextarea = document.createElement('textarea');
        messageTextarea.className = 'form-control';
        messageTextarea.rows = 3;
        messageTextarea.value = state.menu.message.body || '';
        messageTextarea.addEventListener('input', () => {
            state.menu.message.body = messageTextarea.value;
        });

        const buttonsHeader = document.createElement('div');
        buttonsHeader.className = 'd-flex justify-content-between align-items-center mt-3 mb-2';
        buttonsHeader.innerHTML = '<span class="fw-600">Botones del menú</span>';

        const addMenuButton = document.createElement('button');
        addMenuButton.type = 'button';
        addMenuButton.className = 'btn btn-sm btn-outline-primary';
        addMenuButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir botón';
        addMenuButton.addEventListener('click', () => {
            if (state.menu.message.type !== 'buttons') {
                window.alert('Cambia el tipo de mensaje a "Botones interactivos" para añadir botones.');
                return;
            }

            state.menu.message.buttons = Array.isArray(state.menu.message.buttons) ? state.menu.message.buttons : [];
            if (state.menu.message.buttons.length >= MENU_BUTTON_LIMIT) {
                window.alert(`Solo puedes añadir hasta ${MENU_BUTTON_LIMIT} botones.`);
                return;
            }

            state.menu.message.buttons.push({id: '', title: ''});
            renderButtonsList(buttonsContainer, {message: state.menu.message});
            updateButtonsVisibility();
        });
        buttonsHeader.appendChild(addMenuButton);

        const buttonsContainer = document.createElement('div');
        renderButtonsList(buttonsContainer, {message: state.menu.message});

        const updateButtonsVisibility = () => {
            const showButtons = state.menu.message.type === 'buttons';
            buttonsHeader.classList.toggle('d-none', !showButtons);
            buttonsContainer.classList.toggle('d-none', !showButtons);
        };

        messageGroup.appendChild(typeLabel);
        messageGroup.appendChild(typeSelect);
        messageGroup.appendChild(messageLabel);
        messageGroup.appendChild(messageTextarea);
        messageGroup.appendChild(buttonsHeader);
        messageGroup.appendChild(buttonsContainer);

        menuPanel.appendChild(messageGroup);

        const optionsHeader = document.createElement('div');
        optionsHeader.className = 'd-flex justify-content-between align-items-center mb-2';
        optionsHeader.innerHTML = '<h6 class="mb-0">Opciones del menú</h6>';

        const addOptionButton = document.createElement('button');
        addOptionButton.type = 'button';
        addOptionButton.className = 'btn btn-sm btn-outline-primary';
        addOptionButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir opción';
        addOptionButton.addEventListener('click', () => {
            state.menu.options.push({id: '', title: '', keywords: [], actions: []});
            renderMenuOptions(optionsContainer);
        });
        optionsHeader.appendChild(addOptionButton);

        const optionsContainer = document.createElement('div');
        renderMenuOptions(optionsContainer);

        menuPanel.appendChild(optionsHeader);
        menuPanel.appendChild(optionsContainer);

        updateButtonsVisibility();
    }

    function renderMenuOptions(container) {
        if (!container || !templates.menuOption) {
            return;
        }
        container.innerHTML = '';

        state.menu.options.forEach((option, index) => {
            const node = templates.menuOption.content.firstElementChild.cloneNode(true);
            const idInput = node.querySelector('[data-option-id]');
            const titleInput = node.querySelector('[data-option-title]');
            const keywordsInput = node.querySelector('[data-option-keywords]');
            const removeButton = node.querySelector('[data-action="remove-menu-option"]');
            const addActionButton = node.querySelector('[data-action="add-option-action"]');
            const actionList = node.querySelector('[data-option-action-list]');

            if (idInput) {
                idInput.value = option.id || '';
                idInput.addEventListener('input', () => {
                    option.id = idInput.value.trim();
                });
            }

            if (titleInput) {
                titleInput.value = option.title || '';
                titleInput.addEventListener('input', () => {
                    option.title = titleInput.value;
                });
            }

            if (keywordsInput) {
                keywordsInput.value = Array.isArray(option.keywords) ? option.keywords.join(', ') : '';
                keywordsInput.addEventListener('input', () => {
                    option.keywords = keywordsInput.value.split(/[,\n]/).map((value) => value.trim()).filter(Boolean);
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    state.menu.options.splice(index, 1);
                    renderMenuOptions(container);
                });
            }

            if (addActionButton) {
                addActionButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    option.actions = option.actions || [];
                    option.actions.push({type: 'send_message', message: {type: 'text', body: ''}});
                    renderActions(actionList, option.actions, option);
                });
            }

            renderActions(actionList, option.actions || [], option);

            container.appendChild(node);
        });
    }

    function buildPayload() {
        const variablesPayload = {};
        state.variables.forEach((variable) => {
            variablesPayload[variable.key] = {
                label: variable.label || capitalize(variable.key),
                source: variable.source || 'context.' + variable.key,
                persist: Boolean(variable.persist),
            };
        });

        normalizeMenu();

        return {
            variables: variablesPayload,
            scenarios: state.scenarios,
            menu: state.menu,
        };
    }

    function normalizeMenu() {
        state.menu = state.menu || {};
        state.menu.message = state.menu.message || {};
        const type = state.menu.message.type === 'buttons' ? 'buttons' : 'text';
        state.menu.message.type = type;
        state.menu.message.body = state.menu.message.body || '';

        if (type === 'buttons') {
            state.menu.message.buttons = Array.isArray(state.menu.message.buttons)
                ? state.menu.message.buttons.filter((button) => button && (button.title || button.id)).slice(0, MENU_BUTTON_LIMIT)
                : [];
        } else {
            delete state.menu.message.buttons;
        }

        state.menu.options = Array.isArray(state.menu.options)
            ? state.menu.options.filter((option) => option && (option.id || option.title))
            : [];
    }

    function validatePayload(payload) {
        const errors = [];
        if (!Array.isArray(payload.scenarios) || payload.scenarios.length === 0) {
            errors.push('Debes definir al menos un escenario.');
        }

        payload.scenarios.forEach((scenario, index) => {
            if (!Array.isArray(scenario.actions) || scenario.actions.length === 0) {
                errors.push(`El escenario "${scenario.name || 'Escenario ' + (index + 1)}" no tiene acciones.`);
            }
        });

        return errors;
    }

    function presentErrors(errors) {
        if (!validationAlert) {
            return;
        }
        validationAlert.innerHTML = `<strong>Revisa los siguientes puntos:</strong><ul class="mb-0">${errors.map((error) => `<li>${error}</li>`).join('')}</ul>`;
        validationAlert.classList.remove('d-none');
        validationAlert.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function resetValidation() {
        if (validationAlert) {
            validationAlert.classList.add('d-none');
            validationAlert.innerHTML = '';
        }
    }

    function normalizeScenarios() {
        state.scenarios.forEach((scenario, index) => {
            if (!scenario.id || scenario.id.trim() === '') {
                scenario.id = slugify(scenario.name || `scenario_${index + 1}`);
            }
            scenario.conditions = Array.isArray(scenario.conditions) && scenario.conditions.length > 0
                ? scenario.conditions
                : [{type: 'always'}];
        });
    }

    function simulateFlow() {
        const message = window.prompt('Ingresa un mensaje de prueba');
        if (message === null) {
            return;
        }
        const normalized = normalizeText(message);
        const facts = {
            is_first_time: true,
            has_consent: false,
            state: 'inicio',
            awaiting_field: null,
            message: normalized,
            raw_message: message,
            minutes_since_last: 999,
            patient_found: false,
        };

        const match = state.scenarios.find((scenario) => {
            return (scenario.conditions || [{type: 'always'}]).every((condition) => evaluateCondition(condition, facts));
        });

        if (match) {
            window.alert(`Se activaría el escenario "${match.name || match.id}" con ${match.actions?.length || 0} acciones.`);
        } else {
            window.alert('Ningún escenario coincide con el mensaje proporcionado.');
        }
    }

    function evaluateCondition(condition, facts) {
        const type = condition.type || 'always';
        switch (type) {
            case 'always':
                return true;
            case 'is_first_time':
                return Boolean(facts.is_first_time) === Boolean(condition.value);
            case 'has_consent':
                return Boolean(facts.has_consent) === Boolean(condition.value);
            case 'state_is':
                return (facts.state || '') === (condition.value || '');
            case 'awaiting_is':
                return (facts.awaiting_field || '') === (condition.value || '');
            case 'message_in':
                return Array.isArray(condition.values)
                    ? condition.values.some((value) => value === facts.message)
                    : false;
            case 'message_contains':
                return Array.isArray(condition.keywords)
                    ? condition.keywords.some((value) => value && facts.message.includes(value))
                    : false;
            case 'message_matches':
                if (!condition.pattern) {
                    return false;
                }
                try {
                    const regex = new RegExp(condition.pattern, 'i');
                    return regex.test(facts.raw_message || '');
                } catch (error) {
                    console.warn('Expresión regular inválida en simulación', error);
                    return false;
                }
            case 'last_interaction_gt':
                return (facts.minutes_since_last || 0) >= (condition.minutes || 0);
            case 'patient_found':
                return Boolean(facts.patient_found) === Boolean(condition.value ?? true);
            default:
                return false;
        }
    }

    function createDefaultScenario() {
        return {
            id: '',
            name: 'Nuevo escenario',
            description: '',
            conditions: [{type: 'always'}],
            actions: [{type: 'send_message', message: {type: 'text', body: 'Mensaje de ejemplo.'}}],
        };
    }

    function createDefaultMenu() {
        return {
            message: {
                type: 'buttons',
                body: 'Selecciona una opción:',
                buttons: [
                    {id: 'menu_agendar', title: 'Agendar cita'},
                    {id: 'menu_resultados', title: 'Resultados'},
                ],
            },
            options: [
                {id: 'menu_agendar', title: 'Agendar cita', keywords: ['agendar', 'cita'], actions: [{type: 'send_message', message: {type: 'text', body: 'Estamos listos para agendar tu cita.'}}]},
            ],
        };
    }

    function variableDescription(key) {
        switch (key) {
            case 'cedula':
                return 'Última cédula capturada durante la conversación.';
            case 'telefono':
                return 'Número de WhatsApp del contacto.';
            case 'nombre':
                return 'Nombre completo obtenido de la base de pacientes.';
            case 'consentimiento':
                return 'Estado actual del consentimiento de datos.';
            case 'estado':
                return 'Paso actual del flujo.';
            default:
                return 'Variable personalizada.';
        }
    }

    function slugify(value) {
        if (!value) {
            return '';
        }
        return value
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 48);
    }

    function capitalize(value) {
        if (!value) {
            return '';
        }
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function normalizeText(value) {
        return value.toLowerCase().trim().replace(/\s+/g, ' ');
    }
})();
