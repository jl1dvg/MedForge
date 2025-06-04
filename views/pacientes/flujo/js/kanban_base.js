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
    const filtered = filtrarSolicitudes(); // ya devuelve solo VISITAS de la fecha filtrada

    // Limpiar columnas
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');

    // Para cronómetro y resumen por estado global (usando estado del primer trayecto, puedes ajustar esto)
    const conteoPorEstado = {};
    const promedioPorEstado = {};

    filtered.forEach(visita => {
        // Obtén estado global (el más avanzado, el primero, o el que tú decidas)
        let estadoGlobal = '';
        let trayectoPrincipal = visita.trayectos && visita.trayectos[0];

        if (trayectoPrincipal) {
            estadoGlobal = trayectoPrincipal.estado || 'Otro';
        } else {
            estadoGlobal = 'Otro';
        }

        // Declarar estadoId solo una vez y reutilizar
        let estadoId = estadoGlobal.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
        conteoPorEstado[estadoId] = (conteoPorEstado[estadoId] || 0) + 1;

        // Cronómetro: tiempo desde la llegada (hora_llegada de la visita)
        let minutosDesdeLlegada = null;
        if (visita.hora_llegada) {
            const llegada = new Date(visita.hora_llegada.replace(' ', 'T'));
            const ahora = new Date();
            minutosDesdeLlegada = Math.floor((ahora - llegada) / 60000);
            if (!promedioPorEstado[estadoGlobal]) promedioPorEstado[estadoGlobal] = [];
            promedioPorEstado[estadoGlobal].push(minutosDesdeLlegada);
        }

        // Iconos según tipos de trayecto
        const tipos = new Set();
        const procedimientos = [];
        const doctores = new Set();
        visita.trayectos.forEach(t => {
            if (t.procedimiento && t.procedimiento.includes('CIRUGIAS')) tipos.add('🔪');
            else if (t.procedimiento && t.procedimiento.includes('CONSULTA')) tipos.add('🩺');
            else if (t.procedimiento && t.procedimiento.includes('EXAMEN')) tipos.add('🔬');
            else if (t.procedimiento && t.procedimiento.includes('OPTOMETRIA')) tipos.add('👓');
            else if (t.procedimiento && t.procedimiento !== '(no definido)') tipos.add('📄');
            procedimientos.push(t.procedimiento);
            if (t.doctor) doctores.add(t.doctor);
        });

        // Tooltips: historial de estados por trayecto
        let historialTooltip = '';
        visita.trayectos.forEach(t => {
            if (Array.isArray(t.historial_estados) && t.historial_estados.length > 0) {
                historialTooltip += t.procedimiento + ':\n' +
                    t.historial_estados.map(h => `${h.estado}: ${moment(h.fecha_hora_cambio).format('DD-MM-YYYY HH:mm')}`).join('\n') + '\n\n';
            }
        });

        // Badge semáforo por minutos en clínica
        let color = '#007bff';
        if (minutosDesdeLlegada !== null) {
            if (minutosDesdeLlegada > 180) color = '#dc3545';
            else if (minutosDesdeLlegada > 90) color = '#ffc107';
            else if (minutosDesdeLlegada > 30) color = '#198754';
        }

        // Tarjeta de visita (una por visita_id)
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card view-details';
        tarjeta.setAttribute('data-visita-id', visita.visita_id);
        tarjeta.title = historialTooltip || '';

        tarjeta.innerHTML = `
            <div>
                <span style="color:${color};font-weight:bold;">⏱️ ${minutosDesdeLlegada ?? '--'} min</span>
                ${[...tipos].join(' ')}
            </div>
            <div style="font-size:1.08em;font-weight:600;">
                <i class="mdi mdi-account"></i> ${[visita.fname, visita.lname, visita.lname2].filter(Boolean).join(' ')}
            </div>
            <div style="font-size:0.95em; color:#555;">
                <i class="mdi mdi-card-account-details"></i> <b>${visita.hc_number}</b>
            </div>
            <div style="font-size:0.95em;">
                <i class="mdi mdi-calendar"></i> ${visita.hora_llegada ? visita.hora_llegada.slice(11, 16) : '-'}
            </div>
            <div style="font-size:0.93em; color:#375;">
                <i class="mdi mdi-hospital-building"></i> ${trayectoPrincipal?.afiliacion || visita.afiliacion || '-'}
            </div>
            <div>
                <span style="font-weight:600">Trayectos:</span> ${[...tipos].length > 0 ? [...tipos].join(' + ') : 'Ninguno'}
            </div>
            <div style="font-size:0.93em;color:#375;">${procedimientos.filter(p => p && p !== '(no definido)').slice(0, 2).join('<br>')}</div>
            <div>
                <i class="mdi mdi-stethoscope"></i> ${[...doctores].join(', ')}
            </div>
            <div style="margin-top:3px;font-size:0.93em;"><b>Estado:</b> ${estadoGlobal}</div>
        `;

        // Agrega al kanban por estado global
        const col = document.getElementById('kanban-' + estadoId);
        if (col) col.appendChild(tarjeta);
        else console.warn(`No se encontró la columna para el estado: "${estadoGlobal}"`);
    });

    // Mostrar/ocultar badges de conteo por estado
    Object.entries(conteoPorEstado).forEach(([estadoKey, count]) => {
        const badge = document.getElementById(`badge-${estadoKey}`);
        if (badge) badge.style.display = count > 4 ? 'inline-block' : 'none';
    });

    // Resumen estadístico, usando promedio por estado global (minutos en clínica)
    let resumen = `<span style="font-weight:600;">📊 Total pacientes: <b>${filtered.length}</b></span> &nbsp;|&nbsp; `;
    resumen += Object.entries(conteoPorEstado).map(([estado, cant]) =>
        `<span style="margin-right:10px;">${estado}: <b>${cant}</b></span>`
    ).join(' ');
    resumen += '<br>';
    Object.entries(promedioPorEstado).forEach(([estado, minsArr]) => {
        const avg = Math.round(minsArr.reduce((a, b) => a + b, 0) / minsArr.length);
        resumen += `<span style="font-size:0.96em;">⏱️ ${estado}: ${isNaN(avg) ? '-' : avg + ' min promedio en clínica'}</span>&nbsp;&nbsp;`;
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

    return allSolicitudes.filter(visita => {
        // Filtro por fecha de la visita (no de trayecto)
        const coincideFecha = !selectedDate || visita.fecha_visita === selectedDate;

        // Filtro por afiliación: debe cumplirse al menos en uno de los trayectos
        const coincideAfiliacion = !selectedAfiliacion || (
            Array.isArray(visita.trayectos) &&
            visita.trayectos.some(t => t.afiliacion === selectedAfiliacion)
        );

        // Filtro por doctor: al menos un trayecto debe coincidir
        const coincideDoctor = !selectedDoctor || (
            Array.isArray(visita.trayectos) &&
            visita.trayectos.some(t => t.doctor === selectedDoctor)
        );

        return coincideFecha && coincideAfiliacion && coincideDoctor;
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
                fetch(`/public/ajax/flujo?fecha=${today}&modo=visita`)
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
    fetch(`/public/ajax/flujo?fecha=${today}&modo=visita`)
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
            fetch(`/public/ajax/flujo?fecha=${selected}&modo=visita`)
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