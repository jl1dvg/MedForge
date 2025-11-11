// ==== Helpers para contexto de extensión (MV3) ====
function isExtensionContextActive() {
    try {
        return !!(typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.id);
    } catch (e) {
        return false;
    }
}

function safeGetURL(pathRelativo) {
    if (isExtensionContextActive()) {
        try {
            return chrome.runtime.getURL(pathRelativo);
        } catch (e) {
            console.warn('getURL falló, usando fallback remoto:', e);
        }
    }
    return `https://raw.githubusercontent.com/jl1dvg/cive_extention/main/${pathRelativo}`;
}

function safeSendMessage(message, {reintentos = 3, delayMs = 500} = {}) {
    return new Promise((resolve, reject) => {
        const intentar = (intento) => {
            if (!isExtensionContextActive()) {
                if (intento >= reintentos) {
                    return reject(new Error('Extension context invalidated (sendMessage).'));
                }
                return setTimeout(() => intentar(intento + 1), delayMs);
            }
            try {
                chrome.runtime.sendMessage(message, (response) => {
                    const err = chrome.runtime && chrome.runtime.lastError ? chrome.runtime.lastError : null;
                    if (err) {
                        if (intento < reintentos) return setTimeout(() => intentar(intento + 1), delayMs);
                        return reject(new Error(err.message || 'sendMessage error'));
                    }
                    resolve(response);
                });
            } catch (e) {
                if (intento < reintentos) return setTimeout(() => intentar(intento + 1), delayMs);
                reject(e);
            }
        };
        intentar(0);
    });
}
// ==== Fin helpers ====

const procedimientosCache = new Map();
const uiState = {
    procedimientos: [],
    procedimientosVisibles: [],
    categoriaProcedimientosActiva: '',
    recetas: [],
    recetasVisibles: [],
    categoriaRecetasActiva: '',
    examenes: [],
};

function getSectionElement(sectionId) {
    return document.getElementById(sectionId);
}

function getSectionStateElement(sectionId) {
    return document.getElementById(`estado-${sectionId}`);
}

function setContainerBusy(containerId, busy) {
    if (!containerId) return;
    const contenedor = document.getElementById(containerId);
    if (contenedor) {
        if (busy) {
            contenedor.setAttribute('aria-busy', 'true');
        } else {
            contenedor.removeAttribute('aria-busy');
        }
    }
}

function setSectionState(sectionId, {type, message = '', action, containerId} = {}) {
    const estado = getSectionStateElement(sectionId);
    if (!estado) return;

    estado.innerHTML = '';
    estado.removeAttribute('data-state');
    setContainerBusy(containerId, false);

    if (!type || type === 'ready') {
        return;
    }

    estado.setAttribute('data-state', type);
    if (type === 'loading') {
        setContainerBusy(containerId, true);
    }

    const wrapper = document.createElement('div');
    wrapper.className = `state-message state-${type}`;

    if (type === 'loading') {
        const spinner = document.createElement('span');
        spinner.className = 'state-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        wrapper.appendChild(spinner);
    }

    const text = document.createElement('span');
    text.className = 'state-text';
    text.textContent = message;
    wrapper.appendChild(text);

    if (action && typeof action.handler === 'function') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn-secondary state-action';
        button.textContent = action.label || 'Reintentar';
        button.addEventListener('click', action.handler);
        wrapper.appendChild(button);
    }

    estado.appendChild(wrapper);
}

function createCardButton({id, icon, label, description, onSelect}) {
    const button = document.createElement('button');
    button.type = 'button';
    button.id = id || '';
    button.className = 'shortcut-card';
    button.setAttribute('role', 'listitem');

    const iconSpan = document.createElement('span');
    iconSpan.className = 'shortcut-icon';
    iconSpan.innerHTML = `<i class="${icon}" aria-hidden="true"></i>`;

    const content = document.createElement('span');
    content.className = 'shortcut-content';

    const title = document.createElement('span');
    title.className = 'shortcut-label';
    title.textContent = label;

    content.appendChild(title);

    if (description) {
        const descriptionSpan = document.createElement('span');
        descriptionSpan.className = 'shortcut-description';
        descriptionSpan.textContent = description;
        content.appendChild(descriptionSpan);
    }

    button.appendChild(iconSpan);
    button.appendChild(content);

    if (typeof onSelect === 'function') {
        button.addEventListener('click', () => onSelect());
        button.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onSelect();
            }
        });
    }

    return button;
}

