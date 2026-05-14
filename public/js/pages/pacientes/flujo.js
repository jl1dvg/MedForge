let allSolicitudes = [];
let ultimoTimestamp = null;
let pollingIntervalId = null;

const STAGES = {
    visita: [
        {label: 'Agendado', id: 'agendado'},
        {label: 'Llegado', id: 'llegado'},
        {label: 'En atención', id: 'en-atencion'},
        {label: 'Alta', id: 'alta'},
        {label: 'Otro', id: 'otro'},
    ],
    consulta: [
        {label: 'Agendado', id: 'agendado'},
        {label: 'Llegado', id: 'llegado'},
        {label: 'En consulta', id: 'en-consulta'},
        {label: 'Alta', id: 'alta'},
        {label: 'Otro', id: 'otro'},
    ],
    optometria: [
        {label: 'Agendado', id: 'agendado'},
        {label: 'Llegado', id: 'llegado'},
        {label: 'Optometria', id: 'optometria'},
        {label: 'Dilatando', id: 'dilatando'},
        {label: 'Alta', id: 'alta'},
        {label: 'Otro', id: 'otro'},
    ],
    cirugia: [
        {label: 'Agendado', id: 'agendado'},
        {label: 'Llegado', id: 'llegado'},
        {label: 'Preoperatorio', id: 'preoperatorio'},
        {label: 'En quirófano', id: 'en-quirofano'},
        {label: 'Recuperación', id: 'recuperacion'},
        {label: 'Alta', id: 'alta'},
        {label: 'Otro', id: 'otro'},
    ],
};

const TYPE_LABELS = {
    consulta: 'Consulta',
    optometria: 'Optometría',
    cirugia: 'Cirugía',
};

function showLoader() {
    const loader = document.getElementById('loader');
    if (loader) loader.style.display = 'block';
}

