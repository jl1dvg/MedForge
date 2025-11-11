let origenGuardado = null;

// 📦 Extraer datos de la tabla de procedimientos y mostrarlos como JSON
function extraerDatosProcedimientos() {
    const filas = document.querySelectorAll('#seriales-input-procedimientos .multiple-input-list__item');
    const procedimientos = [];

    filas.forEach((fila, index) => {
        const getText = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.textContent.trim() : null;
        };

        const getValue = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.value.trim() : null;
        };

        const procedimiento = {
            id: getValue(`#hccirugiahospitalizacion-procedimientos-${index}-id`),
            procInterno: getText(`#select2-hccirugiahospitalizacion-procedimientos-${index}-procinterno-container`),
            //procPorcentaje: getValue(`#hccirugiahospitalizacion-procedimientos-${index}-procporcentaje`),
            //secPorcentaje: getValue(`#hccirugiahospitalizacion-procedimientos-${index}-secporcentaje`),
            // procAfiliacion: getText(`#select2-hccirugiahospitalizacion-procedimientos-${index}-procafiliacion-container`),
            precioBase: getValue(`#hccirugiahospitalizacion-procedimientos-${index}-preciobase`),
            //precio: getValue(`#hccirugiahospitalizacion-procedimientos-${index}-precio`)
        };

        // Descomponer procAfiliacion en código, detalle y precio
        (() => {
            const texto = getText(`#select2-hccirugiahospitalizacion-procedimientos-${index}-procafiliacion-container`);
            if (texto && texto.includes(" - ")) {
                const partes = texto.split(" - ").map(p => p.trim());
                procedimiento.procCodigo = partes[0];
                procedimiento.procDetalle = partes.slice(1, -1).join(" - ");
                procedimiento.procPrecio = partes[partes.length - 1];
            } else {
                procedimiento.procCodigo = "";
                procedimiento.procDetalle = "";
                procedimiento.procPrecio = "";
            }
        })();

        // Ignorar si no hay código o procInterno vacío
        if (procedimiento.procInterno && procedimiento.procInterno !== 'SELECCIONE') {
            procedimientos.push(procedimiento);
        }
    });

    // Obtener hcNumber y formId antes del log
    const hcNumber = document.querySelector('#numero-historia-clinica')?.value.trim() || null;

    let formId = null;
    document.querySelectorAll('a[href*="imprimir-cotizacion"]').forEach(link => {
        const urlParams = new URLSearchParams(new URL(link.href, window.location.origin).search);
        if (urlParams.has("idSolicitud")) {
            formId = urlParams.get("idSolicitud");
        }
    });

    const payload = {
        hcNumber: hcNumber,
        form_id: formId,
        procedimientos: procedimientos
    };

    // Añadir derechos al payload usando la nueva función
    payload.derechos = extraerDatosDerechos();
    payload.insumos = extraerDatosInsumos();
    // ➕ Añadir oxígeno y anestesia tiempo al payload
    payload.oxigeno = extraerDatosOxigenoFormulario();
    payload.anestesiaTiempo = extraerDatosAnestesiaFormulario();

    console.log("📦 Payload completo para enviar:", JSON.stringify(payload, null, 2));
    enviarBillingAlAPI(payload);
    if (origenGuardado !== 'terminar') {
        Swal.fire({
            icon: "question",
            title: "¿Desea descargar la prefactura?",
            showCancelButton: true,
            confirmButtonText: 'Sí, descargar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(`https://asistentecive.consulmed.me/views/billing/descargar_excel.php?form_id=${formId}`, '_blank');
            } else {
                console.log("❌ Descarga cancelada.");
            }
        });
    }
}

// 📦 Extraer datos de oxígeno y retornarlos como array
function extraerDatosOxigenoFormulario() {
    const filas = document.querySelectorAll('#seriales-input-oxigeno .multiple-input-list__item');
    const oxigenos = [];

    filas.forEach((fila, index) => {
        const getValue = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.value.trim() : null;
        };

        const oxigeno = {
            codigo: (() => {
                const texto = fila.querySelector(`#select2-hccirugiahospitalizacion-oxigeno-${index}-oxigeno_id-container`)?.textContent.trim();
                if (texto && texto.includes(" - ")) {
                    const partes = texto.split(" - ");
                    return partes[0].trim();
                }
                return "";
            })(),
            nombre: (() => {
                const texto = fila.querySelector(`#select2-hccirugiahospitalizacion-oxigeno-${index}-oxigeno_id-container`)?.textContent.trim();
                if (texto && texto.includes(" - ")) {
                    const partes = texto.split(" - ");
                    return partes.slice(1).join(" - ").trim();
                }
                return "";
            })(),
            tiempo: getValue(`#hccirugiahospitalizacion-oxigeno-${index}-tiempo`),
            litros: getValue(`#hccirugiahospitalizacion-oxigeno-${index}-litros`),
            valor1: getValue(`#hccirugiahospitalizacion-oxigeno-${index}-valor1`),
            valor2: getValue(`#hccirugiahospitalizacion-oxigeno-${index}-valor2`),
            precio: getValue(`#hccirugiahospitalizacion-oxigeno-${index}-precio-varios`)
        };

        if (oxigeno.codigo) {
            oxigenos.push(oxigeno);
        }
    });

    return oxigenos;
}

