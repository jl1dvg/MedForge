// kanban_base.js

// =========================
// VARIABLES GLOBALES
// =========================
let allSolicitudes = [];
let ultimoTimestamp = null;

// =========================
// HELPERS DE DATOS Y UI
// =========================
function poblarAfiliacionesUnicas(data) {
    const select = document.getElementById('kanbanAfiliacionFilter');
    if (!select) return;
    // Conservar solo la opción "Todas"
    select.innerHTML = '<option value="">Todas</option>';
    const afiliaciones = [...new Set(data.map(d => d.afiliacion).filter(Boolean))].sort();
    afiliaciones.forEach(af => {
        const option = document.createElement('option');
        option.value = af;
        option.textContent = af;
        select.appendChild(option);
    });
}

// Llenar filtro de doctores y fechas según datosFiltrados
function llenarSelectDoctoresYFechas(datosFiltrados) {
    // Doctor
    const doctorFiltro = document.getElementById('kanbanDoctorFilter');
    const currentDoctor = doctorFiltro.value;
    doctorFiltro.innerHTML = '<option value="">Todos</option>';
    const doctoresSet = new Set(datosFiltrados.map(item => item.doctor));
    doctoresSet.forEach(doctor => {
        if (doctor) {
            const option = document.createElement('option');
            option.value = doctor;
            option.textContent = doctor;
            if (doctor === currentDoctor) {
                option.selected = true;
            }
            doctorFiltro.appendChild(option);
        }
    });
    // Fecha
    const fechaFiltro = document.getElementById('kanbanFechaFiltro');
    if (fechaFiltro) {
        fechaFiltro.innerHTML = '<option value="">Todas</option>';
        const fechasSet = new Set(datosFiltrados.map(item => item.fecha_cambio));
        const fechasOrdenadas = Array.from(fechasSet).sort().reverse();
        fechasOrdenadas.forEach(fecha => {
            const option = document.createElement('option');
            option.value = fecha;
            option.textContent = fecha;
            fechaFiltro.appendChild(option);
        });
    }
}

function llenarSelectProcedimientoCategorias() {
    // Extraer categorías únicas de allSolicitudes
    const categorias = Array.from(
        new Set(
            allSolicitudes
                .map(s => extraerCategoriaProcedimiento(s.procedimiento))
                .filter(Boolean)
        )
    ).sort();
    const select = document.getElementById('filtroProcedimiento');
    if (select) {
        select.innerHTML = '<option value="">Todos</option>' +
            categorias.map(cat => `<option value="${cat}">${cat}</option>`).join('');
    }
}

// Extrae y asigna la categoría al dataset de cada tarjeta al cargar
function extraerCategoriaProcedimiento(procedimiento) {
    if (typeof procedimiento !== 'string') return '';
    return procedimiento.split(' - ')[0]?.trim() || '';
}