function renderCardList(containerId, items, {getIcon, getLabel, getDescription, getId, onSelect}) {
    const contenedor = document.getElementById(containerId);
    if (!contenedor) return;
    contenedor.innerHTML = '';

    items.forEach((item) => {
        const card = createCardButton({
            id: getId ? getId(item) : '',
            icon: getIcon ? getIcon(item) : 'fas fa-dot-circle',
            label: getLabel ? getLabel(item) : '',
            description: getDescription ? getDescription(item) : '',
            onSelect: () => onSelect(item),
        });
        contenedor.appendChild(card);
    });
}

function normalizarTexto(texto) {
    return (texto || '').toString().toLowerCase();
}

async function obtenerProcedimientosDesdeApi(afiliacion) {
    await (window.configCIVE ? window.configCIVE.ready : Promise.resolve());
    const clave = afiliacion && afiliacion !== '' ? afiliacion : '__general__';
    const ttl = window.configCIVE ? window.configCIVE.get('proceduresCacheTtlMs', 300000) : 300000;
    const cached = procedimientosCache.get(clave);
    if (cached && (Date.now() - cached.timestamp) < ttl) {
        return cached.data;
    }

    let data;
    try {
        data = await window.CiveApiClient.get('/procedimientos/listar.php', {
            query: {afiliacion},
            cacheKey: `procedimientos:${clave}`,
            cacheTtlMs: ttl,
            useCache: true,
        });
    } catch (error) {
        console.error('Error solicitando procedimientos al API:', error);
        throw error;
    }

    const procedimientos = Array.isArray(data?.procedimientos) ? data.procedimientos : [];
    procedimientosCache.set(clave, {data: procedimientos, timestamp: Date.now()});
    return procedimientos;
}

function obtenerAfiliacion() {
    const afiliacionElement = document.querySelector('.media-body p span b');
    if (afiliacionElement) {
        return afiliacionElement.innerText.trim().toUpperCase();
    }
    return '';
}

function actualizarBusquedaPlaceholder(inputId, textoBase) {
    const input = document.getElementById(inputId);
    if (input) {
        input.placeholder = textoBase;
    }
}

function focusFirstElement(section) {
    if (!section) return;
    const focusable = section.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable) {
        focusable.focus();
    }
}

function actualizarNavegacion(seccionId) {
    const popup = document.getElementById('floatingPopup');
    if (!popup) return;

    const secciones = popup.querySelectorAll('.popup-main .section');
    secciones.forEach((section) => {
        const isActive = section.id === seccionId;
        section.classList.toggle('active', isActive);
        section.setAttribute('aria-hidden', (!isActive).toString());
    });

    const navButtons = popup.querySelectorAll('.popup-nav .nav-item');
    navButtons.forEach((button) => {
        const target = button.getAttribute('data-target');
        const isActive = target === seccionId;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', String(isActive));
    });

    const section = document.getElementById(seccionId);
    focusFirstElement(section);
}

window.mostrarSeccion = function (seccionId) {
    actualizarNavegacion(seccionId);
};

