const TONE_TO_AVATAR = {
    success: 'bg-soft-success',
    info: 'bg-soft-info',
    warning: 'bg-soft-warning',
    danger: 'bg-soft-danger',
    primary: 'bg-soft-primary',
};

const DEFAULT_ICON = 'mdi mdi-bell-outline';

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
        .map(item => typeof item === 'string' ? item.trim() : '')
        .filter(Boolean);
}

function sanitizeBadges(badges) {
    return (Array.isArray(badges) ? badges : [])
        .map(badge => ({
            label: escapeHtml(badge.label ?? ''),
            variant: badge.variant ?? 'bg-light text-muted',
        }))
        .filter(badge => badge.label !== '');
}

function normalizeChannels(channels) {
    if (!Array.isArray(channels) || channels.length === 0) {
        return [];
    }

    return channels
        .map(channel => typeof channel === 'string' ? channel.trim() : '')
        .filter(Boolean);
}

function normalizeEntry(entry, fallbackTone = 'info') {
    const timestamp = entry.timestamp instanceof Date
        ? entry.timestamp
        : entry.timestamp
            ? new Date(entry.timestamp)
            : new Date();

    return {
        dedupeKey: entry.dedupeKey || null,
        title: entry.title ? escapeHtml(entry.title) : 'Notificación',
        message: entry.message ? escapeHtml(entry.message) : '',
        meta: sanitizeMeta(entry.meta),
        badges: sanitizeBadges(entry.badges),
        icon: entry.icon || DEFAULT_ICON,
        tone: entry.tone && TONE_TO_AVATAR[entry.tone] ? entry.tone : fallbackTone,
        timestamp,
        dueAt: entry.dueAt instanceof Date
            ? entry.dueAt
            : entry.dueAt
                ? new Date(entry.dueAt)
                : null,
        channels: normalizeChannels(entry.channels),
    };
}

function renderEntry(entry) {
    const avatarClass = TONE_TO_AVATAR[entry.tone] || TONE_TO_AVATAR.info;
    const badgesHtml = entry.badges.map(badge => `
        <span class="badge rounded-pill ${badge.variant}">${badge.label}</span>
    `).join('');

    const metaItems = [...entry.meta];
    if (entry.channels.length > 0) {
        metaItems.push(`Canales: ${escapeHtml(entry.channels.join(', '))}`);
    }

    const metaHtml = metaItems.map(item => `<span>${escapeHtml(item)}</span>`).join('');
    const timeLabel = formatRelative(entry.timestamp);

    return `
        <div class="media py-10 px-0 notification-entry">
            <div class="avatar ${avatarClass}">
                <i class="${escapeHtml(entry.icon)}"></i>
            </div>
            <div class="media-body">
                <p class="fs-16 mb-0"><strong>${entry.title}</strong></p>
                ${entry.message ? `<p class="text-muted mb-1">${entry.message}</p>` : ''}
                ${badgesHtml ? `<div class="notification-meta">${badgesHtml}</div>` : ''}
                ${metaHtml ? `<div class="notification-meta">${metaHtml}</div>` : ''}
                ${timeLabel ? `<time datetime="${entry.timestamp.toISOString()}"><i class="mdi mdi-timer-outline"></i> ${escapeHtml(timeLabel)}</time>` : ''}
            </div>
        </div>
    `;
}

