// js/content_script.js
(function () {
    // Crear el botón flotante
    const button = document.createElement('button');
    button.id = 'floatingButton';
    button.className = 'actionable-icon';

    // Añadir la imagen del botón
    const img = document.createElement('img');
    img.src = chrome.runtime.getURL('icon.png'); // Asegúrate de que la ruta de la imagen es correcta
    img.alt = 'Icono Flotante';
    button.appendChild(img);

    // Crear el elemento de manejo para arrastrar (tres puntos)
    const dragHandle = document.createElement('div');
    dragHandle.className = 'drag-handle';
    button.appendChild(dragHandle);

    // Añadir el botón al body
    document.body.appendChild(button);

    // Variables para el manejo del arrastre
    let isDragging = false;
    let initialY;
    let currentY = button.getBoundingClientRect().top; // Posición inicial del botón
    let offsetY = 0; // Desplazamiento acumulado

    // Función para iniciar el arrastre
    dragHandle.addEventListener('mousedown', function (e) {
        isDragging = true;
        initialY = e.clientY; // Guardar la posición del cursor
        button.style.transition = 'none';
    });

    // Función para mover el botón
    document.addEventListener('mousemove', function (e) {
        if (isDragging) {
            offsetY = e.clientY - initialY;
            let newTranslateY = currentY + offsetY; // Nueva posición basada en la original más el desplazamiento

            const maxY = window.innerHeight - button.offsetHeight;
            const minY = 0;

            // Asegurarse de que el botón se mantenga dentro de los límites de la pantalla
            newTranslateY = Math.max(minY, Math.min(maxY, newTranslateY));

            button.style.transform = `translateY(${newTranslateY - currentY}px)`;
        }
    });

    // Función para terminar el arrastre
    document.addEventListener('mouseup', function () {
        if (isDragging) {
            isDragging = false;
            currentY += offsetY; // Actualizar la posición actual del botón
            offsetY = 0; // Reiniciar el desplazamiento
            button.style.transition = 'background 100ms ease-out, box-shadow 100ms ease-out'; // Reactiva la transición
        }
    });

    // Crear el contenedor del popup flotante
    const popup = document.createElement('div');
    popup.id = 'floatingPopup';
    popup.innerHTML = `
            <div class="popup-content">
                <div class="popup-header">
                    <span>Opciones</span>
                    <button class="popup-close-btn" id="popupCloseBtn">&times;</button>
                </div>
                <div id="notificaciones" class="section active">
                <div id="contenedorNotificaciones"></div>
            </div>
                <div id="inicio" class="section active">
        <div class="grid-container">
            <div id="btnExamenes" class="grid-item">
                <i class="fas fa-notes-medical"></i>
                Exámenes
            </div>
            <div id="btnProtocolos" class="grid-item">
                <i class="fas fa-file-alt"></i>
                Protocolos
            </div>
            <div id="btnConsulta" class="grid-item">
                <i class="fas fa-user-md"></i>
                Consulta
            </div>
            <div id="btnRecetas" class="grid-item">
                <i class="fas fa-user-md"></i>
                Recetas
            </div>
            <!-- Añade más botones si es necesario -->
        </div>
    </div>
                <div id="examenes" class="section">
                    <div class="card">
                        <div class="card-header bg-primary text-white">Exámenes</div>
                        <div class="card-body">
                            <div class="grid-container" id="contenedorExamenes"></div>
                        </div>
                        <div class="card-footer bg-secondary text-white">
                            <button id="btnBackExamenes" class="btn btn-danger">
                                <i class="fas fa-arrow-alt-circle-left"></i> Atrás
                            </button>
                        </div>
                    </div>
                </div>
    <div id="protocolos" class="section">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span>Protocolos</span>
            </div>
            <div class="card-body">
                <div class="grid-container" id="contenedorProtocolos"></div>
            </div>
            <div class="card-footer bg-secondary text-white">
                <button id="btnBackProtocolos" class="btn btn-danger">
                    <i class="fas fa-arrow-alt-circle-left"></i> Atrás
                </button>
            </div>
        </div>
    </div>
    <div id="procedimientos" class="section">
        <div class="card">
            <div class="card-header bg-primary text-white">Procedimientos</div>
            <div class="card-body">
                <div class="grid-container" id="contenedorProcedimientos"></div>
            </div>
            <div class="card-footer bg-secondary text-white">
                <button id="btnBackProcedimientos" class="btn btn-danger">
                    <i class="fas fa-arrow-alt-circle-left"></i> Atrás
                </button>
                <button id="btnGeneratePDF" class="btn btn-success btn-sm">
                    <i class="fas fa-file-pdf"></i> Descargar PDF
                </button>
            </div>
        </div>
    </div>
    <div id="recetas" class="section">
        <div class="card">
            <div class="card-header bg-primary text-white">Recetas</div>
            <div class="card-body">
                <div class="grid-container" id="contenedorRecetas"></div>
            </div>
            <div class="card-footer bg-secondary text-white">
                <button id="btnBackRecetas" class="btn btn-danger">
                    <i class="fas fa-arrow-alt-circle-left"></i> Atrás
                </button>
            </div>
        </div>
    </div>
    <div id="consulta" class="section">
        <div class="card">
            <div class="card-header bg-primary text-white">Consulta</div>
            <div class="card-body">
                <div class="grid-container">
                    <div id="btnConsultaAnterior" class="grid-item">
                        <i class="fas fa-user-md"></i>
                        Consulta Anterior
                    </div>
                    <div id="btnPOP" class="grid-item">
                        <i class="fas fa-file-alt"></i>
                        Control POP
                    </div>
                </div>
            </div>
            <div class="card-footer bg-secondary text-white">
                <button id="btnBackConsulta" class="btn btn-danger">
                    <i class="fas fa-arrow-alt-circle-left"></i> Atrás
                </button>
            </div>
        </div>
    </div>
            </div>
        `;
    document.body.appendChild(popup);

    // Añadir la funcionalidad draggable al botón
    button.addEventListener('mousedown', function (e) {
        let shiftY = e.clientY - button.getBoundingClientRect().top;

        function moveAt(pageY) {
            button.style.top = pageY - shiftY + 'px';
            button.style.bottom = 'auto'; // Para que no interfiera con el posicionamiento
        }

        function onMouseMove(event) {
            moveAt(event.pageY);
        }

        // Añadir el evento mousemove al document
        document.addEventListener('mousemove', onMouseMove);

        // Eliminar los eventos mousemove y mouseup al soltar el botón
        document.addEventListener('mouseup', function () {
            document.removeEventListener('mousemove', onMouseMove);
        }, {once: true});
    });

    button.ondragstart = function () {
        return false;
    };

    // Función para mostrar y ocultar el popup
    function togglePopup() {
        popup.classList.toggle('active');
        console.log('Toggle popup, estado:', popup.classList.contains('active'));
    }

    // Añadir el evento click al botón flotante para mostrar/ocultar el popup
    button.addEventListener('click', togglePopup);

    // Añadir el evento click al botón de cierre para ocultar el popup
    document.getElementById('popupCloseBtn').addEventListener('click', togglePopup);

    // Añadir la hoja de estilos al documento
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = chrome.runtime.getURL('css/floating_button.css');
    document.head.appendChild(link);

    // Añadir la hoja de estilos del popup al documento
    const popupLink = document.createElement('link');
    popupLink.rel = 'stylesheet';
    popupLink.href = chrome.runtime.getURL('css/floating_popup.css');
    document.head.appendChild(popupLink);

    // Añadir la hoja de estilos de Font Awesome al documento
    const fontAwesomeLink = document.createElement('link');
    fontAwesomeLink.rel = 'stylesheet';
    fontAwesomeLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css';
    document.head.appendChild(fontAwesomeLink);

    // Función para observar la tabla de pacientes y añadir listeners a las filas cuando se carga la página
    function inicializarTablaPacientes() {
        // Verificar si la URL contiene la ruta específica
        const rutaValida = window.location.pathname.includes('/documentacion/doc-solicitud-procedimientos/index-doctor');

        // Verificar si el título del panel es "PACIENTES POR ATENDER"
        const panelTitle = document.querySelector('h3.panel-title');
        const tituloValido = panelTitle && panelTitle.textContent.trim().includes('PACIENTES POR ATENDER');

        // Si ninguna de las condiciones es verdadera, no ejecutar la función
        if (!rutaValida && !tituloValido) {
            console.log("La función 'inicializarTablaPacientes' no se ejecutará porque no cumple con las condiciones.");
            return;
        }

        console.log("Condiciones cumplidas. Inicializando tabla de pacientes...");

        // Seleccionar la tabla
        const tabla = document.querySelector('table.kv-grid-table'); // Selector de la tabla

        if (!tabla) {
            console.log("No se encontró la tabla de pacientes.");
            return;
        }

        console.log("Tabla de pacientes encontrada. Añadiendo listeners a las filas...");

        // Agregar listeners a las filas ya presentes en la tabla
        const filas = tabla.querySelectorAll('tr[data-key]');
        filas.forEach((fila) => {
            console.log("Añadiendo listener a la fila:", fila); // Depuración: Ver la fila a la que se le añade el listener
            agregarListenerAFila(fila);
        });
    }


    function descomponerNombreCompleto(patientName) {
        const partes = patientName.split(' ');

        // Aseguramos que al menos haya dos apellidos y dos nombres
        const lname = partes[0] || ''; // Primer apellido
        const lname2 = partes[1] || ''; // Segundo apellido
        const fname = partes[2] || ''; // Primer nombre
        const mname = partes[3] || ''; // Segundo nombre

        return {lname, lname2, fname, mname};
    }

    function agregarListenerAFila(fila) {
        fila.addEventListener('click', function () {
            // Extraer los valores de Identificación y Fecha de Caducidad de la fila clickeada
            const doctor = fila.querySelector('td[data-col-seq="6"]')?.textContent.trim() || null;
            const patientName = fila.querySelector('td[data-col-seq="8"]')?.textContent.trim() || null;
            const identificacion = fila.querySelector('td[data-col-seq="9"]')?.textContent.trim() || null;
            const afiliacion = fila.querySelector('td[data-col-seq="11"]')?.textContent.trim() || null;
            const procedimiento_proyectado = fila.querySelector('td[data-col-seq="13"]')?.textContent.trim() || null;

            // Extraer el form_id del enlace
            const enlace = fila.querySelector('td[data-col-seq="13"]');
            const form_id = enlace?.getAttribute('onclick')?.match(/id=(\d+)/)?.[1] || null;

            let fechaCaducidad = fila.querySelector('td[data-col-seq="16"]')?.textContent.trim();
            if (!fechaCaducidad || fechaCaducidad === '(no definido)') {
                fechaCaducidad = null;
            }

            const {lname, lname2, fname, mname} = descomponerNombreCompleto(patientName);

            if (identificacion && lname && fname && form_id) {
                enviarDatosAHC(identificacion, lname, lname2, fname, mname, afiliacion, doctor, procedimiento_proyectado, fechaCaducidad, form_id);
            }
        });
    }

    function enviarDatosAHC(hcNumber, lname, lname2, fname, mname, afiliacion, doctor, procedimiento_proyectado, fechaCaducidad, form_id) {
        const url = 'https://cive.consulmed.me/interface/guardar_datos.php';

        const data = {
            hcNumber, lname, lname2, fname, mname, afiliacion, doctor, procedimiento_proyectado, fechaCaducidad, form_id
        };

        fetch(url, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data),
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Datos guardados correctamente.');
                }
            })
            .catch(error => {
                console.error('Error al enviar los datos:', error);
                if (error instanceof TypeError) {
                    console.error("Tipo de error detectado. ¿Problema de conexión?");
                }
            });
    }

