(function () {
    function initAgendaTable() {
        if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
            return;
        }

        const $table = window.jQuery('#agenda-table');
        if (!$table.length || window.jQuery.fn.dataTable.isDataTable($table)) {
            return;
        }

        $table.DataTable({
            language: window.medforgeDataTableLanguageEs ? window.medforgeDataTableLanguageEs() : {},
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            deferRender: true,
            responsive: false,
            order: [[0, 'asc'], [1, 'asc']],
            columnDefs: [
                {
                    targets: [12],
                    orderable: false,
                    searchable: false,
                },
            ],
        });
    }

    initAgendaTable();

    const modal = document.getElementById('agenda-visit-modal');
    if (!modal) {
        return;
    }

    const subtitle = document.getElementById('agenda-visit-subtitle');
    const loading = document.getElementById('agenda-visit-loading');
    const errorBox = document.getElementById('agenda-visit-error');
    const content = document.getElementById('agenda-visit-content');
    const grid = document.getElementById('agenda-visit-grid');
    const procedimientos = document.getElementById('agenda-visit-procedimientos');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function formatTime(value) {
        const text = String(value ?? '').trim();
        if (!text) {
            return '-';
        }

        return text.length >= 5 ? text.slice(0, 5) : text;
    }

    function setLoadingState() {
        loading.classList.remove('d-none');
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        content.classList.add('d-none');
        subtitle.textContent = 'Cargando...';
        grid.innerHTML = '';
        procedimientos.innerHTML = '';
    }

    function openModal() {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }

    function renderCards(visita) {
        const cards = [
            ['Visita', visita?.id ?? '-'],
            ['HC', visita?.hc_number ?? '-'],
            ['Paciente', [visita?.fname, visita?.mname, visita?.lname, visita?.lname2].filter(Boolean).join(' ') || '-'],
            ['Fecha', formatDate(visita?.fecha_visita)],
            ['Hora llegada', formatTime(visita?.hora_llegada)],
            ['Afiliación', visita?.afiliacion ?? '-'],
            ['Celular', visita?.celular ?? '-'],
            ['Registrado por', visita?.usuario_registro ?? '-'],
        ];

        grid.innerHTML = cards.map(([label, value]) => `
            <div class="agenda-visit-card">
                <span class="label">${escapeHtml(label)}</span>
                <div>${escapeHtml(value)}</div>
            </div>
        `).join('');
    }

    function renderProcedimientos(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            procedimientos.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay procedimientos asociados.</td></tr>';
            return;
        }

        procedimientos.innerHTML = rows.map((row) => `
            <tr>
                <td>${escapeHtml(formatDate(row?.fecha_agenda || row?.fecha))}</td>
                <td>${escapeHtml(formatTime(row?.hora || row?.hora_llegada))}</td>
                <td>${escapeHtml(row?.form_id ?? '-')}</td>
                <td>${escapeHtml(row?.procedimiento ?? '-')}</td>
                <td>${escapeHtml(row?.doctor ?? '-')}</td>
                <td>${escapeHtml(row?.estado_agenda ?? '-')}</td>
            </tr>
        `).join('');
    }

    async function loadVisita(visitaId) {
        setLoadingState();
        openModal();

        try {
            const response = await fetch(`/v2/api/agenda/visitas/${encodeURIComponent(visitaId)}`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload?.data?.visita) {
                throw new Error(payload?.error || 'No se pudo cargar la visita.');
            }

            const visita = payload.data.visita;
            const rows = payload.data.procedimientos || [];

            subtitle.textContent = `Visita ${visita.id ?? visitaId}`;
            renderCards(visita);
            renderProcedimientos(rows);
            loading.classList.add('d-none');
            content.classList.remove('d-none');
        } catch (error) {
            loading.classList.add('d-none');
            errorBox.textContent = error?.message || 'No se pudo cargar la visita.';
            errorBox.classList.remove('d-none');
        }
    }

    document.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-agenda-view-visita]');
        if (openButton) {
            const visitaId = openButton.getAttribute('data-visita-id');
            if (visitaId) {
                loadVisita(visitaId);
            }
            return;
        }

        if (event.target.closest('[data-agenda-close-visita]')) {
            closeModal();
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
})();