// =========================
// FUNCIONES DE RENDER Y RESUMEN
// =========================
function renderKanban() {
    const filtered = filtrarSolicitudes();
    // Conteo de pacientes por estado y para promedios
    const conteoPorEstado = {};
    const porEstado = {};
    const promedioPorEstado = {};
    filtered.forEach(s => {
        const estadoId = s.estado.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
        conteoPorEstado[estadoId] = (conteoPorEstado[estadoId] || 0) + 1;
        porEstado[s.estado] = (porEstado[s.estado] || 0) + 1;
        // Para promedios de minutos en estado
        if (Array.isArray(s.historial_estados)) {
            const ult = [...s.historial_estados].reverse().find(h => h.estado === s.estado);
            if (ult && ult.fecha_hora_cambio) {
                const fechaCambio = new Date(ult.fecha_hora_cambio.replace(' ', 'T'));
                const ahora = new Date();
                const diffMs = ahora - fechaCambio;
                const min = Math.floor(diffMs / 60000);
                if (!promedioPorEstado[s.estado]) promedioPorEstado[s.estado] = [];
                promedioPorEstado[s.estado].push(min);
            }
        }
    });
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');
    filtered
        .forEach(s => {
            const tarjeta = document.createElement('div');
            tarjeta.className = 'kanban-card view-details';
            tarjeta.setAttribute('draggable', true);
            tarjeta.setAttribute('data-hc', s.hc_number);
            tarjeta.setAttribute('data-form', s.form_id);
            tarjeta.setAttribute('data-secuencia', s.secuencia);
            tarjeta.setAttribute('data-estado', s.estado);
            tarjeta.setAttribute('data-id', s.id);
            // Nuevos atributos para filtrado frontend
            tarjeta.setAttribute('data-doctor', s.doctor || '');
            tarjeta.setAttribute('data-afiliacion', s.afiliacion || '');
            tarjeta.setAttribute('data-fecha', s.fecha_cambio || '');
            // Tipo
            let tipo = '';
            if (s.procedimiento && s.procedimiento.startsWith('CIRUGIAS')) tipo = 'CIRUGIAS';
            else if (s.procedimiento && s.procedimiento.startsWith('CONSULTA')) tipo = 'CONSULTAS';
            else if (s.procedimiento && s.procedimiento.startsWith('EXAMEN')) tipo = 'EXAMENES';
            else if (s.procedimiento && s.procedimiento.startsWith('OPTOMETRIA')) tipo = 'OPTOMETRIA';
            tarjeta.setAttribute('data-tipo', tipo);
            // Procedimiento categoría para filtrado
            const categoria = extraerCategoriaProcedimiento(s.procedimiento);
            tarjeta.setAttribute('data-procedimiento_categoria', categoria);

            const fechaCreacion = new Date(s.fecha_creacion);
            const hoy = new Date();
            const dias = Math.floor((hoy - fechaCreacion) / (1000 * 60 * 60 * 24));

            let semaforo = '';
            if (!isNaN(fechaCreacion)) {
                if (dias <= 3) semaforo = '<span class="badge bg-success">🟢 Normal</span>';
                else if (dias <= 7) semaforo = '<span class="badge bg-warning text-dark">🟡 Pendiente</span>';
                else semaforo = '<span class="badge bg-danger">🔴 Urgente</span>';
            }

            let nombreProcedimiento = s.procedimiento;
            if (typeof s.procedimiento === 'string') {
                const partes = s.procedimiento.split(' - ');
                if (partes.length >= 3) {
                    nombreProcedimiento = partes.slice(2).join(' - ');
                }
            }
            const ojoMatch = s.procedimiento.match(/- (DERECHO|IZQUIERDO|AMBOS OJOS)$/i);
            const ojo = ojoMatch ? ojoMatch[1] : '';

            // Mostrar tiempo en el estado actual si se tiene historial_estados
            let tiempoEnEstado = '';
            if (Array.isArray(s.historial_estados)) {
                const ult = [...s.historial_estados].reverse().find(h => h.estado === s.estado);
                if (ult && ult.fecha_hora_cambio) {
                    const fechaCambio = new Date(ult.fecha_hora_cambio.replace(' ', 'T'));
                    const ahora = new Date();
                    const diffMs = ahora - fechaCambio;
                    const min = Math.floor(diffMs / 60000);

                    let color = '#007bff'; // azul normal
                    if (min > 90) color = '#dc3545'; // rojo
                    else if (min > 45) color = '#ffc107'; // amarillo
                    else if (min > 20) color = '#198754'; // verde
                    else color = '#007bff'; // azul

                    tiempoEnEstado = `<span style="color: ${color}; font-weight:bold;">⏱️ ${min} min en ${s.estado}</span><br>`;
                }
            }

            let historialTooltip = '';
            if (Array.isArray(s.historial_estados) && s.historial_estados.length > 0) {
                historialTooltip = s.historial_estados
                    .map(h => `${h.estado}: ${moment(h.fecha_hora_cambio).format('DD-MM-YYYY HH:mm')}`)
                    .join('\n');
            }

            tarjeta.innerHTML = `
      ${tiempoEnEstado}
      <div style="font-size:1.08em;font-weight:600;">
          <i class="mdi mdi-account"></i> ${[s.fname, s.lname, s.lname2].filter(Boolean).join(' ')}
      </div>
      <div style="font-size:0.95em; color:#555;">
          <i class="mdi mdi-card-account-details"></i> <b>${s.hc_number}</b>
      </div>
      <div style="font-size:0.95em;">
          <i class="mdi mdi-calendar"></i> ${s.fecha_cambio} ${semaforo}
      </div>
      <div style="font-size:0.93em; color:#375;">
          <i class="mdi mdi-hospital-building"></i> ${s.afiliacion}
      </div>
      <div style="font-size:0.93em;">
          <i class="mdi mdi-clipboard-text"></i> <span class="text-primary fw-bold" title="${s.procedimiento}" style="display:inline-block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${nombreProcedimiento}</span>
      </div>
      <div style="font-size:0.93em;"><i class="mdi mdi-eye"></i> ${ojo}</div>
      <div style="font-size:10px; color:#b5b5b5;text-align:right;">form_id: ${s.form_id}</div>
  `;

            // Asignar tooltip de historial
            tarjeta.title = historialTooltip || '';

            const estadoId = 'kanban-' + s.estado.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
            const col = document.getElementById(estadoId);
            if (col) {
                col.appendChild(tarjeta);
            } else console.warn(`No se encontró la columna para el estado: "${s.estado}"`);
        });

    // Mostrar/ocultar badges de conteo por estado
    Object.entries(conteoPorEstado).forEach(([estadoId, count]) => {
        const badge = document.getElementById(`badge-${estadoId}`);
        if (badge) badge.style.display = count > 4 ? 'inline-block' : 'none';
    });

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
                const rawEstado = evt.to.id.replace('kanban-', '').replace(/-/g, ' ');
                const newEstado = rawEstado
                    .split(' ')
                    .map(p => p.charAt(0).toUpperCase() + p.slice(1))
                    .join(' ');
                const formId = item.getAttribute('data-form');

                const solicitud = allSolicitudes.find(s => String(s.form_id) === String(formId));
                const estadoAnterior = solicitud ? solicitud.estado : null;
                if (solicitud) {
                    solicitud.estado = newEstado;
                    renderKanban();
                }

                fetch('actualizar_estado.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        form_id: item.getAttribute('data-form'),
                        estado: newEstado
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            // Si falla, revierte el estado en el array y renderiza de nuevo
                            if (solicitud && estadoAnterior) {
                                solicitud.estado = estadoAnterior;
                                renderKanban();
                            }
                            Swal.fire('Error', data.message || 'No se pudo actualizar el estado.', 'error');
                        } else {
                            // Consulta el estado real en el backend usando el API global
                            fetch('/public/ajax/flujo')
                                .then(r => r.json())
                                .then(arr => {
                                    const actual = arr.find(s => String(s.form_id) === String(formId));
                                    console.log('🔎 Estado actual en BD:', actual ? actual.estado : 'No encontrado');
                                });
                            showToast('✅ Estado actualizado correctamente');
                        }
                    })
                    .catch(error => {
                        // Si hay error de red, revierte el estado
                        if (solicitud && estadoAnterior) {
                            solicitud.estado = estadoAnterior;
                            renderKanban();
                        }
                        Swal.fire('Error', 'No se pudo actualizar el estado: ' + error.message, 'error');
                    });
            }
        });
    });
    // Actualizar los filtros de doctor y fecha según los datos filtrados actuales
    llenarSelectDoctoresYFechas(filtered);

    // Resumen estadístico - ahora por función separada y usando datos filtrados
    generarResumenKanban(filtered);
}

