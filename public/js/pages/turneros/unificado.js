const REFRESH_INTERVAL = 30000;
const ESTADOS_PARAM = encodeURIComponent('Recibido,Llamado,En atención,Atendido');

const container = document.getElementById('turneroGrid');
const panelConfigs = (typeof window !== 'undefined' && window.TURNERO_UNIFICADO_PANELES) || {};

const elements = {
    clock: document.getElementById('turneroClock'),
    refresh: document.getElementById('turneroRefresh'),
    lastUpdate: document.getElementById('turneroLastUpdate'),
};

const stateOrder = ['en espera', 'llamado', 'en atencion', 'atendido'];
const estadoClases = new Map([
    ['en espera', 'recibido'],
    ['llamado', 'llamado'],
    ['en atencion', 'en-atencion'],
    ['atendido', 'atendido'],
]);

const normalizeText = value => {
    if (typeof value !== 'string') return '';
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[_-]+/g, ' ')
        .toLowerCase()
        .trim();
};

const normalizeEstado = raw => {
    const normalized = normalizeText(raw);
    if (normalized === 'recibido' || normalized === '') return 'en espera';
    return normalized;
};

const formatTurno = turno => {
    const numero = Number.parseInt(turno, 10);
    if (Number.isNaN(numero) || numero <= 0) return '--';
    return String(numero).padStart(2, '0');
};

const priorityScore = prioridad => {
    const normalized = normalizeText(prioridad);
    if (normalized === 'si' || normalized === 'alta' || normalized === 'urgente') return 0;
    if (normalized) return 1;
    return 2;
};

const estadoScore = estado => {
    const normalized = normalizeEstado(estado);
    const index = stateOrder.indexOf(normalized);
    return index >= 0 ? index : stateOrder.length + 1;
};

const parseTurnoNumero = turno => {
    const numero = Number.parseInt(turno, 10);
    return Number.isNaN(numero) ? Number.POSITIVE_INFINITY : numero;
};

const buildDetalle = ({ fecha, hora }) => {
    const partes = [];
    if (fecha) partes.push(`Registrado el ${fecha}`);
    if (hora) partes.push(hora);
    if (partes.length === 0) return '';
    if (partes.length === 1) return partes[0];
    return `${partes[0]} • ${partes[1]}`;
};

const buildCard = item => {
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

    const descripcionTexto = item?.examen_nombre || item?.procedimiento || '';
    if (descripcionTexto) {
        const descripcion = document.createElement('div');
        descripcion.className = 'turno-descripcion';
        descripcion.textContent = descripcionTexto;
        detalles.appendChild(descripcion);
    }

    const meta = document.createElement('div');
    meta.className = 'turno-meta mt-1';
    detalles.appendChild(meta);

    const prioridad = item?.prioridad ? String(item.prioridad) : '';
    if (prioridad) {
        const badge = document.createElement('span');
        badge.className = 'turno-badge';
        badge.title = 'Prioridad';
        badge.textContent = prioridad.toUpperCase();
        meta.appendChild(badge);
    }

    const estado = normalizeEstado(item?.estado);
    if (estado) {
        const estadoEl = document.createElement('span');
        const estadoClass = estadoClases.get(estado) ?? '';
        estadoEl.className = `turno-estado${estadoClass ? ` ${estadoClass}` : ''}`;
        estadoEl.textContent = estado.replace('en ', 'En ');
        meta.appendChild(estadoEl);
        card.dataset.estado = estado;
    }

    const detalle = buildDetalle(item);
    if (detalle) {
        const detalleEl = document.createElement('span');
        detalleEl.className = 'turno-detalle';
        detalleEl.textContent = detalle;
        meta.appendChild(detalleEl);
    }

    const prioridadValue = priorityScore(prioridad);
    if (prioridadValue === 0) {
        card.classList.add('is-priority');
    }

    if (estado === 'llamado') {
        card.classList.add('is-llamado');
        card.setAttribute('aria-live', 'assertive');
    }

    return card;
};

const columns = {};

const initColumns = () => {
    if (!container || typeof panelConfigs !== 'object') return;

    Object.entries(panelConfigs).forEach(([key, config]) => {
        columns[key] = {
            key,
            config,
            listado: document.getElementById(`listado-${key}`),
            empty: document.getElementById(`empty-${key}`),
            counters: container.querySelectorAll(`[data-counter^="${key}-"]`),
            filterButtons: container.querySelectorAll(`[data-key="${key}"] .chip-filter`),
            filterState: 'all',
        };
    });
};

