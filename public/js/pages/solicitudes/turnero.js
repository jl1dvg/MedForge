import { getKanbanConfig, getRealtimeConfig } from './kanban/config.js';

const ENDPOINT = `${getKanbanConfig().basePath}/turnero-data`;
const REFRESH_INTERVAL = 30000;

const DEFAULT_CHANNELS = {
    solicitudes: 'solicitudes-kanban',
    examenes: 'examenes-kanban',
};

const BASE_EVENT_FALLBACKS = {
    new_request: 'kanban.nueva-solicitud',
    status_updated: 'kanban.estado-actualizado',
    crm_updated: 'crm.detalles-actualizados',
    turnero_updated: 'turnero.turno-actualizado',
    surgery_reminder: 'recordatorio-cirugia',
    preop_reminder: 'recordatorio-preop',
    postop_reminder: 'recordatorio-postop',
    exams_expiring: 'alerta-examenes-por-vencer',
    exam_reminder: 'recordatorio-examen',
};

const MODULE_EVENT_OVERRIDES = {
    examenes: {
        new_request: 'kanban.nueva-examen',
    },
};

const CRM_EXTRA_EVENTS = [
    'crm.detalles-actualizados',
    'crm.nota-registrada',
    'crm.tarea-creada',
    'crm.tarea-actualizada',
    'crm.adjunto-subido',
];

const LEGACY_EVENT_NAMES = {
    solicitudes: [
        'nueva-solicitud',
        'estado-actualizado',
        'crm-actualizado',
    ],
    examenes: [
        'nuevo-examen',
        'examen-actualizado',
        'crm-examen-actualizado',
    ],
};

const elements = {
    listado: document.getElementById('turneroListado'),
    empty: document.getElementById('turneroEmpty'),
    lastUpdate: document.getElementById('turneroLastUpdate'),
    refresh: document.getElementById('turneroRefresh'),
    clock: document.getElementById('turneroClock'),
};
const defaultEmptyMessage = elements.empty ? elements.empty.textContent : '';

const padTurn = turno => String(turno).padStart(2, '0');
const formatTurno = turno => {
    const numero = Number.parseInt(turno, 10);
    if (Number.isNaN(numero) || numero <= 0) {
        return '--';
    }
    return padTurn(numero);
};

const estadoClases = new Map([
    ['recibido', 'recibido'],
    ['llamado', 'llamado'],
    ['en atencion', 'en-atencion'],
    ['en atención', 'en-atencion'],
    ['atendido', 'atendido'],
]);

const getEstadoClass = estado => {
    if (!estado) {
        return '';
    }
    const key = String(estado).toLowerCase().trim();
    return estadoClases.get(key) ?? '';
};

const renderClock = () => {
    if (!elements.clock) {
        return;
    }
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    elements.clock.textContent = formatter.format(now);
};

const renderTurnos = turnos => {
    if (!elements.listado || !elements.empty) {
        return;
    }

    elements.listado.innerHTML = '';

    if (!Array.isArray(turnos) || turnos.length === 0) {
        elements.empty.style.display = '';
        elements.empty.textContent = defaultEmptyMessage;
        return;
    }

    elements.empty.style.display = 'none';
    elements.empty.textContent = defaultEmptyMessage;

    const fragment = document.createDocumentFragment();

    turnos.forEach(item => {
        const col = document.createElement('div');
        col.className = 'col-12 col-lg-6 col-xxl-4';

        const prioridad = item.prioridad ? String(item.prioridad).toUpperCase() : '';
        const estado = item.estado ? String(item.estado) : '';
        const estadoClass = getEstadoClass(estado);
        const fecha = item.fecha ? `Registrado el ${item.fecha}` : '';
        const hora = item.hora ? `• ${item.hora}` : '';

        col.innerHTML = `
            <div class="turno-card">
                <div class="turno-numero">#${formatTurno(item.turno)}</div>
                <div class="flex-grow-1">
                    <div class="turno-nombre">${item.full_name ?? 'Paciente sin nombre'}</div>
                    <div class="turno-meta mt-2">
                        ${prioridad ? `<span class="turno-badge" title="Prioridad">${prioridad}</span>` : ''}
                        ${estado ? `<span class="turno-estado ${estadoClass}">${estado}</span>` : ''}
                        ${(fecha || hora) ? `<span class="turno-detalle">${fecha} ${hora}</span>` : ''}
                    </div>
                </div>
            </div>
        `;

        fragment.appendChild(col);
    });

    elements.listado.appendChild(fragment);
};

const updateLastUpdate = () => {
    if (!elements.lastUpdate) {
        return;
    }
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
    elements.lastUpdate.textContent = `Última actualización: ${formatter.format(now)}`;
};

