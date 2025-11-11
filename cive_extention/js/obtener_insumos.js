// Utils agrupados para Select2
const Select2Utils = {
    hacerClick: function (selector) {
        return new Promise((resolve, reject) => {
            const container = document.querySelector(selector);
            if (container) {
                const event = new MouseEvent('mousedown', {
                    view: window, bubbles: true, cancelable: true
                });
                container.dispatchEvent(event);
                setTimeout(resolve, 200);
            } else {
                console.error(`❌ Contenedor "${selector}" no encontrado.`);
                reject(`Contenedor "${selector}" no encontrado.`);
            }
        });
    },

    buscar: function (textoBusqueda) {
        console.log("🧪 Valor recibido en buscarEnSelect2:", textoBusqueda);
        return new Promise((resolve, reject) => {
            const maxIntentos = 10;
            let intentos = 0;

            const intentarBuscar = () => {
                const campo = document.querySelector("input.select2-search__field");
                if (campo) {
                    campo.value = textoBusqueda;
                    const eventoInput = new Event("input", {bubbles: true, cancelable: true});
                    campo.dispatchEvent(eventoInput);
                    setTimeout(resolve, 300);
                } else if (intentos < maxIntentos) {
                    intentos++;
                    setTimeout(intentarBuscar, 300);
                } else {
                    reject("No se encontró el campo de búsqueda de Select2.");
                }
            };

            intentarBuscar();
        });
    },

    seleccionar: function () {
        return new Promise((resolve, reject) => {
            const esperarOpciones = () => {
                const opcion = document.querySelector(".select2-results__option");
                if (opcion) {
                    const campo = document.querySelector("input.select2-search__field");
                    if (campo) {
                        const enterEvent = new KeyboardEvent("keydown", {
                            key: "Enter", keyCode: 13, bubbles: true, cancelable: true
                        });
                        campo.dispatchEvent(enterEvent);
                        setTimeout(resolve, 300);
                    } else {
                        reject("No se encontró el campo de búsqueda para hacer Enter.");
                    }
                } else {
                    setTimeout(esperarOpciones, 200);
                }
            };
            esperarOpciones();
        });
    }
};

// Utilidad: convertir duración tipo "HH:MM" a cuartos de hora (15 minutos)
function convertirDuracionACuartos(duracionStr) {
    const [horas, minutos] = duracionStr.split(':').map(Number);
    return Math.round((horas * 60 + minutos) / 15);
}

// Nueva función utilitaria para calcular cuartos desde duración en formato HH:mm
function calcularCuartosDesdeDuracion(duracionStr) {
    const [horasStr, minutosStr] = duracionStr.split(":");
    const horas = parseInt(horasStr, 10);
    const minutos = parseInt(minutosStr, 10);
    const totalMinutos = horas * 60 + minutos;
    const cuartos = Math.round(totalMinutos / 15); // Cada cuarto = 15 minutos
    return cuartos;
}

// Nueva función utilitaria para lógica de anestesia según afiliación y código de cirugía
function generarTiempoAnestesia(afiliacion, codigoCirugia, duracion = "01:00") {
    const afiliacionNormalizada = afiliacion.trim().toUpperCase();
    const codigo = (codigoCirugia || "").trim();
    const CUARTOS = calcularCuartosDesdeDuracion(duracion);

    console.log("🟡 DEBUG afiliacionNormalizada:", afiliacionNormalizada);
    console.log("🟡 DEBUG codigoCirugia detectado:", codigo);
    console.log("🟡 DEBUG cuartos calculados:", CUARTOS);

    if (afiliacionNormalizada === "ISSFA" && codigo === "66984") {
        return [{codigo: "999999", cantidad: CUARTOS}];
    } else if (afiliacionNormalizada === "ISSFA") {
        const cantidad99149 = CUARTOS >= 2 ? 1 : CUARTOS;
        const cantidad99150 = CUARTOS > 2 ? CUARTOS - 2 : 0;
        const result = [];
        if (cantidad99149 > 0) result.push({codigo: "99149", cantidad: cantidad99149});
        if (cantidad99150 > 0) result.push({codigo: "99150", cantidad: cantidad99150});
        return result;
    } else {
        return [{codigo: "999999", cantidad: CUARTOS}];
    }
}

