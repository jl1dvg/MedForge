import { poblarAfiliacionesUnicas, poblarDoctoresUnicos } from './kanban/filtros.js';
import { initKanban } from './kanban/index.js';
import { updateKanbanCardSla } from './kanban/renderer.js';
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
    const normalizedApiBasePath = config.apiBasePath && config.apiBasePath !== '/'
        ? config.apiBasePath.replace(/\/+$/, '')
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

    const buildEstadoApiCandidates = () => {
        const candidates = [
            '/solicitudes/api/estado',
            '/api/solicitudes/estado',
        ];

        if (normalizedApiBasePath) {
            candidates.push(`${normalizedApiBasePath}/solicitudes/estado`);
        }

        if (normalizedBasePath) {
            candidates.push(`${normalizedBasePath}/api/estado`);
        }

        return Array.from(new Set(candidates));
    };

    const fetchDetalleSolicitud = async ({ solicitudId, formId, hcNumber }) => {
        if (!hcNumber) {
            throw new Error('No se puede solicitar detalle sin HC');
        }

        const searchParams = new URLSearchParams({ hcNumber });
        if (formId) {
            searchParams.set('form_id', formId);
        }

        const candidates = buildEstadoApiCandidates();
        let lastError = null;

        for (const base of candidates) {
            try {
                const response = await fetch(`${base}?${searchParams.toString()}`, {
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    lastError = new Error(`HTTP ${response.status}`);
                    continue;
                }
                const payload = await response.json();
                const lista = Array.isArray(payload?.solicitudes) ? payload.solicitudes : [];
                const detalle = lista.find(item =>
                    String(item.id) === String(solicitudId)
                    || String(item.form_id) === String(formId)
                );
                if (!detalle) {
                    throw new Error('No se encontró información de la solicitud');
                }
                return detalle;
            } catch (error) {
                lastError = error;
            }
        }

        throw lastError || new Error('No se pudo completar la solicitud');
    };

    const refreshSolicitudFromBackend = async ({ solicitudId, formId, hcNumber }) => {
        if (!solicitudId && !formId && !hcNumber) {
            return;
        }

        try {
            const detalle = await fetchDetalleSolicitud({ solicitudId, formId, hcNumber });
            const store = getDataStore();
            const target = Array.isArray(store)
                ? store.find(item => String(item.id) === String(solicitudId))
                : null;
            if (target && typeof target === 'object') {
                Object.assign(target, detalle);
            }
            updateKanbanCardSla(detalle);
        } catch (error) {
            console.warn('No se pudo refrescar SLA desde backend', error);
        }
    };

    const realtimeRefreshTimers = new Map();
    const scheduleRealtimeRefresh = ({ solicitudId, formId, hcNumber }) => {
        const key = solicitudId ? String(solicitudId) : formId ? `form-${formId}` : `hc-${hcNumber}`;
        if (!key) {
            return;
        }
        if (realtimeRefreshTimers.has(key)) {
            clearTimeout(realtimeRefreshTimers.get(key));
        }
        realtimeRefreshTimers.set(
            key,
            setTimeout(() => {
                realtimeRefreshTimers.delete(key);
                refreshSolicitudFromBackend({ solicitudId, formId, hcNumber });
            }, 350)
        );
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
    window.__solicitudesMetrics = window.__solicitudesMetrics || null;
    // Estados que NO deben aparecer en el overview
    const OVERVIEW_EXCLUDED_STATES = new Set(['llamado', 'en-atencion']);
    const DEFAULT_VISIBLE_KEYS = [
        'total-de-solicitudes',
        'sla-vencido',
        'cobertura',
        'oftalmologo',
        'anestesia',
        'listo',
    ];
    const OVERVIEW_STORAGE_KEY = 'solicitudes:overview:expanded';
    const OVERVIEW_TOGGLE_ID = 'solicitudesOverviewToggle';
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
    const exportPdfButton = document.getElementById('solicitudesExportPdfButton');
    const dateFilter = document.getElementById('kanbanDateFilter');

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

    const normalizeEstado = (value) => {
        return (value ?? '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-');
    };

    const EMOJI_REGEX = (() => {
        try {
            return new RegExp('[\\u{1F300}-\\u{1FAFF}]', 'gu');
        } catch (error) {
            return null;
        }
    })();

    const normalizeKey = (text) => {
        return (text ?? '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(EMOJI_REGEX || /$^/, '')
            .replace(/[^a-z0-9\s-]/gi, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '-');
    };

    const getOverviewCards = () => {
        if (!overviewContainer) {
            return [];
        }

        return Array.from(overviewContainer.querySelectorAll('.overview-card'));
    };

    const readOverviewExpanded = () => {
        try {
            return sessionStorage.getItem(OVERVIEW_STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    };

    const writeOverviewExpanded = (expanded) => {
        try {
            sessionStorage.setItem(OVERVIEW_STORAGE_KEY, expanded ? '1' : '0');
        } catch (error) {
            // Ignorar fallas de almacenamiento local
        }
    };

    const updateOverviewToggleText = (button, expanded) => {
        if (!button) {
            return;
        }

        button.textContent = expanded ? 'Ocultar métricas detalladas' : 'Ver métricas detalladas';
    };

    const applyOverviewVisibility = (forceExpanded) => {
        if (!overviewContainer) {
            return;
        }

        const cards = getOverviewCards();
        if (!cards.length) {
            return;
        }

        const expanded = typeof forceExpanded === 'boolean' ? forceExpanded : readOverviewExpanded();

        cards.forEach(card => {
            const title = card.querySelector('h6')?.textContent || '';
            const key = normalizeKey(title);

            if (key) {
                card.dataset.metricKey = key;
            }

            if (expanded || DEFAULT_VISIBLE_KEYS.includes(key)) {
                card.classList.remove('d-none');
            } else {
                card.classList.add('d-none');
            }
        });

        updateOverviewToggleText(document.getElementById(OVERVIEW_TOGGLE_ID), expanded);
    };

    const ensureOverviewToggle = () => {
        if (!overviewContainer) {
            return;
        }

        const cards = getOverviewCards();
        if (!cards.length) {
            return;
        }

        const existing = document.getElementById(OVERVIEW_TOGGLE_ID);
        if (existing) {
            updateOverviewToggleText(existing, readOverviewExpanded());
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex justify-content-end mb-2';

        const button = document.createElement('button');
        button.type = 'button';
        button.id = OVERVIEW_TOGGLE_ID;
        button.className = 'btn btn-outline-primary btn-sm';
        updateOverviewToggleText(button, readOverviewExpanded());

        button.addEventListener('click', () => {
            const nextExpanded = !readOverviewExpanded();
            writeOverviewExpanded(nextExpanded);
            applyOverviewVisibility(nextExpanded);
        });

        wrapper.appendChild(button);
        overviewContainer.parentNode?.insertBefore(wrapper, overviewContainer);
    };

    const formatDate = (date) => {
        const pad = (value) => String(value).padStart(2, '0');
        return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`;
    };

    const buildDefaultDateRange = () => {
        if (typeof moment === 'function') {
            const end = moment().endOf('day');
            const start = moment().subtract(29, 'days').startOf('day');
            return {
                start,
                end,
                label: `${start.format('DD-MM-YYYY')} - ${end.format('DD-MM-YYYY')}`,
            };
        }

        const endDate = new Date();
        const startDate = new Date(endDate);
        startDate.setDate(endDate.getDate() - 29);
        return {
            start: startDate,
            end: endDate,
            label: `${formatDate(startDate)} - ${formatDate(endDate)}`,
        };
    };

    const defaultDateRange = buildDefaultDateRange();

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

    const SLA_META = {
        en_rango: { label: 'En rango', badgeClass: 'text-bg-success', hint: 'Dentro de la ventana operativa' },
        advertencia: { label: 'Seguimiento 72h', badgeClass: 'text-bg-warning text-dark', hint: 'Revisar en las próximas 72h' },
        critico: { label: 'Crítico 24h', badgeClass: 'text-bg-danger', hint: 'Revisar en las próximas 24h' },
        vencido: { label: 'Vencido', badgeClass: 'text-bg-dark', hint: 'SLA excedido' },
        sin_fecha: { label: 'Sin programación', badgeClass: 'text-bg-secondary', hint: 'Sin fecha objetivo registrada' },
        cerrado: { label: 'Cerrado', badgeClass: 'text-bg-secondary', hint: 'Solicitud cerrada' },
    };

    const PRIORIDAD_META = {
        urgente: { label: 'Urgente', badgeClass: 'text-bg-danger' },
        pendiente: { label: 'Pendiente', badgeClass: 'text-bg-warning text-dark' },
        normal: { label: 'Normal', badgeClass: 'text-bg-success' },
    };

    const getSlaMeta = (status) => {
        const normalized = (status || '').toString().trim();
        return SLA_META[normalized] || SLA_META.sin_fecha;
    };

    const getPrioridadMeta = (prioridad) => {
        const normalized = (prioridad || '').toString().trim().toLowerCase();
        return PRIORIDAD_META[normalized] || PRIORIDAD_META.normal;
    };

    const formatIsoDate = (iso, { dateOnly = false } = {}) => {
        if (!iso) {
            return null;
        }

        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return dateOnly ? date.toLocaleDateString() : date.toLocaleString();
    };

    const formatHours = (value) => {
        if (typeof value !== 'number' || Number.isNaN(value)) {
            return null;
        }

        const rounded = Math.round(value);
        if (Math.abs(rounded) >= 48) {
            const days = (rounded / 24).toFixed(1);
            return `${days} día(s)`;
        }

        return `${rounded} h`;
    };

    const getAlertBadges = (item = {}) => {
        const alerts = [];

        if (item.alert_reprogramacion) {
            alerts.push({
                label: 'Reprogramar',
                variant: 'text-bg-danger',
                icon: 'mdi-calendar-alert',
            });
        }

        if (item.alert_pendiente_consentimiento) {
            alerts.push({
                label: 'Consentimiento',
                variant: 'text-bg-warning text-dark',
                icon: 'mdi-shield-alert',
            });
        }

        return alerts;
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

    const normalizeUpper = (value) => (value ?? '').toString().trim().toUpperCase();
    const isChecked = (id) => Boolean(document.getElementById(id)?.checked);
    const getValue = (id) => document.getElementById(id)?.value ?? '';

    const aplicarFiltrosLocales = (data) => {
        const items = Array.isArray(data) ? data : [];
        const term = (searchInput?.value || '').trim().toLowerCase();
        const tipoSeleccionado = normalizeUpper(getValue('kanbanTipoFilter'));

        const filtrarDerivacionVencida = isChecked('kanbanDerivacionVencidaFilter');
        const filtrarDerivacionPorVencer = isChecked('kanbanDerivacionPorVencerFilter');
        const derivacionDiasRaw = Number.parseInt(getValue('kanbanDerivacionDiasInput'), 10);
        const diasPorVencer = Number.isFinite(derivacionDiasRaw) ? Math.max(0, derivacionDiasRaw) : 0;
        const filtrarSinResponsable = isChecked('kanbanCrmSinResponsableFilter');

        const today = new Date();
        const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const msPerDay = 24 * 60 * 60 * 1000;

        const keys = ['full_name', 'hc_number', 'procedimiento', 'doctor', 'afiliacion', 'estado', 'crm_pipeline_stage'];

        return items.filter(item => {
            if (tipoSeleccionado && normalizeUpper(item?.tipo) !== tipoSeleccionado) {
                return false;
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
                const vence = diffDays < 0;
                const porVencer = diffDays >= 0 && diffDays <= diasPorVencer;
                const matchDerivacion = (filtrarDerivacionVencida && vence)
                    || (filtrarDerivacionPorVencer && porVencer);
                if (!matchDerivacion) {
                    return false;
                }
            }

            if (!term) {
                return true;
            }

            return keys.some(key => {
                const value = item?.[key];
                return value && value.toString().toLowerCase().includes(term);
            });
        });
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

        const metrics = window.__solicitudesMetrics || {};
        const slaMetrics = metrics.sla || {};
        const alertMetrics = metrics.alerts || {};
        const priorityMetrics = metrics.prioridad || {};
        const teams = metrics.teams ? Object.values(metrics.teams) : [];

        const counts = {};

        (Array.isArray(data) ? data : []).forEach(item => {
            const slug = normalizeEstado(item?.estado);
            counts[slug] = (counts[slug] || 0) + 1;
        });

        const cards = [];

        const urgentes = priorityMetrics.urgente ?? 0;
        const pendientes = priorityMetrics.pendiente ?? 0;
        const vencidos = slaMetrics.vencido ?? 0;
        const criticos = slaMetrics.critico ?? 0;
        const advertencias = slaMetrics.advertencia ?? 0;
        const reprogramar = alertMetrics.requiere_reprogramacion ?? 0;
        const consentimientoPendiente = alertMetrics.pendiente_consentimiento ?? 0;
        const topTeam = teams.length ? teams[0] : null;

        cards.push(createOverviewCard({
            title: 'Total de solicitudes',
            count: total,
            badge: total ? `${Math.round(((urgentes || 0) / (total || 1)) * 100)}% urgentes` : null,
            badgeClass: 'text-bg-primary',
            subtitle: total ? `${pendientes} pendientes · ${urgentes} urgentes` : 'No hay solicitudes registradas',
        }));

        cards.push(createOverviewCard({
            title: 'SLA vencido',
            count: vencidos,
            badge: total ? `${Math.round((vencidos / (total || 1)) * 100)}%` : null,
            badgeClass: vencidos ? 'text-bg-danger' : 'text-bg-success',
            subtitle: vencidos ? 'Atender inmediatamente' : 'Sin vencimientos activos',
            metricKey: 'sla-vencido',
        }));

        cards.push(createOverviewCard({
            title: 'SLA crítico (24h)',
            count: criticos,
            badge: criticos ? `${criticos} caso(s)` : null,
            badgeClass: criticos ? 'text-bg-warning text-dark' : 'text-bg-secondary',
            subtitle: criticos ? 'Programar seguimiento hoy' : 'Sin casos críticos',
        }));

        cards.push(createOverviewCard({
            title: 'Seguimiento (72h)',
            count: advertencias,
            badge: advertencias ? `${advertencias} en agenda` : null,
            badgeClass: advertencias ? 'text-bg-info text-white' : 'text-bg-secondary',
            subtitle: advertencias ? 'Preparar documentación y confirmaciones' : 'Todo en rango extendido',
        }));

        cards.push(createOverviewCard({
            title: 'Reprogramación',
            count: reprogramar,
            badge: reprogramar ? 'Alertas activas' : null,
            badgeClass: reprogramar ? 'text-bg-danger' : 'text-bg-secondary',
            subtitle: reprogramar ? 'Contactar y reagendar' : 'Sin cirugías vencidas',
        }));

        cards.push(createOverviewCard({
            title: 'Consentimiento',
            count: consentimientoPendiente,
            badge: consentimientoPendiente ? 'Falta registro' : null,
            badgeClass: consentimientoPendiente ? 'text-bg-warning text-dark' : 'text-bg-secondary',
            subtitle: consentimientoPendiente ? 'Gestionar firmas pendientes' : 'Consentimientos vigentes',
        }));

        if (topTeam) {
            const resumenEquipo = [
                topTeam.vencido ? `${topTeam.vencido} vencido(s)` : null,
                topTeam.critico ? `${topTeam.critico} crítico(s)` : null,
                topTeam.advertencia ? `${topTeam.advertencia} seguimiento(s)` : null,
            ].filter(Boolean);

            cards.push(createOverviewCard({
                title: 'Equipo con mayor carga',
                count: topTeam.total,
                badge: topTeam.responsable_nombre || 'Sin responsable',
                badgeClass: 'text-bg-info text-white',
                subtitle: resumenEquipo.length ? resumenEquipo.join(' · ') : 'Sin alertas en este equipo',
            }));
        }

        const metricKeyBySlug = Object.entries(quickMetricsConfig).reduce((acc, [key, config]) => {
            if (config?.estado) {
                acc[normalizeEstado(config.estado)] = key;
            }
            return acc;
        }, {});

        Object.entries(estadosMeta).forEach(([slug, meta]) => {
            // Omitir estados excluidos en el overview
            if (OVERVIEW_EXCLUDED_STATES.has(slug)) return;
            const count = counts[slug] || 0;
            const porcentaje = total ? Math.round((count / total) * 100) : 0;
            const metricKey = metricKeyBySlug[slug];
            cards.push(createOverviewCard({
                title: meta?.label ?? slug,
                count,
                badge: `${porcentaje}%`,
                badgeClass: `text-bg-${escapeHtml(meta?.color || 'secondary')}`,
                subtitle: count ? 'Solicitudes en esta etapa' : 'Sin tarjetas en la columna',
                metricKey,
            }));
        });

        overviewContainer.innerHTML = cards.join('');
        ensureOverviewToggle();
        applyOverviewVisibility();
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

            const dias = calcularDias(item?.fecha_programada_iso || item?.fecha || item?.created_at_iso);
            const turno = formatTurno(item?.turno) || '';
            const pipeline = item?.crm_pipeline_stage || 'Recibido';
            const fuente = item?.crm_fuente || '';
            const responsable = item?.crm_responsable_nombre || 'Sin responsable asignado';
            const avatarHtml = renderResponsableAvatar(responsable, item?.crm_responsable_avatar);
            const prioridadMeta = getPrioridadMeta(item?.prioridad_automatica);
            const prioridadDisplay = item?.prioridad || prioridadMeta.label;
            const prioridadBadgeClass = item?.prioridad_origen === 'manual'
                ? 'text-bg-primary'
                : prioridadMeta.badgeClass;
            const prioridadOrigen = item?.prioridad_origen === 'manual' ? 'Prioridad manual' : 'Regla automática';
            const slaMeta = getSlaMeta(item?.sla_status);
            const slaDeadlineLabel = formatIsoDate(item?.sla_deadline);
            const slaHoursLabel = formatHours(item?.sla_hours_remaining);
            const slaSummaryParts = [];
            if (slaDeadlineLabel) {
                slaSummaryParts.push(`Vence: ${slaDeadlineLabel}`);
            }
            if (slaHoursLabel) {
                slaSummaryParts.push(slaHoursLabel);
            }
            if (typeof dias === 'number' && !Number.isNaN(dias)) {
                slaSummaryParts.push(`Edad: ${dias} día(s)`);
            }
            const slaSummary = slaSummaryParts.join(' · ');
            const alerts = getAlertBadges(item);
            const alertsHtml = alerts.length
                ? `<div class="d-flex flex-wrap gap-1 mt-1">${alerts.map(alert => `<span class="badge ${escapeHtml(alert.variant)}"><i class="mdi ${escapeHtml(alert.icon)} me-1"></i>${escapeHtml(alert.label)}</span>`).join('')}</div>`
                : '';

            const detalleProcedimiento = item?.procedimiento || 'Sin procedimiento';
            const detalleDoctor = item?.doctor || 'Sin doctor';
            const detalleAfiliacion = item?.afiliacion || 'Sin afiliación';

            const progress = item?.checklist_progress || {};
            const progressPercent = Number.isFinite(progress.percent) ? progress.percent : null;
            const progressLabel = progressPercent !== null ? `${progressPercent}%` : '—';
            const nextLabel = progress.next_label || progress.next_slug || '';
            const progressHtml = `
                <div class="w-100">
                    <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                        <span>${escapeHtml(progress.completed ?? 0)}/${escapeHtml(progress.total ?? 0)} pasos</span>
                        <span>${escapeHtml(progressLabel)}</span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: ${progressPercent ?? 0}%;" aria-valuenow="${progressPercent ?? 0}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    ${nextLabel ? `<div class="small text-muted mt-1">Próximo: ${escapeHtml(nextLabel)}</div>` : ''}
                </div>`;

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
                <td>${progressHtml}</td>
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
                    <div class="d-flex flex-column gap-1">
                        <span class="badge ${escapeHtml(prioridadBadgeClass)}">${escapeHtml(prioridadDisplay)}</span>
                        <div class="text-muted small">${escapeHtml(prioridadOrigen)}</div>
                        <div>
                            <span class="badge ${escapeHtml(slaMeta.badgeClass)}" title="${escapeHtml(slaMeta.hint)}">${escapeHtml(slaMeta.label)}</span>
                            ${slaSummary ? `<div class="text-muted small">${escapeHtml(slaSummary)}</div>` : ''}
                        </div>
                        ${alertsHtml}
                    </div>
                </td>
                <td>
                    ${turno ? `<span class="badge text-bg-info text-dark">#${escapeHtml(turno)}</span>` : '<span class="text-muted">—</span>'}
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-prefactura-trigger="button" data-hc="${escapeHtml(item?.hc_number ?? '')}" data-form="${escapeHtml(item?.form_id ?? '')}">
                            <i class="mdi mdi-eye-outline"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-open-crm" data-solicitud-id="${escapeHtml(item?.id ?? '')}" data-paciente-nombre="${escapeHtml(item?.full_name ?? '')}">
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
            openExportModal({ title: 'Exportar PDF de solicitudes' });
        });
    }

    switchView(currentView, false);

    const applyLocalFilters = () => renderFromCache();

    let searchDebounce = null;
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                applyLocalFilters();
            }, 220);
        });
    }

    const obtenerFiltros = () => ({
        afiliacion: document.getElementById('kanbanAfiliacionFilter')?.value ?? '',
        doctor: document.getElementById('kanbanDoctorFilter')?.value ?? '',
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

    const exportSolicitudesPdf = async ({ quickMetric = '' } = {}) => {
        const payload = buildReportPayload({ quickMetric, format: 'pdf' });
        const response = await fetch('/solicitudes/reportes/pdf', {
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
            link.download = 'reporte_solicitudes.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        setTimeout(() => window.URL.revokeObjectURL(blobUrl), 10000);
    };

    const exportSolicitudesExcel = async ({ quickMetric = '' } = {}) => {
        const payload = buildReportPayload({ quickMetric, format: 'excel' });
        const response = await fetch('/solicitudes/reportes/excel', {
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
            'reporte_solicitudes.xlsx'
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
                        exportSolicitudesExcel({ quickMetric: resolvedQuickMetric })
                            .catch(error => showToast(error?.message || 'No se pudo generar el Excel', false));
                    } else {
                        exportSolicitudesPdf({ quickMetric: resolvedQuickMetric })
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
                    exportSolicitudesExcel({ quickMetric: resolvedQuickMetric })
                        .catch(error => showToast(error?.message || 'No se pudo generar el Excel', false));
                } else {
                    exportSolicitudesPdf({ quickMetric: resolvedQuickMetric })
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
                exportSolicitudesExcel({ quickMetric: resolvedQuickMetric })
                    .then(() => showToast('Reporte Excel generado.', true))
                    .catch(error => showToast(error?.message || 'No se pudo generar el Excel', false));
                return;
            }

            exportSolicitudesPdf({ quickMetric: resolvedQuickMetric })
                .then(() => showToast('Reporte PDF generado.', true))
                .catch(error => showToast(error?.message || 'No se pudo generar el PDF', false));
        });
    };

    const setFilterValues = (f) => {
        if (!f) return;
        const afSelect = document.getElementById('kanbanAfiliacionFilter');
        const docSelect = document.getElementById('kanbanDoctorFilter');
        const dateInput = document.getElementById('kanbanDateFilter');
        if (afSelect && f.afiliacion !== undefined) afSelect.value = f.afiliacion;
        if (docSelect && f.doctor !== undefined) docSelect.value = f.doctor;
        if (dateInput && f.fechaTexto !== undefined) dateInput.value = f.fechaTexto;
        if (searchInput && f.search !== undefined) searchInput.value = f.search;
    };

    const cargarKanban = (filtros = {}) => {
        const filtrosSeleccionados = obtenerFiltros();
        console.groupCollapsed('%cKANBAN ▶ Filtros aplicados', 'color:#0b7285');
        console.log(filtros);
        console.groupEnd();

        return fetch(`${config.basePath}/kanban-data`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros),
        })
            .then(async (response) => {
                if (!response.ok) {
                    const errorProbe = response.clone();
                    let serverMsg = '';
                    try {
                        const data = await errorProbe.json();
                        serverMsg = data?.error || JSON.stringify(data);
                    } catch (_) {
                        try {
                            serverMsg = await errorProbe.text();
                        } catch (__) {
                            serverMsg = '';
                        }
                    }
                    const msg = serverMsg ? `No se pudo cargar el tablero. Servidor: ${serverMsg}` : 'No se pudo cargar el tablero';
                    throw new Error(msg);
                }
                return response.json();
            })
            .then(({ data = [], options = {} }) => {
                const normalized = Array.isArray(data) ? data : [];
                setDataStore(normalized);
                window.__solicitudesMetrics = options.metrics || null;

                if (options.afiliaciones) {
                    poblarAfiliacionesUnicas(options.afiliaciones);
                } else {
                    poblarAfiliacionesUnicas(getDataStore());
                }

                if (options.doctores) {
                    poblarDoctoresUnicos(options.doctores);
                } else {
                    poblarDoctoresUnicos(getDataStore());
                }

                if (options.crm) {
                    setCrmOptions(options.crm);
                } else {
                    setCrmOptions({});
                }

                // Restaurar selección de filtros después de repoblar opciones
                setFilterValues(filtrosSeleccionados);
                renderFromCache();
            })
            .catch(error => {
                console.error('❌ Error cargando Kanban:', error);
                showToast(error?.message || 'No se pudo cargar el tablero de solicitudes', false);
                window.__solicitudesMetrics = null;
            });
    };

    window.aplicarFiltros = () => cargarKanban(obtenerFiltros());

    ['kanbanAfiliacionFilter', 'kanbanDoctorFilter'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', () => window.aplicarFiltros());
        }
    });

    [
        'kanbanTipoFilter',
        'kanbanDerivacionVencidaFilter',
        'kanbanDerivacionPorVencerFilter',
        'kanbanCrmSinResponsableFilter',
    ].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', applyLocalFilters);
        }
    });

    const derivacionDiasInput = document.getElementById('kanbanDerivacionDiasInput');
    if (derivacionDiasInput) {
        derivacionDiasInput.addEventListener('input', applyLocalFilters);
    }

    if (dateFilter && !dateFilter.value) {
        dateFilter.value = defaultDateRange.label;
    }

    if (typeof $ !== 'undefined' && typeof $.fn.daterangepicker === 'function') {
        const $datePicker = $('#kanbanDateFilter')
            .daterangepicker({
                locale: {
                    format: 'DD-MM-YYYY',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                },
                startDate: defaultDateRange.start,
                endDate: defaultDateRange.end,
                autoUpdateInput: false,
                parentEl: 'body',
            })
            .on('apply.daterangepicker', function (ev, picker) {
                this.value = `${picker.startDate.format('DD-MM-YYYY')} - ${picker.endDate.format('DD-MM-YYYY')}`;
                window.aplicarFiltros();
            })
            .on('cancel.daterangepicker', function () {
                this.value = '';
                window.aplicarFiltros();
            });

        const daterangepicker = $datePicker.data('daterangepicker');
        if (daterangepicker && dateFilter && dateFilter.value === defaultDateRange.label) {
            daterangepicker.setStartDate(defaultDateRange.start);
            daterangepicker.setEndDate(defaultDateRange.end);
            // Asegura que arranque oculto y con un z-index alto
            if (daterangepicker.container && daterangepicker.container[0]) {
                daterangepicker.container.hide();
                daterangepicker.container.css('z-index', 2050);
            }
        }
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
            const channelName = realtimeConfig.channel || 'solicitudes-kanban';
            const events = realtimeConfig.events || {};
            const newEventName = events.new_request || realtimeConfig.event || 'kanban.nueva-solicitud';
            const statusEventName = events.status_updated || 'kanban.estado-actualizado';
            const crmEventName = events.crm_updated || 'crm.detalles-actualizados';
            const reminderEvents = [
                {
                    key: 'surgery',
                    eventName: events.surgery_reminder || 'recordatorio-cirugia',
                    defaultLabel: 'Recordatorio de cirugía',
                    icon: 'mdi mdi-alarm-check',
                    tone: 'primary',
                },
                {
                    key: 'preop',
                    eventName: events.preop_reminder || 'recordatorio-preop',
                    defaultLabel: 'Preparación preoperatoria',
                    icon: 'mdi mdi-clipboard-check-outline',
                    tone: 'info',
                },
                {
                    key: 'postop',
                    eventName: events.postop_reminder || 'recordatorio-postop',
                    defaultLabel: 'Control postoperatorio',
                    icon: 'mdi mdi-heart-pulse',
                    tone: 'success',
                },
                {
                    key: 'exams',
                    eventName: events.exams_expiring || 'alerta-examenes-por-vencer',
                    defaultLabel: 'Exámenes por vencer',
                    icon: 'mdi mdi-file-alert-outline',
                    tone: 'warning',
                },
            ];

            notificationPanel.setIntegrationWarning('');

            const channel = pusher.subscribe(channelName);

            channel.bind(newEventName, data => {
                const nombre = data?.full_name || data?.nombre || (data?.hc_number ? `HC ${data.hc_number}` : 'Paciente sin nombre');
                const prioridad = String(data?.prioridad ?? '').toUpperCase();
                const urgente = prioridad === 'SI' || prioridad === 'URGENTE' || prioridad === 'ALTA';
                const mensaje = `🆕 Nueva solicitud: ${nombre}`;

                notificationPanel.pushRealtime({
                    dedupeKey: `new-${data?.form_id ?? data?.secuencia ?? Date.now()}`,
                    title: nombre,
                    message: data?.procedimiento || data?.tipo || 'Nueva solicitud registrada',
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
                maybeShowDesktopNotification('Nueva solicitud', mensaje);
                window.aplicarFiltros();
            });

            if (statusEventName) {
                channel.bind(statusEventName, data => {
                    const paciente = data?.full_name || (data?.hc_number ? `HC ${data.hc_number}` : `Solicitud #${data?.id ?? ''}`);
                    const nuevoEstado = data?.estado || 'Actualizada';
                    const estadoAnterior = data?.estado_anterior || 'Sin estado previo';
                    const solicitudId = data?.id ?? data?.solicitud_id ?? null;
                    const formId = data?.form_id ?? data?.formId ?? null;
                    const hcNumber = data?.hc_number
                        ?? data?.hcNumber
                        ?? (solicitudId
                            ? getDataStore().find(item => String(item.id) === String(solicitudId))?.hc_number
                            : null);

                    notificationPanel.pushRealtime({
                        dedupeKey: `estado-${data?.id ?? Date.now()}-${nuevoEstado}`,
                        title: paciente,
                        message: `Estado actualizado: ${estadoAnterior} → ${nuevoEstado}`,
                        meta: [
                            data?.procedimiento || '',
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
                    maybeShowDesktopNotification('Estado de solicitud', `${paciente} pasó a ${nuevoEstado}`);
                    scheduleRealtimeRefresh({ solicitudId, formId, hcNumber });
                    window.aplicarFiltros();
                });
            }

            if (crmEventName) {
                channel.bind(crmEventName, data => {
                    const paciente = data?.paciente_nombre || `Solicitud #${data?.solicitud_id ?? ''}`;
                    const etapa = data?.pipeline_stage || 'Etapa actualizada';
                    const responsable = data?.responsable_nombre || '';

                    notificationPanel.pushRealtime({
                        dedupeKey: `crm-${data?.solicitud_id ?? Date.now()}-${etapa}-${responsable}`,
                        title: paciente,
                        message: `CRM actualizado · ${etapa}`,
                        meta: [
                            data?.procedimiento || '',
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

            const bindReminderEvent = config => {
                if (!config.eventName) {
                    return;
                }

                channel.bind(config.eventName, rawData => {
                    const data = rawData || {};
                    const paciente = data.full_name || `Solicitud #${(data.id ?? '')}`;
                    const reminderLabel = data.reminder_label || config.defaultLabel;
                    const reminderContext = data.reminder_context || '';

                    const dueIso = data.due_at || data.fecha_programada || null;
                    const dueDate = dueIso ? new Date(dueIso) : null;
                    const dueLabel = dueDate && !Number.isNaN(dueDate.getTime())
                        ? dueDate.toLocaleString()
                        : '';

                    const fechaProgramada = data.fecha_programada ? new Date(data.fecha_programada) : null;
                    const examExpiry = data.exam_expires_at ? new Date(data.exam_expires_at) : null;
                    const examLabel = examExpiry && !Number.isNaN(examExpiry.getTime())
                        ? examExpiry.toLocaleDateString()
                        : '';

                    const meta = [
                        data.procedimiento || '',
                        data.doctor ? `Dr(a). ${data.doctor}` : '',
                        data.quirofano ? `Quirófano: ${data.quirofano}` : '',
                        data.prioridad ? `Prioridad: ${String(data.prioridad).toUpperCase()}` : '',
                        reminderContext,
                    ].filter(Boolean);

                    if (config.key === 'exams' && examLabel) {
                        meta.push(`Vencen: ${examLabel}`);
                    }

                    notificationPanel.pushPending({
                        dedupeKey: `recordatorio-${config.key}-${data.id ?? Date.now()}-${dueIso ?? data.fecha_programada ?? ''}`,
                        title: paciente,
                        message: reminderLabel,
                        meta,
                        badges: [
                            dueLabel ? { label: dueLabel, variant: 'bg-primary text-white' } : null,
                        ].filter(Boolean),
                        icon: config.icon,
                        tone: config.tone,
                        timestamp: new Date(),
                        dueAt: dueDate || fechaProgramada,
                        channels: mapChannels(data?.channels),
                    });

                    const toastLabel = reminderLabel || 'Recordatorio';
                    const mensaje = dueLabel
                        ? `${toastLabel}: ${paciente} · ${dueLabel}`
                        : `${toastLabel}: ${paciente}`;
                    showToast(mensaje, true, toastDurationMs);
                    maybeShowDesktopNotification(toastLabel, mensaje);
                });
            };

            reminderEvents.forEach(bindReminderEvent);
        }
    }

    cargarKanban(obtenerFiltros());
});
