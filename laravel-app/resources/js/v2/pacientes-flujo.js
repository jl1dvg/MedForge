const STATES = [
    { id: 'PENDIENTE', label: 'Pendientes' },
    { id: 'CONFIRMADO', label: 'Confirmados' },
    { id: 'LLEGADO', label: 'Llegaron' },
    { id: 'OPTOMETRIA', label: 'Optometria' },
    { id: 'OPTOMETRIA_TERMINADO', label: 'Optometria terminada' },
    { id: 'CONSULTA', label: 'Consulta' },
    { id: 'CONSULTA_TERMINADO', label: 'Consulta terminada' },
    { id: 'DILATAR', label: 'Dilatar' },
];

const TYPE_LABELS = {
    visita: 'Visita',
    consulta: 'Consulta',
    optometria: 'Optometria',
    examen: 'Examen',
    cirugia: 'Cirugia',
};

const board = document.querySelector('.patient-flow-board');
const summary = document.getElementById('kanban-summary');
const loader = document.getElementById('loader');
const dateFilter = document.getElementById('kanbanDateFilter');
const affiliationFilter = document.getElementById('kanbanAfiliacionFilter');
const doctorFilter = document.getElementById('kanbanDoctorFilter');
const tabs = Array.from(document.querySelectorAll('.tab-kanban'));

let allItems = [];
let activeType = 'visita';

const today = () => new Date().toISOString().slice(0, 10);

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const text = (value, fallback = '-') => {
    const normalized = String(value ?? '').trim();
    return normalized === '' ? fallback : normalized;
};

const escapeHtml = (value) => text(value, '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const normalizeState = (value) => {
    const raw = String(value || '').trim().toUpperCase();
    if (raw === '') {
        return 'PENDIENTE';
    }

    return raw
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '') || 'PENDIENTE';
};

const patientName = (row) => [row.lname, row.lname2, row.fname, row.mname]
    .map((part) => String(part || '').trim())
    .filter(Boolean)
    .join(' ') || 'Paciente sin nombre';

const classifyType = (row) => {
    const procedure = String(row.procedimiento || row.procedimiento_proyectado || '').toLowerCase();
    if (procedure.includes('cirug')) return 'cirugia';
    if (procedure.includes('opto')) return 'optometria';
    if (procedure.includes('examen') || procedure.includes('oct') || procedure.includes('retino')) return 'examen';
    if (procedure.includes('consulta')) return 'consulta';
    return row.trayectos ? 'visita' : 'consulta';
};

const flattenPayload = (payload) => {
    const rows = Array.isArray(payload) ? payload : [];
    const items = [];

    rows.forEach((row) => {
        const trayectos = Array.isArray(row.trayectos) ? row.trayectos : [];
        if (trayectos.length === 0) {
            items.push({
                ...row,
                type: classifyType(row),
                estado_normalized: normalizeState(row.estado || row.estado_agenda),
                patient_name: patientName(row),
            });
            return;
        }

        trayectos.forEach((trayecto) => {
            items.push({
                ...row,
                ...trayecto,
                type: classifyType(trayecto),
                estado_normalized: normalizeState(trayecto.estado || trayecto.estado_agenda),
                patient_name: patientName(row),
                hora_llegada: row.hora_llegada,
                fecha_visita: row.fecha_visita,
            });
        });
    });

    return items;
};

const setLoading = (enabled) => {
    if (loader) {
        loader.style.display = enabled ? 'block' : 'none';
    }
};

const populateSelect = (select, values, firstLabel) => {
    if (!select) return;
    const current = select.value;
    select.innerHTML = '';

    const first = document.createElement('option');
    first.value = '';
    first.textContent = firstLabel;
    select.appendChild(first);

    values.forEach((value) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        select.appendChild(option);
    });

    select.value = values.includes(current) ? current : '';
};

const refreshFilters = () => {
    const affiliations = Array.from(new Set(allItems.map((row) => text(row.afiliacion, '')).filter(Boolean))).sort();
    const doctors = Array.from(new Set(allItems.map((row) => text(row.doctor || row.doctor_original, '')).filter(Boolean))).sort();

    populateSelect(affiliationFilter, affiliations, 'Todas');
    populateSelect(doctorFilter, doctors, 'Todos');
};

const visibleItems = () => {
    const affiliation = affiliationFilter?.value || '';
    const doctor = doctorFilter?.value || '';

    return allItems.filter((item) => {
        if (activeType !== 'visita' && item.type !== activeType) return false;
        if (affiliation !== '' && text(item.afiliacion, '') !== affiliation) return false;
        if (doctor !== '' && text(item.doctor || item.doctor_original, '') !== doctor) return false;
        return true;
    });
};

const renderSummary = (items) => {
    if (!summary) return;

    const byType = items.reduce((acc, item) => {
        acc[item.type] = (acc[item.type] || 0) + 1;
        return acc;
    }, {});

    summary.innerHTML = `
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <strong>${items.length} trayectos</strong>
            <span>Consultas: ${byType.consulta || 0}</span>
            <span>Optometria: ${byType.optometria || 0}</span>
            <span>Examenes: ${byType.examen || 0}</span>
            <span>Cirugias: ${byType.cirugia || 0}</span>
        </div>
    `;
};