// Llamar a la función para inicializar la tabla cuando el DOM esté completamente cargado
    window.addEventListener('load', inicializarTablaPacientes);

// Función para extraer datos del div y enviar al servidor
    function extraerDatosYEnviar() {
        const btnGuardar = document.getElementById('interconsulta-btn-guardar');
        if (btnGuardar) btnGuardar.disabled = true;

        // Inicializar el objeto data
        const data = {};

        // Determinar el URL según el tipo de formato
        const isProtocoloQuirurgico = document.querySelector('#consultasubsecuente-membrete') !== null;
        const url = isProtocoloQuirurgico ? 'https://cive.consulmed.me/interface/protocolos_datos.php' : 'https://cive.consulmed.me/interface/datos_consulta.php';

        if (isProtocoloQuirurgico) {
            // Extraer datos para protocolo quirúrgico
            console.log('Protocolo Quirúrgico detectado. Extrayendo datos...');

            const div = document.querySelector('.media-body.responsive');

            if (!div) {
                console.warn('No se encontró el div con los datos del paciente.');
                return;
            }

            // Datos básicos del paciente
            data.hcNumber = div.querySelector('p:nth-of-type(2)')?.textContent.replace('HC #:', '').trim() || '';
            data.fechaNacimiento = div.querySelector('p:nth-of-type(4)')?.textContent.replace('Fecha de Nacimiento:', '').trim() || '';
            data.sexo = div.querySelector('p:nth-of-type(6)')?.textContent.trim() || '';
            data.celular = div.querySelector('p:nth-of-type(7)')?.textContent.replace('Celular:', '').trim() || '';
            data.ciudad = div.querySelector('p:nth-of-type(8)')?.textContent.trim() || '';

            // Validación básica
            if (!data.hcNumber) {
                console.error('El número de HC es obligatorio.');
                return;
            }

            // Extraer el `idSolicitud` del URL actual
            const urlParams = new URLSearchParams(window.location.search);
            data.form_id = urlParams.get('idSolicitud') || 'N/A';
            data.fechaActual = new Date().toISOString().slice(0, 10);

            // Extraer valores de los textareas
            data.membrete = document.querySelector('#consultasubsecuente-membrete')?.value.trim() || '';
            data.procedimiento_id = document.querySelector('#consultasubsecuente-piepagina')?.value.trim() || '';
            data.dieresis = document.querySelector('#consultasubsecuente-dieresis')?.value.trim() || '';
            data.exposicion = document.querySelector('#consultasubsecuente-exposicion')?.value.trim() || '';
            data.hallazgo = document.querySelector('#consultasubsecuente-hallazgo')?.value.trim() || '';
            data.operatorio = document.querySelector('#consultasubsecuente-operatorio')?.value.trim() || '';
            data.complicaciones_operatorio = document.querySelector('#consultasubsecuente-complicacionesoperatorio')?.value.trim() || '';
            data.datos_cirugia = document.querySelector('#consultasubsecuente-datoscirugia')?.value.trim() || '';

            // Extraer lateralidad
            data.lateralidad = document.querySelector('.list-cell__lateralidadProcedimiento select')?.selectedOptions[0]?.textContent.trim() || '';

            // Extraer fechas y horas
            data.fechaInicio = document.querySelector('#consultasubsecuente-fecha_inicio')?.value || '';
            data.horaInicio = document.querySelector('#consultasubsecuente-horainicio')?.value || '';
            data.fechaFin = document.querySelector('#consultasubsecuente-fecha_fin')?.value || '';
            data.horaFin = document.querySelector('#consultasubsecuente-horafin')?.value || '';

            // Extraer tipo de anestesia
            data.tipoAnestesia = document.querySelector('#consultasubsecuente-anestesia_id')?.selectedOptions[0]?.textContent || '';

            // Recopilar datos del protocolo
            document.querySelectorAll('.multiple-input-list__item').forEach((item) => {
                const funcion = item.querySelector('.list-cell__funcion select')?.selectedOptions[0]?.textContent.trim() || '';
                const doctor = item.querySelector('.list-cell__doctor select')?.selectedOptions[0]?.textContent.trim() || '';

                // Asignar los valores con los nombres correctos según lo esperado en el backend
                if (funcion && doctor) {
                    switch (funcion.toLowerCase()) {
                        case 'cirujano 1':
                            data.cirujano_1 = doctor;
                            break;
                        case 'cirujano 2':
                            data.cirujano_2 = doctor;
                            break;
                        case 'instrumentista':
                            data.instrumentista = doctor;
                            break;
                        case 'circulante':
                            data.circulante = doctor;
                            break;
                        case 'anestesiologo':
                            data.anestesiologo = doctor;
                            break;
                        case 'ayudante anestesiologo':
                            data.ayudante_anestesiologo = doctor;
                            break;
                        case 'primer ayudante':
                            data.primer_ayudante = doctor;
                            break;
                        case 'segundo ayudante':
                            data.segundo_ayudante = doctor;
                            break;
                        case 'tercer ayudante':
                            data.tercer_ayudante = doctor;
                            break;
                        default:
                            data.otros = doctor;
                    }
                }
            });

            // Extraer procedimientos
            data.procedimientos = [];
            document.querySelectorAll('.multiple-input-list__item').forEach((item) => {
                const procInterno = item.querySelector('.list-cell__procInterno select')?.selectedOptions[0]?.textContent.trim() || '';
                if (procInterno) data.procedimientos.push({procInterno});
            });

            // Extraer diagnósticos
            data.diagnosticos = [];
            document.querySelectorAll('.multiple-input-list__item').forEach((item) => {
                const idDiagnostico = item.querySelector('.list-cell__idDiagnostico select')?.selectedOptions[0]?.textContent.trim() || '';
                const evidencia = item.querySelector('.list-cell__evidencia .cbx-icon')?.textContent.trim() || '';
                const ojo = item.querySelector('.list-cell__ojo_id select')?.selectedOptions[0]?.textContent.trim() || '';
                const observaciones = item.querySelector('.list-cell__observaciones textarea')?.value.trim() || '';

                if (idDiagnostico) {
                    data.diagnosticos.push({idDiagnostico, evidencia, ojo, observaciones});
                }
            });

        } else {
            console.log('Formato de consulta u optometría detectado');

            // Extraer datos para una consulta normal
            const div = document.querySelector('.media-body.responsive');

            if (!div) {
                console.warn('No se encontró el div con los datos del paciente.');
                return;
            }

            // Datos básicos del paciente
            data.hcNumber = div.querySelector('p:nth-of-type(2)')?.textContent.replace('HC #:', '').trim() || '';
            data.fechaNacimiento = div.querySelector('p:nth-of-type(4)')?.textContent.replace('Fecha de Nacimiento:', '').trim() || '';
            data.sexo = div.querySelector('p:nth-of-type(6)')?.textContent.trim() || '';
            data.celular = div.querySelector('p:nth-of-type(7)')?.textContent.replace('Celular:', '').trim() || '';
            data.ciudad = div.querySelector('p:nth-of-type(8)')?.textContent.trim() || '';

            // Validación básica
            if (!data.hcNumber) {
                console.error('El número de HC es obligatorio.');
                return;
            }

            // Extraer el `idSolicitud` del URL actual
            const urlParams = new URLSearchParams(window.location.search);
            data.form_id = urlParams.get('idSolicitud') || 'N/A';
            data.fechaActual = new Date().toISOString().slice(0, 10);

            data.motivoConsulta = document.querySelector('#consultas-motivoconsulta')?.value.trim() || '';
            data.enfermedadActual = document.querySelector('#consultas-enfermedadactual')?.value.trim() || '';
            data.examenFisico = document.querySelector('#consultas-fisico-0-observacion')?.value.trim() || '';
            data.plan = document.querySelector('#docsolicitudprocedimientos-observacion_consulta')?.value.trim() || '';

            // Extraer diagnósticos de consulta normal
            data.diagnosticos = [];
            document.querySelectorAll('#diagnosticosconsultaexterna .multiple-input-list__item').forEach((item) => {
                const idDiagnostico = item.querySelector('.list-cell__idEnfermedades select')?.selectedOptions[0]?.textContent.trim() || '';
                const ojo = item.querySelector('.list-cell__ojo_id select')?.selectedOptions[0]?.textContent.trim() || '';
                const evidencia = item.querySelector('.list-cell__evidencia input[type="checkbox"]')?.checked ? '1' : '0';

                if (idDiagnostico) {
                    data.diagnosticos.push({idDiagnostico, ojo, evidencia});
                }
            });

            // Extraer los exámenes seleccionados
            data.examenes = [];
            document.querySelectorAll('.examendiv').forEach(examenDiv => {
                const examenCheckbox = examenDiv.querySelector('input[type="text"]');
                if (examenCheckbox && examenCheckbox.value === '1') { // Verificar si el examen está seleccionado
                    const examenNombreCompleto = examenDiv.querySelector('.cbx-label').textContent.trim();

                    // Descomponer el nombre de forma flexible
                    const examenPartes = examenNombreCompleto.split('-');
                    if (examenPartes.length >= 2) {
                        const examenCodigo = examenPartes[0].trim(); // Código del examen (número)
                        let examenNombre = examenPartes.slice(1).join('-').trim(); // Nombre del examen
                        let examenLateralidad = '';

                        // Verificar si el nombre contiene la lateralidad (OD, OI, AO)
                        if (examenNombre.includes('(OD)')) {
                            examenLateralidad = 'OD';
                            examenNombre = examenNombre.replace('(OD)', '').trim();
                        } else if (examenNombre.includes('(OI)')) {
                            examenLateralidad = 'OI';
                            examenNombre = examenNombre.replace('(OI)', '').trim();
                        } else if (examenNombre.includes('(AO)')) {
                            examenLateralidad = 'AO';
                            examenNombre = examenNombre.replace('(AO)', '').trim();
                        }

                        // Almacenar el examen con código, nombre y lateralidad
                        data.examenes.push({
                            codigo: examenCodigo, nombre: examenNombre, lateralidad: examenLateralidad
                        });
                    } else {
                        console.error(`No se pudo descomponer el nombre del examen: ${examenNombreCompleto}`);
                    }
                }
            });

            console.log('Exámenes recopilados:', data.examenes);

            console.log('Exámenes recopilados:', data.examenes);

            // Validación básica
            if (!data.hcNumber || !data.motivoConsulta) {
                console.error('El número de historia clínica o el motivo de consulta son obligatorios.');
                return;
            }

            data.fechaActual = new Date().toISOString().slice(0, 10);

            // Puedes agregar otros campos relevantes de la consulta normal aquí
        }

        // Enviar los datos al backend
        console.log('Datos a enviar:', data);
        fetch(url, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data),
        })
            .then((response) => response.json())
            .then((result) => {
                if (result.success) {
                    console.log('Datos guardados correctamente.');
                } else {
                    console.error('Error:', result.message);
                }
            })
            .catch((error) => {
                console.error('Error al enviar los datos:', error);
            });
    }

    // Comprobar si el botón "Guardar Toda la Consulta" existe antes de añadir el listener
    const botonGuardar = document.querySelector('#botonGuardar');
    if (botonGuardar) {
        botonGuardar.addEventListener('click', function (e) {
            e.preventDefault(); // Evita el envío tradicional del formulario
            extraerDatosYEnviar(); // Llama a la función para enviar los datos
        });
    }

