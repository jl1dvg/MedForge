// Consulta anterior vía API (MedForge)
(function () {
    const EXAM_SELECTOR = '#consultas-fisico-0-observacion';
    const PLAN_SELECTOR = '#docsolicitudprocedimientos-observacion_consulta';
    const PREV_EXAM_BTN_ID = 'cive-prev-exam-btn';

    function getIdentifiers() {
        const params = new URLSearchParams(window.location.search);
        const formId = params.get('idSolicitud') || params.get('id') || params.get('form_id') || null;

        let hcNumber = null;
        let procedimiento = null;
        const hcInput = document.querySelector('#numero-historia-clinica');
        if (hcInput && hcInput.value) {
            hcNumber = hcInput.value.trim();
        } else {
            const hcFromMedia = document.querySelector('.media-body p:nth-of-type(2)');
            if (hcFromMedia) {
                const text = hcFromMedia.textContent || '';
                const parts = text.split('HC #:');
                if (parts[1]) {
                    hcNumber = parts[1].trim();
                }
            }
        }

        if (!hcNumber) {
            try {
                const stored = JSON.parse(localStorage.getItem('datosPacienteSeleccionado') || '{}');
                hcNumber = stored.identificacion || stored.hcNumber || null;
                procedimiento = stored.procedimiento_proyectado || null;
            } catch (error) {
                // ignore parse errors
            }
        }

        return {formId, hcNumber, procedimiento};
    }

    function setIfEmpty(selector, value) {
        if (!value) return false;
        const field = document.querySelector(selector);
        if (!field) return false;
        const current = String(field.value || '').trim();
        if (current !== '') return false;
        field.value = value.trim();
        return true;
    }

    function formatDateLabel(value) {
        if (!value) return 'consulta previa';
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) return 'consulta previa';
        const day = `${parsed.getDate()}`.padStart(2, '0');
        const month = `${parsed.getMonth() + 1}`.padStart(2, '0');
        const year = parsed.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function placePrevExamButton(examText, planText, fecha) {
        const textarea = document.querySelector(EXAM_SELECTOR);
        const planField = document.querySelector(PLAN_SELECTOR);
        if (!textarea) {
            console.warn('CIVE Extension: textarea de examen físico no encontrado.');
            return;
        }
        const row = textarea.closest('tr') || textarea.closest('.multiple-input-list__item');
        if (!row) {
            console.warn('CIVE Extension: fila de examen físico no encontrada.');
            return;
        }

        const plusCell = row.querySelector('.list-cell__button');
        if (!plusCell) {
            console.warn('CIVE Extension: celda de botones no encontrada para examen físico.');
            return;
        }

        // Mantener el botón "+" tal como está
        const plusButton = plusCell.querySelector('.multiple-input-list__btn');

        // Normalizar estilos del botón "+" por si alguna vez lo pusimos en flex/column
        if (plusButton) {
            plusButton.style.display = '';
            plusButton.style.flexDirection = '';
            plusButton.style.gap = '';
            plusButton.style.alignItems = '';
        }

        // Crear (o reutilizar) un contenedor independiente para el botón de examen previo
        let btnContainer = plusCell.querySelector('.cive-prev-exam-container');
        if (!btnContainer) {
            btnContainer = document.createElement('div');
            btnContainer.className = 'cive-prev-exam-container';
            plusCell.appendChild(btnContainer);
        }

        // Acomodar ambos (plus y copia) en la misma celda pero uno al lado del otro
        plusCell.style.display = 'flex';
        plusCell.style.flexDirection = 'row';
        plusCell.style.gap = '6px';
        plusCell.style.alignItems = 'center';

        // Si el botón ya existe (aunque esté dentro del plus), lo movemos al contenedor
        let btn = plusCell.querySelector(`#${PREV_EXAM_BTN_ID}`);
        if (btn) {
            btnContainer.appendChild(btn);
        } else {
            // Crear el botón si todavía no existe
            btn = document.createElement('button');
            btn.type = 'button';
            btn.id = PREV_EXAM_BTN_ID;
            btn.className = 'btn btn-info btn-sm';
            btn.textContent = `Examen físico de ${formatDateLabel(fecha)}`;
            btn.addEventListener('click', () => {
                if (textarea) {
                    textarea.value = examText || '';
                }
                if (planField) {
                    planField.value = planText || '';
                }
            });
            btnContainer.appendChild(btn);
        }
    }

    async function fetchConsultaAnterior() {
        if (!window.CiveApiClient || typeof window.CiveApiClient.get !== 'function') {
            console.warn('CIVE Extension: CiveApiClient no está disponible para consulta anterior.');
            return null;
        }

        await (window.configCIVE ? window.configCIVE.ready : Promise.resolve());
        const {formId, hcNumber, procedimiento} = getIdentifiers();
        if (!hcNumber) {
            console.warn('CIVE Extension: no se pudo obtener HC para consulta anterior.');
            return null;
        }

        const query = {hcNumber};
        if (formId) {
            query.form_id = formId;
        }
        if (procedimiento) {
            query.procedimiento = procedimiento;
        }

        try {
            const resp = await window.CiveApiClient.get('/consultas/anterior.php', {
                query, retries: 1, retryDelayMs: 500,
            });
            console.log('CIVE Extension: respuesta cruda de /consultas/anterior.php', resp);

            if (resp && resp.success && resp.data) {
                console.log('CIVE Extension: datos de consulta anterior obtenidos del API', resp.data);
                return resp.data;
            }
            console.info('CIVE Extension: sin consulta anterior disponible.', resp?.message || '');
        } catch (error) {
            console.error('CIVE Extension: error al obtener consulta anterior.', error);
        }
        return null;
    }

    window.consultaAnterior = async function consultaAnterior() {
        const data = await fetchConsultaAnterior();
        if (!data) return;

        const examen = data.examen_fisico || data.examenFisico || '';
        const plan = data.plan || '';

        console.log('CIVE Extension: usando datos de consulta anterior para examen previo', {
            dataCompleta: data, examenPrevio: examen, planPrevio: plan
        });

        // Asegurarnos de que el textarea exista (el formulario puede llegar vía PJAX)
        try {
            if (typeof esperarElemento === 'function') {
                // Usamos el helper global si está disponible
                await esperarElemento(EXAM_SELECTOR);
            }
        } catch (e) {
            console.warn('CIVE Extension: no se pudo esperar al campo de examen físico.', e);
        }

        // Intentar colocar el botón aunque el campo ya tenga texto
        placePrevExamButton(examen, plan, data.fecha);
    };

    // Intento automático de insertar el botón de examen físico previo
    try {
        const autoInit = () => {
            if (window.consultaAnterior) {
                window.consultaAnterior().catch && window.consultaAnterior().catch(err => {
                    console.warn('CIVE Extension: error al ejecutar consultaAnterior automáticamente.', err);
                });
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', autoInit);
        } else {
            autoInit();
        }
    } catch (e) {
        console.warn('CIVE Extension: no se pudo inicializar automáticamente consultaAnterior.', e);
    }
})();

// Función que se ejecutará en la página actual para protocolos de cirugía
function ejecutarPopEnPagina() {
    // Función para obtener el contenido después del elemento <th> con el texto específico
    function getContentAfterTh(parent, thText) {
        const thElement = Array.from(parent.querySelectorAll('th')).find(th => th.textContent.includes(thText));
        console.log(`Buscando <th> con el texto: ${thText}`); // Depuración
        console.log('Elemento <th> encontrado:', thElement); // Depuración
        return thElement ? thElement.parentElement.nextElementSibling.textContent.trim() : null;
    }

    // Encuentra el primer <li> que contiene "PROTOCOLO CIRUGIA"
    const liElement = Array.from(document.querySelectorAll('li')).find(li => li.textContent.includes("PROTOCOLO CIRUGIA"));

    console.log("Encontrado el elemento li:", liElement); // Añadido para depurar


    if (liElement) {
        // Extrae los diagnósticos postoperatorios
        const diagnosticosPost = [];
        const postOperatorioHeader = Array.from(liElement.querySelectorAll('th')).find(th => th.textContent.includes('Post Operatorio'));
        console.log("Encontrado el encabezado Post Operatorio:", postOperatorioHeader); // Añadido para depurar

        if (postOperatorioHeader) {
            let row = postOperatorioHeader.parentElement.nextElementSibling;
            while (row && row.querySelector('th') && !row.querySelector('th').textContent.includes('C. PROCEDIMIENTO')) {
                console.log("Procesando fila:", row); // Añadido para depurar
                const diagnosticoCell = row.querySelector('th.descripcion:nth-child(2)');
                if (diagnosticoCell) {
                    const diagnostico = diagnosticoCell.textContent.trim();
                    diagnosticosPost.push(diagnostico);
                    console.log("Encontrado diagnóstico:", diagnostico); // Añadido para depurar
                }
                row = row.nextElementSibling;
            }
        }

        // Extrae el primer código de procedimiento realizado y el ojo afectado
        const procedimientoHeader = Array.from(liElement.querySelectorAll('th')).find(th => th.textContent.includes('Realizado:'));
        console.log("Encontrado el encabezado Realizado:", procedimientoHeader); // Añadido para depurar
        let procedimiento = '';
        let ojoRealizado = '';
        if (procedimientoHeader) {
            const procedimientoElement = procedimientoHeader.nextElementSibling;
            console.log("Elemento siguiente del encabezado Realizado:", procedimientoElement); // Añadido para depurar
            if (procedimientoElement) {
                let procedimientoText = procedimientoElement.textContent.trim();
                const primeraLinea = procedimientoText.split('\n')[0]; // Obtener solo la primera línea
                procedimiento = primeraLinea.trim();

                // Elimina el código de 5 dígitos seguido de un guion al inicio
                procedimiento = procedimiento.replace(/^\d{5}-\s*/, '');

                // Reemplaza (OD) y (OI) con ojo derecho y ojo izquierdo respectivamente
                procedimiento = procedimiento.replace(/\(OD\)/g, 'ojo derecho').replace(/\(OI\)/g, 'ojo izquierdo');
                const ojoMatch = primeraLinea.match(/\((OD|OI)\)/); // Buscar el texto (OD) o (OI)
                if (ojoMatch) {
                    if (ojoMatch[1] === 'OD') {
                        ojoRealizado = 'ojo derecho';
                    } else if (ojoMatch[1] === 'OI') {
                        ojoRealizado = 'ojo izquierdo';
                    }
                }
            }
        }
        console.log("Procedimiento Realizado:", procedimiento); // Añadido para depurar
        console.log("Ojo Realizado:", ojoRealizado); // Añadido para depurar

        // Extrae la fecha de realización
        const fechaInicioOperacionHeader = Array.from(liElement.querySelectorAll('th')).find(th => th.textContent.includes('FECHA DE INICIO DE OPERACIÓN'));
        let fechaInicioOperacion = '';
        if (fechaInicioOperacionHeader) {
            const fechaRow = fechaInicioOperacionHeader.parentElement.nextElementSibling;
            if (fechaRow) {
                const dia = fechaRow.children[0] ? fechaRow.children[0].textContent.trim() : '';
                const mes = fechaRow.children[1] ? fechaRow.children[1].textContent.trim() : '';
                const año = fechaRow.children[2] ? fechaRow.children[2].textContent.trim() : '';
                const hora = fechaRow.children[3] ? fechaRow.children[3].textContent.trim() : '';
                fechaInicioOperacion = `${año}-${mes}-${dia}T${hora}`; // Formato completo para Date
            }
        }
        console.log('Fecha de Inicio de Operación:', fechaInicioOperacion); // Añadido para depurar

        // Calcula el tiempo transcurrido desde la fecha de realización hasta hoy
        const fechaOperacion = new Date(fechaInicioOperacion);
        const fechaActual = new Date();
        const diffTime = Math.abs(fechaActual - fechaOperacion);
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)); // Diferencia en días completos
        const diffHours = Math.floor(diffTime / (1000 * 60 * 60)); // Diferencia en horas completas

        let tiempoTranscurrido;
        if (diffDays < 1) {
            // Menos de 1 día, muestra las horas
            tiempoTranscurrido = `${diffHours} horas`;
        } else {
            // Un día o más, muestra los días
            tiempoTranscurrido = `${diffDays} días`;
        }

        // Construye la nota de evolución médica
        const notaEvolucion = `Paciente acude a control post quirúrgico de ${tiempoTranscurrido} tras haber sido sometido a ${procedimiento}. Sin complicaciones.`;
        const examenFisico = `Biomicroscopia\n${ojoRealizado}: córnea transparente sin edema, cámara anterior formada con burbuja de aire presente, pupila miótica, negra y central, reactiva a la luz, pseudofaquia correctamente posicionada y centrada, sin signos de inflamación intraocular evidente.`;

        // Asigna la nota de evolución médica al textarea con id "consultas-motivoconsulta"
        const consultaTextarea = document.getElementById('consultas-motivoconsulta');
        const observacionTextarea = document.getElementById('consultas-fisico-0-observacion');

        if (consultaTextarea) {
            consultaTextarea.value = notaEvolucion;
            if (observacionTextarea) {
                observacionTextarea.value = examenFisico;
            } else {
                console.log('Textarea con id "consultas-fisico-0-observacion" no encontrado.');
            }
        } else {
            console.log('Textarea con id "consultas-motivoconsulta" no encontrado.');
        }

        // Aquí comienza la parte de la receta
        const item = {
            recetas: [{
                id: 0,
                nombre: 'TRAZIDEX OFTENO SUSP',
                via: 'GOTERO',
                unidad: 'GOTAS',
                pauta: 'Cada 4 horas',
                cantidad: 21,
                totalFarmacia: 1,
                observaciones: `TRAZIDEX OFTENO SUSP. OFT. X 5 ML 1 GOTAS GOTERO CADA 4 HORAS x 21 DÍAS EN ${ojoRealizado}`
            }], recetaCount: 1
        };

        realizarSecuenciaDeAcciones(item).then(() => {
            console.log('Recetas generadas correctamente.');
        }).catch(error => {
            console.error('Error al generar las recetas:', error);
        });

    } else {
        console.log('No se encontró un <li> con "PROTOCOLO CIRUGIA".');
    }
}