function hideLoader() {
    const loader = document.getElementById('loader');
    if (loader) loader.style.display = 'none';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function normalizeText(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .trim();
}

function slug(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function estadoSlug(estado) {
    const value = slug(estado);
    if (value === 'admision') return 'admision';
    if (value === 'llegado' || value === 'confirmado' || value === 'confirmada' || value.includes('confirmado') || value.includes('confirmada')) return 'llegado';
    if (value === 'atendido' || value === 'atendida' || value === 'terminado' || value === 'terminada' || value === 'consulta_terminado' || value === 'consulta-terminado' || value === 'completado' || value === 'completada') return 'alta';
    if (value === 'alta-medica' || value === 'dado-de-alta' || value === 'dada-de-alta') return 'alta';
    if (value === 'en-quirofano' || value === 'quirofano') return 'en-quirofano';
    if (value === 'recuperacion') return 'recuperacion';
    if (value === 'revision-resultados' || value === 'revision-de-resultados') return 'revision-resultados';
    if (value === 'optometria') return 'optometria';
    if (value === 'dilatar' || value === 'dilatando') return 'dilatando';
    if (value === 'consulta' || value === 'en-consulta') return 'en-consulta';
    if (value === 'en-atencion') return 'en-atencion';
    return value || 'otro';
}


function tipoTrayecto(trayecto) {
    const proc = normalizeText(trayecto.procedimiento);
    const doctor = normalizeText(trayecto.doctor);

    if (proc.startsWith('CIRUGIAS')) return 'cirugia';
    if (proc.includes('OPTOMETRIA') || doctor.includes('OPTOMETR')) return 'optometria';

    return 'consulta';
}

function formatearProcedimientoCorto(proc) {
    if (!proc || typeof proc !== 'string') return '';
    const partes = proc.split(' - ');
    if (proc.startsWith('CIRUGIAS')) return partes.slice(2).join(' - ') || 'Cirugía';
    if (proc.startsWith('IMAGENES')) return partes.find(p => p.includes('(')) || partes[3] || partes[2] || 'Imagen';
    if (proc.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES')) return partes[3] || partes[2] || 'Servicio';
    return partes[3] || partes[2] || proc;
}

function estadoOrdenIndex(tipo, estado) {
    const id = estadoSlug(estado);
    const index = (STAGES[tipo] || STAGES.consulta).findIndex(stage => stage.id === id);
    return index >= 0 ? index : -1;
}

function estadoParaColumna(tipo, estado) {
    const stages = STAGES[tipo] || STAGES.consulta;
    const id = estadoSlug(estado);
    return stages.some(stage => stage.id === id) ? id : 'otro';
}

function estadoMasAvanzado(tipo, trayectos) {
    let seleccionado = trayectos[0] || null;
    let mejorIndice = -1;

    trayectos.forEach(trayecto => {
        const indice = estadoOrdenIndex(tipo, trayecto.estado || 'Agendado');
        if (indice > mejorIndice) {
            mejorIndice = indice;
            seleccionado = trayecto;
        }
    });

    return seleccionado;
}

function minutosEnEstado(trayecto) {
    if (!Array.isArray(trayecto.historial_estados)) return null;

    const actual = estadoSlug(trayecto.estado);
    const evento = [...trayecto.historial_estados].reverse().find(h => estadoSlug(h.estado) === actual);
    if (!evento || !evento.fecha_hora_cambio) return null;

    const inicio = new Date(String(evento.fecha_hora_cambio).replace(' ', 'T'));
    if (Number.isNaN(inicio.getTime())) return null;

    return Math.max(0, Math.floor((new Date() - inicio) / 60000));
}

function colorTiempo(minutos) {
    if (minutos === null) return '#6c757d';
    if (minutos > 90) return '#dc3545';
    if (minutos > 45) return '#ffc107';
    if (minutos > 20) return '#198754';
    return '#007bff';
}

function trayectosFiltradosPorTipo(tipo) {
    return filtrarSolicitudes().flatMap(visita => {
        if (!Array.isArray(visita.trayectos)) return [];
        return visita.trayectos
            .filter(trayecto => tipoTrayecto(trayecto) === tipo)
            .map(trayecto => ({...trayecto, visita, tipo}));
    });
}

function visitasConAtenciones() {
    return filtrarSolicitudes().map(visita => {
        const atenciones = {consulta: [], optometria: [], cirugia: []};
        (visita.trayectos || []).forEach(trayecto => {
            const tipo = tipoTrayecto(trayecto);
            if (atenciones[tipo]) atenciones[tipo].push({...trayecto, tipo});
        });

        return {...visita, atenciones};
    }).filter(visita => Object.values(visita.atenciones).some(items => items.length > 0));
}

function estadoVisitaAgregada(visita) {
    const principales = Object.entries(visita.atenciones)
        .flatMap(([tipo, trayectos]) => trayectos.length ? [{tipo, trayecto: estadoMasAvanzado(tipo, trayectos)}] : []);

    if (principales.length === 0) return 'Otro';
    if (principales.every(item => estadoSlug(item.trayecto.estado) === 'alta')) return 'Alta';
    if (principales.some(item => ['en-consulta', 'optometria', 'dilatando', 'preoperatorio', 'en-quirofano', 'recuperacion', 'admision'].includes(estadoSlug(item.trayecto.estado)))) {
        return 'En atención';
    }
    if (principales.some(item => ['llegado', 'confirmado'].includes(estadoSlug(item.trayecto.estado)))) {
        return 'Llegado';
    }
    if (principales.some(item => estadoSlug(item.trayecto.estado) === 'agendado')) return 'Agendado';

    return 'Otro';
}

function renderColumnas(tipo) {
    const board = document.querySelector('.kanban-board');
    if (!board) return;

    board.innerHTML = '';
    (STAGES[tipo] || STAGES.visita).forEach(stage => {
        const col = document.createElement('div');
        col.className = 'kanban-col patient-flow-column';
        col.innerHTML = `
            <div class="patient-flow-column__header">
                <h5 class="patient-flow-column__title">${escapeHtml(stage.label)}</h5>
                <span class="patient-flow-column__count" id="badge-${stage.id}">0</span>
            </div>
            <div class="kanban-items patient-flow-items" id="kanban-${stage.id}" data-estado-label="${escapeHtml(stage.label)}"></div>
        `;
        board.appendChild(col);
    });
}

function renderResumen(items, titulo) {
    const summary = document.getElementById('kanban-summary');
    if (!summary) return;

    const counts = {};
    items.forEach(item => {
        const estado = item.estadoResumen || item.estado || 'Otro';
        counts[estado] = (counts[estado] || 0) + 1;
    });

    summary.innerHTML = `<div><span class="fw-semibold">${escapeHtml(titulo)}: <b>${items.length}</b></span> &nbsp;|&nbsp; `
        + Object.entries(counts).map(([estado, total]) => `<span class="me-2">${escapeHtml(estado)}: <b>${total}</b></span>`).join(' ')
        + `</div>${renderAuditoriaClasificacion()}`;
}

function actualizarConteosColumnas() {
    document.querySelectorAll('.patient-flow-column').forEach(column => {
        const count = column.querySelectorAll('.patient-flow-card').length;
        const badge = column.querySelector('.patient-flow-column__count');
        if (badge) badge.textContent = String(count);
    });
}

function renderAuditoriaClasificacion() {
    const counts = {consulta: 0, optometria: 0, cirugia: 0};
    const estadosFuera = {};
    const activeTipo = document.querySelector('.tab-kanban.active')?.dataset?.tipo || 'visita';

    filtrarSolicitudes().forEach(visita => {
        (visita.trayectos || []).forEach(trayecto => {
            const tipo = tipoTrayecto(trayecto);
            counts[tipo] = (counts[tipo] || 0) + 1;

            const tipoParaEstado = activeTipo === 'visita' ? tipo : activeTipo;
            if (tipo === activeTipo || activeTipo === 'visita') {
                const estadoColumna = estadoParaColumna(tipoParaEstado, trayecto.estado || 'Agendado');
                if (estadoColumna === 'otro') {
                    const estado = trayecto.estado || '(sin estado)';
                    estadosFuera[estado] = (estadosFuera[estado] || 0) + 1;
                }
            }
        });
    });

    const fuera = Object.entries(estadosFuera)
        .map(([estado, total]) => `${escapeHtml(estado)} (${total})`)
        .join(', ');

    return `<div class="mt-1 small text-muted">
        Clasificación: Consulta <b>${counts.consulta}</b>, Optometría <b>${counts.optometria}</b>, Cirugía <b>${counts.cirugia}</b>
        ${fuera ? ` · Estados en Otro: ${fuera}` : ''}
    </div>`;
}

function renderTarjetaTrayecto(trayecto) {
    const minutos = minutosEnEstado(trayecto);
    const paciente = [trayecto.visita.fname, trayecto.visita.lname, trayecto.visita.lname2].filter(Boolean).join(' ');
    const historial = (trayecto.historial_estados || [])
        .map(h => `${h.estado}: ${moment(h.fecha_hora_cambio).format('DD-MM-YYYY HH:mm')}`)
        .join('\n');

    const card = document.createElement('div');
    card.className = `kanban-card patient-flow-card patient-flow-card--${trayecto.tipo || 'consulta'} view-details`;
    card.setAttribute('draggable', true);
    card.dataset.form = trayecto.form_id || '';
    card.dataset.id = trayecto.id || '';
    card.dataset.tipo = trayecto.tipo || '';
    card.dataset.doctor = trayecto.doctor || '';
    card.dataset.afiliacion = trayecto.afiliacion || '';
    card.dataset.fecha = trayecto.visita.fecha_visita || '';
    card.title = historial;
    card.innerHTML = `
        <div class="patient-flow-card__top">
            <span class="patient-flow-card__badge patient-flow-card__badge--${trayecto.tipo || 'consulta'}">${escapeHtml(TYPE_LABELS[trayecto.tipo] || 'Atención')}</span>
            <span class="patient-flow-card__time" style="color:${colorTiempo(minutos)};">${minutos === null ? '' : `${minutos} min`}</span>
        </div>
        <div class="patient-flow-card__name">${escapeHtml(paciente)}</div>
        <div class="patient-flow-card__meta">
            <span><i class="mdi mdi-card-account-details"></i> <b>${escapeHtml(trayecto.visita.hc_number)}</b></span>
            <span><i class="mdi mdi-calendar"></i> ${escapeHtml(trayecto.visita.fecha_visita || '')} · ${escapeHtml(trayecto.visita.hora_llegada ? trayecto.visita.hora_llegada.slice(11, 16) : '-')}</span>
            <span><i class="mdi mdi-hospital-building"></i> ${escapeHtml(trayecto.afiliacion || '-')}</span>
            <span><i class="mdi mdi-doctor"></i> ${escapeHtml(trayecto.doctor || '-')}</span>
        </div>
        <div class="patient-flow-card__procedure" title="${escapeHtml(trayecto.procedimiento || '')}">
            ${escapeHtml(formatearProcedimientoCorto(trayecto.procedimiento))}
        </div>
        <div class="patient-flow-card__footer">
            <span>${escapeHtml(trayecto.estado || 'Agendado')}</span>
            <span>#${escapeHtml(trayecto.form_id || '')}</span>
        </div>
    `;

    return card;
}

function renderTarjetaVisita(visita) {
    const estadoResumen = estadoVisitaAgregada(visita);
    const paciente = [visita.fname, visita.lname, visita.lname2].filter(Boolean).join(' ');
    const tiposHtml = Object.entries(visita.atenciones)
        .filter(([, trayectos]) => trayectos.length > 0)
        .map(([tipo, trayectos]) => {
            const principal = estadoMasAvanzado(tipo, trayectos);
            return `<div class="patient-flow-visit-line">
                <span class="patient-flow-card__badge patient-flow-card__badge--${escapeHtml(tipo)}">${escapeHtml(TYPE_LABELS[tipo])}</span>
                <span>${escapeHtml(principal.estado || 'Agendado')}</span>
                <span>${trayectos.length}</span>
            </div>`;
        }).join('');

    const card = document.createElement('div');
    card.className = 'kanban-card patient-flow-card patient-flow-card--visita view-details';
    card.dataset.visitaId = visita.visita_id || '';
    card.dataset.fecha = visita.fecha_visita || '';
    card.innerHTML = `
        <div class="patient-flow-card__top">
            <span class="patient-flow-card__badge patient-flow-card__badge--visita">${escapeHtml(estadoResumen)}</span>
            <span class="patient-flow-card__time">${escapeHtml(visita.hora_llegada ? visita.hora_llegada.slice(11, 16) : '-')}</span>
        </div>
        <div class="patient-flow-card__name">${escapeHtml(paciente)}</div>
        <div class="patient-flow-card__meta">
            <span><i class="mdi mdi-card-account-details"></i> <b>${escapeHtml(visita.hc_number)}</b></span>
            <span><i class="mdi mdi-calendar"></i> ${escapeHtml(visita.fecha_visita || '')}</span>
        </div>
        <div class="patient-flow-visit-lines">${tiposHtml}</div>
    `;

    return {card, estadoResumen};
}

function attachSortable(tipo) {
    if (tipo === 'visita' || typeof Sortable === 'undefined') return;

    document.querySelectorAll('.kanban-items').forEach(container => {
        new Sortable(container, {
            group: 'kanban',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: function (evt) {
                const item = evt.item;
                const formId = item.dataset.form;
                const newEstado = evt.to.dataset.estadoLabel || '';
                const trayecto = findTrayecto(formId);
                const estadoAnterior = trayecto ? trayecto.estado : null;

                if (trayecto) {
                    trayecto.estado = newEstado;
                    renderTabActivo();
                }

                actualizarEstadoTrayecto(formId, newEstado)
                    .catch(error => {
                        if (trayecto && estadoAnterior) {
                            trayecto.estado = estadoAnterior;
                            renderTabActivo();
                        }
                        Swal.fire('Error', error.message || 'No se pudo actualizar el estado.', 'error');
                    });
            },
        });
    });
}

function findTrayecto(formId) {
    for (const visita of allSolicitudes) {
        const trayecto = (visita.trayectos || []).find(item => String(item.form_id) === String(formId));
        if (trayecto) return trayecto;
    }
    return null;
}

function actualizarEstadoTrayecto(formId, estado) {
    return fetch('/v2/pacientes/flujo/trayecto-estado', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({form_id: formId, estado}),
    })
        .then(async response => {
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                throw new Error(payload.message || 'No se pudo actualizar el estado.');
            }
            showToast('Estado actualizado correctamente');
            return payload;
        });
}

function renderTabActivo() {
    const tipo = document.querySelector('.tab-kanban.active')?.dataset?.tipo || 'visita';
    renderColumnas(tipo);

    if (tipo === 'visita') {
        const visitas = visitasConAtenciones();
        visitas.forEach(visita => {
            const {card, estadoResumen} = renderTarjetaVisita(visita);
            const col = document.getElementById('kanban-' + estadoParaColumna('visita', estadoResumen));
            if (col) col.appendChild(card);
            visita.estadoResumen = estadoResumen;
        });
        renderResumen(visitas, 'Total visitas');
        actualizarConteosColumnas();
        return;
    }

    const trayectos = trayectosFiltradosPorTipo(tipo);
    trayectos.forEach(trayecto => {
        const card = renderTarjetaTrayecto(trayecto);
        const col = document.getElementById('kanban-' + estadoParaColumna(tipo, trayecto.estado || 'Agendado'));
        if (col) col.appendChild(card);
    });
    renderResumen(trayectos, TYPE_LABELS[tipo] || 'Atenciones');
    attachSortable(tipo);
    actualizarConteosColumnas();
}

function poblarAfiliacionesUnicas(data) {
    const select = document.getElementById('kanbanAfiliacionFilter');
    if (!select) return;

    select.innerHTML = '<option value="">Todas</option>';
    const afiliaciones = [...new Set(data.flatMap(visita => (visita.trayectos || []).map(t => t.afiliacion).filter(Boolean)))].sort();
    afiliaciones.forEach(afiliacion => {
        const option = document.createElement('option');
        option.value = afiliacion;
        option.textContent = afiliacion;
        select.appendChild(option);
    });
}

function llenarDoctores(data) {
    const select = document.getElementById('kanbanDoctorFilter');
    if (!select) return;

    const current = select.value;
    select.innerHTML = '<option value="">Todos</option>';
    const doctores = [...new Set(data.flatMap(visita => (visita.trayectos || []).map(t => t.doctor).filter(Boolean)))].sort();
    doctores.forEach(doctor => {
        const option = document.createElement('option');
        option.value = doctor;
        option.textContent = doctor;
        option.selected = doctor === current;
        select.appendChild(option);
    });
}

function filtrarSolicitudes() {
    const selectedDate = document.getElementById('kanbanDateFilter')?.value || '';
    const selectedAfiliacion = document.getElementById('kanbanAfiliacionFilter')?.value || '';
    const selectedDoctor = document.getElementById('kanbanDoctorFilter')?.value || '';

    return allSolicitudes.filter(visita => {
        const trayectos = visita.trayectos || [];
        return (!selectedDate || visita.fecha_visita === selectedDate)
            && (!selectedAfiliacion || trayectos.some(t => t.afiliacion === selectedAfiliacion))
            && (!selectedDoctor || trayectos.some(t => t.doctor === selectedDoctor));
    });
}

if (typeof showToast !== 'function') {
    function showToast(mensaje) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: mensaje,
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true,
        });
    }
}

