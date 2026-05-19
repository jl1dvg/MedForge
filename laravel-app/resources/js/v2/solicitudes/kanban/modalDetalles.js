let prefacturaListenerAttached = false;
let handlersModulePromise = null;

function getAssetSuffix() {
    const config = window.__SOLICITUDES_V2_UI__ || {};
    const version = String(config.assetVersion || "").trim();
    return version ? `?v=${encodeURIComponent(version)}` : "";
}

function loadHandlersModule() {
    if (!handlersModulePromise) {
        handlersModulePromise = import(`./modalDetalles/handlers.js${getAssetSuffix()}`);
    }

    return handlersModulePromise;
}

export function inicializarModalDetalles() {
    if (prefacturaListenerAttached) {
        return;
    }

    prefacturaListenerAttached = true;
    document.addEventListener("click", (event) => {
        const target = event.target;
        loadHandlersModule()
            .then((module) => {
                module.handlePrefacturaClick({target});
            })
            .catch((error) => {
                console.error("No se pudo inicializar handlePrefacturaClick", error);
            });
    });
    document.addEventListener("click", (event) => {
        const target = event.target;
        loadHandlersModule()
            .then((module) => {
                module.handleContextualAction({target});
            })
            .catch((error) => {
                console.error("No se pudo inicializar handleContextualAction", error);
            });
    });
    document.addEventListener("click", (event) => {
        const target = event.target;
        loadHandlersModule()
            .then((module) => {
                module.handleRescrapeDerivacion({target});
            })
            .catch((error) => {
                console.error("No se pudo inicializar handleRescrapeDerivacion", error);
            });
    });
}