// Las funciones adicionales que mencionaste se integran aquí:
function realizarSecuenciaDeAcciones(item) {
    return hacerClickEnPresuntivo('.form-group.field-consultas-tipo_externa .cbx-container .cbx', 1)
        .then(() => hacerClickEnSelect2('#select2-consultas-fisico-0-tipoexamen_id-container'))
        .then(() => establecerBusqueda('#select2-consultas-fisico-0-tipoexamen_id-container', "OJOS"))
        .then(() => seleccionarOpcion())
        .then(() => ejecutarRecetas(item))
        .then(() => {
            console.log('Recetas generadas correctamente.');
        })
        .catch(error => {
            console.error('Error al ejecutar la secuencia:', error);
        });
}

function llenarCampoTexto(selector, valor) {
    return new Promise((resolve, reject) => {
        const textArea = document.querySelector(selector);
        if (textArea) {
            console.log(`Llenando el campo de texto "${selector}" con "${valor}"`);
            textArea.value = valor;
            setTimeout(resolve, 100); // Añadir un retraso para asegurar que el valor se establezca
        } else {
            console.error(`El campo de texto "${selector}" no se encontró.`);
            reject(`El campo de texto "${selector}" no se encontró.`);
        }
    });
}

function hacerClickEnBotonDentroDeMedicina(selector, contenedorId, numeroDeClicks) {
    return new Promise((resolve, reject) => {
        const contenedor = document.getElementById(contenedorId);
        if (!contenedor) {
            console.error(`El contenedor con ID "${contenedorId}" no se encontró.`);
            reject(`El contenedor con ID "${contenedorId}" no se encontró.`);
            return;
        }

        const botonPlus = contenedor.querySelector(selector);
        if (botonPlus) {
            console.log(`Haciendo clic en el botón "${selector}" dentro de "${contenedorId}" ${numeroDeClicks} veces`);
            let clicks = 0;

            function clickBoton() {
                if (clicks < numeroDeClicks) {
                    botonPlus.click();
                    clicks++;
                    setTimeout(clickBoton, 100); // 100ms delay between clicks
                } else {
                    resolve();
                }
            }

            clickBoton();
        } else {
            console.error(`El botón "${selector}" no se encontró dentro de "${contenedorId}".`);
            reject(`El botón "${selector}" no se encontró dentro de "${contenedorId}".`);
        }
    });
}

