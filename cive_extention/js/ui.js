const NAV_SECTIONS = [
    {id: 'inicio', icon: 'fas fa-compass', label: 'Inicio'},
    {id: 'protocolos', icon: 'fas fa-file-medical', label: 'Protocolos'},
    {id: 'procedimientos', icon: 'fas fa-briefcase-medical', label: 'Procedimientos'},
    {id: 'recetas', icon: 'fas fa-prescription-bottle-alt', label: 'Recetas'},
    {id: 'consulta', icon: 'fas fa-user-md', label: 'Consulta'},
    {id: 'cirugia', icon: 'fas fa-syringe', label: 'Cirugía'},
];

function createShortcutCard({id, icon, label, description}) {
    return `
        <button id="${id}" class="shortcut-card" type="button" role="listitem">
            <span class="shortcut-icon" aria-hidden="true"><i class="${icon}"></i></span>
            <span class="shortcut-content">
                <span class="shortcut-label">${label}</span>
                ${description ? `<span class="shortcut-description">${description}</span>` : ''}
            </span>
        </button>
    `;
}

function createNavigation() {
    return `
        <nav class="popup-nav" role="tablist" aria-label="Secciones del asistente">
            ${NAV_SECTIONS.map((section, index) => `
                <button
                    class="nav-item ${index === 0 ? 'active' : ''}"
                    id="tab-${section.id}"
                    data-target="${section.id}"
                    role="tab"
                    aria-selected="${index === 0}"
                    aria-controls="${section.id}"
                    type="button"
                >
                    <i class="${section.icon}" aria-hidden="true"></i>
                    <span>${section.label}</span>
                </button>
            `).join('')}
        </nav>
    `;
}

function createSectionShell({id, title, body, footer = '', tools = ''}) {
    return `
        <section id="${id}" class="section ${id === 'inicio' ? 'active' : ''}" role="tabpanel" aria-labelledby="tab-${id}" aria-hidden="${id === 'inicio' ? 'false' : 'true'}">
            <header class="section-header">
                <h3>${title}</h3>
                ${tools}
            </header>
            <div class="section-body">
                ${body}
                <div id="estado-${id}" class="section-state" aria-live="polite"></div>
            </div>
            ${footer}
        </section>
    `;
}