window.erroresInsumosNoIngresados = [];
window.detectarInsumosPaciente = () => {
    // Validación de URL permitida
    const urlPermitidas = ['http://cive.ddns.net:8085/documentacion/doc-documento', 'http://192.168.1.13:8085/documentacion/doc-documento'];
    if (!urlPermitidas.some(url => window.location.href.startsWith(url))) {
        console.log("🚫 URL no permitida. Script detenido.");
        return;
    }

    console.log("🔍 Módulo obtener_insumos.js cargado. Esperando clic en icono de corazón...");

    setTimeout(() => {
        document.querySelectorAll('td[data-col-seq="14"] a').forEach(heartIcon => {
            if (!heartIcon.dataset.listenerAdded) {
                heartIcon.addEventListener("click", (event) => {
                    event.preventDefault();

                    const fila = heartIcon.closest("tr");
                    const textoProcedimiento = fila?.querySelector('td[data-col-seq="8"]')?.textContent?.trim()?.toUpperCase();
                    console.log("🟢 DEBUG textoProcedimiento capturado:", textoProcedimiento);
                    window.textoProcedimientoGlobal = textoProcedimiento;

                    if (!textoProcedimiento || !textoProcedimiento.startsWith("CIRUGIAS")) {
                        console.log("⚠️ Procedimiento no quirúrgico. No se ejecutará la lógica de insumos.");
                        return;
                    }

                    console.log("❤️ Icono de corazón clickeado. Esperando que se abra el modal...");

                    // Esperar a que el modal esté visible
                    let intentos = 0;
                    const maxIntentos = 10;

                    const esperarModal = setInterval(() => {
                        const modal = document.querySelector('.modal.show, .modal[style*="display: block"], .modal[aria-hidden="false"]');

                        if (modal) {
                            console.log("🟢 Modal detectado. Extrayendo datos...");
                            clearInterval(esperarModal);

                            // Extraer idSolicitud desde los enlaces con "imprimir-cotizacion"
                            let idSolicitud = null;
                            document.querySelectorAll('a[href*="imprimir-cotizacion"]').forEach(link => {
                                const urlParams = new URLSearchParams(new URL(link.href, window.location.origin).search);
                                if (urlParams.has("idSolicitud")) {
                                    idSolicitud = urlParams.get("idSolicitud");
                                }
                            });

                            // Extraer el número de historia clínica
                            const hcInput = document.querySelector('input#numero-historia-clinica');
                            let hcNumber = hcInput ? hcInput.value : null;

                            if (idSolicitud && hcNumber) {
                                console.log(`✅ Datos detectados:
                                - idSolicitud: ${idSolicitud}
                                - hcNumber: ${hcNumber}`);

                                // Llamar a la función para enviar los datos al API
                                enviarDatosAPI(idSolicitud, hcNumber);
                            } else {
                                console.warn("⚠️ No se encontraron todos los datos necesarios.");
                            }
                        } else {
                            console.log(`⏳ Intentando detectar modal... (${intentos + 1}/${maxIntentos})`);
                            intentos++;
                            if (intentos >= maxIntentos) {
                                console.warn("⚠️ No se detectó el modal después de varios intentos.");
                                clearInterval(esperarModal);
                            }
                        }
                    }, 500);
                });
                heartIcon.dataset.listenerAdded = "true";
            }
        });
    }, 1000);
};

// Función para enviar los datos al API y mostrar alerta con los insumos
function enviarDatosAPI(idSolicitud, hcNumber) {
    console.log("📡 Enviando datos al API...");

    fetch("https://asistentecive.consulmed.me/api/insumos/obtener.php", {
        method: "POST", headers: {
            "Content-Type": "application/json"
        }, body: JSON.stringify({
            hcNumber: hcNumber, form_id: idSolicitud
        })
    })
        .then(response => response.json())
        .then(data => {
            // Obtener y normalizar afiliación inmediatamente después de recibir la respuesta de la API
            const afiliacion = data.afiliacion || "";
            const afiliacionNormalizada = afiliacion.trim().toUpperCase();
            console.log("Afiliación detectada:", afiliacionNormalizada);

            if (data.success) {
                console.log("✅ Respuesta del API:", data);
                // Guardar duración en variable global si está disponible
                window.duracionOxigenoGlobal = data.duracion;

                // --- Nueva lógica para definir tiempoAnestesia según afiliación y código usando la función utilitaria ---
                // Asegurarse de que textoProcedimiento esté disponible en este scope:
                // Si no está definido, intenta obtenerlo como en el listener del heartIcon
                let textoProcedimiento = window.textoProcedimientoGlobal;
                if (!textoProcedimiento) {
                    // Busca la fila seleccionada del modal si es posible
                    const filaSeleccionada = document.querySelector('tr.selected');
                    textoProcedimiento = filaSeleccionada?.querySelector('td[data-col-seq="8"]')?.textContent?.trim()?.toUpperCase();
                    window.textoProcedimientoGlobal = textoProcedimiento;
                }
                const matchCodigo = textoProcedimiento?.match(/CIRUGIAS\s*-\s*(\d+)/);
                const codigoCirugia = matchCodigo ? matchCodigo[1] : "";
                window.tiempoAnestesiaGlobal = generarTiempoAnestesia(afiliacion, codigoCirugia, window.duracionOxigenoGlobal || "01:00");
                console.log("✅ Tiempo de anestesia definido:", window.tiempoAnestesiaGlobal);

                // Nuevo: Verificar status distinto de 1 antes de mostrar insumos
                if (data.status !== 1) {
                    Swal.fire({
                        icon: "warning",
                        title: "Protocolo no revisado",
                        text: "Este protocolo aún no ha sido revisado. No se puede iniciar el autollenado.",
                        confirmButtonText: "Aceptar"
                    });
                    return;
                }

                if (data.insumos && (data.insumos.equipos?.length > 0 || data.insumos.anestesia?.length > 0 || data.insumos.quirurgicos?.length > 0)) {
                    // Si necesitas usar afiliacion o afiliacionNormalizada, hazlo dentro de este bloque
                    mostrarAlertaInsumos(data.insumos);
                } else {
                    Swal.fire({
                        icon: "info",
                        title: "Sin insumos",
                        text: "No se encontraron insumos disponibles para este paciente.",
                        confirmButtonText: "Aceptar"
                    });
                }
            } else {
                console.warn("⚠️ Error en la respuesta del API:", data.message);
                Swal.fire({
                    icon: "error", title: "Error en la consulta", text: data.message, confirmButtonText: "Cerrar"
                });
            }
        })
        .catch(error => {
            console.error("❌ Error en la solicitud:", error);
            Swal.fire({
                icon: "error",
                title: "Error en la conexión",
                text: "No se pudo conectar con el servidor.",
                confirmButtonText: "Cerrar"
            });
        });
}

