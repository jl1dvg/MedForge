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
const defaultEmptyMessage = elements.empty ? elements.empty.textContent.trim() : '';

const padTurn = turno => String(turno).padStart(2, '0');
const formatTurno = turno => {
    const numero = Number.parseInt(turno, 10);
    if (Number.isNaN(numero) || numero <= 0) {
        return '--';
    }
    return padTurn(numero);
};

const normalizeText = value => {
    if (typeof value !== 'string') {
        return '';
    }

    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[_-]+/g, ' ')
        .toLowerCase()
        .trim();
};

const estadoClases = new Map([
    ['recibido', 'recibido'],
    ['llamado', 'llamado'],
    ['en atencion', 'en-atencion'],
    ['atendido', 'atendido'],
]);

const estadoPrioridad = new Map([
    ['llamado', 0],
    ['en atencion', 1],
    ['recibido', 2],
    ['en espera', 3],
    ['atendido', 4],
]);

const getEstadoClass = estado => {
    const key = normalizeText(estado);
    return estadoClases.get(key) ?? '';
};

const getEstadoPriority = estado => {
    const key = normalizeText(estado);
    return estadoPrioridad.get(key) ?? 99;
};

const parseTurnoNumero = turno => {
    const numero = Number.parseInt(turno, 10);
    return Number.isNaN(numero) ? Number.POSITIVE_INFINITY : numero;
};

const getPrioridadScore = prioridad => {
    const normalized = normalizeText(prioridad);
    if (normalized === 'si' || normalized === 'sí' || normalized === 'alta') {
        return 0;
    }
    if (normalized === 'no' || normalized === '') {
        return 1;
    }
    return 2;
};

const buildDetalle = ({ fecha, hora }) => {
    const partes = [];
    if (fecha) {
        partes.push(`Registrado el ${fecha}`);
    }
    if (hora) {
        partes.push(hora);
    }
    if (partes.length === 0) {
        return '';
    }
    if (partes.length === 1) {
        return partes[0];
    }
    return `${partes[0]} • ${partes[1]}`;
};

const createTurnoCard = item => {
    const card = document.createElement('article');
    card.className = 'turno-card';
    card.setAttribute('role', 'listitem');

    const numero = document.createElement('div');
    numero.className = 'turno-numero';
    numero.textContent = `#${formatTurno(item.turno)}`;
    card.appendChild(numero);

    const detalles = document.createElement('div');
    detalles.className = 'turno-detalles';
    card.appendChild(detalles);

    const nombre = document.createElement('div');
    nombre.className = 'turno-nombre';
    nombre.textContent = item?.full_name ? String(item.full_name) : 'Paciente sin nombre';
    detalles.appendChild(nombre);

    const meta = document.createElement('div');
    meta.className = 'turno-meta mt-2';
    detalles.appendChild(meta);

    const prioridad = item?.prioridad ? String(item.prioridad).toUpperCase() : '';
    if (prioridad) {
        const badge = document.createElement('span');
        badge.className = 'turno-badge';
        badge.title = 'Prioridad';
        badge.textContent = prioridad;
        meta.appendChild(badge);
    }

    const estado = item?.estado ? String(item.estado) : '';
    const estadoClass = getEstadoClass(estado);
    const estadoNormalized = normalizeText(estado);
    if (estado) {
        const estadoEl = document.createElement('span');
        estadoEl.className = `turno-estado${estadoClass ? ` ${estadoClass}` : ''}`;
        estadoEl.textContent = estado;
        meta.appendChild(estadoEl);
    }

    const detalle = buildDetalle(item);
    if (detalle) {
        const detalleEl = document.createElement('span');
        detalleEl.className = 'turno-detalle';
        detalleEl.textContent = detalle;
        meta.appendChild(detalleEl);
    }

    if (estadoNormalized) {
        card.dataset.estado = estadoNormalized;
    }

    if (estadoNormalized === 'llamado') {
        card.classList.add('is-llamado');
        card.setAttribute('aria-live', 'assertive');
    }

    if (prioridad) {
        card.dataset.prioridad = normalizeText(prioridad);
    }

    return card;
};

const clearListado = () => {
    if (!elements.listado) {
        return;
    }

    if (typeof elements.listado.replaceChildren === 'function') {
        elements.listado.replaceChildren();
    } else {
        elements.listado.innerHTML = '';
    }

    elements.listado.setAttribute('data-turnos', '0');
};

const setEmptyVisibility = (visible, message = defaultEmptyMessage) => {
    if (!elements.empty) {
        return;
    }

    elements.empty.textContent = message;
    elements.empty.setAttribute('aria-hidden', visible ? 'false' : 'true');
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

    if (!Array.isArray(turnos) || turnos.length === 0) {
        clearListado();
        setEmptyVisibility(true, defaultEmptyMessage);
        return;
    }

    setEmptyVisibility(false, defaultEmptyMessage);

    const ordenados = [...turnos].sort((a, b) => {
        const estadoDiff = getEstadoPriority(a?.estado) - getEstadoPriority(b?.estado);
        if (estadoDiff !== 0) {
            return estadoDiff;
        }

        const prioridadDiff = getPrioridadScore(a?.prioridad) - getPrioridadScore(b?.prioridad);
        if (prioridadDiff !== 0) {
            return prioridadDiff;
        }

        const turnoDiff = parseTurnoNumero(a?.turno) - parseTurnoNumero(b?.turno);
        if (turnoDiff !== 0) {
            return turnoDiff;
        }

        const nombreA = a?.full_name ? String(a.full_name) : '';
        const nombreB = b?.full_name ? String(b.full_name) : '';
        return nombreA.localeCompare(nombreB, 'es', { sensitivity: 'base' });
    });

    const fragment = document.createDocumentFragment();
    ordenados.forEach(item => {
        fragment.appendChild(createTurnoCard(item));
    });

    if (typeof elements.listado.replaceChildren === 'function') {
        elements.listado.replaceChildren(fragment);
    } else {
        elements.listado.innerHTML = '';
        elements.listado.appendChild(fragment);
    }

    elements.listado.setAttribute('data-turnos', String(ordenados.length));
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
            clearListado();
            setEmptyVisibility(true, error.message || 'No se pudo cargar el turnero');
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
