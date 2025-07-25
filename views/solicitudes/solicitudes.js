function poblarAfiliacionesUnicas(data) {
    const select = document.getElementById('kanbanAfiliacionFilter');
    if (!select) return;
    select.innerHTML = '<option value="">Todas</option>';
    const afiliaciones = [...new Set(data.map(d => d.afiliacion).filter(Boolean))].sort();
    afiliaciones.forEach(af => {
        const option = document.createElement('option');
        option.value = af;
        option.textContent = af;
        select.appendChild(option);
    });
}


function aplicarFiltrosCompletos() {
    const afiliacion = document.getElementById('kanbanAfiliacionFilter').value.toLowerCase();
    const doctor = document.getElementById('kanbanDoctorFilter').value;
    const fechaTexto = document.getElementById('kanbanDateFilter').value;
    const estadoSemaforo = document.getElementById('kanbanSemaforoFilter').value;

    let fechaInicio = null;
    let fechaFin = null;

    if (fechaTexto && fechaTexto.includes(' - ')) {
        const partes = fechaTexto.split(' - ');
        fechaInicio = moment(partes[0], 'DD-MM-YYYY').startOf('day');
        fechaFin = moment(partes[1], 'DD-MM-YYYY').endOf('day');
    }

    const filtradas = allSolicitudes.filter(s => {
        const matchAfiliacion = !afiliacion || (s.afiliacion && s.afiliacion.toLowerCase().includes(afiliacion));
        const matchDoctor = !doctor || s.doctor === doctor;
        const fecha = moment(s.fecha_creacion);
        const hoy = moment();
        const dias = hoy.diff(fecha, 'days');

        let estadoCalculado = '';
        if (dias <= 3) estadoCalculado = 'normal';
        else if (dias <= 7) estadoCalculado = 'pendiente';
        else estadoCalculado = 'urgente';

        const matchSemaforo = !estadoSemaforo || estadoSemaforo === estadoCalculado;
        const matchFecha = (!fechaInicio || fecha.isSameOrAfter(fechaInicio)) &&
            (!fechaFin || fecha.isSameOrBefore(fechaFin));
        return matchAfiliacion && matchDoctor && matchFecha && matchSemaforo;
    });

    renderKanban(filtradas);
}

function poblarDoctoresUnicos(data) {
    const select = document.getElementById('kanbanDoctorFilter');
    if (!select) return;
    select.innerHTML = '<option value="">Todos</option>';
    const doctores = [...new Set(data.map(d => d.doctor).filter(Boolean))].sort();
    doctores.forEach(doc => {
        const option = document.createElement('option');
        option.value = doc;
        option.textContent = doc;
        select.appendChild(option);
    });
}

function renderKanban(filtered = allSolicitudes) {
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');
    filtered.forEach(s => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', true);
        tarjeta.setAttribute('data-hc', s.hc_number);
        tarjeta.setAttribute('data-form', s.form_id);
        tarjeta.setAttribute('data-secuencia', s.secuencia);
        tarjeta.setAttribute('data-estado', s.estado);
        tarjeta.setAttribute('data-id', s.id);
        const fechaCreacion = new Date(s.fecha_creacion);
        const hoy = new Date();
        const dias = Math.floor((hoy - fechaCreacion) / (1000 * 60 * 60 * 24));

        const fechaFormateada = moment(s.fecha_creacion).format('DD-MM-YYYY');

        let semaforo = '';
        if (!isNaN(fechaCreacion)) {
            if (dias <= 3) semaforo = '<span class="badge bg-success">🟢 Normal</span>';
            else if (dias <= 7) semaforo = '<span class="badge bg-warning text-dark">🟡 Pendiente</span>';
            else semaforo = '<span class="badge bg-danger">🔴 Urgente</span>';
        }
        tarjeta.innerHTML = `
    <strong>👤 ${s.nombre}</strong><br>
    <small>🆔 ${s.hc_number}</small><br>
    <small>📅 ${fechaFormateada} ${semaforo}</small><br>
    <small>🏥 ${s.afiliacion}</small><br>
    <small>🔍 <span class="text-primary fw-bold">${s.procedimiento}</span></small><br>
    <small>👁️ ${s.ojo}</small>
`;

        const estadoId = 'kanban-' + s.estado.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const col = document.getElementById(estadoId);
        if (col) {
            col.appendChild(tarjeta);
        } else console.warn(`No se encontró la columna para el estado: "${s.estado}"`);
    });
    // Reaplicar SortableJS para las tarjetas recién renderizadas
    document.querySelectorAll('.kanban-items').forEach(container => {
        new Sortable(container, {
            group: 'kanban', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, onEnd: function (evt) {
                const item = evt.item;
                const rawEstado = evt.to.id.replace('kanban-', '').replace(/-/g, ' ');
                const newEstado = rawEstado
                    .split(' ')
                    .map(p => p.charAt(0).toUpperCase() + p.slice(1))
                    .join(' ');
                const formId = item.getAttribute('data-form');

                console.log('🟡 Movimiento detectado');
                console.log('➡️ ID:', formId);
                console.log('🆕 Estado nuevo:', newEstado);

                item.dataset.estado = newEstado;

                const solicitud = allSolicitudes.find(s => s.form_id === formId);
                if (solicitud) {
                    solicitud.estado = newEstado;
                    console.log('✅ Estado actualizado en memoria:', solicitud);
                    aplicarFiltrosCompletos();
                } else {
                    console.warn('⚠️ No se encontró la solicitud en el array allSolicitudes');
                }

                fetch('actualizar_estado.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: item.getAttribute('data-id'), estado: newEstado})
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Respuesta no válida del servidor');
                        return response.json();
                    })
                    .then(data => {
                        console.log('📬 Respuesta del servidor:', data);
                        if (data.success) {
                            showToast('✅ Estado actualizado correctamente');
                        } else {
                            console.error('❌ Error al actualizar estado en el servidor:', data.error);
                        }
                    }).catch(error => {
                    showToast('❌ No se pudo actualizar el estado');
                    console.error('🚨 Error de red al enviar estado:', error);
                });
            }
        });
    });
}