// 📦 Extraer datos de anestesia (modificador) y retornarlos como array
function extraerDatosAnestesiaFormulario() {
    const filas = document.querySelectorAll('#seriales-input-anestesia .multiple-input-list__item');
    const anestesias = [];

    filas.forEach((fila, index) => {
        const getValue = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.value.trim() : null;
        };

        // Extraer código y nombre (detalle) del campo select2
        const parsed = (() => {
            const texto = fila.querySelector(`#select2-hccirugiahospitalizacion-anestesia-${index}-anestesia-container`)?.textContent.trim();
            if (texto && texto.includes(" - ")) {
                const partes = texto.split(" - ");
                return {
                    codigo: partes[0].trim(),
                    nombre: partes.slice(1).join(" - ").trim()
                };
            }
            return {codigo: "", nombre: ""};
        })();

        const anestesia = {
            codigo: parsed.codigo,
            nombre: parsed.nombre,
            tiempo: getValue(`#hccirugiahospitalizacion-anestesia-${index}-tiempo`),
            valor2: getValue(`#hccirugiahospitalizacion-anestesia-${index}-valor2`),
            precio: getValue(`#hccirugiahospitalizacion-anestesia-${index}-precio-varios`)
        };

        if (anestesia.codigo) {
            anestesias.push(anestesia);
        }
    });

    return anestesias;
}

// 📦 Extraer datos de la tabla de derechos y retornarlos como array
function extraerDatosDerechos() {
    const filas = document.querySelectorAll('#seriales-input-derecho .multiple-input-list__item');
    const derechos = [];

    filas.forEach((fila, index) => {
        const getText = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.textContent.trim() : null;
        };

        const getValue = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.value.trim() : null;
        };

        const derecho = {
            id: getValue(`#hccirugiahospitalizacion-derechos-${index}-id`),
            cantidad: getValue(`#hccirugiahospitalizacion-derechos-${index}-cantidad-derechos`),
            //fecha: getValue(`#hccirugiahospitalizacion-derechos-${index}-fecha`),
            //porcentaje: document.querySelector(`#hccirugiahospitalizacion-derechos-${index}-porcentaje`)?.checked ? 1 : 0,
            //descuento: getValue(`#hccirugiahospitalizacion-derechos-${index}-descuento-derechos`),
            //precio: getValue(`#hccirugiahospitalizacion-derechos-${index}-precio-derechos`),
            //subtotal: getValue(`#hccirugiahospitalizacion-derechos-${index}-subtotal-derechos`),
            iva: getValue(`#hccirugiahospitalizacion-derechos-${index}-iva-derechos`)
        };

        // Descomponer derechoAfiliacion en código, detalle y precio
        (() => {
            const texto = getText(`#select2-hccirugiahospitalizacion-derechos-${index}-derechoafiliacion-container`);
            if (texto && texto.includes(" - ")) {
                const partes = texto.split(" - ").map(p => p.trim());
                derecho.codigo = partes[0];
                derecho.detalle = partes.slice(1, -1).join(" - ");
                derecho.precioAfiliacion = partes[partes.length - 1];
            } else {
                derecho.codigo = "";
                derecho.detalle = "";
                derecho.precioAfiliacion = "";
            }
        })();

        // Solo agregar si hay código
        if (derecho.codigo) {
            derechos.push(derecho);
        }
    });

    return derechos;
}

