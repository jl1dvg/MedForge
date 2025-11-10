(function () {
    const root = document.querySelector('[data-inbox-root]');
    if (!root) {
        return;
    }

    const list = root.querySelector('[data-inbox-list]');
    const emptyState = root.querySelector('[data-inbox-empty]');
    const countBadge = root.querySelector('[data-inbox-count]');
    const bootstrapElement = root.querySelector('[data-inbox-bootstrap]');

    if (!list || !emptyState || !bootstrapElement) {
        return;
    }

    const endpoint = root.getAttribute('data-endpoint') || '';
    if (!endpoint) {
        return;
    }

    const pollInterval = Math.max(parseInt(root.getAttribute('data-poll-interval') || '6000', 10), 3000);

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    };

    const formatTimestamp = (value) => {
        if (!value) {
            return '';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        const pad = (num) => String(num).padStart(2, '0');

        return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const summarizeButtons = (payload) => {
        if (!payload || !Array.isArray(payload.buttons)) {
            return '';
        }

        const titles = payload.buttons
            .map((button) => {
                if (!button || typeof button !== 'object') {
                    return '';
                }

                return String(button.title || '').trim();
            })
            .filter((title) => title !== '');

        if (!titles.length) {
            return '';
        }

        return `Botones: ${titles.join(', ')}`;
    };

    const summarizeList = (payload) => {
        if (!payload || !Array.isArray(payload.sections)) {
            return '';
        }

        const totalRows = payload.sections.reduce((count, section) => {
            if (!section || typeof section !== 'object' || !Array.isArray(section.rows)) {
                return count;
            }

            return count + section.rows.length;
        }, 0);

        if (totalRows === 0) {
            return '';
        }

        const label = payload.options && payload.options.button ? `Botón: ${payload.options.button}` : '';
        const rowsLabel = `${totalRows} opción${totalRows === 1 ? '' : 'es'}`;

        return label ? `${label} · ${rowsLabel}` : rowsLabel;
    };

    const summarizeTemplate = (payload) => {
        if (!payload || typeof payload.template !== 'object' || !payload.template) {
            return '';
        }

        const template = payload.template;
        const name = template.name ? String(template.name) : '';
        const language = template.language ? String(template.language) : '';

        if (!name && !language) {
            return '';
        }

        return language ? `${name} · ${language}` : name;
    };

    const createTag = (text) => {
        const span = document.createElement('span');
        span.className = 'wa-inbox-tag';
        span.textContent = text;
        return span;
    };

    let lastId = 0;

    const updateEmptyState = () => {
        if (list.children.length === 0) {
            emptyState.classList.remove('d-none');
        } else {
            emptyState.classList.add('d-none');
        }

        if (countBadge) {
            countBadge.textContent = String(list.children.length);
        }
    };

    const renderMessage = (message) => {
        const li = document.createElement('li');
        const direction = message.direction === 'outgoing' ? 'outgoing' : 'incoming';
        const directionLabel = direction === 'incoming' ? 'Recibido' : 'Enviado';
        const badgeClass = direction === 'incoming' ? 'bg-success-light text-success' : 'bg-primary-light text-primary';
        const type = (message.message_type || 'text').toString();
        const payload = message.payload && typeof message.payload === 'object' ? message.payload : {};
        const success = payload.success !== undefined ? Boolean(payload.success) : true;

        li.className = `wa-inbox-item wa-inbox-item--${direction}`;
        li.setAttribute('data-message-id', String(message.id || '0'));

        const meta = document.createElement('div');
        meta.className = 'wa-inbox-meta d-flex justify-content-between align-items-center flex-wrap gap-2';

        const metaLeft = document.createElement('div');
        metaLeft.className = 'd-flex align-items-center gap-2';
        const badge = document.createElement('span');
        badge.className = `badge ${badgeClass}`;
        badge.textContent = success ? directionLabel : `${directionLabel} · Error`;
        metaLeft.appendChild(badge);

        if (message.wa_number) {
            const number = document.createElement('span');
            number.className = 'text-muted small';
            number.textContent = message.wa_number;
            metaLeft.appendChild(number);
        }

        meta.appendChild(metaLeft);

        const timestamp = document.createElement('div');
        timestamp.className = 'text-muted small';
        timestamp.textContent = formatTimestamp(message.created_at || message.createdAt || '');
        meta.appendChild(timestamp);

        li.appendChild(meta);

        const body = document.createElement('div');
        body.className = 'wa-inbox-body mt-2';
        const sanitized = escapeHtml(message.message_body || message.body || '');
        body.innerHTML = sanitized.replace(/\n/g, '<br>');
        li.appendChild(body);

        const tags = document.createElement('div');
        tags.className = 'wa-inbox-tags mt-3 text-muted small';
        tags.appendChild(createTag(`Tipo: ${type.toUpperCase()}`));
        if (!success) {
            tags.appendChild(createTag('No enviado'));
        }
        li.appendChild(tags);

        const extra = [];
        if (type === 'buttons') {
            const summary = summarizeButtons(payload);
            if (summary) {
                extra.push(summary);
            }
        } else if (type === 'list') {
            const summary = summarizeList(payload);
            if (summary) {
                extra.push(summary);
            }
        } else if (type === 'template') {
            const summary = summarizeTemplate(payload);
            if (summary) {
                extra.push(summary);
            }
        }

        if (extra.length) {
            const extraDiv = document.createElement('div');
            extraDiv.className = 'wa-inbox-extra text-muted small mt-1';
            extraDiv.textContent = extra.join(' · ');
            li.appendChild(extraDiv);
        }

        return li;
    };

    const appendMessages = (messages) => {
        messages.forEach((message) => {
            const id = Number(message.id || 0);
            if (!id || id <= lastId) {
                return;
            }

            const element = renderMessage(message);
            list.appendChild(element);
            lastId = Math.max(lastId, id);
        });

        updateEmptyState();
    };

    try {
        const bootstrapData = JSON.parse(bootstrapElement.textContent || '[]');
        if (Array.isArray(bootstrapData) && bootstrapData.length) {
            bootstrapData.sort((a, b) => (a.id || 0) - (b.id || 0));
            appendMessages(bootstrapData);
        }
    } catch (error) {
        console.warn('No fue posible inicializar el historial de WhatsApp.', error);
    }

    bootstrapElement.remove();
    updateEmptyState();

    const fetchUpdates = () => {
        fetch(`${endpoint}?since=${encodeURIComponent(lastId)}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((payload) => {
                if (!payload || typeof payload !== 'object' || !Array.isArray(payload.messages)) {
                    return;
                }

                if (!payload.messages.length) {
                    return;
                }

                appendMessages(payload.messages);
            })
            .catch((error) => {
                console.warn('No fue posible actualizar el feed de WhatsApp.', error);
            });
    };

    setInterval(fetchUpdates, pollInterval);
})();
