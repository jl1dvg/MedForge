window.inicializarEventos = function () {
    console.log("Inicializando eventos de los botones...");

    const eventos = [
        {
            id: "btnProtocolos", evento: () => {
                console.log("Botón Protocolos clickeado");
                mostrarSeccion("protocolos");
                cargarProtocolos();
            }
        },
        {
            id: "btnRecetas", evento: () => {
                console.log("Botón Recetas clickeado");
                mostrarSeccion("recetas");
                cargarRecetas();
            }
        },
        {
            id: "btnConsulta", evento: () => {
                console.log("Botón Consulta clickeado");
                mostrarSeccion("consulta");
            }
        },
        {
            id: "btnConsultaAnterior", evento: () => {
                console.log("Botón Consulta Anterior clickeado");
                chrome.runtime.sendMessage({action: "consultaAnterior"});
            }
        },
        {
            id: "btnPOP", evento: () => {
                console.log("Botón POP clickeado");
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
                mostrarSeccion("protocolos");
            }
        },
        {
            id: "btnBackConsulta", evento: () => {
                console.log("Botón Back Consulta clickeado");
                mostrarSeccion("inicio");
            }
        },
        {
            id: "btnGeneratePDF", evento: () => {
                console.log("Botón Generar PDF clickeado");
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
                mostrarSeccion("examenes");
                cargarExamenes();
            }
        });
    }

    eventos.forEach(({id, evento}) => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener("click", evento);
        } else {
            console.warn(`Elemento ${id} no encontrado.`);
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

    console.log("Eventos de botones inicializados.");
};