async function cargarExamenes() {
    const contenedorId = 'contenedorExamenes';
    setSectionState('examenes', {
        type: 'loading',
        message: 'Cargando exámenes clínicos...',
        containerId: contenedorId,
    });

    try {
        const data = await cargarJSON(safeGetURL('data/examenes.json'));
        const examenes = Array.isArray(data?.examenes) ? data.examenes : [];
        uiState.examenes = examenes;

        if (!examenes.length) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('examenes', {
                type: 'empty',
                message: 'No se encontraron exámenes configurados.',
                containerId: contenedorId,
            });
            return;
        }

        renderCardList(contenedorId, examenes, {
            getId: (item) => `examen-${item.id}`,
            getIcon: () => 'fas fa-notes-medical',
            getLabel: (item) => item.cirugia,
            getDescription: (item) => item.descripcion || 'Aplicar examen en la historia clínica',
            onSelect: (item) => ejecutarExamenes(item.id),
        });
        setSectionState('examenes', {type: 'ready', containerId: contenedorId});
    } catch (error) {
        console.error('Error cargando JSON de examenes:', error);
        document.getElementById(contenedorId).innerHTML = '';
        setSectionState('examenes', {
            type: 'error',
            message: 'No fue posible cargar los exámenes. Revisa tu conexión.',
            containerId: contenedorId,
            action: {handler: cargarExamenes, label: 'Reintentar'},
        });
    }
}

async function cargarRecetas() {
    const contenedorId = 'contenedorRecetas';
    setSectionState('recetas', {
        type: 'loading',
        message: 'Sincronizando recetas con Medforge...',
        containerId: contenedorId,
    });

    try {
        const data = await cargarJSON(safeGetURL('data/recetas.json'));
        const recetas = Array.isArray(data?.receta) ? data.receta : [];
        uiState.recetas = recetas;
        uiState.recetasVisibles = recetas;
        uiState.categoriaRecetasActiva = '';

        const input = document.getElementById('searchRecetas');
        if (input) input.value = '';

        if (!recetas.length) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('recetas', {
                type: 'empty',
                message: 'No hay recetas disponibles para este paciente.',
                containerId: contenedorId,
            });
            return;
        }

        renderRecetasPorCategoria();
        setSectionState('recetas', {type: 'ready', containerId: contenedorId});
    } catch (error) {
        console.error('Error cargando JSON de recetas:', error);
        document.getElementById(contenedorId).innerHTML = '';
        setSectionState('recetas', {
            type: 'error',
            message: 'No pudimos descargar las recetas. Intenta nuevamente.',
            containerId: contenedorId,
            action: {handler: cargarRecetas},
        });
    }
}

function renderRecetasPorCategoria({query = ''} = {}) {
    const contenedorId = 'contenedorRecetas';
    const recetas = uiState.recetas;

    actualizarBusquedaPlaceholder('searchRecetas', 'Buscar receta');

    if (!query) {
        uiState.recetasVisibles = recetas;
        uiState.categoriaRecetasActiva = '';
    }

    if (!recetas.length) {
        document.getElementById(contenedorId).innerHTML = '';
        setSectionState('recetas', {
            type: 'empty',
            message: 'Aún no hay recetas configuradas.',
            containerId: contenedorId,
        });
        return;
    }

    if (query) {
        const filtro = normalizarTexto(query);
        const resultados = recetas.filter((receta) => {
            const texto = `${receta.categoria || ''} ${receta.cirugia || ''}`;
            return normalizarTexto(texto).includes(filtro);
        });

        if (!resultados.length) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('recetas', {
                type: 'empty',
                message: `No se encontraron recetas para "${query}".`,
                containerId: contenedorId,
            });
            return;
        }

        uiState.recetasVisibles = resultados;
        uiState.categoriaRecetasActiva = '';
        renderCardList(contenedorId, resultados, {
            getId: (item) => `receta-${item.id}`,
            getIcon: () => 'fas fa-prescription-bottle-alt',
            getLabel: (item) => item.cirugia,
            getDescription: (item) => item.categoria || 'Receta frecuente',
            onSelect: (item) => ejecutarReceta(item.id),
        });
        setSectionState('recetas', {type: 'ready', containerId: contenedorId});
        return;
    }

    const categoriasMap = recetas.reduce((acc, receta) => {
        const categoria = receta.categoria || 'General';
        if (!acc.has(categoria)) {
            acc.set(categoria, 0);
        }
        acc.set(categoria, acc.get(categoria) + 1);
        return acc;
    }, new Map());

    const categorias = Array.from(categoriasMap.entries()).map(([categoria, total]) => ({categoria, total}));

    renderCardList(contenedorId, categorias, {
        getId: (item) => `categoria-receta-${normalizarTexto(item.categoria)}`,
        getIcon: () => 'fas fa-layer-group',
        getLabel: (item) => item.categoria,
        getDescription: (item) => `${item.total} recetas disponibles`,
        onSelect: (item) => mostrarRecetasPorCategoria(item.categoria),
    });

    setSectionState('recetas', {type: 'ready', containerId: contenedorId});
}

