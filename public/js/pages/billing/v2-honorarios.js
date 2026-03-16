(() => {
    const endpoint = '/v2/billing/honorarios-data';
    const rangeInput = document.getElementById('honorarios-range-input');
    const cirujanoSelect = document.getElementById('honorarios-cirujano');
    const categoriaSelect = document.getElementById('honorarios-categoria');
    const empresaSeguroSelect = document.getElementById('honorarios-empresa-seguro');
    const seguroSelect = document.getElementById('honorarios-seguro');
    const refreshButton = document.getElementById('honorarios-refresh');

    if (!rangeInput || !refreshButton) {
        return;
    }

    const formatCurrency = value => new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));

    const formatNumber = value => new Intl.NumberFormat('es-EC').format(Number(value || 0));

    const metric = (id, value) => {
        const node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    };

    const setTable = rows => {
        const tbody = document.getElementById('table-honorarios');
        if (!tbody) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr>
                <td>${row.cirujano ?? '—'}</td>
                <td class="text-end">${formatNumber(row.casos ?? 0)}</td>
                <td class="text-end">${formatNumber(row.procedimientos ?? 0)}</td>
                <td class="text-end">${formatCurrency(row.produccion ?? 0)}</td>
                <td class="text-end">${formatCurrency(row.honorarios ?? 0)}</td>
            </tr>
        `).join('');
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

    const buildRules = () => {
        const rules = {};
        document.querySelectorAll('[data-rule-key]').forEach(input => {
            const key = String(input.getAttribute('data-rule-key') || '').trim();
            const val = parseFloat(String(input.value || '0'));
            if (key) {
                rules[key] = Number.isFinite(val) ? val : 0;
            }
        });
        return rules;
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
            cirujano: cirujanoSelect ? String(cirujanoSelect.value || '').trim() : '',
            categoria_seguro: categoriaSelect ? String(categoriaSelect.value || '').trim() : '',
            empresa_seguro: empresaSeguroSelect ? String(empresaSeguroSelect.value || '').trim() : '',
            seguro: seguroSelect ? String(seguroSelect.value || '').trim() : '',
            reglas: buildRules(),
        };

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                const data = result && result.data ? result.data : {};
                const kpis = data.kpis || {};
                const series = data.series || {};

                metric('metric-casos', formatNumber(kpis.total_casos || 0));
                metric('metric-procedimientos', formatNumber(kpis.total_procedimientos || 0));
                metric('metric-produccion', formatCurrency(kpis.total_produccion || 0));
                metric('metric-honorarios', formatCurrency(kpis.honorarios_estimados || 0));
                metric('metric-ticket', formatCurrency(kpis.ticket_promedio || 0));
                metric('metric-honorario-promedio', formatCurrency(kpis.honorario_promedio || 0));

                renderBar('chart-honorarios-afiliacion', series.por_afiliacion?.labels || [], series.por_afiliacion?.totals || [], '#22c55e');
                renderBar('chart-honorarios-cirujano', series.por_cirujano?.labels || [], series.por_cirujano?.totals || [], '#0ea5e9');
                renderBar('chart-honorarios-procedimientos', series.top_procedimientos?.labels || [], series.top_procedimientos?.totals || [], '#6366f1');
                setTable(data.table || []);
            })
            .catch(() => {
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
    if (cirujanoSelect) {
        cirujanoSelect.addEventListener('change', fetchData);
    }
    [categoriaSelect, empresaSeguroSelect, seguroSelect].forEach(select => {
        if (!select) {
            return;
        }
        select.addEventListener('change', fetchData);
    });
    document.querySelectorAll('[data-rule-key]').forEach(input => {
        input.addEventListener('change', fetchData);
    });

    fetchData();
})();
