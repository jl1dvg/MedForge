(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('whatsapp-kpi-root');
        if (!root) {
            return;
        }

        var endpointKpis = root.getAttribute('data-endpoint-kpis') || '/whatsapp/api/kpis';
        var endpointDrilldown = root.getAttribute('data-endpoint-drilldown') || '/whatsapp/api/kpis/drilldown';

        var charts = {};
        var state = {
            dateFrom: null,
            dateTo: null,
            roleId: '',
            agentId: '',
            data: null,
            optionsCatalog: {
                roles: {},
                agents: {}
            },
            drilldown: {
                metric: '',
                page: 1,
                limit: 50,
                totalPages: 0
            }
        };

        var rangeInput = document.getElementById('wa-kpi-range');
        var roleSelect = document.getElementById('wa-kpi-role');
        var agentSelect = document.getElementById('wa-kpi-agent');
        var refreshButton = document.getElementById('wa-kpi-refresh');
        var cardNodes = root.querySelectorAll('[data-kpi-card]');

        var drilldownModalNode = document.getElementById('waKpiDrilldownModal');
        var drilldownTitle = document.getElementById('wa-kpi-drilldown-title');
        var drilldownMeta = document.getElementById('wa-kpi-drilldown-meta');
        var drilldownTable = document.getElementById('wa-kpi-drilldown-table');
        var drilldownPrevButton = document.getElementById('wa-kpi-drilldown-prev');
        var drilldownNextButton = document.getElementById('wa-kpi-drilldown-next');

        var drilldownModal = null;
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function' && drilldownModalNode) {
            drilldownModal = new window.bootstrap.Modal(drilldownModalNode);
        }

        function toIsoDate(date) {
            if (!(date instanceof Date)) {
                return null;
            }
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function parseDate(value) {
            if (typeof value !== 'string' || value.length !== 10) {
                return null;
            }
            var parts = value.split('-');
            if (parts.length !== 3) {
                return null;
            }
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1;
            var day = parseInt(parts[2], 10);
            if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
                return null;
            }
            var date = new Date(year, month, day);
            if (isNaN(date.getTime())) {
                return null;
            }
            return date;
        }

        function formatNumber(value) {
            var number = Number(value || 0);
            if (!Number.isFinite(number)) {
                return '0';
            }
            return new Intl.NumberFormat('es-EC').format(number);
        }

        function formatDecimal(value, digits) {
            var number = Number(value);
            if (!Number.isFinite(number)) {
                return '—';
            }
            return number.toFixed(typeof digits === 'number' ? digits : 2);
        }

        function formatPercent(value) {
            var number = Number(value);
            if (!Number.isFinite(number)) {
                return '0%';
            }
            return number.toFixed(2).replace(/\.00$/, '') + '%';
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function setText(selector, value) {
            var node = root.querySelector('[data-kpi-value="' + selector + '"]');
            if (node) {
                node.textContent = value;
            }
        }

        function setSubText(selector, value) {
            var node = root.querySelector('[data-kpi-sub="' + selector + '"]');
            if (node) {
                node.textContent = value;
            }
        }

        function ensureChartDestroyed(id) {
            if (charts[id] && typeof charts[id].destroy === 'function') {
                charts[id].destroy();
            }
            delete charts[id];
        }

        function renderEmptyChart(id, message) {
            var node = document.getElementById(id);
            if (!node) {
                return;
            }
            ensureChartDestroyed(id);
            node.innerHTML = '<div class="wa-kpi-empty">' + (message || 'Sin datos para graficar') + '</div>';
        }

        function renderChart(id, options) {
            var node = document.getElementById(id);
            if (!node || typeof window.ApexCharts === 'undefined') {
                return;
            }
            ensureChartDestroyed(id);
            node.innerHTML = '';
            charts[id] = new window.ApexCharts(node, options);
            charts[id].render();
        }

        function buildQuery(params) {
            var query = new URLSearchParams();
            Object.keys(params).forEach(function (key) {
                var value = params[key];
                if (value === null || value === undefined || value === '') {
                    return;
                }
                query.set(key, String(value));
            });
            return query.toString();
        }

        function mapStatusLabel(status) {
            var normalized = String(status || '').toLowerCase();
            if (normalized === 'sent') {
                return 'Enviado';
            }
            if (normalized === 'delivered') {
                return 'Entregado';
            }
            if (normalized === 'read') {
                return 'Leído';
            }
            if (normalized === 'failed') {
                return 'Fallido';
            }
            if (normalized === 'unknown') {
                return 'Sin estado';
            }
            return normalized;
        }

        function badgeForBoolean(value) {
            if (String(value) === '1') {
                return '<span class="badge bg-success-light text-success">Sí</span>';
            }
            if (String(value) === '0') {
                return '<span class="badge bg-danger-light text-danger">No</span>';
            }
            return String(value == null ? '' : value);
        }

        function mergeOptionCatalog(options) {
            if (!options) {
                return;
            }

            var roles = Array.isArray(options.roles) ? options.roles : [];
            var agents = Array.isArray(options.agents) ? options.agents : [];

            roles.forEach(function (role) {
                var id = Number(role.id || 0);
                if (!id) {
                    return;
                }
                state.optionsCatalog.roles[id] = {
                    id: id,
                    name: role.name || ('Rol #' + id)
                };
            });

            agents.forEach(function (agent) {
                var id = Number(agent.id || 0);
                if (!id) {
                    return;
                }
                state.optionsCatalog.agents[id] = {
                    id: id,
                    name: agent.name || ('Agente #' + id)
                };
            });
        }

        function renderFilterSelects() {
            if (roleSelect) {
                var roleCurrent = state.roleId || '';
                roleSelect.innerHTML = '<option value="">Todos</option>';
                Object.keys(state.optionsCatalog.roles)
                    .map(function (key) { return Number(key); })
                    .sort(function (a, b) { return a - b; })
                    .forEach(function (id) {
                        var item = state.optionsCatalog.roles[id];
                        if (!item) {
                            return;
                        }
                        var option = document.createElement('option');
                        option.value = String(item.id);
                        option.textContent = item.name;
                        roleSelect.appendChild(option);
                    });
                roleSelect.value = roleCurrent;
            }

            if (agentSelect) {
                var agentCurrent = state.agentId || '';
                agentSelect.innerHTML = '<option value="">Todos</option>';
                Object.keys(state.optionsCatalog.agents)
                    .map(function (key) { return Number(key); })
                    .sort(function (a, b) { return a - b; })
                    .forEach(function (id) {
                        var item = state.optionsCatalog.agents[id];
                        if (!item) {
                            return;
                        }
                        var option = document.createElement('option');
                        option.value = String(item.id);
                        option.textContent = item.name;
                        agentSelect.appendChild(option);
                    });
                agentSelect.value = agentCurrent;
            }
        }

        function renderSummary(summary) {
            if (!summary) {
                return;
            }

            setText('conversations_new', formatNumber(summary.conversations_new));
            setText('contacts_active', formatNumber(summary.contacts_active));
            setText('messages_inbound', formatNumber(summary.messages_inbound));
            setText('messages_outbound', formatNumber(summary.messages_outbound));
            setText('handoffs_total', formatNumber(summary.handoffs_total));
            setText('handoff_rate', formatPercent(summary.handoff_rate));
            setText('autoservice_rate', formatPercent(summary.autoservice_rate));
            setText('fallback_rate', formatPercent(summary.fallback_rate));
            setText('live_queue_total', formatNumber(summary.live_queue_total));
            setText('handoff_transfers', formatNumber(summary.handoff_transfers));
            setText('reopened_24h', formatNumber(summary.reopened_24h));
            setText('reopened_72h', formatNumber(summary.reopened_72h));

            setText('avg_first_response_minutes', summary.avg_first_response_minutes == null ? '—' : formatDecimal(summary.avg_first_response_minutes, 1));
            setText('avg_handoff_assignment_minutes', summary.avg_handoff_assignment_minutes == null ? '—' : formatDecimal(summary.avg_handoff_assignment_minutes, 1));
            setText('sla_assignments_rate', formatPercent(summary.sla_assignments_rate));

            setSubText(
                'sla_assignments_rate',
                formatNumber(summary.sla_assignments_in_target) + '/' + formatNumber(summary.sla_assignments_total) + ' en <= ' + formatNumber(summary.sla_target_minutes) + ' min'
            );

            setSubText(
                'live_queue_total',
                'En cola ' + formatNumber(summary.live_queue_queued) + ' · Asignadas ' + formatNumber(summary.live_queue_assigned) + ' · Vencidas ' + formatNumber(summary.live_queue_assigned_overdue)
            );

            setSubText(
                'handoff_rate',
                formatNumber(summary.handoff_conversations) + ' de ' + formatNumber(summary.inbound_conversations) + ' conversaciones inbound'
            );

            setSubText(
                'autoservice_rate',
                formatNumber(summary.autoservice_conversations) + ' de ' + formatNumber(summary.inbound_conversations) + ' conversaciones inbound'
            );

            setSubText(
                'fallback_rate',
                formatNumber(summary.fallback_messages) + ' de ' + formatNumber(summary.messages_inbound) + ' mensajes inbound'
            );

            setSubText(
                'reopened_24h',
                formatPercent(summary.reopen_rate_24h) + ' de ' + formatNumber(summary.resolved_for_reopen) + ' resueltos'
            );

            setSubText(
                'reopened_72h',
                formatPercent(summary.reopen_rate_72h) + ' de ' + formatNumber(summary.resolved_for_reopen) + ' resueltos'
            );
        }

        function renderStatusChart(data) {
            var list = data && data.breakdowns && Array.isArray(data.breakdowns.outbound_status)
                ? data.breakdowns.outbound_status
                : [];

            if (!list.length) {
                renderEmptyChart('wa-kpi-chart-status', 'Sin estados outbound.');
                return;
            }

            var labels = list.map(function (item) { return mapStatusLabel(item.status); });
            var series = list.map(function (item) { return Number(item.total || 0); });

            renderChart('wa-kpi-chart-status', {
                chart: { type: 'donut', height: 300 },
                labels: labels,
                series: series,
                legend: { position: 'bottom' },
                dataLabels: { enabled: true },
                colors: ['#1d4ed8', '#0ea5e9', '#16a34a', '#dc2626', '#64748b']
            });
        }

        function renderVolumeChart(data) {
            var labels = data && data.trends ? (data.trends.labels || []) : [];
            if (!labels.length) {
                renderEmptyChart('wa-kpi-chart-volume', 'Sin actividad para el periodo seleccionado.');
                return;
            }

            renderChart('wa-kpi-chart-volume', {
                chart: {
                    type: 'area',
                    height: 320,
                    toolbar: { show: false }
                },
                colors: ['#0284c7', '#16a34a'],
                series: [
                    { name: 'Inbound', data: data.trends.messages_inbound || [] },
                    { name: 'Outbound', data: data.trends.messages_outbound || [] }
                ],
                xaxis: { categories: labels },
                stroke: { curve: 'smooth', width: 3 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 0.3,
                        opacityFrom: 0.4,
                        opacityTo: 0.1
                    }
                },
                dataLabels: { enabled: false }
            });
        }

        function renderHandoffChart(data) {
            var labels = data && data.trends ? (data.trends.labels || []) : [];
            if (!labels.length) {
                renderEmptyChart('wa-kpi-chart-handoffs', 'Sin handoffs en el periodo.');
                return;
            }

            renderChart('wa-kpi-chart-handoffs', {
                chart: {
                    type: 'bar',
                    height: 320,
                    stacked: false,
                    toolbar: { show: false }
                },
                colors: ['#f59e0b', '#22c55e', '#6366f1'],
                series: [
                    { name: 'En cola', data: data.trends.handoffs_queued || [] },
                    { name: 'Resueltos', data: data.trends.handoffs_resolved || [] },
                    { name: 'Transferencias', data: data.trends.handoff_transfers || [] }
                ],
                xaxis: { categories: labels },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
                dataLabels: { enabled: false }
            });
        }

        function renderRolesChart(data) {
            var rows = data && data.breakdowns && Array.isArray(data.breakdowns.handoffs_by_role)
                ? data.breakdowns.handoffs_by_role
                : [];

            if (!rows.length) {
                renderEmptyChart('wa-kpi-chart-roles', 'Sin datos de equipos.');
                return;
            }

            renderChart('wa-kpi-chart-roles', {
                chart: {
                    type: 'bar',
                    height: 320,
                    toolbar: { show: false }
                },
                colors: ['#0ea5e9'],
                series: [{
                    name: 'Handoffs',
                    data: rows.map(function (row) { return Number(row.total || 0); })
                }],
                xaxis: {
                    categories: rows.map(function (row) { return row.role_name || 'Sin rol'; })
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: '58%'
                    }
                },
                dataLabels: { enabled: false }
            });
        }

        function renderTopMenuChart(data) {
            var rows = data && data.breakdowns && Array.isArray(data.breakdowns.top_menu_options)
                ? data.breakdowns.top_menu_options
                : [];

            if (!rows.length) {
                renderEmptyChart('wa-kpi-chart-menu', 'Sin selecciones de menú en el periodo.');
                return;
            }

            var labels = rows.map(function (row) { return row.option_label || '[Sin opción]'; });
            var totals = rows.map(function (row) { return Number(row.total || 0); });

            renderChart('wa-kpi-chart-menu', {
                chart: {
                    type: 'bar',
                    height: 320,
                    toolbar: { show: false }
                },
                colors: ['#2563eb'],
                series: [{
                    name: 'Selecciones',
                    data: totals
                }],
                xaxis: {
                    categories: labels
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: '56%'
                    }
                },
                dataLabels: { enabled: false }
            });
        }

        function renderAgentTable(data) {
            var table = document.getElementById('wa-kpi-agent-table');
            if (!table) {
                return;
            }

            var tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }

            var rows = data && data.breakdowns && Array.isArray(data.breakdowns.handoffs_by_agent)
                ? data.breakdowns.handoffs_by_agent
                : [];

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin actividad de agentes en el periodo.</td></tr>';
                return;
            }

            var html = rows.map(function (row) {
                var assignmentMinutes = row.avg_assignment_minutes == null ? '—' : formatDecimal(row.avg_assignment_minutes, 1) + ' min';
                var resolutionMinutes = row.avg_resolution_minutes == null ? '—' : formatDecimal(row.avg_resolution_minutes, 1) + ' min';

                return '<tr>' +
                    '<td>' + escapeHtml(row.agent_name || ('Agente #' + row.user_id)) + '</td>' +
                    '<td>' + formatNumber(row.assigned_count) + '</td>' +
                    '<td><span class="wa-kpi-badge bg-warning-light text-warning">' + formatNumber(row.active_count) + '</span></td>' +
                    '<td>' + formatNumber(row.resolved_count) + '</td>' +
                    '<td>' + formatPercent(row.resolution_rate) + '</td>' +
                    '<td>' + assignmentMinutes + '</td>' +
                    '<td>' + resolutionMinutes + '</td>' +
                    '</tr>';
            }).join('');

            tbody.innerHTML = html;
        }

        function renderAll(data) {
            if (!data) {
                return;
            }

            state.data = data;
            mergeOptionCatalog(data.options || null);
            renderFilterSelects();
            renderSummary(data.summary || {});
            renderVolumeChart(data);
            renderStatusChart(data);
            renderHandoffChart(data);
            renderRolesChart(data);
            renderTopMenuChart(data);
            renderAgentTable(data);
        }

        function buildKpiParams() {
            return {
                date_from: state.dateFrom,
                date_to: state.dateTo,
                role_id: state.roleId || null,
                agent_id: state.agentId || null
            };
        }

        function fetchKpis() {
            var query = buildQuery(buildKpiParams());
            var url = endpointKpis + (query ? ('?' + query) : '');

            if (refreshButton) {
                refreshButton.disabled = true;
            }

            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        throw new Error(payload && payload.error ? payload.error : 'No se pudieron cargar los KPIs.');
                    }
                    return payload.data;
                });
            }).then(function (data) {
                renderAll(data);
            }).catch(function (error) {
                console.error(error);
                renderEmptyChart('wa-kpi-chart-volume', error.message || 'No se pudieron cargar los KPIs.');
            }).finally(function () {
                if (refreshButton) {
                    refreshButton.disabled = false;
                }
            });
        }

        function renderDrilldownTable(columns, rows) {
            if (!drilldownTable) {
                return;
            }

            var thead = drilldownTable.querySelector('thead');
            var tbody = drilldownTable.querySelector('tbody');
            if (!thead || !tbody) {
                return;
            }

            if (!Array.isArray(columns) || !columns.length) {
                thead.innerHTML = '';
                tbody.innerHTML = '<tr><td class="text-center text-muted py-3">Sin columnas disponibles.</td></tr>';
                return;
            }

            thead.innerHTML = '<tr>' + columns.map(function (column) {
                return '<th>' + escapeHtml(column.label || column.key || '') + '</th>';
            }).join('') + '</tr>';

            if (!Array.isArray(rows) || !rows.length) {
                tbody.innerHTML = '<tr><td colspan="' + columns.length + '" class="text-center text-muted py-3">Sin resultados para este KPI.</td></tr>';
                return;
            }

            var html = rows.map(function (row) {
                var cells = columns.map(function (column) {
                    var key = column.key;
                    var value = row && Object.prototype.hasOwnProperty.call(row, key) ? row[key] : '';
                    if (key === 'within_sla') {
                        return '<td>' + badgeForBoolean(value) + '</td>';
                    }
                    if (key === 'share_percent') {
                        return '<td>' + formatPercent(value) + '</td>';
                    }
                    if (value === null || value === undefined) {
                        return '<td>—</td>';
                    }
                    return '<td>' + escapeHtml(value) + '</td>';
                }).join('');
                return '<tr>' + cells + '</tr>';
            }).join('');

            tbody.innerHTML = html;
        }

        function metricLabel(metric) {
            var labels = {
                conversations_new: 'Conversaciones nuevas',
                contacts_active: 'Contactos activos',
                messages_inbound: 'Mensajes inbound',
                messages_outbound: 'Mensajes outbound',
                messages_total: 'Mensajes totales',
                handoffs_total: 'Handoffs totales',
                handoffs_queued: 'Handoffs en cola',
                handoffs_assigned: 'Handoffs asignados',
                handoffs_resolved: 'Handoffs resueltos',
                handoffs_expired: 'Handoffs vencidos',
                avg_first_response: 'Tiempo de primera respuesta',
                avg_handoff_assignment: 'Tiempo de asignación de handoff',
                sla_assignments: 'Cumplimiento SLA de asignación',
                live_queue: 'Cola activa',
                handoff_transfers: 'Transferencias',
                reopened_24h: 'Reaperturas 24h',
                reopened_72h: 'Reaperturas 72h',
                handoff_rate: 'Tasa de handoff',
                autoservice_rate: 'Tasa de autoservicio',
                fallback_rate: 'Tasa de fallback',
                top_menu_options: 'Top opciones de menú'
            };
            return labels[metric] || metric;
        }

        function fetchDrilldown(metric) {
            state.drilldown.metric = metric;

            var params = buildKpiParams();
            params.metric = metric;
            params.page = state.drilldown.page;
            params.limit = state.drilldown.limit;

            var query = buildQuery(params);
            var url = endpointDrilldown + (query ? ('?' + query) : '');

            if (drilldownTitle) {
                drilldownTitle.textContent = 'Detalle: ' + metricLabel(metric);
            }
            if (drilldownMeta) {
                drilldownMeta.textContent = 'Cargando...';
            }

            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        throw new Error(payload && payload.error ? payload.error : 'No se pudo cargar el drill-down.');
                    }
                    return payload.data;
                });
            }).then(function (data) {
                renderDrilldownTable(data.columns || [], data.rows || []);

                state.drilldown.totalPages = Number(data.total_pages || 0);
                state.drilldown.page = Number(data.page || 1);

                var start = ((state.drilldown.page - 1) * state.drilldown.limit) + 1;
                var end = start + (Array.isArray(data.rows) ? data.rows.length : 0) - 1;
                if (!data.total || end < start) {
                    start = 0;
                    end = 0;
                }

                if (drilldownMeta) {
                    drilldownMeta.textContent = 'Mostrando ' + start + ' - ' + end + ' de ' + formatNumber(data.total || 0);
                }

                if (drilldownPrevButton) {
                    drilldownPrevButton.disabled = state.drilldown.page <= 1;
                }
                if (drilldownNextButton) {
                    drilldownNextButton.disabled = state.drilldown.page >= state.drilldown.totalPages;
                }
            }).catch(function (error) {
                console.error(error);
                if (drilldownMeta) {
                    drilldownMeta.textContent = error.message || 'No se pudo cargar el detalle.';
                }
                renderDrilldownTable([], []);
            });
        }

        function openDrilldown(metric) {
            state.drilldown.metric = metric;
            state.drilldown.page = 1;
            state.drilldown.totalPages = 0;

            if (drilldownModal) {
                drilldownModal.show();
            }

            fetchDrilldown(metric);
        }

        function wireEvents() {
            if (refreshButton) {
                refreshButton.addEventListener('click', function () {
                    fetchKpis();
                });
            }

            if (roleSelect) {
                roleSelect.addEventListener('change', function () {
                    state.roleId = roleSelect.value || '';
                    fetchKpis();
                });
            }

            if (agentSelect) {
                agentSelect.addEventListener('change', function () {
                    state.agentId = agentSelect.value || '';
                    fetchKpis();
                });
            }

            cardNodes.forEach(function (node) {
                node.addEventListener('click', function () {
                    var metric = node.getAttribute('data-kpi-card');
                    if (!metric) {
                        return;
                    }
                    openDrilldown(metric);
                });
            });

            if (drilldownPrevButton) {
                drilldownPrevButton.addEventListener('click', function () {
                    if (!state.drilldown.metric || state.drilldown.page <= 1) {
                        return;
                    }
                    state.drilldown.page -= 1;
                    fetchDrilldown(state.drilldown.metric);
                });
            }

            if (drilldownNextButton) {
                drilldownNextButton.addEventListener('click', function () {
                    if (!state.drilldown.metric || state.drilldown.page >= state.drilldown.totalPages) {
                        return;
                    }
                    state.drilldown.page += 1;
                    fetchDrilldown(state.drilldown.metric);
                });
            }
        }

        function setupDateRange() {
            var now = new Date();
            var start = new Date(now.getTime());
            start.setDate(start.getDate() - 29);

            state.dateFrom = toIsoDate(start);
            state.dateTo = toIsoDate(now);

            if (!rangeInput) {
                return;
            }

            rangeInput.value = state.dateFrom + ' - ' + state.dateTo;

            if (typeof window.$ === 'undefined' || !window.$.fn || typeof window.$.fn.daterangepicker === 'undefined') {
                rangeInput.addEventListener('change', function () {
                    var value = String(rangeInput.value || '');
                    if (value.indexOf(' - ') === -1) {
                        return;
                    }
                    var parts = value.split(' - ');
                    if (parts.length !== 2) {
                        return;
                    }

                    var from = parseDate(parts[0].trim());
                    var to = parseDate(parts[1].trim());
                    if (!from || !to) {
                        return;
                    }

                    state.dateFrom = toIsoDate(from);
                    state.dateTo = toIsoDate(to);
                    fetchKpis();
                });
                return;
            }

            window.$(rangeInput).daterangepicker({
                startDate: window.moment(state.dateFrom, 'YYYY-MM-DD'),
                endDate: window.moment(state.dateTo, 'YYYY-MM-DD'),
                autoUpdateInput: true,
                parentEl: 'body',
                opens: 'left',
                drops: 'down',
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                    fromLabel: 'Desde',
                    toLabel: 'Hasta',
                    customRangeLabel: 'Personalizado'
                },
                ranges: {
                    'Hoy': [window.moment(), window.moment()],
                    'Últimos 7 días': [window.moment().subtract(6, 'days'), window.moment()],
                    'Últimos 30 días': [window.moment().subtract(29, 'days'), window.moment()],
                    'Este mes': [window.moment().startOf('month'), window.moment().endOf('month')],
                    'Mes anterior': [window.moment().subtract(1, 'month').startOf('month'), window.moment().subtract(1, 'month').endOf('month')]
                }
            }, function (startMoment, endMoment) {
                state.dateFrom = startMoment.format('YYYY-MM-DD');
                state.dateTo = endMoment.format('YYYY-MM-DD');
                fetchKpis();
            });
        }

        setupDateRange();
        wireEvents();
        fetchKpis();
    });
})();