renderKanban();

document.addEventListener('DOMContentLoaded', function () {
    poblarAfiliacionesUnicas(allSolicitudes);
    poblarDoctoresUnicos(allSolicitudes);
});

// Filtro de fechas usando daterangepicker
$(function () {
    const inputFecha = $('#kanbanDateFilter');

    inputFecha.daterangepicker({
        locale: {
            format: 'DD-MM-YYYY',
            applyLabel: "Aplicar",
            cancelLabel: "Cancelar",
            daysOfWeek: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
            monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"]
        },
        autoUpdateInput: false
    });

    inputFecha.on('apply.daterangepicker', function (ev, picker) {
        this.value = `${picker.startDate.format('DD-MM-YYYY')} - ${picker.endDate.format('DD-MM-YYYY')}`;
        aplicarFiltrosCompletos();
    });

    inputFecha.on('cancel.daterangepicker', function () {
        this.value = '';
        aplicarFiltrosCompletos();
    });
});


document.getElementById('kanbanAfiliacionFilter').addEventListener('change', aplicarFiltrosCompletos);
document.getElementById('kanbanDoctorFilter').addEventListener('change', aplicarFiltrosCompletos);
document.getElementById('kanbanSemaforoFilter').addEventListener('change', aplicarFiltrosCompletos);

document.addEventListener('click', function (e) {
    if (e.target.closest('.view-details')) {
        const tarjeta = e.target.closest('.view-details');
        document.querySelectorAll('.kanban-card').forEach(card => card.classList.remove('active'));
        tarjeta.classList.add('active');
        const hc = tarjeta.getAttribute('data-hc');
        const formId = tarjeta.getAttribute('data-form');

        const modal = new bootstrap.Modal(document.getElementById('prefacturaModal'));
        document.getElementById('prefacturaContent').innerHTML = 'Cargando información...';
        modal.show();

        fetch(`get_prefactura.php?hc_number=${encodeURIComponent(hc)}&form_id=${encodeURIComponent(formId)}`)
            .then(response => response.text())
            .then(html => {
                const contentDiv = document.getElementById('prefacturaContent');
                contentDiv.innerHTML = html;

                const id = tarjeta.getAttribute('data-id');
                const solicitud = allSolicitudes.find(s => s.id == id);
                const currentEstado = solicitud ? solicitud.estado : '';
                const btn = document.getElementById('btnRevisarCodigos');

                const estadosOrden = ['Recibido', 'revision-codigos', 'docs-completos', 'aprobacion-anestesia', 'listo-para-agenda'];

                const siguiente = {
                    'Recibido': {id: 'revision-codigos', label: '✅ Códigos Revisado'},
                    'revision-codigos': {id: 'docs-completos', label: '📄 Docs Completos'},
                    'docs-completos': {id: 'aprobacion-anestesia', label: '🧪 Aprobación Anestesia'},
                    'aprobacion-anestesia': {id: 'listo-para-agenda', label: '🗓️ Listo para Agenda'}
                };

                // Mostrar botón de cambio de estado si aplica
                if (siguiente[currentEstado]) {
                    btn.style.display = 'inline-block';
                    btn.textContent = siguiente[currentEstado].label;
                    btn.dataset.estado = siguiente[currentEstado].id;
                } else {
                    btn.style.display = 'none';
                }

                // Mostrar contenido adicional específico del estado
                const extra = document.createElement('div');
                extra.className = 'mt-3 p-3 border-top';

                switch (currentEstado) {
                    case 'revision-codigos':
                        extra.innerHTML = `
    <h6>📝 Entregar órdenes de exámenes prequirúrgicos</h6>
    <button class="btn btn-outline-primary" id="btnGenerarOrden">📄 Generar órdenes</button>
`;
                        break;
                    case 'docs-completos':
                        extra.innerHTML = '<h6>📤 Subir exámenes prequirúrgicos</h6><button class="btn btn-outline-success">📎 Adjuntar archivos</button>';
                        break;
                    case 'aprobacion-anestesia':
                        extra.innerHTML = '<h6>🩺 Observación de anestesiología</h6><textarea class="form-control" rows="3" placeholder="Observación médica..."></textarea>';
                        break;
                }

                contentDiv.appendChild(extra);

                // Agregar evento para generar orden si corresponde
                if (currentEstado === 'revision-codigos') {
                    const btnOrden = document.getElementById('btnGenerarOrden');
                    btnOrden.addEventListener('click', function () {
                        const url = `solicitud_quirurgica/solicitud_qx_pdf.php?hc_number=${encodeURIComponent(hc)}&form_id=${encodeURIComponent(formId)}`;
                        window.open(url, '_blank');
                    });
                }
            })
            .catch(error => {
                document.getElementById('prefacturaContent').innerHTML = 'Error al cargar los datos.';
                console.error(error);
            });
    }
});