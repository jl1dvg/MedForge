// kanban_base.js

// Loader helpers
function showLoader() {
    document.getElementById('loader').style.display = 'block';
}

function hideLoader() {
    document.getElementById('loader').style.display = 'none';
}

// Definir estados para columnas del tablero de Visitas
const ESTADOS_VISITA = [
    {label: "Agendado", id: "agendado"},
    {label: "Llegado", id: "llegado"},
    {label: "Preoperatorio", id: "preoperatorio"},
    {label: "En quirófano", id: "en-quirofano"},
    {label: "Recuperación", id: "recuperacion"},
    {label: "Alta", id: "alta"},
    {label: "Otro", id: "otro"}
];

function renderColumnasVisita() {
    const board = document.querySelector('.kanban-board');
    if (!board) return;

    // Limpiar columnas actuales
    board.innerHTML = '';

    ESTADOS_VISITA.forEach(estado => {
        const col = document.createElement('div');
        col.className = 'kanban-col';
        col.innerHTML = `
            <div class='kanban-column box box-solid box-info rounded shadow-sm p-1 me-0' style='min-width: 250px; flex-shrink: 0;'>
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

    // Recorrer trayectos para poblar los doctores únicos
    const doctoresSet = new Set();
    datosFiltrados.forEach(visita => {
        if (Array.isArray(visita.trayectos)) {
            visita.trayectos.forEach(t => {
                if (t.doctor) doctoresSet.add(t.doctor);
            });
        }
    });
    const doctoresOrdenados = Array.from(doctoresSet).sort((a, b) => a.localeCompare(b, 'es', {sensitivity: 'base'}));
    doctoresOrdenados.forEach(doctor => {
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
    // Fecha: solo usa fechas de la visita
    const fechaFiltro = document.getElementById('kanbanFechaFiltro');
    if (fechaFiltro) {
        fechaFiltro.innerHTML = '<option value="">Todas</option>';
        const fechasSet = new Set(datosFiltrados.map(item => item.fecha_visita));
        const fechasOrdenadas = Array.from(fechasSet).sort().reverse();
        fechasOrdenadas.forEach(fecha => {
            const option = document.createElement('option');
            option.value = fecha;
            option.textContent = fecha;
            fechaFiltro.appendChild(option);
        });
    }
}

function formatearProcedimientoCorto(proc) {
    if (!proc || typeof proc !== 'string') return '';

    // SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-001 - OPTOMETRIA - AMBOS OJOS
    if (proc.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES')) {
        const partes = proc.split(' - ');
        // Toma el cuarto segmento si existe, o el tercero si no hay "AMBOS OJOS"
        return partes[3] ? partes[2] : (partes[2] || '');
    }
    // CIRUGIAS - CYP-OCU-035 - IPL TRATAMIENTO DE OJO SECO (AO POR SESION) - IZQUIERDO
    if (proc.startsWith('CIRUGIAS')) {
        return 'CIRUGIAS';
    }
    // IMAGENES - IMA-DIA-003 - 281306-CAMPIMETRIA COMPUTARIZADA - CAMPO VISUAL (AO) - AMBOS OJOS
    if (proc.startsWith('IMAGENES')) {
        // Busca el nombre del estudio: después del segundo " - "
        const partes = proc.split(' - ');
        // Busca la primera parte que contiene paréntesis, sino el 3er segmento (usualmente el nombre)
        const conParentesis = partes.find(p => p.includes('('));
        return conParentesis || partes[3] || partes[2] || 'IMAGEN';
    }
    // Si nada coincide, muestra el tercer segmento si existe
    const partes = proc.split(' - ');
    return partes[3] || partes[2] || proc;
}

// kanban_base.js (puedes ponerlo al final o cerca del render principal)
function renderTabActivo() {
    const activeTab = document.querySelector('.tab-kanban.active');
    if (!activeTab) return;
    const tipo = activeTab.dataset.tipo;
    if (tipo === 'cirugia' && typeof renderKanbanCirugia === "function") {
        renderKanbanCirugia();
    } else if (tipo === 'consulta' && typeof renderKanbanConsulta === "function") {
        renderKanbanConsulta();
    } else if (tipo === 'examen' && typeof renderKanbanExamen === "function") {
        renderKanbanExamen();
    } else {
        renderKanban();
    }
}

// =========================
// FUNCIONES DE RENDER Y RESUMEN
// =========================
function renderKanban() {
    renderColumnasVisita();
    const filtered = filtrarSolicitudes(); // ya devuelve solo VISITAS de la fecha filtrada
    llenarSelectDoctoresYFechas(filtered);

    // Limpiar columnas
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');

    // Para cronómetro y resumen por estado global (usando estado del primer trayecto, puedes ajustar esto)
    const conteoPorEstado = {};
    const promedioPorEstado = {};

    filtered.forEach(visita => {
        console.log('Visita:', visita); // ← Aquí lo agregas
        // 🚩 NUEVO: Si no tiene trayectos, ignora la visita y no pinta tarjeta
        if (!Array.isArray(visita.trayectos) || visita.trayectos.length === 0) {
            return;
        }

        // Obtén estado global (el más avanzado, el primero, o el que tú decidas)
        let estadoGlobal = '';
        let trayectoPrincipal = visita.trayectos && visita.trayectos[0];

        if (trayectoPrincipal) {
            estadoGlobal = trayectoPrincipal.estado || 'Otro';
        } else {
            estadoGlobal = 'Otro';
        }

        // --- Mapeo defensivo de estados ---
        // Array de IDs válidos según las columnas
        const idsValidos = ESTADOS_VISITA.map(e => e.id);

        // Normaliza el estadoId
        let estadoId = estadoGlobal.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');

        // Si el estadoId no está en los IDs válidos, mapea a "otro"
        if (!idsValidos.includes(estadoId)) {
            estadoGlobal = 'Otro';
            estadoId = 'otro';
        }

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
            <div style="font-size:0.93em;color:#375;">
                ${procedimientos
            .filter(p => p && p !== '(no definido)')
            .slice(0, 2)
            .map(p => formatearProcedimientoCorto(p))
            .join('<br>')}
            </div>            
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

let pollingIntervalId = null;

function startPolling() {
    if (!pollingIntervalId) {
        pollingIntervalId = setInterval(verificarCambiosRecientes, 30000);
    }
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

function verificarCambiosRecientes() {
    const url = new URL('/public/ajax/flujo_recientes.php', window.location.origin);
    if (ultimoTimestamp) {
        url.searchParams.set('desde', ultimoTimestamp);
    }

    showLoader();
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data && Array.isArray(data.pacientes) && data.pacientes.length > 0) {
                // Data nueva, refrescamos el flujo completo (no borramos DOM todavía)
                const today = moment().format('YYYY-MM-DD');
                // 🚩 No borres columnas hasta tener los datos:
                fetch(`/public/ajax/flujo?fecha=${today}&modo=visita`)
                    .then(response => response.json())
                    .then(flujo => {
                        // 1. Compara la data anterior con la nueva para detectar movimientos de estado (opcional)
                        const prevData = JSON.stringify(allSolicitudes);
                        allSolicitudes = flujo;

                        // 2. Refresca los filtros únicos (afiliaciones, doctores, etc.)
                        poblarAfiliacionesUnicas(allSolicitudes);

                        // 3. Repinta SOLO el tab activo, esto ya limpia columnas y vuelve a poner tarjetas con los nuevos estados
                        renderTabActivo();

                        // 4. Loader fuera
                        hideLoader();

                        // 5. Banner visual si hubo realmente cambios en la data (opcional)
                        if (prevData !== JSON.stringify(flujo)) {
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
                    })
                    .catch(err => {
                        hideLoader();
                        console.error('❌ Error al refrescar flujo:', err);
                    });
            } else {
                // No hay cambios recientes, oculta loader si estaba visible
                hideLoader();
            }
            if (data && data.timestamp) {
                ultimoTimestamp = data.timestamp;
            }
        })
        .catch(err => {
            hideLoader();
            console.error('❌ Error al verificar cambios recientes:', err);
        });
}

// =========================
// INICIALIZACIÓN DE INTERFAZ Y LISTENERS
// =========================
$(document).ready(function () {
    // Primero renderiza las columnas de Visitas
    renderColumnasVisita();

    // Iniciar polling de cambios recientes cada 30 segundos SOLO cuando visible
    startPolling();

    // Cargar solicitudes por defecto usando la fecha de hoy al cargar la página
    const today = moment().format('YYYY-MM-DD');
    document.getElementById('kanbanDateFilter').value = today;
    showLoader();
    fetch(`/public/ajax/flujo?fecha=${today}&modo=visita`)
        .then(response => response.json())
        .then(data => {
            allSolicitudes = data;
            poblarAfiliacionesUnicas(allSolicitudes);
            hideLoader();
            renderTabActivo();
        })
        .catch(error => {
            hideLoader();
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
            renderColumnasVisita(); // <- ¡Agregado aquí para resetear columnas antes de recargar!
            showLoader();
            fetch(`/public/ajax/flujo?fecha=${today}&modo=visita`)
                .then(response => response.json())
                .then(data => {
                    allSolicitudes = data;
                    poblarAfiliacionesUnicas(allSolicitudes);
                    hideLoader();
                    renderTabActivo();
                })
                .catch(error => {
                    hideLoader();
                    console.error('Error al cargar las solicitudes del flujo:', error);
                });
        }
    });

    // Listeners para filtros en frontend
    ['kanbanDoctorFilter', 'kanbanAfiliacionFilter', 'kanbanDateFilter'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('input', renderTabActivo);
        if (input && input.tagName === 'SELECT') input.addEventListener('change', renderTabActivo);
    });
});