function mostrarRecetasPorCategoria(categoria) {
    const recetas = uiState.recetas.filter((receta) => (receta.categoria || 'General') === categoria);
    uiState.recetasVisibles = recetas;
    uiState.categoriaRecetasActiva = categoria;
    actualizarBusquedaPlaceholder('searchRecetas', `Buscar en ${categoria}`);
    const input = document.getElementById('searchRecetas');
    if (input) input.value = '';

    const contenedorId = 'contenedorRecetas';
    if (!recetas.length) {
        document.getElementById(contenedorId).innerHTML = '';
        setSectionState('recetas', {
            type: 'empty',
            message: `No hay recetas guardadas en ${categoria}.`,
            containerId: contenedorId,
        });
        return;
    }

    renderCardList(contenedorId, recetas, {
        getId: (item) => `receta-${item.id}`,
        getIcon: () => 'fas fa-prescription-bottle-alt',
        getLabel: (item) => item.cirugia,
        getDescription: () => categoria,
        onSelect: (item) => ejecutarReceta(item.id),
    });
    setSectionState('recetas', {type: 'ready', containerId: contenedorId});
}

function aplicarFiltroRecetas(valor) {
    const query = valor.trim();
    if (!query && uiState.categoriaRecetasActiva) {
        mostrarRecetasPorCategoria(uiState.categoriaRecetasActiva);
    } else {
        renderRecetasPorCategoria({query});
    }
}

async function cargarProtocolos() {
    const contenedorId = 'contenedorProtocolos';
    setSectionState('protocolos', {
        type: 'loading',
        message: 'Buscando protocolos disponibles...',
        containerId: contenedorId,
    });

    try {
        const afiliacion = obtenerAfiliacion();
        const procedimientosData = await obtenerProcedimientosDesdeApi(afiliacion);
        uiState.procedimientos = procedimientosData;
        uiState.procedimientosVisibles = procedimientosData;
        uiState.categoriaProcedimientosActiva = '';

        const input = document.getElementById('searchProtocolos');
        if (input) input.value = '';
        actualizarBusquedaPlaceholder('searchProcedimientos', 'Buscar procedimiento');

        if (!procedimientosData.length) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('protocolos', {
                type: 'empty',
                message: 'No se encontraron protocolos configurados para esta afiliación.',
                containerId: contenedorId,
            });
            return;
        }

        renderProtocolosPorCategoria();
        setSectionState('protocolos', {type: 'ready', containerId: contenedorId});
    } catch (error) {
        console.error('Error cargando procedimientos desde MedForge:', error);
        let fallbackUsado = false;
        try {
            const fallbackData = await cargarJSON(safeGetURL('data/procedimientos.json'));
            const fallbackProcedimientos = Array.isArray(fallbackData?.procedimientos) ? fallbackData.procedimientos : [];
            if (fallbackProcedimientos.length) {
                fallbackUsado = true;
                uiState.procedimientos = fallbackProcedimientos;
                uiState.procedimientosVisibles = fallbackProcedimientos;
                uiState.categoriaProcedimientosActiva = '';
                renderProtocolosPorCategoria();
                setSectionState('protocolos', {type: 'ready', containerId: contenedorId});
                const estado = getSectionStateElement('protocolos');
                if (estado) {
                    estado.innerHTML = '';
                    const aviso = document.createElement('div');
                    aviso.className = 'state-message state-info';
                    aviso.textContent = 'Mostrando protocolos en modo sin conexión.';
                    estado.appendChild(aviso);
                }
            }
        } catch (fallbackError) {
            console.error('Error cargando protocolos de respaldo:', fallbackError);
        }

        if (!fallbackUsado) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('protocolos', {
                type: 'error',
                message: 'No pudimos cargar los protocolos. Intenta nuevamente.',
                containerId: contenedorId,
                action: {handler: cargarProtocolos},
            });
        }
    }
}

