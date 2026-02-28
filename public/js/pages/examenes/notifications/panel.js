const TONE_TO_AVATAR = {
    success: 'bg-soft-success',
    info: 'bg-soft-info',
    warning: 'bg-soft-warning',
    danger: 'bg-soft-danger',
    primary: 'bg-soft-primary',
};

const DEFAULT_ICON = 'mdi mdi-bell-outline';
const REVIEWED_ICON = 'mdi mdi-check-circle-outline';

const relativeTimeFormat = typeof Intl !== 'undefined' && Intl.RelativeTimeFormat
    ? new Intl.RelativeTimeFormat('es', { numeric: 'auto' })
    : null;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function sanitizeClassName(value, fallback = '') {
    const tokens = String(value ?? '')
        .trim()
        .split(/\s+/)
        .filter(token => /^[-_a-zA-Z0-9]+$/.test(token));

    if (tokens.length > 0) {
        return tokens.join(' ');
    }

    return fallback;
}

function toDate(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function toIsoOrNull(date) {
    return date instanceof Date && !Number.isNaN(date.getTime())
        ? date.toISOString()
        : null;
}

function formatRelative(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }

    const diffMs = date.getTime() - Date.now();
    const diffSec = Math.round(diffMs / 1000);

    if (!relativeTimeFormat) {
        return date.toLocaleString();
    }

    const thresholds = [
        { limit: 60, unit: 'second' },
        { limit: 3600, unit: 'minute' },
        { limit: 86400, unit: 'hour' },
        { limit: 604800, unit: 'day' },
        { limit: 2629800, unit: 'week' },
        { limit: 31557600, unit: 'month' },
    ];

    const abs = Math.abs(diffSec);
    for (const { limit, unit } of thresholds) {
        if (abs < limit) {
            const divisor = unit === 'second' ? 1
                : unit === 'minute' ? 60
                : unit === 'hour' ? 3600
                : unit === 'day' ? 86400
                : unit === 'week' ? 604800
                : 2629800;
            const value = Math.round(diffSec / divisor);
            return relativeTimeFormat.format(value, unit);
        }
    }

    const years = Math.round(diffSec / 31557600);
    return relativeTimeFormat.format(years, 'year');
}

function sanitizeMeta(meta) {
    return (Array.isArray(meta) ? meta : [])
        .map(item => String(item ?? '').trim())
        .filter(Boolean);
}

function sanitizeBadges(badges) {
    return (Array.isArray(badges) ? badges : [])
        .map(badge => ({
            label: String(badge?.label ?? '').trim(),
            variant: sanitizeClassName(badge?.variant, 'bg-light text-muted') || 'bg-light text-muted',
        }))
        .filter(badge => badge.label !== '');
}

function normalizeChannels(channels) {
    if (!Array.isArray(channels) || channels.length === 0) {
        return [];
    }

    return channels
        .map(channel => String(channel ?? '').trim())
        .filter(Boolean);
}

function resolveEntryId(entry, timestamp) {
    const explicit = String(entry?.id ?? '').trim();
    if (explicit !== '') {
        return explicit;
    }

    const dedupe = String(entry?.dedupeKey ?? '').trim();
    if (dedupe !== '') {
        return `dedupe:${dedupe}`;
    }

    const base = timestamp instanceof Date && !Number.isNaN(timestamp.getTime())
        ? timestamp.getTime()
        : Date.now();
    const entropy = Math.random().toString(36).slice(2, 8);

    return `evt:${base}:${entropy}`;
}

function normalizeReviewedAt(entry, timestamp) {
    const reviewedAt = toDate(entry?.reviewedAt);
    if (reviewedAt instanceof Date) {
        return reviewedAt;
    }

    if (entry?.reviewed === true) {
        return timestamp;
    }

    return null;
}

function isReviewed(entry) {
    return entry?.reviewedAt instanceof Date && !Number.isNaN(entry.reviewedAt.getTime());
}

