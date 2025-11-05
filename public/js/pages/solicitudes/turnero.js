const ENDPOINT = '/solicitudes/turnero-data';
const CALL_NEXT_ENDPOINT = '/solicitudes/turnero-siguiente';
const CALL_ENDPOINT = '/solicitudes/turnero-llamar';
const REFRESH_INTERVAL = 30000;

const elements = {
    listado: document.getElementById('turneroListado'),
    empty: document.getElementById('turneroEmpty'),
    lastUpdate: document.getElementById('turneroLastUpdate'),
    refresh: document.getElementById('turneroRefresh'),
    clock: document.getElementById('turneroClock'),
    current: document.getElementById('turneroCurrent'),
    controls: document.getElementById('turneroControls'),
    callNext: document.getElementById('turneroCallNext'),
    markAttending: document.getElementById('turneroMarkAttending'),
    markDone: document.getElementById('turneroMarkDone'),
    controlStatus: document.getElementById('turneroControlStatus'),
};

const defaultEmptyMessage = elements.empty ? elements.empty.textContent : '';

let turneroData = [];
let statusLock = false;
let statusLockTimer = null;

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

const removeDiacritics = value =>
    value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const getEstadoClass = estado => {
    if (!estado) {
        return '';
    }
    const key = removeDiacritics(String(estado).toLowerCase().trim());
    return estadoClases.get(key) ?? '';
};

const getEstadoSlug = estado => {
    if (!estado) {
        return '';
    }
    const key = removeDiacritics(String(estado).toLowerCase().trim());
    switch (key) {
        case 'recibido':
            return 'recibido';
        case 'llamado':
            return 'llamado';
        case 'en atencion':
            return 'en_atencion';
        case 'atendido':
            return 'atendido';
        default:
            return '';
    }
};

const escapeHtml = value => {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const buildDetalle = item => {
    const fecha = item.fecha ? `Registrado el ${item.fecha}` : '';
    let hora = '';
    if (item.hora) {
        hora = fecha ? `• ${item.hora}` : item.hora;
    }
    const detalle = [fecha, hora].filter(Boolean).join(' ');
    return detalle ? escapeHtml(detalle) : '';
};

const buildTurnoCard = (item, extraClass = '') => {
    const clases = ['turno-card'];
    if (extraClass) {
        clases.push(extraClass);
    }

    const prioridad = item.prioridad ? escapeHtml(String(item.prioridad).toUpperCase()) : '';
    const estado = item.estado ? escapeHtml(String(item.estado)) : '';
    const estadoClass = getEstadoClass(item.estado);
    const detalle = buildDetalle(item);
    const nombre = escapeHtml(item.full_name ?? 'Paciente sin nombre');

    return `
        <div class="${clases.join(' ')}">
            <div class="turno-numero">#${formatTurno(item.turno)}</div>
            <div class="flex-grow-1">
                <div class="turno-nombre">${nombre}</div>
                <div class="turno-meta mt-2">
                    ${prioridad ? `<span class="turno-badge" title="Prioridad">${prioridad}</span>` : ''}
                    ${estado ? `<span class="turno-estado ${estadoClass}">${estado}</span>` : ''}
                    ${detalle ? `<span class="turno-detalle">${detalle}</span>` : ''}
                </div>
            </div>
        </div>
    `;
};

const handleAuthError = () => {
    elements.callNext?.setAttribute('disabled', 'disabled');
    elements.markAttending?.setAttribute('disabled', 'disabled');
    elements.markDone?.setAttribute('disabled', 'disabled');
    elements.refresh?.setAttribute('disabled', 'disabled');
};

function setControlStatus(message, tone = 'info', options = {}) {
    if (!elements.controlStatus) {
        return;
    }

    const { lock = false, force = false } = options;

    if (statusLock && !lock && !force) {
        return;
    }

    elements.controlStatus.classList.remove('text-info', 'text-danger', 'text-success', 'text-warning');

    let toneClass = 'text-info';
    if (tone === 'error') {
        toneClass = 'text-danger';
    } else if (tone === 'success') {
        toneClass = 'text-success';
    } else if (tone === 'warning') {
        toneClass = 'text-warning';
    }

    elements.controlStatus.classList.add(toneClass);
    elements.controlStatus.textContent = message;

    if (statusLockTimer) {
        window.clearTimeout(statusLockTimer);
        statusLockTimer = null;
    }

    if (lock) {
        statusLock = true;
        statusLockTimer = window.setTimeout(() => {
            statusLock = false;
            statusLockTimer = null;
            applyStatusSummary(true);
        }, 6000);
    } else if (force) {
        statusLock = false;
    } else {
        statusLock = false;
    }
}

function applyStatusSummary(force = false) {
    if (!elements.controlStatus) {
        return;
    }

    if (statusLock && !force) {
        return;
    }

    if (!Array.isArray(turneroData) || turneroData.length === 0) {
        setControlStatus('No hay pacientes en cola. Usa "Llamar siguiente" cuando llegue una nueva solicitud.', 'info', { force });
        return;
    }

    const actual = turneroData.find(item => item.estadoSlug === 'en_atencion')
        ?? turneroData.find(item => item.estadoSlug === 'llamado');

    if (actual) {
        const nombre = actual.full_name ?? 'Paciente sin nombre';
        const mensaje = `Turno #${formatTurno(actual.turno)} · ${nombre} (${actual.estado ?? 'En proceso'})`;
        setControlStatus(mensaje, 'info', { force });
        return;
    }

    const siguiente = turneroData.find(item => item.estadoSlug === 'recibido');
    if (siguiente) {
        const nombre = siguiente.full_name ?? 'Paciente sin nombre';
        const mensaje = `Próximo turno disponible: #${formatTurno(siguiente.turno)} · ${nombre}`;
        setControlStatus(mensaje, 'info', { force });
        return;
    }

    setControlStatus('Todos los pacientes han sido atendidos. Esperando nuevas solicitudes.', 'info', { force });
}

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

const renderCurrentTurn = current => {
    if (!elements.current) {
        return;
    }

    if (!current) {
        elements.current.innerHTML = `
            <div class="turnero-current-vacio" role="status">
                Ningún paciente ha sido llamado todavía.
            </div>
        `;
        return;
    }

    elements.current.innerHTML = buildTurnoCard(current, 'turnero-current-card');
};

const renderUpcoming = items => {
    if (!elements.listado) {
        return;
    }

    elements.listado.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
        return;
    }

    const fragment = document.createDocumentFragment();

    items.forEach(item => {
        const col = document.createElement('div');
        col.className = 'col-12 col-lg-6 col-xxl-4';
        col.innerHTML = buildTurnoCard(item);
        fragment.appendChild(col);
    });

    elements.listado.appendChild(fragment);
};