const setEmptyVisibility = (columnKey, visible) => {
    const column = columns[columnKey];
    if (!column?.empty) return;
    column.empty.setAttribute('aria-hidden', visible ? 'false' : 'true');
};

const clearListado = columnKey => {
    const column = columns[columnKey];
    if (!column?.listado) return;
    if (typeof column.listado.replaceChildren === 'function') {
        column.listado.replaceChildren();
    } else {
        column.listado.innerHTML = '';
    }
};

const updateCounters = (columnKey, items) => {
    const column = columns[columnKey];
    if (!column) return;
    const totals = {
        'en espera': 0,
        llamado: 0,
        'en atencion': 0,
        atendido: 0,
    };

    items.forEach(item => {
        const estado = normalizeEstado(item.estado);
        const key = totals.hasOwnProperty(estado) ? estado : 'en espera';
        totals[key] += 1;
    });

    column.counters.forEach(counter => {
        const state = counter.dataset.counter?.split('-')[1];
        counter.textContent = totals[state] ?? 0;
    });
};

const shouldRenderItem = (columnKey, item) => {
    const column = columns[columnKey];
    if (!column) return false;
    if (column.filterState === 'all') return true;
    const estado = normalizeEstado(item.estado);
    return estado === column.filterState;
};

const sortItems = items => {
    return [...items].sort((a, b) => {
        const prioridadDiff = priorityScore(a.prioridad) - priorityScore(b.prioridad);
        if (prioridadDiff !== 0) return prioridadDiff;

        const estadoDiff = estadoScore(a.estado) - estadoScore(b.estado);
        if (estadoDiff !== 0) return estadoDiff;

        const turnoDiff = parseTurnoNumero(a.turno) - parseTurnoNumero(b.turno);
        if (!Number.isNaN(turnoDiff) && turnoDiff !== 0) return turnoDiff;

        const fechaA = a.created_at ? new Date(a.created_at).getTime() : 0;
        const fechaB = b.created_at ? new Date(b.created_at).getTime() : 0;
        return fechaA - fechaB;
    });
};

const renderColumn = (columnKey, items) => {
    const column = columns[columnKey];
    if (!column?.listado || !column.empty) return;

    const filtered = sortItems(items).filter(item => shouldRenderItem(columnKey, item));
    clearListado(columnKey);

    if (filtered.length === 0) {
        setEmptyVisibility(columnKey, true);
        updateCounters(columnKey, items);
        return;
    }

    setEmptyVisibility(columnKey, false);
    const fragment = document.createDocumentFragment();
    filtered.forEach(item => fragment.appendChild(buildCard(item)));
    column.listado.appendChild(fragment);
    updateCounters(columnKey, items);
};

const renderClock = () => {
    if (!elements.clock) return;
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    elements.clock.textContent = formatter.format(now);
};

const setLastUpdate = () => {
    if (!elements.lastUpdate) return;
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('es-EC', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    elements.lastUpdate.textContent = `Última actualización: ${formatter.format(now)}`;
};

const fetchColumn = async columnKey => {
    const column = columns[columnKey];
    const endpoint = column?.config?.endpoint;
    if (!endpoint) return [];

    const response = await fetch(`${endpoint}?estado=${ESTADOS_PARAM}`, {
        credentials: 'same-origin',
    });

    if (!response.ok) return [];
    const payload = await response.json();
    if (!payload || !Array.isArray(payload.data)) return [];
    return payload.data;
};

const refresh = async () => {
    if (!container) return;
    const keys = Object.keys(columns);
    const results = await Promise.all(keys.map(key => fetchColumn(key)));
    keys.forEach((key, index) => renderColumn(key, results[index] || []));
    setLastUpdate();
};

const bindFilters = () => {
    Object.values(columns).forEach(column => {
        column.filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                column.filterButtons.forEach(other => other.setAttribute('aria-pressed', 'false'));
                btn.setAttribute('aria-pressed', 'true');
                column.filterState = btn.dataset.filterState || 'all';
                refresh();
            });
        });
    });
};

const start = () => {
    initColumns();
    if (!Object.keys(columns).length) return;

    bindFilters();
    renderClock();
    setInterval(renderClock, 1000);

    elements.refresh?.addEventListener('click', refresh);

    refresh();
    setInterval(refresh, REFRESH_INTERVAL);
};

document.addEventListener('DOMContentLoaded', start);