function renderProtocolosPorCategoria({query = ''} = {}) {
    const contenedorId = 'contenedorProtocolos';
    const procedimientos = uiState.procedimientos;

    actualizarBusquedaPlaceholder('searchProtocolos', 'Buscar protocolo');

    if (!query) {
        uiState.procedimientosVisibles = procedimientos;
        uiState.categoriaProcedimientosActiva = '';
    }

    if (!procedimientos.length) {
        document.getElementById(contenedorId).innerHTML = '';
        setSectionState('protocolos', {
            type: 'empty',
            message: 'Sin protocolos disponibles.',
            containerId: contenedorId,
        });
        return;
    }

    if (query) {
        const filtro = normalizarTexto(query);
        const resultados = procedimientos.filter((procedimiento) => {
            const texto = `${procedimiento.categoria || ''} ${procedimiento.cirugia || ''}`;
            return normalizarTexto(texto).includes(filtro);
        });

        if (!resultados.length) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('protocolos', {
                type: 'empty',
                message: `No hay protocolos que coincidan con "${query}".`,
                containerId: contenedorId,
            });
            return;
        }

        renderCardList(contenedorId, resultados, {
            getId: (item) => `procedimiento-${item.id}`,
            getIcon: () => 'fas fa-file-medical',
            getLabel: (item) => item.cirugia,
            getDescription: (item) => item.categoria || 'Protocolo clínico',
            onSelect: (item) => ejecutarProtocolos(item.id),
        });
        setSectionState('protocolos', {type: 'ready', containerId: contenedorId});
        return;
    }

    const categoriasMap = procedimientos.reduce((acc, procedimiento) => {
        const categoria = procedimiento.categoria || 'General';
        if (!acc.has(categoria)) {
            acc.set(categoria, 0);
        }
        acc.set(categoria, acc.get(categoria) + 1);
        return acc;
    }, new Map());

    const categorias = Array.from(categoriasMap.entries()).map(([categoria, total]) => ({categoria, total}));

    renderCardList(contenedorId, categorias, {
        getId: (item) => `categoria-protocolo-${normalizarTexto(item.categoria)}`,
        getIcon: () => 'fas fa-layer-group',
        getLabel: (item) => item.categoria,
        getDescription: (item) => `${item.total} procedimientos`,
        onSelect: (item) => mostrarProcedimientosPorCategoria(item.categoria),
    });
    setSectionState('protocolos', {type: 'ready', containerId: contenedorId});
}

function mostrarProcedimientosPorCategoria(categoria) {
    const procedimientos = uiState.procedimientos.filter((item) => (item.categoria || 'General') === categoria);
    uiState.procedimientosVisibles = procedimientos;
    uiState.categoriaProcedimientosActiva = categoria;
    actualizarBusquedaPlaceholder('searchProcedimientos', `Buscar en ${categoria}`);
    const input = document.getElementById('searchProcedimientos');
    if (input) input.value = '';

    renderProcedimientos(procedimientos);
    mostrarSeccion('procedimientos');
}