const updateControlsAvailability = (items = []) => {
    if (elements.callNext) {
        elements.callNext.toggleAttribute('disabled', false);
    }

    const hasLlamado = items.some(item => item.estadoSlug === 'llamado');
    const hasEnAtencion = items.some(item => item.estadoSlug === 'en_atencion');

    if (elements.markAttending) {
        elements.markAttending.toggleAttribute('disabled', !hasLlamado);
    }

    if (elements.markDone) {
        elements.markDone.toggleAttribute('disabled', !hasEnAtencion);
    }
};

const renderTurnos = turnos => {
    if (!elements.listado || !elements.empty) {
        return;
    }

    if (!Array.isArray(turnos) || turnos.length === 0) {
        turneroData = [];
        renderCurrentTurn(null);
        elements.listado.innerHTML = '';
        elements.empty.style.display = '';
        elements.empty.textContent = defaultEmptyMessage;
        updateControlsAvailability([]);
        applyStatusSummary();
        return;
    }

    elements.empty.style.display = 'none';
    elements.empty.textContent = defaultEmptyMessage;

    turneroData = turnos.map(item => ({
        ...item,
        estadoSlug: getEstadoSlug(item.estado),
    }));

    const actual = turneroData.find(item => item.estadoSlug === 'en_atencion')
        ?? turneroData.find(item => item.estadoSlug === 'llamado')
        ?? null;

    renderCurrentTurn(actual);

    const restantes = turneroData.filter(item => item !== actual);

    renderUpcoming(restantes);

    updateControlsAvailability(turneroData);
    applyStatusSummary();
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

const fetchJson = (url, options = {}, errorMessage = 'Error inesperado') => {
    const config = {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.headers ?? {}),
        },
        ...options,
    };

    return fetch(url, config).then(response => {
        if (response.status === 401) {
            const error = new Error('Sesión expirada');
            error.code = 401;
            throw error;
        }

        if (!response.ok) {
            const error = new Error(errorMessage);
            error.code = response.status;
            throw error;
        }

        return response.json();
    });
};

const postJson = (url, payload = {}, errorMessage = 'Error inesperado') =>
    fetchJson(
        url,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        },
        errorMessage
    );

const fetchTurnero = () =>
    fetchJson(ENDPOINT, {}, 'No se pudo cargar el turnero')
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
            setControlStatus(error.message, 'error', { lock: true });
            if (error.code === 401) {
                renderTurnos([]);
                handleAuthError();
            }
        });

