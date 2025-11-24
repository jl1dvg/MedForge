(function () {
    const root = document.querySelector('[data-flow-builder-root]');
    const configScript = document.querySelector('[data-flowmaker-config]');

    if (!root || !configScript) {
        return;
    }

    const parsed = safeJson(configScript.textContent || '{}');
    const contract = parsed.contract || {};
    const constraints = contract.constraints || {};
    const buttonLimit = typeof constraints.buttonLimit === 'number' ? constraints.buttonLimit : 3;
    const stageValues = Array.isArray(constraints.stageValues) ? constraints.stageValues : [];

    const state = {
        flow: clone(parsed.flow || {}),
        lastPublishedAt: null,
    };

    function clone(value) {
        return JSON.parse(JSON.stringify(value || {}));
    }

    function safeJson(value) {
        try {
            return JSON.parse(value);
        } catch (error) {
            console.warn('No se pudo interpretar la configuración de Flowmaker', error);
            return {};
        }
    }

    function setStatus(message, type = 'info') {
        const alert = root.querySelector('[data-flow-builder-status]');
        if (!alert) {
            return;
        }

        if (!message) {
            alert.classList.add('d-none');
            alert.textContent = '';
            alert.className = 'alert d-none';
            return;
        }

        alert.className = `alert alert-${type}`;
        alert.textContent = message;
    }

    function render() {
        const flow = state.flow || {};

        root.innerHTML = `
            <div class="flow-builder__toolbar">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-secondary" data-action="load-stored">Cargar flujo publicado</button>
                    <button class="btn btn-sm btn-outline-secondary" data-action="load-defaults">Usar contrato por defecto</button>
                    <button class="btn btn-sm btn-outline-secondary" data-action="reset-flow">Deshacer cambios</button>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="text-muted small">Límite de botones: ${buttonLimit}</div>
                    <button class="btn btn-sm btn-primary" data-action="publish-flow">
                        <i class="mdi mdi-cloud-upload-outline me-1"></i>Publicar
                    </button>
                </div>
            </div>
            <div class="alert d-none" role="alert" data-flow-builder-status></div>
            <div class="flow-builder__grid">
                <div class="flow-builder__column">
                    ${renderSection('entry', 'Bienvenida', flow.entry || {})}
                    ${renderOptions(flow.options || [])}
                </div>
                <div class="flow-builder__column">
                    ${renderSection('fallback', 'Fallback', flow.fallback || {})}
                    ${renderScenarios(flow.scenarios || [])}
                </div>
            </div>
        `;
    }

    function renderSection(section, title, data) {
        const keywords = Array.isArray(data.keywords) ? data.keywords : [];
        const messages = Array.isArray(data.messages) ? data.messages : [];

        return `
            <div class="flow-builder-card" data-section="${section}">
                <div class="flow-builder-card__header">
                    <div>
                        <h5 class="mb-1">${title}</h5>
                        <p class="text-muted small mb-0">Administra keywords y mensajes principales.</p>
                    </div>
                </div>
                <div class="flow-builder-card__body">
                    ${renderKeywords(keywords, section)}
                    ${renderMessages(messages, section)}
                </div>
            </div>
        `;
    }

    function renderOptions(options) {
        if (!Array.isArray(options) || options.length === 0) {
            return '<div class="flow-builder-card"><div class="text-muted">No hay opciones configuradas.</div></div>';
        }

        return options
            .map((option) => {
                const id = option.id || '';
                const keywords = Array.isArray(option.keywords) ? option.keywords : [];
                const messages = Array.isArray(option.messages) ? option.messages : [];
                const followup = typeof option.followup === 'string' ? option.followup : '';

                return `
                    <div class="flow-builder-card" data-option-id="${escapeHtml(id)}">
                        <div class="flow-builder-card__header">
                            <div>
                                <h5 class="mb-1">${escapeHtml(option.title || 'Opción')}</h5>
                                <p class="text-muted small mb-0">Keywords, mensajes y copy de seguimiento.</p>
                            </div>
                            <span class="badge bg-primary-light text-primary">${escapeHtml(id)}</span>
                        </div>
                        <div class="flow-builder-card__body">
                            ${renderKeywords(keywords, `option:${id}`)}
                            ${renderMessages(messages, `option:${id}`)}
                            <div class="mb-0">
                                <label class="form-label form-label-sm">Seguimiento</label>
                                <textarea class="form-control form-control-sm" rows="2" data-action="update-followup" data-option-id="${escapeHtml(id)}">${escapeHtml(followup)}</textarea>
                            </div>
                        </div>
                    </div>
                `;
            })
            .join('');
    }

    function renderScenarios(scenarios) {
        if (!Array.isArray(scenarios) || scenarios.length === 0) {
            return '';
        }

        return `
            <div class="flow-builder-card" data-section="scenarios">
                <div class="flow-builder-card__header">
                    <div>
                        <h5 class="mb-1">Escenarios</h5>
                        <p class="text-muted small mb-0">Etapas y mensajes ejecutados por condición.</p>
                    </div>
                </div>
                <div class="flow-builder-card__body flow-builder-card__body--stacked">
                    ${scenarios
                        .map((scenario) => renderScenario(scenario))
                        .join('')}
                </div>
            </div>
        `;
    }

    function renderScenario(scenario) {
        const stage = scenario.stage || scenario.stage_id || scenario.stageId || '';
        const actions = Array.isArray(scenario.actions) ? scenario.actions : [];
        const description = typeof scenario.description === 'string' ? scenario.description : '';
        const name = scenario.name || scenario.id;

        return `
            <div class="flow-builder-card flow-builder-card--nested" data-scenario-id="${escapeHtml(scenario.id || '')}">
                <div class="flow-builder-card__header">
                    <div>
                        <h6 class="mb-1">${escapeHtml(name || 'Escenario')}</h6>
                        <p class="text-muted small mb-0">${escapeHtml(description)}</p>
                    </div>
                    <div class="flow-builder-stage">
                        <label class="form-label form-label-sm mb-0">Etapa</label>
                        <select class="form-select form-select-sm" data-action="update-stage" data-scenario-id="${escapeHtml(scenario.id || '')}">
                            ${stageValues
                                .map((value) => `<option value="${escapeHtml(value)}" ${value === stage ? 'selected' : ''}>${escapeHtml(value)}</option>`)
                                .join('')}
                        </select>
                    </div>
                </div>
                <div class="flow-builder-card__body flow-builder-card__body--stacked">
                    ${actions.map((action, index) => renderAction(action, scenario.id, index)).join('')}
                </div>
            </div>
        `;
    }

    function renderAction(action, scenarioId, index) {
        if (!action || typeof action !== 'object') {
            return '';
        }

        const type = action.type || 'send_message';
        const message = action.message || {};
        const body = typeof message.body === 'string' ? message.body : '';
        const buttons = Array.isArray(message.buttons) ? message.buttons : [];

        return `
            <div class="flow-builder-action" data-scenario-id="${escapeHtml(scenarioId || '')}" data-action-index="${index}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="text-muted small">${escapeHtml(type)}</div>
                    ${type === 'send_buttons' ? `<span class="badge bg-secondary-light text-muted">${buttons.length}/${buttonLimit} botones</span>` : ''}
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm">Mensaje</label>
                    <textarea class="form-control form-control-sm" rows="2" data-action="update-scenario-message" data-scenario-id="${escapeHtml(scenarioId || '')}" data-action-index="${index}">${escapeHtml(body)}</textarea>
                </div>
                ${type === 'send_buttons' ? renderButtons(buttons, scenarioId, index) : ''}
            </div>
        `;
    }

    function renderButtons(buttons, scenarioId, actionIndex) {
        const renderedButtons = buttons
            .map((button, index) => {
                const title = typeof button.title === 'string' ? button.title : '';
                const id = typeof button.id === 'string' ? button.id : '';

                return `
                    <div class="flow-builder-button" data-scenario-id="${escapeHtml(scenarioId || '')}" data-action-index="${actionIndex}" data-button-index="${index}">
                        <div class="row g-2 align-items-center">
                            <div class="col-6">
                                <label class="form-label form-label-sm mb-0">Título</label>
                                <input type="text" class="form-control form-control-sm" data-action="update-button-title" value="${escapeHtml(title)}">
                            </div>
                            <div class="col-5">
                                <label class="form-label form-label-sm mb-0">ID</label>
                                <input type="text" class="form-control form-control-sm" data-action="update-button-id" value="${escapeHtml(id)}">
                            </div>
                            <div class="col-1 text-end">
                                <button class="btn btn-sm btn-outline-danger" type="button" data-action="remove-button" title="Eliminar botón">
                                    <i class="mdi mdi-close"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            })
            .join('');

        const canAdd = buttons.length < buttonLimit;

        return `
            <div class="flow-builder-button-group">
                ${renderedButtons || '<div class="text-muted small">Sin botones configurados.</div>'}
                <button class="btn btn-sm btn-outline-primary" type="button" data-action="add-button" data-scenario-id="${escapeHtml(scenarioId || '')}" data-action-index="${actionIndex}" ${canAdd ? '' : 'disabled'}>
                    <i class="mdi mdi-plus"></i> Añadir botón
                </button>
                ${!canAdd ? '<div class="text-muted small">Límite máximo alcanzado.</div>' : ''}
            </div>
        `;
    }

    function renderKeywords(keywords, section) {
        const items = keywords.map((keyword, index) => `
            <span class="flow-builder-chip">
                ${escapeHtml(keyword)}
                <button class="btn btn-link btn-sm text-danger" type="button" data-action="remove-keyword" data-keyword-index="${index}" aria-label="Eliminar keyword">×</button>
            </span>
        `);

        return `
            <div class="flow-builder-keywords" data-keywords="${section}">
                <label class="form-label form-label-sm">Keywords</label>
                <div class="flow-builder-chip-list">${items.join('') || '<span class="text-muted small">Sin keywords.</span>'}</div>
                <div class="input-group input-group-sm" data-keyword-form>
                    <input type="text" class="form-control" placeholder="Escribe una keyword y presiona Enter" data-action="keyword-input">
                    <button class="btn btn-outline-secondary" type="button" data-action="add-keyword">Añadir</button>
                </div>
            </div>
        `;
    }

    function renderMessages(messages, section) {
        const renderedMessages = messages
            .map((message, index) => `
                <div class="flow-builder-message" data-message-index="${index}">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label form-label-sm mb-0">Mensaje ${index + 1}</label>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-action="remove-message">Eliminar</button>
                    </div>
                    <textarea class="form-control form-control-sm" rows="3" data-action="update-message" data-section="${section}">${escapeHtml(message.body || '')}</textarea>
                </div>
            `)
            .join('');

        return `
            <div class="flow-builder-messages" data-messages="${section}">
                ${renderedMessages || '<div class="text-muted small mb-2">No hay mensajes configurados.</div>'}
                <button class="btn btn-sm btn-outline-primary" type="button" data-action="add-message" data-section="${section}">
                    <i class="mdi mdi-plus"></i> Añadir mensaje
                </button>
            </div>
        `;
    }

    function escapeHtml(value) {
        return (value ?? '')
            .toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function resolveSection(section) {
        if (section.startsWith('option:')) {
            const id = section.replace('option:', '');
            return state.flow.options?.find((option) => option.id === id) || null;
        }

        return state.flow[section] || null;
    }

    function handleKeywordAdd(container) {
        const section = container?.dataset.keywords;
        const input = container?.querySelector('[data-action="keyword-input"]');
        if (!section || !input) {
            return;
        }

        const value = (input.value || '').trim();
        if (value === '') {
            return;
        }

        const target = resolveSection(section);
        if (!target) {
            return;
        }

        if (!Array.isArray(target.keywords)) {
            target.keywords = [];
        }

        target.keywords.push(value);
        target.keywords = Array.from(new Set(target.keywords.map((item) => item.trim()).filter(Boolean)));
        input.value = '';
        render();
    }

    function handleKeywordRemove(container, index) {
        const section = container?.dataset.keywords;
        const target = resolveSection(section || '');
        if (!target || !Array.isArray(target.keywords)) {
            return;
        }

        target.keywords.splice(index, 1);
        render();
    }

    function handleMessageUpdate(target, section) {
        const index = Number(target.closest('[data-message-index]')?.dataset.messageIndex);
        const sectionData = resolveSection(section);
        if (!sectionData || !Array.isArray(sectionData.messages) || Number.isNaN(index)) {
            return;
        }

        if (!sectionData.messages[index]) {
            sectionData.messages[index] = { type: 'text', body: '' };
        }

        sectionData.messages[index].type = sectionData.messages[index].type || 'text';
        sectionData.messages[index].body = target.value;
    }

    function handleMessageAdd(section) {
        const sectionData = resolveSection(section);
        if (!sectionData) {
            return;
        }

        if (!Array.isArray(sectionData.messages)) {
            sectionData.messages = [];
        }

        sectionData.messages.push({ type: 'text', body: '' });
        render();
    }

    function handleMessageRemove(section, container) {
        const index = Number(container?.dataset.messageIndex);
        const sectionData = resolveSection(section);
        if (!sectionData || !Array.isArray(sectionData.messages) || Number.isNaN(index)) {
            return;
        }

        sectionData.messages.splice(index, 1);
        render();
    }

    function handleFollowupUpdate(optionId, value) {
        const option = state.flow.options?.find((item) => item.id === optionId);
        if (!option) {
            return;
        }

        option.followup = value;
    }

    function handleStageUpdate(scenarioId, value) {
        const scenario = state.flow.scenarios?.find((item) => item.id === scenarioId);
        if (!scenario) {
            return;
        }

        scenario.stage = value;
        scenario.stage_id = value;
        scenario.stageId = value;
    }

    function handleScenarioMessageUpdate(scenarioId, actionIndex, value) {
        const scenario = state.flow.scenarios?.find((item) => item.id === scenarioId);
        if (!scenario || !Array.isArray(scenario.actions) || typeof actionIndex !== 'number') {
            return;
        }

        if (!scenario.actions[actionIndex]) {
            scenario.actions[actionIndex] = { type: 'send_message', message: { type: 'text', body: '' } };
        }

        const action = scenario.actions[actionIndex];
        action.message = action.message || { type: 'text' };
        action.message.body = value;
    }

    function handleButtonUpdate(scenarioId, actionIndex, buttonIndex, key, value) {
        const scenario = state.flow.scenarios?.find((item) => item.id === scenarioId);
        if (!scenario || !Array.isArray(scenario.actions)) {
            return;
        }

        const action = scenario.actions[actionIndex];
        if (!action || action.type !== 'send_buttons') {
            return;
        }

        if (!Array.isArray(action.message?.buttons)) {
            action.message = action.message || {};
            action.message.buttons = [];
        }

        if (!action.message.buttons[buttonIndex]) {
            action.message.buttons[buttonIndex] = { id: '', title: '' };
        }

        action.message.buttons[buttonIndex][key] = value;
    }

    function handleButtonAdd(scenarioId, actionIndex) {
        const scenario = state.flow.scenarios?.find((item) => item.id === scenarioId);
        if (!scenario || !Array.isArray(scenario.actions)) {
            return;
        }

        const action = scenario.actions[actionIndex];
        if (!action || action.type !== 'send_buttons') {
            return;
        }

        if (!Array.isArray(action.message?.buttons)) {
            action.message = action.message || {};
            action.message.buttons = [];
        }

        if (action.message.buttons.length >= buttonLimit) {
            return;
        }

        action.message.buttons.push({ id: '', title: '' });
        render();
    }

    function handleButtonRemove(scenarioId, actionIndex, buttonIndex) {
        const scenario = state.flow.scenarios?.find((item) => item.id === scenarioId);
        if (!scenario || !Array.isArray(scenario.actions)) {
            return;
        }

        const action = scenario.actions[actionIndex];
        if (!action || action.type !== 'send_buttons' || !Array.isArray(action.message?.buttons)) {
            return;
        }

        action.message.buttons.splice(buttonIndex, 1);
        render();
    }

    function sanitizeFlow(flow) {
        const cloned = clone(flow);

        const sanitizeMessages = (messages) =>
            (messages || [])
                .map((message) => ({
                    type: message.type || 'text',
                    body: (message.body || '').trim(),
                    buttons: Array.isArray(message.buttons)
                        ? message.buttons
                            .slice(0, buttonLimit)
                            .map((button) => ({
                                id: (button.id || '').trim(),
                                title: (button.title || '').trim(),
                            }))
                            .filter((button) => button.title !== '')
                        : undefined,
                }))
                .filter((message) => message.body !== '' || (Array.isArray(message.buttons) && message.buttons.length > 0));

        const sanitizeKeywords = (keywords) =>
            Array.from(new Set((keywords || []).map((item) => item.toString().trim()).filter(Boolean)));

        if (cloned.entry) {
            cloned.entry.keywords = sanitizeKeywords(cloned.entry.keywords);
            cloned.entry.messages = sanitizeMessages(cloned.entry.messages);
        }

        if (cloned.fallback) {
            cloned.fallback.keywords = sanitizeKeywords(cloned.fallback.keywords);
            cloned.fallback.messages = sanitizeMessages(cloned.fallback.messages);
        }

        if (Array.isArray(cloned.options)) {
            cloned.options = cloned.options.map((option) => ({
                ...option,
                keywords: sanitizeKeywords(option.keywords),
                messages: sanitizeMessages(option.messages),
                followup: (option.followup || '').trim(),
            }));
        }

        if (Array.isArray(cloned.scenarios)) {
            cloned.scenarios = cloned.scenarios.map((scenario) => ({
                ...scenario,
                actions: Array.isArray(scenario.actions)
                    ? scenario.actions.map((action) => {
                        if (action.type === 'send_buttons') {
                            const buttons = Array.isArray(action.message?.buttons)
                                ? action.message.buttons
                                    .slice(0, buttonLimit)
                                    .map((button) => ({
                                        id: (button.id || '').trim(),
                                        title: (button.title || '').trim(),
                                    }))
                                    .filter((button) => button.title !== '')
                                : [];

                            return {
                                ...action,
                                message: {
                                    ...(action.message || {}),
                                    type: 'buttons',
                                    body: (action.message?.body || '').trim(),
                                    buttons,
                                },
                            };
                        }

                        return {
                            ...action,
                            message: {
                                ...(action.message || {}),
                                body: (action.message?.body || '').trim(),
                                type: action.message?.type || 'text',
                            },
                        };
                    })
                    : [],
            }));
        }

        return cloned;
    }

    function validateFlow(flow) {
        const errors = [];

        if (!flow.entry || !Array.isArray(flow.entry.messages) || flow.entry.messages.length === 0) {
            errors.push('Falta la configuración del mensaje de bienvenida.');
        }

        if (!flow.fallback || !Array.isArray(flow.fallback.messages) || flow.fallback.messages.length === 0) {
            errors.push('Falta la configuración del mensaje de fallback.');
        }

        if (!Array.isArray(flow.options) || flow.options.length === 0) {
            errors.push('Debes definir al menos una opción del menú.');
        }

        if (Array.isArray(flow.scenarios)) {
            flow.scenarios.forEach((scenario) => {
                (scenario.actions || []).forEach((action) => {
                    const buttons = action.message?.buttons || [];
                    if (action.type === 'send_buttons' && buttons.length > buttonLimit) {
                        errors.push(`El escenario ${scenario.id || ''} supera el límite de ${buttonLimit} botones.`);
                    }
                });
            });
        }

        return errors;
    }

    async function publishFlow() {
        const sanitized = sanitizeFlow(state.flow);
        const errors = validateFlow(sanitized);

        if (errors.length > 0) {
            setStatus(errors.join(' '), 'danger');
            return;
        }

        setStatus('Publicando flujo en WhatsApp…', 'info');

        try {
            const response = await fetch(parsed.api?.publish || '/whatsapp/api/flowmaker/publish', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ flow: sanitized }),
            });

            const payload = await response.json();
            if (!response.ok) {
                setStatus(payload?.message || 'No fue posible publicar el flujo.', 'danger');
                return;
            }

            state.flow = payload.flow || sanitized;
            state.lastPublishedAt = new Date();
            render();
            setStatus(payload?.message || 'Flujo publicado correctamente.', 'success');
        } catch (error) {
            console.error('No se pudo publicar el flujo', error);
            setStatus('No se pudo publicar el flujo. Revisa la conexión e intenta nuevamente.', 'danger');
        }
    }

    function resetFlow() {
        state.flow = clone(parsed.flow || {});
        render();
        setStatus('Se restableció la configuración cargada.', 'info');
    }

    function loadDefaults() {
        const schema = contract.schema || parsed.flow;
        state.flow = clone(schema || {});
        render();
        setStatus('Se cargó el contrato por defecto de AutoresponderFlow.', 'info');
    }

    function loadStored() {
        fetch(parsed.api?.contract || '/whatsapp/api/flowmaker/contract')
            .then((response) => response.json())
            .then((data) => {
                if (data?.flow) {
                    state.flow = clone(data.flow);
                    render();
                    setStatus('Se cargó el contrato publicado más reciente.', 'info');
                } else if (data?.schema) {
                    state.flow = clone(data.schema);
                    render();
                    setStatus('Se cargó el contrato por defecto.', 'info');
                }
            })
            .catch(() => setStatus('No fue posible cargar el contrato remoto.', 'warning'));
    }

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const keywordContainer = target.closest('[data-keywords]');
        if (target.dataset.action === 'add-keyword' && keywordContainer) {
            event.preventDefault();
            handleKeywordAdd(keywordContainer);
            return;
        }

        if (target.dataset.action === 'remove-keyword' && keywordContainer) {
            const index = Number(target.dataset.keywordIndex);
            handleKeywordRemove(keywordContainer, index);
            return;
        }

        if (target.dataset.action === 'add-message') {
            const section = target.dataset.section || '';
            handleMessageAdd(section);
            return;
        }

        if (target.dataset.action === 'remove-message') {
            const section = target.closest('[data-messages]')?.dataset.messages || '';
            const container = target.closest('[data-message-index]');
            handleMessageRemove(section, container);
            return;
        }

        if (target.dataset.action === 'publish-flow') {
            publishFlow();
            return;
        }

        if (target.dataset.action === 'reset-flow') {
            resetFlow();
            return;
        }

        if (target.dataset.action === 'load-defaults') {
            loadDefaults();
            return;
        }

        if (target.dataset.action === 'load-stored') {
            loadStored();
            return;
        }

        if (target.dataset.action === 'add-button') {
            const scenarioId = target.dataset.scenarioId || '';
            const actionIndex = Number(target.dataset.actionIndex);
            handleButtonAdd(scenarioId, actionIndex);
            return;
        }

        if (target.dataset.action === 'remove-button') {
            const container = target.closest('[data-button-index]');
            const scenarioId = target.closest('[data-scenario-id]')?.dataset.scenarioId || '';
            const actionIndex = Number(target.closest('[data-action-index]')?.dataset.actionIndex);
            const buttonIndex = Number(container?.dataset.buttonIndex);
            handleButtonRemove(scenarioId, actionIndex, buttonIndex);
            return;
        }
    });

    root.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.dataset.action === 'keyword-input' && event instanceof KeyboardEvent && event.key === 'Enter') {
            event.preventDefault();
            handleKeywordAdd(target.closest('[data-keywords]'));
            return;
        }

        if (target.dataset.action === 'update-message') {
            const section = target.dataset.section || target.closest('[data-messages]')?.dataset.messages || '';
            handleMessageUpdate(target, section);
            return;
        }

        if (target.dataset.action === 'update-followup') {
            const optionId = target.dataset.optionId || '';
            handleFollowupUpdate(optionId, target.value);
            return;
        }

        if (target.dataset.action === 'update-stage') {
            const scenarioId = target.dataset.scenarioId || '';
            handleStageUpdate(scenarioId, target.value);
            return;
        }

        if (target.dataset.action === 'update-scenario-message') {
            const scenarioId = target.dataset.scenarioId || '';
            const actionIndex = Number(target.dataset.actionIndex);
            handleScenarioMessageUpdate(scenarioId, actionIndex, target.value);
            return;
        }

        if (target.dataset.action === 'update-button-title' || target.dataset.action === 'update-button-id') {
            const container = target.closest('[data-button-index]');
            const buttonIndex = Number(container?.dataset.buttonIndex);
            const scenarioId = target.closest('[data-scenario-id]')?.dataset.scenarioId || '';
            const actionIndex = Number(target.closest('[data-action-index]')?.dataset.actionIndex);
            handleButtonUpdate(scenarioId, actionIndex, buttonIndex, target.dataset.action === 'update-button-title' ? 'title' : 'id', target.value);
        }
    });

    render();
})();