// 🔍 Obtener nombres de la tabla actual (columna "Nombre")
function obtenerNombresTabla() {
    const nombres = [];
    document.querySelectorAll('#seriales-input-insumos td.list-cell__insumo').forEach(td => {
        const texto = td.textContent.trim().toUpperCase();
        const nombre = texto.split(" - ")[0];
        if (nombre) nombres.push(nombre);
    });
    return nombres;
}

// 📢 Mostrar alerta con insumos no repetidos
function mostrarAlertaInsumos(insumos) {
    const nombresTabla = obtenerNombresTabla();
    console.log("📋 Nombres existentes en la tabla:", nombresTabla);

    // Detectar también los nombres de los derechos (uso de equipos)
    const nombresDerechosTabla = [];
    document.querySelectorAll('[id^="select2-hccirugiahospitalizacion-derechos-"][id$="-derecho-container"]').forEach(span => {
        const texto = span.textContent.trim().toUpperCase();
        const nombre = texto.split(" - ")[0]; // Extraer código o nombre base
        if (nombre) nombresDerechosTabla.push(nombre);
    });
    console.log("📋 Nombres de derechos ya en la tabla:", nombresDerechosTabla);

    const nombresAnestesiaTabla = [];
    document.querySelectorAll('[id^="select2-hccirugiahospitalizacion-insumos-"][id$="-insumo-container"]').forEach(span => {
        const texto = span.textContent.trim().toUpperCase();
        const nombre = texto.split(" - ")[0]; // Extraer parte útil si tiene formato "código - descripción"
        if (nombre) nombresAnestesiaTabla.push(nombre);
    });
    console.log("📋 Nombres de anestesia ya en la tabla:", nombresAnestesiaTabla);

    // 🧪 Verificar si el oxígeno 911111 ya está presente y con los valores correctos usando setTimeout
    setTimeout(() => {
        // --- Oxígeno ---
        const filaOxigeno = document.querySelector(`#select2-hccirugiahospitalizacion-oxigeno-0-oxigeno_id-container`);
        window.oxigenoPresente = false;
        window.oxigenoDuracionCorrecta = false;
        window.oxigenoLitrosCorrecto = false;

        if (filaOxigeno && filaOxigeno.textContent.toUpperCase().includes("911111")) {
            window.oxigenoPresente = true;

            const inputTiempo = document.querySelector(`#hccirugiahospitalizacion-oxigeno-0-tiempo`);
            if (inputTiempo && inputTiempo.value !== "") {
                const duracionApi = window.duracionOxigenoGlobal || "01:00";
                const [h, m] = duracionApi.split(":").map(Number);
                const tiempoEsperado = h + (m / 60);
                window.oxigenoDuracionCorrecta = parseFloat(inputTiempo.value).toFixed(2) === tiempoEsperado.toFixed(2);
            }

            const inputLitros = document.querySelector(`#hccirugiahospitalizacion-oxigeno-0-litros`);
            if (inputLitros && inputLitros.value !== "") {
                window.oxigenoLitrosCorrecto = parseFloat(inputLitros.value) === 3;
            }
        }

        if (!window.oxigenoPresente || !window.oxigenoDuracionCorrecta || !window.oxigenoLitrosCorrecto) {
            window.erroresInsumosNoIngresados.push({
                tipo: "oxigeno",
                nombre: "911111",
                fila: 1,
                error: `${!window.oxigenoPresente ? 'No presente' : ''}${!window.oxigenoPresente ? ', ' : ''}${!window.oxigenoDuracionCorrecta ? 'Duración incorrecta' : ''}${!window.oxigenoDuracionCorrecta && !window.oxigenoLitrosCorrecto ? ', ' : ''}${!window.oxigenoLitrosCorrecto ? 'Litros distinto de 3' : ''}`
            });

            const duracion = window.duracionOxigenoGlobal || "01:00";
            const [h, m] = duracion.split(":").map(Number);
            const tiempoEsperado = h + (m / 60);

            const inputTiempo = document.querySelector(`#hccirugiahospitalizacion-oxigeno-0-tiempo`);
            if (inputTiempo) {
                inputTiempo.value = tiempoEsperado;
                inputTiempo.dispatchEvent(new Event("change", {bubbles: true}));
                console.log(`🔄 Duración de oxígeno corregida a ${tiempoEsperado} horas`);
            }

            const inputLitros = document.querySelector(`#hccirugiahospitalizacion-oxigeno-0-litros`);
            if (inputLitros) {
                inputLitros.value = 3;
                inputLitros.dispatchEvent(new Event("change", {bubbles: true}));
                console.log("🔄 Litros de oxígeno corregidos a 3");
            }
        }

        // --- Tiempo de Anestesia ---
        // Nueva lógica: Asignar correctamente los cuartos según los códigos 99149 y 99150 sin sobrescribir toda la duración en un solo campo
        const duracion = window.duracionOxigenoGlobal || "01:00";
        const CUARTOS = calcularCuartosDesdeDuracion(duracion);
        const cantidad99149 = Math.min(CUARTOS, 2);
        const cantidad99150 = CUARTOS > 2 ? CUARTOS - 2 : 0;

        const anestesiaFila1Codigo = document.querySelector(`#select2-hccirugiahospitalizacion-anestesia-0-anestesia-container`)?.textContent.trim();
        const anestesiaFila2Codigo = document.querySelector(`#select2-hccirugiahospitalizacion-anestesia-1-anestesia-container`)?.textContent.trim();

        const anestesiaFila1Tiempo = document.querySelector(`#hccirugiahospitalizacion-anestesia-0-tiempo`);
        const anestesiaFila2Tiempo = document.querySelector(`#hccirugiahospitalizacion-anestesia-1-tiempo`);

        // Asegurar que el código 99149 tenga hasta 2 cuartos
        if (anestesiaFila1Codigo === "99149" && anestesiaFila1Tiempo) {
            anestesiaFila1Tiempo.value = cantidad99149;
            anestesiaFila1Tiempo.dispatchEvent(new Event("change", {bubbles: true}));
            console.log(`🔄 Tiempo 99149 corregido a ${cantidad99149}`);
        }

        // Asegurar que el código 99150 tenga el resto de los cuartos
        if (anestesiaFila2Codigo === "99150" && anestesiaFila2Tiempo) {
            anestesiaFila2Tiempo.value = cantidad99150;
            anestesiaFila2Tiempo.dispatchEvent(new Event("change", {bubbles: true}));
            console.log(`🔄 Tiempo 99150 corregido a ${cantidad99150}`);
        }
        if (!window.tiempoAnestesiaPresente || !window.tiempoAnestesiaCuartosCorrecto) {
            window.erroresInsumosNoIngresados.push({
                tipo: "tiempo_anestesia",
                nombre: "999999",
                fila: 1,
                error: `${!window.tiempoAnestesiaPresente ? 'No presente' : ''}${!window.tiempoAnestesiaPresente ? ', ' : ''}${!window.tiempoAnestesiaCuartosCorrecto ? 'Duración incorrecta' : ''}`
            });

            const duracion = window.duracionOxigenoGlobal || "01:00";
            const CUARTOS = calcularCuartosDesdeDuracion(duracion);

            const inputTiempoAnestesia = document.querySelector(`#hccirugiahospitalizacion-anestesia-0-tiempo`);
            if (inputTiempoAnestesia) {
                inputTiempoAnestesia.value = CUARTOS;
                inputTiempoAnestesia.dispatchEvent(new Event("change", {bubbles: true}));
                console.log(`🔄 Duración de anestesia corregida a ${CUARTOS} bloques de 15 minutos`);
            }
        }
    }, 500);

    const nuevosEquipos = (insumos.equipos || []).filter(equipo => {
        if (!equipo.codigo) return false; // Solo contar si tiene código
        const codigoAPI = equipo.codigo.trim().toUpperCase();
        const yaExiste = nombresTabla.includes(codigoAPI) || nombresDerechosTabla.includes(codigoAPI);
        console.log(`🔍 Comparando: "${codigoAPI}" con [${nombresTabla.join(", ")}] y derechos [${nombresDerechosTabla.join(", ")}] → ${yaExiste ? "❌ Ya existe" : "✅ Nuevo"}`);
        return !yaExiste;
    });

    // 🔄 Combinar anestesia y quirúrgicos por código y sumar cantidades
    const mapInsumos = new Map();
    const insumosCombinados = [...(insumos.anestesia || []), ...(insumos.quirurgicos || [])];

    insumosCombinados.forEach(item => {
        if (!item.codigo) {
            //console.warn("⚠️ Insumo sin código omitido:", item.nombre || item);
            return;
        }

        const codigo = item.codigo.trim().toUpperCase();
        const cantidad = parseInt(item.cantidad) || 0;

        if (mapInsumos.has(codigo)) {
            mapInsumos.get(codigo).cantidad += cantidad;
        } else {
            mapInsumos.set(codigo, {
                ...item, codigo, cantidad
            });
        }
    });

    console.log("🔎 Total insumos combinados:", mapInsumos.size);
    const insumosUnificados = Array.from(mapInsumos.values()).filter(i => i.codigo && i.codigo.trim() !== "");

    // Detectar insumos presentes pero con cantidad incorrecta
    const insumosCantidadIncorrecta = insumosUnificados.filter(item => {
        const codigo = item.codigo;
        const cantidadEsperada = parseInt(item.cantidad);
        const fila = [...document.querySelectorAll('[id^="select2-hccirugiahospitalizacion-insumos-"][id$="-insumo-container"]')]
            .findIndex(span => span.textContent.trim().toUpperCase().startsWith(codigo));

        if (fila === -1) return false;

        const inputCantidad = document.querySelector(`#hccirugiahospitalizacion-insumos-${fila}-cantidad-insumos`);
        if (!inputCantidad) return false;

        const cantidadActual = parseInt(inputCantidad.value || "0");
        return cantidadActual !== cantidadEsperada;
    });

    // 📋 Obtener códigos existentes en la tabla de insumos
    const codigosTablaAnestesia = [];
    document.querySelectorAll('[id^="select2-hccirugiahospitalizacion-insumos-"][id$="-insumo-container"]').forEach(span => {
        const texto = span.textContent.trim().toUpperCase();
        const codigo = texto.split(" - ")[0]; // Asumimos que inicia con el código
        if (codigo) codigosTablaAnestesia.push(codigo);
    });

    // 🔍 Filtrar solo los nuevos insumos (no repetidos)
    const insumosNuevos = insumosUnificados.filter(item => !codigosTablaAnestesia.includes(item.codigo));
    console.log("📋 Códigos ya en la tabla de anestesia:", codigosTablaAnestesia);
    console.log("🆕 Insumos (anestesia + quirúrgicos) a insertar:", insumosNuevos.map(i => `${i.codigo} (${i.cantidad})`));

    console.log("🆕 Equipos nuevos a insertar:", nuevosEquipos.map(e => e.nombre));

    let mensaje = "";
    if (nuevosEquipos.length > 0) {
        mensaje += `<strong>Equipos nuevos:</strong> ${nuevosEquipos.length} elementos<br>`;
    }
    if (insumosNuevos.length > 0) {
        mensaje += `<strong>Anestesia/Quirúrgicos nuevos:</strong> ${insumosNuevos.length} elementos<br>`;
    }
    if (insumosCantidadIncorrecta.length > 0) {
        mensaje += `<strong>Cantidades incorrectas:</strong> ${insumosCantidadIncorrecta.length} insumos serán corregidos<br>`;
    }
    if (!window.oxigenoPresente || !window.oxigenoDuracionCorrecta || !window.oxigenoLitrosCorrecto) {
        mensaje += `<strong>Oxígeno:</strong> Se corregirá el valor<br>`;
    }
    if (!window.tiempoAnestesiaPresente || !window.tiempoAnestesiaCuartosCorrecto) {
        mensaje += `<strong>Tiempo de anestesia:</strong> Se corregirá el valor<br>`;
    }

    if (nuevosEquipos.length === 0 && insumosNuevos.length === 0 && insumosCantidadIncorrecta.length === 0) {
        Swal.fire({
            icon: "info",
            title: "Insumos ya existentes",
            text: "Todos los equipos y anestesias ya están presentes en la tabla.",
            confirmButtonText: "Aceptar"
        });
        return;
    }

    Swal.fire({
        icon: "success",
        title: "Insumos nuevos detectados",
        html: `${mensaje}<br>¿Deseas agregarlos?`,
        showCancelButton: true,
        confirmButtonText: "Sí, agregar",
        cancelButtonText: "No, cancelar"
    }).then(async (result) => {
        if (result.isConfirmed) {
            console.log("✅ El usuario aceptó agregar los nuevos insumos.");

            // Corregir cantidades incorrectas antes de completar datos
            insumosCantidadIncorrecta.forEach(item => {
                const codigo = item.codigo;
                const cantidadEsperada = parseInt(item.cantidad);
                const fila = [...document.querySelectorAll('[id^="select2-hccirugiahospitalizacion-insumos-"][id$="-insumo-container"]')]
                    .findIndex(span => span.textContent.trim().toUpperCase().startsWith(codigo));

                if (fila !== -1) {
                    const inputCantidad = document.querySelector(`#hccirugiahospitalizacion-insumos-${fila}-cantidad-insumos`);
                    if (inputCantidad) {
                        inputCantidad.value = cantidadEsperada;
                        const eventChange = new Event("change", {bubbles: true});
                        inputCantidad.dispatchEvent(eventChange);
                        console.log(`🔁 Cantidad de "${codigo}" corregida a ${cantidadEsperada} en fila ${fila}`);
                    }
                }
            });

            await new Promise(resolve => agregarFilas("#seriales-input-derecho .js-input-plus", nuevosEquipos.length, resolve, "equipos"));
            await new Promise(resolve => agregarFilas("#seriales-input-insumos .js-input-plus", insumosNuevos.length, resolve, "anestesia"));
            await new Promise(resolve => agregarFilas("#seriales-input-oxigeno .js-input-plus", 1, resolve, "oxigeno"));
            await new Promise(resolve => agregarFilas("#seriales-input-anestesia .js-input-plus", window.tiempoAnestesiaGlobal.length, resolve, "tiempoAnestesia"));

            await completarDatosEquipos(nuevosEquipos);
            console.log("✅ Equipos agregados correctamente.");

            await completarDatosAnestesia(insumosNuevos);
            console.log("✅ Anestesia + quirúrgicos agregados correctamente.");

            await completarDatosOxigeno("911111", 0);
            console.log("✅ Oxígeno agregado correctamente.");

            // Nueva lógica: Asegurar filas y asignar SOLO los códigos definidos en tiempoAnestesiaGlobal
            const filasAnestesia = document.querySelectorAll("#seriales-input-anestesia .multiple-input-list__item");
            const filasAnestesiaExistentes = filasAnestesia.length;
            let filasNecesarias = window.tiempoAnestesiaGlobal.length;

            // Si hay filas insuficientes, agregar las necesarias
            if (filasAnestesiaExistentes < filasNecesarias) {
                await new Promise(resolve => agregarFilas("#seriales-input-anestesia .js-input-plus", filasNecesarias - filasAnestesiaExistentes, resolve, "tiempoAnestesia"));
            }

            // Insertar SOLO los códigos definidos en tiempoAnestesiaGlobal
            for (let i = 0; i < window.tiempoAnestesiaGlobal.length; i++) {
                const item = window.tiempoAnestesiaGlobal[i];
                await completarDatosTiempoAnestesia(item.codigo, item.cantidad, i);
            }

            // Filtrar errores que realmente persisten después de correcciones
            window.erroresInsumosNoIngresados = window.erroresInsumosNoIngresados.filter(err => {
                if (err.tipo === "oxigeno") {
                    return !window.oxigenoPresente || !window.oxigenoDuracionCorrecta || !window.oxigenoLitrosCorrecto;
                }
                if (err.tipo === "tiempo_anestesia") {
                    return !window.tiempoAnestesiaPresente || !window.tiempoAnestesiaCuartosCorrecto;
                }
                return true;
            });

            if (window.erroresInsumosNoIngresados.length > 0) {
                console.warn("❗Errores detectados durante el llenado:");
                console.table(window.erroresInsumosNoIngresados);

                const detalles = window.erroresInsumosNoIngresados
                    .map(err => `• ${err.tipo.toUpperCase()} "${err.nombre}" (fila ${err.fila}) - ${err.error}`)
                    .join('\n');

                const insumosFaltantes = window.erroresInsumosNoIngresados
                    .filter(err => err.tipo === 'anestesia' || err.tipo === 'equipo')
                    .map(err => `<li><strong>${err.tipo.toUpperCase()}</strong>: ${err.nombre} (fila ${err.fila})</li>`)
                    .join('');

                let htmlFinal = `<pre style="text-align:left;font-size:13px">${detalles}</pre>`;

                if (insumosFaltantes) {
                    htmlFinal += `<hr><p><strong>❗Insumos no ingresados que deben ser agregados manualmente:</strong></p><ul>${insumosFaltantes}</ul>`;
                }

                Swal.fire({
                    icon: "warning",
                    title: "Insumos no ingresados",
                    html: htmlFinal,
                    showCancelButton: true,
                    confirmButtonText: "Reintentar",
                    cancelButtonText: "Cerrar"
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        console.log("♻️ Reintentando inserción de insumos fallidos...");

                        const erroresActuales = [...window.erroresInsumosNoIngresados];
                        window.erroresInsumosNoIngresados = [];

                        for (const error of erroresActuales) {
                            if (error.tipo === "oxigeno") {
                                await completarDatosOxigeno(error.nombre, error.fila - 1);
                            } else if (error.tipo === "tiempo_anestesia") {
                                await completarDatosTiempoAnestesia(error.nombre, error.fila - 1);
                            } else if (error.tipo === "anestesia") {
                                // Añadir explícitamente una fila antes de intentar nuevamente
                                await new Promise(resolve => agregarFilas("#seriales-input-insumos .js-input-plus", 1, resolve, "anestesia"));
                                await completarDatosAnestesia([{
                                    codigo: error.nombre, cantidad: 1
                                }]);
                            } else if (error.tipo === "equipo") {
                                // Añadir explícitamente una fila antes de intentar nuevamente
                                await new Promise(resolve => agregarFilas("#seriales-input-derecho .js-input-plus", 1, resolve, "equipos"));
                                await completarDatosEquipos([{
                                    codigo: error.nombre
                                }]);
                            }
                        }

                        if (window.erroresInsumosNoIngresados.length > 0) {
                            console.warn("⚠️ Algunos insumos siguen sin poder agregarse:", window.erroresInsumosNoIngresados);
                            Swal.fire({
                                icon: "error",
                                title: "Algunos insumos aún no se pudieron agregar",
                                text: "Verifica manualmente los insumos que quedaron pendientes.",
                                confirmButtonText: "Aceptar"
                            });
                        } else {
                            Swal.fire({
                                icon: "success",
                                title: "¡Todos los insumos fueron agregados!",
                                confirmButtonText: "Cerrar"
                            });
                        }
                    } else {
                        console.log("❌ El usuario canceló el reintento.");
                    }
                });
            } else {
                // Mostrar alerta positiva cuando no hay errores tras autollenado
                Swal.fire({
                    icon: "success",
                    title: "Autollenado completo",
                    text: "✅ Todos los insumos fueron ingresados y verificados correctamente.",
                    confirmButtonText: "Cerrar"
                });
                console.log("✅ Todos los insumos fueron ingresados exitosamente.");
            }
        } else {
            console.log("❌ El usuario canceló la acción.");
        }
    });
}

