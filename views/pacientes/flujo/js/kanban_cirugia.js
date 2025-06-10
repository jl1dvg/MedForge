// Definir estados para columnas del tablero de Cirugía
const ESTADOS_CIRUGIA = [
    {label: "Agendado", id: "agendado"},
    {label: "Llegado", id: "llegado"},
    {label: "Preoperatorio", id: "preoperatorio"},
    {label: "En quirófano", id: "en-quirofano"},
    {label: "Recuperación", id: "recuperacion"},
    {label: "Alta", id: "alta"}
];

function renderColumnasCirugia() {
    const board = document.querySelector('.kanban-board');
    if (!board) return;

    // Limpiar columnas actuales
    board.innerHTML = '';

    ESTADOS_CIRUGIA.forEach(estado => {
        const col = document.createElement('div');
        col.className = 'kanban-col';
        col.innerHTML = `
            <div class='kanban-column box box-solid box-primary rounded shadow-sm p-1 me-0' style='min-width: 250px; flex-shrink: 0;'>
            <div class='box-header with-border'>
            <h5 class='text-center box-title'>${estado.label} <span class='badge bg-danger' id='badge-${estado.id}' style='display:none;'>¡+4!</span></h5>
            <ul class='box-controls pull-right'><li><a class='box-btn-close' href='#'></a></li><li><a class='box-btn-slide' href='#'></a></li><li><a class='box-btn-fullscreen' href='#'></a></li></ul></div>
            <div class='box-body p-0'>
            <div class='kanban-items' id='kanban-${estado.id}'></div>         
            </div>
            </div>
        `;
        board.appendChild(col);
    });
}