function hacerClickEnSelect2(selector) {
    return new Promise((resolve, reject) => {
        const tecnicoContainer = document.querySelector(selector);
        if (tecnicoContainer) {
            console.log(`Haciendo clic en el contenedor: ${selector}`);
            const event = new MouseEvent('mousedown', {
                view: window, bubbles: true, cancelable: true
            });
            tecnicoContainer.dispatchEvent(event);
            setTimeout(resolve, 100); // Añadir un retraso para asegurar que el menú se despliegue
        } else {
            console.error(`El contenedor "${selector}" no se encontró.`);
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
                console.log(`Intento ${attempts + 1}: no se encontró el campo de búsqueda. Retentando...`);
                attempts++;
                if (attempts < maxAttempts) {
                    hacerClickEnSelect2(selector).then(() => setTimeout(searchForField, 500)).catch(error => reject(error));
                } else {
                    console.error('El campo de búsqueda del Select2 no se encontró.');
                    reject('El campo de búsqueda del Select2 no se encontró.');
                }
            } else {
                console.log('Estableciendo búsqueda:', valor);
                searchField.value = valor;
                const inputEvent = new Event('input', {bubbles: true, cancelable: true});
                searchField.dispatchEvent(inputEvent);
                setTimeout(() => resolve(searchField), 500);
            }
        };

        searchForField();
    });
}