// Función utilitaria para agregar filas genéricamente
/**
 * Agrega filas a una tabla haciendo clic en el botón "+" una cantidad de veces.
 * @param {string} tablaSelector - Selector CSS del botón "+" correspondiente a la tabla.
 * @param {number} cantidad - Número de veces a hacer clic.
 * @param {function} callback - Función a llamar al finalizar.
 * @param {string} tipoLog - (opcional) Tipo de insumo para log.
 */
function agregarFilas(tablaSelector, cantidad, callback, tipoLog = "") {
    const botonAgregar = document.querySelector(tablaSelector);
    if (!botonAgregar) {
        console.warn(`⚠️ No se encontró el botón '+' para agregar (${tipoLog || tablaSelector})`);
        if (callback && typeof callback === "function") setTimeout(callback, 100);
        return;
    }
    if (cantidad <= 0) {
        if (callback && typeof callback === "function") setTimeout(callback, 100);
        return;
    }
    if (tipoLog) {
        console.log(`➕ Haciendo clic en el botón "+" de ${tipoLog} ${cantidad} veces...`);
    } else {
        console.log(`➕ Haciendo clic en el botón "+" (${tablaSelector}) ${cantidad} veces...`);
    }
    let clicksRealizados = 0;
    const interval = setInterval(() => {
        if (clicksRealizados < cantidad) {
            botonAgregar.click();
            clicksRealizados++;
        } else {
            clearInterval(interval);
            if (tipoLog) {
                console.log(`🎯 Todos los elementos de ${tipoLog} han sido agregados.`);
            }
            if (callback && typeof callback === "function") {
                setTimeout(callback, 500); // esperar a que se rendericen las filas
            }
        }
    }, 400);
}