// Inicializamos modalData con los campos del formulario principal y del modal
    let modalData = {
        sede: '',
        area: '',
        afiliacion: '',
        parentesco: '',
        hcNumber: '',
        tipoAfiliacion: '',
        numeroAprobacion: '',
        tipoPlan: '',
        fechaRegistro: '',
        fechaVigencia: '',
        codDerivacion: '',
        numSecuencialDerivacion: '',
        numHistoria: '',
        examenFisico: '',
        observacion: '',
        procedimientos: [], // Campo para almacenar los datos de las filas de la tabla
        diagnosticos: [], // Diagnósticos extraídos
        apellidos: '',
        nombres: '',
        lname: '',
        lname2: '',
        fname: '',
        mname: '',
        sexo: '',
        fechaNacimiento: '',
        estadoCivil: '',
        telefonoMovil: '',
        email: '',
        direccion: '',
        ocupacion: '',
        lugarTrabajo: '',
        ciudad: '',
        parroquia: '',
        nacionalidad: '',
        idProcedencia: '',
        idReferido: ''
    };

    let isSubmitting = false; // Flag to prevent multiple submissions

    function registrarCambiosEnCampos() {
        const fields = [
            {id: '#docsolicitudpaciente-sede_id', key: 'sede', type: 'select'},
            {id: '#docsolicitudpaciente-externa_hospitalizacion', key: 'area', type: 'select'},
            {id: '#docsolicitudpaciente-afiliacionid', key: 'afiliacion', type: 'select'},
            {id: '#docsolicitudpaciente-parentescoid', key: 'parentesco', type: 'select'},
            {id: '#numero-historia-clinica', key: 'hcNumber', type: 'input'},
            {id: '#docsolicitudpaciente-tipoafiliacion', key: 'tipoAfiliacion', type: 'select'},
            {id: '#docsolicitudpaciente-numeroaprobacion', key: 'numeroAprobacion', type: 'input'},
            {id: '#docsolicitudpaciente-tipoplan', key: 'tipoPlan', type: 'select'},
            {id: '#docsolicitudpaciente-fecha_registro', key: 'fechaRegistro', type: 'input'},
            {id: '#docsolicitudpaciente-fecha_vigencia', key: 'fechaVigencia', type: 'input'},
            {id: '#docsolicitudpaciente-cod_derivacion', key: 'codDerivacion', type: 'input'},
            {id: '#docsolicitudpaciente-num_secuencial_derivacion', key: 'numSecuencialDerivacion', type: 'input'},
            {id: '#docsolicitudpaciente-num_historia', key: 'numHistoria', type: 'input'},
            {id: '#docsolicitudpaciente-examenfisico', key: 'examenFisico', type: 'input'},
            {id: '#docsolicitudpaciente-observacion', key: 'observacion', type: 'input'},
            {id: '#paciente-apellidos', key: 'apellidos', type: 'input'},
            {id: '#paciente-nombres', key: 'nombres', type: 'input'},
            {id: '#numero-historia-clinica', key: 'hcNumber', type: 'input'},
            {id: '#paciente-sexo', key: 'sexo', type: 'select'},
            {id: '#paciente-fecha_nac', key: 'fechaNacimiento', type: 'input'},
            {id: '#paciente-estado_civil_id', key: 'estadoCivil', type: 'select'},
            {id: '#paciente-celular', key: 'telefonoMovil', type: 'input'},
            {id: '#paciente-email', key: 'email', type: 'input'},
            {id: '#paciente-direccion', key: 'direccion', type: 'input'},
            {id: '#paciente-ocupacion', key: 'ocupacion', type: 'input'},
            {id: '#paciente-lugar_trabajo', key: 'lugarTrabajo', type: 'input'},
            {id: '#paciente-ciudad_id', key: 'ciudad', type: 'select'},
            {id: '#paciente-parroquia_id', key: 'parroquia', type: 'select'},
            {id: '#paciente-pais_id', key: 'nacionalidad', type: 'select'},
            {id: '#select2-paciente-id_procedencia-container', key: 'idProcedencia', type: 'selectText'},
            {id: '#select2-paciente-referido_id-container', key: 'idReferido', type: 'selectText'}
        ];

        fields.forEach(({id, key, type}) => {
            const field = document.querySelector(id);
            if (field) {
                if (type === 'select') {
                    modalData[key] = field.selectedOptions[0]?.textContent?.trim() || '';
                    field.addEventListener('change', () => {
                        modalData[key] = field.selectedOptions[0]?.textContent?.trim() || '';
                        //console.log(`Campo ${key} actualizado:`, modalData[key]);
                    });
                } else if (type === 'input') {
                    modalData[key] = field.value.trim() || '';
                    field.addEventListener('input', () => {
                        modalData[key] = field.value.trim() || '';
                        //console.log(`Campo ${key} actualizado:`, modalData[key]);
                    });
                }
            } else {
                console.info(`Campo ${key} no encontrado o no es obligatorio.`);
            }
        });
        // Procesar apellidos y nombres en campos separados
        modalData.lname = modalData.apellidos.split(' ')[0] || '';
        modalData.lname2 = modalData.apellidos.split(' ')[1] || '';
        modalData.fname = modalData.nombres.split(' ')[0] || '';
        modalData.mname = modalData.nombres.split(' ')[1] || '';

        console.log('Listeners para cambios en campos relevantes configurados.');
    }

    function extractTableRowData() {
        const rows = document.querySelectorAll('.multiple-input-list tbody tr'); // Selecciona todas las filas de la tabla
        const rowData = []; // Almacena los datos extraídos

        rows.forEach((row, index) => {
            const procedimiento = row.querySelector('.list-cell__Procedimiento .select2-selection__rendered')?.textContent.trim() || '';
            const procedimientoAfiliacion = row.querySelector('.list-cell__ProcedimientoAfiliacion .select2-selection__rendered')?.textContent.trim() || '';
            const ojoId = row.querySelector('.list-cell__ojo_id .select2-selection__rendered')?.textContent.trim() || '';
            const equipment = row.querySelector('.list-cell__equipment_id .select2-selection__rendered')?.textContent.trim() || '';
            const precio = row.querySelector('.list-cell__precio input')?.value.trim() || '';

            // Agrega la fila solo si contiene datos válidos
            if (procedimiento || procedimientoAfiliacion || equipment || precio) {
                rowData.push({
                    procedimiento,
                    procedimientoAfiliacion,
                    ojoId,
                    equipment,
                    precio,
                });
            }
        });

        if (rowData.length > 0) {
            modalData.procedimientos = rowData; // Solo actualiza si hay datos válidos
        } else {
            console.warn('No se encontraron datos válidos en la tabla.');
        }
        console.log('Datos extraídos de la tabla:', rowData);
    }

    function extractDiagnosticosData() {
        //console.group('Extracción de datos de diagnósticos');
        const rows = document.querySelectorAll('#diagnosticosconsultaexterna .multiple-input-list tbody tr');
        const diagnosticosData = [];

        rows.forEach((row, index) => {
            const diagnostico = row.querySelector('.list-cell__idEnfermedades .select2-selection__rendered')?.textContent.trim() || '';
            const ojoId = row.querySelector('.list-cell__ojo_id .select2-selection__rendered')?.textContent.trim() || '';
            const evidenciaCheckbox = row.querySelector('.list-cell__evidencia input[type="checkbox"]');
            const evidencia = evidenciaCheckbox ? evidenciaCheckbox.checked : false;

            // Agregar solo si hay información válida
            if ((diagnostico || ojoId || evidencia) && diagnostico !== 'SELECCIONE') {
                console.log(`Fila ${index}:`, {diagnostico, ojoId, evidencia});

                diagnosticosData.push({
                    diagnostico,
                    ojoId,
                    evidencia,
                });
            }
        });

        if (diagnosticosData.length > 0) {
            modalData.diagnosticos = diagnosticosData; // Solo actualiza si hay datos válidos
        } else {
            console.warn('No se encontraron datos válidos en la tabla.');
        }
        console.log('Datos extraídos de la tabla:', diagnosticosData);
    }

    function attachSaveButtonListener() {
        const modal = document.getElementById('ajaxCrudModal');
        if (modal) {
            const form = modal.querySelector('form');
            if (form && form.id === 'formprodoc1') {
                const botonGuardar = modal.querySelector('.modal-footer .btn-success[type="submit"]');
                if (botonGuardar) {
                    // Elimina cualquier listener previo antes de asignar uno nuevo
                    botonGuardar.removeEventListener('click', handleSaveButtonClick);
                    botonGuardar.addEventListener('click', handleSaveButtonClick);
                    console.log('Evento asignado al botón guardar.');
                }
            } else {
                //console.log('Formulario no relevante, no se asignará evento al botón guardar.');
            }
        } else {
            console.error('El modal especificado no contiene el botón guardar.');
        }
    }

    function handleSaveButtonClick(event) {
        event.preventDefault(); // Evita el envío predeterminado del formulario
        extractTableRowData();
        extractDiagnosticosData();
        extraerDatosYEnviarDesdeModal();
    }

    function extraerDatosYEnviarDesdeModal() {
        if (isSubmitting) return; // Prevent multiple submissions
        isSubmitting = true;
        // Extraer datos de la tabla antes de enviar
        extractTableRowData();
        extractDiagnosticosData();

        // Verificar y mostrar en consola el estado de los campos
        Object.entries(modalData).forEach(([key, value]) => {
            if (value) {
                console.log(`${key} detectado:`, value);
            } else {
                console.info(`${key} está vacío pero puede ser opcional.`);
            }
        });

        // Mostrar los datos en la consola para verificación
        console.log('Datos extraídos del modal:', modalData);

        // URL de la API para enviar los datos
        const url = 'https://cive.consulmed.me/interface/formulario_datos_modal.php';

        // Enviar los datos al backend usando fetch
        fetch(url, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(modalData),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((result) => {
                if (result.success) {
                    console.log('Datos enviados correctamente desde el modal.');
                } else {
                    console.error('Error en la API:', result.message);
                }
            })
            .catch((error) => {
                console.error('Error al enviar los datos desde el modal:', error);
            })
            .finally(() => {
                isSubmitting = false; // Reset flag after operation completes
            });
    }

// Configurar el MutationObserver para capturar campos dinámicos en el modal
    const modal = document.getElementById('ajaxCrudModal');
    if (modal) {
        const observer = new MutationObserver((mutationsList) => {
            for (const mutation of mutationsList) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    attachSaveButtonListener(); // Attach save button listener when modal is updated
                    registrarCambiosEnCampos(); // Track changes in fields
                    extractTableRowData();
                    extractDiagnosticosData();
                    break; // Avoid processing further mutations in this cycle
                }
            }
        });

        observer.observe(modal, {
            childList: true, subtree: true
        });

        console.log('Observando el modal para cambios y eventos de guardado...');
    } else {
        console.log('Modal no encontrado.');
    }


    // Función principal para añadir el evento de clic al botón de guardar
    function agregarEventoGuardar() {
        const btnGuardar = document.getElementById('interconsulta-btn-guardar');

        if (btnGuardar) {
            // Prevenir duplicados de eventos
            if (!btnGuardar.classList.contains('evento-agregado')) {
                btnGuardar.classList.add('evento-agregado');
                btnGuardar.addEventListener('click', function (e) {
                    e.preventDefault(); // Prevenir envío automático
                    console.log('Botón clicado, iniciando extracción de datos...'); // Confirmación en la consola
                    extraerDatosSolicitudYEnviar(btnGuardar); // Llamada a la función de extracción y envío
                });
            }
        } //else {
        //console.log('Botón interconsulta-btn-guardar no encontrado en la página.');
        //}
    }