function normalizeEntry(entry, fallbackTone = 'info') {
    const timestamp = toDate(entry?.timestamp) || new Date();
    const dueAt = toDate(entry?.dueAt);

    const dedupeRaw = String(entry?.dedupeKey ?? '').trim();
    const dedupeKey = dedupeRaw !== '' ? dedupeRaw : null;

    return {
        id: resolveEntryId(entry, timestamp),
        dedupeKey,
        title: String(entry?.title ?? '').trim() || 'Notificación',
        message: String(entry?.message ?? '').trim(),
        meta: sanitizeMeta(entry?.meta),
        badges: sanitizeBadges(entry?.badges),
        icon: sanitizeClassName(entry?.icon, DEFAULT_ICON) || DEFAULT_ICON,
        tone: entry?.tone && TONE_TO_AVATAR[entry.tone] ? entry.tone : fallbackTone,
        timestamp,
        dueAt,
        reviewedAt: normalizeReviewedAt(entry, timestamp),
        channels: normalizeChannels(entry?.channels),
    };
}

function serializeEntry(entry) {
    return {
        id: String(entry?.id ?? '').trim(),
        dedupeKey: entry?.dedupeKey || null,
        title: String(entry?.title ?? '').trim(),
        message: String(entry?.message ?? '').trim(),
        meta: sanitizeMeta(entry?.meta),
        badges: sanitizeBadges(entry?.badges),
        icon: sanitizeClassName(entry?.icon, DEFAULT_ICON) || DEFAULT_ICON,
        tone: entry?.tone,
        timestamp: toIsoOrNull(entry?.timestamp),
        dueAt: toIsoOrNull(entry?.dueAt),
        reviewedAt: toIsoOrNull(entry?.reviewedAt),
        channels: normalizeChannels(entry?.channels),
    };
}

function parseEntry(raw, fallbackTone) {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    return normalizeEntry({
        ...raw,
        timestamp: raw.timestamp ? new Date(raw.timestamp) : undefined,
        dueAt: raw.dueAt ? new Date(raw.dueAt) : undefined,
        reviewedAt: raw.reviewedAt ? new Date(raw.reviewedAt) : undefined,
    }, fallbackTone);
}

function isWithinRetention(entry, retentionMs) {
    if (!Number.isFinite(retentionMs) || retentionMs <= 0) {
        return true;
    }

    const timestamp = entry.timestamp instanceof Date && !Number.isNaN(entry.timestamp.getTime())
        ? entry.timestamp.getTime()
        : Date.now();

    return Date.now() - timestamp <= retentionMs;
}

function countUnread(entries) {
    return entries.reduce((total, entry) => total + (isReviewed(entry) ? 0 : 1), 0);
}

function renderEntry(entry, listType = 'realtime') {
    const avatarClass = TONE_TO_AVATAR[entry.tone] || TONE_TO_AVATAR.info;
    const badgesHtml = entry.badges.map(badge => `
        <span class="badge rounded-pill ${escapeHtml(badge.variant)}">${escapeHtml(badge.label)}</span>
    `).join('');

    const metaItems = [...entry.meta];
    if (entry.channels.length > 0) {
        metaItems.push(`Canales: ${entry.channels.join(', ')}`);
    }

    const metaHtml = metaItems
        .map(item => `<span>${escapeHtml(item)}</span>`)
        .join('');

    const timeLabel = formatRelative(entry.timestamp);
    const dueLabel = entry.dueAt instanceof Date && !Number.isNaN(entry.dueAt.getTime())
        ? entry.dueAt.toLocaleString()
        : '';

    const unread = !isReviewed(entry);
    const statusBadge = unread
        ? '<span class="badge bg-warning text-dark">Por revisar</span>'
        : '<span class="badge bg-light text-muted">Revisada</span>';

    const actionHtml = unread
        ? `<button type="button" class="btn btn-xs btn-outline-primary" data-action="mark-reviewed" data-list-type="${escapeHtml(listType)}" data-entry-id="${escapeHtml(entry.id)}">Marcar revisada</button>`
        : `<span class="notification-entry__reviewed"><i class="${REVIEWED_ICON}"></i> Revisada</span>`;

    const dueHtml = listType === 'pending' && dueLabel
        ? `<time datetime="${entry.dueAt.toISOString()}"><i class="mdi mdi-calendar-clock"></i> ${escapeHtml(dueLabel)}</time>`
        : '';

    const relativeHtml = timeLabel
        ? `<time datetime="${entry.timestamp.toISOString()}"><i class="mdi mdi-timer-outline"></i> ${escapeHtml(timeLabel)}</time>`
        : '';

    return `
        <div class="media py-10 px-0 notification-entry ${unread ? 'notification-entry--unread' : ''}" data-list-type="${escapeHtml(listType)}" data-entry-id="${escapeHtml(entry.id)}">
            <div class="avatar ${avatarClass}">
                <i class="${escapeHtml(entry.icon || DEFAULT_ICON)}"></i>
            </div>
            <div class="media-body">
                <div class="notification-entry__header">
                    <p class="fs-16 mb-0 notification-entry__title"><strong>${escapeHtml(entry.title)}</strong></p>
                    ${statusBadge}
                </div>
                ${entry.message ? `<p class="text-muted mb-1">${escapeHtml(entry.message)}</p>` : ''}
                ${badgesHtml ? `<div class="notification-meta">${badgesHtml}</div>` : ''}
                ${metaHtml ? `<div class="notification-meta">${metaHtml}</div>` : ''}
                ${dueHtml}
                ${relativeHtml}
                <div class="notification-entry__actions">${actionHtml}</div>
            </div>
        </div>
    `;
}