async function completarDatosEquipos(equipos) {
    console.log("🧠 Completando datos de equipos en la tabla...");

    const filasExistentes = document.querySelectorAll("#seriales-input-derecho .multiple-input-list__item").length;
    let filaDestino = filasExistentes - equipos.length;

    for (let index = 0; index < equipos.length; index++) {
        const filaActual = filaDestino + index;

        // Validación defensiva
        if (!equipos[index] || typeof equipos[index].nombre !== 'string' || equipos[index].nombre.trim() === '') {
            console.warn(`⚠️ El equipo en la fila ${index + 1} no tiene nombre válido:`, equipos[index]);
            continue;
        }

        const nombre = equipos[index].codigo.trim().toUpperCase();
        console.log(`🧾 Preparando búsqueda para "${nombre}"`);

        const select2ContainerId = `#select2-hccirugiahospitalizacion-derechos-${filaActual}-derecho-container`;

        try {
            console.log(`🔍 Buscando y seleccionando "${nombre}" en fila ${index + 1}...`);
            await Select2Utils.hacerClick(select2ContainerId);
            await Select2Utils.buscar(nombre);
            await Select2Utils.seleccionar();
            // Validación de selección efectiva
            const contenedorSeleccion = document.querySelector(select2ContainerId);
            if (!contenedorSeleccion || !contenedorSeleccion.textContent.trim().toUpperCase().includes(nombre)) {
                console.warn(`⚠️ El equipo "${nombre}" no fue realmente seleccionado en fila ${index + 1}`);
                window.erroresInsumosNoIngresados.push({
                    tipo: "equipo",
                    nombre: nombre,
                    fila: index + 1,
                    error: "No se pudo seleccionar correctamente en el Select2"
                });
                continue;
            }
            console.log(`✅ "${nombre}" seleccionado correctamente`);
        } catch (error) {
            console.error(`❌ Error al seleccionar "${nombre}" en la fila ${index + 1}:`, error);
            window.erroresInsumosNoIngresados.push({
                tipo: "equipo", nombre: nombre, fila: index + 1, error: error.toString()
            });
        }
    }
}