// Observador para detectar cambios en el DOM (por si el botón se carga dinámicamente)
    const observador = new MutationObserver(agregarEventoGuardar);
    observador.observe(document.body, {childList: true, subtree: true});

// Ejecutar la función al cargar la página por primera vez
    document.addEventListener('DOMContentLoaded', agregarEventoGuardar);

// Función de extracción y envío de datos
    function extraerDatosSolicitudYEnviar(btnGuardar) {
        const url = 'https://cive.consulmed.me/interface/solicitud_procedimiento.php';
        const data = {};

        const div = document.querySelector('.media-body.responsive');
        if (!div) {
            console.warn('Div de datos del paciente no encontrado.');
            return;
        }

        // Datos básicos del paciente
        data.hcNumber = div.querySelector('p:nth-of-type(2)')?.textContent.replace('HC #:', '').trim() || '';
        data.form_id = new URLSearchParams(window.location.search).get('idSolicitud') || 'N/A';

        if (!data.hcNumber || !data.form_id) {
            console.error('Número de HC o form_id faltante.');
            return;
        }

        // Extracción de datos de solicitudes
        data.solicitudes = [];
        document.querySelectorAll('#interconsultas .multiple-input-list__item').forEach((item, index) => {
            const tipo = item.querySelector('.list-cell__tipo .cbx-icon')?.textContent.trim() || '';
            const afiliacion = item.querySelector('.list-cell__afiliacion_id select')?.selectedOptions[0]?.textContent.trim() || '';
            const procedimiento = item.querySelector('.list-cell__procedimiento_id select')?.selectedOptions[0]?.textContent.trim() || '';
            const doctor = item.querySelector('.list-cell__doctor select')?.selectedOptions[0]?.textContent.trim() || '';
            const fecha = item.querySelector('.list-cell__fecha input')?.value.trim() || '';
            const duracion = item.querySelector('.list-cell__duracion input')?.value.trim() || '';
            const ojo = Array.from(item.querySelectorAll('.list-cell__ojo_id input[type="checkbox"]')).filter(checkbox => checkbox.checked).map(checkbox => checkbox.nextSibling.textContent.trim()).join(', ') || '';
            const prioridad = item.querySelector('.list-cell__prioridad .cbx-icon')?.textContent.trim() || 'NO';
            const producto = item.querySelector('.list-cell__producto_id select')?.selectedOptions[0]?.textContent.trim() || '';
            const observacion = item.querySelector('.list-cell__observacionInterconsulta textarea')?.value.trim() || '';

            data.solicitudes.push({
                secuencia: index + 1,
                tipo,
                afiliacion,
                procedimiento,
                doctor,
                fecha,
                duracion,
                ojo,
                prioridad,
                producto,
                observacion
            });
        });

        if (!data.solicitudes.length) {
            console.error('No se encontraron solicitudes.');
            return;
        }

        // Desactivar el botón mientras se envían los datos
        if (btnGuardar) btnGuardar.disabled = true;

        console.log('Datos a enviar:', data); // Verificar los datos antes de enviar
        fetch(url, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data),
        })
            .then((response) => response.json())
            .then((result) => {
                if (result.success) {
                    console.log('Datos guardados correctamente.');
                } else {
                    console.error('Error:', result.message);
                }
            })
            .catch((error) => {
                console.error('Error al enviar los datos:', error);
            })
            .finally(() => {
                if (btnGuardar) btnGuardar.disabled = false; // Reactivar el botón después del envío
            });
    }

    const estilo = document.createElement('style');
    estilo.innerHTML = `
    .llegado-particular {
        background-color: #FFD700 !important; /* Color para menos de 30 min */
    }

    .espera-prolongada-particular {
        background-color: #FF6347 !important; /* Color para más de 30 min */
    }
`;
    document.head.appendChild(estilo);

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

