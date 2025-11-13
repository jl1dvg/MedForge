/**
 * Mailbox interactions: row selection + detail rendering.
 */
(function () {
    const table = document.querySelector('[data-mailbox-table]');
    const detail = document.querySelector('[data-mailbox-detail]');

    if (!table || !detail) {
        return;
    }

    const detailFields = {
        subject: detail.querySelector('[data-mailbox-field="subject"]'),
        contact: detail.querySelector('[data-mailbox-field="contact"]'),
        time: detail.querySelector('[data-mailbox-field="time"]'),
        body: detail.querySelector('[data-mailbox-field="body"]'),
    };
    const channelsContainer = detail.querySelector('[data-mailbox-channels]');
    const metaSection = detail.querySelector('[data-mailbox-meta-section]');
    const metaList = detail.querySelector('[data-mailbox-meta]');
    const actionsSection = detail.querySelector('[data-mailbox-actions-section]');
    const actionsList = detail.querySelector('[data-mailbox-actions]');

    const emptyState = {
        subject: detail.dataset.mailboxEmptySubject || 'Selecciona un mensaje',
        contact: detail.dataset.mailboxEmptyContact || 'Sin seleccionar',
        time: detail.dataset.mailboxEmptyTime || '',
        body: detail.dataset.mailboxEmptyBody || 'Haz clic en un mensaje para ver los detalles.',
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const renderBody = (container, value) => {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const text = (value || '').trim();
        if (text === '') {
            container.innerHTML = `<p class="text-muted">${escapeHtml(emptyState.body)}</p>`;
            return;
        }
        text.split(/\n+/).forEach((paragraph) => {
            const p = document.createElement('p');
            p.textContent = paragraph;
            container.appendChild(p);
        });
    };

    const toggleSection = (section, visible) => {
        if (!section) {
            return;
        }
        if (visible) {
            section.removeAttribute('hidden');
        } else {
            section.setAttribute('hidden', 'hidden');
        }
    };

    const setActiveRow = (row) => {
        table.querySelectorAll('[data-mailbox-row]').forEach((entry) => {
            entry.classList.toggle('is-active', entry === row);
        });
    };

    const updateDetail = (message) => {
        const hasMessage = Boolean(message);

        if (!hasMessage) {
            if (detailFields.subject) detailFields.subject.textContent = emptyState.subject;
            if (detailFields.contact) detailFields.contact.textContent = emptyState.contact;
            if (detailFields.time) detailFields.time.textContent = emptyState.time;
            renderBody(detailFields.body, '');
            toggleSection(channelsContainer, false);
            toggleSection(metaSection, false);
            toggleSection(actionsSection, false);
            return;
        }

        if (detailFields.subject) detailFields.subject.textContent = message.subject || 'Mensaje';
        const contactLabel = message.contact && typeof message.contact === 'object'
            ? (message.contact.label || '')
            : '';
        if (detailFields.contact) detailFields.contact.textContent = contactLabel || emptyState.contact;
        if (detailFields.time) detailFields.time.textContent = message.relative_time || '';

        renderBody(detailFields.body, message.body || message.snippet || '');

        if (channelsContainer) {
            channelsContainer.innerHTML = '';
            const channels = Array.isArray(message.channels) ? message.channels : [];
            if (channels.length === 0) {
                toggleSection(channelsContainer, false);
            } else {
                channels.forEach((channel) => {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-light text-dark me-1';
                    badge.textContent = channel;
                    channelsContainer.appendChild(badge);
                });
                toggleSection(channelsContainer, true);
            }
        }

        if (metaList) {
            metaList.innerHTML = '';
            const entries = message.meta && typeof message.meta === 'object'
                ? Object.entries(message.meta)
                : [];

            if (entries.length === 0) {
                toggleSection(metaSection, false);
            } else {
                entries.forEach(([label, value]) => {
                    const li = document.createElement('li');
                    li.className = 'd-flex justify-content-between py-5 border-bottom';

                    const labelSpan = document.createElement('span');
                    labelSpan.className = 'text-muted';
                    labelSpan.textContent = label;

                    const valueSpan = document.createElement('span');
                    valueSpan.textContent = value;

                    li.append(labelSpan, valueSpan);
                    metaList.appendChild(li);
                });
                toggleSection(metaSection, true);
            }
        }

        if (actionsList) {
            actionsList.innerHTML = '';
            const links = message.links && typeof message.links === 'object'
                ? Object.entries(message.links)
                : [];

            if (links.length === 0) {
                toggleSection(actionsSection, false);
            } else {
                links.forEach(([label, url]) => {
                    const anchor = document.createElement('a');
                    anchor.className = 'btn btn-sm btn-outline-primary me-2 mb-2';
                    anchor.href = url;
                    anchor.target = '_blank';
                    anchor.rel = 'noopener';
                    anchor.textContent = `Ir a ${label.charAt(0).toUpperCase()}${label.slice(1)}`;
                    actionsList.appendChild(anchor);
                });
                toggleSection(actionsSection, true);
            }
        }
    };

    const parseRowMessage = (row) => {
        if (!row) {
            return null;
        }
        const payload = row.getAttribute('data-mailbox-entry');
        if (!payload) {
            return null;
        }
        try {
            return JSON.parse(payload);
        } catch (error) {
            console.warn('No fue posible interpretar el mensaje seleccionado.', error);
            return null;
        }
    };

    const rows = () => Array.from(table.querySelectorAll('[data-mailbox-row]'));

    const selectInitialRow = () => {
        const active = rows().find((row) => row.classList.contains('is-active')) || rows()[0];
        if (!active) {
            updateDetail(null);
            return;
        }

        const message = parseRowMessage(active);
        setActiveRow(active);
        updateDetail(message);
    };

    table.addEventListener('click', (event) => {
        const row = event.target.closest('[data-mailbox-row]');
        if (!row) {
            return;
        }
        const message = parseRowMessage(row);
        if (!message) {
            return;
        }
        setActiveRow(row);
        updateDetail(message);
    });

    selectInitialRow();
})();
