(function () {
    const tableEl = document.getElementById('billing-no-facturados-table');
    if (!tableEl || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable !== 'function') {
        return;
    }

    const $ = window.jQuery;
    const searchInput = document.getElementById('billing-search');
    const refreshButton = document.getElementById('billing-refresh');
    const crearForm = document.getElementById('crear-factura-form');
    const crearFormId = document.getElementById('crear-form-id');
    const crearHcNumber = document.getElementById('crear-hc-number');
    const totalEl = document.getElementById('nf-total');
    const quirurgicosEl = document.getElementById('nf-quirurgicos');
    const noQuirurgicosEl = document.getElementById('nf-no-quirurgicos');

    const formatDate = function (raw) {
        if (!raw) {
            return '-';
        }

        const date = new Date(raw);
        if (Number.isNaN(date.getTime())) {
            return String(raw);
        }

        return date.toLocaleString('es-EC', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const updateSummary = function (summary) {
        const payload = summary && typeof summary === 'object' ? summary : {};

        if (totalEl) {
            totalEl.textContent = String(payload.total || 0);
        }

        const quirurgicos = payload.quirurgicos && typeof payload.quirurgicos === 'object'
            ? payload.quirurgicos
            : { cantidad: 0 };
        const noQuirurgicos = payload.no_quirurgicos && typeof payload.no_quirurgicos === 'object'
            ? payload.no_quirurgicos
            : { cantidad: 0 };

        if (quirurgicosEl) {
            quirurgicosEl.textContent = String(quirurgicos.cantidad || 0);
        }
        if (noQuirurgicosEl) {
            noQuirurgicosEl.textContent = String(noQuirurgicos.cantidad || 0);
        }
    };

    const escapeAttribute = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    const table = $('#billing-no-facturados-table').DataTable({
        processing: true,
        pageLength: 25,
        lengthChange: false,
        searching: false,
        order: [[2, 'desc']],
        ajax: {
            url: '/v2/api/billing/no-facturados',
            data: function (request) {
                request.start = 0;
                request.length = 500;
                request.busqueda = searchInput ? searchInput.value.trim() : '';
            },
            dataSrc: function (json) {
                updateSummary(json && json.summary ? json.summary : {});
                return Array.isArray(json && json.data ? json.data : null) ? json.data : [];
            },
            error: function () {
                updateSummary({});
            }
        },
        columns: [
            { data: 'form_id', defaultContent: '' },
            { data: 'hc_number', defaultContent: '' },
            {
                data: 'fecha',
                defaultContent: '',
                render: function (value, type) {
                    if (type !== 'display') {
                        return value || '';
                    }
                    return formatDate(value);
                }
            },
            { data: 'paciente', defaultContent: '' },
            { data: 'afiliacion', defaultContent: '' },
            { data: 'procedimiento', defaultContent: '' },
            { data: 'tipo', defaultContent: '' },
            { data: 'estado_agenda', defaultContent: '' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (_, type, row) {
                    if (type !== 'display') {
                        return '';
                    }

                    const formId = escapeAttribute(row && row.form_id ? row.form_id : '');
                    const hcNumber = escapeAttribute(row && row.hc_number ? row.hc_number : '');

                    return '<button type="button" class="btn btn-sm btn-primary js-facturar" data-form-id="' + formId + '" data-hc-number="' + hcNumber + '">Facturar</button>';
                }
            }
        ],
        language: {
            emptyTable: 'No hay procedimientos pendientes de facturación.',
            processing: 'Cargando...'
        }
    });

    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            table.ajax.reload();
        });
    }

    if (searchInput) {
        let searchTimer = null;

        searchInput.addEventListener('input', function () {
            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }

            searchTimer = window.setTimeout(function () {
                table.ajax.reload();
            }, 350);
        });
    }

    $('#billing-no-facturados-table').on('click', '.js-facturar', function () {
        if (!crearForm || !crearFormId || !crearHcNumber) {
            return;
        }

        const formId = this.getAttribute('data-form-id') || '';
        const hcNumber = this.getAttribute('data-hc-number') || '';

        if (!formId || !hcNumber) {
            return;
        }

        crearFormId.value = formId;
        crearHcNumber.value = hcNumber;
        crearForm.submit();
    });
})();
