import { actualizarEstadoExamen } from './estado.js';
import { showToast } from './toast.js';

const PREQUIRURGICO_DEBOUNCE_MS = 900;
let lastPrequirurgicoOpenAt = 0;
let prequirurgicoOpening = false;

function obtenerTarjetaActiva() {
    return document.querySelector('.kanban-card.view-details.active');
}

function cerrarModal() {
    const modalElement = document.getElementById('prefacturaModal');
    const instance = bootstrap.Modal.getInstance(modalElement);
    if (instance) {
        instance.hide();
    }
}

function abrirEnNuevaPestana(url) {
    if (!url) {
        return false;
    }

    const nuevaVentana = window.open(url, '_blank', 'noopener');
    if (nuevaVentana && typeof nuevaVentana.focus === 'function') {
        nuevaVentana.focus();
        return true;
    }

    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    anchor.style.position = 'absolute';
    anchor.style.left = '-9999px';
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);

    return false;
}

function buildCoberturaUrl(formId, hcNumber, pages) {
    if (!formId || !hcNumber) {
        return '';
    }

    const params = new URLSearchParams({
        form_id: formId,
        hc_number: hcNumber,
        variant: 'appendix',
    });

    if (pages) {
        params.set('pages', pages);
    }

    return `/reports/cobertura/pdf?${params.toString()}`;
}

function imprimirExamenesPrequirurgicos(tarjeta) {
    if (!tarjeta) {
        showToast('Selecciona un examen antes de solicitar exámenes', false);
        return false;
    }

    const now = Date.now();
    if (prequirurgicoOpening || now - lastPrequirurgicoOpenAt < PREQUIRURGICO_DEBOUNCE_MS) {
        return false;
    }
    prequirurgicoOpening = true;
    lastPrequirurgicoOpenAt = now;
    window.setTimeout(() => {
        prequirurgicoOpening = false;
    }, PREQUIRURGICO_DEBOUNCE_MS);

    const formId = tarjeta.dataset.form;
    const hcNumber = tarjeta.dataset.hc;
    if (!formId || !hcNumber) {
        showToast('No se encontró la información necesaria para imprimir los documentos.', false);
        return false;
    }

    const url = buildCoberturaUrl(formId, hcNumber, '007,010');
    const abierta = abrirEnNuevaPestana(url);

    if (!abierta) {
        showToast('Permite las ventanas emergentes para ver los documentos prequirúrgicos.', false);
    }

    return abierta;
}

function imprimirReferenciaCobertura(tarjeta) {
    if (!tarjeta) {
        showToast('Selecciona un examen antes de solicitar cobertura', false);
        return false;
    }

    const formId = tarjeta.dataset.form;
    const hcNumber = tarjeta.dataset.hc;
    if (!formId || !hcNumber) {
        showToast('No se encontró la información necesaria para imprimir los documentos.', false);
        return false;
    }

    const url = buildCoberturaUrl(formId, hcNumber, 'referencia');
    const abierta = abrirEnNuevaPestana(url);

    if (!abierta) {
        showToast('Permite las ventanas emergentes para ver el documento de cobertura.', false);
    }

    return abierta;
}

function actualizarDesdeBoton(nuevoEstado) {
    const tarjeta = obtenerTarjetaActiva();
    if (!tarjeta) {
        showToast('Selecciona un examen antes de continuar', false);
        return Promise.reject(new Error('No hay tarjeta activa'));
    }

    return actualizarEstadoExamen(
        tarjeta.dataset.id,
        tarjeta.dataset.form,
        nuevoEstado,
        window.__examenesKanban || [],
        window.aplicarFiltros
    ).then(() => cerrarModal());
}

export function inicializarBotonesModal() {
    const revisarBtn = document.getElementById('btnRevisarCodigos');
    if (revisarBtn && revisarBtn.dataset.listenerAttached !== 'true') {
        revisarBtn.dataset.listenerAttached = 'true';
        revisarBtn.addEventListener('click', () => {
            const estado = revisarBtn.dataset.estado || 'Revisión Códigos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }

    const examenesBtn = document.getElementById('btnSolicitarExamenesPrequirurgicos');
    if (examenesBtn && examenesBtn.dataset.listenerAttached !== 'true') {
        examenesBtn.dataset.listenerAttached = 'true';
        examenesBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const tarjeta = obtenerTarjetaActiva();
            imprimirExamenesPrequirurgicos(tarjeta);
        });
    }

    const coberturaBtn = document.getElementById('btnSolicitarCobertura');
    if (coberturaBtn && coberturaBtn.dataset.listenerAttached !== 'true') {
        coberturaBtn.dataset.listenerAttached = 'true';
        coberturaBtn.addEventListener('click', () => {
            const tarjeta = obtenerTarjetaActiva();
            imprimirReferenciaCobertura(tarjeta);

            const estado = coberturaBtn.dataset.estado || 'Docs Completos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }
}