function renderPendingEntry(entry) {
    const avatarClass = TONE_TO_AVATAR[entry.tone] || TONE_TO_AVATAR.primary;
    const dueLabel = entry.dueAt instanceof Date && !Number.isNaN(entry.dueAt.getTime())
        ? entry.dueAt.toLocaleString()
        : '';

    const badgesHtml = entry.badges.map(badge => `
        <span class="badge rounded-pill ${badge.variant}">${badge.label}</span>
    `).join('');

    const metaItems = [...entry.meta];
    if (entry.channels.length > 0) {
        metaItems.push(`Canales: ${escapeHtml(entry.channels.join(', '))}`);
    }

    const metaHtml = metaItems.map(item => `<span>${escapeHtml(item)}</span>`).join('');

    return `
        <div class="media py-10 px-0 notification-entry">
            <div class="avatar ${avatarClass}">
                <i class="${escapeHtml(entry.icon || 'mdi mdi-alarm-check')}"></i>
            </div>
            <div class="media-body">
                <p class="fs-16 mb-0"><strong>${entry.title}</strong></p>
                ${entry.message ? `<p class="text-muted mb-1">${entry.message}</p>` : ''}
                ${badgesHtml ? `<div class="notification-meta">${badgesHtml}</div>` : ''}
                ${metaHtml ? `<div class="notification-meta">${metaHtml}</div>` : ''}
                ${dueLabel ? `<time datetime="${entry.dueAt.toISOString()}"><i class="mdi mdi-calendar-clock"></i> ${escapeHtml(dueLabel)}</time>` : ''}
            </div>
        </div>
    `;
}

export function createNotificationPanel(options = {}) {
    const panel = document.getElementById(options.panelId || 'kanbanNotificationPanel');
    const backdrop = document.getElementById(options.backdropId || 'notificationPanelBackdrop');

    if (!panel || !backdrop) {
        return {
            pushRealtime: () => {},
            pushPending: () => {},
            setChannelPreferences: () => {},
            setIntegrationWarning: () => {},
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

    const toggleSelector = options.toggleSelector || '[data-notification-panel-toggle]';
    const toggleButtons = document.querySelectorAll(toggleSelector);
    const realtimeLimit = options.realtimeLimit || 40;
    const pendingLimit = options.pendingLimit || 40;

    const state = {
        realtime: [],
        pending: [],
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

    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) {
            close();
        }
    });

    const updateCounter = (counter, value) => {
        if (counter) {
            counter.textContent = String(value);
        }
    };

    const renderRealtime = () => {
        if (!realtimeList) {
            return;
        }

        if (state.realtime.length === 0) {
            realtimeList.innerHTML = '<p class="notification-empty">Aún no hay eventos recientes.</p>';
        } else {
            realtimeList.innerHTML = state.realtime
                .map(entry => renderEntry(entry))
                .join('');
        }

        updateCounter(realtimeCounter, state.realtime.length);
    };

    const renderPending = () => {
        if (!pendingList) {
            return;
        }

        if (state.pending.length === 0) {
            pendingList.innerHTML = '<p class="notification-empty">Sin recordatorios pendientes.</p>';
        } else {
            pendingList.innerHTML = state.pending
                .map(entry => renderPendingEntry(entry))
                .join('');
        }

        updateCounter(pendingCounter, state.pending.length);
    };

    const pushRealtime = entry => {
        const normalized = normalizeEntry(entry);

        if (normalized.dedupeKey) {
            state.realtime = state.realtime.filter(item => item.dedupeKey !== normalized.dedupeKey);
        }

        state.realtime.unshift(normalized);
        if (state.realtime.length > realtimeLimit) {
            state.realtime.length = realtimeLimit;
        }

        renderRealtime();
    };

    const pushPending = entry => {
        const normalized = normalizeEntry(entry, 'primary');

        if (normalized.dedupeKey) {
            state.pending = state.pending.filter(item => item.dedupeKey !== normalized.dedupeKey);
        }

        state.pending.push(normalized);
        state.pending.sort((a, b) => {
            const aTime = a.dueAt instanceof Date && !Number.isNaN(a.dueAt.getTime()) ? a.dueAt.getTime() : Number.POSITIVE_INFINITY;
            const bTime = b.dueAt instanceof Date && !Number.isNaN(b.dueAt.getTime()) ? b.dueAt.getTime() : Number.POSITIVE_INFINITY;
            return aTime - bTime;
        });

        if (state.pending.length > pendingLimit) {
            state.pending.length = pendingLimit;
        }

        renderPending();
    };

    const setChannelPreferences = prefs => {
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

    const setIntegrationWarning = message => {
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

    // Initial render state
    renderRealtime();
    renderPending();

    const api = {
        pushRealtime,
        pushPending,
        setChannelPreferences,
        setIntegrationWarning,
        open,
        close,
    };

    panel.__notificationController = api;

    return api;
}
