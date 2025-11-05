const ENDPOINT = '/solicitudes/turnero-data';
const REFRESH_INTERVAL = 30000;

const elements = {
    listado: document.getElementById('turneroListado'),
    empty: document.getElementById('turneroEmpty'),
    lastUpdate: document.getElementById('turneroLastUpdate'),
    refresh: document.getElementById('turneroRefresh'),
    clock: document.getElementById('turneroClock'),
    current: document.getElementById('turneroCurrent'),
};

const defaultEmptyMessage = elements.empty ? elements.empty.textContent : '';

let turneroData = [];
let refreshTimer = null;

const padTurn = turno => String(turno).padStart(2, '0');
const formatTurno = turno => {
    const numero = Number.parseInt(turno, 10);
    if (Number.isNaN(numero) || numero <= 0) {
        return '--';
    }
    return padTurn(numero);
};

const estadoClases = new Map([
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
        ?? turneroData[0];

    renderCurrentTurn(actual);

    const restantes = turneroData.filter(item => item !== actual);
    renderUpcoming(restantes);
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
            if (error.code === 401 && elements.refresh) {
                elements.refresh.setAttribute('disabled', 'disabled');
            }
        });

const scheduleRefresh = () => {
    if (refreshTimer) {
        window.clearInterval(refreshTimer);
    }
    refreshTimer = window.setInterval(fetchTurnero, REFRESH_INTERVAL);
};

const init = () => {
    renderClock();
    window.setInterval(renderClock, 1000);

    fetchTurnero();
    scheduleRefresh();

    if (elements.refresh) {
        elements.refresh.addEventListener('click', () => {
            elements.refresh?.setAttribute('disabled', 'disabled');
            fetchTurnero()
                .finally(() => {
                    elements.refresh?.removeAttribute('disabled');
                });
        });
    }
};

document.addEventListener('DOMContentLoaded', init);