function generarResumenKanban(filtrados) {
    // Conteo de pacientes por estado y para promedios
    const porEstado = {};
    const promedioPorEstado = {};
    filtrados.forEach(s => {
        porEstado[s.estado] = (porEstado[s.estado] || 0) + 1;
        if (Array.isArray(s.historial_estados)) {
            const ult = [...s.historial_estados].reverse().find(h => h.estado === s.estado);
            if (ult && ult.fecha_hora_cambio) {
                const fechaCambio = new Date(ult.fecha_hora_cambio.replace(' ', 'T'));
                const ahora = new Date();
                const diffMs = ahora - fechaCambio;
                const min = Math.floor(diffMs / 60000);
                if (!promedioPorEstado[s.estado]) promedioPorEstado[s.estado] = [];
                promedioPorEstado[s.estado].push(min);
            }
        }
    });
    const total = filtrados.length;
    let resumen = `<span style="font-weight:600;">📊 Total solicitudes: <b>${total}</b></span> &nbsp;|&nbsp; `;
    resumen += Object.entries(porEstado).map(([estado, cant]) =>
        `<span style="margin-right:10px;">${estado}: <b>${cant}</b></span>`
    ).join(' ');
    resumen += '<br>';
    Object.entries(promedioPorEstado).forEach(([estado, minsArr]) => {
        const avg = Math.round(minsArr.reduce((a, b) => a + b, 0) / minsArr.length);
        resumen += `<span style="font-size:0.96em;">⏱️ ${estado}: ${isNaN(avg) ? '-' : avg + ' min promedio'}</span>&nbsp;&nbsp;`;
    });
    if (document.getElementById('kanban-summary')) {
        document.getElementById('kanban-summary').innerHTML = resumen;
    } else {
        // Si no existe, créalo antes del board
        const board = document.querySelector('.kanban-board');
        if (board) {
            const div = document.createElement('div');
            div.id = 'kanban-summary';
            div.style.margin = '1em 0';
            div.innerHTML = resumen;
            board.parentNode.insertBefore(div, board);
        }
    }
}