function renderKanbanCirugia() {
    showLoader();

    // Renderiza las columnas primero (vacía y crea el layout)
    renderColumnasCirugia();

    // Obtiene la data FRESCA y filtrada (aplica todos los filtros activos)
    const visitasFiltradas = filtrarSolicitudes();

    // FlatMap para obtener solo trayectos de cirugía
    const trayectosCirugia = visitasFiltradas
        .flatMap(visita =>
            Array.isArray(visita.trayectos)
                ? visita.trayectos
                    .filter(t => t.procedimiento && t.procedimiento.toUpperCase().includes('CIRUGIAS'))
                    .map(t => ({...t, visita}))
                : []
        );

    // Limpiar columnas
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');

    // Conteo y resumen
    const conteoPorEstado = {};
    const promedioPorEstado = {};

    trayectosCirugia.forEach(t => {
        // Cronómetro por estado usando historial_estados
        let tiempoEnEstado = '';
        let minutos = null;
        if (Array.isArray(t.historial_estados)) {
            const ult = [...t.historial_estados].reverse().find(h => h.estado === t.estado);
            if (ult && ult.fecha_hora_cambio) {
                const fechaCambio = new Date(ult.fecha_hora_cambio.replace(' ', 'T'));
                const ahora = new Date();
                const diffMs = ahora - fechaCambio;
                minutos = Math.floor(diffMs / 60000);

                let color = '#007bff';
                if (minutos > 90) color = '#dc3545'; // rojo
                else if (minutos > 45) color = '#ffc107'; // amarillo
                else if (minutos > 20) color = '#198754'; // verde

                tiempoEnEstado = `<span style="color: ${color}; font-weight:bold;">⏱️ ${minutos} min en ${t.estado}</span><br>`;
            }
        }
        if (!conteoPorEstado[t.estado]) conteoPorEstado[t.estado] = 0;
        conteoPorEstado[t.estado]++;
        if (!promedioPorEstado[t.estado]) promedioPorEstado[t.estado] = [];
        if (minutos !== null) promedioPorEstado[t.estado].push(minutos);

        // Tooltip de historial de estados
        let historialTooltip = '';
        if (Array.isArray(t.historial_estados) && t.historial_estados.length > 0) {
            historialTooltip = t.historial_estados
                .map(h => `${h.estado}: ${moment(h.fecha_hora_cambio).format('DD-MM-YYYY HH:mm')}`)
                .join('\n');
        }

        // Render de tarjeta
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card view-details';
        tarjeta.setAttribute('draggable', true);
        tarjeta.setAttribute('data-id', t.id);
        tarjeta.setAttribute('data-form', t.form_id);
        tarjeta.title = historialTooltip || '';

        // Nombre paciente
        const nombrePaciente = [t.visita.fname, t.visita.lname, t.visita.lname2].filter(Boolean).join(' ');
        // Procedimiento principal
        let nombreProcedimiento = t.procedimiento;
        if (typeof t.procedimiento === 'string') {
            const partes = t.procedimiento.split(' - ');
            if (partes.length >= 3) nombreProcedimiento = partes.slice(2).join(' - ');
        }

        tarjeta.innerHTML = `
            ${tiempoEnEstado}
            <div style="font-size:1.08em;font-weight:600;">
                <i class="mdi mdi-account"></i> ${nombrePaciente}
            </div>
            <div style="font-size:0.95em; color:#555;">
                <i class="mdi mdi-card-account-details"></i> <b>${t.visita.hc_number}</b>
            </div>
            <div style="font-size:0.95em;">
                <i class="mdi mdi-calendar"></i> ${t.visita.fecha_visita} <b>${t.visita.hora_llegada ? t.visita.hora_llegada.slice(11, 16) : '-'}</b>
            </div>
            <div style="font-size:0.93em; color:#375;">
                <i class="mdi mdi-hospital-building"></i> ${t.afiliacion || ''}
            </div>
            <div style="font-size:0.93em;">
                <i class="mdi mdi-clipboard-text"></i> <span class="text-primary fw-bold" title="${t.procedimiento}" style="display:inline-block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${nombreProcedimiento}</span>
            </div>
            <div style="font-size:0.93em;"><i class="mdi mdi-doctor"></i> ${t.doctor || ''}</div>
            <div style="font-size:0.93em;"><b>Estado:</b> ${t.estado}</div>
            <div style="font-size:10px; color:#b5b5b5;text-align:right;">form_id: ${t.form_id}</div>
        `;

        // Asignar tarjeta a columna por estado
        const estadoId = 'kanban-' + (t.estado || 'otro').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const col = document.getElementById(estadoId);
        if (col) {
            col.appendChild(tarjeta);
        }
    });

    // Drag & drop sobre columnas
    document.querySelectorAll('.kanban-items').forEach(container => {
        new Sortable(container, {
            group: 'kanban',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            dragClass: 'dragging',
            ghostClass: 'drop-area-highlight',
            onStart: function (evt) {
                evt.from.classList.add('drop-area-highlight');
            },
            onEnd: function (evt) {
                const item = evt.item;
                const newEstado = evt.to.id.replace('kanban-', '').replace(/-/g, ' ').toUpperCase();
                const trayectoId = item.getAttribute('data-id');
                const formId = item.getAttribute('data-form');

                // Buscar trayecto en allSolicitudes y actualizarlo
                let trayectoActual = null;
                let visitaActual = null;
                for (const visita of allSolicitudes) {
                    if (!Array.isArray(visita.trayectos)) continue;
                    const trayecto = visita.trayectos.find(t => String(t.id) === String(trayectoId));
                    if (trayecto) {
                        trayectoActual = trayecto;
                        visitaActual = visita;
                        break;
                    }
                }
                const estadoAnterior = trayectoActual ? trayectoActual.estado : null;
                if (trayectoActual) {
                    trayectoActual.estado = newEstado;
                    renderKanbanCirugia();
                }
                // Lógica de actualización en backend (AJAX/fetch)
                fetch('actualizar_estado.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        form_id: formId,
                        estado: newEstado
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            if (trayectoActual && estadoAnterior) {
                                trayectoActual.estado = estadoAnterior;
                                renderKanbanCirugia();
                            }
                            Swal.fire('Error', data.message || 'No se pudo actualizar el estado.', 'error');
                        } else {
                            showToast('✅ Estado actualizado correctamente');
                        }
                    })
                    .catch(error => {
                        if (trayectoActual && estadoAnterior) {
                            trayectoActual.estado = estadoAnterior;
                            renderKanbanCirugia();
                        }
                        Swal.fire('Error', 'No se pudo actualizar el estado: ' + error.message, 'error');
                    });
            }
        });
    });

    // Resumen visual solo de estos trayectos
    generarResumenKanban(trayectosCirugia);

    hideLoader(); // Oculta el loader al finalizar el render
}