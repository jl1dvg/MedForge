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
    cirugias: [],
    hcCirugia: '',
    doctores: [],
    lentes: [],
};
let __cirugiaAutoCheckDone = false;

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

function detectarHcNumber() {
    // Intenta leer de la ficha de paciente
    const cont = document.querySelector('.media-body');
    if (cont) {
        const ps = cont.querySelectorAll('p');
        for (const p of ps) {
            if (p.textContent && p.textContent.includes('HC #:')) {
                const hc = p.textContent.split('HC #:')[1]?.trim();
                if (hc) return hc;
            }
        }
    }
    // Inputs comunes en formularios
    const selectors = [
        '#docsolicitudprocedimientosdoctorsearch-hcnumber',
        'input[name="DocSolicitudProcedimientosDoctorSearch[hcNumber]"]',
        '#historiaclinica-historiaclinica',
        'input[name="HistoriaClinica[historiaClinica]"]'
    ];
    for (const sel of selectors) {
        const el = document.querySelector(sel);
        if (el && el.value) return el.value.trim();
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
    if (seccionId === 'cirugia' && typeof inicializarCirugiaSection === 'function') {
        inicializarCirugiaSection();
    }
};

function renderCirugias(lista) {
    const cont = document.getElementById('contenedorCirugias');
    if (!cont) return;
    cont.innerHTML = '';

    if (!Array.isArray(lista) || lista.length === 0) {
        setSectionState('cirugia', {
            type: 'empty',
            message: 'No se encontraron solicitudes quirúrgicas pendientes para este paciente.',
            containerId: 'contenedorCirugias',
        });
        return;
    }

    const badge = (estado) => {
        const norm = (estado || '').toString().toUpperCase();
        const map = {
            PENDIENTE: 'badge-warn',
            APROBADA: 'badge-ok',
            RECHAZADA: 'badge-error',
            EN_PROCESO: 'badge-info',
        };
        const cls = map[norm] || 'badge-info';
        return `<span class="badge ${cls}">${estado || 'SIN ESTADO'}</span>`;
    };

    const fragment = document.createDocumentFragment();
    lista.forEach((item, idx) => {
        const card = document.createElement('article');
        card.className = 'card';
        card.setAttribute('role', 'listitem');
        const hc = item?.hcNumber || item?.hc_number || uiState.hcCirugia || 'N/D';
        card.innerHTML = `
            <header class="card-header">
                <div>
                    <p class="card-kicker">Solicitud #${idx + 1}</p>
                    <h4>${item?.procedimiento || 'Procedimiento no especificado'}</h4>
                </div>
                ${badge(item?.estado)}
            </header>
            <div class="card-body">
                <p><strong>HC:</strong> ${hc}</p>
                <p><strong>Cirujano:</strong> ${item?.doctor || 'N/D'}</p>
                <p><strong>Fecha:</strong> ${item?.fecha || 'N/D'}</p>
                ${item?.afiliacion ? `<p><strong>Afiliación:</strong> ${item.afiliacion}</p>` : ''}
                ${item?.ojo ? `<p><strong>Ojo:</strong> ${item.ojo}</p>` : ''}
                ${item?.lente_nombre || item?.lente_id ? `<p><strong>Lente:</strong> ${item.lente_nombre || ''} ${item.lente_id ? '(' + item.lente_id + ')' : ''}</p>` : ''}
                ${item?.lente_poder ? `<p><strong>Poder:</strong> ${item.lente_poder}</p>` : ''}
                ${item?.incision ? `<p><strong>Incisión:</strong> ${item.incision}</p>` : ''}
                ${item?.observacion ? `<p><strong>Obs:</strong> ${item.observacion}</p>` : ''}
            </div>
            <footer class="card-footer">
                <button type="button" class="btn btn-sm btn-outline-primary" data-accion="editar" data-id="${item?.id || ''}">
                    Editar
                </button>
            </footer>
        `;
        card.querySelector('button[data-accion="editar"]')?.addEventListener('click', () => {
            if (item?.id) {
                abrirModalEditarSolicitud(item);
            } else {
                Swal.fire('Sin ID', 'No se puede editar porque la solicitud no trae ID.', 'warning');
            }
        });
        fragment.appendChild(card);
    });
    cont.appendChild(fragment);
    setSectionState('cirugia', {type: 'ready', containerId: 'contenedorCirugias'});
}

async function consultarCirugias(hcNumberInput) {
    const hc = (hcNumberInput || uiState.hcCirugia || detectarHcNumber() || '').trim();
    const inputEl = document.getElementById('inputHcCirugia');
    if (inputEl && !inputEl.value && hc) {
        inputEl.value = hc;
    }
    uiState.hcCirugia = hc;

    if (!hc) {
        setSectionState('cirugia', {
            type: 'error',
            message: 'Ingresa un HC para consultar las solicitudes.',
            containerId: 'contenedorCirugias',
        });
        return;
    }

    setSectionState('cirugia', {
        type: 'loading',
        message: `Consultando solicitudes quirúrgicas para HC ${hc}...`,
        containerId: 'contenedorCirugias',
    });

    try {
        const data = await obtenerEstadoCirugias(hc);
        const lista = Array.isArray(data?.solicitudes) ? data.solicitudes : (Array.isArray(data) ? data : []);
        uiState.cirugias = lista;
        renderCirugias(lista);
        if (lista.length > 0) {
            notificarCirugiasPrevias(lista, hc);
        }
    } catch (error) {
        console.error('Error consultando solicitudes quirúrgicas:', error);
        setSectionState('cirugia', {
            type: 'error',
            message: 'No fue posible obtener las solicitudes quirúrgicas. Revisa tu conexión o vuelve a intentar.',
            containerId: 'contenedorCirugias',
            action: {handler: () => consultarCirugias(hc), label: 'Reintentar'},
        });
    }
}

function inicializarCirugiaSection() {
    const input = document.getElementById('inputHcCirugia');
    if (!input) return;
    const hcDetectado = detectarHcNumber();
    if (hcDetectado) {
        input.value = hcDetectado;
        uiState.hcCirugia = hcDetectado;
    }
}

async function obtenerEstadoCirugias(hc) {
    // Solo vía background para evitar CORS desde la página
    if (isExtensionContextActive()) {
        const resp = await safeSendMessage({action: 'solicitudesEstado', hcNumber: hc});
        if (resp && resp.success !== false) {
            return resp.data ?? resp;
        }
        throw new Error(resp?.error || 'No se pudo consultar solicitudes');
    }
    throw new Error('Contexto de extensión no disponible para solicitudesEstado');
}

async function obtenerDoctores() {
    if (uiState.doctores && uiState.doctores.length) return uiState.doctores;
    if (!isExtensionContextActive()) throw new Error('Contexto extensión no activo');
    const resp = await safeSendMessage({action: 'listarDoctores'});
    if (resp && resp.success !== false) {
        const lista = Array.isArray(resp.doctores) ? resp.doctores : [];
        uiState.doctores = lista;
        return lista;
    }
    throw new Error(resp?.error || 'No se pudo obtener doctores');
}

async function obtenerLentes() {
    if (uiState.lentes && uiState.lentes.length) return uiState.lentes;
    if (!isExtensionContextActive()) throw new Error('Contexto extensión no activo');
    const resp = await safeSendMessage({action: 'listarLentes'});
    if (resp && resp.success !== false) {
        const lista = Array.isArray(resp.lentes) ? resp.lentes : [];
        uiState.lentes = lista;
        return lista;
    }
    throw new Error(resp?.error || 'No se pudo obtener lentes');
}

function generarPoderes(lente) {
    const powers = [];
    const min = lente?.rango_desde;
    const max = lente?.rango_hasta;
    const paso = lente?.rango_paso || 0.5;
    const inicioInc = lente?.rango_inicio_incremento || min;
    const toNum = (v) => (v === null || v === undefined || v === '' ? null : parseFloat(v));
    const minNum = toNum(min);
    const maxNum = toNum(max);
    const pasoNum = toNum(paso) || 0.5;
    const inicioNum = toNum(inicioInc);

    if (minNum !== null && maxNum !== null) {
        for (let v = minNum; v <= maxNum + 1e-6; v += pasoNum) {
            const rounded = Math.round(v * 100) / 100;
            if (inicioNum !== null && v < inicioNum && v > 0) continue; // saltar incrementos antes del inicio definido
            powers.push(rounded.toFixed(2));
        }
    }
    return powers;
}

function toDatetimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function actualizarSolicitud(id, payload) {
    if (!isExtensionContextActive()) {
        throw new Error('Contexto de extensión no disponible para actualizar solicitudes');
    }
    const resp = await safeSendMessage({action: 'solicitudActualizar', id, payload});
    if (resp && resp.success !== false) {
        return resp.data ?? resp;
    }
    throw new Error(resp?.error || 'No se pudo actualizar la solicitud');
}

function abrirModalEditarSolicitud(item) {
    const estado = item?.estado || '';
    const doctor = item?.doctor || '';
    const fecha = item?.fecha || '';
    const prioridad = item?.prioridad || '';
    const observacion = item?.observacion || '';
    const procedimiento = item?.procedimiento || '';
    const producto = item?.producto || '';
    const ojo = item?.ojo || item?.lateralidad || '';
    const afiliacion = item?.afiliacion || '';
    const duracion = item?.duracion || '';
    const lenteId = item?.lente_id || '';
    const lenteNombre = item?.lente_nombre || '';
    const lentePoder = item?.lente_poder || '';
    const lenteObs = item?.lente_observacion || '';
    const incision = item?.incision || '';

    const html = `
        <div class="cive-modal-card">
            <div class="cive-modal-section">
                <h4><i class="fas fa-user-md"></i> Datos de solicitud</h4>
                <div class="cive-row">
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Estado</label>
                            <input id="sol-estado" class="swal2-input" value="${estado}" placeholder="PENDIENTE / APROBADA / RECHAZADA" readonly />
                        </div>
                    </div>
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Doctor</label>
                            <select id="sol-doctor" class="swal2-select" data-value="${doctor}"></select>
                        </div>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Fecha</label>
                            <input id="sol-fecha" type="datetime-local" class="swal2-input" value="${toDatetimeLocal(fecha)}" />
                        </div>
                    </div>
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Prioridad</label>
                            <input id="sol-prioridad" class="swal2-input" value="${prioridad}" placeholder="URGENTE / NORMAL" readonly />
                        </div>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Producto</label>
                            <input id="sol-producto" class="swal2-input" value="${producto}" placeholder="Producto asociado" />
                        </div>
                    </div>
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Ojo</label>
                            <select id="sol-ojo" class="swal2-select">
                                <option value="">Selecciona ojo</option>
                                <option value="DERECHO"${ojo === 'DERECHO' ? ' selected' : ''}>DERECHO</option>
                                <option value="IZQUIERDO"${ojo === 'IZQUIERDO' ? ' selected' : ''}>IZQUIERDO</option>
                                <option value="AMBOS OJOS"${ojo === 'AMBOS OJOS' ? ' selected' : ''}>AMBOS OJOS</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Afiliación</label>
                            <input id="sol-afiliacion" class="swal2-input" value="${afiliacion}" placeholder="Afiliación" readonly />
                        </div>
                    </div>
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Duración</label>
                            <input id="sol-duracion" class="swal2-input" value="${duracion}" placeholder="Minutos" readonly />
                        </div>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-full cive-form-group">
                        <label>Procedimiento</label>
                        <textarea id="sol-procedimiento" class="swal2-textarea" rows="2" placeholder="Descripción">${procedimiento}</textarea>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-full cive-form-group">
                        <label>Observación</label>
                        <textarea id="sol-observacion" class="swal2-textarea" rows="2" placeholder="Notas">${observacion}</textarea>
                    </div>
                </div>
            </div>
            <div class="cive-modal-section">
                <h4><i class="fas fa-eye"></i> Lente e incisión</h4>
                <div class="cive-row">
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Lente</label>
                            <select id="sol-lente-id" class="swal2-select" data-value="${lenteId}">
                                <option value="">Selecciona lente</option>
                            </select>
                        </div>
                    </div>
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Nombre de lente</label>
                            <input id="sol-lente-nombre" class="swal2-input" value="${lenteNombre}" placeholder="Nombre del lente" readonly />
                        </div>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Poder del lente</label>
                            <select id="sol-lente-poder" class="swal2-select">
                                <option value="">Selecciona poder</option>
                            </select>
                        </div>
                    </div>
                    <div class="cive-col-6">
                        <div class="cive-form-group">
                            <label>Incisión</label>
                            <input id="sol-incision" class="swal2-input" value="${incision}" placeholder="Ej: Clear cornea temporal" />
                        </div>
                    </div>
                </div>
                <div class="cive-row">
                    <div class="cive-col-full cive-form-group">
                        <label>Observación de lente</label>
                        <textarea id="sol-lente-obs" class="swal2-textarea" rows="2" placeholder="Notas de lente">${lenteObs}</textarea>
                    </div>
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: `Editar solicitud #${item?.id || ''}`,
        html,
        width: 800,
        customClass: {popup: 'cive-modal-wide'},
        showCancelButton: true,
        confirmButtonText: 'Guardar cambios',
        cancelButtonText: 'Cancelar',
        focusConfirm: false,
        didOpen: async () => {
            // Poblar doctores en el select
            try {
                const doctores = await obtenerDoctores();
                const sel = document.getElementById('sol-doctor');
                if (sel) {
                    sel.innerHTML = '<option value="">Selecciona doctor</option>';
                    doctores.forEach((d) => {
                        const opt = document.createElement('option');
                        opt.value = d;
                        opt.textContent = d;
                        sel.appendChild(opt);
                    });
                    const preset = sel.dataset.value || doctor || '';
                    if (preset) sel.value = preset;
                }
            } catch (err) {
                console.warn('No se pudieron cargar doctores para el select:', err);
                const sel = document.getElementById('sol-doctor');
                if (sel) {
                    sel.innerHTML = '';
                    const opt = document.createElement('option');
                    opt.value = doctor;
                    opt.textContent = doctor || 'Doctor no disponible';
                    sel.appendChild(opt);
                }
            }

            // Poblar lentes en el select
            try {
                const lentes = await obtenerLentes();
                const selLente = document.getElementById('sol-lente-id');
                const selPoder = document.getElementById('sol-lente-poder');
                if (selLente) {
                    selLente.innerHTML = '<option value="">Selecciona lente</option>';
                    lentes.forEach((l) => {
                        const opt = document.createElement('option');
                        opt.value = l.id;
                        opt.textContent = `${l.marca} · ${l.modelo} · ${l.nombre}${l.poder ? ' (' + l.poder + ')' : ''}`;
                        opt.dataset.nombre = l.nombre;
                        opt.dataset.poder = l.poder || '';
                        opt.dataset.rango_desde = l.rango_desde ?? '';
                        opt.dataset.rango_hasta = l.rango_hasta ?? '';
                        opt.dataset.rango_paso = l.rango_paso ?? '';
                        opt.dataset.rango_inicio_incremento = l.rango_inicio_incremento ?? '';
                        selLente.appendChild(opt);
                    });
                    const preset = selLente.dataset.value || lenteId || '';
                    if (preset) selLente.value = preset;

                    const syncLente = () => {
                        const optSel = selLente.selectedOptions?.[0];
                        const nombre = optSel?.dataset?.nombre || '';
                        const poder = optSel?.dataset?.poder || '';
                        const rangoDesde = optSel?.dataset?.rango_desde || '';
                        const rangoHasta = optSel?.dataset?.rango_hasta || '';
                        const rangoPaso = optSel?.dataset?.rango_paso || '';
                        const rangoInicio = optSel?.dataset?.rango_inicio_incremento || '';
                        const nombreInput = document.getElementById('sol-lente-nombre');
                        const poderSelect = document.getElementById('sol-lente-poder');
                        if (nombreInput) nombreInput.value = nombre || nombreInput.value;
                        if (poderSelect) {
                            poderSelect.innerHTML = '<option value=\"\">Selecciona poder</option>';
                            const lenteObj = {
                                rango_desde: rangoDesde,
                                rango_hasta: rangoHasta,
                                rango_paso: rangoPaso,
                                rango_inicio_incremento: rangoInicio,
                            };
                            const powers = generarPoderes(lenteObj);
                            if (powers.length) {
                                powers.forEach((p) => {
                                    const optP = document.createElement('option');
                                    optP.value = p;
                                    optP.textContent = p;
                                    poderSelect.appendChild(optP);
                                });
                            }
                            if (poder) {
                                const optP = document.createElement('option');
                                optP.value = poder;
                                optP.textContent = poder;
                                poderSelect.appendChild(optP);
                            }
                            poderSelect.value = lentePoder || poder || '';
                        }
                    };
                    selLente.addEventListener('change', syncLente);
                    syncLente();
                }
            } catch (err) {
                console.warn('No se pudieron cargar lentes para el select:', err);
            }
        },
        preConfirm: () => {
            return {
                estado: document.getElementById('sol-estado')?.value.trim(),
                doctor: document.getElementById('sol-doctor')?.value.trim(),
                fecha: document.getElementById('sol-fecha')?.value.trim(),
                prioridad: document.getElementById('sol-prioridad')?.value.trim(),
                observacion: document.getElementById('sol-observacion')?.value.trim(),
                procedimiento: document.getElementById('sol-procedimiento')?.value.trim(),
                producto: document.getElementById('sol-producto')?.value.trim(),
                ojo: document.getElementById('sol-ojo')?.value.trim(),
                afiliacion: document.getElementById('sol-afiliacion')?.value.trim(),
                duracion: document.getElementById('sol-duracion')?.value.trim(),
                lente_id: document.getElementById('sol-lente-id')?.value.trim(),
                lente_nombre: document.getElementById('sol-lente-nombre')?.value.trim(),
                lente_poder: document.getElementById('sol-lente-poder')?.value.trim(),
                lente_observacion: document.getElementById('sol-lente-obs')?.value.trim(),
                incision: document.getElementById('sol-incision')?.value.trim(),
            };
        }
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        const payload = result.value || {};
        try {
            setSectionState('cirugia', {type: 'loading', message: 'Guardando cambios...', containerId: 'contenedorCirugias'});
            await actualizarSolicitud(item.id, payload);
            const idx = uiState.cirugias.findIndex((c) => c.id === item.id);
            if (idx >= 0) {
                uiState.cirugias[idx] = {...uiState.cirugias[idx], ...payload};
            }
            renderCirugias(uiState.cirugias);
            Swal.fire('Guardado', 'Solicitud actualizada en MedForge', 'success');
        } catch (err) {
            console.error('Error actualizando solicitud:', err);
            Swal.fire('Error', err?.message || 'No se pudo actualizar la solicitud', 'error');
            setSectionState('cirugia', {type: 'ready', containerId: 'contenedorCirugias'});
        }
    });
}