function cargarFlujoPorFecha(fechaSeleccionada) {
    const selected = fechaSeleccionada || moment().format('YYYY-MM-DD');
    const dateInput = document.getElementById('kanbanDateFilter');
    if (dateInput) dateInput.value = selected;

    showLoader();
    return fetch(`/v2/pacientes/flujo/tablero?fecha=${encodeURIComponent(selected)}&modo=visita`)
        .then(response => response.json())
        .then(data => {
            allSolicitudes = Array.isArray(data) ? data : [];
            poblarAfiliacionesUnicas(allSolicitudes);
            llenarDoctores(allSolicitudes);
            renderTabActivo();
        })
        .catch(error => {
            console.error('Error al cargar el flujo de pacientes:', error);
            Swal.fire('Error', 'No se pudo cargar el flujo de pacientes.', 'error');
        })
        .finally(hideLoader);
}

function verificarCambiosRecientes() {
    const url = new URL('/v2/pacientes/flujo/recientes', window.location.origin);
    if (ultimoTimestamp) url.searchParams.set('desde', ultimoTimestamp);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data?.timestamp) ultimoTimestamp = data.timestamp;
            if (Array.isArray(data?.pacientes) && data.pacientes.length > 0) {
                return cargarFlujoPorFecha(document.getElementById('kanbanDateFilter')?.value || moment().format('YYYY-MM-DD'));
            }
            return null;
        })
        .catch(error => console.error('Error al verificar cambios recientes:', error));
}