function seleccionarOpcion() {
    return new Promise((resolve, reject) => {
        const searchField = document.querySelector('input.select2-search__field');
        if (searchField) {
            console.log('Seleccionando opción');
            const enterEvent = new KeyboardEvent('keydown', {
                key: 'Enter', keyCode: 13, bubbles: true, cancelable: true
            });
            searchField.dispatchEvent(enterEvent);
            setTimeout(resolve, 200); // Añadir un retraso para asegurar que la opción se seleccione
        } else {
            console.error('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
            reject('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
        }
    });
}

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

function llenarCampoCantidad(selector, cantidad, tabCount = 0) {
    return new Promise((resolve, reject) => {
        const campoCantidad = document.querySelector(selector);
        if (campoCantidad) {
            console.log(`Llenando el campo cantidad con el valor: ${cantidad}`);
            campoCantidad.focus();
            campoCantidad.value = cantidad;
            campoCantidad.dispatchEvent(new Event('input', {bubbles: true}));
            campoCantidad.dispatchEvent(new Event('change', {bubbles: true}));

            // Simular la tecla TAB la cantidad de veces especificada
            let tabsPressed = 0;
            const pressTab = () => {
                if (tabsPressed < tabCount) {
                    const tabEvent = new KeyboardEvent('keydown', {
                        key: 'Tab', keyCode: 9, code: 'Tab', which: 9, bubbles: true, cancelable: true
                    });
                    document.activeElement.dispatchEvent(tabEvent);

                    const tabEventPress = new KeyboardEvent('keypress', {
                        key: 'Tab', keyCode: 9, code: 'Tab', which: 9, bubbles: true, cancelable: true
                    });
                    document.activeElement.dispatchEvent(tabEventPress);

                    const tabEventUp = new KeyboardEvent('keyup', {
                        key: 'Tab', keyCode: 9, code: 'Tab', which: 9, bubbles: true, cancelable: true
                    });
                    document.activeElement.dispatchEvent(tabEventUp);

                    tabsPressed++;
                    setTimeout(pressTab, 100); // Asegurar que el evento se despacha correctamente
                } else {
                    campoCantidad.blur();
                    resolve();
                }
            };
            pressTab();
        } else {
            console.error('El campo cantidad no se encontró.');
            reject('El campo cantidad no se encontró.');
        }
    });
}

function ejecutarRecetas(item) {
    if (!Array.isArray(item.recetas)) return Promise.resolve();

    return hacerClickEnBotonDentroDeMedicina('.js-input-plus', 'medicamento', 0)
        .then(() => hacerClickEnBotonDentroDeMedicina('.js-input-plus', 'medicamento', item.recetaCount))
        .then(() => esperarElemento(`#select2-recetas-recetasadd-0-producto_id-container`)) // Solo se ejecuta una vez
        .then(() => {
            // Iterar sobre cada receta
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    // Manejar el producto
                    return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-producto_id-container`)
                        .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-producto_id-container`, receta.nombre))
                        .then(() => seleccionarOpcion());
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        })
        .then(() => {
            // Ahora manejar las vías
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-vias-container`)
                        .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-vias-container`, receta.via))
                        .then(() => seleccionarOpcion());
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        })
        .then(() => {
            // Ahora manejar las unidades
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-unidad_id-container`)
                        .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-unidad_id-container`, receta.unidad))
                        .then(() => seleccionarOpcion());
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        })
        .then(() => {
            // Ahora manejar las pautas
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    return hacerClickEnSelect2(`#select2-recetas-recetasadd-${receta.id}-pauta-container`)
                        .then(() => establecerBusqueda(`#select2-recetas-recetasadd-${receta.id}-pauta-container`, receta.pauta))
                        .then(() => seleccionarOpcion());
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        })
        .then(() => {
            // Ahora manejar las cantidades
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    return llenarCampoCantidad(`#recetas-recetasadd-${receta.id}-cantidad`, receta.cantidad, 2)
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        })
        .then(() => {
            // Ahora manejar las total_farmacia
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    return llenarCampoTexto(`#recetas-recetasadd-${receta.id}-total_farmacia`, receta.totalFarmacia)
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        })
        .then(() => {
            // Ahora manejar las observaciones
            return item.recetas.reduce((promise, receta) => {
                return promise.then(() => {
                    return llenarCampoTexto(`#recetas-recetasadd-${receta.id}-observaciones`, receta.observaciones)
                });
            }, Promise.resolve()); // Inicializa con una promesa resuelta
        });
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