async function completarDatosAnestesia(anestesiaArray) {
    console.log("🧠 Completando datos de anestesia en la tabla...");
    const filasExistentes = document.querySelectorAll("#seriales-input-insumos .multiple-input-list__item").length;
    let filaDestino = filasExistentes - anestesiaArray.length;

    for (let index = 0; index < anestesiaArray.length; index++) {
        const item = anestesiaArray[index];

        if (!item || typeof item.nombre !== 'string' || item.nombre.trim() === '') {
            console.warn(`⚠️ El insumo de anestesia en la fila ${index + 1} no tiene nombre válido:`, item);
            continue;
        }

        const nombre = item.codigo.trim().toUpperCase();
        console.log(`🧾 Preparando búsqueda para anestesia "${nombre}" en fila ${index + 1}...`);

        const filaActual = filaDestino + index;
        const select2ContainerId = `#select2-hccirugiahospitalizacion-insumos-${filaActual}-insumo-container`;

        try {
            await Select2Utils.hacerClick(select2ContainerId);
            await Select2Utils.buscar(nombre);
            await Select2Utils.seleccionar();
            // Validación de selección efectiva
            const contenedorSeleccion = document.querySelector(select2ContainerId);
            if (!contenedorSeleccion || !contenedorSeleccion.textContent.trim().toUpperCase().includes(nombre)) {
                console.warn(`⚠️ El insumo "${nombre}" no fue realmente seleccionado en fila ${index + 1}`);
                window.erroresInsumosNoIngresados.push({
                    tipo: "anestesia",
                    nombre: nombre,
                    fila: index + 1,
                    error: "No se pudo seleccionar correctamente en el Select2"
                });
                continue;
            }
            const inputCantidad = document.querySelector(`#hccirugiahospitalizacion-insumos-${filaActual}-cantidad-insumos`);
            if (inputCantidad) {
                inputCantidad.value = item.cantidad;
                const eventChange = new Event("change", {bubbles: true});
                inputCantidad.dispatchEvent(eventChange);
                console.log(`✏️ Cantidad "${item.cantidad}" establecida en fila ${index + 1}`);
            } else {
                console.warn(`⚠️ Campo de cantidad no encontrado para la fila ${index + 1}`);
            }
            console.log(`✅ "${nombre}" seleccionado correctamente en anestesia`);
        } catch (error) {
            console.error(`❌ Error al seleccionar anestesia "${nombre}" en la fila ${index + 1}:`, error);
            window.erroresInsumosNoIngresados.push({
                tipo: "anestesia", nombre: nombre, fila: index + 1, error: error.toString()
            });
        }
    }
}