// 📦 Extraer datos de la tabla de insumos y retornarlos como array
function extraerDatosInsumos() {
    const filas = document.querySelectorAll('#seriales-input-insumos .multiple-input-list__item');
    const insumos = [];

    filas.forEach((fila, index) => {
        const getText = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.textContent.trim() : null;
        };

        const getValue = (selector) => {
            const el = fila.querySelector(selector);
            return el ? el.value.trim() : null;
        };

        const insumo = {
            id: getValue(`#hccirugiahospitalizacion-insumos-${index}-id`),
            cantidad: getValue(`#hccirugiahospitalizacion-insumos-${index}-cantidad-insumos`),
            //descuento: getValue(`#hccirugiahospitalizacion-insumos-${index}-descuento-insumos`),
            precio: getValue(`#hccirugiahospitalizacion-insumos-${index}-precio-insumos`),
            //subtotal: getValue(`#hccirugiahospitalizacion-insumos-${index}-subtotal-insumos`),
            iva: getValue(`#hccirugiahospitalizacion-insumos-${index}-iva-insumos`)
        };

        // Descomponer campo insumo para obtener código, nombre y precio si aplica
        (() => {
            const texto = getText(`#select2-hccirugiahospitalizacion-insumos-${index}-insumo-container`);
            if (texto && texto.includes(" - ")) {
                const partes = texto.split(" - ").map(p => p.trim());
                insumo.codigo = partes[0];

                // Eliminar etiquetas como MEDICAMENTOS o INSUMOS si están presentes
                const nombrePartes = partes.slice(1).filter(p => !["MEDICAMENTOS", "INSUMOS"].includes(p.toUpperCase()));
                insumo.nombre = nombrePartes.join(" - ").replace(/\(.*?\)$/, "").trim();

                // IVA como booleano
                insumo.iva = parseFloat(getValue(`#hccirugiahospitalizacion-insumos-${index}-iva-insumos`)) > 0 ? 1 : 0;
            } else {
                insumo.codigo = "";
                insumo.nombre = "";
                insumo.iva = "";
            }
        })();

        if (insumo.codigo) {
            insumos.push(insumo);
        }
    });

    return insumos;
}

function enviarProcedimientosAlAPI(payload) {
    fetch("https://asistentecive.consulmed.me/api/procedimientos/guardar.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: "success",
                    title: "Procedimientos guardados",
                    text: data.message || "Se enviaron correctamente al API."
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error en el envío",
                    text: data.message || "Ocurrió un error al enviar los datos."
                });
            }
        })
        .catch(error => {
            console.error("❌ Error en la solicitud:", error);
            Swal.fire({
                icon: "error",
                title: "Error de red",
                text: "No se pudo conectar al servidor."
            });
        });
}

function enviarBillingAlAPI(payload) {
    fetch("https://asistentecive.consulmed.me/api/billing/guardar_billing.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("✅ Billing guardado:", data.message || "Guardado con éxito.");
            } else {
                console.error("❌ Error al guardar billing:", data.message || "Error desconocido.");
            }
        })
        .catch(error => {
            console.error("❌ Error de red al enviar billing:", error);
        });
}

// Mock de exportación a Excel de procedimientos
function exportarProcedimientosAExcel(data) {
    console.log("📝 Procedimientos para exportar a Excel:", data);
    // Aquí podrías implementar la lógica real de exportación
}

// 🟡 Nuevo módulo de extracción de procedimientos al guardar
window.detectarProcedimientosAlGuardar = () => {
    console.log("🟡 Observador para botón Guardar (admisión) activado...");

    const intentosMaximos = 15;
    let intentos = 0;

    const buscarBoton = setInterval(() => {
        const btnGuardar = document.getElementById("btn-guardar-admision");
        intentos++;

        if (btnGuardar) {
            clearInterval(buscarBoton);
            console.log("✅ Botón Guardar encontrado. Se agregará interceptación...");

            // Clonamos el botón original para capturar antes de su acción
            const clon = btnGuardar.cloneNode(true);
            btnGuardar.parentNode.replaceChild(clon, btnGuardar);

            clon.addEventListener("click", (event) => {
                console.log("🟢 Botón Guardar interceptado. Capturando datos ANTES de cierre...");

                const hcNumber = document.querySelector('#numero-historia-clinica')?.value?.trim() || null;

                let formId = null;
                document.querySelectorAll('a[href*="imprimir-cotizacion"]').forEach(link => {
                    const urlParams = new URLSearchParams(new URL(link.href, window.location.origin).search);
                    if (urlParams.has("idSolicitud")) {
                        formId = urlParams.get("idSolicitud");
                    }
                });

                if (hcNumber && formId) {
                    console.log(`✅ Datos capturados justo antes de guardar:
                    - hcNumber: ${hcNumber}
                    - formId: ${formId}`);

                    origenGuardado = 'guardar';
                    extraerDatosProcedimientos(); // Esto mostrará también el SweetAlert

                    // Luego continuamos con el evento original
                    setTimeout(() => {
                        clon.setAttribute("disabled", true); // Evita doble clic
                        btnGuardar.click(); // Disparo original si hace falta
                        origenGuardado = null;
                    }, 300);
                } else {
                    console.warn("⚠️ No se pudieron capturar los datos antes de que se cierre el modal.");
                }

                event.preventDefault(); // Impedimos el cierre inmediato
            });
        } else if (intentos >= intentosMaximos) {
            clearInterval(buscarBoton);
            //console.warn("❌ No se encontró el botón Guardar admisión.");
        }
    }, 500);
};