// =========================
// FUNCIONES DE FILTRO
// =========================
function filtrarSolicitudes() {
    const selectedDate = document.getElementById('kanbanDateFilter').value;
    const selectedAfiliacion = document.getElementById('kanbanAfiliacionFilter').value;
    const selectedDoctor = document.getElementById('kanbanDoctorFilter').value;
    // const selectedTipo = document.getElementById('kanbanTipoFiltro').value;

    return allSolicitudes.filter(s => {
        // Determinar tipo
        // let tipo = '';
        // if (s.procedimiento && s.procedimiento.startsWith('CIRUGIAS')) tipo = 'CIRUGIAS';
        // else if (s.procedimiento && s.procedimiento.startsWith('CONSULTA')) tipo = 'CONSULTAS';
        // else if (s.procedimiento && s.procedimiento.startsWith('EXAMEN')) tipo = 'EXAMENES';
        // else if (s.procedimiento && s.procedimiento.startsWith('OPTOMETRIA')) tipo = 'OPTOMETRIA';

        // const coincideTipo = !selectedTipo || tipo === selectedTipo;
        const coincideFecha = !selectedDate || s.fecha_cambio === selectedDate;
        const coincideAfiliacion = !selectedAfiliacion || s.afiliacion === selectedAfiliacion;
        const coincideDoctor = !selectedDoctor || (s.doctor && s.doctor === selectedDoctor);
        return /*coincideTipo &&*/ coincideFecha && coincideAfiliacion && coincideDoctor;
    });
}

function aplicarFiltros() {
    const doctorFiltro = document.getElementById('kanbanDoctorFilter')?.value.toLowerCase() || '';
    const afiliacionFiltro = document.getElementById('kanbanAfiliacionFilter')?.value.toLowerCase() || '';
    const fechaFiltro = document.getElementById('kanbanDateFilter')?.value || '';
    // const tipoFiltro = document.getElementById('kanbanTipoFiltro')?.value || '';

    document.querySelectorAll('.kanban-card').forEach(card => {
        const doctor = card.dataset.doctor?.toLowerCase() || '';
        const afiliacion = card.dataset.afiliacion?.toLowerCase() || '';
        const fecha = card.dataset.fecha || '';
        // const tipo = card.dataset.tipo || '';

        const visible =
            (!doctorFiltro || doctor.includes(doctorFiltro)) &&
            (!afiliacionFiltro || afiliacion.includes(afiliacionFiltro)) &&
            (!fechaFiltro || fecha === fechaFiltro);
        // && (!tipoFiltro || tipo === tipoFiltro);

        card.style.display = visible ? '' : 'none';
    });
    // Después de aplicar los filtros básicos, aplicar el de procedimiento
    aplicarFiltroProcedimiento();

    // Generar el resumen estadístico según los datos filtrados
    // Recopilar los datos filtrados actualmente visibles
    const filtrados = [];
    document.querySelectorAll('.kanban-card').forEach(card => {
        if (card.style.display !== 'none') {
            const formId = card.getAttribute('data-form');
            const obj = allSolicitudes.find(s => String(s.form_id) === String(formId));
            if (obj) filtrados.push(obj);
        }
    });
    generarResumenKanban(filtrados);
}

function aplicarFiltroProcedimiento() {
    const filtro = document.getElementById('filtroProcedimiento')?.value.toLowerCase() || '';
    document.querySelectorAll('.kanban-card').forEach(card => {
        const categoria = card.dataset.procedimiento_categoria?.toLowerCase() || '';
        // Si ya está oculto por otros filtros, no lo mostramos
        if (card.style.display === 'none') return;
        card.style.display = (filtro === '' || categoria.includes(filtro)) ? '' : 'none';
    });

    // Actualizar los filtros dependientes (doctor y fecha) según la categoría seleccionada
    // 1. Obtener los datos actualmente visibles tras aplicar el filtro de procedimiento
    const datosFiltrados = [];
    document.querySelectorAll('.kanban-card').forEach(card => {
        if (card.style.display !== 'none') {
            // Buscar en allSolicitudes el objeto correspondiente por form_id
            const formId = card.getAttribute('data-form');
            const obj = allSolicitudes.find(s => String(s.form_id) === String(formId));
            if (obj) datosFiltrados.push(obj);
        }
    });
    llenarSelectDoctoresYFechas(datosFiltrados);
    // Generar el resumen estadístico según los datos filtrados por procedimiento
    generarResumenKanban(datosFiltrados);
}

