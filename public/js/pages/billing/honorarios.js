(() => {
    const charts = {};
    const endpoint = '/billing/honorarios-data';
    const chartEmptyMessage = 'Sin datos suficientes para mostrar este gráfico.';

    const elements = {
        rangeInput: document.getElementById('honorarios-range-input'),
        refreshButton: document.getElementById('honorarios-refresh'),
        cirujanoSelect: document.getElementById('honorarios-cirujano'),
        afiliacionSelect: document.getElementById('honorarios-afiliacion'),
        metricCasos: document.getElementById('metric-casos'),
        metricProcedimientos: document.getElementById('metric-procedimientos'),
        metricProduccion: document.getElementById('metric-produccion'),
        metricHonorarios: document.getElementById('metric-honorarios'),
        metricTicket: document.getElementById('metric-ticket'),
        metricHonorarioPromedio: document.getElementById('metric-honorario-promedio'),
        tableHonorarios: document.getElementById('table-honorarios'),
        ruleInputs: document.querySelectorAll('[data-rule-key]'),
    };

    if (!elements.rangeInput) {
        return;
    }

    const formatNumber = value => new Intl.NumberFormat('es-EC').format(value ?? 0);

    const formatCurrency = value => {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return '—';
        }
        return new Intl.NumberFormat('es-EC', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
        }).format(Number(value));
    };

    const setMetricText = (node, value) => {
        if (node) {
            node.textContent = value;
        }
    };

    const setChartEmpty = (chartId, message = chartEmptyMessage) => {
        const container = document.getElementById(chartId);
        if (!container) {
            return;
        }
        if (charts[chartId]) {
            charts[chartId].destroy();
            delete charts[chartId];
        }
        container.innerHTML = `<div class="chart-empty">${message}</div>`;
    };

    const renderChart = (chartId, options) => {
        const container = document.getElementById(chartId);
        if (!container || typeof ApexCharts === 'undefined') {
            return;
        }
        if (charts[chartId]) {
            charts[chartId].destroy();
            delete charts[chartId];
        }
        container.innerHTML = '';
        charts[chartId] = new ApexCharts(container, options);
        charts[chartId].render();
    };

    const renderHorizontalBar = (chartId, labels, data, color = '#38bdf8') => {
        if (!labels.length) {
            setChartEmpty(chartId);
            return;
        }
        renderChart(chartId, {
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            series: [{ data }],
            plotOptions: {
                bar: { horizontal: true, borderRadius: 6, barHeight: '60%' },
            },
            colors: [color],
            dataLabels: { enabled: false },
            xaxis: { categories: labels },
        });
    };

    const renderVerticalBar = (chartId, labels, data, color = '#0ea5e9') => {
        if (!labels.length) {
            setChartEmpty(chartId);
            return;
        }
        renderChart(chartId, {
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            series: [{ data }],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            colors: [color],
            dataLabels: { enabled: false },
            xaxis: { categories: labels },
        });
    };

    const renderTable = rows => {
        if (!elements.tableHonorarios) {
            return;
        }
        if (!rows || !rows.length) {
            elements.tableHonorarios.innerHTML =
                '<tr><td colspan="5" class="text-center text-muted">Sin datos disponibles</td></tr>';
            return;
        }
        elements.tableHonorarios.innerHTML = rows
            .map(row => {
                const cirujano = row.cirujano ?? '—';
                const casos = formatNumber(row.casos ?? 0);
                const procedimientos = formatNumber(row.procedimientos ?? 0);
                const produccion = formatCurrency(row.produccion ?? 0);
                const honorarios = formatCurrency(row.honorarios ?? 0);
                return `
                    <tr>
                        <td>${cirujano}</td>
                        <td class="text-end">${casos}</td>
                        <td class="text-end">${procedimientos}</td>
                        <td class="text-end">${produccion}</td>
                        <td class="text-end">${honorarios}</td>
                    </tr>
                `;
            })
            .join('');
    };

    const buildRulesPayload = () => {
        const rules = {};
        elements.ruleInputs.forEach(input => {
            const key = input.dataset.ruleKey;
            if (!key) {
                return;
            }
            const value = Number(input.value);
            rules[key] = Number.isNaN(value) ? 0 : value;
        });
        return rules;
    };

    const renderDashboard = payload => {
        if (!payload?.data) {
            return;
        }
        const { data } = payload;

        setMetricText(elements.metricCasos, formatNumber(data.kpis?.total_casos ?? 0));
        setMetricText(elements.metricProcedimientos, formatNumber(data.kpis?.total_procedimientos ?? 0));
        setMetricText(elements.metricProduccion, formatCurrency(data.kpis?.total_produccion ?? 0));
        setMetricText(elements.metricHonorarios, formatCurrency(data.kpis?.honorarios_estimados ?? 0));
        setMetricText(elements.metricTicket, formatCurrency(data.kpis?.ticket_promedio ?? 0));
        setMetricText(elements.metricHonorarioPromedio, formatCurrency(data.kpis?.honorario_promedio ?? 0));

        renderVerticalBar(
            'chart-honorarios-afiliacion',
            data.series?.por_afiliacion?.labels ?? [],
            data.series?.por_afiliacion?.totals ?? [],
            '#22c55e'
        );
        renderHorizontalBar(
            'chart-honorarios-cirujano',
            data.series?.por_cirujano?.labels ?? [],
            data.series?.por_cirujano?.totals ?? [],
            '#6366f1'
        );
        renderHorizontalBar(
            'chart-honorarios-procedimientos',
            data.series?.top_procedimientos?.labels ?? [],
            data.series?.top_procedimientos?.totals ?? [],
            '#0ea5e9'
        );

        renderTable(data.table ?? []);
    };

    const fetchDashboard = (filters = {}) => {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filters),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .catch(() => null);
    };

    const collectFilters = () => {
        const value = elements.rangeInput?.value ?? '';
        let dateFrom = '';
        let dateTo = '';
        if (value.includes(' - ')) {
            const [from, to] = value.split(' - ');
            dateFrom = from.trim();
            dateTo = to.trim();
        }
        return {
            date_from: dateFrom,
            date_to: dateTo,
            cirujano: elements.cirujanoSelect?.value ?? '',
            afiliacion: elements.afiliacionSelect?.value ?? '',
            reglas: buildRulesPayload(),
        };
    };

    const refresh = () => {
        fetchDashboard(collectFilters()).then(renderDashboard);
    };

    if (typeof $ !== 'undefined' && typeof $.fn?.daterangepicker === 'function') {
        const initialStart = moment().startOf('month');
        const initialEnd = moment().endOf('month');
        $(elements.rangeInput).daterangepicker(
            {
                startDate: initialStart,
                endDate: initialEnd,
                autoUpdateInput: true,
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                    fromLabel: 'Desde',
                    toLabel: 'Hasta',
                    customRangeLabel: 'Personalizado',
                },
            },
            (startDate, endDate) => {
                fetchDashboard({
                    date_from: startDate.format('YYYY-MM-DD'),
                    date_to: endDate.format('YYYY-MM-DD'),
                    cirujano: elements.cirujanoSelect?.value ?? '',
                    afiliacion: elements.afiliacionSelect?.value ?? '',
                    reglas: buildRulesPayload(),
                }).then(renderDashboard);
            }
        );
    }

    elements.refreshButton?.addEventListener('click', refresh);
    elements.cirujanoSelect?.addEventListener('change', refresh);
    elements.afiliacionSelect?.addEventListener('change', refresh);
    elements.ruleInputs?.forEach(input => input.addEventListener('change', refresh));

    refresh();
})();