function normalizeRetentionDays(retentionDays) {
    return Number.isFinite(retentionDays) && retentionDays >= 0 ? retentionDays : 0;
}

function resolveStorageKey(storageKey) {
    if (typeof storageKey !== 'string') {
        return null;
    }

    const normalized = storageKey.trim();
    return normalized !== '' ? normalized : null;
}

export function createNotificationPanel(options = {}) {
    const panel = document.getElementById(options.panelId || 'kanbanNotificationPanel');
    const backdrop = document.getElementById(options.backdropId || 'notificationPanelBackdrop');

    if (!panel || !backdrop) {
        return {
            pushRealtime: () => {},
            pushPending: () => {},
            markAllReviewed: () => 0,
            setChannelPreferences: () => {},
            setIntegrationWarning: () => {},
            getStats: () => ({
                realtimeTotal: 0,
                pendingTotal: 0,
                realtimeUnread: 0,
                pendingUnread: 0,
                totalReceived: 0,
                totalUnread: 0,
            }),
            open: () => {},
            close: () => {},
        };
    }

    if (panel.__notificationController) {
        return panel.__notificationController;
    }

    const realtimeList = panel.querySelector('[data-panel-list="realtime"]');
    const pendingList = panel.querySelector('[data-panel-list="pending"]');
    const realtimeCounter = panel.querySelector('[data-count="realtime"]');
    const pendingCounter = panel.querySelector('[data-count="pending"]');
    const channelFlags = panel.querySelector('[data-channel-flags]');
    const warningBox = panel.querySelector('[data-integration-warning]');
    const summaryReceived = panel.querySelector('[data-summary-count="received"]');
    const summaryUnread = panel.querySelector('[data-summary-count="unread"]');

    const toggleSelector = options.toggleSelector || '[data-notification-panel-toggle]';
    const toggleButtons = document.querySelectorAll(toggleSelector);
    const unreadBadges = document.querySelectorAll('[data-notification-unread-badge]');
    const realtimeLimit = options.realtimeLimit || 40;
    const pendingLimit = options.pendingLimit || 40;
    const storageKey = resolveStorageKey(options.storageKey);
    const retentionDays = normalizeRetentionDays(options.retentionDays);
    const retentionMs = retentionDays > 0 ? retentionDays * 24 * 60 * 60 * 1000 : 0;

    const state = {
        realtime: [],
        pending: [],
    };

    const applyRetentionAndLimits = () => {
        const filterAndLimit = (entries, limit) => entries
            .filter(entry => isWithinRetention(entry, retentionMs))
            .slice(0, limit);

        state.realtime = filterAndLimit(state.realtime, realtimeLimit);
        state.pending = filterAndLimit(state.pending, pendingLimit);

        state.pending.sort((a, b) => {
            const aTime = a.dueAt instanceof Date && !Number.isNaN(a.dueAt.getTime())
                ? a.dueAt.getTime()
                : Number.POSITIVE_INFINITY;
            const bTime = b.dueAt instanceof Date && !Number.isNaN(b.dueAt.getTime())
                ? b.dueAt.getTime()
                : Number.POSITIVE_INFINITY;
            return aTime - bTime;
        });
    };

    const loadState = () => {
        if (!storageKey || typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        try {
            const raw = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
            const realtime = Array.isArray(raw.realtime)
                ? raw.realtime.map(item => parseEntry(item, 'info')).filter(Boolean)
                : [];
            const pending = Array.isArray(raw.pending)
                ? raw.pending.map(item => parseEntry(item, 'primary')).filter(Boolean)
                : [];

            state.realtime = realtime;
            state.pending = pending;
            applyRetentionAndLimits();
        } catch (error) {
            console.warn('No se pudo restaurar el panel de notificaciones', error);
        }
    };

    const persistState = () => {
        if (!storageKey || typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        try {
            const payload = {
                realtime: state.realtime.map(entry => serializeEntry(entry)),
                pending: state.pending.map(entry => serializeEntry(entry)),
            };
            window.localStorage.setItem(storageKey, JSON.stringify(payload));
        } catch (error) {
            console.warn('No se pudo guardar el panel de notificaciones', error);
        }
    };

    const ensureIndicator = (button) => {
        if (!button) {
            return null;
        }

        const existing = button.querySelector('[data-notification-dot]');
        if (existing) {
            return existing;
        }

        const dot = document.createElement('span');
        dot.className = 'notification-dot';
        dot.setAttribute('data-notification-dot', '');
        dot.setAttribute('aria-hidden', 'true');
        button.appendChild(dot);
        return dot;
    };

    const getStats = () => {
        const realtimeTotal = state.realtime.length;
        const pendingTotal = state.pending.length;
        const realtimeUnread = countUnread(state.realtime);
        const pendingUnread = countUnread(state.pending);

        return {
            realtimeTotal,
            pendingTotal,
            realtimeUnread,
            pendingUnread,
            totalReceived: realtimeTotal + pendingTotal,
            totalUnread: realtimeUnread + pendingUnread,
        };
    };

    const updateCounter = (counter, value) => {
        if (counter) {
            counter.textContent = String(value);
        }
    };

    const updateUnreadBadges = (unread) => {
        unreadBadges.forEach(badge => {
            badge.textContent = String(unread);
            badge.classList.toggle('d-none', unread <= 0);
        });
    };

    const updateToggleIndicators = (unread) => {
        toggleButtons.forEach(button => {
            const dot = ensureIndicator(button);

            if (unread > 0) {
                button.classList.add('has-notifications');
                if (dot) {
                    dot.classList.add('is-visible');
                }
            } else {
                button.classList.remove('has-notifications');
                if (dot) {
                    dot.classList.remove('is-visible');
                }
            }
        });
    };

    const syncCountersAndSummary = () => {
        const stats = getStats();

        updateCounter(realtimeCounter, stats.realtimeUnread);
        updateCounter(pendingCounter, stats.pendingUnread);
        updateCounter(summaryReceived, stats.totalReceived);
        updateCounter(summaryUnread, stats.totalUnread);

        updateUnreadBadges(stats.totalUnread);
        updateToggleIndicators(stats.totalUnread);

        return stats;
    };

    const renderRealtime = () => {
        if (!realtimeList) {
            return;
        }

        if (state.realtime.length === 0) {
            realtimeList.innerHTML = '<p class="notification-empty">Aún no hay eventos recientes.</p>';
            return;
        }

        realtimeList.innerHTML = state.realtime
            .map(entry => renderEntry(entry, 'realtime'))
            .join('');
    };

    const renderPending = () => {
        if (!pendingList) {
            return;
        }

        if (state.pending.length === 0) {
            pendingList.innerHTML = '<p class="notification-empty">Sin recordatorios pendientes.</p>';
            return;
        }

        pendingList.innerHTML = state.pending
            .map(entry => renderEntry(entry, 'pending'))
            .join('');
    };

    const render = () => {
        renderRealtime();
        renderPending();
        syncCountersAndSummary();
    };

    const markEntryReviewed = (listType, entryId) => {
        if (!entryId) {
            return false;
        }

        const list = listType === 'pending' ? state.pending : listType === 'realtime' ? state.realtime : null;
        if (!list) {
            return false;
        }

        const entry = list.find(item => item.id === entryId);
        if (!entry || isReviewed(entry)) {
            return false;
        }

        entry.reviewedAt = new Date();
        return true;
    };

    const markAllReviewed = (listType = 'all') => {
        const now = new Date();
        let changed = 0;

        const markList = (entries) => {
            entries.forEach(entry => {
                if (!isReviewed(entry)) {
                    entry.reviewedAt = now;
                    changed += 1;
                }
            });
        };

        if (listType === 'realtime') {
            markList(state.realtime);
        } else if (listType === 'pending') {
            markList(state.pending);
        } else {
            markList(state.realtime);
            markList(state.pending);
        }

        if (changed > 0) {
            render();
            persistState();
        }

        return changed;
    };

    const pushRealtime = (entry) => {
        const normalized = normalizeEntry(entry, 'info');
        normalized.reviewedAt = null;

        if (normalized.dedupeKey) {
            state.realtime = state.realtime.filter(item => item.dedupeKey !== normalized.dedupeKey);
        }

        state.realtime.unshift(normalized);
        applyRetentionAndLimits();

        render();
        persistState();
    };

    const pushPending = (entry) => {
        const normalized = normalizeEntry(entry, 'primary');
        normalized.reviewedAt = null;

        if (normalized.dedupeKey) {
            state.pending = state.pending.filter(item => item.dedupeKey !== normalized.dedupeKey);
        }

        state.pending.push(normalized);
        applyRetentionAndLimits();

        render();
        persistState();
    };

    const setChannelPreferences = (prefs) => {
        if (!channelFlags) {
            return;
        }

        const defaults = ['Tiempo real (Pusher)'];
        if (prefs?.email) {
            defaults.push('Correo electrónico');
        }
        if (prefs?.sms) {
            defaults.push('SMS');
        }
        if (prefs?.daily_summary) {
            defaults.push('Resumen diario');
        }

        channelFlags.textContent = `Canales activos: ${defaults.join(' · ')}`;
    };

    const setIntegrationWarning = (message) => {
        if (!warningBox) {
            return;
        }

        if (message) {
            warningBox.textContent = message;
            warningBox.classList.remove('d-none');
        } else {
            warningBox.textContent = '';
            warningBox.classList.add('d-none');
        }
    };

    const open = () => {
        panel.classList.add('is-open');
        panel.classList.add('control-sidebar-open');

        if (document && document.body) {
            document.body.classList.add('control-sidebar-open');
        }

        if (backdrop) {
            backdrop.classList.add('is-visible');
        }

        panel.setAttribute('aria-hidden', 'false');
    };

    const close = () => {
        panel.classList.remove('is-open');
        panel.classList.remove('control-sidebar-open');

        if (document && document.body) {
            document.body.classList.remove('control-sidebar-open');
        }

        if (backdrop) {
            backdrop.classList.remove('is-visible');
        }

        panel.setAttribute('aria-hidden', 'true');
    };

    toggleButtons.forEach(button => {
        button.addEventListener('click', event => {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (panel.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });
    });

    panel.querySelectorAll('[data-action="close-panel"]').forEach(element => {
        element.addEventListener('click', close);
        element.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                close();
            }
        });
    });

    panel.addEventListener('click', event => {
        const markOne = event.target.closest('[data-action="mark-reviewed"]');
        if (markOne && panel.contains(markOne)) {
            const listType = String(markOne.getAttribute('data-list-type') || '').trim();
            const entryId = String(markOne.getAttribute('data-entry-id') || '').trim();

            const changed = markEntryReviewed(listType, entryId);
            if (changed) {
                render();
                persistState();
            }
            return;
        }

        const markAll = event.target.closest('[data-action="mark-all-reviewed"]');
        if (markAll && panel.contains(markAll)) {
            event.preventDefault();
            markAllReviewed();
        }
    });

    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) {
            close();
        }
    });

    loadState();
    render();

    const api = {
        pushRealtime,
        pushPending,
        markAllReviewed,
        setChannelPreferences,
        setIntegrationWarning,
        getStats,
        open,
        close,
    };

    panel.__notificationController = api;

    return api;
}