const handleCallNext = () => {
    elements.callNext?.setAttribute('disabled', 'disabled');
    setControlStatus('Llamando al siguiente paciente...', 'info', { lock: true });

    return postJson(CALL_NEXT_ENDPOINT, {}, 'No se pudo llamar al siguiente turno')
        .then(({ success, data, error }) => {
            if (!success || !data) {
                setControlStatus(error || 'No hay turnos pendientes en la cola.', 'warning', { lock: true });
                return fetchTurnero();
            }

            const nombre = data.full_name ?? 'Paciente sin nombre';
            const estado = data.estado ?? 'Llamado';
            setControlStatus(`Turno #${formatTurno(data.turno)} · ${nombre} (${estado})`, 'success', { lock: true });
            return fetchTurnero();
        })
        .catch(err => {
            console.error('❌ Error al llamar al siguiente turno:', err);
            setControlStatus(err.message || 'No se pudo llamar al siguiente turno', 'error', { lock: true });
            if (err.code === 401) {
                handleAuthError();
            }
        })
        .finally(() => {
            elements.callNext?.removeAttribute('disabled');
        });
};

const handleMarkAttending = () => {
    const llamado = turneroData.find(item => item.estadoSlug === 'llamado');

    if (!llamado) {
        setControlStatus('No hay turnos llamados para marcar en atención.', 'warning', { lock: true });
        return;
    }

    elements.markAttending?.setAttribute('disabled', 'disabled');
    setControlStatus(`Marcando en atención el turno #${formatTurno(llamado.turno)}...`, 'info', { lock: true });

    return postJson(CALL_ENDPOINT, { id: llamado.id, estado: 'En atención' }, 'No se pudo actualizar el turno')
        .then(({ success, data, error }) => {
            if (!success || !data) {
                setControlStatus(error || 'No se pudo marcar el turno en atención.', 'error', { lock: true });
                return fetchTurnero();
            }

            const nombre = data.full_name ?? 'Paciente sin nombre';
            setControlStatus(`Turno #${formatTurno(data.turno)} en atención (${nombre}).`, 'success', { lock: true });
            return fetchTurnero();
        })
        .catch(err => {
            console.error('❌ Error al marcar turno en atención:', err);
            setControlStatus(err.message || 'No se pudo marcar el turno en atención', 'error', { lock: true });
            if (err.code === 401) {
                handleAuthError();
            }
        })
        .finally(() => {
            elements.markAttending?.removeAttribute('disabled');
        });
};

const handleMarkDone = () => {
    const enAtencion = turneroData.find(item => item.estadoSlug === 'en_atencion');

    if (!enAtencion) {
        setControlStatus('No hay turnos en atención para finalizar.', 'warning', { lock: true });
        return;
    }

    elements.markDone?.setAttribute('disabled', 'disabled');
    setControlStatus(`Finalizando el turno #${formatTurno(enAtencion.turno)}...`, 'info', { lock: true });

    return postJson(CALL_ENDPOINT, { id: enAtencion.id, estado: 'Atendido' }, 'No se pudo finalizar el turno')
        .then(({ success, data, error }) => {
            if (!success || !data) {
                setControlStatus(error || 'No se pudo finalizar el turno.', 'error', { lock: true });
                return fetchTurnero();
            }

            const nombre = data.full_name ?? 'Paciente sin nombre';
            setControlStatus(`Turno #${formatTurno(data.turno)} atendido (${nombre}).`, 'success', { lock: true });
            return fetchTurnero();
        })
        .catch(err => {
            console.error('❌ Error al finalizar el turno:', err);
            setControlStatus(err.message || 'No se pudo finalizar el turno', 'error', { lock: true });
            if (err.code === 401) {
                handleAuthError();
            }
        })
        .finally(() => {
            elements.markDone?.removeAttribute('disabled');
        });
};

const init = () => {
    renderClock();
    setInterval(renderClock, 1000);

    setControlStatus('Cargando turnero...', 'info');

    fetchTurnero();
    setInterval(fetchTurnero, REFRESH_INTERVAL);

    if (elements.refresh) {
        elements.refresh.addEventListener('click', () => {
            elements.refresh?.setAttribute('disabled', 'disabled');
            fetchTurnero().finally(() => {
                elements.refresh?.removeAttribute('disabled');
            });
        });
    }

    if (elements.callNext) {
        elements.callNext.addEventListener('click', handleCallNext);
    }

    if (elements.markAttending) {
        elements.markAttending.addEventListener('click', handleMarkAttending);
    }

    if (elements.markDone) {
        elements.markDone.addEventListener('click', handleMarkDone);
    }
};

document.addEventListener('DOMContentLoaded', init);