const fetchTurnero = () => {
    return fetch(ENDPOINT, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
        },
    })
        .then(response => {
            if (response.status === 401) {
                throw new Error('Sesión expirada');
            }

            if (!response.ok) {
                throw new Error('No se pudo cargar el turnero');
            }

            return response.json();
        })
        .then(({ data }) => {
            renderTurnos(Array.isArray(data) ? data : []);
            updateLastUpdate();
        })
        .catch(error => {
            console.error('❌ Error al actualizar el turnero:', error);
            if (elements.lastUpdate) {
                elements.lastUpdate.textContent = error.message;
            }
            if (elements.empty) {
                elements.empty.style.display = '';
                elements.empty.textContent = error.message;
            }
        });
};

const init = () => {
    renderClock();
    setInterval(renderClock, 1000);

    fetchTurnero();
    setInterval(fetchTurnero, REFRESH_INTERVAL);

    if (elements.refresh) {
        elements.refresh.addEventListener('click', () => {
            elements.refresh.setAttribute('disabled', 'disabled');
            fetchTurnero().finally(() => {
                elements.refresh?.removeAttribute('disabled');
            });
        });
    }

    const parseUserId = value => {
        const num = Number.parseInt(value, 10);
        return Number.isNaN(num) ? null : num;
    };

    const kanbanConfig = getKanbanConfig();
    const realtimeConfig = getRealtimeConfig();
    const moduleKey = (kanbanConfig.key || 'solicitudes').toString();
    const appOptions = window?.app?.options || {};

    const realtimeKey = (realtimeConfig.key || appOptions.pusher_app_key || '').toString().trim();
    const realtimeCluster = (realtimeConfig.cluster || appOptions.pusher_cluster || '').toString().trim();

    const enabledByApp = typeof appOptions.pusher_realtime_notifications !== 'undefined'
        && String(appOptions.pusher_realtime_notifications).trim() === '1';
    const realtimeEnabled = (Boolean(realtimeConfig.enabled) || enabledByApp) && realtimeKey !== '';

    const fallbackEvents = {
        ...BASE_EVENT_FALLBACKS,
        ...(MODULE_EVENT_OVERRIDES[moduleKey] || {}),
    };

    const configuredEvents = (realtimeConfig.events && typeof realtimeConfig.events === 'object')
        ? realtimeConfig.events
        : {};

    const resolvedEvents = { ...fallbackEvents, ...configuredEvents };
    if (typeof realtimeConfig.event === 'string' && realtimeConfig.event.trim() !== '') {
        const defaultEvent = realtimeConfig.event.trim();
        if (!resolvedEvents.new_request) {
            resolvedEvents.new_request = defaultEvent;
        }
    }

    const eventNames = new Set();
    Object.values(resolvedEvents)
        .concat(Object.values(configuredEvents))
        .concat(typeof realtimeConfig.event === 'string' ? [realtimeConfig.event] : [])
        .forEach(name => {
            if (typeof name === 'string' && name.trim() !== '') {
                eventNames.add(name.trim());
            }
        });

    CRM_EXTRA_EVENTS.forEach(name => eventNames.add(name));
    (LEGACY_EVENT_NAMES[moduleKey] || []).forEach(name => eventNames.add(name));

    const channelName = (() => {
        const configuredChannel = typeof realtimeConfig.channel === 'string'
            ? realtimeConfig.channel.trim()
            : '';
        if (configuredChannel !== '') {
            return configuredChannel;
        }
        return DEFAULT_CHANNELS[moduleKey] || DEFAULT_CHANNELS.solicitudes;
    })();

    const currentUserId = parseUserId(window?.app?.user_id);

    const shouldIgnoreEvent = payload => {
        const triggered = parseUserId(payload?.triggered_by ?? payload?.user_id ?? payload?.staff_id);
        return triggered !== null && currentUserId !== null && triggered === currentUserId;
    };

    let realtimeRefreshTimeout = null;
    const scheduleRealtimeRefresh = () => {
        if (realtimeRefreshTimeout) {
            clearTimeout(realtimeRefreshTimeout);
        }
        realtimeRefreshTimeout = setTimeout(() => {
            fetchTurnero();
        }, 750);
    };

    if (typeof Pusher !== 'undefined' && realtimeEnabled) {
        const options = { forceTLS: true };
        if (realtimeCluster !== '') {
            options.cluster = realtimeCluster;
        }

        const pusher = new Pusher(realtimeKey, options);
        const channel = pusher.subscribe(channelName);

        eventNames.forEach(eventName => {
            channel.bind(eventName, data => {
                if (shouldIgnoreEvent(data)) {
                    return;
                }
                scheduleRealtimeRefresh();
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', init);
