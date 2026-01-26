(() => {
    const charts = {};
    const endpoint = '/solicitudes/dashboard-data';
    const chartEmptyMessage = 'Sin datos suficientes para mostrar este gráfico.';

    const elements = {
        rangeLabel: document.getElementById('dashboard-range'),
        rangeInput: document.getElementById('dashboard-range-input'),
        refreshButton: document.getElementById('dashboard-refresh'),
        metricTotal: document.getElementById('metric-total'),
        metricCompleted: document.getElementById('metric-completed'),
        metricProgress: document.getElementById('metric-progress'),
        metricMailsSent: document.getElementById('metric-mails-sent'),
        metricMailsFailed: document.getElementById('metric-mails-failed'),
        metricAttachments: document.getElementById('metric-attachments'),
    };

    if (!elements.rangeInput) {
        return;
    }

    const formatNumber = value => new Intl.NumberFormat('es-EC').format(value ?? 0);

    const formatPercent = value => `${(value ?? 0).toFixed(1)}%`;

    const formatBytes = value => {
        if (!value || Number.isNaN(value)) {
            return '—';
        }
        const size = Number(value);
        if (size < 1024) {
            return `${size.toFixed(0)} B`;
        }
        if (size < 1024 * 1024) {
            return `${(size / 1024).toFixed(1)} KB`;
        }
        return `${(size / (1024 * 1024)).toFixed(2)} MB`;
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
            series: [{ name: title || 'Solicitudes', data }],
            xaxis: { categories: labels },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.15 } },
            colors: ['#6366f1'],
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

    const renderDonut = (chartId, labels, data, colors) => {
        if (!labels.length) {
            setChartEmpty(chartId);
            return;
        }
        renderChart(chartId, {
            chart: { type: 'donut', height: 300 },
            labels,
            series: data,
            legend: { position: 'bottom' },
            colors,
            dataLabels: { enabled: true },
        });
    };

    const renderRadial = (chartId, value) => {
        renderChart(chartId, {
            chart: { type: 'radialBar', height: 280 },
            series: [value],
            labels: ['Progreso'],
            colors: ['#22c55e'],
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

    const toSeries = dataObj => {
        const labels = [];
        const totals = [];
        Object.values(dataObj || {}).forEach(item => {
            labels.push(item.label ?? 'Sin etiqueta');
            totals.push(item.total ?? 0);
        });
        return { labels, totals };
    };

    const renderDashboard = payload => {
        if (!payload?.data) {
            return;
        }
        const { data, filters } = payload;

        if (elements.rangeLabel && filters) {
            elements.rangeLabel.textContent = `${filters.date_from ?? '—'} a ${filters.date_to ?? '—'}`;
        }

        setMetricText(elements.metricTotal, formatNumber(data.kanban?.total ?? 0));
        setMetricText(elements.metricCompleted, formatNumber(data.kanban?.completed ?? 0));
        setMetricText(elements.metricProgress, formatPercent(data.kanban?.avg_progress ?? 0));
        setMetricText(elements.metricMailsSent, formatNumber(data.cobertura?.status?.sent ?? 0));
        setMetricText(elements.metricMailsFailed, formatNumber(data.cobertura?.status?.failed ?? 0));
        setMetricText(elements.metricAttachments, formatBytes(data.cobertura?.attachments?.avg_size));

        renderLineChart('chart-solicitudes-mes', data.volumen?.por_mes?.labels ?? [], data.volumen?.por_mes?.totals ?? [], 'Solicitudes');
        renderHorizontalBar('chart-procedimientos', data.volumen?.por_procedimiento?.labels ?? [], data.volumen?.por_procedimiento?.totals ?? [], '#6366f1');
        renderHorizontalBar('chart-doctor', data.volumen?.por_doctor?.labels ?? [], data.volumen?.por_doctor?.totals ?? [], '#0ea5e9');
        renderDonut('chart-afiliacion', data.volumen?.por_afiliacion?.labels ?? [], data.volumen?.por_afiliacion?.totals ?? [], ['#38bdf8', '#22c55e', '#f97316', '#a855f7', '#ef4444']);
        renderDonut('chart-prioridad', data.volumen?.por_prioridad?.labels ?? [], data.volumen?.por_prioridad?.totals ?? [], ['#f97316', '#22c55e', '#e11d48', '#64748b']);

        const wipData = toSeries(data.kanban?.wip ?? {});
        renderHorizontalBar('chart-wip', wipData.labels, wipData.totals, '#14b8a6');

        renderRadial('chart-progress', data.kanban?.avg_progress ?? 0);

        const buckets = data.kanban?.progress_buckets ?? {};
        renderVerticalBar('chart-progress-buckets', Object.keys(buckets), Object.values(buckets), '#6366f1');

        const nextStages = toSeries(data.kanban?.next_stages ?? {});
        renderVerticalBar('chart-next-stages', nextStages.labels, nextStages.totals, '#f97316');

        renderDonut('chart-mail-status', ['Enviados', 'Fallidos'], [data.cobertura?.status?.sent ?? 0, data.cobertura?.status?.failed ?? 0], ['#22c55e', '#ef4444']);
        renderVerticalBar('chart-mail-templates', data.cobertura?.templates?.labels ?? [], data.cobertura?.templates?.totals ?? [], '#3b82f6');
        renderVerticalBar('chart-mail-users', data.cobertura?.users?.labels ?? [], data.cobertura?.users?.totals ?? [], '#a855f7');
    };

    const fetchDashboard = (filters = {}) => {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filters),
        })
            .then(response => response.json())
            .then(data => {
                renderDashboard(data);
            })
            .catch(() => {
                setChartEmpty('chart-solicitudes-mes', 'No se pudo cargar el dashboard.');
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
