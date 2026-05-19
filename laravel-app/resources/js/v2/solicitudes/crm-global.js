import { createCrmPanel } from '../../shared/crmPanelFactory.js';

const config = window.__SOLICITUDES_CRM_PANEL__ || {};
const buttonSelector = config.buttonSelector || '.btn-open-solicitud-crm';
const basePath = config.basePath || '/v2/solicitudes';
const optionsEndpoint = config.optionsEndpoint || '/v2/solicitudes/crm/options';

function showToast(message, ok = true) {
    if (window.MEDF && typeof window.MEDF.showToast === 'function') {
        window.MEDF.showToast(message, ok);
        return;
    }

    let toast = document.querySelector('.solicitudes-crm-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'solicitudes-crm-toast';
        document.body.appendChild(toast);
    }

    toast.className = 'solicitudes-crm-toast ' + (ok ? 'ok' : 'err');
    toast.textContent = message;
    toast.style.display = 'block';
    window.clearTimeout(toast.dataset.hideTimer);
    toast.dataset.hideTimer = window.setTimeout(() => {
        toast.style.display = 'none';
    }, 3200);
}

const panel = createCrmPanel({
    showToast,
    getBasePath: () => basePath,
    entityLabel: 'solicitud',
    entityArticle: 'la',
    entitySelectionSuffix: 'seleccionada',
    datasetIdKey: 'solicitudId',
    buttonSelector,
});

async function loadOptions() {
    try {
        const response = await fetch(optionsEndpoint, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const payload = await response.json();
        panel.setCrmOptions(payload.options?.crm || payload.crm || payload.options || {});
    } catch (error) {
        console.warn('Solicitudes CRM ▶ no se pudieron cargar opciones CRM:', error);
        panel.setCrmOptions(config.options || {});
    }
}

function bindDelegatedOpen() {
    if (document.body.dataset.solicitudesCrmGlobalBound === '1') {
        return;
    }

    document.body.dataset.solicitudesCrmGlobalBound = '1';
    document.addEventListener('click', event => {
        const button = event.target.closest(buttonSelector);
        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const id = Number.parseInt(button.dataset.solicitudId || button.dataset.id || '', 10);
        if (!Number.isFinite(id) || id <= 0) {
            showToast('No se pudo identificar la solicitud seleccionada', false);
            return;
        }

        panel.openEntityCrm(id, button.dataset.pacienteNombre || button.dataset.paciente || '');
    });
}

async function init() {
    if (!document.getElementById('crmOffcanvas')) {
        return;
    }

    window.SolicitudesCrmPanel = panel;
    bindDelegatedOpen();
    panel.initCrmInteractions();
    await loadOptions();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
