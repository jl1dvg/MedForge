(() => {
    const charts = {};
    const endpoint = '/billing/dashboard-data';
    const procedimientosEndpoint = '/api/billing/kpis_procedimientos.php';
    const chartEmptyMessage = 'Sin datos suficientes para mostrar este gráfico.';

    const elements = {
        loaderOverlay: document.getElementById('billing-dashboard-loader'),
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
        procedimientosYear: document.getElementById('procedimientos-year'),
        procedimientosSede: document.getElementById('procedimientos-sede'),
        procedimientosCliente: document.getElementById('procedimientos-cliente'),
        procedimientosRefresh: document.getElementById('procedimientos-refresh'),
        procedimientosExport: document.getElementById('procedimientos-export'),
        procedimientosFiltersLabel: document.getElementById('procedimientos-filters-label'),
        procDetailCategory: document.getElementById('proc-detail-category'),
        procDetailRefresh: document.getElementById('proc-detail-refresh'),
        tableProcDetail: document.getElementById('table-proc-detail'),
        procTotalAnual: document.getElementById('proc-total-anual'),
        procYtd: document.getElementById('proc-ytd'),
        procRunRate: document.getElementById('proc-run-rate'),
        procBestMonth: document.getElementById('proc-best-month'),
        procWorstMonth: document.getElementById('proc-worst-month'),
        procMom: document.getElementById('proc-mom'),
        procTopCategory: document.getElementById('proc-top-category'),
        procTopCategorySubtext: document.getElementById('proc-top-category-subtext'),
        procCirugiaShare: document.getElementById('proc-cirugia-share'),
        tableProcSummary: document.getElementById('table-proc-summary'),
    };

    const currentProcFilters = { year: '', sede: '', tipoCliente: 'todos' };
    let loadingCounter = 0;

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

    const formatPercent = value => {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return '—';
        }
        return `${Number(value).toFixed(1)}%`;
    };

    const setMetricText = (node, value) => {
        if (node) {
            node.textContent = value;
        }
    };

    const setLoading = isLoading => {
        if (!elements.loaderOverlay) {
            return;
        }
        if (isLoading) {
            loadingCounter += 1;
        } else {
            loadingCounter = Math.max(0, loadingCounter - 1);
        }
        elements.loaderOverlay.classList.toggle('is-visible', loadingCounter > 0);
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

    const renderStackedBar = (chartId, labels, series) => {
        if (!labels.length || !series.length) {
            setChartEmpty(chartId, 'Sin datos para este periodo.');
            return;
        }
        renderChart(chartId, {
            chart: { type: 'bar', height: 320, stacked: true, toolbar: { show: false } },
            series,
            plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: labels },
            legend: { position: 'top' },
        });
    };

    const renderDonut = (chartId, labels, data) => {
        if (!labels.length) {
            setChartEmpty(chartId, 'Sin datos para este periodo.');
            return;
        }
        renderChart(chartId, {
            chart: { type: 'donut', height: 320 },
            labels,
            series: data,
            legend: { position: 'bottom' },
            dataLabels: { enabled: false },
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
            .map(row => `
                <tr>
                    <td>${row.form_id ?? '—'}</td>
                    <td>${row.paciente ?? '—'}</td>
                    <td>${row.afiliacion ?? '—'}</td>
                    <td>${row.dias_pendiente ?? '—'}</td>
                </tr>
            `)
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

    const renderProcedimientosTable = rows => {
        if (!elements.tableProcSummary) {
            return;
        }
        if (!rows || !rows.length) {
            elements.tableProcSummary.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin datos para este periodo</td></tr>';
            return;
        }

        elements.tableProcSummary.innerHTML = rows
            .map(row => `
                <tr>
                    <td>${row.category ?? '—'}</td>
                    <td>${formatNumber(row.count ?? 0)}</td>
                    <td>${formatCurrency(row.total ?? 0)}</td>
                    <td>${formatPercent(row.share ?? 0)}</td>
                    <td>${row.peak_month ?? '—'}</td>
                    <td>${formatCurrency(row.avg_monthly ?? 0)}</td>
                </tr>
            `)
            .join('');
    };

    const renderProcedimientosDetailTable = rows => {
        if (!elements.tableProcDetail) {
            return;
        }
        if (!rows || !rows.length) {
            elements.tableProcDetail.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Sin datos para este periodo</td></tr>';
            return;
        }

        elements.tableProcDetail.innerHTML = rows
            .map(row => `
                <tr>
                    <td>${row.fecha ?? '—'}</td>
                    <td>${row.form_id ?? '—'}</td>
                    <td>${row.paciente ?? '—'}</td>
                    <td>${row.afiliacion ?? '—'}</td>
                    <td>${row.tipo_cliente ?? '—'}</td>
                    <td>${row.categoria ?? '—'}</td>
                    <td>${row.codigo ?? '—'}</td>
                    <td>${row.detalle ?? '—'}</td>
                    <td>${formatCurrency(row.valor ?? 0)}</td>
                </tr>
            `)
            .join('');
    };

    const renderProcedimientosDashboard = payload => {
        if (!payload?.data) {
            return;
        }

        const { data } = payload;
        const labels = data.labels ?? [];
        const monthlyTotals = data.monthly_totals ?? [];
        const kpis = data.kpis ?? {};
        const hasData = (kpis.annual_total ?? 0) > 0;

        if (elements.procedimientosFiltersLabel) {
            const year = data.filters?.year ?? '—';
            const sedeLabel = elements.procedimientosSede?.value?.trim() || 'Todas las sedes';
            const clienteLabel = elements.procedimientosCliente?.selectedOptions?.[0]?.textContent ?? 'Todos los clientes';
            elements.procedimientosFiltersLabel.textContent = `${year} · ${sedeLabel} · ${clienteLabel}`;
        }

        if (!hasData) {
            setMetricText(elements.procTotalAnual, '—');
            setMetricText(elements.procYtd, '—');
            setMetricText(elements.procRunRate, '—');
            setMetricText(elements.procBestMonth, '—');
            setMetricText(elements.procWorstMonth, '—');
            setMetricText(elements.procMom, '—');
            setMetricText(elements.procTopCategory, '—');
            setMetricText(elements.procCirugiaShare, '—');
            setChartEmpty('chart-proc-line', 'Sin datos para este periodo.');
            setChartEmpty('chart-proc-stacked', 'Sin datos para este periodo.');
            setChartEmpty('chart-proc-donut', 'Sin datos para este periodo.');
            renderProcedimientosTable([]);
            if (elements.procTopCategorySubtext) {
                elements.procTopCategorySubtext.textContent = 'Participación anual';
            }
            return;
        }

        setMetricText(elements.procTotalAnual, formatCurrency(kpis.annual_total ?? 0));
        setMetricText(elements.procYtd, formatCurrency(kpis.ytd_total ?? 0));
        setMetricText(elements.procRunRate, formatCurrency(kpis.run_rate ?? 0));

        if (kpis.best_month) {
            setMetricText(elements.procBestMonth, `${kpis.best_month.label} · ${formatCurrency(kpis.best_month.total ?? 0)}`);
        } else {
            setMetricText(elements.procBestMonth, '—');
        }

        if (kpis.worst_month) {
            setMetricText(elements.procWorstMonth, `${kpis.worst_month.label} · ${formatCurrency(kpis.worst_month.total ?? 0)}`);
        } else {
            setMetricText(elements.procWorstMonth, '—');
        }

        setMetricText(elements.procMom, kpis.mom_growth === null ? '—' : formatPercent(kpis.mom_growth));

        if (kpis.top_category) {
            const top = kpis.top_category;
            setMetricText(elements.procTopCategory, `${top.category} · ${formatPercent(top.share ?? 0)}`);
        } else {
            setMetricText(elements.procTopCategory, '—');
        }

        if (elements.procTopCategorySubtext) {
            const topThree = kpis.top_three ?? [];
            if (topThree.length) {
                elements.procTopCategorySubtext.textContent = `Top 3: ${topThree
                    .map(item => `${item.category} ${formatPercent(item.share ?? 0)}`)
                    .join(' · ')}`;
            } else {
                elements.procTopCategorySubtext.textContent = 'Participación anual';
            }
        }

        setMetricText(elements.procCirugiaShare, formatPercent(kpis.cirugia_share ?? 0));

        renderLineChart('chart-proc-line', labels, monthlyTotals, 'Total mensual');

        const categorySeries = data.categories?.series ?? {};
        const stackedSeries = (data.categories?.order ?? Object.keys(categorySeries)).map(category => ({
            name: category,
            data: categorySeries[category] ?? [],
        }));
        renderStackedBar('chart-proc-stacked', labels, stackedSeries);

        const donutLabels = data.categories?.order ?? Object.keys(data.categories?.totals ?? {});
        const donutData = donutLabels.map(label => data.categories?.totals?.[label] ?? 0);
        renderDonut('chart-proc-donut', donutLabels, donutData);

        renderProcedimientosTable(data.summary_table ?? []);
    };

    const fetchDashboard = (filters = {}) => {
        setLoading(true);
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
            })
            .finally(() => {
                setLoading(false);
            });
    };

    const buildProcParams = (extra = {}) => {
        const params = new URLSearchParams({
            year: currentProcFilters.year,
            tipo_cliente: currentProcFilters.tipoCliente || 'todos',
            ...extra,
        });
        if (currentProcFilters.sede) {
            params.set('sede', currentProcFilters.sede);
        }
        if (elements.procDetailCategory?.value) {
            params.set('categoria', elements.procDetailCategory.value);
        }
        return params;
    };

    const fetchProcedimientosDetalle = () => {
        if (!currentProcFilters.year) {
            return Promise.resolve();
        }
        const params = buildProcParams({ mode: 'detail', limit: '600' });

        setLoading(true);
        return fetch(`${procedimientosEndpoint}?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data?.success) {
                    throw new Error('API error');
                }
                renderProcedimientosDetailTable(data.data?.rows ?? []);
            })
            .catch(() => {
                renderProcedimientosDetailTable([]);
            })
            .finally(() => {
                setLoading(false);
            });
    };

    const fetchProcedimientos = () => {
        if (!elements.procedimientosYear) {
            return Promise.resolve();
        }
        currentProcFilters.year = elements.procedimientosYear.value;
        currentProcFilters.sede = elements.procedimientosSede?.value?.trim() || '';
        currentProcFilters.tipoCliente = elements.procedimientosCliente?.value || 'todos';

        const params = buildProcParams();

        setLoading(true);
        return fetch(`${procedimientosEndpoint}?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data?.success) {
                    throw new Error('API error');
                }
                renderProcedimientosDashboard(data);
                fetchProcedimientosDetalle();
            })
            .catch(() => {
                setChartEmpty('chart-proc-line', 'Sin datos para este periodo.');
                setChartEmpty('chart-proc-stacked', 'Sin datos para este periodo.');
                setChartEmpty('chart-proc-donut', 'Sin datos para este periodo.');
                renderProcedimientosTable([]);
                renderProcedimientosDetailTable([]);
                setMetricText(elements.procTotalAnual, '—');
                setMetricText(elements.procYtd, '—');
                setMetricText(elements.procRunRate, '—');
                setMetricText(elements.procBestMonth, '—');
                setMetricText(elements.procWorstMonth, '—');
                setMetricText(elements.procMom, '—');
                setMetricText(elements.procTopCategory, '—');
                setMetricText(elements.procCirugiaShare, '—');
            })
            .finally(() => {
                setLoading(false);
            });
    };

    const exportProcedimientosDetalle = () => {
        if (!currentProcFilters.year) {
            return;
        }
        const params = buildProcParams({ mode: 'detail', export: 'csv', limit: '5000' });
        window.open(`${procedimientosEndpoint}?${params.toString()}`, '_blank');
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

    if (elements.procedimientosRefresh) {
        elements.procedimientosRefresh.addEventListener('click', fetchProcedimientos);
    }

    if (elements.procDetailRefresh) {
        elements.procDetailRefresh.addEventListener('click', fetchProcedimientosDetalle);
    }

    if (elements.procedimientosExport) {
        elements.procedimientosExport.addEventListener('click', exportProcedimientosDetalle);
    }

    fetchDashboard();
    fetchProcedimientos();
})();