const cardTemplate = (item) => {
    const type = item.type || 'consulta';
    const badge = TYPE_LABELS[type] || TYPE_LABELS.consulta;
    const time = text(item.hora || item.hora_llegada, '');
    const formId = text(item.form_id, '');
    const canMove = formId !== '';

    return `
        <article class="patient-flow-card patient-flow-card--${escapeHtml(type)}" data-form-id="${escapeHtml(formId)}" ${canMove ? '' : 'data-static="1"'}>
            <div class="patient-flow-card__top">
                <span class="patient-flow-card__badge patient-flow-card__badge--${escapeHtml(type)}">${escapeHtml(badge)}</span>
                <span class="patient-flow-card__time">${escapeHtml(time)}</span>
            </div>
            <div class="patient-flow-card__name">${escapeHtml(item.patient_name)}</div>
            <div class="patient-flow-card__meta">
                <span>HC ${escapeHtml(text(item.hc_number))}</span>
                <span>${escapeHtml(text(item.doctor || item.doctor_original, 'Sin medico'))}</span>
                <span>${escapeHtml(text(item.afiliacion, 'Sin afiliacion'))}</span>
            </div>
            <div class="patient-flow-card__procedure">${escapeHtml(text(item.procedimiento || item.procedimiento_proyectado, 'Sin procedimiento'))}</div>
            <div class="patient-flow-card__footer">
                <span>${escapeHtml(text(item.fecha_cambio || item.fecha_visita, ''))}</span>
                <span>${formId ? `Form ${escapeHtml(formId)}` : ''}</span>
            </div>
        </article>
    `;
};

const updateTrayectoState = async (formId, estado) => {
    const response = await fetch('/v2/pacientes/flujo/trayecto-estado', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ form_id: formId, estado }),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || 'No se pudo actualizar el estado.');
    }

    allItems = allItems.map((item) => {
        if (String(item.form_id || '') !== String(formId)) return item;
        return { ...item, estado, estado_normalized: normalizeState(estado) };
    });
};

const enableColumnSorting = () => {
    if (!window.Sortable) return;

    document.querySelectorAll('.patient-flow-items').forEach((column) => {
        window.Sortable.create(column, {
            group: 'pacientes-flujo',
            animation: 150,
            filter: '[data-static="1"]',
            onEnd: async (event) => {
                const formId = event.item?.dataset?.formId || '';
                const targetState = event.to?.dataset?.state || '';
                if (!formId || !targetState) {
                    render();
                    return;
                }

                try {
                    await updateTrayectoState(formId, targetState);
                    render();
                } catch (error) {
                    window.alert(error.message || 'No se pudo actualizar el estado.');
                    render();
                }
            },
        });
    });
};

const render = () => {
    if (!board) return;
    const items = visibleItems();
    renderSummary(items);

    board.innerHTML = STATES.map((state) => {
        const columnItems = items.filter((item) => item.estado_normalized === normalizeState(state.id));
        return `
            <section class="patient-flow-column" data-state="${state.id}">
                <div class="patient-flow-column__header">
                    <h5 class="patient-flow-column__title">${escapeHtml(state.label)}</h5>
                    <span class="patient-flow-column__count">${columnItems.length}</span>
                </div>
                <div class="patient-flow-items" data-state="${state.id}">
                    ${columnItems.map(cardTemplate).join('') || '<div class="text-muted small p-2">Sin pacientes</div>'}
                </div>
            </section>
        `;
    }).join('');

    enableColumnSorting();
};

const loadBoard = async () => {
    setLoading(true);
    try {
        const params = new URLSearchParams({
            fecha: dateFilter?.value || today(),
            modo: activeType === 'visita' ? 'visita' : 'trayecto',
        });
        const response = await fetch(`/v2/pacientes/flujo/tablero?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.error || 'No se pudo cargar el tablero.');
        }

        allItems = flattenPayload(payload);
        refreshFilters();
        render();
    } catch (error) {
        if (board) {
            board.innerHTML = `<div class="alert alert-danger w-100 mb-0">${escapeHtml(error.message || 'No se pudo cargar el flujo de pacientes.')}</div>`;
        }
        renderSummary([]);
    } finally {
        setLoading(false);
    }
};

const bindEvents = () => {
    if (dateFilter) {
        dateFilter.type = 'date';
        dateFilter.value = dateFilter.value || today();
        dateFilter.addEventListener('change', loadBoard);
    }

    affiliationFilter?.addEventListener('change', render);
    doctorFilter?.addEventListener('change', render);

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activeType = tab.dataset.tipo || 'visita';
            tabs.forEach((item) => item.classList.toggle('active', item === tab));
            render();
        });
    });
};

bindEvents();
loadBoard();
