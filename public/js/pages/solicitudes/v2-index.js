(() => {
    const config = window.__SOLICITUDES_V2_UI__ || {};
    const endpoints = config.endpoints || {};
    const columns = Array.isArray(config.columns) ? config.columns : [];
    const initialFilters = config.initialFilters || {};
    const rawRealtimeConfig = config.realtime && typeof config.realtime === 'object'
        ? config.realtime
        : {};
    const fallbackRealtimeConfig = window.MEDF_PusherConfig && typeof window.MEDF_PusherConfig === 'object'
        ? window.MEDF_PusherConfig
        : {};
    const realtimeConfig = {
        ...fallbackRealtimeConfig,
        ...rawRealtimeConfig,
        events: {
            ...(fallbackRealtimeConfig.events && typeof fallbackRealtimeConfig.events === 'object' ? fallbackRealtimeConfig.events : {}),
            ...(rawRealtimeConfig.events && typeof rawRealtimeConfig.events === 'object' ? rawRealtimeConfig.events : {}),
        },
        channels: {
            ...(fallbackRealtimeConfig.channels && typeof fallbackRealtimeConfig.channels === 'object' ? fallbackRealtimeConfig.channels : {}),
            ...(rawRealtimeConfig.channels && typeof rawRealtimeConfig.channels === 'object' ? rawRealtimeConfig.channels : {}),
        },
    };

    const rawToastDismiss = Number(realtimeConfig.toast_auto_dismiss_seconds);
    const toastDurationMs = Number.isFinite(rawToastDismiss)
        ? (rawToastDismiss <= 0 ? 0 : rawToastDismiss * 1000)
        : 4000;
    const rawDesktopDismiss = Number(realtimeConfig.auto_dismiss_seconds);
    const desktopDismissSeconds = Number.isFinite(rawDesktopDismiss) && rawDesktopDismiss > 0
        ? rawDesktopDismiss
        : 0;
    const rawRetentionDays = Number(realtimeConfig.panel_retention_days);
    const panelRetentionDays = Number.isFinite(rawRetentionDays) && rawRetentionDays >= 0
        ? rawRetentionDays
        : 7;
    const notificationStorageKey = String(config.notificationStorageKey || 'medf:notification-panel:solicitudes-v2').trim()
        || 'medf:notification-panel:solicitudes-v2';
    const defaultChannels = {
        email: Boolean((window.MEDF && window.MEDF.defaultNotificationChannels && window.MEDF.defaultNotificationChannels.email) || realtimeConfig.channels.email),
        sms: Boolean((window.MEDF && window.MEDF.defaultNotificationChannels && window.MEDF.defaultNotificationChannels.sms) || realtimeConfig.channels.sms),
        daily_summary: Boolean((window.MEDF && window.MEDF.defaultNotificationChannels && window.MEDF.defaultNotificationChannels.daily_summary) || realtimeConfig.channels.daily_summary),
    };

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
    const tipoSelect = document.getElementById('solTipo');
    const responsableSelect = document.getElementById('solResponsable');
    const sinResponsableCheckbox = document.getElementById('solCrmSinResponsable');
    const derivacionVencidaCheckbox = document.getElementById('solDerivacionVencida');
    const derivacionPorVencerCheckbox = document.getElementById('solDerivacionPorVencer');
    const derivacionDiasInput = document.getElementById('solDerivacionDias');
    const exportPdfButton = document.getElementById('solExportPdfBtn');
    const exportExcelButton = document.getElementById('solExportExcelBtn');
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
        filteredRows: [],
        options: {
            afiliaciones: [],
            sedes: [],
            doctores: [],
            metrics: {},
            crm: {},
        },
        loading: false,
        exporting: false,
        notificationPanel: null,
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
        realtime: {
            bound: false,
            pusher: null,
            channel: null,
            refreshTimer: null,
        },
        view: VIEW_ALLOWED.has(storedView) ? storedView : VIEW_DEFAULT,
    };

    window.MEDF = window.MEDF || {};
    window.MEDF.defaultNotificationChannels = defaultChannels;
    window.MEDF.pusherIntegration = {
        enabled: Boolean(realtimeConfig.enabled),
        hasKey: Boolean(realtimeConfig.key),
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

    const showToast = (message, ok, durationMs = toastDurationMs) => {
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.classList.remove('ok', 'err');
        toast.classList.add(ok ? 'ok' : 'err');
        toast.style.display = 'block';
        window.clearTimeout(showToast._timer);
        const normalizedDuration = Number.isFinite(durationMs) ? durationMs : toastDurationMs;
        if (normalizedDuration > 0) {
            showToast._timer = window.setTimeout(() => {
                toast.style.display = 'none';
            }, normalizedDuration);
        }
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

    const mapChannels = (channels = {}) => {
        const merged = {
            email: channels && Object.prototype.hasOwnProperty.call(channels, 'email')
                ? Boolean(channels.email)
                : defaultChannels.email,
            sms: channels && Object.prototype.hasOwnProperty.call(channels, 'sms')
                ? Boolean(channels.sms)
                : defaultChannels.sms,
            daily_summary: channels && Object.prototype.hasOwnProperty.call(channels, 'daily_summary')
                ? Boolean(channels.daily_summary)
                : defaultChannels.daily_summary,
        };

        const labels = [];
        if (merged.email) {
            labels.push('Correo');
        }
        if (merged.sms) {
            labels.push('SMS');
        }
        if (merged.daily_summary) {
            labels.push('Resumen diario');
        }
        return labels;
    };

    const parseUserId = (value) => {
        const parsed = Number.parseInt(String(value ?? '').trim(), 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    };

    const maybeShowDesktopNotification = (title, body) => {
        if (!realtimeConfig.desktop_notifications || typeof window === 'undefined' || !('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }

        if (Notification.permission !== 'granted') {
            return;
        }

        const notification = new Notification(title, {body});
        if (desktopDismissSeconds > 0) {
            window.setTimeout(() => notification.close(), desktopDismissSeconds * 1000);
        }
    };

    const scheduleRealtimeReload = (delayMs = 550) => {
        if (state.realtime.refreshTimer) {
            window.clearTimeout(state.realtime.refreshTimer);
        }
        state.realtime.refreshTimer = window.setTimeout(() => {
            state.realtime.refreshTimer = null;
            loadKanban();
        }, Math.max(150, delayMs));
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

    const parseLocalDate = (value) => {
        if (!value) {
            return null;
        }
        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? null : value;
        }
        const raw = typeof value === 'string' ? value.trim() : String(value);
        if (!raw) {
            return null;
        }
        const normalized = raw.includes(' ') ? raw.replace(' ', 'T') : raw;
        const parsed = new Date(normalized);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const normalizeUpper = (value) => String(value || '').trim().toUpperCase();

    const syncResponsableOptions = (responsables, rows) => {
        if (!responsableSelect) {
            return;
        }

        const selectedValue = String(responsableSelect.value || '').trim();
        const map = new Map();

        (Array.isArray(responsables) ? responsables : []).forEach((item) => {
            const id = item?.id ?? item?.responsable_id ?? null;
            if (id == null) {
                return;
            }
            const name = String(item?.name ?? item?.nombre ?? item?.responsable_nombre ?? '').trim();
            if (!name) {
                return;
            }
            map.set(String(id), name);
        });

        if (map.size === 0) {
            (Array.isArray(rows) ? rows : []).forEach((row) => {
                const id = row?.crm_responsable_id;
                if (!id) {
                    return;
                }
                const name = String(row?.crm_responsable_nombre || `ID ${id}`).trim();
                map.set(String(id), name);
            });
        }

        const sorted = Array.from(map.entries()).sort((a, b) => a[1].localeCompare(b[1], 'es', {sensitivity: 'base'}));
        responsableSelect.innerHTML = '<option value="">Todos</option><option value="sin_asignar">Sin responsable</option>';

        sorted.forEach(([id, name]) => {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = name;
            responsableSelect.appendChild(option);
        });

        if (selectedValue && Array.from(responsableSelect.options).some((opt) => opt.value === selectedValue)) {
            responsableSelect.value = selectedValue;
        }
    };

    const applyLocalFilters = (rows) => {
        const items = Array.isArray(rows) ? rows : [];
        const tipoSeleccionado = normalizeUpper(tipoSelect ? tipoSelect.value : '');
        const filtrarDerivacionVencida = Boolean(derivacionVencidaCheckbox && derivacionVencidaCheckbox.checked);
        const filtrarDerivacionPorVencer = Boolean(derivacionPorVencerCheckbox && derivacionPorVencerCheckbox.checked);
        const derivacionDiasRaw = Number.parseInt(derivacionDiasInput ? derivacionDiasInput.value : '', 10);
        const diasPorVencer = Number.isFinite(derivacionDiasRaw) ? Math.max(0, derivacionDiasRaw) : 0;
        const filtrarSinResponsable = Boolean(sinResponsableCheckbox && sinResponsableCheckbox.checked);
        const responsableSeleccionado = String(responsableSelect ? responsableSelect.value : '').trim();

        const today = new Date();
        const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const msPerDay = 24 * 60 * 60 * 1000;

        return items.filter((item) => {
            if (tipoSeleccionado && normalizeUpper(item?.tipo) !== tipoSeleccionado) {
                return false;
            }

            if (responsableSeleccionado) {
                const responsableId = item?.crm_responsable_id ? String(item.crm_responsable_id) : '';
                if (responsableSeleccionado === 'sin_asignar' && responsableId !== '') {
                    return false;
                }
                if (responsableSeleccionado !== 'sin_asignar' && responsableId !== responsableSeleccionado) {
                    return false;
                }
            }

            if (filtrarSinResponsable && item?.crm_responsable_id) {
                return false;
            }

            if (filtrarDerivacionVencida || filtrarDerivacionPorVencer) {
                const fechaDerivacion = parseLocalDate(item?.derivacion_fecha_vigencia);
                if (!fechaDerivacion) {
                    return false;
                }
                const diffDays = Math.ceil((fechaDerivacion - todayStart) / msPerDay);
                const vencida = diffDays < 0;
                const porVencer = diffDays >= 0 && diffDays <= diasPorVencer;
                const matchDerivacion = (filtrarDerivacionVencida && vencida)
                    || (filtrarDerivacionPorVencer && porVencer);
                if (!matchDerivacion) {
                    return false;
                }
            }

            return true;
        });
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
        const total = Array.isArray(state.filteredRows) ? state.filteredRows.length : (Array.isArray(state.rows) ? state.rows.length : 0);

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

        const grouped = groupRowsByColumn(state.filteredRows);

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

    const ensureNotificationPanel = async () => {
        if (state.notificationPanel) {
            return state.notificationPanel;
        }

        try {
            const module = await import('/js/pages/solicitudes/notifications/panel.js');
            if (!module || typeof module.createNotificationPanel !== 'function') {
                return null;
            }

            state.notificationPanel = module.createNotificationPanel({
                panelId: 'kanbanNotificationPanel',
                backdropId: 'notificationPanelBackdrop',
                toggleSelector: '[data-notification-panel-toggle]',
                storageKey: notificationStorageKey,
                retentionDays: panelRetentionDays,
            });

            if (state.notificationPanel && typeof state.notificationPanel.setChannelPreferences === 'function') {
                state.notificationPanel.setChannelPreferences(defaultChannels);
            }

            return state.notificationPanel;
        } catch (error) {
            console.error('No se pudo inicializar el panel de avisos', error);
            return null;
        }
    };

    const initRealtimeNotifications = async () => {
        if (state.realtime.bound) {
            return;
        }
        state.realtime.bound = true;

        const panel = await ensureNotificationPanel();
        if (!panel) {
            return;
        }

        if (!realtimeConfig.enabled) {
            if (typeof panel.setIntegrationWarning === 'function') {
                panel.setIntegrationWarning('Las notificaciones en tiempo real están desactivadas en Configuración → Notificaciones.');
            }
            return;
        }

        if (typeof window.Pusher === 'undefined') {
            if (typeof panel.setIntegrationWarning === 'function') {
                panel.setIntegrationWarning('Pusher no está disponible. Verifica que el script se haya cargado correctamente.');
            }
            console.warn('Pusher no está disponible. Verifica que el script se haya cargado correctamente.');
            return;
        }

        const realtimeKey = String(realtimeConfig.key || '').trim();
        if (!realtimeKey) {
            if (typeof panel.setIntegrationWarning === 'function') {
                panel.setIntegrationWarning('No se configuró la APP Key de Pusher en los ajustes.');
            }
            console.warn('No se configuró la APP Key de Pusher.');
            return;
        }

        const options = {forceTLS: true};
        const realtimeCluster = String(realtimeConfig.cluster || '').trim();
        if (realtimeCluster) {
            options.cluster = realtimeCluster;
        }

        const pusher = new window.Pusher(realtimeKey, options);
        const channelName = String(realtimeConfig.channel || '').trim() || 'solicitudes-kanban';
        const channel = pusher.subscribe(channelName);

        state.realtime.pusher = pusher;
        state.realtime.channel = channel;

        if (typeof panel.setIntegrationWarning === 'function') {
            panel.setIntegrationWarning('');
        }

        const events = realtimeConfig.events && typeof realtimeConfig.events === 'object'
            ? realtimeConfig.events
            : {};
        const newEventName = String(events.new_request || realtimeConfig.event || 'kanban.nueva-solicitud').trim();
        const statusEventName = String(events.status_updated || 'kanban.estado-actualizado').trim();
        const crmEventName = String(events.crm_updated || 'crm.detalles-actualizados').trim();
        const whatsappHandoffEventName = String(events.whatsapp_handoff || 'whatsapp.handoff').trim();
        const reminderEvents = [
            {
                key: 'surgery',
                eventName: String(events.surgery_reminder || 'recordatorio-cirugia').trim(),
                defaultLabel: 'Recordatorio de cirugía',
                icon: 'mdi mdi-alarm-check',
                tone: 'primary',
            },
            {
                key: 'surgery_24h',
                eventName: String(events.surgery_precheck_24h || 'recordatorio-cirugia-24h').trim(),
                defaultLabel: 'Checklist 24h prequirúrgico',
                icon: 'mdi mdi-timer-sand',
                tone: 'info',
            },
            {
                key: 'surgery_2h',
                eventName: String(events.surgery_precheck_2h || 'recordatorio-cirugia-2h').trim(),
                defaultLabel: 'Checklist 2h prequirúrgico',
                icon: 'mdi mdi-timer-alert-outline',
                tone: 'warning',
            },
            {
                key: 'preop',
                eventName: String(events.preop_reminder || 'recordatorio-preop').trim(),
                defaultLabel: 'Preparación preoperatoria',
                icon: 'mdi mdi-clipboard-check-outline',
                tone: 'info',
            },
            {
                key: 'postop',
                eventName: String(events.postop_reminder || 'recordatorio-postop').trim(),
                defaultLabel: 'Control postoperatorio',
                icon: 'mdi mdi-heart-pulse',
                tone: 'success',
            },
            {
                key: 'post_consulta',
                eventName: String(events.post_consulta || 'postconsulta').trim(),
                defaultLabel: 'Seguimiento postconsulta',
                icon: 'mdi mdi-stethoscope',
                tone: 'primary',
            },
            {
                key: 'exams',
                eventName: String(events.exams_expiring || 'alerta-examenes-por-vencer').trim(),
                defaultLabel: 'Exámenes por vencer',
                icon: 'mdi mdi-file-alert-outline',
                tone: 'warning',
            },
            {
                key: 'exam_reminder',
                eventName: String(events.exam_reminder || 'recordatorio-examen').trim(),
                defaultLabel: 'Recordatorio de examen',
                icon: 'mdi mdi-file-check-outline',
                tone: 'info',
            },
            {
                key: 'crm_task',
                eventName: String(events.crm_task_reminder || 'crm.task-reminder').trim(),
                defaultLabel: 'Recordatorio de tarea CRM',
                icon: 'mdi mdi-format-list-checks',
                tone: 'warning',
            },
        ];

        if (newEventName) {
            channel.bind(newEventName, (data) => {
                const nombre = data?.full_name || data?.nombre || (data?.hc_number ? `HC ${data.hc_number}` : 'Paciente sin nombre');
                const prioridad = String(data?.prioridad || '').toUpperCase().trim();
                const urgente = prioridad === 'SI' || prioridad === 'SÍ' || prioridad === 'URGENTE' || prioridad === 'ALTA';
                const mensaje = `Nueva solicitud: ${nombre}`;

                panel.pushRealtime({
                    dedupeKey: `new-${data?.form_id ?? data?.secuencia ?? Date.now()}`,
                    title: nombre,
                    message: data?.procedimiento || data?.tipo || 'Nueva solicitud registrada',
                    meta: [
                        data?.doctor ? `Dr(a). ${data.doctor}` : '',
                        data?.afiliacion ? `Afiliación: ${data.afiliacion}` : '',
                    ].filter(Boolean),
                    badges: [
                        data?.tipo ? {label: data.tipo, variant: 'bg-primary text-white'} : null,
                        prioridad ? {label: `Prioridad ${prioridad}`, variant: urgente ? 'bg-danger text-white' : 'bg-success text-white'} : null,
                    ].filter(Boolean),
                    icon: urgente ? 'mdi mdi-alert-decagram-outline' : 'mdi mdi-flash',
                    tone: urgente ? 'danger' : 'info',
                    timestamp: new Date(),
                    channels: mapChannels(data?.channels),
                });

                showToast(mensaje, true, toastDurationMs);
                maybeShowDesktopNotification('Nueva solicitud', mensaje);
                scheduleRealtimeReload();
            });
        }

        if (statusEventName) {
            channel.bind(statusEventName, (data) => {
                const paciente = data?.full_name || (data?.hc_number ? `HC ${data.hc_number}` : `Solicitud #${data?.id ?? ''}`);
                const nuevoEstado = data?.estado || 'Actualizada';
                const estadoAnterior = data?.estado_anterior || 'Sin estado previo';
                const solicitudId = data?.id ?? data?.solicitud_id ?? '';

                panel.pushRealtime({
                    dedupeKey: `estado-${solicitudId || Date.now()}-${nuevoEstado}`,
                    title: paciente,
                    message: `Estado actualizado: ${estadoAnterior} → ${nuevoEstado}`,
                    meta: [
                        data?.procedimiento || '',
                        data?.doctor ? `Dr(a). ${data.doctor}` : '',
                        data?.afiliacion ? `Afiliación: ${data.afiliacion}` : '',
                    ].filter(Boolean),
                    badges: [
                        data?.prioridad ? {label: `Prioridad ${String(data.prioridad).toUpperCase()}`, variant: 'bg-secondary text-white'} : null,
                        nuevoEstado ? {label: nuevoEstado, variant: 'bg-warning text-dark'} : null,
                    ].filter(Boolean),
                    icon: 'mdi mdi-view-kanban',
                    tone: 'warning',
                    timestamp: new Date(),
                    channels: mapChannels(data?.channels),
                });

                const toastMessage = `${paciente}: ahora está en ${nuevoEstado}`;
                showToast(toastMessage, true, toastDurationMs);
                maybeShowDesktopNotification('Estado de solicitud', `${paciente} pasó a ${nuevoEstado}`);
                scheduleRealtimeReload();
            });
        }

        if (crmEventName) {
            channel.bind(crmEventName, (data) => {
                const paciente = data?.paciente_nombre || `Solicitud #${data?.solicitud_id ?? ''}`;
                const etapa = data?.pipeline_stage || 'Etapa actualizada';
                const responsable = data?.responsable_nombre || '';

                panel.pushRealtime({
                    dedupeKey: `crm-${data?.solicitud_id ?? Date.now()}-${etapa}-${responsable}`,
                    title: paciente,
                    message: `CRM actualizado · ${etapa}`,
                    meta: [
                        data?.procedimiento || '',
                        data?.doctor ? `Dr(a). ${data.doctor}` : '',
                        responsable ? `Responsable: ${responsable}` : '',
                        data?.fuente ? `Fuente: ${data.fuente}` : '',
                    ].filter(Boolean),
                    badges: etapa ? [{label: etapa, variant: 'bg-info text-white'}] : [],
                    icon: 'mdi mdi-account-cog-outline',
                    tone: 'info',
                    timestamp: new Date(),
                    channels: mapChannels(data?.channels),
                });

                showToast(`${paciente}: CRM actualizado`, true, toastDurationMs);
            });
        }

        if (whatsappHandoffEventName) {
            channel.bind(whatsappHandoffEventName, (data) => {
                const action = String(data?.handoff_action || 'requested').toLowerCase().trim();
                const contactName = data?.display_name || data?.patient_full_name || data?.wa_number || 'Contacto';
                const targetName = data?.target_user_name || data?.assigned_user_name || 'agente';
                const occurredAt = data?.occurred_at || data?.last_message_at || null;
                const messageByAction = {
                    assigned: `Asignado a ${targetName}`,
                    transferred: `Derivado a ${targetName}`,
                    requeued: 'Handoff reencolado',
                    resolved: 'Handoff resuelto',
                    requested: 'Se solicitó atención humana',
                };
                const badgeByAction = {
                    assigned: {label: 'Asignado', variant: 'bg-success text-white'},
                    transferred: {label: 'Derivado', variant: 'bg-info text-white'},
                    requeued: {label: 'Reencolado', variant: 'bg-warning text-dark'},
                    resolved: {label: 'Resuelto', variant: 'bg-primary text-white'},
                    requested: {label: 'Derivación', variant: 'bg-warning text-dark'},
                };

                panel.pushRealtime({
                    dedupeKey: `wa-handoff-${action}-${data?.conversation_id ?? '0'}-${data?.assigned_user_id ?? data?.target_user_id ?? '0'}-${occurredAt ?? Date.now()}`,
                    title: contactName,
                    message: messageByAction[action] || messageByAction.requested,
                    meta: [
                        data?.handoff_role_name ? `Equipo: ${data.handoff_role_name}` : '',
                        data?.actor_user_name ? `Acción por: ${data.actor_user_name}` : '',
                        data?.handoff_notes ? `Nota: ${String(data.handoff_notes).slice(0, 120)}` : '',
                    ].filter(Boolean),
                    badges: [badgeByAction[action] || badgeByAction.requested],
                    icon: 'mdi mdi-whatsapp',
                    tone: action === 'assigned'
                        ? 'success'
                        : action === 'resolved'
                            ? 'primary'
                            : action === 'transferred'
                                ? 'info'
                                : 'warning',
                    timestamp: occurredAt ? new Date(occurredAt) : new Date(),
                    channels: mapChannels(data?.channels),
                });
            });
        }

        const currentUserId = parseUserId(window?.MEDF?.currentUser?.id);
        const reminderBindings = new Set();
        reminderEvents.forEach((eventConfig) => {
            if (!eventConfig.eventName || reminderBindings.has(eventConfig.eventName)) {
                return;
            }
            reminderBindings.add(eventConfig.eventName);

            channel.bind(eventConfig.eventName, (rawData) => {
                const data = rawData || {};
                const assignedTo = parseUserId(data?.assigned_to);
                const audienceUserIds = Array.isArray(data?.audience_user_ids)
                    ? data.audience_user_ids.map((value) => parseUserId(value)).filter((value) => value !== null)
                    : [];
                const isAudienceUser = currentUserId !== null && audienceUserIds.includes(currentUserId);

                if (
                    eventConfig.key === 'crm_task'
                    && currentUserId !== null
                    && !isAudienceUser
                    && (assignedTo === null || assignedTo !== currentUserId)
                ) {
                    return;
                }

                const paciente = eventConfig.key === 'crm_task'
                    ? (data.assigned_name || data.full_name || 'Tarea CRM')
                    : (data.full_name || `Solicitud #${data.id ?? ''}`);
                const reminderLabel = data.reminder_label || eventConfig.defaultLabel;
                const reminderContext = data.reminder_context || '';
                const dueIso = data.remind_at || data.due_at || data.fecha_programada || null;
                const dueDate = parseLocalDate(dueIso);
                const dueLabel = dueDate ? dueDate.toLocaleString() : '';
                const fechaProgramada = parseLocalDate(data.fecha_programada);
                const examExpiry = parseLocalDate(data.exam_expires_at);
                const examLabel = examExpiry ? examExpiry.toLocaleDateString() : '';

                const meta = eventConfig.key === 'crm_task'
                    ? [
                        data.title ? `Tarea: ${data.title}` : '',
                        data.source_module ? `Módulo: ${String(data.source_module).toUpperCase()}` : '',
                        data.source_ref_id ? `Referencia: ${data.source_ref_id}` : '',
                        data.assigned_name ? `Responsable: ${data.assigned_name}` : '',
                        data.escalated ? 'Escalada a supervisión' : '',
                        data.task_url ? `Acceso: ${data.task_url}` : '',
                        reminderContext,
                    ].filter(Boolean)
                    : [
                        data.procedimiento || '',
                        data.doctor ? `Dr(a). ${data.doctor}` : '',
                        data.quirofano ? `Quirófano: ${data.quirofano}` : '',
                        data.prioridad ? `Prioridad: ${String(data.prioridad).toUpperCase()}` : '',
                        reminderContext,
                    ].filter(Boolean);

                if (eventConfig.key === 'exams' && examLabel) {
                    meta.push(`Vencen: ${examLabel}`);
                }

                panel.pushPending({
                    dedupeKey: `recordatorio-${eventConfig.key}-${data.task_id ?? data.id ?? Date.now()}-${data.reminder_id ?? dueIso ?? data.fecha_programada ?? ''}`,
                    title: paciente,
                    message: eventConfig.key === 'crm_task'
                        ? `${reminderLabel}${data.title ? ` · ${data.title}` : ''}`
                        : reminderLabel,
                    meta,
                    badges: dueLabel ? [{label: dueLabel, variant: 'bg-primary text-white'}] : [],
                    icon: eventConfig.icon,
                    tone: eventConfig.tone,
                    timestamp: new Date(),
                    dueAt: dueDate || fechaProgramada,
                    channels: mapChannels(data?.channels),
                });

                const toastLabel = reminderLabel || 'Recordatorio';
                const toastMessage = eventConfig.key === 'crm_task'
                    ? `${toastLabel}: ${data.title || 'Tarea CRM'}${dueLabel ? ` · ${dueLabel}` : ''}`
                    : (dueLabel
                        ? `${toastLabel}: ${paciente} · ${dueLabel}`
                        : `${toastLabel}: ${paciente}`);
                showToast(toastMessage, true, toastDurationMs);
                maybeShowDesktopNotification(toastLabel, toastMessage);
            });
        });
    };

    const parseDateRangeForReports = (filters) => {
        const from = String(filters.date_from || '').trim();
        const to = String(filters.date_to || '').trim();
        if (from && to) {
            return {from, to};
        }
        if (from) {
            return {from, to: from};
        }
        if (to) {
            return {from: to, to};
        }
        return {from: '', to: ''};
    };

    const buildReportPayload = (format) => {
        const filters = getFilters();
        const range = parseDateRangeForReports(filters);

        return {
            filters: {
                search: filters.search,
                doctor: filters.doctor,
                afiliacion: filters.afiliacion,
                sede: filters.sede,
                responsable_id: responsableSelect ? String(responsableSelect.value || '').trim() : '',
                date_from: range.from,
                date_to: range.to,
            },
            quickMetric: null,
            format,
        };
    };

    const extractFilename = (contentDisposition, fallbackName) => {
        if (!contentDisposition) {
            return fallbackName;
        }
        const match = contentDisposition.match(/filename="([^"]+)"/i);
        return match && match[1] ? match[1] : fallbackName;
    };

    const exportSolicitudes = async (format) => {
        if (state.exporting) {
            return;
        }

        const endpoint = format === 'excel' ? '/solicitudes/reportes/excel' : '/solicitudes/reportes/pdf';
        const expectedContentType = format === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/pdf';

        state.exporting = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': '*/*',
                },
                credentials: 'same-origin',
                body: JSON.stringify(buildReportPayload(format)),
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes(expectedContentType)) {
                let message = 'No se pudo generar el reporte.';
                try {
                    if (contentType.includes('application/json')) {
                        const payload = await response.json();
                        message = payload && payload.error ? payload.error : message;
                    } else {
                        const text = await response.text();
                        if (text) {
                            message = text;
                        }
                    }
                } catch (error) {
                    // Keep default message.
                }
                throw new Error(message);
            }

            const blob = await response.blob();
            const blobUrl = window.URL.createObjectURL(blob);

            if (format === 'pdf') {
                const opened = window.open(blobUrl, '_blank', 'noopener,noreferrer');
                if (!opened) {
                    const link = document.createElement('a');
                    link.href = blobUrl;
                    link.download = 'reporte_solicitudes.pdf';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            } else {
                const filename = extractFilename(
                    response.headers.get('content-disposition'),
                    'reporte_solicitudes.xlsx'
                );
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            window.setTimeout(() => {
                window.URL.revokeObjectURL(blobUrl);
            }, 10000);

            showToast(format === 'excel' ? 'Reporte Excel generado.' : 'Reporte PDF generado.', true);
        } catch (error) {
            showToast(error && error.message ? error.message : 'No se pudo exportar el reporte', false);
        } finally {
            state.exporting = false;
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
            syncResponsableOptions((state.options.crm && state.options.crm.responsables) || [], state.rows);
            state.filteredRows = applyLocalFilters(state.rows);
            renderMetrics((state.options.metrics || {}));
            renderBoard();
            renderTable(state.filteredRows);
            initTooltips();
            await syncCrmPanel();

            if (state.view === 'conciliacion') {
                const panel = await ensureConciliacionPanel();
                if (panel && typeof panel.reload === 'function') {
                    panel.reload();
                }
            }
        } catch (error) {
            state.filteredRows = [];
            renderTable([]);
            showToast(`No se pudo cargar solicitudes: ${error.message || error}`, false);
        } finally {
            state.loading = false;
        }
    };

    const rerenderFromLocalFilters = () => {
        state.filteredRows = applyLocalFilters(state.rows);
        renderMetrics((state.options.metrics || {}));
        renderBoard();
        renderTable(state.filteredRows);
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
            const panel = await ensureNotificationPanel();
            if (panel && typeof panel.pushRealtime === 'function') {
                panel.pushRealtime({
                    dedupeKey: `estado-${solicitudId}-${nextSlug}-${Date.now()}`,
                    title: 'Solicitud actualizada',
                    message: `Estado cambiado a ${nextSlug}`,
                    meta: [`Solicitud #${solicitudId}`],
                    icon: 'mdi mdi-view-kanban',
                    tone: 'success',
                    timestamp: new Date(),
                });
            }
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

    if (exportPdfButton) {
        exportPdfButton.addEventListener('click', () => {
            exportSolicitudes('pdf');
        });
    }

    if (exportExcelButton) {
        exportExcelButton.addEventListener('click', () => {
            exportSolicitudes('excel');
        });
    }

    [
        tipoSelect,
        responsableSelect,
        sinResponsableCheckbox,
        derivacionVencidaCheckbox,
        derivacionPorVencerCheckbox,
    ].forEach((element) => {
        if (!element) {
            return;
        }
        element.addEventListener('change', () => {
            rerenderFromLocalFilters();
        });
    });

    if (derivacionDiasInput) {
        derivacionDiasInput.addEventListener('input', () => {
            rerenderFromLocalFilters();
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
    initRealtimeNotifications();
    switchView(state.view, false);
    loadKanban();
})();
