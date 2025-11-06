const ENDPOINT = '/solicitudes/turnero-data';
const REFRESH_INTERVAL = 30000;

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

    const currentUserId = parseUserId(window?.app?.user_id);
    const realtimeKey = typeof window?.app?.options?.pusher_app_key === 'string'
        ? window.app.options.pusher_app_key.trim()
        : '';
    const realtimeCluster = typeof window?.app?.options?.pusher_cluster === 'string'
        ? window.app.options.pusher_cluster.trim()
        : '';
    const realtimeEnabled = typeof window?.app?.options?.pusher_realtime_notifications !== 'undefined'
        && String(window.app.options.pusher_realtime_notifications).trim() === '1'
        && realtimeKey !== '';

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
        const channel = pusher.subscribe('solicitudes-kanban');

        const bindings = [
            'kanban.nueva-solicitud',
            'kanban.estado-actualizado',
            'crm.detalles-actualizados',
            'crm.nota-registrada',
            'crm.tarea-creada',
            'crm.tarea-actualizada',
            'crm.adjunto-subido',
            'turnero.turno-actualizado',
        ];

        bindings.forEach(eventName => {
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
