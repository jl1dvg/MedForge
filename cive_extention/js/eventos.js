window.__civeEventosRetryCount = window.__civeEventosRetryCount || 0;
window.__civeEventosListos = window.__civeEventosListos || false;
const TELEMETRIA_STORAGE_KEY = 'civeExtensionTelemetry';

function registrarAccion(nombre) {
    try {
        const payload = JSON.parse(localStorage.getItem(TELEMETRIA_STORAGE_KEY) || '{"acciones":[]}');
        const acciones = Array.isArray(payload.acciones) ? payload.acciones : [];
        acciones.push({nombre, ts: Date.now()});
        // Conserva solo las últimas 50 acciones para no crecer indefinidamente
        payload.acciones = acciones.slice(-50);
        localStorage.setItem(TELEMETRIA_STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {
        console.warn('No se pudo registrar telemetría:', e);
    }
}

window.inicializarEventos = function () {
    if (window.__civeEventosListos) {
        return;
    }

    const popupRoot = document.getElementById('floatingPopup');
    if (!popupRoot) {
        if (window.__civeEventosRetryCount < 5) {
            window.__civeEventosRetryCount += 1;
            setTimeout(window.inicializarEventos, 400);
        } else {
            console.info('CIVE Extension: UI no encontrada, se omite el enlace de eventos en esta vista.');
        }
        return;
    }

    window.__civeEventosListos = true;
    console.log("Inicializando eventos de los botones...");

    const eventos = [
        {
            id: "btnProtocolos", evento: () => {
                console.log("Botón Protocolos clickeado");
                registrarAccion('protocolos');
                mostrarSeccion("protocolos");
                cargarProtocolos();
            }
        },
        {
            id: "btnRecetas", evento: () => {
                console.log("Botón Recetas clickeado");
                registrarAccion('recetas');
                mostrarSeccion("recetas");
                cargarRecetas();
            }
        },
        {
            id: "btnConsulta", evento: () => {
                console.log("Botón Consulta clickeado");
                registrarAccion('consulta');
                mostrarSeccion("consulta");
            }
        },
        {
            id: "btnCirugia", evento: () => {
                console.log("Botón Cirugía clickeado");
                registrarAccion('cirugia');
                mostrarSeccion("cirugia");
                if (window.inicializarCirugiaSection) window.inicializarCirugiaSection();
            }
        },
        {
            id: "btnConsultaAnterior", evento: () => {
                console.log("Botón Consulta Anterior clickeado");
                registrarAccion('consulta_anterior');
                chrome.runtime.sendMessage({action: "consultaAnterior"});
            }
        },
        {
            id: "btnPOP", evento: () => {
                console.log("Botón POP clickeado");
                registrarAccion('control_pop');
                chrome.runtime.sendMessage({action: "ejecutarPopEnPagina"});
            }
        },
        {
            id: "btnBackExamenes", evento: () => {
                console.log("Botón Back Exámenes clickeado");
                mostrarSeccion("inicio");
            }
        },
        {
            id: "btnBackProtocolos", evento: () => {
                console.log("Botón Back Protocolos clickeado");
                mostrarSeccion("inicio");
            }
        },
        {
            id: "btnBackRecetas", evento: () => {
                console.log("Botón Back Recetas clickeado");
                mostrarSeccion("inicio");
            }
        },
        {
            id: "btnBackProcedimientos", evento: () => {
                console.log("Botón Back Procedimientos clickeado");
                registrarAccion('back_procedimientos');
                mostrarSeccion("protocolos");
            }
        },
        {
            id: "btnBackConsulta", evento: () => {
                console.log("Botón Back Consulta clickeado");
                registrarAccion('back_consulta');
                mostrarSeccion("inicio");
            }
        },
        {
            id: "btnBackCirugia", evento: () => {
                console.log("Botón Back Cirugía clickeado");
                registrarAccion('back_cirugia');
                mostrarSeccion("inicio");
            }
        },
        {
            id: "btnGeneratePDF", evento: () => {
                console.log("Botón Generar PDF clickeado");
                registrarAccion('generar_pdf');
                chrome.runtime.sendMessage({action: "checkSubscription"}, (response) => {
                    if (response.success) {
                        generatePDF();
                    } else {
                        const medforgeOrigin = (window.CiveApiClient && typeof window.CiveApiClient.apiOrigin === 'function')
                            ? window.CiveApiClient.apiOrigin()
                            : (window.location && window.location.origin ? window.location.origin.replace(/\/$/, '') : 'https://cive.consulmed.me');
                        window.open(medforgeOrigin, "_blank");
                    }
                });
            }
        }
    ];

    const config = window.configCIVE || {};
    const examsEnabled = typeof config.isFeatureEnabled === 'function'
        ? config.isFeatureEnabled('examsButton', Boolean(config.ES_LOCAL))
        : Boolean(config.ES_LOCAL);

    if (examsEnabled) {
        eventos.push({
            id: "btnExamenes", evento: () => {
                console.log("Botón Exámenes clickeado");
                registrarAccion('examenes');
                mostrarSeccion("examenes");
                cargarExamenes();
            }
        });
    }

    eventos.forEach(({id, evento}) => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener("click", evento);
        }
    });

    const filtros = [
        {id: "searchProtocolos", handler: aplicarFiltroProtocolos},
        {id: "searchProcedimientos", handler: aplicarFiltroProcedimientos},
        {id: "searchRecetas", handler: aplicarFiltroRecetas},
    ];

    filtros.forEach(({id, handler}) => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener("input", (event) => handler(event.target.value));
            input.addEventListener("search", (event) => handler(event.target.value));
            input.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    handler(event.target.value);
                }
            });
        }
    });

    const btnConsultarCirugia = document.getElementById("btnConsultarCirugia");
    if (btnConsultarCirugia) {
        btnConsultarCirugia.addEventListener("click", () => {
            registrarAccion('consultar_cirugia');
            const hc = document.getElementById('inputHcCirugia')?.value || '';
            if (window.consultarCirugias) window.consultarCirugias(hc);
        });
    }

    const inputHcCirugia = document.getElementById("inputHcCirugia");
    if (inputHcCirugia) {
        inputHcCirugia.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                const hc = inputHcCirugia.value || '';
                registrarAccion('consultar_cirugia_enter');
                if (window.consultarCirugias) window.consultarCirugias(hc);
            }
        });
    }

    console.log("Eventos de botones inicializados.");
};
