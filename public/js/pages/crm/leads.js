(function () {
    'use strict';

    const root = document.getElementById('crm-root');
    if (!root) {
        return;
    }

    let bootstrapData = {};
    try {
        bootstrapData = JSON.parse(root.getAttribute('data-bootstrap') || '{}');
    } catch (error) {
        console.warn('No se pudo interpretar los datos iniciales de leads', error);
    }

    const leadStatuses = Array.isArray(bootstrapData.leadStatuses) ? bootstrapData.leadStatuses : [];
    const summaryContainer = document.getElementById('lead-status-summary');
    const summaryHint = document.getElementById('lead-summary-hint');
    const statusFilter = document.getElementById('lead-filter-status');
    const searchInput = document.getElementById('lead-search');

    const tableSection = document.getElementById('lead-table-section');
    const kanbanSection = document.getElementById('lead-kanban-section');
    const viewTableBtn = document.getElementById('lead-view-table');
    const viewKanbanBtn = document.getElementById('lead-view-kanban');

    function dispatchChange(element) {
        if (!element) return;
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setActiveStatus(status) {
        if (!summaryContainer) return;
        summaryContainer.querySelectorAll('.lead-status-chip').forEach((chip) => {
            const isActive = chip.getAttribute('data-status') === status;
            chip.classList.toggle('active', isActive);
        });
    }

    function renderSummary(metrics) {
        if (!summaryContainer) {
            return;
        }

        summaryContainer.innerHTML = '';
        const counts = metrics && metrics.by_status ? metrics.by_status : {};
        const statuses = [...leadStatuses];
        Object.keys(counts || {}).forEach((key) => {
            if (key && !statuses.includes(key)) {
                statuses.push(key);
            }
        });

        const ensureStatusKey = (value) => (value && value.length ? value : 'sin_estado');

        statuses.forEach((status) => {
            const statusKey = ensureStatusKey(status);
            const count = counts && typeof counts[statusKey] !== 'undefined' ? counts[statusKey] : 0;
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'lead-status-chip btn btn-light btn-sm d-inline-flex align-items-center gap-2';
            chip.setAttribute('data-status', statusKey);
            chip.setAttribute('data-status-filter', statusKey);
            chip.innerHTML = `<span class="text-uppercase small fw-semibold">${statusKey.replace(/_/g, ' ')}</span><span class="badge bg-primary-light text-primary">${count}</span>`;
            chip.addEventListener('click', () => {
                if (statusFilter) {
                    statusFilter.value = statusKey;
                    dispatchChange(statusFilter);
                }
                setActiveStatus(statusKey);
            });
            summaryContainer.appendChild(chip);
        });

        if (summaryHint) {
            summaryHint.textContent = 'Resumen actualizado';
        }
    }

    async function loadMetrics() {
        const metricsUrl = (document.getElementById('lead-summary-card') || {}).dataset.endpointMetrics;
        if (!metricsUrl || !summaryContainer) {
            return;
        }
        if (summaryHint) {
            summaryHint.textContent = 'Actualizando…';
        }
        try {
            const response = await fetch(metricsUrl, { credentials: 'same-origin' });
            const payload = await response.json();
            if (payload && payload.ok) {
                renderSummary(payload.data || {});
                return;
            }
        } catch (error) {
            console.warn('No se pudieron cargar las métricas de leads', error);
        }
        if (summaryHint) {
            summaryHint.textContent = 'No se pudo cargar el resumen';
        }
    }

    function toggleView(showKanban) {
        if (tableSection) {
            tableSection.classList.toggle('d-none', showKanban);
        }
        if (kanbanSection) {
            kanbanSection.classList.toggle('d-none', !showKanban);
        }
        if (viewTableBtn) {
            viewTableBtn.classList.toggle('active', !showKanban);
        }
        if (viewKanbanBtn) {
            viewKanbanBtn.classList.toggle('active', showKanban);
        }
    }

    if (viewTableBtn && viewKanbanBtn) {
        viewTableBtn.addEventListener('click', () => toggleView(false));
        viewKanbanBtn.addEventListener('click', () => toggleView(true));
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                const tableSearch = document.getElementById('lead-table-search');
                if (tableSearch) {
                    tableSearch.value = searchInput.value;
                    dispatchChange(tableSearch);
                }
            }
        });
    }

    if (summaryContainer) {
        renderSummary({ statuses: {} });
        loadMetrics();
    }
})();