function renderProcedimientos(lista, {query = ''} = {}) {
    const contenedorId = 'contenedorProcedimientos';

    if (!lista.length) {
        document.getElementById(contenedorId).innerHTML = '';
        setSectionState('procedimientos', {
            type: 'empty',
            message: 'Selecciona una categoría para ver sus procedimientos.',
            containerId: contenedorId,
        });
        return;
    }

    let procedimientos = lista;
    if (query) {
        const filtro = normalizarTexto(query);
        procedimientos = lista.filter((item) => normalizarTexto(item.cirugia).includes(filtro));
        if (!procedimientos.length) {
            document.getElementById(contenedorId).innerHTML = '';
            setSectionState('procedimientos', {
                type: 'empty',
                message: `No se encontraron procedimientos para "${query}".`,
                containerId: contenedorId,
            });
            return;
        }
    }

    renderCardList(contenedorId, procedimientos, {
        getId: (item) => `procedimiento-${item.id}`,
        getIcon: () => 'fas fa-briefcase-medical',
        getLabel: (item) => item.cirugia,
        getDescription: (item) => item.categoria || uiState.categoriaProcedimientosActiva || 'Procedimiento',
        onSelect: (item) => ejecutarProtocolos(item.id),
    });

    setSectionState('procedimientos', {type: 'ready', containerId: contenedorId});
}

function aplicarFiltroProcedimientos(valor) {
    const query = valor.trim();
    const base = uiState.procedimientosVisibles.length ? uiState.procedimientosVisibles : uiState.procedimientos;
    if (!base.length) {
        renderProcedimientos([]);
        return;
    }
    renderProcedimientos(base, {query});
}

function aplicarFiltroProtocolos(valor) {
    const query = valor.trim();
    renderProtocolosPorCategoria({query});
}

async function ejecutarProtocolos(id) {
    try {
        const afiliacion = obtenerAfiliacion();
        const procedimientos = await obtenerProcedimientosDesdeApi(afiliacion);
        const item = procedimientos.find((d) => d.id === id);
        if (!item) {
            throw new Error('ID no encontrado en la base de datos');
        }
        await safeSendMessage({action: 'ejecutarProtocolo', item});
    } catch (error) {
        console.error('Error en la ejecución de protocolo:', error);
        setSectionState('procedimientos', {
            type: 'error',
            message: 'No se pudo ejecutar el protocolo seleccionado.',
            containerId: 'contenedorProcedimientos',
        });
    }
}

function ejecutarReceta(id) {
    cargarJSON(safeGetURL('data/recetas.json'))
        .then((data) => {
            const item = Array.isArray(data?.receta) ? data.receta.find((d) => d.id === id) : null;
            if (!item) throw new Error('ID no encontrado en el JSON');
            return safeSendMessage({action: 'ejecutarReceta', item});
        })
        .catch((error) => {
            console.error('Error en la ejecución de receta:', error);
            setSectionState('recetas', {
                type: 'error',
                message: 'No se pudo insertar la receta seleccionada.',
                containerId: 'contenedorRecetas',
            });
        });
}

function ejecutarExamenes(id) {
    cargarJSON(safeGetURL('data/examenes.json'))
        .then((data) => {
            const item = Array.isArray(data?.examenes) ? data.examenes.find((d) => d.id === id) : null;
            if (!item) throw new Error('ID no encontrado en el JSON');
            return ejecutarEnPagina(item);
        })
        .catch((error) => {
            console.error('Error en la ejecución de examen:', error);
            setSectionState('examenes', {
                type: 'error',
                message: 'No fue posible ejecutar el examen seleccionado.',
                containerId: 'contenedorExamenes',
            });
        });
}

async function cargarJSON(url) {
    const response = await fetch(url, {cache: 'no-store'});
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
}

chrome.runtime.onMessage.addListener((message) => {
    if (message.action === 'ejecutarExamenDirecto' && message.examenId) {
        ejecutarExamenes(message.examenId);
    }
});

window.cargarExamenes = cargarExamenes;
window.cargarRecetas = cargarRecetas;
window.cargarProtocolos = cargarProtocolos;
window.mostrarProcedimientosPorCategoria = mostrarProcedimientosPorCategoria;
window.aplicarFiltroRecetas = aplicarFiltroRecetas;
window.aplicarFiltroProcedimientos = aplicarFiltroProcedimientos;
window.aplicarFiltroProtocolos = aplicarFiltroProtocolos;