// Observador de cambios en la tabla y en el paginador
    function observarCambiosEnTablaYPaginacion() {
        const contenedorTabla = document.querySelector('.kv-grid-container'); // Contenedor de la tabla con paginación
        if (!contenedorTabla) return;

        // Observar cambios en el contenido de la tabla
        const observer = new MutationObserver(() => {
            actualizarColorFilasPorTiempoYAfiliacion();
        });

        // Observar cambios en el contenedor de la tabla
        observer.observe(contenedorTabla, {childList: true, subtree: true});
    }

// Esperar a que la tabla esté presente en el DOM y luego aplicar el MutationObserver
    const intervalo = setInterval(() => {
        const contenedorTabla = document.querySelector('.kv-grid-container');
        if (contenedorTabla) {
            clearInterval(intervalo); // Detener el intervalo una vez que encontramos la tabla
            observarCambiosEnTablaYPaginacion(); // Activar el observador de cambios
            actualizarColorFilasPorTiempoYAfiliacion(); // Aplicar el color inicial
        }
    }, 250); // Comprobamos cada 500 ms

    // Añadir el evento click al botón Exámenes después de cargar el popup
    document.getElementById('btnExamenes').addEventListener('click', () => {
        console.log('Botón Exámenes clickeado');
        mostrarSeccion('examenes');
        cargarExamenes();
    });

    document.getElementById('btnProtocolos').addEventListener('click', () => {
        console.log('Botón Protocolos clickeado');
        mostrarSeccion('protocolos');
        cargarProtocolos();
    });

    document.getElementById('btnRecetas').addEventListener('click', () => {
        console.log('Botón Recetas clickeado');
        mostrarSeccion('recetas');
        cargarRecetas();
    });

    document.getElementById('btnConsulta').addEventListener('click', () => {
        console.log('Botón Consulta clickeado');
        mostrarSeccion('consulta');
    });

    document.getElementById('btnConsultaAnterior').addEventListener('click', () => {
        console.log('Botón Consulta Anterior clickeado');
        chrome.runtime.sendMessage({action: "consultaAnterior"});
    });

    document.getElementById('btnPOP').addEventListener('click', () => {
        console.log('Botón POP clickeado');
        chrome.runtime.sendMessage({action: "ejecutarPopEnPagina"});
    });

    document.getElementById('btnBackExamenes').addEventListener('click', () => {
        console.log('Botón Back Exámenes clickeado');
        mostrarSeccion('inicio');
    });

    document.getElementById('btnBackProtocolos').addEventListener('click', () => {
        console.log('Botón Back Protocolos clickeado');
        mostrarSeccion('inicio');
    });

    document.getElementById('btnBackRecetas').addEventListener('click', () => {
        console.log('Botón Back Recetas clickeado');
        mostrarSeccion('inicio');
    });

    document.getElementById('btnBackProcedimientos').addEventListener('click', () => {
        console.log('Botón Back Procedimientos clickeado');
        mostrarSeccion('protocolos');
    });

    document.getElementById('btnBackConsulta').addEventListener('click', () => {
        console.log('Botón Back Consulta clickeado');
        mostrarSeccion('inicio');
    });

    document.getElementById('btnGeneratePDF').addEventListener('click', () => {
        // Enviar un mensaje al background.js para verificar la suscripción
        chrome.runtime.sendMessage({action: 'checkSubscription'}, (response) => {
            if (response.success) {
                // Si la verificación es exitosa, continúa con la generación del PDF
                generatePDF();
            } else {
                // Redirigir al usuario a la página de inicio de sesión en una nueva pestaña si no tiene acceso
                window.open('https://cive.consulmed.me/login.html', '_blank');
            }
        });
    });
})();