async function completarDatosOxigeno(codigoOxigeno = "911111", fila = 0) {
    console.log("🧠 Completando datos de oxígeno en la fila...");

    const select2ContainerId = `#select2-hccirugiahospitalizacion-oxigeno-${fila}-oxigeno_id-container`;

    try {
        console.log(`🔍 Buscando y seleccionando código de oxígeno "${codigoOxigeno}" en fila ${fila}...`);
        await Select2Utils.hacerClick(select2ContainerId);
        await Select2Utils.buscar(codigoOxigeno);
        await Select2Utils.seleccionar();
        // Validación de selección efectiva
        const contenedorSeleccion = document.querySelector(select2ContainerId);
        if (!contenedorSeleccion || !contenedorSeleccion.textContent.trim().toUpperCase().includes(codigoOxigeno)) {
            console.warn(`⚠️ El oxígeno "${codigoOxigeno}" no fue realmente seleccionado en fila ${fila + 1}`);
            window.erroresInsumosNoIngresados.push({
                tipo: "oxigeno",
                nombre: codigoOxigeno,
                fila: fila + 1,
                error: "No se pudo seleccionar correctamente en el Select2"
            });
            return;
        }
        // Establecer duración después de seleccionar el código
        setTimeout(() => {
            try {
                const duracion = window.duracionOxigenoGlobal || "01:00";
                const [horas, minutos] = duracion.split(":").map(Number);
                const tiempoEnHoras = horas + (minutos / 60);

                const inputTiempo = document.querySelector(`#hccirugiahospitalizacion-oxigeno-${fila}-tiempo`);
                if (inputTiempo) {
                    inputTiempo.value = tiempoEnHoras;
                    const eventChange = new Event("change", {bubbles: true});
                    inputTiempo.dispatchEvent(eventChange);
                    console.log(`⏱️ Tiempo de oxígeno establecido: ${tiempoEnHoras} horas`);
                } else {
                    console.warn(`⚠️ Campo de duración de oxígeno no encontrado para la fila ${fila}`);
                }

                // Añadido: establecer siempre el valor 3 en el campo de litros
                const inputLitros = document.querySelector(`#hccirugiahospitalizacion-oxigeno-${fila}-litros`);
                if (inputLitros) {
                    inputLitros.value = 3;
                    const eventChange = new Event("change", {bubbles: true});
                    inputLitros.dispatchEvent(eventChange);
                    console.log("💧 Litros de oxígeno establecidos en: 3");
                } else {
                    console.warn(`⚠️ Campo de litros de oxígeno no encontrado para la fila ${fila}`);
                }
            } catch (err) {
                console.error("❌ Error al establecer duración de oxígeno:", err);
            }
        }, 500);

        console.log("✅ Oxígeno por defecto agregado y configurado");
    } catch (error) {
        console.error(`❌ Error al completar datos de oxígeno:`, error);
        window.erroresInsumosNoIngresados.push({
            tipo: "oxigeno", nombre: codigoOxigeno, fila: fila, error: error.toString()
        });
    }
}

