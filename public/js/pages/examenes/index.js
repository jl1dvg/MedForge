import { poblarAfiliacionesUnicas, poblarDoctoresUnicos } from './kanban/filtros.js';
import { initKanban } from './kanban/index.js';
import { setCrmOptions } from './kanban/crmPanel.js';
import { showToast } from './kanban/toast.js';
import { createNotificationPanel } from './notifications/panel.js';
import { formatTurno } from './kanban/turnero.js';
import {
    getKanbanConfig,
    getDataStore,
    setDataStore,
    getEstadosMeta,
    getReportingConfig,
    setEstadosMeta,
    resolveAttr,
    resolveId,
    getTableBodySelector,
    getRealtimeConfig,
} from './kanban/config.js';

document.addEventListener('DOMContentLoaded', () => {
    const config = getKanbanConfig();
    const normalizedBasePath = config.basePath && config.basePath !== '/'
        ? config.basePath.replace(/\/+$/, '')
        : '';
    const realtimeConfig = getRealtimeConfig();
    const reportingConfig = getReportingConfig();
    const rawDesktopDismiss = Number(realtimeConfig.auto_dismiss_seconds);
    const desktopDismissSeconds = Number.isFinite(rawDesktopDismiss) && rawDesktopDismiss >= 0 ? rawDesktopDismiss : 0;
    const rawToastDismiss = Number(realtimeConfig.toast_auto_dismiss_seconds);
    const toastDismissSeconds = Number.isFinite(rawToastDismiss) && rawToastDismiss >= 0 ? rawToastDismiss : null;
    const toastDurationMs = toastDismissSeconds === null
        ? 4000
        : toastDismissSeconds === 0
            ? 0
            : toastDismissSeconds * 1000;
    const rawRetentionDays = Number(realtimeConfig.panel_retention_days);
    const retentionDays = Number.isFinite(rawRetentionDays) && rawRetentionDays >= 0 ? rawRetentionDays : 0;

    const ensureNotificationPanel = () => {
        if (window.MEDF?.notificationPanel) {
            return window.MEDF.notificationPanel;
        }

        const instance = createNotificationPanel({
            panelId: 'kanbanNotificationPanel',
            backdropId: 'notificationPanelBackdrop',
            toggleSelector: '[data-notification-panel-toggle]',
            storageKey: `${config.key}:notification-panel`,
            retentionDays,
        });

        window.MEDF = window.MEDF || {};
        window.MEDF.notificationPanel = instance;
        return instance;
    };

    const notificationPanel = ensureNotificationPanel();

    const defaultChannels = {
        ...(window.MEDF?.defaultNotificationChannels || {}),
        ...(realtimeConfig.channels || {}),
    };

    window.MEDF = window.MEDF || {};
    window.MEDF.defaultNotificationChannels = defaultChannels;
    window.MEDF.pusherIntegration = {
        enabled: Boolean(realtimeConfig.enabled),
        hasKey: Boolean(realtimeConfig.key),
    };

    notificationPanel.setChannelPreferences(defaultChannels);

    const mapChannels = (channels = {}) => {
        const merged = {
            email: channels.email ?? defaultChannels.email ?? false,
            sms: channels.sms ?? defaultChannels.sms ?? false,
            daily_summary: channels.daily_summary ?? defaultChannels.daily_summary ?? false,
        };

        const labels = [];
        if (merged.email) labels.push('Correo');
        if (merged.sms) labels.push('SMS');
        if (merged.daily_summary) labels.push('Resumen diario');
        return labels;
    };

    if (!realtimeConfig.enabled) {
        notificationPanel.setIntegrationWarning('Las notificaciones en tiempo real están desactivadas en Configuración → Notificaciones.');
    }

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

        const notification = new Notification(title, { body });
        if (desktopDismissSeconds && desktopDismissSeconds > 0) {
            setTimeout(() => notification.close(), desktopDismissSeconds * 1000);
        }
    };

    const estadosMeta = getEstadosMeta();
    // Estados que NO deben aparecer en el overview
    const OVERVIEW_EXCLUDED_STATES = new Set(['llamado', 'en-atencion']);
    const STORAGE_KEY_VIEW = config.storageKeyView;
    const viewAttr = resolveAttr('view');
    const viewButtons = Array.from(document.querySelectorAll(`[${viewAttr}]`));
    const kanbanContainer = document.getElementById(resolveId('ViewKanban'));
    const tableContainer = document.getElementById(resolveId('ViewTable'));
    const totalCounter = document.getElementById(resolveId('TotalCount'));
    const overviewContainer = document.getElementById(resolveId('Overview'));
    const tableBody = document.querySelector(getTableBodySelector());
    const tableEmptyState = document.getElementById(resolveId('TableEmpty'));
    const searchInput = document.getElementById('kanbanSearchFilter');
    const exportPdfButton = document.getElementById('examenesExportPdfButton');

    const VIEW_DEFAULT = 'kanban';
    let currentView = localStorage.getItem(STORAGE_KEY_VIEW) === 'table' ? 'table' : VIEW_DEFAULT;

    const normalizeFormats = (formats) => {
        if (!Array.isArray(formats)) {
            return ['pdf', 'excel'];
        }

        const normalized = formats
            .map(format => String(format).trim().toLowerCase())
            .filter(format => ['pdf', 'excel'].includes(format));

        return normalized.length ? Array.from(new Set(normalized)) : ['pdf', 'excel'];
    };

    const enabledFormats = normalizeFormats(reportingConfig?.formats);
    const quickMetricsConfig = reportingConfig?.quickMetrics && typeof reportingConfig.quickMetrics === 'object'
        ? reportingConfig.quickMetrics
        : {};

    const resolveQuickMetric = (metric) => {
        if (!metric) {
            return '';
        }
        if (Object.keys(quickMetricsConfig).length === 0) {
            return metric;
        }
        return quickMetricsConfig[metric] ? metric : '';
    };

    const ESCAPE_MAP = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '`': '&#96;',
    };

    const escapeHtml = (value) => {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).replace(/[&<>"'`]/g, character => ESCAPE_MAP[character]);
    };

    const resolveExamenNombre = (payload = {}) => {
        const canonical = (payload?.examen_nombre ?? '').toString().trim();
        if (canonical !== '') {
            return canonical;
        }

        return (payload?.examen ?? '').toString().trim();
    };

    const normalizeEstado = (value) => {
        return (value ?? '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-');
    };

    const calcularDias = (fechaIso) => {
        if (!fechaIso) {
            return 0;
        }

        const fecha = new Date(fechaIso);
        if (Number.isNaN(fecha.getTime())) {
            return 0;
        }

        const hoy = new Date();
        const diff = hoy - fecha;

        return Math.max(0, Math.floor(diff / (1000 * 60 * 60 * 24)));
    };

    const obtenerSemaforo = (dias) => {
        if (dias <= 3) {
            return { label: 'Normal', badgeClass: 'text-bg-success' };
        }

        if (dias <= 7) {
            return { label: 'Pendiente', badgeClass: 'text-bg-warning text-dark' };
        }

        return { label: 'Urgente', badgeClass: 'text-bg-danger' };
    };

    const getInitials = (nombre) => {
        if (!nombre) {
            return '—';
        }

        const parts = nombre
            .replace(/\s+/g, ' ')
            .trim()
            .split(' ')
            .filter(Boolean);

        if (!parts.length) {
            return '—';
        }

        if (parts.length === 1) {
            return parts[0].substring(0, 2).toUpperCase();
        }

        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    };

    const renderResponsableAvatar = (nombre, avatarUrl) => {
        if (avatarUrl) {
            return `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(nombre || 'Responsable')}" class="table-avatar">`;
        }

        return `<span class="table-avatar-placeholder">${escapeHtml(getInitials(nombre || ''))}</span>`;
    };

    const aplicarFiltrosLocales = (data) => {
        const term = (searchInput?.value || '').trim().toLowerCase();
        if (!term) {
            return Array.isArray(data) ? [...data] : [];
        }

        const keys = ['full_name', 'hc_number', 'examen_nombre', 'doctor', 'afiliacion', 'estado', 'crm_pipeline_stage'];

        return (Array.isArray(data) ? data : []).filter(item =>
            (resolveExamenNombre(item).toLowerCase().includes(term))
            || keys.some(key => {
                const value = item?.[key];
                return value && value.toString().toLowerCase().includes(term);
            })
        );
    };

    const createOverviewCard = ({ title, count, badge, badgeClass = 'text-bg-secondary', subtitle, metricKey }) => {
        const metricAttr = metricKey ? ` data-metric-key="${escapeHtml(metricKey)}"` : '';
        const actionClass = metricKey ? ' overview-card-actionable' : '';
        const icon = metricKey ? '<span class="overview-card-action" aria-hidden="true"><i class="mdi mdi-file-pdf-box"></i></span>' : '';
        return `
            <div class="overview-card${actionClass}"${metricAttr}>
                <h6>${escapeHtml(title)}</h6>
                <div class="d-flex justify-content-between align-items-end">
                    <span class="count">${escapeHtml(String(count))}</span>
                    ${badge ? `<span class="badge ${escapeHtml(badgeClass)}">${escapeHtml(badge)}</span>` : ''}
                </div>
                ${subtitle ? `<div class="meta">${escapeHtml(subtitle)}</div>` : ''}
                ${icon}
            </div>
        `;
    };

    const updateOverview = (data) => {
        if (!overviewContainer) {
            return;
        }

        const total = Array.isArray(data) ? data.length : 0;
        if (totalCounter) {
            totalCounter.textContent = total;
        }

        const counts = {};
        let urgentes = 0;
        let pendientes = 0;

        (Array.isArray(data) ? data : []).forEach(item => {
            const slug = normalizeEstado(item?.estado);
            counts[slug] = (counts[slug] || 0) + 1;

            const dias = calcularDias(item?.fecha);

            if (dias > 7) {
                urgentes += 1;
            } else if (dias >= 4) {
                pendientes += 1;
            }
        });

        const cards = [];

        cards.push(createOverviewCard({
            title: 'Total de examenes',
            count: total,
            badge: total ? `${Math.round(((urgentes || 0) / (total || 1)) * 100)}% urgentes` : null,
            badgeClass: 'text-bg-primary',
            subtitle: total ? `${pendientes} pendientes · ${urgentes} urgentes` : 'No hay examenes registradas',
        }));

        cards.push(createOverviewCard({
            title: 'Urgentes (>7 días)',
            count: urgentes,
            badge: total ? `${Math.round((urgentes / (total || 1)) * 100)}%` : null,
            badgeClass: 'text-bg-danger',
            subtitle: urgentes ? 'Priorizar seguimiento' : 'Sin urgencias activas',
        }));

        cards.push(createOverviewCard({
            title: 'Pendientes (4–7 días)',
            count: pendientes,
            badge: total ? `${Math.round((pendientes / (total || 1)) * 100)}%` : null,
            badgeClass: 'text-bg-warning text-dark',
            subtitle: pendientes ? 'Revisar avances y documentación' : 'Sin pendientes en este rango',
        }));

        const metricKeyBySlug = Object.entries(quickMetricsConfig).reduce((acc, [key, metricConfig]) => {
            if (metricConfig?.estado) {
                acc[normalizeEstado(metricConfig.estado)] = key;
            }
            return acc;
        }, {});

        Object.entries(estadosMeta).forEach(([slug, meta]) => {
            // Omitir estados excluidos en el overview
            if (OVERVIEW_EXCLUDED_STATES.has(slug)) return;
            const count = counts[slug] || 0;
            const porcentaje = total ? Math.round((count / total) * 100) : 0;
            cards.push(createOverviewCard({
                title: meta?.label ?? slug,
                count,
                badge: `${porcentaje}%`,
                badgeClass: `text-bg-${escapeHtml(meta?.color || 'secondary')}`,
                subtitle: count ? 'Exámenes en esta etapa' : 'Sin tarjetas en la columna',
                metricKey: metricKeyBySlug[slug],
            }));
        });

        overviewContainer.innerHTML = cards.join('');
    };

    const renderTable = (data) => {
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';

        const rows = Array.isArray(data) ? data : [];
        if (!rows.length) {
            if (tableEmptyState) {
                tableEmptyState.classList.remove('d-none');
            }
            return;
        }

        if (tableEmptyState) {
            tableEmptyState.classList.add('d-none');
        }

        const fragment = document.createDocumentFragment();

        rows.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.prefacturaTrigger = 'table';
            tr.dataset.hc = item?.hc_number ?? '';
            tr.dataset.form = item?.form_id ?? '';
            tr.dataset.id = item?.id ?? '';
            tr.dataset.pacienteNombre = item?.full_name ?? '';

            const dias = calcularDias(item?.fecha);
            const semaforo = obtenerSemaforo(dias);
            const turno = formatTurno(item?.turno) || '';
            const pipeline = item?.crm_pipeline_stage || 'Recibido';
            const fuente = item?.crm_fuente || '';
            const responsable = item?.crm_responsable_nombre || 'Sin responsable asignado';
            const avatarHtml = renderResponsableAvatar(responsable, item?.crm_responsable_avatar);
            const prioridadLabel = item?.prioridad || semaforo.label;

            const detalleProcedimiento = resolveExamenNombre(item) || 'Sin examen';
            const detalleDoctor = item?.doctor || 'Sin doctor';
            const detalleAfiliacion = item?.afiliacion || 'Sin afiliación';

            tr.innerHTML = `
                <td>
                    <div class="fw-semibold">${escapeHtml(item?.full_name ?? 'Paciente sin nombre')}</div>
                    <div class="text-muted small">HC ${escapeHtml(item?.hc_number ?? '—')}</div>
                </td>
                <td>
                    <div class="small text-muted">${escapeHtml(detalleProcedimiento)}</div>
                    <div class="small text-muted">${escapeHtml(detalleDoctor)}</div>
                    <div class="small text-muted">${escapeHtml(detalleAfiliacion)}</div>
                </td>
                <td>
                    <span class="badge text-bg-light text-dark">${escapeHtml(item?.estado || 'Sin estado')}</span>
                </td>
                <td>
                    <div class="fw-semibold small">${escapeHtml(pipeline)}</div>
                    ${fuente ? `<div class="text-muted small">${escapeHtml(fuente)}</div>` : ''}
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        ${avatarHtml}
                        <div>
                            <div class="fw-semibold small">${escapeHtml(responsable)}</div>
                            <div class="text-muted small">${escapeHtml(item?.crm_contacto_email || item?.crm_contacto_telefono || '')}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge ${escapeHtml(semaforo.badgeClass)}">${escapeHtml(prioridadLabel)}</span>
                    <div class="text-muted small">${escapeHtml(String(dias))} día(s)</div>
                </td>
                <td>
                    ${turno ? `<span class="badge text-bg-info text-dark">#${escapeHtml(turno)}</span>` : '<span class="text-muted">—</span>'}
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-prefactura-trigger="button" data-hc="${escapeHtml(item?.hc_number ?? '')}" data-form="${escapeHtml(item?.form_id ?? '')}">
                            <i class="mdi mdi-eye-outline"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-open-crm" data-examen-id="${escapeHtml(item?.id ?? '')}" data-paciente-nombre="${escapeHtml(item?.full_name ?? '')}">
                            <i class="mdi mdi-account-box-outline"></i>
                        </button>
                    </div>
                </td>
            `;

            fragment.appendChild(tr);
        });

        tableBody.appendChild(fragment);
    };

    const switchView = (view, persist = true) => {
        const normalized = view === 'table' ? 'table' : VIEW_DEFAULT;
        currentView = normalized;

        if (kanbanContainer) {
            kanbanContainer.classList.toggle('d-none', normalized === 'table');
        }

        if (tableContainer) {
            tableContainer.classList.toggle('d-none', normalized !== 'table');
        }

        viewButtons.forEach(button => {
            const buttonView = button.getAttribute(viewAttr) === 'table' ? 'table' : VIEW_DEFAULT;
            button.classList.toggle('active', buttonView === normalized);
        });

        if (persist) {
            localStorage.setItem(STORAGE_KEY_VIEW, normalized);
        }
    };

    const renderFromCache = () => {
        const baseData = getDataStore();
        const filtradas = aplicarFiltrosLocales(baseData);

        updateOverview(filtradas);
        renderTable(filtradas);
        initKanban(filtradas);
        switchView(currentView, false);
    };

    viewButtons.forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            const view = button.getAttribute(viewAttr);
            switchView(view);
        });
    });

    if (overviewContainer) {
        overviewContainer.addEventListener('click', event => {
            const target = event.target.closest('.overview-card-actionable[data-metric-key]');
            if (!target || !overviewContainer.contains(target)) {
                return;
            }

            const metricKey = target.dataset.metricKey || '';
            const title = target.querySelector('h6')?.textContent?.trim() || 'Exportar reporte';
            openExportModal({ quickMetric: metricKey, title: `Quick report: ${title}` });
        });
    }

    if (exportPdfButton) {
        exportPdfButton.addEventListener('click', () => {
            openExportModal({ title: 'Exportar reporte de exámenes' });
        });
    }

    switchView(currentView, false);

    let searchDebounce = null;
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                renderFromCache();
            }, 220);
        });
    }

    const obtenerFiltros = () => ({
        afiliacion: document.getElementById('kanbanAfiliacionFilter')?.value ?? '',
        doctor: document.getElementById('kanbanDoctorFilter')?.value ?? '',
        prioridad: document.getElementById('kanbanSemaforoFilter')?.value ?? '',
        fechaTexto: document.getElementById('kanbanDateFilter')?.value ?? '',
        search: searchInput?.value ?? '',
    });

    const normalizeDatePart = (value) => {
        if (!value) return '';
        const trimmed = value.trim();
        const match = trimmed.match(/^(\d{2})-(\d{2})-(\d{4})$/);
        if (match) {
            return `${match[3]}-${match[2]}-${match[1]}`;
        }
        return trimmed;
    };

    const parseDateRange = (rangeText) => {
        if (!rangeText || !rangeText.includes(' - ')) {
            const single = normalizeDatePart(rangeText || '');
            return { from: single || '', to: single || '' };
        }
        const [from, to] = rangeText.split(' - ');
        return {
            from: normalizeDatePart(from),
            to: normalizeDatePart(to),
        };
    };

    const buildReportPayload = ({ quickMetric = '', format = 'pdf' } = {}) => {
        const filtros = obtenerFiltros();
        const range = parseDateRange(filtros.fechaTexto);

        return {
            filters: {
                search: filtros.search,
                doctor: filtros.doctor,
                afiliacion: filtros.afiliacion,
                prioridad: filtros.prioridad,
                date_from: range.from,
                date_to: range.to,
            },
            quickMetric: quickMetric || null,
            format,
        };
    };

    const extractFilename = (contentDisposition, fallbackName) => {
        if (!contentDisposition) {
            return fallbackName;
        }
        const match = contentDisposition.match(/filename="([^"]+)"/i);
        return match?.[1] || fallbackName;
    };

    const exportExamenesPdf = async ({ quickMetric = '' } = {}) => {
        const payload = buildReportPayload({ quickMetric, format: 'pdf' });
        const reportBasePath = normalizedBasePath || '/examenes';
        const response = await fetch(`${reportBasePath}/reportes/pdf`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const contentType = response.headers.get('content-type') || '';

        if (!response.ok || !contentType.includes('application/pdf')) {
            let mensaje = 'No se pudo generar el reporte.';
            try {
                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data?.error) {
                        mensaje = data.error;
                    }
                } else {
                    const text = await response.text();
                    if (text) {
                        mensaje = text;
                    }
                }
            } catch (error) {
                try {
                    const text = await response.text();
                    if (text) {
                        mensaje = text;
                    }
                } catch (_) {
                    mensaje = 'No se pudo generar el reporte.';
                }
            }
            throw new Error(mensaje);
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const opened = window.open(blobUrl, '_blank', 'noopener,noreferrer');
        if (!opened) {
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = 'reporte_examenes.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        setTimeout(() => window.URL.revokeObjectURL(blobUrl), 10000);
    };

    const exportExamenesExcel = async ({ quickMetric = '' } = {}) => {
        const payload = buildReportPayload({ quickMetric, format: 'excel' });
        const reportBasePath = normalizedBasePath || '/examenes';
        const response = await fetch(`${reportBasePath}/reportes/excel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const contentType = response.headers.get('content-type') || '';

        if (!response.ok || !contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
            let mensaje = 'No se pudo generar el reporte.';
            try {
                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data?.error) {
                        mensaje = data.error;
                    }
                } else {
                    const text = await response.text();
                    if (text) {
                        mensaje = text;
                    }
                }
            } catch (error) {
                try {
                    const text = await response.text();
                    if (text) {
                        mensaje = text;
                    }
                } catch (_) {
                    mensaje = 'No se pudo generar el reporte.';
                }
            }
            throw new Error(mensaje);
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const filename = extractFilename(
            response.headers.get('content-disposition'),
            'reporte_examenes.xlsx'
        );

        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => window.URL.revokeObjectURL(blobUrl), 10000);
    };

    const openExportModal = ({ quickMetric = '', title = 'Exportar reporte' } = {}) => {
        const resolvedQuickMetric = resolveQuickMetric(quickMetric);
        const formats = enabledFormats;

        if (typeof Swal === 'undefined') {
            if (formats.length === 1) {
                const format = formats[0];
                if (window.confirm(`${title}\n¿Generar ${format.toUpperCase()} con los filtros actuales?`)) {
                    if (format === 'excel') {
                        exportExamenesExcel({ quickMetric: resolvedQuickMetric })
                            .catch(error => showToast(error?.message || 'No se pudo generar el Excel', false));
                    } else {
                        exportExamenesPdf({ quickMetric: resolvedQuickMetric })
                            .catch(error => showToast(error?.message || 'No se pudo generar el PDF', false));
                    }
                }
                return;
            }

            if (window.confirm(`${title}\n¿Generar reporte con los filtros actuales?`)) {
                const chosen = window.prompt(`Formato (${formats.join('/')}):`, formats[0] || 'pdf');
                const format = (chosen || '').toLowerCase();
                if (!formats.includes(format)) {
                    showToast('Formato no habilitado.', false);
                    return;
                }
                if (format === 'excel') {
                    exportExamenesExcel({ quickMetric: resolvedQuickMetric })
                        .catch(error => showToast(error?.message || 'No se pudo generar el Excel', false));
                } else {
                    exportExamenesPdf({ quickMetric: resolvedQuickMetric })
                        .catch(error => showToast(error?.message || 'No se pudo generar el PDF', false));
                }
            }
            return;
        }

        const formatOptions = formats.map((format, index) => `
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="exportFormat" id="exportFormat-${format}" value="${format}" ${index === 0 ? 'checked' : ''}>
                <label class="form-check-label" for="exportFormat-${format}">
                    ${format === 'excel' ? 'Excel (.xlsx)' : 'PDF (tabla)'}
                </label>
            </div>
        `).join('');

        Swal.fire({
            title,
            html: `
                <div class="text-start">
                    ${formatOptions}
                    <hr class="my-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="exportUseFilters" checked disabled>
                        <label class="form-check-label" for="exportUseFilters">Usar filtros actuales</label>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Exportar',
            cancelButtonText: 'Cancelar',
            width: 420,
            focusConfirm: false,
            preConfirm: () => {
                const selected = document.querySelector('input[name="exportFormat"]:checked');
                return selected ? selected.value : (formats[0] || 'pdf');
            },
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            const format = result.value || (formats[0] || 'pdf');
            if (!formats.includes(format)) {
                showToast('Formato no habilitado.', false);
                return;
            }
            if (format === 'excel') {
                exportExamenesExcel({ quickMetric: resolvedQuickMetric })
                    .then(() => showToast('Reporte Excel generado.', true))
                    .catch(error => showToast(error?.message || 'No se pudo generar el Excel', false));
                return;
            }

            exportExamenesPdf({ quickMetric: resolvedQuickMetric })
                .then(() => showToast('Reporte PDF generado.', true))
                .catch(error => showToast(error?.message || 'No se pudo generar el PDF', false));
        });
    };

    const cargarKanban = (filtros = {}) => {
        console.groupCollapsed('%cKANBAN ▶ Filtros aplicados', 'color:#0b7285');
        console.log(filtros);
        console.groupEnd();

        return fetch('/examenes/kanban-data', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros),
        })
            .then(async (response) => {
                if (!response.ok) {
                    let serverMsg = '';
                    try {
                        const data = await response.json();
                        serverMsg = data?.error || JSON.stringify(data);
                    } catch (_) {
                        serverMsg = await response.text();
                    }
                    const msg = serverMsg ? `No se pudo cargar el tablero. Servidor: ${serverMsg}` : 'No se pudo cargar el tablero';
                    throw new Error(msg);
                }
                return response.json();
            })
            .then(({ data = [], options = {} }) => {
                const store = setDataStore(Array.isArray(data) ? data : []);

                if (options.kanban_columns) {
                    const meta = {};
                    Object.entries(options.kanban_columns).forEach(([slug, metaItem]) => {
                        meta[slug] = {
                            slug,
                            label: metaItem?.label || slug,
                            color: metaItem?.color || 'secondary',
                        };
                    });
                    setEstadosMeta(meta);
                }

                if (options.afiliaciones) {
                    poblarAfiliacionesUnicas(options.afiliaciones);
                } else {
                    poblarAfiliacionesUnicas(store);
                }

                if (options.doctores) {
                    poblarDoctoresUnicos(options.doctores);
                } else {
                    poblarDoctoresUnicos(store);
                }

                if (options.crm) {
                    setCrmOptions(options.crm);
                } else {
                    setCrmOptions({});
                }

                renderFromCache();
            })
            .catch(error => {
                console.error('❌ Error cargando Kanban:', error);
                showToast(error?.message || 'No se pudo cargar el tablero de examenes', false);
            });
    };

    window.aplicarFiltros = () => cargarKanban(obtenerFiltros());

    ['kanbanAfiliacionFilter', 'kanbanDoctorFilter', 'kanbanSemaforoFilter'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', () => window.aplicarFiltros());
        }
    });

    if (typeof $ !== 'undefined' && typeof $.fn.daterangepicker === 'function') {
        $('#kanbanDateFilter')
            .daterangepicker({
                locale: {
                    format: 'DD-MM-YYYY',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                },
                autoUpdateInput: false,
            })
            .on('apply.daterangepicker', function (ev, picker) {
                this.value = `${picker.startDate.format('DD-MM-YYYY')} - ${picker.endDate.format('DD-MM-YYYY')}`;
                window.aplicarFiltros();
            })
            .on('cancel.daterangepicker', function () {
                this.value = '';
                window.aplicarFiltros();
            });
    }

    if (realtimeConfig.enabled) {
        if (typeof Pusher === 'undefined') {
            notificationPanel.setIntegrationWarning('Pusher no está disponible. Verifica que el script se haya cargado correctamente.');
            console.warn('Pusher no está disponible. Verifica que el script se haya cargado correctamente.');
        } else if (!realtimeConfig.key) {
            notificationPanel.setIntegrationWarning('No se configuró la APP Key de Pusher en los ajustes.');
            console.warn('No se configuró la APP Key de Pusher.');
        } else {
            const options = { forceTLS: true };
            if (realtimeConfig.cluster) {
                options.cluster = realtimeConfig.cluster;
            }

            const pusher = new Pusher(realtimeConfig.key, options);
            const channelName = realtimeConfig.channel || 'examenes-kanban';
            const events = realtimeConfig.events || {};
            const newEventName = events.new_request || realtimeConfig.event || 'kanban.nueva-examen';
            const statusEventName = events.status_updated || 'kanban.estado-actualizado';
            const crmEventName = events.crm_updated || 'crm.detalles-actualizados';
            const reminderEventName = events.exam_reminder
                || events.surgery_reminder
                || 'recordatorio-examen';

            notificationPanel.setIntegrationWarning('');

            const channel = pusher.subscribe(channelName);

            channel.bind(newEventName, data => {
                const nombre = data?.full_name || data?.nombre || (data?.hc_number ? `HC ${data.hc_number}` : 'Paciente sin nombre');
                const prioridad = String(data?.prioridad ?? '').toUpperCase();
                const urgente = prioridad === 'SI' || prioridad === 'URGENTE' || prioridad === 'ALTA';
                const mensaje = `🆕 Nuevo examen: ${nombre}`;

                notificationPanel.pushRealtime({
                    dedupeKey: `new-${data?.form_id ?? data?.secuencia ?? Date.now()}`,
                    title: nombre,
                    message: resolveExamenNombre(data) || data?.tipo || 'Nuevo examen registrado',
                    meta: [
                        data?.doctor ? `Dr(a). ${data.doctor}` : '',
                        data?.afiliacion ? `Afiliación: ${data.afiliacion}` : '',
                    ],
                    badges: [
                        data?.tipo ? { label: data.tipo, variant: 'bg-primary text-white' } : null,
                        prioridad ? { label: `Prioridad ${prioridad}`, variant: urgente ? 'bg-danger text-white' : 'bg-success text-white' } : null,
                    ].filter(Boolean),
                    icon: urgente ? 'mdi mdi-alert-decagram-outline' : 'mdi mdi-flash',
                    tone: urgente ? 'danger' : 'info',
                    timestamp: new Date(),
                    channels: mapChannels(data?.channels),
                });

                showToast(mensaje, true, toastDurationMs);
                maybeShowDesktopNotification('Nuevo examen', mensaje);
                window.aplicarFiltros();
            });

            if (statusEventName) {
                channel.bind(statusEventName, data => {
                    const paciente = data?.full_name || (data?.hc_number ? `HC ${data.hc_number}` : `Examen #${data?.id ?? ''}`);
                    const nuevoEstado = data?.estado || 'Actualizada';
                    const estadoAnterior = data?.estado_anterior || 'Sin estado previo';

                    notificationPanel.pushRealtime({
                        dedupeKey: `estado-${data?.id ?? Date.now()}-${nuevoEstado}`,
                        title: paciente,
                        message: `Estado actualizado: ${estadoAnterior} → ${nuevoEstado}`,
                        meta: [
                            resolveExamenNombre(data) || '',
                            data?.doctor ? `Dr(a). ${data.doctor}` : '',
                            data?.afiliacion ? `Afiliación: ${data.afiliacion}` : '',
                        ],
                        badges: [
                            data?.prioridad ? { label: `Prioridad ${String(data.prioridad).toUpperCase()}`, variant: 'bg-secondary text-white' } : null,
                            nuevoEstado ? { label: nuevoEstado, variant: 'bg-warning text-dark' } : null,
                        ].filter(Boolean),
                        icon: 'mdi mdi-view-kanban',
                        tone: 'warning',
                        timestamp: new Date(),
                        channels: mapChannels(data?.channels),
                    });

                    showToast(`📌 ${paciente}: ahora está en ${nuevoEstado}`, true, toastDurationMs);
                    maybeShowDesktopNotification('Estado de examen', `${paciente} pasó a ${nuevoEstado}`);
                    window.aplicarFiltros();
                });
            }

            if (crmEventName) {
                channel.bind(crmEventName, data => {
                    const paciente = data?.paciente_nombre || `Examen #${data?.examen_id ?? ''}`;
                    const etapa = data?.pipeline_stage || 'Etapa actualizada';
                    const responsable = data?.responsable_nombre || '';

                    notificationPanel.pushRealtime({
                        dedupeKey: `crm-${data?.examen_id ?? Date.now()}-${etapa}-${responsable}`,
                        title: paciente,
                        message: `CRM actualizado · ${etapa}`,
                        meta: [
                            resolveExamenNombre(data) || '',
                            data?.doctor ? `Dr(a). ${data.doctor}` : '',
                            responsable ? `Responsable: ${responsable}` : '',
                            data?.fuente ? `Fuente: ${data.fuente}` : '',
                        ],
                        badges: [
                            etapa ? { label: etapa, variant: 'bg-info text-white' } : null,
                        ].filter(Boolean),
                        icon: 'mdi mdi-account-cog-outline',
                        tone: 'info',
                        timestamp: new Date(),
                        channels: mapChannels(data?.channels),
                    });

                    showToast(`🤝 ${paciente}: CRM actualizado`, true, toastDurationMs);
                });
            }

            if (reminderEventName) {
                channel.bind(reminderEventName, data => {
                    const paciente = data?.full_name || `Examen #${data?.id ?? ''}`;
                    const fechaProgramada = data?.fecha_programada ? new Date(data.fecha_programada) : null;
                    const fechaTexto = fechaProgramada && !Number.isNaN(fechaProgramada.getTime())
                        ? fechaProgramada.toLocaleString()
                        : '';

                    notificationPanel.pushPending({
                        dedupeKey: `recordatorio-${data?.id ?? Date.now()}-${data?.fecha_programada ?? ''}`,
                        title: paciente,
                        message: 'Recordatorio de cirugía',
                        meta: [
                            resolveExamenNombre(data) || '',
                            data?.doctor ? `Dr(a). ${data.doctor}` : '',
                            data?.quirofano ? `Quirófano: ${data.quirofano}` : '',
                            data?.prioridad ? `Prioridad: ${String(data.prioridad).toUpperCase()}` : '',
                        ],
                        badges: [
                            fechaTexto ? { label: fechaTexto, variant: 'bg-primary text-white' } : null,
                        ].filter(Boolean),
                        icon: 'mdi mdi-alarm-check',
                        tone: 'primary',
                        timestamp: new Date(),
                        dueAt: fechaProgramada,
                        channels: mapChannels(data?.channels),
                    });

                    const mensaje = fechaTexto ? `⏰ Cirugía ${paciente} · ${fechaTexto}` : `⏰ Cirugía ${paciente}`;
                    showToast(mensaje, true, toastDurationMs);
                    maybeShowDesktopNotification('Recordatorio de cirugía', mensaje);
                });
            }
        }
    }

    cargarKanban();
});