function startPolling() {
    if (!pollingIntervalId) pollingIntervalId = setInterval(verificarCambiosRecientes, 30000);
}

function stopPolling() {
    if (pollingIntervalId) {
        clearInterval(pollingIntervalId);
        pollingIntervalId = null;
    }
}

document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
        stopPolling();
    } else {
        verificarCambiosRecientes();
        startPolling();
    }
});

$(document).ready(function () {
    const dateInput = document.getElementById('kanbanDateFilter');
    const today = moment().format('YYYY-MM-DD');

    if (dateInput) dateInput.value = today;

    document.querySelectorAll('.tab-kanban').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.tab-kanban').forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            renderTabActivo();
        });
    });

    $('#kanbanDateFilter')
        .datepicker({
            autoclose: true,
            clearBtn: true,
            todayBtn: 'linked',
            todayHighlight: true,
            format: 'yyyy-mm-dd',
            language: 'es',
        })
        .on('changeDate', function (event) {
            const selected = event?.format?.('yyyy-mm-dd') || dateInput?.value || today;
            cargarFlujoPorFecha(selected);
        })
        .on('clearDate', function () {
            cargarFlujoPorFecha(today);
        });

    ['kanbanDoctorFilter', 'kanbanAfiliacionFilter'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('change', renderTabActivo);
    });

    cargarFlujoPorFecha(today);
    startPolling();
});
