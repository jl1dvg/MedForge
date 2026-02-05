function ejecutarProtocoloEnPagina(item) {
    // Candado para evitar ejecuciones múltiples
    if (window._cive_protocolo_en_ejecucion) {
        console.warn('[CIVE EXT] Ya se está ejecutando el protocolo. Ignorando llamada duplicada.');
        return;
    }
    window._cive_protocolo_en_ejecucion = true;
    const SELECTORS = {
        membrete: '#consultasubsecuente-membrete',
        piepagina: '#consultasubsecuente-piepagina',
        dieresis: '#consultasubsecuente-dieresis',
        exposicion: '#consultasubsecuente-exposicion',
        hallazgo: '#consultasubsecuente-hallazgo',
        operatorio: '#consultasubsecuente-operatorio',
        hallazgopostquirurgico: '#consultasubsecuente-hallazgopostquirurgico',
        drenajes: '#consultasubsecuente-drenajes',
        sangrado: '#consultasubsecuente-sangrado',
        complicaciones: '#consultasubsecuente-complicaciones',
        complicacionesoperatorio: '#consultasubsecuente-complicacionesoperatorio',
        perdidasanguineat: '#consultasubsecuente-perdidasanguineat',
        datosCirugia: '#consultasubsecuente-datoscirugia',
        anestesia: '#select2-consultasubsecuente-anestesia_id-container',
        nombreAnestesia: '#consultasubsecuente-nombreanestesia',
        observaciones: '#docsolicitudprocedimientos-observacion_consulta'
    };
    const LOG_PREFIX = '[CIVE EXT]';
    const MODO_DEBUG_VISUAL = true; // Cambiar a false para desactivar visualización
    const MODO_LOG_COMPACTO = true;
    const SELECT2_RESULT_TIMEOUT_MS = 12000;
    const SELECT2_INTERACTION_PAUSE_MS = 250;
    const SELECT2_RESULTS_SELECTOR = '.select2-container--open .select2-results__option';

    function validarItem(item) {
        if (!item || typeof item !== 'object') {
            throw new Error('El item no está definido o no es un objeto.');
        }

        const camposObligatorios = ['id', 'membrete', 'dieresis', 'exposicion', 'hallazgo', 'operatorio'];
        const faltantes = camposObligatorios.filter(campo => !item[campo]);

        if (faltantes.length > 0) {
            throw new Error(`Faltan los siguientes campos obligatorios en item: ${faltantes.join(', ')}`);
        }

        if (!Array.isArray(item.codigos) || item.codigos.length === 0) {
            throw new Error('El array de códigos está vacío o no es válido.');
        }

        if (!Array.isArray(item.diagnosticos)) {
            console.warn(`${LOG_PREFIX} Diagnósticos no es un arreglo válido, se continuará pero puede faltar información.`);
        }

        if (typeof item.anestesia !== 'string') {
            throw new Error('El campo "anestesia" debe estar presente y ser una cadena.');
        }
    }

    function liberarCandado() {
        window._cive_protocolo_en_ejecucion = false;
    }

    function dispatchInputChange(element) {
        if (!element) return;
        element.dispatchEvent(new Event('input', {bubbles: true, cancelable: true}));
        element.dispatchEvent(new Event('change', {bubbles: true, cancelable: true}));
    }

// Verificar que el item tenga la estructura esperada
    try {
        validarItem(item);
    } catch (error) {
        console.error(`${LOG_PREFIX} Error en la validación del item:`, error.message);
        Swal.fire({
            icon: 'error',
            title: 'Error en los datos',
            text: error.message,
            confirmButtonText: 'Entendido'
        });
        liberarCandado();
        return;
    }

    console.log(`${LOG_PREFIX} Item recibido en ejecutarProtocoloEnPagina:`, item);

    if (!item || typeof item !== 'object' || !item.codigos || !Array.isArray(item.codigos)) {
        console.error(`${LOG_PREFIX} El item recibido no tiene la estructura esperada.`, item);
        liberarCandado();
        return;
    }

    // Asegúrate de que el DOM esté listo antes de hacer cualquier operación
    function llenarCampoTexto(selector, valor) {
        return new Promise((resolve, reject) => {
            const textArea = document.querySelector(selector);
            if (textArea) {
                const campo = selector.replace('#consultasubsecuente-', '').replace('#', '');
                if (MODO_LOG_COMPACTO) {
                    console.log(`${LOG_PREFIX} [${campo}] = "${valor}"`);
                } else {
                    console.log(`${LOG_PREFIX} Llenando el campo de texto "${selector}" con "${valor}"`);
                }
                textArea.value = valor;
                dispatchInputChange(textArea);
                resaltarElemento(textArea, 'blue');
                // Si el campo es el pie de página, hacerlo de solo lectura
                if (selector === SELECTORS.piepagina) {
                    textArea.readOnly = true;
                }
                // Refuerzo: establecer readOnly para piepagina siempre después de llenar
                if (selector === SELECTORS.piepagina) {
                    setTimeout(() => {
                        textArea.readOnly = true;
                    }, 120);
                }
                setTimeout(() => {
                    // Como refuerzo general, podríamos establecer readOnly en todos los campos aquí si se quisiera.
                    resolve();
                }, 100); // Añadir un retraso para asegurar que el valor se establezca
            } else {
                console.error(`${LOG_PREFIX} El campo de texto "${selector}" no se encontró.`);
                reject(`El campo de texto "${selector}" no se encontró.`);
            }
        });
    }

    function resaltarElemento(elemento, color = 'orange') {
        if (!MODO_DEBUG_VISUAL || !elemento) return;

        const bordeOriginal = elemento.style.outline;
        elemento.style.outline = `2px solid ${color}`;
        elemento.scrollIntoView({behavior: 'smooth', block: 'center'});

        setTimeout(() => {
            elemento.style.outline = bordeOriginal;
        }, 1000);
    }

    function delay(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    // Nueva función retry para reintentos
    function retry(fn, maxRetries = 5, delay = 300) {
        return new Promise((resolve, reject) => {
            let attempt = 0;
            const tryExecute = () => {
                fn()
                    .then(resolve)
                    .catch((error) => {
                        attempt++;
                        if (attempt < maxRetries) {
                            console.warn(`${LOG_PREFIX} Reintentando (${attempt}/${maxRetries})...`);
                            setTimeout(tryExecute, delay * Math.pow(2, attempt));
                        } else {
                            reject(error);
                        }
                    });
            };
            tryExecute();
        });
    }

    function buscarYSeleccionar(selector, valor) {
        return retry(() => hacerClickEnSelect2(selector), 3, 300)
            .then(() => establecerBusqueda(selector, valor))
            .then(() => seleccionarOpcion())
            .then(() => delay(SELECT2_INTERACTION_PAUSE_MS));
    }

    function abrirYBuscarSelect2(selector, valor) {
        return retry(() => hacerClickEnSelect2(selector), 3, 300)
            .then(() => establecerBusqueda(selector, valor));
    }

    function esperarResultadosSelect2(timeoutMs = SELECT2_RESULT_TIMEOUT_MS) {
        return new Promise((resolve, reject) => {
            const start = Date.now();
            const poll = () => {
                const resultados = Array.from(document.querySelectorAll(SELECT2_RESULTS_SELECTOR))
                    .filter((option) => {
                        const text = option.textContent ? option.textContent.trim() : '';
                        if (!text) return false;
                        if (option.getAttribute('aria-disabled') === 'true') return false;
                        if (text.toLowerCase().includes('loading')) return false;
                        if (text.toLowerCase().includes('no results')) return false;
                        return true;
                    });
                if (resultados.length > 0) {
                    resolve(resultados);
                    return;
                }
                if (Date.now() - start >= timeoutMs) {
                    reject(new Error('No se encontraron resultados de Select2.'));
                    return;
                }
                setTimeout(poll, 250);
            };
            poll();
        });
    }

    function hacerClickEnBoton(selector, veces) {
        return new Promise((resolve, reject) => {
            const clickear = (i) => {
                if (i >= veces) {
                    resolve();
                    return;
                }

                const boton = document.querySelector(selector);
                if (boton) {
                    console.log(`Haciendo clic en el botón "${selector}" (${i + 1}/${veces})`);
                    boton.click();
                    setTimeout(() => clickear(i + 1), 500);
                } else {
                    console.error(`No se encontró el botón: ${selector}`);
                    reject(`No se encontró el botón: ${selector}`);
                }
            };

            clickear(0);
        });
    }

    function hacerClickEnSelect2(selector) {
        return new Promise((resolve, reject) => {
            const tecnicoContainer = document.querySelector(selector);
            if (tecnicoContainer) {
                console.log(`${LOG_PREFIX} Haciendo clic en el contenedor: ${selector}`);
                resaltarElemento(tecnicoContainer, 'purple');
                const event = new MouseEvent('mousedown', {
                    view: window, bubbles: true, cancelable: true
                });
                tecnicoContainer.dispatchEvent(event);
                setTimeout(resolve, 100); // Añadir un retraso para asegurar que el menú se despliegue
            } else {
                console.error(`${LOG_PREFIX} El contenedor "${selector}" no se encontró.`);
                reject(`El contenedor "${selector}" no se encontró.`);
            }
        });
    }

    function establecerBusqueda(selector, valor) {
        return new Promise((resolve, reject) => {
            let attempts = 0;
            const maxAttempts = 20;

            const searchForField = () => {
                const searchField = document.querySelector('input.select2-search__field');
                if (!searchField) {
                    console.log(`${LOG_PREFIX} Intento ${attempts + 1}: no se encontró el campo de búsqueda. Reintentando...`);
                    attempts++;
                    if (attempts < maxAttempts) {
                        hacerClickEnSelect2(selector).then(() => setTimeout(searchForField, 500)).catch(error => reject(error));
                    } else {
                        console.error(`${LOG_PREFIX} El campo de búsqueda del Select2 no se encontró.`);
                        reject('El campo de búsqueda del Select2 no se encontró.');
                    }
                } else {
                    console.log(`${LOG_PREFIX} Estableciendo búsqueda:`, valor);
                    searchField.value = '';
                    searchField.dispatchEvent(new Event('input', {bubbles: true, cancelable: true}));
                    searchField.value = valor;
                    const inputEvent = new Event('input', {bubbles: true, cancelable: true});
                    searchField.dispatchEvent(inputEvent);
                    esperarResultadosSelect2()
                        .then(() => resolve(searchField))
                        .catch(reject);
                }
            };

            searchForField();
        });
    }

    function normalizarTexto(texto) {
        return (texto || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function seleccionarOpcion(valorEsperado = '') {
        return new Promise((resolve, reject) => {
            // Verificar que el selector existe y es accesible antes de intentar realizar operaciones
            if (document.querySelector('input.select2-search__field') === null) {
                console.error(`${LOG_PREFIX} El campo de búsqueda select2-search__field no existe en el DOM.`);
                reject('El campo de búsqueda select2-search__field no existe en el DOM.');
                return;
            }
            const searchField = document.querySelector('input.select2-search__field');
            if (!searchField) {
                console.error(`${LOG_PREFIX} El campo de búsqueda del Select2 no se encontró para seleccionar la opción.`);
                reject('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
                return;
            }

            esperarResultadosSelect2()
                .then((resultados) => {
                    console.log(`${LOG_PREFIX} Seleccionando opción`);
                    resaltarElemento(searchField, 'red');
                    const valorNormalizado = normalizarTexto(valorEsperado);
                    const highlighted = document.querySelector('.select2-container--open .select2-results__option--highlighted');
                    const coincidenciaExacta = valorNormalizado
                        ? resultados.find((opcion) => normalizarTexto(opcion.textContent) === valorNormalizado)
                        : null;
                    const coincidenciaParcial = !coincidenciaExacta && valorNormalizado
                        ? resultados.find((opcion) => normalizarTexto(opcion.textContent).includes(valorNormalizado))
                        : null;

                    const opcionSeleccionada = coincidenciaExacta || coincidenciaParcial || highlighted || resultados[0];
                    if (opcionSeleccionada) {
                        opcionSeleccionada.dispatchEvent(new MouseEvent('mouseup', {bubbles: true, cancelable: true}));
                        setTimeout(resolve, 250);
                        return;
                    }

                    const enterEvent = new KeyboardEvent('keydown', {
                        key: 'Enter', keyCode: 13, bubbles: true, cancelable: true
                    });
                    searchField.dispatchEvent(enterEvent);
                    setTimeout(resolve, 250);
                })
                .catch((error) => {
                    console.error(`${LOG_PREFIX} No se pudieron cargar resultados de Select2:`, error);
                    reject(error);
                });
        });
    }

    function seleccionarRadioNo() {
        return new Promise((resolve, reject) => {
            const radioNo = document.querySelector('input[name="ConsultaSubsecuente[examenHistopatologico]"][value="2"]');
            if (radioNo) {
                console.log('Seleccionando el radio botón "NO"');
                radioNo.checked = true;
                dispatchInputChange(radioNo);
                resolve();
            } else {
                console.error('El radio botón "NO" no se encontró.');
                reject('El radio botón "NO" no se encontró.');
            }
        });
    }

    function obtenerFechaActual() {
        const fecha = new Date();
        const dia = String(fecha.getDate()).padStart(2, '0');
        const mes = String(fecha.getMonth() + 1).padStart(2, '0'); // Los meses son de 0 a 11
        const year = fecha.getFullYear();
        return `${dia}/${mes}/${year}`;
    }

    function hacerClickEnPresuntivo(selector, numeroDeClicks = 1) {
        return new Promise((resolve, reject) => {
            const botonPresuntivo = document.querySelector(selector);

            if (botonPresuntivo) {
                console.log(`Haciendo clic en el checkbox "PRESUNTIVO" ${numeroDeClicks} veces`);
                let contador = 0;
                const intervalo = setInterval(() => {
                    botonPresuntivo.click();
                    contador++;
                    if (contador >= numeroDeClicks) {
                        clearInterval(intervalo);
                        resolve();
                    }
                }, 100); // Intervalo entre clics, ajustable según necesidad
            } else {
                console.error('El checkbox "PRESUNTIVO" no se encontró.');
                reject('El checkbox "PRESUNTIVO" no se encontró.');
            }
        });
    }

    const ejecutarAcciones = (item) => {
        if (!item || !item.membrete || !item.dieresis) {
            console.error('El objeto item o alguna de sus propiedades necesarias están indefinidos:', item);
            return Promise.reject('Item inválido');
        }
        // Llenar campos de texto
        return Promise.all([
            llenarCampoTexto(SELECTORS.membrete, `${item.membrete} en ${ojoATratar.descripcion}`),
            llenarCampoTexto(SELECTORS.piepagina, `${item.id}`).then(() => {
                // Refuerzo: asegúrate de que el campo quede como solo lectura tras llenarlo
                const piepaginaField = document.querySelector(SELECTORS.piepagina);
                if (piepaginaField) piepaginaField.readOnly = true;
            }),
            llenarCampoTexto(SELECTORS.dieresis, item.dieresis),
            llenarCampoTexto(SELECTORS.exposicion, item.exposicion),
            llenarCampoTexto(SELECTORS.hallazgo, item.hallazgo),
            llenarCampoTexto(SELECTORS.operatorio, item.operatorio),
            llenarCampoTexto(SELECTORS.hallazgopostquirurgico, 'Paciente orientado en las tres esferas y presenta un parche oclusivo en el ojo operado, conforme a las indicaciones postoperatorias. Constantes vitales dentro de los parámetros normales. No se observan complicaciones inmediatas.'),
            llenarCampoTexto(SELECTORS.drenajes, 'No'),
            llenarCampoTexto(SELECTORS.sangrado, 'No'),
            llenarCampoTexto(SELECTORS.complicaciones, 'No'),
            llenarCampoTexto('#consultasubsecuente-heridas', 'No'),
            llenarCampoTexto(SELECTORS.complicacionesoperatorio, item.complicacionesoperatorio),
            llenarCampoTexto(SELECTORS.perdidasanguineat, item.perdidasanguineat),
            hacerClickEnBoton('#trabajadorprotocolo-input-subsecuente .multiple-input-list__item .js-input-plus', item.staffCount),
            (() => {
                const yaCreados = Array.from(
                    document.querySelectorAll('#procedimientoprotocolo-input-subsecuente .multiple-input-list__item')
                ).filter(row => {
                    const select = row.querySelector('select[id^="consultasubsecuente-procedimientoprotocolo-"][id$="-procinterno"]');
                    return select && select.value && select.value !== '';
                }).length;

                const necesarios = Array.isArray(item.codigos) ? item.codigos.length : 0;
                const faltantes = Math.max(necesarios - yaCreados, 0);

                // 🪵 Log de depuración
                console.log(`🧮 Procedimientos: necesarios=${necesarios}, yaCreados=${yaCreados}, faltantes=${faltantes}`);

                return hacerClickEnBoton(
                    '#procedimientoprotocolo-input-subsecuente .multiple-input-list__item .js-input-plus',
                    faltantes
                );
            })(),
            hacerClickEnBoton('#diagnosticossub11111 .list-cell__button .js-input-plus', Array.isArray(item.diagnosticos) ? item.diagnosticos.length - 1 : 0)
        ]);
    };

    function obtenerOjoATratar() {
        // Primero, intentar leer del select2 de lateralidad (campo de la UI)
        const lateralidadSpan = document.querySelector('#select2-consultasubsecuente-procedimientoprotocolo-0-lateralidadprocedimiento-container');
        if (lateralidadSpan) {
            const texto = lateralidadSpan.getAttribute('title');
            if (texto === 'DERECHO') {
                return {sigla: 'DERECHO', descripcion: 'OJO DERECHO'};
            } else if (texto === 'IZQUIERDO') {
                return {sigla: 'IZQUIERDO', descripcion: 'OJO IZQUIERDO'};
            } else if (texto === 'AMBOS OJOS') {
                return {sigla: 'AMBOS OJOS', descripcion: 'AMBOS OJOS'};
            }
        }

        // Si no se encuentra en el select2, buscar en las notas del doctor primero
        function buscarOjoEnTexto(texto) {
            const textoUpper = texto.toUpperCase();
            if (textoUpper.includes("OJO DERECHO")) {
                return {sigla: "DERECHO", descripcion: "OJO DERECHO"};
            } else if (textoUpper.includes("OJO IZQUIERDO")) {
                return {sigla: "IZQUIERDO", descripcion: "OJO IZQUIERDO"};
            } else if (textoUpper.includes("AMBOS OJOS")) {
                return {sigla: "AMBOS OJOS", descripcion: "AMBOS OJOS"};
            } else {
                return null; // Retorna null si no encuentra nada
            }
        }

        const notasDoctorElement = document.querySelector("div[style*='text-align:justify;'] div[style*='margin-right:30px;margin-left:30px;']");
        if (notasDoctorElement) {
            const resultado = buscarOjoEnTexto(notasDoctorElement.textContent);
            if (resultado) {
                return resultado;
            }
        }

        // Si no se encuentra en las notas del doctor, buscar en el pedido de cirugía
        const liElement = Array.from(document.querySelectorAll('li')).find(li => li.textContent.includes("PEDIDO DE CIRUGÍA"));
        if (liElement) {
            const seccionQuirurgica = Array.from(liElement.querySelectorAll('b')).find(b => b.textContent.includes("SECCIÓN:"));
            if (seccionQuirurgica) {
                const resultado = buscarOjoEnTexto(seccionQuirurgica.nextSibling.textContent);
                if (resultado) {
                    return resultado;
                }
            }
        }

        // Si no se encuentra en ninguno de los dos lugares, retornar ojo no especificado
        return {sigla: "", descripcion: "Ojo no especificado"};
    }

// Ejecutar y mostrar el resultado
    const ojoATratar = obtenerOjoATratar();
    console.log(ojoATratar);

    // Función genérica para select2 con lateralidad
    function ejecutarSelect2ConLateralidad(lista, campoNombre = 'nombre') {
        if (!Array.isArray(lista)) return Promise.resolve();

        return lista.reduce((promise, item) => {
            return promise.then(() => {
                return buscarYSeleccionar(item.selector, item[campoNombre])
                    .then(() => item.definitivo !== undefined ? hacerClickEnPresuntivo(item.definitivo, 1) : Promise.resolve())
                    .then(() => buscarYSeleccionar(item.lateralidad, ojoATratar.sigla))
                    .then(() => delay(SELECT2_INTERACTION_PAUSE_MS))
                    .catch(error => console.error(`Error procesando item ${item[campoNombre]}:`, error));
            });
        }, Promise.resolve());
    }

    function ejecutarCodigos(item, ojo) {
        return ejecutarSelect2ConLateralidad(item.codigos);
    }

    function ejecutarDiagnosticos(item, ojo) {
        return ejecutarSelect2ConLateralidad(item.diagnosticos);
    }

    function capturarNombreUsuario() {
        const nombreUsuarioElement = document.querySelector('.dropdown.user.user-menu .hidden-xs');
        if (nombreUsuarioElement) {
            return nombreUsuarioElement.textContent.trim();
        } else {
            console.error(`${LOG_PREFIX} No se encontró el nombre del usuario.`);
            return 'Nombre no encontrado';
        }
    }

    function obtenerNombreMedicoSeleccionado(opcion = 'completo') {
        const nombreMedicoElement = document.getElementById('select2-consultasubsecuente-trabajadoragenda-container');
        if (nombreMedicoElement) {
            const nombreMedico = nombreMedicoElement.getAttribute('title');
            if (nombreMedico) {
                if (opcion === 'completo') {
                    return nombreMedico;
                } else if (opcion === 'apellidos') {
                    const palabras = nombreMedico.split(' ');
                    return palabras.slice(-2).join(' ');
                } else {
                    return 'Opción no válida';
                }
            } else {
                return 'Nombre no encontrado';
            }
        } else {
            console.error(`${LOG_PREFIX} No se encontró el nombre del médico.`);
            return 'Nombre no encontrado';
        }
    }

// Ejemplo de uso:
    const nombreCompleto = obtenerNombreMedicoSeleccionado('completo');
    console.log(`${LOG_PREFIX} Nombre completo del médico:`, nombreCompleto);

    const apellidosMedico = obtenerNombreMedicoSeleccionado('apellidos');
    console.log(`${LOG_PREFIX} Apellidos del médico:`, apellidosMedico);

    function esperarElemento(selector) {
        return new Promise((resolve, reject) => {
            const elemento = document.querySelector(selector);
            if (elemento) {
                resolve(elemento);
                return;
            }

            const observer = new MutationObserver((mutations, observerInstance) => {
                mutations.forEach((mutation) => {
                    const nodes = Array.from(mutation.addedNodes);
                    for (const node of nodes) {
                        if (node.matches && node.matches(selector)) {
                            observerInstance.disconnect();
                            resolve(node);
                            return;
                        }
                    }
                });
            });

            observer.observe(document.body, {
                childList: true, subtree: true
            });

            setTimeout(() => {
                observer.disconnect();
                reject(`El elemento "${selector}" no se encontró dentro del tiempo esperado.`);
            }, 10000); // Timeout de 10 segundos, ajusta según sea necesario
        });
    }

    function sumarHoras(hora, horasASumar) {
        const [horas, minutos] = hora.split(':').map(Number);
        if (isNaN(horas) || isNaN(minutos)) {
            return null; // Manejo de error si alguno de los valores no es un número
        }
        const fecha = new Date();
        fecha.setHours(horas, minutos, 0); // Establecer horas y minutos

        // Sumar las horas y minutos al objeto Date
        const horasEnteras = Math.floor(horasASumar);
        const minutosASumar = (horasASumar - horasEnteras) * 60;

        fecha.setHours(fecha.getHours() + horasEnteras);
        fecha.setMinutes(fecha.getMinutes() + minutosASumar);

        // Formatear la nueva hora en formato hh:mm
        return [String(fecha.getHours()).padStart(2, '0'), String(fecha.getMinutes()).padStart(2, '0')].join(':');
    }

    function actualizarHoraFin(item) {
        const campoHoraInicio = document.querySelector('#consultasubsecuente-horainicio');
        const campoHoraFin = document.querySelector('#consultasubsecuente-horafin');

        if (campoHoraInicio && campoHoraFin) {
            const horaInicio = campoHoraInicio.value;

            // Verificar que la hora de inicio tenga el formato correcto (hh:mm)
            if (!/^\d{2}:\d{2}$/.test(horaInicio)) {
                console.error(`${LOG_PREFIX} La hora de inicio no está en el formato correcto (hh:mm).`);
                return;
            }

            // Determinar cuántas horas sumar (2 por defecto si no se especifica en item)
            const horasASumar = item && item.horas !== undefined ? parseFloat(item.horas) : 2;

            // Depuración para verificar los valores
            console.log(`${LOG_PREFIX} Item recibido:`, item);
            console.log(`${LOG_PREFIX} Horas encontradas en item:`, item ? item.horas : 'No especificado (usando valor por defecto: 2)');

            // Calcular la nueva hora de fin
            const nuevaHoraFin = sumarHoras(horaInicio, horasASumar);

            if (nuevaHoraFin) {
                campoHoraFin.value = nuevaHoraFin; // Asignar la nueva hora de fin
                console.log(`${LOG_PREFIX} La nueva hora de fin es: ${nuevaHoraFin}`);
            } else {
                console.error(`${LOG_PREFIX} Error al sumar horas a la hora de inicio.`);
            }
        } else {
            console.error(`${LOG_PREFIX} No se encontraron los campos de hora de inicio o de fin.`);
        }
    }

    const nombreUsuario = capturarNombreUsuario();
    const textoDictado = `DICTADO POR: ${nombreCompleto}\nFECHA DE DICTADO: ${obtenerFechaActual()}\nESCRITO POR: ${nombreUsuario}`;
    const observaciones = `Se realiza ${item.membrete} en ${ojoATratar.descripcion} sin complicaciones.
Se dan indicaciones médicas, cuidado de la herida y actividades permitidas y restringidas.
Se prescribe medicación por vía oral
Se indica al paciente que debe acudir a una consulta de control en las próximas 24 horas`

    function ejecutarTecnicos(item, medicoSeleccionado) {
        if (!Array.isArray(item.tecnicos)) return Promise.resolve();

        return item.tecnicos.reduce((promise, tecnico) => {
            return promise.then(() => {
                return abrirYBuscarSelect2(tecnico.selector, tecnico.funcion)
                    .then(() => seleccionarOpcion())
                    .then(() => {
                        const valor = (tecnico.nombre === 'cirujano_principal')
                            ? medicoSeleccionado.apellidos
                            : tecnico.nombre;
                        return abrirYBuscarSelect2(tecnico.trabajador, valor);
                    })
                    .then(() => {
                        const valorEsperado = (tecnico.nombre === 'cirujano_principal')
                            ? medicoSeleccionado.nombreCompleto
                            : tecnico.nombre;
                        return seleccionarOpcion(valorEsperado);
                    })
                    .then(() => delay(SELECT2_INTERACTION_PAUSE_MS))
                    .catch(error => console.error(`Error procesando técnico ${tecnico.nombre}:`, error));
            });
        }, Promise.resolve()); // Inicializa con una promesa resuelta
    }


    function ejecutarFaseInicial(item, medicoSeleccionado) {
        return ejecutarAcciones(item)
            .then(() => ejecutarTecnicos(item, medicoSeleccionado))
            .then(() => ejecutarCodigos(item, ojoATratar.sigla))
            .then(() => llenarCampoTexto(SELECTORS.datosCirugia, textoDictado));
    }

    function ejecutarFaseFinal(item) {
        return seleccionarRadioNo()
            .then(() => actualizarHoraFin(item))
            .then(() => abrirYBuscarSelect2(SELECTORS.anestesia, item.anestesia))
            .then(() => seleccionarOpcion())
            .then(() => ejecutarDiagnosticos(item, ojoATratar.sigla))
            .then(() => hacerClickEnPresuntivo('.form-group.field-proyectada .cbx-container .cbx', 2))
            .then(() => hacerClickEnPresuntivo('.form-group.field-termsChkbx .cbx-container .cbx', 1))
            .then(() => item.anestesia === 'OTROS'
                ? llenarCampoTexto(SELECTORS.nombreAnestesia, 'TOPICA')
                : Promise.resolve())
            .then(() => esperarElemento(SELECTORS.observaciones))
            .then(() => llenarCampoTexto(SELECTORS.observaciones, observaciones))
            .then(() => hacerClickEnBoton('#consultaActual', 1));
    }

    const medicoSeleccionado = {
        nombreCompleto: nombreCompleto !== 'Nombre no encontrado' ? nombreCompleto : apellidosMedico,
        apellidos: apellidosMedico,
    };

    ejecutarFaseInicial(item, medicoSeleccionado)
        .then(() => ejecutarFaseFinal(item))
        .then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Proceso completado',
                text: 'Todos los campos se han llenado correctamente.',
                confirmButtonText: 'Aceptar'
            });
        })
        .then(() => console.log(`${LOG_PREFIX} Clic simulado correctamente.`))
        .catch(error => console.error(`${LOG_PREFIX} Error en la ejecución de acciones:`, error))
        .finally(() => {
            liberarCandado();
        });
}

// Forzar campo piepagina como solo lectura al cargar la página
document.addEventListener('DOMContentLoaded', () => {
    const piepagina = document.querySelector('#consultasubsecuente-piepagina');
    if (piepagina) {
        piepagina.readOnly = true;
        console.log('[CIVE EXT] Campo piepagina marcado como solo lectura al cargar.');
    }
});
