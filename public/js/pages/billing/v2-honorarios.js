(() => {
    const endpoint = '/v2/billing/honorarios-data';
    const rangeInput = document.getElementById('honorarios-range-input');
    const doctorSelect = document.getElementById('honorarios-doctor') || document.getElementById('honorarios-cirujano');
    const sedeSelect = document.getElementById('honorarios-sede');
    const tipoSelect = document.getElementById('honorarios-tipo');
    const categoriaSelect = document.getElementById('honorarios-categoria');
    const empresaSeguroSelect = document.getElementById('honorarios-empresa-seguro');
    const seguroSelect = document.getElementById('honorarios-seguro');
    const refreshButton = document.getElementById('honorarios-refresh');
    const quickFilters = document.getElementById('honorarios-table-filters');
    const visibleSummary = document.getElementById('honorarios-visible-summary');
    let honorariosDataTable = null;
    let currentTableFilter = 'all';
    let lastTableRows = [];
    let lastTableMode = 'resumen';

    console.info('[Honorarios] script v2-honorarios cargado', {
        scriptVersion: '20260503-honorarios-sede-filter',
        hasRangeInput: Boolean(rangeInput),
        hasDoctorSelect: Boolean(doctorSelect),
        doctorOptionsCount: doctorSelect ? doctorSelect.options.length : 0,
        doctorServerOptionsCount: doctorSelect ? doctorSelect.dataset.serverOptionsCount : null,
        bladeDebug: window.medforgeHonorariosDoctorDebug || null,
    });

    if (!rangeInput || !refreshButton) {
        console.warn('[Honorarios] inicializacion detenida: faltan nodos base', {
            hasRangeInput: Boolean(rangeInput),
            hasRefreshButton: Boolean(refreshButton),
        });
        return;
    }

    const formatCurrency = value => new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));

    const formatNumber = value => new Intl.NumberFormat('es-EC').format(Number(value || 0));

    const escapeHtml = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const badge = (label, tone, icon = '') => `
        <span class="honorarios-badge honorarios-badge-${tone}">
            ${icon ? `<i class="mdi ${icon}"></i>` : ''}${escapeHtml(label)}
        </span>
    `;

    const rowMatchesQuickFilter = (row, mode = 'detalle') => {
        if (mode !== 'detalle' && ['facturadas', 'pendientes'].includes(currentTableFilter)) {
            return true;
        }
        const hasFacturacion = Number(row?.has_facturacion || 0) === 1;
        const honorario = Number(row?.honorarios || 0);
        if (currentTableFilter === 'facturadas') {
            return hasFacturacion;
        }
        if (currentTableFilter === 'pendientes') {
            return !hasFacturacion;
        }
        if (currentTableFilter === 'con_honorario') {
            return honorario > 0;
        }
        if (currentTableFilter === 'honorario_cero') {
            return honorario <= 0;
        }
        return true;
    };

    const updateVisibleSummary = () => {
        if (!visibleSummary || !honorariosDataTable) {
            return;
        }

        const visibleRows = honorariosDataTable.rows({ search: 'applied' }).data().toArray();
        const totals = visibleRows.reduce((acc, row) => {
            acc.produccion += Number(row.produccion || 0);
            acc.honorarios += Number(row.honorarios || 0);
            if (Number(row.has_facturacion || 0) !== 1) {
                acc.pendientes += 1;
            }
            return acc;
        }, { produccion: 0, honorarios: 0, pendientes: 0 });

        visibleSummary.innerHTML = `
            <span>Filas: ${formatNumber(visibleRows.length)}</span>
            <span>Recolectado: ${formatCurrency(totals.produccion)}</span>
            <span>Honorarios: ${formatCurrency(totals.honorarios)}</span>
            <span>Pendientes: ${formatNumber(totals.pendientes)}</span>
        `;
    };

    const destroyDataTable = () => {
        if (honorariosDataTable && typeof honorariosDataTable.destroy === 'function') {
            honorariosDataTable.destroy();
        }
        honorariosDataTable = null;
    };

    const initDataTable = (table, rows, mode) => {
        if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
            return false;
        }

        const $ = window.jQuery;
        const $table = $(table);
        if ($.fn.dataTable && $.fn.dataTable.isDataTable(table)) {
            $table.DataTable().destroy();
        }

        const language = typeof window.medforgeDataTableLanguageEs === 'function'
            ? window.medforgeDataTableLanguageEs()
            : {};
        const hasButtons = Boolean($.fn.dataTable?.Buttons);
        const commonOptions = {
            data: rows,
            language,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
            deferRender: true,
            autoWidth: false,
            scrollX: true,
            order: [[0, 'asc']],
            dom: hasButtons ? 'Bfrtip' : 'frtip',
            buttons: hasButtons ? [
                { extend: 'csvHtml5', text: 'CSV', title: 'honorarios_medicos' },
                { extend: 'excelHtml5', text: 'Excel', title: 'honorarios_medicos' },
            ] : [],
            createdRow: (row, data) => {
                if (Number(data.has_facturacion || 0) !== 1 || Number(data.honorarios || 0) <= 0) {
                    row.classList.add('honorarios-row-alert');
                }
            },
            drawCallback: updateVisibleSummary,
        };

        const detailColumns = [
            { data: 'fecha', defaultContent: '' },
            { data: 'sede', defaultContent: '' },
            {
                data: null,
                render: row => `
                    <div>${escapeHtml(row.cirujano || '')}</div>
                    <small class="text-muted">${escapeHtml(row.realizado_por || '')}</small>
                `,
            },
            {
                data: null,
                render: row => `
                    <div>${escapeHtml(row.paciente || '')}</div>
                    <small class="text-muted">${escapeHtml(row.hc_number || '')}</small>
                `,
            },
            { data: 'tipo', defaultContent: '' },
            {
                data: null,
                render: row => `
                    <div>${escapeHtml(row.procedimiento || '')}</div>
                    <small class="text-muted">${escapeHtml(row.form_id || '')}</small>
                `,
            },
            {
                data: null,
                render: row => `
                    <div>${escapeHtml(row.afiliacion || '')}</div>
                    <small class="text-muted">${escapeHtml(row.empresa_seguro || '')}</small>
                `,
            },
            {
                data: null,
                render: row => Number(row.has_facturacion || 0) === 1
                    ? badge(row.estado_facturacion || 'Facturada', Number(row.honorarios || 0) > 0 ? 'success' : 'muted', 'mdi-check-circle-outline')
                    : badge('Pendiente', 'warning', 'mdi-clock-outline'),
            },
            {
                data: 'produccion',
                className: 'text-end',
                render: (value, type) => type === 'sort' || type === 'type' ? Number(value || 0) : formatCurrency(value || 0),
            },
            {
                data: 'honorarios',
                className: 'text-end',
                render: (value, type, row) => {
                    const amount = Number(value || 0);
                    if (type === 'sort' || type === 'type') {
                        return amount;
                    }
                    if (amount <= 0 && Number(row.has_facturacion || 0) === 1) {
                        return badge(formatCurrency(amount), 'danger', 'mdi-alert-circle-outline');
                    }
                    return formatCurrency(amount);
                },
            },
        ];

        const summaryColumns = [
            { data: 'cirujano', defaultContent: '' },
            { data: 'tipo', defaultContent: '' },
            { data: 'casos', className: 'text-end', render: (value, type) => type === 'sort' || type === 'type' ? Number(value || 0) : formatNumber(value || 0) },
            { data: 'procedimientos', className: 'text-end', render: (value, type) => type === 'sort' || type === 'type' ? Number(value || 0) : formatNumber(value || 0) },
            { data: 'produccion', className: 'text-end', render: (value, type) => type === 'sort' || type === 'type' ? Number(value || 0) : formatCurrency(value || 0) },
            { data: 'honorarios', className: 'text-end', render: (value, type) => type === 'sort' || type === 'type' ? Number(value || 0) : formatCurrency(value || 0) },
        ];

        honorariosDataTable = $table.DataTable(Object.assign({}, commonOptions, {
            columns: mode === 'detalle' ? detailColumns : summaryColumns,
        }));
        updateVisibleSummary();

        return true;
    };

    const metric = (id, value) => {
        const node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    };

    const setTable = (rows, mode = 'resumen') => {
        lastTableRows = Array.isArray(rows) ? rows : [];
        lastTableMode = mode;
        const tbody = document.getElementById('table-honorarios');
        if (!tbody) {
            return;
        }
        const table = tbody.closest('table');
        const thead = table ? table.querySelector('thead') : null;
        destroyDataTable();
        if (thead) {
            thead.innerHTML = mode === 'detalle'
                ? '<tr><th>Fecha</th><th>Sede</th><th>Doctor</th><th>Paciente</th><th>Tipo</th><th>Procedimiento</th><th>Afiliación</th><th>Facturación</th><th class="text-end">Recolectado</th><th class="text-end">Honorario</th></tr>'
                : '<tr><th>Médico</th><th>Tipo</th><th class="text-end">Atenciones</th><th class="text-end">Procedimientos</th><th class="text-end">Recolectado</th><th class="text-end">Honorarios</th></tr>';
        }

        const filteredRows = Array.isArray(rows) ? rows.filter(row => rowMatchesQuickFilter(row, mode)) : [];
        if (!Array.isArray(rows) || filteredRows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${mode === 'detalle' ? 10 : 6}" class="text-center text-muted">Sin datos</td></tr>`;
            if (visibleSummary) {
                visibleSummary.innerHTML = '<span>Filas: 0</span><span>Recolectado: $0,00</span><span>Honorarios: $0,00</span><span>Pendientes: 0</span>';
            }
            return;
        }

        tbody.innerHTML = '';
        if (initDataTable(table, filteredRows, mode)) {
            return;
        }

        if (mode === 'detalle') {
            tbody.innerHTML = filteredRows.map(row => `
                <tr>
                    <td>${escapeHtml(row.fecha ?? '—')}</td>
                    <td>${escapeHtml(row.sede ?? '—')}</td>
                    <td>
                        <div>${escapeHtml(row.cirujano ?? '—')}</div>
                        <small class="text-muted">${escapeHtml(row.realizado_por ?? '')}</small>
                    </td>
                    <td>
                        <div>${escapeHtml(row.paciente ?? '—')}</div>
                        <small class="text-muted">${escapeHtml(row.hc_number ?? '')}</small>
                    </td>
                    <td>${escapeHtml(row.tipo ?? '—')}</td>
                    <td>
                        <div>${escapeHtml(row.procedimiento ?? '—')}</div>
                        <small class="text-muted">${escapeHtml(row.form_id ?? '')}</small>
                    </td>
                    <td>
                        <div>${escapeHtml(row.afiliacion ?? '—')}</div>
                        <small class="text-muted">${escapeHtml(row.empresa_seguro ?? '')}</small>
                    </td>
                    <td>${escapeHtml(row.estado_facturacion ?? (row.has_facturacion ? 'Facturada' : 'Pendiente facturación'))}</td>
                    <td class="text-end">${formatCurrency(row.produccion ?? 0)}</td>
                    <td class="text-end">${formatCurrency(row.honorarios ?? 0)}</td>
                </tr>
            `).join('');
            return;
        }

        tbody.innerHTML = filteredRows.map(row => `
            <tr>
                <td>${escapeHtml(row.cirujano ?? '—')}</td>
                <td>${escapeHtml(row.tipo ?? '—')}</td>
                <td class="text-end">${formatNumber(row.casos ?? 0)}</td>
                <td class="text-end">${formatNumber(row.procedimientos ?? 0)}</td>
                <td class="text-end">${formatCurrency(row.produccion ?? 0)}</td>
                <td class="text-end">${formatCurrency(row.honorarios ?? 0)}</td>
            </tr>
        `).join('');
        if (visibleSummary) {
            const totals = filteredRows.reduce((acc, row) => {
                acc.produccion += Number(row.produccion || 0);
                acc.honorarios += Number(row.honorarios || 0);
                if (Number(row.has_facturacion || 0) !== 1) {
                    acc.pendientes += 1;
                }
                return acc;
            }, { produccion: 0, honorarios: 0, pendientes: 0 });
            visibleSummary.innerHTML = `
                <span>Filas: ${formatNumber(filteredRows.length)}</span>
                <span>Recolectado: ${formatCurrency(totals.produccion)}</span>
                <span>Honorarios: ${formatCurrency(totals.honorarios)}</span>
                <span>Pendientes: ${formatNumber(totals.pendientes)}</span>
            `;
        }
    };

    const renderBar = (id, labels, series, color) => {
        const container = document.getElementById(id);
        if (!container || typeof ApexCharts === 'undefined') {
            return;
        }
        container.innerHTML = '';

        if (!Array.isArray(labels) || labels.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-40">Sin datos</div>';
            return;
        }

        const chart = new ApexCharts(container, {
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            series: [{ data: series }],
            plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '60%' } },
            colors: [color],
            dataLabels: { enabled: false },
            xaxis: { categories: labels },
        });
        chart.render();
    };

    const selectedValues = select => {
        if (!select) {
            return [];
        }

        if (!select.multiple) {
            const value = String(select.value || '').trim();
            return value ? [value] : [];
        }

        const visibleOptions = Array.from(select.options || []).filter(option => !option.hidden && String(option.value || '').trim());
        const selected = Array.from(select.selectedOptions || [])
            .map(option => String(option.value || '').trim())
            .filter(Boolean);
        if (visibleOptions.length > 0 && selected.length === visibleOptions.length) {
            return [];
        }

        return selected;
    };

    const intersects = (left, right) => left.length === 0 || right.length === 0 || left.some(value => right.includes(value));

    const updateAffiliationOptionVisibility = () => {
        const selectedSeguroOptions = seguroSelect ? Array.from(seguroSelect.selectedOptions || []) : [];
        const selectedSeguroCategorias = selectedSeguroOptions
            .map(option => String(option.dataset.categoria || '').trim())
            .filter(Boolean);
        const selectedSeguroEmpresas = selectedSeguroOptions
            .map(option => String(option.dataset.empresa || '').trim())
            .filter(Boolean);

        if (categoriaSelect) {
            Array.from(categoriaSelect.options).forEach(option => {
                const value = String(option.value || '').trim();
                const visible = selectedSeguroCategorias.length === 0 || selectedSeguroCategorias.includes(value);
                option.hidden = !visible;
                if (!visible) {
                    option.selected = false;
                }
            });
        }

        let selectedCategorias = selectedValues(categoriaSelect);

        if (empresaSeguroSelect) {
            Array.from(empresaSeguroSelect.options).forEach(option => {
                const value = String(option.value || '').trim();
                const categorias = String(option.dataset.categorias || '')
                    .split(',')
                    .map(value => value.trim())
                    .filter(Boolean);
                const visibleByCategoria = intersects(selectedCategorias, categorias);
                const visibleBySeguro = selectedSeguroEmpresas.length === 0 || selectedSeguroEmpresas.includes(value);
                const visible = visibleByCategoria && visibleBySeguro;
                option.hidden = !visible;
                if (!visible) {
                    option.selected = false;
                }
            });
        }

        selectedCategorias = selectedValues(categoriaSelect);
        const selectedEmpresas = selectedValues(empresaSeguroSelect);

        if (seguroSelect) {
            Array.from(seguroSelect.options).forEach(option => {
                const categoria = String(option.dataset.categoria || '').trim();
                const empresa = String(option.dataset.empresa || '').trim();
                const visibleByCategoria = selectedCategorias.length === 0 || selectedCategorias.includes(categoria);
                const visibleByEmpresa = selectedEmpresas.length === 0 || selectedEmpresas.includes(empresa);
                const visible = visibleByCategoria && visibleByEmpresa;
                option.hidden = !visible;
                if (!visible) {
                    option.selected = false;
                }
            });
        }
    };

    const populateDoctorSelect = doctors => {
        if (!doctorSelect || !Array.isArray(doctors) || doctors.length === 0) {
            console.warn('[Honorarios] no se pudo poblar doctores desde JSON', {
                hasDoctorSelect: Boolean(doctorSelect),
                isArray: Array.isArray(doctors),
                count: Array.isArray(doctors) ? doctors.length : null,
                payload: doctors,
            });
            return;
        }

        const currentValue = String(doctorSelect.value || '');
        const existingValues = new Set(Array.from(doctorSelect.options).map(option => String(option.value || '')));
        doctors.forEach(doctor => {
            const value = String(doctor?.value || '').trim();
            const label = String(doctor?.label || value).trim();
            if (!value || existingValues.has(value)) {
                return;
            }

            const option = document.createElement('option');
            option.value = value;
            option.textContent = label || value;
            doctorSelect.appendChild(option);
            existingValues.add(value);
        });

        if (currentValue) {
            doctorSelect.value = currentValue;
        }
        console.info('[Honorarios] doctores poblados/confirmados', {
            received: doctors.length,
            optionsCount: doctorSelect.options.length,
            firstOptions: Array.from(doctorSelect.options).slice(0, 6).map(option => ({
                value: option.value,
                text: option.textContent,
            })),
        });
    };

    const hydrateDoctorOptions = () => {
        if (!doctorSelect || doctorSelect.options.length > 1) {
            return;
        }

        fetch('/v2/billing/honorarios', {
            method: 'GET',
            headers: { Accept: 'application/json' },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                console.info('[Honorarios] respuesta JSON doctores', {
                    count: (result?.data?.doctores || result?.data?.cirujanos || []).length,
                    sample: (result?.data?.doctores || result?.data?.cirujanos || []).slice(0, 5),
                    result,
                });
                populateDoctorSelect(result?.data?.doctores || result?.data?.cirujanos || []);
            })
            .catch(error => {
                console.error('[Honorarios] error cargando doctores JSON', error);
            });
    };

    const fetchData = () => {
        let dateFrom = '';
        let dateTo = '';

        const value = String(rangeInput.value || '');
        if (value.includes(' - ')) {
            const parts = value.split(' - ');
            dateFrom = (parts[0] || '').trim();
            dateTo = (parts[1] || '').trim();
        }

        const payload = {
            date_from: dateFrom,
            date_to: dateTo,
            doctor: doctorSelect ? String(doctorSelect.value || '').trim() : '',
            sede: sedeSelect ? String(sedeSelect.value || '').trim() : '',
            tipo_procedimiento: selectedValues(tipoSelect),
            categoria_seguro: selectedValues(categoriaSelect),
            empresa_seguro: selectedValues(empresaSeguroSelect),
            seguro: selectedValues(seguroSelect),
        };
        console.info('[Honorarios] payload honorarios-data', payload);

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(body => {
                        throw new Error(`HTTP ${response.status}: ${body}`);
                    });
                }
                return response.json();
            })
            .then(result => {
                const data = result && result.data ? result.data : {};
                const kpis = data.kpis || {};
                const series = data.series || {};

                console.info('[Honorarios] respuesta honorarios-data', {
                    tableMode: data.table_mode || 'resumen',
                    tableCount: Array.isArray(data.table) ? data.table.length : null,
                    kpis,
                    firstRows: Array.isArray(data.table) ? data.table.slice(0, 5) : [],
                });

                metric('metric-casos', formatNumber(kpis.total_casos || 0));
                metric('metric-procedimientos', formatNumber(kpis.total_procedimientos || 0));
                metric('metric-produccion', formatCurrency(kpis.total_produccion || 0));
                metric('metric-honorarios', formatCurrency(kpis.honorarios_estimados || 0));
                metric('metric-ticket', formatCurrency(kpis.ticket_promedio || 0));
                metric('metric-honorario-promedio', formatCurrency(kpis.honorario_promedio || 0));

                renderBar('chart-honorarios-afiliacion', series.por_afiliacion?.labels || [], series.por_afiliacion?.totals || [], '#22c55e');
                renderBar('chart-honorarios-cirujano', series.por_cirujano?.labels || [], series.por_cirujano?.totals || [], '#0ea5e9');
                renderBar('chart-honorarios-procedimientos', series.top_procedimientos?.labels || [], series.top_procedimientos?.totals || [], '#6366f1');
                setTable(data.table || [], data.table_mode || 'resumen');
            })
            .catch(error => {
                console.error('[Honorarios] error honorarios-data', error);
                metric('metric-casos', '—');
                metric('metric-procedimientos', '—');
                metric('metric-produccion', '—');
                metric('metric-honorarios', '—');
                metric('metric-ticket', '—');
                metric('metric-honorario-promedio', '—');
                setTable([]);
            });
    };

    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.daterangepicker !== 'undefined') {
        const $ = window.jQuery;
        const start = window.moment().subtract(89, 'days');
        const end = window.moment();
        $(rangeInput).daterangepicker({
            startDate: start,
            endDate: end,
            autoUpdateInput: true,
            locale: { format: 'YYYY-MM-DD' },
        }, fetchData);
    }

    refreshButton.addEventListener('click', fetchData);
    if (doctorSelect) {
        doctorSelect.addEventListener('change', fetchData);
    }
    if (sedeSelect) {
        sedeSelect.addEventListener('change', fetchData);
    }
    if (quickFilters) {
        quickFilters.addEventListener('click', event => {
            const button = event.target.closest('[data-filter]');
            if (!button) {
                return;
            }
            currentTableFilter = String(button.dataset.filter || 'all');
            quickFilters.querySelectorAll('[data-filter]').forEach(node => {
                node.classList.toggle('active', node === button);
            });
            setTable(lastTableRows, lastTableMode);
        });
    }
    [tipoSelect, categoriaSelect, empresaSeguroSelect, seguroSelect].forEach(select => {
        if (!select) {
            return;
        }
        select.addEventListener('change', () => {
            updateAffiliationOptionVisibility();
            fetchData();
        });
    });

    updateAffiliationOptionVisibility();
    hydrateDoctorOptions();
    fetchData();
})();
