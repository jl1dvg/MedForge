(() => {
    const config = window.__SOLICITUDES_V2_UI__ || {};
    const endpoints = config.endpoints || {};
    const columns = Array.isArray(config.columns) ? config.columns : [];
    const initialFilters = config.initialFilters || {};

    const kanbanEndpoint = String(endpoints.kanban || '').trim();
    const actualizarEstadoEndpoint = String(endpoints.actualizarEstado || '').trim();
    const estadoEndpoint = String(endpoints.estado || '').trim();
    const crmBaseEndpoint = String(endpoints.crmBase || '').trim() || '/v2/solicitudes';

    if (!kanbanEndpoint || !actualizarEstadoEndpoint) {
        return;
    }

    const form = document.getElementById('solV2Filters');
    const board = document.getElementById('solV2Kanban');
    const toast = document.getElementById('solV2Toast');
    const afiliacionSelect = document.getElementById('solAfiliacion');
    const sedeSelect = document.getElementById('solSede');
    const doctorSelect = document.getElementById('solDoctor');
    const prioridadSelect = document.getElementById('solPrioridad');
    const searchInput = document.getElementById('solSearch');
    const dateFromInput = document.getElementById('solDateFrom');
    const dateToInput = document.getElementById('solDateTo');
    const toolbarBox = document.getElementById('solV2ToolbarBox');
    const metricsBox = document.getElementById('solV2Metrics');
    const kanbanView = document.getElementById('solV2ViewKanban');
    const tableView = document.getElementById('solicitudesViewTable');
    const tableBody = document.getElementById('solicitudesTableBody');
    const tableEmptyState = document.getElementById('solicitudesTableEmpty');
    const conciliacionView = document.getElementById('solicitudesConciliacionSection');
    const viewButtons = Array.from(document.querySelectorAll('[data-solicitudes-view]'));

    const VIEW_STORAGE_KEY = 'solicitudes:view-mode';
    const VIEW_DEFAULT = 'kanban';
    const VIEW_ALLOWED = new Set(['kanban', 'table', 'conciliacion']);
    let storedView = '';
    try {
        storedView = String(window.localStorage.getItem(VIEW_STORAGE_KEY) || '').toLowerCase();
    } catch (error) {
        storedView = '';
    }

    const state = {
        rows: [],
        options: {
            afiliaciones: [],
            sedes: [],
            doctores: [],
            metrics: {},
            crm: {},
        },
        loading: false,
        crmPanel: {
            api: null,
            loadingPromise: null,
            failed: false,
        },
        prefacturaPanel: {
            api: null,
            loadingPromise: null,
            failed: false,
        },
        conciliacionPanel: {
            api: null,
            loadingPromise: null,
            failed: false,
        },
        view: VIEW_ALLOWED.has(storedView) ? storedView : VIEW_DEFAULT,
    };

    const labelByColumn = columns.reduce((acc, col) => {
        acc[String(col.slug || '')] = String(col.label || col.slug || '');
        return acc;
    }, {});

    const estadoColorBySlug = {
        'recibida': 'secondary',
        'llamado': 'info',
        'revision-codigos': 'warning',
        'espera-documentos': 'warning',
        'apto-oftalmologo': 'primary',
        'apto-anestesia': 'primary',
        'listo-para-agenda': 'success',
        'programada': 'success',
        'completado': 'dark',
    };

    const showToast = (message, ok) => {
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.classList.remove('ok', 'err');
        toast.classList.add(ok ? 'ok' : 'err');
        toast.style.display = 'block';
        window.clearTimeout(showToast._timer);
        showToast._timer = window.setTimeout(() => {
            toast.style.display = 'none';
        }, 3400);
    };

    const initTooltips = () => {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((node) => {
                try {
                    if (typeof window.bootstrap.Tooltip.getOrCreateInstance === 'function') {
                        window.bootstrap.Tooltip.getOrCreateInstance(node);
                        return;
                    }

                    if (typeof window.bootstrap.Tooltip.getInstance === 'function') {
                        const existing = window.bootstrap.Tooltip.getInstance(node);
                        if (existing) {
                            return;
                        }
                    }

                    // Bootstrap versions without getOrCreateInstance.
                    // eslint-disable-next-line no-new
                    new window.bootstrap.Tooltip(node);
                } catch (error) {
                    // Ignore tooltip init failures to avoid blocking page init.
                }
            });
            return;
        }

        if (window.jQuery && typeof window.jQuery.fn.tooltip === 'function') {
            window.jQuery('[data-bs-toggle="tooltip"]').tooltip();
        }
    };

    const requestId = () => {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return `sol-v2-${crypto.randomUUID()}`;
        }
        return `sol-v2-${Date.now()}-${Math.floor(Math.random() * 99999)}`;
    };

    const getFilters = () => ({
        search: searchInput ? searchInput.value.trim() : '',
        afiliacion: afiliacionSelect ? afiliacionSelect.value.trim() : '',
        sede: sedeSelect ? sedeSelect.value.trim() : '',
        doctor: doctorSelect ? doctorSelect.value.trim() : '',
        prioridad: prioridadSelect ? prioridadSelect.value.trim() : '',
        date_from: dateFromInput ? dateFromInput.value.trim() : '',
        date_to: dateToInput ? dateToInput.value.trim() : '',
    });

    const asFechaTexto = (filters) => {
        if (filters.date_from && filters.date_to) {
            const from = filters.date_from.split('-').reverse().join('-');
            const to = filters.date_to.split('-').reverse().join('-');
            return `${from} - ${to}`;
        }
        return '';
    };

    const asIsoRangeText = (filters) => {
        if (filters.date_from && filters.date_to) {
            return `${filters.date_from} - ${filters.date_to}`;
        }
        if (filters.date_from) {
            return filters.date_from;
        }
        if (filters.date_to) {
            return filters.date_to;
        }
        return '';
    };

    const applyInitialFilters = () => {
        if (searchInput && typeof initialFilters.search === 'string') {
            searchInput.value = initialFilters.search;
        }
        if (dateFromInput && typeof initialFilters.date_from === 'string') {
            dateFromInput.value = initialFilters.date_from;
        }
        if (dateToInput && typeof initialFilters.date_to === 'string') {
            dateToInput.value = initialFilters.date_to;
        }
        if (prioridadSelect && typeof initialFilters.prioridad === 'string') {
            prioridadSelect.value = initialFilters.prioridad;
        }
        if (sedeSelect && typeof initialFilters.sede === 'string') {
            sedeSelect.value = initialFilters.sede;
        }
    };

    const buildEstadosMeta = () => columns.reduce((acc, column) => {
        const slug = String(column.slug || '').trim();
        if (!slug) {
            return acc;
        }
        acc[slug] = {
            label: String(column.label || slug),
            color: estadoColorBySlug[slug] || 'secondary',
        };
        return acc;
    }, {});

    const syncLegacyKanbanBridge = () => {
        const existing = window.__KANBAN_MODULE__ && typeof window.__KANBAN_MODULE__ === 'object'
            ? window.__KANBAN_MODULE__
            : {};
        const existingSelectors = existing.selectors && typeof existing.selectors === 'object'
            ? existing.selectors
            : {};

        window.__KANBAN_MODULE__ = {
            ...existing,
            key: 'solicitudes',
            basePath: '/v2/solicitudes',
            apiBasePath: '/api',
            readPrefix: '/v2',
            v2ReadsEnabled: true,
            writePrefix: '/v2',
            v2WritesEnabled: true,
            dataKey: '__solicitudesKanban',
            estadosMetaKey: '__solicitudesEstadosMeta',
            selectors: {
                ...existingSelectors,
                prefix: 'solicitudes',
            },
            strings: {
                singular: 'solicitud',
                plural: 'solicitudes',
                capitalizedPlural: 'Solicitudes',
                articleSingular: 'la',
                articleSingularShort: 'la',
                ...(existing.strings && typeof existing.strings === 'object' ? existing.strings : {}),
            },
        };

        window.__solicitudesKanban = Array.isArray(state.rows) ? state.rows : [];
        window.__solicitudesEstadosMeta = buildEstadosMeta();
        window.aplicarFiltros = () => {
            loadKanban();
        };
    };

    const fetchJson = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Request-Id': requestId(),
            },
            body: JSON.stringify(payload || {}),
        });

        const json = await response.json().catch(() => ({}));
        return {response, json};
    };

    const renderSelectOptions = (selectNode, values, selectedValue) => {
        if (!selectNode) {
            return;
        }

        const current = selectedValue || '';
        const first = selectNode.querySelector('option');
        const firstLabel = first ? first.textContent : 'Todas';

        selectNode.innerHTML = `<option value="">${firstLabel || 'Todas'}</option>`;
        (values || []).forEach((value) => {
            const label = String(value || '').trim();
            if (!label) {
                return;
            }
            const option = document.createElement('option');
            option.value = label;
            option.textContent = label;
            if (label === current) {
                option.selected = true;
            }
            selectNode.appendChild(option);
        });
    };

    const fmtNumber = (value) => {
        const number = Number(value || 0);
        if (!Number.isFinite(number)) {
            return '0';
        }
        return new Intl.NumberFormat('es-EC').format(number);
    };

    const renderMetrics = (metrics) => {
        const sla = (metrics && metrics.sla) || {};
        const alerts = (metrics && metrics.alerts) || {};
        const total = Array.isArray(state.rows) ? state.rows.length : 0;

        const map = {
            mTotal: total,
            mSlaVencido: sla.vencido || 0,
            mSlaCritico: sla.critico || 0,
            mDocs: alerts.documentos_faltantes || 0,
            mAuth: alerts.autorizacion_pendiente || 0,
        };

        Object.keys(map).forEach((id) => {
            const node = document.getElementById(id);
            if (node) {
                node.textContent = fmtNumber(map[id]);
            }
        });
    };

    const escapeHtml = (value) => {
        const str = String(value == null ? '' : value);
        return str
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const toShortDate = (value) => {
        const raw = String(value || '').trim();
        if (!raw) {
            return '--';
        }
        const date = new Date(raw);
        if (!Number.isNaN(date.getTime())) {
            return date.toLocaleDateString('es-EC', {year: 'numeric', month: '2-digit', day: '2-digit'});
        }
        return raw;
    };

    const truncateText = (value, max) => {
        const text = String(value || '').trim();
        if (!text || text.length <= max) {
            return text;
        }
        return `${text.slice(0, Math.max(0, max - 1)).trimEnd()}...`;
    };

    const initials = (value) => {
        const raw = String(value || '').trim();
        if (!raw) {
            return '--';
        }
        const parts = raw.split(/\s+/).filter(Boolean);
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
    };

    const renderAvatar = (fullName, avatarUrl) => {
        if (avatarUrl) {
            return `<img class="sol-v2-avatar-img" src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(fullName)}" loading="lazy">`;
        }
        return `<span class="sol-v2-avatar-fallback">${escapeHtml(initials(fullName))}</span>`;
    };

    const slaToneClass = (value) => {
        const normalized = String(value || '').trim().toLowerCase();
        if (normalized === 'vencido' || normalized === 'critico') {
            return 'is-danger';
        }
        if (normalized === 'advertencia') {
            return 'is-warning';
        }
        return 'is-ok';
    };

    const cardTemplate = (row) => {
        const prioridad = String(row.prioridad || row.prioridad_automatica_label || '').trim() || 'Normal';
        const sla = String(row.sla_status || '').trim() || 'sin_fecha';
        const nextSlug = String((row.kanban_next && row.kanban_next.slug) || '').trim();
        const nextLabel = String((row.kanban_next && row.kanban_next.label) || '').trim();
        const requestIdValue = String(row.id || '');
        const hcNumber = String(row.hc_number || '');
        const formId = String(row.form_id || '');
        const fullName = String(row.full_name || 'Paciente sin nombre');
        const doctorName = String(row.doctor || 'N/A');
        const procName = String(row.procedimiento || 'Sin procedimiento');
        const shortProc = truncateText(procName, 95);
        const pipeline = String(row.crm_pipeline_stage || 'Recibido');
        const responsable = String(row.crm_responsable_nombre || 'Sin responsable');
        const notesCount = Number(row.crm_total_notas || 0);
        const attachmentsCount = Number(row.crm_total_adjuntos || 0);
        const pendingTasks = Number(row.crm_tareas_pendientes || 0);
        const totalTasks = Number(row.crm_tareas_total || 0);
        const avatar = String(row.doctor_avatar || row.crm_responsable_avatar || '').trim();
        const slaLabel = sla.replaceAll('_', ' ');
        const dateLabel = toShortDate(row.fecha_programada || row.fecha || row.created_at);

        return `
            <article class="sol-v2-card" data-id="${escapeHtml(requestIdValue)}">
                <div class="sol-v2-card-head">
                    <div class="sol-v2-avatar">${renderAvatar(fullName, avatar)}</div>
                    <div class="sol-v2-head-copy">
                        <h6>${escapeHtml(fullName)}</h6>
                        <p class="sol-v2-card-meta">HC ${escapeHtml(hcNumber || '--')} · Form ${escapeHtml(formId || '--')}</p>
                    </div>
                    <span class="sol-v2-chip ${slaToneClass(sla)}">${escapeHtml(slaLabel)}</span>
                </div>
                <p class="sol-v2-card-proc">${escapeHtml(shortProc)}</p>
                <div class="sol-v2-card-meta-grid">
                    <span><i class="mdi mdi-stethoscope"></i> ${escapeHtml(doctorName)}</span>
                    <span><i class="mdi mdi-calendar"></i> ${escapeHtml(dateLabel)}</span>
                    <span><i class="mdi mdi-source-branch"></i> ${escapeHtml(pipeline)}</span>
                    <span><i class="mdi mdi-account-star"></i> ${escapeHtml(responsable)}</span>
                </div>
                <div class="sol-v2-tags">
                    <span class="sol-v2-tag sol-v2-tag-priority">${escapeHtml(prioridad)}</span>
                    <span class="sol-v2-tag sol-v2-tag-ops"><i class="mdi mdi-note-text-outline"></i> ${notesCount}</span>
                    <span class="sol-v2-tag sol-v2-tag-ops"><i class="mdi mdi-paperclip"></i> ${attachmentsCount}</span>
                    <span class="sol-v2-tag sol-v2-tag-ops"><i class="mdi mdi-format-list-checks"></i> ${pendingTasks}/${totalTasks}</span>
                </div>
                <div class="sol-v2-card-actions">
                    <a class="btn btn-xs btn-light" href="/v2/pacientes/detalles?hc_number=${encodeURIComponent(hcNumber)}">Paciente</a>
                    <button class="btn btn-xs btn-outline-secondary btn-open-crm" data-action="open-crm" data-id="${escapeHtml(requestIdValue)}" data-paciente="${escapeHtml(fullName)}" data-solicitud-id="${escapeHtml(requestIdValue)}" data-paciente-nombre="${escapeHtml(fullName)}">CRM</button>
                    <button class="btn btn-xs btn-outline-primary" data-action="refresh-detalle" data-id="${escapeHtml(requestIdValue)}" data-hc="${escapeHtml(hcNumber)}" data-form="${escapeHtml(formId)}">Detalle</button>
                    ${
                        nextSlug
                            ? `<button class="btn btn-xs btn-primary" data-action="advance" data-id="${escapeHtml(requestIdValue)}" data-next="${escapeHtml(nextSlug)}">${escapeHtml(nextLabel || 'Avanzar')}</button>`
                            : ''
                    }
                </div>
            </article>
        `;
    };

    const formatTurnoLabel = (turno) => {
        if (turno == null) {
            return '';
        }

        if (typeof turno === 'object') {
            const candidate = turno.turno ?? turno.numero ?? turno.label ?? turno.codigo ?? '';
            return String(candidate || '').trim();
        }

        return String(turno).trim();
    };

    const tableRowTemplate = (row) => {
        const solicitudId = String(row.id || '').trim();
        const hcNumber = String(row.hc_number || '').trim();
        const formId = String(row.form_id || '').trim();
        const fullName = String(row.full_name || 'Paciente sin nombre');
        const procedimiento = String(row.procedimiento || 'Sin procedimiento');
        const doctor = String(row.doctor || 'Sin doctor');
        const afiliacion = String(row.afiliacion || 'Sin afiliación');
        const estado = String(row.estado || 'Sin estado');
        const estadoKanban = String(row.kanban_estado_label || labelByColumn[row.kanban_estado] || '').trim();
        const pipeline = String(row.crm_pipeline_stage || 'Recibido');
        const responsable = String(row.crm_responsable_nombre || 'Sin responsable');
        const sla = String(row.sla_status || 'sin_fecha').replaceAll('_', ' ');
        const turno = formatTurnoLabel(row.turno);
        const nextSlug = String((row.kanban_next && row.kanban_next.slug) || '').trim();
        const nextLabel = String((row.kanban_next && row.kanban_next.label) || '').trim();

        return `
            <tr class="sol-v2-table-row" data-id="${escapeHtml(solicitudId)}" data-hc="${escapeHtml(hcNumber)}" data-form="${escapeHtml(formId)}" data-paciente="${escapeHtml(fullName)}">
                <td>
                    <div class="fw-semibold">${escapeHtml(fullName)}</div>
                    <div class="text-muted small">HC ${escapeHtml(hcNumber || '--')} · Form ${escapeHtml(formId || '--')}</div>
                </td>
                <td>
                    <div>${escapeHtml(procedimiento)}</div>
                    <div class="text-muted small">${escapeHtml(doctor)}</div>
                    <div class="text-muted small">${escapeHtml(afiliacion)}</div>
                </td>
                <td>
                    <span class="badge text-bg-light text-dark">${escapeHtml(estado)}</span>
                    ${estadoKanban ? `<div class="text-muted small mt-1">${escapeHtml(estadoKanban)}</div>` : ''}
                </td>
                <td>
                    <div class="fw-semibold small">${escapeHtml(pipeline)}</div>
                    <div class="text-muted small">${escapeHtml(responsable)}</div>
                </td>
                <td>
                    <span class="sol-v2-chip ${slaToneClass(sla)}">${escapeHtml(sla)}</span>
                </td>
                <td>
                    ${turno ? `<span class="badge text-bg-info text-dark">#${escapeHtml(turno)}</span>` : '<span class="text-muted">—</span>'}
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-xs btn-outline-primary" data-action="refresh-detalle" data-id="${escapeHtml(solicitudId)}" data-hc="${escapeHtml(hcNumber)}" data-form="${escapeHtml(formId)}" title="Abrir detalle rápido">
                            <i class="mdi mdi-eye-outline"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" data-action="open-crm" data-id="${escapeHtml(solicitudId)}" data-paciente="${escapeHtml(fullName)}" data-solicitud-id="${escapeHtml(solicitudId)}" data-paciente-nombre="${escapeHtml(fullName)}" title="Abrir CRM">
                            <i class="mdi mdi-account-box-outline"></i>
                        </button>
                        ${
                            nextSlug
                                ? `<button type="button" class="btn btn-xs btn-primary" data-action="advance" data-id="${escapeHtml(solicitudId)}" data-next="${escapeHtml(nextSlug)}" title="Mover al siguiente estado">${escapeHtml(nextLabel || 'Avanzar')}</button>`
                                : ''
                        }
                    </div>
                </td>
            </tr>
        `;
    };

    const renderTable = (rows) => {
        if (!tableBody) {
            return;
        }

        const list = Array.isArray(rows) ? rows : [];
        if (!list.length) {
            tableBody.innerHTML = '';
            if (tableEmptyState) {
                tableEmptyState.classList.remove('d-none');
            }
            return;
        }

        if (tableEmptyState) {
            tableEmptyState.classList.add('d-none');
        }

        tableBody.innerHTML = list.map((row) => tableRowTemplate(row)).join('');
    };

    const groupRowsByColumn = (rows) => {
        const grouped = {};
        columns.forEach((column) => {
            grouped[String(column.slug)] = [];
        });

        (rows || []).forEach((row) => {
            const key = String(row.kanban_estado || 'programada');
            if (!grouped[key]) {
                grouped[key] = [];
            }
            grouped[key].push(row);
        });

        return grouped;
    };

    const renderBoard = () => {
        if (!board) {
            return;
        }

        const grouped = groupRowsByColumn(state.rows);

        columns.forEach((column) => {
            const slug = String(column.slug || '');
            const col = board.querySelector(`[data-column="${slug}"]`);
            if (!col) {
                return;
            }

            const body = col.querySelector('[data-body]');
            const count = col.querySelector('[data-count]');
            const items = grouped[slug] || [];

            if (count) {
                count.textContent = String(items.length);
            }

            if (!body) {
                return;
            }

            if (items.length === 0) {
                body.innerHTML = '<div class="sol-v2-empty">Sin solicitudes</div>';
                return;
            }

            body.innerHTML = items.map(cardTemplate).join('');
        });
    };

    const ensureCrmPanel = async () => {
        if (state.crmPanel.failed) {
            return null;
        }

        if (state.crmPanel.api) {
            return state.crmPanel.api;
        }

        if (!state.crmPanel.loadingPromise) {
            state.crmPanel.loadingPromise = import('/js/pages/shared/crmPanelFactory.js')
                .then((module) => {
                    if (!module || typeof module.createCrmPanel !== 'function') {
                        throw new Error('Módulo CRM no disponible');
                    }

                    const panel = module.createCrmPanel({
                        showToast,
                        getBasePath: () => crmBaseEndpoint,
                        entityLabel: 'solicitud',
                        entityArticle: 'la',
                        entitySelectionSuffix: 'seleccionada',
                        datasetIdKey: 'solicitudId',
                    });

                    state.crmPanel.api = panel;
                    return panel;
                })
                .catch((error) => {
                    state.crmPanel.failed = true;
                    console.error('No se pudo inicializar el panel CRM v2', error);
                    showToast('No se pudo inicializar el panel CRM', false);
                    return null;
                });
        }

        return state.crmPanel.loadingPromise;
    };

    const syncCrmPanel = async () => {
        const panel = await ensureCrmPanel();
        if (!panel) {
            return;
        }

        const crmOptions = state.options && state.options.crm && typeof state.options.crm === 'object'
            ? state.options.crm
            : {};

        panel.setCrmOptions(crmOptions);
        panel.initCrmInteractions();
    };

    const openCrmPanelForSolicitud = async (solicitudId, nombrePaciente) => {
        const panel = await ensureCrmPanel();
        if (!panel) {
            return;
        }

        panel.setCrmOptions((state.options && state.options.crm) || {});

        if (typeof panel.openEntityCrm === 'function') {
            panel.openEntityCrm(solicitudId, nombrePaciente || '');
            return;
        }

        panel.initCrmInteractions();
        const fallbackButton = board
            ? board.querySelector(`.btn-open-crm[data-solicitud-id="${String(solicitudId)}"]`)
            : null;
        if (fallbackButton) {
            fallbackButton.click();
        } else {
            showToast('No se pudo abrir el panel CRM para la solicitud', false);
        }
    };

    const ensurePrefacturaModalApi = async () => {
        if (state.prefacturaPanel.failed) {
            return null;
        }

        if (state.prefacturaPanel.api) {
            return state.prefacturaPanel.api;
        }

        if (!state.prefacturaPanel.loadingPromise) {
            state.prefacturaPanel.loadingPromise = import('/js/pages/solicitudes/kanban/modalDetalles/prefactura.js')
                .then((module) => {
                    if (!module || typeof module.abrirPrefactura !== 'function') {
                        throw new Error('Módulo de prefactura no disponible');
                    }
                    state.prefacturaPanel.api = module;
                    return module;
                })
                .catch((error) => {
                    state.prefacturaPanel.failed = true;
                    console.error('No se pudo inicializar el modal prefactura', error);
                    showToast('No se pudo inicializar el modal de detalle', false);
                    return null;
                });
        }

        return state.prefacturaPanel.loadingPromise;
    };

    const attachPrefacturaCrmProxy = () => {
        const modal = document.getElementById('prefacturaModal');
        if (!modal || modal.dataset.crmProxyAttached === 'true') {
            return;
        }
        modal.dataset.crmProxyAttached = 'true';

        modal.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-crm-proxy]');
            if (!trigger) {
                return;
            }

            const solicitudId = String(trigger.dataset.solicitudId || trigger.dataset.id || '').trim();
            if (!solicitudId) {
                return;
            }

            const selectorId = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                ? CSS.escape(solicitudId)
                : solicitudId.replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
            const crmButton = board
                ? board.querySelector(`.btn-open-crm[data-solicitud-id="${selectorId}"]`)
                : null;

            if (crmButton) {
                crmButton.click();
            }
        });
    };

    const persistView = (view) => {
        try {
            window.localStorage.setItem(VIEW_STORAGE_KEY, view);
        } catch (error) {
            // Ignore storage errors in restricted browsers.
        }
    };

    const ensureConciliacionPanel = async () => {
        if (state.conciliacionPanel.failed) {
            return null;
        }

        if (state.conciliacionPanel.api) {
            return state.conciliacionPanel.api;
        }

        if (!state.conciliacionPanel.loadingPromise) {
            syncLegacyKanbanBridge();
            state.conciliacionPanel.loadingPromise = import('/js/pages/solicitudes/conciliacion.js')
                .then((module) => {
                    if (!module || typeof module.initSolicitudesConciliacion !== 'function') {
                        throw new Error('Módulo de conciliación no disponible');
                    }

                    state.conciliacionPanel.api = module.initSolicitudesConciliacion({
                        showToast,
                        getFilters: () => {
                            const filters = getFilters();
                            return {
                                ...filters,
                                fechaTexto: asIsoRangeText(filters),
                            };
                        },
                        onConfirmed: async () => {
                            await loadKanban();
                        },
                    });

                    return state.conciliacionPanel.api;
                })
                .catch((error) => {
                    state.conciliacionPanel.failed = true;
                    console.error('No se pudo inicializar conciliación', error);
                    showToast('No se pudo inicializar la vista de conciliación', false);
                    return null;
                });
        }

        return state.conciliacionPanel.loadingPromise;
    };

    const resolveView = (view) => {
        const normalized = String(view || '').toLowerCase();
        return VIEW_ALLOWED.has(normalized) ? normalized : VIEW_DEFAULT;
    };

    const switchView = async (nextView, persist = true) => {
        const normalized = resolveView(nextView);
        state.view = normalized;

        if (kanbanView) {
            kanbanView.classList.toggle('d-none', normalized !== 'kanban');
        }

        if (tableView) {
            tableView.classList.toggle('d-none', normalized !== 'table');
        }

        if (conciliacionView) {
            conciliacionView.classList.toggle('d-none', normalized !== 'conciliacion');
        }

        const hideForConciliacion = normalized === 'conciliacion';
        if (toolbarBox) {
            toolbarBox.classList.toggle('d-none', hideForConciliacion);
        }
        if (metricsBox) {
            metricsBox.classList.toggle('d-none', hideForConciliacion);
        }

        viewButtons.forEach((button) => {
            const buttonView = resolveView(button.dataset.solicitudesView || '');
            button.classList.toggle('is-active', buttonView === normalized);
        });

        if (persist) {
            persistView(normalized);
        }

        if (normalized === 'conciliacion') {
            const panel = await ensureConciliacionPanel();
            if (panel && typeof panel.reload === 'function') {
                panel.reload();
            }
        }
    };

    const loadKanban = async () => {
        if (state.loading) {
            return;
        }
        state.loading = true;

        const filters = getFilters();
        const payload = {
            ...filters,
            fechaTexto: asFechaTexto(filters),
        };

        try {
            const {response, json} = await fetchJson(kanbanEndpoint, payload);
            if (response.status === 401) {
                window.location.href = '/auth/login?auth_required=1';
                return;
            }
            if (!response.ok) {
                throw new Error(json.error || `Error HTTP ${response.status}`);
            }

            state.rows = Array.isArray(json.data) ? json.data : [];
            state.options = json.options || {};
            syncLegacyKanbanBridge();

            renderSelectOptions(afiliacionSelect, state.options.afiliaciones || [], filters.afiliacion);
            renderSelectOptions(sedeSelect, state.options.sedes || ['MATRIZ', 'CEIBOS'], filters.sede);
            renderSelectOptions(doctorSelect, state.options.doctores || [], filters.doctor);
            renderMetrics((state.options.metrics || {}));
            renderBoard();
            renderTable(state.rows);
            initTooltips();
            await syncCrmPanel();

            if (state.view === 'conciliacion') {
                const panel = await ensureConciliacionPanel();
                if (panel && typeof panel.reload === 'function') {
                    panel.reload();
                }
            }
        } catch (error) {
            renderTable([]);
            showToast(`No se pudo cargar solicitudes: ${error.message || error}`, false);
        } finally {
            state.loading = false;
        }
    };

    const advanceSolicitud = async (solicitudId, nextSlug) => {
        const body = {
            id: Number(solicitudId),
            estado: String(nextSlug || ''),
            completado: true,
            force: true,
        };

        try {
            const {response, json} = await fetchJson(actualizarEstadoEndpoint, body);
            if (response.status === 401) {
                window.location.href = '/auth/login?auth_required=1';
                return;
            }
            if (!response.ok || json.success === false) {
                throw new Error(json.error || `No se pudo actualizar estado (${response.status})`);
            }

            showToast('Estado actualizado', true);
            await loadKanban();
        } catch (error) {
            showToast(error.message || 'No se pudo actualizar estado', false);
        }
    };

    const loadDetalle = async (solicitudId, hcNumber, formId) => {
        if (!estadoEndpoint) {
            return;
        }

        const params = new URLSearchParams();
        if (hcNumber) {
            params.set('hcNumber', hcNumber);
        }
        if (formId) {
            params.set('form_id', formId);
        }

        try {
            const response = await fetch(`${estadoEndpoint}?${params.toString()}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Request-Id': requestId(),
                },
            });
            const json = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(json.error || `Error HTTP ${response.status}`);
            }

            const solicitudes = Array.isArray(json.solicitudes) ? json.solicitudes : [];
            const found = solicitudes.find((item) => String(item.id || '') === String(solicitudId || ''));
            if (!found) {
                showToast('No se encontró detalle de la solicitud', false);
                return;
            }

            const status = `${found.estado || 'N/A'} | ${found.kanban_estado_label || labelByColumn[found.kanban_estado] || 'N/A'}`;
            showToast(`Detalle: ${status}`, true);
        } catch (error) {
            showToast(`Detalle no disponible: ${error.message || error}`, false);
        }
    };

    const openDetalleModal = async (solicitudId, hcNumber, formId) => {
        const hc = String(hcNumber || '').trim();
        const form = String(formId || '').trim();
        if (!hc || !form) {
            showToast('No se puede abrir detalle sin HC y formulario', false);
            return;
        }

        if (!window.bootstrap || !window.bootstrap.Modal) {
            showToast('Bootstrap modal no está disponible', false);
            return;
        }

        syncLegacyKanbanBridge();
        attachPrefacturaCrmProxy();

        const prefacturaApi = await ensurePrefacturaModalApi();
        if (!prefacturaApi || typeof prefacturaApi.abrirPrefactura !== 'function') {
            await loadDetalle(solicitudId, hc, form);
            return;
        }

        try {
            prefacturaApi.abrirPrefactura({
                hc,
                formId: form,
                solicitudId,
            });
        } catch (error) {
            console.error('No se pudo abrir detalle prefactura', error);
            showToast('No se pudo abrir detalle, mostrando estado básico', false);
            await loadDetalle(solicitudId, hc, form);
        }
    };

    const handleActionTarget = (target) => {
        const action = String(target.dataset.action || '');

        if (action === 'advance') {
            const id = target.dataset.id || '';
            const next = target.dataset.next || '';
            if (!id || !next) {
                return true;
            }
            advanceSolicitud(id, next);
            return true;
        }

        if (action === 'open-crm') {
            const id = target.dataset.id || '';
            const paciente = target.dataset.paciente || '';
            if (!id) {
                showToast('Solicitud inválida para abrir CRM', false);
                return true;
            }
            openCrmPanelForSolicitud(id, paciente);
            return true;
        }

        if (action === 'refresh-detalle') {
            const id = target.dataset.id || '';
            const hc = target.dataset.hc || '';
            const formId = target.dataset.form || '';
            openDetalleModal(id, hc, formId);
            return true;
        }

        return false;
    };

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            loadKanban();
        });
    }

    if (board) {
        board.addEventListener('click', (event) => {
            const target = event.target.closest('[data-action]');
            if (!target) {
                return;
            }
            handleActionTarget(target);
        });
    }

    if (tableView) {
        tableView.addEventListener('click', (event) => {
            const actionTarget = event.target.closest('[data-action]');
            if (actionTarget) {
                handleActionTarget(actionTarget);
                return;
            }

            const clickable = event.target.closest('button, a, input, select, textarea, label');
            if (clickable) {
                return;
            }

            const row = event.target.closest('tr[data-id]');
            if (!row) {
                return;
            }

            const id = row.dataset.id || '';
            const hc = row.dataset.hc || '';
            const formId = row.dataset.form || '';
            openDetalleModal(id, hc, formId);
        });
    }

    viewButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const targetView = button.dataset.solicitudesView || VIEW_DEFAULT;
            switchView(targetView);
        });
    });

    applyInitialFilters();
    syncLegacyKanbanBridge();
    initTooltips();
    switchView(state.view, false);
    loadKanban();
})();
