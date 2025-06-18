// Definir estados para columnas del tablero de Consulta
const ESTADOS_CONSULTA = [
    {label: "Agendado", id: "agendado"},
    {label: "Llegado", id: "llegado"},
    {label: "Optometria", id: "optometria"},
    {label: "En consulta", id: "en-consulta"},
    {label: "Alta", id: "alta"}
];

function renderColumnasConsulta() {
    const board = document.querySelector('.kanban-board');
    if (!board) return;

    // Limpiar columnas actuales
    board.innerHTML = '';

    ESTADOS_CONSULTA.forEach(estado => {
        const col = document.createElement('div');
        col.className = 'kanban-col';
        col.innerHTML = `
            <div class='kanban-column box box-solid box-success rounded shadow-sm p-1 me-0' style='min-width: 250px; flex-shrink: 0;'>
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

function renderKanbanConsulta() {
    showLoader();

    // Render columnas
    renderColumnasConsulta();

    // Filtrar visitas según filtros activos (por fecha, doctor, afiliación...)
    const visitasFiltradas = filtrarSolicitudes();

    // Agrupar por visita los trayectos que sean de "SERVICIOS OFTALMOLOGICOS GENERALES"
    const consultasPorVisita = visitasFiltradas
        .map(visita => {
            // Tomar solo los trayectos de consulta
            const trayectosConsulta = Array.isArray(visita.trayectos)
                ? visita.trayectos.filter(t => t.procedimiento && t.procedimiento.toUpperCase().startsWith('SERVICIOS OFTALMOLOGICOS GENERALES'))
                : [];
            if (trayectosConsulta.length === 0) return null;
            return {visita, trayectosConsulta};
        })
        .filter(Boolean);

    // Define el orden de los estados de consulta
    const ordenEstados = [
        "Agendado",
        "Llegado",
        "Optometria",
        "Dilatando",
        "En consulta",
        "Alta"
    ];

    // Limpiar columnas
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');

    // Recorrer cada consulta agrupada y pintar UNA tarjeta
    consultasPorVisita.forEach(({visita, trayectosConsulta}) => {
        // Obtener el estado más avanzado según el orden definido
        let estadoActual = "Agendado";
        let trayectoActual = trayectosConsulta[0];
        for (const estado of ordenEstados.slice().reverse()) {
            const trayecto = trayectosConsulta.find(t => t.estado && t.estado.toLowerCase() === estado.toLowerCase());
            if (trayecto) {
                estadoActual = estado;
                trayectoActual = trayecto;
                break;
            }
        }

        // Variables para mostrar en la tarjeta
        let tiempoEnEstadoActual = '';
        let tiempoTotal = '';

        if (estadoActual === 'Agendado') {
            // Mostrar mensaje de tiempo para la cita, sin cronómetro ni tiempo total
            if (visita.hora_llegada) {
                const ahora = new Date();
                // Parsear hora_llegada como fecha con fecha de visita para comparación
                let fechaCita;
                try {
                    // Intentar construir fecha de cita combinando fecha_visita y hora_llegada
                    // Asumimos visita.fecha_visita en formato YYYY-MM-DD o similar
                    // visita.hora_llegada puede ser formato completo o solo hora
                    let fechaVisitaStr = visita.fecha_visita || '';
                    let horaLlegadaStr = visita.hora_llegada;
                    // Si hora_llegada incluye fecha, usar directamente
                    if (horaLlegadaStr.length >= 16) {
                        fechaCita = new Date(horaLlegadaStr.replace(' ', 'T'));
                    } else if (fechaVisitaStr) {
                        // Combinar fecha_visita y hora_llegada (hora_llegada puede tener formato 'YYYY-MM-DD HH:mm:ss' o solo 'HH:mm:ss')
                        let horaPart = horaLlegadaStr.length >= 5 ? horaLlegadaStr.slice(11, 16) : horaLlegadaStr.slice(0, 5);
                        if (horaLlegadaStr.length >= 16) {
                            fechaCita = new Date(horaLlegadaStr.replace(' ', 'T'));
                        } else {
                            fechaCita = new Date(fechaVisitaStr + 'T' + horaPart + ':00');
                        }
                    } else {
                        fechaCita = null;
                    }
                } catch (e) {
                    fechaCita = null;
                }
                if (fechaCita && !isNaN(fechaCita.getTime())) {
                    const diffMs = fechaCita - ahora;
                    const diffMin = Math.round(diffMs / 60000);
                    if (diffMin > 0) {
                        // Adelantado a la cita
                        tiempoEnEstadoActual = `<span style="color:#007bff; font-weight:bold;">Faltan ${diffMin} min para la cita</span><br>`;
                    } else {
                        // Atrasado y paciente no ha llegado
                        tiempoEnEstadoActual = `<span style="color:#dc3545; font-weight:bold;">Atrasado por ${Math.abs(diffMin)} min</span><br>`;
                    }
                }
            }
        } else {
            // Para estados Llegado o posteriores, calcular tiempo total desde primer historial con estado "Llegado"
            // y mostrar cronómetro de estado actual

            // Calcular cronómetro de estado actual (robusto)
            let minutosActual = null;
            if (Array.isArray(trayectoActual.historial_estados)) {
                const estActual = (estadoActual || '').toLowerCase().replace(/[^a-z0-9]/g, '');
                const ult = [...trayectoActual.historial_estados].reverse().find(h =>
                    (h.estado || '').toLowerCase().replace(/[^a-z0-9]/g, '') === estActual
                );
                if (ult && ult.fecha_hora_cambio) {
                    const fechaCambio = new Date(ult.fecha_hora_cambio.replace(' ', 'T'));
                    const ahora = new Date();
                    const diffMs = ahora - fechaCambio;
                    minutosActual = Math.floor(diffMs / 60000);

                    let color = '#007bff';
                    if (minutosActual > 90) color = '#dc3545'; // rojo
                    else if (minutosActual > 45) color = '#ffc107'; // amarillo
                    else if (minutosActual > 20) color = '#198754'; // verde

                    tiempoEnEstadoActual = `<span style="color:${color};font-weight:bold;">⏱️ ${minutosActual} min en ${estadoActual}</span><br>`;
                }
            }

            // Calcular tiempo total desde primer historial con estado "Llegado" entre todos los trayectos
            const todosHistoriales = trayectosConsulta.flatMap(t => t.historial_estados || []);
            const primerLlegado = todosHistoriales.reduce((min, h) => {
                if ((h.estado || '').toLowerCase() === 'llegado') {
                    if (!min || h.fecha_hora_cambio < min.fecha_hora_cambio) return h;
                }
                return min;
            }, null);
            if (primerLlegado && primerLlegado.fecha_hora_cambio) {
                const inicio = new Date(primerLlegado.fecha_hora_cambio.replace(' ', 'T'));
                const ahora = new Date();
                const diffMsTotal = ahora - inicio;
                const minutosTotales = Math.floor(diffMsTotal / 60000);
                tiempoTotal = `<span style="color:#666;font-size:0.95em;">⏲️ Total: ${minutosTotales} min</span><br>`;
            }
        }

        // Tooltip de historial de estados combinando los trayectos
        let historialTooltip = '';
        const todosEstados = trayectosConsulta.flatMap(t => t.historial_estados || []);
        if (todosEstados.length > 0) {
            historialTooltip = todosEstados
                .map(h => `${h.estado}: ${moment(h.fecha_hora_cambio).format('DD-MM-YYYY HH:mm')}`)
                .join('\n');
        }

        // Mostrar los pasos en línea como badges (opcional)
        const pasosHTML = ordenEstados.map(estado =>
            trayectosConsulta.some(t => t.estado && t.estado.toLowerCase() === estado.toLowerCase())
                ? `<span class="badge bg-success">${estado}</span>`
                : `<span class="badge bg-secondary">${estado}</span>`
        ).join(' ');

        // Render de tarjeta
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card view-details';
        tarjeta.setAttribute('draggable', true);
        tarjeta.setAttribute('data-id', trayectoActual.id);
        tarjeta.setAttribute('data-form', trayectoActual.form_id);
        tarjeta.title = historialTooltip || '';

        // Nombre paciente
        const nombrePaciente = [visita.fname, visita.lname, visita.lname2].filter(Boolean).join(' ');

// NUEVO: Listado de trayectos y médicos
        let trayectosDetalleHTML = '';
        if (trayectosConsulta.length > 1) {
            trayectosDetalleHTML = `
        <div style="margin-top:4px;font-size:0.92em;">
            <b>Pasos:</b>
            <ul style="margin:0 0 0 14px; padding:0;">
                ${trayectosConsulta.map(t => {
                // Cronómetro por trayecto
                let tiempoEnEstado = '';
                if (Array.isArray(t.historial_estados)) {
                    // --- MODIFICADO: comparación más robusta ---
                    const estActual = (t.estado || '').toLowerCase().replace(/[^a-z0-9]/g, '');
                    const ult = [...t.historial_estados].reverse().find(h =>
                        (h.estado || '').toLowerCase().replace(/[^a-z0-9]/g, '') === estActual
                    );
                    if (ult && ult.fecha_hora_cambio) {
                        const fechaCambio = new Date(ult.fecha_hora_cambio.replace(' ', 'T'));
                        const ahora = new Date();
                        const diffMs = ahora - fechaCambio;
                        const minutos = Math.floor(diffMs / 60000);

                        let color = '#007bff';
                        if (minutos > 90) color = '#dc3545'; // rojo
                        else if (minutos > 45) color = '#ffc107'; // amarillo
                        else if (minutos > 20) color = '#198754'; // verde

                        tiempoEnEstado = `<span style="color:${color};font-weight:bold;">⏱️ ${minutos} min </span>`;
                        // Puedes dejar este log para debug:
                        // console.log('Reloj:', t.estado, tiempoEnEstado, ult);
                    }
                }
                return `<li>
                        ${tiempoEnEstado}
                        <span class="badge bg-info" style="min-width:80px;display:inline-block;">${t.estado || '-'}</span>
                        <span style="color:#007bff;">
                            <i class="mdi mdi-doctor"></i> ${t.doctor || '-'}
                        </span>
                    </li>`;
            }).join('')}
            </ul>
        </div>
    `;
        } else if (trayectosConsulta.length === 1) {
            trayectosDetalleHTML = `
        <div style="margin-top:4px;font-size:0.92em;">
            <span class="badge bg-info">${trayectoActual.estado || '-'}</span>
            <span style="color:#007bff;">
                <i class="mdi mdi-doctor"></i> ${trayectoActual.doctor || '-'}
            </span>
        </div>
    `;
        }

// ... tu código anterior de tarjeta.innerHTML:
        tarjeta.innerHTML = `
            ${tiempoEnEstadoActual}${tiempoTotal}
            <div style="font-size:1.08em;font-weight:600;">
                <i class="mdi mdi-account"></i> ${nombrePaciente}
            </div>
            <div style="font-size:0.95em; color:#555;">
                <i class="mdi mdi-card-account-details"></i> <b>${visita.hc_number}</b>
            </div>
            <div style="font-size:0.95em;">
                <i class="mdi mdi-calendar"></i> ${visita.fecha_visita} <b>${visita.hora_llegada ? visita.hora_llegada.slice(11, 16) : '-'}</b>
            </div>
            <div style="font-size:0.93em; color:#375;">
                <i class="mdi mdi-hospital-building"></i> ${trayectoActual.afiliacion || ''}
            </div>
            <div style="font-size:0.93em;">
                <i class="mdi mdi-clipboard-text"></i> <span class="text-primary fw-bold" title="${trayectoActual.procedimiento}" style="display:inline-block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    ${trayectoActual.procedimiento.split(' - ').slice(2).join(' - ')}
                </span>
            </div>
    <div style="font-size:0.93em;"><b>Estado actual:</b> ${estadoActual}</div>
            <div>${pasosHTML}</div>
    ${trayectosDetalleHTML}
            <div style="font-size:10px; color:#b5b5b5;text-align:right;">form_id: ${trayectoActual.form_id}</div>
        `;

        // Asignar tarjeta a columna por estado actual
        const estadoId = 'kanban-' + estadoActual.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const col = document.getElementById(estadoId);
        if (col) {
            col.appendChild(tarjeta);
        }
    });

    // Drag & drop igual que antes (puedes ajustar lógica según lo que se deba mover realmente)
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
                const newEstado = evt.to.id.replace('kanban-', '').replace(/-/g, ' ');
                const trayectoId = item.getAttribute('data-id');
                const formId = item.getAttribute('data-form');

                // Solo actualizar el trayecto principal (el más avanzado)
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
                    renderKanbanConsulta();
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
                                renderKanbanConsulta();
                            }
                            Swal.fire('Error', data.message || 'No se pudo actualizar el estado.', 'error');
                        } else {
                            showToast('✅ Estado actualizado correctamente');
                        }
                    })
                    .catch(error => {
                        if (trayectoActual && estadoAnterior) {
                            trayectoActual.estado = estadoAnterior;
                            renderKanbanConsulta();
                        }
                        Swal.fire('Error', 'No se pudo actualizar el estado: ' + error.message, 'error');
                    });
            }
        });
    });

    // Puedes generar un resumen como quieras, por ejemplo:
    // generarResumenKanban(consultasPorVisita.map(c => c.trayectoActual)); // Solo si tienes la función ajustada

    // Generar resumen de Kanban para Consulta
    const tarjetasFiltradas = consultasPorVisita.map(({trayectosConsulta}) => {
        // El trayecto más avanzado (último encontrado) o el primero
        return trayectosConsulta[trayectosConsulta.length - 1] || trayectosConsulta[0];
    });
    generarResumenKanban(tarjetasFiltradas);

    hideLoader();
}