function buildPopupTemplate(isLocal) {
    const shortcutCards = [
        {id: 'btnProtocolos', icon: 'fas fa-file-medical', label: 'Protocolos', description: 'Plantillas estandarizadas por afiliación'},
        {id: 'btnConsulta', icon: 'fas fa-user-md', label: 'Consulta', description: 'Atajos hacia consultas previas y control POP'},
        {id: 'btnRecetas', icon: 'fas fa-prescription-bottle-alt', label: 'Recetas', description: 'Catálogo de recetas frecuentes'},
        {id: 'btnCirugia', icon: 'fas fa-syringe', label: 'Planificador de cirugía', description: 'Revisa solicitudes y evita duplicados'},
        ...(isLocal ? [{id: 'btnExamenes', icon: 'fas fa-notes-medical', label: 'Exámenes', description: 'Herramientas disponibles en modo local'}] : []),
    ];

    return `
        <div class="popup-content" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
            <header class="popup-header">
                <h2 id="popupTitle" tabindex="-1">Asistente CIVE</h2>
                <div class="popup-header-actions">
                    <button id="popupExpandBtn" class="icon-button" type="button" aria-label="Expandir panel">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button class="icon-button" id="popupCloseBtn" type="button" aria-label="Cerrar asistente">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </header>
            ${createNavigation()}
            <div class="popup-main" role="region" aria-live="polite">
                ${createSectionShell({
                    id: 'inicio',
                    title: 'Accesos rápidos',
                    body: `
                        <div class="shortcut-grid" role="list">
                            ${shortcutCards.map(createShortcutCard).join('')}
                        </div>
                    `,
                })}
                ${createSectionShell({
                    id: 'protocolos',
                    title: 'Protocolos',
                    tools: `
                        <label class="input-search">
                            <span class="sr-only">Buscar protocolo</span>
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input id="searchProtocolos" type="search" placeholder="Buscar protocolo" autocomplete="off" />
                        </label>
                    `,
                    body: '<div id="contenedorProtocolos" class="card-grid" role="list"></div>',
                    footer: `
                        <footer class="section-footer">
                            <button id="btnBackProtocolos" class="btn-secondary" type="button">
                                <i class="fas fa-arrow-alt-circle-left" aria-hidden="true"></i>
                                <span>Volver</span>
                            </button>
                        </footer>
                    `,
                })}
                ${createSectionShell({
                    id: 'procedimientos',
                    title: 'Procedimientos',
                    tools: `
                        <label class="input-search">
                            <span class="sr-only">Buscar procedimiento</span>
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input id="searchProcedimientos" type="search" placeholder="Buscar procedimiento" autocomplete="off" />
                        </label>
                    `,
                    body: '<div id="contenedorProcedimientos" class="card-grid" role="list"></div>',
                    footer: `
                        <footer class="section-footer">
                            <div class="footer-actions">
                                <button id="btnBackProcedimientos" class="btn-secondary" type="button">
                                    <i class="fas fa-arrow-alt-circle-left" aria-hidden="true"></i>
                                    <span>Volver</span>
                                </button>
                                <button id="btnGeneratePDF" class="btn-primary" type="button">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                    <span>Descargar PDF</span>
                                </button>
                            </div>
                        </footer>
                    `,
                })}
                ${createSectionShell({
                    id: 'recetas',
                    title: 'Recetas',
                    tools: `
                        <label class="input-search">
                            <span class="sr-only">Buscar receta</span>
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input id="searchRecetas" type="search" placeholder="Buscar receta" autocomplete="off" />
                        </label>
                    `,
                    body: '<div id="contenedorRecetas" class="card-grid" role="list"></div>',
                    footer: `
                        <footer class="section-footer">
                            <button id="btnBackRecetas" class="btn-secondary" type="button">
                                <i class="fas fa-arrow-alt-circle-left" aria-hidden="true"></i>
                                <span>Volver</span>
                            </button>
                        </footer>
                    `,
                })}
                ${createSectionShell({
                    id: 'consulta',
                    title: 'Consulta',
                    body: `
                        <div class="card-grid" role="list">
                            ${createShortcutCard({
                                id: 'btnConsultaAnterior',
                                icon: 'fas fa-history',
                                label: 'Consulta anterior',
                                description: 'Revisa rápidamente el último registro clínico',
                            })}
                            ${createShortcutCard({
                                id: 'btnPOP',
                                icon: 'fas fa-heartbeat',
                                label: 'Control POP',
                                description: 'Seguimiento del postoperatorio inmediato',
                            })}
                        </div>
                    `,
                    footer: `
                        <footer class="section-footer">
                            <button id="btnBackConsulta" class="btn-secondary" type="button">
                                <i class="fas fa-arrow-alt-circle-left" aria-hidden="true"></i>
                                <span>Volver</span>
                            </button>
                        </footer>
                    `,
                })}
                ${createSectionShell({
                    id: 'cirugia',
                    title: 'Planificador de cirugía',
                    tools: `
                        <div class="input-search" style="gap:8px; display:flex; align-items:center;">
                            <span class="sr-only">Buscar por HC</span>
                            <i class="fas fa-id-card" aria-hidden="true"></i>
                            <input id="inputHcCirugia" type="search" placeholder="HC del paciente" autocomplete="off" />
                            <button id="btnConsultarCirugia" class="btn-primary" type="button">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <span>Consultar</span>
                            </button>
                        </div>
                    `,
                    body: '<div id="contenedorCirugias" class="card-grid" role="list"></div>',
                    footer: `
                        <footer class="section-footer">
                            <button id="btnBackCirugia" class="btn-secondary" type="button">
                                <i class="fas fa-arrow-alt-circle-left" aria-hidden="true"></i>
                                <span>Volver</span>
                            </button>
                        </footer>
                    `,
                })}
                ${isLocal ? createSectionShell({
                    id: 'examenes',
                    title: 'Exámenes',
                    body: '<div id="contenedorExamenes" class="card-grid" role="list"></div>',
                    footer: `
                        <footer class="section-footer">
                            <button id="btnBackExamenes" class="btn-secondary" type="button">
                                <i class="fas fa-arrow-alt-circle-left" aria-hidden="true"></i>
                                <span>Volver</span>
                            </button>
                        </footer>
                    `,
                }) : ''}
            </div>
        </div>
    `;
}

