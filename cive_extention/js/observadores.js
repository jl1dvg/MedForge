// Variables globales para pacientes de optometría y prioritarios
let pacientesOptometriaHoy = [];
let pacientesPrioritarios = [];
(function () {

    function actualizarColorFilasPorTiempoYAfiliacion() {
        const tabla = document.querySelector('table.kv-grid-table');
        if (!tabla) return;

        const excluirAfiliaciones = ['CONTRIBUYENTE VOLUNTARIO', 'CONYUGE', 'CONYUGE PENSIONISTA', 'ISSFA', 'ISSPOL', 'MSP', 'SEGURO CAMPESINO', 'SEGURO CAMPESINO JUBILADO', 'SEGURO GENERAL', 'SEGURO GENERAL JUBILADO', 'SEGURO GENERAL POR MONTEPIO', 'SEGURO GENERAL TIEMPO PARCIAL'];

        const filas = tabla.querySelectorAll('tbody tr');
        filas.forEach((fila) => {
            const afiliacionTd = fila.querySelector('td[data-col-seq="11"]');
            const tiempoTd = fila.querySelector('td[data-col-seq="17"]');

            if (afiliacionTd && tiempoTd) {
                const afiliacionTexto = afiliacionTd.textContent.trim();
                const tiempoTexto = tiempoTd.querySelector('span[name="intervalos"]')?.textContent.trim();

                if (!excluirAfiliaciones.includes(afiliacionTexto) && tiempoTexto) {
                    const [horas, minutos] = tiempoTexto.split(':').map(Number);
                    const tiempoTotalMinutos = horas * 60 + minutos;

                    // Aplicar clases en función del tiempo de espera
                    if (tiempoTotalMinutos >= 30) {
                        fila.classList.add('espera-prolongada-particular');
                        fila.classList.remove('llegado-particular');
                    } else if (tiempoTotalMinutos > 0) {
                        fila.classList.add('llegado-particular');
                        fila.classList.remove('espera-prolongada-particular');
                    }
                } else {
                    // Remover cualquier clase previa si no cumple la condición
                    fila.classList.remove('llegado-particular', 'espera-prolongada-particular');
                }
            }
        });
    }

    // Nueva función: marcar filas en atención optometría
    async function actualizarEstadoAtencionOptometria() {
        if (window.__bloqueoActualizacionOpto) return;
        window.__bloqueoActualizacionOpto = true;
        setTimeout(() => window.__bloqueoActualizacionOpto = false, 10000); // Bloqueo de 10s

        const tabla = document.querySelector('table.kv-grid-table');
        if (!tabla) return;

        const fechaHoy = new Date().toISOString().split('T')[0];

        try {
            const respuesta = await fetch(`https://asistentecive.consulmed.me/api/proyecciones/estado_optometria.php?fecha=${fechaHoy}`);
            const pacientesEnAtencion = await respuesta.json();
            // Normalizar los IDs recibidos del API a strings limpias
            const pacientesFormIds = pacientesEnAtencion.map(id => id.toString().trim());
            console.log('📋 Pacientes en atención OPTOMETRIA hoy:', pacientesEnAtencion);

            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach((fila) => {
                const formIdTd = fila.querySelector('td[data-col-seq="5"]');
                if (formIdTd) {
                    const formId = formIdTd.textContent.trim();
                    if (pacientesFormIds.includes(formId.toString().trim())) {
                        fila.classList.add('atendiendo-optometria');
                        // Cambios solicitados:
                        fila.title = 'Paciente actualmente en atención en optometría';

                        const primeraCelda = fila.querySelector('td');
                        if (primeraCelda && !primeraCelda.querySelector('.icono-optometria')) {
                            const icono = document.createElement('span');
                            icono.classList.add('glyphicon', 'glyphicon-eye-open', 'icono-optometria');
                            icono.style.marginLeft = '5px';
                            icono.style.color = '#007bff';
                            icono.title = 'Paciente en atención en optometría';
                            primeraCelda.appendChild(icono);
                        }
                    } else {
                        fila.classList.remove('atendiendo-optometria');
                        // Cambios solicitados:
                        fila.removeAttribute('title');
                        const iconoExistente = fila.querySelector('.icono-optometria');
                        if (iconoExistente) iconoExistente.remove();
                    }
                }
            });
        } catch (error) {
            console.error('Error al actualizar el estado de atención en optometría:', error);
        }
    }

    function mostrarNotificacionPrioridadOptometria(listaPacientes, pacientesOptometriaHoy) {
        // Define la variable lista con el valor correcto para evitar ReferenceError
        const lista = pacientesPrioritarios;
        if (!Array.isArray(listaPacientes) || !Array.isArray(pacientesOptometriaHoy)) {
            console.warn('⚠️ Lista de pacientes prioritarios no válida:', listaPacientes);
            return;
        }

        lista.forEach((p) => {
            if (!p || !p.nombre) return; // Asegura que el paciente tenga datos válidos

            const alerta = document.createElement("div");
            alerta.className =
                "myadmin-alert myadmin-alert-img myadmin-alert-click alert-warning myadmin-alert-bottom alertbottom2";
            alerta.style.display = "block";
            alerta.style.position = "fixed";
            alerta.style.bottom = "0";
            alerta.style.left = "0";
            alerta.style.width = "100%";
            alerta.style.zIndex = "9999";
            alerta.innerHTML = `
      <img src="https://cdn-icons-png.flaticon.com/512/2920/2920050.png" class="img" alt="img" style="width:40px; height:40px;">
      <a href="#" class="closed" onclick="this.parentElement.remove()">×</a>
      <h4>Paciente en atención OPTOMETRÍA</h4>
      <b>${p.nombre}</b> está siendo atendido.
    `;

            document.body.appendChild(alerta);
            setTimeout(() => {
                alerta.remove();
            }, 10000); // Desaparece tras 10 segundos
        });
    }

    // Variable global para controlar el bloqueo de notificación
    let _bloqueoNotificacion = false;

    function observarCambiosEnTablaYPaginacion() {
        const contenedorTabla = document.querySelector('.kv-grid-container');
        if (!contenedorTabla) return;
        const observer = new MutationObserver(() => {
            if (_bloqueoNotificacion) return;
            _bloqueoNotificacion = true;

            setTimeout(() => {
                actualizarColorFilasPorTiempoYAfiliacion();
                mostrarNotificacionPrioridadOptometria(pacientesPrioritarios, pacientesOptometriaHoy);
                _bloqueoNotificacion = false;
            }, 500);
        });

        observer.observe(contenedorTabla, {childList: true, subtree: true});
    }

    function iniciarObservadores() {
        const intervalo = setInterval(() => {
            const contenedorTabla = document.querySelector('.kv-grid-container');
            if (contenedorTabla) {
                clearInterval(intervalo);
                observarCambiosEnTablaYPaginacion();
                actualizarColorFilasPorTiempoYAfiliacion();
                mostrarNotificacionPrioridadOptometria(pacientesPrioritarios, pacientesOptometriaHoy);
                actualizarEstadoAtencionOptometria();
            }
        }, 250);
    }

    function obtenerColumnMap() {
        const map = {};
        const ths = document.querySelectorAll('#crud-datatable-por-atender thead tr th');

        ths.forEach((th, index) => {
            const texto = th.textContent.trim().toLowerCase().replace(/[\n\r]+/g, ' ').replace(/\s+/g, ' ');
            if (texto && !map[texto]) {
                map[texto] = index;
            }
        });

        // Prevenir ejecución repetida
        if (window.__mapeoYaEjecutado) return;
        window.__mapeoYaEjecutado = true;
        setTimeout(() => {
            window.__mapeoYaEjecutado = false;
        }, 10000); // Espera 10s antes de permitir otro mapeo
        console.log("🔍 Mapeo de columnas detectado:", map);
        return map;
    }

    function observarPacientesPorAtender() {
        const selectorTabla = '#crud-datatable-por-atender table.kv-grid-table';
        const tabla = document.querySelector(selectorTabla);
        if (!tabla) return;

        const columnMap = obtenerColumnMap();

        const filas = tabla.querySelectorAll('tbody tr[data-key]');
        const pacientes = [];
        let fechaBusqueda = new URLSearchParams(window.location.search).get('DocSolicitudProcedimientosDoctorSearch[fechaBusqueda]');
        if (!fechaBusqueda) {
            // Busca el valor del input de fecha si no está en la URL
            const inputFecha = document.querySelector('#docsolicitudprocedimientosdoctorsearch-fechabusqueda');
            if (inputFecha && inputFecha.value) {
                fechaBusqueda = inputFecha.value.trim();
                console.log('🗓️ Fecha extraída del input:', fechaBusqueda);
            }
        }

        // Si sigue sin fecha, muestra un warning y NO envíes datos
        if (!fechaBusqueda) {
            alert('No se pudo determinar la fecha de la agenda. Selecciona una fecha antes de sincronizar.');
            return;
        }
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('td');
            // Ajusta los nombres de clave según los textos reales en el thead (minúsculas y espacios normalizados)
            const paciente = {
                id: celdas[columnMap["id"]]?.textContent.trim() || '',
                doctor: celdas[columnMap["doctor"]]?.textContent.trim() || '',
                hora: celdas[columnMap["hora"]]?.textContent.trim() || '',
                nombre: celdas[columnMap["paciente"]]?.textContent.trim() || '',
                identificacion: celdas[columnMap["identificación"]]?.textContent.trim() || '',
                afiliacion: celdas[columnMap["afiliación"]]?.textContent.trim() || '',
                procedimiento: celdas[columnMap["procedimiento"]]?.textContent.trim() || '',
                estado: celdas[columnMap["estado"]]?.textContent.trim() || '',
                fechaCaducidad: celdas[columnMap["fecha caducidad"]]?.textContent.trim() || ''
            };

            console.log("🧪 Datos extraídos:", paciente);

            if (!paciente.id || !paciente.identificacion || !paciente.procedimiento) {
                console.warn("⛔ Faltan datos clave, omitiendo fila:", paciente);
                return;
            }

            const partesNombre = paciente.nombre.split(/\s+/);
            const datosNombre = {
                lname: partesNombre[0] || '',
                lname2: partesNombre[1] || '',
                fname: partesNombre[2] || '',
                mname2: partesNombre.slice(3).join(' ') || ''
            };

            pacientes.push({
                hcNumber: paciente.identificacion,
                form_id: paciente.id,
                procedimiento_proyectado: paciente.procedimiento,
                fname: datosNombre.fname,
                mname: datosNombre.mname,
                lname: datosNombre.lname,
                lname2: datosNombre.lname2,
                doctor: paciente.doctor,
                hora: paciente.hora,
                afiliacion: paciente.afiliacion,
                estado: paciente.estado,
                fecha: fechaBusqueda,
                fechaCaducidad: paciente.fechaCaducidad,
                nombre_completo: paciente.nombre
            });
        });

        pacientes.forEach(p => {
            if (!p || !p.id) return;
            // continuar lógica...
        });

        if (pacientes.length > 0) {
            fetch('https://asistentecive.consulmed.me/api/proyecciones/guardar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(pacientes)
            })
                .then(res => res.json())
                .then(data => {
                    console.log('✅ Sincronización exitosa:', data);
                    // Asignar pacientesOptometriaHoy y pacientesPrioritarios tras sincronización
                    if (data && Array.isArray(data.detalles)) {
                        pacientesOptometriaHoy = data.detalles.map(p => p.id);
                        pacientesPrioritarios = data.detalles.filter(p => p.afiliacion === 'PRIORITARIO').map(p => p.id);
                    }
                })
                .catch(err => {
                    console.error('❌ Error en la sincronización', err);
                });
        }
    }

    // Inicializar variable global para evitar bucles infinitos al seleccionar "ver todo"
    window._ultimaClaveProcesada = null;

    // --- localStorage parseo seguro para 'professional' ---
    let professional = localStorage.getItem("professional");
    try {
        professional = professional ? JSON.parse(professional) : null;
    } catch (e) {
        console.warn("Error al parsear 'professional':", e);
        professional = null;
    }

    function observarTablaPorAtenderSiCambio() {
        const urlParams = new URLSearchParams(window.location.search);
        const paginaActual = urlParams.get('por-atender-page') || '1'; // tratar sin parámetro como página 1
        const claveMostrarTodos = Array.from(urlParams).find(([k, v]) => k.includes('_tog') && v === 'all');
        const claveActual = claveMostrarTodos ? claveMostrarTodos[0] : null;

        const claveCombinada = `p${paginaActual}_all${claveActual ? '_yes' : '_no'}`;

        if (claveCombinada !== window._ultimaClaveProcesada) {
            window._ultimaClaveProcesada = claveCombinada;
            setTimeout(() => {
                // Esperar a que la tabla esté actualizada antes de sincronizar
                requestAnimationFrame(() => {
                    observarPacientesPorAtender();
                });
            }, 500);
        }
    }

    // Observador principal para detectar cambios en la tabla de por atender
    const observadorPorAtender = new MutationObserver((mutations) => {
        const tabla = document.querySelector('#crud-datatable-por-atender table.kv-grid-table');
        if (tabla) observarTablaPorAtenderSiCambio();
    });

    // Incluir estilos para notificación
    const estilo = document.createElement('style');
    estilo.innerHTML = `
    .llegado-particular {
        background-color: #FFD700 !important; /* Color para menos de 30 min */
    }

    .espera-prolongada-particular {
        background-color: #FF6347 !important; /* Color para más de 30 min */
    }

    .atendiendo-optometria {
        background-color: #add8e6 !important; /* Azul claro */
        color: black !important;
    }
    .icono-optometria {
        font-size: 14px;
        vertical-align: middle;
    }
    /* Notificación estilos básicos para .myadmin-alert y .alertbottom2 si no existen */
    .myadmin-alert {
        position: fixed;
        left: 0;
        right: 0;
        width: 100%;
        max-width: none;
        border-radius: 0;
        z-index: 9999;
        background: #fff8e1;
        color: #8a6d3b;
        border: 1px solid #faebcc;
        box-shadow: 0 4px 10px rgba(0,0,0,0.18);
        padding: 20px 30px 20px 70px;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 15px;
        transition: opacity 0.4s;
    }
    .myadmin-alert-img .img {
        position: absolute;
        left: 25px;
        top: 20px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
    }
    .myadmin-alert h4 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 17px;
        font-weight: bold;
    }
    .myadmin-alert .closed {
        position: absolute;
        top: 8px;
        right: 12px;
        color: #a94442;
        font-size: 22px;
        text-decoration: none;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    .myadmin-alert-bottom.alertbottom2 {
        bottom: 40px;
        top: auto;
    }
    @keyframes slideUp {
        from {
            transform: translateY(100%);
            opacity: 0;
        }
        to {
            transform: translateY(0%);
            opacity: 1;
        }
    }
    .slide-up {
        animation: slideUp 0.5s ease-out;
    }
    `;
    document.head.appendChild(estilo);

    // Asignar funciones a `window` para que sean accesibles globalmente
    window.actualizarColorFilasPorTiempoYAfiliacion = actualizarColorFilasPorTiempoYAfiliacion;
    window.observarCambiosEnTablaYPaginacion = observarCambiosEnTablaYPaginacion;
    window.iniciarObservadores = iniciarObservadores;

    const contenedorGeneral = document.body;
    if (contenedorGeneral) {
        // Inicialización de la variable global justo antes de iniciar la observación
        window._ultimaClaveProcesada = null;
        observadorPorAtender.observe(contenedorGeneral, {
            childList: true, subtree: true
        });
    }
})();