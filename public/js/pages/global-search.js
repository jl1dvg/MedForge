(function () {
    const root = document.getElementById('app-global-search');
    if (!root) {
        return;
    }

    const form = root.querySelector('.global-search-form');
    const input = root.querySelector('#global-search-input');
    const resultsPanel = root.querySelector('#global-search-results');
    const MIN_LENGTH = 2;
    const REQUEST_HEADERS = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    let debounceId = null;
    let controller = null;
    let history = [];
    let historyLoaded = false;
    let lastQuery = '';
    let lastSections = [];

    const escapeHtml = (value) => {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const escapeAttr = (value) => escapeHtml(value).replace(/`/g, '&#96;');

    const showPanel = () => {
        resultsPanel.hidden = false;
        root.classList.add('global-search-open');
        input.setAttribute('aria-expanded', 'true');
    };

    const hidePanel = () => {
        resultsPanel.hidden = true;
        resultsPanel.innerHTML = '';
        root.classList.remove('global-search-open');
        input.setAttribute('aria-expanded', 'false');
    };

    const renderMessage = (message) => {
        lastSections = [];
        resultsPanel.innerHTML = `<div class="px-3 py-2 text-muted small">${escapeHtml(message)}</div>`;
        showPanel();
    };

    const renderHistory = (items) => {
        lastSections = [];
        if (!Array.isArray(items) || items.length === 0) {
            hidePanel();
            return;
        }

        const html = [];
        html.push('<div class="global-search-section-header">Búsquedas recientes</div>');
        html.push('<div class="list-group list-group-flush">');

        items.forEach((term) => {
            const safeTerm = typeof term === 'string' ? term : '';
            if (!safeTerm) {
                return;
            }

            html.push(`
                <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2 global-search-history-item" data-term="${escapeAttr(safeTerm)}">
                    <span class="text-muted"><i class="fa-regular fa-clock"></i></span>
                    <span class="text-start">${escapeHtml(safeTerm)}</span>
                </button>
            `);
        });

        html.push('</div>');
        html.push('<button type="button" class="btn btn-link btn-sm w-100 border-top global-search-clear" data-action="clear-history">Limpiar historial</button>');

        resultsPanel.innerHTML = html.join('');
        showPanel();
    };

    const renderResults = (sections) => {
        lastSections = Array.isArray(sections) ? sections : [];
        if (!lastSections.length) {
            renderMessage('No se encontraron resultados.');
            return;
        }

        const html = [];

        lastSections.forEach((section) => {
            if (!section || !Array.isArray(section.items) || section.items.length === 0) {
                return;
            }

            html.push(`<div class="global-search-section-header">${escapeHtml(section.label || '')}</div>`);
            html.push('<div class="list-group list-group-flush">');

            section.items.forEach((item) => {
                if (!item) {
                    return;
                }

                const subtitle = item.subtitle ? `<div class="global-search-item-subtitle text-muted small">${escapeHtml(item.subtitle)}</div>` : '';
                const badge = item.badge ? `<span class="badge bg-primary-subtle text-primary-emphasis ms-auto">${escapeHtml(item.badge)}</span>` : '';
                const meta = Array.isArray(item.meta) && item.meta.length
                    ? `<div class="global-search-item-meta text-muted small">${item.meta.map((metaLine) => `<span>${escapeHtml(metaLine)}</span>`).join(' · ')}</div>`
                    : '';
                const icon = item.icon ? `<span class="global-search-item-icon me-2"><i class="${escapeHtml(item.icon)}"></i></span>` : '';
                const url = item.url ? String(item.url) : '#';

                html.push(`
                    <a class="list-group-item list-group-item-action d-flex align-items-start gap-2 global-search-result" href="${escapeAttr(url)}" data-search-type="${escapeAttr(section.type || '')}">
                        ${icon}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold">${escapeHtml(item.title || '')}</span>
                                ${badge}
                            </div>
                            ${subtitle}
                            ${meta}
                        </div>
                    </a>
                `);
            });

            html.push('</div>');
        });

        resultsPanel.innerHTML = html.join('');
        showPanel();
    };

    const findFirstItem = (sections) => {
        if (!Array.isArray(sections)) {
            return null;
        }

        for (const section of sections) {
            if (!section || !Array.isArray(section.items)) {
                continue;
            }

            for (const item of section.items) {
                if (item) {
                    return item;
                }
            }
        }

        return null;
    };

    const executeSearch = (query, { openFirst = false } = {}) => {
        const trimmed = query.trim();
        if (trimmed.length < MIN_LENGTH) {
            renderMessage('Ingresa al menos 2 caracteres para buscar.');
            return;
        }

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        const { signal } = controller;
        lastQuery = trimmed;

        fetch(`/search?q=${encodeURIComponent(trimmed)}`, {
            signal,
            headers: REQUEST_HEADERS,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((payload) => {
                if (signal.aborted) {
                    return;
                }

                if (input.value.trim() !== trimmed) {
                    return;
                }

                if (Array.isArray(payload?.history)) {
                    history = payload.history;
                }

                if (payload && payload.ok === false) {
                    renderMessage(payload.message || 'No se pudo completar la búsqueda.');
                    return;
                }

                const sections = Array.isArray(payload?.data) ? payload.data : [];
                const feedbackMessage = typeof payload?.message === 'string' ? payload.message.trim() : '';

                if (!sections.length && feedbackMessage) {
                    renderMessage(feedbackMessage);
                    return;
                }

                renderResults(sections);

                if (openFirst) {
                    const firstItem = findFirstItem(lastSections);
                    if (firstItem && firstItem.url) {
                        window.location.assign(firstItem.url);
                    }
                }
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }

                renderMessage('No se pudo completar la búsqueda.');
            })
            .finally(() => {
                if (controller && controller.signal === signal) {
                    controller = null;
                }
            });
    };

    const loadHistory = () => {
        if (historyLoaded) {
            if (!input.value.trim() && history.length) {
                renderHistory(history);
            }
            return;
        }

        historyLoaded = true;

        fetch('/search', { headers: REQUEST_HEADERS })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((payload) => {
                if (Array.isArray(payload?.history)) {
                    history = payload.history;
                }

                if (!input.value.trim() && history.length) {
                    renderHistory(history);
                }
            })
            .catch(() => {
                historyLoaded = false;
            });
    };

    const clearHistory = () => {
        fetch('/search/history/clear', {
            method: 'POST',
            headers: REQUEST_HEADERS,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then(() => {
                history = [];
                hidePanel();
            })
            .catch(() => {
                renderMessage('No se pudo limpiar el historial.');
            });
    };

    input.addEventListener('focus', () => {
        loadHistory();
    });

    input.addEventListener('input', () => {
        const value = input.value.trim();

        if (debounceId) {
            window.clearTimeout(debounceId);
            debounceId = null;
        }

        if (value === '') {
            if (controller) {
                controller.abort();
            }

            if (history.length) {
                renderHistory(history);
            } else {
                hidePanel();
            }

            return;
        }

        if (value.length < MIN_LENGTH) {
            if (controller) {
                controller.abort();
            }

            renderMessage('Ingresa al menos 2 caracteres para buscar.');
            return;
        }

        debounceId = window.setTimeout(() => {
            executeSearch(value);
        }, 250);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const query = input.value.trim();

        if (query === '') {
            hidePanel();
            return;
        }

        if (query.length < MIN_LENGTH) {
            renderMessage('Ingresa al menos 2 caracteres para buscar.');
            return;
        }

        executeSearch(query);
    });

    resultsPanel.addEventListener('click', (event) => {
        const historyItem = event.target.closest('.global-search-history-item');
        if (historyItem) {
            event.preventDefault();
            const term = historyItem.dataset.term ? historyItem.dataset.term.trim() : '';
            if (term) {
                input.value = term;
                input.focus();
                executeSearch(term);
            }
            return;
        }

        const clearButton = event.target.closest('[data-action="clear-history"]');
        if (clearButton) {
            event.preventDefault();
            clearHistory();
            return;
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            hidePanel();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hidePanel();
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            const active = document.activeElement;
            if (!root.contains(active)) {
                hidePanel();
            }
        }, 150);
    });
})();
