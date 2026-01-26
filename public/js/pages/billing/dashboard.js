(() => {
    const charts = {};
    const endpoint = '/billing/dashboard-data';
    const chartEmptyMessage = 'Sin datos suficientes para mostrar este gráfico.';

    const elements = {
        rangeLabel: document.getElementById('billing-range'),
        rangeInput: document.getElementById('billing-range-input'),
        refreshButton: document.getElementById('billing-refresh'),
        metricFacturas: document.getElementById('metric-facturas'),
        metricMonto: document.getElementById('metric-monto'),
        metricTicket: document.getElementById('metric-ticket'),
        metricItems: document.getElementById('metric-items'),
        metricLeakage: document.getElementById('metric-leakage'),
        metricAging: document.getElementById('metric-aging'),
        tableOldest: document.getElementById('table-oldest'),
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

    const formatDays = value => {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return '—';
        }
        return `${Number(value).toFixed(0)} días`;
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

    const renderLineChart = (chartId, labels, data, title = '') => {
        if (!labels.length) {
            setChartEmpty(chartId);
            return;
        }
        renderChart(chartId, {
            chart: { type: 'area', height: 300, toolbar: { show: false } },
            series: [{ name: title || 'Monto facturado', data }],
            xaxis: { categories: labels },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.15 } },
            colors: ['#0ea5e9'],
        });
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
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            series: [{ data }],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            colors: [color],
            dataLabels: { enabled: false },
            xaxis: { categories: labels },
        });
    };

    const renderRadial = (chartId, value) => {
        renderChart(chartId, {
            chart: { type: 'radialBar', height: 280 },
            series: [value],
            labels: ['Fuga'],
            colors: ['#f97316'],
            plotOptions: {
                radialBar: {
                    dataLabels: {
                        name: { fontSize: '16px' },
                        value: { fontSize: '28px', formatter: val => `${val.toFixed(0)}%` },
                    },
                },
            },
        });
    };

    const renderTable = rows => {
        if (!elements.tableOldest) {
            return;
        }
        if (!rows || !rows.length) {
            elements.tableOldest.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sin datos disponibles</td></tr>';
            return;
        }
        elements.tableOldest.innerHTML = rows
            .map(row => {
                const formId = row.form_id ?? '—';
                const paciente = row.paciente ?? '—';
                const afiliacion = row.afiliacion ?? '—';
                const dias = row.dias_pendiente ?? '—';
                return `
                    <tr>
                        <td>${formId}</td>
                        <td>${paciente}</td>
                        <td>${afiliacion}</td>
                        <td>${dias}</td>
                    </tr>
                `;
            })
            .join('');
    };

    const renderDashboard = payload => {
        if (!payload?.data) {
            return;
        }
        const { data, filters } = payload;

        if (elements.rangeLabel && filters) {
            elements.rangeLabel.textContent = `${filters.date_from ?? '—'} a ${filters.date_to ?? '—'}`;
        }

        setMetricText(elements.metricFacturas, formatNumber(data.kpis?.total_facturas ?? 0));
        setMetricText(elements.metricMonto, formatCurrency(data.kpis?.monto_total ?? 0));
        setMetricText(elements.metricTicket, formatCurrency(data.kpis?.ticket_promedio ?? 0));
        setMetricText(elements.metricItems, formatNumber(data.kpis?.items_promedio ?? 0));
        setMetricText(elements.metricLeakage, formatNumber(data.leakage?.total ?? 0));
        setMetricText(elements.metricAging, formatDays(data.leakage?.avg_aging));

        renderLineChart('chart-billing-dia', data.series?.por_dia?.labels ?? [], data.series?.por_dia?.totals ?? [], 'Monto');
        renderRadial('chart-leakage', data.leakage?.porcentaje ?? 0);
        renderHorizontalBar('chart-afiliacion', data.series?.por_afiliacion?.labels ?? [], data.series?.por_afiliacion?.totals ?? [], '#22c55e');
        renderHorizontalBar('chart-procedimientos', data.series?.top_procedimientos?.labels ?? [], data.series?.top_procedimientos?.totals ?? [], '#6366f1');
        renderVerticalBar('chart-leakage-afiliacion', data.leakage?.por_afiliacion?.labels ?? [], data.leakage?.por_afiliacion?.totals ?? [], '#f97316');
        renderTable(data.leakage?.oldest ?? []);
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
            .then(data => {
                renderDashboard(data);
            })
            .catch(() => {
                setChartEmpty('chart-billing-dia', 'No se pudo cargar el dashboard.');
            });
    };

    const setupDatePicker = () => {
        if (typeof $ === 'undefined' || typeof $.fn.daterangepicker === 'undefined') {
            return;
        }
        const start = moment().subtract(89, 'days');
        const end = moment();

        $(elements.rangeInput).daterangepicker(
            {
                startDate: start,
                endDate: end,
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
                });
            }
        );
    };

    setupDatePicker();

    if (elements.refreshButton) {
        elements.refreshButton.addEventListener('click', () => {
            const value = elements.rangeInput.value || '';
            if (value.includes(' - ')) {
                const [from, to] = value.split(' - ');
                fetchDashboard({ date_from: from.trim(), date_to: to.trim() });
                return;
            }
            fetchDashboard();
        });
    }

    fetchDashboard();
})();
