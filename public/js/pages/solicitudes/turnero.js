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
        const fecha = item.fecha ? `Registrado el ${item.fecha}` : '';
        const hora = item.hora ? `• ${item.hora}` : '';

        col.innerHTML = `
            <div class="turno-card">
                <div class="turno-numero">#${padTurn(item.turno ?? 0)}</div>
                <div class="flex-grow-1">
                    <div class="turno-nombre">${item.full_name ?? 'Paciente sin nombre'}</div>
                    <div class="turno-meta mt-2">
                        ${prioridad ? `<span class="turno-badge" title="Prioridad">${prioridad}</span>` : ''}
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
};

document.addEventListener('DOMContentLoaded', init);