// =========================
// POLLING Y RED
// =========================
// Definir showToast si no existe
if (typeof showToast !== 'function') {
    function showToast(mensaje) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: mensaje,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
}

function verificarCambiosRecientes() {
    const url = new URL('/public/ajax/flujo_recientes.php', window.location.origin);
    if (ultimoTimestamp) {
        url.searchParams.set('desde', ultimoTimestamp);
    }

    console.log("⏳ Verificando cambios recientes...");

    fetch(url)
        .then(response => response.json())
        .then(data => {
            //console.log("📦 Cambios recibidos:", data);

            if (data && Array.isArray(data.pacientes) && data.pacientes.length > 0) {
                // Aquí puedes decidir cómo actualizar el Kanban.
                // Por ejemplo, recargar todo, o actualizar solo los pacientes cambiados.
                // Por simplicidad, recargamos todo:
                const today = moment().format('YYYY-MM-DD');
                fetch(`/public/ajax/flujo?fecha=${today}`)
                    .then(response => response.json())
                    .then(flujo => {
                        allSolicitudes = flujo;
                        poblarAfiliacionesUnicas(allSolicitudes);
                        llenarSelectProcedimientoCategorias();
                        renderKanban();
                        aplicarFiltros();
                    });
                // Mostrar banner visual solo si hay actualizaciones recientes
                if (data.pacientes.length > 0) {
                    const alerta = document.createElement('div');
                    alerta.textContent = 'Tablero actualizado ✅';
                    alerta.style.position = 'fixed';
                    alerta.style.top = '20px';
                    alerta.style.right = '20px';
                    alerta.style.padding = '10px 20px';
                    alerta.style.backgroundColor = '#28a745';
                    alerta.style.color = '#fff';
                    alerta.style.fontWeight = 'bold';
                    alerta.style.borderRadius = '5px';
                    alerta.style.boxShadow = '0 2px 6px rgba(0, 0, 0, 0.2)';
                    alerta.style.zIndex = '9999';
                    document.body.appendChild(alerta);

                    setTimeout(() => {
                        document.body.removeChild(alerta);
                    }, 3000);
                }
            }
            if (data && data.timestamp) {
                ultimoTimestamp = data.timestamp;
            }
        })
        .catch(err => console.error('❌ Error al verificar cambios recientes:', err));
}

// =========================
// INICIALIZACIÓN DE INTERFAZ Y LISTENERS
// =========================
$(document).ready(function () {
    // Iniciar polling de cambios recientes cada 30 segundos
    setInterval(verificarCambiosRecientes, 30000);

    // Cargar solicitudes por defecto usando la fecha de hoy al cargar la página
    const today = moment().format('YYYY-MM-DD');
    document.getElementById('kanbanDateFilter').value = today;
    fetch(`/public/ajax/flujo?fecha=${today}`)
        .then(response => response.json())
        .then(data => {
            allSolicitudes = data;
            poblarAfiliacionesUnicas(allSolicitudes);
            llenarSelectProcedimientoCategorias();
            renderKanban();
            aplicarFiltros();
        })
        .catch(error => {
            console.error('Error al cargar las solicitudes del flujo:', error);
        });

    // Filtros básicos: ahora filtran en frontend sin recargar
    $('#kanbanDateFilter').pickadate({
        format: 'yyyy-mm-dd',
        selectMonths: true,
        selectYears: true,
        today: 'Hoy',
        clear: 'Limpiar',
        close: 'Cerrar',
        onStart: function () {
            const picker = this;
            const today = moment().format('YYYY-MM-DD');
            picker.set('select', today, {format: 'yyyy-mm-dd'});
        },
        onSet: function (context) {
            const picker = this;
            const selected = picker.get('select', 'yyyy-mm-dd');
            fetch(`/public/ajax/flujo?fecha=${selected}`)
                .then(response => response.json())
                .then(data => {
                    allSolicitudes = data;
                    poblarAfiliacionesUnicas(allSolicitudes);
                    llenarSelectProcedimientoCategorias();
                    renderKanban();
                    // Aplicar filtros en frontend después de renderizar
                    aplicarFiltros();
                })
                .catch(error => {
                    console.error('Error al cargar las solicitudes del flujo:', error);
                });
        }
    });

    // Listeners para filtros en frontend
    ['kanbanDoctorFilter', 'kanbanAfiliacionFilter', 'kanbanDateFilter', /*'kanbanTipoFiltro',*/ 'filtroProcedimiento'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('input', aplicarFiltros);
        // Para selects (change también)
        if (input && input.tagName === 'SELECT') input.addEventListener('change', aplicarFiltros);
    });
});