async function completarDatosTiempoAnestesia(codigoTiempoAnestesia = "999999", cantidadAsignar = 1, fila = 0) {
    const select2ContainerId = `#select2-hccirugiahospitalizacion-anestesia-${fila}-anestesia-container`;

    try {
        console.log(`🔍 Buscando y seleccionando código "${codigoTiempoAnestesia}" en fila ${fila}...`);
        await Select2Utils.hacerClick(select2ContainerId);
        await Select2Utils.buscar(codigoTiempoAnestesia);
        await Select2Utils.seleccionar();

        const contenedorSeleccion = document.querySelector(select2ContainerId);
        if (!contenedorSeleccion || !contenedorSeleccion.textContent.trim().includes(codigoTiempoAnestesia)) {
            console.warn(`⚠️ El código "${codigoTiempoAnestesia}" no fue realmente seleccionado en fila ${fila + 1}`);
            window.erroresInsumosNoIngresados.push({
                tipo: "tiempo_anestesia",
                nombre: codigoTiempoAnestesia,
                fila: fila + 1,
                error: "No seleccionado correctamente en Select2"
            });
            return;
        }

        const inputTiempo = document.querySelector(`#hccirugiahospitalizacion-anestesia-${fila}-tiempo`);
        if (inputTiempo) {
            inputTiempo.value = cantidadAsignar;
            inputTiempo.dispatchEvent(new Event("change", {bubbles: true}));
            console.log(`⏱️ Tiempo de Anestesia (${codigoTiempoAnestesia}) establecido: ${cantidadAsignar} bloques de 15 minutos`);
        }
    } catch (error) {
        console.error(`❌ Error al completar anestesia:`, error);
        window.erroresInsumosNoIngresados.push({
            tipo: "tiempo_anestesia",
            nombre: codigoTiempoAnestesia,
            fila: fila,
            error: error.toString()
        });
    }
}


