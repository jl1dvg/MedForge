// ==== Helpers de contexto de extensión ====
function isExtensionContextActive() {
    try {
        return !!(typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.id);
    } catch (e) {
        return false;
    }
}

function safeGetURL(path) {
    if (isExtensionContextActive()) {
        try {
            return chrome.runtime.getURL(path);
        } catch (e) {
            console.warn('getURL falló:', e);
        }
    }
    return null; // En este archivo preferimos no usar remoto para no romper postMessage
}

const MODAL_STYLE_ID = 'cive-exam-modal-style';

function ensureModalStyles() {
    if (document.getElementById(MODAL_STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = MODAL_STYLE_ID;
    style.innerHTML = `
    .cive-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(12, 18, 38, 0.55);
        backdrop-filter: blur(2px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 16px;
    }
    .cive-modal-window {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        min-width: 340px;
        max-width: 90vw;
        width: 600px;
        max-height: 92vh;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.25);
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    }
    .cive-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px 12px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    }
    .cive-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #f8fafc;
    }
    .cive-modal-kicker {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 11px;
        color: #94a3b8;
    }
    .cive-modal-close {
        background: none;
        border: none;
        color: #e2e8f0;
        font-size: 24px;
        cursor: pointer;
        line-height: 1;
        transition: transform 120ms ease, color 120ms ease;
    }
    .cive-modal-close:hover {
        color: #38bdf8;
        transform: scale(1.05);
    }
    .cive-modal-body {
        padding: 14px 20px 6px;
        overflow-y: auto;
        max-height: 65vh;
    }
    .cive-form-group {
        margin-bottom: 12px;
    }
    .cive-form-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        color: #cbd5e1;
    }
    .cive-input {
        width: 100%;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        background: rgba(15, 23, 42, 0.6);
        color: #e2e8f0;
        padding: 10px 12px;
        font-size: 14px;
        transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }
    .cive-input:focus {
        outline: none;
        border-color: #38bdf8;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
        background: rgba(15, 23, 42, 0.8);
    }
    .cive-modal-footer {
        padding: 12px 20px 18px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-top: 1px solid rgba(148, 163, 184, 0.2);
    }
    .cive-btn {
        border: 1px solid transparent;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 100ms ease, box-shadow 120ms ease, background 120ms ease, color 120ms ease;
    }
    .cive-btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35);
    }
    .cive-btn-primary {
        background: linear-gradient(120deg, #0ea5e9, #6366f1);
        color: #f8fafc;
        box-shadow: 0 10px 30px rgba(14, 165, 233, 0.25);
    }
    .cive-btn-primary:hover {
        transform: translateY(-1px);
    }
    .cive-btn-ghost {
        background: rgba(148, 163, 184, 0.15);
        color: #e2e8f0;
        border-color: rgba(148, 163, 184, 0.3);
    }
    .cive-btn-ghost:hover {
        background: rgba(148, 163, 184, 0.25);
    }
    .cive-iframe-shell {
        position: relative;
        padding: 10px 10px 16px;
    }
    .cive-iframe-shell iframe {
        width: 100%;
        height: 600px;
        border: none;
        border-radius: 12px;
        overflow: auto;
        background: #0b1120;
    }
    @media (max-width: 640px) {
        .cive-modal-window { width: 100%; }
        .cive-iframe-shell iframe { height: 70vh; }
    }
    `;
    document.head.appendChild(style);
}

function crearPopupFallbackOD_OI() {
    return new Promise((resolve) => {
        ensureModalStyles();
        const overlay = document.createElement('div');
        overlay.className = 'cive-modal-backdrop';

        const box = document.createElement('div');
        box.className = 'cive-modal-window';
        box.innerHTML = `
      <div class="cive-modal-header">
        <div>
          <p class="cive-modal-kicker">Exámenes</p>
          <h3>Ingresar recomendaciones</h3>
        </div>
        <button id="xClose" class="cive-modal-close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="cive-modal-body">
        <div class="cive-form-group">
          <label for="odTxt">OD</label>
          <textarea id="odTxt" rows="3" class="cive-input"></textarea>
        </div>
        <div class="cive-form-group">
          <label for="oiTxt">OI</label>
          <textarea id="oiTxt" rows="3" class="cive-input"></textarea>
        </div>
      </div>
      <div class="cive-modal-footer">
        <button id="cancelBtn" class="cive-btn cive-btn-ghost" type="button">Cancelar</button>
        <button id="okBtn" class="cive-btn cive-btn-primary" type="button">Aceptar</button>
      </div>
    `;
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        const close = (data = null) => {
            document.body.removeChild(overlay);
            resolve(data);
        };
        box.querySelector('#xClose').addEventListener('click', () => close(null));
        box.querySelector('#cancelBtn').addEventListener('click', () => close(null));
        box.querySelector('#okBtn').addEventListener('click', () => {
            const OD = box.querySelector('#odTxt').value || '';
            const OI = box.querySelector('#oiTxt').value || '';
            close({OD, OI});
        });
    });
}

// ==== Fin helpers ====

function ejecutarEnPagina(item) {
    console.log("Datos recibidos en ejecutarEnPagina:", item);

    function mostrarPopup(url) {
        return new Promise((resolve) => {
            // Si el contexto de la extensión no está activo (MV3 descargado/reload del paquete), usar fallback de formulario simple
            if (!isExtensionContextActive()) {
                console.warn('Contexto de extensión inválido. Usando popup fallback (OD/OI) sin iframe.');
                return crearPopupFallbackOD_OI().then(resolve);
            }

            ensureModalStyles();

            const popup = document.createElement('div');
            popup.className = 'cive-modal-backdrop';

            const popupURL = safeGetURL(url);
            if (!popupURL) {
                console.warn('No se pudo obtener URL interna de la extensión. Usando popup fallback de texto.');
                return crearPopupFallbackOD_OI().then(resolve);
            }

            popup.innerHTML = `
        <div class="cive-modal-window">
          <div class="cive-modal-header">
            <div>
              <p class="cive-modal-kicker">Exámenes</p>
              <h3>Completar información</h3>
            </div>
            <button id="btnClose" class="cive-modal-close" aria-label="Cerrar">&times;</button>
          </div>
          <div class="cive-iframe-shell">
            <iframe class="content-panel-frame placeholder-frame" id="placeholder-dialog" src="${popupURL}"></iframe>
          </div>
        </div>
      `;
            document.body.appendChild(popup);

            function cerrarPopup() {
                document.body.removeChild(popup);
                window.removeEventListener('message', onMessage);
            }

            function onMessage(event) {
                // Validar el origen del mensaje solo si hay runtime; si no, ya habríamos usado el fallback
                const expectedOrigin = chrome && chrome.runtime ? chrome.runtime.getURL('/').slice(0, -1) : null;
                if (expectedOrigin && event.origin !== expectedOrigin) return;

                if (event.data && (event.data.OD !== undefined && event.data.OI !== undefined)) {
                    cerrarPopup();
                    resolve(event.data);
                } else if (event.data && event.data.close) {
                    cerrarPopup();
                    resolve(null);
                }
            }

            window.addEventListener('message', onMessage);
            popup.querySelector('#btnClose').addEventListener('click', () => {
                cerrarPopup();
                resolve(null);
            });
        });
    }

    function ejecutarTecnicos(item) {
        if (!Array.isArray(item.tecnicos)) return Promise.resolve();

        return item.tecnicos.reduce((promise, tecnico) => {
            return promise.then(() => {
                return hacerClickEnSelect2(tecnico.selector)
                    .then(() => establecerBusqueda(tecnico.selector, tecnico.funcion))
                    .then(() => seleccionarOpcion())
                    .then(() => hacerClickEnSelect2(tecnico.trabajador))
                    .then(() => establecerBusqueda(tecnico.trabajador, tecnico.nombre))
                    .then(() => seleccionarOpcion())
                    .catch(error => console.error(`Error procesando técnico ${tecnico.nombre}:`, error));
            });
        }, Promise.resolve());
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
                if (searchField) {
                    console.log('Estableciendo búsqueda:', valor);
                    searchField.value = valor;
                    const inputEvent = new Event('input', {
                        bubbles: true, cancelable: true
                    });
                    searchField.dispatchEvent(inputEvent);
                    setTimeout(() => resolve(searchField), 300); // Añadir un retraso para asegurar que los resultados se carguen
                } else if (attempts < maxAttempts) {
                    console.log(`Esperando campo de búsqueda del Select2... intento ${attempts + 1}`);
                    attempts++;
                    hacerClickEnSelect2(selector)
                        .then(() => {
                            setTimeout(searchForField, 300); // Espera y reintenta
                        })
                        .catch(error => reject(error));
                } else {
                    reject('El campo de búsqueda del Select2 no se encontró.');
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
                reject('El campo de búsqueda del Select2 no se encontró para seleccionar la opción.');
            }
        });
    }

    function hacerClickEnBotonTerminar() {
        return new Promise((resolve, reject) => {
            const botonTerminar = document.querySelector('button.btn.btn-success[onclick="guardarTerminar()"]');
            if (botonTerminar) {
                console.log('Haciendo clic en el botón "Terminar"');
                botonTerminar.click();
                resolve();
            } else {
                reject('El botón "Terminar" no se encontró.');
            }
        });
    }

    if (item.id === 'octno') {
        mostrarPopup('js/popup/popup.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = 'SE REALIZA TOMOGRAFIA CON PRUEBAS PROVOCATIVAS DE CAPA DE FIBRAS NERVIOSAS RETINALES CON TOMOGRAFO SPECTRALIS (HEIDELBERG ENGINEERING)'; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `\n${OD}\n`;
            }
            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `\n${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'eco') {
        mostrarPopup('js/eco/eco.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = 'SE REALIZA ESTUDIO CON EQUIPO EYE CUBED ELLEX DE ECOGRAFIA MODO B POR CONTACTO TRANSPALPEBRAL EN:\n    '; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `\nOD: ${OD}\n`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `\nOI: ${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'angulo') {
        mostrarPopup('js/angulo/angulo.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = 'SE REALIZA ESTUDIO DE TOMOGRAFIA CON PRUEBAS PROVOCATIVAS DE ANGULO IRIDOCORNEAL CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, VISUALIZANDO LA ESTRUCTURA ANGULAR.\n' + '\n' + 'APERTURA EN GRADOS DEL ANGULO IRIDOCORNEAL:'; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `\n${OD}\n`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `\n${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'octm') {
        mostrarPopup('js/octm/octm.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = 'SE REALIZA ESTUDIO DE TOMOGRAFIA CON PRUEBAS PROVOCATIVAS MACULAR CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, VISUALIZANDO LAS DIFERENTES CAPAS DE LA RETINA NEUROSENSORIAL, EPITELIO PIGMENTADO DE LA RETINA, MEMBRANA DE BRUCH Y COROIDES ANTERIOR DE ÁREA MACULAR. \n'; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `\n${OD}\n`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `\n${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'retino') {
        mostrarPopup('js/retino/retino.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = ''; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `EL ESTUDIO DE LAS FOTOGRAFIAS SE REALIZA CON EQUIPO OPTOS DAYTONA, OBTENIENDO IMAGENES SUGESTIVAS DE LOS SIGUIENTES PROBABLES DIAGNOSTICOS:

OD: ${OD}`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `

EL ESTUDIO DE LAS FOTOGRAFIAS SE REALIZA CON EQUIPO OPTOS DAYTONA, OBTENIENDO IMAGENES SUGESTIVAS DE LOS SIGUIENTES PROBABLES DIAGNOSTICOS:

OI: ${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'auto') {
        mostrarPopup('js/auto/auto.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = 'SE REALIZA ESTUDIO DE AUTOFLOURESCENCIA CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, VISUALIZANDO: \n'; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `\nOD: ${OD}\n`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `\nOI: ${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'angio') {
        mostrarPopup('js/angio/angio.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = 'SE REALIZA ESTUDIO DE ANGIOGRAFIA RETINAL CON FLUORESCEINA SÓDICA CON EQUIPO HEIDELBERG ENGINEERING MODELO SPECTRALIS CON SOFTWARE 6.7, PREVIO A INYECCION DE 5ML DE FLUORESCEINA SODICA AL 10% EN LA VENA DEL CODO VISUALIZANDO LAS DIFERENTES FASES DE LA CIRCULACION COROIDO RETINAL.  SE DOCUMENTA LAS FASES COROIDEA, ARTERIAL TEMPRANA, ARTERIOVENOSA, FASE VENOSA Y DE RECIRCULACION. \n'; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `\nOD: ${OD}\n`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `\nOI: ${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    } else if (item.id === 'cv') {
        mostrarPopup('js/cv/cv.html').then(({OD, OI}) => {
            const recomendaciones = document.getElementById('ordenexamen-0-recomendaciones');
            recomendaciones.value = ''; // Inicializa las recomendaciones

            // Recomendaciones para OD
            if (OD) {
                recomendaciones.value += `${OD}\n\n`;
            }

            // Recomendaciones para OI
            if (OI) {
                recomendaciones.value += `${OI}`;
            }

            ejecutarTecnicos(item)
                .then(() => hacerClickEnBotonTerminar())
                .catch(error => console.log('Error en la ejecución de examen:', error));
        });
    }
}
