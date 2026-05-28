import Chart from 'chart.js/auto';

const root = document.querySelector('[data-imagenes-v3-dashboard]');

if (root) {
    const initialPayload = JSON.parse(root.querySelector('[data-initial-payload]')?.textContent || '{}');
    const endpoints = JSON.parse(root.dataset.endpoints || '{}');
    const state = {
        payload: initialPayload,
        funnelChart: null,
        moneyChart: null,
    };

    const money = new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    });
    const number = new Intl.NumberFormat('es-EC');

    const formatValue = (value, type = 'number') => {
        const numeric = Number(value || 0);

        return type === 'money' ? money.format(numeric) : number.format(numeric);
    };

    const setText = (selector, value) => {
        const node = root.querySelector(selector);
        if (node) {
            node.textContent = value;
        }
    };

    const card = (key) => root.querySelector(`[data-kpi="${key}"]`);

    const setLoading = (loading) => {
        root.classList.toggle('is-loading', loading);
        root.querySelectorAll('[data-refresh-button]').forEach((button) => {
            button.disabled = loading;
        });
    };

    const renderCards = (payload) => {
        const executive = payload.executive || {};
        const requests = payload.solicitudes || {};
        const operation = payload.operacion || {};
        const billing = payload.billing || {};

        const values = {
            facturado_real: [executive.facturado_real, 'money'],
            honorario_real: [executive.honorario_real, 'money'],
            pendiente_facturar: [executive.pendiente_de_facturar, 'number'],
            pendiente_cobrar: [executive.pendiente_de_cobrar, 'number'],
            perdida_estimada: [executive.perdida_estimada, 'money'],
            oportunidad_recuperacion: [executive.oportunidad_recuperacion, 'money'],
            solicitudes_recibidas: [requests.solicitudes_recibidas, 'number'],
            solicitudes_sin_agenda: [requests.solicitudes_sin_agenda, 'number'],
            solicitudes_realizadas_corte: [requests.solicitudes_realizadas_al_corte, 'number'],
            agendas_periodo: [operation.agendas_periodo, 'number'],
            atendidas: [operation.atendidas, 'number'],
            no_atendidas: [operation.no_atendidas, 'number'],
            sin_cierre: [operation.sin_cierre_operativo, 'number'],
            nas: [operation.con_archivos_nas, 'number'],
            informes: [operation.con_informe, 'number'],
            pendientes_informar: [operation.pendientes_informar, 'number'],
            billing_real: [billing.estudios_con_billing_real, 'number'],
            realizados_sin_billing: [billing.realizados_sin_billing_real, 'number'],
        };

        Object.entries(values).forEach(([key, [value, type]]) => {
            const node = card(key);
            if (node) {
                node.textContent = formatValue(value, type);
            }
        });

        setText('[data-generated-at]', payload.meta?.generated_at || '');
        setText('[data-summary-mode]', payload.meta?.summary_mode ? 'Modo resumen: detalle desactivado para proteger el servidor.' : 'Modo interactivo: detalle paginado disponible.');
    };

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => `${context.label}: ${formatValue(context.raw)}`,
                },
            },
        },
    };

    const renderCharts = (payload) => {
        const funnelCanvas = root.querySelector('[data-chart="funnel"]');
        const moneyCanvas = root.querySelector('[data-chart="money"]');
        const funnel = payload.charts?.funnel || { labels: [], values: [] };
        const moneyData = payload.charts?.money || { labels: [], values: [] };

        if (funnelCanvas) {
            state.funnelChart?.destroy();
            state.funnelChart = new Chart(funnelCanvas, {
                type: 'bar',
                data: {
                    labels: funnel.labels,
                    datasets: [{
                        data: funnel.values,
                        backgroundColor: ['#2563eb', '#0891b2', '#16a34a', '#15803d'],
                        borderRadius: 4,
                    }],
                },
                options: chartOptions,
            });
        }

        if (moneyCanvas) {
            state.moneyChart?.destroy();
            state.moneyChart = new Chart(moneyCanvas, {
                type: 'bar',
                data: {
                    labels: moneyData.labels,
                    datasets: [{
                        data: moneyData.values,
                        backgroundColor: ['#16a34a', '#d97706', '#dc2626'],
                        borderRadius: 4,
                    }],
                },
                options: {
                    ...chartOptions,
                    plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label}: ${formatValue(context.raw, 'money')}`,
                            },
                        },
                    },
                },
            });
        }
    };

    const renderTopList = (selector, rows, labelKey) => {
        const node = root.querySelector(selector);
        if (!node) {
            return;
        }

        node.innerHTML = '';
        if (!Array.isArray(rows) || rows.length === 0) {
            node.innerHTML = '<li class="mf-v3-empty">Sin datos para el rango.</li>';
            return;
        }

        rows.forEach((row) => {
            const item = document.createElement('li');
            item.innerHTML = `<span>${row[labelKey] || 'Sin dato'}</span><strong>${formatValue(row.total)}</strong>`;
            node.appendChild(item);
        });
    };

    const renderTops = (payload) => {
        const tops = payload.oportunidad?.tops || {};
        renderTopList('[data-top="examenes"]', tops.examenes, 'examen');
        renderTopList('[data-top="sedes"]', tops.sedes, 'sede');
        renderTopList('[data-top="seguros"]', tops.seguros, 'seguro');
        renderTopList('[data-top="doctores"]', tops.doctores_solicitantes, 'doctor');
        renderTopList('[data-top="causas"]', tops.causas_perdida, 'causa');
    };

    const renderDetail = async (payload) => {
        const table = root.querySelector('[data-detail-table]');
        if (!table || !endpoints.detail) {
            return;
        }

        const params = new URLSearchParams(new FormData(root.querySelector('[data-filter-form]')));
        const response = await fetch(`${endpoints.detail}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const detail = await response.json();
        const body = table.querySelector('tbody');
        body.innerHTML = '';

        if (detail.message) {
            body.innerHTML = `<tr><td colspan="8">${detail.message}</td></tr>`;
            return;
        }
        if (!detail.rows || detail.rows.length === 0) {
            body.innerHTML = '<tr><td colspan="8">Sin detalle para el rango.</td></tr>';
            return;
        }

        detail.rows.forEach((row) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${row.fecha || ''}</td>
                <td>${row.form_id || ''}</td>
                <td>${row.hc_number || ''}</td>
                <td>${row.procedimiento_proyectado || ''}</td>
                <td>${row.sede || ''}</td>
                <td>${row.estado_realizacion || ''}</td>
                <td>${row.estado_facturacion || ''}</td>
                <td class="text-right">${formatValue(row.monto_facturado_real, 'money')}</td>
            `;
            body.appendChild(tr);
        });

        setText('[data-detail-count]', `${formatValue(detail.rows.length)} de ${formatValue(detail.total)} registros`);
    };

    const render = async (payload) => {
        state.payload = payload;
        renderCards(payload);
        renderCharts(payload);
        renderTops(payload);
        await renderDetail(payload);
    };

    root.querySelector('[data-filter-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setLoading(true);
        try {
            const params = new URLSearchParams(new FormData(event.currentTarget));
            const response = await fetch(`${endpoints.data}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            await render(await response.json());
        } finally {
            setLoading(false);
        }
    });

    root.querySelector('[data-export-link]')?.addEventListener('click', (event) => {
        const form = root.querySelector('[data-filter-form]');
        if (!form || !endpoints.export) {
            return;
        }
        event.currentTarget.href = `${endpoints.export}?${new URLSearchParams(new FormData(form)).toString()}`;
    });

    render(initialPayload);
}