function notificarCirugiasPrevias(lista, hc) {
    if (!Array.isArray(lista) || lista.length === 0) return;
    const existeSwal = typeof Swal !== 'undefined' && Swal.fire;
    const resumen = lista.slice(0, 3).map((c) => `${c.procedimiento || 'Procedimiento'} · ${c.estado || 'Estado N/D'}`).join('\n');
    const texto = `HC ${hc}: ya existe(n) ${lista.length} solicitud(es) de cirugía.\n${resumen}${lista.length > 3 ? '\n…' : ''}`;
    if (existeSwal) {
        Swal.fire({
            icon: 'info',
            title: 'Solicitudes quirúrgicas detectadas',
            text: texto,
            confirmButtonText: 'Ver en planificador',
            footer: 'Revisa antes de crear una nueva solicitud.',
        }).then(() => {
            if (typeof window.mostrarSeccion === 'function') {
                window.mostrarSeccion('cirugia');
            }
        });
    } else {
        alert(texto);
    }
}

async function verificarCirugiasPreviasAuto() {
    if (__cirugiaAutoCheckDone) return;
    const hc = detectarHcNumber();
    if (!hc) return;
    __cirugiaAutoCheckDone = true;
    try {
        await consultarCirugias(hc);
    } catch (e) {
        console.warn('Chequeo automático de cirugías falló:', e);
    }
}

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
window.consultarCirugias = consultarCirugias;
window.inicializarCirugiaSection = inicializarCirugiaSection;
window.verificarCirugiasPreviasAuto = verificarCirugiasPreviasAuto;
