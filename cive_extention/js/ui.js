window.inicializarUI = function () {
    // Crear botón flotante
    const button = document.createElement("button");
    button.id = "floatingButton";
    button.className = "actionable-icon";

    // Añadir la imagen del botón
    const img = document.createElement("img");
    img.src = chrome.runtime.getURL("icon.png"); // Ruta correcta de la imagen
    img.alt = "Icono Flotante";
    button.appendChild(img);

    // Crear el elemento de manejo para arrastrar
    const dragHandle = document.createElement("div");
    dragHandle.className = "drag-handle";
    button.appendChild(dragHandle);

    // Agregar el botón al documento
    document.body.appendChild(button);

    const botonExamenesHTML = window.configCIVE.ES_LOCAL
        ? `<div id="btnExamenes" class="grid-item"><i class="fas fa-notes-medical"></i> Exámenes</div>`
        : '';

    // Crear el contenedor del popup flotante
    const popup = document.createElement("div");
    popup.id = "floatingPopup";
    popup.innerHTML = `
        <div class="popup-content">
            <div class="popup-header">
                <span>Asistente CIVE</span>
                <button class="popup-close-btn" id="popupCloseBtn">&times;</button>
            </div>
            <div id="notificaciones" class="section active">
                <div id="contenedorNotificaciones"></div>
            </div>
            <div id="inicio" class="section active">
                <div class="grid-container">
                    ${botonExamenesHTML}
                    <div id="btnProtocolos" class="grid-item"><i class="fas fa-file-alt"></i> Protocolos</div>
                    <div id="btnConsulta" class="grid-item"><i class="fas fa-user-md"></i> Consulta</div>
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

    // Evento para abrir/cerrar el popup
    button.addEventListener("click", function () {
        popup.classList.toggle("active");
        console.log("Toggle popup, estado:", popup.classList.contains("active"));
    });

    // Evento para cerrar el popup con el botón (X)
    document.getElementById("popupCloseBtn").addEventListener("click", function () {
        popup.classList.remove("active");
    });

    // Cargar estilos dinámicamente
    const estilos = [
        {href: "css/floating_button.css"},
        {href: "css/floating_popup.css"},
        {href: "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"}
    ];

    estilos.forEach((estilo) => {
        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = estilo.href.startsWith("http") ? estilo.href : chrome.runtime.getURL(estilo.href);
        document.head.appendChild(link);
    });

    console.log("UI inicializada correctamente.");
};