function togglePopupState({popup, expanded}) {
    popup.classList.toggle('full-screen', expanded);
    const icon = popup.querySelector('#popupExpandBtn i');
    if (icon) {
        icon.classList.toggle('fa-expand', !expanded);
        icon.classList.toggle('fa-compress', expanded);
    }
    const label = expanded ? 'Contraer panel' : 'Expandir panel';
    popup.querySelector('#popupExpandBtn').setAttribute('aria-label', label);
}

window.inicializarUI = function () {
    // Evitar renderizar el asistente en dominios productivos de CIVE
    const host = (window.location && window.location.hostname || '').toLowerCase();
    if (host.includes('cive.consulmed.me') || host.includes('asistentecive.consulmed.me')) {
        console.info('CIVE Extension: UI deshabilitada en este dominio.');
        return;
    }

    const button = document.createElement('button');
    button.id = 'floatingButton';
    button.className = 'actionable-icon';
    button.type = 'button';
    button.setAttribute('aria-label', 'Abrir asistente CIVE. Atajo Alt+Shift+A');
    button.setAttribute('aria-haspopup', 'dialog');
    button.setAttribute('aria-controls', 'floatingPopup');
    button.setAttribute('aria-expanded', 'false');

    const img = document.createElement('img');
    img.src = chrome.runtime.getURL('icon.png');
    img.alt = '';
    img.setAttribute('aria-hidden', 'true');
    button.appendChild(img);

    // Eliminar texto/label del botón para dejar solo el ícono

    const statusBadge = document.createElement('span');
    statusBadge.className = 'status-badge';
    statusBadge.title = 'Estado de conexión';
    button.appendChild(statusBadge);

    const dragHandle = document.createElement('div');
    dragHandle.className = 'drag-handle';
    dragHandle.setAttribute('role', 'presentation');
    button.appendChild(dragHandle);

    document.body.appendChild(button);

    const popup = document.createElement('div');
    popup.id = 'floatingPopup';
    popup.setAttribute('role', 'presentation');
    popup.innerHTML = buildPopupTemplate(window.configCIVE && window.configCIVE.ES_LOCAL);
    document.body.appendChild(popup);

    const closeBtn = popup.querySelector('#popupCloseBtn');
    const expandBtn = popup.querySelector('#popupExpandBtn');
    const titleEl = popup.querySelector('#popupTitle');
    const navButtons = Array.from(popup.querySelectorAll('.popup-nav .nav-item'));

    function closePopup({restoreFocus = true} = {}) {
        togglePopupState({popup, expanded: false});
        popup.classList.remove('active');
        button.setAttribute('aria-expanded', 'false');
        if (restoreFocus) {
            button.focus();
        }
    }

    function openPopup() {
        popup.classList.add('active');
        button.setAttribute('aria-expanded', 'true');
        setTimeout(() => titleEl && titleEl.focus(), 0);
    }

    function togglePopup() {
        if (popup.classList.contains('active')) {
            closePopup();
        } else {
            openPopup();
        }
    }

    button.addEventListener('click', togglePopup);

    button.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            button.click();
        }
    });

    closeBtn.addEventListener('click', () => closePopup());

    expandBtn.addEventListener('click', () => {
        const expanded = !popup.classList.contains('full-screen');
        togglePopupState({popup, expanded});
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && popup.classList.contains('active')) {
            event.preventDefault();
            closePopup();
            return;
        }
        if (event.altKey && event.shiftKey && (event.key === 'A' || event.key === 'a')) {
            event.preventDefault();
            togglePopup();
        }
    });

    popup.addEventListener('click', (event) => {
        const navItem = event.target.closest && event.target.closest('.nav-item');
        if (navItem) {
            const target = navItem.getAttribute('data-target');
            if (window.mostrarSeccion) {
                window.mostrarSeccion(target);
            }
        }
    });

    navButtons.forEach((navButton) => {
        navButton.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navButton.click();
            }
        });
    });

    // Focus trap sencillo dentro del popup
    popup.addEventListener('keydown', (event) => {
        if (!popup.classList.contains('active') || event.key !== 'Tab') return;
        const focusables = popup.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const visible = Array.from(focusables).filter((el) => !el.disabled && el.offsetParent !== null);
        if (!visible.length) return;
        const first = visible[0];
        const last = visible[visible.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    // Estado online/offline para el badge
    function actualizarEstadoConexion() {
        const online = navigator.onLine;
        button.classList.toggle('offline', !online);
        statusBadge.title = online ? 'Conectado' : 'Sin conexión';
    }
    window.addEventListener('online', actualizarEstadoConexion);
    window.addEventListener('offline', actualizarEstadoConexion);
    actualizarEstadoConexion();

    // Restaurar última posición guardada
    function clampPosition(pos) {
        const padding = 8;
        const rect = button.getBoundingClientRect();
        const width = rect.width || button.offsetWidth || 64;
        const height = rect.height || button.offsetHeight || 48;
        const maxX = Math.max(padding, window.innerWidth - width - padding);
        const maxY = Math.max(padding, window.innerHeight - height - padding);
        return {
            x: Math.min(Math.max(padding, pos.x), maxX),
            y: Math.min(Math.max(padding, pos.y), maxY),
        };
    }

    function applyPosition(pos, {persist} = {}) {
        const clamped = clampPosition(pos);
        button.style.left = `${clamped.x}px`;
        button.style.top = `${clamped.y}px`;
        button.style.right = 'auto';
        button.style.bottom = 'auto';
        if (persist) {
            try {
                localStorage.setItem('civeFloatingPos', JSON.stringify(clamped));
            } catch (e) {
                console.warn('No se pudo guardar la posición del botón:', e);
            }
        }
        return clamped;
    }

    function restoreDefaultPosition() {
        button.style.left = '';
        button.style.top = '';
        button.style.right = '16px';
        button.style.bottom = '20px';
    }

    try {
        const pos = JSON.parse(localStorage.getItem('civeFloatingPos') || 'null');
        if (pos && typeof pos.x === 'number' && typeof pos.y === 'number') {
            const clamped = applyPosition(pos, {persist: true});
            const rect = button.getBoundingClientRect();
            const fueraDeVista = rect.left > window.innerWidth || rect.top > window.innerHeight || rect.right < 0 || rect.bottom < 0;
            if (fueraDeVista || clamped.x !== pos.x || clamped.y !== pos.y) {
                applyPosition(clamped, {persist: true});
            }
        } else {
            restoreDefaultPosition();
        }
    } catch (e) {
        console.warn('No se pudo restaurar posición del botón:', e);
        restoreDefaultPosition();
    }

    window.addEventListener('resize', () => {
        const rect = button.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        applyPosition({x: rect.left, y: rect.top}, {persist: true});
    });

    const estilos = [
        {href: 'css/floating_button.css'},
        {href: 'css/floating_popup.css'},
        {href: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'}
    ];

    estilos.forEach((estilo) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = estilo.href.startsWith('http') ? estilo.href : chrome.runtime.getURL(estilo.href);
        document.head.appendChild(link);
    });

    console.log('UI inicializada con navegación accesible.');
};
